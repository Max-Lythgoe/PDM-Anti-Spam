<?php
/**
 * Frontend init script configuration.
 *
 * Handles the frontend-specific logic that the main plugin class
 * delegates to:
 * 1. Per-form init script config assembly (PoW challenge, collector settings)
 * 2. Form applicability checks for frontend enqueueing
 *
 * Script registration itself stays in PDM_Antispam::scripts() (GFAddOn pattern),
 * and init script registration stays in PDM_Antispam::init() via
 * gform_register_init_scripts. This class provides the logic those hooks call.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Frontend;

use PDM_Antispam\Settings;
use PDM_Antispam\Submission_Context;
use PDM_Antispam\Techniques\PoW_Challenge;

/**
 * Frontend logic for init script configuration.
 */
class Frontend {

	// =========================================================================
	// Form Applicability
	// =========================================================================

	/**
	 * Determine if the frontend collector script should be enqueued for a form.
	 *
	 * Used as a callable in the `enqueue` condition of the `scripts()` array.
	 * GFAddOn calls this per-form and only enqueues if it returns true.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return bool
	 */
	public function should_enqueue( $form ): bool {
		// Don't enqueue on GF admin pages (form editor, entry detail, etc.).
		if ( \GFForms::get_page() ) {
			return false;
		}

		// Skip frontend scripts for logged-in users when bypass is active.
		if ( Settings::should_bypass_logged_in() && is_user_logged_in() && ! Settings::is_force_check() ) {
			return false;
		}

		return $this->is_applicable_form( $form );
	}

	/**
	 * Determine if spam protection applies to a given form.
	 *
	 * Checks per-form override first, then falls back to the global setting.
	 *
	 * @param array|int|false|null $form The GF form array, form ID, or falsy value.
	 *
	 * @return bool
	 */
	public function is_applicable_form( $form ): bool {
		if ( empty( $form ) ) {
			return false;
		}

		// Accept form ID or form array.
		if ( is_numeric( $form ) ) {
			$form = \GFAPI::get_form( (int) $form );
		}

		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			return false;
		}

		// Check per-form override via GFAddOn form settings API.
		/** @var array|false $form_settings */
		$form_settings = PDM_Antispam()->get_form_settings( $form );
		$form_enabled  = is_array( $form_settings ) ? rgar( $form_settings, 'gfsh_enabled', 'global' ) : 'global';

		if ( $form_enabled === 'disabled' ) {
			return false;
		}

		if ( $form_enabled === 'enabled' ) {
			return true;
		}

		// 'global' — use global setting.
		return Settings::is_enabled();
	}

	// =========================================================================
	// Init Scripts (Per-Form Collector Config)
	// =========================================================================

	/**
	 * Register per-form init scripts for the frontend collector.
	 *
	 * Called from `gform_register_init_scripts`. Injects a per-form
	 * initialization call that runs at `ON_PAGE_RENDER`. This replaces the
	 * old `wp_localize_script()` approach with dynamic global variables.
	 *
	 * GF calls this on every render (including AJAX re-renders), so the
	 * collector always gets a fresh config with a fresh PoW challenge.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return void
	 */
	public function add_init_scripts( $form ): void {
		if ( ! $this->is_applicable_form( $form ) ) {
			return;
		}

		// Skip init scripts for logged-in users when bypass is active.
		if ( Settings::should_bypass_logged_in() && is_user_logged_in() && ! Settings::is_force_check() ) {
			return;
		}

		$form_id = (int) rgar( $form, 'id' );

		$config = [
			'formId'       => $form_id,
			'powEnabled'   => Settings::is_technique_enabled_for_form( 'pow', $form ),
			'workerUrl'    => PDM_Antispam()->get_base_url() . '/js/built/pdm-antispam-pow-worker.js',
			'payloadField' => Submission_Context::PAYLOAD_FIELD,
			'powOnSubmit'  => Settings::is_pow_on_submit(),
			'hasPages'     => \GFCommon::has_pages( $form ),
		];

		// Embed a fallback PoW challenge for forms with PoW enabled.
		// Uses mint_fallback() (4-field, no server nonce) so the challenge is
		// safe to serve from full-page caches — each visitor generates their
		// own client nonce for replay protection.
		if ( $config['powEnabled'] ) {
			$difficulty             = Settings::get_pow_difficulty( $form_id );
			$config['powChallenge'] = PoW_Challenge::mint_fallback( $form_id, $difficulty );
		}

		$script = 'window.gfshInitCollector( ' . wp_json_encode( $config ) . ' );';

		\GFFormDisplay::add_init_script(
			$form_id,
			'gfsh_collector_' . $form_id,
			\GFFormDisplay::ON_PAGE_RENDER,
			$script
		);
	}
}
