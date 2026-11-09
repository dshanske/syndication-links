<?php
/**
 * Builds the Simple Icons files from the simple-icons Composer package.
 *
 * Replaces js/simpleicons.js and the cp-based copy-simple-icons script, so the
 * icon build needs no Node. Run it with `composer build-icons`. It:
 *
 * - syncs svgs/simple-icons/ with the curated set ($curated_icons): copies
 *   new and changed SVGs, and removes SVGs that aren't in the set
 * - cleans the SVGs in svgs/, svgs/simple-icons/ and svgs/historic/: removes the XML
 *   declaration, comments and <title> (the icon's span has its own title and
 *   label), and warns about any SVG in svgs/ that still has a <style> element
 * - writes includes/simple-icons-catalog.php, the title and colour of every
 *   Simple Icon and the other names that lead to it, for adding icons that
 *   aren't bundled
 * - writes includes/simple-icons.php, the icon titles and brand colours keyed
 *   by slug, for these, the historic icons in svgs/historic/ (retired
 *   services) and the custom icons in svgs/. Colours are output with each
 *   icon, so the stylesheet holds none.
 *
 * Slugs follow Simple Icons' own getIconSlug(): the icon's `slug` if it has
 * one, otherwise titleToSlug() of its title. That needs the intl extension.
 *
 * @package Syndication_Links
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root      = dirname( __DIR__ );
$package   = $root . '/vendor/simple-icons/simple-icons';
$data_file = $package . '/data/simple-icons.json';
$svg_dir   = $root . '/svgs/simple-icons';
$hist_dir  = $root . '/svgs/historic';

/*
 * The curated set: the Simple Icons bundled with the plugin. Other brands show
 * the generic website icon unless a site adds them (see the readme FAQ).
 *
 * The first part is the IndieWeb plugin's list (indieweb/wordpress-indieweb
 * PR #300, static/js/simpleicons.js), in the same order so the two can be
 * diffed. The second part is what this plugin needs as well. To ask for an
 * icon, open an issue; adding one is a line here.
 */
$curated_icons = array(
	// IndieWeb: explicitly mapped domains.
	'blogger',
	'facebook',
	'swarm',
	'instagram',
	'googleplay',
	'applepodcasts',
	'flipboard',
	'microdotblog',
	'wordpress', // phpcs:ignore WordPress.WP.CapitalPDangit
	'bluesky',
	'mastodon',
	// IndieWeb: profile fields.
	'github',
	'flickr',
	'reddit',
	// IndieWeb: common social networks.
	'medium',
	'tumblr',
	'youtube',
	'linkedin',
	'pinterest',
	'snapchat',
	'tiktok',
	'whatsapp',
	'telegram',
	'discord',
	'threads',
	'x',
	// IndieWeb: Fediverse and IndieWeb.
	'activitypub',
	'pixelfed',
	'peertube',
	'lemmy',
	'diaspora',
	'pleroma',
	'misskey',
	'matrix',
	// IndieWeb: developer platforms.
	'gitlab',
	'codeberg',
	'gitea',
	'codepen',
	'stackoverflow',
	'npm',
	'ycombinator',
	'orcid',
	'gravatar',
	// IndieWeb: content and creator platforms.
	'substack',
	'patreon',
	'kofi',
	'buymeacoffee',
	'gumroad',
	'etsy',
	'bandcamp',
	'soundcloud',
	// IndieWeb: media and entertainment.
	'spotify',
	'lastdotfm',
	'letterboxd',
	'goodreads',
	'vimeo',
	'twitch',
	'500px',
	'strava',
	// IndieWeb: design.
	'dribbble',
	'behance',
	'figma',
	// IndieWeb: messaging.
	'signal',
	'element',
	// IndieWeb: other.
	'paypal',
	'rss',

	// Syndication Links: domains mapped in Syn_Link_Domain_Icon_Map.
	'applemusic',
	'indieweb',
	// Syndication Links: syndication providers.
	'pinboard',
	// Syndication Links: networks the cross-posting plugins in Social_Plugins
	// post to (Jetpack Social, NextScripts, Keyring), whose links are imported.
	'nextdoor',
	'vk',
	'xing',
	'livejournal',
	'plurk',
	'line',
	'instapaper',
	'foursquare',
	// Syndication Links: Fediverse software, for the NodeInfo detection (#361).
	'firefish',
	'forgejo',
);

