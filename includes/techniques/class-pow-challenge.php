<?php
/**
 * PoW challenge minting and verification.
 *
 * Proof-of-Work is an asymmetric cost mechanism: the server creates a puzzle
 * that's cheap to verify but expensive to solve. Think of it like a lock that
 * takes 1 second to check but 16 milliseconds to pick — except the "picking"
 * is brute-force guessing, so the cost scales linearly with difficulty.
 *
 * How it works:
 *
 * 1. **Server mints a challenge** — a signed string containing the form ID,
 *    timestamps, difficulty, and a random nonce. Cost: ~2µs (one HMAC).
 *
 * 2. **Client solves the puzzle** — the browser concatenates the challenge with
 *    incrementing counter values and SHA-256 hashes each one, looking for a hash
 *    that starts with N zero bits. At difficulty 15, this takes ~33K attempts
 *    (~285ms on real-world devices at p50). The work is done in a Web Worker
 *    so the UI stays responsive.
 *
 * 3. **Server verifies the solution** — one HMAC check (was this our challenge?)
 *    plus one SHA-256 hash (does challenge|counter produce enough leading zeros?).
 *    Cost: ~2.5µs. The server does NOT re-solve the puzzle.
 *
 * The asymmetry is the key insight: verification is O(1) regardless of difficulty,
 * while solving is O(2^difficulty). Raising difficulty by 1 bit doubles the client's
 * work but adds zero cost to the server.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Techniques;

use PDM_Antispam\Security\Signing_Service;

/**
 * Mints and verifies Proof-of-Work challenges.
 */
class PoW_Challenge {

	/**
	 * Signing purpose for PoW challenges.
	 *
	 * Passed to Signing_Service so PoW keys are isolated from other
	 * signing purposes (payload signing, etc.).
	 */
	private const SIGNING_PURPOSE = 'pow_challenge';

	/**
	 * Default challenge TTL in seconds (10 minutes).
	 */
	public const DEFAULT_TTL = 600;

	/**
	 * Fallback challenge TTL in seconds (24 hours).
	 *
	 * Fallback challenges are embedded in page HTML and may be served from
	 * full-page caches for hours. 24h covers common CDN/page-cache TTLs.
	 */
	public const FALLBACK_TTL = 86400;

	/**
	 * Grace period beyond stated expiry for fallback challenges (12 hours).
	 *
	 * Analogous to how WordPress wp_verify_nonce() accepts nonces from the
	 * current and previous tick. Covers CDN stale-while-revalidate and
	 * clock drift between edge nodes.
	 */
	private const FALLBACK_GRACE = 43200;

	/**
	 * Clock skew tolerance in seconds for expiry checks.
	 *
	 * Allows for minor time differences between server and client,
	 * or between load-balanced servers with slightly out-of-sync clocks.
	 */
	private const CLOCK_SKEW_TOLERANCE = 120;

	/**
	 * Transient prefix for consumed nonces.
	 */
	private const NONCE_PREFIX = 'gfsh_pow_nonce_';

	/**
	 * TTL for the replay-protection transient (seconds).
	 *
	 * Short enough to avoid wp_options accumulation on sites without
	 * persistent object cache, long enough to catch rapid replays.
	 * Self-renewing: every failed replay attempt resets the timer.
	 */
	private const REPLAY_TRANSIENT_TTL = 120;

