<?php
/**
 * Tests the Micro.blog provider's feed, settings and posse(). HTTP requests are mocked.
 *
 * @package Syndication_Links
 */

class Test_Microdotblog_Feed extends WP_UnitTestCase {

	/**
	 * Requests made, as array( url, args ).
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * The body Micro.blog answers with.
	 *
	 * @var string
	 */
	private $body = '';

	/**
	 * The status Micro.blog answers with.
	 *
	 * @var int
	 */
	private $code = 200;

	public static function wpSetUpBeforeClass() {
		require_once dirname( __DIR__ ) . '/includes/apis/class-syndication-provider-microdotblog.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function set_up() {
		parent::set_up();
		$this->requests = array();
		// No current post, so a refresh uses the current user.
		$GLOBALS['post'] = null;
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http' ), 10 );
		delete_option( 'syndication_provider_enable' );
		wp_clear_scheduled_hook( 'microdotblog_get_ids' );
		parent::tear_down();
	}

	/**
	 * Records the request and answers it.
	 *
	 * @param false|array $pre  Preempted response.
	 * @param array       $args Request arguments.
	 * @param string      $url  URL.
	 * @return array The response.
	 */
	public function mock_http( $pre, $args, $url ) {
		$this->requests[] = array( $url, $args );
		return array(
			'headers'  => array(),
			'body'     => $this->body,
			'response' => array(
				'code'    => $this->code,
				'message' => get_status_header_desc( $this->code ),
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Creates a user with a Micro.blog username.
	 *
	 * @return int User ID.
	 */
	private function microblogger() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $user_id, 'microblog', 'someone' );
		return $user_id;
	}

	/**
	 * A query with the given query vars.
	 *
	 * @param array $vars Query vars.
	 * @return WP_Query
	 */
	private function query( $vars ) {
		$query             = new WP_Query();
		$query->query_vars = $vars;
		return $query;
	}

	public function test_the_feed_is_hooked_only_when_enabled() {
		$disabled = new Syndication_Provider_MicroDotBlog();
		$this->assertFalse( has_action( 'pre_get_posts', array( $disabled, 'create_feed' ) ) );

		update_option( 'syndication_provider_enable', array( 'microdotblog' ) );
		$enabled = new Syndication_Provider_MicroDotBlog();

		$this->assertSame( 10, has_action( 'pre_get_posts', array( $enabled, 'create_feed' ) ) );
		$this->assertSame( 10, has_filter( 'query_vars', array( $enabled, 'query_var' ) ) );
		$this->assertSame( 10, has_action( 'init', array( $enabled, 'rewrite' ) ) );
		$this->assertSame( 10, has_filter( 'user_contactmethods', array( $enabled, 'user_contactmethods' ) ) );
		$this->assertSame( 10, has_action( 'microdotblog_get_ids', array( $enabled, 'get_microblog_post' ) ) );
		$this->assertSame( 10, has_filter( 'syndication_links_feed_display', array( $enabled, 'feed_display' ) ) );

		remove_action( 'pre_get_posts', array( $enabled, 'create_feed' ) );
		remove_filter( 'query_vars', array( $enabled, 'query_var' ) );
		remove_action( 'init', array( $enabled, 'rewrite' ) );
		remove_action( 'admin_init', array( $enabled, 'admin_init' ), 11 );
		remove_filter( 'user_contactmethods', array( $enabled, 'user_contactmethods' ) );
		remove_action( 'microdotblog_get_ids', array( $enabled, 'get_microblog_post' ) );
		remove_filter( 'syndication_links_feed_display', array( $enabled, 'feed_display' ) );
	}

	public function test_profiles_get_a_username_field() {
		$this->assertArrayHasKey( 'microblog', Syndication_Provider_MicroDotBlog::user_contactmethods( array() ) );
		$this->assertSame( array( 'microblog' => 'Mine' ), Syndication_Provider_MicroDotBlog::user_contactmethods( array( 'microblog' => 'Mine' ) ) );
	}

	public function test_the_query_var_is_added() {
		$this->assertSame( array( 'p', 'microdotblog' ), Syndication_Provider_MicroDotBlog::query_var( array( 'p' ) ) );
	}

	public function test_other_queries_are_left_alone() {
		$query = $this->query( array( 'p' => 1 ) );

		Syndication_Provider_MicroDotBlog::create_feed( $query );

		$this->assertSame( array( 'p' => 1 ), $query->query_vars );
	}

	/**
	 * The feed lists only the posts syndicated to Micro.blog, as RSS unless a feed is named.
	 */
	public function test_the_feed_lists_the_syndicated_posts() {
		$query = $this->query( array( 'microdotblog' => '1' ) );

		Syndication_Provider_MicroDotBlog::create_feed( $query );

		$this->assertSame( 'rss2', $query->get( 'feed' ) );
		$this->assertSame( '_syndication_links_microdotblog', $query->get( 'meta_query' )[0][0]['key'] );
		$this->assertSame( array(), $this->requests );

		$query = $this->query(
			array(
				'microdotblog' => '1',
				'feed'         => 'atom',
			)
		);
		Syndication_Provider_MicroDotBlog::create_feed( $query );
		$this->assertSame( 'atom', $query->get( 'feed' ) );
	}

	public function test_the_feed_only_has_syndicated_posts() {
		$sent  = self::factory()->post->create();
		$other = self::factory()->post->create();
		add_post_meta( $sent, '_syndication_links_microdotblog', 1 );
		$query = $this->query( array( 'microdotblog' => '1' ) );
		Syndication_Provider_MicroDotBlog::create_feed( $query );
		$query->set( 'fields', 'ids' );

		$this->assertSame( array( $sent ), $query->get_posts() );
		$this->assertNotContains( $other, $query->get_posts() );
	}

	/**
	 * With refresh, the account's Micro.blog posts are fetched first.
	 */
	public function test_a_refresh_fetches_the_posts() {
		wp_set_current_user( $this->microblogger() );
		$this->body = wp_json_encode( array( 'items' => array() ) );

		Syndication_Provider_MicroDotBlog::create_feed( $this->query( array( 'microdotblog' => 'refresh' ) ) );

		$this->assertSame( 'https://micro.blog/posts/someone', $this->requests[0][0] );
	}

	public function test_fetching_the_posts_adds_each_copy() {
		$user_id = $this->microblogger();
		$first   = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$second  = self::factory()->post->create( array( 'post_author' => $user_id ) );
		wp_set_current_user( $user_id );
		$this->body = wp_json_encode(
			array(
				'items' => array(
					array(
						'id'  => '1',
						'url' => get_permalink( $first ),
					),
					'not an item',
					array( 'url' => get_permalink( $second ) ),
					array(
						'id'  => '2',
						'url' => get_permalink( $second ),
					),
				),
			)
		);

		$this->assertTrue( Syndication_Provider_MicroDotBlog::retrieve_json_feed() );
		$this->assertSame( array( 'https://micro.blog/someone/1' ), get_post_syndication_links_data( $first ) );
		$this->assertSame( array( 'https://micro.blog/someone/2' ), get_post_syndication_links_data( $second ) );
	}

	/**
	 * Looking for one post stops when its copy is found.
	 */
	public function test_fetching_for_a_post_stops_at_its_copy() {
		$user_id = $this->microblogger();
		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$later   = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$this->body = wp_json_encode(
			array(
				'items' => array(
					array(
						'id'  => '1',
						'url' => get_permalink( $post_id ),
					),
					array(
						'id'  => '2',
						'url' => get_permalink( $later ),
					),
				),
			)
		);

		$this->assertTrue( Syndication_Provider_MicroDotBlog::retrieve_json_feed( $post_id ) );
		$this->assertSame( array(), get_post_syndication_links_data( $later ) );
	}

	public function test_fetching_the_posts_reports_an_error_status() {
		$post_id    = self::factory()->post->create( array( 'post_author' => $this->microblogger() ) );
		$this->code = 500;

		$result = Syndication_Provider_MicroDotBlog::retrieve_json_feed( $post_id );

		$this->assertWPError( $result );
		$this->assertSame( 500, $result->get_error_data()['status'] );
	}

	/**
	 * Micro.blog posts from its feed, so the feed leaves the links out.
	 */
	public function test_the_micro_blog_feed_has_no_links() {
		$this->assertTrue( Syndication_Provider_MicroDotBlog::feed_display( true ) );

		set_query_var( 'microdotblog', '1' );
		$this->assertFalse( Syndication_Provider_MicroDotBlog::feed_display( true ) );
		set_query_var( 'microdotblog', '' );
	}

	public function test_the_rewrite_rule() {
		global $wp_rewrite;

		Syndication_Provider_MicroDotBlog::rewrite();

		$this->assertSame( 'index.php?microdotblog=1&withcomments=0&feed=$matches[1]', $wp_rewrite->extra_rules_top['^microdotblog/feed/(.*)/?'] );
		unset( $wp_rewrite->extra_rules_top['^microdotblog/feed/(.*)/?'] );
	}

	/**
	 * Without the JSON Feed plugin, the feed is the default feed.
	 */
	public function test_the_feed_url() {
		$this->assertSame( add_query_arg( 'microdotblog', 1, get_feed_link( get_default_feed() ) ), Syndication_Provider_MicroDotBlog::get_feed() );
	}

	public function test_posse_adds_the_post_to_the_feed_and_pings_micro_blog() {
		$post_id = self::factory()->post->create();

		$result = ( new Syndication_Provider_MicroDotBlog() )->posse( $post_id );

		$this->assertSame( array( 'message' => 'Posted to Micro.blog' ), $result );
		$this->assertSame( '1', get_post_meta( $post_id, '_syndication_links_microdotblog', true ) );
		$this->assertSame( 'https://micro.blog/ping?url=' . rawurlencode( Syndication_Provider_MicroDotBlog::get_feed() ), $this->requests[0][0] );
		$this->assertSame( 'POST', $this->requests[0][1]['method'] );
		$this->assertNotFalse( wp_next_scheduled( 'microdotblog_get_ids', array( $post_id ) ) );
	}

	public function test_the_settings_section_shows_the_feed() {
		global $wp_settings_sections;

		Syndication_Provider_MicroDotBlog::admin_init();
		ob_start();
		Syndication_Provider_MicroDotBlog::options_callback();
		$html = ob_get_clean();

		$this->assertArrayHasKey( 'microdotblog_options', $wp_settings_sections['syndication_provider_options'] );
		$this->assertStringContainsString( '<a href="' . esc_url( Syndication_Provider_MicroDotBlog::get_feed() ) . '">', $html );
	}
}
