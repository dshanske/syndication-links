<?php
/**
 * Tests storing the syndication links of comments received by webmention (#103).
 *
 * @package Syndication_Links
 */

class Test_Webmention_Comments extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( Syn_Webmention_Comments::OPTION );
		parent::tear_down();
	}

	/**
	 * A stand-in for the Webmention plugin's handler, holding a parsed item.
	 *
	 * @param array $raw The parsed microformats item.
	 * @return object
	 */
	private function handler( $raw ) {
		$item = new class( $raw ) {
			/**
			 * The parsed item.
			 *
			 * @var array
			 */
			private $raw;

			/**
			 * Stores the item.
			 *
			 * @param array $raw The parsed item.
			 */
			public function __construct( $raw ) {
				$this->raw = $raw;
			}

			/**
			 * Returns the parsed item.
			 *
			 * @return array
			 */
			public function get_raw() {
				return $this->raw;
			}
		};
		return new class( $item ) {
			/**
			 * The item.
			 *
			 * @var object
			 */
			private $item;

			/**
			 * Stores the item.
			 *
			 * @param object $item The item.
			 */
			public function __construct( $item ) {
				$this->item = $item;
			}

			/**
			 * Returns the item.
			 *
			 * @return object
			 */
			public function get_webmention_item() {
				return $this->item;
			}
		};
	}

	public function test_the_filter_is_hooked_only_when_the_setting_is_on() {
		remove_filter( 'webmention_handler_mf2_set_properties', array( 'Syn_Webmention_Comments', 'set_properties' ), 10 );
		Syn_Webmention_Comments::init();
		$this->assertFalse( has_filter( 'webmention_handler_mf2_set_properties', array( 'Syn_Webmention_Comments', 'set_properties' ) ) );

		update_option( Syn_Webmention_Comments::OPTION, '1' );
		Syn_Webmention_Comments::init();
		$this->assertSame( 10, has_filter( 'webmention_handler_mf2_set_properties', array( 'Syn_Webmention_Comments', 'set_properties' ) ) );
	}

	public function test_the_replys_syndication_links_are_added_to_the_comment_meta() {
		$handler = $this->handler(
			array(
				'type'       => array( 'h-entry' ),
				'properties' => array(
					'syndication' => array(
						'https://mastodon.example/@them/1',
						array(
							'type'  => array( 'h-cite' ),
							'value' => 'https://bsky.app/profile/them/post/2',
						),
						'not a url',
					),
				),
			)
		);

		$meta = Syn_Webmention_Comments::set_properties( array( 'geo_address' => 'Somewhere' ), $handler );

		$this->assertSame(
			array(
				'geo_address'     => 'Somewhere',
				'mf2_syndication' => array( 'https://mastodon.example/@them/1', 'https://bsky.app/profile/them/post/2' ),
			),
			$meta
		);
	}

	public function test_a_reply_without_syndication_links_adds_nothing() {
		$handler = $this->handler( array( 'properties' => array( 'content' => array( 'A reply' ) ) ) );

		$this->assertSame( array(), Syn_Webmention_Comments::set_properties( array(), $handler ) );
	}

	public function test_an_unexpected_handler_is_left_alone() {
		$this->assertSame( array( 'a' => 1 ), Syn_Webmention_Comments::set_properties( array( 'a' => 1 ), new stdClass() ) );
	}

	/**
	 * Stored this way, the links are shown like any comment's.
	 */
	public function test_stored_links_are_the_comments_links() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_meta'    => array( 'mf2_syndication' => array( 'https://mastodon.example/@them/1' ) ),
			)
		);

		$this->assertSame( array( 'https://mastodon.example/@them/1' ), get_comment_syndication_links_data( $comment_id ) );
	}
}
