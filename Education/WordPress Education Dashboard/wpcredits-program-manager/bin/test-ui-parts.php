<?php
/**
 * WPCPM_UI: the small parts' markup (design 2.1, 2.2, 2.5, 2.11 and 3.3).
 *
 * What each block pins, and why:
 *
 * - **The button prints the frame's classes on the front end and WordPress's own in wp-admin.** Each kind
 *   (primary, secondary, danger, quiet, quiet-danger) has its classes on both sides; an unknown kind is
 *   primary. `side` says which, and defaults from `is_admin()`.
 * - **A button carries its hooks as attributes the plugin already reads**: `type`, `name`, `value`, `form`,
 *   `data-wpcpm-confirm`, an opener's `aria-controls` and `aria-expanded`, `aria-label`, `hidden` and `id`;
 *   a link prints an `a` with its `href` escaped; words for a screen reader sit inside the control.
 * - **Everything printed is escaped**, the label and the context included.
 * - **The tag, the chip, the heading, the intro and the action row** print their own classes; the tag takes
 *   a tone only from red, amber and green; the row keeps the buttons in the order given and puts the
 *   consequence last.
 *
 * - **Every attribute a caller feeds is escaped**: a double quote and `<` through confirm, aria_label, controls,
 *   id, value and form of the button, and the heading's id, come out as entities.
 * - **A link prints no name, value or form** (they belong to a form control); its id it keeps.
 * - **The field** (design 2.4): the label over the box with `for`, Required or Optional in words, the control
 *   with the same id and name, the hint under it tied by `aria-describedby`, the refusal markup exactly what
 *   WPCPM_Form_Errors prints, the width modifiers, escaping, and none of the legacy `wpcpm-field` classes.
 *
 * - **The folds, the item header, the table and the list row** (design 2.3, 2.7, 2.8, 2.10): a card head
 *   prints the phrase and no count, or the count when there is no phrase; the tag, the cue and the help slot
 *   sit in the end group in that order; the chevron is the last child and hidden from a screen reader, or
 *   the external icon when the row leaves; an inner fold opens with `open`; the item header's ref is a
 *   `code`, its tag the facts line's first child, an `html` fact printed as given and a plain one escaped;
 *   a table prints its action column's screen-reader name, each cell's `data-label` and kind class; a row
 *   with a form prints it under the line in the same `li`, with its id.
 *
 * Run: php bin/test-ui-parts.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['admin'] = false;

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
// WordPress's own: a text area's value is escaped whole, a typed-out entity included, where esc_html() leaves an
// entity as it is and the box would show the character instead of what was typed. Recorded, so a check can ask
// which one a box's value went through.
function esc_textarea( $s ) { $GLOBALS['esc_textarea'][] = (string) $s; return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) {
	$url = str_replace( ' ', '%20', ltrim( (string) $url ) );
	$url = (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );
	return str_replace( array( '&', "'" ), array( '&#038;', '&#039;' ), $url );
}
function is_admin() { return $GLOBALS['admin']; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return esc_html( $s ); }

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

// The default side follows is_admin().
ck( 'front end: the primary button', WPCPM_UI::button( array( 'label' => 'Approve' ) ), '<button type="submit" class="wpcpm-btn">Approve</button>' );
$GLOBALS['admin'] = true;
ck( 'wp-admin: the primary button', WPCPM_UI::button( array( 'label' => 'Approve' ) ), '<button type="submit" class="button button-primary">Approve</button>' );
$GLOBALS['admin'] = false;
ck( 'side admin on the front end', WPCPM_UI::button( array( 'label' => 'Approve', 'side' => 'admin' ) ), '<button type="submit" class="button button-primary">Approve</button>' );
$GLOBALS['admin'] = true;
ck( 'side front in wp-admin', WPCPM_UI::button( array( 'label' => 'Approve', 'side' => 'front' ) ), '<button type="submit" class="wpcpm-btn">Approve</button>' );
$GLOBALS['admin'] = false;

$classes = array(
	'primary'      => array( 'wpcpm-btn', 'button button-primary' ),
	'secondary'    => array( 'wpcpm-btn wpcpm-btn--secondary', 'button' ),
	'danger'       => array( 'wpcpm-btn wpcpm-btn--danger', 'button wpcpm-btn--danger' ),
	'quiet'        => array( 'wpcpm-btn wpcpm-btn--quiet', 'button wpcpm-btn--quiet' ),
	'quiet-danger' => array( 'wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger', 'button wpcpm-btn--quiet wpcpm-btn--danger' ),
	'nonsense'     => array( 'wpcpm-btn', 'button button-primary' ),
);
foreach ( $classes as $kind => $pair ) {
	ck( "front end: kind $kind", WPCPM_UI::button( array( 'label' => 'Go', 'kind' => $kind, 'side' => 'front' ) ), '<button type="submit" class="' . $pair[0] . '">Go</button>' );
	ck( "wp-admin: kind $kind", WPCPM_UI::button( array( 'label' => 'Go', 'kind' => $kind, 'side' => 'admin' ) ), '<button type="submit" class="' . $pair[1] . '">Go</button>' );
}

ck( 'an opener: aria-controls and aria-expanded false', WPCPM_UI::button( array( 'label' => 'Reject', 'kind' => 'danger', 'type' => 'button', 'controls' => 'p1', 'side' => 'front' ) ), '<button type="button" class="wpcpm-btn wpcpm-btn--danger" aria-controls="p1" aria-expanded="false">Reject</button>' );
ck( 'an opener held open: aria-expanded true', false !== strpos( WPCPM_UI::button( array( 'label' => 'Reject', 'controls' => 'p1', 'expanded' => true, 'side' => 'front' ) ), 'aria-controls="p1" aria-expanded="true"' ), true );
ck( 'no controls, no aria-expanded', false === strpos( WPCPM_UI::button( array( 'label' => 'Go', 'expanded' => true ) ), 'aria-expanded' ), true );
ck( 'type button', 0 === strpos( WPCPM_UI::button( array( 'label' => 'Go', 'type' => 'button' ) ), '<button type="button"' ), true );
ck( 'an unknown type is submit', 0 === strpos( WPCPM_UI::button( array( 'label' => 'Go', 'type' => 'reset' ) ), '<button type="submit"' ), true );
ck( 'hidden prints the attribute', WPCPM_UI::button( array( 'label' => 'Go', 'hidden' => true, 'side' => 'front' ) ), '<button type="submit" class="wpcpm-btn" hidden>Go</button>' );
ck( 'id, name, value and form', WPCPM_UI::button( array( 'label' => 'Go', 'id' => 'b1', 'name' => 'act', 'value' => 'approve', 'form' => 'f1', 'side' => 'front' ) ), '<button type="submit" class="wpcpm-btn" id="b1" name="act" value="approve" form="f1">Go</button>' );
ck( 'confirm prints data-wpcpm-confirm', false !== strpos( WPCPM_UI::button( array( 'label' => 'Reject', 'confirm' => 'Reject it?' ) ), ' data-wpcpm-confirm="Reject it?"' ), true );
ck( 'aria_label', false !== strpos( WPCPM_UI::button( array( 'label' => 'x', 'aria_label' => 'Remove Example School' ) ), ' aria-label="Remove Example School"' ), true );
ck( 'context sits inside the control, after the label', WPCPM_UI::button( array( 'label' => 'Remove', 'context' => 'Example School', 'side' => 'front' ) ), '<button type="submit" class="wpcpm-btn">Remove<span class="screen-reader-text"> Example School</span></button>' );
ck( 'href prints a link', WPCPM_UI::button( array( 'label' => 'Open', 'kind' => 'secondary', 'href' => 'https://example.org/a?b=1&c=2', 'side' => 'front' ) ), '<a class="wpcpm-btn wpcpm-btn--secondary" href="https://example.org/a?b=1&#038;c=2">Open</a>' );
ck( 'a link has no type', false === strpos( WPCPM_UI::button( array( 'label' => 'Open', 'href' => 'https://example.org/' ) ), 'type=' ), true );
ck( 'a label is escaped', WPCPM_UI::button( array( 'label' => '<b>Go</b>', 'side' => 'front' ) ), '<button type="submit" class="wpcpm-btn">&lt;b&gt;Go&lt;/b&gt;</button>' );
ck( 'attributes are escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'name' => 'a"b' ) ), 'name="a&quot;b"' ), true );
ck( 'context is escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'context' => '<i>' ) ), '&lt;i&gt;' ), true );

ck( 'tag with a tone', WPCPM_UI::tag( 'Waiting since today', 'red' ), '<span class="wpcpm-status wpcpm-status--red">Waiting since today</span>' );
ck( 'tag amber', WPCPM_UI::tag( 'Soon', 'amber' ), '<span class="wpcpm-status wpcpm-status--amber">Soon</span>' );
ck( 'tag green', WPCPM_UI::tag( 'Done', 'green' ), '<span class="wpcpm-status wpcpm-status--green">Done</span>' );
ck( 'tag without a tone', WPCPM_UI::tag( 'New' ), '<span class="wpcpm-status">New</span>' );
ck( 'tag with another tone is neutral', WPCPM_UI::tag( 'New', 'purple' ), '<span class="wpcpm-status">New</span>' );
ck( 'tag escapes its words', WPCPM_UI::tag( '<b>', '' ), '<span class="wpcpm-status">&lt;b&gt;</span>' );
ck( 'chip', WPCPM_UI::chip( 'Design' ), '<span class="wpcpm-ui-chip">Design</span>' );
ck( 'chip escapes its words', WPCPM_UI::chip( 'A & B' ), '<span class="wpcpm-ui-chip">A &amp; B</span>' );
ck( 'heading defaults to h4', WPCPM_UI::heading( 'Who is it for' ), '<h4 class="wpcpm-ui-h">Who is it for</h4>' );
ck( 'heading with a level and an id', WPCPM_UI::heading( 'Who', 'h3', 'who-1' ), '<h3 class="wpcpm-ui-h" id="who-1">Who</h3>' );
ck( 'heading falls back to h4 for a non-heading', WPCPM_UI::heading( 'Who', 'script' ), '<h4 class="wpcpm-ui-h">Who</h4>' );
ck( 'heading escapes', WPCPM_UI::heading( '<i>' ), '<h4 class="wpcpm-ui-h">&lt;i&gt;</h4>' );
ck( 'intro', WPCPM_UI::intro( 'Say why.' ), '<p class="wpcpm-ui-intro">Say why.</p>' );
ck( 'intro escapes', WPCPM_UI::intro( '<i>' ), '<p class="wpcpm-ui-intro">&lt;i&gt;</p>' );
// The muted note a card prints under a list it cut, "N more are waiting" (Task 14 fix round 1): the same line, muted.
ck( 'intro, muted: the muted modifier after the class; any other tone is the plain line', array( WPCPM_UI::intro( '3 more <b>wait</b>.', 'muted' ), WPCPM_UI::intro( 'Say why.', 'loud" x' ) ), array( '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">3 more &lt;b&gt;wait&lt;/b&gt;.</p>', '<p class="wpcpm-ui-intro">Say why.</p>' ) );

$a = WPCPM_UI::button( array( 'label' => 'Approve', 'side' => 'front' ) );
$b = WPCPM_UI::button( array( 'label' => 'Reject', 'kind' => 'danger', 'side' => 'front' ) );
$row = WPCPM_UI::actions( array( $a, $b ), 'They are emailed.' );
ck( 'the action row: buttons in order, then the consequence', $row, '<div class="wpcpm-ui-actions">' . $a . $b . '<p class="wpcpm-ui-consequence">They are emailed.</p></div>' );
ck( 'the action row without a consequence', WPCPM_UI::actions( array( $a ) ), '<div class="wpcpm-ui-actions">' . $a . '</div>' );
ck( 'the consequence is escaped', false !== strpos( WPCPM_UI::actions( array( $a ), '<i>' ), '&lt;i&gt;' ), true );

// Every attribute a caller feeds is escaped: a double quote and a "<" come out as entities.
$q = 'a"b<c';
$e = 'a&quot;b&lt;c';
ck( 'confirm is escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'confirm' => $q ) ), ' data-wpcpm-confirm="' . $e . '"' ), true );
ck( 'aria_label is escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'aria_label' => $q ) ), ' aria-label="' . $e . '"' ), true );
ck( 'controls is escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'controls' => $q ) ), ' aria-controls="' . $e . '"' ), true );
ck( 'id is escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'id' => $q ) ), ' id="' . $e . '"' ), true );
ck( 'value is escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'value' => $q ) ), ' value="' . $e . '"' ), true );
ck( 'form is escaped', false !== strpos( WPCPM_UI::button( array( 'label' => 'Go', 'form' => $q ) ), ' form="' . $e . '"' ), true );
ck( 'the heading id is escaped', WPCPM_UI::heading( 'Who', 'h3', $q ), '<h3 class="wpcpm-ui-h" id="' . $e . '">Who</h3>' );

// A link has no name, value or form; it keeps its id.
$link = WPCPM_UI::button( array( 'label' => 'Open', 'href' => 'https://example.org/', 'name' => 'n', 'value' => 'v', 'form' => 'f', 'id' => 'l1', 'side' => 'front' ) );
ck( 'a link prints no name, value or form, and keeps its id', $link, '<a class="wpcpm-btn" href="https://example.org/" id="l1">Open</a>' );

// The field (design 2.4).
$f = WPCPM_UI::field( array( 'id' => 'why', 'name' => 'why', 'label' => 'Reason', 'type' => 'textarea', 'need' => 'required', 'hint' => 'Say why.', 'attrs' => array( 'maxlength' => 2000 ), 'side' => 'front' ) );
ck( 'a required textarea field, whole', $f,
	'<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="why">Reason <span class="wpcpm-ui-field__need">Required</span></label>'
	. '<textarea class="wpcpm-ui-field__control wpcpm-ui-field__control--textarea" id="why" name="why" required maxlength="2000" aria-describedby="why-hint">' . "\n" . '</textarea>'
	. '<p class="wpcpm-ui-field__hint" id="why-hint">Say why.</p></div>' );
$o = WPCPM_UI::field( array( 'id' => 'nm', 'name' => 'nm', 'label' => 'Name', 'need' => 'optional' ) );
ck( 'optional prints Optional and no required', array( false !== strpos( $o, '<span class="wpcpm-ui-field__need">Optional</span>' ), false === strpos( $o, 'required' ) ), array( true, true ) );
// Task 18a, point 5: a box the handler refuses empty, which today's form leaves to the server, says Required in words
// and prints no `required` (nor minlength): what the browser refuses stays as it is.
$w = WPCPM_UI::field( array( 'id' => 'q', 'name' => 'q', 'label' => 'Ask', 'type' => 'textarea', 'need' => 'required', 'required_attr' => false, 'attrs' => array( 'maxlength' => 2000 ) ) );
// Task 18c: the box says so to a screen reader too (aria-required), as the word says it on screen.
ck( 'Required in words alone: the word, no required on the box, and aria-required on it', $w, '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="q">Ask <span class="wpcpm-ui-field__need">Required</span></label><textarea class="wpcpm-ui-field__control wpcpm-ui-field__control--textarea" id="q" name="q" aria-required="true" maxlength="2000">' . "\n" . '</textarea></div>' );
ck( 'required_attr true, or left out, prints required as before', array(
	WPCPM_UI::field( array( 'id' => 'q', 'name' => 'q', 'label' => 'Ask', 'need' => 'required', 'required_attr' => true ) ),
	WPCPM_UI::field( array( 'id' => 'q', 'name' => 'q', 'label' => 'Ask', 'need' => 'required' ) ),
), array(
	'<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="q">Ask <span class="wpcpm-ui-field__need">Required</span></label><input type="text" class="wpcpm-ui-field__control" id="q" name="q" required value=""></div>',
	'<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="q">Ask <span class="wpcpm-ui-field__need">Required</span></label><input type="text" class="wpcpm-ui-field__control" id="q" name="q" required value=""></div>',
) );
ck( 'no need prints no word', false === strpos( WPCPM_UI::field( array( 'id' => 'nm', 'name' => 'nm', 'label' => 'Name' ) ), 'wpcpm-ui-field__need' ), true );
ck( 'no hint prints no aria-describedby and no hint element', array( false === strpos( $o, 'aria-describedby' ), false === strpos( $o, 'wpcpm-ui-field__hint' ) ), array( true, true ) );
ck( 'a text box', $o, '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="nm">Name <span class="wpcpm-ui-field__need">Optional</span></label><input type="text" class="wpcpm-ui-field__control" id="nm" name="nm" value=""></div>' );
ck( 'a textarea value keeps both line feeds', false !== strpos( WPCPM_UI::field( array( 'id' => 'w', 'name' => 'w', 'label' => 'W', 'type' => 'textarea', 'value' => "\nline" ) ), "\n\nline</textarea>" ), true );
$GLOBALS['esc_textarea'] = array();
WPCPM_UI::field( array( 'id' => 'w', 'name' => 'w', 'label' => 'W', 'type' => 'textarea', 'value' => 'Why &lt;b&gt;?' ) );
ck( 'a textarea\'s value is escaped as WordPress escapes a text area, so typing kept with a typed-out entity in it comes back as typed', $GLOBALS['esc_textarea'], array( 'Why &lt;b&gt;?' ) );
ck( 'a textarea has rows', false !== strpos( WPCPM_UI::field( array( 'id' => 'w', 'name' => 'w', 'label' => 'W', 'type' => 'textarea', 'rows' => 6 ) ), ' rows="6"' ), true );
$p = WPCPM_UI::field( array( 'id' => 'why', 'name' => 'why', 'label' => 'Reason', 'hint' => 'Say why.', 'problem' => 'Too long.' ) );
ck( 'a problem prints what WPCPM_Form_Errors prints', array(
	false !== strpos( $p, WPCPM_Form_Errors::attrs( 'why', 'Too long.', 'why-hint' ) ),
	substr( $p, -strlen( WPCPM_Form_Errors::problem( 'why', 'Too long.' ) ) - 6, -6 ),
), array( true, WPCPM_Form_Errors::problem( 'why', 'Too long.' ) ) );
ck( 'a problem without a hint', false !== strpos( WPCPM_UI::field( array( 'id' => 'a', 'name' => 'a', 'label' => 'A', 'problem' => 'No.' ) ), WPCPM_Form_Errors::attrs( 'a', 'No.' ) ), true );
ck( 'no problem prints no problem markup', false === strpos( $f, 'problem' ), true );
foreach ( array( 'date', 'number' ) as $t ) {
	ck( "a $t field has the --$t class and its type", false !== strpos( WPCPM_UI::field( array( 'id' => 'd', 'name' => 'd', 'label' => 'D', 'type' => $t ) ), 'type="' . $t . '" class="wpcpm-ui-field__control wpcpm-ui-field__control--' . $t . '"' ), true );
}
foreach ( array( 'email', 'url', 'search' ) as $t ) {
	ck( "a $t field", false !== strpos( WPCPM_UI::field( array( 'id' => 'd', 'name' => 'd', 'label' => 'D', 'type' => $t ) ), '<input type="' . $t . '" class="wpcpm-ui-field__control"' ), true );
}
ck( 'an unknown type is text', false !== strpos( WPCPM_UI::field( array( 'id' => 'd', 'name' => 'd', 'label' => 'D', 'type' => 'file' ) ), '<input type="text"' ), true );
$s = WPCPM_UI::field( array( 'id' => 'tr', 'name' => 'tr', 'label' => 'Track', 'type' => 'select', 'value' => 'b', 'options' => array( 'a' => 'Alpha', 'b' => 'B & Co' ), 'need' => 'required' ) );
ck( 'a select marks its selected option and escapes the labels', $s,
	'<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="tr">Track <span class="wpcpm-ui-field__need">Required</span></label>'
	. '<select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" id="tr" name="tr" required><option value="a">Alpha</option><option value="b" selected>B &amp; Co</option></select></div>' );
ck( 'a long select', false !== strpos( WPCPM_UI::field( array( 'id' => 'tr', 'name' => 'tr', 'label' => 'T', 'type' => 'select', 'long' => true, 'options' => array() ) ), 'wpcpm-ui-field__control--select wpcpm-ui-field__control--long' ), true );
$x = WPCPM_UI::field( array( 'id' => 'x"', 'name' => 'n"', 'label' => '<b>L</b>', 'value' => '"><i>', 'hint' => '<u>h</u>', 'attrs' => array( 'placeholder' => 'p"<' ) ) );
ck( 'label, value, hint, id, name and attributes are escaped', array(
	false !== strpos( $x, '&lt;b&gt;L&lt;/b&gt;' ), false !== strpos( $x, 'value="&quot;&gt;&lt;i&gt;"' ), false !== strpos( $x, '&lt;u&gt;h&lt;/u&gt;' ),
	false !== strpos( $x, 'id="x&quot;"' ), false !== strpos( $x, 'name="n&quot;"' ), false !== strpos( $x, 'placeholder="p&quot;&lt;"' ), false === strpos( $x, '<b>' ) && false === strpos( $x, '<i>' ) && false === strpos( $x, '<u>' ),
), array( true, true, true, true, true, true, true ) );
ck( 'an attribute outside the list is not printed', false === strpos( WPCPM_UI::field( array( 'id' => 'd', 'name' => 'd', 'label' => 'D', 'attrs' => array( 'onclick' => 'x', 'minlength' => 3 ) ) ), 'onclick' ) && false !== strpos( WPCPM_UI::field( array( 'id' => 'd', 'name' => 'd', 'label' => 'D', 'attrs' => array( 'minlength' => 3 ) ) ), ' minlength="3"' ), true );
// Task 13e fix round 1: a time box, and a pattern passed through. Every kind that was there prints what it printed:
// the digests are of each kind as the code at 96623a8f drew it, with every argument it takes, before the change.
$every = static function ( $type ) {
	return WPCPM_UI::field(
		array(
			'id'      => 'k-' . $type,
			'name'    => 'k',
			'label'   => 'Kind & "label"',
			'type'    => $type,
			'value'   => 'b & "v"',
			'need'    => 'required',
			'hint'    => 'A hint.',
			'rows'    => 3,
			'options' => array( 'a' => 'Alpha', 'b & "v"' => 'Beta' ),
			'attrs'   => array( 'minlength' => 2, 'maxlength' => 9, 'min' => 1, 'max' => 5, 'step' => 2, 'placeholder' => 'p', 'autocomplete' => 'off' ),
			'problem' => 'Not that.',
		)
	);
};
$kinds = array();
foreach ( array( 'text', 'email', 'url', 'search', 'date', 'number', 'textarea', 'select', 'file' ) as $t ) {
	// The one space Task 18c put between the label's words and the need word is taken out before the digest: the
	// space is pinned in the checks above, and everything else is as 96623a8f drew it.
	$kinds[ $t ] = md5( str_replace( ' <span class="wpcpm-ui-field__need">', '<span class="wpcpm-ui-field__need">', $every( $t ) ) );
}
ck(
	'every kind that was there prints byte for byte what it printed before the time box and the pattern (96623a8f)',
	$kinds,
	array(
		'text'     => 'a536b6e01de47ff8d43b84247f637fd1',
		'email'    => 'd08cfe49ca85fcf31d009870103d6517',
		'url'      => '6768f7ce6855648553881035c4fbd0d7',
		'search'   => 'eeb8356fd0acace6f31eb83866a7e9c8',
		'date'     => '56a1a69666e4403df4910e66dbfe39ab',
		'number'   => '97736452803bf56e16a6eab7b05b0920',
		'textarea' => 'c3e65312b80d02e9f997d86710c1c865',
		'select'   => '33d87c4636738754196b270ae02070b4',
		'file'     => '20925d67e6ddf1e0dad3002769e33345',
	)
);
ck(
	'a time field: a single-line box of type time, the date\'s width class (200px, design 2.4), its bounds and value as given',
	WPCPM_UI::field( array( 'id' => 'st', 'name' => 'st', 'label' => 'Start time', 'type' => 'time', 'need' => 'required', 'value' => '14:00', 'hint' => 'In your time zone.' ) ),
	'<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="st">Start time <span class="wpcpm-ui-field__need">Required</span></label>'
	. '<input type="time" class="wpcpm-ui-field__control wpcpm-ui-field__control--date" id="st" name="st" required aria-describedby="st-hint" value="14:00">'
	. '<p class="wpcpm-ui-field__hint" id="st-hint">In your time zone.</p></div>'
);
ck(
	'a pattern is passed to a single-line box, escaped as an attribute, after the other bounds',
	array(
		false !== strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'url', 'attrs' => array( 'maxlength' => 500, 'pattern' => 'https?://.+' ) ) ), ' maxlength="500" pattern="https?://.+"' ),
		false !== strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'attrs' => array( 'pattern' => 'a"b<c' ) ) ), ' pattern="a&quot;b&lt;c"' ),
		false !== strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'email', 'attrs' => array( 'pattern' => '.+@.+' ) ) ), ' pattern=".+@.+"' ),
		false !== strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'search', 'attrs' => array( 'pattern' => '\w+' ) ) ), ' pattern="\w+"' ),
	),
	array( true, true, true, true )
);
ck(
	'no pattern, or an empty one, prints none; a box that is not one line of text (a date, a time, a number, a text area, a select) prints none',
	array(
		strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'url' ) ), 'pattern' ),
		strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'url', 'attrs' => array( 'pattern' => '' ) ) ), 'pattern' ),
		strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'date', 'attrs' => array( 'pattern' => 'x' ) ) ), 'pattern' ),
		strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'time', 'attrs' => array( 'pattern' => 'x' ) ) ), 'pattern' ),
		strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'number', 'attrs' => array( 'pattern' => 'x' ) ) ), 'pattern' ),
		strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'textarea', 'attrs' => array( 'pattern' => 'x' ) ) ), 'pattern' ),
		strpos( WPCPM_UI::field( array( 'id' => 'l', 'name' => 'l', 'label' => 'L', 'type' => 'select', 'attrs' => array( 'pattern' => 'x' ) ) ), 'pattern' ),
	),
	array( false, false, false, false, false, false, false )
);
$all = $f . $s . $x . $p;
ck( 'the legacy wpcpm-field class never appears, but for the refusal markup', preg_match( '/wpcpm-field/', str_replace( WPCPM_Form_Errors::problem( 'why', 'Too long.' ), '', $all ) ), 0 );

// A field with both a hint and a refusal: the refusal's id comes first in aria-describedby.
$hp = WPCPM_UI::field( array( 'id' => 'why', 'name' => 'why', 'label' => 'Reason', 'hint' => 'Say why.', 'problem' => 'Too long.' ) );
ck( 'a hint and a refusal: aria-describedby holds both ids, the refusal first', false !== strpos( $hp, ' aria-invalid="true" aria-describedby="why-problem why-hint"' ), true );
ck( 'a hint and a refusal: both paragraphs carry the ids the box names', array( false !== strpos( $hp, '<p class="wpcpm-ui-field__hint" id="why-hint">' ), false !== strpos( $hp, '<p class="wpcpm-field__problem" id="why-problem">' ) ), array( true, true ) );
// An id with an item suffix: the hint's and the refusal's ids follow it.
$hs = WPCPM_UI::field( array( 'id' => 'why-12', 'name' => 'why', 'label' => 'Reason', 'hint' => 'Say why.', 'problem' => 'Too long.' ) );
ck( 'an item-suffixed id: the box, the hint and the refusal follow it', array(
	false !== strpos( $hs, 'for="why-12"' ), false !== strpos( $hs, ' id="why-12" name="why"' ), false !== strpos( $hs, 'aria-describedby="why-12-problem why-12-hint"' ),
	false !== strpos( $hs, 'id="why-12-hint"' ), false !== strpos( $hs, 'id="why-12-problem"' ),
), array( true, true, true, true, true ) );

// ---- Task 6: the folds (design 2.3) ----
$chev = WPCPM_Icons::ui( 'chevron', 20 );
$ext  = WPCPM_Icons::svg( 'external', 20 );
$h    = WPCPM_UI::fold_head( array( 'size' => 'card', 'title' => 'Applications', 'phrase' => '1 waiting', 'count' => 1, 'tag' => array( 'words' => 'Waiting', 'tone' => 'red' ), 'cue' => 'Review', 'help_slot' => true ) );
ck( 'a card head, whole', $h,
	'<h3 class="wpcpm-ui-fold__title">Applications</h3><span class="wpcpm-ui-fold__phrase">1 waiting</span>'
	. '<span class="wpcpm-ui-fold__end"><span class="wpcpm-status wpcpm-status--red">Waiting</span><span class="wpcpm-ui-fold__cue" aria-hidden="true">Review</span><span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span></span>'
	. '<span class="wpcpm-ui-fold__chev" aria-hidden="true">' . $chev . '</span>' );
ck( 'with a phrase the count is not printed', false === strpos( $h, 'wpcpm-ui-count' ), true );
$hc = WPCPM_UI::fold_head( array( 'size' => 'card', 'title' => 'Students', 'count' => 3 ) );
ck( 'with no phrase the count is printed', false !== strpos( $hc, '<span class="wpcpm-ui-count">3</span>' ), true );
ck( 'the tag, the cue and the help slot are in the end group in that order', array(
	strpos( $h, 'wpcpm-status' ) < strpos( $h, 'wpcpm-ui-fold__cue' ), strpos( $h, 'wpcpm-ui-fold__cue' ) < strpos( $h, 'wpcpm-ui-fold__help-slot' ),
), array( true, true ) );
ck( 'the chevron is the last child and aria-hidden', substr( $h, -( strlen( $chev ) + strlen( '<span class="wpcpm-ui-fold__chev" aria-hidden="true"></span>' ) ) ), '<span class="wpcpm-ui-fold__chev" aria-hidden="true">' . $chev . '</span>' );
$hl = WPCPM_UI::fold_head( array( 'size' => 'card', 'title' => 'Mentor applications', 'phrase' => '2 people', 'leaves' => true ) );
ck( 'leaves: the external icon replaces the chevron', array( false !== strpos( $hl, '<span class="wpcpm-ui-fold__ext" aria-hidden="true">' . $ext . '</span>' ), false === strpos( $hl, 'wpcpm-ui-fold__chev' ) ), array( true, true ) );
ck( 'no cue and no help slot print nothing', array( false === strpos( $hc, 'wpcpm-ui-fold__cue' ), false === strpos( $hc, 'help-slot' ) ), array( true, true ) );
ck( 'a title id', false !== strpos( WPCPM_UI::fold_head( array( 'size' => 'card', 'title' => 'T', 'title_id' => 't-1' ) ), '<h3 class="wpcpm-ui-fold__title" id="t-1">T</h3>' ), true );
ck( 'a head escapes its words', false === strpos( WPCPM_UI::fold_head( array( 'size' => 'card', 'title' => '<b>', 'phrase' => '<i>', 'cue' => '<u>', 'tag' => array( 'words' => '<s>' ) ) ), '<b>' ), true );
$hi = WPCPM_UI::fold_head( array( 'size' => 'inner', 'title' => 'Consent', 'second' => 'Given' ) );
ck( 'an inner head: the title, the second piece, the chevron', $hi, '<span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Consent</span><span class="wpcpm-ui-fold__second">Given</span></span><span class="wpcpm-ui-fold__chev" aria-hidden="true">' . $chev . '</span>' );
ck( 'an inner fold, closed', WPCPM_UI::inner_open( array( 'title' => 'Consent' ) ),
	'<details class="wpcpm-ui-fold wpcpm-ui-fold--inner"><summary class="wpcpm-ui-fold__head">' . WPCPM_UI::fold_head( array( 'size' => 'inner', 'title' => 'Consent' ) ) . '</summary><div class="wpcpm-ui-fold__body">' );
$io = WPCPM_UI::inner_open( array( 'title' => 'Consent', 'open' => true, 'id' => 'c-1' ) );
ck( 'an inner fold with open prints open and its id', 0 === strpos( $io, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="c-1">' ), true );
ck( 'inner_close', WPCPM_UI::inner_close(), '</div></details>' );
// Task 28, W6: a run of inner folds as a list, an item per fold.
ck( 'a run of inner folds as a list', method_exists( 'WPCPM_UI', 'inner_list' ) ? WPCPM_UI::inner_list() . WPCPM_UI::inner_item() . WPCPM_UI::inner_open( array( 'title' => 'Ada' ) ) . WPCPM_UI::inner_close() . WPCPM_UI::inner_item_close() . WPCPM_UI::inner_list_close() : '', '<ul class="wpcpm-ui-folds" role="list"><li class="wpcpm-ui-folds__item">' . WPCPM_UI::inner_open( array( 'title' => 'Ada' ) ) . '</div></details></li></ul>' );
// Institution Dashboard, Task 15, fix round 1: inner_open() takes a portrait (or initials), a title and a second piece as
// markup, and a tag; with none of them it prints what it printed before (digests taken at aeb8875c).
ck( 'inner_open: today\'s arguments print today\'s bytes (digests)', array_map( static function ( $a ) {
	return md5( WPCPM_UI::inner_open( $a ) );
}, array( array( 'title' => 'Consent' ), array( 'title' => 'Consent', 'second' => 'Given <b>' ), array( 'title' => 'A & <b>', 'open' => true, 'id' => 'c-1' ), array( 'title' => 'Plan', 'open' => false, 'second' => '' ), array(), array( 'title' => 'Consent', 'portrait' => '', 'initials' => '', 'title_html' => '', 'second_html' => '', 'tag' => '' ) ) ), array( '3ad3688cd97615e1d78df62711d2ae4e', '22c515601aa2e9aa285e82c0b184b2a3', '9eb7711d2670b25d01e6a72e988500d0', 'd553de71b89063f965e8f344df9559df', '0d5a384c9ed3d09f9ac1b92cb671e821', '3ad3688cd97615e1d78df62711d2ae4e' ) );
// Fix round 2: row() takes its name as markup the caller escaped (name_html); without it, today's bytes (digests taken at
// cb3efde4). The initials are the name's first character as a reader sees it, a letter and its combining mark kept.
$row_all = array( 'name' => 'Ada', 'href' => 'https://example.test/?a=1&b=2', 'mark' => '<i>m</i>', 'tag' => array( 'words' => 'Waits', 'tone' => 'red' ), 'facts' => array( 'one', array( 'html' => '<a href="x">two</a>' ) ), 'actions' => array( '<button>Go</button>' ), 'note' => 'A note', 'form' => '<form></form>', 'form_id' => 'f-1', 'media' => '<img alt="">', 'level' => 'h4', 'id' => 'r-1' );
ck( 'row: today\'s arguments print today\'s bytes (digests), and an empty name_html changes nothing', array_map( static function ( $a ) {
	return md5( WPCPM_UI::row( $a ) );
}, array( array( 'name' => 'Ada <b> & Co' ), $row_all, array(), array( 'name' => 'Bo', 'level' => 'h5' ), array( 'name' => 'Ada <b> & Co', 'name_html' => '' ) ) ), array( 'a3c41aea7eae7273743193d8d7ed1b35', '9fb1046c6f7124ff97ed782b749ce509', 'b9f1d46fc2156383521ddb3e85a22b2e', '0762bf1edee0083b39f8542dc08110bd', 'a3c41aea7eae7273743193d8d7ed1b35' ) );
ck( 'row: name_html is printed as given in place of the name, inside the link when there is one', array(
	WPCPM_UI::row( array( 'name' => 'ignored', 'name_html' => 'Abc<wbr>def' ) ),
	false !== strpos( WPCPM_UI::row( array( 'name_html' => 'A<wbr>b', 'href' => 'https://example.test/' ) ), '<span class="wpcpm-ui-list__name"><a href="https://example.test/">A<wbr>b</a></span>' ),
), array( '<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Abc<wbr>def</span><span class="wpcpm-ui-list__facts"></span></div></li>', true ) );
ck( 'initials: the first character a reader sees, a letter and its combining mark together', array(
	WPCPM_UI::initials( "E\u{0301}va Example" ),
	WPCPM_UI::initials( 'Ada' ),
	WPCPM_UI::initials( '' ),
	WPCPM_UI::initials( "\u{0141}ukasz" ),
), array( "E\u{0301}", 'A', '', "\u{0141}" ) );
// Institution Dashboard, Task 16: a name that starts with a combining mark or a zero-width joiner (a typing slip, a pasted
// name) gives its first letter, never a lone mark in the box; a name of marks alone gives none.
ck( 'initials: leading combining marks and zero-width joiners are skipped', array(
	WPCPM_UI::initials( "\u{0301}Eva" ),
	WPCPM_UI::initials( "\u{200D}Ola" ),
	WPCPM_UI::initials( "\u{200D}\u{0301}\u{0308}A\u{0301}na" ),
	WPCPM_UI::initials( "\u{0301}\u{200D}" ),
	WPCPM_UI::initials( "\u{0301} Bo" ),
), array( 'E', 'O', "A\u{0301}", '', 'B' ) );
ck( 'inner_open: a portrait, a title and a second piece as markup, and a tag, in the head', array(
	WPCPM_UI::inner_open( array( 'title' => 'ignored', 'title_html' => '<a href="x">Ada</a>', 'second_html' => '<span class="wpcpm-ui-whole">2026</span>', 'portrait' => '<img src="p" alt="">', 'tag' => WPCPM_UI::tag( 'On', 'green' ), 'open' => true ) ),
	WPCPM_UI::inner_open( array( 'title' => 'B <b>', 'initials' => 'B<' ) ),
), array(
	'<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait" open><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__portrait"><img src="p" alt=""></span><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title"><a href="x">Ada</a></span><span class="wpcpm-ui-fold__second"><span class="wpcpm-ui-whole">2026</span></span></span><span class="wpcpm-ui-fold__end"><span class="wpcpm-status wpcpm-status--green">On</span></span><span class="wpcpm-ui-fold__chev" aria-hidden="true">' . $chev . '</span></summary><div class="wpcpm-ui-fold__body">',
	'<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__portrait"><span class="wpcpm-ui-fold__initials" aria-hidden="true">B&lt;</span></span><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">B &lt;b&gt;</span></span><span class="wpcpm-ui-fold__chev" aria-hidden="true">' . $chev . '</span></summary><div class="wpcpm-ui-fold__body">',
) );

ck( 'a heading is never printed inside a span (it is a direct child of the summary)', array(
	1 === preg_match( '/<span[^>]*>(?:(?!<\/span>).)*<h[1-6]/s', $h ), 1 === preg_match( '/<span[^>]*>(?:(?!<\/span>).)*<h[1-6]/s', $hc ), 0 === strpos( $h, '<h3 ' ),
), array( false, false, true ) );

// The item header (design 2.10).
$ih = WPCPM_UI::item_header( array( 'name' => 'Example School', 'ref' => 'MAPP-2026-1', 'tag' => array( 'words' => 'New', 'tone' => 'amber' ), 'facts' => array( 'Poland', array( 'html' => '<a href="https://example.org/">Site</a>' ), 'A & B' ) ) );
ck( 'an item header, whole', $ih,
	'<h4 class="wpcpm-ui-item__title">Example School<code class="wpcpm-ui-item__ref">MAPP-2026-1</code></h4>'
	. '<p class="wpcpm-ui-item__facts"><span class="wpcpm-status wpcpm-status--amber">New</span><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">Poland</span><span class="wpcpm-ui-item__fact"><a href="https://example.org/">Site</a></span><span class="wpcpm-ui-item__fact">A &amp; B</span></span></p>' );
ck( 'a tag and no facts print no run', false === strpos( WPCPM_UI::item_header( array( 'name' => 'S', 'tag' => array( 'words' => 'New' ) ) ), 'wpcpm-ui-item__run' ), true );
ck( 'the tag is the facts line\'s first child', 0 === strpos( substr( $ih, strpos( $ih, '<p class="wpcpm-ui-item__facts">' ) + strlen( '<p class="wpcpm-ui-item__facts">' ) ), '<span class="wpcpm-status' ), true );
ck( 'a name with a link and a mark', false !== strpos( WPCPM_UI::item_header( array( 'name' => 'S', 'href' => 'https://example.org/s', 'mark' => '<span class="m">T</span>', 'level' => 'h3' ) ), '<h3 class="wpcpm-ui-item__title"><a href="https://example.org/s">S</a><span class="m">T</span></h3>' ), true );
ck( 'no ref prints no code', false === strpos( WPCPM_UI::item_header( array( 'name' => 'S' ) ), '<code' ), true );
ck( 'no facts and no tag print no facts line', false === strpos( WPCPM_UI::item_header( array( 'name' => 'S' ) ), 'wpcpm-ui-item__facts' ), true );
ck( 'a name is escaped', false !== strpos( WPCPM_UI::item_header( array( 'name' => '<b>' ) ), '&lt;b&gt;' ), true );
ck( 'a level outside h2 to h6 is h4', 0 === strpos( WPCPM_UI::item_header( array( 'name' => 'S', 'level' => 'p' ) ), '<h4 ' ), true );
// A name the caller built and escaped (a sentence with a linked name inside, Task 12 fix round 1), printed as given
// in place of name, href and mark, and an id on the title; a caller passing neither gets exactly what it got.
ck( 'a name already built is printed as given in place of name, href and mark, with the title\'s id', WPCPM_UI::item_header( array( 'name_html' => '<a href="https://example.org/s">S</a> asks', 'name' => 'ignored', 'href' => 'https://example.org/x', 'mark' => '<i>m</i>', 'id' => 'wpcpm-request-7-title', 'ref' => 'R-1' ) ),
	'<h4 class="wpcpm-ui-item__title" id="wpcpm-request-7-title"><a href="https://example.org/s">S</a> asks<code class="wpcpm-ui-item__ref">R-1</code></h4>' );
ck( 'an empty name_html or id is as none: the header is the one drawn without them', array(
	WPCPM_UI::item_header( array( 'name' => 'S', 'href' => 'https://example.org/s', 'name_html' => '', 'id' => '' ) ),
	WPCPM_UI::item_header( array( 'name' => 'S', 'id' => 'a"b' ) ),
), array(
	WPCPM_UI::item_header( array( 'name' => 'S', 'href' => 'https://example.org/s' ) ),
	'<h4 class="wpcpm-ui-item__title" id="a&quot;b">S</h4>',
) );

// Institution Dashboard, Task 16: the item header takes a photo's markup (media) or the name's first letter (initials), in a
// 44px box before the name and its facts, as a student's card leads with the student's photo; with neither (or each ''),
// today's bytes (digests taken at 7eb5745e, before the change).
ck( 'item_header: today\'s arguments print today\'s bytes (digests), and an empty media or initials changes nothing', array_map( static function ( $a ) {
	return md5( WPCPM_UI::item_header( $a ) );
}, array(
	array( 'name' => 'Example School', 'ref' => 'MAPP-2026-1', 'tag' => array( 'words' => 'New', 'tone' => 'amber' ), 'facts' => array( 'Poland', array( 'html' => '<a href="https://example.org/">Site</a>' ), 'A & B' ) ),
	array( 'name' => 'S', 'href' => 'https://example.org/s', 'mark' => '<span class="m">T</span>', 'level' => 'h3' ),
	array( 'name_html' => '<a href="https://example.org/s">S</a> asks', 'id' => 'r-7', 'ref' => 'R-1' ),
	array(),
	array( 'name' => 'Example School', 'ref' => 'MAPP-2026-1', 'tag' => array( 'words' => 'New', 'tone' => 'amber' ), 'facts' => array( 'Poland', array( 'html' => '<a href="https://example.org/">Site</a>' ), 'A & B' ), 'media' => '', 'initials' => '' ),
) ), array( 'efcf0c124b5a8d5772564f6c7c5dbd2e', '12594e7340a8030b2c6a15d94b5686c3', 'bc153cfcdf8d833605ae24c8c96d750b', '190a9e6c44cc5cd4a73ae3584b3b6784', 'efcf0c124b5a8d5772564f6c7c5dbd2e' ) );
ck( 'item_header: a photo, or the initials when there is none, in its box before the name and the facts', array(
	WPCPM_UI::item_header( array( 'name_html' => 'Ada<wbr>Example', 'level' => 'h2', 'media' => '<img src="p" alt="">', 'tag' => array( 'words' => 'On', 'tone' => 'green' ), 'facts' => array( 'From May' ) ) ),
	WPCPM_UI::item_header( array( 'name' => 'B <b>', 'initials' => 'B<' ) ),
	WPCPM_UI::item_header( array( 'name' => 'C', 'media' => '<img src="c" alt="">', 'initials' => 'C' ) ),
), array(
	'<div class="wpcpm-ui-item__head"><span class="wpcpm-ui-item__media"><img src="p" alt=""></span><div class="wpcpm-ui-item__lead"><h2 class="wpcpm-ui-item__title">Ada<wbr>Example</h2><p class="wpcpm-ui-item__facts"><span class="wpcpm-status wpcpm-status--green">On</span><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">From May</span></span></p></div></div>',
	'<div class="wpcpm-ui-item__head"><span class="wpcpm-ui-item__media"><span class="wpcpm-ui-item__initials" aria-hidden="true">B&lt;</span></span><div class="wpcpm-ui-item__lead"><h4 class="wpcpm-ui-item__title">B &lt;b&gt;</h4></div></div>',
	'<div class="wpcpm-ui-item__head"><span class="wpcpm-ui-item__media"><img src="c" alt=""></span><div class="wpcpm-ui-item__lead"><h4 class="wpcpm-ui-item__title">C</h4></div></div>',
) );
// Fix round 2 of Task 16: the same, for argument sets copied from the Administrator Dashboard's own calls (an application's
// and a school's request's header in class-wpcpm-administrators-cards.php, its "Read the application" and "Recently
// closed" folds, the agreement's return note and the request's note in the panel and the request class it draws): digests
// taken with the base's class-wpcpm-ui.php (7eb5745e).
ck( 'item_header, inner_open, field: the Administrator Dashboard\'s own calls print the base\'s bytes (digests)', array(
	md5( WPCPM_UI::item_header( array( 'name' => 'Example Institute of Applied Web', 'href' => 'https://example.org/wp-admin/admin.php?page=wpcpm-institutions&tab=queue&application=42', 'mark' => '<span class="wpcpm-test-mark">Test</span>', 'ref' => 'MAPP-2026-1922', 'tag' => array( 'words' => 'Awaiting review', 'tone' => '' ), 'facts' => array( 'Poland', 'Assigned to admin@example.org', 'Received 3 days ago' ) ) ) ),
	md5( WPCPM_UI::item_header( array( 'name' => 'Example School', 'href' => 'https://example.org/wp-admin/admin.php?page=wpcpm-institutions&institution=recEXAMPLE0000001', 'mark' => '', 'tag' => array( 'words' => 'Overdue', 'tone' => 'red' ), 'facts' => array( 'A new tutor', 'Opened 9 days ago' ) ) ) ),
	md5( WPCPM_UI::inner_open( array( 'title' => 'Read the application' ) ) ),
	md5( WPCPM_UI::inner_open( array( 'title' => 'Recently closed' ) ) ),
	md5( WPCPM_UI::field( array( 'id' => 'wpcpm-agreement-review-7-note', 'name' => 'wpcpm_agreement_note', 'label' => 'What has to change', 'hint' => 'In your own words. It is emailed to everybody at the institution exactly as you write it, with your address to reply to.', 'need' => 'required', 'type' => 'textarea', 'rows' => 4, 'value' => '', 'attrs' => array( 'minlength' => 20, 'maxlength' => 2000 ), 'problem' => '' ) ) ),
	md5( WPCPM_UI::field( array( 'id' => 'wpcpm-request-9-note', 'name' => 'wpcpm_request_note', 'label' => 'Note', 'hint' => 'For the log. It is not sent to the institution.', 'need' => 'optional', 'type' => 'textarea', 'rows' => 2, 'value' => 'Kept & typed', 'attrs' => array( 'maxlength' => 1000 ), 'problem' => 'Too long.' ) ) ),
), array( '5a8d21143fddeb4bdd72ed71b5cc3134', '2bb737c68d8334b7103c19142bc2800e', 'bb1f6b92808cb768ee38c9939293cdc6', '7fbc0a4b75823b433e623889653ea101', '54797ebd8ec1a81b4f295fdb927a7378', '432e03eea1fbd483dfa29f4ed6741d7e' ) );
// Task 16: inner_open() carries the plugin's own data attributes on its disclosure (the Student Report Card's fetch hook,
// `data-wpcpm-report`, which assets/js/dashboard.js reads on open); with none, today's bytes.
ck( 'inner_open: data attributes on the disclosure, and none prints today\'s bytes', array(
	WPCPM_UI::inner_open( array( 'title' => 'Student Report Card', 'data' => array( 'wpcpm-report' => 'rec"1', 'Bad Name' => 'x', 'wpcpm-off' => false ) ) ),
	md5( WPCPM_UI::inner_open( array( 'title' => 'Consent', 'data' => array() ) ) ),
	0 === strpos( WPCPM_UI::inner_open( array( 'title' => 'T', 'open' => true, 'id' => 'f-1', 'data' => array( 'wpcpm-x' => true ) ) ), '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="f-1" data-wpcpm-x>' ),
), array(
	'<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" data-wpcpm-report="rec&quot;1"><summary class="wpcpm-ui-fold__head">' . WPCPM_UI::fold_head( array( 'size' => 'inner', 'title' => 'Student Report Card' ) ) . '</summary><div class="wpcpm-ui-fold__body">',
	'3ad3688cd97615e1d78df62711d2ae4e',
	true,
) );
// Task 16: field() draws a box the form may not use (today's correction form draws the Students half disabled when the
// site cannot tell which entry a change belongs to); without `disabled`, or with false, today's bytes.
ck( 'field: today\'s arguments print today\'s bytes (digests), and disabled false changes nothing', array_map( static function ( $a ) {
	return md5( WPCPM_UI::field( $a ) );
}, array(
	array( 'id' => 'f1', 'name' => 'student[f1]', 'label' => 'Full name', 'value' => 'A & B', 'attrs' => array( 'maxlength' => 200 ), 'problem' => 'Too long.' ),
	array( 'id' => 'f2', 'name' => 'student[f2]', 'label' => 'Start date', 'type' => 'date', 'value' => '2026-02-16' ),
	array( 'id' => 'f3', 'name' => 'student[f3]', 'label' => 'Field of study', 'type' => 'select', 'value' => 'b', 'options' => array( '' => 'Not set', 'a' => 'A', 'b' => 'B' ) ),
	array( 'id' => 'f1', 'name' => 'student[f1]', 'label' => 'Full name', 'value' => 'A & B', 'attrs' => array( 'maxlength' => 200 ), 'problem' => 'Too long.', 'disabled' => false ),
) ), array( 'fa32343af1c41ca9562eddd4d89202ef', '76a653720883d7f337697c7933b1d253', '3dbf70bc4fc5f8a0b51dd50f5f29b4f5', 'fa32343af1c41ca9562eddd4d89202ef' ) );
ck( 'field: disabled prints the attribute on the box, a select and a date alike', array(
	WPCPM_UI::field( array( 'id' => 'f1', 'name' => 'student[f1]', 'label' => 'Full name', 'value' => 'v', 'attrs' => array( 'maxlength' => 200 ), 'disabled' => true ) ),
	false !== strpos( WPCPM_UI::field( array( 'id' => 'f3', 'name' => 's', 'label' => 'L', 'type' => 'select', 'options' => array( '' => 'Not set' ), 'disabled' => true ) ), '<select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" id="f3" name="s" disabled>' ),
	false !== strpos( WPCPM_UI::field( array( 'id' => 'f2', 'name' => 'd', 'label' => 'L', 'type' => 'date', 'disabled' => true ) ), ' name="d" disabled value=""' ),
), array(
	'<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="f1">Full name</label><input type="text" class="wpcpm-ui-field__control" id="f1" name="student[f1]" maxlength="200" disabled value="v"></div>',
	true,
	true,
) );

// The table (design 2.7).
$cols = array(
	array( 'key' => 'name', 'label' => 'Name', 'kind' => 'text', 'width' => '' ),
	array( 'key' => 'n', 'label' => 'Students', 'kind' => 'num', 'width' => '' ),
	array( 'key' => 'act', 'label' => '', 'kind' => 'action', 'width' => '' ),
);
$t = WPCPM_UI::table_open( $cols );
ck( 'a table with an action column prints the screen-reader name Action', false !== strpos( $t, '<th scope="col" role="columnheader" class="wpcpm-ui-cell--action"><span class="screen-reader-text">Action</span></th>' ), true );
ck( 'a table, whole, with the roles that keep its semantics when the phone layout sets display: block', $t . WPCPM_UI::table_close(),
	'<table class="wpcpm-ui-table wpcpm-ui-table--pairs-2" role="table"><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader" class="wpcpm-ui-cell--text">Name</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--num">Students</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--action"><span class="screen-reader-text">Action</span></th></tr></thead><tbody role="rowgroup"></tbody></table>' );
ck( 'no width, no colgroup', false === strpos( $t, 'colgroup' ), true );
$tw = WPCPM_UI::table_open( array( array( 'key' => 'a', 'label' => 'A', 'kind' => 'text', 'width' => '40%' ), array( 'key' => 'b', 'label' => 'B', 'kind' => 'num', 'width' => '' ) ) );
ck( 'a width prints a colgroup', false !== strpos( $tw, '<colgroup><col style="width:40%"><col></colgroup>' ), true );
ck( 'a num cell carries its kind and its data-label', WPCPM_UI::cell( $cols[1], '12' ), '<td class="wpcpm-ui-cell--num" role="cell" data-label="Students">12</td>' );
ck( 'a cell prints the html as given', WPCPM_UI::cell( $cols[0], '<a href="#">x</a>' ), '<td class="wpcpm-ui-cell--text" role="cell" data-label="Name"><a href="#">x</a></td>' );
ck( 'an empty value kept in words', WPCPM_UI::cell( $cols[0], 'Not recorded', 'empty' ), '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="Name">Not recorded</td>' );
ck( 'an action cell has no data-label', WPCPM_UI::cell( $cols[2], 'X' ), '<td class="wpcpm-ui-cell--action" role="cell">X</td>' );
$kv = array( 'key' => 'k', 'label' => 'School', 'kind' => 'text', 'width' => '', 'header' => true );
ck( 'a label cell of a key and value table is a row header', WPCPM_UI::cell( $kv, 'School' ), '<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">School</th>' );
ck( 'a row header keeps the html it is given', false !== strpos( WPCPM_UI::cell( $kv, '<b>x</b>' ), '><b>x</b></th>' ), true );
ck( 'a row header with an empty value mark', false !== strpos( WPCPM_UI::cell( $kv, 'x', 'empty' ), 'wpcpm-ui-cell--text wpcpm-ui-cell--empty' ), true );
ck( 'a row opens and closes with its role', array( WPCPM_UI::row_open(), WPCPM_UI::row_close() ), array( '<tr role="row">', '</tr>' ) );
ck( 'a row with an id and a class, escaped', WPCPM_UI::row_open( array( 'id' => 'r"1', 'class' => 'a b"' ) ), '<tr role="row" id="r&quot;1" class="a b&quot;">' );
ck( 'the empty line of a list', WPCPM_UI::list_empty( 'No <b>company</b> matches.' ), '<p class="wpcpm-ui-list__empty">No &lt;b&gt;company&lt;/b&gt; matches.</p>' );
$tk = WPCPM_UI::table_open( $cols, array( 'keyvalue' => true, 'caption' => 'Facts', 'labelledby' => 'h-1' ) );
ck( 'keyvalue, caption and labelledby, and no header row', $tk, '<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table" aria-labelledby="h-1"><caption class="screen-reader-text">Facts</caption><tbody role="rowgroup">' );
ck( 'a column label is escaped', false !== strpos( WPCPM_UI::table_open( array( array( 'key' => 'a', 'label' => '<b>', 'kind' => 'text', 'width' => '' ) ) ), '&lt;b&gt;' ), true );

// The list row (design 2.8).
ck( 'the list', array( WPCPM_UI::list_open(), WPCPM_UI::list_close() ), array( '<ul class="wpcpm-ui-list">', '</ul>' ) );
// Task 18c: a list whose rows carry no tag asks for its facts under the name; anything else prints the list as before,
// byte for byte.
ck( 'list_open(): facts under prints the list\'s modifier; no option, or any other value, prints the list as before', array(
	WPCPM_UI::list_open( array( 'facts' => 'under' ) ),
	WPCPM_UI::list_open( array() ),
	WPCPM_UI::list_open( array( 'facts' => 'beside' ) ),
	WPCPM_UI::list_open( array( 'facts' => '' ) ),
), array( '<ul class="wpcpm-ui-list wpcpm-ui-list--under">', '<ul class="wpcpm-ui-list">', '<ul class="wpcpm-ui-list">', '<ul class="wpcpm-ui-list">' ) );
// Task 18c, point 10: the fold's chevron is WordPress's filled chevron the approved prototype drew, at its scale (a
// 24-unit glyph 28px wide centered in a 20px box), in the second ink through currentColor.
ck( 'the fold\'s chevron: the prototype\'s filled glyph, at its 28/24 scale in the 20px box, in currentColor', WPCPM_Icons::ui( 'chevron', 20 ), '<svg class="wpcpm-icon wpcpm-icon--chevron" width="20" height="20" viewBox="3.43 3.43 17.14 17.14" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.5 11.6 12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z" /></svg>' );
ck( 'and every fold head prints it, never the stroked chevron', array( substr_count( WPCPM_UI::fold_head( array( 'title' => 'T' ) ), 'wpcpm-icon--chevron"' ), substr_count( WPCPM_UI::inner_open( array( 'title' => 'T' ) ), 'wpcpm-icon--chevron"' ), substr_count( WPCPM_UI::fold_head( array( 'title' => 'T' ) ) . WPCPM_UI::inner_open( array( 'title' => 'T' ) ), 'chevron-down' ) ), array( 1, 1, 0 ) );
$act = WPCPM_UI::button( array( 'label' => 'Resend', 'kind' => 'quiet', 'side' => 'front' ) );
$r   = WPCPM_UI::row( array( 'name' => 'Example Co', 'tag' => array( 'words' => 'Signed', 'tone' => 'green' ), 'facts' => array( '2026', array( 'html' => '<b>x</b>' ) ), 'actions' => array( $act ) ) );
ck( 'a row, whole', $r,
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Example Co</span><span class="wpcpm-status wpcpm-status--green">Signed</span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">2026</span><span class="wpcpm-ui-item__fact"><b>x</b></span></span><div class="wpcpm-ui-list__act">' . $act . '</div></div></li>' );
$rf = WPCPM_UI::row( array( 'name' => 'Example Co', 'actions' => array( $act ), 'form' => '<form></form>', 'form_id' => 'rf-1' ) );
ck( 'a row with a form prints it after the line, inside the same li, with its id', false !== strpos( $rf, '</div><div class="wpcpm-ui-list__form" id="rf-1"><form></form></div></li>' ) && 1 === substr_count( $rf, '<li ' ), true );
ck( 'a row without a form prints no form div', false === strpos( $r, 'wpcpm-ui-list__form' ), true );
ck( 'a row name is escaped', false !== strpos( WPCPM_UI::row( array( 'name' => '<b>' ) ), '&lt;b&gt;' ), true );
// Task 11: a row's name may be a link, and a mark (already escaped) may follow it, as an item header's may.
ck( 'a row name with href is a link, the mark after it, inside the name', WPCPM_UI::row( array( 'name' => 'A & B', 'href' => 'https://example.org/?a=1', 'mark' => ' <span class="m">Test</span>' ) ),
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name"><a href="https://example.org/?a=1">A &amp; B</a> <span class="m">Test</span></span><span class="wpcpm-ui-list__facts"></span></div></li>' );

// Task 11, fix round 1: the action cell is a div, since a form may not sit in a span; a note has its own line.
ck( 'a row\'s action cell is a div, so a form may sit in it', false !== strpos( WPCPM_UI::row( array( 'name' => 'A', 'actions' => array( '<form></form>' ) ) ), '<div class="wpcpm-ui-list__act"><form></form></div>' ), true );
ck( 'a row with a note prints it under its line, inside the row, before any form, and marks the row', WPCPM_UI::row( array( 'name' => 'A', 'note' => 'Page two is missing.', 'form' => '<form></form>', 'form_id' => 'n-1' ) ),
	'<li class="wpcpm-ui-list__row wpcpm-ui-list__row--note"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div><p class="wpcpm-ui-list__note">Page two is missing.</p><div class="wpcpm-ui-list__form" id="n-1"><form></form></div></li>' );
ck( 'a note keeps its line break as typed and escapes its markup characters', false !== strpos( WPCPM_UI::row( array( 'name' => 'A', 'note' => "Line one <b>&\nline \"two\"" ) ), '<p class="wpcpm-ui-list__note">Line one &lt;b&gt;&amp;' . "\n" . 'line &quot;two&quot;</p>' ), true );
ck( 'an empty note, or none, prints nothing and leaves the row as it was', array(
	WPCPM_UI::row( array( 'name' => 'A', 'note' => '' ) ),
	WPCPM_UI::row( array( 'name' => 'A' ) ),
), array_fill( 0, 2, '<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>' ) );

// Task V1, ruling 14: a table of a family says so by its modifier, after the others; without it the table is as it was.
ck( 'table_open( fixed ): the family modifier after the others, the colgroup as given; none without it', array(
	WPCPM_UI::table_open( array( array( 'label' => 'A', 'width' => '30%' ), array( 'label' => 'B' ) ), array( 'fixed' => true, 'wide' => true ) ),
	WPCPM_UI::table_open( array( array( 'label' => 'A' ) ), array( 'fixed' => false ) ) === WPCPM_UI::table_open( array( array( 'label' => 'A' ) ) ),
), array(
	'<table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--fixed wpcpm-ui-table--pairs-2" role="table"><colgroup><col style="width:30%"><col></colgroup><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader" class="wpcpm-ui-cell--text">A</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--text">B</th></tr></thead><tbody role="rowgroup">',
	true,
) );

// Task V1, ruling 14: a row may carry its own id, an anchor a link lands on (a revoked sponsor agreement's), escaped;
// with none, or '', the row is as it was, byte for byte.
ck( 'a row\'s id is printed on its li, escaped; none or \'\' prints none', array(
	WPCPM_UI::row( array( 'name' => 'A', 'id' => 'wpcpm-sponsor-agreement-7' ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'id' => 'a" b' ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'id' => '' ) ),
), array(
	'<li class="wpcpm-ui-list__row" id="wpcpm-sponsor-agreement-7"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row" id="a&quot; b"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
) );

// Task 15b: a row with no action prints no action cell, so on a phone no empty cell takes a grid row and its gap;
// a row with an action is as it was, byte for byte. An action list of empty strings is no action.
$r15 = WPCPM_UI::row( array( 'name' => 'A', 'actions' => array( $act ) ) );
ck( 'a row with an action keeps its action cell, whole; one with none, an empty list or only empty markup prints none', array(
	$r15,
	WPCPM_UI::row( array( 'name' => 'A' ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'actions' => array() ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'actions' => array( '', '' ) ) ),
), array(
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span><div class="wpcpm-ui-list__act">' . $act . '</div></div></li>',
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
) );
// Task 18d: the one rule that names the action after a sibling, `.wpcpm-status ~ .wpcpm-ui-list__act` (a row with a tag
// in a list with its facts under the name puts its action on line 1), places the action when it is there and moves
// nothing when it is not; it is left out of the search, and nothing else may follow it. (Task 18f: that rule went, the
// action of every such row following the list's own rule; the search finds nothing either way.)
ck( 'no stylesheet rule and no script of the parts needs the action cell to be there: the sheet places each cell by its own class, the script never names it', array(
	preg_match( '/list__act\s*(:empty|\+|~)|(\+|~)\s*\.wpcpm-ui-list__act|list__facts\s*\+/', str_replace( '.wpcpm-ui-on .wpcpm-ui-list--under .wpcpm-status ~ .wpcpm-ui-list__act {', '', (string) file_get_contents( __DIR__ . '/../assets/css/parts.css' ) ) ),
	strpos( (string) file_get_contents( __DIR__ . '/../assets/js/parts.js' ), 'list__act' ),
), array( 0, false ) );

// Task 15b: a submit may post to an address of its own (formaction), as the Students gone quiet card's row action posts
// to the address that carries its student's nonce; a link prints none.
ck( 'button: formaction on a submit, escaped as an address; none on a link or when empty', array(
	WPCPM_UI::button( array( 'label' => 'Go', 'kind' => 'quiet', 'name' => 'student', 'value' => '9', 'formaction' => 'https://example.org/admin-post.php?_wpnonce=n-9&a="b"', 'side' => 'front' ) ),
	WPCPM_UI::button( array( 'label' => 'Go', 'href' => 'https://example.org/', 'formaction' => 'https://example.org/x', 'side' => 'front' ) ),
	WPCPM_UI::button( array( 'label' => 'Go', 'formaction' => '', 'side' => 'front' ) ),
), array(
	'<button type="submit" class="wpcpm-btn wpcpm-btn--quiet" name="student" value="9" formaction="' . esc_url( 'https://example.org/admin-post.php?_wpnonce=n-9&a="b"' ) . '">Go</button>',
	'<a class="wpcpm-btn" href="https://example.org/">Go</a>',
	'<button type="submit" class="wpcpm-btn">Go</button>',
) );
// Task 17: formaction does nothing on a button that submits nothing, so a type="button" prints none; a submit is as
// it was, the explicit submit too.
ck( 'button: no formaction on a type="button", in either side; a submit, named so or by default, keeps it', array(
	WPCPM_UI::button( array( 'label' => 'Go', 'kind' => 'quiet', 'type' => 'button', 'formaction' => 'https://example.org/x', 'side' => 'front' ) ),
	WPCPM_UI::button( array( 'label' => 'Go', 'type' => 'button', 'formaction' => 'https://example.org/x', 'side' => 'admin' ) ),
	WPCPM_UI::button( array( 'label' => 'Go', 'type' => 'submit', 'formaction' => 'https://example.org/x', 'side' => 'front' ) ),
), array(
	'<button type="button" class="wpcpm-btn wpcpm-btn--quiet">Go</button>',
	'<button type="button" class="button button-primary">Go</button>',
	'<button type="submit" class="wpcpm-btn" formaction="https://example.org/x">Go</button>',
) );

// Task 17: a table that ends its card says so, and the sheet drops its last row's hairline by that modifier, wherever
// the table sits in the card's body (Students gone quiet's last table sits inside the card's one form); a table that
// does not say so prints what it printed before, byte for byte.
ck( 'table_open: "end" prints the modifier after the table\'s other classes; false, absent or empty prints today\'s table', array(
	WPCPM_UI::table_open( $cols, array( 'end' => true ) ),
	WPCPM_UI::table_open( $cols, array( 'end' => true, 'keyvalue' => true, 'labelledby' => 'h-1' ) ),
	WPCPM_UI::table_open( $cols, array( 'end' => false ) ) === $t,
	WPCPM_UI::table_open( $cols, array( 'end' => '' ) ) === $t,
	WPCPM_UI::table_open( $cols, array( 'keyvalue' => true, 'caption' => 'Facts', 'labelledby' => 'h-1' ) ) === $tk,
), array(
	str_replace( '<table class="wpcpm-ui-table ', '<table class="wpcpm-ui-table wpcpm-ui-table--end ', $t ),
	'<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue wpcpm-ui-table--end" role="table" aria-labelledby="h-1"><tbody role="rowgroup">',
	true,
	true,
	true,
) );

// Task 15b: the card fold's foot (design 2.3), the line a card's body ends with: the way to the card's home, its words
// for a screen reader alone after the label inside the link, the space before them inside the hidden span.
ck( 'fold_foot(): the foot paragraph and its link, the words for a screen reader after the label, all escaped; none with no context', array(
	WPCPM_UI::fold_foot( 'https://example.org/a?b=1&c=2', 'Open <the> screen', 'for the "X" card' ),
	WPCPM_UI::fold_foot( 'https://example.org/', 'Open', '' ),
	WPCPM_UI::fold_foot( 'https://example.org/', 'Open', '   ' ),
), array(
	'<p class="wpcpm-ui-fold__foot"><a href="' . esc_url( 'https://example.org/a?b=1&c=2' ) . '">Open &lt;the&gt; screen<span class="screen-reader-text"> for the &quot;X&quot; card</span></a></p>',
	'<p class="wpcpm-ui-fold__foot"><a href="https://example.org/">Open</a></p>',
	'<p class="wpcpm-ui-fold__foot"><a href="https://example.org/">Open</a></p>',
) );

// Task V1, ruling 3: the help link in a parts head carries the part's own class beside its old one, so the part's
// look decides by weight; anything that is not the guide's link is given back as it came.
ck( 'help_link(): the guide\'s link gains the part\'s class after its own, once; anything else, and no link, as given', array(
	WPCPM_UI::help_link( '<a class="wpcpm-job-link" href="https://example.org/guide/#job">How to do this<span class="screen-reader-text">: X</span></a>' ),
	WPCPM_UI::help_link( '' ),
	WPCPM_UI::help_link( '<a href="#x" class="wpcpm-job-link">Other</a>' ),
	WPCPM_UI::help_link( 'text <a class="wpcpm-job-link" href="#">How</a>' ),
), array(
	'<a class="wpcpm-job-link wpcpm-ui-fold__help-link" href="https://example.org/guide/#job">How to do this<span class="screen-reader-text">: X</span></a>',
	'',
	'<a href="#x" class="wpcpm-job-link">Other</a>',
	'text <a class="wpcpm-job-link" href="#">How</a>',
) );

// Task 7: the button's data hooks (the Decide area's Cancel and a shared panel's submits).
ck( 'button: data prints the plugin\'s hooks, true as a bare attribute, after hidden', WPCPM_UI::button( array( 'label' => 'Cancel', 'kind' => 'quiet', 'type' => 'button', 'hidden' => true, 'data' => array( 'wpcpm-cancel' => true ) ) ), '<button type="button" class="wpcpm-btn wpcpm-btn--quiet" hidden data-wpcpm-cancel>Cancel</button>' );
ck( 'button: a data value is escaped, a false one skipped, a key that is not lowercase words and hyphens dropped', WPCPM_UI::button( array( 'label' => 'Go', 'data' => array( 'wpcpm-for' => 'a"<b', 'wpcpm-off' => false, 'on click' => 'x', 'Bad' => 'y' ) ) ), '<button type="submit" class="wpcpm-btn" data-wpcpm-for="a&quot;&lt;b">Go</button>' );

// Task 6's re-review: list_empty() prints a paragraph, and its docblock tells a caller to print it outside the list.
$src = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-ui.php' );
$doc = preg_match( '#/\*\*((?:(?!\*/).)*)\*/\s*public static function list_empty#s', $src, $m ) ? $m[1] : '';
ck( 'list_empty()\'s docblock says it prints a paragraph, printed outside the list', array( false !== stripos( $doc, 'paragraph' ), false !== stripos( $doc, 'outside the list' ) ), array( true, true ) );
ck( 'list_empty() prints that paragraph', WPCPM_UI::list_empty( 'No company matches.' ), '<p class="wpcpm-ui-list__empty">No company matches.</p>' );

