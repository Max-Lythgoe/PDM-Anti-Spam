/**
 * Action button handlers for the GF Dev Tools QM panel.
 *
 * @module dev-tools/action-handlers
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

const win = window as any;

/** Track forms where the solution was deliberately cleared via dev tools. */
export const clearedForms = new Set<number>();

/**
 * Action handlers keyed by data-action attribute value.
 */
export const actionHandlers: Record<
	string,
	(formId: number, button: HTMLElement, shadowRoot: ShadowRoot) => void
> = {
	'gfsh-force-check'() {
		const url = new URL(window.location.href);
		url.searchParams.set('gfsh_force', '1');
		window.location.href = url.toString();
	},

	'gfsh-remove-force-check'() {
		const url = new URL(window.location.href);
		url.searchParams.delete('gfsh_force');
		url.searchParams.delete('gfsh_pow_on_submit');
		window.location.href = url.toString();
	},

	'gfsh-pow-on-submit'(formId, button) {
		const debug = win.gfshDebug;
		if (debug?.setPowOnSubmit) {
			debug.setPowOnSubmit(formId);
			showButtonFeedback(button, 'Active', 'success');
		} else {
			// Fallback: page reload if debug API not available.
			const url = new URL(window.location.href);
			url.searchParams.set('gfsh_force', '1');
			url.searchParams.set('gfsh_pow_on_submit', '1');
			window.location.href = url.toString();
		}
	},

	'gfsh-force-fallback'(formId, button) {
		const debug = win.gfshDebug;
		if (debug?.forceFallback) {
			debug.forceFallback(formId);
			showButtonFeedback(button, 'Re-solving...', 'info');
		}
	},

	'gfsh-force-expire'(formId, button) {
		const debug = win.gfshDebug;
		if (debug?.forceExpire) {
			debug.forceExpire(formId);
			showButtonFeedback(button, 'Expired', 'warning');
		}
	},

	'gfsh-clear-solution'(formId, button) {
		const debug = win.gfshDebug;
		if (debug?.clearSolution) {
			debug.clearSolution(formId);
			clearedForms.add(formId);
			showButtonFeedback(button, 'Cleared', 'error');
		}
	},
};

// Register simplified handlers on the global extensions object for GF Dev Tools'
// own delegation (which doesn't pass button/shadowRoot).
const extensions: Record<string, (formId: number) => void> = {
	...(win.gfDebugExtensions || {}),
};
for (const action of Object.keys(actionHandlers)) {
	extensions[action] = (formId: number) =>
		actionHandlers[action](formId, null as any, null as any);
}
win.gfDebugExtensions = extensions;

/**
 * Shows temporary feedback on a button, then reverts after 2s.
 */
function showButtonFeedback(
	button: HTMLElement,
	text: string,
	type: 'success' | 'error' | 'info' | 'warning'
): void {
	const original = button.textContent;
	const originalBorder = button.style.borderColor;
	const originalColor = button.style.color;

	const colors: Record<string, string> = {
		success: '#46b450',
		error: '#dc3232',
		info: '#00a0d2',
		warning: '#ffb900',
	};
	const color = colors[type] || '#333';

	button.textContent = text;
	button.style.borderColor = color;
	button.style.color = color;

	setTimeout(() => {
		button.textContent = original;
		button.style.borderColor = originalBorder;
		button.style.color = originalColor;
	}, 2000);
}
