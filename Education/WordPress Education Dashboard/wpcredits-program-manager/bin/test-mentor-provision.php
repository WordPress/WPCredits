<?php
/**
 * The shared provisioning step (1.122.33): `WPCPM_Mentor_Provision`.
 *
 * Design of 16 October 2026, section 3, decision 6: the account half of the mentors sync, moved to
 * one class that the sync and the activation of an application both call, so that there is one way
 * to make a person a mentor. What each block pins, and why:
 *
 * - **The sync's rule, as it was.** A new mentor gets an account named after their WordPress.org
 *   username, with the address, the name, the profile as its website and the Mentor role, the three
 *   mentor meta, and the invitation queued and counted under the invitation setting. An account that
 *   exists is found by its record id, its address, then its username, and linked: the Mentor role is
 *   added beside the roles it had (never set), an Administrator keeps hers, an account linked by its
 *   username alone is flagged for a person to confirm, and one already linked to another record is a
 *   conflict, never a second link.
 * - **An account the caller found is linked as given**, with how it was found kept: the
 *   username-only notice follows the match kind, and no lookup makes a second account (Review Focus
 *   1). The claimed-record check still applies to it.
 * - **What is refused** is skipped with a notice, quoting what was read.
 * - **A mentor with no record id yet**, which is what an application is until its row exists: no
 *   lookup and no write by an empty record id (an account holding an empty one is nobody's).
 * - **`find_account()`** takes the address, then the username, then the stored profile, and says
 *   which.
 * - **The row flag** an activation leaves on an account is cleared when the sync links the account
 *   to a row.
 * - **What the report says of a linked account** (1.123.42): who it is, whether it was an active
 *   mentor before the link, and how it was found, so the sync can decide about the welcome (see
 *   bin/test-mentors-sync-welcome.php); the step queues nothing for it.
 * - **One way to make a mentor**: the sync keeps no account code of its own.
 *
 * Runs on the shared stand-in (bin/stubs/mentor-applications.php) with the real WPCPM_Roles,
 * WPCPM_Mentors_Sync and WPCPM_Mentor_Provision.
 *
 * Run from the plugin root:  php bin/test-mentor-provision.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['queued']    = array();
$GLOBALS['settings']  = array();
$GLOBALS['unmodeled'] = array();

/** The invitation queue, at its contract: an account is queued once. */
class WPCPM_Mail {
	public static function queue_invite( $user_id ) {
		if ( ! in_array( (int) $user_id, $GLOBALS['queued'], true ) ) {
			$GLOBALS['queued'][] = (int) $user_id;
		}
	}
}

/** The settings the step reads, as a check sets them. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
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
function fresh( array $settings = array() ) {
	$GLOBALS['users']        = array();
	$GLOBALS['umeta']        = array();
	$GLOBALS['queued']       = array();
	$GLOBALS['settings']     = $settings;
	$GLOBALS['insert_error'] = null;
}

/** The mentor as the sync reads one from the base, or as an application gives one. */
function mentor( $record ) {
	return array(
		'record_id' => $record,
		'name'      => 'Ada Example',
		'profile'   => 'https://profiles.wordpress.org/ada-example/',
		'email'     => 'ada@example.test',
	);
}

/** An account on the site: the stand-in's takes its login from the name, so the login is set after. */
function account( $id, $login, $email, array $roles, array $meta = array() ) {
	an_account( $id, 'Account ' . $id, $email, $roles, $meta );
	$GLOBALS['users'][ $id ]->user_login = $login;
}

function roles( $id ) {
	return $GLOBALS['users'][ $id ]->roles;
}
function meta( $id, $key ) {
	return get_user_meta( $id, $key, true );
}
function login( $id ) {
	return $GLOBALS['users'][ $id ]->user_login;
}
function email( $id ) {
	return $GLOBALS['users'][ $id ]->user_email;
}
function display( $id ) {
	return $GLOBALS['users'][ $id ]->display_name;
}
function url( $id ) {
	return $GLOBALS['users'][ $id ]->user_url;
}

echo "\n=== A new mentor ===\n";
fresh( array( 'send_welcome_email' => true ) );
$report = array();
$id     = WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report );
ck( 'a new mentor gets an account named after their WordPress.org username, with the address, the name, the profile as its website and the Mentor role',
	array( login( $id ), email( $id ), display( $id ), url( $id ), roles( $id ) ),
	array( 'ada-example', 'ada@example.test', 'Ada Example', 'https://profiles.wordpress.org/ada-example/', array( 'wpcpm_mentor' ) ) );
