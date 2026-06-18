<?php
/**
 * Blocked-submission event recorder.
 *
 * Writes one row to {$wpdb->prefix}gfsh_events for every spam-check outcome
 * (fail, reject, mark_spam, allow, bypassed, pre_flagged) from both GF and
 * WordPress comments. This table is the single source of truth for all
 * dashboard aggregate stats, replacing the entry-meta JOIN approach that
 * broke when entries were deleted.
 *
 * Table schema (created by PDM_Antispam::upgrade()):
 *
 *   CREATE TABLE {$wpdb->prefix}gfsh_events (
 *       id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 *       source      VARCHAR(20)  NOT NULL,   -- 'gf' | 'comment'
 *       form_id     BIGINT UNSIGNED NULL,    -- GF form id (null for comments)
 *       action      VARCHAR(20)  NOT NULL,   -- 'fail'|'reject'|'mark_spam'|'allow'|'bypassed'|'pre_flagged'
 *       signals     LONGTEXT     NULL,       -- JSON Spam_Result::to_array() blob
 *       created_at  DATETIME     NOT NULL,   -- UTC
 *       PRIMARY KEY (id),
 *       KEY idx_action_created (action, created_at),
 *       KEY idx_source_form_created (source, form_id, created_at),
 *       KEY idx_created (created_at)
 *   );
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam;

/**
 * Records every spam-check outcome to the gfsh_events table.
 *
 * Two entry points:
 *   - record_blocked() — for fail/reject paths where no entry/comment row exists.
 *   - record_event()   — for all other outcomes (mark_spam, allow, bypassed, pre_flagged).
 *
 * Both are no-ops if the table doesn't exist yet (pre-migration safety).
 */
class Event_Recorder {

	/**
	 * Source: a Gravity Forms submission.
	 */
	const SOURCE_GF = 'gf';

	/**
	 * Source: a WordPress comment submission.
	 */
	const SOURCE_COMMENT = 'comment';

	/**
	 * Returns the fully-qualified events table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gfsh_events';
	}

	/**
	 * Records a blocked submission that produced no entry/comment row.
	 *
	 * Convenience wrapper around record_event() for the fail/reject paths.
	 *
	 * @param string               $source  One of the SOURCE_* constants.
	 * @param string               $action  The blocking action: 'fail' | 'reject'.
	 * @param array<string, mixed> $context {
	 *     Optional contextual data.
	 *
	 *     @type int|null $form_id GF form id (null for comments).
	 *     @type array    $signals Spam_Result::to_array() blob.
	 * }
	 *
	 * @return void
	 */
	public static function record_blocked( string $source, string $action, array $context = [] ): void {
		self::record_event( $source, $action, $context );
	}

	/**
	 * Records any spam-check outcome to the events table.
	 *
	 * Safe to call before the table exists — silently no-ops if the table
	 * is missing (e.g., during the activation request itself).
	 *
	 * @param string               $source  One of the SOURCE_* constants.
	 * @param string               $action  Outcome: 'fail'|'reject'|'mark_spam'|'allow'|'bypassed'|'pre_flagged'.
	 * @param array<string, mixed> $context {
	 *     Optional contextual data.
	 *
	 *     @type int|null $form_id GF form id (null for comments).
	 *     @type array    $signals Spam_Result::to_array() blob.
	 * }
	 *
	 * @return void
	 */
	public static function record_event( string $source, string $action, array $context = [] ): void {
		global $wpdb;

		$table = self::table_name();

		// Guard: silently skip if table doesn't exist yet.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		$form_id = isset( $context['form_id'] ) ? (int) $context['form_id'] : null;
		$signals = isset( $context['signals'] ) ? wp_json_encode( $context['signals'] ) : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'source'     => $source,
				'form_id'    => $form_id,
				'action'     => $action,
				'signals'    => $signals,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%d', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Creates the gfsh_events table via dbDelta.
	 *
	 * Called from PDM_Antispam::upgrade() on plugin version change.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source      VARCHAR(20)  NOT NULL,
			form_id     BIGINT UNSIGNED NULL,
			action      VARCHAR(20)  NOT NULL,
			signals     LONGTEXT     NULL,
			created_at  DATETIME     NOT NULL,
			PRIMARY KEY (id),
			KEY idx_action_created (action, created_at),
			KEY idx_source_form_created (source, form_id, created_at),
			KEY idx_created (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drops the gfsh_events table.
	 *
	 * Called from PDM_Antispam::uninstall() when the addon is removed via the GF UI.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table_name() ) );
	}
}
