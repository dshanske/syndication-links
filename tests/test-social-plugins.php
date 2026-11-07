<?php
/**
 * Tests for the Social_Plugins integrations.
 *
 * @package Syndication_Links
 */

if ( ! class_exists( 'autopostToMastodon' ) ) {
	/**
	 * Stands in for the autopostToMastodon plugin, which Social_Plugins detects by class name.
	 */
	class autopostToMastodon {} // phpcs:ignore
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- The stub above is only for these tests.
class Test_Social_Plugins extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'autopostToMastodon-instance' );
		parent::tear_down();
	}

	public function test_mapping_is_hooked_to_the_icon_map_filter() {
		$this->assertSame( 10, has_filter( 'syn_link_mapping', array( 'Social_Plugins', 'url_to_name_plugins' ) ) );
	}

	/**
	 * Links to the autopostToMastodon instance get the Mastodon icon (#247).
	 */
	public function test_autopost_to_mastodon_instance_is_mastodon() {
		update_option( 'autopostToMastodon-instance', 'https://toot.example.social' );

		$this->assertSame( 'mastodon', Syn_Link_Domain_Icon_Map::url_to_name( 'https://toot.example.social/@someone/123' ) );
	}

	/**
	 * Other links keep their own icon name.
	 */
	public function test_other_links_keep_their_name() {
		update_option( 'autopostToMastodon-instance', 'https://toot.example.social' );

		$this->assertSame( 'github', Syn_Link_Domain_Icon_Map::url_to_name( 'https://github.com/someone' ) );
		$this->assertSame( 'website', Social_Plugins::url_to_name_plugins( 'website', 'https://other.example/post' ) );
	}

	public function test_instance_host_is_compared_case_insensitively() {
		update_option( 'autopostToMastodon-instance', 'https://Toot.Example.Social/' );

		$this->assertSame( 'mastodon', Social_Plugins::url_to_name_plugins( 'website', 'https://toot.example.social/@someone/123' ) );
	}

	public function test_an_invalid_instance_is_ignored() {
		update_option( 'autopostToMastodon-instance', 'not a url' );

		$this->assertSame( 'website', Social_Plugins::url_to_name_plugins( 'website', 'https://toot.example.social/@someone/123' ) );
	}

	public function test_without_an_instance_names_are_unchanged() {
		$this->assertSame( 'website', Social_Plugins::url_to_name_plugins( 'website', 'https://toot.example.social/@someone/123' ) );
	}
}
