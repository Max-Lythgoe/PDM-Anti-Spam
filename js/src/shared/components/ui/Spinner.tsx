/**
 * Native Spinner Component
 *
 * Simple loading spinner matching Gravity Forms / WordPress admin styling.
 */

import './Spinner.css';

interface SpinnerProps {
	/** Size of the spinner */
	size?: 'small' | 'medium' | 'large';
	/** Additional CSS class */
	className?: string;
}

export const Spinner = ({ size = 'medium', className = '' }: SpinnerProps) => {
	const sizeClass = `gpm-spinner--${size}`;
	const classes = `gpm-spinner ${sizeClass} ${className}`.trim();

	return (
		<span className={classes} role="status" aria-label="Loading">
			<span className="gpm-spinner__circle" />
		</span>
	);
};
