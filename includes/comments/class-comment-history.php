<?php
/**
 * Comment history meta storage.
 *
 * Multiple timestamped entries per comment stored as non-unique comment meta.
 * Each entry records an
 * event (check-spam, check-ham, report-spam, etc.) with optional score
 * and technique breakdowns.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Comments;

/**
 * Comment meta read/write for spam analysis history.
 */
class Comment_History {

	const META_KEY_HISTORY = 'gfsh_history';
	const META_KEY_ACTION  = 'gfsh_action';
	const META_KEY_RESULT  = 'gfsh_result';

	/**
	 * Admin-triggered events where the acting user should be recorded.
	 *
	 * @var string[]
	 */
	private static $admin_events = [ 'report-spam', 'report-ham', 'trashed' ];

	/**
	 * Adds a history entry to a comment.
	 *
	 * Uses $unique = false to allow multiple entries per comment.
	 *
	 * @param int    $comment_id   The comment ID.
	 * @param string $event        Event code (check-spam, check-ham, report-spam, etc.).
	 * @param array  $result_array Optional spam result data.
	 * @param array  $meta         Optional additional metadata.
	 */
	public static function add_entry(
		int $comment_id,
		string $event,
		array $result_array = [],
		array $meta = []
	): void {
		$entry = [
			'time'  => microtime( true ),
			'event' => $event,
		];

		// Only record the acting user for admin-triggered events.
		if ( in_array( $event, self::$admin_events, true ) ) {
			$current_user = wp_get_current_user();

			if ( $current_user->exists() && ! empty( $current_user->user_login ) ) {
				$entry['user'] = $current_user->user_login;
			}
		}

		if ( ! empty( $result_array['action'] ) ) {
			$entry['action'] = $result_array['action'];
		}

		if ( ! empty( $result_array['technique_results'] ) ) {
			$entry['techniques'] = $result_array['technique_results'];
		}

		if ( ! empty( $meta ) ) {
			$entry['meta'] = $meta;
		}

		add_comment_meta( $comment_id, self::META_KEY_HISTORY, $entry, false );
	}

	/**
	 * Gets all history entries for a comment, sorted by time ascending.
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return array List of history entry arrays.
	 */
	public static function get_entries( int $comment_id ): array {
		$entries = get_comment_meta( $comment_id, self::META_KEY_HISTORY, false );

		if ( empty( $entries ) ) {
			return [];
		}

		usort( $entries, fn( $a, $b ) => ( $a['time'] ?? 0 ) <=> ( $b['time'] ?? 0 ) );

		return $entries;
	}
}
