<?php
class SLinksTest extends WP_UnitTestCase {

	public function test_set_and_retrieve_syndication_link() {
		$post_id = self::factory()->post->create();
		$link    = 'http://www.example.com';
		add_post_syndication_link( $post_id, $link );
		$get = get_post_syndication_links_data( $post_id );
		$this->assertEquals( array( $link ), $get );
	}

	public function test_set_and_retrieve_syndication_links() {
		$post_id = self::factory()->post->create();
		$link    = array( 'http://www.example.com', 'http://www.example2.com' );
		add_post_syndication_link( $post_id, $link );
		$get = get_post_syndication_links_data( $post_id );
		$this->assertEquals( $link, $get );
	}

	public function test_add_two_syndication_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'http://www.example.com' );
		add_post_syndication_link( $post_id, 'http://www2.example.com' );
		$get = get_post_syndication_links_data( $post_id );
		$this->assertEquals( array( 'http://www.example.com', 'http://www2.example.com' ), $get );
	}

	public function test_replace_syndication_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'http://www.example.com' );
		add_post_syndication_link( $post_id, 'http://www2.example.com', true );
		$get = get_post_syndication_links_data( $post_id );
		$this->assertEquals( array( 'http://www2.example.com' ), $get );
	}


}
