<?php

class SynProvider_Webmention_Bridgy_Mastodon extends SynProvider_Webmention_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Mastodon via Bridgy', 'syndication-links' );
		$this->uid  = 'webmention-mastodon-bridgy';

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option ) : false;

		if ( $enable ) {
			add_action( 'wp_footer', array( $this, 'wp_footer' ) );
		}

		// Parent Constructor
		parent::__construct( $args );
	}

	public function wp_footer() {
		if ( ( 1 === (int) get_option( 'syndication_use_excerpt' ) ) && has_excerpt() ) {
			printf( '<p class="p-bridgy-mastodon-content" style="display:none">%1$s</p>', get_the_excerpt() ); // phpcs:ignore
		}
	}

	public function get_target() {
		return 'https://brid.gy/publish/mastodon';
	}
}
