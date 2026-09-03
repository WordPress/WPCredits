<?php
/**
 * Is the agreement the site generates the agreement the program approved?
 *
 * The wording lives in a Google Doc the program owns; the plugin carries a copy as a block
 * list, and a fixture pins that copy's sha256, version and load-bearing sentences. This
 * suite is what makes "any edit needs a version bump and a fixture refresh" a rule rather
 * than a hope: change a word without bumping, and the checksum here disagrees.
 *
 * It also pins the placeholder discipline. A template edit that adds `[Signatory Title]`
 * must be a refusal on the generate form, never a bracket on a document a rector signs;
 * and a name typed with a bracket must be refused the same way.
 *
 * And it pins `drift()`, the button that asks whether the Doc still says what the plugin's
 * copy says. The assertions that matter there are the ones about what it must NOT do: it
 * must not report a word processor's whitespace as a change to the agreement, it must not
 * cache a verdict about a Doc it could not read, and it must not stop `merge()` producing a
 * document however badly the Doc has been vandalised. The Doc is editable by anyone holding
 * its link, so the check is a report a manager reads and never a gate.
 *
 * **The Doc in these assertions is `bin/fixtures/agreement-doc-en.txt`, which is hand written.**
 * It used to be `plain_text()`'s own output dressed up in a word processor's whitespace, and
 * that is a fixture made of the code under test: it agreed with itself while the real check
 * reported the same four differences on every press, and this file could not see it. The
 * fixture is now the Doc's text as a human transcribed it, shape and all - the instruction at
 * the top, the hyperlink with no address in it, the Doc's bullet glyph, the ruled signature
 * lines - so "the unchanged Doc matches" is an assertion about the agreement rather than about
 * a round trip.
 *
 * Run from the plugin root:  php bin/test-agreement-template.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return htmlspecialchars( $s, ENT_QUOTES ); }

/* ---- what `drift()` reaches for --------------------------------------- */

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['settings'] = array( 'agreement_doc_url' => '' );
$GLOBALS['fetched']  = array();
$GLOBALS['diffed']   = array();
$GLOBALS['doc']      = array( 'response' => array( 'code' => 200 ), 'body' => '' );

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['opts'][ $k ], $GLOBALS['autoload'][ $k ] );
	return true;
}
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['fetched'][] = $url;
	return $GLOBALS['doc'];
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) && isset( $r['body'] ) ? $r['body'] : ''; }

/**
 * Core's `wp_text_diff()`, in the two respects this class relies on.
 *
 * It returns the empty string when the two texts are the same, and an HTML table naming the
 * lines that are not otherwise. The real one renders far more; nothing here reads the markup,
 * only whether a line was named.
 *
 * @param string $left  The left-hand text.
 * @param string $right The right-hand text.
 * @param array  $args  Titles. Journalled so the argument order can be asserted.
 * @return string
 */
function wp_text_diff( $left, $right, $args = null ) {
	$GLOBALS['diffed'][] = array( $left, $right, $args );

	$a = explode( "\n", (string) $left );
	$b = explode( "\n", (string) $right );

	if ( $a === $b ) {
		return '';
	}

	$rows = '';

	foreach ( array_diff( $a, $b ) as $line ) {
		$rows .= '<tr><td class="diff-deletedline">' . $line . '</td></tr>';
	}

	foreach ( array_diff( $b, $a ) as $line ) {
		$rows .= '<tr><td class="diff-addedline">' . $line . '</td></tr>';
	}

	return '<table class="diff">' . $rows . '</table>';
}

/** The settings, from one array the assertions move. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * The error code of a WP_Error, or a marker when the value is not one.
 *
 * @param mixed $value Whatever a method returned.
 * @return string
 */
function code_of( $value ) {
	return is_wp_error( $value ) ? $value->get_error_code() : 'not an error';
}

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/agreement-template-en.json' ), true );

/* ---- the fixture pins the copy ------------------------------------------ */

echo "=== The fixture pins the plugin's copy ===\n";

$template = WPCPM_Agreement_Template::load();

ck( 'the English template loads', is_wp_error( $template ), false );

