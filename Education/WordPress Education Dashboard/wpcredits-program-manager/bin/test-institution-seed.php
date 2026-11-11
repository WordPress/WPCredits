<?php
/**
 * The one-off seed: areas, booking links, assignments and tutors (Institutions off Airtable, R1, task 8).
 *
 * `wp wpcredits institutions_seed [--dry-run]` fills, once, what release 1 reads from the site instead of
 * Airtable. An Administrator runs it on the live site after the deploy, first with --dry-run, so its summary
 * must be readable and its dry run must write nothing. What is pinned here and why:
 *
 * - **`plan()` says what would be written and writes nothing**: each country's contact becomes the country's
 *   Administrator when its address is an Administrator's account; a booking link is planned only while the
 *   account has none; an institution is assigned by its country's area, else by its Original program
 *   manager; a tutor row is listed under each school it links.
 * - **The Original program manager is a link to Team Members**: the linked row's Email resolves to an
 *   account, else its Name is compared, trimmed and case-insensitive, with an Administrator's display
 *   name, else nothing.
 * - **`apply()` never replaces**: the areas are written only while none are set, and a booking link, an
 *   assignee and a listed tutor already there are left alone. It sends no email, and its rows in the
 *   school's history are the site's own (actor 0, ground `system`).
 * - **The summary is what the Administrator reads**: short plain sentences, one fact each, pinned below.
 * - **Nothing here reads Airtable but through the client it is handed**, and the stand-in client records
 *   every request, so a read of another table or field fails here.
 *
 * Run from the plugin root:  php bin/test-institution-seed.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['settings'] = array(
	'institutions_table'      => 'tblInstitutions',
	'tutors_table'            => 'tblTutors',
	'institution_areas'       => array( 'countries' => array(), 'regions' => array() ),
	'institution_regions'     => array(),
);
$GLOBALS['managers']  = array( 11, 12 );
$GLOBALS['accounts']  = array(
	11 => array( 'Ana Admin', 'ana@example.test' ),
	12 => array( 'Bo Boss', 'bo@example.test' ),
	13 => array( 'Cy Client', 'cy@example.test' ),
);
$GLOBALS['umeta']     = array();
$GLOBALS['countries'] = array();
$GLOBALS['records']   = array();
$GLOBALS['audit']     = array();
$GLOBALS['sent']      = array();
$GLOBALS['requests']  = array();
$GLOBALS['tables']    = array();
$GLOBALS['patched']   = 0;
$GLOBALS['refuse']    = false;
$GLOBALS['read_at']   = 1789000000;
$GLOBALS['busy']      = array();
$GLOBALS['unindexed'] = array();

function __( $s, $d = null ) { return $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function user_can( $id, $cap ) { $id = is_object( $id ) ? $id->ID : (int) $id; return 'wpcpm_manage_program' === $cap && in_array( $id, $GLOBALS['managers'], true ); }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . rawurlencode( $v ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_option( $k, $d = false ) { return 'date_format' === $k ? 'F j, Y' : $d; }
function wp_date( $f, $t = null ) { return gmdate( $f, $t ); }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

class WP_Error {
	public $code, $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID, $display_name, $user_email, $user_login;
	public function __construct( $id ) { $this->ID = $id; $this->display_name = $GLOBALS['accounts'][ $id ][0]; $this->user_email = $GLOBALS['accounts'][ $id ][1]; $this->user_login = 'u' . $id; }
	public function exists() { return true; }
}
function get_userdata( $id ) { return isset( $GLOBALS['accounts'][ (int) $id ] ) ? new WP_User( (int) $id ) : false; }
function get_user_by( $field, $value ) {
	foreach ( array_keys( $GLOBALS['accounts'] ) as $id ) {
		if ( 'email' === $field && 0 === strcasecmp( $GLOBALS['accounts'][ $id ][1], (string) $value ) ) { return new WP_User( $id ); }
		if ( 'id' === $field && $id === (int) $value ) { return new WP_User( $id ); }
	}
	return false;
}

class WPCPM_Roles { const CAP_MANAGE = 'wpcpm_manage_program'; }
class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
	/** The one setting the seed writes, through the real cleaner the Settings screen uses. */
	public static function patch( array $changes, $dry = false ) {
		++$GLOBALS['patched'];
		if ( $GLOBALS['refuse'] ) { return $GLOBALS['settings']; }
		foreach ( $changes as $key => $value ) {
			$GLOBALS['settings'][ $key ] = 'institution_areas' === $key ? WPCPM_Institution_Areas::clean( $value, $GLOBALS['settings'][ $key ] ) : $value;
		}
		return $GLOBALS['settings'];
	}
}
class WPCPM_Settings_Choices {
	public static function manager_accounts() { $o = array(); foreach ( $GLOBALS['managers'] as $id ) { $o[ $id ] = $GLOBALS['accounts'][ $id ][0]; } asort( $o ); return $o; }
}
class WPCPM_Countries {
	public static function all() { return $GLOBALS['countries']; }
	public static function read() { return array( 'v' => 1, 'read' => $GLOBALS['read_at'], 'rows' => $GLOBALS['countries'] ); }
	public static function name_of( $id ) { return isset( $GLOBALS['countries'][ $id ] ) ? $GLOBALS['countries'][ $id ]['name'] : ''; }
	public static function routing( $id ) {
		$row = $GLOBALS['countries'][ $id ] ?? null;
		return ( null !== $row && ( '' !== $row['manager'] || '' !== $row['email'] || '' !== $row['calendly'] ) ) ? $row : null;
	}
}
class WPCPM_Manager_Booking {
	public static function url_for( $id ) { return (string) ( $GLOBALS['umeta'][ (int) $id ]['wpcpm_booking_url'] ?? '' ); }
	public static function set( $id, $url ) {
		if ( ! isset( $GLOBALS['accounts'][ (int) $id ] ) || 0 !== strpos( (string) $url, 'https://' ) ) { return false; }
		$GLOBALS['umeta'][ (int) $id ]['wpcpm_booking_url'] = $url;
		return true;
	}
}
class WPCPM_Institution_Record {
	public static function get( $id ) { return $GLOBALS['records'][ $id ] ?? array(); }
	public static function forget_cached( $id ) {}
	public static function set( $id, array $answers ) {
		$held = self::get( $id );
		$kept = array_merge( $held, $answers );
		if ( $kept === $held ) { return false; }
		$GLOBALS['records'][ $id ] = $kept;
		return true;
	}
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';
	public static function record( array $entry ) { $GLOBALS['audit'][] = $entry; return 1; }
	/** A school's rows, newest first, as the real one answers them. */
	public static function entries_for( $institution, $limit = 50 ) {
		$out = array();
		foreach ( array_reverse( $GLOBALS['audit'] ) as $entry ) {
			if ( isset( $entry['institution'] ) && (string) $entry['institution'] === (string) $institution ) {
				$out[] = $entry + array( 'data' => array() );
			}
		}
		return $limit > 0 ? array_slice( $out, 0, (int) $limit ) : $out;
	}
}
class WPCPM_Institution_Request {
	const LOCK_TTL = 300;
	public static function school_lock_name( $id ) { return 'lock-' . $id; }
}
/** The school's lock: free, unless a check holds it for another request (`$GLOBALS['busy']`, by name). */
class WPCPM_Lock {
	public static function take( $name, $ttl ) {
		if ( ! empty( $GLOBALS['busy'][ $name ] ) ) { return ''; }
		$GLOBALS['locks_taken'][] = $name;
		return 'token';
	}
	public static function release( $name, $token ) { $GLOBALS['locks_released'][] = $name; }
}
/** The index: it holds every school but those a check names in `$GLOBALS['unindexed']`. */
class WPCPM_Institutions_Index {
	public static function row( $id ) { return null; }
	public static function has( $id ) { return ! in_array( (string) $id, $GLOBALS['unindexed'], true ); }
}
class WPCPM_Mail {
	public static function site_name() { return 'Site'; }
	public static function send( $recipient, $context, $build ) { $GLOBALS['sent'][] = $context; return true; }
}
class WPCPM_Mail_Templates {
	public static function mail( $id, array $values ) { return array( 'id' => $id ); }
}