ck( 'and the three mentor meta the sync writes',
	array( meta( $id, 'wpcpm_mentor_record_id' ), meta( $id, 'wpcpm_mentor_profile' ), meta( $id, 'wpcpm_mentor_active' ) ),
	array( 'recNEWMENTOR00001', 'https://profiles.wordpress.org/ada-example/', 1 ) );
ck( 'with invitation emails on, the invitation is queued and counted', array( $GLOBALS['queued'], $report['stats']['created'], $report['stats']['invited'] ), array( array( $id ), 1, 1 ) );

fresh( array( 'send_welcome_email' => false ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report );
ck( 'with them off, nothing is queued', array( $GLOBALS['queued'], $report['stats']['invited'] ), array( array(), 0 ) );

echo "\n=== An account that exists ===\n";
fresh();
account( 41, 'ada-example', 'old@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recNEWMENTOR00001', WPCPM_Mentor_Provision::USER_ROW_PENDING => 1 ) );
$report = array();
ck( 'found by its record id, an account is updated, not linked again, and linking it to its row clears the flag an activation left',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), $report['stats']['updated'], $report['stats']['linked'], meta( 41, WPCPM_Mentor_Provision::USER_ROW_PENDING ) ),
	array( 41, 1, 0, '' ) );

fresh();
account( 42, 'someone-else', 'ada@example.test', array( 'subscriber' ) );
$report = array();
ck( 'found by its address, an account with no record is linked and gains the Mentor role beside the one it had, with a notice',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), roles( 42 ), $report['stats']['linked'], $report['notices'] ),
	array( 42, array( 'subscriber', 'wpcpm_mentor' ), 1, array( 'Added the Mentor role to the existing account "someone-else" (Ada Example).' ) ) );

fresh();
account( 43, 'ada-example', 'other@example.test', array( 'wpcpm_mentor' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report );
ck( 'found by its username alone, it is linked with the notice that asks a person to confirm',
	$report['notices'], array( 'Linked mentor record to the pre-existing account "ada-example" (Ada Example) by username. Confirm it is the same person.' ) );

fresh();
account( 44, 'ada-example', 'ada@example.test', array( 'administrator' ) );
$report = array();
ck( 'an Administrator\'s account is linked and keeps its roles', array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), roles( 44 ) ), array( 44, array( 'administrator' ) ) );

fresh();
account( 45, 'ada-example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001' ) );
$report = array();
ck( 'an account linked to another mentor record is refused, counted as a conflict and named',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), $report['stats']['conflicts'], $report['notices'] ),
	array( 0, 1, array( 'Skipped Ada Example - the account "ada-example" is already linked to a different Airtable mentor record. Resolve the duplicate in Airtable.' ) ) );

fresh();
account( 50, 'renamed-example', 'renamed@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRENAMED0000001' ) );
$report = array();
ck( 'an account the caller found is linked as given, and no lookup by address or username makes another',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recRENAMED0000001' ), $report, array( 'user' => get_userdata( 50 ), 'how' => 'profile' ) ), count( $GLOBALS['users'] ), $report['stats']['created'] ),
	array( 50, 1, 0 ) );

fresh();
account( 51, 'ada-example', 'other@example.test', array( 'wpcpm_mentor' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report, WPCPM_Mentor_Provision::find_account( 'none@example.test', 'ada-example' ) );
$by_username = $report['notices'];
fresh();
account( 51, 'ada-example', 'other@example.test', array( 'wpcpm_mentor' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report, WPCPM_Mentor_Provision::find_account( 'other@example.test', 'nobody' ) );
$by_address = $report['notices'];
ck( 'and how it was found is kept: the answer of find_account() passed on carries the username-only notice for a username match and none for an address',
	array( $by_username, $by_address ),
	array( array( 'Linked mentor record to the pre-existing account "ada-example" (Ada Example) by username. Confirm it is the same person.' ), array() ) );

fresh();
account( 52, 'renamed-example', 'renamed@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001' ) );
$report = array();
ck( 'an account the caller found that is linked to another mentor record is still a conflict',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report, array( 'user' => get_userdata( 52 ), 'how' => 'profile' ) ), $report['stats']['conflicts'], meta( 52, 'wpcpm_mentor_record_id' ) ),
	array( 0, 1, 'recRETURNING00001' ) );

