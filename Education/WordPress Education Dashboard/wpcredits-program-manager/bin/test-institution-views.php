<?php
/**
 * The Institution Dashboard's views and their addresses: the router the Sponsor Dashboard has.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The Overview is the bare page, every other view names itself in `wpcpm_view`, and an
 *   Administrator's link keeps the school in `wpcpm_institution_view`, so moving between
 *   views never drops the school chosen. An unknown view is the Overview.
 * - Every card the views draw is on one view, and `card_url()` ends at the card's anchor, so a
 *   mail, a guide link or a way back after a press lands on the view that holds the card.
 * - **An old address opens the view that holds its card** (Review Focus 1): a student's link,
 *   a roster filter, an enrollment batch and a semester report each infer their view when the
 *   address names none; an explicit view wins, and an unknown one is the Overview with no
 *   inference.
 * - Only an Administrator's address names a school, and only a well-formed record the index
 *   holds; a member's page is always their own school's.
 *
 * Run: php bin/test-institution-views.php
 *
 * @package WPCreditsProgramManager
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']   = array();
$GLOBALS['manage'] = false;

function __( $s, $d = null ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_post_status( $id ) { return 'publish'; }
function get_permalink( $id ) { return 'https://example.test/institution-dashboard/'; }
function current_user_can( $c ) { return WPCPM_Roles::CAP_MANAGE === $c && (bool) $GLOBALS['manage']; }

/**
 * `add_query_arg()` as WordPress builds it: one key and value or an array of them, set on the
 * URL's query (a key already there is replaced), the fragment kept at the end. Like WordPress it
 * re-encodes the query already in the URL (`urlencode_deep()`) and puts the new keys and values in
 * exactly as given, never encoded: a caller that does not encode them gets a broken address.
 */
function add_query_arg( ...$a ) {
	if ( is_array( $a[0] ) ) {
		$set = $a[0];
		$url = isset( $a[1] ) ? (string) $a[1] : '';
	} else {
		$set = array( $a[0] => $a[1] );
		$url = isset( $a[2] ) ? (string) $a[2] : '';
	}

	$frag = '';
	if ( false !== ( $h = strpos( $url, '#' ) ) ) {
		$frag = substr( $url, $h );
		$url  = substr( $url, 0, $h );
	}

	$query = array();
	if ( false !== ( $q = strpos( $url, '?' ) ) ) {
		parse_str( substr( $url, $q + 1 ), $query );
		$query = array_map( function ( $v ) { return urlencode( (string) $v ); }, $query );
		$url   = substr( $url, 0, $q );
	}

	foreach ( $set as $k => $v ) {
		$query[ $k ] = $v;
	}

	$pairs = array();
	foreach ( $query as $k => $v ) {
		$pairs[] = $k . '=' . $v;
	}

	return $url . ( array() === $pairs ? '' : '?' . implode( '&', $pairs ) ) . $frag;
}

/*
 * The stand-ins `views_for()` asks (Task 3): the account, the policy answering from a table keyed by
 * the viewer's login and the action, the members' stamp, the agreement's settled list, the import
 * switch read by the real `WPCPM_Institution_Import_Form::enabled()`, and the events list. Every card
 * a view draws logs its call to `card_calls`, so a check can say that answering drew none.
 */
class WP_User {
	public $ID;
	public $user_login;
	public function __construct( $id = 0, $login = '' ) {
		$this->ID         = (int) $id;
		$this->user_login = (string) $login;
	}
	public function exists() { return $this->ID > 0; }
}

$GLOBALS['allow']       = array();
$GLOBALS['members']     = array();
$GLOBALS['settled']     = array();
$GLOBALS['import_on']   = true;
$GLOBALS['events_show'] = true;
$GLOBALS['card_calls']  = array();
$GLOBALS['shows_asked'] = array();

class WPCPM_Institution_Policy {
	// 1.123.98: the two acts the Overview's rows that wait ask for.
	const ACT_RESEND_INVITE = 'resend_invite';
	const ACT_EDIT_STUDENT  = 'edit_student';
	const ACT_VIEW_ROSTER          = 'view_roster';
	const ACT_ADD_STUDENT          = 'add_student';
	const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
	// The details card's own question, which the Overview's request row asks too (Task 25, fix round 1, B2).
	const ACT_EDIT_DETAILS         = 'edit_details';
	const ACT_AGREEMENT            = 'agreement';
	public static function subject_institution( $record ) { return array( 'kind' => 'institution', 'institution_ids' => array( (string) $record ) ); }
	public static function decide( $act, array $subject, $user = null ) {
		$login = $user instanceof WP_User ? $user->user_login : '';
		$ok    = ! empty( $GLOBALS['allow'][ $login ][ $act ] );
		return array( 'allowed' => $ok, 'ground' => $ok ? 'member' : '', 'institution' => '', 'fields' => array(), 'why' => $ok ? '' : 'refused' );
	}
}

