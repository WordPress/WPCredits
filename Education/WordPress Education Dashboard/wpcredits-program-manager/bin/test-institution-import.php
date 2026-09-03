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
		public static function fields() {
			return array(
				'student_record_name' => 'Full Name',
				'student_email'       => 'Email',
				'student_status'      => 'Status',
				'student_institution' => 'Educational Institutions',
				'student_start'       => 'Start Date',
				'student_profile'     => 'WP Profile',
				'report_name'         => 'Name',
				'report_email'        => 'Email',
				'report_status'       => 'Status',
				'report_instituton'   => 'Educational institution',
				'report_profile'      => 'WordPress Profile',
			);
		}
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

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	class WPCPM_Airtable {
		public static function flatten( $value, $glue = ', ' ) {
			return is_array( $value ) ? implode( $glue, array_map( 'strval', $value ) ) : (string) $value;
		}
		public static function link_ids( $value ) {
			return array_values( array_filter( (array) $value, 'strlen' ) );
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

/**
 * The base and the site, as far as the ladder can see them.
 *
 * `$GLOBALS['records']` is one table's rows; the fake client filters them the way Airtable
 * would, so the formulas the module builds are exercised rather than bypassed. `$GLOBALS['site']`
 * is the accounts `get_user_by()` finds.
 */
$GLOBALS['records'] = array();
$GLOBALS['site']    = array();
$GLOBALS['roster']  = array();
$GLOBALS['queries'] = array();

$GLOBALS['posts']  = array();
$GLOBALS['pmeta']  = array();
$GLOBALS['opts']   = array();
$GLOBALS['next_id'] = 500;
$GLOBALS['now']    = 1788400000;

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $autoload = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_insert_post( $args, $wp_error = false ) {
	$id = ++$GLOBALS['next_id'];
	$GLOBALS['posts'][ $id ] = (object) array_merge( array( 'ID' => $id, 'post_type' => '', 'post_author' => 0, 'post_status' => '' ), $args );
	return $id;
}
function wp_delete_post( $id, $force = false ) { $gone = isset( $GLOBALS['posts'][ (int) $id ] ); unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return $gone; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$out = array();

	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		$ok = true;

		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			if ( get_post_meta( $id, $clause['key'], true ) !== $clause['value'] ) { $ok = false; break; }
		}

		if ( $ok ) { $out[] = $id; }
	}

	return $out;
}
function register_post_type( $type, $args ) { $GLOBALS['registered'][ $type ] = $args; return true; }
function wp_hash( $v ) { return md5( (string) $v ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }

require_once __DIR__ . '/../includes/class-wpcpm-ceiling.php';

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function get_user_by( $by, $value ) {
	return isset( $GLOBALS['site'][ strtolower( (string) $value ) ] ) ? (object) array( 'ID' => 1 ) : false;
}

if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	class WPCPM_Roster_Index {
		public static function rows( $record_id ) {
			return isset( $GLOBALS['roster'][ $record_id ] ) ? $GLOBALS['roster'][ $record_id ] : array();
		}
	}
}

/**
 * A client that answers the two formulas this module builds, rather than a canned list.
 *
 * Filtering the fixtures with the formula is what makes the query assertions worth anything: a
 * stub that returned every seeded row whatever was asked would pass just as happily with the
 * formula left empty, which is the bug most worth catching here.
 */