/*
 * Icons that Simple Icons has removed at the brand's request but this plugin
 * keeps, because the service is still running. Their SVG stays in
 * svgs/simple-icons/ instead of being pruned. The title and colour are the last
 * ones Simple Icons published.
 */
$retained_icons = array(
	'codepen'  => array(
		'title' => 'CodePen',
		'hex'   => '000000',
	),
	'linkedin' => array(
		'title' => 'LinkedIn',
		'hex'   => '0A66C2',
	),
);

/*
 * Historic icons: retired services that old posts still link to (#38). The
 * SVGs are kept by hand in svgs/historic/. Titles and colours are the last
 * ones Simple Icons published.
 */
$historic_icons = array(
	'cohost'              => array(
		'title' => 'cohost',
		'hex'   => '83254F',
	),
	'delicious'           => array(
		'title' => 'Delicious',
		'hex'   => '3399FF',
	),
	'ello'                => array(
		'title' => 'Ello',
		'hex'   => '000000',
	),
	'foursquarecityguide' => array(
		'title' => 'Foursquare City Guide',
		'hex'   => 'F94877',
	),
	'googleplus'          => array(
		'title' => 'Google+',
		'hex'   => 'DC4E41',
	),
	'googlepodcasts'      => array(
		'title' => 'Google Podcasts',
		'hex'   => '4285F4',
	),
	'pocket'              => array(
		'title' => 'Pocket',
		'hex'   => 'EF3F56',
	),
	'revue'               => array(
		'title' => 'Revue',
		'hex'   => 'E15718',
	),
	'stitcher'            => array(
		'title' => 'Stitcher',
		'hex'   => '000000',
	),
);

/*
 * Custom icons: SVGs kept by hand in svgs/ for services Simple Icons doesn't
 * have. Their titles and colours go into the generated map like the others.
 * Leave out the colour (empty) for an icon that should take the link's colour.
 */
$custom_icons = array(
	'bookwyrm' => array(
		'title' => 'BookWyrm',
		'hex'   => '000000',
	),
	'nostr'    => array(
		'title' => 'Nostr',
		'hex'   => '662482',
	),
);

if ( ! is_readable( $data_file ) ) {
	fwrite( STDERR, "Can't read $data_file. Run composer install first.\n" );
	exit( 1 );
}
if ( ! class_exists( 'Normalizer' ) ) {
	fwrite( STDERR, "The intl extension is required to build the icon slugs.\n" );
	exit( 1 );
}

$package_json = json_decode( (string) file_get_contents( $package . '/package.json' ), true );
$version      = isset( $package_json['version'] ) ? (string) $package_json['version'] : '';
if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
	fwrite( STDERR, "Can't read the simple-icons version from $package/package.json.\n" );
	exit( 1 );
}

$data = json_decode( file_get_contents( $data_file ), true );
// Simple Icons 16 stores a list of icons; older releases wrapped it in "icons".
if ( is_array( $data ) && isset( $data['icons'] ) ) {
	$data = $data['icons'];
}
if ( ! is_array( $data ) || empty( $data ) ) {
	fwrite( STDERR, "No icons found in $data_file.\n" );
	exit( 1 );
}

/**
 * Converts a brand title into a slug, the same way as Simple Icons' titleToSlug().
 *
 * @param string $title Brand title.
 * @return string Slug.
 */
