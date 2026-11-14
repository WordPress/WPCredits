<?php
/**
 * assets/js/parts.js: the parts' script (design 3.2, job 1, and 8.5), run by node on a stand-in page.
 *
 * Run from the plugin root:  php bin/test-parts-js.php
 *
 * What each block pins, and why:
 *
 * - **The script keeps to itself**: it never fetches, posts or stores anything and never writes markup
 *   (no `fetch(`, `XMLHttpRequest`, `localStorage`, `sessionStorage`, `innerHTML`), runs in strict mode,
 *   and draws no motion (no `animate(`, no transition or animation set from the script).
 * - **A shared panel's opener names its submit**: `WPCPM_UI::decide()` prints `data-wpcpm-for` the
 *   decision's key on each opener of a shared panel and on its submit, so the script pairs them by key; run
 *   here with the sharer listed after the owner and before it.
 * - **The harness's page is decide()'s own**: three pages of its real output (nothing open, A's Ask open
 *   after a refusal, the shared panel open after a refused Decline) are written to a fixture file in a
 *   temporary directory and passed to the harness, which reads them into its stand-in.
 * - **What the script does**, run by node on the page `decide()` and `row()` print with no script
 *   (bin/js/parts.js): on load the openers and Cancels are revealed and every panel and row form is folded
 *   but the one printed `data-wpcpm-open`; an opener shows its panel, folds the area's others, and moves the
 *   focus to the first field; pressed again, Cancel and Escape fold it, the last two putting the focus back
 *   on the opener; a shared panel shows only the pressed opener's submit, and a post made with its hidden
 *   submit goes out with the shown one; a type-and-pick box inside a panel is no opener and its own Escape
 *   is not the panel's; one area that fails leaves the others folding; two areas never touch each
 *   other; a page with none of the parts is left alone and a script included twice runs once. Two
 *   mutation proofs show the scenarios can fail.
 *
 * A machine without node on the path gets one `skip` line for the run, and the other blocks still run.
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return esc_attr( $url ); }
function is_admin() { return false; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function wp_style_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {}
function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {}
function wp_enqueue_script( $handle ) {}
function _doing_it_wrong( $function_name, $message, $version ) {}
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function wp_create_nonce( $action ) { return 'n0nce-' . $action; }

require_once __DIR__ . '/../includes/class-wpcpm-icons.php';
require_once __DIR__ . '/../includes/class-wpcpm-form-errors.php';
require_once __DIR__ . '/../includes/class-wpcpm-ui.php';

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '     expected: ' . var_export( $expected, true ) . "\n     actual:   " . var_export( $actual, true ) . "\n";
	}
}

$script_file = __DIR__ . '/../assets/js/parts.js';
$script      = is_file( $script_file ) ? (string) file_get_contents( $script_file ) : '';

echo "=== The script keeps to itself ===\n";
ck( 'assets/js/parts.js exists', '' !== $script, true );
ck( 'it runs in strict mode', false !== strpos( $script, "'use strict';" ), true );
foreach ( array( 'fetch(', 'XMLHttpRequest', 'localStorage', 'sessionStorage', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'sendBeacon', 'animate(', '.style.' ) as $banned ) {
	ck( 'it holds no ' . $banned, false === strpos( $script, $banned ), true );
}
ck( 'it holds no em or en dash', 1 === preg_match( '/\x{2013}|\x{2014}/u', $script ), false );

echo "\n=== A shared panel's opener names its submit: both carry data-wpcpm-for the decision's key ===\n";
/**
 * For each opener of the shared panel, in order: its words, the key it carries, and whether a submit in the
 * panel carries the same key.
 *
 * @param string $html decide() markup.
 * @return array
 */
function opener_to_submit( $html ) {
	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$x       = new DOMXPath( $doc );
	$panel   = $x->query( '//*[@data-wpcpm-panel]' )->item( 0 );
	$openers = $x->query( '//*[@aria-controls="' . $panel->getAttribute( 'id' ) . '"]' );
	$submits = $x->query( './/*[@data-wpcpm-for]', $panel );
	$out     = array();
	$keys    = array();
	foreach ( $submits as $submit ) {
		$keys[] = $submit->getAttribute( 'data-wpcpm-for' );
	}
	foreach ( $openers as $opener ) {
		$key   = $opener->getAttribute( 'data-wpcpm-for' );
		$out[] = array( trim( $opener->textContent ), $key, in_array( $key, $keys, true ) );
	}
	return $out;
}

