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
	 * The Webmention spec deletes by sending the webmention again, once the post is gone.
	 *
	 * @var bool
	 */
	protected $supports_delete = true;

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
	 * Tells the target the post is gone, by sending the webmention again.
	 *
	 * The target refetches the post's old URL and finds it gone: the Webmention plugin
	 * answers 410 for trashed posts. Bridgy and Bridgy Fed then delete their copies.
	 *
	 * @since 5.0.0
	 *
	 * @param int      $post_id Post ID. The post may no longer exist.
	 * @param string   $source  The post's URL before it was trashed.
	 * @param string[] $copies  The copies' URLs. Not used.
	 * @return array|WP_Error|null The response, an error, or null if there's no target.
	 */
	public function delete( $post_id, $source, $copies = array() ) {
		$response = $this->send_webmention( $source );
		if ( null === $response || is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 2 !== (int) floor( $code / 100 ) ) {
			return new WP_Error(
				$this->uid . '_delete_error',
				/* translators: %s: provider name */
				sprintf( __( '%s could not delete the copy', 'syndication-links' ), $this->name ),
				array(
					'status' => $code,
					'data'   => wp_remote_retrieve_body( $response ),
				)
			);
		}
		return $response;
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
	 * If sending fails, the target is removed again, unless it was already there.
	 *
	 * @since 5.0.0 Removes the target when sending fails, and returns an error when there's no endpoint.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return array|WP_Error The response, or an error.
	 */
	public function posse( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		$post = get_post( $post_id );
		// A target that was already in the links, e.g. added by hand, is left there if this fails.
		$had_target = in_array( $this->get_target(), Syn_Meta::get_stored_links( 'post', $post_id ), true );
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

		// The Webmention plugin returns false when the target has no endpoint.
		if ( ! is_wp_error( $response ) && ! is_array( $response ) ) {
			$response = new WP_Error(
				$this->uid . '_publish_error',
				/* translators: Syndication Target */
				sprintf( __( 'No webmention endpoint found for %1$s', 'syndication-links' ), $this->name )
			);
		}

		if ( is_wp_error( $response ) ) {
			if ( ! $had_target ) {
				$this->remove_target( $post_id );
			}
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$data          = json_decode( $response['body'] );
		if ( ! $data ) {
			$data = $response['body'];
		}

		// The Webmention spec counts any 2xx response as a success.
		if ( 2 !== (int) floor( (int) $response_code / 100 ) ) {
			if ( ! $had_target ) {
				$this->remove_target( $post_id );
			}
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

		$links    = $this->links_without_target( $post_id );
		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( ! empty( $location ) ) {
			$links[] = $location;
		}

		add_post_syndication_link( $post_id, $links, true );
		return $response;
	}

	/**
	 * Returns the post's stored links, without the target.
	 *
	 * Only the stored links: they replace the post's links, and the ones other plugins
	 * add aren't stored.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[] The links.
	 */
	protected function links_without_target( $post_id ) {
		$links  = Syn_Meta::get_stored_links( 'post', $post_id );
		$search = array_search( $this->get_target(), $links, true );
		if ( false !== $search ) {
			unset( $links[ $search ] );
		}
		return array_values( $links );
	}

	/**
	 * Removes the target from the post's links, after sending to it failed (#146).
	 *
	 * The target is only there for the service to find while it fetches the post.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	protected function remove_target( $post_id ) {
		add_post_syndication_link( $post_id, $this->links_without_target( $post_id ), true );
	}
}
