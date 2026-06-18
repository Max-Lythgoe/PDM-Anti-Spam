<?php
/**
 * AI Provider interface.
 *
 * Abstracts the AI API call so the classifier doesn't depend on a
 * concrete provider implementation. Currently only OpenRouter is used,
 * but the interface allows for future providers or testing with mocks.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\AI;

/**
 * Interface for AI classification providers.
 *
 * Each provider implements a single `classify()` method that sends
 * a prompt to the AI API and returns the parsed response.
 */
interface AI_Provider {

	/**
	 * Sends a classification prompt to the AI and returns the response.
	 *
	 * @param string $system  The system instruction (rules, guidance, format).
	 * @param string $user    The user message (submission data to classify).
	 * @param int    $timeout Request timeout in seconds.
	 *
	 * @return AI_Response The parsed AI response.
	 *
	 * @throws AI_Exception If the API call fails.
	 */
	public function classify( string $system, string $user, int $timeout = 10 ): AI_Response;

	/**
	 * Returns the provider's identifier.
	 *
	 * @return string e.g. 'openrouter'.
	 */
	public function get_name(): string;
}
