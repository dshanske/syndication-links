<?php
/**
 * Settings page and front-end output.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Syn_Config', 'init' ) );
add_action( 'admin_init', array( 'Syn_Config', 'admin_init' ) );
add_action( 'admin_menu', array( 'Syn_Config', 'admin_menu' ), 11 );

/**
 * Registers the settings and their page, and adds the links to content, comments and feeds.
 */
class Syn_Config {
	/**
	 * Registers the settings, and hooks the front-end output.
	 */
	public static function init() {
		$cls = get_called_class();
		add_action( 'wp_enqueue_scripts', array( $cls, 'enqueue_scripts' ) );

		// Always hooked: whether to add the links is decided when they'd be added (see auto_display()),
		// so a theme's add_theme_support() or filter works whenever it's added.
		add_filter( 'the_content', array( $cls, 'the_content' ), 30 );

		if ( get_option( 'syndication-links_feed' ) ) {
			add_filter( 'the_content_feed', array( $cls, 'the_content_feed' ), 20, 2 );
		}
		add_filter( 'comment_text', array( $cls, 'comment_text' ), 20, 2 );
		add_filter( 'json_feed_item', array( $cls, 'json_feed_item' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $cls, 'admin_enqueue' ) );

		// Syndication Content Options.
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

		// Syndication Links POSSE/Syndication Options.
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

	/**
	 * Enqueues the front-end stylesheet for the chosen icon size and colours.
	 */
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

	/**
	 * Enqueues the settings page's styles and scripts.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public static function admin_enqueue( $hook_suffix ) {
		// The settings page is under the IndieWeb menu if that plugin is active, otherwise under Settings.
		$hooks = array( 'indieweb_page_syndication_links', 'settings_page_syndication_links' );
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
			// The Add and Remove buttons for custom providers.
			wp_enqueue_script(
				'synlinks',
				plugins_url( 'js/synlinks.js', __DIR__ ),
				array( 'jquery' ),
				SYNDICATION_LINKS_VERSION,
				true
			);
		}
	}

	/**
	 * Adds the settings sections and fields.
	 */
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

	/**
	 * Adds the settings page: under the IndieWeb menu if that plugin is active, otherwise under Settings.
	 */
	public static function admin_menu() {
		$cls = get_called_class();
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			add_submenu_page(
				'indieweb',
				__( 'Syndication Links', 'syndication-links' ), // page title.
				__( 'Syndication Links', 'syndication-links' ), // menu title.
				'manage_options', // access capability.
				'syndication_links',
				array( $cls, 'links_options' )
			);
		} else {
			add_options_page(
				__( 'Syndication Links', 'syndication-links' ), // page title.
				__( 'Syndication Links', 'syndication-links' ), // menu title.
				'manage_options',
				'syndication_links',
				array( $cls, 'links_options' )
			);
		}
	}

	/**
	 * Displays the description of the content options section.
	 */
	public static function options_callback() {
		esc_html_e( 'Options for Presenting Syndication Links in Posts.', 'syndication-links' );
		echo '<p>';
		if ( self::theme_handles( 'content' ) || self::theme_handles( 'comments' ) ) {
			esc_html_e( 'Your theme places the syndication links itself. The options below still set how they look.', 'syndication-links' );
		} else {
			esc_html_e( 'Syndication Links adds the links after the content and after each comment. A theme can place them itself instead; see the FAQ.', 'syndication-links' );
		}
		echo '</p>';
	}

	/**
	 * Whether the theme places the links itself, with add_theme_support( 'syndication-links' ).
	 *
	 * A theme can list what it places: add_theme_support( 'syndication-links', array( 'content' ) ).
	 * Without a list, it places both the post's and the comments' links.
	 *
	 * @since 5.0.0
	 *
	 * @param string $placement 'content' or 'comments'.
	 * @return bool
	 */
	public static function theme_handles( $placement ) {
		$support = get_theme_support( 'syndication-links' );
		if ( ! $support ) {
			return false;
		}
		// Declared with a list: get_theme_support() returns the arguments, wrapped in an array.
		if ( is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ) {
			return in_array( $placement, $support[0], true );
		}
		return true;
	}

