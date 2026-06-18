<?php
/**
 * Comment spam checker orchestrator.
 *
 * Mirrors Spam_Checker but for WordPress comments. Runs on the WordPress
 * comment pipeline hooks:
 *
 * - preprocess_comment (priority 1) → check()
 * - rest_pre_insert_comment (priority 1) → check_rest()
 * - pre_comment_approved (priority 10) → get_verdict()
 * - wp_insert_comment (priority 10) → write_meta()
 *
 * The two-phase approach is necessary because WordPress doesn't assign
 * a comment ID until after insertion. Phase 1 evaluates techniques and
 * stores the result; Phase 2 writes comment meta after the ID exists.
 *
 * ## Token-based comment matching
 *
 * WordPress runs wp_filter_comment() between preprocess_comment and
 * pre_comment_approved, which texturizes comment_content (e.g. straight
 * apostrophes become curly quotes). This means we cannot use comment_content
 * as a fingerprint to match the comment across hooks — the stored raw value
 * won't equal the filtered value WordPress passes to pre_comment_approved.
 *
 * Solution: inject a unique token into $commentdata during check(). WordPress
 * passes unknown keys through wp_filter_comment() unchanged, so the token
 * survives to pre_comment_approved where get_verdict() can use it for an
 * exact match.
 *
 * write_meta() fires on wp_insert_comment and receives a WP_Comment object
 * (no token), so it relies on the instance property $last_result directly —
 * safe because wp_insert_comment fires in the same request immediately after
 * preprocess_comment.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Comments;

use PDM_Antispam\Spam_Checker;
use PDM_Antispam\Spam_Result;
use PDM_Antispam\Techniques\Technique;

/**
 * Orchestrates PoW + AI spam checking for WordPress comments.
 *
 * Uses composition: receives a configured Spam_Checker instance and
 * delegates technique evaluation to it via evaluate_techniques().
 */
class Comment_Checker {

	/**
	 * Hidden field name for the per-request check token.
	 *
	 * Injected into $commentdata by check() and read back by get_verdict().
	 * WordPress passes unknown keys through wp_filter_comment() unchanged,
	 * so this token survives the texturization step between the two hooks.
	 */
	const CHECK_TOKEN_KEY = 'gfsh_check_token';

	/**
	 * The spam checker instance providing technique evaluation.
	 *
	 * @var Spam_Checker
	 */
	private Spam_Checker $checker;

	/**
	 * Result from the last check, used by get_verdict() and write_meta().
	 *
	 * @var Spam_Result|null
	 */
	private ?Spam_Result $last_result = null;

	/**
	 * Token injected into $commentdata during check(), matched in get_verdict().
	 *
	 * @var string|null
	 */
	private ?string $last_token = null;

	/**
	 * @param Spam_Checker $checker The configured spam checker with registered techniques.
	 */
	public function __construct( Spam_Checker $checker ) {
		$this->checker = $checker;
	}

	/**
	 * Phase 1 (shared): Evaluates all techniques against a comment.
	 *
	 * Side-effect-light: sets $last_result + $last_token and injects the
	 * matching token into $commentdata. NEVER terminates the request and
	 * NEVER returns a WP_Error — the public wrappers check() (form POST)
	 * and check_rest() (REST API) decide how to enforce a 'fail' action,
	 * because the two paths require different mechanisms (wp_die() vs
	 * WP_Error).
	 *
	 * Returns $commentdata unchanged (aside from the token) when the check
	 * is skipped (disabled / logged-in bypass), so $last_result stays null
	 * and the caller short-circuits.
	 *
	 * @param array $commentdata The WordPress comment data array.
	 *
	 * @return array The comment data with the check token injected.
	 */
	private function run_evaluation( array $commentdata ): array {
		if ( ! Comment_Settings::is_enabled() ) {
			return $commentdata;
		}

		if ( Comment_Settings::should_bypass_logged_in() && is_user_logged_in() ) {
			return $commentdata;
		}

		$context = new Comment_Context( $commentdata );

		// Use the shared evaluation loop with comment-specific enable check.
		$results = $this->checker->evaluate_techniques(
			$context,
			fn( Technique $t ) => $this->is_technique_enabled( $t )
		);

		// Per-technique action settings drive the resolved action. Comments
		// support 'spam' (mark as spam/trash) and 'fail' (block submission).
		// 'reject' is intentionally not offered for comments.
		$action_settings = [
			'pow' => Comment_Settings::get_pow_action(),
			'ai'  => Comment_Settings::get_ai_action(),
		];

		$this->last_result = Spam_Result::from_technique_results( $results, $action_settings );

		// Inject a unique token so get_verdict() can match this comment.
		// WordPress passes unknown $commentdata keys through wp_filter_comment()
		// unchanged, so the token survives to pre_comment_approved.
		$token                                = uniqid( 'gfsh_', true );
		$this->last_token                     = $token;
		$commentdata[ self::CHECK_TOKEN_KEY ] = $token;

		return $commentdata;
	}

