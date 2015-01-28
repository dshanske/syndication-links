<?php

function get_syn_network_strings() {
        $strings = array(
                'Twitter' => _x( 'Twitter', 'Syn Links' ),
                'Facebook' => _x( 'Facebook', 'Syn Links' ),
                'Google+' => _x( 'Google+', 'Syn Links' ),
                'Instagram' => _x( 'Instagram', 'Syn Links' ),
                'Flickr' => _x( 'Flickr', 'Syn Links' ),
                'YouTube' => _x( 'YouTube', 'Syn Links' ),
                'LinkedIn' => _x( 'LinkedIn', 'Syn Links' ),
                'Tumblr' => _x( 'Tumblr', 'Syn Links' ),
                'WordPress' => _x( 'WordPress', 'Syn Links' ),
                'IndieNews' => _x( 'IndieNews', 'Syn Links' )
                );
                return apply_filters( 'syn_network_strings', $strings );
        }

function add_syndication_options_to_menu(){
	add_options_page( '', 'Syndication Links', 'manage_options', 'syndication_links', 'syndication_links_options');
}

add_action('admin_menu', 'add_syndication_options_to_menu');

add_action( 'admin_init', 'syndication_options_init' );
function syndication_options_init() {
    // Syndication Networks
    $strings = get_syn_network_strings(); 
    register_setting( 'syndication_options', 'syndication_network_options' );
    add_settings_section( 'syndication-networks', __('Syndication Networks', 'Syn Links'), 'syndication_network_options_callback', 'syndication_links_options' );  
    add_settings_field( 'twitter', $strings['Twitter'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks', 
		array( 'name' => 'Twitter')
	);
    add_settings_field( 'facebook', $strings['Facebook'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'Facebook')
        ); 
    add_settings_field( 'gplus', $strings['Google+'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'Google+')
        );  
   add_settings_field( 'instagram', $strings['Instagram'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'Instagram')
        );  
   add_settings_field( 'flickr', $strings['Flickr'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'Flickr')
        );
   add_settings_field( 'youtube', $strings['YouTube'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'YouTube')
        );
   add_settings_field( 'linkedin', $strings['LinkedIn'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'LinkedIn')
        );
   add_settings_field( 'tumblr', $strings['Tumblr'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'Tumblr')
        );
   add_settings_field( 'wordpress', $strings['WordPress'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'WordPress')
        );
   add_settings_field( 'indiewebcampnews', $strings['IndieNews'], 'syndication_network_callback', 'syndication_links_options', 'syndication-networks',
                array( 'name' => 'IndieNews')
        );
  // Syndication Content Options
    register_setting( 'syndication_options', 'syndication_content_options' );
    add_settings_section( 'syndication-content', __('Content Options', 'Syn Links'), 'syndication_content_options_callback', 'syndication_links_options' );
    add_settings_field( 'the_content', __('Add Syndication Links to the Content', 'Syn Links'), 'syndication_content_callback', 'syndication_links_options', 'syndication-content' ,  array( 'name' => 'the_content') );
    add_settings_field( 'just_icons', __('Display Text', 'Syn Links'), 'syndication_content_callback', 'syndication_links_options', 'syndication-content',  array( 'name' => 'just_icons')
 );
    add_settings_field( 'text_before', __('Text Before Links', 'Syn Links'), 'syndication_text_before_callback', 'syndication_links_options', 'syndication-content' );


}

function syndication_content_options_callback()
   {
	_e ('Options for Presenting Syndication Links in Posts.', 'Syn Links');
   }

function syndication_network_options_callback()
   {
        _e ('Networks to Syndicate To:', 'Syn Links');
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
    echo '<div class="wrap">';
    echo '<h2>' . __('Syndication Links', 'Syn Links') . '</h2>';  
    echo '<p>';
    _e ('Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites.', 'Syn Links');
    echo '</p><hr />';
    ?>
        <form method="post" action="options.php">
        <?php settings_fields( 'syndication_options' ); ?>

         <?php do_settings_sections( 'syndication_links_options' ); ?>
         <?php submit_button(); ?>
       </form>
     </div>
    <?php
 }

?>
