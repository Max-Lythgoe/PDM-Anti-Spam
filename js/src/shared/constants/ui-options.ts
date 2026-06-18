/**
 * Shared UI Option Constants
 *
 * Reusable option arrays for settings controls.
 * Used by both plugin-settings and form-settings apps.
 */

import { __ } from '@wordpress/i18n';

/**
 * PoW difficulty solve time lookup table.
 *
 * Maps bit values to human-readable labels and estimated solve times.
 *
 * Calibrated from real-world production data (May 2026, gravitywiz.com):
 * - Desktop p50: based on actual user solve times across global traffic
 * - Mobile p50: estimated at ~2.8× desktop (observed ratio)
 * - p95 ≈ 3–4× p50 (geometric distribution with real-world variance)
 *
 * @see scripts/benchmark-pow.html — run to regenerate on different hardware.
 */
export const POW_DIFFICULTY_INFO: Record<
	number,
	{
		label: string;
		desktopP50: string;
		mobileP50: string;
		desktopP95: string;
		mobileP95: string;
	}
> = {
	13: {
		label: 'Very Light',
		desktopP50: '~70ms',
		mobileP50: '~200ms',
		desktopP95: '~210ms',
		mobileP95: '~600ms',
	},
	14: {
		label: 'Light',
		desktopP50: '~140ms',
		mobileP50: '~400ms',
		desktopP95: '~420ms',
		mobileP95: '~1.2s',
	},
	15: {
		label: 'Normal',
		desktopP50: '~285ms',
		mobileP50: '~800ms',
		desktopP95: '~850ms',
		mobileP95: '~2.4s',
	},
	16: {
		label: 'Moderate',
		desktopP50: '~570ms',
		mobileP50: '~1.5s',
		desktopP95: '~1.7s',
		mobileP95: '~4.5s',
	},
	17: {
		label: 'Firm',
		desktopP50: '~1s',
		mobileP50: '~3s',
		desktopP95: '~3.5s',
		mobileP95: '~9s',
	},
	18: {
		label: 'Strong',
		desktopP50: '~2.3s',
		mobileP50: '~6s',
		desktopP95: '~7s',
		mobileP95: '~18s',
	},
	19: {
		label: 'Heavy',
		desktopP50: '~4.5s',
		mobileP50: '~12s',
		desktopP95: '~14s',
		mobileP95: '~36s',
	},
};

/**
 * Get human-readable info for a PoW difficulty value.
 */
export function getDifficultyInfo(bits: number): {
	label: string;
	desktopP50: string;
	mobileP50: string;
	desktopP95: string;
	mobileP95: string;
} {
	return (
		POW_DIFFICULTY_INFO[bits] || {
			label: `${bits} bits`,
			desktopP50: 'unknown',
			mobileP50: 'unknown',
			desktopP95: 'unknown',
			mobileP95: 'unknown',
		}
	);
}

/**
 * Protection level preset type.
 */
export type ProtectionLevelId = 'light' | 'standard' | 'strict';

export interface ProtectionLevel {
	id: ProtectionLevelId;
	label: string;
	base: number;
	max: number;
	shortDesc: string;
	timing: string;
}

/**
 * Protection level presets for the PoW technique.
 *
 * Replaces the raw bit-value sliders with a simple 3-option selector.
 * Each preset maps to a base and max difficulty that the PHP
 * PoW_Difficulty_Manager resolves.
 */
export const POW_PROTECTION_LEVELS: readonly ProtectionLevel[] = [
	{
		id: 'light',
		label: __('Light', 'gf-spam-hexer'),
		base: 13,
		max: 16,
		shortDesc: __(
			'Fast for all devices. Basic bot deterrent.',
			'gf-spam-hexer'
		),
		timing: __(
			"Typical visitors' puzzles solve instantly. Repeat spammers' puzzles take about 1 second to solve before submitting.",
			'gf-spam-hexer'
		),
	},
	{
		id: 'standard',
		label: __('Standard', 'gf-spam-hexer'),
		base: 15,
		max: 18,
		shortDesc: __(
			'Recommended. Effective filtering without slowing down visitors.',
			'gf-spam-hexer'
		),
		timing: __(
			"Typical visitors' puzzles solve in under 1 second. Repeat spammers' puzzles take a few seconds to solve before submitting.",
			'gf-spam-hexer'
		),
	},
	{
		id: 'strict',
		label: __('Strict', 'gf-spam-hexer'),
		base: 16,
		max: 19,
		shortDesc: __(
			'Stronger protection. May be noticeable on older mobile devices.',
			'gf-spam-hexer'
		),
		timing: __(
			"Typical visitors' puzzles solve in about 1 second. Repeat spammers' puzzles take up to 10 seconds to solve before submitting.",
			'gf-spam-hexer'
		),
	},
] as const;

/**
 * Get a protection level preset by ID.
 */
export function getProtectionLevel(id: string): ProtectionLevel | undefined {
	return POW_PROTECTION_LEVELS.find((level) => level.id === id);
}
