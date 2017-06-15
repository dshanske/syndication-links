<?php

add_action( 'init', array( 'Syn_Config', 'init' ) );

class Syn_Config {
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( 'Syn_Config', 'enqueue_scripts' ) );
		add_action( 'admin_menu', array( 'Syn_Config', 'admin_menu' ), 11 );
		add_action( 'admin_init', array( 'Syn_Config', 'admin_init' ) );
		add_filter( 'the_content', array( 'Syn_Config', 'the_content' ), 30 );
		add_filter( 'the_content_feed', array( 'Syn_Config', 'the_content_feed' ), 20 );
		add_filter( 'comment_text', array( 'Syn_Config', 'comment_text' ), 20, 2 );

		// Syndication Content Options
		register_setting(
			'syndication_options',
			'syndication-links_bw',
			array(
				'type' => 'boolean',
				'description' => 'Black and White Syndication Icons',
				'show_in_rest' => true,
				'default' => false,
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_the_content',
			array(
				'type' => 'boolean',
				'description' => 'Disable Syndication Links in the Content',
				'show_in_rest' => true,
				'default' => false,
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_archives',
			array(
				'type' => 'boolean',
				'description' => 'Show on Front Page, Archive Pages, and Search Results',
				'show_in_rest' => true,
				'default' => true,
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_display',
			array(
				'type' => 'string',
				'description' => 'How to Display Icons',
				'show_in_rest' => true,
				'default' => 'icons',
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_size',
			array(
				'type' => 'string',
				'description' => 'Icon Size',
				'show_in_rest' => true,
				'default' => 'small',
			)
		);

		register_setting(
			'syndication_options',
			'syndication-links_text_before',
			array(
				'type' => 'string',
				'description' => 'Text Before Syndication Links',
				'show_in_rest' => true,
				'default' => 'Also on:',
			)
		);
	}

	public static function enqueue_scripts() {
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

	public static function admin_init() {
		add_settings_section(
			'syndication-content',
			__( 'Content Options', 'syndication-links' ),
			array( 'Syn_Config', 'options_callback' ),
			'links_options'
		);
		add_settings_field(
			'syndication-links_display',
			__( 'Display', 'syndication-links' ),
			array( 'Syn_Config', 'radio_callback' ),
			'links_options',
			'syndication-content',
			array(
				'name' => 'syndication-links_display',
				   'list' => self::display_options(),
			)
		);
		add_settings_field(
			'syndication-links_size',
			__( 'Size', 'syndication-links' ),
			array( 'Syn_Config', 'radio_callback' ),
			'links_options',
			'syndication-content',
			array(
				'name' => 'syndication-links_size',
				   'list' => self::size_options(),
			)
		);
		add_settings_field(
			'syndication-links_bw',
			__( 'Black Icons', 'syndication-links' ),
			array( 'Syn_Config', 'checkbox_callback' ),
			'links_options',
			'syndication-content',
			array(
				'name' => 'syndication-links_bw',
			)
		);
		add_settings_field(
			'syndication-links_archives',
			__( 'Show on Front Page, Archive Pages, and Search Results', 'syndication-links' ),
			array( 'Syn_Config', 'checkbox_callback' ),
			'links_options',
			'syndication-content',
			array(
				'name' => 'syndication-links_archives',
			)
		);
		add_settings_field(
			'syndication-links_text_before',
			__( 'Text Before Links', 'syndication-links' ),
			array( 'Syn_Config', 'text_callback' ),
			'links_options',
			'syndication-content',
			array(
				'name' => 'syndication-links_text_before',
			)
		);
	}

	public static function admin_menu() {
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			add_submenu_page(
				'indieweb',
				__( 'Syndication Links', 'syndication-links' ), // page title
				__( 'Syndication Links', 'syndication-links' ), // menu title
				'manage_options', // access capability
				'syndication_links',
				array( 'Syn_Config', 'links_options' )
			);
		} else {
			add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', array( 'Syn_Config', 'links_options' ) );
		}
	}

	public static function options_callback() {
		esc_html_e( 'Options for Presenting Syndication Links in Posts.', 'syndication-links' );
		echo '<p>';
		esc_html_e( 'Syndication Links by default will add links to the content. You can disable this for theme support.', 'syndication-links' );
		echo '</p>';
	}

	public static function checkbox_callback(array $args) {
		$name = $args['name'];
		 $checked = get_option( $args['name'] );
		echo "<input name='" . $name . "' type='hidden' value='0' />";
		echo "<input name='" . $name . "' type='checkbox' value='1' " . checked( 1, $checked, false ) . ' /> ';
	}

	public static function select_callback( array $args ) {
		$name = $args['name'];
		$select = get_option( $name );
		$options = $args['list'];
		echo "<select name='" . $name . "id='" . $name . "'>";
		foreach ( $options as $key => $value ) {
			echo '<option value="' . $key . '" ' . ( $select === $key ? 'selected>' : '>' ) . $value . '</option>';
		}
		echo '</select>';
	}

	public static function radio_callback( array $args ) {
		$name = $args['name'];
		$select = get_option( $name );
		$options = $args['list'];
		echo '<fieldset>';
		foreach ( $options as $key => $value ) {
			echo '<input type="radio" name="' . $name . '" id="' . $name . '" value="' . $key . '" ' . checked( $key, $select, false ) . ' />';
			echo '<label for="' . $args['name'] . '">' . $value . '</label>';
			echo '<br />';
		}
		echo '</fieldset>';
	}


	public static function text_callback( $args ) {
		$name = $args['name'];
		echo "<input name='" . $name . "' type='text' value='" . get_option( $name ) . "' /> ";
	}

	public static function display_options() {
		return array(
			'icons' => _x( 'Icons Only', 'syndication-links' ),
			'text' => _x( 'Text Only', 'syndication-links' ),
			'iconstext'  => _x( 'Icons and Text', 'syndication-links' ),
			'hidden' => _x( 'Hidden Links', 'syndication-links' ),
		);
	}

	public static function size_options() {
		return array(
			'small' => _x( 'Small', 'syndication-links' ),
			'medium' => _x( 'Medium', 'syndication-links' ),
			'large'  => _x( 'Large', 'syndication-links' ),
		);
	}


	public static function links_options() {
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Syndication Links', 'syndication-links' ) . '</h2>';
		echo '<p>';
		esc_html_e( 'Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites. You can either manually add these to the box in the post editor or automatically. Several plugins support or are supported.', 'syndication-links' );
		echo '</p><hr />';
		echo '<form method="post" action="options.php">';
		settings_fields( 'syndication_options' );
		do_settings_sections( 'links_options' );
		submit_button();
		echo '</form></div>';
	}

	public static function the_content( $content ) {
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
			if ( 'the_content' == $filter ) {
				if ( $done ) {
					return $content;
				} else { $done = true;
				}
			}
		}
		return $content . get_post_syndication_links();
	}

	public static function comment_text( $comment_text, $comment ) {
		if ( ! is_admin() ) {
			return $comment_text . '<p>' . get_comment_syndication_links( $comment ) . '</p>';
		}
		return $comment_text;
	}

	public static function the_content_feed( $content ) {
		$post_ID = get_the_ID();
		if ( ! $post_ID ) {
			return $content;
		}
		if ( ( is_admin() ) && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $content;
		}
		$args = array(
			'style' => 'p',
			'icons' => false,
			'text' => true,
		);
		return $content . get_post_syndication_links( $post_ID, $args );
	}



} // End Class

?>
