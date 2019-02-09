<?php
// Triggers Syndication using Micropub or a Metabox in the UI

// The Post_Syndication class sets up a post meta box to trigger

add_action( 'init', array( 'Post_Syndication', 'init' ) );

class Post_Syndication {
	protected static $targets = array();

	public static function init() {
		$cls = get_called_class();
		// Add meta box to new post/post pages only
		add_action( 'load-post.php', array( $cls, 'metabox_setup' ) );
		add_action( 'load-post-new.php', array( $cls, 'metabox_setup' ) );

		add_action( 'save_post', array( $cls, 'save_post' ), 8, 3 );
		add_action( 'micropub_syndication', array( $cls, 'syndication' ), 10, 2 );
		add_action( 'syn_syndication', array( $cls, 'syndication' ), 10, 2 );
		add_action( 'admin_init', array( $cls, 'admin_init' ) );

		// Syndication Links POSSE/Syndication Options
		register_setting(
			'syndication_options',
			'syndication_provider_disable',
			array(
				'type'         => 'string',
				'description'  => 'Disable Display of these Providers',
				'show_in_rest' => true,
				'default'      => array(),
			)
		);

		register_setting(
			'syndication_options',
			'syndication_links_custom_posse',
			array(
				'type'         => 'string',
				'description'  => 'Syndication Links Custom Webmention POSSE list',
				'show_in_rest' => true,
				'default'      => array(),
			)
		);

	}

	public static function admin_init() {
		add_settings_field(
			'syndication_provider_disable',
			__( 'Disable the Following Providers', 'syndication-links' ),
			array(
				get_called_class(),
				'provider_callback',
			),
			'links_options',
			'syndication_posse_options',
			array(
				'name' => 'syndication_provider_disable',
			)
		);

		add_settings_section(
			'webmention_provider_options',
			__( 'Custom Webmention POSSE providers', 'syndication-links' ),
			array( get_called_class(), 'webmention_heading' ),
			'links_options'
		);

		add_settings_field(
			'syndication_links_custom_posse',
			__( 'Custom Providers', 'syndication-links' ),
			array(
				get_called_class(),
				'webmention_callback',
			),
			'links_options',
			'webmention_provider_options',
			array()
		);
	}

	public static function webmention_heading() {
		esc_html_e( 'Set up Custom Webmention POSSE handling', 'syndication-links' );
	}

	public static function register( $object ) {
		if ( $object instanceof Syndication_Provider ) {
			static::$targets[ $object->get_uid() ] = $object;
			return true;
		}
		return false;
	}

	/* Syndicate To is an Array of UIDS */
	public static function syndication( $post_ID, $syndicate_to ) {
		$post = get_post( $post_ID );
		// If this is an invalid post return
		if ( ! $post ) {
			return;
		}
		// Ensure this will not fire if the status is not publish
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		$targets = static::$targets;
		if ( empty( $targets ) || ! is_array( $targets ) ) {
			return;
		}
		foreach ( $targets as $target ) {
			if ( ! $target instanceof Syndication_Provider ) {
				continue;
			}
			if ( in_array( $target->get_uid(), $syndicate_to, true ) ) {
				$return = $target->posse( $post_ID );
				if ( is_wp_error( $return ) ) {
					error_log( $return->get_error_message() . wp_json_encode( $return->error_data ) ); // phpcs:ignore
				}
			}
		}
	}

