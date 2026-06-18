<?php
/**
 * Comment protection settings.
 *
 * Registers settings fields on the WordPress Discussion settings page
 * (options-discussion.php) and provides static option getters used by
 * Comment_Checker, Comment_Frontend, and Comment_Admin.
 *
 * Renders a React-based settings UI (TechniqueCards) with hidden fields
 * that the WP Settings API saves on form submit.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Comments;

use PDM_Antispam\Settings;

/**
 * Discussion settings registration and option helpers for comment protection.
 */
class Comment_Settings {

	const OPTION_ENABLED                 = 'gfsh_comment_enabled';
	const OPTION_POW_ENABLED             = 'gfsh_comment_pow_enabled';
	const OPTION_AI_ENABLED              = 'gfsh_comment_ai_enabled';
	const OPTION_BYPASS_LOGGEDIN         = 'gfsh_comment_bypass_loggedin';
	const OPTION_POW_PROTECTION_LEVEL    = 'gfsh_comment_pow_protection_level';
	const OPTION_AI_CUSTOM_CONTEXT       = 'gfsh_comment_ai_custom_context';
	const OPTION_AI_CONFIDENCE_THRESHOLD = 'gfsh_comment_ai_confidence_threshold';
	const OPTION_POW_ACTION              = 'gfsh_comment_pow_action';
	const OPTION_AI_ACTION               = 'gfsh_comment_ai_action';
	const OPTION_POW_FAIL_MESSAGE        = 'gfsh_comment_pow_fail_message';
	const OPTION_AI_FAIL_MESSAGE         = 'gfsh_comment_ai_fail_message';

	/**
	 * Registers hooks for the Discussion settings page.
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Registers settings section and fields on the Discussion page.
	 *
	 * The visible UI is rendered by React. This method registers:
	 * 1. A settings section with a heading and description
	 * 2. A settings field row with the enable toggle + React root
	 * 3. Hidden fields for all React-managed state
	 */
	public function register_settings(): void {
		// Section heading + description.
		add_settings_section(
			'gfsh_comment_section',
			__( 'Spam Hexer Comment Protection', 'pdm-antispam' ),
			[ $this, 'render_section_description' ],
			'discussion'
		);

		// Table row: left column = label, right column = checkbox + React root.
		add_settings_field(
			'gfsh_comment_protection',
			__( 'PDM Anti-Spam', 'pdm-antispam' ),
			[ $this, 'render_field' ],
			'discussion',
			'gfsh_comment_section'
		);

		// Register all options so WP Settings API saves them.
		$this->register_options();
	}

	/**
	 * Registers all wp_options for comment settings.
	 */
	private function register_options(): void {
		$options = [
			self::OPTION_ENABLED                 => [ '0', 'sanitize_text_field' ],
			self::OPTION_POW_ENABLED             => [ '1', 'sanitize_text_field' ],
			self::OPTION_AI_ENABLED              => [ '1', 'sanitize_text_field' ],
			self::OPTION_BYPASS_LOGGEDIN         => [ '1', 'sanitize_text_field' ],
			self::OPTION_POW_PROTECTION_LEVEL    => [ 'standard', 'sanitize_text_field' ],
			self::OPTION_AI_CUSTOM_CONTEXT       => [ '', 'sanitize_textarea_field' ],
			self::OPTION_AI_CONFIDENCE_THRESHOLD => [ '0.50', 'sanitize_text_field' ],
			self::OPTION_POW_ACTION              => [ 'spam', 'sanitize_text_field' ],
			self::OPTION_AI_ACTION               => [ 'spam', 'sanitize_text_field' ],
			self::OPTION_POW_FAIL_MESSAGE        => [ '', 'sanitize_text_field' ],
			self::OPTION_AI_FAIL_MESSAGE         => [ '', 'sanitize_text_field' ],
		];

		foreach ( $options as $name => [ $default, $sanitize ] ) {
			register_setting( 'discussion', $name, [
				'type'              => 'string',
				'default'           => $default,
				'sanitize_callback' => $sanitize,
			] );
		}
	}

	// -------------------------------------------------------------------------
	// Static option getters
	// -------------------------------------------------------------------------

	/**
	 * Whether comment protection is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Whether PoW is enabled for comments.
	 *
	 * @return bool
	 */
	public static function is_pow_enabled(): bool {
		return (bool) get_option( self::OPTION_POW_ENABLED, true );
	}

