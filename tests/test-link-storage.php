<?php
/**
 * Tests for storing and reading syndication links: Syn_Meta and the template API.
 *
 * Stored arrays can have gaps in their keys (REVIEW.md B12), so these tests
 * compare values with array_values() until that's fixed.
 *
 * @package Syndication_Links
 */

class Test_Link_Storage extends WP_UnitTestCase {

	public function test_meta_is_registered_for_posts_and_comments() {
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

		$this->assertSame( array( 'https://a.example/1', 'https://b.example/2' ), array_values( $clean ) );
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
			array_values( $clean )
		);
	}

	public function test_clean_urls_https_list_is_filterable() {
		$add = function ( $domains ) {
			$domains[] = 'example.com';
			return $domains;
		};
		add_filter( 'syn_rewrite_secure', $add );

		$clean = Syn_Meta::clean_urls( array( 'http://example.com/now-https' ) );

		$this->assertSame( array( 'https://example.com/now-https' ), array_values( $clean ) );
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
			array_values( get_post_syndication_links_data( $post_id ) )
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

		$this->assertSame( array( 'https://c.example/3' ), array_values( get_post_syndication_links_data( $post_id ) ) );
	}

	public function test_newline_separated_string_meta_is_read_as_a_list() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'mf2_syndication', "https://a.example/1\nhttps://b.example/2" );

		$this->assertSame(
			array( 'https://a.example/1', 'https://b.example/2' ),
			array_values( get_post_syndication_links_data( $post_id ) )
		);
	}

	/**
	 * Links in the legacy syndication_urls meta are merged into mf2_syndication when read.
	 *
	 * This documents the current behaviour. REVIEW.md P5 moves the migration out of the getter.
	 */
	public function test_legacy_syndication_urls_are_migrated() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'mf2_syndication', array( 'https://a.example/1' ) );
		update_post_meta( $post_id, 'syndication_urls', "https://old.example/1\nhttps://a.example/1" );

		$this->assertSame(
			array( 'https://a.example/1', 'https://old.example/1' ),
			array_values( get_post_syndication_links_data( $post_id ) )
		);
		$this->assertFalse( metadata_exists( 'post', $post_id, 'syndication_urls' ) );
		$this->assertSame(
			array( 'https://a.example/1', 'https://old.example/1' ),
			array_values( get_post_meta( $post_id, 'mf2_syndication', true ) )
		);
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
			array_values( get_post_syndication_links_data( $post_id ) )
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

		$this->assertSame( array( 'https://legacy.example/1' ), array_values( get_post_syndication_links_data( $post_id ) ) );
	}

	public function test_comment_links() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		add_comment_syndication_link( $comment_id, array( 'https://a.example/1', 'https://b.example/2' ) );
		add_comment_syndication_link( $comment_id, 'https://c.example/3' );

		$this->assertSame(
			array( 'https://a.example/1', 'https://b.example/2', 'https://c.example/3' ),
			array_values( get_comment_syndication_links_data( $comment_id ) )
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
		$this->assertSame( array( 'https://filtered.example/1' ), array_values( get_comment_syndication_links_data( $comment_id ) ) );
	}

	public function test_missing_objects_return_false() {
		$this->assertFalse( add_post_syndication_link( PHP_INT_MAX, 'https://a.example/1' ) );
		$this->assertFalse( get_post_syndication_links_data( PHP_INT_MAX ) );
		$this->assertFalse( add_comment_syndication_link( PHP_INT_MAX, 'https://a.example/1' ) );
		$this->assertFalse( get_comment_syndication_links_data( PHP_INT_MAX ) );
	}
}
