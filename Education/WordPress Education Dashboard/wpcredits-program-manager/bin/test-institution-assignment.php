<?php
/**
 * The assigned Administrator and the assignment email (Institutions off Airtable, R1).
 *
 * What is pinned here and why:
 *
 * - **The assignment is kept on the institution's record**, as a user ID, and read back as "nobody" once
 *   that account loses the manage capability (`assignee_of()`), while `stored_of()` still says who it was.
 * - **A change is one audit row and one email.** The same call again writes nothing and sends nothing.
 * - **Nobody is mailed about their own assignment**, and `notify` false (the import) sends nothing.
 * - **Only an Administrator can be assigned.**
 *
 * Run from the plugin root:  php bin/test-institution-assignment.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['managers'] = array( 11, 12, 7 );
$GLOBALS['names']    = array( 7 => 'Gus Admin', 11 => 'Ana Admin', 12 => 'Bo Boss', 13 => 'Cy Client' );
$GLOBALS['records']  = array();
$GLOBALS['audit']    = array();
$GLOBALS['sent']     = array();
$GLOBALS['fail_set'] = false;

function __( $s, $d = null ) { return $s; }
function user_can( $id, $cap ) { return 'wpcpm_manage_program' === $cap && in_array( (int) $id, $GLOBALS['managers'], true ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . rawurlencode( $v ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }

class WP_Error {
	public $code, $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
}
class WP_User {
	public $ID, $display_name, $user_email, $user_login;
	public function __construct( $id ) { $this->ID = $id; $this->display_name = $GLOBALS['names'][ $id ]; $this->user_email = 'u' . $id . '@example.test'; $this->user_login = 'u' . $id; }
	public function exists() { return true; }
}
function get_userdata( $id ) { return isset( $GLOBALS['names'][ (int) $id ] ) ? new WP_User( (int) $id ) : false; }
function get_user_by( $field, $id ) { return get_userdata( $id ); }

class WPCPM_Roles { const CAP_MANAGE = 'wpcpm_manage_program'; }
class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Manager_Booking {
	public static function url_for( $id ) { return 11 === (int) $id ? 'https://calendly.com/ana' : ''; }
}
class WPCPM_Institution_Application { const META_ASSIGNED = '_wpcpm_app_assigned'; const META_MANAGER = '_wpcpm_app_manager'; }
/** A distinct anchor, so a hard-coded one cannot pass. */
class WPCPM_Institution_Details_Card { const ANCHOR = 'the-details-card'; }
class WPCPM_Institution_Roster { const ARG_VIEW = 'wpcpm_institution_view'; }
class WPCPM_Institutions_Dashboard { public static function page_url() { return 'https://example.test/institution-dashboard/'; } }
class WPCPM_Institutions_Index {
	public static function row( $id ) {
		if ( 'recCCCCCCCCCCCCC3' === $id ) { return array( 'name' => 'Sin Pais' ); }
		return 'recAAAAAAAAAAAAA1' === $id ? array( 'name' => 'Universidad Example', 'country_name' => 'Costa Rica' ) : null;
	}
}
class WPCPM_Settings_Choices {
	public static function manager_accounts() { $o = array(); foreach ( $GLOBALS['managers'] as $id ) { $o[ $id ] = $GLOBALS['names'][ $id ]; } asort( $o ); return $o; }
}
class WPCPM_Institution_Record {
	public static function get( $id ) { return $GLOBALS['records'][ $id ] ?? array(); }
	public static function set( $id, array $answers ) {
		if ( $GLOBALS['fail_set'] ) { return false; }
		$held = self::get( $id );
		$kept = array_merge( $held, $answers );
		if ( $kept === $held ) { return false; }
		$GLOBALS['records'][ $id ] = $kept;
		return true;
	}
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';
	public static function record( array $entry ) { $GLOBALS['audit'][] = $entry; return 1; }
}
class WPCPM_Mail {
	public static function site_name() { return 'Site'; }
	public static function send( $recipient, $context, $build ) {
		$user = is_object( $recipient ) ? $recipient : get_userdata( $recipient );
		if ( ! empty( $GLOBALS['mail_fails'] ) ) {
			return false;
		}
		$GLOBALS['sent'][] = array( 'to' => $user->ID, 'context' => $context, 'mail' => call_user_func( $build, $user ) );
		return true;
	}
}
class WPCPM_Mail_Templates {
	public static function mail( $id, array $values ) { return array( 'id' => $id, 'values' => $values ); }
}

