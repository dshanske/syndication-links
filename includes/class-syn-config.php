<?php

add_action( 'init', array( 'Syn_Config', 'init' ) );

class Syn_Config {
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( 'Syn_Config', 'enqueue_scripts' ) );
		$option = get_option( 'syndication_content_options' );
		if ( $option['the_content'] != '1' ) {
			add_filter( 'the_content', array( 'Syn_Config', 'the_content' ) , 20 );
		}
		add_action( 'admin_menu', array( 'Syn_Config', 'admin_menu' ), 11 );
		add_action( 'admin_init', array( 'Syn_Config', 'admin_init' ) );
	}

	public static function enqueue_scripts() {
		$options = get_option( 'syndication_content_options' );
		if ( $options['bw'] == 1 ) {
			wp_enqueue_style( 'syndication-style', plugin_dir_url( dirname( __FILE__ ) ) . 'css/syn-bw.min.css', array(), SYNDICATION_LINKS_VERSION );
		} else {
			wp_enqueue_style( 'syndication-style', plugin_dir_url( dirname( __FILE__ ) ) . 'css/syn.min.css', array(), SYNDICATION_LINKS_VERSION );
		}
	}

	public static function admin_init() {
		// Syndication Content Options
		register_setting( 'syndication_options', 'syndication_content_options' );
		add_settings_section( 'syndication-content', __( 'Content Options', 'Syn Links' ), array( 'Syn_Config', 'options_callback' ), 'links_options' );
		add_settings_field( 'the_content', __( 'Disable Syndication Links in the Content', 'Syn Links' ), array( 'Syn_Config', 'content_callback' ), 'links_options', 'syndication-content' ,  array( 'name' => 'the_content' ) );
		add_settings_field( 'just_icons', __( 'Display Text', 'Syn Links' ), array( 'Syn_Config', 'content_callback' ), 'links_options', 'syndication-content',  array( 'name' => 'just_icons' ) );
		add_settings_field( 'bw', __( 'Black Icons', 'Syn Links' ), array( 'Syn_Config', 'content_callback' ), 'links_options', 'syndication-content',  array( 'name' => 'bw' ) );
		add_settings_field( 'text_before', __( 'Text Before Links', 'Syn Links' ), array( 'Syn_Config', 'text_before_callback' ), 'links_options', 'syndication-content' );
	}

	public static function admin_menu() {
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
		    add_submenu_page(
				'indieweb',
				__( 'Syndication Links', 'Syn Links' ), // page title
				__( 'Syndication Links', 'Syn Links' ), // menu title
				'manage_options', // access capability
				'syndication_links',
				array( 'Syn_Config', 'links_options' )
			);
		} else {
			add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', array( 'Syn_Config', 'links_options' ) );
		}
	}

	public static function options_callback() {
		esc_html_e( 'Options for Presenting Syndication Links in Posts.', 'Syn Links' );
		echo '<p>';
		esc_html_e( 'Syndication Links by default will add links to the content. You can disable this for theme support.', 'Syn Links' );
		echo '</p>';
	}

	public static function content_callback(array $args) {
		$options = get_option( 'syndication_content_options' );
		$name = $args['name'];
		$checked = $options[ $name ];
		echo "<input name='syndication_content_options[$name]' type='hidden' value='0' />";
		echo "<input name='syndication_content_options[$name]' type='checkbox' value='1' " . checked( 1, $checked, false ) . ' /> ';
	}

	public static function text_before_callback() {
		$options = get_option( 'syndication_content_options' );
		$text = $options['text_before'];
		echo "<input name='syndication_content_options[text_before]' type='text' value='" . $text . "' /> ";
	}

	public static function links_options() {
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Syndication Links', 'Syn Links' ) . '</h2>';
		echo '<p>';
		esc_html_e( 'Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites.', 'Syn Links' );
		echo '</p><hr />';
		echo '<form method="post" action="options.php">';
		settings_fields( 'syndication_options' );
		do_settings_sections( 'links_options' );
		submit_button();
		echo '</form>' . '</div>';
	}

	public static function the_content($meta = '' ) {
		return $meta . get_syndication_links();
	}

} // End Class

?>
