<?php
/**
 * Tests for detecting the Fediverse software of linked servers (#361).
 *
 * Each test uses its own hosts, because Syn_Link_Domain_Icon_Map caches the
 * icon of each domain for the request.
 *
 * @package Syndication_Links
 */

class Test_Fediverse_Detection extends WP_UnitTestCase {

	/**
	 * Mocked responses, keyed by URL: array( code, body ).
	 *
	 * @var array[]
	 */
	private $responses = array();

	/**
	 * Requests made, as array( url, args ).
	 *
	 * @var array[]
	 */
	private $requests = array();

	public function set_up() {
		parent::set_up();
		$this->responses = array();
		$this->requests  = array();
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
		delete_option( Syn_Fediverse_Detection::CACHE );
		delete_option( Syn_Fediverse_Detection::OPTION );
		Syn_Fediverse_Detection::flush();
		_set_cron_array( array() );
	}

	public function tear_down() {
		delete_option( Syn_Fediverse_Detection::CACHE );
		delete_option( Syn_Fediverse_Detection::OPTION );
		Syn_Fediverse_Detection::flush();
		_set_cron_array( array() );
		parent::tear_down();
	}

	/**
	 * Returns the mocked response for a URL, or a 404.
	 *
	 * @param false|array $pre  Preempted response.
	 * @param array       $args Request arguments.
	 * @param string      $url  URL.
	 * @return array
	 */
	public function mock_http( $pre, $args, $url ) {
		$this->requests[] = array( $url, $args );
		$response         = isset( $this->responses[ $url ] ) ? $this->responses[ $url ] : array( 404, 'Not found' );
		$dictionary       = class_exists( 'WpOrg\\Requests\\Utility\\CaseInsensitiveDictionary' ) ? 'WpOrg\\Requests\\Utility\\CaseInsensitiveDictionary' : 'Requests_Utility_CaseInsensitiveDictionary';
		return array(
			'headers'  => new $dictionary( array( 'content-type' => 'application/json' ) ),
			'body'     => $response[1],
			'response' => array(
				'code'    => $response[0],
				'message' => get_status_header_desc( $response[0] ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Mocks a server's NodeInfo.
	 *
	 * @param string $host      Host.
	 * @param string $software  Software name.
	 * @param array  $protocols Protocols.
	 * @param string $version   Schema version of the link.
	 */
	private function nodeinfo( $host, $software, $protocols = array( 'activitypub' ), $version = '2.0' ) {
		$href = "https://$host/nodeinfo/$version";
		$this->responses[ "https://$host/.well-known/nodeinfo" ] = array(
			200,
			wp_json_encode(
				array(
					'links' => array(
						array(
							'rel'  => "http://nodeinfo.diaspora.software/ns/schema/$version",
							'href' => $href,
						),
					),
				)
			),
		);
		$this->responses[ $href ]                                = array(
			200,
			wp_json_encode(
				array(
					'version'   => $version,
					'software'  => array(
						'name'    => $software,
						'version' => '1.0',
					),
					'protocols' => $protocols,
				)
			),
		);
	}

	public function test_detection_is_on_by_default() {
		$this->assertTrue( Syn_Fediverse_Detection::enabled() );
	}

	public function test_detects_mastodon() {
		$this->nodeinfo( 'social.example', 'mastodon' );

		$result = Syn_Fediverse_Detection::detect( 'social.example' );

		$this->assertSame( 'mastodon', $result['software'] );
		$this->assertSame( 'mastodon', $result['icon'] );
		$this->assertSame( 'mastodon', Syn_Fediverse_Detection::get_icon( 'social.example' ) );
		$this->assertSame( 'mastodon', Syn_Link_Domain_Icon_Map::url_to_name( 'https://social.example/@someone/1' ) );
	}

	/**
	 * Software without an icon of its own falls back on its protocol.
	 *
	 * @dataProvider data_software
	 *
	 * @param string $software  Software name.
	 * @param array  $protocols Protocols.
	 * @param string $icon      Expected icon.
	 */
	public function test_software_icon( $software, $protocols, $icon ) {
		$this->assertSame( $icon, Syn_Fediverse_Detection::software_icon( $software, $protocols ) );
	}

	public function data_software() {
		return array(
			'Pixelfed'                => array( 'pixelfed', array( 'activitypub' ), 'pixelfed' ),
			'PeerTube'                => array( 'peertube', array( 'activitypub' ), 'peertube' ),
			'Lemmy'                   => array( 'lemmy', array( 'activitypub' ), 'lemmy' ),
			'Sharkey, a Misskey fork' => array( 'sharkey', array( 'activitypub' ), 'misskey' ),
			'Akkoma, a Pleroma fork'  => array( 'akkoma', array( 'activitypub' ), 'pleroma' ),
			'GoToSocial'              => array( 'gotosocial', array( 'activitypub' ), 'activitypub' ),
			'WordPress'               => array( 'wordpress', array( 'activitypub' ), 'wordpress' ),
			'diaspora only'           => array( 'someserver', array( 'diaspora' ), 'diaspora' ),
			'not the Fediverse'       => array( 'someserver', array( 'other' ), '' ),
			'no protocols'            => array( 'someserver', array(), '' ),
		);
	}

	public function test_software_filter_adds_an_icon() {
		add_filter(
			'syn_link_fediverse_software',
			function ( $map ) {
				$map['gotosocial'] = 'mastodon';
				return $map;
			}
		);

		$this->assertSame( 'mastodon', Syn_Fediverse_Detection::software_icon( 'gotosocial', array( 'activitypub' ) ) );
	}

	public function test_software_filter_ignores_icons_that_dont_exist() {
		add_filter(
			'syn_link_fediverse_software',
			function ( $map ) {
				$map['gotosocial'] = 'no-such-icon';
				return $map;
			}
		);

		$this->assertSame( 'activitypub', Syn_Fediverse_Detection::software_icon( 'gotosocial', array( 'activitypub' ) ) );
	}

	/**
	 * The newest 2.x schema link is followed.
	 */
	public function test_follows_the_newest_schema() {
		$this->responses['https://multi.example/.well-known/nodeinfo'] = array(
			200,
			wp_json_encode(
				array(
					'links' => array(
						array(
							'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/1.0',
							'href' => 'https://multi.example/nodeinfo/1.0',
						),
						array(
							'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
							'href' => 'https://multi.example/nodeinfo/2.1',
						),
						array(
							'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
							'href' => 'https://multi.example/nodeinfo/2.0',
						),
					),
				)
			),
		);
		$this->responses['https://multi.example/nodeinfo/2.1']         = array( 200, '{"software":{"name":"Pixelfed"},"protocols":["activitypub"]}' );

		$result = Syn_Fediverse_Detection::detect( 'multi.example' );

		$this->assertSame( 'pixelfed', $result['icon'] );
		$this->assertSame( 'https://multi.example/nodeinfo/2.1', $this->requests[1][0] );
	}

	/**
	 * Requests refuse private addresses, are short and limited in size.
	 */
	public function test_requests_are_safe() {
		$this->nodeinfo( 'safe.example', 'mastodon' );

		Syn_Fediverse_Detection::detect( 'safe.example' );

		$this->assertCount( 2, $this->requests );
		foreach ( $this->requests as $request ) {
			$this->assertTrue( $request[1]['reject_unsafe_urls'] );
			$this->assertLessThanOrEqual( 5, $request[1]['timeout'] );
			$this->assertSame( 65536, $request[1]['limit_response_size'] );
		}
	}

	/**
	 * A server without NodeInfo is cached as having no icon.
	 *
	 * @dataProvider data_no_nodeinfo
	 *
	 * @param array $responses Mocked responses.
	 */
	public function test_no_nodeinfo_caches_none( $responses ) {
		$this->responses = $responses;

		$result = Syn_Fediverse_Detection::detect( 'plain.example' );

		$this->assertSame( '', $result['icon'] );
		$this->assertNull( Syn_Fediverse_Detection::get_icon( 'plain.example' ) );
		$this->assertNotNull( Syn_Fediverse_Detection::get_host( 'plain.example' ) );
	}

	public function data_no_nodeinfo() {
		$index = 'https://plain.example/.well-known/nodeinfo';
		return array(
			'404'            => array( array() ),
			'not JSON'       => array( array( $index => array( 200, '<html>' ) ) ),
			'no links'       => array( array( $index => array( 200, '{"links":[]}' ) ) ),
			'other schema'   => array( array( $index => array( 200, '{"links":[{"rel":"http://example.com/other","href":"https://plain.example/x"}]}' ) ) ),
			'bad scheme'     => array( array( $index => array( 200, '{"links":[{"rel":"http://nodeinfo.diaspora.software/ns/schema/2.0","href":"file:///etc/passwd"}]}' ) ) ),
			'document fails' => array( array( $index => array( 200, '{"links":[{"rel":"http://nodeinfo.diaspora.software/ns/schema/2.0","href":"https://plain.example/ni"}]}' ) ) ),
		);
	}

	public function test_software_name_is_sanitized() {
		$this->responses['https://odd.example/.well-known/nodeinfo'] = array( 200, '{"links":[{"rel":"http://nodeinfo.diaspora.software/ns/schema/2.0","href":"https://odd.example/ni"}]}' );
		$this->responses['https://odd.example/ni']                   = array( 200, '{"software":{"name":"<b>Odd</b> Soft"},"protocols":[]}' );

		$result = Syn_Fediverse_Detection::detect( 'odd.example' );

		$this->assertSame( 'boddbsoft', $result['software'] );
	}

	public function test_invalid_hosts_are_not_checked() {
		$this->assertNull( Syn_Fediverse_Detection::detect( 'bad host/../x' ) );
		$this->assertNull( Syn_Fediverse_Detection::detect( 'localhost' ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_detection_off_checks_nothing() {
		update_option( Syn_Fediverse_Detection::OPTION, '0' );

		$this->assertNull( Syn_Fediverse_Detection::detect( 'off.example' ) );
		$this->assertFalse( Syn_Fediverse_Detection::maybe_schedule( 'https://off.example/@a/1' ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Cached icons are still used when detection is turned off.
	 */
	public function test_cached_icons_are_used_when_off() {
		$this->nodeinfo( 'kept.example', 'peertube' );
		Syn_Fediverse_Detection::detect( 'kept.example' );
		update_option( Syn_Fediverse_Detection::OPTION, '0' );

		$this->assertSame( 'peertube', Syn_Link_Domain_Icon_Map::url_to_name( 'https://kept.example/w/1' ) );
	}

	public function test_cache_is_not_autoloaded() {
		$this->nodeinfo( 'auto.example', 'mastodon' );
		Syn_Fediverse_Detection::detect( 'auto.example' );

		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", Syn_Fediverse_Detection::CACHE ) );
		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}

	public function test_cache_keeps_the_newest_hosts() {
		$hosts = array();
		for ( $i = 0; $i < Syn_Fediverse_Detection::MAX_HOSTS; $i++ ) {
			$hosts[ "old$i.example" ] = array(
				'software' => '',
				'icon'     => '',
				'checked'  => 1000 + $i,
			);
		}
		update_option( Syn_Fediverse_Detection::CACHE, $hosts, false );
		$this->nodeinfo( 'new.example', 'mastodon' );

		Syn_Fediverse_Detection::detect( 'new.example' );

		$cached = get_option( Syn_Fediverse_Detection::CACHE );
		$this->assertCount( Syn_Fediverse_Detection::MAX_HOSTS, $cached );
		$this->assertArrayHasKey( 'new.example', $cached );
		$this->assertArrayNotHasKey( 'old0.example', $cached );
		$this->assertArrayHasKey( 'old1.example', $cached );
	}

	/**
	 * Saving a link to an unknown host schedules a check; known ones don't.
	 */
	public function test_saving_a_link_schedules_a_check() {
		$post_id = self::factory()->post->create();

		add_post_syndication_link( $post_id, 'https://queue.example/@someone/1' );
		add_post_syndication_link( $post_id, 'https://www.facebook.com/someone/posts/1' );
		add_post_syndication_link( $post_id, 'https://mastodon.social/@someone/1' );
		add_post_syndication_link( $post_id, 'mailto:someone@example.com' );

		$this->assertNotFalse( wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( 'queue.example' ) ) );
		$this->assertFalse( wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( 'facebook.com' ) ) );
		$this->assertFalse( wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( 'mastodon.social' ) ) );
		$this->assertSame( array(), $this->requests, 'Saving makes no requests.' );
	}

	public function test_comment_links_schedule_a_check() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );

		add_comment_meta( $comment_id, 'mf2_syndication', array( 'https://comment.example/@a/1' ) );

		$this->assertNotFalse( wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( 'comment.example' ) ) );
	}

	public function test_other_meta_is_ignored() {
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, 'some_other_key', array( 'https://other.example/x' ) );

		$this->assertFalse( wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( 'other.example' ) ) );
	}

	/**
	 * A host already checked isn't checked again until the recheck time, unless it has an icon.
	 */
	public function test_cached_hosts_are_not_rescheduled() {
		update_option(
			Syn_Fediverse_Detection::CACHE,
			array(
				'fresh.example' => array(
					'software' => '',
					'icon'     => '',
					'checked'  => time(),
				),
				'stale.example' => array(
					'software' => '',
					'icon'     => '',
					'checked'  => time() - Syn_Fediverse_Detection::RECHECK - 1,
				),
			),
			false
		);
		Syn_Fediverse_Detection::flush();

		$this->assertFalse( Syn_Fediverse_Detection::maybe_schedule( 'https://fresh.example/a' ) );
		$this->assertTrue( Syn_Fediverse_Detection::maybe_schedule( 'https://stale.example/a' ) );
		$this->assertFalse( Syn_Fediverse_Detection::maybe_schedule( 'https://stale.example/b' ), 'Already scheduled.' );
	}

	public function test_cron_hook_runs_detection() {
		$this->nodeinfo( 'cron.example', 'lemmy' );

		do_action( Syn_Fediverse_Detection::HOOK, 'cron.example' );

		$this->assertSame( 'lemmy', Syn_Fediverse_Detection::get_icon( 'cron.example' ) );
	}

	public function test_setting_is_on_the_general_tab() {
		global $wp_settings_fields;
		Syn_Fediverse_Detection::admin_init();

		$this->assertArrayHasKey( Syn_Fediverse_Detection::OPTION, $wp_settings_fields['syndication_display_options']['syndication_display'] );

		ob_start();
		Syn_Fediverse_Detection::setting_callback( array( 'label_for' => Syn_Fediverse_Detection::OPTION ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( "id='" . Syn_Fediverse_Detection::OPTION . "'", $html );
		$this->assertStringContainsString( 'checked', $html );
	}
}
