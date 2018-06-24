<?php
// Triggers Syndication using Micropub or a Metabox in the UI

// The Post_Syndication class sets up a post meta box to trigger
class Post_Syndication {
	public function __construct() {
		// Add meta box to new post/post pages only
		add_action( 'load-post.php', array( $this, 'metabox_setup' ) );
		add_action( 'load-post-new.php', array( $this, 'metabox_setup' ) );

		add_action( 'save_post', array( $this, 'save_post' ), 8, 3 );
		add_action( 'micropub_syndication', array( $this, 'syndication' ), 10, 2 );
		add_action( 'syn_syndication', array( $this, 'syndication' ), 10, 2 );
	}

	/* Syndicate To is an Array of UIDS */
	public function syndication( $post_ID, $syndicate_to ) {
		$post = get_post( $post_ID );
		// If this is an invalid post return
		if ( ! $post ) {
			return;
		}
		// Ensure this will not fire if the status is not publish
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		$targets = $this->targets();
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
	public function metabox_setup() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( $this, 'add_postmeta_boxes' ) );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public function add_postmeta_boxes() {
		$post_types = apply_filters( 'syndication_publish_post_types', array( 'post', 'page' ) );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'syndicationbox-meta',      // Unique ID
				esc_html__( 'Syndicate To', 'syndication-links' ),    // Title
				array( &$this, 'metabox' ),   // Callback function
				$post_type,         // Admin page (or post type)
				'side',         // Context
				'default'         // Priority
			);
		}
	}

	public function targets() {
		return apply_filters( 'syn_syndication_targets', array() );
	}

	public function checkboxes( $post_ID ) {
		$targets = $this->targets();
		if ( empty( $targets ) ) {
			return __( 'No Syndication Targets Available', 'syndication-links' );
		}
		$string = '<ul>';
		$meta   = get_post_meta( $post_ID, 'syndicate-to', true );
		foreach ( $targets as $target ) {
			if ( ! $target instanceof Syndication_Provider ) {
				continue;
			}
			$string .= $this->checkbox( $target, $post_ID );
		}
		$string .= '</ul>';
		return $string;
	}

	public function checkbox( $target, $post_ID ) {
		$checked = $this->get_target( $post_ID, $target->get_uid() );
		return sprintf(
			'<li><input type="checkbox" name="syndicate-to[]" id="%1$s" value="%1$s" %3$s />
			<label for="%1$s">%2$s</label></li>', $target->get_uid(), $target->get_name(), checked( $target->get_uid(), $checked, true, false )
		);
	}

	public function get_target( $post_ID ) {
	}

	public function metabox( $object, $box ) {
		wp_nonce_field( 'synto_metabox', 'synto_metabox_nonce' );

		echo $this->checkboxes( $object->ID );
	}

	/* Save the meta box's post metadata. */
	public function save_post( $post_id, $post, $update ) {
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
			do_action( 'syn_syndication', $post_id, $_POST['syndicate-to'] );
		}
	}

	public function str_prefix( $source, $prefix ) {
		if ( ! is_string( $source ) || ! is_string( $prefix ) ) {
			return false;
		}
		return strncmp( $source, $prefix, strlen( $prefix ) ) === 0;
	}

} // End Class