fresh();
account( 53, 'ada-example', 'ada@example.test', array( 'wpcpm_mentor' ) );
$report = array();
ck( 'an answer that found nobody is no account the caller found: the lookup is made, and the account is the one the address finds',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report, array( 'user' => null, 'how' => '' ) ), count( $GLOBALS['users'] ) ),
	array( 53, 1 ) );

echo "\n=== What the report says of an account that was linked (1.123.42) ===\n";
// The step reports what each account it linked was before the link and how it was found, and sends
// nothing itself: the mentors sync decides about the welcome, and the activation queues its own.
fresh( array( 'send_welcome_email' => true ) );
account( 60, 'ada-active', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recACTIVEMENTOR01', 'wpcpm_mentor_active' => 1 ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recACTIVEMENTOR01' ), $report );
ck( 'an account that held the Mentor role with its active flag at 1 is reported as an active mentor, found by its record',
	$report['linked_accounts'], array( array( 'user_id' => 60, 'was_active_mentor' => true, 'how' => 'record' ) ) );

fresh( array( 'send_welcome_email' => true ) );
account( 61, 'ada-former', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recFORMERMENTOR01', 'wpcpm_mentor_active' => 0 ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recFORMERMENTOR01' ), $report );
ck( 'a former mentor, the role kept and the flag at 0, is not an active mentor, and is one once linked',
	array( $report['linked_accounts'], meta( 61, 'wpcpm_mentor_active' ) ), array( array( array( 'user_id' => 61, 'was_active_mentor' => false, 'how' => 'record' ) ), 1 ) );

