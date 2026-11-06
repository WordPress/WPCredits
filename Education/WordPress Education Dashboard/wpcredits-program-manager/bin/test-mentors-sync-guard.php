<?php
/**
 * The count check of the mentors sync (LOG-01, 1.123.58), and the retry of a passing refusal (LOG-06).
 *
 * The revoke step takes the Mentor role from every linked account the run did not read. A read that
 * came back with nothing, or with far fewer than the site holds, would take it from everybody. What
 * each block pins, and why:
 *
 * - **The stop.** A run that read none, or under half as many mentors as there are accounts linked
 *   to a record and marked active, takes nobody's role: it keeps the state at the revoke step, stores
 *   the guard's sentence as the last error, and holds both counts in the state. Exactly half goes on.
 * - **A stopped run is a state of its own.** It is not running, so a tick does nothing and the
 *   recurring start leaves it alone; `progress()` says it is stopped, with both counts and the
 *   sentence. The stop is never retried on its own: no tick is booked for it.
 * - **Run anyway is the run's.** `accept()` lets the stopped run go on from where it stopped; the
 *   next start is without it.
 * - **A passing refusal waits** (LOG-06): the tick is booked after the wait, the run says so once,
 *   and the recurring start leaves a running run alone unless it has stalled.
 *
 * The phases are reached as the suite of the welcome reaches `phase_mentors()`: `phase_revoke()`
 * through `ReflectionMethod`, and the tick as cron runs it, on a stand-in for Airtable. Runs on the
 * shared stand-in (bin/stubs/mentor-applications.php) with the real WPCPM_Roles, WPCPM_Sync_Guard,
 * WPCPM_Mentors_Sync and WPCPM_Mentor_Provision. Every address is example.test's, and every name an
 * invented one.
 *
 * Run from the plugin root:  php bin/test-mentors-sync-guard.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['queued']    = array();
$GLOBALS['settings']  = array();
$GLOBALS['unmodeled'] = array();
$GLOBALS['pages']     = array();
$GLOBALS['refusal']   = null;

/** The invitation queue, at its contract: an account is queued once. */
class WPCPM_Mail {
	public static function queue_invite( $user_id ) {
		if ( ! in_array( (int) $user_id, $GLOBALS['queued'], true ) ) {
			$GLOBALS['queued'][] = (int) $user_id;
		}
	}
	public static function is_held( $user_id ) {
		return metadata_exists( 'user', (int) $user_id, WPCPM_Mentor_Provision::USER_INVITE_HELD );
	}
}

