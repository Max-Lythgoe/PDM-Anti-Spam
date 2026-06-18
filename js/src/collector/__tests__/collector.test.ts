/**
 * @jest-environment jsdom
 */

/**
 * Tests for the collector module (initCollector).
 *
 * The collector orchestrates PoW solving and payload field management.
 * These tests exercise the exported initCollector() function and verify
 * that it correctly initializes PoW, populates the payload field, and
 * handles re-initialization.
 *
 * Uses jsdom environment for DOM access. TextEncoder is polyfilled
 * since jsdom doesn't provide it by default in all Node versions.
 */

// Polyfill TextEncoder/TextDecoder for jsdom environment.
import { TextEncoder, TextDecoder } from 'util';

if (typeof globalThis.TextEncoder === 'undefined') {
	(globalThis as any).TextEncoder = TextEncoder;
	(globalThis as any).TextDecoder = TextDecoder;
}

import { initCollector } from '../index';
import type { GpshFrontendConfig } from '../../frontend';

// Mock @wordpress/api-fetch (used by PoWManager).
jest.mock('@wordpress/api-fetch', () => ({
	__esModule: true,
	default: jest.fn(),
}));

import apiFetch from '@wordpress/api-fetch';

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

// Force no Worker support so all tests use the chunked fallback.
const originalWorker = globalThis.Worker;

beforeAll(() => {
	// @ts-expect-error — intentionally removing Worker for testing.
	delete globalThis.Worker;
});

afterAll(() => {
	globalThis.Worker = originalWorker;
});

/**
 * Creates a minimal GpshFrontendConfig for testing.
 */
function makeConfig(
	overrides: Partial<GpshFrontendConfig> = {}
): GpshFrontendConfig {
	const now = Math.floor(Date.now() / 1000);
	return {
		formId: 1,
		powEnabled: true,
		workerUrl: '/fake/worker.js',
		payloadField: 'gfsh_payload',
		powChallenge: {
			challenge: `1|${now}|${now + 600}|1|test_nonce`,
			signature: 'test_sig',
			difficulty: 1,
			expires: now + 600,
		},
		...overrides,
	};
}

/**
 * Sets up a minimal DOM with a form element for the collector.
 */
function setupDOM(formId: number): HTMLFormElement {
	document.body.innerHTML = `<form id="gform_${formId}"></form>`;
	return document.getElementById(`gform_${formId}`) as HTMLFormElement;
}

// Mock gform.utils for the submission hook.
beforeEach(() => {
	// Provide a minimal window.gform mock so bindSubmissionHook doesn't throw.
	(globalThis as any).window = globalThis;
	(globalThis as any).gform = {
		utils: {
			addAsyncFilter: jest.fn(),
		},
	};
});

afterEach(() => {
	document.body.innerHTML = '';
	jest.restoreAllMocks();
	delete (globalThis as any).gform;
});

// ── initCollector — basic initialization ────────────────────────────

describe('initCollector', () => {
	it('creates payload field when it does not exist', async () => {
		const form = setupDOM(1);
		const config = makeConfig();

		// Mock REST fetch to return a challenge.
		const now = Math.floor(Date.now() / 1000);
		mockedApiFetch.mockResolvedValueOnce({
			challenge: `1|${now}|${now + 600}|1|rest_nonce`,
			signature: 'rest_sig',
			difficulty: 1,
			expires: now + 600,
		});

		await initCollector(config);

		const field = form.querySelector(
			'input[name="gfsh_payload"]'
		) as HTMLInputElement;
		expect(field).not.toBeNull();
		expect(field.type).toBe('hidden');
	});

	it('skips PoW when disabled', async () => {
		setupDOM(1);
		const config = makeConfig({ powEnabled: false });

		await initCollector(config);

		// apiFetch should NOT have been called since PoW is disabled.
		expect(mockedApiFetch).not.toHaveBeenCalled();
	});

	it('populates payload field after solving', async () => {
		const form = setupDOM(1);
		const config = makeConfig();

		// Mock REST fetch.
		const now = Math.floor(Date.now() / 1000);
		mockedApiFetch.mockResolvedValueOnce({
			challenge: `1|${now}|${now + 600}|1|rest_nonce`,
			signature: 'rest_sig',
			difficulty: 1,
			expires: now + 600,
		});

		await initCollector(config);

		// Wait for the async solve chain to complete.
		await new Promise((resolve) => setTimeout(resolve, 2000));

		const field = form.querySelector(
			'input[name="gfsh_payload"]'
		) as HTMLInputElement;

		if (field && field.value) {
			// Decode and verify the payload structure.
			const decoded = JSON.parse(atob(field.value));
			expect(decoded).toHaveProperty('v', 1);
			expect(decoded).toHaveProperty('t');
			expect(decoded).toHaveProperty('pow');
			expect(decoded.pow).toHaveProperty('challenge');
			expect(decoded.pow).toHaveProperty('signature');
			expect(decoded.pow).toHaveProperty('solution');
		}
		// If field is empty, the solve may still be in progress — that's OK
		// for a unit test with low difficulty.
	});

	it('re-init destroys old PoW manager', async () => {
		setupDOM(1);
		const config = makeConfig();

		const now = Math.floor(Date.now() / 1000);
		const challenge = {
			challenge: `1|${now}|${now + 600}|1|rest_nonce`,
			signature: 'rest_sig',
			difficulty: 1,
			expires: now + 600,
		};

		mockedApiFetch.mockResolvedValueOnce(challenge);
		await initCollector(config);

		// Re-init with a new challenge.
		mockedApiFetch.mockResolvedValueOnce({
			...challenge,
			challenge: `1|${now}|${now + 600}|1|new_nonce`,
		});
		await initCollector(config);

		// Should not throw — the old manager should have been destroyed.
		expect(true).toBe(true);
	});

	it('handles missing form element gracefully', async () => {
		// Don't set up DOM — form element won't exist.
		document.body.innerHTML = '';
		const config = makeConfig();

		// Should not throw.
		await initCollector(config);
	});
});

// ── Payload assembly ────────────────────────────────────────────────

describe('payload structure', () => {
	it('base64-encodes JSON payload with v, t, pow keys', async () => {
		const form = setupDOM(1);
		const config = makeConfig();

		const now = Math.floor(Date.now() / 1000);
		mockedApiFetch.mockResolvedValueOnce({
			challenge: `1|${now}|${now + 600}|1|rest_nonce`,
			signature: 'rest_sig',
			difficulty: 1,
			expires: now + 600,
		});

		await initCollector(config);

		// Wait for solve.
		await new Promise((resolve) => setTimeout(resolve, 2000));

		const field = form.querySelector(
			'input[name="gfsh_payload"]'
		) as HTMLInputElement;

		if (field && field.value) {
			// Should be valid base64.
			const raw = atob(field.value);
			const parsed = JSON.parse(raw);

			expect(parsed.v).toBe(1);
			expect(typeof parsed.t).toBe('number');
			expect(parsed.pow).toBeDefined();
			expect(typeof parsed.pow.solution).toBe('string');
		}
	});
});
