<?php
/**
 * WPCPM_Form_Stash: the redirect-and-stash machinery both application forms share (1.98.1).
 *
 * Extracted from the two public application forms, which each carried a copy of it (the S5
 * whole-branch review's recommendation). What is pinned here is the contract the two forms now
 * depend on: which outcomes travel bare in the address and which go behind a transient, what a
 * stash loses on the way in, and which outcomes are read once and deleted.
 *
 * Run from the plugin root:  php bin/test-form-stash.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['transients'] = array();
$GLOBALS['redirects']  = array();
$GLOBALS['request']    = array();

function set_transient( $k, $v, $ttl ) { $GLOBALS['transients'][ $k ] = array( 'v' => $v, 'ttl' => $ttl ); return true; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ]['v'] : false; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function wp_generate_password( $n, $a = false, $b = false ) { return str_repeat( 'Ab1', 11 ) . 'Z'; }
function home_url( $p = '' ) { return 'https://site.example' . $p; }
function add_query_arg( $args, $url ) { $q = array(); foreach ( (array) $args as $k => $v ) { $q[] = rawurlencode( $k ) . '=' . rawurlencode( (string) $v ); } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $q ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }

/** A redirect, caught: the real one calls `exit` and the suite has to carry on. */
class Bounced extends Exception {}

function wp_safe_redirect( $url ) { $GLOBALS['redirects'][] = $url; throw new Bounced( $url ); }

