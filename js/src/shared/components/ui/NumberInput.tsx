/**
 * Native Number Input Component
 *
 * Matches Gravity Forms settings panel input styling.
 * For inputs with unit selection, use UnitControl instead.
 */

import './NumberInput.css';

export interface NumberInputProps {
	label: string;
	value: number;
	onChange: (value: number) => void;
	placeholder?: string;
	help?: string;
	min?: number;
	max?: number;
	step?: number;
	disabled?: boolean;
	readOnly?: boolean;
	id?: string;
	/**
	 * Static suffix to display after the input (e.g., "ms", "px")
	 */
	suffix?: string;
}

export const NumberInput = ({
	label,
	value,
	onChange,
	placeholder = '',
	help,
	min,
	max,
	step = 1,
	disabled = false,
	readOnly = false,
	id,
	suffix,
}: NumberInputProps) => {
	const inputId =
		id || `gfsh-input-${label.toLowerCase().replace(/\s+/g, '-')}`;

	return (
		<div className="gform-settings-field gform-settings-field__number">
			<div className="gform-settings-field__header">
				<label className="gform-settings-label" htmlFor={inputId}>
					{label}
				</label>
			</div>
			<div className="gform-settings-input__container">
				<div className="gfsh-number-input__wrapper">
					<input
						type="number"
						id={inputId}
						value={value}
						onChange={(e) =>
							onChange(parseInt(e.target.value, 10) || 0)
						}
						placeholder={placeholder}
						min={min}
						max={max}
						step={step}
						disabled={disabled}
						readOnly={readOnly}
						className={
							suffix ? 'gfsh-number-input--with-suffix' : ''
						}
					/>
					{suffix && (
						<span className="gfsh-number-input__suffix">
							{suffix}
						</span>
					)}
				</div>
			</div>
			{help && <span className="gform-settings-field__hint">{help}</span>}
		</div>
	);
};
