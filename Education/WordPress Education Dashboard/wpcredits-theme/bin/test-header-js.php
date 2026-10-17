<?php
/**
 * Runs the site header's scripts on the header's own server markup, in node (theme 1.24.20).
 *
 * Run from the theme root:  php bin/test-header-js.php
 *
 * The pages are rendered here: the header part around the header as bin/stubs/header-markup.php
 * prints it, passed through the theme's own `wpcredits_header_menu()` on the WordPress of
 * bin/stubs/hub-wp.php, then a page's main content, a paragraph holding a link. They are handed to
 * bin/js/header-harness.js on stdin, which runs assets/js/header-height.js and
 * assets/js/header-menu.js on a stand-in for the page (bin/js/hub-dom.js) and drives it as a person
 * does. Each scenario the harness prints is shown here as one line, and its summary, "N scenarios, M
 * differ", is read as well: a harness that stopped early with a status of 0 shows fewer lines than it
 * counts.
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
require_once WPCREDITS_THEME_DIR . 'inc/icons.php';
require_once WPCREDITS_THEME_DIR . 'inc/header.php';
require_once __DIR__ . '/stubs/header-markup.php';
require_once __DIR__ . '/stubs/node-suite.php';

if ( '' === wpcredits_node() ) {
	echo "skip the site header's scripts were not run: node is not on the path\n";
	exit( 0 );
}

/**
 * A page of the site: the header part around the header, as the theme's filter leaves it for the
 * reader, and the page's main content after it.
 *
 * @param array $reader    Who is reading and what the header holds, as wpcredits_test_header() takes it.
 * @param bool  $logged_in Whether the reader is signed in.
 * @return string
 */
function wpcredits_test_header_page( array $reader, $logged_in ) {
	$GLOBALS['logged_in'] = $logged_in;

	$header = wpcredits_header_menu( wpcredits_test_header( $reader ), array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'wpc-header' ) ) );

	$GLOBALS['logged_in'] = false;

	return '<div class="wpc-header-part wp-block-template-part">' . $header . '</div>'
		. '<main><p>The page\'s own text, and <a href="https://site.example/about/">a link in it</a>.</p></main>';
}

// The pages the scenarios open: an Administrator's header with every entry, the navigation with a
// submenu, a student's on their own Student Report Card, a header of Log out alone, which gets no
// button, and a guest's.
$pages = array(
	'admin'   => wpcredits_test_header_page( array(), true ),
	'submenu' => wpcredits_test_header_page( array( 'nav' => wpcredits_test_three_with_submenu() ), true ),
	'student' => wpcredits_test_header_page(
		array(
			'mentor'  => false,
			'current' => 'student',
		),
		true
	),
	'one'     => wpcredits_test_header_page(
		array(
			'nav'     => array(),
			'help'    => false,
			'student' => false,
			'mentor'  => false,
		),
		true
	),
	'guest'   => wpcredits_test_header_page( array( 'signed_in' => false ), false ),
);

exit( wpcredits_run_node_suite( __DIR__ . '/js/header-harness.js', $pages ) );
