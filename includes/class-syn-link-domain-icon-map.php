<?php
class Syn_Link_Domain_Icon_Map {

  private static $map = array(
    'play.google.com'    => 'googleplay',
    'plus.google.com'    => 'googleplus',
    'indieweb.xyz'       => 'info',
    'getpocket.com'      => 'pocket',
    'flip.it'            => 'flipboard',
    'micro.blog'         => 'microblog',
    'wordpress.org'      => 'wordpress'
  );

  public static function getName($url) {
    $parsed = parse_url($url);
    if(false !== $parsed) {
      $host = $parsed['host'];
      if(array_key_exists($host,$map)) {
        return $map[$host];
      } else {
        return false;
      }
    }

    return false;
  }
}
?>
