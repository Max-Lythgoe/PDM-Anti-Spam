<?php
/**
 * Hooks into the Gravity Forms entry list to render styled badges for the
 * gfsh_action and gfsh_result meta columns registered by PDM_Antispam.
 *
 * - gfsh_action → compact badge with a GF tooltip showing technique breakdown
 * - gfsh_result → badge + inline expanded text (action + signals), no tooltip
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Admin;

/**
 * Renders entry list column values for spam analysis meta.
 *
 * Uses the Analysis_Renderer trait for shared formatting helpers.
 */
class Entry_List_Column {

	use Analysis_Renderer;

	/**
	 * Registers the column value filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'gform_entries_field_value', [ $this, 'display_list_value' ], 10, 4 );
	}

	// =========================================================================
	// Hook Callbacks
	// =========================================================================

	/**
	 * Renders a styled badge for the gfsh_action and gfsh_result entry list columns.
	 *
	 * Both meta keys are registered by PDM_Antispam::get_entry_meta(). This
	 * replaces the raw action string / raw JSON with a compact visual badge.
	 *
	 * - gfsh_action → badge + GF tooltip with technique breakdown
	 * - gfsh_result → per-technique rows (no badge duplication)
	 *
	 * @param string $value    The current column value.
	 * @param int    $form_id  The form ID.
	 * @param string $field_id The field/meta key being rendered.
	 * @param array  $entry    The entry array.
	 *
	 * @return string Modified column value.
	 */
	public function display_list_value( string $value, int $form_id, string $field_id, array $entry ): string {
		if ( $field_id !== 'gfsh_action' && $field_id !== 'gfsh_result' ) {
			return $value;
		}

		$entry_id   = (int) rgar( $entry, 'id' );
		$result_raw = gform_get_meta( $entry_id, 'gfsh_result' );
		$result     = $this->decode_result( $result_raw );

		if ( empty( $result ) ) {
			return $value;
		}

		$action = (string) ( $result['action'] ?? 'allow' );

		if ( $field_id === 'gfsh_action' ) {
			// Compact badge with tooltip showing full technique breakdown.
			$tooltip = $this->format_action_tooltip( $result );
			return $this->render_badge_with_tooltip( $action, $tooltip );
		}

		// gfsh_result: per-technique rows showing each technique's result.
		return $this->render_technique_rows( $result );
	}

	// =========================================================================
	// Rendering
	// =========================================================================

	/**
	 * Renders a badge wrapped in a GF tooltip button.
	 *
	 * GF's gform_initialize_tooltips() picks up any element with class
	 * "gf_tooltip" and an aria-label attribute, rendering the label as HTML.
	 *
	 * @param string $action  The action taken.
	 * @param string $tooltip HTML tooltip content stored in aria-label.
	 *
	 * @return string HTML markup.
	 */
	private function render_badge_with_tooltip( string $action, string $tooltip ): string {
		$badge = $this->render_badge( $action );

		return sprintf(
			'<button type="button" class="gf_tooltip gfsh-list-tooltip" aria-label="%s">%s</button>',
			esc_attr( $tooltip ),
			$badge
		);
	}

