=== Syndication Links ===
Contributors: dshanske
Tags: syndication, indieweb, indiewebcamp, POSSE
Requires at least: 4.1
Tested up to: 4.1
Stable tag: 0.6.0
License: GPLv2 or later

Simple way to link to copies of your post elsewhere.

== Description == 

This plugin adds relational links to various parts of your site.

1. It supports adding rel-syndication links to your posts, indicating where
a syndicated copy is.
2. It adds CSS that will, if the class 'social-icon' is attached to a link,
it will display the appropriate one if available. This allows the setup to be extended very easily. You can use this with a WordPress menu to create some social icons.

== Future Plans ==

1. Add different display options.

== Installation == 

1. Upload the folder `syndication-links' to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Credits ==

1. Justin Tadlock for the initial [Social Icons](http://justintadlock.com/archives/2013/08/14/social-nav-menus-part-2)
design.
2. Ryan Barrett for the initial version of the Bridgy code.

== Changelog ==

Version 0.6.0 - Add hooks and functions to allow additional urls to 
be added
Version 0.5.0 - Clean up the plugin for initial release to WordPress repository.
Version 0.5 - Moved to simplified data structure. Hidden migration function
Version 0.4 - Rewriting using Grunt/SASS for more flexibility

Version 0.3 - Customizable Networks finished. Changed social icons CSS to automatically add only if the class for the list has social-icon in it.

Version 0.2 - Settings Screen rewritten using WordPress Settings API. Going to rewrite with customizable networks and thus option to disable sites you do not syndicate to. Added social icons CSS to automatically add icons to anything in a <LI>

Version 0.1 - Forked from the Semantic Comments plugin. Start of configurable
options.

= Supported POSSE plugins and implementations =

The plugin supports pulling data from plugins that syndicate your content.
Right now, it has experimental support for the Bridgy service.

For anything not built in, it supports a filter 'syn_add_links' to add URLs,
for potential use with any other plugin.


* Bridgy Publish (https://www.brid.gy/about#publish). Requires the
  wordpress-webmention plugin (https://wordpress.org/plugins/webmention/).
  (Credit to Ryan Barrett for the initial code)
