<?php
/**
 * Tests for storing and reading syndication links: Syn_Meta and the template API.
 *
 * @package Syndication_Links
 */

class Test_Link_Storage extends WP_UnitTestCase {

	/**
	 * Syn_Meta::init() registers the meta key for posts and comments.
	 */
	public function test_meta_is_registered_for_posts_and_comments() {
		// The test framework unregisters all meta keys after each test, so register them again.
		Syn_Meta::init();

		$this->assertTrue( registered_meta_key_exists( 'post', 'mf2_syndication' ) );
		$this->assertTrue( registered_meta_key_exists( 'comment', 'mf2_syndication' ) );

		$keys = get_registered_meta_keys( 'post' );
		$this->assertSame( 'array', $keys['mf2_syndication']['type'] );
		$this->assertTrue( $keys['mf2_syndication']['single'] );
		$this->assertNotEmpty( $keys['mf2_syndication']['show_in_rest'] );
	}

	/**
	 * The registrable domain is extracted from a URL's host.
	 *
	 * @dataProvider data_extract_domain_name
	 *
	 * @param string $url    URL.
	 * @param string $domain Expected domain.
	 */
	public function test_extract_domain_name( $url, $domain ) {
		$this->assertSame( $domain, Syn_Meta::extract_domain_name( $url ) );
	}

	public function data_extract_domain_name() {
		return array(
			'bare domain'     => array( 'https://example.com/post/1', 'example.com' ),
			'www'             => array( 'https://www.example.com/', 'example.com' ),
			'deep subdomain'  => array( 'https://a.b.example.com/x', 'example.com' ),
			'two-part suffix' => array( 'https://www.example.co.uk/x', 'example.co.uk' ),
		);
	}

	public function test_clean_urls_drops_invalid_and_duplicate_urls() {
		$clean = Syn_Meta::clean_urls(
			array(
				'https://a.example/1',
				'not a url',
				'',
				' https://b.example/2 ',
				'https://a.example/1',
			)
		);

		$this->assertSame( array( 'https://a.example/1', 'https://b.example/2' ), $clean );
	}

	public function test_clean_urls_rewrites_known_sites_to_https() {
		$clean = Syn_Meta::clean_urls(
			array(
				'http://www.facebook.com/someone/posts/1',
				'http://github.com/someone',
				'http://example.com/stays-http',
			)
		);

		$this->assertSame(
			array(
				'https://www.facebook.com/someone/posts/1',
				'https://github.com/someone',
				'http://example.com/stays-http',
			),
			$clean
		);
	}

	public function test_clean_urls_https_list_is_filterable() {
		$add = function ( $domains ) {
			$domains[] = 'example.com';
			return $domains;
		};
		add_filter( 'syn_rewrite_secure', $add );

		$clean = Syn_Meta::clean_urls( array( 'http://example.com/now-https' ) );

		$this->assertSame( array( 'https://example.com/now-https' ), $clean );
	}

	public function test_clean_urls_passes_non_arrays_through() {
		$this->assertSame( 'https://a.example', Syn_Meta::clean_urls( 'https://a.example' ) );
	}

	public function test_links_are_stored_in_mf2_syndication() {
		$post_id = self::factory()->post->create();

		$this->assertNotFalse( add_post_syndication_link( $post_id, 'https://a.example/1' ) );

		$this->assertSame( array( 'https://a.example/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	public function test_adding_skips_invalid_and_duplicate_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://a.example/1', 'not a url', 'https://b.example/2' ) );
		add_post_syndication_link( $post_id, 'https://a.example/1' );

		$this->assertSame(
			array( 'https://a.example/1', 'https://b.example/2' ),
			get_post_syndication_links_data( $post_id )
		);
	}

	public function test_adding_only_invalid_links_stores_nothing() {
		$post_id = self::factory()->post->create();

		$this->assertFalse( add_post_syndication_link( $post_id, 'not a url' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'mf2_syndication' ) );
		$this->assertSame( array(), get_post_syndication_links_data( $post_id ) );
	}

	/**
	 * Replacing stores what it's given, but reading still cleans it.
	 */
	public function test_replacing_with_a_string() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://a.example/1', 'https://b.example/2' ) );

		add_post_syndication_link( $post_id, 'https://c.example/3', true );

