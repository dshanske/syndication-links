<?php
/**
 * Tests that the POSSE code and every provider load (#178).
 *
 * The bootstrap turns POSSE on and stubs send_webmention(), so the provider
 * set that loads here is what a site with POSSE and the Webmention plugin
 * active gets.
 *
 * @package Syndication_Links
 */

class Test_Providers extends WP_UnitTestCase {

	/**
	 * Registered providers before each test, restored afterwards.
	 *
	 * @var array
	 */
	private $saved_targets;

	public function set_up() {
		parent::set_up();
		$this->saved_targets = $this->targets()->getValue();
	}

	public function tear_down() {
		$this->targets()->setValue( null, $this->saved_targets );
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
	 * The POSSE base classes load when POSSE is enabled.
	 */
	public function test_posse_classes_are_loaded() {
		$this->assertTrue( class_exists( 'Syndication_Provider' ) );
		$this->assertTrue( class_exists( 'Post_Syndication' ) );
		$this->assertTrue( class_exists( 'SynProvider_Micropub' ) );
		$this->assertTrue( class_exists( 'SynProvider_Webmention' ) );
		$this->assertTrue( class_exists( 'SynProvider_Webmention_Custom' ) );
	}

	/**
	 * The default providers are registered, keyed by UID.
	 */
	public function test_default_providers_are_registered() {
		$providers = Post_Syndication::get_providers();

		$expected = array(
			'microdotblog',
			'bridgy-fed',
			'micropub-flickr-bridgy',
			'micropub-github-bridgy',
			'micropub-mastodon-bridgy',
			'micropub-bluesky-bridgy',
		);
		foreach ( $expected as $uid ) {
			$this->assertArrayHasKey( $uid, $providers, "Provider {$uid} is not registered." );
			$this->assertNotEmpty( $providers[ $uid ], "Provider {$uid} has no name." );
		}
	}

	/**
	 * Providers that depend on another setting or plugin aren't registered.
	 */
	public function test_conditional_providers_are_not_registered() {
		$providers = Post_Syndication::get_providers();

		// SYNDICATION_LINKS_BRIDGY_WEBMENTION defaults to 0, which selects the Micropub versions of Bridgy.
		$this->assertSame( 0, SYNDICATION_LINKS_BRIDGY_WEBMENTION );
		$this->assertArrayNotHasKey( 'webmention-github-bridgy', $providers );
		$this->assertArrayNotHasKey( 'webmention-flickr-bridgy', $providers );
		$this->assertArrayNotHasKey( 'webmention-mastodon-bridgy', $providers );

		// Pinboard needs Post Kinds.
		if ( ! function_exists( 'get_post_kind_response' ) ) {
			$this->assertArrayNotHasKey( 'pinboard', $providers );
		}
	}

	/**
	 * Only Syndication_Provider objects with a UID can be registered.
	 */
	public function test_register_rejects_invalid_objects() {
		$this->assertFalse( register_syndication_provider( new stdClass() ) );
		$this->assertFalse( Post_Syndication::register( 'not-an-object' ) );
	}

	/**
	 * Every PHP file in includes/ loads, including the ones that are only
	 * loaded conditionally, without errors or a class name clash.
	 */
	public function test_every_include_file_loads() {
		$files = $this->get_include_files();
		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			require_once $file;
		}

		// The conditionally loaded providers are now defined.
		$this->assertTrue( class_exists( 'SynProvider_Webmention_Bridgy' ) );
		$this->assertTrue( class_exists( 'SynProvider_Webmention_Bridgy_Github' ) );
		$this->assertTrue( class_exists( 'SynProvider_Webmention_Bridgy_Flickr' ) );
		$this->assertTrue( class_exists( 'SynProvider_Webmention_Bridgy_Mastodon' ) );
		$this->assertTrue( class_exists( 'Syndication_Provider_Pinboard' ) );
	}

	/**
	 * Every concrete provider can be created, registered, and describes
	 * itself as a syndication target.
	 *
	 * Each provider is registered before get() is called, as the plugin does:
	 * get() looks the provider up in the registry (see REVIEW.md B10).
	 *
	 * @depends test_every_include_file_loads
	 */
	public function test_every_provider_can_be_instantiated() {
		foreach ( $this->get_include_files() as $file ) {
			require_once $file;
		}

		$classes = array(
			'Syndication_Provider_MicroDotBlog',
			'Syndication_Provider_Pinboard',
			'SynProvider_Micropub_Bridgy_Bluesky',
			'SynProvider_Micropub_Bridgy_Flickr',
			'SynProvider_Micropub_Bridgy_Github',
			'SynProvider_Micropub_Bridgy_Mastodon',
			'SynProvider_Webmention_Bridgy_Fed',
			'SynProvider_Webmention_Bridgy_Flickr',
			'SynProvider_Webmention_Bridgy_Github',
			'SynProvider_Webmention_Bridgy_Mastodon',
		);

		foreach ( $classes as $class ) {
			$provider = new $class();
			$this->assertInstanceOf( 'Syndication_Provider', $provider, $class );
			$this->assert_target( $provider, $class );
		}

		$custom = new SynProvider_Webmention_Custom(
			array(
				'name'   => 'Example',
				'uid'    => 'Example Target',
				'target' => 'https://example.com/webmention',
			)
		);
		$this->assert_target( $custom, 'SynProvider_Webmention_Custom' );
		$this->assertSame( 'example-target', $custom->get_uid() );
	}

	/**
	 * Assert that a provider describes itself with a UID and a name.
	 *
	 * @param Syndication_Provider $provider The provider.
	 * @param string               $label    Label for failure messages.
	 */
	private function assert_target( $provider, $label ) {
		$this->assertTrue( register_syndication_provider( $provider ), "{$label} could not be registered." );
		$this->assertNotEmpty( $provider->get_uid(), "{$label} has no UID." );
		$this->assertNotEmpty( $provider->get_name(), "{$label} has no name." );

		$target = $provider->get();
		$this->assertIsArray( $target, $label );
		$this->assertSame( $provider->get_uid(), $target['uid'], $label );
		$this->assertSame( $provider->get_name(), $target['name'], $label );
	}

	/**
	 * All PHP files under includes/, shortest path first so each base class
	 * loads before the classes that extend it.
	 *
	 * @return string[] Absolute file paths.
	 */
	private function get_include_files() {
		$dir      = dirname( __DIR__ ) . '/includes';
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		$files    = array();
		foreach ( $iterator as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}
		usort(
			$files,
			function ( $a, $b ) {
				return strlen( $a ) - strlen( $b ) ?: strcmp( $a, $b );
			}
		);
		return $files;
	}
}
