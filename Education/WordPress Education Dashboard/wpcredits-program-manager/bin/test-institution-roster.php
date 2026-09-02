<?php
/**
 * Which institution the viewer is, what a claim costs, and the four roster groups.
 *
 * Four things are asserted here that nothing else can assert. That `resolve_institution()`
 * reads the switcher argument with `text()` and not `key()`, proved by a record ID with
 * capitals in it that `sanitize_key()` would quietly ruin. That `claim()` spends nothing
 * before it is sure: a 4KB paste, an unknown record type and a record on somebody else's
 * roster each refuse with the stubbed Airtable client untouched, and the client counts
 * every call so a regression shows as a number rather than as a slow page. That the
 * live decision is made on the **Students** row's link and never on the reports row's own,
 * proved by a fixture where the two disagree: the member of the institution the Students
 * table names is allowed, and the member of the one the reports row names is refused.
 *
 * And that a claim carries the roster's columns and not the row. The stand-in answers with
 * `Accessibility needs` and `Notes` whatever field list it was sent - which is the honest
 * behaviour, since Airtable's `fields[]` is a request and not a promise - and the fence has
 * to be what removes them. The disclosure was made to the program, not to the school
 * (design spec 7.5), and `claim()` is the one call in the module that turns a record ID
 * into live Airtable columns.
 *
 * The refusal is compared byte for byte every time, because "not yours", "no such record"
 * and "the base said 404" reading differently is a membership oracle.
 *
 * Each of the three ways a report resolves to a student has its own fixture, because the
 * link branches carry no traffic yet - both sides of the link are empty on all 795 rows -
 * and an untested branch is the one that breaks on the day somebody fills the column in.
 * The counted calls are what tells them apart: the link route reads two records and no
 * lookup, the address route reads one and then filters, and neither of the two guards in
 * front of an unfiltered lookup lets one be sent.
 *
 * Run from the plugin root:  php bin/test-institution-roster.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['users']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['memberships'] = array();
$GLOBALS['settled']     = array();
$GLOBALS['umeta']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['opts']        = array();
$GLOBALS['audit']       = array();
$GLOBALS['air']         = array(
	'calls'    => array(),
	'records'  => array(),
	'students' => array(),
);

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_User {
	public $ID = 0, $roles = array(), $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '', $email = '' ) { $this->ID = $id; $this->display_name = $name; $this->user_email = $email; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = 'private';
	public function __construct( $id = 0, $type = '' ) { $this->ID = $id; $this->post_type = $type; }
}

function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function apply_filters( $t, $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $v ) ) ); }
function sanitize_textarea_field( $v ) { return (string) $v; }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_date( $format ) { return gmdate( $format ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function is_multisite() { return false; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_by( $f, $v ) { return isset( $GLOBALS['users'][ (int) $v ] ) ? $GLOBALS['users'][ (int) $v ] : false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ''; }
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : ''; }
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;
	return WPCPM_Roles::CAP_MANAGE === $c && in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }

/**
 * `get_users()` with the one thing that matters here: the database's collation.
 *
 * MySQL's default collation is case-insensitive, so a meta query for `recABC…` also
 * returns a row holding `recabc…`, and every caller in this plugin has to check the case
 * again in PHP. The stand-in matches case-insensitively on purpose, so a caller that
 * forgot fails here rather than in production.
 *
 * @param array $args Only `meta_key` and `meta_value` are honoured.
 * @return WP_User[]
 */
