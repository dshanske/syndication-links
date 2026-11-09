<?php
/**
 * Tests for adding Simple Icons that aren't bundled (#363).
 *
 * @package Syndication_Links
 */

class Test_Simple_Icons extends WP_UnitTestCase {

	/**
	 * Requests made, as array( url, args ).
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * Mocked responses, keyed by URL: array( code, body ).
	 *
	 * @var array[]
	 */
	private $responses = array();

	/**
	 * A Simple Icons SVG, as the package ships it.
	 *
	 * @var string
	 */
	const SVG = '<svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>Untappd</title><path d="M8.29 0C3.71 0 0 3.71 0 8.29v7.42C0 20.29 3.71 24 8.29 24h7.42C20.29 24 24 20.29 24 15.71V8.29C24 3.71 20.29 0 15.71 0z"/></svg>';

	public function set_up() {
		parent::set_up();
		$this->requests  = array();
		$this->responses = array(
			$this->source( 'untappd' ) => array( 200, self::SVG ),
			$this->source( 'imdb' )    => array( 200, self::SVG ),
		);
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
		delete_option( Syn_Simple_Icons::ADDED );
	}

	public function tear_down() {
		foreach ( array_keys( Syn_Simple_Icons::added() ) as $slug ) {
			Syn_Simple_Icons::remove( $slug );
		}
		delete_option( Syn_Simple_Icons::ADDED );
		parent::tear_down();
	}