class WPCPM_Institution_Members {
	const LEVEL_TUTOR          = 'tutor';
	const LEVEL_REPRESENTATIVE = 'representative';
	const META_ACTIVE          = 'wpcpm_institution_active';
	public static function institution_of( $user = null ) { return $user instanceof WP_User && isset( $GLOBALS['members'][ $user->ID ] ) ? $GLOBALS['members'][ $user->ID ][0] : ''; }
	public static function level_of( $user = null ) { return $user instanceof WP_User && isset( $GLOBALS['members'][ $user->ID ] ) && 'tutor' === $GLOBALS['members'][ $user->ID ][1] ? self::LEVEL_TUTOR : self::LEVEL_REPRESENTATIVE; }
}

class WPCPM_Institution_Agreement {
	const SUMMARY_NONE      = 'none';
	const SUMMARY_GENERATED = 'generated';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED  = 'returned';
	const SUMMARY_REVOKED   = 'revoked';
	const SUMMARY_ACCEPTED  = 'accepted';
	const SUMMARY_ON_FILE   = 'on_file';
	public static function is_settled( $record ) { return in_array( (string) $record, $GLOBALS['settled'], true ); }
	// A signed copy waiting for review, which waits for no school's reader (the menu's counts, Task 8).
	public static function summary( $record ) { return array( 'state' => self::SUMMARY_SUBMITTED ); }
}

class WPCPM_Settings {
	// 1.123.98: the tracked statuses the Overview's rows read (`WPCPM_Mentors_Sync::tracked_statuses()`), none here.
	public static function get() { return array(); }
	public static function get_value( $key ) { return 'import_enabled' === $key ? (bool) $GLOBALS['import_on'] : null; }
}

class WPCPM_Sponsor_Event_List {
	const BACK_INSTITUTION = 'institution';
	public static function shows( WP_User $viewer ): bool {
		$GLOBALS['shows_asked'][] = $viewer->user_login;
		return (bool) $GLOBALS['events_show'];
	}
	public static function render( $viewer, $back ) { $GLOBALS['card_calls'][] = 'WPCPM_Sponsor_Event_List'; }
}

// The cards of the views the suite does not load for real, each logging that it was drawn.
class WPCPM_Institution_Panel { public static function render( ...$a ) { $GLOBALS['card_calls'][] = __CLASS__; } }
class WPCPM_Institution_Details_Card { public static function render( ...$a ) { $GLOBALS['card_calls'][] = __CLASS__; } }
class WPCPM_Institution_People { public static function render( ...$a ) { $GLOBALS['card_calls'][] = __CLASS__; } }
class WPCPM_Institution_History { public static function render( ...$a ) { $GLOBALS['card_calls'][] = __CLASS__; return ''; } }

// The real flash over a user-meta table of the suite's own, for `WPCPM_Flash::peek()` (Task 3, fix round 1).
$GLOBALS['umeta'] = array();
$GLOBALS['uid']   = 0;
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) {
	$unslash = function ( $v ) use ( &$unslash ) { return is_array( $v ) ? array_map( $unslash, $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); };
	$GLOBALS['umeta'][ (int) $id ][ $key ] = $unslash( $value );
	return true;
}
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ (int) $id ][ $key ] ); return true; }
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';
// 1.123.98: what the Overview's new rows ask, where this suite stands in for none of it.
require_once __DIR__ . '/stubs/overview-more.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';

$GLOBALS['failed'] = 0;

function ck( $label, $actual, $expected ) {
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}

	$GLOBALS['failed']++;
	echo "FAIL $label\n     expected: " . var_export( $expected, true ) . "\n     actual:   " . var_export( $actual, true ) . "\n";
}

$page = 'https://example.test/institution-dashboard/';
$rec  = 'recEXA00000000001';

$GLOBALS['opts'][ WPCPM_Institutions_Dashboard::OPT_PAGE ] = 42;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]     = array(
	'v'    => WPCPM_Institutions_Index::VERSION,
	'read' => 1,
	'rows' => array( $rec => array( 'name' => 'Example Institute' ) ),
);

// The constants later tasks route by.
ck( 'the view argument is the dashboards\' own', WPCPM_Institutions_Dashboard::VIEW_ARG, 'wpcpm_view' );
ck( 'the frame names this page', WPCPM_Institutions_Dashboard::FRAME_PAGE, 'institution' );
ck( 'the guide view', WPCPM_Institutions_Dashboard::VIEW_GUIDE, 'guide' );
ck( 'the updates view', WPCPM_Institutions_Dashboard::VIEW_UPDATES, 'updates' );
ck( 'the title is the page\'s', WPCPM_Institutions_Dashboard::title(), 'Institution Dashboard' );

