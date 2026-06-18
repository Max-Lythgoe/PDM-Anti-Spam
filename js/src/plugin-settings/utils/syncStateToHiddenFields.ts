/**
 * Plugin Settings Field Mappings
 *
 * Maps Zustand store keys to GF hidden field names.
 * Uses the shared sync utility with the `_gform_setting_` prefix.
 */

import type { StateToFieldMapping } from '../../shared/utils/syncStateToHiddenFields';

/**
 * GF Spam Hexer plugin settings mappings.
 *
 * Maps Zustand store keys to GF addon hidden field names.
 * These hidden fields are defined in GF_Spam_Hexer::plugin_settings_fields().
 */
export const pluginSettingsMappings: StateToFieldMapping = {
	// General
	bypassLoggedIn: { fieldName: 'bypass_logged_in' },

	// Proof of Work
	powEnabled: { fieldName: 'pow_enabled' },
	powProtectionLevel: { fieldName: 'pow_protection_level' },
	powAction: { fieldName: 'pow_action' },
	powFailMessage: { fieldName: 'pow_fail_message' },

	// AI Classification
	aiEnabled: { fieldName: 'ai_enabled' },
	aiProvider: { fieldName: 'ai_provider' },
	aiApiKey: { fieldName: 'ai_api_key' },
	aiModel: { fieldName: 'ai_model' },
	aiCustomContext: { fieldName: 'ai_custom_context' },
	aiTimeout: { fieldName: 'ai_timeout' },
	aiZdr: { fieldName: 'ai_zdr' },
	aiAction: { fieldName: 'ai_action' },
	aiFailMessage: { fieldName: 'ai_fail_message' },
	aiConfidenceThreshold: { fieldName: 'ai_confidence_threshold' },
};
