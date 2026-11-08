<?php
/**
 * Tests what Micropub providers send: the h-entry, the create and delete requests, and posse().
 *
 * HTTP requests are mocked.
 *
 * @package Syndication_Links
 */

class Test_Micropub_Sending extends WP_UnitTestCase {

	/**
	 * Requests made, as array( url, args ).
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * The mocked response, or a WP_Error.
	 *
	 * @var array|WP_Error
	 */
	private $response;

	public function set_up() {
		parent::set_up();
		$this->requests = array();
		$this->respond( 201, '', array( 'location' => 'https://mastodon.example/@me/1' ) );
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
		// The content is the post's content, with no link back.
		update_option( 'syndication_backlink', 'true' );
	}

	public function tear_down() {
		delete_option( 'syndication_backlink' );
		parent::tear_down();
	}

	/**
	 * Sets the mocked response.
	 *
	 * @param int    $code    Status code.
	 * @param string $body    Body.
	 * @param array  $headers Headers.
	 */
	private function respond( $code, $body, $headers = array() ) {
		// Real responses use a case-insensitive dictionary; wp_remote_retrieve_header() relies on it.
		$dictionary     = class_exists( 'WpOrg\\Requests\\Utility\\CaseInsensitiveDictionary' ) ? 'WpOrg\\Requests\\Utility\\CaseInsensitiveDictionary' : 'Requests_Utility_CaseInsensitiveDictionary';
		$this->response = array(
			'headers'  => new $dictionary( $headers ),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Records the request and returns the mocked response.
	 *
	 * @param false|array $pre  Preempted response.
	 * @param array       $args Request arguments.
	 * @param string      $url  URL.
	 * @return array|WP_Error
	 */
	public function mock_http( $pre, $args, $url ) {
		$this->requests[] = array( $url, $args );
		return $this->response;
	}

	/**
	 * A Micropub provider with a token and an endpoint.
	 *
	 * @param array $args Optional. Constructor arguments.
	 * @return SynProvider_Micropub
	 */
	private function provider( $args = array( 'token' => 'abc123' ) ) {
		$provider = new SynProvider_Micropub( $args );
		$provider->set_endpoint( 'https://micropub.example/' );
		return $provider;
	}

	/**
	 * Creates a post.
	 *
	 * @param array $args Optional. Post arguments.
	 * @return int Post ID.
	 */
	private function post( $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_title'    => 'The title',
					'post_content'  => 'The content.',
					// The factory makes up an excerpt unless one is given.
					'post_excerpt'  => '',
					'post_date'     => '2026-11-01 10:00:00',
					'post_date_gmt' => '2026-11-01 10:00:00',
				),
				$args
			)
		);
	}

	public function test_post_to_mf2_builds_an_h_entry() {
		$post_id = $this->post( array( 'post_excerpt' => 'The excerpt.' ) );

		$mf2 = $this->provider()->post_to_mf2( $post_id );

		$this->assertSame( array( 'h-entry' ), $mf2['type'] );
		$this->assertSame( array( 'The title' ), $mf2['properties']['name'] );
		$this->assertSame( array( 'The excerpt.' ), $mf2['properties']['summary'] );
		$this->assertSame( array( get_post_datetime( $post_id )->format( DATE_W3C ) ), $mf2['properties']['published'] );
		$this->assertSame( array( array( 'html' => 'The content.' ) ), $mf2['properties']['content'] );
	}

	public function test_post_to_mf2_leaves_out_an_empty_title_and_excerpt() {
		$mf2 = $this->provider()->post_to_mf2(
			$this->post(
				array(
					'post_title'   => '',
					'post_excerpt' => '',
				)
			)
		);

		$this->assertArrayNotHasKey( 'name', $mf2['properties'] );
		$this->assertArrayNotHasKey( 'summary', $mf2['properties'] );
	}

	public function test_post_to_mf2_includes_updated_only_when_modified() {
		$post_id = $this->post();
		$this->assertArrayNotHasKey( 'updated', $this->provider()->post_to_mf2( $post_id )['properties'] );

		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2026-11-02 10:00:00',
				'post_modified_gmt' => '2026-11-02 10:00:00',
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$mf2 = $this->provider()->post_to_mf2( $post_id );

		$this->assertSame( array( get_post_datetime( $post_id, 'modified' )->format( DATE_W3C ) ), $mf2['properties']['updated'] );
	}

	public function test_post_to_mf2_includes_the_mf2_meta() {
		$post_id = $this->post();
		add_post_meta(
			$post_id,
			'mf2_category',
			array(
				3 => 'one',
				7 => 'two',
			)
		);
		add_post_meta( $post_id, 'mf2_empty', array() );
		add_post_meta( $post_id, 'other', 'not sent' );

		$properties = $this->provider()->post_to_mf2( $post_id )['properties'];

		// Lists are re-indexed so they encode as JSON arrays.
		$this->assertSame( array( 'one', 'two' ), $properties['category'] );
		$this->assertArrayNotHasKey( 'empty', $properties );
		$this->assertArrayNotHasKey( 'other', $properties );
	}

	public function test_post_to_mf2_includes_the_featured_image() {
		$post_id       = $this->post();
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $post_id );
		set_post_thumbnail( $post_id, $attachment_id );

		$mf2 = $this->provider()->post_to_mf2( $post_id );

		$this->assertSame( array( wp_get_attachment_image_url( $attachment_id, 'full' ) ), $mf2['properties']['featured'] );
		wp_delete_attachment( $attachment_id, true );
	}

	public function test_post_micropub_sends_the_h_entry_as_json() {
		$post_id  = $this->post();
		$provider = $this->provider();

		$provider->post_micropub( $post_id, 'https://micropub.example/' );

		$this->assertCount( 1, $this->requests );
		list( $url, $args ) = $this->requests[0];
		$this->assertSame( 'https://micropub.example/', $url );
		$this->assertSame( 'POST', $args['method'] );
		$this->assertSame( 'application/json', $args['headers']['Content-type'] );
		$this->assertSame( 'Bearer abc123', $args['headers']['Authorization'] );
		$this->assertSame( $provider->post_to_mf2( $post_id ), json_decode( $args['body'], true ) );
	}

	public function test_post_micropub_keeps_an_explicit_authorization_header() {
		$this->provider()->post_micropub( $this->post(), 'https://micropub.example/', array( 'Authorization' => 'Bearer other' ) );

		$this->assertSame( 'Bearer other', $this->requests[0][1]['headers']['Authorization'] );
	}

	public function test_post_micropub_without_a_token_sends_no_authorization() {
		$this->provider( array() )->post_micropub( $this->post(), 'https://micropub.example/' );

		$this->assertArrayNotHasKey( 'Authorization', $this->requests[0][1]['headers'] );
	}

	public function test_post_micropub_rejects_an_invalid_post() {
		$result = $this->provider()->post_micropub( PHP_INT_MAX, 'https://micropub.example/' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_post', $result->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_delete_micropub_sends_a_delete_action() {
		$this->provider()->delete_micropub( 'https://mastodon.example/@me/1', 'https://micropub.example/' );

		$this->assertCount( 1, $this->requests );
		list( $url, $args ) = $this->requests[0];
		$this->assertSame( 'https://micropub.example/', $url );
		$this->assertSame( 'Bearer abc123', $args['headers']['Authorization'] );
		$this->assertSame(
			array(
				'action' => 'delete',
				'url'    => 'https://mastodon.example/@me/1',
			),
			json_decode( $args['body'], true )
		);
	}

	public function test_posse_adds_the_copys_location_to_the_links() {
		$post_id = $this->post();
		add_post_syndication_link( $post_id, 'https://elsewhere.example/1' );

		$result = $this->provider()->posse( $post_id );

		$this->assertNotWPError( $result );
		$this->assertSame( 'https://micropub.example/', $this->requests[0][0] );
		$this->assertSame( array( 'https://elsewhere.example/1', 'https://mastodon.example/@me/1' ), Syn_Meta::get_stored_links( 'post', $post_id ) );
	}

	public function test_posse_without_a_location_adds_no_link() {
		$this->respond( 202, '' );
		$post_id = $this->post();

		$this->assertNotWPError( $this->provider()->posse( $post_id ) );
		$this->assertSame( array(), Syn_Meta::get_stored_links( 'post', $post_id ) );
	}

	/**
	 * A Micropub error is returned as one, with its description (#342).
	 */
	public function test_posse_returns_an_error_for_an_error_status() {
		$this->respond(
			400,
			'{"error":"invalid_request","error_description":"Bridgy could not publish this."}',
			array( 'location' => 'https://mastodon.example/@me/1' )
		);
		$post_id = $this->post();

		$result = $this->provider()->posse( $post_id );

		$this->assertWPError( $result );
		$this->assertSame( 'Bridgy could not publish this.', $result->get_error_message() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertSame( 'invalid_request', $result->get_error_data()['data']['error'] );
		$this->assertSame( array(), Syn_Meta::get_stored_links( 'post', $post_id ) );
	}

	public function test_posse_error_without_a_description() {
		$this->respond( 500, 'Server error' );

		$result = $this->provider()->posse( $this->post() );

		$this->assertWPError( $result );
		$this->assertSame( 500, $result->get_error_data()['status'] );
		$this->assertSame( 'Server error', $result->get_error_data()['data'] );
	}

	public function test_posse_passes_request_errors_through() {
		$this->response = new WP_Error( 'http_request_failed', 'Timed out' );
		$post_id        = $this->post();

		$result = $this->provider()->posse( $post_id );

		$this->assertWPError( $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
		$this->assertSame( array(), Syn_Meta::get_stored_links( 'post', $post_id ) );
	}

	public function test_posse_without_a_token_sends_nothing() {
		$result = $this->provider( array() )->posse( $this->post() );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_token', $result->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_posse_defaults_to_the_current_post() {
		$post_id         = $this->post();
		$GLOBALS['post'] = get_post( $post_id );

		$this->provider()->posse();

		$this->assertSame( array( 'https://mastodon.example/@me/1' ), Syn_Meta::get_stored_links( 'post', $post_id ) );
	}

	/**
	 * The Bridgy providers send to Bridgy's endpoint, with their own content limit.
	 */
	public function test_bridgy_mastodon_posts_to_bridgy() {
		$provider = new SynProvider_Micropub_Bridgy_Mastodon( array( 'token' => 'abc123' ) );

		$provider->posse( $this->post( array( 'post_content' => str_repeat( 'word ', 200 ) ) ) );

		$this->assertSame( 'https://brid.gy/micropub', $this->requests[0][0] );
		$content = json_decode( $this->requests[0][1]['body'], true )['properties']['content'][0]['html'];
		$this->assertLessThanOrEqual( 500, strlen( $content ) );
	}
}
