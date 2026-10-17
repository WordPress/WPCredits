<?php
/**
 * The activation step (1.122.33): `WPCPM_Mentor_Activation`.
 *
 * Design of 16 October 2026, section 3, decisions 6 and 7, and section 7: one step makes a selected
 * applicant a mentor, the account first, then the invitation, then the Airtable row, so a mentor is
 * never kept waiting on Airtable. What each block pins, and why:
 *
 * - **The switch.** Nothing activates while Make mentors from applications is off (owner item 1):
 *   no lock, no account, no row, no invitation.
 * - **A new mentor**: the account named after the username, the application active, the invitation
 *   queued whatever the sync's invitation setting says, since it carries the welcome, the Slack line
 *   as from an application, and the row written with its record id on the account. Once is all: a
 *   second activation, an application not selected and a number that is none are refused.
 * - **Somebody the site or the table knows** (Review Focus 1): a former mentor on their own account
 *   and their own row; a current mentor filed as already a mentor with nothing sent (owner item 4),
 *   an Administrator linked and active as a mentor among them;
 *   a row that holds the address set back to Active and linked (owner item 3); the account that
 *   holds that row's record id before another that holds the address (ruling P9); an Administrator
 *   whose roles are never touched and who is sent no invitation; a student linked, not duplicated;
 *   an account found by its stored profile; one found by its username alone linked and sent no
 *   invitation until a person confirms it (ruling R5).
 * - **Airtable says no** (Review Focus 2): the mentor made and invited all the same, the refusal on
 *   the application, written again until it succeeds, never a second row; the account waits for its
 *   row, and settles once the sync or a person links it (rulings R1 and R2), or once it is gone.
 * - **The mentors sync meanwhile**: an account waiting for its row, or made a mentor during the run,
 *   is not revoked.
 * - **Two at once** (Review Focus 3): one lock per application, which the activation, its retry, the
 *   lapse clock and settling take; a held lock refuses, and a lock left behind runs out.
 * - **No account**: the reason stays on the application, which stays selected.
 * - **The Activated list**: the rows waiting for their Airtable write first, then the newest.
 *
 * Runs on the shared stand-in (bin/stubs/mentor-applications.php) with the real
 * WPCPM_Mentor_Application, WPCPM_Mentor_Provision, WPCPM_Mentor_Airtable_Row, WPCPM_Mentors_Sync,
 * WPCPM_Roles, WPCPM_Typed_Text, and the real WPCPM_Lock over bin/stubs/options-lock-wpdb.php. The
 * Airtable client, the invitation queue, the Slack message and the settings are stood in below, so
 * every outside answer comes from here.
 *
 * Run from the plugin root:  php bin/test-mentor-activation.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';

$GLOBALS['settings']  = array();
$GLOBALS['connected'] = true;
$GLOBALS['queue']     = array();
$GLOBALS['slack']     = array();
$GLOBALS['unmodeled'] = array();

/** The settings the step and the row read, as a check sets them. */
class WPCPM_Settings {
	public static function get() {
		return array_merge(
			array(
				'mentors_table'             => 'tblMENTORS0000001',
				'mentor_status'             => 'Active',
				'checker_source_status'     => 'Vetted - positive',
				'checker_target_status'     => 'Active',
				'mentor_activation_enabled' => true,
			),
			$GLOBALS['settings']
		);
	}

	public static function get_value( $key, $fallback = null ) {
		$settings = self::get();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	public static function is_connected() {
		return (bool) $GLOBALS['connected'];
	}
}

/** The invitation queue, at its contract: an account is queued once. */
class WPCPM_Mail {
	public static function queue_invite( $user_id ) {
		if ( ! in_array( (int) $user_id, $GLOBALS['queue'], true ) ) {
			$GLOBALS['queue'][] = (int) $user_id;
		}
	}
}

/** The Mentor Status Checker's Slack message: each mentor it is told of is kept. */
class WPCPM_Mentor_Checker_Slack {
	public static function record( array $mentor ) {
		$GLOBALS['slack'][] = $mentor;
	}
}

/**
 * The Airtable client, at its contract, as bin/test-mentor-airtable-row.php has it. The answers come
 * from the globals a check sets; every read and write is kept for the check to read.
 */
class WPCPM_Airtable {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';

	public function cached_schema() {
		if ( $GLOBALS['schema_error'] instanceof WP_Error ) {
			return $GLOBALS['schema_error'];
		}

		return array(
			'schema' => $GLOBALS['schema'],
			'age'    => 0,
		);
	}

	public function fetch_schema() {
		return $GLOBALS['schema_error'] instanceof WP_Error ? $GLOBALS['schema_error'] : $GLOBALS['schema'];
	}

	public function fetch_all( $table, array $args = array() ) {
		if ( isset( $GLOBALS['linked_error'][ $table ] ) ) {
			return $GLOBALS['linked_error'][ $table ];
		}

		return isset( $GLOBALS['linked'][ $table ] ) ? $GLOBALS['linked'][ $table ] : array();
	}

	public function fetch_page( $table, array $args = array() ) {
		$GLOBALS['searched'][] = array(
			'table' => $table,
			'args'  => $args,
		);

		if ( $GLOBALS['refuse_search'] instanceof WP_Error ) {
			return $GLOBALS['refuse_search'];
		}

		$records = array();

		foreach ( $GLOBALS['by_email'] as $id ) {
			$records[] = array(
				'id'     => $id,
				'fields' => array( 'Email' => 'ada@example.test' ),
			);
		}

		return array(
			'records' => $records,
			'offset'  => null,
		);
	}

	public function create_records( $table, array $records ) {
		// What happens in the moment before Airtable answers, played once: the clock moving on.
		if ( is_callable( $GLOBALS['before_create'] ) ) {
			$run                      = $GLOBALS['before_create'];
			$GLOBALS['before_create'] = null;
			$run();
		}

		if ( $GLOBALS['refuse_create'] instanceof WP_Error ) {
			return $GLOBALS['refuse_create'];
		}

		foreach ( $records as $record ) {
			$GLOBALS['created'][] = $record;
		}

		return $GLOBALS['created_ids'];
	}

	public function update_records( $table, array $records ) {
		if ( $GLOBALS['refuse_update'] instanceof WP_Error ) {
			return $GLOBALS['refuse_update'];
		}

		$done = array();

		foreach ( $records as $record ) {
			$GLOBALS['updated'][]  = $record;
			$done[ $record['id'] ] = true;
		}

		return $done;
	}

	public static function table_in( array $schema, $table ) {
		$table = (string) $table;

		if ( isset( $schema[ $table ] ) && is_array( $schema[ $table ] ) ) {
			return $schema[ $table ];
		}

		return new WP_Error( 'wpcpm_airtable_no_table', 'The base has no table ' . $table . '.', array( 'table' => $table ) );
	}