// Task 13d fix round 1: choices() (design 2.4 "Choice group", 3.3).
$ch_doc = function ( $html ) {
	$d = new DOMDocument();
	libxml_use_internal_errors( true );
	$d->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $d );
};
$ch_resolves = function ( $html ) use ( $ch_doc ) {
	$x   = $ch_doc( $html );
	$ids = array();
	foreach ( $x->query( '//*[@id]' ) as $n ) {
		$ids[] = $n->getAttribute( 'id' );
	}
	$bad = array();
	foreach ( $x->query( '//*[@for] | //*[@aria-describedby] | //*[@aria-labelledby]' ) as $n ) {
		foreach ( preg_split( '/\s+/', trim( $n->getAttribute( 'for' ) . ' ' . $n->getAttribute( 'aria-describedby' ) . ' ' . $n->getAttribute( 'aria-labelledby' ) ) ) as $ref ) {
			if ( '' !== $ref && ! in_array( $ref, $ids, true ) ) {
				$bad[] = $ref;
			}
		}
	}
	return array( count( $ids ) === count( array_unique( $ids ) ), $bad );
};
$ch_radios = WPCPM_UI::choices( array( 'id' => 'tier-7', 'legend' => 'Which level?', 'name' => 'wpcpm_tier', 'type' => 'radio', 'options' => array( 'a' => 'Supporter', 'b' => 'Backer' ), 'checked' => 'b', 'need' => 'required' ) );
ck( 'choices: a group of radios is a fieldset whose legend is the field\'s label with Required in words; each option a label holding its input and then its words; the checked value ticked; required on each radio', $ch_radios,
	'<fieldset class="wpcpm-ui-choices"><legend class="wpcpm-ui-choices__legend">Which level? <span class="wpcpm-ui-field__need">Required</span></legend>'
	. '<label class="wpcpm-ui-choice" for="tier-7-a"><input type="radio" class="wpcpm-ui-choice__input" id="tier-7-a" name="wpcpm_tier" value="a" required><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="tier-7-a-words">Supporter</span></span></label>'
	. '<label class="wpcpm-ui-choice" for="tier-7-b"><input type="radio" class="wpcpm-ui-choice__input" id="tier-7-b" name="wpcpm_tier" value="b" checked required><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="tier-7-b-words">Backer</span></span></label></fieldset>' );
