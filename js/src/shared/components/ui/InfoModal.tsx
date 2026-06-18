/**
 * Info Modal Component
 *
 * A lightweight, accessible modal dialog for displaying technique
 * "How it works" content. Uses a portal to render at the document body
 * level, with focus trap, Escape-to-close, and click-outside-to-close.
 *
 * Built custom (no @wordpress/components dependency) to stay consistent
 * with the project's native UI component pattern.
 */

import { useEffect, useRef, useCallback } from '@wordpress/element';
import { createPortal } from 'react-dom';
import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

import './InfoModal.css';

export interface InfoModalProps {
	/** Modal title displayed in the header */
	title: string;
	/** Whether the modal is open */
	isOpen: boolean;
	/** Close handler */
	onClose: () => void;
	/** Modal body content */
	children: ReactNode;
	/** Called when any link inside the modal body is clicked */
	onLinkClick?: () => void;
}

export const InfoModal = ({
	title,
	isOpen,
	onClose,
	children,
	onLinkClick,
}: InfoModalProps) => {
	const dialogRef = useRef<HTMLDivElement>(null);
	const previousFocusRef = useRef<HTMLElement | null>(null);

	// Store the previously focused element and restore on close
	useEffect(() => {
		const dialogEl = dialogRef.current;

		if (isOpen) {
			const ownerDoc = dialogEl?.ownerDocument ?? document;
			previousFocusRef.current = ownerDoc.activeElement as HTMLElement;

			// Focus the dialog after render
			requestAnimationFrame(() => {
				const closeBtn = dialogEl?.querySelector<HTMLElement>(
					'.gfsh-info-modal__close'
				);
				closeBtn?.focus();
			});

			// Prevent body scroll
			ownerDoc.body.style.overflow = 'hidden';
		}

		return () => {
			const ownerDoc = dialogEl?.ownerDocument ?? document;
			ownerDoc.body.style.overflow = '';
			if (previousFocusRef.current) {
				previousFocusRef.current.focus();
				previousFocusRef.current = null;
			}
		};
	}, [isOpen]);

	// Close modal when any link inside is clicked (e.g. "AI Provider tab" link)
	useEffect(() => {
		if (!isOpen || !onLinkClick) {
			return;
		}
		const dialogEl = dialogRef.current;
		if (!dialogEl) {
			return;
		}
		const handleClick = (e: MouseEvent) => {
			if ((e.target as HTMLElement).closest('a')) {
				onLinkClick();
			}
		};
		dialogEl.addEventListener('click', handleClick);
		return () => dialogEl.removeEventListener('click', handleClick);
	}, [isOpen, onLinkClick]);

	// Handle Escape key
	const handleKeyDown = useCallback(
		(e: React.KeyboardEvent) => {
			if (e.key === 'Escape') {
				e.stopPropagation();
				onClose();
			}

			// Basic focus trap: Tab within the modal
			if (e.key === 'Tab' && dialogRef.current) {
				const ownerDoc = dialogRef.current.ownerDocument;
				const focusable =
					dialogRef.current.querySelectorAll<HTMLElement>(
						'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
					);
				const first = focusable[0];
				const last = focusable[focusable.length - 1];

				if (e.shiftKey && ownerDoc.activeElement === first) {
					e.preventDefault();
					last?.focus();
				} else if (!e.shiftKey && ownerDoc.activeElement === last) {
					e.preventDefault();
					first?.focus();
				}
			}
		},
		[onClose]
	);

	if (!isOpen) {
		return null;
	}

	return createPortal(
		/* eslint-disable jsx-a11y/no-static-element-interactions -- keyDown handler on wrapper for focus trap */
		<div
			className="gfsh-info-modal__overlay"
			onClick={(e) => {
				if (e.target === e.currentTarget) {
					onClose();
				}
			}}
			onKeyDown={handleKeyDown}
		>
			<div
				ref={dialogRef}
				className="gfsh-info-modal"
				role="dialog"
				aria-modal="true"
				aria-label={title}
			>
				<div className="gfsh-info-modal__header">
					<h3 className="gfsh-info-modal__title">{title}</h3>
					<button
						type="button"
						className="gfsh-info-modal__close"
						onClick={onClose}
						aria-label={__('Close', 'gf-spam-hexer')}
					>
						×
					</button>
				</div>
				<div className="gfsh-info-modal__body">{children}</div>
			</div>
		</div>,
		/* eslint-enable jsx-a11y/no-static-element-interactions */
		document.body
	);
};
