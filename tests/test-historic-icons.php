<?php
/**
 * Tests for the historic icons: retired services that old posts still link to.
 *
 * @package Syndication_Links
 */

class Test_Historic_Icons extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Show links (not the hidden <data> markup) on non-singular views, which tests run as.
		update_option( 'syndication-links_display', 'iconstext' );
		update_option( 'syndication-links_archives', '1' );
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

		$this->assertMatchesRegularExpression( '/^#[0-9A-F]{6}$/', (string) Syn_Link_Domain_Icon_Map::get_color( $slug ) );
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
	 * A historic icon renders with its colour, like any other icon.
	 */
	public function test_historic_icon_links_carry_their_colour() {
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
		$this->assertStringContainsString( 'style="--syn-icon-color: #DC4E41;"', $links[0] );
	}
}