	/**
	 * Phase 1 (form POST): Evaluates a comment submitted via wp-comments-post.php.
	 *
	 * Runs on preprocess_comment at priority 1 (earliest possible).
	 *
	 * If the resolved action is 'fail', enforcement uses wp_die() because the
	 * preprocess_comment filter does NOT propagate a WP_Error — wp_new_comment()
	 * assigns the filter return value straight back to $commentdata with no
	 * is_wp_error() check. wp_die() is the same mechanism core uses for
	 * duplicate/flood comment errors.
	 *
	 * @param array $commentdata The WordPress comment data array.
	 *
	 * @return array The comment data with the check token injected.
	 */
	public function check( array $commentdata ): array {
		$commentdata = $this->run_evaluation( $commentdata );

		if ( $this->last_result !== null && $this->last_result->should_fail_validation() ) {
			$technique = $this->get_failing_technique();
			$message   = Comment_Settings::get_fail_message( $technique );

			// 'fail' blocks before wp_insert_comment() — no comment row exists.
			// Record to gfsh_events so it surfaces in dashboard stats.
			\PDM_Antispam\Event_Recorder::record_blocked(
				\PDM_Antispam\Event_Recorder::SOURCE_COMMENT,
				'fail',
				[ 'signals' => $this->last_result->to_array() ]
			);

			wp_die(
				esc_html( $message ),
				esc_html__( 'Comment Submission Failure', 'pdm-antispam' ),
				[
					'response'  => 403,
					'back_link' => true,
				]
			);
		}

		return $commentdata;
	}

	/**
	 * Phase 1 (REST): Evaluates a comment submitted via the REST API.
	 *
	 * Runs on rest_pre_insert_comment at priority 1.
	 *
	 * If the resolved action is 'fail', returns a WP_Error — which the
	 * rest_pre_insert_comment filter DOES honor (WP_REST_Comments_Controller::
	 * create_item() bails with the error). This is the REST equivalent of the
	 * wp_die() used on the form POST path in check().
	 *
	 * @param array $commentdata The WordPress comment data array.
	 *
	 * @return array|\WP_Error The comment data with the token injected, or a
	 *                         WP_Error when the resolved action is 'fail'.
	 */
	public function check_rest( array $commentdata ) {
		$commentdata = $this->run_evaluation( $commentdata );

		if ( $this->last_result !== null && $this->last_result->should_fail_validation() ) {
			$technique = $this->get_failing_technique();
			$message   = Comment_Settings::get_fail_message( $technique );

			// 'fail' via REST — WP_Error blocks before comment is inserted, no comment row exists.
			// Record to gfsh_events so it surfaces in dashboard stats.
			\PDM_Antispam\Event_Recorder::record_blocked(
				\PDM_Antispam\Event_Recorder::SOURCE_COMMENT,
				'fail',
				[ 'signals' => $this->last_result->to_array() ]
			);

			return new \WP_Error(
				'gfsh_comment_blocked',
				$message,
				[ 'status' => 403 ]
			);
		}

		return $commentdata;
	}

	/**
	 * Identifies which technique triggered the 'fail' action.
	 *
	 * Used solely to select the per-technique fail message (pow vs ai).
	 * Returns the first spam-flagged technique whose configured action is
	 * 'fail'; falls back to 'pow' when none is identified.
	 *
	 * @return string The technique name: 'pow' or 'ai'.
	 */
	private function get_failing_technique(): string {
		if ( $this->last_result === null ) {
			return 'pow';
		}

		$actions = [
			'pow' => Comment_Settings::get_pow_action(),
			'ai'  => Comment_Settings::get_ai_action(),
		];

		foreach ( $this->last_result->get_technique_results() as $name => $data ) {
			if ( empty( $data['is_spam'] ) ) {
				continue;
			}

			if ( ( $actions[ $name ] ?? '' ) === 'fail' ) {
				return $name;
			}
		}

		return 'pow';
	}

