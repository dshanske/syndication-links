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
		$this->assertGreaterThan( 100, count( $svgs ) );

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
	 * The custom icons have titles, and Nostr keeps its colour.
	 */
	public function test_custom_icons_have_titles() {
		$this->assertSame( 'BookWyrm', Syn_Link_Domain_Icon_Map::get_title( 'bookwyrm' ) );
		$this->assertSame( 'Nostr', Syn_Link_Domain_Icon_Map::get_title( 'nostr' ) );
		$this->assertSame( '#662482', Syn_Link_Domain_Icon_Map::get_color( 'nostr' ) );
		$this->assertNull( Syn_Link_Domain_Icon_Map::get_color( 'bookwyrm' ) );
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
