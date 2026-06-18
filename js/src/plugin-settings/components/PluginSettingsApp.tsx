/**
 * Plugin Settings App Component
 *
 * Main component for the global plugin settings page (Forms → Settings → Spam Hexer).
 * Renders a tabbed layout: Settings tab (techniques + bypass), AI Provider tab,
 * and Stats tab (dashboard). Supports URL hash deep-linking (#settings, #ai, #stats).
 */

import { __ } from '@wordpress/i18n';
import { TechniqueSettings } from './TechniqueSettings';
import { DashboardStats } from './DashboardStats';
import { AiProviderConfig } from '../../shared/components/AiProviderConfig';
import { BypassRulesCard } from '../../shared/components/BypassRulesCard';
import { useGFToggleValue } from '../../shared/hooks/useGFToggleValue';
import { useHashTab } from '../../shared/hooks/useHashTab';
import { usePluginSettingsStore } from '../store';
import { Tabs } from '../../shared/components/ui/Tabs';
import type { Tab } from '../../shared/components/ui/Tabs';
import { SettingsPanel } from '../../shared/components/ui/SettingsPanel';
import {
	connectorsUrl,
	wpAiClientAvailable,
	availableModelsAuto,
	availableModelsOpenRouter,
} from '../constants';
import GearIcon from '../../shared/components/icons/svg/gear.svg';
import AiIcon from '../../shared/components/icons/svg/ai.svg';
import ChartIcon from '../../shared/components/icons/svg/chart.svg';
import { Notice } from '@shared/components/ui';

const PLUGIN_TABS: Tab[] = [
	{
		id: 'settings',
		label: __('Settings', 'gf-spam-hexer'),
		icon: <GearIcon />,
	},
	{
		id: 'ai',
		label: __('AI Provider', 'gf-spam-hexer'),
		icon: <AiIcon />,
	},
	{
		id: 'stats',
		label: __('Stats', 'gf-spam-hexer'),
		icon: <ChartIcon />,
	},
];

/**
 * Main Plugin Settings App
 *
 * Watches the GF-rendered "enabled" toggle and hides the React settings
 * UI when spam protection is disabled globally.
 */
export const PluginSettingsApp = () => {
	const isEnabled = useGFToggleValue({
		selector: 'input[name="_gform_setting_enabled"]',
		disabledValue: '0',
		defaultEnabled: true,
	});

	const bypassLoggedIn = usePluginSettingsStore((s) => s.bypassLoggedIn);
	const setBypassLoggedIn = usePluginSettingsStore(
		(s) => s.setBypassLoggedIn
	);

	// AI provider store values for the AI tab
	const aiProvider = usePluginSettingsStore((s) => s.aiProvider);
	const aiApiKey = usePluginSettingsStore((s) => s.aiApiKey);
	const aiModel = usePluginSettingsStore((s) => s.aiModel);
	const aiZdr = usePluginSettingsStore((s) => s.aiZdr);
	const setAiProvider = usePluginSettingsStore((s) => s.setAiProvider);
	const setAiApiKey = usePluginSettingsStore((s) => s.setAiApiKey);
	const setAiModel = usePluginSettingsStore((s) => s.setAiModel);
	const setAiZdr = usePluginSettingsStore((s) => s.setAiZdr);

	// Controlled tab state with URL hash deep-linking
	const { activeTab, handleTabChange } = useHashTab(
		new Set(PLUGIN_TABS.map((t) => t.id)),
		'settings'
	);

	// Don't render anything when spam protection is disabled
	if (!isEnabled) {
		return null;
	}

	return (
		<div className="gfsh-settings-layout">
			<Tabs
				tabs={PLUGIN_TABS}
				activeTab={activeTab}
				onTabChange={handleTabChange}
			>
				{{
					settings: (
						<div className="gfsh-settings-layout__main">
							<TechniqueSettings />

							<BypassRulesCard
								bypassLoggedIn={bypassLoggedIn}
								onChange={(checked) =>
									setBypassLoggedIn(checked ? '1' : '0')
								}
							/>
						</div>
					),
					ai: (
						<div className="gfsh-settings-layout__main">
							<Notice
								variant="info"
								className="gfsh-ai-provider-modal__note"
							>
								{__(
									'These settings are shared plugin-wide across all forms and comments.',
									'gf-spam-hexer'
								)}
							</Notice>

							<SettingsPanel
								title={__('Provider', 'gf-spam-hexer')}
							>
								<AiProviderConfig
									aiProvider={aiProvider}
									aiApiKey={aiApiKey}
									aiModel={aiModel}
									aiZdr={aiZdr}
									setAiProvider={setAiProvider}
									setAiApiKey={setAiApiKey}
									setAiModel={setAiModel}
									setAiZdr={setAiZdr}
									wpAiClientAvailable={wpAiClientAvailable}
									connectorsUrl={connectorsUrl}
									availableModelsAuto={availableModelsAuto}
									availableModelsOpenRouter={
										availableModelsOpenRouter
									}
								/>
							</SettingsPanel>
						</div>
					),
					stats: <DashboardStats />,
				}}
			</Tabs>
		</div>
	);
};
