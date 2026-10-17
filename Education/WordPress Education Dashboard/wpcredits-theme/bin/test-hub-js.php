<?php
/**
 * Runs the hub's view scripts on the hub's own server markup, in node (theme 1.24.15).
 *
 * Run from the theme root:  php bin/test-hub-js.php
 *
 * The markup is rendered here, by the blocks' render.php files on the WordPress of
 * bin/stubs/hub-wp.php, and handed to bin/js/hub-harness.js on stdin, which runs each block's
 * view.js on a stand-in for the page (bin/js/hub-dom.js) and drives it as a person does. Each scenario
 * the harness prints is shown here as one line, and its summary, "N scenarios, M differ", is read as
 * well: a harness that stopped early with a status of 0 shows fewer lines than it counts.
 *
 * Every other check of the theme is PHP and needs nothing installed, so a machine without node on the
 * path gets one `skip` line and an exit of 0 rather than a failure.
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';
require_once __DIR__ . '/stubs/node-suite.php';

if ( '' === wpcredits_node() ) {
	echo "skip the hub's view scripts were not run: node is not on the path\n";
	exit( 0 );
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it, and the whole hub page, as the template draws it.
$pages = array(
	'index'   => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a> <a href="#program-wordpress-credits">WordPress Credits<span aria-hidden="true"> →</span></a></nav></main>',
	'pathway' => '<main>' . wpcredits_test_block( 'wpcredits/pathway' ) . '<nav><a href="#wpc-hub-step-students-6">Community Leader<span aria-hidden="true"> →</span></a></nav></main>',
	'page'    => '<main>' . wpcredits_test_page() . '</main>',
);

exit( wpcredits_run_node_suite( __DIR__ . '/js/hub-harness.js', $pages ) );
