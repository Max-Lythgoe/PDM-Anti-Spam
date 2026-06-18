/**
 * Notice Component
 *
 * Renders a Gravity Forms-styled alert notice using GF's native
 * `.alert.gforms_note_*` classes. These classes provide full styling
 * (icon, colors, border, box-shadow) when rendered inside GF's
 * `.gform-settings__wrapper` context (which all our settings pages use).
 *
 * @example
 * ```tsx
 * <Notice variant="success">Your license key has been validated.</Notice>
 * <Notice variant="error">API key is invalid.</Notice>
 * <Notice variant="warning">AI provider is not configured.</Notice>
 * <Notice variant="info">Stats refresh every 5 minutes.</Notice>
 * ```
 */

import type { ReactNode } from 'react';

export type NoticeVariant = 'success' | 'error' | 'warning' | 'info';

export interface NoticeProps {
	/** Visual style variant */
	variant: NoticeVariant;
	/** Notice content */
	children: ReactNode;
	/** Additional CSS class */
	className?: string;
	/** Inline styles */
	style?: React.CSSProperties;
}

export const Notice = ({
	variant,
	children,
	className = '',
	style,
}: NoticeProps) => {
	const classes = `alert gforms_note_${variant} ${className}`.trim();

	return (
		<div className={classes} style={style}>
			{children}
		</div>
	);
};