	public function formula_in( $field, array $values, $lower = false, $trim = false ) {
		$values = array_values( array_filter( array_map( 'trim', array_map( 'strval', $values ) ), 'strlen' ) );

		if ( empty( $values ) ) {
			return '';
		}

		return 'TRIM(LOWER({' . $field . '})) = \'' . mb_strtolower( $values[0] ) . '\'';
	}

	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			return implode( $glue, array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ), 'strlen' ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-provision.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-airtable-row.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-activation.php';

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

/** A choice list as the schema keeps one. */
function choices( array $names ) {
	return array(
		'choices' => array_map(
			function ( $name ) {
				return array( 'name' => $name );
			},
			$names
		),
	);
}

/** The bare schema, table id to table, as bin/test-mentor-airtable-row.php has it. */
function schema() {
	$columns = array(
		'Full Name'                                   => array( 'type' => 'singleLineText' ),
		'WordPress profile'                           => array( 'type' => 'url' ),
		'Email'                                       => array( 'type' => 'email' ),
		'Status'                                      => array(
			'type'    => 'singleSelect',
			'options' => choices( array( 'Interested', 'Vetted - positive', 'Active', 'Inactive' ) ),
		),
		'Country'                                     => array(
			'type'    => 'multipleRecordLinks',
			'options' => array( 'linkedTableId' => 'tblCOUNTRIES00001' ),
		),
		'Languages'                                   => array(
			'type'    => 'multipleRecordLinks',
			'options' => array( 'linkedTableId' => 'tblLANGUAGES00001' ),
		),
		'Contribution Area - Expertise'               => array(
			'type'    => 'multipleSelects',
			'options' => choices( array( 'Polyglots', 'Training' ) ),
		),
		'Available hours per week'                    => array( 'type' => 'number' ),
		'Teacher from a participating institution?'   => array(
			'type'    => 'singleSelect',
			'options' => choices( array( 'Yes', 'No' ) ),
		),
		"Anything else you\u{2019}d like us to know?" => array( 'type' => 'multilineText' ),
	);
	$linked  = array(
		'name'    => 'Linked',
		'primary' => 'Name',
		'fields'  => array( 'Name' => '' ),
		'columns' => array( 'Name' => array( 'type' => 'singleLineText' ) ),
		'ids'     => array(),
	);

	return array(
		'tblMENTORS0000001' => array(
			'name'    => 'Mentors',
			'primary' => 'Full Name',
			'fields'  => array_fill_keys( array_keys( $columns ), '' ),
			'columns' => $columns,
			'ids'     => array(),
		),
		'tblCOUNTRIES00001' => array_merge( $linked, array( 'name' => 'Countries' ) ),
		'tblLANGUAGES00001' => array_merge( $linked, array( 'name' => 'Languages' ) ),
	);
}

/** A site with no accounts and no applications, the stand-ins emptied, and the settings given. */
function fresh( array $settings = array() ) {
	$GLOBALS['settings']      = $settings;
	$GLOBALS['connected']     = true;
	$GLOBALS['opts']          = array();
	$GLOBALS['transients']    = array();
	$GLOBALS['posts']         = array();
	$GLOBALS['pmeta']         = array();
	$GLOBALS['users']         = array();
	$GLOBALS['umeta']         = array();
	$GLOBALS['next_id']       = 500;
	$GLOBALS['next_user']     = 1000;
	$GLOBALS['insert_error']  = null;
	$GLOBALS['queue']         = array();
	$GLOBALS['slack']         = array();
	$GLOBALS['cache_deleted'] = array();
	$GLOBALS['before_create'] = null;
	unset( $GLOBALS['write_log'], $GLOBALS['events'], $GLOBALS['throw_on_write'] );

	$GLOBALS['wpdb']->statements = array();

	$GLOBALS['schema']        = schema();
	$GLOBALS['schema_error']  = null;
	$GLOBALS['linked']        = array(
		'tblCOUNTRIES00001' => array(
			array(
				'id'     => 'recCOUNTRYPL00001',
				'fields' => array( 'Name' => 'Poland' ),
			),
		),
		'tblLANGUAGES00001' => array(
			array(
				'id'     => 'recLANGENGLISH001',
				'fields' => array( 'Name' => 'English' ),
			),
			array(
				'id'     => 'recLANGPOLISH0001',
				'fields' => array( 'Name' => 'Polish' ),
			),
		),
	);
	$GLOBALS['linked_error']  = array();
	$GLOBALS['searched']      = array();
	$GLOBALS['by_email']      = array();
	$GLOBALS['created']       = array();
	$GLOBALS['created_ids']   = array( 'recNEWMENTOR00001' );
	$GLOBALS['updated']       = array();
	$GLOBALS['refuse_search'] = null;
	$GLOBALS['refuse_create'] = null;
	$GLOBALS['refuse_update'] = null;
}

/** The answers as the form stores them, as bin/test-mentor-airtable-row.php has them. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'      => 'Ada Example',
			'email'     => 'ada@example.test',
			'profile'   => 'ada-example',
			'country'   => 'Poland',
			'teacher'   => 'No',
			'languages' => array( 'English', 'Polish' ),
			'expertise' => array( 'Polyglots', 'Training' ),
			'hours'     => '2.5',
			'anything'  => 'Tom & Jerry\'s "quote", and a &lt; b, Zoë &amp; co.',
		),
		$more
	);
}

/**
 * An application with those answers, its address confirmed and the applicant selected: its ID. Not
 * `selected()`, which the stand-in already defines as WordPress's own form helper.
 */
function selected_app( array $more = array() ) {
	$id = WPCPM_Mentor_Application::store( answers( $more ), 'new', array(), array() );

	WPCPM_Mentor_Application::confirm( $id );
	WPCPM_Mentor_Application::decide( $id, WPCPM_Mentor_Application::STATE_SELECTED, WPCPM_Mentor_Application::EVENT_SELECTED, 1 );

	return $id;
}

/** The newest `$n` event names of an application, oldest of them first. */
function last_events( $id, $n ) {
	return array_slice( array_column( WPCPM_Mentor_Application::events( $id ), 'event' ), -$n );
}

/** The event `$back` rows from the newest (1 is the newest). */
function event_back( $id, $back ) {
	$events = WPCPM_Mentor_Application::events( $id );

	return $events[ count( $events ) - $back ];
}

/** The mentors sync's revoke phase, run on its own, as a run that started at `$started` reaches it. */
function revoke( $started ) {
	$state  = array(
		'mentors' => array(),
		'stats'   => WPCPM_Mentors_Sync::empty_stats(),
		'started' => $started,
	);
	$method = new ReflectionMethod( 'WPCPM_Mentors_Sync', 'phase_revoke' );

	// Needed before PHP 8.1, and deprecated from 8.5, as bin/test-students-sync.php has it.
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	$method->invokeArgs( null, array( &$state, array( 'on_inactive' => 'revoke' ) ) );

	return $state;
}

/** The lock row of an application, as the options table holds it, or null. */
function lock_row( $id ) {
	$name = WPCPM_Mentor_Application::lock_name( $id );

	return isset( $GLOBALS['opts'][ $name ] ) ? $GLOBALS['opts'][ $name ] : null;
}

// Every warning, notice and deprecation of the run is kept; the last check names any.
$php_warnings = array();

set_error_handler(
	static function ( $number, $message ) use ( &$php_warnings ) {
		$php_warnings[] = $message;

		return true;
	}
);

echo "=== The names ===\n";

ck( 'one lock per application, under the prefix the uninstaller sweeps, held ten minutes at most', array( WPCPM_Mentor_Application::LOCK_PREFIX, WPCPM_Mentor_Application::LOCK_TTL, WPCPM_Mentor_Application::lock_name( 501 ) ), array( 'wpcpm_mapp_lock_', 600, 'wpcpm_mapp_lock_501' ) );
ck( 'the application keeps its account, its Airtable row, its problem and when it was made a mentor as site fields',
	array( WPCPM_Mentor_Application::META_USER, WPCPM_Mentor_Application::META_AIRTABLE, WPCPM_Mentor_Application::META_PROBLEM, WPCPM_Mentor_Application::META_ACTIVATED_AT ),
	array( '_wpcpm_mapp_user', '_wpcpm_mapp_airtable', '_wpcpm_mapp_problem', '_wpcpm_mapp_activated' ) );
ck( 'and its history four events more',
	array( WPCPM_Mentor_Application::EVENT_AIRTABLE_WRITTEN, WPCPM_Mentor_Application::EVENT_AIRTABLE_REFUSED, WPCPM_Mentor_Application::EVENT_ALREADY_MENTOR, WPCPM_Mentor_Application::EVENT_AIRTABLE_SETTLED ),
	array( 'Airtable row written', 'Airtable row refused', 'already a mentor', 'Airtable row settled' ) );

echo "\n=== The switch ===\n";

fresh( array( 'mentor_activation_enabled' => false ) );
$off = selected_app();
$r   = WPCPM_Mentor_Activation::activate( $off, 0 );
ck( 'with Make mentors from applications off, nothing is made: no lock taken, no account, no row, no invitation',
	array( $r['outcome'], $GLOBALS['wpdb']->statements, count( $GLOBALS['users'] ), $GLOBALS['created'], $GLOBALS['queue'], WPCPM_Mentor_Application::state_of( get_post( $off ) ) ),
	array( 'off', array(), 0, array(), array(), 'selected' ) );
ck( 'and the answer says where the switch is', $r['message'], 'Nothing was done: Make mentors from applications is off, under Mentor applications on the Students and mentors tab of Settings.' );
ck( 'the switch is the setting, off unless it is switched on',
	array( WPCPM_Mentor_Activation::enabled(), ( function () { $GLOBALS['settings'] = array( 'mentor_activation_enabled' => true ); return WPCPM_Mentor_Activation::enabled(); } )(), ( function () { $GLOBALS['settings'] = array( 'mentor_activation_enabled' => null ); return WPCPM_Mentor_Activation::enabled(); } )() ),
	array( false, true, false ) );

echo "\n=== A new mentor ===\n";

fresh();
$ada = selected_app();
$r   = WPCPM_Mentor_Activation::activate( $ada, 0 );
$uid = $r['user_id'];
ck( 'the course shown, a selected applicant is made a mentor: an account named after their username, the new row\'s record id on it, the invitation queued once, the application active',
	array( $r['outcome'], $r['airtable'], get_userdata( $uid )->user_login, get_user_meta( $uid, 'wpcpm_mentor_record_id', true ), $GLOBALS['queue'], WPCPM_Mentor_Application::state_of( get_post( $ada ) ), WPCPM_Mentor_Application::user_of( get_post( $ada ) ) ),
	array( 'activated', 'created', 'ada-example', 'recNEWMENTOR00001', array( $uid ), 'active', $uid ) );
ck( 'the answer says the invitation was queued, and has nothing more to say', array( $r['invited'], $r['held'], $r['message'] ), array( true, '', '' ) );
ck( 'and nothing on the account holds the invitation back', isset( $GLOBALS['umeta'][ $uid ][ WPCPM_Mentor_Provision::USER_INVITE_HELD ] ), false );
ck( 'and the answer says the account was made, not linked', $r['linked'], false );
ck( 'its history says it was made a mentor by the daily check, then that the row was written; the account is no longer waiting for its row',
	array( last_events( $ada, 2 ), get_user_meta( $uid, WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), get_user_meta( $uid, WPCPM_Mentor_Provision::USER_ACTIVATED_AT, true ) > 0 ),
	array( array( 'activated', 'Airtable row written' ), '', true ) );
ck( 'both by the daily check, which is nobody, with nothing left out of the row to say', array( event_back( $ada, 2 )['actor'], event_back( $ada, 1 )['actor'], event_back( $ada, 1 )['note'] ), array( 0, 0, '' ) );
ck( 'the row Airtable was sent is the one the answers make, and what is kept of the write says so',
	array( $GLOBALS['created'][0]['fields']['Full Name'], WPCPM_Mentor_Application::airtable_of( get_post( $ada ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $ada ) )['record'] ),
	array( 'Ada Example', 'created', 'recNEWMENTOR00001' ) );
ck( 'what is kept of the write has every key, the time among them',
	array_keys( WPCPM_Mentor_Application::airtable_of( get_post( $ada ) ) ),
	array( 'state', 'how', 'record', 'error', 'left_out', 'at' ) );
ck( 'the moment it was made a mentor is kept on the application, and nothing is wrong with it',
	array( (int) get_post_meta( $ada, WPCPM_Mentor_Application::META_ACTIVATED_AT, true ) > 0, WPCPM_Mentor_Application::problem_of( get_post( $ada ) ) ),
	array( true, array() ) );
ck( 'the Slack message is told of the mentor made, as from an application', $GLOBALS['slack'], array( array( 'id' => 'app-' . $ada, 'name' => 'Ada Example', 'profile' => 'https://profiles.wordpress.org/ada-example/', 'source' => 'site' ) ) );
ck( 'and the lock is let go', lock_row( $ada ), null );
$r = WPCPM_Mentor_Activation::activate( $ada, 0 );
ck( 'a second activation of the same application leaves it alone and says so: no second account, row or invitation',
	array( $r['outcome'], count( $GLOBALS['created'] ), $GLOBALS['queue'], count( $GLOBALS['users'] ) ),
	array( 'already', 1, array( $uid ), 1 ) );
ck( 'in a sentence', $r['message'], 'This application was made a mentor already, so nothing was changed.' );
$r = WPCPM_Mentor_Activation::activate( WPCPM_Mentor_Application::store( answers(), 'new', array(), array() ), 0 );
ck( 'an application that is not selected is not made a mentor', array( $r['outcome'], $r['message'] ), array( 'not-selected', 'This application is not selected, so it was not made a mentor.' ) );
$r = WPCPM_Mentor_Activation::activate( 999999, 0 );
ck( 'and a number that is no application is nobody', array( $r['outcome'], $r['message'] ), array( 'unknown', 'There is no such mentor application.' ) );

fresh();
$GLOBALS['events'] = array();
$fresh_read        = selected_app();
WPCPM_Mentor_Activation::activate( $fresh_read, 0 );
ck( 'the application is read again once its lock is held: the lock is taken, then this request\'s copy of the row is dropped, before anything is read',
	array_slice( $GLOBALS['events'], 0, 2 ), array( 'lock insert', 'drop post_meta ' . $fresh_read ) );

fresh( array( 'send_welcome_email' => false ) );
$r = WPCPM_Mentor_Activation::activate( selected_app(), 0 );
ck( 'the invitation is queued with the sync\'s invitation emails off, since it carries the welcome', $GLOBALS['queue'], array( $r['user_id'] ) );

echo "\n=== Somebody the site or the table knows ===\n";

fresh();
an_account( 61, 'Cy Example', 'ada@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001', 'wpcpm_mentor_active' => 0 ) );
$cy = selected_app();
$r  = WPCPM_Mentor_Activation::activate( $cy, 0 );
ck( 'a former mentor is made a mentor again on their own account and their own row: its Status set back to Active, nothing created',
	array( $r['outcome'], $r['airtable'], $r['user_id'], $GLOBALS['created'], $GLOBALS['updated'], get_user_meta( 61, 'wpcpm_mentor_active', true ), WPCPM_Mentor_Application::airtable_of( get_post( $cy ) )['how'] ),
	array( 'activated', 'reactivated', 61, array(), array( array( 'id' => 'recRETURNING00001', 'fields' => array( 'Status' => 'Active' ) ) ), 1, 'reactivate' ) );
ck( 'and they are sent the invitation with the welcome again', array( $GLOBALS['queue'], $r['invited'] ), array( array( 61 ), true ) );
ck( 'and the answer says the account they had was linked, not made', $r['linked'], true );
ck( 'their own record id is trusted first: the table is not searched by the address', $GLOBALS['searched'], array() );

fresh();
an_account( 64, 'Eli Example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recCURRENT0000001', 'wpcpm_mentor_active' => 1 ) );
$eli = selected_app();
$r   = WPCPM_Mentor_Activation::activate( $eli, 0 );
ck( 'a current mentor selected again is filed as already a mentor: nothing sent, nothing written, no Slack line',
	array( $r['outcome'], WPCPM_Mentor_Application::state_of( get_post( $eli ) ), last_events( $eli, 1 ), $GLOBALS['queue'], $GLOBALS['created'], $GLOBALS['updated'], $GLOBALS['slack'], WPCPM_Mentor_Application::airtable_of( get_post( $eli ) )['state'] ),
	array( 'already-mentor', 'active', array( 'already a mentor' ), array(), array(), array(), array(), 'already' ) );