if ( is_wp_error( $template ) ) {
	echo "       " . $template->get_error_message() . "\n";
	echo "\n$fail FAILURE(S)\n";
	exit( 1 );
}

$text = WPCPM_Agreement_Template::plain_text( $template );

ck( 'the checksum of the unmerged plain text is the one the fixture pins',
    WPCPM_Agreement_Template::checksum( $template ), $fixture['sha256'] );
ck( 'the version is the one the fixture pins', WPCPM_Agreement_Template::version( $template ), $fixture['version'] );
ck( 'the read date is the one the fixture pins', $template['read'], $fixture['read'] );
ck( 'the language is the one the fixture pins', $template['language'], $fixture['language'] );
ck( 'the block count is the one the fixture pins', count( $template['blocks'] ), $fixture['blocks'] );
// The provenance is a description, not an address. The Doc is editable by anyone holding its
// link, and this plugin's source is public, so the link lives in a site setting instead.
ck( 'the source names where the wording came from', false !== stripos( $template['source'], 'Collaboration Agreement template' ), true );
ck( 'and carries no address at all', preg_match( '#https?://#', $template['source'] ), 0 );

ck( 'the placeholder appears exactly twice', substr_count( $text, WPCPM_Agreement_Template::PLACEHOLDER ), 2 );
ck( 'which is what the fixture and the class both expect',
    array( $fixture['placeholder_count'], WPCPM_Agreement_Template::OCCURRENCES ), array( 2, 2 ) );

foreach ( $fixture['load_bearing'] as $sentence ) {
	ck( 'load-bearing: ' . $sentence, false !== strpos( $text, $sentence ), true );
}

/* ---- the deliberate departures from the Doc, and nothing else ----------- */

echo "\n=== What was changed on purpose, and what was not ===\n";

ck( '"PLEASE MAKE A COPY" is not part of the agreement', false !== stripos( $text, 'please make a copy' ), false );
ck( 'the Code of Conduct address is printed in parentheses after the words',
    false !== strpos( $text, 'Abide by the WordPress community’s Code of Conduct (https://make.wordpress.org/handbook/community-code-of-conduct/).' ),
    true );
ck( 'the curly quotes survived: “the Institution”', false !== strpos( $text, '(hereafter referred to as “the Institution”)' ), true );
ck( 'and “the Program”', false !== strpos( $text, '(hereafter referred to as “the Program”)' ), true );
ck( 'no straight-quoted version crept in', false !== strpos( $text, '"the Institution"' ), false );

/* ---- plain_text() is deterministic and tidy ------------------------------ */

echo "\n=== plain_text() ===\n";

$lines = explode( "\n", $text );

ck( 'starts with the title on its own line', $lines[0], 'Collaboration Agreement' );
ck( 'no carriage returns', false !== strpos( $text, "\r" ), false );
ck( 'no trailing whitespace on any line', $lines, array_map( 'rtrim', $lines ) );
ck( 'no trailing whitespace at the end', $text, rtrim( $text ) );
ck( 'bullets print as "- item"', false !== strpos( $text, "\n- The Program is conducted fully online and remotely.\n" ), true );
ck( 'the signature parties print with their three blanks',
    substr( $text, -strlen( "For WordPress Foundation\nName: ____\nTitle: ____\nDate: ____\n\nFor The Institution\nName: ____\nTitle: ____\nDate: ____" ) ),
    "For WordPress Foundation\nName: ____\nTitle: ____\nDate: ____\n\nFor The Institution\nName: ____\nTitle: ____\nDate: ____" );
ck( 'the same template gives the same text twice', WPCPM_Agreement_Template::plain_text( $template ), $text );

/* ---- merge() ------------------------------------------------------------- */

echo "\n=== merge() ===\n";

$merged = WPCPM_Agreement_Template::merge( $template, 'Uniwersytet Łódzki' );

ck( 'a real name merges', is_wp_error( $merged ), false );

$merged_text = is_wp_error( $merged ) ? '' : WPCPM_Agreement_Template::plain_text( $merged );

ck( 'no bracket survives the merge', false !== strpos( $merged_text, '[' ), false );
ck( 'the name appears twice', substr_count( $merged_text, 'Uniwersytet Łódzki' ), 2 );
ck( 'the title line names the institution',
    false !== strpos( $merged_text, "Between the WordPress Foundation and Uniwersytet Łódzki\n" ), true );