	/**
	 * The pinned GitHub API URL of a Simple Icons SVG.
	 *
	 * @param string $slug The slug.
	 * @return string
	 */
	private function source( $slug ) {
		return 'https://api.github.com/repos/simple-icons/simple-icons/contents/icons/' . $slug . '.svg?ref=' . simpleicons_syn_version();
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
			'headers'  => new $dictionary( array() ),
			'body'     => $response[1],
			'response' => array(
				'code'    => $response[0],
				'message' => get_status_header_desc( $response[0] ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function test_version_is_recorded_by_the_build() {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', simpleicons_syn_version() );
	}

	/**
	 * The catalog has every Simple Icon, with its title and colour, and is bundled.
	 */
	public function test_catalog() {
		$catalog = Syn_Simple_Icons::catalog();

		$this->assertGreaterThan( 3000, count( $catalog['icons'] ) );
		$this->assertSame( array( 'Untappd', '#FFC000' ), $catalog['icons']['untappd'] );
		$this->assertSame( array( 'Last.fm', '#D51007' ), $catalog['icons']['lastdotfm'] );
		$this->assertSame( 'eleventy', $catalog['keys']['11ty'] );
	}

	/**
	 * Matching tries the domain's name, the domain with dots spelled out, and other names. It makes no requests.
	 *
	 * @dataProvider data_matches
	 *
	 * @param string      $host Host.
	 * @param string|null $slug Expected slug.
	 */
	public function test_match( $host, $slug ) {
		$this->assertSame( $slug, Syn_Simple_Icons::match( $host ) );
		$this->assertSame( array(), $this->requests );
	}

	public function data_matches() {
		return array(
			'name'       => array( 'untappd.com', 'untappd' ),
			'subdomain'  => array( 'www2.untappd.com', 'untappd' ),
			'dotted'     => array( 'last.fm', 'lastdotfm' ),
			'other name' => array( '11ty.dev', 'eleventy' ),
			'no match'   => array( 'nothing-like-it.example', null ),
		);
	}

	/**
	 * The SVG is rebuilt from its viewBox and paths; nothing else survives.
	 */
	public function test_sanitize_svg() {
		$dirty = '<svg viewBox="0 0 24 24" onload="alert(1)" xmlns="http://www.w3.org/2000/svg"><title>x</title><script>alert(1)</script><style>*{}</style><a href="javascript:x"><path d="M0 0h24v24H0z" onclick="x"/></a></svg>';

		$this->assertSame( '<svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z"/></svg>' . "\n", Syn_Simple_Icons::sanitize_svg( $dirty ) );
	}

	/**
	 * @dataProvider data_bad_svgs
	 *
	 * @param string $svg SVG.
	 */
	public function test_sanitize_svg_rejects( $svg ) {
		$this->assertNull( Syn_Simple_Icons::sanitize_svg( $svg ) );
	}

	public function data_bad_svgs() {
		return array(
			'not SVG'       => array( '<html><body>Not found</body></html>' ),
			'no paths'      => array( '<svg viewBox="0 0 24 24"></svg>' ),
			'bad viewBox'   => array( '<svg viewBox="0 0 24 24\" onload=\"x"><path d="M0 0"/></svg>' ),
			'bad path data' => array( '<svg viewBox="0 0 24 24"><path d="M0 0&quot;/><script>"/></svg>' ),
		);
	}

	public function test_add_stores_the_icon() {
		$this->assertTrue( Syn_Simple_Icons::add( 'untappd' ) );

		$file = Syn_Simple_Icons::dir() . '/untappd.svg';
		$this->assertFileExists( $file );
		$this->assertStringNotContainsString( '<title>', file_get_contents( $file ) );
		$added = Syn_Simple_Icons::added();
		$this->assertSame( 'Untappd', $added['untappd']['title'] );
		$this->assertSame( '#FFC000', $added['untappd']['hex'] );
		$this->assertSame( simpleicons_syn_version(), $added['untappd']['version'] );
		$request = end( $this->requests );
		$this->assertSame( $this->source( 'untappd' ), $request[0] );
		$this->assertSame( 'application/vnd.github.raw', $request[1]['headers']['Accept'] );
		$this->assertTrue( $request[1]['reject_unsafe_urls'] );
	}

	/**
	 * An added icon is shown like a bundled one, with its title and colour.
	 */
	public function test_added_icon_is_shown() {
		$this->assertNotContains( Syn_Simple_Icons::dir(), Syn_Link_Domain_Icon_Map::get_icon_file_dirs() );

		Syn_Simple_Icons::add( 'untappd' );

		$this->assertContains( Syn_Simple_Icons::dir(), Syn_Link_Domain_Icon_Map::get_icon_file_dirs() );
		$this->assertSame( 'untappd', Syn_Link_Domain_Icon_Map::url_to_name( 'https://untappd.com/someone/film/x/' ) );
		$this->assertSame( 'Untappd', Syn_Link_Domain_Icon_Map::get_title( 'untappd' ) );
		$this->assertSame( '#FFC000', Syn_Link_Domain_Icon_Map::get_color( 'untappd' ) );
		$this->assertStringContainsString( 'style="--syn-icon-color: #FFC000;"', Syn_Link_Domain_Icon_Map::get_icon( 'untappd' ) );
	}

	/**
	 * A bundled icon isn't downloaded; the host whose name doesn't match it is mapped to it.
	 */
	public function test_bundled_icon_is_only_mapped() {
		$this->assertSame( 'website', Syn_Link_Domain_Icon_Map::url_to_name( 'https://www.last.fm/user/someone' ) );

		$this->assertTrue( Syn_Simple_Icons::add( 'lastdotfm', 'www.last.fm' ) );

		$this->assertSame( array(), $this->requests );
		$this->assertFileDoesNotExist( Syn_Simple_Icons::dir() . '/lastdotfm.svg' );
		$this->assertSame( array( 'last.fm' ), Syn_Simple_Icons::added()['lastdotfm']['hosts'] );
		$this->assertSame( 'lastdotfm', Syn_Link_Domain_Icon_Map::url_to_name( 'https://www.last.fm/user/someone' ) );
		$this->assertWPError( Syn_Simple_Icons::add( 'lastdotfm' ), 'Nothing to do without a host.' );
	}

	public function test_add_errors() {
		$this->assertWPError( Syn_Simple_Icons::add( '../evil' ) );
		$this->assertWPError( Syn_Simple_Icons::add( 'notinsimpleicons' ) );

		$this->responses[ $this->source( 'trakt' ) ] = array( 200, '<html>' );
		$this->assertWPError( Syn_Simple_Icons::add( 'trakt' ) );
		$this->assertFileDoesNotExist( Syn_Simple_Icons::dir() . '/trakt.svg' );
		$this->assertArrayNotHasKey( 'trakt', Syn_Simple_Icons::added() );
	}

	/**
	 * Error notices come from a fixed list, so the URL can't put text on the page.
	 */
	public function test_error_notice_is_from_a_fixed_list() {
		$_GET['syn_icon_error'] = 'syn_simple_icons_svg';
		ob_start();
		Syn_Simple_Icons::notices();
		$known = ob_get_clean();

		$_GET['syn_icon_error'] = '<b>made up</b>';
		ob_start();
		Syn_Simple_Icons::notices();
		$unknown = ob_get_clean();
		unset( $_GET['syn_icon_error'] );

		$this->assertStringContainsString( 'The icon could not be downloaded.', $known );
		$this->assertSame( '', $unknown );
	}

	public function test_remove() {
		Syn_Simple_Icons::add( 'untappd' );

		$this->assertTrue( Syn_Simple_Icons::remove( 'untappd' ) );
		$this->assertFileDoesNotExist( Syn_Simple_Icons::dir() . '/untappd.svg' );
		$this->assertSame( array(), Syn_Simple_Icons::added() );
		$this->assertFalse( Syn_Simple_Icons::remove( 'untappd' ) );
	}

	public function test_request_needs_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->expectException( 'WPDieException' );

		Syn_Simple_Icons::handle_request();
	}

	public function test_request_needs_a_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['_wpnonce'] = 'wrong';
		$this->expectException( 'WPDieException' );

		try {
			Syn_Simple_Icons::handle_request();
		} finally {
			unset( $_REQUEST['_wpnonce'] );
		}
	}

	/**
	 * The Icons tab offers an Add button for each site that matches, and lists added icons.
	 */
	public function test_icons_tab() {
		remove_all_actions( 'added_post_meta' );
		remove_all_actions( 'updated_post_meta' );
		delete_transient( Syn_Missing_Icons::CACHE );
		update_post_meta( self::factory()->post->create(), 'mf2_syndication', array( 'https://untappd.example/a/', 'https://nomatch-site.example/' ) );

		ob_start();
		Syn_Missing_Icons::render();
		$after = ob_get_clean();
		$this->assertStringContainsString( 'name="do" value="add"', $after );
		$this->assertStringContainsString( 'name="slug" value="untappd"', $after );
		$this->assertStringContainsString( 'Add the Untappd icon', $after );
		$this->assertStringContainsString( 'No match', $after );
		$this->assertSame( array(), $this->requests, 'Showing matches downloads nothing.' );

		Syn_Simple_Icons::add( 'untappd', 'untappd.example' );
		ob_start();
		Syn_Missing_Icons::render();
		$added = ob_get_clean();
		$this->assertStringNotContainsString( '<td>untappd.example</td>', $added );
		$this->assertStringContainsString( 'name="do" value="remove"', $added );
		delete_transient( Syn_Missing_Icons::CACHE );
	}
}
