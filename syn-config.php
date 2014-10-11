<?php

function add_syndication_options_to_menu(){
	add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', 'syndication_links_options');
}

add_action('admin_menu', 'add_syndication_options_to_menu');

add_action( 'admin_init', 'syndication_options_init' );
function syndication_options_init() {
    // Syndication Networks
    register_setting( 'syndication_options', 'syndication_network_options' );
    add_settings_section( 'syndication-networks', 'Syndication Networks', 'syndication_network_options_callback', 'syndication_links_options' );  
    add_settings_field( 'twitter', 'Twitter', 'syndication_network_callback', 'syndication_links_options', 'syndication-networks', 
		array( 'name' => 'twitter')
	);
    add_settings_field( 'facebook', 'Facebook', 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'facebook')
        ); 
    add_settings_field( 'gplus', 'Google+', 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'gplus')
        );  
    add_settings_field( 'instagram', 'Instagram', 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'instagram')
        );  



    // Syndication Content Options
    register_setting( 'syndication_options', 'syndication_content_options' );
    add_settings_section( 'syndication-content', 'Content Options', 'syndication_content_options_callback', 'syndication_links_options' );
    add_settings_field( 'the_content', 'Add Syndication Links to the Content', 'syndication_content_callback', 'syndication_links_options', 'syndication-content' ,  array( 'name' => 'the_content') );
    add_settings_field( 'just_icons', 'Display Text', 'syndication_content_callback', 'syndication_links_options', 'syndication-content',  array( 'name' => 'just_icons')
 );
    add_settings_field( 'text_before', 'Text Before Links', 'syndication_text_before_callback', 'syndication_links_options', 'syndication-content' );


}

function syndication_content_options_callback()
   {
	echo 'Options for Presenting Syndication Links in Posts.';
   }

function syndication_network_options_callback()
   {
        echo 'Syndicate For:';
   }

function syndication_network_callback(array $args)
   {
        $options = get_option('syndication_network_options');
        $name = $args['name'];
        $checked = $options[$name];
        echo "<input name='syndication_network_options[$name]' type='hidden' value='0' />";
        echo "<input name='syndication_network_options[$name]' type='checkbox' value='1' " . checked( 1, $checked, false ) . " /> ";
   }

function syndication_content_callback(array $args)
   {
        $options = get_option('syndication_content_options');
        $name = $args['name'];
        $checked = $options[$name];
        echo "<input name='syndication_content_options[$name]' type='hidden' value='0' />";
        echo "<input name='syndication_content_options[$name]' type='checkbox' value='1' " . checked( 1, $checked, false ) . " /> ";
   }

function syndication_text_before_callback()
   {
        $options = get_option('syndication_content_options');
        $text = $options['text_before'];
        echo "<input name='syndication_content_options[text_before]' type='text' value='" . $text . "' /> ";
   } 

function syndication_links_options() 
  {
    ?>
     <div class="wrap">
        <h2>Syndication Links</h2>  
        <p>Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites.</p>

        <hr />
        <form method="post" action="options.php">
        <?php settings_fields( 'syndication_options' ); ?>

         <?php do_settings_sections( 'syndication_links_options' ); ?>
         <?php submit_button(); ?>
       </form>
     </div>
    <?php
 }

?>
