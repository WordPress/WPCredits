<?php
/**
 * WPCPM_UI::file(), the file box (parts design 2.4 "File box", 3.2 job 3), its rules in assets/css/parts.css and its
 * job in assets/js/parts.js, run by node on the box's own markup (bin/js/parts-file.js).
 *
 * Run from the plugin root:  php bin/test-parts-file.php
 *
 * What each block pins, and why:
 *
 * - **The markup**: a required box with a hint is the field's label, Required after it, the browser's own file
 *   control, then a row holding the secondary button "Choose a file" and the name beside it ("No file chosen"),
 *   both printed `hidden`, and the hint tied to the control. `required` is on the control only, the label's `for`
 *   is the control, there is no `multiple`; a refused box ties its problem line first, as field() does. With no
 *   script nothing a person needs is `hidden`: only the button and the name, which need the script to work.
 * - **The words**: "Choose a file" and "No file chosen" come from PHP, translatable with the plugin's text domain;
 *   the script holds no words of its own.
 * - **The rules**: the row's 12px gap, the name muted 14px, a long name breaking inside its box, the hidden control
 *   clipped (never `display: none`, never `visibility: hidden`), the ring drawn on the button while the control has
 *   the focus, and the printed `hidden` kept against the frame's `display` of a button.
 * - **What the script does**, run by node on the markup file() prints (bin/js/parts-file.js), with two mutation
 *   proofs.
 * - **The layout harness** keeps one real box (required, a hint, a problem line, a 60-character name with no
 *   space) as the script leaves it, and the same box as printed with no script, for bin/ui/layout/run.mjs.
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

// A translation the suite can switch on, so a test can see that the words come from the translated strings.
$GLOBALS['wpcpm_test_l10n']    = array();
$GLOBALS['wpcpm_test_domains'] = array();
$GLOBALS['wpcpm_test_scripts'] = array();

function wpcpm_test_translate( $s, $d ) {
	$GLOBALS['wpcpm_test_domains'][ (string) $s ] = $d;
	return $GLOBALS['wpcpm_test_l10n'][ $s ] ?? $s;
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return esc_attr( $url ); }
function is_admin() { return false; }
function __( $s, $d = null ) { return wpcpm_test_translate( $s, $d ); }
function esc_html__( $s, $d = null ) { return esc_html( wpcpm_test_translate( $s, $d ) ); }
function wp_style_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {}
function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {}
function wp_enqueue_script( $handle ) { $GLOBALS['wpcpm_test_scripts'][] = $handle; }
function _doing_it_wrong( $function_name, $message, $version ) {}
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }

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

/**
 * The box, or '' while WPCPM_UI has no file() (so the checks fail one by one instead of the suite stopping).
 *
 * @param array $args file()'s arguments.
 * @return string
 */
function box( array $args ) {
	return method_exists( 'WPCPM_UI', 'file' ) ? WPCPM_UI::file( $args ) : '';
}

/**
 * The markup as a DOM, for the checks that read an element's attributes.
 *
 * @param string $html Markup.
 * @return DOMXPath
 */
function dom( $html ) {
	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	return new DOMXPath( $doc );
}

/**
 * One attribute of the first element a query finds, or null when it finds none.
 *
 * @param DOMXPath $x     The markup.
 * @param string   $query The query.
 * @param string   $attr  The attribute, or '' for the element's text.
 * @return string|null
 */
function first( $x, $query, $attr = '' ) {
	$node = $x->query( $query )->item( 0 );
	if ( ! $node ) {
		return null;
	}
	return '' === $attr ? trim( $node->textContent ) : $node->getAttribute( $attr );
}

$agreement = array(
	'id'     => 'wpcpm-agreement-file',
	'name'   => 'wpcpm_agreement_file',
	'label'  => 'Signed agreement',
	'accept' => 'application/pdf,.pdf',
	'need'   => 'required',
	'hint'   => 'A PDF of the signed Collaboration Agreement.',
);

