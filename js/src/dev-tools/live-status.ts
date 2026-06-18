/**
 * Live status updates — polling loop and inline status rendering.
 *
 * @module dev-tools/live-status
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import type { CollectorDebugStatus } from '../collector';
import { actionHandlers, clearedForms } from './action-handlers';
import {
	formatPoWStatus,
	formatExpiry,
	formatChallengeType,
} from './formatters';
import { updateEventLog } from './event-log';

/** Alias for the debug status shape. */
type PoWStatus = CollectorDebugStatus;

const win = window as any;

/**
 * Find the QM shadow root, attach click handlers, and start live updates.
 * Retries a few times since QM may not have rendered yet.
 */
export function initInShadowRoot(retries = 10): void {
	const container = document.getElementById('query-monitor-container');
	const shadowRoot = container?.shadowRoot;

	if (!shadowRoot) {
		if (retries > 0) {
			setTimeout(() => initInShadowRoot(retries - 1), 200);
		}
		return;
	}

	// Delegate clicks on any button with data-action starting with "gfsh-".
	shadowRoot.addEventListener('click', (e: Event) => {
		const target = e.target as HTMLElement;
		const button = target.closest('[data-action^="gfsh-"]') as HTMLElement;
		if (!button) {
			return;
		}

		const action = button.getAttribute('data-action');
		const formId = parseInt(button.getAttribute('data-form-id') || '0', 10);

		if (action && actionHandlers[action]) {
			actionHandlers[action](formId, button, shadowRoot);
		}
	});

	// Start the reactive live status updates.
	startLiveUpdates(shadowRoot);
}

/**
 * Starts the polling loop that updates live status spans in the QM panel.
 */
function startLiveUpdates(shadowRoot: ShadowRoot): void {
	let pollInterval: ReturnType<typeof setInterval> | null = null;

	const poll = () => {
		const debug = win.gfshDebug;

		// Find all grids with data-gfsh-grid attribute.
		const grids =
			shadowRoot.querySelectorAll<HTMLElement>('[data-gfsh-grid]');

		if (grids.length === 0) {
			return;
		}

		grids.forEach((grid) => {
			const formId = parseInt(
				grid.getAttribute('data-gfsh-grid') || '0',
				10
			);
			if (!formId) {
				return;
			}

			const status: PoWStatus | null = debug
				? debug.getStatus(formId)
				: null;
			updateInlineStatus(shadowRoot, formId, status);
		});
	};

	// Poll every second.
	pollInterval = setInterval(poll, 1000);

	// Run once immediately after a short delay for gfshDebug to be exposed.
	setTimeout(poll, 300);

	// Clean up if the QM panel is removed (unlikely but defensive).
	const observer = new MutationObserver(() => {
		const container = document.getElementById('query-monitor-container');
		if (!container && pollInterval) {
			clearInterval(pollInterval);
			pollInterval = null;
			observer.disconnect();
		}
	});
	observer.observe(document.body, { childList: true });
}

/**
 * Updates the inline live status spans for a single form.
 */
function updateInlineStatus(
	shadowRoot: ShadowRoot,
	formId: number,
	status: PoWStatus | null
): void {
	const fid = String(formId);
	const hasPowManager = !!(status && status.status !== 'no_manager');
	const wasCleared = clearedForms.has(formId);

	// If the manager came back (e.g. after a Force Fallback re-solve),
	// clear the "cleared" flag.
	if (hasPowManager && wasCleared) {
		clearedForms.delete(formId);
	}

	// Show live rows if we have a manager OR if the solution was deliberately
	// cleared (so the user can see the "cleared" state). Hide buttons when
	// there's no manager regardless.
	const showLiveRows = hasPowManager || wasCleared;
	const powElements = shadowRoot.querySelectorAll<HTMLElement>(
		`[data-gfsh-requires-pow="${fid}"]`
	);
	powElements.forEach((el) => {
		const isButton = el.tagName === 'BUTTON';
		if (isButton) {
			// Buttons only show when there's an active manager.
			el.style.display = hasPowManager ? 'inline-block' : 'none';
		} else {
			// Live row labels/values show when manager exists or was cleared.
			el.style.display = showLiveRows ? '' : 'none';
		}
	});

	// Helper to update a live span's text without destroying child elements
	// (e.g. inline buttons). Updates or creates the first text node.
	const setLive = (key: string, text: string, color?: string) => {
		const valueEl = shadowRoot.querySelector<HTMLElement>(
			`[data-gfsh-live="${key}-${fid}"]`
		);
		if (valueEl) {
			// Find or create the first text node to avoid wiping buttons.
			const textNode = Array.from(valueEl.childNodes).find(
				(n) => n.nodeType === Node.TEXT_NODE
			);
			if (textNode) {
				textNode.textContent = text;
			} else {
				valueEl.insertBefore(
					document.createTextNode(text),
					valueEl.firstChild
				);
			}
			if (color) {
				valueEl.style.color = color;
			}
		}
	};

	// If cleared and no manager, show the cleared state.
	if (wasCleared && !hasPowManager) {
		setLive(
			'pow-status',
			'Solution cleared (PoW will be missing on submit)',
			'#dc3232'
		);

		// Payload field — still check the actual DOM.
		const payloadField = document.querySelector<HTMLInputElement>(
			`#gform_${formId} input[name="gfsh_payload"]`
		);
		if (!payloadField) {
			setLive('payload', 'No form found', 'var(--qm-info-fg, #999)');
		} else if (payloadField.value) {
			setLive('payload', 'Populated', '#46b450');
		} else {
			setLive('payload', 'Empty', '#dc3232');
		}

		setLive('expires', '--');
		setLive('challenge', '--');

		updateEventLog(shadowRoot, formId);
		return;
	}

	// No manager and not cleared — hide everything (initial/bypassed state).
	if (!hasPowManager) {
		updateEventLog(shadowRoot, formId);
		return;
	}

	// PoW Status — granular info when solving.
	const pow = formatPoWStatus(status);
	setLive('pow-status', pow.text, pow.color);

	// Payload field check (reads from the actual page DOM, not shadow DOM).
	const payloadField = document.querySelector<HTMLInputElement>(
		`#gform_${formId} input[name="gfsh_payload"]`
	);
	if (!payloadField) {
		setLive('payload', 'No form found', 'var(--qm-info-fg, #999)');
	} else if (payloadField.value) {
		setLive('payload', 'Populated', '#46b450');
	} else {
		setLive('payload', 'Empty', '#dc3232');
	}

	// Expires countdown.
	const expiry = formatExpiry(status);
	setLive('expires', expiry);

	// Challenge type.
	const challengeType = formatChallengeType(status);
	if (challengeType) {
		setLive('challenge', challengeType);
	}

	// Event log.
	updateEventLog(shadowRoot, formId);
}
