/**
 * GF Theme
 *
 * Re-exports the existing GF-class-based UI primitives as a UIComponents
 * object. Used by form-settings and plugin-settings entry points which render
 * inside Gravity Forms' admin CSS context.
 */

import type { UIComponents } from '../context/UIContext';
import { TextInput } from '../components/ui/TextInput';
import { SelectInput } from '../components/ui/SelectInput';
import { SwitchToggle } from '../components/ui/SwitchToggle';
import { NumberInput } from '../components/ui/NumberInput';
import { RangeInput } from '../components/ui/RangeInput';
import { Notice } from '../components/ui/Notice';
import { Button } from '../components/ui/Button';
import { SettingsField } from '../components/ui/SettingsField';
import { InfoModal } from '../components/ui/InfoModal';
import { SettingsPanel } from '../components/ui/SettingsPanel';
import { SegmentedControl } from '../components/ui/SegmentedControl';
import { Tabs } from '../components/ui/Tabs';
import { Card } from '../components/ui/Card';

export const gfTheme: UIComponents = {
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
