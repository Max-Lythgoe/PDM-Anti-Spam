/**
 * WP Theme — SettingsField adapter
 * Wraps @wordpress/components BaseControl to match the shared SettingsFieldProps API.
 * Maps description → help prop name.
 * Maps tooltip → help prop name (rendered as help text below the control in WP context,
 * since gc-components Tooltip is not appropriate for the Discussion settings page).
 * Renders labelAction inline with the label via a custom header when provided.
 */

import { BaseControl } from '@wordpress/components';
import type { SettingsFieldProps } from '../../context/UIContext';

export const SettingsField = ({
	label,
	htmlFor,
	description,
	tooltip,
	children,
	className,
	labelAction,
}: SettingsFieldProps) => {
	// In WP context, tooltip falls back to rendering as help text below the control.
	const helpText = description ?? tooltip;

	if (labelAction) {
		return (
			<BaseControl
				id={htmlFor ?? ''}
				help={helpText}
				className={className}
			>
				<div className="gfsh-settings-field-header">
					<BaseControl.VisualLabel>{label}</BaseControl.VisualLabel>
					<span className="gfsh-settings-field-header__action">
						{labelAction}
					</span>
				</div>
				{children}
			</BaseControl>
		);
	}

	return (
		<BaseControl
			label={label}
			id={htmlFor ?? ''}
			help={helpText}
			className={className}
		>
			{children}
		</BaseControl>
	);
};
