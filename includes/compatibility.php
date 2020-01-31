<?php

namespace syndication_links;

function save_wptotwitter_to_syndication_links( $connection, $id ) {
	// If someone is doing a a credentials check to twitter, we won't have an ID.
	if ( false === $id ) {
		return;
	}

	$url = sprintf(
		'https://twitter.com/%s/status/%s',
		$connection->body->user->screen_name,
		$connection->body->id_str
	);

	\Syn_Meta::add_syndication_link( $id, $url );
}
add_action( 'wpt_tweet_posted', __NAMESPACE__ . '\save_wptotwitter_to_syndication_links', 10, 2 );
