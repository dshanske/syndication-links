<?php
/* Maps domain names to icons from the provided SVG fontset
 */
class Syn_Link_Domain_Icon_Map {

	// Common mappings and custom mappings
	private static $map = array(
		'twitter.com'         => 'twitter',
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

	// Return the filename of an icon based on name if the file exists
	public static function get_icon_filename( $name ) {
		$svg = sprintf( '%1$ssvgs/%2$s.svg', plugin_dir_path( __DIR__ ), $name );
		if ( file_exists( $svg ) ) {
			return $svg;
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
			return sprintf( '<span class="syndication-link-icon svg-%1$s" style="display: inline-block; max-width: 1rem; margin: 2px;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $name ), esc_attr( $title ), $icon );
		}
		return $name;
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

	public static function mastodon_urls() {
		$mastodon = get_transient( 'syn_mastodon' );
		if ( false !== $mastodon && is_array( $mastodon ) ) {
			return $mastodon;
		}
		$args    = array(
			'count_total' => false,
			'meta_query'  => array(
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
					$value[] = wp_parse_url( $value, PHP_URL_HOST );
				}
			}
		}
		set_transient( 'syn_mastodon', $value );
		return $value;
	}

	public static function url_to_name( $url ) {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		// The default if not an http link is to return notice
		$return = 'notice';
		if ( ( 'http' === $scheme ) || ( 'https' === $scheme ) ) {
			$return = 'website'; // default for web links
			$url    = strtolower( $url );
			$domain = wp_parse_url( $url, PHP_URL_HOST );

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
		// Save the determined mapping into the map so that it will not have to look again on the same page load
		self::$map[ $domain ] = $return;
		$return               = apply_filters( 'syn_link_mapping', $return, $url );
		return $return;
	}
}
