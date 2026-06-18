/**
 * Plugin Settings Adapter Hook
 *
 * Adapts the plugin-settings Zustand store into the shared
 * TechniqueSettingsAdapter interface so the unified <TechniqueSettings>
 * component can render global settings.
 *
 * Plugin mode has no override indicators — all values are definitive.
 * AI provider/model/key configuration is rendered in a dedicated tab
 * in PluginSettingsApp, not inside the technique card.
 */

import { useMemo } from '@wordpress/element';
import { usePluginSettingsStore } from '../store';
import { handleProtectionLevelChange } from '../../shared/utils/handleProtectionLevelChange';
import { AiInfoContent } from '../../shared/components/AiInfoContent';
import type { TechniqueSettingsAdapter } from '../../shared/types/technique-settings-adapter';
import { pluginSettingsUrl } from '../constants';

export function usePluginSettingsAdapter(): TechniqueSettingsAdapter {
	const powEnabled = usePluginSettingsStore((s) => s.powEnabled);
	const powProtectionLevel = usePluginSettingsStore(
		(s) => s.powProtectionLevel
	);
	const powAction = usePluginSettingsStore((s) => s.powAction);
	const powFailMessage = usePluginSettingsStore((s) => s.powFailMessage);
	const aiEnabled = usePluginSettingsStore((s) => s.aiEnabled);
	const aiCustomContext = usePluginSettingsStore((s) => s.aiCustomContext);
	const aiAction = usePluginSettingsStore((s) => s.aiAction);
	const aiFailMessage = usePluginSettingsStore((s) => s.aiFailMessage);
	const aiConfidenceThreshold = usePluginSettingsStore(
		(s) => s.aiConfidenceThreshold
	);

	const setPowEnabled = usePluginSettingsStore((s) => s.setPowEnabled);
	const setPowProtectionLevel = usePluginSettingsStore(
		(s) => s.setPowProtectionLevel
	);
	const setPowAction = usePluginSettingsStore((s) => s.setPowAction);
	const setPowFailMessage = usePluginSettingsStore(
		(s) => s.setPowFailMessage
	);
	const setAiEnabled = usePluginSettingsStore((s) => s.setAiEnabled);
	const setAiAction = usePluginSettingsStore((s) => s.setAiAction);
	const setAiFailMessage = usePluginSettingsStore((s) => s.setAiFailMessage);
	const setAiCustomContext = usePluginSettingsStore(
		(s) => s.setAiCustomContext
	);
	const setAiConfidenceThreshold = usePluginSettingsStore(
		(s) => s.setAiConfidenceThreshold
	);

	const aiInfoModalContent = useMemo(
		() => (
			<AiInfoContent pluginSettingsUrl={pluginSettingsUrl || undefined} />
		),
		[]
	);

	return {
		mode: 'plugin',

		// PoW
		powEnabled: powEnabled === '1',
		powAction,
		powProtectionLevel: powProtectionLevel || 'standard',
		powFailMessage,
		setPowEnabled: (enabled) => setPowEnabled(enabled ? '1' : '0'),
		setPowAction,
		setPowProtectionLevel: (level) =>
			handleProtectionLevelChange(level, { setPowProtectionLevel }),
		setPowFailMessage,

		// AI
		aiEnabled: aiEnabled === '1',
		aiAction,
		aiCustomContext,
		aiConfidenceThreshold: parseFloat(aiConfidenceThreshold || '0.50'),
		aiFailMessage,
		setAiEnabled: (enabled) => setAiEnabled(enabled ? '1' : '0'),
		setAiAction,
		setAiCustomContext,
		setAiConfidenceThreshold: (threshold) =>
			setAiConfidenceThreshold(threshold.toFixed(2)),
		setAiFailMessage,

		// No overrides in plugin mode
		powHasAnyOverride: false,
		aiHasAnyOverride: false,
		resetAllPowOverrides: () => {},
		resetAllAiOverrides: () => {},
		overrides: {
			powAction: false,
			powProtectionLevel: false,
			powFailMessage: false,
			aiAction: false,
			aiConfidenceThreshold: false,
			aiFailMessage: false,
		},
		resetField: {
			powAction: () => {},
			powProtectionLevel: () => {},
			powFailMessage: () => {},
			aiAction: () => {},
			aiConfidenceThreshold: () => {},
			aiFailMessage: () => {},
		},

		// AI provider config is in its own tab — no extra content in card
		aiExtraContent: undefined,
		aiInfoModalContent,
	};
}
