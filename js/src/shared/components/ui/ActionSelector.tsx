/**
 * Action Selector Component
 *
 * Reusable SegmentedControl for choosing between "Flag as Spam",
 * "Silent Reject", and "Validation Error" actions. Used by both PoW and AI
 * technique settings in plugin-settings, form-settings, and comment-settings pages.
 *
 * "Flag as Spam" is always the first (left) option to reinforce it as the
 * recommended default.
 *
 * The label for this control is provided by the parent SettingsField wrapper —
 * this component renders only the segmented buttons and help text.
 *
 * @example
 * ```tsx
 * <ActionSelector variant="pow" value={powAction} onChange={setPowAction} />
 * <ActionSelector variant="ai" value={aiAction} onChange={setAiAction} />
 * ```
 */

import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { useUI } from '../../context/UIContext';
import { FlagIcon, BlockIcon, AlertIcon } from '../icons/ActionIcons';
import './ActionSelector.css';

type ActionValue = 'spam' | 'reject' | 'fail';

interface ActionSelectorProps {
	/** Which technique this selector is for — determines help text. */
	variant: 'pow' | 'ai';
	/** Currently selected action value. */
	value: string;
	/** Change handler. */
	onChange: (value: string) => void;
	/** Whether the control is disabled. */
	disabled?: boolean;
	/**
	 * Which actions to offer. Defaults to all three (spam/reject/fail).
	 * Comment mode passes ['spam', 'fail'] because silent reject is not
	 * supported for comments.
	 */
	allowedActions?: ActionValue[];
}

const HELP_TEXT: Record<
	ActionSelectorProps['variant'],
	Record<ActionValue, string>
> = {
	pow: {
		spam: __(
			'Submissions without a valid proof of work are marked as spam for review. The entry is still saved.',
			'gf-spam-hexer'
		),
		reject: __(
			'Submissions without a valid proof of work are silently discarded. The submitter sees a fake success message.',
			'gf-spam-hexer'
		),
		fail: __(
			'Submissions without a valid proof of work are blocked with a visible validation error. Useful for testing.',
			'gf-spam-hexer'
		),
	},
	ai: {
		spam: __(
			'Detected spam is marked as spam for review. The entry is saved — nothing is lost.',
			'gf-spam-hexer'
		),
		reject: __(
			"Detected spam is quietly discarded. The submitter sees a normal confirmation so bots don't know they were caught. No entry is created.",
			'gf-spam-hexer'
		),
		fail: __(
			'Detected spam is blocked with a validation error, giving the submitter a chance to correct and resubmit. No entry is created until the submission passes.',
			'gf-spam-hexer'
		),
	},
};

const ACTION_OPTIONS: {
	value: ActionValue;
	label: ReactNode;
	textLabel: string;
}[] = [
	{
		value: 'spam',
		label: (
			<span className="gfsh-action-selector__option">
				<FlagIcon />
				{__('Flag as Spam', 'gf-spam-hexer')}
			</span>
		),
		textLabel: __('Flag as Spam', 'gf-spam-hexer'),
	},
	{
		value: 'reject',
		label: (
			<span className="gfsh-action-selector__option">
				<BlockIcon />
				{__('Silent Reject', 'gf-spam-hexer')}
			</span>
		),
		textLabel: __('Silent Reject', 'gf-spam-hexer'),
	},
	{
		value: 'fail',
		label: (
			<span className="gfsh-action-selector__option">
				<AlertIcon />
				{__('Validation Error', 'gf-spam-hexer')}
			</span>
		),
		textLabel: __('Validation Error', 'gf-spam-hexer'),
	},
];

export const ActionSelector = ({
	variant,
	value,
	onChange,
	disabled,
	allowedActions,
}: ActionSelectorProps) => {
	const { SegmentedControl } = useUI();

	const options =
		allowedActions && allowedActions.length > 0
			? ACTION_OPTIONS.filter((opt) => allowedActions.includes(opt.value))
			: ACTION_OPTIONS;

	// Normalize the value, then ensure it is one of the allowed actions —
	// falling back to the first allowed option (spam) if not.
	let actionValue: ActionValue =
		value === 'reject' ? 'reject' : value === 'fail' ? 'fail' : 'spam';
	if (!options.some((opt) => opt.value === actionValue)) {
		actionValue = (options[0]?.value as ActionValue) ?? 'spam';
	}

	return (
		<div className="gfsh-action-selector">
			<SegmentedControl
				options={options}
				value={actionValue}
				onChange={(v) => onChange(String(v))}
				disabled={disabled}
				help={HELP_TEXT[variant][actionValue]}
			/>
		</div>
	);
};
