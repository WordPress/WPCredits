<?php
/**
 * The error summary and the field marking every refused form prints (1.123.58).
 *
 * `WPCPM_Form_Errors` is what a refused form says: a summary at the top, focused when the page loads,
 * with one link per problem to its field, and the field marked `aria-invalid` with its sentence tied
 * by `aria-describedby`. These checks read the markup it returns, escaped, in the form's order, and
 * that a form nothing was refused on prints nothing.
 *
 * Run from the plugin root:  php bin/test-form-errors.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = null ) {
	return $text;
}
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

require_once dirname( __DIR__ ) . '/includes/class-wpcpm-form-errors.php';

$fail = 0;
function ck( $l, $a, $e ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $l . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $e, true ) . '  got: ' . var_export( $a, true ) . "\n"; }
}

$fields = array(
	'name'    => array( 'id' => 'wpcpm-sponsor-name', 'label' => 'Name' ),
	'website' => array( 'id' => 'wpcpm-sponsor-website', 'label' => 'Website' ),
	'email'   => array( 'id' => 'wpcpm-sponsor-email', 'label' => 'Contact email' ),
);

ck( 'nothing refused prints no summary', WPCPM_Form_Errors::summary( array(), $fields ), '' );
ck( 'and a blank sentence is no problem', WPCPM_Form_Errors::summary( array( 'name' => '  ' ), $fields ), '' );

ck(
	'one problem: the heading spelled out, one link to the field with its label, the sentence after it',
	WPCPM_Form_Errors::summary( array( 'website' => 'Enter a web address that starts with https://.' ), $fields ),
	'<div class="wpcpm-errors" role="alert" tabindex="-1"><h2 class="wpcpm-errors__title">One answer needs a change</h2><ul class="wpcpm-errors__list"><li><a href="#wpcpm-sponsor-website">Website</a>: Enter a web address that starts with https://.</li></ul></div>'
);

$two = WPCPM_Form_Errors::summary(
	array(
		'email'   => 'Enter one email address, such as name@example.org.',
		'website' => 'Enter a web address that starts with https://.',
	),
	$fields,
	array( 'level' => 3, 'class' => 'notice notice-error' )
);
ck( 'two problems are listed in the form\'s order, not the order the handler found them', strpos( $two, 'Website' ) < strpos( $two, 'Contact email' ), true );
ck( 'the heading counts them, at the level asked, and the classes follow wpcpm-errors', 0 === strpos( $two, '<div class="wpcpm-errors notice notice-error" role="alert" tabindex="-1"><h3 class="wpcpm-errors__title">Two answers need a change</h3>' ), true );

ck( 'a level outside two to four is held to the nearest', false !== strpos( WPCPM_Form_Errors::summary( array( 'name' => 'Give a name.' ), $fields, array( 'level' => 1 ) ), '<h2 ' ), true );
ck( 'the heading can be the caller\'s words', false !== strpos( WPCPM_Form_Errors::summary( array( 'name' => 'Kept.' ), $fields, array( 'heading' => 'One setting changed since you opened this page' ) ), '>One setting changed since you opened this page</h2>' ), true );

$whole = WPCPM_Form_Errors::summary( array( '' => 'Too many attempts from this connection.', 'name' => 'Give a name.' ), $fields );
ck( 'a problem with no field in the map is listed after the others, without a link', false !== strpos( $whole, '<li><a href="#wpcpm-sponsor-name">Name</a>: Give a name.</li><li>Too many attempts from this connection.</li>' ), true );

$hostile = WPCPM_Form_Errors::summary( array( 'name' => 'Not "<b>this</b>" & that' ), array( 'name' => array( 'id' => 'a"b', 'label' => '<i>Name</i>' ) ) );
ck( 'the sentence, the label and the id are escaped', false !== strpos( $hostile, '<a href="#a&quot;b">&lt;i&gt;Name&lt;/i&gt;</a>: Not &quot;&lt;b&gt;this&lt;/b&gt;&quot; &amp; that' ), true );

$many = array();
for ( $i = 1; $i <= 12; $i++ ) {
	$many[ 'f' . $i ] = 'Fix ' . $i . '.';
}
ck( 'from ten the count is in figures', false !== strpos( WPCPM_Form_Errors::summary( $many, array() ), '>12 answers need a change<' ), true );
ck( 'the count is spelled out from one to nine', array( WPCPM_Form_Errors::count_words( 1 ), WPCPM_Form_Errors::count_words( 9 ), WPCPM_Form_Errors::count_words( 10 ) ), array( 'One', 'Nine', '10' ) );

ck( 'a refused control: aria-invalid, and its problem named before its hint', WPCPM_Form_Errors::attrs( 'wpcpm-sponsor-website', 'Enter a web address.', 'wpcpm-sponsor-website-hint' ), ' aria-invalid="true" aria-describedby="wpcpm-sponsor-website-problem wpcpm-sponsor-website-hint"' );
ck( 'a control nothing was refused on: its hint alone', WPCPM_Form_Errors::attrs( 'wpcpm-sponsor-website', '', 'wpcpm-sponsor-website-hint' ), ' aria-describedby="wpcpm-sponsor-website-hint"' );
ck( 'and with no hint, nothing', WPCPM_Form_Errors::attrs( 'wpcpm-sponsor-website', '' ), '' );
ck( 'the problem paragraph carries the id attrs() names', WPCPM_Form_Errors::problem( 'wpcpm-sponsor-website', 'Enter a web address & try again.' ), '<p class="wpcpm-field__problem" id="wpcpm-sponsor-website-problem">Enter a web address &amp; try again.</p>' );
ck( 'and prints nothing for a field nothing was refused on', WPCPM_Form_Errors::problem( 'wpcpm-sponsor-website', '' ), '' );

ck( 'a file input that comes back says the file has to be chosen again, in one sentence for every form', WPCPM_Form_Errors::file_again(), 'A chosen file cannot be kept when the form comes back. Choose the file again.' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
