/**
 * Unified Technique Settings Component
 *
 * Renders technique configuration cards for both plugin-level (global)
 * and form-level (per-form override) settings pages. Driven by a
 * TechniqueSettingsAdapter that abstracts store differences.
 *
 * Plugin mode:  All values are definitive. No override indicators.
 *               AI provider/model/key config is injected via `aiExtraContent`.
 * Form mode:    Values are resolved (override ?? global). Each field is wrapped
 *               in a SettingsField with an inline "↩ Reset to global" labelAction
 *               when the value differs from the global setting.
 */

import { __ } from '@wordpress/i18n';
import './TechniqueSettings.css';
import { TechniqueCard } from './ui/TechniqueCard';
import { ActionSelector } from './ui/ActionSelector';
import { OverridableFieldReset } from './ui/OverridableField';
import { NestedSettings } from './ui/NestedSettings';
import { ProtectionLevelSelector } from './ProtectionLevelSelector';
import { PowInfoContent } from './PowInfoContent';
import { TECHNIQUES } from '../constants/techniques';
import type { TechniqueSettingsAdapter } from '../types/technique-settings-adapter';
import { useUI } from '../context/UIContext';

interface TechniqueSettingsProps {
	adapter: TechniqueSettingsAdapter;
}

/**
 * Wraps children in a SettingsField with an optional inline reset button.
 * In plugin mode, renders a plain SettingsField with no reset action.
 * In form mode, shows the reset button when the value is overridden.
 */
const FieldWithReset = ({
	label,
	description,
	tooltip,
	isFormMode,
	isOverridden,
	onReset,
	children,
}: {
	label: string;
	description?: string;
	tooltip?: string;
	isFormMode: boolean;
	isOverridden: boolean;
	onReset: () => void;
	children: React.ReactNode;
}) => {
	const { SettingsField } = useUI();
	return (
		<SettingsField
			label={label}
			description={description}
			tooltip={tooltip}
			labelAction={
				isFormMode && isOverridden ? (
					<OverridableFieldReset onReset={onReset} />
				) : undefined
			}
		>
			{children}
		</SettingsField>
	);
};

