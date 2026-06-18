/**
 * WP Theme — SwitchToggle adapter
 * Wraps @wordpress/components ToggleControl to match the shared SwitchToggleProps API.
 * Maps tooltip → help prop (rendered as help text below the toggle in WP context).
 */

import { ToggleControl } from '@wordpress/components';
import type { SwitchToggleProps } from '../../context/UIContext';

export const SwitchToggle = ({
	label,
	checked,
	onChange,
	disabled,
	tooltip,
}: SwitchToggleProps) => (
	<ToggleControl
		label={label ?? ''}
		checked={checked}
		onChange={onChange}
		disabled={disabled}
		help={tooltip}
	/>
);
