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
		foreach ( self::sources() as $source ) {
			if ( $source['show'] ) {
				$see_on = array_merge( $see_on, array_filter( (array) call_user_func( $source['callback'], $post_id ), 'is_string' ) );
			}
		}
		return array_merge( $see_on, $urls );
	}

	/**
	 * Sources registered with register_source().
	 *
	 * @var array[]
	 */
	private static $registered = array();

	/**
	 * Registers a plugin whose recorded links are shown with a post's links, and can be imported.
	 *
	 * @since 5.0.0
	 *
	 * @param string $slug Unique slug (lowercase letters, numbers, dashes and underscores).
	 * @param array  $args {
	 *     The source.
	 *
	 *     @type string   $name       Plugin name, shown on the Import tab. Required.
	 *     @type callable $callback   Given a post ID, returns an array of link URLs. Required.
	 *                                It should read only stored data (post meta, options), so it
	 *                                keeps working when the other plugin is inactive.
	 *     @type array    $meta_query A WP_Query meta_query finding the posts that may have links.
	 *                                Without one, the source is shown but can't be imported.
	 *     @type bool     $show       Whether the links are added to the links shown for the post,
	 *                                before they're imported. Default true.
	 * }
	 * @return bool Whether the source was registered.
	 */
	public static function register_source( $slug, $args ) {
		$source = self::normalize_source( $slug, $args );
		if ( ! $source ) {
			return false;
		}
		self::$registered[ $slug ] = $source;
		return true;
	}

	/**
	 * Validates a source and fills in its defaults.
	 *
	 * @param string $slug Slug.
	 * @param array  $args Source.
	 * @return array|false
	 */
	private static function normalize_source( $slug, $args ) {
		if ( ! is_string( $slug ) || sanitize_key( $slug ) !== $slug || ! is_array( $args ) || empty( $args['name'] ) || empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}
		$args = wp_parse_args(
			$args,
			array(
				'meta_query' => array(),
				'show'       => true,
			)
		);
		return array(
			'name'       => (string) $args['name'],
			'callback'   => $args['callback'],
			'meta_query' => is_array( $args['meta_query'] ) ? $args['meta_query'] : array(),
			'show'       => (bool) $args['show'],
		);
	}

	/**
	 * Every source: the built-in integrations, registered ones, and filtered ones.
	 *
	 * @since 5.0.0
	 *
	 * @return array[] Sources keyed by slug. See register_source() for the keys.
	 */
	public static function sources() {
		$sources = array_merge( self::builtin_sources(), self::$registered );

		/**
		 * Filters the plugins whose recorded links are shown and can be imported.
		 *
		 * @since 5.0.0
		 *
		 * @param array[] $sources Sources keyed by slug. See Social_Plugins::register_source() for the keys.
		 */
		$sources = apply_filters( 'syn_link_sources', $sources );

		$valid = array();
		foreach ( (array) $sources as $slug => $source ) {
			$source = self::normalize_source( $slug, $source );
			if ( $source ) {
				$valid[ $slug ] = $source;
			}
		}
		return $valid;
	}

	/**
	 * The sources that can be imported: those with a meta query.
	 *
	 * @since 5.0.0
	 *
	 * @return array[]
	 */
	public static function import_sources() {
		return array_filter(
			self::sources(),
			function ( $source ) {
				return ! empty( $source['meta_query'] );
			}
		);
	}

	/**
	 * Mastodon Autopost's toot URL meta keys, newest first.
	 *
	 * @var string[]
	 */
	private static $mastodon_autopost_keys = array( 'autopostToMastodonshare-lastSuccessfullTootURL', 'mastodonAutopostLastSuccessfullPostURL', 'mastodonAutopostLastSuccessfullTootURL' );

	/**
	 * Keyring Social Importers' permalink meta keys that only it uses.
	 *
	 * @var string[]
	 */
	private static $keyring_keys = array( 'flickr_url', 'instagram_url', 'pinterest_url', 'twitter_permalink' );

	/**
	 * The integrations built into Syndication Links.
	 *
	 * Each reads only the other plugin's stored data, so its links can be shown and
	 * imported after that plugin is deactivated. The other plugin's data is never changed.
	 *
	 * @return array[]
	 */
	private static function builtin_sources() {
		$sources = array(
			'snap'              => array(
				'name'       => 'NextScripts: Social Networks Auto-Poster',
				'callback'   => array( __CLASS__, 'snap_links' ),
				'meta_query' => array(
					array(
						'key'         => 'snap',
						'compare_key' => 'LIKE',
					),
				),
			),
			'mastodon-autopost' => array(
				'name'       => 'Mastodon Autopost',
				'callback'   => array( __CLASS__, 'mastodon_autopost_links' ),
				'meta_query' => self::any_meta( self::$mastodon_autopost_keys ),
			),
			'xposter'           => array(
				'name'       => 'XPoster (WP to Twitter)',
				'callback'   => array( __CLASS__, 'xposter_links' ),
				'meta_query' => self::any_meta( array( '_wpt_tweet_id' ) ),
			),
			'autopost-for-x'    => array(
				'name'       => 'Autopost for X',
				'callback'   => array( __CLASS__, 'autopost_for_x_links' ),
				'meta_query' => self::any_meta( array( 'autoshare_status' ) ),
				// Shown by add_autopost_for_x_links(), which leaves them out on admin screens.
				'show'       => false,
			),
			'keyring'           => array(
				'name'       => 'Keyring Social Importers',
				'callback'   => array( __CLASS__, 'keyring_links' ),
				'meta_query' => self::any_meta( array_merge( self::$keyring_keys, array( 'href' ) ) ),
				'show'       => class_exists( 'Keyring_Importer_Base' ),
			),
			// Support for the Official Medium Plugin per request @chrisaldrich. Its links are in
			// the Medium plugin's own data, so they can't be imported once it's gone.
			'medium'            => array(
				'name'     => 'Medium',
				'callback' => array( __CLASS__, 'medium_links' ),
				'show'     => class_exists( 'Medium_Post' ),
			),
		);

		return $sources;
	}

	/**
	 * The post's link from the official Medium plugin, while it's active.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function medium_links( $post_id ) {
		if ( ! class_exists( 'Medium_Post' ) ) {
			return array();
		}
		$medium_post = Medium_Post::get_by_wp_id( $post_id );
		return ( $medium_post && ! empty( $medium_post->url ) ) ? array( $medium_post->url ) : array();
	}

	/**
	 * A meta query matching posts that have any of the keys.
	 *
	 * @param string[] $keys Meta keys.
	 * @return array
	 */
	private static function any_meta( $keys ) {
		$query = array( 'relation' => 'OR' );
		foreach ( $keys as $key ) {
			$query[] = array(
				'key'     => $key,
				'compare' => 'EXISTS',
			);
		}
		return $query;
	}

	/**
	 * Links SNAP recorded for a post, from its snapXX meta.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function snap_links( $post_id ) {
		$links = array();
		$keys  = get_post_meta( $post_id );
		if ( ! $keys ) {
			return $links;
		}
		foreach ( array_keys( $keys ) as $key ) {
			if ( 0 === strpos( $key, 'snap' ) && 6 === strlen( $key ) ) {
				$meta = self::array_flatten( maybe_unserialize( get_post_meta( $post_id, $key, true ) ) );
				if ( isset( $meta['postURL'] ) ) {
					$links[] = $meta['postURL'];
				}
			}
		}
		return $links;
	}

	/**
	 * The toot Mastodon Autopost recorded for a post.
	 *
	 * The meta is read whether or not the plugin is still active: the toots it posted still exist.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function mastodon_autopost_links( $post_id ) {
		foreach ( self::$mastodon_autopost_keys as $key ) {
			$mastodon = get_post_meta( $post_id, $key, true );
			if ( $mastodon && is_string( $mastodon ) ) {
				return array( $mastodon );
			}
		}
		return array();
	}

	/**
	 * The X status XPoster recorded for a post, in the form wptotwitter_to_syn_links() records it.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function xposter_links( $post_id ) {
		$uid = get_post_meta( $post_id, '_wpt_tweet_id', true );
		if ( ! $uid || ! is_scalar( $uid ) ) {
			return array();
		}
		$account = get_option( 'wtt_twitter_username' );
		if ( $account ) {
			return array( sprintf( 'https://twitter.com/%s/status/%s', $account, $uid ) );
		}
		return array( 'https://x.com/i/web/status/' . rawurlencode( $uid ) );
	}

	/**
	 * The X statuses Autopost for X recorded for a post.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function autopost_for_x_links( $post_id ) {
		$links  = array();
		$tweets = get_post_meta( $post_id, 'autoshare_status', true );
		if ( empty( $tweets ) || ! is_array( $tweets ) ) {
			return $links;
		}
		foreach ( $tweets as $tweet ) {
			if ( is_array( $tweet ) && isset( $tweet['status'] ) && 'published' === $tweet['status'] && ! empty( $tweet['twitter_id'] ) ) {
				// The plugin saves an empty handle when it doesn't know it; x.com/i/web/status/ works without one.
				$handle  = empty( $tweet['handle'] ) ? 'i/web' : rawurlencode( $tweet['handle'] );
				$links[] = 'https://x.com/' . $handle . '/status/' . rawurlencode( $tweet['twitter_id'] );
			}
		}
		return $links;
	}

	/**
	 * The permalinks Keyring Social Importers saved on the posts it created.
	 *
	 * The generic `href` key is used only while Keyring's keyring_services taxonomy
	 * exists and the post has a term in it, so other plugins' `href` meta isn't imported.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function keyring_links( $post_id ) {
		$keys = self::$keyring_keys;
		if ( taxonomy_exists( 'keyring_services' ) && has_term( '', 'keyring_services', $post_id ) ) {
			$keys[] = 'href';
		}
		$links = array();
		foreach ( $keys as $key ) {
			$link = get_post_meta( $post_id, $key, true );
			if ( $link && is_string( $link ) ) {
				$links[] = $link;
			}
		}
		return $links;
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

		return array_merge( $urls, self::autopost_for_x_links( $post_id ) );
	}

} // End Class
