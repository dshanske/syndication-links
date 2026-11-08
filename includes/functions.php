<?php
/**
 * Template functions: the public API that themes and other plugins call.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the markup for a post's syndication links.
 *
 * @param int|WP_Post|null $post_id Post ID or object. Defaults to the current post.
 * @param array            $args {
 *     Optional. Display arguments. Defaults come from the display settings, via the
 *     `syn_links_display_defaults` filter.
 *
 *     @type string $style            Markup: 'ul' (the default), 'ol', 'p' or 'span'.
 *     @type bool   $text             Whether to show each link's name.
 *     @type bool   $icons            Whether to show each link's icon.
 *     @type string $container-css    Class for the list.
 *     @type string $single-css       Class for each link.
 *     @type string $text-css         Class for the text before the links.
 *     @type bool   $show_text_before Whether to show the text before the links.
 * }
 * @return string|false The markup, or false if there's no such post.
 */
function get_post_syndication_links( $post_id = null, $args = array() ) {
	$post = get_post( $post_id );
	if ( $post ) {
		return Syn_Meta::get_syndication_links( 'post', $post->ID, $args );
	}
	return false;
}

/**
 * Returns the markup for a post's syndication links.
 *
 * An alias of get_post_syndication_links().
 *
 * @param int|WP_Post|null $post_id Post ID or object. Defaults to the current post.
 * @param array            $args    Optional. Display arguments; see get_post_syndication_links().
 * @return string|false The markup, or false if there's no such post.
 */
function get_syndication_links( $post_id = null, $args = array() ) {
	return get_post_syndication_links( $post_id, $args );
}

/**
 * Returns the markup for a comment's syndication links.
 *
 * @param int|WP_Comment|null $comment_id Comment ID or object. Defaults to the current comment.
 * @param array               $args       Optional. Display arguments; see get_post_syndication_links().
 * @return string|false The markup, or false if there's no such comment.
 */
function get_comment_syndication_links( $comment_id = null, $args = array() ) {
	$comment = get_comment( $comment_id );
	if ( $comment ) {
		return Syn_Meta::get_syndication_links( 'comment', $comment->comment_ID, $args );
	}
	return false;
}

/**
 * Displays a post's syndication links.
 *
 * For themes that place the links themselves (see add_theme_support( 'syndication-links' )).
 * The display settings apply: the theme's arguments change how visible links look, but
 * links the site hides stay hidden.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post|null $post Optional. Post ID or object. Defaults to the current post.
 * @param array            $args Optional. Display arguments; see get_post_syndication_links().
 */
function the_syndication_links( $post = null, $args = array() ) {
	$html = get_post_syndication_links( $post, $args );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by Syn_Meta::get_syndication_links().
	}
}

/**
 * Displays a comment's syndication links.
 *
 * For themes that place the links themselves (see add_theme_support( 'syndication-links' )).
 *
 * @since 5.0.0
 *
 * @param int|WP_Comment|null $comment Optional. Comment ID or object. Defaults to the current comment.
 * @param array               $args    Optional. Display arguments; see get_post_syndication_links().
 */
function the_comment_syndication_links( $comment = null, $args = array() ) {
	$html = get_comment_syndication_links( $comment, $args );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by Syn_Meta::get_syndication_links().
	}
}

/**
 * Returns how syndication links are displayed on the current page.
 *
 * On archive pages, links are hidden unless the setting to show them there is on.
 *
 * @since 5.0.0
 *
 * @return string 'icons', 'text', 'iconstext' or 'hidden'.
 */
function syndication_links_display_mode() {
	return (string) Syn_Meta::get_syndication_links_display_option();
}

/**
 * Returns a post's syndication links as URLs.
 *
 * Includes links that other plugins add through the `get_post_syndication_links` filter.
 *
 * @param int|WP_Post $post_id Post ID or object.
 * @return string[]|false The URLs, or false if there's no such post.
 */
function get_post_syndication_links_data( $post_id ) {
	$post = get_post( $post_id );
	if ( $post ) {
		return Syn_Meta::get_syndication_links_data( 'post', $post->ID );
	}
	return false;
}

/**
 * Returns a comment's syndication links as URLs.
 *
 * Includes links that other plugins add through the `get_comment_syndication_links` filter.
 *
 * @param int|WP_Comment $comment_id Comment ID or object.
 * @return string[]|false The URLs, or false if there's no such comment.
 */
function get_comment_syndication_links_data( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( $comment ) {
		return Syn_Meta::get_syndication_links_data( 'comment', $comment->comment_ID );
	}
	return false;
}

/**
 * Adds syndication links to a post.
 *
 * New links go after the existing ones. Invalid and duplicate URLs are dropped.
 *
 * @param int|WP_Post     $post_id Post ID or object.
 * @param string|string[] $uri     A URL, or an array of URLs.
 * @param bool            $replace Optional. Whether to replace the existing links. Default false.
 * @return int|bool The result of update_metadata(), or false if there's no such post or nothing to add.
 */
