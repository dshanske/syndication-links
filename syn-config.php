<?php

function add_syndication_options_to_menu(){
	add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', 'syndication_links_options');
}

add_action('admin_menu', 'add_syndication_options_to_menu');

add_action( 'admin_init', 'syndication_options_init' );

function syndication_options_init() {
    // Syndication Networks
    $strings = get_syn_network_strings(); 
    register_setting( 'syndication_options', 'syndication_network_options' );
  // Syndication Content Options
    register_setting( 'syndication_options', 'syndication_content_options' );
    add_settings_section( 'syndication-content', __('Content Options', 'Syn Links'), 'syndication_content_options_callback', 'syndication_links_options' );
    add_settings_field( 'the_content', __('Disable Syndication Links in the Content', 'Syn Links'), 'syndication_content_callback', 'syndication_links_options', 'syndication-content' ,  array( 'name' => 'the_content') );
    add_settings_field( 'just_icons', __('Display Text', 'Syn Links'), 'syndication_content_callback', 'syndication_links_options', 'syndication-content',  array( 'name' => 'just_icons')
 );
    add_settings_field( 'text_before', __('Text Before Links', 'Syn Links'), 'syndication_text_before_callback', 'syndication_links_options', 'syndication-content' );


}

function syndication_content_options_callback() {
  _e ('Options for Presenting Syndication Links in Posts.', 'Syn Links');
}

function syndication_content_callback(array $args) {
  $options = get_option('syndication_content_options');
  $name = $args['name'];
  $checked = $options[$name];
  echo "<input name='syndication_content_options[$name]' type='hidden' value='0' />";
  echo "<input name='syndication_content_options[$name]' type='checkbox' value='1' " . checked( 1, $checked, false ) . " /> ";
}

function syndication_text_before_callback() {
  $options = get_option('syndication_content_options');
  $text = $options['text_before'];
  echo "<input name='syndication_content_options[text_before]' type='text' value='" . $text . "' /> ";
} 

function syndication_links_options() {
  // migrate_synlinks();
  echo '<div class="wrap">';
  echo '<h2>' . __('Syndication Links', 'Syn Links') . '</h2>';  
  echo '<p>';
  _e ('Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites.', 'Syn Links');
  echo '</p><hr />';
  ?>
  <form method="post" action="options.php">
  <?php 
    settings_fields( 'syndication_options' );
    do_settings_sections( 'syndication_links_options' );
    submit_button(); 
  ?>
    </form>
  </div>
  <?php
}

?>
