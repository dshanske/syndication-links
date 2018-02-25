<?php

add_action( 'init', array( 'Social_Plugins', 'init' ) );

class Social_Plugins {
	public static function init() {
		add_filter( 'syn_add_links', array( 'Social_Plugins', 'add_syn_plugins' ) );
	}

	public static function array_flatten($array) { 
		  if (!is_array($array)) { 
			      return false; 
			        } 
		    $result = array(); 
		    foreach ($array as $key => $value) { 
			        if (is_array($value)) { 
					      $result = array_merge($result, self::array_flatten($value)); 
					          } else { 
							        $result[$key] = $value; 
								    } 
				  } 
		    return $result;
	}

	public static function add_syn_plugins( $urls ) {
		$see_on = array();
//		if ( defined( 'NextScripts_SNAP_Version' ) ) {
//			$see_on = array_merge( $see_on, self::add_links_from_snap() );
//		}

		$keys = get_post_meta( get_the_ID() );
		$keys = array_keys( $keys );
		foreach(  $keys as $key ) {
			if ( 0 === strpos( $key, 'snap' ) && 6 === strlen( $key ) ) {
				$meta = get_post_meta( get_the_ID(), $key, true );
				$meta = maybe_unserialize( $meta );
				$meta = self::array_flatten( $meta );
				if ( isset( $meta['postURL'] ) ) {
					$see_on[] = $meta['postURL'];
				}
			}
		}
		// Support for the Official Medium Plugin per request @chrisaldrich
		if ( class_exists( 'Medium_Post' ) ) {
			$medium_post = Medium_Post::get_by_wp_id( get_the_ID() );
			$see_on[]    = $medium_post->url;
		}
		// Support for Mastodon Autopost https://github.com/dshanske/syndication-links/issues/75
		if ( function_exists( 'mastodon_autopost_ajax_handler' ) ) {
			$mastodon = get_post_meta( get_the_ID(), 'mastodonAutopostLastSuccessfullPostURL', true );
			if ( $mastodon ) {
				$see_on[] = $mastodon;
			}
		}
		// Support for Keyring Social Importer fields https://github.com/dshanske/syndication-links/issues/73
		if ( class_exists( 'Keyring_Importer_Base' ) ) {
			$keyrings = array( 'href', 'flickr_url', 'instagram_url', 'pinterest_url', 'twitter_permalink' );
			foreach( $keyrings as $keyring ) {
				$keyr = get_post_meta( get_the_ID(), $keyring, true );
				if ( $keyr ) {
					$see_on[] = $keyr;
				}
			}
		}
		return array_merge( $see_on, $urls );
	}

	public static function add_links_from_snap() {
		global $nxs_snapAvNts;
		global $post;
		$broadcasts   = array();
		$snap_options = get_option( 'NS_SNAutoPoster' );
		$urlmap       = array(
			'AP' => array(),
			'BG' => array(),
			'DA' => array(),
			'DI' => array(),
			'DL' => array(),
			'FB' => array( 'url' => '%BASE%/posts/%pgID%' ),
			'FF' => array(),
			'FL' => array(),
			'FP' => array(),
			'GP' => array(),
			'IP' => array(),
			'LI' => array(),
			'LJ' => array(),
			'PK' => array(),
			'PN' => array(),
			'SC' => array(),
			'ST' => array(),
			'SU' => array(),
			'TR' => array( 'url' => '%BASE%/post/%pgID%' ),
			'TW' => array( 'url' => '%BASE%/status/%pgID%' ),
			'VB' => array(),
			'VK' => array(),
			'WP' => array(),
			'YT' => array(),
		);
		foreach ( $nxs_snapAvNts as $key => $serv ) {
			/* all SNAP entries are in separate meta entries for the post based on the service name's "code" */
			$mkey   = 'snap' . $serv['code'];
			$urlkey = $serv['lcode'] . 'URL';
			$okey   = $serv['lcode'];
			$metas  = maybe_unserialize( get_post_meta( get_the_ID(), $mkey, true ) );
			if ( ! empty( $metas ) && is_array( $metas ) ) {
				foreach ( $metas as $cntr => $m ) {
					$url = false;
					if ( isset( $m['isPosted'] ) && 1 === (int) $m['isPosted'] ) {
						if ( isset( $m['postURL'] ) && ! filter_var( $m['postURL'], FILTER_VALIDATE_URL ) ) {
							$url = $m['postURL'];
						} else {
							$base = ( isset( $urlmap[ $serv['code'] ]['url'] ) ) ? $urlmap[ $serv['code'] ]['url'] : false;
							if ( false !== $base ) {
								/* Facebook exception, why not */
								if ( 'FB' === $serv['code'] ) {
									$pos  = strpos( $m['pgID'], '_' );
									$pgID = ( false === $pos ) ? $m['pgID'] : substr( $m['pgID'], $pos + 1 );
								} else {
									$pgID = $m['pgID'];
								}
								$o       = $snap_options[ $okey ][ $cntr ];
								$search  = array( '%BASE%', '%pgID%' );
								$replace = array( $o[ $urlkey ], $pgID );
								$url     = str_replace( $search, $replace, $base );
							}
						}
						if ( false !== $url ) {
							$url          = preg_replace( '~(^|[^:])//+~', '\\1/', $url );
							$broadcasts[] = $url;
						}
					}
				}
			}
		}
		return $broadcasts;
	}

} // End Class
