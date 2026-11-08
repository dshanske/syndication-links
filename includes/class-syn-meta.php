<?php
/**
 * Storing, editing and displaying syndication links.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Syn_Meta', 'init' ) );

/**
 * Stores syndication links in the `mf2_syndication` meta of posts and comments.
 *
 * Also provides the links box in the editor, the `original-of` and `syndication` query
 * vars, the privacy exporter, the migration of the legacy `syndication_urls` meta, and
 * the front-end markup.
 */
class Syn_Meta {
	/**
	 * Registers the meta and hooks.
	 */
	public static function init() {
		$cls = get_called_class();
		add_action( 'admin_init', array( $cls, 'setup' ) );
		add_action( 'save_post', array( $cls, 'save_post_meta' ), 11 );
		add_action( 'edit_comment', array( $cls, 'save_comment_meta' ) );
		add_action( 'syn_migrate_legacy_links', array( $cls, 'migrate_legacy_links' ) );
		add_filter( 'is_protected_meta', array( $cls, 'protect_meta' ), 10, 2 );
		self::schedule_legacy_migration();
		self::register_meta();
		add_filter( 'query_vars', array( $cls, 'query_var' ) );
		add_action( 'parse_query', array( $cls, 'parse_query' ) );
		add_action( 'pre_get_posts', array( $cls, 'syndication_filter_query' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( $cls, 'wp_privacy_personal_data_exporters' ), 10 );
	}

	/**
	 * Registers the `mf2_syndication` meta for posts and comments, including for the REST API.
	 *
	 * @since 5.0.0
	 */
	public static function register_meta() {
		$args = array(
			'type'          => 'array',
			'description'   => 'Syndication URLs',
			'single'        => true,
			// The key is protected (see protect_meta()), which would otherwise make it read-only
			// in the REST API. Whoever can edit the post or comment can edit its links.
			'auth_callback' => '__return_true',
			'show_in_rest'  => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
			),
		);
		register_meta(
			'comment',
			'mf2_syndication',
			$args
		);
		register_meta(
			'post',
			'mf2_syndication',
			$args
		);
	}

	/**
	 * Registers the privacy exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array The exporters, with this plugin's added.
	 */
	public static function wp_privacy_personal_data_exporters( $exporters ) {
		$exporters['syndication-links'] = array(
			'exporter_friendly_name' => __( 'Syndication Links Plugin', 'syndication-links' ),
			'callback'               => array( __CLASS__, 'data_exporter' ),
		);
		return $exporters;
	}

	/**
	 * Exports the syndication links of the comments made with an email address.
	 *
	 * One row per link, 500 comments per page.
	 *
	 * @param string $email_address The email address.
	 * @param int    $page          Optional. Page number, from 1. Default 1.
	 * @return array {
	 *     @type array $data The rows.
	 *     @type bool  $done Whether this was the last page.
	 * }
	 */
	public static function data_exporter( $email_address, $page = 1 ) {
		$number = 500; // Limit us to avoid timing out.
		$page   = (int) $page;

		$export_items = array();
		$comments     = get_comments(
			array(
				'author_email' => $email_address,
				'number'       => $number,
				'paged'        => $page,
				'orderby'      => 'comment_ID',
				'order'        => 'ASC',
			)
		);

		foreach ( (array) $comments as $comment ) {
			$syndication = get_comment_syndication_links_data( $comment );
			if ( ! empty( $syndication ) ) {
				$item_id     = "comment-{$comment->comment_ID}";
				$group_id    = 'comments';
				$group_label = __( 'Comments', 'syndication-links' );

				// One row per link: core expects a string value, and turns a URL into a link.
				$data = array();
				foreach ( $syndication as $url ) {
					$data[] = array(
						'name'  => __( 'Syndication Link', 'syndication-links' ),
						'value' => $url,
					);
				}
				$export_items[] = array(
					'group_id'    => $group_id,
					'group_label' => $group_label,
					'item_id'     => $item_id,
					'data'        => $data,
				);
			}
		}

		$done = count( $comments ) < $number;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	/**
	 * Returns the admin screens that have the links box or its script.
	 *
	 * @return string[] Screen IDs: comments, the settings page and the syndication post types.
	 */
	public static function screens() {
		$screens = array( 'comment', 'indieweb_page_syndication_links' );
		$screens = array_merge( $screens, syndication_post_types() );
		/**
		 * Filters the admin screens that have the syndication links box.
		 *
		 * @param string[] $screens Screen IDs.
		 */
		return apply_filters( 'syn_metabox_types', $screens );
	}

	/**
	 * Enqueues the links box's script on the screens that have it.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		$screens = self::screens();
		if ( in_array( get_current_screen()->id, $screens, true ) ) {
			wp_enqueue_script(
				'synlinks',
				plugins_url( 'js/synlinks.js', __DIR__ ),
				array( 'jquery' ),
				SYNDICATION_LINKS_VERSION,
				true
			);
		}
	}

	/**
	 * Registers the `original-of` and `syndication` query vars.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[] The query vars.
	 */
	public static function query_var( $vars ) {
		$vars[] = 'original-of';
		$vars[] = 'syndication';
		return $vars;
	}

	/**
	 * Redirects `?original-of=<syndicated URL>` to the post it's a copy of.
	 *
	 * Only the main query is handled. When there's no such post, the request is a 404.
	 *
	 * @param WP_Query $wp The query being parsed.
	 */
	public static function parse_query( $wp ) {
		if ( ! $wp->is_main_query() || ! array_key_exists( 'original-of', $wp->query_vars ) ) {
			return;
		}
		$url = $wp->get( 'original-of' );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			self::not_found( $wp );
			return;
		}
		$post_id = self::find_original( esc_url_raw( $url ) );
		if ( ! $post_id ) {
			self::not_found( $wp );
			return;
		}
		wp_safe_redirect( get_permalink( $post_id ) );
		exit;
	}

