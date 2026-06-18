/**
 * Technique Override Settings Component
 *
 * Thin wrapper that connects the form-settings store to the unified
 * TechniqueSettings component via the adapter pattern.
 *
 * Per-form overrides use the "Inherit by Default, Override on Edit" pattern:
 * - All settings are always visible with their effective (resolved) values
 * - Editing a value automatically creates an override
 * - A "Custom ↩" chip appears on the card header when ANY setting differs from global
 * - Per-setting "↩" reset icons allow granular resets
 * - Clicking "Custom ↩" resets ALL overrides for that technique
 */

import { TechniqueSettings } from '../../shared/components/TechniqueSettings';
import { useFormSettingsAdapter } from '../hooks/useFormSettingsAdapter';

export const TechniqueOverrideSettings = () => {
	const adapter = useFormSettingsAdapter();
	return <TechniqueSettings adapter={adapter} />;
};
