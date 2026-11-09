<?php
/**
 * Detecting the Fediverse software of linked servers.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Syn_Fediverse_Detection', 'init' ) );
add_action( 'admin_init', array( 'Syn_Fediverse_Detection', 'admin_init' ), 11 );

/**
 * Finds the icon for links to Fediverse servers, whatever their domain.
 *
 * Fediverse servers run on any domain, so no list of domains can recognize them.
 * When a syndication link to a domain without its own icon is saved, a background
 * job reads that server's NodeInfo (`/.well-known/nodeinfo`), which names the
 * software it runs, and maps the software to an icon: Mastodon, Pixelfed,
 * PeerTube, Lemmy and so on. The result is cached per host, and
 * Syn_Link_Domain_Icon_Map::url_to_name() reads it. Nothing is fetched while a
 * page is shown.
 *
 * It only contacts servers a post or comment already links to, and can be turned
 * off on the settings page.
 *
 * @since 5.0.0
 */
class Syn_Fediverse_Detection {

	/**
	 * The option that turns detection on. On by default.
	 *
	 * @var string
	 */
	const OPTION = 'syndication-links_fediverse_detection';

	/**
	 * The option caching what was found for each host. Not autoloaded.
	 *
	 * @var string
	 */
	const CACHE = 'syndication_links_fediverse_hosts';

	/**
	 * The cron hook that checks a host.
	 *
	 * @var string
	 */
	const HOOK = 'syn_detect_fediverse_software';

	/**
	 * How long a host without a Fediverse icon is cached before it's checked again.
	 *
	 * @var int
	 */
	const RECHECK = MONTH_IN_SECONDS;

	/**
	 * The most hosts kept in the cache; the ones checked longest ago are dropped first.
	 *
	 * @var int
	 */
	const MAX_HOSTS = 2000;

	/**
	 * The cached hosts, loaded once per request.
	 *
	 * @var array|null
	 */
	private static $hosts = null;

	/**
	 * Registers the setting, and hooks link saving and the background check when it's on.
	 */
	public static function init() {
		register_setting(
			'syndication_display',
			self::OPTION,
			array(
				'type'         => 'boolean',
				'description'  => 'Detect the Fediverse software of linked servers, to show their icon',
				'show_in_rest' => true,
				'default'      => true,
			)
		);
		add_action( self::HOOK, array( __CLASS__, 'detect' ) );
		if ( ! self::enabled() ) {
			return;
		}
		foreach ( array( 'post', 'comment' ) as $type ) {
			add_action( "added_{$type}_meta", array( __CLASS__, 'meta_saved' ), 10, 4 );
			add_action( "updated_{$type}_meta", array( __CLASS__, 'meta_saved' ), 10, 4 );
		}
	}

	/**
	 * Adds the setting to the General tab.
	 */
	public static function admin_init() {
		add_settings_field(
			self::OPTION,
			__( 'Detect Fediverse Servers', 'syndication-links' ),
			array( __CLASS__, 'setting_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => self::OPTION,
			)
		);
	}

	/**
	 * Displays the setting, with what it does.
	 *
	 * @param array $args The field's arguments.
	 */
	public static function setting_callback( array $args ) {
		Syn_Config::checkbox_callback( $args );
		echo '<p class="description">' . esc_html__( 'When a link to an unknown site is saved, ask that site in the background whether it runs Fediverse software, such as Mastodon or Pixelfed, to show the right icon.', 'syndication-links' ) . '</p>';
	}

