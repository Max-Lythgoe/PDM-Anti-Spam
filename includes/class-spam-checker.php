<?php
/**
 * Scoring orchestrator.
 *
 * Runs all enabled techniques against a submission, collects their results,
 * and produces a single Spam_Result with the final verdict and action.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam;

use PDM_Antispam\Techniques\Technique;
use PDM_Antispam\Techniques\Technique_Result;

/**
 * Orchestrates spam checking across all enabled techniques.
 *
 * Collects verdicts from each technique and resolves the final action
 * based on per-technique action settings (spam / reject / fail).
 */
class Spam_Checker {

	/**
	 * Registered techniques.
	 *
	 * @var Technique[]
	 */
	private array $techniques = [];

	/**
	 * Registers a technique for evaluation.
	 *
	 * @param Technique $technique The technique to register.
	 *
	 * @return self For chaining.
	 */
	public function register( Technique $technique ): self {
		$this->techniques[] = $technique;

		return $this;
	}

	/**
	 * Evaluates techniques against a context with configurable enable check and result callback.
	 *
	 * Shared evaluation loop used by both Spam_Checker::evaluate() and Comment_Checker::check().
	 * Handles disabled skipping, short-circuit on spam, exception catching, and timing.
	 *
	 * @param \PDM_Antispam\Contracts\Checkable_Context $context          The submission context.
	 * @param callable                                    $is_enabled_check fn(Technique): bool — returns true if technique should run.
	 * @param callable|null                               $on_result        Optional fn(string $name, Technique_Result, float $elapsed_ms): void.
	 *
	 * @return array<string, Technique_Result>
	 */
	public function evaluate_techniques(
		\PDM_Antispam\Contracts\Checkable_Context $context,
		callable $is_enabled_check,
		?callable $on_result = null
	): array {
		$results = [];

		foreach ( $this->techniques as $technique ) {
			$name            = $technique->get_name();
			$technique_start = microtime( true );

			if ( ! $is_enabled_check( $technique ) ) {
				$results[ $name ] = Technique_Result::skipped( 'disabled' );
				continue;
			}

			if ( Technique_Result::any_spam( $results ) ) {
				$results[ $name ] = Technique_Result::skipped( 'short_circuited' );
				continue;
			}

			try {
				$results[ $name ] = $technique->evaluate( $context );
				$elapsed_ms       = round( ( microtime( true ) - $technique_start ) * 1000 );

				if ( $on_result ) {
					$on_result( $name, $results[ $name ], $elapsed_ms );
				}
			} catch ( \Throwable $e ) {
				$results[ $name ] = Technique_Result::skipped( 'error', [
					'error_message' => sprintf(
						'Technique "%s" threw %s: %s',
						$name,
						get_class( $e ),
						$e->getMessage()
					),
				] );
			}
		}

		return $results;
	}

	/**
	 * Evaluates a submission against all enabled techniques.
	 *
	 * @param Submission_Context $context The submission metadata.
	 *
	 * @return Spam_Result The aggregate result with verdict, action, and breakdowns.
	 */
	public function evaluate( Submission_Context $context ): Spam_Result {
		$form    = $context->get_form();
		$form_id = $context->get_form_id();
		$start   = microtime( true );

		$results = $this->evaluate_techniques(
			$context,
			fn( Technique $t ) => $t->is_enabled( $form ),
			function ( string $name, Technique_Result $result, float $elapsed_ms ) use ( $form_id ) {
				$technique = $this->get_technique( $name );
				$headline  = $technique ? $technique->format_headline( $result->to_array() ) : '';

				PDM_Antispam()->log_debug(
					sprintf(
						'%s(): Form #%d — %s: spam=%s, %s, signals=[%s], %dms.',
						__METHOD__,
						$form_id,
						$name,
						$result->is_spam() ? 'yes' : 'no',
						$headline,
						implode( ', ', $result->get_signals() ),
						$elapsed_ms
					)
				);
			}
		);

		/**
		 * Filters the per-technique results before final action resolution.
		 *
		 * @param array<string, Technique_Result> $results Per-technique results.
		 * @param Submission_Context               $context The submission context.
		 * @param array                            $form    The GF form array.
		 */
		$results = apply_filters( 'gfsh_technique_results', $results, $context, $form );

		// Build per-technique action settings from form-level settings.
		$action_settings = [
			'pow' => Settings::get_for_form( 'pow_action', $form, 'spam' ),
			'ai'  => Settings::get_for_form( 'ai_action', $form, 'spam' ),
		];

		/**
		 * Filters the per-technique action settings before final resolution.
		 *
		 * @param array<string, string> $action_settings Per-technique action settings.
		 * @param array                 $form            The GF form array.
		 */
		$action_settings = apply_filters( 'gfsh_action_settings', $action_settings, $form );

		$spam_result = Spam_Result::from_technique_results( $results, $action_settings );

		$total_ms = round( ( microtime( true ) - $start ) * 1000 );

		PDM_Antispam()->log_debug(
			sprintf(
				'%s(): Form #%d — Verdict: spam=%s, action=%s, %dms total.',
				__METHOD__,
				$form_id,
				$spam_result->is_spam() ? 'yes' : 'no',
				$spam_result->get_action(),
				$total_ms
			)
		);

		return $spam_result;
	}


	/**
	 * Gets the registered techniques.
	 *
	 * @return Technique[]
	 */
	public function get_techniques(): array {
		return $this->techniques;
	}

	/**
	 * Gets a registered technique by name.
	 *
	 * @param string $name The technique name (e.g. 'pow', 'ai').
	 *
	 * @return Technique|null Null if not found.
	 */
	public function get_technique( string $name ): ?Technique {
		foreach ( $this->techniques as $technique ) {
			if ( $technique->get_name() === $name ) {
				return $technique;
			}
		}
		return null;
	}
}
