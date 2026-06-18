<?php

GFForms::include_addon_framework();

class PDM_Antispam extends GFAddOn {

	/**
	 * Stores the instance of this class.
	 *
	 * @since 1.0
	 *
	 * @var PDM_Antispam|null
	 */
	private static $instance = null;

	/**
	 * Version number of the Add-On.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $_version = PDM_ANTISPAM_VERSION;

	/**
	 * Relative path to the plugin from the plugins folder.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $_path = 'pdm-antispam/pdm-antispam.php';

	/**
	 * Full path to the plugin.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * URL-friendly identifier used for form settings, add-on settings, text domain localization.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $_slug = 'pdm-antispam';

	/**
	 * Title of the plugin to be used on the settings page, form settings and plugins page.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $_title = 'PDM Anti-Spam';

	/**
	 * Short version of the plugin title to be used on menus and other places.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $_short_title = 'PDM Anti-Spam';

	/**
	 * Defines the capabilities needed for PDM Anti-Spam.
	 *
	 * @since 1.0
	 *
	 * @var array<string> $_capabilities The capabilities needed for the Add-On.
	 */
	protected $_capabilities = [
		'gravityforms_antispam_settings',
		'gravityforms_antispam_form_settings',
		'gravityforms_antispam_uninstall',
	];

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since 1.0
	 *
	 * @var string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_antispam_settings';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since 1.0
	 *
	 * @var string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_antispam_form_settings';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since 1.0
	 *
	 * @var string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_antispam_uninstall';

	/**
	 * The spam checker instance, stored for access by rendering and meta-writing code.
	 *
	 * @var \PDM_Antispam\Spam_Checker|null
	 */
	public ?\PDM_Antispam\Spam_Checker $spam_checker = null;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @since 1.0
	 *
	 * @return PDM_Antispam
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Renders the global menu icon.
	 */
	public function get_menu_icon() {
		return file_get_contents( __DIR__ . '/icon.svg' );
	}

	/**
	 * Override __construct to require PHP 8.0 or greater.
	 */
	public function __construct() {
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			return;
		}

