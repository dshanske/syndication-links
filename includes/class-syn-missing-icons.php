<?php
/**
 * The list of linked sites that have no icon.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Syn_Missing_Icons', 'init' ) );

/**
 * Lists the sites that a site's syndication links point to but that show a generic icon.
 *
 * It reads the stored links of posts and comments (`mf2_syndication`), counts the
 * links to each host, and keeps the hosts whose icon is generic. The list is
 * shown on the Icons tab of the settings page and by
 * `wp syndication-links icons missing`. Each host can be reported in a
 * prefilled GitHub issue, and the hosts can be checked for Fediverse software
 * (see Syn_Fediverse_Detection), which covers links saved before that check
 * existed. The list is built only on request and cached, never on the front end.
 *
 * @since 5.0.0
 */
class Syn_Missing_Icons {

	/**
	 * The transient caching the list.
	 *
	 * @var string
	 */
	const CACHE = 'syndication_links_missing_icons';

	/**
	 * Meta rows read per query.
	 *
	 * @var int
	 */
	const BATCH = 500;

	/**
	 * The most hosts kept in the list, those with the most links first.
	 *
	 * @var int
	 */
	const MAX_HOSTS = 500;

	/**
	 * Seconds between the background Fediverse checks queued from the list.
	 *
	 * @var int
	 */
	const DETECT_INTERVAL = 5;

	/**
	 * Where icon requests are filed.
	 *
	 * @var string
	 */
	const ISSUES = 'https://github.com/dshanske/syndication-links/issues/new';

	/**
	 * Hooks the settings page actions and the WP-CLI command.
	 */
	public static function init() {
		add_action( 'admin_post_syn_missing_icons', array( __CLASS__, 'handle_request' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'syndication-links icons missing', array( __CLASS__, 'cli' ) );
		}
	}

	/**
	 * Counts the stored links to each host.
	 *
	 * @return array[] Keyed by host: `links` (the number of links) and `url` (one of them).
	 */
	public static function count_hosts() {
		global $wpdb;
		$hosts = array();
		foreach ( array( $wpdb->postmeta, $wpdb->commentmeta ) as $table ) {
			$last = 0;
			do {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The table names are core's; values are prepared. Read in batches, only on request.
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM $table WHERE meta_key = %s AND meta_id > %d ORDER BY meta_id LIMIT %d", 'mf2_syndication', $last, self::BATCH ) );
				foreach ( $rows as $row ) {
					$last = (int) $row->meta_id;
					foreach ( (array) maybe_unserialize( $row->meta_value ) as $url ) {
						$host = is_string( $url ) ? Syn_Fediverse_Detection::host( $url ) : null;
						if ( ! $host ) {
							continue;
						}
						if ( ! isset( $hosts[ $host ] ) ) {
							$hosts[ $host ] = array(
								'links' => 0,
								'url'   => $url,
							);
						}
						++$hosts[ $host ]['links'];
					}
				}
				$full = self::BATCH === count( $rows );
			} while ( $full );
		}
		return $hosts;
	}

	/**
	 * Builds the list and caches it for a day.
	 *
	 * @return array `hosts` (keyed by host: `links` and `url`, most links first) and `time`.
	 */
	public static function build() {
		$missing = array();
		foreach ( self::count_hosts() as $host => $data ) {
			if ( Syn_Link_Domain_Icon_Map::is_generic( Syn_Link_Domain_Icon_Map::url_to_name( $data['url'] ) ) ) {
				$missing[ $host ] = $data;
			}
		}
		uksort(
			$missing,
			function ( $a, $b ) use ( $missing ) {
				$diff = $missing[ $b ]['links'] - $missing[ $a ]['links'];
				return 0 !== $diff ? $diff : strcmp( $a, $b );
			}
		);
		$list = array(
			'hosts' => array_slice( $missing, 0, self::MAX_HOSTS, true ),
			'time'  => time(),
		);
		set_transient( self::CACHE, $list, DAY_IN_SECONDS );
		return $list;
	}

	/**
	 * Returns the list, building it if it isn't cached.
	 *
	 * Hosts that have an icon by now, for example because a Fediverse check found
	 * one, are left out.
	 *
	 * @param bool $refresh Optional. Whether to build it again. Default false.
	 * @return array `hosts` (keyed by host: `links` and `url`) and `time`.
	 */
	public static function get( $refresh = false ) {
		$list = $refresh ? false : get_transient( self::CACHE );
		if ( ! is_array( $list ) || ! isset( $list['hosts'], $list['time'] ) ) {
			$list = self::build();
		}
		foreach ( $list['hosts'] as $host => $data ) {
			if ( ! Syn_Link_Domain_Icon_Map::is_generic( Syn_Link_Domain_Icon_Map::url_to_name( $data['url'] ) ) ) {
				unset( $list['hosts'][ $host ] );
			}
		}
		return $list;
	}

	/**
	 * Queues a Fediverse check for each host in the list that needs one.
	 *
	 * The checks are spread out, so a long list doesn't run in one cron request.
	 *
	 * @return int The number of checks queued.
	 */
	public static function detect() {
		$queued = 0;
		foreach ( self::get()['hosts'] as $data ) {
			if ( Syn_Fediverse_Detection::maybe_schedule( $data['url'], $queued * self::DETECT_INTERVAL ) ) {
				++$queued;
			}
		}
		return $queued;
	}

