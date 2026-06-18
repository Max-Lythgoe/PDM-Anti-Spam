<?php
/**
 * Global and per-form plugin settings helper.
 *
 * Provides typed access to global plugin settings stored via GFAddOn's
 * plugin_settings mechanism, plus per-form override resolution.
 *
 * Acts as a convenience layer over the raw settings array returned by
 * PDM_Antispam::get_plugin_settings() and per-form `gfsh` meta.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam;

/**
 * Manages global plugin settings for PDM Anti-Spam.
 */
class Settings {

	/**
	 * Default settings values.
	 *
	 * Referenced by plugin_settings_fields() for default_value attributes
	 * and mirrored in JS via shared/constants/settings-defaults.ts.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = [
		'enabled'                 => '1',
		'bypass_logged_in'        => '1',
		'pow_enabled'             => '1',
		'pow_protection_level'    => 'standard',
		'pow_action'              => 'spam',
		'pow_fail_message'        => '',
		'ai_enabled'              => '0',
		'ai_provider'             => 'auto',
		'ai_api_key'              => '',
		'ai_model'                => '',
		'ai_custom_context'       => '',
		'ai_timeout'              => '10',
		'ai_zdr'                  => '0',
		'ai_action'               => 'spam',
		'ai_fail_message'         => '',
		'ai_confidence_threshold' => '0.50',
	];

	/**
	 * Get a global setting value with fallback to default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional override default. If null, uses DEFAULTS.
	 *
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$settings = PDM_Antispam()->get_plugin_settings();

		if ( ! is_array( $settings ) ) {
			return $default ?? ( self::DEFAULTS[ $key ] ?? null );
		}

		$value = rgar( $settings, $key );

		if ( $value === '' || $value === null ) {
			return $default ?? ( self::DEFAULTS[ $key ] ?? null );
		}

		return $value;
	}

	/**
	 * Check if spam protection is globally enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return (bool) self::get( 'enabled' );
	}

	/**
	 * Check if spam protection should be bypassed for logged-in users.
	 *
	 * @return bool
	 */
	public static function should_bypass_logged_in(): bool {
		return (bool) self::get( 'bypass_logged_in' );
	}

	/**
	 * Protection level presets mapping preset ID to difficulty in bits.
	 *
	 * @var array<string, int>
	 */
	public const POW_PRESETS = [
		'light'    => 13,
		'standard' => 15,
		'strict'   => 16,
	];

	/**
	 * Gets the PoW difficulty for a form.
	 *
	 * Resolves from the protection level preset.
	 *
	 * @param int $form_id The form ID (for filter context).
	 *
	 * @return int Difficulty in bits.
	 */
	public static function get_pow_difficulty( int $form_id = 0 ): int {
		$preset_id = (string) self::get( 'pow_protection_level', 'standard' );

		/**
		 * Filters the protection level preset ID for a form.
		 *
		 * @param string $preset_id The preset ID ('light', 'standard', 'strict').
		 * @param int    $form_id   The form ID.
		 */
		$preset_id = (string) apply_filters( 'gfsh_pow_protection_level', $preset_id, $form_id );

		$difficulty = self::POW_PRESETS[ $preset_id ] ?? self::POW_PRESETS['standard'];

		/**
		 * Filters the PoW difficulty for a form.
		 *
		 * @param int $difficulty Difficulty in bits.
		 * @param int $form_id   The form ID.
		 */
		return (int) apply_filters( 'gfsh_pow_difficulty', $difficulty, $form_id );
	}

	/**
	 * Gets the AI confidence threshold.
	 *
	 * Submissions with AI confidence >= this value are considered spam.
	 * Default: 0.50 (50% confidence).
	 *
	 * @return float
	 */
	public static function get_ai_confidence_threshold(): float {
		return (float) self::get( 'ai_confidence_threshold', '0.50' );
	}

	/**
	 * Check if a specific technique is enabled globally.
	 *
	 * @param string $technique Technique key: 'pow', 'ai'.
	 *
	 * @return bool
	 */
	public static function is_technique_enabled( string $technique ): bool {
		return (bool) self::get( $technique . '_enabled' );
	}

