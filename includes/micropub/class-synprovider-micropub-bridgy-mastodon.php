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
		$this->name     = __( 'Mastodon via Bridgy', 'syndication-links' );
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
	}

	public function admin_init() {
		add_settings_field(
			'bridgy_mastodon_token',
			__( 'Micropub Token to enable Bridgy Mastodon Publish', 'syndication-links' ),
			array(
				'Syn_Config',
				'text_callback',
			),
			'syndication_provider_options',
			'syndication_providers',
			array(
				'name' => 'bridgy_mastodon_token',
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
		$mf2     = parent::post_to_mf2( $post );
		$content = '';

		if ( ! empty( $post->post_excerpt ) && 1 === get_option( 'syndication_use_excerpt' ) ) {
			$content = $post->post_excerpt;
		} elseif ( ! empty( $post->post_content ) & 500 < strlen( $post->post_content ) ) {
			// If length is over 500 characters then replace content with link plus the title
			$link = get_permalink( $post );
			if ( function_exists( 'kind_get_the_title' ) ) {
				$content = kind_get_the_title( $post ) . ' - ' . $link;
			} elseif ( ! empty( get_the_title( $post ) ) ) {
				$content = get_the_title( $post ) . ' - ' . $link;
			} else {
				$content = substr( $post->post_content, 0, 497 - strlen( $link ) ) . ' - ' . $link;
			}
		}

		if ( ! empty( $content ) ) {
			$mf2['properties']['content'] = array( $content );
		}

		return $mf2;
	}
}
