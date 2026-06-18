/**
 * Tests for PoWManager — client-side PoW orchestrator.
 *
 * These tests run in the Node environment (no real Web Workers).
 * They exercise the main-thread chunked fallback solver and the
 * state management (getSolution, isSolving, waitForSolution).
 *
 * The apiFetch module is mocked to simulate REST endpoint responses.
 * Worker is set to undefined to force the chunked fallback path.
 */

import { PoWManager } from '../pow-manager';
import type { PoWChallenge, PoWFormConfig } from '../pow-manager';

// Mock @wordpress/api-fetch.
jest.mock('@wordpress/api-fetch', () => {
	return {
		__esModule: true,
		default: jest.fn(),
	};
});

import apiFetch from '@wordpress/api-fetch';

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

/**
 * Creates a test challenge at low difficulty for fast solving (5-field REST format).
 */
function makeChallenge(formId = 1, difficulty = 1): PoWChallenge {
	return {
		challenge: `${formId}|${Math.floor(Date.now() / 1000)}|${Math.floor(Date.now() / 1000) + 600}|${difficulty}|test_nonce_abc`,
		signature: 'test_signature_123',
		difficulty,
		expires: Math.floor(Date.now() / 1000) + 600,
	};
}

/**
 * Creates a test fallback challenge (4-field, no server nonce).
 */
function makeFallbackChallenge(formId = 1, difficulty = 1): PoWChallenge {
	return {
		challenge: `${formId}|${Math.floor(Date.now() / 1000)}|${Math.floor(Date.now() / 1000) + 86400}|${difficulty}`,
		signature: 'test_signature_fallback',
		difficulty,
		expires: Math.floor(Date.now() / 1000) + 86400,
	};
}

/**
 * Creates a default PoWFormConfig for testing.
 */
function makeConfig(overrides: Partial<PoWFormConfig> = {}): PoWFormConfig {
	return {
		formId: 1,
		workerUrl: '/fake/worker.js',
		fallbackChallenge: null,
		fetchTimeout: 3000,
		...overrides,
	};
}

// Force no Worker support so all tests use the chunked fallback.
const originalWorker = globalThis.Worker;

beforeAll(() => {
	// @ts-expect-error — intentionally removing Worker for testing.
	delete globalThis.Worker;
});

afterAll(() => {
	globalThis.Worker = originalWorker;
});

// ── Fetching + solving ──────────────────────────────────────────────

describe('PoWManager with REST fetch', () => {
	it('should fetch a challenge and solve it via chunked fallback', async () => {
		const challenge = makeChallenge(1, 1);
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();

		// Wait for the chunked solver to find a solution.
		const solution = await manager.waitForSolution(5000);

		expect(solution).not.toBeNull();
		expect(solution!.challenge).toBe(challenge.challenge);
		expect(solution!.signature).toBe(challenge.signature);
		expect(solution!.is_fallback).toBe(false);
		expect(typeof solution!.solution).toBe('string');
		expect(solution!.solve_time_ms).toBeGreaterThanOrEqual(0);
	});

	it('should call apiFetch with correct path and method', async () => {
		const challenge = makeChallenge(42, 1);
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig({ formId: 42 }));
		await manager.init();

		expect(mockedApiFetch).toHaveBeenCalledWith(
			expect.objectContaining({
				path: '/gfsh/v1/challenge',
				method: 'POST',
				data: { form_id: 42 },
			})
		);

		// Clean up — wait for solve to finish.
		await manager.waitForSolution(5000);
	});
});

// ── Fallback challenge ──────────────────────────────────────────────

describe('PoWManager with fallback challenge', () => {
	it('should use fallback when REST fetch fails', async () => {
		const fallback = makeChallenge(1, 1);
		mockedApiFetch.mockRejectedValueOnce(new Error('Network error'));

		const manager = new PoWManager(
			makeConfig({ fallbackChallenge: fallback })
		);
		await manager.init();

		const solution = await manager.waitForSolution(5000);

		expect(solution).not.toBeNull();
		expect(solution!.challenge).toBe(fallback.challenge);
		expect(solution!.is_fallback).toBe(true);
	});

	it('should have no solution when fetch fails and no fallback', async () => {
		mockedApiFetch.mockRejectedValueOnce(new Error('Network error'));

		const manager = new PoWManager(makeConfig({ fallbackChallenge: null }));
		await manager.init();

		// No fallback, no solution.
		expect(manager.getSolution()).toBeNull();
		expect(manager.isSolving()).toBe(false);
	});
});

// ── State management ────────────────────────────────────────────────

describe('PoWManager state', () => {
	it('should report isSolving while solving', async () => {
		const challenge = makeChallenge(1, 4); // Slightly harder.
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();

		// Should be solving immediately after init.
		expect(manager.isSolving()).toBe(true);

		// Wait for completion.
		await manager.waitForSolution(10000);
		expect(manager.isSolving()).toBe(false);
	});

	it('should return solution immediately from getSolution after solving', async () => {
		const challenge = makeChallenge(1, 1);
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();
		await manager.waitForSolution(5000);

		// getSolution should return immediately now.
		const solution = manager.getSolution();
		expect(solution).not.toBeNull();
	});

	it('waitForSolution should resolve immediately if already solved', async () => {
		const challenge = makeChallenge(1, 1);
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();
		await manager.waitForSolution(5000);

		// Second call should resolve instantly.
		const start = Date.now();
		const solution = await manager.waitForSolution(5000);
		const elapsed = Date.now() - start;

		expect(solution).not.toBeNull();
		expect(elapsed).toBeLessThan(100); // Should be near-instant.
	});

	it('waitForSolution should return null on timeout', async () => {
		// Use very high difficulty so it can't solve in time.
		const challenge = makeChallenge(1, 30);
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();

		// Very short timeout — should return null.
		const solution = await manager.waitForSolution(50);
		expect(solution).toBeNull();

		// Clean up.
		manager.destroy();
	});
});

