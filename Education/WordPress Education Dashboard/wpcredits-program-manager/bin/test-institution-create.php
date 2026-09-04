<?php
/**
 * Creating the students of one confirmed batch, and what makes retrying it free.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The guard runs before every slice, whoever runs it.** A three hundred row batch takes a
 *   dozen slices over several minutes, most of them under cron with nobody signed in. An
 *   agreement returned, or the confirming member removed, part way through has to stop the
 *   rest, so the account that pressed Confirm is stored on the batch and re-asked every time.
 *   A guard that read `wp_get_current_user()` would answer "nobody" under cron and a revoke
 *   during a long batch would stop precisely nothing.
 * - An actor of 0 is refused rather than passed to the policy: `WPCPM_Roles::resolve_user()`
 *   reads a zero as "no argument" and falls back to the current user, which under a browser
 *   request is whoever is signed in and is never the account that confirmed.
 * - **A lost response creates one record, not two.** The row's state is written before the
 *   request rather than after, so a row that was in flight can be told from one that was never
 *   sent, and every record carries a `Site import key` that names the batch and the row, so
 *   the record that already exists can be found instead of made again.
 * - An address that matches but carries no key is somebody else's student. It is never adopted:
 *   the wrong ID stamped is the wrong person fenced onto this school's roster.
 * - **One row per `create_records()` call.** A batch call returns a re-indexed list of the IDs
 *   Airtable accepted, so one dropped row mis-assigns every ID after it.
 * - A `WP_Error` fails that row with the message word for word and the loop carries on.
 * - The duplicate ladder runs again before creating, `near-name` rows included, and a verdict
 *   that has changed since the preview blocks that row and says so.
 * - No `create_records()` call ever carries an empty or absent `Educational Institutions` cell,
 *   which is how a manager import with no institution would otherwise make a silent orphan per
 *   row that no roster and no fence can see afterwards.
 * - Nothing is written but the ten cells the design spec prints: not `Mentor`, not either
 *   consent box, not `Students Reports`, which the automation creates when a mentor is assigned.
 *
 * Run from the plugin root:  php bin/test-institution-create.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['opts']     = array();
$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['next_id']  = 700;
$GLOBALS['uid']      = 11;
$GLOBALS['allowed']  = true;
$GLOBALS['ground']   = 'member';
$GLOBALS['settled']  = true;
$GLOBALS['calls']    = array();
$GLOBALS['audit']    = array();
$GLOBALS['requests'] = array();
$GLOBALS['inserted'] = array();
$GLOBALS['cron']     = array();
$GLOBALS['created']  = array();
$GLOBALS['all']      = array();
$GLOBALS['base']     = array();
$GLOBALS['roster']   = array();
$GLOBALS['site']     = array();
$GLOBALS['refuse']   = array();
$GLOBALS['lose']     = array();
$GLOBALS['slow']     = 0;
$GLOBALS['fail']     = '';
$GLOBALS['hooks']    = array();

function __( $t, $d = null ) { return $t; }
function esc_html__( $t, $d = null ) { return $t; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return (string) $s; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_email( $e ) { return filter_var( trim( (string) $e ), FILTER_SANITIZE_EMAIL ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function absint( $v ) { return abs( (int) $v ); }
function number_format_i18n( $n ) { return (string) $n; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_date( $f ) { return gmdate( $f ); }
function add_action( $hook = '', $callback = null ) { $GLOBALS['hooks'][] = array( $hook, $callback ); }
function add_filter() {}
function do_action() {}
function apply_filters( $t, $v ) { return $v; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u ); }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

function wp_insert_post( $args, $e = false ) {
	$id                      = ++$GLOBALS['next_id'];
	$GLOBALS['posts'][ $id ] = (object) array_merge( array( 'ID' => $id, 'post_type' => '', 'post_author' => 0, 'post_status' => '' ), $args );
	return $id;
}
function wp_delete_post( $id, $f = false ) { $g = isset( $GLOBALS['posts'][ (int) $id ] ); unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return $g; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $s = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $c ) {
			if ( get_post_meta( $id, $c['key'], true ) !== $c['value'] ) { $ok = false; break; }
		}
		if ( $ok ) { $out[] = $id; }
	}
	return $out;
}
function register_post_type( $t, $a ) { return true; }
function get_user_by( $by, $v ) { return isset( $GLOBALS['site'][ strtolower( (string) $v ) ] ) ? (object) array( 'ID' => 3 ) : false; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return (object) array( 'ID' => (int) $GLOBALS['uid'] ); }

function wp_next_scheduled( $hook, $args = array() ) {
	$key = $hook . ':' . json_encode( $args );
	return isset( $GLOBALS['cron'][ $key ] ) ? $GLOBALS['cron'][ $key ] : false;
}
function wp_schedule_single_event( $when, $hook, $args = array() ) {
	$GLOBALS['cron'][ $hook . ':' . json_encode( $args ) ] = (int) $when;
	return true;
}

/**
 * The code and the data matter here, not only the message.
 *
 * `blame()` reads both to decide whether a refusal belongs to the row or to the base, so a
 * stub that carried only the message could not tell the two apart and the tests below would
 * all be about the same branch.
 */
class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

/** A killed request: the create reached Airtable and its answer never got back. */
class Lost extends Exception {}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';

class WPCPM_Mentors_Sync {
	public static function is_record_id( $id ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $id ); }
	public static function wporg_username( $raw ) {
		$v = strtolower( trim( ltrim( trim( (string) $raw ), '@' ) ) );
		$v = preg_replace( '#^https?://#', '', $v );
		$v = trim( (string) preg_replace( '#^profiles\.wordpress\.org/#', '', $v ), '/' );
		return preg_match( '/^[a-z0-9][a-z0-9._\-]{0,59}$/', $v ) ? $v : '';
	}
	public static function fields() {
		return array(
			'student_record_name' => 'Full Name',
			'student_email'       => 'Email',
			'student_status'      => 'Status',
			'student_institution' => 'Educational Institutions',
			'student_start'       => 'Start Date',
			'student_end'         => 'End Date',
			'student_profile'     => 'WP Profile',
			'student_import_key'  => 'Site import key',
			'report_name'         => 'Name',
			'report_email'        => 'Email',
			'report_status'       => 'Status',
			'report_instituton'   => 'Educational institution',
			'report_profile'      => 'WordPress Profile',
		);
	}
}

