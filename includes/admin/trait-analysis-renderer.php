<?php
/**
 * Shared spam analysis rendering logic.
 *
 * Provides card-based rendering for spam analysis results: action header
 * with badge, per-technique cards with expandable details, and
 * signal tag footer. Used by both the GF entry meta box and the
 * WordPress comment meta box.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Admin;

/**
 * Renders spam analysis results as styled cards.
 *
 * Outputs an action header (color-coded badge, optional AI confidence),
 * per-technique cards (icon, name, detail subtitle, status, expandable
 * detail grid), and a signals footer with tag-style badges.
 *
 * Classes using this trait must be in the PDM_Antispam\Admin namespace
 * or import it.
 */
trait Analysis_Renderer {

	/**
	 * Human-readable technique names.
	 *
	 * @return array<string, string>
	 */
	private static function technique_labels(): array {
		return [
			'pow' => 'Proof of Work',
			'ai'  => 'AI Classification',
		];
	}

	/**
	 * Technique icons as inline SVG markup.
	 *
	 * Uses currentColor so the icon inherits the color from the
	 * .gfsh-technique-icon--ok / --flagged / --skipped modifier classes.
	 *
	 * @return array<string, string>
	 */
	private static function technique_icons(): array {
		return [
			'pow' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M15.3899 4.39009C15.5156 4.51592 15.6726 4.60594 15.8446 4.65089C16.0167 4.69583 16.1977 4.69406 16.3689 4.64577C16.54 4.59747 16.6952 4.50439 16.8185 4.37613C16.9417 4.24787 17.0285 4.08906 17.0699 3.91609C17.1737 3.48399 17.3908 3.08736 17.6988 2.76707C18.0069 2.44677 18.3947 2.21437 18.8225 2.09381C19.2502 1.97325 19.7023 1.96888 20.1323 2.08116C20.5623 2.19343 20.9546 2.41829 21.2688 2.73258C21.5829 3.04687 21.8077 3.43922 21.9198 3.86924C22.0319 4.29926 22.0274 4.75139 21.9067 5.17908C21.786 5.60677 21.5535 5.99456 21.2331 6.3025C20.9127 6.61045 20.516 6.82743 20.0839 6.93109C19.9109 6.97249 19.7521 7.05927 19.6238 7.18249C19.4956 7.30571 19.4025 7.46091 19.3542 7.63208C19.3059 7.80326 19.3041 7.98422 19.3491 8.15631C19.394 8.3284 19.484 8.48539 19.6099 8.61109L21.2929 10.2931C21.517 10.5173 21.6949 10.7834 21.8162 11.0763C21.9375 11.3691 22 11.6831 22 12.0001C22 12.3171 21.9375 12.631 21.8162 12.9239C21.6949 13.2168 21.517 13.4829 21.2929 13.7071L19.6099 15.3901C19.4842 15.5159 19.3272 15.6059 19.1551 15.6509C18.983 15.6958 18.802 15.6941 18.6309 15.6458C18.4597 15.5975 18.3045 15.5044 18.1813 15.3761C18.058 15.2479 17.9713 15.0891 17.9299 14.9161C17.8261 14.484 17.6089 14.0874 17.3009 13.7671C16.9928 13.4468 16.605 13.2144 16.1773 13.0938C15.7495 12.9733 15.2974 12.9689 14.8674 13.0812C14.4374 13.1934 14.0451 13.4183 13.731 13.7326C13.4168 14.0469 13.1921 14.4392 13.0799 14.8692C12.9678 15.2993 12.9723 15.7514 13.093 16.1791C13.2137 16.6068 13.4462 16.9946 13.7666 17.3025C14.087 17.6104 14.4837 17.8274 14.9159 17.9311C15.0888 17.9725 15.2476 18.0593 15.3759 18.1825C15.5042 18.3057 15.5972 18.4609 15.6455 18.6321C15.6938 18.8033 15.6956 18.9842 15.6507 19.1563C15.6057 19.3284 15.5157 19.4854 15.3899 19.6111L13.7069 21.2931C13.4827 21.5173 13.2166 21.6951 12.9237 21.8164C12.6308 21.9377 12.3169 22.0002 11.9999 22.0002C11.6828 22.0002 11.3689 21.9377 11.076 21.8164C10.7831 21.6951 10.517 21.5173 10.2929 21.2931L8.60986 19.6101C8.48416 19.4843 8.32717 19.3942 8.15508 19.3493C7.983 19.3043 7.80204 19.3061 7.63086 19.3544C7.45968 19.4027 7.30448 19.4958 7.18126 19.624C7.05804 19.7523 6.97126 19.9111 6.92986 20.0841C6.82606 20.5162 6.60895 20.9128 6.3009 21.2331C5.99284 21.5534 5.60498 21.7858 5.17725 21.9064C4.74952 22.0269 4.29738 22.0313 3.86741 21.919C3.43743 21.8067 3.04515 21.5819 2.73096 21.2676C2.41678 20.9533 2.19205 20.561 2.07992 20.1309C1.96779 19.7009 1.9723 19.2488 2.09301 18.8211C2.21371 18.3934 2.44623 18.0056 2.76663 17.6977C3.08703 17.3897 3.48373 17.1728 3.91586 17.0691C4.08884 17.0277 4.24764 16.9409 4.3759 16.8177C4.50417 16.6945 4.59724 16.5393 4.64554 16.3681C4.69384 16.1969 4.6956 16.016 4.65066 15.8439C4.60572 15.6718 4.51569 15.5148 4.38986 15.3891L2.70686 13.7071C2.48269 13.4829 2.30486 13.2168 2.18354 12.9239C2.06222 12.631 1.99978 12.3171 1.99978 12.0001C1.99978 11.6831 2.06222 11.3691 2.18354 11.0763C2.30486 10.7834 2.48269 10.5173 2.70686 10.2931L4.38986 8.61009C4.51557 8.48426 4.67255 8.39423 4.84464 8.34929C5.01673 8.30435 5.19769 8.30612 5.36887 8.35441C5.54005 8.40271 5.69524 8.49579 5.81846 8.62405C5.94168 8.75231 6.02847 8.91111 6.06986 9.08409C6.17367 9.51619 6.39078 9.91281 6.69883 10.2331C7.00688 10.5534 7.39475 10.7858 7.82248 10.9064C8.25021 11.0269 8.70234 11.0313 9.13232 10.919C9.5623 10.8067 9.95458 10.5819 10.2688 10.2676C10.5829 9.95331 10.8077 9.56095 10.9198 9.13094C11.0319 8.70092 11.0274 8.24879 10.9067 7.8211C10.786 7.39341 10.5535 7.00562 10.2331 6.69767C9.91269 6.38973 9.516 6.17275 9.08386 6.06909C8.91089 6.02769 8.75208 5.94091 8.62382 5.81769C8.49556 5.69447 8.40248 5.53927 8.35419 5.36809C8.30589 5.19692 8.30412 5.01596 8.34906 4.84387C8.39401 4.67178 8.48403 4.51479 8.60986 4.38909L10.2929 2.70709C10.517 2.48291 10.7831 2.30509 11.076 2.18377C11.3689 2.06244 11.6828 2 11.9999 2C12.3169 2 12.6308 2.06244 12.9237 2.18377C13.2166 2.30509 13.4827 2.48291 13.7069 2.70709L15.3899 4.39009Z" fill="currentColor"/></svg>',
			'ai'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M21.0002 21.0002L16.6602 16.6602" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.5 3.14062C16.2013 3.84273 19 7.09465 19 11.0002C19 15.4185 15.4182 19.0002 11 19.0002C7.01363 19.0002 3.70829 16.0846 3.1001 12.2695" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.34 0L7.574 0.6318C7.8236 1.3104 8.3618 1.8486 9.0482 2.106L9.68 2.34L9.0482 2.574C8.3696 2.8236 7.8314 3.3618 7.574 4.0482L7.34 4.68L7.106 4.0482C6.8564 3.3696 6.3182 2.8314 5.6318 2.574L5 2.34L5.6318 2.106C6.3104 1.8564 6.8486 1.3182 7.106 0.6318L7.34 0Z" fill="currentColor"/><path d="M2.34 5L2.574 5.6318C2.8236 6.3104 3.3618 6.8486 4.0482 7.106L4.68 7.34L4.0482 7.574C3.3696 7.8236 2.8314 8.3618 2.574 9.0482L2.34 9.68L2.106 9.0482C1.8564 8.3696 1.3182 7.8314 0.631799 7.574L0 7.34L0.631799 7.106C1.3104 6.8564 1.8486 6.3182 2.106 5.6318L2.34 5Z" fill="currentColor"/><path d="M9.1 5.5L9.46 6.472C9.844 7.516 10.672 8.344 11.728 8.74L12.7 9.1L11.728 9.46C10.684 9.844 9.856 10.672 9.46 11.728L9.1 12.7L8.74 11.728C8.356 10.684 7.528 9.856 6.472 9.46L5.5 9.1L6.472 8.74C7.516 8.356 8.344 7.528 8.74 6.472L9.1 5.5Z" fill="currentColor"/></svg>',
		];
	}

