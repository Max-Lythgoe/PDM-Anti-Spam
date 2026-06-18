<?php
/**
 * Aggregate spam analysis result.
 *
 * Combines results from all techniques into a final verdict and action.
 * No scores — each technique provides a clear pass/fail verdict,
 * and the action is determined by the user's per-technique settings.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam;

use PDM_Antispam\Techniques\Technique_Result;

/**
 * Aggregate result from the Spam_Checker orchestrator.
 *
 * Contains the per-technique breakdowns, the overall spam verdict,
 * and the resolved action (allow, mark_spam, reject, fail).
 */
class Spam_Result {

	/**
	 * Possible actions.
	 *
	 * - ACTION_ALLOW:     Entry is allowed through normally.
	 * - ACTION_MARK_SPAM: Entry is marked as spam for review.
	 * - ACTION_REJECT:    Entry is silently discarded (bot sees fake confirmation).
	 * - ACTION_FAIL:      Entry is blocked with a validation error on the form.
	 */
	public const ACTION_ALLOW     = 'allow';
	public const ACTION_MARK_SPAM = 'mark_spam';
	public const ACTION_REJECT    = 'reject';
	public const ACTION_FAIL      = 'fail';

	/**
	 * Resolved action based on technique verdicts and user settings.
	 *
	 * @var string
	 */
	private string $action;

	/**
	 * Per-technique results keyed by technique name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $technique_results;

	/**
	 * All signal codes from all techniques.
	 *
	 * @var string[]
	 */
	private array $signals;

	/**
	 * @param string                              $action            Resolved action.
	 * @param array<string, array<string, mixed>> $technique_results Per-technique breakdowns.
	 * @param string[]                            $signals           All signal codes.
	 */
	public function __construct(
		string $action,
		array $technique_results = [],
		array $signals = [],
	) {
		$this->action            = $action;
		$this->technique_results = $technique_results;
		$this->signals           = $signals;
	}

	/**
	 * Builds a Spam_Result from technique results and per-technique action settings.
	 *
	 * Action resolution:
	 * - If no technique flagged spam → allow.
	 * - If a technique flagged spam → use THAT technique's configured action.
	 * - If multiple techniques flagged spam → use the most severe action
	 *   among the techniques that actually flagged (fail > reject > mark_spam).
	 *
	 * Only techniques that actually ran AND flagged spam contribute to the
	 * action decision. Skipped/short-circuited techniques are ignored.
	 *
	 * @param array<string, Technique_Result> $results         Per-technique results keyed by name.
	 * @param array<string, string>           $action_settings Per-technique action settings (e.g. ['pow' => 'reject', 'ai' => 'spam']).
	 *
	 * @return self
	 */
	/**
	 * Hydrates a Spam_Result from a stored/decoded array.
	 *
	 * Used by the rendering layer to work with typed objects instead of
	 * raw arrays when displaying stored results from entry/comment meta.
	 *
	 * @param array<string, mixed> $data The decoded result array (from to_array() or JSON).
	 *
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(string) ( $data['action'] ?? self::ACTION_ALLOW ),
			(array) ( $data['technique_results'] ?? [] ),
			(array) ( $data['signals'] ?? [] ),
		);
	}

	/**
	 * Builds a Spam_Result from technique results and per-technique action settings.
	 *
	 * @param array<string, Technique_Result> $results         Per-technique results keyed by name.
	 * @param array<string, string>           $action_settings Per-technique action settings.
	 *
	 * @return self
	 */
	public static function from_technique_results(
		array $results,
		array $action_settings
	): self {
		$all_signals = [];
		$breakdowns  = [];
		$any_spam    = false;
		$action      = self::ACTION_ALLOW;

		// Action severity order: fail > reject > mark_spam > allow.
		$severity = [
			self::ACTION_ALLOW     => 0,
			self::ACTION_MARK_SPAM => 1,
			self::ACTION_REJECT    => 2,
			self::ACTION_FAIL      => 3,
		];

		foreach ( $results as $name => $result ) {
			/** @var Technique_Result $result */
			$breakdowns[ $name ] = $result->to_array();

			if ( $result->was_skipped() ) {
				continue;
			}

			if ( $result->is_spam() ) {
				$any_spam    = true;
				$all_signals = array_merge( $all_signals, $result->get_signals() );

				// Resolve action from THIS technique's setting only.
				$technique_action = $action_settings[ $name ] ?? 'spam';
				$resolved_action  = self::normalize_action( $technique_action );

				// Most severe action among flagging techniques wins.
				if ( ( $severity[ $resolved_action ] ?? 0 ) > ( $severity[ $action ] ?? 0 ) ) {
					$action = $resolved_action;
				}
			}
		}

		if ( ! $any_spam ) {
			$action = self::ACTION_ALLOW;
		}

		return new self( $action, $breakdowns, $all_signals );
	}

	/**
	 * Normalizes a user-facing action setting to an internal action constant.
	 *
	 * @param string $action_setting The setting value ('spam', 'reject', 'fail').
	 *
	 * @return string One of ACTION_MARK_SPAM, ACTION_REJECT, ACTION_FAIL.
	 */
	private static function normalize_action( string $action_setting ): string {
		switch ( $action_setting ) {
			case 'reject':
				return self::ACTION_REJECT;
			case 'fail':
				return self::ACTION_FAIL;
			case 'spam':
			default:
				return self::ACTION_MARK_SPAM;
		}
	}

	/**
	 * Gets the resolved action.
	 *
	 * @return string One of ACTION_ALLOW, ACTION_MARK_SPAM, ACTION_REJECT, ACTION_FAIL.
	 */
	public function get_action(): string {
		return $this->action;
	}

	/**
	 * Whether the submission should be marked as spam.
	 *
	 * True for mark_spam, reject, and fail — all are "spam" from GF's perspective.
	 *
	 * @return bool
	 */
	public function is_spam(): bool {
		return in_array( $this->action, [ self::ACTION_MARK_SPAM, self::ACTION_REJECT, self::ACTION_FAIL ], true );
	}

	/**
	 * Whether the submission should be silently rejected (no entry created,
	 * bot sees fake confirmation like the GF honeypot).
	 *
	 * @return bool
	 */
	public function should_reject(): bool {
		return self::ACTION_REJECT === $this->action;
	}

	/**
	 * Whether the submission should fail with a validation error
	 * (form shows error message, no entry created).
	 *
	 * @return bool
	 */
	public function should_fail_validation(): bool {
		return self::ACTION_FAIL === $this->action;
	}

	/**
	 * Gets all signal codes.
	 *
	 * @return string[]
	 */
	public function get_signals(): array {
		return $this->signals;
	}

	/**
	 * Gets per-technique result breakdowns.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_technique_results(): array {
		return $this->technique_results;
	}

	/**
	 * Converts to an array for JSON serialization / logging.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'action'            => $this->action,
			'signals'           => $this->signals,
			'technique_results' => $this->technique_results,
		];
	}
}
