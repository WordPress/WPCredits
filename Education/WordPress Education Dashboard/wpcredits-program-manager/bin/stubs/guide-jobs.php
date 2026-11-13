<?php
/**
 * What the help link to the Administrator guide (`WPCPM_Guide_Jobs`) asks of WordPress, for a suite that
 * draws a screen or a card holding one. Loads the real class and the Settings screen's guide lookup.
 *
 * The guide is the page `$GLOBALS['wpcpm_guide_page']` holds, a `WP_Post`-shaped object with a
 * `post_status`; with none, which is what a suite starts with, the site has no published guide and no
 * link is drawn, so a suite that pins a screen's markup byte for byte is not changed by it. A suite that
 * checks the link sets the page. A function a suite declares itself is left as it is,
 * and `current_user_can()` is always the suite's own.
 */

if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = 'OBJECT', $post_type = 'page' ) { return $GLOBALS['wpcpm_guide_page'] ?? null; }
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) { return 'https://example.test/administrator-guide/'; }
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}

require_once dirname( __DIR__, 2 ) . '/includes/class-wpcpm-settings-screen.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wpcpm-guide-jobs.php';
