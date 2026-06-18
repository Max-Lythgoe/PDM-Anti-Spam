/**
 * Native Range Input Component
 *
 * Range slider with labels and marks, matching Gravity Forms / WordPress admin styling.
 */

import './RangeInput.css';

export interface RangeMark {
	value: number;
	label: string;
}

export interface RangeInputProps {
	/** Label for the input */
	label?: string;
	/** Current value */
	value: number;
	/** Change handler */
	onChange: (value: number) => void;
	/** Minimum value */
	min?: number;
	/** Maximum value */
	max?: number;
	/** Step increment */
	step?: number;
	/** Whether the input is disabled */
	disabled?: boolean;
	/** Help text displayed below the input */
	help?: string;
	/** Marks to display on the track */
	marks?: RangeMark[];
	/** Additional CSS class */
	className?: string;
	/** ID for the input */
	id?: string;
	/** Format the displayed value (default: `${value}%`) */
	formatValue?: (value: number) => string;
}

export const RangeInput = ({
	label,
	value,
	onChange,
	min = 0,
	max = 100,
	step = 1,
	disabled = false,
	help,
	marks,
	className = '',
	id,
	formatValue,
}: RangeInputProps) => {
	const inputId =
		id ||
		`gpm-range-${(label || 'range').toLowerCase().replace(/\s+/g, '-')}`;

	// Calculate percentage for styling the track fill
	const percentage = ((value - min) / (max - min)) * 100;

	const displayValue = formatValue ? formatValue(value) : `${value}%`;

	return (
		<div
			className={`gpm-range-input ${disabled ? 'gpm-range-input--disabled' : ''} ${className}`.trim()}
		>
			{label && (
				<label className="gpm-range-input__label" htmlFor={inputId}>
					{label}
				</label>
			)}

			<div className="gpm-range-input__track-container">
				<input
					type="range"
					id={inputId}
					className="gpm-range-input__slider"
					value={value}
					onChange={(e) => onChange(parseInt(e.target.value, 10))}
					min={min}
					max={max}
					step={step}
					disabled={disabled}
					style={
						{
							'--range-progress': `${percentage}%`,
						} as React.CSSProperties
					}
				/>

				{/* Value badge tracks the thumb position.
				    Thumb is 20px wide; thumb center = percentage% * (trackW - 20px) / trackW + 10px.
				    Simplified: left = percentage% + (10 - percentage * 0.2)px.
				    Clamped so the badge (≈48px wide, half=24px) never overflows the track. */}
				<span
					className="gpm-range-input__value"
					style={{
						left: `clamp(24px, calc(${percentage}% + ${10 - percentage * 0.2}px), calc(100% - 24px))`,
					}}
				>
					{displayValue}
				</span>

				{marks && marks.length > 0 && (
					<div className="gpm-range-input__marks">
						{marks.map((mark, index) => {
							const markPosition =
								((mark.value - min) / (max - min)) * 100;
							const isFirst = index === 0;
							const isLast = index === marks.length - 1;
							const markClass = [
								'gpm-range-input__mark',
								isFirst && 'gpm-range-input__mark--first',
								isLast && 'gpm-range-input__mark--last',
							]
								.filter(Boolean)
								.join(' ');
							return (
								<span
									key={mark.value}
									className={markClass}
									style={{ left: `${markPosition}%` }}
								>
									{mark.label}
								</span>
							);
						})}
					</div>
				)}
			</div>

			{help && <p className="gpm-range-input__help">{help}</p>}
		</div>
	);
};