ck( 'filed with their account and record, a dated index for the Activated list, and the note that says why nothing went',
	array( WPCPM_Mentor_Application::user_of( get_post( $eli ) ), WPCPM_Mentor_Application::airtable_of( get_post( $eli ) )['record'], (int) get_post_meta( $eli, WPCPM_Mentor_Application::META_ACTIVATED_AT, true ) > 0, event_back( $eli, 1 )['note'], $r['invited'] ),
	array( 64, 'recCURRENT0000001', true, 'A mentor on this site already: nothing was sent, and nothing was written to Airtable.', false ) );
ck( 'and the answer says so', $r['message'], 'The applicant is a mentor on this site already: the application is filed as already a mentor, and nothing was sent or written to Airtable.' );

fresh();
$GLOBALS['by_email'] = array( 'recADOPTED0000001' );
$fay                 = selected_app();
$r                   = WPCPM_Mentor_Activation::activate( $fay, 0 );
ck( 'a row that already holds the address is set back to Active and linked: one row for the person',
	array( $r['airtable'], get_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', true ), $GLOBALS['created'], $GLOBALS['updated'] ),
	array( 'adopted', 'recADOPTED0000001', array(), array( array( 'id' => 'recADOPTED0000001', 'fields' => array( 'Status' => 'Active' ) ) ) ) );

fresh();
an_account( 71, 'Old Example', 'old-address@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recHOLDER00000001', 'wpcpm_mentor_active' => 0 ) );
an_account( 72, 'Ada Example', 'ada@example.test', array( 'wpcpm_student' ) );
$GLOBALS['users'][71]->user_login = 'old-example';
$GLOBALS['by_email']              = array( 'recHOLDER00000001' );
$ho                               = selected_app();
$r                                = WPCPM_Mentor_Activation::activate( $ho, 0 );
ck( 'an account holding the record id of the Mentors row with the address, and another holding the address, resolve to the record holder: its own row set back to Active, and no second account gets the record id',
	array( $r['outcome'], $r['user_id'], $r['airtable'], get_user_meta( 72, 'wpcpm_mentor_record_id', true ), get_userdata( 72 )->roles, $GLOBALS['created'], $GLOBALS['updated'], count( $GLOBALS['users'] ), $GLOBALS['queue'] ),
	array( 'activated', 71, 'reactivated', '', array( 'wpcpm_student' ), array(), array( array( 'id' => 'recHOLDER00000001', 'fields' => array( 'Status' => 'Active' ) ) ), 2, array() ) );
