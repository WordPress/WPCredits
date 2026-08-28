<?php
/**
 * Where a mentor or a student lands when they log in.
 *
 * This suite exists because the claim "they are redirected to their Report Card" was made,
 * believed, and false. `login_redirect` stepped aside whenever `$requested_redirect_to` was
 * non-empty — and core's login form carries a hidden `redirect_to` whose default value is
 * `admin_url()`, so it was non-empty on every ordinary login and the filter never once
 * redirected anybody. The `admin_init` fallback quietly covered for it, which is why the
 * outcome looked right and nothing complained.
 *
 * So the assertions below pass the arguments **core actually passes**, taken from
 * `wp-login.php`: on a plain login both `$redirect_to` and `$requested_redirect_to` are
 * `admin_url()`. A harness that passed an empty string for the second one would have agreed
 * with the broken code.
 *
 * Run from the plugin root:  php bin/test-login-redirect.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['manage']    = array();
$GLOBALS['edit']      = array();
$GLOBALS['status']    = array();
$GLOBALS['multisite'] = false;

class WP_Error {
	public function __construct( $c = '', $m = '' ) {}
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_title = '', $post_content = '', $post_type = 'page', $post_status = 'publish'; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $u, $p = null ) { return $u; }
function esc_textarea( $s ) { return esc_html( $s ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {} function add_shortcode() {}
function register_block_type() {} function do_action() {}
function wp_timezone_string() { return 'UTC'; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function get_current_user_id() { return 0; }
function wp_get_current_user() { return new WP_User( 0 ); }
function current_user_can( $c ) { return false; }
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;

	if ( 'edit_posts' === $c ) {
		return in_array( $id, $GLOBALS['edit'], true );
	}

	return in_array( $id, $GLOBALS['manage'], true );
}
function is_multisite() { return (bool) $GLOBALS['multisite']; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function network_admin_url( $p = '' ) { return 'https://example.test/wp-admin/network/' . $p; }
function get_post_status( $id ) { return $GLOBALS['status'][ (int) $id ] ?? false; }
function get_permalink( $id ) {
	return 20 === (int) $id
		? 'https://example.test/mentor-dashboard/'
		: 'https://example.test/student-dashboard/';
}
function get_post( $id ) { return null; }
function get_posts( $a = array() ) { return array(); }
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style() {} function wp_enqueue_style() {}
function wp_register_script() {} function wp_enqueue_script() {}
function get_role( $r ) { return null; }
function wp_roles() { return null; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';

/* ---- fixtures ----------------------------------------------------------- */
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = WPCPM_Settings::defaults();

// Both pages exist and are published.
$GLOBALS['opts'][ WPCPM_Mentors_Dashboard::OPT_PAGE ]  = 20;
$GLOBALS['opts'][ WPCPM_Students_Dashboard::OPT_PAGE ] = 30;
$GLOBALS['status'][20] = 'publish';
$GLOBALS['status'][30] = 'publish';

$mentor  = new WP_User( 2, 'Kel', array( WPCPM_Roles::ROLE_MENTOR ) );
$student = new WP_User( 3, 'Moldir', array( WPCPM_Roles::ROLE_STUDENT ) );
$admin   = new WP_User( 4, 'Admin', array( 'administrator' ) );
$editor  = new WP_User( 5, 'Mentor who edits', array( WPCPM_Roles::ROLE_MENTOR, 'editor' ) );

// Recognised by their Airtable link and nothing else — no role. This is the shape the
// reported bug had: the toolbar offered them the Mentor Report Card while the redirects
// treated them as a stranger, so they logged in and sat on the wp-admin dashboard. The
// first version of this suite gave every fixture the role and therefore agreed with the
// broken code.
$linked_mentor  = new WP_User( 6, 'Sebastian', array( 'subscriber' ) );
$linked_student = new WP_User( 7, 'Linked student', array( 'subscriber' ) );

// Was a mentor, no longer is: the role is gone and the active flag is 0, but the Airtable
// link stays behind by design.
$former_mentor = new WP_User( 8, 'Former mentor', array( 'subscriber' ) );

// An administrator who also mentors — the sync never gives them the role, only the link.
$admin_mentor = new WP_User( 9, 'Admin who mentors', array( 'administrator' ) );

$GLOBALS['users'] = array(
	2 => $mentor,
	3 => $student,
	4 => $admin,
	5 => $editor,
	6 => $linked_mentor,
	7 => $linked_student,
	8 => $former_mentor,
	9 => $admin_mentor,
);
$GLOBALS['manage'] = array( 4, 9 );
$GLOBALS['edit']   = array( 4, 5, 9 );

