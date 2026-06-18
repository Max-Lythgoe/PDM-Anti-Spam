/**
 * PoW initialization — manager creation and solve orchestration.
 *
 * @module collector/init
 */

import { PoWManager } from './pow-manager';
import type { PoWFormConfig } from './pow-manager';
import type { GpshFrontendConfig } from '../frontend';
import { powManagers } from './state';
import { writePayloadField } from './payload';
import { isOnLastPage, bindPageWatcher } from './multi-page';
import { scheduleProactiveRefresh } from './refresh';
import { createLogger } from '@shared/logger';

const logger = createLogger('Collector');

/**
 * Creates a PoWManager with standard config, allowing overrides.
 */
export function createManager(
	formId: number,
	config: GpshFrontendConfig,
	overrides?: Partial<PoWFormConfig>
): PoWManager {
	return new PoWManager({
		formId,
		workerUrl: config.workerUrl ?? '',
		fallbackChallenge: config.powChallenge ?? null,
		fetchTimeout: 5000,
		...overrides,
	});
}

/**
 * Initializes (or re-initializes) the PoW manager for a form.
 *
 * Destroys any existing manager first to terminate the old worker
 * and clear the stale solution, then creates a new one with the
 * fresh challenge provided by the server.
 *
 * Once the solution is ready, eagerly writes the payload to the
 * hidden field so it's already populated before the user submits.
 *
 * For multi-page forms, solving is deferred until the user reaches
 * the last page. This avoids burning CPU on early pages and prevents
 * challenge expiry while the user fills out multiple pages.
 */
export function initPoW(formId: number, config: GpshFrontendConfig): void {
	// Destroy existing manager if present.
	const existing = powManagers.get(formId);
	if (existing) {
		logger.log(`formId ${formId} — destroying existing PoWManager`);
		existing.destroy();
		powManagers.delete(formId);
	}

	if (!config.powEnabled) {
		logger.log(`formId ${formId} — PoW disabled, skipping`);
		return;
	}

	if (!config.powChallenge) {
		logger.warn(
			`formId ${formId} — powEnabled but powChallenge is missing!`
		);
		return;
	}

	// powOnSubmit mode: create manager but defer solving until form submission.
	if (config.powOnSubmit) {
		logger.log(
			`formId ${formId} — powOnSubmit mode: deferring solve until submit`
		);
		const manager = createManager(formId, config);
		powManagers.set(formId, manager);
		return; // Do NOT call manager.init()
	}

	// Multi-page form: defer solving until the user reaches the last page.
	// This prevents challenge expiry while the user fills out earlier pages
	// and avoids unnecessary CPU usage on pages that don't submit.
	if (config.hasPages && !isOnLastPage(formId)) {
		logger.log(
			`formId ${formId} — multi-page form, not on last page: deferring PoW solve`
		);
		const manager = createManager(formId, config);
		powManagers.set(formId, manager);

		// Bind a page-change watcher so we start solving when the user
		// reaches the last page (handles both AJAX and postback navigation).
		bindPageWatcher(formId, config);
		return; // Do NOT call manager.init() yet
	}

	logger.log(`formId ${formId} — creating PoWManager`);

	const manager = createManager(formId, config);

	powManagers.set(formId, manager);

	// Start solving, then eagerly populate the hidden field once solved.
	startSolving(formId, manager, config);
}

/**
 * Solves the PoW challenge and calls onSolution with the result.
 *
 * Shared by startSolving (GF forms) and comment/index.ts.
 *
 * @param manager    - The PoWManager instance to use.
 * @param onSolution - Called with the solution when ready.
 * @param onTimeout  - Optional callback when waitForSolution() times out.
 */
export function solveAndWrite(
	manager: PoWManager,
	onSolution: (
		solution: NonNullable<ReturnType<PoWManager['getSolution']>>
	) => void,
	onTimeout?: () => void
): void {
	manager
		.init()
		.then(() => manager.waitForSolution())
		.then((solution) => {
			if (!solution) {
				onTimeout?.();
				return;
			}
			onSolution(solution);
		})
		.catch((err) => {
			// PoW init failure is non-fatal; server will see missing solution.
			logger.error('PoWManager.init() threw:', err);
		});
}

/**
 * Starts solving the PoW challenge and eagerly populates the hidden field.
 *
 * Extracted from initPoW() so it can be called both on initial init
 * (single-page forms / last page) and when a page-change watcher fires.
 */
export function startSolving(
	formId: number,
	manager: PoWManager,
	config: GpshFrontendConfig
): void {
	solveAndWrite(
		manager,
		(solution) => {
			writePayloadField(formId, solution, config.payloadField);

			// Schedule a proactive re-solve before the challenge expires.
			// This runs in the background so the submission hook rarely
			// needs to delay the user.
			scheduleProactiveRefresh(formId, manager, config);
		},
		() =>
			logger.warn(
				`formId ${formId} — waitForSolution() timed out, payload field will be empty`
			)
	);
}
