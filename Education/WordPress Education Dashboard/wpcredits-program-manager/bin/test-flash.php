<?php
/**
 * One-shot messages appear once.
 *
 * The bug this covers: outcomes used to travel as `?wpcpm_call=cancelled`, so the message
 * came back on every reload of that URL — "That call is canceled and the slot is free
 * again" sat on the page permanently, describing something that happened once. Reported on
 * both dashboards.
 *
 * Run from the plugin root:  php bin/test-flash.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['umeta'] = array();
$GLOBALS['uid']   = 7;

function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

require_once dirname( __DIR__ ) . '/includes/class-wpcpm-flash.php';

$fail = 0;
function ck( $l, $a, $e ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

// NOTE ON SCENARIOS: `take()` memoizes per request, and a function static cannot be
// reset from outside. Each scenario below therefore uses its own channel name rather
// than re-reading one — do not consolidate them into a single channel.

WPCPM_Flash::set( 'call', 'cancelled' );
ck( 'the message is there on the page the redirect lands on', WPCPM_Flash::take( 'call' ), 'cancelled' );
ck( 'asking twice in one request gives the same answer', WPCPM_Flash::take( 'call' ), 'cancelled' );
ck( 'and it is gone from storage immediately', $GLOBALS['umeta'][7]['wpcpm_flash'] ?? array(), array() );

// A second request cannot see it — simulated with a channel that has never been set.
ck( 'a reload shows nothing', WPCPM_Flash::take( 'never-set' ), '' );

// Channels are independent: one message must not consume another.
WPCPM_Flash::set( 'availability', 'saved' );
WPCPM_Flash::set( 'note', 'deleted' );
ck( 'two channels queue side by side',
    array_keys( $GLOBALS['umeta'][7]['wpcpm_flash'] ), array( 'availability', 'note' ) );
ck( 'taking one leaves the other', WPCPM_Flash::take( 'availability' ), 'saved' );
ck( '...still queued', array_keys( $GLOBALS['umeta'][7]['wpcpm_flash'] ), array( 'note' ) );
ck( 'and it reads correctly', WPCPM_Flash::take( 'note' ), 'deleted' );
ck( 'the meta row is removed once nothing is queued', isset( $GLOBALS['umeta'][7]['wpcpm_flash'] ), false );

// Structured payloads survive, for the Airtable error detail.
WPCPM_Flash::set( 'details', array( 'status' => 'airtable', 'why' => 'Token is read-only.' ) );
ck( 'an array payload round-trips',
    WPCPM_Flash::take( 'details' ), array( 'status' => 'airtable', 'why' => 'Token is read-only.' ) );

// One user's message is not another's.
$GLOBALS['uid'] = 7;
WPCPM_Flash::set( 'x-user', 'mine' );
$GLOBALS['uid'] = 8;
ck( 'another user sees nothing of it', WPCPM_Flash::take( 'x-user' ), '' );
$GLOBALS['uid'] = 7;
ck( 'and the owner still has it', WPCPM_Flash::take( 'x-user' ), 'mine' );

// Logged out: nothing stored, nothing read, no fatal.
$GLOBALS['uid'] = 0;
WPCPM_Flash::set( 'guest', 'nope' );
ck( 'nothing is stored for a guest', isset( $GLOBALS['umeta'][0] ), false );
ck( 'and a guest reads nothing', WPCPM_Flash::take( 'guest' ), '' );

// No status is left in the redirect URLs any more.
$root = dirname( __DIR__ );
foreach ( array(
	'includes/modules/class-wpcpm-mentor-calls.php'        => 'wpcpm_call',
	'includes/modules/class-wpcpm-mentor-availability.php' => 'wpcpm_availability',
	'includes/modules/class-wpcpm-mentor-notes.php'        => 'wpcpm_note',
	'includes/modules/class-wpcpm-student-profile.php'     => 'wpcpm_details',
	'includes/modules/class-wpcpm-student-report-form.php' => 'wpcpm_report',
) as $file => $arg ) {
	$src = file_get_contents( $root . '/' . $file );
	ck(
		sprintf( 'no %s status left in a redirect (%s)', $arg, basename( $file ) ),
		(bool) preg_match( "/'" . preg_quote( $arg, '/' ) . "'\s*=>/", $src ),
		false
	);
}

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
