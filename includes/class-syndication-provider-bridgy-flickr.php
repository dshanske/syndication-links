<?php

class Syndication_Provider_Bridgy_Flickr extends Syndication_Provider_Bridgy {

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