$ch_boxes = WPCPM_UI::choices( array( 'id' => 'aud-7', 'legend' => 'Who sees it', 'name' => 'wpcpm_aud[]', 'type' => 'checkbox', 'options' => array( 'students' => array( 'label' => 'Students <all>', 'hint' => 'Every "student" & graduate.' ), 'mentors' => 'Mentors' ), 'checked' => array( 'students', 'mentors' ), 'need' => 'optional' ) );
ck( 'choices: a group of checkboxes with a hint: Optional in words, every checked value ticked, the hint under the option\'s words, tied by aria-describedby and the input named by its words; escaped; no required on a group of checkboxes', $ch_boxes,
	'<fieldset class="wpcpm-ui-choices"><legend class="wpcpm-ui-choices__legend">Who sees it <span class="wpcpm-ui-field__need">Optional</span></legend>'
	. '<label class="wpcpm-ui-choice" for="aud-7-students"><input type="checkbox" class="wpcpm-ui-choice__input" id="aud-7-students" name="wpcpm_aud[]" value="students" checked aria-labelledby="aud-7-students-words" aria-describedby="aud-7-students-hint"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="aud-7-students-words">Students &lt;all&gt;</span><span class="wpcpm-ui-choice__hint" id="aud-7-students-hint">Every &quot;student&quot; &amp; graduate.</span></span></label>'
	. '<label class="wpcpm-ui-choice" for="aud-7-mentors"><input type="checkbox" class="wpcpm-ui-choice__input" id="aud-7-mentors" name="wpcpm_aud[]" value="mentors" checked><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="aud-7-mentors-words">Mentors</span></span></label></fieldset>' );
