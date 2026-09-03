<?php
/**
 * Reading and cleaning an institution's list of students.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **A file that is not UTF-8 is refused, not repaired.** A Latin-1 export is the common way
 *   this arrives and converting it means guessing the source encoding; guessing wrong writes
 *   "FidÃ©litas" onto a record that is then created, synced and printed on a report. Refusing
 *   costs one re-export.
 * - A byte order mark is stripped, because Excel writes one and a parser that does not strip it
 *   reads the first header as `\xEF\xBB\xBFName` and matches nothing.
 * - Semicolons are read as well as commas: Excel exports them in most of the countries this
 *   program runs in, where the comma is the decimal separator.
 * - A quoted field carrying a newline survives, which is what `fgetcsv()` over a stream buys
 *   over splitting on newlines first.
 * - Headers are matched by spelling, not by schema. `Full Name`, `full_name` and `E-Mail` are
 *   things a registry actually exports, and refusing them would send a person back to hand-edit
 *   a header row. An unknown column is listed back, never a reason to refuse a file.
 * - **A name beginning `=`, `+`, `-` or `@` refuses the row.** It is a formula to every
 *   spreadsheet a program manager will open the export in, and it lands in the subject line of
 *   the welcome automation. No real name starts with those.
 * - Mandatory fields refuse the row; optional fields are dropped and named. A mistyped field of
 *   study costs a school one column, not one student.
 * - **Both halves of an in-file duplicate are blocked.** Creating one of the two would be this
 *   plugin deciding which of a school's lines was the real one.
 * - The ceilings refuse before the work: bytes before parsing, rows during it.
 * - A `start_date` or `program` column may agree with the batch or be empty, and disagreeing
 *   refuses the file naming the lines, because the file then describes a different import from
 *   the one the form describes.
 * - Nothing in this file opens a socket, writes a post or creates a record. The suite asserts
 *   that too, by reading the source.
 *
 * Run from the plugin root:  php bin/test-institution-import.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['filters'] = array();

function apply_filters( $tag, $value ) {
	foreach ( isset( $GLOBALS['filters'][ $tag ] ) ? $GLOBALS['filters'][ $tag ] : array() as $callback ) {
		$value = call_user_func( $callback, $value );
	}

	return $value;
}
function add_filter( $tag, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $tag ][] = $callback; }
function add_action() {}
function do_action() {}
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function absint( $v ) { return abs( (int) $v ); }
function get_option( $k, $d = false ) { return $d; }
function sanitize_email( $email ) { return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL ); }
function is_email( $email ) { return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL ); }

/**
 * The real one strips tags, then control characters, then percent-encoded octets.
 *
 * Close enough here that the difference cannot hide a bug: what the tests turn on is that tabs
 * and newlines are gone by the time a cleaned value is compared, which is exactly what this
 * does, and that the control-character check runs *before* it, which is a property of the code
 * under test rather than of this stub.
 */
function sanitize_text_field( $str ) {
	$str = wp_strip_all_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );

	return trim( (string) $str );
}
function wp_strip_all_tags( $str ) { return strip_tags( (string) $str ); }
function sanitize_textarea_field( $str ) { return (string) $str; }

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-import.php';

/**
 * Only the two members the import calls, so the suite stays about the import.
 *
 * `wporg_username()` is the real thing's contract, not a re-implementation of its parser: what
 * the import needs from it is a handle out of any of the spellings a school's file carries, and
 * pinning that here would pin the wrong file's behaviour. Its own suite owns the parser.
 */