	/**
	 * Mints a signed challenge for a given form.
	 *
	 * The returned challenge is a pipe-delimited string containing everything
	 * the client needs to solve the puzzle and the server needs to verify it:
	 *
	 *   `{form_id}|{issued_at}|{expires_at}|{difficulty}|{nonce}`
	 *
	 * The signature is an HMAC-SHA256 of this string using a server-side secret.
	 * The client cannot forge or modify the challenge without invalidating the
	 * signature — any tampering (e.g., lowering difficulty) is detected on verify.
	 *
	 * @param int $form_id   The Gravity Forms form ID.
	 * @param int $difficulty Number of leading zero bits required in the solution hash.
	 *                        Each additional bit doubles the expected solve time.
	 *                        15 bits ≈ 285ms real-world p50.
	 *                        20 bits ≈ 9s real-world p50.
	 *                        @see scripts/benchmark-pow.html for real benchmarks.
	 * @param int $ttl        Challenge lifetime in seconds. After this + clock skew
	 *                        tolerance, the challenge is rejected as expired.
	 *
	 * @return array{challenge: string, signature: string, difficulty: int, expires: int, issued_at: int}
	 */
	public static function mint( int $form_id, int $difficulty, int $ttl = self::DEFAULT_TTL ): array {
		$nonce      = bin2hex( random_bytes( 16 ) );
		$issued_at  = time();
		$expires_at = $issued_at + $ttl;

		$challenge = implode( '|', [
			$form_id,
			$issued_at,
			$expires_at,
			$difficulty,
			$nonce,
		] );

		$signature = Signing_Service::sign( $challenge, self::SIGNING_PURPOSE );

		return [
			'challenge'  => $challenge,
			'signature'  => $signature,
			'difficulty' => $difficulty,
			'expires'    => $expires_at,
			'issued_at'  => $issued_at,
		];
	}

	/**
	 * Mints a signed fallback challenge for embedding in cached HTML.
	 *
	 * Unlike mint(), this produces a **4-field** challenge with no server nonce:
	 *
	 *   `{form_id}|{issued_at}|{expires_at}|{difficulty}`
	 *
	 * The client generates its own nonce (via crypto.randomUUID()) and includes
	 * it in the hash input and submission. This means each visitor gets a unique
	 * replay key even when they all share the same cached challenge string.
	 *
	 * @param int $form_id   The Gravity Forms form ID.
	 * @param int $difficulty Number of leading zero bits required.
	 * @param int $ttl        Challenge lifetime in seconds (default: 24 hours).
	 *
	 * @return array{challenge: string, signature: string, difficulty: int, expires: int, issued_at: int}
	 */
	public static function mint_fallback( int $form_id, int $difficulty, int $ttl = self::FALLBACK_TTL ): array {
		$issued_at  = time();
		$expires_at = $issued_at + $ttl;

		$challenge = implode( '|', [
			$form_id,
			$issued_at,
			$expires_at,
			$difficulty,
		] );

		$signature = Signing_Service::sign( $challenge, self::SIGNING_PURPOSE );

		return [
			'challenge'  => $challenge,
			'signature'  => $signature,
			'difficulty' => $difficulty,
			'expires'    => $expires_at,
			'issued_at'  => $issued_at,
		];
	}

