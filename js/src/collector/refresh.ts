/**
 * PoW refresh logic — proactive and on-demand challenge re-solving.
 *
 * @module collector/refresh
 */

import type { PoWManager } from './pow-manager';
import type { GpshFrontendConfig } from '../frontend';
import { powManagers, refreshingForms } from './state';
import { createManager } from './init';
import { writePayloadField } from './payload';
import { createLogger } from '@shared/logger';

const logger = createLogger('Collector');

/**
 * Destroys the current PoW manager, creates a fresh one, solves,
 * and updates the hidden payload field.
 *
 * Used by both the submission hook (on-demand) and the proactive
 * timer (background). The `refreshingForms` guard prevents concurrent
 * refreshes for the same form.
 */
export async function refreshPoW(
	formId: number,
	config: GpshFrontendConfig
): Promise<void> {
	// Already refreshing — wait for the existing refresh instead.
	if (refreshingForms.has(formId)) {
		logger.log(`formId ${formId} — refresh already in progress, waiting…`);
		const manager = powManagers.get(formId);
		if (manager) {
			await manager.waitForSolution(10_000);
		}
		return;
	}

	refreshingForms.add(formId);

	try {
		// Destroy existing manager.
		const existing = powManagers.get(formId);
		if (existing) {
			existing.destroy();
			powManagers.delete(formId);
		}

		// Create fresh manager.
		const manager = createManager(formId, config);
		powManagers.set(formId, manager);

		// Solve.
		await manager.init();
		const solution = await manager.waitForSolution(10_000);

		if (solution) {
			writePayloadField(formId, solution, config.payloadField);
			logger.log(
				`formId ${formId} — payload field updated with fresh solution`
			);
		} else {
			logger.warn(
				`formId ${formId} — refreshPoW timed out, submitting with stale/missing PoW`
			);
		}
	} finally {
		refreshingForms.delete(formId);
	}
}

/**
 * Schedules a proactive re-solve before the challenge expires.
 *
 * Fires 60 seconds before expiry so the user never notices. The
 * submission hook becomes a safety net for edge cases (e.g., the
 * proactive timer was cleared by a page transition).
 *
 * If the tab is hidden when the timer fires, defers the refresh
 * until the tab becomes visible again — no point burning CPU in
 * a background tab the user may have left open for hours.
 */
export function scheduleProactiveRefresh(
	formId: number,
	manager: PoWManager,
	config: GpshFrontendConfig
): void {
	// Already within 60s of expiry — don't bother scheduling.
	if (manager.isExpiredOrExpiring(60)) {
		return;
	}

	// Parse the challenge expiry from the config's fallback challenge.
	// The actual challenge may have come from REST (different expiry),
	// but we use isExpiredOrExpiring() at fire time to decide.
	const REFRESH_BUFFER = 60; // seconds before expiry
	const challengeExpires = config.powChallenge?.expires ?? 0;

	if (!challengeExpires) {
		return;
	}

	const timeUntilRefresh =
		(challengeExpires - Date.now() / 1000 - REFRESH_BUFFER) * 1000;

	if (timeUntilRefresh <= 0) {
		return;
	}

	logger.log(
		`formId ${formId} — scheduling proactive re-solve in ${Math.round(timeUntilRefresh / 1000)}s`
	);

	setTimeout(() => {
		// Only refresh if this manager is still the active one for this form.
		if (powManagers.get(formId) !== manager) {
			return;
		}

		// If the tab is hidden, defer until it becomes visible.
		// No point solving a PoW puzzle in a background tab — the user
		// isn't going to submit while the tab is hidden, and we'd just
		// burn CPU for nothing. The submission hook is the safety net.
		if (document.hidden) {
			logger.log(
				`formId ${formId} — tab hidden, deferring re-solve until visible`
			);
			const onVisible = () => {
				document.removeEventListener('visibilitychange', onVisible);
				// Re-check that this manager is still active.
				if (powManagers.get(formId) === manager) {
					logger.log(
						`formId ${formId} — tab visible again, re-solving now`
					);
					refreshPoW(formId, config);
				}
			};
			document.addEventListener('visibilitychange', onVisible);
			return;
		}

		logger.log(`formId ${formId} — proactive re-solve timer fired`);
		refreshPoW(formId, config);
	}, timeUntilRefresh);
}
