<?php
/**
 * AI Classification technique.
 *
 * Sends form submission data to an AI provider for spam classification.
 * The AI analyzes the content in context (site info, form structure,
 * custom admin context) and returns a spam probability.
 *
 * Key design decisions:
 * - The AI returns a confidence score (0.0–1.0) which is compared against
 *   the user-configurable ai_confidence_threshold setting.
 * - If confidence >= threshold → spam. Otherwise → pass.
 * - Gracefully degrades: skipped if no AI provider available.
 * - Timeout is configurable (default 10s) to avoid blocking form submission.
 * - On WP 7.0+, uses the WP AI Client (wp_ai_client_prompt()) as the
 *   primary path. Falls back to direct OpenRouter HTTP for WP < 7.0.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Techniques;

use PDM_Antispam\Comments\Comment_Context;
use PDM_Antispam\Comments\Comment_Settings;
use PDM_Antispam\Contracts\Checkable_Context;
use PDM_Antispam\Settings;
use PDM_Antispam\AI\AI_Exception;
use PDM_Antispam\AI\AI_Provider;
use PDM_Antispam\AI\OpenRouter_Provider;
use PDM_Antispam\AI\WP_AI_Client_Provider;
use PDM_Antispam\AI\Prompt_Builder;

/**
 * AI-powered spam content classifier.
 */
class AI_Classifier extends Base_Technique {

	/**
	 * Default maximum completion tokens sent to AI providers.
	 *
	 * Filterable via the `gfsh_ai_max_tokens` hook.
	 */
	private const DEFAULT_MAX_TOKENS = 1000;

	/**
	 * Default reasoning effort level sent to AI providers.
	 *
	 * Filterable via the `gfsh_ai_reasoning_effort` hook.
	 * Defaults to null — omitted by default because most modern models
	 * (Gemini, GPT-4o-mini, etc.) do not accept the reasoning parameter
	 * and return a 400 error when it is present.
	 */
	private const DEFAULT_REASONING_EFFORT = null;

	/**
	 * Default model for OpenRouter fallback.
	 *
	 * llama-4-scout benchmarked with 100% accuracy across all 5 prompt types,
	 * fast response times (~750ms), and is widely available including via ZDR endpoints.
	 */
	private const OPENROUTER_DEFAULT_MODEL = 'meta-llama/llama-4-scout';

	/**
	 * Returns the technique's unique identifier.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'ai';
	}

	/**
	 * Checks if AI classification is enabled for a given form.
	 *
	 * Requires both the technique to be enabled AND an AI provider
	 * to be available (WP AI Client or OpenRouter API key).
	 *
	 * Uses wp_supports_ai() as a lightweight check (constant + filter,
	 * no prompt builder instantiation) for the WP AI Client path.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return bool
	 */
	public function is_enabled( array $form ): bool {
		if ( ! parent::is_enabled( $form ) ) {
			return false;
		}

		$provider_mode = Settings::get( 'ai_provider', 'auto' );

		// Auto mode: check WP AI Client availability (lightweight).
		if ( $provider_mode === 'auto' && function_exists( 'wp_supports_ai' ) && wp_supports_ai() ) {
			return true;
		}

		// OpenRouter mode or auto fallback: check if API key is configured.
		$key = Settings::get( 'ai_api_key', '' );
		return ! empty( $key );
	}