function get_users( $args = array() ) {
	$key   = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';
	$value = isset( $args['meta_value'] ) ? (string) $args['meta_value'] : '';
	$out   = array();

	foreach ( $GLOBALS['umeta'] as $id => $meta ) {
		if ( ! isset( $meta[ $key ] ) || ! is_scalar( $meta[ $key ] ) ) {
			continue;
		}

		if ( 0 !== strcasecmp( (string) $meta[ $key ], $value ) ) {
			continue;
		}

		if ( isset( $GLOBALS['users'][ (int) $id ] ) ) {
			$out[] = $GLOBALS['users'][ (int) $id ];
		}
	}

	return $out;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

/**
 * `WPCPM_Mentors_Sync::fields()` as the real file declares it, parsed out of the source.
 *
 * The stand-in has to answer `fields()` because the fence takes every column name from
 * there, and a hand-copied map would let this suite go on passing after the real names
 * moved. So the pairs are read out of the real file and the assertion below names the ones
 * this module depends on: rename `Accessibility needs` in the base and in `fields()`, and
 * the check that the fence withholds it goes on testing the column that is really there.
 *
 * @return array<string, string> `fields()` key to Airtable column name.
 */
function mentors_fields_from_source() {
	$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' );

	if ( ! preg_match( '/public static function fields\(\).*?\n\t\}/s', $source, $body ) ) {
		return array();
	}

	// Only `'key' => 'value',` pairs, so the filter name the body opens with - a quoted
	// string with no arrow in front of it - is not read as a column.
	preg_match_all( "/'([a-z_]+)'\s*=>\s*'([^']*)',/", $body[0], $pairs, PREG_SET_ORDER );

	$fields = array();

	foreach ( $pairs as $pair ) {
		$fields[ $pair[1] ] = $pair[2];
	}

	return $fields;
}

$GLOBALS['mentor_fields'] = mentors_fields_from_source();

/*
 * Stand-ins for everything the roster leans on, each to its contract. The policy, the
 * cohort, the program, the request reader, the pipeline index and the roster index itself
 * are loaded for real below: the point of this suite is what those five do together.
 */
if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	/** Stands in for the mentors sync: the record-ID shape, the column names, and the two status lists. */
	class WPCPM_Mentors_Sync {
		const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
		public static function is_record_id( $value ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) ); }
		public static function fields() { return $GLOBALS['mentor_fields']; }
		public static function tracked_statuses( $settings = null ) {
			return array(
				'active' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ),
				'past'   => array( 'Graduate', 'Dropped out' ),
				'all'    => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Graduate', 'Dropped out' ),
			);
		}
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	/** Stands in for the students sync: the three meta keys, and the account for a report record. */
	class WPCPM_Students_Sync {
		const META_RECORD_ID  = 'wpcpm_student_record_id';
		const META_PROGRAM    = 'wpcpm_student_program';
		const META_MENTOR     = 'wpcpm_student_mentor';
		const META_INSTITUTION = 'wpcpm_student_institution';
		public static function user_for_record( $record_id ) {
			$found = get_users( array( 'meta_key' => self::META_RECORD_ID, 'meta_value' => (string) $record_id ) );
			return empty( $found[0] ) ? null : $found[0];
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Stands in for the members module: the live stamp, and the flag the switcher's fallback queries. */
	class WPCPM_Institution_Members {
		const META_ACTIVE = 'wpcpm_institution_active';
		public static function institution_of( $user = null ) {
			$id = is_object( $user ) ? $user->ID : (int) $user;
			$mine = isset( $GLOBALS['memberships'][ $id ] ) ? $GLOBALS['memberships'][ $id ] : array();
			return empty( $mine[0] ) ? '' : (string) $mine[0];
		}
		public static function memberships_of( $user = null ) {
			$record = self::institution_of( $user );
			return '' === $record ? array() : array( $record );
		}
		public static function is_member( $user = null ) { return '' !== self::institution_of( $user ); }
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** Stands in for the agreement module: whether that institution's gate is open. */
	class WPCPM_Institution_Agreement {
		const STAGE_ORDER     = array( 'First Contact Made', 'Confirmed' );
		const TERMINAL_STAGES = array( 'Not Moving Forward', 'SPAM' );
		public static function is_settled( $record_id ) { return in_array( $record_id, $GLOBALS['settled'], true ); }
	}
}
if ( ! class_exists( 'WPCPM_Settings' ) ) {
	/** Stands in for the settings: the two table IDs a claim reads. */
	class WPCPM_Settings {
		public static function get() {
			return array(
				'students_table' => 'tblStudents',
				'reports_table'  => 'tblReports',
			);
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	/** Stands in for the audit log: one row per recorded event, kept for inspection. */
	class WPCPM_Institution_Audit {
		const GROUND_MANAGER = 'manager';
		const GROUND_MEMBER  = 'member';
		const GROUND_SYSTEM  = 'system';
		const EVIDENCE_INDEX = 'index';
		const EVIDENCE_CACHE = 'cache';
		const EVIDENCE_LIVE  = 'live';
		public static function record( array $entry ) {
			$GLOBALS['audit'][] = $entry;
			return count( $GLOBALS['audit'] );
		}
	}
}
if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/**
	 * Stands in for the Airtable client, counting every call it is asked to make.
	 *
	 * `formula_in()` and `link_ids()` reproduce the real ones so the formula this suite
	 * inspects is the formula the base would have been sent; the source check further down
	 * asserts the real file still declares all four members by these names.
	 *
	 * `fields[]` is recorded and then **ignored**: the whole row is answered whatever was
	 * asked for. That is the point of it. Airtable's field list is a request and not a
	 * promise, so a base that answers with a column nobody asked for is exactly what the
	 * fence on the way out is for, and a stand-in that honoured the list would prove nothing
	 * about it.
	 */
	class WPCPM_Airtable {
		public function __construct( $settings = null ) {}
		public function get_record( $table, $record_id ) {
			$GLOBALS['air']['calls'][] = array( 'get_record', $table, (string) $record_id );

			if ( ! isset( $GLOBALS['air']['records'][ (string) $record_id ] ) ) {
				return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 404): NOT_FOUND', array( 'status' => 404 ) );
			}

			$answer = $GLOBALS['air']['records'][ (string) $record_id ];

			if ( $answer instanceof WP_Error ) {
				return $answer;
			}

			return array( 'id' => (string) $record_id, 'fields' => $answer );
		}
		public function fetch_page( $table, array $args = array() ) {
			$formula                   = isset( $args['formula'] ) ? (string) $args['formula'] : '';
			$GLOBALS['air']['calls'][] = array( 'fetch_page', $table, $formula, isset( $args['fields'] ) ? (array) $args['fields'] : array() );

			if ( isset( $GLOBALS['air']['fetch_error'] ) && $GLOBALS['air']['fetch_error'] instanceof WP_Error ) {
				return $GLOBALS['air']['fetch_error'];
			}

			// No filter is no filter: the base answers an empty `filterByFormula` with every
			// row of the table, not with none of them. The stand-in has to be honest about that
			// or the guard against ever sending one could not be shown to be doing anything.
			if ( '' === $formula ) {
				$all = array();

				foreach ( $GLOBALS['air']['students'] as $id => $fields ) {
					$all[] = array( 'id' => (string) $id, 'fields' => $fields );
				}

				return array( 'records' => $all, 'offset' => null );
			}

			// One match that is not a row. Airtable does not answer like this; a proxy, a
			// truncated body or a decode that half worked can, and a claim has to fail closed
			// on it rather than fatally on the type hint behind it.
			if ( ! empty( $GLOBALS['air']['malformed'] ) ) {
				return array( 'records' => array( 'not a row at all' ), 'offset' => null );
			}

			// Only the one formula shape this class ever sends is understood, which is itself
			// an assertion: a claim that built any other formula finds nobody here.
			if ( ! preg_match( "/^LOWER\(\{Email\}\) = '(.*)'$/", $formula, $m ) ) {
				return array( 'records' => array(), 'offset' => null );
			}

			$records = array();

			foreach ( $GLOBALS['air']['students'] as $id => $fields ) {
				$email = isset( $fields['Email'] ) ? strtolower( (string) $fields['Email'] ) : '';

				if ( $email === $m[1] ) {
					$records[] = array( 'id' => (string) $id, 'fields' => $fields );
				}
			}

			return array( 'records' => $records, 'offset' => null );
		}
		public function formula_in( $field, array $values, $lower = false ) {
			// A client that cannot build a formula is not a state the caller can reach on its
			// own - an address it could not filter on was refused an instant earlier - which is
			// why the guard behind it needs a way to be reached at all. Deleted, that guard
			// sends a lookup with no filter, and the answer above is the whole table.
			if ( ! empty( $GLOBALS['air']['no_formula'] ) ) {
				return '';
			}

			$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );

			if ( empty( $values ) ) {
				return '';
			}

			$tests = array();

			foreach ( $values as $value ) {
				$tests[] = $lower
					? sprintf( "LOWER({%s}) = '%s'", $field, strtolower( $value ) )
					: sprintf( "{%s} = '%s'", $field, $value );
			}

			return 1 === count( $tests ) ? $tests[0] : 'OR(' . implode( ',', $tests ) . ')';
		}
		public static function link_ids( $value ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$ids = array();

			foreach ( $value as $item ) {
				if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) {
					$ids[] = $item;
				} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
					$ids[] = (string) $item['id'];
				}
			}

			return $ids;
		}
		public static function flatten( $value, $glue = ', ' ) {
			if ( is_array( $value ) ) {
				return isset( $value['name'] ) ? (string) $value['name'] : implode( $glue, array_map( 'strval', $value ) );
			}

			return is_scalar( $value ) ? (string) $value : '';
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php';

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
 * Sign somebody in and clear both request arrays.
 *
 * Every resolver assertion starts from nothing in the request, so a leftover argument
 * cannot make the next one pass.
 *
 * @param int $user_id Who is looking.
 */
function viewing( $user_id ) {
	$GLOBALS['uid'] = (int) $user_id;
	$_GET           = array();
	$_POST          = array();
}

/**
 * Forget every call the stubbed client was asked to make, and every way it was told to fail.
 *
 * Both failures are cleared here rather than at the end of the block that set them, so a
 * fixture left standing cannot make the next assertion pass for the wrong reason.
 */
function reset_calls() {
	$GLOBALS['air']['calls'] = array();
	unset( $GLOBALS['air']['fetch_error'] );
	unset( $GLOBALS['air']['no_formula'] );
	unset( $GLOBALS['air']['malformed'] );
}

/**
 * What a refused claim looks like, whatever refused it.
 *
 * @param mixed $result What claim() returned.
 * @return string The code and message, or a description of what came back instead.
 */
function refusal_of( $result ) {
	if ( ! $result instanceof WP_Error ) {
		return 'not a WP_Error: ' . gettype( $result );
	}

	return $result->get_error_code() . '|' . $result->get_error_message();
}

/* ---- the fixture ---------------------------------------------------------- */

$A     = 'rec' . str_repeat( 'A', 14 );
$B     = 'rec' . str_repeat( 'B', 14 );
$MIX   = 'recMiXeDcAsE12345';                 // 3 + 14, with capitals sanitize_key() would eat.
$EMPTY = 'rec' . str_repeat( 'E', 14 );       // In the index, first, and nobody acts for it.
$GHOST = 'rec' . str_repeat( 'G', 14 );       // Well-formed, and the index has never heard of it.

$S_CURRENT = 'recS' . str_repeat( '1', 13 );
$S_WAITING = 'recS' . str_repeat( '2', 13 );
$S_PAST    = 'recS' . str_repeat( '3', 13 );
$S_NONE    = 'recS' . str_repeat( '4', 13 );
$S_SPAM    = 'recS' . str_repeat( '5', 13 );
$S_DUPE    = 'recS' . str_repeat( '6', 13 );
$S_ODD     = 'recS' . str_repeat( '7', 13 );
$S_BLANK   = 'recS' . str_repeat( '8', 13 );
$S_H2      = 'recS' . str_repeat( '9', 13 );
$S_B1      = 'recB' . str_repeat( '1', 13 );
$S_STALE   = 'recT' . str_repeat( '1', 13 );
$S_TWICE_1 = 'recW' . str_repeat( '1', 13 );
$S_TWICE_2 = 'recW' . str_repeat( '2', 13 );
$S_LINKED  = 'recL' . str_repeat( '1', 13 );   // Only the `Students` link finds this one.

$R_CURRENT = 'recR' . str_repeat( '1', 13 );
$R_STALE   = 'recR' . str_repeat( '2', 13 );
$R_ORPHAN  = 'recR' . str_repeat( '3', 13 );
$R_TWICE   = 'recR' . str_repeat( '4', 13 );
$R_LINKED  = 'recR' . str_repeat( '5', 13 );   // One `Students` link, and an address naming somebody else.
$R_TWO     = 'recR' . str_repeat( '6', 13 );   // Two, which is a student nobody can name.
$R_JUNK    = 'recR' . str_repeat( '7', 13 );   // A link that is not a record ID.
$R_NOMAIL  = 'recR' . str_repeat( '8', 13 );   // No link and no address: the 3 rows in the base.

$GLOBALS['users'] = array(
	1  => new WP_User( 1, 'Manager', 'manager@example.org' ),
	2  => new WP_User( 2, 'Member of A', 'a@example.org' ),
	3  => new WP_User( 3, 'Member of B', 'b@example.org' ),
	4  => new WP_User( 4, 'Stranger', 'nobody@example.org' ),
	5  => new WP_User( 5, 'Member of the mixed-case institution', 'mix@example.org' ),
	10 => new WP_User( 10, 'Ada Example', 'ada@example.org' ),
	11 => new WP_User( 11, 'Stale Example', 'stale@example.org' ),
	12 => new WP_User( 12, 'Orphan Example', 'orphan@example.org' ),
	13 => new WP_User( 13, 'Twice Example', 'twice@example.org' ),
	14 => new WP_User( 14, 'Reports-only Example', 'reports-only@example.org' ),
	15 => new WP_User( 15, 'Students-side Example', 'students-side@example.org' ),
	16 => new WP_User( 16, 'Another school Example', 'other@example.org' ),
	17 => new WP_User( 17, 'Case-mangled Example', 'mangled@example.org' ),
	18 => new WP_User( 18, 'Linked Example', 'linked@example.org' ),
	19 => new WP_User( 19, 'Two-students Example', 'two@example.org' ),
	20 => new WP_User( 20, 'Junk-link Example', 'junk@example.org' ),
	21 => new WP_User( 21, 'No-address Example', 'no-address@example.org' ),
);
$GLOBALS['manage']      = array( 1 );
$GLOBALS['memberships'] = array(
	2 => array( $A ),
	3 => array( $B ),
	5 => array( $MIX ),
);
$GLOBALS['settled'] = array( $A, $B, $MIX );

// The student accounts: the stamp the sync wrote, and the report record each was read from.
$GLOBALS['umeta'] = array(
	5  => array( WPCPM_Institution_Members::META_ACTIVE => 1 ),
	2  => array( WPCPM_Institution_Members::META_ACTIVE => 1 ),
	3  => array( WPCPM_Institution_Members::META_ACTIVE => 1 ),
	10 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_CURRENT,
	),
	11 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_STALE,
	),
	12 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_ORPHAN,
	),
	13 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_TWICE,
	),
	// Four more accounts of A, one per way the report-to-student join can go, so that each
	// of them gets past the cheap decision and is answered by the live read rather than by
	// step 2. None of them carries a program row, so none reaches the unlinked list below.
	18 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_LINKED,
	),
	19 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_TWO,
	),
	20 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_JUNK,
	),
	21 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $A,
		WPCPM_Students_Sync::META_RECORD_ID   => $R_NOMAIL,
	),
);

