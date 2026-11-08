<?php
/**
 * Tests the template tags themes use to place the links themselves.
 *
 * @package Syndication_Links
 */

class Test_Template_Tags extends WP_UnitTestCase {

	/**
	 * A post with a link.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
		add_post_syndication_link( self::$post_id, 'https://mastodon.example/@someone/1' );
	}

	public function tear_down() {
		delete_option( 'syndication-links_display' );
		parent::tear_down();
	}

	public function test_the_syndication_links_echoes_the_post_links() {
		update_option( 'syndication-links_display', 'text' );
		$this->go_to( get_permalink( self::$post_id ) );

		ob_start();
		the_syndication_links( self::$post_id );
		$html = ob_get_clean();

		$this->assertSame( get_post_syndication_links( self::$post_id ), $html );
		$this->assertStringContainsString( 'href="https://mastodon.example/@someone/1"', $html );
	}

	public function test_the_comment_syndication_links_echoes_the_comment_links() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		add_comment_syndication_link( $comment_id, 'https://mastodon.example/@someone/2' );

		ob_start();
		the_comment_syndication_links( $comment_id );
		$html = ob_get_clean();

		$this->assertSame( get_comment_syndication_links( $comment_id ), $html );
		$this->assertStringContainsString( 'mastodon.example/@someone/2', $html );
	}

	public function test_nothing_is_echoed_without_links() {
		$post_id = self::factory()->post->create();

		ob_start();
		the_syndication_links( $post_id );

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * A theme's arguments can't make links the site hides visible.
	 */
	public function test_hidden_links_stay_hidden() {
		update_option( 'syndication-links_display', 'hidden' );
		$this->go_to( get_permalink( self::$post_id ) );

		ob_start();
		the_syndication_links(
			self::$post_id,
			array(
				'icons' => true,
				'text'  => true,
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<data class="u-syndication" value="https://mastodon.example/@someone/1"></data>', $html );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	public function test_display_mode() {
		update_option( 'syndication-links_display', 'iconstext' );
		$this->go_to( get_permalink( self::$post_id ) );

		$this->assertSame( 'iconstext', syndication_links_display_mode() );
	}
}
