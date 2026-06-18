/**
 * WP Theme — SegmentedControl adapter
 * Wraps @wordpress/components __experimentalToggleGroupControl to match the shared
 * SegmentedControlProps API.
 *
 * Limitation: WP ToggleGroupControlOption only accepts string labels, not ReactNode.
 * Icon+text labels (used by ProtectionLevelSelector) will render as text-only in WP theme.
 */

import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import type { SegmentedControlProps } from '../../context/UIContext';

export const SegmentedControl = <T extends string | number = string | number>({
	label,
	options,
	value,
	onChange,
	disabled,
	help,
}: SegmentedControlProps<T>) => (
	<ToggleGroupControl
		label={label ?? ''}
		value={value as string | number}
		onChange={(v) => onChange(v as T)}
		isBlock
		help={help}
		disabled={disabled}
	>
		{options.map((opt) => (
			<ToggleGroupControlOption
				key={String(opt.value)}
				value={opt.value as string | number}
				label={opt.textLabel ?? String(opt.value)}
			/>
		))}
	</ToggleGroupControl>
);
