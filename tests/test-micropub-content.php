<?php
/**
 * Tests the content Micropub providers send, with the backlink and excerpt settings.
 *
 * @package Syndication_Links
 */

class Test_Micropub_Content extends WP_UnitTestCase {

	/**
	 * A Micropub provider with a short content limit.
	 *
	 * @return SynProvider_Micropub
	 */
	private function provider() {
		return new class( array( 'token' => 'x' ) ) extends SynProvider_Micropub {
			/**
			 * Maximum content length.
			 *
			 * @var int
			 */
			protected $content_length = 100;
		};
	}

	/**
	 * Creates a post.
	 *
	 * @param string $content Content.
	 * @param string $excerpt Optional. Excerpt.
	 * @return WP_Post
	 */
	private function post( $content, $excerpt = '' ) {
		return get_post(
			self::factory()->post->create(
				array(
					'post_title'   => 'The title',
					'post_content' => $content,
					'post_excerpt' => $excerpt,
				)
			)
		);
	}

	public function tear_down() {
		delete_option( 'syndication_backlink' );
		delete_option( 'syndication_use_excerpt' );
		parent::tear_down();
	}

	/**
	 * The excerpt setting is stored as the string '1'.
	 */
	public function test_the_excerpt_is_used_when_the_setting_is_on() {
		update_option( 'syndication_use_excerpt', '1' );
		$post = $this->post( 'The content.', 'The excerpt.' );

		$this->assertSame( 'The excerpt.', $this->provider()->get_content( $post ) );
	}

	public function test_the_content_is_used_when_the_excerpt_setting_is_off() {
		update_option( 'syndication_use_excerpt', '0' );
		update_option( 'syndication_backlink', 'true' );
		$post = $this->post( 'The content.', 'The excerpt.' );

		$this->assertStringContainsString( 'The content.', $this->provider()->get_content( $post ) );
	}

	public function test_enable_adds_the_link() {
		update_option( 'syndication_backlink', '' );
		$post = $this->post( 'Short.' );

		$content = $this->provider()->get_content( $post );

		$this->assertStringContainsString( 'Short.', $content );
		$this->assertStringContainsString( '<a href="' . esc_url( wp_get_shortlink( $post ) ) . '">', $content );
	}

	public function test_disable_adds_no_link_and_uses_the_whole_limit() {
		update_option( 'syndication_backlink', 'true' );
		$words = str_repeat( 'word ', 40 );
		$post  = $this->post( $words );

		$content = $this->provider()->get_content( $post );

		$this->assertStringNotContainsString( '<a ', $content );
		// Cut at the limit, not at the limit minus room for a link.
		$this->assertGreaterThan( 90, strlen( $content ) );
		$this->assertLessThanOrEqual( 100, strlen( $content ) );
	}

	public function test_if_too_long_adds_no_link_to_short_content() {
		update_option( 'syndication_backlink', 'maybe' );
		$post = $this->post( 'Short.' );

		$this->assertStringNotContainsString( '<a ', $this->provider()->get_content( $post ) );
	}

	public function test_if_too_long_sends_the_title_and_link_for_long_content() {
		update_option( 'syndication_backlink', 'maybe' );
		$post = $this->post( str_repeat( 'word ', 40 ) );

		$this->assertSame(
			'The title - <a href="' . esc_url( wp_get_shortlink( $post ) ) . '">' . esc_html( wp_get_shortlink( $post ) ) . '</a>',
			$this->provider()->get_content( $post )
		);
	}
}
