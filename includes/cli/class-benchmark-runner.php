<?php
/**
 * Benchmark runner.
 *
 * Executes classify() calls against OpenRouter_Provider for each
 * model × reasoning_effort × prompt_type × run combination.
 * Prints each result immediately to STDOUT as it completes.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\CLI;

use PDM_Antispam\AI\AI_Exception;
use PDM_Antispam\AI\OpenRouter_Provider;

/**
 * Runs the benchmark and collects results.
 */
class Benchmark_Runner {

	/**
	 * Number of runs per prompt type per model × effort combination.
	 *
	 * @var int
	 */
	private int $runs;

	/**
	 * Per-request timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout;

	/**
	 * Prompt types to test.
	 *
	 * @var string[]
	 */
	private array $prompt_types;

	/**
	 * Reasoning effort levels to test per model.
	 *
	 * @var string[]
	 */
	private array $reasoning_efforts;

	/**
	 * OpenRouter API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * All collected results.
	 *
	 * @var Benchmark_Result[]
	 */
	private array $results = [];

	/**
	 * Running total cost in USD.
	 *
	 * @var float
	 */
	private float $total_cost = 0.0;

	/**
	 * Whether the runner has been interrupted (Ctrl+C).
	 *
	 * @var bool
	 */
	private bool $interrupted = false;

	/**
	 * Whether to show per-run detail (verbose mode).
	 *
	 * @var bool
	 */
	private bool $verbose = false;

	/**
	 * Maximum acceptable latency in ms. Models exceeding this on their
	 * first successful response are skipped for remaining prompts.
	 * 0 = no limit.
	 *
	 * @var int
	 */
	private int $max_latency = 0;

	/**
	 * Whether to skip remaining prompts for a model after all runs fail.
	 *
	 * @var bool
	 */
	private bool $skip_on_fail = true;

