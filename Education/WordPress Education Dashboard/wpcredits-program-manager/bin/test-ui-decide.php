<?php
/**
 * WPCPM_UI::decide(): the Decide area (design 2.6, 3.2, 3.3 and 9).
 *
 * What each block pins, and why:
 *
 * - **The markup, in order**: the heading "Decide", one action row holding a decision with no note as its
 *   whole form and a decision with a note as an opener (`type="button"`, `aria-controls`, `aria-expanded`,
 *   printed `hidden`), then after the row one panel per note, printed open, holding the form, the field, the
 *   confirming button that names the act in full and a Cancel printed `hidden`.
 * - **No script, or a script that failed to load (Review Focus 3)**: every submit button and every field is
 *   outside any element carrying `hidden`; the only hidden things are the openers and the Cancels, so no
 *   Cancel that does nothing shows and every decision posts.
 * - **Two items on one page (Review Focus 2)**: two areas share no id, no `aria-controls` target and no
 *   label `for`; every id the helper prints starts with its area's id; each form holds its own item's hidden
 *   inputs; field `name` attributes are exactly what the caller passed.
 * - **A refused note comes back open (Review Focus 1)**: `open` puts `data-wpcpm-open` on that panel only,
 *   its opener says it is expanded, and the refusal sentence is in the open panel, tied to its box.
 * - **A shared panel** (a school's request): two openers controlling one panel, one form, one submit per
 *   sharer with its own `name`, `value` and `data-wpcpm-for`, one Cancel.
 * - **The form's own hooks** (`data-wpcpm-once`, `data-wpcpm-busy`, `data-wpcpm-confirm`, class, method,
 *   action) are printed on the form as given, escaped.
 * - **The script is enqueued only when the area has a panel.**
 *
 * Run: php bin/test-ui-decide.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
$GLOBALS['admin'] = false;

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) {
	$url = str_replace( ' ', '%20', ltrim( (string) $url ) );
	$url = (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );
	return str_replace( array( '&', "'" ), array( '&#038;', '&#039;' ), $url );
}
function is_admin() { return $GLOBALS['admin']; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }

// The script registry, as test-ui.php stands it in: what is registered and what is enqueued is recorded.
$GLOBALS['script_src']       = array();
$GLOBALS['enqueued_scripts'] = array();
function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list && isset( $GLOBALS['style_src'][ $handle ] ); }
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['style_src'][ $handle ] = $src; }
function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list && isset( $GLOBALS['script_src'][ $handle ] ); }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['script_src'][ $handle ] = $src; }
function wp_enqueue_script( $handle ) { $GLOBALS['enqueued_scripts'][] = $handle; }

// What a caller did wrong, as WordPress would report it; the nonce and the referer as WordPress prints them.
$GLOBALS['wrong'] = array();
function _doing_it_wrong( $function_name, $message, $version ) { $GLOBALS['wrong'][] = array( $function_name, $message ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function wp_create_nonce( $action ) { return 'n0nce-' . $action; }
function wp_referer_field( $display = true ) { return '<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=x" />'; }

// Every warning and notice is recorded, so a check can say none was raised.
$GLOBALS['warnings'] = array();
set_error_handler(
	function ( $no, $str ) {
		$GLOBALS['warnings'][] = $str;
		return true;
	}
);

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

if ( ! method_exists( 'WPCPM_UI', 'decide' ) ) {
	ck( 'WPCPM_UI::decide() exists', false, true );
	echo "\n$fail of $checks checks failed\n";
	exit( 1 );
}

/** The markup as a document, for the structural checks. */
function dom_of( $html ) {
	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="test-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	return new DOMXPath( $doc );
}

/** The values of one attribute over the nodes a query finds. */
function attrs_of( DOMXPath $x, $query, $attr ) {
	$out = array();
	foreach ( $x->query( $query ) as $node ) {
		$out[] = $node->getAttribute( $attr );
	}
	return $out;
}

/** The hidden inputs of one item, printed by its callable. */
function hidden_for( $item, $action = 'wpcpm_application_decide' ) {
	return function () use ( $item, $action ) {
		echo '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="wpcpm_application" value="' . $item . '">';
	};
}

/** One application's area: Approve (no note) and Reject (a note). */
function app_area( $item, array $extra = array() ) {
	$form = array(
		'class'           => 'wpcpm-decision',
		'method'          => 'post',
		'action'          => 'https://example.org/wp-admin/admin-post.php',
		'data-wpcpm-once' => true,
		'data-wpcpm-busy' => 'Working',
	);
	$reject = array(
		'key'           => 'reject',
		'label'         => 'Reject',
		'kind'          => 'danger',
		'form_attrs'    => $form,
		'hidden'        => hidden_for( $item ),
		'fields'        => WPCPM_UI::field(
			array(
				'id'      => 'wpcpm_reason-' . $item,
				'name'    => 'wpcpm_reason',
				'label'   => 'Why, for the next Administrator who reads this',
				'type'    => 'textarea',
				'hint'    => 'It is never sent to the applicant.',
				'problem' => (string) ( $extra['problem'] ?? '' ),
			)
		),
		'confirm_label' => 'Reject the application',
		'submit_name'   => 'wpcpm_decision',
		'submit_value'  => 'reject',
		'open'          => ! empty( $extra['open'] ),
	);
	return WPCPM_UI::decide(
		array(
			'id'        => 'wpcpm-decide-app-' . $item,
			'decisions' => array(
				array(
					'key'          => 'approve',
					'label'        => 'Approve',
					'kind'         => 'primary',
					'form_attrs'   => $form,
					'hidden'       => hidden_for( $item ),
					'fields'       => '',
					'submit_name'  => 'wpcpm_decision',
					'submit_value' => 'approve',
				),
				$reject,
			),
		)
	);
}