function syn_build_title_to_slug( $title ) {
	$replacements = array(
		'+' => 'plus',
		'.' => 'dot',
		'&' => 'and',
		'đ' => 'd',
		'ħ' => 'h',
		'ı' => 'i',
		'ĸ' => 'k',
		'ŀ' => 'l',
		'ł' => 'l',
		'ß' => 'ss',
		'ŧ' => 't',
		'ø' => 'o',
	);

	$slug = strtr( mb_strtolower( $title, 'UTF-8' ), $replacements );
	$slug = Normalizer::normalize( $slug, Normalizer::FORM_D );
	return preg_replace( '/[^a-z0-9]/', '', $slug );
}

$icons   = array();
$catalog = array(
	'icons' => array(),
	'keys'  => array(),
);
foreach ( $data as $icon ) {
	if ( empty( $icon['title'] ) || empty( $icon['hex'] ) ) {
		continue;
	}
	$slug = ! empty( $icon['slug'] ) ? $icon['slug'] : syn_build_title_to_slug( $icon['title'] );
	if ( '' === $slug ) {
		continue;
	}
	// Every icon goes into the catalog, for adding icons that aren't bundled.
	$catalog['icons'][ $slug ] = array( str_replace( '&amp;', '&', $icon['title'] ), '#' . strtoupper( $icon['hex'] ) );
	$keys                      = array( $slug, syn_build_title_to_slug( $icon['title'] ) );
	if ( isset( $icon['aliases']['aka'] ) && is_array( $icon['aliases']['aka'] ) ) {
		foreach ( $icon['aliases']['aka'] as $alias ) {
			$keys[] = syn_build_title_to_slug( $alias );
		}
	}
	foreach ( array_filter( $keys ) as $key ) {
		if ( $key !== $slug && ! isset( $catalog['keys'][ $key ] ) ) {
			$catalog['keys'][ $key ] = $slug;
		}
	}
	if ( ! in_array( $slug, $curated_icons, true ) ) {
		continue;
	}
	$icons[ $slug ] = array(
		'slug'  => $slug,
		'title' => str_replace( '&amp;', '&', $icon['title'] ),
		'hex'   => strtoupper( $icon['hex'] ),
	);
}

foreach ( $retained_icons as $slug => $icon ) {
	if ( ! isset( $icons[ $slug ] ) && in_array( $slug, $curated_icons, true ) ) {
		$icons[ $slug ] = array(
			'slug'     => $slug,
			'title'    => $icon['title'],
			'hex'      => strtoupper( $icon['hex'] ),
			'retained' => true,
		);
	}
}

foreach ( array_diff( $curated_icons, array_keys( $icons ) ) as $slug ) {
	fwrite( STDERR, "Warning: curated icon $slug isn't in Simple Icons or \$retained_icons.\n" );
}

$historic = array();
foreach ( $historic_icons as $slug => $icon ) {
	if ( isset( $icons[ $slug ] ) ) {
		fwrite( STDERR, "Warning: $slug is historic but Simple Icons has it again; using the bundled icon.\n" );
		continue;
	}
	if ( ! file_exists( $hist_dir . '/' . $slug . '.svg' ) ) {
		fwrite( STDERR, "Warning: historic icon svgs/historic/$slug.svg is missing.\n" );
	}
	$historic[ $slug ] = array(
		'slug'  => $slug,
		'title' => $icon['title'],
		'hex'   => strtoupper( $icon['hex'] ),
	);
}

/**
 * Cleans an SVG for inline use.
 *
 * Removes the XML declaration, comments and the <title> element. The icon's
 * span already has a title and label, and a <title> inside an inline SVG adds
 * a second tooltip; core's SVG sanitizing keeps its text. <style> isn't
 * removed, because the shapes may depend on it: those SVGs are fixed by hand.
 *
 * @param string $svg SVG markup.
 * @return string Cleaned SVG markup.
 */
