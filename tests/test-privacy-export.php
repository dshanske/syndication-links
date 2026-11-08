<?php
/**
 * Tests for the personal data exporter (REVIEW.md B11).
 *
 * @package Syndication_Links
 */

class Test_Privacy_Export extends WP_UnitTestCase {

	public function test_exporter_is_registered_with_a_callable() {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );

		$this->assertArrayHasKey( 'syndication-links', $exporters );
		$this->assertIsCallable( $exporters['syndication-links']['callback'] );
	}

	public function test_exports_a_commenters_links_as_strings() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'commenter@example.com',
			)
		);
		add_comment_syndication_link( $comment_id, array( 'https://one.example/1', 'https://two.example/2' ) );
		self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'commenter@example.com',
			)
		);

		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$result    = call_user_func( $exporters['syndication-links']['callback'], 'commenter@example.com', 1 );

		$this->assertTrue( $result['done'] );
		$this->assertCount( 1, $result['data'], 'Only comments with links are exported.' );
		$item = $result['data'][0];
		$this->assertSame( 'comments', $item['group_id'] );
		$this->assertSame( "comment-{$comment_id}", $item['item_id'] );
		$this->assertEqualsCanonicalizing(
			array( 'https://one.example/1', 'https://two.example/2' ),
			wp_list_pluck( $item['data'], 'value' )
		);
		foreach ( $item['data'] as $datum ) {
			$this->assertIsString( $datum['value'] );
		}
	}

	public function test_exports_nothing_for_another_email() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::factory()->post->create(),
				'comment_author_email' => 'commenter@example.com',
			)
		);
		add_comment_syndication_link( $comment_id, 'https://one.example/1' );

		$result = Syn_Meta::data_exporter( 'someone@example.com' );

		$this->assertSame( array(), $result['data'] );
		$this->assertTrue( $result['done'] );
	}
}
