<?php
/**
 * The Students screen's account list: every Student account, however many the site has.
 *
 * What this pins: the Student accounts list shows every account with the Student role, the
 * heading counts them all, and the institution picker's count for a school is the school's
 * whole list. Before 1.117.1 the list asked WordPress for the first 500 accounts by display
 * name and filtered and counted from that page alone, so once the site passed 500 students the
 * accounts sorting after the cap were missing from the list and from their institution's count
 * with no trace: on the live site on 28 Sep 2026, 13 of 513 accounts, and one school's picker
 * read 13 where it had 14.
 *
 * `get_users()` is stubbed the way WordPress behaves for the three arguments the list passes:
 * `role` narrows, `orderby` `display_name` sorts, and a positive `number` is a LIMIT while -1
 * means every account. So a cap in the query is a cap in the list here too, which is what makes
 * the check below fail against the capped query and pass without it.
 *
 * Run from the plugin root:  php bin/test-students-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['users']   = array();
$GLOBALS['umeta']   = array();
$GLOBALS['opts']    = array();
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
	public function __construct( $id, $name, array $roles ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
		$this->user_email   = $this->user_login . '@example.test';
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
function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; }
function get_edit_user_link( $id ) { return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="n" />'; }
function selected( $a, $b = true, $echo = true ) { $out = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $out; } return $out; }
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other = null ) { echo '<button type="submit">' . esc_html( $text ) . '</button>'; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_user_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ( $single ? '' : array() );
}

/**
 * WordPress's `get_users()` for the arguments the list passes: `role` narrows to accounts
 * holding that role, `orderby` `display_name` sorts by name, and `number` is a LIMIT when it is
 * positive and no limit at all when it is -1 (or absent). Every query is recorded.
 */
function get_users( $args = array() ) {
	$GLOBALS['queries'][] = $args;
	$out                  = array();

	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) {
			continue;
		}
		$out[] = $user;
	}

	if ( isset( $args['orderby'] ) && 'display_name' === $args['orderby'] ) {
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a->display_name, $b->display_name );
			}
		);
		if ( isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ) {
			$out = array_reverse( $out );
		}
	}

	$number = isset( $args['number'] ) ? (int) $args['number'] : 0;

	if ( $number > 0 ) {
		$out = array_slice( $out, 0, $number );
	}

	return $out;
}

require_once __DIR__ . '/stubs/caps.php';

/** The student page's address, which the list prints once and links from every row. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-dashboard/'; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php';

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
 * The Student accounts card, as the screen draws it for the request in `$_GET`.
 *
 * @return string The card's markup.
 */
function draw_list() {
	$GLOBALS['queries'] = array();
	$method             = new ReflectionMethod( 'WPCPM_Students', 'render_student_list' );
	ob_start();
	$method->invoke( new WPCPM_Students() );
	return ob_get_clean();
}

/** One username cell per row, so the cells are the rows. */
function rows_in( $html ) {
	return substr_count( $html, '<td><code>' );
}

// The fixture: 501 Student accounts at one school, named so that display-name order is the
// numbering (Student 001 to Student 501), one more at another school, a mentor, and a former
// student whose role the sync took away. Every program row is complete, so `get_program()`'s
// heal-from-the-mentor path is never entered.
$school = 'Universidad de Ejemplo';
$next = 100;

function add_person( $name, array $roles, $institution ) {
	global $next;
	$id                       = $next++;
	$GLOBALS['users'][ $id ]  = new WP_User( $id, $name, $roles );
	$GLOBALS['umeta'][ $id ]  = array(
		WPCPM_Students_Sync::META_PROGRAM => array(
			'record_id'      => 'rec' . $id,
			'name'           => $name,
			'program'        => 'Developer Track',
			'is_past'        => false,
			'institution'    => $institution,
			'field_of_study' => 'Technology & Engineering',
			'accessibility'  => 'none',
		),
		WPCPM_Students_Sync::META_MENTOR  => array( 'name' => 'Mentor Example', 'record_id' => 'recMentor' ),
		'wpcpm_student_invited'           => 1,
	);
	return $id;
}

for ( $i = 1; $i <= 501; $i++ ) {
	add_person( sprintf( 'Student %03d', $i ), array( WPCPM_Roles::ROLE_STUDENT ), $school );
}
add_person( 'Zed Elsewhere', array( WPCPM_Roles::ROLE_STUDENT ), 'Another School' );
add_person( 'Mentor Example', array( WPCPM_Roles::ROLE_MENTOR ), $school );
add_person( 'Former Student', array( 'subscriber' ), $school );

echo "=== Every Student account, with no institution chosen ===\n";

$_GET = array();
$html = draw_list();

ck( 'the list has one row per Student account: 501 at the school and one elsewhere', rows_in( $html ), 502 );
ck( 'the 501st account by name is on it', false !== strpos( $html, 'Student 501' ), true );
ck( 'and the heading counts them all', false !== strpos( $html, '<span class="wpcpm-count">502</span>' ), true );
ck( 'the picker counts the whole school', false !== strpos( $html, esc_html( $school ) . ' (501)' ), true );
ck( 'the mentor is not on the list', false === strpos( $html, 'Mentor Example</a>' ), true );
ck( 'nor the former student', false === strpos( $html, 'Former Student' ), true );
ck( 'the list asked WordPress for every Student account, not a first page', isset( $GLOBALS['queries'][0]['number'] ) ? (int) $GLOBALS['queries'][0]['number'] : -1, -1 );

echo "\n=== The same list narrowed to the school ===\n";

$_GET = array( 'wpcpm_institution' => $school );
$html = draw_list();

ck( 'every one of the school\'s 501 students is on the list', rows_in( $html ), 501 );
ck( 'including the 501st by name', false !== strpos( $html, 'Student 501' ), true );
ck( 'the heading counts 501', false !== strpos( $html, '<span class="wpcpm-count">501</span>' ), true );
ck( 'the student from the other school is not', false === strpos( $html, 'Zed Elsewhere' ), true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

exit( $fail ? 1 : 0 );