ck( 'the Overview is the bare page', WPCPM_Institutions_Dashboard::view_url(), $page );
ck( 'a view names itself', WPCPM_Institutions_Dashboard::view_url( 'students' ), $page . '?wpcpm_view=students' );
ck( 'an Administrator keeps the school', WPCPM_Institutions_Dashboard::view_url( 'enroll', $rec ), $page . '?wpcpm_view=enroll&wpcpm_institution_view=' . $rec );
ck( 'an unknown view is the Overview', WPCPM_Institutions_Dashboard::view_url( 'nope' ), $page );
ck( 'a malformed school is not carried', WPCPM_Institutions_Dashboard::view_url( 'students', 'not-a-record' ), $page . '?wpcpm_view=students' );
ck( 'an empty arg is not carried', WPCPM_Institutions_Dashboard::view_url( 'students', '', array( 'wpcpm_cohort' => '' ) ), $page . '?wpcpm_view=students' );

$cards = array( 'roster' => 'students', 'student-status' => 'students', 'student-form' => 'students', 'student-invite' => 'students', 'import' => 'enroll', 'report' => 'reports', 'details' => 'institution', 'people' => 'institution', 'tutors' => 'institution', 'agreement' => 'agreement', 'sevents' => 'events', 'for-administrators' => 'administrators', 'admin-answers' => 'administrators', 'admin-history' => 'administrators' );
foreach ( $cards as $card => $view ) {
	ck( "card $card is on view $view", WPCPM_Institutions_Dashboard::view_of( $card ), $view );
	ck( "card_url( $card ) ends at its anchor", substr( WPCPM_Institutions_Dashboard::card_url( $card ), -strlen( "#wpcpm-$card" ) ), "#wpcpm-$card" );
}
ck( 'a card no view lists is on the Overview', WPCPM_Institutions_Dashboard::view_of( 'nothing' ), 'overview' );
ck( 'an item anchor replaces the card one', WPCPM_Institutions_Dashboard::card_url( 'import', '', 'wpcpm-import-file' ), $page . '?wpcpm_view=enroll#wpcpm-import-file' );
ck( 'args travel before the anchor', WPCPM_Institutions_Dashboard::card_url( 'report', $rec, '', array( 'wpcpm_report' => '2026-H1' ) ), $page . '?wpcpm_view=reports&wpcpm_report=2026-H1&wpcpm_institution_view=' . $rec . '#wpcpm-report' );

// Review Focus 1: old addresses.
$old = array(
	array( array( 'wpcpm_institution_student' => '12' ), 'students' ),
	array( array( 'wpcpm_cohort' => '2026-H1' ), 'students' ),
	array( array( 'wpcpm_roster_status' => 'waiting' ), 'students' ),
	array( array( 'wpcpm_roster_search' => 'Example' ), 'students' ),
	array( array( 'wpcpm_batch' => '7' ), 'enroll' ),
	array( array( 'wpcpm_report' => '2026-H1' ), 'reports' ),
	array( array( 'wpcpm_institution_view' => $rec ), 'overview' ),
	array( array(), 'overview' ),
	array( array( 'wpcpm_view' => 'agreement', 'wpcpm_report' => '2026-H1' ), 'agreement' ),
	array( array( 'wpcpm_view' => 'bogus', 'wpcpm_batch' => '7' ), 'overview' ),
);
foreach ( $old as $case ) {
	$_GET = $case[0];
	ck( 'asked view for ' . http_build_query( $case[0] ), WPCPM_Institutions_Dashboard::asked_view(), $case[1] );
}

$GLOBALS['manage'] = false;
$_GET              = array( 'wpcpm_institution_view' => $rec );
ck( 'a member never names a school', WPCPM_Institutions_Dashboard::requested_record(), '' );
$GLOBALS['manage'] = true;
ck( 'an Administrator names the school', WPCPM_Institutions_Dashboard::requested_record(), $rec );
$_GET = array( 'wpcpm_institution_view' => ' ' . $rec . ' ' );
ck( 'the school is trimmed', WPCPM_Institutions_Dashboard::requested_record(), $rec );
$_GET = array( 'wpcpm_institution_view' => 'not-a-record' );
ck( 'a malformed school is none', WPCPM_Institutions_Dashboard::requested_record(), '' );
$_GET = array( 'wpcpm_institution_view' => 'recEXA00000000009' );
ck( 'a school the index does not hold is none', WPCPM_Institutions_Dashboard::requested_record(), '' );
$_GET = array();
ck( 'an address that names no school', WPCPM_Institutions_Dashboard::requested_record(), '' );

ck( 'keep_institution adds the school', WPCPM_Institutions_Dashboard::keep_institution( $page, $rec ), $page . '?wpcpm_institution_view=' . $rec );
ck( 'keep_institution leaves a member link alone', WPCPM_Institutions_Dashboard::keep_institution( $page, '' ), $page );
ck( 'keep_institution leaves no address alone', WPCPM_Institutions_Dashboard::keep_institution( '', $rec ), '' );

/**
 * An address read back as a browser and PHP read it: its query's arguments, decoded, and its fragment.
 */
function parsed( $url ) {
	$query = array();
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

	return array( $query, (string) parse_url( $url, PHP_URL_FRAGMENT ) );
}