function syn_build_clean_svg( $svg ) {
	$svg = preg_replace( '/<\?xml.*?\?>/s', '', $svg );
	$svg = preg_replace( '/<!--.*?-->/s', '', $svg );
	$svg = preg_replace( '#<title\b[^>]*>.*?</title>#s', '', $svg );
	return trim( $svg ) . "\n";
}

/**
 * Sorts icons alphabetically by title, then by slug for brands that share a title.
 *
 * @param array $list Icons.
 * @return array Sorted icons.
 */
function syn_build_sort_icons( $list ) {
	usort(
		$list,
		function ( $a, $b ) {
			return strcmp( $a['title'], $b['title'] ) ?: strcmp( $a['slug'], $b['slug'] );
		}
	);
	return $list;
}

$custom = array();
foreach ( $custom_icons as $slug => $icon ) {
	if ( isset( $icons[ $slug ] ) ) {
		fwrite( STDERR, "Warning: $slug is a custom icon but Simple Icons has it; using the Simple Icons title and colour.\n" );
		continue;
	}
	if ( ! file_exists( $root . '/svgs/' . $slug . '.svg' ) ) {
		fwrite( STDERR, "Warning: custom icon svgs/$slug.svg is missing.\n" );
	}
	$custom[ $slug ] = array(
		'slug'  => $slug,
		'title' => $icon['title'],
		'hex'   => strtoupper( $icon['hex'] ),
	);
}

$icons    = syn_build_sort_icons( $icons );
$historic = syn_build_sort_icons( $historic );
$named    = syn_build_sort_icons( array_merge( $icons, $historic, $custom ) );

$width = max( array_map( 'strlen', array_column( $named, 'slug' ) ) );

// Titles and colours, keyed by slug.
$php  = "<?php\n";
$php .= "/**\n * Icon titles and colours, keyed by icon slug: Simple Icons and the historic icons.\n *\n * Generated by bin/build-icons.php from the simple-icons package. Don't edit by hand.\n *\n * @package Syndication_Links\n */\n\n";
$php .= "defined( 'ABSPATH' ) || exit;\n\n";
$php .= "/**\n * Returns the icon titles.\n *\n * @return string[] Titles, keyed by icon slug.\n */\n";
$php .= "function simpleicons_syn_get_names() {\n\treturn array(";
foreach ( $named as $icon ) {
	$php .= sprintf(
		"\n\t\t'%s'%s => '%s',",
		$icon['slug'],
		str_repeat( ' ', $width - strlen( $icon['slug'] ) ),
		addcslashes( $icon['title'], "'\\" )
	);
}
$php .= "\n\t);\n}\n\n";
$php .= "/**\n * Returns the icons' brand colours.\n *\n * @since 5.0.0\n *\n * @return string[] Hex colours, such as `#1185FE`, keyed by icon slug.\n */\n";
$php .= "function simpleicons_syn_get_colors() {\n\treturn array(";
foreach ( $named as $icon ) {
	if ( '' === $icon['hex'] ) {
		continue;
	}
	$php .= sprintf(
		"\n\t\t'%s'%s => '#%s',",
		$icon['slug'],
		str_repeat( ' ', $width - strlen( $icon['slug'] ) ),
		$icon['hex']
	);
}
$php .= "\n\t);\n}\n";

$php .= "\n/**\n * Returns the version of Simple Icons the bundled icons come from.\n *\n * Icons added later are fetched from the same version, so they match.\n *\n * @since 5.0.0\n *\n * @return string The version, such as `16.36.0`.\n */\n";
$php .= "function simpleicons_syn_version() {\n\treturn '" . $version . "';\n}\n";

file_put_contents( $root . '/includes/simple-icons.php', $php );

