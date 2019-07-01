<?php

class Syndication_Provider_Bridgy_Github extends Syndication_Provider_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Github via Bridgy', 'syndication-links' );
		$this->uid  = 'github-bridgy';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://brid.gy/publish/github';
	}
}

register_syndication_provider( new Syndication_Provider_Bridgy_Github() );

