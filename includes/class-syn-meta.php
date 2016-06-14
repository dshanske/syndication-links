<?php
// Adds Post Meta Box for Syndication URLs
add_action( 'init' , array( 'Syn_Meta', 'init' ) );

// The Syn_Meta class sets up post meta boxes for data associated with Syndication
class Syn_Meta {
	public static function init() {
		// Add meta box to new post/post pages only
		add_action( 'load-post.php', array( 'Syn_Meta', 'setup' ) );
		add_action( 'load-post-new.php', array( 'Syn_Meta', 'setup' ) );
		add_action( 'save_post', array( 'Syn_Meta', 'save_post_meta' ) );

    // Return Syndication URLs as part of the JSON Rest API
    add_filter( 'json_prepare_post', array( 'Syn_Meta', 'json_rest_add_synmeta' ),10,3 );
	}

  public static function json_rest_add_synmeta($_post,$post,$context) {
		$syn = self::get_syndication_links_data( $post['ID'] );
    if ( ! empty( $syn ) ) {
      $urls = explode( "\n", $syn );
      $_post['syndication'] = $urls;
    }
    return $_post;
  }

	/*
	Filters incoming URLs.
	 *
	 * @param array $urls An array of URLs to filter.
	 * @return array A filtered array of unique URLs.
	 * @uses clean_url
	 */
	public static function clean_urls($urls) {
		$array = array_map( array( 'Syn_Meta', 'clean_url' ), $urls );
		return array_filter( array_unique( $array ) );
	}

	/**
	 * Filters a single syndication URL.
	 *
	 * @param string $string A string that is expected to be a syndication URL.
	 * @return string|bool The filtered and escaped URL string, or FALSE if invalid.
	 * @used-by clean_urls
	 */
	public static function clean_url($string) {
		$url = trim( $string );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		// Rewrite these to https as needed
		$secure = apply_filters( 'syn_rewrite_secure', array( 'facebook.com', 'twitter.com' ) );
		if ( in_array( self::extract_domain_name( $url ), $secure ) ) {
			$url = preg_replace( '/^http:/i', 'https:', $url );
		}
		$url = esc_url_raw( $url );
		return $url;
	}

