<?php
/**
 * An old link to the Institution Dashboard that names only an anchor moves to the view that holds the card (Task 10 of
 * the dashboard frame).
 *
 * Links sent before the frame (in mails, bookmarks, other pages) name an anchor on the bare page, for example
 * `/institution-dashboard/#wpcpm-import`. The bare page is now the Overview, which holds no such card. So the Overview
 * prints a map from each anchor to the address of the view that holds it, and the dashboards' opener follows it.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The map holds only what the reader may open**: every card of every view in the reader's own `views_for()`, the
 *   Overview's left out. A tutor's has no Enroll students and no Events, a locked school's representative's holds the
 *   Agreement alone, and only an Administrator's holds the For Administrators cards. An Administrator's addresses keep
 *   the school they are looking at, and nothing of any other school is in it. A map with a card the reader may not
 *   open would send them to a view that falls back to the Overview.
 * - **The tag is data, not markup**: `type="application/json"`, encoded with `JSON_HEX_TAG | JSON_HEX_AMP`, so no
 *   address can close the tag: its text holds no `<`, `>` or `&`.
 * - **Only the Overview prints it**, and the Sponsor Dashboard and the Administrator Dashboard print none:
 *   bin/test-institutions-dashboard.php draws every view and the choice; here, the sources.
 * - **The opener's move is guarded**: assets/js/dashboard-open.js reads the map as JSON, never runs it, and asks it for
 *   its own key. bin/js/institution-anchors.js runs the script in node on a stand-in for the page (its scenarios,
 *   their mutation proofs included, are this suite's own lines) with the map this suite draws.
 *
 * Run from the plugin root: php bin/test-institution-anchors.php
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

$GLOBALS['opts']      = array();
$GLOBALS['manage']    = false;
$GLOBALS['permalink'] = 'https://example.test/institution-dashboard/';

function __( $s, $d = null ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_post_status( $id ) { return 'publish'; }
function get_permalink( $id ) { return $GLOBALS['permalink']; }
function current_user_can( $c ) { return WPCPM_Roles::CAP_MANAGE === $c && (bool) $GLOBALS['manage']; }
function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); }

/**
 * `add_query_arg()` as WordPress builds it (bin/test-institution-views.php's): one key and value or an array of them,
 * set on the URL's query, the fragment kept at the end, the new keys and values put in exactly as given.
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
 * The stand-ins `views_for()` asks, as bin/test-institution-views.php has them: the account, the policy answering from
 * a table keyed by the viewer's login and the action, the members' stamp, the agreement's settled list, the import
 * switch read by the real `WPCPM_Institution_Import_Form::enabled()`, and the events list.
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

class WPCPM_Institution_Policy {
	const ACT_VIEW_ROSTER          = 'view_roster';
	const ACT_ADD_STUDENT          = 'add_student';
	const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
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
	const SUMMARY_SUBMITTED = 'submitted';
	public static function is_settled( $record ) { return in_array( (string) $record, $GLOBALS['settled'], true ); }
	public static function summary( $record ) { return array( 'state' => self::SUMMARY_SUBMITTED ); }
}

class WPCPM_Settings {
	public static function get_value( $key ) { return 'import_enabled' === $key ? (bool) $GLOBALS['import_on'] : null; }
}

class WPCPM_Sponsor_Event_List {
	const BACK_INSTITUTION = 'institution';
	public static function shows( WP_User $viewer ): bool { return (bool) $GLOBALS['events_show']; }
}

class WPCPM_Semester_Report { public static function reports_of( $record ) { return array(); } }

$GLOBALS['umeta'] = array();
$GLOBALS['uid']   = 0;
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_slash( $v ) { return $v; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ (int) $id ][ $key ] = $value; return true; }
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
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';

$GLOBALS['failed'] = 0;
$GLOBALS['checks'] = 0;

function ck( $label, $actual, $expected ) {
	++$GLOBALS['checks'];

	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}

	$GLOBALS['failed']++;
	echo "FAIL $label\n     expected: " . var_export( $expected, true ) . "\n     actual:   " . var_export( $actual, true ) . "\n";
}

$page       = 'https://example.test/institution-dashboard/';
$rec        = 'recEXA00000000001';
$rec_locked = 'recEXA00000000003';
$other      = 'recEXA00000000002';

$GLOBALS['opts'][ WPCPM_Institutions_Dashboard::OPT_PAGE ] = 42;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]     = array(
	'v'    => WPCPM_Institutions_Index::VERSION,
	'read' => 1,
	'rows' => array(
		$rec        => array( 'name' => 'Example Institute' ),
		$rec_locked => array( 'name' => 'Example Locked College' ),
		$other      => array( 'name' => 'Example Other College' ),
	),
);

$id = 100;
/** A reader, a member of a school at a level, or nobody's member. */
function viewer( $login, $record, $level ) {
	global $id;
	$user = new WP_User( ++$id, $login );
	if ( '' !== $record ) {
		$GLOBALS['members'][ $user->ID ] = array( $record, $level );
	}
	return $user;
}