// ---- The markup, in order ----
$GLOBALS['enqueued_scripts'] = array();
$one    = app_area( 1 );
$form1  = '<form class="wpcpm-decision" method="post" action="https://example.org/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Working">';
$hid1   = '<input type="hidden" name="action" value="wpcpm_application_decide"><input type="hidden" name="wpcpm_application" value="1">';
$field1 = WPCPM_UI::field(
	array(
		'id'    => 'wpcpm_reason-1',
		'name'  => 'wpcpm_reason',
		'label' => 'Why, for the next Administrator who reads this',
		'type'  => 'textarea',
		'hint'  => 'It is never sent to the applicant.',
	)
);
ck(
	'the area: the heading, the row with Approve\'s form and Reject\'s hidden opener, then Reject\'s open panel with its form, the field, the act in full and a hidden Cancel',
	$one,
	'<div class="wpcpm-ui-decide" id="wpcpm-decide-app-1"><h4 class="wpcpm-ui-h">Decide</h4><div class="wpcpm-ui-actions">'
		. $form1 . $hid1 . '<button type="submit" class="wpcpm-btn" name="wpcpm_decision" value="approve">Approve</button></form>'
		. '<button type="button" class="wpcpm-btn wpcpm-btn--danger" aria-controls="wpcpm-decide-app-1-reject-panel" aria-expanded="false" hidden>Reject</button>'
		. '</div>'
		. '<div class="wpcpm-ui-panel" id="wpcpm-decide-app-1-reject-panel" data-wpcpm-panel>'
		. $form1 . $hid1 . $field1
		. '<div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn wpcpm-btn--danger" name="wpcpm_decision" value="reject">Reject the application</button>'
		. '<button type="button" class="wpcpm-btn wpcpm-btn--quiet" hidden data-wpcpm-cancel>Cancel</button></div>'
		. '</form></div></div>'
);

$x = dom_of( $one );
ck( 'the panel is printed without hidden', $x->query( '//*[@data-wpcpm-panel][@hidden]' )->length, 0 );
ck( 'the opener\'s aria-controls names a panel that is on the page', attrs_of( $x, '//*[@aria-controls]', 'aria-controls' ), attrs_of( $x, '//*[@data-wpcpm-panel]', 'id' ) );
ck( 'the panel follows the row, a sibling, not inside it', $x->query( '//div[contains(@class,"wpcpm-ui-actions")]//*[@data-wpcpm-panel]' )->length, 0 );

// ---- Review Focus 3: with no script nothing a person needs is hidden ----
$no_script = function ( $html ) {
	$x = dom_of( $html );
	return array(
		'submits under hidden'         => $x->query( '//button[@type="submit"][ancestor-or-self::*[@hidden]]' )->length,
		'fields under hidden'          => $x->query( '//*[self::textarea or self::select or self::input[not(@type="hidden")]][ancestor-or-self::*[@hidden]]' )->length,
		'labels under hidden'          => $x->query( '//label[ancestor-or-self::*[@hidden]]' )->length,
		'hidden things not an opener or a Cancel' => $x->query( '//*[@hidden][not(@aria-controls) and not(@data-wpcpm-cancel)]' )->length,
		'Cancels shown'                => $x->query( '//*[@data-wpcpm-cancel][not(@hidden)]' )->length,
		'openers shown'                => $x->query( '//*[@aria-controls][not(@hidden)]' )->length,
		'openers whose panel has no submit' => count(
			array_filter(
				attrs_of( $x, '//*[@aria-controls]', 'aria-controls' ),
				function ( $id ) use ( $x ) {
					return 0 === $x->query( '//*[@id="' . $id . '"]//form//button[@type="submit"]' )->length;
				}
			)
		),
	);
};
$zero = array(
	'submits under hidden'         => 0,
	'fields under hidden'          => 0,
	'labels under hidden'          => 0,
	'hidden things not an opener or a Cancel' => 0,
	'Cancels shown'                => 0,
	'openers shown'                => 0,
	'openers whose panel has no submit' => 0,
);
ck( 'Review Focus 3: with no script every submit and field is outside hidden; only openers and Cancels are hidden', $no_script( $one ), $zero );
ck( 'Review Focus 3: every decision has a submit that posts (two submits for two decisions)', $x->query( '//form//button[@type="submit"]' )->length, 2 );

