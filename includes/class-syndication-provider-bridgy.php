<?php

class Syndication_Provider_Bridgy extends Syndication_Provider {
	protected $uid;
	protected $name;
	protected $service;
	protected $user;

	public function __construct( $args = array() ) {
		if ( ! class_exists( 'Webmention_Plugin' ) ) {
			return;
		}
		add_filter( 'webmention_send_vars', array( $this, 'webmention_send_vars' ), 10, 2 );
		// Micropub Syndication Targets
		add_filter( 'micropub_syndicate-to', array( $this, 'syndicate_to' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Parent Constructor
		parent::__construct( $args );
	}

	public function add_notice_query_var( $location, $post_id ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99, 2 );
		return add_query_arg( array( 'bridgyerror' => $post_id ), $location );
	}

	public function admin_notices() {
		if ( ! isset( $_GET['bridgyerror'] ) ) {
			return;
		}
		$post_id = (int) $_GET['bridgyerror'];
		$error   = get_post_meta( $post_id, 'bridgy_error', true );
		if ( empty( $error ) ) {
			return;
		} else {
			delete_post_meta( $post_id, 'bridgy_error' );
		}
		if ( is_array( $error ) ) {
			$error = implode( '\n', $error );
		}
		?>
				<div class="error notice">
					<p><?php _e( 'Bridgy Error: ', 'syndication-links' ); ?>
					<?php echo wp_kses_data( $error ); ?></p>
				</div>
			<?php
	}

	public function webmention_send_vars( $body, $post_id ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		$domain = wp_parse_url( urldecode( $body['target'] ), PHP_URL_HOST );
		if ( 'www.brid.gy' !== $domain ) {
			return $body;
		}
		$backlink = get_post_meta( $post_id, '_bridgy_backlink', true );
		if ( ! $backlink ) {
			$backlink = get_option( 'bridgy_backlink' );
		}
		if ( ! empty( $backlink ) ) {
			$body['bridgy_omit_link'] = $backlink;
		}
		if ( 1 === (int) get_option( 'bridgy_ignoreformatting' ) ) {
			$body['bridgy-ignore-formatting'] = 'true';
		}
		return $body;
	}

	public function send_webmention( $url, $key ) {
		$response      = send_webmention( $url, 'https://www.brid.gy/publish/' . $key );
		$response_code = wp_remote_retrieve_response_code( $response );
		$json          = json_decode( $response['body'] );
		if ( 201 === $response_code ) {
			return $json->url;
		}
		if ( ( 400 === $response_code ) || ( 500 === $response_code ) ) {
			return new WP_Error(
				'bridgy_publish_error',
				$json->error,
				array(
					'status' => 400,
					'data'   => $json,
				)
			);
		}
		return new WP_Error(
			'bridgy_publish_error',
			__( 'Unknown Bridgy Publish Error', 'syndication-links' ),
			array(
				'status' => $response_code,
				'data'   => $json,
			)
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
		$services = self::services( $post_id );
		if ( ! $services ) {
			return;
		}
		/* OK, its safe for us to save the data now. */
		$url = '';
		if ( 1 === (int) get_option( 'bridgy_shortlinks' ) ) {
			$url = wp_get_shortlink( $post_id );
		}
		if ( empty( $url ) ) {
			$url = get_permalink( $post_id );
		}
		$returns = array();
		$errors  = array();
		foreach ( $services as $service ) {
			$response = self::send_webmention( $url, $service );
			if ( ! is_wp_error( $response ) ) {
				$returns[] = $response;
			} else {
				$errors[] = $response->get_error_message();
			}
		}

		if ( ! empty( $returns ) ) {
			add_syndication_link( $post_id, $returns );
		}
		if ( ! empty( $errors ) ) {
			// Add your query var if there are errors are not retreive correctly.
			add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99, 2 );
			update_post_meta( $post_id, 'bridgy_error', join( '<br />', $errors ) );
		}
	}

}
