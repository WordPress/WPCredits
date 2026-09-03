<?php
/**
 * The shared field rules: what a posted value becomes, and why one is refused.
 *
 * `WPCPM_Field_Value` was hoisted out of the Student Report Card form and the feedback forms,
 * which each carried their own `clean()` and disagreed in small ways - one read a checkbox with
 * `! empty()`, the other only on the value the control posts; one knew what a comma decimal was,
 * the other had never seen a number. The two form suites still pin what each form does with its
 * own answers. This one pins the rules themselves, type by type, and every refusal key, because
 * the application form and the student import are built on the same class and a key that
 * quietly changed would misfile a rejection in every one of them.
 *
 * The list of keys is **read out of the class's own docblock** rather than typed here. A copy
 * kept by hand is a copy that goes stale the moment a type is added: it was seven names long
 * while the class documented eight, so `bad_date` was asserted nowhere and the check that was
 * supposed to notice a key appearing or disappearing was comparing a list with itself.
 *
 * Run from the plugin root:  php bin/test-field-value.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_email( $s ) { return trim( preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@\-]/', '', (string) $s ) ); }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }

/**
 * Honours the protocol allow-list, so the URL assertions are not vacuous.
 *
 * A pass-through stub would let "a javascript: URL is refused" pass whatever the class did.
 */
function esc_url_raw( $url, $protocols = null ) {
	$url    = trim( (string) $url );
	$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

	if ( null !== $protocols && ! in_array( $scheme, (array) $protocols, true ) ) {
		return '';
	}

	return $url;
}

/**
 * The team list the `team` type checks against, without the sync behind the real one.
 */
class WPCPM_Contribution_Teams {
	public static function options() {
		return $GLOBALS['teams'];
	}
}

$GLOBALS['teams'] = array(
	'recAAA000000000001' => 'Core',
	'recBBB000000000002' => 'Docs',
);

require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';

$fails = 0;
$total = 0;
$seen  = array();

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * Run the rules and remember every refusal key they produced.
 *
 * @param mixed $raw  Posted value.
 * @param array $spec Field spec.
 * @return array{ok:bool,value:mixed,problem:string}
 */
function fv( $raw, array $spec ) {
	global $seen;

	$result = WPCPM_Field_Value::clean( $raw, $spec );

	if ( '' !== $result['problem'] ) {
		$seen[ $result['problem'] ] = true;
	}

	return $result;
}

/**
 * The accepted shape, for the checks that only care about the value.
 *
 * @param mixed $value What Airtable gets.
 * @return array{ok:bool,value:mixed,problem:string}
 */
function accepted( $value ) {
	return array( 'ok' => true, 'value' => $value, 'problem' => '' );
}

/**
 * The refused shape.
 *
 * @param string $problem Refusal key.
 * @return array{ok:bool,value:mixed,problem:string}
 */
function refused( $problem ) {
	return array( 'ok' => false, 'value' => null, 'problem' => $problem );
}

echo "=== The shape every caller reads ===\n";

$textarea = array( 'type' => 'textarea' );

ck( 'an answer comes back as ok, value and problem, in that order',
    array_keys( fv( 'x', $textarea ) ), array( 'ok', 'value', 'problem' ) );
ck( 'an accepted value has no problem', fv( 'x', $textarea )['problem'], '' );
ck( 'a refused value is null, never the offending input', fv( array( 'x' ), $textarea ), refused( 'not_scalar' ) );

echo "\n=== team: a list of record IDs, dropping what it does not know ===\n";

$team = array( 'type' => 'team' );

ck( 'known IDs are kept in the order ticked',
    fv( array( 'recBBB000000000002', 'recAAA000000000001' ), $team ), accepted( array( 'recBBB000000000002', 'recAAA000000000001' ) ) );
ck( 'a duplicate collapses to one link',
    fv( array( 'recAAA000000000001', 'recAAA000000000001' ), $team ), accepted( array( 'recAAA000000000001' ) ) );
ck( 'an ID the list does not know is dropped, not refused',
    fv( array( 'recAAA000000000001', 'recZZZ000000000009' ), $team ), accepted( array( 'recAAA000000000001' ) ) );
ck( 'a nested array is skipped rather than cast',
    fv( array( array( 'recAAA000000000001' ), 'recBBB000000000002' ), $team ), accepted( array( 'recBBB000000000002' ) ) );
ck( 'whitespace around an ID is not part of it',
    fv( array( ' recAAA000000000001 ' ), $team ), accepted( array( 'recAAA000000000001' ) ) );
