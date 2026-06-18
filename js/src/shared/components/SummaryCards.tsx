/**
 * Summary Cards Component
 *
 * Three metric cards in a row showing top-level protection stats:
 * Submissions Checked, Spam Blocked, Clean Allowed.
 *
 * Uses SettingsBox for GF-native panel chrome on each card.
 * Used by both the plugin-settings and comment-settings dashboards.
 */

import { __ } from '@wordpress/i18n';
import type { SummaryData } from '../../plugin-settings/types/dashboard';
import { formatNumber } from '../../plugin-settings/utils/formatters';
import { useUI } from '../context/UIContext';
import './SummaryCards.css';

interface SummaryCardsProps {
	summary: SummaryData;
}

export const SummaryCards = ({ summary }: SummaryCardsProps) => {
	const { Card } = useUI();
	const total = summary.total_checked;
	const spamPct =
		total > 0 ? ((summary.spam_count / total) * 100).toFixed(1) : '0';
	const cleanPct =
		total > 0 ? ((summary.clean_count / total) * 100).toFixed(1) : '0';

	return (
		<div className="gfsh-summary-cards">
			<Card className="gfsh-summary-card">
				<div className="gfsh-summary-card__value">
					{formatNumber(summary.total_checked)}
				</div>
				<div className="gfsh-summary-card__label">
					{__('Checked', 'gf-spam-hexer')}
				</div>
			</Card>

			<Card className="gfsh-summary-card gfsh-summary-card--spam">
				<div className="gfsh-summary-card__value">
					{formatNumber(summary.spam_count)}
				</div>
				<div className="gfsh-summary-card__label">
					{__('Spam Blocked', 'gf-spam-hexer')}
				</div>
				<div className="gfsh-summary-card__detail">
					{spamPct}
					{__('% of submissions', 'gf-spam-hexer')}
				</div>
			</Card>

			<Card className="gfsh-summary-card gfsh-summary-card--clean">
				<div className="gfsh-summary-card__value">
					{formatNumber(summary.clean_count)}
				</div>
				<div className="gfsh-summary-card__label">
					{__('Clean Allowed', 'gf-spam-hexer')}
				</div>
				<div className="gfsh-summary-card__detail">
					{cleanPct}
					{__('% of submissions', 'gf-spam-hexer')}
				</div>
			</Card>
		</div>
	);
};
