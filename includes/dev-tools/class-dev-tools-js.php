<?php
/**
 * Dev Tools JS — Enqueues the GFSH dev-tools script.
 *
 * Hooks into the Dev Tools asset enqueue action to load the
 * gfsh-dev-tools.js bundle that registers action button handlers
 * for the QM panel.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\DevTools;

/**
 * Enqueues the GFSH dev-tools JavaScript when Dev Tools is active.
 */
class Dev_Tools_JS {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'gf_dev_tools_qm_assets_enqueued', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue the gfsh-dev-tools script.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_script(
			'gfsh-dev-tools',
			PDM_Antispam()->get_base_url() . '/js/built/gfsh-dev-tools.js',
			[ 'gf-debug-console' ],
			PDM_ANTISPAM_VERSION,
			true
		);
	}
}
