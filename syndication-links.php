<?php
/**
 * Plugin Name: Syndication Links
 * Plugin URI: http://david.shanske.com
 * Description: Add and display Syndication Links
 * Version: 0.5.0
 * Author: David Shanske
 * Author URI: http://david.shanske.com
 */

require_once( plugin_dir_path( __FILE__ ) . '/syn-postmeta.php');
require_once( plugin_dir_path( __FILE__ ) . '/syn-display.php');
require_once( plugin_dir_path( __FILE__ ) . '/syn-config.php');
require_once( plugin_dir_path( __FILE__ ) . '/bridgy.php');


function syndication_scripts() {
 	wp_enqueue_style( 'syndication-style', plugin_dir_url( __FILE__ ) . 'syn.min.css');	
}

add_action( 'wp_enqueue_scripts', 'syndication_scripts' );

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

function syn_clean_urls($string) {
  $urls = explode("\n", $string);
  $array=array();
  foreach ( (array) $urls as $url ) {
    $url = trim($url);
    if(!filter_var($url, FILTER_VALIDATE_URL))
      { continue; }
    $url = esc_url_raw($url);
    $array[] = $url;
   }
  $array = array_unique($array);
  return(implode("\n", $array));
 }

?>
