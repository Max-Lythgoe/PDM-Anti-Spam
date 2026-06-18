/**
 * GF Spam Hexer — Dev Tools Extension
 *
 * Entry point that wires together the dev tools modules.
 * Loaded only when Dev Tools is active (enqueued via Dev_Tools_JS).
 *
 * @module dev-tools
 */

import { initLogSubscriber } from './event-log';
import { initInShadowRoot } from './live-status';

// Start collecting log entries immediately (before QM panel is ready).
initLogSubscriber();

// Bind buttons and start live updates when DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => initInShadowRoot());
} else {
	initInShadowRoot();
}