export const TechniqueSettings = ({ adapter }: TechniqueSettingsProps) => {
	const { RangeInput, TextInput, SettingsField } = useUI();
	const isFormMode = adapter.mode === 'form';
	const isCommentMode = adapter.mode === 'comment';

	/**
	 * Actions offered in the ActionSelector. Comments support 'spam' and
	 * 'fail' only — 'reject' (silent discard) is not available for comments.
	 * Passing undefined lets ActionSelector render all three options.
	 */
	const allowedActions = isCommentMode
		? (['spam', 'fail'] as const)
		: undefined;

	/** Custom context label varies by mode. */
	const customContextLabel = isFormMode
		? __('Form-Specific Context (optional)', 'gf-spam-hexer')
		: isCommentMode
			? __('Comment-Specific Context (optional)', 'gf-spam-hexer')
			: __('Custom Context (optional)', 'gf-spam-hexer');

	const customContextDescription = isFormMode
		? __(
				'Appended to your global context to help the AI understand what submissions are legitimate for this form.',
				'gf-spam-hexer'
			)
		: isCommentMode
			? __(
					'Help the AI understand what types of comments are legitimate for your site.',
					'gf-spam-hexer'
				)
			: __(
					'Help the AI understand what types of submissions are legitimate for your site. This is sent with every classification request.',
					'gf-spam-hexer'
				);

	const customContextPlaceholder = isFormMode
		? __(
				'e.g. This form collects job applications for our engineering team.',
				'gf-spam-hexer'
			)
		: isCommentMode
			? __(
					'e.g. This is a tech blog — comments about programming are legitimate.',
					'gf-spam-hexer'
				)
			: __(
					'e.g. We welcome wholesale partnership inquiries and catering requests.',
					'gf-spam-hexer'
				);

	return (
		<div className="gfsh-technique-settings">
			{/* Proof of Work Card */}
			<TechniqueCard
				icon={TECHNIQUES.pow.icon}
				name={TECHNIQUES.pow.name}
				description={TECHNIQUES.pow.description}
				enabled={adapter.powEnabled}
				onToggle={adapter.setPowEnabled}
				infoModalContent={<PowInfoContent />}
			>
				<FieldWithReset
					label={__('When a submission fails', 'gf-spam-hexer')}
					isFormMode={isFormMode}
					isOverridden={adapter.overrides.powAction}
					onReset={adapter.resetField.powAction}
				>
					<ActionSelector
						variant="pow"
						value={adapter.powAction}
						onChange={adapter.setPowAction}
						allowedActions={
							allowedActions ? [...allowedActions] : undefined
						}
					/>
				</FieldWithReset>

				{adapter.powAction === 'fail' && (
					<NestedSettings>
						<FieldWithReset
							label={__('Validation Message', 'gf-spam-hexer')}
							tooltip={__(
								'Shown to the visitor when their submission is blocked.',
								'gf-spam-hexer'
							)}
							isFormMode={isFormMode}
							isOverridden={adapter.overrides.powFailMessage}
							onReset={adapter.resetField.powFailMessage}
						>
							<TextInput
								id="gfsh-pow-fail-message"
								label=""
								value={adapter.powFailMessage}
								onChange={adapter.setPowFailMessage}
								placeholder={__(
									'Your submission could not be processed. Please try again.',
									'gf-spam-hexer'
								)}
							/>
						</FieldWithReset>
					</NestedSettings>
				)}

				<FieldWithReset
					label={__('Protection Level', 'gf-spam-hexer')}
					isFormMode={isFormMode}
					isOverridden={adapter.overrides.powProtectionLevel}
					onReset={adapter.resetField.powProtectionLevel}
				>
					<ProtectionLevelSelector
						value={adapter.powProtectionLevel}
						onChange={adapter.setPowProtectionLevel}
					/>
				</FieldWithReset>
			</TechniqueCard>

			{/* AI Classification Card */}
			<TechniqueCard
				icon={TECHNIQUES.ai.icon}
				name={TECHNIQUES.ai.name}
				description={TECHNIQUES.ai.description}
				enabled={adapter.aiEnabled}
				onToggle={adapter.setAiEnabled}
				infoModalContent={adapter.aiInfoModalContent}
			>
				<FieldWithReset
					label={__('When spam is detected', 'gf-spam-hexer')}
					isFormMode={isFormMode}
					isOverridden={adapter.overrides.aiAction}
					onReset={adapter.resetField.aiAction}
				>
					<ActionSelector
						variant="ai"
						value={adapter.aiAction}
						onChange={adapter.setAiAction}
						allowedActions={
							allowedActions ? [...allowedActions] : undefined
						}
					/>
				</FieldWithReset>

				{adapter.aiAction === 'fail' && (
					<NestedSettings>
						<FieldWithReset
							label={__('Validation Message', 'gf-spam-hexer')}
							tooltip={__(
								'Shown to the visitor when their submission is blocked.',
								'gf-spam-hexer'
							)}
							isFormMode={isFormMode}
							isOverridden={adapter.overrides.aiFailMessage}
							onReset={adapter.resetField.aiFailMessage}
						>
							<TextInput
								id="gfsh-ai-fail-message"
								label=""
								value={adapter.aiFailMessage}
								onChange={adapter.setAiFailMessage}
								placeholder={__(
									'Your submission could not be processed. Please try again.',
									'gf-spam-hexer'
								)}
							/>
						</FieldWithReset>
					</NestedSettings>
				)}

				{/* AI provider config (plugin: modal trigger, comment: link) */}
				{adapter.aiExtraContent}

				{/* Confidence Threshold */}
				<FieldWithReset
					label={__('Confidence Threshold', 'gf-spam-hexer')}
					tooltip={__(
						'AI confidence at or above this level is classified as spam. Lower = more aggressive.',
						'gf-spam-hexer'
					)}
					isFormMode={isFormMode}
					isOverridden={adapter.overrides.aiConfidenceThreshold}
					onReset={adapter.resetField.aiConfidenceThreshold}
				>
					<RangeInput
						label=""
						value={Math.round(adapter.aiConfidenceThreshold * 100)}
						onChange={(v) =>
							adapter.setAiConfidenceThreshold(v / 100)
						}
						min={10}
						max={95}
						step={5}
						formatValue={(v) => `${v}%`}
					/>
				</FieldWithReset>

				{/* Custom Context */}
				<SettingsField
					label={customContextLabel}
					htmlFor="gfsh-ai-custom-context"
					tooltip={customContextDescription}
				>
					<textarea
						id="gfsh-ai-custom-context"
						className={
							isFormMode
								? 'gform-text-input-reset'
								: 'gfsh-ai-input'
						}
						value={adapter.aiCustomContext}
						onChange={(e) =>
							adapter.setAiCustomContext(e.target.value)
						}
						rows={3}
						placeholder={customContextPlaceholder}
					/>
				</SettingsField>
			</TechniqueCard>
		</div>
	);
};
