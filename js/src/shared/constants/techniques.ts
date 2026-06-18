/**
 * Technique Metadata Constants
 *
 * Shared technique display metadata used by both form-settings
 * (TechniqueOverrideSettings) and plugin-settings (TechniqueSettings).
 */

import type { ReactNode } from 'react';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ProofOfWorkIcon from '../components/icons/svg/proof-of-work.svg';
import AiClassify from '../components/icons/svg/ai-classify.svg';

export interface TechniqueInfo {
	icon: ReactNode;
	name: string;
	description: string;
}

export const TECHNIQUES: Record<string, TechniqueInfo> = {
	pow: {
		icon: createElement(ProofOfWorkIcon, {
			className: 'gfsh-technique-icon',
		}),
		name: __('Proof of Work', 'gf-spam-hexer'),
		description: __(
			'Requires browsers to solve a computational puzzle before submitting. Invisible to users, expensive for bots.',
			'gf-spam-hexer'
		),
	},
	ai: {
		icon: createElement(AiClassify, { className: 'gfsh-technique-icon' }),
		name: __('AI Classification', 'gf-spam-hexer'),
		description: __(
			'Reads what was submitted and detects spam: SEO spam, phishing, gibberish, off-topic content.',
			'gf-spam-hexer'
		),
	},
};
