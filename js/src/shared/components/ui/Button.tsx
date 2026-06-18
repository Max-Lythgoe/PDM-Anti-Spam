/**
 * Native Button Component
 *
 * Matches Gravity Forms settings panel button styling.
 */

export interface ButtonProps {
	children: React.ReactNode;
	onClick?: () => void;
	variant?: 'primary' | 'secondary' | 'tertiary' | 'destructive';
	type?: 'button' | 'submit' | 'reset';
	disabled?: boolean;
	className?: string;
}

export const Button = ({
	children,
	onClick,
	variant = 'secondary',
	type = 'button',
	disabled = false,
	className = '',
}: ButtonProps) => {
	const variantClass = `gform-button--${variant}`;
	const classes = `gform-button ${variantClass} ${className}`.trim();

	return (
		<button
			type={type}
			className={classes}
			onClick={onClick}
			disabled={disabled}
		>
			{children}
		</button>
	);
};