$failures = 0;
$checks   = 0;
function check( $label, $ok ) {
	global $failures, $checks;
	++$checks;
	if ( ! $ok ) {
		++$failures;
		echo "FAIL: {$label}\n";
	}
}

/** What a failed equality prints, so a wrong summary line shows itself. */
function same( $label, $actual, $expected ) {
	$ok = $actual === $expected;
	check( $label, $ok );
	if ( ! $ok ) {
		echo '      expected: ' . var_export( $expected, true ) . "\n      actual:   " . var_export( $actual, true ) . "\n";
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-areas.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tutors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-seed.php';

/** A client that answers from the suite's tables and records every request. */
class Fake_Airtable extends WPCPM_Airtable {
	public $fail = '';
	public function __construct() {}
	public function fetch_all( $table, array $args = array() ) {
		$GLOBALS['requests'][] = array( $table, $args['fields'] ?? null );
		if ( $this->fail === $table ) {
			return new WP_Error( 'wpcpm_airtable_down', 'Airtable said no.' );
		}
		return $GLOBALS['tables'][ $table ] ?? array();
	}
}

const PL = 'recPPPPPPPPPPPPP1';
const JP = 'recJJJJJJJJJJJJJ2';
const NO = 'recNNNNNNNNNNNNN3';
const KR = 'recKKKKKKKKKKKKK4';
const XX = 'recXXXXXXXXXXXXX5';
const A  = 'recAAAAAAAAAAAAA1';
const B  = 'recBBBBBBBBBBBBB2';
const C  = 'recCCCCCCCCCCCCC3';
const D  = 'recDDDDDDDDDDDDD4';
const E  = 'recEEEEEEEEEEEEE5';
const F  = 'recFFFFFFFFFFFFF6';
const G  = 'recGGGGGGGGGGGGG7';
const H  = 'recHHHHHHHHHHHHH8';
const T12   = 'recTTTTTTTTTTTT12';
const TNAME = 'recTTTTTTTTTTTT13';
const TNONE = 'recTTTTTTTTTTTT14';

$GLOBALS['countries'] = array(
	PL => array( 'name' => 'Poland', 'manager' => 'recMMMMMMMMMMMMM1', 'email' => 'ana@example.test', 'calendly' => 'https://calendly.com/ana' ),
	JP => array( 'name' => 'Japan', 'manager' => 'recMMMMMMMMMMMMM2', 'email' => 'jp.contact@example.test', 'calendly' => 'https://calendly.com/jp' ),
	KR => array( 'name' => 'Korea', 'manager' => 'recMMMMMMMMMMMMM2', 'email' => 'jp.contact@example.test', 'calendly' => '' ),
	XX => array( 'name' => 'Chile', 'manager' => 'recMMMMMMMMMMMMM3', 'email' => 'cy@example.test', 'calendly' => 'https://calendly.com/cy' ),
	NO => array( 'name' => 'Norway', 'manager' => '', 'email' => '', 'calendly' => '' ),
);

$keep_countries = $GLOBALS['countries'];
$tutor_one = array( 'Name' => 'Tutor One', 'Email' => 'tutor.one@example.test', 'Institutions Onboarding' => array( A ) );
$tutor_two = array( 'Name' => 'Tutor Two', 'Email' => ' Tutor.Two@Example.test ', 'Institutions Onboarding' => array( A ) );

function reset_tables() {
	global $tutor_one, $tutor_two;
	$GLOBALS['requests'] = array();
	$GLOBALS['tables']   = array(
		'tblInstitutions'  => array(
			array( 'id' => A, 'fields' => array( 'Name' => 'School A', 'Country' => array( PL ) ) ),
			array( 'id' => B, 'fields' => array( 'Name' => 'School B', 'Country' => array( JP ), 'Original program manager' => array( T12 ) ) ),
			array( 'id' => C, 'fields' => array( 'Name' => 'School C', 'Country' => array( JP ) ) ),
		),
		'tblUYWUSEcRLJ5BaR' => array(
			array( 'id' => T12, 'fields' => array( 'Name' => 'Someone Else', 'Email' => 'bo@example.test', 'Calendly link' => 'https://calendly.com/bo' ) ),
			array( 'id' => TNAME, 'fields' => array( 'Name' => '  BO boss ' ) ),
			array( 'id' => TNONE, 'fields' => array( 'Name' => 'Nobody Known' ) ),
		),
		'tblTutors'        => array(
			array( 'id' => 'recRRRRRRRRRRRR01', 'fields' => $tutor_one ),
			array( 'id' => 'recRRRRRRRRRRRR02', 'fields' => $tutor_two ),
		),
	);
}

reset_tables();
$air = new Fake_Airtable();

echo "=== names ===\n";
check( 'the Team Members table', 'tblUYWUSEcRLJ5BaR' === WPCPM_Institution_Seed::TEAM_TABLE );
check( 'the Institutions columns the seed reads', array( 'Name', 'Country', 'Original program manager' ) === array( WPCPM_Institution_Seed::FIELD_NAME, WPCPM_Institution_Seed::FIELD_COUNTRY, WPCPM_Institution_Seed::FIELD_ORIGINAL ) );

echo "=== plan() ===\n";
$plan = WPCPM_Institution_Seed::plan( $air );
check( 'a plan, not an error', is_array( $plan ) );
check( 'six parts at least, by name', array() === array_diff( array( 'areas', 'booking', 'assign', 'unassigned', 'tutors', 'unresolved' ), array_keys( $plan ) ) );
same( 'areas: Poland to 11, and no Japan, Korea or Chile', array( PL => 11 ), $plan['areas'] );
same( 'booking: 11 gets the countries map\'s link, 12 the Team Members row\'s', array( 11 => 'https://calendly.com/ana', 12 => 'https://calendly.com/bo' ), $plan['booking'] );
same( 'the names of the schools left unassigned are kept for the summary', array( C => 'School C' ), $plan['names'] );
same( 'assign: A by area to 11, B by the Original program manager to 12', array( A => array( 11, 'area' ), B => array( 12, 'original' ) ), $plan['assign'] );
same( 'unassigned: C, for want of an Administrator', array( C => 'no_area' ), $plan['unassigned'] );
same( 'tutors: A has the two, addresses trimmed and lowercase', array( A => array(
	array( 'name' => 'Tutor One', 'email' => 'tutor.one@example.test' ),
	array( 'name' => 'Tutor Two', 'email' => 'tutor.two@example.test' ),
) ), $plan['tutors'] );
check( 'unresolved names Japan\'s address with no account', 1 === count( array_filter( $plan['unresolved'], function ( $s ) { return false !== strpos( $s, 'jp.contact@example.test' ) && false !== strpos( $s, 'Japan' ) && false !== strpos( $s, 'Korea' ) && false !== strpos( $s, 'no account' ); } ) ) );
check( 'and Chile\'s contact, an account that is no Administrator', 1 === count( array_filter( $plan['unresolved'], function ( $s ) { return false !== strpos( $s, 'cy@example.test' ) && false !== strpos( $s, 'Chile' ) && false !== strpos( $s, 'not an Administrator' ); } ) ) );
check( 'Norway has no contact and is not mentioned', 0 === count( array_filter( $plan['unresolved'], function ( $s ) { return false !== strpos( $s, 'Norway' ); } ) ) );

echo "=== the requests: one read of each table, the fields named ===\n";
same( 'three reads', array(
	array( 'tblInstitutions', array( 'Name', 'Country', 'Original program manager' ) ),
	array( 'tblUYWUSEcRLJ5BaR', array( 'Name', 'Email', 'Calendly link' ) ),
	array( 'tblTutors', array( 'Name', 'Email', 'Institutions Onboarding' ) ),
), $GLOBALS['requests'] );

echo "=== plan() writes nothing ===\n";
$before = array( $GLOBALS['settings'], $GLOBALS['umeta'], $GLOBALS['records'], $GLOBALS['audit'], $GLOBALS['sent'] );
WPCPM_Institution_Seed::plan( $air );
check( 'options, meta, records, history and mail unchanged', array( $GLOBALS['settings'], $GLOBALS['umeta'], $GLOBALS['records'], $GLOBALS['audit'], $GLOBALS['sent'] ) === $before );

echo "=== the Original program manager, through the linked Team Members row ===\n";
$GLOBALS['tables']['tblInstitutions'] = array(
	array( 'id' => B, 'fields' => array( 'Name' => 'B', 'Country' => array( JP ), 'Original program manager' => array( T12 ) ) ),
	array( 'id' => D, 'fields' => array( 'Name' => 'D', 'Country' => array( JP ), 'Original program manager' => array( TNAME ) ) ),
	array( 'id' => E, 'fields' => array( 'Name' => 'E', 'Country' => array( JP ), 'Original program manager' => array( TNONE ) ) ),
	array( 'id' => G, 'fields' => array( 'Name' => 'G' ) ),
	array( 'id' => F, 'fields' => array( 'Name' => 'F', 'Country' => array( PL ), 'Original program manager' => array( T12 ) ) ),
);
$plan = WPCPM_Institution_Seed::plan( $air );
same( 'its Email to an account', array( 12, 'original' ), $plan['assign'][ B ] ?? null );
same( 'else its Name, trimmed and case-insensitive, with an Administrator\'s display name', array( 12, 'original' ), $plan['assign'][ D ] ?? null );
check( 'a name that matches nobody assigns nobody', ! isset( $plan['assign'][ E ] ) );
same( 'and says why', 'no_match', $plan['unassigned'][ E ] ?? null );
same( 'no country and no Original program manager', 'no_country', $plan['unassigned'][ G ] ?? null );
same( 'the country\'s area wins over the Original program manager', array( 11, 'area' ), $plan['assign'][ F ] ?? null );

echo "=== a booking link already there is not planned again ===\n";
$GLOBALS['umeta'][11]['wpcpm_booking_url'] = 'https://calendly.com/ana-before';
$plan = WPCPM_Institution_Seed::plan( $air );
same( 'only the Team Members link is planned, for the account with none', array( 12 => 'https://calendly.com/bo' ), $plan['booking'] );
$GLOBALS['umeta'][12]['wpcpm_booking_url'] = 'https://calendly.com/bo-before';
same( 'and no link at all while both accounts hold one', array(), WPCPM_Institution_Seed::plan( $air )['booking'] );
unset( $GLOBALS['umeta'][12] );
unset( $GLOBALS['umeta'][11] );

echo "=== an assignee and a listed tutor are not planned again ===\n";
reset_tables();
$GLOBALS['records'][ A ] = array( 'assigned' => 12, 'tutors' => array( array( 'name' => 'Tutor One', 'email' => 'tutor.one@example.test' ) ) );
$plan = WPCPM_Institution_Seed::plan( $air );
check( 'A is not in assign', ! isset( $plan['assign'][ A ] ) && ! isset( $plan['unassigned'][ A ] ) );
same( 'A\'s tutors: only the one not listed', array( A => array( array( 'name' => 'Tutor Two', 'email' => 'tutor.two@example.test' ) ) ), $plan['tutors'] );
$GLOBALS['records'] = array();

echo "=== a school set to Nobody keeps Nobody (1.123.84) ===\n";
reset_tables();
$GLOBALS['records'][ A ] = array( 'assigned' => 0 );
$nobody_plan             = WPCPM_Institution_Seed::plan( $air );
check( 'a record set to Nobody is not planned, though its country has an Administrator', ! isset( $nobody_plan['assign'][ A ] ) && ! isset( $nobody_plan['unassigned'][ A ] ) );
same( 'and it is counted', 1, $nobody_plan['nobody'] ?? null );
check( 'and the summary says so', in_array( '1 institution was set to Nobody on this site and stays unassigned.', WPCPM_Institution_Seed::summary( $nobody_plan ), true ) );
check( 'the plural', in_array( '2 institutions were set to Nobody on this site and stay unassigned.', WPCPM_Institution_Seed::summary( array( 'nobody' => 2 ) ), true ) );
$GLOBALS['records'] = array();
$fresh_plan         = WPCPM_Institution_Seed::plan( $air );
check( 'a plan made while A had nobody plans A', isset( $fresh_plan['assign'][ A ] ) );
$GLOBALS['records'][ A ] = array( 'assigned' => 0 );
$audit_before            = count( $GLOBALS['audit'] );
$nobody_done             = WPCPM_Institution_Seed::apply( array( 'assign' => $fresh_plan['assign'] ) );
check( 'set to Nobody between the plan and the write, A is not written', array( 'assigned' => 0 ) === $GLOBALS['records'][ A ] && 0 === count( array_filter( array_slice( $GLOBALS['audit'], $audit_before ), function ( $r ) { return 'assignment_changed' === $r['kind'] && A === $r['institution']; } ) ) && 0 === $nobody_done['assigned_area'] );
$GLOBALS['records'] = array();
$GLOBALS['audit']   = array();

echo "=== the areas on the site already: the assignments follow them, the plan writes none ===\n";
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array( JP => 12 ), 'regions' => array() );
$plan = WPCPM_Institution_Seed::plan( $air );
same( 'B and C go to the site\'s Administrator for Japan', array( 12, 'area' ), $plan['assign'][ B ] ?? null );
check( 'C too', array( 12, 'area' ) === ( $plan['assign'][ C ] ?? null ) );
same( 'A has no area on the site: the Original program manager is none, so A stays unassigned', 'no_area', $plan['unassigned'][ A ] ?? null );
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array(), 'regions' => array() );