ck( 'the merge does not touch the metadata',
    array( $merged['language'], $merged['version'], $merged['read'], $merged['source'] ),
    array( $template['language'], $template['version'], $template['read'], $template['source'] ) );
ck( 'the unmerged template is left alone', WPCPM_Agreement_Template::checksum( $template ), $fixture['sha256'] );
ck( 'surrounding whitespace on the name is trimmed',
    substr_count( WPCPM_Agreement_Template::plain_text( WPCPM_Agreement_Template::merge( $template, "  Uniwersytet Łódzki \n" ) ), 'and Uniwersytet Łódzki (hereafter' ),
    1 );

ck( 'an empty name is refused', code_of( WPCPM_Agreement_Template::merge( $template, '' ) ), 'wpcpm_template_name' );
ck( 'a whitespace-only name is refused', code_of( WPCPM_Agreement_Template::merge( $template, "  \t " ) ), 'wpcpm_template_name' );
ck( 'a name with a bracket is refused as a placeholder',
    code_of( WPCPM_Agreement_Template::merge( $template, 'Universität [X]' ) ), 'wpcpm_template_placeholder' );

// The scenario the rule exists for: a wording change adds a second token. The generator must
// refuse rather than print "[Signatory Title]" on a document a rector signs.
$edited             = $template;
$edited['blocks'][] = array( 'type' => 'p', 'text' => 'Signed for the Institution by [Signatory Title].' );

ck( 'a template with a token the merge does not fill is refused',
    code_of( WPCPM_Agreement_Template::merge( $edited, 'Uniwersytet Łódzki' ) ), 'wpcpm_template_placeholder' );

$edited             = $template;
$edited['blocks'][] = array(
	'type'    => 'signatures',
	'parties' => array( array( 'party' => 'For [Institution Name]', 'lines' => array( 'Name' ) ) ),
);

ck( 'the placeholder is replaced inside signature parties too',
    substr_count( WPCPM_Agreement_Template::plain_text( WPCPM_Agreement_Template::merge( $edited, 'Uniwersytet Łódzki' ) ), 'Uniwersytet Łódzki' ),
    3 );

/* ---- load() and languages() --------------------------------------------- */

echo "\n=== load() and languages() ===\n";

ck( 'the default language is English', WPCPM_Agreement_Template::load(), WPCPM_Agreement_Template::load( 'en' ) );
ck( 'the code is case-insensitive', WPCPM_Agreement_Template::load( 'EN' ), $template );
ck( 'an unknown language is refused', code_of( WPCPM_Agreement_Template::load( 'xx' ) ), 'wpcpm_template_language' );
ck( 'a code that is not a code is refused before it becomes a path',
    code_of( WPCPM_Agreement_Template::load( '../class-wpcpm-flash' ) ), 'wpcpm_template_language' );
ck( 'an empty code is refused', code_of( WPCPM_Agreement_Template::load( '' ) ), 'wpcpm_template_language' );
ck( 'languages() lists the files that exist', WPCPM_Agreement_Template::languages(), array( 'en' ) );

ck( 'merge() passes a load() error through', code_of( WPCPM_Agreement_Template::merge( WPCPM_Agreement_Template::load( 'xx' ), 'Name' ) ), 'wpcpm_template_language' );
ck( 'and refuses what is neither an error nor a template', code_of( WPCPM_Agreement_Template::merge( 'text', 'Name' ) ), 'wpcpm_template_shape' );

/* ---- the file-level guard rails, driven through a scratch language ------- */

echo "\n=== a malformed template file is refused before anything else happens ===\n";

$zz = WPCPM_PLUGIN_DIR . 'includes/templates/collaboration-agreement-zz.php';
register_shutdown_function( function () use ( $zz ) { if ( file_exists( $zz ) ) { unlink( $zz ); } } );
$write_zz = function ( $value ) use ( $zz ) { file_put_contents( $zz, "<?php\nreturn " . var_export( $value, true ) . ";\n" ); };
$as_zz = $template;
$as_zz['language'] = 'zz';

