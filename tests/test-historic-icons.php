<?php
/**
 * Tests for the historic icons: retired services that old posts still link to.
 *
 * @package Syndication_Links
 */

class Test_Historic_Icons extends WP_UnitTestCase {

	const STYLE = 'syndication-links-historic';

	public function set_up() {
		parent::set_up();
		wp_dequeue_style( self::STYLE );
		wp_deregister_style( self::STYLE );
		delete_option( 'syndication-links_bw' );
		// Show links (not the hidden <data> markup) on non-singular views, which tests run as.
		update_option( 'syndication-links_display', 'iconstext' );
		update_option( 'syndication-links_archives', '1' );
	}

	public function tear_down() {
		wp_dequeue_style( self::STYLE );
		wp_deregister_style( self::STYLE );
		delete_option( 'syndication-links_bw' );
		parent::tear_down();
	}

	/**
	 * The historic icons, as built by bin/build-icons.php.
	 *
	 * @return array[] Slug and title.
	 */
	public function data_historic_icons() {
		return array(
			array( 'cohost', 'cohost' ),
			array( 'delicious', 'Delicious' ),
			array( 'ello', 'Ello' ),
			array( 'foursquarecityguide', 'Foursquare City Guide' ),
			array( 'googleplus', 'Google+' ),
			array( 'googlepodcasts', 'Google Podcasts' ),
			array( 'pocket', 'Pocket' ),
			array( 'revue', 'Revue' ),
			array( 'stitcher', 'Stitcher' ),
		);
	}

	/**
	 * Each historic icon has an SVG in svgs/historic/, a title and a colour.
	 *
	 * @dataProvider data_historic_icons
	 *
	 * @param string $slug  Icon slug.
	 * @param string $title Icon title.
	 */
	public function test_historic_icon_is_complete( $slug, $title ) {
		$file = Syn_Link_Domain_Icon_Map::get_icon_filename( $slug );

		$this->assertNotNull( $file, "No SVG for $slug." );
		$this->assertSame( Syn_Link_Domain_Icon_Map::get_historic_icon_dir(), dirname( $file ) );
		$this->assertTrue( Syn_Link_Domain_Icon_Map::is_historic( $slug ) );
		$this->assertSame( $title, Syn_Link_Domain_Icon_Map::get_title( $slug ) );

		$css = file_get_contents( dirname( __DIR__ ) . '/css/syn-historic.min.css' );
		$this->assertStringContainsString( ".svg-$slug{", $css );
	}

	/**
	 * Historic icons stay out of the main stylesheet.
	 */
	public function test_historic_icons_not_in_main_stylesheet() {
		$css = file_get_contents( dirname( __DIR__ ) . '/css/syn.min.css' );
		foreach ( $this->data_historic_icons() as $icon ) {
			$this->assertStringNotContainsString( '.svg-' . $icon[0] . '{', $css, $icon[0] );
		}
	}

	/**
	 * Links to retired services map to their historic icon.
	 *
	 * @dataProvider data_historic_urls
	 *
	 * @param string $url  URL.
	 * @param string $name Expected icon name.
	 */
	public function test_url_maps_to_historic_icon( $url, $name ) {
		$this->assertSame( $name, Syn_Link_Domain_Icon_Map::url_to_name( $url ) );
	}

	public function data_historic_urls() {
		return array(
			'Google+'         => array( 'https://plus.google.com/+someone/posts/abc', 'googleplus' ),
			'Google Podcasts' => array( 'https://podcasts.google.com/feed/abc', 'googlepodcasts' ),
			'Pocket'          => array( 'https://getpocket.com/@someone', 'pocket' ),
			'Revue'           => array( 'https://www.getrevue.co/profile/someone', 'revue' ),
			'del.icio.us'     => array( 'https://del.icio.us/someone', 'delicious' ),
			'Delicious'       => array( 'https://delicious.com/someone', 'delicious' ),
			'Ello'            => array( 'https://ello.co/someone', 'ello' ),
			'cohost'          => array( 'https://cohost.org/someone/post/1', 'cohost' ),
			'Stitcher'        => array( 'https://www.stitcher.com/show/abc', 'stitcher' ),
		);
	}

	public function test_current_icons_are_not_historic() {
		$this->assertFalse( Syn_Link_Domain_Icon_Map::is_historic( 'facebook' ) );
		$this->assertFalse( Syn_Link_Domain_Icon_Map::is_historic( 'website' ) );
		$this->assertFalse( Syn_Link_Domain_Icon_Map::is_historic( 'no-such-icon-here' ) );
	}

	/**
	 * Rendering a historic icon loads the historic stylesheet; other icons don't.
	 */
	public function test_historic_icon_enqueues_style() {
		Syn_Link_Domain_Icon_Map::get_icon( 'facebook' );
		$this->assertFalse( wp_style_is( self::STYLE, 'enqueued' ) );

		$icon = Syn_Link_Domain_Icon_Map::get_icon( 'googleplus' );
		$this->assertStringContainsString( '<svg', $icon );
		$this->assertStringContainsString( 'svg-googleplus', $icon );
		$this->assertTrue( wp_style_is( self::STYLE, 'enqueued' ) );
		$this->assertStringEndsWith( '/css/syn-historic.min.css', wp_styles()->registered[ self::STYLE ]->src );
	}

	/**
	 * Black-and-white mode uses no brand colours, so it doesn't load them.
	 */
	public function test_black_and_white_mode_skips_style() {
		update_option( 'syndication-links_bw', '1' );

		Syn_Link_Domain_Icon_Map::get_icon( 'googleplus' );

		$this->assertFalse( wp_style_is( self::STYLE, 'enqueued' ) );
	}

	public function test_filter_can_turn_off_style() {
		add_filter( 'syn_load_historic_icon_style', '__return_false' );

		Syn_Link_Domain_Icon_Map::get_icon( 'googleplus' );

		$this->assertFalse( wp_style_is( self::STYLE, 'enqueued' ) );
	}

	/**
	 * Text-only links don't build the icon, so they don't load icon styles.
	 */
	public function test_text_only_links_skip_style() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://plus.google.com/+someone/posts/abc' );

		$links = Syn_Meta::get_syndication_links_elements(
			'post',
			$post_id,
			array(
				'icons' => false,
				'text'  => true,
			)
		);

		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'Google+', $links[0] );
		$this->assertStringNotContainsString( '<svg', $links[0] );
		$this->assertFalse( wp_style_is( self::STYLE, 'enqueued' ) );
	}

	/**
	 * Hidden links are only microformats markup, so they don't load icon styles.
	 */
	public function test_hidden_links_skip_style() {
		update_option( 'syndication-links_display', 'hidden' );
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://plus.google.com/+someone/posts/abc' );

		$links = Syn_Meta::get_syndication_links_elements( 'post', $post_id, array( 'icons' => true ) );

		$this->assertCount( 1, $links );
		$this->assertStringStartsWith( '<data class="u-syndication"', $links[0] );
		$this->assertFalse( wp_style_is( self::STYLE, 'enqueued' ) );
	}

	/**
	 * Links shown with icons load the stylesheet.
	 */
	public function test_icon_links_load_style() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://plus.google.com/+someone/posts/abc' );

		$links = Syn_Meta::get_syndication_links_elements(
			'post',
			$post_id,
			array(
				'icons' => true,
				'text'  => false,
			)
		);

		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'svg-googleplus', $links[0] );
		$this->assertTrue( wp_style_is( self::STYLE, 'enqueued' ) );
	}
}