	/**
	 * Evaluates a submission using AI Classification.
	 *
	 * The AI returns a spam probability (0.0–1.0). This is compared against
	 * the ai_confidence_threshold setting to produce a pass/fail verdict.
	 * The raw probability is preserved as the confidence score for admin display.
	 *
	 * @param Checkable_Context $context The submission metadata.
	 *
	 * @return Technique_Result
	 */
	public function evaluate( Checkable_Context $context ): Technique_Result {
		$form = $context->get_form();
		try {
			$provider  = $this->create_provider( $form );
			$system    = Prompt_Builder::build_system( $context, $form );
			$user      = Prompt_Builder::build_user( $context, $form );
			$timeout   = (int) Settings::get( 'ai_timeout', 10 );
			$threshold = $this->resolve_threshold( $form );

			$response = $provider->classify( $system, $user, $timeout );

			$probability = $response->get_spam_probability();
			$is_spam     = $probability >= $threshold;

			$signals = [];
			if ( $is_spam ) {
				$signals[] = 'ai_spam_' . $response->get_reason();
			}

			$usage = $response->get_usage();

			// Capture the full request body from the provider for dev-tools display.
			$raw_request = method_exists( $provider, 'get_last_request_body' )
				? $provider->get_last_request_body()
				: null;

			return new Technique_Result(
				$is_spam,
				$signals,
				[
					'provider'          => $provider->get_name(),
					'model'             => $response->get_model(),
					'raw_probability'   => $probability,
					'threshold'         => $threshold,
					'reason'            => $response->get_reason(),
					'rationale'         => $response->get_rationale(),
					'latency_ms'        => $response->get_latency_ms(),
					'prompt_tokens'     => $usage['prompt_tokens'] ?? null,
					'completion_tokens' => $usage['completion_tokens'] ?? null,
					'total_tokens'      => $usage['total_tokens'] ?? null,
					'cost'              => $response->get_cost(),
					'raw_response'      => $response->get_raw_response(),
					'raw_request'       => $raw_request,
				]
			);
		} catch ( AI_Exception $e ) {
			$error_detail = sprintf(
				'AI classification failed: %s (HTTP %s)',
				$e->getMessage(),
				$e->getCode() ?: 'N/A'
			);

			// AI failure is non-fatal — skip gracefully.
			PDM_Antispam()->log_error(
				sprintf( '%s(): %s', __METHOD__, $error_detail )
			);

			$raw          = $e->get_raw_response();
			$skipped_meta = [
				'error_message' => $error_detail,
				'hints'         => $this->diagnose_empty_response( $raw ),
			];

			if ( $raw !== null ) {
				$skipped_meta['raw_response'] = $raw;
			}

			// Capture the request body even on failure, so dev-tools can show what was sent.
			$raw_request = isset( $provider ) && method_exists( $provider, 'get_last_request_body' )
				? $provider->get_last_request_body()
				: null;
			if ( $raw_request !== null ) {
				$skipped_meta['raw_request'] = $raw_request;
			}

			return Technique_Result::skipped( 'ai_error', $skipped_meta );
		}
	}

	/**
	 * Formats a one-line headline for AI classification results.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return string
	 */
	public function format_headline( array $result_data ): string {
		$metadata = $result_data['metadata'] ?? [];

		if ( ! empty( $metadata['skipped'] ) ) {
			$signals = $result_data['signals'] ?? [];
			return str_replace( 'skipped_', '', $signals[0] ?? 'skipped' );
		}

		$probability = $metadata['raw_probability'] ?? null;
		if ( $probability === null ) {
			return ( $result_data['is_spam'] ?? false ) ? 'flagged' : 'ok';
		}

		return sprintf( '%d%% spam probability', (int) round( $probability * 100 ) );
	}

	/**
	 * Formats detail key-value pairs for AI expanded display.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string>
	 */
	public function format_details( array $result_data ): array {
		$metadata = $result_data['metadata'] ?? [];
		$details  = [];

		// Surface error details when AI was skipped due to an exception.
		if ( ! empty( $metadata['error_message'] ) ) {
			$details['Error'] = $metadata['error_message'];

			if ( ! empty( $metadata['hints'] ) ) {
				$details['Hints'] = implode( ' · ', $metadata['hints'] );
			}

			return $details;
		}

		if ( isset( $metadata['raw_probability'] ) ) {
			$details['Spam Probability'] = sprintf( '%d%%', (int) round( $metadata['raw_probability'] * 100 ) );
		}
		if ( isset( $metadata['threshold'] ) ) {
			$details['Threshold'] = sprintf( '%d%%', (int) round( $metadata['threshold'] * 100 ) );
		}
		if ( isset( $metadata['reason'] ) ) {
			$details['Reason'] = $metadata['reason'];
		}
		if ( isset( $metadata['rationale'] ) ) {
			$details['Rationale'] = $metadata['rationale'];
		}
		if ( isset( $metadata['provider'] ) ) {
			$details['Provider'] = $metadata['provider'];
		}
		if ( isset( $metadata['model'] ) ) {
			$details['Model'] = $metadata['model'];
		}
		if ( isset( $metadata['latency_ms'] ) ) {
			$details['Latency'] = $metadata['latency_ms'] . 'ms';
		}
		if ( isset( $metadata['cost'] ) ) {
			$cost_str = '$' . number_format( $metadata['cost'], 6 );
			if ( isset( $metadata['total_tokens'] ) ) {
				$cost_str .= sprintf( ' (%d tokens)', $metadata['total_tokens'] );
			}
			$details['Cost'] = $cost_str;
		}

		return $details;
	}

