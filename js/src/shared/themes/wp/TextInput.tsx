/**
 * WP Theme — TextInput adapter
 * Wraps @wordpress/components TextControl to match the shared TextInputProps API.
 */

import { TextControl } from '@wordpress/components';
import type { TextInputProps } from '../../context/UIContext';

export const TextInput = ({
	label,
	value,
	onChange,
	placeholder,
	help,
	type = 'text',
	disabled,
	readOnly,
	id,
}: TextInputProps) => (
	<TextControl
		label={label}
		value={value}
		onChange={onChange}
		placeholder={placeholder}
		help={help}
		type={type}
		disabled={disabled}
		readOnly={readOnly}
		id={id}
	/>
);
