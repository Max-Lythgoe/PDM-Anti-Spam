/**
 * useGFToggleValue Hook
 *
 * Watches a GF-rendered toggle or select element for value changes.
 * Handles both native change events and programmatic value updates
 * (via MutationObserver for hidden inputs, click handlers for visual wrappers).
 *
 * Used by both PluginSettingsApp (watches addon toggle) and
 * FormSettingsApp (watches select dropdown).
 */

import { useState, useEffect } from '@wordpress/element';

interface UseGFToggleValueOptions {
	/** CSS selector for the form element to watch */
	selector: string;
	/** Value that means "disabled" / should hide the UI */
	disabledValue: string;
	/** Default state if element is not found */
	defaultEnabled?: boolean;
}

/**
 * Watch a GF-rendered form element and return whether the settings UI should be shown.
 *
 * @return `true` if the UI should be visible, `false` if disabled
 */
export function useGFToggleValue({
	selector,
	disabledValue,
	defaultEnabled = true,
}: UseGFToggleValueOptions): boolean {
	const [isEnabled, setIsEnabled] = useState(defaultEnabled);

	useEffect(() => {
		const element = document.querySelector(selector) as
			| HTMLInputElement
			| HTMLSelectElement
			| null;

		if (!element) {
			return;
		}

		const updateState = () => {
			setIsEnabled(element.value !== disabledValue);
		};

		// Set initial state
		updateState();

		// Listen for native change events
		element.addEventListener('change', updateState);

		// For hidden inputs that GF updates programmatically,
		// observe attribute/value changes via MutationObserver
		const observer = new MutationObserver(updateState);
		observer.observe(element, {
			attributes: true,
			attributeFilter: ['value'],
		});

		// For GF visual toggle wrappers, also listen for clicks
		// on the parent container (with a small delay for GF to update)
		const wrapper = element.closest('.gform-settings-input__container');
		let handleWrapperClick: (() => void) | null = null;

		if (wrapper) {
			handleWrapperClick = () => {
				setTimeout(updateState, 50);
			};
			wrapper.addEventListener('click', handleWrapperClick);
		}

		return () => {
			element.removeEventListener('change', updateState);
			observer.disconnect();
			if (wrapper && handleWrapperClick) {
				wrapper.removeEventListener('click', handleWrapperClick);
			}
		};
	}, [selector, disabledValue]);

	return isEnabled;
}