	/**
	 * Finds the published post that has a syndication link.
	 *
	 * Searches every syndication post type, and the legacy syndication_urls meta the
	 * background migration hasn't moved yet. Only an exact match counts: a link that
	 * merely starts with the URL doesn't.
	 *
	 * @since 5.0.0
	 *
	 * @param string $url The syndicated URL, as cleaned on save.
	 * @return int The post ID, or 0 if no post has the link.
	 */
	public static function find_original( $url ) {
		if ( empty( $url ) ) {
			return 0;
		}
		$posts = get_posts(
			array(
				'fields'         => 'ids',
				'post_type'      => syndication_post_types(),
				'posts_per_page' => 10,
				// The links are stored serialized, so this is an unindexed LIKE on the meta value.
				// In a serialized array each link is wrapped in quotes, which rules out longer URLs.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Only on original-of requests; misses are a 404.
					'relation' => 'OR',
					array(
						'key'     => 'mf2_syndication',
						'value'   => '"' . $url . '"',
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'syndication_urls',
						'value'   => $url,
						'compare' => 'LIKE',
					),
				),
			)
		);
		if ( empty( $posts ) ) {
			return 0;
		}
		update_meta_cache( 'post', $posts );
		foreach ( $posts as $post_id ) {
			$links = get_post_meta( $post_id, 'mf2_syndication', true );
			$links = is_array( $links ) ? $links : explode( "\n", (string) $links );
			$links = array_merge( $links, explode( "\n", (string) get_post_meta( $post_id, 'syndication_urls', true ) ) );
			if ( in_array( $url, array_map( 'trim', array_filter( $links, 'is_string' ) ), true ) ) {
				return (int) $post_id;
			}
		}
		return 0;
	}

	/**
	 * Makes the request a 404, which the template loader then shows.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_Query $wp The main query.
	 */
	private static function not_found( $wp ) {
		$wp->set_404();
		status_header( 404 );
		nocache_headers();
	}


	/**
	 * Limits the main query to posts with a syndication link to a domain.
	 *
	 * `?syndication=example.com` (or a URL, whose domain is used) combines with the
	 * other query vars, such as a search.
	 *
	 * @param WP_Query $query The query.
	 */
	public static function syndication_filter_query( $query ) {
		// Only the front-end main query: secondary queries (widgets, menus, related posts) are left alone.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$search = $query->get( 'syndication' );

		// If a URL, match the domain.
		if ( wp_http_validate_url( $search ) ) {
			$search = wp_parse_url( $search, PHP_URL_HOST );
		}

		if ( empty( $search ) ) {
			return;
		}
		$meta_query = array(
			array(
				'key'     => 'mf2_syndication',
				'value'   => $search,
				'compare' => 'LIKE',
			),
		);
		$query->set( 'meta_query', $meta_query );
	}


	/**
	 * Cleans a list of URLs.
	 *
	 * Drops invalid URLs and duplicates, upgrades some services to https, and
	 * re-indexes the list.
	 *
	 * @param string[] $urls The URLs.
	 * @return string[] The cleaned URLs. Anything that isn't an array is returned as is.
	 */
	public static function clean_urls( $urls ) {
		if ( ! is_array( $urls ) ) {
			return $urls;
		}
		$array = array_map(
			function ( $string ) {
				if ( is_array( $string ) ) {
					return $string;
				}
				$url = trim( $string );
				if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
					return false;
				}
				// Rewrite these to https as needed.
				/**
				 * Filters the domains whose links are rewritten from http to https.
				 *
				 * @param string[] $domains Domains, without subdomains.
				 */
				$secure = apply_filters( 'syn_rewrite_secure', array( 'facebook.com', 'twitter.com', 'huffduffer.com', 'foursquare.com', 'blogger.com', 'dailymotion.com', 'github.com', 'gitlab.com', 'google.com', 'tumblr.com', 'vimeo.com', 'wikipedia.org', 'wordpress.com', 'youtube.com' ) );
				if ( in_array( self::extract_domain_name( $url ), $secure, true ) ) {
					$url = preg_replace( '/^http:/i', 'https:', $url );
				}
				$url = esc_url_raw( $url );
				return $url;
			},
			$urls
		);
		// Re-index, so a dropped URL doesn't leave a gap that json_encode() turns into an object.
		return array_values( array_filter( array_unique( $array ) ) );
	}


	/**
	 * Hooks the links box and its script.
	 */
	public static function setup() {
		add_action( 'admin_enqueue_scripts', array( 'Syn_Meta', 'enqueue' ) );
		add_action( 'add_meta_boxes', array( 'Syn_Meta', 'add_meta_boxes' ) );
	}

	/**
	 * Adds the links box to the post and comment editors.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'synbox-meta',      // Unique ID.
			esc_html__( 'Syndication Links', 'syndication-links' ),    // Title.
			array( 'Syn_Meta', 'metabox' ),   // Callback function.
			self::screens(),         // Admin page (or post type).
			'normal',         // Context.
			'default',        // Priority.
			array(
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			)
		);
	}

	/**
	 * Displays the links box: one field per link, and an Add button.
	 *
	 * @param WP_Post|WP_Comment $object The post or comment being edited.
	 * @param array              $args   The box's arguments.
	 */
	public static function metabox( $object, $args ) {
		$screen = get_current_screen();
		if ( 'comment' === $screen->id ) {
			$type = 'comment';
			$id   = (int) $object->comment_ID;
		} else {
			$type = 'post';
			$id   = (int) $object->ID;
		}

		wp_nonce_field( 'syn_metabox', 'syn_metabox_nonce' );

		// Only the links stored here are editable. Links other plugins add are listed below.
		$stored = self::get_stored_links( $type, $id );
		$others = array_diff( self::get_syndication_links_data( $type, $id ), $stored );
		$urls   = $stored ? $stored : array( '' );

		$html  = '<div class="syndication_url_list">';
		$html .= sprintf(
			'<label for="syndication_urls">%s</label>',
			esc_html__( 'Add Links to this same content on other sites', 'syndication-links' )
		);
		$html .= '<ul>';
		foreach ( array_values( $urls ) as $i => $url ) {
			// Only the first input has the ID, which the label points at.
			$html .= sprintf(
				'<li><input type="text" name="syndication_urls[]" class="widefat"%1$s value="%2$s" /></li>',
				0 === $i ? ' id="syndication_urls"' : '',
				esc_attr( $url )
			);
		}
		$html .= '</ul>';
		$html .= sprintf(
			'<button class="button-primary" id="add-syn-link-button">%s</button>',
			esc_html__( 'Add', 'syndication-links' )
		);
		// The links as loaded, so saving applies only this form's changes (see save_meta()).
		$html .= sprintf(
			'<input type="hidden" name="syndication_urls_loaded" value="%s" />',
			esc_attr( wp_json_encode( $stored ) )
		);
		if ( $others ) {
			$html .= sprintf(
				'<p>%s</p><ul>',
				esc_html__( 'Also shown, from other plugins. To remove one, remove it in the plugin that added it.', 'syndication-links' )
			);
			foreach ( $others as $url ) {
				$html .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $url ) );
			}
			$html .= '</ul>';
		}
		$html .= '</div>';

		echo $html; // phpcs:ignore
	}

	/**
	 * Saves the links submitted from the links box.
	 *
	 * The caller checks the nonce and capability. Submitting no links deletes them.
	 *
	 * @param string $type Object type: 'post' or 'comment'.
	 * @param int    $id   Object ID.
	 */
	public static function save_meta( $type, $id ) {
		// The field is a list; anything else isn't from the metabox, so leave the links alone.
		if ( ! isset( $_POST['syndication_urls'] ) || ! is_array( $_POST['syndication_urls'] ) ) {
			return;
		}

		// clean_urls() validates each URL, so only the shape is checked here.
		$syn = self::clean_urls( array_filter( array_filter( wp_unslash( $_POST['syndication_urls'] ), 'is_string' ) ) );

		// Apply only what this form changed, against the links as they're stored now. Links
		// added since the form was loaded, such as by syndication or because the block editor
		// doesn't reload the box after saving, are kept.
		if ( isset( $_POST['syndication_urls_loaded'] ) && is_string( $_POST['syndication_urls_loaded'] ) ) {
			$loaded = json_decode( wp_unslash( $_POST['syndication_urls_loaded'] ), true );
			$loaded = is_array( $loaded ) ? self::clean_urls( array_filter( $loaded, 'is_string' ) ) : array();
			$syn    = self::clean_urls( array_merge( $syn, array_diff( self::get_stored_links( $type, $id ), $loaded ) ) );
			// The box showed the legacy links too, so they're now saved in mf2_syndication or removed.
			delete_metadata( $type, $id, 'syndication_urls' );
		}

		if ( empty( $syn ) ) {
			delete_metadata( $type, $id, 'mf2_syndication' );
		} else {
			self::add_syndication_link( $type, $id, $syn, true );
		}
	}


	/**
	 * Saves a post's links from the links box, after checking the nonce and capability.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_post_meta( $post_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 * Check if our nonce is set.
		 */
		if ( ! isset( $_POST['syn_metabox_nonce'] ) ) {
			return;
		}
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['syn_metabox_nonce'], 'syn_metabox' ) ) {
			return;
		}
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
		}
		self::save_meta( 'post', $post_id );
	}



	/**
	 * Saves a comment's links from the links box, after checking the nonce and capability.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public static function save_comment_meta( $comment_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 * Check if our nonce is set.
		 */
		if ( ! isset( $_POST['syn_metabox_nonce'] ) ) {
			return;
		}
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['syn_metabox_nonce'], 'syn_metabox' ) ) {
			return;
		}
		// Check the user's permissions.
		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			return;
		}
		self::save_meta( 'comment', $comment_id );
	}

	/**
	 * Returns a URL's registrable domain: `www.example.com` becomes `example.com`.
	 *
	 * @param string $url The URL.
	 * @return string|null The domain, or null if the URL has no host.
	 */
	public static function extract_domain_name( $url ) {
		$parse = wp_parse_url( $url, PHP_URL_HOST );
		return preg_replace( '/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/', '$2', $parse );
	}

	/**
	 * Adds syndication links to an object.
	 *
	 * New links go after the existing ones, and duplicates are dropped.
	 *
	 * @param string          $type    Object type, e.g. 'post' or 'comment'.
	 * @param int             $id      Object ID.
	 * @param string|string[] $uri     A URL, or an array of URLs.
	 * @param bool            $replace Optional. Whether to replace the existing links. Default false.
	 * @return int|bool The result of update_metadata(), or false if there was nothing to add.
	 */
	public static function add_syndication_link( $type, $id, $uri, $replace = false ) {
		if ( $replace ) {
			return update_metadata( $type, $id, 'mf2_syndication', $uri );
		}
		// Only the stored links: the ones other plugins add at read time aren't saved with them.
		$links = self::get_stored_links( $type, $id );
		if ( is_string( $uri ) ) {
			$links[] = $uri;
		}
		if ( is_array( $uri ) ) {
			$links = array_merge( $links, $uri );
		}
		$links = self::clean_urls( $links );
		if ( empty( $links ) ) {
			return false;
		}

		return update_metadata( $type, $id, 'mf2_syndication', $links );
	}

	/**
	 * Number of posts, and of comments, migrated per run.
	 *
	 * @since 5.0.0
	 */
	const LEGACY_BATCH = 100;

	/**
	 * Schedules the migration of the legacy syndication_urls meta, until it's done.
	 *
	 * @since 5.0.0
	 */
	public static function schedule_legacy_migration() {
		if ( get_option( 'syndication_links_legacy_migrated' ) || wp_next_scheduled( 'syn_migrate_legacy_links' ) ) {
			return;
		}
		wp_schedule_single_event( time(), 'syn_migrate_legacy_links' );
	}

	/**
	 * Moves a batch of posts and comments from the legacy syndication_urls meta into mf2_syndication.
	 *
	 * Runs on WP-Cron. It schedules itself again until no legacy meta is left,
	 * then records that in the syndication_links_legacy_migrated option.
	 *
	 * @since 5.0.0
	 */
	public static function migrate_legacy_links() {
		$posts    = get_posts(
			array(
				// Every type and status: 'any' leaves out types and statuses excluded from search.
				'post_type'      => get_post_types(),
				'post_status'    => array_keys( get_post_stati() ),
				'meta_key'       => 'syndication_urls', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Batched background migration on cron.
				'fields'         => 'ids',
				'posts_per_page' => self::LEGACY_BATCH,
				'no_found_rows'  => true,
			)
		);
		$comments = get_comments(
			array(
				'meta_key' => 'syndication_urls', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Batched background migration on cron.
				'fields'   => 'ids',
				'number'   => self::LEGACY_BATCH,
				'status'   => 'any',
			)
		);
		update_meta_cache( 'post', $posts );
		update_meta_cache( 'comment', $comments );
		foreach ( $posts as $id ) {
			self::migrate_legacy_meta( 'post', $id );
		}
		foreach ( $comments as $id ) {
			self::migrate_legacy_meta( 'comment', $id );
		}

		if ( count( $posts ) < self::LEGACY_BATCH && count( $comments ) < self::LEGACY_BATCH ) {
			update_option( 'syndication_links_legacy_migrated', 1 );
			return;
		}
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'syn_migrate_legacy_links' );
	}

	/**
	 * Merges one object's legacy syndication_urls meta into mf2_syndication, then deletes it.
	 *
	 * @since 5.0.0
	 *
	 * @param string $type 'post' or 'comment'.
	 * @param int    $id   Object ID.
	 */
	private static function migrate_legacy_meta( $type, $id ) {
		$old = get_metadata( $type, $id, 'syndication_urls', true );
		if ( $old && is_string( $old ) ) {
			$urls = get_metadata( $type, $id, 'mf2_syndication', true );
			if ( is_string( $urls ) ) {
				$urls = explode( "\n", $urls );
			}
			$urls = self::clean_urls( array_merge( (array) $urls, explode( "\n", $old ) ) );
			if ( $urls ) {
				update_metadata( $type, $id, 'mf2_syndication', $urls );
			}
		}
		delete_metadata( $type, $id, 'syndication_urls' );
	}

	/**
	 * Returns the links stored for an object, without the ones other plugins add.
	 *
	 * Includes links still in the legacy meta that the background migration hasn't moved
	 * yet. Reading never writes.
	 *
	 * @since 5.0.0
	 *
	 * @param string $type Object type, e.g. 'post' or 'comment'.
	 * @param int    $id   Object ID.
	 * @return string[] The URLs, cleaned.
	 */
	public static function get_stored_links( $type, $id ) {
		$urls = get_metadata( $type, $id, 'mf2_syndication', true );
		if ( is_string( $urls ) ) {
			$urls = explode( "\n", $urls );
		}
		$urls = is_array( $urls ) ? $urls : array();
		$old  = get_metadata( $type, $id, 'syndication_urls', true );
		if ( $old && is_string( $old ) ) {
			$urls = array_merge( $urls, explode( "\n", $old ) );
		}
		return self::clean_urls( array_filter( $urls, 'is_string' ) );
	}

	/**
	 * Returns an object's syndication links.
	 *
	 * The stored links (see get_stored_links()), plus links other plugins add through
	 * the `get_{$type}_syndication_links` filter.
	 *
	 * @param string $type Object type, e.g. 'post' or 'comment'.
	 * @param int    $id   Object ID.
	 * @return string[] The URLs, or an empty array if there are none.
	 */
	public static function get_syndication_links_data( $type, $id ) {
		$urls = self::get_stored_links( $type, $id );

		/**
		 * Filters an object's syndication links before they're cleaned and returned.
		 *
		 * The dynamic part of the hook is the object type: `get_post_syndication_links`
		 * or `get_comment_syndication_links`. Other plugins use it to add the links they
		 * recorded. Duplicates and invalid URLs are dropped afterwards.
		 *
		 * @param string[] $urls The URLs.
		 * @param int      $id   Object ID.
		 */
		$urls = apply_filters( "get_{$type}_syndication_links", $urls, $id );

		if ( 'post' === $type ) {
			/**
			 * Filters a post's syndication links.
			 *
			 * Kept for backward compatibility; use `get_post_syndication_links` instead.
			 *
			 * @param string[] $urls    The URLs.
			 * @param int      $post_id Post ID.
			 */
			$urls = apply_filters( 'syn_add_links', $urls, $id );
		}

		$urls = array_unique( self::clean_urls( $urls ) );
		return array_values( array_filter( $urls ) );
	}

	/**
	 * Returns the markup for each of an object's syndication links.
	 *
	 * Bridgy targets are left out; get_syndication_links() adds them separately.
	 *
	 * @param string   $type Object type, e.g. 'post' or 'comment'.
	 * @param int|null $id   Object ID.
	 * @param array    $args Optional. Display arguments; see get_post_syndication_links().
	 * @return string[] One `<a>` element per link.
	 */
	public static function get_syndication_links_elements( $type, $id = null, $args = array() ) {
		$urls = self::get_syndication_links_data( $type, $id );
		if ( empty( $urls ) ) {
			return array();
		}
		$display = self::get_syndication_links_display_option();
		$r       = wp_parse_args( $args, self::get_syndication_links_display_defaults() );
		$rel     = is_single() ? ' rel="syndication">' : '>';
		$links   = array();
		foreach ( $urls as $url ) {
			if ( empty( $url ) || ! is_string( $url ) ) {
				continue; }
			// Bridgy targets aren't copies of the post; get_syndication_links() adds them as invisible links.
			if ( self::is_bridgy_target( $url ) ) {
				continue;
			}
			$name = Syn_Link_Domain_Icon_Map::url_to_name( $url );
			// Only build the icon when it's shown, so text-only and hidden links don't load icon styles.
			$icon = ( $r['icons'] && 'hidden' !== $display ) ? Syn_Link_Domain_Icon_Map::get_icon( $name ) : '';
			if ( 'website' === $name ) {
				$name = self::extract_domain_name( $url );
			}
			$syn = $icon . ( $r['text'] ? esc_html( Syn_Link_Domain_Icon_Map::get_title( $name ) ) : '' );

			if ( 'hidden' === $display ) {
				$link    = '<data class="u-syndication" value="%1$s"></data>';
				$links[] = sprintf( $link, esc_url( $url ) );
			} else {
				$link    = '<a aria-label="%1$s" class="u-syndication %2$s" href="%3$s"%4$s %5$s</a>';
				$links[] = sprintf( $link, esc_attr( $name ), esc_attr( $r['single-css'] ), esc_url( $url ), $rel, $syn );
			}
		}

		return $links;
	}

	/**
	 * Whether a URL is a Bridgy target, such as https://brid.gy/publish/mastodon or https://fed.brid.gy/.
	 *
	 * The Bridgy webmention providers add the target to the post's links while they
	 * syndicate, because Bridgy checks that the post links to it. It isn't a copy of
	 * the post, so it's not shown as one.
	 *
	 * @since 5.0.0
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_bridgy_target( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return 'brid.gy' === $host || str_ends_with( $host, '.brid.gy' );
	}

	/**
	 * Builds invisible links to the Bridgy targets among a post's links.
	 *
	 * Bridgy documents an empty link, e.g. <a href="https://brid.gy/publish/mastodon"></a>,
	 * as what it looks for, so these are output in every display mode.
	 *
	 * @since 5.0.0
	 *
	 * @param string[] $urls Syndication links.
	 * @return string Markup.
	 */
	public static function get_bridgy_target_links( $urls ) {
		$html = '';
		foreach ( (array) $urls as $url ) {
			if ( is_string( $url ) && self::is_bridgy_target( $url ) ) {
				$html .= sprintf( '<a href="%1$s"></a>', esc_url( $url ) );
			}
		}
		return $html;
	}

	/**
	 * Returns the display setting for the current request.
	 *
	 * Archives use 'hidden' unless the setting to show links on archives is on.
	 *
	 * @return string 'text', 'icons', 'iconstext' or 'hidden'.
	 */
	public static function get_syndication_links_display_option() {
		$display = get_option( 'syndication-links_display' );
		if ( ! is_singular() ) {
			$display = get_option( 'syndication-links_archives' ) ? $display : 'hidden';
		}

		return $display;
	}

	/**
	 * Returns the default display arguments, from the display settings.
	 *
	 * @return array Display arguments; see get_post_syndication_links().
	 */
	public static function get_syndication_links_display_defaults() {
		$display  = self::get_syndication_links_display_option();
		$defaults = array(
			'style'            => ( 'hidden' === $display ) ? 'span' : 'ul',
			'text'             => in_array( $display, array( 'text', 'iconstext' ), true ),
			'icons'            => in_array( $display, array( 'icons', 'iconstext' ), true ),
			'container-css'    => 'relsyn',
			'single-css'       => 'syn-link',
			'text-css'         => 'syn-text',
			'show_text_before' => true,
		);

		/**
		 * Filters the default display arguments for syndication links.
		 *
		 * @param array $defaults Display arguments; see get_post_syndication_links().
		 */
		return apply_filters( 'syn_links_display_defaults', $defaults );
	}

	/**
	 * Returns the text shown before the links, from the settings.
	 *
	 * @param string $css Optional. Class for the wrapping span. Default 'syn-text'.
	 * @return string The markup, or '' when the links are hidden.
	 */
	public static function get_syndication_links_text_before( $css = 'syn-text' ) {
		$display = self::get_syndication_links_display_option();

		return ( 'hidden' !== $display ) ? '<span class="' . esc_attr( $css ) . '">' . wp_kses_post( (string) get_option( 'syndication-links_text_before' ) ) . '</span>' : '';
	}



	/**
	 * Returns the markup for an object's syndication links.
	 *
	 * Bridgy targets are added after the list as empty links, in every display mode.
	 *
	 * @param string   $type Object type, e.g. 'post' or 'comment'.
	 * @param int|null $id   Object ID.
	 * @param array    $args Optional. Display arguments; see get_post_syndication_links().
	 * @return string The markup, or '' if there are no links.
	 */
	public static function get_syndication_links( $type, $id = null, $args = array() ) {
		$r = wp_parse_args( $args, self::get_syndication_links_display_defaults() );

		$targets = self::get_bridgy_target_links( self::get_syndication_links_data( $type, $id ) );
		$links   = self::get_syndication_links_elements( $type, $id, $r );
		if ( empty( $links ) ) {
			return $targets;
		}

		if ( $r['show_text_before'] ) {
			$textbefore = self::get_syndication_links_text_before( $r['text-css'] );
		} else {
			$textbefore = '';
		}

		switch ( $r['style'] ) {
			case 'p':
				$before = '<p class="' . esc_attr( $r['container-css'] ) . '"><span>';
				$sep    = '</span><span>';
				$after  = '</span></p>';
				break;
			case 'ol':
				$before = '<ol class="' . esc_attr( $r['container-css'] ) . '"><li>';
				$sep    = '</li><li>';
				$after  = '</li></ol>';
				break;
			case 'span':
				$before = '<span class="' . esc_attr( $r['container-css'] ) . '">';
				$sep    = ' ';
				$after  = '</span>';
				break;
			default:
				$before = '<ul class="' . esc_attr( $r['container-css'] ) . '"><li>';
				$sep    = '</li><li>';
				$after  = '</li></ul>';
		}

		return $textbefore . $before . join( $sep, $links ) . $after . $targets;
	}

	/**
	 * Hides the plugin's meta from the Custom Fields box.
	 *
	 * The links and the log have their own boxes. The keys keep their names, since
	 * other plugins and themes read `mf2_syndication`.
	 *
	 * @since 5.0.0
	 *
	 * @param bool   $protected Whether the key is protected.
	 * @param string $meta_key  Meta key.
	 * @return bool Whether the key is protected.
	 */
	public static function protect_meta( $protected, $meta_key ) {
		if ( in_array( $meta_key, array( 'mf2_syndication', 'syndication_urls', 'syndication_log' ), true ) ) {
			return true;
		}
		return $protected;
	}
} // End Class
