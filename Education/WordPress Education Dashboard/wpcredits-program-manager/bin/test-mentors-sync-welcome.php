<?php
/**
 * The welcome for a mentor whose account already existed (1.123.42): the mentors sync's pass.
 *
 * The site's mentor invitation carries the mentor welcome, and the sync queued it only for an account
 * it created, so a returning mentor, or a student or institution member made a mentor, was linked to
 * an account that never received it. What each block pins, and why:
 *
 * - **Once per activation.** An account that was not an active mentor before the link (it lacked the
 *   Mentor role, or held it with its active flag at 0) is queued once; an account that was one is
 *   never queued, so the next run, three hours later, and every run after it, send nothing, and an
 *   account a site application already made a mentor is not invited a second time (it queued its
 *   own).
 * - **The rules of a created account.** Nothing is queued with invitation emails off, and never for
 *   an Administrator, whose account signs in already.
 * - **A match by username alone is no link** (1.123.58). The account is given nothing and waits under
 *   Needs confirming, so it is no candidate for the welcome either.
 * - **The brake.** The candidates of a run are kept in the state, which survives from one request
 *   to the next, and queued once, when the pass over the Mentors table ends; more than
 *   `RELINK_INVITE_MAX` of them in one run are queued for nobody, because many accounts made mentors
 *   at once is a base that was changed, not a welcome to send. Each is held instead (`bulk`), so the
 *   Mentors screen's Accounts tab lists it until an Administrator sends it from its row, and one
 *   notice names them all, first among the run's notices, which the report cuts at a hundred.
 * - **Nothing is queued twice or for nothing.** An account whose invitation is held already is
 *   named in a notice and left out, counted nowhere; and the setting is read again when the pass
 *   ends, so a run that began with invitation emails on and finished with them off queues and
 *   holds nothing.
 * - **A created account is as it was**: queued, counted under `invited`, never under
 *   `relinked_invited`.
 *
 * The pass is `phase_mentors()` itself, a page of the base at a time, on a stand-in for Airtable that
 * answers the pages a check gives it, with the state written and read back between pages as the
 * options table does between requests. Runs on the shared stand-in (bin/stubs/mentor-applications.php)
 * with the real WPCPM_Roles, WPCPM_Mentors_Sync and WPCPM_Mentor_Provision. Every address is
 * example.test's, and every name an invented one.
 *
 * Run from the plugin root:  php bin/test-mentors-sync-welcome.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['queued']    = array();
$GLOBALS['settings']  = array();
$GLOBALS['unmodeled'] = array();
$GLOBALS['pages']     = array();

/**
 * The invitation queue, at its contract: an account is queued once, under the queue's lock, taken
 * through the real lock over the stand-in's options table, and nothing is queued while another
 * request holds it, which answers `QUEUE_BUSY`. A check can play another request after each queueing
 * (`$after_queue`).
 */
class WPCPM_Mail {
	const QUEUE_BUSY = -1;
	/** @var callable|null */
	public static $after_queue = null;
	public static function queue_invite( $user_id ) {
		$token = (string) WPCPM_Lock::take( 'wpcpm_invite_queue_lock', 30 );
		if ( '' === $token ) {
			return self::QUEUE_BUSY;
		}
		$added = ! in_array( (int) $user_id, $GLOBALS['queued'], true );
		if ( $added ) {
			$GLOBALS['queued'][] = (int) $user_id;
		}
		WPCPM_Lock::release( 'wpcpm_invite_queue_lock', $token );
		if ( is_callable( self::$after_queue ) ) {
			call_user_func( self::$after_queue );
		}
		return $added ? 1 : 0;
	}
	/** An invitation is held where the account carries the hold, whatever it holds. */
	public static function is_held( $user_id ) {
		return metadata_exists( 'user', (int) $user_id, WPCPM_Mentor_Provision::USER_INVITE_HELD );
	}
}

