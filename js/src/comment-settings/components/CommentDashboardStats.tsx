/**
 * Comment Dashboard Stats Component
 *
 * Displays comment spam protection statistics on the comment settings page.
 * Reads PHP-injected stats from the window object (gfsh_comment_settings.commentStats).
 *
 * Mirrors the form entries DashboardStats layout: summary cards + technique
 * detail cards (PowStatsCard, AiStatsCard).
 */

import { __, sprintf } from '@wordpress/i18n';
import { useUI } from '../../shared/context/UIContext';
import { PowStatsCard } from '../../shared/components/PowStatsCard';
import { AiStatsCard } from '../../shared/components/AiStatsCard';
import '../../shared/components/DashboardStats.css';
import '../../shared/components/SummaryCards.css';

export const CommentDashboardStats = () => {
	const { Card } = useUI();
	const stats = window.gfsh_comment_settings?.commentStats;

	if (!stats || !stats.comments) {
		return (
			<div className="gfsh-dashboard gfsh-dashboard--empty">
				<p>
					{__(
						'No comments checked yet. Stats will appear here once comments start being processed.',
						'gf-spam-hexer'
					)}
				</p>
			</div>
		);
	}

	const { comments } = stats;
	const periodDays = stats.period_days;
	const spamRate =
		comments.total_checked > 0
			? Math.round((comments.spam_count / comments.total_checked) * 100)
			: 0;

	return (
		<div className="gfsh-dashboard">
			<div className="gfsh-dashboard__header">
				<h3>{__('Comment Protection Overview', 'gf-spam-hexer')}</h3>
				<span className="gfsh-dashboard__period">
					{sprintf(
						/* translators: %d: number of days */
						__('Last %d days', 'gf-spam-hexer'),
						periodDays
					)}
				</span>
			</div>

			<div className="gfsh-summary-cards gfsh-summary-cards--4col">
				<Card className="gfsh-summary-card">
					<div className="gfsh-summary-card__value">
						{comments.total_checked.toLocaleString()}
					</div>
					<div className="gfsh-summary-card__label">
						{__('Comments Checked', 'gf-spam-hexer')}
					</div>
				</Card>
				<Card className="gfsh-summary-card gfsh-summary-card--spam">
					<div className="gfsh-summary-card__value">
						{comments.spam_count.toLocaleString()}
					</div>
					<div className="gfsh-summary-card__label">
						{__('Spam Caught', 'gf-spam-hexer')}
					</div>
				</Card>
				<Card className="gfsh-summary-card gfsh-summary-card--clean">
					<div className="gfsh-summary-card__value">
						{comments.clean_count.toLocaleString()}
					</div>
					<div className="gfsh-summary-card__label">
						{__('Clean Comments', 'gf-spam-hexer')}
					</div>
				</Card>
				<Card className="gfsh-summary-card">
					<div className="gfsh-summary-card__value">{spamRate}%</div>
					<div className="gfsh-summary-card__label">
						{__('Spam Rate', 'gf-spam-hexer')}
					</div>
				</Card>
			</div>

			<div className="gfsh-dashboard__details">
				<PowStatsCard
					pow={comments.pow}
					signals={comments.pow_signals}
				/>
				{comments.ai && (
					<AiStatsCard
						ai={comments.ai}
						reasons={comments.ai_reasons}
						signals={comments.ai_signals}
					/>
				)}
			</div>
		</div>
	);
};
