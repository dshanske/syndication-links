<?php
/**
 * Tests for Syn_Link_Domain_Icon_Map.
 *
 * Ported from the IndieWeb plugin's Domain_Icon_Map tests (5.3.0), for the
 * behaviour the two copies share. Cases that depend on open fixes come with
 * those fixes: non-http, mailto: and sms: URLs (REVIEW.md B4), icon name
 * validation (S7), the fallback for a missing icon (B9) and Mastodon
 * detection (#226).
 *
 * @package Syndication_Links
 */

class Test_Domain_Icon_Map extends WP_UnitTestCase {

	/**
	 * Directory used to test additional icon directories.
	 *
	 * @var string
	 */
	private $icon_dir;

	/**
	 * Create a directory with a custom icon.
	 */
	public function set_up() {
		parent::set_up();

		$this->icon_dir = get_temp_dir() . 'syn-test-icons/';
		wp_mkdir_p( $this->icon_dir );
		file_put_contents( $this->icon_dir . 'testicon.svg', '<svg id="testicon"></svg>' ); // phpcs:ignore
		file_put_contents( $this->icon_dir . 'facebook.svg', '<svg id="custom-facebook"></svg>' ); // phpcs:ignore
	}

	/**
	 * Remove the custom icon directory.
	 */
	public function tear_down() {
		foreach ( array( 'testicon.svg', 'facebook.svg' ) as $file ) {
			if ( file_exists( $this->icon_dir . $file ) ) {
				wp_delete_file( $this->icon_dir . $file );
			}
		}
		if ( is_dir( $this->icon_dir ) ) {
			rmdir( $this->icon_dir ); // phpcs:ignore
		}

		parent::tear_down();
	}

	/**
	 * Add the test directory to the end of the icon directories.
	 */
	private function append_icon_dir() {
		add_filter(
			'syn_icon_file_dirs',
			function ( $dirs ) {
				$dirs[] = $this->icon_dir;
				return $dirs;
			}
		);
	}

	/**
	 * Add the test directory to the front of the icon directories.
	 */
	private function prepend_icon_dir() {
		add_filter(
			'syn_icon_file_dirs',
			function ( $dirs ) {
				array_unshift( $dirs, $this->icon_dir );
				return $dirs;
			}
		);
	}

	public function test_split_domain_two_parts() {
		$this->assertSame( 'example', Syn_Link_Domain_Icon_Map::split_domain( 'example.com' ) );
	}

	public function test_split_domain_three_parts() {
		$this->assertSame( 'example', Syn_Link_Domain_Icon_Map::split_domain( 'www.example.com' ) );
	}

	public function test_split_domain_single_part() {
		$this->assertSame( 'localhost', Syn_Link_Domain_Icon_Map::split_domain( 'localhost' ) );
	}

	public function test_split_domain_four_parts_is_unchanged() {
		$this->assertSame( 'a.b.example.com', Syn_Link_Domain_Icon_Map::split_domain( 'a.b.example.com' ) );
	}

	/**
	 * Domains in the built-in map.
	 *
	 * @dataProvider data_mapped_domains
	 *
	 * @param string $url  URL.
	 * @param string $name Expected icon name.
	 */
	public function test_url_to_name_mapped_domain( $url, $name ) {
		$this->assertSame( $name, Syn_Link_Domain_Icon_Map::url_to_name( $url ) );
	}

	public function data_mapped_domains() {
		return array(
			'facebook'           => array( 'https://facebook.com/someone', 'facebook' ),
			'twitter maps to x'  => array( 'https://twitter.com/someone/status/1', 'x' ),
			'bsky.app'           => array( 'https://bsky.app/profile/someone', 'bluesky' ),
			'swarm'              => array( 'https://swarmapp.com/c/abc', 'swarm' ),
			'micro.blog'         => array( 'https://micro.blog/someone', 'microdotblog' ),
			'blogspot'           => array( 'https://blogspot.com/', 'blogger' ),
			'nostr client'       => array( 'https://primal.net/e/abc', 'nostr' ),
			'www. is stripped'   => array( 'https://www.facebook.com/someone', 'facebook' ),
			'case is ignored'    => array( 'https://Facebook.COM/someone', 'facebook' ),
			'plain http'         => array( 'http://facebook.com/someone', 'facebook' ),
		);
	}

