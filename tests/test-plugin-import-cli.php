<?php
/**
 * Tests the `wp syndication-links import` command, with WP-CLI stood in for.
 *
 * @package Syndication_Links
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Records what the command prints. error() stops the command, as WP-CLI's does.
	 */
	class WP_CLI {
		/**
		 * Messages, as array( type, message ).
		 *
		 * @var array[]
		 */
		public static $messages = array();

		/**
		 * Records a command.
		 *
		 * @param string   $name     Command name.
		 * @param callable $callback Callback.
		 */
		public static function add_command( $name, $callback ) {
			self::$messages[] = array( 'command', $name );
		}

		/**
		 * Records an error and stops.
		 *
		 * @param string $message Message.
		 * @throws RuntimeException Always.
		 */
		public static function error( $message ) {
			self::$messages[] = array( 'error', $message );
			throw new RuntimeException( $message );
		}

		/**
		 * Records a warning.
		 *
		 * @param string $message Message.
		 */
		public static function warning( $message ) {
			self::$messages[] = array( 'warning', $message );
		}

		/**
		 * Records a success.
		 *
		 * @param string $message Message.
		 */
		public static function success( $message ) {
			self::$messages[] = array( 'success', $message );
		}
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- The WP-CLI stand-in above is only for these tests.
class Test_Plugin_Import_Cli extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		WP_CLI::$messages = array();
	}

	public function tear_down() {
		delete_option( Syn_Plugin_Import::OPTION );
		parent::tear_down();
	}

	/**
	 * Creates a post with a link recorded by Mastodon Autopost.
	 *
	 * @return int Post ID.
	 */
	private function autopost_post() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'autopostToMastodonshare-lastSuccessfullTootURL', 'https://toot.example.social/@someone/1' );
		return $post_id;
	}

	/**
	 * The messages of one type.
	 *
	 * @param string $type Message type.
	 * @return string[]
	 */
	private function messages( $type ) {
		return array_values(
			array_map(
				function ( $message ) {
					return $message[1];
				},
				array_filter(
					WP_CLI::$messages,
					function ( $message ) use ( $type ) {
						return $type === $message[0];
					}
				)
			)
		);
	}

	public function test_a_source_is_imported() {
		$post_id = $this->autopost_post();

		Syn_Plugin_Import::cli( array( 'mastodon-autopost' ), array() );

		$this->assertSame( array( 'Mastodon Autopost: imported links for 1 posts.' ), $this->messages( 'success' ) );
		$this->assertSame( array( 'https://toot.example.social/@someone/1' ), get_post_meta( $post_id, 'mf2_syndication', true ) );
		$status = Syn_Plugin_Import::statuses()['mastodon-autopost'];
		$this->assertSame( 'done', $status['status'] );
		$this->assertSame( 1, $status['posts'] );
	}

	public function test_all_imports_every_source() {
		$this->autopost_post();

		Syn_Plugin_Import::cli( array(), array( 'all' => true ) );

		$this->assertCount( count( Syn_Plugin_Import::sources() ), $this->messages( 'success' ) );
		$this->assertContains( 'Mastodon Autopost: imported links for 1 posts.', $this->messages( 'success' ) );
	}

	public function test_an_unknown_source_is_a_warning() {
		Syn_Plugin_Import::cli( array( 'nope', 'mastodon-autopost' ), array() );

		$this->assertSame( array( 'Unknown source: nope' ), $this->messages( 'warning' ) );
		$this->assertCount( 1, $this->messages( 'success' ) );
	}

	/**
	 * Without a source, it lists them.
	 */
	public function test_no_source_is_an_error_listing_them() {
		try {
			Syn_Plugin_Import::cli( array(), array() );
			$this->fail( 'Expected an error.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'mastodon-autopost', $e->getMessage() );
		}
		$this->assertSame( array(), $this->messages( 'success' ) );
	}

	/**
	 * Batches continue until a short one: a full batch and one more post here.
	 */
	public function test_every_batch_is_imported() {
		$ids = self::factory()->post->create_many( Syn_Plugin_Import::BATCH + 1 );
		foreach ( $ids as $i => $id ) {
			update_post_meta( $id, 'autopostToMastodonshare-lastSuccessfullTootURL', 'https://toot.example.social/@someone/' . $i );
		}

		Syn_Plugin_Import::cli( array( 'mastodon-autopost' ), array() );

		$this->assertSame( array( 'Mastodon Autopost: imported links for ' . ( Syn_Plugin_Import::BATCH + 1 ) . ' posts.' ), $this->messages( 'success' ) );
	}
}