/**
 * A base that answers the formulas this module builds, and can lose an answer on demand.
 *
 * The point of filtering with the formula rather than returning a canned list is the import
 * key search: a stub that answered every query with the same row would pass just as happily
 * with the key left out of the formula, which is the one bug this suite exists to catch.
 */
class WPCPM_Airtable {
	public function __construct( $s = null ) {}

	public function formula_in( $field, array $values, $lower = false ) {
		$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );
		return empty( $values ) ? '' : 'in:' . $field . ':' . implode( ',', array_map( 'strtolower', $values ) );
	}

	public function formula_contains( $field, array $needles, $lower = true ) {
		$needles = array_values( array_filter( array_map( 'strval', $needles ), 'strlen' ) );
		return empty( $needles ) ? '' : 'has:' . $field . ':' . implode( ',', array_map( 'strtolower', $needles ) );
	}

	public function fetch_all( $table, array $args = array() ) {
		$formula              = isset( $args['formula'] ) ? (string) $args['formula'] : '';
		$GLOBALS['queries'][] = $formula;

		if ( '' !== $GLOBALS['fail'] && $GLOBALS['fail'] === $table ) {
			return new WP_Error( 'airtable', 'the base said no' );
		}

		if ( '' === $formula ) {
			return array();
		}

		// `{Column} = 'value'`, which is the shape the import key search builds by hand.
		if ( preg_match( "/^\{(.+)\} = '(.*)'$/", $formula, $m ) ) {
			$out = array();
			foreach ( $GLOBALS['base'] as $row ) {
				if ( isset( $row['fields'][ $m[1] ] ) && (string) $row['fields'][ $m[1] ] === $m[2] ) {
					$out[] = $row;
				}
			}
			return $out;
		}

		list( $kind, $field, $list ) = explode( ':', $formula, 3 );
		$wanted                      = explode( ',', $list );
		$out                         = array();

		foreach ( $GLOBALS['base'] as $row ) {
			$cell = isset( $row['fields'][ $field ] ) ? $row['fields'][ $field ] : '';
			$cell = is_array( $cell ) ? implode( ', ', $cell ) : (string) $cell;

			foreach ( $wanted as $needle ) {
				$hit = 'in' === $kind ? strtolower( $cell ) === $needle : false !== strpos( strtolower( $cell ), $needle );

				if ( $hit ) {
					$out[] = $row;
					break;
				}
			}
		}

		return $out;
	}

	public function create_records( $table, array $records ) {
		// Recorded whole and never trimmed: the map assertions read these back, and the last
		// block of the suite walks every call this run made looking for an orphan.
		$GLOBALS['calls'][] = array( 'create', count( $records ) );

		if ( $GLOBALS['slow'] > 0 ) {
			usleep( (int) $GLOBALS['slow'] );
		}

		$ids = array();

		foreach ( $records as $record ) {
			$fields               = $record['fields'];
			$GLOBALS['created'][] = $fields;
			// Never reset between scenarios: the last block of the suite walks every call the
			// whole run made looking for a record that would have been an orphan.
			$GLOBALS['all'][]     = $fields;
			$email                = isset( $fields['Email'] ) ? (string) $fields['Email'] : '';

			// The shape the real client answers with when Airtable took the request and refused
			// this one record: the code the client always uses, and the status in the data. The
			// status is what tells this row's own fault from a credential or a rate limit, so a
			// fixture without it would be testing the wrong branch of `blame()`.
			if ( in_array( $email, $GLOBALS['refuse'], true ) ) {
				return new WP_Error( 'wpcpm_airtable_error', 'INVALID_MULTIPLE_CHOICE_OPTIONS: Your field of study', array( 'status' => 422 ) );
			}

			// A refusal that is about the base rather than this record: the client hands the
			// same object back for every call that follows, so the slice has to stop.
			if ( ! empty( $GLOBALS['base_refusal'] ) ) {
				// Four shapes of "the whole import, not this row": a backoff the client honours
				// before sending, the base being unwell, a table that is not there (a wrong
				// students-table setting), and a request the client built too large.
				switch ( $GLOBALS['base_refusal'] ) {
					case 'unsent':
						return new WP_Error( 'wpcpm_airtable_rate_limited', 'Airtable asked us to wait 30 seconds before sending more requests.' );
					case 'not-found':
						return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 404): NOT_FOUND: Could not find table tblNOPE in application appX', array( 'status' => 404 ) );
					case 'too-large':
						return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 413): REQUEST_TOO_LARGE', array( 'status' => 413 ) );
					default:
						return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 503): the service is unavailable', array( 'status' => 503 ) );
				}
			}

			if ( ! empty( $GLOBALS['on_create'] ) && is_callable( $GLOBALS['on_create'] ) ) {
				call_user_func( $GLOBALS['on_create'], count( $GLOBALS['base'] ) );
			}

			$id            = sprintf( 'rec%014d', count( $GLOBALS['base'] ) + 1 );
			$GLOBALS['base'][] = array(
				'id'     => $id,
				'fields' => $fields,
			);
			$ids[]         = $id;

			if ( in_array( $email, $GLOBALS['lose'], true ) ) {
				// Written in the base, and the caller never hears about it.
				throw new Lost( $email );
			}
		}

		return $ids;
	}

	public static function flatten( $v, $g = ', ' ) { return is_array( $v ) ? implode( $g, array_map( 'strval', $v ) ) : (string) $v; }
	public static function link_ids( $v ) { return array_values( array_filter( (array) $v, 'strlen' ) ); }
}

