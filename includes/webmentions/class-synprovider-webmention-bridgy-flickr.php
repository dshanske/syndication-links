<?php

class SynProvider_Webmention_Bridgy_Flickr extends SynProvider_Webmention_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Flickr via Bridgy', 'syndication-links' );
		$this->uid  = 'webmention-flickr-bridgy';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://brid.gy/publish/flickr';
	}
}
