=== Communication and Syndication Links ==
Contributors: dshanske
Tags: syndication, indieweb, indiewebcamp, POSSE
Requires at least: 4.1
Tested up to: 4.1
Stable tag: 0.5
License: GPLv2 or later

Allows you to link to copies of your post elsewhere. Also offers support for linking to your presence on other sites and methods of communication.

== Description == 

This plugin adds relational links to various parts of your site.

1. It supports adding rel-syndication links to your posts, indicating where
a syndicated copy is.
2. It supports a menu of links to other services to indicate a rel-me relationship

== Future Plans ==

1. Add support for hiding certain communications links for logged-out users.
2. Add different display options. 

== Installation == 

1. Upload the folder `syndication-links' to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

Version 0.5 - Moved to simplified data structure. Hidden migration function
Version 0.4 - Rewriting using Grunt/SASS for more flexibility

Version 0.3 - Customizable Networks finished. Changed social icons CSS to automatically add only if the class for the list has social-icon in it.

Version 0.2 - Settings Screen rewritten using WordPress Settings API. Going to rewrite with customizable networks and thus option to disable sites you do not syndicate to. Added social icons CSS to automatically add icons to anything in a <LI>

Version 0.1 - Forked from the Semantic Comments plugin. Start of configurable
options.

= Supported POSSE plugins and implementations =

* Social plugin is fully supported (http://wordpress.org/plugins/social/)
* partial ( Facebook, Twitter & Tumblr only ) support for Social Networks Auto Poster {SNAP}
* Bridgy Publish (https://www.brid.gy/about#publish). Requires the
  wordpress-webmention plugin (https://wordpress.org/plugins/webmention/).