// ---- Review Focus 2: two items on one page ----
$two  = app_area( 1 ) . app_area( 2 );
$x2   = dom_of( $two );
$ids  = attrs_of( $x2, '//*[@id][not(@id="test-root")]', 'id' );
$ctrl = attrs_of( $x2, '//*[@aria-controls]', 'aria-controls' );
$fors = attrs_of( $x2, '//label[@for]', 'for' );
ck( 'Review Focus 2: no id repeats across two areas', count( $ids ) === count( array_unique( $ids ) ) && count( $ids ) > 4, true );
ck( 'Review Focus 2: no aria-controls target repeats', count( $ctrl ), count( array_unique( $ctrl ) ) );
ck( 'Review Focus 2: no label for repeats', array( count( $fors ), count( array_unique( $fors ) ) ), array( 2, 2 ) );
ck( 'Review Focus 2: each opener controls its own item\'s panel', $ctrl, array( 'wpcpm-decide-app-1-reject-panel', 'wpcpm-decide-app-2-reject-panel' ) );
$own = array();
foreach ( array( 1, 2 ) as $item ) {
	foreach ( $x2->query( '//*[@id="wpcpm-decide-app-' . $item . '"]//form' ) as $form ) {
		$values = array();
		foreach ( $x2->query( './/input[@name="wpcpm_application"]', $form ) as $input ) {
			$values[] = $input->getAttribute( 'value' );
		}
		$own[] = implode( ',', $values );
	}
}
ck( 'Review Focus 2: each form (row and panel) holds its own item\'s ID, once', $own, array( '1', '1', '2', '2' ) );
$area_ids = array();
foreach ( $x2->query( '//*[contains(concat(" ", @class, " ")," wpcpm-ui-decide ")]' ) as $area ) {
	$base = $area->getAttribute( 'id' );
	foreach ( $x2->query( './/*[@id][not(ancestor::*[contains(concat(" ", @class, " ")," wpcpm-ui-field ")])]', $area ) as $node ) {
		if ( 0 !== strpos( $node->getAttribute( 'id' ), $base . '-' ) ) {
			$area_ids[] = $node->getAttribute( 'id' );
		}
	}
}
ck( 'every id the helper prints in an area starts with the area\'s id', $area_ids, array() );
$names = array_values( array_unique( attrs_of( $x2, '//*[@name]', 'name' ) ) );
sort( $names );
ck( 'field and submit names are exactly what the caller passed, never suffixed', $names, array( 'action', 'wpcpm_application', 'wpcpm_decision', 'wpcpm_reason' ) );
ck( 'the field ids the caller gave are kept: one box per item', attrs_of( $x2, '//textarea', 'id' ), array( 'wpcpm_reason-1', 'wpcpm_reason-2' ) );

// ---- Review Focus 1: a refused note comes back with its panel open ----
$refused = app_area( 1 ) . app_area( 2, array( 'open' => true, 'problem' => 'Say why in at least 20 characters.' ) );
$x3      = dom_of( $refused );
ck( 'open puts data-wpcpm-open on that panel and on no other', attrs_of( $x3, '//*[@data-wpcpm-open]', 'id' ), array( 'wpcpm-decide-app-2-reject-panel' ) );
ck( 'data-wpcpm-open sits only on panels', $x3->query( '//*[@data-wpcpm-open][not(@data-wpcpm-panel)]' )->length, 0 );
ck( 'the open panel\'s opener says it is expanded; the other says it is not', attrs_of( $x3, '//*[@aria-controls]', 'aria-expanded' ), array( 'false', 'true' ) );
ck( 'the refusal sentence is in the open panel, tied to its box', array(
	$x3->query( '//*[@id="wpcpm-decide-app-2-reject-panel"]//*[@id="' . WPCPM_Form_Errors::problem_id( 'wpcpm_reason-2' ) . '"]' )->length,
	false !== strpos( (string) $x3->query( '//textarea[@id="wpcpm_reason-2"]' )->item( 0 )->getAttribute( 'aria-describedby' ), WPCPM_Form_Errors::problem_id( 'wpcpm_reason-2' ) ),
	$x3->query( '//*[@id="wpcpm-decide-app-1-reject-panel"]//*[@id="' . WPCPM_Form_Errors::problem_id( 'wpcpm_reason-2' ) . '"]' )->length,
), array( 1, true, 0 ) );
ck( 'Review Focus 3 holds with a refused note too', $no_script( $refused ), $zero );
ck( 'with no decision open, no panel carries data-wpcpm-open', $x->query( '//*[@data-wpcpm-open]' )->length, 0 );