echo "=== a failed read is the error, and no table after it is read ===\n";
reset_tables();
$down       = new Fake_Airtable();
$down->fail = 'tblUYWUSEcRLJ5BaR';
$bad        = WPCPM_Institution_Seed::plan( $down );
check( 'an error', is_wp_error( $bad ) && 'Airtable said no.' === $bad->get_error_message() );
same( 'the tutors were not read', array( 'tblInstitutions', 'tblUYWUSEcRLJ5BaR' ), array_column( $GLOBALS['requests'], 0 ) );

echo "=== the summary, as the Administrator reads it ===\n";
reset_tables();
$plan = WPCPM_Institution_Seed::plan( $air );
$lines = WPCPM_Institution_Seed::summary( $plan );
same( 'the summary, line by line', array(
	'The countries map holds 5 countries, read on ' . gmdate( 'F j, Y', $GLOBALS['read_at'] ) . '.',
	'1 country is given an Administrator.',
	'2 Administrators are given a booking link.',
	'1 institution is assigned to its country\'s Administrator.',
	'1 institution is assigned to the person who first handled it.',
	'1 institution stays unassigned: no Administrator covers its country and nobody is recorded as having first handled it.',
	'2 tutors are added, at 1 institution.',
	'Not assigned: School C: no Administrator covers its country and nobody is recorded as having first handled it.',
	'The Countries table names jp.contact@example.test as the contact for Japan and Korea, but no account on this site has that address, so they are given no Administrator.',
	'The Countries table names cy@example.test as the contact for Chile, but that account is not an Administrator, so it is given no Administrator.',
), $lines );