/** The settings the pass reads, as a check sets them. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/**
 * Airtable, as far as the pass reaches it: it answers the page a check gave for the offset asked, and
 * says which offset the next one is at. `$GLOBALS['pages']` is a list of record lists.
 */
class WPCPM_Airtable {
	public function formula_in( $field, array $values ) {
		return 'OR(' . $field . ')';
	}
	public function fetch_page( $table, array $args ) {
		$index = isset( $args['offset'] ) && null !== $args['offset'] ? (int) $args['offset'] : 0;
		$next  = $index + 1 < count( $GLOBALS['pages'] ) ? (string) ( $index + 1 ) : null;

		return array(
			'records' => $GLOBALS['pages'][ $index ],
			'offset'  => $next,
		);
	}
	public static function flatten( $value ) {
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
// The tick lock is a row of the options table the lock writes straight to it (1.123.58): the stand-in
// runs its statements over `$GLOBALS['opts']`.
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
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

/** A site with no accounts, no queue and the settings given. */
function fresh( array $settings = array( 'send_welcome_email' => true ) ) {
	$GLOBALS['users']        = array();
	$GLOBALS['umeta']        = array();
	$GLOBALS['queued']       = array();
	$GLOBALS['opts']         = array();
	$GLOBALS['settings']     = $settings;
	$GLOBALS['pages']        = array();
	$GLOBALS['insert_error'] = null;

	unset( $GLOBALS['between_pages'] );
}

/** An account on the site: the stand-in's takes its login from the name, so the login is set after. */
function account( $id, $login, $email, array $roles, array $meta = array() ) {
	an_account( $id, 'Account ' . $id, $email, $roles, $meta );
	$GLOBALS['users'][ $id ]->user_login = $login;
}

/** A mentor's row in the base, as Airtable answers it. */
function row( $record, $login, $email ) {
	return array(
		'id'     => $record,
		'fields' => array(
			'Full Name'         => 'Mentor ' . $login,
			'WordPress profile' => 'https://profiles.wordpress.org/' . $login . '/',
			'Email'             => $email,
			'Status'            => 'Active',
		),
	);
}

/**
 * What a run said about the welcome, apart from what the step says of each account it links.
 *
 * @param array $state The state after a pass.
 * @return string[]
 */
function welcome_notices( array $state ) {
	return array_values(
		array_filter(
			$state['notices'],
			static function ( $notice ) {
				return false !== strpos( $notice, 'welcome' ) || false !== strpos( $notice, 'No invitation was sent' );
			}
		)
	);
}

function roles( $id ) {
	return $GLOBALS['users'][ $id ]->roles;
}
function meta( $id, $key ) {
	return get_user_meta( $id, $key, true );
}

/**
 * The state a run starts the mentors phase with.
 *
 * @return array
 */
function state() {
	return array(
		'phase'       => 'mentors',
		'offset'      => null,
		'started'     => time(),
		'touched'     => time(),
		'steps'       => array(),
		'mentors'     => array(),
		'sponsorship' => array(),
		'reports'     => array(),
		'stats'       => WPCPM_Mentors_Sync::empty_stats(),
		'notices'     => array(),
	);
}

/**
 * The pass over the Mentors table, a page at a time: `$GLOBALS['pages']`, each page one request of the
 * run, with the state written to the options table and read back between pages. What the queue held
 * after each page is kept, to read when the invitations were queued.
 *
 * @param array|null $state The state to carry on from, or null for a run's start.
 * @return array The state after the last page.
 */
function pass( $state = null ) {
	$method = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'phase_mentors' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	$state  = null === $state ? state() : $state;

	// The phase pages through the table under the tick's lock and holds it longer after each page
	// (1.123.58), so the pass takes it as a tick does and lets it go at the end.
	$lock = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'acquire_lock' );
	if ( PHP_VERSION_ID < 80100 ) {
		$lock->setAccessible( true );
	}
	$lock->invoke( null );

	$GLOBALS['queue_after_page'] = array();

	do {
		$method->invokeArgs( null, array( &$state, new WPCPM_Airtable(), array( 'mentors_table' => 'Mentors', 'mentor_status' => 'Active' ) ) );

		$GLOBALS['queue_after_page'][] = $GLOBALS['queued'];

		// What a check does between two requests of the run, given the number of pages read so far.
		if ( isset( $GLOBALS['between_pages'] ) ) {
			call_user_func( $GLOBALS['between_pages'], count( $GLOBALS['queue_after_page'] ) );
		}

		update_option( WPCPM_Mentors_Sync::OPT_STATE, $state );
		$state = get_option( WPCPM_Mentors_Sync::OPT_STATE );
	} while ( 'mentors' === $state['phase'] );

	$release = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'release_lock' );
	if ( PHP_VERSION_ID < 80100 ) {
		$release->setAccessible( true );
	}
	$release->invoke( null );