echo "=== The markup: a required box with a hint ===\n";
$GLOBALS['wpcpm_test_scripts'] = array();
$html                          = box( $agreement );
ck(
	'the label and Required, the browser\'s control, the row with Choose a file and No file chosen (both hidden), the hint tied to the control',
	$html,
	'<div class="wpcpm-ui-field wpcpm-ui-file"><label class="wpcpm-ui-field__label" for="wpcpm-agreement-file">Signed agreement <span class="wpcpm-ui-field__need">Required</span></label>'
	. '<input class="wpcpm-ui-file__input" type="file" id="wpcpm-agreement-file" name="wpcpm_agreement_file" accept="application/pdf,.pdf" required aria-describedby="wpcpm-agreement-file-hint">'
	. '<span class="wpcpm-ui-file__row"><button type="button" class="wpcpm-btn wpcpm-btn--secondary wpcpm-ui-file__choose" hidden aria-controls="wpcpm-agreement-file">Choose a file</button>'
	. '<span class="wpcpm-ui-file__name" aria-live="polite" hidden>No file chosen</span></span>'
	. '<p class="wpcpm-ui-field__hint" id="wpcpm-agreement-file-hint">A PDF of the signed Collaboration Agreement.</p></div>'
);
ck( 'drawing a box enqueues the parts\' script, which drives it', $GLOBALS['wpcpm_test_scripts'], array( 'wpcpm-parts' ) );

$x = dom( $html );
ck( 'required is on the control and on nothing else', array( $x->query( '//*[@required]' )->length, $x->query( '//input[@type="file"][@required]' )->length ), array( 1, 1 ) );
ck( 'the label\'s for is the control\'s id, and the button controls it', array( first( $x, '//label', 'for' ), first( $x, '//button', 'aria-controls' ) ), array( 'wpcpm-agreement-file', 'wpcpm-agreement-file' ) );
ck( 'the box is for one file: no multiple', $x->query( '//*[@multiple]' )->length, 0 );
ck( 'with no script nothing a person needs is hidden: the button and the name alone carry hidden, the control does not', array_map( function ( $node ) { return $node->getAttribute( 'class' ); }, iterator_to_array( $x->query( '//*[@hidden]' ) ) ), array( 'wpcpm-btn wpcpm-btn--secondary wpcpm-ui-file__choose', 'wpcpm-ui-file__name' ) );
ck( 'the name is announced politely and reads No file chosen before a choice', array( $x->query( '//span[@aria-live="polite"]' )->length, first( $x, '//*[@aria-live]' ) ), array( 1, 'No file chosen' ) );
ck( 'the button is a button (never a submit), the secondary kind', $x->query( '//button[@type="button"][contains(@class,"wpcpm-btn--secondary")]' )->length, 1 );

echo "\n=== The markup: refused, optional, plain, escaped ===\n";
$refused = box( $agreement + array( 'problem' => 'Choose the signed agreement, a PDF.' ) );
$rx      = dom( $refused );
ck( 'a refused box: aria-invalid, the problem line first in aria-describedby, then the hint, and the field\'s own problem paragraph after the hint', array( first( $rx, '//input', 'aria-invalid' ), first( $rx, '//input', 'aria-describedby' ), 1 === preg_match( '#</p><p class="wpcpm-field__problem" id="wpcpm-agreement-file-problem">Choose the signed agreement, a PDF\.</p></div>$#', $refused ) ), array( 'true', 'wpcpm-agreement-file-problem wpcpm-agreement-file-hint', true ) );
$optional = box( array( 'id' => 'wpcpm-import-file', 'name' => 'list', 'label' => 'Your list', 'accept' => '.csv,text/csv', 'need' => 'optional' ) );
ck( 'an optional box: Optional after the label, no required, no aria-describedby and no hint when there is none', array( false !== strpos( $optional, 'Your list <span class="wpcpm-ui-field__need">Optional</span></label>' ), false !== strpos( $optional, 'required' ), false !== strpos( $optional, 'aria-describedby' ), false !== strpos( $optional, 'wpcpm-ui-field__hint' ) ), array( true, false, false, false ) );
$plain = box( array( 'id' => 'wpcpm-plain', 'name' => 'plain', 'label' => 'A file' ) );
ck( 'no need and no accept: no word after the label and no accept attribute', array( false !== strpos( $plain, 'for="wpcpm-plain">A file</label>' ), false !== strpos( $plain, 'accept=' ) ), array( true, false ) );
$data = box( array( 'id' => 'wpcpm-d', 'name' => 'd', 'label' => 'D', 'attrs' => array( 'data-wpcpm-kind' => 'list', 'onclick' => 'x()', 'multiple' => true ) ) );
ck( 'attrs: a data attribute is printed on the control; anything else (an event handler, multiple) is not', array( false !== strpos( $data, 'type="file" id="wpcpm-d" name="d" data-wpcpm-kind="list">' ), false !== strpos( $data, 'onclick' ), false !== strpos( $data, 'multiple' ) ), array( true, false, false ) );
$odd = box( array( 'id' => 'a"b', 'name' => 'n<', 'label' => 'Tom & <b>Jerry</b>', 'accept' => '"x', 'hint' => '<i>h</i>' ) );
ck( 'every value is escaped', array( false !== strpos( $odd, 'id="a&quot;b"' ), false !== strpos( $odd, 'name="n&lt;"' ), false !== strpos( $odd, 'Tom &amp; &lt;b&gt;Jerry&lt;/b&gt;</label>' ), false !== strpos( $odd, 'accept="&quot;x"' ), false !== strpos( $odd, '&lt;i&gt;h&lt;/i&gt;</p>' ), false !== strpos( $odd, '<b>' ) ), array( true, true, true, true, true, false ) );

