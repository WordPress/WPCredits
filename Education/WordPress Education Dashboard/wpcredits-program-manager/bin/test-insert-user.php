<?php
/**
 * Account creation survives the host's signup spam check.
 *
 * On the WordPress.com Atomic host every `wp_insert_user()` for a new account passes through the
 * platform's `wp_pre_insert_user_data` callback (`Atomic_Platform_Mu_Plugin::bkismet_check_signup`).
 * It asks the bkismet signup service about the login and the email, and returns `false` when the
 * verdict is "block". Core reads that `false` as `empty_data` - "Not enough data to create this
 * user." - which is how a vetted mentor's account failed on 4 September 2026 with a message that
 * named no cause. wp-admin's Add New User and `wp user create` fail the same way, so the account
 * could not be created by any route.
 *
 * None of the accounts this plugin creates is a self-signup: a mentor or a student comes from
 * Airtable, an institution account from an approved application, a manager's import or an
 * invitation a manager sent. So the plugin creates every one of them through
 * `WPCPM_Roles::insert_user()`, which switches the platform check off for the length of that one
 * insert by answering the `atomic_bkismet_client_key` filter with false - the check exits early
 * without a key, before any request leaves the site.
 *
 * What this suite pins:
 *
 * - with the platform callback in place and a key configured, a plain `wp_insert_user()` is
 *   refused with `empty_data`, so the stand-in below reproduces the host;
 * - `WPCPM_Roles::insert_user()` with the same data creates the account, and the service is never
 *   asked;
 * - the switch-off lasts exactly one insert: afterwards the key is back, nothing of ours is left on
 *   the filter, and a plain insert is refused again - the same when the insert itself failed;
 * - the helper hands back whatever core returned, the ID or the WP_Error untouched;
 * - it works on a host with no such callback at all;
 * - no file under includes/ calls `wp_insert_user()` directly except the helper, so no new account
 *   path can bring the opaque failure back.
 *
 * Every address is under example.test and every name is invented.
 *
 * Run from the plugin root:  php bin/test-insert-user.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['users']         = array();
$GLOBALS['filters']       = array();
$GLOBALS['bkismet_asked'] = 0;

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

class WP_User {
	public $ID = 0, $user_login = '', $user_email = '', $roles = array();
	public function __construct( $id = 0 ) {
		$this->ID = (int) $id;
		$data     = $GLOBALS['users'][ $this->ID ] ?? array();

		$this->user_login = $data['login'] ?? '';
		$this->user_email = $data['email'] ?? '';
		$this->roles      = $data['roles'] ?? array();
	}
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function __return_false() { return false; }

/*
 * A working filter registry, because the behaviour under test is what happens to a filter while
 * one function runs. Priorities are honoured; a callback is identified the way core does it, by
 * the callable itself.
 */
function wpcpm_test_callback_id( $fn ) {
	if ( is_string( $fn ) ) {
		return $fn;
	}
	if ( is_object( $fn ) ) {
		return spl_object_hash( $fn );
	}

	return ( is_object( $fn[0] ) ? spl_object_hash( $fn[0] ) : $fn[0] ) . '::' . $fn[1];
}

function add_filter( $hook, $fn, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['filters'][ $hook ][ (int) $priority ][ wpcpm_test_callback_id( $fn ) ] = array( $fn, (int) $accepted_args );

	return true;
}

function remove_filter( $hook, $fn, $priority = 10 ) {
	$id = wpcpm_test_callback_id( $fn );

	if ( ! isset( $GLOBALS['filters'][ $hook ][ (int) $priority ][ $id ] ) ) {
		return false;
	}

	unset( $GLOBALS['filters'][ $hook ][ (int) $priority ][ $id ] );

	// As WP_Hook does: an emptied priority goes, and an emptied hook with it.
	if ( empty( $GLOBALS['filters'][ $hook ][ (int) $priority ] ) ) {
		unset( $GLOBALS['filters'][ $hook ][ (int) $priority ] );
	}
	if ( empty( $GLOBALS['filters'][ $hook ] ) ) {
		unset( $GLOBALS['filters'][ $hook ] );
	}

	return true;
}

