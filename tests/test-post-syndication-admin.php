<?php
/**
 * Tests Post_Syndication's settings, editor boxes and log output.
 *
 * @package Syndication_Links
 */

class Test_Post_Syndication_Admin extends WP_UnitTestCase {

	/**
	 * Registered providers before each test, restored afterwards.
	 *
	 * @var array
	 */
	private $saved_targets;

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		// add_meta_box() converts each post type to a screen.
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
	}

	public function set_up() {
		parent::set_up();
		$this->saved_targets = $this->targets()->getValue();
		$this->targets()->setValue( null, array() );
		$_POST = array();
	}

	public function tear_down() {
		global $wp_meta_boxes;
		$this->targets()->setValue( null, $this->saved_targets );
		$_POST         = array();
		$wp_meta_boxes = array();
		delete_option( 'syndication_provider_enable' );
		delete_option( 'syndication_wp_cron' );
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
	 * A provider that sends nothing.
	 *
	 * @param string $uid  UID.
	 * @param string $name Name.
	 * @return Syndication_Provider
	 */
	private function provider( $uid = 'one', $name = 'One' ) {
		return new class( $uid, $name ) extends Syndication_Provider {
			/**
			 * Sets the UID and name.
			 *
			 * @param string $uid  UID.
			 * @param string $name Name.
			 */
			public function __construct( $uid, $name ) {
				$this->uid  = $uid;
				$this->name = $name;
				parent::__construct();
			}

			/**
			 * Sends nothing.
			 *
			 * @param int $post_id Post ID.
			 * @return array
			 */
			public function posse( $post_id = null ) {
				return array();
			}
		};
	}

	/**
	 * Captures what a callback prints.
	 *
	 * @param callable $callback The callback.
	 * @param mixed    ...$args  Its arguments.
	 * @return string
	 */
	private function output( $callback, ...$args ) {
		ob_start();
		call_user_func_array( $callback, $args );
		return ob_get_clean();
	}

	/**
	 * Creates a response as WP_Http returns it, with the Requests response it wraps.
	 *
	 * @param int    $code     Status code.
	 * @param string $body     Body.
	 * @param string $location Optional. The Location header.
	 * @return array
	 */
	private function http_response( $code, $body, $location = '' ) {
		$response_class        = class_exists( 'WpOrg\\Requests\\Response' ) ? 'WpOrg\\Requests\\Response' : 'Requests_Response';
		$requests              = new $response_class();
		$requests->status_code = $code;
		$requests->body        = $body;
		if ( $location ) {
			$requests->headers['location'] = $location;
		}
		$wrapped                   = new WP_HTTP_Requests_Response( $requests );
		$response                  = $wrapped->to_array();
		$response['http_response'] = $wrapped;
		return $response;
	}

	public function test_the_settings_are_registered() {
		$settings = get_registered_settings();

		$this->assertSame( 'syndication_providers', $settings['syndication_provider_enable']['group'] );
		$this->assertSame( array(), $settings['syndication_provider_enable']['default'] );
		$this->assertSame( 'syndication_providers', $settings['syndication_use_excerpt']['group'] );
		$this->assertSame( 'syndication_providers', $settings['syndication_backlink']['group'] );
		$this->assertSame( 'maybe', $settings['syndication_backlink']['default'] );
	}

	/**
	 * Saving runs early with WP-Cron on (the default), and late with it off, after other plugins have saved their meta.
	 */
	public function test_saving_runs_late_without_cron() {
		$this->assertSame( 4, has_action( 'save_post_post', array( 'Post_Syndication', 'save_post' ) ) );
		remove_action( 'save_post_post', array( 'Post_Syndication', 'save_post' ), 4 );
		update_option( 'syndication_wp_cron', '0' );
		Post_Syndication::init();

		$this->assertSame( 99, has_action( 'save_post_post', array( 'Post_Syndication', 'save_post' ) ) );
		remove_action( 'save_post_post', array( 'Post_Syndication', 'save_post' ), 99 );
		add_action( 'save_post_post', array( 'Post_Syndication', 'save_post' ), 4, 2 );
	}

	public function test_admin_init_adds_the_fields_and_api_keys_section() {
		global $wp_settings_sections, $wp_settings_fields;

		Post_Syndication::admin_init();

		$fields = $wp_settings_fields['syndication_provider_options']['syndication_providers'];
		$this->assertArrayHasKey( 'syndication_provider_enable', $fields );
		$this->assertArrayHasKey( 'syndication_use_excerpt', $fields );
		$this->assertSame( array( '', 'true', 'maybe' ), array_map( 'strval', array_keys( $fields['syndication_backlink']['args']['list'] ) ) );
		$this->assertSame( array( 'Post_Syndication', 'syndication_apis_callback' ), $wp_settings_sections['syndication_api_keys']['syndication_apis']['callback'] );
	}

	public function test_the_api_keys_section_explains_bridgy_tokens() {
		$html = $this->output( array( 'Post_Syndication', 'syndication_apis_callback' ) );

		$this->assertStringContainsString( '<a href="https://brid.gy/about#micropub">Bridgy</a>', $html );
		$this->assertStringContainsString( '<strong>Get token</strong>', $html );
	}

	public function test_the_boxes_are_added_on_the_editor_screens() {
		global $wp_meta_boxes;

		Post_Syndication::metabox_setup();
		$this->assertSame( 10, has_action( 'add_meta_boxes', array( 'Post_Syndication', 'add_postmeta_boxes' ) ) );
		remove_action( 'add_meta_boxes', array( 'Post_Syndication', 'add_postmeta_boxes' ) );

		Post_Syndication::add_postmeta_boxes();

		foreach ( syndication_post_types() as $type ) {
			$this->assertSame( array( 'Post_Syndication', 'metabox' ), $wp_meta_boxes[ $type ]['side']['default']['syndicationbox-meta']['callback'] );
			$this->assertSame( array( 'Post_Syndication', 'syndication_log_metabox' ), $wp_meta_boxes[ $type ]['advanced']['default']['syndicationlog-meta']['callback'] );
		}
	}

	public function test_the_syndicate_to_box_has_a_nonce_and_the_checkboxes() {
		Post_Syndication::register( $this->provider() );
		update_option( 'syndication_provider_enable', array( 'one' ) );
		$post = get_post( self::factory()->post->create() );

		$html = $this->output( array( 'Post_Syndication', 'metabox' ), $post, array() );

		$this->assertStringContainsString( 'name="synto_metabox_nonce"', $html );
		$this->assertStringContainsString( 'name="syndicate-to[]" id="one" value="one"', $html );
	}

	/**
	 * Saving the box for a page checks that the user can edit pages.
	 */
	public function test_saving_a_page_needs_permission_to_edit_it() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$_POST   = array(
			'synto_metabox_nonce' => wp_create_nonce( 'synto_metabox' ),
			'post_type'           => 'page',
			'syndicate-to'        => array( 'one' ),
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		Post_Syndication::save_post( $page_id );
		$this->assertFalse( metadata_exists( 'post', $page_id, '_syndicate-to' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST['synto_metabox_nonce'] = wp_create_nonce( 'synto_metabox' );
		Post_Syndication::save_post( $page_id );
		$this->assertSame( array( 'one' ), get_post_meta( $page_id, '_syndicate-to', true ) );
	}

	public function test_a_response_is_logged_with_its_body_code_and_location() {
		$entry = Post_Syndication::syndication_log(
			$this->http_response( 201, '{"id":"1"}', 'https://mastodon.example/@me/1' ),
			$this->provider()
		);

		$this->assertSame( 'one', $entry['uid'] );
		$this->assertSame( 'One', $entry['name'] );
		$this->assertSame(
			array(
				'body' => array( 'id' => '1' ),
				'code' => 201,
				'url'  => 'https://mastodon.example/@me/1',
			),
			$entry['data']
		);
	}

	public function test_a_body_that_isnt_json_is_logged_as_text() {
		$entry = Post_Syndication::syndication_log( $this->http_response( 400, 'Bad request' ), $this->provider() );

		$this->assertSame( 'Bad request', $entry['data']['body'] );
		$this->assertSame( 400, $entry['data']['code'] );
	}

	public function test_the_log_box_says_when_there_are_no_logs() {
		$post = get_post( self::factory()->post->create() );

		$this->assertSame( 'No Logs Found', $this->output( array( 'Post_Syndication', 'syndication_log_metabox' ), $post, array() ) );
	}

	public function test_the_log_lists_each_entry() {
		$post_id = self::factory()->post->create();
		$date    = 1793000000;
		update_post_meta(
			$post_id,
			'syndication_log',
			array(
				array(
					'date' => $date,
					'uid'  => 'one',
					'name' => 'One',
					'data' => array( 'code' => 201 ),
				),
				// Entries from before names were logged show the UID.
				array(
					'date' => $date,
					'uid'  => 'two',
					'data' => array(),
				),
				'not an entry',
			)
		);

		$html = $this->output( array( 'Post_Syndication', 'syndication_log_output' ), $post_id );

		$this->assertSame( 2, substr_count( $html, '<details>' ) );
		$this->assertStringContainsString( '<summary>' . wp_date( DATE_W3C, $date ) . ': One</summary>', $html );
		$this->assertStringContainsString( ': two</summary>', $html );
		$this->assertStringContainsString( '&quot;code&quot;: 201', $html );
	}

	/**
	 * Logged responses come from other services, so they're escaped.
	 */
	public function test_the_log_is_escaped() {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'syndication_log',
			array(
				array(
					'date' => time(),
					'uid'  => 'one',
					'name' => '<b>One</b>',
					'data' => array( 'body' => '<script>alert(1)</script>' ),
				),
			)
		);

		$html = $this->output( array( 'Post_Syndication', 'syndication_log_output' ), $post_id );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;One&lt;/b&gt;', $html );
	}
}
