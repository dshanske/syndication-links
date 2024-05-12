<?php
// Triggers Syndication using Micropub or a Metabox in the UI

// The Post_Syndication class sets up a post meta box to trigger

add_action( 'init', array( 'Post_Syndication', 'init' ) );

class Post_Syndication {
	protected static $targets = array();

	public static function init() {
		// Add meta box to new post/post pages only
		add_action( 'load-post.php', array( __CLASS__, 'metabox_setup' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'metabox_setup' ) );
		add_action( 'do_pings', array( __CLASS__, 'do_pings' ), 9, 2 );
		$priority = get_option( 'syndication_wp_cron', 1 ) ? 4 : 99;
		foreach ( syndication_post_types() as $type ) {
			add_action( 'save_post_' . $type, array( __CLASS__, 'save_post' ), $priority, 2 );
		}
		add_action( 'micropub_syndication', array( __CLASS__, 'syndication' ), 10, 2 );
		add_action( 'syn_syndication', array( __CLASS__, 'syndication' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );

		// Syndication Links POSSE/Syndication Options

		register_setting(
			'syndication_providers',
			'syndication_provider_enable',
			array(
				'type'         => 'array',
				'description'  => 'Enable Display of these Providers',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'default'      => array(),
			)
		);

		register_setting(
			'syndication_providers',
			'syndication_use_excerpt',
			array(
				'type'         => 'boolean',
				'description'  => 'Use Post Excerpt for Content',
				'show_in_rest' => true,
				'default'      => '0',
			)
		);
		register_setting(
			'syndication_providers',
			'syndication_backlink',
			array(
				'type'         => 'string',
				'description'  => 'Insert link back to original post',
				'show_in_rest' => true,
				'default'      => 'maybe',
			)
		);

		/* register_setting(
			'syndication_providers',
			'bridgy_ignoreformatting',
			array(
				'type'         => 'boolean',
				'description'  => 'Tell Bridgy to Ignore Formatting when Publishing',
				'show_in_rest' => true,
				'default'      => false,
			)
		); */
	}

	public static function admin_init() {
		add_settings_field(
			'syndication_provider_enable',
			__( 'Enable the Following Providers', 'syndication-links' ),
			array(
				__CLASS__,
				'provider_callback',
			),
			'syndication_provider_options',
			'syndication_providers',
			array(
				'label_for' => 'syndication_provider_enable',
			)
		);
		add_settings_field(
			'syndication_use_excerpt',
			__( 'Use Post Excerpt for Content if Set', 'syndication-links' ),
			array(
				'Syn_Config',
				'checkbox_callback',
			),
			'syndication_provider_options',
			'syndication_providers',
			array(
				'label_for' => 'syndication_use_excerpt',
			)
		);
		add_settings_field(
			'syndication_backlink',
			__( 'Posts should link back to site posts', 'syndication-links' ),
			array(
				'Syn_Config',
				'select_callback',
			),
			'syndication_provider_options',
			'syndication_providers',
			array(
				'label_for' => 'syndication_backlink',
				'list'      => array(
					''      => __( 'Enable', 'syndication-links' ),
					'true'  => __( 'Disable', 'syndication-links' ),
					'maybe' => __( 'If too long', 'syndication-links' ),
				),
			)
		);

		/* add_settings_field(
			'bridgy_ignoreformatting',
			__( 'Tell Bridgy to Ignore Formatting', 'syndication-links' ),
			array(
				'Syn_Config',
				'checkbox_callback',
			),
			'syndication_provider_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_ignoreformatting',
			)
		); */
		add_settings_section(
			'syndication_apis',
			__( 'Syndication API Keys', 'syndication-links' ),
			array( __CLASS__, 'syndication_apis_callback' ),
			'syndication_api_keys'
		);
	}

	public static function syndication_apis_callback() {
		esc_html_e( 'For Bridgy, once you have enabled publish on your Bridgy user page, there will be a Get token button, put that into the field for the token for the service', 'syndication-links' );
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
				'meta_key'    => '_syndicate-to',
				'fields'      => 'ids',
				'nopaging'    => true,
				'post_status' => 'publish',
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
			wp_schedule_single_event( $timestamp + 5, 'syn_syndication', array( $post_ID, $syndicate_to ) );
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
					$return = $target->posse( $post_ID );
					self::error_log( $return );
					$returns[] = self::syndication_log( $return, $target );
				}
			}
		}
		if ( ! empty( $returns ) ) {
			$log = get_post_meta( $post_ID, 'syndication_log', true );
			if ( empty( $log ) ) {
				$log = array();
			}
			update_post_meta( $post_ID, 'syndication_log', array_merge( $log, $returns ) );
			return $log;
		}
		return true;
	}

	public static function syndication_log( $input, $target ) {
		if ( is_wp_error( $input ) ) {
			$data            = $input->error_data;
			$data['message'] = $input->get_error_message();
		} else {
			$data = array();
			if ( array_key_exists( 'http_response', $input ) && $input['http_response'] instanceof WP_HTTP_Requests_Response ) {
				$body = $input['http_response']->get_data();
				$json = json_decode( $body, true );
				if ( ! is_null( $json ) ) {
					$body = $json;
				}
				$data        = array(
					'body' => $body,
					'code' => $input['http_response']->get_status(),
				);
				$data['url'] = wp_remote_retrieve_header( $input, 'location' );
			}
		}
		return array(
			'date' => time(),
			'uid'  => $target->get_uid(),
			'name' => $target->get_name(),
			'data' => $data,
		);
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

	public static function syndication_log_output( $post_id ) {
		$logs = get_post_meta( $post_id, 'syndication_log', true );
		if ( empty( $logs ) ) {
			esc_html_e( 'No Logs Found', 'syndication-links' );
			return;
		}
		foreach ( $logs as $log ) {
			if ( is_array( $log ) && array_key_exists( 'date', $log ) ) {
				$date = new DateTime();
				$date->setTimestamp( $log['date'] );
				$date->setTimezone( wp_timezone() );
				$name = array_key_exists( 'name', $log ) ? $log['name'] : $log['uid'];
				printf( '<p><details><summary>%1$s: %2$s</summary><pre>%3$s</pre></details></p>', esc_html( $date->format( DATE_W3C ) ), esc_html( $name ), esc_html( wp_json_encode( $log['data'], JSON_PRETTY_PRINT ) ) );
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
			syndication_post_types(),         // Admin page (or post type)
			'side',         // Context
			'default',      // Priority
			array(
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			)
		);
		add_meta_box(
			'syndicationlog-meta',      // Unique ID
			esc_html__( 'Syndicate Logs', 'syndication-links' ),    // Title
			array( get_called_class(), 'syndication_log_metabox' ),   // Callback function
			syndication_post_types(),         // Admin page (or post type)
			'advanced',         // Context
			'default',      // Priority
			array(
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			)
		);
	}


	public static function syndication_log_metabox( $object, $box ) {
		self::syndication_log_output( $object->ID ); // phpcs:ignore
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
		$checked = static::$targets[ $uid ]->is_checked();
		return apply_filters( 'syndication_link_checked', $checked, $uid, $post_ID );
	}

	public static function disabled( $uid, $post_ID = 0 ) {
		$disabled = static::$targets[ $uid ]->is_disabled();
		return apply_filters( 'syndication_link_disabled', $disabled, $uid, $post_ID );
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
			$disabled = self::disabled( $uid, $post_ID );
			if ( ! $disabled ) {
				$checked = self::checked( $uid, $post_ID );
			} else {
				$checked = false;
			}
			$string .= self::checkbox( $uid, $name, $checked, $disabled );
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
	public static function save_post( $post_id ) {
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
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
		}

		// If this property is set then set to
		if ( isset( $_POST['syndicate-to'] ) ) {
			$syndication = array_map( 'sanitize_key', $_POST['syndicate-to'] );
			if ( get_option( 'syndication_wp_cron', 1 ) ) {
				add_post_meta( $post_id, '_syndicate-to', $syndication, true );
			} elseif ( 'publish' === get_post_status( $post_id ) ) {
				self::syndication( $post_id, $syndication );
			}
		}
	}
} // End Class
