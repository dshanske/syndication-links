<?php

class Syndication_Provider_Bridgy_Flickr extends Syndication_Provider_Webmention {

	public function __construct( $args = array() ) {
		$this->name = __( 'Flickr via Bridgy', 'syndication-links' );
		$this->uid  = 'flickr-bridgy';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://brid.gy/publish/flickr';
	}
}

if ( class_exists( 'Webmention_Plugin' ) ) {
	register_syndication_provider( new Syndication_Provider_Bridgy_Flickr() );
}
