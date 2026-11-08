<?php
/**
 * Tests the `wp syndication-links import` command.
 *
 * Uses WP-CLI from the dev dependencies, with a logger that records what it prints.
 *
 * @package Syndication_Links
 */

class Test_Plugin_Import_Cli extends WP_UnitTestCase {

	/**
	 * Messages printed, as array( type, message ).
	 *
	 * @var array[]
	 */
	public $messages = array();

	/**
	 * WP-CLI's logger and capture_exit before the test.
	 *
	 * @var array
	 */
	private $saved = array();

	public function set_up() {
		parent::set_up();
		if ( ! class_exists( 'WP_CLI' ) ) {
			$this->markTestSkipped( 'WP-CLI is not installed.' );
		}
		$this->messages = array();
		$this->saved    = array(
			'logger'       => $this->property( 'logger' )->getValue(),
			'capture_exit' => $this->property( 'capture_exit' )->getValue(),
		);
		WP_CLI::set_logger( $this->logger() );
		// WP_CLI::error() throws instead of exiting.
		$this->property( 'capture_exit' )->setValue( null, true );
	}

	public function tear_down() {
		if ( $this->saved ) {
			WP_CLI::set_logger( $this->saved['logger'] );
			$this->property( 'capture_exit' )->setValue( null, $this->saved['capture_exit'] );
		}
		delete_option( Syn_Plugin_Import::OPTION );
		parent::tear_down();
	}

	/**
	 * Accessor for one of WP_CLI's private static properties.
	 *
	 * @param string $name Property name.
	 * @return ReflectionProperty
	 */
	private function property( $name ) {
		$property = new ReflectionProperty( 'WP_CLI', $name );
		$property->setAccessible( true );
		return $property;
	}

	/**
	 * A logger that records the messages in $this->messages.
	 *
	 * @return object
	 */
	private function logger() {
		$test = $this;
		return new class( $test ) {
			/**
			 * The test.
			 *
			 * @var Test_Plugin_Import_Cli
			 */
			private $test;

			/**
			 * Stores the test.
			 *
			 * @param Test_Plugin_Import_Cli $test The test.
			 */
			public function __construct( $test ) {
				$this->test = $test;
			}

			/**
			 * Records a message.
			 *
			 * @param string $type Message type.
			 * @param array  $args Arguments: the message first.
			 */
			public function __call( $type, $args ) {
				$this->test->messages[] = array( $type, (string) $args[0] );
			}
		};
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
					$this->messages,
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
		} catch ( Exception $e ) {
			$this->assertInstanceOf( 'WP_CLI\\ExitException', $e );
		}
		$this->assertCount( 1, $this->messages( 'error' ) );
		$this->assertStringContainsString( 'mastodon-autopost', $this->messages( 'error' )[0] );
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