	// =========================================================================
	// Full Result Rendering
	// =========================================================================

	/**
	 * Renders a complete analysis result (action header + technique cards + signals).
	 *
	 * Handles special states (pre_flagged, bypassed) and normal results.
	 *
	 * @param array<string, mixed> $result The decoded result array.
	 *
	 * @return void
	 */
	protected function render_analysis( array $result ): void {
		$action = (string) ( $result['action'] ?? 'allow' );

		if ( $action === 'pre_flagged' ) {
			$this->render_pre_flagged( $result );
			return;
		}

		if ( $action === 'bypassed' ) {
			$this->render_bypassed( $result );
			return;
		}

		$this->render_action_header( $action );
		$this->render_technique_cards( $result['technique_results'] ?? [] );
		$this->render_signals_footer( $result['signals'] ?? [] );
	}

	// =========================================================================
	// Render: Pre-flagged
	// =========================================================================

	/**
	 * Renders the pre-flagged state (entry was already marked spam by another filter).
	 *
	 * @param array<string, mixed> $result The decoded result array.
	 *
	 * @return void
	 */
	protected function render_pre_flagged( array $result ): void {
		$prior = $result['prior_filter'] ?? null;

		echo '<div class="gfsh-meta-box">';
		echo '<div class="gfsh-score-header">';

		// Badge.
		printf(
			'<div class="gfsh-score-row">'
			. '<span class="gfsh-score-badge gfsh-score-badge--pre-flagged">%s</span>'
			. '</div>',
			esc_html__( 'Pre-flagged', 'pdm-antispam' )
		);

		echo '</div>'; // .gfsh-score-header

		// Prior filter info.
		if ( $prior ) {
			echo '<div class="gfsh-techniques">';
			echo '<div class="gfsh-technique-card">';
			echo '<div class="gfsh-technique-header">';

			printf(
				'<div class="gfsh-technique-icon gfsh-technique-icon--flagged">'
				. '<span class="dashicons dashicons-shield"></span>'
				. '</div>'
			);

			printf(
				'<div class="gfsh-technique-info">'
				. '<div class="gfsh-technique-name">%s</div>'
				. '<div class="gfsh-technique-detail">%s</div>'
				. '</div>',
				esc_html( $prior['filter'] ?? __( 'External Filter', 'pdm-antispam' ) ),
				esc_html( $prior['reason'] ?? '' )
			);

			echo '</div>'; // .gfsh-technique-header
			echo '</div>'; // .gfsh-technique-card
			echo '</div>'; // .gfsh-techniques
		} else {
			printf(
				'<p class="gfsh-pre-flagged-note">%s</p>',
				esc_html__( 'Flagged before Spam Hexer ran', 'pdm-antispam' )
			);
		}

		echo '</div>'; // .gfsh-meta-box
	}

