<?php
/**
 * Adding Simple Icons that aren't bundled.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Syn_Simple_Icons', 'init' ) );

/**
 * Adds icons from Simple Icons that the plugin doesn't bundle.
 *
 * The plugin bundles a curated set. For a linked site that Simple Icons has but
 * the set leaves out, an admin can add its icon: from the Icons tab of the
 * settings page, or with `wp syndication-links icons add`. The icon, title and
 * colour come from the jsDelivr copy of the same Simple Icons release the
 * bundled icons were built from (simpleicons_syn_version()). The SVG is rebuilt
 * from its viewBox and path data only, and stored in
 * `uploads/syndication-links/icons/`, which is added to the icon directories.
 *
 * Nothing is fetched without an admin asking for it: looking up the linked
 * sites in Simple Icons and adding an icon are both explicit actions.
 *
 * @since 5.0.0
 */
class Syn_Simple_Icons {

	/**
	 * The option holding the added icons: slug => title, hex, hosts, version. Autoloaded, as it's read when links are shown.
	 *
	 * @var string
	 */
	const ADDED = 'syndication_links_added_icons';

	/**
	 * The option caching the Simple Icons lookup index. Not autoloaded; only read in the admin.
	 *
	 * @var string
	 */
	const INDEX = 'syndication_links_simple_icons_index';

	/**
	 * Where the Simple Icons package is fetched from; `%1$s` is the version, `%2$s` the file.
	 *
	 * @var string
	 */
	const CDN = 'https://cdn.jsdelivr.net/npm/simple-icons@%1$s/%2$s';

	/**
	 * Hooks the added icons in, and the settings page actions and WP-CLI commands.
	 */
	public static function init() {
		add_filter( 'syn_icon_file_dirs', array( __CLASS__, 'icon_dirs' ) );
		add_filter( 'syn_link_title', array( __CLASS__, 'title' ), 5, 2 );
		add_filter( 'syn_link_icon_color', array( __CLASS__, 'color' ), 5, 2 );
		add_filter( 'syn_link_mapping', array( __CLASS__, 'mapping' ), 5, 2 );
		add_action( 'admin_post_syn_simple_icons', array( __CLASS__, 'handle_request' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'syndication-links icons add', array( __CLASS__, 'cli_add' ) );
			WP_CLI::add_command( 'syndication-links icons remove', array( __CLASS__, 'cli_remove' ) );
		}
	}

	/**
	 * Returns the added icons.
	 *
	 * @return array[] Keyed by slug: `title`, `hex` (with `#`), `hosts` and `version`.
	 */
	public static function added() {
		$added = get_option( self::ADDED, array() );
		return is_array( $added ) ? $added : array();
	}

	/**
	 * Returns the directory the added icons are stored in.
	 *
	 * @return string The path, without a trailing slash.
	 */
	public static function dir() {
		$uploads = wp_upload_dir( null, false );
		return untrailingslashit( $uploads['basedir'] ) . '/syndication-links/icons';
	}

	/**
	 * Adds the added icons' directory to the icon directories, after the bundled ones.
	 *
	 * @param string[] $dirs Directory paths.
	 * @return string[] Directory paths.
	 */
	public static function icon_dirs( $dirs ) {
		if ( self::added() ) {
			$dirs[] = self::dir();
		}
		return $dirs;
	}

	/**
	 * Gives an added icon its title.
	 *
	 * @param string $title The title.
	 * @param string $name  The icon name.
	 * @return string The title.
	 */
	public static function title( $title, $name ) {
		$added = self::added();
		return ( $title === $name && isset( $added[ $name ]['title'] ) ) ? $added[ $name ]['title'] : $title;
	}

	/**
	 * Gives an added icon its colour.
	 *
	 * @param string|null $color The colour.
	 * @param string      $name  The icon name.
	 * @return string|null The colour.
	 */
	public static function color( $color, $name ) {
		$added = self::added();
		return ( null === $color && isset( $added[ $name ]['hex'] ) ) ? $added[ $name ]['hex'] : $color;
	}

	/**
	 * Maps the host an icon was added for to it, when its name doesn't match the icon's.
	 *
	 * For example `last.fm`, whose Simple Icons name is `lastdotfm`.
	 *
	 * @param string $name The icon name.
	 * @param string $url  The link.
	 * @return string The icon name.
	 */
	public static function mapping( $name, $url ) {
		if ( ! Syn_Link_Domain_Icon_Map::is_generic( $name ) ) {
			return $name;
		}
		$host = Syn_Fediverse_Detection::host( $url );
		foreach ( self::added() as $slug => $icon ) {
			if ( $host && in_array( $host, (array) $icon['hosts'], true ) ) {
				return $slug;
			}
		}
		return $name;
	}