	/**
	 * Returns AI meta keys to denormalize for dashboard queries.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string>
	 */
	public function get_dashboard_meta( array $result_data ): array {
		$metadata = $result_data['metadata'] ?? [];
		$meta     = [];

		if ( isset( $metadata['latency_ms'] ) ) {
			$meta['gfsh_ai_latency_ms'] = (string) $metadata['latency_ms'];
		}
		if ( isset( $metadata['cost'] ) ) {
			$meta['gfsh_ai_cost'] = (string) $metadata['cost'];
		}

		return $meta;
	}

	/**
	 * Creates the AI provider based on available capabilities.
	 *
	 * Tries WP AI Client first (WP 7.0+), falls back to direct
	 * OpenRouter HTTP. The resolved provider is passed through the
	 * `gfsh_ai_provider` filter for per-form overrides.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return AI_Provider
	 *
	 * @throws AI_Exception If no AI provider is available.
	 */
	private function create_provider( array $form ): AI_Provider {
		$provider_mode = Settings::get( 'ai_provider', 'auto' );

		/**
		 * Filters the maximum completion tokens sent to the AI provider.
		 *
		 * @since 1.0
		 *
		 * @param int $max_tokens Maximum tokens. Default 1000.
		 */
		$max_tokens = (int) apply_filters( 'gfsh_ai_max_tokens', self::DEFAULT_MAX_TOKENS );

		/**
		 * Filters the reasoning effort level sent to the AI provider.
		 *
		 * Accepts 'low', 'medium', 'high', or null to omit the reasoning
		 * parameter entirely. Defaults to null — most modern models do not
		 * accept this parameter and return a 400 error when it is present.
		 * Only set this for dedicated reasoning models (e.g. o3-mini).
		 *
		 * @since 1.0
		 *
		 * @param string|null $reasoning_effort Effort level. Default null.
		 */
		$reasoning_effort = apply_filters( 'gfsh_ai_reasoning_effort', self::DEFAULT_REASONING_EFFORT );
		$reasoning_effort = $reasoning_effort !== null ? (string) $reasoning_effort : null;

		// 1. Try WP AI Client (WP 7.0+) when mode is 'auto'.
		if ( $provider_mode === 'auto' && function_exists( 'wp_ai_client_prompt' ) ) {
			$builder = wp_ai_client_prompt();

			if ( $builder->is_supported_for_text_generation() ) {
				// Pass explicit model ID (or null for auto-cheapest selection).
				$user_model = Settings::get( 'ai_model', '' );
				$provider   = new WP_AI_Client_Provider( $user_model ?: null, $max_tokens, $reasoning_effort );

				/**
				 * Filters the AI provider used for spam classification.
				 *
				 * Allows overriding the provider on a per-form basis, e.g., to use
				 * a zero-data-retention model for forms handling sensitive data.
				 *
				 * @since 1.0
				 *
				 * @param AI_Provider $provider The resolved AI provider instance.
				 * @param array       $form     The GF form array.
				 */
				return apply_filters( 'gfsh_ai_provider', $provider, $form );
			}
		}

		// 2. Fallback: direct HTTP to OpenRouter.
		$api_key = Settings::get( 'ai_api_key', '' );
		if ( empty( $api_key ) ) {
			throw new AI_Exception(
				'No AI provider available: WP AI Client not supported and no OpenRouter API key configured.'
			);
		}

		$model    = Settings::get( 'ai_model', '' ) ?: self::OPENROUTER_DEFAULT_MODEL;
		$zdr      = (bool) Settings::get( 'ai_zdr', '0' );
		$provider = new OpenRouter_Provider( $api_key, $model, $zdr, $max_tokens, $reasoning_effort );

		/** This filter is documented above. */
		return apply_filters( 'gfsh_ai_provider', $provider, $form );
	}

