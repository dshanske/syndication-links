<?php
/**
 * Integrations with other cross-posting plugins.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Social_Plugins', 'init' ) );

/**
 * Shows, records and imports the links that other cross-posting plugins store.
 *
 * Each integration is a source in a registry; see register_source().
 */
class Social_Plugins {
	/**
	 * Hooks the integrations.
	 */
	public static function init() {
		add_filter( 'get_post_syndication_links', array( 'Social_Plugins', 'add_syn_plugins' ), 10, 2 );
		add_filter( 'syn_link_mapping', array( 'Social_Plugins', 'url_to_name_plugins' ), 10, 2 );
		// XPoster fires wpt_tweet_posted before it saves the status ID, so wait for the ID itself.
		add_action( 'added_post_meta', array( 'Social_Plugins', 'wptotwitter_tweet_id_added' ), 10, 4 );
		// XPoster saves only the Bluesky post's CID, so its URL is looked up in the background.
		add_action( 'added_post_meta', array( 'Social_Plugins', 'xposter_bluesky_id_added' ), 10, 3 );
		add_action( 'syn_xposter_bluesky', array( 'Social_Plugins', 'record_xposter_bluesky' ), 10, 2 );
		add_filter( 'get_post_syndication_links', array( 'Social_Plugins', 'add_autopost_for_x_links' ), 10, 2 );
	}

	/**
	 * Gives links to the Mastodon instance set in Mastodon Autopost or XPoster the Mastodon icon.
	 *
	 * @param string $name The icon name.
	 * @param string $url  The link.
	 * @return string The icon name.
	 */
	public static function url_to_name_plugins( $name, $url ) {
		// Mastodon Autopost's and XPoster's instances. Compare hosts only: wp_http_validate_url() would do a DNS lookup for every link shown.
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		foreach ( array( 'autopostToMastodon-instance', 'wpt_mastodon_instance' ) as $option ) {
			$instance = wp_parse_url( (string) get_option( $option, '' ), PHP_URL_HOST );
			if ( $instance && strtolower( $instance ) === $host ) {
				return 'mastodon';
			}
		}
		return $name;
	}