	/**
	 * Domains that aren't in the map are matched to an icon by name.
	 */
	public function test_url_to_name_matches_icon_file() {
		$this->assertSame( 'github', Syn_Link_Domain_Icon_Map::url_to_name( 'https://github.com/someone' ) );
		$this->assertSame( 'youtube', Syn_Link_Domain_Icon_Map::url_to_name( 'https://m.youtube.com/watch?v=1' ) );
	}

	public function test_url_to_name_unknown_domain_is_website() {
		$this->assertSame( 'website', Syn_Link_Domain_Icon_Map::url_to_name( 'https://no-such-icon-here.example/post' ) );
	}

	/**
	 * Non-web links get an icon for their scheme (REVIEW.md B4).
	 *
	 * @dataProvider data_non_web_links
	 *
	 * @param string $url  The link.
	 * @param string $name The expected icon name.
	 */
	public function test_url_to_name_non_web_link( $url, $name ) {
		$this->assertSame( $name, Syn_Link_Domain_Icon_Map::url_to_name( $url ) );
	}

	public function data_non_web_links() {
		return array(
			'mailto'       => array( 'mailto:someone@example.com', 'mail' ),
			'mailto, caps' => array( 'MAILTO:someone@example.com', 'mail' ),
			'sms'          => array( 'sms:+15555550100', 'phone' ),
			'tel'          => array( 'tel:+15555550100', 'phone' ),
			'other scheme' => array( 'gemini://example.com/post', 'notice' ),
			'no scheme'    => array( 'example.com/post', 'notice' ),
		);
	}

	public function test_non_web_links_are_not_cached_under_a_domain() {
		$map = new ReflectionProperty( 'Syn_Link_Domain_Icon_Map', 'map' );
		$map->setAccessible( true );
		$before = $map->getValue();

		Syn_Link_Domain_Icon_Map::url_to_name( 'mailto:someone@example.com' );

		$this->assertSame( $before, $map->getValue() );
	}

	public function test_non_web_links_go_through_the_filter() {
		$filter = function ( $name, $url ) {
			return 0 === strpos( $url, 'mailto:' ) ? 'custom-mail' : $name;
		};
		add_filter( 'syn_link_mapping', $filter, 10, 2 );

		$this->assertSame( 'custom-mail', Syn_Link_Domain_Icon_Map::url_to_name( 'mailto:someone@example.com' ) );
		remove_filter( 'syn_link_mapping', $filter, 10 );
	}

	/**
	 * Unknown domains with WordPress, read or news in them get a generic icon.
	 */
	public function test_url_to_name_keyword_fallbacks() {
		$this->assertSame( 'wordpress', Syn_Link_Domain_Icon_Map::url_to_name( 'https://mywordpresssite.example/post' ) ); // phpcs:ignore WordPress.WP.CapitalPDangit
		$this->assertSame( 'book', Syn_Link_Domain_Icon_Map::url_to_name( 'https://goodread-ish.example/post' ) );
		$this->assertSame( 'summary', Syn_Link_Domain_Icon_Map::url_to_name( 'https://dailynews.example/post' ) );
	}

	public function test_url_to_name_filter() {
		$callback = function ( $name, $url ) {
			return 'https://example.com/special' === $url ? 'custom' : $name;
		};
		add_filter( 'syn_link_mapping', $callback, 10, 2 );

		$this->assertSame( 'custom', Syn_Link_Domain_Icon_Map::url_to_name( 'https://example.com/special' ) );
		$this->assertSame( 'facebook', Syn_Link_Domain_Icon_Map::url_to_name( 'https://facebook.com/other' ) );

		remove_filter( 'syn_link_mapping', $callback, 10 );
	}

	public function test_url_to_name_uses_added_dir() {
		$this->append_icon_dir();

		$this->assertSame( 'testicon', Syn_Link_Domain_Icon_Map::url_to_name( 'https://testicon.com/someone' ) );
	}