$GLOBALS['settled'] = array( $rec );
$all = array( 'view_roster' => true, 'add_student' => true, 'view_semester_report' => true, 'agreement' => true );
$GLOBALS['allow'] = array(
	'rep'    => $all,
	'tutor'  => array( 'view_roster' => true, 'view_semester_report' => true ),
	'locked' => array( 'agreement' => true ),
	'admin'  => $all,
);

$rep    = viewer( 'rep', $rec, 'representative' );
$tutor  = viewer( 'tutor', $rec, 'tutor' );
$locked = viewer( 'locked', $rec_locked, 'representative' );
$admin  = viewer( 'admin', '', '' );

/** The reader's views, worked out afresh. */
function views_of( $record, $viewer, $manage ) {
	WPCPM_Institutions_Dashboard::forget();
	return WPCPM_Institutions_Dashboard::views_for( $record, $viewer, $manage );
}

/** The map for a reader, as the Overview builds it: the school only for an Administrator. */
function map_of( $record, $viewer, $manage ) {
	if ( ! is_callable( array( 'WPCPM_Institutions_Dashboard', 'anchor_views' ) ) ) {
		return array( 'no anchor_views()' );
	}
	return WPCPM_Institutions_Dashboard::anchor_views( views_of( $record, $viewer, $manage ), $manage ? $record : '' );
}

/** The tag for a reader, as the Overview prints it. */
function tag_of( $record, $viewer, $manage ) {
	if ( ! is_callable( array( 'WPCPM_Institutions_Dashboard', 'anchor_views_script' ) ) ) {
		return 'no anchor_views_script()';
	}
	return WPCPM_Institutions_Dashboard::anchor_views_script( views_of( $record, $viewer, $manage ), $manage ? $record : '' );
}

/** The text inside a map's tag, or null when the markup is not one tag. */
function tag_text( $html ) {
	return 1 === preg_match( '#^<script type="application/json" class="wpcpm-anchor-views">(.*)</script>$#s', (string) $html, $m ) ? $m[1] : null;
}

echo "=== The map holds what the reader may open ===\n";

