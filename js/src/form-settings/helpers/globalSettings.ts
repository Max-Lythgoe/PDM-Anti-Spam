/**
 * Global Settings Helper
 *
 * Provides typed access to the global plugin settings that are passed
 * from PHP via the `gf_spam_hexer_form_settings_strings` localized script object.
 *
 * These values are used by InheritToggle and other components to show
 * the current global default when a per-form override is not set.
 */

import {
	SETTINGS_DEFAULTS,
	type GlobalSettingsValues,
	type SiteInfo,
} from '../../shared/constants/settings-defaults';

// Re-export shared constants for backward compatibility
export {
	POW_DIFFICULTY_INFO,
	getDifficultyInfo,
} from '../../shared/constants/ui-options';

// Re-export the type
export type { GlobalSettingsValues as GlobalSettings };

declare global {
	interface Window {
		gf_spam_hexer_form_settings_strings?: {
			formId: number;
			globalSettings: Record<string, string>;
			wpAiClientAvailable?: boolean;
			siteInfo?: SiteInfo;
			pluginSettingsUrl?: string;
		};
	}
}

/**
 * Parse a global settings value, returning the fallback if empty/missing.
 */
function parseNum(raw: string | undefined, fallback: number): number {
	if (!raw && raw !== '0') {
		return fallback;
	}
	const n = parseFloat(raw);
	return isNaN(n) ? fallback : n;
}

function parseBool(raw: string | undefined, fallback: boolean): boolean {
	if (raw === undefined || raw === '') {
		return fallback;
	}
	return raw === '1' || raw === 'true';
}

/**
 * Get the resolved global settings with proper types.
 *
 * Reads from `window.gf_spam_hexer_form_settings_strings.globalSettings` (set by PHP)
 * and falls back to shared defaults if unavailable.
 */
export function getGlobalSettings(): GlobalSettingsValues {
	const raw = window.gf_spam_hexer_form_settings_strings?.globalSettings;

	if (!raw) {
		return { ...SETTINGS_DEFAULTS };
	}

	return {
		enabled: parseBool(raw.enabled, SETTINGS_DEFAULTS.enabled),
		bypassLoggedIn: parseBool(
			raw.bypass_logged_in,
			SETTINGS_DEFAULTS.bypassLoggedIn
		),
		powEnabled: parseBool(raw.pow_enabled, SETTINGS_DEFAULTS.powEnabled),
		powProtectionLevel:
			raw.pow_protection_level || SETTINGS_DEFAULTS.powProtectionLevel,
		powAction: raw.pow_action || SETTINGS_DEFAULTS.powAction,
		powFailMessage:
			raw.pow_fail_message ?? SETTINGS_DEFAULTS.powFailMessage,
		aiEnabled: parseBool(raw.ai_enabled, SETTINGS_DEFAULTS.aiEnabled),
		aiProvider: raw.ai_provider || SETTINGS_DEFAULTS.aiProvider,
		aiApiKey: raw.ai_api_key ?? SETTINGS_DEFAULTS.aiApiKey,
		aiModel: raw.ai_model || SETTINGS_DEFAULTS.aiModel,
		aiCustomContext:
			raw.ai_custom_context ?? SETTINGS_DEFAULTS.aiCustomContext,
		aiTimeout: parseNum(raw.ai_timeout, SETTINGS_DEFAULTS.aiTimeout),
		aiZdr: parseBool(raw.ai_zdr, SETTINGS_DEFAULTS.aiZdr),
		aiAction: raw.ai_action || SETTINGS_DEFAULTS.aiAction,
		aiFailMessage: raw.ai_fail_message ?? SETTINGS_DEFAULTS.aiFailMessage,
		aiConfidenceThreshold: parseNum(
			raw.ai_confidence_threshold,
			SETTINGS_DEFAULTS.aiConfidenceThreshold
		),
	};
}
