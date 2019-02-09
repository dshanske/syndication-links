<?php

class Syndication_Provider_Webmention_Custom extends Syndication_Provider_Webmention {
	protected $target;

	public function __construct( $args = array() ) {
		if ( ! array_key_exists( 'name', $args ) || ! array_key_exists( 'uid', $args ) ) {
			return null;
		}
		$this->name   = $args['name'];
		$this->uid    = $args['uid'];
		$this->target = $args['target'];

		// Parent Constructor
		parent::__construct( $args );
	}

	public static function option_callback( $args ) {
		$custom = get_option( 'syndication_links_custom_posse' );
		if ( ! empty( $custom ) || ! is_array( $custom ) ) {
			printf( '<input type="checkbox" name="f" id="f" value="" />' );
		} else {
			foreach ( $custom as $key => $value ) {
				printf( '<input type="checkbox" name="f" id="f" value="" />' );
			}
		}
	}

	public function get_target() {
		return esc_url_raw( $this->target );
	}
}

if ( class_exists( 'Webmention_Plugin' ) ) {
	$custom = get_option( 'syndication_links_custom_posse' );
	if ( ! empty( $custom ) && is_array( $custom ) ) {
		foreach ( $custom as $c ) {
			register_syndication_provider( new Syndication_Provider_Webmention_Custom( $c ) );
		}
	}
}
