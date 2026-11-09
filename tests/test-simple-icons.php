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
			$this->cdn( 'data/simple-icons.json' ) => array(
				200,
				wp_json_encode(
					array(
						array(
							'title'  => 'Untappd',
							'hex'    => 'FFC000',
							'source' => 'https://untappd.com/about/brand/',
						),
						array(
							'title'  => 'Dotted.io',
							'hex'    => 'D51007',
							'source' => 'https://dotted.example/',
						),
						array(
							'title'   => 'Example Brand',
							'hex'     => '123456',
							'source'  => 'https://brand.example/',
							'aliases' => array( 'aka' => array( 'Old Name' ) ),
						),
						array(
							'title'  => 'Has Slug',
							'slug'   => 'custom_slug',
							'hex'    => 'ABCDEF',
							'source' => 'https://slug.example/',
						),
						array( 'title' => 'No colour' ),
					)
				),
			),
			$this->cdn( 'icons/untappd.svg' )      => array( 200, self::SVG ),
			$this->cdn( 'icons/dotteddotio.svg' )  => array( 200, self::SVG ),
		);
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
		delete_option( Syn_Simple_Icons::INDEX );
		delete_option( Syn_Simple_Icons::ADDED );
	}

	public function tear_down() {
		foreach ( array_keys( Syn_Simple_Icons::added() ) as $slug ) {
			Syn_Simple_Icons::remove( $slug );
		}
		delete_option( Syn_Simple_Icons::INDEX );
		delete_option( Syn_Simple_Icons::ADDED );
		parent::tear_down();
	}

	/**
	 * The pinned jsDelivr URL of a file in the Simple Icons package.
	 *
	 * @param string $file The file.
	 * @return string
	 */
	private function cdn( $file ) {
		return 'https://cdn.jsdelivr.net/npm/simple-icons@' . simpleicons_syn_version() . '/' . $file;
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

	public function test_nothing_is_fetched_until_asked() {
		$this->assertNull( Syn_Simple_Icons::index() );
		$this->assertNull( Syn_Simple_Icons::match( 'untappd.com' ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_fetch_index() {
		$index = Syn_Simple_Icons::fetch_index();

		$this->assertSame( array( 'Untappd', '#FFC000' ), $index['icons']['untappd'] );
		$this->assertSame( array( 'Dotted.io', '#D51007' ), $index['icons']['dotteddotio'] );
		$this->assertArrayHasKey( 'custom_slug', $index['icons'] );
		$this->assertArrayNotHasKey( 'nocolour', $index['icons'] );
		$this->assertSame( $this->cdn( 'data/simple-icons.json' ), $this->requests[0][0] );
		$this->assertTrue( $this->requests[0][1]['reject_unsafe_urls'] );
		$this->assertSame( $index, Syn_Simple_Icons::index() );
	}

	/**
	 * Matching tries the domain's name, the domain with dots spelled out, and aliases.
	 *
	 * @dataProvider data_matches
	 *
	 * @param string      $host Host.
	 * @param string|null $slug Expected slug.
	 */
	public function test_match( $host, $slug ) {
		Syn_Simple_Icons::fetch_index();

		$this->assertSame( $slug, Syn_Simple_Icons::match( $host ) );
	}

	public function data_matches() {
		return array(
			'name'      => array( 'untappd.com', 'untappd' ),
			'dotted'    => array( 'dotted.io', 'dotteddotio' ),
			'title'     => array( 'examplebrand.com', 'examplebrand' ),
			'alias'     => array( 'oldname.net', 'examplebrand' ),
			'subdomain' => array( 'www2.untappd.com', 'untappd' ),
			'no match'  => array( 'nothing.example', null ),
		);
	}

	public function test_index_for_another_version_is_ignored() {
		$index            = Syn_Simple_Icons::fetch_index();
		$index['version'] = '1.0.0';
		update_option( Syn_Simple_Icons::INDEX, $index, false );

		$this->assertNull( Syn_Simple_Icons::index() );
	}

	public function test_failed_download_is_an_error() {
		$this->responses = array();

		$this->assertWPError( Syn_Simple_Icons::fetch_index() );
		$this->assertNull( Syn_Simple_Icons::index() );
	}

	public function test_title_to_slug() {
		$this->assertSame( 'lastdotfm', Syn_Simple_Icons::title_to_slug( 'Last.fm' ) );
		$this->assertSame( 'googleplus', Syn_Simple_Icons::title_to_slug( 'Google+' ) );
		$this->assertSame( 'cafe', Syn_Simple_Icons::title_to_slug( 'Café' ) );
		$this->assertSame( 'atandt', Syn_Simple_Icons::title_to_slug( 'AT&T' ) );
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
		$this->assertSame( $this->cdn( 'icons/untappd.svg' ), end( $this->requests )[0] );
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
	 * A host whose name doesn't match the icon's is mapped to it.
	 */
	public function test_added_icon_is_mapped_to_its_host() {
		Syn_Simple_Icons::add( 'dotteddotio', 'www.dotted.io' );

		$this->assertSame( array( 'dotted.io' ), Syn_Simple_Icons::added()['dotteddotio']['hosts'] );
		$this->assertSame( 'dotteddotio', Syn_Link_Domain_Icon_Map::url_to_name( 'https://www.dotted.io/user/someone' ) );
	}

	public function test_add_errors() {
		$this->assertWPError( Syn_Simple_Icons::add( '../evil' ) );
		$this->assertWPError( Syn_Simple_Icons::add( 'notinsimpleicons' ) );

		$this->responses[ $this->cdn( 'icons/examplebrand.svg' ) ] = array( 200, '<html>' );
		$this->assertWPError( Syn_Simple_Icons::add( 'examplebrand' ) );
		$this->assertFileDoesNotExist( Syn_Simple_Icons::dir() . '/examplebrand.svg' );
		$this->assertArrayNotHasKey( 'examplebrand', Syn_Simple_Icons::added() );
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
	 * The Icons tab offers the look-up first, then an Add button for each match.
	 */
	public function test_icons_tab() {
		remove_all_actions( 'added_post_meta' );
		remove_all_actions( 'updated_post_meta' );
		delete_transient( Syn_Missing_Icons::CACHE );
		update_post_meta( self::factory()->post->create(), 'mf2_syndication', array( 'https://untappd.com/a/', 'https://nomatch-site.example/' ) );

		ob_start();
		Syn_Missing_Icons::render();
		$before = ob_get_clean();
		$this->assertStringContainsString( 'name="do" value="lookup"', $before );
		$this->assertStringNotContainsString( 'name="do" value="add"', $before );

		Syn_Simple_Icons::fetch_index();
		ob_start();
		Syn_Missing_Icons::render();
		$after = ob_get_clean();
		$this->assertStringContainsString( 'name="do" value="add"', $after );
		$this->assertStringContainsString( 'name="slug" value="untappd"', $after );
		$this->assertStringContainsString( 'Add the Untappd icon', $after );
		$this->assertStringContainsString( 'No match', $after );

		Syn_Simple_Icons::add( 'untappd', 'untappd.com' );
		ob_start();
		Syn_Missing_Icons::render();
		$added = ob_get_clean();
		$this->assertStringNotContainsString( '<td>untappd.com</td>', $added );
		$this->assertStringContainsString( 'name="do" value="remove"', $added );
		delete_transient( Syn_Missing_Icons::CACHE );
	}
}
