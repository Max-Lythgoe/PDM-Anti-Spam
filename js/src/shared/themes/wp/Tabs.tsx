/**
 * WP Theme — Tabs adapter
 * Wraps @wordpress/components TabPanel to match the shared TabsProps API.
 *
 * API differences:
 * - WP TabPanel uses { name, title } tabs; shared uses { id, label, icon }
 * - WP TabPanel uses a render-prop children; shared uses Record<string, ReactNode>
 * - WP TabPanel manages active tab internally; shared supports controlled mode via activeTab/onTabChange
 */

import { TabPanel } from '@wordpress/components';
import type { TabsProps } from '../../context/UIContext';

export const Tabs = ({
	tabs,
	children,
	activeTab,
	onTabChange,
	className,
}: TabsProps) => {
	const wpTabs = tabs.map((tab) => ({
		name: tab.id,
		title: tab.label,
		disabled: tab.disabled,
	}));

	return (
		<TabPanel
			className={className}
			tabs={wpTabs}
			initialTabName={activeTab ?? tabs[0]?.id}
			onSelect={(tabName) => onTabChange?.(tabName)}
		>
			{(tab) => <>{children[tab.name]}</>}
		</TabPanel>
	);
};