ck( 'two accounts answering for one applicant hold the invitation, as a match by username alone does: nothing queued, the answer says it is held and why, and the application says how the account was found',
	array( $GLOBALS['queue'], $r['invited'], $r['held'], WPCPM_Mentor_Application::linked_by_of( get_post( $ho ) ), $r['message'] ),
	array( array(), false, 'record', 'record', 'Made a mentor, but the invitation is held: another account holds the applicant\'s address. Confirm which account is the person\'s, then press Resend invite on the Mentors screen\'s Accounts tab.' ) );
ck( 'and the record holder\'s account is marked held, with the reason, so the bulk invite passes it over until it is sent from its row',
	array( get_user_meta( 71, WPCPM_Mentor_Provision::USER_INVITE_HELD, true ), isset( $GLOBALS['umeta'][ 72 ][ WPCPM_Mentor_Provision::USER_INVITE_HELD ] ) ),
	array( 'record', false ) );
ck( 'and the history says another account holds the applicant\'s address, so an Administrator confirms which account is the person\'s before pressing Resend invite',
	event_back( $ho, 2 )['note'],
	'Another account, "adaexample", holds the applicant\'s address. The account linked to the Mentors row with the applicant\'s address, "old-example", was made a mentor. Added the Mentor role to the existing account "old-example" (Ada Example). No invitation was sent: once it is confirmed which of the two accounts is the person\'s, press Resend invite on the Mentors screen\'s Accounts tab.' );

fresh();
an_account( 74, 'Login Example', 'other@example.test', array( 'subscriber' ) );
an_account( 75, 'Old Example', 'older@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recHOLDER00000004', 'wpcpm_mentor_active' => 0 ) );
$GLOBALS['users'][74]->user_login = 'ada-example';
$GLOBALS['users'][75]->user_login = 'old-example';
$GLOBALS['by_email']              = array( 'recHOLDER00000004' );
$by_name                          = selected_app();
$r                                = WPCPM_Mentor_Activation::activate( $by_name, 0 );
ck( 'another account named after the applicant\'s username, not holding the address, is said to be that, in the history and in the answer, and the invitation is held all the same',
	array( $r['user_id'], $GLOBALS['queue'], $r['held'], event_back( $by_name, 2 )['note'], $r['message'] ),
	array( 75, array(), 'record', 'Another account, "ada-example", is named after the applicant\'s WordPress.org username. The account linked to the Mentors row with the applicant\'s address, "old-example", was made a mentor. Added the Mentor role to the existing account "old-example" (Ada Example). No invitation was sent: once it is confirmed which of the two accounts is the person\'s, press Resend invite on the Mentors screen\'s Accounts tab.', 'Made a mentor, but the invitation is held: another account is named after the applicant\'s WordPress.org username. Confirm which account is the person\'s, then press Resend invite on the Mentors screen\'s Accounts tab.' ) );

fresh();
an_account( 76, 'Ada Example', 'ada@example.test', array( 'wpcpm_student' ) );
an_account( 77, 'Cur Example', 'current@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recHOLDER00000005', 'wpcpm_mentor_active' => 1 ) );
$GLOBALS['users'][77]->user_login = 'cur-example';
$GLOBALS['by_email']              = array( 'recHOLDER00000005' );
$cur                              = selected_app();
$r                                = WPCPM_Mentor_Activation::activate( $cur, 0 );
ck( 'a record holder who is a mentor already is filed as already a mentor, and the history never says it was made one',
	array( $r['outcome'], $r['user_id'], event_back( $cur, 1 )['note'], false === strpos( event_back( $cur, 1 )['note'], 'was made a mentor' ) ),
	array( 'already-mentor', 77, 'A mentor on this site already: nothing was sent, and nothing was written to Airtable. Another account, "adaexample", holds the applicant\'s address. The account linked to the Mentors row with the applicant\'s address, "cur-example", is a mentor on this site already.', true ) );

fresh();
an_account( 73, 'Ada Example', 'ada@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recHOLDER00000003', 'wpcpm_mentor_active' => 0 ) );
$GLOBALS['by_email'] = array( 'recHOLDER00000003' );
$same                = WPCPM_Mentor_Activation::activate( selected_app(), 0 );
ck( 'when the account that holds the address also holds the row\'s record id, it is one account for the applicant, and the invitation goes as planned',
	array( $same['user_id'], $same['airtable'], $GLOBALS['queue'], $same['invited'], $same['held'], $same['message'] ),
	array( 73, 'reactivated', array( 73 ), true, '', '' ) );

fresh();
an_account( 62, 'Ada Example', 'ada@example.test', array( 'administrator' ) );
$adm = selected_app();
$r   = WPCPM_Mentor_Activation::activate( $adm, 0 );
ck( 'an Administrator who applied keeps their roles and is sent no invitation, and the activated event\'s note says why',
	array( WPCPM_Mentor_Application::user_of( get_post( $adm ) ), get_userdata( 62 )->roles, $GLOBALS['queue'], 'activated' === event_back( $adm, 2 )['event'] && false !== strpos( event_back( $adm, 2 )['note'], 'no invitation was sent' ), $r['invited'] ),
	array( 62, array( 'administrator' ), array(), true, false ) );