	public function test_get_icon_file_dirs_contains_bundled_dirs() {
		$dirs = array_map( 'untrailingslashit', Syn_Link_Domain_Icon_Map::get_icon_file_dirs() );
		$base = untrailingslashit( dirname( __DIR__ ) ) . '/svgs';

		$this->assertContains( $base . '/simple-icons', $dirs );
		$this->assertNotContains( $base . '/genericons-neue', $dirs );
		$this->assertContains( $base, $dirs );
	}

	public function test_get_icon_filename_finds_bundled_icon() {
		$file = Syn_Link_Domain_Icon_Map::get_icon_filename( 'facebook' );

		$this->assertNotNull( $file );
		$this->assertFileExists( $file );
		$this->assertSame( 'facebook.svg', basename( $file ) );
	}

	/**
	 * The generic icons used as fallbacks are present. They come from
	 * Genericons and the plugin's own svgs/ directory (see REVIEW.md B9).
	 */
	/**
	 * Names that could leave the icon directories aren't looked up (#258).
	 *
	 * @dataProvider data_unsafe_icon_names
	 *
	 * @param mixed $name Icon name.
	 */
	public function test_unsafe_icon_names_are_refused( $name ) {
		$this->assertNull( Syn_Link_Domain_Icon_Map::get_icon_filename( $name ) );
	}

	public function data_unsafe_icon_names() {
		return array(
			'parent directory' => array( '../svgs/website' ),
			'two dots'         => array( 'web..site' ),
			'absolute path'    => array( '/etc/passwd' ),
			'backslash'        => array( '..\\website' ),
			'null byte'        => array( "website\0" ),
			'trailing newline' => array( "website\n" ),
			'space'            => array( 'web site' ),
			'empty'            => array( '' ),
			'not a string'     => array( array( 'website' ) ),
		);
	}

	public function test_safe_icon_names_are_found() {
		$this->assertNotNull( Syn_Link_Domain_Icon_Map::get_icon_filename( 'website' ) );
		$this->assertStringEndsWith( '/github.svg', Syn_Link_Domain_Icon_Map::get_icon_filename( 'github' ) );
	}

	/**
	 * Without an icon, the website icon is shown, with the name's class and title (#220, #117).
	 */
	public function test_missing_icon_falls_back_to_website_icon() {
		$icon = Syn_Link_Domain_Icon_Map::get_icon( 'no-such-icon' );

		$this->assertStringContainsString( 'class="syndication-link-icon svg-website svg-no-such-icon"', $icon );
		$this->assertStringContainsString( 'title="no-such-icon"', $icon );
		$this->assertStringContainsString( '<svg', $icon );
	}

	public function test_missing_icon_name_is_escaped() {
		$icon = Syn_Link_Domain_Icon_Map::get_icon( '<b>x</b>"' );

		$this->assertStringNotContainsString( '<b>', $icon );
		$this->assertStringContainsString( 'svg-bx', $icon );
		$this->assertStringContainsString( 'title="&lt;b&gt;x&lt;/b&gt;&quot;"', $icon );
	}

	/**
	 * Only when not even the website icon can be found is the name shown as text.
	 */
	public function test_missing_website_icon_falls_back_to_escaped_text() {
		add_filter( 'syn_icon_file_dirs', '__return_empty_array' );

		$this->assertSame( '&lt;b&gt;x&lt;/b&gt;', Syn_Link_Domain_Icon_Map::get_icon( '<b>x</b>' ) );
	}

	/**
	 * The utility icons are bundled in svgs/ (Genericons Neue is no longer bundled whole).
	 */
	public function test_utility_icons_are_bundled() {
		foreach ( array( 'website', 'notice', 'book', 'summary', 'mail', 'phone', 'info' ) as $name ) {
			$this->assertSame( untrailingslashit( dirname( __DIR__ ) ) . "/svgs/$name.svg", Syn_Link_Domain_Icon_Map::get_icon_filename( $name ) );
		}
		$this->assertDirectoryDoesNotExist( dirname( __DIR__ ) . '/svgs/genericons-neue' );
	}

