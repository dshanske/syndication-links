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
		$this->name     = __( 'Mastodon via Bridgy Micropub', 'syndication-links' );
		$this->uid      = 'micropub-mastodon-bridgy';
		$this->endpoint = 'https://brid.gy/micropub';

		if ( ! array_key_exists( 'token', $args ) ) {
			$this->token = get_option( 'bridgy_mastodon_token' );
		}

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option ) : false;

		if ( $enable ) {
			add_action( 'admin_init', array( $this, 'admin_init' ), 12 );
		}

		parent::__construct( $args );
		$this->register_setting();
	}

	public function register_setting() {
		register_setting(
			'syndication_providers',
			'bridgy_mastodon_token',
			array(
				'type'         => 'string',
				'description'  => 'Bridgy Mastodon Micropub Token',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'syndication_providers',
			'bridgy_mastodonexcerpt',
			array(
				'type'         => 'boolean',
				'description'  => 'Use Post Excerpt for Posts',
				'show_in_rest' => true,
				'default'      => '0',
			)
		);
	}

	public function _admin_init() {
		add_settings_field(
			'bridgy_mastodon_token',
			__( 'Micropub Token for Bridgy Mastodon Publish', 'syndication-links' ),
			array(
				'Syn_Config',
				'text_callback',
			),
			'syndication_provider_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_mastodon_token',
			)
		);
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