// Stands in for the real `WPCPM_Request::key()`, which reads `$_GET` and passes the value
// through `sanitize_key()`. Lowercasing is the half this class depends on: the id it mints is
// lowercased on the way out so that the one that comes back is the same string.
class WPCPM_Request {
	public static function key( $name ) { return isset( $GLOBALS['request'][ $name ] ) ? strtolower( (string) $GLOBALS['request'][ $name ] ) : ''; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-stash.php';

$fail = 0; $checks = 0;

/**
 * One assertion.
 *
 * @param string $label    What is being asserted.
 * @param mixed  $actual   What the code answered.
 * @param mixed  $expected What it should have answered.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * `WPCPM_Form_Stash::bounce()` with the redirect caught, answering with the URL it sent.
 *
 * @param array    $form     The form's description.
 * @param string   $outcome  The outcome slug.
 * @param array    $stash    What the page needs to draw.
 * @param callable $is_blank Says whether one value is blank.
 * @return string The URL redirected to.
 */
function bounce( $form, $outcome, $stash, $is_blank ) { try { WPCPM_Form_Stash::bounce( $form, $outcome, $stash, $is_blank ); } catch ( Bounced $e ) { return $e->getMessage(); } return 'no redirect'; }

$is_blank = static function ( $v ) { return null === $v || false === $v || ( is_array( $v ) ? empty( $v ) : '' === trim( (string) $v ) ); };
$form     = array(
	'url'           => 'https://site.example/apply/',
	'outcomes'      => array( 'closed', 'spam', 'again' ),
	'query_outcome' => 'wpcpm_x_said',
	'query_stash'   => 'wpcpm_x',
	'prefix'        => 'wpcpm_x_',
	'minutes'       => 30,
	'one_shot'      => array( 'sent' ),
);
$id = strtolower( wp_generate_password( 32, false, false ) );

echo "=== bounce ===\n";
ck( 'a known outcome with nothing behind it travels bare in the address', bounce( $form, 'closed', array(), $is_blank ), 'https://site.example/apply/?wpcpm_x_said=closed' );
ck( 'and leaves no transient', $GLOBALS['transients'], array() );
ck( 'an outcome the form does not know never travels bare: it goes behind a transient even with nothing else', bounce( $form, 'sent', array(), $is_blank ), 'https://site.example/apply/?wpcpm_x=' . $id );
ck( 'the transient holds the outcome, under the prefix, for the minutes given', $GLOBALS['transients'][ 'wpcpm_x_' . $id ], array( 'v' => array( 'outcome' => 'sent' ), 'ttl' => 1800 ) );
$GLOBALS['transients'] = array();
ck( 'a stash with words in it travels behind a transient whatever the outcome', bounce( $form, 'again', array( 'values' => array( 'Company Name' => 'Acme', 'Website' => '' ), 'problems' => array() ), $is_blank ), 'https://site.example/apply/?wpcpm_x=' . $id );
ck( 'blank values and empty keys are dropped before it is kept', $GLOBALS['transients'][ 'wpcpm_x_' . $id ]['v'], array( 'values' => array( 'Company Name' => 'Acme' ), 'outcome' => 'again' ) );
ck( 'no page means the home page', strpos( bounce( array_merge( $form, array( 'url' => '' ) ), 'closed', array(), $is_blank ), 'https://site.example/?' ), 0 );

echo "\n=== read ===\n";
$GLOBALS['request'] = array( 'wpcpm_x' => strtoupper( $id ) );
ck( 'the stash is read back through the lowercased id', WPCPM_Form_Stash::read( $form ), array( 'values' => array( 'Company Name' => 'Acme' ), 'outcome' => 'again' ) );
ck( 'and a failed attempt is kept for a reload', isset( $GLOBALS['transients'][ 'wpcpm_x_' . $id ] ), true );
$GLOBALS['transients'][ 'wpcpm_x_' . $id ]['v'] = array( 'outcome' => 'sent', 'reference' => 'X-1' );
ck( 'a one-shot outcome is read once', WPCPM_Form_Stash::read( $form ), array( 'outcome' => 'sent', 'reference' => 'X-1' ) );
ck( 'and deleted as it is read', isset( $GLOBALS['transients'][ 'wpcpm_x_' . $id ] ), false );
ck( 'a missing stash falls through to the bare outcome, which must be a slug the form knows', WPCPM_Form_Stash::read( $form ), array() );
$GLOBALS['request'] = array( 'wpcpm_x_said' => 'spam' );
ck( 'a known bare outcome is answered as itself', WPCPM_Form_Stash::read( $form ), array( 'outcome' => 'spam' ) );
$GLOBALS['request'] = array( 'wpcpm_x_said' => '<b>hi</b>' );
ck( 'and anything else is nothing: the argument chooses between fixed sentences and is never text on the page', WPCPM_Form_Stash::read( $form ), array() );

// Seven keys are read on a page anybody can reach, and the array holding them is built from a
// caller's own constants. A key left out used to be a warning printed above the form; now it is
// the harmless reading of that key. `one_shot` is the one worth pinning, because its default
// decides whether a stash survives the read: a form that says nothing about one-shot outcomes
// has none, so the stash is handed back and left where it is.
$GLOBALS['transients'][ 'wpcpm_x_' . $id ] = array( 'v' => array( 'outcome' => 'sent', 'reference' => 'X-2' ), 'ttl' => 1800 );
$GLOBALS['request']                        = array( 'wpcpm_x' => $id );
$partial                                   = $form;
unset( $partial['one_shot'] );
$noticed = '';
set_error_handler( static function ( $number, $message ) use ( &$noticed ) { $noticed = (string) $message; return true; } );
$partial_read = WPCPM_Form_Stash::read( $partial );
restore_error_handler();
ck( 'a form that leaves a key out is read in silence, and an absent one_shot means no outcome is one', array(
	$noticed,
	$partial_read,
	isset( $GLOBALS['transients'][ 'wpcpm_x_' . $id ] ),
), array( '', array( 'outcome' => 'sent', 'reference' => 'X-2' ), true ) );

echo "\n=== keep ===\n";
ck( 'keep() drops blank values, then blank keys, and leaves the rest', WPCPM_Form_Stash::keep( array( 'values' => array( 'a' => ' ', 'b' => 'x', 'c' => array() ), 'problems' => array(), 'reference' => 'R-1', 'note' => null ), $is_blank ), array( 'values' => array( 'b' => 'x' ), 'reference' => 'R-1' ) );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