$ch_alone = WPCPM_UI::choices( array( 'id' => 'wpcpm_test-9', 'name' => 'wpcpm_test', 'options' => array( '1' => 'Test sponsor: not listed publicly' ), 'checked' => '1' ) );
ck( 'choices: one checkbox with no legend is its own field, no fieldset, its id the one given', $ch_alone,
	'<div class="wpcpm-ui-choices wpcpm-ui-choices--alone"><label class="wpcpm-ui-choice" for="wpcpm_test-9"><input type="checkbox" class="wpcpm-ui-choice__input" id="wpcpm_test-9" name="wpcpm_test" value="1" checked><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="wpcpm_test-9-words">Test sponsor: not listed publicly</span></span></label></div>' );
ck( 'choices: one checkbox not checked is clear; a checked value that is no option ticks nothing; Required on one checkbox alone is in words after its words and on its input', array(
	false !== strpos( WPCPM_UI::choices( array( 'id' => 'x', 'name' => 'x', 'options' => array( '1' => 'One' ), 'checked' => '' ) ), ' checked' ),
	false !== strpos( WPCPM_UI::choices( array( 'id' => 'x', 'name' => 'x', 'options' => array( '1' => 'One', '2' => 'Two' ), 'legend' => 'L', 'checked' => array( '3' ) ) ), ' checked' ),
	WPCPM_UI::choices( array( 'id' => 'x', 'name' => 'x', 'options' => array( '1' => 'One' ), 'need' => 'required' ) ),
), array( false, false, '<div class="wpcpm-ui-choices wpcpm-ui-choices--alone"><label class="wpcpm-ui-choice" for="x"><input type="checkbox" class="wpcpm-ui-choice__input" id="x" name="x" value="1" required><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="x-words">One <span class="wpcpm-ui-field__need">Required</span></span></span></label></div>' ) );
ck( 'choices: every id is unique and every for, aria-describedby and aria-labelledby resolves, the three kinds drawn on one page', $ch_resolves( $ch_radios . $ch_boxes . $ch_alone ), array( true, array() ) );
// Institution Dashboard, Task 17: choices() takes the refusal of one checkbox alone (`problem`), tied to it as field() ties
// a box's; with none, or an empty one, or on a group, today's bytes (digests taken at 63a50750, before the change), the
// Administrator Dashboard's own call among them (a sponsor application's Approve box, ticked and not).
$ch_digest = static function ( array $a ) {
	return md5( WPCPM_UI::choices( $a ) );
};
ck( 'choices: with no problem, an empty one, or a problem on a group, the bytes it printed before', array_map( $ch_digest, array(
	'approve, ticked'     => array( 'id' => 'wpcpm_test-41', 'name' => 'wpcpm_test', 'type' => 'checkbox', 'options' => array( '1' => 'Test sponsor: not listed publicly' ), 'checked' => '1' ),
	'approve, not ticked' => array( 'id' => 'wpcpm_test-41', 'name' => 'wpcpm_test', 'type' => 'checkbox', 'options' => array( '1' => 'Test sponsor: not listed publicly' ), 'checked' => '' ),
	'radios'              => array( 'id' => 'tier-7', 'legend' => 'Which level?', 'name' => 'wpcpm_tier', 'type' => 'radio', 'options' => array( 'a' => 'Supporter', 'b' => 'Backer' ), 'checked' => 'b', 'need' => 'required' ),
	'boxes with a hint'   => array( 'id' => 'aud-7', 'legend' => 'Who sees it', 'name' => 'wpcpm_aud[]', 'type' => 'checkbox', 'options' => array( 'students' => array( 'label' => 'Students <all>', 'hint' => 'Every "student" & graduate.' ), 'mentors' => 'Mentors' ), 'checked' => array( 'students', 'mentors' ), 'need' => 'optional' ),
	'alone, required'     => array( 'id' => 'x', 'name' => 'x', 'options' => array( '1' => 'One' ), 'need' => 'required' ),
	'alone, empty problem' => array( 'id' => 'x', 'name' => 'x', 'options' => array( '1' => 'One' ), 'need' => 'required', 'problem' => '' ),
	'alone with a hint, empty problem' => array( 'id' => 'y', 'name' => 'y', 'options' => array( '1' => array( 'label' => 'One', 'hint' => 'Why.' ) ), 'problem' => '' ),
	'group, a problem'    => array( 'id' => 'tier-7', 'legend' => 'Which level?', 'name' => 'wpcpm_tier', 'type' => 'radio', 'options' => array( 'a' => 'Supporter', 'b' => 'Backer' ), 'checked' => 'b', 'need' => 'required', 'problem' => 'Choose one.' ),
) ), array(
	'approve, ticked'     => '5e0594a9a521c9dac94924fa210f4a85',
	'approve, not ticked' => 'b82d03f31ec839d590f5211a44ce2d3c',
	'radios'              => 'f7de81e67d29e54e7d90347308f36144',
	'boxes with a hint'   => '6d211b1a3d26314669bd1a97c616baa2',
	'alone, required'     => '285eca76d9a7d3394ca9ced9bcfde144',
	'alone, empty problem' => '285eca76d9a7d3394ca9ced9bcfde144',
	'alone with a hint, empty problem' => '98b31081eca040762ab442d43ba8980c',
	'group, a problem'    => 'f7de81e67d29e54e7d90347308f36144',
) );
$ch_refused = WPCPM_UI::choices( array( 'id' => 'wpcpm-import-notified', 'name' => 'notified', 'options' => array( '1' => 'They were told.' ), 'need' => 'required', 'problem' => 'Confirm it <first>.' ) );
$ch_refused_hint = WPCPM_UI::choices( array( 'id' => 'z', 'name' => 'z', 'options' => array( '1' => array( 'label' => 'One', 'hint' => 'Why.' ) ), 'problem' => 'No.' ) );
ck( 'choices: one checkbox alone, refused: aria-invalid and its sentence named, before its hint when it has one; the sentence under it, escaped, in a field\'s wrapper', array( $ch_refused, $ch_refused_hint ), array(
	'<div class="wpcpm-ui-choices wpcpm-ui-choices--alone wpcpm-ui-field"><label class="wpcpm-ui-choice" for="wpcpm-import-notified"><input type="checkbox" class="wpcpm-ui-choice__input" id="wpcpm-import-notified" name="notified" value="1" required aria-invalid="true" aria-describedby="wpcpm-import-notified-problem"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="wpcpm-import-notified-words">They were told. <span class="wpcpm-ui-field__need">Required</span></span></span></label><p class="wpcpm-field__problem" id="wpcpm-import-notified-problem">Confirm it &lt;first&gt;.</p></div>',
	'<div class="wpcpm-ui-choices wpcpm-ui-choices--alone wpcpm-ui-field"><label class="wpcpm-ui-choice" for="z"><input type="checkbox" class="wpcpm-ui-choice__input" id="z" name="z" value="1" aria-labelledby="z-words" aria-invalid="true" aria-describedby="z-problem z-hint"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="z-words">One</span><span class="wpcpm-ui-choice__hint" id="z-hint">Why.</span></span></label><p class="wpcpm-field__problem" id="z-problem">No.</p></div>',
) );
ck( 'choices: refused, every id is unique and every reference resolves', $ch_resolves( $ch_refused . $ch_refused_hint ), array( true, array() ) );

