<?php
/**
 * Tests for link sources from other plugins, and importing their links.
 *
 * @package Syndication_Links
 */

class Test_Plugin_Import extends WP_UnitTestCase {

	public function tear_down() {
		$registered = new ReflectionProperty( 'Social_Plugins', 'registered' );
		$registered->setAccessible( true );
		$registered->setValue( null, array() );
		delete_option( Syn_Plugin_Import::OPTION );
		wp_clear_scheduled_hook( 'syn_plugin_import', array( 'mastodon-autopost', 1 ) );
		parent::tear_down();
	}

	public function test_builtin_sources() {
		$this->assertSame(
			array( 'snap', 'mastodon-autopost', 'xposter', 'autopost-for-x', 'keyring', 'medium' ),
			array_keys( Social_Plugins::sources() )
		);
		// Medium's links live in the Medium plugin, so there's nothing to query.
		$this->assertArrayNotHasKey( 'medium', Social_Plugins::import_sources() );
	}

	private function register_custom() {
		return register_syndication_link_source(
			'my-crossposter',
			array(
				'name'       => 'My Crossposter',
				'callback'   => function ( $post_id ) {
					return (array) get_post_meta( $post_id, '_my_crossposter_url', false );
				},
				'meta_query' => array(
					array(
						'key'     => '_my_crossposter_url',
						'compare' => 'EXISTS',
					),
				),
			)
		);
	}

	public function test_a_registered_source_is_shown_and_imported() {
		$this->assertTrue( $this->register_custom() );
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, '_my_crossposter_url', 'https://cross.example/1' );
		self::factory()->post->create();

		$this->assertSame( array( 'https://cross.example/1' ), get_post_syndication_links_data( $post_id ) );
		$this->assertSame( 1, Syn_Plugin_Import::count( 'my-crossposter' ) );

		$result = Syn_Plugin_Import::import_batch( 'my-crossposter' );

		$this->assertSame( 1, $result['posts'] );
		$this->assertFalse( $result['more'] );
		$this->assertSame( array( 'https://cross.example/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	public function test_a_source_added_by_filter_is_used() {
		$filter = function ( $sources ) {
			$sources['filtered'] = array(
				'name'     => 'Filtered',
				'callback' => function () {
					return array( 'https://filtered.example/1' );
				},
			);
			return $sources;
		};
		add_filter( 'syn_link_sources', $filter );

		$sources = Social_Plugins::sources();
		$links   = get_post_syndication_links_data( self::factory()->post->create() );
		remove_filter( 'syn_link_sources', $filter );

		$this->assertTrue( $sources['filtered']['show'] );
		$this->assertSame( array(), $sources['filtered']['meta_query'] );
		$this->assertContains( 'https://filtered.example/1', $links );
	}

	/**
	 * @dataProvider data_invalid_sources
	 *
	 * @param string $slug Slug.
	 * @param array  $args Source.
	 */
	public function test_invalid_sources_are_rejected( $slug, $args ) {
		$this->assertFalse( register_syndication_link_source( $slug, $args ) );
		$this->assertArrayNotHasKey( $slug, Social_Plugins::sources() );
	}

	public function data_invalid_sources() {
		return array(
			'no name'         => array( 'a', array( 'callback' => '__return_empty_array' ) ),
			'no callback'     => array( 'b', array( 'name' => 'B' ) ),
			'not callable'    => array( 'c', array( 'name' => 'C', 'callback' => 'no_such_function' ) ),
			'bad slug'        => array( 'Not A Slug', array( 'name' => 'D', 'callback' => '__return_empty_array' ) ),
		);
	}

	/**
	 * Links are imported for posts of every type and status, without changing the other plugin's data.
	 */
	public function test_import_copies_links_and_leaves_the_source_alone() {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $page, 'autopostToMastodonshare-lastSuccessfullTootURL', 'https://toot.example.social/@someone/1' );
		add_post_syndication_link( $page, 'https://github.com/someone/repo' );

		Syn_Plugin_Import::import_batch( 'mastodon-autopost' );
		Syn_Plugin_Import::import_batch( 'mastodon-autopost' );

		// add_syndication_link() puts new links first; what matters is that each is there once.
		$this->assertEqualsCanonicalizing( array( 'https://github.com/someone/repo', 'https://toot.example.social/@someone/1' ), get_post_meta( $page, 'mf2_syndication', true ) );
		$this->assertSame( 'https://toot.example.social/@someone/1', get_post_meta( $page, 'autopostToMastodonshare-lastSuccessfullTootURL', true ) );
	}