$write_zz( $as_zz );
ck( 'a second language file is listed', WPCPM_Agreement_Template::languages(), array( 'en', 'zz' ) );
ck( 'and loads when well formed', is_wp_error( WPCPM_Agreement_Template::load( 'zz' ) ), false );

$write_zz( 'not a template' );
ck( 'a file that does not return an array is refused', code_of( WPCPM_Agreement_Template::load( 'zz' ) ), 'wpcpm_template_shape' );

$write_zz( $template );
ck( 'a language that does not match the file name is refused', code_of( WPCPM_Agreement_Template::load( 'zz' ) ), 'wpcpm_template_shape' );

$bad = $as_zz;
$bad['version'] = 'v1';
$write_zz( $bad );
ck( 'a version that is not a date is refused', code_of( WPCPM_Agreement_Template::load( 'zz' ) ), 'wpcpm_template_shape' );

$bad = $as_zz;
$bad['blocks'][] = array( 'type' => 'table', 'text' => 'x' );
$write_zz( $bad );
ck( 'an unknown block type is refused', code_of( WPCPM_Agreement_Template::load( 'zz' ) ), 'wpcpm_template_shape' );

$bad = $as_zz;
foreach ( $bad['blocks'] as $i => $block ) {
	if ( 'ul' === $block['type'] ) {
		$bad['blocks'][ $i ]['items'][] = '';
		break;
	}
}
$write_zz( $bad );
ck( 'an empty bullet is refused', code_of( WPCPM_Agreement_Template::load( 'zz' ) ), 'wpcpm_template_shape' );

$bad = $as_zz;
$bad['blocks'][] = array( 'type' => 'p', 'text' => 'Signed for [Institution Name].' );
$write_zz( $bad );
ck( 'a third placeholder is refused at load, not at merge', code_of( WPCPM_Agreement_Template::load( 'zz' ) ), 'wpcpm_template_placeholder' );

$bad = $as_zz;
$bad['blocks'][] = array( 'type' => 'p', 'text' => 'Title: [Signatory Title]' );
$write_zz( $bad );
ck( 'a token the generator does not fill is refused at load', code_of( WPCPM_Agreement_Template::load( 'zz' ) ), 'wpcpm_template_placeholder' );

unlink( $zz );
ck( 'the scratch language is gone again', WPCPM_Agreement_Template::languages(), array( 'en' ) );

/* ---- drift(): the plugin's copy against the Doc -------------------------- */

/**
 * What a Google Doc's `export?format=txt` actually hands back.
 *
 * A byte order mark, CRLF line endings, a blank line under every paragraph, trailing spaces
 * nobody can see, and a no-break space wherever somebody's word processor decided one
 * belonged. None of that is the agreement, and the whole point of normalising is that none
 * of it is reported as a change to it.
 *
 * @param string $text The text the Doc holds.
 * @return string The bytes the export would carry it in.
 */
function as_doc_export( $text ) {
	$lines = array();

	foreach ( explode( "\n", $text ) as $line ) {
		$lines[] = $line . '   ';
		$lines[] = '';
	}

	return "\xEF\xBB\xBF" . str_replace( ' the ', " the\xC2\xA0", implode( "\r\n", $lines ) );
}

/**
 * The program's Doc, as a human transcribed its plain-text export.
 *
 * Read from a file rather than built here, and built by hand rather than by the class: the
 * whole question this side of the suite asks is whether the plugin's copy still says what the
 * Doc says, and a Doc assembled out of `plain_text()` cannot answer it. The note at the top of
 * the fixture explains what a reader is looking at and how to check it against the real Doc;
 * it is stripped here, and the export begins at the first line that is not a comment.
 *
 * @return string
 */
function doc_text() {
	$lines = explode( "\n", (string) file_get_contents( __DIR__ . '/fixtures/agreement-doc-en.txt' ) );

	while ( $lines && 0 === strpos( $lines[0], '#' ) ) {
		array_shift( $lines );
	}

	return rtrim( implode( "\n", $lines ) );
}

/**
 * Hand the next fetch a body, or a failure.
 *
 * @param mixed $body A body string, or a WP_Error the HTTP layer returns instead.
 * @param int   $code The status code to answer with.
 */
