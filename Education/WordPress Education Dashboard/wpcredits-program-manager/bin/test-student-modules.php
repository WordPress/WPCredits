<?php
/**
 * The Student Report Card's movable modules (1.95.11, per student since 1.95.12): the order,
 * the mover, the handler.
 *
 * What is worth pinning:
 *
 * - **The saved order is repaired, never trusted.** A key this version does not know is dropped,
 *   a module the saved order does not know joins at the end in the default order, nothing
 *   appears twice, and a value that is not a list is the default - the updates first.
 * - **A move at the edge moves nothing**, and so does a key the order does not hold.
 * - **The order is the student's.** A student arranges their own page and nobody else's, whatever
 *   the form says; a program manager arranges the student the form names; anybody else is
 *   refused before anything is written. The arrows are printed for exactly those two readers.
 * - **The script gets the kept order back as JSON; the plain form lands back where it was**: the
 *   same student's page, at the module that moved.
 * - **A module with nothing to say is not printed**, so nothing empty is offered to move.
 *
 * Nothing real is loaded but the dashboard class and `WPCPM_Request`; every section renderer is a
 * stand-in that prints a marker, because this suite is about the wrapper around them.
 *
 * Run from the plugin root:  php bin/test-student-modules.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']  = array( 'wpcpm_student_page_id' => 7 );
$GLOBALS['users'] = array();
$GLOBALS['uid']   = 0;
$GLOBALS['caps']  = false;
$GLOBALS['umeta'] = array();
$GLOBALS['program'] = array( 'status' => 'WordPress Credits Program 150h', 'link' => 'https://airtable.example/form' );

class RedirectSignal extends Exception {}
class DieSignal extends Exception {}
class JsonSignal extends Exception {}

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_kses( $s, $a = array() ) { return (string) $s; }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function shortcode_atts( $pairs, $atts, $sc = '' ) { return array_merge( $pairs, is_array( $atts ) ? array_intersect_key( $atts, $pairs ) : array() ); }
function wp_enqueue_style( $h ) {}
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function current_user_can( $cap ) { return (bool) $GLOBALS['caps']; }
function get_user_by( $f, $v ) { return isset( $GLOBALS['users'][ (int) $v ] ) ? $GLOBALS['users'][ (int) $v ] : false; }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function wp_send_json_success( $data = null ) { throw new JsonSignal( json_encode( $data ) ); }
function wp_register_script( $h, $s, $d = array(), $v = false, $f = false ) {}
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function get_users( $args = array() ) { return array_values( array_filter( $GLOBALS['users'], function ( $u ) { return in_array( 'wpcpm_student', $u->roles, true ); } ) ); }
function human_time_diff( $from, $to = 0 ) { return '2 hours'; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_post_status( $id ) { return 7 === (int) $id ? 'publish' : false; }
function get_permalink( $id ) { return 'https://example.test/student-dashboard/'; }
function get_queried_object_id() { return 7; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . rawurlencode( (string) $v ); }
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce">'; }
function check_admin_referer( $a = '', $q = '' ) { $GLOBALS['nonce_checked'][] = $a; return true; }
function wp_safe_redirect( $u ) { throw new RedirectSignal( $u ); }
function wp_die( $m = '', $t = '', $a = array() ) { throw new DieSignal( is_array( $a ) && isset( $a['response'] ) ? (string) $a['response'] : 'died' ); }
function selected( $a, $b = true, $e = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function add_action() {}
function add_filter() {}
function apply_filters( $t, $v ) { return $v; }

class WPCPM_Roles {
	const CAP_MANAGE   = 'wpcpm_manage';
	const ROLE_STUDENT = 'wpcpm_student';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) {
		if ( $user instanceof WP_User ) { return $user; }
		if ( is_numeric( $user ) ) { return get_user_by( 'id', $user ); }
		return wp_get_current_user();
	}
}
class WPCPM_Students_Sync {
	const META_RECORD_ID = 'wpcpm_student_record';
	const META_UPDATED   = 'wpcpm_student_updated';
	const META_ACTIVE    = 'wpcpm_student_active';
	public static function get_program( $id ) { return $GLOBALS['program']; }
	public static function get_mentor( $id ) { return array(); }
}
class WPCPM_Dashboards { public static function nothing_to_show( $a, $m ) { return 'nothing'; } }
class WPCPM_Two_Factor { public static function prompt( $u ) {} }
class WPCPM_Program {
	public static function course_url( $s ) { return 'https://learn.example/course'; }
	public static function label( $s ) { return (string) $s; }
}
class WPCPM_Mentors_Dashboard {
	const STYLE = 'wpcpm-dashboard';
	public static function __callStatic( $n, $a ) { return ''; }
}
class WPCPM_Mentors_Sync { public static function resolve_stored( $v, $k = '' ) { return ''; } }
class WPCPM_Contribution_Teams { public static function __callStatic( $n, $a ) { return ''; } }
class WPCPM_Content_Access { const META_KEY = 'wpcpm_access'; }
class WPCPM_Icons { public static function svg( $n, $a = array() ) { return ''; } }
class WPCPM_Settings { public static function get_value( $k, $d = '' ) { return $d; } }
class WPCPM_Student_Report_Form {
	public static function render( $s, $p ) { echo '<!-- report-form -->'; }
	public static function render_hours( $s, $p ) { echo '<!-- hours -->'; }
}
class WPCPM_Student_Feedback { public static function render( $s, $p ) { echo '<!-- feedback -->'; } }
class WPCPM_Call_Calendar { public static function render_student( $s, $m ) { echo '<section class="wpcpm-student__section wpcpm-calls"><!-- calendar --></section>'; } }
class WPCPM_Handbook_Assistant { public static function render_resources( $a ) { return '<section class="wpcpm-student__section wpcpm-resources--split"><!-- resources --></section>'; } }
class WPCPM_Sponsor_Tools {
	const AUDIENCE_STUDENTS = 'students';
	public static function render( $a, $u ) { echo '<section class="wpcpm-student__section wpcpm-tools"><!-- tools --></section>'; }
	public static function render_count_line( $u ) { echo '<p class="wpcpm-tools__count"><!-- tools-count --></p>'; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-module-order.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php';

$GLOBALS['users'][1]  = new WP_User( 1, 'Program Manager', array( 'administrator' ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Moldir Bekezhanova', array( 'wpcpm_student' ) );
$GLOBALS['umeta'][30] = array( 'wpcpm_student_record' => 'recSTU1', 'wpcpm_student_updated' => time() - 60 );

$fail = 0;
$n    = 0;
function ck( $label, $got, $want ) {
	global $fail, $n;
	$n++;
	if ( $got === $want ) {
		echo "ok   $label\n";
		return;
	}
	$fail++;
	echo "FAIL $label\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) . "\n";
}
function ran( $method ) {
	$GLOBALS['nonce_checked'] = array();
	try {
		call_user_func( array( 'WPCPM_Students_Dashboard', $method ) );
		return 'returned';
	} catch ( RedirectSignal $e ) {
		return 'redirect|' . $e->getMessage();
	} catch ( DieSignal $e ) {
		return 'die|' . $e->getMessage();
	} catch ( JsonSignal $e ) {
		return 'json|' . $e->getMessage();
	}
}
function module_ids( $html ) {
	preg_match_all( '/id="wpcpm-module-([a-z]+)"/', $html, $m );
	return $m[1];
}

echo "=== The order ===\n";
$default = array( 'updates', 'course', 'forms', 'calls', 'tools' );
ck( 'nothing saved: the default order, the updates first', WPCPM_Students_Dashboard::module_order( 30 ), $default );
$GLOBALS['umeta'][30]['wpcpm_student_modules'] = array( 'calls', 'course' );
ck( 'a partial order: the rest joins at the end in default order', WPCPM_Students_Dashboard::module_order( 30 ), array( 'calls', 'course', 'updates', 'forms', 'tools' ) );
$GLOBALS['umeta'][30]['wpcpm_student_modules'] = array( 'forms', 'bogus', 'forms', 7, 'updates', 'course', 'calls', 'tools' );
ck( 'unknown keys and duplicates are dropped', WPCPM_Students_Dashboard::module_order( 30 ), array( 'forms', 'updates', 'course', 'calls', 'tools' ) );
$GLOBALS['umeta'][30]['wpcpm_student_modules'] = 'course,forms';
ck( 'a value that is not a list is the default', WPCPM_Students_Dashboard::module_order( 30 ), $default );
unset( $GLOBALS['umeta'][30]['wpcpm_student_modules'] );
ck( 'another student\'s order is not this one\'s', WPCPM_Students_Dashboard::module_order( 31 ), $default );

echo "\n=== One move ===\n";
ck( 'up at the top moves nothing', WPCPM_Students_Dashboard::moved( $default, 'updates', 'up' ), $default );
ck( 'down at the bottom moves nothing', WPCPM_Students_Dashboard::moved( $default, 'tools', 'down' ), $default );
ck( 'down swaps with the next', WPCPM_Students_Dashboard::moved( $default, 'course', 'down' ), array( 'updates', 'forms', 'course', 'calls', 'tools' ) );
ck( 'up swaps with the one before', WPCPM_Students_Dashboard::moved( $default, 'course', 'up' ), array( 'course', 'updates', 'forms', 'calls', 'tools' ) );
ck( 'a key the order does not hold moves nothing', WPCPM_Students_Dashboard::moved( $default, 'bogus', 'up' ), $default );

echo "\n=== The handler ===\n";
$GLOBALS['users'][31] = new WP_User( 31, 'Another Student', array( 'wpcpm_student' ) );
$GLOBALS['umeta'][31] = array( 'wpcpm_student_record' => 'recSTU2' );
$GLOBALS['users'][40] = new WP_User( 40, 'A Mentor', array( 'wpcpm_mentor' ) );

$GLOBALS['uid']  = 40;
$GLOBALS['caps'] = false;
$_POST           = array( 'wpcpm_module' => 'course', 'wpcpm_direction' => 'up', 'wpcpm_student' => '30' );
ck( 'somebody who is neither a student nor a manager is refused with a 403, after the nonce', array( ran( 'handle_move' ), $GLOBALS['nonce_checked'] ), array( 'die|403', array( 'wpcpm_student_module_move' ) ) );
ck( 'and nothing was written', isset( $GLOBALS['umeta'][30]['wpcpm_student_modules'] ), false );

$GLOBALS['uid'] = 30;
$_POST          = array( 'wpcpm_module' => 'course', 'wpcpm_direction' => 'up', 'wpcpm_student' => '31' );
ck( 'a student arranges their own page whatever the form says, and lands back on it at the module', ran( 'handle_move' ), 'redirect|https://example.test/student-dashboard/#wpcpm-module-course' );
ck( 'their own order is saved, and the other student\'s is untouched', array( $GLOBALS['umeta'][30]['wpcpm_student_modules'], isset( $GLOBALS['umeta'][31]['wpcpm_student_modules'] ) ), array( array( 'course', 'updates', 'forms', 'calls', 'tools' ), false ) );
$_POST = array( 'wpcpm_module' => 'tools', 'wpcpm_direction' => 'up', 'wpcpm_student' => '30', 'wpcpm_async' => '1' );
ck( 'the script gets the kept order back as JSON instead of a redirect', ran( 'handle_move' ), 'json|{"order":["course","updates","forms","tools","calls"]}' );

$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
$_POST           = array( 'wpcpm_module' => 'calls', 'wpcpm_direction' => 'up', 'wpcpm_student' => '31' );
ck( 'a manager arranges the student the form names, and lands on that student', ran( 'handle_move' ), 'redirect|https://example.test/student-dashboard/?wpcpm_student_view=31#wpcpm-module-calls' );
ck( 'saved on that student', $GLOBALS['umeta'][31]['wpcpm_student_modules'], array( 'updates', 'course', 'calls', 'forms', 'tools' ) );
$_POST = array( 'wpcpm_module' => 'calls', 'wpcpm_direction' => 'up', 'wpcpm_student' => '999' );
ck( 'a manager naming nobody who is a student, and being none themself, is refused', ran( 'handle_move' ), 'die|403' );
$saved = $GLOBALS['umeta'][31]['wpcpm_student_modules'];
$_POST = array( 'wpcpm_module' => 'bogus', 'wpcpm_direction' => 'up', 'wpcpm_student' => '31' );
ck( 'an unknown module: nothing saved, back to the page without an anchor', array( ran( 'handle_move' ), $GLOBALS['umeta'][31]['wpcpm_student_modules'] ), array( 'redirect|https://example.test/student-dashboard/?wpcpm_student_view=31', $saved ) );
$_POST = array( 'wpcpm_module' => 'forms', 'wpcpm_direction' => 'sideways', 'wpcpm_student' => '31' );
ran( 'handle_move' );
ck( 'an unknown direction saves nothing', $GLOBALS['umeta'][31]['wpcpm_student_modules'], $saved );
$_POST = array();

echo "\n=== The page ===\n";
$GLOBALS['umeta'][30]['wpcpm_student_modules'] = array( 'course', 'forms', 'updates', 'calls', 'tools' );
$GLOBALS['uid']      = 1;
$GLOBALS['caps']     = true;
$GLOBALS['enqueued'] = array();
$_GET                = array( 'wpcpm_student_view' => '30' );
$html                = WPCPM_Students_Dashboard::render();
ck( 'the modules, in the student\'s saved order', module_ids( $html ), array( 'course', 'forms', 'updates', 'calls', 'tools' ) );
ck( 'each module wears its own class', substr_count( $html, 'class="wpcpm-module wpcpm-module--' ), 5 );
ck( 'a manager gets one mover per module, and the script that moves them in place', array( substr_count( $html, 'class="wpcpm-module__mover"' ), in_array( 'wpcpm-modules', $GLOBALS['enqueued'], true ) ), array( 5, true ) );
ck( 'two arrows each, posting the action with a nonce', array( substr_count( $html, 'wpcpm-module__move--up' ), substr_count( $html, 'wpcpm-module__move--down' ), substr_count( $html, 'name="action" value="wpcpm_student_module_move"' ), substr_count( $html, 'name="_wpnonce"' ) ), array( 5, 5, 5, 5 ) );
ck( 'the arrows say which module they move, and what to announce once it has', array( substr_count( $html, 'aria-label="Move My course up"' ), substr_count( $html, 'aria-label="Move Program updates and resources down"' ), substr_count( $html, 'data-wpcpm-moved="My mentor call moved up."' ) ), array( 1, 1, 1 ) );
ck( 'the top module cannot go up and the bottom one cannot go down; nothing else is disabled', array(
	1 === preg_match( '/id="wpcpm-module-course">.*?wpcpm-module__move--up[^>]* disabled>/s', $html ),
	1 === preg_match( '/id="wpcpm-module-tools">.*?wpcpm-module__move--down[^>]* disabled>/s', $html ),
	substr_count( $html, ' disabled>' ),
), array( true, true, 2 ) );
ck( 'the student whose page it is travels with every mover', substr_count( $html, 'name="wpcpm_student" value="30"' ), 5 );
$updates = substr( $html, strpos( $html, 'id="wpcpm-module-updates">' ) );
$updates = substr( $updates, 0, strpos( $updates, '<div class="wpcpm-module ' ) );
ck( 'the updates module holds the resources and nothing else', array( substr_count( $updates, '<!-- resources -->' ), substr_count( $updates, '<!-- tools' ), substr_count( $html, '<!-- tools -->' ) ), array( 1, 0, 0 ) );
ck( 'on a manager\'s view the tools module is the count line', 1 === preg_match( '/id="wpcpm-module-tools">.*?<!-- tools-count -->/s', $html ), true );
ck( 'the forms module holds the report and the feedback forms', 1 === preg_match( '/id="wpcpm-module-forms">.*?<!-- report-form -->.*?<!-- feedback -->/s', $html ), true );
ck( 'the "last updated" line stays after the modules', strpos( $html, 'wpcpm-dashboard__updated' ) > strrpos( $html, 'id="wpcpm-module-' ), true );

$GLOBALS['uid']      = 30;
$GLOBALS['caps']     = false;
$GLOBALS['enqueued'] = array();
$_GET                = array();
$html                = WPCPM_Students_Dashboard::render();
ck( 'the student sees their own order', module_ids( $html ), array( 'course', 'forms', 'updates', 'calls', 'tools' ) );
ck( 'with the arrows and the script, since the page is theirs to arrange', array( substr_count( $html, 'class="wpcpm-module__mover"' ), substr_count( $html, 'name="wpcpm_student" value="30"' ), in_array( 'wpcpm-modules', $GLOBALS['enqueued'], true ) ), array( 5, 5, true ) );
ck( 'on their own page the tools module is theirs', 1 === preg_match( '/id="wpcpm-module-tools">.*?<!-- tools -->/s', $html ), true );

$GLOBALS['program'] = array();
$html               = WPCPM_Students_Dashboard::render();
ck( 'before the first sync there is no course and no form to move', module_ids( $html ), array( 'updates', 'calls', 'tools' ) );
$GLOBALS['program'] = array( 'status' => 'WordPress Credits Program 150h' );

echo "\n=== House rules ===\n";
$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php' );
ck( 'the mover is printed behind the may-arrange check only', 1 === preg_match( '/if \( \$can_move \) \{\s*self::render_mover\(/', $source ), true );
$script = file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/modules.js' );
ck( 'the script posts to the action attribute, never to form.action, which the hidden action field shadows', array( substr_count( $script, "fetch( form.getAttribute( 'action' )" ), substr_count( $script, 'fetch( form.action' ) ), array( 1, 0 ) );
ck( 'the script posts the same field names the handler reads', array(
	false !== strpos( file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/modules.js' ), "'wpcpm_async'" ),
	WPCPM_Students_Dashboard::FIELD_ASYNC,
), array( true, 'wpcpm_async' ) );
ck( 'the handler verifies the nonce before it reads anything', strpos( $source, 'check_admin_referer( self::ACTION_MOVE )' ) < strpos( $source, "WPCPM_Request::posted_key( self::FIELD_MODULE )" ), true );

printf( "\n%s (%d checks)\n", $fail ? "FAILED ($fail)" : 'ALL PASS', $n );
exit( $fail ? 1 : 0 );
