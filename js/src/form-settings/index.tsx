/**
 * Form Settings Entry Point
 *
 * Initializes the React app for per-form spam protection settings,
 * sets up state syncing to hidden fields, and renders into the DOM.
 */

import { FormSettingsApp } from './components/FormSettingsApp';
import { renderReactComponent } from './utils/renderReactComponent';
import { useFormSettingsStore } from './store';
import { createStateSyncer } from '../shared/utils/syncStateToHiddenFields';
import { formSettingsMappings } from './utils/syncStateToHiddenFields';
import { UIProvider } from '../shared/context/UIContext';
import { gfTheme } from '../shared/themes/gf-theme';

import './form-settings.css';

// Set up automatic state syncing to hidden fields
createStateSyncer(
	useFormSettingsStore,
	formSettingsMappings,
	'_gform_setting_'
);

// Single React app - renders into our injected root element
renderReactComponent(document.querySelector('#gfsh-settings-root'), () => (
	<UIProvider components={gfTheme}>
		<FormSettingsApp />
	</UIProvider>
));
