/**
 * GF Spam Hexer Logger Utility
 *
 * Centralized logging with debug flag support and console groups.
 *
 * Debug mode can be enabled via:
 * - Query parameter: ?gp_debug or ?gp_debug=1
 * - URL hash: #gp-debug
 * - Webpack dev mode (process.env.NODE_ENV === 'development')
 *
 * Usage:
 *   import { createLogger } from '@shared/logger';
 *   const logger = createLogger('ModuleName');
 *   logger.log('message', data);
 *   logger.group('Group Name', () => {
 *     logger.log('inside group');
 *   });
 */

export type LogLevel = 'log' | 'warn' | 'error' | 'info';

/** Callback shape for log subscribers. */
export type LogSubscriber = (
	level: LogLevel,
	prefix: string,
	args: unknown[]
) => void;

/** Registered log subscribers. */
const subscribers: LogSubscriber[] = [];

/**
 * Subscribe to all log output across all logger instances.
 *
 * Subscribers receive every log call (regardless of debug mode) so the
 * dev-tools live panel can display events even in production builds.
 *
 * @param fn - Callback invoked on each log call.
 * @return An unsubscribe function.
 */
export function subscribeToLogs(fn: LogSubscriber): () => void {
	subscribers.push(fn);
	return () => {
		const idx = subscribers.indexOf(fn);
		if (idx !== -1) {
			subscribers.splice(idx, 1);
		}
	};
}

interface Logger {
	/** Log a message (only in debug mode) */
	log: (...args: unknown[]) => void;
	/** Log a warning (only in debug mode) */
	warn: (...args: unknown[]) => void;
	/** Log an error (always shown) */
	error: (...args: unknown[]) => void;
	/** Log info (only in debug mode) */
	info: (...args: unknown[]) => void;
	/** Create a collapsed console group (only in debug mode) */
	group: (label: string, fn: () => void) => void;
	/** Create an expanded console group (only in debug mode) */
	groupExpanded: (label: string, fn: () => void) => void;
	/** Log a table (only in debug mode) */
	table: (data: unknown) => void;
	/** Log with timing info (only in debug mode) */
	time: (label: string) => void;
	timeEnd: (label: string) => void;
}

/** Cache the debug state to avoid repeated checks */
let debugEnabled: boolean | null = null;

/**
 * Check if debug mode is enabled.
 * Caches the result for performance.
 */
export function isDebugEnabled(): boolean {
	if (debugEnabled !== null) {
		return debugEnabled;
	}

	debugEnabled = checkDebugFlags();
	return debugEnabled;
}

/**
 * Check all debug flag sources
 */
function checkDebugFlags(): boolean {
	// 1. Check webpack mode (set at build time)
	if (process.env.NODE_ENV === 'development') {
		return true;
	}

	// 2. Check query parameter: ?gp_debug or ?gp_debug=1
	if (typeof window !== 'undefined' && window.location) {
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.has('gp_debug')) {
			const value = urlParams.get('gp_debug');
			// ?gp_debug (no value) or ?gp_debug=1 or ?gp_debug=true
			if (
				value === null ||
				value === '' ||
				value === '1' ||
				value === 'true'
			) {
				return true;
			}
		}

		// 3. Check URL hash: #gp-debug
		if (window.location.hash.includes('gp-debug')) {
			return true;
		}
	}

	return false;
}

/**
 * Force re-check of debug flags.
 * Useful if you change localStorage or URL after page load.
 */
export function refreshDebugState(): void {
	debugEnabled = null;
	isDebugEnabled();
}

/**
 * Manually enable or disable debug mode.
 * Useful for programmatic control.
 */
export function setDebugEnabled(enabled: boolean): void {
	debugEnabled = enabled;
}

/**
 * Create a scoped logger for a specific module.
 *
 * @param scope - The module name (e.g., 'Popup', 'PopupManager', 'ButtonTrigger')
 * @return A logger instance with scoped methods
 *
 * @example
 * const logger = createLogger('Popup');
 * logger.log('show() called', { feedId: 123 });
 * // Output: [GF Spam Hexer] [Popup] show() called { feedId: 123 }
 */
export function createLogger(scope: string): Logger {
	const prefix = `[GF Spam Hexer] [${scope}]`;

	const logWithLevel = (level: LogLevel, ...args: unknown[]): void => {
		// Notify subscribers unconditionally so the live panel works
		// even in production builds where debug mode is off.
		for (const fn of subscribers) {
			try {
				fn(level, prefix, args);
			} catch {
				// Subscriber errors must never break logging.
			}
		}

		if (level === 'error' || isDebugEnabled()) {
			// eslint-disable-next-line no-console
			console[level](prefix, ...args);
		}
	};

	return {
		log: (...args: unknown[]) => logWithLevel('log', ...args),
		warn: (...args: unknown[]) => logWithLevel('warn', ...args),
		error: (...args: unknown[]) => logWithLevel('error', ...args),
		info: (...args: unknown[]) => logWithLevel('info', ...args),

		/**
		 * Create a collapsed console group.
		 * The callback is ALWAYS executed - only the console grouping is conditional.
		 */
		group: (label: string, fn: () => void): void => {
			if (isDebugEnabled()) {
				// eslint-disable-next-line no-console
				console.groupCollapsed(`${prefix} ${label}`);
			}

			try {
				fn();
			} finally {
				if (isDebugEnabled()) {
					// eslint-disable-next-line no-console
					console.groupEnd();
				}
			}
		},

		/**
		 * Create an expanded console group.
		 * The callback is ALWAYS executed - only the console grouping is conditional.
		 */
		groupExpanded: (label: string, fn: () => void): void => {
			if (isDebugEnabled()) {
				// eslint-disable-next-line no-console
				console.group(`${prefix} ${label}`);
			}

			try {
				fn();
			} finally {
				if (isDebugEnabled()) {
					// eslint-disable-next-line no-console
					console.groupEnd();
				}
			}
		},

		/**
		 * Log tabular data
		 */
		table: (data: unknown): void => {
			if (!isDebugEnabled()) {
				return;
			}
			// eslint-disable-next-line no-console
			console.log(prefix);
			// eslint-disable-next-line no-console
			console.table(data);
		},

		/**
		 * Start a timer
		 */
		time: (label: string): void => {
			if (!isDebugEnabled()) {
				return;
			}
			// eslint-disable-next-line no-console
			console.time(`${prefix} ${label}`);
		},

		/**
		 * End a timer and log the duration
		 */
		timeEnd: (label: string): void => {
			if (!isDebugEnabled()) {
				return;
			}
			// eslint-disable-next-line no-console
			console.timeEnd(`${prefix} ${label}`);
		},
	};
}

/**
 * Default logger without a specific scope.
 * Use createLogger() for module-specific logging.
 */
export const logger = createLogger('Core');
