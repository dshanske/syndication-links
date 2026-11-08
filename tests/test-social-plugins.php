<?php
/**
 * Tests for the Social_Plugins integrations.
 *
 * @package Syndication_Links
 */

class Test_Social_Plugins extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'autopostToMastodon-instance' );
		delete_option( 'wtt_twitter_username' );
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

	/**
	 * Mastodon Autopost's toot is added for the post the filter asks about, outside the loop too.
	 */
	public function test_mastodon_autopost_toot_is_added() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'autopostToMastodonshare-lastSuccessfullTootURL', 'https://toot.example.social/@someone/123' );
		$saved           = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = self::factory()->post->create_and_get();

		$links           = get_post_syndication_links_data( $post_id );
		$GLOBALS['post'] = $saved;

		$this->assertSame( array( 'https://toot.example.social/@someone/123' ), $links );
	}

	public function test_mastodon_autopost_older_meta_key_is_still_read() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'mastodonAutopostLastSuccessfullTootURL', 'https://toot.example.social/@someone/1' );

		$this->assertSame( array( 'https://toot.example.social/@someone/1' ), get_post_syndication_links_data( $post_id ) );
	}

	/**
	 * @dataProvider data_autopost_for_x
	 *
	 * @param string $handle   Saved handle.
	 * @param string $expected Expected link.
	 */
	public function test_autopost_for_x_status_is_added( $handle, $expected ) {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'autoshare_status',
			array(
				array(
					'status'     => 'published',
					'twitter_id' => '123',
					'handle'     => $handle,
				),
				array(
					'status'  => 'error',
					'message' => 'Failed',
				),
			)
		);

		$this->assertSame( array( $expected ), get_post_syndication_links_data( $post_id ) );
	}

	public function data_autopost_for_x() {
		return array(
			'with a handle' => array( 'someone', 'https://x.com/someone/status/123' ),
			'empty handle'  => array( '', 'https://x.com/i/web/status/123' ),
		);
	}

	/**
	 * XPoster saves the status ID after wpt_tweet_posted, so the link is recorded when the ID is saved.
	 */
	public function test_xposter_status_is_recorded_when_its_id_is_saved() {
		update_option( 'wtt_twitter_username', 'someone' );
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, '_wpt_tweet_id', '456' );

		$this->assertSame( array( 'https://twitter.com/someone/status/456' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	public function test_other_meta_doesnt_record_an_xposter_status() {
		update_option( 'wtt_twitter_username', 'someone' );
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, '_wpt_short_url', 'https://short.example/1' );

		$this->assertSame( '', get_post_meta( $post_id, 'mf2_syndication', true ) );
	}
}
