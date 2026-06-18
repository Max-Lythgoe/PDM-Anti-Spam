<?php
/**
 * Entry detail spam analysis display.
 *
 * Renders a meta box on the GF entry detail page showing the spam
 * analysis results: composite score with visual bar, per-technique
 * cards with inline details, and action taken.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Admin;

/**
 * Displays spam analysis details on the entry detail page.
 *
 * Shows the composite score, individual technique signals,
 * and the action taken for each entry.
 */
class Entry_Meta_Box {

	use Analysis_Renderer;

	/**
	 * Registers the meta box hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'gform_entry_detail_meta_boxes', [ $this, 'add_meta_box' ], 10, 3 );
	}

	/**
	 * Adds the Spam Hexer Analysis meta box to the entry detail page.
	 *
	 * Only adds the meta box if analysis data exists for the entry.
	 *
	 * @param array $meta_boxes Existing meta box definitions.
	 * @param array $entry      The entry being viewed.
	 * @param array $form       The form object.
	 *
	 * @return array Modified meta box definitions.
	 */
	public function add_meta_box( $meta_boxes, $entry, $form ) {
		$meta_boxes['gfsh_analysis'] = [
			'title'         => esc_html__( 'Spam Hexer Analysis', 'pdm-antispam' ),
			'callback'      => [ $this, 'render' ],
			'context'       => 'side',
			'callback_args' => [ $entry, $form ],
		];

		return $meta_boxes;
	}

	/**
	 * Renders the meta box content.
	 *
	 * @param array $args {
	 *     Meta box callback arguments from GF.
	 *
	 *     @type array $entry The entry being viewed.
	 *     @type array $form  The form object.
	 * }
	 *
	 * @return void
	 */
	public function render( $args ): void {
		$entry    = rgar( $args, 'entry', [] );
		$entry_id = (int) rgar( $entry, 'id' );

		if ( ! $entry_id ) {
			return;
		}

		$result_raw = gform_get_meta( $entry_id, 'gfsh_result' );
		$result     = $this->decode_result( $result_raw );

		if ( empty( $result ) ) {
			echo '<p>' . esc_html__( 'No spam analysis data available.', 'pdm-antispam' ) . '</p>';
			return;
		}

		$this->render_analysis( $result );
	}
}