if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function wporg_username( $raw ) {
			$value = trim( (string) $raw );
			$value = trim( $value, " \t\n\r\0\x0B<>" );
			$value = ltrim( $value, '@' );
			$value = preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $value );
			$value = preg_split( '/[?#]/', $value )[0];

			if ( false !== strpos( $value, '/' ) ) {
				$parts = array_values( array_filter( explode( '/', $value ), 'strlen' ) );

				if ( ! empty( $parts ) && false !== strpos( $parts[0], '.' ) ) {
					array_shift( $parts );
				}

				$value = ! empty( $parts ) ? array_pop( $parts ) : '';
			}

			$value = strtolower( trim( $value ) );

			return preg_match( '/^[a-z0-9][a-z0-9._\-]{0,59}$/', $value ) ? $value : '';
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Student_Form' ) ) {
	class WPCPM_Institution_Student_Form {
		public static function choices( $name ) {
			return 'field_of_study' === $name
				? array(
					'Technology & Engineering',
					'Design & Creative Media',
					'Languages, Communication & Writing',
					'Business, Marketing & Management',
					'Education & Learning',
					'Natural Sciences & Mathematics',
					'Health & Medicine',
					'Arts & Architecture',
					'Humanities & Social Sciences',
				)
				: array();
		}
	}
}

$fails = 0;
$total = 0;

function ck( $label, $actual, $expected ) {
	global $fails, $total;
	++$total;

	if ( $actual === $expected ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $actual, true ), var_export( $expected, true ) );
}

/** The verdicts of a cleaned list, in file order, so a block reads as one line. */
function verdicts( array $rows ) {
	return array_map(
		function ( $row ) {
			return $row['verdict'];
		},
		$rows
	);
}

/** One cleaned row, by the line it came from. */
function at_line( array $rows, $line ) {
	foreach ( $rows as $row ) {
		if ( (int) $row['line'] === (int) $line ) {
			return $row;
		}
	}

	return array();
}

echo "=== A file is read, whatever the registry exported ===\n";

$csv = "Full Name,E-Mail,WordPress.org profile,Field of study,Tutor\n"
	. "Anna Kowalska,Anna@uek.krakow.pl,https://profiles.wordpress.org/annak/,Technology & Engineering,Dr Nowak\n"
	. "Bartek Zielinski,bartek@uek.krakow.pl,@bartekz,design & creative media,\n";

$parsed = WPCPM_Institution_Import::parse( $csv );

ck( 'the file is accepted', $parsed['ok'], true );
ck( 'both students are read', count( $parsed['rows'] ), 2 );
ck( 'and no column was left unrecognised', $parsed['unknown'], array() );
ck( 'the line number is the file\'s own, so a refusal can name it', $parsed['rows'][0]['line'], 2 );

$rows = WPCPM_Institution_Import::clean_rows( $parsed['rows'] );

ck( 'both rows may be created', verdicts( $rows ), array( 'ok', 'ok' ) );
ck( 'the address is kept as typed', $rows[0]['email'], 'Anna@uek.krakow.pl' );
// Airtable holds addresses as they were typed, and two spellings of one mailbox are one person.
ck( 'and lowercased for comparing', $rows[0]['email_key'], 'anna@uek.krakow.pl' );
ck( 'a profile URL becomes a handle', $rows[0]['handle'], 'annak' );
ck( 'an @handle becomes the same kind of handle', $rows[1]['handle'], 'bartekz' );
// The base holds these as URLs and a school's column holds five spellings of one.
ck( 'and both are stored canonically', $rows[1]['profile'], 'https://profiles.wordpress.org/bartekz/' );
ck( 'the field of study is matched however it was cased', $rows[1]['field_of_study'], 'Design & Creative Media' );
ck( 'the tutor is carried', $rows[0]['tutor'], 'Dr Nowak' );
ck( 'and an empty one is not invented', $rows[1]['tutor'], '' );

echo "\n=== What a spreadsheet did to the file on the way ===\n";

// Excel writes one, and a parser that does not strip it reads `\xEF\xBB\xBFFull Name`.
$bom = WPCPM_Institution_Import::parse( "\xEF\xBB\xBFFull Name,Email\nAnna Kowalska,anna@uek.krakow.pl\n" );
ck( 'a byte order mark does not hide the first column', $bom['ok'], true );

