/**
 * WP Theme — SettingsPanel adapter
 * Wraps @wordpress/components Panel + PanelBody to match the shared SettingsPanelProps API.
 *
 * When a custom `header` ReactNode is provided (e.g. TechniqueCard's icon+toggle header),
 * it is rendered above the PanelBody content since PanelBody only accepts a string title.
 *
 * Note: half, noPadding have no WP equivalent — omitted.
 */

import { Panel, PanelBody } from '@wordpress/components';
import type { SettingsPanelProps } from '../../context/UIContext';

export const SettingsPanel = ({
	title,
	header,
	children,
	collapsed,
	className,
}: SettingsPanelProps) => (
	<Panel className={className}>
		{header && <div className="gfsh-wp-panel-header">{header}</div>}
		<PanelBody title={header ? undefined : title} initialOpen={!collapsed}>
			{children}
		</PanelBody>
	</Panel>
);
