/**
 * Settings Nested Fields Component
 *
 * Zero-CSS wrapper that emits GF's `.gform-settings-nested-fields` markup.
 * Provides the indented left-border pattern for child/conditional settings.
 *
 * GF provides: border-left: 2px solid #ececf2; margin: 0.75rem 0 0 0.625rem;
 * padding-left: 1.375rem; and lighter label weight for nested labels.
 *
 * @example
 * ```tsx
 * <SettingsField label="Enable Feature">
 *   <SwitchToggle ... />
 * </SettingsField>
 * <SettingsNestedFields>
 *   <SettingsField label="Sub-option">...</SettingsField>
 * </SettingsNestedFields>
 * ```
 */

import type { ReactNode } from 'react';

export interface SettingsNestedFieldsProps {
	children: ReactNode;
	/** Additional CSS class */
	className?: string;
}

export const SettingsNestedFields = ({
	children,
	className = '',
}: SettingsNestedFieldsProps) => (
	<div className={`gform-settings-nested-fields ${className}`.trim()}>
		{children}
	</div>
);
