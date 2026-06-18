/**
 * Proof of Work — "How it works" Modal Content
 *
 * Educational content shown in the info modal when the user clicks
 * "How it works" on the PoW technique card.
 */

import { __ } from '@wordpress/i18n';

export const PowInfoContent = () => {
	return (
		<div className="gfsh-info-modal__section">
			<p>
				{__(
					'When someone loads your form, their browser silently solves a SHA-256 puzzle in the background — like a CAPTCHA, but invisible. Legitimate visitors never notice it. Bots have to burn real CPU time on every submission attempt.',
					'gf-spam-hexer'
				)}
			</p>
			<p>
				{__(
					'The puzzle difficulty is set by your protection level. Higher levels require more computation, making automated spam progressively more expensive while remaining imperceptible to real users.',
					'gf-spam-hexer'
				)}
			</p>
		</div>
	);
};
