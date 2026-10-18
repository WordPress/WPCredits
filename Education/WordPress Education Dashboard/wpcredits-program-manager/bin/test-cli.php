<?php
/**
 * The WP-CLI command that needs no Airtable: `wp wpcredits seed_tracks` (Track Builder, phase T3c);
 * `wp wpcredits check_mentors`; `wp wpcredits outcome_figures` (1.122.24), its read stood in; and
 * `wp wpcredits mentor_engagement` (1.122.26), its report read from the engagement reader's test input; and
 * `wp wpcredits mentor_airtable_row` (1.122.33), the Mentors row an application would write and the check row,
 * both read from a stand-in of the row class.
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
	public static function line( $message ) { self::$lines[] = 'line: ' . $message; }
	public static function warning( $message ) { self::$lines[] = 'warning: ' . $message; }
	public static function success( $message ) { self::$lines[] = 'success: ' . $message; }
	public static function error( $message ) { self::$lines[] = 'error: ' . $message; throw new ExitSignal( $message ); }
}

// `WP_CLI\Utils\format_items()`, kept in `WP_CLI::$lines` as 'items: <format> <json>'.
require __DIR__ . '/stubs/wp-cli-utils.php';

define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function wp_json_encode( $d ) { return json_encode( $d ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }

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

/**
 * The Mentor Status Checker, for `wp wpcredits check_mentors`: one mentor promoted, and a sentence about the Slack message.
 * `$configured` is the Airtable connection and `$skip` Stop checking mentors in Airtable, each set by a check.
 */
class WPCPM_Mentor_Checker {
	public static $configured = true;
	public static $skip       = false;
	public static function is_configured() { return self::$configured; }
	public static function config() { return array( 'source_status' => 'Vetted - positive', 'target_status' => 'Active', 'course_title' => 'The course', 'skip_airtable' => self::$skip ); }
}
class WPCPM_Mentor_Checker_Runner {
	public static $airtable_error = '';
	public function __construct( $settings = null ) {}
	public function run_all( $apply = false, $progress = null ) {
		$row = array( 'name' => 'Mentor One', 'state' => 'completed', 'action_note' => 'Moved from "Vetted - positive" to "Active".' );
		call_user_func( $progress, $row );
		return array( 'rows' => array( $row ), 'summary' => array( 'checked' => 1, 'promoted' => 1 ), 'airtable_error' => self::$airtable_error );
	}
	/** The note a row is printed with: here, the one it kept, as for every row but an application's. */
	public static function note_of( array $row ) { return isset( $row['action_note'] ) ? $row['action_note'] : ''; }
}
class WPCPM_Mentor_Checker_Slack {
	public static $sentence = '';
	public static $since    = null;
	public static function status_sentence( $since = 0 ) { self::$since = $since; return self::$sentence; }
}

/**
 * The outcome figures, for `wp wpcredits outcome_figures`: each answers what a check sets, and the
 * command's every call of refresh() is kept with what it asked.
 */
class WPCPM_Outcome_Figures {
	const FIGURES = array( 'graduation', 'recommend', 'keep' );
	public static $run       = array();
	public static $asked     = array();
	public static $names     = array();
	public static $stored    = array();
	public static $published = array();
	public static $status    = array();
	public static function refresh( array $args = array() ) { self::$asked[] = $args; return self::$run; }
	public static function field_names() { return self::$names; }
	public static function stored() { return self::$stored; }
	public static function published() { return self::$published; }
	public static function status() { return self::$status; }
}

/**
 * The Mentors row, for `wp wpcredits mentor_airtable_row`: an application is a post for one reference, and the
 * row class answers what a check sets. `preview()` and `check_row()` keep what they were asked; `check_row()`
 * answers the next of `$checks`. `left_out_sentence()` is the real one's sentence for a name the base lacks.
 */
