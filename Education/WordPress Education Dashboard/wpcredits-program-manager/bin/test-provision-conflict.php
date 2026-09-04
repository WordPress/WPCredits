<?php
/**
 * What provisioning refuses to adopt, and what it still adopts.
 *
 * `provision_student()` identifies a student by the record ID this sync stamped, and falls
 * back to the email address. The fallback is the dangerous half. Since the institutions
 * module an institution chooses which addresses it imports (design spec 7.6), and assigning
 * a mentor is routine manager work, so a mentor's or a colleague's address imported as a
 * student would, on the very next sync, have turned that person's account into a student on
 * that institution's roster: the Student role added, the program row written over them, and
 * the institution stamp that opens the roster fence.
 *
 * So an account found by email carrying another module's record stamp, live or ended, or any
 * role this program did not give it, is a conflict and not a match. What this suite pins:
 *
 * - each of those accounts leaves the run exactly as it arrived: the same roles, no student
 *   stamp, no institution stamp, no program row, and no roster row pointing at it;
 * - each is counted on `conflicts`, the number the sync already keeps for this, and named in
 *   the run's notices, because an address nobody here can resolve is a person's job and a
 *   silent skip is how that job never gets done;
 * - what was adopted yesterday is still adopted: a plain unstamped Subscriber, and a student
 *   found by their own stamp, whatever else that account happens to hold.
 *
 * Every address is under example.test and every name is invented.
 *
 * Run from the plugin root:  php bin/test-provision-conflict.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array();
$GLOBALS['users']    = array();
$GLOBALS['umeta']    = array();
$GLOBALS['cron']     = array();
$GLOBALS['airtable'] = array();
$GLOBALS['inserted'] = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

/**
 * A user hydrated from `$GLOBALS['users']`, whose role changes are written back.
 *
 * Written back on purpose: the assertions below are about accounts the run must not have
 * touched, and a stub that kept roles on the object alone would show an untouched account
 * whatever the sync did to it.
 */
class WP_User {
	public $ID = 0, $user_login = '', $user_email = '', $display_name = '', $roles = array();
	public function __construct( $id = 0 ) {
		$this->ID = (int) $id;
		$data     = $GLOBALS['users'][ $this->ID ] ?? array();

		$this->user_login   = $data['login'] ?? '';
		$this->user_email   = $data['email'] ?? '';
		$this->display_name = $data['name'] ?? '';
		$this->roles        = $data['roles'] ?? array();
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) { $this->roles = array_values( array_unique( array_merge( $this->roles, array( $r ) ) ) ); $this->save(); }
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); $this->save(); }
	public function set_role( $r ) { $this->roles = array( $r ); $this->save(); }
	private function save() { $GLOBALS['users'][ $this->ID ]['roles'] = $this->roles; }
}
class WP_Post { public $ID = 0, $post_title = '', $post_type = '', $post_status = 'publish'; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $s, $p = null ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_user( $s, $strict = false ) { return preg_replace( '/[^a-z0-9 _.\-@]/i', '', (string) $s ); }
function wp_generate_password() { return 'not-a-real-password'; }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function remove_filter() { return true; }
function add_filter() {} function do_action() {}
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $h ) { return isset( $GLOBALS['cron'][ $h ] ) ? $GLOBALS['cron'][ $h ] : false; }
function wp_get_scheduled_event( $h ) { return false; }
function wp_schedule_event() { return true; }
function wp_schedule_single_event( $when, $hook ) { $GLOBALS['cron'][ $hook ] = (int) $when; return true; }
function wp_clear_scheduled_hook( $h = '' ) { unset( $GLOBALS['cron'][ $h ] ); return 1; }

function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ (int) $id ][ $key ] = $value; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ (int) $id ][ $key ] ); return true; }

function get_user_by( $field, $value ) {
	if ( 'id' === $field ) {
		return isset( $GLOBALS['users'][ (int) $value ] ) ? new WP_User( (int) $value ) : false;
	}

	foreach ( $GLOBALS['users'] as $id => $data ) {
		if ( 'email' === $field && strtolower( $data['email'] ) === strtolower( (string) $value ) ) {
			return new WP_User( $id );
		}
	}

	return false;
}
function username_exists( $login ) {
	foreach ( $GLOBALS['users'] as $id => $data ) {
		if ( $data['login'] === $login ) {
			return $id;
		}
	}

	return false;
}

