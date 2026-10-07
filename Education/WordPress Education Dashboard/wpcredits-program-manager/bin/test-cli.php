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

/** The Mentor Status Checker, for `wp wpcredits check-mentors`: one mentor promoted, and a sentence about the Slack message. */
class WPCPM_Mentor_Checker {
	public static function is_configured() { return true; }
	public static function config() { return array( 'source_status' => 'Vetted - positive', 'target_status' => 'Active', 'course_title' => 'The course' ); }
}
class WPCPM_Mentor_Checker_Runner {
	public function __construct( $settings = null ) {}
	public function run_all( $apply = false, $progress = null ) {
		$row = array( 'name' => 'Mentor One', 'state' => 'completed', 'action_note' => 'Moved from "Vetted - positive" to "Active".' );
		call_user_func( $progress, $row );
		return array( 'rows' => array( $row ), 'summary' => array( 'checked' => 1, 'promoted' => 1 ) );
	}
}
class WPCPM_Mentor_Checker_Slack {
	public static $sentence = '';
	public static function status_sentence() { return self::$sentence; }
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

echo "\n=== wp wpcredits check-mentors ===\n";

// Since 1.122.4 a promoting run sends the Slack message as it ends, and whoever ran it from a shell
// sees how that went, after the counts and before the last word.
WP_CLI::$lines                       = array();
WPCPM_Mentor_Checker_Slack::$sentence = 'Last Slack message sent 1 min ago, naming 1 mentor.';
( new WPCPM_CLI() )->check_mentors( array(), array( 'promote' => true ) );

ck( 'a run says how the Slack message went, after the counts and before the last word',
    array_slice( WP_CLI::$lines, -2 ),
    array( 'log: Last Slack message sent 1 min ago, naming 1 mentor.', 'success: Mentor status check complete.' ) );

WP_CLI::$lines                       = array();
WPCPM_Mentor_Checker_Slack::$sentence = '';
( new WPCPM_CLI() )->check_mentors( array(), array() );

ck( 'and says nothing of Slack when there is nothing to say', array_slice( WP_CLI::$lines, -2 ), array( 'log: promoted     1', 'success: Mentor status check complete.' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
