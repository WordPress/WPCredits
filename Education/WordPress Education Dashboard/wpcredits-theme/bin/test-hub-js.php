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

/**
 * The whole hub page as the template draws it: with its planned content (the pathway among it, and
 * the six programs), as the hub prints it with the filter on, or as it is offered today.
 *
 * @param bool $planned Whether the hub shows its planned content.
 * @return string
 */
function wpcredits_test_hub_page( $planned ) {
	if ( $planned ) {
		add_filter( 'wpcredits_hub_show_planned', 'wpcredits_test_true' );
	}

	$html = '<main>' . wpcredits_test_page() . '</main>';

	unset( $GLOBALS['hooks']['wpcredits_hub_show_planned'] );

	return $html;
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it, and the whole hub page, as the template draws it. The index is the one the
// hub pattern holds, its Program cards inside it, and the band of courses after it (theme 1.32.0).
$pages = array(
	'index'   => '<main>' . render_block( wpcredits_test_block_in( wpcredits_test_pattern( 'hub-programs.php' ), 'wpcredits/program-index' ) ) . do_blocks( (string) wpcredits_hub_course_bands_markup( wpcredits_hub_programs() ) ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a> <a href="#program-wordpress-credits">WordPress Credits<span aria-hidden="true"> →</span></a></nav></main>',
	'pathway' => '<main>' . wpcredits_test_block( 'wpcredits/pathway' ) . '<nav><a href="#wpc-hub-step-students-6">Community Leader<span aria-hidden="true"> →</span></a></nav></main>',
	'page'    => wpcredits_test_hub_page( true ),
	'today'   => wpcredits_test_hub_page( false ),
);

/**
 * The program index with two cards hidden at some size by core's block visibility (theme 1.32.1), as the
 * server draws it: the first student card (Campus Connect) hidden on mobile and the card for students and
 * faculty (Learn WordPress) hidden on desktop, so the window shows four of the five at the narrowest and the
 * widest, and all five between. The band of courses follows the index, as on the hub.
 *
 * @return string
 */
function wpcredits_test_hidden_index() {
	$index = wpcredits_test_block_in( wpcredits_test_pattern( 'hub-programs.php' ), 'wpcredits/program-index' );
	$sizes = array(
		'campus-connect'  => 'mobile',
		'learn-wordpress' => 'desktop',
	);

	foreach ( $index['innerBlocks'] as $at => $card ) {
		if ( isset( $sizes[ $card['attrs']['slug'] ] ) ) {
			$index['innerBlocks'][ $at ]['attrs']['metadata'] = array( 'blockVisibility' => array( 'viewport' => array( $sizes[ $card['attrs']['slug'] ] => false ) ) );
		}
	}

	add_filter( 'render_block', 'wpcredits_test_block_visibility', 10, 2 );

	$html = '<main>' . render_block( $index ) . do_blocks( (string) wpcredits_hub_course_bands_markup( wpcredits_hub_programs() ) ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-campus-connect">Campus Connect</a></nav></main>';

	remove_filter( 'render_block', 'wpcredits_test_block_visibility', 10 );

	return $html;
}

$pages['hidden'] = wpcredits_test_hidden_index();

/**
 * The words of the Field of study rows block's frame as the server draws them for a list with none of the
 * five saved, under some translations: the header line's three names and the two labels a screen reader
 * hears in a row. The Field of study rows block's editor script (theme 1.32.1) shows the same words as its
 * values, and the harness compares the two.
 *
 * @param array $translations What a translated string comes back as, as `$GLOBALS['translated']` has it.
 * @return array{translations: array, head: string[], labels: string[]}
 */
function wpcredits_test_fit_words( array $translations ) {
	$GLOBALS['translated'] = $translations;

	$html = do_blocks( get_comment_delimited_block_content( 'wpcredits/fit-list', array(), "\n" . get_comment_delimited_block_content( 'wpcredits/fit-row', array( 'field' => 'Music', 'teams' => array( 'Docs' ), 'proof' => 'A recital' ), '' ) . "\n" ) );

	$GLOBALS['translated'] = array();

	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	$xpath = new DOMXPath( $document );
	$said  = static function ( $node ) { return trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ); };

	return array(
		'translations' => $translations,
		'head'         => array_map( $said, iterator_to_array( $xpath->query( '//div[@class="wpc-hub-flow__head"]/span' ) ) ),
		'labels'       => array_map( $said, iterator_to_array( $xpath->query( '//li//span[@class="screen-reader-text"]' ) ) ),
	);
}

// The Field of study rows block's editor is run against what the server draws for it (theme 1.32.1): with
// no translation, and with the five words translated.
$pages['fit'] = array(
	'today'      => wpcredits_test_fit_words( array() ),
	'translated' => wpcredits_test_fit_words(
		array(
			'Your field of study'         => 'Ihr Fachgebiet',
			'The team you join'           => 'Das Team',
			'What you\'ll have to show'   => 'Was Sie zeigen',
			'The team you join:'          => 'Das Team:',
			'What you\'ll have to show:'  => 'Was Sie zeigen:',
		)
	),
);

exit( wpcredits_run_node_suite( __DIR__ . '/js/hub-harness.js', $pages ) );
