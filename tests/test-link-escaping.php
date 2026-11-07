<?php
/**
 * Tests that filtered and stored values are escaped in the link markup (#253).
 *
 * @package Syndication_Links
 */

class Test_Link_Escaping extends WP_UnitTestCase {

	const ATTACK = '"><script>alert(1)</script>';

	public function set_up() {
		parent::set_up();
		update_option( 'syndication-links_display', 'text' );
		update_option( 'syndication-links_archives', '1' );
	}

	public function tear_down() {
		delete_option( 'syndication-links_display' );
		delete_option( 'syndication-links_archives' );
		delete_option( 'syndication-links_text_before' );
		parent::tear_down();
	}

	/**
	 * A post with one GitHub link.
	 *
	 * @return int Post ID.
	 */
	private function post() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://github.com/someone/repo' );
		return $post_id;
	}

	public function test_filtered_icon_name_is_escaped_in_the_label() {
		$post_id = $this->post();
		add_filter(
			'syn_link_mapping',
			function () {
				return 'x' . self::ATTACK;
			}
		);

		$html = get_post_syndication_links( $post_id );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'aria-label="x&quot;&gt;&lt;script&gt;', $html );
	}

	public function test_filtered_title_is_escaped() {
		$post_id = $this->post();
		add_filter(
			'syn_link_title',
			function () {
				return 'Git & <b>Hub</b>';
			}
		);

		$html = get_post_syndication_links( $post_id );

		$this->assertStringContainsString( 'Git &amp; &lt;b&gt;Hub&lt;/b&gt;</a>', $html );
	}

	public function test_titles_with_ampersands_are_encoded_once() {
		$post_id = $this->post();
		add_filter(
			'syn_link_title',
			function () {
				return 'AT&amp;T';
			}
		);

		$this->assertStringContainsString( 'AT&amp;T</a>', get_post_syndication_links( $post_id ) );
	}

	public function test_css_class_arguments_are_escaped() {
		$post_id = $this->post();

		$html = get_post_syndication_links(
			$post_id,
			array(
				'single-css'    => 'a' . self::ATTACK,
				'container-css' => 'b' . self::ATTACK,
				'text-css'      => 'c' . self::ATTACK,
			)
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'class="u-syndication a&quot;&gt;', $html );
		$this->assertStringContainsString( '<ul class="b&quot;&gt;', $html );
		$this->assertStringContainsString( '<span class="c&quot;&gt;', $html );
	}

	public function test_display_defaults_filter_is_escaped() {
		$post_id = $this->post();
		add_filter(
			'syn_links_display_defaults',
			function ( $defaults ) {
				$defaults['container-css'] = 'b' . Test_Link_Escaping::ATTACK;
				return $defaults;
			}
		);

		$this->assertStringNotContainsString( '<script>', get_post_syndication_links( $post_id ) );
	}

	/**
	 * The text before the links keeps simple formatting but not scripts.
	 */
	public function test_text_before_is_filtered_through_kses() {
		update_option( 'syndication-links_text_before', '<em>Also</em> on:<script>alert(1)</script>' );
		$post_id = $this->post();

		$html = get_post_syndication_links( $post_id );

		$this->assertStringStartsWith( '<span class="syn-text"><em>Also</em> on:alert(1)</span>', $html );
	}
}
