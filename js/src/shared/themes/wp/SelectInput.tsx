/**
 * WP Theme — SelectInput adapter
 * Wraps @wordpress/components SelectControl to match the shared SelectInputProps API.
 */

import { SelectControl } from '@wordpress/components';
import type { SelectInputProps } from '../../context/UIContext';

export const SelectInput = ({
	label,
	value,
	onChange,
	options,
	help,
	disabled,
}: SelectInputProps) => (
	<SelectControl
		label={label}
		value={value}
		onChange={onChange}
		options={options}
		help={help}
		disabled={disabled}
	/>
);
