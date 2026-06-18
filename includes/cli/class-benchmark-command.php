<?php
/**
 * WP-CLI benchmark command.
 *
 * Registers the `wp gfsh benchmark` command for benchmarking AI models
 * against spam classification prompts using the plugin's OpenRouter_Provider.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\CLI;

use PDM_Antispam\Settings;

/**
 * Benchmark AI models for spam classification.
 *
 * Runs classify() calls through OpenRouter_Provider — the exact same
 * code path used in production — and reports latency, token usage,
 * cost, and accuracy for each model.
 *
 * ## OPTIONS
 *
 * [--models=<models>]
 * : Comma-separated OpenRouter model IDs to benchmark.
 * Example: google/gemini-2.5-flash-lite,openai/gpt-oss-20b
 *
 * [--discover]
 * : Auto-discover models from the OpenRouter /models API.
 * Combine with --max-cost, --require-reasoning, and --providers to filter.
 *
 * [--max-cost=<float>]
 * : Maximum completion cost in $/M tokens (e.g. 1.00).
 * Only used with --discover. Default: no limit.
 *
 * [--require-reasoning]
 * : Only include models that support the reasoning parameter.
 * Only used with --discover.
 *
 * [--providers=<list>]
 * : Comma-separated provider prefixes to filter discovered models.
 * Example: qwen,deepseek,nvidia,openai,google,anthropic
 * Only used with --discover.
 *
 * [--runs=<int>]
 * : Number of runs per prompt type per model × effort combination.
 * ---
 * default: 2
 * ---
 *
 * [--prompts=<list>]
 * : Comma-separated prompt types to test.
 * ---
 * default: ham,spam,partial,large-ham,large-spam
 * options:
 *   - ham
 *   - spam
 *   - partial
 *   - large-ham
 *   - large-spam
 * ---
 *
 * [--reasoning=<list>]
 * : Comma-separated reasoning effort levels to test per model.
 * Use "none" to omit the reasoning parameter entirely.
 * ---
 * default: none,low
 * options:
 *   - low
 *   - medium
 *   - high
 *   - none
 * ---
 *
 * [--timeout=<int>]
 * : Per-request timeout in seconds.
 * ---
 * default: 15
 * ---
 *
 * [--save=<path>]
 * : Save full JSON results to this file path.
 * Example: scripts/results/2026-06-01.json
 *
 * [--api-key=<key>]
 * : OpenRouter API key. Defaults to the key saved in plugin settings.
 *
 * ## EXAMPLES
 *
 *     # Benchmark two specific models, 3 runs each
 *     wp gfsh benchmark --models=google/gemini-2.5-flash-lite,openai/gpt-oss-20b --runs=3
 *
 *     # Auto-discover cheap reasoning models from Qwen and DeepSeek
 *     wp gfsh benchmark --discover --max-cost=0.50 --require-reasoning --providers=qwen,deepseek
 *
 *     # Full sweep of all models ≤$2.50/M, save results
 *     wp gfsh benchmark --discover --max-cost=2.50 --runs=5 --save=scripts/results/2026-06-01.json
 *
 *     # Test a model at multiple reasoning effort levels
 *     wp gfsh benchmark --models=openai/gpt-oss-20b --reasoning=low,high,none --runs=3
 *
 *     # Only test spam prompts (faster)
 *     wp gfsh benchmark --models=qwen/qwen3-235b-a22b-thinking-2507 --prompts=spam,large-spam
 *
 * @when after_wp_load
 */
class Benchmark_Command extends \WP_CLI_Command {

	/**
	 * Runs the benchmark.
	 *
	 * @param string[]             $args       Positional arguments (unused).
	 * @param array<string, mixed> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		// ── Setup signal handling for graceful Ctrl+C ──────────────────────
		/** @var Benchmark_Runner|null $runner */
		$runner = null;

		if ( function_exists( 'pcntl_async_signals' ) ) {
			pcntl_async_signals( true );
		}

		if ( function_exists( 'pcntl_signal' ) ) {
			pcntl_signal( SIGINT, function () use ( &$runner ): void {
				\WP_CLI::log( '' );
				\WP_CLI::log( \WP_CLI::colorize( '%YInterrupted — printing partial results...%n' ) );
				if ( $runner !== null ) {
					$runner->interrupt();
					$runner->print_summary();
				}
				exit( 0 );
			} );
		}

