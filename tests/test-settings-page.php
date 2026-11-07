<?php
/**
 * Tests for the settings page: its assets and the custom webmention providers section (#218).
 *
 * @package Syndication_Links
 */

class Test_Settings_Page extends WP_UnitTestCase {

	/**
	 * The admin assets the settings page needs.
	 *
	 * @var string[]
	 */
	private static $scripts = array( 'synlinks', 'syndication_links_password' );

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function set_up() {
		parent::set_up();
		$this->dequeue();
	}

	public function tear_down() {
		$this->dequeue();
		delete_option( 'syndication_links_custom_posse' );
		parent::tear_down();
	}

	/**
	 * Removes the settings page's assets from the queue.
	 */
	private function dequeue() {
		foreach ( self::$scripts as $handle ) {
			wp_dequeue_script( $handle );
		}
		wp_dequeue_style( 'syn_admin' );
	}

	/**
	 * The assets load whether the page is under the IndieWeb menu or under Settings.
	 *
	 * @dataProvider data_page_hooks
	 *
	 * @param string $hook Page hook suffix.
	 */
	public function test_settings_page_loads_its_assets( $hook ) {
		Syn_Config::admin_enqueue( $hook );

		foreach ( self::$scripts as $handle ) {
			$this->assertTrue( wp_script_is( $handle, 'enqueued' ), $handle );
		}
		$this->assertTrue( wp_style_is( 'syn_admin', 'enqueued' ) );
	}

	public function data_page_hooks() {
		return array(
			'IndieWeb menu' => array( 'indieweb_page_syndication_links' ),
			'Settings menu' => array( 'settings_page_syndication_links' ),
		);
	}

	public function test_other_pages_dont_load_the_assets() {
		Syn_Config::admin_enqueue( 'options-general.php' );

		foreach ( self::$scripts as $handle ) {
			$this->assertFalse( wp_script_is( $handle, 'enqueued' ), $handle );
		}
		$this->assertFalse( wp_style_is( 'syn_admin', 'enqueued' ) );
	}

	/**
	 * The option is registered before any custom provider exists, so the first one can be saved.
	 */
	public function test_custom_providers_option_is_registered() {
		SynProvider_Webmention_Custom::register_settings();

		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'syndication_links_custom_posse', $settings );
		$this->assertSame( 'syndication_providers', $settings['syndication_links_custom_posse']['group'] );
	}

	public function test_custom_providers_section_is_added_without_providers() {
		global $wp_settings_sections, $wp_settings_fields;

		SynProvider_Webmention_Custom::admin_init();

		$this->assertArrayHasKey( 'webmention_provider_options', $wp_settings_sections['syndication_provider_options'] );
		$this->assertArrayHasKey( 'syndication_links_custom_posse', $wp_settings_fields['syndication_provider_options']['webmention_provider_options'] );
	}

	public function test_custom_providers_field_with_no_providers() {
		ob_start();
		SynProvider_Webmention_Custom::webmention_callback( array() );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<ul id="custom_webmention"><li>', $html );
		$this->assertStringContainsString( 'name="syndication_links_custom_posse[0][name]"', $html );
		$this->assertStringContainsString( 'id="add-custom-webmention-button"', $html );
		$this->assertStringContainsString( 'id="delete-custom-webmention-button"', $html );
	}

	public function test_custom_providers_field_lists_saved_providers() {
		update_option(
			'syndication_links_custom_posse',
			array(
				array(
					'name'   => 'One',
					'uid'    => 'one',
					'target' => 'https://one.example/webmention',
				),
				array(
					'name'   => 'Two',
					'uid'    => 'two',
					'target' => 'https://two.example/webmention',
				),
			)
		);

		ob_start();
		SynProvider_Webmention_Custom::webmention_callback( array() );
		$html = ob_get_clean();

		$this->assertSame( 2, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( 'name="syndication_links_custom_posse[1][target]" id="syndication_links_custom_posse" value="https://two.example/webmention"', $html );
	}

	public function test_custom_providers_field_tolerates_a_bad_option() {
		update_option( 'syndication_links_custom_posse', '' );

		ob_start();
		SynProvider_Webmention_Custom::webmention_callback( array() );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="syndication_links_custom_posse[0][name]"', $html );
	}
}
