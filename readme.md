# Syndication Links #
**Contributors:** dshanske  
**Tags:** syndication, indieweb, indiewebcamp, POSSE, social media, sharing  
**Requires at least:** 4.7  
**Tested up to:** 4.7.1  
**Stable tag:** 3.1.0  
**License:** GPLv2 or later  

A simple way to link to copies of your content elsewhere.

## Description ##

It's common for websites to post original content and then cross-post them to social media sites like Facebook, Twitter, LinkedIn, Google+, etc. Syndication Links supports adding and displaying permalinks to your posts and pages, indicating where those syndicated copies of your content are on the internet. Permalinks can be added manually in the post UI, but some plugins support automatically adding their links as well.

Proper microformats v2 classes rel-syndication and u-syndication on these links aid in [post discovery](http://indieweb.org/original-post-discovery) for third-party services like [Brid.gy](https://brid.gy/) which leverage them for additional functionality like bringing back replies, comments, and likes from Facebook, Twitter, Instagram, Flickr, and Google+ to the original post's comments section. 

## Installation ##

1. Upload the folder 'syndication-links' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## FAQ ##

### How do I prevent the links from being automatically added to the content? ###

You will have to remove the content filter `remove_filter( 'the_content', array( 'Syn_Config', 'the_content' ) , 20 )` and then you can call `get_syndication_links()` directly in your theme.

### What plugins does Syndication Links support? ###

The plugin supports pulling data for displaying permalinks based on other cross-posting (aka [POSSE](http://indieweb.org/POSSE)) plugins that syndicate your content including:

* [Bridgy Publish](https://wordpress.org/plugins/bridgy-publish) - Simple user interface for [Bridgy](https://brid.gy/about#publishing) publishing
* [Social](https://github.com/crowdfavorite/wp-social) - Syndication Links supports Twitter URL import since version 1.0.0
* [Social Networks Autoposter](https://wordpress.org/plugins/social-networks-auto-poster-facebook-twitter-g/) - Syndication Links supports Twitter, Facebook, and Tumblr since version 1.0.0
* [Medium](https://wordpress.org/plugins/medium/) - Syndication Links supports since version 3.0.5
* [Tumblr Crosspostr](https://wordpress.org/plugins/tumblr-crosspostr) - Supports Syndication Links since version 0.8.1
* [WordPress Crossposter](https://wordpress.org/plugins/wp-crosspost) - Supports Syndication Links since version 0.3.2
* [Diaposter](https://wordpress.org/plugins/diasposter/) - Supports Syndication Links since version 0.1.8

For anything not built in, Syndication Links supports a filter `syn_add_links` to add URLs,
for potential use with any other plugin.

### What social sites does Syndication Links support ###

One can add permalinks to any website. Social sites that are explicitly supported will include the service's icon (if the icon setting is selected), otherwise a generic share icon will be displayed.

Social services with specific icon support include: Amazon, Behance, Blogger, Codepen, Dribble, Dropbox, Eventbrite, Facebook, Flickr, FourSquare, Ghost, Google+, Github, Instagram, LinkedIn, Medium, Path, Pinterest, Pocket, PollDaddy, Reddit, Squarespace, Skype, SoundCloud, Spotify, StumbleUpon, Telegram, Tumblr, Twitch, Twitter, WordPress, and YouTube.

## Settings ##

Settings for the Syndication Links plugin can be found in the main WordPress "Settings" tab in the
admin dashboard, or if the [Indieweb plugin](https://wordpress.org/plugins/indieweb) is installed, under the Indieweb tab. The options provided allow for various ways of presenting the syndication links in posts. Syndication Links by default will add links to the content. You can remove this in your theme or plugin if you wish to call the display function directly.

The settings include the following options as follows with either a set of buttons, a check box (with a check indicating that the feature is "on") or an optional text field:

* **Display Text** -  Offers options to display text only, icons only, icons and text, or no display (hidden icons).
* **Size** - Choice of small, medium, or large size icons.
* **Black Icons** - Checking the box defaults to a "black" social media icon set. 
* **Show on Front Page, Archive Page, and Search Results** - If checked the icons will show on pages other than a single view. If not checked, the icons will be hidden but the links will remain.
* **Text Before Links** - This is the text that appears before the Display Text/Icons (as indicated above). The default text is "Syndicated to:" but can be modified if desired.


**Note**: The particular CSS of your theme may change the display and output of the text and some of the icons.

## Screenshots ##

Examples of Syndication Link output/display at the bottom of a post:

Text with icons in color:

![sl-text-icons-color](https://cloud.githubusercontent.com/assets/5882943/22413329/c765befc-e66b-11e6-9290-aea34ed840ff.PNG)

Icons only in color:

![sl-icons-color](https://cloud.githubusercontent.com/assets/5882943/22413334/cf456b18-e66b-11e6-90ad-a772fe09b9b8.PNG)

Icons only in black and white:

![sl-icons-black and white](https://cloud.githubusercontent.com/assets/5882943/22413338/d4560df6-e66b-11e6-8910-80c70b9b737e.PNG)


## Upgrade Notice ##

### Version 3.0.5 ###

Upgrade to this version moves the location of stored syndication links to match the changes in the Micropub plugin. Recommend
that all users back up prior to upgrade in the event of accidental corruption.

### Version 3.0 ###

Version 3.0 removes the two sets of icon fonts in favor of SVG icons. If this is a concern, do not upgrade at this time. Version 3.0 also removes
rel-me support from this plugin as this support is being built into the Indieweb plugin. Please install that. H-Card support is no longer
supported and will be removed in a future version.

## Credits ##

1. [Meitar Moscovitz](https://github.com/meitar) for fixes and code contributions
2. Justin Tadlock for the initial [Social Icons](http://justintadlock.com/archives/2013/08/14/social-nav-menus-part-2)
design.
3. Jihaisse and Peter Molnar for the [SNAP](https://wordpress.org/plugins/social-networks-auto-poster-facebook-twitter-g/) and [Social](https://github.com/crowdfavorite/wp-social) Import, courtesy of [WordPress Syndication](https://github.com/jihaisse/wordpress-syndication)
4. Automattic for their [Social Logos](https://github.com/Automattic/social-logos/) icon library.
5. [Chris Aldrich](http://boffosocko.com) for many suggestions on improving the display.

## Changelog ##

### Version 3.1.0 ###

* Cleanup of settings attributes using enhancements available in WordPress 4.7
* Individual SVG icons and code to generate an SVG sprite now included in the plugin
* Option to have hidden links now available
* Small Medium and Large CSS files are included by option - generated by sass
* Option to disable links being added to content removed as they can now be hidden. Any theme that wants to call the display function directly will have to remove the content filter

### Version 3.0.5 ###

* Change storage of syndication links in order to match Micropub plugin. Storage is now array
* Remove old property once migrated to new
* Remove JSON REST filter as deprecated
* Add support for the official Medium plugin per request @chrisaldrich

### Version 3.0.4 ###

* Compatibility update
* Add textdomain for language support

### Version 3.0.2 ###
	* Adjust close bracket

### Version 3.0.1 ###
	* Fix text display issue

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