class WP_Post {
	public $ID = 0;
}
class WPCPM_Airtable {}
/** The connection, for the commands that read or write Airtable: configured, unless a check says not. */
class WPCPM_Settings {
	public static $connected = true;
	public static function is_connected() { return self::$connected; }
}
class WPCPM_Mentor_Application {
	public static function by_reference( $reference ) {
		if ( 'MAPP-2026-0503' !== $reference ) {
			return null;
		}

		$post     = new WP_Post();
		$post->ID = 503;

		return $post;
	}
}
class WPCPM_Mentor_Airtable_Row {
	public static $preview = array();
	public static $checks  = array();
	public static $asked   = array();
	public static $reasons = array();
	public static $lines_when_asked = array();
	public static function preview( WP_Post $post, WPCPM_Airtable $airtable ) { self::$asked[] = array( 'preview', $post->ID ); return self::$preview; }
	public static function check_row( $email, WPCPM_Airtable $airtable ) { self::$asked[] = array( 'check', $email ); self::$lines_when_asked = WP_CLI::$lines; return array_shift( self::$checks ); }
	/** The real one's sentences for a name the base lacks and for columns that could not be read, with the reason when it is asked for, which is kept. */
	public static function left_out_sentence( array $left_out, $reasons = false ) {
		self::$reasons[] = $reasons;
		$sentences       = array();

		foreach ( $left_out as $column => $entry ) {
			if ( 'unread' === $entry['why'] ) {
				$sentences[] = 'The base\'s columns could not be read' . ( $reasons ? ': ' . implode( ', ', $entry['values'] ) : '.' );
				continue;
			}

			$sentences[] = sprintf( '%1$s: not in the base: %2$s.', $column, implode( ', ', $entry['values'] ) );
		}

		return implode( ' ', $sentences );
	}
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-engagement.php';
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

echo "=== wp wpcredits seed_tracks ===\n";

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

echo "\n=== wp wpcredits check_mentors ===\n";

// Since 1.122.4 a promoting run sends the Slack message as it ends, and whoever ran it from a shell
// sees how that went, after the counts and before the last word.
WP_CLI::$lines                       = array();
WPCPM_Mentor_Checker_Slack::$sentence = 'Last Slack message sent 1 min ago, naming 1 mentor.';
$before                              = time();
( new WPCPM_CLI() )->check_mentors( array(), array( 'promote' => true ) );

ck( 'a run says how the Slack message went, after the counts and before the last word',
    array_slice( WP_CLI::$lines, -2 ),
    array( 'log: Last Slack message sent 1 min ago, naming 1 mentor.', 'success: Mentor status check complete.' ) );
ck( 'and asks only about a send since the run began, so an older message is not reported as this run\'s',
    is_int( WPCPM_Mentor_Checker_Slack::$since ) && WPCPM_Mentor_Checker_Slack::$since >= $before && WPCPM_Mentor_Checker_Slack::$since <= time(), true );

WP_CLI::$lines                       = array();
WPCPM_Mentor_Checker_Slack::$sentence = '';
( new WPCPM_CLI() )->check_mentors( array(), array() );

ck( 'and says nothing of Slack when there is nothing to say', array_slice( WP_CLI::$lines, -2 ), array( 'log: promoted     1', 'success: Mentor status check complete.' ) );

/**
 * Run check_mentors with the Airtable connection and the stop as given; the lines it wrote.
 *
 * @param bool   $configured Whether Airtable is connected.
 * @param bool   $skip       Whether Stop checking mentors in Airtable is ticked.
 * @param bool   $promote    Whether --promote is given.
 * @param string $error      Why Airtable's mentors could not be read, or ''.
 * @return string[]
 */
function run_check_mentors( $configured, $skip, $promote = false, $error = '' ) {
	WP_CLI::$lines                               = array();
	WPCPM_Mentor_Checker::$configured            = $configured;
	WPCPM_Mentor_Checker::$skip                  = $skip;
	WPCPM_Mentor_Checker_Runner::$airtable_error = $error;
	WPCPM_Mentor_Checker_Slack::$sentence        = '';

	( new WPCPM_CLI() )->check_mentors( array(), $promote ? array( 'promote' => true ) : array() );

	WPCPM_Mentor_Checker::$configured            = true;
	WPCPM_Mentor_Checker::$skip                  = false;
	WPCPM_Mentor_Checker_Runner::$airtable_error = '';

	return WP_CLI::$lines;
}

$both = run_check_mentors( true, false );
$site = run_check_mentors( false, false );

ck( 'check_mentors names the sources it reads: the site\'s applications and Airtable\'s mentors, or the site\'s alone, and says when Airtable is not connected',
	array( array_slice( $both, 0, 2 ), array_slice( $site, 0, 3 ) ),
	array(
		array(
			'log: Checking the applications selected on this site and the Airtable mentors with status "Vetted - positive" for "The course" - report only.',
			'log: [  1] Mentor One                   completed        Moved from "Vetted - positive" to "Active".',
		),
		array(
			'log: Checking the applications selected on this site for "The course" - report only.',
			'log: Airtable is not connected: only the applications selected on this site are checked.',
			'log: [  1] Mentor One                   completed        Moved from "Vetted - positive" to "Active".',
		),
	) );
ck( 'and it no longer refuses without the connection: it runs to its last word',
	array( end( $site ), in_array( 'error: Airtable is not configured.', $site, true ) ), array( 'success: Mentor status check complete.', false ) );

$stopped = run_check_mentors( true, true, true );

ck( 'with Stop checking mentors in Airtable ticked the site\'s are named alone, and nothing says Airtable is not connected, since it is; --promote is said as it was',
	array_slice( $stopped, 0, 2 ),
	array(
		'log: Checking the applications selected on this site for "The course" - will promote to "Active".',
		'log: [  1] Mentor One                   completed        Moved from "Vetted - positive" to "Active".',
	) );

$both_down = run_check_mentors( false, true );

ck( 'ticked and not connected both, the site\'s alone are named and the connection is said too', array_slice( $both_down, 0, 2 ),
	array(
		'log: Checking the applications selected on this site for "The course" - report only.',
		'log: Airtable is not connected: only the applications selected on this site are checked.',
	) );

$unread = run_check_mentors( true, false, false, 'Airtable is out of reach.' );

ck( 'an Airtable read that failed is a warning above the counts, with what is left out',
	array( array_slice( $unread, 2, 2 ) ),
	array( array(
		'warning: Airtable could not be read, so its mentors were left out of this run: Airtable is out of reach.',
		'log: checked      1',
	) ) );
ck( 'and no warning is printed for a run that read both', in_array( true, array_map( function ( $line ) { return 0 === strpos( $line, 'warning:' ); }, $both ), true ), false );

echo "\n=== wp wpcredits outcome_figures ===\n";

/**
 * Run the command with some options; its exit code.
 *
 * @param array $options The options, as WP-CLI hands them over.
 * @return int
 */
function run_figures( array $options ) {
	WP_CLI::$lines                = array();
	WPCPM_Outcome_Figures::$asked = array();

	try {
		( new WPCPM_CLI() )->outcome_figures( array(), $options );
	} catch ( ExitSignal $e ) {
		return 1;
	}

	return 0;
}

$today_run = array(
	'result'  => 'dry_run',
	'reason'  => '',
	'message' => '',
	'figures' => array(
		'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
		'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
		'keep'       => array( 'share' => 79, 'count' => 245, 'n' => 310 ),
	),
	'rows'    => array( 'reports' => 413, 'feedback' => 834 ),
);
$counts    = array(
	'log: Students Reports: 413 rows read',
	'log:   Graduates (G):           361',
	'log:   Dropped out (D):         52',
	'log:   Graduation share:        87% of 413',
	'log: Feedback: 834 rows read (P: the answers of Likely, N: every answer to the question)',
	'log:   Would recommend:         P 277 of N 315, 88%',
	'log:   Keep contributing:       P 245 of N 310, 79%',
);

WPCPM_Outcome_Figures::$run   = $today_run;
WPCPM_Outcome_Figures::$names = array(
	'fldyXVlkChJaO9Q47' => 'Name',
	'fldCMdqqJGAUQ9nbV' => 'Status',
	'fldZXbvOTIGad6r90' => 'F4 How likely are you to recommend the program?',
	'fld8RZMdCLYKtwcLN' => '',
);
$exit = run_figures( array( 'refresh' => true, 'dry-run' => true ) );

ck( '--refresh --dry-run reads once, asking to store nothing, prints G, D, N and P for both questions, with P and N defined, and under a heading the name the base gives each field ID, and exits 0',
	array( $exit, WPCPM_Outcome_Figures::$asked, WP_CLI::$lines ),
	array(
		0,
		array( array( 'dry_run' => true, 'accept' => false ) ),
		array_merge(
			$counts,
			array(
				'log: The base\'s names for the four field IDs:',
				'log:   fldyXVlkChJaO9Q47    Name',
				'log:   fldCMdqqJGAUQ9nbV    Status',
				'log:   fldZXbvOTIGad6r90    F4 How likely are you to recommend the program?',
				'log:   fld8RZMdCLYKtwcLN    (not in its table)',
				'success: Dry run complete - nothing was stored.',
			)
		),
	) );

WPCPM_Outcome_Figures::$names = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 403): no further detail' );
run_figures( array( 'refresh' => true, 'dry-run' => true ) );

