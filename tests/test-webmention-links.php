<?php
/**
 * Tests that an unchosen webmention provider's target isn't sent by the Webmention plugin (#203).
 *
 * @package Syndication_Links
 */

class Test_Webmention_Links extends WP_UnitTestCase {

	/**
	 * Targets the Webmention plugin found in a post's content.
	 *
	 * @var string[]
	 */
	private $urls = array( 'https://fed.brid.gy/', 'https://example.com/a-post' );

	public function set_up() {
		parent::set_up();
		update_option( 'syndication_provider_enable', array( 'bridgy-fed' ) );
	}

	public function tear_down() {
		delete_option( 'syndication_provider_enable' );
		parent::tear_down();
	}

	public function test_the_filter_is_hooked() {
		$this->assertSame( 10, has_filter( 'webmention_links', array( 'Post_Syndication', 'webmention_links' ) ) );
	}

	public function test_an_unchosen_enabled_target_is_dropped() {
		$post_id = self::factory()->post->create();

		$this->assertSame( array( 'https://example.com/a-post' ), Post_Syndication::webmention_links( $this->urls, $post_id ) );
	}

	public function test_a_queued_target_is_kept() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_syndicate-to', array( 'bridgy-fed' ) );

		$this->assertSame( $this->urls, Post_Syndication::webmention_links( $this->urls, $post_id ) );
	}

	/**
	 * A post sent to the provider before keeps getting its updates.
	 */
	public function test_a_previously_sent_target_is_kept() {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'syndication_log',
			array(
				array(
					'uid'  => 'bridgy-fed',
					'date' => time(),
					'data' => array(),
				),
			)
		);

		$this->assertSame( $this->urls, Post_Syndication::webmention_links( $this->urls, $post_id ) );
	}

	/**
	 * Sites that link to Bridgy Fed by hand, without the provider, are left alone.
	 */
	public function test_a_target_is_kept_when_the_provider_is_not_enabled() {
		update_option( 'syndication_provider_enable', array() );
		$post_id = self::factory()->post->create();

		$this->assertSame( $this->urls, Post_Syndication::webmention_links( $this->urls, $post_id ) );
	}
}