	/**
	 * Flattens a nested array, keeping the innermost keys.
	 *
	 * @param array $array The array.
	 * @return array|false The flattened array, or false if it isn't an array.
	 */
	public static function array_flatten( $array ) {
		if ( ! is_array( $array ) ) {
			return false;
		}
		$result = array();
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$result = array_merge( $result, self::array_flatten( $value ) );
			} else {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Adds the links that sources shown at read time recorded for a post.
	 *
	 * @param string[] $urls    The post's links.
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return string[] The links, with the sources' links first.
	 */
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
	 *     @type callable $import     Given a post ID, returns the links to import, when that needs more
	 *                                than $callback, such as a remote lookup. Optional; defaults to $callback.
	 *                                It runs only in the background (WP-Cron) or from WP-CLI.
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

	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- These define each source's import query, which only runs in batches from the importer.
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
				'import'     => null,
				'meta_query' => array(),
				'show'       => true,
			)
		);
		return array(
			'name'       => (string) $args['name'],
			'callback'   => $args['callback'],
			'import'     => is_callable( $args['import'] ) ? $args['import'] : $args['callback'],
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
				// Importing also looks up Bluesky posts, which needs a request to Bluesky.
				'import'     => array( __CLASS__, 'xposter_import_links' ),
				'meta_query' => self::any_meta( array( '_wpt_tweet_id', '_wpt_status_id', '_wpt_bluesky_id' ) ),
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
			'jetpack'           => array(
				'name'       => 'Jetpack Social (Publicize)',
				'callback'   => array( __CLASS__, 'jetpack_links' ),
				'meta_query' => self::any_meta( array( '_publicize_shares' ) ),
			),
			// The official Medium plugin was closed on WordPress.org in 2019. Its links are no longer
			// shown, but can still be imported from the medium_post meta it left.
			'medium'            => array(
				'name'       => 'Medium',
				'callback'   => array( __CLASS__, 'medium_links' ),
				'meta_query' => self::any_meta( array( 'medium_post' ) ),
				'show'       => false,
			),
		);

		return $sources;
	}
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

	/**
	 * The Medium post the official Medium plugin recorded for a post.
	 *
	 * The plugin saved a Medium_Post object in the medium_post meta. Without the plugin
	 * that's an incomplete object, whose public properties can still be read as an array.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function medium_links( $post_id ) {
		$medium = get_post_meta( $post_id, 'medium_post', true );
		if ( ! is_object( $medium ) && ! is_array( $medium ) ) {
			return array();
		}
		$medium = (array) $medium;
		if ( empty( $medium['url'] ) || ! is_string( $medium['url'] ) || ( isset( $medium['status'] ) && 'draft' === $medium['status'] ) ) {
			return array();
		}
		return array( $medium['url'] );
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
	 * The posts Jetpack Social (Publicize) shared a post to.
	 *
	 * Jetpack saves each share in `_publicize_shares`, with the copy's URL as the `message` of a
	 * successful share. Its `jetpack_publicize_share_urls_saved` action doesn't pass the post, so
	 * the meta is read instead. Older data from WordPress.com saves one share per meta row.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function jetpack_links( $post_id ) {
		$shares = get_post_meta( $post_id, '_publicize_shares', true );
		if ( is_array( $shares ) && $shares && array_keys( $shares ) !== range( 0, count( $shares ) - 1 ) ) {
			$shares = get_post_meta( $post_id, '_publicize_shares', false );
		}
		$links = array();
		foreach ( (array) $shares as $share ) {
			if ( is_array( $share ) && isset( $share['status'], $share['message'] ) && 'success' === $share['status'] && is_string( $share['message'] ) && self::is_web_url( $share['message'] ) ) {
				$links[] = $share['message'];
			}
		}
		return $links;
	}

	/**
	 * Whether a string is an http(s) URL. Unlike wp_http_validate_url(), no DNS lookup:
	 * this runs for every post shown.
	 *
	 * @since 5.0.0
	 *
	 * @param string $url The string.
	 * @return bool
	 */
	private static function is_web_url( $url ) {
		return false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true );
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
		$links = array();
		$uid   = get_post_meta( $post_id, '_wpt_tweet_id', true );
		if ( $uid && is_scalar( $uid ) ) {
			$account = get_option( 'wtt_twitter_username' );
			$links[] = $account ? sprintf( 'https://twitter.com/%s/status/%s', $account, $uid ) : 'https://x.com/i/web/status/' . rawurlencode( $uid );
		}
		$mastodon = self::xposter_mastodon_link( $post_id );
		if ( $mastodon ) {
			$links[] = $mastodon;
		}
		return $links;
	}

	/**
	 * Reads an XPoster setting for the post: the author's own, when XPoster is set up per author, or the site's.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Setting.
	 * @return string
	 */
	private static function xposter_setting( $post_id, $key ) {
		$author = (int) get_post_field( 'post_author', $post_id );
		$value  = $author ? get_user_meta( $author, $key, true ) : '';
		if ( ! $value ) {
			$value = get_option( $key, '' );
		}
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * The Mastodon status XPoster posted for a post: {instance}/@{username}/{id}.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string|false
	 */
	public static function xposter_mastodon_link( $post_id ) {
		$id       = get_post_meta( $post_id, '_wpt_status_id', true );
		$instance = self::xposter_setting( $post_id, 'wpt_mastodon_instance' );
		$username = ltrim( self::xposter_setting( $post_id, 'wpt_mastodon_username' ), '@' );
		if ( ! $id || ! is_scalar( $id ) || ! $instance || ! $username ) {
			return false;
		}
		// The username may be stored as user@instance; the profile path wants the local part.
		$username = strtok( $username, '@' );
		return sprintf( '%1$s/@%2$s/%3$s', untrailingslashit( $instance ), rawurlencode( $username ), rawurlencode( $id ) );
	}

	/**
	 * XPoster's links for importing: the stored ones, plus the Bluesky post looked up by its CID.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function xposter_import_links( $post_id ) {
		$links   = self::xposter_links( $post_id );
		$bluesky = self::xposter_bluesky_link( $post_id );
		if ( $bluesky ) {
			$links[] = $bluesky;
		}
		return $links;
	}

	/**
	 * Schedules the lookup of a Bluesky post when XPoster saves its CID.
	 *
	 * @since 5.0.0
	 *
	 * @param int    $meta_id  Meta ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 */
	public static function xposter_bluesky_id_added( $meta_id, $post_id, $meta_key ) {
		if ( '_wpt_bluesky_id' === $meta_key ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'syn_xposter_bluesky', array( (int) $post_id, 1 ) );
		}
	}

	/**
	 * Records the Bluesky post XPoster posted, retrying a few times while Bluesky's feed catches up.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $attempt Attempt number, from 1.
	 */
	public static function record_xposter_bluesky( $post_id, $attempt = 1 ) {
		$link = self::xposter_bluesky_link( $post_id );
		if ( $link ) {
			add_post_syndication_link( $post_id, $link );
			return;
		}
		if ( $attempt < 3 ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'syn_xposter_bluesky', array( (int) $post_id, $attempt + 1 ) );
		}
	}

	/**
	 * The Bluesky post XPoster posted for a post, found by its CID in the account's public feed.
	 *
	 * XPoster saves only the post's CID, but a link needs the record key, so this looks
	 * through the account's recent posts (up to 1,000, cached for an hour). It makes
	 * requests to Bluesky, so it's only used in the background and when importing.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string|false
	 */
	public static function xposter_bluesky_link( $post_id ) {
		$cid    = get_post_meta( $post_id, '_wpt_bluesky_id', true );
		$handle = ltrim( self::xposter_setting( $post_id, 'wpt_bluesky_username' ), '@' );
		if ( ! $cid || ! is_string( $cid ) || ! $handle ) {
			return false;
		}
		$posts = self::bluesky_posts( $handle, $cid );
		if ( ! isset( $posts[ $cid ] ) ) {
			return false;
		}
		return sprintf( 'https://bsky.app/profile/%1$s/post/%2$s', rawurlencode( $handle ), rawurlencode( $posts[ $cid ] ) );
	}

	/**
	 * An account's recent posts, as CID => record key.
	 *
	 * @param string $handle Bluesky handle.
	 * @param string $cid    The CID being looked for; the walk stops once it's found.
	 * @return string[]
	 */
	private static function bluesky_posts( $handle, $cid ) {
		$key   = 'syn_bsky_' . md5( strtolower( $handle ) );
		$posts = get_transient( $key );
		if ( is_array( $posts ) && isset( $posts[ $cid ] ) ) {
			return $posts;
		}
		$posts  = array();
		$cursor = '';
		for ( $page = 0; $page < 10; $page++ ) {
			$args = array(
				'actor'  => $handle,
				'limit'  => 100,
				'filter' => 'posts_no_replies',
			);
			if ( $cursor ) {
				$args['cursor'] = $cursor;
			}
			$response = wp_safe_remote_get( add_query_arg( array_map( 'rawurlencode', $args ), 'https://public.api.bsky.app/xrpc/app.bsky.feed.getAuthorFeed' ), array( 'timeout' => 10 ) );
			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				break;
			}
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body['feed'] ) ) {
				break;
			}
			foreach ( $body['feed'] as $item ) {
				if ( isset( $item['post']['cid'], $item['post']['uri'] ) && preg_match( '#/app\.bsky\.feed\.post/([^/]+)$#', $item['post']['uri'], $match ) ) {
					$posts[ $item['post']['cid'] ] = $match[1];
				}
			}
			if ( isset( $posts[ $cid ] ) || empty( $body['cursor'] ) ) {
				break;
			}
			$cursor = $body['cursor'];
		}
		set_transient( $key, $posts, HOUR_IN_SECONDS );
		return $posts;
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

	/**
	 * Records the X status XPoster posted for a post.
	 *
	 * @param mixed    $connection Unused; kept from XPoster's old `wpt_tweet_posted` hook.
	 * @param int|bool $id         Post ID, or false during a credentials check.
	 * @return int|bool|null The result of add_post_syndication_link(), or null if there's nothing to add.
	 */
	public static function wptotwitter_to_syn_links( $connection, $id ) {
		// If someone is doing a a credentials check to twitter, we won't have an ID.
		if ( false === $id ) {
			return;
		}

		$account = get_option( 'wtt_twitter_username' );
		$uid     = get_post_meta( $id, '_wpt_tweet_id', true );
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
	 * @param string[] $urls    The post's links.
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return string[] The links.
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
