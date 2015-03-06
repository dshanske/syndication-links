<?php
/**
 * Plugin Name: Syndication Links
 * Plugin URI: http://wordpress.org/plugins/syndication-links
 * Description: Add and display Syndication Links
 * Version: 1.0.0
 * Author: David Shanske
 * Author URI: http://david.shanske.com
 */

require_once( plugin_dir_path( __FILE__ ) . '/syn-postmeta.php');
require_once( plugin_dir_path( __FILE__ ) . '/syn-display.php');
require_once( plugin_dir_path( __FILE__ ) . '/syn-config.php');
require_once( plugin_dir_path( __FILE__ ) . '/bridgy.php');
require_once( plugin_dir_path( __FILE__ ) . '/social-plugins.php');

// User/H-Card Functions
require_once( plugin_dir_path( __FILE__ ) . '/user-config.php');
require_once( plugin_dir_path( __FILE__ ) . '/hcard-widget.php');

function syndication_scripts() {
 	$options = get_option('syndication_content_options');
  if ($options['fontawesome'] == 1) {
    if ($options['bw']==1) {
      wp_enqueue_style( 'syndication-style', plugin_dir_url( __FILE__ ) . 'css/awesome-bw.min.css');
    }
    else {
      wp_enqueue_style( 'syndication-style', plugin_dir_url( __FILE__ ) . 'css/awesome.min.css');
    }
  }
  else {
    if ($options['bw']==1) {
      wp_enqueue_style( 'syndication-style', plugin_dir_url( __FILE__ ) . 'css/syn-bw.min.css');	
    }
    else { 
      wp_enqueue_style( 'syndication-style', plugin_dir_url( __FILE__ ) . 'css/syn.min.css');
    }
  }
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

/**
* Filters incoming URLs.
*
* @param array $urls An array of URLs to filter.
* @return array A filtered array of unique URLs.
* @uses syn_clean_url
*/
function syn_clean_urls($urls) {
  $array = array_map('syn_clean_url', $urls);
  return array_filter(array_unique($array));
}

/**
* Filters a single syndication URL.
*
* @param string $string A string that is expected to be a syndication URL.
* @return string|bool The filtered and escaped URL string, or FALSE if invalid.
* @used-by syn_clean_urls
*/
function syn_clean_url($string) {
  $url = trim($string);
  if ( !filter_var($url, FILTER_VALIDATE_URL) )
  { return false ; }
  $url = esc_url_raw($url);
  return $url;
}

// Return Syndication URLs as part of the JSON Rest API
add_filter("json_prepare_post",'json_rest_add_synmeta',10,3);

function json_rest_add_synmeta($_post,$post,$context) {
  $syn = get_post_meta( $post["ID"], 'syndication_urls');
  if (!empty($syn)) { 
      $urls = explode("\n", $syn);
      $_post['syndication'] = $urls; 
    }  
  return $_post;
}

?>
