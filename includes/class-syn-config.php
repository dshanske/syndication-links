<?php

add_action( 'init', array( 'Syn_Config', 'init' ) );
add_action( 'admin_init', array( 'Syn_Config', 'admin_init' ) );
add_action( 'admin_menu', array( 'Syn_Config', 'admin_menu' ), 11 );

class Syn_Config {
	public static function init() {
		$cls = get_called_class();
		add_action( 'wp_enqueue_scripts', array( $cls, 'enqueue_scripts' ) );

		if ( apply_filters( 'syndication_links_display', true ) ) {
			add_filter( 'the_content', array( $cls, 'the_content' ), 30 );
		}

		if ( get_option( 'syndication-links_feed' ) ) {
			add_filter( 'the_content_feed', array( $cls, 'the_content_feed' ), 20, 2 );
		}
		add_filter( 'comment_text', array( $cls, 'comment_text' ), 20, 2 );
		add_filter( 'json_feed_item', array( $cls, 'json_feed_item' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $cls, 'admin_enqueue' ) );

		// Syndication Content Options
		register_setting(
			'syndication_display',
			'syndication-links_bw',
			array(
				'type'         => 'boolean',
				'description'  => 'Black and White Syndication Icons',
				'show_in_rest' => true,
				'default'      => false,
			)
		);

		register_setting(
			'syndication_display',
			'syndication-links_the_content',
			array(
				'type'         => 'boolean',
				'description'  => 'Disable Syndication Links in the Content',
				'show_in_rest' => true,
				'default'      => false,
			)
		);

		register_setting(
			'syndication_display',
			'syndication-links_archives',
			array(
				'type'         => 'boolean',
				'description'  => 'Show on Front Page, Archive Pages, and Search Results',
				'show_in_rest' => true,
				'default'      => true,
			)
		);

		register_setting(
			'syndication_display',
			'syndication-links_feed',
			array(
				'type'         => 'boolean',
				'description'  => 'Show on Feed',
				'show_in_rest' => true,
				'default'      => true,
			)
		);

		register_setting(
			'syndication_display',
			'syndication-links_display',
			array(
				'type'         => 'string',
				'description'  => 'How to Display Icons',
				'show_in_rest' => true,
				'default'      => 'icons',
			)
		);

		register_setting(
			'syndication_display',
			'syndication-links_size',
			array(
				'type'         => 'string',
				'description'  => 'Icon Size',
				'show_in_rest' => true,
				'default'      => 'small',
			)
		);

		register_setting(
			'syndication_display',
			'syndication-links_text_before',
			array(
				'type'         => 'string',
				'description'  => 'Text Before Syndication Links',
				'show_in_rest' => true,
				'default'      => 'Also on:',
			)
		);

		register_setting(
			'syndication_display',
			'syndication_post_types',
			array(
				'type'         => 'array',
				'description'  => 'Supported Post Types for Syndication',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'default'      => array( 'post', 'page' ),
			)
		);

		// Syndication Links POSSE/Syndication Options
		register_setting(
			'syndication_providers',
			'syndication_posse_enable',
			array(
				'type'         => 'number',
				'description'  => 'Enable Syndication via Syndication Links',
				'show_in_rest' => true,
				'default'      => '0',
			)
		);
		register_setting(
			'syndication_providers',
			'syndication_wp_cron',
			array(
				'type'         => 'number',
				'description'  => 'Syndicate in the Background',
				'show_in_rest' => true,
				'default'      => '1',
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
		wp_enqueue_style( 'syndication-style', plugin_dir_url( __DIR__ ) . $css, array(), SYNDICATION_LINKS_VERSION );
	}


	public static function admin_enqueue( $hook_suffix ) {
		$hooks = array( 'indieweb_page_syndication_links' );
		if ( in_array( $hook_suffix, $hooks, true ) ) {
			wp_enqueue_style(
				'syn_admin',
				plugins_url( 'css/syn-admin.min.css', __DIR__ ),
				array(),
				SYNDICATION_LINKS_VERSION
			);
			wp_enqueue_script(
				'syndication_links_password',
				plugins_url( 'js/password.js', __DIR__ ),
				array(),
				SYNDICATION_LINKS_VERSION,
				true
			);
		}
	}

	public static function admin_init() {
		add_settings_section(
			'syndication_display',
			__( 'Content Options', 'syndication-links' ),
			array( __CLASS__, 'options_callback' ),
			'syndication_display_options'
		);
		add_settings_field(
			'syndication-links_display',
			__( 'Display', 'syndication-links' ),
			array( __CLASS__, 'radio_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => 'syndication-links_display',
				'list'      => self::display_options(),
			)
		);
		add_settings_field(
			'syndication-links_size',
			__( 'Size', 'syndication-links' ),
			array( __CLASS__, 'radio_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => 'syndication-links_size',
				'list'      => self::size_options(),
			)
		);
		add_settings_field(
			'syndication-links_bw',
			__( 'Black Icons', 'syndication-links' ),
			array( __CLASS__, 'checkbox_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => 'syndication-links_bw',
			)
		);
		add_settings_field(
			'syndication-links_archives',
			__( 'Show on Front Page, Archive Pages, and Search Results', 'syndication-links' ),
			array( __CLASS__, 'checkbox_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => 'syndication-links_archives',
			)
		);
		add_settings_field(
			'syndication-links_feed',
			__( 'Show on Feed', 'syndication-links' ),
			array( __CLASS__, 'checkbox_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => 'syndication-links_feed',
			)
		);
		add_settings_field(
			'syndication-links_text_before',
			__( 'Text Before Links', 'syndication-links' ),
			array( __CLASS__, 'text_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => 'syndication-links_text_before',
			)
		);

		add_settings_field(
			'syndication_post_types',
			__( 'Other Post Types to Offer Syndication For', 'syndication-links' ),
			array( __CLASS__, 'post_type_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => 'syndication_post_types',
			)
		);

		add_settings_section(
			'syndication_providers',
			__( 'Syndication/POSSE Options', 'syndication-links' ),
			array( __CLASS__, 'posse_options_callback' ),
			'syndication_provider_options'
		);
		add_settings_field(
			'syndication_posse_enable',
			__( 'Enable Syndication to Other Sites', 'syndication-links' ),
			array( __CLASS__, 'checkbox_callback' ),
			'syndication_provider_options',
			'syndication_providers',
			array(
				'label_for' => 'syndication_posse_enable',
			)
		);
		add_settings_field(
			'syndication_wp_cron',
			__( 'Syndication Runs in the Background', 'syndication-links' ),
			array( __CLASS__, 'checkbox_callback' ),
			'syndication_provider_options',
			'syndication_providers',
			array(
				'label_for' => 'syndication_wp_cron',
			)
		);
	}

	public static function admin_menu() {
		$cls = get_called_class();
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			add_submenu_page(
				'indieweb',
				__( 'Syndication Links', 'syndication-links' ), // page title
				__( 'Syndication Links', 'syndication-links' ), // menu title
				'manage_options', // access capability
				'syndication_links',
				array( $cls, 'links_options' )
			);
		} else {
			add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', array( $cls, 'links_options' ) );
		}
	}

	public static function options_callback() {
		esc_html_e( 'Options for Presenting Syndication Links in Posts.', 'syndication-links' );
		echo '<p>';
		esc_html_e( 'Syndication Links by default will add links to the content. You can disable this for theme support.', 'syndication-links' );
		echo '</p>';
	}

	public static function posse_options_callback() {
		esc_html_e( 'Options for Syndicating to Other Sites', 'syndication-links' );
		echo '<p>';
		esc_html_e( 'Syndication Links offers an interface to syndicate your content to other sites via the Post Editor or Micropub', 'syndication-links' );
		echo '</p>';
	}


	public static function checkbox_callback( array $args ) {
		$name    = $args['label_for'];
		$checked = get_option( $args['label_for'] );
		echo "<input name='" . esc_attr( $name ) . "' type='hidden' value='0' />";
		echo "<input name='" . esc_attr( $name ) . "' type='checkbox' value='1' " . checked( 1, $checked, false ) . ' /> ';
	}

	public static function select_callback( array $args ) {
		$name    = $args['label_for'];
		$select  = get_option( $name );
		$options = $args['list'];
		echo "<select name='" . esc_attr( $name ) . "' id='" . esc_attr( $name ) . "'>";
		foreach ( $options as $key => $value ) {
			echo '<option value="' . esc_attr( $key ) . '"' . ( $select === $key ? ' selected>' : '>' ) . esc_attr( $value ) . '</option>';
		}
		echo '</select>';
	}

	public static function radio_callback( array $args ) {
		$name    = $args['label_for'];
		$select  = get_option( $name );
		$options = $args['list'];
		echo '<fieldset>';
		foreach ( $options as $key => $value ) {
			echo '<input type="radio" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '-' . esc_attr( $key ) . '" value="' . esc_attr( $key ) . '" ' . checked( $key, $select, false ) . ' />';
			echo '<label for="' . esc_attr( $name ) . '-' . esc_attr( $key ) . '">' . esc_attr( $value ) . '</label>';
			echo '<br />';
		}
		echo '</fieldset>';
	}

	public static function post_type_callback( array $args ) {
		echo '<ul>';
		$option = get_option( $args['label_for'] );
		if ( ! is_array( $option ) ) {
			$option = array();
		}
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( 'post' === $post_type->name ) {
				continue;
			}
			?>
			<li><input name='<?php echo esc_attr( $args['label_for'] ); ?>[]' type='checkbox' value='<?php echo esc_attr( $post_type->name ); ?>' <?php checked( in_array( $post_type->name, $option ), true ); ?>/> 
			<label for="<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->label ); ?></label>
			</li> 
			<?php
		}
		echo '</ul>';
	}

