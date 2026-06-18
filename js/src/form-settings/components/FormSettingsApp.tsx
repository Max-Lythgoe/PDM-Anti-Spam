/**
 * Form Settings App Component
 *
 * Main component that renders the single-page layout for per-form spam protection settings.
 * Watches the GF-rendered gfsh_enabled select to show/hide the React settings UI.
 */

import { TechniqueOverrideSettings } from './TechniqueOverrideSettings';
import { useGFToggleValue } from '../../shared/hooks/useGFToggleValue';

/**
 * Main Form Settings App
 *
 * Renders technique override settings in a single-page layout.
 *
 * Watches the GF-rendered gfsh_enabled select field and hides the entire
 * settings UI when "disabled" is selected, since there's nothing to configure.
 */
export const FormSettingsApp = () => {
	const isEnabled = useGFToggleValue({
		selector: 'select[name="_gform_setting_gfsh_enabled"]',
		disabledValue: 'disabled',
		defaultEnabled: true,
	});

	// Don't render anything when spam protection is disabled
	if (!isEnabled) {
		return null;
	}

	return (
		<div className="gfsh-settings-layout">
			<TechniqueOverrideSettings />
		</div>
	);
};
