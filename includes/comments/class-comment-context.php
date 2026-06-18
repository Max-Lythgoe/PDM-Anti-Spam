<?php
/**
 * Comment submission context.
 *
 * Wraps WordPress $commentdata and provides the Checkable_Context interface
 * so that Proof_Of_Work and AI_Classifier can evaluate comments using the
 * same code paths as GF form submissions.
 *
 * @package PDM_Antispam
 */

namespace PDM_Antispam\Comments;

use PDM_Antispam\Abstract_Context;

/**
 * Context object wrapping WordPress comment data for spam analysis.
 *
 * Provides synthetic "form" and "entry" arrays that map comment fields
 * to GF-style structures, allowing techniques and Prompt_Builder to
 * work without modification.
 */
class Comment_Context extends Abstract_Context {

	/**
	 * Stable form ID for comment PoW scoping.
	 *
	 * Derived from abs( crc32( 'wp_comments' ) ) so comment difficulty
	 * tracking is isolated from any GF form's difficulty tracking.
	 */
	const COMMENT_FORM_ID = 1234567890;

	/**
	 * The WordPress comment data array.
	 *
	 * @var array
	 */
	private array $commentdata;

	/**
	 * @param array $commentdata The WordPress comment data array.
	 */
	public function __construct( array $commentdata ) {
		parent::__construct();
		$this->commentdata = $commentdata;
	}

	/**
	 * Gets the synthetic form ID for comments.
	 *
	 * @return int
	 */
	public function get_form_id(): int {
		return self::COMMENT_FORM_ID;
	}

	/**
	 * Returns a synthetic "form" array for AI prompt building.
	 *
	 * Provides enough structure for Prompt_Builder to work. Fields are
	 * stdClass objects to match GF field format (Prompt_Builder checks
	 * is_object() and accesses ->type, ->label, ->isRequired, ->id).
	 *
	 * @return array
	 */
	public function get_form(): array {
		return [
			'id'     => self::COMMENT_FORM_ID,
			'title'  => __( 'WordPress Comment', 'pdm-antispam' ),
			'fields' => [
				(object) [ 'id' => 1, 'label' => 'Name', 'type' => 'name', 'isRequired' => true, 'inputs' => null ],
				(object) [ 'id' => 2, 'label' => 'Email', 'type' => 'email', 'isRequired' => true, 'inputs' => null ],
				(object) [ 'id' => 3, 'label' => 'Website', 'type' => 'website', 'isRequired' => false, 'inputs' => null ],
				(object) [ 'id' => 4, 'label' => 'Comment', 'type' => 'textarea', 'isRequired' => true, 'inputs' => null ],
			],
		];
	}

	/**
	 * Returns a synthetic "entry" array for AI prompt building.
	 *
	 * Maps comment fields to GF-style field IDs matching get_form().
	 *
	 * @return array
	 */
	public function get_entry(): array {
		return [
			'form_id'    => self::COMMENT_FORM_ID,
			'ip'         => $this->commentdata['comment_author_IP'] ?? '',
			'user_agent' => $this->commentdata['comment_agent'] ?? '',
			'source_url' => $this->commentdata['comment_author_url'] ?? '',
			'1'          => $this->commentdata['comment_author'] ?? '',
			'2'          => $this->commentdata['comment_author_email'] ?? '',
			'3'          => $this->commentdata['comment_author_url'] ?? '',
			'4'          => $this->commentdata['comment_content'] ?? '',
		];
	}

	/**
	 * Gets additional context for the AI prompt.
	 *
	 * Includes the post title and excerpt (or trimmed body) so the AI
	 * can assess whether the comment is on-topic for the post content.
	 *
	 * @return string Post context string.
	 */
	public function get_prompt_context(): string {
		$post_id = (int) ( $this->commentdata['comment_post_ID'] ?? 0 );

		if ( $post_id <= 0 ) {
			return '';
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$parts = [];

		// Post title.
		$title = get_the_title( $post );
		if ( ! empty( $title ) ) {
			$parts[] = sprintf( 'Post: %s', $title );
		}

		// Post excerpt or trimmed body.
		$excerpt = $this->get_post_excerpt( $post );
		if ( ! empty( $excerpt ) ) {
			$parts[] = sprintf( 'Post excerpt: %s', $excerpt );
		}

		// Post categories/tags for topic context.
		$terms = $this->get_post_terms( $post );
		if ( ! empty( $terms ) ) {
			$parts[] = sprintf( 'Topics: %s', $terms );
		}

		return implode( "\n", $parts );
	}

	/**
	 * Gets the post excerpt, falling back to a trimmed post body.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return string The excerpt (max 300 chars).
	 */
	private function get_post_excerpt( \WP_Post $post ): string {
		// Prefer the manual excerpt if set.
		if ( ! empty( $post->post_excerpt ) ) {
			$excerpt = wp_strip_all_tags( $post->post_excerpt );
		} else {
			// Fall back to trimmed post content.
			$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$excerpt = $content;
		}

		// Trim to 300 chars to save tokens.
		if ( mb_strlen( $excerpt ) > 300 ) {
			$excerpt = mb_substr( $excerpt, 0, 300 ) . '…';
		}

		return trim( $excerpt );
	}

	/**
	 * Gets a comma-separated list of post categories and tags.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return string
	 */
	private function get_post_terms( \WP_Post $post ): string {
		$terms = [];

		$categories = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $cat ) {
				if ( $cat->slug !== 'uncategorized' ) {
					$terms[] = $cat->name;
				}
			}
		}

		$tags = get_the_tags( $post->ID );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$terms[] = $tag->name;
			}
		}

		return implode( ', ', $terms );
	}

	/**
	 * Gets the raw WordPress comment data array.
	 *
	 * @return array
	 */
	public function get_commentdata(): array {
		return $this->commentdata;
	}
}