	// =========================================================================
	// Render: Bypassed
	// =========================================================================

	/**
	 * Renders the bypassed state (logged-in user bypass).
	 *
	 * @param array<string, mixed> $result The decoded result array.
	 *
	 * @return void
	 */
	protected function render_bypassed( array $result ): void {
		$user = $result['bypassed_user'] ?? '';

		echo '<div class="gfsh-meta-box">';
		echo '<div class="gfsh-score-header">';

		// Badge.
		printf(
			'<div class="gfsh-score-row">'
			. '<span class="gfsh-score-badge gfsh-score-badge--bypassed">%s</span>'
			. '</div>',
			esc_html__( 'Bypassed', 'pdm-antispam' )
		);

		echo '</div>'; // .gfsh-score-header

		// User info.
		echo '<div class="gfsh-techniques">';
		echo '<div class="gfsh-technique-card">';
		echo '<div class="gfsh-technique-header">';

		printf(
			'<div class="gfsh-technique-icon gfsh-technique-icon--ok">'
			. '<span class="dashicons dashicons-admin-users"></span>'
			. '</div>'
		);

		printf(
			'<div class="gfsh-technique-info">'
			. '<div class="gfsh-technique-name">%s</div>'
			. '<div class="gfsh-technique-detail">%s</div>'
			. '</div>',
			esc_html__( 'Logged-in User', 'pdm-antispam' ),
			esc_html( $user )
		);

		echo '</div>'; // .gfsh-technique-header
		echo '</div>'; // .gfsh-technique-card
		echo '</div>'; // .gfsh-techniques

		echo '</div>'; // .gfsh-meta-box
	}