// The other reasons, the kept schools, the plurals and a tutor row that cannot be placed.
$rich = array( 'areas' => array( PL => 11, JP => 12 ), 'areas_set' => false, 'booking' => array( 11 => 'https://calendly.com/ana', 12 => 'https://calendly.com/bo' ), 'assign' => array( A => array( 11, 'area' ), B => array( 12, 'area' ), D => array( 12, 'original' ) ), 'unassigned' => array( C => 'no_match', E => 'no_match', G => 'no_country' ), 'kept' => 2, 'names' => array( C => 'School C', E => '', G => 'School G' ), 'tutors' => array( A => array( array(), array() ), B => array( array() ) ), 'unresolved' => array( '1 row of the Tutors table has no name or no valid address, so it is left out.' ) );
same( 'plurals, every reason and the schools kept', array(
	'2 countries are given an Administrator.',
	'2 Administrators are given a booking link.',
	'2 institutions are assigned to their countries\' Administrators.',
	'1 institution is assigned to the person who first handled it.',
	'2 institutions have an Administrator already and keep them.',
	'2 institutions stay unassigned: no Administrator covers their country and the people who first handled them are not Administrators here.',
	'1 institution stays unassigned: it has no country and nobody is recorded as having first handled it.',
	'3 tutors are added, at 2 institutions.',
	'Not assigned: School C: no Administrator covers its country and the person who first handled it is not an Administrator here.',
	'Not assigned: ' . E . ': no Administrator covers its country and the person who first handled it is not an Administrator here.',
	'Not assigned: School G: it has no country and nobody is recorded as having first handled it.',
	'1 row of the Tutors table has no name or no valid address, so it is left out.',
), WPCPM_Institution_Seed::summary( $rich ) );

