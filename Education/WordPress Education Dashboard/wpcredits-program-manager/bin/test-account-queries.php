<?php
/**
 * The four account queries that fed a dropdown, a list or a mailing from a first page.
 *
 * What this pins: each of these asks WordPress for every matching account, however many the
 * site has. Until 1.117.3 each carried a cap the way the Students and Mentors screens did until
 * 1.117.1 and 1.117.2 (where the first hid 13 of 513 accounts once the site passed 500
 * students): the Administrators screen's list (200), `WPCPM_Institutions::managers()` (200, the
 * accounts a notification reaches and the reviewer choices on Settings), the mentor switcher and
 * the call calendar's fallback through `WPCPM_Mentors_Dashboard::all_mentors()` (500 twice, the
 * role and the linked accounts), and the student switcher through
 * `WPCPM_Students_Dashboard::all_students()` (1000). None was near its cap on 28 Sep 2026 (12,
 * 12, 98 and 683); they are lifted before one can bite, with the same check the two screens have.
 *
 * `get_users()` is stubbed the way WordPress behaves for the arguments these pass: `role` and
 * `capability` narrow, a `meta_query` with `EXISTS` narrows to accounts carrying the key,
 * `orderby` sorts by display name or ID, and a positive `number` is a LIMIT while -1 means every
 * account. So a cap in a query is a cap in its answer here too, which is what makes each check
 * fail against the capped query and pass without it. `user_can()` answers from a list of IDs.
 *
 * Run from the plugin root:  php bin/test-account-queries.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['users']   = array();
$GLOBALS['umeta']   = array();
$GLOBALS['opts']    = array();
$GLOBALS['manage']  = array();
$GLOBALS['queries'] = array();
$fail               = 0;

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}

class WP_User {
	public $ID = 0, $display_name = '', $user_login = '', $user_email = '', $roles = array();
	public function __construct( $id, $name, array $roles, $email = null ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
		$this->user_email   = null === $email ? $this->user_login . '@example.test' : $email;
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_edit_user_link( $id ) { return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_user_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ( $single ? '' : array() );
}
/**
 * WordPress's `get_users()` for the arguments the four queries pass. Every query is recorded.
 * `user_can()` is the shared stand-in from stubs/caps.php, reading `$GLOBALS['manage']` as a
 * list of the managers' IDs.
 */
function get_users( $args = array() ) {
	$GLOBALS['queries'][] = $args;
	$out                  = array();

	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) {
			continue;
		}
		if ( isset( $args['capability'] ) && ! user_can( $user, $args['capability'] ) ) {
			continue;
		}
		if ( ! empty( $args['meta_query'] ) ) {
			foreach ( $args['meta_query'] as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
					continue;
				}
				$has = isset( $GLOBALS['umeta'][ $user->ID ][ $clause['key'] ] );
				if ( 'EXISTS' === ( $clause['compare'] ?? '=' ) && ! $has ) {
					continue 2;
				}
			}
		}
		$out[] = $user;
	}

	$orderby = $args['orderby'] ?? 'login';
	usort(
		$out,
		static function ( $a, $b ) use ( $orderby ) {
			if ( 'display_name' === $orderby ) {
				return strcmp( $a->display_name, $b->display_name );
			}
			if ( 'ID' === $orderby ) {
				return $a->ID <=> $b->ID;
			}
			return strcmp( $a->user_login, $b->user_login );
		}
	);
	if ( isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ) {
		$out = array_reverse( $out );
	}

	$number = isset( $args['number'] ) ? (int) $args['number'] : 0;

	if ( $number > 0 ) {
		$out = array_slice( $out, 0, $number );
	}

	return $out;
}

require_once __DIR__ . '/stubs/caps.php';

/** The meta key each dashboard's query looks for; the sync classes that own them are not loaded here. */
class WPCPM_Mentors_Sync {
	const META_RECORD_ID = 'wpcpm_mentor_record_id';
}
class WPCPM_Students_Sync {
	const META_RECORD_ID = 'wpcpm_student_record_id';
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php';

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

/** The `number` every query recorded since the last reset asked for, as a list. */
function numbers_asked() {
	$asked = array();
	foreach ( $GLOBALS['queries'] as $q ) {
		$asked[] = isset( $q['number'] ) ? (int) $q['number'] : -1;
	}
	return $asked;
}

$next = 100;

function add_person( $name, array $roles, array $meta = array(), $email = null ) {
	global $next;
	$id                      = $next++;
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, $roles, $email );
	$GLOBALS['umeta'][ $id ] = $meta;
	return $id;
}

echo "=== The Administrators screen lists every administrator ===\n";