class Fake_Airtable {
	public function formula_in( $field, array $values, $lower = false ) {
		$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );
		return empty( $values ) ? '' : 'in:' . $field . ':' . implode( ',', array_map( 'strtolower', $values ) );
	}
	public function formula_contains( $field, array $needles, $lower = true ) {
		$needles = array_values( array_filter( array_map( 'strval', $needles ), 'strlen' ) );
		return empty( $needles ) ? '' : 'has:' . $field . ':' . implode( ',', array_map( 'strtolower', $needles ) );
	}
	public function fetch_all( $table, array $args = array() ) {
		$GLOBALS['queries'][] = array( $table, isset( $args['formula'] ) ? $args['formula'] : '' );

		if ( isset( $GLOBALS['fail_table'] ) && $GLOBALS['fail_table'] === $table ) {
			return new WP_Error( 'airtable', 'the base said no' );
		}

		$formula = isset( $args['formula'] ) ? $args['formula'] : '';
		$rows    = isset( $GLOBALS['records'][ $table ] ) ? $GLOBALS['records'][ $table ] : array();

		if ( '' === $formula ) {
			return array();
		}

		list( $kind, $field, $list ) = explode( ':', $formula, 3 );
		$wanted                      = explode( ',', $list );
		$out                         = array();

		foreach ( $rows as $row ) {
			$cell = isset( $row['fields'][ $field ] ) ? (string) $row['fields'][ $field ] : '';

			foreach ( $wanted as $needle ) {
				$hit = 'in' === $kind
					? strtolower( $cell ) === $needle
					: false !== strpos( strtolower( $cell ), $needle );

				if ( $hit ) {
					$out[] = $row;
					break;
				}
			}
		}

		return $out;
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

echo "\n=== The ladder: what the base and this site already know ===\n";

$HERE      = 'recHERE000000001';
$ELSEWHERE = 'recELSE000000001';
$SETTINGS  = array( 'students_table' => 'tblStudents', 'reports_table' => 'tblReports' );

/** Seed the base, the site and this institution's roster, then run one file through the ladder. */
function ladder( $csv, $students = array(), $reports = array(), $site = array(), $roster = array() ) {
	global $HERE, $SETTINGS;

	$GLOBALS['records'] = array( 'tblStudents' => $students, 'tblReports' => $reports );
	$GLOBALS['site']    = array_flip( array_map( 'strtolower', $site ) );
	$GLOBALS['roster']  = array( $HERE => $roster );
	$GLOBALS['queries'] = array();
	unset( $GLOBALS['fail_table'] );

	$rows = WPCPM_Institution_Import::clean_rows( WPCPM_Institution_Import::parse( $csv )['rows'] );

	return WPCPM_Institution_Import::check_against_base( $rows, $HERE, new Fake_Airtable(), $SETTINGS );
}

/** One Airtable record, in the shape the client returns. */
function rec( $id, array $fields ) {
	return array( 'id' => $id, 'fields' => $fields );
}

$one = "Name,Email\nAnna Kowalska,anna@uek.krakow.pl\n";

// Their own list, so they are told about it in full: this is the school's own roster and
// nothing about it is a fact about anybody else.
$here = ladder(
	$one,
	array( rec( 'recS1', array( 'Full Name' => 'Anna Kowalska', 'Email' => 'Anna@uek.krakow.pl', 'Status' => 'In Sensei', 'Start Date' => '2026-02-01', 'Educational Institutions' => array( $HERE ) ) ) )
);

ck( 'a student already on this roster is named as such', $here[0]['verdict'], 'exists-here' );
ck( 'with the name the base holds', $here[0]['detail']['name'], 'Anna Kowalska' );
ck( 'their status', $here[0]['detail']['status'], 'In Sensei' );
ck( 'and when they started', $here[0]['detail']['start'], '2026-02-01' );
// The address is matched as a mailbox, not as a spelling: the base holds it as it was typed.
ck( 'the address matched despite its casing', $here[0]['detail']['record'], 'recS1' );

// Half a record: a reports row on this institution with no Students row behind it. The school
// is not being kept from anything, so the blank refusal would be untrue as well as unhelpful.
$half = ladder(
	$one,
	array(),
	array( rec( 'recR1', array( 'Name' => 'Anna Kowalska', 'Email' => 'anna@uek.krakow.pl', 'Status' => 'In Sensei', 'Educational institution' => array( $HERE ) ) ) )
);
ck( 'a reports row on this institution is also "already here"', $half[0]['verdict'], 'exists-here' );
ck( 'flagged as the half-made record it is', $half[0]['detail']['reports_only'], true );

echo "\n=== Everything else gets one answer, and one only ===\n";

// The four ways a row can hit something outside this school. The institution must not be able
// to tell them apart: three hundred addresses pasted in would otherwise answer three hundred
// questions about who is in the program.
$causes = array(
	'another institution' => ladder( $one, array( rec( 'recS2', array( 'Full Name' => 'Anna Kowalska', 'Email' => 'anna@uek.krakow.pl', 'Status' => 'In Sensei', 'Educational Institutions' => array( $ELSEWHERE ) ) ) ) ),
	'no institution'      => ladder( $one, array( rec( 'recS3', array( 'Full Name' => 'Anna Kowalska', 'Email' => 'anna@uek.krakow.pl', 'Status' => 'Interested', 'Educational Institutions' => array() ) ) ) ),
	'a reports row'       => ladder( $one, array(), array( rec( 'recR2', array( 'Name' => 'Anna K', 'Email' => 'anna@uek.krakow.pl', 'Educational institution' => array( $ELSEWHERE ) ) ) ) ),
	'an account here'     => ladder( $one, array(), array(), array( 'anna@uek.krakow.pl' ) ),
);

foreach ( $causes as $what => $result ) {
	ck( sprintf( 'a hit at %s blocks the row', $what ), $result[0]['verdict'], 'blocked' );
	// The whole point: nothing about where the hit was reaches the row the school will read.
	ck( sprintf( 'and tells the school nothing about it (%s)', $what ), $result[0]['detail'], array() );
}

$reasons = array_map(
	function ( $result ) {
		return $result[0]['manager_reason'];
	},
	$causes
);
// A program manager does get the difference, on the batch and not on the school's screen.
ck( 'a program manager is told which it was, and the four differ', count( array_unique( $reasons ) ), 4 );

// The profile is an identity too. A student enrolled elsewhere under another address is found
// by their handle, and the row is blocked exactly as if the address had matched.
$by_handle = ladder(
	"Name,Email,Profile\nAnna Kowalska,new.address@uek.krakow.pl,@annak\n",
	array( rec( 'recS4', array( 'Full Name' => 'Anna Kowalska', 'Email' => 'other@example.test', 'WP Profile' => 'https://profiles.wordpress.org/annak/', 'Educational Institutions' => array( $ELSEWHERE ) ) ) )
);
ck( 'a handle already on a record blocks the row', $by_handle[0]['verdict'], 'blocked' );
ck( 'saying no more than the others do', $by_handle[0]['detail'], array() );

// FIND() is a substring test, so the base answers with candidates. `ann` inside `joanna` is
// exactly the false positive that would block an innocent row, and PHP is what throws it out.
$substring = ladder(
	"Name,Email,Profile\nAnn Nowak,ann@uek.krakow.pl,@ann\n",
	array( rec( 'recS5', array( 'Full Name' => 'Joanna Lis', 'Email' => 'joanna@example.test', 'WP Profile' => 'https://profiles.wordpress.org/joanna/', 'Educational Institutions' => array( $ELSEWHERE ) ) ) )
);
ck( 'a handle inside a longer one is not a match', $substring[0]['verdict'], 'ok' );

// The same person, written three ways. None of the spellings can defeat the comparison,
// because both sides go through the normaliser the file went through.
foreach ( array( 'profiles.wordpress.org/annak', 'https://profiles.wordpress.org/AnnaK/', 'annak' ) as $spelling ) {
	$variant = ladder(
		"Name,Email,Profile\nAnna Kowalska,fresh@uek.krakow.pl,@annak\n",
		array( rec( 'recS6', array( 'Full Name' => 'Anna Kowalska', 'Email' => 'other@example.test', 'WP Profile' => $spelling, 'Educational Institutions' => array( $ELSEWHERE ) ) ) )
	);
	ck( sprintf( 'the URL written as "%s" still matches', $spelling ), $variant[0]['verdict'], 'blocked' );
}

// Two characters is most of the base. Asking is a request spent to throw every row away again.
$short = ladder( "Name,Email,Profile\nAnna Kowalska,anna@uek.krakow.pl,@an\n" );
$asked = array_filter( $GLOBALS['queries'], function ( $q ) { return 0 === strpos( $q[1], 'has:' ); } );
ck( 'a handle under three characters is not searched for', $asked, array() );

echo "\n=== A resemblance warns; it does not refuse ===\n";

$near = ladder(
	"Name,Email\nanna  kowalska,fresh@uek.krakow.pl\n",
	array(), array(), array(),
	array( array( 'name' => 'Anna Kowalska', 'email_key' => 'anna@uek.krakow.pl' ) )
);
// Two people at one university do share a name, and refusing on a resemblance would make the
// school argue with a robot about it.
ck( 'a name already on the roster is a warning, not a block', $near[0]['verdict'], 'near-name' );
ck( 'and names who it resembles', $near[0]['detail']['near'], 'Anna Kowalska' );

$unlike = ladder(
	"Name,Email\nAnna Kowalska-Nowak,fresh@uek.krakow.pl\n",
	array(), array(), array(),
	array( array( 'name' => 'Anna Kowalska', 'email_key' => 'anna@uek.krakow.pl' ) )
);
ck( 'a different name is not a resemblance', $unlike[0]['verdict'], 'ok' );

echo "\n=== What the ladder does not do ===\n";

// Rows the cleaner already refused are not looked up: they are not going to be created, and
// asking about them spends requests to change nothing.
$skipped = ladder( "Name,Email\n=cmd(),anna@uek.krakow.pl\n" );
ck( 'an invalid row keeps its verdict', $skipped[0]['verdict'], 'invalid' );
ck( 'and nothing was asked about it', $GLOBALS['queries'], array() );

$clean = ladder( $one );
ck( 'a row nothing knows about is ready to create', $clean[0]['verdict'], 'ok' );

// One request per table per chunk, and the address list is in the formula: a stub that ignored
// the formula would pass every assertion above with the query left empty.
ck( 'two tables were asked, students and reports, by address', count( $GLOBALS['queries'] ), 2 );
ck( 'and the address was in the query', $GLOBALS['queries'][0][1], 'in:Email:anna@uek.krakow.pl' );

// The first error stops the ladder rather than a verdict being guessed from half an answer.
$GLOBALS['fail_table'] = 'tblReports';
$rows                  = WPCPM_Institution_Import::clean_rows( WPCPM_Institution_Import::parse( $one )['rows'] );
$errored               = WPCPM_Institution_Import::check_against_base( $rows, $HERE, new Fake_Airtable(), $SETTINGS );
ck( 'an error from the base is returned, not swallowed', is_wp_error( $errored ), true );
unset( $GLOBALS['fail_table'] );

echo "\n=== The batch: staged, read, cancelled ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();

$staged = WPCPM_Institution_Import::stage(
	$HERE,
	7,
	array( 'status' => 'In Sensei', 'start' => '2026-09-07', 'end' => '', 'confirmed' => true ),
	WPCPM_Institution_Import::clean_rows( WPCPM_Institution_Import::parse( $one )['rows'] ),
	array( 'Notes' )
);

ck( 'staging answers with a post ID', $staged > 0, true );

$read = WPCPM_Institution_Import::batch( $staged );

ck( 'the batch knows its institution', $read['institution'], $HERE );
ck( 'and starts staged', $read['state'], 'staged' );
ck( 'the member who ran it is the author', $read['author'], 7 );
ck( 'the batch-wide answers are kept', $read['values']['start'], '2026-09-07' );
ck( 'so are the rows', count( $read['rows'] ), 1 );
ck( 'and the columns nobody read', $read['unknown'], array( 'Notes' ) );

// A post title is the one field of a private post that tends to escape into an admin list or
// a search result, and this one is read by nobody: the screen renders the rows.
ck( 'no student name is in the post title', false !== strpos( $GLOBALS['posts'][ $staged ]->post_title, 'Anna' ), false );
ck( 'and the post is private', $GLOBALS['posts'][ $staged ]->post_status, 'private' );

// One at a time, so a school cannot stage six lists and confirm them in an order nobody meant.
ck( 'the institution has one waiting', WPCPM_Institution_Import::staged_for( $HERE ), $staged );
ck( 'and another institution has none', WPCPM_Institution_Import::staged_for( $ELSEWHERE ), 0 );

ck( 'a staged batch can be cancelled', WPCPM_Institution_Import::cancel( $staged ), true );
ck( 'and is gone afterwards', WPCPM_Institution_Import::batch( $staged ), null );

// A batch being created has records in Airtable behind it. Deleting the post would leave them
// with nothing on this site that remembers why they exist, and their Site import key pointing
// at a batch that is gone.
$creating = WPCPM_Institution_Import::stage( $HERE, 7, array(), array(), array() );
update_post_meta( $creating, WPCPM_Institution_Import::META_STATE, 'creating' );
ck( 'a batch being created cannot be cancelled', WPCPM_Institution_Import::cancel( $creating ), false );
ck( 'and is still there', WPCPM_Institution_Import::batch( $creating )['state'], 'creating' );

// Eighteen characters. register_post_type() refuses a name over twenty and returns a WP_Error
// nothing reads, so an over-long name is a type that silently does not exist.
ck( 'the post type name fits inside the twenty WordPress allows', strlen( WPCPM_Institution_Import::POST_TYPE ) <= 20, true );

echo "\n=== The ceilings refuse before the work ===\n";

$GLOBALS['opts'] = array();

// A person correcting a file and trying again does it two or three times.
$checks = array();
for ( $i = 0; $i < 6; $i++ ) {
	$checks[] = WPCPM_Institution_Import::may_check( $HERE )['ok'];
}
ck( 'five checks an hour are allowed and the sixth is not', $checks, array( true, true, true, true, true, false ) );
ck( 'and the refusal is named', WPCPM_Institution_Import::may_check( $HERE )['problem'], 'too_often' );
// One school running out says nothing about another.
ck( 'another institution is unaffected', WPCPM_Institution_Import::may_check( $ELSEWHERE )['ok'], true );

$GLOBALS['opts'] = array();
ck( 'a file of two hundred rows fits the day', WPCPM_Institution_Import::claim_rows( $HERE, 200 )['ok'], true );
ck( 'and so does a second', WPCPM_Institution_Import::claim_rows( $HERE, 200 )['ok'], true );
// All of them or none: a batch that would cross the line is refused whole, rather than let
// part way in and stopped in the middle with half a school's term created.
ck( 'a third that would cross the line is refused whole', WPCPM_Institution_Import::claim_rows( $HERE, 300 )['ok'], false );
ck( 'the day is untouched by that refusal', WPCPM_Institution_Import::rows_used( $HERE ), 400 );
ck( 'and what does fit is still allowed', WPCPM_Institution_Import::claim_rows( $HERE, 200 )['ok'], true );

// Once the day is full, a check is refused before a byte is parsed rather than after.
ck( 'a full day refuses the next check up front', WPCPM_Institution_Import::may_check( $HERE )['problem'], 'rows_today' );

echo "\n=== The log is a ratio, not a list of people ===\n";

$GLOBALS['opts'] = array();
WPCPM_Institution_Import::log_check( $HERE, 7, 20, 1 );
WPCPM_Institution_Import::log_check( $HERE, 7, 20, 18 );

$log = WPCPM_Institution_Import::log();
ck( 'both checks are logged', count( $log ), 2 );
// No name and no address: the count is the signal, and the names are in the batch post for as
// long as it lives.
ck( 'and neither line carries a person', array_keys( $log[0] ), array( 'institution', 'member', 'rows', 'blocked', 'when' ) );

// A member feeding addresses in to see which come back blocked looks exactly like this.
$odd = WPCPM_Institution_Import::suspicious();
ck( 'the mostly-blocked check is the one flagged', count( $odd ), 1 );
ck( 'and it is the right one', $odd[0]['blocked'], 18 );

$GLOBALS['opts'] = array();
for ( $i = 0; $i < WPCPM_Institution_Import::LOG_MAX + 10; $i++ ) {
	WPCPM_Institution_Import::log_check( $HERE, 7, 1, 0 );
}
// It is an option, read on a manager screen. A log that grew without a bound is eventually
// the reason one is slow.
ck( 'the log is capped', count( WPCPM_Institution_Import::log() ), WPCPM_Institution_Import::LOG_MAX );

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
// `wp_insert_post` and `update_post_meta` were on this list until the batch store landed in
// the same file, and they are off it now because staging a checked list is exactly what that
// store does. What the list still means, and what is worth asserting, is unchanged: this file
// creates nothing in Airtable, sends no mail, and reads no superglobal - every value it works
// on was handed to it by a caller that had already decided who was asking.
foreach ( array( 'wp_remote_', 'create_records', 'update_records', 'wp_mail', '$_POST', '$_FILES', '$_GET' ) as $forbidden ) {
	ck( sprintf( 'no %s anywhere in it', $forbidden ), false !== strpos( $code, $forbidden ), false );
}

// Never in this project, chat, code or comment alike.
foreach ( array( 'import' => $source, 'suite' => file_get_contents( __FILE__ ) ) as $what => $text ) {
	ck( sprintf( 'no dash but the plain hyphen in the %s', $what ), preg_match( '/[\x{2013}\x{2014}]/u', $text ), 0 );
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
