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
	 * @param string $content   The current post content.
	 * @param string $syndication_uid The UID for this syndication
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

/* Return post content ready for excerpting
 *
 * @param int|WP_Post $post Post Object or ID.
 * @return string String ready for excerpting.
 *
 */
function syn_get_post_content( $post ) {
	$allowed = array(
		'a'          => array(
			'href' => array(),
		),
		'abbr'       => array(),
		'b'          => array(),
		'br'         => array(),
		'code'       => array(),
		'ins'        => array(),
		'del'        => array(),
		'em'         => array(),
		'i'          => array(),
		'q'          => array(),
		'strike'     => array(),
		'strong'     => array(),
		'time'       => array(
			'datetime' => array(),
		),
		'blockquote' => array(),
		'pre'        => array(),
		'p'          => array(),
		'h1'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'h5'         => array(),
		'h6'         => array(),
		'ul'         => array(),
		'li'         => array(),
		'ol'         => array(),
		'span'       => array(),
		'img'        => array(
			'src'    => array(),
			'alt'    => array(),
			'title'  => array(),
			'width'  => array(),
			'height' => array(),
			'srcset' => array(),
		),
		'figure'     => array(),
		'figcaption' => array(),
		'picture'    => array(
			'srcset' => array(),
			'type'   => array(),
		),
		'video'      => array(
			'poster' => array(),
			'src'    => array(),
		),
		'audio'      => array(
			'duration' => array(),
			'src'      => array(),
		),
		'track'      => array(
			'label'   => array(),
			'src'     => array(),
			'srclang' => array(),
			'kind'    => array(),
		),
		'source'     => array(
			'src'    => array(),
			'srcset' => array(),
			'type'   => array(),

		),
		'hr'         => array(),
	);

	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
		$text = get_the_content( '', false, $post );

		$text = strip_shortcodes( $text );
		$text = excerpt_remove_blocks( $text );
		$text = excerpt_remove_footnotes( $text );
		/*
		* Temporarily unhook wp_filter_content_tags() since any tags
		* within the excerpt are stripped out. Modifying the tags here
		* is wasteful and can lead to bugs in the image counting logic.
		*/
		$filter_image_removed = remove_filter( 'the_content', 'wp_filter_content_tags', 12 );

		/*
		* Temporarily unhook do_blocks() since excerpt_remove_blocks( $text )
		* handles block rendering needed for excerpt.
		*/
		$filter_block_removed = remove_filter( 'the_content', 'do_blocks', 9 );

		/** This filter is documented in wp-includes/post-template.php */
		$text = apply_filters( 'the_content', $text );

		// Restore the original filter if removed.
	if ( $filter_block_removed ) {
		add_filter( 'the_content', 'do_blocks', 9 );
	}

		/*
		* Only restore the filter callback if it was removed above. The logic
		* to unhook and restore only applies on the default priority of 10,
		* which is generally used for the filter callback in WordPress core.
		*/
	if ( $filter_image_removed ) {
		add_filter( 'the_content', 'wp_filter_content_tags', 12 );
	}

	return wp_kses( $text, $allowed );
}

/* Customized Excerpt Function for Syndication
 *
 * @param text Text to excerpt.
 * @param int Character Count. Optional.
 * @return string Excerpted String.
 *
 */
function syn_excerpt( $text, $count = 200 ) {
	$text = wp_strip_all_tags( $text );
	if ( $count > strlen( $text ) ) {
		return $text;
	}
	$wrap = wordwrap( $text, $count );
	$wrap = explode( "\n", $wrap );
	return $wrap[0];
}