ck( 'which is no invitation held for a person to confirm', array( $r['held'], $r['message'], isset( $GLOBALS['umeta'][ 62 ][ WPCPM_Mentor_Provision::USER_INVITE_HELD ] ) ), array( '', '', false ) );
ck( 'in these words', event_back( $adm, 2 )['note'], 'The account is an Administrator\'s, which signs in already: no invitation was sent.' );

// The shared step never gives an Administrator the Mentor role, so an Administrator who is a mentor
// already is known by the record id and the active flag alone.
fresh();
an_account( 78, 'Ada Example', 'ada@example.test', array( 'administrator' ), array( 'wpcpm_mentor_record_id' => 'recADMINMENTOR001', 'wpcpm_mentor_active' => 1 ) );
$adm_mentor = selected_app();
$r          = WPCPM_Mentor_Activation::activate( $adm_mentor, 0 );
$counted    = array_sum( array_column( WPCPM_Mentor_Application::month_counts_of( get_post( $adm_mentor ) ), 'activated' ) );
ck( 'an Administrator who is a linked, active mentor already is filed as already a mentor: nothing sent, nothing written, no Slack line, not counted as made a mentor, roles untouched',
	array( $r['outcome'], $r['user_id'], last_events( $adm_mentor, 1 ), $GLOBALS['queue'], $GLOBALS['created'], $GLOBALS['updated'], $GLOBALS['searched'], $GLOBALS['slack'], $counted, get_userdata( 78 )->roles, WPCPM_Mentor_Application::airtable_of( get_post( $adm_mentor ) )['state'] ),
	array( 'already-mentor', 78, array( 'already a mentor' ), array(), array(), array(), array(), array(), 0, array( 'administrator' ), 'already' ) );

fresh();
an_account( 63, 'Ada Example', 'ada@example.test', array( 'wpcpm_student' ) );
ck( 'a student\'s account is linked, not duplicated', array( WPCPM_Mentor_Activation::activate( selected_app(), 0 )['user_id'], get_userdata( 63 )->roles, count( $GLOBALS['users'] ), $GLOBALS['queue'] ), array( 63, array( 'wpcpm_student', 'wpcpm_mentor' ), 1, array( 63 ) ) );

fresh();
an_account( 66, 'Hal Example', 'old-address@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001', 'wpcpm_mentor_profile' => 'https://profiles.wordpress.org/ada-example/' ) );
$GLOBALS['users'][66]->user_login = 'hal-old';
$r                                = WPCPM_Mentor_Activation::activate( selected_app(), 0 );
ck( 'an account found by its stored profile is the one made a mentor, with its own row: no second account by the new address',
	array( $r['user_id'], count( $GLOBALS['users'] ), $GLOBALS['updated'][0]['id'], $r['invited'] ),
	array( 66, 1, 'recRETURNING00001', true ) );

fresh();
an_account( 65, 'Gus Example', 'gus@example.test', array(), array() );
$GLOBALS['users'][65]->user_login = 'ada-example';
$gus                              = selected_app();
$r                                = WPCPM_Mentor_Activation::activate( $gus, 7, 'Wrote in on Monday.' );
ck( 'the press\'s note and what the provisioning noticed are kept in the history, by who pressed, and why no invitation went',
	array( event_back( $gus, 2 )['actor'], event_back( $gus, 2 )['note'] ),
	array( 7, 'Wrote in on Monday. Added the Mentor role to the existing account "ada-example" (Ada Example). Linked mentor record to the pre-existing account "ada-example" (Ada Example) by username. Confirm it is the same person. No invitation was sent, since a match by username alone may be somebody else: once that is confirmed, press Resend invite on the Mentors screen\'s Accounts tab.' ) );
ck( 'an account found by its username alone is made a mentor and sent no invitation until a person confirms it is the same person',
	array( $r['outcome'], $r['user_id'], $GLOBALS['queue'], $r['invited'], WPCPM_Mentor_Application::linked_by_of( get_post( $gus ) ) ),
	array( 'activated', 65, array(), false, 'login' ) );
ck( 'and the answer says the invitation is held, and why',
	array( $r['held'], $r['message'] ),
	array( 'login', 'Made a mentor, but the invitation is held: the account was matched by its username alone. Confirm it is the same person, then press Resend invite on the Mentors screen\'s Accounts tab.' ) );
ck( 'and the account is marked held, with the reason, so the bulk invite passes it over until it is sent from its row', get_user_meta( 65, WPCPM_Mentor_Provision::USER_INVITE_HELD, true ), 'login' );

// An account an earlier activation held, found by the address this time, is the person's: the
// invitation goes, and nothing holds it back any more.
fresh();
an_account( 79, 'Ada Example', 'ada@example.test', array( 'subscriber' ), array( WPCPM_Mentor_Provision::USER_INVITE_HELD => 'login' ) );
$r = WPCPM_Mentor_Activation::activate( selected_app(), 0 );
ck( 'an account held before, found by its address this time, is sent the invitation and is held no longer',
	array( $r['user_id'], $r['invited'], $GLOBALS['queue'], isset( $GLOBALS['umeta'][ 79 ][ WPCPM_Mentor_Provision::USER_INVITE_HELD ] ) ),
	array( 79, true, array( 79 ), false ) );

echo "\n=== How each account was found, for the Activated list ===\n";

fresh();
$made_new = selected_app();
WPCPM_Mentor_Activation::activate( $made_new, 0 );
an_account( 67, 'Ada Example', 'cy@example.test', array( 'subscriber' ) );
$by_mail = selected_app( array( 'email' => 'cy@example.test', 'profile' => 'cy-example' ) );
WPCPM_Mentor_Activation::activate( $by_mail, 0 );
an_account( 68, 'Ada Example', 'old-address@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_profile' => 'https://profiles.wordpress.org/hal-example/' ) );
$by_profile = selected_app( array( 'email' => 'hal@example.test', 'profile' => 'hal-example' ) );
WPCPM_Mentor_Activation::activate( $by_profile, 0 );
an_account( 69, 'Old Example', 'older@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recHOLDER00000002' ) );
$GLOBALS['by_email'] = array( 'recHOLDER00000002' );
$by_record           = selected_app( array( 'email' => 'new-address@example.test', 'profile' => 'old-example' ) );
$GLOBALS['queue']    = array();
$by_row              = WPCPM_Mentor_Activation::activate( $by_record, 0 );
ck( 'a new account, one found by the address, by the stored profile, by the record of the row that holds the address with no other account answering, and none yet for an application not made a mentor',
	array( WPCPM_Mentor_Application::linked_by_of( get_post( $made_new ) ), WPCPM_Mentor_Application::linked_by_of( get_post( $by_mail ) ), WPCPM_Mentor_Application::linked_by_of( get_post( $by_profile ) ), WPCPM_Mentor_Application::linked_by_of( get_post( $by_record ) ), WPCPM_Mentor_Application::user_of( get_post( $by_record ) ), WPCPM_Mentor_Application::linked_by_of( get_post( selected_app() ) ) ),
	array( 'created', 'email', 'profile', 'row', 69, '' ) );
ck( 'the record holder made a mentor with no other account answering for the applicant is sent the invitation as planned', array( $GLOBALS['queue'], $by_row['invited'], $by_row['held'] ), array( array( 69 ), true, '' ) );

