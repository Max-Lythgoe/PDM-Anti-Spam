/**
 * Form Settings Store
 *
 * Combined Zustand store for all GF Spam Hexer form settings slices.
 */

import { create } from 'zustand';

import {
	TechniqueSettingsSlice,
	createTechniqueSettingsSlice,
} from './slices/technique-settings';

/**
 * Combined store type
 */
export type FormSettingsStore = TechniqueSettingsSlice;

/**
 * Create the combined store
 */
export const useFormSettingsStore = create<FormSettingsStore>((...a) => ({
	...createTechniqueSettingsSlice(...a),
}));