// ---- A shared panel: a school's request ----
$req_form = array(
	'class'           => 'wpcpm-request-form',
	'method'          => 'post',
	'action'          => 'https://example.org/wp-admin/admin-post.php',
	'data-wpcpm-once' => true,
);
$GLOBALS['enqueued_scripts'] = array();
$req = WPCPM_UI::decide(
	array(
		'id'        => 'wpcpm-decide-req-7',
		'decisions' => array(
			array(
				'key'           => 'done',
				'label'         => 'Mark as handled',
				'kind'          => 'primary',
				'form_attrs'    => $req_form,
				'hidden'        => function () {
					echo '<input type="hidden" name="wpcpm_request" value="7">';
				},
				'fields'        => WPCPM_UI::field(
					array(
						'id'    => 'wpcpm-request-note-7',
						'name'  => 'wpcpm_request_note',
						'label' => 'What you did about it',
						'type'  => 'textarea',
						'need'  => 'optional',
					)
				),
				'confirm_label' => 'Mark the request as handled',
				'submit_name'   => 'state',
				'submit_value'  => 'done',
			),
			array(
				'key'           => 'declined',
				'label'         => 'Decline',
				'kind'          => 'danger',
				'panel'         => 'done',
				'confirm_label' => 'Decline the request',
				'submit_name'   => 'state',
				'submit_value'  => 'declined',
			),
		),
	)
);
$xr = dom_of( $req );
ck( 'shared: two openers, both controlling the one panel', attrs_of( $xr, '//*[@aria-controls]', 'aria-controls' ), array( 'wpcpm-decide-req-7-done-panel', 'wpcpm-decide-req-7-done-panel' ) );
ck( 'shared: the openers keep their own words and kinds', array( attrs_of( $xr, '//*[@aria-controls]', 'class' ), array_map( 'trim', array_map( function ( $n ) { return $n->textContent; }, iterator_to_array( $xr->query( '//*[@aria-controls]' ) ) ) ) ), array( array( 'wpcpm-btn', 'wpcpm-btn wpcpm-btn--danger' ), array( 'Mark as handled', 'Decline' ) ) );
ck( 'shared: each opener carries data-wpcpm-for its own decision\'s key, so the script pairs it with its submit by key', attrs_of( $xr, '//*[@aria-controls]', 'data-wpcpm-for' ), array( 'done', 'declined' ) );
ck(
	'shared: the opener\'s exact markup, the key after hidden',
	false !== strpos( $req, '<button type="button" class="wpcpm-btn wpcpm-btn--danger" aria-controls="wpcpm-decide-req-7-done-panel" aria-expanded="false" hidden data-wpcpm-for="declined">Decline</button>' ),
	true
);
ck( 'an opener of a panel with one decision carries no data-wpcpm-for', $x->query( '//*[@aria-controls][@data-wpcpm-for]' )->length, 0 );
ck( 'shared: one panel, one form, one field, one Cancel', array( $xr->query( '//*[@data-wpcpm-panel]' )->length, $xr->query( '//form' )->length, $xr->query( '//textarea' )->length, $xr->query( '//*[@data-wpcpm-cancel]' )->length ), array( 1, 1, 1, 1 ) );
ck( 'shared: the form holds the item\'s hidden input once', $xr->query( '//form//input[@name="wpcpm_request"][@value="7"]' )->length, 1 );
$subs = array();
foreach ( $xr->query( '//form//button[@type="submit"]' ) as $b ) {
	$subs[] = array( $b->getAttribute( 'name' ), $b->getAttribute( 'value' ), $b->getAttribute( 'data-wpcpm-for' ), $b->getAttribute( 'class' ), trim( $b->textContent ) );
}
ck( 'shared: two submits, state done and declined, each with data-wpcpm-for its decision, its kind and the act in full', $subs, array(
	array( 'state', 'done', 'done', 'wpcpm-btn', 'Mark the request as handled' ),
	array( 'state', 'declined', 'declined', 'wpcpm-btn wpcpm-btn--danger', 'Decline the request' ),
) );
ck( 'shared: Cancel comes after both submits, in one action row', $xr->query( '//form/div[contains(@class,"wpcpm-ui-actions")]/*[3][@data-wpcpm-cancel]' )->length, 1 );
ck( 'shared: Review Focus 3 holds', $no_script( $req ), $zero );
ck( 'shared: the script is enqueued', $GLOBALS['enqueued_scripts'], array( 'wpcpm-parts' ) );

// A sharer naming a panel that does not exist keeps its own: here no fields, so its whole form in the row.
$lone = WPCPM_UI::decide(
	array(
		'id'        => 'wpcpm-decide-x-1',
		'decisions' => array(
			array(
				'key'        => 'go',
				'label'      => 'Go',
				'panel'      => 'nowhere',
				'form_attrs' => array( 'method' => 'post' ),
			),
		),
	)
);
ck( 'a panel key that names no decision with a note is ignored: the decision posts from the row', array( dom_of( $lone )->query( '//div[contains(@class,"wpcpm-ui-actions")]/form/button[@type="submit"]' )->length, dom_of( $lone )->query( '//*[@aria-controls]' )->length ), array( 1, 0 ) );

// ---- The form's own hooks, printed as given ----
$hooks = WPCPM_UI::decide(
	array(
		'id'        => 'wpcpm-decide-app-9',
		'decisions' => array(
			array(
				'key'          => 'approve',
				'label'        => 'Approve',
				'form_attrs'   => array(
					'class'              => 'wpcpm-decision wpcpm-once',
					'method'             => 'post',
					'action'             => 'https://example.org/wp-admin/admin-post.php',
					'data-wpcpm-once'    => true,
					'data-wpcpm-busy'    => 'Working',
					'data-wpcpm-confirm' => 'Approve "this" application?',
				),
				'hidden'       => hidden_for( 9 ),
				'submit_name'  => 'wpcpm_decision',
				'submit_value' => 'approve',
			),
		),
	)
);
ck( 'the form attributes are printed on the form unchanged, in the order given, escaped', false !== strpos( $hooks, '<form class="wpcpm-decision wpcpm-once" method="post" action="https://example.org/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Working" data-wpcpm-confirm="Approve &quot;this&quot; application?">' ), true );
ck( 'an attribute that is not a form\'s is not printed', false !== strpos( WPCPM_UI::decide( array( 'id' => 'a', 'decisions' => array( array( 'key' => 'k', 'label' => 'K', 'form_attrs' => array( 'onsubmit' => 'x()', 'method' => 'post' ) ) ) ) ), 'onsubmit' ), false );
ck( 'a decision\'s confirm is the browser question on its submit', false !== strpos( WPCPM_UI::decide( array( 'id' => 'a', 'decisions' => array( array( 'key' => 'k', 'label' => 'Reject as spam', 'kind' => 'danger', 'confirm' => 'Reject as spam?' ) ) ) ), '<button type="submit" class="wpcpm-btn wpcpm-btn--danger" data-wpcpm-confirm="Reject as spam?">Reject as spam</button>' ), true );

