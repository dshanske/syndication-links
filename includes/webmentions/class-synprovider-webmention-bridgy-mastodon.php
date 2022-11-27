<?php

class SynProvider_Webmention_Bridgy_Mastodon extends SynProvider_Webmention_Bridgy {

	public function __construct( $args = array() ) {
		$this->name = __( 'Mastodon via Bridgy Webmention', 'syndication-links' );
		$this->uid  = 'webmention-mastodon-bridgy';

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option) ? in_array( $this->uid, $option ) : false;

		if ( $enable ) {
			add_action( 'wp_footer', array( $this, 'wp_footer' ) );
			add_action( 'admin_init', array( $this, 'mastodon_admin_init' ), 12 );
		}

		// Parent Constructor
		parent::__construct( $args );
		$this->register_mastodon_setting();
	}

	public function register_mastodon_setting() {
		register_setting(
			'syndication_options',
			'bridgy_mastodonexcerpt',
			array(
				'type'         => 'boolean',
				'description'  => 'Use Post Excerpt for Posts',
				'show_in_rest' => true,
				'default'      => '0',
			)
		);
	}

	public function mastodon_admin_init() {
		add_settings_field(
			'bridgy_mastodonexcerpt',
			__( 'Tell Bridgy to Use Post Excerpt for Matodon Posts if set', 'syndication-links' ),
			array(
				'Syn_Config',
				'checkbox_callback',
			),
			'syndication_provider_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_mastodonexcerpt',
			)
		);
	}
	public function wp_footer() {
		if ( ( 1 === (int) get_option( 'bridgy_mastodonexcerpt' ) ) && has_excerpt() ) {
			printf( '<p class="p-bridgy-mastodon-content" style="display:none">%1$s</p>', get_the_excerpt() ); // phpcs:ignore
		}
	}

	public function get_target() {
		return 'https://brid.gy/publish/mastodon';
	}
}