	/**
	 * Whether the plugin adds the links to a placement itself.
	 *
	 * Not when the theme declares it places them, or a filter turns it off. Checked when
	 * the links would be added, so it doesn't matter when the theme does either.
	 *
	 * @since 5.0.0
	 *
	 * @param string $placement 'content' or 'comments'.
	 * @return bool
	 */
	public static function auto_display( $placement ) {
		if ( self::theme_handles( $placement ) ) {
			return false;
		}
		if ( 'comments' === $placement ) {
			/**
			 * Filters whether the links are added after each comment's text automatically.
			 *
			 * Return false when the theme displays them itself. A theme can also declare
			 * add_theme_support( 'syndication-links' ).
			 *
			 * @since 5.0.0
			 *
			 * @param bool $display Whether to add the links to comments. Default true.
			 */
			return (bool) apply_filters( 'syndication_links_comment_display', true );
		}
		/**
		 * Filters whether the links are added to the post content automatically.
		 *
		 * Return false when the theme displays them itself. A theme can also declare
		 * add_theme_support( 'syndication-links' ).
		 *
		 * @since 5.0.0 Checked when the content is displayed, rather than once on `init`.
		 *
		 * @param bool $display Whether to add the links to the content. Default true.
		 */
		return (bool) apply_filters( 'syndication_links_display', true );
	}

	/**
	 * Displays the description of the syndication options section.
	 */
	public static function posse_options_callback() {
		esc_html_e( 'Options for Syndicating to Other Sites', 'syndication-links' );
		echo '<p>';
		esc_html_e( 'Syndication Links offers an interface to syndicate your content to other sites via the Post Editor or Micropub', 'syndication-links' );
		echo '</p>';
	}

	/**
	 * Displays a checkbox setting.
	 *
	 * A hidden field submits 0 when the box is unchecked.
	 *
	 * @param array $args {
	 *     The field's arguments.
	 *
	 *     @type string $label_for The option name.
	 * }
	 */
	public static function checkbox_callback( array $args ) {
		$name    = $args['label_for'];
		$checked = get_option( $args['label_for'] );
		echo "<input name='" . esc_attr( $name ) . "' type='hidden' value='0' />";
		echo "<input name='" . esc_attr( $name ) . "' id='" . esc_attr( $name ) . "' type='checkbox' value='1' " . checked( 1, $checked, false ) . ' /> ';
	}