	public static function text_callback( $args ) {
		$name = $args['label_for'];
		if ( ! isset( $args['type'] ) ) {
			$args['type'] = 'text';
		}
		$value = get_option( $name );
		if ( 'password' === $args['type'] ) {
			printf( '<input name="%1$s" id="%1$s" size="50" autocomplete="off" class="regular-text" type="password" autocomplete="off" data-lpignore="true" autofill="off" value="%2$s"/>', esc_attr( $name ), esc_attr( $value ) );
		} else {
			printf( '<input name="%1$s" id="%1$s" size="50" autocomplete="off" class="regular-text" type="%2$s" value="%3$s" />', esc_attr( $name ), esc_attr( $args['type'] ), esc_attr( $value ) );
		}
	}

	public static function display_options() {
		return array(
			'icons'     => __( 'Icons Only', 'syndication-links' ),
			'text'      => __( 'Text Only', 'syndication-links' ),
			'iconstext' => __( 'Icons and Text', 'syndication-links' ),
			'hidden'    => __( 'Hidden Links', 'syndication-links' ),
		);
	}

	public static function size_options() {
		return array(
			'small'  => __( 'Small', 'syndication-links' ),
			'medium' => __( 'Medium', 'syndication-links' ),
			'large'  => __( 'Large', 'syndication-links' ),
		);
	}


