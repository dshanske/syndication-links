<?php
/**
 * Imports the links other cross-posting plugins recorded into the posts' own syndication links.
 *
 * Links from those plugins are shown while their integration in Social_Plugins
 * works. Importing copies them into mf2_syndication, so they stay when the other
 * plugin is deactivated, stops working, or its integration is removed. The other
 * plugin's data is only read.
 *
 * @package Syndication_Links
 * @since 5.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Syn_Plugin_Import', 'init' ) );

/**
 * Batched import of links from other plugins.
 *
 * @since 5.0.0
 */
class Syn_Plugin_Import {

	/**
	 * Posts per batch.
	 */
	const BATCH = 100;

	/**
	 * Option holding each source's import status.
	 */
	const OPTION = 'syndication_links_imports';

	/**
	 * Hooks the import in.
	 */
	public static function init() {
		add_action( 'syn_plugin_import', array( __CLASS__, 'run_scheduled' ), 10, 2 );
		add_action( 'admin_post_syn_plugin_import', array( __CLASS__, 'handle_request' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'syndication-links import', array( __CLASS__, 'cli' ) );
		}
	}

	/**
	 * The sources that can be imported.
	 *
	 * @return array[] Sources with a meta query. See register_syndication_link_source().
	 */
	public static function sources() {
		return Social_Plugins::import_sources();
	}