	/* Meta box setup function. */
	public static function metabox_setup() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( get_called_class(), 'add_postmeta_boxes' ) );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public static function add_postmeta_boxes() {
		add_meta_box(
			'syndicationbox-meta',      // Unique ID
			esc_html__( 'Syndicate To', 'syndication-links' ),    // Title
			array( get_called_class(), 'metabox' ),   // Callback function
			apply_filters( 'syndication_publish_post_types', array( 'post', 'page' ) ),         // Admin page (or post type)
			'side',         // Context
			'default',      // Priority
			array(
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			)
		);
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

	public static function ifset( $array, $key ) {
		if ( array_key_exists( $key, $array ) ) {
			return $array[ $key ];
		}
		return '';
	}

	private static function webmention_inputs( $int, $value = array() ) {
		$output = '<input type="text" name="%1$s[%2$s][%3$s]" id="%4$s" value="%5$s" placeholder="%6$s" />';
		$name   = 'syndication_links_custom_posse';
		echo '<li>';
		printf( $output, $name, $int, 'name', esc_attr( $name ), esc_attr( self::ifset( $value, 'name' ) ), esc_html__( 'Name', 'syndication-links' ) );
		printf( $output, $name, $int, 'uid', esc_attr( $name ), esc_attr( self::ifset( $value, 'uid' ) ), esc_html__( 'UID', 'syndication-links' ) );
		printf( $output, $name, $int, 'target', esc_attr( $name ), esc_attr( self::ifset( $value, 'target' ) ), esc_html__( 'Target URL', 'syndication-links' ) );
		echo '</li>';
	}

	public static function provider_callback( $args ) {
		$targets   = self::get_providers();
		$blacklist = (array) get_option( 'syndication_provider_disable', array() );
		echo '<div>';
		foreach ( $targets as $uid => $name ) {
			if ( empty( $uid ) || empty( $name ) ) {
				continue;
			}
			printf( '<p><input type="checkbox" name="syndication_provider_disable[]" id="%1$s" value="%1$s" %3$s /><label for="%1$s">%2$s</label></p>', esc_attr( $uid ), esc_html( $name ), checked( true, in_array( $uid, $blacklist, true ), false ) );
			echo '</div>';
		}
	}

	public static function checkboxes( $post_ID ) {
		$targets   = self::get_providers();
		$blacklist = get_option( 'syndication_provider_disable', array() );
		if ( ! is_array( $blacklist ) ) {
			$blacklist = array( $blacklist );
		}
		if ( empty( $targets ) ) {
			return __( 'No Syndication Targets Available', 'syndication-links' );
		}
		$string = '<ul>';
		$meta   = get_post_meta( $post_ID, 'syndicate-to', true );
		foreach ( $targets as $uid => $name ) {
			if ( in_array( $uid, $blacklist, true ) ) {
				continue;
			}
			$string .= self::checkbox( $uid, $name, $post_ID );
		}
		$string .= '</ul>';
		return $string;
	}

	public static function checkbox( $uid, $name, $post_ID ) {
		$checked = self::get_target( $post_ID, $uid );
		return sprintf(
			'<li><input type="checkbox" name="syndicate-to[]" id="%1$s" value="%1$s" %3$s />
			<label for="%1$s">%2$s</label></li>',
			$uid,
			$name,
			checked( $uid, $checked, false )
		);
	}

	public static function get_providers() {
		$return = array();
		foreach ( static::$targets as $target ) {
			$return[ $target->get_uid() ] = esc_html( $target->get_name() );
		}
		return $return;
	}

	public static function get_target( $post_ID ) {
	}

	public static function metabox( $object, $box ) {
		wp_nonce_field( 'synto_metabox', 'synto_metabox_nonce' );

		echo self::checkboxes( $object->ID ); // phpcs:ignore
	}

	/* Save the meta box's post metadata. */
	public static function save_post( $post_id, $post, $update ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */
		// Check if our nonce is set.
		if ( ! isset( $_POST['synto_metabox_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['synto_metabox_nonce'], 'synto_metabox' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		// If this property is set then trigger an action identical to the micropub action
		if ( isset( $_POST['syndicate-to'] ) ) {
			// Wait 15 seconds before posting to ensure the post is published
			wp_schedule_single_event( time() + 60, 'syn_syndication', array( $post_id, $_POST['syndicate-to'] ) );
		}
	}

	public static function str_prefix( $source, $prefix ) {
		if ( ! is_string( $source ) || ! is_string( $prefix ) ) {
			return false;
		}
		return strncmp( $source, $prefix, strlen( $prefix ) ) === 0;
	}

} // End Class