	/**
	 * Get the validation failure message for a technique, with per-form override support.
	 *
	 * Returns the configured custom message, or falls back to the built-in default
	 * when the setting is empty. Empty string means "use the default".
	 *
	 * @param string $technique Technique key: 'pow', 'ai'.
	 * @param array  $form      The GF form array.
	 *
	 * @return string The message to display in the form validation error.
	 */
	public static function get_fail_message( string $technique, array $form ): string {
		$msg = (string) self::get_for_form( $technique . '_fail_message', $form, '' );

		if ( $msg !== '' ) {
			return $msg;
		}

		return __( 'Your submission could not be processed. Please try again.', 'pdm-antispam' );
	}

	// =========================================================================
	// Per-Form Override Resolution
	// =========================================================================

	/**
	 * Get a setting value with per-form override support.
	 *
	 * Uses GFAddOn::get_form_settings() to retrieve the per-form settings
	 * stored under the addon's slug key (`pdm-antispam`). If the key exists
	 * and is non-empty, returns that value. Otherwise falls back to the
	 * global plugin setting via self::get().
	 *
	 * @param string $key     Setting key (e.g. 'pow_enabled', 'pow_action').
	 * @param array  $form    The GF form array.
	 * @param mixed  $default Optional override default passed to self::get().
	 *
	 * @return mixed
	 */
	public static function get_for_form( string $key, array $form, $default = null ) {
		/** @var array|false $form_settings */
		$form_settings = PDM_Antispam()->get_form_settings( $form );

		if ( is_array( $form_settings ) && isset( $form_settings[ $key ] ) && $form_settings[ $key ] !== '' ) {
			return $form_settings[ $key ];
		}

		return self::get( $key, $default );
	}

	/**
	 * Check if a specific technique is enabled for a given form.
	 *
	 * Checks per-form technique override first (from the `technique_overrides`
	 * JSON blob saved by the React form-settings UI), then falls back to the
	 * global setting.
	 *
	 * The JS form-settings UI stores per-technique enable/disable as a JSON
	 * object in the `technique_overrides` hidden field, e.g.:
	 *   {"pow_enabled":false,"ai_enabled":true}
	 *
	 * @param string $technique Technique key: 'pow', 'ai'.
	 * @param array  $form      The GF form array.
	 *
	 * @return bool
	 */
	public static function is_technique_enabled_for_form( string $technique, array $form ): bool {
		/** @var array|false $form_settings */
		$form_settings = PDM_Antispam()->get_form_settings( $form );

		if ( is_array( $form_settings ) ) {
			$overrides_json = rgar( $form_settings, 'technique_overrides', '' );
			if ( $overrides_json !== '' ) {
				$overrides = \GFCommon::maybe_decode_json( $overrides_json );
				if ( is_array( $overrides ) && array_key_exists( $technique . '_enabled', $overrides ) ) {
					return (bool) $overrides[ $technique . '_enabled' ];
				}
			}
		}

		// Fall back to global setting.
		return self::is_technique_enabled( $technique );
	}

	/**
	 * Get all default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return self::DEFAULTS;
	}

	// =========================================================================
	// Dev Tools / Force Check
	// =========================================================================

	/**
	 * Check if the current request has the force-check flag.
	 *
	 * When active, logged-in bypass is skipped so admins can test the full
	 * spam check pipeline from the frontend.
	 *
	 * @return bool
	 */
	public static function is_force_check(): bool {
		if ( ! \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['gfsh_force'] ) && $_GET['gfsh_force'] === '1';
	}

	/**
	 * Check if the current request has the PoW-on-submit flag.
	 *
	 * When active, the PoW challenge is deferred until form submission
	 * so admins can observe the "Verifying…" UX flow.
	 *
	 * @return bool
	 */
	public static function is_pow_on_submit(): bool {
		if ( ! \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['gfsh_pow_on_submit'] ) && $_GET['gfsh_pow_on_submit'] === '1';
	}
}
