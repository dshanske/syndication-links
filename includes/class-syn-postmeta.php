<?php
// Adds Post Meta Box for Syndication URLs

add_action( 'init' , array('syn_postmeta', 'init') );

// The syn_postmeta class sets up post meta boxes for data associated with Syndication
class syn_postmeta {
	public static function init() {
		// Add meta box to new post/post pages only 
		add_action('load-post.php', array('syn_postmeta', 'setup') );
		add_action('load-post-new.php', array('syn_postmeta', 'setup') );
		add_action( 'save_post', array('syn_postmeta', 'save_post_meta') );
	}

	/* Meta box setup function. */
	public static function setup() {
  	/* Add meta boxes on the 'add_meta_boxes' hook. */
  	add_action( 'add_meta_boxes', array('syn_postmeta', 'add_postmeta_boxes') );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public static function add_postmeta_boxes() {
		$screens = array( 'post', 'page' );
		$screens = apply_filters('syn_post_types', $screens);
		foreach ( $screens as $screen ) {
			add_meta_box(
				'synbox-meta',      // Unique ID
				esc_html__( 'Syndication Links', 'Syn Links' ),    // Title
				array('syn_postmeta', 'metabox'),   // Callback function
				$screen,         // Admin page (or post type)
				'normal',         // Context
				'default'         // Priority
			);
		}
	}

	public static function metabox( $object, $box ) { 
		wp_nonce_field( 'syn_metabox', 'syn_metabox_nonce' );
		$meta = get_post_meta( $object->ID, 'syndication_urls', true );
		echo '<p><label>';
		_e('One URL per line.', 'Syn Links');
		echo '</label></p>';
		echo "<textarea name='syndication_urls' rows='4' cols='70'>";
		if (!empty($meta)) {echo $meta; }
		echo "</textarea>";
	}

	/* Save the meta box's post metadata. */
	public static function save_post_meta( $post_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 * Check if our nonce is set.
		 */
		if ( ! isset( $_POST['syn_metabox_nonce'] ) ) {
			return;
		}
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['syn_metabox_nonce'], 'syn_metabox' ) ) {
			return;
		}
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		if( isset( $_POST[ 'syndication_urls' ])) {
			if (empty($_POST[ 'syndication_urls' ]) ) {
				delete_post_meta($post_id, 'syndication_urls');
			}
			else {
				$meta = syn_clean_urls(explode("\n", $_POST[ 'syndication_urls' ]) );
				update_post_meta( $post_id, 'syndication_urls', implode("\n", $meta));
			}
		}
	}

} // End Class 

?>
