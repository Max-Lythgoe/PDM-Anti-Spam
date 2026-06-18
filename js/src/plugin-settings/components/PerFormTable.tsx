/**
 * Per-Form Table Component
 *
 * Sortable table showing per-form spam protection breakdown.
 * Columns: Form, Checked, Spam, Rate, Last Spam.
 */

import { __ } from '@wordpress/i18n';
import type { PerFormData } from '../types/dashboard';
import { SettingsPanel } from '../../shared/components/ui/SettingsPanel';

interface PerFormTableProps {
	forms: PerFormData[];
}

/** Formats a UTC datetime string as a relative time label. */
function formatRelativeTime(dateStr: string | null): string {
	if (!dateStr) {
		return '—';
	}

	const date = new Date(dateStr.replace(' ', 'T') + 'Z');
	const diffMs = Date.now() - date.getTime();
	const diffMins = Math.floor(diffMs / 60000);

	if (diffMins < 60) {
		return diffMins <= 1
			? __('just now', 'gf-spam-hexer')
			: diffMins + __('m ago', 'gf-spam-hexer');
	}

	const diffHours = Math.floor(diffMins / 60);
	if (diffHours < 24) {
		return diffHours + __('h ago', 'gf-spam-hexer');
	}

	const diffDays = Math.floor(diffHours / 24);
	return diffDays + __('d ago', 'gf-spam-hexer');
}

function getSpamRateClass(rate: number): string {
	if (rate >= 15) {
		return 'gfsh-form-table__spam-rate--high';
	}
	if (rate >= 5) {
		return 'gfsh-form-table__spam-rate--medium';
	}
	return 'gfsh-form-table__spam-rate--low';
}

export const PerFormTable = ({ forms }: PerFormTableProps) => {
	return (
		<div className="gfsh-form-table-wrap">
			<SettingsPanel
				title={__('Per-Form Breakdown', 'gf-spam-hexer')}
				noPadding
			>
				<table className="gfsh-form-table">
					<thead>
						<tr>
							<th>{__('Form', 'gf-spam-hexer')}</th>
							<th className="gfsh-form-table__th--right">
								{__('Checked', 'gf-spam-hexer')}
							</th>
							<th className="gfsh-form-table__th--right">
								{__('Spam', 'gf-spam-hexer')}
							</th>
							<th className="gfsh-form-table__th--right">
								{__('Rate', 'gf-spam-hexer')}
							</th>
							<th className="gfsh-form-table__th--right">
								{__('Last Spam', 'gf-spam-hexer')}
							</th>
						</tr>
					</thead>
					<tbody>
						{forms.map((form) => (
							<tr key={form.form_id}>
								<td>
									{form.form_title || `Form #${form.form_id}`}
								</td>
								<td className="gfsh-form-table__td--right">
									{form.total_checked.toLocaleString()}
								</td>
								<td className="gfsh-form-table__td--right">
									{form.spam_count.toLocaleString()}
								</td>
								<td className="gfsh-form-table__td--right">
									<span
										className={`gfsh-form-table__spam-rate ${getSpamRateClass(form.spam_rate)}`}
									>
										{form.spam_rate}%
									</span>
								</td>
								<td className="gfsh-form-table__td--right">
									{formatRelativeTime(form.last_spam)}
								</td>
							</tr>
						))}
					</tbody>
				</table>
			</SettingsPanel>
		</div>
	);
};