ck( 'a token that may not read the base\'s metadata is a warning, and the counts still stand',
	array_slice( WP_CLI::$lines, -2 ),
	array( 'warning: The base\'s names for the four fields could not be read: Airtable request failed (HTTP 403): no further detail', 'success: Dry run complete - nothing was stored.' ) );

// A base with no answers to a question has no share to print: the dry run says so rather than 0%.
WPCPM_Outcome_Figures::$run = array_merge(
	$today_run,
	array(
		'figures' => array(
			'graduation' => array( 'share' => null, 'count' => 0, 'n' => 0 ),
			'recommend'  => array( 'share' => null, 'count' => 0, 'n' => 0 ),
			'keep'       => array( 'share' => null, 'count' => 0, 'n' => 0 ),
		),
		'rows'    => array( 'reports' => 0, 'feedback' => 0 ),
	)
);
run_figures( array( 'refresh' => true, 'dry-run' => true ) );

ck( 'a dry run over a base with nothing to count says there is no share, and counts zero for each',
	array_slice( WP_CLI::$lines, 0, 7 ),
	array(
		'log: Students Reports: 0 rows read',
		'log:   Graduates (G):           0',
		'log:   Dropped out (D):         0',
		'log:   Graduation share:        no share of 0',
		'log: Feedback: 0 rows read (P: the answers of Likely, N: every answer to the question)',
		'log:   Would recommend:         P 0 of N 0, no share',
		'log:   Keep contributing:       P 0 of N 0, no share',
	) );

WPCPM_Outcome_Figures::$run = array_merge( $today_run, array( 'result' => 'stored' ) );
$exit                       = run_figures( array( 'refresh' => true ) );

ck( '--refresh stores what passes the guards, after the counts',
	array( $exit, WPCPM_Outcome_Figures::$asked, WP_CLI::$lines ),
	array( 0, array( array( 'dry_run' => false, 'accept' => false ) ), array_merge( $counts, array( 'success: The outcome figures are stored.' ) ) ) );

WPCPM_Outcome_Figures::$run = array_merge( $today_run, array( 'result' => 'held', 'reason' => 'moved', 'message' => 'Held: the graduation share moved from 87% to 76%, by more than 10 points.' ) );
$exit                       = run_figures( array( 'refresh' => true ) );

ck( 'a held read prints its counts, says why and how to store it once checked, and exits non-zero',
	array( $exit, array_slice( WP_CLI::$lines, -1 ) ),
	array( 1, array( 'error: Held: the graduation share moved from 87% to 76%, by more than 10 points. Nothing was stored. Once the counts above are checked, --refresh --accept stores them.' ) ) );

// A numerator of zero is never stored, with --accept or without: a hold for it must not send a person
// to --accept (the first parity run is where a renamed status or answer shows up as a zero).
WPCPM_Outcome_Figures::$run = array_merge(
	$today_run,
	array(
		'result'  => 'held',
		'reason'  => 'zero',
		'message' => 'Held: no answers of likely to keep contributing were counted.',
		'figures' => array_merge( $today_run['figures'], array( 'keep' => array( 'share' => 0, 'count' => 0, 'n' => 310 ) ) ),
	)
);
$zero_sentence = 'error: Held: no answers of likely to keep contributing were counted. Nothing was stored. --accept never stores a count of zero; a zero usually means a renamed status or answer, so check the base with --refresh --dry-run.';
$zero_runs     = array();

foreach ( array( array( 'refresh' => true ), array( 'refresh' => true, 'accept' => true ) ) as $options ) {
	$zero_runs[] = array( run_figures( $options ), array_slice( WP_CLI::$lines, -1 ), false !== strpos( implode( "\n", WP_CLI::$lines ), '--refresh --accept stores' ) );
}

ck( 'a hold for a count of zero ends in its own sentence, with --refresh alone and with --refresh --accept: nothing stored, --accept never stores a zero, check the base with a dry run, and never "--refresh --accept stores them"; non-zero exit',
	$zero_runs, array( array( 1, array( $zero_sentence ), false ), array( 1, array( $zero_sentence ), false ) ) );

