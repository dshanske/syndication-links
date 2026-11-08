<?php
/**
 * Syndication links for comments received by webmention.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Syn_Webmention_Comments', 'init' ) );
add_action( 'admin_init', array( 'Syn_Webmention_Comments', 'admin_init' ), 11 );

/**
 * Stores the syndication links of a reply received by webmention with its comment.
 *
 * When someone replies on their own site and posts copies elsewhere, their reply's
 * microformats list the copies as `syndication`. The Webmention plugin doesn't keep
 * them, so this adds them to the comment's `mf2_syndication` meta, where they're shown
 * like any comment's syndication links. It uses only the Webmention plugin's public
 * `webmention_handler_mf2_set_properties` filter, and is off unless the setting is on.
 *
 * @since 5.0.0
 */
class Syn_Webmention_Comments {

	/**
	 * The option that turns this on.
	 *
	 * @var string
	 */
	const OPTION = 'syndication-links_webmention_comments';

	/**
	 * Registers the setting, and hooks the Webmention plugin's filter when it's on.
	 */
	public static function init() {
		register_setting(
			'syndication_display',
			self::OPTION,
			array(
				'type'         => 'boolean',
				'description'  => 'Store the syndication links of comments received by webmention',
				'show_in_rest' => true,
				'default'      => false,
			)
		);
		if ( get_option( self::OPTION ) ) {
			add_filter( 'webmention_handler_mf2_set_properties', array( __CLASS__, 'set_properties' ), 10, 2 );
		}
	}

	/**
	 * Adds the setting to the General tab, if the Webmention plugin is active.
	 */
	public static function admin_init() {
		if ( ! function_exists( 'send_webmention' ) ) {
			return;
		}
		add_settings_field(
			self::OPTION,
			__( 'Store Syndication Links of Webmention Comments', 'syndication-links' ),
			array( 'Syn_Config', 'checkbox_callback' ),
			'syndication_display_options',
			'syndication_display',
			array(
				'label_for' => self::OPTION,
			)
		);
	}

	/**
	 * Adds a reply's syndication links to the comment meta the Webmention plugin saves.
	 *
	 * @param array  $properties Comment meta to save, keyed by meta key.
	 * @param object $handler    The Webmention plugin's microformats handler.
	 * @return array The comment meta.
	 */
	public static function set_properties( $properties, $handler ) {
		if ( ! is_array( $properties ) || ! is_object( $handler ) || ! method_exists( $handler, 'get_webmention_item' ) ) {
			return $properties;
		}
		$item = $handler->get_webmention_item();
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_raw' ) ) {
			return $properties;
		}
		$links = self::syndication_links( $item->get_raw() );
		if ( $links ) {
			$properties['mf2_syndication'] = $links;
		}
		return $properties;
	}

	/**
	 * Returns the `syndication` URLs of a parsed microformats item.
	 *
	 * @param mixed $item The item, as parsed microformats.
	 * @return string[] The cleaned URLs.
	 */
	public static function syndication_links( $item ) {
		if ( ! is_array( $item ) || empty( $item['properties']['syndication'] ) || ! is_array( $item['properties']['syndication'] ) ) {
			return array();
		}
		$urls = array();
		foreach ( $item['properties']['syndication'] as $value ) {
			// A plain URL, or an embedded item whose value is the URL.
			if ( is_array( $value ) && isset( $value['value'] ) ) {
				$value = $value['value'];
			}
			if ( is_string( $value ) ) {
				$urls[] = $value;
			}
		}
		return Syn_Meta::clean_urls( $urls );
	}
}