function doc_answers( $body, $code = 200 ) {
	$GLOBALS['fetched'] = array();
	$GLOBALS['diffed']  = array();
	$GLOBALS['doc']     = $body instanceof WP_Error ? $body : array( 'response' => array( 'code' => $code ), 'body' => $body );
}

echo "\n=== The Doc these assertions use is the Doc, not the plugin's copy of it ===\n";

// The check this whole section is about compares two documents that are written differently
// on purpose, so the first thing to establish is that the fixture really is the other one.
// Every assertion below rests on it: if this file ever went back to comparing `plain_text()`
// with a dressed-up copy of itself, these five fail and say why.
$doc_text = doc_text();

ck( 'the Doc\'s text is not the plugin\'s plain text', $doc_text === $text, false );
ck( 'it opens with the instruction the plugin\'s copy leaves out',
    0 === strpos( $doc_text, 'PLEASE MAKE A COPY' ), true );
ck( 'its Code of Conduct bullet carries the words and no address, the way a text export does',
    array( false !== strpos( $doc_text, 'Code of Conduct.' ), false !== strpos( $doc_text, 'community-code-of-conduct' ) ),
    array( true, false ) );
ck( 'its lists are drawn with a bullet glyph and not with the hyphen plain_text() writes',
    array( false !== strpos( $doc_text, "\xE2\x97\x8F Provides structure" ), false !== strpos( $doc_text, '- Provides structure' ) ),
    array( true, false ) );
ck( 'and its signature lines are ruled rather than four underscores',
    array( false !== strpos( $doc_text, 'Name: ' . str_repeat( '_', 30 ) ), false !== strpos( $doc_text, 'Name: ____' . "\n" ) ),
    array( true, false ) );
ck( 'the two carry the same load-bearing sentences all the same', array_values( array_filter(
	$fixture['load_bearing'],
	function ( $sentence ) use ( $doc_text ) {
		return false === strpos( $doc_text, $sentence );
	}
) ), array() );

echo "\n=== drift(): the address is a setting, and an unset one says so ===\n";

$GLOBALS['settings']['agreement_doc_url'] = '';
doc_answers( as_doc_export( $doc_text ) );

$result = WPCPM_Agreement_Template::drift();

ck( 'with no address recorded there is no verdict', $result['ok'], false );
ck( 'and it says the address is not recorded rather than guessing one',
    false !== strpos( $result['error'], 'is not recorded' ), true );
ck( 'nothing was requested', $GLOBALS['fetched'], array() );
ck( 'and nothing was cached', array_key_exists( 'wpcpm_agreement_drift', $GLOBALS['opts'] ), false );
ck( 'the template version is still reported, so the card can name what was not checked',
    $result['version'], $fixture['version'] );

// The setting is sanitised on save, so these can only arrive by another route; the request
// is outbound, which is reason enough to check again at the point it is made.
$refused = array(
	'https://example.test/document/d/1AbCdEfGhIjKlMn/edit' => 'a host that is not Google',
	'http://docs.google.com/document/d/1AbCdEfGhIjKlMn/edit' => 'a link that is not https',
	'https://docs.google.com/'                            => 'a link naming no document',
	'https://docs.google.com/document/d/short/edit'        => 'an id too short to be one',
);

foreach ( $refused as $link => $why ) {
	$GLOBALS['settings']['agreement_doc_url'] = $link;
	doc_answers( as_doc_export( $doc_text ) );

	$result = WPCPM_Agreement_Template::drift();

	ck( $why . ' is refused before a request is made', $GLOBALS['fetched'], array() );
	ck( $why . ': and the reason says what is wrong with it',
	    false !== strpos( $result['error'], 'does not name a Google Doc' ), true );
}

echo "\n=== drift(): only the document ID travels ===\n";

$expected = 'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/export?format=txt';
$shapes   = array(
	'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit',
	'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit?usp=sharing#heading=h.abc',
	'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/export?format=html',
	'https://drive.google.com/open?id=1AbCdEfGhIjKlMnOp',
);

foreach ( $shapes as $shape ) {
	$GLOBALS['settings']['agreement_doc_url'] = $shape;
	doc_answers( as_doc_export( $doc_text ) );

	WPCPM_Agreement_Template::drift();

	ck( 'the export address is built from the ID alone: ' . $shape, $GLOBALS['fetched'], array( $expected ) );
}

