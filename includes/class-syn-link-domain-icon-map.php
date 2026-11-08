<?php

defined( 'ABSPATH' ) || exit;

// Rebuild the cached Mastodon hosts when a user's Mastodon URL changes, or a user is deleted.
add_action( 'added_user_meta', array( 'Syn_Link_Domain_Icon_Map', 'mastodon_meta_changed' ), 10, 3 );
add_action( 'updated_user_meta', array( 'Syn_Link_Domain_Icon_Map', 'mastodon_meta_changed' ), 10, 3 );
add_action( 'deleted_user_meta', array( 'Syn_Link_Domain_Icon_Map', 'mastodon_meta_changed' ), 10, 3 );
add_action( 'deleted_user', array( 'Syn_Link_Domain_Icon_Map', 'flush_mastodon_urls' ) );

/* Maps domain names to icons from the provided SVG fontset
 */
class Syn_Link_Domain_Icon_Map {

	// Common mappings and custom mappings
	private static $map = array(
		'twitter.com'         => 'x',
		'facebook.com'        => 'facebook',
		'swarmapp.com'        => 'swarm',
		'instagram.com'       => 'instagram',
		'play.google.com'     => 'googleplay',
		'plus.google.com'     => 'googleplus',
		'podcasts.google.com' => 'googlepodcasts',
		'podcasts.apple.com'  => 'applepodcasts',
		'indieweb.xyz'        => 'info',
		'getpocket.com'       => 'pocket',
		'flip.it'             => 'flipboard',
		'micro.blog'          => 'microdotblog',
		'wordpress.org'       => 'wordpress',
		'wordpress.com'       => 'wordpress',
		'itunes.apple.com'    => 'applemusic',
		'reading.am'          => 'book',
		'blogspot.com'        => 'blogger',
		'astral.ninja'        => 'nostr',
		'nos.social'          => 'nostr',
		'iris.to'             => 'nostr',
		'snort.social'        => 'nostr',
		'app.coracle.social'  => 'nostr',
		'primal.net'          => 'nostr',
		'habla.news'          => 'nostr',
		'nostr.band'          => 'nostr',
		'news.indieweb.org'   => 'indieweb',
		'bsky.app'            => 'bluesky',
		'bsky.social'         => 'bluesky',
		'bookwyrm.social'     => 'bookwyrm',
		'getrevue.co'         => 'revue',
		'del.icio.us'         => 'delicious',

	);

	// Try to get the correct icon for the majority of sites
	public static function split_domain( $string ) {
		$explode = explode( '.', $string );
		if ( 2 === count( $explode ) ) {
			return $explode[0];
		}
		if ( 3 === count( $explode ) ) {
			return $explode[1];
		}
		return $string;
	}

	// Return directories for icon files
	public static function get_icon_file_dirs() {
		$dir  = sprintf( '%1$ssvgs/', plugin_dir_path( __DIR__ ) );
		$dirs = array(
			$dir . 'simple-icons',
			$dir . 'genericons-neue',
			$dir,
			self::get_historic_icon_dir(),
		);
		return apply_filters( 'syn_icon_file_dirs', $dirs );
	}

	/**
	 * Directory of the historic icons: retired services that old posts still link to.
	 *
	 * @since 5.0.0
	 *
	 * @return string Directory path, without a trailing slash.
	 */
	public static function get_historic_icon_dir() {
		return sprintf( '%1$ssvgs/historic', plugin_dir_path( __DIR__ ) );
	}

	/**
	 * Whether an icon is one of the historic icons.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name The icon name.
	 * @return bool True if the icon file found for the name is in the historic directory.
	 */
	public static function is_historic( $name ) {
		$file = self::get_icon_filename( $name );
		return $file && dirname( $file ) === self::get_historic_icon_dir();
	}

	/**
	 * Enqueue the stylesheet with the historic icons' colours.
	 *
	 * Called when a historic icon is rendered, so the stylesheet only loads on
	 * pages that show one. Styles enqueued after the head are printed in the
	 * footer.
	 *
	 * @since 5.0.0
	 */
	public static function enqueue_historic_style() {
		// Black-and-white mode doesn't use brand colours.
		if ( '1' === get_option( 'syndication-links_bw' ) ) {
			return;
		}

		/**
		 * Filters whether to load the historic icons' stylesheet when a historic icon is shown.
		 *
		 * @since 5.0.0
		 *
		 * @param bool $load Whether to load the stylesheet. Default true.
		 */
		if ( ! apply_filters( 'syn_load_historic_icon_style', true ) ) {
			return;
		}

		wp_enqueue_style( 'syndication-links-historic', plugin_dir_url( __DIR__ ) . 'css/syn-historic.min.css', array(), SYNDICATION_LINKS_VERSION );
	}


