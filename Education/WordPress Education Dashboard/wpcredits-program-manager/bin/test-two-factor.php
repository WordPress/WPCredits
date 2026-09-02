<?php
/**
 * Who is asked for a second factor, and what they are asked for.
 *
 * The Two Factor plugin decides whether to demand a second step by asking whether the account
 * has any provider enabled, and the list it reads runs through a filter first. This suite pins
 * the consequence: an account in an enforced role is asked for a code at its very next login,
 * with nothing set up in advance and no window in which a stolen password is enough on its own.
 *
 * The negative assertions matter as much as the positive ones. A student is not enforced, an
 * account that already chose its own providers is not overridden, and nothing at all happens
 * when the plugin is absent, because a policy that throws when its plugin is deactivated would
 * take the site down rather than merely stop protecting it.
 *
 * Run from the plugin root:  php bin/test-two-factor.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array();
$GLOBALS['users']    = array();
$GLOBALS['enabled']  = array();
$GLOBALS['roles']    = array( 'administrator', 'wpcpm_institution', 'wpcpm_mentor', 'wpcpm_student', 'subscriber' );
$GLOBALS['filters']  = array();
$GLOBALS['printed']  = '';
$GLOBALS['uid']      = 0;

class WP_Error {
	public function __construct( $c = '', $m = '', $d = null ) {}
	public function get_error_message() { return ''; }
}
class WP_Role {
	public $name;
	public function __construct( $name ) { $this->name = $name; }
}
class WP_User {
	public $ID = 0;
	public $roles = array();
	public $user_email = '';
	public function __construct( $id = 0, array $roles = array() ) {
		$this->ID    = $id;
		$this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_attr( $s ) { return esc_html( $s ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function wp_unslash( $v ) { return $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_role( $slug ) { return in_array( $slug, $GLOBALS['roles'], true ) ? new WP_Role( $slug ) : null; }
function wp_roles() {
	return new class() {
		public function get_names() {
			return array( 'administrator' => 'Administrator', 'wpcpm_institution' => 'Institution', 'wpcpm_mentor' => 'Mentor', 'wpcpm_student' => 'Student' );
		}
	};
}
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $u ) {
		if ( isset( $args['role'] ) && ! in_array( $args['role'], $u->roles, true ) ) {
			continue;
		}
		$out[] = $u->ID;
	}
	return $out;
}
function get_user_by( $f, $v ) { return isset( $GLOBALS['users'][ (int) $v ] ) ? $GLOBALS['users'][ (int) $v ] : false; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $h ][] = $c; }
function apply_filters( $h, $v ) {
	$args = array_slice( func_get_args(), 1 );
	foreach ( isset( $GLOBALS['filters'][ $h ] ) ? $GLOBALS['filters'][ $h ] : array() as $cb ) {
		$args[0] = call_user_func_array( $cb, $args );
	}
	return $args[0];
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-two-factor.php';

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
function user( $id, array $roles ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $roles );
	return $GLOBALS['users'][ $id ];
}
function prompt_for( $id ) {
	$GLOBALS['uid'] = $id;
	ob_start();
	WPCPM_Two_Factor::prompt( wp_get_current_user() );
	return ob_get_clean();
}

/* ---- with no plugin present --------------------------------------------- */

echo "=== With the Two Factor plugin absent ===\n";

user( 1, array( 'subscriber' ) );

ck( 'available() is false', WPCPM_Two_Factor::available(), false );
ck( 'init() registers nothing rather than fataling', array( WPCPM_Two_Factor::init(), isset( $GLOBALS['filters']['two_factor_enabled_providers_for_user'] ) ), array( null, false ) );
ck( 'has_app() is false for everyone', WPCPM_Two_Factor::has_app( 1 ), false );
ck( 'providers() is empty', WPCPM_Two_Factor::providers(), array() );
ck( 'status() says so and counts nobody', WPCPM_Two_Factor::status(), array( 'available' => false, 'roles' => array(), 'enforced' => 0, 'uncovered' => 0 ) );
ck( 'and the prompt prints nothing at all', prompt_for( 1 ), '' );

/* ---- the plugin, stood in for ------------------------------------------- */

