<?php

class SynProvider_Webmention_Bridgy_Twitter extends SynProvider_Webmention_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Twitter via Bridgy Webmention', 'syndication-links' );
		$this->uid  = 'webmention-twitter-bridgy';

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option ) : false;

		if ( $enable ) {
			add_action( 'wp_footer', array( $this, 'wp_footer' ) );
			add_action( 'admin_init', array( $this, 'twitter_admin_init' ), 12 );
		}

		// Parent Constructor
		parent::__construct( $args );
		$this->register_twitter_setting();
	}

	public function register_twitter_setting() {
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
	public function wp_footer() {
		if ( ( 1 === (int) get_option( 'bridgy_twitterexcerpt' ) ) && has_excerpt() ) {
			printf( '<p class="p-bridgy-twitter-content" style="display:none">%1$s</p>', get_the_excerpt() ); // phpcs:ignore
		}
	}

	public function get_target() {
		return 'https://brid.gy/publish/twitter';
	}
}