class WPCPM_Roster_Index {
	public static function rows( $r ) { return isset( $GLOBALS['roster'][ $r ] ) ? $GLOBALS['roster'][ $r ] : array(); }
	public static function insert( $r, array $row ) { $GLOBALS['inserted'][] = array( $r, $row ); }
}
class WPCPM_Settings {
	public static function get() { return array( 'students_table' => 'tblStudents', 'reports_table' => 'tblReports' ); }
	public static function get_value( $k ) { return false; }
}
class WPCPM_Institution_Student_Form {
	public static function choices( $n ) { return 'field_of_study' === $n ? array( 'Technology & Engineering' ) : array(); }
}
class WPCPM_Institution_Agreement {
	public static function is_settled( $r ) { $GLOBALS['calls'][] = array( 'settled', $r ); return (bool) $GLOBALS['settled']; }
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_CACHE = 'cache';
	const EVIDENCE_LIVE  = 'live';
	public static function grounds() { return array( 'manager', 'member', 'system' ); }
	public static function record( array $entry ) { $GLOBALS['audit'][] = $entry; return 1; }
}
class WPCPM_Institution_Request {
	const KIND_ADD = 'add';
	public static function raise( $k, $i, $s, $a, $g = '' ) { $GLOBALS['requests'][] = array( $k, $i, $s, $a, $g ); return 1; }
}
class WPCPM_Institution_Policy {
	const ACT_ADD_STUDENT = 'add_student';
	public static function subject_institution( $id ) { return array( 'institution' => $id ); }
	public static function decide( $action, $subject, $user = null ) {
		// The user this was asked about is recorded, because "which account was the policy
		// asked about" is the whole of the revoke assertion.
		$GLOBALS['calls'][] = array( 'decide', isset( $subject['institution'] ) ? $subject['institution'] : '', $user );
		return array(
			'allowed'     => (bool) $GLOBALS['allowed'],
			'ground'      => (string) $GLOBALS['ground'],
			'institution' => isset( $subject['institution'] ) ? $subject['institution'] : '',
		);
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-create.php';

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

$HERE  = 'recHERE0000000001';
$THERE = 'recTHERE000000001';

/** A cleaned row in the shape the checker leaves it. */
function row( $line, $name, $email, array $over = array() ) {
	return array_merge(
		array(
			'line'           => $line,
			'name'           => $name,
			'email'          => $email,
			'email_key'      => strtolower( $email ),
			'profile'        => '',
			'handle'         => '',
			'field_of_study' => '',
			'tutor'          => '',
			'verdict'        => WPCPM_Institution_Import::OK,
			'problems'       => array(),
			'warnings'       => array(),
			'detail'         => array(),
			'manager_reason' => '',
		),
		$over
	);
}

/** A staged batch, confirmed by one account, in a world with nothing else in it. */
function stage( $institution, array $rows, array $over = array(), $actor = 11 ) {
	$values = array_merge(
		array(
			'status'   => WPCPM_Program::STATUS_150H,
			'start'    => '2026-09-07',
			'end'      => '',
			'notified' => true,
		),
		$over
	);

	$id = WPCPM_Institution_Import::stage( $institution, $actor, $values, $rows );
	WPCPM_Institution_Create::claim( $id, $actor, 'member' );

	return $id;
}

/** Everything a scenario must not inherit from the one before it. */
function fresh() {
	$GLOBALS['posts']    = array();
	$GLOBALS['pmeta']    = array();
	$GLOBALS['opts']     = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['audit']    = array();
	$GLOBALS['requests'] = array();
	$GLOBALS['inserted'] = array();
	$GLOBALS['cron']     = array();
	$GLOBALS['created']  = array();
	$GLOBALS['base']     = array();
	$GLOBALS['roster']   = array();
	$GLOBALS['site']     = array();
	$GLOBALS['refuse']   = array();
	$GLOBALS['lose']     = array();
	$GLOBALS['queries']  = array();
	$GLOBALS['slow']     = 0;
	$GLOBALS['fail']     = '';
	$GLOBALS['allowed']  = true;
	$GLOBALS['ground']   = 'member';
	$GLOBALS['settled']  = true;
	$GLOBALS['uid']      = 11;
}

/** The stored rows of a batch. */
function rows_of( $id ) {
	return WPCPM_Institution_Import::batch( $id )['rows'];
}

/** The state of a batch. */
function state_of( $id ) {
	return WPCPM_Institution_Import::batch( $id )['state'];
}

/** The states of every row, in order, so a block reads as one line. */
function row_states( $id ) {
	$out = array();

	foreach ( rows_of( $id ) as $row ) {
		$out[] = WPCPM_Institution_Create::state_of( $row );
	}

	return $out;
}

echo "=== What is written, and nothing else ===\n";

fresh();

$batch_id = stage(
	$HERE,
	array(
		row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ),
	)
);
$batch = WPCPM_Institution_Import::batch( $batch_id );
$cells = WPCPM_Institution_Create::fields_for( $batch, $batch['rows'][0], 0 );

// The six that are always sent. Every one of them is a cell the design spec prints.
ck(
	'the required cells, and only those, when nothing optional is given',
	array_keys( $cells ),
	array( 'Full Name', 'Email', 'Status', 'Educational Institutions', 'Start Date', 'Site import key' )
);
// From the batch, which stored it from `resolve_institution()`, and never from a form.
ck( 'the institution is the batch\'s, as a link list', $cells['Educational Institutions'], array( $HERE ) );

// **Both of the next two are asked against a row that disagrees, or they prove nothing.** A
// reviewer mutated `fields_for()` to prefer a per-row institution over the batch's, and to
// write the address as typed instead of the key, and all 367 checks stayed green: the fixture
// row carried no competing institution and its address was already lowercase, so each
// assertion was comparing a value with itself.
$hostile = WPCPM_Institution_Create::fields_for(
	$batch,
	array_merge(
		row( 2, 'Anna Kowalska', 'Anna@UEK.krakow.pl' ),
		// What a forged preview row would carry, and what a later feature might add innocently.
		array( 'institution' => $THERE )
	),
	0
);

ck( 'a row naming another school is written to the batch\'s all the same', $hostile['Educational Institutions'], array( $HERE ) );
ck( 'and never to the one on the row', in_array( $THERE, $hostile['Educational Institutions'], true ), false );
// Lowercased: the base holds addresses as typed, and every join in this plugin compares keys.
ck( 'the address is the lowercased one', $cells['Email'], 'anna@uek.krakow.pl' );
ck( 'a mixed-case address is written lowercased', $hostile['Email'], 'anna@uek.krakow.pl' );
ck( 'the status is the batch\'s program', $cells['Status'], WPCPM_Program::STATUS_150H );
// Names the batch and the row, which is what tells a lost response from somebody else's work.
ck( 'the import key names the batch and the row', $cells['Site import key'], 'imp-' . $batch_id . '-0' );

$full = WPCPM_Institution_Create::fields_for(
	array_merge( $batch, array( 'values' => array_merge( $batch['values'], array( 'end' => '2027-02-07' ) ) ) ),
	row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl', array( 'profile' => 'https://profiles.wordpress.org/bartekz/', 'field_of_study' => 'Technology & Engineering', 'tutor' => 'Dr Nowak' ) ),
	1
);

ck(
	'and the four optional ones appear only when they are given',
	array_keys( $full ),
	array( 'Full Name', 'Email', 'Status', 'Educational Institutions', 'Start Date', 'Site import key', 'End Date', 'WP Profile', 'Your field of study', 'Tutor ' )
);
// The column's real name ends in a space. Dropping it writes nothing and reads nothing back,
// which is why the fixture records it as load-bearing rather than as a typo.
ck( 'the tutor column keeps its trailing space', $full['Tutor '], 'Dr Nowak' );

// Airtable is sent no typecast, so an empty string in a single-select is a 422 for the whole
// record: a school that left the column out would lose the student rather than the cell.
$blank = WPCPM_Institution_Create::fields_for( $batch, row( 4, 'Cecylia Nowak', 'cecylia@uek.krakow.pl', array( 'field_of_study' => '' ) ), 2 );
ck( 'an empty optional cell is absent rather than empty', isset( $blank['Your field of study'] ), false );

// The automation creates the report and the feedback rows when a mentor is assigned; the
// consent boxes are the student's own acts, and the batch's tick is the school's statement.
foreach ( array( 'Mentor', 'Notes', 'Privacy Policy Compliance', 'English and proactivity acceptance', 'Total hours', 'Students Reports', 'Feedback', 'HelpScout', 'Contribution Area', 'Tutor email' ) as $never ) {
	ck( sprintf( '%s is never written', $never ), isset( $full[ $never ] ), false );
}

// Every column named here is one the base actually has. A rename in Airtable that nobody
// noticed is a 422 per record, on every student of every import.
$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/students-table-fields.json' ), true );

foreach ( array_keys( $full ) as $column ) {
	ck( sprintf( '"%s" is a column the base has', $column ), in_array( $column, $fixture['fields'], true ), true );
}

// A batch with no readable institution can only make rows belonging to nobody, which is the
// one shape of orphan no roster and no fence in this module can see afterwards.
$orphan = WPCPM_Institution_Create::fields_for( array_merge( $batch, array( 'institution' => '' ) ), $batch['rows'][0], 0 );
ck( 'a batch with no institution produces no map at all', $orphan, array() );

echo "\n=== The guard, before every slice, whoever runs it ===\n";

fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ), row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl' ) ) );

