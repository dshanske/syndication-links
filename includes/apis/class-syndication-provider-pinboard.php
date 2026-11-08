<?php
/**
 * Provider: Pinboard.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saves the URL a post cites (bookmarks, likes and the like) to Pinboard.
 *
 * Requires the Post Kinds plugin, which provides the cited URL.
 */
class Syndication_Provider_Pinboard extends Syndication_Provider {

	/**
	 * Pinboard API token.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Sets up the provider, and its token setting.
	 *
	 * @param array $args Optional. Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Pinboard', 'syndication-links' );
		$this->uid  = 'pinboard';

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option, true ) : false;

		if ( $enable ) {
			add_action( 'admin_init', array( $this, 'admin_init' ), 11 );
		}

		$this->token = get_option( 'pinboard_token' );

		// Parent Constructor.
		parent::__construct( $args );
		$this->register_setting();
	}

	/**
	 * Whether the provider's checkbox is disabled: it is until a token is set.
	 *
	 * @return bool
	 */
	public function is_disabled() {
		return empty( $this->token );
	}

	/**
	 * Registers the token setting.
	 */
	public function register_setting() {
		register_setting(
			'syndication_apis',
			'pinboard_token',
			array(
				'type'         => 'string',
				'description'  => 'Pinboard Token',
				'show_in_rest' => false,
			)
		);
	}

	/**
	 * Adds the Pinboard section to the Providers tab, and the token field to the API Keys tab.
	 */
	public function admin_init() {
		add_settings_section(
			'pinboard_options',
			__( 'Pinboard.in Options', 'syndication-links' ),
			array( get_called_class(), 'options_callback' ),
			'syndication_provider_options'
		);
		add_settings_field(
			'pinboard_token',
			__( 'Pinboard Token', 'syndication-links' ),
			array(
				'Syn_Config',
				'text_callback',
			),
			'syndication_api_keys',
			'syndication_apis',
			array(
				'label_for' => 'pinboard_token',
				'type'      => 'password',
			)
		);
	}

	/**
	 * Displays where to find the Pinboard token.
	 */
	public static function options_callback() {
		esc_html_e( 'Your Pinboard token can be found under your account settings, in the password tab.', 'syndication-links' );
	}

	/**
	 * Saves the URL the post cites to Pinboard, and adds the bookmark to the post's links.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return array|WP_Error|false The response, an error, or false if there's no cited URL or token.
	 */
	public function posse( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$kind_post = new Kind_Post( $post_id );
		$cite      = $kind_post->get_cite();
		$cite      = $kind_post->normalize_cite( $cite );
		if ( empty( $cite['url'] ) ) {
			return false;
		}

		if ( ! $this->token ) {
			return false;
		}

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

		$url = 'https://api.pinboard.in/v1/posts/add';

		$terms = get_the_tags( $post_id );
		$tags  = array();
		foreach ( $terms as $term ) {
			$tags[] = $term->name;
		}

		$bookmark = array(
			'auth_token'  => $this->token,
			'format'      => 'json',
			'url'         => $cite['url'],
			'description' => $cite['name'],
			'tags'        => implode( ' ', $tags ),
			'extended'    => $cite['extended'],
		);
		$bookmark = array_filter( $bookmark );

		$url = add_query_arg( $bookmark, $url );

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 2 !== (int) floor( $code / 100 ) ) {
			return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
		}

		$response = wp_remote_post(
			'https://pinboard.in/url/',
			array(
				'redirection' => 0,
				'body'        => array(
					'url' => $cite['url'],
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$link = wp_remote_retrieve_header( $response, 'Location' );
		if ( ! empty( $link ) ) {
			add_post_syndication_link( $post_id, 'https://pinboard.in' . $link, true );
		}

		return $response;
	}
}
