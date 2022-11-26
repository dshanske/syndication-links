<?php

class SynProvider_Webmention_Bridgy_Reddit extends SynProvider_Webmention_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Reddit via Bridgy Webmention', 'syndication-links' );
		$this->uid  = 'webmention-reddit-bridgy';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://brid.gy/publish/reddit';
	}
}


