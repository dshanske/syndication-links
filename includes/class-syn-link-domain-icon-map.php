<?php
class Syn_Link_Domain_Icon_Map {

	private static $map = array(
		'play.google.com'   => 'googleplay',
		'plus.google.com'   => 'googleplus',
		'indieweb.xyz'      => 'info',
		'news.indieweb.org' => 'website',
		'getpocket.com'     => 'pocket',
		'flip.it'           => 'flipboard',
		'micro.blog'        => 'microblog',
		'wordpress.org'     => 'wordpress',
		'itunes.apple.com'  => 'applemusic',
		'reading.am'        => 'book',

	);


	// Try to get the correct icon for the majority of sites by dropping
	public static function split_domain( $string ) {
		// Strip things we know we dont want. Not every TLD but the common ones in the fontset
		$unwanted = array( 'www.', '-', '.com', '.org', '.net', '.io', '.in', '.tv', '.fm', '.social' );
		// Strip these
		$string = str_replace( $unwanted, '', $string );
		// Strip the dot if it is a TLD other than the above
		$string = str_replace( '.', '', $string );
		return strtolower( $string );
	}

	public static function get_icon_filename( $name ) {
		$svg = sprintf( '%1$ssvgs/%2$s.svg', plugin_dir_path( __DIR__ ), $name );
		if ( file_exists( $svg ) ) {
			return $svg;
		}
		return null;
	}

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
		$icon = self::get_icon_svg( $name );
		if ( $icon ) {
			return sprintf( '<span class="svg-icon svg-%1$s" style="display: inline-block; max-width: 1rem; margin: 2px;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $name ), esc_attr( $name ), $icon );
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
		if ( ( 'http' === $scheme ) || ( 'https' === $scheme ) ) {

			$iconmap = self::get_name( $url );
			if ( false !== $iconmap ) {
				return $iconmap;
			}

			$domain = preg_replace( '/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/', '$2', wp_parse_url( $url, PHP_URL_HOST ) );

			$strip   = self::split_domain( $domain );
			$strings = array_keys( simpleicons_syn_get_names() );
			if ( in_array( $strip, array_keys( $strings ), true ) ) {
				return $strip;
			}

			if ( false !== stripos( $url, 'lanyard' ) ) {
				return 'lanyrd';
			}

			// Anything with WordPress in the name that is not matched return WordPress
			if ( false !== stripos( $domain, 'wordpress' ) ) { // phpcs:ignore
				return 'wordpress'; // phpcs:ignore
			}
			// Some domains have the word app in them check for matches with that
			$strip = str_replace( 'app', '', $strip );
			if ( in_array( $strip, $strings, true ) ) {
				return $strip;
			}
			return apply_filters( 'syn_links_url_to_name', 'website', $url );
		}
		// Not sure why someone would do a scheme other than web for a syndication link
		return 'notice';
	}

	public static function get_name( $url ) {
		$parsed = wp_parse_url( $url );
		$return = false;
		if ( false !== $parsed ) {
			$host = $parsed['host'];
			if ( array_key_exists( $host, self::$map ) ) {
				$return = self::$map[ $host ];
			}
		}
		return apply_filters( 'syn_link_mapping', $return, $url );
	}
}

