<?php
/**
 * Runs the Notice template's forward script on its own server markup, in node (theme 1.32.12).
 *
 * Run from the theme root:  php bin/test-notice-js.php
 *
 * The page is rendered here: the forward block (blocks/notice-forward) as the theme renders it on the
 * WordPress of bin/stubs/hub-wp.php, after a page's content with its two buttons, as the Notice template
 * draws it. It is handed to bin/js/notice-harness.js on stdin, which runs the block's view script on a
 * stand-in for the page (bin/js/hub-dom.js) with a clock it moves by hand, and drives it as a person does.
 * Each scenario the harness prints is shown here as one line, and its summary is read as well.
 *
 * A machine without node on the path gets one `skip` line and an exit of 0.
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once __DIR__ . '/stubs/node-suite.php';

if ( '' === wpcredits_node() ) {
	echo "skip the Notice template's forward was not run: node is not on the path\n";
	exit( 0 );
}

$forward = is_file( WPCREDITS_THEME_DIR . 'blocks/notice-forward/render.php' ) ? wpcredits_test_block( 'wpcredits/notice-forward' ) : '';
$content = '<p class="wpc-hub-lede">The page\'s own words.</p><div class="wp-block-buttons wpc-hero__actions"><div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="https://site.example/#programs">Find where to start</a></div></div>';

$pages = array(
	'notice' => '<main>' . $content . $forward . '</main>',
	'plain'  => '<main>' . $content . '</main>',
);

exit( wpcredits_run_node_suite( __DIR__ . '/js/notice-harness.js', $pages ) );