	/**
	 * Prompt fixtures — mirrors the JS benchmark prompts exactly.
	 *
	 * Keys match the prompt type names used throughout the benchmark.
	 *
	 * @var array<string, string>
	 */
	private const PROMPTS = [
		'ham'        => 'You are a form spam classifier. Analyze the submission below and respond with JSON only.

Site: Acme Widgets - Premium industrial widgets since 1985
Domain: acmewidgets.com
Context: We sell industrial widgets to B2B customers. Legitimate inquiries ask about pricing, bulk orders, or custom specifications.

Form: Contact Us
Fields: Name(text*), Email(email*), Phone(phone), Company(text), Message(textarea*)

Submission:
  Name: Sarah Chen
  Email: sarah.chen@techcorp.com
  Company: TechCorp Industries
  Message: Hi, we\'re looking to order about 500 units of your W-200 series widgets for our manufacturing line. Could you send us a quote and lead time estimate? We\'d need delivery to our Portland facility. Thanks!

Respond: {"spam_probability": 0.0-1.0, "reason": "one_word_code"}

Reason codes: ham, generic_sales, seo_spam, phishing, url_stuffing, gibberish, template_text, off_topic',

		'spam'       => 'You are a form spam classifier. Analyze the submission below and respond with JSON only.

Site: Acme Widgets - Premium industrial widgets since 1985
Domain: acmewidgets.com
Context: We sell industrial widgets to B2B customers. Legitimate inquiries ask about pricing, bulk orders, or custom specifications.

Form: Contact Us
Fields: Name(text*), Email(email*), Phone(phone), Company(text), Message(textarea*)

Submission:
  Name: Marketing Pro
  Email: deals@best-seo-rankings.xyz
  Company: Best SEO Rankings
  Message: Hello, I noticed your website acmewidgets.com is not ranking on the first page of Google. We can guarantee top 3 rankings for your target keywords within 30 days or your money back! Visit https://best-seo-rankings.xyz/special-offer for 50% off our premium SEO package. We\'ve helped 10,000+ businesses just like yours. Reply now for a free audit! Check out our results: https://best-seo-rankings.xyz/case-studies https://best-seo-rankings.xyz/testimonials

Respond: {"spam_probability": 0.0-1.0, "reason": "one_word_code"}

Reason codes: ham, generic_sales, seo_spam, phishing, url_stuffing, gibberish, template_text, off_topic',

		'partial'    => 'You are a form spam classifier. Analyze the submission below and respond with JSON only.

Site: Acme Widgets - Premium industrial widgets since 1985
Domain: acmewidgets.com

Form: Contact Us
Fields: Name(text*), Email(email*), Phone(phone), Company(text), Message(textarea*)

Submission:
  Name: John
  Email: john@gmail.com

Respond: {"spam_probability": 0.0-1.0, "reason": "one_word_code"}

Reason codes: ham, generic_sales, seo_spam, phishing, url_stuffing, gibberish, template_text, off_topic',

		'large-ham'  => 'You are a form spam classifier. Analyze the submission below and respond with JSON only.

Site: Greenfield Community Hospital - Compassionate care for every patient
Domain: greenfieldcommunityhospital.org
Context: We are a regional hospital. This form is used by patients to request appointments, ask billing questions, or submit feedback about their care experience. Legitimate submissions reference specific departments, doctors, or medical concerns.

Form: Patient Feedback & Appointment Request
Fields: First Name(text*), Last Name(text*), Email(email*), Phone(phone*), Date of Birth(date), Patient ID(text), Department(select*), Preferred Doctor(text), Preferred Date(date), Preferred Time(select), Insurance Provider(text), Policy Number(text), Reason for Visit(textarea*), Current Medications(textarea), Allergies(textarea), How did you hear about us?(select), Additional Comments(textarea), Consent to Contact(checkbox*)

Submission:
  First Name: Margaret
  Last Name: Thornton
  Email: margaret.thornton@outlook.com
  Phone: (503) 555-0147
  Date of Birth: 1958-04-12
  Patient ID: GCH-2024-88431
  Department: Orthopedics
  Preferred Doctor: Dr. Ramirez
  Preferred Date: 2026-04-18
  Preferred Time: Morning (9am-12pm)
  Insurance Provider: Blue Cross Blue Shield of Oregon
  Policy Number: BCBS-OR-7742901
  Reason for Visit: I\'ve been experiencing persistent pain in my left knee for the past three weeks, especially when climbing stairs or getting up from a seated position. My primary care physician, Dr. Langley, suggested I see an orthopedic specialist and recommended Dr. Ramirez at your hospital. I had an X-ray done last week and the results were sent to your imaging department. I\'d like to schedule a consultation to discuss treatment options, possibly including physical therapy or an MRI if needed.
  Current Medications: Lisinopril 10mg daily, Metformin 500mg twice daily, Vitamin D3 2000 IU daily
  Allergies: Penicillin (causes hives), Sulfa drugs
  How did you hear about us?: Referred by my doctor
  Additional Comments: I have limited mobility right now so a ground-floor exam room would be greatly appreciated. Also, I may need to bring my husband who uses a wheelchair — is there accessible parking near the orthopedics entrance? Thank you for your help.
  Consent to Contact: Yes

Respond: {"spam_probability": 0.0-1.0, "reason": "one_word_code"}

Reason codes: ham, generic_sales, seo_spam, phishing, url_stuffing, gibberish, template_text, off_topic',

		'large-spam' => 'You are a form spam classifier. Analyze the submission below and respond with JSON only.

Site: Greenfield Community Hospital - Compassionate care for every patient
Domain: greenfieldcommunityhospital.org
Context: We are a regional hospital. This form is used by patients to request appointments, ask billing questions, or submit feedback about their care experience. Legitimate submissions reference specific departments, doctors, or medical concerns.

Form: Patient Feedback & Appointment Request
Fields: First Name(text*), Last Name(text*), Email(email*), Phone(phone*), Date of Birth(date), Patient ID(text), Department(select*), Preferred Doctor(text), Preferred Date(date), Preferred Time(select), Insurance Provider(text), Policy Number(text), Reason for Visit(textarea*), Current Medications(textarea), Allergies(textarea), How did you hear about us?(select), Additional Comments(textarea), Consent to Contact(checkbox*)

Submission:
  First Name: Dr. Michael
  Last Name: Health Expert
  Email: admin@discount-pharmacy-online.xyz
  Phone: 1234567890
  Date of Birth: 2000-01-01
  Patient ID: N/A
  Department: General
  Preferred Doctor: Any
  Preferred Date: 2026-01-01
  Preferred Time: Morning (9am-12pm)
  Insurance Provider: N/A
  Policy Number: N/A
  Reason for Visit: Are you tired of paying too much for your prescriptions? Our online pharmacy offers up to 80% off on all medications — no prescription needed! We carry a full range of FDA-approved generics including Lisinopril, Metformin, Atorvastatin, and more. Visit https://discount-pharmacy-online.xyz/order-now for free shipping on your first order. Use code HEALTH2026 for an extra 15% off! We also offer telemedicine consultations for just $19.99. Don\'t wait — your health shouldn\'t cost a fortune! https://discount-pharmacy-online.xyz/testimonials https://discount-pharmacy-online.xyz/faq
  Current Medications: Visit our site for the best prices on all medications https://discount-pharmacy-online.xyz
  Allergies: None
  How did you hear about us?: Other
  Additional Comments: Forward this to your friends and family! Everyone deserves affordable healthcare. Sign up for our newsletter at https://discount-pharmacy-online.xyz/newsletter and get a free health guide. We ship worldwide with discreet packaging. Bulk orders available for clinics and hospitals — contact wholesale@discount-pharmacy-online.xyz for special pricing.
  Consent to Contact: Yes

Respond: {"spam_probability": 0.0-1.0, "reason": "one_word_code"}

Reason codes: ham, generic_sales, seo_spam, phishing, url_stuffing, gibberish, template_text, off_topic',
	];

