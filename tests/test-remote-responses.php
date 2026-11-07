<?php
/**
 * Tests for how providers handle remote responses (#243), with HTTP requests mocked.
 *
 * @package Syndication_Links
 */

class Test_Remote_Responses extends WP_UnitTestCase {

	/**
	 * Requests made, as array( url, args ).
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * The mocked response: status code, body and headers.
	 *
	 * @var array
	 */
	private $response;

	public function set_up() {
		parent::set_up();
		$this->requests = array();
		$this->respond( 200, '{"syndicate-to":[]}' );
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
	}

	/**
	 * Sets the mocked response.
	 *
	 * @param int    $code    Status code.
	 * @param string $body    Body.
	 * @param array  $headers Headers.
	 */
	private function respond( $code, $body, $headers = array( 'content-type' => 'application/json' ) ) {
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
	 * @return array
	 */
	public function mock_http( $pre, $args, $url ) {
		$this->requests[] = array( $url, $args );
		return $this->response;
	}

	/**
	 * A Micropub provider with a token.
	 *
	 * @return SynProvider_Micropub
	 */
	private function micropub() {
		return new SynProvider_Micropub( array( 'token' => 'abc123' ) );
	}

	public function test_fetch_micropub_returns_the_json() {
		$result = $this->micropub()->fetch_micropub( 'https://micropub.example/endpoint', array( 'q' => 'config' ) );

		$this->assertSame( array( 'syndicate-to' => array() ), $result );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://micropub.example/endpoint?q=config', $this->requests[0][0] );
		$this->assertSame( 'application/json', $this->requests[0][1]['headers']['Accept'] );
	}

	public function test_fetch_micropub_sends_the_token() {
		$this->micropub()->fetch_micropub( 'https://micropub.example/endpoint', array( 'q' => 'config' ) );

		$this->assertSame( 'Bearer abc123', $this->requests[0][1]['headers']['Authorization'] );
	}

	public function test_fetch_micropub_keeps_an_explicit_authorization_header() {
		$this->micropub()->fetch_micropub( 'https://micropub.example/endpoint', array( 'q' => 'config' ), array( 'Authorization' => 'Bearer other' ) );

		$this->assertSame( 'Bearer other', $this->requests[0][1]['headers']['Authorization'] );
	}

	public function test_fetch_micropub_without_a_token_sends_no_authorization() {
		$provider = new SynProvider_Micropub();

		$provider->fetch_micropub( 'https://micropub.example/endpoint', array( 'q' => 'config' ) );

		$this->assertArrayNotHasKey( 'Authorization', $this->requests[0][1]['headers'] );
	}

	/**
	 * Every 2xx status is a success, not only 200.
	 *
	 * @dataProvider data_success_codes
	 *
	 * @param int $code Status code.
	 */
	public function test_fetch_micropub_accepts_any_2xx( $code ) {
		$this->respond( $code, '{"ok":true}' );

		$this->assertSame( array( 'ok' => true ), $this->micropub()->fetch_micropub( 'https://micropub.example/endpoint', array() ) );
	}

	public function data_success_codes() {
		return array(
			'200' => array( 200 ),
			'201' => array( 201 ),
			'202' => array( 202 ),
		);
	}

	/**
	 * Any other status is an invalid_response error with the body and status.
	 *
	 * @dataProvider data_error_codes
	 *
	 * @param int $code Status code.
	 */
	public function test_fetch_micropub_rejects_other_statuses( $code ) {
		$this->respond( $code, 'Nope' );

		$result = $this->micropub()->fetch_micropub( 'https://micropub.example/endpoint', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_response', $result->get_error_code() );
		$this->assertSame( 'Nope', $result->get_error_message() );
		$this->assertSame( array( 'status' => $code ), $result->get_error_data() );
	}

	/**
	 * Status codes that aren't a success.
	 *
	 * @return array[]
	 */
	public function data_error_codes() {
		return array(
			'301' => array( 301 ),
			'401' => array( 401 ),
			'404' => array( 404 ),
			'500' => array( 500 ),
		);
	}

	public function test_fetch_micropub_rejects_a_body_that_isnt_json() {
		$this->respond( 200, '<html></html>', array( 'content-type' => 'text/html' ) );

		$result = $this->micropub()->fetch_micropub( 'https://micropub.example/endpoint', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'not_json_response', $result->get_error_code() );
		$this->assertSame( array( 'type' => 'text/html' ), $result->get_error_data() );
	}

	public function test_fetch_micropub_passes_request_errors_through() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http' ), 10 );
		$error = new WP_Error( 'http_request_failed', 'Timed out' );
		add_filter(
			'pre_http_request',
			function () use ( $error ) {
				return $error;
			}
		);

		$this->assertSame( $error, $this->micropub()->fetch_micropub( 'https://micropub.example/endpoint', array() ) );
	}

	/**
	 * Micro.blog's conversation lookup adds the link it finds.
	 */
	public function test_microdotblog_lookup_adds_the_link() {
		$post_id = self::factory()->post->create();
		$this->respond( 200, '{"home_page_url":"https://micro.blog/someone/123"}' );

		$result = Syndication_Provider_MicroDotBlog::get_microblog_post( $post_id );

		$this->assertSame( 'https://micro.blog/someone/123', $result );
		$this->assertSame( array( 'https://micro.blog/someone/123' ), array_values( get_post_syndication_links_data( $post_id ) ) );
		$this->assertStringStartsWith( 'https://micro.blog/conversation.js?', $this->requests[0][0] );
	}

	public function test_microdotblog_lookup_ignores_errors() {
		$post_id = self::factory()->post->create();
		$this->respond( 404, 'Not found' );

		$this->assertFalse( Syndication_Provider_MicroDotBlog::get_microblog_post( $post_id ) );
		$this->assertSame( array(), get_post_syndication_links_data( $post_id ) );
	}
}