// ---- The script: only with a panel ----
$GLOBALS['enqueued_scripts'] = array();
app_area( 3 );
ck( 'an area with a panel enqueues the parts\' script', $GLOBALS['enqueued_scripts'], array( 'wpcpm-parts' ) );
$GLOBALS['enqueued_scripts'] = array();
$GLOBALS['script_src']       = array();
WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-app-4', 'decisions' => array( array( 'key' => 'approve', 'label' => 'Approve', 'form_attrs' => array( 'method' => 'post' ) ) ) ) );
ck( 'an area with no panel does not', $GLOBALS['enqueued_scripts'], array() );
ck( 'an area with no panel prints no opener and no Cancel', false === strpos( $hooks, 'aria-controls' ) && false === strpos( $hooks, 'data-wpcpm-cancel' ), true );

// ---- The heading and the side ----
ck( 'the title replaces "Decide"', false !== strpos( WPCPM_UI::decide( array( 'id' => 'a', 'title' => 'Your decision', 'decisions' => array() ) ), '<h4 class="wpcpm-ui-h">Your decision</h4>' ), true );
ck( 'the heading level can be given', false !== strpos( WPCPM_UI::decide( array( 'id' => 'a', 'level' => 'h5', 'decisions' => array() ) ), '<h5 class="wpcpm-ui-h">Decide</h5>' ), true );
// Fix round 1, item 3: an area with no usable id prints nothing and says why; a key is normalised before it is an id.
$GLOBALS['wrong'] = array();
ck( 'an area\'s id holding a character that is not a letter, a digit, a hyphen or an underscore is refused: nothing printed, the caller told', array( WPCPM_UI::decide( array( 'id' => 'a"b', 'decisions' => array() ) ), count( $GLOBALS['wrong'] ), $GLOBALS['wrong'][0][0] ?? '' ), array( '', 1, 'WPCPM_UI::decide' ) );
$GLOBALS['wrong'] = array();
ck( 'an empty or missing id is refused the same way', array( WPCPM_UI::decide( array( 'id' => '', 'decisions' => array() ) ), WPCPM_UI::decide( array( 'decisions' => array() ) ), count( $GLOBALS['wrong'] ) ), array( '', '', 2 ) );
// Carried from Task 7's re-review: `$` matches before a final line break, so the pattern must end at `\z`.
$GLOBALS['wrong'] = array();
ck( 'an id ending in a line break is refused the same way', array( WPCPM_UI::decide( array( 'id' => "wpcpm-decide-app-8\n", 'decisions' => array() ) ), count( $GLOBALS['wrong'] ) ), array( '', 1 ) );
// Follow-up round, item 5: the form's attribute names and the data attributes' names end at `\z` too.
$nl_form = WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-nl', 'decisions' => array( array( 'key' => 'approve', 'label' => 'Approve', 'form_attrs' => array( "class\n" => 'wpcpm-x', "data-wpcpm-once\n" => true, 'method' => 'post' ) ) ) ) );
ck( 'a form attribute whose name ends in a line break is dropped, and the others are kept', array( false !== strpos( $nl_form, '<form method="post">' ), strpos( $nl_form, 'wpcpm-x' ), strpos( $nl_form, 'data-wpcpm-once' ) ), array( true, false, false ) );
$nl_data = WPCPM_UI::button( array( 'label' => 'Go', 'type' => 'button', 'data' => array( "wpcpm-for\n" => 'a', 'wpcpm-cancel' => true ) ) );
ck( 'a data attribute whose name ends in a line break is dropped, and the others are kept', array( strpos( $nl_data, 'data-wpcpm-for' ), false !== strpos( $nl_data, ' data-wpcpm-cancel>' ) ), array( false, true ) );
$norm = WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-app-8', 'decisions' => array( array( 'key' => 'Re"ject Now', 'label' => 'Reject', 'fields' => '<div class="wpcpm-ui-field"></div>' ) ) ) );
ck( 'a decision key is normalised by sanitize_key() before it goes into an id', array( false !== strpos( $norm, 'id="wpcpm-decide-app-8-rejectnow-panel"' ), false !== strpos( $norm, 'aria-controls="wpcpm-decide-app-8-rejectnow-panel"' ) ), array( true, true ) );
$GLOBALS['wrong'] = array();
ck( 'a decision whose key normalises to nothing is left out, and the caller told', array( false === strpos( WPCPM_UI::decide( array( 'id' => 'a', 'decisions' => array( array( 'key' => '!!', 'label' => 'Gone' ) ) ) ), 'Gone' ), count( $GLOBALS['wrong'] ) ), array( true, 1 ) );
$admin = WPCPM_UI::decide(
	array(
		'id'        => 'wpcpm-decide-app-5',
		'side'      => 'admin',
		'decisions' => array(
			array( 'key' => 'approve', 'label' => 'Approve', 'kind' => 'primary' ),
			array( 'key' => 'reject', 'label' => 'Reject', 'kind' => 'danger', 'fields' => '<div class="wpcpm-ui-field"></div>' ),
		),
	)
);
ck( 'side admin: the buttons are WordPress\'s own', array( substr_count( $admin, 'class="button button-primary"' ), substr_count( $admin, 'class="button wpcpm-btn--danger"' ), substr_count( $admin, 'class="button wpcpm-btn--quiet"' ) ), array( 1, 2, 1 ) );
ck( 'the Cancel and the opener carry no id of their own and no name, so they never post', dom_of( $one )->query( '//button[@type="button"][@name or @id]' )->length, 0 );

