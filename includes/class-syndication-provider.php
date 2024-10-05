<?php

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
	 * Service.
	 *
	 * @param array {
	 *  @type string $name Name of Provider.
	 *  @type string $url URL of Provider.
	 *  @type string $photo Icon/Photo of Service
	 * }
	 */
	protected $service;

	/**
	 * User Information.
	 *
	 * @param array {
	 *  @type string $name Username.
	 *  @type string $url User URL.
	 *  @type string $photo User Photo.
	 * }
	 */
	protected $user;

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Array of Arguments
	 */
	public function __construct( $args = array() ) {
		$defaults  = array();
		$defaults  = apply_filters( 'syn_provider_defaults', $defaults );
		$r         = wp_parse_args( $args, $defaults );
		$allowlist = get_option( 'syndication_provider_enable', array() );
		if ( is_string( $allowlist ) && empty( $allowlist ) ) {
			$allowlist = array();
		}
		if ( in_array( $this->uid, $allowlist, true ) ) {
			add_filter( 'micropub_syndicate-to', array( $this, 'syndicate_to' ), 10, 2 );
		}
	}

	public function syndicate_to( $targets, $user_id ) {
		$targets[] = $this->get();
		return array_filter( $targets );
	}

	/**
	 * Returns information about a syndication target
	 *
	 * @return array $target {
	 *  @string $uid  UID
	 *  @string $name Name
	 *  @array $service {
	 *   Optional. An array of service details.
	 *  @string $name Name of Service.
	 *  @string $url URL of Service.
	 *  @string $photo URL of Photo/Service Logo
	 *  }
	 *  @array $user {
	 *  Optional. Details of the User.
	 *  @string $name Name of User.
	 *  @string $url URL of User on Service.
	 *  @string $photo URL of User Photo
	 *  }
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

	public function get_uid() {
		return $this->uid;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_user() {
		return $this->user;
	}

	public function get_service() {
		return $this->service;
	}

	/**
	 * Should checkbox be disabled
	 */
	public function is_disabled() {
		return false;
	}

	/**
	 * Should checkbox be checked
	 */
	public function is_checked() {
		return false;
	}

	/**
	 * Given a post try to POSSE it to a given network
	 *
	 * @return array to extract log data from.
	 */
	abstract public function posse( $post_id = null );
}
