<?php
/*
Function Adapted from wordpress syndication plugin
*/

if (class_exists("WebMentionPlugin")) {
function store_bridgy_publish_link($response, $source, $target, $post_ID) {
	if (!$post_ID) {
		return;
	}

	$json = json_decode(wp_remote_retrieve_body($response));
	if (!is_wp_error($response) && $json && $json->url &&
		preg_match('~https?://(?:www\.)?(brid.gy|localhost:8080)/publish/(.*)~', $target, $matches)) {
			if (strpos($json->url,'https://www.facebook.com/') !== false) {
			     update_post_meta( $post_id, 'sc_fb_url', $json->url );
			}
                        if (strpos($json->url,'https://www.twitter.com/') !== false) {
			     update_post_meta( $post_id, 'sc_fb_url', $json->url );
                        }
	}
}
add_action('webmention_post_send', 'store_bridgy_publish_link', 10, 4);
}

?>