fresh( array( 'send_welcome_email' => true ) );
account( 62, 'ada-student', 'ada@example.test', array( 'wpcpm_student' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recSTUDENTMENTOR1' ), $report );
ck( 'an account with no Mentor role is not an active mentor, found by its address, and has the role once linked',
	array( $report['linked_accounts'], roles( 62 ) ), array( array( array( 'user_id' => 62, 'was_active_mentor' => false, 'how' => 'email' ) ), array( 'wpcpm_student', 'wpcpm_mentor' ) ) );

fresh( array( 'send_welcome_email' => true ) );
account( 63, 'ada-example', 'other@example.test', array( 'wpcpm_student' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recLOGINMENTOR001' ), $report );
ck( 'a match by username alone is reported as one', $report['linked_accounts'], array( array( 'user_id' => 63, 'was_active_mentor' => false, 'how' => 'login' ) ) );

fresh( array( 'send_welcome_email' => true ) );
account( 64, 'ada-flag-only', 'ada@example.test', array( 'wpcpm_student' ), array( 'wpcpm_mentor_active' => 1 ) );
account( 65, 'zoe-role-example', 'zoe@example.test', array( 'wpcpm_mentor' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recFLAGONLY000001' ), $report );
$both = array_merge( mentor( 'recROLEONLY000001' ), array( 'email' => 'zoe@example.test', 'profile' => 'https://profiles.wordpress.org/zoe-role-example/' ) );
WPCPM_Mentor_Provision::provision( $both, $report );
ck( 'the flag at 1 without the role, and the role without the flag, are each not an active mentor',
	array_column( $report['linked_accounts'], 'was_active_mentor', 'user_id' ), array( 64 => false, 65 => false ) );

fresh( array( 'send_welcome_email' => true ) );
account( 66, 'ada-example', 'ada@example.test', array( 'wpcpm_student' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recQUEUENOTHING01' ), $report );
ck( 'linking an account queues nothing, whatever the invitation setting says: the mentors sync decides, and the activation queues its own',
	array( $GLOBALS['queued'], $report['stats']['invited'] ), array( array(), 0 ) );

fresh( array( 'send_welcome_email' => true ) );
account( 67, 'ada-example', 'ada@example.test', array( 'wpcpm_student' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recFOUNDBYCALLER1' ), $report, array( 'user' => get_userdata( 67 ), 'how' => 'profile' ) );
ck( 'an account the caller found is reported as found, with how the caller said', $report['linked_accounts'], array( array( 'user_id' => 67, 'was_active_mentor' => false, 'how' => 'profile' ) ) );

fresh( array( 'send_welcome_email' => true ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00002' ), $report );
ck( 'an account the step creates is no linked account', array_key_exists( 'linked_accounts', $report ) ? $report['linked_accounts'] : array(), array() );

echo "\n=== What is refused ===\n";
fresh();
$report = array();
WPCPM_Mentor_Provision::provision( array_merge( mentor( 'recNEWMENTOR00001' ), array( 'profile' => 'https://example.test/' ) ), $report );
ck( 'a profile with no WordPress.org username in it is skipped, quoted', array( $report['stats']['skipped'], $report['notices'] ), array( 1, array( 'Skipped Ada Example - no WordPress.org username could be read from "https://example.test/".' ) ) );
$report = array();
WPCPM_Mentor_Provision::provision( array_merge( mentor( 'recNEWMENTOR00001' ), array( 'email' => 'not-an-address' ) ), $report );
ck( 'no valid address is skipped', $report['notices'], array( 'Skipped Ada Example - Airtable has no valid email address, and WordPress cannot create an account without one.' ) );
$GLOBALS['insert_error'] = new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
$report                  = array();
ck( 'WordPress refusing the account is skipped with its words', array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), $report['notices'] ), array( 0, array( 'Could not create the account "ada-example": Sorry, that username already exists!' ) ) );

echo "\n=== A mentor with no record id yet ===\n";
fresh();
account( 46, 'decoy-example', 'decoy@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => '' ) );
account( 47, 'someone-else', 'ada@example.test', array( 'subscriber' ) );
$report = array();
$id     = WPCPM_Mentor_Provision::provision( mentor( '' ), $report );
ck( 'with no record id, the address finds the account, no account is found by an empty record id, and no record id is written',
	array( $id, roles( 46 ), array_key_exists( 'wpcpm_mentor_record_id', $GLOBALS['umeta'][47] ), meta( 47, 'wpcpm_mentor_active' ) ),
	array( 47, array( 'subscriber' ), false, 1 ) );
ck( 'a mentor with no record id is looked up by nothing: find_by_record_id() answers null for it', WPCPM_Mentor_Provision::find_by_record_id( '' ), null );
fresh();
$report = array();
$id     = WPCPM_Mentor_Provision::provision( mentor( '' ), $report );
ck( 'and a new account made with no record id carries none', array( login( $id ), array_key_exists( 'wpcpm_mentor_record_id', $GLOBALS['umeta'][ $id ] ) ), array( 'ada-example', false ) );
fresh();
account( 48, 'ada-example', 'ada@example.test', array(), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001' ) );
$report = array();
ck( 'and an account holding a record id is a conflict for a mentor with none: the activation passes that record id instead', array( WPCPM_Mentor_Provision::provision( mentor( '' ), $report ), $report['stats']['conflicts'] ), array( 0, 1 ) );
account( 49, 'renamed-example', 'renamed@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRENAMED0000001', 'wpcpm_mentor_profile' => 'https://profiles.wordpress.org/zoe-example/' ) );
ck( 'find_account() takes the address, then the username, then the stored profile, and says which',
	array(
		WPCPM_Mentor_Provision::find_account( 'ada@example.test', 'nobody' )['how'],
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'ada-example' )['how'],
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody', 'zoe-example' )['user']->ID,
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody', 'zoe-example' )['how'],
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody' ),
	),
	array( 'email', 'login', 49, 'profile', array( 'user' => null, 'how' => '' ) ) );
ck( 'and a username typed in capitals finds the stored profile all the same, and a profile nobody holds finds nobody',
	array( WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody', 'Zoe-Example' )['how'], WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody', 'yan-example' )['how'] ),
	array( 'profile', '' ) );

echo "\n=== One way to make a mentor ===\n";
$sync = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' );
ck( 'the mentors sync makes no account of its own any more: it hands each mentor to the shared step',
	array( substr_count( $sync, 'WPCPM_Mentor_Provision::provision(' ), substr_count( $sync, 'insert_user(' ), substr_count( $sync, 'function link_existing_user' ), substr_count( $sync, 'function create_mentor_user' ), substr_count( $sync, 'function find_by_record_id' ) ),
	array( 1, 0, 0, 0, 0 ) );
ck( 'and nothing was asked of the stand-ins that they do not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