// Task 16, fix round 1: a cell's second line, and a row's media box.
// Task 17, fix round 1: one ordinary space before the span, so a screen reader hears the value and the line apart ("4 3 In
// Sensei", not "43 In Sensei"); the span is a block, so nothing moves on screen.
ck( 'a cell\'s second line: a space, then one span with the part\'s class, its words escaped, put in a cell after its value', array(
	WPCPM_UI::cell_sub( '3 In Sensei, 1 <b>Paused</b>' ),
	WPCPM_UI::cell( $cols[1], '4' . WPCPM_UI::cell_sub( '3 In Sensei' ) ),
), array(
	' <span class="wpcpm-ui-cell__sub">3 In Sensei, 1 &lt;b&gt;Paused&lt;/b&gt;</span>',
	'<td class="wpcpm-ui-cell--num" role="cell" data-label="Students">4 <span class="wpcpm-ui-cell__sub">3 In Sensei</span></td>',
) );

// Task 17, fix round 1: a table of seven or more columns says it is wide, and the sheet stacks it while the dashboard's
// main area is too narrow for it; a table that does not say so prints what it printed before, byte for byte.
ck( 'table_open: "wide" prints the modifier after the table\'s other classes; false, absent or empty prints today\'s table; the tables that need more room than a narrow main area pass it (Task 18f, fix round 2)', array(
	WPCPM_UI::table_open( $cols, array( 'wide' => true, 'labelledby' => 'h-1' ) ),
	WPCPM_UI::table_open( $cols, array( 'wide' => true, 'end' => true ) ),
	WPCPM_UI::table_open( $cols, array( 'wide' => false ) ) === $t,
	WPCPM_UI::table_open( $cols, array( 'wide' => '' ) ) === $t,
	array_values( array_filter( array_map( static function ( $file ) {
		// Files that draw the table part and pass it "wide" (a form's own "wide" option elsewhere is another thing).
		$src = (string) file_get_contents( $file );
		return false !== strpos( $src, 'WPCPM_UI::table_open(' ) && preg_match_all( "/'wide'\s*=>\s*(true|936)/", $src ) ? basename( $file ) . ' ' . preg_match_all( "/'wide'\s*=>\s*(true|936)/", $src ) : '';
	}, array_merge( glob( __DIR__ . '/../includes/*.php' ), glob( __DIR__ . '/../includes/modules/*.php' ) ) ) ) ),
	// Task 18d: 936 says the table needs about 936px of the main area: it is wide, and stacks below that too.
	WPCPM_UI::table_open( $cols, array( 'wide' => 936 ) ),
	WPCPM_UI::table_open( $cols, array( 'wide' => 935 ) ) === WPCPM_UI::table_open( $cols, array( 'wide' => true ) ),
), array(
	str_replace( '<table class="wpcpm-ui-table wpcpm-ui-table--pairs-2" role="table">', '<table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--pairs-2" role="table" aria-labelledby="h-1">', $t ),
	str_replace( '<table class="wpcpm-ui-table ', '<table class="wpcpm-ui-table wpcpm-ui-table--end wpcpm-ui-table--wide ', $t ),
	true,
	true,
	// Task 18f, fix round 2: Programs running and the Semester reports family (936) in the cards, the tables that need
	// more than a main area of 800px; the month table in the cohort view.
	// Fix round 3: Syncs and health and Students gone quiet again (true), as their least widths exceed 766px of card.
	// 1.123.93: Students waiting to graduate (true, 880), five columns, about 560px least width as measured with sixty rows.
	array( 'class-wpcpm-administrators-cards.php 5', 'class-wpcpm-cohort-timeline-view.php 1' ),
	str_replace( '<table class="wpcpm-ui-table ', '<table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--wide-936 ', $t ),
	true,
) );
// Task 18e: a cell with nothing in it, or only white space, is marked blank, so a stacked table draws neither it nor its
// column's name; a word kept for an empty value ("Never") is not blank; a filled cell prints as it did.
ck( 'cell(): a cell holding nothing or white space is marked blank, after its other classes; a word, an empty value kept in words and a filled cell are not', array(
	WPCPM_UI::cell( array( 'label' => 'Last error', 'kind' => 'text' ), '' ),
	WPCPM_UI::cell( array( 'label' => 'Action', 'kind' => 'action' ), " \n " ),
	WPCPM_UI::cell( array( 'label' => 'Last run', 'kind' => 'date' ), 'Never', 'empty' ),
	WPCPM_UI::cell( array( 'label' => 'A', 'kind' => 'text' ), 'x' ),
	WPCPM_UI::cell( array( 'label' => 'A', 'kind' => 'text', 'header' => 'row' ), '' ),
), array(
	'<td class="wpcpm-ui-cell--text wpcpm-ui-cell--blank" role="cell" data-label="Last error"></td>',
	'<td class="wpcpm-ui-cell--action wpcpm-ui-cell--blank" role="cell" data-label="Action"> ' . "\n" . ' </td>',
	'<td class="wpcpm-ui-cell--date wpcpm-ui-cell--empty" role="cell" data-label="Last run">Never</td>',
	'<td class="wpcpm-ui-cell--text" role="cell" data-label="A">x</td>',
	'<th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead"></th>',
) );
// Task 18f, fix round 2: a table says how many pairs a stacked row holds, its row header aside; a key and value table
// says nothing.
ck( 'table_open(): the pairs a stacked row holds, the row header not counted; none on a key and value table', array(
	false !== strpos( WPCPM_UI::table_open( array( array( 'label' => 'A', 'header' => 'row' ), array( 'label' => 'B' ), array( 'label' => 'C' ) ) ), 'class="wpcpm-ui-table wpcpm-ui-table--pairs-2"' ),
	false !== strpos( WPCPM_UI::table_open( array( array( 'label' => 'A' ), array( 'label' => 'B' ) ) ), 'class="wpcpm-ui-table wpcpm-ui-table--pairs-2"' ),
	false !== strpos( WPCPM_UI::table_open( array( array( 'label' => 'A', 'header' => true ), array( 'label' => 'B' ) ), array( 'keyvalue' => true ) ), 'pairs' ),
	false !== strpos( WPCPM_UI::table_open( array_fill( 0, 12, array( 'label' => 'A' ) ) ), 'wpcpm-ui-table--pairs-9"' ),
), array( true, true, false, true ) );
// Task 18f, fix round 3: the action and a cell that joins the header line are not pairs; a joining column's cells say so.
ck( 'table_open() counts no action and no joining cell as a pair; cell() marks a joining cell', array(
	false !== strpos( WPCPM_UI::table_open( array( array( 'label' => 'A', 'header' => 'row' ), array( 'label' => 'B' ), array( 'label' => '', 'kind' => 'action' ), array( 'label' => 'C', 'kind' => 'short', 'joins' => true ) ) ), 'class="wpcpm-ui-table wpcpm-ui-table--pairs-1"' ),
	WPCPM_UI::cell( array( 'label' => 'Outlook', 'kind' => 'short', 'joins' => true ), 'x' ),
), array( true, '<td class="wpcpm-ui-cell--short wpcpm-ui-cell--joins" role="cell" data-label="Outlook">x</td>' ) );
// Task 18d: a run of words that never breaks inside itself (a date in a range that may break between its dates).
ck( 'whole(): one span with the part\'s class, its words escaped', WPCPM_UI::whole( '2026-12-01 <b>' ), '<span class="wpcpm-ui-whole">2026-12-01 &lt;b&gt;</span>' );

