/**
 * Event log — subscribes to logger output and renders the event stream.
 *
 * @module dev-tools/event-log
 */

import { subscribeToLogs } from '@shared/logger';
import type { LogLevel } from '@shared/logger';
import { getLogLevelColor, escapeHtml } from './formatters';

/** A single log entry for the event stream. */
interface LogEntry {
	time: string;
	level: LogLevel;
	prefix: string;
	message: string;
}

/** Maximum log entries to keep in the event stream. */
const MAX_LOG_ENTRIES = 30;

/** Collected log entries from the logger subscriber. */
const logEntries: LogEntry[] = [];

/** Scopes we care about for the event stream. */
const RELEVANT_SCOPES = ['Collector', 'PoWManager'];

/**
 * Subscribe to logger output and collect relevant entries.
 */
export function initLogSubscriber(): void {
	subscribeToLogs((level: LogLevel, prefix: string, args: unknown[]) => {
		// Only capture Collector and PoWManager logs.
		const isRelevant = RELEVANT_SCOPES.some((scope) =>
			prefix.includes(`[${scope}]`)
		);
		if (!isRelevant) {
			return;
		}

		const message = args
			.map((a) => {
				if (typeof a === 'string') {
					return a;
				}
				if (typeof a === 'number' || typeof a === 'boolean') {
					return String(a);
				}
				try {
					return JSON.stringify(a);
				} catch {
					return String(a);
				}
			})
			.join(' ');

		const now = new Date();
		const hh = ('0' + now.getHours()).slice(-2);
		const mm = ('0' + now.getMinutes()).slice(-2);
		const ss = ('0' + now.getSeconds()).slice(-2);
		const time = `${hh}:${mm}:${ss}`;

		logEntries.push({ time, level, prefix, message });

		// Trim to max size.
		while (logEntries.length > MAX_LOG_ENTRIES) {
			logEntries.shift();
		}
	});
}

/**
 * Updates the event log container for a form.
 */
export function updateEventLog(shadowRoot: ShadowRoot, formId: number): void {
	const container = shadowRoot.querySelector<HTMLElement>(
		`[data-gfsh-event-log="${formId}"]`
	);
	if (!container) {
		return;
	}

	const relevantLogs = logEntries.slice(-8);
	if (relevantLogs.length === 0) {
		container.style.display = 'none';
		return;
	}

	container.style.display = '';

	let html =
		'<div style="font-weight:600;font-size:10px;color:var(--qm-info-fg, #666);margin-bottom:2px;">Event Log</div>';
	html +=
		'<div style="font-family:monospace;font-size:10px;line-height:1.5;max-height:120px;overflow-y:auto;background:var(--qm-panel-bg, #f8f8f8);border:1px solid var(--qm-cell-border, #eee);border-radius:3px;padding:4px 6px;">';

	for (const entry of relevantLogs) {
		const levelColor = getLogLevelColor(entry.level);
		// Strip the [GF Spam Hexer] prefix, keep the scope.
		const scope = entry.prefix.replace('[GF Spam Hexer] ', '');
		html += `<div style="color:${levelColor};"><span style="color:var(--qm-info-fg, #999);">${entry.time}</span> ${scope} ${escapeHtml(entry.message)}</div>`;
	}

	html += '</div>';
	container.innerHTML = html;
}
