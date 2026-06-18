<?php
/**
 * REST endpoint for PoW challenges.
 *
 * This controller provides the primary path for fetching Proof-of-Work challenges.
 * It's the "AJAX tier" of the two-tier challenge delivery system:
 *
 * 1. **Primary (this endpoint):** The JS collector calls `POST /wp-json/gfsh/v1/challenge`
 *    on page load. This returns a fresh challenge with adaptive difficulty based on the
 *    client's submission history and current form volume. POST requests naturally bypass
 *    full-page caches (Cloudflare, WP Super Cache, etc.).
 *
 * 2. **Fallback (embedded in HTML):** A challenge at base difficulty is embedded in the
 *    form HTML via `wp_localize_script`. If this endpoint is blocked (ad blocker, firewall),
 *    the client falls back to solving the embedded challenge.
 *
 * The endpoint is public (no authentication required) because every visitor — logged in
 * or anonymous — needs a challenge to submit the form. Rate limiting is handled implicitly
 * by the adaptive difficulty system: each request from the same client makes the next
 * puzzle harder, so bot-driven request floods just make their own puzzles unsolvable.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\REST;

use PDM_Antispam\Settings;
use PDM_Antispam\Techniques\PoW_Challenge;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for Proof-of-Work challenges.
 *
 * Registers `POST /wp-json/gfsh/v1/challenge` and returns a signed challenge
 * with adaptive difficulty. The client-side `pow-manager.ts` calls this endpoint
 * on form render, then hands the challenge to `pow-worker.ts` for solving.
 */
class Challenge_Controller {

	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'gfsh/v1';

	/**
	 * Route path.
	 */
	private const ROUTE = '/challenge';

	/**
	 * Default challenge TTL in seconds (10 minutes).
	 *
	 * This is the lifetime of AJAX-fetched challenges. The fallback challenge
	 * embedded in HTML uses a much longer TTL (1 week) to survive page caching.
	 */
	private const DEFAULT_TTL = 600;

	/**
	 * Registers the REST route.
	 *
	 * Called via `rest_api_init` action. The route accepts POST only — GET would
	 * be cacheable by CDNs and proxies, defeating the purpose of fresh challenges.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'get_challenge' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'form_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => [ $this, 'validate_form_id' ],
						'description'       => 'The Gravity Forms form ID to generate a challenge for.',
					],
				],
			]
		);
	}

	/**
	 * Validates the form_id parameter.
	 *
	 * Ensures the form ID is a positive integer. We intentionally do NOT check
	 * whether the form exists in the database — that would add a DB query to
	 * every challenge request, and a non-existent form ID just means the challenge
	 * will fail verification at submission time (form_id mismatch).
	 *
	 * @param mixed $value The parameter value.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_form_id( $value ) {
		$form_id = absint( $value );

		if ( $form_id < 1 ) {
			return new WP_Error(
				'gfsh_invalid_form_id',
				__( 'form_id must be a positive integer.', 'pdm-antispam' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Handles the challenge request.
	 *
	 * Builds a client signal from IP + User-Agent (never stored, only HMAC'd),
	 * calculates adaptive difficulty, mints a signed challenge, and returns it.
	 *
	 * Response shape (matches what `pow-manager.ts` expects):
	 * ```json
	 * {
	 *   "challenge":  "42|1711979400|1711980000|18|a1b2c3...",
	 *   "signature":  "f4e5d6...",
	 *   "difficulty": 18,
	 *   "expires":    1711980000
	 * }
	 * ```
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The challenge data.
	 */
	public function get_challenge( WP_REST_Request $request ): WP_REST_Response {
		$form_id    = $request->get_param( 'form_id' );
		$difficulty = Settings::get_pow_difficulty( (int) $form_id );

		/**
		 * Filters the challenge TTL for dynamically fetched challenges.
		 *
		 * The TTL determines how long the client has to solve and submit the puzzle.
		 * Shorter TTLs are more secure (less time for offline brute-force) but may
		 * cause issues for users who leave forms open for a long time.
		 *
		 * @since 1.0.0
		 *
		 * @param int $ttl     Challenge lifetime in seconds. Default: 600 (10 minutes).
		 * @param int $form_id The form ID the challenge is for.
		 */
		$ttl = (int) apply_filters( 'gfsh_pow_challenge_ttl', self::DEFAULT_TTL, $form_id );

		// Mint the signed challenge.
		$challenge_data = PoW_Challenge::mint( $form_id, $difficulty, $ttl );

		// Prevent caching of this response. POST requests are naturally excluded
		// from most page caches, but this is belt-and-suspenders for edge cases
		// (aggressive CDN configs, misconfigured caching plugins).
		nocache_headers();

		return new WP_REST_Response( $challenge_data, 200 );
	}
}
