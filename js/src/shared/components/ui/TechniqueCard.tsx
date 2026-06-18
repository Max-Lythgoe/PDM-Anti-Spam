/**
 * Technique Card Component
 *
 * A settings panel with a custom header for technique configuration.
 * Uses SettingsPanel for the GF-native panel chrome (border, shadow, content area)
 * and only adds custom styling for the header layout (icon + toggle + chip).
 *
 * Each card is a self-contained unit with:
 * - Icon + name + one-line description
 * - "How it works" info link (opens modal)
 * - Toggle switch (on/off, not tri-state)
 * - "Custom" chip when any setting differs from global
 * - Configuration body (visible when enabled)
 *
 * @example
 * ```tsx
 * <TechniqueCard
 *   icon="⚡"
 *   name="Proof of Work"
 *   description="Computational puzzle that costs bots real CPU time"
 *   enabled={true}
 *   onToggle={(enabled) => setEnabled(enabled)}
 *   infoModalTitle="How Proof of Work works"
 *   infoModalContent={<PowInfoContent />}
 * >
 *   <SegmentedControl ... />
 * </TechniqueCard>
 * ```
 */

import { useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { useUI } from '../../context/UIContext';

import './TechniqueCard.css';

interface TechniqueCardProps {
	/** SVG icon component or any ReactNode for the technique */
	icon: ReactNode;
	/** Technique name */
	name: string;
	/** One-line description */
	description: string;
	/** Whether the technique is enabled */
	enabled: boolean;
	/** Toggle handler */
	onToggle: (enabled: boolean) => void;
	/** Configuration content (shown when enabled) */
	children?: ReactNode;
	/** Title for the info modal (if provided, shows "How it works" link) */
	infoModalTitle?: string;
	/** Content rendered inside the info modal */
	infoModalContent?: ReactNode;
	/** Plugin mode: no header toggle, body always visible */
	alwaysOpen?: boolean;
}

export const TechniqueCard = ({
	icon,
	name,
	description,
	enabled,
	onToggle,
	children,
	infoModalTitle,
	infoModalContent,
	alwaysOpen = false,
}: TechniqueCardProps) => {
	const { SwitchToggle, SettingsPanel, InfoModal } = useUI();
	const [infoModalOpen, setInfoModalOpen] = useState(false);

	const closeModal = () => setInfoModalOpen(false);

	const headerContent = (
		<div className="gfsh-technique-card__header">
			<span className="gfsh-technique-card__icon" aria-hidden="true">
				{icon}
			</span>
			<div className="gfsh-technique-card__info">
				<div className="gfsh-technique-card__name">{name}</div>
				<div className="gfsh-technique-card__description">
					{description}
					{infoModalContent && (
						<>
							{' · '}
							<button
								type="button"
								className="gfsh-technique-card__info-link"
								onClick={() => setInfoModalOpen(true)}
							>
								{__('How it works', 'gf-spam-hexer')}
							</button>
						</>
					)}
				</div>
			</div>

			{!alwaysOpen && (
				<div className="gfsh-technique-card__toggle">
					<SwitchToggle
						checked={enabled}
						onChange={onToggle}
						ariaLabel={`${name} ${enabled ? __('enabled', 'gf-spam-hexer') : __('disabled', 'gf-spam-hexer')}`}
					/>
				</div>
			)}
		</div>
	);

	return (
		<>
			<SettingsPanel
				header={headerContent}
				collapsed={!alwaysOpen && !enabled}
				className={`gfsh-technique-card ${!alwaysOpen && !enabled ? 'gfsh-technique-card--disabled' : ''}`}
			>
				{(alwaysOpen || enabled) && children}
			</SettingsPanel>

			{infoModalContent && (
				<InfoModal
					title={
						infoModalTitle ||
						`${__('How it works:', 'gf-spam-hexer')} ${name}`
					}
					isOpen={infoModalOpen}
					onClose={closeModal}
					onLinkClick={closeModal}
				>
					{infoModalContent}
				</InfoModal>
			)}
		</>
	);
};
