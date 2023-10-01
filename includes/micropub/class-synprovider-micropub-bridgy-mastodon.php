<?php

/**
 *  Mastodon Micropub Class
 */

class SynProvider_Micropub_Bridgy_Mastodon extends SynProvider_Micropub {
	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Array of Arguments
	 */
	public function __construct( $args = array() ) {
		$this->name           = __( 'Mastodon via Bridgy', 'syndication-links' );
		$this->uid            = 'micropub-mastodon-bridgy';
		$this->endpoint       = 'https://brid.gy/micropub';
		$this->content_length = 500;

		if ( ! array_key_exists( 'token', $args ) ) {
			$this->token = get_option( 'bridgy_mastodon_token' );
		}

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option ) : false;

		if ( $enable ) {
			add_action( 'admin_init', array( $this, 'admin_init' ), 11 );
		}

		parent::__construct( $args );
		$this->register_setting();
	}

	public function register_setting() {
		register_setting(
			'syndication_apis',
			'bridgy_mastodon_token',
			array(
				'type'         => 'string',
				'description'  => 'Bridgy Mastodon Micropub Token',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public function admin_init() {
		add_settings_field(
			'bridgy_mastodon_token',
			__( 'Micropub Token to enable Bridgy Mastodon Publish', 'syndication-links' ),
			array(
				'Syn_Config',
				'text_callback',
			),
			'syndication_api_keys',
			'syndication_apis',
			array(
				'label_for' => 'bridgy_mastodon_token',
				'type'      => 'password',
			)
		);
	}
}