reset_tables();
$GLOBALS['tables']['tblTutors'][] = array( 'id' => 'recRRRRRRRRRRRR03', 'fields' => array( 'Name' => 'No Address', 'Institutions Onboarding' => array( A ) ) );
$GLOBALS['tables']['tblTutors'][] = array( 'id' => 'recRRRRRRRRRRRR04', 'fields' => array( 'Name' => 'No School', 'Email' => 'no.school@example.test' ) );
$more = WPCPM_Institution_Seed::plan( $air );
check( 'a tutor row with no address, and one that links no school, are named and left out', in_array( '1 row of the Tutors table has no name or no valid address, so it is left out.', $more['unresolved'], true ) && in_array( '1 row of the Tutors table links no institution, so it is left out.', $more['unresolved'], true ) && 2 === count( $more['tutors'][ A ] ) );
reset_tables();

// The plan with nothing in it says so, in as many lines.
$empty = array( 'areas' => array(), 'booking' => array(), 'assign' => array(), 'unassigned' => array(), 'tutors' => array(), 'unresolved' => array(), 'kept' => 0, 'areas_set' => false );
same( 'an empty plan', array(
	'No country is given an Administrator.',
	'No booking link is added.',
	'No institution is assigned.',
	'No tutor is added.',
), WPCPM_Institution_Seed::summary( $empty ) );

$GLOBALS['settings']['institution_areas'] = array( 'countries' => array( JP => 12 ), 'regions' => array() );
$plan = WPCPM_Institution_Seed::plan( $air );
same( 'with areas on the site, the line after the map\'s is the sentence that says so', 'The areas are already set on the site, so they were left as they are.', WPCPM_Institution_Seed::summary( $plan )[1] );
check( 'and the sentences about countries given no Administrator are left out: the site\'s areas decide', array() === array_filter( $plan['unresolved'], function ( $s ) { return false !== strpos( $s, 'The Countries table' ); } ) );
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array(), 'regions' => array() );

