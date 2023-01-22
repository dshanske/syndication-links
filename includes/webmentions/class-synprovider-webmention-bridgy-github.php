<?php

class SynProvider_Webmention_Bridgy_Github extends SynProvider_Webmention_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Github via Bridgy', 'syndication-links' );
		$this->uid  = 'webmention-github-bridgy';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://brid.gy/publish/github';
	}
}
