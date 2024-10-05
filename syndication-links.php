<?php
/**
 * Plugin Name: Syndication Links
 * Plugin URI: http://wordpress.org/plugins/syndication-links
 * Description: Add Links to Syndicated Copies of Your Posts
 * Version: 4.4.21
 * Requires at least: 4.9.9
 * Requires PHP: 7.0
 * Author: David Shanske
 * Author URI: http://david.shanske.com
 * Text Domain: syndication-links
 * Domain Path:  /languages
 * License: GPLv2 or later
 */

// Version Number Constant.
define( 'SYNDICATION_LINKS_VERSION', get_file_data( __FILE__, array( 'Version' => 'Version' ) )['Version'] );

// If set to 0 disable the webmention version of Bridgy in favor of Micropub.
defined( 'SYNDICATION_LINKS_BRIDGY_WEBMENTION' ) || define( 'SYNDICATION_LINKS_BRIDGY_WEBMENTION', 0 );

function syndication_links_load( $files, $dir = 'includes/' ) {
	$dir = trailingslashit( $dir );
	if ( empty( $files ) ) {
		return;
	}
	$path = plugin_dir_path( __FILE__ ) . $dir;
	foreach ( $files as $file ) {
		if ( file_exists( $path . $file ) ) {
			require_once $path . $file;
		} else {
			error_log( $path . $file );
		}
	}
}

/**
 * Filename to Classname Function.
 *
 * @param string $filename.
 */
function syndication_links_filename_to_classname( $filename ) {
	$class = str_replace( 'class-', '', $filename );
	$class = str_replace( '.php', '', $class );
	$class = ucwords( $class, '-' );
	$class = str_replace( '-', '_', $class );
	if ( class_exists( $class ) ) {
			return $class;
	}
	return false;
}

/**
 * Load and register files.
 *
 * Checks for the existence of and loads files, then registers them as providers.
 *
 * @param array  $files An array of filenames.
 * @param string $dir The directory the files can be found in, relative to the current directory.
 */
function syndication_links_register_providers( $files, $dir = 'includes/' ) {
	$dir = trailingslashit( $dir );
	if ( empty( $files ) ) {
		return;
	}
	$path = plugin_dir_path( __FILE__ ) . $dir;
	foreach ( $files as $file ) {
		if ( file_exists( $path . $file ) ) {
			require_once $path . $file;
			if ( str_contains( $file, 'provider' ) ) {
				$class = syndication_links_filename_to_classname( $file );
				if ( $class ) {
					register_syndication_provider( new $class() );
				} else {
					error_log( 'Cannot register ' . $class );
				}
			}
		} else {
			error_log( $path . $file );
		}
	}
}

function syndication_links_init() {
	syndication_links_load(
		array(
			'simple-icons.php', // Icon Information
			'class-syn-link-domain-icon-map.php', // Mapping domains to icon names
			'class-syn-meta.php', // Information on Metadata
			'class-syn-config.php', // Configuration Options
			'class-social-plugins.php', // Social Plugin Add-Ons
			'functions.php', // Global Functions
			'compat-functions.php', // Compat Functions
			'class-widget-original-of.php', // Original Of Widget
		)
	);

	if ( class_exists( 'Widget_Original_Of' ) ) {
		add_action(
			'widgets_init',
			function () {
				register_widget( 'Widget_Original_Of' );
			}
		);
	}

	if ( 1 === intval( get_option( 'syndication_posse_enable', 0 ) ) ) {
		syndication_links_load(
			array(
				'class-syndication-provider.php', // Syndication Provider Base Class
				'class-post-syndication.php', // Post syndication logic
			)
		);

		// Providers that require access to a third-party API or other method
		syndication_links_register_providers(
			array(
				'class-syndication-provider-microdotblog.php', // Micro.blog
			),
			'/includes/apis'
		);

		// Micropub Base Class
			syndication_links_register_providers(
				array(
					'class-synprovider-micropub.php', // Class for any Micropub Based Service
				),
				'/includes/micropub'
			);

		// Webmention Only Providers
		if ( function_exists( 'send_webmention' ) ) {

			syndication_links_load(
				array(
					'class-synprovider-webmention.php', // Class for Any Webmention Based Service
					'class-synprovider-webmention-custom.php', // Class for A Custom Webmention Based Service
				),
				'/includes/webmentions'
			);
			syndication_links_register_providers(
				array(
					'class-synprovider-webmention-bridgy-fed.php', // Bridgy Fed
				),
				'/includes/webmentions'
			);

			if ( class_exists( 'SynProvider_Webmention_Custom' ) ) {
				$custom = get_option( 'syndication_links_custom_posse' );
				if ( ! empty( $custom ) && is_array( $custom ) ) {
					foreach ( $custom as $c ) {
						register_syndication_provider( new SynProvider_Webmention_Custom( $c ) );
					}
				}
			}
		}

		if ( 0 === SYNDICATION_LINKS_BRIDGY_WEBMENTION ) {
			// Providers that require Micropub
			syndication_links_register_providers(
				array(
					'class-synprovider-micropub-bridgy-flickr.php',
					'class-synprovider-micropub-bridgy-github.php',
					'class-synprovider-micropub-bridgy-mastodon.php',
					'class-synprovider-micropub-bridgy-bluesky.php',
				),
				'/includes/micropub'
			);
		} elseif ( function_exists( 'send_webmention' ) ) {
				syndication_links_register_providers(
					array(
						'class-synprovider-webmention-bridgy.php', // Bridgy Base Class
						'class-synprovider-webmention-bridgy-github.php', // Github via Bridgy
						'class-synprovider-webmention-bridgy-flickr.php', // Flickr via Bridgy
						'class-synprovider-webmention-bridgy-mastodon.php', // Mastodon via Bridgy
					),
					'/includes/webmentions'
				);
		}

		// Providers that have a Post Kinds Dependency
		if ( class_exists( 'Post_Kinds_Plugin' ) ) {
			syndication_links_register_providers(
				array(
					'class-syndication-provider-pinboard.php', // Pinboard
				),
				'/includes/apis'
			);
		}
	}
	load_plugin_textdomain( 'syndication-links', false, basename( __DIR__ ) . '/languages/' );
}

	add_action( 'plugins_loaded', 'syndication_links_init', 11 );

function syndication_links_privacy_declaration() {
	if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
		$content = __(
			'Syndication Links, which are links to the same content on other sites, may be displayed on comments, but only if supplied by the submitter or if your comment was
			generated by webmention, if they appear on your site.',
			'syndication-links'
		);
		wp_add_privacy_policy_content(
			'Syndication Links',
			wp_kses_post( wpautop( $content, false ) )
		);
	}
}

	add_action( 'admin_init', 'syndication_links_privacy_declaration' );