$field    = WPCPM_UI::field(
	array(
		'id'    => 'wpcpm-request-note-7',
		'name'  => 'wpcpm_request_note',
		'label' => 'What you did about it',
		'type'  => 'textarea',
		'need'  => 'optional',
	)
);
$owner    = array(
	'key'          => 'done',
	'label'        => 'Mark as handled',
	'form_attrs'   => array( 'method' => 'post' ),
	'fields'       => $field,
	'submit_name'  => 'state',
	'submit_value' => 'done',
);
$sharer   = array(
	'key'          => 'declined',
	'label'        => 'Decline',
	'kind'         => 'danger',
	'panel'        => 'done',
	'submit_name'  => 'state',
	'submit_value' => 'declined',
);
$after    = WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-req-7', 'side' => 'front', 'decisions' => array( $owner, $sharer ) ) );
$before   = WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-req-8', 'side' => 'front', 'decisions' => array( $sharer, $owner ) ) );
ck( 'the sharer listed after the owner: each opener carries its own decision\'s key, and a submit carries the same', opener_to_submit( $after ), array( array( 'Mark as handled', 'done', true ), array( 'Decline', 'declined', true ) ) );
// UI shared parts, Task 13d fix round 1: decide() prints the row primary first, so the danger sharer listed first is
// drawn after the owner, its own key on its opener and its submit as before.
ck( 'the sharer listed before the owner: the same, the row in the one order (the owner, the primary, first)', opener_to_submit( $before ), array( array( 'Mark as handled', 'done', true ), array( 'Decline', 'declined', true ) ) );

// The pages the harness runs on are decide()'s own markup, so the stand-in cannot drift from what the site
// prints: areas A and B (two applications) and C (a school's request), with nothing open, with A's Ask open
// after a refusal, and with C open after a refused Decline. Labels carry the area's letter so the harness can
// find each control by its words.
$application = function ( $name, $open = '' ) {
	$decisions = array(
		array(
			'key'          => 'approve',
			'label'        => $name . ' Approve',
			'form_attrs'   => array( 'method' => 'post' ),
			'hidden'       => function () use ( $name ) {
				echo '<input type="hidden" name="wpcpm_application" value="' . esc_attr( $name ) . '">';
			},
			'submit_name'  => 'wpcpm_decision',
			'submit_value' => 'approve',
		),
	);
	foreach ( array( 'ask', 'reject' ) as $key ) {
		$decisions[] = array(
			'key'           => $key,
			'label'         => $name . ' ' . $key . ' opener',
			'kind'          => 'reject' === $key ? 'danger' : 'secondary',
			'form_attrs'    => array( 'method' => 'post' ),
			'hidden'        => function () use ( $name ) {
				echo '<input type="hidden" name="wpcpm_application" value="' . esc_attr( $name ) . '">';
			},
			'fields'        => WPCPM_UI::field(
				array(
					'id'    => 'wpcpm_reason-' . $name . '-' . $key,
					'name'  => 'wpcpm_reason',
					'label' => 'Why',
					'type'  => 'textarea',
				)
			),
			'confirm_label' => $name . ' ' . $key . ' submit',
			'submit_name'   => 'wpcpm_decision',
			'submit_value'  => $key,
			'open'          => $key === $open,
		);
	}
	return WPCPM_UI::decide( array( 'id' => 'decide-' . $name, 'side' => 'front', 'decisions' => $decisions ) );
};
$request     = function ( $open = '' ) use ( $field ) {
	return WPCPM_UI::decide(
		array(
			'id'        => 'decide-C',
			'side'      => 'front',
			'decisions' => array(
				array(
					'key'           => 'done',
					'label'         => 'Mark as handled',
					'form_attrs'    => array( 'method' => 'post' ),
					'hidden'        => function () {
						echo '<input type="hidden" name="wpcpm_request" value="7">';
					},
					'fields'        => $field,
					'confirm_label' => 'done submit',
					'submit_name'   => 'state',
					'submit_value'  => 'done',
					'open'          => 'done' === $open,
				),
				array(
					'key'           => 'declined',
					'label'         => 'Decline',
					'kind'          => 'danger',
					'panel'         => 'done',
					'confirm_label' => 'declined submit',
					'submit_name'   => 'state',
					'submit_value'  => 'declined',
					'open'          => 'declined' === $open,
				),
			),
		)
	);
};
// Task V1, ruling 4: an event of the program's own (D: "Change the event", a panel with a field, and "Cancel the
// event", a panel that holds a sentence, its confirming button and its dismissing one, "D keep", and no field), and a
// sponsor application's Approve (E: a panel whose one field is a checkbox), as the events card and the sponsor
// applications card hand them to decide().
$event       = WPCPM_UI::decide(
	array(
		'id'        => 'decide-D',
		'side'      => 'front',
		'decisions' => array(
			array(
				'key'           => 'change',
				'label'         => 'D change opener',
				'kind'          => 'secondary',
				'form_attrs'    => array( 'method' => 'post' ),
				'fields'        => WPCPM_UI::field( array( 'id' => 'wpcpm-event-title-D', 'name' => 'wpcpm_event_title', 'label' => 'Title' ) ),
				'confirm_label' => 'D change submit',
			),
			array(
				'key'           => 'cancel',
				'label'         => 'D cancel opener',
				'kind'          => 'danger',
				'form_attrs'    => array( 'method' => 'post' ),
				'fields'        => WPCPM_UI::intro( 'The event is canceled for everyone.' ),
				'confirm'       => 'Cancel this event for everyone?',
				'confirm_label' => 'D cancel submit',
				'cancel_label'  => 'D keep',
			),
		),
	)
);
$approve     = WPCPM_UI::decide(
	array(
		'id'        => 'decide-E',
		'side'      => 'front',
		'decisions' => array(
			array(
				'key'           => 'approve',
				'label'         => 'E approve opener',
				'form_attrs'    => array( 'method' => 'post' ),
				'fields'        => WPCPM_UI::choices(
					array(
						'id'      => 'wpcpm_test-E',
						'name'    => 'wpcpm_test',
						'type'    => 'checkbox',
						'options' => array( '1' => 'Test sponsor' ),
					)
				),
				'confirm_label' => 'E approve submit',
			),
		),
	)
);
$pages       = array(
	'plain'           => $application( 'A' ) . $application( 'B' ) . $request(),
	'a-ask-open'      => $application( 'A', 'ask' ) . $application( 'B' ) . $request(),
	'c-declined-open' => $application( 'A' ) . $application( 'B' ) . $request( 'declined' ),
	'no-field'        => $event . $approve,
);
ck( 'the fixture pages hold three areas each, and the open ones one panel printed open; the page of task V1 two areas, none open', array_map( function ( $html ) { return array( substr_count( $html, 'class="wpcpm-ui-decide"' ), substr_count( $html, 'data-wpcpm-open' ) ); }, $pages ), array( 'plain' => array( 3, 0 ), 'a-ask-open' => array( 3, 1 ), 'c-declined-open' => array( 3, 1 ), 'no-field' => array( 2, 0 ) ) );
ck( 'V1: the panel of "Cancel the event" holds no field a person fills in, its confirming button and its dismissing one; Approve\'s holds a checkbox', array(
	1 === preg_match( '#<div[^>]*id="decide-D-cancel-panel"[^>]*>(.*?)</form></div>#s', $event, $m ) ? array( preg_match( '#<(input(?![^>]*type="hidden")|textarea|select)\b#', $m[1] ), substr_count( $m[1], 'type="submit"' ), substr_count( $m[1], 'data-wpcpm-cancel' ), false !== strpos( $m[1], '>D keep</button>' ) ) : null,
	false !== strpos( $approve, 'type="checkbox"' ),
), array( array( 0, 1, 1, true ), true ) );
$fixture_dir = sys_get_temp_dir() . '/wpcpm-parts-js-' . getmypid();
if ( ! is_dir( $fixture_dir ) ) {
	mkdir( $fixture_dir, 0700 );
}
$fixture = $fixture_dir . '/decide.json';
file_put_contents( $fixture, json_encode( $pages ) );

