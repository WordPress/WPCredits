<?php
/**
 * Runs the FAQ page's script (assets/js/faq.js) in node, on a page of questions, and proves the run
 * would catch a broken script.
 *
 * Run from the theme root:  php bin/test-faq-js.php
 *
 * The page is markup of the shape the FAQ page saves: section heads, and Groups with the class
 * `wpc-faq__list` holding core Details blocks, each with its HTML anchor, as core saves them, with
 * links between them. It is handed to bin/js/faq-harness.js on stdin, which runs the script on the
 * stand-in of bin/js/hub-dom.js and drives it as a person does. Each scenario is shown as one line,
 * and the harness's summary is read as well.
 *
 * Then the mutation proof: the harness is run again on copies of the script, each with one of its jobs
 * taken out (opening the question, listening for the fragment's change, bringing the question into
 * view, reopening on a second press of the same link, opening the questions around a nested one,
 * catching a fragment that cannot be decoded, decoding the fragment, resolving a link as a URL, testing
 * that a link leads to this very page). Each
 * copy must make at least one scenario fail; a copy every scenario still passes is a job no scenario
 * checks, and fails this suite.
 *
 * A machine without node on the path gets one `skip` line and an exit of 0.
 *
 * @package WPCredits_Theme
 */

// phpcs:ignoreFile -- a command-line check, not theme code.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once __DIR__ . '/stubs/node-suite.php';

if ( '' === wpcredits_node() ) {
	echo "skip the FAQ page's script was not run: node is not on the path\n";
	exit( 0 );
}

/**
 * A question as core's Details block saves it, with its anchor.
 *
 * @param string $id       The HTML anchor.
 * @param string $question The summary.
 * @param string $answer   The answer's inner markup.
 * @param bool   $open     Whether it is saved open.
 * @return string
 */
function wpcredits_test_question( $id, $question, $answer, $open = false ) {
	return '<details id="' . $id . '" class="wp-block-details"' . ( $open ? ' open' : '' ) . '><summary>' . $question . '</summary><p>' . $answer . '</p></details>';
}

$faq = '<main class="wp-block-group wpc-main wpc-hub wpc-landing"><div class="entry-content wpc-landing__content">'
	. '<h2 id="get-connected" class="wp-block-heading wpc-hub-anchored">Get connected first</h2>'
	. '<div class="wp-block-group wpc-faq__list">'
	. wpcredits_test_question( 'account-email', 'The email did not arrive', 'Check your spam folder, then write to <a href="mailto:help@example.org">help@example.org</a>.' )
	. wpcredits_test_question( 'slack-email', 'The email from Slack did not arrive', 'It goes to the address on your account. See also <a href="#account-email">the account email</a>.' )
	. '</div>'
	. '<h2 id="registering" class="wp-block-heading wpc-hub-anchored">Registering</h2>'
	. '<div class="wp-block-group wpc-faq__list">'
	. wpcredits_test_question( 'caf&#xe9;', 'A question with an accented address', 'Its answer.' )
	. wpcredits_test_question( '', 'A question with no anchor', 'Back to <a href="#slack-email">the Slack email</a>.' )
	. '</div></div></main>';

$links = '<main><div class="wp-block-group wpc-faq__list">'
	. wpcredits_test_question( 'account-username', 'The username is taken', 'Its answer. <a href="https://site.example/faq/#account-username">This question</a>, <a href="https://site.example/other/#account-username">elsewhere</a>, <a href="https://site.example/faq/?page=2#account-username">page 2</a>, <a href="https://other.example/faq/#account-username">another site</a>.' )
	. wpcredits_test_question( 'caf&#xe9;', 'An accented address', 'Its answer. <a href="#caf&#xe9;">This question</a>, <a href="#caf%c3%a9">written in lower case</a>.' )
	. wpcredits_test_question( 'odd', 'Odd links', '<a href="#">top</a> <a href="#a&quot;b">quote</a> <a href="#%E0%A4%A">broken</a>' )
	. '</div></main>';

$answer = '<main><div class="wp-block-group wpc-faq__list">'
	. wpcredits_test_question( 'first', 'The first', 'Its answer.' )
	. wpcredits_test_question( 'midway', 'A question midway down', 'Before. <span id="answer">The words a link names.</span>' )
	. '</div></main>';

