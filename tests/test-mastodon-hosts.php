<?php
/**
 * Tests for the cached list of the site's Mastodon hosts (REVIEW.md P4).
 *
 * @package Syndication_Links
 */

class Test_Mastodon_Hosts extends WP_UnitTestCase {

	public function tear_down() {
		delete_transient( 'syn_mastodon_hosts' );
		delete_transient( 'syn_mastodon' );
		parent::tear_down();
	}

	public function test_hosts_are_cached_with_an_expiration() {
		Syn_Link_Domain_Icon_Map::mastodon_urls();

		$this->assertSame( array(), get_transient( 'syn_mastodon_hosts' ) );
		$timeout = get_option( '_transient_timeout_syn_mastodon_hosts' );
		$this->assertNotFalse( $timeout );
		$this->assertLessThanOrEqual( time() + DAY_IN_SECONDS, (int) $timeout );
	}

	public function test_the_old_transient_without_an_expiration_is_removed() {
		set_transient( 'syn_mastodon', array( 'old.example' ) );

		$this->assertSame( array(), Syn_Link_Domain_Icon_Map::mastodon_urls() );
		$this->assertFalse( get_transient( 'syn_mastodon' ) );
	}

	/**
	 * @dataProvider data_meta_changes
	 *
	 * @param string $change add, update or delete.
	 */
	public function test_changing_a_users_mastodon_url_clears_the_cache( $change ) {
		$user_id = self::factory()->user->create();
		if ( 'add' !== $change ) {
			update_user_meta( $user_id, 'mastodon', 'https://one.example/@someone' );
		}
		set_transient( 'syn_mastodon_hosts', array( 'one.example' ), DAY_IN_SECONDS );

		if ( 'delete' === $change ) {
			delete_user_meta( $user_id, 'mastodon' );
		} else {
			update_user_meta( $user_id, 'mastodon', 'https://two.example/@someone' );
		}

		$this->assertFalse( get_transient( 'syn_mastodon_hosts' ) );
	}

	public function data_meta_changes() {
		return array(
			'add'    => array( 'add' ),
			'update' => array( 'update' ),
			'delete' => array( 'delete' ),
		);
	}

	public function test_other_user_meta_leaves_the_cache() {
		$user_id = self::factory()->user->create();
		set_transient( 'syn_mastodon_hosts', array( 'one.example' ), DAY_IN_SECONDS );

		update_user_meta( $user_id, 'description', 'Hello' );

		$this->assertSame( array( 'one.example' ), get_transient( 'syn_mastodon_hosts' ) );
	}

	public function test_deleting_a_user_clears_the_cache() {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$user_id = self::factory()->user->create();
		set_transient( 'syn_mastodon_hosts', array( 'one.example' ), DAY_IN_SECONDS );

		wp_delete_user( $user_id );

		$this->assertFalse( get_transient( 'syn_mastodon_hosts' ) );
	}
}
