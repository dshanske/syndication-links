<?php
/**
 * Tests deleting a post's syndicated copies when it's trashed or deleted (#184).
 *
 * HTTP requests and send_webmention() are mocked.
 *
 * @package Syndication_Links
 */

class Test_Delete_Copies extends WP_UnitTestCase {

	/**
	 * Registered providers before each test, restored afterwards.
	 *
	 * @var array
	 */
	private $saved_targets;

	/**
	 * Requests made, as array( url, args ).
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * Webmentions sent, as array( source, target ).
	 *
	 * @var array[]
	 */
	private $webmentions = array();

	/**
	 * The status code mocked responses have.
	 *
	 * @var int
	 */
	private $code = 200;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		// Only loaded when SYNDICATION_LINKS_BRIDGY_WEBMENTION is set.
		syndication_links_load(
			array(
				'class-synprovider-webmention-bridgy.php',
				'class-synprovider-webmention-bridgy-github.php',
				'class-synprovider-webmention-bridgy-mastodon.php',
			),
			'includes/webmentions/'
		);
	}

	public function set_up() {
		parent::set_up();
		$property            = $this->targets();
		$this->saved_targets = $property->getValue();
		$property->setValue(
			null,
			array(
				'micropub-mastodon-bridgy' => new SynProvider_Micropub_Bridgy_Mastodon( array( 'token' => 'abc123' ) ),
				'micropub-github-bridgy'   => new SynProvider_Micropub_Bridgy_Github( array( 'token' => 'abc123' ) ),
				'bridgy-fed'               => new SynProvider_Webmention_Bridgy_Fed(),
			)
		);
		$this->requests    = array();
		$this->webmentions = array();
		$this->code        = 200;
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
		add_filter( 'syn_test_send_webmention', array( $this, 'mock_webmention' ), 10, 3 );
	}

	public function tear_down() {
		$this->targets()->setValue( null, $this->saved_targets );
		remove_filter( 'syn_test_send_webmention', array( $this, 'mock_webmention' ), 10 );
		delete_option( 'syndication_delete_on_trash' );
		wp_clear_scheduled_hook( 'syn_delete_copies' );
		_set_cron_array( array() );
		parent::tear_down();
	}

	/**
	 * Accessor for the protected Post_Syndication::$targets registry.
	 *
	 * @return ReflectionProperty
	 */
	private function targets() {
		$property = new ReflectionProperty( 'Post_Syndication', 'targets' );
		$property->setAccessible( true );
		return $property;
	}

	/**
	 * A response with $this->code.
	 *
	 * @return array
	 */
	private function response() {
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => $this->code,
				'message' => get_status_header_desc( $this->code ),
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Records the request and answers it.
	 *
	 * @param false|array $pre  Preempted response.
	 * @param array       $args Request arguments.
	 * @param string      $url  URL.
	 * @return array
	 */
	public function mock_http( $pre, $args, $url ) {
		$this->requests[] = array( $url, $args );
		return $this->response();
	}

	/**
	 * Records the webmention and answers it.
	 *
	 * @param mixed  $result The stub's result.
	 * @param string $source Source URL.
	 * @param string $target Target URL.
	 * @return array
	 */
	public function mock_webmention( $result, $source, $target ) {
		$this->webmentions[] = array( $source, $target );
		return $this->response();
	}

	/**
	 * A log entry, as Post_Syndication::syndication_log() writes it.
	 *
	 * @param string $uid    Provider UID.
	 * @param int    $code   Status code, or 0 for an error.
	 * @param string $url    Optional. The copy's URL.
	 * @param string $action Optional. 'delete' for a delete.
	 * @return array
	 */
	private function entry( $uid, $code, $url = '', $action = '' ) {
		$data  = $code ? array(
			'body' => '',
			'code' => $code,
			'url'  => $url,
		) : array( 'message' => 'It failed' );
		$entry = array(
			'date' => time(),
			'uid'  => $uid,
			'name' => $uid,
			'data' => $data,
		);
		if ( $action ) {
			$entry['action'] = $action;
		}
		return $entry;
	}

	/**
	 * A published post sent to Mastodon by Micropub and to Bridgy Fed by webmention.
	 *
	 * @return int Post ID.
	 */
	private function sent_post() {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'syndication_log',
			array(
				$this->entry( 'micropub-mastodon-bridgy', 201, 'https://mastodon.example/@me/1' ),
				$this->entry( 'bridgy-fed', 202 ),
			)
		);
		return $post_id;
	}

	/**
	 * The scheduled syn_delete_copies events' arguments and times.
	 *
	 * @return array[] As array( timestamp, args ).
	 */
	private function scheduled() {
		$events = array();
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			if ( isset( $hooks['syn_delete_copies'] ) ) {
				foreach ( $hooks['syn_delete_copies'] as $event ) {
					$events[] = array( $timestamp, $event['args'] );
				}
			}
		}
		return $events;
	}

	public function test_sent_copies_lists_what_each_provider_made() {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'syndication_log',
			array(
				$this->entry( 'micropub-mastodon-bridgy', 201, 'https://mastodon.example/@me/1' ),
				$this->entry( 'micropub-mastodon-bridgy', 400 ),
				$this->entry( 'micropub-mastodon-bridgy', 0 ),
				$this->entry( 'micropub-mastodon-bridgy', 201, 'https://mastodon.example/@me/1' ),
				// Bridgy can't delete on GitHub.
				$this->entry( 'micropub-github-bridgy', 201, 'https://github.com/me/repo/issues/1' ),
				// Webmention providers resend, so they're listed without a URL.
				$this->entry( 'bridgy-fed', 202 ),
				$this->entry( 'not-registered', 201, 'https://example.com/1' ),
				'not an entry',
			)
		);

		$this->assertSame(
			array(
				'micropub-mastodon-bridgy' => array( 'https://mastodon.example/@me/1' ),
				'bridgy-fed'               => array(),
			),
			Post_Syndication::sent_copies( $post_id )
		);
	}

	/**
	 * After a delete, the copy is gone: a later trash doesn't delete it again.
	 */
	public function test_sent_copies_leaves_out_deleted_copies() {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'syndication_log',
			array(
				$this->entry( 'micropub-mastodon-bridgy', 201, 'https://mastodon.example/@me/1' ),
				$this->entry( 'bridgy-fed', 202 ),
				$this->entry( 'micropub-mastodon-bridgy', 200, '', 'delete' ),
				// A failed delete leaves the copy.
				$this->entry( 'bridgy-fed', 500, '', 'delete' ),
			)
		);

		$this->assertSame( array( 'bridgy-fed' => array() ), Post_Syndication::sent_copies( $post_id ) );
	}

	public function test_providers_that_can_delete() {
		$this->assertTrue( ( new SynProvider_Micropub_Bridgy_Mastodon() )->supports_delete() );
		$this->assertTrue( ( new SynProvider_Micropub_Bridgy_Bluesky() )->supports_delete() );
		$this->assertTrue( ( new SynProvider_Webmention_Bridgy_Fed() )->supports_delete() );
		$this->assertTrue( ( new SynProvider_Webmention_Bridgy_Mastodon() )->supports_delete() );
		$this->assertFalse( ( new SynProvider_Micropub_Bridgy_Github() )->supports_delete() );
		$this->assertFalse( ( new SynProvider_Micropub_Bridgy_Flickr() )->supports_delete() );
		$this->assertFalse( ( new SynProvider_Webmention_Bridgy_Github() )->supports_delete() );
	}

	/**
	 * Trashing schedules the delete after a delay, with the post's URL from before.
	 */
	public function test_trashing_schedules_the_delete() {
		$post_id   = $this->sent_post();
		$permalink = get_permalink( $post_id );

		wp_trash_post( $post_id );

		$events = $this->scheduled();
		$this->assertCount( 1, $events );
		$this->assertEqualsWithDelta( time() + Post_Syndication::DELETE_DELAY, $events[0][0], 60 );
		$this->assertSame(
			array(
				$post_id,
				$permalink,
				array(
					'micropub-mastodon-bridgy' => array( 'https://mastodon.example/@me/1' ),
					'bridgy-fed'               => array(),
				),
			),
			$events[0][1]
		);
	}

	public function test_deleting_schedules_the_delete_now() {
		$post_id = $this->sent_post();

		wp_delete_post( $post_id, true );

		$events = $this->scheduled();
		$this->assertCount( 1, $events );
		$this->assertEqualsWithDelta( time(), $events[0][0], 60 );
	}

	/**
	 * Its copies were deleted when it was trashed.
	 */
	public function test_deleting_a_trashed_post_schedules_nothing_more() {
		$post_id = $this->sent_post();
		wp_trash_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertCount( 1, $this->scheduled() );
	}

	public function test_nothing_is_scheduled_for_a_post_that_wasnt_sent() {
		wp_trash_post( self::factory()->post->create() );

		$this->assertSame( array(), $this->scheduled() );
	}

	public function test_nothing_is_scheduled_when_the_setting_is_off() {
		update_option( 'syndication_delete_on_trash', '0' );

		wp_trash_post( $this->sent_post() );

		$this->assertSame( array(), $this->scheduled() );
	}

	public function test_the_copies_to_delete_are_filterable() {
		add_filter( 'syndication_links_delete_copies', '__return_empty_array' );

		wp_trash_post( $this->sent_post() );

		remove_filter( 'syndication_links_delete_copies', '__return_empty_array' );
		$this->assertSame( array(), $this->scheduled() );
	}

	public function test_the_copies_are_deleted_and_logged() {
		$post_id   = $this->sent_post();
		$permalink = get_permalink( $post_id );
		wp_trash_post( $post_id );
		$args = $this->scheduled()[0][1];

		Post_Syndication::delete_copies( ...$args );

		// Micropub deletes the copy by its URL.
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://brid.gy/micropub', $this->requests[0][0] );
		$this->assertSame(
			array(
				'action' => 'delete',
				'url'    => 'https://mastodon.example/@me/1',
			),
			json_decode( $this->requests[0][1]['body'], true )
		);
		// The webmention is sent again from the post's old URL.
		$this->assertSame( array( array( $permalink, 'https://fed.brid.gy' ) ), $this->webmentions );

		$log = get_post_meta( $post_id, 'syndication_log', true );
		$this->assertCount( 4, $log );
		$this->assertSame( array( 'delete', 'delete' ), wp_list_pluck( array_slice( $log, 2 ), 'action' ) );
		$this->assertSame( array( 'micropub-mastodon-bridgy', 'bridgy-fed' ), wp_list_pluck( array_slice( $log, 2 ), 'uid' ) );
	}

	/**
	 * Restoring the post before the event runs keeps its copies.
	 */
	public function test_a_restored_post_keeps_its_copies() {
		$post_id = $this->sent_post();
		wp_trash_post( $post_id );
		$args = $this->scheduled()[0][1];
		wp_untrash_post( $post_id );

		Post_Syndication::delete_copies( ...$args );

		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->webmentions );
	}

	/**
	 * A deleted post's copies are deleted too, with nothing left to log to.
	 */
	public function test_a_deleted_posts_copies_are_deleted() {
		$post_id = $this->sent_post();
		wp_delete_post( $post_id, true );

		Post_Syndication::delete_copies( ...$this->scheduled()[0][1] );

		$this->assertCount( 1, $this->requests );
		$this->assertCount( 1, $this->webmentions );
	}

	public function test_micropub_delete_needs_a_copy_and_a_token() {
		$this->assertNull( ( new SynProvider_Micropub_Bridgy_Mastodon( array( 'token' => 'abc123' ) ) )->delete( 1, 'https://example.com/post', array() ) );

		$result = ( new SynProvider_Micropub_Bridgy_Mastodon( array( 'token' => '' ) ) )->delete( 1, 'https://example.com/post', array( 'https://mastodon.example/@me/1' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_token', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
	}

	public function test_micropub_delete_returns_an_error_for_an_error_status() {
		$this->code = 400;

		$result = ( new SynProvider_Micropub_Bridgy_Mastodon( array( 'token' => 'abc123' ) ) )->delete( 1, 'https://example.com/post', array( 'https://mastodon.example/@me/1' ) );

		$this->assertWPError( $result );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_webmention_delete_returns_an_error_for_an_error_status() {
		$this->code = 400;

		$result = ( new SynProvider_Webmention_Bridgy_Fed() )->delete( 1, 'https://example.com/post' );

		$this->assertWPError( $result );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertSame( array( array( 'https://example.com/post', 'https://fed.brid.gy' ) ), $this->webmentions );
	}

	public function test_the_log_marks_deletes() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'syndication_log', array( $this->entry( 'bridgy-fed', 202, '', 'delete' ) ) );

		ob_start();
		Post_Syndication::syndication_log_output( $post_id );
		$html = ob_get_clean();

		$this->assertStringContainsString( ': bridgy-fed (delete)</summary>', $html );
	}
}
