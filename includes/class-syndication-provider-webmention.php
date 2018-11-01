<?php

class Syndication_Provider_Webmention extends Syndication_Provider {

	public function __construct( $args = array() ) {
		// Parent Constructor
		parent::__construct( $args );
	}

	public function send_webmention( $url ) {
		$target = $this->get_target();
		if ( ! $target ) {
			return;
		}
		$response      = send_webmention( $url, $target );
		$response_code = wp_remote_retrieve_response_code( $response );
		$location      = wp_remote_retrieve_header( $response, 'location' );
		if ( 201 === $response_code ) {
			return $location;
		}
		$json = json_decode( $response['body'] );
		return new WP_Error(
			$this->uid . '_publish_error',
			/* translators: Syndication Target */
			sprintf( __( 'Unknown %1$s Error', 'syndication-links' ), $this->name ),
			array(
				'status' => $response_code,
				'data'   => $json,
			)
		);
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
		add_syndication_link( $post_id, $this->get_target() );
		$response = self::send_webmention( get_permalink( $post_id ) );
		if ( ! is_wp_error( $response ) ) {
			$links  = get_syndication_links_data( $post_id );
			$search = array_search( $this->get_target(), $links, true );
			if ( false !== $search ) {
				unset( $links[ $search ] );
			}
			if ( is_string( $response ) ) {
				$links[] = $response;
				add_syndication_link( $post_id, $links, true );
			} else {
				error_log( $response );
			}
		} else {
			return $response;
		}
		return true;
	}

}
