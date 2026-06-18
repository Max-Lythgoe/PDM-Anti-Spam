/**
 * Custom SVG icons for the ActionSelector options.
 *
 * - FlagIcon:   A flag — "Flag as Spam" (mark for review).
 * - BlockIcon:  A circle-slash — "Silent Reject" (discard with fake confirmation).
 * - AlertIcon:  A triangle with exclamation — "Validation Error" (show error).
 *
 * All icons share a consistent 16×16 viewBox and use `currentColor`
 * so they inherit the button's text color automatically.
 */

import type { SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement>;

const defaults: IconProps = {
	width: 16,
	height: 16,
	viewBox: '0 0 16 16',
	fill: 'none',
	xmlns: 'http://www.w3.org/2000/svg',
	'aria-hidden': true,
	focusable: false,
	style: {
		verticalAlign: '-0.125em',
		flexShrink: 0,
	},
};

/**
 * Flag icon — represents "Flag as Spam" (mark for review).
 */
export const FlagIcon = (props: IconProps) => (
	<svg {...defaults} {...props}>
		{/* Flag pole */}
		<path
			d="M4 2V14"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
		/>
		{/* Flag body */}
		<path
			d="M4 2.5C4 2.5 5.5 2 7.5 3C9.5 4 11.5 3.5 12 3V8.5C11.5 9 9.5 9.5 7.5 8.5C5.5 7.5 4 8 4 8"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
			strokeLinejoin="round"
			fill="currentColor"
			fillOpacity="0.1"
		/>
	</svg>
);

/**
 * Block / ban icon — represents "Silent Reject" (discard with fake confirmation).
 * A circle with a diagonal slash through it.
 */
export const BlockIcon = (props: IconProps) => (
	<svg {...defaults} {...props}>
		<circle
			cx="8"
			cy="8"
			r="5.5"
			stroke="currentColor"
			strokeWidth="1.25"
		/>
		<path
			d="M4.1 11.9L11.9 4.1"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
		/>
	</svg>
);

/**
 * Alert / warning icon — represents "Validation Error" (show error on form).
 * A triangle with an exclamation mark inside.
 */
export const AlertIcon = (props: IconProps) => (
	<svg {...defaults} {...props}>
		{/* Triangle outline */}
		<path
			d="M8 2.5L14.5 13.5H1.5L8 2.5Z"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinejoin="round"
			fill="currentColor"
			fillOpacity="0.1"
		/>
		{/* Exclamation mark */}
		<path
			d="M8 6.5V9.5"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
		/>
		<circle cx="8" cy="11.5" r="0.75" fill="currentColor" />
	</svg>
);
