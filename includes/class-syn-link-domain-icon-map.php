<?php
/**
 * Mapping links to icons.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

// Rebuild the cached Mastodon hosts when a user's Mastodon URL changes, or a user is deleted.
add_action( 'added_user_meta', array( 'Syn_Link_Domain_Icon_Map', 'mastodon_meta_changed' ), 10, 3 );
add_action( 'updated_user_meta', array( 'Syn_Link_Domain_Icon_Map', 'mastodon_meta_changed' ), 10, 3 );
add_action( 'deleted_user_meta', array( 'Syn_Link_Domain_Icon_Map', 'mastodon_meta_changed' ), 10, 3 );
add_action( 'deleted_user', array( 'Syn_Link_Domain_Icon_Map', 'flush_mastodon_urls' ) );

/**
 * Maps a link's domain to an icon name, and renders the icons.
 *
 * Icons are SVG files, looked up by name in the directories from get_icon_file_dirs().
 */
class Syn_Link_Domain_Icon_Map {

	/**
	 * Icon names for domains whose name doesn't match their icon.
	 *
	 * Also caches, for the request, the names already worked out for other domains.
	 *
	 * @var string[]
	 */
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

	/**
	 * Returns the name part of a domain: `example.com` and `www.example.com` become `example`.
	 *
	 * @param string $string The domain.
	 * @return string The name, or the domain if it has more than three parts.
	 */
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

