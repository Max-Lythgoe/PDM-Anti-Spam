/**
 * AI Classification — "How it works" Modal Content
 *
 * Educational content shown in the info modal when the user clicks
 * "How it works" on the AI technique card.
 */

import { __ } from '@wordpress/i18n';

interface AiInfoContentProps {
	/** Context determines the wording — 'form' (default) or 'comment' */
	context?: 'form' | 'comment';
	/** Full URL to the plugin settings AI Provider tab */
	pluginSettingsUrl?: string;
}

export const AiInfoContent = ({
	context = 'form',
	pluginSettingsUrl,
}: AiInfoContentProps) => {
	const isComment = context === 'comment';
	const aiTabUrl = pluginSettingsUrl || '#ai';

	return (
		<>
			<div className="gfsh-info-modal__section">
				<p>
					{isComment
						? __(
								"After a comment is submitted, the content is sent to an AI model that reads what was written and classifies it as legitimate or spam. It catches SEO spam, phishing links, gibberish, and off-topic content that PoW alone can't detect — bots can solve puzzles, they just can't write convincing human content.",
								'gf-spam-hexer'
							)
						: __(
								"After a form is submitted, the content is sent to an AI model that reads what was written and classifies it as legitimate or spam. It catches SEO spam, phishing links, gibberish, and off-topic content that PoW alone can't detect — bots can solve puzzles, they just can't write convincing human content.",
								'gf-spam-hexer'
							)}
				</p>
				<p>
					{__(
						'Submissions are evaluated only if they passed earlier checks. Configure your AI provider in the',
						'gf-spam-hexer'
					)}{' '}
					<a href={aiTabUrl}>
						{__('AI Provider tab', 'gf-spam-hexer')}
					</a>
					{'.'}
				</p>
			</div>
		</>
	);
};
