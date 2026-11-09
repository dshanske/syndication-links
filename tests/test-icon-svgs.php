<?php
/**
 * Tests that the bundled SVGs are clean for inline use (#359).
 *
 * @package Syndication_Links
 */

class Test_Icon_Svgs extends WP_UnitTestCase {

	/**
	 * Every bundled SVG.
	 *
	 * @return string[] File paths.
	 */
	private function svgs() {
		$dir = dirname( __DIR__ ) . '/svgs';
		return array_merge( glob( $dir . '/*.svg' ), glob( $dir . '/simple-icons/*.svg' ), glob( $dir . '/historic/*.svg' ) );
	}

	/**
	 * No <title> (a second tooltip next to the span's), no <style> (it styles the
	 * whole page), and no XML declaration or comments.
	 */
	public function test_bundled_svgs_are_clean() {
		$svgs = $this->svgs();
		$this->assertGreaterThan( 50, count( $svgs ) );

		$dirty = array();
		foreach ( $svgs as $file ) {
			$svg = file_get_contents( $file );
			if ( preg_match( '/<title\b|<style\b|<\?xml|<!--/i', $svg ) ) {
				$dirty[] = basename( dirname( $file ) ) . '/' . basename( $file );
			}
		}
		$this->assertSame( array(), $dirty );
	}

	/**
	 * Only the curated set is bundled, and each icon in it has a title and a colour (#360).
	 */
	public function test_curated_set_is_bundled() {
		$svgs = glob( dirname( __DIR__ ) . '/svgs/simple-icons/*.svg' );
		$this->assertGreaterThan( 50, count( $svgs ) );
		$this->assertLessThan( 200, count( $svgs ) );

		$names  = simpleicons_syn_get_names();
		$colors = simpleicons_syn_get_colors();
		foreach ( $svgs as $file ) {
			$slug = basename( $file, '.svg' );
			$this->assertArrayHasKey( $slug, $names, $slug );
			$this->assertArrayHasKey( $slug, $colors, $slug );
		}

		// The syndication targets and mapped services are in the set.
		foreach ( array( 'bluesky', 'mastodon', 'github', 'flickr', 'microdotblog', 'pinboard', 'applemusic', 'indieweb', 'x' ) as $slug ) {
			$this->assertFileExists( dirname( __DIR__ ) . "/svgs/simple-icons/$slug.svg" );
		}
	}

	/**
	 * The custom icons have titles and colours: Nostr purple, BookWyrm black as before.
	 */
	public function test_custom_icons_have_titles() {
		$this->assertSame( 'BookWyrm', Syn_Link_Domain_Icon_Map::get_title( 'bookwyrm' ) );
		$this->assertSame( 'Nostr', Syn_Link_Domain_Icon_Map::get_title( 'nostr' ) );
		$this->assertSame( '#662482', Syn_Link_Domain_Icon_Map::get_color( 'nostr' ) );
		$this->assertSame( '#000000', Syn_Link_Domain_Icon_Map::get_color( 'bookwyrm' ) );
	}

	/**
	 * The custom SVGs draw in the icon's colour, not a fixed one.
	 *
	 * @dataProvider data_custom_icons
	 *
	 * @param string $name Icon name.
	 */
	public function test_custom_icons_use_current_colour( $name ) {
		$svg = Syn_Link_Domain_Icon_Map::get_icon_svg( $name );

		$this->assertNotNull( $svg );
		$this->assertDoesNotMatchRegularExpression( '/(fill|stroke)="#/i', $svg );
	}

	public function data_custom_icons() {
		return array(
			array( 'bookwyrm' ),
			array( 'nostr' ),
		);
	}
}
