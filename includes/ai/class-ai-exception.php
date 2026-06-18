<?php
/**
 * AI-specific exception.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\AI;

/**
 * Exception thrown when an AI API call fails.
 *
 * Contains the provider name and HTTP status code (if applicable)
 * for debugging and logging.
 */
class AI_Exception extends \RuntimeException {

	/**
	 * HTTP status code from the API response, if applicable.
	 *
	 * @var int
	 */
	private int $status_code;

	/**
	 * Raw decoded API response body, if available.
	 *
	 * Stored for dev-tools debugging — allows dumping the full response
	 * JSON when diagnosing empty/unexpected API responses.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $raw_response;

	/**
	 * @param string               $message      Error message.
	 * @param int                  $status_code  HTTP status code (0 if not applicable).
	 * @param \Throwable|null      $previous     Previous exception.
	 * @param array<string,mixed>|null $raw_response Raw decoded API response body.
	 */
	public function __construct( string $message, int $status_code = 0, ?\Throwable $previous = null, ?array $raw_response = null ) {
		$this->status_code  = $status_code;
		$this->raw_response = $raw_response;
		parent::__construct( $message, $status_code, $previous );
	}

	/**
	 * Gets the HTTP status code.
	 *
	 * @return int
	 */
	public function get_status_code(): int {
		return $this->status_code;
	}

	/**
	 * Gets the raw decoded API response body, if available.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_raw_response(): ?array {
		return $this->raw_response;
	}
}