WPCPM_Outcome_Figures::$run = array_merge( $today_run, array( 'result' => 'stored' ) );
run_figures( array( 'refresh' => true, 'accept' => true ) );

ck( '--refresh --accept asks to store a read the guards would hold',
	WPCPM_Outcome_Figures::$asked, array( array( 'dry_run' => false, 'accept' => true ) ) );

$refusals = array();

foreach ( array(
	array( 'result' => 'failed', 'reason' => 'refused', 'message' => 'Failed: Airtable refused the token.', 'figures' => array(), 'rows' => array() ),
	array( 'result' => 'busy', 'reason' => 'busy', 'message' => 'Another read of the outcome figures is running. Try again in a few minutes.', 'figures' => array(), 'rows' => array() ),
	array( 'result' => 'not_connected', 'reason' => 'not_connected', 'message' => 'Not connected.', 'figures' => array(), 'rows' => array() ),
) as $refused ) {
	WPCPM_Outcome_Figures::$run = $refused;
	$refusals[]                 = array( run_figures( array( 'refresh' => true ) ), WP_CLI::$lines );
}

ck( 'a failed read, a read already running and a site with no token each end at once with their sentence and a non-zero exit',
	$refusals,
	array(
		array( 1, array( 'error: Failed: Airtable refused the token.' ) ),
		array( 1, array( 'error: Another read of the outcome figures is running. Try again in a few minutes.' ) ),
		array( 1, array( 'error: Airtable is not configured.' ) ),
	) );

$misused = array();

foreach ( array( array( 'dry-run' => true ), array( 'accept' => true ), array( 'refresh' => true, 'dry-run' => true, 'accept' => true ) ) as $options ) {
	$misused[] = array( run_figures( $options ), WP_CLI::$lines, WPCPM_Outcome_Figures::$asked );
}

ck( '--dry-run or --accept without --refresh, or the two together, are refused before anything is read',
	$misused,
	array(
		array( 1, array( 'error: --dry-run and --accept go with --refresh.' ), array() ),
		array( 1, array( 'error: --dry-run and --accept go with --refresh.' ), array() ),
		array( 1, array( 'error: Use --dry-run or --accept, not both.' ), array() ),
	) );

WPCPM_Outcome_Figures::$stored    = array(
	'read'       => 1791788405,
	'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
	'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
	'keep'       => array( 'share' => 41, 'count' => 9, 'n' => 22 ),
);
WPCPM_Outcome_Figures::$published = array(
	'read'    => 1791788405,
	'figures' => array(
		'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
		'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
	),
);
WPCPM_Outcome_Figures::$status    = array( 'time' => 1792393209, 'result' => 'failed', 'reason' => 'unreachable', 'message' => 'Failed: Airtable request failed (HTTP 503): no further detail' );
$exit                             = run_figures( array() );

ck( 'with no option it reads nothing: the stored set and when it was read, what the homepage may show of it, and the last attempt',
	array( $exit, WPCPM_Outcome_Figures::$asked, WP_CLI::$lines ),
	array(
		0,
		array(),
		array(
			'log: Stored, read 2026-10-12 07:00 UTC:',
			'log:   graduation   87%, 361 of 413',
			'log:   recommend    88%, 277 of 315',
			'log:   keep         41%, 9 of 22',
			'log: Shown on the homepage: graduation, recommend',
			'log: Last attempt, 2026-10-19 07:00 UTC: failed - Failed: Airtable request failed (HTTP 503): no further detail',
			'success: Nothing was read or stored.',
		),
	) );

WPCPM_Outcome_Figures::$status = array( 'time' => 1792393209, 'result' => 'stored', 'reason' => '', 'message' => '' );
run_figures( array() );

ck( 'a stored attempt has no sentence, so the last attempt ends at its result',
	array_slice( WP_CLI::$lines, -2, 1 ), array( 'log: Last attempt, 2026-10-19 07:00 UTC: stored' ) );

WPCPM_Outcome_Figures::$stored    = array();
WPCPM_Outcome_Figures::$published = array();
WPCPM_Outcome_Figures::$status    = array();
run_figures( array() );

ck( 'and on a site that never stored any, says so',
	WP_CLI::$lines, array( 'log: No figures are stored yet.', 'log: Shown on the homepage: none', 'success: Nothing was read or stored.' ) );

// The weekly run holds the first set: the last attempt says how to check it and store it.
WPCPM_Outcome_Figures::$status = array( 'time' => 1791788405, 'result' => 'held', 'reason' => 'first', 'message' => 'Held: the first figures are stored by a person, not by the weekly read. Run wp wpcredits outcome_figures --refresh --dry-run and compare its counts with the WordPress Credits impact dashboard\'s, then store them with wp wpcredits outcome_figures --refresh.' );
run_figures( array() );

ck( 'a first set the weekly run held: nothing stored, and the last attempt says to compare the counts with the dashboard\'s with --refresh --dry-run and store them with --refresh',
	WP_CLI::$lines,
	array(
		'log: No figures are stored yet.',
		'log: Shown on the homepage: none',
		'log: Last attempt, 2026-10-12 07:00 UTC: held - Held: the first figures are stored by a person, not by the weekly read. Run wp wpcredits outcome_figures --refresh --dry-run and compare its counts with the WordPress Credits impact dashboard\'s, then store them with wp wpcredits outcome_figures --refresh.',
		'success: Nothing was read or stored.',
	) );

// The run option edited by hand: a part missing, or not the type a run writes. The line leaves it
// out, and no warning comes of it.
$cli_warnings = array();

set_error_handler( static function ( $number, $message ) use ( &$cli_warnings ) { $cli_warnings[] = $message; return true; } );

$odd_attempts = array();

