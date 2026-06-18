/**
 * AI Provider Configuration Content
 *
 * Renders the AI provider/model/API key/ZDR configuration fields.
 * Used both inline (as tab content in plugin settings) and inside
 * the AiProviderModal. AI provider config is plugin-wide (shared
 * across forms + comments).
 */

import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { FormField } from './FormField';
import { Notice } from './ui';
import {
	AI_PROVIDER_MODES,
	AI_PROVIDER_MODE_OPTIONS,
} from '../constants/ai-providers';
import type { AiProviderMode } from '../types/settings';

export interface AiProviderConfigProps {
	// Values
	aiProvider: string;
	aiApiKey: string;
	aiModel: string;
	aiZdr: string;

	// Setters
	setAiProvider: (value: string) => void;
	setAiApiKey: (value: string) => void;
	setAiModel: (value: string) => void;
	setAiZdr: (value: string) => void;

	// Environment
	wpAiClientAvailable: boolean;
	connectorsUrl: string;
	availableModelsAuto: Array<{ id: string; label: string; provider: string }>;
	availableModelsOpenRouter: Array<{ id: string; label: string }>;
}

export const AiProviderConfig = ({
	aiProvider,
	aiApiKey,
	aiModel,
	aiZdr,
	setAiProvider,
	setAiApiKey,
	setAiModel,
	setAiZdr,
	wpAiClientAvailable,
	connectorsUrl,
	availableModelsAuto,
	availableModelsOpenRouter,
}: AiProviderConfigProps) => {
	const availableModels: Array<{
		id: string;
		label: string;
		provider?: string;
	}> =
		aiProvider === 'openrouter'
			? availableModelsOpenRouter
			: availableModelsAuto;
	const isCustomModel =
		!!aiModel && !availableModels.some((m) => m.id === aiModel);
	const [customMode, setCustomMode] = useState(isCustomModel);

	const currentMode =
		AI_PROVIDER_MODES[aiProvider as AiProviderMode] ??
		AI_PROVIDER_MODES.auto;

	return (
		<>
			{/* Provider Section */}
			<div className="gfsh-ai-section">
				<FormField
					label={__('AI Provider Mode', 'gf-spam-hexer')}
					htmlFor="gfsh-ai-provider"
					description={currentMode.description}
				>
					<select
						id="gfsh-ai-provider"
						className="gfsh-ai-input"
						value={aiProvider}
						onChange={(e) => {
							const newMode = e.target.value as AiProviderMode;
							setAiProvider(newMode);
							if (newMode === 'auto') {
								setAiModel('');
							}
						}}
					>
						{AI_PROVIDER_MODE_OPTIONS.map((id) => (
							<option key={id} value={id}>
								{AI_PROVIDER_MODES[id].name}
							</option>
						))}
					</select>
				</FormField>

				{/* WP AI Client status (only when auto mode selected) */}
				{aiProvider === 'auto' && (
					<div className="gfsh-connector-status">
						{wpAiClientAvailable ? (
							<Notice variant="success">
								{__(
									'WordPress AI Client is available. Provider and API keys are managed in',
									'gf-spam-hexer'
								)}{' '}
								{connectorsUrl ? (
									<a href={connectorsUrl}>
										{__(
											'Settings → Connectors',
											'gf-spam-hexer'
										)}
									</a>
								) : (
									__('Settings → Connectors', 'gf-spam-hexer')
								)}
								.
							</Notice>
						) : (
							<Notice
								variant="warning"
								style={{ marginBottom: 0 }}
							>
								{__(
									'WordPress AI Client is not available. Requires WordPress 7.0+ with at least one AI provider plugin active.',
									'gf-spam-hexer'
								)}
							</Notice>
						)}
					</div>
				)}
			</div>

			{/* Model / API Configuration Section */}
			<div className="gfsh-ai-section">
				{aiProvider !== 'auto' && (
					<h4 className="gfsh-ai-section__title">
						API Configuration
					</h4>
				)}

				{/* API Key — only needed for OpenRouter mode */}
				{aiProvider === 'openrouter' && (
					<FormField
						label={__('API Key', 'gf-spam-hexer')}
						htmlFor="gfsh-ai-api-key"
						description={
							<>
								{__('Get a key at', 'gf-spam-hexer')}{' '}
								<a
									href="https://openrouter.ai/keys"
									target="_blank"
									rel="noopener noreferrer"
								>
									openrouter.ai/keys
								</a>
							</>
						}
					>
						<input
							id="gfsh-ai-api-key"
							type="password"
							className="gfsh-ai-input"
							value={aiApiKey}
							onChange={(e) => setAiApiKey(e.target.value)}
							placeholder="••••••••••••••••"
							autoComplete="off"
						/>
					</FormField>
				)}

				{/* Zero Data Retention — only for OpenRouter direct mode */}
				{aiProvider === 'openrouter' && (
					<FormField
						label={__('Zero Data Retention', 'gf-spam-hexer')}
						htmlFor="gfsh-ai-zdr"
						tooltip={__(
							'Only route to endpoints that do not store your prompts or responses. Providers with ZDR are also unable to train on your data. Learn more: openrouter.ai/docs/guides/features/zdr',
							'gf-spam-hexer'
						)}
					>
						<label
							htmlFor="gfsh-ai-zdr"
							style={{
								display: 'flex',
								alignItems: 'center',
								margin: '10px 0',
							}}
						>
							<input
								id="gfsh-ai-zdr"
								type="checkbox"
								checked={aiZdr === '1'}
								onChange={(e) =>
									setAiZdr(e.target.checked ? '1' : '0')
								}
							/>
							{__('Enforce Zero Data Retention', 'gf-spam-hexer')}
						</label>
						{aiZdr === '1' && (
							<Notice variant="warning">
								{__(
									'Not all models support Zero Data Retention. If your selected model does not support ZDR, OpenRouter will return an error and classification will fail. Check',
									'gf-spam-hexer'
								)}{' '}
								<a
									href="https://openrouter.ai/docs/guides/features/zdr"
									target="_blank"
									rel="noopener noreferrer"
								>
									{__(
										"OpenRouter's ZDR docs",
										'gf-spam-hexer'
									)}
								</a>{' '}
								{__(
									'to confirm your model is compatible.',
									'gf-spam-hexer'
								)}
							</Notice>
						)}
					</FormField>
				)}

				<FormField
					label={
						aiProvider === 'auto'
							? __('Preferred Model', 'gf-spam-hexer')
							: __('Model', 'gf-spam-hexer')
					}
					htmlFor="gfsh-ai-model"
					tooltip={
						aiProvider === 'auto'
							? __(
									'The AI Client will try this model first. Leave blank to auto-select the cheapest available model.',
									'gf-spam-hexer'
								)
							: __(
									'OpenRouter model ID. Fast, cheap models recommended.',
									'gf-spam-hexer'
								)
					}
				>
					<select
						id="gfsh-ai-model"
						className="gfsh-ai-input"
						value={customMode ? '__custom__' : aiModel}
						onChange={(e) => {
							const val = e.target.value;
							if (val === '__custom__') {
								setCustomMode(true);
								if (
									!aiModel ||
									availableModels.some(
										(m) => m.id === aiModel
									)
								) {
									setAiModel('');
								}
							} else {
								setCustomMode(false);
								setAiModel(val);
							}
						}}
					>
						<option value="">
							{aiProvider === 'auto'
								? __(
										'Auto (cheapest available)',
										'gf-spam-hexer'
									)
								: __('Default', 'gf-spam-hexer')}
						</option>
						{availableModels.map((m) => (
							<option key={m.id} value={m.id}>
								{m.label}
								{m.provider ? ` — ${m.provider}` : ''}
							</option>
						))}
						<option value="__custom__">
							{__('Custom…', 'gf-spam-hexer')}
						</option>
					</select>

					{/* Show text input when "Custom..." is selected */}
					{customMode && (
						<input
							type="text"
							className="gfsh-ai-input gfsh-ai-custom-model-input"
							value={aiModel}
							onChange={(e) => setAiModel(e.target.value)}
							placeholder="vendor/model-name"
						/>
					)}
				</FormField>
			</div>
		</>
	);
};
