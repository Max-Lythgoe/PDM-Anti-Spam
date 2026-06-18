/**
 * Plugin Settings Constants
 *
 * Reads all values from the PHP-injected window object once at module load.
 * Centralizes the window-reading surface for plugin settings.
 */

import type { SiteInfo } from '../shared/constants/settings-defaults';
import type { DashboardStatsData } from './types/dashboard';

const strings = window.gf_spam_hexer_plugin_settings_strings;

export const connectorsUrl: string = strings?.connectorsUrl ?? '';
export const pluginSettingsUrl: string = strings?.pluginSettingsUrl ?? '';
export const wpAiClientAvailable: boolean =
	strings?.wpAiClientAvailable ?? false;
export const availableModelsAuto: Array<{
	id: string;
	label: string;
	provider: string;
}> = strings?.availableModelsAuto ?? [];
export const availableModelsOpenRouter: Array<{
	id: string;
	label: string;
}> = strings?.availableModelsOpenRouter ?? [];
export const siteInfo: SiteInfo = strings?.siteInfo ?? {
	name: '',
	description: '',
	domain: '',
};
export const dashboardStats: DashboardStatsData | undefined =
	strings?.dashboardStats;
