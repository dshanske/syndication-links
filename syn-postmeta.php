<?php
// Adds Post Meta Box for Syndication URLs
// Plan is to optionally automate filling in of this data from third parties


// Add meta box to new post/post pages only 
add_action('load-post.php', 'synbox_setup');
add_action('load-post-new.php', 'synbox_setup');

/* Meta box setup function. */
function synbox_setup() {

  /* Add meta boxes on the 'add_meta_boxes' hook. */
  add_action( 'add_meta_boxes', 'synbox_add_postmeta_boxes' );
}

/* Create one or more meta boxes to be displayed on the post editor screen. */
function synbox_add_postmeta_boxes() {

  add_meta_box(
    'synbox-meta',      // Unique ID
    esc_html__( 'Syndication Links', 'semantic' ),    // Title
    'syn_metabox',   // Callback function
    'post',         // Admin page (or post type)
    'normal',         // Context
    'default'         // Priority
  );
}



function syn_metabox( $object, $box ) { 
	wp_nonce_field( 'syn_metabox', 'syn_metabox_nonce' );
        $network = get_option('syndication_network_options');
	$meta = get_post_meta( $object->ID, 'synlinks', true );
        foreach( $network as $key => $value){
	   if ($value==1)
 	      {
            	echo '<p> Syndication URL for: ' . $key . '</label>';
	    	echo '<input type="text" name="' . $key . '" value="' . esc_attr($meta[$key]) . '" size ="70" /></p>';
	      }
            }
}

/* Save the meta box's post metadata. */
function synbox_save_post_meta( $post_id ) {

	/*
	 * We need to verify this came from our screen and with proper authorization,
	 * because the save_post action can be triggered at other times.
	 */

	// Check if our nonce is set.
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
	$network = get_option('syndication_network_options');
        foreach( $network as $key => $value){
           if ($value==1)
              {
                 if( isset( $_POST[ $key ] ) ) {
                     $meta[$key] = esc_url_raw( $_POST[ $key ] );
		   }
              }
            }
	update_post_meta( $post_id, 'synlinks', $meta);

}

add_action( 'save_post', 'synbox_save_post_meta' );
?>