	// =========================================================================
	// Render: Action Header
	// =========================================================================

	/**
	 * Renders the action header with badge and action row.
	 *
	 * @param string $action The action taken.
	 *
	 * @return void
	 */
	protected function render_action_header( string $action ): void {
		$label       = $this->get_action_display_label( $action );
		$badge_class = $this->get_badge_class( $action );
		$action_text = $this->get_action_label( $action );
		$action_icon = $this->get_action_icon( $action );

		echo '<div class="gfsh-meta-box">';
		echo '<div class="gfsh-score-header">';

		// Action badge.
		printf(
			'<div class="gfsh-score-row">'
			. '<span class="gfsh-score-badge %s">%s</span>'
			. '</div>',
			esc_attr( $badge_class ),
			esc_html( $label )
		);

		// Action row.
		printf(
			'<div class="gfsh-action-row">'
			. '<span class="dashicons dashicons-%s"></span>'
			. '<span>%s</span>'
			. '</div>',
			esc_attr( $action_icon ),
			esc_html( $action_text )
		);

		echo '</div>'; // .gfsh-score-header
	}

	// =========================================================================
	// Render: Technique Cards
	// =========================================================================

	/**
	 * Renders technique result cards with expandable details.
	 *
	 * @param array<string, array<string, mixed>> $technique_results Keyed by technique name.
	 *
	 * @return void
	 */
	protected function render_technique_cards( array $technique_results ): void {
		if ( empty( $technique_results ) ) {
			return;
		}

		echo '<div class="gfsh-techniques">';

		foreach ( $technique_results as $name => $data ) {
			$this->render_technique_card( $name, $data );
		}

		echo '</div>';
	}

	/**
	 * Renders a single technique card.
	 *
	 * @param string               $name The technique name.
	 * @param array<string, mixed> $data The technique result data.
	 *
	 * @return void
	 */
	protected function render_technique_card( string $name, array $data ): void {
		$is_spam  = $this->resolve_is_spam( $data );
		$signals  = (array) ( $data['signals'] ?? [] );
		$metadata = (array) ( $data['metadata'] ?? [] );
		$skipped  = ! empty( $metadata['skipped'] );

		$label      = self::technique_labels()[ $name ] ?? ucfirst( $name );
		$icon_class = self::technique_icons()[ $name ] ?? 'admin-generic';
		$detail     = $this->format_technique_detail( $name, $is_spam, $signals, $metadata, $skipped );
		$details    = $this->collect_technique_details( $name, $data );

		// Card modifier class.
		$card_class = 'gfsh-technique-card';
		if ( $skipped ) {
			$card_class .= ' gfsh-technique-card--skipped';
		} elseif ( $is_spam ) {
			$card_class .= ' gfsh-technique-card--flagged';
		}

		// Icon modifier.
		$icon_mod = $skipped ? 'skipped' : ( $is_spam ? 'flagged' : 'ok' );

		// Status display.
		$status_display = $skipped ? "\xE2\x80\x94" : ( $is_spam ? __( 'SPAM', 'pdm-antispam' ) : __( 'OK', 'pdm-antispam' ) );
		$status_mod     = $skipped ? 'skipped' : ( $is_spam ? 'flagged' : 'ok' );

		$has_details = ! empty( $details );
		$card_id     = 'gfsh-card-' . esc_attr( $name );

		printf( '<div class="%s" id="%s">', esc_attr( $card_class ), esc_attr( $card_id ) );

		// Header row.
		printf(
			'<div class="gfsh-technique-header"%s%s>',
			$has_details ? ' data-expandable' : '',
			$has_details ? ' onclick="this.closest(\'.gfsh-technique-card\').classList.toggle(\'is-open\')"' : ''
		);

		// Icon (inline SVG — hardcoded, no escaping needed).
		printf(
			'<div class="gfsh-technique-icon gfsh-technique-icon--%s">%s</div>',
			esc_attr( $icon_mod ),
			$icon_class
		);

		// Name + detail.
		printf(
			'<div class="gfsh-technique-info">'
			. '<div class="gfsh-technique-name">%s</div>'
			. '<div class="gfsh-technique-detail">%s</div>'
			. '</div>',
			esc_html( $label ),
			esc_html( $detail )
		);

		// Status badge.
		printf(
			'<span class="gfsh-technique-score gfsh-technique-score--%s">%s</span>',
			esc_attr( $status_mod ),
			esc_html( $status_display )
		);

		// Chevron (only if expandable).
		if ( $has_details ) {
			echo '<span class="gfsh-technique-chevron"><span class="dashicons dashicons-arrow-down-alt2"></span></span>';
		}

		echo '</div>'; // .gfsh-technique-header

		// Details panel.
		if ( $has_details ) {
			echo '<div class="gfsh-technique-details">';
			echo '<div class="gfsh-detail-grid">';

			foreach ( $details as $dl => $dv ) {
				printf(
					'<span class="gfsh-detail-label">%s</span>'
					. '<span class="gfsh-detail-value">%s</span>',
					esc_html( $dl ),
					esc_html( $dv )
				);
			}

			echo '</div>'; // .gfsh-detail-grid
			echo '</div>'; // .gfsh-technique-details
		}

		echo '</div>'; // .gfsh-technique-card
	}

