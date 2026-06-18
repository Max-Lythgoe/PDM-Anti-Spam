/**
 * Plugin Settings Store
 *
 * Zustand store for global GF Spam Hexer plugin settings.
 * Reads initial values from GF hidden input fields in the DOM
 * and syncs changes back so GF can save on form submit.
 */

import { createSettingsStore } from '../../shared/utils/createSettingsStore';
import { SETTINGS_DEFAULTS_STRINGS } from '../../shared/constants/settings-defaults';
import type { SiteInfo } from '../../shared/constants/settings-defaults';
import type { DashboardStatsData } from '../types/dashboard';

declare global {
	interface Window {
		gf_spam_hexer_plugin_settings_strings?: {
			wpAiClientAvailable?: boolean;
			availableModelsAuto?: Array<{
				id: string;
				label: string;
				provider: string;
			}>;
			availableModelsOpenRouter?: Array<{
				id: string;
				label: string;
			}>;
			siteInfo?: SiteInfo;
			connectorsUrl?: string;
			pluginSettingsUrl?: string;
			dashboardStats?: DashboardStatsData;
		};
	}
}

/**
 * Maps camelCase store keys to their snake_case GF hidden field names.
 * Hidden fields use the `_gform_setting_` prefix (applied by createSettingsStore).
 */
const SETTINGS_KEY_MAP = {
	bypassLoggedIn: 'bypass_logged_in',
	powEnabled: 'pow_enabled',
	powProtectionLevel: 'pow_protection_level',
	powAction: 'pow_action',
	powFailMessage: 'pow_fail_message',
	aiEnabled: 'ai_enabled',
	aiProvider: 'ai_provider',
	aiApiKey: 'ai_api_key',
	aiModel: 'ai_model',
	aiCustomContext: 'ai_custom_context',
	aiTimeout: 'ai_timeout',
	aiZdr: 'ai_zdr',
	aiAction: 'ai_action',
	aiFailMessage: 'ai_fail_message',
	aiConfidenceThreshold: 'ai_confidence_threshold',
} as const;

export type PluginSettingsState = ReturnType<
	typeof usePluginSettingsStore.getState
>;

export const usePluginSettingsStore = createSettingsStore(
	SETTINGS_KEY_MAP,
	'_gform_setting_',
	SETTINGS_DEFAULTS_STRINGS
);
