<?php
/**
 * Every way back to the Institution Dashboard, and every link to one of its cards, opens the view that holds the
 * card (Task 9 of the dashboard frame, design 9).
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **A handler's way back lands on the view of the card that prints its message**, at the card's anchor: since the
 *   page draws one view at a time, an address with only the anchor is the Overview, where no card prints the
 *   message, and the reader does not see what their press did. Each row drives the handler's real exit, through the
 *   real class loaded with its `exit` turned into an exception, for an Administrator viewing a school (who keeps the
 *   school in the address) and for a member (whose address names none).
 * - **Each message is taken on that view**: the card that takes the channel is drawn on the view the way back opens,
 *   read from the real sources (the class that takes the channel, the card that draws that class, the view that
 *   draws the card). `bin/test-institutions-dashboard.php` draws the views: the Overview leaves every channel, the
 *   view prints it once.
 * - A referer is kept whole where a handler returned to it before; only the fallback changes.
 * - The wp-admin ways back (Manage members), the other three event-list pages and the mails that link the bare page
 *   are as before; the assignment mail opens the details card.
 * - On the dashboard, where the menu leads back, a card's own back link is not printed; anywhere else it is.
 *
 * Run from the plugin root: php bin/test-institution-ways-back.php
 *
 * @package WPCreditsProgramManager
 */

// phpcs:disable

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array( 'wpcpm_institution_page_id' => 42 );
$GLOBALS['umeta']    = array();
$GLOBALS['manage']   = false;
$GLOBALS['uid']      = 20;
$GLOBALS['referer']  = false;
$GLOBALS['redirect'] = '';
$GLOBALS['index']    = array();

