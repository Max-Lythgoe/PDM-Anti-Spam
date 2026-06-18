<?php
/**
 * WP AI Client provider.
 *
 * Wraps the WordPress 7.0 AI Client API (wp_ai_client_prompt()) behind
 * the AI_Provider interface. Only instantiated when WP 7.0+ is detected
 * and is_supported_for_text_generation() returns true.
 *
 * Model selection strategy:
 * - If a specific model ID is given, resolve it from the registry via
 *   using_model() (bypasses SDK matching entirely).
 * - Otherwise, find the cheapest available model via pattern matching
 *   on model IDs (haiku, flash, mini, etc.) and use using_model().
 * - If no cheap model found, let the SDK pick (sdk_default).
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\AI;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

/**
 * AI provider backed by the WP 7.0 AI Client.
 *
 * Uses wp_ai_client_prompt() internally. Provider routing and key
 * management are handled automatically by the Connectors API.
 */
class WP_AI_Client_Provider implements AI_Provider {

	/**
	 * Explicit model ID to use, or null to auto-select cheapest.
	 *
	 * @var string|null
	 */
	private ?string $model_id;

	/**
	 * The last request body captured before sending, for dev-tools debugging.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $last_request_body = null;

	/**
	 * Maximum tokens to request in the completion.
	 *
	 * @var int
	 */
	private int $max_tokens;

	/**
	 * Reasoning effort level ('low', 'medium', 'high'), or null to omit.
	 *
	 * Defaults to null — most modern models do not accept this parameter
	 * and return a 400 error when it is present.
	 *
	 * @var string|null
	 */
	private ?string $reasoning_effort;

	/**
	 * Cheap model ID patterns, in priority order (cheapest first).
	 *
	 * Matched via str_contains() against the full model ID from the registry.
	 * Order matters: haiku/flash-lite/flash before larger models.
	 *
	 * @var string[]
	 */
	private const CHEAP_PATTERNS = [
		'haiku',
		'flash-lite',
		'flash',
		'mini',
		'nano',
		'lite',
		'scout',
	];

	/**
	 * @param string|null $model_id         Explicit model ID, or null to auto-select cheapest.
	 * @param int         $max_tokens        Maximum completion tokens.
	 * @param string|null $reasoning_effort  Reasoning effort ('low'|'medium'|'high'), or null to omit.
	 */
	public function __construct( ?string $model_id = null, int $max_tokens = 1000, ?string $reasoning_effort = null ) {
		$this->model_id         = $model_id;
		$this->max_tokens       = $max_tokens;
		$this->reasoning_effort = $reasoning_effort;
	}