/**
 * Every account this run created, and the account itself.
 *
 * The list is asserted as well as the accounts: "the mentor was not adopted" and "no second
 * account was made for the mentor's address" are two different promises, and a run could
 * keep the first while breaking the second.
 */
function wp_insert_user( array $data ) {
	static $next = 500;

	if ( get_user_by( 'email', $data['user_email'] ) ) {
		return new WP_Error( 'existing_user_email', 'Sorry, that email address is already used!' );
	}

	$id = ++$next;

	$GLOBALS['inserted'][]  = $data['user_email'];
	$GLOBALS['users'][ $id ] = array(
		'login' => $data['user_login'],
		'email' => $data['user_email'],
		'name'  => $data['display_name'] ?? '',
		'roles' => array( $data['role'] ),
	);

	return $id;
}

/**
 * Users by exact meta match, or by a meta key existing, honouring `fields`.
 */
function get_users( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['umeta'] as $id => $meta ) {
		if ( isset( $args['meta_key'] ) ) {
			if ( ! isset( $meta[ $args['meta_key'] ] ) || $meta[ $args['meta_key'] ] !== $args['meta_value'] ) {
				continue;
			}
		} elseif ( isset( $args['meta_query'][0]['key'] ) ) {
			if ( ! array_key_exists( $args['meta_query'][0]['key'], $meta ) ) {
				continue;
			}
		}

		$out[] = ( 'ID' === ( $args['fields'] ?? 'all' ) ) ? (int) $id : new WP_User( $id );
	}

	return $out;
}

/**
 * An Airtable that answers from `$GLOBALS['airtable'][ table ]`.
 *
 * Only the requested columns come back, as the API does it, and the reports table is
 * filtered by the status formula, so a row in an untracked status is never seen.
 */
class WPCPM_Airtable {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) ); }
	public function __construct( $settings = null ) {}
	public function formula_in( $field, array $values, $lower = false ) {
		return 'IN:' . json_encode( array( 'field' => $field, 'values' => array_values( $values ) ) );
	}
	public function fetch_page( $table, array $args = array() ) {
		$wanted = isset( $args['fields'] ) ? (array) $args['fields'] : array();
		$filter = null;

		if ( ! empty( $args['formula'] ) && 0 === strpos( $args['formula'], 'IN:' ) ) {
			$filter = json_decode( substr( $args['formula'], 3 ), true );
		}

		$out = array();

		foreach ( $GLOBALS['airtable'][ $table ] ?? array() as $record ) {
			$cells = $record['fields'];

			if ( $filter && ! in_array( (string) ( $cells[ $filter['field'] ] ?? '' ), $filter['values'], true ) ) {
				continue;
			}

			if ( $wanted ) {
				$cells = array_intersect_key( $cells, array_flip( $wanted ) );
			}

			$out[] = array( 'id' => $record['id'], 'createdTime' => '2026-01-05T10:00:00.000Z', 'fields' => $cells );
		}

		return array( 'records' => $out, 'offset' => null );
	}
	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) {
				return (string) $value['name'];
			}
			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$parts[] = (string) $item;
				} elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
					$parts[] = (string) $item['name'];
				}
			}
			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
	public static function link_ids( $value ) {
		$ids = array();
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) {
				$ids[] = $item;
			} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$ids[] = (string) $item['id'];
			}
		}
		return $ids;
	}
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
// The members module is loaded for its two constants and nothing else: the fence names the
// stamp keys through the class that writes them, and a suite that stubbed them would go on
// passing after a rename that broke the real thing.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';

$fails = 0;
$total = 0;

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

function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }

/** The roles an account holds now, as the run left them. */
function roles( $id ) { return $GLOBALS['users'][ (int) $id ]['roles'] ?? array(); }

/** One meta value, with `(absent)` for a key that was never written: the two differ here. */
function meta( $id, $key ) {
	return array_key_exists( $key, $GLOBALS['umeta'][ (int) $id ] ?? array() ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : '(absent)';
}

/** Every meta key the run left on an account. */
function meta_keys( $id ) {
	$keys = array_keys( $GLOBALS['umeta'][ (int) $id ] ?? array() );
	sort( $keys );

	return $keys;
}

/** The run's notice about one person, or '' when the run said nothing about them. */
function notice_about( array $report, $name ) {
	foreach ( (array) $report['notices'] as $notice ) {
		if ( has( $notice, $name ) ) {
			return (string) $notice;
		}
	}

	return '';
}

/** The body of one method, by brace depth. */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}
	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	$length = strlen( $source );
	while ( $end < $length && $depth > 0 ) {
		if ( '{' === $source[ $end ] ) { ++$depth; } elseif ( '}' === $source[ $end ] ) { --$depth; }
		++$end;
	}
	return substr( $source, $offset, $end - $offset - 1 );
}