	// =========================================================================
	// Render: Signals Footer
	// =========================================================================

	/**
	 * Renders the signals footer with tag-style badges.
	 *
	 * @param string[] $signals All signal codes from the result.
	 *
	 * @return void
	 */
	protected function render_signals_footer( array $signals ): void {
		if ( ! empty( $signals ) ) {
			echo '<div class="gfsh-signals">';
			echo '<div class="gfsh-signals-label">' . esc_html__( 'Signals', 'pdm-antispam' ) . '</div>';
			echo '<div class="gfsh-signal-tags">';

			foreach ( $signals as $signal ) {
				printf( '<span class="gfsh-signal-tag">%s</span>', esc_html( $signal ) );
			}

			echo '</div>'; // .gfsh-signal-tags
			echo '</div>'; // .gfsh-signals
		}

		echo '</div>'; // .gfsh-meta-box
	}

	// =========================================================================
	// Per-Technique Detail Collection
	// =========================================================================

	/**
	 * Collects detail key-value pairs for a single technique's expandable panel.
	 *
	 * Delegates to the technique's format_details() method.
	 *
	 * @param string               $name The technique name.
	 * @param array<string, mixed> $data The technique result data.
	 *
	 * @return array<string, string> Label => value pairs.
	 */
	protected function collect_technique_details( string $name, array $data ): array {
		$metadata = (array) ( $data['metadata'] ?? [] );

		if ( ! empty( $metadata['skipped'] ) ) {
			return [];
		}

		$technique = $this->get_technique_instance( $name );
		if ( $technique ) {
			return $technique->format_details( $data );
		}

		return [];
	}

	// =========================================================================
	// Formatting Helpers
	// =========================================================================

	/**
	 * Gets a human-readable display label for the action (used in badges).
	 *
	 * @param string $action The action taken.
	 *
	 * @return string
	 */
	protected function get_action_display_label( string $action ): string {
		if ( $action === 'pre_flagged' ) {
			return __( 'Pre-flagged', 'pdm-antispam' );
		}
		if ( $action === 'bypassed' ) {
			return __( 'Bypassed', 'pdm-antispam' );
		}
		if ( $action === 'reject' ) {
			return __( 'Rejected', 'pdm-antispam' );
		}
		if ( $action === 'mark_spam' ) {
			return __( 'Spam', 'pdm-antispam' );
		}

		return __( 'Clean', 'pdm-antispam' );
	}

	/**
	 * Gets the badge CSS class for the action.
	 *
	 * @param string $action The action taken.
	 *
	 * @return string CSS class.
	 */
	protected function get_badge_class( string $action ): string {
		if ( $action === 'reject' ) {
			return 'gfsh-score-badge--rejected';
		}
		if ( $action === 'mark_spam' ) {
			return 'gfsh-score-badge--spam';
		}
		if ( $action === 'pre_flagged' ) {
			return 'gfsh-score-badge--pre-flagged';
		}
		if ( $action === 'bypassed' ) {
			return 'gfsh-score-badge--bypassed';
		}

		return 'gfsh-score-badge--clean';
	}

