<?php
/**
 * Tests the options Bridgy webmention providers add to the webmention, and the Mastodon excerpt.
 *
 * @package Syndication_Links
 */

class Test_Bridgy_Webmention_Options extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'syndication_backlink' );
		delete_option( 'bridgy_ignoreformatting' );
		delete_option( 'syndication_use_excerpt' );
		delete_option( 'syndication_provider_enable' );
		parent::tear_down();
	}

	/**
	 * The parameters of a webmention to a target.
	 *
	 * @param string $target Target URL.
	 * @return array
	 */
	private function body( $target = 'https://brid.gy/publish/github' ) {
		return array(
			'source' => 'https://example.com/post',
			'target' => $target,
		);
	}

	/**
	 * Bridgy's omit-link option takes the backlink setting's value.
	 *
	 * @dataProvider data_backlink
	 *
	 * @param string $backlink The setting.
	 */
	public function test_the_backlink_setting_is_sent_as_omit_link( $backlink ) {
		update_option( 'syndication_backlink', $backlink );

		$body = ( new SynProvider_Webmention_Bridgy_Github() )->webmention_send_vars( $this->body(), 1 );

		$this->assertSame( $backlink, $body['bridgy_omit_link'] );
	}

	public function data_backlink() {
		return array(
			'disable'     => array( 'true' ),
			'if too long' => array( 'maybe' ),
		);
	}

	/**
	 * The "add a link" setting is empty, which is Bridgy's default, so nothing is sent.
	 */
	public function test_adding_a_link_sends_no_omit_link() {
		update_option( 'syndication_backlink', '' );

		$body = ( new SynProvider_Webmention_Bridgy_Github() )->webmention_send_vars( $this->body(), 1 );

		$this->assertArrayNotHasKey( 'bridgy_omit_link', $body );
	}

	public function test_ignore_formatting_is_sent_when_on() {
		update_option( 'bridgy_ignoreformatting', '1' );

		$body = ( new SynProvider_Webmention_Bridgy_Github() )->webmention_send_vars( $this->body(), 1 );

		$this->assertSame( 'true', $body['bridgy-ignore-formatting'] );
	}

	public function test_ignore_formatting_is_not_sent_when_off() {
		update_option( 'bridgy_ignoreformatting', '0' );

		$body = ( new SynProvider_Webmention_Bridgy_Github() )->webmention_send_vars( $this->body(), 1 );

		$this->assertArrayNotHasKey( 'bridgy-ignore-formatting', $body );
	}

	public function test_an_encoded_target_matches() {
		update_option( 'syndication_backlink', 'true' );

		$body = ( new SynProvider_Webmention_Bridgy_Github() )->webmention_send_vars( $this->body( rawurlencode( 'https://brid.gy/publish/github' ) ), 1 );

		$this->assertSame( 'true', $body['bridgy_omit_link'] );
	}

	/**
	 * Each provider only changes webmentions to its own target.
	 */
	public function test_other_targets_are_left_alone() {
		update_option( 'syndication_backlink', 'true' );
		update_option( 'bridgy_ignoreformatting', '1' );
		$body = $this->body( 'https://brid.gy/publish/flickr' );

		$this->assertSame( $body, ( new SynProvider_Webmention_Bridgy_Github() )->webmention_send_vars( $body, 1 ) );
		$this->assertSame( $body, ( new SynProvider_Webmention_Bridgy_Mastodon() )->webmention_send_vars( $body, 1 ) );
	}

	public function test_the_filter_is_hooked_only_when_the_provider_is_enabled() {
		update_option( 'syndication_provider_enable', array( 'webmention-mastodon-bridgy' ) );
		$disabled = new SynProvider_Webmention_Bridgy_Github();
		$enabled  = new SynProvider_Webmention_Bridgy_Mastodon();

		$this->assertFalse( has_filter( 'webmention_send_vars', array( $disabled, 'webmention_send_vars' ) ) );
		$this->assertSame( 10, has_filter( 'webmention_send_vars', array( $enabled, 'webmention_send_vars' ) ) );
		$this->assertSame( 10, has_action( 'wp_footer', array( $enabled, 'wp_footer' ) ) );

		remove_filter( 'webmention_send_vars', array( $enabled, 'webmention_send_vars' ), 10 );
		remove_action( 'wp_footer', array( $enabled, 'wp_footer' ) );
	}

	/**
	 * The footer output for a post.
	 *
	 * @param string $excerpt The post's excerpt.
	 * @return string
	 */
	private function footer( $excerpt ) {
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_excerpt' => $excerpt ) ) ) );
		the_post();
		ob_start();
		( new SynProvider_Webmention_Bridgy_Mastodon() )->wp_footer();
		return ob_get_clean();
	}

	public function test_the_footer_adds_the_excerpt_as_mastodon_content() {
		update_option( 'syndication_use_excerpt', '1' );

		$this->assertSame( '<p class="p-bridgy-mastodon-content" style="display:none">The excerpt.</p>', $this->footer( 'The excerpt.' ) );
	}

	public function test_the_footer_is_empty_when_the_excerpt_setting_is_off() {
		update_option( 'syndication_use_excerpt', '0' );

		$this->assertSame( '', $this->footer( 'The excerpt.' ) );
	}

	public function test_the_footer_is_empty_without_an_excerpt() {
		update_option( 'syndication_use_excerpt', '1' );

		$this->assertSame( '', $this->footer( '' ) );
	}

	public function test_the_bridgy_targets() {
		$this->assertSame( 'https://brid.gy/publish/mastodon', ( new SynProvider_Webmention_Bridgy_Mastodon() )->get_target() );
		$this->assertSame( 'https://brid.gy/publish/github', ( new SynProvider_Webmention_Bridgy_Github() )->get_target() );
		$this->assertSame( 'https://brid.gy/publish/flickr', ( new SynProvider_Webmention_Bridgy_Flickr() )->get_target() );
	}
}