/* ---- the pipeline index, written through the real writer ------------------ */

WPCPM_Institutions_Index::write(
	array(
		$EMPTY => array( 'record_id' => $EMPTY, 'name' => 'Nobody University', 'stage' => 'Confirmed' ),
		$A     => array( 'record_id' => $A, 'name' => 'Universidad Example ', 'stage' => 'Confirmed' ),
		$B     => array( 'record_id' => $B, 'name' => 'Beta Institute', 'stage' => 'Confirmed' ),
		$MIX   => array( 'record_id' => $MIX, 'name' => '', 'stage' => 'Confirmed' ),
	),
	1756800000
);

/* ---- the roster index, written through the real writer -------------------- */

/**
 * One roster row, in the shape the students sync hands over.
 *
 * @param string $record_id Students record ID.
 * @param array  $extra     What this row is about: status, start, reports, institution.
 * @return array
 */
function roster_row( $record_id, array $extra = array() ) {
	return array_merge(
		array(
			'record_id'   => $record_id,
			'name'        => 'Student ' . substr( $record_id, 3, 4 ),
			'email'       => strtolower( $record_id ) . '@example.org',
			'status'      => 'In Sensei',
			'institution' => 'rec' . str_repeat( 'A', 14 ),
			'start'       => '2026-02-16',
			'end'         => '2026-06-30',
			'has_mentor'  => true,
			'reports'     => array(),
			'user_id'     => 0,
		),
		$extra
	);
}