// Review fix 1: what an address carries comes back intact, each value one argument.
$carried = array(
	'an ampersand and a space' => 'Smith & Sons Example',
	'a hash'                   => 'Example #2',
	'an injected school'       => 'x&wpcpm_institution_view=recEXA00000000009',
	'a non-ASCII value'        => 'Zażółć Example',
	'a plus'                   => 'C++ Example',
);
foreach ( $carried as $what => $value ) {
	list( $q, $frag ) = parsed( WPCPM_Institutions_Dashboard::card_url( 'roster', $rec, '', array( 'wpcpm_roster_search' => $value ) ) );
	ck( "a search with $what comes back intact", $q, array( 'wpcpm_view' => 'students', 'wpcpm_roster_search' => $value, 'wpcpm_institution_view' => $rec ) );
	ck( "a search with $what keeps the card's anchor", $frag, 'wpcpm-roster' );
}
list( $q ) = parsed( WPCPM_Institutions_Dashboard::view_url( 'students', '', array( 'a&b=c#d' => 'v' ) ) );
ck( 'an argument name is encoded too', $q, array( 'wpcpm_view' => 'students', 'a&b=c#d' => 'v' ) );

// keep_institution: a record ID only, on an address that already carries a value.
$search = WPCPM_Institutions_Dashboard::view_url( 'students', '', array( 'wpcpm_roster_search' => 'Smith & Sons Example' ) );
list( $q ) = parsed( WPCPM_Institutions_Dashboard::keep_institution( $search, $rec ) );
ck( 'keep_institution keeps what the address carries', $q, array( 'wpcpm_view' => 'students', 'wpcpm_roster_search' => 'Smith & Sons Example', 'wpcpm_institution_view' => $rec ) );
ck( 'keep_institution adds no malformed school', WPCPM_Institutions_Dashboard::keep_institution( $page, 'x&wpcpm_view=enroll' ), $page );
ck( 'keep_institution adds the school trimmed', WPCPM_Institutions_Dashboard::keep_institution( $page, ' ' . $rec . ' ' ), $page . '?wpcpm_institution_view=' . $rec );

// Review fix 2: a card's item anchor is an id, nothing else.
ck( 'a hostile item keeps only id characters', WPCPM_Institutions_Dashboard::card_url( 'import', '', '"><script>alert(1)</script>#x y' ), $page . '?wpcpm_view=enroll#scriptalert1scriptxy' );
ck( 'an item of no id characters falls back to the card', WPCPM_Institutions_Dashboard::card_url( 'import', '', '"#<>' ), $page . '?wpcpm_view=enroll#wpcpm-import' );
ck( 'an item with an underscore is kept', WPCPM_Institutions_Dashboard::card_url( 'import', '', 'wpcpm-import_file2' ), $page . '?wpcpm_view=enroll#wpcpm-import_file2' );

// Review minors: an empty explicit view, a non-scalar view, the posted form.
// An empty `wpcpm_view` is still a view asked for by name, so it wins over the batch beside it and,
// naming no view, is the Overview: an explicit argument is never second-guessed by inference.
$_GET = array( 'wpcpm_view' => '', 'wpcpm_batch' => '7' );
ck( 'an empty wpcpm_view beside a batch is the Overview', WPCPM_Institutions_Dashboard::asked_view(), 'overview' );
$notices = array();
set_error_handler(
	function ( $no, $str ) use ( &$notices ) {
		$notices[] = $str;
		return true;
	}
);
$_GET = array( 'wpcpm_view' => array( 'students' ), 'wpcpm_batch' => '7' );
ck( 'an array wpcpm_view is the Overview', WPCPM_Institutions_Dashboard::asked_view(), 'overview' );
restore_error_handler();
ck( 'an array wpcpm_view raises no notice', $notices, array() );

$other = 'recEXA00000000002';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $other ] = array( 'name' => 'Example College' );
$GLOBALS['manage'] = true;
$_GET  = array();
$_POST = array( 'wpcpm_institution_view' => $other );
ck( 'the posted form names the school when the query names none', WPCPM_Institutions_Dashboard::requested_record(), $other );
$_GET = array( 'wpcpm_institution_view' => $rec );
ck( 'the query wins over the posted form', WPCPM_Institutions_Dashboard::requested_record(), $rec );
$_GET = array( 'wpcpm_institution_view' => 'not-a-record' );
ck( 'a malformed query is not overridden by the form', WPCPM_Institutions_Dashboard::requested_record(), '' );
$GLOBALS['manage'] = false;
$_GET              = array();
ck( 'a member\'s posted school is none', WPCPM_Institutions_Dashboard::requested_record(), '' );
$_POST = array();

/*
 * Task 3: which views a reader gets, from the same policy questions the cards ask (Review Focus 3
 * and 4). A representative and a tutor of a settled school, a representative of a locked one, and
 * an Administrator with no school of their own.
 */
function viewer( $login, $record, $level ) {
	static $id = 100;
	$user = new WP_User( ++$id, $login );
	if ( '' !== $record ) {
		$GLOBALS['members'][ $user->ID ] = array( $record, $level );
	}
	return $user;
}

