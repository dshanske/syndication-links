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

function add_syndication_link( $post_id, $uri, $replace = false ) {
	return Syn_Meta::add_syndication_link( $post_id, $uri, $replace );
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
	if ( '' != $of_form_template ) {
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
		echo $form;
	} else {
		return $form;
	}

}
