/**
 * Native Select Input Component
 *
 * Matches Gravity Forms settings panel select styling.
 */

export interface SelectOption {
	label: string;
	value: string;
}

export interface SelectInputProps {
	label?: string;
	value: string;
	onChange: (value: string) => void;
	options: SelectOption[];
	help?: string;
	disabled?: boolean;
	id?: string;
}

export const SelectInput = ({
	label,
	value,
	onChange,
	options,
	help,
	disabled = false,
	id,
}: SelectInputProps) => {
	const inputId =
		id ||
		`gfsh-select-${(label || 'select').toLowerCase().replace(/\s+/g, '-')}`;

	return (
		<div className="gform-settings-field gform-settings-field__select">
			{label && (
				<div className="gform-settings-field__header">
					<label className="gform-settings-label" htmlFor={inputId}>
						{label}
					</label>
				</div>
			)}
			<div className="gform-settings-input__container">
				<select
					id={inputId}
					value={value}
					onChange={(e) => onChange(e.target.value)}
					disabled={disabled}
				>
					{options.map((option) => (
						<option key={option.value} value={option.value}>
							{option.label}
						</option>
					))}
				</select>
			</div>
			{help && <span className="gform-settings-field__hint">{help}</span>}
		</div>
	);
};