	/**
	 * Verifies a PoW solution submitted by the client.
	 *
	 * Supports two challenge formats:
	 *
	 * - **5-field (REST):** `{form_id}|{issued_at}|{expires_at}|{difficulty}|{server_nonce}`
	 *   Hash: SHA-256(challenge|solution). Replay key from server nonce.
	 *
	 * - **4-field (fallback):** `{form_id}|{issued_at}|{expires_at}|{difficulty}`
	 *   Hash: SHA-256(challenge|client_nonce|solution). Replay key from client nonce.
	 *   Requires non-empty $client_nonce parameter.
	 *
	 * This runs 7 checks in order, failing fast on the first problem:
	 *
	 * 1. **Signature check** — Was this challenge issued by our server?
	 * 2. **Format check** — Does the challenge have 4 or 5 pipe-delimited fields?
	 * 3. **Form ID check** — Does the challenge belong to the form being submitted?
	 * 4. **Expiry check** — Is the challenge still within its time window?
	 *    (FALLBACK_GRACE for 4-field, CLOCK_SKEW_TOLERANCE for 5-field)
	 * 5. **Replay check** — Has this nonce been used before?
	 * 6. **Proof-of-work check** — Does the hash have enough leading zero bits?
	 * 7. **Nonce consumption** — Mark the nonce as used.
	 *
	 * @param string $challenge    The original challenge string from mint() or mint_fallback().
	 * @param string $signature    The HMAC signature.
	 * @param string $solution     The counter value the client found.
	 * @param int    $form_id      The form ID being submitted (for cross-check).
	 * @param string $client_nonce Client-generated nonce for fallback challenges.
	 *                             Required (non-empty) for 4-field challenges, ignored for 5-field.
	 *
	 * @return array{valid: bool, reason: string, issued_at?: int, difficulty?: int, nonce_key?: string}
	 */
	public static function verify(
		string $challenge,
		string $signature,
		string $solution,
		int $form_id,
		string $client_nonce = ''
	): array {
		// 1. Was this challenge issued by our server?
		if ( ! Signing_Service::verify( $challenge, $signature, self::SIGNING_PURPOSE ) ) {
			return [ 'valid' => false, 'reason' => 'invalid_challenge' ];
		}

		// 2. Does the challenge have the expected pipe-delimited structure?
		//    Accept both 4-field (fallback) and 5-field (REST) formats.
		$parts      = explode( '|', $challenge );
		$part_count = count( $parts );

		if ( $part_count !== 4 && $part_count !== 5 ) {
			return [ 'valid' => false, 'reason' => 'bad_format' ];
		}

		$is_fallback       = ( $part_count === 4 );
		$challenge_form_id = (int) $parts[0];
		$issued_at         = (int) $parts[1];
		$expires_at        = (int) $parts[2];
		$difficulty        = (int) $parts[3];
		$server_nonce      = $is_fallback ? null : $parts[4];

		// 3. Does the challenge belong to this form?
		if ( $challenge_form_id !== $form_id ) {
			return [ 'valid' => false, 'reason' => 'form_mismatch' ];
		}

		// 4. Is the challenge still valid (not expired)?
		//    Fallback challenges get a generous grace period (12h) because they
		//    may be served from page caches long after minting.
		$grace = $is_fallback ? self::FALLBACK_GRACE : self::CLOCK_SKEW_TOLERANCE;

		if ( time() > $expires_at + $grace ) {
			return [ 'valid' => false, 'reason' => 'expired' ];
		}

		// 5. Has this exact challenge been submitted before?
		//    For REST challenges: replay key from server nonce.
		//    For fallback challenges: replay key from client nonce (unique per visitor).
		if ( $is_fallback ) {
			// Fallback requires a client nonce — without one, we can't derive a replay key.
			if ( $client_nonce === '' ) {
				return [ 'valid' => false, 'reason' => 'missing_client_nonce' ];
			}
			$replay_source = $client_nonce;
		} else {
			$replay_source = $server_nonce;
		}

		$nonce_key = self::NONCE_PREFIX . substr( hash( 'sha256', $replay_source ), 0, 16 );

		// 5a. Fast path — transient catches rapid replays.
		if ( get_transient( $nonce_key ) ) {
			// Renew the shield — keeps transient alive as long as bot keeps trying.
			set_transient( $nonce_key, 1, self::REPLAY_TRANSIENT_TTL );
			return [
				'valid'      => false,
				'reason'     => 'replay',
				'issued_at'  => $issued_at,
				'difficulty' => $difficulty,
			];
		}

		// 5b. Durable path — entry meta catches replays after transient expires.
		if ( self::nonce_exists_in_entry_meta( $nonce_key ) ) {
			// Promote back to hot cache for subsequent rapid replays.
			set_transient( $nonce_key, 1, self::REPLAY_TRANSIENT_TTL );
			return [
				'valid'      => false,
				'reason'     => 'replay',
				'issued_at'  => $issued_at,
				'difficulty' => $difficulty,
			];
		}

		// 6. Does the solution actually satisfy the difficulty requirement?
		//    REST:     SHA-256(challenge|solution)
		//    Fallback: SHA-256(challenge|client_nonce|solution)
		$hash_input = $is_fallback
			? $challenge . '|' . $client_nonce . '|' . $solution
			: $challenge . '|' . $solution;

		$hash = hash( 'sha256', $hash_input );

		if ( ! self::has_leading_zero_bits( $hash, $difficulty ) ) {
			return [ 'valid' => false, 'reason' => 'invalid_solution' ];
		}

		// 7. Consume — short transient as hot cache.
		set_transient( $nonce_key, 1, self::REPLAY_TRANSIENT_TTL );

		return [
			'valid'      => true,
			'reason'     => '',
			'issued_at'  => $issued_at,
			'difficulty' => $difficulty,
			'nonce_key'  => $nonce_key,
		];
	}

