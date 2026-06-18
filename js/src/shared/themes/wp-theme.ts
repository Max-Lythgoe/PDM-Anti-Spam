/**
 * WP Theme
 *
 * Assembles all @wordpress/components adapters into a UIComponents object.
 * Used by the comment-settings entry point which renders on options-discussion.php
 * (a native WordPress admin page, not inside Gravity Forms).
 */

import type { UIComponents } from '../context/UIContext';
import { TextInput } from './wp/TextInput';
import { SelectInput } from './wp/SelectInput';
import { SwitchToggle } from './wp/SwitchToggle';
import { NumberInput } from './wp/NumberInput';
import { RangeInput } from './wp/RangeInput';
import { Notice } from './wp/Notice';
import { Button } from './wp/Button';
import { SettingsField } from './wp/SettingsField';
import { InfoModal } from './wp/InfoModal';
import { SettingsPanel } from './wp/SettingsPanel';
import { SegmentedControl } from './wp/SegmentedControl';
import { Tabs } from './wp/Tabs';
import { Card } from './wp/Card';

export const wpTheme: UIComponents = {
	TextInput,
	SelectInput,
	SwitchToggle,
	NumberInput,
	RangeInput,
	Notice,
	Button,
	SettingsField,
	InfoModal,
	SettingsPanel,
	SegmentedControl,
	Tabs,
	Card,
};