// The comma is the decimal separator in most of the countries this program runs in.
$semi = WPCPM_Institution_Import::parse( "Full Name;Email;Tutor\nAnna Kowalska;anna@uek.krakow.pl;Dr Nowak\n" );
ck( 'semicolons are read as a delimiter', $semi['ok'], true );
ck( 'and the cells land in the right columns', $semi['rows'][0]['tutor'], 'Dr Nowak' );
ck( 'the delimiter is reported, for the preview to say', $semi['delimiter'], ';' );

$tabbed = WPCPM_Institution_Import::parse( "Full Name\tEmail\nAnna Kowalska\tanna@uek.krakow.pl\n" );
ck( 'a tab-separated export is read too', count( $tabbed['rows'] ), 1 );

// Valid CSV. Splitting on newlines before parsing would tear this row in half and then complain.
$quoted = WPCPM_Institution_Import::parse( "Full Name,Email,Tutor\n\"Kowalska, Anna\",anna@uek.krakow.pl,\"Dr Nowak\nDepartment of Design\"\n" );
ck( 'a quoted newline does not split the row', count( $quoted['rows'] ), 1 );
ck( 'and the comma inside quotes stays in the name', $quoted['rows'][0]['name'], 'Kowalska, Anna' );

$trailing = WPCPM_Institution_Import::parse( "Full Name,Email\nAnna Kowalska,anna@uek.krakow.pl\n\n\n,\n" );
ck( 'empty rows at the end are dropped rather than named', count( $trailing['rows'] ), 1 );

echo "\n=== Headers, as registries actually write them ===\n";

foreach ( array( 'Full Name', 'full_name', 'FULL-NAME', 'Student', 'Name' ) as $spelling ) {
	$try = WPCPM_Institution_Import::parse( $spelling . ",Email\nAnna Kowalska,anna@uek.krakow.pl\n" );
	ck( sprintf( '"%s" is the name column', $spelling ), $try['ok'] && 'Anna Kowalska' === $try['rows'][0]['name'], true );
}

foreach ( array( 'Email', 'E-Mail', 'e_mail', 'Mail', 'Email Address' ) as $spelling ) {
	$try = WPCPM_Institution_Import::parse( "Name,$spelling\nAnna Kowalska,anna@uek.krakow.pl\n" );
	ck( sprintf( '"%s" is the email column', $spelling ), $try['ok'] && 'anna@uek.krakow.pl' === $try['rows'][0]['email'], true );
}

// A registry that always exports a Notes column should not have to strip it first.
$extra = WPCPM_Institution_Import::parse( "Name,Email,Notes,Semester\nAnna Kowalska,anna@uek.krakow.pl,nothing,2\n" );
ck( 'an unknown column does not refuse the file', $extra['ok'], true );
ck( 'it is listed back so the school can see it was ignored', $extra['unknown'], array( 'Notes', 'Semester' ) );

$no_email = WPCPM_Institution_Import::parse( "Name,Tutor\nAnna Kowalska,Dr Nowak\n" );
ck( 'a file with no email column is refused', $no_email['problem'], 'no_columns' );
ck( 'and the refusal names what is missing', $no_email['detail']['missing'], array( 'email' ) );

// The two a record cannot be created without. Everything else this import reads is optional.
$bare = WPCPM_Institution_Import::parse( "Name,Email\nAnna Kowalska,anna@uek.krakow.pl\n" );
ck( 'name and email alone are a valid file', $bare['ok'], true );

echo "\n=== A file that is not UTF-8 is refused, not repaired ===\n";

// "Fidélitas" as Latin-1. Converting means guessing the source encoding, and guessing wrong
// writes the mangled name onto a record that is then created, synced and printed on a report.
$latin1 = WPCPM_Institution_Import::parse( "Name,Email\nFid\xE9litas,a@example.test\n" );
ck( 'a Latin-1 export is refused', $latin1['problem'], 'not_utf8' );
ck( 'and nothing was read out of it', $latin1['rows'], array() );