	/**
	 * Checks if a hex-encoded hash has the required number of leading zero bits.
	 *
	 * "Leading zero bits" means the hash, when viewed as a binary number, starts
	 * with at least N zeros. For example, difficulty 17 requires the first 17 bits
	 * to all be zero — which means the first 4 hex characters must be "0" (16 zero
	 * bits) and the 5th hex character must be 0 or 1 (one more zero bit).
	 *
	 * This is the same mechanism Bitcoin uses for mining, just at much lower
	 * difficulty. Each additional bit doubles the expected number of hash attempts
	 * needed to find a valid solution (because you're halving the valid hash space).
	 *
	 * @param string $hex_hash The SHA-256 hash as a 64-character hex string.
	 * @param int    $required Number of leading zero bits required.
	 *
	 * @return bool True if the hash has at least $required leading zero bits.
	 */
	public static function has_leading_zero_bits( string $hex_hash, int $required ): bool {
		$bits_checked = 0;

		for ( $i = 0, $len = strlen( $hex_hash ); $i < $len && $bits_checked < $required; $i++ ) {
			$nibble = intval( $hex_hash[ $i ], 16 );

			// Each hex digit represents 4 bits. We check from the most significant
			// bit (MSB) to the least significant bit (LSB) within each nibble.
			for ( $bit = 3; $bit >= 0 && $bits_checked < $required; $bit-- ) {
				if ( $nibble & ( 1 << $bit ) ) {
					return false; // Found a 1-bit before reaching the required zero count.
				}
				++$bits_checked;
			}
		}

		return $bits_checked >= $required;
	}

	/**
	 * Checks if a nonce has been consumed by checking GF entry meta.
	 *
	 * This is the durable replay check — survives transient flushes,
	 * object cache restarts, and hosting migrations.
	 *
	 * @param string $nonce_key The hashed nonce key.
	 * @return bool True if the nonce exists in any entry's meta.
	 */
	private static function nonce_exists_in_entry_meta( string $nonce_key ): bool {
		if ( ! class_exists( '\GFFormsModel' ) ) {
			return false;
		}

		global $wpdb;

		$table = \GFFormsModel::get_entry_meta_table_name();

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM %i WHERE meta_key = 'gfsh_pow_nonce' AND meta_value = %s LIMIT 1",
				$table,
				$nonce_key
			)
		);
	}

	/**
	 * Parses a challenge string into its component parts.
	 *
	 * Accepts both 4-field (fallback) and 5-field (REST) challenges.
	 * For 4-field challenges, `nonce` is null.
	 *
	 * @param string $challenge The pipe-delimited challenge string.
	 *
	 * @return array{form_id: int, issued_at: int, expires_at: int, difficulty: int, nonce: string|null}|null
	 */
	public static function parse( string $challenge ): ?array {
		$parts      = explode( '|', $challenge );
		$part_count = count( $parts );

		if ( $part_count !== 4 && $part_count !== 5 ) {
			return null;
		}

		return [
			'form_id'    => (int) $parts[0],
			'issued_at'  => (int) $parts[1],
			'expires_at' => (int) $parts[2],
			'difficulty' => (int) $parts[3],
			'nonce'      => $part_count === 5 ? $parts[4] : null,
		];
	}
}
