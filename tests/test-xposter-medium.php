<?php
/**
 * Tests for XPoster's Mastodon and Bluesky links, and importing Medium links.
 *
 * @package Syndication_Links
 */

class Test_Xposter_Medium extends WP_UnitTestCase {

	/**
	 * Requests made to Bluesky.
	 *
	 * @var string[]
	 */
	private $requests = array();

	/**
	 * The mocked feed: CID => record key.
	 *
	 * @var string[]
	 */
	private $feed = array();

	public function set_up() {
		parent::set_up();
		$this->requests = array();
		$this->feed     = array();
		add_filter( 'pre_http_request', array( $this, 'mock_bluesky' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_bluesky' ), 10 );
		foreach ( array( 'wpt_mastodon_instance', 'wpt_mastodon_username', 'wpt_bluesky_username', 'wtt_twitter_username' ) as $option ) {
			delete_option( $option );
		}
		delete_transient( 'syn_bsky_' . md5( 'someone.bsky.social' ) );
		parent::tear_down();
	}

	/**
	 * Answers getAuthorFeed requests from $this->feed.
	 *
	 * @param false|array $pre  Preempted response.
	 * @param array       $args Request arguments.
	 * @param string      $url  URL.
	 * @return false|array
	 */
	public function mock_bluesky( $pre, $args, $url ) {
		if ( false === strpos( $url, 'public.api.bsky.app' ) ) {
			return $pre;
		}
		$this->requests[] = $url;
		$feed             = array();
		foreach ( $this->feed as $cid => $rkey ) {
			$feed[] = array(
				'post' => array(
					'cid' => $cid,
					'uri' => 'at://did:plc:abc/app.bsky.feed.post/' . $rkey,
				),
			);
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'feed' => $feed ) ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function mastodon_post() {
		update_option( 'wpt_mastodon_instance', 'https://toot.example.social/' );
		update_option( 'wpt_mastodon_username', 'someone' );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_wpt_status_id', '789' );
		return $post_id;
	}

	public function test_xposter_mastodon_status_is_shown() {
		$post_id = $this->mastodon_post();

		$this->assertSame( array( 'https://toot.example.social/@someone/789' ), get_post_syndication_links_data( $post_id ) );
		$this->assertSame( 'mastodon', Syn_Link_Domain_Icon_Map::url_to_name( 'https://toot.example.social/@someone/789' ) );
	}

	public function test_xposter_mastodon_uses_the_authors_own_account() {
		$author  = self::factory()->user->create();
		$post_id = $this->mastodon_post();
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_author' => $author,
			)
		);
		update_user_meta( $author, 'wpt_mastodon_instance', 'https://other.example.social' );
		update_user_meta( $author, 'wpt_mastodon_username', 'author@other.example.social' );

		$this->assertSame( 'https://other.example.social/@author/789', Social_Plugins::xposter_mastodon_link( $post_id ) );
	}

	public function test_xposter_mastodon_needs_an_account() {
		$post_id = $this->mastodon_post();
		delete_option( 'wpt_mastodon_username' );

		$this->assertFalse( Social_Plugins::xposter_mastodon_link( $post_id ) );
	}

	private function bluesky_post() {
		update_option( 'wpt_bluesky_username', '@someone.bsky.social' );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_wpt_bluesky_id', 'bafycid1' );
		return $post_id;
	}

	public function test_xposter_bluesky_post_is_found_by_its_cid() {
		$this->feed = array(
			'bafyother' => 'rkey0',
			'bafycid1'  => 'rkey1',
		);
		$post_id    = $this->bluesky_post();

		$this->assertSame( 'https://bsky.app/profile/someone.bsky.social/post/rkey1', Social_Plugins::xposter_bluesky_link( $post_id ) );
		$this->assertStringContainsString( 'actor=someone.bsky.social', $this->requests[0] );

		// The feed is cached, so a second lookup doesn't ask again.
		Social_Plugins::xposter_bluesky_link( $post_id );
		$this->assertCount( 1, $this->requests );
	}

	public function test_bluesky_isnt_requested_when_showing_links() {
		$this->feed = array( 'bafycid1' => 'rkey1' );
		$post_id    = $this->bluesky_post();

		$this->assertSame( array(), get_post_syndication_links_data( $post_id ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_saving_a_bluesky_cid_schedules_the_lookup() {
		$post_id = $this->bluesky_post();

		$this->assertNotFalse( wp_next_scheduled( 'syn_xposter_bluesky', array( $post_id, 1 ) ) );
		wp_clear_scheduled_hook( 'syn_xposter_bluesky', array( $post_id, 1 ) );
	}

	public function test_the_lookup_records_the_link() {
		$this->feed = array( 'bafycid1' => 'rkey1' );
		$post_id    = $this->bluesky_post();

		Social_Plugins::record_xposter_bluesky( $post_id, 1 );

		$this->assertSame( array( 'https://bsky.app/profile/someone.bsky.social/post/rkey1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	public function test_the_lookup_retries_twice_while_the_post_isnt_in_the_feed() {
		$post_id = $this->bluesky_post();
		wp_clear_scheduled_hook( 'syn_xposter_bluesky', array( $post_id, 1 ) );

		Social_Plugins::record_xposter_bluesky( $post_id, 1 );
		$this->assertNotFalse( wp_next_scheduled( 'syn_xposter_bluesky', array( $post_id, 2 ) ) );

		Social_Plugins::record_xposter_bluesky( $post_id, 3 );
		$this->assertFalse( wp_next_scheduled( 'syn_xposter_bluesky', array( $post_id, 4 ) ) );
		wp_clear_scheduled_hook( 'syn_xposter_bluesky', array( $post_id, 2 ) );
	}

	public function test_importing_xposter_includes_mastodon_and_bluesky() {
		$this->feed = array( 'bafycid1' => 'rkey1' );
		$post_id    = $this->mastodon_post();
		update_option( 'wpt_bluesky_username', 'someone.bsky.social' );
		update_post_meta( $post_id, '_wpt_bluesky_id', 'bafycid1' );
		wp_clear_scheduled_hook( 'syn_xposter_bluesky', array( $post_id, 1 ) );

		Syn_Plugin_Import::import_batch( 'xposter' );

		$this->assertEqualsCanonicalizing(
			array( 'https://toot.example.social/@someone/789', 'https://bsky.app/profile/someone.bsky.social/post/rkey1' ),
			get_post_meta( $post_id, 'mf2_syndication', true )
		);
	}

	/**
	 * Stores medium_post meta the way the Medium plugin did: a serialized Medium_Post,
	 * a class that isn't loaded here.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  Medium status.
	 */
	private function add_medium_meta( $post_id, $status ) {
		global $wpdb;
		$url   = 'https://medium.com/@someone/a-post-123';
		$value = sprintf( 'O:11:"Medium_Post":2:{s:3:"url";s:%1$d:"%2$s";s:6:"status";s:%3$d:"%4$s";}', strlen( $url ), $url, strlen( $status ), $status );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->postmeta,
			array(
				'post_id'    => $post_id,
				'meta_key'   => 'medium_post', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		wp_cache_delete( $post_id, 'post_meta' );
	}

	public function test_medium_links_are_imported_without_the_medium_plugin() {
		$this->assertFalse( class_exists( 'Medium_Post', false ) );
		$post_id = self::factory()->post->create();
		$this->add_medium_meta( $post_id, 'public' );

		$this->assertSame( array(), get_post_syndication_links_data( $post_id ), 'Medium links are no longer shown.' );

		$result = Syn_Plugin_Import::import_batch( 'medium' );

		$this->assertSame( 1, $result['posts'] );
		$this->assertSame( array( 'https://medium.com/@someone/a-post-123' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	public function test_medium_drafts_arent_imported() {
		$post_id = self::factory()->post->create();
		$this->add_medium_meta( $post_id, 'draft' );

		$this->assertSame( array(), Social_Plugins::medium_links( $post_id ) );
	}
}
