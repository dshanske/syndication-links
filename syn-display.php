<?php

function get_syndication_links() {
   $facebook =  get_post_meta(get_the_ID(), 'sc_fb_url', true);
   $twitter =  get_post_meta(get_the_ID(), 'sc_tw_url', true);
   $gplus =  get_post_meta(get_the_ID(), 'sc_gplus_url', true);
   $instagram =  get_post_meta(get_the_ID(), 'sc_insta_url', true);
   $display = get_option('webmention_syndication_display_text');
   $synlinks = get_option('webmention_syndication_text');
        if ( ! empty($facebook) )
            {
              $synlinks .=  '<a title="Facebook" class="u-syndication fb" href="' . esc_url($facebook) . '" rel="syndication">';
 	      if ($display == "true" )
		 {
		     $synlinks .= 'Facebook';
		 }
	      $synlinks .= '</a>';
            }

         if ( ! empty($twitter) )
                {
              $synlinks .= '<a title="Twitter" class="u-syndication twitter" href="' . esc_url($twitter) . '" rel="syndication">';
              if ($display == "true" )
                 {
                     $synlinks .= 'Twitter';
                 }

	      $synlinks .= '</a>';
                }
        if ( ! empty($gplus) )
                {
              $synlinks .= '<a title="Google Plus" class="u-syndication gplus" href="' . esc_url($gplus) . '" rel="syndication">';
              if ($display == "true" )
                 {
                     $synlinks .= 'Google Plus';
                 }
	      $synlinks .= '</a>';
                }
        if ( ! empty($instagram) )
                {
              $synlinks .= '<a title="Instagram" class="u-syndication instagram" href="' . esc_url($instagram) . '" rel="syndication">';
              if ($display == "true" )
                 {   
                     $synlinks .= 'Instagram';
                 }
	      $synlinks .= '</a>';
                }
   return $synlinks;
}

function syndication_links_before($meta = "" ) {
   return get_syndication_links() . $meta;
   }

function syndication_links_after($meta = "" ) {
   return $meta . get_syndication_links();
   }


if(get_option('webmention_syndication_content_filter')=="true"){
        if(get_option('webmention_syndication_content-top')=="true"){
                add_filter( 'the_content', 'syndication_links_before', 20 );
            }
        else {
                add_filter( 'the_content', 'syndication_links_after', 20 );
             }
   }

  add_filter('footer_entry_meta', 'syndication_links_before'); 

function get_share_links () {
  
       $share = '<div>Share this on:';
       $share .= '<a class="twitter" href="https://www.twitter.com/intent/tweet?url=' . urlencode(the_permalink()) . '&text=' . urlencode(the_title()) . '" onclick="javascript:window.open(this.href,  \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600\');return false;">Twitter</a>';
       $share .= '<a class="fb" href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode(the_permalink()) .  '" target="_blank" onclick="javascript:window.open(this.href,  \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600\');return false;">Facebook</a>';
       $share .= '<a class="gplus" href="https://plus.google.com/share?url=' . urlencode(the_permalink()) . '" onclick="javascript:window.open(this.href,  \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600\');return false;">Google+</a>';
	$share .= '</div>';
	return $share;
   }
?>