$rec_locked = 'recEXA00000000003';
$GLOBALS['settled'] = array( $rec );
$all = array( 'view_roster' => true, 'add_student' => true, 'view_semester_report' => true, 'agreement' => true );
$GLOBALS['allow'] = array(
	'rep'    => $all,
	'tutor'  => array( 'view_roster' => true, 'view_semester_report' => true ),
	'locked' => array( 'agreement' => true ),
	'admin'  => $all,
);

$rep    = viewer( 'rep', $rec, 'representative' );   // settled school
$tutor  = viewer( 'tutor', $rec, 'tutor' );
$locked = viewer( 'locked', $rec_locked, 'representative' );
$admin  = viewer( 'admin', '', '' );
$GLOBALS['events_show'] = true; $GLOBALS['import_on'] = true;
/** `views_for()` and `choice_views()`, with anything they print kept apart from the checks' own lines. */
$GLOBALS['printed'] = '';
function quiet_views( ...$a ) {
	// Each check asks afresh: `views_for()` is worked out once a request (Task 4, fix round 1).
	WPCPM_Institutions_Dashboard::forget();
	ob_start();
	$views = WPCPM_Institutions_Dashboard::views_for( ...$a );
	$GLOBALS['printed'] .= (string) ob_get_clean();
	return $views;
}
function quiet_choice() {
	ob_start();
	$views = WPCPM_Institutions_Dashboard::choice_views();
	$GLOBALS['printed'] .= (string) ob_get_clean();
	return $views;
}

// Fix round 1, Minor 2: Semester reports carries its card's guard on the report class, which this
// suite declares only after this check (a conditional declaration, so it is not hoisted).
ck( 'no semester report class: no Semester reports view', array( in_array( 'reports', quiet_views( $rec, $rep, false ), true ), in_array( 'reports', quiet_choice(), true ) ), array( false, false ) );
if ( ! class_exists( 'WPCPM_Semester_Report' ) ) {
	// No report approved: what the menu's counts read for a school's reader (Task 8).
	class WPCPM_Semester_Report { public static function reports_of( $record ) { return array(); } }
}
$GLOBALS['shows_asked'] = array();
$GLOBALS['card_calls']  = array();
ck( 'a representative', quiet_views( $rec, $rep, false ), array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events' ) );
// Review Focus 4.
ck( 'a tutor: no Enroll students, no Events', quiet_views( $rec, $tutor, false ), array( 'overview', 'students', 'reports', 'institution', 'agreement' ) );
// Review Focus 3.
ck( 'a locked school: Overview and Agreement', quiet_views( $rec_locked, $locked, false ), array( 'overview', 'agreement' ) );
ck( 'an Administrator viewing a school', quiet_views( $rec, $admin, true ), array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) );
ck( 'an Administrator viewing a locked school', quiet_views( $rec_locked, $admin, true ), array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) );
ck( 'the events list is asked on the reader\'s own settled page alone', $GLOBALS['shows_asked'], array( 'rep' ) );
$GLOBALS['events_show'] = false;
ck( 'no event: no Events view', in_array( 'events', quiet_views( $rec, $rep, false ), true ), false );
$GLOBALS['events_show'] = true;
$GLOBALS['settled']     = array();
ck( 'an agreement not settled: no Events view', in_array( 'events', quiet_views( $rec, $rep, false ), true ), false );
$GLOBALS['settled']     = array( $rec );
ck( 'another school\'s page: no Events view', in_array( 'events', quiet_views( $other, $rep, false ), true ), false );
$GLOBALS['import_on'] = false;
ck( 'enrollment switched off: no Enroll students view', in_array( 'enroll', quiet_views( $rec, $rep, false ), true ), false );
ck( 'enrollment switched off: an Administrator has no Enroll students view either', in_array( 'enroll', quiet_views( $rec, $admin, true ), true ), false );
ck( 'the choice lists, enrollment off', quiet_choice(), array( 'overview', 'students', 'reports', 'institution', 'agreement', 'administrators' ) );
$GLOBALS['import_on'] = true;
ck( 'the choice lists, enrollment on', quiet_choice(), array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) );
ck( 'views_for draws no card', $GLOBALS['card_calls'], array() );
ck( 'views_for and choice_views print nothing', $GLOBALS['printed'], '' );
ck( 'every view a reader gets is a menu view of the page', array_values( array_diff( quiet_views( $rec, $admin, true ), array_merge( array( 'overview' ), array_keys( WPCPM_Institutions_Dashboard::VIEW_CARDS ) ) ) ), array() );

