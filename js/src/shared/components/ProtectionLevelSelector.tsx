/**
 * Protection Level Selector Component
 *
 * A 3-option segmented control for selecting PoW protection level
 * (Light / Standard / Strict). Shows dynamic description text below
 * based on the selected preset.
 *
 * Uses custom shield SVG icons instead of emoji for a polished look
 * that inherits the button's text color automatically.
 */

import type { ReactNode } from 'react';
import { useUI } from '../context/UIContext';
import './ProtectionLevelSelector.css';
import {
	POW_PROTECTION_LEVELS,
	getProtectionLevel,
} from '../constants/ui-options';
import type { ProtectionLevelId } from '../constants/ui-options';
import {
	ShieldLightIcon,
	ShieldStandardIcon,
	ShieldStrictIcon,
} from './icons/ProtectionLevelIcons';

/**
 * Maps each protection level ID to its SVG icon component.
 */
const LEVEL_ICONS: Record<ProtectionLevelId, ReactNode> = {
	light: <ShieldLightIcon />,
	standard: <ShieldStandardIcon />,
	strict: <ShieldStrictIcon />,
};

interface ProtectionLevelSelectorProps {
	/** Currently selected protection level ID */
	value: string;
	/** Change handler */
	onChange: (value: string) => void;
	/** Whether the control is disabled */
	disabled?: boolean;
}

export const ProtectionLevelSelector = ({
	value,
	onChange,
	disabled = false,
}: ProtectionLevelSelectorProps) => {
	const { SegmentedControl } = useUI();
	const selectedLevel =
		getProtectionLevel(value) ?? getProtectionLevel('standard')!;

	const options = POW_PROTECTION_LEVELS.map((level) => ({
		value: level.id,
		label: (
			<span className="gfsh-protection-level-selector__option">
				{LEVEL_ICONS[level.id]}
				{level.label}
			</span>
		),
		textLabel: level.label,
	}));

	return (
		<div className="gfsh-protection-level-selector">
			<SegmentedControl
				options={options}
				value={value || 'standard'}
				onChange={(v) => onChange(String(v))}
				disabled={disabled}
			/>
			<div className="gfsh-protection-level-selector__description">
				<p className="gfsh-protection-level-selector__short-desc">
					{selectedLevel.shortDesc}
				</p>
				<p className="gfsh-protection-level-selector__timing">
					{selectedLevel.timing}
				</p>
			</div>
		</div>
	);
};
