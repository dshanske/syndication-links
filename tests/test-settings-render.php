<?php
/**
 * Tests the settings page: its settings, sections, fields, field types and tabs.
 *
 * @package Syndication_Links
 */

class Test_Settings_Render extends WP_UnitTestCase {

	/**
	 * Options the tests change.
	 *
	 * @var string[]
	 */
	private static $options = array( 'syn_test_option', 'syndication-links_display', 'syndication_post_types' );

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	public function tear_down() {
		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
		unset( $_GET['tab'] );
		parent::tear_down();
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
	 * The settings and their defaults.
	 *
	 * @dataProvider data_settings
	 *
	 * @param string $name    Option name.
	 * @param string $group   Settings group.
	 * @param mixed  $default Default.
	 */
	public function test_the_settings_are_registered( $name, $group, $default ) {
		$settings = get_registered_settings();

		$this->assertArrayHasKey( $name, $settings );
		$this->assertSame( $group, $settings[ $name ]['group'] );
		$this->assertSame( $default, $settings[ $name ]['default'] );
	}

	public function data_settings() {
		return array(
			array( 'syndication-links_bw', 'syndication_display', false ),
			array( 'syndication-links_archives', 'syndication_display', true ),
			array( 'syndication-links_feed', 'syndication_display', true ),
			array( 'syndication-links_display', 'syndication_display', 'icons' ),
			array( 'syndication-links_size', 'syndication_display', 'small' ),
			array( 'syndication-links_text_before', 'syndication_display', 'Also on:' ),
			array( 'syndication_posse_enable', 'syndication_providers', '0' ),
			array( 'syndication_wp_cron', 'syndication_providers', '1' ),
		);
	}

	public function test_admin_init_adds_the_sections_and_fields() {
		global $wp_settings_sections, $wp_settings_fields;

		Syn_Config::admin_init();

		$this->assertArrayHasKey( 'syndication_display', $wp_settings_sections['syndication_display_options'] );
		$this->assertArrayHasKey( 'syndication_providers', $wp_settings_sections['syndication_provider_options'] );
		$this->assertSame(
			array(
				'syndication-links_display',
				'syndication-links_size',
				'syndication-links_bw',
				'syndication-links_archives',
				'syndication-links_feed',
				'syndication-links_text_before',
				'syndication_post_types',
			),
			array_values( array_intersect( array_keys( $wp_settings_fields['syndication_display_options']['syndication_display'] ), array( 'syndication-links_display', 'syndication-links_size', 'syndication-links_bw', 'syndication-links_archives', 'syndication-links_feed', 'syndication-links_text_before', 'syndication_post_types' ) ) )
		);
		$this->assertArrayHasKey( 'syndication_posse_enable', $wp_settings_fields['syndication_provider_options']['syndication_providers'] );
		$this->assertArrayHasKey( 'syndication_wp_cron', $wp_settings_fields['syndication_provider_options']['syndication_providers'] );
	}

	/**
	 * Without the IndieWeb plugin, the page is under Settings.
	 */
	public function test_the_page_is_under_settings() {
		global $submenu;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Syn_Config::admin_menu();

		$this->assertContains( 'syndication_links', wp_list_pluck( $submenu['options-general.php'], 2 ) );
	}

	public function test_the_section_descriptions() {
		$this->assertStringContainsString( 'Options for Presenting Syndication Links', $this->output( array( 'Syn_Config', 'options_callback' ) ) );
		$this->assertStringContainsString( 'Options for Syndicating to Other Sites', $this->output( array( 'Syn_Config', 'posse_options_callback' ) ) );
	}

	/**
	 * A checkbox submits 0 when unchecked, through a hidden field before it.
	 */
	public function test_checkbox_field() {
		$args = array( 'label_for' => 'syn_test_option' );
		$this->assertSame(
			"<input name='syn_test_option' type='hidden' value='0' /><input name='syn_test_option' type='checkbox' value='1'  /> ",
			$this->output( array( 'Syn_Config', 'checkbox_callback' ), $args )
		);

		update_option( 'syn_test_option', '1' );
		$this->assertMatchesRegularExpression( "/type='checkbox' value='1'\\s+checked/", $this->output( array( 'Syn_Config', 'checkbox_callback' ), $args ) );
	}

	public function test_select_field() {
		update_option( 'syn_test_option', 'b' );

		$html = $this->output(
			array( 'Syn_Config', 'select_callback' ),
			array(
				'label_for' => 'syn_test_option',
				'list'      => array(
					'a' => 'A',
					'b' => 'B & C',
				),
			)
		);

		$this->assertStringStartsWith( "<select name='syn_test_option' id='syn_test_option'>", $html );
		$this->assertStringContainsString( '<option value="a">A</option>', $html );
		$this->assertStringContainsString( '<option value="b" selected>B &amp; C</option>', $html );
	}

	public function test_radio_field() {
		update_option( 'syndication-links_display', 'text' );

		$html = $this->output(
			array( 'Syn_Config', 'radio_callback' ),
			array(
				'label_for' => 'syndication-links_display',
				'list'      => Syn_Config::display_options(),
			)
		);

		$this->assertSame( 4, substr_count( $html, 'type="radio"' ) );
		$this->assertMatchesRegularExpression( '/id="syndication-links_display-text" value="text"\s+checked/', $html );
		$this->assertDoesNotMatchRegularExpression( '/value="icons"\s+checked/', $html );
		$this->assertStringContainsString( '<label for="syndication-links_display-hidden">Hidden Links</label>', $html );
	}

	/**
	 * Posts always have links, so only the other public types are offered.
	 */
	public function test_post_type_field() {
		update_option( 'syndication_post_types', array( 'page' ) );

		$html = $this->output( array( 'Syn_Config', 'post_type_callback' ), array( 'label_for' => 'syndication_post_types' ) );

		$this->assertStringNotContainsString( "value='post'", $html );
		$this->assertMatchesRegularExpression( "/value='page'\\s+checked/", $html );
		$this->assertMatchesRegularExpression( "/value='attachment'\\s*\\/>/", $html );
	}

	/**
	 * Text fields escape their value; password fields aren't autofilled.
	 */
	public function test_text_fields() {
		update_option( 'syn_test_option', '"quoted"' );

		$text     = $this->output( array( 'Syn_Config', 'text_callback' ), array( 'label_for' => 'syn_test_option' ) );
		$url      = $this->output(
			array( 'Syn_Config', 'text_callback' ),
			array(
				'label_for' => 'syn_test_option',
				'type'      => 'url',
			)
		);
		$password = $this->output(
			array( 'Syn_Config', 'text_callback' ),
			array(
				'label_for' => 'syn_test_option',
				'type'      => 'password',
			)
		);

		$this->assertStringContainsString( 'type="text" value="&quot;quoted&quot;"', $text );
		$this->assertStringContainsString( 'type="url"', $url );
		$this->assertStringContainsString( 'type="password"', $password );
		$this->assertStringContainsString( 'data-lpignore="true"', $password );
	}

	public function test_the_display_and_size_choices() {
		$this->assertSame( array( 'icons', 'text', 'iconstext', 'hidden' ), array_keys( Syn_Config::display_options() ) );
		$this->assertSame( array( 'small', 'medium', 'large' ), array_keys( Syn_Config::size_options() ) );
	}

	public function test_tab_link_marks_the_active_tab() {
		$active   = $this->output( array( 'Syn_Config', 'tab_link' ), 'apis', 'API Keys', 'apis' );
		$inactive = $this->output( array( 'Syn_Config', 'tab_link' ), 'import', 'Import', 'apis' );

		$this->assertStringContainsString( 'class="nav-tab nav-tab-active">API Keys</a>', $active );
		$this->assertStringContainsString( 'tab=import', $inactive );
		$this->assertStringContainsString( 'class="nav-tab">Import</a>', $inactive );
	}

	/**
	 * Each settings tab is a form for its own settings group.
	 *
	 * @dataProvider data_tabs
	 *
	 * @param string|null $tab   The tab, or null for the default.
	 * @param string      $group The settings group its form saves.
	 */
	public function test_each_tab_saves_its_settings_group( $tab, $group ) {
		if ( $tab ) {
			$_GET['tab'] = $tab;
		}

		$html = $this->output( array( 'Syn_Config', 'links_options' ) );

		$this->assertStringContainsString( '<form method="post" action="options.php">', $html );
		$this->assertStringContainsString( "name='option_page' value='" . $group . "'", $html );
		$this->assertStringContainsString( 'type="submit"', $html );
	}

	public function data_tabs() {
		return array(
			'default'   => array( null, 'syndication_display' ),
			'general'   => array( 'general', 'syndication_display' ),
			'providers' => array( 'providers', 'syndication_providers' ),
			'apis'      => array( 'apis', 'syndication_apis' ),
		);
	}

	/**
	 * The Import tab has its own forms, not the settings form.
	 */
	public function test_the_import_tab() {
		$_GET['tab'] = 'import';

		$html = $this->output( array( 'Syn_Config', 'links_options' ) );

		$this->assertStringNotContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( 'nav-tab nav-tab-active">Import</a>', $html );
		$this->assertStringEndsWith( '</div>', $html );
	}
}
