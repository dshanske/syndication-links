<?php
/**
 * Plugin Name: Communication and Syndication Links
 * Plugin URI: http://david.shanske.com
 * Description: Add and display Syndication Links and Communication/rel-me Links
 * Version: 0.5
 * Author: David Shanske
 * Author URI: http://david.shanske.com
 * License: CCO
 */

require_once( plugin_dir_path( __FILE__ ) . '/syn-postmeta.php');
require_once( plugin_dir_path( __FILE__ ) . '/syn-display.php');
require_once( plugin_dir_path( __FILE__ ) . '/syn-config.php');
require_once( plugin_dir_path( __FILE__ ) . '/bridgy.php');


function syndication_scripts() {
 	wp_enqueue_style( 'syndication-style', plugin_dir_url( __FILE__ ) . 'syn.min.css');	
}

add_action( 'wp_enqueue_scripts', 'syndication_scripts' );

?>
