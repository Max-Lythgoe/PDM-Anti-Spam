/**
 * Settings Field Component
 *
 * Zero-CSS wrapper that emits the correct Gravity Forms field markup:
 * `.gform-settings-field` > `.gform-settings-field__header` > `.gform-settings-label`
 * + control + `.gform-settings-description`.
 *
 * All visual styling (spacing, font sizes, colors) is inherited from GF's admin CSS
 * when rendered inside a `.gform-settings-panel__content` container.
 *
 * This is the successor to FormField.tsx — same API, better name, documented intent.
 *
 * @example
 * ```tsx
 * <SettingsField label="API Key" htmlFor="api-key" tooltip="Your OpenRouter key">
 *   <input id="api-key" type="password" />
 * </SettingsField>
 * ```
 */

import type { ReactNode } from 'react';
import { Tooltip } from '@gravitywiz/gc-components';

export interface SettingsFieldProps {
	/** Field label */
	label: string;
	/** HTML id for the primary control (used in label's htmlFor) */
	htmlFor?: string;
	/** Help/description text shown below the control */
	description?: ReactNode;
	/** Tooltip shown as an icon next to the label */
	tooltip?: string;
	/** The form control(s) */
	children: ReactNode;
	/** Additional CSS class */
	className?: string;
	/**
	 * Optional node rendered inline with the label (right-aligned).
	 * Used for per-field reset links in form-override mode.
	 */
	labelAction?: ReactNode;
}

export const SettingsField = ({
	label,
	htmlFor,
	description,
	tooltip,
	children,
	className = '',
	labelAction,
}: SettingsFieldProps) => (
	<div className={`gform-settings-field ${className}`.trim()}>
		<div className="gform-settings-field__header">
			<label className="gform-settings-label" htmlFor={htmlFor}>
				{label}
				{tooltip && <Tooltip content={tooltip} />}
			</label>
			{labelAction && (
				<span className="gform-settings-field__label-action">
					{labelAction}
				</span>
			)}
		</div>
		{children}
		{description && (
			<span className="gform-settings-description">{description}</span>
		)}
	</div>
);
