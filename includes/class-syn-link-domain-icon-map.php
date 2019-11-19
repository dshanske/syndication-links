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
	);

	public static function getName( $url ) {
		$parsed = parse_url( $url );
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