	/**
	 * Gets a human-readable label for the action.
	 *
	 * @param string $action The action code.
	 *
	 * @return string
	 */
	protected function get_action_label( string $action ): string {
		$labels = [
			'allow'       => __( 'Allowed', 'pdm-antispam' ),
			'mark_spam'   => __( 'Marked as spam', 'pdm-antispam' ),
			'reject'      => __( 'Rejected', 'pdm-antispam' ),
			'pre_flagged' => __( 'Pre-flagged by another filter', 'pdm-antispam' ),
			'bypassed'    => __( 'Bypassed for logged-in user', 'pdm-antispam' ),
		];

		return $labels[ $action ] ?? $action;
	}

	/**
	 * Gets the dashicon name for the action.
	 *
	 * @param string $action The action code.
	 *
	 * @return string Dashicon class suffix.
	 */
	protected function get_action_icon( string $action ): string {
		$icons = [
			'allow'     => 'yes-alt',
			'mark_spam' => 'warning',
			'reject'    => 'dismiss',
		];

		return $icons[ $action ] ?? 'info';
	}

	/**
	 * Formats the detail string for a technique result.
	 *
	 * Delegates to the technique's format_headline() method for non-skipped results.
	 *
	 * @param string               $name     Technique name.
	 * @param bool                 $is_spam  Whether the technique flagged spam.
	 * @param string[]             $signals  Signal codes.
	 * @param array<string, mixed> $metadata Technique metadata.
	 * @param bool                 $skipped  Whether the technique was skipped.
	 *
	 * @return string
	 */
	protected function format_technique_detail( string $name, bool $is_spam, array $signals, array $metadata, bool $skipped ): string {
		if ( $skipped ) {
			$reason = $this->extract_skip_reason( $signals );
			return sprintf(
				/* translators: %s: skip reason */
				__( 'Skipped (%s)', 'pdm-antispam' ),
				$reason
			);
		}

		$technique = $this->get_technique_instance( $name );
		if ( $technique ) {
			return $technique->format_headline( [
				'is_spam'  => $is_spam,
				'signals'  => $signals,
				'metadata' => $metadata,
			] );
		}

		// Fallback for unknown techniques.
		if ( $is_spam && ! empty( $signals ) ) {
			return implode( ', ', $signals );
		}
		return $is_spam ? __( 'Flagged', 'pdm-antispam' ) : __( 'Clean', 'pdm-antispam' );
	}

	/**
	 * Gets a technique instance by name from the spam checker.
	 *
	 * @param string $name The technique name (e.g. 'pow', 'ai').
	 *
	 * @return \PDM_Antispam\Techniques\Technique|null
	 */
	private function get_technique_instance( string $name ): ?\PDM_Antispam\Techniques\Technique {
		$checker = PDM_Antispam()->spam_checker ?? null;
		if ( ! $checker ) {
			return null;
		}
		return $checker->get_technique( $name );
	}

	/**
	 * Extracts the skip reason from signal codes.
	 *
	 * @param string[] $signals Signal codes (e.g., ['skipped_disabled']).
	 *
	 * @return string The reason portion after 'skipped_'.
	 */
	protected function extract_skip_reason( array $signals ): string {
		foreach ( $signals as $signal ) {
			if ( str_starts_with( $signal, 'skipped_' ) ) {
				return substr( $signal, 8 );
			}
		}

		return __( 'unknown', 'pdm-antispam' );
	}

	/**
	 * Resolves the is_spam flag from technique result data.
	 *
	 * Handles backward compatibility with entries stored before the
	 * score→is_spam migration (commit 50c4730). Old entries stored a
	 * float `score` (0.8 = spam, 0.0 = clean) instead of a boolean
	 * `is_spam`. Falls back to inferring from `score >= 0.8`.
	 *
	 * @param array<string, mixed> $data The technique result data.
	 *
	 * @return bool Whether the technique flagged spam.
	 */
	protected function resolve_is_spam( array $data ): bool {
		if ( isset( $data['is_spam'] ) ) {
			return (bool) $data['is_spam'];
		}

		// Legacy format: infer from score field (score >= 0.8 was the spam threshold).
		if ( isset( $data['score'] ) ) {
			return ( (float) $data['score'] ) >= 0.8;
		}

		return false;
	}

	/**
	 * Decodes the stored result data.
	 *
	 * Handles both JSON string and already-decoded array.
	 *
	 * @param mixed $raw The raw stored value.
	 *
	 * @return array<string, mixed>|null Decoded result or null.
	 */
	protected function decode_result( $raw ): ?array {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : null;
		}

		return null;
	}
}
