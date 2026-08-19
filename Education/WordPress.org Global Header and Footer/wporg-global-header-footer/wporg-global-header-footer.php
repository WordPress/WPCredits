<?php
/**
 * Plugin Name:       WordPress.org Global Header and Footer
 * Description:       The official wordpress.org header and footer, vendored from WordPress/wporg-mu-plugins, for sites outside the wordpress.org network. Adds the wporg/global-header and wporg/global-footer blocks.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            WordPress Education
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wporg-global-header-footer
 *
 * The blocks themselves are the WordPress.org project's own code, copied unmodified from
 * `mu-plugins/blocks/global-header-footer` in https://github.com/WordPress/wporg-mu-plugins.
 * The commit they came from is recorded in `vendor/UPSTREAM_SHA`, and `bin/update-vendor.sh`
 * refreshes them — worth running periodically, because that repository's own README warns that
 * changes there "must be tested on all sites": it is the network's internal code and carries no
 * compatibility promise to anybody outside it.
 *
 * **What this file adds is only what the copy needs in order to run off-network.** Nothing in
 * `vendor/` is edited, so re-vendoring is a copy rather than a merge. There is less to shim than
 * there looks:
 *
 * - The Rosetta paths (locale sites, `switch_to_blog()`) all sit behind `is_rosetta_site()`, which
 *   tests `$rosetta instanceof Rosetta_Sites`. Off the network there is no such global and no such
 *   class, and `instanceof` against an undefined class is simply false rather than an error — so
 *   those branches are unreachable here and need nothing.
 * - The global menu is a list of absolute `wordpress.org` URLs written into the source, not
 *   something fetched, so it works anywhere.
 * - The logos are `require`d from `vendor/global-header-footer/images/`, so they are local.
 *
 * @package WPORG_Global_Header_Footer
 */

namespace WPORG_Global_Header_Footer;

defined( 'ABSPATH' ) || exit;

const VENDOR = __DIR__ . '/vendor/global-header-footer';

/**
 * Load the vendored blocks.
 *
 * On `plugins_loaded` rather than at file scope: the vendored code hooks `init` and
 * `rest_api_init` itself, and loading it before WordPress has finished booting its own plugins
 * would register block types before the block registry is ready.
 */
function boot() {
	// A site that is actually on the wordpress.org network already has these, from the real
	// mu-plugin. Registering them twice is a `_doing_it_wrong` for every block on every request.
	if ( function_exists( '\WordPressdotorg\MU_Plugins\Global_Header_Footer\render_global_header' ) ) {
		return;
	}

	if ( ! is_readable( VENDOR . '/blocks.php' ) ) {
		return;
	}

	fill_server_globals();

	require_once VENDOR . '/blocks.php';
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );

/**
 * Give the vendored code the request variables it reads directly.
 *
 * It reads three of them without checking they exist: `SERVER_NAME` to skip itself on
 * `login.wordpress.org`, and `HTTP_HOST` + `REQUEST_URI` to work out which menu item is the current
 * one. On a web request all three are set and this does nothing. **Under WP-CLI none of them are**,
 * and the undefined-key warnings land in the middle of whatever a cron or a sync was printing —
 * which is how this surfaced: `wp eval` rendering the header printed a PHP warning above it.
 *
 * Filled rather than patched, so `vendor/` stays a copy and re-vendoring stays a copy.
 */
function fill_server_globals() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Assigning known-safe values from the site's own URL, not reading input.
	if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
		$_SERVER['SERVER_NAME'] = $host;
	}

	if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
		$_SERVER['HTTP_HOST'] = $host;
	}

	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		$_SERVER['REQUEST_URI'] = '/';
	}
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput
}

/**
 * The header and footer as they should be placed in a block theme.
 *
 * A convenience for template parts: `wporg_ghf_header()` echoes the same thing as putting
 * `<!-- wp:wporg/global-header /-->` in a template, for themes that render their chrome in PHP.
 *
 * @return string
 */
function header_markup() {
	return do_blocks( '<!-- wp:wporg/global-header /-->' );
}

/**
 * The footer, as above.
 *
 * @return string
 */
function footer_markup() {
	return do_blocks( '<!-- wp:wporg/global-footer /-->' );
}

/**
 * Put the header and footer in a Classic theme.
 *
 * A block theme places these as template parts and WordPress enqueues their stylesheet while
 * rendering the template, which happens before `wp_head()`. A Classic theme calls
 * `get_header()` **after** `wp_head()` has already run, so the same enqueue lands too late and
 * the stylesheet is printed in the footer — the page loads unstyled and then snaps into place.
 *
 * So a Classic theme gets the style enqueued up front instead. `wporg_global_header()` and
 * `wporg_global_footer()` below are what the theme actually calls.
 */
function classic_theme_assets() {
	if ( wp_is_block_theme() || ! apply_filters( 'wporg_ghf_classic_theme', true ) ) {
		return;
	}

	wp_enqueue_style( 'wporg-global-header-footer' );
	wp_enqueue_script( 'wporg-global-header-script' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\classic_theme_assets', 20 );

require_once __DIR__ . '/template-tags.php';