	/**
	 * Renders per-technique rows for the gfsh_result column.
	 *
	 * Shows each technique's name, status, and detail inline — giving a
	 * richer view than the gfsh_action badge without duplicating it.
	 *
	 * @param array<string, mixed> $result The full decoded result array.
	 *
	 * @return string HTML markup.
	 */
	private function render_technique_rows( array $result ): string {
		$technique_results = (array) ( $result['technique_results'] ?? [] );

		if ( empty( $technique_results ) ) {
			return $this->render_context_row( $result );
		}

		$rows = [];

		foreach ( $technique_results as $name => $data ) {
			$is_spam  = $this->resolve_is_spam( $data );
			$metadata = (array) ( $data['metadata'] ?? [] );
			$signals  = (array) ( $data['signals'] ?? [] );
			$skipped  = ! empty( $metadata['skipped'] );
			$label    = self::technique_labels()[ $name ] ?? ucfirst( $name );
			$detail   = $this->format_technique_detail( $name, $is_spam, $signals, $metadata, $skipped );

			$status_mod = $skipped ? 'skipped' : ( $is_spam ? 'flagged' : 'ok' );
			$status_txt = $skipped ? '—' : ( $is_spam ? __( 'SPAM', 'pdm-antispam' ) : __( 'OK', 'pdm-antispam' ) );

			$rows[] = sprintf(
				'<div class="gfsh-list-technique">'
				. '<div class="gfsh-list-technique__header">'
				. '<span class="gfsh-list-technique__name">%s</span>'
				. '<span class="gfsh-list-technique__score gfsh-list-technique__score--%s">%s</span>'
				. '</div>'
				. '<div class="gfsh-list-technique__detail">%s</div>'
				. '</div>',
				esc_html( $label . ':' ),
				esc_attr( $status_mod ),
				esc_html( $status_txt ),
				esc_html( $detail )
			);
		}

		return implode( '', $rows );
	}

