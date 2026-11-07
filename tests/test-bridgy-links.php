<?php
/**
 * Tests for how Bridgy targets in a post's links are displayed.
 *
 * @package Syndication_Links
 */

class Test_Bridgy_Links extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'syndication-links_display', 'iconstext' );
		update_option( 'syndication-links_archives', '1' );
	}

	public function tear_down() {
		delete_option( 'syndication-links_display' );
		delete_option( 'syndication-links_archives' );
		parent::tear_down();
	}

	/**
	 * Bridgy targets are recognised by host.
	 *
	 * @dataProvider data_bridgy_targets
	 *
	 * @param string $url    URL.
	 * @param bool   $target Whether it's a Bridgy target.
	 */
	public function test_is_bridgy_target( $url, $target ) {
		$this->assertSame( $target, Syn_Meta::is_bridgy_target( $url ) );
	}

	public function data_bridgy_targets() {
		return array(
			'Bridgy Publish'    => array( 'https://brid.gy/publish/mastodon', true ),
			'Bridgy Fed'        => array( 'https://fed.brid.gy/', true ),
			'upper case'        => array( 'https://Brid.GY/publish/github', true ),
			'lookalike domain'  => array( 'https://notbrid.gy/publish/mastodon', false ),
			'Bridgy in a path'  => array( 'https://example.com/brid.gy', false ),
			'a syndicated copy' => array( 'https://mastodon.social/@someone/1', false ),
		);
	}

	public function test_targets_are_not_shown_as_copies() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://github.com/someone/repo', 'https://brid.gy/publish/mastodon' ) );

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'https://github.com/someone/repo', $links[0] );
	}

	/**
	 * The target is an empty link after the list, as Bridgy documents.
	 */
	public function test_targets_are_invisible_links_after_the_list() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://github.com/someone/repo', 'https://brid.gy/publish/mastodon' ) );

		$html = get_post_syndication_links( $post_id );

		$this->assertStringEndsWith( '</ul><a href="https://brid.gy/publish/mastodon"></a>', $html );
		$this->assertSame( 1, substr_count( $html, 'brid.gy' ) );
	}

	/**
	 * Bridgy has to find the target whatever the display mode.
	 */
	public function test_targets_are_links_in_hidden_mode() {
		update_option( 'syndication-links_display', 'hidden' );
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://github.com/someone/repo', 'https://fed.brid.gy/' ) );

		$html = get_post_syndication_links( $post_id );

		$this->assertStringContainsString( '<data class="u-syndication" value="https://github.com/someone/repo"></data>', $html );
		$this->assertStringEndsWith( '<a href="https://fed.brid.gy/"></a>', $html );
	}

	public function test_only_a_target() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://brid.gy/publish/github' );

		$this->assertSame( array(), Syn_Meta::get_syndication_links_elements( 'post', $post_id ) );
		$this->assertSame( '<a href="https://brid.gy/publish/github"></a>', get_post_syndication_links( $post_id ) );
	}

	public function test_target_is_in_the_content() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://brid.gy/publish/mastodon' );
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$this->assertStringContainsString( '<a href="https://brid.gy/publish/mastodon"></a>', apply_filters( 'the_content', 'Hello' ) );
	}
}