	/**
	 * The query arguments for the posts a source may have links for.
	 *
	 * @param array $source Source.
	 * @return array
	 */
	private static function query_args( $source ) {
		return array(
			// Every type and status: 'any' leaves out types and statuses excluded from search.
			'post_type'      => get_post_types(),
			'post_status'    => array_keys( get_post_stati() ),
			'meta_query'     => $source['meta_query'],
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
	}

	/**
	 * Counts the posts a source may have links for.
	 *
	 * @param string $slug Source slug.
	 * @return int
	 */
	public static function count( $slug ) {
		$sources = self::sources();
		if ( ! isset( $sources[ $slug ] ) ) {
			return 0;
		}
		$args                   = self::query_args( $sources[ $slug ] );
		$args['posts_per_page'] = 1;
		$query                  = new WP_Query( $args );
		return (int) $query->found_posts;
	}

	/**
	 * Imports one batch of a source's links.
	 *
	 * Posts aren't removed from the source's results by importing (its meta is
	 * left alone), so batches page through a stable, ID-ordered list.
	 *
	 * @param string $slug Source slug.
	 * @param int    $page Batch number, from 1.
	 * @return array|false {
	 *     False for an unknown source.
	 *
	 *     @type int  $posts Posts that had links in this batch.
	 *     @type bool $more  Whether there may be another batch.
	 * }
	 */
	public static function import_batch( $slug, $page = 1 ) {
		$sources = self::sources();
		if ( ! isset( $sources[ $slug ] ) || ! is_callable( $sources[ $slug ]['callback'] ) ) {
			return false;
		}
		$args                   = self::query_args( $sources[ $slug ] );
		$args['posts_per_page'] = self::BATCH;
		$args['paged']          = max( 1, (int) $page );
		$args['no_found_rows']  = true;
		$ids                    = get_posts( $args );
		update_meta_cache( 'post', $ids );

		$posts = 0;
		foreach ( $ids as $id ) {
			$links = (array) call_user_func( $sources[ $slug ]['callback'], $id );
			$links = array_filter( $links, 'is_string' );
			if ( $links ) {
				// Merges with the post's links; clean_urls() drops invalid URLs and duplicates.
				add_post_syndication_link( $id, $links );
				++$posts;
			}
		}
		return array(
			'posts' => $posts,
			'more'  => count( $ids ) === self::BATCH,
		);
	}

	/**
	 * Each source's import status.
	 *
	 * @return array[] Keyed by slug: status (running or done), posts, time.
	 */
	public static function statuses() {
		$statuses = get_option( self::OPTION, array() );
		return is_array( $statuses ) ? $statuses : array();
	}

	/**
	 * Records a source's import status.
	 *
	 * @param string $slug   Source slug.
	 * @param string $status running or done.
	 * @param int    $posts  Posts with links so far.
	 */
	private static function set_status( $slug, $status, $posts ) {
		$statuses          = self::statuses();
		$statuses[ $slug ] = array(
			'status' => $status,
			'posts'  => (int) $posts,
			'time'   => time(),
		);
		update_option( self::OPTION, $statuses, false );
	}

	/**
	 * Starts a background import of a source.
	 *
	 * @param string $slug Source slug.
	 * @return bool Whether it was started.
	 */
	public static function start( $slug ) {
		$sources = self::sources();
		if ( ! isset( $sources[ $slug ] ) ) {
			return false;
		}
		wp_clear_scheduled_hook( 'syn_plugin_import', array( $slug, 1 ) );
		self::set_status( $slug, 'running', 0 );
		wp_schedule_single_event( time(), 'syn_plugin_import', array( $slug, 1 ) );
		return true;
	}

	/**
	 * Runs a scheduled batch, and schedules the next one until the source is done.
	 *
	 * @param string $slug Source slug.
	 * @param int    $page Batch number.
	 */
	public static function run_scheduled( $slug, $page = 1 ) {
		$result = self::import_batch( $slug, $page );
		if ( false === $result ) {
			return;
		}
		$statuses = self::statuses();
		$posts    = ( isset( $statuses[ $slug ]['posts'] ) && $page > 1 ) ? (int) $statuses[ $slug ]['posts'] : 0;
		$posts   += $result['posts'];
		if ( $result['more'] ) {
			self::set_status( $slug, 'running', $posts );
			wp_schedule_single_event( time(), 'syn_plugin_import', array( $slug, $page + 1 ) );
			return;
		}
		self::set_status( $slug, 'done', $posts );
	}

	/**
	 * Handles the Import button on the settings page.
	 */
	public static function handle_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import links.', 'syndication-links' ), 403 );
		}
		$slug = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		check_admin_referer( 'syn_plugin_import_' . $slug );
		$started = self::start( $slug );
		$back    = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'options-general.php?page=syndication_links' );
		}
		wp_safe_redirect( add_query_arg( 'syn_import', $started ? 'started' : 'unknown', $back ) );
		exit;
	}

	/**
	 * Prints the Import tab of the settings page.
	 */
	public static function render() {
		$statuses = self::statuses();
		if ( isset( $_GET['syn_import'] ) && 'started' === $_GET['syn_import'] ) {
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html__( 'The import has started. It runs in the background; reload this page to see its progress.', 'syndication-links' ) );
		}
		?>
		<p><?php esc_html_e( 'Links recorded by these plugins are shown while their integration works. Importing copies them into the posts\' own syndication links, so they stay if the plugin is deactivated or stops working. The other plugin\'s data isn\'t changed, and importing again doesn\'t add duplicates.', 'syndication-links' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plugin', 'syndication-links' ); ?></th>
					<th><?php esc_html_e( 'Posts that may have links', 'syndication-links' ); ?></th>
					<th><?php esc_html_e( 'Import', 'syndication-links' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( self::sources() as $slug => $source ) {
				$count = self::count( $slug );
				echo '<tr><td>' . esc_html( $source['name'] ) . '</td><td>' . esc_html( number_format_i18n( $count ) ) . '</td><td>';
				if ( isset( $statuses[ $slug ] ) ) {
					$status = $statuses[ $slug ];
					$when   = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['time'] );
					if ( 'running' === $status['status'] ) {
						/* translators: 1: number of posts, 2: date and time */
						printf( esc_html__( 'Running: %1$s posts with links so far (%2$s)', 'syndication-links' ), esc_html( number_format_i18n( $status['posts'] ) ), esc_html( $when ) );
					} else {
						/* translators: 1: number of posts, 2: date and time */
						printf( esc_html__( 'Imported links for %1$s posts on %2$s', 'syndication-links' ), esc_html( number_format_i18n( $status['posts'] ) ), esc_html( $when ) );
					}
				}
				echo '</td><td>';
				if ( $count ) {
					?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="syn_plugin_import" />
						<input type="hidden" name="source" value="<?php echo esc_attr( $slug ); ?>" />
						<?php wp_nonce_field( 'syn_plugin_import_' . $slug ); ?>
						<?php submit_button( __( 'Import links', 'syndication-links' ), 'secondary', 'submit', false ); ?>
					</form>
					<?php
				}
				echo '</td></tr>';
			}
			?>
			</tbody>
		</table>
		<p><?php esc_html_e( 'With WP-CLI: wp syndication-links import <plugin>, or --all.', 'syndication-links' ); ?></p>
		<?php
	}

	/**
	 * Imports links that other cross-posting plugins recorded.
	 *
	 * ## OPTIONS
	 *
	 * [<source>...]
	 * : Sources to import. Run without arguments to list them.
	 *
	 * [--all]
	 * : Import every source.
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication-links import xposter
	 *     wp syndication-links import --all
	 *
	 * @param string[] $args       Sources.
	 * @param array    $assoc_args Flags.
	 */
	public static function cli( $args, $assoc_args ) {
		$sources = self::sources();
		if ( ! empty( $assoc_args['all'] ) ) {
			$args = array_keys( $sources );
		}
		if ( empty( $args ) ) {
			WP_CLI::error( 'Name a source, or use --all. Sources: ' . implode( ', ', array_keys( $sources ) ) );
		}
		foreach ( $args as $slug ) {
			if ( ! isset( $sources[ $slug ] ) ) {
				WP_CLI::warning( "Unknown source: $slug" );
				continue;
			}
			$page  = 1;
			$posts = 0;
			do {
				$result = self::import_batch( $slug, $page );
				$posts += $result['posts'];
				++$page;
			} while ( $result['more'] );
			self::set_status( $slug, 'done', $posts );
			WP_CLI::success( sprintf( '%1$s: imported links for %2$d posts.', $sources[ $slug ]['name'], $posts ) );
		}
	}
}