ck( 'a single ID posted bare is still a list', fv( 'recBBB000000000002', $team ), accepted( array( 'recBBB000000000002' ) ) );
ck( 'nothing ticked clears every link with an empty array', fv( array(), $team ), accepted( array() ) );
ck( 'and so does an empty string', fv( '', $team ), accepted( array() ) );
ck( 'and so does nothing at all', fv( null, $team ), accepted( array() ) );

echo "\n=== checkbox: ticked only on the value the control posts ===\n";

$checkbox = array( 'type' => 'checkbox' );

ck( 'a tick posts 1 from either form', fv( '1', $checkbox ), accepted( true ) );
ck( 'true is a tick too', fv( 'true', $checkbox ), accepted( true ) );
ck( 'in any case', fv( 'TRUE', $checkbox ), accepted( true ) );
ck( 'and survives whitespace', fv( ' 1 ', $checkbox ), accepted( true ) );
ck( "the report form's hidden zero is an unticked box", fv( '0', $checkbox ), accepted( false ) );
ck( "the feedback form's absent box, passed as empty, is an unticked box", fv( '', $checkbox ), accepted( false ) );
ck( 'a word is not a tick', fv( 'yes', $checkbox ), accepted( false ) );
ck( 'nor is "on"', fv( 'on', $checkbox ), accepted( false ) );
ck( 'an array is refused, not read as a tick', fv( array( '1' ), $checkbox ), refused( 'not_scalar' ) );

echo "\n=== email: cleared with an empty string, refused when it is not one ===\n";

$email = array( 'type' => 'email' );

ck( 'an address is kept as typed', fv( 'Ada@Example.org', $email ), accepted( 'Ada@Example.org' ) );
ck( 'whitespace around it is not part of it', fv( '  ada@example.org  ', $email ), accepted( 'ada@example.org' ) );
ck( 'an emptied box clears the column', fv( '', $email ), accepted( '' ) );
ck( 'words are refused', fv( 'not an address', $email ), refused( 'bad_email' ) );
ck( 'and so is half an address', fv( 'ada@', $email ), refused( 'bad_email' ) );
ck( 'an array is refused before it is looked at', fv( array( 'ada@example.org' ), $email ), refused( 'not_scalar' ) );

echo "\n=== number: comma decimals, two places, a whole number when the step is 1 ===\n";

$number = array( 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 100 );
$whole  = array( 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 100 );
$free   = array( 'type' => 'number' );

ck( 'a comma decimal is read the way it was meant', fv( '12,5', $number ), accepted( 12.5 ) );
ck( 'a long decimal is rounded to two places', fv( '3.14159', $number ), accepted( 3.14 ) );
ck( 'whitespace around a number is not part of it', fv( ' 7 ', $number ), accepted( 7.0 ) );
ck( 'an emptied box clears the column with null', fv( '', $number ), accepted( null ) );
ck( 'a whole-number field rounds up', fv( '4.6', $whole ), accepted( 5 ) );
ck( 'and down', fv( '4.4', $whole ), accepted( 4 ) );
ck( 'and gives an integer, not a float', is_int( fv( '4', $whole )['value'] ), true );
ck( 'a comma decimal rounds in a whole-number field too', fv( '4,5', $whole ), accepted( 5 ) );
ck( 'a word is not a number', fv( 'twelve', $number ), refused( 'not_a_number' ) );
ck( 'nor is a number with words in it', fv( '12 hours', $number ), refused( 'not_a_number' ) );
ck( 'below the minimum is refused', fv( '-1', $number ), refused( 'below_min' ) );
ck( 'above the maximum is refused', fv( '100.01', $number ), refused( 'above_max' ) );
ck( 'the minimum itself is allowed', fv( '0', $number ), accepted( 0.0 ) );
ck( 'and the maximum itself', fv( '100', $number ), accepted( 100.0 ) );
ck( 'rounding happens before the range check, so 100.004 fits', fv( '100.004', $number ), accepted( 100.0 ) );
ck( 'with no range, anything numeric is fine', fv( '-273.15', $free ), accepted( -273.15 ) );
ck( 'an array is refused', fv( array( '1' ), $number ), refused( 'not_scalar' ) );

echo "\n=== url: a scheme added when missing, a dangerous one refused ===\n";

$url = array( 'type' => 'url' );

