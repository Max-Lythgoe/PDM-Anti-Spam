<?php
/**
 * OpenRouter model discovery.
 *
 * Fetches the public OpenRouter /models API and filters candidates
 * suitable for spam classification benchmarking.
 *
 * No API key required — the models endpoint is public.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\CLI;

/**
 * Discovers and filters models from the OpenRouter API.
 */
class Model_Discovery {

	/**
	 * OpenRouter models API endpoint.
	 */
	private const MODELS_URL = 'https://openrouter.ai/api/v1/models';

	/**
	 * Minimum context length required (must fit our largest prompt ~2k chars).
	 */
	private const MIN_CONTEXT_LENGTH = 4096;

	/**
	 * Fetches models from OpenRouter and applies filter criteria.
	 *
	 * Filter criteria:
	 * - Accepts text input (may also accept image, but must include text)
	 * - Outputs text
	 * - Supports max_tokens parameter
	 * - Has a non-zero completion price (excludes free/experimental)
	 * - Completion price within --max-cost ($/M tokens), if specified
	 * - Supports reasoning parameter, if --require-reasoning is set
	 * - ID starts with one of the --providers prefixes, if specified
	 * - Context length >= MIN_CONTEXT_LENGTH
	 *
	 * @param array $filters Filter options: max_cost (float), require_reasoning (bool), providers (string[]).
	 *
	 * @return array<int, array<string, mixed>> Filtered model records, sorted by completion price asc.
	 *
	 * @throws \RuntimeException If the API request fails.
	 */
	public static function fetch( array $filters ): array {
		$response = wp_remote_get(
			self::MODELS_URL,
			[
				'timeout' => 15,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'Failed to fetch OpenRouter models: ' . $response->get_error_message()
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new \RuntimeException(
				sprintf( 'OpenRouter models API returned HTTP %d', $status )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['data'] ) ) {
			throw new \RuntimeException( 'Invalid response from OpenRouter models API' );
		}

		$models = $body['data'];

		$max_cost          = (float) $filters['max_cost'];
		$require_reasoning = (bool) $filters['require_reasoning'];
		$providers         = (array) $filters['providers'];

		$filtered = array_filter( $models, function ( array $m ) use ( $max_cost, $require_reasoning, $providers ): bool {
			$arch   = $m['architecture'] ?? [];
			$params = $m['supported_parameters'] ?? [];

			// Must accept text input.
			$input_mods = $arch['input_modalities'] ?? [];
			if ( ! in_array( 'text', $input_mods, true ) ) {
				return false;
			}

			// Must output text.
			$output_mods = $arch['output_modalities'] ?? [];
			if ( ! in_array( 'text', $output_mods, true ) ) {
				return false;
			}

			// Must support max_tokens.
			if ( ! in_array( 'max_tokens', $params, true ) ) {
				return false;
			}

			// Must have a non-zero completion price.
			$completion_price = (float) ( $m['pricing']['completion'] ?? 0 );
			if ( $completion_price <= 0 ) {
				return false;
			}

			// Must be within max cost ($/M tokens), if specified.
			if ( $max_cost > 0 ) {
				$completion_per_million = $completion_price * 1_000_000;
				if ( $completion_per_million > $max_cost ) {
					return false;
				}
			}

			// Must support reasoning, if required.
			if ( $require_reasoning && ! in_array( 'reasoning', $params, true ) ) {
				return false;
			}

			// Must have sufficient context length.
			if ( (int) ( $m['context_length'] ?? 0 ) < self::MIN_CONTEXT_LENGTH ) {
				return false;
			}

			// Must match one of the provider prefixes, if specified.
			if ( ! empty( $providers ) ) {
				$model_id = (string) ( $m['id'] ?? '' );
				$matched  = false;
				foreach ( $providers as $prefix ) {
					if ( str_starts_with( $model_id, $prefix . '/' ) ) {
						$matched = true;
						break;
					}
				}
				if ( ! $matched ) {
					return false;
				}
			}

			return true;
		} );

		// Sort by completion price ascending (cheapest first).
		usort( $filtered, function ( array $a, array $b ): int {
			$price_a = (float) ( $a['pricing']['completion'] ?? 0 );
			$price_b = (float) ( $b['pricing']['completion'] ?? 0 );
			return $price_a <=> $price_b;
		} );

		return $filtered;
	}

	/**
	 * Returns whether a model supports the reasoning parameter.
	 *
	 * @param array<string, mixed> $model Model record from the API.
	 *
	 * @return bool
	 */
	public static function supports_reasoning( array $model ): bool {
		return in_array( 'reasoning', $model['supported_parameters'] ?? [], true );
	}

	/**
	 * Returns the completion cost per million tokens for a model.
	 *
	 * @param array<string, mixed> $model Model record from the API.
	 *
	 * @return float
	 */
	public static function completion_cost_per_million( array $model ): float {
		return (float) ( $model['pricing']['completion'] ?? 0 ) * 1_000_000;
	}

	/**
	 * Formats a model record as a short summary string for display.
	 *
	 * @param array<string, mixed> $model Model record from the API.
	 *
	 * @return string e.g. "google/gemini-2.5-flash-lite ($0.40/M, reasoning)"
	 */
	public static function format_model_summary( array $model ): string {
		$id     = (string) ( $model['id'] ?? 'unknown' );
		$cost   = self::completion_cost_per_million( $model );
		$suffix = self::supports_reasoning( $model ) ? ', reasoning' : '';
		return sprintf( '%s ($%.2f/M%s)', $id, $cost, $suffix );
	}
}