	/**
	 * Returns the provider's identifier.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'wp_ai_client';
	}

	/**
	 * Sends a classification prompt to the WP AI Client and returns the response.
	 *
	 * @param string $system  The system instruction (rules, guidance, format).
	 * @param string $user    The user message (submission data to classify).
	 * @param int    $timeout Request timeout in seconds.
	 *
	 * @return AI_Response The parsed AI response.
	 *
	 * @throws AI_Exception If the API call fails.
	 */
	public function classify( string $system, string $user, int $timeout = 10 ): AI_Response {
		$start_time = microtime( true );

		// Reset the captured request body for this call.
		$this->last_request_body = null;

		// Build the WP AI Client prompt.
		$builder = wp_ai_client_prompt( $user )
			->using_system_instruction( $system )
			->using_max_tokens( $this->max_tokens );

		// Pass reasoning effort via a custom ModelConfig option so that
		// OpenAI-compatible providers (e.g. OpenRouter via WP Connectors) use
		// the configured reasoning level. Omitted when reasoning_effort is null.
		if ( $this->reasoning_effort !== null ) {
			$model_config = new ModelConfig();
			$model_config->setCustomOption( 'reasoning', [ 'effort' => $this->reasoning_effort ] );
			$builder->using_model_config( $model_config );
		}

		$builder->using_request_options(
			RequestOptions::fromArray( [
				RequestOptions::KEY_TIMEOUT => $timeout,
			] )
		);

		// Resolve model and apply to builder.
		[ $resolved_model, $resolve_trace ] = $this->resolve_model_with_trace();
		if ( $resolved_model ) {
			$builder->using_model( $resolved_model );
		}

		// Hook into the WP AI Client event to capture the resolved model and messages
		// before the HTTP request is sent. This gives us the full request body for
		// dev-tools debugging, including which model was actually selected and how.
		$capture_request = function ( $event ) use ( $resolve_trace, $system ): void {
			$model    = $event->getModel();
			$model_id = $model->metadata()->getId();

			// Prepend the system instruction as a synthetic 'system' message.
			// The WP AI Client passes system instructions via ModelConfig (not the
			// messages array), so they are not available via $event->getMessages().
			// We capture $system directly from the classify() call scope instead.
			$messages = [
				[
					'role'    => 'system',
					'content' => $system,
				],
			];

			// Append the user messages from the event (role is always 'user' here;
			// MessageRoleEnum only has USER and MODEL — no SYSTEM).
			foreach ( $event->getMessages() as $msg ) {
				$parts = $msg->getParts();
				$texts = [];
				foreach ( $parts as $part ) {
					if ( $part->getType()->isText() ) {
						$texts[] = $part->getText();
					}
				}
				$messages[] = [
					'role'    => 'user',
					'content' => implode( "\n", $texts ),
				];
			}

			// Derive a short provider name from the class (e.g. AnthropicTextGenerationModel → anthropic).
			$provider_class = get_class( $model );
			$short_class    = substr( $provider_class, (int) strrpos( $provider_class, '\\' ) + 1 );

			$this->last_request_body = array_merge(
				[
					'model'          => $model_id,
					'provider_class' => $short_class,
				],
				$resolve_trace,
				[
					'messages' => $messages,
				]
			);
		};

		add_action( 'wp_ai_client_before_generate_result', $capture_request );

		$result = $builder->generate_result();

		// Clean up the event hook — it's only needed for this single call.
		remove_action( 'wp_ai_client_before_generate_result', $capture_request );

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $result ) ) {
			$error_data  = $result->get_error_data();
			$status_code = is_array( $error_data ) ? ( $error_data['status'] ?? 0 ) : 0;

			throw new AI_Exception(
				'WP AI Client error: ' . $result->get_error_message(),
				(int) $status_code
			);
		}

		// toText() throws RuntimeException if no text content found,
		// so we catch it and convert to our AI_Exception.
		try {
			$text = $result->toText();
		} catch ( \RuntimeException $e ) {
			throw new AI_Exception( 'Empty response from WP AI Client: ' . $e->getMessage() );
		}

		// Extract metadata from the result object.
		// These are never null — they always return their respective DTOs.
		$token_usage = $result->getTokenUsage();
		$model_meta  = $result->getModelMetadata();

		$usage = [
			'prompt_tokens'     => $token_usage->getPromptTokens(),
			'completion_tokens' => $token_usage->getCompletionTokens(),
			'total_tokens'      => $token_usage->getTotalTokens(),
		];

		$model_name = $model_meta->getId();

		// Parse the JSON response into our AI_Response value object.
		$ai_response = AI_Response::from_json( $text );

		return new AI_Response(
			$ai_response->get_spam_probability(),
			$ai_response->get_reason(),
			$text,
			new AI_Response_Meta(
				$usage,
				null, // WP AI Client doesn't expose cost data.
				$latency_ms,
				$model_name
			),
			$ai_response->get_rationale()
		);
	}

	/**
	 * Returns the last request body captured before sending, for dev-tools debugging.
	 *
	 * Contains the resolved model ID, provider class, and messages array.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_last_request_body(): ?array {
		return $this->last_request_body;
	}

	/**
	 * Resolves a model instance to use and returns a diagnostic trace.
	 *
	 * Priority:
	 * 1. Explicit model ID → look up in registry across all providers.
	 * 2. Auto → find cheapest model via CHEAP_PATTERNS str_contains matching.
	 * 3. None found → return null, let SDK pick (sdk_default).
	 *
	 * @return array{0: \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|null, 1: array<string, mixed>}
	 */
	private function resolve_model_with_trace(): array {
		$registry     = AiClient::defaultRegistry();
		$provider_ids = $registry->getRegisteredProviderIds();

		$registered_providers = array_values( (array) $provider_ids );

		// 1. Explicit model ID — exact registry lookup.
		if ( $this->model_id !== null ) {
			foreach ( $provider_ids as $provider_id ) {
				try {
					$model = $registry->getProviderModel( $provider_id, $this->model_id );
					return [
						$model,
						[
							'model_selection'      => 'explicit',
							'resolve_note'         => "User-selected model \"{$this->model_id}\" matched via provider \"{$provider_id}\".",
							'registered_providers' => $registered_providers,
						],
					];
				} catch ( \Exception $e ) {
					continue;
				}
			}

			// Explicit ID given but not found in registry — fall through to auto.
			$not_found_note = "User-selected model \"{$this->model_id}\" not found in registry. Falling back to cheapest auto.";
		}

		// 2. Auto — pattern-match cheapest model across all providers.
		// modelMetadataDirectory() is a static method on the provider class, not on the registry.
		foreach ( self::CHEAP_PATTERNS as $pattern ) {
			foreach ( $provider_ids as $provider_id ) {
				try {
					$class_name = $registry->getProviderClassName( $provider_id );
					$dir        = $class_name::modelMetadataDirectory();
					foreach ( $dir->listModelMetadata() as $meta ) {
						if ( str_contains( $meta->getId(), $pattern ) ) {
							$model = $registry->getProviderModel( $provider_id, $meta->getId() );
							return [
								$model,
								[
									'model_selection'      => 'auto_cheapest',
									'resolve_note'         => "Auto-selected cheapest model \"{$meta->getId()}\" via provider \"{$provider_id}\" (pattern: \"{$pattern}\")." . ( isset( $not_found_note ) ? ' ' . $not_found_note : '' ),
									'registered_providers' => $registered_providers,
								],
							];
						}
					}
				} catch ( \Exception $e ) {
					continue;
				}
			}
		}

		// 3. Nothing found — SDK picks.
		return [
			null,
			[
				'model_selection'      => 'sdk_default',
				'resolve_note'         => 'No model resolved — SDK picks any compatible model.' . ( isset( $not_found_note ) ? ' ' . $not_found_note : '' ),
				'registered_providers' => $registered_providers,
			],
		];
	}
}
