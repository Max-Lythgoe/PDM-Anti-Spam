/**
 * Settings Store Factory
 *
 * Creates a Zustand + Immer store from a key map, a GF field prefix, and defaults.
 * Reads initial values directly from hidden input fields in the DOM,
 * eliminating the need for PHP-injected window objects.
 */

import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';
import { getHiddenFieldValue, type GFFieldPrefix } from './getHiddenFieldValue';

type SetterName<K extends string> = `set${Capitalize<K>}`;

export type SettingsKeyMap = Record<string, string>;

export type SettingsStoreState<KM extends SettingsKeyMap> = {
	[K in keyof KM & string]: string;
} & {
	[K in keyof KM & string as SetterName<K>]: (value: string) => void;
};

/**
 * Creates a Zustand store from a key map + hidden field prefix.
 *
 * Reads initial values from GF hidden input fields in the DOM.
 *
 * @param keyMap   Maps camelCase store keys to hidden field names (without prefix)
 * @param prefix   GF field name prefix (`_gform_setting_`, `_gaddon_setting_`, or `''`)
 * @param defaults Default values keyed by field name (without prefix)
 */
export function createSettingsStore<KM extends SettingsKeyMap>(
	keyMap: KM,
	prefix: GFFieldPrefix,
	defaults: Record<string, string>
) {
	return create<SettingsStoreState<KM>>()(
		immer((set) => {
			// Build initial state from hidden inputs
			const initialState: Record<string, string> = {};
			for (const [camelKey, fieldName] of Object.entries(keyMap)) {
				initialState[camelKey] = getHiddenFieldValue(
					prefix,
					fieldName,
					defaults[fieldName] ?? ''
				);
			}

			// Auto-generate setters
			const setters: Record<string, (value: string) => void> = {};
			for (const camelKey of Object.keys(keyMap)) {
				const setterName = `set${camelKey.charAt(0).toUpperCase()}${camelKey.slice(1)}`;
				setters[setterName] = (value: string) =>
					// eslint-disable-next-line @typescript-eslint/no-explicit-any
					set((state: any) => {
						state[camelKey] = value;
					});
			}

			return { ...initialState, ...setters } as SettingsStoreState<KM>;
		})
	);
}
