== Syndication Links ===
Contributors: dshanske
Tags: syndication, indieweb, POSSE, sharing
Tested up to: 6.6
Stable tag: 4.4.21
License: GPLv2 or later

A simple way to link to copies of your [cross-posted](https://indieweb.org/cross-posting) content in other social networks or websites. Now with posting UI.

== Description ==

It supports addinglinks to your WordPress posts, pages, and comments, indicating where a syndicated copy is, in the form of a text or icon link. You can do this
manually or with supported plugins. Also contains a generic UI for syndicating to other sites through your site or a Micropub Client.

== Screenshots ==

1. Example of Syndication Links metabox with links filled in
2. Example of output display on website

== Installation ==

1. Upload the folder 'syndication-links' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress

Or install through the WordPress Plugin Directory.

== Privacy and Data Storage Information ==

This plugin stores syndication links, which can be attached to any post, page, or comment. They can be added to custom post types by filter.

For webmention initiated comments, this information will be added by parsing the source of the webmention.
It is assumed if you send a webmention, you consent to share this information if it is publicly shared on your page. Our recommendation
is that site owners should take down information on request.

For comments initiated on the site, there is built-in way to add these links. For posts, they are typically added by retrieving information stored by other plugins for display. It is assumed
that by installing this plugin, as its intent is to display these links, that you wish to display them.

== Settings ==

Settings for the Syndication Links plugin can be found in the main WordPress "Settings" tab in the
admin dashboard, or if the [Indieweb plugin](https://wordpress.org/plugins/indieweb) is installed, under the Indieweb tab. The options provided allow for various ways of presenting the syndication links in posts. Syndication Links by default will add links to the content. You can remove this in your plugin if you wish to call the display function directly.

The settings include the following options as follows with either a set of buttons, a check box (with a check indicating that the feature is "on") or an optional text field:

* **Display Text** -  Offers options to display text only, icons only, icons and text, and no display(hidden icons).
* **Size** - Choice of small, medium, or large size icons.
* **Black Icons** - Checking the box defaults to a "black" social media icon set.
* **Show on Front Page, Archive Page, and Search Results** - If checked the icons will show on pages other than a single view. If not checked, the icons will be hidden by the links will remain.
* **Show on Feed** - If checked the icons will show in your RSS feed as well
* **Text Before Links** - This is the text that appears before the Display Text/Icons (as indicated above). The default text is "Syndicated to:" but can be modified if desired.


**Note**: The particular CSS of your theme may change the display and output of the text and some of the icons.

== Supported POSSE plugins and implementations ==

The plugin supports automatically pulling data from plugins that syndicate your content so you don't need to do it manually. For anything not built in, integation is easy with a simple filter for potential use with any other plugin.

* [Social Networks Autoposter](https://wordpress.org/plugins/social-networks-auto-poster-facebook-twitter-g/) - Syndication Links supports Twitter, Facebook, and Tumblr since version 1.0.0
* [Tumblr Crosspostr](https://wordpress.org/plugins/tumblr-crosspostr) - Supports Syndication Links since version 0.8.1
* [WordPress Crossposter](https://wordpress.org/plugins/wp-crosspost) - Supports Syndication Links since version 0.3.2
* [Diasposter](https://wordpress.org/plugins/diasposter/) - Supports Syndication Links since version 0.1.8
* [WP-To_Twitter](https://wordpress.org/plugins/wp-to-twitter/) - Supported by the plugin since version 4.2.3

Using the optional Syndication feature(disabled by default) you can syndicate your posts to:

* [Bridgy](https://brid.gy) - Bridgy is a service that allows you to post to various sites. Signup is required. It currently supports Github, Mastodon and Flickr. The plugin supports Bridgy Publish via Micropub by default.
* [Bridgy Fed](https://fed.brid.gy) - Bridgy Fed is a service that allows you to interact with federated social networks using webmentions.
* [Micro.blog](https://micro.blog) - Micro.blog is a social network and publishing platform for independent microblogs, created by Manton Reece. It uses a custom feed you can add to Micro.blog to support this.
* [Pinboard](https://pinboard.in) - Pinboard is a bookmarking site. The support for this is currently only enabled if you have Post Kinds enabled, due to the difficulty in getting a URL. It will bookmark the URL of any object you are citing.
* Custom Webmention Syndication - Add any site that supports publishing by sending a webmention by configuring it in the settings page

Will be looking to integrate with other plugins to add more options and invite developers to add support if they wish as the interface is simple.
The goal of the interface is not only can you syndicate via Micropub, but in the editor using a simple checkbox.

== Frequently Asked Questions ==

= How do I prevent the links from being automatically added to the content? =

You will have to add the following code to your theme `add_filter( 'syndication_links_display', '__return_false' );` and then you can call get_syndication_links() directly in your theme. You should add
this to the init hook.

* `get_post_syndication_links( $post, $args ) - Returns the HTML for a post.
* `get_comment_syndication_links( $comment, $args ) - Returns the HTML for a comment.
** $args
*** `style` - Defaults to ul
*** `text` - Display text, defaults to settings option
*** `icons` - Display icons, defaults to settings option
*** `container-css` - Class to wrap entire syndication links in
*** `single-css` - Class to wrap a single link in
*** `text-css` - Class to wrap the text before the links in

= How can I look up the original if I have a syndication link? =

If you add `?original-of=` and the URL-encoded URL it will return the post that has that URL stored. As no two posts should have the same two syndication links it will by default only return the first.

If you want to do this with a form, there is a function you can add to your theme called `get_original_of_form()` and a widget that calls this. Like the search form if you have a
originalofform.php in your theme folder the function will return it so you can customize the form.

= What filters are available to modify output? =

* `syn_rewrite_secure( $domains )` - $domains is an array of domain names to rewrite to https if found
* `syn_metabox_types( $screens )` - $screens would be an array of post types to add the Syndication Link metabox to.
* `syn_network_strings( $strings )` - $strings is an array of descriptive text strings by domain name
* `syn_add_links( $urls, $post_ID )` - (Deprecated) $urls is an array of retrieved links from $post_ID
* `get_post_syndication_links( $urls, $post_ID)` - Replaces syn_add_links.
* `get_comment_syndication_links( $urls, $comment_ID` - Filters an array of retrieved comment syndication links.
* `syn_links_display_defaults( $defaults )` - Filter the defaults for displaying Syndication Links
* `syndication_link_checked( $checked, $uid, $post_ID )` - Will check a syndication provider($uid) when loaded. The post ID is passed through to allow more specific targeting.
* `syndication_link_disabled( $disabled, $uid, $post_ID )` - Will disable the checkbox for a syndication provider($uid) when loaded. The post ID is passed through to allow more specific targeting.
* `syn_link_title( $title, $name )` - Allows you to set the title string for Links. Example, pinboard => Pinboard.
* `syn_link_mapping( $icon, $url )` - Allows you to override or set the mapping from URL to icon name.
* `pre_syn_link_icon( $icon, $name )` - Allows you to provide a custom icon. Icons by default are SVG, not URL or filenames.
* `syndication_links_display( true )` - Adds Syndication Links to content display. Set to false if the theme supports this.

= How do I contribute or file bug reports?

Development and bug reports on this plugin is on Github at https://github.com/dshanske/syndication-links

== Upgrade Notice ==

= Version 4.4.0 =

Several core function and filter signatures have changed and may cause some compatability issues.

= Version 4.2.0 =

You will have to set up your enabled providers as this setting has changed

= Version 4.0.4 =

Support for Indienews is no longer bundled with this plugin due spam issues. Indienews is dedicated for Indieweb related news.

= Version 4.0.0 =

This version includes the ability to syndicate to external sites. This is disabled by default

= Version 3.0.5 =

Upgrade to this version moves the location of stored syndication links to match the changes in the Micropub plugin. Recommend
that all users back up prior to upgrade in the event of accidental corruption.

= Version 3.0 =

Version 3.0 removes the two sets of icon fonts in favor of SVG icons. If this is a concern, do not upgrade at this time. Version 3.0 also removes
rel-me support from this plugin as this support is being built into the Indieweb plugin. Please install that.

= Version 3.2.2 =

Removes H-Card Widget as does not fit into this plugin. Moving over to the Indieweb plugin although there is a possibility it may not stay there.

== Credits ==

In no particular order...

1. The [Indieweb](https://indieweb.org) community of users and all users of this plugin
2. [Meitar Moscovitz](https://github.com/meitar) for fixes and code contributions related to support of the plugin in his various plugins.
3. Justin Tadlock for the initial [Social Icons](http://justintadlock.com/archives/2013/08/14/social-nav-menus-part-2) design.
4. Jihaisse and Peter Molnar for the [SNAP](https://wordpress.org/plugins/social-networks-auto-poster-facebook-twitter-g/), courtesy of [WordPress Syndication](https://github.com/jihaisse/wordpress-syndication)
5. [Simple-Icons](https://https://simpleicons.org/) for their icon packs for logos. Simple Icons is licensed under CC0 v1.0 Universal.
6. [Genericons Neue](http://genericons.com/) for their generic icon packs. Genericons Neue is licensed under the GPLv2.
7. [Chris Aldrich](http://stream.boffosocko.com) for many suggestions on improving the display and for screenshots.
8. [PHPCS](https://github.com/squizlabs/PHP_CodeSniffer) is used with the [WordPress](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) and [PHP Compatibility](https://github.com/wimg/PHPCompatibility) Standards in order to ensure compatibility with supported versions of PHP and enact WordPress Coding Standards.
9. GitHub Actions is used to actively test against various PHP versions

== Changelog ==

= Version 4.4.21 ( 2024-09-28 ) =
* Send Micropub content as HTML over plaintext

= Version 4.4.20 ( 2024-05-12 ) =
* Refresh icons
* Add filtering of some HTML tags for publishing
* Update Micropub token text

= Version 4.4.19 ( 2024-03-12 ) =
* One final tweak today

= Version 4.4.18 ( 2024-03-12 ) =
* Introduce custom excerpting function for syndication

= Version 4.4.17 ( 2024-03-12 ) =
* Fix for issue in not properly passing content to Micropub.

= Version 4.4.16 ( 2024-02-25 ) =
* Update Simple Icons to latest
* Remove custom bluesky icon in favor of current one
* Generate minimal CSS file with just the icon colors for Bridgy supported services that can be manually queued
* Add support for 10ups Twitter Autoshare plugin
* Add Syndication provider for Bluesky via Bridgy

= Version 4.4.15 ( 2023-12-05 ) =
* Fix issue with constant

= Version 4.4.14 ( 2023-12-01 ) =
* Update support for WP-To-Twitter plugin, now Xposter.

= Version 4.4.13 ( 2023-10-21 ) =
* Add featured derived from the thumbnail of the post for the micropub syndication mf2.
* Add nostr icon
* Add cloud icon colored blue as placeholder for Bluesky
* Add map from indieweb news to indieweb icon

= Version 4.4.12 ( 2023-10-01 ) =
* Only alow for immediate publishing if the post status is publish
* Refresh dependencies
* Bump minimum version to PHP 7.0

= Version 4.4.11 ( 2023-5-28 ) =
* Disable the option to use the webmention version of Bridgy where Micropub is available and use constant instead.

= Version 4.4.10 ( 2023-05-28 ) =
* Add API Key tab to the settings page
* Minor tweaks to improve the rendering of the settings page

= Version 4.4.9 ( 2023-05-22 ) =
* Refresh Simple Icon set

= Version 4.4.8 ( 2023-05-22 ) =
* Add labels to settings
* Remove Twitter Bridgy support due Bridgy removing it.

= Version 4.4.7 ( 2023-02-18 ) =
* Make posts always enabled for Syndication Links by default to try to address issues people had with same.

= Version 4.4.6 ( 2023-02-10 ) =
* Emergency release to deal with critical error caused by removal of Bridgy settings.

= Version 4.4.5 ( 2023-02-10 ) =
* Add labels to settings
* Fix load issues introduced in previous version
* Migrate Bridgy Specific settings to universal settings by reimplementing them for Micropub.

= Version 4.4.4 ( 2023-01-24 ) =
* Remove support for Reddit which does not have a publishing option
* Per request developer of Bridgy only offer Micropub as a basic action
* Improve disabled and checkbox handling
* Improve WordPress to Micropub conversion and use function from Post Kinds

= Version 4.4.3 ( 2023-01-02 ) =
* Improve response to error and store logs in a metafield.

= Version 4.4.2 ( 2022-12-25 ) =
* Fix a miscalled function
* Adjust the load of options to better display
* Add notation regarding difference between Micropub and Webmention for Bridgy

= Version 4.4.1 ( 2022-12-20 ) =
* Add function to shorten content to title for Twitter Micropub if longer than 280 characters
* Add function to shorten content to title for Mastodon Micropub if longer than 500 characters
* Fix respecting of excerpt settings for Twitter and Mastodon
* Fix issue with saving Syndication Links

= Version 4.4.0 ( 2022-12-19 ) =
* Add filter to disable content addition
* Hide settings if not being used
* Refresh icons
* Decommission Meetup as no longer offered by Bridgy
* New Tabbed Settings Page
* Add options to select which post types will offer syndication and links
* Add option to disable use of wp cron and publish immediately
* Introduce support for Bridgy via Micropub
* Fix issue with Mastodon Autoposter

= Version 4.3.11 ( 2022-05-14 ) =
* Refresh icons and CSS build logic
* Add function to determine mastodon URLs using the mastodon fields in user profiles

= Version 4.3.10 ( 2022-02-13 ) =
* Refresh icons and other dependencies
* Save syndication on save, and publish on publish, whereas previously it would only save if you published.

= Version 4.3.9 ( 2022-01-11 ) =
* Switch empty links to data tags.
* Switch to new query https://github.com/microdotblog/issues/issues/81 for microdotblog to save on multiple polling requests.

= Version 4.3.8 ( 2022-01-08 ) =
* Refresh icons
* Fix issue where hidden links were still taking up space.

= Version 4.3.7 ( 2021-11-02 ) =
* Refresh icons

= Version 4.3.6 ( 2021-07-24 ) =
* Style change

= Version 4.3.4 ( 2021-06-12 ) =
* Refresh icons
* Switch from id to class for syndication links wrapper

= Version 4.3.3 ( 2021-02-28 ) =
* Fix issue with schema.

= Version 4.3.2 ( 2021-02-28 ) =
* Introduce `pre_syndication_links_webmention` hook to allow you to clear cache before sending webmention.
* Fix various PHP errors.

= Version 4.3.1 ( 2020-12-12 ) =
* Introduce `syn_link_name` filter that allows you to set the name string.
* Introduce `pre_syn_link_icon` filter to allow short circuiting the domain mapping and providing an SVG of your choice.
* Save last return in post meta for diagnostic purposes.

= Version 4.3.0 ( 2020-10-13 ) =
* Refresh icons
* Add optional Pinboard POSSE that is only enabled with Post Kinds

= Version 4.2.6 ( 2020-08-15 ) =
* Update dependencies
* Adjust publish hook to try to improve success

= Version 4.2.5 ( 2020-08-03 ) =
* Change time delay syndication code to behave more like the ping/webmention code

= Version 4.2.4 ( 2020-08-01 ) =
* Change how to decide to postpone syndication if post is scheduled as publish status can be in the future
* Add ?syndication query variable to allow for filtering archives by syndication link type

= Version 4.2.3 ( 2020-06-28 ) =
* Add support for Reddit Via Bridgy
* Dependency and icon updates
* Add support for importing links from WP-To-Twitter ( props @tw2113)
* Removal of master branch in favor of trunk branch.

= Version 4.2.2 ( 2020-03-26 ) =
* Add support for Meetup via Bridgy, props @ngm
* Dependency and icon updates
* Code organization cleanup, props @tw2113 and @asuh

= Version 4.2.1 ( 2019-12-21 ) =
* Fix escaping issue
* Improve text for micro.blog syndication
* Add filter to allow checkboxes to be disabled or checked or both

= Version 4.2.0 ( 2019-12-15 ) =
* Add support for Bridgy Mastodon ( props @CharlieRoseMarie )
* Check for empty values ( props @glueckpress )
* Enhance Syndication Metabox ( props @glueckpress )
* Refactor domain to icon mapping into separate class already there to map custom domains
* Fix issue where PHPCS was rewriting wordpress to WordPress
* Switch provider disable functionality to enable functionality
* Redo domain to icon mapping logic
* Add micro.blog POSSE support


= Version 4.1.4 ( 2019-11-18 ) =
* Refresh icons
* Fix icon association to news.indieweb.org


= Version 4.1.3 ( 2019-07-01 ) =
* Fix issue with providers not loading because of name change in webmention plugin
* Refresh icons

= Version 4.1.2 ( 2019-05-12 ) =
* Update icon size to be relative
* Reschedule syndication if post status is in future
* Trigger edit post hook if syndication links are added to try to invalidate cache

= Version 4.1.1 ( 2019-04-13 ) =
* Update icons
* Fix minor typo
* Attempt at cache busting

= Version 4.1.0 ( 2019-02-09 ) =
* Switch to inline SVG over SVG sprites to reduce load size
* Add ability to configure arbitrary webmention POSSE providers on the settings page

= Version 4.0.5 ( 2019-01-05 ) =
* Fix minor bug introduced in customizer by original of widget

= Version 4.0.4 ( 2018-12-29 ) =
* Add setting to use the excerpt if set for Bridgy Publish to Twitter
* Indienews(news.indieweb.org) will no longer be a bundled provider per request due spam issues
* When automatically added to content wrap the links in an element for styling
* Add function `get_original_of_form()` which creates a search for for the original of query which looks up posts by their syndication link
* Added widget that calls new original of form function

= Version 4.0.3 ( 2018-12-08 ) =
* Checks for 5.0 compatibility.

= Version 4.0.2 ( 2018-11-05 ) =
* Fix PHP notice about incorrect setting
* Restore Bridgy global settings options that were in the Bridgy plugin to disable the link back to the post and ignore whitespace

= Version 4.0.1 ( 2018-11-03 ) =
* Fix issue with settings caused in previous version
* Add base Bridgy class to store settings
* Fix issue with display

= Version 4.0.0 ( 2018-11-01 ) =
* Add support for syndicating posts using an interface to any arbitrary provider
* include support for syndicating from Micropub to any arbitrary provider

= Version 3.4.1 ( 2018-05-06 ) =
* Refresh Simple Icons
* Remove internationalization of icon names
* Update development environment
* Add Syndication data to JSONFeed
* Do not add syndication information to the content of a jsonfeed
* Add privacy policy and export data support for WordPress 4.9.6
* Add privacy notice to readme

= Version 3.4.0 ( 2018-03-03 ) =
* Updated Simple Icons to latest version
* Switched Genericons Neue to a submodule as the svg version was not distributed in the npm package
* Added support for Mastodon Auto Post per request. Unable to test due not using same
* Added support for Keyring Social Importer. Unable to test due not using same
* Simplified saving of metadata
* Changed Metabox to a dynamically generated array

= Version 3.3.2 ( 2018-02-03 ) =
* Updated Simple Icons to latest version

= Version 3.3.1 ( 2018-01-04 ) =
* Updated Simple Icons to latest version
* Support for pulling data out for SNAP out of Post Meta without dependence on SNAP classes

= Version 3.3.0 ( 2017-12-?? ) =
* Switched to Simple Icons as larger, updated, and maintained more frequently than Automattic's Social Icons repo
* Added in the Genericon Neue pack for when there is no logo for a site with a series of generic icons
* SVG Sprite only will be distributed instead of individual SVG files
* Icon colors automatically generated from Simple Icons repository
* Icon names automatically generated from Simple Icon repository
* New code to try and find an icon without hard coding the domain to icon relationship by trying to find the icon name inside the domain string.
* Development tools now configured for bringing in PHPCS Coding Standards and generating new files
* Screenshots!
* License information included for the plugin as well as dependencies
* Again, automation automation automation

= Version 3.2.4 ( 2017-11-23 ) =
* Changelog will now note a release date
* Added/redid colors for many links

= Version 3.2.3 =
* Remove Social Support as Plugin is no longer listed in WordPress repository
* Add additional syndication icons
* Fix textdomain issues
* Add PHP Compatibility tests and textdomain tests
* PHPCS Improvements
* Add setting for disabling links in feed

= Version 3.2.2 =
* Remove H-Card Widget

= Version 3.2.1 =
* Break add `get_syndication_links function` into smaller pieces ( props @Ruxton )
* Adds `get_syndication_links_elements` which returns array of anchor tags
* Adds `get_syndication_links_display_defaults` to return default options
* Adds `get_syndication_links_text_before` to return textbefore on it's own

= Version 3.2.0 =
* Add support for comment syndication links
* Add CSS for styling text before

= Version 3.1.1 =

* Fix documentation re priority of content filter
* Remove empty check as interfering with filter
* Add uniqueness check after filter

= Version 3.1.0 =

* Cleanup of settings attributes using enhancements available in WordPress 4.7
* Individual SVG icons and code to generate an SVG sprite now included in the plugin
* Option to have hidden links now available
* Small Medium and Large CSS files are included by option - generated by sass
* Option to disable links being added to content removed as they can now be hidden. Any theme that wants to call the display function directly will have to remove the content filter
* Add arguments to `get_syndication_links` to allow for customized presentation
* Adding `?original-of=url` with url being the syndication URL will return the original entry.

= Version 3.0.5 =

* Change storage of syndication links in order to match Micropub plugin. Storage is now array
* Remove old property once migrated to new
* Remove JSON REST filter as deprecated
* Add support for the official Medium plugin per request @chrisaldrich

= Version 3.0.4 =

* Compatibility update
* Add textdomain for language support

= Version 3.0.2 =
	* Adjust close bracket

= Version 3.0.1 =
	* Fix text display issue

= Version 3.0.0 =
	* Remove icon fonts in favor of SVG
	* Remove rel-me support to move to implementation in Indieweb plugin
	* Remove h-card support to move to implementation in Indieweb plugin (it wasn't very good anyway)
	* Introduce new get_syndication_data function to abstract out storage

= Version 2.1.0 =
	* Removed user meta code

= Version 2.0.3 =
	* Minor Tweaks and Cleanup

= Version 2.0.2 =
	* CSS fixes
	* Version number added to CSS import to avoid caching on update

= Version 2.0.1 =
	* Misc. Bug Fixes
	* Bridgy Publish Support removed - new Bridgy plugin will handle that

= Version 2.0.0 =
	* Rewritten to remove global scoping
	* Option to add URLs to the head of the home page for rel-me auth
	* Option to add URLs as a widget for rel-me auth

= Version 1.0.3 =
	* Security Fix. Nothing new
= Version 1.0.2 =
	* Refinements
	* Add support for pages and a filter to add additional content types
= Version 1.0.0 =
	* Refinements
	* Addition of h-card widget.
	* Improvements to hooks
	* Addition of automatic information from SNAP and Social(courtesy WordPress Syndication).
	* Now supports second font choice and choice of color or black.
= Version 0.6.0 =
	* Add hooks and functions to allow additional urls to  be added
= Version 0.5.0 =
	* Clean up the plugin for initial release to WordPress repository.
= Version 0.5 =
	* Moved to simplified data structure.
	* Hidden migration function
= Version 0.4 =
	* Rewriting using Grunt/SASS for more flexibility
= Version 0.3 =
	* Customizable Networks finished.
	* Changed social icons CSS to automatically add only if the class for the list has social-icon in it.
= Version 0.2 =
	* Settings Screen rewritten using WordPress Settings API.
	* Going to rewrite with customizable networks and thus option to disable sites you do not syndicate to.
	* Added social icons CSS to automatically add icons to anything in a <LI>
= Version 0.1 =
	* Forked from the Semantic Comments plugin. Start of configurable options.
