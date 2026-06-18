/**
 * Tabs Component
 *
 * A reusable tabs component styled to match Gravity Forms / WordPress admin UI.
 * Supports controlled and uncontrolled modes.
 */

import { useState, useCallback, ReactNode } from 'react';

import './Tabs.css';

export interface Tab {
	/** Unique identifier for the tab */
	id: string;
	/** Display label for the tab */
	label: string;
	/** Optional icon to display before the label */
	icon?: ReactNode;
	/** Whether the tab is disabled */
	disabled?: boolean;
}

export interface TabsProps {
	/** Array of tab definitions */
	tabs: Tab[];
	/** Content to render for each tab (keyed by tab id) */
	children: Record<string, ReactNode>;
	/** Currently active tab id (controlled mode) */
	activeTab?: string;
	/** Callback when tab changes */
	onTabChange?: (tabId: string) => void;
	/** Default active tab id (uncontrolled mode) */
	defaultTab?: string;
	/** Additional CSS class for the container */
	className?: string;
}

export const Tabs = ({
	tabs,
	children,
	activeTab: controlledActiveTab,
	onTabChange,
	defaultTab,
	className = '',
}: TabsProps) => {
	// Internal state for uncontrolled mode
	const [internalActiveTab, setInternalActiveTab] = useState<string>(
		defaultTab || tabs[0]?.id || ''
	);

	// Determine if we're in controlled mode
	const isControlled = controlledActiveTab !== undefined;
	const activeTab = isControlled ? controlledActiveTab : internalActiveTab;

	const handleTabClick = useCallback(
		(tabId: string) => {
			const tab = tabs.find((t) => t.id === tabId);
			if (tab?.disabled) {
				return;
			}

			if (!isControlled) {
				setInternalActiveTab(tabId);
			}
			onTabChange?.(tabId);
		},
		[tabs, isControlled, onTabChange]
	);

	const handleKeyDown = useCallback(
		(e: React.KeyboardEvent, currentIndex: number) => {
			const enabledTabs = tabs.filter((t) => !t.disabled);
			const currentEnabledIndex = enabledTabs.findIndex(
				(t) => t.id === tabs[currentIndex].id
			);

			let newIndex = -1;

			switch (e.key) {
				case 'ArrowLeft':
					e.preventDefault();
					newIndex =
						currentEnabledIndex > 0
							? currentEnabledIndex - 1
							: enabledTabs.length - 1;
					break;
				case 'ArrowRight':
					e.preventDefault();
					newIndex =
						currentEnabledIndex < enabledTabs.length - 1
							? currentEnabledIndex + 1
							: 0;
					break;
				case 'Home':
					e.preventDefault();
					newIndex = 0;
					break;
				case 'End':
					e.preventDefault();
					newIndex = enabledTabs.length - 1;
					break;
				default:
					return;
			}

			if (newIndex >= 0 && enabledTabs[newIndex]) {
				handleTabClick(enabledTabs[newIndex].id);
				// Focus the new tab button
				const tabButton = document.querySelector(
					`[data-tab-id="${enabledTabs[newIndex].id}"]`
				) as HTMLButtonElement;
				tabButton?.focus();
			}
		},
		[tabs, handleTabClick]
	);

	return (
		<div className={`gfsh-tabs ${className}`.trim()}>
			{/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role */}
			<nav className="gform-settings-tabs__navigation" role="tablist">
				{tabs.map((tab, index) => (
					// eslint-disable-next-line jsx-a11y/anchor-is-valid
					<a
						key={tab.id}
						href="#"
						role="tab"
						data-tab-id={tab.id}
						className={activeTab === tab.id ? 'active' : ''}
						aria-selected={activeTab === tab.id}
						aria-controls={`gfsh-tabpanel-${tab.id}`}
						tabIndex={activeTab === tab.id ? 0 : -1}
						onClick={(e) => {
							e.preventDefault();
							handleTabClick(tab.id);
						}}
						onKeyDown={(e) => handleKeyDown(e, index)}
					>
						{tab.icon && (
							<span className="gfsh-tabs__tab-icon">
								{tab.icon}
							</span>
						)}
						{tab.label}
					</a>
				))}
			</nav>
			<div className="gfsh-tabs__panels">
				{tabs.map((tab) => (
					<div
						key={tab.id}
						id={`gfsh-tabpanel-${tab.id}`}
						role="tabpanel"
						aria-labelledby={tab.id}
						className={`gfsh-tabs__panel ${
							activeTab === tab.id
								? 'gfsh-tabs__panel--active'
								: ''
						}`}
						hidden={activeTab !== tab.id}
					>
						{children[tab.id]}
					</div>
				))}
			</div>
		</div>
	);
};
