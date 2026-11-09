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
 * settings page, or with `wp syndication-links icons add`. Sites are matched
 * against a catalog of every Simple Icon, bundled with the plugin
 * (includes/simple-icons-catalog.php), which also has their titles and colours.
 * Only the SVG is downloaded, from the same Simple Icons release the bundled
 * icons were built from (simpleicons_syn_version()), through GitHub's API. It's
 * rebuilt from its viewBox and path data only, and stored in
 * `uploads/syndication-links/icons/`, which is added to the icon directories.
 *
 * Nothing is downloaded without an admin asking for it.
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
	 * Where an SVG is downloaded from: GitHub's API for the Simple Icons repository; `%1$s` is the slug, `%2$s` the release.
	 *
	 * @var string
	 */
	const SOURCE = 'https://api.github.com/repos/simple-icons/simple-icons/contents/icons/%1$s.svg?ref=%2$s';

	/**
	 * The catalog, loaded once per request.
	 *
	 * @var array|null
	 */
	private static $catalog = null;

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
	 * Returns the catalog of every Simple Icon in the bundled release.
	 *
	 * @return array `icons` (slug => title, colour) and `keys` (other name => slug).
	 */
	public static function catalog() {
		if ( null === self::$catalog ) {
			self::$catalog = require __DIR__ . '/simple-icons-catalog.php';
		}
		return self::$catalog;
	}

	/**
	 * Returns the names a host might have in Simple Icons.
	 *
	 * @param string $host The host.
	 * @return string[] Candidate names.
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
	 * Finds the Simple Icon that matches a host.
	 *
	 * Tries the domain's name, the domain with its dots spelled out (`last.fm`
	 * is `lastdotfm`) and without them, against each icon's slug, title and aliases.
	 *
	 * @param string $host The host.
	 * @return string|null The slug, or null if there's no match.
	 */
	public static function match( $host ) {
		$catalog = self::catalog();
		foreach ( self::host_keys( $host ) as $key ) {
			if ( isset( $catalog['icons'][ $key ] ) ) {
				return $key;
			}
			if ( isset( $catalog['keys'][ $key ] ) ) {
				return $catalog['keys'][ $key ];
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
	 * If the icon is bundled already, nothing is downloaded: the host is only
	 * mapped to it (for a domain like `last.fm` whose name doesn't match `lastdotfm`).
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
		$catalog = self::catalog();
		if ( ! isset( $catalog['icons'][ $slug ] ) ) {
			return new WP_Error( 'syn_simple_icons_unknown', __( 'Simple Icons has no icon by that name.', 'syndication-links' ) );
		}
		$host = $host ? Syn_Fediverse_Detection::host( 'https://' . $host ) : null;

		$file = Syn_Link_Domain_Icon_Map::get_icon_filename( $slug );
		if ( $file && dirname( $file ) !== self::dir() ) {
			if ( ! $host ) {
				return new WP_Error( 'syn_simple_icons_bundled', __( 'That icon is already included.', 'syndication-links' ) );
			}
			self::record( $slug, $host );
			return true;
		}

		$response = wp_safe_remote_get(
			sprintf( self::SOURCE, $slug, simpleicons_syn_version() ),
			array(
				'timeout'             => 10,
				'limit_response_size' => 256 * KB_IN_BYTES,
				'headers'             => array( 'Accept' => 'application/vnd.github.raw' ),
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

		self::record( $slug, $host );
		return true;
	}

	/**
	 * Records an added icon, and the host it was added for.
	 *
	 * @param string      $slug The slug.
	 * @param string|null $host The host, or null.
	 */
	private static function record( $slug, $host ) {
		$catalog        = self::catalog();
		$added          = self::added();
		$hosts          = isset( $added[ $slug ]['hosts'] ) ? (array) $added[ $slug ]['hosts'] : array();
		$added[ $slug ] = array(
			'title'   => $catalog['icons'][ $slug ][0],
			'hex'     => $catalog['icons'][ $slug ][1],
			'hosts'   => array_values( array_unique( array_filter( array_merge( $hosts, array( $host ) ) ) ) ),
			'version' => simpleicons_syn_version(),
		);
		update_option( self::ADDED, $added, true );
	}

	/**
	 * Removes an added icon, and its file if it was downloaded.
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
		$file = self::dir() . '/' . $slug . '.svg';
		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
		return true;
	}

	/**
	 * Handles the Add and Remove buttons on the Icons tab.
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
		if ( 'add' === $do ) {
			$result = self::add( $slug, $host );
		} elseif ( 'remove' === $do ) {
			self::remove( $slug );
		}
		$args = is_wp_error( $result ) ? array( 'syn_icon_error' => $result->get_error_code() ) : array( 'syn_icon_done' => $do );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php?page=syndication_links&tab=icons' ) ) );
		exit;
	}

	/**
	 * Prints a button that posts to handle_request().
	 *
	 * @param string $do    The action: `add` or `remove`.
	 * @param string $label The button label.
	 * @param string $slug  The icon.
	 * @param string $host  Optional. The host. Default empty.
	 */
	public static function button( $do, $label, $slug, $host = '' ) {
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
	 *
	 * The error is passed as a code and its message looked up here, so the URL can't put text on the page.
	 */
	public static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only picks which notice to show after a redirect.
		$messages = array(
			'syn_simple_icons_slug'    => __( 'That isn\'t a Simple Icons name.', 'syndication-links' ),
			'syn_simple_icons_unknown' => __( 'Simple Icons has no icon by that name.', 'syndication-links' ),
			'syn_simple_icons_bundled' => __( 'That icon is already included.', 'syndication-links' ),
			'syn_simple_icons_svg'     => __( 'The icon could not be downloaded.', 'syndication-links' ),
			'syn_simple_icons_dir'     => __( 'The icons folder in uploads could not be created.', 'syndication-links' ),
			'syn_simple_icons_write'   => __( 'The icon could not be saved in uploads.', 'syndication-links' ),
		);
		$error    = isset( $_GET['syn_icon_error'] ) ? sanitize_key( wp_unslash( $_GET['syn_icon_error'] ) ) : '';
		if ( isset( $messages[ $error ] ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $messages[ $error ] ) );
		} elseif ( isset( $_GET['syn_icon_done'] ) && 'add' === sanitize_key( wp_unslash( $_GET['syn_icon_done'] ) ) ) {
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html__( 'The icon was added.', 'syndication-links' ) );
		}
		// phpcs:enable
	}

	/**
	 * Prints the added icons on the Icons tab.
	 */
	public static function render() {
		$added = self::added();
		if ( ! $added ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Added icons', 'syndication-links' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Icon', 'syndication-links' ); ?></th>
					<th><?php esc_html_e( 'For', 'syndication-links' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $added as $slug => $icon ) {
				echo '<tr><td>' . Syn_Link_Domain_Icon_Map::get_icon( $slug ) . ' ' . esc_html( $icon['title'] ) . '</td><td>' . esc_html( implode( ', ', (array) $icon['hosts'] ) ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_icon() escapes its parts, and the SVG was rebuilt from path data when it was added.
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
	 * : The icon's Simple Icons name, such as `untappd`.
	 *
	 * [--domain=<domain>]
	 * : A domain to show the icon for, when its name doesn't match the icon's (such as `last.fm` for `lastdotfm`).
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication-links icons add untappd
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
