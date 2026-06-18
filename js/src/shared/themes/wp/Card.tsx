/**
 * WP Theme — Card adapter
 *
 * Wraps @wordpress/components Card + CardHeader + CardBody to match
 * the shared CardProps API.
 *
 * TODO: Migrate to Card/CollapsibleCard from @wordpress/ui when that package
 * becomes available in this WordPress version. The @wordpress/components Card
 * is deprecated in favour of @wordpress/ui.
 *
 * @see https://wordpress.github.io/gutenberg/?path=/docs/components-card--docs
 */

import { Card as WPCard, CardBody, CardHeader } from '@wordpress/components';
import type { CardProps } from '../../components/ui/Card';

export const Card = ({ title, children, className }: CardProps) => (
	<WPCard className={className}>
		{title && <CardHeader>{title}</CardHeader>}
		<CardBody>{children}</CardBody>
	</WPCard>
);
