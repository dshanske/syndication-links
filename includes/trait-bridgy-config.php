<?php
/**
 *
 *
 *
 */
trait Bridgy_Config {
	public function register_setting() {
		register_setting(
			'syndication_providers',
			'bridgy_backlink',
			array(
				'type'         => 'string',
				'description'  => 'Disable Bridgy Linking Back to These Providers',
				'show_in_rest' => true,
				'default'      => 'maybe',
			)
		);
		register_setting(
			'syndication_providers',
			'bridgy_ignoreformatting',
			array(
				'type'         => 'boolean',
				'description'  => 'Tell Bridgy to Ignore Formatting when Publishing',
				'show_in_rest' => true,
				'default'      => false,
			)
		);
	}

	public static function options_callback() {
		?>
		<p><?php esc_html_e( 'Bridgy Publish can either use Webmentions or Micropub to trigger syndication. To use Micropub, a token must be provided. This token can be found on the Bridgy user page. If using Webmention, the elements of the post will be determined by Bridgy using Microformats in the page. If using Micropub, then the plugin will send the elements of the post to Bridgy directly, which gives the site more control over this' ); ?></p>

		<?php
	}

	public static function admin_init() {
		add_settings_section(
			'bridgy_options',
			__( 'Bridgy Publish Options', 'syndication-links' ),
			array( get_called_class(), 'options_callback' ),
			'syndication_provider_options'
		);

		add_settings_field(
			'bridgy_backlink',
			__( 'Bridgy Posts should link back to site posts', 'syndication-links' ),
			array(
				'Syn_Config',
				'select_callback',
			),
			'syndication_provider_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_backlink',
				'list' => array(
					''      => __( 'True', 'syndication-links' ),
					'true'  => __( 'False', 'syndication-links' ),
					'maybe' => __( 'If too long', 'syndication-links' ),
				),
			)
		);
		add_settings_field(
			'bridgy_ignoreformatting',
			__( 'Tell Bridgy to Ignore Formatting', 'syndication-links' ),
			array(
				'Syn_Config',
				'checkbox_callback',
			),
			'syndication_provider_options',
			'bridgy_options',
			array(
				'name' => 'bridgy_ignoreformatting',
			)
		);
	}

}
