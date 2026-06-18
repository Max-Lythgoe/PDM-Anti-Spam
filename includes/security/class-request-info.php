<?php
/**
 * HTTP request information utility.
 *
 * Provides centralized, proxy-aware client identification for all
 * plugin components: PoW difficulty tracking, challenge generation,
 * and submission context.
 *
 * The client signal (IP + User-Agent) is NEVER stored directly — it
 * is only used as HMAC input with rotating window secrets for
 * GDPR-compliant tracking.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Security;

/**
 * Extracts client identification from the current HTTP request.
 */
class Request_Info {

	/**
	 * Gets the client IP address via Gravity Forms' proxy-aware IP resolution.
	 *
	 * Delegates to GFFormsModel::get_ip(), which applies the gform_ip_address
	 * filter. Site owners who have already configured that filter for Cloudflare,
	 * Sucuri, or other proxies get correct IP resolution here automatically.
	 *
	 * The IP is NEVER stored — it is only used as HMAC input with a rotating
	 * window secret for GDPR-compliant adaptive difficulty tracking.
	 *
	 * @return string The client IP address.
	 */
	public static function get_client_ip(): string {
		return (string) \GFFormsModel::get_ip();
	}

	/**
	 * Gets the User-Agent header.
	 *
	 * Never stored directly — only used as HMAC input for difficulty tracking.
	 *
	 * @return string The User-Agent string, or empty string if not present.
	 */
	public static function get_user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
	}

	/**
	 * Builds the client signal string for HMAC-based tracking.
	 *
	 * Combines IP + User-Agent. This value is NEVER stored — only
	 * used as input to HMAC with a rotating window secret.
	 *
	 * @return string The client signal (e.g., "192.168.1.1|Mozilla/5.0...").
	 */
	public static function get_client_signal(): string {
		return self::get_client_ip() . '|' . self::get_user_agent();
	}
}