	// Return the filename of an icon based on name if the file exists
	public static function get_icon_filename( $name ) {
		// The name is used as a filename, so don't allow anything that could leave the directory.
		if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9._-]+$/iD', $name ) || false !== strpos( $name, '..' ) ) {
			return null;
		}
		$dirs = self::get_icon_file_dirs();
		$svg  = $name . '.svg';
		foreach ( $dirs as $dir ) {
			$dir = trailingslashit( $dir );
			if ( file_exists( $dir . $svg ) ) {
				return $dir . $svg;
			}
		}
		return null;
	}

	// Return the retrieved svg based on name
	public static function get_icon_svg( $name ) {
		$icon = apply_filters( 'pre_syn_link_icon', null, $name );
		if ( is_string( $icon ) ) {
			return $icon;
		}

		$file = self::get_icon_filename( $name );
		if ( $file ) {
			$icon = file_get_contents( $file ); // phpcs:ignore
			if ( $icon ) {
				return $icon;
			}
		}
		return null;
	}

	public static function get_icon( $name ) {
		$icon  = self::get_icon_svg( $name );
		$title = self::get_title( $name );
		if ( $icon ) {
			if ( self::is_historic( $name ) ) {
				self::enqueue_historic_style();
			}
			return sprintf( '<span class="syndication-link-icon svg-%1$s" style="display: inline-block; max-width: 1rem; margin: 2px;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $name ), esc_attr( $title ), $icon );
		}
		// No icon: fall back to the name as text.
		return esc_html( $name );
	}

	public static function get_title( $name ) {
		$strings = simpleicons_syn_get_names();
		if ( isset( $strings[ $name ] ) ) {
			$return = $strings[ $name ];
		} else {
			$return = $name;
		}
		return apply_filters( 'syn_link_title', $return, $name );
	}

	/**
	 * Clears the cached Mastodon hosts when a user's `mastodon` meta changes.
	 *
	 * @since 5.0.0
	 *
	 * @param int|int[] $meta_id   Meta ID(s).
	 * @param int       $object_id User ID.
	 * @param string    $meta_key  Meta key.
	 */
	public static function mastodon_meta_changed( $meta_id, $object_id, $meta_key ) {
		if ( 'mastodon' === $meta_key ) {
			self::flush_mastodon_urls();
		}
	}

	/**
	 * Clears the cached Mastodon hosts.
	 *
	 * @since 5.0.0
	 */
	public static function flush_mastodon_urls() {
		delete_transient( 'syn_mastodon_hosts' );
	}

	public static function mastodon_urls() {
		$mastodon = get_transient( 'syn_mastodon_hosts' );
		if ( false !== $mastodon && is_array( $mastodon ) ) {
			return $mastodon;
		}
		$args    = array(
			'count_total' => false,
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cached in a transient for a day.
				array(
					'key'     => 'mastodon',
					'compare' => 'EXISTS',
				),
			),
			'fields'      => 'ID',
		);
		$query   = new WP_User_Query( $args );
		$results = $query->get_results();
		$value   = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$url = get_user_meta( $result, 'mastodon', true );
				if ( ! empty( $url ) && wp_http_validate_url( $url ) ) {
					$value[] = wp_parse_url( $url, PHP_URL_HOST );
				}
			}
		}
		// Expire it, so it isn't autoloaded and does get refreshed. The old name had no expiration.
		set_transient( 'syn_mastodon_hosts', $value, DAY_IN_SECONDS );
		delete_transient( 'syn_mastodon' );
		return $value;
	}

	public static function url_to_name( $url ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		// The default if not an http link is to return notice
		$return = 'notice';
		if ( ( 'http' === $scheme ) || ( 'https' === $scheme ) ) {
			$return = 'website'; // default for web links
			$url    = strtolower( $url );
			$domain = (string) wp_parse_url( $url, PHP_URL_HOST );

			$domain = str_replace( 'www.', '', $domain ); // Always remove www

			// If the domain is already on the pre-loaded list then use that
			if ( array_key_exists( $domain, self::$map ) ) {
				$return = self::$map[ $domain ];
			} elseif ( in_array( $domain, self::mastodon_urls(), true ) ) {
				$return = 'mastodon';
			} else {
				// Remove extra info and try to map it to an icon
				$strip = self::split_domain( $domain );
				if ( self::get_icon_filename( $strip ) ) {
					$return = $strip;
				} elseif ( self::get_icon_filename( str_replace( '.', '-dot-', $domain ) ) ) {
					$return = str_replace( '.', '-dot-', $domain );
				} elseif ( self::get_icon_filename( str_replace( '.', '', $domain ) ) ) {
					$return = str_replace( '.', '', $domain );
				} else if ( false !== stripos( $domain, 'wordpress' ) ) { // phpcs:ignore
					// Anything with WordPress in the name that is not matched return WordPress
					$return = 'wordpress'; // phpcs:ignore
				} else if ( false !== stripos( $domain, 'read' ) ) { // phpcs:ignore
					// Anything with read in the name that is not matched return a book
					$return = 'book'; // phpcs:ignore
				} else if ( false !== stripos( $domain, 'news' ) ) { // phpcs:ignore
					// Anything with news in the name that is not matched return the summary icon
					$return = 'summary'; // phpcs:ignore
				} else {
					// Some domains have the word app in them check for matches with that
					$strip = str_replace( 'app', '', $strip );
					if ( self::get_icon_filename( $strip ) ) {
						$return = $strip;
					}
				}
			}
		}
		// Non-web links: mail and phone have their own icons, anything else is a notice.
		$schemes = array(
			'mailto' => 'mail',
			'sms'    => 'phone',
			'tel'    => 'phone',
		);
		if ( isset( $schemes[ $scheme ] ) ) {
			$return = $schemes[ $scheme ];
		}
		// Save the determined mapping into the map so that it will not have to look again on the same page load.
		// Only web links have a domain to cache.
		if ( isset( $domain ) ) {
			self::$map[ $domain ] = $return;
		}
		$return = apply_filters( 'syn_link_mapping', $return, $url );
		return $return;
	}
}
