/**
 * Bypass Rules Card
 *
 * Renders the "Bypass for Logged-In Users" settings panel.
 * Used on both the plugin settings page and comment settings page.
 * Uses SettingsPanel for GF-native panel chrome.
 */

import { __ } from '@wordpress/i18n';
import { useUI } from '../context/UIContext';
import BypassIcon from './icons/svg/bypass.svg';

interface BypassRulesCardProps {
	bypassLoggedIn: string; // '1' | '0'
	onChange: (checked: boolean) => void;
}

export const BypassRulesCard = ({
	bypassLoggedIn,
	onChange,
}: BypassRulesCardProps) => {
	const { SettingsPanel, SwitchToggle } = useUI();

	return (
		<SettingsPanel
			header={
				<div className="gfsh-technique-card__header">
					<span
						className="gfsh-technique-card__icon"
						aria-hidden="true"
					>
						<BypassIcon className="gfsh-technique-icon" />
					</span>
					<div className="gfsh-technique-card__info">
						<div className="gfsh-technique-card__name">
							{__('Bypass Rules', 'gf-spam-hexer')}
						</div>
						<div className="gfsh-technique-card__description">
							{__(
								'Skip spam checks for trusted users.',
								'gf-spam-hexer'
							)}
						</div>
					</div>
				</div>
			}
			className="gfsh-technique-card"
		>
			<SwitchToggle
				label={__('Bypass for Logged-In Users', 'gf-spam-hexer')}
				checked={bypassLoggedIn === '1'}
				onChange={onChange}
				tooltip={__(
					'When enabled, authenticated WordPress users skip all spam checks including Proof of Work and AI classification.',
					'gf-spam-hexer'
				)}
			/>
		</SettingsPanel>
	);
};