foreach ( array(
	array( 'result' => 'failed' ),
	array( 'time' => '1792393209', 'result' => 'held', 'message' => array( 'Held' ) ),
	array( 'time' => 1792393209, 'result' => array( 'stored' ), 'message' => 7 ),
) as $odd ) {
	WPCPM_Outcome_Figures::$status = $odd;
	run_figures( array() );
	$odd_attempts[] = WP_CLI::$lines[2];
}

restore_error_handler();
WPCPM_Outcome_Figures::$status = array();

ck( 'a last attempt with no time, no sentence, or one of either that is not what a run writes, prints what it has, with no warning',
	array( $odd_attempts, $cli_warnings ),
	array( array( 'log: Last attempt: failed', 'log: Last attempt: held', 'log: Last attempt, 2026-10-19 07:00 UTC: unknown' ), array() ) );

echo "\n=== The help names each command as WP-CLI registers it ===\n";

// WP-CLI registers each public method under its own name, underscores and all, unless a
// `@subcommand` tag gives another (CommandFactory::create_subcommand() reads the tag, then the
// method's name, and replaces nothing). `sync_mentors` is `wp wpcredits sync_mentors`; the hyphen
// form is no command, and an example that says so is copied into an error. So the examples of each
// command, and every sentence naming one, are read here against the names WP-CLI would register.
$main    = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$reflect = new ReflectionClass( 'WPCPM_CLI' );
$root    = 1 === preg_match( "/WP_CLI::add_command\( '([a-z-]+)', 'WPCPM_CLI' \)/", $main, $found ) ? $found[1] : '';

$registered = array();

foreach ( $reflect->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
	// What WP-CLI's own is_good_method() lets through: public, not static, not a magic method.
	if ( $method->isStatic() || 0 === strpos( $method->getName(), '__' ) ) {
		continue;
	}

	$doc                 = (string) $method->getDocComment();
	$name                = 1 === preg_match( '/^\s*\*\s*@subcommand\s+(\S+)/m', $doc, $tag ) ? $tag[1] : $method->getName();
	$registered[ $name ] = $doc;
}

/** The commands a text runs, as `wp wpcredits <command>` names them. */
function named_commands( $text, $root ) {
	preg_match_all( '/\bwp\s+' . preg_quote( $root, '/' ) . '\s+([A-Za-z0-9_-]+)/', (string) $text, $named );

	return $named[1];
}

ck( 'the plugin registers the class as the command the help names', $root, 'wpcredits' );
ck( 'and the commands whose names carry an underscore are registered with it', array(
	isset( $registered['sync_mentors'] ),
	isset( $registered['check_mentors'] ),
	isset( $registered['seed_tracks'] ),
	isset( $registered['sync-mentors'] ),
), array( true, true, true, false ) );

foreach ( $registered as $name => $doc ) {
	ck( sprintf( '%s: its examples run %s, and it has at least one', $name, $name ), array_values( array_unique( named_commands( $doc, $root ) ) ), array( $name ) );
}

ck( 'the class description names only commands that are registered', array_values( array_diff( named_commands( $reflect->getDocComment(), $root ), array_keys( $registered ) ) ), array() );

// The readme's description and the guides say how to run these from a shell too. The readme's
// changelog is what each release said at the time, so it is left as it was written.
$texts = array( 'readme.txt' => (string) preg_replace( '/^== Changelog ==.*\z/ms', '', (string) file_get_contents( __DIR__ . '/../readme.txt' ) ) );

foreach ( array_merge( (array) glob( __DIR__ . '/../docs/*.md' ), (array) glob( __DIR__ . '/../docs/sections/*.md' ) ) as $guide ) {
	$texts[ 'docs/' . ( 'sections' === basename( dirname( $guide ) ) ? 'sections/' : '' ) . basename( $guide ) ] = (string) file_get_contents( $guide );
}

$unknown = array();

foreach ( $texts as $file => $text ) {
	foreach ( array_unique( array_diff( named_commands( $text, $root ), array_keys( $registered ) ) ) as $word ) {
		$unknown[] = $file . ': ' . $word;
	}
}

ck( 'the readme and the guides name only commands that are registered', $unknown, array() );

echo "\n=== wp wpcredits mentor_engagement ===\n";

// Every warning, notice and deprecation of this section is kept; the last check names any.
$me_warnings = array();

set_error_handler( static function ( $number, $message ) use ( &$me_warnings ) { $me_warnings[] = $message; return true; } );

$me_rows = array(
	array( 'id' => 1, 'name' => 'Ada', 'login' => 'ada', 'email' => 'ada@example.test', 'roles' => array( 'wpcpm_mentor' ), 'active' => true, 'students_now' => 2, 'students_past' => 0, 'last_sign_in' => 1792000000, 'last_visit' => 1792000000, 'schedule_saved' => true, 'weekly_hours' => true, 'meeting_link' => false ),
	array( 'id' => 2, 'name' => 'Bo', 'login' => 'bo', 'email' => 'bo@example.test', 'roles' => array( 'wpcpm_mentor' ), 'active' => true, 'students_now' => 0, 'students_past' => 0, 'last_sign_in' => 0, 'last_visit' => 0, 'schedule_saved' => false, 'weekly_hours' => false, 'meeting_link' => false ),
);
WPCPM_Mentor_Engagement::for_tests( array( 'accounts' => $me_rows, 'notes' => array(), 'calls' => array() ) );
$cli = new WPCPM_CLI();

WP_CLI::$lines = array();
$cli->mentor_engagement( array(), array( 'summary' => true, 'format' => 'json', 'now' => 1792100000 ) );
$json = json_decode( substr( WP_CLI::$lines[0], strlen( 'line: ' ) ), true );
ck( '--summary --format=json prints the summary as one object', array( count( WP_CLI::$lines ), $json['active'], $json['never'], $json['with_students_hours'] ), array( 1, 2, 1, 1 ) );

