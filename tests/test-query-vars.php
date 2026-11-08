<?php
/**
 * Tests for the original-of and syndication query vars (REVIEW.md P6).
 *
 * @package Syndication_Links
 */

class Test_Query_Vars extends WP_UnitTestCase {

	/**
	 * Posts with GitHub and example.com links.
	 *
	 * @var int[]
	 */
	private static $github;
	private static $other;
	private static $longer;
	private static $page;
	private static $legacy;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$github = $factory->post->create();
		add_post_syndication_link( self::$github, 'https://github.com/someone/repo' );
		self::$other = $factory->post->create();
		add_post_syndication_link( self::$other, 'https://www.example.com/post/1' );
		self::$longer = $factory->post->create();
		add_post_syndication_link( self::$longer, 'https://www.example.com/post/12' );
		self::$page = $factory->post->create( array( 'post_type' => 'page' ) );
		add_post_syndication_link( self::$page, 'https://www.example.com/page/1' );
		self::$legacy = $factory->post->create();
		update_post_meta( self::$legacy, 'syndication_urls', "https://legacy.example/1\nhttps://legacy.example/2" );
	}

	public function set_up() {
		parent::set_up();
		update_option( 'syndication_post_types', array( 'page' ) );
	}

	/**
	 * @dataProvider data_originals
	 *
	 * @param string $url  The syndicated URL.
	 * @param string $post Which post has it.
	 */
	public function test_find_original( $url, $post ) {
		$this->assertSame( self::$$post, Syn_Meta::find_original( $url ) );
	}

	public function data_originals() {
		return array(
			'exact, not the longer URL' => array( 'https://www.example.com/post/1', 'other' ),
			'the longer URL'            => array( 'https://www.example.com/post/12', 'longer' ),
			'a page'                    => array( 'https://www.example.com/page/1', 'page' ),
			'legacy meta'               => array( 'https://legacy.example/2', 'legacy' ),
		);
	}

	public function test_find_original_ignores_a_prefix() {
		$this->assertSame( 0, Syn_Meta::find_original( 'https://www.example.com/post/' ) );
		$this->assertSame( 0, Syn_Meta::find_original( 'https://legacy.example/' ) );
	}

	public function test_find_original_ignores_disabled_post_types() {
		update_option( 'syndication_post_types', array() );

		$this->assertSame( 0, Syn_Meta::find_original( 'https://www.example.com/page/1' ) );
	}

	public function test_original_of_redirects_to_the_exact_match() {
		$redirect = null;
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$redirect ) {
				$redirect = $location;
				throw new Exception( 'redirected' );
			}
		);

		try {
			$this->go_to( home_url( '/?original-of=' . rawurlencode( 'https://www.example.com/post/1' ) ) );
		} catch ( Exception $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}

		$this->assertSame( get_permalink( self::$other ), $redirect );
	}

	public function test_query_vars_are_registered() {
		$vars = apply_filters( 'query_vars', array() );

		$this->assertContains( 'original-of', $vars );
		$this->assertContains( 'syndication', $vars );
	}

	/**
	 * @dataProvider data_unknown_originals
	 *
	 * @param string $url The original-of value.
	 */
	public function test_unknown_original_is_a_404( $url ) {
		$this->go_to( home_url( '/?original-of=' . rawurlencode( $url ) ) );

		$this->assertTrue( is_404() );
	}

	public function data_unknown_originals() {
		return array(
			'not a URL'      => array( 'not a url' ),
			'no post has it' => array( 'https://nowhere.example/1' ),
		);
	}

	public function test_original_of_leaves_other_queries_alone() {
		$query = new WP_Query(
			array(
				'original-of' => 'not a url',
				'post__in'    => array( self::$github ),
			)
		);

		$this->assertFalse( $query->is_404() );
		$this->assertSame( array( self::$github ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_syndication_filters_the_main_query() {
		$this->go_to( home_url( '/?syndication=github.com' ) );

		$this->assertSame( array( self::$github ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_syndication_leaves_secondary_queries_alone() {
		$this->go_to( home_url( '/?syndication=github.com' ) );

		$query = new WP_Query( array( 'post__in' => array( self::$github, self::$other ) ) );

		$this->assertEqualsCanonicalizing( array( self::$github, self::$other ), wp_list_pluck( $query->posts, 'ID' ) );
	}
}