	/**
	 * Describes what the Fediverse check found for a host.
	 *
	 * @param string $host The host.
	 * @return string The status, for display.
	 */
	public static function status( $host ) {
		if ( wp_next_scheduled( Syn_Fediverse_Detection::HOOK, array( $host ) ) ) {
			return __( 'Fediverse check queued', 'syndication-links' );
		}
		$cached = Syn_Fediverse_Detection::get_host( $host );
		if ( ! $cached ) {
			return __( 'Not checked', 'syndication-links' );
		}
		if ( '' !== $cached['software'] ) {
			/* translators: %s: software name reported by the site */
			return sprintf( __( 'Runs %s, which has no icon', 'syndication-links' ), $cached['software'] );
		}
		return __( 'Not a Fediverse server', 'syndication-links' );
	}

	/**
	 * Returns the link that opens a prefilled GitHub issue asking for a host's icon.
	 *
	 * Only the host is filled in; nothing is sent until the person submits the issue.
	 *
	 * @param string $host The host.
	 * @return string The URL.
	 */
	public static function report_url( $host ) {
		return add_query_arg(
			array(
				'template' => 'icon-request.yml',
				'title'    => rawurlencode( 'Icon request: ' . $host ),
				'domain'   => rawurlencode( $host ),
			),
			self::ISSUES
		);
	}

	/**
	 * Handles the Refresh and Check buttons on the Icons tab.
	 */
	public static function handle_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'syndication-links' ), 403 );
		}
		check_admin_referer( 'syn_missing_icons' );
		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$result = array();
		if ( 'detect' === $do ) {
			$result['syn_detect'] = self::detect();
		} else {
			self::get( true );
			$result['syn_refreshed'] = 1;
		}
		wp_safe_redirect( add_query_arg( $result, admin_url( 'options-general.php?page=syndication_links&tab=icons' ) ) );
		exit;
	}

	/**
	 * Prints a button that posts to handle_request().
	 *
	 * @param string $do    The action: `refresh` or `detect`.
	 * @param string $label The button label.
	 */
	private static function button( $do, $label ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
			<input type="hidden" name="action" value="syn_missing_icons" />
			<input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>" />
			<?php wp_nonce_field( 'syn_missing_icons' ); ?>
			<?php submit_button( $label, 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Prints the Icons tab of the settings page.
	 */
	public static function render() {
		if ( isset( $_GET['syn_detect'] ) ) {
			/* translators: %s: number of sites */
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( sprintf( _n( 'Queued a Fediverse check for %s site. The checks run in the background; reload this page to see what they find.', 'Queued Fediverse checks for %s sites. The checks run in the background; reload this page to see what they find.', absint( $_GET['syn_detect'] ), 'syndication-links' ), number_format_i18n( absint( $_GET['syn_detect'] ) ) ) ) );
		}
		$list = self::get();
		?>
		<h2><?php esc_html_e( 'Sites without an icon', 'syndication-links' ); ?></h2>
		<p><?php esc_html_e( 'Your posts and comments link to these sites, but they show the generic website icon. Ask for an icon to be included with the Report link, which opens a GitHub issue for you to send, or add one yourself (see the FAQ).', 'syndication-links' ); ?></p>
		<p>
			<?php
			self::button( 'refresh', __( 'Refresh the list', 'syndication-links' ) );
			if ( Syn_Fediverse_Detection::enabled() && $list['hosts'] ) {
				self::button( 'detect', __( 'Check these sites for Fediverse software', 'syndication-links' ) );
			}
			/* translators: %s: date and time */
			printf( '<span class="description">%s</span>', esc_html( sprintf( __( 'Listed on %s.', 'syndication-links' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $list['time'] ) ) ) );
			?>
		</p>
		<?php
		if ( ! $list['hosts'] ) {
			echo '<p>' . esc_html__( 'Every linked site has an icon.', 'syndication-links' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site', 'syndication-links' ); ?></th>
					<th><?php esc_html_e( 'Links', 'syndication-links' ); ?></th>
					<th><?php esc_html_e( 'Fediverse check', 'syndication-links' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $list['hosts'] as $host => $data ) {
				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td><a href="%4$s" target="_blank" rel="noopener noreferrer">%5$s</a></td></tr>',
					esc_html( $host ),
					esc_html( number_format_i18n( $data['links'] ) ),
					esc_html( self::status( $host ) ),
					esc_url( self::report_url( $host ) ),
					esc_html__( 'Report', 'syndication-links' )
				);
			}
			?>
			</tbody>
		</table>
		<p><?php esc_html_e( 'With WP-CLI: wp syndication-links icons missing, with --refresh to rebuild the list and --detect to check the sites for Fediverse software.', 'syndication-links' ); ?></p>
		<?php
	}

	/**
	 * Lists the linked sites that show a generic icon.
	 *
	 * ## OPTIONS
	 *
	 * [--refresh]
	 * : Build the list again instead of using the cached one.
	 *
	 * [--detect]
	 * : Queue a background check of each site for Fediverse software.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication-links icons missing
	 *     wp syndication-links icons missing --refresh --detect
	 *
	 * @param string[] $args       Unused.
	 * @param array    $assoc_args Flags.
	 */
	public static function cli( $args, $assoc_args ) {
		$list = self::get( ! empty( $assoc_args['refresh'] ) );
		if ( ! empty( $assoc_args['detect'] ) ) {
			if ( ! Syn_Fediverse_Detection::enabled() ) {
				WP_CLI::warning( 'Fediverse detection is turned off in the settings.' );
			} else {
				WP_CLI::log( sprintf( 'Queued %d Fediverse checks.', self::detect() ) );
			}
		}
		$items = array();
		foreach ( $list['hosts'] as $host => $data ) {
			$items[] = array(
				'site'   => $host,
				'links'  => $data['links'],
				'status' => self::status( $host ),
				'report' => self::report_url( $host ),
			);
		}
		if ( ! $items ) {
			WP_CLI::success( 'Every linked site has an icon.' );
			return;
		}
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		WP_CLI\Utils\format_items( $format, $items, array( 'site', 'links', 'status', 'report' ) );
	}
}