$nested = '<main><div class="wp-block-group wpc-faq__list"><details id="outer" class="wp-block-details"><summary>Outer</summary>'
	. '<details id="inner" class="wp-block-details"><summary>Inner</summary><p id="inside">The words a link names.</p></details>'
	. '</details>' . wpcredits_test_question( 'other', 'Another', 'Its answer.' ) . '</div></main>';

$saved = '<main><div class="wp-block-group wpc-faq__list">' . wpcredits_test_question( 'already', 'A question saved open', 'Its answer.', true ) . wpcredits_test_question( 'closed', 'A closed one', 'Its answer.' ) . '</div></main>';

$pages = array(
	'faq'    => $faq,
	'links'  => $links,
	'answer' => $answer,
	'nested' => $nested,
	'saved'  => $saved,
);

$status = wpcredits_run_node_suite( __DIR__ . '/js/faq-harness.js', $pages );

echo "\n=== The mutation proof: each job taken out of the script fails a scenario ===\n";

$script  = (string) file_get_contents( WPCREDITS_THEME_DIR . 'assets/js/faq.js' );
$mutants = array(
	'the question is never opened'                      => array( "around.setAttribute( 'open', '' );", '' ),
	'the fragment\'s change is not listened for'        => array( "window.addEventListener( 'hashchange', reveal );", '' ),
	'the question is not brought into view'             => array( 'element.scrollIntoView();', '' ),
	'a second press of the same link does nothing'      => array( 'window.setTimeout( reveal, 0 );', '' ),
	'only the nearest question is opened'               => array( "around = around.parentNode ? around.parentNode.closest( 'details' ) : null", 'around = null' ),
	'an undecodable fragment is not caught'             => array( "try {\n\t\t\tid = decodeURIComponent( hash.slice( 1 ) );\n\t\t} catch ( error ) {\n\t\t\treturn null;\n\t\t}", 'id = decodeURIComponent( hash.slice( 1 ) );' ),
	'the fragment is not decoded'                        => array( 'id = decodeURIComponent( hash.slice( 1 ) );', 'id = hash.slice( 1 );' ),
	'the link is not resolved as a URL'                  => array( 'samePageHash( link.getAttribute( \'href\' ) )', 'link.getAttribute( \'href\' )' ),
	'a link to another page counts as this page'         => array( "if ( url.origin !== here.origin || url.pathname !== here.pathname || url.search !== here.search ) {\n\t\t\treturn '';\n\t\t}", '' ),
);
$caught  = 0;

foreach ( $mutants as $label => $swap ) {
	$broken = str_replace( $swap[0], $swap[1], $script );

	if ( $broken === $script ) {
		echo 'FAIL ' . $label . ": the script no longer holds the line this copy takes out, so the proof proves nothing\n";
		continue;
	}

	$file = tempnam( sys_get_temp_dir(), 'wpcredits-faq-' );
	file_put_contents( $file, $broken );

	$pipes   = array();
	$process = proc_open(
		escapeshellarg( wpcredits_node() ) . ' ' . escapeshellarg( __DIR__ . '/js/faq-harness.js' ) . ' ' . escapeshellarg( WPCREDITS_THEME_DIR ) . ' ' . escapeshellarg( $file ),
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);

	fwrite( $pipes[0], (string) json_encode( $pages ) );
	fclose( $pipes[0] );

	$out = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );

	fclose( $pipes[1] );
	fclose( $pipes[2] );
	proc_close( $process );
	unlink( $file );

	$differ = preg_match( '/^\d+ scenarios, (\d+) differ$/m', $out, $found ) ? (int) $found[1] : 0;

	if ( $differ > 0 ) {
		++$caught;
		printf( "ok   %s: %d scenario(s) fail\n", $label, $differ );
	} else {
		echo 'FAIL ' . $label . ": every scenario still passes\n";
	}
}

$proved = count( $mutants ) === $caught;

printf( "\n%s (%d of %d broken copies caught)\n", $proved ? 'ALL PASS' : ( count( $mutants ) - $caught ) . ' FAILURE(S)', $caught, count( $mutants ) );

exit( 0 === $status && $proved ? 0 : 1 );
