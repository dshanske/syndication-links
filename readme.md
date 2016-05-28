# Syndication Links #
**Contributors:** dshanske  
**Tags:** syndication, indieweb, indiewebcamp, POSSE  
**Requires at least:** 4.1  
**Tested up to:** 4.5  
**Stable tag:** 3.0.0  
**License:** GPLv2 or later  

Simple way to link to copies of your post elsewhere.

## Description ##

It supports adding rel-syndication/u-syndication links to your posts and pages, indicating where a syndicated copy is.

## Installation ##

1. Upload the folder `syndication-links` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## Upgrade Notice ##

### Version 3.0 ###

Version 3.0 removes the two sets of icon fonts in favor of SVG icons. If this is a concern, do not upgrade at this time. Version 3.0 also removes
rel-me and h-card support from this plugin as this support is being built into the Indieweb plugin.

## Credits ##

1. [Meitar Moscovitz](https://github.com/meitar) for fixes and code contributions
2. Justin Tadlock for the initial [Social Icons](http://justintadlock.com/archives/2013/08/14/social-nav-menus-part-2)
design.
3. Jihaisse and Peter Molnar for the SNAP and Social Import, courtesy of [WordPress Syndication](https://github.com/jihaisse/wordpress-syndication)
4. Automattic for their [Social Logos](https://github.com/Automattic/social-logos/) icon library.

## Changelog ##

### Version 3.0.0 ###
	* Remove icon fonts in favor of SVG
	* Remove rel-me support to move to implementation in Indieweb plugin
	* Remove h-card support to move to implementation in Indieweb plugin (it wasn't very good anyway)
	* Introduce new get_syndication_data function to abstract out storage

### Version 2.1.0 ###
	* Removed user meta code

### Version 2.0.3 ###
	* Minor Tweaks and Cleanup

### Version 2.0.2 ###
	* CSS fixes
	* Version number added to CSS import to avoid caching on update

### Version 2.0.1 ###
	* Misc. Bug Fixes
	* Bridgy Publish Support removed - new Bridgy plugin will handle that

### Version 2.0.0 ###
	* Rewritten to remove global scoping
	* Option to add URLs to the head of the home page for rel-me auth
	* Option to add URLs as a widget for rel-me auth

### Version 1.0.3 ###
	* Security Fix. Nothing new
### Version 1.0.2 ###
	* Refinements
	* Add support for pages and a filter to add additional content types
### Version 1.0.0 ###
	* Refinements
	* Addition of h-card widget. 
	* Improvements to hooks
	* Addition of automatic information from SNAP and Social(courtesy WordPress Syndication). 
	* Now supports second font choice and choice of color or black.
### Version 0.6.0 ###
	* Add hooks and functions to allow additional urls to  be added
### Version 0.5.0 ###
	* Clean up the plugin for initial release to WordPress repository.
### Version 0.5 ###
	* Moved to simplified data structure. 
	* Hidden migration function
### Version 0.4 ###
	* Rewriting using Grunt/SASS for more flexibility
### Version 0.3 ###
	* Customizable Networks finished. 
	* Changed social icons CSS to automatically add only if the class for the list has social-icon in it.
### Version 0.2 ###
	* Settings Screen rewritten using WordPress Settings API. 
	* Going to rewrite with customizable networks and thus option to disable sites you do not syndicate to. 
	* Added social icons CSS to automatically add icons to anything in a <LI>
### Version 0.1 ###
	* Forked from the Semantic Comments plugin. Start of configurable options.

### Supported POSSE plugins and implementations ###

The plugin supports pulling data from plugins that syndicate your content.

For anything not built in, it supports a filter 'syn_add_links' to add URLs,
for potential use with any other plugin.


* [Bridgy Publish](https://wordpress.org/plugins/bridgy-publish) - Simple interface for Bridgy Publish
* [Social](https://wordpress.org/plugins/social/) - Supports Twitter URL import
* [Social Networks Autoposter](https://wordpress.org/plugins/social-networks-auto-poster-facebook-twitter-g/) - Supports Twitter, Facebook, and Tumblr
* [Tumblr Crosspostr](https://wordpress.org/plugins/tumblr-crosspostr) - Supports Syndication Links since version 0.8.1
* [WordPress Crossposter](https://wordpress.org/plugins/wp-crosspost) - Supports Syndication Links since version 0.3.2
* [Diaposter](https://wordpress.org/plugins/diasposter/) - Supports Syndication Links since version 0.1.8
