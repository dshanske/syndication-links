<?php

add_action( 'init', array('syn_config', 'init') );

class syn_config {
	public function init() {
		add_action( 'wp_enqueue_scripts', array('syn_config', 'enqueue_scripts') );
		// Return Syndication URLs as part of the JSON Rest API
		add_filter("json_prepare_post", array('syn_config', 'json_rest_add_synmeta'),10,3);
		$option = get_option('syndication_content_options');
		if($option['the_content']!="1"){
			add_filter( 'the_content', array('syn_config', 'the_content') , 20 );
   	}
	add_action( 'admin_menu', array('syn_config', 'admin_menu') );
	add_action( 'admin_init', array('syn_config', 'admin_init') );
	}

	public function enqueue_scripts() {
		$options = get_option('syndication_content_options');
		if ($options['fontawesome'] == 1) {
			if ($options['bw']==1) {
				wp_enqueue_style( 'syndication-style', plugin_dir_url( dirname(__FILE__) ) . 'css/awesome-bw.min.css');
    	}
			else {
				wp_enqueue_style( 'syndication-style', plugin_dir_url( dirname(__FILE__) ) . 'css/awesome.min.css');
			}
		}
  	else {
			if ($options['bw']==1) {
				wp_enqueue_style( 'syndication-style', plugin_dir_url( dirname(__FILE__) ) . 'css/syn-bw.min.css');
			}
			else {
				wp_enqueue_style( 'syndication-style', plugin_dir_url( dirname(__FILE__) ) . 'css/syn.min.css');
			}
		}
	}

	public static function admin_init() {
  	// Syndication Content Options
		register_setting( 'syndication_options', 'syndication_content_options' );
		add_settings_section( 'syndication-content', __('Content Options', 'Syn Links'), array('syn_config', 'options_callback'), 'links_options' );
		add_settings_field( 'the_content', __('Disable Syndication Links in the Content', 'Syn Links'), array('syn_config', 'content_callback'), 'links_options', 'syndication-content' ,  array( 'name' => 'the_content') );
		add_settings_field( 'just_icons', __('Display Text', 'Syn Links'), array('syn_config', 'content_callback'), 'links_options', 'syndication-content',  array( 'name' => 'just_icons') );
		add_settings_field( 'bw', __('Black Icons', 'Syn Links'), array('syn_config', 'content_callback'), 'links_options', 'syndication-content',  array( 'name' => 'bw') );
		add_settings_field( 'fontawesome', __('Use Alternate Fontset', 'Syn Links'), array('syn_config', 'content_callback'), 'links_options', 'syndication-content',  array( 'name' => 'fontawesome') );
		add_settings_field( 'text_before', __('Text Before Links', 'Syn Links'), array('syn_config', 'text_before_callback'), 'links_options', 'syndication-content' );
	}

	public static function admin_menu(){
		add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', array('syn_config', 'links_options') );
	}

	public static function options_callback() {
		esc_html_e ('Options for Presenting Syndication Links in Posts.', 'Syn Links');
	}

	public static function content_callback(array $args) {
		$options = get_option('syndication_content_options');
		$name = $args['name'];
		$checked = $options[$name];
		echo "<input name='syndication_content_options[$name]' type='hidden' value='0' />";
		echo "<input name='syndication_content_options[$name]' type='checkbox' value='1' " . checked( 1, $checked, false ) . " /> ";
	}

	public static function text_before_callback() {
		$options = get_option('syndication_content_options');
		$text = $options['text_before'];
		echo "<input name='syndication_content_options[text_before]' type='text' value='" . $text . "' /> ";
	} 

	public static function links_options() {
		echo '<div class="wrap">';
		echo '<h2>' . __('Syndication Links', 'Syn Links') . '</h2>';  
		echo '<p>';
		esc_html_e ('Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites.', 'Syn Links');
		echo '</p><hr />';
		echo '<form method="post" action="options.php">';
		settings_fields( 'syndication_options' );
		do_settings_sections( 'links_options' );
		submit_button(); 
    echo '</form>' . '</div>';
	}

	public static function json_rest_add_synmeta($_post,$post,$context) {
		$syn = get_post_meta( $post["ID"], 'syndication_urls');
		if (!empty($syn)) {
			$urls = explode("\n", $syn);
			$_post['syndication'] = $urls;
		}
		return $_post;
	}

	public static function the_content($meta = "" ) {
   return $meta . get_syndication_links();
   }


} // End Class

?>
