<?php
/**
 * Tests the Pinboard provider, which saves the URL a Post Kinds post cites.
 *
 * @package Syndication_Links
 */

/*
 * Stand in for Post Kinds' public function, so the provider can be tested without Post Kinds.
 * Tests set the citation with the syn_test_post_kind_response filter.
 */
if ( ! function_exists( 'get_post_kind_response' ) ) {
	function get_post_kind_response( $post = null ) {
		return apply_filters( 'syn_test_post_kind_response', null, $post );
	}
}

class Test_Pinboard extends WP_UnitTestCase {

	/**
	 * The requests made, by URL.
	 *
	 * @var array
	 */
	private $requests = array();

	public function set_up() {
		parent::set_up();
		require_once dirname( __DIR__ ) . '/includes/apis/class-syndication-provider-pinboard.php';
		update_option( 'pinboard_token', 'someone:TOKEN' );
		$this->requests = array();
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http' ), 10 );
		delete_option( 'pinboard_token' );
		parent::tear_down();
	}

	/**
	 * Answers Pinboard's API and its bookmark lookup.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array The response.
	 */
	public function mock_http( $pre, $args, $url ) {
		$this->requests[] = $url;
		$headers          = array();
		if ( 0 === strpos( $url, 'https://pinboard.in/url/' ) ) {
			$headers['location'] = '/u:someone/b:abc123/';
		}
		return array(
			'headers'  => $headers,
			'body'     => '{"result_code":"done"}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Gives posts a citation, as Post Kinds would.
	 *
	 * @param array $cite The normalized citation.
	 */
	private function cite( $cite ) {
		add_filter(
			'syn_test_post_kind_response',
			function () use ( $cite ) {
				return array( 'cite' => $cite );
			}
		);
	}

	public function test_the_bookmark_is_added_to_the_existing_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://mastodon.example/@someone/1' );
		$this->cite(
			array(
				'url'     => 'https://example.com/article',
				'name'    => 'An article',
				'summary' => '<p>A summary.</p>',
			)
		);

		$result = ( new Syndication_Provider_Pinboard() )->posse( $post_id );

		$this->assertIsArray( $result );
		$this->assertSame(
			array( 'https://mastodon.example/@someone/1', 'https://pinboard.in/u:someone/b:abc123/' ),
			get_post_meta( $post_id, 'mf2_syndication', true )
		);
		$query = array();
		wp_parse_str( (string) wp_parse_url( $this->requests[0], PHP_URL_QUERY ), $query );
		$this->assertSame( 'https://example.com/article', $query['url'] );
		$this->assertSame( 'An article', $query['description'] );
		$this->assertSame( 'A summary.', $query['extended'] );
	}

	/**
	 * A post without tags doesn't trip over get_the_tags() returning false.
	 */
	public function test_a_post_without_tags_is_sent() {
		$post_id = self::factory()->post->create();
		$this->cite( array( 'url' => 'https://example.com/article' ) );

		$result = ( new Syndication_Provider_Pinboard() )->posse( $post_id );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $this->requests );
	}

	public function test_a_post_without_a_citation_is_not_sent() {
		$post_id = self::factory()->post->create();

		$this->assertFalse( ( new Syndication_Provider_Pinboard() )->posse( $post_id ) );
		$this->assertSame( array(), $this->requests );
	}
}
