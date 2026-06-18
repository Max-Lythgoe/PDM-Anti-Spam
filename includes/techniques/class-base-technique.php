<?php
/**
 * Base technique abstract class.
 *
 * Provides a default is_enabled() implementation that checks per-form
 * overrides then falls back to the global setting. Techniques that need
 * additional checks (e.g. AI requiring an API key) can override.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Techniques;

use PDM_Antispam\Contracts\Checkable_Context;
use PDM_Antispam\Settings;

/**
 * Abstract base for spam detection techniques.
 *
 * Implements the common is_enabled() pattern so concrete techniques
 * only need to provide get_name() and evaluate().
 */
abstract class Base_Technique implements Technique {

	/**
	 * Returns the technique's unique identifier.
	 *
	 * Used as the key in settings (e.g. 'pow' → 'pow_enabled')
	 * and in result arrays.
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Evaluates a submission against this technique.
	 *
	 * The form array is available via $context->get_form().
	 *
	 * @param Checkable_Context $context The submission metadata.
	 *
	 * @return Technique_Result
	 */
	abstract public function evaluate( Checkable_Context $context ): Technique_Result;

	/**
	 * Checks if this technique is enabled for a given form.
	 *
	 * Default implementation: checks per-form override first, then
	 * falls back to the global setting. Override in subclasses that
	 * need additional checks (e.g. AI requiring an API key).
	 *
	 * @param array $form The GF form array.
	 *
	 * @return bool
	 */
	public function is_enabled( array $form ): bool {
		return Settings::is_technique_enabled_for_form( $this->get_name(), $form );
	}

	/**
	 * Default headline: "flagged" or "ok" based on is_spam.
	 * Override in subclasses for technique-specific formatting.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return string
	 */
	public function format_headline( array $result_data ): string {
		if ( ! empty( $result_data['metadata']['skipped'] ) ) {
			$signals = $result_data['signals'] ?? [];
			return str_replace( 'skipped_', '', $signals[0] ?? 'skipped' );
		}

		return ( $result_data['is_spam'] ?? false ) ? 'flagged' : 'ok';
	}

	/**
	 * Default details: empty array.
	 * Override in subclasses for technique-specific detail grids.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string>
	 */
	public function format_details( array $result_data ): array {
		return [];
	}

	/**
	 * Default dashboard meta: empty array.
	 * Override in subclasses to denormalize technique-specific meta.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string>
	 */
	public function get_dashboard_meta( array $result_data ): array {
		return [];
	}
}
