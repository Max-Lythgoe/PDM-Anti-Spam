/**
 * Switch Toggle Component
 *
 * An on/off toggle switch. Replaces tri-state select dropdowns for technique
 * enable/disable. Uses a hidden checkbox for accessibility.
 *
 * @example
 * ```tsx
 * <SwitchToggle
 *   label="Enable Proof of Work"
 *   checked={true}
 *   onChange={(checked) => setEnabled(checked)}
 *   tooltip="Runs a background computation to filter bots."
 * />
 * ```
 */

import { useId } from '@wordpress/element';
import { Tooltip } from '@gravitywiz/gc-components';

import './SwitchToggle.css';

export interface SwitchToggleProps {
	/** Label displayed next to the toggle */
	label?: string;
	/** Whether the toggle is on */
	checked: boolean;
	/** Change handler */
	onChange: (checked: boolean) => void;
	/** Whether the toggle is disabled */
	disabled?: boolean;
	/** Additional CSS class */
	className?: string;
	/** Accessible label (used when no visible label) */
	ariaLabel?: string;
	/** Tooltip shown as an icon next to the label */
	tooltip?: string;
}

export const SwitchToggle = ({
	label,
	checked,
	onChange,
	disabled = false,
	className = '',
	ariaLabel,
	tooltip,
}: SwitchToggleProps) => {
	const id = useId();

	return (
		<label
			className={`gfsh-switch-toggle ${disabled ? 'gfsh-switch-toggle--disabled' : ''} ${className}`.trim()}
			htmlFor={id}
		>
			<input
				type="checkbox"
				id={id}
				className="gfsh-switch-toggle__input"
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
				disabled={disabled}
				aria-label={ariaLabel || label}
			/>
			<span
				className={`gfsh-switch-toggle__track ${checked ? 'gfsh-switch-toggle__track--on' : ''}`}
				aria-hidden="true"
			>
				<span className="gfsh-switch-toggle__thumb" />
			</span>
			{label && (
				<span className="gfsh-switch-toggle__label">
					{label}
					{tooltip && <Tooltip content={tooltip} />}
				</span>
			)}
		</label>
	);
};