$GLOBALS['settled'] = false;
$out                = WPCPM_Institution_Create::create_slice( $batch_id );

// The agreement is checked apart from the policy, because a manager is not gated by it and an
// import is the institution's own act on its own roster.
ck( 'an unsettled agreement stops the slice', $out['problem'], 'agreement' );
ck( 'and nothing was created', count( $GLOBALS['created'] ), 0 );
ck( 'and the batch is parked', state_of( $batch_id ), WPCPM_Institution_Import::STATE_BLOCKED );
ck( 'with the reason on the record', get_post_meta( $batch_id, WPCPM_Institution_Create::META_REASON, true ), 'agreement' );
ck( 'and one audit row saying so', count( $GLOBALS['audit'] ), 1 );
ck( 'named as the import stopping', $GLOBALS['audit'][0]['kind'], WPCPM_Institution_Create::LOG_STOPPED );

fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ), array(), 42 );
$GLOBALS['allowed'] = false;
// Nobody is signed in, which is exactly the state a cron continuation runs in.
$GLOBALS['uid']     = 0;
$out                = WPCPM_Institution_Create::create_slice( $batch_id );

ck( 'a revoked actor stops the slice under cron too', $out['problem'], 'not-allowed' );
ck( 'and nothing was created', count( $GLOBALS['created'] ), 0 );
ck( 'and the batch is parked', state_of( $batch_id ), WPCPM_Institution_Import::STATE_BLOCKED );

$asked = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $asked[] = $call[2]; } }
// **The whole of the revoke rule.** The policy is asked about the account that pressed
// Confirm, read off the batch, and never about whoever is signed in now - which under cron
// is nobody at all.
ck( 'the policy was asked about the account that confirmed', $asked, array( 42 ) );

fresh();
$batch_id = WPCPM_Institution_Import::stage( $HERE, 11, array( 'status' => WPCPM_Program::STATUS_150H, 'start' => '2026-09-07', 'end' => '' ), array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ) );
$out      = WPCPM_Institution_Create::create_slice( $batch_id );

// Nobody claimed it, so there is no account to hold to the rule. `resolve_user()` reads a zero
// as "no argument" and falls back to the current user, so passing it on would decide about
// whoever happened to be signed in.
ck( 'a batch nobody claimed creates nothing', $out['problem'], 'no-actor' );
$asked = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $asked[] = $call[2]; } }
ck( 'and the policy was never asked with a zero', $asked, array() );

echo "\n=== A revoke between two slices ===\n";

