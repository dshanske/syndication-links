<?php
/**
 * Provider: Micro.blog.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syndicates to Micro.blog through a feed.
 *
 * Syndicated posts are added to a dedicated feed, which the account's Micro.blog
 * settings must point to. Micro.blog is then pinged to fetch it, and the copy's URL
 * is looked up shortly after.
 */
class Syndication_Provider_MicroDotBlog extends Syndication_Provider {

	/**
	 * Sets the provider's name and UID, and hooks the feed if the provider is enabled.
	 *
	 * @param array $args Optional. Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Micro.blog', 'syndication-links' );
		$this->uid  = 'microdotblog';

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option, true ) : false;
		if ( $enable ) {
			add_filter( 'query_vars', array( $this, 'query_var' ) );
			add_action( 'pre_get_posts', array( $this, 'create_feed' ) );
			add_action( 'init', array( $this, 'rewrite' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ), 11 );
			add_filter( 'user_contactmethods', array( $this, 'user_contactmethods' ) );
			add_action( 'microdotblog_get_ids', array( $this, 'get_microblog_post' ), 10 );
			add_filter( 'syndication_links_feed_display', array( $this, 'feed_display' ) );
		}
		// Parent Constructor.
		parent::__construct( $args );
	}

	/**
	 * Adds the Micro.blog section to the Providers tab.
	 */
	public static function admin_init() {
		add_settings_section(
			'microdotblog_options',
			__( 'Micro.blog', 'syndication-links' ),
			array( get_called_class(), 'options_callback' ),
			'syndication_provider_options'
		);
	}

	/**
	 * Adds a Micro.blog username field to user profiles.
	 *
	 * @param string[] $profile_fields Contact methods, keyed by meta key.
	 * @return string[] The contact methods.
	 */
	public static function user_contactmethods( $profile_fields ) {
		if ( ! array_key_exists( 'microblog', $profile_fields ) ) {
			$profile_fields['microblog'] = __( 'Micro.blog username', 'syndication-links' );
		}
		return $profile_fields;
	}

	/**
	 * Registers the `microdotblog` query var.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[] The query vars.
	 */
	public static function query_var( $vars ) {
		$vars[] = 'microdotblog';
		return $vars;
	}

	/**
	 * Limits a feed request with `microdotblog` to the posts syndicated to Micro.blog.
	 *
	 * With `microdotblog=refresh`, the account's Micro.blog posts are fetched first.
	 *
	 * @param WP_Query $query The query.
	 */
	public static function create_feed( $query ) {
		if ( ! array_key_exists( 'microdotblog', $query->query_vars ) ) {
			return;
		}
		if ( 'refresh' === $query->query_vars['microdotblog'] ) {
			self::retrieve_json_feed();
		}
		$args = array(
			array(
				'key'     => '_syndication_links_microdotblog',
				'compare' => 'EXISTS',
			),
		);

		$query->set( 'meta_query', array( $args ) );
		if ( ! array_key_exists( 'feed', $query->query_vars ) || empty( $query->query_vars['feed'] ) ) {
			$query->set( 'feed', 'rss2' );
		}
	}

	/**
	 * Leaves the links out of the Micro.blog feed, so Micro.blog doesn't post them.
	 *
	 * @since 5.0.0
	 *
	 * @param bool $display Whether the feed shows the post's links.
	 * @return bool
	 */
	public static function feed_display( $display ) {
		return get_query_var( 'microdotblog' ) ? false : $display;
	}

	/**
	 * Looks up a post's copy on Micro.blog, and adds it to the post's links.
	 *
	 * Runs on `microdotblog_get_ids`, scheduled shortly after the post is syndicated.
	 *
	 * @param int|null $post_id Optional. Post ID.
	 * @return string|false The copy's URL, or false if it wasn't found.
	 */
	public static function get_microblog_post( $post_id = null ) {
		$urls = array_filter( array( get_the_guid( $post_id ), get_permalink( $post_id ) ) );

		if ( empty( $urls ) ) {
			return false;
		}

		foreach ( $urls as $permalink ) {
			$url = add_query_arg(
				array(
					'format' => 'jsonfeed',
					'url'    => rawurlencode( $permalink ),
				),
				'https://micro.blog/conversation.js'
			);

			$args = array(
				'headers'             => array(
					'Accept' => 'application/json',
				),
				'timeout'             => 10,
				'limit_response_size' => 1048576,
				'redirection'         => 1,
				// Use an explicit user-agent.
				'user-agent'          => sprintf( 'Syndication Links for WordPress(%1$s)', home_url() ),
			);
			$response = wp_remote_get( $url, $args );
			if ( ! is_wp_error( $response ) ) {
				$code = wp_remote_retrieve_response_code( $response );
				if ( 2 === (int) floor( $code / 100 ) ) {
					$json = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( is_array( $json ) && ! empty( $json['home_page_url'] ) && is_string( $json['home_page_url'] ) ) {
						add_post_syndication_link( $post_id, esc_url( $json['home_page_url'] ) );
						return $json['home_page_url'];
					}
				}
			}
		}
		return false;
	}

