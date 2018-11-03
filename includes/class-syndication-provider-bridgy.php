<?php

class Syndication_Provider_Bridgy extends Syndication_Provider_Webmention {

	public function __construct( $args = array() ) {
		add_filter( 'webmention_send_vars', array( $this, 'webmention_send_vars' ), 10, 2 );

		// Parent Constructor
		parent::__construct( $args );

		// Syndication Links POSSE/Syndication Options
		register_setting(
			'syndication_options',
			'bridgy_backlink',
			array(
				'type'         => 'string',
				'description'  => 'Disable Display of these Providers',
				'show_in_rest' => true,
				'default'      => '',
			)
		);
		// add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public static function admin_init() {
		add_settings_field(
			'bridgy_backlink',
			__( 'Bridgy Posts should link back to site posts', 'syndication-links' ),
			array(
				'Syn_Config',
				'select_callback',
			),
			'links_options',
			'syndication_posse_options',
			array(
				'name' => 'bridgy_backlink',
				'list' => array(
					''      => __( 'True', 'syndication-links' ),
					'true'  => __( 'False', 'syndication-links' ),
					'maybe' => __( 'If too long', 'syndication-links' ),
				),
			)
		);
	}


	public function webmention_send_vars( $body, $post_id ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( $this->get_target() === urldecode( $body['target'] ) ) {
			$backlink = get_option( 'bridgy_backlink' );
			if ( ! empty( $backlink ) ) {
				$body['bridgy_omit_link'] = $backlink;
			}
			if ( 1 === (int) get_option( 'bridgy_ignoreformatting' ) ) {
				$body['bridgy-ignore-formatting'] = 'true';
			}
		}
		return $body;
	}
}

