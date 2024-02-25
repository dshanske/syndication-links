<?php

add_action( 'init', array( 'Social_Plugins', 'init' ) );

class Social_Plugins {
	public static function init() {
		add_filter( 'get_post_syndication_links', array( 'Social_Plugins', 'add_syn_plugins' ) );
		add_filter( 'syn_links_url_to_name', array( 'Social_Plugins', 'url_to_name_plugins' ), 10, 2 );
		add_action( 'wpt_tweet_posted', array( 'Social_Plugins', 'wptotwitter_to_syn_links' ), 10, 2 );
		add_action( 'autoshare_for_twitter_after_status_update', array( 'Social_Plugins', 'autoshare_for_twitter_after_status_update' ), 10, 3 );
	}

	public static function url_to_name_plugins( $name, $url ) {
		if ( class_exists( 'autopostToMastodon' ) ) {
			$instance = get_option( 'autopostToMastodon-instance', null );
			if ( wp_http_validate_url( $instance ) ) {
				if ( wp_parse_url( $instance, PHP_URL_HOST ) === wp_parse_url( $url, PHP_URL_HOST ) ) {
					return 'mastodon';
				}
			}
		}
	}

	public static function array_flatten( $array ) { 
		if ( ! is_array( $array ) ) { 	 
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
		if ( ! $keys ) {
			return $urls;
		}
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
		if ( class_exists( 'autopostToMastodon' ) ) {
			$mastodon = get_post_meta( get_the_ID(), 'mastodonAutopostLastSuccessfullPostURL', true );
			if ( ! $mastodon ) {
				$mastodon = get_post_meta( get_the_ID(), 'mastodonAutopostLastSuccessfullTootURL', true );
			}
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

	public static function autoshare_for_twitter_after_status_update( $response, $update_data, $post ) {
		$post = get_post( $post );
		if ( $post ) {
			$data = get_post_meta( $post->ID, 'autoshare_status', true );
			$tweet_id = $tweet_status['twitter_id'] ?? '';
			$handle   = $tweet_status['handle'] ?? 'i/web';
			$url = esc_url( 'https://twitter.com/' . $handle . '/status/' . $tweet_id );
			add_post_syndication_link( $post->ID, $url );
		}
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

	public static function wptotwitter_to_syn_links( $connection, $id ) {
		// If someone is doing a a credentials check to twitter, we won't have an ID.
		if ( false === $id ) {
			return;
		}

		$account = get_option( 'wtt_twitter_username' );
		$uid = get_post_meta( $id, '_wpt_tweet_id', true );
		if ( ! $account || ! $uid ) {
			return;
		}

		$url = sprintf(
			'https://twitter.com/%s/status/%s',
			$account,
			$uid
		);

		return add_post_syndication_link( $id, $url );
	}

} // End Class
