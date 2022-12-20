<?php

/**
 * Base Micropub Class
 */

class SynProvider_Micropub_Bridgy_Twitter extends SynProvider_Micropub {
	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Array of Arguments
	 */
	public function __construct( $args = array() ) {
		$this->name     = __( 'Twitter via Bridgy Micropub', 'syndication-links' );
		$this->uid      = 'micropub-twitter-bridgy';
		$this->endpoint = 'https://brid.gy/micropub';

		if ( ! array_key_exists( 'token', $args ) ) {
			$this->token = get_option( 'bridgy_twitter_token' );
		}

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option ) : false;

		if ( $enable ) {
			add_action( 'admin_init', array( $this, 'twitter_admin_init' ), 12 );
		}

		parent::__construct( $args );
		$this->register_twitter_setting();
	}

	public function register_twitter_setting() {
		register_setting(
			'syndication_providers',
			'bridgy_twitter_token',
			array(
				'type'         => 'string',
				'description'  => 'Bridgy Twitter Micropub Token',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'syndication_providers',
			'bridgy_twitterexcerpt',
			array(
				'type'         => 'boolean',
				'description'  => 'Use Post Excerpt for Tweets',
				'show_in_rest' => true,
				'default'      => '0',
			)
		);
	}

	public function twitter_admin_init() {
		add_settings_field(
			'bridgy_twitter_token',
			__( 'Micropub Token for Bridgy', 'syndication-links' ),
			array(
				'Syn_Config',
				'text_callback',
			),
			'syndication_provider_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_twitter_token',
			)
		);
		add_settings_field(
			'bridgy_twitterexcerpt',
			__( 'Tell Bridgy to Use Post Excerpt for Tweets if set', 'syndication-links' ),
			array(
				'Syn_Config',
				'checkbox_callback',
			),
			'syndication_provider_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_twitterexcerpt',
			)
		);
	}

	/**
	 * Convert post to Microformats for Micropub
	 *
	 * @param int|WP_Post $post WordPress Post
	 * @return array|false Microformats
	 */
	public static function post_to_mf2( $post ) {
		$mf2 = parent::post_to_mf2( $post );

		return $mf2;
	}
}
