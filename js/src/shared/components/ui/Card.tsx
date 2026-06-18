/**
 * Card Component — GF Theme Implementation
 *
 * A content card with an optional title header and body area.
 * In the GF context, renders the existing `gfsh-detail-card` markup
 * so stat cards look consistent with the GF admin panel chrome.
 *
 * For cards without a title (e.g. SummaryCards), wraps with SettingsBox
 * (gform-settings-panel--no-padding) to get GF's border/shadow treatment.
 *
 * @example
 * ```tsx
 * // With title (AiStatsCard, PowStatsCard)
 * <Card title="AI Classification">
 *   <p>Stats content...</p>
 * </Card>
 *
 * // Without title (SummaryCards)
 * <Card className="gfsh-summary-card">
 *   <div className="gfsh-summary-card__value">1,234</div>
 * </Card>
 * ```
 */

import type { ReactNode } from 'react';
import { SettingsPanel } from './SettingsPanel';

export interface CardProps {
	/** Optional card title rendered as a GF legend header */
	title?: string;
	/** Card body content */
	children: ReactNode;
	/** Additional CSS class on the outer container */
	className?: string;
}

export const Card = ({ title, children, className = '' }: CardProps) => {
	if (title) {
		return (
			<SettingsPanel
				title={title}
				className={`gfsh-detail-card ${className}`.trim()}
			>
				{children}
			</SettingsPanel>
		);
	}

	// No title: use SettingsBox-equivalent markup (GF panel chrome, no padding)
	const classes = [
		'gform-settings-panel',
		'gform-settings-panel--no-padding',
		className,
	]
		.filter(Boolean)
		.join(' ');

	return <div className={classes}>{children}</div>;
};
