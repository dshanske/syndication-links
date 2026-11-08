<?php
/**
 * Tests that a theme can place the links itself (#130).
 *
 * @package Syndication_Links
 */

class Test_Theme_Support extends WP_UnitTestCase {

	/**
	 * A post with a link, and a comment with one.
	 *
	 * @var int
	 */
	private static $post_id;
	private static $comment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
		add_post_syndication_link( self::$post_id, 'https://mastodon.example/@someone/1' );
		self::$comment_id = $factory->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		add_comment_syndication_link( self::$comment_id, 'https://mastodon.example/@someone/2' );
	}

	public function tear_down() {
		remove_theme_support( 'syndication-links' );
		parent::tear_down();
	}

	/**
	 * Runs the plugin's content filter for the post.
	 *
	 * @return string
	 */
	private function content() {
		$GLOBALS['post'] = get_post( self::$post_id );
		setup_postdata( $GLOBALS['post'] );
		$content = Syn_Config::the_content( 'Content.' );
		wp_reset_postdata();
		return $content;
	}

	/**
	 * Runs the plugin's comment filter for the comment.
	 *
	 * @return string
	 */
	private function comment() {
		return Syn_Config::comment_text( 'Comment.', get_comment( self::$comment_id ) );
	}

	public function test_links_are_added_by_default() {
		$this->assertStringContainsString( 'mastodon.example/@someone/1', $this->content() );
		$this->assertStringContainsString( 'mastodon.example/@someone/2', $this->comment() );
	}

	public function test_theme_support_without_a_list_handles_both() {
		add_theme_support( 'syndication-links' );

		$this->assertSame( 'Content.', $this->content() );
		$this->assertSame( 'Comment.', $this->comment() );
	}

	public function test_theme_support_for_content_only() {
		add_theme_support( 'syndication-links', array( 'content' ) );

		$this->assertSame( 'Content.', $this->content() );
		$this->assertStringContainsString( 'mastodon.example/@someone/2', $this->comment() );
	}

	public function test_theme_support_for_comments_only() {
		add_theme_support( 'syndication-links', array( 'comments' ) );

		$this->assertStringContainsString( 'mastodon.example/@someone/1', $this->content() );
		$this->assertSame( 'Comment.', $this->comment() );
	}

	/**
	 * The filter used to be read once on init, so adding it later did nothing.
	 */
	public function test_the_content_filter_works_after_init() {
		$this->assertTrue( did_action( 'init' ) > 0 );
		add_filter( 'syndication_links_display', '__return_false' );

		$this->assertSame( 'Content.', $this->content() );
		$this->assertStringContainsString( 'mastodon.example/@someone/2', $this->comment() );
	}

	public function test_the_comment_filter() {
		add_filter( 'syndication_links_comment_display', '__return_false' );

		$this->assertSame( 'Comment.', $this->comment() );
		$this->assertStringContainsString( 'mastodon.example/@someone/1', $this->content() );
	}

	/**
	 * Themes that remove the content filter, as before, still can.
	 */
	public function test_the_content_filter_is_still_hooked_at_30() {
		$this->assertSame( 30, has_filter( 'the_content', array( 'Syn_Config', 'the_content' ) ) );
	}

	public function test_the_settings_page_says_the_theme_places_the_links() {
		add_theme_support( 'syndication-links' );

		ob_start();
		Syn_Config::options_callback();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Your theme places the syndication links itself.', $html );
	}
}
