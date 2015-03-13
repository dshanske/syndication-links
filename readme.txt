=== Syndication Links ===
Contributors: dshanske
Tags: syndication, indieweb, indiewebcamp, POSSE
Requires at least: 4.1
Tested up to: 4.1
Stable tag: 1.0.0
License: GPLv2 or later

Simple way to link to copies of your post or presence elsewhere.

== Description == 

This plugin adds relational links to various parts of your site.

1. It supports adding rel-syndication links to your posts, indicating where
a syndicated copy is.
2. It adds CSS that will, if the class 'social-icon' is attached to a link in
a list, it will display the appropriate one if available. This allows the setup to be extended very easily. You can use this with a WordPress menu to create some social icons.
3. Offers two icon sets: [Genericons](http://genericons.com) or [Font Awesome](http://fortawesome.github.io/Font-Awesome/icons/)
3. Adds an h-card/vcard widget for a specific site author. (Under Development)

== Future Plans ==

3. Style the h-card/vcard widget with better design and optional rel-me/social icons.
4. Adds a widget for sitewide rel-me social icons


== Installation == 

1. Upload the folder `syndication-links' to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Credits ==

1. [Meitar Moscovitz](https://github.com/meitar) for fixes and code contributions
2. Justin Tadlock for the initial [Social Icons](http://justintadlock.com/archives/2013/08/14/social-nav-menus-part-2)
design.
3. Ryan Barrett for the initial version of the Bridgy code.
4. Jihaise and Peter Molnar for the SNAP and Social Import, courtesy of [WordPress Syndication](https://github.com/jihaisse/wordpress-syndication)


== Changelog ==

Version 1.0.0 - Refinements - Addition of h-card widget. Improvements to hooks, addition of automatic information from SNAP and Social(courtesy WordPress Syndication). Now supports second font choice and choice of color or black.
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
* [Social](https://wordpress.org/plugins/social/) - Supports Twitter URL import
* [Social Networks Autoposter](https://wordpress.org/plugins/social-networks-auto-poster-facebook-twitter-g/) - Supports Twitter, Facebook, and Tumblr
* [Tumblr Crosspostr](https://wordpress.org/plugins/tumblr-crosspostr/) - Supports Syndication Links since version 0.8.1
* [WordPress Crossposter](https://wordpress.org/plugins/wp-crosspost/) - Supports Syndication Links since version 0.3.2
* [Diaposter](https://wordpress.org/plugins/diasposter/) - Supports Syndication Links since version 0.1.8
