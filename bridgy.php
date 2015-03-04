<?php
/*
Function Adapted from wordpress syndication plugin
*/

if (class_exists("WebMentionPlugin")) {
function syn_bridgy_publish_link($response, $source, $target, $post_ID) {
	if (!$post_ID) {
		return;
	}
  $meta = 
	$json = json_decode(wp_remote_retrieve_body($response));
	if (!is_wp_error($response) && $json && $json->url &&
		preg_match('~https?://(?:www\.)?(brid.gy|localhost:8080)/publish/(.*)~', $target, $matches)) {
      $urls = get_post_meta($post_id, 'syndication_urls',true);
      $urls .= '\n' . $json->url;    
      $meta = syn_clean_urls( implode("\n", $_POST[ 'syndication_urls' ]) );
      update_post_meta( $post_id, 'syndication_urls', explode("\n", $meta) );
	}
}
add_action('webmention_post_send', 'syn_bridgy_publish_link', 10, 4);
}

?>
