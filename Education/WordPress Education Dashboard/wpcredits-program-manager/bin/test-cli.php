<?php
/**
 * The WP-CLI command that needs no Airtable: `wp wpcredits seed_tracks` (Track Builder, phase T3c);
 * `wp wpcredits check_mentors`; and `wp wpcredits outcome_figures` (1.122.24), its read stood in.
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

/** The Mentor Status Checker, for `wp wpcredits check_mentors`: one mentor promoted, and a sentence about the Slack message. */
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

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
