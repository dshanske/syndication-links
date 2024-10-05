<?php

/*
 * Micro.Blog Provider enables a hidden feed that items can be added to in order to get published to micro.blog, then advises micro.blog to poll that feed.
 */

class Syndication_Provider_MicroDotBlog extends Syndication_Provider {


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
		}
		// Parent Constructor
		parent::__construct( $args );
	}


	public static function admin_init() {
		add_settings_section(
			'microdotblog_options',
			__( 'Micro.blog', 'syndication-links' ),
			array( get_called_class(), 'options_callback' ),
			'syndication_provider_options'
		);
	}

	public static function user_contactmethods( $profile_fields ) {
		if ( ! array_key_exists( 'microblog', $profile_fields ) ) {
			$profile_fields['microblog'] = __( 'Micro.blog username', 'syndication-links' );
		}
		return $profile_fields;
	}

	public static function query_var( $vars ) {
		$vars[] = 'microdotblog';
		return $vars;
	}

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
				// Use an explicit user-agent
				'user-agent'          => sprintf( 'Syndication Links for WordPress(%1$s)', home_url() ),
			);
			$response = wp_remote_get( $url, $args );
			if ( ! is_wp_error( $response ) ) {
				$code = wp_remote_retrieve_response_code( $response );
				if ( ( $code / 100 ) === 2 ) {
					$json = json_decode( $response['body'], true );
					if ( array_key_exists( 'home_page_url', $json ) ) {
						add_post_syndication_link( $post_id, esc_url( $json['home_page_url'] ) );
						return $json['home_page_url'];
					}
				}
			}
		}
		return false;
	}


	public static function retrieve_json_feed( $post_id = null ) {
		if ( $post_id ) {
			$permalink = get_permalink( $post_id );
		} else {
			$permalink = null;
		}
		$post    = get_post();
		$user_id = $post->post_author;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$username = get_user_meta( $user_id, 'microblog', true );
		if ( ! $username ) {
			return false;
		}
		$url  = sprintf( 'https://micro.blog/posts/%1$s', $username );
		$args = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent
			'user-agent'          => sprintf( 'Syndication Links for WordPress(%1$s)', home_url() ),
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
		}
		$json = json_decode( $response['body'], true );
		foreach ( $json['items'] as $item ) {
			$post_id = url_to_postid( $item['url'] );
			if ( $post_id ) {
				add_post_syndication_link( $post_id, sprintf( 'https://micro.blog/%1$s/%2$s', $username, $item['id'] ) );
			}
			if ( $item['url'] === $permalink ) {
				return true;
			}
		}
		return true;
	}

	public static function rewrite() {
		add_rewrite_rule( '^microdotblog/feed/(.*)/?', 'index.php?microdotblog=1&withcomments=0&feed=$matches[1]', 'top' );
	}

	/**
	 * Return micro.blog feed link
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
	 * Given a post try to POSSE it to a given network
	 *
	 * @return array of results
	 */
	public function posse( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		$post = get_post( $post_id );
		add_post_meta( $post_id, '_syndication_links_microdotblog', 1 );
		$url      = self::get_feed();
		$response = wp_remote_post(
			add_query_arg(
				'url',
				rawurlencode( $url ),
				'https://micro.blog/ping'
			)
		);

		wp_schedule_single_event( time() + 15, 'microdotblog_get_ids', array( $post_id ) );
		return array( 'message' => __( 'Posted to Micro.blog', 'syndication-links' ) );
	}

	public static function options_callback() {
		esc_html_e( 'Micro.blog requires you to add a feed under your account settings. Micro.blog polls that feed and uses it to create posts on the service. In order for you to use the syndication feature in this plugin, you need to remove all feeds and replace them with the one below and set your micro.blog username in your WordPress user profile. By default this using the JSONfeed if installed, otherwise RSS. This feed is generated by the same triggers used by the other providers. Please check to make sure the feed is working properly. A few seconds after publishing the plugin will poll micro.blog to get the link.', 'syndication-links' );
		printf( '<p><a href="%1$s">%1$s</a></p>', esc_url( self::get_feed() ) );
	}
}