$utf8 = WPCPM_Institution_Import::parse( "Name,Email\nFidélitas Ana,ana@example.test\n" );
ck( 'the same file re-exported is accepted', $utf8['ok'], true );
ck( 'with the accent intact', $utf8['rows'][0]['name'], 'Fidélitas Ana' );

echo "\n=== The ceilings refuse before the work ===\n";

$rows_text = "Name,Email\n";

for ( $i = 0; $i < 301; $i++ ) {
	$rows_text .= sprintf( "Student %d,s%d@example.test\n", $i, $i );
}

$too_many = WPCPM_Institution_Import::parse( $rows_text );
ck( 'over three hundred rows is refused', $too_many['problem'], 'too_many_rows' );
ck( 'and the refusal names the ceiling', $too_many['detail']['max'], 300 );

$big = WPCPM_Institution_Import::parse( "Name,Email\n" . str_repeat( 'x', 300000 ) );
ck( 'a file over the byte ceiling is refused before parsing', $big['problem'], 'too_large' );

ck( 'an empty paste is refused', WPCPM_Institution_Import::parse( "   \n " )['problem'], 'empty' );
ck( 'a header with no rows under it is refused', WPCPM_Institution_Import::parse( "Name,Email\n" )['problem'], 'no_rows' );

echo "\n=== Cleaning: mandatory refuses the row, optional is dropped and named ===\n";

// A formula to Excel, Numbers and Sheets alike. Every program manager exports this list, and
// the name lands in the subject line of the welcome automation.
foreach ( array( '=HYPERLINK("http://x")', '+1+1', '-2', '@SUM(A1)' ) as $attack ) {
	$row = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => $attack, 'email' => 'a@example.test' ) );
	ck( sprintf( 'a name beginning "%s" refuses the row', substr( $attack, 0, 1 ) ), $row['verdict'], 'invalid' );
	ck( 'naming the reason', in_array( 'name_formula', $row['problems'], true ), true );
}

$piped = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => "Anna|Kowalska", 'email' => 'a@example.test' ) );
ck( 'a pipe in a name refuses the row', $piped['problems'], array( 'name_control' ) );

// The check has to run before sanitize_text_field(), which removes the very characters it looks
// for. A tab inside a name is a row that was torn out of another table.
$tabbed_name = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => "Anna\tKowalska", 'email' => 'a@example.test' ) );
ck( 'and so does a tab, which the sanitiser would have removed first', $tabbed_name['problems'], array( 'name_control' ) );

$short = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => 'A', 'email' => 'a@example.test' ) );
ck( 'a one-character name refuses the row', $short['problems'], array( 'name_length' ) );

$long = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => str_repeat( 'a', 201 ), 'email' => 'a@example.test' ) );
ck( 'and so does a name over two hundred characters', $long['problems'], array( 'name_length' ) );

$spaced = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => "  Anna   Kowalska  ", 'email' => 'a@example.test' ) );
ck( 'a name pasted out of a PDF has its spacing collapsed', $spaced['name'], 'Anna Kowalska' );

$bad_email = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => 'Anna Kowalska', 'email' => 'anna@' ) );
ck( 'an address that is not one refuses the row', $bad_email['problems'], array( 'email_invalid' ) );

$no_name = WPCPM_Institution_Import::clean_row( array( 'line' => 2, 'name' => '', 'email' => 'a@example.test' ) );
ck( 'a missing name refuses the row', $no_name['problems'], array( 'name_missing' ) );