$GLOBALS['umeta'][6][ WPCPM_Mentors_Sync::META_RECORD_ID ]  = 'recMENTOR12345678';
$GLOBALS['umeta'][7][ WPCPM_Students_Sync::META_RECORD_ID ] = 'recSTUDENT1234567';
$GLOBALS['umeta'][9][ WPCPM_Mentors_Sync::META_RECORD_ID ]  = 'recADMINMENTOR123';

$GLOBALS['umeta'][8][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recFORMER12345678';
$GLOBALS['umeta'][8][ WPCPM_Mentors_Sync::META_ACTIVE ]    = '0';

const MENTOR_PAGE  = 'https://example.test/mentor-dashboard/';
const STUDENT_PAGE = 'https://example.test/student-dashboard/';
const ADMIN_ROOT   = 'https://example.test/wp-admin/';

/* ---- runner ------------------------------------------------------------- */
$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * A plain login, with the arguments `wp-login.php` really passes.
 *
 * Both are `admin_url()`: `$redirect_to` because that is the default destination, and
 * `$requested_redirect_to` because the form posted back the hidden field holding it.
 *
 * @param string  $side `mentor` or `student`.
 * @param WP_User $user Who logged in.
 * @return string
 */
function plain_login( $side, $user ) {
	$class = 'mentor' === $side ? 'WPCPM_Mentors_Dashboard' : 'WPCPM_Students_Dashboard';

	return call_user_func( array( $class, 'login_redirect' ), ADMIN_ROOT, ADMIN_ROOT, $user );
}

/**
 * A login that really did ask for somewhere, e.g. after being bounced off gated content.
 *
 * @param string  $side      `mentor` or `student`.
 * @param WP_User $user      Who logged in.
 * @param string  $requested Where they asked to go.
 * @return string
 */
function requested_login( $side, $user, $requested ) {
	$class = 'mentor' === $side ? 'WPCPM_Mentors_Dashboard' : 'WPCPM_Students_Dashboard';

	return call_user_func( array( $class, 'login_redirect' ), $requested, $requested, $user );
}

echo "=== A plain login, as core presents it ===\n";

ck( 'a mentor lands on the Mentor Report Card', array( plain_login( 'mentor', $mentor ) ), array( MENTOR_PAGE ) );
ck( 'a student lands on the Student Report Card', array( plain_login( 'student', $student ) ), array( STUDENT_PAGE ) );

// The admin root with no trailing slash is the same place.
ck( 'the admin root counts as "nowhere in particular", slash or no slash',
    array(
        WPCPM_Mentors_Dashboard::login_redirect( 'https://example.test/wp-admin', 'https://example.test/wp-admin', $mentor ),
    ),
    array( MENTOR_PAGE ) );

echo "\n=== Recognised by an Airtable link, without the role ===\n";

ck( 'a mentor linked to a record but holding no role is still routed',
    array( plain_login( 'mentor', $linked_mentor ) ), array( MENTOR_PAGE ) );
ck( 'and the same for a student',
    array( plain_login( 'student', $linked_student ) ), array( STUDENT_PAGE ) );
ck( 'the toolbar and the redirect now agree about who is a mentor',
    array( WPCPM_Mentors_Dashboard::is_mentor( $linked_mentor ), MENTOR_PAGE === plain_login( 'mentor', $linked_mentor ) ),
    array( true, true ) );

// The link outlives the role on purpose, so "is a mentor" and "is mentoring" are not the
// same question. Sending somebody to a page with nothing on it is not an improvement on
// wp-admin.
ck( 'a former mentor, inactive with the link left behind, is not routed',
    array( plain_login( 'mentor', $former_mentor ) ), array( ADMIN_ROOT ) );
ck( 'though they are still recognised as one elsewhere',
    array( WPCPM_Mentors_Dashboard::is_mentor( $former_mentor ) ), array( true ) );

// Starting from `is_mentor()` widens the net to administrators, whom the sync links but
// never gives the role. The capability exclusions are what keep them out of it.
ck( 'an administrator who mentors still goes to wp-admin',
    array( plain_login( 'mentor', $admin_mentor ) ), array( ADMIN_ROOT ) );

echo "\n=== Who is left alone ===\n";

ck( 'an administrator still goes to wp-admin', array( plain_login( 'mentor', $admin ) ), array( ADMIN_ROOT ) );
ck( 'a mentor who can also edit posts is left alone', array( plain_login( 'mentor', $editor ) ), array( ADMIN_ROOT ) );
ck( 'the student filter ignores a mentor', array( plain_login( 'student', $mentor ) ), array( ADMIN_ROOT ) );
ck( 'the mentor filter ignores a student', array( plain_login( 'mentor', $student ) ), array( ADMIN_ROOT ) );
ck( 'a failed login is not redirected', array( WPCPM_Mentors_Dashboard::login_redirect( ADMIN_ROOT, ADMIN_ROOT, new WP_Error() ) ), array( ADMIN_ROOT ) );

echo "\n=== A destination that really was asked for ===\n";

ck( 'gated content a mentor was bounced off is honoured',
    array( requested_login( 'mentor', $mentor, 'https://example.test/private-handbook/' ) ),
    array( 'https://example.test/private-handbook/' ) );
ck( 'and for a student',
    array( requested_login( 'student', $student, 'https://example.test/course/week-one/' ) ),
    array( 'https://example.test/course/week-one/' ) );
// A specific admin screen is a real request; only the *root* is the form's default.
ck( 'a specific admin screen is honoured',
    array( requested_login( 'mentor', $mentor, 'https://example.test/wp-admin/profile.php' ) ),
    array( 'https://example.test/wp-admin/profile.php' ) );

echo "\n=== When the switch is off, or the page is gone ===\n";

$settings                = WPCPM_Settings::defaults();
$settings['mentor_home'] = false;
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = $settings;
ck( 'mentor routing off means wp-admin', array( plain_login( 'mentor', $mentor ) ), array( ADMIN_ROOT ) );
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = WPCPM_Settings::defaults();

// A trashed page is truthy from `get_post_status()`, which is why this is checked: sending
// every mentor to a 404 would be worse than sending them to wp-admin.
$GLOBALS['status'][20] = 'trash';
ck( 'a trashed mentor page means wp-admin, not a 404', array( plain_login( 'mentor', $mentor ) ), array( ADMIN_ROOT ) );
$GLOBALS['status'][20] = 'publish';

unset( $GLOBALS['opts'][ WPCPM_Students_Dashboard::OPT_PAGE ] );
ck( 'a missing student page means wp-admin', array( plain_login( 'student', $student ) ), array( ADMIN_ROOT ) );
$GLOBALS['opts'][ WPCPM_Students_Dashboard::OPT_PAGE ] = 30;

echo "\n=== is_explicit_redirect ===\n";

ck( 'an empty string is not a request', array( WPCPM_Request::is_explicit_redirect( '' ) ), array( false ) );
ck( 'whitespace is not a request', array( WPCPM_Request::is_explicit_redirect( '   ' ) ), array( false ) );
ck( 'the admin root is not a request', array( WPCPM_Request::is_explicit_redirect( ADMIN_ROOT ) ), array( false ) );
ck( 'a page is', array( WPCPM_Request::is_explicit_redirect( 'https://example.test/x/' ) ), array( true ) );

$GLOBALS['multisite'] = true;
ck( 'on multisite the network admin root is not a request either',
    array( WPCPM_Request::is_explicit_redirect( 'https://example.test/wp-admin/network/' ) ), array( false ) );
$GLOBALS['multisite'] = false;
ck( 'and off multisite it is an ordinary URL',
    array( WPCPM_Request::is_explicit_redirect( 'https://example.test/wp-admin/network/' ) ), array( true ) );


echo "\n=== Password reset links survive support-session detection ===\n";

// A reset link is a plain `/wp-login.php?action=rp&key=...` URL. On this host wpcomsh redirects
// logged-out login requests to `/_wpcomsh_detect_support_session?redirect=...`, and that path is
// only served while the request is proxied AND no detection cookie is set yet. Re-open the wrapped
// URL later — from a mailbox, a chat, the back button — and nothing handles it, so WordPress 404s.
// Reported for peiraisotta and others on 28 August 2026.
//
// wpcomsh's own `need_to_detect()` short-circuits on a query parameter it defines for the purpose,
// so setting it before their `login_init` callback runs keeps the redirect from happening at all.
// Doing it here rather than in the invitation email also repairs the links already in people's
// inboxes, which changing the email body could not.

$short_circuit = 'disable-support-session-detection';

foreach ( array( 'rp', 'resetpass', 'lostpassword' ) as $action ) {
	$_GET = array( 'action' => $action, 'key' => 'abc', 'login' => 'someone' );

	WPCPM_Mail::keep_password_links_working();

	ck( sprintf( 'the %s screen opts out of detection', $action ), isset( $_GET[ $short_circuit ] ), true );
}

// Everything else is left alone: detection exists for a reason, and a plain login is the case it
// was built for. Only the long-lived links are exempted.
foreach ( array( '', 'login', 'register', 'jetpack-sso' ) as $action ) {
	$_GET = '' === $action ? array() : array( 'action' => $action );

	WPCPM_Mail::keep_password_links_working();

	ck( sprintf( 'the %s screen still detects', '' === $action ? 'login' : $action ), isset( $_GET[ $short_circuit ] ), false );
}

// An action arrives as user input, so it has to survive being nonsense.
$_GET = array( 'action' => array( 'rp' ) );
WPCPM_Mail::keep_password_links_working();
ck( 'an array action is not an action', isset( $_GET[ $short_circuit ] ), false );

$_GET = array();


echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
