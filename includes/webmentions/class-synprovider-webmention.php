<?php

class SynProvider_Webmention extends Syndication_Provider {

	public function send_webmention( $url ) {
		$target = $this->get_target();
		if ( ! $target ) {
			return;
		}
		return send_webmention( $url, $target );
	}

	public function get_target() {
		return null;
	}

	/**
	 * Given a post try to POSSE it to a given network
	 *
	 * @return array of results
	 */
	public function posse( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		$post = get_post( $post_id );
		add_post_syndication_link( $post_id, $this->get_target() );

		// Attempt at cache busting by running anything attached to 'edit_post'.
		do_action( 'edit_post', $post_id, $post );

		// Add a custom action to attach a manual clear cache to.
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

		if ( ! in_array( $response_code, array( 201, 202 ) ) ) {
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
