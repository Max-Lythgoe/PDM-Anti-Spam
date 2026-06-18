/**
 * WP Theme — RangeInput adapter
 * Wraps @wordpress/components RangeControl to match the shared RangeInputProps API.
 * Maps formatValue → renderTooltipContent, coercing WP's ControlledRangeValue to number.
 */

import { RangeControl } from '@wordpress/components';
import type { RangeInputProps } from '../../context/UIContext';

export const RangeInput = ({
	label,
	value,
	onChange,
	min = 0,
	max = 100,
	step = 1,
	disabled,
	help,
	marks,
	formatValue,
}: RangeInputProps) => (
	<RangeControl
		label={label ?? ''}
		value={value}
		onChange={(v) => onChange(v ?? min)}
		min={min}
		max={max}
		step={step}
		disabled={disabled}
		help={help}
		marks={marks}
		renderTooltipContent={
			formatValue ? (v) => formatValue(Number(v) ?? min) : undefined
		}
	/>
);
