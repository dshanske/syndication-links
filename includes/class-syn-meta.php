<?php
// Adds Post Meta Box for Syndication URLs
add_action( 'init', array( 'Syn_Meta', 'init' ) );

// The Syn_Meta class sets up post meta boxes for data associated with Syndication
class Syn_Meta {
	public static function init() {
		add_action( 'admin_init', array( 'Syn_Meta', 'setup' ) );
		add_action( 'save_post', array( 'Syn_Meta', 'save_post_meta' ) );
		add_action( 'edit_comment', array( 'Syn_Meta', 'save_comment_meta' ) );
		$args = array(
			'type'         => 'string',
			'description'  => 'Syndication URLs',
			'single'       => false,
			'show_in_rest' => true,
		);
		register_meta( 'post', 'mf2_syndication', $args );
		add_filter( 'query_vars', array( 'Syn_Meta', 'query_var' ) );
		add_action( 'parse_query', array( 'Syn_Meta', 'parse_query' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( 'Syn_Meta', 'wp_privacy_personal_data_exporters' ), 10 );
	}

	public static function wp_privacy_personal_data_exporters( $exporters ) {
		$exporters['syndication-links'] = array(
			'exporter_friendly_name' => __( 'Syndication Links Plugin', 'syndication-links' ),
			'callback'               => array( 'Syn_Meta', 'data_exporter' ),
		);
		return $exporters;
	}

	public static function data_exporter( $email_address, $page = 1 ) {
		$number = 500; // Limit us to avoid timing out
		$page   = (int) $page;

		$export_items = array();
		$comments     = get_comments(
			array(
				'author_email' => $email_address,
				'number'       => $number,
				'paged'        => $page,
				'order_by'     => 'comment_ID',
				'order'        => 'ASC',
			)
		);

		foreach ( (array) $comments as $comment ) {
			$syndication = self::get_syndication_links_data( $comment );
			if ( ! empty( $syndication ) ) {
				$item_id     = "comment-{$comment->comment_ID}";
				$group_id    = 'comments';
				$group_label = __( 'Comments', 'syndication-links' );

				$data           = array(
					array(
						'name'  => __( 'Syndication Links', 'syndication-links' ),
						'value' => $syndication,
					),
				);
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





	public static function screens() {
		$screens = array( 'post', 'page', 'comment', 'indieweb_page_syndication_links' );
		return apply_filters( 'syn_metabox_types', $screens );
	}

	public static function enqueue( $hook_suffix ) {
		$screens = self::screens();
		if ( in_array( get_current_screen()->id, $screens, true ) ) {
			wp_enqueue_script(
				'synlinks',
				plugins_url( 'js/synlinks.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				SYNDICATION_LINKS_VERSION,
				true
			);
		}
	}


	public static function query_var( $vars ) {
		$vars[] = 'original-of';
			return $vars;
	}

	public static function parse_query( $wp ) {
		if ( ! array_key_exists( 'original-of', $wp->query_vars ) ) {
			return;
		}
		$url = $wp->get( 'original-of' );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			include get_404_template();
			exit;
		}
		$url   = esc_url_raw( $url );
		$args  = array(
			'fields'     => 'ids',
			'meta_key'   => 'mf2_syndication',
			'meta_query' => array(
				'key'     => 'mf2_syndication',
				'value'   => $url,
				'compare' => 'LIKE',
			),
		);
		$posts = get_posts( $args );
		if ( empty( $posts ) ) {
			include get_404_template();
			exit;
		}
		wp_safe_redirect( get_permalink( $posts[0] ) );
		exit;
	}


	/*
	Filters incoming URLs.
	 *
	 * @param array $urls An array of URLs to filter.
	 * @return array A filtered array of unique URLs.
	 * @uses clean_url
	 */
	public static function clean_urls( $urls ) {
		if ( ! is_array( $urls ) ) {
			return $urls;
		}
		$array = array_map( array( 'Syn_Meta', 'clean_url' ), $urls );
		return array_filter( array_unique( $array ) );
	}

	/**
	 * Filters a single syndication URL.
	 *
	 * @param string $string A string that is expected to be a syndication URL.
	 * @return string|bool The filtered and escaped URL string, or FALSE if invalid.
	 * @used-by clean_urls
	 */
	public static function clean_url( $string ) {
		if ( is_array( $string ) ) {
			return $string;
		}
		$url = trim( $string );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		// Rewrite these to https as needed
		$secure = apply_filters( 'syn_rewrite_secure', array( 'facebook.com', 'twitter.com', 'huffduffer.com', 'foursquare.com' ) );
		if ( in_array( self::extract_domain_name( $url ), $secure, true ) ) {
			$url = preg_replace( '/^http:/i', 'https:', $url );
		}
		$url = esc_url_raw( $url );
		return $url;
	}

	/* Meta box setup function. */
	public static function setup() {
		add_action( 'admin_enqueue_scripts', array( 'Syn_Meta', 'enqueue' ) );
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Syn_Meta', 'add_meta_boxes' ) );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public static function add_meta_boxes() {
		add_meta_box(
			'synbox-meta',      // Unique ID
			esc_html__( 'Syndication Links', 'syndication-links' ),    // Title
			array( 'Syn_Meta', 'metabox' ),   // Callback function
			self::screens(),         // Admin page (or post type)
			'normal',         // Context
			'default',        // Priority
			array(
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			)
		);
	}

	public static function metabox( $object, $box ) {
		wp_nonce_field( 'syn_metabox', 'syn_metabox_nonce' );
		$urls = self::get_syndication_links_data( $object );

		if ( is_string( $urls ) ) {
			$urls = explode( PHP_EOL, $urls );
		}
		if ( ! $urls ) {
			$urls = array( '' );
		}

		$html  = '<div class="syndication_url_list">';
		$html .= sprintf(
			'<label for="syndication_urls">%s</label>',
			esc_html__( 'Add Links to this same content on other sites', 'syndication-links' )
		);
		$html .= '<ul>';
		foreach ( $urls as $url ) {
			$html .= sprintf(
				'<li><input type="text" name="syndication_urls[]" class="widefat" id="syndication_urls" value="%s" /></li>',
				esc_url_raw( $url )
			);
		}
		$html .= '</ul>';
		$html .= sprintf(
			'<button class="button-primary" id="add-syn-link-button">%s</button>',
			esc_html__( 'Add', 'syndication-links' )
		);
		$html .= '</div>';

		echo $html; // phpcs:ignore
	}

	/* Save the meta box's metadata. */
	public static function save_meta( $type, $id ) {
		if ( empty( $_POST['syndication_urls'] ) ) {
			delete_metadata( $type, $id, 'mf2_syndication' );
		} else {
			$meta = $_POST['syndication_urls'];
			if ( is_string( $meta ) ) {
				$meta = explode( PHP_EOL, $_POST['syndication_urls'] );
			}
			$meta = self::clean_urls( $meta );
			update_metadata( $type, $id, 'mf2_syndication', $meta );
		}
	}


	/* Save the meta box's post metadata. */
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
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		self::save_meta( 'post', $post_id );
	}



	/* Save the meta box's comment metadata. */
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
		self::save_meta( 'comment', $comment_id );

	}

	public static function extract_domain_name( $url ) {
		$parse = wp_parse_url( $url, PHP_URL_HOST );
		return preg_replace( '/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/', '$2', $parse );
	}

	public static function add_syndication_link( $object = null, $uri, $replace = false ) {
		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( empty( $uri ) ) {
			return;
		}
		if ( $object instanceof WP_Post ) {
			$type = 'post';
			$id   = $object->ID;
			do_action( 'edit_post', $id, $object );
		}
		if ( $object instanceof WP_Comment ) {
			$type = 'comment';
			$id   = $object->comment_ID;
		}
		if ( $replace ) {
			return update_metadata( $type, $id, 'mf2_syndication', $uri );
		}
		$links = get_metadata( $type, $id, 'mf2_syndication', true );
		if ( ! is_array( $links ) ) {
			$links = array();
		}
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

	public static function get_syndication_links_data( $object = null ) {
		$urls = array();
		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( $object instanceof WP_Post ) {
			$urls = get_post_meta( $object->ID, 'mf2_syndication', true );
			if ( $urls ) {
				if ( is_string( $urls ) ) {
					$urls = explode( "\n", $urls );
				}
			} else {
				$urls = array();
			}
			$old = get_post_meta( $object->ID, 'syndication_urls', true );
			if ( $old ) {
				$old = explode( "\n", $old );
				if ( is_array( $old ) ) {
					$urls = array_filter( array_unique( array_merge( $urls, $old ) ) );
					update_post_meta( $object->ID, 'mf2_syndication', $urls );
					delete_post_meta( $object->ID, 'syndication_urls' );
				}
			}
			// Allow adding of additional links before display but ensuring they are unique
			$urls = apply_filters( 'syn_add_links', $urls, $object->ID );
		}
		if ( $object instanceof WP_Comment ) {
			$urls = get_comment_meta( $object->comment_ID, 'mf2_syndication', true );
			if ( $urls ) {
				if ( is_string( $urls ) ) {
					$urls = explode( "\n", $urls );
				}
			} else {
				$urls = array();
			}
		}
		$urls = array_unique( self::clean_urls( $urls ) );
		return array_filter( $urls );
	}

	public static function get_syndication_links_elements( $object = null, $args = array() ) {
		$urls = self::get_syndication_links_data( $object );
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
			$name = Syn_Link_Domain_Icon_Map::url_to_name( $url );
			$icon = Syn_Link_Domain_Icon_Map::get_icon( $name );
			if ( 'website' === $name ) {
				$name = self::extract_domain_name( $url );
			}
			$syn = ( $r['icons'] ? $icon : '' ) . ( $r['text'] ? Syn_Link_Domain_Icon_Map::get_title( $name ) : '' );

			$links[] = sprintf( '<a aria-label="%1$s" class="u-syndication %2$s" href="%3$s"%4$s %5$s</a>', $name, $r['single-css'], esc_url( $url ), $rel, $syn );
		}

		return $links;
	}

	public static function get_syndication_links_display_option() {
		$display = get_option( 'syndication-links_display' );
		if ( ! is_singular() ) {
			$display = get_option( 'syndication-links_archives' ) ? $display : 'hidden';
		}

		return $display;
	}

	public static function get_syndication_links_display_defaults() {
		$display  = self::get_syndication_links_display_option();
		$defaults = array(
			'style'            => 'ul',
			'text'             => in_array( $display, array( 'text', 'iconstext' ), true ),
			'icons'            => in_array( $display, array( 'icons', 'iconstext' ), true ),
			'container-css'    => 'relsyn',
			'single-css'       => 'syn-link',
			'text-css'         => 'syn-text',
			'show_text_before' => true,
		);

		return apply_filters( 'syn_links_display_defaults', $defaults );
	}

	public static function get_syndication_links_text_before( $css = 'syn-text' ) {
		$display = self::get_syndication_links_display_option();

		return ( 'hidden' !== $display ) ? '<span class="' . $css . '">' . get_option( 'syndication-links_text_before' ) . '</span>' : '';
	}



	public static function get_syndication_links( $object = null, $args = array() ) {
		$r = wp_parse_args( $args, self::get_syndication_links_display_defaults() );

		$links = self::get_syndication_links_elements( $object, $r );
		if ( empty( $links ) ) {
			return '';
		}

		if ( $r['show_text_before'] ) {
			$textbefore = self::get_syndication_links_text_before( $r['text-css'] );
		} else {
			$textbefore = '';
		}

		switch ( $r['style'] ) {
			case 'p':
				$before = '<p class="' . $r['container-css'] . '"><span>';
				$sep    = '</span><span>';
				$after  = '</span></p>';
				break;
			case 'ol':
				$before = '<ol class="' . $r['container-css'] . '"><li>';
				$sep    = '</li><li>';
				$after  = '</li></ol>';
				break;
			case 'span':
				$before = '<span class="' . $r['container-css'] . '">';
				$sep    = ' ';
				$after  = '</span>';
				break;
			default:
				$before = '<ul class="' . $r['container-css'] . '"><li>';
				$sep    = '</li><li>';
				$after  = '</li></ul>';
		}

		return $textbefore . $before . join( $sep, $links ) . $after;
	}

	public static function get_post_syndication_links( $post_ID = null, $args = array() ) {
		return get_syndication_links( $post_ID, $args );
	}

	public static function get_comment_syndication_links( $comment_id = null, $args = array() ) {
		return get_syndication_links( get_comment( $comment_id ), $args );
	}

} // End Class
