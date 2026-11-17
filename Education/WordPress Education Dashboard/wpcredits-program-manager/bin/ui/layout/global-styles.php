<?php
/**
 * The layout harness's global styles: WordPress prints them from a theme.json it is handed. Read only.
 *
 * Never run by hand. refresh-global-styles.mjs puts `$wpcpm_gs_input` in front of this file (the theme.json to
 * print, the core blocks the page renders) and pipes the two to the Docker copy's WP-CLI:
 *
 *   bin/wp eval-file - --skip-themes --skip-plugins=<every active plugin but Gutenberg>
 *
 * It prints one line, `WPCPM-GS <base64 of a JSON object>`, and nothing else of its own: the block library's
 * sheets for those blocks, the global styles block as the front end prints it (gutenberg_get_global_stylesheet()
 * with the block nodes filtered out, the Customizer's CSS and the global styles' custom CSS after it, then the
 * block nodes of the blocks on the page, as gutenberg_add_global_styles_for_blocks() adds them), and what it was
 * made from.
 *
 * Why it writes nothing:
 * - every database query from here on passes a filter that stops the run on anything but SELECT, SHOW,
 *   DESCRIBE or EXPLAIN, so a stray write never reaches the database;
 * - it refuses to run with a persistent object cache, so wp_cache_set() stays in this process's memory;
 * - it calls no function that sets a transient or a theme mod (gutenberg_add_global_styles_for_blocks() and
 *   wp_get_custom_css_post() do, so their reads are repeated here without the cache) and reads the user's
 *   global styles with create_post false;
 * - it opens no file for writing and makes no request.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) || ! isset( $wpcpm_gs_input ) || ! is_array( $wpcpm_gs_input ) ) {
	fwrite( STDERR, "global-styles.php: run through refresh-global-styles.mjs\n" );
	exit( 2 );
}

// No write reaches the database from here on.
add_filter(
	'query',
	static function ( $query ) {
		if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', (string) $query ) ) {
			fwrite( STDERR, 'global-styles.php: refused a query that is not a read: ' . substr( (string) $query, 0, 80 ) . "\n" );
			exit( 3 );
		}
		return $query;
	},
	PHP_INT_MAX
);
if ( wp_using_ext_object_cache() ) {
	fwrite( STDERR, "global-styles.php: a persistent object cache is in use; refusing\n" );
	exit( 4 );
}

$wpcpm_gs_gutenberg = class_exists( 'WP_Theme_JSON_Resolver_Gutenberg' ) && function_exists( 'gutenberg_get_global_stylesheet' );
$wpcpm_gs_resolver  = $wpcpm_gs_gutenberg ? 'WP_Theme_JSON_Resolver_Gutenberg' : 'WP_Theme_JSON_Resolver';
$wpcpm_gs_data_cls  = $wpcpm_gs_gutenberg ? 'WP_Theme_JSON_Data_Gutenberg' : 'WP_Theme_JSON_Data';

$wpcpm_gs_theme = json_decode( (string) $wpcpm_gs_input['theme_json'], true );
if ( ! is_array( $wpcpm_gs_theme ) ) {
	fwrite( STDERR, "global-styles.php: the theme.json handed in is not JSON\n" );
	exit( 5 );
}

// The variations registered with style data, merged in as the resolver does before its filter.
$wpcpm_gs_inject = new ReflectionMethod( $wpcpm_gs_resolver, 'inject_variations_from_block_styles_registry' );
$wpcpm_gs_inject->setAccessible( true );
$wpcpm_gs_theme = $wpcpm_gs_inject->invoke( null, $wpcpm_gs_theme );

// The theme's data is the theme.json handed in, not the copy's theme's: the filter runs first, so any other
// filter of the theme's data still runs after it, as on the site.
add_filter(
	'wp_theme_json_data_theme',
	static function () use ( $wpcpm_gs_theme, $wpcpm_gs_data_cls ) {
		return new $wpcpm_gs_data_cls( $wpcpm_gs_theme, 'theme' );
	},
	PHP_INT_MIN
);
call_user_func( array( $wpcpm_gs_resolver, 'clean_cached_data' ) );
wp_cache_delete( 'gutenberg_get_global_stylesheet', 'theme_json' );
wp_cache_delete( 'wp_get_global_stylesheet', 'theme_json' );

// The global styles block, as the front end builds it for a block theme.
add_filter( 'wp_theme_json_get_style_nodes', 'wp_filter_out_block_nodes' );
$wpcpm_gs_global = $wpcpm_gs_gutenberg ? gutenberg_get_global_stylesheet() : wp_get_global_stylesheet();
// The Customizer's CSS, read as wp_get_custom_css() reads it but without wp_get_custom_css_post()'s cache,
// which stores the post's ID as a theme mod (a write).
$wpcpm_gs_css_q  = new WP_Query(
	array(
		'post_type'              => 'custom_css',
		'post_status'            => get_post_stati(),
		'name'                   => sanitize_title( get_stylesheet() ),
		'posts_per_page'         => 1,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	)
);
$wpcpm_gs_custom = trim( (string) apply_filters( 'wp_get_custom_css', $wpcpm_gs_css_q->post ? $wpcpm_gs_css_q->post->post_content : '', get_stylesheet() ) );
if ( '' !== $wpcpm_gs_custom ) {
	$wpcpm_gs_global .= "\n" . $wpcpm_gs_custom;
}
$wpcpm_gs_global .= $wpcpm_gs_gutenberg ? gutenberg_get_global_stylesheet( array( 'custom-css' ) ) : wp_get_global_stylesheet( array( 'custom-css' ) );

// The block nodes of the blocks on the page, in the tree's order: core blocks only when the page renders them,
// any other block's always (gutenberg_add_global_styles_for_blocks(), without its transient).
$wpcpm_gs_blocks = array_values( array_map( 'strval', (array) $wpcpm_gs_input['blocks'] ) );
$wpcpm_gs_tree   = call_user_func( array( $wpcpm_gs_resolver, 'get_merged_data' ) );
$wpcpm_gs_tree   = call_user_func( array( $wpcpm_gs_resolver, 'resolve_theme_file_uris' ), $wpcpm_gs_tree );
$wpcpm_gs_nodes  = array();
foreach ( $wpcpm_gs_tree->get_styles_block_nodes() as $wpcpm_gs_meta ) {
	$wpcpm_gs_name = isset( $wpcpm_gs_meta['name'] ) ? $wpcpm_gs_meta['name'] : ( empty( $wpcpm_gs_meta['path'] ) ? '' : wp_get_block_name_from_theme_json_path( $wpcpm_gs_meta['path'] ) );
	if ( '' === (string) $wpcpm_gs_name ) {
		continue;
	}
	if ( str_starts_with( $wpcpm_gs_name, 'core/' ) && ! in_array( $wpcpm_gs_name, $wpcpm_gs_blocks, true ) ) {
		continue;
	}
	$wpcpm_gs_css = $wpcpm_gs_tree->get_styles_for_block( $wpcpm_gs_meta );
	// wp_add_inline_style() keeps each piece that is not empty, and the page prints them a line apart.
	if ( '' !== (string) $wpcpm_gs_css ) {
		$wpcpm_gs_nodes[] = $wpcpm_gs_name;
		$wpcpm_gs_global .= "\n" . $wpcpm_gs_css;
	}
}

// The block library's sheets: each rendered block's own style handles, then the library's shared sheet, which
// a page that loads block assets separately prints as common.css.
$wpcpm_gs_styles = wp_styles();
$wpcpm_gs_read   = static function ( $handle ) use ( $wpcpm_gs_styles ) {
	if ( empty( $wpcpm_gs_styles->registered[ $handle ] ) ) {
		return null;
	}
	$style = $wpcpm_gs_styles->registered[ $handle ];
	$path  = isset( $style->extra['path'] ) ? (string) $style->extra['path'] : '';
	if ( 'wp-block-library' === $handle ) {
		$path = (string) preg_replace( '#/style((\.min)?\.css)$#', '/common$1', $path );
	}
	$css = '' !== $path && is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	if ( ! empty( $style->extra['after'] ) ) {
		$css .= "\n" . implode( "\n", (array) $style->extra['after'] );
	}
	return array(
		'handle' => $handle,
		'file'   => '' === $path ? '' : str_replace( ABSPATH, '', $path ),
		'css'    => $css,
	);
};
$wpcpm_gs_library = array();
$wpcpm_gs_reg     = WP_Block_Type_Registry::get_instance();
foreach ( $wpcpm_gs_blocks as $wpcpm_gs_block ) {
	$wpcpm_gs_type = $wpcpm_gs_reg->get_registered( $wpcpm_gs_block );
	foreach ( $wpcpm_gs_type ? (array) $wpcpm_gs_type->style_handles : array() as $wpcpm_gs_handle ) {
		$wpcpm_gs_sheet = $wpcpm_gs_read( $wpcpm_gs_handle );
		if ( $wpcpm_gs_sheet && '' !== trim( $wpcpm_gs_sheet['css'] ) ) {
			$wpcpm_gs_library[] = $wpcpm_gs_sheet;
		}
	}
}
$wpcpm_gs_common = $wpcpm_gs_read( 'wp-block-library' );
if ( $wpcpm_gs_common ) {
	$wpcpm_gs_library[] = $wpcpm_gs_common;
}

echo 'WPCPM-GS ' . base64_encode(
	wp_json_encode(
		array(
			'wordpress'     => get_bloginfo( 'version' ),
			'gutenberg'     => defined( 'GUTENBERG_VERSION' ) ? GUTENBERG_VERSION : '',
			'separate'      => wp_should_load_separate_core_block_assets(),
			'blocks'        => $wpcpm_gs_blocks,
			'block_nodes'   => $wpcpm_gs_nodes,
			'custom_css'    => strlen( $wpcpm_gs_custom ),
			'library'       => $wpcpm_gs_library,
			'global_styles' => $wpcpm_gs_global,
		)
	)
) . "\n";