fresh();
$rows = array();
foreach ( range( 1, 4 ) as $n ) {
	$rows[] = row( $n + 1, 'Student ' . $n, 'student' . $n . '@uek.krakow.pl' );
}
$batch_id = stage( $HERE, $rows );

// Four rows, a one second budget and a create that takes a third of it: the slice stops part
// way through by itself, which is the only honest way to reach a second slice.
$GLOBALS['slow'] = 350000;
$first           = WPCPM_Institution_Create::create_slice( $batch_id, 1 );
$GLOBALS['slow'] = 0;

ck( 'the first slice stopped with rows left', $first['problem'], 'progress' );
ck( 'and created some of them', $first['created'] > 0 && $first['created'] < 4, true );
ck( 'and the batch is still being created', state_of( $batch_id ), WPCPM_Institution_Import::STATE_CREATING );
// Cron carries it on when nobody is watching, with the batch named in the arguments so two
// schools' imports are two events rather than one.
ck( 'and a continuation is scheduled', false !== wp_next_scheduled( WPCPM_Institution_Import::CRON_TICK, array( $batch_id ) ), true );

$made               = $first['created'];
$GLOBALS['allowed'] = false;
$second             = WPCPM_Institution_Create::create_slice( $batch_id );

// The point of the whole piece: a revoke part way through a long batch stops the rest of it.
ck( 'the next slice creates nothing further', count( $GLOBALS['created'] ), $made );
ck( 'and parks the batch', state_of( $batch_id ), WPCPM_Institution_Import::STATE_BLOCKED );
ck( 'and says why', $second['problem'], 'not-allowed' );
// The students already created keep their records: the import stopped, it did not roll back.
ck( 'and the students already created are still counted', $second['created'], $made );

echo "\n=== The lock ===\n";

fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ) );

// Somebody else's slice is running. `add_option()` is the test and set: two requests reaching
// it together cannot both be told yes.
add_option( 'wpcpm_import_lock_' . $HERE, time(), '', false );
$out = WPCPM_Institution_Create::create_slice( $batch_id );

ck( 'a slice will not start while another holds the lock', $out['problem'], 'locked' );
ck( 'and creates nothing', count( $GLOBALS['created'] ), 0 );
ck( 'and leaves the batch where it was', state_of( $batch_id ), WPCPM_Institution_Import::STATE_STAGED );

// Older than a slice can take, so the request holding it is gone. Left alone, a lock a killed
// request was holding would strand the batch until somebody noticed.
update_option( 'wpcpm_import_lock_' . $HERE, time() - WPCPM_Institution_Create::LOCK_TIMEOUT - 1 );
$out = WPCPM_Institution_Create::create_slice( $batch_id );

ck( 'a stale lock is taken over', $out['problem'], 'created' );
ck( 'and the row is created', count( $GLOBALS['created'] ), 1 );
ck( 'and the lock is let go afterwards', get_option( 'wpcpm_import_lock_' . $HERE ), false );

echo "\n=== The ladder runs again, right before creating ===\n";

fresh();
$batch_id = stage(
	$HERE,
	array(
		row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ),
		// A soft warning at preview time. It has to be asked again, or the one row the school
		// was told was fine is the one row nothing checks.
		row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl', array( 'verdict' => WPCPM_Institution_Import::NEAR_NAME, 'detail' => array( 'near' => 'Bartosz Zielinski' ) ) ),
	)
);

// Somebody enrolled Bartek at another university between the preview and the confirm.
$GLOBALS['base'][] = array(
	'id'     => 'recOTHER000000001',
	'fields' => array( 'Email' => 'bartek@uek.krakow.pl', 'Full Name' => 'Bartek Zielinski', 'Educational Institutions' => array( $THERE ), 'Status' => 'In Sensei' ),
);

$out = WPCPM_Institution_Create::create_slice( $batch_id );

ck( 'the row whose answer changed is not created', count( $GLOBALS['created'] ), 1 );
ck( 'the other one is', $GLOBALS['created'][0]['Email'], 'anna@uek.krakow.pl' );
ck( 'and the changed row is blocked', row_states( $batch_id ), array( 'created', 'blocked' ) );

$changed = rows_of( $batch_id )[1];
// Named rather than silently dropped: a school reading a refusal on a line the screen called
// ready ten seconds ago is owed the sentence saying the answer moved.
ck( 'and named as having changed', ! empty( $changed['changed'] ), true );
ck( 'with the one refusal every outside hit gets', $changed['verdict'], WPCPM_Institution_Import::BLOCKED );
// The reason is kept for a program manager and never shown: answering per row would turn a
// preview into a lookup service for anybody who can paste addresses.
ck( 'and the reason kept for a program manager', $changed['manager_reason'], 'students row at another institution' );

fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ) );
$GLOBALS['fail'] = 'tblStudents';
$out             = WPCPM_Institution_Create::create_slice( $batch_id );

// Creating on the strength of a check this slice could not make is creating on a preview that
// may be minutes old. Nothing goes out, and the batch waits for the base to come back.
ck( 'a base that cannot be read creates nothing', count( $GLOBALS['created'] ), 0 );
ck( 'and says so', $out['problem'], 'unreadable' );
ck( 'and the batch is still going', state_of( $batch_id ), WPCPM_Institution_Import::STATE_CREATING );
ck( 'and a continuation is scheduled', false !== wp_next_scheduled( WPCPM_Institution_Import::CRON_TICK, array( $batch_id ) ), true );

echo "\n=== A lost response creates one record, not two ===\n";

fresh();
$batch_id = stage(
	$HERE,
	array(
		row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ),
		row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl' ),
		row( 4, 'Cecylia Nowak', 'cecylia@uek.krakow.pl' ),
	)
);

// Row two reaches Airtable and the process dies before the answer is stored.
$GLOBALS['lose'] = array( 'bartek@uek.krakow.pl' );

try {
	WPCPM_Institution_Create::create_slice( $batch_id );
	ck( 'the request was killed part way', false, true );
} catch ( Lost $e ) {
	ck( 'the request was killed part way', $e->getMessage(), 'bartek@uek.krakow.pl' );
}

