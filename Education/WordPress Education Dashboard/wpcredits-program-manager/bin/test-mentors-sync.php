<?php
/**
 * The mentors sync tells the mentors (the assign step hands every mentor's list to
 * WPCPM_Mentor_Assigned, and the run's report says what came of it).
 *
 * What this pins, and why:
 *
 * - **The first run tells nobody.** A site with no stored set writes the lists as before, sends
 *   nothing, and reports `told` 0.
 * - **The first run keeps the lists it is about to replace.** While no set exists, the revoke step
 *   puts every account's stored list (IDs only) in the run's state before it deletes any, and the
 *   assign step hands them to the class as `known`: a mentor the first read leaves out is remembered,
 *   and not emailed about every student they already have when a later run reads them again.
 * - **A new student is told once.** The next run, with one more current student for one mentor,
 *   emails that mentor, reports `told` 1, and the list is on the mentor's account before the email
 *   goes out (the email's link opens a card that holds the student).
 * - **By hand or by the schedule.** `start()` keeps `by_hand` in the run's state: false under cron,
 *   true anywhere else, and a state from before this release reads as false. The brake lets a run
 *   started by hand through and holds one started by the schedule.
 * - **The notices.** Mentors left for the next run, emails refused, and mentors the brake held each
 *   say so in the run's notices when their number is above 0, in numbers only: never a name or an
 *   address.
 * - **A mentor with no account** is passed over as before, and every other stat of the run is what it
 *   was. A sync that lost its lock stops sending. A dry run neither sends nor writes the set.
 *
 * Runs on the shared stand-in (bin/stubs/mentor-applications.php) with the real WPCPM_Mentors_Sync and
 * WPCPM_Mentor_Assigned, and stand-ins for the mail layer and the settings. Every person is invented,
 * at example.org.
 *
 * Run from the plugin root:  php bin/test-mentors-sync.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['settings'] = array();
$GLOBALS['cron_now'] = false;
$GLOBALS['mail']     = array();
$GLOBALS['take']     = true;
$GLOBALS['steal']    = false;
$GLOBALS['seen']     = array();

function wp_doing_cron() {
	return (bool) $GLOBALS['cron_now'];
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $time ) {
		return gmdate( $format, $time );
	}
}
if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $time, $hook, $args = array() ) {
		$GLOBALS['cron'][ $hook ] = array( 'at' => (int) $time, 'every' => '' );
		return true;
	}
}
if ( ! function_exists( 'wp_get_scheduled_event' ) ) {
	function wp_get_scheduled_event( $hook ) {
		return false;
	}
}

class WPCPM_Settings {
	public static function is_connected() {
		return ! array_key_exists( 'connected', $GLOBALS ) || $GLOBALS['connected'];
	}
	public static function get() {
		return array_merge(
			array(
				'mentors_table'      => 'Mentors',
				'mentor_status'      => 'Active',
				'student_statuses'   => array( 'In Sensei' ),
				'past_statuses'      => array( 'Graduate' ),
				'on_inactive'        => 'revoke',
				'reports_table'      => 'Reports',
				'students_table'     => 'Students',
				'institutions_table' => 'Institutions',
				'teams_table'        => 'Teams',
			),
			$GLOBALS['settings']
		);
	}
	public static function get_value( $key, $fallback = null ) {
		$all = self::get();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}
}

class WPCPM_Airtable {
	public function formula_in( $field, array $values ) {
		return 'OR(' . $field . ')';
	}
	public function fetch_page( $table, array $args ) {
		return array( 'records' => array(), 'offset' => null );
	}
	public static function flatten( $value ) {
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}
	public function fetch_schema() {
		return new WP_Error( 'wpcpm_airtable_error', 'No schema scope.' );
	}
	public function fetch_all( $table, array $args = array() ) {
		return array();
	}
}

class WPCPM_Program {
	public static function label( $status ) {
		return trim( (string) $status );
	}
}
// The site's rule (`WPCPM_Sponsors_Index::is_test_name()`): the word TEST, in capitals, first.
class WPCPM_Test_Institutions {
	public static function is_test( $name ) {
		return is_string( $name ) && 1 === preg_match( '/^TEST(?![\p{L}\p{N}])/u', trim( $name ) );
	}
}
class WPCPM_Mail_Catalog {
	const TYPE_MENTOR = 'mentor';
}
class WPCPM_Mail_Templates {
	public static function page_of_type( $type ) {
		return 'https://example.org/mentor-report-card/';
	}
	public static function mail( $id, $values ) {
		return array(
			'subject' => '[' . $values['site_name'] . '] ' . $values['count'] . ' assigned to you',
			'body'    => $values['students_list'] . "\n" . $values['dashboard_link'],
		);
	}
}

/** The mail layer: records each email, and what the mentor's list held when it went out. */
class WPCPM_Mail {
	public static function site_name() {
		return 'Example Site';
	}
	public static function send( $user_id, $context, $build ) {
		$mail   = call_user_func( $build, null );
		$list   = (array) get_user_meta( $user_id, WPCPM_Mentors_Sync::META_MENTEES, true );
		$report = array();

		foreach ( $list as $row ) {
			$report[] = isset( $row['record_id'] ) ? $row['record_id'] : '';
		}

		$GLOBALS['mail'][] = array(
			'to'      => (int) $user_id,
			'subject' => $mail['subject'],
			'on_list' => $report,
		);

		if ( $GLOBALS['steal'] ) {
			// Another tick takes the lock after this email: this one is no longer the sync's.
			$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOCK ] = '99999999999';
		}

