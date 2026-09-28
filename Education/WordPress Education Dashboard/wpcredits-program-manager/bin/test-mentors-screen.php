<?php
/**
 * The Mentors screen's account list: every Mentor account, however many the site has.
 *
 * What this pins: the Mentor accounts list shows every account with the Mentor role and the
 * heading counts them all. Until 1.117.2 the list asked WordPress for the first 500 accounts by
 * display name, the same cap the Students screen carried until 1.117.1, where it hid 13 of 513
 * accounts once the site passed 500 students. The mentors are far fewer today, so the cap has
 * never bitten this screen; it is lifted before it can, with the same check the Students screen
 * has (bin/test-students-screen.php).
 *
 * `get_users()` is stubbed the way WordPress behaves for the three arguments the list passes:
 * `role` narrows, `orderby` `display_name` sorts, and a positive `number` is a LIMIT while -1
 * means every account. So a cap in the query is a cap in the list here too, which is what makes
 * the check below fail against the capped query and pass without it.
 *
 * It also pins that the invitations card the screen draws is handed the flash channel the screen
 * reads its outcomes from, which the card's Stop names, so "Sending stopped." prints on this screen
 * after a Stop pressed here (the card itself is pinned in bin/test-students-screen.php).
 *
 * Run from the plugin root:  php bin/test-mentors-screen.php
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
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true ) { echo '<input type="submit" value="' . esc_attr( $text ) . '" />'; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="n" />'; }
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

/** The two meta keys the list reads off a mentor's account; the sync class that owns them is not loaded here. */
class WPCPM_Mentors_Sync {
	const META_ACTIVE     = 'wpcpm_mentor_active';
	const META_PAST_COUNT = 'wpcpm_mentee_past_count';
}

/** The invitations card, as far as the screen hands it anything: what it was given, and nobody to invite. */
class WPCPM_Mail {
	public static $card = array();
	public static function never_invited( $role, $meta ) { return array(); }
	public static function render_invite_card( array $args ) { self::$card = $args; }
}

/** The mentor page's address and a mentor's student count, which the list prints per row. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-dashboard/'; }
	public static function get_mentee_count( $id ) { return (int) get_user_meta( $id, 'wpcpm_mentee_count', true ); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php';

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
 * The Mentor accounts card, as the screen draws it.
 *
 * @return string The card's markup.
 */
function draw_list() {
	$GLOBALS['queries'] = array();
	$method             = new ReflectionMethod( 'WPCPM_Mentors', 'render_mentor_list' );

	// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	ob_start();
	$method->invoke( new WPCPM_Mentors() );
	return ob_get_clean();
}

/** One username cell per row, so the cells are the rows. */
function rows_in( $html ) {
	return substr_count( $html, '<td><code>' );
}

// The fixture: 501 Mentor accounts named so that display-name order is the numbering (Mentor 001
// to Mentor 501), a student, and an administrator who is nobody's mentor.
$next = 100;

function add_person( $name, array $roles, $mentees = 0 ) {
	global $next;
	$id                      = $next++;
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, $roles );
	$GLOBALS['umeta'][ $id ] = array(
		WPCPM_Mentors_Sync::META_ACTIVE     => 1,
		WPCPM_Mentors_Sync::META_PAST_COUNT => 0,
		'wpcpm_mentee_count'                => $mentees,
		'wpcpm_mentor_invited'              => 1,
	);
	return $id;
}

for ( $i = 1; $i <= 501; $i++ ) {
	add_person( sprintf( 'Mentor %03d', $i ), array( WPCPM_Roles::ROLE_MENTOR ), $i % 4 );
}
add_person( 'Student Example', array( WPCPM_Roles::ROLE_STUDENT ) );
add_person( 'Manager Example', array( 'administrator' ) );

echo "=== Every Mentor account ===\n";

$html = draw_list();

ck( 'the list has one row per Mentor account', rows_in( $html ), 501 );
ck( 'the 501st account by name is on it', false !== strpos( $html, 'Mentor 501' ), true );
ck( 'and the heading counts them all', false !== strpos( $html, '<span class="wpcpm-count">501</span>' ), true );
ck( 'the student is not on the list', false === strpos( $html, 'Student Example' ), true );
ck( 'nor the administrator', false === strpos( $html, 'Manager Example' ), true );
ck( 'the list asked WordPress for every Mentor account, not a first page', isset( $GLOBALS['queries'][0]['number'] ) ? (int) $GLOBALS['queries'][0]['number'] : -1, -1 );

echo "\n=== The invitations card's Stop comes back to this screen's own notices ===\n";

$mentors = new WPCPM_Mentors();
$panel   = new ReflectionMethod( 'WPCPM_Mentors', 'render_sync_panel' );
$channel = new ReflectionMethod( 'WPCPM_Mentors', 'flash_key' );

// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
if ( PHP_VERSION_ID < 80100 ) {
	$panel->setAccessible( true );
	$channel->setAccessible( true );
}

ob_start();
$panel->invoke( $mentors, array( 'running' => false ), 0 );
ob_end_clean();

ck( 'the card is handed the channel the Mentors screen reads its outcomes from, for its Stop to name',
	array( isset( WPCPM_Mail::$card['flash'] ) ? WPCPM_Mail::$card['flash'] : 'no channel handed', $channel->invoke( $mentors ) ),
	array( 'mentors_admin', 'mentors_admin' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

exit( $fail ? 1 : 0 );
