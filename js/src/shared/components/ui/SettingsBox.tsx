/**
 * Settings Box Component
 *
 * A minimal GF-styled container that provides only the panel chrome
 * (border, border-radius, box-shadow, background) with no internal structure.
 *
 * Use this for:
 * - Stat cards that need GF's visual treatment but no header/content split
 * - Wrapping tabs or other custom layouts in a GF-styled container
 * - Any content that needs the "card" look without field semantics
 *
 * For panels with header + content structure, use SettingsPanel instead.
 *
 * @example
 * ```tsx
 * <SettingsBox>
 *   <Tabs ... />
 * </SettingsBox>
 *
 * <SettingsBox className="gfsh-summary-card">
 *   <div className="gfsh-summary-card__value">1,234</div>
 *   <div className="gfsh-summary-card__label">Checked</div>
 * </SettingsBox>
 * ```
 */

import type { ReactNode } from 'react';

export interface SettingsBoxProps {
	/** Box content */
	children: ReactNode;
	/** Additional CSS class */
	className?: string;
	/** Half-width (grid-column: span 1) */
	half?: boolean;
}

export const SettingsBox = ({
	children,
	className = '',
	half = false,
}: SettingsBoxProps) => {
	const classes = [
		'gform-settings-panel',
		'gform-settings-panel--no-padding',
		half ? 'gform-settings-panel--half' : '',
		className,
	]
		.filter(Boolean)
		.join(' ');

	return <div className={classes}>{children}</div>;
};