function has_filter( $hook, $fn = false ) {
	if ( false === $fn ) {
		foreach ( (array) ( $GLOBALS['filters'][ $hook ] ?? array() ) as $callbacks ) {
			if ( ! empty( $callbacks ) ) {
				return true;
			}
		}

		return false;
	}

	$id = wpcpm_test_callback_id( $fn );

	foreach ( (array) ( $GLOBALS['filters'][ $hook ] ?? array() ) as $priority => $callbacks ) {
		if ( isset( $callbacks[ $id ] ) ) {
			return $priority;
		}
	}

	return false;
}

function apply_filters( $hook, $value, ...$more ) {
	$levels = (array) ( $GLOBALS['filters'][ $hook ] ?? array() );
	ksort( $levels );

	foreach ( $levels as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			list( $fn, $accepted ) = $entry;
			$args  = array_slice( array_merge( array( $value ), $more ), 0, max( 1, $accepted ) );
			$value = call_user_func_array( $fn, $args );
		}
	}

	return $value;
}

function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $id => $u ) {
		if ( ( 'login' === $field && $u['login'] === $value ) || ( 'email' === $field && $u['email'] === $value ) ) {
			return new WP_User( $id );
		}
	}

	return false;
}

/*
 * The part of core's `wp_insert_user()` that matters here, in its own order: the login checks, then
 * the `wp_pre_insert_user_data` filter, then the `empty_data` refusal when the filter returned
 * nothing usable, then the row.
 */
function wp_insert_user( array $userdata ) {
	static $next = 100;

	$login = trim( (string) ( $userdata['user_login'] ?? '' ) );

	if ( '' === $login ) {
		return new WP_Error( 'empty_user_login', 'Cannot create a user with an empty login name.' );
	}

	if ( get_user_by( 'login', $login ) ) {
		return new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
	}

	$data = array(
		'user_login'   => $login,
		'user_email'   => (string) ( $userdata['user_email'] ?? '' ),
		'display_name' => (string) ( $userdata['display_name'] ?? $login ),
	);

	$data = apply_filters( 'wp_pre_insert_user_data', $data, false, null, $userdata );

	if ( empty( $data ) || ! is_array( $data ) ) {
		return new WP_Error( 'empty_data', 'Not enough data to create this user.' );
	}

	$id = ++$next;

	$GLOBALS['users'][ $id ] = array(
		'login' => $data['user_login'],
		'email' => $data['user_email'],
		'roles' => array( (string) ( $userdata['role'] ?? '' ) ),
	);

	return $id;
}

/*
 * The host's callback, as it reads in /wordpress/mu-plugins/atomic-platform.php: nothing on an
 * update, nothing without a key, otherwise the service's verdict decides - and the verdict here is
 * always "block", because that is the case the plugin has to survive.
 */
$platform_check = static function ( $data, $update ) {
	if ( false !== $update ) {
		return $data;
	}

	$api_key = apply_filters( 'atomic_bkismet_client_key', false );

	if ( empty( $api_key ) ) {
		return $data;
	}

	++$GLOBALS['bkismet_asked'];

	return false;
};

$platform_key = static function () {
	return 'site-key';
};

add_filter( 'wp_pre_insert_user_data', $platform_check, 10, 2 );
add_filter( 'atomic_bkismet_client_key', $platform_key );

require __DIR__ . '/../includes/class-wpcpm-roles.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
	} else {
		++$fails;
		printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
	}
}

function mentor( $login ) {
	return array(
		'user_login'   => $login,
		'user_email'   => $login . '@example.test',
		'user_pass'    => 'x',
		'display_name' => ucfirst( $login ),
		'nickname'     => ucfirst( $login ),
		'role'         => WPCPM_Roles::ROLE_MENTOR,
	);
}

// --- The stand-in reproduces the host: a plain insert is refused with the opaque message.

$plain = wp_insert_user( mentor( 'jakub' ) );

ck( 'a plain insert on the host is refused', is_wp_error( $plain ), true );
ck( 'with core\'s empty_data code', is_wp_error( $plain ) ? $plain->get_error_code() : null, 'empty_data' );
ck( 'and the message that names no cause', is_wp_error( $plain ) ? $plain->get_error_message() : null, 'Not enough data to create this user.' );
ck( 'the service was asked once', $GLOBALS['bkismet_asked'], 1 );
ck( 'no account was created', get_user_by( 'login', 'jakub' ), false );