echo "=== the Team Members rows that resolve to nobody ===\n";
$GLOBALS['accounts'][14] = array( 'Bo Boss', 'bo2@example.test' );
const TNOACC = 'recTTTTTTTTTTTT15';
const TNADM  = 'recTTTTTTTTTTTT16';
const TAMB   = 'recTTTTTTTTTTTT17';
reset_tables();
$GLOBALS['tables']['tblUYWUSEcRLJ5BaR'][] = array( 'id' => TNOACC, 'fields' => array( 'Name' => 'Eli Example', 'Email' => 'eli@example.test' ) );
$GLOBALS['tables']['tblUYWUSEcRLJ5BaR'][] = array( 'id' => TNADM, 'fields' => array( 'Name' => 'Cy Client', 'Email' => 'cy@example.test' ) );
$GLOBALS['tables']['tblUYWUSEcRLJ5BaR'][] = array( 'id' => TAMB, 'fields' => array( 'Name' => 'BO BOSS' ) );
$GLOBALS['tables']['tblInstitutions']     = array(
	array( 'id' => E, 'fields' => array( 'Name' => 'School E', 'Country' => array( JP ), 'Original program manager' => array( TNONE ) ) ),
	array( 'id' => D, 'fields' => array( 'Name' => 'School D', 'Country' => array( JP ), 'Original program manager' => array( TNOACC ) ) ),
	array( 'id' => F, 'fields' => array( 'Name' => 'School F', 'Country' => array( JP ), 'Original program manager' => array( TNOACC ) ) ),
	array( 'id' => G, 'fields' => array( 'Name' => 'School G', 'Original program manager' => array( TNADM ) ) ),
	array( 'id' => H, 'fields' => array( 'Name' => 'School H', 'Country' => array( JP ), 'Original program manager' => array( TAMB ) ) ),
	array( 'id' => B, 'fields' => array( 'Name' => 'School B', 'Country' => array( JP ), 'Original program manager' => array( T12 ) ) ),
);
$GLOBALS['managers'][] = 14;
$plan  = WPCPM_Institution_Seed::plan( $air );
$lines = WPCPM_Institution_Seed::summary( $plan );
same( 'a row with an address and no account, with a name that is no Administrator\'s', 'Not resolved: Eli Example (eli@example.test): no account has this address; 2 institutions stay unassigned.', $lines[ array_search( 'Not resolved: Eli Example (eli@example.test): no account has this address; 2 institutions stay unassigned.', $lines, true ) ] ?? '' );
check( 'a name only, and nobody has it', in_array( 'Not resolved: Nobody Known: no Administrator has this name; 1 institution stays unassigned.', $lines, true ) );
check( 'an account that is not an Administrator', in_array( 'Not resolved: Cy Client (cy@example.test): the account with this address is not an Administrator; 1 institution stays unassigned.', $lines, true ) );
check( 'two Administrators share the display name: the row resolves to nobody, and says so', in_array( 'Not resolved: BO BOSS: two Administrators have this name; 1 institution stays unassigned.', $lines, true ) && ! isset( $plan['assign'][ H ] ) && 'no_match' === $plan['unassigned'][ H ] );
check( 'a row that resolved is not listed', 0 === count( array_filter( $lines, function ( $l ) { return 0 === strpos( $l, 'Not resolved: Someone Else' ); } ) ) && isset( $plan['assign'][ B ] ) );
same( 'the Not assigned lines come before the Not resolved lines, each school once, by its name', array(
	'Not assigned: School E: no Administrator covers its country and the person who first handled it is not an Administrator here.',
	'Not assigned: School D: no Administrator covers its country and the person who first handled it is not an Administrator here.',
	'Not assigned: School F: no Administrator covers its country and the person who first handled it is not an Administrator here.',
	'Not assigned: School G: it has no country and the person who first handled it is not an Administrator here.',
	'Not assigned: School H: no Administrator covers its country and the person who first handled it is not an Administrator here.',
), array_values( array_filter( $lines, function ( $l ) { return 0 === strpos( $l, 'Not assigned: ' ); } ) ) );
unset( $GLOBALS['accounts'][14] );
$GLOBALS['managers'] = array( 11, 12 );
reset_tables();

echo "=== a Team Members link that is not https is not kept ===\n";
$GLOBALS['tables']['tblUYWUSEcRLJ5BaR'][0]['fields']['Calendly link'] = 'http://calendly.com/bo';
$http = WPCPM_Institution_Seed::plan( $air );
check( 'no booking for 12', ! isset( $http['booking'][12] ) );
check( 'and the summary names the Administrator whose link is not https (1.123.84)', in_array( 'The booking link of Bo Boss is not an https address, so it was not added.', $http['unresolved'], true ) );
check( 'once, and nobody else', 1 === count( array_filter( $http['unresolved'], function ( $s ) { return false !== strpos( $s, 'https' ); } ) ) );
reset_tables();

echo "=== a countries map link that is not https is not kept, and is named ===\n";
$GLOBALS['countries'][ PL ]['calendly'] = 'http://calendly.com/ana';
$http = WPCPM_Institution_Seed::plan( $air );
check( 'no booking for 11', ! isset( $http['booking'][11] ) );
check( 'the summary names Ana Admin', in_array( 'The booking link of Ana Admin is not an https address, so it was not added.', $http['unresolved'], true ) );
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array( JP => 12 ), 'regions' => array() );
check( 'and still with areas on the site, since the booking links are seeded whatever the areas', in_array( 'The booking link of Ana Admin is not an https address, so it was not added.', WPCPM_Institution_Seed::plan( $air )['unresolved'], true ) );
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array(), 'regions' => array() );
$GLOBALS['umeta'][11]['wpcpm_booking_url'] = 'https://calendly.com/ana-before';
check( 'an Administrator who holds a link already is not named: nothing was going to be added', 0 === count( array_filter( WPCPM_Institution_Seed::plan( $air )['unresolved'], function ( $s ) { return false !== strpos( $s, 'booking link' ); } ) ) );
unset( $GLOBALS['umeta'][11] );
$GLOBALS['countries'] = $keep_countries;
reset_tables();