$rep_map = map_of( $rec, $rep, false );
ck( 'a representative: every card of every view of theirs, the Overview\'s left out, each with its view\'s address', $rep_map, array(
	'wpcpm-roster'         => $page . '?wpcpm_view=students',
	'wpcpm-student-status' => $page . '?wpcpm_view=students',
	'wpcpm-student-form'   => $page . '?wpcpm_view=students',
	'wpcpm-student-invite' => $page . '?wpcpm_view=students',
	'wpcpm-module-students' => $page . '?wpcpm_view=students',
	'wpcpm-import'         => $page . '?wpcpm_view=enroll',
	'wpcpm-report'         => $page . '?wpcpm_view=reports',
	'wpcpm-module-report'  => $page . '?wpcpm_view=reports',
	'wpcpm-details'        => $page . '?wpcpm_view=institution',
	'wpcpm-people'         => $page . '?wpcpm_view=institution',
	'wpcpm-tutors'         => $page . '?wpcpm_view=institution',
	'wpcpm-module-people'  => $page . '?wpcpm_view=institution',
	'wpcpm-agreement'      => $page . '?wpcpm_view=agreement',
	'wpcpm-sevents'        => $page . '?wpcpm_view=events',
) );
$wanted = array( 'wpcpm-import', 'wpcpm-report', 'wpcpm-details', 'wpcpm-people', 'wpcpm-tutors', 'wpcpm-student-form', 'wpcpm-agreement', 'wpcpm-sevents' );
ck( 'the brief\'s eight anchors are there, each at the view view_of() names for its card', array_map( function ( $a ) use ( $rep_map, $page ) { return isset( $rep_map[ $a ] ) ? $rep_map[ $a ] : null; }, $wanted ), array_map( function ( $a ) use ( $page ) { return WPCPM_Institutions_Dashboard::view_url( WPCPM_Institutions_Dashboard::view_of( substr( $a, 6 ) ) ); }, $wanted ) );
ck( 'and no For Administrators card, and nothing names a school', array( isset( $rep_map['wpcpm-for-administrators'] ), isset( $rep_map['wpcpm-admin-answers'] ), isset( $rep_map['wpcpm-admin-history'] ), false !== strpos( implode( ' ', $rep_map ), 'wpcpm_institution_view' ) ), array( false, false, false, false ) );

$tutor_map = map_of( $rec, $tutor, false );
ck( 'a tutor: no Enroll students and no Events, the rest of their views', array( isset( $tutor_map['wpcpm-import'] ), isset( $tutor_map['wpcpm-sevents'] ), array_keys( $tutor_map ) ), array( false, false, array( 'wpcpm-roster', 'wpcpm-student-status', 'wpcpm-student-form', 'wpcpm-student-invite', 'wpcpm-module-students', 'wpcpm-report', 'wpcpm-module-report', 'wpcpm-details', 'wpcpm-people', 'wpcpm-tutors', 'wpcpm-module-people', 'wpcpm-agreement' ) ) );
ck( 'a locked school\'s representative: the Agreement alone, as their views_for() gives', map_of( $rec_locked, $locked, false ), array( 'wpcpm-agreement' => $page . '?wpcpm_view=agreement' ) );

$admin_map = map_of( $rec, $admin, true );
ck( 'an Administrator viewing a school: every address holds the school', array( count( $admin_map ) > 0, count( array_filter( $admin_map, function ( $u ) use ( $rec ) { return false !== strpos( $u, '&wpcpm_institution_view=' . $rec ); } ) ) === count( $admin_map ) ), array( true, true ) );
ck( 'and the For Administrators cards are in it, at their view, with Enroll students at the school', array( isset( $admin_map['wpcpm-for-administrators'], $admin_map['wpcpm-admin-answers'], $admin_map['wpcpm-admin-history'] ), $admin_map['wpcpm-for-administrators'] ?? '', $admin_map['wpcpm-import'] ?? '' ), array( true, $page . '?wpcpm_view=administrators&wpcpm_institution_view=' . $rec, $page . '?wpcpm_view=enroll&wpcpm_institution_view=' . $rec ) );
ck( 'and no Events, which an Administrator\'s views never hold', isset( $admin_map['wpcpm-sevents'] ), false );
// The live page's module anchors (`handle_move()` sent the reader back to `#wpcpm-module-<module>`, for its three modules
// alone): each opens the view that holds the module's content now, for a reader who may open it, the school kept.
ck( 'the live page\'s three module anchors open Students, Semester reports and Your institution, at the Administrator\'s school', array( $admin_map['wpcpm-module-students'] ?? '', $admin_map['wpcpm-module-report'] ?? '', $admin_map['wpcpm-module-people'] ?? '' ), array( $page . '?wpcpm_view=students&wpcpm_institution_view=' . $rec, $page . '?wpcpm_view=reports&wpcpm_institution_view=' . $rec, $page . '?wpcpm_view=institution&wpcpm_institution_view=' . $rec ) );
ck( 'and nothing of any other school', array( false !== strpos( implode( ' ', $admin_map ), $other ), false !== strpos( implode( ' ', $admin_map ), $rec_locked ) ), array( false, false ) );
$locked_admin = map_of( $rec_locked, $admin, true );
ck( 'an Administrator viewing a locked school: that school in every address, and only it', array( count( array_filter( $locked_admin, function ( $u ) use ( $rec_locked ) { return false !== strpos( $u, '&wpcpm_institution_view=' . $rec_locked ); } ) ) === count( $locked_admin ), false !== strpos( implode( ' ', $locked_admin ), $rec ) ), array( true, false ) );

