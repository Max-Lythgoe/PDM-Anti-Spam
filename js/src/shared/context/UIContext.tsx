/**
 * UI Theme Context
 *
 * Provides a swappable set of UI primitives so shared components render
 * correctly in both the Gravity Forms admin (GF CSS classes) and the native
 * WordPress admin (wp-components).
 *
 * Prop interfaces are imported from the canonical GF component implementations
 * and re-exported here so WP adapters and consumers share a single source of truth.
 *
 * Usage:
 *   // Entry point wraps with the appropriate theme:
 *   <UIProvider components={gfTheme}><App /></UIProvider>
 *   <UIProvider components={wpTheme}><App /></UIProvider>
 *
 *   // Shared components consume primitives via hook:
 *   const { SwitchToggle, RangeInput } = useUI();
 */

import { createContext, useContext } from '@wordpress/element';
import type { ComponentType, ReactNode } from 'react';

// ─── Re-export prop interfaces from canonical GF implementations ──────────────

export type { TextInputProps } from '../components/ui/TextInput';
export type {
	SelectOption,
	SelectInputProps,
} from '../components/ui/SelectInput';
export type { SwitchToggleProps } from '../components/ui/SwitchToggle';
export type { NumberInputProps } from '../components/ui/NumberInput';
export type { RangeMark, RangeInputProps } from '../components/ui/RangeInput';
export type { NoticeProps, NoticeVariant } from '../components/ui/Notice';
export type { ButtonProps } from '../components/ui/Button';
export type { SettingsFieldProps } from '../components/ui/SettingsField';
export type { InfoModalProps } from '../components/ui/InfoModal';
export type { SettingsPanelProps } from '../components/ui/SettingsPanel';
export type {
	SegmentedOption,
	SegmentedControlProps,
} from '../components/ui/SegmentedControl';
export type { Tab, TabsProps } from '../components/ui/Tabs';
export type { CardProps } from '../components/ui/Card';

// ─── Import for use in UIComponents interface ─────────────────────────────────

import type { TextInputProps } from '../components/ui/TextInput';
import type { SelectInputProps } from '../components/ui/SelectInput';
import type { SwitchToggleProps } from '../components/ui/SwitchToggle';
import type { NumberInputProps } from '../components/ui/NumberInput';
import type { RangeInputProps } from '../components/ui/RangeInput';
import type { NoticeProps } from '../components/ui/Notice';
import type { ButtonProps } from '../components/ui/Button';
import type { SettingsFieldProps } from '../components/ui/SettingsField';
import type { InfoModalProps } from '../components/ui/InfoModal';
import type { SettingsPanelProps } from '../components/ui/SettingsPanel';
import type { SegmentedControlProps } from '../components/ui/SegmentedControl';
import type { TabsProps } from '../components/ui/Tabs';
import type { CardProps } from '../components/ui/Card';

// ─── UIComponents interface ───────────────────────────────────────────────────

export interface UIComponents {
	TextInput: ComponentType<TextInputProps>;
	SelectInput: ComponentType<SelectInputProps>;
	SwitchToggle: ComponentType<SwitchToggleProps>;
	NumberInput: ComponentType<NumberInputProps>;
	RangeInput: ComponentType<RangeInputProps>;
	Notice: ComponentType<NoticeProps>;
	Button: ComponentType<ButtonProps>;
	SettingsField: ComponentType<SettingsFieldProps>;
	InfoModal: ComponentType<InfoModalProps>;
	SettingsPanel: ComponentType<SettingsPanelProps>;
	SegmentedControl: ComponentType<SegmentedControlProps>;
	Tabs: ComponentType<TabsProps>;
	Card: ComponentType<CardProps>;
}

// ─── Context ──────────────────────────────────────────────────────────────────

const UIContext = createContext<UIComponents | null>(null);

export const UIProvider = ({
	components,
	children,
}: {
	components: UIComponents;
	children: ReactNode;
}) => <UIContext.Provider value={components}>{children}</UIContext.Provider>;

export const useUI = (): UIComponents => {
	const ctx = useContext(UIContext);
	if (!ctx) {
		throw new Error('useUI must be used inside a UIProvider');
	}
	return ctx;
};
