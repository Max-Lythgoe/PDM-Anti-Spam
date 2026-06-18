/**
 * Comment Settings Store
 *
 * Zustand store for WordPress comment spam protection settings.
 * Reads initial values from hidden input fields in the DOM
 * and syncs changes back so the WP Settings API can save on form submit.
 */

import { createSettingsStore } from '../../shared/utils/createSettingsStore';
import type {
	PowData,
	AiData,
	SignalData,
	ReasonData,
} from '../../plugin-settings/types/dashboard';

declare global {
	interface Window {
		gfsh_comment_settings?: {
			pluginSettingsUrl?: string;
			aiProviderConfigured?: boolean;
			commentStats?: {
				period_days: number;
				comments: {
					total_checked: number;
					spam_count: number;
					clean_count: number;
					pow: PowData;
					ai: AiData | null;
					pow_signals: SignalData[];
					ai_reasons: ReasonData[];
					ai_signals: SignalData[];
				};
			};
		};
	}
}

/**
 * Maps camelCase store keys to their full hidden field names.
 * Comment settings use no GF prefix (prefix = '').
 */
const SETTINGS_KEY_MAP = {
	enabled: 'gfsh_comment_enabled',
	powEnabled: 'gfsh_comment_pow_enabled',
	aiEnabled: 'gfsh_comment_ai_enabled',
	bypassLoggedIn: 'gfsh_comment_bypass_loggedin',
	powProtectionLevel: 'gfsh_comment_pow_protection_level',
	aiCustomContext: 'gfsh_comment_ai_custom_context',
	aiConfidenceThreshold: 'gfsh_comment_ai_confidence_threshold',
	powAction: 'gfsh_comment_pow_action',
	aiAction: 'gfsh_comment_ai_action',
	powFailMessage: 'gfsh_comment_pow_fail_message',
	aiFailMessage: 'gfsh_comment_ai_fail_message',
} as const;

/** Default values for comment settings (keyed by full field name). */
const DEFAULTS: Record<string, string> = {
	gfsh_comment_enabled: '0',
	gfsh_comment_pow_enabled: '1',
	gfsh_comment_ai_enabled: '1',
	gfsh_comment_bypass_loggedin: '1',
	gfsh_comment_pow_protection_level: 'standard',
	gfsh_comment_ai_custom_context: '',
	gfsh_comment_ai_confidence_threshold: '0.50',
	gfsh_comment_pow_action: 'spam',
	gfsh_comment_ai_action: 'spam',
	gfsh_comment_pow_fail_message: '',
	gfsh_comment_ai_fail_message: '',
};

export type CommentSettingsState = ReturnType<
	typeof useCommentSettingsStore.getState
>;

export const useCommentSettingsStore = createSettingsStore(
	SETTINGS_KEY_MAP,
	'',
	DEFAULTS
);
