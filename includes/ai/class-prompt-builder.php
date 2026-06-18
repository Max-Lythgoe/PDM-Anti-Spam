<?php
/**
 * AI prompt builder.
 *
 * Constructs the classification prompt from site context, form structure,
 * and submission data. The prompt is split into a system message (rules,
 * guidance, response format) and a user message (submission data only).
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\AI;

use PDM_Antispam\Comments\Comment_Context;
use PDM_Antispam\Comments\Comment_Settings;
use PDM_Antispam\Contracts\Checkable_Context;
use PDM_Antispam\Settings;

/**
 * Builds classification prompts for the AI provider.
 */
class Prompt_Builder {

	/**
	 * GF field types to skip in prompt construction.
	 *
	 * These fields don't contain user-submitted content relevant
	 * to spam classification.
	 *
	 * @var string[]
	 */
	private const SKIP_FIELD_TYPES = [ 'hidden', 'section', 'page', 'html', 'captcha' ];

	/**
	 * Reason codes for form submissions.
	 *
	 * Filterable via the `gfsh_ai_reason_codes` hook.
	 *
	 * @var string[]
	 */
	private const FORM_REASON_CODES = [
		'ham',
		'generic_sales',
		'seo_spam',
		'phishing',
		'url_stuffing',
		'gibberish',
		'template_text',
		'off_topic',
	];

	/**
	 * Reason codes for comment submissions.
	 *
	 * Adds engagement_bait for AI-generated sycophantic comments that exist
	 * primarily to place a backlink URL.
	 *
	 * Filterable via the `gfsh_ai_reason_codes` hook.
	 *
	 * @var string[]
	 */
	private const COMMENT_REASON_CODES = [
		'ham',
		'engagement_bait',
		'seo_spam',
		'phishing',
		'url_stuffing',
		'gibberish',
		'template_text',
		'off_topic',
	];

	/**
	 * Comment-specific detection guidance appended to the system instruction.
	 *
	 * Teaches the AI to recognize LLM-generated backlink spam that is
	 * topically adjacent but vacuous — the "engagement bait" pattern.
	 */
	private const COMMENT_GUIDANCE = <<<'GUIDANCE'
Comment detection guidance:
- Engagement bait: generic praise + vague open-ended question + no specific insight = spam
- URL paths with keyword-stuffed slugs (e.g., /understanding-types-of…) suggest SEO backlink placement
- Very short or abbreviated author names combined with generic text suggest automation
- A strange website URL alone is NOT spam — judge by whether the comment adds real substance
- Key question: does this comment exist to contribute, or to place a URL?
GUIDANCE;

	/**
	 * Testing detection guidance included in the system instruction.
	 *
	 * Instructs the AI to flag developer test submissions that contain
	 * a test_REASONCODE sentinel pattern in any field value.
	 */
	private const TESTING_GUIDANCE = <<<'GUIDANCE'
Testing detection:
- If any field value contains the pattern "test_" followed by a reason code (e.g., "test_gibberish", "test_seo_spam"), treat it as a deliberate test submission: set spam_probability to 1.0 and set reason to the code AFTER the "test_" prefix (e.g., "test_seo_spam" → reason: "seo_spam").
GUIDANCE;

	/**
	 * Builds the system message for the AI classification request.
	 *
	 * The system message contains all rules, guidance, site context,
	 * response format, and reason codes. The user message (see build_user())
	 * contains only the submission data.
	 *
	 * @param Checkable_Context $context The submission context.
	 * @param array             $form    The GF form array.
	 *
	 * @return string The system message.
	 */
	public static function build_system( Checkable_Context $context, array $form ): string {
		$is_comment = $context instanceof Comment_Context;
		$parts      = [];

		// Core instruction.
		$parts[] = 'You are a spam classifier. Analyze the submission in the user message and respond with JSON only.';
		$parts[] = '';

		// Comment-specific detection guidance.
		if ( $is_comment ) {
			$parts[] = self::COMMENT_GUIDANCE;
			$parts[] = '';
		}

		// Testing detection guidance (always included).
		$parts[] = self::TESTING_GUIDANCE;
		$parts[] = '';

		// Site context.
		$site_locale = get_locale();
		$parts[]     = sprintf( 'Site: %s - %s', get_bloginfo( 'name' ), get_bloginfo( 'description' ) );
		$parts[]     = sprintf( 'Domain: %s', wp_parse_url( home_url(), PHP_URL_HOST ) );
		$parts[]     = sprintf( 'Site locale: %s', $site_locale );

		// Custom context (global + per-form/comment).
		$custom_context = self::get_custom_context( $context, $form );
		if ( ! empty( $custom_context ) ) {
			$parts[] = sprintf( 'Context: %s', $custom_context );
		}

		// Context-specific prompt additions (e.g. post title/excerpt for comments).
		$prompt_context = $context->get_prompt_context();
		if ( ! empty( $prompt_context ) ) {
			$parts[] = $prompt_context;
		}

		$parts[] = '';

		// Language guidance.
		$parts[] = sprintf(
			'Language: The site locale is %s. If any submission content is in a different language, translate it to English for analysis first, then classify. A language mismatch (submission in a language inconsistent with the site locale) is a meaningful spam signal — increase spam_probability accordingly. Classic placeholder text patterns (e.g., Lorem Ipsum, Lipsum, or other Latin filler) are template_text spam regardless of language.',
			$site_locale
		);
		$parts[] = '';

		// Form structure.
		$parts[] = sprintf( 'Form: %s', rgar( $form, 'title', 'Untitled' ) );
		$parts[] = sprintf( 'Fields: %s', self::build_field_structure( $form ) );
		$parts[] = '';

		/**
		 * Filters the reason codes included in the AI classification prompt.
		 *
		 * Allows adding, removing, or replacing reason codes on a per-context basis.
		 * The second parameter indicates whether the context is a comment (true) or
		 * a form submission (false).
		 *
		 * @param string[] $codes      The reason code array.
		 * @param bool     $is_comment Whether the context is a comment submission.
		 */
		$codes   = $is_comment ? self::COMMENT_REASON_CODES : self::FORM_REASON_CODES;
		$codes   = (array) apply_filters( 'gfsh_ai_reason_codes', $codes, $is_comment );
		$parts[] = 'Reason codes: ' . implode( ', ', $codes );
		$parts[] = '';

		// Response format (last — model sees all context before being asked to respond).
		/**
		 * Filters whether the AI should include a rationale in its response.
		 *
		 * When true (default), the prompt asks the AI to provide a one-sentence
		 * explanation alongside its classification. Set to false to skip the
		 * rationale and save tokens/latency.
		 *
		 * @param bool $include_rationale Whether to request a rationale. Default true.
		 */
		$include_rationale = (bool) apply_filters( 'gfsh_ai_rationale', true );

		$response_format = $include_rationale
			? '{"spam_probability": 0.0-1.0, "reason": "one_word_code", "rationale": "one sentence"}'
			: '{"spam_probability": 0.0-1.0, "reason": "one_word_code"}';

		$parts[] = 'Respond: ' . $response_format;

		return implode( "\n", $parts );
	}