	/**
	 * Diagnoses why an AI response was empty and returns actionable hints.
	 *
	 * Inspects the raw API response to detect common failure patterns:
	 * - finish_reason=length: model hit max_tokens (especially with reasoning models)
	 * - reasoning tokens consumed all budget: increase max_tokens or disable reasoning
	 * - content=null with reasoning present: model is a reasoning model, needs different config
	 * - api-level error object: surfaces the error message directly
	 *
	 * @param array<string, mixed>|null $raw The decoded API response, or null if unavailable.
	 *
	 * @return string[] List of human-readable hint strings.
	 */
	private function diagnose_empty_response( ?array $raw ): array {
		if ( $raw === null ) {
			return [];
		}

		$hints         = [];
		$choice        = $raw['choices'][0] ?? [];
		$finish_reason = $choice['finish_reason'] ?? '';
		$message       = $choice['message'] ?? [];
		$usage         = $raw['usage'] ?? [];
		$model         = $raw['model'] ?? '';

		// API-level error (e.g. rate limit, invalid model).
		if ( ! empty( $raw['error'] ) ) {
			$api_error = is_array( $raw['error'] )
				? ( $raw['error']['message'] ?? wp_json_encode( $raw['error'] ) )
				: (string) $raw['error'];
			$hints[]   = 'API error: ' . $api_error;
			return $hints;
		}

		// Detect reasoning model: has reasoning_details or reasoning_tokens > 0.
		$reasoning_tokens = (int) ( $usage['completion_tokens_details']['reasoning_tokens'] ?? 0 );
		$has_reasoning    = ! empty( $message['reasoning_details'] ) || $reasoning_tokens > 0;

		$non_reasoning_models = 'google/gemini-2.5-flash, google/gemini-2.0-flash, openai/gpt-4o-mini, or anthropic/claude-3-5-haiku-latest';

		if ( $finish_reason === 'length' ) {
			if ( $has_reasoning ) {
				$hints[] = sprintf(
					'Reasoning model used %d of %d tokens on internal reasoning, leaving none for the response content.',
					$reasoning_tokens,
					(int) ( $usage['completion_tokens'] ?? 0 )
				);
				$hints[] = sprintf( 'Fix: switch to a non-reasoning model (%s), or increase the AI Timeout setting to allow a higher token budget.', $non_reasoning_models );
			} else {
				$hints[] = 'Response was cut off at the max_tokens limit. The model did not finish its output.';
				$hints[] = sprintf( 'Fix: switch to a faster model (%s) or increase the AI Timeout setting.', $non_reasoning_models );
			}
		} elseif ( $has_reasoning && empty( $message['content'] ) ) {
			$hints[] = 'Reasoning model returned no content — it may have exhausted its token budget on internal reasoning.';
			$hints[] = sprintf( 'Fix: switch to a non-reasoning model (%s).', $non_reasoning_models );
		}

		if ( ! empty( $model ) ) {
			$hints[] = 'Model used: ' . $model;
		}

		return $hints;
	}

	/**
		* Resolves the AI confidence threshold for the given form.
		*
		* Priority:
		* 1. Comment-specific option (when form is a synthetic comment form)
		* 2. Per-form override (GF form settings)
		* 3. Global plugin setting
		*
		* @param array $form The form array.
		*
		* @return float Threshold between 0.0 and 1.0.
		*/
	private function resolve_threshold( array $form ): float {
		// Comment context uses a synthetic form ID.
		if ( ( $form['id'] ?? 0 ) === Comment_Context::COMMENT_FORM_ID ) {
			return Comment_Settings::get_ai_confidence_threshold();
		}

		// GF form: check per-form override, then global.
		return (float) Settings::get_for_form( 'ai_confidence_threshold', $form, '0.50' );
	}
}
