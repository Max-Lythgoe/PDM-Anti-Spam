/**
 * Native Text Input Component
 *
 * Matches Gravity Forms settings panel input styling.
 */

export interface TextInputProps {
	label: string;
	value: string;
	onChange: (value: string) => void;
	placeholder?: string;
	help?: string;
	type?: 'text' | 'email' | 'url' | 'tel' | 'password' | 'search';
	disabled?: boolean;
	readOnly?: boolean;
	id?: string;
}

export const TextInput = ({
	label,
	value,
	onChange,
	placeholder = '',
	help,
	type = 'text',
	disabled = false,
	readOnly = false,
	id,
}: TextInputProps) => {
	const inputId =
		id || `gfsh-input-${label.toLowerCase().replace(/\s+/g, '-')}`;

	return (
		<div className="gform-settings-field gform-settings-field__text">
			<div className="gform-settings-field__header">
				<label className="gform-settings-label" htmlFor={inputId}>
					{label}
				</label>
			</div>
			<div className="gform-settings-input__container">
				<input
					type={type}
					id={inputId}
					value={value}
					onChange={(e) => onChange(e.target.value)}
					placeholder={placeholder}
					disabled={disabled}
					readOnly={readOnly}
				/>
			</div>
			{help && <span className="gform-settings-field__hint">{help}</span>}
		</div>
	);
};
