/**
 * PoW Stats Card Component
 *
 * Proof of Work performance card with headline stats (solves + avg time),
 * a passed/failed summary, and a collapsible details section for
 * min/max, difficulty distribution, challenge source, and failure signals.
 *
 * Used by both the plugin-settings and comment-settings dashboards.
 */

import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useUI } from '../context/UIContext';
import type {
	PowData,
	SignalData,
} from '../../plugin-settings/types/dashboard';
import { formatMs } from '../../plugin-settings/utils/formatters';
import './PowStatsCard.css';

interface PowStatsCardProps {
	pow: PowData;
	signals?: SignalData[];
}

export const PowStatsCard = ({ pow, signals }: PowStatsCardProps) => {
	const { Card } = useUI();
	const [detailsOpen, setDetailsOpen] = useState(false);

	const failedCount = signals
		? signals.reduce((sum, s) => sum + s.count, 0)
		: 0;

	const totalFallback = pow.fallback_count + pow.rest_count;
	const restPct =
		totalFallback > 0
			? Math.round((pow.rest_count / totalFallback) * 100)
			: 0;
	const fallbackPct = totalFallback > 0 ? 100 - restPct : 0;

	const maxCount = pow.difficulty_distribution.reduce(
		(max, d) => Math.max(max, d.count),
		1
	);

	const hasSignals = signals && signals.length > 0;
	const hasDetails =
		pow.difficulty_distribution.length > 0 ||
		totalFallback > 0 ||
		hasSignals;

	return (
		<Card title={__('Proof of Work', 'gf-spam-hexer')}>
			{pow.total_solves === 0 && failedCount === 0 ? (
				<p className="gfsh-detail-card__empty">
					{__('No solve data yet.', 'gf-spam-hexer')}
				</p>
			) : (
				<>
					{/* Headline stats */}
					<div className="gfsh-detail-card__stats">
						<div className="gfsh-detail-card__stat">
							<span className="gfsh-detail-card__stat-value">
								{pow.total_solves.toLocaleString()}
							</span>
							<span className="gfsh-detail-card__stat-label">
								{__('Solves', 'gf-spam-hexer')}
							</span>
						</div>
						<div className="gfsh-detail-card__stat">
							<span className="gfsh-detail-card__stat-value">
								{formatMs(pow.avg_solve_ms)}
							</span>
							<span className="gfsh-detail-card__stat-label">
								{__('Avg Time', 'gf-spam-hexer')}
							</span>
						</div>
					</div>

					{/* Passed/Failed summary — always visible */}
					<div className="gfsh-detail-card__classification">
						<div className="gfsh-detail-card__row">
							<span className="gfsh-detail-card__row-label">
								{__('Passed', 'gf-spam-hexer')}
							</span>
							<span className="gfsh-detail-card__row-value gfsh-detail-card__row-value--clean">
								{pow.total_solves.toLocaleString()}
							</span>
						</div>
						<div className="gfsh-detail-card__row">
							<span className="gfsh-detail-card__row-label">
								{__('Failed', 'gf-spam-hexer')}
							</span>
							<span className="gfsh-detail-card__row-value gfsh-detail-card__row-value--spam">
								{failedCount.toLocaleString()}
							</span>
						</div>
					</div>

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
									{__('Min / Max', 'gf-spam-hexer')}
								</span>
								<span className="gfsh-detail-card__row-value">
									{formatMs(pow.min_solve_ms)} /{' '}
									{formatMs(pow.max_solve_ms)}
								</span>
							</div>

							{pow.difficulty_distribution.length > 0 && (
								<div className="gfsh-detail-card__section">
									<p className="gfsh-detail-card__section-title">
										{__(
											'Difficulty Distribution',
											'gf-spam-hexer'
										)}
									</p>
									<div className="gfsh-difficulty-bars">
										{pow.difficulty_distribution.map(
											(d) => (
												<div
													key={d.difficulty}
													className="gfsh-difficulty-bar"
												>
													<span className="gfsh-difficulty-bar__label">
														{d.difficulty}b
													</span>
													<div className="gfsh-difficulty-bar__track">
														<div
															className="gfsh-difficulty-bar__fill"
															style={{
																width: `${Math.round((d.count / maxCount) * 100)}%`,
															}}
														/>
													</div>
													<span className="gfsh-difficulty-bar__count">
														{d.count.toLocaleString()}
													</span>
												</div>
											)
										)}
									</div>
								</div>
							)}

							{totalFallback > 0 && (
								<div className="gfsh-detail-card__section">
									<p className="gfsh-detail-card__section-title">
										{__(
											'Challenge Source',
											'gf-spam-hexer'
										)}
									</p>
									<div className="gfsh-detail-card__row">
										<span className="gfsh-detail-card__row-label">
											{__('REST API', 'gf-spam-hexer')}
										</span>
										<span className="gfsh-detail-card__row-value">
											{restPct}%
										</span>
									</div>
									<div className="gfsh-detail-card__row">
										<span className="gfsh-detail-card__row-label">
											{__(
												'Fallback (inline)',
												'gf-spam-hexer'
											)}
										</span>
										<span className="gfsh-detail-card__row-value">
											{fallbackPct}%
										</span>
									</div>
								</div>
							)}

							{hasSignals && (
								<div className="gfsh-detail-card__section">
									<p className="gfsh-detail-card__section-title">
										{__('Failure Signals', 'gf-spam-hexer')}
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
