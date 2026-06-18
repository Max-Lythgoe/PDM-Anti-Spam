/**
 * Technique Settings Slice
 *
 * Manages per-form technique override settings:
 * PoW difficulty, AI custom context, and per-technique action overrides.
 * Empty values mean "use global setting".
 *
 * Reads initial values from GF hidden input fields in the DOM.
 */

import { StateCreator } from 'zustand';
import { getHiddenFieldValue } from '../../../shared/utils/getHiddenFieldValue';

export interface TechniqueOverrides {
	pow_enabled?: boolean;
	ai_enabled?: boolean;
}

export interface TechniqueSettingsSlice {
	gfshTechniqueOverrides: TechniqueOverrides;
	gfshPowProtectionLevel: string;
	gfshAiCustomContext: string;
	gfshAiConfidenceThreshold: string; // '' = use global, '0.XX' = override
	gfshPowAction: string; // '' | 'spam' | 'reject' | 'fail'
	gfshAiAction: string; // '' | 'spam' | 'reject' | 'fail'
	gfshPowFailMessage: string; // '' = use global/default
	gfshAiFailMessage: string; // '' = use global/default

	setGpshTechniqueOverrides: (value: TechniqueOverrides) => void;
	setGpshPowProtectionLevel: (value: string) => void;
	setGpshAiCustomContext: (value: string) => void;
	setGpshAiConfidenceThreshold: (value: string) => void;
	setGpshPowAction: (value: string) => void;
	setGpshAiAction: (value: string) => void;
	setGpshPowFailMessage: (value: string) => void;
	setGpshAiFailMessage: (value: string) => void;
}

/** Read a GF form setting hidden field. */
function readField(fieldName: string, fallback: string): string {
	return getHiddenFieldValue('_gform_setting_', fieldName, fallback);
}

/** Parse a JSON string with a typed fallback. */
function parseJsonOrDefault<T>(value: string, fallback: T): T {
	try {
		return value ? JSON.parse(value) : fallback;
	} catch {
		return fallback;
	}
}

export const createTechniqueSettingsSlice: StateCreator<
	TechniqueSettingsSlice
> = (set) => ({
	gfshTechniqueOverrides: parseJsonOrDefault<TechniqueOverrides>(
		readField('technique_overrides', '{}'),
		{}
	),
	gfshPowProtectionLevel: readField('pow_protection_level', ''),
	gfshAiCustomContext: readField('ai_custom_context', ''),
	gfshAiConfidenceThreshold: readField('ai_confidence_threshold', ''),
	gfshPowAction: readField('pow_action', ''),
	gfshAiAction: readField('ai_action', ''),
	gfshPowFailMessage: readField('pow_fail_message', ''),
	gfshAiFailMessage: readField('ai_fail_message', ''),

	setGpshTechniqueOverrides: (value) =>
		set({ gfshTechniqueOverrides: value }),
	setGpshPowProtectionLevel: (value) =>
		set({ gfshPowProtectionLevel: value }),
	setGpshAiCustomContext: (value) => set({ gfshAiCustomContext: value }),
	setGpshAiConfidenceThreshold: (value) =>
		set({ gfshAiConfidenceThreshold: value }),
	setGpshPowAction: (value) => set({ gfshPowAction: value }),
	setGpshAiAction: (value) => set({ gfshAiAction: value }),
	setGpshPowFailMessage: (value) => set({ gfshPowFailMessage: value }),
	setGpshAiFailMessage: (value) => set({ gfshAiFailMessage: value }),
});