$rows_a = array(
	$S_CURRENT => roster_row( $S_CURRENT, array( 'reports' => array( $R_CURRENT ), 'user_id' => 10 ) ),
	$S_WAITING => roster_row( $S_WAITING, array( 'has_mentor' => false ) ),
	$S_PAST    => roster_row( $S_PAST, array( 'status' => 'Graduate', 'reports' => array( 'recP' . str_repeat( '1', 13 ) ) ) ),
	$S_NONE    => roster_row( $S_NONE, array( 'status' => 'Not moving forward' ) ),
	$S_SPAM    => roster_row( $S_SPAM, array( 'status' => 'SPAM' ) ),
	$S_DUPE    => roster_row( $S_DUPE, array( 'status' => 'Duplicated' ) ),
	$S_ODD     => roster_row( $S_ODD, array( 'status' => 'In Sensei Self-onboarding' ) ),
	$S_BLANK   => roster_row( $S_BLANK, array( 'status' => '' ) ),
	$S_H2      => roster_row( $S_H2, array( 'start' => '2026-09-01', 'reports' => array( 'recH' . str_repeat( '2', 13 ) ) ) ),
	$S_STALE   => roster_row( $S_STALE, array( 'reports' => array( $R_STALE ), 'user_id' => 11 ) ),
);
$rows_b = array(
	$S_B1 => roster_row( $S_B1, array( 'institution' => $B ) ),
);

WPCPM_Roster_Index::write_all(
	array( $A => $rows_a, $B => $rows_b ),
	array(),
	array( $A => array(), $B => array() ),
	array(),
	1756800000
);

/* ---- what Airtable would answer ------------------------------------------- */

$GLOBALS['air']['students'] = array(
	// `Accessibility needs` and `Notes` are on the two rows a claim actually resolves to,
	// answered by the stand-in whatever field list it was sent, because the fence is the one
	// on the way out and this is the row it has to cut down.
	$S_CURRENT => array(
		'Email'                    => 'Ada@Example.org',
		'Educational Institutions' => array( $A ),
		'Full Name'                => 'Ada Example',
		'Status'                   => 'In Sensei',
		'Accessibility needs'      => 'A disclosure the school must never see',
		'Notes'                    => 'A note the program keeps about this student',
		// Neither withheld by name nor disclosed: simply not on the list. An allowlist drops
		// it; a denylist of the two names above would let it through.
		'Phone'                    => '+1 555 0100',
	),
	$S_STALE   => array( 'Email' => 'stale@example.org', 'Educational Institutions' => array( $B ) ),
	$S_B1      => array(
		'Email'                    => 'beta@example.org',
		'Educational Institutions' => array( $B ),
		'Full Name'                => 'Beta Example',
		'Accessibility needs'      => 'A disclosure the school must never see',
		'Notes'                    => 'A note the program keeps about this student',
	),
	$S_TWICE_1 => array( 'Email' => 'twice@example.org', 'Educational Institutions' => array( $A ) ),
	$S_TWICE_2 => array( 'Email' => 'Twice@Example.org', 'Educational Institutions' => array( $A ) ),
	$S_LINKED  => array( 'Email' => 'linked@example.org', 'Educational Institutions' => array( $A ), 'Full Name' => 'Linked Example', 'Phone' => '+1 555 0100' ),
);
$GLOBALS['air']['records'] = array(
	// The reports rows. Note the institution each names on its own side: `Educational
	// institution`, lowercase i, and deliberately the wrong school on the first two.
	$R_CURRENT => array( 'Email' => 'ada@example.org', 'Educational institution' => array( $B ) ),
	$R_STALE   => array( 'Email' => 'stale@example.org', 'Educational institution' => array( $A ) ),
	$R_ORPHAN  => array( 'Email' => 'nobody-in-students@example.org', 'Educational institution' => array( $A ) ),
	$R_TWICE   => array( 'Email' => 'twice@example.org', 'Educational institution' => array( $A ) ),
	// The `Students` link, empty on all 795 rows of the base today and filled in here, since
	// the day somebody fills it in is the day it becomes the join every claim takes. Each of
	// these carries `ada@example.org` as well, which names a **different** Students row: the
	// address is the fallback, so a claim that took it when a link was there is visible.
	$R_LINKED  => array( 'Email' => 'ada@example.org', 'Students' => array( $S_LINKED ), 'Educational institution' => array( $A ) ),
	$R_TWO     => array( 'Email' => 'ada@example.org', 'Students' => array( $S_LINKED, $S_CURRENT ), 'Educational institution' => array( $A ) ),
	$R_JUNK    => array( 'Email' => 'ada@example.org', 'Students' => array( 'recNOPE' ), 'Educational institution' => array( $A ) ),
	// Three reports rows in the base have no address at all, and this is one of them.
	$R_NOMAIL  => array( 'Educational institution' => array( $A ) ),
);

foreach ( $GLOBALS['air']['students'] as $id => $fields ) {
	$GLOBALS['air']['records'][ $id ] = $fields;
}

$R = 'WPCPM_Institution_Roster';
$P = 'WPCPM_Institution_Policy';

/* ---- the fixture is the shape the plugin believes in ---------------------- */

echo "=== The stand-ins ===\n";

ck( 'every fixture record ID is well-formed',
	array_map( array( 'WPCPM_Mentors_Sync', 'is_record_id' ), array( $A, $B, $MIX, $EMPTY, $GHOST, $S_CURRENT, $R_CURRENT ) ),
	array( true, true, true, true, true, true, true ) );

$mentors_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' );
preg_match( "/function is_record_id\([^)]*\)\s*\{\s*return \(bool\) preg_match\( '([^']+)'/", $mentors_src, $m );
ck( 'the record-ID pattern is the one the plugin uses', isset( $m[1] ) ? $m[1] : '', WPCPM_Mentors_Sync::RECORD_ID_PATTERN );

$airtable_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php' );
foreach ( array( 'get_record', 'fetch_page', 'formula_in', 'link_ids', 'flatten' ) as $method ) {
	ck( "the real Airtable client still declares $method()", false !== strpos( $airtable_src, 'function ' . $method . '(' ), true );
}

$settings_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php' );
ck( 'and the settings still name both tables',
	array( false !== strpos( $settings_src, "'students_table'" ), false !== strpos( $settings_src, "'reports_table'" ) ),
	array( true, true ) );

$declared = array_intersect_key(
	$GLOBALS['mentor_fields'],
	array_flip( array_merge( WPCPM_Institution_Roster::disclosed_keys(), array( WPCPM_Institution_Roster::KEY_WITHHELD ) ) )
);
ksort( $declared );

ck( 'the mentors sync declares every Students column this fence is built from, spelled its way',
	$declared,
	array(
		'student_access'      => 'Accessibility needs',
		'student_email'       => 'Email',
		'student_end'         => 'End Date',
		'student_import_key'  => 'Site import key',
		'student_institution' => 'Educational Institutions',
		'student_mentor'      => 'Mentor',
		'student_profile'     => 'WP Profile',
		'student_record_name' => 'Full Name',
		'student_start'       => 'Start Date',
		'student_status'      => 'Status',
		'student_study'       => 'Your field of study',
		// The trailing space is the column's real name, and it is here rather than in the
		// fence so that nobody tidying one file quietly changes what the other one reads.
		'student_tutor'       => 'Tutor ',
		'student_tutors'      => 'Tutors official',
	) );

$stub = new WPCPM_Airtable();
$open = $stub->fetch_page( 'tblStudents', array() );
reset_calls();

ck( 'and the stand-in answers a lookup with no filter the way the base does, with every row',
	count( $open['records'] ), count( $GLOBALS['air']['students'] ) );

/* ---- 5.5: which institution the viewer is --------------------------------- */

echo "\n=== resolve_institution() ===\n";

