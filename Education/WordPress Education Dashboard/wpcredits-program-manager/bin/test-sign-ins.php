<?php
/**
 * When an account last signed in, and last used the site (1.122.26).
 *
 * What this pins, and why:
 *
 * - A sign-in is recorded when it completes. The Two Factor plugin (0.16.0) hooks `wp_login` at
 *   PHP_INT_MAX and exits there for an account that owes a code, so every other `wp_login`
 *   callback runs after the password and before the code: an account that owes one is recorded on
 *   `two_factor_user_authenticated`, never on `wp_login`, and an abandoned code step records nothing.
 * - One request records a sign-in once, so a later Two Factor that fired `wp_login` again after the
 *   code would still write one value.
 * - A visit is the first signed-in page view of the site's day: one write a day, the day read in the
 *   site's time zone, none for AJAX (the heartbeat), cron or REST.
 *
 * Run from the plugin root:  php bin/test-sign-ins.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['umeta']  = array();
$GLOBALS['opts']   = array();
$GLOBALS['hooks']  = array();
$GLOBALS['uid']    = 0;
$GLOBALS['ajax']   = false;
$GLOBALS['cron']   = false;
$GLOBALS['offset'] = 0;
$GLOBALS['using']  = array();
$GLOBALS['can']    = false;
$GLOBALS['writes'] = 0;

class WP_User {
	public $ID = 0;
	public function __construct( $id = 0 ) { $this->ID = (int) $id; }
}

function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, is_array( $cb ) ? $cb[1] : (string) $cb, $priority, $args ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { ++$GLOBALS['writes']; $GLOBALS['umeta'][ $id ][ $key ] = $value; return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function wp_doing_cron() { return $GLOBALS['cron']; }
// The site's clock: UTC plus the site's offset, as core's wp_date() reads the site's time zone.
function wp_date( $format, $timestamp = null ) { return gmdate( $format, (int) $timestamp + $GLOBALS['offset'] ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function current_user_can( $cap ) { return $GLOBALS['can']; }
$GLOBALS['accounts']  = array();
$GLOBALS['batches']   = array();
$GLOBALS['primed']    = array();
function get_users( $args = array() ) {
	$GLOBALS['batches'][] = $args;
	$ids = $GLOBALS['accounts'];
	sort( $ids );
	// As this program's site answers: `fields => ID` hands back row objects, not integers (see
	// WPCPM_Roles::id_of()).
	return array_map( function ( $id ) { return (object) array( 'ID' => $id ); }, array_slice( $ids, ( $args['paged'] - 1 ) * $args['number'], $args['number'] ) );
}
function update_meta_cache( $type, $ids ) { $GLOBALS['primed'][] = array( $type, count( $ids ) ); return array(); }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-two-factor.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}
function meta( $id, $key ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : null; }
function fresh() {
	$GLOBALS['umeta']  = array();
	$GLOBALS['writes'] = 0;
	WPCPM_Sign_Ins::for_tests();
}

$S = WPCPM_Sign_Ins::META_SIGN_IN;
$V = WPCPM_Sign_Ins::META_VISIT;
$T = gmmktime( 10, 0, 0, 10, 15, 2026 );

echo "=== The hooks ===\n";

WPCPM_Sign_Ins::init();
ck( 'a sign-in is heard on wp_login with both arguments and on two_factor_user_authenticated, a visit on template_redirect and admin_init',
	array_values( array_filter( $GLOBALS['hooks'], function ( $h ) { return 'maybe_seed' !== $h[1]; } ) ),
	array(
		array( 'wp_login', 'on_wp_login', 10, 2 ),
		array( 'two_factor_user_authenticated', 'on_two_factor', 10, 1 ),
		array( 'template_redirect', 'on_visit', 10, 1 ),
		array( 'admin_init', 'on_visit', 10, 1 ),
	) );

echo "\n=== Without the Two Factor plugin ===\n";

fresh();
$before = time();
WPCPM_Sign_Ins::on_wp_login( 'mentor', new WP_User( 30 ) );
ck( 'wp_login records the sign-in as an int, now', is_int( meta( 30, $S ) ) && meta( 30, $S ) >= $before && meta( 30, $S ) <= time() );
ck( 'and the visit with it, at the same moment', meta( 30, $V ), meta( 30, $S ) );

fresh();
WPCPM_Sign_Ins::record_sign_in( 30, $T );
WPCPM_Sign_Ins::record_sign_in( 30, $T + 60 );
ck( 'one request records a sign-in once: two writes, the sign-in and the visit', array( $GLOBALS['writes'], meta( 30, $S ) ), array( 2, $T ) );

fresh();
WPCPM_Sign_Ins::record_sign_in( 0, $T );
WPCPM_Sign_Ins::on_wp_login( 'nobody', 'not a user' );
ck( 'no account, nothing written', $GLOBALS['writes'], 0 );

echo "\n=== With the Two Factor plugin ===\n";

// Declared here, not at the top: PHP hoists class declarations, and the section above must run
// without it (the same reason bin/test-two-factor.php declares its stand-in with eval).
eval(
	'class Two_Factor_Core {
		public static function is_user_using_two_factor( $user_id ) {
			return ! empty( $GLOBALS["using"][ (int) $user_id ] );
		}
	}'
);

$GLOBALS['using'][40] = true;
fresh();
WPCPM_Sign_Ins::on_wp_login( 'owes-a-code', new WP_User( 40 ) );
ck( 'an account that owes a code records nothing on wp_login: the code step has not happened', array( meta( 40, $S ), meta( 40, $V ) ), array( null, null ) );
WPCPM_Sign_Ins::on_two_factor( new WP_User( 40 ) );
ck( 'and is recorded once the code is accepted', array( is_int( meta( 40, $S ) ), meta( 40, $V ) === meta( 40, $S ) ), array( true, true ) );
$writes = $GLOBALS['writes'];
WPCPM_Sign_Ins::on_wp_login( 'owes-a-code', new WP_User( 40 ) );
ck( 'a wp_login fired again after the code in the same request writes nothing more', $GLOBALS['writes'], $writes );

fresh();
WPCPM_Sign_Ins::on_wp_login( 'no-code', new WP_User( 41 ) );
ck( 'an account that uses no second factor is recorded on wp_login', is_int( meta( 41, $S ) ) );

fresh();
WPCPM_Sign_Ins::on_two_factor( 'not a user' );
ck( 'two_factor_user_authenticated without an account writes nothing', $GLOBALS['writes'], 0 );

echo "\n=== Visits ===\n";

fresh();
WPCPM_Sign_Ins::record_visit( 30, $T );
WPCPM_Sign_Ins::record_visit( 30, $T + 3600 );
ck( 'the first view of the day is kept: a later one that day writes nothing', array( meta( 30, $V ), $GLOBALS['writes'] ), array( $T, 1 ) );
WPCPM_Sign_Ins::record_visit( 30, $T + DAY_IN_SECONDS );
ck( 'the next day writes the next day', meta( 30, $V ), $T + DAY_IN_SECONDS );

// Review Focus 4: the day is the site's day.
$GLOBALS['offset'] = 2 * 3600;
fresh();
$late  = gmmktime( 21, 30, 0, 10, 15, 2026 ); // 23:30 site time.
$early = gmmktime( 22, 30, 0, 10, 15, 2026 ); // 00:30 site time, the 16th; the same UTC day.
WPCPM_Sign_Ins::record_visit( 30, $late );
WPCPM_Sign_Ins::record_visit( 30, $early );
ck( 'two site days on one UTC day are two writes', array( $GLOBALS['writes'], meta( 30, $V ) ), array( 2, $early ) );

$GLOBALS['offset'] = -5 * 3600;
fresh();
WPCPM_Sign_Ins::record_visit( 30, gmmktime( 23, 0, 0, 10, 15, 2026 ) ); // 18:00 site time, the 15th; UTC the 15th.
WPCPM_Sign_Ins::record_visit( 30, gmmktime( 1, 0, 0, 10, 16, 2026 ) );  // 20:00 site time, the 15th; UTC the 16th.
ck( 'one site day across UTC midnight is one write', $GLOBALS['writes'], 1 );
$GLOBALS['offset'] = 0;

fresh();
WPCPM_Sign_Ins::record_visit( 30, $T );
WPCPM_Sign_Ins::record_sign_in( 30, $T + 600 );
ck( 'a sign-in on a day already visited keeps the first view and records the sign-in', array( meta( 30, $V ), meta( 30, $S ) ), array( $T, $T + 600 ) );

fresh();
$GLOBALS['umeta'][30][ $V ] = 'not a time';
WPCPM_Sign_Ins::record_visit( 30, $T );
ck( 'a stored value that is not a time is replaced, not trusted', meta( 30, $V ), $T );

echo "\n=== A stored time only moves forward ===\n";

// Review finding G (not `$writes`: at the top level that name is the $GLOBALS['writes'] counter itself): a time earlier than the stored one must never replace it.
fresh();
WPCPM_Sign_Ins::record_sign_in( 30, $T );
WPCPM_Sign_Ins::for_tests();
$w0 = $GLOBALS['writes'];
WPCPM_Sign_Ins::record_sign_in( 30, $T - 3 * DAY_IN_SECONDS );
ck( 'an older sign-in after a newer one writes nothing: the sign-in and the visit both keep their time', array( $GLOBALS['writes'], meta( 30, $S ), meta( 30, $V ) ), array( $w0, $T, $T ) );

WPCPM_Sign_Ins::for_tests();
WPCPM_Sign_Ins::record_sign_in( 30, $T );
ck( 'the same time again writes nothing either', array( $GLOBALS['writes'], meta( 30, $S ) ), array( $w0, $T ) );

WPCPM_Sign_Ins::for_tests();
WPCPM_Sign_Ins::record_sign_in( 30, $T + 2 * DAY_IN_SECONDS );
ck( 'a later sign-in is written, with its visit', array( meta( 30, $S ), meta( 30, $V ) ), array( $T + 2 * DAY_IN_SECONDS, $T + 2 * DAY_IN_SECONDS ) );

fresh();
$GLOBALS['umeta'][30][ $S ] = 'not a time';
WPCPM_Sign_Ins::record_sign_in( 30, $T );
ck( 'a stored sign-in that is not a time is replaced, not trusted', meta( 30, $S ), $T );

fresh();
WPCPM_Sign_Ins::record_visit( 30, $T );
$w0 = $GLOBALS['writes'];
WPCPM_Sign_Ins::record_visit( 30, $T - 3 * DAY_IN_SECONDS );
ck( 'an older visit on another day, after a newer one, writes nothing', array( $GLOBALS['writes'], meta( 30, $V ) ), array( $w0, $T ) );

echo "\n=== Which requests are visits ===\n";

fresh();
$GLOBALS['uid'] = 0;
WPCPM_Sign_Ins::on_visit();
ck( 'nobody signed in, nothing written', $GLOBALS['writes'], 0 );

$GLOBALS['uid']  = 30;
$GLOBALS['ajax'] = true;
WPCPM_Sign_Ins::on_visit();
ck( 'an AJAX request is not a visit (the wp-admin heartbeat)', $GLOBALS['writes'], 0 );
$GLOBALS['ajax'] = false;

$GLOBALS['cron'] = true;
WPCPM_Sign_Ins::on_visit();
ck( 'a cron request is not a visit', $GLOBALS['writes'], 0 );
$GLOBALS['cron'] = false;

WPCPM_Sign_Ins::on_visit();
ck( 'a signed-in page view is', is_int( meta( 30, $V ) ) );
WPCPM_Sign_Ins::on_visit();
ck( 'and a second one that day writes nothing more', $GLOBALS['writes'], 1 );

echo "\n=== The seed: what the site already held ===\n";

$tok = 'session_tokens';
$app = '_two_factor_totp_last_successful_login';
$code = '_two_factor_email_token_timestamp';

fresh();
$GLOBALS['umeta'][50] = array( $tok => array( 'h1' => array( 'login' => 100, 'expiration' => 999 ), 'h2' => array( 'login' => 300, 'expiration' => 999 ) ), $app => 200, $code => '250' );
ck( 'the latest of the session sign-ins, the last app code and the last emailed code', WPCPM_Sign_Ins::latest_trace( 50 ), 300 );
$GLOBALS['umeta'][51] = array( $app => '400', $code => 350 );
ck( 'with no session, the codes', WPCPM_Sign_Ins::latest_trace( 51 ), 400 );
// Review Focus 3.
$GLOBALS['umeta'][52] = array( $tok => 'garbage', $app => 'not a time', $code => 120 );
ck( 'a session list that is not a list and a stamp that is not a number read as 0', WPCPM_Sign_Ins::latest_trace( 52 ), 120 );
$GLOBALS['umeta'][53] = array( $tok => array( 'h' => array( 'expiration' => 999 ), 'x' => 'bad' ) );
ck( 'a session without a sign-in time, or not a session at all, reads as 0', WPCPM_Sign_Ins::latest_trace( 53 ), 0 );
ck( 'an account with nothing reads 0', WPCPM_Sign_Ins::latest_trace( 54 ), 0 );

fresh();
$GLOBALS['batches'] = array();
$GLOBALS['primed']  = array();
$GLOBALS['accounts'] = range( 1, 450 );
$GLOBALS['umeta'][10] = array( $S => 500, $app => 900 );
$GLOBALS['umeta'][11] = array( $app => 900 );
$GLOBALS['umeta'][12] = array( $code => 700 );
ck( 'the seed gives a sign-in to the two accounts that had a trace and none', WPCPM_Sign_Ins::seed(), 2 );
ck( 'an account that already had a sign-in keeps it, even with a later trace', meta( 10, $S ), 500 );
ck( 'the others take their latest trace, as an int', array( meta( 11, $S ), meta( 12, $S ) ), array( 900, 700 ) );
ck( 'an account with no trace stays empty, and nobody is given a visit', array( meta( 13, $S ), meta( 11, $V ), meta( 12, $V ) ), array( null, null, null ) );
ck( 'the accounts are read 200 at a time, by ID, three pages for 450', array_map( function ( $a ) { return array( $a['number'], $a['paged'], $a['orderby'], $a['fields'] ); }, $GLOBALS['batches'] ), array( array( 200, 1, 'ID', 'ID' ), array( 200, 2, 'ID', 'ID' ), array( 200, 3, 'ID', 'ID' ) ) );
ck( 'each page\'s meta is read in one go', $GLOBALS['primed'], array( array( 'user', 200 ), array( 'user', 200 ), array( 'user', 50 ) ) );

echo "\n=== The seed runs once, for an Administrator ===\n";

fresh();
$GLOBALS['opts']     = array();
$GLOBALS['batches']  = array();
$GLOBALS['accounts'] = array( 11 );
$GLOBALS['umeta'][11] = array( $app => 900 );
$GLOBALS['can'] = false;
WPCPM_Sign_Ins::maybe_seed();
ck( 'not for someone who does not manage the program', array( $GLOBALS['batches'], get_option( WPCPM_Sign_Ins::OPT_VERSION ) ), array( array(), false ) );
$GLOBALS['can'] = true;
WPCPM_Sign_Ins::maybe_seed();
ck( 'for an Administrator: the version and the time are written, and the seed ran', array( get_option( WPCPM_Sign_Ins::OPT_VERSION ), is_int( get_option( WPCPM_Sign_Ins::OPT_SINCE ) ), meta( 11, $S ) ), array( 1, true, 900 ) );
ck( 'since() reads the time', WPCPM_Sign_Ins::since(), get_option( WPCPM_Sign_Ins::OPT_SINCE ) );
$GLOBALS['batches'] = array();
WPCPM_Sign_Ins::maybe_seed();
ck( 'and never again', $GLOBALS['batches'], array() );
ck( 'init() hooks it on admin_init', in_array( array( 'admin_init', 'maybe_seed', 10, 1 ), $GLOBALS['hooks'], true ) );

echo "\n=== Whether an account has signed in at all ===\n";

// The Institution Dashboard asks this one question of the record, for a student's card, and never
// reads the time itself.
fresh();
$GLOBALS['umeta'][40] = array( $S => $T );
$GLOBALS['umeta'][41] = array( $S => (string) $T );
$GLOBALS['umeta'][42] = array();
$GLOBALS['umeta'][43] = array( $S => 0 );
$GLOBALS['umeta'][44] = array( $S => 'yesterday' );
$GLOBALS['umeta'][45] = array( $V => $T );
ck( 'a recorded sign-in, as an int or as the string user meta comes back as, is a sign-in',
	array( WPCPM_Sign_Ins::has_signed_in( 40 ), WPCPM_Sign_Ins::has_signed_in( 41 ) ), array( true, true ) );
ck( 'no key, a zero or a word is never signed in',
	array( WPCPM_Sign_Ins::has_signed_in( 42 ), WPCPM_Sign_Ins::has_signed_in( 43 ), WPCPM_Sign_Ins::has_signed_in( 44 ) ), array( false, false, false ) );
ck( 'a visit alone is not a sign-in', WPCPM_Sign_Ins::has_signed_in( 45 ), false );
ck( 'and asking writes nothing', $GLOBALS['writes'], 0 );

// Last, because a constant cannot be taken back.
fresh();
define( 'REST_REQUEST', true );
WPCPM_Sign_Ins::on_visit();
ck( 'a REST request is not a visit', $GLOBALS['writes'], 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
