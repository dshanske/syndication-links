<?php
/**
 * Tests that the plugin's meta is hidden from Custom Fields but still editable (#217).
 *
 * @package Syndication_Links
 */

class Test_Protected_Meta extends WP_UnitTestCase {

	/**
	 * The test case unregisters every meta key after each test, so register it again.
	 */
	public function set_up() {
		parent::set_up();
		Syn_Meta::register_meta();
	}

	/**
	 * @dataProvider data_protected_keys
	 *
	 * @param string $key  Meta key.
	 * @param string $type Object type.
	 */
	public function test_plugin_meta_is_protected( $key, $type ) {
		$this->assertTrue( is_protected_meta( $key, $type ) );
	}

	public function data_protected_keys() {
		return array(
			'post links'      => array( 'mf2_syndication', 'post' ),
			'comment links'   => array( 'mf2_syndication', 'comment' ),
			'legacy links'    => array( 'syndication_urls', 'post' ),
			'syndication log' => array( 'syndication_log', 'post' ),
		);
	}

	public function test_other_meta_is_not_protected() {
		$this->assertFalse( is_protected_meta( 'mf2_in-reply-to', 'post' ) );
		$this->assertFalse( is_protected_meta( 'syndication', 'post' ) );
	}

	public function test_an_editor_can_still_edit_the_links() {
		$editor  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post_id = self::factory()->post->create();

		$this->assertTrue( user_can( $editor, 'edit_post_meta', $post_id, 'mf2_syndication' ) );
	}

	public function test_a_subscriber_cannot_edit_the_links() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id    = self::factory()->post->create();

		$this->assertFalse( user_can( $subscriber, 'edit_post_meta', $post_id, 'mf2_syndication' ) );
	}

	public function test_links_can_be_saved_through_the_rest_api() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'meta' => array(
					'mf2_syndication' => array( 'https://example.com/copy/1' ),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'https://example.com/copy/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}
}