	public function test_xposter_and_autopost_for_x_import() {
		update_option( 'wtt_twitter_username', 'someone' );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_wpt_tweet_id', '456' );
		update_post_meta(
			$post_id,
			'autoshare_status',
			array(
				array(
					'status'     => 'published',
					'twitter_id' => '123',
					'handle'     => '',
				),
			)
		);

		Syn_Plugin_Import::import_batch( 'xposter' );
		Syn_Plugin_Import::import_batch( 'autopost-for-x' );
		delete_option( 'wtt_twitter_username' );

		$this->assertSame( array( 'https://twitter.com/someone/status/456', 'https://x.com/i/web/status/123' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	/**
	 * Without Keyring's taxonomy, the generic href key isn't treated as Keyring's.
	 */
	public function test_keyring_href_needs_keyrings_taxonomy() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'href', 'https://not-keyring.example/1' );
		update_post_meta( $post_id, 'flickr_url', 'https://www.flickr.com/photos/someone/1' );

		$this->assertSame( array( 'https://www.flickr.com/photos/someone/1' ), Social_Plugins::keyring_links( $post_id ) );
	}

	public function test_unknown_source() {
		$this->assertFalse( Syn_Plugin_Import::import_batch( 'nope' ) );
		$this->assertFalse( Syn_Plugin_Import::start( 'nope' ) );
		$this->assertSame( 0, Syn_Plugin_Import::count( 'nope' ) );
	}

	public function test_start_schedules_the_import_and_a_run_finishes_it() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'autopostToMastodonshare-lastSuccessfullTootURL', 'https://toot.example.social/@someone/1' );

		$this->assertTrue( Syn_Plugin_Import::start( 'mastodon-autopost' ) );
		$this->assertNotFalse( wp_next_scheduled( 'syn_plugin_import', array( 'mastodon-autopost', 1 ) ) );
		$statuses = Syn_Plugin_Import::statuses();
		$this->assertSame( 'running', $statuses['mastodon-autopost']['status'] );

		Syn_Plugin_Import::run_scheduled( 'mastodon-autopost', 1 );

		$statuses = Syn_Plugin_Import::statuses();
		$this->assertSame( 'done', $statuses['mastodon-autopost']['status'] );
		$this->assertSame( 1, $statuses['mastodon-autopost']['posts'] );
		$this->assertFalse( wp_next_scheduled( 'syn_plugin_import', array( 'mastodon-autopost', 2 ) ) );
	}

	public function test_import_request_needs_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST['source'] = 'mastodon-autopost';

		try {
			Syn_Plugin_Import::handle_request();
			$this->fail( 'Expected wp_die().' );
		} catch ( WPDieException $e ) {
			$this->assertFalse( wp_next_scheduled( 'syn_plugin_import', array( 'mastodon-autopost', 1 ) ) );
		} finally {
			unset( $_POST['source'] );
		}
	}

	public function test_import_tab_lists_sources_with_a_button_when_there_is_data() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'autopostToMastodonshare-lastSuccessfullTootURL', 'https://toot.example.social/@someone/1' );

		ob_start();
		Syn_Plugin_Import::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Mastodon Autopost', $html );
		$this->assertStringContainsString( 'NextScripts: Social Networks Auto-Poster', $html );
		$this->assertStringNotContainsString( '>Medium<', $html );
		$this->assertSame( 1, substr_count( $html, 'name="source"' ) );
		$this->assertStringContainsString( 'value="mastodon-autopost"', $html );
	}
}