echo "\n=== The script, run by node on the page the parts print ===\n";
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the script was not run: node is not on the path\n";
} else {
	$out    = array();
	$status = 0;

	exec(
		escapeshellarg( $node ) . ' ' . escapeshellarg( __DIR__ . '/js/parts.js' ) . ' ' . escapeshellarg( $script_file ) . ' ' . escapeshellarg( $fixture ) . ' 2>&1',
		$out,
		$status
	);

	// Each scenario line is counted here, the summary is checked against the lines shown, and anything
	// else (a crash) is shown as it came.
	$ran     = 0;
	$differ  = 0;
	$summary = null;

	foreach ( $out as $line ) {
		if ( 0 === strpos( $line, 'ok   ' ) ) {
			++$ran;
			++$checks;
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, 'FAIL ' ) ) {
			++$ran;
			++$checks;
			++$differ;
			++$fail;
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, '       ' ) ) {
			echo $line . "\n";
		} elseif ( '' !== trim( $line ) ) {
			if ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', trim( $line ), $counts ) ) {
				$summary = array( (int) $counts[1], (int) $counts[2] );
			}

			echo '     ' . $line . "\n";
		}
	}

	ck( 'the harness ran scenarios', $ran > 0, true );
	ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
	ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );
}

unlink( $fixture );
rmdir( $fixture_dir );

echo "\n" . ( $fail ? "$fail of $checks checks failed\n" : "ALL PASS ($checks checks)\n" );
exit( $fail ? 1 : 0 );