// Fix round 1: a read-only peek at a flash. Each case its own user, since the memo lasts the request.
/** What is stored for one user's channel, read from the suite's table and not through the flash. */
function stored( $uid, $channel ) {
	$pending = get_user_meta( $uid, WPCPM_Flash::META, true );
	return is_array( $pending ) && array_key_exists( $channel, $pending ) ? $pending[ $channel ] : null;
}
WPCPM_Flash::set( 'sevent-list', 'full', 201 );
ck( 'peek() returns the pending message', is_callable( array( 'WPCPM_Flash', 'peek' ) ) ? WPCPM_Flash::peek( 'sevent-list', 201 ) : 'no peek', 'full' );
ck( 'peek() leaves it stored', stored( 201, 'sevent-list' ), 'full' );
ck( 'take() after peek() returns it', WPCPM_Flash::take( 'sevent-list', 201 ), 'full' );
ck( 'take() after peek() removes it', stored( 201, 'sevent-list' ), null );
WPCPM_Flash::set( 'sevent-list', 'gone', 202 );
WPCPM_Flash::set( 'other', 'kept', 202 );
WPCPM_Flash::take( 'sevent-list', 202 );
ck( 'peek() after take() in the same request returns what was taken', is_callable( array( 'WPCPM_Flash', 'peek' ) ) ? WPCPM_Flash::peek( 'sevent-list', 202 ) : 'no peek', 'gone' );
ck( 'peek() of another channel leaves it stored', array( is_callable( array( 'WPCPM_Flash', 'peek' ) ) ? WPCPM_Flash::peek( 'other', 202 ) : 'no peek', stored( 202, 'other' ) ), array( 'kept', 'kept' ) );
ck( 'peek() with nothing pending returns what take() returns', is_callable( array( 'WPCPM_Flash', 'peek' ) ) ? array( WPCPM_Flash::peek( 'sevent-list', 203 ), WPCPM_Flash::take( 'sevent-list', 203 ) ) : 'no peek', array( '', '' ) );
ck( 'peek() with nobody signed in returns the empty value', is_callable( array( 'WPCPM_Flash', 'peek' ) ) ? WPCPM_Flash::peek( 'sevent-list' ) : 'no peek', '' );
$GLOBALS['uid'] = 204;
WPCPM_Flash::set( 'sevent-list', 'left' );
ck( 'peek() reads the signed-in user by default', is_callable( array( 'WPCPM_Flash', 'peek' ) ) ? WPCPM_Flash::peek( 'sevent-list' ) : 'no peek', 'left' );
$GLOBALS['uid'] = 0;

// Fix round 1, Minor 1: Events is asked as `render()` asks it, `! $locked`: a member who also manages
// the program reads the list on an unsettled school's own page.
$both = viewer( 'both', $rec_locked, 'representative' );
$GLOBALS['allow']['both'] = $all;
$GLOBALS['events_show']   = true;
ck( 'a member who manages the program, on an unsettled school of their own: Events', in_array( 'events', quiet_views( $rec_locked, $both, true ), true ), true );
ck( 'the same reader without the capability: no Events', in_array( 'events', quiet_views( $rec_locked, $both, false ), true ), false );

/*
 * Task 5: the view a reader is drawn (the planner's ruling P5): the one the address asks for, an old address
 * inferred, while it is one of the reader's views, and the Overview otherwise. Answering draws no card.
 */
function quiet_view( array $get, ...$a ) {
	WPCPM_Institutions_Dashboard::forget();
	$_GET = $get;
	ob_start();
	$view = is_callable( array( 'WPCPM_Institutions_Dashboard', 'view' ) ) ? WPCPM_Institutions_Dashboard::view( ...$a ) : 'no view()';
	$GLOBALS['printed'] .= (string) ob_get_clean();
	$_GET = array();
	return $view;
}
$GLOBALS['card_calls'] = array();
$GLOBALS['printed']    = '';
$GLOBALS['settled']    = array( $rec );
ck( 'a representative asking for Students is drawn Students', quiet_view( array( 'wpcpm_view' => 'students' ), $rec, $rep, false ), 'students' );
ck( 'an old student link is drawn Students', quiet_view( array( 'wpcpm_institution_student' => '12' ), $rec, $rep, false ), 'students' );
ck( 'an old report link is drawn Semester reports', quiet_view( array( 'wpcpm_report' => '2026-H1' ), $rec, $rep, false ), 'reports' );
ck( 'the bare page is the Overview', quiet_view( array(), $rec, $rep, false ), 'overview' );
ck( 'a representative asking for For Administrators is drawn the Overview', quiet_view( array( 'wpcpm_view' => 'administrators' ), $rec, $rep, false ), 'overview' );
// Review Focus 3.
foreach ( array( array( 'wpcpm_view' => 'students' ), array( 'wpcpm_view' => 'enroll' ), array( 'wpcpm_institution_student' => '12' ), array( 'wpcpm_report' => '2026-H1' ), array( 'wpcpm_batch' => '7' ) ) as $q ) {
	ck( 'a locked school asking ' . http_build_query( $q ) . ' is drawn the Overview', quiet_view( $q, $rec_locked, $locked, false ), 'overview' );
}
ck( 'a locked school asking for the Agreement is drawn it', quiet_view( array( 'wpcpm_view' => 'agreement' ), $rec_locked, $locked, false ), 'agreement' );
// Review Focus 4.
ck( 'a tutor asking for Enroll students or Events is drawn the Overview', array( quiet_view( array( 'wpcpm_view' => 'enroll' ), $rec, $tutor, false ), quiet_view( array( 'wpcpm_view' => 'events' ), $rec, $tutor, false ), quiet_view( array( 'wpcpm_batch' => '7' ), $rec, $tutor, false ) ), array( 'overview', 'overview', 'overview' ) );
ck( 'a tutor asking for Semester reports is drawn it', quiet_view( array( 'wpcpm_view' => 'reports' ), $rec, $tutor, false ), 'reports' );
ck( 'an Administrator asking for Events is drawn the Overview, and For Administrators is theirs', array( quiet_view( array( 'wpcpm_view' => 'events' ), $rec, $admin, true ), quiet_view( array( 'wpcpm_view' => 'administrators' ), $rec, $admin, true ) ), array( 'overview', 'administrators' ) );
ck( 'answering draws no card and prints nothing', array( $GLOBALS['card_calls'], $GLOBALS['printed'] ), array( array(), '' ) );

