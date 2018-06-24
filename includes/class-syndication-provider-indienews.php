<?php

class Syndication_Provider_Indienews extends Syndication_Provider {

	public function __construct( $args = array() ) {
		if ( ! class_exists( 'Webmention_Plugin' ) ) {
			return;
		}
		$this->name = __( 'Indienews', 'syndication-links' );
		$this->uid  = 'indienews';
		// Micropub Syndication Targets
		add_filter( 'micropub_syndicate-to', array( $this, 'syndicate_to' ), 10, 2 );

		// Parent Constructor
		parent::__construct( $args );
	}

	/**
	 * Get the blogs language and check if it supported.
	 *
	 * @return string The blogs language with a default fallback.
	 */
	public function get_language() {
		$locale    = get_locale();
		$locale    = substr( $locale, 0, 2 );
		$languages = array( 'en', 'sv', 'de', 'fr', 'nl' );
		if ( in_array( $locale, $languages, true ) ) {
			return $locale;
		}
		return 'en';
	}

	public function get_indienews() {
		return sprintf( 'https://news.indieweb.org/%1$s', $this->get_language() );
	}

	public function send_webmention( $url ) {
		$target        = $this->get_indienews();
		$response      = send_webmention( $url, $target );
		$response_code = wp_remote_retrieve_response_code( $response );
		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( 201 === $response_code ) {
			return $location;
		}
		$json = json_decode( $response['body'] );
		return new WP_Error(
			'indienews_publish_error', __( 'Unknown Indienews Error', 'syndication-links' ), array(
				'status' => $response_code,
				'data'   => $json,
			)
		);
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
		add_syndication_link( $post_id, $this->get_indienews() );
		$response = self::send_webmention( get_permalink( $post_id ) );
		if ( ! is_wp_error( $response ) ) {
			$links  = get_syndication_links_data( $post_id );
			$search = array_search( $this->get_indienews(), $links, true );
			if ( $search ) {
				unset( $links[ $search ] );
			}
			if ( is_string( $response ) ) {
				$links[] = $response;
				add_syndication_link( $post_id, $links, true );
			}
			else {
				error_log( $response );
			}
		} else {
			return $response;
		}
		return true;
	}

}