$defaults       = WPCPM_Settings::defaults();
$students_table = $defaults['students_table'];
$reports_table  = $defaults['reports_table'];
$fields         = WPCPM_Mentors_Sync::fields();

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array(
	'api_token'           => 'patTEST',
	'base_id'             => 'appTEST',
	'auto_sync'           => true,
	'send_welcome_email'  => false,
	'student_on_inactive' => 'revoke',
);

$inst  = 'recINSTEXAMPLE001';
$other = 'recINSTOTHER00001';

$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
	'v'            => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
	'institutions' => array( $inst => 'Universidad Example', $other => 'Politechnika Example' ),
	'teams'        => array(),
);

$GLOBALS['airtable'][ $students_table ] = array();
$GLOBALS['airtable'][ $reports_table ]  = array();

function next_record( $prefix ) {
	static $n = 0;
	++$n;
	return $prefix . str_pad( (string) $n, 17 - strlen( $prefix ), '0', STR_PAD_LEFT );
}

/** A Students row, the shape an institution's import leaves behind. */
function student_row( $name, $email, $institution ) {
	global $students_table, $fields;

	$id = next_record( 'recS' );

	$GLOBALS['airtable'][ $students_table ][] = array(
		'id'     => $id,
		'fields' => array(
			$fields['student_record_name'] => $name,
			$fields['student_email']       => $email,
			$fields['student_status']      => 'In Sensei',
			$fields['student_institution'] => array( $institution ),
			$fields['student_start']       => '2026-09-07',
		),
	);

	return $id;
}

/** The Students Reports row the mentor-assignment automation writes for that student. */
function report_row( $name, $email, $institution ) {
	global $reports_table, $fields;

	$id = next_record( 'recR' );

	$GLOBALS['airtable'][ $reports_table ][] = array(
		'id'     => $id,
		'fields' => array(
			$fields['report_name']       => $name,
			$fields['report_email']      => $email,
			$fields['report_status']     => 'In Sensei',
			$fields['report_instituton'] => array( $institution ),
		),
	);

	return $id;
}

/*
 * One import, one sync. Eight of these rows are the institution's import of an address that
 * is not a student's; three are the ordinary cases the rule must leave alone.
 */
$people = array(
	'mira'  => array( 'Mira Mentor', 'mira@example.test' ),
	'nils'  => array( 'Nils Mentor', 'nils@example.test' ),
	'pia'   => array( 'Pia Program', 'pia@example.test' ),
	'eve'   => array( 'Eve Editor', 'eve@example.test' ),
	'ola'   => array( 'Ola Office', 'ola@example.test' ),
	'frank' => array( 'Frank Former', 'frank@example.test' ),
	'sam'   => array( 'Sam Subscriber', 'sam@example.test' ),
	'wes'   => array( 'Wes Wrong', 'wes@example.test' ),
	'tess'  => array( 'Tess Student', 'tess@example.test' ),
	'uma'   => array( 'Uma Student', 'uma@example.test' ),
	'vic'   => array( 'Vic Fresh', 'vic@example.test' ),
	'zoe'   => array( 'Zoe Both', 'zoe@example.test' ),
);

$rows    = array();
$reports = array();

foreach ( $people as $key => $person ) {
	$rows[ $key ]    = student_row( $person[0], $person[1], $inst );
	$reports[ $key ] = report_row( $person[0], $person[1], $inst );
}

/*
 * The accounts the addresses already belong to. Two shapes of mentor on purpose: Mira carries
 * the mentors sync's stamp on a Subscriber account, which is what a mentor whose role was
 * taken away looks like, and Nils carries only the role. Either half of the rule alone has to
 * be enough, or a hole opens the first time one of the two is missing.
 */
