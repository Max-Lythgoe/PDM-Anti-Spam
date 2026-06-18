<?php
/**
 * Dashboard statistics queries and caching.
 *
 * Provides aggregate query methods for the plugin settings dashboard.
 *
 * Primary source: {$wpdb->prefix}gfsh_events — written for every spam-check
 * outcome (fail, reject, mark_spam, allow, bypassed, pre_flagged) from both
 * GF and WordPress comments. This table is immune to entry/comment deletion,
 * so "Last 30 days" stats remain accurate even after admins empty the trash.
 *
 * Secondary source (per-entry detail only): entry meta / comment meta written
 * by write_dashboard_meta() — used only by the entry meta box, not here.
 *
 * JSON queries (PoW/AI signal breakdowns) use JSON_EXTRACT on the `signals`
 * column of gfsh_events. Requires MySQL 5.7+ / MariaDB 10.2.3+.
 * If JSON functions are unavailable, get_stats() returns null and the
 * dashboard is hidden.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Admin;

use PDM_Antispam\Event_Recorder;

/**
 * Static utility class for dashboard aggregate queries.
 *
 * All query methods accept a $since datetime string (UTC) and return
 * associative arrays ready for JSON serialization into the React dashboard.
 */
class Dashboard_Stats {

	/**
	 * Detects whether the database supports MySQL JSON functions.
	 *
	 * Uses a probe query rather than version string parsing, which is
	 * unreliable on managed hosts, Aurora, PlanetScale, etc.
	 *
	 * Result is cached in a static variable for the lifetime of the request.
	 *
	 * @return bool True if JSON_EXTRACT is available.
	 */
	public static function supports_json_queries(): bool {
		static $result = null;

		if ( $result !== null ) {
			return $result;
		}

		global $wpdb;

		$wpdb->suppress_errors( true );
		$test = $wpdb->get_var( "SELECT JSON_EXTRACT('{\"ok\":1}', '$.ok')" );
		$wpdb->suppress_errors( false );

		$result = ( $test !== null );

		return $result;
	}

	/**
	 * The only reason code that indicates a legitimate (non-spam) submission.
	 *
	 * All other reason codes from the AI prompt (generic_sales, seo_spam,
	 * phishing, url_stuffing, gibberish, template_text, off_topic) are spam.
	 */
	private const HAM_REASON = 'ham';

	/**
	 * Assembles all dashboard stats.
	 *
	 * Returns null if the database doesn't support JSON functions
	 * (MySQL 5.7+ / MariaDB 10.2.3+ required).
	 *
	 * @param int $days Number of days to look back. Default 30.
	 *
	 * @return array<string, mixed>|null Stats array for React injection, or null if unsupported.
	 */
	public static function get_stats( int $days = 30 ): ?array {
		if ( ! self::supports_json_queries() ) {
			return null;
		}

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$stats = [
			'period_days'  => $days,
			'generated_at' => time(),
			'summary'      => self::query_summary( $since ),
			'pow'          => self::query_pow_stats( $since ),
			'ai'           => self::query_ai_stats( $since ),
			'per_form'     => self::query_per_form( $since ),
			'actions'      => self::query_action_distribution( $since ),
			'pow_signals'  => self::query_pow_signal_distribution( $since ),
			'ai_reasons'   => self::query_ai_reason_distribution( $since ),
			'ai_signals'   => self::query_ai_signal_distribution( $since ),
		];

		// Enrich AI stats with pre-computed ham/spam counts from reason codes.
		if ( $stats['ai'] !== null && ! empty( $stats['ai_reasons'] ) ) {
			[ $ham, $spam ]                 = self::aggregate_ham_spam( $stats['ai_reasons'] );
			$stats['ai']['classified_ham']  = $ham;
			$stats['ai']['classified_spam'] = $spam;
		}

		// Comment stats — only if comment protection is enabled.
		if ( \PDM_Antispam\Comments\Comment_Settings::is_enabled() ) {
			$stats['comments'] = self::query_comment_stats( $since );
		}

		return $stats;
	}

	/**
	 * Aggregates ham/spam counts from AI reason code distribution.
	 *
	 * Only "ham" is a non-spam reason; all other reason codes from the
	 * AI prompt (generic_sales, seo_spam, phishing, url_stuffing,
	 * gibberish, template_text, off_topic) indicate spam.
	 *
	 * @param array<int, array{reason: string, count: int}> $reasons Reason distribution rows.
	 *
	 * @return array{0: int, 1: int} [ ham_count, spam_count ].
	 */
	private static function aggregate_ham_spam( array $reasons ): array {
		$ham  = 0;
		$spam = 0;

		foreach ( $reasons as $r ) {
			if ( $r['reason'] === self::HAM_REASON ) {
				$ham += $r['count'];
			} else {
				$spam += $r['count'];
			}
		}

		return [ $ham, $spam ];
	}