WP_CLI::$lines = array();
$cli->mentor_engagement( array(), array( 'view' => 'never', 'format' => 'csv', 'now' => 1792100000 ) );
ck( 'a view prints its rows with the export fields, and no email anywhere', array( false !== strpos( WP_CLI::$lines[0], '"Bo"' ), false === strpos( WP_CLI::$lines[0], '"Ada"' ), false === strpos( WP_CLI::$lines[0], '@example.test' ), 0 === strpos( WP_CLI::$lines[0], 'items: csv ' ) ), array( true, true, true, true ) );

foreach ( array( array( 'format' => 'xml' ), array( 'view' => 'nope' ) ) as $bad ) {
	WP_CLI::$lines = array();
	$threw = '';
	try {
		$cli->mentor_engagement( array(), $bad );
	} catch ( ExitSignal $e ) {
		$threw = $e->getMessage();
	}
	ck( 'refused: ' . json_encode( $bad ), '' !== $threw, true );
}

// Review finding H1: --now is a Unix time, a positive whole number, or the command refuses.
foreach ( array( 'abc', '-5', '0', '1.5', '1e9', '', ' 1792100000', true, 1.5, 0, -5 ) as $bad_now ) {
	WP_CLI::$lines = array();
	$threw         = '';
	try {
		$cli->mentor_engagement( array(), array( 'summary' => true, 'format' => 'json', 'now' => $bad_now ) );
	} catch ( ExitSignal $e ) {
		$threw = $e->getMessage();
	}
	ck( 'refused with the way to say it, and nothing printed: --now=' . var_export( $bad_now, true ), array( $threw, WP_CLI::$lines ), array( 'Use --now=<Unix time>.', array( 'error: Use --now=<Unix time>.' ) ) );
}

foreach ( array( '1792100000', 1792100000 ) as $good_now ) {
	WP_CLI::$lines = array();
	$cli->mentor_engagement( array(), array( 'summary' => true, 'format' => 'json', 'now' => $good_now ) );
	ck( 'a whole number is taken, as a string or an int: --now=' . var_export( $good_now, true ), array( count( WP_CLI::$lines ), 0 === strpos( WP_CLI::$lines[0], 'line: {' ) ), array( 1, true ) );
}

// Review finding H2: --view means nothing to --summary, and the command says so on STDERR.
WP_CLI::$lines = array();
$cli->mentor_engagement( array(), array( 'summary' => true, 'view' => 'never', 'format' => 'json', 'now' => 1792100000 ) );
ck(
	'--summary with --view warns that --view is ignored, and the JSON stays one line',
	array(
		array_values( array_filter( WP_CLI::$lines, static function ( $l ) { return 0 === strpos( $l, 'warning: ' ); } ) ),
		count( array_filter( WP_CLI::$lines, static function ( $l ) { return 0 === strpos( $l, 'line: ' ); } ) ),
		count( WP_CLI::$lines ),
	),
	array( array( 'warning: --view is ignored with --summary.' ), 1, 2 )
);

WP_CLI::$lines = array();
$cli->mentor_engagement( array(), array( 'summary' => true, 'format' => 'json', 'now' => 1792100000 ) );
ck( '--summary alone warns of nothing', count( WP_CLI::$lines ), 1 );

WP_CLI::$lines = array();
$cli->mentor_engagement( array(), array( 'view' => 'never', 'format' => 'csv', 'now' => 1792100000 ) );
ck( '--view alone warns of nothing', array_filter( WP_CLI::$lines, static function ( $l ) { return 0 === strpos( $l, 'warning: ' ); } ), array() );

WPCPM_Mentor_Engagement::for_tests( null );

restore_error_handler();

ck( 'and the section raised no PHP warning, notice or deprecation', $me_warnings, array() );

echo "\n=== wp wpcredits mentor_airtable_row ===\n";

/** Run the command; what it printed, the exit read from the last line. */
function cli_lines( $method, $args, $assoc = array() ) {
	WP_CLI::$lines = array();

	try {
		( new WPCPM_CLI() )->$method( $args, $assoc );
	} catch ( ExitSignal $e ) {
		// WP_CLI::error() has printed its line, and the process would have ended there.
		unset( $e );
	}

	return WP_CLI::$lines;
}

$row_warnings = array();

set_error_handler( static function ( $number, $message ) use ( &$row_warnings ) { $row_warnings[] = $message; return true; } );

WPCPM_Mentor_Airtable_Row::$preview = array(
	'cells'    => array(
		'Full Name'                => 'Ada Example',
		'Languages'                => array( 'recLANGENGLISH001', 'recLANGPOLISH0001' ),
		'Available hours per week' => 2.5,
	),
	'left_out' => array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Wu Chinese (Shanghainese)' ) ) ),
);
ck( 'mentor_airtable_row prints each cell an application would write, lists values with commas, says what would be left out, and writes nothing',
	cli_lines( 'mentor_airtable_row', array( 'MAPP-2026-0503' ) ),
	array( 'log: Full Name: Ada Example', 'log: Languages: recLANGENGLISH001, recLANGPOLISH0001', 'log: Available hours per week: 2.5', 'log: Languages: not in the base: Wu Chinese (Shanghainese).', 'success: Nothing was written to Airtable.' ) );
