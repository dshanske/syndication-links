<?php
/**
 * Tests for saving syndication links from the post and comment metaboxes.
 *
 * @package Syndication_Links
 */

class Test_Link_Saving extends WP_UnitTestCase {

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * A subscriber, who can't edit posts.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
		$_POST = array();
	}

	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Fills $_POST as the metabox form would.
	 *
	 * @param array $urls The submitted syndication_urls[] values.
	 */
	protected function submit( $urls ) {
		$_POST['syn_metabox_nonce'] = wp_create_nonce( 'syn_metabox' );
		$_POST['syndication_urls']  = $urls;
	}

	public function test_save_handlers_are_hooked() {
		$this->assertSame( 11, has_action( 'save_post', array( 'Syn_Meta', 'save_post_meta' ) ) );
		$this->assertSame( 10, has_action( 'edit_comment', array( 'Syn_Meta', 'save_comment_meta' ) ) );
	}

	public function test_saving_stores_cleaned_links() {
		$post_id = self::factory()->post->create();
		$this->submit( array( 'https://a.example/1', '', 'not a url', ' http://www.facebook.com/someone/posts/1 ' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertSame(
			array( 'https://a.example/1', 'https://www.facebook.com/someone/posts/1' ),
			get_post_meta( $post_id, 'mf2_syndication', true )
		);
	}

	public function test_saving_replaces_existing_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://old.example/1', 'https://old.example/2' ) );
		$this->submit( array( 'https://new.example/1' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertSame( array( 'https://new.example/1' ), get_post_syndication_links_data( $post_id ) );
	}

	public function test_saving_an_empty_list_deletes_the_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://old.example/1' );
		$this->submit( array( '' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'mf2_syndication' ) );
	}

	/**
	 * A value that isn't a list leaves the links alone, instead of a fatal error.
	 */
	public function test_saving_a_non_array_keeps_the_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://old.example/1' );
		$this->submit( 'https://a.example/1' );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertSame( array( 'https://old.example/1' ), get_post_syndication_links_data( $post_id ) );
	}

	/**
	 * Nested arrays in the submitted list are ignored.
	 */
	public function test_saving_ignores_nested_values() {
		$post_id = self::factory()->post->create();
		$this->submit( array( 'https://a.example/1', array( 'https://b.example/2' ) ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertSame( array( 'https://a.example/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	/**
	 * A save without the metabox fields (e.g. Quick Edit) leaves the links alone.
	 */
	public function test_saving_without_the_field_keeps_the_links() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://old.example/1' );
		$_POST['syn_metabox_nonce'] = wp_create_nonce( 'syn_metabox' );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertSame( array( 'https://old.example/1' ), get_post_syndication_links_data( $post_id ) );
	}

	public function test_saving_without_a_nonce_does_nothing() {
		$post_id                   = self::factory()->post->create();
		$_POST['syndication_urls'] = array( 'https://a.example/1' );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'mf2_syndication' ) );
	}

	public function test_saving_with_a_bad_nonce_does_nothing() {
		$post_id = self::factory()->post->create();
		$this->submit( array( 'https://a.example/1' ) );
		$_POST['syn_metabox_nonce'] = 'not-a-nonce';

		Syn_Meta::save_post_meta( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'mf2_syndication' ) );
	}

	public function test_saving_needs_permission_to_edit_the_post() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$admin_id ) );
		wp_set_current_user( self::$subscriber_id );
		$this->submit( array( 'https://a.example/1' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'mf2_syndication' ) );
	}

	public function test_saving_needs_permission_to_edit_the_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => self::$admin_id,
			)
		);
		wp_set_current_user( self::$subscriber_id );
		$this->submit( array( 'https://a.example/1' ) );
		$_POST['post_type'] = 'page';

		Syn_Meta::save_post_meta( $page_id );

		$this->assertFalse( metadata_exists( 'post', $page_id, 'mf2_syndication' ) );
	}

	public function test_saving_a_page() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->submit( array( 'https://a.example/1' ) );
		$_POST['post_type'] = 'page';

		Syn_Meta::save_post_meta( $page_id );

		$this->assertSame( array( 'https://a.example/1' ), get_post_syndication_links_data( $page_id ) );
	}

	public function test_saving_comment_links() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		$this->submit( array( 'https://a.example/1', 'https://b.example/2' ) );

		Syn_Meta::save_comment_meta( $comment_id );

		$this->assertSame(
			array( 'https://a.example/1', 'https://b.example/2' ),
			get_comment_syndication_links_data( $comment_id )
		);
	}

	/**
	 * A user who can't edit the comment can't change its links (#252).
	 */
	public function test_saving_comment_links_needs_permission_to_edit_the_comment() {
		$post_id    = self::factory()->post->create( array( 'post_author' => self::$admin_id ) );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		wp_set_current_user( self::$subscriber_id );
		$this->submit( array( 'https://a.example/1' ) );

		Syn_Meta::save_comment_meta( $comment_id );

		$this->assertFalse( metadata_exists( 'comment', $comment_id, 'mf2_syndication' ) );
	}

	public function test_saving_comment_links_without_a_nonce_does_nothing() {
		$comment_id                = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		$_POST['syndication_urls'] = array( 'https://a.example/1' );

		Syn_Meta::save_comment_meta( $comment_id );

		$this->assertFalse( metadata_exists( 'comment', $comment_id, 'mf2_syndication' ) );
	}
	/**
	 * Fills $_POST as the metabox form would, including the links it loaded.
	 *
	 * @param array $urls   The submitted syndication_urls[] values.
	 * @param array $loaded The links the form was loaded with.
	 */
	protected function submit_from( $urls, $loaded ) {
		$this->submit( $urls );
		$_POST['syndication_urls_loaded'] = wp_slash( wp_json_encode( $loaded ) );
	}

	/**
	 * A link added after the form was loaded survives a save from that form (#216).
	 */
	public function test_saving_a_stale_form_keeps_links_added_since() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://a.example/1' );
		// Syndication adds a link after the editor loaded the box.
		add_post_syndication_link( $post_id, 'https://copy.example/1' );
		$this->submit_from( array( 'https://a.example/1' ), array( 'https://a.example/1' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertEqualsCanonicalizing( array( 'https://a.example/1', 'https://copy.example/1' ), get_post_syndication_links_data( $post_id ) );
	}

	public function test_saving_applies_the_forms_removals_and_additions() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, array( 'https://a.example/1', 'https://b.example/2' ) );
		add_post_syndication_link( $post_id, 'https://copy.example/1' );
		$this->submit_from( array( 'https://a.example/1', 'https://new.example/1' ), array( 'https://a.example/1', 'https://b.example/2' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertEqualsCanonicalizing(
			array( 'https://a.example/1', 'https://new.example/1', 'https://copy.example/1' ),
			get_post_syndication_links_data( $post_id )
		);
	}

	public function test_removing_every_link_deletes_them() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://a.example/1' );
		$this->submit_from( array( '' ), array( 'https://a.example/1' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'mf2_syndication' ) );
	}

	/**
	 * Links another plugin adds aren't saved, so removing them there removes them (#212).
	 */
	public function test_other_plugins_links_are_not_saved() {
		$post_id = self::factory()->post->create();
		add_post_syndication_link( $post_id, 'https://a.example/1' );
		$filter = function ( $urls ) {
			$urls[] = 'https://other.example/1';
			return $urls;
		};
		add_filter( 'get_post_syndication_links', $filter );
		$this->submit_from( array( 'https://a.example/1' ), array( 'https://a.example/1' ) );

		Syn_Meta::save_post_meta( $post_id );
		remove_filter( 'get_post_syndication_links', $filter );

		$this->assertSame( array( 'https://a.example/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
	}

	/**
	 * The box shows legacy links as its own, so saving moves them into mf2_syndication, and removing one sticks.
	 */
	public function test_saving_moves_the_legacy_links() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'syndication_urls', "https://old.example/1\nhttps://old.example/2" );
		$this->submit_from( array( 'https://old.example/1' ), array( 'https://old.example/1', 'https://old.example/2' ) );

		Syn_Meta::save_post_meta( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'syndication_urls' ) );
		$this->assertSame( array( 'https://old.example/1' ), get_post_syndication_links_data( $post_id ) );
	}
}
