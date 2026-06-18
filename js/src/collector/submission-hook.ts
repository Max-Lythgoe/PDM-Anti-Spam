/**
 * GF submission hook — ensures PoW is fresh before form submission.
 *
 * @module collector/submission-hook
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import { powManagers, formConfigs } from './state';
import { isOnLastPage } from './multi-page';
import { writePayloadField } from './payload';
import { refreshPoW } from './refresh';
import { createLogger } from '@shared/logger';

const logger = createLogger('Collector');

/**
 * Registers an async filter on GF's pre_submission hook to ensure
 * the PoW challenge is fresh before the form actually submits.
 *
 * This is a one-time global registration — not per-form.
 * The filter checks the specific form being submitted.
 *
 * Deferred to the first `initCollector()` call to ensure
 * `window.gform.utils` is available (it may not be at module load time).
 */
/**
 * @return `true` if the hook was successfully bound, `false` if GF's
 *          hook system wasn't available (caller should retry later).
 */
export function bindSubmissionHook(): boolean {
	// Guard: only bind if GF's modern hook system is available (≥2.9.0).
	const gformUtils = (window as any).gform?.utils;
	if (!gformUtils?.addAsyncFilter) {
		logger.warn(
			'GF async filter system not available — skipping submission hook'
		);
		return false;
	}

	gformUtils.addAsyncFilter(
		'gform/submission/pre_submission',
		async (data: Record<string, unknown>) => {
			// Respect prior abort.
			if (data.abort) {
				return data;
			}

			// Only intercept submissions that will be validated server-side.
			const validTypes = ['submit', 'next', 'save_and_continue'];
			if (!validTypes.includes(data.submissionType as string)) {
				return data;
			}

			const form = data.form as HTMLFormElement;
			const formId = parseInt(form.dataset.formid || '0', 10);
			if (!formId) {
				return data;
			}

			const manager = powManagers.get(formId);
			const config = formConfigs.get(formId);
			if (!manager || !config) {
				return data;
			}

			// Multi-page form: skip PoW work when navigating between pages
			// (not on the last page yet). The PoW will start solving when
			// the user reaches the last page via the page watcher.
			if (config.hasPages && !isOnLastPage(formId)) {
				logger.log(
					`formId ${formId} — not on last page, skipping PoW for page navigation`
				);
				return data;
			}

			// Case 1: Solution exists but challenge is expired or expiring.
			if (manager.getSolution() && manager.isExpiredOrExpiring(30)) {
				logger.log(
					`formId ${formId} — challenge expired/expiring, re-solving…`
				);

				// Show status text after 2s if still working.
				const statusTimeout = setTimeout(() => {
					showRefreshStatus(form, formId);
				}, 2000);

				try {
					await refreshPoW(formId, config);
				} finally {
					clearTimeout(statusTimeout);
					removeRefreshStatus(form, formId);
				}
			}

			// Case 2: Still solving (user submitted very quickly).
			else if (!manager.getSolution() && manager.isSolving()) {
				logger.log(`formId ${formId} — still solving, waiting…`);
				const solution = await manager.waitForSolution(10_000);
				if (solution) {
					writePayloadField(formId, solution, config.payloadField);
				}
			}

			// Case 3: Solution ready and not expired — no action needed.
			// (payload field was already eagerly populated)

			// Case 4: powOnSubmit mode — manager exists but never started solving.
			else if (!manager.getSolution() && !manager.isSolving()) {
				logger.log(`formId ${formId} — powOnSubmit mode: solving now…`);
				const statusTimeout = setTimeout(() => {
					showRefreshStatus(form, formId);
				}, 500);
				try {
					await manager.init();
					const solution = await manager.waitForSolution(15_000);
					if (solution) {
						writePayloadField(
							formId,
							solution,
							config.payloadField
						);
					}
				} finally {
					clearTimeout(statusTimeout);
					removeRefreshStatus(form, formId);
				}
			}

			return data;
		},
		10 // Default priority — after spinner (3) and file upload check (8).
	);

	// Clean up custom UI if submission is aborted by another filter.
	// GF fires this via trigger() from @gravityforms/utils, which dispatches
	// a native CustomEvent on document — data lives in event.detail.
	document.addEventListener(
		'gform/submission/submission_aborted',
		(event: Event) => {
			const form = (event as CustomEvent).detail?.form as HTMLFormElement;
			if (form) {
				const formId = parseInt(form.dataset.formid || '0', 10);
				removeRefreshStatus(form, formId);
			}
		}
	);

	logger.log('Submission hook registered');
	return true;
}

// ── Status UI Helpers ───────────────────────────────────────────────

/**
 * Shows a message next to the GF spinner.
 * Only called after a 2-second delay to avoid flashing for fast re-solves.
 */
function showRefreshStatus(form: HTMLFormElement, formId: number): void {
	const spinner = form.querySelector(`#gform_ajax_spinner_${formId}`);
	if (!spinner) {
		return;
	}

	// Don't add twice.
	if (form.querySelector(`#gfsh_refresh_status_${formId}`)) {
		return;
	}

	const status = document.createElement('span');
	status.className = 'gfsh-refresh-status';
	status.id = `gfsh_refresh_status_${formId}`;
	status.textContent = 'Verifying…';
	status.style.cssText =
		'display:inline-block;margin-left:8px;font-size:0.85em;color:#666;vertical-align:middle';
	spinner.after(status);
}

/**
 * Removes the refresh status message if present.
 */
function removeRefreshStatus(form: HTMLFormElement, formId: number): void {
	form.querySelector(`#gfsh_refresh_status_${formId}`)?.remove();
}
