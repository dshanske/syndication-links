<?php

abstract class Syndication_Provider {
	protected $uid;
	protected $name;
	protected $service;
	protected $user;

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param string $key API Key if Needed
	 */
	public function __construct( $args = array() ) {
		$defaults = array();
		$defaults = apply_filters( 'syn_provider_defaults', $defaults );
		$r        = wp_parse_args( $args, $defaults );
		add_filter( 'micropub_syndicate-to', array( $this, 'syndicate_to' ), 10, 2 );
	}

	public function syndicate_to( $targets, $user_id ) {
		$targets[] = $this->get();
		return $targets;
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
	 * Given a post try to POSSE it to a given network
	 *
	 * @return array of results
	 */
	abstract public function posse( $post_id = null );

}

function register_syndication_provider( $object ) {
	return Post_Syndication::register( $object );
}

