<?php
/**
 * OpenRouter AI provider.
 *
 * Standalone provider that sends classification prompts to OpenRouter's
 * chat completions API. Supports every major model (Gemini, OpenAI,
 * Anthropic, open-source) through a single API key.
 *
 * OpenRouter-specific features:
 * - Attribution headers (HTTP-Referer, X-OpenRouter-Title)
 * - Cost parsing from the response envelope
 * - Zero Data Retention (ZDR) routing via the `provider` body key
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\AI;

/**
 * OpenRouter provider implementation.
 *
 * Implements the AI_Provider interface directly — no abstract parent class.
 * The request format is OpenAI-compatible at the base level but includes
 * OpenRouter-specific extensions (provider routing, ZDR, etc.).
 */
class OpenRouter_Provider implements AI_Provider {

	/**
	 * OpenRouter API base URL.
	 */
	private const BASE_URL = 'https://openrouter.ai/api/v1';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Whether to enforce Zero Data Retention routing.
	 *
	 * When true, requests are only routed to endpoints that do not
	 * store prompts or responses.
	 *
	 * @var bool
	 */
	private bool $zdr;

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
	 * The last request body sent to the API, for dev-tools debugging.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $last_request_body = null;

	/**
	 * @param string      $api_key          The OpenRouter API key.
	 * @param string      $model            The model name (e.g. 'google/gemini-2.5-flash').
	 * @param bool        $zdr              Whether to enforce Zero Data Retention.
	 * @param int         $max_tokens       Maximum completion tokens.
	 * @param string|null $reasoning_effort Reasoning effort ('low'|'medium'|'high'), or null to omit.
	 */
	public function __construct( string $api_key, string $model, bool $zdr = false, int $max_tokens = 1000, ?string $reasoning_effort = null ) {
		$this->api_key          = $api_key;
		$this->model            = $model;
		$this->zdr              = $zdr;
		$this->max_tokens       = $max_tokens;
		$this->reasoning_effort = $reasoning_effort;
	}

	/**
	 * Returns the provider identifier.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'openrouter';
	}

	/**
	 * Returns the HTTP headers for the API request.
	 *
	 * Includes OpenRouter-specific attribution headers.
	 *
	 * @return array<string, string>
	 */
	protected function get_headers(): array {
		return [
			'Content-Type'       => 'application/json',
			'Authorization'      => 'Bearer ' . $this->api_key,
			'HTTP-Referer'       => home_url(),
			'X-OpenRouter-Title' => 'PDM Anti-Spam',
		];
	}

	/**
	 * Builds the request body for the chat completions API.
	 *
	 * @param string $system The system instruction.
	 * @param string $user   The user message (submission data).
	 *
	 * @return array<string, mixed>
	 */
	protected function build_body( string $system, string $user ): array {
		$body = [
			'model'      => $this->model,
			'messages'   => [
				[
					'role'    => 'system',
					'content' => $system,
				],
				[
					'role'    => 'user',
					'content' => $user,
				],
			],
			'max_tokens' => $this->max_tokens,
		];

		if ( $this->reasoning_effort !== null ) {
			$body['reasoning'] = [ 'effort' => $this->reasoning_effort ];
		}

		if ( $this->zdr ) {
			$body['provider'] = [ 'zdr' => true ];
		}

		return $body;
	}

	/**
	 * Sends a classification prompt via the chat completions API.
	 *
	 * @param string $system  The system instruction (rules, guidance, format).
	 * @param string $user    The user message (submission data to classify).
	 * @param int    $timeout Request timeout in seconds.
	 *
	 * @return AI_Response
	 *
	 * @throws AI_Exception If the API call fails.
	 */
	public function classify( string $system, string $user, int $timeout = 10 ): AI_Response {
		$url  = self::BASE_URL . '/chat/completions';
		$body = $this->build_body( $system, $user );

		// Store for dev-tools debugging.
		$this->last_request_body = $body;

		$start_time = microtime( true );

		$response = wp_remote_post( $url, [
			'timeout' => $timeout,
			'headers' => $this->get_headers(),
			'body'    => wp_json_encode( $body ),
		] );

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			throw new AI_Exception(
				'openrouter API request failed: ' . $response->get_error_message()
			);
		}

		$status      = wp_remote_retrieve_response_code( $response );
		$body_string = wp_remote_retrieve_body( $response );

		$data = json_decode( $body_string, true );

		if ( $status < 200 || $status >= 300 ) {
			throw new AI_Exception(
				sprintf( 'openrouter API returned HTTP %d: %s', $status, substr( $body_string, 0, 200 ) ),
				$status,
				null,
				is_array( $data ) ? $data : null
			);
		}

		if ( ! is_array( $data ) ) {
			throw new AI_Exception( 'Invalid JSON from openrouter API' );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';

		if ( empty( $text ) ) {
			throw new AI_Exception( 'Empty response from openrouter API', 0, null, $data );
		}

		$usage = $this->parse_usage( $data );
		$model = isset( $data['model'] ) ? (string) $data['model'] : null;

		try {
			$ai_response = AI_Response::from_json( $text );
		} catch ( AI_Exception $e ) {
			// Re-throw with the full API response attached for dev-tools debugging.
			throw new AI_Exception( $e->getMessage(), $e->getCode(), $e, $data );
		}

		return new AI_Response(
			$ai_response->get_spam_probability(),
			$ai_response->get_reason(),
			$text,
			new AI_Response_Meta(
				$usage,
				$this->parse_cost( $data ),
				$latency_ms,
				$model
			),
			$ai_response->get_rationale()
		);
	}

	/**
	 * Returns the last request body sent to the API, for dev-tools debugging.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_last_request_body(): ?array {
		return $this->last_request_body;
	}

	/**
	 * Parses token usage from the API response.
	 *
	 * @param array<string, mixed> $data The decoded API response.
	 *
	 * @return array<string, int>
	 */
	protected function parse_usage( array $data ): array {
		$usage_raw = $data['usage'] ?? [];
		return [
			'prompt_tokens'     => (int) ( $usage_raw['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $usage_raw['completion_tokens'] ?? 0 ),
			'total_tokens'      => (int) ( $usage_raw['total_tokens'] ?? 0 ),
		];
	}

	/**
	 * Parses cost from OpenRouter's response envelope.
	 *
	 * OpenRouter includes a `cost` field in the usage data.
	 *
	 * @param array<string, mixed> $data The decoded API response.
	 *
	 * @return float|null
	 */
	protected function parse_cost( array $data ): ?float {
		$usage_raw = $data['usage'] ?? [];
		return isset( $usage_raw['cost'] ) ? (float) $usage_raw['cost'] : null;
	}
}