ck( 'a scheme-less address gets https', fv( 'example.org/path', $url ), accepted( 'https://example.org/path' ) );
ck( 'an address with one keeps it', fv( 'http://example.org', $url ), accepted( 'http://example.org' ) );
ck( 'whatever its case', fv( 'HTTPS://Example.org', $url ), accepted( 'HTTPS://Example.org' ) );
ck( 'leading slashes do not double up', fv( '//example.org', $url ), accepted( 'https://example.org' ) );
ck( 'whitespace around it is not part of it', fv( '  example.org  ', $url ), accepted( 'https://example.org' ) );
ck( 'an emptied box clears the column', fv( '', $url ), accepted( '' ) );
ck( 'a javascript: URL does not survive', fv( 'javascript:alert(1)', $url ), accepted( '' ) );
ck( 'clean_url() is the same rule on its own', WPCPM_Field_Value::clean_url( 'example.org' ), 'https://example.org' );
ck( 'clean_url() refuses javascript: on its own too', WPCPM_Field_Value::clean_url( 'javascript:alert(1)' ), '' );
ck( 'clean_url() passes an empty string through', WPCPM_Field_Value::clean_url( '' ), '' );
ck( 'an array is refused', fv( array( 'example.org' ), $url ), refused( 'not_scalar' ) );

echo "\n=== text: one line, capped by maxlength, then by the caller, then by the class ===\n";

$text = array( 'type' => 'text' );

ck( 'markup is stripped', fv( 'Hello <b>world</b>', $text ), accepted( 'Hello world' ) );
ck( 'whitespace around it is not part of it', fv( '  Ada  ', $text ), accepted( 'Ada' ) );
ck( 'maxlength clips', fv( 'abcdefgh', array( 'type' => 'text', 'maxlength' => 5 ) ), accepted( 'abcde' ) );
ck( 'in characters, not bytes', fv( 'żółć', array( 'type' => 'text', 'maxlength' => 2 ) ), accepted( 'żó' ) );
ck( 'with no maxlength the class cap holds', strlen( fv( str_repeat( 'x', 6000 ), $text )['value'] ), 5000 );
ck( 'which is what MAX_TEXT says', WPCPM_Field_Value::MAX_TEXT, 5000 );
ck( 'a caller may pass its own cap', strlen( fv( str_repeat( 'x', 20 ), array( 'type' => 'text', 'max_text' => 10 ) )['value'] ), 10 );
ck( 'maxlength wins over the caller cap', fv( 'abcdef', array( 'type' => 'text', 'maxlength' => 3, 'max_text' => 10 ) ), accepted( 'abc' ) );
ck( 'a spec with no type is a line of text', fv( 'abcdef', array( 'maxlength' => 3 ) ), accepted( 'abc' ) );
ck( 'an array is refused', fv( array( 'x' ), $text ), refused( 'not_scalar' ) );

echo "\n=== textarea and richtext: kept as typed, capped by the caller or the class ===\n";

$richtext = array( 'type' => 'richtext' );

ck( 'line breaks survive', fv( "  line one\nline two  ", $textarea ), accepted( "line one\nline two" ) );
ck( 'a bullet list stays a bullet list', fv( "- a bullet\n- another", $richtext ), accepted( "- a bullet\n- another" ) );
ck( 'a textarea has no maxlength, that is a text rule',
    fv( 'abcdef', array( 'type' => 'textarea', 'maxlength' => 3 ) ), accepted( 'abcdef' ) );
ck( 'the class cap holds a textarea', strlen( fv( str_repeat( 'x', 6000 ), $textarea )['value'] ), 5000 );
ck( 'and rich text', strlen( fv( str_repeat( 'x', 6000 ), $richtext )['value'] ), 5000 );
ck( 'a caller cap holds a textarea', strlen( fv( str_repeat( 'x', 20 ), array( 'type' => 'textarea', 'max_text' => 12 ) )['value'] ), 12 );
ck( 'and rich text', strlen( fv( str_repeat( 'x', 20 ), array( 'type' => 'richtext', 'max_text' => 12 ) )['value'] ), 12 );
ck( 'an emptied box clears the column', fv( '', $textarea ), accepted( '' ) );
ck( 'a type nobody has heard of is kept as typed rather than lost',
    fv( 'Some words', array( 'type' => 'mystery' ) ), accepted( 'Some words' ) );
ck( 'an array is refused', fv( array( 'x' ), $richtext ), refused( 'not_scalar' ) );

echo "\n=== rating: a whole number on the scale, cleared with null ===\n";

$rating = array( 'type' => 'rating', 'max' => 5 );

