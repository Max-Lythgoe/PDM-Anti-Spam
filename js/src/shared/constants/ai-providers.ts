/**
 * AI Provider Mode Metadata
 *
 * Configuration for the settings UI. Two modes:
 * - 'auto': WP AI Client (WP 7.0+) handles provider routing and keys
 * - 'openrouter': Direct HTTP to OpenRouter (fallback for WP < 7.0)
 */

import type { AiProviderMode } from '../types/settings';

export interface AiProviderModeInfo {
	id: AiProviderMode;
	name: string;
	description: string;
	/** Whether this mode needs an API key in plugin settings. */
	needsApiKey: boolean;
	/** URL where users can get an API key (only for modes that need one). */
	credentialsUrl?: string;
}

export const AI_PROVIDER_MODES: Record<AiProviderMode, AiProviderModeInfo> = {
	auto: {
		id: 'auto',
		name: 'WordPress AI Client',
		description:
			'Uses whichever AI provider is configured in Settings → Connectors. Requires WordPress 7.0+ with at least one AI provider plugin active.',
		needsApiKey: false,
	},
	openrouter: {
		id: 'openrouter',
		name: 'OpenRouter (Direct)',
		description:
			'Direct connection to OpenRouter. Access hundreds of models through one API key. Use this if running WordPress < 7.0 or as a fallback.',
		needsApiKey: true,
		credentialsUrl: 'https://openrouter.ai/keys',
	},
};

/** Ordered list for the provider mode dropdown. */
export const AI_PROVIDER_MODE_OPTIONS: AiProviderMode[] = [
	'auto',
	'openrouter',
];
