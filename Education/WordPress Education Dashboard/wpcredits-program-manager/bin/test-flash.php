<?php
/**
 * One-shot messages appear once.
 *
 * The bug this covers: outcomes used to travel as `?wpcpm_call=cancelled`, so the message
 * came back on every reload of that URL - "That call is canceled and the slot is free
 * again" sat on the page permanently, describing something that happened once. Reported on
 * both dashboards.
 *
 * And a string, an array of them at any depth, or any other scalar comes back exactly as it was
 * given; an object's strings are not covered, since `wp_slash()` does not reach into an object.
 * Core's metadata API unslashes every value it writes, so the stand-in for user meta below does
 * too, and a value with a backslash, a quote, a string nested in an array or a value that is not a
 * string at all is checked through `set()` and `take()`, the one that waits while another channel
 * is taken among them.
 *
 * Run from the plugin root:  php bin/test-flash.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['umeta'] = array();
$GLOBALS['uid']   = 7;

/**
 * Core's own, as wp-includes/formatting.php writes it.
 *
 * @param mixed $value A value to slash.
 * @return mixed
 */
function wp_slash( $value ) {
	if ( is_array( $value ) ) {
		$value = array_map( 'wp_slash', $value );
	}

	if ( is_string( $value ) ) {
		return addslashes( $value );
	}

	return $value;
}

/**
 * Core's `wp_unslash()` for the values a flash keeps: every string, at any depth of an array,
 * with its slashes stripped, and every other value as it is (core's `stripslashes_deep()`).
 *
 * @param mixed $value A value to unslash.
 * @return mixed
 */
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
// As core writes it: `update_metadata()` passes the value it is handed through `wp_unslash()`.
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = wp_unslash( $v ); return true; }
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
// than re-reading one - do not consolidate them into a single channel.

WPCPM_Flash::set( 'call', 'cancelled' );
ck( 'the message is there on the page the redirect lands on', WPCPM_Flash::take( 'call' ), 'cancelled' );
ck( 'asking twice in one request gives the same answer', WPCPM_Flash::take( 'call' ), 'cancelled' );
ck( 'and it is gone from storage immediately', $GLOBALS['umeta'][7]['wpcpm_flash'] ?? array(), array() );

// A second request cannot see it - simulated with a channel that has never been set.
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

// What is kept comes back as it was given: core unslashes every value it writes to user meta, so a
// value handed to it as it stands loses a backslash there on every write.
WPCPM_Flash::set( 'slash-one', 'one \ backslash' );
ck( 'a backslash in a string comes back', WPCPM_Flash::take( 'slash-one' ), 'one \ backslash' );
WPCPM_Flash::set( 'slash-path', 'C:\drafts' );
ck( 'and so does a path written with backslashes', WPCPM_Flash::take( 'slash-path' ), 'C:\drafts' );
WPCPM_Flash::set( 'slash-double', 'two \\\\ in a row' );
ck( 'and a doubled backslash keeps both', WPCPM_Flash::take( 'slash-double' ), 'two \\\\ in a row' );
WPCPM_Flash::set( 'slash-quote', 'It\'s "typed"' );
ck( 'a quote comes back with no backslash added to it', WPCPM_Flash::take( 'slash-quote' ), 'It\'s "typed"' );

$typed = array(
	'status' => 'error',
	'values' => array(
		'label'      => 'C:\drafts',
		'key'        => 'two \\\\ in a row',
		'course_url' => 'https://learn.example.test/?q="a\b"',
	),
);
WPCPM_Flash::set( 'slash-nested', $typed );
ck( 'strings nested in an array come back as they were', WPCPM_Flash::take( 'slash-nested' ), $typed );

$kinds = array(
	'count' => 42,
	'on'    => true,
	'off'   => false,
	'none'  => null,
	'ratio' => 1.5,
);
WPCPM_Flash::set( 'slash-kinds', $kinds );
ck( 'an integer, a boolean, null and a float keep their types and their values', WPCPM_Flash::take( 'slash-kinds' ), $kinds );
WPCPM_Flash::set( 'slash-int', 7 );
WPCPM_Flash::set( 'slash-bool', true );
ck( 'and an integer and a boolean kept on their own channels do too', array( WPCPM_Flash::take( 'slash-int' ), WPCPM_Flash::take( 'slash-bool' ) ), array( 7, true ) );

// A value that waits while another channel is taken is written again with what is left, so it
// must come through that write as well.
WPCPM_Flash::set( 'slash-waits', 'two \\\\ in a row, C:\drafts' );
WPCPM_Flash::set( 'slash-first', 'saved' );
WPCPM_Flash::take( 'slash-first' );
ck( 'a value that waits while another channel is taken comes back as it was', WPCPM_Flash::take( 'slash-waits' ), 'two \\\\ in a row, C:\drafts' );

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

// No status is left in the redirect URLs any more. Each file is asserted to be there first:
// a check that reads a module which no longer exists passes on an empty string and warns on
// every run, which is what the student profile row here did until the deep check of
// 7 September 2026 read the battery's output.
$root = dirname( __DIR__ );
foreach ( array(
	'includes/modules/class-wpcpm-mentor-calls.php'        => 'wpcpm_call',
	'includes/modules/class-wpcpm-mentor-availability.php' => 'wpcpm_availability',
	'includes/modules/class-wpcpm-mentor-notes.php'        => 'wpcpm_note',
	'includes/modules/class-wpcpm-student-report-form.php' => 'wpcpm_report',
) as $file => $arg ) {
	$path = $root . '/' . $file;

	ck( sprintf( 'the module the check reads is there (%s)', basename( $file ) ), is_file( $path ), true );

	$src = is_file( $path ) ? (string) file_get_contents( $path ) : '';
	ck(
		sprintf( 'no %s status left in a redirect (%s)', $arg, basename( $file ) ),
		(bool) preg_match( "/'" . preg_quote( $arg, '/' ) . "'\s*=>/", $src ),
		false
	);
}

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