ck( 'a rating on the scale is kept', fv( '4', $rating ), accepted( 4 ) );
ck( 'the ends of the scale are on it', array( fv( '1', $rating ), fv( '5', $rating ) ), array( accepted( 1 ), accepted( 5 ) ) );
ck( 'nothing chosen clears it rather than failing', fv( '', $rating ), accepted( null ) );
ck( 'a six is refused', fv( '6', $rating ), refused( 'out_of_range' ) );
ck( 'a zero is refused', fv( '0', $rating ), refused( 'out_of_range' ) );
ck( 'and so is a word', fv( 'five', $rating ), refused( 'out_of_range' ) );
ck( 'the scale is five wide unless the spec says', fv( '5', array( 'type' => 'rating' ) ), accepted( 5 ) );
ck( 'and a six is still off it', fv( '6', array( 'type' => 'rating' ) ), refused( 'out_of_range' ) );
ck( 'a wider scale takes its top', fv( '10', array( 'type' => 'rating', 'max' => 10 ) ), accepted( 10 ) );
ck( 'an array is refused', fv( array( '4' ), $rating ), refused( 'not_scalar' ) );

echo "\n=== select: one of the choices the column has, cleared with null ===\n";

$select = array( 'type' => 'select', 'choices' => array( 'Yes', 'Maybe', 'No' ) );

ck( 'a real choice is kept', fv( 'Maybe', $select ), accepted( 'Maybe' ) );
ck( 'whitespace around it is not part of it', fv( ' Yes ', $select ), accepted( 'Yes' ) );
ck( 'no answer clears it', fv( '', $select ), accepted( null ) );
ck( 'a value that is not on the list is refused', fv( 'Definitely', $select ), refused( 'bad_choice' ) );
ck( "and case matters, because Airtable's choices do", fv( 'yes', $select ), refused( 'bad_choice' ) );
ck( 'a spec with no choices takes nothing', fv( 'Yes', array( 'type' => 'select' ) ), refused( 'bad_choice' ) );
ck( 'an array is refused', fv( array( 'Yes' ), $select ), refused( 'not_scalar' ) );

echo "\n=== date: YYYY-MM-DD on the calendar, cleared with null ===\n";

$date = array( 'type' => 'date' );

ck( 'a real date is taken as typed', fv( '2026-06-30', $date ), accepted( '2026-06-30' ) );
ck( 'whitespace around it is not part of it', fv( ' 2026-06-30 ', $date ), accepted( '2026-06-30' ) );
ck( 'an emptied box clears the column with null, which is how Airtable empties a date', fv( '', $date ), accepted( null ) );
ck( 'a day that is not on the calendar is refused', fv( '2026-06-31', $date ), refused( 'bad_date' ) );
ck( 'and so is 29 February in a common year', fv( '2025-02-29', $date ), refused( 'bad_date' ) );
ck( 'but not in a leap year', fv( '2024-02-29', $date ), accepted( '2024-02-29' ) );
ck( 'an unpadded date is refused rather than repaired', fv( '2026-6-3', $date ), refused( 'bad_date' ) );
ck( 'a date with a time on the end is refused', fv( '2026-06-30T00:00:00Z', $date ), refused( 'bad_date' ) );
ck( 'a date written the other way round is refused', fv( '30-06-2026', $date ), refused( 'bad_date' ) );
ck( 'a word is refused', fv( 'next Tuesday', $date ), refused( 'bad_date' ) );
ck( 'an array is refused before the pattern is looked at', fv( array( '2026-06-30' ), $date ), refused( 'not_scalar' ) );

echo "\n=== Every refusal key the class documents has been seen ===\n";

/*
 * The class docblock is the list, and the source is where it is read from.
 *
 * `WPCPM_Field_Value::clean()` hands a caller a key and no sentence, so the keys are the
 * contract; the docblock is where that contract is written down and the only place a reader
 * looks it up. Reading it here means a key added to the class without a check for it fails
 * this suite, a key removed from the class without its check going too fails it, and a key
 * documented but never producible fails it. A list typed out below would do none of the
 * three - it would just be a copy agreeing with itself.
 */
$class = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-field-value.php' );
$doc   = substr( $class, 0, (int) strpos( $class, 'final class WPCPM_Field_Value' ) );
$keys  = array();

if ( preg_match( '/The keys are(.*?)\./s', $doc, $sentence ) ) {
	preg_match_all( '/`([a-z_]+)`/', $sentence[1], $named );
	$keys = $named[1];
}

sort( $keys );
$produced = array_keys( $seen );
sort( $produced );

ck( 'the class names its keys where a reader would look for them', count( $keys ) > 0, true );
ck( 'every key it names has been produced by a check above, and no other key exists', $produced, $keys );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
