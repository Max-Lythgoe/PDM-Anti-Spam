/**
 * GF Spam Hexer — Form Collector
 *
 * Thin orchestrator that wires together the collector modules.
 * Manages the lifecycle of spam protection signals for a single form:
 * 1. Starts PoW solving immediately (if enabled)
 * 2. Eagerly populates the hidden payload field as soon as the solution is ready
 * 3. Hooks into GF's pre-submission filter to refresh expired challenges
 *
 * Called by GF's init scripts (`ON_PAGE_RENDER`) which fire on every render,
 * including AJAX re-renders. The collector handles re-initialization by
 * destroying the old PoW manager and creating a new one with a fresh challenge.
 *
 * @module collector
 */

import type { PoWDebugStatus } from './pow-manager';
import type { GpshFrontendConfig } from '../frontend';
import { formConfigs, powManagers, refreshingForms } from './state';
import { initPoW, createManager } from './init';
import { isOnLastPage } from './multi-page';
import { ensurePayloadField, writePayloadField } from './payload';
import { bindSubmissionHook } from './submission-hook';
import { maybeExposeDebugApi } from './debug-api';
import { createLogger } from '@shared/logger';

const logger = createLogger('Collector');

/** Full debug status including collector-level state. */
export interface CollectorDebugStatus extends PoWDebugStatus {
	status?: 'no_manager';
	isRefreshing: boolean;
	isDeferredForPage: boolean;
}

let submissionHookBound = false;

/**
 * Initializes the collector for a single form.
 *
 * Called from GF init scripts on every `ON_PAGE_RENDER` event.
 * On first call: starts PoW solving and eagerly populates the payload field.
 * On subsequent calls (AJAX re-render): destroys the old PoW manager
 * and starts a fresh one with the new challenge from the server.
 *
 * @param config Per-form config from PHP (via `GFFormDisplay::add_init_script`).
 */
export async function initCollector(config: GpshFrontendConfig): Promise<void> {
	const { formId } = config;

	logger.group(`initCollector — formId: ${formId}`, () => {
		logger.log('config:', config);
		logger.log('powEnabled:', config.powEnabled);
		logger.log('powChallenge:', config.powChallenge ?? '(none)');
		logger.log('payloadField:', config.payloadField);
		logger.log('workerUrl:', config.workerUrl);
	});

	// Store config for later use by the submission hook.
	formConfigs.set(formId, config);

	// Ensure the hidden payload field exists (may need re-creation after AJAX).
	ensurePayloadField(formId, config.payloadField);

	// Always re-initialize PoW (fresh challenge on every render).
	initPoW(formId, config);

	// Bind the submission hook on first collector init (deferred from module
	// load to ensure window.gform.utils is available).
	if (!submissionHookBound) {
		submissionHookBound = bindSubmissionHook();
	}
}

// ── Debug API ───────────────────────────────────────────────────────

maybeExposeDebugApi({
	powManagers,
	formConfigs,
	refreshingForms,
	isOnLastPage,
	initPoW,
	writePayloadField,
	createManager,
});
