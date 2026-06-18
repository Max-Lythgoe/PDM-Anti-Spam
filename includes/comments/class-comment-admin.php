<?php
/**
 * Comment admin integration.
 *
 * Registers a meta box on the comment edit screen showing the Spam Hexer
 * analysis with card-based UI (score header, technique cards, signals)
 * and a brief history timeline for admin status transitions.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Comments;

use PDM_Antispam\Admin\Analysis_Renderer;

/**
 * Meta box registration and status transition logging for comments.
 */
class Comment_Admin {

	use Analysis_Renderer;

	/**
	 * Registers admin hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'transition_comment_status', [ $this, 'log_status_transition' ], 10, 3 );
	}

	/**
	 * Registers the Spam Hexer Analysis meta box on the comment edit screen.
	 */
	public function register_meta_box(): void {
		if ( ! Comment_Settings::is_enabled() ) {
			return;
		}

		add_meta_box(
			'gfsh-comment-history',
			__( 'Spam Hexer Analysis', 'pdm-antispam' ),
			[ $this, 'render_meta_box' ],
			'comment',
			'normal',
			'default'
		);
	}

	/**
	 * Enqueues the analysis meta box stylesheet on the comment edit screen.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_styles( string $hook_suffix ): void {
		if ( $hook_suffix !== 'comment.php' ) {
			return;
		}

		if ( ! Comment_Settings::is_enabled() ) {
			return;
		}

		$plugin_dir = dirname( __DIR__, 2 );

		wp_enqueue_style(
			'gfsh-analysis-meta-box',
			plugins_url( 'assets/css/entry-meta-box.css', $plugin_dir . '/pdm-antispam.php' ),
			[],
			defined( 'PDM_ANTISPAM_VERSION' ) ? PDM_ANTISPAM_VERSION : '1.0'
		);
	}

	/**
	 * Renders the Spam Hexer Analysis meta box.
	 *
	 * Shows the latest analysis result as a card-based display (score header,
	 * technique cards with expandable details, signal tags), followed by a
	 * brief history timeline for admin status transitions.
	 *
	 * @param \WP_Comment $comment The comment object.
	 */
	public function render_meta_box( \WP_Comment $comment ): void {
		$entries = Comment_History::get_entries( (int) $comment->comment_ID );

		if ( empty( $entries ) ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'No Spam Hexer analysis recorded for this comment.', 'pdm-antispam' )
			);
			return;
		}

		// Find the latest check event (check-spam or check-ham) for the analysis card.
		$check_entry = $this->find_latest_check( $entries );

		if ( $check_entry ) {
			$this->render_check_analysis( $check_entry );
		}

		// Render admin action history timeline (report-spam, report-ham, trashed).
		$admin_entries = $this->get_admin_entries( $entries );

		if ( ! empty( $admin_entries ) ) {
			$this->render_history_timeline( $admin_entries );
		}
	}

	/**
	 * Logs admin status transitions to comment history.
	 *
	 * When an admin approves a spam comment or marks a ham comment as spam,
	 * this records the action in the comment's Spam Hexer history.
	 *
	 * @param string      $new_status The new comment status.
	 * @param string      $old_status The old comment status.
	 * @param \WP_Comment $comment    The comment object.
	 */
	public function log_status_transition( string $new_status, string $old_status, \WP_Comment $comment ): void {
		if ( $new_status === $old_status ) {
			return;
		}

		// Only log transitions for comments that have Spam Hexer history.
		$existing = Comment_History::get_entries( (int) $comment->comment_ID );
		if ( empty( $existing ) ) {
			return;
		}

		$event = null;

		if ( in_array( $new_status, [ 'approved', 'hold', 'unapproved' ], true ) && $old_status === 'spam' ) {
			$event = 'report-ham'; // Admin un-spammed a comment.
		} elseif ( $new_status === 'spam' && in_array( $old_status, [ 'approved', 'hold', 'unapproved' ], true ) ) {
			$event = 'report-spam'; // Admin marked a ham/pending comment as spam.
		} elseif ( $new_status === 'trash' ) {
			$event = 'trashed';
		}

		if ( $event !== null ) {
			Comment_History::add_entry( (int) $comment->comment_ID, $event );
		}
	}

	// =========================================================================
	// Analysis Card Rendering
	// =========================================================================

	/**
	 * Renders the analysis card for a check event.
	 *
	 * Builds a result array from the history entry and delegates to the
	 * shared Analysis_Renderer trait.
	 *
	 * @param array<string, mixed> $entry The check history entry.
	 */
	private function render_check_analysis( array $entry ): void {
		$result = [
			'action'            => $entry['action'] ?? ( ( $entry['event'] ?? '' ) === 'check-spam' ? 'mark_spam' : 'allow' ),
			'technique_results' => $entry['techniques'] ?? [],
			'signals'           => $this->collect_signals_from_techniques( $entry['techniques'] ?? [] ),
		];

		$this->render_analysis( $result );
	}

	/**
	 * Collects all signal codes from technique results.
	 *
	 * @param array<string, array<string, mixed>> $techniques The technique results.
	 *
	 * @return string[]
	 */
	private function collect_signals_from_techniques( array $techniques ): array {
		$signals = [];

		foreach ( $techniques as $data ) {
			if ( ! empty( $data['signals'] ) && is_array( $data['signals'] ) ) {
				$signals = array_merge( $signals, $data['signals'] );
			}
		}

		return $signals;
	}

	// =========================================================================
	// History Timeline
	// =========================================================================

	/**
	 * Renders a brief timeline of admin status transitions.
	 *
	 * @param array<int, array<string, mixed>> $entries Admin action entries.
	 */
	private function render_history_timeline( array $entries ): void {
		echo '<div class="gfsh-history-timeline">';
		echo '<div class="gfsh-history-label">' . esc_html__( 'History', 'pdm-antispam' ) . '</div>';

		foreach ( $entries as $entry ) {
			$time = isset( $entry['time'] )
				? sprintf(
					/* translators: %s: human-readable time difference */
					esc_html__( '%s ago', 'pdm-antispam' ),
					human_time_diff( (int) $entry['time'] )
				)
				: '';
			$message = $this->format_history_message( $entry );

			if ( empty( $message ) ) {
				continue;
			}

			$full_date = isset( $entry['time'] )
				? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['time'] )
				: '';

			echo '<div class="gfsh-history-event">';

			printf(
				'<span class="dashicons dashicons-%s gfsh-history-icon"></span>',
				esc_attr( $this->get_history_icon( $entry['event'] ?? '' ) )
			);

			if ( $time ) {
				printf(
					'<span class="gfsh-history-time" title="%s">%s</span>',
					esc_attr( $full_date ),
					esc_html( $time )
				);
				echo '<span class="gfsh-history-sep">&mdash;</span>';
			}

			printf( '<span class="gfsh-history-message">%s</span>', esc_html( $message ) );

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Formats a human-readable message for a history event.
	 *
	 * @param array<string, mixed> $entry The history entry.
	 *
	 * @return string
	 */
	private function format_history_message( array $entry ): string {
		$event = $entry['event'] ?? '';
		$user  = $entry['user'] ?? '';

		switch ( $event ) {
			case 'report-spam':
				return $user
					/* translators: %s: username */
					? sprintf( __( '%s reported as spam', 'pdm-antispam' ), $user )
					: __( 'Reported as spam', 'pdm-antispam' );

			case 'report-ham':
				return $user
					/* translators: %s: username */
					? sprintf( __( '%s reported as not spam', 'pdm-antispam' ), $user )
					: __( 'Reported as not spam', 'pdm-antispam' );

			case 'trashed':
				return $user
					/* translators: %s: username */
					? sprintf( __( '%s trashed', 'pdm-antispam' ), $user )
					: __( 'Trashed', 'pdm-antispam' );

			default:
				return '';
		}
	}

	/**
	 * Gets the dashicon name for a history event.
	 *
	 * @param string $event The event code.
	 *
	 * @return string Dashicon class suffix.
	 */
	private function get_history_icon( string $event ): string {
		$icons = [
			'report-spam' => 'warning',
			'report-ham'  => 'yes-alt',
			'trashed'     => 'trash',
		];

		return $icons[ $event ] ?? 'info';
	}

	// =========================================================================
	// Entry Filtering
	// =========================================================================

	/**
	 * Finds the latest check event (check-spam or check-ham) from history entries.
	 *
	 * @param array<int, array<string, mixed>> $entries All history entries (sorted ascending).
	 *
	 * @return array<string, mixed>|null The latest check entry, or null.
	 */
	private function find_latest_check( array $entries ): ?array {
		$check_entry = null;

		foreach ( $entries as $entry ) {
			$event = $entry['event'] ?? '';
			if ( $event === 'check-spam' || $event === 'check-ham' ) {
				$check_entry = $entry;
			}
		}

		return $check_entry;
	}

	/**
	 * Filters history entries to only admin-triggered events.
	 *
	 * @param array<int, array<string, mixed>> $entries All history entries.
	 *
	 * @return array<int, array<string, mixed>> Only admin action entries.
	 */
	private function get_admin_entries( array $entries ): array {
		$admin_events = [ 'report-spam', 'report-ham', 'trashed' ];

		return array_values(
			array_filter(
				$entries,
				fn( $entry ) => in_array( $entry['event'] ?? '', $admin_events, true )
			)
		);
	}
}