echo "=== a tutor removed on the site is not brought back (1.123.84) ===\n";
$GLOBALS['audit'] = array(
	array( 'kind' => 'tutor_added', 'institution' => A, 'data' => array( 'name' => 'Tutor One', 'email' => 'tutor.one@example.test' ) ),
	array( 'kind' => 'tutor_removed', 'institution' => A, 'data' => array( 'name' => 'Tutor One', 'email' => 'tutor.one@example.test' ) ),
	array( 'kind' => 'tutor_removed', 'institution' => B, 'data' => array( 'name' => 'Tutor Two', 'email' => 'tutor.two@example.test' ) ),
);
$removed = WPCPM_Institution_Seed::plan( $air );
same( 'A plans only the tutor never removed from its list', array( A => array( array( 'name' => 'Tutor Two', 'email' => 'tutor.two@example.test' ) ) ), $removed['tutors'] );
check( 'the summary says why one was left out', in_array( '1 tutor of the Tutors table was removed from the school\'s list on this site, so they are not added again.', $removed['unresolved'], true ) );
$GLOBALS['audit'] = array();

echo "=== a tutor linked to a school the index does not hold is named, not written (1.123.84) ===\n";
const Z = 'recZZZZZZZZZZZZZ9';
$GLOBALS['tables']['tblTutors'][] = array( 'id' => 'recRRRRRRRRRRRR05', 'fields' => array( 'Name' => 'Tutor Five', 'Email' => 'tutor.five@example.test', 'Institutions Onboarding' => array( Z, A ) ) );
$GLOBALS['tables']['tblTutors'][] = array( 'id' => 'recRRRRRRRRRRRR06', 'fields' => array( 'Name' => 'Tutor Six', 'Email' => 'tutor.six@example.test', 'Institutions Onboarding' => array( Z ) ) );
$GLOBALS['unindexed']             = array( Z );
$unknown                          = WPCPM_Institution_Seed::plan( $air );
check( 'nothing is planned for the record the index does not hold', ! isset( $unknown['tutors'][ Z ] ) );
check( 'the tutor is still planned for the school it also links', in_array( 'tutor.five@example.test', array_column( $unknown['tutors'][ A ], 'email' ), true ) );
check( 'the record is named, with how many tutors', in_array( 'The Tutors table links 2 tutors to ' . Z . ', a record the pipeline index does not hold, so they are left out.', $unknown['unresolved'], true ) );
$GLOBALS['unindexed'] = array();
reset_tables();

echo "=== the countries map line ===\n";
$GLOBALS['countries'] = array();
$none                 = WPCPM_Institution_Seed::summary( WPCPM_Institution_Seed::plan( $air ) );
same( 'an empty map says what to do first', 'The countries map is empty: run the institutions sync on the Institutions screen\'s Sync and storage tab first.', $none[0] );
$GLOBALS['countries'] = $keep_countries;
same( 'one country is singular', 'The countries map holds 1 country, read on ' . gmdate( 'F j, Y', $GLOBALS['read_at'] ) . '.', ( function () { $GLOBALS['countries'] = array_slice( $GLOBALS['countries'], 0, 1, true ); return WPCPM_Institution_Seed::summary( WPCPM_Institution_Seed::plan( new Fake_Airtable() ) )[0]; } )() );
$GLOBALS['countries'] = $keep_countries;
reset_tables();

echo "=== apply() ===\n";
reset_tables();
$GLOBALS['tables']['tblInstitutions'][] = array( 'id' => H, 'fields' => array( 'Name' => 'H', 'Country' => array( PL ) ) );
$GLOBALS['records'][ H ]                = array( 'assigned' => 12 );
$plan                                   = WPCPM_Institution_Seed::plan( $air );
check( 'H already has an Administrator, so the plan leaves it', ! isset( $plan['assign'][ H ] ) );

$done = WPCPM_Institution_Seed::apply( $plan );
same( 'the areas are written', array( 'countries' => array( PL => 11 ), 'regions' => array() ), WPCPM_Institution_Areas::areas() );
same( 'the booking link is kept', 'https://calendly.com/ana', WPCPM_Manager_Booking::url_for( 11 ) );
same( 'the Team Members link is kept for 12', 'https://calendly.com/bo', WPCPM_Manager_Booking::url_for( 12 ) );
same( 'A is assigned to 11, B to 12', array( 11, 12 ), array( WPCPM_Institution_Assignment::stored_of( A ), WPCPM_Institution_Assignment::stored_of( B ) ) );
check( 'C is not assigned', 0 === WPCPM_Institution_Assignment::stored_of( C ) );
same( 'H keeps its assignee', 12, WPCPM_Institution_Assignment::stored_of( H ) );
same( 'A lists its two tutors', array( 'tutor.one@example.test', 'tutor.two@example.test' ), array_column( WPCPM_Institution_Tutors::list_for( A ), 'email' ) );
check( 'no email was sent', array() === $GLOBALS['sent'] );
$kinds = array_count_values( array_column( $GLOBALS['audit'], 'kind' ) );
same( 'history: two assignments and two tutors', array( 'assignment_changed' => 2, 'tutor_added' => 2 ), $kinds );
check( 'every row is the site\'s: actor 0, ground system', array( array( 0, 'system' ) ) === array_values( array_unique( array_map( function ( $r ) { return array( $r['actor'], $r['ground'] ); }, $GLOBALS['audit'] ), SORT_REGULAR ) ) );
same( 'the counts written', array( 'areas' => 1, 'areas_kept' => false, 'booking' => 2, 'assigned_area' => 1, 'assigned_original' => 1, 'tutors' => 2, 'failed' => array() ), $done );

