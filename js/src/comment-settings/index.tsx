/**
 * Comment Settings Entry Point
 *
 * Initializes the React app for WordPress comment spam protection settings,
 * sets up state syncing to hidden fields, and renders into the DOM.
 *
 * Rendered on the WP Discussion settings page (options-discussion.php)
 * inside a container injected by Comment_Settings::render_react_root().
 */

import { CommentSettingsApp } from './components/CommentSettingsApp';
import { renderReactComponent } from '../form-settings/utils/renderReactComponent';
import { useCommentSettingsStore } from './store';
import { createStateSyncer } from '../shared/utils/syncStateToHiddenFields';
import { commentSettingsMappings } from './utils/syncStateToHiddenFields';
import { UIProvider } from '../shared/context/UIContext';
import { wpTheme } from '../shared/themes/wp-theme';

// Shared base styles
import '../form-settings/form-settings.css';
// Shared technique settings styles (cards, AI sections, etc.)
import '../shared/components/TechniqueSettings.css';
// Comment settings specific styles
import './comment-settings.css';

// Set up automatic state syncing (handles initial sync + subscribe)
createStateSyncer(useCommentSettingsStore, commentSettingsMappings, '');

// Render into the container
const container = document.getElementById('gfsh-comment-settings-root');
renderReactComponent(container, () => (
	<UIProvider components={wpTheme}>
		<CommentSettingsApp />
	</UIProvider>
));
