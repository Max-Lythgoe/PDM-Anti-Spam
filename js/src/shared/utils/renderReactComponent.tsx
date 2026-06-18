/**
 * Render React Component Utility
 *
 * Helper function to render React components into DOM containers.
 * Follows the GC API Alchemist pattern.
 */

import { createRoot, render } from '@wordpress/element';
import { createLogger } from '../logger';

const logger = createLogger('RenderReactComponent');

/**
 * Render a React component into a container element.
 *
 * Uses createRoot (React 18+) if available, falls back to render for older versions.
 *
 * @param container - The DOM element to render into
 * @param Component - The React component to render
 */
export function renderReactComponent(
	container: Element | null,
	Component: () => React.ReactNode | null
) {
	if (!container) {
		logger.warn('Container not found for React component');
		return;
	}

	// Clear loading text
	container.innerHTML = '';

	if (typeof createRoot === 'function') {
		const root = createRoot(container);
		root.render(<Component />);
	} else {
		render(<Component />, container);
	}
}

/**
 * Configuration for rendering multiple React components
 */
interface RenderConfig {
	container: Element | null | (() => Element | null);
	Component: () => JSX.Element | null;
	setup?: (el: Element | null) => void;
}

/**
 * Render multiple React components into their respective containers.
 *
 * @param roots - Array of render configurations
 */
export function renderReactComponents(roots: RenderConfig[]) {
	roots.forEach(({ container, Component, setup }) => {
		let resolvedContainer = container;

		if (typeof container === 'function') {
			resolvedContainer = container();
		}

		if (setup && resolvedContainer) {
			setup(resolvedContainer as Element);
		}

		renderReactComponent(resolvedContainer as Element | null, Component);
	});
}
