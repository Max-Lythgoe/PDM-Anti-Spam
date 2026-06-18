<?php
/**
 * Comment form frontend integration.
 *
 * Injects the PoW challenge hidden field into WordPress comment forms
 * and enqueues the PoW solver script. Mirrors what Frontend does for
 * Gravity Forms, but uses the comment_form action and wp_enqueue_scripts.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Comments;

use PDM_Antispam\Settings;
use PDM_Antispam\Techniques\PoW_Challenge;

/**
 * Handles comment_form injection and script enqueueing for PoW.
 */
class Comment_Frontend {

	/**
	 * Registers frontend hooks.
	 */
	public function register_hooks(): void {
		add_action( 'comment_form', [ $this, 'inject_pow_fields' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Injects the PoW challenge hidden field into the comment form.
	 *
	 * Runs on the comment_form action. Outputs:
	 * 1. A hidden input for the collector payload
	 * 2. An inline script with the challenge config for the JS solver
	 */
	public function inject_pow_fields(): void {
		if ( ! Comment_Settings::is_enabled() || ! Comment_Settings::is_pow_enabled() ) {
			return;
		}

		if ( Comment_Settings::should_bypass_logged_in() && is_user_logged_in() ) {
			return;
		}

		$form_id    = Comment_Context::COMMENT_FORM_ID;
		$difficulty = Settings::get_pow_difficulty( $form_id );
		$challenge  = PoW_Challenge::mint( $form_id, $difficulty );

		// Hidden field that the JS collector will populate with the PoW solution.
		printf(
			'<input type="hidden" name="gfsh_payload" id="gfsh_comment_payload" value="" />%s',
			"\n"
		);

		// Inline challenge config for the JS solver.
		$config = [
			'formId'            => $form_id,
			'payloadFieldId'    => 'gfsh_comment_payload',
			'challenge'         => $challenge['challenge'],
			'challengeSig'      => $challenge['signature'],
			'difficulty'        => $difficulty,
			'challengeEndpoint' => rest_url( 'gfsh/v1/challenge' ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'isFallback'        => false,
		];

		printf(
			'<script>window.gfshCommentConfig = %s;</script>%s',
			wp_json_encode( $config ),
			"\n"
		);
	}

	/**
	 * Enqueues the comment PoW solver script on pages with comment forms.
	 *
	 * Only loads on singular pages where comments are open and the
	 * feature is enabled. Uses the webpack-built bundle from js/built/.
	 */
	public function enqueue_scripts(): void {
		if ( ! Comment_Settings::is_enabled() || ! Comment_Settings::is_pow_enabled() ) {
			return;
		}

		if ( ! is_singular() || ! comments_open() ) {
			return;
		}

		if ( Comment_Settings::should_bypass_logged_in() && is_user_logged_in() ) {
			return;
		}

		$plugin_dir = dirname( __DIR__, 2 );

		// Load dependencies from the webpack asset file if available.
		$asset_path = $plugin_dir . '/js/built/pdm-antispam-comment.asset.php';
		$asset_file = file_exists( $asset_path ) ? require $asset_path : [
			'dependencies' => [],
			'version'      => PDM_ANTISPAM_VERSION,
		];

		wp_enqueue_script(
			'gfsh-comment-collector',
			plugins_url( 'js/built/pdm-antispam-comment.js', $plugin_dir . '/pdm-antispam.php' ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
	}
}