$GLOBALS['lose'] = array();

ck( 'two records reached the base', count( $GLOBALS['base'] ), 2 );
// **Written before the request and never after.** Saved afterwards, this row would say
// `pending` and the next slice would create Bartek a second time.
ck( 'the row in flight says it was being created', row_states( $batch_id ), array( 'created', 'creating', 'pending' ) );
ck( 'and carries no record ID', rows_of( $batch_id )[1]['record_id'], '' );
// The killed request never got to release it. Production waits out LOCK_TIMEOUT; here the
// wait is asserted and then skipped.
ck( 'and its lock is still held', WPCPM_Institution_Create::create_slice( $batch_id )['problem'], 'locked' );

update_option( 'wpcpm_import_lock_' . $HERE, time() - WPCPM_Institution_Create::LOCK_TIMEOUT - 1 );

$made  = count( $GLOBALS['created'] );
$again = WPCPM_Institution_Create::create_slice( $batch_id );

// One more create for row three, and none for row two: the retry found what row two had
// already made and adopted it instead of making a second Bartek.
ck( 'the retry creates the row that was never sent, and only that one', count( $GLOBALS['created'] ) - $made, 1 );
ck( 'so the base holds three students and not four', count( $GLOBALS['base'] ), 3 );
ck( 'every row is created', row_states( $batch_id ), array( 'created', 'created', 'created' ) );
ck( 'and the batch is done', state_of( $batch_id ), WPCPM_Institution_Import::STATE_DONE );

$recovered = rows_of( $batch_id )[1];
ck( 'the recovered row carries the record it had already made', $recovered['record_id'], $GLOBALS['base'][1]['id'] );

$searched = false;
foreach ( $GLOBALS['queries'] as $formula ) {
	if ( false !== strpos( $formula, "{Site import key} = 'imp-" . $batch_id . "-1'" ) ) { $searched = true; }
}
// The key is the only question with a true answer: email alone cannot tell "created a second
// ago, response lost" from "somebody else enrolled this student".
ck( 'and it was found by its import key', $searched, true );

echo "\n=== An address that matches is not the same as a record of ours ===\n";

fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ) );

// The row is mid-flight, and the address now belongs to a record this batch did not make.
$rows          = rows_of( $batch_id );
$rows[0]['state'] = WPCPM_Institution_Create::ROW_CREATING;
update_post_meta( $batch_id, WPCPM_Institution_Import::META_ROWS, $rows );
update_post_meta( $batch_id, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );

$GLOBALS['base'][] = array(
	'id'     => 'recSOMEONE0000001',
	'fields' => array( 'Email' => 'anna@uek.krakow.pl', 'Full Name' => 'Anna Kowalska', 'Educational Institutions' => array( $THERE ) ),
);

WPCPM_Institution_Create::create_slice( $batch_id );

// Adopting it would stamp somebody else's record onto this school's row, and every fence in
// this module downstream reads that ID as "this school's student".
ck( 'a record with no key of ours is never adopted', WPCPM_Institution_Create::record_of( rows_of( $batch_id )[0] ), '' );
ck( 'the row is blocked instead', row_states( $batch_id ), array( 'blocked' ) );
ck( 'and nothing was created', count( $GLOBALS['created'] ), 0 );

echo "\n=== One refused row does not stop the others ===\n";

fresh();
$batch_id = stage(
	$HERE,
	array(
		row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ),
		row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl' ),
		row( 4, 'Cecylia Nowak', 'cecylia@uek.krakow.pl' ),
	)
);

$GLOBALS['refuse'] = array( 'bartek@uek.krakow.pl' );
$GLOBALS['base_refusal'] = '';
$out               = WPCPM_Institution_Create::create_slice( $batch_id );

ck( 'the rows after the refused one are still created', row_states( $batch_id ), array( 'created', 'failed', 'created' ) );
ck( 'the batch finishes', state_of( $batch_id ), WPCPM_Institution_Import::STATE_DONE );
ck( 'and the summary names all three outcomes', array( $out['created'], $out['blocked'], $out['failed'] ), array( 2, 0, 1 ) );

echo "\n=== A refusal that is not this row's stops the slice ===\n";

/**
 * `create_records()` answers with a WP_Error for two quite different things, and the loop
 * treated them alike: a value Airtable would not accept in this record, and a condition that
 * refuses every call after it too. An expired token or an open rate-limit window would have
 * marked three hundred students terminally failed in one pass and finished the batch, leaving
 * nothing pending for a later slice. A reviewer traced it; these pin the difference.
 */
// A 404 (the table or base is not there) and a 413 refuse the next row exactly as a 5xx does;
// filing them as the row's own fault terminally failed every row of an import whose only
// fault was a setting, where a halt keeps the list recoverable.
foreach ( array( 'unsent' => 'pending', 'base' => 'creating', 'not-found' => 'creating', 'too-large' => 'creating' ) as $kind => $expected ) {
	fresh();
	$batch_id = stage(
		$HERE,
		array(
			row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ),
			row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl' ),
			row( 4, 'Cecylia Nowak', 'cecylia@uek.krakow.pl' ),
		)
	);
	$GLOBALS['base_refusal'] = $kind;
	$before = count( $GLOBALS['base'] );
	$out    = WPCPM_Institution_Create::create_slice( $batch_id );
	$GLOBALS['base_refusal'] = '';

	ck( sprintf( 'a %s refusal does not finish the batch', $kind ), state_of( $batch_id ), WPCPM_Institution_Import::STATE_CREATING );
	// Not one row failed: every one of them is still there to try again.
	ck( sprintf( 'and writes off no row (%s)', $kind ), in_array( 'failed', row_states( $batch_id ), true ), false );
	ck( sprintf( 'nothing was created (%s)', $kind ), count( $GLOBALS['base'] ), $before );
	// A slice the base stopped is a slice to try again: something has to come back for it.
	ck( sprintf( 'and a continuation is scheduled (%s)', $kind ), false !== wp_next_scheduled( WPCPM_Institution_Import::CRON_TICK, array( $batch_id ) ), true );
	// Where nothing was sent the row is safe to put back. Where it may have been sent, it
	// stays `creating`, the one state find_created() can recover a lost record from: calling
	// it failed would strand a student who exists in Airtable and is on nobody's roster.
	ck( sprintf( 'the first row is left %s', $expected ), row_states( $batch_id )[0], $expected );
}

