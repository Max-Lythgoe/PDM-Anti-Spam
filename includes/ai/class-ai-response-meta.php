<?php
/**
 * AI API call metadata value object.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\AI;

/**
 * Immutable metadata from an AI API call.
 *
 * Captures operational details (tokens, cost, latency, model) that are
 * separate from the classification result (probability, reason).
 */
class AI_Response_Meta {

	/**
	 * Token usage counts.
	 *
	 * @var array<string, int>
	 */
	private array $usage;

	/**
	 * Cost in USD.
	 *
	 * @var float|null
	 */
	private ?float $cost;

	/**
	 * API call latency in milliseconds.
	 *
	 * @var int|null
	 */
	private ?int $latency_ms;

	/**
	 * Actual model used.
	 *
	 * @var string|null
	 */
	private ?string $model;

	/**
	 * @param array<string, int> $usage      Token usage counts.
	 * @param float|null         $cost       Cost in USD.
	 * @param int|null           $latency_ms API call latency in ms.
	 * @param string|null        $model      Actual model used.
	 */
	public function __construct(
		array $usage = [],
		?float $cost = null,
		?int $latency_ms = null,
		?string $model = null
	) {
		$this->usage      = $usage;
		$this->cost       = $cost;
		$this->latency_ms = $latency_ms;
		$this->model      = $model;
	}

	/**
	 * Gets the token usage counts.
	 *
	 * @return array<string, int>
	 */
	public function get_usage(): array {
		return $this->usage;
	}

	/**
	 * Gets the cost in USD.
	 *
	 * @return float|null
	 */
	public function get_cost(): ?float {
		return $this->cost;
	}

	/**
	 * Gets the API call latency in milliseconds.
	 *
	 * @return int|null
	 */
	public function get_latency_ms(): ?int {
		return $this->latency_ms;
	}

	/**
	 * Gets the actual model used.
	 *
	 * @return string|null
	 */
	public function get_model(): ?string {
		return $this->model;
	}
}
