/**
 * Plugin Settings Entry Point
 *
 * Initializes the React app for global plugin settings,
 * sets up state syncing to hidden fields, and renders into the DOM.
 */

import { PluginSettingsApp } from './components/PluginSettingsApp';
import { renderReactComponent } from '../form-settings/utils/renderReactComponent';
import { usePluginSettingsStore } from './store';
import { createStateSyncer } from '../shared/utils/syncStateToHiddenFields';
import { pluginSettingsMappings } from './utils/syncStateToHiddenFields';
import { UIProvider } from '../shared/context/UIContext';
import { gfTheme } from '../shared/themes/gf-theme';

// Shared base styles from form-settings (GF field hints, react root loader, etc.)
import '../form-settings/form-settings.css';
// Plugin-settings-specific overrides
import './plugin-settings.css';
// Dashboard stats styles (shared — also used by comment-settings)
import '../shared/components/DashboardStats.css';

// Set up automatic state syncing to hidden fields
createStateSyncer(
	usePluginSettingsStore,
	pluginSettingsMappings,
	'_gform_setting_'
);

// Render into the React root container
renderReactComponent(
	document.querySelector('#gfsh-plugin-settings-root'),
	() => (
		<UIProvider components={gfTheme}>
			<PluginSettingsApp />
		</UIProvider>
	)
);
