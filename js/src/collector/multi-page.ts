/**
 * Multi-page form helpers — page detection and page-change watchers.
 *
 * @module collector/multi-page
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import type { GpshFrontendConfig } from '../frontend';
import { powManagers, pageWatchersBound } from './state';
import { startSolving } from './init';
import { createLogger } from '@shared/logger';

const logger = createLogger('Collector');

/**
 * GF pagination state shape (from `gform.state.get(formId, 'pagination/pages')`).
 * Only the fields we use are typed here.
 */
interface GFPaginationState {
	currentPage: number;
	lastPage: number;
	totalPageCount: number;
}

/**
 * Determines whether the user is currently on the last page of a
 * multi-page form.
 *
 * Uses GF's state API (`gform.state.get`) when available (GF ≥2.9),
 * which correctly handles pages hidden by conditional logic. Falls
 * back to DOM inspection for older GF versions or when state hasn't
 * been populated yet.
 *
 * Returns `true` for single-page forms (no `.gform_page` elements).
 */
export function isOnLastPage(formId: number): boolean {
	// Try GF state API first — handles conditional logic correctly.
	const gformState = (window as any).gform?.state;
	if (gformState?.get) {
		const pageInfo: GFPaginationState | null = gformState.get(
			formId,
			'pagination/pages'
		);
		if (pageInfo && pageInfo.totalPageCount > 0) {
			return pageInfo.currentPage === pageInfo.lastPage;
		}
	}

	// Fallback: DOM inspection.
	const pages = document.querySelectorAll(`#gform_${formId} .gform_page`);
	if (pages.length === 0) {
		return true; // Single-page form.
	}

	// Find the last page not hidden by conditional logic.
	const activePages = Array.from(pages).filter(
		(p) => p.getAttribute('data-conditional-logic') !== 'hidden'
	);
	if (activePages.length === 0) {
		return true; // All pages hidden — treat as last page.
	}

	const lastActivePage = activePages[activePages.length - 1];
	return (lastActivePage as HTMLElement).style.display !== 'none';
}

/**
 * Binds a page-change watcher for a multi-page form so that PoW
 * solving starts when the user reaches the last page.
 *
 * Uses two mechanisms for broad compatibility:
 * 1. `gform.state.watch('pagination/pages')` — fires on AJAX page
 *    changes and conditional logic evaluation (GF ≥2.9).
 * 2. `gform_page_loaded` jQuery event — fires on legacy iframe/postback
 *    page changes.
 *
 * The watcher is idempotent: if the manager already has a solution
 * or is already solving, the callback is a no-op.
 *
 * Note: For postback (non-AJAX) page navigation, the entire page
 * reloads and `initCollector()` is called fresh via `ON_PAGE_RENDER`.
 * The `isOnLastPage()` check in `initPoW()` handles that case
 * directly — this watcher is primarily for AJAX navigation and
 * conditional logic changes that happen without a full re-init.
 */
export function bindPageWatcher(
	formId: number,
	config: GpshFrontendConfig
): void {
	if (pageWatchersBound.has(formId)) {
		return;
	}
	pageWatchersBound.add(formId);

	const onPageChange = () => {
		if (!isOnLastPage(formId)) {
			logger.log(
				`formId ${formId} — page changed but still not on last page`
			);
			return;
		}

		const manager = powManagers.get(formId);
		if (!manager) {
			return;
		}

		// Already solving or solved — nothing to do.
		if (manager.getSolution() || manager.isSolving()) {
			return;
		}

		logger.log(`formId ${formId} — reached last page, starting PoW solve`);
		startSolving(formId, manager, config);
	};

	// GF ≥2.9 state watcher (AJAX page changes + conditional logic).
	const gformState = (window as any).gform?.state;
	if (gformState?.watch) {
		gformState.watch(formId, ['pagination/pages'], () => onPageChange());
		logger.log(
			`formId ${formId} — bound gform.state.watch for pagination/pages`
		);
	}

	// Legacy jQuery event (iframe/postback page changes).
	const jQuery = (window as any).jQuery;
	if (jQuery) {
		jQuery(document).on(
			'gform_page_loaded',
			(_event: unknown, eventFormId: number) => {
				if (eventFormId === formId) {
					onPageChange();
				}
			}
		);
	}
}
