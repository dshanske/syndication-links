<?php
/**
 * Plugin Name: Syndication Links
 * Plugin URI: http://wordpress.org/plugins/syndication-links
 * Description: Add and display Syndication Links
 * Version: 2.0.2
 * Author: David Shanske
 * Author URI: http://david.shanske.com
 */

define ("SYNDICATION_LINKS_VERSION", "2.0.2");

function syndication_links_activation() {
  if (version_compare(phpversion(), 5.3, '<')) {
    die("The minimum PHP version required for this plugin is 5.3");
  }
}

register_activation_hook(__FILE__, 'syndication_links_activation');


require_once( plugin_dir_path( __FILE__ ) . '/includes/class-syn-meta.php');
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-syn-config.php');
// Social Plugin Add-Ons
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-social-plugins.php');

// User/H-Card Functions
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-syn-user.php');
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-hcard-widget.php');
require_once( plugin_dir_path( __FILE__ ) . '/includes/class-relme-widget.php');

function get_syn_network_strings() {
  $strings = array(
    'twitter.com' => _x( 'Twitter', 'Syn Links' ),
    'facebook.com' => _x( 'Facebook', 'Syn Links' ),
    'plus.google.com' => _x( 'Google+', 'Syn Links' ),
    'instagram.com' => _x( 'Instagram', 'Syn Links' ),
    'flickr.com' => _x( 'Flickr', 'Syn Links' ),
    'youtube.com' => _x( 'YouTube', 'Syn Links' ),
    'linkedin.com' => _x( 'LinkedIn', 'Syn Links' ),
    'tumblr.com' => _x( 'Tumblr', 'Syn Links' ),
    'wordpress.com' => _x( 'WordPress', 'Syn Links' ),
    'news.indiewebcamp.com' => _x( 'IndieNews', 'Syn Links' )
  );
  return apply_filters( 'syn_network_strings', $strings );
}

if (!function_exists('extract_domain_name')) {
	function extract_domain_name($url) {
		$host = parse_url($url, PHP_URL_HOST);
		$host = preg_replace("/^www\./", "", $host);
		return $host;
	}
} 

?>
