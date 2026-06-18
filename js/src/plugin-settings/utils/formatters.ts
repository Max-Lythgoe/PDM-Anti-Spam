/**
 * Formatting utilities for plugin-settings stats components.
 */

/** Format a millisecond duration as a human-readable string. */
export function formatMs(ms: number, decimals = 1): string {
	if (ms >= 1000) {
		return (ms / 1000).toFixed(decimals) + 's';
	}
	return Math.round(ms) + 'ms';
}

/** Format a number with locale-aware thousands separators. */
export function formatNumber(n: number): string {
	return n.toLocaleString();
}

/** Format a cost value as a dollar string. */
export function formatCost(cost: number): string {
	if (cost < 0.01) {
		return '$' + cost.toFixed(4);
	}
	return '$' + cost.toFixed(2);
}
