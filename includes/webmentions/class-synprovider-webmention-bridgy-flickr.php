<?php
/**
 * Provider: Flickr via Bridgy Publish.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syndicates to Flickr via Bridgy Publish.
 */
class SynProvider_Webmention_Bridgy_Flickr extends SynProvider_Webmention_Bridgy {

	/**
	 * Bridgy can't delete posts on this service.
	 *
	 * @var bool
	 */
	protected $supports_delete = false;

	/**
	 * Sets the provider's name and UID.
	 *
	 * @param array $args Optional. Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Flickr via Bridgy', 'syndication-links' );
		$this->uid  = 'webmention-flickr-bridgy';

		// Parent Constructor.
		parent::__construct( $args );
	}

	/**
	 * Returns the webmention target.
	 *
	 * @return string The target URL.
	 */
	public function get_target() {
		return 'https://brid.gy/publish/flickr';
	}
}