// Declared here rather than at the top of the file on purpose: PHP hoists class declarations,
// so a stand-in written at file level would exist from the first line and the section above
// could never have seen its own condition.
eval(
	'class Two_Factor_Core {
		public static function get_providers() {
			return empty( $GLOBALS["no_email"] )
				? array( "Two_Factor_Email" => "Email", "Two_Factor_Totp" => "Authenticator app", "Two_Factor_Backup_Codes" => "Backup codes" )
				: array( "Two_Factor_Totp" => "Authenticator app" );
		}
		public static function get_enabled_providers_for_user( $user_id ) {
			$id  = $user_id instanceof WP_User ? $user_id->ID : (int) $user_id;
			$own = isset( $GLOBALS["enabled"][ $id ] ) ? $GLOBALS["enabled"][ $id ] : array();
			// The real method runs the filter last, which is the whole mechanism under test.
			return (array) apply_filters( "two_factor_enabled_providers_for_user", $own, $id );
		}
		public static function is_user_using_two_factor( $user_id ) {
			return ! empty( self::get_enabled_providers_for_user( $user_id ) );
		}
	}'
);

WPCPM_Two_Factor::init();

echo "\n=== Who is asked, and who is not ===\n";

ck( 'the plugin is seen now', WPCPM_Two_Factor::available(), true );
ck( 'and init() registered both filters', array(
	count( $GLOBALS['filters']['two_factor_enabled_providers_for_user'] ),
	count( $GLOBALS['filters']['two_factor_primary_provider_for_user'] ),
), array( 1, 1 ) );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = array( 'two_factor_roles' => array( 'administrator', 'wpcpm_institution' ) );

user( 10, array( 'administrator' ) );
user( 20, array( 'wpcpm_institution' ) );
user( 30, array( 'wpcpm_mentor' ) );
user( 40, array( 'wpcpm_student' ) );
user( 50, array( 'subscriber' ) );

ck( 'the default policy is administrators and institutions', WPCPM_Settings::defaults()['two_factor_roles'], array( 'administrator', 'wpcpm_institution' ) );
ck( 'an administrator is required', WPCPM_Two_Factor::is_required( 10 ), true );
ck( 'an institution is required', WPCPM_Two_Factor::is_required( 20 ), true );
ck( 'a mentor is not, by default', WPCPM_Two_Factor::is_required( 30 ), false );
ck( 'a student is not', WPCPM_Two_Factor::is_required( 40 ), false );
ck( 'nor is a plain subscriber', WPCPM_Two_Factor::is_required( 50 ), false );
ck( 'nor is somebody who is not an account', array( WPCPM_Two_Factor::is_required( 0 ), WPCPM_Two_Factor::is_required( 999 ) ), array( false, false ) );

// The point of the whole design: no setup step, no window.
echo "\n=== The second step exists before anyone sets anything up ===\n";

ck( 'an enforced account with nothing configured is already using two factors', Two_Factor_Core::is_user_using_two_factor( 10 ), true );
ck( 'and what it will be asked for is an emailed code', Two_Factor_Core::get_enabled_providers_for_user( 10 ), array( 'Two_Factor_Email' ) );
ck( 'an account nobody requires it of is left alone', array( Two_Factor_Core::is_user_using_two_factor( 30 ), Two_Factor_Core::get_enabled_providers_for_user( 30 ) ), array( false, array() ) );

// An account that chose for itself is never overridden, in either direction.
$GLOBALS['enabled'][30] = array( 'Two_Factor_Totp' );
ck( 'a mentor who turned it on for themselves keeps exactly what they chose', Two_Factor_Core::get_enabled_providers_for_user( 30 ), array( 'Two_Factor_Totp' ) );