$GLOBALS['import_on'] = false;
ck( 'enrollment switched off: no Enroll students in anyone\'s map', array( isset( map_of( $rec, $rep, false )['wpcpm-import'] ), isset( map_of( $rec, $admin, true )['wpcpm-import'] ) ), array( false, false ) );
$GLOBALS['import_on'] = true;
$GLOBALS['events_show'] = false;
ck( 'no event: no Events in the map', isset( map_of( $rec, $rep, false )['wpcpm-sevents'] ), false );
$GLOBALS['events_show'] = true;

if ( is_callable( array( 'WPCPM_Institutions_Dashboard', 'anchor_views' ) ) ) {
	ck( 'only the views given count: the Overview alone is an empty map, and a view the page does not have adds nothing', array( WPCPM_Institutions_Dashboard::anchor_views( array( 'overview' ), '' ), WPCPM_Institutions_Dashboard::anchor_views( array( 'overview', 'guide', 'updates', 'nope' ), '' ) ), array( array(), array() ) );
	ck( 'a school that is not a record ID is never carried', WPCPM_Institutions_Dashboard::anchor_views( array( 'enroll' ), 'rec</script>&x=1' ), array( 'wpcpm-import' => $page . '?wpcpm_view=enroll' ) );
} else {
	ck( 'anchor_views() exists', false, true );
}

echo "\n=== The tag is data, not markup ===\n";

$rep_tag = tag_of( $rec, $rep, false );
$text    = tag_text( $rep_tag );
ck( 'one script tag of type application/json, class wpcpm-anchor-views, holding the map', array( null !== $text, null === $text ? null : json_decode( $text, true ) ), array( true, $rep_map ) );
ck( 'encoded as wp_json_encode() encodes with JSON_HEX_TAG and JSON_HEX_AMP', $text, wp_json_encode( $rep_map, JSON_HEX_TAG | JSON_HEX_AMP ) );
ck( 'an Administrator\'s tag decodes to their map', json_decode( (string) tag_text( tag_of( $rec, $admin, true ) ), true ), $admin_map );
ck( 'no tag at all when the map is empty', is_callable( array( 'WPCPM_Institutions_Dashboard', 'anchor_views_script' ) ) ? WPCPM_Institutions_Dashboard::anchor_views_script( array( 'overview' ), '' ) : 'none', '' );

// A page address that tries to close the tag and open another, with an ampersand to start an entity.
$GLOBALS['permalink'] = 'https://example.test/</script><script>alert(1)</script>&amp;<!--/';
$hostile              = tag_of( $rec, $admin, true );
$inner                = tag_text( $hostile );
ck( 'an address that tries to close the tag cannot: the text holds no <, no > and no &', array( null !== $inner, null === $inner ? null : array( strpos( $inner, '<' ), strpos( $inner, '>' ), strpos( $inner, '&' ) ) ), array( true, array( false, false, false ) ) );
ck( 'and the tag closes once, at its end', substr_count( $hostile, '</script>' ), 1 );
ck( 'and the text reads back to the address, whole', null === $inner ? null : json_decode( $inner, true )['wpcpm-import'], $GLOBALS['permalink'] . '?wpcpm_view=enroll&wpcpm_institution_view=' . $rec );
$GLOBALS['permalink'] = $page;

