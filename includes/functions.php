<?php

function get_syndication_links( $meta_type, $object_id = null, $args = array() ) {
		return Syn_Meta::get_syndication_links( $meta_type, $object_id, $args );
}

function get_post_syndication_links( $post_id = null, $args = array() ) {
		return Syn_Meta::get_post_syndication_links( $post_id, $args );
}

function get_comment_syndication_links( $comment_id = null, $args = array() ) {
		return Syn_Meta::get_comment_syndication_links( $comment_id, $args );
}

function get_syndication_links_data( $object = null ) {
		return Syn_Meta::get_syndication_links_data( $object );
}

function get_the_content_syndication( $syndication_uid = null ) {

	/** This filter is documented in wp-includes/post-template.php */
	$content = apply_filters( 'the_content', get_the_content() );
	/**
	 * Filters the post content for use in syndication.
	 *
	 *
	 * @param string $content   The current post content.
	 * @param string $syndication_uid The UID for this syndication
	 *
	 */
	return apply_filters( 'the_content_syndication', $content, $syndication_uid );
}

function is_syndication() {
	return doing_filter( 'the_content_syndication' );
}
