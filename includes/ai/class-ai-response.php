<?php
/**
 * AI classification response value object.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\AI;

use PDM_Antispam\AI\AI_Response_Meta;

/**
 * Immutable response from an AI classification request.
 */
class AI_Response {

	/**
	 * Spam probability from 0.0 (ham) to 1.0 (definitely spam).
	 *
	 * @var float
	 */
	private float $spam_probability;

	/**
	 * Reason code from the AI (e.g. 'ham', 'seo_spam', 'phishing').
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Optional free-text rationale from the AI explaining its decision.
	 *
	 * @var string|null
	 */
	private ?string $rationale;

	/**
	 * Raw response text from the AI for debugging.
	 *
	 * @var string
	 */
	private string $raw_response;

	/**
	 * API call metadata (tokens, cost, latency, model).
	 *
	 * @var AI_Response_Meta
	 */
	private AI_Response_Meta $meta;

	/**
	 * @param float                  $spam_probability Spam probability (0.0–1.0).
	 * @param string                 $reason           Reason code.
	 * @param string                 $raw_response     Raw API response text.
	 * @param AI_Response_Meta|null  $meta             API call metadata.
	 * @param string|null            $rationale        Optional free-text explanation.
	 */
	public function __construct(
		float $spam_probability,
		string $reason,
		string $raw_response = '',
		?AI_Response_Meta $meta = null,
		?string $rationale = null
	) {
		$this->spam_probability = max( 0.0, min( 1.0, $spam_probability ) );
		$this->reason           = $reason;
		$this->raw_response     = $raw_response;
		$this->meta             = $meta ?? new AI_Response_Meta();
		$this->rationale        = $rationale !== '' ? $rationale : null;
	}

	/**
	 * Parses a JSON response string into an AI_Response.
	 *
	 * Expected format: {"spam_probability": 0.0-1.0, "reason": "code"}
	 *
	 * @param string $json The JSON response from the AI.
	 *
	 * @return self
	 *
	 * @throws AI_Exception If the JSON is invalid or missing required fields.
	 */
	public static function from_json( string $json ): self {
		// Strip markdown code fences if present.
		$clean = preg_replace( '/^```(?:json)?\s*|\s*```$/s', '', trim( $json ) );

		$data = json_decode( $clean, true );

		if ( ! is_array( $data ) ) {
			throw new AI_Exception( 'Invalid JSON response from AI: ' . substr( $json, 0, 200 ) );
		}

		if ( ! isset( $data['spam_probability'] ) ) {
			throw new AI_Exception( 'Missing spam_probability in AI response' );
		}

		$rationale = isset( $data['rationale'] ) ? (string) $data['rationale'] : null;

		return new self(
			(float) $data['spam_probability'],
			(string) ( $data['reason'] ?? 'unknown' ),
			$json,
			null,
			$rationale
		);
	}

	/**
	 * Gets the spam probability.
	 *
	 * @return float
	 */
	public function get_spam_probability(): float {
		return $this->spam_probability;
	}

	/**
	 * Gets the reason code.
	 *
	 * @return string
	 */
	public function get_reason(): string {
		return $this->reason;
	}

	/**
	 * Gets the optional free-text rationale from the AI.
	 *
	 * @return string|null
	 */
	public function get_rationale(): ?string {
		return $this->rationale;
	}

	/**
	 * Gets the raw response text.
	 *
	 * @return string
	 */
	public function get_raw_response(): string {
		return $this->raw_response;
	}

	/**
	 * Gets the token usage counts.
	 *
	 * @return array<string, int>
	 */
	public function get_usage(): array {
		return $this->meta->get_usage();
	}

	/**
	 * Gets the cost in USD.
	 *
	 * @return float|null
	 */
	public function get_cost(): ?float {
		return $this->meta->get_cost();
	}

	/**
	 * Gets the API call latency in milliseconds.
	 *
	 * @return int|null
	 */
	public function get_latency_ms(): ?int {
		return $this->meta->get_latency_ms();
	}

	/**
	 * Gets the actual model used.
	 *
	 * @return string|null
	 */
	public function get_model(): ?string {
		return $this->meta->get_model();
	}

	/**
	 * Converts to array for logging.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$data = [
			'spam_probability' => $this->spam_probability,
			'reason'           => $this->reason,
		];

		if ( $this->rationale !== null ) {
			$data['rationale'] = $this->rationale;
		}

		$usage = $this->meta->get_usage();
		if ( ! empty( $usage ) ) {
			$data['usage'] = $usage;
		}

		$cost = $this->meta->get_cost();
		if ( $cost !== null ) {
			$data['cost'] = $cost;
		}

		$latency_ms = $this->meta->get_latency_ms();
		if ( $latency_ms !== null ) {
			$data['latency_ms'] = $latency_ms;
		}

		$model = $this->meta->get_model();
		if ( $model !== null ) {
			$data['model'] = $model;
		}

		return $data;
	}
}
