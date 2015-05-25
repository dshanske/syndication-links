<?php
// Adds Post Meta Box for Syndication URLs

add_action( 'init' , array('syn_meta', 'init') );

// The syn_meta class sets up post meta boxes for data associated with Syndication
class syn_meta {
	public static function init() {
		// Add meta box to new post/post pages only 
		add_action('load-post.php', array('syn_meta', 'setup') );
		add_action('load-post-new.php', array('syn_meta', 'setup') );
		add_action( 'save_post', array('syn_meta', 'save_post_meta') );
	}

	/* Filters incoming URLs.
	 *
	 * @param array $urls An array of URLs to filter.
	 * @return array A filtered array of unique URLs.
	 * @uses clean_url
	 */
	public static function clean_urls($urls) {
		$array = array_map(array('syn_meta', 'clean_url'), $urls);
		return array_filter(array_unique($array));
	}

	/**
	 * Filters a single syndication URL.
	 *
	 * @param string $string A string that is expected to be a syndication URL.
	 * @return string|bool The filtered and escaped URL string, or FALSE if invalid.
	 * @used-by clean_urls
	 */
	public static function clean_url($string) {
		$url = trim($string);
		if ( !filter_var($url, FILTER_VALIDATE_URL) ) { 
			return false; 
		}
		// Rewrite these to https as needed
		$secure = apply_filters('syn_rewrite_secure', array('facebook.com', 'twitter.com'));
		if (in_array(extract_domain_name($url), $secure) ) {
			$url = preg_replace("/^http:/i", "https:", $url);
		}
		$url = esc_url_raw($url);
		return $url;
	}

	/* Meta box setup function. */
	public static function setup() {
  	/* Add meta boxes on the 'add_meta_boxes' hook. */
  	add_action( 'add_meta_boxes', array('syn_meta', 'add_meta_boxes') );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public static function add_meta_boxes() {
		$screens = array( 'post', 'page' );
		$screens = apply_filters('syn_post_types', $screens);
		foreach ( $screens as $screen ) {
			add_meta_box(
				'synbox-meta',      // Unique ID
				esc_html__( 'Syndication Links', 'Syn Links' ),    // Title
				array('syn_meta', 'metabox'),   // Callback function
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
				$meta = self::clean_urls(explode("\n", $_POST[ 'syndication_urls' ]) );
				update_post_meta( $post_id, 'syndication_urls', implode("\n", $meta));
			}
		}
	}
} // End Class
	
function get_syndication_links() {
	$options = get_option('syndication_content_options');
	$urls = explode("\n", get_post_meta(get_the_ID(), 'syndication_urls', true));
	// Mf2_syndication is used by the Micropub plugin
	$mf2 = explode("\n", get_post_meta(get_the_ID(), 'mf2_syndication', true ));
	// Clean and dudupe
	$urls = syn_meta::clean_urls(array_merge($urls, $mf2));
	// Allow URLs to be added by other plugins
	$urls = apply_filters('syn_add_links', $urls);
	if (!empty($urls)) {
		$strings = get_syn_network_strings();
		$synlinks = '<span class="relsyn social-icon"><ul>' . $options['text_before'];
		foreach ($urls as $url) {
			if (empty($url)) { continue; }
			$domain = extract_domain_name($url);
			if (array_key_exists($domain, $strings)) {
				$name = $strings[$domain];
			}
			else {
				$name = $domain;
			}
			$synlinks .=  '<li><a title="' . $name . '" class="u-syndication" href="' . esc_url($url) . '"';
			if (is_single() ) {
				$synlinks .= ' rel="syndication">';
			}
     	else {
       	$synlinks .= '>';
			}
			if ($options['just_icons'] == "1") {
				$synlinks .= $name;
			}
			$synlinks .= '</a></li>';
		}
		$synlinks .= '</ul></span>';
	}
 	return (empty($synlinks)) ? '' : $synlinks;
}

?>
