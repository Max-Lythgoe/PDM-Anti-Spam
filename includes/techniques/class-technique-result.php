<?php
/**
 * Result object returned by individual techniques.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Techniques;

/**
 * Immutable result from a single technique evaluation.
 *
 * Each technique returns a clear pass/fail verdict (is_spam).
 * No scores, no confidence — just a boolean decision plus
 * signal codes and metadata for logging/admin display.
 *
 * AI stores its probability in metadata['raw_probability'] for
 * admin display purposes only.
 */
class Technique_Result {

	/**
	 * Whether this technique considers the submission spam.
	 *
	 * @var bool
	 */
	private bool $is_spam;

	/**
	 * Signal codes describing what was detected.
	 *
	 * Examples: 'pow_missing', 'pow_expired', 'ai_spam_seo'.
	 *
	 * @var string[]
	 */
	private array $signals;

	/**
	 * Optional metadata for logging and debugging.
	 *
	 * Examples: ['solve_time_ms' => 42, 'difficulty' => 17]
	 * AI stores: ['raw_probability' => 0.85, 'reason' => 'seo_spam', ...]
	 *
	 * @var array<string, mixed>
	 */
	private array $metadata;

	/**
	 * @param bool                 $is_spam  Whether this technique flags the submission as spam.
	 * @param string[]             $signals  Signal codes.
	 * @param array<string, mixed> $metadata Optional metadata.
	 */
	public function __construct( bool $is_spam, array $signals = [], array $metadata = [] ) {
		$this->is_spam  = $is_spam;
		$this->signals  = $signals;
		$this->metadata = $metadata;
	}

	/**
	 * Creates a "skipped" result for when a technique can't evaluate.
	 *
	 * Not spam (benefit of the doubt).
	 *
	 * @param string               $reason         Why the technique was skipped.
	 * @param array<string, mixed> $extra_metadata Additional metadata (e.g. error_message).
	 *
	 * @return self
	 */
	public static function skipped( string $reason, array $extra_metadata = [] ): self {
		return new self( false, [ 'skipped_' . $reason ], array_merge( [ 'skipped' => true ], $extra_metadata ) );
	}

	/**
	 * Creates a clean (not spam) result.
	 *
	 * @param array<string, mixed> $metadata Optional metadata.
	 *
	 * @return self
	 */
	public static function clean( array $metadata = [] ): self {
		return new self( false, [], $metadata );
	}

	/**
	 * Creates a spam result (binary fail, e.g. PoW missing).
	 *
	 * @param string[]             $signals  Signal codes.
	 * @param array<string, mixed> $metadata Optional metadata.
	 *
	 * @return self
	 */
	public static function spam( array $signals = [], array $metadata = [] ): self {
		return new self( true, $signals, $metadata );
	}

	/**
	 * Whether this technique flagged the submission as spam.
	 *
	 * @return bool
	 */
	public function is_spam(): bool {
		return $this->is_spam;
	}

	/**
	 * Gets the signal codes.
	 *
	 * @return string[]
	 */
	public function get_signals(): array {
		return $this->signals;
	}

	/**
	 * Gets the metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function get_metadata(): array {
		return $this->metadata;
	}

	/**
	 * Checks if the technique was skipped.
	 *
	 * @return bool
	 */
	public function was_skipped(): bool {
		return ! empty( $this->metadata['skipped'] );
	}

	/**
	 * Converts to an array for JSON serialization / logging.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'is_spam'  => $this->is_spam,
			'signals'  => $this->signals,
			'metadata' => $this->metadata,
		];
	}

	/**
	 * Returns whether ANY non-skipped technique in a set flagged spam.
	 *
	 * Used for short-circuit decisions: if PoW already flagged spam,
	 * skip the AI call.
	 *
	 * @param array<string, Technique_Result> $results Results collected so far.
	 *
	 * @return bool Whether any technique flagged spam.
	 */
	public static function any_spam( array $results ): bool {
		foreach ( $results as $result ) {
			if ( ! $result->was_skipped() && $result->is_spam() ) {
				return true;
			}
		}
		return false;
	}
}
