/**
 * Technique Settings Adapter
 *
 * Shared interface that abstracts the differences between plugin-level (global)
 * and form-level (per-form override) technique settings. Both settings pages
 * render the same UI structure — this adapter lets a single unified
 * `<TechniqueSettings>` component work with either store.
 *
 * Plugin mode:  Values are definitive. No override indicators.
 * Form mode:    Values are resolved (override ?? global). Override indicators
 *               and reset buttons are shown.
 */

import type { ReactNode } from 'react';

export interface TechniqueSettingsAdapter {
	// ── Mode ──
	/** Which settings page is rendering. */
	mode: 'plugin' | 'form' | 'comment';

	// ── PoW values ──
	powEnabled: boolean;
	powAction: string;
	powProtectionLevel: string;
	/** Custom validation message for PoW 'fail' action. Empty = use built-in default. */
	powFailMessage: string;

	// ── PoW setters ──
	setPowEnabled: (enabled: boolean) => void;
	setPowAction: (action: string) => void;
	setPowProtectionLevel: (level: string) => void;
	setPowFailMessage: (msg: string) => void;

	// ── AI values ──
	aiEnabled: boolean;
	aiAction: string;
	aiCustomContext: string;
	aiConfidenceThreshold: number;
	/** Custom validation message for AI 'fail' action. Empty = use built-in default. */
	aiFailMessage: string;

	// ── AI setters ──
	setAiEnabled: (enabled: boolean) => void;
	setAiAction: (action: string) => void;
	setAiCustomContext: (context: string) => void;
	setAiConfidenceThreshold: (threshold: number) => void;
	setAiFailMessage: (msg: string) => void;

	// ── Override state (form mode only; plugin mode returns false/noop) ──
	powHasAnyOverride: boolean;
	aiHasAnyOverride: boolean;
	resetAllPowOverrides: () => void;
	resetAllAiOverrides: () => void;

	/** Per-field override status for OverridableField wrappers. */
	overrides: {
		powAction: boolean;
		powProtectionLevel: boolean;
		powFailMessage: boolean;
		aiAction: boolean;
		aiConfidenceThreshold: boolean;
		aiFailMessage: boolean;
	};

	/** Per-field reset handlers for OverridableField wrappers. */
	resetField: {
		powAction: () => void;
		powProtectionLevel: () => void;
		powFailMessage: () => void;
		aiAction: () => void;
		aiConfidenceThreshold: () => void;
		aiFailMessage: () => void;
	};

	// ── AI provider config ──
	/** Extra content rendered inside the AI card body (provider/model config). */
	aiExtraContent?: ReactNode;

	/** Info modal content for the AI card (plugin mode provides AiInfoContent). */
	aiInfoModalContent?: ReactNode;

	/**
	 * Link/button to configure AI provider settings.
	 * Plugin mode: renders a button that opens the AiProviderModal.
	 * Comment mode: renders a link to the plugin settings page.
	 * Form mode: undefined (no AI provider config).
	 */
	aiProviderLink?: ReactNode;

	/**
	 * Whether the AI provider is configured and ready to use.
	 * Comment mode: read from PHP-injected window data.
	 * Plugin/form mode: undefined (not applicable).
	 */
	aiProviderConfigured?: boolean;
}