	/**
	 * Whether detection is on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) get_option( self::OPTION, true );
	}

	/**
	 * Queues the hosts of newly saved syndication links.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post or comment ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value The links.
	 */
	public static function meta_saved( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'mf2_syndication' !== $meta_key ) {
			return;
		}
		foreach ( (array) $meta_value as $url ) {
			if ( is_string( $url ) ) {
				self::maybe_schedule( $url );
			}
		}
	}

	/**
	 * Returns the cache key for a link's host, matching Syn_Link_Domain_Icon_Map::url_to_name().
	 *
	 * @param string $url The link.
	 * @return string|null The host, or null if it isn't a web link with a usable host.
	 */
	public static function host( $url ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return null;
		}
		$host = str_replace( 'www.', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
		if ( ! preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/D', $host ) ) {
			return null;
		}
		return $host;
	}

	/**
	 * Schedules a check of a link's host, if it has no icon and isn't freshly cached.
	 *
	 * @param string $url   The link.
	 * @param int    $delay Optional. Seconds from now to run the check. Default 0.
	 * @return bool Whether a check was scheduled.
	 */
	public static function maybe_schedule( $url, $delay = 0 ) {
		$host = self::host( $url );
		if ( ! $host || ! self::enabled() ) {
			return false;
		}
		$cached = self::get_host( $host );
		if ( $cached && ( '' !== $cached['icon'] || time() - $cached['checked'] < self::RECHECK ) ) {
			return false;
		}
		// Only hosts that would otherwise get a generic icon.
		if ( ! Syn_Link_Domain_Icon_Map::is_generic( Syn_Link_Domain_Icon_Map::url_to_name( $url ) ) ) {
			return false;
		}
		if ( wp_next_scheduled( self::HOOK, array( $host ) ) ) {
			return false;
		}
		return (bool) wp_schedule_single_event( time() + max( 0, (int) $delay ), self::HOOK, array( $host ) );
	}

	/**
	 * Returns what was found for a host.
	 *
	 * @param string $host The host.
	 * @return array|null `software`, `icon` (empty for none) and `checked` (a timestamp), or null if it hasn't been checked.
	 */
	public static function get_host( $host ) {
		if ( null === self::$hosts ) {
			$hosts       = get_option( self::CACHE, array() );
			self::$hosts = is_array( $hosts ) ? $hosts : array();
		}
		return isset( self::$hosts[ $host ] ) ? self::$hosts[ $host ] : null;
	}

	/**
	 * Returns the icon found for a host.
	 *
	 * @param string $host The host, as Syn_Link_Domain_Icon_Map::url_to_name() has it.
	 * @return string|null The icon name, or null if none was found.
	 */
	public static function get_icon( $host ) {
		$cached = self::get_host( $host );
		return ( $cached && '' !== $cached['icon'] ) ? $cached['icon'] : null;
	}

	/**
	 * Clears the in-request copy of the cache.
	 */
	public static function flush() {
		self::$hosts = null;
	}

	/**
	 * Checks a host's NodeInfo and caches the result. Runs in the background.
	 *
	 * @param string $host The host.
	 * @return array|null What was cached, or null if the host isn't valid or detection is off.
	 */
	public static function detect( $host ) {
		$host = self::host( 'https://' . $host );
		if ( ! $host || ! self::enabled() ) {
			return null;
		}
		$nodeinfo = self::fetch_nodeinfo( $host );
		$software = '';
		$icon     = '';
		if ( $nodeinfo ) {
			$software = isset( $nodeinfo['software']['name'] ) && is_string( $nodeinfo['software']['name'] ) ? strtolower( $nodeinfo['software']['name'] ) : '';
			$software = preg_replace( '/[^a-z0-9._-]/', '', $software );
			$icon     = self::software_icon( $software, isset( $nodeinfo['protocols'] ) ? (array) $nodeinfo['protocols'] : array() );
		}
		$result = array(
			'software' => $software,
			'icon'     => $icon,
			'checked'  => time(),
		);

		self::flush();
		$hosts = get_option( self::CACHE, array() );
		$hosts = is_array( $hosts ) ? $hosts : array();
		unset( $hosts[ $host ] );
		$hosts[ $host ] = $result;
		if ( count( $hosts ) > self::MAX_HOSTS ) {
			uasort(
				$hosts,
				function ( $a, $b ) {
					return $b['checked'] - $a['checked'];
				}
			);
			$hosts = array_slice( $hosts, 0, self::MAX_HOSTS, true );
		}
		update_option( self::CACHE, $hosts, false );
		self::flush();
		return $result;
	}

	/**
	 * Fetches a host's NodeInfo document.
	 *
	 * Reads `/.well-known/nodeinfo` and follows its newest 2.x schema link. Both
	 * requests use wp_safe_remote_get(), so private and local addresses are refused.
	 *
	 * @param string $host The host.
	 * @return array|null The NodeInfo document, or null if there's none.
	 */
	public static function fetch_nodeinfo( $host ) {
		$index = self::get_json( 'https://' . $host . '/.well-known/nodeinfo' );
		if ( ! $index || empty( $index['links'] ) || ! is_array( $index['links'] ) ) {
			return null;
		}
		$best    = null;
		$version = '';
		foreach ( $index['links'] as $link ) {
			if ( ! is_array( $link ) || empty( $link['rel'] ) || empty( $link['href'] ) || ! is_string( $link['rel'] ) || ! is_string( $link['href'] ) ) {
				continue;
			}
			if ( preg_match( '#^https?://nodeinfo\.diaspora\.software/ns/schema/(2\.\d+)$#', $link['rel'], $matches ) && version_compare( $matches[1], $version, '>' ) ) {
				$version = $matches[1];
				$best    = $link['href'];
			}
		}
		if ( ! $best || ! in_array( wp_parse_url( $best, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return null;
		}
		return self::get_json( $best );
	}

	/**
	 * Fetches a URL and decodes its JSON.
	 *
	 * @param string $url The URL.
	 * @return array|null The decoded object, or null on any failure.
	 */
	private static function get_json( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 2,
				'limit_response_size' => 65536,
				'headers'             => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $json ) ? $json : null;
	}

	/**
	 * Maps Fediverse software to an icon.
	 *
	 * Software without an icon of its own gets the ActivityPub icon if it speaks
	 * ActivityPub, or the diaspora icon if it speaks only diaspora.
	 *
	 * @param string   $software  The software name from NodeInfo, lowercase.
	 * @param string[] $protocols The protocols from NodeInfo.
	 * @return string The icon name, or an empty string for none.
	 */
	public static function software_icon( $software, $protocols = array() ) {
		$map = array(
			'mastodon'   => 'mastodon',
			'hometown'   => 'mastodon',
			'glitch-soc' => 'mastodon',
			'pixelfed'   => 'pixelfed',
			'peertube'   => 'peertube',
			'lemmy'      => 'lemmy',
			'misskey'    => 'misskey',
			'sharkey'    => 'misskey',
			'iceshrimp'  => 'misskey',
			'calckey'    => 'misskey',
			'foundkey'   => 'misskey',
			'cherrypick' => 'misskey',
			'firefish'   => 'firefish',
			'pleroma'    => 'pleroma',
			'akkoma'     => 'pleroma',
			'diaspora'   => 'diaspora',
			'forgejo'    => 'forgejo',
			'gitea'      => 'gitea',
			'wordpress'  => 'wordpress', // phpcs:ignore WordPress.WP.CapitalPDangit
			'bookwyrm'   => 'bookwyrm',
		);

		/**
		 * Filters the icons for Fediverse software, keyed by the lowercase software name from NodeInfo.
		 *
		 * Software that isn't listed gets the ActivityPub icon if it speaks ActivityPub.
		 *
		 * @since 5.0.0
		 *
		 * @param string[] $map Icon names, keyed by software name.
		 */
		$map = apply_filters( 'syn_link_fediverse_software', $map );

		if ( isset( $map[ $software ] ) && is_string( $map[ $software ] ) && Syn_Link_Domain_Icon_Map::get_icon_filename( $map[ $software ] ) ) {
			return $map[ $software ];
		}
		$protocols = array_map( 'strtolower', array_filter( $protocols, 'is_string' ) );
		if ( in_array( 'activitypub', $protocols, true ) ) {
			return 'activitypub';
		}
		if ( in_array( 'diaspora', $protocols, true ) ) {
			return 'diaspora';
		}
		return '';
	}
}
