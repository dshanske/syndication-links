<?php
/**
 * Base class for syndication providers.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * A place posts can be syndicated to.
 *
 * Subclasses set `$uid` and `$name`, and implement posse(). Providers enabled in the
 * settings are offered to Micropub clients through `micropub_syndicate-to`.
 */
abstract class Syndication_Provider {

	/**
	 * Unique Identifier for the Provider.
	 *
	 * @var string
	 */
	protected $uid;

	/**
	 * Display Name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The service, as Micropub's `syndicate-to` describes it.
	 *
	 * Optional keys: `name`, `url` and `photo` (the service's icon).
	 *
	 * @var array
	 */
	protected $service;

	/**
	 * The account on the service, as Micropub's `syndicate-to` describes it.
	 *
	 * Optional keys: `name`, `url` and `photo`.
	 *
	 * @var array
	 */
	protected $user;

	/**
	 * Whether the provider can delete the copies it made.
	 *
	 * @since 5.0.0
	 *
	 * @var bool
	 */
	protected $supports_delete = false;

	/**
	 * Offers the provider to Micropub clients if it's enabled in the settings.
	 *
	 * @param array $args Optional. Arguments, used by subclasses.
	 */
	public function __construct( $args = array() ) {
		$allowlist = get_option( 'syndication_provider_enable', array() );
		if ( is_string( $allowlist ) && empty( $allowlist ) ) {
			$allowlist = array();
		}
		if ( in_array( $this->uid, $allowlist, true ) ) {
			add_filter( 'micropub_syndicate-to', array( $this, 'syndicate_to' ), 10, 2 );
		}
	}

	/**
	 * Adds the provider to Micropub's syndication targets.
	 *
	 * @param array $targets Syndication targets.
	 * @param int   $user_id ID of the user making the request.
	 * @return array The targets.
	 */
	public function syndicate_to( $targets, $user_id ) {
		// A provider that can't be chosen in the editor, such as one without a token, isn't offered either.
		if ( ! Post_Syndication::disabled( $this->uid ) ) {
			$targets[] = $this->get();
		}
		return array_filter( $targets );
	}

	/**
	 * Returns the provider as a Micropub syndication target.
	 *
	 * Empty values are left out.
	 *
	 * @return array {
	 *     The target.
	 *
	 *     @type string $uid     The provider's UID.
	 *     @type string $name    Its display name.
	 *     @type array  $service Optional. The service: `name`, `url` and `photo`.
	 *     @type array  $user    Optional. The account: `name`, `url` and `photo`.
	 *     @type bool   $checked Whether it's selected by default.
	 * }
	 */
	public function get() {
		$return = array(
			'name'    => $this->name,
			'uid'     => $this->uid,
			'service' => $this->service,
			'user'    => $this->user,
			'checked' => Post_Syndication::checked( $this->uid ),
		);
		return array_filter( $return );
	}

	/**
	 * Returns the provider's unique ID.
	 *
	 * @return string
	 */
	public function get_uid() {
		return $this->uid;
	}

	/**
	 * Returns the provider's display name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns the account on the service.
	 *
	 * @return array|null
	 */
	public function get_user() {
		return $this->user;
	}

	/**
	 * Returns the service.
	 *
	 * @return array|null
	 */
	public function get_service() {
		return $this->service;
	}

	/**
	 * Whether the provider's checkbox is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled() {
		return false;
	}

	/**
	 * Whether the provider's checkbox is checked by default.
	 *
	 * @return bool
	 */
	public function is_checked() {
		return false;
	}

	/**
	 * Whether the provider can delete the copies it made, when a post is trashed.
	 *
	 * @since 5.0.0
	 *
	 * @return bool
	 */
	public function supports_delete() {
		return $this->supports_delete;
	}

	/**
	 * Deletes the copies of a post this provider made.
	 *
	 * Runs after the post is trashed or deleted, for providers that support it.
	 *
	 * @since 5.0.0
	 *
	 * @param int      $post_id Post ID. The post may no longer exist.
	 * @param string   $source  The post's URL before it was trashed.
	 * @param string[] $copies  The copies' URLs, from the syndication log.
	 * @return array|WP_Error|null The response, which is logged, an error, or null if there's nothing to delete.
	 */
	public function delete( $post_id, $source, $copies = array() ) {
		return null;
	}

	/**
	 * Syndicates a post.
	 *
	 * Implementations add the URL of the copy to the post's syndication links.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to the current post.
	 * @return array|WP_Error The response, which is logged, or an error.
	 */
	abstract public function posse( $post_id = null );
}
