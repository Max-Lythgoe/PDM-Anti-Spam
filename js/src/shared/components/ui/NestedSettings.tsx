/**
 * Nested Settings Component
 *
 * A wrapper component for child/nested settings that appear indented with a left border.
 * Used for conditional options, cookie duration settings, and other dependent settings.
 *
 * @example
 * ```tsx
 * <SettingCheckbox
 *   name="enable-cookies"
 *   label="Enable Cookies"
 *   checked={enableCookies}
 *   onChange={setEnableCookies}
 * />
 * {enableCookies && (
 *   <NestedSettings>
 *     <NumberInput
 *       label="Cookie Duration (days)"
 *       value={cookieDuration}
 *       onChange={setCookieDuration}
 *     />
 *   </NestedSettings>
 * )}
 * ```
 */

import type { ReactNode } from 'react';

import './NestedSettings.css';

export interface NestedSettingsProps {
	/** The nested settings content */
	children: ReactNode;
	/** Whether to animate the appearance (default: true) */
	animated?: boolean;
	/** Additional CSS class names */
	className?: string;
}

export const NestedSettings = ({
	children,
	animated = true,
	className = '',
}: NestedSettingsProps) => {
	const classNames = [
		'nested-settings',
		animated ? 'nested-settings--animated' : '',
		className,
	]
		.filter(Boolean)
		.join(' ');

	return <div className={classNames}>{children}</div>;
};
