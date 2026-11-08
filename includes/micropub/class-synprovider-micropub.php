<?php
/**
 * Base class for providers that syndicate by Micropub.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syndicates a post by creating it at a Micropub endpoint, such as Bridgy Publish's.
 *
 * The post is sent as an h-entry in JSON. The copy's URL comes back in the `Location` header.
 */
class SynProvider_Micropub extends Syndication_Provider {

	/**
	 * Bearer token for the Micropub endpoint.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Micropub endpoint to publish to.
	 *
	 * @var string
	 */
	protected $endpoint;

	/**
	 * Maximum content length, in characters.
	 *
	 * @var int
	 */
	protected $content_length = 10000;

	/**
	 * Sets the token, if one is given.
	 *
	 * @param array $args {
	 *     Optional. Arguments.
	 *
	 *     @type string $token Bearer token.
	 * }
	 */
	public function __construct( $args = array() ) {
		if ( array_key_exists( 'token', $args ) ) {
			$this->token = $args['token'];
		}
		parent::__construct( $args );
	}

	/**
	 * Sets the bearer token.
	 *
	 * @param string $token The token.
	 */
	public function set_token( $token ) {
		$this->token = $token;
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
	 * Sets the Micropub endpoint.
	 *
	 * @param string $endpoint The endpoint URL.
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;
	}

	/**
	 * Fetches JSON from a Micropub endpoint, such as a `q=config` query.
	 *
	 * @param string     $url     URL to fetch.
	 * @param array      $query   Query parameters.
	 * @param array|null $headers Optional. Extra headers. The token is sent unless they include `Authorization`.
	 * @return array|WP_Error The decoded response, or an error for a non-2xx or non-JSON response.
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

		if ( ! array_key_exists( 'Authorization', $args['headers'] ) && ! empty( $this->token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_get( $fetch, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 2 !== (int) floor( $code / 100 ) ) {
			return new WP_Error( 'invalid_response', $body, array( 'status' => $code ) );
		}

		$json = json_decode( $body, true );
		if ( empty( $json ) ) {
			return new WP_Error( 'not_json_response', $body, array( 'type' => wp_remote_retrieve_header( $response, 'Content-Type' ) ) );
		}
		return $json;
	}

	/**
	 * Returns the content to send: the post's content, shortened to fit, with a link back
	 * depending on the backlink setting, or the excerpt if that setting is on.
	 *
	 * @param WP_Post $post The post.
	 * @return string The content, as HTML.
	 */
	public function get_content( $post ) {
		$content = syn_get_post_content( $post );
		$length  = strlen( $content );
		// '' adds a link back, 'true' doesn't, and 'maybe' adds one only when the content is too long.
		$backlink  = (string) get_option( 'syndication_backlink', 'maybe' );
		$shortlink = wp_get_shortlink( $post );
		$link      = '<a href="' . esc_url( $shortlink ) . '">' . esc_html( $shortlink ) . '</a>';
		if ( 'true' === $backlink ) {
			$content = syn_excerpt( $content, $this->content_length );
		} else {
			// Leave room for the link and its separator.
			$content = syn_excerpt( $content, ( $this->content_length - 3 ) - strlen( $shortlink ) );
		}
		// Options are stored as strings, so compare the checkbox's value as a number.
		if ( ! empty( $post->post_excerpt ) && 1 === (int) get_option( 'syndication_use_excerpt' ) ) {
			$content = $post->post_excerpt;
		} elseif ( $this->content_length < $length && 'maybe' === $backlink ) {
			// If length is over $content_length then replace content with link plus the title.
			if ( function_exists( 'kind_get_the_title' ) ) {
				$content = kind_get_the_title( $post ) . ' - ' . $link;
			} elseif ( ! empty( get_the_title( $post ) ) ) {
				$content = get_the_title( $post ) . ' - ' . $link;
			} else {
				$content = $content . ' - ' . $link;
			}
		} elseif ( '' === $backlink ) {
			$content = $content . ' (' . $link . ')';
		}
		return $content;
	}

	/**
	 * Converts a post to an h-entry for Micropub.
	 *
	 * Includes the post's `mf2_*` meta, its dates, title, excerpt, featured image and content.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return array The h-entry, as Micropub JSON.
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

		// Time Information.
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
				),
			);
		}

		$mf2['type'] = array( 'h-entry' );

		return $mf2;
	}

	/**
	 * Creates a post at a Micropub endpoint.
	 *
	 * @param int|WP_Post $post     Post ID or object.
	 * @param string      $endpoint Micropub endpoint.
	 * @param array|null  $headers  Optional. Extra headers. The token is sent unless they include `Authorization`.
	 * @return array|WP_Error The response, or an error.
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
	 * Deletes a post at a Micropub endpoint.
	 *
	 * @param string     $url      URL of the post to delete.
	 * @param string     $endpoint Micropub endpoint.
	 * @param array|null $headers  Optional. Extra headers. The token is sent unless they include `Authorization`.
	 * @return array|WP_Error The response, or an error.
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
	 * Syndicates a post, and adds the copy's URL from the `Location` header to its links.
	 *
	 * @since 5.0.0 Returns an error for a response that isn't a 2xx.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return array|WP_Error The response, or an error for a failed request or a non-2xx response.
	 */
	public function posse( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( empty( $this->token ) ) {
			return new WP_Error(
				'missing_token',
				/* translators: %s: provider name */
				sprintf( __( '%s has no token. Add one on the API Keys tab of the Syndication Links settings.', 'syndication-links' ), $this->name )
			);
		}
		$response = self::post_micropub( $post_id, $this->endpoint );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Micropub creates the post with a 201 or 202; anything other than a 2xx is an error.
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 2 !== (int) floor( $code / 100 ) ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			// Micropub errors are JSON with an `error` code and an optional `error_description`.
			if ( is_array( $data ) && ! empty( $data['error_description'] ) && is_string( $data['error_description'] ) ) {
				$message = $data['error_description'];
			} else {
				/* translators: %s: provider name */
				$message = sprintf( __( 'Unknown %s Error', 'syndication-links' ), $this->name );
			}
			return new WP_Error(
				$this->uid . '_publish_error',
				$message,
				array(
					'status' => $code,
					'data'   => is_null( $data ) ? $body : $data,
				)
			);
		}

		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( ! empty( $location ) ) {
			add_post_syndication_link( $post_id, $location );
		}

		return $response;
	}
}
