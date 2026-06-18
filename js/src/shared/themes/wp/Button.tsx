/**
 * WP Theme — Button adapter
 * Wraps @wordpress/components Button to match the shared ButtonProps API.
 * Maps variant='destructive' → isDestructive flag.
 */

import { Button as WPButton } from '@wordpress/components';
import type { ButtonProps } from '../../context/UIContext';

export const Button = ({
	children,
	onClick,
	variant = 'secondary',
	type = 'button',
	disabled,
	className,
}: ButtonProps) => (
	<WPButton
		variant={variant === 'destructive' ? 'secondary' : variant}
		isDestructive={variant === 'destructive'}
		onClick={onClick}
		type={type}
		disabled={disabled}
		className={className}
	>
		{children}
	</WPButton>
);
