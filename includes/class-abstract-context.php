<?php
/**
 * Abstract base for checkable submission contexts.
 *
 * Provides the shared implementation of POST data access, client payload
 * decoding, PoW solution extraction, and server timing that both
 * Submission_Context and Comment_Context need.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam;

use PDM_Antispam\Contracts\Checkable_Context;

/**
 * Base class for all checkable submission contexts.
 *
 * Subclasses must implement get_form_id(), get_form(), get_entry(),
 * and get_prompt_context() — the parts that differ between GF form
 * submissions and WordPress comments.
 */
abstract class Abstract_Context implements Checkable_Context {

	/**
	 * Hidden field name for the collector payload.
	 */
	public const PAYLOAD_FIELD = 'gfsh_payload';

	/**
	 * Server-side receive timestamp.
	 *
	 * @var float
	 */
	private float $server_receive_time;

	/**
	 * Decoded client payload (lazily populated).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $client_payload = null;

	/**
	 * Constructor. Records the server receive time.
	 */
	public function __construct() {
		$this->server_receive_time = microtime( true );
	}

	/**
	 * Gets a raw POST value.
	 *
	 * @param string $key     POST field name.
	 * @param mixed  $default Default value if not present.
	 *
	 * @return mixed
	 */
	public function get_post_value( string $key, $default = null ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled by caller (GF or WP comment system).
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Gets the decoded client payload from the collector.
	 *
	 * The payload is JSON-encoded, base64'd, and submitted in a hidden field.
	 * Returns an empty array if the payload is missing or malformed.
	 *
	 * @return array<string, mixed>
	 */
	public function get_client_payload(): array {
		if ( $this->client_payload !== null ) {
			return $this->client_payload;
		}

		$raw = $this->get_post_value( self::PAYLOAD_FIELD, '' );

		if ( empty( $raw ) ) {
			$this->client_payload = [];
			return $this->client_payload;
		}

		$decoded = base64_decode( $raw, true );

		if ( $decoded === false ) {
			$this->client_payload = [];
			return $this->client_payload;
		}

		$parsed = json_decode( $decoded, true );

		$this->client_payload = is_array( $parsed ) ? $parsed : [];
		return $this->client_payload;
	}

	/**
	 * Gets the PoW solution data from the client payload.
	 *
	 * @return array{challenge: string, signature: string, solution: string, solve_time_ms: int, is_fallback: bool, client_nonce: string}|null
	 */
	public function get_pow_solution(): ?array {
		$pow = $this->get_client_payload()['pow'] ?? null;

		if ( ! is_array( $pow ) ) {
			return null;
		}

		if ( ! isset( $pow['challenge'], $pow['signature'], $pow['solution'] ) ) {
			return null;
		}

		return [
			'challenge'     => (string) $pow['challenge'],
			'signature'     => (string) $pow['signature'],
			'solution'      => (string) $pow['solution'],
			'solve_time_ms' => (int) ( $pow['solve_time_ms'] ?? 0 ),
			'is_fallback'   => ! empty( $pow['is_fallback'] ),
			'client_nonce'  => (string) ( $pow['client_nonce'] ?? '' ),
		];
	}

	/**
	 * Gets the server-side receive timestamp.
	 *
	 * @return float Unix timestamp with microseconds.
	 */
	public function get_server_receive_time(): float {
		return $this->server_receive_time;
	}
}