echo "\n=== drift(): the unchanged Doc matches ===\n";

$GLOBALS['settings']['agreement_doc_url'] = 'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit';
doc_answers( as_doc_export( $doc_text ) );

$result = WPCPM_Agreement_Template::drift();

ck( 'a Doc that says what the plugin says matches', $result['ok'], true );
ck( 'with nothing to show', $result['differences'], '' );
ck( 'and no failure to report', $result['error'], '' );
ck( 'the whitespace a word processor exports is not a change to the agreement',
    false !== strpos( $GLOBALS['doc']['body'], "\r\n" ) && false !== strpos( $GLOBALS['doc']['body'], "\xC2\xA0" ), true );
ck( 'the renderer was not asked: the verdict is the comparison', $GLOBALS['diffed'], array() );
ck( 'the answer is stamped with the version it is about', $result['version'], $fixture['version'] );
ck( 'and with the moment it was taken', $result['checked'] > 0, true );

echo "\n=== drift(): the answer is remembered, not autoloaded ===\n";

ck( 'the answer is in the option the design names',
    array_key_exists( 'wpcpm_agreement_drift', $GLOBALS['opts'] ), true );
ck( 'and it is not autoloaded: a diff nobody is reading is not needed on every request',
    $GLOBALS['autoload']['wpcpm_agreement_drift'], false );
ck( 'cached() hands back the answer that was taken', WPCPM_Agreement_Template::cached(), $result );

echo "\n=== drift(): the placeholder is not a difference ===\n";

doc_answers( as_doc_export( str_replace( WPCPM_Agreement_Template::PLACEHOLDER, '', $doc_text ) ) );

$result = WPCPM_Agreement_Template::drift();

ck( 'a Doc whose placeholder is written differently still matches', $result['ok'], true );
ck( 'and nothing about it is reported', $result['differences'], '' );

echo "\n=== drift(): a word processor's choices are not the agreement ===\n";

// Every one of these is a way the same agreement can come back looking different, and every
// one of them is a rule `normalise()` names and says why it is safe. They are asserted one at
// a time rather than through the fixture alone, because the fixture can only carry one shape:
// the day the export switches its bullet glyph, or somebody drags a signature line longer,
// this says whether the check survives it.
$rendered = array(
	'a different bullet glyph'         => str_replace( "\xE2\x97\x8F ", "\xE2\x80\xA2 ", $doc_text ),
	'a hyphen where the glyph was'     => str_replace( "\xE2\x97\x8F ", '- ', $doc_text ),
	'a glyph with no space after it'   => str_replace( "\xE2\x97\x8F ", "\xE2\x97\x8F", $doc_text ),
	'signature lines dragged longer'   => str_replace( str_repeat( '_', 30 ), str_repeat( '_', 60 ), $doc_text ),
	'a word added to the instruction'  => str_replace( 'PLEASE MAKE A COPY', 'PLEASE MAKE A COPY BEFORE EDITING', $doc_text ),
	'the link written out as its address' => str_replace(
		'Code of Conduct.',
		'Code of Conduct (https://make.wordpress.org/handbook/community-code-of-conduct/).',
		$doc_text
	),
);

foreach ( $rendered as $why => $body ) {
	ck( $why . ': the Doc really was rewritten that way', $body === $doc_text, false );

	doc_answers( as_doc_export( $body ) );

	$result = WPCPM_Agreement_Template::drift();

	ck( $why . ' is not a change to the agreement',
	    array( $result['ok'], $result['differences'], $result['error'] ), array( true, '', '' ) );
}

echo "\n=== drift(): a changed sentence is reported, and named ===\n";

$changed = str_replace(
	'This document is not legally binding.',
	'This document is legally binding.',
	$doc_text
);

ck( 'the Doc really did change', $changed === $doc_text, false );

doc_answers( as_doc_export( $changed ) );

$result = WPCPM_Agreement_Template::drift();

ck( 'a Doc that says something else does not match', $result['ok'], false );
ck( 'the sentence the Doc now carries is named',
    false !== strpos( $result['differences'], 'This document is legally binding.' ), true );
