/**
 * Sync State to Hidden Fields
 *
 * Shared utility to sync React/Zustand store state to GF hidden form fields.
 * Supports both form-settings (`_gform_setting_`) and plugin-settings
 * (`_gaddon_setting_`) prefixes via the `prefix` parameter.
 */

import { createLogger } from '../logger';
import type { GFFieldPrefix } from './getHiddenFieldValue';

const logger = createLogger('SyncStateToHiddenFields');

export interface StateToFieldMapping {
	[stateKey: string]: {
		fieldName: string;
		transform?: (value: any) => string;
	};
}

/**
 * Sync store state to hidden form fields.
 *
 * @param state    - Current store state object
 * @param mappings - Map of state keys to hidden field configs
 * @param prefix   - GF field name prefix
 */
export function syncStateToHiddenFields(
	state: Record<string, any>,
	mappings: StateToFieldMapping,
	prefix: GFFieldPrefix
): void {
	Object.entries(mappings).forEach(([stateKey, config]) => {
		const value = state[stateKey];
		const { fieldName, transform } = config;

		const field = document.querySelector(
			`input[name="${prefix}${fieldName}"]`
		) as HTMLInputElement;

		if (field) {
			const stringValue = transform
				? transform(value)
				: String(value ?? '');
			field.value = stringValue;

			// Trigger change event for any listeners
			field.dispatchEvent(new Event('change', { bubbles: true }));
		} else {
			logger.warn(`Hidden field not found: ${prefix}${fieldName}`);
		}
	});
}

/**
 * Transform functions for common data types.
 */
export const transforms = {
	/** Convert array to JSON string. */
	arrayToJson: (value: any[]): string => {
		return JSON.stringify(value || []);
	},

	/** Convert object to JSON string. */
	objectToJson: (value: Record<string, any>): string => {
		return JSON.stringify(value || {});
	},

	/** Convert boolean to '1'/'0' string. */
	booleanToString: (value: boolean): string => {
		return value ? '1' : '0';
	},

	/** Convert number to string. */
	numberToString: (value: number): string => {
		return String(value ?? '');
	},
};

interface SyncableStore<T> {
	subscribe: (listener: (state: T) => void) => () => void;
	getState: () => T;
}

/**
 * Create a store subscription that syncs mapped state to hidden fields.
 *
 * Tracks previous state and only syncs when mapped fields actually change.
 * Performs an initial sync on first call.
 *
 * @param store    - Zustand store with a `subscribe` method
 * @param mappings - Map of state keys to hidden field configs
 * @param prefix   - GF field name prefix
 */
export function createStateSyncer<T extends Record<string, any>>(
	store: SyncableStore<T>,
	mappings: StateToFieldMapping,
	prefix: GFFieldPrefix
): () => void {
	let previousState: Record<string, any> | null = null;
	const mappedKeys = Object.keys(mappings);

	return store.subscribe((state) => {
		let hasMappedChanges = false;

		if (previousState) {
			for (const key of mappedKeys) {
				if (
					JSON.stringify((state as any)[key]) !==
					JSON.stringify(previousState[key])
				) {
					hasMappedChanges = true;
					break;
				}
			}

			if (hasMappedChanges) {
				syncStateToHiddenFields(state, mappings, prefix);
			}
		} else {
			// Initial sync
			syncStateToHiddenFields(state, mappings, prefix);
		}

		previousState = JSON.parse(JSON.stringify(state));
	});
}
