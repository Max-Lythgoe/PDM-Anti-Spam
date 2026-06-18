<?php
/**
 * Dev Tools Integration — QM panel bridge.
 *
 * Hooks into GF Dev Tools' extension points to display per-form
 * spam protection status, PoW configuration, and submission results
 * in the Query Monitor debug panel.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\DevTools;

use PDM_Antispam\Settings;
use PDM_Antispam\Spam_Result;

/**
 * Bridges PDM Anti-Spam data into the GF Dev Tools QM panel.
 */
class Dev_Tools_Integration {

	/**
	 * Extension slug used as the key in Dev Tools' extension data arrays.
	 */
	private const SLUG = 'spam-hexer';

	/**
	 * The QM collector instance, stored when the collector fires set_up.
	 *
	 * @var \QM_Collector_GF_Forms|null
	 */
	private $collector = null;

	/**
	 * Register hooks into Dev Tools' extension points.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'gf_dev_tools_qm_collector_set_up', [ $this, 'on_collector_set_up' ] );
		add_action( 'gf_dev_tools_qm_after_form_row', [ $this, 'render_form_section' ], 10, 3 );
		add_action( 'gf_dev_tools_qm_after_form_rows', [ $this, 'render_submission_results' ] );

		// If the collector set_up action already fired (timing: GF Dev Tools
		// initializes during gform_loaded before PDM Anti-Spam's init()),
		// grab the collector from QM directly.
		if ( did_action( 'gf_dev_tools_qm_collector_set_up' ) && class_exists( '\QM_Collectors' ) ) {
			$collector = \QM_Collectors::get( 'gf-forms' );
			if ( $collector ) {
				$this->on_collector_set_up( $collector );
			}
		}
	}

	/**
	 * Called when the QM collector is set up.
	 *
	 * Stores the collector reference and hooks into gform_pre_render at
	 * priority 6 (after the collector's own priority 5 capture).
	 *
	 * @param object $collector The QM_Collector_GF_Forms instance.
	 *
	 * @return void
	 */
	public function on_collector_set_up( $collector ): void {
		$this->collector = $collector;
		add_filter( 'gform_pre_render', [ $this, 'collect_form_debug_info' ], 6, 4 );
	}

	/**
	 * Collects per-form debug info and passes it to the QM collector.
	 *
	 * Hooked to gform_pre_render at priority 6 so it runs after the
	 * collector captures the form at priority 5.
	 *
	 * @param array  $form         The GF form array.
	 * @param bool   $ajax         Whether this is an AJAX render.
	 * @param array  $field_values Pre-populated field values.
	 * @param string $context      The render context.
	 *
	 * @return array The unmodified form array.
	 */
	public function collect_form_debug_info( $form, $ajax = false, $field_values = [], $context = 'form_display' ) {
		if ( $context !== 'form_display' || ! $this->collector ) {
			return $form;
		}

		$form_id = (int) rgar( $form, 'id' );
		if ( ! $form_id ) {
			return $form;
		}

		$debug_info = $this->build_form_debug_info( $form );

		$this->collector->add_extension_data( self::SLUG, $form_id, $debug_info );
		$this->collector->add_extension_script_data( self::SLUG, [
			'forceCheckParam'  => 'gfsh_force',
			'powOnSubmitParam' => 'gfsh_pow_on_submit',
			'hasDebugApi'      => true,
		] );

		return $form;
	}

