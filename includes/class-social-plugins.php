<?php

add_action( 'init' , array('social_plugins', 'init') );

class social_plugins {
	public static function init() {
		add_filter('syn_add_links', array('social_plugins', 'add_syn_plugins') );
		if (class_exists("WebMentionPlugin")) {
			add_action('webmention_post_send', array('social_plugins', 'bridgy_publish_link'), 10, 4);
		}
	}

	public static function add_syn_plugins($urls) {
  	$see_on = array();
  	if (class_exists("Social")){
    	$see_on = self::getURLsFromSocial();
  	}
  	elseif ( defined ( 'NextScripts_SNAP_Version' ) ) {
    	$see_on = self::getURLsFromSNAP();
  	}
  	return array_merge($see_on, $urls);
	}
	public static function getURLsFromSocial() {
		$Social = new Social();
		$ids = get_post_meta( get_the_ID(), "_social_broadcasted_ids", true );
		$services = $Social->instance()->services();
		$broadcasts = array();
		$see_on_social = "";
		if (is_array($ids) and count($ids)) {
			foreach ($services as $key => $service) {
				if (isset($ids[$key]) and count($ids[$key])) {
					$broadcasted = true;
        	foreach ($ids[$key] as $user_id => $broadcasted) {
						$account = $service->account($user_id);
						foreach ($broadcasted as $broadcasted_id => $data) {
							if ($account === false) {
								$class = 'Social_Service_'.$key.'_Account';
								$account = new $class($data['account']);
								if (!$account->has_user() and $key == 'twitter') {
									$recovered = $service->recover_broadcasted_tweet_data($broadcasted_id, $post->ID);
									if (isset($recovered->user)) {
										$data['account']->user = $recovered->user;
										$account = new $class($data['account']);
									}
								}
							}
							$broadcasted = esc_html($service->title());
							if (isset($broadcasted_id)) {
								if ($account->has_user() or $service->key() != 'twitter') {
									$url = $service->status_url($account->username(), $broadcasted_id);
									if (!empty($url)) {
										$broadcasts[]  = esc_url($url);
									}
								}
							}
						}
					}
				}
			}
		}
  	return $broadcasts;
	}

	public static function getURLsFromSNAP() {
		global $nxs_snapAvNts;
		global $post;
		$broadcasts = array();
		$snap_options = get_option('NS_SNAutoPoster');
		$urlmap = array (
			'AP' => array(),
			'BG' => array(),
			'DA' => array(),
			'DI' => array(),
			'DL' => array(),
			'FB' => array( 'url' => '%BASE%/posts/%pgID%' ),
			'FF' => array(),
			'FL' => array(),
			'FP' => array(),
			'GP' => array(),
			'IP' => array(),
			'LI' => array(),
			'LJ' => array(),
			'PK' => array(),
			'PN' => array(),
			'SC' => array(),
			'ST' => array(),
			'SU' => array(),
			'TR' => array( 'url'=>'%BASE%/post/%pgID%' ),
			'TW' => array( 'url'=>'%BASE%/status/%pgID%' ),
			'VB' => array(),
			'VK' => array(),
			'WP' => array(),
			'YT' => array(),
  	);
		foreach ( $nxs_snapAvNts as $key => $serv ) {
		/* all SNAP entries are in separate meta entries for the post based on the service name's "code" */
			$mkey = 'snap'. $serv['code'];
			$urlkey = $serv['lcode'].'URL';
			$okey = $serv['lcode'];
			$metas = maybe_unserialize(get_post_meta(get_the_ID(), $mkey, true ));
			if ( !empty( $metas ) && is_array ( $metas ) ) {
				foreach ( $metas as $cntr => $m ) {
					$url = false;
					if ( isset ( $m['isPosted'] ) && $m['isPosted'] == 1 ) {
					/* this should be available for some services, for example Tumblr,
					 * but buggy and misses slashes so URL ends up invalid
					if ( isset( $m['postURL'] ) && !empty( $m['postURL'] ) ) {
						$url = $m['postURL'];
					}
					else {
					*/
						$base = (isset( $urlmap[ $serv['code'] ]['url'])) ? $urlmap[ $serv['code'] ]['url'] : false;
						if ( $base != false ) {
						/* Facebook exception, why not */
							if ( $serv['code'] == 'FB' ) {
								$pos = strpos( $m['pgID'],'_' );
								$pgID = ( $pos == false ) ? $m['pgID'] : substr( $m['pgID'], $pos + 1 );
							}
							else {
								$pgID = $m['pgID'];
							}
							$o = $snap_options[ $okey ][$cntr];
							$search = array('%BASE%', '%pgID%' );
							$replace = array ( $o[ $urlkey ], $pgID );
							$url = str_replace ( $search, $replace, $base );
						}
				/* } */
						if ( $url != false ) {
							$url = preg_replace('~(^|[^:])//+~', '\\1/', $url);
							$broadcasts[] = $url;
						}
					}
				}
			}
		}
  	return $broadcasts;
	}

	public static function bridgy_publish_link($response, $source, $target, $post_ID) {
		if (!$post_ID) {
			return;
		}
		$meta = $json = json_decode(wp_remote_retrieve_body($response));
		if (!is_wp_error($response) && $json && $json->url && preg_match('~https?://(?:www\.)?(brid.gy|localhost:8080)/publish/(.*)~', $target, $matches)) {
			$urls = get_post_meta($post_id, 'syndication_urls',true);
			$urls .= '\n' . $json->url;    
			$meta = syn_clean_urls( implode("\n", $_POST[ 'syndication_urls' ]) );
			update_post_meta( $post_id, 'syndication_urls', explode("\n", $meta) );
		}
	}

} // End Class 