	/**
	 * Returns the directories searched for icon files, in order.
	 *
	 * @return string[] Directory paths.
	 */
	public static function get_icon_file_dirs() {
		$dir  = sprintf( '%1$ssvgs/', plugin_dir_path( __DIR__ ) );
		$dirs = array(
			$dir . 'simple-icons',
			$dir . 'genericons-neue',
			$dir,
			self::get_historic_icon_dir(),
		);
		/**
		 * Filters the directories searched for icon files, in order.
		 *
		 * Add a directory to the start to override the bundled icons, or to the end to add icons.
		 *
		 * @param string[] $dirs Directory paths.
		 */
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
	 * Returns the path of an icon's SVG file.
	 *
	 * @param string $name The icon name. Only letters, numbers, dots, dashes and underscores.
	 * @return string|null The path, or null if there's no such icon or the name isn't allowed.
	 */
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

	/**
	 * Returns an icon's SVG markup.
	 *
	 * @param string $name The icon name.
	 * @return string|null The SVG, or null if there's no such icon.
	 */
	public static function get_icon_svg( $name ) {
		/**
		 * Filters an icon's SVG before the icon files are searched.
		 *
		 * Return a string to use it as the icon.
		 *
		 * @param string|null $icon The SVG markup. Default null.
		 * @param string      $name The icon name.
		 */
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

	/**
	 * Returns an icon's markup.
	 *
	 * The icon's brand colour, if it has one, is set as the `--syn-icon-color`
	 * custom property, which the stylesheet uses as the icon's colour.
	 *
	 * @param string $name The icon name.
	 * @return string The icon in a span, or the escaped name if there's no such icon.
	 */
	public static function get_icon( $name ) {
		$icon  = self::get_icon_svg( $name );
		$title = self::get_title( $name );
		if ( $icon ) {
			$color = self::get_color( $name );
			$style = $color ? sprintf( ' style="--syn-icon-color: %s;"', esc_attr( $color ) ) : '';
			return sprintf( '<span class="syndication-link-icon svg-%1$s"%4$s aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $name ), esc_attr( $title ), $icon, $style );
		}
		// No icon: fall back to the name as text.
		return esc_html( $name );
	}

	/**
	 * Returns an icon's brand colour.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name The icon name.
	 * @return string|null The colour as a hex value such as `#1185FE`, or null if the icon has none.
	 */
	public static function get_color( $name ) {
		$colors = simpleicons_syn_get_colors();
		$color  = isset( $colors[ $name ] ) ? $colors[ $name ] : null;

		/**
		 * Filters an icon's brand colour.
		 *
		 * Use it to give a colour to an icon added through `syn_icon_file_dirs` or
		 * `pre_syn_link_icon`, or to change a bundled icon's colour. Return null
		 * for no colour: the icon then takes the link's colour. Colours that
		 * aren't a hex value (`#abc` or `#aabbcc`) are ignored.
		 *
		 * @since 5.0.0
		 *
		 * @param string|null $color The colour, or null if the icon has none.
		 * @param string      $name  The icon name.
		 */
		$color = apply_filters( 'syn_link_icon_color', $color, $name );

		if ( ! is_string( $color ) ) {
			return null;
		}
		$color = sanitize_hex_color( $color );
		return $color ? $color : null;
	}

	/**
	 * Returns an icon's title: the service's name.
	 *
	 * @param string $name The icon name.
	 * @return string The title, or the icon name if it has none.
	 */
	public static function get_title( $name ) {
		$strings = simpleicons_syn_get_names();
		if ( isset( $strings[ $name ] ) ) {
			$return = $strings[ $name ];
		} else {
			$return = $name;
		}
		/**
		 * Filters an icon's title, used as the link text and the icon's label.
		 *
		 * The title is escaped when it's output, so return plain text.
		 *
		 * @param string $return The title.
		 * @param string $name   The icon name.
		 */
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

	/**
	 * Returns the hosts of the Mastodon accounts in users' profiles.
	 *
	 * Read from each user's `mastodon` meta, such as the IndieWeb plugin's Mastodon field,
	 * and cached for a day.
	 *
	 * @return string[] Hosts.
	 */
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

	/**
	 * Returns the icon name for a link.
	 *
	 * Web links are matched by domain, falling back to 'website'. `mailto:` links are
	 * 'mail', `sms:` and `tel:` links are 'phone', and other links are 'notice'.
	 *
	 * @param string $url The link.
	 * @return string The icon name.
	 */
	public static function url_to_name( $url ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		// The default if not an http link is to return notice.
		$return = 'notice';
		if ( ( 'http' === $scheme ) || ( 'https' === $scheme ) ) {
			$return = 'website'; // default for web links.
			$url    = strtolower( $url );
			$domain = (string) wp_parse_url( $url, PHP_URL_HOST );

			$domain = str_replace( 'www.', '', $domain ); // Always remove www.

			// If the domain is already on the pre-loaded list then use that.
			if ( array_key_exists( $domain, self::$map ) ) {
				$return = self::$map[ $domain ];
			} elseif ( in_array( $domain, self::mastodon_urls(), true ) ) {
				$return = 'mastodon';
			} else {
				// Remove extra info and try to map it to an icon.
				$strip = self::split_domain( $domain );
				if ( self::get_icon_filename( $strip ) ) {
					$return = $strip;
				} elseif ( self::get_icon_filename( str_replace( '.', '-dot-', $domain ) ) ) {
					$return = str_replace( '.', '-dot-', $domain );
				} elseif ( self::get_icon_filename( str_replace( '.', '', $domain ) ) ) {
					$return = str_replace( '.', '', $domain );
				} else if ( false !== stripos( $domain, 'wordpress' ) ) { // phpcs:ignore
					// Anything with WordPress in the name that is not matched return WordPress.
					$return = 'wordpress'; // phpcs:ignore
				} else if ( false !== stripos( $domain, 'read' ) ) { // phpcs:ignore
					// Anything with read in the name that is not matched return a book.
					$return = 'book'; // phpcs:ignore
				} else if ( false !== stripos( $domain, 'news' ) ) { // phpcs:ignore
					// Anything with news in the name that is not matched return the summary icon.
					$return = 'summary'; // phpcs:ignore
				} else {
					// Some domains have the word app in them check for matches with that.
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
		/**
		 * Filters the icon name for a link.
		 *
		 * @param string $return The icon name.
		 * @param string $url    The link.
		 */
		$return = apply_filters( 'syn_link_mapping', $return, $url );
		return $return;
	}
}