// ── Challenge expiry ────────────────────────────────────────────────

describe('PoWManager.isExpiredOrExpiring', () => {
	it('should return false when no solution exists yet', async () => {
		mockedApiFetch.mockRejectedValueOnce(new Error('Network error'));

		const manager = new PoWManager(makeConfig({ fallbackChallenge: null }));
		await manager.init();

		expect(manager.isExpiredOrExpiring()).toBe(false);
	});

	it('should return false when challenge is still fresh', async () => {
		// Challenge expires 600s from now — well within buffer.
		const challenge = makeChallenge(1, 1);
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();
		await manager.waitForSolution(5000);

		// Default buffer is 30s. Challenge expires in ~600s.
		expect(manager.isExpiredOrExpiring()).toBe(false);
		expect(manager.isExpiredOrExpiring(30)).toBe(false);
	});

	it('should return true when challenge is within buffer of expiry', async () => {
		// Create a challenge that expires in 20 seconds.
		const now = Math.floor(Date.now() / 1000);
		const challenge: PoWChallenge = {
			challenge: `1|${now}|${now + 20}|1|test_nonce_expiring`,
			signature: 'test_signature_123',
			difficulty: 1,
			expires: now + 20,
		};
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();
		await manager.waitForSolution(5000);

		// With default 30s buffer, a challenge expiring in 20s is "expiring".
		expect(manager.isExpiredOrExpiring(30)).toBe(true);
		// With a 10s buffer, it's still valid.
		expect(manager.isExpiredOrExpiring(10)).toBe(false);
	});

	it('should return true when challenge is already expired', async () => {
		// Create a challenge that expired 60 seconds ago.
		const now = Math.floor(Date.now() / 1000);
		const challenge: PoWChallenge = {
			challenge: `1|${now - 660}|${now - 60}|1|test_nonce_expired`,
			signature: 'test_signature_123',
			difficulty: 1,
			expires: now - 60,
		};
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();
		await manager.waitForSolution(5000);

		expect(manager.isExpiredOrExpiring()).toBe(true);
		expect(manager.isExpiredOrExpiring(0)).toBe(true);
	});
});

// ── Fallback client nonce ───────────────────────────────────────────

describe('PoWManager fallback client nonce', () => {
	it('should generate client_nonce when using fallback challenge', async () => {
		const fallback = makeFallbackChallenge(1, 1);
		mockedApiFetch.mockRejectedValueOnce(new Error('Network error'));

		const manager = new PoWManager(
			makeConfig({ fallbackChallenge: fallback })
		);
		await manager.init();

		const solution = await manager.waitForSolution(5000);

		expect(solution).not.toBeNull();
		expect(solution!.client_nonce).toBeDefined();
		expect(typeof solution!.client_nonce).toBe('string');
		expect(solution!.client_nonce!.length).toBeGreaterThan(0);
		expect(solution!.is_fallback).toBe(true);
	});

	it('should not include client_nonce for REST challenges', async () => {
		const challenge = makeChallenge(1, 1);
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();

		const solution = await manager.waitForSolution(5000);

		expect(solution).not.toBeNull();
		expect(solution!.client_nonce).toBeUndefined();
		expect(solution!.is_fallback).toBe(false);
	});

	it('should include client_nonce in hash computation for fallback', async () => {
		const fallback = makeFallbackChallenge(1, 1);
		mockedApiFetch.mockRejectedValueOnce(new Error('Network error'));

		const manager = new PoWManager(
			makeConfig({ fallbackChallenge: fallback })
		);
		await manager.init();

		const solution = await manager.waitForSolution(5000);

		expect(solution).not.toBeNull();

		// Verify the solution is valid against challenge|client_nonce|counter.
		const { computeSha256, hasLeadingZeroBits } = await import(
			'../../collector/pow-utils'
		);
		const hashInput =
			fallback.challenge +
			'|' +
			solution!.client_nonce +
			'|' +
			solution!.solution;
		const hashArray = await computeSha256(hashInput);
		expect(hasLeadingZeroBits(hashArray, fallback.difficulty)).toBe(true);
	});
});

// ── Cleanup ─────────────────────────────────────────────────────────

describe('PoWManager cleanup', () => {
	it('destroy should stop solving', async () => {
		const challenge = makeChallenge(1, 20); // Hard enough to take a while.
		mockedApiFetch.mockResolvedValueOnce(challenge);

		const manager = new PoWManager(makeConfig());
		await manager.init();

		expect(manager.isSolving()).toBe(true);

		manager.destroy();
		expect(manager.isSolving()).toBe(false);
	});
});
