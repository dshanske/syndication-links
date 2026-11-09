<?php
/**
 * Tests for the list of linked sites without an icon (#362).
 *
 * Each test uses its own hosts, because Syn_Link_Domain_Icon_Map caches the
 * icon of each domain for the request.
 *
 * @package Syndication_Links
 */

class Test_Missing_Icons extends WP_UnitTestCase {

	/**
	 * Messages printed by WP-CLI, as array( type, message ).
	 *
	 * @var array[]
	 */
	public $messages = array();

	public function set_up() {
		parent::set_up();
		delete_transient( Syn_Missing_Icons::CACHE );
		delete_option( Syn_Fediverse_Detection::CACHE );
		delete_option( Syn_Fediverse_Detection::OPTION );
		Syn_Fediverse_Detection::flush();
		_set_cron_array( array() );
		// Keep saving from queueing checks, so the tests control the queue.
		remove_all_actions( 'added_post_meta' );
		remove_all_actions( 'updated_post_meta' );
		remove_all_actions( 'added_comment_meta' );
		remove_all_actions( 'updated_comment_meta' );
		add_filter( 'pre_http_request', array( $this, 'no_requests' ) );
	}

	public function tear_down() {
		delete_transient( Syn_Missing_Icons::CACHE );
		delete_option( Syn_Fediverse_Detection::CACHE );
		delete_option( Syn_Fediverse_Detection::OPTION );
		Syn_Fediverse_Detection::flush();
		_set_cron_array( array() );
		parent::tear_down();
	}

	/**
	 * Fails any HTTP request: listing never fetches anything.
	 */
	public function no_requests() {
		$this->fail( 'The list made an HTTP request.' );
	}

