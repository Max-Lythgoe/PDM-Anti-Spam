/**
 * AI Stats Card Component
 *
 * AI classification performance card with headline stats (calls + latency),
 * a ham/spam classification summary, and a collapsible details section
 * for cost and per-reason breakdown.
 *
 * Used by both the plugin-settings and comment-settings dashboards.
 */

import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useUI } from '../context/UIContext';
import type {
	AiData,
	ReasonData,
	SignalData,
} from '../../plugin-settings/types/dashboard';
import { formatMs, formatCost } from '../../plugin-settings/utils/formatters';
import './AiStatsCard.css';

interface AiStatsCardProps {
	ai: AiData;
	reasons?: ReasonData[];
	signals?: SignalData[];
}

export const AiStatsCard = ({ ai, reasons, signals }: AiStatsCardProps) => {
	const { Card } = useUI();
	const [detailsOpen, setDetailsOpen] = useState(false);

	// Ham/spam counts come pre-computed from the backend.
	const hamCount = ai.classified_ham ?? 0;
	const spamCount = ai.classified_spam ?? 0;
	const hasClassification = hamCount > 0 || spamCount > 0;

	const hasReasons = reasons && reasons.length > 0;
	const hasSignals = signals && signals.length > 0;
	const hasDetails = hasReasons || hasSignals || ai.total_ai_calls > 0;

	return (
		<Card title={__('AI Classification', 'gf-spam-hexer')}>
			{ai.total_ai_calls === 0 ? (
				<p className="gfsh-detail-card__empty">
					{__('No AI classification data yet.', 'gf-spam-hexer')}
				</p>
			) : (
				<>
					{/* Headline stats */}
					<div className="gfsh-detail-card__stats">
						<div className="gfsh-detail-card__stat">
							<span className="gfsh-detail-card__stat-value">
								{ai.total_ai_calls.toLocaleString()}
							</span>
							<span className="gfsh-detail-card__stat-label">
								{__('AI Calls', 'gf-spam-hexer')}
							</span>
						</div>
						<div className="gfsh-detail-card__stat">
							<span className="gfsh-detail-card__stat-value">
								{formatMs(ai.avg_latency_ms, 2)}
							</span>
							<span className="gfsh-detail-card__stat-label">
								{__('Avg Latency', 'gf-spam-hexer')}
							</span>
						</div>
					</div>

					{/* Ham/Spam summary — always visible when classification data exists */}
					{hasClassification && (
						<div className="gfsh-detail-card__classification">
							<div className="gfsh-detail-card__row">
								<span className="gfsh-detail-card__row-label">
									{__('Classified Ham', 'gf-spam-hexer')}
								</span>
								<span className="gfsh-detail-card__row-value gfsh-detail-card__row-value--clean">
									{hamCount.toLocaleString()}
								</span>
							</div>
							<div className="gfsh-detail-card__row">
								<span className="gfsh-detail-card__row-label">
									{__('Classified Spam', 'gf-spam-hexer')}
								</span>
								<span className="gfsh-detail-card__row-value gfsh-detail-card__row-value--spam">
									{spamCount.toLocaleString()}
								</span>
							</div>
						</div>
					)}

					{/* Collapsible details */}
					{hasDetails && (
						<details
							className="gfsh-detail-card__details"
							open={detailsOpen}
							onToggle={(e) =>
								setDetailsOpen(
									(e.target as HTMLDetailsElement).open
								)
							}
						>
							<summary className="gfsh-detail-card__details-toggle">
								{__('Details', 'gf-spam-hexer')}
							</summary>

							<div className="gfsh-detail-card__row">
								<span className="gfsh-detail-card__row-label">
									{__('Total Cost', 'gf-spam-hexer')}
								</span>
								<span className="gfsh-detail-card__row-value">
									{formatCost(ai.total_cost)}
								</span>
							</div>

							{hasReasons && (
								<div className="gfsh-detail-card__section">
									<p className="gfsh-detail-card__section-title">
										{__('Reason Codes', 'gf-spam-hexer')}
									</p>
									{reasons.map((r) => (
										<div
											key={r.reason}
											className="gfsh-detail-card__row"
										>
											<span className="gfsh-detail-card__row-label">
												{r.reason}
											</span>
											<span className="gfsh-detail-card__row-value">
												{r.count.toLocaleString()}
											</span>
										</div>
									))}
								</div>
							)}

							{hasSignals && (
								<div className="gfsh-detail-card__section">
									<p className="gfsh-detail-card__section-title">
										{__('Skipped', 'gf-spam-hexer')}
									</p>
									{signals.map((s) => (
										<div
											key={s.signal}
											className="gfsh-detail-card__row"
										>
											<span className="gfsh-detail-card__row-label">
												{s.signal}
											</span>
											<span className="gfsh-detail-card__row-value gfsh-detail-card__row-value--warn">
												{s.count.toLocaleString()}
											</span>
										</div>
									))}
								</div>
							)}
						</details>
					)}
				</>
			)}
		</Card>
	);
};
