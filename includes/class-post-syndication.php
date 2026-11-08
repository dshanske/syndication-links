<?php

defined( 'ABSPATH' ) || exit;

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
		add_action( 'wp_after_insert_post', array( __CLASS__, 'schedule_pings' ), 10, 2 );
		$priority = get_option( 'syndication_wp_cron', 1 ) ? 4 : 99;
		foreach ( syndication_post_types() as $type ) {
			add_action( 'save_post_' . $type, array( __CLASS__, 'save_post' ), $priority, 2 );
		}
		add_action( 'micropub_syndication', array( __CLASS__, 'syndication' ), 10, 2 );
		add_action( 'syn_syndication', array( __CLASS__, 'syndication' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		// The Webmention plugin's public filter for the targets it found in a post's content.
		add_filter( 'webmention_links', array( __CLASS__, 'webmention_links' ), 10, 2 );

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
				'meta_key'    => '_syndicate-to', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Runs on do_pings (cron), for the few posts with queued targets.
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

	/**
	 * Makes sure do_pings runs for a published post that's queued for syndication.
	 *
	 * Core schedules do_pings only when a `post` is published (_publish_post_hook()),
	 * so a queued post of any other type would wait for the next `post`.
	 *
	 * @since 5.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function schedule_pings( $post_id, $post ) {
		if ( 'publish' !== get_post_status( $post ) || ! metadata_exists( 'post', $post_id, '_syndicate-to' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( 'do_pings' ) ) {
			wp_schedule_single_event( time(), 'do_pings' );
		}
	}

	/* Syndicate To is an Array of UIDS */
	public static function syndication( $post_ID, $syndicate_to ) {
		$post = get_post( $post_ID );
		// If this is an invalid post return
		if ( ! $post ) {
			return;
		}

		$syndicate_to = (array) $syndicate_to;
		$status       = get_post_status( $post );

		// Syndication is public: no provider supports private posts yet, so they're never sent.
		// If one ever does, allow it here.
		if ( 'private' === $status ) {
			return false;
		}

		$returns = array();

		$timestamp = get_post_timestamp( $post_ID );
		$current   = time();
		$diff      = $current - $timestamp;

		// If the post is dated in the future, syndicate it when it's published, not now.
		if ( $diff < 0 ) {
			self::schedule( $post_ID, $syndicate_to, $timestamp + 5 );
			return true;
		}

		// Drafts and pending posts wait for publishing: do_pings() sends them then.
		if ( 'publish' !== $status ) {
			self::queue( $post_ID, $syndicate_to );
			return true;
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
					self::error_log( $return, $target, $post_ID );
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

	/**
	 * Schedules syndication of a post for later.
	 *
	 * Keeps one event per post and set of targets: if the post's date has
	 * changed since it was scheduled, the event moves to the new time.
	 *
	 * @since 5.0.0
	 *
	 * @param int      $post_ID      Post ID.
	 * @param string[] $syndicate_to UIDs of the targets.
	 * @param int      $time         Unix timestamp to syndicate at.
	 */
	public static function schedule( $post_ID, $syndicate_to, $time ) {
		$args = array( $post_ID, $syndicate_to );
		$next = wp_next_scheduled( 'syn_syndication', $args );
		if ( $next === $time ) {
			return;
		}
		if ( $next ) {
			wp_unschedule_event( $next, 'syn_syndication', $args );
		}
		wp_schedule_single_event( $time, 'syn_syndication', $args );
	}

	/**
	 * Queues a post to be syndicated by do_pings() once it's published.
	 *
	 * Adds the targets to any that are already queued for the post.
	 *
	 * @since 5.0.0
	 *
	 * @param int      $post_ID      Post ID.
	 * @param string[] $syndicate_to UIDs of the targets.
	 */
	public static function queue( $post_ID, $syndicate_to ) {
		$queued = get_post_meta( $post_ID, '_syndicate-to', true );
		$queued = is_array( $queued ) ? $queued : array();
		update_post_meta( $post_ID, '_syndicate-to', array_values( array_unique( array_merge( $queued, $syndicate_to ) ) ) );
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

	/**
	 * Logs the outcome of a syndication attempt under WP_DEBUG.
	 *
	 * Only a summary is logged: the status code, error code and the new URL.
	 * Response bodies can include account details, and the full response is
	 * already kept in the post's syndication log.
	 *
	 * @since 5.0.0 Logs a summary instead of the whole response, and takes the target and post.
	 *
	 * @param mixed                     $input   What the provider's posse() returned.
	 * @param Syndication_Provider|null $target  The target. Optional.
	 * @param int                       $post_id Post ID. Optional.
	 */
	public static function error_log( $input, $target = null, $post_id = 0 ) {
		if ( ! WP_DEBUG ) {
			return;
		}
		$where = $target instanceof Syndication_Provider ? $target->get_uid() : 'unknown target';
		if ( $post_id ) {
			$where = sprintf( 'post %1$d to %2$s', $post_id, $where );
		}
		if ( is_wp_error( $input ) ) {
			$data   = $input->get_error_data();
			$status = ( is_array( $data ) && isset( $data['status'] ) ) ? sprintf( ' (HTTP %d)', $data['status'] ) : '';
			error_log( sprintf( 'Syndication Links: syndicating %1$s failed: %2$s%3$s', $where, $input->get_error_code(), $status ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}
		$details = '';
		if ( is_array( $input ) && isset( $input['response']['code'] ) ) {
			$details = sprintf( ' (HTTP %d)', $input['response']['code'] );
			$url     = wp_remote_retrieve_header( $input, 'location' );
			if ( $url ) {
				$details .= ' ' . $url;
			}
		}
		error_log( sprintf( 'Syndication Links: syndicated %1$s%2$s', $where, $details ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
		// A provider that isn't registered (e.g. enabled but never registered) isn't checked.
		$checked = isset( static::$targets[ $uid ] ) ? static::$targets[ $uid ]->is_checked() : false;
		return apply_filters( 'syndication_link_checked', $checked, $uid, $post_ID );
	}

	public static function disabled( $uid, $post_ID = 0 ) {
		$disabled = isset( static::$targets[ $uid ] ) ? static::$targets[ $uid ]->is_disabled() : false;
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

	/**
	 * Keeps the Webmention plugin from sending to an enabled provider the post didn't choose.
	 *
	 * The Webmention plugin sends a webmention to every link in a post's content. When a
	 * webmention provider, such as Bridgy Fed, is enabled here, a link to its target in
	 * the content would syndicate the post even with its box unchecked. So the target is
	 * dropped unless the post has the provider queued or has been sent to it before
	 * (so updates still go out). Providers that aren't enabled are left alone, for sites
	 * that link to the target by hand.
	 *
	 * @since 5.0.0
	 *
	 * @param string[] $urls    The targets found in the post's content.
	 * @param int      $post_id Post ID.
	 * @return string[] The targets.
	 */
	public static function webmention_links( $urls, $post_id ) {
		$enabled = get_option( 'syndication_provider_enable', array() );
		if ( ! is_array( $urls ) || ! is_array( $enabled ) || empty( $enabled ) ) {
			return $urls;
		}

		$queued = get_post_meta( $post_id, '_syndicate-to', true );
		$chosen = is_array( $queued ) ? $queued : array();
		$log    = get_post_meta( $post_id, 'syndication_log', true );
		if ( is_array( $log ) ) {
			$chosen = array_merge( $chosen, array_column( array_filter( $log, 'is_array' ), 'uid' ) );
		}

		$skip = array();
		foreach ( static::$targets as $uid => $target ) {
			if ( ! $target instanceof SynProvider_Webmention || ! in_array( $uid, $enabled, true ) || in_array( $uid, $chosen, true ) ) {
				continue;
			}
			$url = $target->get_target();
			if ( $url ) {
				$skip[] = strtolower( untrailingslashit( $url ) );
			}
		}
		if ( empty( $skip ) ) {
			return $urls;
		}

		return array_values(
			array_filter(
				$urls,
				function ( $url ) use ( $skip ) {
					return ! is_string( $url ) || ! in_array( strtolower( untrailingslashit( $url ) ), $skip, true );
				}
			)
		);
	}
} // End Class
