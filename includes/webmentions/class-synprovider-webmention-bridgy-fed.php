<?php
/**
 * Provider: Bridgy Fed, which bridges the post to other networks such as the fediverse.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syndicates to Bridgy Fed, which bridges the post to other networks such as the fediverse.
 */
class SynProvider_Webmention_Bridgy_Fed extends SynProvider_Webmention {

	/**
	 * Sets the provider's name and UID.
	 *
	 * @param array $args Optional. Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Bridgy Fed', 'syndication-links' );
		$this->uid  = 'bridgy-fed';

		// Parent Constructor.
		parent::__construct( $args );
	}

	/**
	 * Returns the webmention target.
	 *
	 * @return string The target URL.
	 */
	public function get_target() {
		return 'https://fed.brid.gy';
	}
}
