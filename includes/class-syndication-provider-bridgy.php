<?php

class Syndication_Provider_Bridgy extends Syndication_Provider_Webmention {

	public function __construct( $args = array() ) {
		$this->register_setting();
		add_action( 'admin_init', array( $this, 'admin_init' ), 11 );
		add_filter( 'webmention_send_vars', array( $this, 'webmention_send_vars' ), 10, 2 );
		// Parent Constructor
		parent::__construct( $args );
	}

	public function register_setting() {
		register_setting(
			'syndication_options',
			'bridgy_backlink',
			array(
				'type'         => 'string',
				'description'  => 'Disable Bridgy Linking Back to These Providers',
				'show_in_rest' => true,
				'default'      => 'maybe',
			)
		);
		register_setting(
			'syndication_options',
			'bridgy_ignoreformatting',
			array(
				'type'         => 'boolean',
				'description'  => 'Tell Bridgy to Ignore Formatting when Publishing',
				'show_in_rest' => true,
				'default'      => false,
			)
		);
	}

	public function options_callback() {
		printf( '<p>%1$s</p>', esc_html__( 'Options for Publishing with Bridgy', 'syndication-links' ) );
	}

	public function admin_init() {
		add_settings_section(
			'bridgy_options',
			__( 'Bridgy Publish  Options', 'syndication-links' ),
			array( get_called_class(), 'options_callback' ),
			'links_options'
		);

		add_settings_field(
			'bridgy_backlink',
			__( 'Bridgy Posts should link back to site posts', 'syndication-links' ),
			array(
				'Syn_Config',
				'select_callback',
			),
			'links_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_backlink',
				'list' => array(
					''      => __( 'True', 'syndication-links' ),
					'true'  => __( 'False', 'syndication-links' ),
					'maybe' => __( 'If too long', 'syndication-links' ),
				),
			)
		);
		add_settings_field(
			'bridgy_ignoreformatting',
			__( 'Tell Bridgy to Ignore Formatting', 'syndication-links' ),
			array(
				'Syn_Config',
				'checkbox_callback',
			),
			'links_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_ignoreformatting',
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