// The lock a slice holds is a heartbeat with a token, released only by its holder. A slice of a
// full batch can outlive the 120-second timeout while still working; before this the next tick
// took the lock and both slices created the same students.
fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ), row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl' ) ) );
$lock     = WPCPM_Institution_Create::LOCK_PREFIX . $HERE;
$seen     = array();
$GLOBALS['on_create'] = function () use ( $lock, &$seen ) { $seen[] = (string) get_option( $lock ); };
WPCPM_Institution_Create::create_slice( $batch_id );
$GLOBALS['on_create'] = null;
ck( 'the lock is written before every row, carrying a time and a token', count( $seen ) === 2 && preg_match( '/^\d+:[A-Za-z0-9]{12}$/', $seen[0] ) === 1, true );
ck( 'the same token for the whole slice', substr( $seen[0], strpos( $seen[0], ':' ) ), substr( $seen[1], strpos( $seen[1], ':' ) ) );
ck( 'and released when the slice ends', get_option( $lock ), false );

// A newcomer that took the lock over mid-slice keeps it: the finishing slice sees a token that
// is not its own and leaves the option alone.
fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ), row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl' ) ) );
$GLOBALS['on_create'] = function ( $n ) use ( $lock ) { if ( 1 === $n ) { update_option( $lock, time() . ':newcomer0000' ); } };
WPCPM_Institution_Create::create_slice( $batch_id );
$GLOBALS['on_create'] = null;
ck( "a lock another slice took over is not released by the one that lost it", (string) get_option( $lock ), time() . ':newcomer0000' );
delete_option( $lock );

// A stale lock is still taken over, which is what keeps a killed request from stranding a batch.
fresh();
$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ) );
update_option( $lock, ( time() - WPCPM_Institution_Create::LOCK_TIMEOUT - 5 ) . ':deadslice000' );
$out = WPCPM_Institution_Create::create_slice( $batch_id );
ck( 'a lock older than the timeout is taken over', $out['problem'], 'created' );
update_option( $lock, time() . ':liveslice000' );
ck( 'while a fresh one is honoured', WPCPM_Institution_Create::create_slice( stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ) ) )['problem'], 'locked' );
delete_option( $lock );

// The reverse, so the two branches are told apart rather than both landing on "stop": a 422
// names this record, and the next row may be perfectly good.
fresh();
$batch_id = stage(
	$HERE,
	array(
		row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ),
		row( 3, 'Bartek Zielinski', 'bartek@uek.krakow.pl' ),
		row( 4, 'Cecylia Nowak', 'cecylia@uek.krakow.pl' ),
	)
);
$GLOBALS['refuse'] = array( 'bartek@uek.krakow.pl' );
WPCPM_Institution_Create::create_slice( $batch_id );
$GLOBALS['refuse'] = array();
ck( 'a refusal naming the record still fails only that row', row_states( $batch_id ), array( 'created', 'failed', 'created' ) );
// Word for word: the base's own account of what it refused is the only clue a program manager
// gets, and a paraphrase would cost them it.
ck( 'the message is the base\'s own, verbatim', rows_of( $batch_id )[1]['error'], 'INVALID_MULTIPLE_CHOICE_OPTIONS: Your field of study' );

// A batch call returns a re-indexed list of the IDs Airtable accepted, so one dropped row
// mis-assigns every ID after it, and the wrong ID stamped is the wrong person fenced.
$sizes = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'create' === $call[0] ) { $sizes[] = $call[1]; } }
ck( 'every create carried exactly one row', array_unique( $sizes ), array( 1 ) );
ck( 'and there was one call per row attempted', count( $sizes ), 3 );

echo "\n=== What a created student joins ===\n";

fresh();
$batch_id = stage(
	$HERE,
	array( row( 2, 'Anna Kowalska', 'Anna@UEK.krakow.pl', array( 'profile' => 'https://profiles.wordpress.org/annak/', 'handle' => 'annak', 'tutor' => 'Dr Nowak' ) ) ),
	array( 'end' => '2027-02-07' )
);

WPCPM_Institution_Create::create_slice( $batch_id );

ck( 'the student is on the roster at once', count( $GLOBALS['inserted'] ), 1 );
ck( 'on this institution\'s roster', $GLOBALS['inserted'][0][0], $HERE );

$indexed = $GLOBALS['inserted'][0][1];
ck( 'carrying the record that was just made', $indexed['record_id'], $GLOBALS['base'][0]['id'] );
ck( 'and the batch\'s program and dates', array( $indexed['status'], $indexed['start'], $indexed['end'] ), array( WPCPM_Program::STATUS_150H, '2026-09-07', '2027-02-07' ) );
// Waiting for a mentor is the state every imported student starts in, and the roster says so
// from this flag rather than from the absence of a value.
ck( 'and no mentor', $indexed['has_mentor'], false );
ck( 'and the import key, so the sync can join to it', $indexed['import_key'], 'imp-' . $batch_id . '-0' );

ck( 'and a request is opened for a mentor', count( $GLOBALS['requests'] ), 1 );
ck( 'of the add kind, about this student', array( $GLOBALS['requests'][0][0], $GLOBALS['requests'][0][2] ), array( 'add', $GLOBALS['base'][0]['id'] ) );
// The ground is re-derived every slice, so the queue says which ground actually carried it.
ck( 'raised on the ground this slice was allowed on', $GLOBALS['requests'][0][4], 'member' );

