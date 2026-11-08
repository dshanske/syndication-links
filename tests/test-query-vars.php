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

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$github = $factory->post->create();
		add_post_syndication_link( self::$github, 'https://github.com/someone/repo' );
		self::$other = $factory->post->create();
		add_post_syndication_link( self::$other, 'https://www.example.com/post/1' );
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
