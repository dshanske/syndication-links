<?php

function get_syndication_links() {
   $options = get_option ('syndication_content_options');
   $network = get_option('syndication_network_options');
   $meta = get_post_meta(get_the_ID(), 'synlinks', true );  
   if (empty($meta))
	{
	   $synlinks = '';
 	}
   else{
	   $synlinks = '<span class="relsyn social-icon"><ul>' . $options['text_before']; 
           foreach( $network as $key => $value){
               if ($value==1)
                  {
                     if ( ! empty($meta[$key]) )
            	         {
              		    $synlinks .=  '<li><a title="' . $key . '" class="u-syndication" href="' . esc_url($meta[$key]) . '" rel="syndication">'; 
             		    if ($options['just_icons'] == "1" )
                 		{ 
                   		  $synlinks .= $key;
                 		}
           		    $synlinks .= '</a></li>';
                         }

                  }
            }
          $synlinks .= '</ul></span>';
	}

   return $synlinks;
}

function syndication_links_before($meta = "" ) {
   return get_syndication_links() . $meta;
   }

function syndication_links_after($meta = "" ) {
   return $meta . get_syndication_links();
   }

$option = get_option('syndication_content_options');
if($option['the_content']=="1"){
         add_filter( 'the_content', 'syndication_links_after', 20 );
   }

function get_share_links () {
  
       $share = '<div>Share this on:';
       $share .= '<a class="twitter" href="https://www.twitter.com/intent/tweet?url=' . urlencode(the_permalink()) . '&text=' . urlencode(the_title()) . '" onclick="javascript:window.open(this.href,  \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600\');return false;">Twitter</a>';
       $share .= '<a class="fb" href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode(the_permalink()) .  '" target="_blank" onclick="javascript:window.open(this.href,  \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600\');return false;">Facebook</a>';
       $share .= '<a class="gplus" href="https://plus.google.com/share?url=' . urlencode(the_permalink()) . '" onclick="javascript:window.open(this.href,  \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600\');return false;">Google+</a>';
	$share .= '</div>';
	return $share;
   } 
?>
