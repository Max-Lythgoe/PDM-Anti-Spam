/**
 * WP Theme — Notice adapter
 * Wraps @wordpress/components Notice to match the shared NoticeProps API.
 * Maps variant → status prop name.
 */

import { Notice as WPNotice } from '@wordpress/components';
import type { NoticeProps } from '../../context/UIContext';

export const Notice = ({ variant, children, className }: NoticeProps) => (
	<WPNotice status={variant} isDismissible={false} className={className}>
		{children}
	</WPNotice>
);
