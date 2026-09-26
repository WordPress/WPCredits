<?php
/**
 * The WP-CLI command that needs no Airtable: `wp wpcredits seed-tracks` (Track Builder, phase T3c).
 *
 * WP_CLI is stood in for so that what the command prints and how it exits can be read, and the
 * track store so that a seed can be made to fail. `WP_CLI::error()` exits the process; here it
 * throws, and the runner reads the throw as exit code 1.
 *
 * Run from the plugin root:  php bin/test-cli.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

/** What WP_CLI::error() does, as something a check can catch. */
class ExitSignal extends Exception {}

class WP_CLI {
	public static $lines = array();
	public static function log( $message ) { self::$lines[] = 'log: ' . $message; }
	public static function warning( $message ) { self::$lines[] = 'warning: ' . $message; }
	public static function success( $message ) { self::$lines[] = 'success: ' . $message; }
	public static function error( $message ) { self::$lines[] = 'error: ' . $message; throw new ExitSignal( $message ); }
}

/**
 * The store, answering whatever a check seeds: a post id, false for a track already held, or a
 * WP_Error. Its `seed()` runs the one compile the store's own ends with, so a compile the command
 * added would count as a second.
 */
class WPCPM_Track_Store {
	public static $seeded   = array();
	public static $compiled = 0;
	public static function seed() { ++self::$compiled; return self::$seeded; }
	public static function compile() { ++self::$compiled; }
}

require_once __DIR__ . '/../includes/class-wpcpm-cli.php';

$fails = 0;
$total = 0;

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

/** Run the command; its exit code. */
function run_seed() {
	WP_CLI::$lines               = array();
	WPCPM_Track_Store::$compiled = 0;

	try {
		( new WPCPM_CLI() )->seed_tracks();
	} catch ( ExitSignal $e ) {
		return 1;
	}

	return 0;
}

echo "=== wp wpcredits seed-tracks ===\n";

WPCPM_Track_Store::$seeded = array( '150h' => 41, 'sensei' => false, 'design' => 43, 'dev' => 44 );
$exit                      = run_seed();

ck( 'every seed is reported, created and published or already there, the tracks compiled once, by seed(), and the command exits 0',
    array( $exit, WP_CLI::$lines, WPCPM_Track_Store::$compiled ),
    array(
        0,
        array(
            'log: 150h   created as track 41 and published',
            'log: sensei already in the Track Builder',
            'log: design created as track 43 and published',
            'log: dev    created as track 44 and published',
            'success: The four original tracks are in the Track Builder, and every published track is compiled.',
        ),
        1,
    ) );

WPCPM_Track_Store::$seeded = array(
	'150h'   => 41,
	'sensei' => new WP_Error( 'wpcpm_track_insert', 'The post could not be created.' ),
	'design' => new WP_Error( 'wpcpm_track_insert', 'The post could not be created.' ),
	'dev'    => 44,
);
$exit                      = run_seed();

ck( 'a seed that fails is warned about, the rest are still tried and compiled once, by seed(), and the command exits non-zero naming the ones that failed (decision 33)',
    array( $exit, WP_CLI::$lines, WPCPM_Track_Store::$compiled ),
    array(
        1,
        array(
            'log: 150h   created as track 41 and published',
            'warning: sensei: The post could not be created.',
            'warning: design: The post could not be created.',
            'log: dev    created as track 44 and published',
            'error: Not created: sensei, design. The others are in the Track Builder; run the command again once the reason is put right.',
        ),
        1,
    ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