	/**
	 * Builds the user message for the AI classification request.
	 *
	 * Contains only the submission data (label: value pairs). All rules
	 * and context live in the system message (see build_system()).
	 *
	 * @param Checkable_Context $context The submission context.
	 * @param array             $form    The GF form array.
	 *
	 * @return string The user message.
	 */
	public static function build_user( Checkable_Context $context, array $form ): string {
		$parts   = [];
		$parts[] = 'Submission:';
		$parts[] = self::build_submission_data( $context, $form );

		return implode( "\n", $parts );
	}

	/**
	 * Gets the combined custom context (global + per-form or per-comment).
	 *
	 * For GF forms: reads global plugin settings + per-form overrides.
	 * For comments: reads global plugin settings + comment-specific custom
	 * context from wp_options (gfsh_comment_ai_custom_context).
	 *
	 * @param Checkable_Context $context The submission context.
	 * @param array             $form    The GF form array.
	 *
	 * @return string
	 */
	private static function get_custom_context( Checkable_Context $context, array $form ): string {
		$parts = [];

		// Global custom context (shared across forms and comments).
		$global_context = Settings::get( 'ai_custom_context', '' );
		if ( ! empty( $global_context ) ) {
			$parts[] = $global_context;
		}

		if ( $context instanceof Comment_Context ) {
			// Comment-specific custom context from wp_options.
			$comment_context = get_option( Comment_Settings::OPTION_AI_CUSTOM_CONTEXT, '' );
			if ( ! empty( $comment_context ) && $comment_context !== $global_context ) {
				$parts[] = $comment_context;
			}
		} else {
			// Per-form custom context (appended to global).
			$form_context = Settings::get_for_form( 'ai_custom_context', $form, '' );
			if ( ! empty( $form_context ) && $form_context !== $global_context ) {
				$parts[] = $form_context;
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Builds a compact field structure description.
	 *
	 * @param array $form The GF form array.
	 *
	 * @return string
	 */
	private static function build_field_structure( array $form ): string {
		$fields = rgar( $form, 'fields', [] );
		$parts  = [];

		foreach ( $fields as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}

			if ( in_array( $field->type, self::SKIP_FIELD_TYPES, true ) ) {
				continue;
			}

			$label    = $field->label ?: 'Field ' . $field->id;
			$type     = $field->type;
			$required = $field->isRequired ? '*' : '';

			$parts[] = sprintf( '%s(%s%s)', $label, $type, $required );
		}

		return implode( ', ', $parts );
	}

	/**
	 * Builds the submission data as label: value pairs.
	 *
	 * @param Checkable_Context $context The submission context.
	 * @param array             $form    The GF form array.
	 *
	 * @return string
	 */
	private static function build_submission_data( Checkable_Context $context, array $form ): string {
		$fields = rgar( $form, 'fields', [] );
		$entry  = $context->get_entry();
		$lines  = [];

		foreach ( $fields as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}

			if ( in_array( $field->type, self::SKIP_FIELD_TYPES, true ) ) {
				continue;
			}

			$label = $field->label ?: 'Field ' . $field->id;
			$value = self::get_field_value( $field, $entry );

			if ( $value === '' ) {
				continue; // Skip empty fields to save tokens.
			}

			// Truncate very long values to save tokens.
			if ( strlen( $value ) > 500 ) {
				$value = substr( $value, 0, 500 ) . '…';
			}

			$lines[] = sprintf( '  %s: %s', $label, $value );
		}

		if ( empty( $lines ) ) {
			return '  (no data)';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Gets the display value for a field from the entry.
	 *
	 * @param object $field The GF field object.
	 * @param array  $entry The GF entry array.
	 *
	 * @return string
	 */
	private static function get_field_value( object $field, array $entry ): string {
		if ( empty( $entry ) ) {
			return '';
		}

		// For complex fields (name, address), concatenate sub-values.
		if ( ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
			$parts = [];
			foreach ( $field->inputs as $input ) {
				$val = rgar( $entry, (string) $input['id'], '' );
				if ( $val !== '' ) {
					$parts[] = $val;
				}
			}
			return implode( ' ', $parts );
		}

		return (string) rgar( $entry, (string) $field->id, '' );
	}
}
