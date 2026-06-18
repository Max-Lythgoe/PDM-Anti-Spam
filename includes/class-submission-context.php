<?php
/**
 * Submission metadata collection.
 *
 * Collects and normalizes all submission metadata needed by techniques:
 * form ID, POST data, client payload (PoW solution, timing, autofill),
 * server-side timing, and request headers.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam;

use PDM_Antispam\Security\Request_Info;

/**
 * Collects and normalizes submission metadata for spam analysis.
 *
 * Created once per submission and passed to all techniques. Lazily
 * decodes the client payload on first access.
 */
class Submission_Context extends Abstract_Context {

	/**
	 * The GF form array.
	 *
	 * @var array
	 */
	private array $form;

	/**
	 * The GF entry array (may be empty for pre-submission checks).
	 *
	 * @var array
	 */
	private array $entry;

	/**
	 * @param array $form  The GF form array.
	 * @param array $entry The GF entry array (may be empty).
	 */
	public function __construct( array $form, array $entry = [] ) {
		parent::__construct();
		$this->form  = $form;
		$this->entry = $entry;
	}

	/**
	 * Gets the form ID.
	 *
	 * @return int
	 */
	public function get_form_id(): int {
		return (int) ( $this->form['id'] ?? 0 );
	}

	/**
	 * Gets the full form array.
	 *
	 * @return array
	 */
	public function get_form(): array {
		return $this->form;
	}

	/**
	 * Gets the entry array.
	 *
	 * @return array
	 */
	public function get_entry(): array {
		return $this->entry;
	}

	/**
	 * Gets a specific value from the client payload.
	 *
	 * @param string $key     Payload key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	public function get_payload_value( string $key, $default = null ) {
		$payload = $this->get_client_payload();

		return $payload[ $key ] ?? $default;
	}

	/**
	 * Gets the client IP address.
	 *
	 * Delegates to Request_Info for proxy-aware resolution.
	 * Never stored directly — only used as HMAC input for difficulty tracking.
	 *
	 * @return string
	 */
	public function get_client_ip(): string {
		return Request_Info::get_client_ip();
	}

	/**
	 * Gets the User-Agent header.
	 *
	 * @return string
	 */
	public function get_user_agent(): string {
		return Request_Info::get_user_agent();
	}

	/**
	 * Builds the client signal string for HMAC-based tracking.
	 *
	 * @return string
	 */
	public function get_client_signal(): string {
		return Request_Info::get_client_signal();
	}

	/**
	 * Whether the client payload is present at all.
	 *
	 * If false, the client-side collector likely didn't run (JS disabled,
	 * bot submission, etc.).
	 *
	 * @return bool
	 */
	public function has_client_payload(): bool {
		return ! empty( $this->get_client_payload() );
	}

	/**
	 * Gets additional context for the AI prompt.
	 *
	 * GF forms use their own custom context system via Settings::get()
	 * and per-form overrides, so this returns an empty string.
	 *
	 * @return string Always empty for GF submissions.
	 */
	public function get_prompt_context(): string {
		return '';
	}
}
