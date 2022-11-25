<?php

class Syndication_Provider_Bridgy_Meetup extends Syndication_Provider_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Meetup via Bridgy', 'syndication-links' );
		$this->uid  = 'meetup-bridgy';

		// Parent Constructor
		parent::__construct( $args );
	}

	public function get_target() {
		return 'https://brid.gy/publish/meetup';
	}
}