ck( 'one audit row for the batch', count( $GLOBALS['audit'] ), 1 );
ck( 'saying the import finished', $GLOBALS['audit'][0]['kind'], WPCPM_Institution_Create::LOG_CREATED );
ck( 'about this institution', $GLOBALS['audit'][0]['institution'], $HERE );
// Live rather than cached: every one of these rows was written to the base by this module,
// and the count is of answers Airtable gave.
ck( 'against live evidence', $GLOBALS['audit'][0]['evidence'], WPCPM_Institution_Audit::EVIDENCE_LIVE );

echo "\n=== A finished batch creates nothing again ===\n";

$before = count( $GLOBALS['created'] );
$out    = WPCPM_Institution_Create::create_slice( $batch_id );

// The browser's back button, and it has to be free.
ck( 'a second slice of a done batch creates nothing', count( $GLOBALS['created'] ), $before );
ck( 'and says the state is wrong for it', $out['problem'], 'wrong-state' );
ck( 'and no second audit row is written', count( $GLOBALS['audit'] ), 1 );

echo "\n=== Rows the preview blocked are never sent ===\n";

fresh();
$batch_id = stage(
	$HERE,
	array(
		row( 2, '', 'nobody@uek.krakow.pl', array( 'verdict' => WPCPM_Institution_Import::INVALID, 'problems' => array( 'name_missing' ) ) ),
		row( 3, 'Anna Kowalska', 'anna@uek.krakow.pl', array( 'verdict' => WPCPM_Institution_Import::EXISTS_HERE ) ),
		row( 4, 'Bartek Zielinski', 'bartek@uek.krakow.pl', array( 'verdict' => WPCPM_Institution_Import::DUPLICATE_FILE, 'duplicate_of' => 5 ) ),
		row( 5, 'Cecylia Nowak', 'cecylia@uek.krakow.pl' ),
	)
);

$out = WPCPM_Institution_Create::create_slice( $batch_id );

ck( 'only the creatable row is created', count( $GLOBALS['created'] ), 1 );
ck( 'and the other three are counted as blocked', $out['blocked'], 3 );
ck( 'so the numbers add up to the list that was sent', $out['created'] + $out['blocked'] + $out['failed'], 4 );

echo "\n=== The unattended continuation ===\n";

fresh();
$GLOBALS['hooks'] = array();
WPCPM_Institution_Create::init();

$hooked = array();
foreach ( $GLOBALS['hooks'] as $hook ) { $hooked[] = $hook[0]; }

// Without this the batch is scheduled and nothing ever answers: the event fires, no callback
// is registered for it, and a school's import simply stops with rows left and no error.
ck( 'the tick hook is registered', in_array( WPCPM_Institution_Import::CRON_TICK, $hooked, true ), true );

$batch_id = stage( $HERE, array( row( 2, 'Anna Kowalska', 'anna@uek.krakow.pl' ) ), array(), 42 );
// Cron runs with nobody signed in. Every check the confirm made has to be makeable again
// from here, out of what the batch itself remembers.
$GLOBALS['uid'] = 0;
WPCPM_Institution_Create::handle_tick( $batch_id );

ck( 'the cron path creates the row', count( $GLOBALS['created'] ), 1 );
ck( 'and finishes the batch', state_of( $batch_id ), WPCPM_Institution_Import::STATE_DONE );

$asked = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $asked[] = $call[2]; } }
ck( 'having asked the policy about the account that confirmed', $asked, array( 42 ) );
// Nobody is emailed by an import. The student hears from the program when a mentor is
// assigned, and gets their login when their account is made.
ck( 'and the created student is only waiting for a mentor', $GLOBALS['requests'][0][0], 'add' );

echo "\n=== No call, anywhere in this run, could make an orphan ===\n";

// **A manager import with no institution would otherwise create a silent orphan per row**:
// a Students record linked to nothing, which no roster shows, no fence sees and nobody finds
// again except by reading the whole table. Asserted over every call the whole suite made,
// rather than in the one scenario that was thinking about it.
$orphans = 0;

foreach ( $GLOBALS['all'] as $fields ) {
	$link = isset( $fields['Educational Institutions'] ) ? $fields['Educational Institutions'] : null;

	if ( ! is_array( $link ) || empty( $link ) || '' === trim( (string) $link[0] ) ) {
		++$orphans;
	}
}

ck( 'every create carried an institution', $orphans, 0 );
ck( 'and there were creates to check', count( $GLOBALS['all'] ) > 0, true );

echo "\n=== This module writes to one table ===\n";

$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-create.php' );

/**
 * The module with its comments removed.
 *
 * Scanned instead of the raw text, because the comments name the things the module does not
 * do in order to explain why. Reading the raw source made "nothing here sends mail" fail on
 * the sentence saying no email is sent now, which is the assertion punishing the
 * documentation for being specific.
 */
$code = '';

foreach ( token_get_all( $source ) as $token ) {
	if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		continue;
	}

	$code .= is_array( $token ) ? $token[1] : $token;
}

// The Students table and nothing else. The reports row and the feedback row are made by the
// Airtable automation when a mentor is assigned, and pre-creating either is how a second
// report row appears against one student.
ck( 'nothing is written to the reports table', false !== strpos( $code, 'reports_table' ), false );
ck( 'nothing here updates an existing record', false !== strpos( $code, 'update_records' ), false );
// No email goes out from an import. The student hears from the program when a mentor is
// assigned, and gets their login when their account is made.
ck( 'nothing here sends mail', false !== strpos( $code, 'wp_mail' ), false );
// Who is asking is settled before this file is reached; a superglobal in here would be a
// second answer to that question, in the half that has no business asking it.
foreach ( array( '$_POST', '$_GET', '$_FILES' ) as $forbidden ) {
	ck( sprintf( 'no %s anywhere in it', $forbidden ), false !== strpos( $code, $forbidden ), false );
}

// Never in this project, chat, code or comment alike.
foreach ( array( 'module' => $source, 'suite' => file_get_contents( __FILE__ ) ) as $what => $text ) {
	ck( sprintf( 'no dash but the plain hyphen in the %s', $what ), preg_match( '/[\x{2013}\x{2014}]/u', $text ), 0 );
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