echo "\n=== The need word is the field's own, byte for byte ===\n";
/**
 * What a label prints from its text's end to its closing tag: the need word and its gap, or ''.
 *
 * @param string $html Markup holding one label whose text is "L".
 * @return string|null
 */
function need_of( $html ) {
	return 1 === preg_match( '#>L(.*?)</label>#s', $html, $m ) ? $m[1] : null;
}
foreach ( array( 'required', 'optional', '' ) as $need ) {
	$from_field = need_of( WPCPM_UI::field( array( 'id' => 'n', 'name' => 'n', 'label' => 'L', 'need' => $need ) ) );
	ck( 'need "' . $need . '": file()\'s word after the label is field()\'s', need_of( box( array( 'id' => 'n', 'name' => 'n', 'label' => 'L', 'need' => $need ) ) ), $from_field );
}
ck( 'and field()\'s words are the ones pinned: Required, Optional, nothing', array( need_of( WPCPM_UI::field( array( 'id' => 'n', 'name' => 'n', 'label' => 'L', 'need' => 'required' ) ) ), need_of( WPCPM_UI::field( array( 'id' => 'n', 'name' => 'n', 'label' => 'L', 'need' => 'optional' ) ) ), need_of( WPCPM_UI::field( array( 'id' => 'n', 'name' => 'n', 'label' => 'L' ) ) ) ), array( ' <span class="wpcpm-ui-field__need">Required</span>', ' <span class="wpcpm-ui-field__need">Optional</span>', '' ) );

echo "\n=== The words come from PHP, translated, and the script holds none ===\n";
$GLOBALS['wpcpm_test_l10n'] = array(
	'Choose a file'  => 'Choisir un fichier',
	'No file chosen' => 'Aucun fichier choisi',
);
$translated                 = box( $agreement );
$GLOBALS['wpcpm_test_l10n'] = array();
ck( 'both new strings are translated where the box prints them', array( false !== strpos( $translated, '>Choisir un fichier</button>' ), false !== strpos( $translated, 'hidden>Aucun fichier choisi</span>' ) ), array( true, true ) );
ck( 'both with the plugin\'s text domain', array( $GLOBALS['wpcpm_test_domains']['Choose a file'] ?? null, $GLOBALS['wpcpm_test_domains']['No file chosen'] ?? null ), array( 'wpcredits-program-manager', 'wpcredits-program-manager' ) );
$script_file = __DIR__ . '/../assets/js/parts.js';
$script      = is_file( $script_file ) ? (string) file_get_contents( $script_file ) : '';
ck( 'the script holds neither string: it reads the empty words from the markup', array( false !== strpos( $script, 'No file chosen' ), false !== strpos( $script, 'Choose a file' ) ), array( false, false ) );
ck( 'and it has a file job', false !== strpos( $script, '.wpcpm-ui-file' ), true );