	/**
	 * Phase 1b: Returns the spam verdict for pre_comment_approved.
	 *
	 * Runs on pre_comment_approved at priority 10. Matches the comment
	 * using the token injected by check() — immune to wp_filter_comment()
	 * texturization because the token is not a known comment field.
	 *
	 * Returns 'spam' or 'trash' if the last check flagged the comment as spam.
	 *
	 * @param string|int $approved The current approval status.
	 * @param array      $comment  The comment data array.
	 *
	 * @return string|int The (possibly modified) approval status.
	 */
	public function get_verdict( $approved, array $comment ) {
		if ( $this->last_result === null || $this->last_token === null ) {
			return $approved;
		}

		if ( ( $comment[ self::CHECK_TOKEN_KEY ] ?? '' ) !== $this->last_token ) {
			return $approved;
		}

		if ( $this->last_result->is_spam() ) {
			return Comment_Settings::get_spam_action(); // 'spam' or 'trash'
		}

		return $approved;
	}

	/**
	 * Phase 2: Writes comment meta after the comment has an ID.
	 *
	 * Runs on wp_insert_comment at priority 10. Uses $last_result directly
	 * (no token needed) because wp_insert_comment fires in the same request
	 * immediately after preprocess_comment.
	 *
	 * @param int         $comment_id The comment ID.
	 * @param \WP_Comment $comment    The comment object.
	 */
	public function write_meta( int $comment_id, \WP_Comment $comment ): void {
		if ( $this->last_result === null ) {
			return;
		}

		$result_array = $this->last_result->to_array();

		update_comment_meta( $comment_id, Comment_History::META_KEY_RESULT, wp_json_encode( $result_array ) );

		// Write denormalized meta for per-comment detail view.
		$this->write_dashboard_meta( $comment_id, $result_array );

		// Record to events table — single source of truth for aggregate stats.
		\PDM_Antispam\Event_Recorder::record_event(
			\PDM_Antispam\Event_Recorder::SOURCE_COMMENT,
			$result_array['action'] ?? 'allow',
			[ 'signals' => $result_array ]
		);

		$event = $this->last_result->is_spam() ? 'check-spam' : 'check-ham';
		Comment_History::add_entry( $comment_id, $event, $result_array );

		// Clear state after writing meta.
		$this->last_result = null;
		$this->last_token  = null;
	}

	/**
	 * Checks if a technique is enabled for comments.
	 *
	 * Uses Comment_Settings toggles instead of GF per-form settings.
	 *
	 * @param Technique $technique The technique to check.
	 *
	 * @return bool
	 */
	private function is_technique_enabled( Technique $technique ): bool {
		return match ( $technique->get_name() ) {
			'pow' => Comment_Settings::is_pow_enabled(),
			'ai'  => Comment_Settings::is_ai_enabled(),
			default => true,
		};
	}

	/**
	 * Writes denormalized comment meta for efficient dashboard queries.
	 *
	 * Mirrors the GF entry meta pattern but uses update_comment_meta()
	 * instead of gform_update_meta(). Only writes meta for techniques
	 * that actually ran (not skipped).
	 *
	 * @param int                  $comment_id   The comment ID.
	 * @param array<string, mixed> $result_array The Spam_Result::to_array() output.
	 *
	 * @return void
	 */
	private function write_dashboard_meta( int $comment_id, array $result_array ): void {
		// Action — always written.
		update_comment_meta( $comment_id, 'gfsh_action', $result_array['action'] ?? 'allow' );

		// Technique-specific meta — each technique declares what it wants denormalized.
		$checker = function_exists( 'PDM_Antispam' ) ? ( PDM_Antispam()->spam_checker ?? null ) : null;
		foreach ( $result_array['technique_results'] as $name => $data ) {
			if ( ! empty( $data['metadata']['skipped'] ) ) {
				continue;
			}
			$technique = $checker ? $checker->get_technique( $name ) : null;
			if ( ! $technique ) {
				continue;
			}
			foreach ( $technique->get_dashboard_meta( $data ) as $key => $value ) {
				update_comment_meta( $comment_id, $key, $value );
			}
		}
	}
}
