=== Syndication Links ===
Contributors: dshanske
Tags: syndication, indieweb, POSSE, sharing
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 4.5.3
License: GPLv2 or later

Link to copies of your [cross-posted](https://indieweb.org/cross-posting) content in other social networks or websites.

== Description ==

Syndication Links is a WordPress (and ClassicPress) plugin, available from the [WordPress.org plugin directory](https://wordpress.org/plugins/syndication-links/).

It supports adding links to your WordPress posts, pages, and comments, indicating where a syndicated copy is, in the form of a text or icon link. You can do this
manually or with supported plugins. Also contains a generic UI for syndicating to other sites through your site or a Micropub Client.

== Screenshots ==

1. The Syndication Links box in the post editor, with links filled in
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

= How does a theme place the links itself? =

Declare support in the theme's `functions.php`, on `after_setup_theme` like other theme features:

`add_theme_support( 'syndication-links' );`

The plugin then stops adding the links after the post content and after each comment, and the settings page says the theme places them. To take over only one of them, list it: `add_theme_support( 'syndication-links', array( 'content' ) )` or `array( 'comments' )`.

Then output the links where the theme wants them with `get_post_syndication_links()` and `get_comment_syndication_links()`. The display settings (icons or text, size, text before the links) still apply.

The older filters still work, and are now checked when the links would be added, so it doesn't matter when they're added: `add_filter( 'syndication_links_display', '__return_false' );` for the post content, and `syndication_links_comment_display` for comments.

* `get_post_syndication_links( $post, $args ) - Returns the HTML for a post.
* `get_comment_syndication_links( $comment, $args ) - Returns the HTML for a comment.
** $args
*** `style` - Defaults to ul
*** `text` - Display text, defaults to settings option
*** `icons` - Display icons, defaults to settings option
*** `container-css` - Class to wrap entire syndication links in
*** `single-css` - Class to wrap a single link in
*** `text-css` - Class to wrap the text before the links in
*** `targets` - Whether to add Bridgy targets as empty links, which Bridgy looks for on the post's page. Defaults to true

= Which template tags can a theme use? =

* `the_syndication_links( $post, $args )` and `the_comment_syndication_links( $comment, $args )` display the links. `get_post_syndication_links()` and `get_comment_syndication_links()` return them instead.
* `syndication_links_display_mode()` returns how links are shown on the current page: `icons`, `text`, `iconstext` or `hidden`.

The display settings apply to all of them. A theme's arguments change how visible links look, but when the site hides the links, they stay hidden (as invisible microformats markup).

= How do I show the links somewhere else, such as a theme's entry footer? =

Many themes have their own hooks, such as Genesis's `genesis_entry_footer`. To show the links there instead of after the content, add this to a child theme's `functions.php`, or to a small plugin:

`add_action( 'after_setup_theme', function () { add_theme_support( 'syndication-links', array( 'content' ) ); } );`
`add_action( 'genesis_entry_footer', function () { the_syndication_links(); } );`

The first line stops the plugin adding the links after the content. The second shows them on the theme's hook; use whichever hook your theme offers. `the_syndication_links()` is new in 5.0.0. Keep the links inside the post's `h-entry` markup, so they're still read as its syndication links.

= How can I look up the original if I have a syndication link? =

If you add `?original-of=` and the URL-encoded URL it will return the post that has that URL stored. As no two posts should have the same two syndication links it will by default only return the first.

If you want to do this with a form, there is a function you can add to your theme called `get_original_of_form()` and a widget that calls this. Like the search form if you have a
originalofform.php in your theme folder the function will return it so you can customize the form.

= How do I find the posts I syndicated to a site? =

Add `?syndication=` and a domain, such as `?syndication=mastodon.social`, to your site's address. It lists the posts with a syndication link to that domain. A full URL works too: its domain is used.

It combines with WordPress's other query parameters, so `?syndication=mastodon.social&s=widgets` searches those posts for "widgets".

= Why does a link show a generic icon instead of the site's? =

The plugin bundles icons for about 80 services: the common social networks, the Fediverse, the sites it can syndicate to, and the networks other cross-posting plugins post to, plus icons for retired services such as Google+ that old posts still link to. A link to any other site shows the generic website icon.

To ask for a service's icon to be included, [open an issue](https://github.com/dshanske/syndication-links/issues) with its domain and, if it has one, its [Simple Icons](https://simpleicons.org/) name.

To add an icon on your own site, put an SVG named after the domain (`example.svg` for `example.com`) in a directory and add it to the search list:

`add_filter( 'syn_icon_file_dirs', function ( $dirs ) { $dirs[] = get_stylesheet_directory() . '/icons'; return $dirs; } );`

Use the `syn_link_title` filter for its name and `syn_link_icon_color` for its colour, or `syn_link_mapping` to point a domain at an icon with a different name.

= Why do links to my Mastodon server show a generic icon? =

There are too many Mastodon servers to recognize them all, so the plugin uses the Mastodon icon for:

* the servers in your users' profiles: the `mastodon` user field, which the [IndieWeb plugin](https://wordpress.org/plugins/indieweb/) adds as "Mastodon Server (URL)". Enter your profile URL, such as `https://masto.ai/@username`.
* the server set in XPoster or Mastodon Autopost, if you use them.
* servers whose name is "mastodon", such as `mastodon.social` or `mastodon.online`, which match the icon by name.

You can also use the `syn_link_mapping` filter to return `mastodon` for any URL.

= What filters are available to modify output? =

* `syn_rewrite_secure( $domains )` - $domains is an array of domain names to rewrite to https if found
* `syn_metabox_types( $screens )` - $screens would be an array of post types to add the Syndication Link metabox to.
* `syn_add_links( $urls, $post_ID )` - (Deprecated) $urls is an array of retrieved links from $post_ID
* `get_post_syndication_links( $urls, $post_ID)` - Replaces syn_add_links.
* `get_comment_syndication_links( $urls, $comment_ID` - Filters an array of retrieved comment syndication links.
* `syn_links_display_defaults( $defaults )` - Filter the defaults for displaying Syndication Links
* `syndication_link_checked( $checked, $uid, $post_ID )` - Will check a syndication provider($uid) when loaded. The post ID is passed through to allow more specific targeting.
* `syndication_link_disabled( $disabled, $uid, $post_ID )` - Will disable the checkbox for a syndication provider($uid) when loaded. The post ID is passed through to allow more specific targeting.
* `syn_link_title( $title, $name )` - Allows you to set the title string for Links. Example, pinboard => Pinboard.
* `syn_link_mapping( $icon, $url )` - Allows you to override or set the mapping from URL to icon name.
* `syn_link_rel( $rels, $url )` - The rel values of a syndication link: `nofollow`, plus `syndication` on a single post's page. Remove `nofollow` to let search engines follow the links.
* `pre_syn_link_icon( $icon, $name )` - Allows you to provide a custom icon. Icons by default are SVG, not URL or filenames.
* `syn_link_icon_color( $color, $name )` - An icon's brand colour as a hex value such as `#1185FE`, or null for none (the icon then takes the link's colour). Use it to colour an icon you've added. Output as the `--syn-icon-color` CSS custom property.
* `syndication_links_display( true )` - Adds Syndication Links to content display. Set to false if the theme supports this, or declare `add_theme_support( 'syndication-links' )`.
* `syndication_links_comment_display( true )` - Adds Syndication Links after each comment. Set to false if the theme places them.
* `syndication_links_feed_display( true, $feed_type, $post_id )` - Whether a feed shows the post's links: after the content in RSS and Atom, and as `syndication` in JSON Feed. The Micro.blog provider turns them off in its own feed.
* `syndication_links_delete_copies( $copies, $post )` - The copies deleted when a post is trashed or deleted: their URLs, keyed by provider UID. Return an empty array to keep them.

= How do I contribute or file bug reports? =

Development and bug reports on this plugin is on Github at https://github.com/dshanske/syndication-links

== Upgrade Notice ==

= Version 5.0.0 =

Requires PHP 7.4 and WordPress 6.2 or later, or ClassicPress. Private posts are never syndicated, and trashing a post deletes its Bridgy copies. HTML from the syn_link_title filter is escaped. Medium links are shown only once imported, from the new Import tab. Only about 80 brand icons are bundled.

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
8. [PHPCS](https://github.com/PHPCSStandards/PHP_CodeSniffer) is used with the [WordPress](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) and [PHP Compatibility](https://github.com/wimg/PHPCompatibility) Standards in order to ensure compatibility with supported versions of PHP and enact WordPress Coding Standards.
9. GitHub Actions is used to actively test against various PHP versions

== Changelog ==

= Version 5.0.0 ( unreleased ) =
* Requires PHP 7.4 and WordPress 6.2 or later, or the latest ClassicPress. On older versions, WordPress won't activate or update the plugin.
* Security: output in the link markup, the metabox and the Original Of widget is escaped, submitted links are sanitized, and saving a comment's links checks the user can edit it.
* Behaviour change: a `syn_link_title` filter that returns HTML now has it escaped. Text before the links still allows formatting such as `<em>`.
* Behaviour change: private posts are never syndicated. Drafts and pending posts are queued and sent when published, and future-dated posts are sent when they're published, not straight away as well.
* Posts of every syndication post type, not only `post`, are sent when they're queued and published (props @kupietools).
* New Import tab and `wp syndication-links import` command: copy the links recorded by other cross-posting plugins (SNAP, Mastodon Autopost, XPoster, Autopost for X, Keyring, Medium) into the post's own syndication links. Once imported, the other plugin can be removed without losing the links. Other plugins can add import sources with `register_syndication_link_source()`.
* Medium links are no longer shown from the Medium plugin, which is closed. Import them to keep them.
* Jetpack Social (Publicize): the posts it shared to are shown with the post's links, and can be imported.
* Themes can place the links themselves by declaring `add_theme_support( 'syndication-links' )`, for the post content, comments or both. The `syndication_links_display` filter now works whenever it's added, and the new `syndication_links_comment_display` filter does the same for comments.
* New setting, off by default: store the syndication links of comments received by webmention, from the reply's microformats, and show them with the comment. Needs the Webmention plugin.
* XPoster: links to its Mastodon and Bluesky posts are added, and its X links are also shown for posts saved before they were recorded.
* Fixed the Mastodon Autopost and Autopost for X integrations.
* Bridgy targets are output as empty links, as Bridgy's docs describe, not as syndicated copies.
* Deleting a link in the Syndication Links box now sticks. Links other plugins add are listed read-only instead of being saved with the post, and adding a link no longer stores them.
* Saving from the editor applies only the box's own changes, so links added by syndication in the meantime are kept. Before, publishing from the block editor could wipe them.
* With a webmention provider such as Bridgy Fed enabled, a link to it in the content no longer syndicates a post that wasn't sent to it.
* Providers without a token aren't offered to Micropub clients, and fail with a clear error. The API Keys tab explains how to get a Bridgy token.
* The links (`mf2_syndication`) and the syndication log no longer appear in the Custom Fields box. The meta keys are unchanged.
* `?original-of=` matches the exact link, searches every syndication post type, and returns a 404 when nothing matches. `?syndication=` only filters the main query.
* `mailto:`, `sms:` and `tel:` links get mail and phone icons.
* Syndication links are `rel="nofollow"`. The new `syn_link_rel` filter can remove it.
* New template tags for themes: `the_syndication_links()`, `the_comment_syndication_links()` and `syndication_links_display_mode()`.
* Icons for retired services (Google+, Google Podcasts, Pocket and others) are kept in a separate pack, loaded only when one is shown. The icons are built with PHP; the Node build is gone.
* Old `syndication_urls` links are moved to `mf2_syndication` in the background instead of when a post is read.
* Links are always returned as a list, so the JSON Feed `syndication` field is never an object.
* Fixed: the privacy exporter, a fatal error in Micropub syndication, the settings page's Add and Remove buttons outside the IndieWeb menu, and adding the first custom webmention provider.
* Behaviour change: for Bridgy's Micropub providers, "Insert link back to original post: If too long" now adds the link only when the post is too long, as Bridgy's own option does. Short posts are sent without it. "Use excerpt" now applies to them, and with the link turned off the content uses the whole length limit.
* Pinboard: needs Post Kinds 4.0.1 or later, and adds the bookmark to the post's links instead of replacing them.
* Webmention providers count any 2xx response as a success, as the Webmention spec says.
* Trashing or deleting a post deletes its copies on Mastodon and Bluesky (through Bridgy) and on Bridgy Fed, and tells custom webmention targets. A trashed post waits five minutes first, so restoring it keeps them. Turn this off on the Providers tab, or filter `syndication_links_delete_copies`.
* When sending to Bridgy or another webmention target fails, its link is removed from the post's links again, instead of staying there.
* Fixed: the provider settings markup, a fatal error in the Micro.blog lookup when Micro.blog doesn't return JSON, and warnings when refreshing the Micro.blog feed or sending an untagged post to Pinboard.
* With `WP_DEBUG` on, syndication logs a one-line summary instead of the full response.
* The plugin bundles a curated set of 79 Simple Icons instead of all 3,469, starting from the IndieWeb plugin's list (see the FAQ for adding or requesting others). Links to other services show the generic website icon. The plugin's download and its icon titles file are much smaller.
* Feeds leave out Bridgy targets, which aren't copies, and the Micro.blog feed leaves out the links, so Micro.blog doesn't post them. The new `syndication_links_feed_display` filter can turn them off in any feed.
* One small front-end stylesheet (under 1 KB) instead of a 143 KB one: each icon's brand colour is now set on the icon, and the icon size and black-and-white options are added as inline styles. Historic icons no longer need a stylesheet of their own. The `syn-medium`, `syn-large`, `syn-bw*` and `syn-minimal` stylesheets are removed. New `syn_link_icon_color` filter.
* The medium and large icon sizes work again: an inline `max-width` held every icon at the small size. Icon size and spacing now come from the stylesheet only. Icons no longer carry a `<title>`, which showed a second tooltip, and the BookWyrm icon is no longer cut off.
* Micropub providers (Bridgy) return an error when the endpoint answers with an error status, using its description if it gives one, instead of logging it as a success.
* Saving a queued post again adds the newly checked providers to the queue.
* The syndication links box is no longer added to the content sent to other sites.
* The excerpt in the Bridgy Mastodon footer is escaped.
* Pages still get syndication links by default, and each post type is listed once.
* The settings page has a title outside the IndieWeb menu, and its checkbox labels can be clicked.

= Version 4.5.3 ( 2025-07-05 ) =
* Add support for Autopost for X plugin, props @iamdharmesh and the plugin team there

= Version 4.5.2 ( 2025-02-16 ) =
* Map Twitter to X icon as Twitter icon removed from set

= Version 4.5.1 ( 2024-12-08 ) =
* Issue with new readme.

= Version 4.5.0 ( 2024-12-08 ) =
* Redo the dev system for icons
* Add ability to search for icons in multiple directories and thus override an entire set of icons
* Remove Grunt as a build system

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

Earlier changelog in the changelog.md file
