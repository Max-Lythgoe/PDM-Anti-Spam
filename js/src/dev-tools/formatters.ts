/**
 * Pure formatting helpers for the dev tools panel.
 *
 * These functions have zero dependencies on any other module — they take
 * data and return strings.
 *
 * @module dev-tools/formatters
 */

import type { CollectorDebugStatus } from '../collector';
import type { LogLevel } from '@shared/logger';

/** Alias for the debug status shape. */
type PoWStatus = CollectorDebugStatus;

export function formatPoWStatus(status: PoWStatus | null): {
	text: string;
	color: string;
} {
	if (!status || status.status === 'no_manager') {
		return {
			text: 'No PoW manager',
			color: 'var(--qm-info-fg, #999)',
		};
	}

	if (status.isRefreshing) {
		return { text: 'Refreshing...', color: '#00a0d2' };
	}

	if (status.isSolving) {
		const hashes = formatNumber(status.hashesChecked);
		const elapsed = formatDuration(status.elapsedMs);
		const diff = status.difficulty ? ` d=${status.difficulty}` : '';
		return {
			text: `Solving... ${hashes} hashes, ${elapsed}${diff}`,
			color: '#ffb900',
		};
	}

	if (status.hasSolution && status.isExpired) {
		const ms = status.solution?.solve_time_ms ?? '?';
		return {
			text: `EXPIRED (solved in ${ms}ms)`,
			color: '#dc3232',
		};
	}

	if (status.hasSolution && status.isExpiring) {
		const ms = status.solution?.solve_time_ms ?? '?';
		return {
			text: `Expiring soon (solved in ${ms}ms)`,
			color: '#ffb900',
		};
	}

	if (status.hasSolution) {
		const ms = status.solution?.solve_time_ms ?? '?';
		const hashes = formatNumber(status.hashesChecked);
		const diff = status.difficulty ? ` d=${status.difficulty}` : '';
		return {
			text: `Solved in ${ms}ms, ${hashes} hashes${diff}`,
			color: '#46b450',
		};
	}

	if (status.isDeferredForPage) {
		return {
			text: 'Deferred (waiting for last page)',
			color: '#00a0d2',
		};
	}

	return {
		text: 'Idle (no solution)',
		color: 'var(--qm-info-fg, #999)',
	};
}

export function formatExpiry(status: PoWStatus | null): string {
	if (!status || !status.challengeExpires) {
		return '--';
	}

	const remaining = status.challengeExpires - Date.now() / 1000;
	if (remaining <= 0) {
		return 'EXPIRED (will re-solve on submit)';
	}

	const mins = Math.floor(remaining / 60);
	const secs = Math.floor(remaining % 60);

	if (mins > 0) {
		return `${mins}m ${secs}s`;
	}
	return `${secs}s`;
}

export function formatChallengeType(status: PoWStatus | null): string {
	if (!status || status.status === 'no_manager') {
		return '';
	}

	if (!status.hasSolution && status.isSolving) {
		const diff = status.difficulty ? `, d=${status.difficulty}` : '';
		return status.isFallback
			? `Fallback, solving...${diff}`
			: `REST, solving...${diff}`;
	}

	if (status.isFallback) {
		const diff = status.difficulty ? `, d=${status.difficulty}` : '';
		return `Fallback (max difficulty${diff})`;
	}

	if (status.hasSolution) {
		const diff = status.difficulty ? `, d=${status.difficulty}` : '';
		return `REST (adaptive${diff})`;
	}

	return '';
}

/**
 * Formats a number with thousands separators (e.g., 1,234,567).
 */
export function formatNumber(n: number): string {
	if (n === 0) {
		return '0';
	}
	const parts: string[] = [];
	let remaining = n;
	while (remaining > 0) {
		const chunk = remaining % 1000;
		remaining = Math.floor(remaining / 1000);
		if (remaining > 0) {
			parts.unshift(('00' + chunk).slice(-3));
		} else {
			parts.unshift(String(chunk));
		}
	}
	return parts.join(',');
}

/**
 * Formats milliseconds into a human-readable duration.
 */
export function formatDuration(ms: number): string {
	if (ms < 1000) {
		return `${Math.round(ms)}ms`;
	}
	const secs = ms / 1000;
	if (secs < 60) {
		return `${secs.toFixed(1)}s`;
	}
	const mins = Math.floor(secs / 60);
	const remainSecs = Math.floor(secs % 60);
	return `${mins}m ${remainSecs}s`;
}

export function getLogLevelColor(level: LogLevel): string {
	switch (level) {
		case 'error':
			return '#dc3232';
		case 'warn':
			return '#ffb900';
		case 'info':
			return '#00a0d2';
		default:
			return 'var(--qm-container-fg, #333)';
	}
}

export function escapeHtml(str: string): string {
	return str
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;');
}