	// =========================================================================
	// Query Methods — GF (events table, source = 'gf')
	// =========================================================================

	/**
	 * Queries summary stats: total checked, spam/clean counts.
	 *
	 * Spam = mark_spam | pre_flagged | fail | reject.
	 * Clean = allow | bypassed.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array{total_checked: int, spam_count: int, clean_count: int}
	 */
	private static function query_summary( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_checked,
					SUM(CASE WHEN action IN ('mark_spam','pre_flagged','fail','reject') THEN 1 ELSE 0 END) AS spam_count,
					SUM(CASE WHEN action IN ('allow','bypassed') THEN 1 ELSE 0 END) AS clean_count
				FROM %i
				WHERE source = %s
				AND created_at >= %s",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		if ( ! $row ) {
			return [
				'total_checked' => 0,
				'spam_count'    => 0,
				'clean_count'   => 0,
			];
		}

		return [
			'total_checked' => (int) $row->total_checked,
			'spam_count'    => (int) $row->spam_count,
			'clean_count'   => (int) $row->clean_count,
		];
	}

	/**
	 * Queries PoW performance stats from the events table signals JSON.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array{avg_solve_ms: float, min_solve_ms: int, max_solve_ms: int, total_solves: int, difficulty_distribution: array, fallback_count: int, rest_count: int}
	 */
	private static function query_pow_stats( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// Solve time stats — from events where PoW solve_time_ms is present in signals.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$solve_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS avg_solve_ms,
					MIN(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS min_solve_ms,
					MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS max_solve_ms,
					COUNT(*) AS total_solves
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms') IS NOT NULL
				AND created_at >= %s",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		// Difficulty distribution.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$difficulty_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.difficulty')) AS difficulty,
					COUNT(*) AS solve_count,
					AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS avg_solve_ms
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.difficulty') IS NOT NULL
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms') IS NOT NULL
				AND created_at >= %s
				GROUP BY difficulty
				ORDER BY CAST(difficulty AS UNSIGNED)",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		// Fallback vs REST split.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$fallback_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.is_fallback')) AS is_fallback,
					COUNT(*) AS count
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.is_fallback') IS NOT NULL
				AND created_at >= %s
				GROUP BY is_fallback",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		$fallback_count = 0;
		$rest_count     = 0;
		foreach ( (array) $fallback_rows as $row ) {
			if ( $row->is_fallback === 'true' || $row->is_fallback === '1' ) {
				$fallback_count = (int) $row->count;
			} else {
				$rest_count = (int) $row->count;
			}
		}

		$difficulty_distribution = [];
		foreach ( (array) $difficulty_rows as $row ) {
			$difficulty_distribution[] = [
				'difficulty' => (int) $row->difficulty,
				'count'      => (int) $row->solve_count,
				'avg_ms'     => round( (float) $row->avg_solve_ms ),
			];
		}

		return [
			'avg_solve_ms'            => $solve_row && $solve_row->total_solves ? round( (float) $solve_row->avg_solve_ms ) : 0.0,
			'min_solve_ms'            => $solve_row && $solve_row->total_solves ? (int) $solve_row->min_solve_ms : 0,
			'max_solve_ms'            => $solve_row && $solve_row->total_solves ? (int) $solve_row->max_solve_ms : 0,
			'total_solves'            => $solve_row ? (int) $solve_row->total_solves : 0,
			'difficulty_distribution' => $difficulty_distribution,
			'fallback_count'          => $fallback_count,
			'rest_count'              => $rest_count,
		];
	}

	/**
	 * Queries AI classification stats from the events table signals JSON.
	 *
	 * Returns null if no AI data exists in the period.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array{avg_latency_ms: float, total_cost: float, total_ai_calls: int}|null
	 */
	private static function query_ai_stats( string $since ): ?array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.latency_ms')) AS UNSIGNED)) AS avg_latency_ms,
					SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.cost')) AS DECIMAL(10,6))) AS total_cost,
					COUNT(*) AS total_ai_calls
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.ai.metadata.latency_ms') IS NOT NULL
				AND created_at >= %s",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		if ( ! $row || ! $row->total_ai_calls ) {
			return null;
		}

		return [
			'avg_latency_ms' => round( (float) $row->avg_latency_ms ),
			'total_cost'     => round( (float) $row->total_cost, 4 ),
			'total_ai_calls' => (int) $row->total_ai_calls,
		];
	}

	/**
	 * Queries per-form breakdown with form title resolution.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array<int, array{form_id: int, form_title: string, total_checked: int, spam_count: int, spam_rate: float, last_spam: string|null}>
	 */
	private static function query_per_form( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					form_id,
					COUNT(*) AS total_checked,
					SUM(CASE WHEN action IN ('mark_spam','pre_flagged','fail','reject') THEN 1 ELSE 0 END) AS spam_count,
					MAX(CASE WHEN action IN ('mark_spam','pre_flagged','fail','reject') THEN created_at ELSE NULL END) AS last_spam
				FROM %i
				WHERE source = %s
				AND form_id IS NOT NULL
				AND created_at >= %s
				GROUP BY form_id
				ORDER BY spam_count DESC",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		if ( ! $rows ) {
			return [];
		}

		$forms_data = [];
		foreach ( $rows as $row ) {
			$form         = \GFAPI::get_form( (int) $row->form_id );
			$total        = (int) $row->total_checked;
			$spam         = (int) $row->spam_count;
			$forms_data[] = [
				'form_id'       => (int) $row->form_id,
				'form_title'    => $form ? rgar( $form, 'title', '' ) : '',
				'total_checked' => $total,
				'spam_count'    => $spam,
				'spam_rate'     => $total > 0 ? round( $spam / $total * 100, 1 ) : 0.0,
				'last_spam'     => $row->last_spam,
			];
		}

		return $forms_data;
	}

	/**
	 * Queries action distribution counts.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array<int, array{action: string, count: int}>
	 */
	private static function query_action_distribution( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					action,
					COUNT(*) AS count
				FROM %i
				WHERE source = %s
				AND created_at >= %s
				GROUP BY action',
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		if ( ! $rows ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			$result[] = [
				'action' => (string) $row->action,
				'count'  => (int) $row->count,
			];
		}

		return $result;
	}

	// =========================================================================
	// JSON Query Methods — GF (signals column in events table)
	// =========================================================================

	/**
	 * Queries PoW signal distribution from the events table signals JSON.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array<int, array{signal: string, count: int}>
	 */
	private static function query_pow_signal_distribution( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.signals[0]')) AS pow_signal,
					COUNT(*) AS count
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.is_spam') = CAST('true' AS JSON)
				AND created_at >= %s
				GROUP BY pow_signal
				ORDER BY count DESC",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		if ( ! $rows ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			if ( $row->pow_signal === null ) {
				continue;
			}
			$result[] = [
				'signal' => (string) $row->pow_signal,
				'count'  => (int) $row->count,
			];
		}

		return $result;
	}

	/**
	 * Queries AI reason code distribution from the events table signals JSON.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array<int, array{reason: string, count: int, avg_probability: float}>
	 */
	private static function query_ai_reason_distribution( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.reason')) AS ai_reason,
					COUNT(*) AS count,
					AVG(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.raw_probability')) AS avg_probability
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.ai.metadata.reason') IS NOT NULL
				AND created_at >= %s
				GROUP BY ai_reason
				ORDER BY count DESC",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		if ( ! $rows ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			if ( $row->ai_reason === null ) {
				continue;
			}
			$result[] = [
				'reason'          => (string) $row->ai_reason,
				'count'           => (int) $row->count,
				'avg_probability' => round( (float) $row->avg_probability, 3 ),
			];
		}

		return $result;
	}

	/**
	 * Queries AI signal distribution from the events table signals JSON.
	 *
	 * Captures skip/bypass signals (skipped_disabled, short_circuited,
	 * ai_error) — entries where the AI technique was not executed.
	 * Only includes entries where the AI score is 0 (skipped).
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array<int, array{signal: string, count: int}>
	 */
	private static function query_ai_signal_distribution( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.signals[0]')) AS ai_signal,
					COUNT(*) AS count
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.ai.is_spam') = CAST('false' AS JSON)
				AND JSON_EXTRACT(signals, '$.technique_results.ai.signals[0]') IS NOT NULL
				AND created_at >= %s
				GROUP BY ai_signal
				ORDER BY count DESC",
				$table,
				Event_Recorder::SOURCE_GF,
				$since
			)
		);

		if ( ! $rows ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			if ( $row->ai_signal === null ) {
				continue;
			}
			$result[] = [
				'signal' => (string) $row->ai_signal,
				'count'  => (int) $row->count,
			];
		}

		return $result;
	}

	// =========================================================================
	// Comment Stats (events table, source = 'comment')
	// =========================================================================

	/**
	 * Queries comment protection stats including technique breakdowns.
	 *
	 * Uses the gfsh_events table (source = 'comment') instead of
	 * wp_commentmeta / wp_comments, so stats survive comment deletion.
	 *
	 * Only called when Comment_Settings::is_enabled() returns true.
	 * JSON support is guaranteed (checked in get_stats()).
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array{total_checked: int, spam_count: int, clean_count: int, pow: array, ai: array|null, pow_signals: array, ai_reasons: array}
	 */
	private static function query_comment_stats( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// ── Summary ──────────────────────────────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_checked,
					SUM(CASE WHEN action IN ('mark_spam','pre_flagged','fail','reject') THEN 1 ELSE 0 END) AS spam_count,
					SUM(CASE WHEN action IN ('allow','bypassed') THEN 1 ELSE 0 END) AS clean_count
				FROM %i
				WHERE source = %s
				AND created_at >= %s",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		$stats = [
			'total_checked' => $row ? (int) $row->total_checked : 0,
			'spam_count'    => $row ? (int) $row->spam_count : 0,
			'clean_count'   => $row ? (int) $row->clean_count : 0,
		];

		// ── PoW stats ─────────────────────────────────────────────────────────
		$stats['pow'] = self::query_comment_pow_stats( $since );

		// ── AI stats ──────────────────────────────────────────────────────────
		$stats['ai'] = self::query_comment_ai_stats( $since );

		// ── JSON-based breakdowns ─────────────────────────────────────────────
		$stats['pow_signals'] = self::query_comment_pow_signal_distribution( $since );
		$stats['ai_reasons']  = self::query_comment_ai_reason_distribution( $since );
		$stats['ai_signals']  = self::query_comment_ai_signal_distribution( $since );

		// Enrich AI stats with pre-computed ham/spam counts.
		if ( $stats['ai'] !== null && ! empty( $stats['ai_reasons'] ) ) {
			[ $ham, $spam ]                 = self::aggregate_ham_spam( $stats['ai_reasons'] );
			$stats['ai']['classified_ham']  = $ham;
			$stats['ai']['classified_spam'] = $spam;
		}

		return $stats;
	}

	/**
	 * Queries PoW performance stats for comments from the events table.
	 *
	 * @param string $since UTC datetime string (Y-m-d H:i:s).
	 *
	 * @return array{avg_solve_ms: float, min_solve_ms: int, max_solve_ms: int, total_solves: int, difficulty_distribution: array, fallback_count: int, rest_count: int}
	 */
	private static function query_comment_pow_stats( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$solve_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS avg_solve_ms,
					MIN(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS min_solve_ms,
					MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS max_solve_ms,
					COUNT(*) AS total_solves
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms') IS NOT NULL
				AND created_at >= %s",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$difficulty_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.difficulty')) AS difficulty,
					COUNT(*) AS solve_count,
					AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms')) AS UNSIGNED)) AS avg_solve_ms
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.difficulty') IS NOT NULL
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.solve_time_ms') IS NOT NULL
				AND created_at >= %s
				GROUP BY difficulty
				ORDER BY CAST(difficulty AS UNSIGNED)",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$fallback_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.metadata.is_fallback')) AS is_fallback,
					COUNT(*) AS count
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.metadata.is_fallback') IS NOT NULL
				AND created_at >= %s
				GROUP BY is_fallback",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		$fallback_count = 0;
		$rest_count     = 0;
		foreach ( (array) $fallback_rows as $r ) {
			if ( $r->is_fallback === 'true' || $r->is_fallback === '1' ) {
				$fallback_count = (int) $r->count;
			} else {
				$rest_count = (int) $r->count;
			}
		}

		$difficulty_distribution = [];
		foreach ( (array) $difficulty_rows as $r ) {
			$difficulty_distribution[] = [
				'difficulty' => (int) $r->difficulty,
				'count'      => (int) $r->solve_count,
				'avg_ms'     => round( (float) $r->avg_solve_ms ),
			];
		}

		return [
			'avg_solve_ms'            => $solve_row && $solve_row->total_solves ? round( (float) $solve_row->avg_solve_ms ) : 0.0,
			'min_solve_ms'            => $solve_row && $solve_row->total_solves ? (int) $solve_row->min_solve_ms : 0,
			'max_solve_ms'            => $solve_row && $solve_row->total_solves ? (int) $solve_row->max_solve_ms : 0,
			'total_solves'            => $solve_row ? (int) $solve_row->total_solves : 0,
			'difficulty_distribution' => $difficulty_distribution,
			'fallback_count'          => $fallback_count,
			'rest_count'              => $rest_count,
		];
	}

	/**
		* Queries AI classification stats for comments from the events table.
		*
		* Returns null if no AI data exists in the period.
		*
		* @param string $since UTC datetime string (Y-m-d H:i:s).
		*
		* @return array{avg_latency_ms: float, total_cost: float, total_ai_calls: int}|null
		*/
	private static function query_comment_ai_stats( string $since ): ?array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.latency_ms')) AS UNSIGNED)) AS avg_latency_ms,
					SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.cost')) AS DECIMAL(10,6))) AS total_cost,
					COUNT(*) AS total_ai_calls
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.ai.metadata.latency_ms') IS NOT NULL
				AND created_at >= %s",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		if ( ! $row || ! $row->total_ai_calls ) {
			return null;
		}

		return [
			'avg_latency_ms' => round( (float) $row->avg_latency_ms ),
			'total_cost'     => round( (float) $row->total_cost, 4 ),
			'total_ai_calls' => (int) $row->total_ai_calls,
		];
	}

	/**
		* Queries PoW signal distribution for comments from the events table signals JSON.
		*
		* @param string $since UTC datetime string (Y-m-d H:i:s).
		*
		* @return array<int, array{signal: string, count: int}>
		*/
	private static function query_comment_pow_signal_distribution( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.pow.signals[0]')) AS pow_signal,
					COUNT(*) AS count
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.pow.is_spam') = CAST('true' AS JSON)
				AND created_at >= %s
				GROUP BY pow_signal
				ORDER BY count DESC",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		$result = [];
		foreach ( (array) $rows as $row ) {
			if ( $row->pow_signal === null ) {
				continue;
			}
			$result[] = [
				'signal' => (string) $row->pow_signal,
				'count'  => (int) $row->count,
			];
		}

		return $result;
	}

	/**
		* Queries AI reason code distribution for comments from the events table signals JSON.
		*
		* @param string $since UTC datetime string (Y-m-d H:i:s).
		*
		* @return array<int, array{reason: string, count: int, avg_probability: float}>
		*/
	private static function query_comment_ai_reason_distribution( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.reason')) AS ai_reason,
					COUNT(*) AS count,
					AVG(JSON_EXTRACT(signals, '$.technique_results.ai.metadata.raw_probability')) AS avg_probability
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.ai.metadata.reason') IS NOT NULL
				AND created_at >= %s
				GROUP BY ai_reason
				ORDER BY count DESC",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		$result = [];
		foreach ( (array) $rows as $row ) {
			if ( $row->ai_reason === null ) {
				continue;
			}
			$result[] = [
				'reason'          => (string) $row->ai_reason,
				'count'           => (int) $row->count,
				'avg_probability' => round( (float) $row->avg_probability, 3 ),
			];
		}

		return $result;
	}

	/**
		* Queries AI signal distribution for comments from the events table signals JSON.
		*
		* @param string $since UTC datetime string (Y-m-d H:i:s).
		*
		* @return array<int, array{signal: string, count: int}>
		*/
	private static function query_comment_ai_signal_distribution( string $since ): array {
		global $wpdb;

		$table = Event_Recorder::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(signals, '$.technique_results.ai.signals[0]')) AS ai_signal,
					COUNT(*) AS count
				FROM %i
				WHERE source = %s
				AND JSON_EXTRACT(signals, '$.technique_results.ai.is_spam') = CAST('false' AS JSON)
				AND JSON_EXTRACT(signals, '$.technique_results.ai.signals[0]') IS NOT NULL
				AND created_at >= %s
				GROUP BY ai_signal
				ORDER BY count DESC",
				$table,
				Event_Recorder::SOURCE_COMMENT,
				$since
			)
		);

		$result = [];
		foreach ( (array) $rows as $row ) {
			if ( $row->ai_signal === null ) {
				continue;
			}
			$result[] = [
				'signal' => (string) $row->ai_signal,
				'count'  => (int) $row->count,
			];
		}

		return $result;
	}
}