function __( $t, $d = null ) { return $t; }
function esc_html__( $t, $d = null ) { return $t; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_post_status( $id ) { return 'publish'; }
function get_permalink( $id ) { return 'https://example.test/institution-dashboard/'; }
function current_user_can( $c ) { return WPCPM_Roles::CAP_MANAGE === $c && (bool) $GLOBALS['manage']; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function wp_get_referer() { return $GLOBALS['referer']; }
function wp_safe_redirect( $url ) { $GLOBALS['redirect'] = (string) $url; return true; }

/**
 * `add_query_arg()` as WordPress builds it: one key and value or an array of them, set on the URL's query (a key
 * already there is replaced), the fragment kept at the end, the new keys and values put in as given.
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
function remove_query_arg( $key, $url = false ) {
	$url  = false === $url ? 'https://example.test/institution-dashboard/?wpcpm_view=students&wpcpm_institution_student=12' : (string) $url;
	$frag = '';
	if ( false !== ( $h = strpos( $url, '#' ) ) ) {
		$frag = substr( $url, $h );
		$url  = substr( $url, 0, $h );
	}
	if ( false === ( $q = strpos( $url, '?' ) ) ) {
		return $url . $frag;
	}
	parse_str( substr( $url, $q + 1 ), $query );
	foreach ( (array) $key as $k ) {
		unset( $query[ $k ] );
	}
	return substr( $url, 0, $q ) . ( array() === $query ? '' : '?' . http_build_query( $query ) ) . $frag;
}

/** The handlers end in `exit`; here they end in an exception the runner catches. */
class WaysBackLeft extends Exception {}
function wpcpm_ways_back_exit() { throw new WaysBackLeft( (string) $GLOBALS['redirect'] ); }

/**
 * A class of the plugin, loaded with each `exit;` line a throw the runner can catch.
 *
 * @param string $file Path under `includes/`.
 */
function load_rewritten( $file ) {
	$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/' . $file );
	$source = preg_replace( '/^\s*exit;\s*$/m', "\t\twpcpm_ways_back_exit();", $source );
	$source = preg_replace( '/^<\?php/', '', $source, 1 );
	$source = str_replace( "defined( 'ABSPATH' ) || exit;", '', $source );
	eval( $source );
}

/*
 * The pieces the ways back ask, as their own classes have them: the roles, the request, the flash (over the suite's
 * user meta), the switch of the shared parts, the record shape, the cohort key, the roster's argument.
 */
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php';

/** The pipeline index: which schools it holds. */
class WPCPM_Institutions_Index {
	public static function has( $record ) { return isset( $GLOBALS['index'][ (string) $record ] ); }
	/** Contract: whether the site keeps the record (release 2); off here, the ways back of today. */
	public static function on_site() { return false; }
}

// The Institutions screen's channel, the value read from its own source so it cannot drift.
preg_match( "/const FLASH\s*=\s*'([^']+)'/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' ), $institutions_flash );
eval( 'class WPCPM_Institutions { const FLASH = ' . var_export( $institutions_flash[1], true ) . '; }' );

// The other three pages an event list is drawn on.
class WPCPM_Students_Dashboard { public static function page_url() { return 'https://example.test/student-report-card/'; } }
class WPCPM_Mentors_Dashboard { public static function page_url() { return 'https://example.test/mentor-report-card/'; } }
class WPCPM_Administrators_Dashboard { public static function page_url() { return 'https://example.test/administrator-dashboard/'; } }

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';

foreach ( array(
	'modules/class-wpcpm-institution-import-form.php',
	'modules/class-wpcpm-institution-request.php',
	'modules/class-wpcpm-institution-details-card.php',
	'modules/class-wpcpm-institution-people.php',
	'modules/class-wpcpm-institution-invite.php',
	'modules/class-wpcpm-institution-tutors.php',
	'modules/class-wpcpm-semester-report-screen.php',
	'modules/class-wpcpm-institution-student-form.php',
	'modules/class-wpcpm-institution-students.php',
	'modules/class-wpcpm-institution-resend.php',
	'modules/class-wpcpm-institution-student-view.php',
	'modules/class-wpcpm-institution-assignment.php',
	'modules/class-wpcpm-institution-tracks.php',
	'modules/class-wpcpm-sponsor-event-list.php',
	'modules/class-wpcpm-agreement-generate.php',
	'modules/class-wpcpm-administrators-cards.php',
	'modules/class-wpcpm-institution-panel.php',
) as $file ) {
	load_rewritten( $file );
}

$fails = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fails, $total;
	++$total;
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}
	++$fails;
	echo "FAIL $label\n     got:  " . var_export( $actual, true ) . "\n     want: " . var_export( $expected, true ) . "\n";
}

/** One argument of an address, as a browser reads it, or null. */
function q( $url, $name ) {
	$url = (string) $url;
	$url = false !== ( $h = strpos( $url, '#' ) ) ? substr( $url, 0, $h ) : $url;
	if ( false === ( $at = strpos( $url, '?' ) ) ) {
		return null;
	}
	parse_str( substr( $url, $at + 1 ), $query );
	return isset( $query[ $name ] ) ? (string) $query[ $name ] : null;
}
/** The fragment of an address, with its `#`, or ''. */
function frag( $url ) {
	$h = strpos( (string) $url, '#' );
	return false === $h ? '' : substr( (string) $url, $h );
}
/** The address without its query and fragment. */
function base( $url ) {
	return (string) preg_replace( '/[?#].*$/', '', (string) $url );
}
/** A private method of a class, called. */
function call( $class, $method, array $args = array() ) {
	$m = new ReflectionMethod( $class, $method );
	return $m->invokeArgs( null, $args );
}

$page = 'https://example.test/institution-dashboard/';
$rec  = 'recEXA00000000001';
$GLOBALS['index'][ $rec ] = true;
$ADMIN  = 4;
$MEMBER = 20;

/**
 * A handler's exit, by its row's name.
 *
 * @return array<string, callable>
 */
function exits( $rec ) {
	return array(
		'import leave'                          => function () use ( $rec ) { call( 'WPCPM_Institution_Import_Form', 'leave', array( 'checked', array(), $rec ) ); },
		'import leave with a batch'             => function () use ( $rec ) { call( 'WPCPM_Institution_Import_Form', 'leave', array( 'checked', array( 'batch' => 7 ), $rec ) ); },
		'details finish'                        => function () use ( $rec ) { WPCPM_Institution_Details_Card::finish( $rec, 'sent' ); },
		'tracks finish'                         => function () use ( $rec ) { WPCPM_Institution_Details_Card::finish( $rec, 'tracks-saved', array( 'section' => 'tracks' ), WPCPM_Institution_Tracks::OUTCOME_ID ); },
		'assignment finish'                     => function () use ( $rec ) { WPCPM_Institution_Details_Card::finish( $rec, 'saved', array( 'section' => 'assignment' ), WPCPM_Institution_Assignment::OUTCOME_ID ); },
		'details request bounce, no referer'    => function () { call( 'WPCPM_Institution_Request', 'bounce_details', array( 'withdrawn' ) ); },
		'invite finish'                         => function () use ( $rec ) { call( 'WPCPM_Institution_Invite', 'finish', array( 'invite-sent', 'robin@example.org', $rec ) ); },
		'invite accepted'                       => function () use ( $rec ) { call( 'WPCPM_Institution_Invite', 'welcome', array( $rec ) ); },
		'people finish'                         => function () use ( $rec ) { call( 'WPCPM_Institution_People', 'finish', array( 'added', 'robin@example.org', $rec ) ); },
		'tutors finish'                         => function () use ( $rec ) { call( 'WPCPM_Institution_Tutors', 'finish', array( 'added', $rec ) ); },
		'semester report leave'                 => function () use ( $rec ) { call( 'WPCPM_Semester_Report_Screen', 'leave', array( 'saved', array(), '', $rec ) ); },
		'semester report leave, a semester'     => function () use ( $rec ) { call( 'WPCPM_Semester_Report_Screen', 'leave', array( 'saved', array(), '2026-H1', $rec ) ); },
		'student form, no referer'              => function () { call( 'WPCPM_Institution_Student_Form', 'bounce', array( 'saved', '', 'recSTUDENTRE00001' ) ); },
		'student status, no referer'            => function () { call( 'WPCPM_Institution_Students', 'bounce', array( 'changed', '', 'recSTUDENTRE00001' ) ); },
		'resend, no referer'                    => function () { call( 'WPCPM_Institution_Resend', 'bounce', array( 'sent', 12 ) ); },
		'sponsor events, institution'           => function () { wp_safe_redirect( WPCPM_Sponsor_Event_List::back_to( WPCPM_Sponsor_Event_List::BACK_INSTITUTION, get_current_user_id() ) ); wpcpm_ways_back_exit(); },
		'agreement generate bounce, no referer' => function () { call( 'WPCPM_Agreement_Generate', 'bounce', array( 'agreement-name' ) ); },
	);
}

/**
 * Where a handler's exit sends a reader.
 *
 * An Administrator's form names the school it was drawn for, as the dashboard's forms post it; a handler that
 * read the school from the press uses its own, and the ones that return to the referer use the request's.
 *
 * @param string       $name    The row.
 * @param string       $who     `admin` or `member`.
 * @param string       $rec     The school.
 * @param string|false $referer The referer, or false for none.
 * @param array        $post    The posted form.
 * @return string
 */
function exit_url( $name, $who, $rec, $referer = false, array $post = array() ) {
	$GLOBALS['manage']   = 'admin' === $who;
	$GLOBALS['uid']      = 'admin' === $who ? 4 : 20;
	$GLOBALS['referer']  = $referer;
	$GLOBALS['redirect'] = '';
	// The request names the school only where the form's action address carries it: never for the four forms that
	// post to a bare admin-post.php (`bare_forms()`), whose way back is their referer.
	$_GET                = 'admin' === $who && ! in_array( $name, bare_forms(), true ) ? array( 'wpcpm_institution_view' => $rec ) : array();
	$_POST               = $post;
	$exits               = exits( $rec );
	try {
		$exits[ $name ]();
	} catch ( WaysBackLeft $left ) {
		return (string) $GLOBALS['redirect'];
	} finally {
		$_GET  = array();
		$_POST = array();
	}
	return 'did not leave';
}

/**
 * The four forms that post to a bare admin-post.php, with no school in the address or the form: the student's form,
 * its status and its invitation, and the details withdrawal. Their ordinary way back is the referer their nonce field
 * prints; with none, an Administrator lands on the view with no school (the choice), and the message waits there.
 *
 * @return string[] Row names.
 */
function bare_forms() {
	return array( 'details request bounce, no referer', 'student form, no referer', 'student status, no referer', 'resend, no referer' );
}

/** Whether a channel is waiting in the reader's flash, read from the meta and not through the flash's memo. */
function pending( $channel, $uid ) {
	$queued = get_user_meta( $uid, WPCPM_Flash::META, true );
	return is_array( $queued ) && array_key_exists( $channel, $queued );
}

/*
 * ---- Review Focus 2 and 5: every handler's exit ----
 *
 * Each row: the exit, the view it opens, the anchor, and the channel the press leaves its message on ('' for none).
 */
$rows = array(
	// handler exit                                  view            anchor                                       flash the card takes
	array( 'import leave',                          'enroll',       '#wpcpm-import',                             WPCPM_Institution_Import_Form::FLASH ),
	array( 'import leave with a batch',             'enroll',       '#wpcpm-import',                             WPCPM_Institution_Import_Form::FLASH ),
	array( 'details finish',                        'institution',  '#wpcpm-details',                            WPCPM_Institution_Request::FLASH_DETAILS ),
	array( 'tracks finish',                         'institution',  '#' . WPCPM_Institution_Tracks::OUTCOME_ID,     WPCPM_Institution_Request::FLASH_DETAILS ),
	array( 'assignment finish',                     'institution',  '#' . WPCPM_Institution_Assignment::OUTCOME_ID, WPCPM_Institution_Request::FLASH_DETAILS ),
	array( 'details request bounce, no referer',    'institution',  '#wpcpm-details',                            WPCPM_Institution_Request::FLASH_DETAILS ),
	array( 'invite finish',                         'institution',  '#wpcpm-people',                             WPCPM_Institution_Invite::FLASH ),
	// Task 28, W1: an accepted invitation's welcome is said where it lands, on the people card.
	array( 'invite accepted',                       'institution',  '#wpcpm-people',                             WPCPM_Institution_Invite::FLASH ),
	array( 'people finish',                         'institution',  '#wpcpm-people',                             WPCPM_Institution_People::FLASH ),
	array( 'tutors finish',                         'institution',  '#wpcpm-tutors',                             WPCPM_Institution_Tutors::FLASH ),
	array( 'semester report leave',                 'reports',      '#wpcpm-report',                             WPCPM_Semester_Report_Screen::FLASH ),
	array( 'semester report leave, a semester',     'reports',      '#wpcpm-report',                             WPCPM_Semester_Report_Screen::FLASH ),
	array( 'student form, no referer',              'students',     '#wpcpm-student-form',                       WPCPM_Institution_Student_Form::FLASH ),
	array( 'student status, no referer',            'students',     '#wpcpm-student-status',                     WPCPM_Institution_Students::FLASH ),
	array( 'resend, no referer',                    'students',     '#wpcpm-student-invite',                     WPCPM_Institution_Resend::FLASH ),
	array( 'sponsor events, institution',           'events',       '#wpcpm-sevents',                            '' ),
	array( 'agreement generate bounce, no referer', 'agreement',    '#wpcpm-agreement',                          WPCPM_Institutions::FLASH ),
);

/*
 * The card that takes each channel, read from each class's render (Review Focus 5): the card key `card_url()` routes
 * by, the class whose render takes the channel, the words in that class that take it, and the cards that draw that
 * class on the dashboard, outermost last. The kept typing of a refused form is taken by the same card as its message.
 */
$takers = array(
	WPCPM_Institution_Import_Form::FLASH          => array( 'import', 'WPCPM_Institution_Import_Form', 'WPCPM_Flash::take( self::FLASH )', array() ),
	'kept: import'                                => array( 'import', 'WPCPM_Institution_Import_Form', 'WPCPM_Flash::kept( self::KEPT_FORM . $record )', array() ),
	WPCPM_Institution_Request::FLASH_DETAILS      => array( 'details', 'WPCPM_Institution_Details_Card', 'WPCPM_Flash::take( WPCPM_Institution_Request::FLASH_DETAILS )', array() ),
	'kept: details'                               => array( 'details', 'WPCPM_Institution_Details_Card', 'WPCPM_Typed_Text::kept( self::typed_form( $record,', array() ),
	WPCPM_Institution_People::FLASH               => array( 'people', 'WPCPM_Institution_People', 'WPCPM_Flash::take( self::FLASH )', array() ),
	'kept: people'                                => array( 'people', 'WPCPM_Institution_People', 'WPCPM_Flash::kept( self::KEPT_ADD . $record_id )', array() ),
	WPCPM_Institution_Invite::FLASH               => array( 'people', 'WPCPM_Institution_Invite', 'WPCPM_Flash::take( self::FLASH )', array( 'WPCPM_Institution_People' ) ),
	'kept: invite'                                => array( 'people', 'WPCPM_Institution_Invite', 'WPCPM_Flash::kept( self::KEPT_FORM . $record_id )', array( 'WPCPM_Institution_People' ) ),
	WPCPM_Institution_Tutors::FLASH               => array( 'tutors', 'WPCPM_Institution_Tutors', 'WPCPM_Flash::take( self::FLASH )', array( 'WPCPM_Institution_People' ) ),
	WPCPM_Semester_Report_Screen::FLASH           => array( 'report', 'WPCPM_Semester_Report_Screen', 'WPCPM_Flash::take( self::FLASH )', array() ),
	WPCPM_Institution_Student_Form::FLASH         => array( 'student-form', 'WPCPM_Institution_Student_Form', 'WPCPM_Flash::take( self::FLASH )', array( 'WPCPM_Institution_Student_View', 'WPCPM_Institution_Roster_View' ) ),
	'kept: student form'                          => array( 'student-form', 'WPCPM_Institution_Student_Form', 'WPCPM_Flash::kept( self::kept_form( $record ) )', array( 'WPCPM_Institution_Student_View', 'WPCPM_Institution_Roster_View' ) ),
	WPCPM_Institution_Students::FLASH             => array( 'student-status', 'WPCPM_Institution_Students', 'WPCPM_Flash::take( self::FLASH )', array( 'WPCPM_Institution_Student_View', 'WPCPM_Institution_Roster_View' ) ),
	WPCPM_Institution_Resend::FLASH               => array( 'student-invite', 'WPCPM_Institution_Resend', 'WPCPM_Flash::take( self::FLASH )', array( 'WPCPM_Institution_Student_View', 'WPCPM_Institution_Roster_View' ) ),
	WPCPM_Institutions::FLASH                     => array( 'agreement', 'WPCPM_Institution_Panel', 'WPCPM_Flash::take( WPCPM_Institutions::FLASH )', array() ),
	'kept: agreement notes'                       => array( 'agreement', 'WPCPM_Institution_Panel', 'WPCPM_Institution_Agreement::kept_', array() ),
	WPCPM_Sponsor_Event_List::FLASH               => array( 'sevents', 'WPCPM_Sponsor_Event_List', 'WPCPM_Flash::take( self::FLASH )', array() ),
);

/** The source of a class as its file holds it (the rewritten ones are evaluated, so their file is read by name). */
function class_source( $class ) {
	static $files = array(
		'WPCPM_Institution_Import_Form'  => 'class-wpcpm-institution-import-form.php',
		'WPCPM_Institution_Details_Card' => 'class-wpcpm-institution-details-card.php',
		'WPCPM_Institution_People'       => 'class-wpcpm-institution-people.php',
		'WPCPM_Institution_Invite'       => 'class-wpcpm-institution-invite.php',
		'WPCPM_Institution_Tutors'       => 'class-wpcpm-institution-tutors.php',
		'WPCPM_Semester_Report_Screen'   => 'class-wpcpm-semester-report-screen.php',
		'WPCPM_Institution_Student_Form' => 'class-wpcpm-institution-student-form.php',
		'WPCPM_Institution_Students'     => 'class-wpcpm-institution-students.php',
		'WPCPM_Institution_Resend'       => 'class-wpcpm-institution-resend.php',
		'WPCPM_Institution_Student_View' => 'class-wpcpm-institution-student-view.php',
		'WPCPM_Institution_Roster_View'  => 'class-wpcpm-institution-roster-view.php',
		'WPCPM_Institution_Panel'        => 'class-wpcpm-institution-panel.php',
		'WPCPM_Sponsor_Event_List'       => 'class-wpcpm-sponsor-event-list.php',
	);
	return (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/' . $files[ $class ] );
}
/** The cards the dashboard draws on one view: its `render_view()` case, as the source has it. */
function view_case( $view ) {
	$src   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php' );
	$const = 'self::VIEW_' . strtoupper( $view );
	$at    = strpos( $src, 'case ' . $const . ':' );
	if ( false === $at ) {
		return '';
	}
	$end = strpos( $src, 'break;', $at );
	return substr( $src, $at, false === $end ? 0 : $end - $at );
}
/** The view whose case draws a class, through the cards that draw it, or ''. */
function view_drawing( $class, array $outer ) {
	$chain = array_merge( array( $class ), $outer );
	for ( $i = 0; $i < count( $chain ) - 1; $i++ ) {
		if ( false === strpos( class_source( $chain[ $i + 1 ] ), $chain[ $i ] . '::render' ) ) {
			return '';
		}
	}
	$top = end( $chain );
	foreach ( array_keys( WPCPM_Institutions_Dashboard::VIEW_CARDS ) as $view ) {
		if ( false !== strpos( view_case( $view ), $top ) ) {
			return $view;
		}
	}
	return '';
}
/** The card key whose render takes a channel. */
function card_taking( $channel ) {
	global $takers;
	return isset( $takers[ $channel ] ) ? $takers[ $channel ][0] : '';
}

foreach ( $rows as $r ) {
	$as_admin  = exit_url( $r[0], 'admin', $rec );
	$as_member = exit_url( $r[0], 'member', $rec );
	// The Events view is drawn for a school's own people on their own page alone (`views_for()`): an Administrator
	// reaches it for no school, so its way back names none for anybody.
	// A form that posts no school (`bare_forms()`) cannot bring one back without its referer.
	$school = 'events' === $r[1] || in_array( $r[0], bare_forms(), true ) ? null : $rec;
	ck( in_array( $r[0], bare_forms(), true ) ? "$r[0], Administrator, the form posting no school: view and no school" : "$r[0], Administrator: view and school", array( base( $as_admin ), q( $as_admin, 'wpcpm_view' ), q( $as_admin, 'wpcpm_institution_view' ), frag( $as_admin ) ), array( $page, $r[1], $school, $r[2] ) );
	ck( "$r[0], member: view and no school", array( base( $as_member ), q( $as_member, 'wpcpm_view' ), q( $as_member, 'wpcpm_institution_view' ), frag( $as_member ) ), array( $page, $r[1], null, $r[2] ) );
	if ( '' !== $r[3] ) {
		ck( "$r[0]: the press leaves its message on its channel", pending( $r[3], 20 ), true );
		ck( "$r[0]: its flash is taken on that view", WPCPM_Institutions_Dashboard::view_of( card_taking( $r[3] ) ), $r[1] );
	}
}
ck( 'a batch travels', q( exit_url( 'import leave with a batch', 'member', $rec ), 'wpcpm_batch' ), '7' );
ck( 'a semester travels', q( exit_url( 'semester report leave, a semester', 'admin', $rec ), WPCPM_Semester_Report_Screen::ARG ), '2026-H1' );
ck( 'the import way back, whole', exit_url( 'import leave with a batch', 'admin', $rec ), $page . '?wpcpm_view=enroll&wpcpm_batch=7&wpcpm_institution_view=' . $rec . '#wpcpm-import' );
ck( 'an Administrator whose press acted on no school, with none in the request, comes back to no school', exit_url( 'semester report leave', 'admin', '' ), $page . '?wpcpm_view=reports#wpcpm-report' );

// Every channel the page's cards take, its card on a view, the card the class that takes it is drawn by.
foreach ( $takers as $channel => $t ) {
	ck( "channel $channel: $t[1] takes it", false !== strpos( class_source( $t[1] ), $t[2] ), true );
	ck( "channel $channel: drawn on the view its card is on", view_drawing( $t[1], $t[3] ), WPCPM_Institutions_Dashboard::view_of( $t[0] ) );
}
// A message every row leaves is one a card takes.
foreach ( $rows as $r ) {
	if ( '' !== $r[3] ) {
		ck( "$r[0]: a card takes its channel", array_key_exists( $r[3], $takers ), true );
	}
}

/* ---- Ruling 4: a referer is kept whole; only the fallback changed ---- */

// The four forms that post no school: the referer an Administrator's page really sends comes back whole, the school
// in it, at the card (the withdrawal names its card, as its form posts it).
$details_post = array( WPCPM_Institution_Request::RETURN_FIELD => WPCPM_Institution_Request::DETAILS_ANCHOR );
foreach ( array(
	array( 'details request bounce, no referer', 'institution', $details_post, '#wpcpm-details' ),
	array( 'student form, no referer', 'students', array(), '#wpcpm-student-form' ),
	array( 'student status, no referer', 'students', array(), '#wpcpm-student-status' ),
	array( 'resend, no referer', 'students', array(), '#wpcpm-student-invite' ),
) as $b ) {
	$from = $page . '?wpcpm_view=' . $b[1] . '&wpcpm_institution_view=' . $rec;
	ck( "$b[0], Administrator, from their page: the referer whole, the school in it", exit_url( $b[0], 'admin', $rec, $from, $b[2] ), $from . $b[3] );
}
// That referer is the ordinary case: each of the four forms prints WordPress's referer field with its nonce
// (`WPCPM_UI::nonce()` with its referer argument left at true), read from the form's own source: the stretch from the
// form's opening up to its nonce.
foreach ( array(
	'student'            => array( 'WPCPM_Institution_Student_Form', 'WPCPM_UI::nonce( self::nonce_action( $record ) )' ),
	'student status'     => array( 'WPCPM_Institution_Students', 'WPCPM_UI::nonce( self::nonce_action( $record, $state ) )' ),
	'resend'             => array( 'WPCPM_Institution_Resend', 'WPCPM_UI::nonce( self::nonce_action( $user_id ) )' ),
	'details withdrawal' => array( 'WPCPM_Institution_Details_Card', 'WPCPM_UI::nonce( WPCPM_Institution_Request::ACTION_DETAILS_WITHDRAW . \'_\' . $request )' ),
) as $what => $f ) {
	$src  = class_source( $f[0] );
	$at   = strpos( $src, $f[1] );
	$head = false === $at ? '' : substr( $src, max( 0, $at - 700 ), 700 );
	$form = false === strrpos( $head, 'admin-post.php' ) ? '' : substr( $head, (int) strrpos( $head, 'admin-post.php' ) );
	ck( "the $what form posts to admin-post.php with no school, and prints the referer field with its nonce", array( '' !== $form, false !== strpos( $form, 'wpcpm_institution_view' ), false !== $at ), array( true, false, true ) );
}

$student = $page . '?wpcpm_view=students&wpcpm_institution_student=12';
ck( 'a referer naming a view is kept', exit_url( 'student form, no referer', 'member', $rec, $student ), $student . '#wpcpm-student-form' );
ck( 'and so for the student status', exit_url( 'student status, no referer', 'admin', $rec, $student . '&wpcpm_institution_view=' . $rec ), $student . '&wpcpm_institution_view=' . $rec . '#wpcpm-student-status' );
ck( 'and for the resend', exit_url( 'resend, no referer', 'member', $rec, $student ), $student . '#wpcpm-student-invite' );
ck( 'the details request returns to its referer', exit_url( 'details request bounce, no referer', 'member', $rec, $page . '?wpcpm_view=institution' ), $page . '?wpcpm_view=institution' );
ck( 'and to its card when the press names it', exit_url( 'details request bounce, no referer', 'member', $rec, $page . '?wpcpm_view=institution#top', array( WPCPM_Institution_Request::RETURN_FIELD => WPCPM_Institution_Request::DETAILS_ANCHOR ) ), $page . '?wpcpm_view=institution#wpcpm-details' );
ck( 'the agreement returns to its referer', exit_url( 'agreement generate bounce, no referer', 'admin', $rec, $page . '?wpcpm_view=agreement&wpcpm_institution_view=' . $rec ), $page . '?wpcpm_view=agreement&wpcpm_institution_view=' . $rec );
ck( 'an Administrator with no school in the request comes back to no school', q( call_without_school( 'student form, no referer', $rec ), 'wpcpm_institution_view' ), null );
ck( 'nor one the index does not hold', q( call_with_school( 'student form, no referer', 'recNOTINDEXD00001' ), 'wpcpm_institution_view' ), null );

/** The exit for an Administrator whose request names no school. */
function call_without_school( $name, $rec ) {
	$GLOBALS['manage'] = true;
	$GLOBALS['uid']    = 4;
	$GLOBALS['referer'] = false;
	$_GET  = array();
	$_POST = array();
	try {
		exits( $rec )[ $name ]();
	} catch ( WaysBackLeft $left ) {
		return (string) $GLOBALS['redirect'];
	}
	return '';
}
/** The exit for an Administrator whose request names a school. */
function call_with_school( $name, $school ) {
	$GLOBALS['manage']  = true;
	$GLOBALS['uid']     = 4;
	$GLOBALS['referer'] = false;
	$_GET  = array( 'wpcpm_institution_view' => $school );
	$_POST = array();
	try {
		exits( $school )[ $name ]();
	} catch ( WaysBackLeft $left ) {
		return (string) $GLOBALS['redirect'];
	} finally {
		$_GET = array();
	}
	return '';
}

/* ---- The suites' stand-in (bin/stubs/institution-card-url.php) against the real builders ---- */

require_once __DIR__ . '/stubs/institution-card-url.php';
$real = array();
$stub = array();
$cards = array_merge( array_merge( ...array_values( WPCPM_Institutions_Dashboard::VIEW_CARDS ) ), array( 'no-such-card' ) );
foreach ( array( true, false ) as $admin ) {
	$GLOBALS['manage'] = $admin;
	foreach ( $cards as $card ) {
		foreach ( array( '', $rec, ' ' . $rec . ' ', 'recShort', 'x&wpcpm_view=enroll' ) as $record ) {
			foreach ( array( '', 'wpcpm-details-tracks-outcome', '"><b>#x y' ) as $item ) {
				foreach ( array( array(), array( 'wpcpm_report' => '2026-H1', 'wpcpm_batch' => '7' ), array( 'q' => 'a&b c#d', 'empty' => '' ) ) as $n => $args ) {
					$key          = ( $admin ? 'admin' : 'member' ) . " $card [$record] [$item] $n";
					$real[ $key ] = WPCPM_Institutions_Dashboard::card_url( $card, $record, $item, $args );
					$stub[ $key ] = wpcpm_test_institution_card_url( $page, $card, $record, $item, $args );
				}
			}
		}
		$real[ "view_of $card" ] = WPCPM_Institutions_Dashboard::view_of( $card );
		$stub[ "view_of $card" ] = wpcpm_test_institution_view_of( $card );
	}
	foreach ( array_merge( array_keys( WPCPM_Institutions_Dashboard::VIEW_CARDS ), array( 'overview', 'guide', 'no-such-view' ) ) as $view ) {
		$real[ "view_url $view" ] = WPCPM_Institutions_Dashboard::view_url( $view, $rec, array( 'wpcpm_cohort' => '2026-H1' ) );
		$stub[ "view_url $view" ] = wpcpm_test_institution_view_url( $page, $view, $rec, array( 'wpcpm_cohort' => '2026-H1' ) );
	}
	foreach ( array( array(), array( 'wpcpm_institution_view' => $rec ), array( 'wpcpm_institution_view' => 'recNOTINDEXD00001' ), array( 'wpcpm_institution_view' => 'nonsense' ) ) as $n => $get ) {
		foreach ( array( array(), array( 'wpcpm_institution_view' => $rec ) ) as $m => $post ) {
			$_GET  = $get;
			$_POST = $post;
			$real[ "requested $admin $n $m" ] = WPCPM_Institutions_Dashboard::requested_record();
			$stub[ "requested $admin $n $m" ] = wpcpm_test_institution_requested_record();
			$real[ "way_back $admin $n $m" ]  = method_exists( 'WPCPM_Institutions_Dashboard', 'way_back' ) ? WPCPM_Institutions_Dashboard::way_back( 'student-form' ) : 'no way_back()';
			$stub[ "way_back $admin $n $m" ]  = wpcpm_test_institution_way_back( $page, 'student-form' );
		}
	}
}
$_GET  = array();
$_POST = array();
ck( 'the suites\' stand-in builds every address the real class builds: every card, both readers, a school, a padded, a short and a forged record, an item, arguments, every view, and the school the request names', array( count( $real ), array_keys( array_diff_assoc( $real, $stub ) ) ), array( count( $stub ), array() ) );
$GLOBALS['opts'] = array();
ck( 'and with no page, nothing, as the stand-in says', array( WPCPM_Institutions_Dashboard::card_url( 'details', $rec ), wpcpm_test_institution_card_url( '', 'details', $rec ) ), array( '', '' ) );
$GLOBALS['opts'] = array( 'wpcpm_institution_page_id' => 42 );

// The address of the view that holds a card, with the school an Administrator's request names: the three student
// handlers' fallback, said once.
$GLOBALS['manage'] = false;
ck( 'way_back(): a member\'s Students view', method_exists( 'WPCPM_Institutions_Dashboard', 'way_back' ) ? WPCPM_Institutions_Dashboard::way_back( 'student-invite' ) : 'no way_back()', $page . '?wpcpm_view=students' );
$GLOBALS['manage'] = true;
$_GET              = array( 'wpcpm_institution_view' => $rec );
ck( 'way_back(): an Administrator\'s, with the school the request names', method_exists( 'WPCPM_Institutions_Dashboard', 'way_back' ) ? WPCPM_Institutions_Dashboard::way_back( 'student-status' ) : 'no way_back()', $page . '?wpcpm_view=students&wpcpm_institution_view=' . $rec );
$_GET = array();
ck( 'the three student handlers ask it, with their own anchor', array_map( static function ( $c ) { return substr_count( class_source( $c ), "WPCPM_Request::back( WPCPM_Institutions_Dashboard::way_back( substr( self::ANCHOR, strlen( 'wpcpm-' ) ) ), home_url( '/' ) ) . '#' . self::ANCHOR" ); }, array( 'WPCPM_Institution_Student_Form', 'WPCPM_Institution_Students', 'WPCPM_Institution_Resend' ) ), array( 1, 1, 1 ) );

/* ---- Ruling 3: the other surfaces' ways back, as before ---- */

$GLOBALS['manage'] = true;
$GLOBALS['uid']    = 4;
$manage_members    = 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $rec . '#wpcpm-people';
ck( 'an invitation pressed on Manage members goes back there', exit_url( 'invite finish', 'admin', $rec, false, array( 'wpcpm_from' => WPCPM_Institution_People::RETURN_ADMIN ) ), $manage_members );
ck( 'and a People press', exit_url( 'people finish', 'admin', $rec, false, array( 'wpcpm_from' => WPCPM_Institution_People::RETURN_ADMIN ) ), $manage_members );
ck( 'the Student Report Card\'s event list', WPCPM_Sponsor_Event_List::back_to( WPCPM_Sponsor_Event_List::BACK_STUDENT, 20 ), 'https://example.test/student-report-card/#wpcpm-sevents' );
ck( 'the Mentor Report Card\'s', WPCPM_Sponsor_Event_List::back_to( WPCPM_Sponsor_Event_List::BACK_MENTOR, 20 ), 'https://example.test/mentor-report-card/#wpcpm-sevents' );
ck( 'the Administrator Dashboard\'s', WPCPM_Sponsor_Event_List::back_to( WPCPM_Sponsor_Event_List::BACK_ADMIN, 20 ), 'https://example.test/administrator-dashboard/#' . WPCPM_Sponsor_Event_List::ADMIN_ANCHOR );
ck( 'the invitation accepted lands on the people card that says its welcome, through welcome()', false !== strpos( (string) method_body( 'WPCPM_Institution_Invite', 'complete_accept' ), 'self::welcome( $record );' ), true );

/* ---- Review Focus 1: links from elsewhere, and mails ---- */

ck( 'the assignment mail opens the details card', WPCPM_Institution_Assignment::page_url( $rec ), $page . '?wpcpm_view=institution&wpcpm_institution_view=' . $rec . '#wpcpm-details' );
ck( 'a report link', WPCPM_Semester_Report_Screen::report_url( '2026-H1' ), $page . '?wpcpm_view=reports&wpcpm_report=2026-H1#wpcpm-report' );
ck( 'no report link for a semester that is none', WPCPM_Semester_Report_Screen::report_url( 'spring' ), '' );
ck( 'the Administrator Dashboard\'s link to a school\'s report keeps the school', call( 'WPCPM_Administrators_Cards', 'report_link', array( $rec, '2026-H1' ) ), $page . '?wpcpm_view=reports&wpcpm_report=2026-H1&wpcpm_institution_view=' . $rec . '#wpcpm-report' );
$GLOBALS['manage'] = false;
$GLOBALS['uid']    = 20;
ck( 'the approved mail links the report as it always did', call( 'WPCPM_Semester_Report_Screen', 'mail_url', array( '2026-H1' ) ), $page . '?wpcpm_report=2026-H1#wpcpm-report' );
/** The body of one method in a class's source. */
function method_body( $class, $method ) {
	$m   = new ReflectionMethod( $class, $method );
	$src = explode( "\n", source_of_file( $class ) );
	return implode( "\n", array_slice( $src, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1 ) );
}
function source_of_file( $class ) {
	if ( 'WPCPM_Institution_Agreement' === $class ) {
		return (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
	}
	return class_source( $class );
}
load_rewritten( 'modules/class-wpcpm-institution-agreement.php' );
/** The link the accepted (or reinstated) mail carries: the address its builder reads. */
function accepted_mail_link( $method ) {
	$body = method_body( 'WPCPM_Institution_Agreement', $method );
	return ( false !== strpos( $body, '$page = WPCPM_Institutions_Dashboard::page_url();' ) && false !== strpos( $body, "'dashboard_link' => \$page" ) ) ? WPCPM_Institutions_Dashboard::page_url() : 'another address';
}
ck( 'the accepted mail links the bare page', accepted_mail_link( 'mail_accepted' ), $page );
ck( 'and the reinstated mail', accepted_mail_link( 'mail_reinstated' ), $page );
ck( 'the semester\'s mails are built from the address they always had', array( false !== strpos( method_body( 'WPCPM_Semester_Report_Screen', 'approved_message' ), 'self::mail_url( $cohort )' ), false !== strpos( method_body( 'WPCPM_Semester_Report_Screen', 'drafted_message' ), 'self::mail_url( $cohort )' ) ), array( true, true ) );

/* ---- Ruling 5: a card's back link, hidden on the dashboard ---- */

// The report's way back was drawn by its old branch alone, which only the Institution Dashboard reached, always on the
// parts, so it printed nowhere; it went with that branch (Task 23, fix round 1). The side menu's Semester reports item
// leads back to the list.
ck( 'no report back link anywhere: the class draws none, and neither the reading view nor the editor asks for one', array(
	method_exists( 'WPCPM_Semester_Report_Screen', 'render_back' ),
	false !== strpos( method_body( 'WPCPM_Semester_Report_Screen', 'render_reading' ) . method_body( 'WPCPM_Semester_Report_Screen', 'render_editor' ), 'render_back' ),
	false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php' ), "esc_html__( 'Back to the other semesters'" ),
), array( false, false, false ) );

/* ---- With no page, the front page, as before ---- */

$GLOBALS['opts'] = array();
ck( 'no page: the details card goes home', exit_url( 'details finish', 'member', $rec ), 'https://example.test/#wpcpm-details' );
ck( 'no page: the assignment mail goes home', WPCPM_Institution_Assignment::page_url( $rec ), 'https://example.test/?wpcpm_institution_view=' . $rec . '#wpcpm-details' );
ck( 'no page: the invitation goes home', exit_url( 'invite finish', 'member', $rec ), 'https://example.test/#wpcpm-people' );
ck( 'no page: the student form goes home', exit_url( 'student form, no referer', 'member', $rec ), 'https://example.test/#wpcpm-student-form' );
ck( 'no page: the report goes home', exit_url( 'semester report leave', 'member', $rec ), 'https://example.test/#wpcpm-report' );
ck( 'no page: the agreement goes home', exit_url( 'agreement generate bounce, no referer', 'member', $rec ), 'https://example.test/' );
ck( 'no page: the events go home', WPCPM_Sponsor_Event_List::back_to( WPCPM_Sponsor_Event_List::BACK_INSTITUTION, 20 ), 'https://example.test/' );

echo "\n" . ( $fails ? "$fails of $total checks failed" : "all passed ($total checks)" ) . "\n";
exit( $fails ? 1 : 0 );
