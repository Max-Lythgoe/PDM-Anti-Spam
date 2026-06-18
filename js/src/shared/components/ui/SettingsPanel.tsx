/**
 * Settings Panel Component
 *
 * Zero-CSS wrapper that emits the correct Gravity Forms `.gform-settings-panel`
 * markup structure. All visual styling (border, shadow, radius, grid-column,
 * content border-top, padding) is inherited from GF's admin CSS.
 *
 * When `title` is provided, renders as a `<fieldset>` + `<legend>` to match
 * GF's native panel markup with the `gform-settings-panel--with-title` legend
 * styling. When only `header` is provided (e.g. TechniqueCard's icon+toggle
 * header), renders as a plain `<div>` without the title modifier class so GF's
 * legend margins/padding don't interfere.
 *
 * Use this instead of manually assembling GF class names on divs.
 *
 * @example
 * ```tsx
 * // Named panel — fieldset + legend
 * <SettingsPanel title="General Settings">
 *   <SettingsField label="Option">...</SettingsField>
 * </SettingsPanel>
 *
 * // Custom header (TechniqueCard) — div, no legend styling
 * <SettingsPanel header={<TechniqueCardHeader />} collapsed={!enabled}>
 *   <SettingsField label="Action">...</SettingsField>
 * </SettingsPanel>
 * ```
 */

import type { ReactNode } from 'react';

export interface SettingsPanelProps {
	/** Panel title — renders as a GF-styled <legend>. Omit for headerless or custom-header panels. */
	title?: string;
	/** Custom header content (e.g. TechniqueCard icon+toggle). Rendered without legend styling. */
	header?: ReactNode;
	/** Panel body content */
	children: ReactNode;
	/** Half-width panel (grid-column: span 1) */
	half?: boolean;
	/** Collapsible state — hides content area when true */
	collapsed?: boolean;
	/** Additional class names */
	className?: string;
	/** No padding on content area */
	noPadding?: boolean;
}

export const SettingsPanel = ({
	title,
	header,
	children,
	half = false,
	collapsed = false,
	className = '',
	noPadding = false,
}: SettingsPanelProps) => {
	const panelClasses = [
		'gform-settings-panel',
		title ? 'gform-settings-panel--with-title' : '',
		half ? 'gform-settings-panel--half' : '',
		collapsed ? 'gform-settings-panel--collapsed' : '',
		noPadding ? 'gform-settings-panel--no-padding' : '',
		className,
	]
		.filter(Boolean)
		.join(' ');

	// Title path: fieldset + legend for native GF legend styling
	if (title) {
		return (
			<fieldset className={panelClasses}>
				<legend className="gform-settings-panel__title gform-settings-panel__title--header">
					{title}
				</legend>
				<div className="gform-settings-panel__content">{children}</div>
			</fieldset>
		);
	}

	// Custom header path (TechniqueCard, BypassRulesCard): plain div, no legend margins
	return (
		<div className={panelClasses}>
			{header && header}
			<div className="gform-settings-panel__content">{children}</div>
		</div>
	);
};