	/**
	 * Expected outcomes per prompt type for accuracy scoring.
	 *
	 * 'min_prob' / 'max_prob' define the acceptable probability range.
	 * 'reason' is the expected reason code, or null to accept any.
	 *
	 * @var array<string, array{min_prob?: float, max_prob?: float, reason?: string|null}>
	 */
	private const EXPECTED = [
		'ham'        => [ 'max_prob' => 0.3, 'reason' => 'ham' ],
		'spam'       => [ 'min_prob' => 0.7, 'reason' => null ],
		'partial'    => [ 'max_prob' => 0.6, 'reason' => null ],  // ambiguous — just no crash
		'large-ham'  => [ 'max_prob' => 0.3, 'reason' => 'ham' ],
		'large-spam' => [ 'min_prob' => 0.7, 'reason' => null ],
	];

	/**
	 * @param int      $runs              Runs per prompt type per model × effort.
	 * @param int      $timeout           Per-request timeout in seconds.
	 * @param string[] $prompt_types      Prompt types to test.
	 * @param string[] $reasoning_efforts Reasoning effort levels to test.
	 * @param string   $api_key           OpenRouter API key.
	 * @param bool     $verbose           Whether to output verbose progress.
	 * @param int      $max_latency       Maximum allowed latency in ms (0 = no limit).
	 */
	public function __construct(
		int $runs,
		int $timeout,
		array $prompt_types,
		array $reasoning_efforts,
		string $api_key,
		bool $verbose = false,
		int $max_latency = 0
	) {
		$this->runs              = $runs;
		$this->timeout           = $timeout;
		$this->prompt_types      = $prompt_types;
		$this->reasoning_efforts = $reasoning_efforts;
		$this->api_key           = $api_key;
		$this->verbose           = $verbose;
		$this->max_latency       = $max_latency;
	}

	/**
	 * Marks the runner as interrupted (called from SIGINT handler).
	 *
	 * @return void
	 */
	public function interrupt(): void {
		$this->interrupted = true;
	}