$GLOBALS['users'] = array(
	10 => array( 'login' => 'mira', 'email' => 'mira@example.test', 'name' => 'Mira Mentor', 'roles' => array( 'subscriber' ) ),
	11 => array( 'login' => 'nils', 'email' => 'nils@example.test', 'name' => 'Nils Mentor', 'roles' => array( WPCPM_Roles::ROLE_MENTOR ) ),
	12 => array( 'login' => 'pia', 'email' => 'pia@example.test', 'name' => 'Pia Program', 'roles' => array( WPCPM_Roles::ROLE_ADMIN ) ),
	13 => array( 'login' => 'eve', 'email' => 'eve@example.test', 'name' => 'Eve Editor', 'roles' => array( 'editor' ) ),
	14 => array( 'login' => 'ola', 'email' => 'ola@example.test', 'name' => 'Ola Office', 'roles' => array( WPCPM_Roles::ROLE_INSTITUTION ) ),
	15 => array( 'login' => 'frank', 'email' => 'frank@example.test', 'name' => 'Frank Former', 'roles' => array( 'subscriber' ) ),
	16 => array( 'login' => 'sam', 'email' => 'sam@example.test', 'name' => 'Sam Subscriber', 'roles' => array( 'subscriber' ) ),
	17 => array( 'login' => 'wes', 'email' => 'wes@example.test', 'name' => 'Wes Wrong', 'roles' => array( 'subscriber' ) ),
	18 => array( 'login' => 'tess', 'email' => 'tess@example.test', 'name' => 'Tess Student', 'roles' => array( WPCPM_Roles::ROLE_STUDENT ) ),
	19 => array( 'login' => 'uma', 'email' => 'uma@example.test', 'name' => 'Uma Student', 'roles' => array( WPCPM_Roles::ROLE_STUDENT, 'editor' ) ),
	// **Two roles, one of them adoptable, and no stamp at all.** This is what `add_role()`
	// leaves behind, which is how half the promotions on this site are made: a subscriber who
	// was later made an editor holds both. Without this account the role loop could return on
	// the first adoptable role it meets and every assertion would still pass, and the hole is
	// exactly the one the rule exists to close - an institution importing the address of a
	// colleague who happens to be an editor would have that account made a student on their
	// roster. A reviewer found it by mutating `continue` to `return` and watching the suite
	// stay green.
	20 => array( 'login' => 'zoe', 'email' => 'zoe@example.test', 'name' => 'Zoe Both', 'roles' => array( 'subscriber', 'editor' ) ),
);

$GLOBALS['umeta'][10][ WPCPM_Mentors_Sync::META_RECORD_ID ]                = 'recMENTOR00000001';
$GLOBALS['umeta'][14][ WPCPM_Institution_Members::META_RECORD_ID ]         = $other;
$GLOBALS['umeta'][15][ WPCPM_Institution_Members::META_RECORD_ID_WAS ]     = $inst;
// Wes's account is linked to a student record that is not the one this address arrived on,
// which is the conflict `provision_student()` has always refused. It is here so the older
// rule is pinned beside the new one rather than left to be broken quietly.
$GLOBALS['umeta'][17][ WPCPM_Students_Sync::META_RECORD_ID ]               = 'recOTHERSTUDENT01';
$GLOBALS['umeta'][18][ WPCPM_Students_Sync::META_RECORD_ID ]               = $reports['tess'];
$GLOBALS['umeta'][19][ WPCPM_Students_Sync::META_RECORD_ID ]               = $reports['uma'];

WPCPM_Students_Sync::start();
WPCPM_Students_Sync::run_tick( 60 );

$report = get_option( WPCPM_Students_Sync::OPT_REPORT );
$stats  = $report['stats'];
$roster = WPCPM_Roster_Index::rows( $inst );

echo "=== The run finished, and read what it was given ===\n";

ck( 'the working state is gone, so the run reached the end', isset( $GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ] ), false );
ck( 'with no error recorded', get_option( WPCPM_Students_Sync::OPT_ERROR, '' ), '' );
ck( 'twelve reports rows were read', $stats['students_seen'], 12 );
ck( 'and twelve Students rows', $stats['rows_read'], 12 );

echo "\n=== A mentor's address, imported as a student ===\n";

// The stamp alone, on a Subscriber account. This is the case the whole rule exists for: the
// role test would say nothing about it, and yesterday's code would have adopted it.
ck( 'the mentor keeps the roles they had, and gains no Student role', roles( 10 ), array( 'subscriber' ) );
ck( 'nothing was written on their account at all', meta_keys( 10 ), array( WPCPM_Mentors_Sync::META_RECORD_ID ) );
ck( 'so no student record claims them', meta( 10, WPCPM_Students_Sync::META_RECORD_ID ), '(absent)' );
ck( 'and no institution stamp opens the roster fence for them', meta( 10, WPCPM_Students_Sync::META_INSTITUTION ), '(absent)' );
ck( 'the run says whose account it is', has( notice_about( $report, 'Mira Mentor' ), "is a mentor's account" ), true );
ck( 'and says the account was left alone', has( notice_about( $report, 'Mira Mentor' ), 'left as it is' ), true );
// A second account for the same address would be the same person on the roster twice, which
// is the failure the refusal is supposed to prevent rather than move.
ck( 'no second account was created for the address', in_array( 'mira@example.test', $GLOBALS['inserted'], true ), false );
ck( 'and the imported Students row points at no account', $roster[ $rows['mira'] ]['user_id'], 0 );