	return $state;
}

$welcome_held = 'No invitation was sent, since a match by username alone may be somebody else: once that is confirmed, press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.';

echo "\n=== An account that was an active mentor ===\n";
fresh();
account( 41, 'ada-example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recACTIVEMENTOR01', 'wpcpm_mentor_active' => 1 ) );
$GLOBALS['pages'] = array( array( row( 'recACTIVEMENTOR01', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'an active mentor read again by a run is sent nothing', array( $GLOBALS['queued'], $state['stats']['relinked_invited'], $state['stats']['updated'], $state['notices'] ), array( array(), 0, 1, array() ) );
pass();
ck( 'and not by the run after it either', $GLOBALS['queued'], array() );

echo "\n=== A former mentor, and an account that was something else ===\n";
fresh();
account( 42, 'ada-example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recFORMERMENTOR01', 'wpcpm_mentor_active' => 0 ) );
$GLOBALS['pages'] = array( array( row( 'recFORMERMENTOR01', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'a former mentor made Active again, matched by its record, is queued once and counted',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], $state['stats']['invited'], meta( 42, 'wpcpm_mentor_active' ), $state['notices'] ),
	array( array( 42 ), 1, 0, 1, array() ) );
pass();
ck( 'and the next run, which finds it active, queues nothing more', $GLOBALS['queued'], array( 42 ) );
$GLOBALS['queued'] = array();
pass();
ck( 'a queue the drain emptied since stays empty: the account is an active mentor now', $GLOBALS['queued'], array() );

fresh();
account( 43, 'ada-student', 'ada@example.test', array( 'wpcpm_student' ) );
$GLOBALS['pages'] = array( array( row( 'recSTUDENTMENTOR1', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'an account with the Student role only, matched by its address, gains the Mentor role and is queued once',
	array( roles( 43 ), $GLOBALS['queued'], $state['stats']['relinked_invited'], $state['stats']['linked'] ),
	array( array( 'wpcpm_student', 'wpcpm_mentor' ), array( 43 ), 1, 1 ) );
ck( 'and a held invitation is none: nothing is left on the account', meta( 43, WPCPM_Mentor_Provision::USER_INVITE_HELD ), '' );

fresh();
account( 44, 'ada-example', 'ada@example.test', array( 'wpcpm_institution' ) );
$GLOBALS['pages'] = array( array( row( 'recINSTITUTION001', 'ada-example', 'ada@example.test' ), row( 'recINSTITUTION001', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'the same row read twice in one run queues its account once, and counts it once',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'] ), array( array( 44 ), 1 ) );

echo "\n=== An account a site application made a mentor ===\n";
fresh();
account(
	45,
	'ada-example',
	'ada@example.test',
	array( 'wpcpm_mentor' ),
	array(
		'wpcpm_mentor_active'                      => 1,
		WPCPM_Mentor_Provision::USER_ROW_PENDING   => 1,
		WPCPM_Mentor_Provision::USER_ACTIVATED_AT  => time(),
	)
);
$GLOBALS['pages'] = array( array( row( 'recAPPLICATION001', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'the sync reading the row an activation wrote finds an active mentor: the activation queued its own invitation, so there is no second',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], $state['stats']['linked'], meta( 45, WPCPM_Mentor_Provision::USER_ROW_PENDING ) ),
	array( array(), 0, 1, '' ) );

echo "\n=== The rules of a created account ===\n";
fresh();
account( 46, 'ada-example', 'ada@example.test', array( 'administrator' ) );
$GLOBALS['pages'] = array( array( row( 'recADMINISTRATOR1', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'an Administrator\'s account is queued for nothing, held for nothing, and keeps its roles',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], meta( 46, WPCPM_Mentor_Provision::USER_INVITE_HELD ), roles( 46 ) ),
	array( array(), 0, '', array( 'administrator' ) ) );

fresh( array( 'send_welcome_email' => false ) );
account( 47, 'ada-student', 'ada@example.test', array( 'wpcpm_student' ) );
account( 48, 'zoe-example', 'zoe@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recFORMERMENTOR02', 'wpcpm_mentor_active' => 0 ) );
$GLOBALS['pages'] = array( array( row( 'recSTUDENTMENTOR2', 'ada-example', 'ada@example.test' ), row( 'recFORMERMENTOR02', 'zoe-example', 'zoe@example.test' ) ) );
$state            = pass();
ck( 'with invitation emails off, a linked account is queued for nothing, as a created one is',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], welcome_notices( $state ) ), array( array(), 0, array() ) );

fresh();
$GLOBALS['pages'] = array( array( row( 'recNEWMENTOR00001', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
$made             = array_keys( $GLOBALS['users'] );
ck( 'a mentor whose account the sync creates is as it was: queued, counted under invited, never under relinked_invited',
	array( count( $made ), $GLOBALS['queued'] === $made, $state['stats']['created'], $state['stats']['invited'], $state['stats']['relinked_invited'] ), array( 1, true, 1, 1, 0 ) );

echo "\n=== A match by username alone ===\n";
fresh();
account( 49, 'ada-example', 'other@example.test', array( 'wpcpm_student' ) );
$GLOBALS['pages'] = array( array( row( 'recLOGINMATCH0001', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'since 1.123.58 the account is given nothing: queued for nothing, held for nothing, and not linked',
	array( $GLOBALS['queued'], meta( 49, WPCPM_Mentor_Provision::USER_INVITE_HELD ), $state['stats']['relinked_invited'], roles( 49 ), meta( 49, 'wpcpm_mentor_active' ), array_keys( (array) get_option( WPCPM_Mentor_Provision::OPT_UNCONFIRMED ) ) ),
	array( array(), '', 0, array( 'wpcpm_student' ), '', array( 'recLOGINMATCH0001' ) ) );
ck( 'and the run says so once, in the step\'s words, with nothing about a welcome',
	array( $state['notices'], in_array( $welcome_held, $state['notices'], true ) ),
	array( array( 'Account 49 has the login of a mentor\'s WordPress.org username (ada-example) and nothing else. Nothing was linked: confirm it under Needs confirming on the Sync tab if it is the mentor\'s account.' ), false ) );

echo "\n=== The brake ===\n";
fresh();
$rows = array();
for ( $i = 1; $i <= 6; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ) );
	$rows[] = row( 'recBRAKESIX0000' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages'] = array( array_slice( $rows, 0, 3 ), array_slice( $rows, 3 ) );
$state            = pass();
$brake_notice = '6 mentors whose accounts already existed became active in one run, so none was sent the welcome: mentor-1, mentor-2, mentor-3, mentor-4, mentor-5, mentor-6. Their invitations wait in the Mentors screen\'s Accounts tab, where Send invite or Resend invite on each row sends it.';
ck( 'six accounts that would each be invited in one run, over two requests: none is queued, and the run says so once, naming all six by username',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], array_slice( $state['notices'], 0, 1 ), count( array_filter( $state['notices'], static function ( $n ) { return false !== strpos( $n, 'became active in one run' ); } ) ) ),
	array( array(), 0, array( $brake_notice ), 1 ) );
ck( 'and each of the six is held as `bulk`, which the Accounts tab lists until an Administrator sends it from its row',
	array_map(
		static function ( $id ) {
			return array( meta( $id, WPCPM_Mentor_Provision::USER_INVITE_HELD ), WPCPM_Mail::is_held( $id ) );
		},
		range( 101, 106 )
	),
	array_fill( 0, 6, array( 'bulk', true ) ) );
ck( 'and they are mentors all the same', array_map( 'roles', array( 101, 106 ) ), array( array( 'wpcpm_student', 'wpcpm_mentor' ), array( 'wpcpm_student', 'wpcpm_mentor' ) ) );
ck( 'and the state keeps no candidates once the pass is over', array_key_exists( 'relink_ids', $state ) ? $state['relink_ids'] : array(), array() );

fresh();
$rows = array();
for ( $i = 1; $i <= 5; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ) );
	$rows[] = row( 'recBRAKEFIVE000' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages'] = array( array_slice( $rows, 0, 2 ), array_slice( $rows, 2, 2 ), array_slice( $rows, 4 ) );
$state            = pass();
ck( 'five accounts over three requests are all queued', array( $GLOBALS['queued'], $state['stats']['relinked_invited'], welcome_notices( $state ) ), array( array( 101, 102, 103, 104, 105 ), 5, array() ) );
ck( 'and only when the pass over the table ended: nothing was queued after the first two pages',
	array( $GLOBALS['queue_after_page'][0], $GLOBALS['queue_after_page'][1], $GLOBALS['queue_after_page'][2] ), array( array(), array(), array( 101, 102, 103, 104, 105 ) ) );
ck( 'and the run moves on to the revoke phase', $state['phase'], 'revoke' );

// The queue's lock taken by another request (the fix wave of 1.123.58): after the first of three is
// queued, another request holds it through the other two, which are not counted, and the run's
// notices say how many to send from the Accounts tab.
fresh();
$rows = array();
for ( $i = 1; $i <= 3; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ) );
	$rows[] = row( 'recBUSYQUEUE000' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages']        = array( $rows );
WPCPM_Mail::$after_queue = static function () {
	$GLOBALS['opts']['wpcpm_invite_queue_lock'] = (string) ( time() + 30 );
};
$state                   = pass();
WPCPM_Mail::$after_queue = null;
$busy_said               = array_values( array_filter( $state['notices'], static function ( $n ) { return false !== strpos( $n, 'the queue was busy' ); } ) );
ck( 'with the queue\'s lock held by another request after the first, the run counts the one it queued and says the other two could not be queued',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], $busy_said ),
	array( array( 101 ), 1, array( '2 invitations could not be queued: the queue was busy. Send them from the Accounts tab.' ) ) );

fresh();
account( 101, 'mentor-1', 'mentor-1@example.test', array( 'wpcpm_student' ) );
$GLOBALS['pages']                           = array( array( row( 'recBUSYQUEUE0001', 'mentor-1', 'mentor-1@example.test' ) ) );
$GLOBALS['opts']['wpcpm_invite_queue_lock'] = (string) ( time() + 30 );
$state                                      = pass();
$busy_said                                  = array_values( array_filter( $state['notices'], static function ( $n ) { return false !== strpos( $n, 'the queue was busy' ); } ) );
ck( 'and with it held throughout, the one is counted under nothing and named in the singular',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], $busy_said ),
	array( array(), 0, array( '1 invitation could not be queued: the queue was busy. Send it from the Accounts tab.' ) ) );

// A run that has said a hundred things already: the report keeps the first hundred notices, so the
// busy notice goes first, where it is kept.
fresh();
account( 101, 'mentor-1', 'mentor-1@example.test', array( 'wpcpm_student' ) );
$GLOBALS['pages']                           = array( array( row( 'recBUSYQUEUE0001', 'mentor-1', 'mentor-1@example.test' ) ) );
$GLOBALS['opts']['wpcpm_invite_queue_lock'] = (string) ( time() + 30 );
$crowded                                    = state();
$crowded['notices']                         = array_fill( 0, 100, 'Something the run said before.' );
$state                                      = pass( $crowded );
ck( 'with a hundred notices said already, the busy notice is first, inside the hundred the report keeps',
	array( array_slice( $state['notices'], 0, 1 ), in_array( '1 invitation could not be queued: the queue was busy. Send it from the Accounts tab.', array_slice( $state['notices'], 0, 100 ), true ) ),
	array( array( '1 invitation could not be queued: the queue was busy. Send it from the Accounts tab.' ), true ) );

fresh();
$rows = array();
for ( $i = 1; $i <= 7; $i++ ) {
	$kind = $i <= 3 ? array( 'wpcpm_student' ) : array( 'wpcpm_mentor' );
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', $kind, $i <= 3 ? array() : array( 'wpcpm_mentor_record_id' => 'recBRAKEMIXED00' . $i, 'wpcpm_mentor_active' => 1 ) );
	$rows[] = row( 'recBRAKEMIXED00' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages'] = array( $rows );
$state            = pass();
ck( 'only an account that would be invited counts toward the brake: three new to the role and four active mentors queue the three',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], welcome_notices( $state ) ), array( array( 101, 102, 103 ), 3, array() ) );

fresh();
$rows = array();
for ( $i = 1; $i <= 6; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ) );
	$rows[] = row( 'recBRAKEHELD000' . $i, $i <= 2 ? 'other-' . $i : 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
// Two of the six are matched by their username alone: the address on the site is not the row's.
$GLOBALS['users'][101]->user_email = 'changed-1@example.test';
$GLOBALS['users'][102]->user_email = 'changed-2@example.test';
$GLOBALS['users'][101]->user_login = 'other-1';
$GLOBALS['users'][102]->user_login = 'other-2';
$GLOBALS['pages'] = array( $rows );
$state            = pass();
ck( 'an account matched by its username alone is no candidate: four of the six are queued, the two that wait to be confirmed are not, and hold nothing',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], meta( 101, WPCPM_Mentor_Provision::USER_INVITE_HELD ), meta( 102, WPCPM_Mentor_Provision::USER_INVITE_HELD ), meta( 103, WPCPM_Mentor_Provision::USER_INVITE_HELD ), array_keys( (array) get_option( WPCPM_Mentor_Provision::OPT_UNCONFIRMED ) ) ),
	array( array( 103, 104, 105, 106 ), 4, '', '', '', array( 'recBRAKEHELD0001', 'recBRAKEHELD0002' ) ) );