$GLOBALS['enabled'][10] = array( 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' );
ck( 'and an enforced account that set up an app is not pushed back to email', Two_Factor_Core::get_enabled_providers_for_user( 10 ), array( 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' ) );
ck( 'the app is what the login screen offers first', WPCPM_Two_Factor::primary_provider( 'Two_Factor_Backup_Codes', 10 ), 'Two_Factor_Totp' );
ck( 'while an account on email alone keeps the plugin\'s own choice', WPCPM_Two_Factor::primary_provider( 'Two_Factor_Email', 20 ), 'Two_Factor_Email' );
ck( 'has_app() tells the two apart', array( WPCPM_Two_Factor::has_app( 10 ), WPCPM_Two_Factor::has_app( 20 ) ), array( true, false ) );

// A provider the plugin does not offer must never leave an enforced account with an empty list.
echo "\n=== A site whose plugin offers no email provider ===\n";

// A class name the plugin does not offer would be dropped by the plugin and leave the account
// with an empty list, which reads as "no second factor at all". Better to hand back nothing and
// have the manager screen show the account as uncovered than to record a protection that is not
// there.
$GLOBALS['no_email'] = true;
$GLOBALS['enabled']  = array();

ck( 'an email provider that does not exist is not invented', WPCPM_Two_Factor::enabled_providers( array(), 20 ), array() );
ck( 'and the account is honestly reported as not using two factors', Two_Factor_Core::is_user_using_two_factor( 20 ), false );
ck( 'while an account that set up the app it does have is untouched', WPCPM_Two_Factor::enabled_providers( array( 'Two_Factor_Totp' ), 20 ), array( 'Two_Factor_Totp' ) );

unset( $GLOBALS['no_email'] );

/* ---- the policy is a setting -------------------------------------------- */

echo "\n=== The policy is a setting, and a filter ===\n";

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['two_factor_roles'] = array( 'administrator', 'wpcpm_mentor' );
ck( 'widening it to mentors takes effect at once', array( WPCPM_Two_Factor::is_required( 30 ), WPCPM_Two_Factor::is_required( 20 ) ), array( true, false ) );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['two_factor_roles'] = array();
ck( 'an empty list requires it of nobody', array( WPCPM_Two_Factor::is_required( 10 ), WPCPM_Two_Factor::required_roles() ), array( false, array() ) );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['two_factor_roles'] = array( 'administrator', 'wpcpm_ghost', '', 'ADMINISTRATOR' );
ck( 'a role slug nothing registers is dropped, and the rest still stands', WPCPM_Two_Factor::required_roles(), array( 'administrator' ) );

$GLOBALS['filters']['wpcpm_two_factor_roles'][] = function ( $roles ) { return array( 'wpcpm_student' ); };
ck( 'the filter is the final word', array( WPCPM_Two_Factor::required_roles(), WPCPM_Two_Factor::is_required( 40 ) ), array( array( 'wpcpm_student' ), true ) );
unset( $GLOBALS['filters']['wpcpm_two_factor_roles'] );

/* ---- what people are told ----------------------------------------------- */

echo "\n=== What each person is told ===\n";

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['two_factor_roles'] = array( 'administrator', 'wpcpm_institution' );
$GLOBALS['enabled'] = array();

$required = prompt_for( 20 );
ck( 'an enforced account on emailed codes is told its account already asks for one', false !== strpos( $required, 'Your account asks for a code when you sign in' ), true );
ck( 'and is offered the app rather than told off', false !== strpos( $required, 'Set up an authenticator app' ), true );
ck( 'with the firmer styling', false !== strpos( $required, 'wpcpm-2fa--required' ), true );
ck( 'and the link goes to the one screen the controls live on', false !== strpos( $required, 'https://example.test/wp-admin/profile.php#two-factor-options' ), true );
ck( 'backup codes are named, because that is the step people skip', false !== strpos( $required, 'save the backup codes' ), true );

$optional = prompt_for( 30 );
ck( 'a mentor nobody requires it of is invited, not warned', array(
	false !== strpos( $optional, 'Add a second step to your sign-in' ),
	false !== strpos( $optional, 'It is optional' ),
	false !== strpos( $optional, 'wpcpm-2fa--required' ),
), array( true, true, false ) );

$GLOBALS['enabled'][30] = array( 'Two_Factor_Totp' );
ck( 'and is not nagged once they have done it', prompt_for( 30 ), '' );

$GLOBALS['enabled'][20] = array( 'Two_Factor_Totp' );
ck( 'nor is an enforced account that moved to the app', prompt_for( 20 ), '' );

$GLOBALS['enabled'][30] = array( 'Two_Factor_Email' );
ck( 'somebody who chose emailed codes for themselves is left in peace too', prompt_for( 30 ), '' );

ck( 'and nobody logged out is shown anything', prompt_for( 0 ), '' );

/* ---- the status the settings screen reads -------------------------------- */

echo "\n=== The status a program manager reads ===\n";

$GLOBALS['enabled'] = array( 20 => array( 'Two_Factor_Totp' ) );
user( 11, array( 'administrator' ) );
user( 12, array( 'administrator', 'wpcpm_mentor' ) );

$status = WPCPM_Two_Factor::status();
ck( 'both enforced roles are reported', array_keys( $status['roles'] ), array( 'administrator', 'wpcpm_institution' ) );
ck( 'with the label a person recognises', $status['roles']['administrator']['label'], 'Administrator' );
ck( 'every administrator is counted, and all are covered by the emailed code', array( $status['roles']['administrator']['total'], $status['roles']['administrator']['covered'] ), array( 3, 3 ) );
ck( 'none of them has an app yet', $status['roles']['administrator']['app'], 0 );
ck( 'the institution has one, and it is an app', array( $status['roles']['wpcpm_institution']['total'], $status['roles']['wpcpm_institution']['app'] ), array( 1, 1 ) );
ck( 'nobody enforced is uncovered, which is the point of the floor', $status['uncovered'], 0 );
ck( 'and the enforced total counts accounts, not role memberships', $status['enforced'], 4 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
