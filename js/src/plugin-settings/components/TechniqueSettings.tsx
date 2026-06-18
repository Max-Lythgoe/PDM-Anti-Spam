/**
 * Technique Settings Component (Plugin/Global)
 *
 * Thin wrapper that connects the plugin-settings store to the unified
 * TechniqueSettings component via the adapter pattern.
 */

import { TechniqueSettings as UnifiedTechniqueSettings } from '../../shared/components/TechniqueSettings';
import { usePluginSettingsAdapter } from '../hooks/usePluginSettingsAdapter';

export const TechniqueSettings = () => {
	const adapter = usePluginSettingsAdapter();
	return <UnifiedTechniqueSettings adapter={adapter} />;
};