// Task 17, fix round 1: a wide table's row header names its row; a key and value table's label cell is as it was.
$t17_row = array( 'key' => 'name', 'label' => 'Name', 'kind' => 'text', 'width' => '', 'header' => 'row' );
ck( 'cell: header "row" is a row header with the row header\'s class, still scope="row" and role="rowheader", no data-label; header true is the key and value label, byte for byte as before; empty kept after it', array(
	WPCPM_UI::cell( $t17_row, '<a href="#">Example Co</a>' ),
	WPCPM_UI::cell( $t17_row, 'x', 'empty' ),
	WPCPM_UI::cell( array_merge( $t17_row, array( 'kind' => 'date' ) ), 'November 2026' ),
	WPCPM_UI::cell( $kv, 'School' ),
	WPCPM_UI::cell( $kv, 'x', 'empty' ),
), array(
	'<th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead"><a href="#">Example Co</a></th>',
	'<th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead wpcpm-ui-cell--empty">x</th>',
	'<th scope="row" role="rowheader" class="wpcpm-ui-cell--date wpcpm-ui-cell--rowhead">November 2026</th>',
	'<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">School</th>',
	'<th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--empty">x</th>',
) );
$media = '<img class="wpcpm-ui-list__media-img" src="https://example.org/logo.png" alt="">';
ck( 'a row with media: the box first in the row, before the line, the markup as given, and the row marked; with a note the note modifier first', array(
	WPCPM_UI::row( array( 'name' => 'Example Co', 'media' => $media ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'note' => 'Hello.', 'media' => '<span class="wpcpm-ui-list__media-initials" aria-hidden="true">A</span>' ) ),
), array(
	'<li class="wpcpm-ui-list__row wpcpm-ui-list__row--media"><span class="wpcpm-ui-list__media">' . $media . '</span><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Example Co</span><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row wpcpm-ui-list__row--note wpcpm-ui-list__row--media"><span class="wpcpm-ui-list__media"><span class="wpcpm-ui-list__media-initials" aria-hidden="true">A</span></span><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div><p class="wpcpm-ui-list__note">Hello.</p></li>',
) );
ck( 'a row without media, or with empty media, prints byte for byte what it printed before', array(
	WPCPM_UI::row( array( 'name' => 'Example Co', 'tag' => array( 'words' => 'Signed', 'tone' => 'green' ), 'facts' => array( '2026', array( 'html' => '<b>x</b>' ) ), 'actions' => array( $act ), 'media' => '' ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'note' => 'Page two is missing.', 'form' => '<form></form>', 'form_id' => 'n-1' ) ),
), array(
	$r,
	'<li class="wpcpm-ui-list__row wpcpm-ui-list__row--note"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div><p class="wpcpm-ui-list__note">Page two is missing.</p><div class="wpcpm-ui-list__form" id="n-1"><form></form></div></li>',
) );

