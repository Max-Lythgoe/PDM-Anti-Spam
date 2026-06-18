/**
 * WP Theme — InfoModal adapter
 * Wraps @wordpress/components Modal to match the shared InfoModalProps API.
 * Maps onClose → onRequestClose prop name.
 */

import { useRef, useEffect } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import type { InfoModalProps } from '../../context/UIContext';

export const InfoModal = ({
	title,
	isOpen,
	onClose,
	onLinkClick,
	children,
}: InfoModalProps) => {
	const bodyRef = useRef<HTMLDivElement>(null);

	// Wire up link-click handler via native event
	useEffect(() => {
		if (!isOpen || !onLinkClick) {
			return;
		}
		const el = bodyRef.current;
		if (!el) {
			return;
		}
		const handleClick = (e: MouseEvent) => {
			if ((e.target as HTMLElement).closest('a')) {
				onLinkClick();
			}
		};
		el.addEventListener('click', handleClick);
		return () => el.removeEventListener('click', handleClick);
	}, [isOpen, onLinkClick]);

	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={title}
			onRequestClose={onClose}
			style={{ width: '560px' }}
		>
			<div ref={bodyRef}>{children}</div>
		</Modal>
	);
};
