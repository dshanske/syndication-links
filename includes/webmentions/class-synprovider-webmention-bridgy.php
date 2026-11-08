<?php
/**
 * Base class for Bridgy Publish via webmention.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syndicates through Bridgy Publish by webmention, passing the backlink and formatting options.
 */
class SynProvider_Webmention_Bridgy extends SynProvider_Webmention {
	/**
	 * Adds Bridgy's options to the webmention, if the provider is enabled.
	 *
	 * Subclasses set `$uid` before calling this.
	 *
	 * @param array $args Optional. Arguments.
	 */
	public function __construct( $args = array() ) {

		$option = get_option( 'syndication_provider_enable' );
		$enable = is_array( $option ) ? in_array( $this->uid, $option, true ) : false;
		if ( $enable ) {
			add_filter( 'webmention_send_vars', array( $this, 'webmention_send_vars' ), 10, 2 );
		}
		// Parent Constructor.
		parent::__construct( $args );
	}

	/**
	 * Adds Bridgy's options to a webmention sent to this provider's target.
	 *
	 * @param array $body    The webmention's parameters.
	 * @param int   $post_id Post ID.
	 * @return array The parameters.
	 */
	public function webmention_send_vars( $body, $post_id ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( $this->get_target() === urldecode( $body['target'] ) ) {
			$backlink = get_option( 'syndication_backlink' );
			if ( ! empty( $backlink ) ) {
				$body['bridgy_omit_link'] = $backlink;
			}
			if ( 1 === (int) get_option( 'bridgy_ignoreformatting' ) ) {
				$body['bridgy-ignore-formatting'] = 'true';
			}
		}
		return $body;
	}
}
