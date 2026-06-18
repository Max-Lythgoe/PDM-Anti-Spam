<?php
/**
 * Common technique interface.
 *
 * All spam detection techniques must implement this interface.
 * The Spam_Checker orchestrator calls evaluate() on each registered
 * technique and aggregates the results.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Techniques;

use PDM_Antispam\Contracts\Checkable_Context;

/**
 * Interface that all spam detection techniques must implement.
 */
interface Technique {

	/**
	 * Evaluates a submission for spam signals.
	 *
	 * Returns a Technique_Result containing:
	 * - A boolean spam verdict (is_spam)
	 * - An array of signal codes describing what was detected
	 * - Optional metadata (solve times, AI probability, etc.)
	 *
	 * The form array is available via $context->get_form().
	 *
	 * @param Checkable_Context $context The submission metadata.
	 *
	 * @return Technique_Result
	 */
	public function evaluate( Checkable_Context $context ): Technique_Result;

	/**
	 * Returns the technique's unique identifier.
	 *
	 * Used as the key in scoring arrays and settings.
	 * Examples: 'pow', 'ai'.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Checks if this technique is enabled for a given form.
	 *
	 * Considers both global settings and per-form overrides.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return bool
	 */
	public function is_enabled( array $form ): bool;

	/**
	 * Formats a one-line headline from a technique result array.
	 *
	 * Generated on demand from the raw metadata — not stored.
	 * The result_data comes from Technique_Result::to_array().
	 *
	 * Used by: debug logging, dev tools, entry list tooltips.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return string
	 */
	public function format_headline( array $result_data ): string;

	/**
	 * Formats detail key-value pairs for expanded display.
	 *
	 * Used by the entry meta box detail grid and dev tools panel.
	 * Returns label => formatted value pairs.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string> Label => formatted value.
	 */
	public function format_details( array $result_data ): array;

	/**
	 * Returns key-value pairs to denormalize into entry/comment meta.
	 *
	 * Used for efficient dashboard aggregate queries (COUNT/AVG/GROUP BY)
	 * without JSON parsing. Only called for non-skipped results.
	 *
	 * @param array<string, mixed> $result_data The technique result (from to_array()).
	 *
	 * @return array<string, string> Meta key => meta value pairs.
	 */
	public function get_dashboard_meta( array $result_data ): array;
}
