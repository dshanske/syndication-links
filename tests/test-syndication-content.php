<?php
/**
 * Tests the content prepared for syndication, and the other helpers in functions.php.
 *
 * @package Syndication_Links
 */

class Test_Syndication_Content extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Outside the loop, so the links box isn't added to the content.
		$GLOBALS['post'] = null;
	}

	public function tear_down() {
		remove_shortcode( 'syn_test' );
		delete_option( 'syndication_post_types' );
		parent::tear_down();
	}

	/**
	 * Creates a post.
	 *
	 * @param string $content Content.
	 * @return int Post ID.
	 */
	private function post( $content ) {
		return self::factory()->post->create( array( 'post_content' => $content ) );
	}

	public function test_post_content_runs_the_content_filters() {
		$this->assertSame( "<p>One.</p>\n<p>Two.</p>\n", syn_get_post_content( $this->post( "One.\n\nTwo." ) ) );
	}

	public function test_post_content_removes_shortcodes() {
		add_shortcode(
			'syn_test',
			function () {
				return 'rendered';
			}
		);

		$content = syn_get_post_content( $this->post( 'Before [syn_test] after' ) );

		$this->assertStringContainsString( 'Before', $content );
		$this->assertStringNotContainsString( 'syn_test', $content );
		$this->assertStringNotContainsString( 'rendered', $content );
	}

	public function test_post_content_keeps_only_basic_formatting() {
		$content = syn_get_post_content(
			$this->post( '<div class="x"><a href="https://example.com/" onclick="evil()" class="link">A link</a> <strong>bold</strong> <script>alert(1)</script><iframe src="https://example.com/"></iframe></div>' )
		);

		$this->assertStringContainsString( '<a href="https://example.com/">A link</a>', $content );
		$this->assertStringContainsString( '<strong>bold</strong>', $content );
		$this->assertStringNotContainsString( '<div', $content );
		$this->assertStringNotContainsString( 'onclick', $content );
		$this->assertStringNotContainsString( 'class=', $content );
		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringNotContainsString( '<iframe', $content );
	}

	public function test_post_content_keeps_images() {
		$content = syn_get_post_content( $this->post( '<img src="https://example.com/a.jpg" alt="An image" class="wp-image-1" />' ) );

		$this->assertStringContainsString( '<img src="https://example.com/a.jpg" alt="An image" />', $content );
	}

	/**
	 * Blocks are rendered as for an excerpt, when the block functions exist.
	 */
	public function test_post_content_renders_blocks() {
		if ( ! function_exists( 'excerpt_remove_blocks' ) ) {
			$this->markTestSkipped( 'No block support.' );
		}

		$content = syn_get_post_content( $this->post( "<!-- wp:paragraph -->\n<p>A paragraph.</p>\n<!-- /wp:paragraph -->" ) );

		$this->assertStringContainsString( '<p>A paragraph.</p>', $content );
		$this->assertStringNotContainsString( 'wp:paragraph', $content );
	}

	/**
	 * The content filters it unhooks while it runs are put back.
	 */
	public function test_post_content_restores_the_content_filters() {
		$tags   = has_filter( 'the_content', 'wp_filter_content_tags' );
		$blocks = has_filter( 'the_content', 'do_blocks' );

		syn_get_post_content( $this->post( 'Content.' ) );

		$this->assertSame( $tags, has_filter( 'the_content', 'wp_filter_content_tags' ) );
		$this->assertSame( $blocks, has_filter( 'the_content', 'do_blocks' ) );
	}

	public function test_post_content_of_a_missing_post_is_empty() {
		$this->assertSame( '', syn_get_post_content( PHP_INT_MAX ) );
	}

	public function test_excerpt_returns_short_text_without_tags() {
		$this->assertSame( 'Short and bold.', syn_excerpt( '<p>Short and <b>bold</b>.</p>', 100 ) );
	}

	public function test_excerpt_cuts_long_text_at_a_word() {
		$excerpt = syn_excerpt( 'one two three four five six', 12 );

		$this->assertSame( 'one two', $excerpt );
	}

	public function test_excerpt_defaults_to_200_characters() {
		$excerpt = syn_excerpt( str_repeat( 'word ', 100 ) );

		$this->assertLessThanOrEqual( 200, strlen( $excerpt ) );
		$this->assertGreaterThan( 190, strlen( $excerpt ) );
	}

	public function test_content_for_syndication_is_filtered_for_the_provider() {
		$this->go_to( get_permalink( $this->post( 'The content.' ) ) );
		the_post();
		$seen   = array();
		$filter = function ( $content, $uid ) use ( &$seen ) {
			$seen = array( $content, $uid, is_syndication() );
			return 'Changed';
		};
		add_filter( 'the_content_syndication', $filter, 10, 2 );

		$content = get_the_content_syndication( 'one' );

		remove_filter( 'the_content_syndication', $filter, 10 );
		$this->assertSame( 'Changed', $content );
		$this->assertStringStartsWith( "<p>The content.</p>\n", $seen[0] );
		$this->assertSame( array( 'one', true ), array_slice( $seen, 1 ) );
		$this->assertFalse( is_syndication() );
	}

	public function test_original_of_form_is_returned_or_echoed() {
		$form = get_original_of_form( false );

		$this->assertStringContainsString( 'action="' . esc_url( home_url( '/' ) ) . '"', $form );
		$this->assertStringContainsString( 'name="original-of"', $form );

		ob_start();
		$this->assertNull( get_original_of_form() );
		$this->assertSame( $form, ob_get_clean() );
	}

	public function test_post_types_always_include_posts() {
		$this->assertSame( array( 'post' ), syndication_post_types() );

		update_option( 'syndication_post_types', array( 'page' ) );
		$this->assertSame( array( 'post', 'page' ), syndication_post_types() );

		update_option( 'syndication_post_types', 'not a list' );
		$this->assertSame( array( 'post' ), syndication_post_types() );
	}

	public function test_post_types_are_filterable() {
		$filter = function ( $types ) {
			$types[] = 'note';
			return $types;
		};
		add_filter( 'syndication_post_types', $filter );

		$this->assertSame( array( 'post', 'note' ), syndication_post_types() );
		remove_filter( 'syndication_post_types', $filter );
	}
}
