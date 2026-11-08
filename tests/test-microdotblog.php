<?php
/**
 * Tests the Micro.blog provider's lookups of the copies Micro.blog made.
 *
 * @package Syndication_Links
 */

class Test_Microdotblog extends WP_UnitTestCase {

	/**
	 * The body Micro.blog answers with.
	 *
	 * @var string
	 */
	private $body = '';

	public function set_up() {
		parent::set_up();
		require_once dirname( __DIR__ ) . '/includes/apis/class-syndication-provider-microdotblog.php';
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http' ), 10 );
		parent::tear_down();
	}

	/**
	 * Answers every request with $this->body.
	 *
	 * @return array The response.
	 */
	public function mock_http() {
		return array(
			'headers'  => array(),
			'body'     => $this->body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Creates a post by an author with a Micro.blog username.
	 *
	 * @return int Post ID.
	 */
	private function post_by_microblogger() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $author, 'microblog', 'someone' );
		return self::factory()->post->create( array( 'post_author' => $author ) );
	}

	public function test_the_copy_is_added_to_the_posts_links() {
		$post_id    = $this->post_by_microblogger();
		$this->body = wp_json_encode(
			array(
				'items' => array(
					array(
						'id'  => '12345',
						'url' => get_permalink( $post_id ),
					),
				),
			)
		);

		$this->assertTrue( Syndication_Provider_MicroDotBlog::retrieve_json_feed( $post_id ) );
		$this->assertSame( array( 'https://micro.blog/someone/12345' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	public function test_a_response_without_posts_is_an_error() {
		$post_id    = $this->post_by_microblogger();
		$this->body = 'not json';

		$this->assertWPError( Syndication_Provider_MicroDotBlog::retrieve_json_feed( $post_id ) );
	}

	/**
	 * A feed refresh has no post: without a username for the current user, it stops quietly.
	 */
	public function test_a_refresh_without_a_post_or_username_stops() {
		$GLOBALS['post'] = null;

		$this->assertFalse( Syndication_Provider_MicroDotBlog::retrieve_json_feed() );
	}

	public function test_a_non_json_lookup_is_not_found() {
		$post_id    = self::factory()->post->create();
		$this->body = 'not json';

		$this->assertFalse( Syndication_Provider_MicroDotBlog::get_microblog_post( $post_id ) );
	}

	public function test_a_lookup_adds_the_home_page_url() {
		$post_id    = self::factory()->post->create();
		$this->body = wp_json_encode( array( 'home_page_url' => 'https://micro.blog/someone/12345' ) );

		$this->assertSame( 'https://micro.blog/someone/12345', Syndication_Provider_MicroDotBlog::get_microblog_post( $post_id ) );
		$this->assertSame( array( 'https://micro.blog/someone/12345' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}
}