// The catalog of every Simple Icon, loaded only when adding icons.
ksort( $catalog['icons'] );
ksort( $catalog['keys'] );
$icon_width = max( array_map( 'strlen', array_keys( $catalog['icons'] ) ) );
$key_width  = max( array_map( 'strlen', array_keys( $catalog['keys'] ) ) );
$cat  = "<?php\n";
$cat .= "/**\n * Every Simple Icon in the release the bundled icons come from: titles and colours keyed by slug, and other names (titles and aliases) that lead to a slug.\n *\n * Generated by bin/build-icons.php from the simple-icons package. Don't edit by hand.\n * Loaded only when matching sites to icons that aren't bundled (Syn_Simple_Icons).\n *\n * @package Syndication_Links\n */\n\n";
$cat .= "defined( 'ABSPATH' ) || exit;\n\n";
$cat .= "return array(\n\t'icons' => array(";
foreach ( $catalog['icons'] as $slug => $icon ) {
	$cat .= sprintf( "\n\t\t'%s'%s => array( '%s', '%s' ),", $slug, str_repeat( ' ', $icon_width - strlen( $slug ) ), addcslashes( $icon[0], "'\\" ), $icon[1] );
}
$cat .= "\n\t),\n\t'keys'  => array(";
foreach ( $catalog['keys'] as $key => $slug ) {
	$cat .= sprintf( "\n\t\t'%s'%s => '%s',", $key, str_repeat( ' ', $key_width - strlen( $key ) ), $slug );
}
$cat .= "\n\t),\n);\n";
file_put_contents( $root . '/includes/simple-icons-catalog.php', $cat );

// Sync the SVGs.
if ( ! is_dir( $svg_dir ) ) {
	mkdir( $svg_dir, 0755, true );
}
$copied = 0;
$wanted = array();
foreach ( $icons as $icon ) {
	$file = $icon['slug'] . '.svg';
	$from = $package . '/icons/' . $file;
	$to   = $svg_dir . '/' . $file;
	if ( ! empty( $icon['retained'] ) ) {
		if ( file_exists( $to ) ) {
			$wanted[ $file ] = true;
		} else {
			fwrite( STDERR, "Warning: {$icon['slug']} is retained but svgs/simple-icons/$file is missing.\n" );
		}
		continue;
	}
	if ( ! is_readable( $from ) ) {
		fwrite( STDERR, "Warning: no SVG for {$icon['slug']} ({$icon['title']}).\n" );
		continue;
	}
	$wanted[ $file ] = true;
	$svg             = syn_build_clean_svg( file_get_contents( $from ) );
	if ( ! file_exists( $to ) || file_get_contents( $to ) !== $svg ) {
		file_put_contents( $to, $svg );
		++$copied;
	}
}
$removed = 0;
foreach ( glob( $svg_dir . '/*.svg' ) as $existing ) {
	if ( ! isset( $wanted[ basename( $existing ) ] ) ) {
		unlink( $existing );
		++$removed;
	}
}

// Clean every SVG in place, including the custom and historic ones kept by hand.
foreach ( array_merge( glob( $root . '/svgs/*.svg' ), glob( $hist_dir . '/*.svg' ), glob( $svg_dir . '/*.svg' ) ) as $file ) {
	$svg   = file_get_contents( $file );
	$clean = syn_build_clean_svg( $svg );
	if ( $clean !== $svg ) {
		file_put_contents( $file, $clean );
	}
}

// A <style> element styles the whole page, not only its SVG: warn about any that are left.
foreach ( array_merge( glob( $root . '/svgs/*.svg' ), glob( $root . '/svgs/*/*.svg' ) ) as $file ) {
	if ( false !== stripos( file_get_contents( $file ), '<style' ) ) {
		fwrite( STDERR, 'Warning: ' . substr( $file, strlen( $root ) + 1 ) . " has a <style> element. Replace it with attributes.\n" );
	}
}

printf(
	"Built %d icons (%d retained) and %d historic icons: %d SVGs copied or updated, %d removed.\n",
	count( $icons ),
	count( array_filter( array_column( $icons, 'retained' ) ) ),
	count( $historic ),
	$copied,
	$removed
);