	/**
	 * Converts a brand title into a Simple Icons slug, like its titleToSlug().
	 *
	 * @param string $title The title.
	 * @return string The slug.
	 */
	public static function title_to_slug( $title ) {
		$title = strtr(
			mb_strtolower( $title, 'UTF-8' ),
			array(
				'+' => 'plus',
				'.' => 'dot',
				'&' => 'and',
				'đ' => 'd',
				'ħ' => 'h',
				'ı' => 'i',
				'ĸ' => 'k',
				'ŀ' => 'l',
				'ł' => 'l',
				'ß' => 'ss',
				'ŧ' => 't',
				'ø' => 'o',
			)
		);
		return preg_replace( '/[^a-z0-9]/', '', remove_accents( $title ) );
	}

	/**
	 * Returns the names a host might have in Simple Icons.
	 *
	 * @param string $host The host.
	 * @return string[] Candidate slugs.
	 */
	public static function host_keys( $host ) {
		$keys = array(
			Syn_Link_Domain_Icon_Map::split_domain( $host ),
			str_replace( '.', 'dot', $host ),
			str_replace( '.', '', $host ),
		);
		return array_values( array_unique( array_filter( array_map( 'strtolower', preg_replace( '/[^a-z0-9]/i', '', $keys ) ) ) ) );
	}

	/**
	 * Returns the cached lookup index, if it's for the bundled Simple Icons version.
	 *
	 * @return array|null `keys` (candidate slug => slug), `icons` (slug => title, hex) and `time`, or null.
	 */
	public static function index() {
		$index = get_option( self::INDEX );
		if ( ! is_array( $index ) || ! isset( $index['version'], $index['keys'], $index['icons'] ) || simpleicons_syn_version() !== $index['version'] ) {
			return null;
		}
		return $index;
	}

	/**
	 * Fetches the Simple Icons data and caches a lookup index.
	 *
	 * @return array|WP_Error The index, or an error.
	 */
	public static function fetch_index() {
		$response = wp_safe_remote_get(
			sprintf( self::CDN, simpleicons_syn_version(), 'data/simple-icons.json' ),
			array(
				'timeout'             => 15,
				'limit_response_size' => 4 * MB_IN_BYTES,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) ) {
			return new WP_Error( 'syn_simple_icons_data', __( 'The Simple Icons list could not be downloaded.', 'syndication-links' ) );
		}
		if ( isset( $data['icons'] ) ) {
			$data = $data['icons'];
		}
		$index = array(
			'version' => simpleicons_syn_version(),
			'time'    => time(),
			'keys'    => array(),
			'icons'   => array(),
		);
		foreach ( $data as $icon ) {
			if ( ! is_array( $icon ) || empty( $icon['title'] ) || empty( $icon['hex'] ) || ! is_string( $icon['title'] ) || ! preg_match( '/^[0-9a-f]{6}$/iD', (string) $icon['hex'] ) ) {
				continue;
			}
			$slug = ! empty( $icon['slug'] ) && is_string( $icon['slug'] ) ? $icon['slug'] : self::title_to_slug( $icon['title'] );
			if ( ! preg_match( '/^[a-z0-9_]+$/D', $slug ) ) {
				continue;
			}
			$index['icons'][ $slug ] = array( sanitize_text_field( str_replace( '&amp;', '&', $icon['title'] ) ), '#' . strtoupper( $icon['hex'] ) );
			$keys                    = array( $slug, self::title_to_slug( $icon['title'] ) );
			if ( isset( $icon['aliases']['aka'] ) && is_array( $icon['aliases']['aka'] ) ) {
				foreach ( $icon['aliases']['aka'] as $alias ) {
					if ( is_string( $alias ) ) {
						$keys[] = self::title_to_slug( $alias );
					}
				}
			}
			foreach ( array_filter( $keys ) as $key ) {
				if ( ! isset( $index['keys'][ $key ] ) ) {
					$index['keys'][ $key ] = $slug;
				}
			}
		}
		if ( ! $index['icons'] ) {
			return new WP_Error( 'syn_simple_icons_data', __( 'The Simple Icons list could not be downloaded.', 'syndication-links' ) );
		}
		update_option( self::INDEX, $index, false );
		return $index;
	}

	/**
	 * Finds the Simple Icon that matches a host, in the cached index.
	 *
	 * @param string $host The host.
	 * @return string|null The slug, or null if there's no match or no index.
	 */
	public static function match( $host ) {
		$index = self::index();
		if ( ! $index ) {
			return null;
		}
		foreach ( self::host_keys( $host ) as $key ) {
			if ( isset( $index['keys'][ $key ] ) ) {
				return $index['keys'][ $key ];
			}
		}
		return null;
	}