// Task 16, fix round 1 (review points): an intro line may carry an id; a row's name may be a heading.
ck( 'an intro line with an id prints it after the class, escaped; without one it prints as it did', array(
	WPCPM_UI::intro( 'Press a code.', 'muted', 'hint"1' ),
	WPCPM_UI::intro( 'Say why.', '', '' ),
	WPCPM_UI::intro( 'Say why.' ),
), array(
	'<p class="wpcpm-ui-intro wpcpm-ui-intro--muted" id="hint&quot;1">Press a code.</p>',
	'<p class="wpcpm-ui-intro">Say why.</p>',
	'<p class="wpcpm-ui-intro">Say why.</p>',
) );
ck( 'a row with a level prints its name inside that heading, with the name\'s class; h4 and h5 only; any other level, or none, prints the span as before', array(
	WPCPM_UI::row( array( 'name' => 'Example Co', 'href' => 'https://example.org/', 'level' => 'h4' ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'level' => 'h5' ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'level' => 'h2' ) ),
	WPCPM_UI::row( array( 'name' => 'A', 'level' => '' ) ),
), array(
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><h4 class="wpcpm-ui-list__name"><a href="https://example.org/">Example Co</a></h4><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><h5 class="wpcpm-ui-list__name">A</h5><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
	'<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">A</span><span class="wpcpm-ui-list__facts"></span></div></li>',
) );

// Task 17b, ruling 1: a note a person typed, kept with its line breaks, as the parts' own paragraph.
ck( 'a note: one paragraph of the note part, its text escaped and its line breaks left as typed for the sheet to keep', array(
	WPCPM_UI::note( "Page two is missing.\nPlease <b>sign</b> it." ),
	WPCPM_UI::note( 'Held & checked "twice".' ),
), array(
	"<p class=\"wpcpm-ui-note\">Page two is missing.\nPlease &lt;b&gt;sign&lt;/b&gt; it.</p>",
	'<p class="wpcpm-ui-note">Held &amp; checked &quot;twice&quot;.</p>',
) );

// Task 17b, ruling 7: an item's article on the parts, its own names kept and the part's class after them.
ck( 'an item\'s article: the names it is known by first, as given, then the part\'s class; its id when it has one, escaped', array(
	WPCPM_UI::item_open( 'wpcpm-request wpcpm-request--details' ),
	WPCPM_UI::item_open( 'wpcpm-application', 'wpcpm-application-12' ),
	WPCPM_UI::item_open( 'a" b', 'x"1' ),
), array(
	'<article class="wpcpm-request wpcpm-request--details wpcpm-ui-item">',
	'<article class="wpcpm-application wpcpm-ui-item" id="wpcpm-application-12">',
	'<article class="a&quot; b wpcpm-ui-item" id="x&quot;1">',
) );

// Task 18a, point 1: the agreement review's checklist is a plain list the helper prints from plain sentences, each
// line with a class of its own (the sheet keys the room between lines on it, never on an element); none for no lines.
ck( 'a checklist: one list, each sentence a line with the line\'s own class, escaped; no lines print nothing', array(
	WPCPM_UI::checklist( array( 'The footer names <b>2025-11-04</b>.', 'The block is "filled" in & signed.' ) ),
	WPCPM_UI::checklist( array() ),
), array(
	'<ul class="wpcpm-ui-checklist"><li class="wpcpm-ui-checklist__item">The footer names &lt;b&gt;2025-11-04&lt;/b&gt;.</li><li class="wpcpm-ui-checklist__item">The block is &quot;filled&quot; in &amp; signed.</li></ul>',
	'',
) );

// Task 18a, point 2: an intro line may hold markup already escaped (a sentence with its link after its words); its text
// argument is escaped as before, and a caller passing text alone prints byte for byte what it printed.
ck( 'an intro line from markup already escaped prints it as given, in place of the text, with the tone and the id; text alone prints as it did', array(
	WPCPM_UI::intro( '', '', '', 'Mail sent. <a href="https://example.test/log">Open the log</a>' ),
	WPCPM_UI::intro( 'ignored <b>', 'muted', 'h1', '<a href="#x">x</a>' ),
	WPCPM_UI::intro( 'Say <b>why</b>.', '', '', '' ),
	WPCPM_UI::intro( 'Say <b>why</b>.' ),
), array(
	'<p class="wpcpm-ui-intro">Mail sent. <a href="https://example.test/log">Open the log</a></p>',
	'<p class="wpcpm-ui-intro wpcpm-ui-intro--muted" id="h1"><a href="#x">x</a></p>',
	'<p class="wpcpm-ui-intro">Say &lt;b&gt;why&lt;/b&gt;.</p>',
	'<p class="wpcpm-ui-intro">Say &lt;b&gt;why&lt;/b&gt;.</p>',
) );

// Task 18a, point 2: a part's class written by hand outside the helpers and the card's shell is one no helper prints
// (the Mentor applications row's fold, the Overview group's note, the logos part kept with its one screen, a row's media
// the caller builds as row() asks); every other part class comes from a helper. Read from the source.
$by_hand = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/includes', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	$rel = substr( $file->getPathname(), strlen( dirname( __DIR__ ) ) + 1 );
	if ( '.php' !== substr( $rel, -4 ) || in_array( $rel, array( 'includes/class-wpcpm-ui.php', 'includes/class-wpcpm-dashboard-card.php' ), true ) ) {
		continue;
	}
	if ( preg_match_all( '/wpcpm-ui-[a-z0-9_-]+/', (string) file_get_contents( $file->getPathname() ), $m ) ) {
		$names = array_values( array_unique( $m[0] ) );
		sort( $names );
		$by_hand[ $rel ] = $names;
	}
}
ksort( $by_hand );
ck( 'the part classes written by hand outside the helpers are only those no helper prints', $by_hand, array(
	'includes/modules/class-wpcpm-administrators-dashboard.php' => array( 'wpcpm-ui-fold', 'wpcpm-ui-fold--row', 'wpcpm-ui-fold-card', 'wpcpm-ui-fold__head', 'wpcpm-ui-fold__help', 'wpcpm-ui-group__note' ),
	// Task V1: an agreement's review is a block of its item (ruling 12); a code in a cell (ruling 8). The Tools section draws
	// today's offers on the parts (ruling 5), so it builds no row media by hand any more.
	// Institution Dashboard, Task 17: Enroll students is the view's content, a block with no head (M22); its two field sets
	// are the choice group's fieldset with an in-card heading as their legend, 24px under the field above (no part prints
	// a set of fields).
	'includes/modules/class-wpcpm-institution-import-form.php'  => array( 'wpcpm-ui-block', 'wpcpm-ui-choices', 'wpcpm-ui-choices--set', 'wpcpm-ui-h' ),
	'includes/modules/class-wpcpm-institution-panel.php'        => array( 'wpcpm-ui-block', 'wpcpm-ui-item__part' ),
	// Institution Dashboard, Task 16: the sign-in line is a block of the student's card, and so is the card itself.
	'includes/modules/class-wpcpm-institution-resend.php'       => array( 'wpcpm-ui-block' ),
	// Institution Dashboard, Task 15: the roster's groups print the card fold's section, disclosure, head and body by hand (planner's P7: a card shell would be drawn open); the filter bar's row of fields; a view's blocks. Each student is inner_open().
	'includes/modules/class-wpcpm-institution-roster-view.php'  => array( 'wpcpm-ui-block', 'wpcpm-ui-fields', 'wpcpm-ui-fold', 'wpcpm-ui-fold-card', 'wpcpm-ui-fold__body', 'wpcpm-ui-fold__head' ),
	'includes/modules/class-wpcpm-institution-student-view.php' => array( 'wpcpm-ui-block' ),
	// Institution Dashboard, Task 18: Semester reports is the view's content, a block with no head (M22); the report's document, as it prints, in a block that lets a long word break.
	'includes/modules/class-wpcpm-semester-report-screen.php'   => array( 'wpcpm-ui-block', 'wpcpm-ui-doc' ),
	'includes/modules/class-wpcpm-sponsor-application.php'      => array( 'wpcpm-ui-logo', 'wpcpm-ui-logo--white', 'wpcpm-ui-logo__caption', 'wpcpm-ui-logo__img', 'wpcpm-ui-logos' ),
	'includes/modules/class-wpcpm-sponsor-event-list.php'       => array( 'wpcpm-ui-block' ), // Institution Dashboard, Task 22: the Events view's content, a block with no head.
	'includes/modules/class-wpcpm-sponsor-offer-review.php'     => array( 'wpcpm-ui-code' ),
) );