	/**
	 * Assembles the debug info array for a single form.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return array<string, mixed>
	 */
	private function build_form_debug_info( array $form ): array {
		$form_id           = (int) rgar( $form, 'id' );
		$frontend          = PDM_Antispam()->frontend;
		$bypass_reason     = '';
		$protection_active = true;

		if ( ! Settings::is_enabled() ) {
			$bypass_reason     = 'globally_disabled';
			$protection_active = false;
		} elseif ( $frontend && ! $frontend->is_applicable_form( $form ) ) {
			$bypass_reason     = 'form_disabled';
			$protection_active = false;
		} elseif ( Settings::should_bypass_logged_in() && is_user_logged_in() && ! Settings::is_force_check() ) {
			$bypass_reason     = 'logged_in';
			$protection_active = false;
		}

		return [
			'form_id'           => $form_id,
			'protection_active' => $protection_active,
			'bypass_reason'     => $bypass_reason,
			'force_check'       => Settings::is_force_check(),
			'techniques'        => [
				'pow' => [
					'enabled'    => Settings::is_technique_enabled_for_form( 'pow', $form ),
					'difficulty' => Settings::get_pow_difficulty( $form_id ),
					'action'     => Settings::get_for_form( 'pow_action', $form, 'spam' ),
				],
				'ai'  => [
					'enabled'              => Settings::is_technique_enabled_for_form( 'ai', $form ),
					'provider'             => Settings::get( 'ai_provider', 'auto' ),
					'model'                => Settings::get( 'ai_model', '' ),
					'action'               => Settings::get_for_form( 'ai_action', $form, 'spam' ),
					'confidence_threshold' => Settings::get_ai_confidence_threshold(),
				],
			],
		];
	}

	/**
	 * Records a submission result in the QM collector.
	 *
	 * Called from the main plugin class after Spam_Checker::evaluate().
	 *
	 * @param int         $form_id        The form ID.
	 * @param Spam_Result $result         The spam analysis result.
	 * @param float       $total_ms       Total evaluation time in milliseconds.
	 * @param bool        $was_bypassed   Whether the check was bypassed.
	 * @param string      $bypass_reason  The bypass reason (if bypassed).
	 *
	 * @return void
	 */
	public function collect_submission_result( int $form_id, Spam_Result $result, float $total_ms, bool $was_bypassed, string $bypass_reason ): void {
		if ( ! $this->collector ) {
			return;
		}

		$this->collector->add_extension_submission( self::SLUG, $form_id, [
			'is_spam'           => $result->is_spam(),
			'action'            => $result->get_action(),
			'signals'           => $result->get_signals(),
			'technique_results' => $result->get_technique_results(),
			'total_ms'          => round( $total_ms ),
			'was_bypassed'      => $was_bypassed,
			'bypass_reason'     => $bypass_reason,
		] );
	}

	/**
	 * Records a bypass result in the QM collector.
	 *
	 * Called from the main plugin class when the spam check is bypassed.
	 *
	 * @param int    $form_id       The form ID.
	 * @param string $bypass_reason The reason for bypassing.
	 *
	 * @return void
	 */
	public function collect_bypass_result( int $form_id, string $bypass_reason ): void {
		if ( ! $this->collector ) {
			return;
		}

		$this->collector->add_extension_submission( self::SLUG, $form_id, [
			'was_bypassed'  => true,
			'bypass_reason' => $bypass_reason,
		] );
	}

	// =========================================================================
	// QM Panel Rendering
	// =========================================================================