	/**
	 * Whether AI classification is enabled for comments.
	 *
	 * @return bool
	 */
	public static function is_ai_enabled(): bool {
		return (bool) get_option( self::OPTION_AI_ENABLED, true );
	}

	/**
	 * Gets the AI confidence threshold for comments.
	 *
	 * @return float
	 */
	public static function get_ai_confidence_threshold(): float {
		return (float) get_option( self::OPTION_AI_CONFIDENCE_THRESHOLD, '0.50' );
	}

	/**
	 * Gets the PoW action for comments ('spam' or 'fail').
	 *
	 * @return string
	 */
	public static function get_pow_action(): string {
		$action = (string) get_option( self::OPTION_POW_ACTION, 'spam' );
		return in_array( $action, [ 'spam', 'fail' ], true ) ? $action : 'spam';
	}

	/**
	 * Gets the AI action for comments ('spam' or 'fail').
	 *
	 * @return string
	 */
	public static function get_ai_action(): string {
		$action = (string) get_option( self::OPTION_AI_ACTION, 'spam' );
		return in_array( $action, [ 'spam', 'fail' ], true ) ? $action : 'spam';
	}

	/**
	 * Gets the fail validation message for a technique.
	 *
	 * Mirrors Settings::get_fail_message() but reads from comment-specific options.
	 *
	 * @param string $technique 'pow' or 'ai'.
	 *
	 * @return string
	 */
	public static function get_fail_message( string $technique ): string {
		$option = 'pow' === $technique ? self::OPTION_POW_FAIL_MESSAGE : self::OPTION_AI_FAIL_MESSAGE;
		$msg    = (string) get_option( $option, '' );

		if ( $msg !== '' ) {
			return $msg;
		}

		return __( 'Your submission could not be processed. Please try again.', 'pdm-antispam' );
	}

	/**
	 * Gets the action to take when spam is detected.
	 *
	 * Defaults to 'spam' (marks comment for review). Use the
	 * `gfsh_comment_spam_action` filter to change this to 'trash'.
	 *
	 * @return string 'spam' or 'trash'.
	 */
	public static function get_spam_action(): string {
		/**
		 * Filters the action taken when a comment is detected as spam.
		 *
		 * @since 1.0
		 *
		 * @param string $action The spam action. 'spam' marks for review; 'trash' moves to trash.
		 */
		$action = apply_filters( 'gfsh_comment_spam_action', 'spam' );
		return in_array( $action, [ 'spam', 'trash' ], true ) ? $action : 'spam';
	}

	/**
	 * Whether to bypass checks for logged-in users.
	 *
	 * @return bool
	 */
	public static function should_bypass_logged_in(): bool {
		return (bool) get_option( self::OPTION_BYPASS_LOGGEDIN, true );
	}

	// -------------------------------------------------------------------------
	// Section renderer (description + field)
	// -------------------------------------------------------------------------

