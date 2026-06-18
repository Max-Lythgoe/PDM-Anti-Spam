<?php
/**
 * Benchmark result value object.
 *
 * Captures the outcome of a single classify() call during benchmarking.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\CLI;

/**
 * Immutable result from a single benchmark run.
 */
class Benchmark_Result {

	/**
	 * Model ID (e.g. 'google/gemini-2.5-flash-lite').
	 *
	 * @var string
	 */
	public string $model;

	/**
	 * Reasoning effort level used ('low', 'medium', 'high', or 'none').
	 *
	 * @var string
	 */
	public string $reasoning_effort;

	/**
	 * Prompt type (e.g. 'ham', 'spam', 'partial', 'large-ham', 'large-spam').
	 *
	 * @var string
	 */
	public string $prompt_type;

	/**
	 * Run number (1-based).
	 *
	 * @var int
	 */
	public int $run_num;

	/**
	 * API call latency in milliseconds.
	 *
	 * @var int|null
	 */
	public ?int $latency_ms;

	/**
	 * Prompt token count.
	 *
	 * @var int
	 */
	public int $prompt_tokens;

	/**
	 * Completion token count.
	 *
	 * @var int
	 */
	public int $completion_tokens;

	/**
	 * Total token count.
	 *
	 * @var int
	 */
	public int $total_tokens;

	/**
	 * Cost in USD for this call, or null if not available.
	 *
	 * @var float|null
	 */
	public ?float $cost_usd;

	/**
	 * Spam probability returned by the model (0.0–1.0).
	 *
	 * @var float
	 */
	public float $spam_probability;

	/**
	 * Reason code returned by the model.
	 *
	 * @var string
	 */
	public string $reason;

	/**
	 * Whether the classification was correct for this prompt type.
	 *
	 * @var bool
	 */
	public bool $correct;

	/**
	 * Error message if the call failed, or null on success.
	 *
	 * @var string|null
	 */
	public ?string $error;

	/**
	 * @param string      $model             Model ID.
	 * @param string      $reasoning_effort  Reasoning effort level.
	 * @param string      $prompt_type       Prompt type.
	 * @param int         $run_num           Run number (1-based).
	 * @param int|null    $latency_ms        Latency in ms.
	 * @param int         $prompt_tokens     Prompt token count.
	 * @param int         $completion_tokens Completion token count.
	 * @param int         $total_tokens      Total token count.
	 * @param float|null  $cost_usd          Cost in USD.
	 * @param float       $spam_probability  Spam probability.
	 * @param string      $reason            Reason code.
	 * @param bool        $correct           Whether classification was correct.
	 * @param string|null $error             Error message if failed.
	 */
	public function __construct(
		string $model,
		string $reasoning_effort,
		string $prompt_type,
		int $run_num,
		?int $latency_ms,
		int $prompt_tokens,
		int $completion_tokens,
		int $total_tokens,
		?float $cost_usd,
		float $spam_probability,
		string $reason,
		bool $correct,
		?string $error = null
	) {
		$this->model             = $model;
		$this->reasoning_effort  = $reasoning_effort;
		$this->prompt_type       = $prompt_type;
		$this->run_num           = $run_num;
		$this->latency_ms        = $latency_ms;
		$this->prompt_tokens     = $prompt_tokens;
		$this->completion_tokens = $completion_tokens;
		$this->total_tokens      = $total_tokens;
		$this->cost_usd          = $cost_usd;
		$this->spam_probability  = $spam_probability;
		$this->reason            = $reason;
		$this->correct           = $correct;
		$this->error             = $error;
	}

	/**
	 * Creates a failed result (API error).
	 *
	 * @param string $model            Model ID.
	 * @param string $reasoning_effort Reasoning effort level.
	 * @param string $prompt_type      Prompt type.
	 * @param int    $run_num          Run number.
	 * @param string $error            Error message.
	 *
	 * @return self
	 */
	public static function failed(
		string $model,
		string $reasoning_effort,
		string $prompt_type,
		int $run_num,
		string $error
	): self {
		return new self(
			$model,
			$reasoning_effort,
			$prompt_type,
			$run_num,
			null,
			0,
			0,
			0,
			null,
			0.0,
			'error',
			false,
			$error
		);
	}

	/**
	 * Whether this result represents a failed API call.
	 *
	 * @return bool
	 */
	public function is_error(): bool {
		return $this->error !== null;
	}
}
