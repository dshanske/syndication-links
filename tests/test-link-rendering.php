<?php
/**
 * Tests for displaying syndication links: Syn_Meta's markup and Syn_Config's output filters.
 *
 * @package Syndication_Links
 */

class Test_Link_Rendering extends WP_UnitTestCase {

	/**
	 * The display options these tests change.
	 *
	 * @var string[]
	 */
	protected static $options = array(
		'syndication-links_display',
		'syndication-links_archives',
		'syndication-links_text_before',
		'syndication-links_bw',
		'syndication-links_size',
	);

	public function set_up() {
		parent::set_up();
		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
		update_option( 'syndication-links_display', 'iconstext' );
		update_option( 'syndication-links_archives', '1' );
	}

	public function tear_down() {
		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
		parent::tear_down();
	}

	/**
	 * Creates a published post with syndication links.
	 *
	 * @param string[] $links Links.
	 * @return int Post ID.
	 */
	protected function post_with_links( $links = array( 'https://github.com/someone/repo' ) ) {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, $links );
		return $post_id;
	}

	/**
	 * The display option decides the container and whether text and icons show.
	 *
	 * @dataProvider data_display_defaults
	 *
	 * @param string $display Display option.
	 * @param string $style   Expected container style.
	 * @param bool   $text    Whether the text is shown.
	 * @param bool   $icons   Whether the icons are shown.
	 */
	public function test_display_option_sets_the_defaults( $display, $style, $text, $icons ) {
		update_option( 'syndication-links_display', $display );

		$defaults = Syn_Meta::get_syndication_links_display_defaults();

		$this->assertSame( $style, $defaults['style'] );
		$this->assertSame( $text, $defaults['text'] );
		$this->assertSame( $icons, $defaults['icons'] );
	}

	public function data_display_defaults() {
		return array(
			'icons and text' => array( 'iconstext', 'ul', true, true ),
			'icons'          => array( 'icons', 'ul', false, true ),
			'text'           => array( 'text', 'ul', true, false ),
			'hidden'         => array( 'hidden', 'span', false, false ),
		);
	}

	public function test_display_defaults_are_filterable() {
		$filter = function ( $defaults ) {
			$defaults['container-css'] = 'my-links';
			return $defaults;
		};
		add_filter( 'syn_links_display_defaults', $filter );

		$this->assertSame( 'my-links', Syn_Meta::get_syndication_links_display_defaults()['container-css'] );
	}

	public function test_archives_are_hidden_unless_enabled() {
		update_option( 'syndication-links_archives', '0' );
		$this->assertSame( 'hidden', Syn_Meta::get_syndication_links_display_option() );

		update_option( 'syndication-links_archives', '1' );
		$this->assertSame( 'iconstext', Syn_Meta::get_syndication_links_display_option() );
	}

	public function test_single_posts_use_the_display_option() {
		update_option( 'syndication-links_archives', '0' );
		$post_id = $this->post_with_links();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame( 'iconstext', Syn_Meta::get_syndication_links_display_option() );
	}

	public function test_link_markup() {
		$post_id = $this->post_with_links();

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertCount( 1, $links );
		$this->assertStringStartsWith( '<a aria-label="github" class="u-syndication syn-link" href="https://github.com/someone/repo" rel="nofollow">', $links[0] );
		$this->assertStringContainsString( 'svg-github', $links[0] );
		$this->assertStringContainsString( '<svg', $links[0] );
		$this->assertStringEndsWith( 'GitHub</a>', $links[0] );
		$this->assertStringNotContainsString( 'rel="syndication"', $links[0] );
	}

	public function test_single_posts_add_rel_syndication() {
		$post_id = $this->post_with_links();
		$this->go_to( get_permalink( $post_id ) );

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertStringContainsString( 'href="https://github.com/someone/repo" rel="syndication nofollow">', $links[0] );
	}

	/**
	 * The syn_link_rel filter can drop nofollow (#55).
	 */
	public function test_nofollow_can_be_removed() {
		$post_id = $this->post_with_links();
		$this->go_to( get_permalink( $post_id ) );
		$filter = function ( $rels ) {
			return array_diff( $rels, array( 'nofollow' ) );
		};
		add_filter( 'syn_link_rel', $filter );

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );
		remove_filter( 'syn_link_rel', $filter );

		$this->assertStringContainsString( 'href="https://github.com/someone/repo" rel="syndication">', $links[0] );
	}

	public function test_no_rel_values_means_no_rel_attribute() {
		$post_id = $this->post_with_links();
		add_filter( 'syn_link_rel', '__return_empty_array' );

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );
		remove_filter( 'syn_link_rel', '__return_empty_array' );

		$this->assertStringStartsWith( '<a aria-label="github" class="u-syndication syn-link" href="https://github.com/someone/repo">', $links[0] );
	}

	public function test_icons_only() {
		update_option( 'syndication-links_display', 'icons' );
		$post_id = $this->post_with_links();

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertStringContainsString( 'svg-github', $links[0] );
		$this->assertStringNotContainsString( 'GitHub</a>', $links[0] );
	}

	public function test_text_only() {
		update_option( 'syndication-links_display', 'text' );
		$post_id = $this->post_with_links();

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertStringNotContainsString( '<svg', $links[0] );
		$this->assertStringEndsWith( 'GitHub</a>', $links[0] );
	}

	public function test_hidden_links_are_data_elements() {
		update_option( 'syndication-links_display', 'hidden' );
		$post_id = $this->post_with_links();

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertSame( array( '<data class="u-syndication" value="https://github.com/someone/repo"></data>' ), $links );
	}

	/**
	 * Sites without their own icon are labelled with their domain.
	 */
	public function test_unknown_sites_are_labelled_with_their_domain() {
		update_option( 'syndication-links_display', 'text' );
		$post_id = $this->post_with_links( array( 'https://www.example.com/post/1' ) );

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertStringStartsWith( '<a aria-label="example.com" ', $links[0] );
		$this->assertStringEndsWith( 'example.com</a>', $links[0] );
	}

	public function test_link_title_is_filterable() {
		update_option( 'syndication-links_display', 'text' );
		$post_id = $this->post_with_links();
		$filter  = function ( $title, $name ) {
			return 'github' === $name ? 'My Code' : $title;
		};
		add_filter( 'syn_link_title', $filter, 10, 2 );

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$this->assertStringEndsWith( 'My Code</a>', $links[0] );
	}

	public function test_link_css_class_is_an_argument() {
		$post_id = $this->post_with_links();

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id, array( 'single-css' => 'my-link' ) );

		$this->assertStringContainsString( 'class="u-syndication my-link"', $links[0] );
	}

	public function test_no_links_render_nothing() {
		$post_id = self::factory()->post->create();

		$this->assertSame( array(), Syn_Meta::get_syndication_links_elements( 'post', $post_id ) );
		$this->assertSame( '', get_post_syndication_links( $post_id ) );
	}

	/**
	 * Each style wraps and separates the links its own way.
	 *
	 * @dataProvider data_container_styles
	 *
	 * @param string $style  Style argument.
	 * @param string $before Expected opening markup.
	 * @param string $sep    Expected separator.
	 * @param string $after  Expected closing markup.
	 */
	public function test_container_styles( $style, $before, $sep, $after ) {
		update_option( 'syndication-links_display', 'text' );
		$post_id = $this->post_with_links( array( 'https://github.com/someone/repo', 'https://www.example.com/post/1' ) );
		$links   = Syn_Meta::get_syndication_links_elements( 'post', $post_id );

		$html = get_post_syndication_links(
			$post_id,
			array(
				'style'            => $style,
				'show_text_before' => false,
			)
		);

		$this->assertSame( $before . implode( $sep, $links ) . $after, $html );
	}

	public function data_container_styles() {
		return array(
			'ul (default)' => array( 'ul', '<ul class="relsyn"><li>', '</li><li>', '</li></ul>' ),
			'ol'           => array( 'ol', '<ol class="relsyn"><li>', '</li><li>', '</li></ol>' ),
			'p'            => array( 'p', '<p class="relsyn"><span>', '</span><span>', '</span></p>' ),
			'span'         => array( 'span', '<span class="relsyn">', ' ', '</span>' ),
		);
	}

	public function test_container_css_class_is_an_argument() {
		$post_id = $this->post_with_links();

		$html = get_post_syndication_links( $post_id, array( 'container-css' => 'my-links' ) );

		$this->assertStringContainsString( '<ul class="my-links"><li>', $html );
	}

	public function test_text_before() {
		update_option( 'syndication-links_text_before', 'Also on:' );
		$post_id = $this->post_with_links();

		$this->assertStringStartsWith( '<span class="syn-text">Also on:</span><ul class="relsyn">', get_post_syndication_links( $post_id ) );
		$this->assertStringStartsWith(
			'<span class="my-text">Also on:</span>',
			get_post_syndication_links( $post_id, array( 'text-css' => 'my-text' ) )
		);
		$this->assertStringStartsWith(
			'<ul class="relsyn">',
			get_post_syndication_links( $post_id, array( 'show_text_before' => false ) )
		);
	}

	public function test_hidden_links_have_no_text_before() {
		update_option( 'syndication-links_display', 'hidden' );
		update_option( 'syndication-links_text_before', 'Also on:' );
		$post_id = $this->post_with_links();

		$this->assertSame(
			'<span class="relsyn"><data class="u-syndication" value="https://github.com/someone/repo"></data></span>',
			get_post_syndication_links( $post_id )
		);
	}

	public function test_comment_links_render() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		add_comment_syndication_link( $comment_id, 'https://github.com/someone/repo' );

		$html = get_comment_syndication_links( $comment_id );

		$this->assertStringContainsString( 'href="https://github.com/someone/repo"', $html );
	}

	public function test_the_content_appends_the_links() {
		$post_id = $this->post_with_links();
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$content = apply_filters( 'the_content', 'Hello' );

		$this->assertStringContainsString( '<div class="syndication-links">' . get_post_syndication_links( $post_id ) . '</div>', $content );
		$this->assertStringContainsString( 'rel="syndication nofollow"', $content );
	}

	public function test_the_content_skips_feeds() {
		$post_id = $this->post_with_links();
		$this->go_to( get_feed_link() );
		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertTrue( is_feed() );
		$this->assertSame( 'Hello', Syn_Config::the_content( 'Hello' ) );
	}

	public function test_the_content_feed_adds_text_links() {
		$post_id = $this->post_with_links();
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$content = Syn_Config::the_content_feed( 'Hello', 'rss2' );

		$this->assertStringStartsWith( 'Hello', $content );
		$this->assertStringContainsString( '<p class="relsyn"><span>', $content );
		$this->assertStringContainsString( 'GitHub</a>', $content );
		$this->assertStringNotContainsString( '<svg', $content );
		$this->assertSame( 'Hello', Syn_Config::the_content_feed( 'Hello', 'json' ) );
	}

	public function test_json_feed_item_gets_the_links() {
		$post_id = $this->post_with_links( array( 'https://github.com/someone/repo', 'https://www.example.com/post/1' ) );

		$item = Syn_Config::json_feed_item( array( 'id' => 'x' ), get_post( $post_id ) );

		$this->assertSame(
			array( 'https://github.com/someone/repo', 'https://www.example.com/post/1' ),
			$item['syndication']
		);
		$this->assertSame( array( 'id' => 'x' ), Syn_Config::json_feed_item( array( 'id' => 'x' ), self::factory()->post->create_and_get() ) );
	}

	/**
	 * A dropped link doesn't leave a gap in the keys, which would make the JSON Feed field an object (B12).
	 */
	public function test_json_feed_links_stay_a_list_when_one_is_dropped() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'mf2_syndication', array( 'https://github.com/someone/repo', 'not a url', 'https://github.com/someone/repo', 'https://www.example.com/post/1' ) );

		$item = Syn_Config::json_feed_item( array(), get_post( $post_id ) );

		$this->assertSame( array( 'https://github.com/someone/repo', 'https://www.example.com/post/1' ), $item['syndication'] );
		$this->assertStringStartsWith( '[', wp_json_encode( $item['syndication'] ) );
	}

	/**
	 * Bridgy finds its targets on the post's page, so feeds leave them out.
	 */
	public function test_feeds_leave_out_bridgy_targets() {
		$post_id = $this->post_with_links( array( 'https://github.com/someone/repo', 'https://brid.gy/publish/mastodon', 'https://fed.brid.gy' ) );
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$content = Syn_Config::the_content_feed( 'Hello', 'rss2' );
		$item    = Syn_Config::json_feed_item( array(), get_post( $post_id ) );

		$this->assertStringContainsString( 'GitHub</a>', $content );
		$this->assertStringNotContainsString( 'brid.gy', $content );
		$this->assertSame( array( 'https://github.com/someone/repo' ), $item['syndication'] );
	}

	public function test_a_feed_with_only_bridgy_targets_gets_no_links() {
		$post_id = $this->post_with_links( array( 'https://brid.gy/publish/mastodon' ) );
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$this->assertSame( 'Hello', Syn_Config::the_content_feed( 'Hello', 'rss2' ) );
		$this->assertArrayNotHasKey( 'syndication', Syn_Config::json_feed_item( array(), get_post( $post_id ) ) );
	}

	public function test_the_feed_links_can_be_turned_off() {
		$post_id = $this->post_with_links();
		$this->go_to( get_permalink( $post_id ) );
		the_post();
		add_filter( 'syndication_links_feed_display', '__return_false' );

		$content = Syn_Config::the_content_feed( 'Hello', 'rss2' );
		$item    = Syn_Config::json_feed_item( array( 'id' => 'x' ), get_post( $post_id ) );

		remove_filter( 'syndication_links_feed_display', '__return_false' );
		$this->assertSame( 'Hello', $content );
		$this->assertSame( array( 'id' => 'x' ), $item );
	}

	/**
	 * On a page, the targets are still there for Bridgy to find, unless turned off.
	 */
	public function test_the_targets_argument() {
		$post_id = $this->post_with_links( array( 'https://github.com/someone/repo', 'https://brid.gy/publish/mastodon' ) );

		$this->assertStringContainsString( 'https://brid.gy/publish/mastodon', get_post_syndication_links( $post_id ) );
		$this->assertStringNotContainsString( 'brid.gy', get_post_syndication_links( $post_id, array( 'targets' => false ) ) );
	}

	public function test_comment_text_appends_the_links() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		add_comment_syndication_link( $comment_id, 'https://github.com/someone/repo' );
		$comment = get_comment( $comment_id );

		$text = Syn_Config::comment_text( 'Nice', $comment );

		$this->assertSame( 'Nice<p>' . get_comment_syndication_links( $comment ) . '</p>', $text );
	}

	/**
	 * The stylesheet matches the black-and-white and size options.
	 *
	 * @dataProvider data_stylesheets
	 *
	 * @param string $bw   Black-and-white option.
	 * @param string $size Size option.
	 * @param string $file Expected stylesheet.
	 */
	public function test_stylesheet_follows_the_options( $bw, $size, $file ) {
		update_option( 'syndication-links_bw', $bw );
		update_option( 'syndication-links_size', $size );
		wp_dequeue_style( 'syndication-style' );
		wp_deregister_style( 'syndication-style' );

		Syn_Config::enqueue_scripts();

		$this->assertTrue( wp_style_is( 'syndication-style', 'enqueued' ) );
		$this->assertStringEndsWith( '/' . $file, wp_styles()->registered['syndication-style']->src );
		$this->assertFileExists( dirname( __DIR__ ) . '/' . $file );
	}

	public function data_stylesheets() {
		return array(
			'colour, small'  => array( '0', 'small', 'css/syn.min.css' ),
			'colour, medium' => array( '0', 'medium', 'css/syn-medium.min.css' ),
			'colour, large'  => array( '0', 'large', 'css/syn-large.min.css' ),
			'bw, small'      => array( '1', 'small', 'css/syn-bw.min.css' ),
			'bw, medium'     => array( '1', 'medium', 'css/syn-bw-medium.min.css' ),
			'bw, large'      => array( '1', 'large', 'css/syn-bw-large.min.css' ),
		);
	}
}