viewing( 2 );
ck( 'a member gets their own institution', WPCPM_Institution_Roster::resolve_institution( 2, false ), $A );

viewing( 2 );
$_GET[ WPCPM_Institution_Roster::ARG_VIEW ] = $B;
ck( 'a member asking for another institution still gets their own',
	WPCPM_Institution_Roster::resolve_institution( 2, false ), $A );
ck( 'even if the caller passed the manager flag by mistake',
	WPCPM_Institution_Roster::resolve_institution( 2, true ), $A );

viewing( 1 );
$_GET[ WPCPM_Institution_Roster::ARG_VIEW ] = $B;
ck( 'a manager with the switcher gets the institution it names',
	WPCPM_Institution_Roster::resolve_institution( 1, true ), $B );
ck( 'and nothing at all without the capability flag',
	WPCPM_Institution_Roster::resolve_institution( 1, false ), '' );

viewing( 1 );
$_POST[ WPCPM_Institution_Roster::ARG_VIEW ] = $B;
ck( 'the switcher is honoured from a posted form too, where a query string does not survive',
	WPCPM_Institution_Roster::resolve_institution( 1, true ), $B );

viewing( 1 );
$_GET[ WPCPM_Institution_Roster::ARG_VIEW ] = $GHOST;
ck( 'a record the index has never read is not accepted, and the fallback stands',
	WPCPM_Institution_Roster::resolve_institution( 1, true ), $A );

viewing( 1 );
$_GET[ WPCPM_Institution_Roster::ARG_VIEW ] = 'not a record id';
ck( 'nor is a value that is not a record ID',
	WPCPM_Institution_Roster::resolve_institution( 1, true ), $A );

viewing( 1 );
ck( 'with no argument a manager lands on the first institution in index order with a live member',
	WPCPM_Institution_Roster::resolve_institution( 1, true ), $A );

viewing( 4 );
ck( 'a stranger gets nothing', WPCPM_Institution_Roster::resolve_institution( 4, false ), '' );
viewing( 0 );
ck( 'and so does nobody', WPCPM_Institution_Roster::resolve_institution( null, false ), '' );

echo "\n--- the switcher argument is read with text(), never key() ---\n";

viewing( 1 );
$_GET[ WPCPM_Institution_Roster::ARG_VIEW ] = $MIX;
ck( 'sanitize_key() really would ruin this record ID', sanitize_key( $MIX ) === $MIX, false );
ck( 'text() hands the record ID back exactly as it arrived', WPCPM_Request::text( WPCPM_Institution_Roster::ARG_VIEW ), $MIX );
ck( 'key() would have lowercased it', WPCPM_Request::key( WPCPM_Institution_Roster::ARG_VIEW ), strtolower( $MIX ) );
ck( 'so the manager reaches the mixed-case institution', WPCPM_Institution_Roster::resolve_institution( 1, true ), $MIX );

viewing( 1 );
$_GET[ WPCPM_Institution_Roster::ARG_VIEW ] = strtolower( $MIX );
ck( 'and the lowercased spelling names nothing the index holds',
	WPCPM_Institution_Roster::resolve_institution( 1, true ), $A );

echo "\n--- the switcher's own list ---\n";

ck( 'every index row, in index order, one entry each',
	WPCPM_Institution_Roster::switcher_options(),
	array(
		$EMPTY => 'Nobody University',
		$A     => 'Universidad Example',
		$B     => 'Beta Institute',
		// The two records in the base with no Name fall back to the ID, which is what the
		// grid shows beside the row; an entry with an empty label is one nobody can pick.
		$MIX   => $MIX,
	) );

/* ---- 5.3: claim() --------------------------------------------------------- */

echo "\n=== claim(): step 1, the shape, before anything reaches the network ===\n";