// --- The helper creates the same account, and the service is never consulted.

$created = WPCPM_Roles::insert_user( mentor( 'jakub' ) );

ck( 'the helper creates the account', is_int( $created ) && $created > 0, true );
ck( 'under the requested login', get_user_by( 'login', 'jakub' ) instanceof WP_User, true );
ck( 'with the requested role', get_user_by( 'login', 'jakub' ) ? get_user_by( 'login', 'jakub' )->roles : null, array( WPCPM_Roles::ROLE_MENTOR ) );
ck( 'and the service was not asked', $GLOBALS['bkismet_asked'], 1 );

// --- The switch-off lasted exactly that one insert.

ck( 'the key is back afterwards', apply_filters( 'atomic_bkismet_client_key', false ), 'site-key' );
ck( 'nothing of ours is left on the key filter', has_filter( 'atomic_bkismet_client_key', '__return_false' ), false );
ck( 'the platform callback is still in place', has_filter( 'wp_pre_insert_user_data', $platform_check ), 10 );

$again = wp_insert_user( mentor( 'nadia' ) );

ck( 'a plain insert is refused again', is_wp_error( $again ) ? $again->get_error_code() : null, 'empty_data' );
ck( 'and asked the service', $GLOBALS['bkismet_asked'], 2 );

// --- A failed insert hands the error back untouched, and still cleans up.

$duplicate = WPCPM_Roles::insert_user( mentor( 'jakub' ) );

ck( 'a failed insert returns core\'s error', is_wp_error( $duplicate ) ? $duplicate->get_error_code() : null, 'existing_user_login' );
ck( 'as the same object', $duplicate instanceof WP_Error, true );
ck( 'the key is back after a failure too', apply_filters( 'atomic_bkismet_client_key', false ), 'site-key' );
ck( 'and nothing of ours is left on the filter', has_filter( 'atomic_bkismet_client_key', '__return_false' ), false );
ck( 'the service was not asked for the failure', $GLOBALS['bkismet_asked'], 2 );

// --- A host without the callback: the helper is a plain insert.

remove_filter( 'wp_pre_insert_user_data', $platform_check, 10 );
remove_filter( 'atomic_bkismet_client_key', $platform_key, 10 );

$elsewhere = WPCPM_Roles::insert_user( mentor( 'nadia' ) );

ck( 'on a host without the check the helper creates the account', is_int( $elsewhere ) && $elsewhere > 0, true );
ck( 'and leaves the key filter empty as it found it', has_filter( 'atomic_bkismet_client_key' ), false );

// --- Every account path in the plugin goes through the helper.

$offenders = array();
$files     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../includes', FilesystemIterator::SKIP_DOTS ) );

foreach ( $files as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}

	$tokens = token_get_all( (string) file_get_contents( $file->getPathname() ) );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || 'wp_insert_user' !== $tokens[ $i ][1] ) {
			continue;
		}

		// A call, not a mention in a string or a comment (those are not T_STRING) and not a
		// method of the same name (preceded by -> or ::).
		$prev = $i > 0 ? $tokens[ $i - 1 ] : null;
		if ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true ) ) {
			continue;
		}

		$next = $i + 1 < $count ? $tokens[ $i + 1 ] : null;
		if ( '(' !== $next ) {
			continue;
		}

		$relative = substr( $file->getPathname(), strlen( __DIR__ . '/../' ) );
		if ( 'includes/class-wpcpm-roles.php' === $relative ) {
			continue;
		}

		$offenders[] = $relative . ':' . $tokens[ $i ][2];
	}
}

sort( $offenders );

ck( 'no file but the helper calls wp_insert_user() directly', $offenders, array() );

// --- House rule.

$dashes = array();
if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-roles.php' ) ) ) {
	$dashes[] = 'the helper';
}
if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __FILE__ ) ) ) {
	$dashes[] = 'this suite';
}

ck( 'no dash but the plain hyphen in the helper or this suite', $dashes, array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
