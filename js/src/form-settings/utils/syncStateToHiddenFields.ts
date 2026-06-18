/**
 * Form Settings Field Mappings
 *
 * Maps Zustand store keys to GF hidden field names.
 * Uses the shared sync utility with the `_gform_setting_` prefix.
 */

import {
	syncStateToHiddenFields,
	transforms,
	type StateToFieldMapping,
} from '../../shared/utils/syncStateToHiddenFields';

// Re-export shared utilities so existing imports and tests still work
export { syncStateToHiddenFields, transforms };
export type { StateToFieldMapping };

/**
 * GF Spam Hexer form settings mappings.
 *
 * Maps Zustand store keys to GF hidden field names.
 * These hidden fields are defined in GF_Spam_Hexer::form_settings_fields().
 *
 * Note: gfshSpamThreshold and gfshRejectThreshold have been removed.
 * Thresholds are now derived from per-technique action settings.
 */
export const formSettingsMappings: StateToFieldMapping = {
	// Technique Overrides
	gfshTechniqueOverrides: {
		fieldName: 'technique_overrides',
		transform: transforms.objectToJson,
	},
	gfshPowProtectionLevel: { fieldName: 'pow_protection_level' },
	gfshAiCustomContext: { fieldName: 'ai_custom_context' },
	gfshAiConfidenceThreshold: { fieldName: 'ai_confidence_threshold' },

	// Per-technique action overrides (blank = use global)
	gfshPowAction: { fieldName: 'pow_action' },
	gfshAiAction: { fieldName: 'ai_action' },

	// Per-technique validation failure messages (blank = use global/default)
	gfshPowFailMessage: { fieldName: 'pow_fail_message' },
	gfshAiFailMessage: { fieldName: 'ai_fail_message' },
};

/**
 * @deprecated Use formSettingsMappings instead. Kept for backward compatibility.
 */
export const feedSettingsMappings = formSettingsMappings;