		return (bool) $GLOBALS['take'];
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-guard.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
require_once __DIR__ . '/stubs/user-count-query.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-provision.php';
if ( getenv( 'WPCPM_RECORD_RUN' ) ) {
	/** A recording stand-in: what the sync hands to run(), answered as the check says. */
	class WPCPM_Mentor_Assigned {
		public static function run( array $lists, array $options = array() ) {
			$GLOBALS['recorded'] = array( 'lists' => $lists, 'by_hand' => $options['by_hand'], 'alive' => is_callable( $options['alive'] ) );

			return array( 'seeded' => false, 'told' => 0, 'students' => 0, 'waiting' => 0, 'failed' => 0, 'braked' => 0 );
		}
	}
} else {
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-assigned.php';
}

$fails = 0;

function ck( $label, $got, $want ) {
	global $fails;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

function fresh() {
	$GLOBALS['users']    = array();
	$GLOBALS['umeta']    = array();
	$GLOBALS['opts']     = array( 'date_format' => 'j F Y' );
	$GLOBALS['cron']     = array();
	$GLOBALS['settings'] = array();
	$GLOBALS['cron_now'] = false;
	$GLOBALS['mail']     = array();
	$GLOBALS['take']     = true;
	$GLOBALS['steal']    = false;
}

function record( $n ) {
	return sprintf( 'recMENTOR%04d', $n );
}

/** A state at the assign step: `$mentors` mentors (the first `$no_account` without an account). */
function assign_state( $mentors, array $reports, $by_hand = null, $no_account = 0 ) {
	$list = array();

	for ( $n = 1; $n <= $mentors; $n++ ) {
		$list[ record( $n ) ] = array(
			'user_id' => $n <= $no_account ? 0 : 1000 + $n,
			'name'    => 'Mentor ' . $n,
		);
	}

	$state = array(
		'phase'   => 'assign',
		'offset'  => null,
		// Each state is a sync of its own, so each has its own start: the email's ceiling counts per start.
		'started' => time() + ( $GLOBALS['sync_starts'] = ( $GLOBALS['sync_starts'] ?? 0 ) + 1 ),
		'touched' => time(),
		'steps'   => array(),
		'mentors' => $list,
		'reports' => $reports,
		'tutors'  => array(),
		'study'   => array(),
		'access'  => array(),
		'stats'   => WPCPM_Mentors_Sync::empty_stats(),
		'notices' => array(),
	);

	if ( null !== $by_hand ) {
		$state['by_hand'] = $by_hand;
	}

	return $state;
}

/** A report row for mentor number `$n`. */
function report( $id, $n, $name = 'Ada Example' ) {
	return array(
		'record_id'  => $id,
		'name'       => $name,
		'email'      => 'ada@example.org',
		'email_key'  => 'ada@example.org',
		'status'     => 'In Sensei',
		'is_past'    => false,
		'start'      => '2026-12-02',
		'end'        => '2027-03-01',
		'institution' => 'Example University',
		'mentor_ids' => array( record( $n ) ),
	);
}

/** `$count` mentors each with a student they already have. */
function known( $count ) {
	$reports = array();

	for ( $n = 1; $n <= $count; $n++ ) {
		$reports[] = report( sprintf( 'recKNOWN%04d', $n ), $n, 'Ben Example' );
	}

	return $reports;
}

/** `$k` of those mentors with one more current student. */
function more( array $reports, $k ) {
	for ( $n = 1; $n <= $k; $n++ ) {
		$reports[] = report( sprintf( 'recNEW%06d', $n ), $n, 'Cy Example' );
	}

	return $reports;
}

function assign( array &$state ) {
	// The tick holds the lock while it works the step: the email's `alive` asks it.
	delete_option( WPCPM_Mentors_Sync::OPT_LOCK );
	$GLOBALS['opts'] = array_diff_key( $GLOBALS['opts'], array( WPCPM_Mentors_Sync::OPT_LOCK => 1 ) );
	$take = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'acquire_lock' );
	if ( PHP_VERSION_ID < 80100 ) {
		$take->setAccessible( true );
	}
	$take->invoke( null );

	$method = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'phase_assign' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method->invokeArgs( null, array( &$state ) );
}

/** The revoke step, as a tick runs it. */
function revoke( array &$state ) {
	$method = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'phase_revoke' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method->invokeArgs( null, array( &$state, WPCPM_Settings::get() ) );
}

/** A run at the assign step, seeded by a first run over `$reports_before`. */
function seeded( $mentors, array $before, $no_account = 0 ) {
	$state = assign_state( $mentors, $before, true, $no_account );
	assign( $state );
	$GLOBALS['mail'] = array();
}

if ( getenv( 'WPCPM_RECORD_RUN' ) ) {
	// The child process: mentor 1 has no account, mentor 2 a student, mentor 3 none.
	fresh();
	$state = assign_state( 3, known( 2 ), true, 1 );
	unset( $state['reports'][0] );
	$state['reports'] = array_values( $state['reports'] );
	assign( $state );
	$out = array();
	foreach ( $GLOBALS['recorded']['lists'] as $id => $entry ) {
		$out[ $id ] = array( $entry['user_id'], array_column( $entry['rows'], 'record_id' ) );
	}
	echo 'RECORDED ' . json_encode( array( $out, $GLOBALS['recorded']['by_hand'], $GLOBALS['recorded']['alive'] ) ) . "\n";
	exit( 0 );
}
echo "\n=== The first run tells nobody ===\n";
fresh();
$state = assign_state( 3, known( 3 ), false );
assign( $state );
ck( 'the lists are written as before, no email goes, and told is 0',
	array( count( $GLOBALS['mail'] ), $state['stats']['told'], $state['stats']['assigned'], $state['stats']['mentors_with_students'], $state['notices'], $state['phase'] ),
	array( 0, 0, 3, 3, array( 'The site now remembers who is on every mentor\'s list. Nobody was emailed about students already there.' ), 'done' ) );
ck( 'every mentor holds their list', count( get_user_meta( 1002, WPCPM_Mentors_Sync::META_MENTEES, true ) ), 1 );

echo "\n=== A new student is told once ===\n";
$state = assign_state( 3, more( known( 3 ), 1 ), false );
assign( $state );
ck( 'one more current student for one mentor: one email to that mentor, told is 1',
	array( array_column( $GLOBALS['mail'], 'to' ), $state['stats']['told'], $state['notices'] ),
	array( array( 1001 ), 1, array() ) );
ck( 'the second run has no seeding notice (above: notices are empty)', $state['notices'], array() );
ck( 'the list was on the account when the email went out, the new student on it',
	in_array( 'recNEW000001', $GLOBALS['mail'][0]['on_list'], true ),
	true );
ck( 'no name or address is in the subject', 0 === preg_match( '/Cy|example\.org/i', $GLOBALS['mail'][0]['subject'] ), true );

$GLOBALS['mail'] = array();
$state           = assign_state( 3, more( known( 3 ), 1 ), false );
assign( $state );
ck( 'the same list again tells nobody', array( count( $GLOBALS['mail'] ), $state['stats']['told'] ), array( 0, 0 ) );

echo "\n=== The first run keeps the lists it is about to replace ===\n";
// Three registered mentors, each holding the one student 1.123.89 stored on their account. The first
// full run after the release reads only two of them: the third is not Active in that read, so revoke
// deletes their list. Then a run that reads all three.
fresh();
for ( $n = 1; $n <= 3; $n++ ) {
	$uid                    = 1000 + $n;
	$GLOBALS['users'][ $uid ] = new WP_User( $uid, 'Mentor ' . $n, 'm' . $n . '@example.org', array( 'wpcpm_mentor' ) );
	$row                    = known( 3 )[ $n - 1 ];
	unset( $row['mentor_ids'], $row['email_key'] );
	$GLOBALS['umeta'][ $uid ] = array(
		WPCPM_Mentors_Sync::META_RECORD_ID => record( $n ),
		WPCPM_Mentors_Sync::META_ACTIVE    => 1,
		WPCPM_Mentors_Sync::META_MENTEES   => array( $row ),
	);
}
$state = assign_state( 2, array_slice( known( 3 ), 0, 2 ), false );
$state['phase'] = 'revoke';
$revoked = revoke( $state );
ck( 'the first run: revoke passes and deletes the third mentor\'s list', array( $revoked, get_user_meta( 1003, WPCPM_Mentors_Sync::META_MENTEES, true ) ), array( true, '' ) );
ck( 'but the run\'s state kept every stored list first, IDs only',
	isset( $state['stored_lists'] ) ? $state['stored_lists'] : null,
	array( record( 1 ) => array( 'recKNOWN0001' ), record( 2 ) => array( 'recKNOWN0002' ), record( 3 ) => array( 'recKNOWN0003' ) ) );
$state['phase'] = 'assign';
assign( $state );
$set = get_option( WPCPM_Mentor_Assigned::OPT_TOLD );
ck( 'nobody is emailed by the first run', array( count( $GLOBALS['mail'] ), $state['stats']['told'] ), array( 0, 0 ) );
ck( 'and the third mentor\'s student is in the remembered set',
	isset( $set['told'][ record( 3 ) ] ) ? $set['told'][ record( 3 ) ] : null,
	array( 'recKNOWN0003' ) );
$GLOBALS['users'][1003]->roles                                  = array( 'wpcpm_mentor' );
$GLOBALS['umeta'][1003][ WPCPM_Mentors_Sync::META_ACTIVE ]      = 1;
$state          = assign_state( 3, known( 3 ), false );
$state['phase'] = 'revoke';
revoke( $state );
ck( 'with a set in place, the next run keeps no lists in its state', array_key_exists( 'stored_lists', $state ), false );
$state['phase'] = 'assign';
assign( $state );
ck( 'the run that reads all three emails nobody', array( count( $GLOBALS['mail'] ), $state['stats']['told'] ), array( 0, 0 ) );

fresh();
$GLOBALS['users'][1001]  = new WP_User( 1001, 'Mentor 1', 'm1@example.org', array( 'wpcpm_mentor' ) );
$GLOBALS['umeta'][1001] = array( WPCPM_Mentors_Sync::META_RECORD_ID => record( 1 ), WPCPM_Mentors_Sync::META_ACTIVE => 1, WPCPM_Mentors_Sync::META_MENTEES => array( array( 'record_id' => 'recKNOWN0001' ) ) );
$state                  = assign_state( 1, known( 1 ), false );
$state['phase']         = 'revoke';
$state['stored_lists']  = array( record( 1 ) => array( 'recKEPT00001' ) );
revoke( $state );
ck( 'a state that already holds the lists keeps them: a revoke run again does not read them twice', $state['stored_lists'], array( record( 1 ) => array( 'recKEPT00001' ) ) );

echo "\n=== By hand or by the schedule ===\n";
foreach ( array( true, false ) as $cron_says ) {
	fresh();
	$GLOBALS['cron_now']              = $cron_says;
	$GLOBALS['settings']['auto_sync'] = true;
	WPCPM_Mentors_Sync::cron_daily();
	$by_schedule = get_option( WPCPM_Mentors_Sync::OPT_STATE );
	WPCPM_Mentors_Sync::cancel();
	WPCPM_Mentors_Sync::start();
	$by_user = get_option( WPCPM_Mentors_Sync::OPT_STATE );
	WPCPM_Mentors_Sync::cancel();
	ck( 'wp_doing_cron() answering ' . var_export( $cron_says, true ) . ': a run started through cron_daily() has by_hand false, a direct start() true',
		array( $by_schedule['by_hand'], $by_user['by_hand'] ),
		array( false, true ) );
}

fresh();
$GLOBALS['connected']             = false;
$GLOBALS['settings']['auto_sync'] = true;
WPCPM_Mentors_Sync::cron_daily();
$GLOBALS['connected'] = true;
WPCPM_Mentors_Sync::start();
ck( 'the scheduled flag is cleared when start() answers an error: the next direct start is by hand', get_option( WPCPM_Mentors_Sync::OPT_STATE )['by_hand'], true );
WPCPM_Mentors_Sync::cancel();
unset( $GLOBALS['connected'] );

// Thirty mentors, each with a new student: more than half of the mentors who have students.
fresh();
seeded( 30, known( 30 ) );
$state = assign_state( 30, more( known( 30 ), 30 ), false );
assign( $state );
ck( 'a run started by the schedule that would tell all thirty mentors tells nobody: the brake holds',
	array( count( $GLOBALS['mail'] ), $state['stats']['told'] ),
	array( 0, 0 ) );
ck( 'and says so, in numbers only',
	$state['notices'],
	array( '30 mentors would have been emailed about students assigned to them at once, more than half of the mentors who have students, so nobody was emailed. If the assignments are real, start a mentors sync by hand: each sync started by hand emails up to 25 mentors, so start another until none wait. If they are not, switch "Students assigned to a mentor" off (Settings, Students and mentors, Accounts), run a sync and switch it on again: nobody is emailed about them.' ) );

$state = assign_state( 30, more( known( 30 ), 30 ), true );
assign( $state );
ck( 'the same run started by hand passes the brake (the ceiling of 25 still holds)',
	array( count( $GLOBALS['mail'] ), $state['stats']['told'] ),
	array( 25, 25 ) );
ck( 'and the five left wait for the next sync, said in numbers',
	$state['notices'],
	array( '5 more mentors have students assigned and are emailed at the next sync.' ) );

// What the brake's notice promises: a sync started by hand emails up to 25 and leaves the rest;
// a later scheduled sync holds again while more than half (and at least 20) still wait, and another
// sync by hand goes on. Sixty mentors, every one with a student assigned.
fresh();
seeded( 60, known( 60 ) );
$state = assign_state( 60, more( known( 60 ), 60 ), false );
assign( $state );
ck( 'sixty mentors waiting: the scheduled sync holds', array( count( $GLOBALS['mail'] ), $state['stats']['told'] ), array( 0, 0 ) );
$GLOBALS['mail'] = array();
$state           = assign_state( 60, more( known( 60 ), 60 ), true );
assign( $state );
ck( 'a sync started by hand passes the brake and stops at the ceiling: 25 emailed, 35 wait',
	array( count( $GLOBALS['mail'] ), $state['stats']['told'], $state['notices'] ),
	array( WPCPM_Mentor_Assigned::MAX_EMAILS, 25, array( '35 more mentors have students assigned and are emailed at the next sync.' ) ) );
$GLOBALS['mail'] = array();
$state           = assign_state( 60, more( known( 60 ), 60 ), false );
assign( $state );
ck( 'with 35 of 60 still waiting, the next scheduled sync holds again and says so',
	array( count( $GLOBALS['mail'] ), $state['stats']['told'], $state['notices'] ),
	array( 0, 0, array( '35 ' . 'mentors would have been emailed about students assigned to them at once, more than half of the mentors who have students, so nobody was emailed. If the assignments are real, start a mentors sync by hand: each sync started by hand emails up to 25 mentors, so start another until none wait. If they are not, switch "Students assigned to a mentor" off (Settings, Students and mentors, Accounts), run a sync and switch it on again: nobody is emailed about them.' ) ) );
$GLOBALS['mail'] = array();
$state           = assign_state( 60, more( known( 60 ), 60 ), true );
assign( $state );
ck( 'another sync started by hand emails the next 25, and 10 wait', array( count( $GLOBALS['mail'] ), $state['notices'] ), array( 25, array( '10 more mentors have students assigned and are emailed at the next sync.' ) ) );
$GLOBALS['mail'] = array();
$state           = assign_state( 60, more( known( 60 ), 60 ), false );
assign( $state );
ck( 'with 10 waiting (under 20), the scheduled sync emails them', array( count( $GLOBALS['mail'] ), $state['notices'] ), array( 10, array() ) );

fresh();
seeded( 30, known( 30 ) );
$state = assign_state( 30, more( known( 30 ), 30 ) );
assign( $state );
ck( 'a state from before this release has no by_hand and reads as false: the brake holds', array( count( $GLOBALS['mail'] ), $state['stats']['told'] ), array( 0, 0 ) );

echo "\n=== The notices ===\n";
fresh();
seeded( 100, known( 100 ) );
$state = assign_state( 100, more( known( 100 ), 26 ), false );
assign( $state );
ck( '26 mentors with new students: 25 are told, one waits, and the notice has the singular form',
	array( count( $GLOBALS['mail'] ), $state['stats']['told'], $state['notices'] ),
	array( 25, 25, array( '1 more mentor has students assigned and is emailed at the next sync.' ) ) );

fresh();
seeded( 3, known( 3 ) );
$GLOBALS['take'] = false;
$state           = assign_state( 3, more( known( 3 ), 1 ), false );
assign( $state );
ck( 'an email the mail layer refuses is not counted as told; the notice has the singular form',
	array( $state['stats']['told'], $state['notices'] ),
	array( 0, array( '1 mentor could not be emailed about students assigned to them; the next sync tries again.' ) ) );

fresh();
seeded( 4, known( 4 ) );
$GLOBALS['take'] = false;
$state           = assign_state( 4, more( known( 4 ), 2 ), false );
assign( $state );
ck( 'two refused: the plural form', $state['notices'], array( '2 mentors could not be emailed about students assigned to them; the next sync tries again.' ) );
ck( 'no notice holds a name or an address', 0 === preg_match( '/Ada|Ben|Cy|example\.org/', implode( ' ', $state['notices'] ) ), true );

echo "\n=== A set that could not be saved ===\n";
$unsaved_notice = 'The site could not save its record of the students mentors have been emailed about, so the next sync tries again. Leave "Students assigned to a mentor" as it is until a sync ends without this line.';
fresh();
$GLOBALS['refuse_option'] = array( WPCPM_Mentor_Assigned::OPT_TOLD => true );
$state = assign_state( 3, known( 3 ), false );
assign( $state );
ck( '(m) the first run cannot save the set: the notice once, and no seeding notice', array( count( $GLOBALS['mail'] ), $state['notices'], $state['phase'] ), array( 0, array( $unsaved_notice ), 'done' ) );
$GLOBALS['refuse_option'] = array();
$state = assign_state( 3, known( 3 ), false );
assign( $state );
ck( '(m) the next sync saves it: the seeding notice, and not this one', $state['notices'], array( 'The site now remembers who is on every mentor\'s list. Nobody was emailed about students already there.' ) );
$state = assign_state( 3, more( known( 3 ), 1 ), false );
assign( $state );
ck( '(m) a sync that saves: no notice', array( count( $GLOBALS['mail'] ), $state['notices'] ), array( 1, array() ) );

echo "\n=== The ceiling holds when the last step runs again ===\n";
fresh();
seeded( 30, known( 30 ) );
$state = assign_state( 30, more( known( 30 ), 30 ), true );
assign( $state );
$first = count( $GLOBALS['mail'] );
$GLOBALS['mail'] = array();
$state['phase']  = 'assign';
assign( $state );
ck( 'the same sync\'s last step again: 25 emails in all, not 30', array( $first, count( $GLOBALS['mail'] ) ), array( 25, 0 ) );
$state = assign_state( 30, more( known( 30 ), 30 ), true );
$GLOBALS['mail'] = array();
assign( $state );
ck( 'and the next sync emails the 5 left', count( $GLOBALS['mail'] ), 5 );

// The report counts every email accepted under the sync, also those an earlier call of the step sent.
fresh();
seeded( 5, known( 5 ) );
$state = assign_state( 5, more( known( 5 ), 5 ), true );
$again = $state; // The state as it was saved before the step ran, for a call that died before answering.
assign( $state );
$GLOBALS['mail'] = array();
assign( $again );
ck( 'the step run again from the saved state: nobody is emailed twice, and the report counts the 5 already sent', array( count( $GLOBALS['mail'] ), $again['stats']['told'] ), array( 0, 5 ) );

echo "\n=== A mentor with no account, and every other stat ===\n";
fresh();
seeded( 3, known( 3 ), 1 );
$state = assign_state( 3, more( known( 3 ), 3 ), false, 1 );
assign( $state );
ck( 'a mentor with no account is passed over: nothing for them, the other two are told',
	array( array_column( $GLOBALS['mail'], 'to' ), $state['stats']['told'], $state['stats']['assigned'], $state['stats']['mentors_with_students'] ),
	array( array( 1002, 1003 ), 2, 4, 2 ) );
ck( 'and nothing is written for them', array( get_user_meta( 1001, WPCPM_Mentors_Sync::META_MENTEES, true ) ), array( '' ) );
ck( 'the stat starts at 0 in a fresh run', WPCPM_Mentors_Sync::empty_stats()['told'], 0 );

echo "\n=== A run in flight at the deploy ===\n";
fresh();
seeded( 3, known( 3 ) );
$state = assign_state( 3, more( known( 3 ), 1 ), false );
unset( $state['stats']['told'] );
$warned = 0;
set_error_handler(
	static function () use ( &$warned ) {
		++$warned;
		return true;
	}
);
assign( $state );
restore_error_handler();
ck( 'a saved state whose stats lack told finishes assign with no warning, and a told value', array( $warned, $state['stats']['told'], $state['phase'] ), array( 0, 1, 'done' ) );

echo "\n=== What run() is handed ===\n";
$child = (string) shell_exec( 'WPCPM_RECORD_RUN=1 ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' 2>&1' );
preg_match( '/RECORDED (.*)/', $child, $m );
$got = isset( $m[1] ) ? json_decode( $m[1], true ) : null;
ck( 'every mentor of the run is handed over: no account with user ID 0, a mentor with no rows with an empty array, the rows by report ID; by_hand and a callable alive',
	$got,
	array(
		array(
			record( 1 ) => array( 0, array() ),
			record( 2 ) => array( 1002, array( 'recKNOWN0002' ) ),
			record( 3 ) => array( 1003, array() ),
		),
		true,
		true,
	) );

echo "\n=== A sync that lost its lock stops sending ===\n";
fresh();
seeded( 3, known( 3 ) );
$GLOBALS['steal'] = true;
$state            = assign_state( 3, more( known( 3 ), 3 ), true );
update_option( WPCPM_Mentors_Sync::OPT_STATE, $state );
unset( $GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOCK ] );
WPCPM_Mentors_Sync::run_tick();
ck( 'after the first email another tick takes the lock: the sync sends no second one', count( $GLOBALS['mail'] ), 1 );
ck( 'and the saved state is still at assign: the run was not marked done', get_option( WPCPM_Mentors_Sync::OPT_STATE )['phase'], 'assign' );

echo "\n=== The report's row ===\n";
$screen = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php' );
ck( 'the stat told is labelled Mentors emailed about assigned students', false !== strpos( $screen, "'told'             => __( 'Mentors emailed about assigned students', 'wpcredits-program-manager' )," ), true );

echo "\n=== A dry run ===\n";
$cli = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cli.php' );
preg_match( '/private function dry_run\(\) \{(.*?)\n\t\}\n/s', $cli, $m );
$body = isset( $m[1] ) ? $m[1] : '';
ck( 'the command line\'s dry run is found', '' !== $body, true );
ck( 'and neither tells mentors, starts a run, nor writes an option', array( false !== strpos( $body, 'Mentor_Assigned' ), false !== strpos( $body, 'update_option' ), false !== strpos( $body, '::start(' ) ), array( false, false, false ) );

echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit( $fails ? 1 : 0 );