viewing( 2 );
reset_calls();
$paste = str_repeat( 'rec0123456789AB ', 256 );   // 4KB of well-formed-looking rubbish.
ck( 'a 4KB paste is 4096 bytes', strlen( $paste ), 4096 );
ck( 'and is refused', refusal_of( WPCPM_Institution_Roster::claim( $paste, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'with the client untouched', count( $GLOBALS['air']['calls'] ), 0 );

reset_calls();
ck( 'a record type this class cannot resolve is refused as well',
	refusal_of( WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'agreement', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'with the client untouched', count( $GLOBALS['air']['calls'] ), 0 );

echo "\n=== claim(): step 2, the cheap decision ===\n";

reset_calls();
ck( "a member reaching for another institution's student is refused",
	refusal_of( WPCPM_Institution_Roster::claim( $S_B1, $P::ACT_EDIT_STUDENT, 'student', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'before any request', count( $GLOBALS['air']['calls'] ), 0 );
ck( 'and a cheap refusal is not logged', count( $GLOBALS['audit'] ), 0 );

reset_calls();
ck( "a member reaching for another institution's report is refused",
	refusal_of( WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 3 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'before any request', count( $GLOBALS['air']['calls'] ), 0 );

reset_calls();
ck( 'a record nothing on this site has heard of is refused for a member',
	refusal_of( WPCPM_Institution_Roster::claim( $GHOST, $P::ACT_VIEW_STUDENT, 'student', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'before any request', count( $GLOBALS['air']['calls'] ), 0 );

ck( 'nothing cached is an institution-less subject',
	WPCPM_Institution_Roster::cached_subject( $GHOST, 'student' ),
	array( 'type' => 'student', 'id' => $GHOST, 'institution_ids' => array(), 'evidence' => 'cache' ) );
ck( "a Students record is placed by the row's own institution",
	WPCPM_Institution_Roster::cached_subject( $S_B1, 'student' ),
	array( 'type' => 'student', 'id' => $S_B1, 'institution_ids' => array( $B ), 'evidence' => 'cache' ) );
ck( "a report record with an account is placed by the account's stamp",
	WPCPM_Institution_Roster::cached_subject( $R_CURRENT, 'report' ),
	array( 'type' => 'student', 'id' => 10, 'institution_ids' => array( $A ), 'evidence' => 'cache' ) );

echo "\n=== claim(): steps 3 and 4, the live read and the authoritative decision ===\n";

reset_calls();
$claim = WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 2 );
ck( 'a member claiming their own student is allowed', is_array( $claim ), true );
ck( 'on the member ground, naming their institution',
	is_array( $claim ) ? $claim['decision'] : null,
	array( 'allowed' => true, 'ground' => 'member', 'institution' => $A, 'fields' => null, 'why' => '' ) );
ck( 'and hands back the Students row, not the report',
	is_array( $claim ) ? $claim['record']['id'] : null, $S_CURRENT );
ck( 'read as two requests: the report, then the Students row behind it',
	array_map( static function ( $call ) { return $call[0] . ' ' . $call[1]; }, $GLOBALS['air']['calls'] ),
	array( 'get_record tblReports', 'fetch_page tblStudents' ) );
ck( 'the address was compared case-insensitively, on the field side by Airtable and on ours in PHP',
	$GLOBALS['air']['calls'][1][2], "LOWER({Email}) = 'ada@example.org'" );

echo "\n--- decision 19: the Students side wins, and the reports side is never consulted ---\n";

ck( "the report row names B on its own side", $GLOBALS['air']['records'][ $R_CURRENT ]['Educational institution'], array( $B ) );
ck( 'and the member of B is still refused',
	refusal_of( WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 3 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );

reset_calls();
$GLOBALS['audit'] = array();
$stale            = WPCPM_Institution_Roster::claim( $R_STALE, $P::ACT_VIEW_REPORT, 'report', 2 );
ck( 'a stamp that says A against a Students row that says B is refused',
	refusal_of( $stale ), $P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'with the identical message the cheap refusals give',
	refusal_of( $stale ), refusal_of( WPCPM_Institution_Roster::claim( $S_B1, $P::ACT_EDIT_STUDENT, 'student', 2 ) ) );
ck( 'and the one refusal worth logging is logged once', count( $GLOBALS['audit'] ), 1 );
ck( "filed under the actor's own institution, on the member ground, against live evidence",
	array(
		$GLOBALS['audit'][0]['kind'],
		$GLOBALS['audit'][0]['institution'],
		$GLOBALS['audit'][0]['subject'],
		$GLOBALS['audit'][0]['actor'],
		$GLOBALS['audit'][0]['ground'],
		$GLOBALS['audit'][0]['evidence'],
	),
	array( WPCPM_Institution_Roster::LOG_REFUSED, $A, $R_STALE, 2, 'member', 'live' ) );

echo "\n--- zero matches and several matches both fail closed ---\n";

reset_calls();
ck( 'a report whose address the Students table does not hold is refused',
	refusal_of( WPCPM_Institution_Roster::claim( $R_ORPHAN, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'after the read that proved it', count( $GLOBALS['air']['calls'] ), 2 );

reset_calls();
ck( 'and one whose address is on two Students rows is refused as well',
	refusal_of( WPCPM_Institution_Roster::claim( $R_TWICE, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'both rows were seen: this is not a lookup that stopped at the first',
	count( $GLOBALS['air']['calls'] ), 2 );

echo "\n--- the join spec 5.3 names first: the report's own `Students` link ---\n";

reset_calls();
$linked = WPCPM_Institution_Roster::claim( $R_LINKED, $P::ACT_VIEW_REPORT, 'report', 2 );

ck( "the report's address and its link name two different students",
	array( $GLOBALS['air']['records'][ $R_LINKED ]['Email'], $GLOBALS['air']['students'][ $S_LINKED ]['Email'] ),
	array( 'ada@example.org', 'linked@example.org' ) );
ck( 'and the link is the one that is followed', is_array( $linked ) ? $linked['record']['id'] : null, $S_LINKED );
ck( 'read as two record reads and no lookup, because a link needs none',
	array_map( static function ( $call ) { return $call[0] . ' ' . $call[1]; }, $GLOBALS['air']['calls'] ),
	array( 'get_record tblReports', 'get_record tblStudents' ) );

reset_calls();
ck( 'a report naming two students is a student nobody can name, so it is refused',
	refusal_of( WPCPM_Institution_Roster::claim( $R_TWO, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'and the address is not quietly tried instead: the report read is the only one',
	count( $GLOBALS['air']['calls'] ), 1 );

reset_calls();
$junk = WPCPM_Institution_Roster::claim( $R_JUNK, $P::ACT_VIEW_REPORT, 'report', 2 );

ck( 'a link that is not a record ID is no link at all, and the address is used',
	is_array( $junk ) ? $junk['record']['id'] : null, $S_CURRENT );
ck( 'through the lookup, since there was nothing well-formed to read directly',
	array_map( static function ( $call ) { return $call[0] . ' ' . $call[1]; }, $GLOBALS['air']['calls'] ),
	array( 'get_record tblReports', 'fetch_page tblStudents' ) );

echo "\n--- and the ways the address route ends without a student ---\n";

reset_calls();
ck( 'a report with no link and no address is refused',
	refusal_of( WPCPM_Institution_Roster::claim( $R_NOMAIL, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
// Two guards stand between an empty address and a lookup - the address test here and the
// empty-formula test inside `students_row_for_email()` - and either alone gives this same
// refusal, so this pair of checks is what holds them together: remove both and the call
// count is 2, and the second call is the whole Students table.
ck( 'without a lookup, which with no address to filter on is every student in the base',
	array( count( $GLOBALS['air']['calls'] ), $GLOBALS['air']['calls'][0][0] ), array( 1, 'get_record' ) );

reset_calls();
$GLOBALS['air']['no_formula'] = true;
ck( 'a lookup the client could build no filter for is refused rather than sent',
	refusal_of( WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );
ck( 'and again the report read is the only one',
	count( $GLOBALS['air']['calls'] ), 1 );

reset_calls();
$GLOBALS['air']['malformed'] = true;
ck( 'a single match that is not a row is not a student either, and does not fatal on the way',
	refusal_of( WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );

reset_calls();
$GLOBALS['air']['fetch_error'] = new WP_Error( 'wpcpm_airtable_rate_limited', 'Airtable is rate limiting this site. Try again in 30 seconds.', array( 'seconds' => 30 ) );
ck( 'a lookup that failed is handed back as it came, not turned into "not on your roster"',
	refusal_of( WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	'wpcpm_airtable_rate_limited|Airtable is rate limiting this site. Try again in 30 seconds.' );

reset_calls();
$was_report                              = $GLOBALS['air']['records'][ $R_CURRENT ];
$GLOBALS['air']['records'][ $R_CURRENT ] = new WP_Error( 'wpcpm_airtable_rate_limited', 'Airtable is rate limiting this site. Try again in 30 seconds.', array( 'seconds' => 30 ) );
ck( 'and so is a reports read that failed, before the join is even reached',
	refusal_of( WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 2 ) ),
	'wpcpm_airtable_rate_limited|Airtable is rate limiting this site. Try again in 30 seconds.' );
ck( 'with nothing read behind it', count( $GLOBALS['air']['calls'] ), 1 );
$GLOBALS['air']['records'][ $R_CURRENT ] = $was_report;

echo "\n--- a manager, and the two ways a read can fail ---\n";

reset_calls();
$managed = WPCPM_Institution_Roster::claim( $S_B1, $P::ACT_EDIT_STUDENT, 'student', 1 );
ck( 'a manager claims any student the base holds', is_array( $managed ), true );
ck( 'on the manager ground, naming the institution the base gave',
	is_array( $managed ) ? $managed['decision']['ground'] . '|' . $managed['decision']['institution'] : null,
	'manager|' . $B );

reset_calls();
ck( 'a record Airtable does not have reads exactly like one that is not yours',
	refusal_of( WPCPM_Institution_Roster::claim( $GHOST, $P::ACT_EDIT_STUDENT, 'student', 1 ) ),
	$P::REFUSAL_CODE . '|That record is not on your roster.' );

reset_calls();
$GLOBALS['air']['records'][ $S_CURRENT ] = new WP_Error( 'wpcpm_airtable_rate_limited', 'Airtable is rate limiting this site. Try again in 30 seconds.', array( 'seconds' => 30 ) );
$limited                                 = WPCPM_Institution_Roster::claim( $S_CURRENT, $P::ACT_EDIT_STUDENT, 'student', 2 );
ck( 'a read that failed for any other reason is handed back as it came, never swallowed',
	refusal_of( $limited ),
	'wpcpm_airtable_rate_limited|Airtable is rate limiting this site. Try again in 30 seconds.' );
$GLOBALS['air']['records'][ $S_CURRENT ] = $GLOBALS['air']['students'][ $S_CURRENT ];

reset_calls();
$direct = WPCPM_Institution_Roster::claim( $S_CURRENT, $P::ACT_EDIT_STUDENT, 'student', 2 );
ck( 'a Students record is claimed with one request and no join',
	array( is_array( $direct ), count( $GLOBALS['air']['calls'] ) ), array( true, 1 ) );

echo "\n--- the fence: a claim carries the roster's columns and not the row ---\n";

ck( 'the list is the Students columns the index already publishes, taken from fields()',
	WPCPM_Institution_Roster::disclosed_fields(),
	array( 'Full Name', 'Email', 'Status', 'Educational Institutions', 'Start Date', 'End Date', 'Mentor', 'WP Profile', 'Tutor ', 'Tutors official', 'Your field of study', 'Site import key' ) );
ck( 'the disclosure is not on it, and neither is a column fields() has never named',
	array(
		in_array( 'Accessibility needs', WPCPM_Institution_Roster::disclosed_fields(), true ),
		in_array( 'Notes', WPCPM_Institution_Roster::disclosed_fields(), true ),
	),
	array( false, false ) );

// `fields()` runs through `wpcpm_mentors_fields`, so the map the fence reads is not beyond
// a site's reach. Pointing a disclosed key at the withheld column is the shape that would
// hand the disclosure over under a name the allowlist does say - which is why the column is
// subtracted by its name and not merely left off the key list.
$was_fields                                = $GLOBALS['mentor_fields'];
$GLOBALS['mentor_fields']['student_study'] = $GLOBALS['mentor_fields'][ WPCPM_Institution_Roster::KEY_WITHHELD ];

ck( 'a filter pointing a disclosed key at the disclosure does not smuggle it through',
	in_array( 'Accessibility needs', WPCPM_Institution_Roster::disclosed_fields(), true ), false );

$GLOBALS['mentor_fields'] = $was_fields;

ck( 'and the list is itself again once the filter is gone',
	count( WPCPM_Institution_Roster::disclosed_fields() ), count( WPCPM_Institution_Roster::disclosed_keys() ) );

ck( 'and the base answers with both of them anyway, whatever it was asked for',
	array_keys( $GLOBALS['air']['students'][ $S_CURRENT ] ),
	array( 'Email', 'Educational Institutions', 'Full Name', 'Status', 'Accessibility needs', 'Notes', 'Phone' ) );

reset_calls();
$claimed = WPCPM_Institution_Roster::claim( $R_CURRENT, $P::ACT_VIEW_REPORT, 'report', 2 );

ck( 'a claim through the lookup hands over neither of them',
	array_keys( $claimed['record']['fields'] ),
	array( 'Email', 'Educational Institutions', 'Full Name', 'Status' ) );
ck( 'nor their values, anywhere in what comes back',
	array(
		false !== strpos( wp_json_encode_test( $claimed ), 'must never see' ),
		false !== strpos( wp_json_encode_test( $claimed ), 'a note the program keeps' ),
	),
	array( false, false ) );
ck( 'nor a column that is merely not on the list, which is what makes it an allowlist',
	array(
		array_key_exists( 'Phone', $claimed['record']['fields'] ),
		false !== strpos( wp_json_encode_test( $claimed ), '555 0100' ),
	),
	array( false, false ) );
ck( 'and the lookup asked the base for the disclosed columns rather than for the row',
	$GLOBALS['air']['calls'][1][3], WPCPM_Institution_Roster::disclosed_fields() );

reset_calls();
$claimed = WPCPM_Institution_Roster::claim( $S_CURRENT, $P::ACT_EDIT_STUDENT, 'student', 2 );

ck( 'a claim by record ID hands over neither of them either, and it could ask for nothing',
	array( array_keys( $claimed['record']['fields'] ), count( $GLOBALS['air']['calls'][0] ) ),
	array( array( 'Email', 'Educational Institutions', 'Full Name', 'Status' ), 3 ) );

reset_calls();
$claimed = WPCPM_Institution_Roster::claim( $S_B1, $P::ACT_EDIT_STUDENT, 'student', 1 );

ck( "a program manager's claim is cut down to the same columns",
	array_keys( $claimed['record']['fields'] ),
	array( 'Email', 'Educational Institutions', 'Full Name' ) );
ck( 'because the fence is the module\'s, not the ground\'s: there is nothing here to get wrong',
	false !== strpos( wp_json_encode_test( $claimed ), 'must never see' ), false );

ck( 'the row still carries what the decision is made on',
	$claimed['record']['fields'][ WPCPM_Institution_Roster::FIELD_INSTITUTIONS ], array( $B ) );
ck( 'and its ID, as a string, whatever shape the answer had',
	array( $claimed['record']['id'], gettype( $claimed['record']['id'] ) ), array( $S_B1, 'string' ) );

echo "\n--- owns() is the cache-level decision and nothing more ---\n";

reset_calls();
ck( 'it answers exactly what the policy answers',
	WPCPM_Institution_Roster::owns( $P::subject_institution( $A ), $P::ACT_VIEW_ROSTER, 2 ),
	$P::decide( $P::ACT_VIEW_ROSTER, $P::subject_institution( $A ), 2 ) );
ck( 'and asks Airtable nothing', count( $GLOBALS['air']['calls'] ), 0 );

/* ---- 7.5: the four groups ------------------------------------------------- */

echo "\n=== groups() ===\n";

$groups = WPCPM_Roster_Index::groups( $A );

ck( 'the four groups are always present, in this order', array_keys( $groups ),
	array( 'current', 'waiting', 'finished', 'not_started' ) );
ck( 'current: a tracked status with a report record',
	array_keys( $groups['current'] ), array( $S_CURRENT, $S_H2, $S_STALE ) );
ck( 'waiting: a tracked status with none', array_keys( $groups['waiting'] ), array( $S_WAITING ) );
ck( 'finished: a past status', array_keys( $groups['finished'] ), array( $S_PAST ) );
ck( 'did not start: Not moving forward, no status at all, and a status the settings do not list',
	array_keys( $groups['not_started'] ), array( $S_NONE, $S_ODD, $S_BLANK ) );

$placed = array_merge(
	array_keys( $groups['current'] ),
	array_keys( $groups['waiting'] ),
	array_keys( $groups['finished'] ),
	array_keys( $groups['not_started'] )
);
ck( 'every row is in exactly one group', count( $placed ), count( array_unique( $placed ) ) );
ck( 'and every row of the roster is placed, but for the two that are never shown',
	count( $placed ), count( $rows_a ) - 2 );
ck( 'SPAM is not one of them', in_array( $S_SPAM, $placed, true ), false );
ck( 'nor is Duplicated', in_array( $S_DUPE, $placed, true ), false );

$b_groups = WPCPM_Roster_Index::groups( $B );
ck( "another institution's roster is another institution's",
	array_keys( $b_groups['waiting'] ), array( $S_B1 ) );

ck( 'a record ID nothing holds gives four empty groups',
	WPCPM_Roster_Index::groups( $GHOST ),
	array( 'current' => array(), 'waiting' => array(), 'finished' => array(), 'not_started' => array() ) );
ck( 'and so does a value that is not one',
	WPCPM_Roster_Index::groups( 'nonsense' ),
	array( 'current' => array(), 'waiting' => array(), 'finished' => array(), 'not_started' => array() ) );

echo "\n--- the cohort narrows first, before anything is placed ---\n";

$h1 = WPCPM_Roster_Index::groups( $A, '2026-H1' );
ck( 'January to June 2026 keeps the rows that started in it',
	array_keys( $h1['current'] ), array( $S_CURRENT, $S_STALE ) );
ck( 'and drops the one that started in July', in_array( $S_H2, array_keys( $h1['current'] ), true ), false );

$h2 = WPCPM_Roster_Index::groups( $A, '2026-H2' );
ck( 'July to December 2026 holds only that one', array_keys( $h2['current'] ), array( $S_H2 ) );
ck( 'and nothing else at all',
	array( count( $h2['waiting'] ), count( $h2['finished'] ), count( $h2['not_started'] ) ), array( 0, 0, 0 ) );

$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $A ) ]['rows'][ $S_WAITING ]['start'] = '';
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $A ) ]['rows'][ $S_SPAM ]['start']    = '';
$none = WPCPM_Roster_Index::groups( $A, WPCPM_Cohort::NONE );
ck( 'the no-start-date bucket is a cohort like any other', array_keys( $none['waiting'] ), array( $S_WAITING ) );
ck( 'and SPAM is dropped there too, before the filter can count it',
	in_array( $S_SPAM, array_keys( $none['not_started'] ), true ), false );

$all = WPCPM_Roster_Index::groups( $A, 'not a cohort key' );
ck( 'a cohort argument that is not a key shows every row', count( $all['current'] ), 3 );

/* ---- 7.5: the fifth list -------------------------------------------------- */

echo "\n=== unlinked_for() ===\n";

$GLOBALS['users'][14] = new WP_User( 14, 'Reports-only Example', 'reports-only@example.org' );
$GLOBALS['umeta'][14] = array(
	WPCPM_Students_Sync::META_INSTITUTION => $A,
	WPCPM_Students_Sync::META_MENTOR      => array( 'name' => 'A Mentor' ),
	WPCPM_Students_Sync::META_PROGRAM     => array(
		'record_id'          => $R_ORPHAN,
		'name'               => 'Reports-only Example',
		'email'              => 'reports-only@example.org',
		'program'            => 'In Sensei',
		'start'              => '2026-03-01',
		'end'                => '2026-07-01',
		'username'           => 'reportsonly',
		'field_of_study'     => 'Technology & Engineering',
		'tutor'              => 'A Tutor',
		// The disclosure that may never reach an institution, handed in on purpose.
		'accessibility'      => 'A disclosure the school must never see',
		'institution_source' => 'reports',
	),
);
$GLOBALS['umeta'][15] = array(
	WPCPM_Students_Sync::META_INSTITUTION => $A,
	WPCPM_Students_Sync::META_PROGRAM     => array( 'name' => 'Students-side Example', 'institution_source' => 'students' ),
);
$GLOBALS['umeta'][16] = array(
	WPCPM_Students_Sync::META_INSTITUTION => $B,
	WPCPM_Students_Sync::META_PROGRAM     => array( 'name' => 'Another school Example', 'institution_source' => 'reports' ),
);
$GLOBALS['umeta'][17] = array(
	// The same letters, the wrong case: the database would return this row and Airtable
	// would call it another institution.
	WPCPM_Students_Sync::META_INSTITUTION => strtolower( $A ),
	WPCPM_Students_Sync::META_PROGRAM     => array( 'name' => 'Case-mangled Example', 'institution_source' => 'reports' ),
);

$unlinked = WPCPM_Roster_Index::unlinked_for( $A );

ck( 'only the accounts the Students table has no row for, keyed by user', array_keys( $unlinked ), array( 14 ) );
ck( 'the row carries exactly the index keys', array_keys( $unlinked[14] ), WPCPM_Roster_Index::KEYS );
ck( 'the accessibility disclosure is not among them',
	in_array( 'accessibility', array_keys( $unlinked[14] ), true ), false );
ck( 'and its value is nowhere in the row',
	false === strpos( wp_json_encode_test( $unlinked[14] ), 'must never see' ), true );
ck( 'no Students record, because that is what these students do not have', $unlinked[14]['record_id'], '' );
ck( 'the reports record is kept where the index keeps report records',
	$unlinked[14]['reports'], array( $R_ORPHAN ) );
ck( 'with the facts the roster prints',
	array( $unlinked[14]['name'], $unlinked[14]['status'], $unlinked[14]['start'], $unlinked[14]['tutor'], $unlinked[14]['has_mentor'], $unlinked[14]['user_id'] ),
	array( 'Reports-only Example', 'In Sensei', '2026-03-01', 'A Tutor', true, 14 ) );
ck( 'the email key is lowercased for the join', $unlinked[14]['email_key'], 'reports-only@example.org' );
ck( 'the institution stamped on the row is the one asked for', $unlinked[14]['institution'], $A );

ck( 'a stamp naming another institution is on that institution\'s list and not on this one',
	array_keys( WPCPM_Roster_Index::unlinked_for( $B ) ), array( 16 ) );
ck( 'the same letters in the wrong case are a different institution, whatever the collation returned',
	array( in_array( 17, array_keys( $unlinked ), true ), array_keys( WPCPM_Roster_Index::unlinked_for( strtolower( $A ) ) ) ),
	array( false, array( 17 ) ) );
ck( 'a malformed record ID reads nothing at all', WPCPM_Roster_Index::unlinked_for( 'nonsense' ), array() );

/**
 * `wp_json_encode()` by another name, so the assertion above does not depend on a stub.
 *
 * @param mixed $value Anything.
 * @return string
 */
function wp_json_encode_test( $value ) {
	return (string) json_encode( $value );
}

/* ---- what a grep can prove about these two files -------------------------- */

echo "\n=== The source ===\n";

$mine = array(
	'includes/modules/class-wpcpm-institution-roster.php',
	'includes/class-wpcpm-roster-index.php',
	'bin/test-institution-roster.php',
);

$dashes      = array();
$comparisons = array();

foreach ( $mine as $rel ) {
	$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel );

	if ( preg_match( '/\x{2013}|\x{2014}/u', $source ) ) {
		$dashes[] = $rel;
	}

	// The regex bin/test-institution-policy.php runs over includes/modules/. The roster
	// index lives one directory up, where that scan does not reach, and it is the file that
	// decides which students a school is shown - so the same grep is run over it here.
	if ( preg_match( '/wpcpm_(student_)?institution(_record_id)?[^;]*===/', $source ) ) {
		$comparisons[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in these three files', $dashes, array() );
ck( 'and no institution ID compared with === in any of them', $comparisons, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
