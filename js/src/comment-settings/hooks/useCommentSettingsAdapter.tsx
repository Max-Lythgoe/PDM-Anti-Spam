/**
 * Comment Settings Adapter Hook
 *
 * Adapts the comment-settings Zustand store into the shared
 * TechniqueSettingsAdapter interface so the unified <TechniqueSettings>
 * component can render comment protection settings.
 *
 * Comment mode behaves like plugin mode (no overrides) but:
 * - No inline AI provider config (links to plugin settings page instead)
 * - No per-technique action selector (comments always mark as spam/trash via spamAction)
 * - Has its own store backed by wp_options
 */

import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useCommentSettingsStore } from '../store';
import { handleProtectionLevelChange } from '../../shared/utils/handleProtectionLevelChange';
import type { TechniqueSettingsAdapter } from '../../shared/types/technique-settings-adapter';
import { Notice } from '../../shared/components/ui';
import { AiInfoContent } from '../../shared/components/AiInfoContent';

/** Plugin settings URL from PHP-injected window object. */
const pluginSettingsUrl = window.gfsh_comment_settings?.pluginSettingsUrl ?? '';

/** Whether the AI provider is configured (from PHP-injected window object). */
const aiProviderConfigured =
	window.gfsh_comment_settings?.aiProviderConfigured ?? false;

export function useCommentSettingsAdapter(): TechniqueSettingsAdapter {
	const powEnabled = useCommentSettingsStore((s) => s.powEnabled);
	const powProtectionLevel = useCommentSettingsStore(
		(s) => s.powProtectionLevel
	);
	const aiEnabled = useCommentSettingsStore((s) => s.aiEnabled);
	const aiCustomContext = useCommentSettingsStore((s) => s.aiCustomContext);
	const aiConfidenceThreshold = useCommentSettingsStore(
		(s) => s.aiConfidenceThreshold
	);
	const powAction = useCommentSettingsStore((s) => s.powAction);
	const aiAction = useCommentSettingsStore((s) => s.aiAction);
	const powFailMessage = useCommentSettingsStore((s) => s.powFailMessage);
	const aiFailMessage = useCommentSettingsStore((s) => s.aiFailMessage);

	const setPowEnabled = useCommentSettingsStore((s) => s.setPowEnabled);
	const setPowProtectionLevel = useCommentSettingsStore(
		(s) => s.setPowProtectionLevel
	);
	const setAiEnabled = useCommentSettingsStore((s) => s.setAiEnabled);
	const setAiCustomContext = useCommentSettingsStore(
		(s) => s.setAiCustomContext
	);
	const setAiConfidenceThreshold = useCommentSettingsStore(
		(s) => s.setAiConfidenceThreshold
	);
	const setPowAction = useCommentSettingsStore((s) => s.setPowAction);
	const setAiAction = useCommentSettingsStore((s) => s.setAiAction);
	const setPowFailMessage = useCommentSettingsStore(
		(s) => s.setPowFailMessage
	);
	const setAiFailMessage = useCommentSettingsStore((s) => s.setAiFailMessage);

	/**
	 * Extra content for the AI card:
	 * Only shown when the AI provider is NOT configured — displays a warning
	 * notice with a link to configure it in plugin settings.
	 */
	const aiExtraContent = useMemo(
		() =>
			!aiProviderConfigured && pluginSettingsUrl ? (
				<div className="gfsh-ai-section gfsh-ai-provider-status">
					<Notice variant="warning">
						<p className="gfsh-ai-provider-status__notice-text">
							{__(
								'AI provider is not configured. AI classification will not run until a provider is set up.',
								'gf-spam-hexer'
							)}
						</p>
						<a
							href={`${pluginSettingsUrl}#ai`}
							className="gfsh-ai-provider-link"
						>
							{__(
								'Configure AI Provider in Plugin Settings',
								'gf-spam-hexer'
							)}
						</a>
					</Notice>
				</div>
			) : null,
		[]
	);

	return {
		mode: 'comment',

		// PoW
		powEnabled: powEnabled === '1',
		// Comments support 'spam' and 'fail' actions ('reject' is not offered).
		powAction: powAction || 'spam',
		powProtectionLevel: powProtectionLevel || 'standard',
		powFailMessage,
		setPowEnabled: (enabled) => setPowEnabled(enabled ? '1' : '0'),
		setPowAction: (action) => setPowAction(action),
		setPowProtectionLevel: (level) =>
			handleProtectionLevelChange(level, { setPowProtectionLevel }),
		setPowFailMessage: (msg) => setPowFailMessage(msg),

		// AI
		aiEnabled: aiEnabled === '1',
		// Comments support 'spam' and 'fail' actions ('reject' is not offered).
		aiAction: aiAction || 'spam',
		aiCustomContext,
		aiConfidenceThreshold: parseFloat(aiConfidenceThreshold || '0.50'),
		aiFailMessage,
		setAiEnabled: (enabled) => setAiEnabled(enabled ? '1' : '0'),
		setAiAction: (action) => setAiAction(action),
		setAiCustomContext,
		setAiConfidenceThreshold: (threshold) =>
			setAiConfidenceThreshold(threshold.toFixed(2)),
		setAiFailMessage: (msg) => setAiFailMessage(msg),

		// No overrides in comment mode
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

		// AI provider status content
		aiExtraContent,
		aiInfoModalContent: (
			<AiInfoContent
				context="comment"
				pluginSettingsUrl={
					pluginSettingsUrl ? `${pluginSettingsUrl}#ai` : undefined
				}
			/>
		),
		aiProviderConfigured,
	};
}