// The role alone, with no stamp: a mentor account made by hand, or one whose sync never ran.
ck( 'a mentor by role only is refused as well', roles( 11 ), array( WPCPM_Roles::ROLE_MENTOR ) );
ck( 'with nothing written on the account', meta_keys( 11 ), array() );
ck( 'and the notice names the role it found', has( notice_about( $report, 'Nils Mentor' ), 'holds the "wpcpm_mentor" role' ), true );

echo "\n=== A program manager, and anyone else with a role of their own ===\n";

// A program manager holds CAP_MANAGE through the Administrator role (WPCPM_Roles::register()),
// so this is what importing a manager's address looks like.
ck( 'the program manager stays an administrator', roles( 12 ), array( WPCPM_Roles::ROLE_ADMIN ) );
ck( 'and is not made a student', meta_keys( 12 ), array() );
ck( 'the notice names the role', has( notice_about( $report, 'Pia Program' ), 'holds the "administrator" role' ), true );

ck( 'an editor is refused too', roles( 13 ), array( 'editor' ) );
ck( 'with nothing written on the account', meta_keys( 13 ), array() );
ck( 'and named as such', has( notice_about( $report, 'Eve Editor' ), 'holds the "editor" role' ), true );

// **One adoptable role beside a privileged one is still a refusal.** `add_role()` is how most
// promotions on this site are made, so a subscriber later made an editor holds both, and a
// rule that stopped at the first adoptable role would adopt them. Nothing else in this suite
// covers it: every other role-only account holds exactly one role, and the two that hold a
// second are caught by their stamps before the roles are read at all.
ck( 'a subscriber who is also an editor is refused', roles( 20 ), array( 'subscriber', 'editor' ) );
ck( 'with nothing written on the account', meta_keys( 20 ), array() );
ck( 'and the notice names the role that disqualified them', has( notice_about( $report, 'Zoe Both' ), 'holds the "editor" role' ), true );

echo "\n=== The institution's own people, present and past ===\n";

ck( 'a live member of another institution keeps their role', roles( 14 ), array( WPCPM_Roles::ROLE_INSTITUTION ) );
ck( 'and their membership, and nothing else', meta_keys( 14 ), array( WPCPM_Institution_Members::META_RECORD_ID ) );
ck( 'the notice says what they are', has( notice_about( $report, 'Ola Office' ), 'acts for an institution' ), true );

// The `_was` stamp is a Subscriber account with no role to give it away, so only the stamp
// half can catch it. A membership that has ended is still why the account exists.
ck( 'a former member is refused on the ended stamp alone', roles( 15 ), array( 'subscriber' ) );
ck( 'their history is untouched and no student stamp joins it', meta_keys( 15 ), array( WPCPM_Institution_Members::META_RECORD_ID_WAS ) );
ck( 'and the notice says the membership is in the past', has( notice_about( $report, 'Frank Former' ), 'acted for an institution before' ), true );

echo "\n=== What is still adopted, exactly as before ===\n";

// The one account the fallback is for: somebody who registered here and is now a student.
ck( 'a plain unstamped Subscriber is adopted', in_array( WPCPM_Roles::ROLE_STUDENT, roles( 16 ), true ), true );
ck( 'keeping the Subscriber role they already had', in_array( 'subscriber', roles( 16 ), true ), true );
ck( 'stamped with the record the address arrived on', meta( 16, WPCPM_Students_Sync::META_RECORD_ID ), $reports['sam'] );
ck( 'and with the institution the Students table names', meta( 16, WPCPM_Students_Sync::META_INSTITUTION ), $inst );
ck( 'their roster row points at the account', $roster[ $rows['sam'] ]['user_id'], 16 );

ck( 'a student found by their own stamp is refreshed', meta( 18, WPCPM_Students_Sync::META_INSTITUTION ), $inst );
ck( 'and keeps their roles', roles( 18 ), array( WPCPM_Roles::ROLE_STUDENT ) );