	/**
	 * Runs the benchmark for all models.
	 *
	 * @param string[]                    $model_ids    Model IDs to benchmark.
	 * @param array<string, array<string, mixed>> $model_meta  Optional model metadata keyed by ID (from discovery).
	 *
	 * @return void
	 */
	public function run( array $model_ids, array $model_meta = [] ): void {
		foreach ( $model_ids as $model_id ) {
			if ( $this->interrupted ) {
				break;
			}

			$meta               = $model_meta[ $model_id ] ?? [];
			$supports_reasoning = ! empty( $meta )
				? Model_Discovery::supports_reasoning( $meta )
				: true; // Unknown — try all effort levels.

			$this->run_model( $model_id, $supports_reasoning );
		}
	}

	/**
	 * Runs all effort × prompt combinations for a single model.
	 *
	 * @param string $model_id           Model ID.
	 * @param bool   $supports_reasoning Whether the model supports reasoning param.
	 *
	 * @return void
	 */
	private function run_model( string $model_id, bool $supports_reasoning ): void {
		foreach ( $this->reasoning_efforts as $effort ) {
			if ( $this->interrupted ) {
				break;
			}

			// Skip non-'none' effort levels for models that don't support reasoning.
			if ( ! $supports_reasoning && $effort !== 'none' ) {
				\WP_CLI::log( \WP_CLI::colorize(
					'  ' . $model_id . '  [effort: ' . $effort . '] — %yskipped (no reasoning support)%n'
				) );
				continue;
			}

			$effort_label = $effort === 'none' ? 'none' : $effort;
			\WP_CLI::log( '' );
			$cost_note = $this->total_cost > 0
				? '  ' . \WP_CLI::colorize( '%Y$' . number_format( $this->total_cost, 4 ) . '%n' )
				: '';
			\WP_CLI::log( \WP_CLI::colorize(
				$model_id . '  %C[' . $effort_label . ']%n' . $cost_note
			) );

			$consecutive_failures = 0;
			$model_too_slow       = false;

			foreach ( $this->prompt_types as $prompt_type ) {
				if ( $this->interrupted ) {
					break 2;
				}

				if ( ! isset( self::PROMPTS[ $prompt_type ] ) ) {
					\WP_CLI::warning( "Unknown prompt type '$prompt_type' — skipping." );
					continue;
				}

				// Skip remaining prompts if model has failed too many times.
				if ( $this->skip_on_fail && $consecutive_failures >= 2 ) {
					\WP_CLI::log( '  ' . str_pad( $prompt_type, 11 ) . \WP_CLI::colorize( '%yskipped%n' ) );
					continue;
				}

				// Skip if model already exceeded max latency.
				if ( $model_too_slow ) {
					\WP_CLI::log( '  ' . str_pad( $prompt_type, 11 ) . \WP_CLI::colorize( '%yskipped (too slow)%n' ) );
					continue;
				}

				$before_count = count( $this->results );
				$this->run_prompt( $model_id, $effort, $prompt_type );
				$after_count = count( $this->results );

				// Check results from this prompt run.
				$prompt_results = array_slice( $this->results, $before_count, $after_count - $before_count );
				$successful     = array_filter( $prompt_results, fn( $r ) => ! $r->is_error() );

				if ( empty( $successful ) ) {
					++$consecutive_failures;
				} else {
					$consecutive_failures = 0;

					// Check if first successful response exceeds max latency.
					if ( $this->max_latency > 0 ) {
						$first_latency = $successful[0]->latency_ms ?? 0;
						if ( $first_latency > $this->max_latency ) {
							$model_too_slow = true;
							\WP_CLI::log( \WP_CLI::colorize(
								'  %y⚡ ' . $first_latency . 'ms > ' . $this->max_latency . 'ms limit — skipping remaining prompts%n'
							) );
						}
					}
				}
			}
		}
	}

