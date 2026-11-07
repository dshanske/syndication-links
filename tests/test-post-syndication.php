<?php
/**
 * Tests for Post_Syndication: the provider registry, the "Syndicate To" metabox, and running syndication.
 *
 * The tests swap the registered providers for fake ones, so nothing is sent anywhere.
 *
 * @package Syndication_Links
 */

if ( ! class_exists( 'Syn_Test_Provider' ) ) {
	/**
	 * A provider that records its calls instead of posting anywhere.
	 */
	class Syn_Test_Provider extends Syndication_Provider {

		/**
		 * Calls to posse(), as array( uid, post ID ).
		 *
		 * @var array[]
		 */
		public static $calls = array();

		/**
		 * What posse() returns.
		 *
		 * @var mixed
		 */
		public $result = array( 'posted' => true );

		/**
		 * Constructor.
		 *
		 * @param string $uid  UID.
		 * @param string $name Name.
		 */
		public function __construct( $uid = 'test-target', $name = 'Test Target' ) {
			$this->uid  = $uid;
			$this->name = $name;
			parent::__construct();
		}

		/**
		 * Records the call.
		 *
		 * @param int $post_id Post ID.
		 * @return mixed
		 */
		public function posse( $post_id = null ) {
			self::$calls[] = array( $this->uid, $post_id );
			return $this->result;
		}
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- The fake provider above is only for these tests.
class Test_Post_Syndication extends WP_UnitTestCase {

	/**
	 * A checked attribute: checked='checked' in WordPress, a bare checked in ClassicPress.
	 */
	const CHECKED = '/\\schecked(=|\\s|>|\/)/';

	/**
	 * A disabled attribute, in either form.
	 */
	const DISABLED = '/\\sdisabled(=|\\s|>|\/)/';

	/**
	 * Registered providers before each test, restored afterwards.
	 *
	 * @var array
	 */
	private $saved_targets;

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
		$this->saved_targets = $this->targets()->getValue();
		$this->targets()->setValue( null, array() );
		Syn_Test_Provider::$calls = array();
		wp_set_current_user( self::$admin_id );
		$_POST = array();
		delete_option( 'syndication_provider_enable' );
		delete_option( 'syndication_wp_cron' );
	}

	public function tear_down() {
		$this->targets()->setValue( null, $this->saved_targets );
		$_POST = array();
		delete_option( 'syndication_provider_enable' );
		delete_option( 'syndication_wp_cron' );
		wp_clear_scheduled_hook( 'syn_syndication' );
		parent::tear_down();
	}

	/**
	 * Accessor for the protected Post_Syndication::$targets registry.
	 *
	 * @return ReflectionProperty
	 */
	private function targets() {
		$property = new ReflectionProperty( 'Post_Syndication', 'targets' );
		$property->setAccessible( true );
		return $property;
	}

	/**
	 * Registers fake providers.
	 *
	 * @param string ...$uids UIDs.
	 * @return Syn_Test_Provider[] Providers, keyed by UID.
	 */
	private function register_fakes( ...$uids ) {
		$providers = array();
		foreach ( $uids as $uid ) {
			$providers[ $uid ] = new Syn_Test_Provider( $uid, 'Name of ' . $uid );
			Post_Syndication::register( $providers[ $uid ] );
		}
		return $providers;
	}

	/**
	 * Creates a post published $age seconds ago (negative for the future).
	 *
	 * @param int   $age  Seconds since publishing.
	 * @param array $args Other post arguments.
	 * @return int Post ID.
	 */
	private function post_aged( $age, $args = array() ) {
		$time = time() - $age;
		return self::factory()->post->create(
			array_merge(
				array(
					'post_status'   => $age < 0 ? 'future' : 'publish',
					'post_date'     => wp_date( 'Y-m-d H:i:s', $time ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
				),
				$args
			)
		);
	}

	public function test_hooks() {
		$this->assertSame( 9, has_action( 'do_pings', array( 'Post_Syndication', 'do_pings' ) ) );
		$this->assertSame( 10, has_action( 'micropub_syndication', array( 'Post_Syndication', 'syndication' ) ) );
		$this->assertSame( 10, has_action( 'syn_syndication', array( 'Post_Syndication', 'syndication' ) ) );
		$this->assertNotFalse( has_action( 'save_post_post', array( 'Post_Syndication', 'save_post' ) ) );
	}

	public function test_register_accepts_only_providers_with_a_uid() {
		$this->assertTrue( Post_Syndication::register( new Syn_Test_Provider( 'one', 'One' ) ) );
		$this->assertFalse( Post_Syndication::register( new Syn_Test_Provider( '', 'No UID' ) ) );
		$this->assertFalse( Post_Syndication::register( new stdClass() ) );

		$this->assertSame( array( 'one' => 'One' ), Post_Syndication::get_providers() );
	}

	public function test_get_providers_escapes_names() {
		Post_Syndication::register( new Syn_Test_Provider( 'one', 'A & <b>B</b>' ) );

		$this->assertSame( array( 'one' => 'A &amp; &lt;b&gt;B&lt;/b&gt;' ), Post_Syndication::get_providers() );
	}

	public function test_syndication_runs_the_selected_providers_and_logs_them() {
		$this->register_fakes( 'one', 'two', 'three' );
		$post_id = $this->post_aged( MINUTE_IN_SECONDS );

		Post_Syndication::syndication( $post_id, array( 'one', 'three', 'not-registered' ) );

		$this->assertSame( array( array( 'one', $post_id ), array( 'three', $post_id ) ), Syn_Test_Provider::$calls );

		$log = get_post_meta( $post_id, 'syndication_log', true );
		$this->assertCount( 2, $log );
		$this->assertSame( array( 'one', 'three' ), wp_list_pluck( $log, 'uid' ) );
		$this->assertSame( 'Name of one', $log[0]['name'] );
		$this->assertSame( array(), $log[0]['data'] );
		$this->assertEqualsWithDelta( time(), $log[0]['date'], 60 );
	}

	public function test_syndication_appends_to_the_log() {
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( MINUTE_IN_SECONDS );

		Post_Syndication::syndication( $post_id, array( 'one' ) );
		Post_Syndication::syndication( $post_id, array( 'one' ) );

		$this->assertCount( 2, get_post_meta( $post_id, 'syndication_log', true ) );
	}

	public function test_errors_are_logged() {
		$providers                = $this->register_fakes( 'one' );
		$providers['one']->result = new WP_Error( 'failed', 'It failed', array( 'status' => 500 ) );
		$post_id                  = $this->post_aged( MINUTE_IN_SECONDS );

		Post_Syndication::syndication( $post_id, array( 'one' ) );

		$log = get_post_meta( $post_id, 'syndication_log', true );
		$this->assertSame( 'It failed', $log[0]['data']['message'] );
		$this->assertSame( array( 'status' => 500 ), $log[0]['data']['failed'] );
	}

	public function test_posts_more_than_a_day_old_are_refused() {
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( 3 * DAY_IN_SECONDS );

		Post_Syndication::syndication( $post_id, array( 'one' ) );

		$this->assertSame( array(), Syn_Test_Provider::$calls );
		$log = get_post_meta( $post_id, 'syndication_log', true );
		$this->assertCount( 1, $log );
		$this->assertInstanceOf( 'WP_Error', $log[0] );
		$this->assertSame( 'too_old', $log[0]->get_error_code() );
	}

	/**
	 * Future posts are scheduled for when they're published, and not sent now (#229).
	 */
	public function test_future_posts_are_scheduled_not_sent() {
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( -HOUR_IN_SECONDS );

		$this->assertTrue( Post_Syndication::syndication( $post_id, array( 'one' ) ) );

		$this->assertSame(
			get_post_timestamp( $post_id ) + 5,
			wp_next_scheduled( 'syn_syndication', array( $post_id, array( 'one' ) ) )
		);
		$this->assertSame( array(), Syn_Test_Provider::$calls );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'syndication_log' ) );
	}

	/**
	 * Asking again for the same post and targets keeps one event.
	 */
	public function test_future_posts_are_scheduled_once() {
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( -HOUR_IN_SECONDS );

		Post_Syndication::syndication( $post_id, array( 'one' ) );
		Post_Syndication::syndication( $post_id, array( 'one' ) );

		$this->assertSame( 1, $this->count_events( $post_id, array( 'one' ) ) );
	}

	/**
	 * If the publish date moves, the event moves with it.
	 */
	public function test_rescheduled_posts_move_the_event() {
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( -HOUR_IN_SECONDS );
		Post_Syndication::syndication( $post_id, array( 'one' ) );

		$later = time() + 2 * HOUR_IN_SECONDS;
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => wp_date( 'Y-m-d H:i:s', $later ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $later ),
			)
		);
		Post_Syndication::syndication( $post_id, array( 'one' ) );

		$this->assertSame( 1, $this->count_events( $post_id, array( 'one' ) ) );
		$this->assertSame( $later + 5, wp_next_scheduled( 'syn_syndication', array( $post_id, array( 'one' ) ) ) );
	}

	/**
	 * When the event runs after the post is published, the post is sent once.
	 */
	public function test_scheduled_event_sends_the_post() {
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( -HOUR_IN_SECONDS );
		Post_Syndication::syndication( $post_id, array( 'one' ) );

		// The publish time arrives.
		$now = time() - MINUTE_IN_SECONDS;
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'publish',
				'post_date'     => wp_date( 'Y-m-d H:i:s', $now ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $now ),
			)
		);
		wp_unschedule_event( wp_next_scheduled( 'syn_syndication', array( $post_id, array( 'one' ) ) ), 'syn_syndication', array( $post_id, array( 'one' ) ) );
		do_action( 'syn_syndication', $post_id, array( 'one' ) );

		$this->assertSame( array( array( 'one', $post_id ) ), Syn_Test_Provider::$calls );
		$this->assertCount( 1, get_post_meta( $post_id, 'syndication_log', true ) );
		$this->assertFalse( wp_next_scheduled( 'syn_syndication', array( $post_id, array( 'one' ) ) ) );
	}

	/**
	 * Counts the scheduled syn_syndication events for a post and targets.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $uids    UIDs.
	 * @return int
	 */
	private function count_events( $post_id, $uids ) {
		$key   = md5( serialize( array( $post_id, $uids ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- How WP-Cron keys events.
		$count = 0;
		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( isset( $hooks['syn_syndication'][ $key ] ) ) {
				++$count;
			}
		}
		return $count;
	}

	public function test_missing_post_does_nothing() {
		$this->register_fakes( 'one' );

		$this->assertNull( Post_Syndication::syndication( PHP_INT_MAX, array( 'one' ) ) );
		$this->assertSame( array(), Syn_Test_Provider::$calls );
	}

	public function test_no_registered_providers() {
		$post_id = $this->post_aged( MINUTE_IN_SECONDS );

		$this->assertFalse( Post_Syndication::syndication( $post_id, array( 'one' ) ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'syndication_log' ) );
	}

	public function test_do_pings_syndicates_queued_posts() {
		$this->register_fakes( 'one' );
		$queued = $this->post_aged( MINUTE_IN_SECONDS );
		$draft  = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		add_post_meta( $queued, '_syndicate-to', array( 'one' ) );
		add_post_meta( $draft, '_syndicate-to', array( 'one' ) );

		Post_Syndication::do_pings();

		$this->assertSame( array( array( 'one', $queued ) ), Syn_Test_Provider::$calls );
		$this->assertFalse( metadata_exists( 'post', $queued, '_syndicate-to' ) );
		$this->assertTrue( metadata_exists( 'post', $draft, '_syndicate-to' ) );
	}

	/**
	 * Fills $_POST as the "Syndicate To" metabox would.
	 *
	 * @param string[] $uids Checked UIDs.
	 */
	private function submit( $uids ) {
		$_POST['synto_metabox_nonce'] = wp_create_nonce( 'synto_metabox' );
		$_POST['syndicate-to']        = $uids;
	}

	public function test_saving_queues_the_checked_providers() {
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( MINUTE_IN_SECONDS );
		$this->submit( array( 'one', 'Two Words' ) );

		Post_Syndication::save_post( $post_id );

		$this->assertSame( array( 'one', 'twowords' ), get_post_meta( $post_id, '_syndicate-to', true ) );
		$this->assertSame( array(), Syn_Test_Provider::$calls );
	}

	public function test_saving_without_cron_syndicates_published_posts() {
		update_option( 'syndication_wp_cron', 0 );
		$this->register_fakes( 'one' );
		$post_id = $this->post_aged( MINUTE_IN_SECONDS );
		$draft   = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$this->submit( array( 'one' ) );

		Post_Syndication::save_post( $post_id );
		Post_Syndication::save_post( $draft );

		$this->assertSame( array( array( 'one', $post_id ) ), Syn_Test_Provider::$calls );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_syndicate-to' ) );
		$this->assertFalse( metadata_exists( 'post', $draft, '_syndicate-to' ) );
	}

	public function test_saving_without_a_valid_nonce_does_nothing() {
		$post_id               = $this->post_aged( MINUTE_IN_SECONDS );
		$_POST['syndicate-to'] = array( 'one' );

		Post_Syndication::save_post( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_syndicate-to' ) );

		$_POST['synto_metabox_nonce'] = 'not-a-nonce';
		Post_Syndication::save_post( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_syndicate-to' ) );
	}

	public function test_saving_needs_permission_to_edit_the_post() {
		$post_id = $this->post_aged( MINUTE_IN_SECONDS, array( 'post_author' => self::$admin_id ) );
		wp_set_current_user( self::$subscriber_id );
		$this->submit( array( 'one' ) );

		Post_Syndication::save_post( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, '_syndicate-to' ) );
	}

	public function test_saving_with_nothing_checked_queues_nothing() {
		$post_id                      = $this->post_aged( MINUTE_IN_SECONDS );
		$_POST['synto_metabox_nonce'] = wp_create_nonce( 'synto_metabox' );

		Post_Syndication::save_post( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, '_syndicate-to' ) );
	}

	public function test_checkboxes_without_enabled_providers() {
		$this->register_fakes( 'one' );
		$post_id = self::factory()->post->create();

		$this->assertSame( 'No Syndication Targets Available', Post_Syndication::checkboxes( $post_id ) );
	}

	public function test_checkboxes_show_only_enabled_providers() {
		$this->register_fakes( 'one', 'two' );
		update_option( 'syndication_provider_enable', array( 'two' ) );
		$post_id = self::factory()->post->create();

		$html = Post_Syndication::checkboxes( $post_id );

		$this->assertStringStartsWith( '<ul>', $html );
		$this->assertStringContainsString( 'name="syndicate-to[]" id="two" value="two"', $html );
		$this->assertStringContainsString( 'Name of two', $html );
		$this->assertStringNotContainsString( 'value="one"', $html );
		$this->assertDoesNotMatchRegularExpression( self::CHECKED, $html );
	}

	public function test_checked_state_is_filterable() {
		$this->register_fakes( 'one' );
		update_option( 'syndication_provider_enable', array( 'one' ) );
		$post_id = self::factory()->post->create();
		$seen    = array();
		$filter  = function ( $checked, $uid, $id ) use ( &$seen ) {
			$seen = array( $uid, $id );
			return true;
		};
		add_filter( 'syndication_link_checked', $filter, 10, 3 );

		$html = Post_Syndication::checkboxes( $post_id );

		$this->assertMatchesRegularExpression( self::CHECKED, $html );
		$this->assertSame( array( 'one', $post_id ), $seen );
	}

	public function test_disabled_providers_are_not_checked() {
		$this->register_fakes( 'one' );
		update_option( 'syndication_provider_enable', array( 'one' ) );
		$post_id = self::factory()->post->create();
		add_filter( 'syndication_link_disabled', '__return_true' );
		add_filter( 'syndication_link_checked', '__return_true' );

		$html = Post_Syndication::checkboxes( $post_id );

		$this->assertMatchesRegularExpression( self::DISABLED, $html );
		$this->assertDoesNotMatchRegularExpression( self::CHECKED, $html );
	}

	/**
	 * Enabled providers offer themselves to Micropub clients as syndication targets.
	 */
	public function test_enabled_providers_are_micropub_targets() {
		update_option( 'syndication_provider_enable', array( 'one' ) );
		$this->register_fakes( 'one', 'two' );

		$targets = apply_filters( 'micropub_syndicate-to', array(), self::$admin_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Micropub's hook.

		$this->assertSame( array( 'one' ), wp_list_pluck( $targets, 'uid' ) );
		$this->assertSame( 'Name of one', $targets[0]['name'] );
	}
}