$failures = 0;
$checks   = 0;
function check( $label, $ok ) {
	global $failures, $checks;
	++$checks;
	if ( ! $ok ) {
		++$failures;
		echo "FAIL: {$label}\n";
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';

const A = 'recAAAAAAAAAAAAA1';
const B = 'recBBBBBBBBBBBBB2';

check( 'the names', 'assigned' === WPCPM_Institution_Assignment::RECORD_KEY && 'assignment_changed' === WPCPM_Institution_Assignment::LOG_CHANGED && 'institution-assigned' === WPCPM_Institution_Assignment::MAIL_ASSIGNED );
check( 'nobody at first', 0 === WPCPM_Institution_Assignment::stored_of( A ) && 0 === WPCPM_Institution_Assignment::assignee_of( A ) && null === WPCPM_Institution_Assignment::contact_of( A ) );
check( 'no label for nobody', '' === WPCPM_Institution_Assignment::label( 0 ) );

// The first assignment.
check( 'assign changes', true === WPCPM_Institution_Assignment::assign( A, 11, 7 ) );
check( 'stored', 11 === WPCPM_Institution_Assignment::stored_of( A ) && 11 === WPCPM_Institution_Assignment::assignee_of( A ) );
check( 'contact', array( 'user_id' => 11, 'name' => 'Ana Admin', 'email' => 'u11@example.test', 'calendly' => 'https://calendly.com/ana' ) === WPCPM_Institution_Assignment::contact_of( A ) );
check( 'one row', 1 === count( $GLOBALS['audit'] ) );
$row = $GLOBALS['audit'][0];
check( 'row kind, institution, subject, actor, ground', array( 'assignment_changed', A, A, 7, 'manager' ) === array( $row['kind'], $row['institution'], (string) $row['subject'], $row['actor'], $row['ground'] ) );
check( 'row data', array( 'from' => 0, 'to' => 11 ) === $row['data'] );
check( 'row message', 'Ana Admin is now the assigned Administrator.' === $row['message'] );
check( 'one email, to 11, with the page link', 1 === count( $GLOBALS['sent'] ) && 11 === $GLOBALS['sent'][0]['to'] && 'institution-assigned' === $GLOBALS['sent'][0]['context'] );
$values = $GLOBALS['sent'][0]['mail']['values'];
check( 'its values', array( 'Site', 'Universidad Example', ', in Costa Rica,', WPCPM_Institution_Assignment::page_url( A ) ) === array( $values['site_name'], $values['institution_name'], $values['country_phrase'], $values['institution_link'] ) );
check( 'the page link, at the details card\'s own anchor', 'https://example.test/institution-dashboard/?wpcpm_institution_view=' . A . '#the-details-card' === WPCPM_Institution_Assignment::page_url( A ) );

// The same call again.
check( 'again: false', false === WPCPM_Institution_Assignment::assign( A, 11, 7 ) );
check( 'again: no second row, no second email', 1 === count( $GLOBALS['audit'] ) && 1 === count( $GLOBALS['sent'] ) );

// To oneself, and without notice.
check( 'to oneself', true === WPCPM_Institution_Assignment::assign( B, 11, 11 ) );
check( 'to oneself sends nothing', 1 === count( $GLOBALS['sent'] ) );
check( 'notify false changes and sends nothing', true === WPCPM_Institution_Assignment::assign( A, 12, 7, false ) && 1 === count( $GLOBALS['sent'] ) );
check( 'a system row', true === WPCPM_Institution_Assignment::assign( B, 12, 0, false ) && 'system' === end( $GLOBALS['audit'] )['ground'] && 0 === end( $GLOBALS['audit'] )['actor'] );
check( 'the system row says from and to', array( 'from' => 11, 'to' => 12 ) === end( $GLOBALS['audit'] )['data'] );

// To nobody.
check( 'to nobody changes', true === WPCPM_Institution_Assignment::assign( A, 0, 7 ) && 0 === WPCPM_Institution_Assignment::stored_of( A ) );
check( 'to nobody: message, no email', 'Nobody is assigned now.' === end( $GLOBALS['audit'] )['message'] && 1 === count( $GLOBALS['sent'] ) );

// Nobody chosen is a choice (1.123.84): the sync, which assigns a school new to the index, reads it so.
check( 'has_choice: a record assigned to Nobody holds a choice, one never assigned holds none', true === WPCPM_Institution_Assignment::has_choice( A ) && false === WPCPM_Institution_Assignment::has_choice( 'recDDDDDDDDDDDDD4' ) );

// Nobody saved on a school that has no assignment key records the choice (Codex finding 2).
$GLOBALS['records']['recNNNNNNNNNNNNN9'] = array();
check( 'Nobody saved on a school with no key is a change, not "the same"', true === WPCPM_Institution_Assignment::assign( 'recNNNNNNNNNNNNN9', 0, 7 ) );
check( 'and the school now holds a choice, stored as 0', true === WPCPM_Institution_Assignment::has_choice( 'recNNNNNNNNNNNNN9' ) && 0 === WPCPM_Institution_Assignment::stored_of( 'recNNNNNNNNNNNNN9' ) );
check( 'saving Nobody again changes nothing', false === WPCPM_Institution_Assignment::assign( 'recNNNNNNNNNNNNN9', 0, 7 ) );

// Whether the email went, for the caller that says so (1.123.84).
$emailed = null;
WPCPM_Institution_Assignment::assign( 'recDDDDDDDDDDDDD4', 12, 7, true, $emailed );
check( 'emailed: true when the email was handed off', true === $emailed && true === WPCPM_Institution_Assignment::has_choice( 'recDDDDDDDDDDDDD4' ) );
$GLOBALS['mail_fails'] = true;
$emailed               = null;
$changed               = WPCPM_Institution_Assignment::assign( 'recDDDDDDDDDDDDD4', 11, 7, true, $emailed );
$GLOBALS['mail_fails'] = false;
check( 'emailed: false when the mail host would not take it, though the change stands', true === $changed && false === $emailed && 11 === WPCPM_Institution_Assignment::stored_of( 'recDDDDDDDDDDDDD4' ) );
$emailed = null;
WPCPM_Institution_Assignment::assign( 'recDDDDDDDDDDDDD4', 12, 7, false, $emailed );
check( 'emailed: false when none was asked for', false === $emailed );

// Choices.
check( 'choices', array( 11 => 'Ana Admin', 12 => 'Bo Boss', 7 => 'Gus Admin' ) === WPCPM_Institution_Assignment::choices() );

// The capability gone.
WPCPM_Institution_Assignment::assign( A, 11, 7, false );
$GLOBALS['managers'] = array( 12, 7 );
check( 'assignee_of is 0, stored_of keeps 11', 0 === WPCPM_Institution_Assignment::assignee_of( A ) && 11 === WPCPM_Institution_Assignment::stored_of( A ) && null === WPCPM_Institution_Assignment::contact_of( A ) );
check( 'label says so', 'Ana Admin (not an Administrator now)' === WPCPM_Institution_Assignment::label( 11 ) );
check( 'label of an Administrator', 'Bo Boss' === WPCPM_Institution_Assignment::label( 12 ) );
$before = count( $GLOBALS['audit'] );
$refused = WPCPM_Institution_Assignment::assign( A, 13, 7 );
check( 'a user without the capability is refused', is_wp_error( $refused ) && 'wpcpm_assign_not_admin' === $refused->get_error_code() && 11 === WPCPM_Institution_Assignment::stored_of( A ) && $before === count( $GLOBALS['audit'] ) );
check( 'so is one that does not exist', is_wp_error( WPCPM_Institution_Assignment::assign( A, 99, 7 ) ) );
check( 'a bad record is refused', is_wp_error( WPCPM_Institution_Assignment::assign( 'nope', 12, 7 ) ) );

// A record that could not be kept.
$GLOBALS['fail_set'] = true;
$sent_before = count( $GLOBALS['sent'] );
$kept        = WPCPM_Institution_Assignment::assign( A, 12, 7 );
check( 'a record that could not be kept is an error, with no row and no email', is_wp_error( $kept ) && $before === count( $GLOBALS['audit'] ) && $sent_before === count( $GLOBALS['sent'] ) );
$GLOBALS['fail_set'] = false;

// A stored value that is not a number is nobody.
$GLOBALS['records'][ B ] = array( 'assigned' => 'x' );
check( 'a malformed stored value is nobody', 0 === WPCPM_Institution_Assignment::stored_of( B ) );

// notify() on its own.
$sent_before = count( $GLOBALS['sent'] );
check( 'notify sends the email', true === WPCPM_Institution_Assignment::notify( 12, 'School', 'Peru', 'https://example.test/x' ) && $sent_before + 1 === count( $GLOBALS['sent'] ) );
check( 'notify to nobody is false', false === WPCPM_Institution_Assignment::notify( 0, 'School', 'Peru', 'https://example.test/x' ) );

// An institution with no country: no empty place in the sentence.
$sent_before = count( $GLOBALS['sent'] );
check( 'assign without a country', true === WPCPM_Institution_Assignment::assign( 'recCCCCCCCCCCCCC3', 12, 7 ) && $sent_before + 1 === count( $GLOBALS['sent'] ) );
check( 'the country phrase is empty', '' === end( $GLOBALS['sent'] )['mail']['values']['country_phrase'] );
check( 'notify with a blank country is empty too', true === WPCPM_Institution_Assignment::notify( 12, 'School', '  ', 'https://example.test/x' ) && '' === end( $GLOBALS['sent'] )['mail']['values']['country_phrase'] );

// An application's assignee.
$GLOBALS['post_meta'] = array( 500 => array( '_wpcpm_app_assigned' => '11' ) );
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['post_meta'][ $id ][ $key ] ?? ''; }
check( 'for_application reads the meta', 11 === WPCPM_Institution_Assignment::for_application( 500 ) && 0 === WPCPM_Institution_Assignment::for_application( 501 ) );

// An application's contact by name, for the queue and the Administrator Dashboard: the Administrator it was given to,
// and for an application filed before the areas existed (or while none were set) the contact snapshotted then.
$GLOBALS['post_meta'] = array(
	502 => array( '_wpcpm_app_assigned' => '12', '_wpcpm_app_manager' => array( 'manager' => 'Old Name', 'email' => 'old@example.test' ) ),
	503 => array( '_wpcpm_app_assigned' => '11' ),
	504 => array( '_wpcpm_app_manager' => array( 'manager' => 'Old Name', 'email' => 'old@example.test' ) ),
	505 => array( '_wpcpm_app_assigned' => '0', '_wpcpm_app_manager' => array( 'manager' => 'recTEAMAAAAAAAAAA', 'email' => 'old@example.test' ) ),
	506 => array( '_wpcpm_app_manager' => array() ),
);
check( 'application_label: the Administrator it was given to, before any snapshot', 'Bo Boss' === WPCPM_Institution_Assignment::application_label( 502 ) );
check( 'application_label: says so for one who is no Administrator now', 'Ana Admin (not an Administrator now)' === WPCPM_Institution_Assignment::application_label( 503 ) );
check( 'application_label: an older application with no assignee names the snapshot\'s contact', 'Old Name' === WPCPM_Institution_Assignment::application_label( 504 ) );
check( 'application_label: a snapshot holding a record ID in the name is read by its address', 'old@example.test' === WPCPM_Institution_Assignment::application_label( 505 ) );
check( 'application_label: an empty snapshot and nobody is empty', '' === WPCPM_Institution_Assignment::application_label( 506 ) && '' === WPCPM_Institution_Assignment::application_label( 507 ) );

echo "\n{$checks} checks, {$failures} failures\n";
exit( $failures ? 1 : 0 );