		// ── Parse arguments ────────────────────────────────────────────────
		$models_arg        = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'models', '' );
		$discover          = isset( $assoc_args['discover'] );
		$max_cost          = (float) \WP_CLI\Utils\get_flag_value( $assoc_args, 'max-cost', 0 );
		$require_reasoning = isset( $assoc_args['require-reasoning'] );
		$providers_arg     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'providers', '' );
		$runs              = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'runs', 2 );
		$timeout           = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'timeout', 15 );
		$max_latency       = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'max-latency', 1500 );
		$save              = \WP_CLI\Utils\get_flag_value( $assoc_args, 'save', '' );
		$api_key_arg       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'api-key', '' );
		$verbose           = isset( $assoc_args['verbose'] );

		$prompts_raw   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'prompts', 'ham,spam,partial,large-ham,large-spam' );
		$reasoning_raw = \WP_CLI\Utils\get_flag_value( $assoc_args, 'reasoning', 'none,low' );

		// Default behavior: if no --models and no --discover, auto-discover ≤$1/M.
		if ( empty( $models_arg ) && ! $discover ) {
			$discover = true;
			if ( $max_cost <= 0 ) {
				$max_cost = 1.00;
			}
		}

		$prompt_types      = array_filter( array_map( 'trim', explode( ',', $prompts_raw ) ) );
		$reasoning_efforts = array_filter( array_map( 'trim', explode( ',', $reasoning_raw ) ) );
		$providers         = array_filter( array_map( 'trim', explode( ',', $providers_arg ) ) );

		// ── Resolve API key ────────────────────────────────────────────────
		$api_key = $api_key_arg ?: Settings::get( 'ai_api_key', '' );

		if ( empty( $api_key ) ) {
			\WP_CLI::error(
				'No OpenRouter API key found. Set one in the plugin settings or pass --api-key=<key>.'
			);
		}

		// ── Resolve model list ─────────────────────────────────────────────
		$model_ids  = [];
		$model_meta = []; // model_id => API record (for reasoning support detection)

		if ( $discover ) {
			\WP_CLI::log( \WP_CLI::colorize( '%CDiscovering models from OpenRouter API...%n' ) );

			try {
				$discovered = Model_Discovery::fetch( [
					'max_cost'          => $max_cost,
					'require_reasoning' => $require_reasoning,
					'providers'         => $providers,
				] );
			} catch ( \RuntimeException $e ) {
				\WP_CLI::error( 'Model discovery failed: ' . $e->getMessage() );
				return;
			}

			if ( empty( $discovered ) ) {
				\WP_CLI::error( 'No models matched the discovery filters. Try relaxing --max-cost or --providers.' );
				return;
			}

			foreach ( $discovered as $m ) {
				$id                = (string) $m['id'];
				$model_ids[]       = $id;
				$model_meta[ $id ] = $m;
			}

			$filter_desc = [];
			if ( $max_cost > 0 ) {
				$filter_desc[] = sprintf( '≤$%.2f/M', $max_cost );
			}
			if ( $require_reasoning ) {
				$filter_desc[] = 'reasoning only';
			}
			if ( ! empty( $providers ) ) {
				$filter_desc[] = 'providers: ' . implode( ', ', $providers );
			}

			$filter_str = ! empty( $filter_desc ) ? ' (' . implode( ', ', $filter_desc ) . ')' : '';
			\WP_CLI::log( sprintf( '  Found %d candidate(s)%s', count( $model_ids ), $filter_str ) );
			\WP_CLI::log( '' );

		} else {
			$model_ids = array_filter( array_map( 'trim', explode( ',', $models_arg ) ) );
		}

		// ── Print header ───────────────────────────────────────────────────
		\WP_CLI::log( \WP_CLI::colorize( '%9╔══════════════════════════════════════════════════════════════════╗%n' ) );
		\WP_CLI::log( \WP_CLI::colorize( '%9║          PDM Anti-Spam — AI Model Benchmark                     ║%n' ) );
		\WP_CLI::log( \WP_CLI::colorize( '%9╚══════════════════════════════════════════════════════════════════╝%n' ) );
		\WP_CLI::log( sprintf( '  Models:   %d', count( $model_ids ) ) );
		\WP_CLI::log( sprintf( '  Runs:     %d per prompt type per model', $runs ) );
		\WP_CLI::log( sprintf( '  Timeout:  %ds', $timeout ) );
		\WP_CLI::log( sprintf( '  Max lat:  %dms (skip model if exceeded)', $max_latency ) );
		\WP_CLI::log( sprintf( '  Prompts:  %s', implode( ', ', $prompt_types ) ) );
		\WP_CLI::log( sprintf( '  Reasoning: %s', implode( ', ', $reasoning_efforts ) ) );
		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( '  %yPress Ctrl+C at any time to stop and print partial results.%n' ) );
		\WP_CLI::log( '' );

		// ── Run benchmark ──────────────────────────────────────────────────
		$runner = new Benchmark_Runner(
			$runs,
			$timeout,
			$prompt_types,
			$reasoning_efforts,
			$api_key,
			$verbose,
			$max_latency
		);

		$runner->run( $model_ids, $model_meta );

		// ── Print summary ──────────────────────────────────────────────────
		$runner->print_summary();

		// ── Save JSON ──────────────────────────────────────────────────────
		if ( ! empty( $save ) ) {
			$runner->save_json( $save );
			\WP_CLI::success( 'Results saved to: ' . $save );
		}
	}
}