	/**
	 * Runs all repetitions for a single model × effort × prompt combination.
	 *
	 * @param string $model_id    Model ID.
	 * @param string $effort      Reasoning effort level.
	 * @param string $prompt_type Prompt type.
	 *
	 * @return void
	 */
	private function run_prompt( string $model_id, string $effort, string $prompt_type ): void {
		$prompt = self::PROMPTS[ $prompt_type ];

		if ( $this->verbose ) {
			\WP_CLI::log( \WP_CLI::colorize(
				'  %K──%n ' . strtoupper( $prompt_type ) . ' (' . strlen( $prompt ) . ' chars) %K──%n'
			) );
		}

		$provider = new OpenRouter_Provider(
			$this->api_key,
			$model_id,
			false,
			150,
			$effort === 'none' ? null : $effort
		);

		$run_results = [];

		for ( $i = 1; $i <= $this->runs; $i++ ) {
			if ( $this->interrupted ) {
				break;
			}

			$result          = $this->run_single( $provider, $model_id, $effort, $prompt_type, $prompt, $i );
			$run_results[]   = $result;
			$this->results[] = $result;

			// Update running cost.
			if ( $result->cost_usd !== null ) {
				$this->total_cost += $result->cost_usd;
			}

			if ( $this->verbose ) {
				// Print per-run detail.
				$this->print_result_row( $result, $i );
			}

			// Small delay between runs to avoid rate limiting.
			if ( $i < $this->runs ) {
				usleep( 200_000 ); // 200ms
			}
		}

		// Print summary line for this prompt type.
		$this->print_prompt_summary( $run_results, $prompt_type );
	}

	/**
	 * Executes a single classify() call and returns a Benchmark_Result.
	 *
	 * @param OpenRouter_Provider $provider    The provider instance.
	 * @param string              $model_id    Model ID.
	 * @param string              $effort      Reasoning effort level.
	 * @param string              $prompt_type Prompt type.
	 * @param string              $prompt      The prompt text.
	 * @param int                 $run_num     Run number (1-based).
	 *
	 * @return Benchmark_Result
	 */
	private function run_single(
		OpenRouter_Provider $provider,
		string $model_id,
		string $effort,
		string $prompt_type,
		string $prompt,
		int $run_num
	): Benchmark_Result {
		try {
			$response = $provider->classify( $prompt, '', $this->timeout );
			$usage    = $response->get_usage();

			$correct = $this->is_correct( $prompt_type, $response->get_spam_probability(), $response->get_reason() );

			return new Benchmark_Result(
				$model_id,
				$effort,
				$prompt_type,
				$run_num,
				$response->get_latency_ms(),
				$usage['prompt_tokens'] ?? 0,
				$usage['completion_tokens'] ?? 0,
				$usage['total_tokens'] ?? 0,
				$response->get_cost(),
				$response->get_spam_probability(),
				$response->get_reason(),
				$correct
			);
		} catch ( AI_Exception $e ) {
			return Benchmark_Result::failed( $model_id, $effort, $prompt_type, $run_num, $e->getMessage() );
		} catch ( \Throwable $e ) {
			return Benchmark_Result::failed( $model_id, $effort, $prompt_type, $run_num, $e->getMessage() );
		}
	}