ck( 'and so is the one the plugin still carries',
    false !== strpos( $result['differences'], 'This document is not legally binding.' ), true );
ck( 'only the line that changed is named, not the whole agreement',
    substr_count( $result['differences'], '<tr>' ), 2 );
ck( 'the plugin copy is the left-hand side and the Doc the right',
    array( $GLOBALS['diffed'][0][2]['title_left'], $GLOBALS['diffed'][0][2]['title_right'] ),
    array( 'The plugin\'s copy', 'The Doc' ) );
ck( 'a difference is not a failure', $result['error'], '' );

// The rule the whole design rests on. A stranger with the Doc's link can make this report say
// anything at all; what it must never do is stop an institution being onboarded.
$still = WPCPM_Agreement_Template::merge( WPCPM_Agreement_Template::load(), 'Uniwersytet Łódzki' );

ck( 'and a reported difference is not a reason to refuse generation', is_wp_error( $still ), false );
ck( 'the document generated afterwards is the same one as before',
    WPCPM_Agreement_Template::checksum( WPCPM_Agreement_Template::load() ), $fixture['sha256'] );

echo "\n=== drift(): a report nobody can hold is a sentence, not half a megabyte ===\n";

// The Doc is world-editable, so the size of a difference is not bounded by the size of the
// agreement. The report is stored and redrawn on every load of the screen that shows it, so
// an unbounded one is a page that carries somebody's paste for ever.
$vandalised = $doc_text . "\n" . implode( "\n", array_fill( 0, 4000, 'A line somebody pasted into the Doc.' ) );

doc_answers( as_doc_export( $vandalised ) );

$result = WPCPM_Agreement_Template::drift();
$stored = $GLOBALS['opts']['wpcpm_agreement_drift']['en'];

ck( 'a Doc with four thousand pasted lines does not match', $result['ok'], false );
ck( 'the report is bounded', strlen( $result['differences'] ) <= WPCPM_Agreement_Template::DRIFT_MAX_DIFF, true );
ck( 'and is a sentence rather than a diff cut off in the middle of its markup',
    array( 0 === strpos( $result['differences'], '<p>' ), false !== strpos( $result['differences'], 'Open the Doc and read it.' ) ),
    array( true, true ) );
ck( 'what was stored is what the presser saw, so the card cannot show more than they did',
    $stored['differences'], $result['differences'] );
ck( 'and the option is small enough to redraw on every screen load',
    strlen( serialize( $GLOBALS['opts']['wpcpm_agreement_drift'] ) ) < 4096, true );
ck( 'a difference of any size is still not a failure', $result['error'], '' );

echo "\n=== drift(): a read that stopped at the cap is a failed read, not a difference ===\n";

// The failure that looks like an answer: the bytes are the Doc's for as far as they go, so
// nothing in the body says the read stopped. Compared, it would report every line past the cut
// as deleted from the Doc - a failure read as a difference, which is exactly what the sign-in
// page and the HTTP guards exist to keep out.
$cut = substr(
	as_doc_export( $doc_text ) . str_repeat( "A line somebody pasted into the Doc.\r\n", 20000 ),
	0,
	WPCPM_Agreement_Template::DRIFT_MAX_BYTES
);

ck( 'the cut body is exactly what the request would have read', strlen( $cut ), WPCPM_Agreement_Template::DRIFT_MAX_BYTES );
ck( 'and it opens with the agreement, which is what makes it look like an answer',
    false !== strpos( substr( $cut, 0, 200 ), 'Collaboration Agreement' ), true );

doc_answers( as_doc_export( $doc_text ) );
WPCPM_Agreement_Template::drift();

ck( 'there is an answer to lose', is_array( WPCPM_Agreement_Template::cached() ), true );

doc_answers( $cut );

$result = WPCPM_Agreement_Template::drift();

ck( 'a truncated read is refused, in words that name the length',
    false !== strpos( $result['error'], sprintf( 'longer than the %d KB', (int) round( WPCPM_Agreement_Template::DRIFT_MAX_BYTES / 1024 ) ) ), true );
