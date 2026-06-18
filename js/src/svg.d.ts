/**
 * Type declarations for SVG imports via @svgr/webpack.
 * SVG files imported in React components are transformed into React components.
 */

declare module '*.svg' {
	import type { FC, SVGProps } from 'react';
	const ReactComponent: FC<SVGProps<SVGSVGElement>>;
	export default ReactComponent;
}

/**
 * Type declarations for CSS side-effect imports.
 * CSS files are processed by webpack's css-loader / style-loader at build time.
 */
declare module '*.css' {}
