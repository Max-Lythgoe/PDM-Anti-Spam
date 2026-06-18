<?php
/**
 * Shared context interface for spam-checkable submissions.
 *
 * Both GF form submissions (Submission_Context) and WordPress comments
 * (Comment_Context) implement this interface, allowing techniques like
 * Proof_Of_Work and AI_Classifier to work with either context type.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Contracts;

/**
 * Interface that all checkable submission contexts must implement.
 *
 * Provides the common surface that spam detection techniques need:
 * form/entry data for AI classification, PoW solution extraction,
 * and POST data access.
 */
interface Checkable_Context {

	/**
	 * Gets the form ID (or synthetic form ID for non-GF contexts).
	 *
	 * @return int
	 */
	public function get_form_id(): int;

	/**
	 * Gets the form array (or synthetic form for non-GF contexts).
	 *
	 * Must include 'id', 'title', and 'fields' keys.
	 * Fields should be objects with ->id, ->label, ->type, ->isRequired properties.
	 *
	 * @return array
	 */
	public function get_form(): array;

	/**
	 * Gets the entry array (or synthetic entry for non-GF contexts).
	 *
	 * Keys are string field IDs mapping to submitted values.
	 *
	 * @return array
	 */
	public function get_entry(): array;

	/**
	 * Gets a raw POST value.
	 *
	 * @param string $key     POST field name.
	 * @param mixed  $default Default value if not present.
	 *
	 * @return mixed
	 */
	public function get_post_value( string $key, $default = null );

	/**
	 * Gets the decoded client payload from the collector.
	 *
	 * @return array<string, mixed>
	 */
	public function get_client_payload(): array;

	/**
	 * Gets the PoW solution data from the client payload.
	 *
	 * @return array{challenge: string, signature: string, solution: string, solve_time_ms: int, is_fallback: bool, client_nonce: string}|null
	 */
	public function get_pow_solution(): ?array;

	/**
	 * Gets the server-side receive timestamp.
	 *
	 * @return float Unix timestamp with microseconds.
	 */
	public function get_server_receive_time(): float;

	/**
	 * Gets additional context for the AI prompt.
	 *
	 * Returns context-specific information that helps the AI classifier
	 * make better decisions. For comments, this includes the post title
	 * and excerpt. For GF forms, this returns an empty string (GF uses
	 * its own custom context system via Settings).
	 *
	 * @return string Additional context string, or empty if none.
	 */
	public function get_prompt_context(): string;
}
