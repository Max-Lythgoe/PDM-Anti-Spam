/**
 * Overridable Field — Reset Button
 *
 * Renders a small inline "↩ Reset to global" button for use as the
 * `labelAction` prop on `SettingsField` when a form-level override is active.
 *
 * @example
 * ```tsx
 * <SettingsField
 *   label="When a submission fails"
 *   labelAction={
 *     isOverridden
 *       ? <OverridableFieldReset onReset={() => resetField()} />
 *       : undefined
 *   }
 * >
 *   <ActionSelector ... />
 * </SettingsField>
 * ```
 */

import { __ } from '@wordpress/i18n';
import './OverridableField.css';

interface OverridableFieldResetProps {
	onReset: () => void;
	/** Accessible label for the reset button */
	label?: string;
}

export const OverridableFieldReset = ({
	onReset,
	label,
}: OverridableFieldResetProps) => (
	<button
		type="button"
		className="gfsh-overridable-field__reset"
		onClick={onReset}
		title={label || __('Reset to global setting', 'gf-spam-hexer')}
		aria-label={label || __('Reset to global setting', 'gf-spam-hexer')}
	>
		<span className="gfsh-overridable-field__reset-icon" aria-hidden="true">
			↻
		</span>
		<span className="gfsh-overridable-field__reset-text">
			{__('Reset to global', 'gf-spam-hexer')}
		</span>
	</button>
);