echo "\n=== The report keeps the brake's notice ===\n";
fresh();
$rows = array();
for ( $i = 1; $i <= 6; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ) );
	$rows[] = row( 'recBRAKEREPORT00' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages'] = array( $rows );
$state            = state();
for ( $i = 1; $i <= 120; $i++ ) {
	$state['notices'][] = 'An earlier notice, number ' . $i . '.';
}
$state  = pass( $state );
$finish = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'finish' );
if ( PHP_VERSION_ID < 80100 ) {
	$finish->setAccessible( true );
}
$finish->invoke( null, $state );
$stored = get_option( WPCPM_Mentors_Sync::OPT_REPORT );
ck( 'the report keeps a hundred notices, and the brake\'s is the first of them, since it is the one that names who is waiting',
	array( count( $stored['notices'] ), false !== strpos( $stored['notices'][0], 'became active in one run' ), $stored['notices'][1] ), array( 100, true, 'An earlier notice, number 1.' ) );

echo "\n=== An invitation held already ===\n";
fresh();
account( 71, 'ada-example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recHELDALREADY01', 'wpcpm_mentor_active' => 0, WPCPM_Mentor_Provision::USER_INVITE_HELD => 'record' ) );
$GLOBALS['pages'] = array( array( row( 'recHELDALREADY01', 'ada-example', 'ada@example.test' ) ) );
$state            = pass();
ck( 'an account whose invitation is held already is queued for nothing, counted under nothing, and its hold is left as it was',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], meta( 71, WPCPM_Mentor_Provision::USER_INVITE_HELD ) ), array( array(), 0, 'record' ) );
ck( 'and the run names it, since the drain would have passed it over without a word',
	welcome_notices( $state ), array( 'No invitation was sent to ada-example: its invitation is held. Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab sends it.' ) );