function add_post_syndication_link( $post_id, $uri, $replace = false ) {
	$post = get_post( $post_id );
	if ( $post ) {
		return Syn_Meta::add_syndication_link( 'post', $post->ID, $uri, $replace );
	}
	return false;
}

/**
 * Adds syndication links to a comment.
 *
 * New links go after the existing ones. Invalid and duplicate URLs are dropped.
 *
 * @param int|WP_Comment  $comment_id Comment ID or object.
 * @param string|string[] $uri        A URL, or an array of URLs.
 * @param bool            $replace    Optional. Whether to replace the existing links. Default false.
 * @return int|bool The result of update_metadata(), or false if there's no such comment or nothing to add.
 */
function add_comment_syndication_link( $comment_id, $uri, $replace = false ) {
	$comment = get_comment( $comment_id );
	if ( $comment ) {
		return Syn_Meta::add_syndication_link( 'comment', $comment->comment_ID, $uri, $replace );
	}
	return false;
}

/**
 * Returns the current post's content, filtered for sending to another site.
 *
 * @param string|null $syndication_uid Optional. UID of the provider the content is for.
 * @return string The content.
 */
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

/**
 * Whether the content is being prepared for syndication.
 *
 * True while the `the_content_syndication` filter runs, so `the_content` callbacks
 * can tell when the content is being sent elsewhere.
 *
 * @return bool
 */
function is_syndication() {
	return doing_filter( 'the_content_syndication' );
}

/**
 * Displays or returns a form that looks up the original of a syndicated copy.
 *
 * Modeled after get_search_form(). A theme can supply its own `originalofform.php`.
 * The form submits to `?original-of=`, which redirects to the post.
 *
 * @param bool $echo Optional. Whether to echo the form. Default true.
 * @return string|void The form markup, if `$echo` is false.
 */
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

/**
 * Registers a syndication provider.
 *
 * Does nothing unless syndication (POSSE) is enabled, since the provider classes
 * are only loaded then.
 *
 * @param Syndication_Provider $object The provider.
 * @return bool|null Whether it was registered, or null if syndication is off.
 */
function register_syndication_provider( $object ) {
	if ( class_exists( 'Post_Syndication' ) ) {
		return Post_Syndication::register( $object );
	}
}

/**
 * Registers a plugin whose recorded links are shown with a post's links, and can be imported.
 *
 * Use this to integrate a cross-posting plugin that stores the URLs of the copies it
 * posted. Its links are added to the links shown for each post, and when the source
 * has a meta query, Settings > Syndication Links > Import can copy them into the
 * posts' own links, so they stay after that plugin is deactivated.
 *
 * Example:
 *
 *     register_syndication_link_source(
 *         'my-crossposter',
 *         array(
 *             'name'       => 'My Crossposter',
 *             'callback'   => function ( $post_id ) {
 *                 return (array) get_post_meta( $post_id, '_my_crossposter_url', false );
 *             },
 *             'meta_query' => array(
 *                 array(
 *                     'key'     => '_my_crossposter_url',
 *                     'compare' => 'EXISTS',
 *                 ),
 *             ),
 *         )
 *     );
 *
 * @since 5.0.0
 *
 * @param string $slug Unique slug (lowercase letters, numbers, dashes and underscores).
 * @param array  $args See Social_Plugins::register_source() for the keys.
 * @return bool Whether the source was registered.
 */
function register_syndication_link_source( $slug, $args ) {
	return Social_Plugins::register_source( $slug, $args );
}

/**
 * Returns the post types that have syndication links and can be syndicated.
 *
 * Always includes `post`, plus the types chosen in the settings (pages by default).
 *
 * @since 5.0.0 Lists each type once.
 *
 * @return string[] Post type names.
 */
function syndication_post_types() {
	$post_types = get_option( 'syndication_post_types' );
	if ( ! is_array( $post_types ) ) {
		$post_types = array();
	}
	$post_types = array_values( array_unique( array_merge( array( 'post' ), $post_types ) ) );
	/**
	 * Filters the post types that have syndication links and can be syndicated.
	 *
	 * @param string[] $post_types Post type names.
	 */
	return apply_filters( 'syndication_post_types', $post_types );
}

/**
 * Returns a post's content, ready for excerpting.
 *
 * Shortcodes and blocks are rendered or removed, and only basic formatting is kept.
 *
 * @param int|WP_Post $post Post ID or object.
 * @return string The content, or '' if there's no such post.
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

	if ( function_exists( 'excerpt_remove_blocks' ) ) {
		$text = excerpt_remove_blocks( $text );
	}
	if ( function_exists( 'excerpt_remove_footnotes' ) ) {
		$text = excerpt_remove_footnotes( $text );
	}

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

/**
 * Shortens text for syndication, at a word boundary.
 *
 * @param string $text  Text to excerpt. HTML is stripped.
 * @param int    $count Optional. Maximum length in characters. Default 200.
 * @return string The excerpt.
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
