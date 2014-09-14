<?php

function webmention_syndication_options()
{
?>
    <div class="wrap">
        <h2>Webmention Syndication</h2>
	<p>Adds optional syndication links for various sites. Syndication is the act of posting your content on other sites.</p>
	<hr />
        <form method="post" action="options.php">
            <?php wp_nonce_field('update-options') ?>
            <p><strong>Filter Content:</strong> - Add Syndication Links inside the content block<br />
                <input type="radio" name="webmention_syndication_content_filter" size="45" value="false"  <?php echo get_option('webmention_syndication_content_filter')!="true"?'checked="checked"':''; ?>/> Disabled<br>
				<input type="radio" name="webmention_syndication_content_filter" size="45" value="true" <?php echo get_option('webmention_syndication_content_filter')=="true"?'checked="checked"':''; ?>/> Enabled
            </p>

            <p><strong>Content Filter at Top or Bottom of Content:</strong> - If the content filter is enabled, should it be at the top of bottom of the content?<br />
                <input type="radio" name="webmention_syndication_content-top" size="45" value="false"  <?php echo get_option('webmention_syndication_content-top')!="true"?'checked="checked"':''; ?>/> Bottom<br>
                <input type="radio" name="webmention_syndication_content-top" size="45" value="true" <?php echo get_option('webmention_syndication_content-top')=="true"?'checked="checked"':''; ?>/> Top
            </p>

            <p><strong>Display Text:</strong> - Display text or just icons? <br />
                <input type="radio" name="webmention_syndication_display_text" size="45" value="true"  <?php echo get_option('webmention_syndication_display_text')=="true"?'checked="checked"':''; ?>/> Display Text<br>
                <input type="radio" name="webmention_syndication_display_text" size="45" value="false" <?php echo get_option('webmention_syndication_display_text')!="true"?'checked="checked"':''; ?>/> Do Not Display Text
            </p>


            <p><input type="submit" name="Submit" value="Store Options" /></p>
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="webmention_syndication_content_filter, webmention_syndication_content-top, webmention_syndication_display_text" />
        </form>
    </div>
<?php
}

function add_webmention_syndication_options_to_menu(){
	add_options_page( '', 'Webmention Syndication', 'manage_options', 'webmention_syndication', 'webmention_syndication_options');
}

add_action('admin_menu', 'add_webmention_syndication_options_to_menu');

?>
