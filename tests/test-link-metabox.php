<?php
/**
 * Tests for the Syndication Links metabox markup (#254).
 *
 * @package Syndication_Links
 */

class Test_Link_Metabox extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'post' );
	}

	public function tear_down() {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Renders the metabox for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Markup.
	 */
	private function render( $post_id ) {
		ob_start();
		Syn_Meta::metabox( get_post( $post_id ), array() );
		return ob_get_clean();
	}

	public function test_lists_each_saved_link() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://a.example/1', 'https://b.example/2' ) );

		$html = $this->render( $post_id );

		$this->assertSame( 2, substr_count( $html, 'name="syndication_urls[]"' ) );
		$this->assertStringContainsString( 'value="https://a.example/1"', $html );
		$this->assertStringContainsString( 'value="https://b.example/2"', $html );
		$this->assertStringContainsString( 'name="syn_metabox_nonce"', $html );
	}

	public function test_only_the_first_input_has_the_id() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://a.example/1', 'https://b.example/2', 'https://c.example/3' ) );

		$html = $this->render( $post_id );

		$this->assertSame( 1, substr_count( $html, 'id="syndication_urls"' ) );
		$this->assertStringContainsString( '<label for="syndication_urls">', $html );
	}

	public function test_empty_metabox_has_one_empty_input() {
		$post_id = self::factory()->post->create();

		$html = $this->render( $post_id );

		$this->assertSame( 1, substr_count( $html, 'name="syndication_urls[]"' ) );
		$this->assertStringContainsString( 'id="syndication_urls" value=""', $html );
	}

	/**
	 * Values are attribute-escaped, so a stored value can't break out of the input.
	 */
	public function test_values_are_attribute_escaped() {
		$post_id = self::factory()->post->create();
		// Stored directly, as another plugin or an import might.
		update_post_meta( $post_id, 'mf2_syndication', array( 'https://a.example/?a=1&b=2' ) );
		$filter = function ( $urls ) {
			$urls[] = 'https://a.example/"><script>alert(1)</script>';
			return $urls;
		};
		add_filter( 'get_post_syndication_links', $filter );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'value="https://a.example/?a=1&amp;b=2"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}
	/**
	 * Links other plugins add are listed, but not as fields, so saving doesn't store them (#212).
	 */
	public function test_other_plugins_links_are_listed_read_only() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://a.example/1' );
		$filter = function ( $urls ) {
			$urls[] = 'https://other.example/1';
			return $urls;
		};
		add_filter( 'get_post_syndication_links', $filter );

		$html = $this->render( $post_id );
		remove_filter( 'get_post_syndication_links', $filter );

		$this->assertSame( 1, substr_count( $html, 'name="syndication_urls[]"' ) );
		$this->assertStringNotContainsString( 'value="https://other.example/1"', $html );
		$this->assertStringContainsString( '<a href="https://other.example/1">', $html );
	}

	public function test_the_loaded_links_are_sent_with_the_form() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://a.example/1', 'https://b.example/2' ) );

		$html = $this->render( $post_id );

		$this->assertMatchesRegularExpression( '/name="syndication_urls_loaded" value="([^"]*)"/', $html );
		preg_match( '/name="syndication_urls_loaded" value="([^"]*)"/', $html, $match );
		$this->assertSame( array( 'https://a.example/1', 'https://b.example/2' ), json_decode( html_entity_decode( $match[1] ), true ) );
	}
}