/** The settings the run reads, as a check sets them: connected, with the statuses a start needs. */
class WPCPM_Settings {
	public static function is_connected() {
		return true;
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

/**
 * Airtable, as far as a tick reaches it: the page a check gave, or the refusal a check set.
 */
class WPCPM_Airtable {
	public function formula_in( $field, array $values ) {
		return 'OR(' . $field . ')';
	}
	public function fetch_page( $table, array $args ) {
		if ( null !== $GLOBALS['refusal'] ) {
			return $GLOBALS['refusal'];
		}

		return array(
			'records' => isset( $GLOBALS['pages'][0] ) ? $GLOBALS['pages'][0] : array(),
			'offset'  => null,
		);
	}
	public static function flatten( $value ) {
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}
	/** The schema read, which a token without its scope is refused; the run goes on without it. */
	public function fetch_schema() {
		return new WP_Error( 'wpcpm_airtable_error', 'The token has no schema scope.' );
	}
	/** A lookup table's read: none of its rows, here. */
	public function fetch_all( $table, array $args = array() ) {
		return array();
	}
}

// The count the guard is given, over the accounts' meta, each query's arguments kept.
require_once __DIR__ . '/stubs/user-count-query.php';

function wp_schedule_single_event( $time, $hook, $args = array() ) {
	$GLOBALS['cron'][ $hook ] = array(
		'at'    => (int) $time,
		'every' => '',
	);
	return true;
}
function wp_get_scheduled_event( $hook ) {
	return false;
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-guard.php';
// The tick lock is a row of the options table the lock writes straight to it (1.123.58): the stand-in
// runs its statements over `$GLOBALS['opts']`.
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-provision.php';

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

/** A site with no accounts, no options, no cron and the settings given. */
function fresh( array $settings = array() ) {
	$GLOBALS['users']    = array();
	$GLOBALS['umeta']    = array();
	$GLOBALS['queued']   = array();
	$GLOBALS['opts']     = array();
	$GLOBALS['cron']     = array();
	$GLOBALS['settings'] = $settings;
	$GLOBALS['pages']    = array();
	$GLOBALS['refusal']  = null;
	$GLOBALS['user_counts']  = array();
}

/** The record id of linked mentor number `$n`, spelled out. */
function record( $n ) {
	return sprintf( 'recMENTOR%04d', $n );
}

/** `$count` accounts holding the Mentor role, each linked to its record and marked active. */
function linked_mentors( $count ) {
	for ( $n = 1; $n <= $count; $n++ ) {
		an_account(
			1000 + $n,
			'Mentor ' . $n,
			'mentor-' . $n . '@example.test',
			array( 'wpcpm_mentor' ),
			array(
				'wpcpm_mentor_record_id' => record( $n ),
				'wpcpm_mentor_active'    => 1,
			)
		);
	}
}

/** How many accounts still hold the Mentor role. */
function holding_role() {
	return count(
		array_filter(
			$GLOBALS['users'],
			static function ( $user ) {
				return in_array( 'wpcpm_mentor', $user->roles, true );
			}
		)
	);
}

/**
 * The state a run reaches the revoke step with, having read the first `$read` linked mentors.
 *
 * @param int $read How many of the linked mentors the run read.
 * @return array
 */
function revoke_state( $read ) {
	$mentors = array();

	for ( $n = 1; $n <= $read; $n++ ) {
		$mentors[ record( $n ) ] = array(
			'user_id' => 1000 + $n,
			'name'    => 'Mentor ' . $n,
		);
	}

	return array(
		'phase'   => 'revoke',
		'offset'  => null,
		'started' => time(),
		'touched' => time(),
		'steps'   => array(),
		'mentors' => $mentors,
		'stats'   => WPCPM_Mentors_Sync::empty_stats(),
		'notices' => array(),
	);
}

/** The revoke step, reached as the suite of the welcome reaches the mentors step. */
function revoke( array &$state ) {
	$method = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'phase_revoke' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method->invokeArgs( null, array( &$state, WPCPM_Settings::get() ) );
}

function state() {
	return get_option( WPCPM_Mentors_Sync::OPT_STATE );
}

$sentence = WPCPM_Sync_Guard::sentence( 'mentors', 0, 140 );

echo "\n=== The stop ===\n";
fresh();
linked_mentors( 140 );
update_option( WPCPM_Mentors_Sync::OPT_STATE, revoke_state( 0 ) );
WPCPM_Mentors_Sync::run_tick();
$stopped = state();
ck( 'a run that read nothing while 140 mentors are linked takes nobody\'s role, keeps its state and stops with both counts',
	array( holding_role(), get_option( WPCPM_Mentors_Sync::OPT_ERROR ), isset( $stopped['guard'] ) ? $stopped['guard'] : null, $stopped['phase'], $stopped['stats']['revoked'] ),
	array( 140, $sentence, array( 'read' => 0, 'linked' => 140 ), 'revoke', 0 ) );
ck( 'the stop is never retried: no tick is booked for it', wp_next_scheduled( WPCPM_Mentors_Sync::CRON_TICK ), false );
ck( 'the count is one query of the accounts holding a record and the active flag, asking for the total alone',
	array( count( $GLOBALS['user_counts'] ), $GLOBALS['user_counts'][0]['fields'], $GLOBALS['user_counts'][0]['number'], $GLOBALS['user_counts'][0]['count_total'], array_column( array_filter( $GLOBALS['user_counts'][0]['meta_query'], 'is_array' ), 'key' ) ),
	array( 1, 'ID', 1, true, array( 'wpcpm_mentor_record_id', 'wpcpm_mentor_active' ) ) );

fresh();
linked_mentors( 140 );
$state  = revoke_state( 70 );
$result = revoke( $state );
ck( 'the revoke step of a run that read exactly half of the linked mentors takes the role of the ones it did not read',
	array( $result, $state['stats']['revoked'], holding_role(), array_key_exists( 'guard', $state ), $state['phase'] ),
	array( true, 70, 70, false, 'reports' ) );

fresh();
linked_mentors( 140 );
$state  = revoke_state( 69 );
$result = revoke( $state );
ck( 'one under half stops: no role is taken, and the state holds both counts',
	array( WPCPM_Sync_Guard::is_stop( $result ), $result->get_error_code(), holding_role(), $state['guard'], $state['phase'] ),
	array( true, WPCPM_Sync_Guard::CODE_FELL, 140, array( 'read' => 69, 'linked' => 140 ), 'revoke' ) );

fresh();
linked_mentors( 3 );
$GLOBALS['umeta'][1003]['wpcpm_mentor_active'] = 0;
$state                                         = revoke_state( 1 );
$result                                        = revoke( $state );
ck( 'an account linked but no longer active is not counted: one read of two active goes on', array( $result, $state['stats']['revoked'] ), array( true, 2 ) );

echo "\n=== A stopped run is a state of its own ===\n";
fresh();
linked_mentors( 140 );
update_option( WPCPM_Mentors_Sync::OPT_STATE, revoke_state( 0 ) );
WPCPM_Mentors_Sync::run_tick();
$before = state();
WPCPM_Mentors_Sync::run_tick();
ck( 'a stopped run does not run: is_running() is false, is_stopped() true, and run_tick() does nothing',
	array( WPCPM_Mentors_Sync::is_running(), WPCPM_Mentors_Sync::is_stopped(), state() === $before, holding_role() ),
	array( false, true, true, 140 ) );

$progress = WPCPM_Mentors_Sync::progress();
ck( 'progress() reports the stop with both counts and its sentence',
	array( $progress['stopped'], $progress['running'], $progress['guard'], $progress['stalled'] ),
	array( true, false, array( 'read' => 0, 'linked' => 140, 'sentence' => $sentence ), false ) );

$GLOBALS['settings'] = array( 'auto_sync' => true );
WPCPM_Mentors_Sync::cron_daily();
ck( 'the daily start leaves a stopped run alone', state() === $before, true );

echo "\n=== Run anyway is the run's ===\n";
$accepted = WPCPM_Mentors_Sync::accept();
$state    = state();
ck( 'accept() lets the stopped run go on, for that run, and the next start is without it',
	array( $accepted, ! empty( $state['accepted'] ), array_key_exists( 'guard', $state ), WPCPM_Mentors_Sync::is_running(), get_option( WPCPM_Mentors_Sync::OPT_ERROR ), false !== wp_next_scheduled( WPCPM_Mentors_Sync::CRON_TICK ) ),
	array( true, true, false, true, false, true ) );

$result = revoke( $state );
ck( 'and the accepted run takes the role of every linked account it did not read', array( $result, holding_role(), $state['stats']['revoked'] ), array( true, 0, 140 ) );

// The accepted run is still going, and a start refuses a run going (1.123.58): the later start is
// one after it ended.
WPCPM_Mentors_Sync::cancel();
WPCPM_Mentors_Sync::start();
ck( 'a later start writes a state with no acceptance in it', array_key_exists( 'accepted', state() ), false );
WPCPM_Mentors_Sync::cancel();
WPCPM_Mentors_Sync::start( false, true );
ck( 'and a start asked to accept writes one with it', ! empty( state()['accepted'] ), true );
ck( 'accept() on a run the guard did not stop does nothing, and says so', array( WPCPM_Mentors_Sync::accept(), ! empty( state()['accepted'] ) ), array( false, true ) );
WPCPM_Mentors_Sync::cancel();
ck( 'and accept() with no run at all says so too', WPCPM_Mentors_Sync::accept(), false );

fresh();
linked_mentors( 140 );
update_option( WPCPM_Mentors_Sync::OPT_STATE, revoke_state( 0 ) );
WPCPM_Mentors_Sync::run_tick();
WPCPM_Mentors_Sync::cancel();
ck( 'Cancel on a stopped run ends it with its last error, so no stop\'s sentence is left asking for Run anyway',
	array( get_option( WPCPM_Mentors_Sync::OPT_STATE ), get_option( WPCPM_Mentors_Sync::OPT_ERROR ), holding_role() ),
	array( false, false, 140 ) );

echo "\n=== Needs confirming keeps only mentors the base still holds ===\n";
$waiting = array(
	record( 1 )     => array( 'name' => 'Mentor 1', 'profile' => '', 'user_id' => 1001, 'at' => 1 ),
	'recMENTORGONE' => array( 'name' => 'Gone Mentor', 'profile' => '', 'user_id' => 1002, 'at' => 1 ),
);
fresh();
linked_mentors( 140 );
update_option( WPCPM_Mentor_Provision::OPT_UNCONFIRMED, $waiting );
$state = revoke_state( 0 );
revoke( $state );
$kept_by_stop = array_keys( get_option( WPCPM_Mentor_Provision::OPT_UNCONFIRMED ) );
$state        = revoke_state( 140 );
revoke( $state );
ck( 'a completed run removes a waiting match the base no longer holds, and a stopped run keeps it',
	array( $kept_by_stop, array_keys( get_option( WPCPM_Mentor_Provision::OPT_UNCONFIRMED ) ) ),
	array( array( record( 1 ), 'recMENTORGONE' ), array( record( 1 ) ) ) );

fresh();
linked_mentors( 140 );
update_option( WPCPM_Mentors_Sync::OPT_STATE, revoke_state( 0 ) );
WPCPM_Mentors_Sync::run_tick();
WPCPM_Mentors_Sync::start();
ck( 'Sync now on a stopped run begins a fresh one from the first step, with no counts held', array( state()['phase'], array_key_exists( 'guard', state() ), WPCPM_Mentors_Sync::is_running() ), array( 'schema', false, true ) );

echo "\n=== A passing refusal waits ===\n";
fresh();
$state            = revoke_state( 0 );
$state['phase']   = 'mentors';
$GLOBALS['refusal'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 503): no further detail', array( 'status' => 503 ) );
update_option( WPCPM_Mentors_Sync::OPT_STATE, $state );
WPCPM_Mentors_Sync::run_tick();
WPCPM_Mentors_Sync::run_tick();
$state = state();
ck( 'a passing refusal books the tick after the wait and says so once in the run\'s notices, counting each wait',
	array( wp_next_scheduled( WPCPM_Mentors_Sync::CRON_TICK ) - time() <= 30 && wp_next_scheduled( WPCPM_Mentors_Sync::CRON_TICK ) - time() >= 29, $state['notices'], $state['retried'], $state['phase'] ),
	array( true, array( 'A read was refused during this run (Airtable request failed (HTTP 503): no further detail). The run waited and carried on from where it stopped.' ), 2, 'mentors' ) );

fresh();
$state              = revoke_state( 0 );
$state['phase']     = 'mentors';
$GLOBALS['refusal'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 403): no further detail', array( 'status' => 403 ) );
update_option( WPCPM_Mentors_Sync::OPT_STATE, $state );
WPCPM_Mentors_Sync::run_tick();
ck( 'a refusal that will not pass books nothing and says nothing of a wait',
	array( wp_next_scheduled( WPCPM_Mentors_Sync::CRON_TICK ), state()['notices'], array_key_exists( 'retried', state() ) ),
	array( false, array(), false ) );

fresh( array( 'auto_sync' => true ) );
$state            = revoke_state( 0 );
$state['phase']   = 'mentors';
update_option( WPCPM_Mentors_Sync::OPT_STATE, $state );
WPCPM_Mentors_Sync::cron_daily();
ck( 'the recurring start leaves a running run alone', state() === $state, true );
$state['touched'] = time() - WPCPM_Mentors_Sync::LOCK_TIMEOUT - 5;
update_option( WPCPM_Mentors_Sync::OPT_STATE, $state );
WPCPM_Mentors_Sync::cron_daily();
ck( 'unless it has stalled, which it starts again from the first step', state()['phase'], 'schema' );

echo "\n=== A tick whose lock was taken over (1.123.58) ===\n";

// A restart while a tick works the steps that read no pages, the field descriptions and then the
// lookups: the restart lets the lock go, and the old tick finds it gone when it next holds it, before
// its next state write, and stops.
fresh(
	array(
		'institutions_name_field' => 'Name',
		'teams_name_field'        => 'Name',
	)
);
$restarted_run = array( 'phase' => 'schema', 'started' => time(), 'touched' => time(), 'marker' => 'the restarted run' );
update_option(
	WPCPM_Mentors_Sync::OPT_STATE,
	array(
		'phase'   => 'schema',
		'offset'  => null,
		'started' => time(),
		'touched' => time(),
		'steps'   => array(),
		'stats'   => WPCPM_Mentors_Sync::empty_stats(),
		'notices' => array(),
	)
);
$GLOBALS['after_write'][ WPCPM_Mentors_Sync::OPT_STATE ] = static function () use ( $restarted_run ) {
	$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_STATE ] = $restarted_run;
	$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOCK ]  = (string) ( time() + 900 );
};
WPCPM_Mentors_Sync::run_tick( 60 );
ck( 'a tick whose lock was taken over in a non-paging phase stops before its next state write',
	array( isset( $GLOBALS['after_write'][ WPCPM_Mentors_Sync::OPT_STATE ] ), state() === $restarted_run, WPCPM_Lock::until( WPCPM_Mentors_Sync::OPT_LOCK ) ),
	array( false, true, time() + 900 ) );
unset( $GLOBALS['after_write'] );

ck( 'and nothing was asked of the stand-ins that they do not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
