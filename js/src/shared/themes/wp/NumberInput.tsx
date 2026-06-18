/**
 * WP Theme — NumberInput adapter
 * Wraps @wordpress/components __experimentalNumberControl to match the shared NumberInputProps API.
 * Coerces WP's string | undefined onChange value to number.
 */

// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __experimentalNumberControl as NumberControl } from '@wordpress/components';
import type { NumberInputProps } from '../../context/UIContext';

export const NumberInput = ({
	label,
	value,
	onChange,
	min,
	max,
	step = 1,
	disabled,
	help,
	suffix,
	id,
}: NumberInputProps) => (
	<NumberControl
		label={label}
		value={value}
		onChange={(v: string | undefined) => onChange(Number(v) || 0)}
		min={min}
		max={max}
		step={step}
		disabled={disabled}
		help={help}
		suffix={suffix}
		id={id}
	/>
);
