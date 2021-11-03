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
		add_action( 'do_pings', array( $cls, 'do_pings' ), 9, 2 );
		foreach ( self::syndication_publish_post_types() as $type ) {
			add_action( 'publish_' . $type, array( $cls, 'publish_post' ), 4, 2 );
		}
		add_action( 'micropub_syndication', array( $cls, 'syndication' ), 10, 2 );
		add_action( 'syn_syndication', array( $cls, 'syndication' ), 10, 2 );
		add_action( 'admin_init', array( $cls, 'admin_init' ) );

		// Syndication Links POSSE/Syndication Options

		register_setting(
			'syndication_options',
			'syndication_provider_enable',
			array(
				'type'         => 'string',
				'description'  => 'Enable Display of these Providers',
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
			'syndication_provider_enable',
			__( 'Enable the Following Providers', 'syndication-links' ),
			array(
				get_called_class(),
				'provider_callback',
			),
			'links_options',
			'syndication_posse_options',
			array(
				'name' => 'syndication_provider_enable',
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
			$uid = $object->get_uid();
			if ( ! empty( $uid ) ) {
				static::$targets[ $uid ] = $object;
				return true;
			}
		}
		return false;
	}

	public static function do_pings() {
		$syndicate = get_posts(
			array(
				'meta_key' => '_syndicate-to',
				'fields'   => 'ids',
				'nopaging' => true,
			)
		);

		if ( empty( $syndicate ) ) {
			return;
		}

		foreach ( $syndicate as $target ) {
			$meta = get_post_meta( $target, '_syndicate-to', true );
			// Send Syndications
			delete_post_meta( $target, '_syndicate-to' );
			self::syndication( $target, $meta );
		}
	}

	/* Syndicate To is an Array of UIDS */
	public static function syndication( $post_ID, $syndicate_to ) {
		$post = get_post( $post_ID );
		// If this is an invalid post return
		if ( ! $post ) {
			return;
		}
		$returns = array();

		$timestamp = get_post_timestamp( $post_ID );
		$current   = time();
		$diff      = $current - $timestamp;

		// If it is for later then schedule it for later.
		if ( $diff < 0 ) {
			wp_schedule_single_event( $timestamp, 'syn_syndication', array( $post_ID, $syndicate_to ) );
		}

		// Reject this
		if ( $diff > DAY_IN_SECONDS ) {
			$returns[] = new WP_Error( 'too_old', sprintf( 'Post is %1$s days old - no syndication', $diff / DAY_IN_SECONDS ) );
		} else {
			$targets = static::$targets;
			if ( empty( $targets ) || ! is_array( $targets ) ) {
				return false;
			}

			foreach ( $targets as $target ) {
				if ( ! $target instanceof Syndication_Provider ) {
					continue;
				}
				if ( in_array( $target->get_uid(), $syndicate_to, true ) ) {
					$return                        = $target->posse( $post_ID );
					$returns[ $target->get_uid() ] = $return;
				}
			}
		}
		if ( ! empty( $returns ) ) {
			update_post_meta( $post_ID, 'syndication_log', $returns );
			foreach ( $returns as $return ) {
				self::error_log( $return );
			}
			return $returns;
		}
		return true;
	}

	public static function error_log( $input ) {
		if ( ! WP_DEBUG ) {
			return;
		}
		if ( is_wp_error( $input ) ) {
			error_log( sprintf( '%1$s: %2$s', $input->get_error_message(), wp_json_encode( $input->error_data ) ) ); // phpcs:ignore
		} else {
			error_log( sprintf( 'Success: %1$s', wp_json_encode( $input ) ) ); // phpcs:ignore
		}
	}

	/* Meta box setup function. */
	public static function metabox_setup() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( get_called_class(), 'add_postmeta_boxes' ) );
	}

	public static function syndication_publish_post_types() {
		return apply_filters( 'syndication_publish_post_types', array( 'post', 'page' ) );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public static function add_postmeta_boxes() {
		add_meta_box(
			'syndicationbox-meta',      // Unique ID
			esc_html__( 'Syndicate To', 'syndication-links' ),    // Title
			array( get_called_class(), 'metabox' ),   // Callback function
			self::syndication_publish_post_types(),         // Admin page (or post type)
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
		printf( $output, $name, $int, 'name', esc_attr( $name ), esc_attr( self::ifset( $value, 'name' ) ), esc_html__( 'Name', 'syndication-links' ) ); // phpcs:ignore
		printf( $output, $name, $int, 'uid', esc_attr( $name ), esc_attr( self::ifset( $value, 'uid' ) ), esc_html__( 'UID', 'syndication-links' ) ); // phpcs:ignore
		printf( $output, $name, $int, 'target', esc_attr( $name ), esc_attr( self::ifset( $value, 'target' ) ), esc_html__( 'Target URL', 'syndication-links' ) ); // phpcs:ignore
		echo '</li>';
	}

	public static function provider_callback( $args ) {
		$targets   = self::get_providers();
		$allowlist = (array) get_option( 'syndication_provider_enable', array() );
		echo '<div>';
		foreach ( $targets as $uid => $name ) {
			if ( empty( $uid ) || empty( $name ) ) {
				continue;
			}
			printf( '<p><input type="checkbox" name="syndication_provider_enable[]" id="%1$s" value="%1$s" %3$s /><label for="%1$s">%2$s</label></p>', esc_attr( $uid ), esc_html( $name ), checked( true, in_array( $uid, $allowlist, true ), false ) );
			echo '</div>';
		}
	}

	public static function checked( $uid, $post_ID = 0 ) {
		return apply_filters( 'syndication_link_checked', false, $uid, $post_ID );
	}

	public static function disabled( $uid, $post_ID = 0 ) {
		return apply_filters( 'syndication_link_disabled', false, $uid, $post_ID );
	}

	public static function checkboxes( $post_ID ) {
		$targets   = self::get_providers();
		$allowlist = get_option( 'syndication_provider_enable', array() );
		if ( ! is_array( $allowlist ) ) {
			$allowlist = array();
		}
		if ( empty( $targets ) || empty( $allowlist ) ) {
			return __( 'No Syndication Targets Available', 'syndication-links' );
		}

		$string = '<ul>';
		$meta   = get_post_meta( $post_ID, 'syndicate-to', true );

		foreach ( $targets as $uid => $name ) {
			if ( ! empty( $allowlist ) && ! in_array( $uid, $allowlist, true ) ) {
				continue;
			}
			$checked  = self::checked( $uid, $post_ID );
			$disabled = self::disabled( $uid, $post_ID );
			$string  .= self::checkbox( $uid, $name, $checked, $disabled );
		}
		$string .= '</ul>';
		return $string;
	}

	public static function checkbox( $uid, $name, $checked = false, $disabled = false ) {
		$properties   = array();
		$properties[] = checked( $checked, true, false );
		$properties[] = disabled( $disabled, true, false );
		return sprintf(
			'<li><input type="checkbox" name="syndicate-to[]" id="%1$s" value="%1$s" %3$s />
			<label for="%1$s">%2$s</label></li>',
			$uid,
			$name,
			implode( ' ', $properties )
		);
	}

	public static function get_providers() {
		$return = array();
		foreach ( static::$targets as $target ) {
			$return[ $target->get_uid() ] = esc_html( $target->get_name() );
		}
		return $return;
	}

	public static function metabox( $object, $box ) {
		wp_nonce_field( 'synto_metabox', 'synto_metabox_nonce' );

		echo self::checkboxes( $object->ID ); // phpcs:ignore
	}

	/* Save the meta box's post metadata. */
	public static function publish_post( $post_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the publish_post action can be triggered at other times.
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

		// If this property is set then set to
		if ( isset( $_POST['syndicate-to'] ) ) {
			// Wait 15 seconds before posting to ensure the post is published
			add_post_meta( $post_id, '_syndicate-to', sanitize_text_field( $_POST['syndicate-to'] ), true );
		}

	}

	public static function str_prefix( $source, $prefix ) {
		if ( ! is_string( $source ) || ! is_string( $prefix ) ) {
			return false;
		}
		return strncmp( $source, $prefix, strlen( $prefix ) ) === 0;
	}

} // End Class