// Optional from here down: the school loses a column, never the student.
$bad_study = WPCPM_Institution_Import::clean_row(
	array( 'line' => 2, 'name' => 'Anna Kowalska', 'email' => 'a@example.test', 'field_of_study' => 'Rocket Surgery' )
);
ck( 'an unknown field of study does not refuse the row', $bad_study['verdict'], 'ok' );
// create_records() sends no typecast, so a value spelled any other way is a 422 for the record.
ck( 'the field is dropped rather than sent', $bad_study['field_of_study'], '' );
ck( 'and the school is told which column went', $bad_study['warnings'], array( 'field_of_study_unknown' ) );

$bad_profile = WPCPM_Institution_Import::clean_row(
	array( 'line' => 2, 'name' => 'Anna Kowalska', 'email' => 'a@example.test', 'profile' => 'not a profile at all' )
);
ck( 'an unreadable profile does not refuse the row', $bad_profile['verdict'], 'ok' );
ck( 'but is named, or a column of them would go unnoticed', $bad_profile['warnings'], array( 'profile_unreadable' ) );

$bad_tutor = WPCPM_Institution_Import::clean_row(
	array( 'line' => 2, 'name' => 'Anna Kowalska', 'email' => 'a@example.test', 'tutor' => '=cmd()' )
);
ck( 'a formula in the tutor column is dropped, not refused', $bad_tutor['verdict'], 'ok' );
ck( 'and named', $bad_tutor['warnings'], array( 'tutor_rejected' ) );

$long_tutor = WPCPM_Institution_Import::clean_row(
	array( 'line' => 2, 'name' => 'Anna Kowalska', 'email' => 'a@example.test', 'tutor' => str_repeat( 'a', 121 ) )
);
ck( 'a paragraph in the tutor column is dropped too', $long_tutor['tutor'], '' );
ck( 'and named', $long_tutor['warnings'], array( 'tutor_too_long' ) );

echo "\n=== A person listed twice blocks both lines ===\n";

$dupes = WPCPM_Institution_Import::clean_rows(
	WPCPM_Institution_Import::parse(
		"Name,Email,Profile\n"
		. "Anna Kowalska,anna@uek.krakow.pl,\n"
		. "Bartek Zielinski,bartek@uek.krakow.pl,\n"
		. "Anna Kowalska,ANNA@uek.krakow.pl,\n"
	)['rows']
);

// Creating one of the two would be this plugin deciding which of a school's lines was real.
ck( 'both halves of a repeated address are blocked', verdicts( $dupes ), array( 'duplicate-file', 'ok', 'duplicate-file' ) );
ck( 'the first names the line of the second', at_line( $dupes, 2 )['duplicate_of'], 4 );
ck( 'and the second names the first', at_line( $dupes, 4 )['duplicate_of'], 2 );

// Two addresses, one person: a handle is as much an identity here as a mailbox is.
$handles = WPCPM_Institution_Import::clean_rows(
	WPCPM_Institution_Import::parse(
		"Name,Email,Profile\n"
		. "Anna Kowalska,anna@uek.krakow.pl,https://profiles.wordpress.org/annak/\n"
		. "Anna Kowalska,a.kowalska@uek.krakow.pl,@annak\n"
	)['rows']
);
ck( 'one handle under two addresses blocks both', verdicts( $handles ), array( 'duplicate-file', 'duplicate-file' ) );

// A row refused for its own reasons is not also called somebody's duplicate: the second label
// would bury the reason it was actually refused.
$mixed = WPCPM_Institution_Import::clean_rows(
	WPCPM_Institution_Import::parse(
		"Name,Email\n"
		. "=cmd(),anna@uek.krakow.pl\n"
		. "Anna Kowalska,anna@uek.krakow.pl\n"
	)['rows']
);
ck( 'an invalid row keeps its own verdict', verdicts( $mixed ), array( 'invalid', 'ok' ) );

echo "\n=== The file must describe the import the form describes ===\n";

$batch = array( 'status' => 'In Sensei', 'start' => '2026-09-07' );

