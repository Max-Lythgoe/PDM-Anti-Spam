/**
 * Form Settings Adapter Hook
 *
 * Adapts the form-settings Zustand store into the shared
 * TechniqueSettingsAdapter interface so the unified <TechniqueSettings>
 * component can render per-form override settings.
 *
 * Form mode resolves effective values (override ?? global), shows override
 * indicators, and provides per-field and per-technique reset handlers.
 */

import { useMemo } from '@wordpress/element';
import { useFormSettingsStore } from '../store';
import type { TechniqueOverrides } from '../store/slices/technique-settings';
import { getGlobalSettings } from '../helpers/globalSettings';
import { handleProtectionLevelChange } from '../../shared/utils/handleProtectionLevelChange';
import type { TechniqueSettingsAdapter } from '../../shared/types/technique-settings-adapter';
import { AiInfoContent } from '../../shared/components/AiInfoContent';

const pluginSettingsUrl =
	window.gf_spam_hexer_form_settings_strings?.pluginSettingsUrl ?? '';

export function useFormSettingsAdapter(): TechniqueSettingsAdapter {
	const overrides = useFormSettingsStore((s) => s.gfshTechniqueOverrides);
	const setOverrides = useFormSettingsStore(
		(s) => s.setGpshTechniqueOverrides
	);
	const powProtectionLevel = useFormSettingsStore(
		(s) => s.gfshPowProtectionLevel
	);
	const setPowProtectionLevel = useFormSettingsStore(
		(s) => s.setGpshPowProtectionLevel
	);
	const aiCustomContext = useFormSettingsStore((s) => s.gfshAiCustomContext);
	const setAiCustomContext = useFormSettingsStore(
		(s) => s.setGpshAiCustomContext
	);
	const aiConfidenceThreshold = useFormSettingsStore(
		(s) => s.gfshAiConfidenceThreshold
	);
	const setAiConfidenceThreshold = useFormSettingsStore(
		(s) => s.setGpshAiConfidenceThreshold
	);
	const powAction = useFormSettingsStore((s) => s.gfshPowAction);
	const setPowAction = useFormSettingsStore((s) => s.setGpshPowAction);
	const aiAction = useFormSettingsStore((s) => s.gfshAiAction);
	const setAiAction = useFormSettingsStore((s) => s.setGpshAiAction);
	const powFailMessage = useFormSettingsStore((s) => s.gfshPowFailMessage);
	const setPowFailMessage = useFormSettingsStore(
		(s) => s.setGpshPowFailMessage
	);
	const aiFailMessage = useFormSettingsStore((s) => s.gfshAiFailMessage);
	const setAiFailMessage = useFormSettingsStore(
		(s) => s.setGpshAiFailMessage
	);

	const globalSettings = useMemo(() => getGlobalSettings(), []);

	// Helper to update a single technique override
	const updateOverride = (
		key: keyof TechniqueOverrides,
		value: boolean | undefined
	) => {
		const next = { ...overrides };
		if (value === undefined) {
			delete next[key];
		} else {
			next[key] = value;
		}
		setOverrides(next);
	};

	// Resolve effective values (override ?? global)
	const powEnabled = overrides.pow_enabled ?? globalSettings.powEnabled;
	const aiEnabled = overrides.ai_enabled ?? globalSettings.aiEnabled;
	const effectiveProtectionLevel =
		powProtectionLevel || globalSettings.powProtectionLevel || 'standard';

	// Override detection
	const powHasAnyOverride =
		overrides.pow_enabled !== undefined ||
		powAction !== '' ||
		powProtectionLevel !== '' ||
		powFailMessage !== '';

	const aiHasAnyOverride =
		overrides.ai_enabled !== undefined ||
		aiAction !== '' ||
		aiCustomContext !== '' ||
		aiConfidenceThreshold !== '' ||
		aiFailMessage !== '';

	return {
		mode: 'form',

		// PoW
		powEnabled,
		powAction: powAction || globalSettings.powAction,
		powProtectionLevel: effectiveProtectionLevel,
		powFailMessage: powFailMessage || globalSettings.powFailMessage,
		setPowEnabled: (enabled) => updateOverride('pow_enabled', enabled),
		setPowAction,
		setPowProtectionLevel: (level) =>
			handleProtectionLevelChange(level, { setPowProtectionLevel }),
		setPowFailMessage,

		// AI
		aiEnabled,
		aiAction: aiAction || globalSettings.aiAction,
		aiCustomContext,
		aiConfidenceThreshold: aiConfidenceThreshold
			? parseFloat(aiConfidenceThreshold)
			: globalSettings.aiConfidenceThreshold,
		aiFailMessage: aiFailMessage || globalSettings.aiFailMessage,
		setAiEnabled: (enabled) => updateOverride('ai_enabled', enabled),
		setAiAction,
		setAiCustomContext,
		setAiConfidenceThreshold: (threshold) =>
			setAiConfidenceThreshold(threshold.toFixed(2)),
		setAiFailMessage,

		// Override state
		powHasAnyOverride,
		aiHasAnyOverride,
		resetAllPowOverrides: () => {
			updateOverride('pow_enabled', undefined);
			setPowAction('');
			setPowProtectionLevel('');
			setPowFailMessage('');
		},
		resetAllAiOverrides: () => {
			updateOverride('ai_enabled', undefined);
			setAiAction('');
			setAiCustomContext('');
			setAiConfidenceThreshold('');
			setAiFailMessage('');
		},

		// Per-field override status
		overrides: {
			powAction: powAction !== '',
			powProtectionLevel: powProtectionLevel !== '',
			powFailMessage: powFailMessage !== '',
			aiAction: aiAction !== '',
			aiConfidenceThreshold: aiConfidenceThreshold !== '',
			aiFailMessage: aiFailMessage !== '',
		},

		// Per-field reset handlers
		resetField: {
			powAction: () => setPowAction(''),
			powProtectionLevel: () => {
				setPowProtectionLevel('');
			},
			powFailMessage: () => setPowFailMessage(''),
			aiAction: () => setAiAction(''),
			aiConfidenceThreshold: () => setAiConfidenceThreshold(''),
			aiFailMessage: () => setAiFailMessage(''),
		},

		// No AI provider config in form mode
		aiExtraContent: undefined,
		aiInfoModalContent: (
			<AiInfoContent pluginSettingsUrl={pluginSettingsUrl || undefined} />
		),
	};
}
