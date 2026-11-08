<?php
/**
 * Builds the Simple Icons files from the simple-icons Composer package.
 *
 * Replaces js/simpleicons.js and the cp-based copy-simple-icons script, so the
 * icon build needs no Node. Run it with `composer build-icons`. It:
 *
 * - syncs svgs/simple-icons/ with the package: copies new and changed SVGs,
 *   and removes SVGs that are no longer part of the set
 * - writes includes/simple-icons.php, the icon titles and brand colours keyed
 *   by slug, for these and for the historic icons in svgs/historic/ (retired
 *   services). Colours are output with each icon, so the stylesheet holds none.
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

if ( ! is_readable( $data_file ) ) {
	fwrite( STDERR, "Can't read $data_file. Run composer install first.\n" );
	exit( 1 );
}
if ( ! class_exists( 'Normalizer' ) ) {
	fwrite( STDERR, "The intl extension is required to build the icon slugs.\n" );
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

$icons = array();
foreach ( $data as $icon ) {
	if ( empty( $icon['title'] ) || empty( $icon['hex'] ) ) {
		continue;
	}
	$slug = ! empty( $icon['slug'] ) ? $icon['slug'] : syn_build_title_to_slug( $icon['title'] );
	if ( '' === $slug ) {
		continue;
	}
	$icons[ $slug ] = array(
		'slug'  => $slug,
		'title' => str_replace( '&amp;', '&', $icon['title'] ),
		'hex'   => strtoupper( $icon['hex'] ),
	);
}

foreach ( $retained_icons as $slug => $icon ) {
	if ( ! isset( $icons[ $slug ] ) ) {
		$icons[ $slug ] = array(
			'slug'     => $slug,
			'title'    => $icon['title'],
			'hex'      => strtoupper( $icon['hex'] ),
			'retained' => true,
		);
	}
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

$icons    = syn_build_sort_icons( $icons );
$historic = syn_build_sort_icons( $historic );
$named    = syn_build_sort_icons( array_merge( $icons, $historic ) );

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
	$php .= sprintf(
		"\n\t\t'%s'%s => '#%s',",
		$icon['slug'],
		str_repeat( ' ', $width - strlen( $icon['slug'] ) ),
		$icon['hex']
	);
}
$php .= "\n\t);\n}\n";

file_put_contents( $root . '/includes/simple-icons.php', $php );

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
	if ( ! file_exists( $to ) || file_get_contents( $from ) !== file_get_contents( $to ) ) {
		copy( $from, $to );
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

printf(
	"Built %d icons (%d retained) and %d historic icons: %d SVGs copied or updated, %d removed.\n",
	count( $icons ),
	count( array_filter( array_column( $icons, 'retained' ) ) ),
	count( $historic ),
	$copied,
	$removed
);
