/**
 * Settings Defaults
 *
 * Single source of truth for default setting values.
 * Mirrors PHP Settings::DEFAULTS to keep both sides in sync.
 */

/**
 * Site info passed from PHP for the "What the AI sees" section.
 */
export interface SiteInfo {
	name: string;
	description: string;
	domain: string;
}

/**
 * Typed global settings shape used by both apps.
 */
export interface GlobalSettingsValues {
	enabled: boolean;
	bypassLoggedIn: boolean;
	powEnabled: boolean;
	powProtectionLevel: string;
	powAction: string;
	/** Custom validation message shown when PoW action is 'fail'. Empty = use built-in default. */
	powFailMessage: string;
	aiEnabled: boolean;
	aiProvider: string;
	aiApiKey: string;
	aiModel: string;
	aiCustomContext: string;
	aiTimeout: number;
	aiZdr: boolean;
	aiAction: string;
	/** Custom validation message shown when AI action is 'fail'. Empty = use built-in default. */
	aiFailMessage: string;
	aiConfidenceThreshold: number;
}

/**
 * Default values matching PHP Settings::DEFAULTS.
 */
export const SETTINGS_DEFAULTS: GlobalSettingsValues = {
	enabled: true,
	bypassLoggedIn: true,
	powEnabled: true,
	powProtectionLevel: 'standard',
	powAction: 'spam',
	powFailMessage: '',
	aiEnabled: false,
	aiProvider: 'auto',
	aiApiKey: '',
	aiModel: '',
	aiCustomContext: '',
	aiTimeout: 10,
	aiZdr: false,
	aiAction: 'spam',
	aiFailMessage: '',
	aiConfidenceThreshold: 0.5,
};

/**
 * String-based defaults for the plugin-settings store
 * (GF addon fields store everything as strings).
 */
export const SETTINGS_DEFAULTS_STRINGS = {
	bypass_logged_in: '1',
	pow_enabled: '1',
	pow_protection_level: 'standard',
	pow_action: 'spam',
	pow_fail_message: '',
	ai_enabled: '0',
	ai_provider: 'auto',
	ai_api_key: '',
	ai_model: '',
	ai_custom_context: '',
	ai_timeout: '10',
	ai_zdr: '0',
	ai_action: 'spam',
	ai_fail_message: '',
	ai_confidence_threshold: '0.50',
} as const;
