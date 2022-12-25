<?php

class SynProvider_Webmention_Custom extends SynProvider_Webmention {
	protected $target;

	public function __construct( $args = array() ) {
		if ( ! array_key_exists( 'name', $args ) || ! array_key_exists( 'uid', $args ) ) {
			return null;
		}
		$this->name   = $args['name'];
		$this->uid    = sanitize_title( $args['uid'] );
		$this->target = $args['target'];

		add_action( 'admin_init', array( $this, 'admin_init' ), 12 );

		register_setting(
			'syndication_providers',
			'syndication_links_custom_posse',
			array(
				'type'         => 'array',
				'description'  => 'Syndication Links Custom Webmention POSSE list',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name'   => array(
									'type' => 'string',
								),
								'uid'    => array(
									'type' => 'string',
								),
								'target' => array(
									'type'   => 'string',
									'format' => 'uri',
								),
							),
						),
					),
				),
				'default'      => array(),
			)
		);

		// Parent Constructor
		parent::__construct( $args );
	}

	public static function admin_init() {
		add_settings_section(
			'webmention_provider_options',
			__( 'Custom Webmention POSSE providers', 'syndication-links' ),
			array( get_called_class(), 'webmention_heading' ),
			'syndication_provider_options'
		);

		add_settings_field(
			'syndication_links_custom_posse',
			__( 'Custom Providers', 'syndication-links' ),
			array(
				get_called_class(),
				'webmention_callback',
			),
			'syndication_provider_options',
			'webmention_provider_options',
			array()
		);
	}

	public static function ifset( $array, $key ) {
		if ( array_key_exists( $key, $array ) ) {
			return $array[ $key ];
		}
		return '';
	}


	public static function webmention_heading() {
		esc_html_e( 'Set up Custom Webmention POSSE handling', 'syndication-links' );
	}

	public static function webmention_callback( $args ) {
		$name   = 'syndication_links_custom_posse';
		$custom = get_option( $name );
		foreach ( $custom as $key => $value ) {
			$custom[ $key ] = array_filter( $value );
		}
		$custom = array_filter( $custom );
		esc_html_e( 'Enter Name, UID, and Target URL for all Custom Webmention POSSE options', 'syndication-links' );
		printf( '<ul id="custom_webmention">' );
		if ( empty( $custom ) ) {
			self::webmention_inputs( '0' );

		} else {
			foreach ( $custom as $key => $value ) {
				self::webmention_inputs( $key, $value );
			}
		}
		printf( '</ul>' );
		printf( '<span class="button button-primary" id="add-custom-webmention-button">%s</span>', esc_html__( 'Add', 'syndication-links' ) );
		printf( '<span class="button button-secondary" id="delete-custom-webmention-button">%s</span>', esc_html__( 'Remove', 'syndication-links' ) );
	}

	private static function webmention_inputs( $int, $value = array() ) {
		$output = '<input type="%1$s" name="%2$s[%3$s][%4$s]" id="%5$s" value="%6$s" placeholder="%7$s" />';
		$name   = 'syndication_links_custom_posse';
		echo '<li>';
		printf( $output, 'text', $name, $int, 'name', esc_attr( $name ), esc_attr( self::ifset( $value, 'name' ) ), esc_html__( 'Name', 'syndication-links' ) ); // phpcs:ignore
		printf( $output, 'text', $name, $int, 'uid', esc_attr( $name ), esc_attr( self::ifset( $value, 'uid' ) ), esc_html__( 'UID', 'syndication-links' ) ); // phpcs:ignore
		printf( $output, 'url', $name, $int, 'target', esc_attr( $name ), esc_attr( self::ifset( $value, 'target' ) ), esc_html__( 'Target URL', 'syndication-links' ) ); // phpcs:ignore
		echo '</li>';
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
