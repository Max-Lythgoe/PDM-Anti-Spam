/**
 * Segmented Control Component
 *
 * A row of mutually exclusive buttons for small discrete ranges (e.g., 1–5).
 * Better than a number input when all options should be visible at once.
 *
 * @example
 * ```tsx
 * <SegmentedControl
 *   label="Field Count"
 *   options={[
 *     { value: 1, label: '1' },
 *     { value: 2, label: '2' },
 *     { value: 3, label: '3' },
 *   ]}
 *   value={2}
 *   onChange={(v) => setCount(v)}
 * />
 * ```
 */

import type { ReactNode } from 'react';
import './SegmentedControl.css';

export interface SegmentedOption<T extends string | number = string | number> {
	value: T;
	label: ReactNode;
	/** Plain-text label for themes that cannot render ReactNode (e.g. WP ToggleGroupControl) */
	textLabel?: string;
}

export interface SegmentedControlProps<
	T extends string | number = string | number,
> {
	/** Label displayed above the control */
	label?: string;
	/** Available options */
	options: SegmentedOption<T>[];
	/** Currently selected value */
	value: T;
	/** Change handler */
	onChange: (value: T) => void;
	/** Whether the control is disabled */
	disabled?: boolean;
	/** Help text displayed below the control */
	help?: string;
	/** Additional CSS class */
	className?: string;
}

export const SegmentedControl = <T extends string | number = string | number>({
	label,
	options,
	value,
	onChange,
	disabled = false,
	help,
	className = '',
}: SegmentedControlProps<T>) => {
	return (
		<div className={`gfsh-segmented-control ${className}`.trim()}>
			{label && (
				<span className="gfsh-segmented-control__label">{label}</span>
			)}
			<div className="gfsh-segmented-control__buttons" role="radiogroup">
				{options.map((option) => (
					<button
						key={String(option.value)}
						type="button"
						role="radio"
						aria-checked={value === option.value}
						className={`gfsh-segmented-control__button ${
							value === option.value
								? 'gfsh-segmented-control__button--active'
								: ''
						}`}
						onClick={() => onChange(option.value)}
						disabled={disabled}
					>
						{option.label}
					</button>
				))}
			</div>
			{help && <p className="gfsh-segmented-control__help">{help}</p>}
		</div>
	);
};
