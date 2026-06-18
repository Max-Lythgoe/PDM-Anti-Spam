/**
 * GF Spam Hexer — Frontend Entry Point
 *
 * Exposes the collector initializer as a global function so that GF's
 * init scripts (registered via `GFFormDisplay::add_init_script()`) can
 * call it per-form at the right lifecycle moment (`ON_PAGE_RENDER`).
 *
 * The PHP side (`GF_Spam_Hexer::add_frontend_init_scripts()`) injects:
 *   window.gfshInitCollector({ formId, powEnabled, ... });
 *
 * This replaces the old approach of listening for `gform_post_render`
 * and guessing config variable names from `window.gfshFrontendConfig_{formId}`.
 */

import { initCollector } from '../collector';

/**
 * Shape of the per-form config passed from PHP via init scripts.
 */
export interface GpshFrontendConfig {
	formId: number;
	powEnabled: boolean;
	workerUrl: string;
	payloadField: string;
	powOnSubmit?: boolean;
	hasPages?: boolean;
	powChallenge?: {
		challenge: string;
		signature: string;
		difficulty: number;
		expires: number;
	};
}

// Expose the collector initializer globally for GF init scripts.
(window as unknown as Record<string, unknown>).gfshInitCollector =
	initCollector;
