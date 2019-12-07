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
		'news.indieweb.org'   => 'website',
		'getpocket.com'       => 'pocket',
		'flip.it'             => 'flipboard',
		'micro.blog'          => 'micro-dot-blog',
		'wordpress.org'       => 'wordpress',
		'itunes.apple.com'    => 'applemusic',
		'reading.am'          => 'book',

	);

	// Try to get the correct icon for the majority of sites by dropping
	public static function split_domain( $string ) {
		// Strip things we know we dont want. Not every TLD but the common ones in the fontset
		$unwanted = array( '-', '.com', '.org', '.net', '.io', '.in', '.tv', '.fm', '.social' );
		// Strip these
		$string = str_replace( $unwanted, '', $string );
		// Strip the dot if it is a TLD other than the above
		$string = str_replace( '.', '', $string );
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
			return sprintf( '<span class="svg-icon svg-%1$s" style="display: inline-block; max-width: 1rem; margin: 2px;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $name ), esc_attr( $title ), $icon );
		}
		return $name;
	}

	public static function get_title( $name ) {
		$strings = simpleicons_syn_get_names();
		if ( isset( $strings[ $name ] ) ) {
			return $strings[ $name ];
		}
		return $name;
	}

	public static function url_to_name( $url ) {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		// The default if not an http link is to return notice
		$return = 'notice';
		if ( ( 'http' === $scheme ) || ( 'https' === $scheme ) ) {
			$return = 'website'; // default for web links
			$url    = strtolower( $url );
			$domain = preg_replace( '/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/', '$2', wp_parse_url( $url, PHP_URL_HOST ) );
			$domain = str_replace( 'www.', '', $domain ); // Always remove www

			// If the domain is already on the pre-loaded list then use that
			if ( array_key_exists( $domain, self::$map ) ) {
				$return = self::$map[ $domain ];
			} else {
				// Remove extra info and try to map it to an icon
				$strip   = self::split_domain( $domain );
				$strings = array_keys( simpleicons_syn_get_names() );
				if ( in_array( $strip, array_keys( $strings ), true ) ) {
					$return = $strip;
				} 				else if ( false !== stripos( $domain, 'wordpress' ) ) { // phpcs:ignore
					// Anything with WordPress in the name that is not matched return WordPress
					$return = 'wordpress'; // phpcs:ignore
				} else {
					// Some domains have the word app in them check for matches with that
					$strip = str_replace( 'app', '', $strip );
					if ( in_array( $strip, $strings, true ) ) {
						$return = $strip;
					}
				}
			}
		}
		return apply_filters( 'syn_link_mapping', $return, $url );
	}

}

