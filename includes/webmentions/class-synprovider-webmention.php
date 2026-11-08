<?php
/**
 * Base class for providers that syndicate by sending a webmention.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syndicates a post by sending a webmention from it to a target, such as Bridgy.
 *
 * The target is added to the post's links first, since services like Bridgy look for
 * it on the page. Requires the Webmention plugin's send_webmention().
 */
class SynProvider_Webmention extends Syndication_Provider {

	/**
	 * Sends a webmention from a URL to the provider's target.
	 *
	 * @param string $url The source URL.
	 * @return array|WP_Error|null The response, or null if there's no target.
	 */
	public function send_webmention( $url ) {
		$target = $this->get_target();
		if ( ! $target ) {
			return;
		}
		return send_webmention( $url, $target );
	}

	/**
	 * Returns the webmention target.
	 *
	 * @return string|null The target URL, or null if there's none.
	 */
	public function get_target() {
		return null;
	}

	/**
	 * Syndicates a post: adds the target to its links, sends the webmention, and
	 * swaps the target for the copy's URL from the `Location` header.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return array|WP_Error The response, or an error.
	 */
	public function posse( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		$post = get_post( $post_id );
		add_post_syndication_link( $post_id, $this->get_target() );

		// Attempt at cache busting by running anything attached to 'edit_post'.
		/** This action is documented in wp-includes/post.php */
		do_action( 'edit_post', $post_id, $post );

		/**
		 * Fires before the webmention is sent, after the target is added to the post's links.
		 *
		 * Use it to clear the post from a page cache, so the target sees the link.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'pre_syndication_links_webmention', $post_id );

		$response = self::send_webmention( get_permalink( $post_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$data          = json_decode( $response['body'] );
		if ( ! $data ) {
			$data = $response['body'];
		}

		if ( ! in_array( (int) $response_code, array( 201, 202 ), true ) ) {
			return new WP_Error(
				$this->uid . '_publish_error',
				/* translators: Syndication Target */
				sprintf( __( 'Unknown %1$s Error', 'syndication-links' ), $this->name ),
				array(
					'status' => $response_code,
					'data'   => $data,
				)
			);
		}

		$links  = get_post_syndication_links_data( $post_id );
		$search = array_search( $this->get_target(), $links, true );
		if ( false !== $search ) {
			unset( $links[ $search ] );
		}

		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( ! empty( $location ) ) {
			$links[] = $location;
		}

		add_post_syndication_link( $post_id, $links, true );
		return $response;
	}
}