echo "\n=== Airtable says no ===\n";

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$bo                       = selected_app();
$r                        = WPCPM_Mentor_Activation::activate( $bo, 0 );
ck( 'Airtable refusing the row keeps nobody waiting: the account made, the invitation queued, the application active, the refusal on it in Airtable\'s words',
	array( $r['outcome'], $r['airtable'], WPCPM_Mentor_Application::state_of( get_post( $bo ) ), $GLOBALS['queue'], WPCPM_Mentor_Application::airtable_of( get_post( $bo ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $bo ) )['error'], last_events( $bo, 1 ) ),
	array( 'activated', 'refused', 'active', array( $r['user_id'] ), 'refused', 'Airtable refused the write.', array( 'Airtable row refused' ) ) );
ck( 'the refusal is the event\'s note, by the daily check, and the answer says it', array( event_back( $bo, 1 )['note'], event_back( $bo, 1 )['actor'], $r['message'] ), array( 'Airtable refused the write.', 0, 'Made a mentor, but the Airtable row was not written: Airtable refused the write.' ) );
ck( 'the account holds no record id until the row is written, waits for its row, and is one with no row yet',
	array( get_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', true ), get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), WPCPM_Mentor_Activation::unwritten_count() ),
	array( '', 1, 1 ) );
$GLOBALS['refuse_create'] = null;
$GLOBALS['events']        = array();
$again                    = WPCPM_Mentor_Activation::retry_airtable( $bo, 7 );
ck( 'Write the Airtable row again writes it, stores the record id on the account and says so, by whoever pressed',
	array( $again['outcome'], get_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', true ), last_events( $bo, 1 ), event_back( $bo, 1 )['actor'], WPCPM_Mentor_Activation::unwritten_count(), get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ) ),
	array( 'written', 'recNEWMENTOR00001', array( 'Airtable row written' ), 7, 0, '' ) );
ck( 'under the application\'s lock, read again once it is held, and in a sentence',
	array( array_slice( $GLOBALS['events'], 0, 2 ), $again['message'] ),
	array( array( 'lock insert', 'drop post_meta ' . $bo ), 'The Airtable row is written, and the mentor\'s account holds its record.' ) );
$second = WPCPM_Mentor_Activation::retry_airtable( $bo, 7 );
ck( 'and a second press writes nothing', array( $second['outcome'], count( $GLOBALS['created'] ), $second['message'] ), array( 'not-needed', 1, 'Nothing was written: the mentor\'s Airtable row needs nothing more.' ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$re                       = selected_app();
WPCPM_Mentor_Activation::activate( $re, 0 );
$refused_again = WPCPM_Mentor_Activation::retry_airtable( $re, 7 );
ck( 'a retry Airtable refuses again keeps the refusal, by who pressed, and says it', array( $refused_again['outcome'], $refused_again['message'], last_events( $re, 2 ), event_back( $re, 1 )['actor'], WPCPM_Mentor_Application::airtable_of( get_post( $re ) )['state'], count( $GLOBALS['queue'] ) ), array( 'refused', 'The Airtable row was not written: Airtable refused the write.', array( 'Airtable row refused', 'Airtable row refused' ), 7, 'refused', 1 ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'The connection was reset.' );
$died                     = selected_app();
WPCPM_Mentor_Activation::activate( $died, 0 );
$GLOBALS['refuse_create'] = null;
$GLOBALS['by_email']      = array( 'recDIEDROW0000001' );
ck( 'a retry finds the row a request that died had made, by its address, and creates nothing',
	array( WPCPM_Mentor_Activation::retry_airtable( $died, 7 )['outcome'], $GLOBALS['created'], WPCPM_Mentor_Application::airtable_of( get_post( $died ) )['record'] ),
	array( 'written', array(), 'recDIEDROW0000001' ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$di                       = selected_app();
$r                        = WPCPM_Mentor_Activation::activate( $di, 0 );
update_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', 'recBYHAND00000001' );
$GLOBALS['refuse_create'] = null;
$by_hand                  = WPCPM_Mentor_Activation::retry_airtable( $di, 7 );
ck( 'a row a person made by hand that the sync linked meanwhile is not written twice: the retry finds the account\'s record and writes nothing',
	array( $by_hand['outcome'], $GLOBALS['created'], WPCPM_Mentor_Application::airtable_of( get_post( $di ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $di ) )['record'] ),
	array( 'not-needed', array(), 'found', 'recBYHAND00000001' ) );
ck( 'and the history says so, by who pressed, as settled and never as written', array( last_events( $di, 1 ), event_back( $di, 1 )['actor'], event_back( $di, 1 )['note'], get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), $by_hand['message'] ), array( array( 'Airtable row settled' ), 7, 'The account already holds its Airtable record; nothing was written.', '', 'Nothing was written: the mentor\'s account holds its Airtable record already.' ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$jo                       = selected_app();
$r                        = WPCPM_Mentor_Activation::activate( $jo, 0 );
$report                   = array();
WPCPM_Mentor_Provision::provision( array( 'record_id' => 'recBYHAND00000002', 'name' => 'Ada Example', 'profile' => 'https://profiles.wordpress.org/ada-example/', 'email' => 'ada@example.test' ), $report );
ck( 'once the sync links the account to a row, it is no longer one with no row, and the sync may revoke it as any other',
	array( get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), WPCPM_Mentor_Activation::unwritten_count() ), array( '', 0 ) );
$jo_events = WPCPM_Mentor_Application::events( $jo );
ck( 'and the application settles: its Airtable line reads found with that record, and the history says who may have linked it, by the site and never as a write',
	array( WPCPM_Mentor_Application::airtable_of( get_post( $jo ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $jo ) )['record'], last_events( $jo, 1 ), end( $jo_events )['note'], end( $jo_events )['actor'] ),
	array( 'found', 'recBYHAND00000002', array( 'Airtable row settled' ), 'Linked by the mentors sync or by hand; nothing was written from here.', 0 ) );
$GLOBALS['settings']['application_approved_days'] = 30;
ck( 'so retention no longer keeps it for ever: past the approved days it goes, as any written one does', array( WPCPM_Mentor_Application::purge( time() + 31 * DAY_IN_SECONDS ), get_post( $jo ) ), array( 1, null ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$daily                    = selected_app();
$r                        = WPCPM_Mentor_Activation::activate( $daily, 0 );
$report                   = array();
WPCPM_Mentor_Provision::provision( array( 'record_id' => 'recBYHAND00000004', 'name' => 'Ada Example', 'profile' => 'https://profiles.wordpress.org/ada-example/', 'email' => 'ada@example.test' ), $report );
WPCPM_Mentor_Application::daily();
ck( 'the daily run settles a row the sync linked, so it no longer reads as refused', array( WPCPM_Mentor_Application::airtable_of( get_post( $daily ) )['state'], WPCPM_Mentor_Activation::settle_all() ), array( 'found', 0 ) );

fresh();
$GLOBALS['write_log']     = array();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$ky                       = selected_app();
WPCPM_Mentor_Activation::activate( $ky, 0 );
ck( 'the account is marked as waiting for its row before the application says its row is pending',
	array_slice( array_values( array_intersect( $GLOBALS['write_log'], array( WPCPM_Mentor_Provision::USER_ROW_PENDING, WPCPM_Mentor_Application::META_AIRTABLE ) ) ), 0, 2 ),
	array( WPCPM_Mentor_Provision::USER_ROW_PENDING, WPCPM_Mentor_Application::META_AIRTABLE ) );
delete_user_meta( WPCPM_Mentor_Application::user_of( get_post( $ky ) ), WPCPM_Mentor_Provision::USER_ROW_PENDING );
update_user_meta( WPCPM_Mentor_Application::user_of( get_post( $ky ) ), 'wpcpm_mentor_record_id', 'recBYHAND00000003' );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $ky ) ] = (string) ( time() + 600 );
ck( 'settle() leaves an application whose lock another request holds', array( WPCPM_Mentor_Activation::settle( get_post( $ky ) ), WPCPM_Mentor_Application::airtable_of( get_post( $ky ) )['state'] ), array( false, 'refused' ) );
unset( $GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $ky ) ] );
$GLOBALS['wpdb']->statements = array();
$GLOBALS['events']           = array();
ck( 'and settles it once the lock is free, read again under the lock', array( WPCPM_Mentor_Activation::settle( get_post( $ky ) ), WPCPM_Mentor_Application::airtable_of( get_post( $ky ) )['state'], array_slice( $GLOBALS['events'], 0, 2 ) ), array( true, 'found', array( 'lock insert', 'drop post_meta ' . $ky ) ) );

fresh();
$written = selected_app();
WPCPM_Mentor_Activation::activate( $written, 0 );
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$waiting                  = selected_app( array( 'email' => 'w1@example.test', 'profile' => 'waiting-one' ) );
WPCPM_Mentor_Activation::activate( $waiting, 0 );
$GLOBALS['wpdb']->statements = array();
ck( 'settle() asks no lock of an application with nothing to settle: a written row, or one still waiting for its row, as a screen draws them',
	array( WPCPM_Mentor_Activation::settle( get_post( $written ) ), WPCPM_Mentor_Activation::settle( get_post( $waiting ) ), WPCPM_Mentor_Activation::unwritten_count(), count( WPCPM_Mentor_Activation::activated_posts( 50 ) ), WPCPM_Mentor_Activation::settle_all(), $GLOBALS['wpdb']->statements ),
	array( false, false, 1, 2, 0, array() ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$lo                       = selected_app();
WPCPM_Mentor_Activation::activate( $lo, 0 );
unset( $GLOBALS['users'][ WPCPM_Mentor_Application::user_of( get_post( $lo ) ) ] );
$GLOBALS['settings']['application_approved_days'] = 30;
ck( 'an application whose account is gone settles as gone, so retention applies to it',
	array( WPCPM_Mentor_Activation::settle( get_post( $lo ) ), WPCPM_Mentor_Application::airtable_of( get_post( $lo ) )['state'], last_events( $lo, 1 ), event_back( $lo, 1 )['note'], WPCPM_Mentor_Application::purge( time() + 31 * DAY_IN_SECONDS ), get_post( $lo ) ),
	array( true, 'gone', array( 'Airtable row settled' ), 'The account made for this mentor no longer exists; nothing more is written for it.', 1, null ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$gone_retry               = selected_app();
WPCPM_Mentor_Activation::activate( $gone_retry, 0 );
unset( $GLOBALS['users'][ WPCPM_Mentor_Application::user_of( get_post( $gone_retry ) ) ] );
$GLOBALS['refuse_create'] = null;
$gone_press               = WPCPM_Mentor_Activation::retry_airtable( $gone_retry, 7 );
ck( 'Write the Airtable row again for an account that is gone writes nothing, and settles the row as gone',
	array( $gone_press['outcome'], $gone_press['message'], $GLOBALS['created'], WPCPM_Mentor_Application::airtable_of( get_post( $gone_retry ) )['state'] ),
	array( 'not-needed', 'The account made for this mentor no longer exists; nothing more is written for it.', array(), 'gone' ) );

fresh();
$GLOBALS['connected'] = false;
$ed                   = selected_app();
$r                    = WPCPM_Mentor_Activation::activate( $ed, 0 );
ck( 'with Airtable not connected the mentor is made and the refusal says so, and nothing is asked of Airtable',
	array( $r['outcome'], WPCPM_Mentor_Application::airtable_of( get_post( $ed ) )['error'], $GLOBALS['created'], $GLOBALS['updated'], $GLOBALS['searched'], $r['message'] ),
	array( 'activated', 'Airtable is not connected.', array(), array(), array(), 'Made a mentor, but the Airtable row was not written: Airtable is not connected.' ) );
$not_active = WPCPM_Mentor_Activation::retry_airtable( selected_app(), 7 );
ck( 'Write the Airtable row again is refused on an application that is not active', array( $not_active['outcome'], $not_active['message'] ), array( 'not-active', 'Only an application that was made a mentor has an Airtable row to write, and this one was not.' ) );
ck( 'and on a number that is no application', WPCPM_Mentor_Activation::retry_airtable( 999999, 7 )['outcome'], 'unknown' );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $ed ) ] = (string) ( time() + 600 );
$busy_press = WPCPM_Mentor_Activation::retry_airtable( $ed, 7 );
ck( 'and while another request holds the application, with nothing asked of Airtable', array( $busy_press['outcome'], $busy_press['message'], $GLOBALS['searched'] ), array( 'busy', 'Another request is working on this application at this moment, so nothing was done.', array() ) );

echo "\n=== The mentors sync meanwhile ===\n";

fresh();
an_account( 81, 'Waiting Example', 'w81@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001', 'wpcpm_mentor_active' => 1, WPCPM_Mentor_Provision::USER_ROW_PENDING => 1 ) );
an_account( 82, 'Fresh Example', 'f82@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recNEWMENTOR00001', 'wpcpm_mentor_active' => 1, WPCPM_Mentor_Provision::USER_ACTIVATED_AT => time() ) );
an_account( 83, 'Gone Example', 'g83@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recGONE0000000001', 'wpcpm_mentor_active' => 1 ) );
an_account( 84, 'Earlier Example', 'e84@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recEARLIER0000001', 'wpcpm_mentor_active' => 1, WPCPM_Mentor_Provision::USER_ACTIVATED_AT => time() - DAY_IN_SECONDS ) );
revoke( time() - 60 );
ck( 'the mentors sync leaves an account alone while its row is not written, and one made a mentor during its run, and revokes the rest as before, one made a mentor before the run among them',
	array( get_user_meta( 81, 'wpcpm_mentor_active', true ), get_user_meta( 82, 'wpcpm_mentor_active', true ), get_user_meta( 83, 'wpcpm_mentor_active', true ), get_user_meta( 84, 'wpcpm_mentor_active', true ) ),
	array( 1, 1, 0, 0 ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$later_row                = selected_app();
$r                        = WPCPM_Mentor_Activation::activate( $later_row, 0 );
update_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ACTIVATED_AT, time() - DAY_IN_SECONDS );
$GLOBALS['refuse_create'] = null;
WPCPM_Mentor_Activation::retry_airtable( $later_row, 7 );
revoke( time() - 60 );
ck( 'a row written again after a sync run started keeps the mentor: the write stamps the account before its wait ends, so the run, which did not read the new row, passes it by',
	array( get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ACTIVATED_AT, true ) >= time() - 60, get_user_meta( $r['user_id'], 'wpcpm_mentor_active', true ) ),
	array( '', true, 1 ) );

fresh();
$GLOBALS['before_create'] = static function () {
	foreach ( array_keys( $GLOBALS['umeta'] ) as $account ) {
		$GLOBALS['umeta'][ $account ][ WPCPM_Mentor_Provision::USER_ACTIVATED_AT ] = time() - DAY_IN_SECONDS;
	}
};
$r = WPCPM_Mentor_Activation::activate( selected_app(), 0 );
revoke( time() - 60 );
ck( 'and so does a run that started between the activation and its own first write',
	array( $r['airtable'], get_user_meta( $r['user_id'], 'wpcpm_mentor_active', true ) ), array( 'created', 1 ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$found_row                = selected_app();
$r                        = WPCPM_Mentor_Activation::activate( $found_row, 0 );
update_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ACTIVATED_AT, time() - DAY_IN_SECONDS );
update_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', 'recBYHAND00000005' );
WPCPM_Mentor_Activation::retry_airtable( $found_row, 7 );
revoke( time() - 60 );
ck( 'and so does a row the retry finds linked by hand, which ends the wait the same way', array( WPCPM_Mentor_Application::airtable_of( get_post( $found_row ) )['state'], get_user_meta( $r['user_id'], 'wpcpm_mentor_active', true ) ), array( 'found', 1 ) );

echo "\n=== Two at once ===\n";

fresh();
$fa   = selected_app();
$held = (string) ( time() + 600 );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $fa ) ] = $held;
$r = WPCPM_Mentor_Activation::activate( $fa, 0 );
ck( 'an activation already in hand is refused, and nothing is made', array( $r['outcome'], count( $GLOBALS['users'] ), $GLOBALS['created'], $GLOBALS['queue'], WPCPM_Mentor_Application::state_of( get_post( $fa ) ) ), array( 'busy', 0, array(), array(), 'selected' ) );
ck( 'and told it is in hand', $r['message'], 'Another request is making this applicant a mentor at this moment, so nothing was done.' );
ck( 'and the lock another request holds is left to it', lock_row( $fa ), $held );
ck( 'the lapse clock leaves an application whose activation is in hand', array( WPCPM_Mentor_Application::lapse( time() + 91 * DAY_IN_SECONDS ), WPCPM_Mentor_Application::state_of( get_post( $fa ) ), lock_row( $fa ) ), array( 0, 'selected', $held ) );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $fa ) ] = (string) ( time() - 1 );
ck( 'a lock left by a request that died is taken over once it has run out, and let go of after', array( WPCPM_Mentor_Activation::activate( $fa, 0 )['outcome'], lock_row( $fa ) ), array( 'activated', null ) );

