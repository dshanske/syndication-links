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
					error_log( $return->get_error_message() . wp_json_encode( $return->error_data ) );
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
		$post_types = apply_filters( 'syndication_publish_post_types', array( 'post', 'page' ) );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'syndicationbox-meta',      // Unique ID
				esc_html__( 'Syndicate To', 'syndication-links' ),    // Title
				array( get_called_class(), 'metabox' ),   // Callback function
				$post_type,         // Admin page (or post type)
				'side',         // Context
				'default'         // Priority
			);
		}
	}

	public static function checkboxes( $post_ID ) {
		$targets = self::get_providers();
		if ( empty( $targets ) ) {
			return __( 'No Syndication Targets Available', 'syndication-links' );
		}
		$string = '<ul>';
		$meta   = get_post_meta( $post_ID, 'syndicate-to', true );
		foreach ( $targets as $uid => $name ) {
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

		echo self::checkboxes( $object->ID );
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
			wp_schedule_single_event( time() + 15, 'syn_syndication', array( $post_id, $_POST['syndicate-to' ] ) );
			// do_action( 'syn_syndication', $post_id, $_POST['syndicate-to'] );
		}
	}

	public static function str_prefix( $source, $prefix ) {
		if ( ! is_string( $source ) || ! is_string( $prefix ) ) {
			return false;
		}
		return strncmp( $source, $prefix, strlen( $prefix ) ) === 0;
	}

} // End Class