		parent::__construct();
	}

	/**
	 * Returns the minimum requirements for the add-on.
	 *
	 * @return array Array of requirements.
	 */
	public function minimum_requirements() {
		return [
			'gravityforms' => [
				'version' => '2.8',
			],
			'wordpress'    => [
				'version' => '5.0',
			],
		];
	}

	/**
	 * Initialize the add-on.
	 *
	 * Loads text domain, sets up frontend hooks, and initializes the spam checker.
	 */
	public function init() {
		parent::init();

		load_plugin_textdomain( 'pdm-antispam', false, basename( __DIR__ ) . '/languages/' );

		$this->frontend = new \PDM_Antispam\Frontend\Frontend();

		// Frontend: per-form init scripts (PoW config, collector initialization).
		add_action( 'gform_register_init_scripts', [ $this->frontend, 'add_init_scripts' ] );

		// Spam checker: register techniques and hook into GF spam filter.
		$this->init_spam_checker();

		// Comment protection: PoW + AI for WordPress native comments.
		$this->init_comment_protection();
	}

	/**
	 * Initialize admin-specific functionality.
	 *
	 * Registers the entry detail meta box for spam analysis display.
	 */
	public function init_admin() {
		parent::init_admin();

		// Admin: entry detail meta box for spam analysis display.
		$meta_box = new \PDM_Antispam\Admin\Entry_Meta_Box();
		$meta_box->register();

		// Admin: entry list column badges for gfsh_action and gfsh_result.
		$list_column = new \PDM_Antispam\Admin\Entry_List_Column();
		$list_column->register();
	}

	/**
	 * Initialize components before the main init.
	 *
	 * Wires up Spam Hexer components: frontend hooks, spam checker,
	 * and GF integration filters.
	 *
	 * Note: We defer actual initialization to `wp_loaded` to ensure
	 * GF and all addons are fully bootstrapped before we call
	 * Settings::get() or register filters.
	 */
	public function pre_init() {
		parent::pre_init();

		// Dev Tools: register extension point hooks during gform_loaded so
		// we can catch gf_dev_tools_qm_collector_set_up (which also fires
		// during gform_loaded). If the action already fired (GF Dev Tools
		// initialized first), register_hooks() uses did_action() to grab
		// the collector retroactively.
		$this->maybe_init_dev_tools();

		$this->maybe_upgrade_db();

		// Register REST API controllers.
		add_action( 'rest_api_init', [ $this, 'register_rest_controllers' ] );
	}

	/**
	 * Handles DB migrations between plugin versions.
	 *
	 * Uses an integer DB version stored in `gfsh_db_version` so migrations
	 * can be re-run during development by bumping the constant — independent
	 * of the plugin's semver string and GFAddOn::setup()'s version tracking.
	 *
	 * Migration history:
	 *   v1 → v2: drop legacy gfsh_cache table + cron.
	 *   v2 → 20260602: create gfsh_events table via dbDelta.
	 */
	private function maybe_upgrade_db(): void {
		$current_version = 20260602;
		$stored_version  = (int) get_option( 'gfsh_db_version', 0 );

		if ( $stored_version >= $current_version ) {
			return;
		}

		global $wpdb;

		// Drop the legacy gfsh_cache table if it exists (from v1 schema).
		$cache_table = $wpdb->prefix . 'gfsh_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $cache_table ) );

		// Unschedule the legacy cache cleanup cron if still registered.
		$timestamp = wp_next_scheduled( 'gfsh_cache_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'gfsh_cache_cleanup' );
		}

		// Create (or update) the gfsh_events table via dbDelta.
		\PDM_Antispam\Event_Recorder::create_table();

		update_option( 'gfsh_db_version', $current_version, true );
	}

	/**
	 * Drops plugin-owned DB tables when the addon is uninstalled via the GF UI.
	 *
	 * Called by GFAddOn::uninstall_addon() after permission checks and before
	 * GF removes entry meta, form settings, and plugin options.
	 *
	 * Return false to cancel the uninstall; return true (or void) to continue.
	 */
	public function uninstall(): bool {
		\PDM_Antispam\Event_Recorder::drop_table();
		return true;
	}

	/**
		* Register REST API controllers.
	 *
	 * @return void
	 */
	public function register_rest_controllers(): void {
		$controller = new \PDM_Antispam\REST\Challenge_Controller();
		$controller->register_routes();
	}

	// =========================================================================
	// Frontend
	// =========================================================================

	/**
	 * The Frontend instance that handles init scripts
	 * and form applicability checks.
	 *
	 * Instantiated in init().
	 *
	 * @var \PDM_Antispam\Frontend\Frontend|null
	 */
	public $frontend = null;

	/**
	 * The Dev Tools integration instance.
	 *
	 * Instantiated in maybe_init_dev_tools() when GF Dev Tools is active.
	 *
	 * @var \PDM_Antispam\DevTools\Dev_Tools_Integration|null
	 */
	public $dev_tools_integration = null;

	/**
	 * Determine if the frontend collector script should be enqueued for a form.
	 *
	 * Delegates to Frontend::should_enqueue(). Must live on the main class
	 * because GFAddOn's `enqueue` callable expects a method on the add-on instance.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return bool
	 */
	public function should_enqueue_frontend( $form ): bool {
		return $this->frontend->should_enqueue( $form );
	}

	/**
	 * Initializes Dev Tools integration.
	 *
	 * Creates the Dev_Tools_Integration and Dev_Tools_JS instances and
	 * registers their hooks. The hooks are registered unconditionally —
	 * they simply won't fire if GF Dev Tools isn't active, since the
	 * actions they hook into (`gf_dev_tools_qm_*`) are only triggered
	 * by the Dev Tools plugin.
	 *
	 * @return void
	 */
	private function maybe_init_dev_tools(): void {
		$this->dev_tools_integration = new \PDM_Antispam\DevTools\Dev_Tools_Integration();
		$this->dev_tools_integration->register_hooks();

		$dev_tools_js = new \PDM_Antispam\DevTools\Dev_Tools_JS();
		$dev_tools_js->register_hooks();
	}

	/**
	 * Cached spam results per form ID for the current request.
	 *
	 * Prevents running the spam check multiple times when multiple hooks
	 * fire for the same submission (validation → abort → entry_is_spam).
	 *
	 * @var array<int, \PDM_Antispam\Spam_Result|null>
	 */
	private array $spam_results = [];

	/**
	 * Initializes the Spam_Checker with all techniques and hooks into GF.
	 *
	 * Registers three hooks following the GF honeypot pattern:
	 * 1. gform_validation — for "fail" action (validation error, no entry created)
	 * 2. gform_abort_submission_with_confirmation — for "reject" action (silent abort, no entry)
	 * 3. gform_entry_is_spam — for "mark_spam" action (entry created, marked as spam)
	 *
	 * The spam check runs once (at the earliest applicable hook) and the result
	 * is cached so subsequent hooks can read it without re-evaluating.
	 *
	 * @return void
	 */
	private function init_spam_checker(): void {
		$checker = new \PDM_Antispam\Spam_Checker();
		$checker->register( new \PDM_Antispam\Techniques\Proof_Of_Work() );
		$checker->register( new \PDM_Antispam\Techniques\AI_Classifier() );

		// Store for access by rendering and meta-writing code.
		$this->spam_checker = $checker;

		// 1. Validation hook — for "fail" action (show validation error).
		// Fires during validate() before entry creation.
		add_filter( 'gform_validation', function ( $validation_result ) use ( $checker ) {
			$form    = $validation_result['form'];
			$form_id = (int) rgar( $form, 'id' );

			// Skip spam check during page navigation on multi-page forms.
			// GF fires gform_validation on every "Next" click to validate the
			// current page's fields — not just on final submit. PoW is
			// intentionally deferred until the last page, so checking it here
			// would always produce pow_missing and block page navigation.
			// GF's own payment addon uses the same pattern (GF_Payment_Addon::validate).
			if ( \GFCommon::has_pages( $form ) && ! \GFFormDisplay::is_last_page( $form ) ) {
				return $validation_result;
			}

			// Only intervene if the form is otherwise valid (don't override real validation errors).
			if ( ! $validation_result['is_valid'] ) {
				return $validation_result;
			}

			// Run the spam check (or get cached result).
			$result = $this->get_or_run_spam_check( $checker, $form );

			if ( $result === null ) {
				return $validation_result;
			}

			// Only act if spam was detected AND the resolved action is 'fail'.
			if ( ! $result->should_fail_validation() ) {
				return $validation_result;
			}

			$this->log_debug( __METHOD__ . "(): Form #{$form_id} — Validation error triggered by spam check." );

				// 'fail' blocks before save_lead() — no entry row exists.
				// Record to gfsh_events so it surfaces in dashboard stats.
				\PDM_Antispam\Event_Recorder::record_blocked(
					\PDM_Antispam\Event_Recorder::SOURCE_GF,
					'fail',
					[
						'form_id' => $form_id,
						'signals' => $result->to_array(),
					]
				);

				$validation_result['is_valid'] = false;

				// No field-level error — the message is injected via gform_validation_message below.
				// This avoids attaching a red error indicator to a specific form field.

				return $validation_result;
		}, 9999 );

			// 1b. Validation message filter — replaces the GF header "There was a problem with your
			// submission. Please review the fields below." with the configured spam message when the
			// 'fail' action caused the validation failure.
			add_filter( 'gform_validation_message', function ( $markup, $form ) {
				$form_id = (int) rgar( $form, 'id' );
				$result  = $this->spam_results[ $form_id ] ?? null;

				if ( $result === null || ! $result->should_fail_validation() ) {
					return $markup;
				}

				$message = $this->get_fail_validation_message( $result, $form );

				// Preserve the GF icon + class structure, replace only the text content.
				return "<h2 class='gform_submission_error'>"
					. "<span class='gform-icon gform-icon--circle-error'></span>"
					. esc_html( $message )
					. '</h2>';
			}, 10, 2 );

			// 2. Abort hook — for "reject" action (silent abort, no entry created).
			// Fires after validation passes but before handle_submission().
			// Also fires for save_and_continue — skip spam check in that case.
			// Same pattern as GF's honeypot handler.
			add_filter( 'gform_abort_submission_with_confirmation', function ( $do_abort, $form ) use ( $checker ) {
				// If already marked to abort, let it abort.
				if ( $do_abort ) {
					return true;
				}

				// Skip spam check for save_and_continue — the user is saving a draft,
				// not submitting. GF fires this hook for both final submit and save_and_continue.
				if ( rgpost( 'gform_save' ) ) {
					return false;
				}

				$form_id = (int) rgar( $form, 'id' );

				// Run the spam check (or get cached result).
				$result = $this->get_or_run_spam_check( $checker, $form );

				if ( $result === null ) {
					return false;
				}

				// Only act if spam was detected AND the resolved action is 'reject'.
				if ( ! $result->should_reject() ) {
					return false;
				}

				$this->log_debug( __METHOD__ . "(): Form #{$form_id} — Silent reject (abort submission)." );

				// 'reject' aborts before an entry is created — no entry row exists.
				// Record to gfsh_events so it surfaces in dashboard stats.
				\PDM_Antispam\Event_Recorder::record_blocked(
					\PDM_Antispam\Event_Recorder::SOURCE_GF,
					'reject',
					[
						'form_id' => $form_id,
						'signals' => $result->to_array(),
					]
				);

				// Cache the spam state so GF knows this was spam (like honeypot does).
				\GFFormDisplay::$submission[ $form_id ]['is_spam'] = true;

				return true;
			}, 10, 2 );

		// 3. Entry is spam hook — for "mark_spam" action (entry created, marked as spam).
		// Fires inside handle_submission() after save_lead().
		add_filter( 'gform_entry_is_spam', function ( $is_spam, $form, $entry ) use ( $checker ) {
			// Skip if already marked as spam by another plugin.
			if ( $is_spam ) {
				$entry_id = (int) rgar( $entry, 'id' );
				if ( $entry_id ) {
					$form_id      = (int) rgar( $form, 'id' );
					$prior        = rgars( \GFFormDisplay::$submission, "{$form_id}/spam_filter" );
					$result_array = [
						'action'            => 'pre_flagged',
						'signals'           => [],
						'prior_filter'      => $prior ?: null,
						'technique_results' => [],
					];
					gform_update_meta( $entry_id, 'gfsh_result', wp_json_encode( $result_array ) );
					$this->write_dashboard_meta( $entry_id, $result_array );
					\PDM_Antispam\Event_Recorder::record_event(
						\PDM_Antispam\Event_Recorder::SOURCE_GF,
						'pre_flagged',
						[ 'form_id' => $form_id, 'signals' => $result_array ]
					);
				}
				return $is_spam;
			}

			$form_id = (int) rgar( $form, 'id' );

			// Skip if protection is globally disabled.
			if ( ! \PDM_Antispam\Settings::is_enabled() ) {
				return $is_spam;
			}

			// Skip if protection is disabled for this specific form.
			if ( ! $this->frontend->is_applicable_form( $form ) ) {
				return $is_spam;
			}

			// Skip spam checks for logged-in users when bypass is enabled.
			if ( \PDM_Antispam\Settings::should_bypass_logged_in() && is_user_logged_in() && ! \PDM_Antispam\Settings::is_force_check() ) {
				$entry_id = (int) rgar( $entry, 'id' );
				if ( $entry_id ) {
					$result_array = [
						'action'            => 'bypassed',
						'signals'           => [],
						'bypassed_user'     => wp_get_current_user()->user_login,
						'technique_results' => [],
					];
					gform_update_meta( $entry_id, 'gfsh_result', wp_json_encode( $result_array ) );
					$this->write_dashboard_meta( $entry_id, $result_array );
					\PDM_Antispam\Event_Recorder::record_event(
						\PDM_Antispam\Event_Recorder::SOURCE_GF,
						'bypassed',
						[ 'form_id' => $form_id, 'signals' => $result_array ]
					);
				}
				if ( $this->dev_tools_integration ) {
					$this->dev_tools_integration->collect_bypass_result( $form_id, 'logged_in' );
				}
				return false;
			}

			// Run the spam check (or get cached result).
			$result = $this->get_or_run_spam_check( $checker, $form, $entry );

			if ( $result === null ) {
				return $is_spam;
			}

			$entry_id = (int) rgar( $entry, 'id' );

			// Persist result as entry meta.
			if ( $entry_id ) {
				$result_array = $result->to_array();
				gform_update_meta( $entry_id, 'gfsh_result', wp_json_encode( $result_array ) );

				// Add entry notes for any technique errors so admins see them in the Notes tab.
				$this->maybe_add_error_notes( $entry_id, $result );
			}

			if ( $result->is_spam() ) {
				// Register with GF's spam API so the entry detail shows which filter flagged it.
				\GFCommon::set_spam_filter(
					$form_id,
					esc_html__( 'PDM Anti-Spam', 'pdm-antispam' ),
					$this->format_spam_reason( $result )
				);

				$this->log_debug( __METHOD__ . '(): Spam detected. ' . $this->format_spam_reason( $result ) );
			}

			// Write denormalized dashboard meta (kept for per-entry detail view).
			if ( $entry_id ) {
				$result_array = $result->to_array();
				$this->write_dashboard_meta( $entry_id, $result_array );
				// Record to events table — single source of truth for aggregate stats.
				\PDM_Antispam\Event_Recorder::record_event(
					\PDM_Antispam\Event_Recorder::SOURCE_GF,
					$result_array['action'] ?? 'allow',
					[ 'form_id' => $form_id, 'signals' => $result_array ]
				);
			}

			return $result->is_spam();
		}, 10, 3 );
	}

	/**
	 * Resolves the validation failure message for a spam result.
	 *
	 * Identifies which technique triggered the 'fail' action and returns
	 * its configured custom message (or the built-in default if none is set).
	 *
	 * @param \PDM_Antispam\Spam_Result $result The spam result.
	 * @param array                       $form   The GF form array.
	 *
	 * @return string The validation message to display.
	 */
	private function get_fail_validation_message( \PDM_Antispam\Spam_Result $result, array $form ): string {
		// Find the technique that triggered the 'fail' action.
		foreach ( $result->get_technique_results() as $technique_name => $technique_data ) {
			if ( empty( $technique_data['is_spam'] ) ) {
				continue;
			}

			$action = \PDM_Antispam\Settings::get_for_form( $technique_name . '_action', $form, '' );

			if ( $action === 'fail' ) {
				return \PDM_Antispam\Settings::get_fail_message( $technique_name, $form );
			}
		}

		// Fallback: no specific technique identified — use the built-in default.
		return \PDM_Antispam\Settings::get_fail_message( 'pow', $form );
	}

	/**
	 * Runs the spam check for a form or returns a cached result.
	 *
	 * Handles all pre-flight checks (globally disabled, per-form disabled,
	 * logged-in bypass) before evaluating. Caches the result per form ID
	 * so multiple hooks in the same request don't re-run the check.
	 *
	 * @param \PDM_Antispam\Spam_Checker $checker The spam checker instance.
	 * @param array                        $form    The GF form array.
	 * @param array                        $entry   The GF entry array (may be empty for pre-submission hooks).
	 *
	 * @return \PDM_Antispam\Spam_Result|null The result, or null if the check should be skipped.
	 */
	private function get_or_run_spam_check( \PDM_Antispam\Spam_Checker $checker, array $form, array $entry = [] ): ?\PDM_Antispam\Spam_Result {
		$form_id = (int) rgar( $form, 'id' );

		// Return cached result if already evaluated for this form.
		if ( array_key_exists( $form_id, $this->spam_results ) ) {
			return $this->spam_results[ $form_id ];
		}

		// Skip if protection is globally disabled.
		if ( ! \PDM_Antispam\Settings::is_enabled() ) {
			$this->spam_results[ $form_id ] = null;
			return null;
		}

		// Skip if protection is disabled for this specific form.
		if ( ! $this->frontend->is_applicable_form( $form ) ) {
			$this->spam_results[ $form_id ] = null;
			return null;
		}

		// Skip spam checks for logged-in users when bypass is enabled.
		if ( \PDM_Antispam\Settings::should_bypass_logged_in() && is_user_logged_in() && ! \PDM_Antispam\Settings::is_force_check() ) {
			if ( $this->dev_tools_integration ) {
				$this->dev_tools_integration->collect_bypass_result( $form_id, 'logged_in' );
			}
			$this->spam_results[ $form_id ] = null;
			return null;
		}

		// If no entry provided (pre-submission hooks), build one from POST.
		// GFFormsModel::get_current_lead() reads $_POST and constructs a
		// lead array with field values — needed for AI classification.
		if ( empty( $entry ) ) {
			$entry = \GFFormsModel::get_current_lead();
			if ( ! is_array( $entry ) ) {
				$entry = [];
			}
		}

		$start   = microtime( true );
		$context = new \PDM_Antispam\Submission_Context( $form, $entry );
		$result  = $checker->evaluate( $context );
		$elapsed = ( microtime( true ) - $start ) * 1000;

		// Report to Dev Tools if active.
		if ( $this->dev_tools_integration ) {
			$this->dev_tools_integration->collect_submission_result( $form_id, $result, $elapsed, false, '' );
		}

		$this->spam_results[ $form_id ] = $result;
		return $result;
	}

	/**
	 * Initializes WordPress comment spam protection.
	 *
	 * Wires up the Comment_Settings (Discussion page), Comment_Checker
	 * (preprocess_comment pipeline), Comment_Frontend (PoW injection),
	 * and Comment_Admin (meta box + status transitions).
	 *
	 * Settings are always registered so the Discussion page shows the
	 * section. The checker/frontend/admin hooks only fire when the
	 * feature is enabled.
	 *
	 * @return void
	 */
	private function init_comment_protection(): void {
		// Always register settings so the Discussion page shows the toggle.
		$settings = new \PDM_Antispam\Comments\Comment_Settings();
		$settings->register_hooks();

		// Only wire checker/frontend/admin when enabled.
		if ( ! \PDM_Antispam\Comments\Comment_Settings::is_enabled() ) {
			return;
		}

		// Comment checker uses the same technique instances as the GF spam checker.
		$checker = new \PDM_Antispam\Comments\Comment_Checker( $this->spam_checker );

		// Comment pipeline hooks.
		add_filter( 'preprocess_comment', [ $checker, 'check' ], 1 );
		add_filter( 'rest_pre_insert_comment', [ $checker, 'check_rest' ], 1 );
		add_filter( 'pre_comment_approved', [ $checker, 'get_verdict' ], 10, 2 );
		add_action( 'wp_insert_comment', [ $checker, 'write_meta' ], 10, 2 );

		// Frontend: inject PoW challenge into comment forms.
		$frontend = new \PDM_Antispam\Comments\Comment_Frontend();
		$frontend->register_hooks();

		// Admin: meta box + status transition logging.
		$admin = new \PDM_Antispam\Comments\Comment_Admin();
		$admin->register_hooks();
	}

	/**
	 * Formats a human-readable spam reason string from a Spam_Result.
	 *
	 * Used for GFCommon::set_spam_filter() and debug logging.
	 *
	 * @param \PDM_Antispam\Spam_Result $result The spam analysis result.
	 *
	 * @return string
	 */
	private function format_spam_reason( \PDM_Antispam\Spam_Result $result ): string {
		$signals = $result->get_signals();
		$action  = $result->get_action();

		$reason = sprintf(
			'Action: %s',
			$action
		);

		if ( ! empty( $signals ) ) {
			$reason .= ', Signals: ' . implode( ', ', $signals );
		}

		return $reason;
	}

	/**
	 * Adds entry notes for any technique that was skipped due to an error.
	 *
	 * Iterates through technique results and adds a GF entry note for each
	 * technique that has an `error_message` in its metadata. This makes
	 * errors visible in the entry's Notes tab, not just the debug log.
	 *
	 * @param int                        $entry_id The GF entry ID.
	 * @param \PDM_Antispam\Spam_Result $result   The spam analysis result.
	 *
	 * @return void
	 */
	private function maybe_add_error_notes( int $entry_id, \PDM_Antispam\Spam_Result $result ): void {
		$technique_results = $result->get_technique_results();

		/** @var array<string, string> */
		$labels = [
			'pow' => __( 'Proof of Work', 'pdm-antispam' ),
			'ai'  => __( 'AI Classification', 'pdm-antispam' ),
		];

		foreach ( $technique_results as $name => $data ) {
			$error_message = $data['metadata']['error_message'] ?? '';

			if ( empty( $error_message ) ) {
				continue;
			}

			$label = $labels[ $name ] ?? ucfirst( $name );
			$hints = $data['metadata']['hints'] ?? [];

			$note = sprintf(
				/* translators: 1: technique name, 2: error details */
				__( '%1$s was skipped due to an error: %2$s', 'pdm-antispam' ),
				$label,
				$error_message
			);

			if ( ! empty( $hints ) ) {
				$note .= "\n" . implode( "\n", $hints );
			}

			\GFAPI::add_note(
				$entry_id,
				0,
				'PDM Anti-Spam',
				$note,
				'gfsh_error'
			);
		}
	}

	/**
	 * Writes denormalized entry meta for efficient dashboard queries.
	 *
	 * Extracts key values from the result array and writes them as
	 * individual meta keys so dashboard queries can use simple
	 * COUNT/AVG/GROUP BY without JSON parsing.
	 *
	 * Only writes meta for techniques that actually ran (not skipped).
	 * For bypassed and pre_flagged paths, only gfsh_action is written.
	 *
	 * @param int                  $entry_id     The GF entry ID.
	 * @param array<string, mixed> $result_array The Spam_Result::to_array() output (possibly with resolved action).
	 *
	 * @return void
	 */
	private function write_dashboard_meta( int $entry_id, array $result_array ): void {
		// Action — always written.
		gform_update_meta( $entry_id, 'gfsh_action', $result_array['action'] ?? 'allow' );

		// Technique-specific meta — each technique declares what it wants denormalized.
		foreach ( $result_array['technique_results'] as $name => $data ) {
			if ( ! empty( $data['metadata']['skipped'] ) ) {
				continue;
			}
			$technique = $this->spam_checker ? $this->spam_checker->get_technique( $name ) : null;
			if ( ! $technique ) {
				continue;
			}
			foreach ( $technique->get_dashboard_meta( $data ) as $key => $value ) {
				gform_update_meta( $entry_id, $key, $value );
			}
		}
	}

	// =========================================================================
	// Entry Meta (GFAddOn pattern)
	// =========================================================================

	/**
	 * Registers custom entry meta properties with Gravity Forms.
	 *
	 * Registers `gfsh_result` and `gfsh_action` for entry list columns and
	 * filtering. The actual values are written directly via gform_update_meta()
	 * inside the gform_entry_is_spam handler, because GF calls
	 * update_entry_meta_callback *before* the spam check runs.
	 *
	 * @param array $entry_meta Existing entry meta definitions.
	 * @param int   $form_id    The form ID.
	 *
	 * @return array Modified entry meta definitions.
	 */
	public function get_entry_meta( $entry_meta, $form_id ) {
		$entry_meta['gfsh_result'] = [
			'label'             => esc_html__( 'Spam Analysis', 'pdm-antispam' ),
			'is_numeric'        => false,
			'is_default_column' => false,
		];

		$entry_meta['gfsh_action'] = [
			'label'             => esc_html__( 'Spam Action', 'pdm-antispam' ),
			'is_numeric'        => false,
			'is_default_column' => false,
		];

		return $entry_meta;
	}

	// =========================================================================
	// Form Settings (Per-Form)
	// =========================================================================

	/**
	 * Define the form settings fields.
	 *
	 * This method defines two sections:
	 *
	 * 1. **Form Settings Section**: Contains hidden fields that store React-managed state.
	 *    These are synced from the React UI via JavaScript before form submission.
	 *
	 * 2. **React Settings Section**: A standard GF section with an `html` field that
	 *    outputs the React root container. CSS strips the fieldset/legend chrome so
	 *    React owns the entire visual UI.
	 *
	 * @param array $form The current form.
	 *
	 * @return array<int, array<string, mixed>> Array of settings sections.
	 */
	public function form_settings_fields( $form ) {
		return [
			// Form Settings Section: visible fields + hidden fields for React state persistence
			[
				'title'  => esc_html__( 'Spam Protection', 'pdm-antispam' ),
				'fields' => [
					// Visible fields
					[
						'name'          => 'gfsh_enabled',
						'label'         => esc_html__( 'Spam Protection', 'pdm-antispam' ),
						'type'          => 'select',
						'default_value' => 'global',
						'choices'       => [
							[
								'label' => esc_html__( 'Use Global Setting', 'pdm-antispam' ),
								'value' => 'global',
							],
							[
								'label' => esc_html__( 'Enabled', 'pdm-antispam' ),
								'value' => 'enabled',
							],
							[
								'label' => esc_html__( 'Disabled', 'pdm-antispam' ),
								'value' => 'disabled',
							],
						],
						'tooltip'       => '<h6>' . esc_html__( 'Spam Protection', 'pdm-antispam' ) . '</h6>'
							. esc_html__( 'Choose whether to use the global setting, explicitly enable, or disable spam protection for this form.', 'pdm-antispam' ),
					],
					// Hidden fields for React-managed state (synced via JS)
					// Technique overrides (JSON object, blank = use global)
					[
						'name'          => 'technique_overrides',
						'type'          => 'hidden',
						'default_value' => '{}',
					],
					// PoW settings
					[
						'name' => 'pow_protection_level',
						'type' => 'hidden',
					],
					// AI settings
						[
							'name' => 'ai_custom_context',
							'type' => 'hidden',
						],
					[
						'name' => 'ai_confidence_threshold',
						'type' => 'hidden',
					],
					// Per-technique action overrides (blank = use global)
					[
						'name' => 'pow_action',
						'type' => 'hidden',
					],
					[
						'name' => 'ai_action',
						'type' => 'hidden',
					],
					// Per-technique validation failure messages (blank = use global)
					[
						'name' => 'pow_fail_message',
						'type' => 'hidden',
					],
					[
						'name' => 'ai_fail_message',
						'type' => 'hidden',
					],
				],
			],
			/*
				 * React Settings Section
				 *
				 * Uses an `html` field to inject the React root container into a
				 * standard GF section. CSS strips the fieldset/legend chrome so the
				 * section is visually invisible — React owns the entire UI.
				 *
				 * This avoids the need for a custom Settings renderer (which caused
				 * postback/save issues due to the reflection-based property copy).
				 */
				[
					'id'     => 'react-settings',
					'class'  => 'gfsh-react-section gfsh-form-settings',
					'fields' => [
						[
							'name' => '_react_root',
							'type' => 'html',
							'html' => '<div id="gfsh-settings-root" class="gfsh-form-settings gp-settings-react-root">'
								. '<div class="gp-settings-react-root__loader">'
								. '<span class="gp-settings-react-root__spinner"></span>'
								. '<span class="gp-settings-react-root__text">' . esc_html__( 'Loading settings...', 'pdm-antispam' ) . '</span>'
								. '</div>'
								. '</div>',
						],
					],
				],
		];
	}

	// =========================================================================
	// Plugin Settings (Global)
	// =========================================================================

	/**
	 * Define the global plugin settings fields.
	 *
	 * These settings appear under Forms → Settings → Spam Hexer and control
	 * the default behavior for all forms.
	 *
	 * This method defines two sections:
	 *
	 * 1. **General Settings Section**: Contains the "Enable Spam Protection" toggle
	 *    (rendered natively by GF) plus hidden fields that store React-managed state.
	 *    These hidden fields are synced from the React UI via JavaScript before save.
	 *
	 * 2. **React Settings Section**: A standard GF section with an `html` field that
	 *    outputs the React root container. CSS strips the fieldset/legend chrome so
	 *    React owns the entire visual UI.
	 *
	 * @since 1.0
	 *
	 * @return array<int, array<string, mixed>> Array of settings sections.
	 */
	public function plugin_settings_fields() {
		return [
			// Section 1: Native GF toggle + hidden fields for React state persistence
			[
				'title'       => esc_html__( 'General Settings', 'pdm-antispam' ),
				'description' => esc_html__( 'Configure the default spam protection settings for all forms.', 'pdm-antispam' ),
				'fields'      => [
					// Visible native GF toggle — kept outside React so the section
					// always has at least one real field and doesn't get stripped.
					[
						'name'          => 'enabled',
						'label'         => esc_html__( 'Enable Spam Protection', 'pdm-antispam' ),
						'type'          => 'toggle',
						'toggle_label'  => esc_html__( 'Enable PDM Anti-Spam globally', 'pdm-antispam' ),
						'default_value' => '1',
						'tooltip'       => '<h6>' . esc_html__( 'Enable Spam Protection', 'pdm-antispam' ) . '</h6>'
							. esc_html__( 'When enabled, spam protection is active on all forms unless overridden in individual form settings.', 'pdm-antispam' ),
					],
					// Hidden fields for React-managed state (synced via JS)
					[
						'name'          => 'bypass_logged_in',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['bypass_logged_in'],
					],
					// AI confidence threshold
					[
						'name'          => 'ai_confidence_threshold',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_confidence_threshold'],
					],
					// PoW settings
						[
							'name'          => 'pow_enabled',
							'type'          => 'hidden',
							'default_value' => \PDM_Antispam\Settings::DEFAULTS['pow_enabled'],
						],
					[
						'name'          => 'pow_protection_level',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['pow_protection_level'],
					],
					// AI Classification settings
						[
							'name'          => 'ai_enabled',
							'type'          => 'hidden',
							'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_enabled'],
						],
					[
						'name'          => 'ai_provider',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_provider'],
					],
					[
						'name'          => 'ai_api_key',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_api_key'],
					],
					[
						'name'          => 'ai_model',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_model'],
					],
					[
						'name'          => 'ai_custom_context',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_custom_context'],
					],
					[
						'name'          => 'ai_timeout',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_timeout'],
					],
					[
						'name'          => 'ai_zdr',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_zdr'],
					],
					// Per-technique action settings
					[
						'name'          => 'pow_action',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['pow_action'],
					],
					[
						'name'          => 'ai_action',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_action'],
					],
					// Per-technique validation failure messages
					[
						'name'          => 'pow_fail_message',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['pow_fail_message'],
					],
					[
						'name'          => 'ai_fail_message',
						'type'          => 'hidden',
						'default_value' => \PDM_Antispam\Settings::DEFAULTS['ai_fail_message'],
					],
				],
			],
			/*
				 * React Settings Section
				 *
				 * Uses an `html` field to inject the React root container into a
				 * standard GF section. CSS strips the fieldset/legend chrome so the
				 * section is visually invisible — React owns the entire UI.
				 */
				[
					'id'     => 'react-settings',
					'class'  => 'gfsh-react-section gfsh-plugin-settings',
					'fields' => [
						[
							'name' => '_react_root',
							'type' => 'html',
							'html' => '<div id="gfsh-plugin-settings-root" class="gfsh-plugin-settings gp-settings-react-root">'
								. '<div class="gp-settings-react-root__loader">'
								. '<span class="gp-settings-react-root__spinner"></span>'
								. '<span class="gp-settings-react-root__text">' . esc_html__( 'Loading settings...', 'pdm-antispam' ) . '</span>'
								. '</div>'
								. '</div>',
						],
					],
				],
		];
	}

	// =========================================================================
	// Scripts & Styles
	// =========================================================================

	/**
	 * Enqueue scripts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function scripts() {
		$scripts = [];

		// Form settings React script
		$form_settings_asset_path = plugin_dir_path( __FILE__ ) . 'js/built/pdm-antispam-form-settings.asset.php';
		if ( file_exists( $form_settings_asset_path ) ) {
			$form_settings_asset_file = include $form_settings_asset_path;

			$scripts[] = [
				'handle'    => 'PDM_Antispam_form_settings',
				'src'       => $this->get_base_url() . '/js/built/pdm-antispam-form-settings.js',
				'version'   => $form_settings_asset_file['version'],
				'deps'      => $form_settings_asset_file['dependencies'],
				'in_footer' => true,
				'enqueue'   => [
					[ 'admin_page' => [ 'form_settings' ] ],
				],
				// Deferred: only runs when this script is actually enqueued (form_settings page).
				// Avoids calling is_wp_ai_client_available() on every admin page load.
				'callback'  => function () {
					wp_localize_script( 'PDM_Antispam_form_settings', 'gf_spam_hexer_form_settings_strings', $this->get_form_settings_strings() );
					wp_set_script_translations( 'PDM_Antispam_form_settings', 'pdm-antispam', plugin_dir_path( __FILE__ ) . 'languages/' );
				},
			];
		}

		// Plugin settings React script
		$plugin_settings_asset_path = plugin_dir_path( __FILE__ ) . 'js/built/pdm-antispam-plugin-settings.asset.php';
		if ( file_exists( $plugin_settings_asset_path ) ) {
			$plugin_settings_asset_file = include $plugin_settings_asset_path;

			$scripts[] = [
				'handle'    => 'PDM_Antispam_plugin_settings',
				'src'       => $this->get_base_url() . '/js/built/pdm-antispam-plugin-settings.js',
				'version'   => $plugin_settings_asset_file['version'],
				'deps'      => $plugin_settings_asset_file['dependencies'],
				'in_footer' => true,
				'enqueue'   => [
					[ 'admin_page' => [ 'plugin_settings' ] ],
				],
				// Deferred: only runs when this script is actually enqueued (plugin_settings page).
				// Avoids running Dashboard_Stats::get_stats() (~9 DB queries) and
				// is_wp_ai_client_available() (outbound HTTP) on every admin page load.
				'callback'  => function () {
					wp_localize_script( 'PDM_Antispam_plugin_settings', 'gf_spam_hexer_plugin_settings_strings', $this->get_plugin_settings_strings() );
					wp_set_script_translations( 'PDM_Antispam_plugin_settings', 'pdm-antispam', plugin_dir_path( __FILE__ ) . 'languages/' );
				},
			];
		}

		// Frontend collector script (PoW solver + payload assembly).
		$frontend_asset_path = plugin_dir_path( __FILE__ ) . 'js/built/pdm-antispam-frontend.asset.php';
		if ( file_exists( $frontend_asset_path ) ) {
			$frontend_asset_file = include $frontend_asset_path;

			$scripts[] = [
				'handle'    => 'pdm-antispam-frontend',
				'src'       => $this->get_base_url() . '/js/built/pdm-antispam-frontend.js',
				'version'   => $frontend_asset_file['version'],
				'deps'      => $frontend_asset_file['dependencies'],
				'in_footer' => true,
				'enqueue'   => [
					[ $this, 'should_enqueue_frontend' ],
				],
			];
		}

		return array_merge(
			parent::scripts(),
			array_map( function ( $script ) {
				// @phpstan-ignore function.impossibleType (callback may exist in the future)
				if ( array_key_exists( 'callback', $script ) ) {
					return $script;
				}

				$script['callback'] = function () use ( $script ) {
					wp_set_script_translations( $script['handle'], 'pdm-antispam', plugin_dir_path( __FILE__ ) . 'languages/' );
				};

				return $script;
			}, $scripts ),
		);
	}

	/**
	 * Enqueue styles.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function styles() {
		$styles = [];

		// Form settings stylesheet
		$form_settings_css_path = $this->get_base_path() . '/assets/css/built/pdm-antispam-form-settings.css';
		if ( file_exists( $form_settings_css_path ) ) {
			$styles[] = [
				'handle'  => 'PDM_Antispam_form_settings',
				'src'     => $this->get_base_url() . '/assets/css/built/pdm-antispam-form-settings.css',
				'version' => $this->_version,
				'enqueue' => [
					[ 'admin_page' => [ 'form_settings' ] ],
				],
			];
		}

		// Plugin settings stylesheet
		$plugin_settings_css_path = $this->get_base_path() . '/assets/css/built/pdm-antispam-plugin-settings.css';
		if ( file_exists( $plugin_settings_css_path ) ) {
			$styles[] = [
				'handle'  => 'PDM_Antispam_plugin_settings',
				'src'     => $this->get_base_url() . '/assets/css/built/pdm-antispam-plugin-settings.css',
				'version' => $this->_version,
				'enqueue' => [
					[ 'admin_page' => [ 'plugin_settings' ] ],
				],
			];
		}

		// Entry detail meta box + entry list column stylesheet
		$styles[] = [
			'handle'  => 'gfsh_entry_meta_box',
			'src'     => $this->get_base_url() . '/assets/css/entry-meta-box.css',
			'version' => $this->_version,
			'enqueue' => [
				[ 'admin_page' => [ 'entry_view', 'entry_edit', 'entry_list' ] ],
			],
		];

		return array_merge( parent::styles(), $styles );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
		* Get localized strings for the form settings React app.
		*
		* Passed to the JS bundle via GFAddOn's `strings` mechanism, which
		* creates a `PDM_Antispam_form_settings` global on the page.
		*
		* @return array<string, mixed>
		*/
	private function get_form_settings_strings(): array {
		$form_id = absint( rgget( 'id' ) );

		return [
			'formId'              => $form_id,
			'globalSettings'      => $this->get_plugin_settings(),
			'wpAiClientAvailable' => $this->is_wp_ai_client_available(),
			'siteInfo'            => $this->get_site_info(),
			'pluginSettingsUrl'   => admin_url( 'admin.php?page=gf_settings&subview=pdm-antispam#ai' ),
		];
	}

	/**
		* Get localized strings for the plugin settings React app.
		*
		* Passed to the JS bundle via GFAddOn's `strings` mechanism, which
		* creates a `PDM_Antispam_plugin_settings` global on the page.
		*
		* @return array<string, mixed>
		*/
	private function get_plugin_settings_strings(): array {
		return [
			'wpAiClientAvailable'       => $this->is_wp_ai_client_available(),
			'availableModelsAuto'       => $this->get_wp_ai_client_models(),
			'availableModelsOpenRouter' => $this->get_openrouter_models(),
			'siteInfo'                  => $this->get_site_info(),
			'connectorsUrl'             => admin_url( 'options-connectors.php' ),
			'pluginSettingsUrl'         => admin_url( 'admin.php?page=gf_settings&subview=pdm-antispam#ai' ),
			'dashboardStats'            => \PDM_Antispam\Admin\Dashboard_Stats::get_stats(),
		];
	}

	/**
	 * Returns models from the WP AI Client registry for the auto provider mode.
	 *
	 * Queries the registry live so the dropdown reflects exactly what's
	 * installed and configured. Returns an empty array when WP AI Client
	 * is unavailable (JS falls back to showing only "Auto" + "Custom").
	 *
	 * @return array<int, array{id: string, label: string, provider: string}>
	 */
	private function get_wp_ai_client_models(): array {
		if ( ! $this->is_wp_ai_client_available() || ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return [];
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			$models   = [];

			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				try {
					// modelMetadataDirectory() is a static method on the provider class.
					$class_name = $registry->getProviderClassName( $provider_id );
					$dir        = $class_name::modelMetadataDirectory();
					foreach ( $dir->listModelMetadata() as $meta ) {
						$models[] = [
							'id'       => $meta->getId(),
							'label'    => $meta->getName() ?: $meta->getId(),
							'provider' => $provider_id,
						];
					}
				} catch ( \Exception $e ) {
					continue;
				}
			}

			return $models;
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Returns the curated OpenRouter model list for the openrouter provider mode.
	 *
	 * Static list of fast, cheap models benchmarked for spam classification.
	 * These are OpenRouter vendor-prefixed IDs used directly as model slugs.
	 *
	 * @return array<int, array{id: string, label: string}>
	 */
	private function get_openrouter_models(): array {
		return [
			// Meta — fast, reliable, widely available (benchmarked 2025-06-01).
			[ 'id' => 'meta-llama/llama-4-scout', 'label' => 'Llama 4 Scout' ],
			// Google — best overall speed/accuracy/cost.
			[ 'id' => 'google/gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash' ],
			[ 'id' => 'google/gemini-2.5-flash-lite', 'label' => 'Gemini 2.5 Flash Lite' ],
			[ 'id' => 'google/gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash' ],
			// OpenAI — pinned version had perfect accuracy across all 5 prompt types.
			[ 'id' => 'openai/gpt-4o-mini-2024-07-18', 'label' => 'GPT-4o Mini (2024-07-18)' ],
			[ 'id' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o Mini' ],
			[ 'id' => 'openai/gpt-4.1-nano', 'label' => 'GPT-4.1 Nano' ],
			[ 'id' => 'openai/gpt-oss-20b', 'label' => 'gpt-oss-20b' ],
			[ 'id' => 'openai/gpt-oss-safeguard-20b', 'label' => 'gpt-oss-safeguard-20b' ],
			// Anthropic — available via OpenRouter direct.
			[ 'id' => 'anthropic/claude-3-5-haiku-latest', 'label' => 'Claude 3.5 Haiku' ],
			// Other top performers from the full OpenRouter sweep.
			[ 'id' => 'microsoft/phi-4-mini-instruct', 'label' => 'Phi-4 Mini Instruct' ],
			[ 'id' => 'ibm-granite/granite-4.1-8b', 'label' => 'IBM Granite 4.1 8B' ],
			[ 'id' => 'amazon/nova-micro-v1', 'label' => 'Amazon Nova Micro' ],
			[ 'id' => 'mistralai/mistral-small-24b-instruct-2501', 'label' => 'Mistral Small 24B' ],
			[ 'id' => 'amazon/nova-lite-v1', 'label' => 'Amazon Nova Lite' ],
		];
	}

	/**
	 * Get site info for the "What the AI sees" section in settings UI.
	 *
	 * @return array{name: string, description: string, domain: string}
	 */
	private function get_site_info(): array {
		return [
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'domain'      => wp_parse_url( home_url(), PHP_URL_HOST ),
		];
	}

	/**
	 * Checks if the WP 7.0 AI Client is available and has at least
	 * one provider configured for text generation.
	 *
	 * @return bool
	 */
	private function is_wp_ai_client_available(): bool {
		static $result = null;

		if ( $result !== null ) {
			return $result;
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$result = false;
			return $result;
		}

		$builder = wp_ai_client_prompt()
			->using_temperature( 0.1 );

		$result = $builder->is_supported_for_text_generation();
		return $result;
	}
}

/**
 * Returns the singleton instance of the PDM_Antispam class.
 *
 * @since 1.0
 *
 * @return PDM_Antispam
 */
function PDM_Antispam() {
	return PDM_Antispam::get_instance();
}