		$this->assertSame( array( 'https://c.example/3' ), get_post_syndication_links_data( $post_id ) );
	}

	public function test_newline_separated_string_meta_is_read_as_a_list() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'mf2_syndication', "https://a.example/1\nhttps://b.example/2" );

		$this->assertSame(
			array( 'https://a.example/1', 'https://b.example/2' ),
			get_post_syndication_links_data( $post_id )
		);
	}

	/**
	 * Links in the legacy syndication_urls meta are included when read, without writing (P5).
	 */
	public function test_legacy_syndication_urls_are_read_without_writing() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'mf2_syndication', array( 'https://a.example/1' ) );
		update_post_meta( $post_id, 'syndication_urls', "https://old.example/1\nhttps://a.example/1" );

		$this->assertSame(
			array( 'https://a.example/1', 'https://old.example/1' ),
			get_post_syndication_links_data( $post_id )
		);
		$this->assertTrue( metadata_exists( 'post', $post_id, 'syndication_urls' ) );
		$this->assertSame( array( 'https://a.example/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	public function test_legacy_migration_moves_post_and_comment_links() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, 'mf2_syndication', array( 'https://a.example/1' ) );
		update_post_meta( $post_id, 'syndication_urls', "https://old.example/1\nhttps://a.example/1" );
		$only_old   = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $only_old ) );
		update_post_meta( $only_old, 'syndication_urls', 'https://old.example/2' );
		update_comment_meta( $comment_id, 'syndication_urls', 'https://old.example/3' );
		delete_option( 'syndication_links_legacy_migrated' );
		// Syn_Meta::init() scheduled the event at bootstrap; WP-Cron removes a single event before running it.
		wp_clear_scheduled_hook( 'syn_migrate_legacy_links' );

		Syn_Meta::migrate_legacy_links();

		$this->assertSame( array( 'https://a.example/1', 'https://old.example/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
		$this->assertSame( array( 'https://old.example/2' ), get_post_meta( $only_old, 'mf2_syndication', true ) );
		$this->assertSame( array( 'https://old.example/3' ), get_comment_meta( $comment_id, 'mf2_syndication', true ) );
		foreach ( array( $post_id, $only_old ) as $id ) {
			$this->assertFalse( metadata_exists( 'post', $id, 'syndication_urls' ) );
		}
		$this->assertFalse( metadata_exists( 'comment', $comment_id, 'syndication_urls' ) );
		$this->assertSame( '1', (string) get_option( 'syndication_links_legacy_migrated' ) );
		$this->assertFalse( wp_next_scheduled( 'syn_migrate_legacy_links' ) );
	}

	public function test_legacy_migration_is_scheduled_until_done() {
		delete_option( 'syndication_links_legacy_migrated' );
		wp_clear_scheduled_hook( 'syn_migrate_legacy_links' );

		Syn_Meta::schedule_legacy_migration();
		$this->assertNotFalse( wp_next_scheduled( 'syn_migrate_legacy_links' ) );

		wp_clear_scheduled_hook( 'syn_migrate_legacy_links' );
		update_option( 'syndication_links_legacy_migrated', 1 );

		Syn_Meta::schedule_legacy_migration();
		$this->assertFalse( wp_next_scheduled( 'syn_migrate_legacy_links' ) );
	}

	public function test_post_links_filter_adds_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://a.example/1' );

		$seen = null;
		$add  = function ( $urls, $id ) use ( &$seen ) {
			$seen   = $id;
			$urls[] = 'https://filtered.example/1';
			$urls[] = 'https://a.example/1';
			$urls[] = 'not a url';
			return $urls;
		};
		add_filter( 'get_post_syndication_links', $add, 10, 2 );

		$this->assertSame(
			array( 'https://a.example/1', 'https://filtered.example/1' ),
			get_post_syndication_links_data( $post_id )
		);
		$this->assertSame( $post_id, $seen );
	}

	public function test_legacy_syn_add_links_filter_runs_for_posts() {
		$post_id = self::factory()->post->create();
		$add     = function ( $urls ) {
			$urls[] = 'https://legacy.example/1';
			return $urls;
		};
		add_filter( 'syn_add_links', $add );

		$this->assertSame( array( 'https://legacy.example/1' ), get_post_syndication_links_data( $post_id ) );
	}

	public function test_comment_links() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		add_comment_syndication_link( $comment_id, array( 'https://a.example/1', 'https://b.example/2' ) );
		add_comment_syndication_link( $comment_id, 'https://c.example/3' );

		$this->assertSame(
			array( 'https://a.example/1', 'https://b.example/2', 'https://c.example/3' ),
			get_comment_syndication_links_data( $comment_id )
		);
		$this->assertSame( array(), get_post_syndication_links_data( $post_id ) );
	}

	public function test_comment_links_filter() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		$add        = function ( $urls ) {
			$urls[] = 'https://filtered.example/1';
			return $urls;
		};
		add_filter( 'get_comment_syndication_links', $add );
		add_filter( 'syn_add_links', '__return_empty_array' );

		// syn_add_links only runs for posts, so it doesn't remove the comment's links.
		$this->assertSame( array( 'https://filtered.example/1' ), get_comment_syndication_links_data( $comment_id ) );
	}

	public function test_missing_objects_return_false() {
		$this->assertFalse( add_post_syndication_link( PHP_INT_MAX, 'https://a.example/1' ) );
		$this->assertFalse( get_post_syndication_links_data( PHP_INT_MAX ) );
		$this->assertFalse( add_comment_syndication_link( PHP_INT_MAX, 'https://a.example/1' ) );
		$this->assertFalse( get_comment_syndication_links_data( PHP_INT_MAX ) );
	}
	/**
	 * Adding a link doesn't save the links other plugins add at read time (#212).
	 */
	public function test_adding_a_link_does_not_store_filtered_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://a.example/1' );
		$filter = function ( $urls ) {
			$urls[] = 'https://other.example/1';
			return $urls;
		};
		add_filter( 'get_post_syndication_links', $filter );

		add_post_syndication_link( $post_id, 'https://b.example/2' );
		remove_filter( 'get_post_syndication_links', $filter );

		$this->assertSame( array( 'https://a.example/1', 'https://b.example/2' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}
}