echo "=== apply() again changes nothing, and never replaces ===\n";
$GLOBALS['settings']['institution_areas']['countries'][ PL ] = 12;
$GLOBALS['umeta'][11]['wpcpm_booking_url']                   = 'https://calendly.com/ana-changed';
WPCPM_Institution_Assignment::assign( A, 12, 7, false );
$GLOBALS['audit'] = array();
$snapshot         = array( $GLOBALS['settings'], $GLOBALS['umeta'], $GLOBALS['records'] );
$plan2            = WPCPM_Institution_Seed::plan( $air );
$again            = WPCPM_Institution_Seed::apply( $plan2 );
check( 'settings, meta and records as they were', array( $GLOBALS['settings'], $GLOBALS['umeta'], $GLOBALS['records'] ) === $snapshot );
check( 'no history row, no email', array() === $GLOBALS['audit'] && array() === $GLOBALS['sent'] );
same( 'the counts: nothing, and the areas kept', array( 'areas' => 0, 'areas_kept' => true, 'booking' => 0, 'assigned_area' => 0, 'assigned_original' => 0, 'tutors' => 0, 'failed' => array() ), $again );
same( 'a plan made then holds the sentence, and apply does not write them', 'The areas are already set on the site, so they were left as they are.', WPCPM_Institution_Seed::summary( $plan2 )[1] );

echo "=== apply() with a plan from before: the areas on the site win, never replaced ===\n";
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array( JP => 12 ), 'regions' => array() );
$patched                                  = $GLOBALS['patched'];
WPCPM_Institution_Seed::apply( $plan );
same( 'the settings were not patched', $patched, $GLOBALS['patched'] );
same( 'the areas are as they were', array( 'countries' => array( JP => 12 ), 'regions' => array() ), WPCPM_Institution_Areas::areas() );

echo "=== apply() assigns under the school's lock (1.123.84) ===\n";
reset_tables();
$GLOBALS['records']        = array();
$GLOBALS['audit']          = array();
$GLOBALS['locks_taken']    = array();
$GLOBALS['locks_released'] = array();
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array(), 'regions' => array() );
$locked_plan               = WPCPM_Institution_Seed::plan( $air );
$GLOBALS['busy']           = array( 'lock-' . B => true );
$locked                    = WPCPM_Institution_Seed::apply( $locked_plan );
$GLOBALS['busy']           = array();
check( 'A is assigned, under its lock, which is let go', 11 === WPCPM_Institution_Assignment::stored_of( A ) && in_array( 'lock-' . A, $GLOBALS['locks_taken'], true ) && in_array( 'lock-' . A, $GLOBALS['locks_released'], true ) );
check( 'B, whose lock another request holds, is not assigned', 0 === WPCPM_Institution_Assignment::stored_of( B ) );
check( 'and failed says so, in a sentence', in_array( 'The institution ' . B . ' was not assigned: somebody else was changing its record. Run the command again.', $locked['failed'], true ) && 1 === $locked['assigned_area'] && 0 === $locked['assigned_original'] );
$GLOBALS['records'] = array();
$GLOBALS['audit']   = array();
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array(), 'regions' => array() );
$GLOBALS['umeta']   = array();

echo "=== apply(): the site refuses the areas ===\n";
$GLOBALS['settings']['institution_areas'] = array( 'countries' => array(), 'regions' => array() );
$GLOBALS['refuse'] = true;
$refused           = WPCPM_Institution_Seed::apply( array( 'areas' => array( PL => 11 ) ) );
same( 'one sentence, and nothing written', array( 0, array( 'The site refused the areas, so none was written.' ) ), array( $refused['areas'], $refused['failed'] ) );
$GLOBALS['refuse'] = false;

echo "=== the source ===\n";
$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-seed.php' );
check( 'it asks for no email: notify is false', false !== strpos( $src, 'WPCPM_Institution_Assignment::assign(' ) && 1 === preg_match( '/assign\([^;]*false\s*\)/', $src ) );
check( 'it writes as the site: actor 0 and the system ground', false !== strpos( $src, 'WPCPM_Institution_Audit::GROUND_SYSTEM' ) );
check( 'no dash of the long kinds anywhere in the file', 0 === preg_match( '/[\x{2013}\x{2014}]/u', $src ) );
check( 'the role is not called by the old name: only the column\'s own name, Original program manager, holds the words', 0 === preg_match( '/program manager/i', str_ireplace( 'Original program manager', '', $src ) ) );

echo "\n" . ( $failures ? "{$failures} FAILURE(S) of {$checks}\n" : "ALL PASS ({$checks} checks)\n" );
exit( $failures ? 1 : 0 );
