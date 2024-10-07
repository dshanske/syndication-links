<?php

/**
 * Base Micropub Class
 */

class SynProvider_Micropub extends Syndication_Provider {

	/**
	 * Bearer Token for the Micropub transaction
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Micropub Endpoint to Publish To
	 *
	 * @var string
	 */
	protected $endpoint;

	/**
	 * Maximum Content Length
	 *
	 * @var int
	 */
	protected $content_length = 10000;


	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Array of Arguments
	 */
	public function __construct( $args = array() ) {
		if ( array_key_exists( 'token', $args ) ) {
			$this->token = $args['token'];
		}
		parent::__construct( $args );
	}

	/**
	 * Set Token
	 */
	public function set_token( $token ) {
		$this->token = $token;
	}

	/**
	 * Check if token is set
	 */
	public function is_disabled() {
		return empty( $this->token );
	}

	/**
	 * Set Endpoint
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;
	}

	/**
	 * Fetches JSON from a micropub endpoint
	 *
	 * @param string $url URL to fetch.
	 * @param array  $query Query parameters.
	 * @param array  $headers Headers.
	 * @return WP_Error|array Either the associated array response or error.
	*/
	public function fetch_micropub( $url, $query, $headers = null ) {
		$fetch = add_query_arg( $query, $url );
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Syndication Links.
			'user-agent'          => 'Syndication Links for WordPress',
		);

		if ( is_array( $headers ) ) {
			$args['headers'] = array_merge( $args['headers'], $headers );
		}

		if ( ! array_key_exists( 'Authorization', args['headers'] ) && ! empty( $this->token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_get( $fetch, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', $body, array( 'status' => $code ) );
		}

		$json = json_decode( $body, true );
		if ( empty( $json ) ) {
			return new WP_Error( 'not_json_response', $body, array( 'type' => wp_remote_retrieve_header( $response, 'Content-Type' ) ) );
		}
		return $json;
	}

	/**
	 * Generate Content
	 *
	 * @param int|WP_Post $post WordPress post
	 * @return string Content string
	 */
	public function get_content( $post ) {
		$content  = syn_get_post_content( $post );
		$length   = strlen( $content );
		$backlink = get_option( 'syndication_backlink' );
		$shortlink     = wp_get_shortlink( $post );
		$link     = '<a href="' . $shortlink . '">' . $shortlink . '</a>';
		if ( true !== $backlink ) {
			$content = syn_excerpt( $content, ( $this->content_length - 3 ) - strlen( $shortlink ) );
		}
		if ( ! empty( $post->post_excerpt ) && 1 === get_option( 'syndication_use_excerpt' ) ) {
			$content = $post->post_excerpt;
		} elseif ( $this->content_length < $length && 'maybe' === $backlink ) {
			// If length is over $content_length then replace content with link plus the title
			if ( function_exists( 'kind_get_the_title' ) ) {
				$content = kind_get_the_title( $post ) . ' - ' . $link;
			} elseif ( ! empty( get_the_title( $post ) ) ) {
				$content = get_the_title( $post ) . ' - ' . $link;
			} else {
				$content = $content . ' - ' . $link;
			}
		} elseif ( 'true' !== $backlink ) {
			$content = $content . ' (' . $link . ')';
		}
		return $content;
	}

	/**
	 * Convert post to Microformats for Micropub
	 *
	 * @param int|WP_Post $post WordPress Post
	 * @return array|false Microformats
	 */
	public function post_to_mf2( $post ) {
		$post = get_post( $post );

		$mf2               = array();
		$mf2['properties'] = array();
		foreach ( get_post_meta( $post->ID ) as $field => $val ) {
			if ( 'mf2_' === substr( $field, 0, 4 ) ) {
				$val = maybe_unserialize( $val[0] );
				if ( is_array( $val ) ) {
					$val = array_values( $val );
				}
				$mf2['properties'][ substr( $field, 4 ) ] = $val;
			}
		}

		$mf2['properties'] = array_filter( $mf2['properties'] );

		// Time Information
		$published                      = get_post_datetime( $post );
		$updated                        = get_post_datetime( $post, 'modified' );
		$mf2['properties']['published'] = array( $published->format( DATE_W3C ) );
		if ( has_post_thumbnail( $post ) ) {
			$mf2['properties']['featured'] = array( get_the_post_thumbnail_url( $post, 'full' ) );
		}

		if ( $published->getTimestamp() !== $updated->getTimestamp() ) {
			$mf2['properties']['updated'] = array( $updated->format( DATE_W3C ) );
		}

		if ( ! empty( $post->post_title ) ) {
			$mf2['properties']['name'] = array( $post->post_title );
		}

		if ( ! empty( $post->post_excerpt ) ) {
			$mf2['properties']['summary'] = array( $post->post_excerpt );
		}

		$content = $this->get_content( $post );

		if ( ! empty( $content ) ) {
			$mf2['properties']['content'] = array(
				array(
					'html' => $content,
				)
			);
		}

		$mf2['type'] = array( 'h-entry' );

		return $mf2;
	}

	/**
	 * Post JSON from a remote endpoint.
	 *
	 * @param int|WP_Post Post to Publish
	 * @param string $endpoint Micropub Endpoint
	 * @param array  $headers Headers.
	 * @return WP_Error|array Either the associated array response or error.
	*/
	public function post_micropub( $post, $endpoint, $headers = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Invalid Post Provided', 'syndication-links' ) );
		}
		$mf2  = $this->post_to_mf2( $post );
		$args = array(
			'headers'             => array(
				'Content-type' => 'application/json',
			),
			'body'                => wp_json_encode( $mf2 ),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			// Use an explicit user-agent for Syndication Links.
			'user-agent'          => 'Syndication Links for WordPress',

		);

		if ( is_array( $headers ) ) {
			$args['headers'] = array_merge( $args['headers'], $headers );
		}

		if ( ! array_key_exists( 'Authorization', $args['headers'] ) && ! empty( $this->token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_post( $endpoint, $args );
		return $response;
	}

	/**
	 * Post JSON from a remote endpoint.
	 *
	 * @param string $url URL to Delete.
	 * @param string $endpoint Micropub Endpoint
	 * @param array  $headers Headers.
	 * @return WP_Error|array Either the associated array response or error.
	*/
	public function delete_micropub( $url, $endpoint, $headers = null ) {
		$args = array(
			'headers'             => array(
				'Content-type' => 'application/json',
			),
			'body'                => wp_json_encode(
				array(
					'action' => 'delete',
					'url'    => $url,
				)
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			// Use an explicit user-agent for Syndication Links.
			'user-agent'          => 'Syndication Links for WordPress',

		);

		if ( is_array( $headers ) ) {
			$args['headers'] = array_merge( $args['headers'], $headers );
		}

		if ( ! array_key_exists( 'Authorization', $args['headers'] ) && ! empty( $this->token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_post( $endpoint, $args );
		return $response;
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

		$response = self::post_micropub( $post_id, $this->endpoint );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( ! empty( $location ) ) {
			add_post_syndication_link( $post_id, $location );
		}

		return $response;
	}
}
