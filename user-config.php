<?php

function hcard_contactmethods() {
  if ( !isset( $contactmethods['tel'] ) )
    $contactmethods['tel'] = 'Telephone Number';
  if ( !isset( $contactmethods['locality'] ) )
    $contactmethods['locality'] = 'City/Town/Village';
  if ( !isset( $contactmethods['region'] ) )
    $contactmethods['region'] = 'State/County/Province';
  if ( !isset( $contactmethods['country-name'] ) )
    $contactmethods['country-name'] = 'Country';
  if ( !isset( $contactmethods['twitter'] ) )
    $contactmethods['twitter'] = 'Twitter username (without @)';
  if ( !isset( $contactmethods['googleplus'] ) )
    $contactmethods['googleplus'] = 'Google+';
  if ( !isset( $contactmethods['facebook'] ) )
    $contactmethods['facebook'] = 'Facebook profile URL';
  if ( !isset( $contactmethods['github'] ) )
    $contactmethods['github'] = 'Github';
  if ( !isset( $contactmethods['instagram'] ) )
    $contactmethods['instagram'] = 'Instagram';
  return $contactmethods;
}

add_filter('user_contactmethods', 'hcard_contactmethods');

