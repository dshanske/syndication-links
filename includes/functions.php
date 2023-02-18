<?php


function get_post_syndication_links( $post_id = null, $args = array() ) {
	$post = get_post( $post_id );
	if ( $post ) {
		return Syn_Meta::get_syndication_links( 'post', $post->ID, $args );
	}
	return false;
}

function get_syndication_links( $post_id = null, $args = array() ) {
	return get_post_syndication_links( $post_id, $args );
}

function get_comment_syndication_links( $comment_id = null, $args = array() ) {
	$comment = get_comment( $comment_id );
	if ( $comment ) {
		return Syn_Meta::get_syndication_links( 'comment', $comment->comment_ID, $args );
	}
	return false;
}

function get_post_syndication_links_data( $post_id ) {
	$post = get_post( $post_id );
	if ( $post ) {
		return Syn_Meta::get_syndication_links_data( 'post', $post->ID );
	}
	return false;
}

function get_comment_syndication_links_data( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( $comment ) {
		return Syn_Meta::get_syndication_links_data( 'comment', $comment->comment_ID );
	}
	return false;
}

function add_post_syndication_link( $post_id, $uri, $replace = false ) {
	$post = get_post( $post_id );
	if ( $post ) {
		return Syn_Meta::add_syndication_link( 'post', $post->ID, $uri, $replace );
	}
	return false;
}

function add_comment_syndication_link( $comment_id, $uri, $replace = false ) {
	$comment = get_comment( $comment_id );
	if ( $comment ) {
		return Syn_Meta::add_syndication_link( 'comment', $comment->comment_ID, $uri, $replace );
	}
	return false;
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

// Modeled after get_search_form
function get_original_of_form( $echo = true ) {
	$of_form_template = locate_template( 'originalofform.php' );
	if ( '' !== $of_form_template ) {
		ob_start();
		require $of_form_template;
		$form = ob_get_clean();
	} else {
		$form = '<form role="search" method="get" class="original-of-form" action="' . esc_url( home_url( '/' ) ) . '">
                <label>
                    <span class="screen-reader-text">' . _x( 'Enter a link to the syndicated copy of a post to get the original page:', 'label', 'syndication-links' ) . '</span>
                    <input type="url" placeholder="http://example.com" class="original-of-field" name="original-of" />
		    </label>
                <input type="submit" class="original-of-submit" value="' . esc_attr_x( 'Lookup Original', 'submit button', 'syndication-links' ) . '" />
		</form>';
	}

	if ( $echo ) {
		echo $form; // phpcs:ignore
	} else {
		return $form;
	}

}

function register_syndication_provider( $object ) {
	if ( class_exists( 'Post_Syndication' ) ) {
		return Post_Syndication::register( $object );
	}
}

function syndication_post_types() {
	$post_types = get_option( 'syndication_post_types' );
	if ( ! is_array( $post_types ) ) {
		$post_types = array();
	}
	$post_types = array_merge( array( 'post' ), $post_types );
	return apply_filters( 'syndication_post_types', $post_types );
}
