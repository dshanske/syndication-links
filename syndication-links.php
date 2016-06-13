<?php
/**
 * Plugin Name: Syndication Links
 * Plugin URI: http://wordpress.org/plugins/syndication-links
 * Description: Add Syndication Links to Your Content
 * Version: 3.0.2
 * Author: David Shanske
 * Author URI: http://david.shanske.com
 */

define( 'SYNDICATION_LINKS_VERSION', '3.0.2' );

function syndication_links_activation() {
}

register_activation_hook( __FILE__, 'syndication_links_activation' );


require_once( plugin_dir_path( __FILE__ ) . '/includes/class-syn-meta.php' );
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-syn-config.php' );
// Social Plugin Add-Ons
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-social-plugins.php' );

// User/H-Card Functions
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-hcard-widget.php' );

?>
