<?php
/**
 * Provider: GitHub via Bridgy Publish.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syndicates to GitHub via Bridgy Publish.
 */
class SynProvider_Webmention_Bridgy_Github extends SynProvider_Webmention_Bridgy {

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
		$this->name = __( 'Github via Bridgy', 'syndication-links' );
		$this->uid  = 'webmention-github-bridgy';

		// Parent Constructor.
		parent::__construct( $args );
	}

	/**
	 * Returns the webmention target.
	 *
	 * @return string The target URL.
	 */
	public function get_target() {
		return 'https://brid.gy/publish/github';
	}
}