echo "\n=== The rules in parts.css ===\n";
$css = (string) file_get_contents( __DIR__ . '/../assets/css/parts.css' );
/**
 * The declarations of the rule whose selector list is exactly $selector, as property => value, or null.
 *
 * @param string $css      The sheet.
 * @param string $selector The selector list as written, its lines joined with ", ".
 * @return array|null
 */
function rule( $css, $selector ) {
	$plain = preg_replace( '#/\*.*?\*/#s', '', $css );
	preg_match_all( '#([^{}]+)\{([^{}]*)\}#', $plain, $all, PREG_SET_ORDER );
	foreach ( $all as $one ) {
		$sel = implode( ', ', array_map( 'trim', explode( ',', trim( $one[1] ) ) ) );
		if ( $sel !== $selector ) {
			continue;
		}
		$out = array();
		foreach ( array_filter( array_map( 'trim', explode( ';', $one[2] ) ), function ( $d ) { return '' !== $d; } ) as $decl ) {
			list( $prop, $value ) = array_map( 'trim', explode( ':', $decl, 2 ) );
			$out[ $prop ]         = $value;
		}
		return $out;
	}
	return null;
}
$row    = rule( $css, '.wpcpm-ui-file__row' );
$name   = rule( $css, '.wpcpm-ui-file__name' );
$hidden = rule( $css, '.wpcpm-ui-file .wpcpm-ui-file__input--hidden' );
$ring   = rule( $css, '.wpcpm-ui-file .wpcpm-ui-file__input:focus + .wpcpm-ui-file__row .wpcpm-ui-file__choose' );
$keep   = rule( $css, '.wpcpm-ui-file .wpcpm-ui-file__choose[hidden], .wpcpm-ui-file .wpcpm-ui-file__name[hidden]' );
ck( 'the row: the button and the name side by side, 12px apart (the parts\' gap token), centered on one line', $row ? array( $row['display'] ?? null, $row['gap'] ?? null, $row['align-items'] ?? null ) : null, array( 'flex', 'var( --wpcpm-f-gap )', 'center' ) );
ck( 'the gap token is 12px', 1 === preg_match( '#--wpcpm-f-gap:\s*12px;#', $css ), true );
ck( 'the name: muted 14px/20px, and a long name breaks inside its box (min-width 0 beside the button, the parts\' break-word)', $name ? array( $name['color'] ?? null, $name['font'] ?? null, $name['min-width'] ?? null, $name['overflow-wrap'] ?? null, $name['flex'] ?? null ) : null, array( 'var( --wpcpm-f-muted )', '400 14px/20px var( --wpcpm-f-sans )', '0', 'break-word', '1 1 auto' ) );
ck( 'the button keeps its width beside a long name', ( rule( $css, '.wpcpm-ui-file__choose' ) ?? array() )['flex'] ?? null, 'none' );
ck( 'the hidden control is clipped: absolute, 1px, inset(50%), overflow hidden', $hidden ? array( $hidden['position'] ?? null, $hidden['width'] ?? null, $hidden['height'] ?? null, $hidden['clip-path'] ?? null, $hidden['overflow'] ?? null, $hidden['margin'] ?? null ) : null, array( 'absolute', '1px', '1px', 'inset( 50% )', 'hidden', '-1px' ) );
ck( 'and never display none, never visibility hidden, never opacity 0', $hidden ? array( isset( $hidden['display'] ), isset( $hidden['visibility'] ), isset( $hidden['opacity'] ) ) : null, array( false, false, false ) );
ck( 'the box holds its clipped control (position relative)', ( rule( $css, '.wpcpm-ui-file' ) ?? array() )['position'] ?? null, 'relative' );
ck( 'the browser\'s own control, with no script, is never wider than its card', ( rule( $css, '.wpcpm-ui-file .wpcpm-ui-file__input' ) ?? array() )['max-width'] ?? null, '100%' );
ck( 'while the control has the focus (a Tab, the browser\'s refusal of a missing file) the ring is drawn on the button', $ring ? array( $ring['outline'] ?? null, $ring['outline-offset'] ?? null ) : null, array( '2px solid var( --wpcpm-f-brand )', '2px' ) );
ck( 'the printed hidden holds against the frame\'s display of a button, with no !important', $keep, array( 'display' => 'none' ) );
ck( 'the sheet still holds one !important outside its comments', substr_count( preg_replace( '#/\*.*?\*/#s', '', $css ), '!important' ), 1 );