	/* Meta box setup function. */
	public static function setup() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Syn_Meta', 'add_meta_boxes' ) );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public static function add_meta_boxes() {
		$screens = array( 'post', 'page' );
		$screens = apply_filters( 'syn_post_types', $screens );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'synbox-meta',      // Unique ID
				esc_html__( 'Syndication Links', 'Syn Links' ),    // Title
				array( 'Syn_Meta', 'metabox' ),   // Callback function
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
		_e( 'One URL per line.', 'Syn Links' );
		echo '</label></p>';
		echo "<textarea name='syndication_urls' rows='4' cols='70'>";
		if ( ! empty( $meta ) ) {echo $meta; }
		echo '</textarea>';
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
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		if ( isset( $_POST['syndication_urls'] ) ) {
			if ( empty( $_POST['syndication_urls'] ) ) {
				delete_post_meta( $post_id, 'syndication_urls' );
			} else {
				$meta = self::clean_urls( explode( "\n", $_POST['syndication_urls'] ) );
				update_post_meta( $post_id, 'syndication_urls', implode( "\n", $meta ) );
			}
		}
	}

	public static function get_network_strings() {
		$strings = array(
			'twitter.com' => _x( 'Twitter', 'Syn Links' ),
			'facebook.com' => _x( 'Facebook', 'Syn Links' ),
			'plus.google.com' => _x( 'Google+', 'Syn Links' ),
			'instagram.com' => _x( 'Instagram', 'Syn Links' ),
			'flickr.com' => _x( 'Flickr', 'Syn Links' ),
			'youtube.com' => _x( 'YouTube', 'Syn Links' ),
			'linkedin.com' => _x( 'LinkedIn', 'Syn Links' ),
			'tumblr.com' => _x( 'Tumblr', 'Syn Links' ),
			'wordpress.com' => _x( 'WordPress', 'Syn Links' ),
			'news.indiewebcamp.com' => _x( 'IndieNews', 'Syn Links' ),
		);
		return apply_filters( 'syn_network_strings', $strings );
	}

	public static function extract_domain_name($url) {
		$parse = wp_parse_url( $url );
		return preg_replace( '/^www\./', '', $parse['host'] );
	}

	public static function get_icon( $domain ) {
		// Supportedicons.
			$icons = array(
				'default'         => 'share',
				'amazon.com'      => 'amazon',
				'behance.net'     => 'behance',
				'blogspot.com'    => 'blogger',
				'codepen.io'      => 'codepen',
				'dribbble.com'    => 'dribbble',
				'dropbox.com'     => 'dropbox',
				'eventbrite.com'  => 'eventbrite',
				'facebook.com'    => 'facebook',
				'flickr.com'      => 'flickr',
				// feed
				'foursquare.com'  => 'foursquare',
				'ghost.org' 					=> 'ghost',
				'plus.google.com' => 'google-plus',
				'github.com'      => 'github',
				'instagram.com'   => 'instagram',
				'linkedin.com'    => 'linkedin',
				'mailto:'         => 'mail',
				'medium.com'      => 'medium',
				'path.com'        => 'path',
				'pinterest.com'   => 'pinterest',
				'getpocket.com'   => 'pocket',
				'polldaddy.com'   => 'polldaddy',
				// print
				'reddit.com'      => 'reddit',
				'squarespace.com' => 'squarespace',
				'skype.com'       => 'skype',
				'skype:'          => 'skype',
				// share
				'soundcloud.com'  => 'cloud',
				'spotify.com'     => 'spotify',
				'stumbleupon.com' => 'stumbleupon',
				'telegram.org'    => 'telegram',
				'tumblr.com'      => 'tumblr',
				'twitch.tv'       => 'twitch',
				'twitter.com'     => 'twitter',
				'vimeo.com'       => 'vimeo',
				'whatsapp.com'    => 'whatsapp',
				'wordpress.org'   => 'wordpress',
				'wordpress.com'   => 'wordpress',
				'youtube.com'     => 'youtube',
			);
			// Substitute another domain to sprite map
			$icons = apply_filters( 'syndication_domain_icons', $icons );
			$icon = $icons['default'];
			if ( array_key_exists( $domain, $icons ) ) {
				$icon = $icons[ $domain ];
			}
			// Substitute another svg sprite file
			$sprite = apply_filters( 'syndication_icon_sprite', plugin_dir_url( __FILE__ ) . 'social-logos.svg', $domain );
			return '<svg class="svg-icon ' . 'svg-' . $icon . '" aria-hidden="true"><use xlink:href="' . $sprite . '#' . $icon . '"></use><svg>';
	}




	public static function get_syndication_links_data( $post_ID = null ) {
		if ( ! $post_ID ) {
			$post_ID = get_the_ID();
		}
		$urls = explode( "\n", get_post_meta( $post_ID, 'syndication_urls', true ) );
		// Mf2_syndication is used by the Micropub plugin
		$mf2 = explode( "\n", get_post_meta( $post_ID, 'mf2_syndication', true ) );
		// Clean and dudupe
		$urls = Syn_Meta::clean_urls( array_merge( $urls, $mf2 ) );
		// Allow URLs to be added by other plugins
		return apply_filters( 'syn_add_links', $urls, $post_ID );
	}


	public static function get_syndication_links( $post_ID = null ) {
		$options = get_option( 'syndication_content_options' );
		if ( ! $post_ID ) {
			$post_ID = get_the_ID();
		}
		$urls = self::get_syndication_links_data( $post_ID );
		if ( empty( $urls ) ) {
			return '';
		}
		$strings = self::get_network_strings();
		$single = is_single( $post_ID );
		$synlinks = '<span class="relsyn"><ul>' . $options['text_before'];
		foreach ( $urls as $url ) {
			if ( empty( $url ) ) { continue; }
			$domain = self::extract_domain_name( $url );
			if ( array_key_exists( $domain, $strings ) ) {
				$name = $strings[ $domain ];
			} else {
				$name = $domain;
			}
			$synlinks .= '<li><a title="' . $name . '" class="u-syndication" href="' . esc_url( $url ) . '"';
			if ( $single ) {
				$synlinks .= ' rel="syndication">';
				if ( '1' === $options['just_icons'] ) {
					$synlinks .= '<span class="syn-name">' . $name . '</span>';
				}
				$synlinks .= self::get_icon( $domain );
				$synlinks .= '</a></li>';
			} else {
				$synlinks .= '>';
				$synlinks .= self::get_icon( $domain );
				$synlinks .= '</a></li>';
			}
		}
		$synlinks .= '</ul></span>';
		return $synlinks;

	}

} // End Class


function get_syndication_links( $post_ID = null ) {
	return Syn_Meta::get_syndication_links( $post_ID );
}

?>