	/**
	 * Rebuilds a Simple Icons SVG from its viewBox and path data.
	 *
	 * Anything else in the file is dropped, so the stored SVG holds no scripts,
	 * styles, links or titles.
	 *
	 * @param string $svg The downloaded SVG.
	 * @return string|null The clean SVG, or null if it isn't a Simple Icons SVG.
	 */
	public static function sanitize_svg( $svg ) {
		if ( ! preg_match( '/^\s*<svg\b[^>]*\bviewBox="([0-9.\s-]+)"/', $svg, $view_box ) ) {
			return null;
		}
		if ( ! preg_match_all( '/<path\b[^>]*\bd="([MmZzLlHhVvCcSsQqTtAa0-9.,\s+eE-]+)"/', $svg, $paths ) || ! $paths[1] ) {
			return null;
		}
		$out = '<svg role="img" viewBox="' . esc_attr( trim( $view_box[1] ) ) . '" xmlns="http://www.w3.org/2000/svg">';
		foreach ( $paths[1] as $d ) {
			$out .= '<path d="' . esc_attr( $d ) . '"/>';
		}
		return $out . "</svg>\n";
	}

	/**
	 * Adds a Simple Icon.
	 *
	 * @param string $slug The Simple Icons slug.
	 * @param string $host Optional. The host it's for, mapped to it if its name doesn't match. Default empty.
	 * @return true|WP_Error True, or an error.
	 */
	public static function add( $slug, $host = '' ) {
		$slug = strtolower( (string) $slug );
		if ( ! preg_match( '/^[a-z0-9_]+$/D', $slug ) ) {
			return new WP_Error( 'syn_simple_icons_slug', __( 'That isn\'t a Simple Icons name.', 'syndication-links' ) );
		}
		$index = self::index();
		if ( ! $index ) {
			$index = self::fetch_index();
			if ( is_wp_error( $index ) ) {
				return $index;
			}
		}
		if ( ! isset( $index['icons'][ $slug ] ) ) {
			return new WP_Error( 'syn_simple_icons_unknown', __( 'Simple Icons has no icon by that name.', 'syndication-links' ) );
		}
		$response = wp_safe_remote_get(
			sprintf( self::CDN, simpleicons_syn_version(), 'icons/' . $slug . '.svg' ),
			array(
				'timeout'             => 10,
				'limit_response_size' => 256 * KB_IN_BYTES,
			)
		);
		$svg      = ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) ? self::sanitize_svg( wp_remote_retrieve_body( $response ) ) : null;
		if ( ! $svg ) {
			return new WP_Error( 'syn_simple_icons_svg', __( 'The icon could not be downloaded.', 'syndication-links' ) );
		}