echo "\n=== Only the Overview prints it, and no other dashboard ===\n";

$class = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php' );
$printers = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' === $file->getExtension() && false !== strpos( (string) file_get_contents( $file->getPathname() ), 'wpcpm-anchor-views' ) ) {
		$printers[] = $file->getFilename();
	}
}
ck( 'the map\'s class is in one file of the plugin\'s code, the Institution Dashboard\'s: the Sponsor Dashboard and the Administrator Dashboard print none', $printers, array( 'class-wpcpm-institutions-dashboard.php' ) );
$overview_at = strpos( $class, 'private static function render_overview(' );
$overview    = false === $overview_at ? '' : substr( $class, $overview_at, (int) strpos( $class, "\n\t}\n", $overview_at ) - $overview_at );
ck( 'and the tag is printed from one place, the Overview\'s own drawing', array( substr_count( $class, 'self::anchor_views_script(' ), substr_count( $overview, 'self::anchor_views_script( $views, $managed )' ) ), array( 1, 1 ) );

echo "\n=== The opener reads the map, and runs nothing ===\n";

$opener_file = WPCPM_PLUGIN_DIR . 'assets/js/dashboard-open.js';
$opener      = (string) file_get_contents( $opener_file );
ck( 'it reads the first map tag, parses it as JSON in a try, asks it for its own key, and replaces the address with the view\'s, the key after the #', array(
	false !== strpos( $opener, "document.querySelector( 'script.wpcpm-anchor-views' )" ),
	false !== strpos( $opener, 'JSON.parse(' ),
	false !== strpos( $opener, 'Object.prototype.hasOwnProperty.call( map, id )' ),
	false !== strpos( $opener, "window.location.replace( target + '#' + id );" ),
), array( true, true, true, true ) );
ck( 'and evaluates nothing', array( strpos( $opener, 'eval(' ), strpos( $opener, 'Function(' ), strpos( $opener, 'innerHTML' ), strpos( $opener, 'location.href =' ), strpos( $opener, 'location.assign' ) ), array( false, false, false, false, false ) );

echo "\n=== The opener's move, run by node ===\n";
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the script was not run: node is not on the path\n";
} else {
	$process = proc_open(
		escapeshellarg( $node ) . ' ' . escapeshellarg( __DIR__ . '/js/institution-anchors.js' ) . ' ' . escapeshellarg( $opener_file ) . ' ' . escapeshellarg( (string) $text ) . ' 2>&1',
		array( 1 => array( 'pipe', 'w' ) ),
		$pipes
	);

	$out = explode( "\n", rtrim( (string) stream_get_contents( $pipes[1] ) ) );

	fclose( $pipes[1] );

	$status  = proc_close( $process );
	$ran     = 0;
	$differ  = 0;
	$summary = null;

	foreach ( $out as $line ) {
		if ( 0 === strpos( $line, 'ok   ' ) ) {
			++$ran;
			++$GLOBALS['checks'];
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, 'FAIL ' ) ) {
			++$ran;
			++$differ;
			++$GLOBALS['checks'];
			++$GLOBALS['failed'];
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, '       ' ) ) {
			echo $line . "\n";
		} elseif ( '' !== trim( $line ) ) {
			if ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', trim( $line ), $counts ) ) {
				$summary = array( (int) $counts[1], (int) $counts[2] );
			}

			echo '     ' . $line . "\n";
		}
	}

	ck( 'the script ran scenarios, the real map\'s among them', array( $ran > 0, in_array( 'ok   the map the PHP suite drew for a representative\'s Overview: #wpcpm-import moves to Enroll students, #wpcpm-for-administrators moves nowhere, and the live page\'s #wpcpm-module-people moves to Your institution', $out, true ) ), array( true, true ) );
	ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
	ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );
}

echo "\n" . ( $GLOBALS['failed'] ? $GLOBALS['failed'] . ' FAILURE(S)' : 'ALL PASS' ) . ' (' . $GLOBALS['checks'] . " checks)\n";
exit( $GLOBALS['failed'] ? 1 : 0 );