	/**
	 * Renders the per-form debug section below each form row in the QM panel.
	 *
	 * @param int    $form_id   The form ID.
	 * @param array  $form_data The form data from the collector.
	 * @param object $data      The full QM output data object.
	 *
	 * @return void
	 */
	public function render_form_section( int $form_id, array $form_data, $data ): void {
		$ext_data = $data->extensions[ self::SLUG ][ $form_id ] ?? null;
		if ( ! $ext_data ) {
			return;
		}

		$col_count = \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ? 5 : 4;

		// Determine badge.
		$badge_color = '#46b450'; // green
		$badge_text  = 'ACTIVE';
		if ( ! $ext_data['protection_active'] ) {
			$badge_color = '#ffb900'; // amber
			$badge_text  = 'BYPASSED';
		} elseif ( $ext_data['force_check'] ) {
			$badge_color = '#00a0d2'; // blue
			$badge_text  = 'FORCE CHECK';
		}

		// Status text — only shown when not simply "Active".
		$status_text = null;
		if ( ! $ext_data['protection_active'] ) {
			$reason_labels = [
				'globally_disabled' => 'globally disabled',
				'form_disabled'     => 'form disabled',
				'logged_in'         => 'logged-in user',
			];
			$status_text   = 'Bypassed: ' . ( $reason_labels[ $ext_data['bypass_reason'] ] ?? $ext_data['bypass_reason'] );
		} elseif ( $ext_data['force_check'] ) {
			$status_text = 'Force check active (logged-in bypass overridden)';
		}

		// PoW info.
		$pow = $ext_data['techniques']['pow'];
		if ( $pow['enabled'] ) {
			$pow_text = sprintf(
				'Enabled · Difficulty: %d · Action: %s',
				$pow['difficulty'],
				self::action_labels()[ $pow['action'] ] ?? $pow['action']
			);
		} else {
			$pow_text = 'Disabled';
		}

		// AI info — threshold merged in.
		$ai = $ext_data['techniques']['ai'];
		if ( $ai['enabled'] ) {
			$threshold_pct = (int) round( ( $ai['confidence_threshold'] ?? 0.5 ) * 100 );
			$ai_text       = sprintf(
				'Enabled · Provider: %s · Model: %s · Action: %s · Threshold: %d%%',
				$ai['provider'],
				$ai['model'] ?: '(default)',
				self::action_labels()[ $ai['action'] ] ?? $ai['action'],
				$threshold_pct
			);
		} else {
			$ai_text = 'Disabled';
		}

		$s            = $this->get_panel_styles();
		$badge_style  = sprintf( '%sbackground:%s;', $s['badge_base'], $badge_color );
		$label_style  = $s['label'];
		$value_style  = $s['value'];
		$grid_style   = $s['grid'];
		$header_style = $s['header'];
		$btn_style    = $s['button'];

		$fid = esc_attr( (string) $form_id );

		?>
		<tr class="qm-gf-extension-row" data-qm-form-id="<?php echo $fid; ?>">
			<td colspan="<?php echo esc_attr( (string) $col_count ); ?>">
				<div style="<?php echo esc_attr( $header_style ); ?>">
					Spam Hexer
					<span style="<?php echo esc_attr( $badge_style ); ?>"><?php echo esc_html( $badge_text ); ?></span>
				</div>
				<div style="<?php echo esc_attr( $grid_style ); ?>" data-gfsh-grid="<?php echo $fid; ?>">
					<?php if ( $status_text !== null ) : ?>
					<span style="<?php echo esc_attr( $label_style ); ?>">Status</span>
					<span style="<?php echo esc_attr( $value_style ); ?>">
						<?php echo esc_html( $status_text ); ?>
						<?php if ( \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) : ?>
							<?php if ( $ext_data['force_check'] ) : ?>
						<button type="button" style="<?php echo esc_attr( $btn_style ); ?>margin-left:6px;" data-action="gfsh-remove-force-check" data-form-id="<?php echo $fid; ?>">Remove Force Check</button>
						<?php elseif ( ! $ext_data['protection_active'] ) : ?>
						<button type="button" style="<?php echo esc_attr( $btn_style ); ?>margin-left:6px;" data-action="gfsh-force-check" data-form-id="<?php echo $fid; ?>">Force Check</button>
						<?php endif; ?>
						<?php endif; ?>
					</span>
					<?php endif; ?>

					<span style="<?php echo esc_attr( $label_style ); ?>">PoW Config</span>
					<span style="<?php echo esc_attr( $value_style ); ?>"><?php echo esc_html( $pow_text ); ?></span>

					<?php // Live rows — hidden until JS populates them (only shown when PoW manager exists). ?>
					<span style="<?php echo esc_attr( $label_style ); ?>display:none;" data-gfsh-live-label="pow-status-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">PoW Status</span>
					<span style="<?php echo esc_attr( $value_style ); ?>display:none;" data-gfsh-live="pow-status-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">
						<?php if ( \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) : ?>
						<button type="button" style="<?php echo esc_attr( $btn_style ); ?>margin-left:6px;display:none;" data-action="gfsh-clear-solution" data-form-id="<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">Clear Solution</button>
						<?php endif; ?>
					</span>

					<span style="<?php echo esc_attr( $label_style ); ?>display:none;" data-gfsh-live-label="payload-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">Payload</span>
					<span style="<?php echo esc_attr( $value_style ); ?>display:none;" data-gfsh-live="payload-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>"></span>

					<span style="<?php echo esc_attr( $label_style ); ?>display:none;" data-gfsh-live-label="expires-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">Expires</span>
					<span style="<?php echo esc_attr( $value_style ); ?>display:none;" data-gfsh-live="expires-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">
						<?php if ( \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) : ?>
						<button type="button" style="<?php echo esc_attr( $btn_style ); ?>margin-left:6px;display:none;" data-action="gfsh-force-expire" data-form-id="<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">Force Expire</button>
						<?php endif; ?>
					</span>

					<span style="<?php echo esc_attr( $label_style ); ?>display:none;" data-gfsh-live-label="challenge-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">Challenge</span>
					<span style="<?php echo esc_attr( $value_style ); ?>display:none;" data-gfsh-live="challenge-<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">
						<?php if ( \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) : ?>
						<button type="button" style="<?php echo esc_attr( $btn_style ); ?>margin-left:6px;display:none;" data-action="gfsh-force-fallback" data-form-id="<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">Use Fallback Challenge</button>
						<button type="button" style="<?php echo esc_attr( $btn_style ); ?>margin-left:6px;display:none;" data-action="gfsh-pow-on-submit" data-form-id="<?php echo $fid; ?>" data-gfsh-requires-pow="<?php echo $fid; ?>">Defer to Submit</button>
						<?php endif; ?>
					</span>

					<span style="<?php echo esc_attr( $label_style ); ?>">AI</span>
					<span style="<?php echo esc_attr( $value_style ); ?>"><?php echo esc_html( $ai_text ); ?></span>
				</div>
				<div data-gfsh-event-log="<?php echo $fid; ?>" style="margin-top:6px;display:none;"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders submission result rows after all form rows in the QM panel.
	 *
	 * @param object $data The full QM output data object.
	 *
	 * @return void
	 */
	public function render_submission_results( $data ): void {
		$submissions = $data->extension_submissions[ self::SLUG ] ?? [];
		if ( empty( $submissions ) ) {
			return;
		}

		$col_count = \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ? 5 : 4;

		$s            = $this->get_panel_styles();
		$badge_base   = $s['badge_base'];
		$label_style  = $s['label'];
		$value_style  = $s['value'];
		$grid_style   = $s['grid'];
		$header_style = $s['header'];

		foreach ( $submissions as $form_id => $submission ) {
			// Bypass-only submission.
			if ( ! empty( $submission['was_bypassed'] ) && ! isset( $submission['action'] ) ) {
				$reason_labels = [
					'globally_disabled' => 'globally disabled',
					'form_disabled'     => 'form disabled',
					'logged_in'         => 'logged-in user',
				];
				$reason_text   = $reason_labels[ $submission['bypass_reason'] ] ?? $submission['bypass_reason'];
				?>
				<tr data-qm-form-id="<?php echo esc_attr( (string) $form_id ); ?>">
					<td colspan="<?php echo esc_attr( (string) $col_count ); ?>">
						<div style="<?php echo esc_attr( $header_style ); ?>">
							Submission Result — Form #<?php echo esc_html( (string) $form_id ); ?>
							<span style="<?php echo esc_attr( $badge_base . 'background:#ffb900;' ); ?>">BYPASSED</span>
						</div>
						<div style="<?php echo esc_attr( $grid_style ); ?>">
							<span style="<?php echo esc_attr( $label_style ); ?>">Status</span>
							<span style="<?php echo esc_attr( $value_style ); ?>">Bypassed: <?php echo esc_html( $reason_text ); ?></span>
						</div>
					</td>
				</tr>
				<?php
				continue;
			}

			// Full submission result.
			$action      = $submission['action'] ?? 'unknown';
			$badge_color = '#46b450'; // green (allow)
			if ( $action === 'reject' || $action === 'fail' ) {
				$badge_color = '#dc3232'; // red
			} elseif ( $action === 'mark_spam' ) {
				$badge_color = '#ffb900'; // amber
			}

			$signals_text = ! empty( $submission['signals'] )
				? implode( ', ', $submission['signals'] )
				: '(none)';

			?>
			<tr data-qm-form-id="<?php echo esc_attr( (string) $form_id ); ?>">
				<td colspan="<?php echo esc_attr( (string) $col_count ); ?>">
					<div style="<?php echo esc_attr( $header_style ); ?>">
						Submission Result — Form #<?php echo esc_html( (string) $form_id ); ?>
						<span style="<?php echo esc_attr( $badge_base . 'background:' . $badge_color . ';' ); ?>"><?php echo esc_html( strtoupper( $action ) ); ?></span>
					</div>
					<div style="<?php echo esc_attr( $grid_style ); ?>">
							<span style="<?php echo esc_attr( $label_style ); ?>">Verdict</span>
							<span style="<?php echo esc_attr( $value_style ); ?>"><?php echo esc_html( self::action_labels()[ $action ] ?? ucfirst( $action ) ); ?></span>
							<span style="<?php echo esc_attr( $label_style ); ?>">Total Time</span>
							<span style="<?php echo esc_attr( $value_style ); ?>"><?php echo esc_html( (string) $submission['total_ms'] ); ?>ms</span>
							<span style="<?php echo esc_attr( $label_style ); ?>">Signals</span>
							<span style="<?php echo esc_attr( $value_style ); ?>"><?php echo esc_html( $signals_text ); ?></span>
						</div>
					<?php if ( ! empty( $submission['technique_results'] ) ) : ?>
							<div style="margin-top:8px;">
								<div style="font-weight:600;font-size:10px;padding:2px 0;color:var(--qm-container-fg,#333);">Technique Breakdown</div>
								<table style="width:100%;border-collapse:collapse;margin-top:4px;font-size:11px;">
									<thead>
										<tr>
											<th style="text-align:left;font-weight:600;font-size:10px;color:var(--qm-info-fg,#666);padding:2px 8px 2px 0;border-bottom:1px solid var(--qm-cell-border,#e9e9eb);white-space:nowrap;">Technique</th>
											<th style="text-align:left;font-weight:600;font-size:10px;color:var(--qm-info-fg,#666);padding:2px 8px 2px 0;border-bottom:1px solid var(--qm-cell-border,#e9e9eb);white-space:nowrap;">Verdict</th>
											<th style="text-align:left;font-weight:600;font-size:10px;color:var(--qm-info-fg,#666);padding:2px 0;border-bottom:1px solid var(--qm-cell-border,#e9e9eb);">Details</th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $submission['technique_results'] as $tech_name => $tech_data ) : ?>
										<?php
										$tech_label = self::technique_labels()[ $tech_name ] ?? ucfirst( $tech_name );
										$checker    = PDM_Antispam()->spam_checker ?? null;
										$technique  = $checker ? $checker->get_technique( $tech_name ) : null;
										$metadata   = $tech_data['metadata'] ?? [];
										$is_spam    = ! empty( $tech_data['is_spam'] );
										$is_skipped = ! empty( $metadata['skipped'] );

										// Verdict badge.
										if ( $is_skipped ) {
											$verdict_text  = 'Skipped';
											$verdict_color = '#ffb900';
										} elseif ( $is_spam ) {
											$verdict_text  = 'Spam';
											$verdict_color = '#dc3232';
										} else {
											$verdict_text  = 'Clean';
											$verdict_color = '#46b450';
										}

										// Headline (short summary).
										$headline = $technique ? $technique->format_headline( $tech_data ) : '';

										// Detail key-value pairs, minus headline-covered keys.
										$details      = $technique ? $technique->format_details( $tech_data ) : [];
										$skip_keys    = self::headline_covered_keys()[ $tech_name ] ?? [];
										$detail_parts = [];
										foreach ( $details as $d_label => $d_value ) {
											if ( in_array( $d_label, $skip_keys, true ) ) {
												continue;
											}
											$detail_parts[] = sprintf(
												'<span style="color:var(--qm-info-fg,#666);">%s:</span> %s',
												esc_html( $d_label ),
												esc_html( $d_value )
											);
										}
										?>
										<tr>
											<td style="padding:3px 8px 3px 0;vertical-align:top;white-space:nowrap;color:var(--qm-container-fg,#333);font-weight:600;"><?php echo esc_html( $tech_label ); ?></td>
											<td style="padding:3px 8px 3px 0;vertical-align:top;white-space:nowrap;">
												<span style="display:inline-block;padding:0 5px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:<?php echo esc_attr( $verdict_color ); ?>;"><?php echo esc_html( strtoupper( $verdict_text ) ); ?></span>
												<?php if ( $headline ) : ?>
												<span style="color:var(--qm-container-fg,#333);margin-left:4px;"><?php echo esc_html( $headline ); ?></span>
												<?php endif; ?>
											</td>
											<td style="padding:3px 0;vertical-align:top;color:var(--qm-container-fg,#333);">
												<?php if ( ! empty( $detail_parts ) ) : ?>
												<span style="display:flex;flex-wrap:wrap;gap:4px 12px;">
													<?php foreach ( $detail_parts as $part ) : ?>
													<span><?php echo $part; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?></span>
													<?php endforeach; ?>
												</span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
								<?php foreach ( $submission['technique_results'] as $tech_name => $tech_data ) : ?>
									<?php
									$hints        = $tech_data['metadata']['hints'] ?? [];
									$raw_request  = $tech_data['metadata']['raw_request'] ?? null;
									$raw_response = $tech_data['metadata']['raw_response'] ?? null;
									if ( $raw_request === null && $raw_response === null && empty( $hints ) ) {
										continue;
									}
									// raw_response is a JSON string — decode for pretty-printing.
									$decoded = is_string( $raw_response ) ? json_decode( $raw_response, true ) : $raw_response;
									$pretty  = $decoded !== null
										? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
										: $raw_response;
									// raw_request is already an array — encode for pretty-printing.
									// Strip 'messages' for the pretty-print so it's shown separately below.
									$request_for_display = is_array( $raw_request )
										? array_diff_key( $raw_request, [ 'messages' => true ] )
										: null;
									$pretty_request      = $request_for_display !== null
										? wp_json_encode( $request_for_display, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
										: null;
									$tech_label          = self::technique_labels()[ $tech_name ] ?? ucfirst( $tech_name );
									?>
									<div style="margin-top:8px;">
										<div style="font-weight:600;font-size:10px;padding:2px 0;color:var(--qm-container-fg,#333);"><?php echo esc_html( $tech_label ); ?> — Raw Response</div>
										<?php if ( ! empty( $hints ) ) : ?>
										<ul style="margin:4px 0 6px;padding-left:16px;font-size:10px;line-height:1.6;color:var(--qm-container-fg,#1e1e1e);">
											<?php foreach ( $hints as $hint ) : ?>
											<li><?php echo esc_html( $hint ); ?></li>
											<?php endforeach; ?>
										</ul>
										<?php endif; ?>
										<?php if ( $pretty !== false && $pretty !== null ) : ?>
										<pre style="margin:0;padding:8px;background:var(--qm-container-bg,#f0f0f0);color:var(--qm-container-fg,#1e1e1e);border:1px solid var(--qm-cell-border,#e9e9eb);border-radius:3px;font-size:10px;line-height:1.5;overflow-x:auto;white-space:pre-wrap;word-break:break-all;"><?php echo esc_html( (string) $pretty ); ?></pre>
										<?php endif; ?>
										<?php if ( is_array( $raw_request ) ) : ?>
										<div style="font-weight:600;font-size:10px;padding:6px 0 2px;color:var(--qm-container-fg,#333);"><?php echo esc_html( $tech_label ); ?> — Request</div>
											<?php if ( $pretty_request !== null && $pretty_request !== false ) : ?>
										<pre style="margin:0 0 4px;padding:8px;background:var(--qm-container-bg,#f0f0f0);color:var(--qm-container-fg,#1e1e1e);border:1px solid var(--qm-cell-border,#e9e9eb);border-radius:3px;font-size:10px;line-height:1.5;overflow-x:auto;white-space:pre-wrap;word-break:break-all;"><?php echo esc_html( (string) $pretty_request ); ?></pre>
										<?php endif; ?>
											<?php if ( ! empty( $raw_request['messages'] ) ) : ?>
												<?php foreach ( $raw_request['messages'] as $msg ) : ?>
										<div style="margin-top:4px;border:1px solid var(--qm-cell-border,#e9e9eb);border-radius:3px;overflow:hidden;">
											<div style="padding:3px 8px;font-size:10px;font-weight:600;background:var(--qm-panel-bg,#f8f8f8);color:var(--qm-info-fg,#666);border-bottom:1px solid var(--qm-cell-border,#e9e9eb);"><?php echo esc_html( $msg['role'] ?? '' ); ?></div>
											<pre style="margin:0;padding:8px;background:var(--qm-container-bg,#f0f0f0);color:var(--qm-container-fg,#1e1e1e);font-size:10px;line-height:1.5;overflow-x:auto;white-space:pre-wrap;word-break:break-all;"><?php echo esc_html( $msg['content'] ?? '' ); ?></pre>
										</div>
										<?php endforeach; ?>
										<?php endif; ?>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Human-readable technique display names, keyed by technique slug.
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
	 * Human-readable action labels, keyed by action slug.
	 *
	 * @return array<string, string>
	 */
	private static function action_labels(): array {
		return [
			'allow'     => 'Allowed',
			'reject'    => 'Rejected',
			'fail'      => 'Failed',
			'mark_spam' => 'Marked as Spam',
		];
	}

	/**
	 * Detail keys that are already expressed in each technique's headline.
	 *
	 * These are excluded from the technique breakdown table's Details column
	 * to avoid redundant output like "solved in 62ms" + "Solve Time: 62ms".
	 *
	 * @return array<string, string[]>
	 */
	private static function headline_covered_keys(): array {
		return [
			'pow' => [ 'Solve Time' ],
			'ai'  => [ 'Spam Probability' ],
		];
	}

	/**
	 * Returns shared inline style strings for QM panel rendering.
	 *
	 * @return array{label: string, value: string, grid: string, header: string, badge_base: string, button: string}
	 */
	private function get_panel_styles(): array {
		return [
			'label'      => 'font-weight:600;font-size:11px;color:var(--qm-info-fg, #666);min-width:70px;',
			'value'      => 'font-size:11px;color:var(--qm-container-fg, #333);',
			'grid'       => 'display:grid;grid-template-columns:auto 1fr;gap:3px 10px;margin-top:6px;',
			'header'     => 'font-weight:600;font-size:12px;padding:4px 0;color:var(--qm-container-fg, #333);',
			'badge_base' => 'display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:600;letter-spacing:0.5px;color:#fff;margin-left:6px;vertical-align:middle;',
			'button'     => 'display:inline-block;padding:2px 8px;margin-right:4px;font-size:10px;border:1px solid var(--qm-cell-border, #ccc);border-radius:3px;background:var(--qm-button-bg, #f7f7f7);color:var(--qm-button-fg, #333);cursor:pointer;line-height:1.6;',
		];
	}
}