	/**
	 * Creates a post with links.
	 *
	 * @param string[] $urls Links.
	 * @return int Post ID.
	 */
	private function post_with( $urls ) {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'mf2_syndication', $urls );
		return $post_id;
	}

	public function test_lists_hosts_with_a_generic_icon() {
		$this->post_with( array( 'https://one.example/a', 'https://www.facebook.com/a/1', 'https://one.example/b' ) );
		$this->post_with( array( 'https://two.example/a', 'https://one.example/c', 'mailto:someone@example.com' ) );

		$list = Syn_Missing_Icons::get();

		$this->assertSame( array( 'one.example', 'two.example' ), array_keys( $list['hosts'] ) );
		$this->assertSame( 3, $list['hosts']['one.example']['links'] );
		$this->assertSame( 1, $list['hosts']['two.example']['links'] );
		$this->assertSame( 'https://one.example/a', $list['hosts']['one.example']['url'] );
	}

	public function test_includes_comment_links() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		update_comment_meta( $comment_id, 'mf2_syndication', array( 'https://comment-site.example/r/1' ) );

		$this->assertArrayHasKey( 'comment-site.example', Syn_Missing_Icons::get()['hosts'] );
	}

	/**
	 * Hosts are read in batches of meta rows.
	 */
	public function test_reads_every_batch() {
		for ( $i = 0; $i <= Syn_Missing_Icons::BATCH; $i++ ) {
			add_metadata( 'post', 1, 'mf2_syndication', array( "https://batch$i.example/" ) );
		}

		$hosts = Syn_Missing_Icons::count_hosts();

		$this->assertArrayHasKey( 'batch0.example', $hosts );
		$this->assertArrayHasKey( 'batch' . Syn_Missing_Icons::BATCH . '.example', $hosts );
	}

	public function test_list_is_cached_and_refreshed() {
		$this->post_with( array( 'https://cached.example/a' ) );
		$first = Syn_Missing_Icons::get();
		$this->post_with( array( 'https://later.example/a' ) );

		$this->assertSame( array_keys( $first['hosts'] ), array_keys( Syn_Missing_Icons::get()['hosts'] ) );
		$this->assertArrayHasKey( 'later.example', Syn_Missing_Icons::get( true )['hosts'] );
		$this->assertIsArray( get_transient( Syn_Missing_Icons::CACHE ) );
	}

	/**
	 * A host that gets an icon after the list was built, for example from a
	 * Fediverse check, drops out without a refresh.
	 */
	public function test_hosts_with_an_icon_by_now_are_left_out() {
		$this->post_with( array( 'https://found.example/@a/1' ) );
		$this->assertArrayHasKey( 'found.example', Syn_Missing_Icons::get()['hosts'] );

		update_option(
			Syn_Fediverse_Detection::CACHE,
			array(
				'found.example' => array(
					'software' => 'mastodon',
					'icon'     => 'mastodon',
					'checked'  => time(),
				),
			),
			false
		);
		Syn_Fediverse_Detection::flush();
		// The icon map cached this domain's icon for the request; a new request would not have.
		$map = new ReflectionProperty( 'Syn_Link_Domain_Icon_Map', 'map' );
		$map->setAccessible( true );
		$cached = $map->getValue();
		unset( $cached['found.example'] );
		$map->setValue( null, $cached );

		$this->assertArrayNotHasKey( 'found.example', Syn_Missing_Icons::get()['hosts'] );
	}

	public function test_detect_spreads_out_the_checks() {
		$this->post_with( array( 'https://d1.example/a', 'https://d2.example/a', 'https://d2.example/b' ) );
		$now = time();

		$this->assertSame( 2, Syn_Missing_Icons::detect() );

		$first  = wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( 'd2.example' ) );
		$second = wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( 'd1.example' ) );
		$this->assertGreaterThanOrEqual( $now, $first );
		$this->assertGreaterThanOrEqual( $first + Syn_Missing_Icons::DETECT_INTERVAL, $second );
		$this->assertSame( 0, Syn_Missing_Icons::detect(), 'Already queued.' );
	}

	public function test_detect_does_nothing_when_detection_is_off() {
		update_option( Syn_Fediverse_Detection::OPTION, '0' );
		$this->post_with( array( 'https://off-site.example/a' ) );

		$this->assertSame( 0, Syn_Missing_Icons::detect() );
	}

	public function test_status() {
		update_option(
			Syn_Fediverse_Detection::CACHE,
			array(
				'none.example'  => array(
					'software' => '',
					'icon'     => '',
					'checked'  => time(),
				),
				'other.example' => array(
					'software' => 'someserver',
					'icon'     => '',
					'checked'  => time(),
				),
			),
			false
		);
		Syn_Fediverse_Detection::flush();
		wp_schedule_single_event( time(), Syn_Fediverse_Detection::HOOK, array( 'queued.example' ) );

		$this->assertSame( 'Not checked', Syn_Missing_Icons::status( 'new.example' ) );
		$this->assertSame( 'Not a Fediverse server', Syn_Missing_Icons::status( 'none.example' ) );
		$this->assertSame( 'Runs someserver, which has no icon', Syn_Missing_Icons::status( 'other.example' ) );
		$this->assertSame( 'Fediverse check queued', Syn_Missing_Icons::status( 'queued.example' ) );
	}

	/**
	 * The report link opens the icon request form with only the domain filled in.
	 */
	public function test_report_url() {
		$url = Syn_Missing_Icons::report_url( 'report.example' );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertStringStartsWith( 'https://github.com/dshanske/syndication-links/issues/new?', $url );
		$this->assertSame( 'icon-request.yml', $query['template'] );
		$this->assertSame( 'Icon request: report.example', $query['title'] );
		$this->assertSame( 'report.example', $query['domain'] );
		$this->assertFileExists( dirname( __DIR__ ) . '/.github/ISSUE_TEMPLATE/icon-request.yml' );
	}

	public function test_render_lists_the_hosts() {
		$this->post_with( array( 'https://render.example/a' ) );

		ob_start();
		Syn_Missing_Icons::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<td>render.example</td>', $html );
		$this->assertStringContainsString( 'Not checked', $html );
		$this->assertStringContainsString( 'name="do" value="refresh"', $html );
		$this->assertStringContainsString( 'name="do" value="detect"', $html );
		$this->assertStringContainsString( esc_url( Syn_Missing_Icons::report_url( 'render.example' ) ), $html );
	}

	public function test_render_without_missing_icons() {
		ob_start();
		Syn_Missing_Icons::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Every linked site has an icon.', $html );
		$this->assertStringNotContainsString( 'value="detect"', $html );
	}

	public function test_render_escapes_hosts() {
		set_transient(
			Syn_Missing_Icons::CACHE,
			array(
				'hosts' => array(
					'<b>x</b>' => array(
						'links' => 1,
						'url'   => 'https://unlisted.example/',
					),
				),
				'time'  => time(),
			),
			DAY_IN_SECONDS
		);

		ob_start();
		Syn_Missing_Icons::render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $html );
	}

	public function test_request_needs_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->expectException( 'WPDieException' );

		Syn_Missing_Icons::handle_request();
	}

	public function test_request_needs_a_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['_wpnonce'] = 'wrong';
		$this->expectException( 'WPDieException' );

		try {
			Syn_Missing_Icons::handle_request();
		} finally {
			unset( $_REQUEST['_wpnonce'] );
		}
	}

	public function test_settings_page_has_an_icons_tab() {
		ob_start();
		Syn_Config::links_options();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'tab=icons', $html );
	}

	public function test_cli_lists_hosts_as_json() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			$this->markTestSkipped( 'WP-CLI is not installed.' );
		}
		// WP-CLI loads its utility functions when it runs; the dev dependency doesn't autoload them.
		if ( ! function_exists( 'WP_CLI\\Utils\\format_items' ) ) {
			require_once dirname( __DIR__ ) . '/vendor/wp-cli/wp-cli/php/utils.php';
		}
		$this->post_with( array( 'https://cli.example/a', 'https://cli.example/b' ) );

		ob_start();
		Syn_Missing_Icons::cli( array(), array( 'format' => 'json' ) );
		$items = json_decode( ob_get_clean(), true );

		$this->assertSame( 'cli.example', $items[0]['site'] );
		$this->assertSame( 2, $items[0]['links'] );
		$this->assertSame( 'Not checked', $items[0]['status'] );
	}
}