	/**
	 * Renders the section description below the heading.
	 */
	public function render_section_description(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Protect your WordPress comments from spam using Proof of Work challenges and AI classification.', 'pdm-antispam' )
		);
	}

	/**
	 * Renders the settings field: enable toggle + React root + hidden fields.
	 *
	 * This is called by add_settings_field() and renders inside a <td> cell
	 * within the form-table, matching the native Discussion page layout.
	 */
	public function render_field(): void {
		$enabled = self::is_enabled();

		// Native enable toggle (always visible, even without JS).
		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_ENABLED ),
			checked( $enabled, true, false ),
			esc_html__( 'Protect WordPress comments with Spam Hexer', 'pdm-antispam' )
		);

		// React root container.
		printf(
			'<div id="gfsh-comment-settings-root" class="gfsh-comment-settings-root"%s>%s</div>',
			$enabled ? '' : ' style="display:none"',
			esc_html__( 'Loading settings…', 'pdm-antispam' )
		);

		// Hidden fields for React-managed state.
		$this->render_hidden_fields();

		// Inline script to toggle React root visibility when enable checkbox changes.
		?>
		<script>
		(function() {
			var checkbox = document.querySelector('input[name="<?php echo esc_js( self::OPTION_ENABLED ); ?>"]');
			var root = document.getElementById('gfsh-comment-settings-root');
			if (checkbox && root) {
				checkbox.addEventListener('change', function() {
					root.style.display = this.checked ? '' : 'none';
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Renders hidden input fields for all React-managed settings.
	 *
	 * These are synced from the React store via JS and saved by the
	 * WP Settings API when the Discussion page form is submitted.
	 */
	private function render_hidden_fields(): void {
		$fields = [
			self::OPTION_POW_ENABLED             => get_option( self::OPTION_POW_ENABLED, '1' ),
			self::OPTION_AI_ENABLED              => get_option( self::OPTION_AI_ENABLED, '1' ),
			self::OPTION_BYPASS_LOGGEDIN         => get_option( self::OPTION_BYPASS_LOGGEDIN, '1' ),
			self::OPTION_POW_PROTECTION_LEVEL    => get_option( self::OPTION_POW_PROTECTION_LEVEL, 'standard' ),
			self::OPTION_AI_CUSTOM_CONTEXT       => get_option( self::OPTION_AI_CUSTOM_CONTEXT, '' ),
			self::OPTION_AI_CONFIDENCE_THRESHOLD => get_option( self::OPTION_AI_CONFIDENCE_THRESHOLD, '0.50' ),
			self::OPTION_POW_ACTION              => get_option( self::OPTION_POW_ACTION, 'spam' ),
			self::OPTION_AI_ACTION               => get_option( self::OPTION_AI_ACTION, 'spam' ),
			self::OPTION_POW_FAIL_MESSAGE        => get_option( self::OPTION_POW_FAIL_MESSAGE, '' ),
			self::OPTION_AI_FAIL_MESSAGE         => get_option( self::OPTION_AI_FAIL_MESSAGE, '' ),
		];

		foreach ( $fields as $name => $value ) {
			printf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( $name ),
				esc_attr( $value )
			);
		}
	}

	// -------------------------------------------------------------------------
	// Script enqueue
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the comment settings React script on the Discussion page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( $hook_suffix !== 'options-discussion.php' ) {
			return;
		}

		$asset_path = plugin_dir_path( dirname( __DIR__ ) ) . 'js/built/pdm-antispam-comment-settings.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;
		$plugin_url = plugin_dir_url( dirname( __DIR__ ) );

		wp_enqueue_script(
			'pdm-antispam-comment-settings',
			$plugin_url . 'js/built/pdm-antispam-comment-settings.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations(
			'pdm-antispam-comment-settings',
			'pdm-antispam',
			plugin_dir_path( dirname( __DIR__ ) ) . 'languages/'
		);

		// Enqueue extracted CSS if it exists.
		$css_path = plugin_dir_path( dirname( __DIR__ ) ) . 'assets/css/built/pdm-antispam-comment-settings.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'pdm-antispam-comment-settings',
				$plugin_url . 'assets/css/built/pdm-antispam-comment-settings.css',
				[],
				$asset_file['version']
			);
		}

		// Inject metadata for the React app (settings values are read from hidden inputs).
		wp_localize_script(
			'pdm-antispam-comment-settings',
			'gfsh_comment_settings',
			[
				'pluginSettingsUrl'    => admin_url( 'admin.php?page=gf_settings&subview=pdm-antispam' ),
				'aiProviderConfigured' => $this->is_ai_provider_configured(),
				'commentStats'         => $this->get_comment_stats(),
			]
		);
	}

	/**
	 * Checks whether the AI provider is configured and ready to use.
	 *
	 * Mirrors the logic in AI_Classifier::is_enabled():
	 * - 'auto' mode: WP AI Client must be available (wp_supports_ai()).
	 * - 'openrouter' mode: an API key must be set.
	 *
	 * @return bool
	 */
	private function is_ai_provider_configured(): bool {
		$provider = Settings::get( 'ai_provider', 'auto' );
		$api_key  = Settings::get( 'ai_api_key', '' );

		if ( $provider === 'auto' ) {
			return function_exists( 'wp_supports_ai' ) && wp_supports_ai();
		}

		return ! empty( $api_key );
	}

	/**
	 * Gets comment stats for the JS stats tab.
	 *
	 * Returns the full enriched comments object (summary + pow + ai +
	 * signals/reasons) along with period_days so the React dashboard can
	 * render the same technique breakdown shown for form entries.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_comment_stats(): ?array {
		$stats = \PDM_Antispam\Admin\Dashboard_Stats::get_stats();

		if ( $stats === null || empty( $stats['comments'] ) ) {
			return null;
		}

		return [
			'period_days' => $stats['period_days'] ?? 30,
			'comments'    => $stats['comments'],
		];
	}
}
