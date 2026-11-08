<?php

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Social_Plugins', 'init' ) );

class Social_Plugins {
	public static function init() {
		add_filter( 'get_post_syndication_links', array( 'Social_Plugins', 'add_syn_plugins' ), 10, 2 );
		add_filter( 'syn_link_mapping', array( 'Social_Plugins', 'url_to_name_plugins' ), 10, 2 );
		// XPoster fires wpt_tweet_posted before it saves the status ID, so wait for the ID itself.
		add_action( 'added_post_meta', array( 'Social_Plugins', 'wptotwitter_tweet_id_added' ), 10, 4 );
		add_filter( 'get_post_syndication_links', array( 'Social_Plugins', 'add_autopost_for_x_links' ), 10, 2 );
	}

	public static function url_to_name_plugins( $name, $url ) {
		// Mastodon Autopost's instance. Compare hosts only: wp_http_validate_url() would do a DNS lookup for every link shown.
		$instance = wp_parse_url( (string) get_option( 'autopostToMastodon-instance', '' ), PHP_URL_HOST );
		if ( $instance && strtolower( $instance ) === strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			return 'mastodon';
		}
		return $name;
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

	public static function add_syn_plugins( $urls, $post_id = null ) {
		// The filter passes the post: get_the_ID() is only right inside the loop.
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( ! $post_id ) {
			return $urls;
		}
		$see_on = array();
//		if ( defined( 'NextScripts_SNAP_Version' ) ) {
//			$see_on = array_merge( $see_on, self::add_links_from_snap() );
//		}
		$keys = get_post_meta( $post_id );
		if ( ! $keys ) {
			return $urls;
		}
		$keys = array_keys( $keys );

		foreach(  $keys as $key ) {
			if ( 0 === strpos( $key, 'snap' ) && 6 === strlen( $key ) ) {
				$meta = get_post_meta( $post_id, $key, true );
				$meta = maybe_unserialize( $meta );
				$meta = self::array_flatten( $meta );
				if ( isset( $meta['postURL'] ) ) {
					$see_on[] = $meta['postURL'];
				}
			}
		}
		// Support for the Official Medium Plugin per request @chrisaldrich
		if ( class_exists( 'Medium_Post' ) ) {
			$medium_post = Medium_Post::get_by_wp_id( $post_id );
			if ( $medium_post && ! empty( $medium_post->url ) ) {
				$see_on[] = $medium_post->url;
			}
		}
		// Support for Mastodon Autopost https://github.com/dshanske/syndication-links/issues/75
		// The meta is read whether or not the plugin is still active: the toots it posted still exist.
		foreach ( array( 'autopostToMastodonshare-lastSuccessfullTootURL', 'mastodonAutopostLastSuccessfullPostURL', 'mastodonAutopostLastSuccessfullTootURL' ) as $key ) {
			$mastodon = get_post_meta( $post_id, $key, true );
			if ( $mastodon && is_string( $mastodon ) ) {
				$see_on[] = $mastodon;
				break;
			}
		}
		// Support for Keyring Social Importer fields https://github.com/dshanske/syndication-links/issues/73
		if ( class_exists( 'Keyring_Importer_Base' ) ) {
			$keyrings = array( 'href', 'flickr_url', 'instagram_url', 'pinterest_url', 'twitter_permalink' );
			foreach( $keyrings as $keyring ) {
				$keyr = get_post_meta( $post_id, $keyring, true );
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

	/**
	 * Records the X status XPoster just posted, once XPoster has saved its ID.
	 *
	 * @since 5.0.0
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function wptotwitter_tweet_id_added( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( '_wpt_tweet_id' === $meta_key ) {
			self::wptotwitter_to_syn_links( null, $post_id );
		}
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

	/**
	 * Add the Autopost for X links to the syndication links.
	 *
	 * @see https://wordpress.org/plugins/autoshare-for-twitter/ for more information on plugin.
	 *
	 * @param array $urls The array of URLs to syndicate to.
	 * @return array The array of URLs to syndicate to.
	 */
	public static function add_autopost_for_x_links( $urls, $post_id = null ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( empty( $post_id ) ) {
			return $urls;
		}

		// Only add tweet URLs to the syndication links on the front end.
		if ( ( is_admin() ) && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $urls;
		}

		try {
			$links  = array();
			$tweets = get_post_meta( $post_id, 'autoshare_status', true );
			if ( ! empty( $tweets ) && is_array( $tweets ) ) {
				foreach ( $tweets as $tweet ) {
					if ( isset( $tweet['status'] ) && 'published' === $tweet['status'] && ! empty( $tweet['twitter_id'] ) ) {
						// The plugin saves an empty handle when it doesn't know it; x.com/i/web/status/ works without one.
						$handle  = empty( $tweet['handle'] ) ? 'i/web' : rawurlencode( $tweet['handle'] );
						$links[] = 'https://x.com/' . $handle . '/status/' . rawurlencode( $tweet['twitter_id'] );
					}
				}
			}

			if ( ! empty( $links ) ) {
				return array_merge( $urls, $links );
			}
		} catch ( Exception $e ) {
			error_log( 'Error adding autoshare for twitter links to syndication links: ' . $e->getMessage() );
		}

		return $urls;
	}

} // End Class