	public function test_fallback_icons_exist() {
		foreach ( array( 'website', 'notice', 'book', 'summary', 'wordpress', 'indieweb', 'nostr', 'bookwyrm' ) as $name ) { // phpcs:ignore WordPress.WP.CapitalPDangit
			$this->assertNotNull( Syn_Link_Domain_Icon_Map::get_icon_filename( $name ), "Icon {$name} is missing." );
		}
	}

	public function test_get_icon_filename_returns_null_for_nonexistent() {
		$this->assertNull( Syn_Link_Domain_Icon_Map::get_icon_filename( 'no-such-icon-here' ) );
	}

	public function test_get_icon_filename_finds_icon_in_added_dir() {
		$this->assertNull( Syn_Link_Domain_Icon_Map::get_icon_filename( 'testicon' ) );

		$this->append_icon_dir();

		$this->assertSame( $this->icon_dir . 'testicon.svg', Syn_Link_Domain_Icon_Map::get_icon_filename( 'testicon' ) );
	}

	/**
	 * Directories are searched in order: one added to the front replaces a
	 * bundled icon, one added to the end doesn't.
	 */
	public function test_icon_dir_order() {
		$this->append_icon_dir();
		$this->assertStringNotContainsString( 'custom-facebook', Syn_Link_Domain_Icon_Map::get_icon_svg( 'facebook' ) );

		remove_all_filters( 'syn_icon_file_dirs' );
		$this->prepend_icon_dir();
		$this->assertStringContainsString( 'custom-facebook', Syn_Link_Domain_Icon_Map::get_icon_svg( 'facebook' ) );
	}

	public function test_get_icon_svg_from_added_dir() {
		$this->append_icon_dir();

		$this->assertSame( '<svg id="testicon"></svg>', Syn_Link_Domain_Icon_Map::get_icon_svg( 'testicon' ) );
	}

	public function test_get_icon_svg_returns_null_for_nonexistent() {
		$this->assertNull( Syn_Link_Domain_Icon_Map::get_icon_svg( 'no-such-icon-here' ) );
	}

	public function test_pre_syn_link_icon_filter() {
		$callback = function ( $icon, $name ) {
			return 'virtual' === $name ? '<svg id="virtual"></svg>' : $icon;
		};
		add_filter( 'pre_syn_link_icon', $callback, 10, 2 );

		$this->assertSame( '<svg id="virtual"></svg>', Syn_Link_Domain_Icon_Map::get_icon_svg( 'virtual' ) );
		$this->assertStringContainsString( '<svg', Syn_Link_Domain_Icon_Map::get_icon_svg( 'facebook' ) );

		remove_filter( 'pre_syn_link_icon', $callback, 10 );
	}

	public function test_get_icon_markup() {
		$icon = Syn_Link_Domain_Icon_Map::get_icon( 'facebook' );

		$this->assertStringContainsString( 'class="syndication-link-icon svg-facebook"', $icon );
		$this->assertStringContainsString( 'title="Facebook"', $icon );
		$this->assertStringContainsString( '<svg', $icon );
	}

	public function test_get_title_known_icon() {
		$this->assertSame( 'Facebook', Syn_Link_Domain_Icon_Map::get_title( 'facebook' ) );
		$this->assertSame( 'X', Syn_Link_Domain_Icon_Map::get_title( 'x' ) );
	}

	public function test_get_title_returns_name_when_not_found() {
		$this->assertSame( 'no-such-icon-here', Syn_Link_Domain_Icon_Map::get_title( 'no-such-icon-here' ) );
	}

	public function test_get_title_filter() {
		$callback = function ( $title, $name ) {
			return 'testicon' === $name ? 'Test Icon' : $title;
		};
		add_filter( 'syn_link_title', $callback, 10, 2 );

		$this->assertSame( 'Test Icon', Syn_Link_Domain_Icon_Map::get_title( 'testicon' ) );

		remove_filter( 'syn_link_title', $callback, 10 );
	}
}