fresh();
$rows = array();
for ( $i = 1; $i <= 6; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ), 6 === $i ? array( WPCPM_Mentor_Provision::USER_INVITE_HELD => 'login' ) : array() );
	$rows[] = row( 'recHELDBRAKE0000' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages'] = array( $rows );
$state            = pass();
ck( 'and an account held already is no candidate toward the brake: five others are queued, and the sixth keeps its hold',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], meta( 106, WPCPM_Mentor_Provision::USER_INVITE_HELD ), meta( 101, WPCPM_Mentor_Provision::USER_INVITE_HELD ) ), array( array( 101, 102, 103, 104, 105 ), 5, 'login', '' ) );

echo "\n=== The setting is read when the pass ends ===\n";
fresh();
$rows = array();
for ( $i = 1; $i <= 3; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ) );
	$rows[] = row( 'recSETTINGOFF000' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages']        = array( array_slice( $rows, 0, 2 ), array_slice( $rows, 2 ) );
$GLOBALS['between_pages'] = static function ( $pages_read ) {
	if ( 1 === $pages_read ) {
		$GLOBALS['settings']['send_welcome_email'] = false;
	}
};
$state = pass();
ck( 'invitation emails turned off after the candidates were noted: nothing is queued, nothing is held, nothing is counted',
	array( $GLOBALS['queued'], $state['stats']['relinked_invited'], welcome_notices( $state ), meta( 101, WPCPM_Mentor_Provision::USER_INVITE_HELD ) ), array( array(), 0, array(), '' ) );

fresh();
$rows = array();
for ( $i = 1; $i <= 6; $i++ ) {
	account( 100 + $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test', array( 'wpcpm_student' ) );
	$rows[] = row( 'recSETTINGOFF100' . $i, 'mentor-' . $i, 'mentor-' . $i . '@example.test' );
}
$GLOBALS['pages']        = array( array_slice( $rows, 0, 3 ), array_slice( $rows, 3 ) );
$GLOBALS['between_pages'] = static function ( $pages_read ) {
	if ( 1 === $pages_read ) {
		$GLOBALS['settings']['send_welcome_email'] = false;
	}
};
$state = pass();
ck( 'and six of them are held for nothing and noted for nothing when it is off by then',
	array( $GLOBALS['queued'], welcome_notices( $state ), meta( 101, WPCPM_Mentor_Provision::USER_INVITE_HELD ) ), array( array(), array(), '' ) );

echo "\n=== The sync's own code ===\n";
$sync = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' );
ck( 'the sync still hands each mentor to the one provisioning step, and makes no account of its own',
	array( substr_count( $sync, 'WPCPM_Mentor_Provision::provision(' ), substr_count( $sync, 'insert_user(' ) ), array( 1, 0 ) );
ck( 'the brake is five', WPCPM_Mentors_Sync::RELINK_INVITE_MAX, 5 );
// One static alone, the token of the tick lock this request holds (1.123.58), which is this request's
// and no run's: it is '' outside a tick.
ck( 'the candidates of a run wait in its state, which is stored between requests: the sync keeps no static property of its own but the request\'s lock token', ( new ReflectionClass( 'WPCPM_Mentors_Sync' ) )->getStaticProperties(), array( 'lock_token' => '' ) );
ck( 'and nothing was asked of the stand-ins that they do not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
