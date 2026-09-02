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
ck( 'the source is the Doc', 0 === strpos( $template['source'], 'https://docs.google.com/document/d/' ), true );

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

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