ck( 'and asked the row class about that application alone', WPCPM_Mentor_Airtable_Row::$asked, array( array( 'preview', 503 ) ) );
WPCPM_Mentor_Airtable_Row::$preview = array(
	'cells'    => array( 'Full Name' => 'Ada Example', 'WordPress profile' => 'https://profiles.wordpress.org/ada-example/', 'Email' => 'ada@example.test', 'Status' => 'Active' ),
	'left_out' => array( '*' => array( 'why' => 'unread', 'values' => array( 'Operation timed out after 20000 milliseconds.' ) ) ),
);
WPCPM_Mentor_Airtable_Row::$reasons = array();
ck( 'columns that could not be read are said with the reason, which nothing else in the preview says: the command asks the row class for the reasons',
	array( cli_lines( 'mentor_airtable_row', array( 'MAPP-2026-0503' ) ), WPCPM_Mentor_Airtable_Row::$reasons ),
	array(
		array( 'log: Full Name: Ada Example', 'log: WordPress profile: https://profiles.wordpress.org/ada-example/', 'log: Email: ada@example.test', 'log: Status: Active', 'log: The base\'s columns could not be read: Operation timed out after 20000 milliseconds.', 'success: Nothing was written to Airtable.' ),
		array( true ),
	) );
WPCPM_Mentor_Airtable_Row::$preview = array(
	'cells'    => array( 'Teacher from a participating institution?' => false, 'Sponsored' => true ),
	'left_out' => array(),
);
ck( 'a yes or no cell prints as Yes or No, and nothing left out adds no line',
	cli_lines( 'mentor_airtable_row', array( 'MAPP-2026-0503' ) ),
	array( 'log: Teacher from a participating institution?: No', 'log: Sponsored: Yes', 'success: Nothing was written to Airtable.' ) );
ck( 'and refuses a reference no application carries', cli_lines( 'mentor_airtable_row', array( 'MAPP-2026-9999' ) ), array( 'error: No mentor application has the reference MAPP-2026-9999.' ) );
ck( 'and asks for a reference when it is given none', cli_lines( 'mentor_airtable_row', array() ), array( 'error: Give the application\'s reference, such as MAPP-2026-0503, or use --check.' ) );

/** What the check row answers when it wrote, read back and deleted a row with nothing wrong. */
function check_result( array $over = array() ) {
	$columns = array( 'Full Name', 'WordPress profile', 'Email', 'Status', 'Country' );

	return array_merge(
		array(
			'written'      => $columns,
			'record'       => 'recNEWMENTOR00001',
			'kept'         => $columns,
			'missing'      => array(),
			'stops'        => array(),
			'notes'        => array(),
			'found_before' => array(),
			'cleaned'      => array(),
			'deleted'      => true,
			'error'        => '',
			'read_error'   => '',
			'delete_error' => '',
			'transport'    => false,
		),
		$over
	);
}

/** The line the check logs before it asks the row class anything, naming the address to look for if the run is killed. */
$check_announced = 'log: Writing the check row for owner+check@example.test. If this stops before it ends, look for that address in the Mentors table and delete the row by hand.';

/**
 * The check as the command is asked it, with the next answer the row class gives: the lines it
 * printed after the one that announces the address, which the checks of the announcement pin.
 */
function check_lines( array $result, array $assoc = array( 'check' => true, 'email' => 'owner+check@example.test' ) ) {
	global $check_announced;

	WPCPM_Mentor_Airtable_Row::$checks = array( $result );
	$lines                             = cli_lines( 'mentor_airtable_row', array(), $assoc );

	return isset( $lines[0] ) && $check_announced === $lines[0] ? array_slice( $lines, 1 ) : $lines;
}

WPCPM_Mentor_Airtable_Row::$asked = array();
ck( 'the check prints what was written and kept, a note as a note, and says the row is gone and nothing on the site changed',
	check_lines( check_result( array( 'notes' => array( 'Contribution Area - Expertise: not in the base: Hosting.' ) ) ) ),
	array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'log: Kept by the base: Full Name, WordPress profile, Email, Status, Country', 'log: Only left out: Contribution Area - Expertise: not in the base: Hosting.', 'success: The check row recNEWMENTOR00001 was written, read back and deleted. Nothing on this site changed.' ) );
ck( 'it asks the row class for the address given, and previews nothing', WPCPM_Mentor_Airtable_Row::$asked, array( array( 'check', 'owner+check@example.test' ) ) );
ck( 'it logs the address to look for before the row class is asked anything, so a process killed in the middle leaves the line in the output',
	WPCPM_Mentor_Airtable_Row::$lines_when_asked,
	array( $check_announced ) );
WPCPM_Mentor_Airtable_Row::$checks = array( check_result() );
ck( 'and that line is the first of the check\'s output, whatever follows', array_slice( cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => 'owner+check@example.test' ) ), 0, 2 ), array( $check_announced, 'log: Written: Full Name, WordPress profile, Email, Status, Country' ) );
ck( 'a stop is a warning, and the check ends in an error that holds the sitting',
	check_lines( check_result( array( 'kept' => array( 'Full Name', 'WordPress profile', 'Email', 'Status' ), 'missing' => array( 'Country' ), 'stops' => array( 'Country: sent, and not read back.' ) ) ) ),
	array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'log: Kept by the base: Full Name, WordPress profile, Email, Status', 'warning: Country: sent, and not read back.', 'error: The check row recNEWMENTOR00001 was written, read back and deleted, but the live base would not take the whole row. Put that right before switching Make mentors from applications on.' ) );
ck( 'a row already holding the check address is named, and nothing was written',
	check_lines( check_result( array( 'written' => array(), 'record' => '', 'kept' => array(), 'deleted' => false, 'found_before' => array( 'recLEFTOVER000001' ), 'error' => 'Rows with the check address are already in the Mentors table: recLEFTOVER000001. Delete them by hand, then check again.' ) ) ),
	array( 'error: Rows with the check address are already in the Mentors table: recLEFTOVER000001. Delete them by hand, then check again.' ) );
