<?php

// Extracts the Domain Name for a URL for presentation purposes
if (!function_exists('extract_domain_name'))
{
function extract_domain_name($url) {
   $host = parse_url($url, PHP_URL_HOST);
   $host = preg_replace("/^www\./", "", $host);
   return $host;
  }
}

function get_syndication_links() {
   $options = get_option ('syndication_content_options');
   $meta = get_post_meta(get_the_ID(), 'syndication_urls', true );  
   if (empty($meta))
	{
	   $synlinks = '';
 	}
   else{
	   $urls = explode("\n", $meta);
	   $strings = get_syn_network_strings();
	   $synlinks = '<span class="relsyn social-icon"><ul>' . $options['text_before']; 
           foreach( $urls as $url){
			    $domain = extract_domain_name($url);
			    if (array_key_exists($domain, $strings))
				{ 
					$name = $strings[$domain];
				}
			    else {
					$name = $domain;
				 }
              		    $synlinks .=  '<li><a title="' . $name . '" class="u-syndication" href="' . esc_url($url) . '" rel="syndication">'; 
             		    if ($options['just_icons'] == "1" )
                 		{ 
                   		  $synlinks .= $name;
                 		}
           		    $synlinks .= '</a></li>';
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

?>