ck( 'nothing was compared and no verdict was reached', array( $result['ok'], $result['differences'] ), array( false, '' ) );
ck( 'the renderer was never asked', $GLOBALS['diffed'], array() );
ck( 'nothing was cached, so a failure cannot be read as a difference',
    array_key_exists( 'wpcpm_agreement_drift', $GLOBALS['opts'] ), false );
ck( 'and the answer taken before it is gone rather than shown as current',
    WPCPM_Agreement_Template::cached(), null );

echo "\n=== drift(): a Doc that cannot be read is not a verdict ===\n";

// Put a real answer back first, so what these assert is that the stale one is thrown away
// rather than that there was never anything there.
$unreadable = array(
	'the request failed'          => array( new WP_Error( 'http_request_failed', 'Connection timed out' ), 200, 'Connection timed out' ),
	'Google answered 404'         => array( '', 404, 'HTTP 404' ),
	'Google answered a sign-in page' => array( '<!DOCTYPE html><html><body>Sign in</body></html>', 200, 'a web page instead' ),
	'the export was empty'        => array( "\xEF\xBB\xBF   \n", 200, 'exported nothing' ),
	'the export was cut off at the cap' => array( $cut, 200, 'only its beginning arrived' ),
);

foreach ( $unreadable as $label => $case ) {
	doc_answers( as_doc_export( $doc_text ) );
	WPCPM_Agreement_Template::drift();

	ck( $label . ': there is an answer to lose', is_array( WPCPM_Agreement_Template::cached() ), true );

	doc_answers( $case[0], $case[1] );

	$result = WPCPM_Agreement_Template::drift();

	ck( $label . ': it is reported in words', false !== strpos( $result['error'], $case[2] ), true );
	ck( $label . ': and carries no verdict', array( $result['ok'], $result['differences'] ), array( false, '' ) );
	ck( $label . ': nothing was cached, so a failure cannot be read as a difference',
	    array_key_exists( 'wpcpm_agreement_drift', $GLOBALS['opts'] ), false );
	ck( $label . ': and yesterday\'s answer is gone rather than shown as current',
	    WPCPM_Agreement_Template::cached(), null );
}

echo "\n=== drift(): the answer is about one version of one template ===\n";

doc_answers( as_doc_export( $doc_text ) );
WPCPM_Agreement_Template::drift();

$as_zz             = $template;
$as_zz['language'] = 'zz';
$write_zz( $as_zz );

// The scratch language is a copy of the English template, so the Doc it is checked against is
// the same hand-written one. What this block is about is the bookkeeping - one row per
// language, keyed to a version - and it uses the real Doc for that too rather than keeping a
// second, easier standard for the language nobody ships.
doc_answers( as_doc_export( $doc_text ) );

$result = WPCPM_Agreement_Template::drift( 'zz' );

ck( 'a second language is checked on its own', $result['ok'], true );
ck( 'and remembered under its own code', WPCPM_Agreement_Template::cached( 'zz' ), $result );
ck( 'without disturbing the answer about the English copy',
    is_array( WPCPM_Agreement_Template::cached( 'en' ) ), true );

$bumped            = $as_zz;
$bumped['version'] = '2026-12-01';
$write_zz( $bumped );

ck( 'a version bump hides an answer that was about the wording before it',
    WPCPM_Agreement_Template::cached( 'zz' ), null );
ck( 'and leaves the other language alone', is_array( WPCPM_Agreement_Template::cached( 'en' ) ), true );

$write_zz( 'not a template' );

ck( 'an unreadable template has no answer to show', WPCPM_Agreement_Template::cached( 'zz' ), null );

// A Doc is waiting to be handed over, so what this asserts is that nothing asked for it.
doc_answers( as_doc_export( $doc_text ) );

$result = WPCPM_Agreement_Template::drift( 'zz' );

ck( 'and checking it reports why rather than asking Google', $GLOBALS['fetched'], array() );
ck( 'in the template file\'s own words',
    false !== stripos( $result['error'], 'does not have the expected shape' ), true );

unlink( $zz );
ck( 'the scratch language is gone once more', WPCPM_Agreement_Template::languages(), array( 'en' ) );
ck( 'and its cached answer with it', WPCPM_Agreement_Template::cached( 'zz' ), null );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