	/**
	 * Fetches the account's Micro.blog posts, and adds each copy to its post's links.
	 *
	 * @param int|null $post_id Optional. Post ID: stop once its copy is found.
	 * @return bool|WP_Error True when done, false if the author has no Micro.blog username, or an error.
	 */
	public static function retrieve_json_feed( $post_id = null ) {
		if ( $post_id ) {
			$permalink = get_permalink( $post_id );
		} else {
			$permalink = null;
		}
		// The post's author, or the current post's: on a feed refresh there's no post at all.
		$post    = get_post( $post_id );
		$user_id = $post ? (int) $post->post_author : 0;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$username = get_user_meta( $user_id, 'microblog', true );
		if ( ! $username ) {
			return false;
		}
		$url  = sprintf( 'https://micro.blog/posts/%1$s', rawurlencode( $username ) );
		$args = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent.
			'user-agent'          => sprintf( 'Syndication Links for WordPress(%1$s)', home_url() ),
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 2 !== (int) floor( $code / 100 ) ) {
			return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) || ! isset( $json['items'] ) || ! is_array( $json['items'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Micro.blog returned no posts.', 'syndication-links' ) );
		}
		foreach ( $json['items'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['url'] ) || empty( $item['id'] ) || ! is_string( $item['url'] ) ) {
				continue;
			}
			$item_post_id = url_to_postid( $item['url'] );
			if ( $item_post_id ) {
				add_post_syndication_link( $item_post_id, sprintf( 'https://micro.blog/%1$s/%2$s', rawurlencode( $username ), rawurlencode( (string) $item['id'] ) ) );
			}
			if ( $item['url'] === $permalink ) {
				return true;
			}
		}
		return true;
	}

	/**
	 * Adds the `/microdotblog/feed/` rewrite rule.
	 */
	public static function rewrite() {
		add_rewrite_rule( '^microdotblog/feed/(.*)/?', 'index.php?microdotblog=1&withcomments=0&feed=$matches[1]', 'top' );
	}

	/**
	 * Returns the URL of the Micro.blog feed: JSON Feed if available, otherwise the default feed.
	 *
	 * @return string The URL.
	 */
	public static function get_feed() {
		if ( function_exists( 'json_feed_content_type' ) ) {
			$feed = 'json';
		} else {
			$feed = get_default_feed();
		}
		return add_query_arg(
			array(
				'microdotblog' => 1,
			),
			get_feed_link( $feed )
		);
	}

	/**
	 * Syndicates a post: adds it to the feed, pings Micro.blog, and schedules the lookup of the copy.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return array A message for the log.
	 */
	public function posse( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		add_post_meta( $post_id, '_syndication_links_microdotblog', 1 );
		$url = self::get_feed();
		wp_remote_post(
			add_query_arg(
				'url',
				rawurlencode( $url ),
				'https://micro.blog/ping'
			)
		);

		wp_schedule_single_event( time() + 15, 'microdotblog_get_ids', array( $post_id ) );
		return array( 'message' => __( 'Posted to Micro.blog', 'syndication-links' ) );
	}

	/**
	 * Displays how to set up the feed on Micro.blog, and its URL.
	 */
	public static function options_callback() {
		esc_html_e( 'Micro.blog requires you to add a feed under your account settings. Micro.blog polls that feed and uses it to create posts on the service. In order for you to use the syndication feature in this plugin, you need to remove all feeds and replace them with the one below and set your micro.blog username in your WordPress user profile. By default this using the JSONfeed if installed, otherwise RSS. This feed is generated by the same triggers used by the other providers. Please check to make sure the feed is working properly. A few seconds after publishing the plugin will poll micro.blog to get the link.', 'syndication-links' );
		printf( '<p><a href="%1$s">%1$s</a></p>', esc_url( self::get_feed() ) );
	}
}
