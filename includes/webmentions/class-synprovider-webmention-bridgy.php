<?php

class SynProvider_Webmention_Bridgy extends SynProvider_Webmention {
	public function __construct( $args = array() ) {

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option ) : false;
		if ( $enable ) {
			add_filter( 'webmention_send_vars', array( $this, 'webmention_send_vars' ), 10, 2 );
		}
		// Parent Constructor
		parent::__construct( $args );
	}

	public function webmention_send_vars( $body, $post_id ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( $this->get_target() === urldecode( $body['target'] ) ) {
			$backlink = get_option( 'syndication_backlink' );
			if ( ! empty( $backlink ) ) {
				$body['bridgy_omit_link'] = $backlink;
			}
			if ( 1 === (int) get_option( 'bridgy_ignoreformatting' ) ) {
				$body['bridgy-ignore-formatting'] = 'true';
			}
		}
		return $body;
	}
}
