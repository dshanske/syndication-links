<?php

class Syndication_Provider_Bridgy_Fed extends Syndication_Provider_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Bridgy Fed', 'syndication-links' );
		$this->uid  = 'bridgy-fed';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://fed.brid.gy';
	}
}
