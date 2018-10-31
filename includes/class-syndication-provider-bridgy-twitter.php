<?php

class Syndication_Provider_Bridgy_Twitter extends Syndication_Provider_Webmention {

	public function __construct( $args = array() ) {
		$this->name = __( 'Twitter via Bridgy', 'syndication-links' );
		$this->uid  = 'twitter-bridgy';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://brid.gy/publish/twitter';
	}
}

if ( class_exists( 'Webmention_Plugin' ) ) {
	register_syndication_provider( new Syndication_Provider_Bridgy_Twitter() );
}