// 1.123.93, fix round 2: a long unbroken run in typed words gets a break opportunity after every 10 characters; every
// shorter word is exactly what esc_html() gives. Characters are grapheme clusters; an entity counts as one.
$bk_20  = str_repeat( 'abcdefghij', 2 );
$bk_21  = $bk_20 . 'k';
$bk_mb  = str_repeat( "\u{0141}o\u{0301}dz\u{0301}", 5 ) . "\u{017B}";
$bk_mbu = array();
preg_match_all( '/\X/u', $bk_mb, $bk_mbu );
ck( 'breakable: twenty characters, a hyphenated or spaced long run, and plain words are byte-identical to esc_html()', array(
	WPCPM_UI::breakable( $bk_20 ) === esc_html( $bk_20 ),
	WPCPM_UI::breakable( 'Uniwersytet <Alpha> & Co' ) === esc_html( 'Uniwersytet <Alpha> & Co' ),
	WPCPM_UI::breakable( 'abcdefghijklmno-pqrstuvwxyz' ) === esc_html( 'abcdefghijklmno-pqrstuvwxyz' ),
	WPCPM_UI::breakable( 'abcdefghijklmno pqrstuvwxyz' ) === esc_html( 'abcdefghijklmno pqrstuvwxyz' ),
	WPCPM_UI::breakable( '' ),
), array( true, true, true, true, '' ) );
ck( 'breakable: twenty-one characters get a break opportunity after the tenth and the twentieth; forty get three', array(
	WPCPM_UI::breakable( $bk_21 ),
	substr_count( WPCPM_UI::breakable( 'Ab' . str_repeat( 'c', 38 ) ), '<wbr>' ),
	WPCPM_UI::breakable( 'Ola ' . $bk_21 . ' Older' ),
), array( 'abcdefghij<wbr>abcdefghij<wbr>k', 3, 'Ola abcdefghij<wbr>abcdefghij<wbr>k Older' ) );
ck( 'breakable: a multibyte run is counted in characters a reader sees, a letter and its combining mark never parted', array(
	count( $bk_mbu[0] ),
	WPCPM_UI::breakable( $bk_mb ),
), array( 21, implode( '', array_slice( $bk_mbu[0], 0, 10 ) ) . '<wbr>' . implode( '', array_slice( $bk_mbu[0], 10, 10 ) ) . '<wbr>' . $bk_mbu[0][20] ) );
ck( 'breakable: markup in a long run is still escaped, an entity counted as one character, and the output sits inside a link', array(
	WPCPM_UI::breakable( '<b>' . $bk_20 . '</b>' ),
	WPCPM_UI::breakable( str_repeat( '&amp;', 21 ) ),
	'<a href="https://example.test/">' . WPCPM_UI::breakable( $bk_21 ) . '</a>',
), array(
	'&lt;b&gt;abcdefg<wbr>hijabcdefg<wbr>hij&lt;/b&gt;',
	esc_html( str_repeat( '&amp;', 10 ) ) . '<wbr>' . esc_html( str_repeat( '&amp;', 10 ) ) . '<wbr>' . esc_html( '&amp;' ),
	'<a href="https://example.test/">abcdefghij<wbr>abcdefghij<wbr>k</a>',
) );
ck( 'cell_sub: with markup it prints the markup the caller escaped; with none, today\'s line', array(
	WPCPM_UI::cell_sub( 'ignored', '<a href="x">School</a> <span class="wpcpm-badge wpcpm-badge--test">Test</span>' ),
	WPCPM_UI::cell_sub( 'A <b>' ) === WPCPM_UI::cell_sub( 'A <b>', '' ),
), array( ' <span class="wpcpm-ui-cell__sub"><a href="x">School</a> <span class="wpcpm-badge wpcpm-badge--test">Test</span></span>', true ) );
// The table of Students waiting to graduate prints the helper, the Institution Dashboard's Enroll students check
// table (a data table's cell) and the head's title (the layout harness faults without it, Task 22b); no other file. A later task that needs it elsewhere changes this pin
// on purpose and says so.
ck( 'breakable: called by the Students waiting to graduate table, the Enroll students check table and the Institution Dashboard head alone', array_values( array_filter( array_map( static function ( $file ) {
	return preg_match( '/WPCPM_UI::breakable\(/', (string) file_get_contents( $file ) ) ? basename( $file ) : '';
}, array_merge( glob( __DIR__ . '/../includes/*.php' ), glob( __DIR__ . '/../includes/modules/*.php' ) ) ) ) ), array( 'class-wpcpm-administrators-cards.php', 'class-wpcpm-institution-import-form.php', 'class-wpcpm-institutions-dashboard.php' ) );

// Institution Dashboard, Task 18 fix round 2: note() may print a quotation (a student's released words) and intro() may
// carry a class of the caller's (a line the print sheet hides). Without the new argument each prints the bytes it printed
// before, pinned by digests taken at 24db6e4b with real Administrator Dashboard arguments.
ck( 'note() and intro() without the new argument: the base\'s bytes', array(
	md5( WPCPM_UI::note( 'Held by the anti-spam checks. Open it on the Institutions screen to read which.' ) ),
	md5( WPCPM_UI::note( "A note <b>&\"x\"\nline two" ) ),
	md5( WPCPM_UI::intro( 'Approved sponsors by tier, as the tiers stand today.' ) ),
	md5( WPCPM_UI::intro( 'Two waiting & <i>more</i>', 'muted' ) ),
	md5( WPCPM_UI::intro( 'ignored', '', 'wpcpm-x-id', '<a href="#">Link</a> after' ) ),
	WPCPM_UI::note( 'x', 'p' ) === WPCPM_UI::note( 'x' ),
	WPCPM_UI::note( 'x', 'div' ) === WPCPM_UI::note( 'x' ),
	WPCPM_UI::intro( 'x', 'muted', '', '', '' ) === WPCPM_UI::intro( 'x', 'muted' ),
), array( 'f5e90f6c5f94ee0db85304a6107c72e3', '38cd0e8282c5534e2373b14d4682c5f0', '6e6e417063e0ee4879f180ba81408c40', 'a22e9e7d0526614a2adf097c92466d53', 'dae59293860c41b573ce060dc249a299', true, true, true ) );
ck( 'note() as a quotation, and intro() with a class of the caller\'s', array(
	WPCPM_UI::note( 'Said <b>"so"</b> & more', 'blockquote' ),
	WPCPM_UI::intro( 'Kept & <i>hint</i>', 'muted', '', '', 'wpcpm-report-hint' ),
	WPCPM_UI::intro( 'Kept', '', 'an-id', '', 'a-b "c"' ),
), array(
	'<blockquote class="wpcpm-ui-note">Said &lt;b&gt;&quot;so&quot;&lt;/b&gt; &amp; more</blockquote>',
	'<p class="wpcpm-ui-intro wpcpm-ui-intro--muted wpcpm-report-hint">Kept &amp; &lt;i&gt;hint&lt;/i&gt;</p>',
	'<p class="wpcpm-ui-intro a-b &quot;c&quot;" id="an-id">Kept</p>',
) );

// Institution Dashboard, Task 19: field() takes the box's `inputmode` (the details card's website and email, as today's form
// draws them), and choices() takes a group's hint, a group's refusal (`group_problem`) and `columns` (the application's
// languages). Without them each prints the base's bytes, pinned by digests taken at eb42e70a with the Administrator
// Dashboard's own arguments (the agreement's return note; a sponsor application's Approve box) and a group of each kind.
ck( 'field() and choices() without the new arguments: the base\'s bytes', array(
	md5( WPCPM_UI::field( array( 'id' => 'wpcpm-agreement-review-7-note', 'name' => 'wpcpm_agreement_note', 'label' => 'What has to change', 'hint' => 'In your own words. It is emailed to everybody at the institution exactly as you write it, with your address to reply to.', 'need' => 'required', 'type' => 'textarea', 'rows' => 4, 'value' => '', 'attrs' => array( 'minlength' => 20, 'maxlength' => 2000 ), 'problem' => '' ) ) ),
	md5( WPCPM_UI::field( array( 'id' => 'w', 'name' => 'w', 'label' => 'Website', 'value' => 'example.org', 'attrs' => array( 'maxlength' => 200, 'autocomplete' => 'url' ), 'hint' => 'For example: example.edu' ) ) ),
	md5( WPCPM_UI::choices( array( 'id' => 'wpcpm_test-41', 'name' => 'wpcpm_test', 'type' => 'checkbox', 'options' => array( '1' => 'Test sponsor: not listed publicly' ), 'checked' => '1' ) ) ),
	md5( WPCPM_UI::choices( array( 'id' => 'aud-7', 'legend' => 'Who sees it', 'name' => 'wpcpm_aud[]', 'type' => 'checkbox', 'options' => array( 'students' => array( 'label' => 'Students <all>', 'hint' => 'Every "student" & graduate.' ), 'mentors' => 'Mentors' ), 'checked' => array( 'students', 'mentors' ), 'need' => 'optional' ) ) ),
	md5( WPCPM_UI::choices( array( 'id' => 'tier-7', 'legend' => 'Which level?', 'name' => 'wpcpm_tier', 'type' => 'radio', 'options' => array( 'a' => 'Supporter', 'b' => 'Backer' ), 'checked' => 'b', 'need' => 'required', 'hint' => '', 'group_problem' => '', 'columns' => false ) ) ),
), array( '54797ebd8ec1a81b4f295fdb927a7378', '4ec3f08b536eab7aae40cb4ccc62c1bf', '5e0594a9a521c9dac94924fa210f4a85', '6d211b1a3d26314669bd1a97c616baa2', 'f7de81e67d29e54e7d90347308f36144' ) );
ck( 'field(): the box\'s inputmode, after its other attributes; a value outside the browser\'s own is not printed', array(
	WPCPM_UI::field( array( 'id' => 'w', 'name' => 'w', 'label' => 'Website', 'value' => 'a"b', 'attrs' => array( 'maxlength' => 200, 'autocomplete' => 'url', 'inputmode' => 'url' ) ) ),
	false === strpos( WPCPM_UI::field( array( 'id' => 'w', 'name' => 'w', 'label' => 'W', 'attrs' => array( 'inputmode' => 'x" onclick="y' ) ) ), 'inputmode' ),
), array(
	'<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="w">Website</label><input type="text" class="wpcpm-ui-field__control" id="w" name="w" maxlength="200" autocomplete="url" inputmode="url" value="a&quot;b"></div>',
	true,
) );
ck( 'choices(): a group\'s hint under its legend and its refusal under its options, both named by the group; in columns, the options in a grid', array(
	WPCPM_UI::choices( array( 'id' => 'lang', 'legend' => 'Languages <b>', 'name' => 'lang[]', 'options' => array( 'en' => 'English', 'pl' => 'Polish & more' ), 'checked' => array( 'pl' ), 'need' => 'required', 'hint' => 'Tick "every" one.', 'group_problem' => 'Fill <this> in.', 'columns' => true ) ),
	WPCPM_UI::choices( array( 'id' => 'tr', 'legend' => 'Tracks', 'name' => 'tr[]', 'options' => array( 'a' => 'A' ), 'hint' => 'A hint.' ) ),
), array(
	'<fieldset class="wpcpm-ui-choices wpcpm-ui-choices--columns wpcpm-ui-field" aria-describedby="lang-problem lang-hint"><legend class="wpcpm-ui-choices__legend">Languages &lt;b&gt; <span class="wpcpm-ui-field__need">Required</span></legend><p class="wpcpm-ui-field__hint" id="lang-hint">Tick &quot;every&quot; one.</p><div class="wpcpm-ui-choices__grid"><label class="wpcpm-ui-choice" for="lang-en"><input type="checkbox" class="wpcpm-ui-choice__input" id="lang-en" name="lang[]" value="en"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="lang-en-words">English</span></span></label><label class="wpcpm-ui-choice" for="lang-pl"><input type="checkbox" class="wpcpm-ui-choice__input" id="lang-pl" name="lang[]" value="pl" checked><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="lang-pl-words">Polish &amp; more</span></span></label></div><p class="wpcpm-field__problem" id="lang-problem">Fill &lt;this&gt; in.</p></fieldset>',
	'<fieldset class="wpcpm-ui-choices" aria-describedby="tr-hint"><legend class="wpcpm-ui-choices__legend">Tracks</legend><p class="wpcpm-ui-field__hint" id="tr-hint">A hint.</p><label class="wpcpm-ui-choice" for="tr-a"><input type="checkbox" class="wpcpm-ui-choice__input" id="tr-a" name="tr[]" value="a"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="tr-a-words">A</span></span></label></fieldset>',
) );
ck( 'choices(): a hint, a group refusal and columns are a group\'s alone: a checkbox alone prints as before', array(
	md5( WPCPM_UI::choices( array( 'id' => 'x', 'name' => 'x', 'options' => array( '1' => 'One' ), 'need' => 'required', 'hint' => 'H', 'group_problem' => 'P', 'columns' => true ) ) ),
), array( '285eca76d9a7d3394ca9ced9bcfde144' ) );

echo "\n" . ( $fail ? "$fail of $checks checks failed" : "all $checks checks passed" ) . "\n";
exit( $fail ? 1 : 0 );
