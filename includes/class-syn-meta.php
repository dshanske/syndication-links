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
		$args = array(
			// 'sanitize_callback' => '',
			'type' => 'array',
			'description' => 'Syndication URLs',
			'single' => false,
			'show_in_rest' => true,
		);
		register_meta( 'post', 'mf2_syndication', $args );
		add_filter( 'query_vars', array( 'Syn_Meta', 'query_var' ) );
		add_action( 'parse_query', array( 'Syn_Meta', 'parse_query' ) );
	}

	public static function query_var( $vars ) {
		$vars[] = 'original-of';
			return $vars;
	}

	public static function parse_query( $wp ) {
		if ( ! array_key_exists( 'original-of', $wp->query_vars ) ) {
			return;
		}
		$url = $wp->get( 'original-of' );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			include( get_404_template() );
			exit;
		}
		$url = esc_url_raw( $url );
		$args = array(
			'fields' => 'ids',
			'meta_key' => 'mf2_syndication',
			'meta_query' => array(
				'key' => 'mf2_syndication',
				'value' => $url,
				'compare' => 'LIKE',
			),
		);
		$posts = get_posts( $args );
		if ( empty( $posts ) ) {
			include( get_404_template() );
			exit;
		}
		wp_redirect( get_permalink( $posts[0] ) );
	}


	/*
	Filters incoming URLs.
	 *
	 * @param array $urls An array of URLs to filter.
	 * @return array A filtered array of unique URLs.
	 * @uses clean_url
	 */
	public static function clean_urls($urls) {
		if ( ! is_array( $urls ) ) {
			return $urls;
		}
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
		if ( is_array( $string ) ) {
			return $string;
		}
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
				esc_html__( 'Syndication Links', 'syndication-links' ),    // Title
				array( 'Syn_Meta', 'metabox' ),   // Callback function
				$screen,         // Admin page (or post type)
				'normal',         // Context
				'default'         // Priority
			);
		}
	}

	public static function metabox( $object, $box ) {
		wp_nonce_field( 'syn_metabox', 'syn_metabox_nonce' );
		$meta = self::get_syndication_links_data( $object->ID );
		if ( is_array( $meta ) ) {
			$meta = implode( PHP_EOL, $meta );
		}
		echo '<p><label for="syndication_urls">';
		_e( 'One URL per line.', 'Syn Links' );
		echo '</label></p>';
		echo '<textarea name="syndication_urls" id="syndication_urls" style="width:99%" rows="4" cols="40">';
		if ( is_string( $meta ) ) {
			echo $meta;
		}
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
		if ( empty( $_POST['syndication_urls'] ) ) {
			delete_post_meta( $post_id, 'mf2_syndication' );
		} else {
			$meta = explode( PHP_EOL, $_POST['syndication_urls'] );
			$meta = self::clean_urls( $meta );
			update_post_meta( $post_id, 'mf2_syndication', $meta );
		}
	}

	public static function get_network_strings() {
		$strings = array(
			'amazon.com' => _x( 'Amazon', 'syndication-links' ),
			'behance.net' => _x( 'Behance', 'syndication-links' ),
			'blogspot.com' => _x( 'Blogger', 'syndication-links' ),
			'codepen.io' => _x( 'codepen', 'syndication-links' ),
			'dribbble.com' => _x( 'Dribbble', 'syndication-links' ),
			'dropbox.com' => _x( 'Dropbox', 'syndication-links' ),
			'eventbrite.com' => _x( 'Eventbrite', 'syndication-links' ),
			'facebook.com' => _x( 'Facebook', 'syndication-links' ),
			'flickr.com' => _x( 'Flickr', 'syndication-links' ),
			'foursquare.com'  => _x( 'Foursquare', 'syndication-links' ),
			'ghost.org' => _x( 'Ghost', 'syndication-links' ),
			'plus.google.com' => _x( 'Google+', 'syndication-links' ),
			'github.com' => _x( 'Github', 'syndication-links' ),
			'instagram.com' => _x( 'Instagram', 'syndication-links' ),
			'linkedin.com' => _x( 'LinkedIn', 'syndication-links' ),
			'medium.com' => _x( 'Medium', 'syndication-links' ),
			'path.com' => _x( 'Path', 'syndication-links' ),
			'pinterest.com' => _x( 'Pinterest', 'syndication-links' ),
			'getpocket.com' => _x( 'Pocket', 'syndication-links' ),
			'polldaddy.com' => _x( 'PollDaddy', 'syndication-links' ),
			'reddit.com' => _x( 'Reddit', 'syndication-links' ),
			'squarespace.com' => _x( 'Squarespace', 'syndication-links' ),
			'skype.com' => _x( 'Skype', 'syndication-links' ),
			'soundcloud.com' => _x( 'SoundCloud', 'syndication-links' ),
			'spotify.com' => _x( 'Spotify', 'syndication-links' ),
			'stumbleupon.com' => _x( 'StumbleUpon', 'syndication-links' ),
			'telegram.org' => _x( 'Telegram', 'syndication-links' ),
			'tumblr.com' => _x( 'Tumblr', 'syndication-links' ),
			'twitch.tv' => _x( 'Twitch', 'syndication-links' ),
			'twitter.com' => _x( 'Twitter', 'syndication-links' ),

			'wordpress.com' => _x( 'WordPress', 'syndication-links' ),
			'youtube.com' => _x( 'YouTube', 'syndication-links' ),

			'news.indiewebcamp.com' => _x( 'IndieNews', 'syndication-links' ),
		);
		return apply_filters( 'syn_network_strings', $strings );
	}

	public static function extract_domain_name($url, $subdomain = false) {
		$parse = wp_parse_url( $url, PHP_URL_HOST );
		if ( $subdomain ) {
			return preg_replace( '/^www\./', '', $parse );
		}
		return preg_replace( '/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/', '$2', $parse );
	}

	public static function get_icon( $domain ) {
		// Supportedicons.
			$icons = array(
				'default'         => 'website',
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
				'ghost.org' 	=> 'ghost',
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
				'soundcloud.com'  => 'soundcloud',
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
			return '<svg class="svg-icon svg-' . $icon . '" aria-hidden="true"><use xlink:href="' . $sprite . '#' . $icon . '"></use></svg>';
	}


	public static function add_link( $post_id = null, $uri ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( empty( $uri ) ) {
			return;
		}
		$links = get_post_meta( $post_id, 'mf2_syndication' );
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		if ( is_string( $uri ) ) {
			$links[] = $uri;
		}
		if ( is_array( $uri ) ) {
			$links = array_merge( $links, $uri );
		}
		$links = self::clean_urls( $links );
		if ( empty( $links ) ) {
			return;
		} else {
			update_post_meta( $post_id, 'mf2_syndication', $links );
		}
	}



	public static function get_syndication_links_data( $post_ID = null ) {
		if ( ! $post_ID ) {
			$post_ID = get_the_ID();
		}
		$urls = get_post_meta( $post_ID, 'mf2_syndication', true );
		if ( $urls ) {
			if ( is_string( $urls ) ) {
				$urls = explode( "\n", $urls );
			}
		} else {
			$urls = array();
		}
		$old = get_post_meta( $post_ID, 'syndication_urls', true );
		if ( $old ) {
			$old = explode( "\n", $old );
			if ( is_array( $old ) ) {
				$urls = array_filter( array_unique( array_merge( $urls, $old ) ) );
				update_post_meta( $post_ID, 'mf2_syndication', $urls );
				delete_post_meta( $post_ID, 'syndication_urls' );
			}
		}
		if ( empty( $urls ) ) {
			return array();
		}

		// Allow adding of additional links before display
		return apply_filters( 'syn_add_links', $urls, $post_ID );
	}


	public static function get_syndication_links( $post_ID = null, $args = array() ) {
		if ( ! $post_ID ) {
			$post_ID = get_the_ID();
		}
		$display = get_option( 'syndication-links_display' );
		if ( ! is_singular() ) {
			$display = get_option( 'syndication-links_archives' ) ? $display : 'hidden';
		}
		$defaults = array(
			'style' => 'ul',
			'text' => in_array( $display, array( 'text', 'iconstext' ) ),
			'icons' => in_array( $display, array( 'icons', 'iconstext' ) ),
			'container-css' => 'relsyn',
			'single-css' => 'syn-link',
		);
		$r = wp_parse_args( $args, $defaults );

		$urls = self::get_syndication_links_data( $post_ID );
		if ( empty( $urls ) ) {
			return '';
		}
		$strings = self::get_network_strings();
		$rel = is_single() ? ' rel="syndication">' : '>';
		$links = array();
		foreach ( $urls as $url ) {
			if ( empty( $url ) || ! is_string( $url ) ) { continue; }
			$domain = self::extract_domain_name( $url );
			$name = ( array_key_exists( $domain, $strings ) ) ? $strings[ $domain ] : $domain;
			$syn = ( $r['icons'] ? self::get_icon( $domain ) : '') . ( $r['text'] ? $name : '');

			$links[] = sprintf( '<a aria-label="%1$s" class="syn-link u-syndication" href="%2$s"%3$s %4$s</a>', $name, esc_url( $url ), $rel, $syn );
		}
		$textbefore = ( 'hidden' !== $display ) ? get_option( 'syndication-links_text_before' ) : '';

		switch ( $r['style'] ) {
			case 'p':
				$before = '<p class="' . $r['container-css']  . '"><span>';
				$sep = ' ';
				$after = '</p>';
				break;
			case 'ol':
				$before = '<ol class="' . $r['container-css'] . '"><li>';
				$sep = '</li><li>';
				$after = '</li></ol>';
				break;

			default:
				$before = '<ul class="' . $r['container-css'] . '"><li>';
				$sep = '</li><li>';
				$after = '</li></ul>';
		}

		return $textbefore . $before . join( $sep, $links ) . $after;
	}

} // End Class


function get_syndication_links( $post_ID = null, $args = array() ) {
	return Syn_Meta::get_syndication_links( $post_ID, $args );
}

?>