ck( 'a refused create is printed in the base\'s words with what the search after it found',
	check_lines( check_result( array( 'record' => '', 'kept' => array(), 'deleted' => false, 'error' => 'Airtable refused the write. The search after it found nothing, so nothing was stored and nothing was deleted.' ) ) ),
	array( 'error: Airtable refused the check row: Airtable refused the write. The search after it found nothing, so nothing was stored and nothing was deleted.' ) );
ck( 'a create whose row the search after it found and deleted says so in the same line',
	check_lines( check_result( array( 'record' => '', 'kept' => array(), 'deleted' => false, 'cleaned' => array( 'recLOSTCHECK00001' ), 'error' => 'Operation timed out after 20000 milliseconds. The search after it found recLOSTCHECK00001 and deleted it.', 'transport' => true ) ) ),
	array( 'error: The check row\'s create failed: Operation timed out after 20000 milliseconds. The search after it found recLOSTCHECK00001 and deleted it.' ) );
ck( 'a search after the create that failed too names the address to look for by hand',
	check_lines( check_result( array( 'record' => '', 'kept' => array(), 'deleted' => false, 'error' => 'Operation timed out after 20000 milliseconds. Could not search the Mentors table after the create failed, so a row may have been stored: look for owner+check@example.test in the Mentors table and delete it by hand.', 'transport' => true ) ) ),
	array( 'error: The check row\'s create failed: Operation timed out after 20000 milliseconds. Could not search the Mentors table after the create failed, so a row may have been stored: look for owner+check@example.test in the Mentors table and delete it by hand.' ) );
ck( 'and a row the search found that could not be deleted names the row to delete by hand',
	check_lines( check_result( array( 'record' => '', 'kept' => array(), 'deleted' => false, 'error' => 'Operation timed out after 20000 milliseconds. The row recLOSTCHECK00001 could not be deleted: delete it by hand in the Mentors table.', 'transport' => true ) ) ),
	array( 'error: The check row\'s create failed: Operation timed out after 20000 milliseconds. The row recLOSTCHECK00001 could not be deleted: delete it by hand in the Mentors table.' ) );
ck( 'and a transport error whose search found no row says Airtable may still store it, after the neutral prefix, never as a refusal',
	check_lines( check_result( array( 'record' => '', 'kept' => array(), 'deleted' => false, 'error' => 'Operation timed out after 20000 milliseconds. The search found no row yet, but Airtable may still store it: look for owner+check@example.test in the Mentors table in a minute and delete it by hand.', 'transport' => true ) ) ),
	array( 'error: The check row\'s create failed: Operation timed out after 20000 milliseconds. The search found no row yet, but Airtable may still store it: look for owner+check@example.test in the Mentors table in a minute and delete it by hand.' ) );
ck( 'a check stopped before anything was sent prints the reason as it is',
	check_lines( check_result( array( 'written' => array(), 'record' => '', 'kept' => array(), 'deleted' => false, 'error' => 'The activation\'s status, Active, is not a choice of the Status column.' ) ) ),
	array( 'error: The activation\'s status, Active, is not a choice of the Status column.' ) );
ck( 'a refused delete names the row to delete by hand',
	check_lines( check_result( array( 'deleted' => false, 'delete_error' => 'Airtable refused the delete.' ) ) ),
	array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'log: Kept by the base: Full Name, WordPress profile, Email, Status, Country', 'error: The check row recNEWMENTOR00001 was written but could not be deleted: Airtable refused the delete. Delete it in Airtable by hand.' ) );
ck( 'a refused delete that has no full stop is closed with one, and comes after the warnings',
	check_lines( check_result( array( 'deleted' => false, 'delete_error' => 'Operation timed out', 'stops' => array( 'Country: sent, and not read back.' ), 'missing' => array( 'Country' ), 'kept' => array( 'Full Name' ) ) ) ),
	array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'log: Kept by the base: Full Name', 'warning: Country: sent, and not read back.', 'error: The check row recNEWMENTOR00001 was written but could not be deleted: Operation timed out. Delete it in Airtable by hand.' ) );
ck( 'a read-back that failed says the check proved nothing about the columns, and prints no list of what the base kept',
	check_lines( check_result( array( 'kept' => array(), 'read_error' => 'Airtable is out of reach.' ) ) ),
	array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'error: The check row recNEWMENTOR00001 was written and deleted, but it could not be read back: Airtable is out of reach. Run the check again.' ) );
WPCPM_Mentor_Airtable_Row::$asked = array();
ck( 'and without an address it asks for one, and logs no address', cli_lines( 'mentor_airtable_row', array(), array( 'check' => true ) ), array( 'error: Give the test mailbox\'s address as --email=<address>.' ) );
ck( 'and asks the row class nothing', WPCPM_Mentor_Airtable_Row::$asked, array() );

WPCPM_Settings::$connected = false;
WPCPM_Mentor_Airtable_Row::$asked = array();
ck( 'with no Airtable connection configured the preview refuses as the dry run does, and asks nothing',
	array( cli_lines( 'mentor_airtable_row', array( 'MAPP-2026-0503' ) ), WPCPM_Mentor_Airtable_Row::$asked ),
	array( array( 'error: Airtable is not configured.' ), array() ) );
WPCPM_Mentor_Airtable_Row::$checks = array( check_result() );
ck( 'and so does the check, before it logs an address',
	array( cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => 'owner+check@example.test' ) ), WPCPM_Mentor_Airtable_Row::$asked ),
	array( array( 'error: Airtable is not configured.' ), array() ) );
WPCPM_Settings::$connected = true;
ck( 'an address given as a bare flag is no address', cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => true ) ), array( 'error: Give the test mailbox\'s address as --email=<address>.' ) );

restore_error_handler();

ck( 'and the section raised no PHP warning, notice or deprecation', $row_warnings, array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
