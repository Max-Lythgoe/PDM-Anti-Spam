<?php
/**
 * HMAC payload signing service.
 *
 * Provides centralized HMAC-SHA256 signing and verification for all
 * plugin components: PoW challenges and any data requiring
 * tamper-proof integrity.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Security;

/**
 * Signs and verifies payloads using HMAC-SHA256.
 *
 * Key hierarchy:
 * - Base secret: derived from WordPress AUTH_KEY + SECURE_AUTH_KEY, or a
 *   generated fallback stored in wp_options.
 * - Purpose keys: derived from the base secret via HMAC with a purpose string,
 *   so different subsystems (PoW, etc.) use independent keys.
 * - Window keys: derived from purpose keys with a time window, enabling
 *   automatic key rotation without explicit revocation.
 */
class Signing_Service {

	/**
	 * Option name for the fallback signing secret.
	 *
	 * Used only when WordPress AUTH_KEY and SECURE_AUTH_KEY are both empty
	 * (misconfigured wp-config.php).
	 */
	private const FALLBACK_OPTION = 'gfsh_signing_secret';

	/**
	 * Signs a message with HMAC-SHA256 using a purpose-specific key.
	 *
	 * @param string $message The message to sign.
	 * @param string $purpose Key purpose identifier (e.g., 'pow_challenge').
	 *
	 * @return string Hex-encoded HMAC signature.
	 */
	public static function sign( string $message, string $purpose = 'general' ): string {
		return hash_hmac( 'sha256', $message, self::get_purpose_key( $purpose ) );
	}

	/**
	 * Verifies an HMAC signature against a message.
	 *
	 * Uses timing-safe comparison to prevent timing attacks.
	 *
	 * @param string $message   The original message.
	 * @param string $signature The signature to verify.
	 * @param string $purpose   Key purpose identifier.
	 *
	 * @return bool True if the signature is valid.
	 */
	public static function verify( string $message, string $signature, string $purpose = 'general' ): bool {
		$expected = self::sign( $message, $purpose );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Signs a message with a time-windowed key.
	 *
	 * The key rotates every $window_seconds. After rotation, signatures
	 * from the previous window become invalid. Useful for GDPR-compliant
	 * tracking where old data must become unlinkable.
	 *
	 * @param string $message        The message to sign.
	 * @param string $purpose        Key purpose identifier.
	 * @param int    $window_seconds Window duration in seconds.
	 *
	 * @return string Hex-encoded HMAC signature.
	 */
	public static function sign_windowed( string $message, string $purpose, int $window_seconds ): string {
		$window_key = self::get_window_key( $purpose, $window_seconds );

		return hash_hmac( 'sha256', $message, $window_key );
	}

	/**
	 * Derives a purpose-specific signing key.
	 *
	 * Each purpose gets its own key derived from the base secret, so
	 * compromising one purpose's key doesn't affect others.
	 *
	 * @param string $purpose Key purpose identifier.
	 *
	 * @return string Derived key (hex-encoded).
	 */
	private static function get_purpose_key( string $purpose ): string {
		return hash_hmac( 'sha256', 'gfsh_' . $purpose, self::get_base_secret() );
	}

	/**
	 * Derives a time-windowed key for a given purpose.
	 *
	 * The window number is floor(time() / window_seconds), so the key
	 * changes at predictable intervals. The window secret is never stored —
	 * it's derived on the fly from the purpose key + window number.
	 *
	 * @param string $purpose        Key purpose identifier.
	 * @param int    $window_seconds Window duration in seconds.
	 *
	 * @return string Derived window key (hex-encoded).
	 */
	private static function get_window_key( string $purpose, int $window_seconds ): string {
		$window = (int) floor( time() / max( $window_seconds, 1 ) );

		return hash_hmac(
			'sha256',
			'gfsh_window_' . $window,
			self::get_purpose_key( $purpose )
		);
	}

	/**
	 * Gets the base signing secret.
	 *
	 * Derives from WordPress AUTH_KEY + SECURE_AUTH_KEY when available.
	 * Falls back to a generated secret stored in wp_options for sites
	 * with misconfigured wp-config.php.
	 *
	 * @return string The base secret.
	 */
	private static function get_base_secret(): string {
		$auth_key        = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$secure_auth_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';

		if ( $auth_key === '' && $secure_auth_key === '' ) {
			return self::get_fallback_secret();
		}

		return hash_hmac( 'sha256', 'gfsh_base_signing', $auth_key . $secure_auth_key );
	}

	/**
	 * Gets or generates the fallback signing secret.
	 *
	 * Only used when WordPress auth keys are missing. The secret is
	 * generated once and stored in wp_options with autoload disabled.
	 *
	 * @return string The fallback secret.
	 */
	private static function get_fallback_secret(): string {
		$secret = get_option( self::FALLBACK_OPTION );

		if ( ! $secret ) {
			$secret = wp_generate_password( 64, true, true );
			add_option( self::FALLBACK_OPTION, $secret, '', false );
		}

		return $secret;
	}
}