	/**
	 * Determines whether a classification result is correct for a prompt type.
	 *
	 * @param string $prompt_type      Prompt type.
	 * @param float  $spam_probability Spam probability from the model.
	 * @param string $reason           Reason code from the model.
	 *
	 * @return bool
	 */
	private function is_correct( string $prompt_type, float $spam_probability, string $reason ): bool {
		$expected = self::EXPECTED[ $prompt_type ] ?? null;
		if ( $expected === null ) {
			return true; // Unknown prompt type — assume correct.
		}

		if ( isset( $expected['min_prob'] ) && $spam_probability < $expected['min_prob'] ) {
			return false;
		}

		if ( isset( $expected['max_prob'] ) && $spam_probability > $expected['max_prob'] ) {
			return false;
		}

		if ( ! empty( $expected['reason'] ) && $reason !== $expected['reason'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Prints a single result row immediately to STDOUT.
	 *
	 * @param Benchmark_Result $result  The result to display.
	 * @param int              $run_num Run number.
	 *
	 * @return void
	 */
	private function print_result_row( Benchmark_Result $result, int $run_num ): void {
		$prefix = '    [' . $run_num . '/' . $this->runs . '] ';

		if ( $result->is_error() ) {
			\WP_CLI::log( $prefix . \WP_CLI::colorize( '%R❌ ' . $result->error . '%n' ) );
			return;
		}

		$latency  = str_pad( $result->latency_ms . 'ms', 7, ' ', STR_PAD_LEFT );
		$tokens   = $result->prompt_tokens . '/' . $result->completion_tokens . '/' . $result->total_tokens;
		$cost     = $result->cost_usd !== null
			? '$' . number_format( $result->cost_usd, 6 )
			: 'n/a';
		$status   = $result->correct
			? \WP_CLI::colorize( '%G✅%n' )
			: \WP_CLI::colorize( '%R❌%n' );
		$prob_str = number_format( $result->spam_probability, 2 );

		\WP_CLI::log(
			$prefix
			. \WP_CLI::colorize( '%C' . $latency . '%n' )
			. '  ' . str_pad( $tokens, 14 )
			. '  ' . \WP_CLI::colorize( '%Y' . str_pad( $cost, 12 ) . '%n' )
			. '  ' . $status
			. '  prob=' . $prob_str . ' ' . $result->reason
		);
	}

	/**
	 * Prints a per-prompt summary line after all runs for a prompt type.
	 *
	 * In compact mode (default): prints a single line per prompt type.
	 * In verbose mode: prints the detailed multi-line summary.
	 *
	 * @param Benchmark_Result[] $results     Results for this prompt type.
	 * @param string             $prompt_type The prompt type name.
	 *
	 * @return void
	 */
	private function print_prompt_summary( array $results, string $prompt_type = '' ): void {
		$successful = array_filter( $results, fn( $r ) => ! $r->is_error() );
		$total      = count( $results );
		$ok_count   = count( $successful );

		if ( $ok_count === 0 ) {
			if ( $this->verbose ) {
				\WP_CLI::log( \WP_CLI::colorize( '         %Rall runs failed%n' ) );
			} else {
				\WP_CLI::log( '  ' . str_pad( $prompt_type, 11 ) . \WP_CLI::colorize( '%RFAILED%n' ) );
			}
			$this->print_running_cost();
			return;
		}

		$latencies = array_map( fn( $r ) => $r->latency_ms, $successful );
		sort( $latencies );

		$avg_latency = (int) round( array_sum( $latencies ) / count( $latencies ) );
		$p50_latency = $this->percentile( $latencies, 50 );
		$min_latency = $latencies[0];
		$max_latency = $latencies[ count( $latencies ) - 1 ];

		$avg_prompt     = (int) round( array_sum( array_map( fn( $r ) => $r->prompt_tokens, $successful ) ) / $ok_count );
		$avg_completion = (int) round( array_sum( array_map( fn( $r ) => $r->completion_tokens, $successful ) ) / $ok_count );
		$avg_total      = (int) round( array_sum( array_map( fn( $r ) => $r->total_tokens, $successful ) ) / $ok_count );

		$costs    = array_filter( array_map( fn( $r ) => $r->cost_usd, $successful ), fn( $c ) => $c !== null );
		$avg_cost = count( $costs ) > 0 ? array_sum( $costs ) / count( $costs ) : null;
		$cost_str = $avg_cost !== null ? '$' . number_format( $avg_cost, 6 ) : 'n/a';

		$correct_count = count( array_filter( $successful, fn( $r ) => $r->correct ) );
		$acc_icon      = $correct_count === $ok_count
			? \WP_CLI::colorize( '%G✅%n' )
			: \WP_CLI::colorize( '%R❌%n' );

		if ( $this->verbose ) {
			\WP_CLI::log(
				'         avg=' . \WP_CLI::colorize( '%C' . $avg_latency . 'ms%n' )
				. '  p50=' . $p50_latency . 'ms'
				. '  min=' . $min_latency . 'ms'
				. '  max=' . $max_latency . 'ms'
				. '  tokens=' . $avg_prompt . '/' . $avg_completion . '/' . $avg_total
				. '  cost/call=' . \WP_CLI::colorize( '%Y' . $cost_str . '%n' )
				. '  ok=' . $ok_count . '/' . $total
				. '  acc=' . $correct_count . '/' . $ok_count
			);
		} else {
			// Compact one-liner per prompt type.
			\WP_CLI::log(
				'  ' . str_pad( $prompt_type, 11 )
				. \WP_CLI::colorize( '%C' . str_pad( $avg_latency . 'ms', 8, ' ', STR_PAD_LEFT ) . '%n' )
				. '  ' . str_pad( $avg_prompt . '/' . $avg_completion . '/' . $avg_total, 14 )
				. '  ' . \WP_CLI::colorize( '%Y' . str_pad( $cost_str, 11 ) . '%n' )
				. '  ' . $acc_icon . ' ' . $correct_count . '/' . $ok_count
			);
		}

		$this->print_running_cost();
	}

	/**
	 * Prints the running cost line in-place using carriage return.
	 *
	 * @return void
	 */
	private function print_running_cost(): void {
		if ( $this->verbose ) {
			// In verbose mode, print on its own line to avoid collision.
			$line = '  ' . \WP_CLI::colorize( '%YRunning cost: $' . number_format( $this->total_cost, 4 ) . '%n' ) . '  ';
			fwrite( STDOUT, "\r" . $line );
		}
		// In compact mode, don't print running cost per-line — it's shown in the model header.
	}

	/**
	 * Prints the final summary table for all collected results.
	 *
	 * Called after all models complete, or on Ctrl+C interruption.
	 *
	 * @return void
	 */
	public function print_summary(): void {
		// Clear the running cost line.
		fwrite( STDOUT, "\r" . str_repeat( ' ', 60 ) . "\r" );

		if ( empty( $this->results ) ) {
			\WP_CLI::warning( 'No results to summarize.' );
			return;
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( str_repeat( '═', 80 ) );
		\WP_CLI::log( '  SUMMARY — sorted by spam avg latency' );
		\WP_CLI::log( str_repeat( '═', 80 ) );

		// Group results by model + effort + prompt_type.
		$groups = [];
		foreach ( $this->results as $result ) {
			$key              = $result->model . '|' . $result->reasoning_effort . '|' . $result->prompt_type;
			$groups[ $key ][] = $result;
		}

		// Build summary rows.
		$rows = [];
		foreach ( $groups as $key => $group_results ) {
			$first      = $group_results[0];
			$successful = array_filter( $group_results, fn( $r ) => ! $r->is_error() );
			$ok_count   = count( $successful );
			$total      = count( $group_results );

			if ( $ok_count === 0 ) {
				$rows[] = [
					'model'     => $first->model,
					'effort'    => $first->reasoning_effort,
					'prompt'    => $first->prompt_type,
					'avg_ms'    => 'FAILED',
					'p50_ms'    => '-',
					'tokens'    => '-',
					'cost_call' => '-',
					'cost_1k'   => '-',
					'accuracy'  => '0/' . $total,
					'_sort_key' => PHP_INT_MAX,
				];
				continue;
			}

			$latencies = array_map( fn( $r ) => $r->latency_ms, $successful );
			sort( $latencies );
			$avg_latency = (int) round( array_sum( $latencies ) / $ok_count );
			$p50_latency = $this->percentile( $latencies, 50 );

			$avg_prompt     = (int) round( array_sum( array_map( fn( $r ) => $r->prompt_tokens, $successful ) ) / $ok_count );
			$avg_completion = (int) round( array_sum( array_map( fn( $r ) => $r->completion_tokens, $successful ) ) / $ok_count );
			$avg_total      = (int) round( array_sum( array_map( fn( $r ) => $r->total_tokens, $successful ) ) / $ok_count );

			$costs    = array_filter( array_map( fn( $r ) => $r->cost_usd, $successful ), fn( $c ) => $c !== null );
			$avg_cost = count( $costs ) > 0 ? array_sum( $costs ) / count( $costs ) : null;

			$correct_count = count( array_filter( $successful, fn( $r ) => $r->correct ) );

			$rows[] = [
				'model'     => $first->model,
				'effort'    => $first->reasoning_effort,
				'prompt'    => $first->prompt_type,
				'avg_ms'    => $avg_latency . 'ms',
				'p50_ms'    => $p50_latency . 'ms',
				'tokens'    => $avg_prompt . '/' . $avg_completion . '/' . $avg_total,
				'cost_call' => $avg_cost !== null ? '$' . number_format( $avg_cost, 6 ) : 'n/a',
				'cost_1k'   => $avg_cost !== null ? '$' . number_format( $avg_cost * 1000, 3 ) : 'n/a',
				'accuracy'  => $correct_count . '/' . $ok_count,
				'_sort_key' => $first->prompt_type === 'spam' ? $avg_latency : PHP_INT_MAX,
			];
		}

		// Sort by spam avg latency.
		usort( $rows, fn( $a, $b ) => $a['_sort_key'] <=> $b['_sort_key'] );

		// Remove internal sort key before display.
		$display_rows = array_map( function ( $row ) {
			unset( $row['_sort_key'] );
			return $row;
		}, $rows );

		$fields = [ 'model', 'effort', 'prompt', 'avg_ms', 'p50_ms', 'tokens', 'cost_call', 'cost_1k', 'accuracy' ];
		\WP_CLI\Utils\format_items( 'table', $display_rows, $fields );

		\WP_CLI::log( '' );
		\WP_CLI::log( '  tokens = prompt/completion/total' );
		\WP_CLI::log( '  cost_1k = projected cost per 1,000 calls' );
		\WP_CLI::log( '  accuracy = correct classifications / successful runs' );
		\WP_CLI::log( '' );
		\WP_CLI::success( 'Total cost this run: $' . number_format( $this->total_cost, 4 ) );
	}

	/**
	 * Returns all collected results for JSON export.
	 *
	 * @return Benchmark_Result[]
	 */
	public function get_results(): array {
		return $this->results;
	}

	/**
	 * Returns the total cost accumulated so far.
	 *
	 * @return float
	 */
	public function get_total_cost(): float {
		return $this->total_cost;
	}

	/**
	 * Saves results to a JSON file.
	 *
	 * @param string $path File path to write to.
	 *
	 * @return void
	 */
	public function save_json( string $path ): void {
		$data = [
			'meta'    => [
				'date'              => gmdate( 'c' ),
				'runs'              => $this->runs,
				'timeout'           => $this->timeout,
				'prompt_types'      => $this->prompt_types,
				'reasoning_efforts' => $this->reasoning_efforts,
				'total_cost_usd'    => $this->total_cost,
			],
			'results' => array_map( function ( Benchmark_Result $r ): array {
				return [
					'model'             => $r->model,
					'reasoning_effort'  => $r->reasoning_effort,
					'prompt_type'       => $r->prompt_type,
					'run_num'           => $r->run_num,
					'latency_ms'        => $r->latency_ms,
					'prompt_tokens'     => $r->prompt_tokens,
					'completion_tokens' => $r->completion_tokens,
					'total_tokens'      => $r->total_tokens,
					'cost_usd'          => $r->cost_usd,
					'spam_probability'  => $r->spam_probability,
					'reason'            => $r->reason,
					'correct'           => $r->correct,
					'error'             => $r->error,
				];
			}, $this->results ),
		];

		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		file_put_contents( $path, wp_json_encode( $data, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Calculates the p-th percentile of a sorted array of values.
	 *
	 * @param int[] $sorted Sorted array of integers.
	 * @param int   $p      Percentile (0–100).
	 *
	 * @return int
	 */
	private function percentile( array $sorted, int $p ): int {
		if ( empty( $sorted ) ) {
			return 0;
		}
		$idx = (int) ceil( ( $p / 100 ) * count( $sorted ) ) - 1;
		return $sorted[ max( 0, $idx ) ];
	}
}