echo "\n=== The script, run by node on the box's own markup ===\n";
// The pages the harness runs on are file()'s own markup: one required box with a hint; two boxes; the box with its
// words translated; a page with no file box (a text field and an old-style file control); a box whose control is
// missing.
$GLOBALS['wpcpm_test_l10n'] = array( 'No file chosen' => 'Aucun fichier choisi' );
$french                     = box( $agreement );
$GLOBALS['wpcpm_test_l10n'] = array();
$import                     = box( array( 'id' => 'wpcpm-import-file', 'name' => 'list', 'label' => 'Your list', 'accept' => '.csv,text/csv', 'need' => 'optional' ) );
$pages                      = array(
	'one'        => '<form method="post" enctype="multipart/form-data">' . $html . '<button type="submit" class="wpcpm-btn">Send</button></form>',
	'two'        => $html . $import,
	'translated' => $french,
	'none'       => WPCPM_UI::field( array( 'id' => 'wpcpm-t', 'name' => 't', 'label' => 'Title' ) ) . '<p class="wpcpm-field"><label for="wpcpm-old">Old</label><input type="file" id="wpcpm-old" name="old"></p>',
	'no-input'   => preg_replace( '#<input class="wpcpm-ui-file__input"[^>]*>#', '', $import ) . $html,
);
ck( 'the fixture pages: one box, two boxes, one translated, none, and a box with no control beside a whole one', array_map( function ( $page ) { return array( substr_count( $page, 'wpcpm-ui-file"' ), substr_count( $page, 'class="wpcpm-ui-file__input"' ) ); }, $pages ), array( 'one' => array( 1, 1 ), 'two' => array( 2, 2 ), 'translated' => array( 1, 1 ), 'none' => array( 0, 0 ), 'no-input' => array( 2, 1 ) ) );
$fixture_dir = sys_get_temp_dir() . '/wpcpm-parts-file-' . getmypid();
if ( ! is_dir( $fixture_dir ) ) {
	mkdir( $fixture_dir, 0700 );
}
$fixture = $fixture_dir . '/file.json';
file_put_contents( $fixture, json_encode( $pages ) );
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the script was not run: node is not on the path\n";
} else {
	$out    = array();
	$status = 0;

	exec(
		escapeshellarg( $node ) . ' ' . escapeshellarg( __DIR__ . '/js/parts-file.js' ) . ' ' . escapeshellarg( $script_file ) . ' ' . escapeshellarg( $fixture ) . ' 2>&1',
		$out,
		$status
	);

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

echo "\n=== The layout harness's cases ===\n";
// One real box, required, with a hint and a problem line, as the script leaves it once a file with a 60-character name
// and no space is chosen: the button and the name shown, the control clipped. And the same box as printed with no
// script, the browser's own control showing.
$long  = 'Exampleagreementsignedbytheinstitutionrepresentative2026.pdf';
$drawn = box( $agreement + array( 'problem' => 'Choose the signed agreement, a PDF.' ) );
$after = str_replace(
	array( 'class="wpcpm-ui-file__input"', ' hidden aria-controls=', ' aria-live="polite" hidden>No file chosen<' ),
	array( 'class="wpcpm-ui-file__input wpcpm-ui-file__input--hidden"', ' tabindex="-1" aria-controls=', ' aria-live="polite">' . $long . '<' ),
	$drawn
);
ck( 'the long name is 60 characters with no space', array( strlen( $long ), false !== strpos( $long, ' ' ) ), array( 60, false ) );
ck( 'the kept box is the drawn one with the script\'s three changes made', array( substr_count( $after, $long ), substr_count( $after, ' hidden' ), substr_count( $after, 'wpcpm-ui-file__input--hidden' ) ), array( 1, 0, 1 ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'File box: required, hint, problem, a 60-character name, with the script', '<div class="wpcpm-ui-fold__body">' . $after . '</div>' );
	wpcpm_layout_keep( 'File box: required, hint, problem, no script', '<div class="wpcpm-ui-fold__body">' . $drawn . '</div>' );
}

echo "\n" . ( $fail ? "$fail of $checks checks failed\n" : "ALL PASS ($checks checks)\n" );
exit( $fail ? 1 : 0 );