// ---- Fix round 1 ----
// Item 1: a shared panel's form is its owner's, whatever the order the decisions are listed in.
$req_decisions = function ( $sharer_first ) {
	$owner  = array(
		'key'           => 'done',
		'label'         => 'Mark as handled',
		'kind'          => 'primary',
		'form_attrs'    => array( 'method' => 'post', 'action' => 'https://example.org/wp-admin/admin-post.php' ),
		'hidden'        => function () {
			echo '<input type="hidden" name="wpcpm_request" value="7">';
		},
		'fields'        => '<div class="wpcpm-ui-field"><textarea id="wpcpm-request-note-7" name="wpcpm_request_note"></textarea></div>',
		'confirm_label' => 'Mark the request as handled',
		'submit_name'   => 'state',
		'submit_value'  => 'done',
	);
	$sharer = array(
		'key'           => 'declined',
		'label'         => 'Decline',
		'kind'          => 'danger',
		'panel'         => 'done',
		'confirm_label' => 'Decline the request',
		'submit_name'   => 'state',
		'submit_value'  => 'declined',
	);
	return WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-req-7', 'decisions' => $sharer_first ? array( $sharer, $owner ) : array( $owner, $sharer ) ) );
};
$form_head = function ( $html ) {
	$x    = dom_of( $html );
	$form = $x->query( '//*[@data-wpcpm-panel]/form' )->item( 0 );
	return array(
		$form ? $form->getAttribute( 'method' ) : null,
		$form ? $form->getAttribute( 'action' ) : null,
		$x->query( '//*[@data-wpcpm-panel]/form/input[@name="wpcpm_request"][@value="7"]' )->length,
		$x->query( '//*[@data-wpcpm-panel]/form//textarea[@name="wpcpm_request_note"]' )->length,
		attrs_of( $x, '//*[@data-wpcpm-panel]/form//button[@type="submit"]', 'value' ),
		attrs_of( $x, '//*[@data-wpcpm-panel]', 'id' ),
	);
};
$GLOBALS['warnings'] = array();
$owner_first         = $form_head( $req_decisions( false ) );
$sharer_first        = $form_head( $req_decisions( true ) );
ck( 'item 1: listed owner first, the panel\'s form is the owner\'s', $owner_first, array( 'post', 'https://example.org/wp-admin/admin-post.php', 1, 1, array( 'done', 'declined' ), array( 'wpcpm-decide-req-7-done-panel' ) ) );
// Task 13d fix round 1: the row and the panel's submits are in the one order (primary first), whoever was listed first.
ck( 'item 1: listed sharer first, the same form: method, action, hidden inputs, the field and both submits, in the one order', $sharer_first, array( 'post', 'https://example.org/wp-admin/admin-post.php', 1, 1, array( 'done', 'declined' ), array( 'wpcpm-decide-req-7-done-panel' ) ) );
ck( 'item 1: and no warning is raised', $GLOBALS['warnings'], array() );

// Item 2: a key used twice: the second decision is left out, and the caller told.
$GLOBALS['wrong'] = array();
$dup              = WPCPM_UI::decide(
	array(
		'id'        => 'wpcpm-decide-app-11',
		'decisions' => array(
			array( 'key' => 'approve', 'label' => 'Approve', 'form_attrs' => array( 'method' => 'post' ) ),
			array( 'key' => 'approve', 'label' => 'Approve again', 'form_attrs' => array( 'method' => 'post' ) ),
		),
	)
);
ck( 'item 2: a key used twice in one area keeps the first decision only, and _doing_it_wrong() says so', array( substr_count( $dup, '<form' ), false === strpos( $dup, 'Approve again' ), count( $GLOBALS['wrong'] ) ), array( 1, true, 1 ) );

// Item 4: a hidden callable that throws leaves the output buffers as it found them.
$level  = ob_get_level();
$thrown = false;
try {
	WPCPM_UI::decide(
		array(
			'id'        => 'wpcpm-decide-app-12',
			'decisions' => array(
				array(
					'key'    => 'approve',
					'label'  => 'Approve',
					'hidden' => function () {
						echo '<input type="hidden" name="half" value="1">';
						throw new RuntimeException( 'the nonce could not be made' );
					},
				),
			),
		)
	);
} catch ( RuntimeException $e ) {
	$thrown = true;
}
ck( 'item 4: a hidden callable that throws: the buffer level after equals the level before', array( ob_get_level(), $thrown ), array( $level, true ) );