/*
 * Task 8: the menu the Institution guide and the Program updates are drawn with, `menu_args()`. The reader is the one
 * signed in; a school's reader gets their own school's views, an Administrator the school the address names, and an
 * Administrator who names none the views a school may have, without counts. The counts themselves, and the menu being
 * the dashboard's own for the same reader, are checked in `bin/test-institutions-dashboard.php`, which draws the page.
 */
$GLOBALS['current'] = null;
function wp_get_current_user() { return $GLOBALS['current'] instanceof WP_User ? $GLOBALS['current'] : new WP_User( 0 ); }
function user_can( $user, $cap ) { return WPCPM_Roles::CAP_MANAGE === $cap && (bool) $GLOBALS['manage']; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function apply_filters( $tag, $value ) { return $value; }
function get_page_by_path( $path, $output = null, $type = 'page' ) { return null; }
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
// Nothing waits at the school: no checked list, no request decided, nobody assigned.
class WPCPM_Institution_Import { const STATE_CREATING = 'creating'; public static function active_for( $record ) { return 0; } public static function batch( $id ) { return null; } }
class WPCPM_Institution_Request {
	const STATE_DONE     = 'done';
	const STATE_DECLINED = 'declined';
	public static function latest_details_outcome( $record ) { return array(); }
	public static function open_details_for( $record ) { return 0; }
}
class WPCPM_Institution_Assignment { public static function contact_of( $record ) { return null; } }
// The switcher's labels, which the choice counts: no test school in this suite's index.
class WPCPM_Test_Institutions { public static function label( $name, $judged ) { return (string) $name; } }
class WPCPM_Handbook_Assistant {
	const INSTITUTION_GUIDE_PATH = 'institution-guide';
	public static function guides() { return array( 'institution' => array( 'url' => 'https://example.org/institution-guide/', 'slack' => 'https://example.org/slack' ) ); }
}
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-frame.php';

/** `menu_args( $view )` as one reader, with one address's arguments; anything printed kept apart. */
function menu_of( $view, $user, $manage, array $get = array() ) {
	WPCPM_Institutions_Dashboard::forget();
	$GLOBALS['current'] = $user;
	$GLOBALS['manage']  = $manage;
	$_GET               = $get;
	ob_start();
	$args = is_callable( array( 'WPCPM_Institutions_Dashboard', 'menu_args' ) ) ? WPCPM_Institutions_Dashboard::menu_args( $view ) : array( 'page' => 'no menu_args()', 'groups' => array( array( 'items' => array() ) ), 'help' => array() );
	$GLOBALS['printed'] .= (string) ob_get_clean();
	$_GET               = array();
	$GLOBALS['manage']  = false;
	return $args;
}
/** The views' items: each label, badge and whether it is current. */
function menu_rows( array $args ) {
	return array_map( function ( $i ) { return array( $i['label'], $i['badge'], $i['current'] ); }, $args['groups'][0]['items'] );
}
/** The Help items that are current, by label. */
function help_current( array $args ) {
	return array_column( array_filter( $args['help'], function ( $i ) { return ! empty( $i['current'] ); } ), 'label' );
}
$labels = array( 'overview' => 'Overview', 'students' => 'Students', 'enroll' => 'Enroll students', 'reports' => 'Semester reports', 'institution' => 'Your institution', 'agreement' => 'Agreement', 'events' => 'Events', 'administrators' => 'For Administrators' );
/** What `menu_rows()` gives for these views, none counted and none current. */
function rows_for( array $views ) {
	global $labels;
	return array_map( function ( $v ) use ( $labels ) { return array( $labels[ $v ], '', false ); }, $views );
}
$GLOBALS['settled']     = array( $rec );
$GLOBALS['import_on']   = true;
$GLOBALS['events_show'] = true;
$GLOBALS['card_calls']  = array();
$GLOBALS['printed']     = '';

$guide = menu_of( 'guide', $rep, false );
ck( 'menu_args( guide ): the page is institution-guide', $guide['page'], 'institution-guide' );
ck( 'a representative\'s guide menu: their views, nothing waiting, no view current', menu_rows( $guide ), rows_for( array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events' ) ) );
ck( 'and the Institution guide is the current item', help_current( $guide ), array( 'Institution guide' ) );
ck( 'a representative\'s links name no school', count( array_filter( array_column( $guide['groups'][0]['items'], 'url' ), function ( $u ) { return false !== strpos( $u, 'wpcpm_institution_view' ); } ) ), 0 );
ck( 'a member whose address names another school gets their own school\'s menu', menu_rows( menu_of( 'guide', $rep, false, array( 'wpcpm_institution_view' => $other ) ) ), menu_rows( $guide ) );
ck( 'a tutor\'s guide menu is a tutor\'s views', menu_rows( menu_of( 'guide', $tutor, false ) ), rows_for( array( 'overview', 'students', 'reports', 'institution', 'agreement' ) ) );
ck( 'a locked school\'s guide menu is the Overview and the Agreement', menu_rows( menu_of( 'guide', $locked, false ) ), rows_for( array( 'overview', 'agreement' ) ) );

$named = menu_of( 'guide', $admin, true, array( 'wpcpm_institution_view' => $rec ) );
$urls  = array_column( $named['groups'][0]['items'], 'url' );
ck( 'an Administrator whose address names the school: its views', menu_rows( $named ), rows_for( array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) ) );
ck( 'and every item keeps the school', array( count( $urls ) > 0, count( array_filter( $urls, function ( $u ) use ( $rec ) { return false !== strpos( $u, 'wpcpm_institution_view=' . $rec ); } ) ) === count( $urls ) ), array( true, true ) );

$none = menu_of( 'guide', $admin, true );
ck( 'an Administrator who names no school: the choice\'s views, with no badge', menu_rows( $none ), rows_for( quiet_choice() ) );
ck( 'and no item names a school', count( array_filter( array_column( $none['groups'][0]['items'], 'url' ), function ( $u ) { return false !== strpos( $u, 'wpcpm_institution_view' ); } ) ), 0 );
ck( 'an Administrator naming a school the index does not hold: the choice too', menu_rows( menu_of( 'guide', $admin, true, array( 'wpcpm_institution_view' => 'recEXA00000000009' ) ) ), rows_for( quiet_choice() ) );

$updates = menu_of( 'updates', $rep, false );
ck( 'menu_args( updates ): the page is institution-updates', $updates['page'], 'institution-updates' );
ck( 'and no guide item is current there', help_current( $updates ), array() );
ck( 'menu_args( bogus ): the page is institution-guide', menu_of( 'bogus', $rep, false )['page'], 'institution-guide' );
// Fix round 1: a reader the policy gives no claim on their school, a tutor of a school whose agreement is not
// settled (the real policy refuses them both questions, as the stand-in's empty row does), is drawn "nothing to show"
// by the page, as a reader with no school is; so their guide's menu is a reader's with no school, item for item.
$GLOBALS['allow']['ututor'] = array();
$ututor = viewer( 'ututor', $rec_locked, 'tutor' );
$nobody = viewer( 'nobody', '', '' );
foreach ( array( 'guide', 'updates' ) as $v ) {
	$refused = menu_of( $v, $ututor, false );
	$no_one  = menu_of( $v, $nobody, false );
	ck( "a tutor of a school not settled: menu_args( $v ) is a reader's with no school", array( $refused['groups'], $refused['help'] ), array( $no_one['groups'], $no_one['help'] ) );
	ck( "and no item of menu_args( $v ) names the school", count( array_filter( array_merge( array_column( $refused['groups'][0]['items'], 'url' ), array_column( $refused['help'], 'url' ) ), function ( $u ) use ( $rec_locked ) { return false !== strpos( $u, $rec_locked ); } ) ), 0 );
}
// Fix round 2: an Administrator the page does not ask to choose, and for whom no school resolves (no school in the
// index), is drawn the page's "nothing to show"; so their menu is a reader's with no school, not the choice's views.
function get_users( $args = array() ) { return array(); }
$kept_rows = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'];
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = array();
$empty_site = menu_of( 'guide', $admin, true );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $kept_rows;
$no_one = menu_of( 'guide', $nobody, false );
ck( 'an Administrator on a site with no school: menu_args( guide ) is a reader\'s with no school', array( $empty_site['groups'], $empty_site['help'] ), array( $no_one['groups'], $no_one['help'] ) );
ck( 'menu_args draws no card, prints nothing and stores nothing', array( $GLOBALS['card_calls'], $GLOBALS['printed'], $GLOBALS['umeta'][ $rep->ID ] ?? array() ), array( array(), '', array() ) );
$GLOBALS['current'] = null;

// No address: every link is ''.
$GLOBALS['opts'][ WPCPM_Institutions_Dashboard::OPT_PAGE ] = 0;
ck( 'no page, no view address', WPCPM_Institutions_Dashboard::view_url( 'students' ), '' );
ck( 'no page, no card address', WPCPM_Institutions_Dashboard::card_url( 'import' ), '' );

echo "\n" . ( $GLOBALS['failed'] ? $GLOBALS['failed'] . ' failed' : 'all passed' ) . "\n";
exit( $GLOBALS['failed'] ? 1 : 0 );