$GLOBALS['users'] = array();
$GLOBALS['umeta'] = array();
for ( $i = 1; $i <= 201; $i++ ) {
	add_person( sprintf( 'Admin %03d', $i ), array( WPCPM_Roles::ROLE_ADMIN ) );
}
add_person( 'Mentor Example', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['queries'] = array();
ob_start();
( new WPCPM_Administrators() )->render_admin_page();
$html = ob_get_clean();

ck( 'one row per administrator, all 201', substr_count( $html, '<td><code>' ), 201 );
ck( 'the 201st by name is on it', false !== strpos( $html, 'Admin 201' ), true );
ck( 'and the heading counts them all', false !== strpos( $html, '<span class="wpcpm-count">201</span>' ), true );
ck( 'the mentor is not on it', false === strpos( $html, 'Mentor Example' ), true );
ck( 'the screen asked for every account, not a first page', numbers_asked(), array( -1 ) );

echo "\n=== managers() reaches every manager with an address ===\n";

$GLOBALS['users']  = array();
$GLOBALS['umeta']  = array();
$GLOBALS['manage'] = array();
for ( $i = 1; $i <= 201; $i++ ) {
	$GLOBALS['manage'][] = add_person( sprintf( 'Manager %03d', $i ), array( WPCPM_Roles::ROLE_ADMIN ) );
}
$GLOBALS['manage'][] = add_person( 'No Address', array( WPCPM_Roles::ROLE_ADMIN ), array(), '' );
add_person( 'Plain Editor', array( 'editor' ) );
$GLOBALS['queries'] = array();
$managers           = WPCPM_Institutions::managers();

ck( 'every one of the 201 managers with an address, and nobody else', count( $managers ), 201 );
ck( 'the 201st by ID among them', in_array( 'Manager 201', array_map( static function ( $u ) { return $u->display_name; }, $managers ), true ), true );
ck( 'the query asked for every account, not a first page', numbers_asked(), array( -1 ) );

echo "\n=== all_mentors() has every mentor, by role and by link, once each ===\n";

$GLOBALS['users'] = array();
$GLOBALS['umeta'] = array();
for ( $i = 1; $i <= 501; $i++ ) {
	// Every third mentor also carries the record link, as the sync leaves it: counted once.
	add_person( sprintf( 'Mentor %03d', $i ), array( WPCPM_Roles::ROLE_MENTOR ), 0 === $i % 3 ? array( WPCPM_Mentors_Sync::META_RECORD_ID => 'rec' . $i ) : array() );
}
for ( $i = 1; $i <= 501; $i++ ) {
	// Linked accounts without the role: administrators who mentor, whose roles the sync leaves alone.
	add_person( sprintf( 'Linked %03d', $i ), array( WPCPM_Roles::ROLE_ADMIN ), array( WPCPM_Mentors_Sync::META_RECORD_ID => 'recL' . $i ) );
}
add_person( 'Student Example', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['queries'] = array();
$all                = WPCPM_Mentors_Dashboard::all_mentors();
$names              = array_map( static function ( $u ) { return $u->display_name; }, $all );

ck( 'all 501 by role and all 501 by link, each once', count( $all ), 1002 );
ck( 'the 501st mentor by name is there', in_array( 'Mentor 501', $names, true ), true );
ck( 'and the 501st linked account', in_array( 'Linked 501', $names, true ), true );
ck( 'nobody twice', count( array_unique( array_map( static function ( $u ) { return $u->ID; }, $all ) ) ), 1002 );
ck( 'the student is not among them', in_array( 'Student Example', $names, true ), false );
ck( 'both queries asked for every account, not a first page', numbers_asked(), array( -1, -1 ) );

echo "\n=== all_students() has every account with program details ===\n";

$GLOBALS['users'] = array();
$GLOBALS['umeta'] = array();
for ( $i = 1; $i <= 1001; $i++ ) {
	// A former student keeps the record but not the role; the switcher still offers them.
	add_person( sprintf( 'Student %04d', $i ), array( 0 === $i % 10 ? 'subscriber' : WPCPM_Roles::ROLE_STUDENT ), array( WPCPM_Students_Sync::META_RECORD_ID => 'rec' . $i ) );
}
add_person( 'Mentor Example', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['queries'] = array();
$method             = new ReflectionMethod( 'WPCPM_Students_Dashboard', 'all_students' );

// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
if ( PHP_VERSION_ID < 80100 ) {
	$method->setAccessible( true );
}

$students = $method->invoke( null );
$names    = array_map( static function ( $u ) { return $u->display_name; }, $students );

ck( 'all 1001 accounts with a record', count( $students ), 1001 );
ck( 'the 1001st by name is there', in_array( 'Student 1001', $names, true ), true );
ck( 'the mentor without a record is not', in_array( 'Mentor Example', $names, true ), false );
ck( 'the query asked for every account, not a first page', numbers_asked(), array( -1 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

exit( $fail ? 1 : 0 );