	/**
	 * Displays a dropdown setting.
	 *
	 * @param array $args {
	 *     The field's arguments.
	 *
	 *     @type string $label_for The option name.
	 *     @type array  $list      Labels, keyed by value.
	 * }
	 */
	public static function select_callback( array $args ) {
		$name    = $args['label_for'];
		$select  = get_option( $name );
		$options = $args['list'];
		echo "<select name='" . esc_attr( $name ) . "' id='" . esc_attr( $name ) . "'>";
		foreach ( $options as $key => $value ) {
			echo '<option value="' . esc_attr( $key ) . '"' . ( $select === $key ? ' selected>' : '>' ) . esc_html( $value ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Displays a setting as radio buttons.
	 *
	 * @param array $args {
	 *     The field's arguments.
	 *
	 *     @type string $label_for The option name.
	 *     @type array  $list      Labels, keyed by value.
	 * }
	 */
	public static function radio_callback( array $args ) {
		$name    = $args['label_for'];
		$select  = get_option( $name );
		$options = $args['list'];
		echo '<fieldset>';
		foreach ( $options as $key => $value ) {
			echo '<input type="radio" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '-' . esc_attr( $key ) . '" value="' . esc_attr( $key ) . '" ' . checked( $key, $select, false ) . ' />';
			echo '<label for="' . esc_attr( $name ) . '-' . esc_attr( $key ) . '">' . esc_html( $value ) . '</label>';
			echo '<br />';
		}
		echo '</fieldset>';
	}

	/**
	 * Displays a checkbox for each public post type other than `post`, which always has links.
	 *
	 * @param array $args {
	 *     The field's arguments.
	 *
	 *     @type string $label_for The option name.
	 * }
	 */
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
			<li><input name='<?php echo esc_attr( $args['label_for'] ); ?>[]' id='<?php echo esc_attr( $args['label_for'] . '-' . $post_type->name ); ?>' type='checkbox' value='<?php echo esc_attr( $post_type->name ); ?>' <?php checked( in_array( $post_type->name, $option, true ), true ); ?>/> 
			<label for="<?php echo esc_attr( $args['label_for'] . '-' . $post_type->name ); ?>"><?php echo esc_html( $post_type->label ); ?></label>
			</li> 
			<?php
		}
		echo '</ul>';
	}

	/**
	 * Displays a text setting.
	 *
	 * @param array $args {
	 *     The field's arguments.
	 *
	 *     @type string $label_for The option name.
	 *     @type string $type      Optional. Input type, such as 'text', 'url' or 'password'. Default 'text'.
	 * }
	 */
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

	/**
	 * Returns the display modes.
	 *
	 * @return string[] Labels, keyed by mode.
	 */
	public static function display_options() {
		return array(
			'icons'     => __( 'Icons Only', 'syndication-links' ),
			'text'      => __( 'Text Only', 'syndication-links' ),
			'iconstext' => __( 'Icons and Text', 'syndication-links' ),
			'hidden'    => __( 'Hidden Links', 'syndication-links' ),
		);
	}

	/**
	 * Returns the icon sizes.
	 *
	 * @return string[] Labels, keyed by size.
	 */
	public static function size_options() {
		return array(
			'small'  => __( 'Small', 'syndication-links' ),
			'medium' => __( 'Medium', 'syndication-links' ),
			'large'  => __( 'Large', 'syndication-links' ),
		);
	}

	/**
	 * Displays a tab of the settings page.
	 *
	 * @param string $tab    The tab's ID.
	 * @param string $name   The tab's label.
	 * @param string $active Optional. The active tab's ID. Default 'display'.
	 */
	public static function tab_link( $tab, $name, $active = 'display' ) {
		$url    = add_query_arg( 'tab', $tab, menu_page_url( 'syndication_links', false ) );
		$active = ( $active === $tab ) ? ' nav-tab-active' : '';
		printf( '<a href="%1$s" class="nav-tab%2$s">%3$s</a>', esc_url( $url ), esc_attr( $active ), esc_html( $name ) );
	}

	/**
	 * Displays the settings page.
	 */
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
			<?php self::tab_link( 'import', __( 'Import', 'syndication-links' ), $active_tab ); ?>
		</h2>
		<hr />
		<?php
		// The import tab has its own forms, which post to admin-post.php.
		if ( 'import' === $active_tab ) {
			Syn_Plugin_Import::render();
			echo '</div>';
			return;
		}
		?>
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

	/**
	 * Adds the post's links after its content.
	 *
	 * Not when the theme places them (see auto_display()), in the admin, in feeds (see
	 * the_content_feed()) or in nested `the_content` calls.
	 *
	 * @param string $content The content.
	 * @return string The content.
	 */
	public static function the_content( $content ) {
		global $wp_current_filter;
		$post = get_post();
		if ( empty( $post ) || ! self::auto_display( 'content' ) ) {
			return $content;
		}
		if ( ( is_admin() ) && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $content;
		}

		if ( is_feed() ) {
			return $content;
		}

		// Don't allow to be added to the_content more than once (prevent infinite loops).
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

	/**
	 * Adds the comment's links after its text, outside the admin, unless the theme places them.
	 *
	 * @param string     $comment_text The comment text.
	 * @param WP_Comment $comment      The comment.
	 * @return string The comment text.
	 */
	public static function comment_text( $comment_text, $comment ) {
		if ( ! is_admin() && self::auto_display( 'comments' ) ) {
			return $comment_text . '<p>' . get_comment_syndication_links( $comment ) . '</p>';
		}
		return $comment_text;
	}

	/**
	 * Adds the post's links, as text, after its content in feeds.
	 *
	 * Only hooked when the feed setting is on. JSON Feed gets them as a field instead.
	 *
	 * @param string $content   The content.
	 * @param string $feed_type The feed type.
	 * @return string The content.
	 */
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

	/**
	 * Adds the post's links to its JSON Feed item, as `syndication`.
	 *
	 * @param array   $feed_item The feed item.
	 * @param WP_Post $post      The post.
	 * @return array The feed item.
	 */
	public static function json_feed_item( $feed_item, $post ) {
		$syn = get_post_syndication_links_data( $post );
		if ( $syn ) {
			$feed_item['syndication'] = get_post_syndication_links_data( $post );
		}
		return $feed_item;
	}
} // End Class
