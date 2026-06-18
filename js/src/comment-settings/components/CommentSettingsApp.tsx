/**
 * Comment Settings App Component
 *
 * Main component for the WordPress comment spam protection settings.
 * Renders a tabbed layout: Settings tab (techniques + bypass) and Stats tab.
 * Uses the shared TechniqueSettings component via the comment adapter.
 *
 * Comments always mark detected spam as 'spam' — no action selector needed.
 * The spam action can be customized via the `gfsh_comment_spam_action` filter.
 */

import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TechniqueSettings } from '../../shared/components/TechniqueSettings';
import { BypassRulesCard } from '../../shared/components/BypassRulesCard';
import type { Tab } from '../../shared/components/ui/Tabs';
import { useUI } from '../../shared/context/UIContext';
import { useCommentSettingsAdapter } from '../hooks/useCommentSettingsAdapter';
import { useCommentSettingsStore } from '../store';
import { CommentDashboardStats } from './CommentDashboardStats';
import GearIcon from '../../shared/components/icons/svg/gear.svg';
import ChartIcon from '../../shared/components/icons/svg/chart.svg';

const COMMENT_TABS: Tab[] = [
	{
		id: 'settings',
		label: __('Settings', 'gf-spam-hexer'),
		icon: <GearIcon />,
	},
	{
		id: 'stats',
		label: __('Stats', 'gf-spam-hexer'),
		icon: <ChartIcon />,
	},
];

export const CommentSettingsApp = () => {
	const { Tabs } = useUI();
	const adapter = useCommentSettingsAdapter();

	const bypassLoggedIn = useCommentSettingsStore((s) => s.bypassLoggedIn);
	const setBypassLoggedIn = useCommentSettingsStore(
		(s) => s.setBypassLoggedIn
	);

	const [activeTab, setActiveTab] = useState('settings');

	const handleTabChange = useCallback((tabId: string) => {
		setActiveTab(tabId);
	}, []);

	return (
		<div className="gfsh-comment-settings">
			<Tabs
				tabs={COMMENT_TABS}
				activeTab={activeTab}
				onTabChange={handleTabChange}
			>
				{{
					settings: (
						<div className="gfsh-comment-settings__main">
							<TechniqueSettings adapter={adapter} />

							<BypassRulesCard
								bypassLoggedIn={bypassLoggedIn}
								onChange={(checked) =>
									setBypassLoggedIn(checked ? '1' : '0')
								}
							/>
						</div>
					),
					stats: (
						<div className="gfsh-comment-settings__main">
							<CommentDashboardStats />
						</div>
					),
				}}
			</Tabs>
		</div>
	);
};
