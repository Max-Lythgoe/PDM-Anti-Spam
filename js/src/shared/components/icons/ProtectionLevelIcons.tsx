/**
 * Custom SVG icons for the three PoW protection levels.
 *
 * Each icon is a shield variant that visually communicates the
 * relative strength of the protection level:
 *
 * - Light:    Thin shield outline — minimal, fast, unobtrusive.
 * - Standard: Shield with a checkmark — balanced, recommended.
 * - Strict:   Shield with a lock — maximum protection.
 *
 * All icons share a consistent 16×16 viewBox and use `currentColor`
 * so they inherit the button text color automatically.
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
 * Light protection — thin shield outline.
 */
export const ShieldLightIcon = (props: IconProps) => (
	<svg {...defaults} {...props}>
		<path
			d="M8 1.5L2.5 3.5V7.5C2.5 10.81 4.84 13.89 8 14.5C11.16 13.89 13.5 10.81 13.5 7.5V3.5L8 1.5Z"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

/**
 * Standard protection — shield with a checkmark.
 */
export const ShieldStandardIcon = (props: IconProps) => (
	<svg {...defaults} {...props}>
		<path
			d="M8 1.5L2.5 3.5V7.5C2.5 10.81 4.84 13.89 8 14.5C11.16 13.89 13.5 10.81 13.5 7.5V3.5L8 1.5Z"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
		<path
			d="M5.75 8L7.25 9.5L10.25 6.5"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

/**
 * Strict protection — shield with a lock.
 */
export const ShieldStrictIcon = (props: IconProps) => (
	<svg {...defaults} {...props}>
		<path
			d="M8 1.5L2.5 3.5V7.5C2.5 10.81 4.84 13.89 8 14.5C11.16 13.89 13.5 10.81 13.5 7.5V3.5L8 1.5Z"
			stroke="currentColor"
			strokeWidth="1.25"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
		{/* Lock body */}
		<rect
			x="6"
			y="7.5"
			width="4"
			height="3.25"
			rx="0.75"
			stroke="currentColor"
			strokeWidth="1"
			fill="currentColor"
			fillOpacity="0.15"
		/>
		{/* Lock shackle */}
		<path
			d="M6.75 7.5V6.25C6.75 5.56 7.31 5 8 5C8.69 5 9.25 5.56 9.25 6.25V7.5"
			stroke="currentColor"
			strokeWidth="1"
			strokeLinecap="round"
		/>
	</svg>
);