	/**
	 * Echoes link for tab on page
	 *
	 * @param string $tab The id of the tab.
	 * @param string $name The label of the tab.
	 * @param string $active Which tab is active.
	*/
	public static function tab_link( $tab, $name, $active = 'display' ) {
		$url    = add_query_arg( 'tab', $tab, menu_page_url( 'syndication_links', false ) );
		$active = ( $active === $tab ) ? ' nav-tab-active' : '';
		printf( '<a href="%1$s" class="nav-tab%2$s">%3$s</a>', esc_url( $url ), esc_attr( $active ), esc_html( $name ) );
	}

	public static function links_options() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap">
			<h2> <?php esc_html_e( 'Syndication Links', 'syndication-links' ); ?></h2>
		<p><?php esc_html_e( 'Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites. You can either manually add these to the box in the post editor or automatically. Several plugins support or are supported.', 'syndication-links' ); ?></p>

		<h2 class="nav-tab-wrapper">
			<?php self::tab_link( 'general', __( 'General', 'syndication-links' ), $active_tab ); ?>
			<?php self::tab_link( 'providers', __( 'Providers', 'syndication-links' ), $active_tab ); ?>
			<?php self::tab_link( 'apis', __( 'API Keys', 'syndication-links' ), $active_tab ); ?>
		</h2>
		<hr />
		<form method="post" action="options.php">
			<?php
			switch ( $active_tab ) {
				case 'general':
					settings_fields( 'syndication_display' );
					do_settings_sections( 'syndication_display_options' );
					break;
				case 'providers':
					settings_fields( 'syndication_providers' );
					do_settings_sections( 'syndication_provider_options' );
					break;
				case 'apis':
					settings_fields( 'syndication_apis' );
					do_settings_sections( 'syndication_api_keys' );
					break;

			}
				submit_button();
			?>
		</form>
		</div>
		<?php
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
			if ( 'the_content' === $filter ) {
				if ( $done ) {
					return $content;
				} else {
					$done = true;
				}
			}
		}
		return $content . '<div class="syndication-links">' . get_post_syndication_links() . '</div>';
	}

	public static function comment_text( $comment_text, $comment ) {
		if ( ! is_admin() ) {
			return $comment_text . '<p>' . get_comment_syndication_links( $comment ) . '</p>';
		}
		return $comment_text;
	}

	public static function the_content_feed( $content, $feed_type ) {
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

	public static function json_feed_item( $feed_item, $post ) {
		$syn = get_post_syndication_links_data( $post );
		if ( $syn ) {
			$feed_item['syndication'] = get_post_syndication_links_data( $post );
		}
		return $feed_item;
	}
} // End Class