$agrees = WPCPM_Institution_Import::parse(
	"Name,Email,Start date,Program\n"
	. "Anna Kowalska,anna@uek.krakow.pl,2026-09-07,In Sensei\n",
	$batch
);
ck( 'a file repeating the batch values is accepted', $agrees['ok'], true );
// They are properties of the batch, chosen on the form; a row never carries its own.
ck( 'and neither column reaches the row', isset( $agrees['rows'][0]['program'] ) || isset( $agrees['rows'][0]['start_date'] ), false );

$by_label = WPCPM_Institution_Import::parse(
	"Name,Email,Program\nAnna Kowalska,anna@uek.krakow.pl,WordPress Credits Program 150h\n",
	$batch
);
ck( 'the program may be named as the program calls it', $by_label['ok'], true );

$blank_cols = WPCPM_Institution_Import::parse(
	"Name,Email,Start date,Program\nAnna Kowalska,anna@uek.krakow.pl,,\n",
	$batch
);
ck( 'empty agrees with anything, so an always-exported column is harmless', $blank_cols['ok'], true );

$disagrees = WPCPM_Institution_Import::parse(
	"Name,Email,Start date\n"
	. "Anna Kowalska,anna@uek.krakow.pl,2026-09-07\n"
	. "Bartek Zielinski,bartek@uek.krakow.pl,2027-02-01\n",
	$batch
);
// Not a row-level problem to be cleaned away: one of the two descriptions is wrong, and only
// the person who wrote them can say which.
ck( 'a row disagreeing refuses the file', $disagrees['problem'], 'batch_mismatch' );
ck( 'naming the line', $disagrees['detail']['lines'], array( 3 ) );

$no_batch = WPCPM_Institution_Import::parse( "Name,Email,Start date\nAnna Kowalska,anna@uek.krakow.pl,2027-02-01\n" );
ck( 'with no batch to check against, the column is simply ignored', $no_batch['ok'], true );

echo "\n=== The spellings are a filter, because registries differ ===\n";

add_filter(
	'wpcpm_import_aliases',
	function ( $aliases ) {
		$aliases['name'][] = 'apellidos_y_nombre';
		return $aliases;
	}
);

$filtered = WPCPM_Institution_Import::parse( "Apellidos y Nombre,Email\nAnna Kowalska,anna@uek.krakow.pl\n" );
ck( 'a site can teach it a registry\'s own header', $filtered['ok'], true );
$GLOBALS['filters']['wpcpm_import_aliases'] = array();

echo "\n=== This file touches nothing ===\n";

$source = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-import.php' );

/**
 * The file with its comments removed.
 *
 * Scanned instead of the raw source, because the comments explain *why* the module does not
 * call these things and name them to do it. Reading the raw text made "no create_records
 * anywhere in it" fail on a sentence saying create_records() sends no typecast, which is the
 * assertion punishing the documentation for being specific.
 */
$code = '';

foreach ( token_get_all( $source ) as $token ) {
	if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		continue;
	}

	$code .= is_array( $token ) ? $token[1] : $token;
}

// The staging, the ladder against the base, the ceilings on how often and the creation loop are
// separate pieces. Keeping them out is what lets a school's file be read, and tested, with none
// of that in the way - and what makes it true that a preview cannot create or leak anything.
foreach ( array( 'wp_remote_', 'create_records', 'update_records', 'wp_insert_post', 'update_post_meta', 'wp_mail', '$_POST', '$_FILES', '$_GET' ) as $forbidden ) {
	ck( sprintf( 'no %s anywhere in it', $forbidden ), false !== strpos( $code, $forbidden ), false );
}

// Never in this project, chat, code or comment alike.
foreach ( array( 'import' => $source, 'suite' => file_get_contents( __FILE__ ) ) as $what => $text ) {
	ck( sprintf( 'no dash but the plain hyphen in the %s', $what ), preg_match( '/[\x{2013}\x{2014}]/u', $text ), 0 );
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