// Found by their own stamp and never asked to prove anything: a student who is also an editor
// here is still this record's student. Scoping the rule to the email path is what makes that
// true, and a rule applied to every match would have thrown her out of her own program.
ck( 'a student who also holds another role is still matched', meta( 19, WPCPM_Students_Sync::META_RECORD_ID ), $reports['uma'] );
ck( 'and keeps both roles', roles( 19 ), array( WPCPM_Roles::ROLE_STUDENT, 'editor' ) );
ck( 'with her institution stamp written', meta( 19, WPCPM_Students_Sync::META_INSTITUTION ), $inst );

ck( 'an address with no account at all still gets one', in_array( 'vic@example.test', $GLOBALS['inserted'], true ), true );
$vic = get_user_by( 'email', 'vic@example.test' );
ck( 'created as a Student', $vic->roles, array( WPCPM_Roles::ROLE_STUDENT ) );

echo "\n=== The count a manager reads ===\n";

// Seven refusals from the adoption rule (two mentors, a manager, an editor, a subscriber who
// is also an editor, a live institution member and a former one). Wes, whose account was
// stamped with a report record the run no longer fetches, used to be an eighth conflict on
// every run forever; the account now follows the row his address arrived on, and the notice
// says so. One number, because the reconciliation card asks one question: how many addresses
// could not be resolved to an account this run.
ck( 'seven conflicts were counted', $stats['conflicts'], 7 );
ck( 'a stale link to a record the run no longer sees is followed, and the notice explains it',
	has( notice_about( $report, 'Wes Wrong' ), 'no longer among the tracked records, so it now follows' ), true );
ck( 'and Wes follows the record his address arrived on', meta( 17, WPCPM_Students_Sync::META_RECORD_ID ), 'recR0000000000016' );
ck( 'one account was created', $stats['created'], 1 );
ck( 'one was adopted', $stats['linked'], 1 );
ck( 'three were refreshed, Wes among them', $stats['updated'], 3 );
// Refusals are not skips: `skipped` is for a row this sync could not use at all, and counting
// a conflict there would hide it from the number the manager screen prints.
ck( 'nothing was counted as skipped', $stats['skipped'], 0 );
ck( 'every notice names the person it is about', count( $report['notices'] ), 8 );

echo "\n=== The rules that are invisible at runtime ===\n";

$src   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php' );
$stamp = method_body( $src, 'foreign_stamps' );

// Named through the classes that write them, so `bin/check-references.php` fails on a rename
// instead of this fence quietly reading a key nothing writes any more.
ck( 'the mentor stamp is named through the mentors sync', has( $stamp, 'WPCPM_Mentors_Sync::META_RECORD_ID' ), true );
ck( 'both institution stamps through the members module',
	array( has( $stamp, 'WPCPM_Institution_Members::META_RECORD_ID ]' ), has( $stamp, 'WPCPM_Institution_Members::META_RECORD_ID_WAS' ) ),
	array( true, true ) );
ck( 'and no meta key is written here as a literal', preg_match( "/'wpcpm_[a-z_]*record_id/", $stamp ), 0 );

// The guard around the institutions module rests on the loader requiring every module. If
// that ever stops being true, the fence narrows in silence, so the assumption is pinned here
// rather than left in a comment.
ck( 'the loader requires the module the guard asks for',
	has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' ), 'modules/class-wpcpm-institution-members.php' ), true );

ck( 'the two adoptable roles are Student and Subscriber',
	WPCPM_Students_Sync::ADOPTABLE_ROLES, array( WPCPM_Roles::ROLE_STUDENT, 'subscriber' ) );
ck( 'the block is asked in one place', substr_count( $src, 'self::adoption_block(' ), 1 );

$dashes = array();

// The two methods this rule added, and every sentence it writes. Not `provision_student()`
// itself: the older "already linked to a different student record" notice in it carries an
// em dash, and rewriting a shipped translatable string is a different change from this one.
foreach ( array( 'adoption_block', 'foreign_stamps' ) as $name ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) method_body( $src, $name ) ) ) {
		$dashes[] = $name;
	}
}

foreach ( array( 'Mira Mentor', 'Nils Mentor', 'Ola Office', 'Frank Former' ) as $person ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', notice_about( $report, $person ) ) ) {
		$dashes[] = $person;
	}
}

if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __FILE__ ) ) ) {
	$dashes[] = 'this suite';
}

ck( 'no dash but the plain hyphen in the rule, its notices or this suite', $dashes, array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
