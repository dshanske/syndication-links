<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

require_once $_tests_dir . '/includes/functions.php';

require_once dirname( dirname( __FILE__ ) ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/syndication-links.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/*
 * Load the POSSE code too. syndication_links_init() only loads the provider
 * classes when POSSE is enabled, and it runs once, on plugins_loaded.
 */
tests_add_filter(
	'pre_option_syndication_posse_enable',
	function () {
		return '1';
	}
);

/*
 * The Webmention providers are only loaded when the Webmention plugin's
 * send_webmention() exists. Stand in for it so they load; tests that send
 * can filter the result.
 */
if ( ! function_exists( 'send_webmention' ) ) {
	function send_webmention( $source, $target, $args = array() ) {
		return apply_filters( 'syn_test_send_webmention', new WP_Error( 'test_stub', 'send_webmention() is a test stub' ), $source, $target, $args );
	}
}

require $_tests_dir . '/includes/bootstrap.php';