// Item 5: two decisions open: only the first listed keeps it.
$two_open = dom_of(
	WPCPM_UI::decide(
		array(
			'id'        => 'wpcpm-decide-app-13',
			'decisions' => array(
				array( 'key' => 'ask', 'label' => 'Ask a question', 'kind' => 'secondary', 'fields' => '<div class="wpcpm-ui-field"></div>', 'open' => true ),
				array( 'key' => 'reject', 'label' => 'Reject', 'kind' => 'danger', 'fields' => '<div class="wpcpm-ui-field"></div>', 'open' => true ),
			),
		)
	)
);
ck( 'item 5: with two decisions open, only the first listed panel is open and only its opener expanded', array( attrs_of( $two_open, '//*[@data-wpcpm-open]', 'id' ), attrs_of( $two_open, '//*[@aria-controls]', 'aria-expanded' ) ), array( array( 'wpcpm-decide-app-13-ask-panel' ), array( 'true', 'false' ) ) );

// Item 6: a caller's form id is not printed.
ck( 'item 6: a form id given in form_attrs is not printed (every id in the area starts with its id)', false === strpos( WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-app-14', 'decisions' => array( array( 'key' => 'k', 'label' => 'K', 'form_attrs' => array( 'id' => 'my-form', 'method' => 'post' ) ) ) ) ), 'my-form' ), true );

// Item 7: the nonce and the referer, with no id.
ck( 'item 7: nonce() prints the nonce input with its name and value and no id, then the referer', WPCPM_UI::nonce( 'wpcpm_decide_1' ), '<input type="hidden" name="_wpnonce" value="n0nce-wpcpm_decide_1"><input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=x" />' );
ck( 'item 7: a name of its own, and no referer when not asked', WPCPM_UI::nonce( 'wpcpm_decide_1', 'wpcpm_nonce', false ), '<input type="hidden" name="wpcpm_nonce" value="n0nce-wpcpm_decide_1">' );
ck( 'item 7: nothing nonce() returns carries an id', 0 === preg_match( '/\sid=/', WPCPM_UI::nonce( 'a"b', 'n"m' ) ), true );
ck( 'item 7: its action and name are escaped', false !== strpos( WPCPM_UI::nonce( 'a"b', 'n"m', false ), 'name="n&quot;m" value="n0nce-a&quot;b"' ), true );

// Item 8: the design's class list names the heading the helper prints.
$design = (string) file_get_contents( __DIR__ . '/../docs/specs/2026-11-13-ui-shared-parts-design.md' );
ck( 'item 8: the design no longer names wpcpm-ui-decide__title', false === strpos( $design, '`wpcpm-ui-decide` (`__title`)' ), true );

// Item 9: a hidden callable that prints nothing: the form is still printed with its field and submit.
$GLOBALS['warnings'] = array();
$quiet               = dom_of(
	WPCPM_UI::decide(
		array(
			'id'        => 'wpcpm-decide-app-15',
			'decisions' => array(
				array(
					'key'    => 'reject',
					'label'  => 'Reject',
					'kind'   => 'danger',
					'hidden' => function () {},
					'fields' => '<div class="wpcpm-ui-field"><textarea id="wpcpm_reason-15" name="wpcpm_reason"></textarea></div>',
				),
			),
		)
	)
);
ck( 'item 9: a hidden callable that prints nothing: the panel\'s form, its field and its submit are printed, no warning', array( $quiet->query( '//*[@data-wpcpm-panel]/form//textarea' )->length, $quiet->query( '//*[@data-wpcpm-panel]/form//button[@type="submit"]' )->length, $GLOBALS['warnings'] ), array( 1, 1, array() ) );

// Task 13c fix round 1: an area may name its item to a screen reader (`context`), on every opener and every submit that
// acts at once, never on a panel's confirming button or its dismissing button; and each panel's dismissing button may
// carry words of its own (`cancel_label`), its owner's on a shared panel. An area that passes neither prints what it
// printed before (the first check of this suite pins `app_area( 1 )` whole).
$ctx_area = function ( array $extra ) {
	$panel = array(
		'key'           => 'reject',
		'label'         => 'Reject',
		'kind'          => 'danger',
		'hidden'        => hidden_for( 21 ),
		'fields'        => '<div class="wpcpm-ui-field"><textarea id="wpcpm_reason-21" name="wpcpm_reason"></textarea></div>',
		'confirm_label' => 'Reject the application',
	);
	return WPCPM_UI::decide(
		array_merge(
			array(
				'id'        => 'wpcpm-decide-app-21',
				'decisions' => array(
					array(
						'key'    => 'approve',
						'label'  => 'Approve',
						'kind'   => 'primary',
						'hidden' => hidden_for( 21 ),
						'fields' => '',
					),
					array_merge( $panel, $extra['panel'] ?? array() ),
					array(
						'key'   => 'spam',
						'label' => 'Spam',
						'kind'  => 'danger',
						'panel' => 'reject',
					) + ( $extra['sharer'] ?? array() ),
				),
			),
			$extra['area'] ?? array()
		)
	);
};
$ctx_plain = $ctx_area( array() );
$ctx_named = $ctx_area( array( 'area' => array( 'context' => 'Uni <b>& "Co"' ) ) );
$ctx_sr    = '<span class="screen-reader-text"> Uni &lt;b&gt;&amp; &quot;Co&quot;</span>';
ck(
	'context: every opener and every submit that acts at once names the item for a screen reader, escaped; a panel\'s confirming buttons and its Cancel do not; nothing else changes',
	array(
		substr_count( $ctx_named, $ctx_sr ),
		false !== strpos( $ctx_named, '>Approve' . $ctx_sr . '</button></form>' ),
		false !== strpos( $ctx_named, 'hidden data-wpcpm-for="reject">Reject' . $ctx_sr . '</button>' ),
		false !== strpos( $ctx_named, 'hidden data-wpcpm-for="spam">Spam' . $ctx_sr . '</button>' ),
		false !== strpos( $ctx_named, '>Reject the application</button>' ),
		false !== strpos( $ctx_named, 'data-wpcpm-cancel>Cancel</button>' ),
		str_replace( $ctx_sr, '', $ctx_named ) === $ctx_plain,
	),
	array( 3, true, true, true, true, true, true )
);
ck(
	'context left out, or empty, prints exactly what the area printed before',
	array( $ctx_area( array( 'area' => array( 'context' => '' ) ) ) === $ctx_plain, false === strpos( $ctx_plain, 'screen-reader-text' ) ),
	array( true, true )
);
$ctx_keep  = $ctx_area( array( 'panel' => array( 'cancel_label' => 'Keep <it> & go' ), 'sharer' => array( 'cancel_label' => 'Ignored' ) ) );
ck(
	'cancel_label: the panel\'s dismissing button carries its owner\'s words, escaped; a decision sharing the panel does not change them; with none, "Cancel"',
	array(
		false !== strpos( $ctx_keep, ' hidden data-wpcpm-cancel>Keep &lt;it&gt; &amp; go</button>' ),
		strpos( $ctx_keep, 'Ignored' ),
		str_replace( 'Keep &lt;it&gt; &amp; go', 'Cancel', $ctx_keep ) === $ctx_plain,
	),
	array( true, false, true )
);

// ---- Task 13d fix round 1: the row's order belongs to decide(): primary, then secondary, then danger, each kind in
// the order given; the panels follow their decisions; a shared panel stays with its owner. ----
$ord_one = function ( $key, $kind, $note = false, $extra = array() ) {
	return array_merge(
		array(
			'key'        => $key,
			'label'      => ucfirst( $key ),
			'kind'       => $kind,
			'form_attrs' => array( 'method' => 'post' ),
			'fields'     => $note ? '<div class="wpcpm-ui-field"><textarea id="n-' . $key . '" name="n_' . $key . '"></textarea></div>' : '',
		),
		$extra
	);
};
$ord_row = function ( $html ) {
	$x   = dom_of( $html );
	$out = array();
	foreach ( $x->query( '//div[@class="wpcpm-ui-decide"]/div[@class="wpcpm-ui-actions"]/*' ) as $n ) {
		$b     = 'button' === $n->nodeName ? $n : $x->query( './/button', $n )->item( 0 );
		$out[] = trim( $b->textContent );
	}
	return $out;
};
$ord_panels = function ( $html ) {
	return attrs_of( dom_of( $html ), '//*[@data-wpcpm-panel]', 'id' );
};
$ord_mixed = WPCPM_UI::decide(
	array(
		'id'        => 'ord-1',
		'decisions' => array(
			$ord_one( 'spam', 'danger' ),
			$ord_one( 'reject', 'danger', true ),
			$ord_one( 'ask', 'secondary', true ),
			$ord_one( 'approve', 'primary' ),
			$ord_one( 'reopen', 'secondary' ),
		),
	)
);
ck( 'callers giving danger before secondary and the primary last: the row prints the primary, then the secondary ones, then the red ones, each kind in the order given; the panels in the same order', array( $ord_row( $ord_mixed ), $ord_panels( $ord_mixed ) ), array( array( 'Approve', 'Ask', 'Reopen', 'Spam', 'Reject' ), array( 'ord-1-ask-panel', 'ord-1-reject-panel' ) ) );
$ord_shared = WPCPM_UI::decide(
	array(
		'id'        => 'ord-2',
		'decisions' => array(
			$ord_one( 'decline', 'danger', false, array( 'panel' => 'done' ) ),
			$ord_one( 'done', 'secondary', true ),
			$ord_one( 'go', 'primary' ),
		),
	)
);
ck( 'a shared panel stays with its owner: one panel, the owner\'s form, the owner\'s submit before the sharer\'s', array(
	$ord_row( $ord_shared ),
	$ord_panels( $ord_shared ),
	attrs_of( dom_of( $ord_shared ), '//*[@data-wpcpm-panel]//button[@type="submit"]', 'data-wpcpm-for' ),
), array( array( 'Go', 'Done', 'Decline' ), array( 'ord-2-done-panel' ), array( 'done', 'decline' ) ) );
$ord_kept = WPCPM_UI::decide(
	array(
		'id'        => 'ord-3',
		'decisions' => array(
			$ord_one( 'approve', 'primary', false, array( 'confirm' => 'Sure?' ) ),
			$ord_one( 'ask', 'secondary', true ),
			$ord_one( 'reopen', 'secondary' ),
			$ord_one( 'reject', 'danger', true, array( 'open' => true ) ),
			$ord_one( 'spam', 'danger' ),
		),
	)
);
// The digest is of this area as decide() printed it at 7c06eb89, before the row was ordered by kind.
ck( 'an area already in order prints byte for byte what it printed before', md5( $ord_kept ), 'f15c5169499429fb10247bd30625e03a' );

echo "\n" . ( $fail ? "$fail of $checks checks failed" : "all $checks checks passed" ) . "\n";
exit( $fail ? 1 : 0 );