	/**
	 * Renders the core badge span (icon + label).
	 *
	 * @param string $action The action taken.
	 *
	 * @return string HTML badge markup.
	 */
	private function render_badge( string $action ): string {
		$label     = $this->get_action_display_label( $action );
		$badge_mod = $this->get_badge_modifier( $action );
		$icon      = $this->get_badge_icon( $action );

		return sprintf(
			'<span class="gfsh-list-badge gfsh-list-badge--%s">'
			. '<span class="dashicons dashicons-%s" aria-hidden="true"></span>'
			. '<span class="gfsh-list-badge__label">%s</span>'
			. '</span>',
			esc_attr( $badge_mod ),
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	// =========================================================================
	// Tooltip Content (gfsh_action)
	// =========================================================================

	/**
	 * Formats the tooltip HTML for the gfsh_action column.
	 *
	 * Produces an HTML string stored in aria-label with action
	 * and a per-technique breakdown. GF renders this as HTML via
	 * gform_strip_scripts() inside gform_initialize_tooltips().
	 *
	 * @param array<string, mixed> $result The decoded result array.
	 *
	 * @return string Tooltip HTML content.
	 */
	private function format_action_tooltip( array $result ): string {
		$action = (string) ( $result['action'] ?? 'allow' );

		if ( $action === 'pre_flagged' ) {
			return $this->format_pre_flagged_tooltip( $result );
		}

		if ( $action === 'bypassed' ) {
			return $this->format_bypassed_tooltip( $result );
		}

		$parts = [];

		$parts[] = sprintf(
			'<strong>%s</strong> %s',
			esc_html__( 'Action:', 'pdm-antispam' ),
			esc_html( $this->get_action_label( $action ) )
		);

		$technique_results = (array) ( $result['technique_results'] ?? [] );
		if ( ! empty( $technique_results ) ) {
			$parts[] = '<strong>' . esc_html__( 'Techniques:', 'pdm-antispam' ) . '</strong>';

			foreach ( $technique_results as $name => $data ) {
				$is_spam  = $this->resolve_is_spam( $data );
				$metadata = (array) ( $data['metadata'] ?? [] );
				$signals  = (array) ( $data['signals'] ?? [] );
				$skipped  = ! empty( $metadata['skipped'] );
				$label    = self::technique_labels()[ $name ] ?? ucfirst( $name );
				$detail   = $this->format_technique_detail( $name, $is_spam, $signals, $metadata, $skipped );

				$parts[] = sprintf(
					'&nbsp;&nbsp;• %s: %s',
					esc_html( $label ),
					esc_html( $detail )
				);
			}
		}

		$signals = (array) ( $result['signals'] ?? [] );
		if ( ! empty( $signals ) ) {
			$parts[] = sprintf(
				'<strong>%s</strong> %s',
				esc_html__( 'Signals:', 'pdm-antispam' ),
				esc_html( implode( ', ', $signals ) )
			);
		}

		return implode( '<br>', $parts );
	}

	// =========================================================================
	// Formatting Helpers
	// =========================================================================

	/**
	 * Formats the tooltip for a pre-flagged entry.
	 *
	 * @param array<string, mixed> $result The decoded result array.
	 *
	 * @return string Tooltip HTML content.
	 */
	private function format_pre_flagged_tooltip( array $result ): string {
		$prior = $result['prior_filter'] ?? null;
		$parts = [];

		if ( $prior ) {
			$parts[] = sprintf(
				'<strong>%s</strong> %s',
				esc_html__( 'Flagged by:', 'pdm-antispam' ),
				esc_html( $prior['filter'] ?? __( 'Unknown', 'pdm-antispam' ) )
			);
			if ( ! empty( $prior['reason'] ) ) {
				$parts[] = sprintf(
					'<strong>%s</strong> %s',
					esc_html__( 'Reason:', 'pdm-antispam' ),
					esc_html( $prior['reason'] )
				);
			}
		} else {
			$parts[] = esc_html__( 'Flagged by another spam filter before Spam Hexer ran.', 'pdm-antispam' );
		}

		return implode( '<br>', $parts );
	}

	/**
	 * Formats the tooltip for a bypassed (logged-in) entry.
	 *
	 * @param array<string, mixed> $result The decoded result array.
	 *
	 * @return string Tooltip HTML content.
	 */
	private function format_bypassed_tooltip( array $result ): string {
		$user = $result['bypassed_user'] ?? '';
		return sprintf(
			'<strong>%s</strong> %s',
			esc_html__( 'Bypassed for logged-in user:', 'pdm-antispam' ),
			esc_html( $user )
		);
	}

	/**
	 * Renders a context row for entries with no technique results.
	 *
	 * @param array<string, mixed> $result The decoded result array.
	 *
	 * @return string HTML markup, or empty string.
	 */
	private function render_context_row( array $result ): string {
		$action = (string) ( $result['action'] ?? 'allow' );

		if ( $action === 'pre_flagged' ) {
			$prior  = $result['prior_filter'] ?? null;
			$name   = $prior['filter'] ?? __( 'External Filter', 'pdm-antispam' );
			$detail = $prior['reason'] ?? __( 'Flagged by another spam filter', 'pdm-antispam' );

			return sprintf(
				'<div class="gfsh-list-technique">'
				. '<span class="gfsh-list-technique__name">%s</span>'
				. '<span class="gfsh-list-technique__detail">%s</span>'
				. '</div>',
				esc_html( $name ),
				esc_html( $detail )
			);
		}

		if ( $action === 'bypassed' ) {
			$user = $result['bypassed_user'] ?? '';

			return sprintf(
				'<div class="gfsh-list-technique">'
				. '<span class="gfsh-list-technique__name">%s</span>'
				. '<span class="gfsh-list-technique__detail">%s</span>'
				. '</div>',
				esc_html__( 'Logged-in User', 'pdm-antispam' ),
				esc_html( $user )
			);
		}

		return esc_html__( '—', 'pdm-antispam' );
	}

	// =========================================================================
	// Formatting Helpers (column-specific)
	// =========================================================================

	/**
	 * Gets the BEM modifier for the badge.
	 *
	 * @param string $action The action taken.
	 *
	 * @return string BEM modifier string.
	 */
	private function get_badge_modifier( string $action ): string {
		if ( $action === 'pre_flagged' ) {
			return 'pre-flagged';
		}
		if ( $action === 'bypassed' ) {
			return 'bypassed';
		}
		if ( $action === 'reject' ) {
			return 'rejected';
		}
		if ( $action === 'mark_spam' ) {
			return 'spam';
		}

		return 'clean';
	}

	/**
	 * Gets the dashicon name for the badge.
	 *
	 * @param string $action The action taken.
	 *
	 * @return string Dashicon class suffix.
	 */
	private function get_badge_icon( string $action ): string {
		if ( $action === 'pre_flagged' ) {
			return 'shield';
		}
		if ( $action === 'bypassed' ) {
			return 'admin-users';
		}
		if ( $action === 'reject' ) {
			return 'dismiss';
		}
		if ( $action === 'mark_spam' ) {
			return 'warning';
		}

		return 'yes-alt';
	}
}
