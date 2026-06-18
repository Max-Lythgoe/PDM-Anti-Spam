/**
 * Dashboard Stats Container Component
 *
 * Main dashboard component rendered above technique settings on the
 * plugin settings page. Reads PHP-injected stats from the window object
 * and renders summary cards, detail cards, per-form table, and notices.
 */

import { __, sprintf } from '@wordpress/i18n';
import type { DashboardStatsData } from '../types/dashboard';
import { SummaryCards } from '../../shared/components/SummaryCards';
import { PowStatsCard } from '../../shared/components/PowStatsCard';
import { AiStatsCard } from '../../shared/components/AiStatsCard';
import { PerFormTable } from './PerFormTable';

export const DashboardStats = () => {
	const stats: DashboardStatsData | undefined =
		window.gf_spam_hexer_plugin_settings_strings?.dashboardStats;

	if (!stats || !stats.summary) {
		return null;
	}

	if (stats.summary.total_checked === 0) {
		return (
			<div className="gfsh-dashboard gfsh-dashboard--empty">
				<p>
					{__(
						'No submissions checked yet. Stats will appear here once forms start receiving submissions.',
						'gf-spam-hexer'
					)}
				</p>
			</div>
		);
	}

	return (
		<div className="gfsh-dashboard">
			<div className="gfsh-dashboard__header">
				<h3>{__('Protection Overview', 'gf-spam-hexer')}</h3>
				<span className="gfsh-dashboard__period">
					{sprintf(
						/* translators: %d: number of days */
						__('Last %d days', 'gf-spam-hexer'),
						stats.period_days
					)}
				</span>
			</div>

			<SummaryCards summary={stats.summary} />

			<div className="gfsh-dashboard__details">
				<PowStatsCard pow={stats.pow} signals={stats.pow_signals} />
				{stats.ai && (
					<AiStatsCard
						ai={stats.ai}
						reasons={stats.ai_reasons}
						signals={stats.ai_signals}
					/>
				)}
			</div>

			{stats.per_form.length > 0 && (
				<PerFormTable forms={stats.per_form} />
			)}
		</div>
	);
};
