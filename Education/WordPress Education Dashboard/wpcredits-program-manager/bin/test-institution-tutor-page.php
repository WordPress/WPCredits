<?php
/**
 * A tutor's page, through the real policy and the real cards (Institutions release 1).
 *
 * The other suites of the cards stub the policy, so this one loads the real `WPCPM_Institution_Policy` and
 * `WPCPM_Institution_Members` with the real Roster View, Import Form and Agreement panel, and holds what
 * each draws for a tutor of a settled institution: the roster, and neither the Enroll students section nor
 * the agreement's upload. A representative of the same school is the control: the same policy answers yes to
 * the two actions the tutor is refused, and draws the same roster.
 *
 * Run from the plugin root:  php bin/test-institution-tutor-page.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['uid']      = 0;
$GLOBALS['users']    = array();
$GLOBALS['manage']   = array();
$GLOBALS['umeta']    = array();
$GLOBALS['settled']  = array();
$GLOBALS['opts']     = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $roles = array(), $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '' ) { $this->ID = $id; $this->display_name = $name; }
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function user_can( $u, $c ) { $id = is_object( $u ) ? $u->ID : (int) $u; return in_array( $id, $GLOBALS['manage'], true ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function remove_query_arg( $k, $url = '' ) { return 'https://example.test/institution-dashboard/'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_url_raw( $u ) { return (string) $u; }
function add_query_arg( $a, $b = null, $c = null ) { return 'https://example.test/?q'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_unslash( $v ) { return $v; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function add_filter() {}
function get_users( $a = array() ) { return array(); }
function get_queried_object_id() { return 0; }
function get_permalink( $i = 0 ) { return 'https://example.test/institution-dashboard/'; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_nonce_url( $u, $a = -1 ) { return $u; }
function esc_textarea( $s ) { return esc_html( $s ); }
function selected( $a, $b = true, $e = true ) { return ''; }
function checked( $a, $b = true, $e = true ) { return ''; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';

class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); }
	public static function tracked_statuses() { return array( 'In Sensei' ); }
}
class WPCPM_Students_Sync { const META_INSTITUTION = 'wpcpm_student_institution'; }
class WPCPM_Institution_Agreement {
	public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
}
class WPCPM_Settings {
	public static function get_value( $k ) { return 'import_enabled' === $k; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';

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
function drawn( $class, $record, $uid ) {
	$GLOBALS['uid'] = $uid;
	ob_start();
	call_user_func( array( $class, 'render' ), $record, array( 'can_manage' => false, 'cohort' => '', 'filters' => array(), 'read' => 0 ) );
	return (string) ob_get_clean();
}

$A = 'rec' . str_repeat( 'A', 14 );

$GLOBALS['users']   = array( 2 => new WP_User( 2, 'Rep' ), 10 => new WP_User( 10, 'Tutor' ) );
$GLOBALS['settled'] = array( $A );
foreach ( array( 2, 10 ) as $id ) {
	$GLOBALS['umeta'][ $id ] = array( 'wpcpm_institution_record_id' => $A, 'wpcpm_institution_active' => 1 );
}
$GLOBALS['umeta'][10]['wpcpm_institution_level'] = 'tutor';

$P = 'WPCPM_Institution_Policy';
$S = $P::subject_institution( $A );

echo "=== The real policy, for the two accounts ===\n";
ck( 'a tutor of settled A is allowed the roster and refused the import and the agreement', array( $P::decide( $P::ACT_VIEW_ROSTER, $S, 10 )['ground'], $P::decide( $P::ACT_ADD_STUDENT, $S, 10 )['allowed'], $P::decide( $P::ACT_AGREEMENT, $S, 10 )['allowed'] ), array( 'tutor', false, false ) );
ck( 'a representative of it is allowed all three', array( $P::decide( $P::ACT_VIEW_ROSTER, $S, 2 )['allowed'], $P::decide( $P::ACT_ADD_STUDENT, $S, 2 )['allowed'], $P::decide( $P::ACT_AGREEMENT, $S, 2 )['allowed'] ), array( true, true, true ) );

echo "\n=== What the real cards draw for a tutor ===\n";
$tutor_roster = drawn( 'WPCPM_Institution_Roster_View', $A, 10 );
$rep_roster   = drawn( 'WPCPM_Institution_Roster_View', $A, 2 );
ck( 'the roster is drawn, and is not the refused viewer\'s empty line', array( '' !== $tutor_roster, false !== strpos( $tutor_roster, 'There are no students to show here yet.' ) && false === strpos( $tutor_roster, 'wpcpm-roster__filters' ) ), array( true, false ) );
ck( 'the same page a representative of the school is drawn', $tutor_roster, $rep_roster );
ck( 'no Enroll students section: the import form draws nothing', drawn( 'WPCPM_Institution_Import_Form', $A, 10 ), '' );
$GLOBALS['settled'] = array();
ck( 'and no agreement upload: the panel draws nothing, locked school or not', drawn( 'WPCPM_Institution_Panel', $A, 10 ), '' );
$GLOBALS['settled'] = array( $A );
ck( 'nor does the panel of a settled school', drawn( 'WPCPM_Institution_Panel', $A, 10 ), '' );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