fresh();
$late = selected_app();
$GLOBALS['events'] = array();
ck( 'the lapse clock takes the application\'s lock to lapse it, reads it again under the lock, and lets it go', array( WPCPM_Mentor_Application::lapse( time() + 91 * DAY_IN_SECONDS ), WPCPM_Mentor_Application::state_of( get_post( $late ) ), array_slice( $GLOBALS['events'], 0, 2 ), lock_row( $late ) ), array( 1, 'lapsed', array( 'lock insert', 'drop post_meta ' . $late ), null ) );

fresh();
$throws                    = selected_app();
$GLOBALS['throw_on_write'] = WPCPM_Mentor_Application::META_AIRTABLE;
$caught                    = '';
try {
	WPCPM_Mentor_Activation::activate( $throws, 0 );
} catch ( RuntimeException $e ) {
	$caught = $e->getMessage();
}
unset( $GLOBALS['throw_on_write'] );
ck( 'an activation that fails part way lets the application\'s lock go, and the failure reaches the caller', array( $caught, lock_row( $throws ) ), array( 'write refused: _wpcpm_mapp_airtable', null ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$throws                   = selected_app();
WPCPM_Mentor_Activation::activate( $throws, 0 );
$GLOBALS['refuse_create']  = null;
$GLOBALS['throw_on_write'] = WPCPM_Mentor_Application::META_AIRTABLE;
$caught                    = '';
try {
	WPCPM_Mentor_Activation::retry_airtable( $throws, 7 );
} catch ( RuntimeException $e ) {
	$caught = $e->getMessage();
}
unset( $GLOBALS['throw_on_write'] );
ck( 'so does a retry', array( $caught, lock_row( $throws ) ), array( 'write refused: _wpcpm_mapp_airtable', null ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$throws                   = selected_app();
$r                        = WPCPM_Mentor_Activation::activate( $throws, 0 );
delete_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING );
update_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', 'recBYHAND00000006' );
$GLOBALS['throw_on_write'] = WPCPM_Mentor_Application::META_AIRTABLE;
$caught                    = '';
try {
	WPCPM_Mentor_Activation::settle( get_post( $throws ) );
} catch ( RuntimeException $e ) {
	$caught = $e->getMessage();
}
unset( $GLOBALS['throw_on_write'] );
ck( 'and so does settling', array( $caught, lock_row( $throws ) ), array( 'write refused: _wpcpm_mapp_airtable', null ) );

echo "\n=== No account ===\n";

fresh();
$GLOBALS['insert_error'] = new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
$gu                      = selected_app();
$r                       = WPCPM_Mentor_Activation::activate( $gu, 0 );
ck( 'an account WordPress will not make leaves the application selected, with why, and nothing goes to Airtable, the queue or Slack',
	array( $r['outcome'], WPCPM_Mentor_Application::state_of( get_post( $gu ) ), WPCPM_Mentor_Application::problem_of( get_post( $gu ) )['message'], $GLOBALS['created'], $GLOBALS['queue'], $GLOBALS['slack'] ),
	array( 'no-account', 'selected', 'Could not create the account "ada-example": Sorry, that username already exists!', array(), array(), array() ) );
ck( 'with when, and the answer says why in a sentence', array( WPCPM_Mentor_Application::problem_of( get_post( $gu ) )['at'] > 0, $r['message'] ), array( true, 'The account could not be made, so the application stays selected: Could not create the account "ada-example": Sorry, that username already exists!' ) );
$GLOBALS['insert_error'] = null;
ck( 'and once it can be made, the reason goes', array( WPCPM_Mentor_Activation::activate( $gu, 0 )['outcome'], WPCPM_Mentor_Application::problem_of( get_post( $gu ) ) ), array( 'activated', array() ) );

echo "\n=== The Activated list ===\n";

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$first                    = selected_app();
WPCPM_Mentor_Activation::activate( $first, 0 );
$GLOBALS['refuse_create'] = null;
$later                    = array();
for ( $i = 0; $i < 51; $i++ ) {
	$one     = selected_app( array( 'email' => 'm' . $i . '@example.test', 'profile' => 'mentor-' . $i ) );
	$later[] = $one;
	WPCPM_Mentor_Activation::activate( $one, 0 );
	update_post_meta( $one, WPCPM_Mentor_Application::META_ACTIVATED_AT, time() + $i );
}
$drawn = array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Activation::activated_posts( 50 ) );
ck( 'the Activated list puts the row waiting for its Airtable write first, even behind 51 newer ones, then the newest',
	array( $drawn[0], $drawn[1], count( $drawn ) ), array( $first, end( $later ), 50 ) );
ck( 'and the rows with no row yet are the one waiting', array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Activation::unwritten() ), array( $first ) );

fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$older_wait               = selected_app();
WPCPM_Mentor_Activation::activate( $older_wait, 0 );
$newer_wait = selected_app( array( 'email' => 'n1@example.test', 'profile' => 'newer-one' ) );
WPCPM_Mentor_Activation::activate( $newer_wait, 0 );
$tied = selected_app( array( 'email' => 'n2@example.test', 'profile' => 'newer-two' ) );
WPCPM_Mentor_Activation::activate( $tied, 0 );
update_post_meta( $older_wait, WPCPM_Mentor_Application::META_ACTIVATED_AT, 1000 );
update_post_meta( $newer_wait, WPCPM_Mentor_Application::META_ACTIVATED_AT, 2000 );
update_post_meta( $tied, WPCPM_Mentor_Application::META_ACTIVATED_AT, 2000 );
ck( 'the rows waiting for their row are oldest first, a tie by the ID', array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Activation::unwritten() ), array( $older_wait, $newer_wait, $tied ) );
ck( 'and a bound of none still draws one', count( WPCPM_Mentor_Activation::activated_posts( 0 ) ), 1 );

echo "\n=== What the step reaches ===\n";

ck( 'nothing was asked of WordPress that the stand-ins do not model', array( isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), $GLOBALS['wpdb']->unmodeled ), array( array(), array() ) );
ck( 'and the run raised no PHP warning or notice', $php_warnings, array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