		if ( ! wp_mkdir_p( self::dir() ) ) {
			return new WP_Error( 'syn_simple_icons_dir', __( 'The icons folder in uploads could not be created.', 'syndication-links' ) );
		}
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! WP_Filesystem() || ! $wp_filesystem->put_contents( self::dir() . '/' . $slug . '.svg', $svg, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'syn_simple_icons_write', __( 'The icon could not be saved in uploads.', 'syndication-links' ) );
		}

		$added          = self::added();
		$hosts          = isset( $added[ $slug ]['hosts'] ) ? (array) $added[ $slug ]['hosts'] : array();
		$host           = $host ? Syn_Fediverse_Detection::host( 'https://' . $host ) : null;
		$added[ $slug ] = array(
			'title'   => $index['icons'][ $slug ][0],
			'hex'     => $index['icons'][ $slug ][1],
			'hosts'   => array_values( array_unique( array_filter( array_merge( $hosts, array( $host ) ) ) ) ),
			'version' => simpleicons_syn_version(),
		);
		update_option( self::ADDED, $added, true );
		return true;
	}

	/**
	 * Removes an added icon.
	 *
	 * @param string $slug The slug.
	 * @return bool Whether it was an added icon.
	 */
	public static function remove( $slug ) {
		$added = self::added();
		if ( ! isset( $added[ $slug ] ) ) {
			return false;
		}
		unset( $added[ $slug ] );
		update_option( self::ADDED, $added, true );
		wp_delete_file( self::dir() . '/' . $slug . '.svg' );
		return true;
	}

	/**
	 * Handles the Look up, Add and Remove buttons on the Icons tab.
	 */
	public static function handle_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'syndication-links' ), 403 );
		}
		check_admin_referer( 'syn_simple_icons' );
		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$slug   = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$host   = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		$result = true;
		if ( 'lookup' === $do ) {
			$result = self::fetch_index();
		} elseif ( 'add' === $do ) {
			$result = self::add( $slug, $host );
		} elseif ( 'remove' === $do ) {
			self::remove( $slug );
		}
		$args = is_wp_error( $result ) ? array( 'syn_icon_error' => rawurlencode( $result->get_error_message() ) ) : array( 'syn_icon_done' => $do );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php?page=syndication_links&tab=icons' ) ) );
		exit;
	}

	/**
	 * Prints a button that posts to handle_request().
	 *
	 * @param string $do    The action: `lookup`, `add` or `remove`.
	 * @param string $label The button label.
	 * @param string $slug  Optional. The icon. Default empty.
	 * @param string $host  Optional. The host. Default empty.
	 */
	public static function button( $do, $label, $slug = '', $host = '' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
			<input type="hidden" name="action" value="syn_simple_icons" />
			<input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>" />
			<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>" />
			<input type="hidden" name="host" value="<?php echo esc_attr( $host ); ?>" />
			<?php wp_nonce_field( 'syn_simple_icons' ); ?>
			<?php submit_button( $label, 'secondary small', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Prints the notices for the Icons tab's actions.
	 */
	public static function notices() {
		if ( isset( $_GET['syn_icon_error'] ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( sanitize_text_field( rawurldecode( wp_unslash( $_GET['syn_icon_error'] ) ) ) ) );
		} elseif ( isset( $_GET['syn_icon_done'] ) && 'add' === $_GET['syn_icon_done'] ) {
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html__( 'The icon was added.', 'syndication-links' ) );
		}
	}

	/**
	 * Prints the Simple Icons part of the Icons tab: the look-up button and the added icons.
	 */
	public static function render() {
		$index = self::index();
		?>
		<h2><?php esc_html_e( 'Icons from Simple Icons', 'syndication-links' ); ?></h2>
		<p>
			<?php
			/* translators: %s: Simple Icons version */
			echo esc_html( sprintf( __( 'Simple Icons has icons for thousands of brands, but the plugin bundles only a few, to stay small. You can add others: look up the sites above in Simple Icons, then add the icons that match. This downloads the Simple Icons list (version %s, about 450 KB) and the icons you add from cdn.jsdelivr.net.', 'syndication-links' ), simpleicons_syn_version() ) );
			?>
		</p>
		<p>
			<?php
			self::button( 'lookup', $index ? __( 'Look up again', 'syndication-links' ) : __( 'Look up these sites in Simple Icons', 'syndication-links' ) );
			if ( $index ) {
				/* translators: %s: date and time */
				printf( '<span class="description">%s</span>', esc_html( sprintf( __( 'Looked up on %s.', 'syndication-links' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $index['time'] ) ) ) );
			}
			?>
		</p>
		<?php
		$added = self::added();
		if ( ! $added ) {
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Added icon', 'syndication-links' ); ?></th>
					<th><?php esc_html_e( 'For', 'syndication-links' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $added as $slug => $icon ) {
				echo '<tr><td>' . Syn_Link_Domain_Icon_Map::get_icon( $slug ) . ' ' . esc_html( $icon['title'] ) . '</td><td>' . esc_html( implode( ', ', (array) $icon['hosts'] ) ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_icon() escapes its parts, and the SVG was sanitized when it was added.
				self::button( 'remove', __( 'Remove', 'syndication-links' ), $slug );
				echo '</td></tr>';
			}
			?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Adds an icon from Simple Icons.
	 *
	 * Downloads it from the Simple Icons release the bundled icons come from.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The icon's Simple Icons name, such as `letterboxd`.
	 *
	 * [--domain=<domain>]
	 * : A domain to show the icon for, when its name doesn't match the icon's (such as `last.fm` for `lastdotfm`).
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication-links icons add letterboxd
	 *     wp syndication-links icons add lastdotfm --domain=last.fm
	 *
	 * @param string[] $args       The slug.
	 * @param array    $assoc_args Flags.
	 */
	public static function cli_add( $args, $assoc_args ) {
		$result = self::add( $args[0], isset( $assoc_args['domain'] ) ? $assoc_args['domain'] : '' );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Added the %s icon.', self::added()[ strtolower( $args[0] ) ]['title'] ) );
	}

	/**
	 * Removes an icon added from Simple Icons.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The icon's Simple Icons name.
	 *
	 * @param string[] $args       The slug.
	 * @param array    $assoc_args Unused.
	 */
	public static function cli_remove( $args, $assoc_args ) {
		if ( ! self::remove( strtolower( $args[0] ) ) ) {
			WP_CLI::error( 'No added icon has that name.' );
		}
		WP_CLI::success( 'Removed.' );
	}
}
