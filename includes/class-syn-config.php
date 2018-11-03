<?php

class Syn_Config {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 11 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'the_content', array( $this, 'the_content' ), 30 );
		if ( get_option( 'syndication-links_feed' ) ) {
			add_filter( 'the_content_feed', array( $this, 'the_content_feed' ), 20, 2 );
		}
		add_filter( 'comment_text', array( $this, 'comment_text' ), 20, 2 );
		add_filter( 'json_feed_item', array( $this, 'json_feed_item' ), 10, 2 );

		// Syndication Content Options
		register_setting(
			'syndication_options',
			'syndication-links_bw',
			array(
				'type'         => 'boolean',
				'description'  => 'Black and White Syndication Icons',
				'show_in_rest' => true,
				'default'      => false,
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_the_content',
			array(
				'type'         => 'boolean',
				'description'  => 'Disable Syndication Links in the Content',
				'show_in_rest' => true,
				'default'      => false,
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_archives',
			array(
				'type'         => 'boolean',
				'description'  => 'Show on Front Page, Archive Pages, and Search Results',
				'show_in_rest' => true,
				'default'      => true,
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_feed',
			array(
				'type'         => 'boolean',
				'description'  => 'Show on Feed',
				'show_in_rest' => true,
				'default'      => true,
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_display',
			array(
				'type'         => 'string',
				'description'  => 'How to Display Icons',
				'show_in_rest' => true,
				'default'      => 'icons',
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_size',
			array(
				'type'         => 'string',
				'description'  => 'Icon Size',
				'show_in_rest' => true,
				'default'      => 'small',
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_text_before',
			array(
				'type'         => 'string',
				'description'  => 'Text Before Syndication Links',
				'show_in_rest' => true,
				'default'      => 'Also on:',
			)
		);

		// Syndication Links POSSE/Syndication Options
		register_setting(
			'syndication_options',
			'syndication_posse_enable',
			array(
				'type'         => 'number',
				'description'  => 'Enable Syndication via Syndication Links',
				'show_in_rest' => true,
				'default'      => '0',
			)
		);

	}

	public function enqueue_scripts() {
		$size = get_option( 'syndication-links_size' );
		if ( '1' === get_option( 'syndication-links_bw' ) ) {
			switch ( $size ) {
				case 'large':
					$css = 'css/syn-bw-large.min.css';
					break;
				case 'medium':
					$css = 'css/syn-bw-medium.min.css';
					break;
				default:
					$css = 'css/syn-bw.min.css';
			}
		} else {
			switch ( $size ) {
				case 'large':
					$css = 'css/syn-large.min.css';
					break;
				case 'medium':
					$css = 'css/syn-medium.min.css';
					break;
				default:
					$css = 'css/syn.min.css';
			}
		}
		wp_enqueue_style( 'syndication-style', plugin_dir_url( dirname( __FILE__ ) ) . $css, array(), SYNDICATION_LINKS_VERSION );
	}

	public function admin_init() {
		add_settings_section(
			'syndication_content',
			__( 'Content Options', 'syndication-links' ),
			array( $this, 'options_callback' ),
			'links_options'
		);
		add_settings_field(
			'syndication-links_display',
			__( 'Display', 'syndication-links' ),
			array( $this, 'radio_callback' ),
			'links_options',
			'syndication_content',
			array(
				'name' => 'syndication-links_display',
				'list' => self::display_options(),
			)
		);
		add_settings_field(
			'syndication-links_size',
			__( 'Size', 'syndication-links' ),
			array( $this, 'radio_callback' ),
			'links_options',
			'syndication_content',
			array(
				'name' => 'syndication-links_size',
				'list' => self::size_options(),
			)
		);
		add_settings_field(
			'syndication-links_bw',
			__( 'Black Icons', 'syndication-links' ),
			array( $this, 'checkbox_callback' ),
			'links_options',
			'syndication_content',
			array(
				'name' => 'syndication-links_bw',
			)
		);
		add_settings_field(
			'syndication-links_archives',
			__( 'Show on Front Page, Archive Pages, and Search Results', 'syndication-links' ),
			array( $this, 'checkbox_callback' ),
			'links_options',
			'syndication_content',
			array(
				'name' => 'syndication-links_archives',
			)
		);
		add_settings_field(
			'syndication-links_feed',
			__( 'Show on Feed', 'syndication-links' ),
			array( $this, 'checkbox_callback' ),
			'links_options',
			'syndication_content',
			array(
				'name' => 'syndication-links_feed',
			)
		);
		add_settings_field(
			'syndication-links_text_before',
			__( 'Text Before Links', 'syndication-links' ),
			array( $this, 'text_callback' ),
			'links_options',
			'syndication_content',
			array(
				'name' => 'syndication-links_text_before',
			)
		);

		add_settings_section(
			'syndication_posse_options',
			__( 'Syndication/POSSE Options', 'syndication-links' ),
			array( $this, 'posse_options_callback' ),
			'links_options'
		);
		add_settings_field(
			'syndication_posse_enable',
			__( 'Enable Syndication to Other Sites', 'syndication-links' ),
			array( $this, 'checkbox_callback' ),
			'links_options',
			'syndication_posse_options',
			array(
				'name' => 'syndication_posse_enable',
			)
		);

	}

	public function admin_menu() {
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			add_submenu_page(
				'indieweb',
				__( 'Syndication Links', 'syndication-links' ), // page title
				__( 'Syndication Links', 'syndication-links' ), // menu title
				'manage_options', // access capability
				'syndication_links',
				array( $this, 'links_options' )
			);
		} else {
			add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', array( $this, 'links_options' ) );
		}
	}

	public function options_callback() {
		esc_html_e( 'Options for Presenting Syndication Links in Posts.', 'syndication-links' );
		echo '<p>';
		esc_html_e( 'Syndication Links by default will add links to the content. You can disable this for theme support.', 'syndication-links' );
		echo '</p>';
	}

	public function posse_options_callback() {
		esc_html_e( 'Options for Syndicating to Other Sites', 'syndication-links' );
		echo '<p>';
		esc_html_e( 'Syndication Links offers an interface to syndicate your content to other sites via the Post Editor or Micropub', 'syndication-links' );
		echo '</p>';
	}


	public function checkbox_callback( array $args ) {
		$name    = $args['name'];
		$checked = get_option( $args['name'] );
		echo "<input name='" . esc_attr( $name ) . "' type='hidden' value='0' />";
		echo "<input name='" . esc_attr( $name ) . "' type='checkbox' value='1' " . checked( 1, $checked, false ) . ' /> ';
	}

	public function select_callback( array $args ) {
		$name    = $args['name'];
		$select  = get_option( $name );
		$options = $args['list'];
		echo "<select name='" . esc_attr( $name ) . "id='" . esc_attr( $name ) . "'>";
		foreach ( $options as $key => $value ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . ( $select === $key ? 'selected>' : '>' ) . esc_attr( $value ) . '</option>';
		}
		echo '</select>';
	}

	public function radio_callback( array $args ) {
		$name    = $args['name'];
		$select  = get_option( $name );
		$options = $args['list'];
		echo '<fieldset>';
		foreach ( $options as $key => $value ) {
			echo '<input type="radio" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $key ) . '" ' . checked( $key, $select, false ) . ' />';
			echo '<label for="' . esc_attr( $args['name'] ) . '">' . esc_attr( $value ) . '</label>';
			echo '<br />';
		}
		echo '</fieldset>';
	}


	public function text_callback( $args ) {
		$name = $args['name'];
		echo "<input name='" . esc_attr( $name ) . "' type='text' value='" . esc_attr( get_option( $name ) ) . "' /> ";
	}

	public function display_options() {
		return array(
			'icons'     => __( 'Icons Only', 'syndication-links' ),
			'text'      => __( 'Text Only', 'syndication-links' ),
			'iconstext' => __( 'Icons and Text', 'syndication-links' ),
			'hidden'    => __( 'Hidden Links', 'syndication-links' ),
		);
	}

	public function size_options() {
		return array(
			'small'  => __( 'Small', 'syndication-links' ),
			'medium' => __( 'Medium', 'syndication-links' ),
			'large'  => __( 'Large', 'syndication-links' ),
		);
	}


	public function links_options() {
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Syndication Links', 'syndication-links' ) . '</h2>';
		echo '<p>';
		esc_html_e( 'Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites. You can either manually add these to the box in the post editor or automatically. Several plugins support or are supported.', 'syndication-links' );
		echo '</p><hr />';
		echo '<form method="post" action="options.php">';
		settings_fields( 'syndication_options' );
		do_settings_sections( 'links_options' );
		submit_button();
		echo '</form></div>';
	}

	public function the_content( $content ) {
		global $wp_current_filter;
		$post = get_post();
		if ( empty( $post ) ) {
			return $content;
		}
		if ( ( is_admin() ) && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $content;
		}

		if ( is_feed() ) {
			return $content;
		}

		// Don't allow to be added to the_content more than once (prevent infinite loops)
		$done = false;
		foreach ( $wp_current_filter as $filter ) {
			if ( 'the_content' === $filter ) {
				if ( $done ) {
					return $content;
				} else {
					$done = true;
				}
			}
		}
		return $content . get_post_syndication_links();
	}

	public function comment_text( $comment_text, $comment ) {
		if ( ! is_admin() ) {
			return $comment_text . '<p>' . get_comment_syndication_links( $comment ) . '</p>';
		}
		return $comment_text;
	}

	public function the_content_feed( $content, $feed_type ) {
		$post_ID = get_the_ID();
		if ( ! $post_ID ) {
			return $content;
		}
		if ( 'json' === $feed_type ) {
			return $content;
		}

		if ( ( is_admin() ) && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $content;
		}
		$args = array(
			'style' => 'p',
			'icons' => false,
			'text'  => true,
		);
		return $content . get_post_syndication_links( $post_ID, $args );
	}

	public function json_feed_item( $feed_item, $post ) {
		$syn = get_syndication_links_data( $post );
		if ( $syn ) {
			$feed_item['syndication'] = get_syndication_links_data( $post );
		}
		return $feed_item;
	}



} // End Class

new Syn_Config();
