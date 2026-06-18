<?php
/**
 * Proof-of-Work technique.
 *
 * Verifies that the client solved a computational puzzle before submitting,
 * which proves two things:
 *
 * 1. **JavaScript executed in a real browser.** The puzzle is solved via
 *    Web Worker using SubtleCrypto — headless bots without JS can't do this.
 *
 * 2. **Real CPU time was spent.** Even bots with JS execution must burn
 *    actual compute cycles. At base difficulty (15 bits), this is ~285ms
 *    per form (p50 on real-world devices) — barely noticeable but adds up
 *    fast for bulk spam.
 *
 * Verdict: binary pass/fail.
 * - Valid solution:   pass (not spam)
 * - Missing solution: fail (spam — JS didn't run)
 * - Invalid solution: fail (spam — tampered, expired, replayed, or wrong answer)
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Techniques;

use PDM_Antispam\Contracts\Checkable_Context;
use PDM_Antispam\Settings;

/**
 * Proof-of-Work spam technique.
 *
 * Requires the client to solve a computational challenge before submission,
 * making automated spam submissions expensive.
 */
class Proof_Of_Work extends Base_Technique {

	/**
	 * Returns the technique's unique identifier.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'pow';
	}

	/**
	 * Evaluates a submission's Proof-of-Work solution.
	 *
	 * This is the server-side half of the PoW system. The client solved
	 * the puzzle (found a counter where SHA-256(challenge|counter) has N
	 * leading zero bits), and now we verify it with a single hash.
	 *
	 * The evaluation flow:
	 * 1. Extract PoW solution from the client payload
	 * 2. If missing → spam (JS didn't run or bot skipped it)
	 * 3. Delegate to PoW_Challenge::verify() for cryptographic validation
	 * 4. If valid → pass, record submission for difficulty escalation
	 * 5. If invalid → spam with specific reason code
	 *
	 * @param Checkable_Context $context The submission metadata.
	 *
	 * @return Technique_Result
	 */
	public function evaluate( Checkable_Context $context ): Technique_Result {
		$pow_data = $context->get_pow_solution();

		// No PoW solution at all — JS collector didn't run.
		if ( $pow_data === null ) {
			return Technique_Result::spam(
				[ 'pow_missing' ],
				[ 'reason' => 'No PoW solution in submission payload.' ]
			);
		}

		$form    = $context->get_form();
		$form_id = (int) rgar( $form, 'id' );

		// Delegate to PoW_Challenge for cryptographic verification.
		// This is ONE HMAC check + ONE SHA-256 hash — extremely cheap.
		$result = PoW_Challenge::verify(
			$pow_data['challenge'],
			$pow_data['signature'],
			$pow_data['solution'],
			$form_id,
			$pow_data['client_nonce']
		);

		if ( $result['valid'] ) {
			// Store nonce in entry meta — permanent replay protection
			// that survives transient flushes.
			$entry_id = (int) rgar( $context->get_entry(), 'id' );
			if ( $entry_id && ! empty( $result['nonce_key'] ) ) {
				gform_update_meta( $entry_id, 'gfsh_pow_nonce', $result['nonce_key'] );
			}

			// Parse the challenge to extract metadata for logging.
			$challenge_parts = PoW_Challenge::parse( $pow_data['challenge'] );

			return Technique_Result::clean( [
				'solve_time_ms' => $pow_data['solve_time_ms'],
				'difficulty'    => $challenge_parts['difficulty'] ?? null,
				'is_fallback'   => $pow_data['is_fallback'],
			] );
		}

		// Map verification failure reasons to signal codes.
		// These appear in the entry meta box and spam logs.
		$signal = $this->map_failure_signal( $result['reason'] );

		return Technique_Result::spam(
			[ $signal ],
			[
				'reason'      => $result['reason'],
				'is_fallback' => $pow_data['is_fallback'],
			]
		);
	}

	/**
	 * Formats a one-line headline for PoW results.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return string
	 */
	public function format_headline( array $result_data ): string {
		$metadata = $result_data['metadata'] ?? [];
		$is_spam  = $result_data['is_spam'] ?? false;
		$signals  = $result_data['signals'] ?? [];

		if ( ! empty( $metadata['skipped'] ) ) {
			return str_replace( 'skipped_', '', $signals[0] ?? 'skipped' );
		}

		if ( ! $is_spam ) {
			$ms = $metadata['solve_time_ms'] ?? null;
			return $ms !== null ? sprintf( 'solved in %dms', $ms ) : 'ok';
		}

		$signal = $signals[0] ?? 'invalid';
		return str_replace( 'pow_', '', $signal );
	}

	/**
	 * Formats detail key-value pairs for PoW expanded display.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string>
	 */
	public function format_details( array $result_data ): array {
		$metadata = $result_data['metadata'] ?? [];
		$details  = [];

		if ( isset( $metadata['difficulty'] ) ) {
			$details['Difficulty'] = (string) $metadata['difficulty'];
		}
		if ( isset( $metadata['solve_time_ms'] ) ) {
			$details['Solve Time'] = $metadata['solve_time_ms'] . 'ms';
		}
		if ( isset( $metadata['is_fallback'] ) ) {
			$details['Challenge Type'] = $metadata['is_fallback'] ? 'Fallback' : 'REST';
		}

		return $details;
	}

	/**
	 * Returns PoW meta keys to denormalize for dashboard queries.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string>
	 */
	public function get_dashboard_meta( array $result_data ): array {
		$metadata = $result_data['metadata'] ?? [];
		$meta     = [];

		if ( isset( $metadata['solve_time_ms'] ) ) {
			$meta['gfsh_pow_solve_ms'] = (string) $metadata['solve_time_ms'];
		}
		if ( isset( $metadata['difficulty'] ) ) {
			$meta['gfsh_pow_difficulty'] = (string) $metadata['difficulty'];
		}
		if ( isset( $metadata['is_fallback'] ) ) {
			$meta['gfsh_pow_fallback'] = $metadata['is_fallback'] ? '1' : '0';
		}

		return $meta;
	}

	/**
	 * Maps a PoW_Challenge verification failure reason to a signal code.
	 *
	 * Signal codes are human-readable identifiers that appear in the
	 * entry meta box and spam analysis logs. They help site admins
	 * understand why a submission was flagged.
	 *
	 * @param string $reason The failure reason from PoW_Challenge::verify().
	 *
	 * @return string The signal code.
	 */
	private function map_failure_signal( string $reason ): string {
		$reason_map = [
			'invalid_challenge' => 'pow_tampered',
			'bad_format'        => 'pow_bad_format',
			'form_mismatch'     => 'pow_form_mismatch',
			'expired'           => 'pow_expired',
			'replay'            => 'pow_replay',
			'invalid_solution'  => 'pow_invalid_solution',
		];

		return $reason_map[ $reason ] ?? 'pow_unknown_error';
	}
}
