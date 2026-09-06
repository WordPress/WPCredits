<?php
/**
 * WPCPM_Sponsor_Posts: the posting flag and the three capabilities, the two category terms,
 * the editor fence, the Posts card, publish and return, the byline (Sponsors module, S3).
 *
 * Run from the plugin root:  php bin/test-sponsor-posts.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['terms']     = array();
$GLOBALS['obj_terms'] = array();
$GLOBALS['next_post'] = 500;
$GLOBALS['next_term'] = 40;
$GLOBALS['uid']       = 0;
$GLOBALS['manage']    = array();
$GLOBALS['calls']     = array();
$GLOBALS['audit']     = array();
$GLOBALS['mail']      = array();
$GLOBALS['flash']     = array();
$GLOBALS['index']     = array();
$GLOBALS['now']       = '2026-09-06 10:00:00';

class WP_Error {
	private $c, $m, $d;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; $this->d = $d; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
	public function get_error_data() { return $this->d; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

class WP_User {
	public $ID = 0, $roles = array(), $caps = array(), $display_name = '', $user_email = '', $user_login = '';
	public function __construct( $id = 0, array $roles = array(), $name = 'Someone', $email = 'maciej@a8c.com' ) {
		$this->ID = (int) $id; $this->roles = $roles; $this->display_name = $name; $this->user_email = $email; $this->user_login = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) { if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; } }
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); }
	public function set_role( $r ) { $this->roles = array( $r ); }
	public function add_cap( $cap, $grant = true ) { $this->caps[ $cap ] = $grant; $GLOBALS['calls'][] = array( 'add_cap', $this->ID, $cap ); }
	public function remove_cap( $cap ) { unset( $this->caps[ $cap ] ); $GLOBALS['calls'][] = array( 'remove_cap', $this->ID, $cap ); }
	public function has_cap( $cap ) { return ! empty( $this->caps[ $cap ] ); }
}
class WP_Post {
	public $ID = 0, $post_type = 'post', $post_title = '', $post_status = 'draft', $post_author = 0, $post_content = '', $post_date = '', $post_date_gmt = '', $post_modified = '';
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WPCPM_Test_Redirect extends Exception {}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_textarea( $s ) { return esc_html( $s ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_title( $s ) { $s = strtolower( trim( (string) $s ) ); $s = preg_replace( '/[^a-z0-9]+/', '-', $s ); return trim( $s, '-' ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_kses_post( $s ) { return $s; }
function current_time( $type, $gmt = 0 ) { return $GLOBALS['now']; }
function wp_date( $f, $ts = null ) { return gmdate( $f, null === $ts ? 1788600000 : (int) $ts ); }
function number_format_i18n( $n ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . rawurlencode( (string) $v ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_permalink( $p ) { $p = get_post( $p ); return $p ? 'https://example.test/?p=' . $p->ID : ''; }
function get_preview_post_link( $p ) { $p = get_post( $p ); return $p ? 'https://example.test/?p=' . $p->ID . '&preview=true' : ''; }
function get_edit_post_link( $id, $c = 'display' ) { return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit'; }
function get_the_date( $f, $p ) { return '2026-09-06'; }
function get_the_modified_date( $f, $p ) { return '2026-09-06'; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $a ) . '" />'; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_by( $f, $v ) { return ( 'id' === $f && isset( $GLOBALS['users'][ (int) $v ] ) ) ? $GLOBALS['users'][ (int) $v ] : false; }
function get_userdata( $id ) { return get_user_by( 'id', $id ); }
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $k ] ) ? $GLOBALS['umeta'][ $id ][ $k ] : ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ $id ][ $k ] ); return true; }
function get_users( array $args ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $u ) {
		if ( isset( $args['meta_key'] ) ) {
			$have = get_user_meta( $u->ID, $args['meta_key'], true );
			if ( isset( $args['meta_value'] ) ) { if ( (string) $have !== (string) $args['meta_value'] ) { continue; } } elseif ( '' === (string) $have ) { continue; }
		}
		$out[] = $u;
	}
	return $out;
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_post( $p = null ) { if ( null === $p ) { return isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null; } if ( $p instanceof WP_Post ) { return $p; } return isset( $GLOBALS['posts'][ (int) $p ] ) ? $GLOBALS['posts'][ (int) $p ] : null; }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_post_meta', (int) $id, $k, $v ); return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function wp_insert_post( $a, $e = false ) { $id = $GLOBALS['next_post']++; $a['ID'] = $id; $GLOBALS['posts'][ $id ] = new WP_Post( $a ); return $id; }
// `edit_date` is an instruction to wp_update_post, never a column: core reads it and drops it.
function wp_update_post( $a, $e = false ) { $id = (int) $a['ID']; if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; } foreach ( $a as $k => $v ) { if ( 'edit_date' !== $k ) { $GLOBALS['posts'][ $id ]->$k = $v; } } $GLOBALS['calls'][] = array( 'wp_update_post', $id, $a ); return $id; }
function wp_publish_post( $p ) { $id = $p instanceof WP_Post ? $p->ID : (int) $p; $GLOBALS['posts'][ $id ]->post_status = 'publish'; $GLOBALS['calls'][] = array( 'wp_publish_post', $id ); }
function wp_is_post_revision( $p ) { return false; }
function wp_is_post_autosave( $p ) { return false; }
function get_posts( array $args ) {
	$GLOBALS['last_get_posts'] = $args;
	$out = array();
	$statuses = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
	foreach ( $GLOBALS['posts'] as $p ) {
		if ( 'post' !== $p->post_type || ! in_array( $p->post_status, $statuses, true ) ) { continue; }
		if ( isset( $args['meta_key'] ) && (string) get_post_meta( $p->ID, $args['meta_key'], true ) !== (string) $args['meta_value'] ) { continue; }
		$out[] = $p;
	}
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) { $out = array_slice( $out, 0, (int) $args['numberposts'] ); }
	return $out;
}
// Terms: one flat table keyed by id, with slug and parent, the way core answers.
function term_exists( $slug, $tax = '', $parent = null ) { foreach ( $GLOBALS['terms'] as $id => $t ) { if ( $t['slug'] === $slug ) { return array( 'term_id' => $id, 'term_taxonomy_id' => $id ); } } return null; }
function get_term_by( $f, $v, $tax = 'category' ) { foreach ( $GLOBALS['terms'] as $id => $t ) { if ( ( 'slug' === $f && $t['slug'] === $v ) || ( 'id' === $f && $id === (int) $v ) ) { return (object) array_merge( array( 'term_id' => $id ), $t ); } } return false; }
function get_term( $id, $tax = 'category' ) { return get_term_by( 'id', (int) $id, $tax ); }
function wp_insert_term( $name, $tax, $args = array() ) {
	$slug = isset( $args['slug'] ) ? $args['slug'] : sanitize_title( $name );
	if ( term_exists( $slug ) ) { return new WP_Error( 'term_exists', 'A term with the name provided already exists.', term_exists( $slug )['term_id'] ); }
	$id = $GLOBALS['next_term']++;
	$GLOBALS['terms'][ $id ] = array( 'name' => $name, 'slug' => $slug, 'parent' => isset( $args['parent'] ) ? (int) $args['parent'] : 0 );
	$GLOBALS['calls'][] = array( 'wp_insert_term', $name, $slug );
	return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
}
function wp_update_term( $id, $tax, $args = array() ) { foreach ( $args as $k => $v ) { $GLOBALS['terms'][ (int) $id ][ $k ] = $v; } $GLOBALS['calls'][] = array( 'wp_update_term', (int) $id, $args ); return array( 'term_id' => (int) $id ); }
function wp_set_post_terms( $id, $terms, $tax, $append = false ) { $GLOBALS['obj_terms'][ (int) $id ] = array_values( array_map( 'intval', (array) $terms ) ); $GLOBALS['calls'][] = array( 'wp_set_post_terms', (int) $id, $GLOBALS['obj_terms'][ (int) $id ], $append ); return $GLOBALS['obj_terms'][ (int) $id ]; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h, $p, $n ); }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_filter', $h, $p, $n ); }
function apply_filters( $h, $v ) { return $v; }
function remove_meta_box( $id, $screen, $ctx ) { $GLOBALS['calls'][] = array( 'remove_meta_box', $id, $screen, $ctx ); }
function remove_menu_page( $slug ) { $GLOBALS['calls'][] = array( 'remove_menu_page', $slug ); }
function is_admin() { return ! empty( $GLOBALS['is_admin'] ); }
function is_singular( $t = '' ) { return ! empty( $GLOBALS['singular'] ); }
function in_the_loop() { return ! empty( $GLOBALS['singular'] ); }
function is_main_query() { return true; }
function check_admin_referer( $a ) { $GLOBALS['calls'][] = array( 'nonce', $a ); return true; }
function wp_safe_redirect( $u ) { throw new WPCPM_Test_Redirect( $u ); }
function wp_die( $m, $t = '', $a = array() ) { throw new WPCPM_Test_Redirect( 'die:' . ( is_int( $t ) ? $t : 0 ) ); }

class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator';
	const CAP_VIEW_STUDENT = 'wpcpm_view_student_content'; const CAP_VIEW_MENTOR = 'wpcpm_view_mentor_content'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Mentors_Sync { public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); } }
class WPCPM_Students_Sync { const META_RECORD_ID = 'wpcpm_student_record_id'; }
class WPCPM_Institution_Members {
	const META_RECORD_ID = 'wpcpm_institution_record_id'; const META_ACTIVE = 'wpcpm_institution_active';
	public static function institution_of( $user = null ) { return ''; }
}
class WPCPM_Sponsors_Index {
	public static function has( $record ) { return isset( $GLOBALS['index'][ $record ] ); }
	public static function row( $record ) { return isset( $GLOBALS['index'][ $record ] ) ? $GLOBALS['index'][ $record ] : null; }
	public static function display_logo( $record ) { return $GLOBALS['logo'] ?? array(); }
	public static function empty_row() { return array( 'record_id' => '', 'name' => '', 'website' => '', 'status' => '' ); }
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}
class WPCPM_Content_Access {
	const META_KEY = '_wpcpm_access_level'; const LEVEL_STUDENTS_MENTORS = 'students_mentors'; const QUERY_UNGATED = 'wpcpm_ungated';
	public static function can_view( $post, $user = null ) { return empty( $GLOBALS['refuse_view'] ); }
}
class WPCPM_Field_Value { public static function clean_url( $u ) { return preg_match( '#^https?://#i', (string) $u ) ? (string) $u : ''; } }
class WPCPM_Mail { public static function send( $to, $context, $build ) { $u = $to instanceof WP_User ? $to : get_user_by( 'id', (int) $to ); $GLOBALS['mail'][] = array( 'to' => $u ? $u->ID : 0, 'context' => $context, 'mail' => call_user_func( $build, $u ) ); return true; } }
class WPCPM_Request {
	public static function posted_text( $k ) { return isset( $_POST[ $k ] ) ? sanitize_text_field( $_POST[ $k ] ) : ''; }
	public static function posted_key( $k ) { return isset( $_POST[ $k ] ) ? sanitize_key( $_POST[ $k ] ) : ''; }
	public static function posted_id( $k ) { return isset( $_POST[ $k ] ) ? absint( $_POST[ $k ] ) : 0; }
}
class WPCPM_Flash { public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][] = array( $channel, $value ); } }
class WPCPM_Return { public static function url( $default ) { return $default; } }
class WPCPM_Sponsors { const FLASH = 'sponsors_admin'; }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function leave( $status, $card, $record = '', $detail = '' ) { throw new WPCPM_Test_Redirect( $status . '|' . $card . '|' . $record . '|' . $detail ); }
}
class WPCPM_Refusal_Meter { public static function is_locked( $scope, $user ) { return false; } public static function refuse( $scope, $user ) { return 0; } }

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-posts.php';

$fail = 0; $checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}
function calls( $kind ) { return array_values( array_filter( $GLOBALS['calls'], static function ( $c ) use ( $kind ) { return $c[0] === $kind; } ) ); }
function reset_calls() { $GLOBALS['calls'] = array(); }

$S = 'recSPN00000000001';
$GLOBALS['index'][ $S ] = array( 'record_id' => $S, 'name' => 'TEST Sponsor', 'website' => 'https://sponsor.example', 'status' => 'Approved' );
$GLOBALS['users'][20] = new WP_User( 20, array( 'wpcpm_sponsor' ), 'Member One' );
$GLOBALS['users'][21] = new WP_User( 21, array( 'wpcpm_sponsor' ), 'Member Two' );
$GLOBALS['users'][1]  = new WP_User( 1, array( 'administrator' ), 'Manager' );
$GLOBALS['manage']    = array( 1 );

echo "=== The flag and the capabilities ===\n";
ck( 'the flag defaults to on for a sponsor with no option yet', WPCPM_Sponsor_Posts::posting_enabled( $S ), true );
ck( 'the cap set is exactly the three and never publish_posts', WPCPM_Sponsor_Posts::caps(), array( 'edit_posts', 'delete_posts', 'upload_files' ) );

WPCPM_Sponsor_Members::attach( 20, $S, 'manager', 1 );
ck( 'attaching an account applies the flag: the three caps, byte for byte', $GLOBALS['users'][20]->caps, array( 'edit_posts' => true, 'delete_posts' => true, 'upload_files' => true ) );

WPCPM_Sponsor_Members::attach( 21, $S, 'manager', 1 );
reset_calls();
ck( 'switching posting off is recorded', WPCPM_Sponsor_Posts::set_posting( $S, false, 1 ), true );
ck( 'and removes exactly the three caps from each live member', array( $GLOBALS['users'][20]->caps, $GLOBALS['users'][21]->caps, count( calls( 'remove_cap' ) ) ), array( array(), array(), 6 ) );
ck( 'the flag reads off', WPCPM_Sponsor_Posts::posting_enabled( $S ), false );
ck( 'an audit row names the switch', end( $GLOBALS['audit'] )['kind'], 'posting_switched' );
reset_calls();
WPCPM_Sponsor_Posts::set_posting( $S, true, 1 );
ck( 'switching on adds them back to each member', array( $GLOBALS['users'][21]->caps, count( calls( 'add_cap' ) ) ), array( array( 'edit_posts' => true, 'delete_posts' => true, 'upload_files' => true ), 6 ) );
WPCPM_Sponsor_Members::detach( 21, 'removed', 1 );
ck( 'detaching drops the three', $GLOBALS['users'][21]->caps, array() );
$GLOBALS['uid'] = 20;
ck( 'a member with the flag on may post', WPCPM_Sponsor_Posts::may_post(), true );
WPCPM_Sponsor_Posts::set_posting( $S, false, 1 );
ck( 'a member with the flag off may not', WPCPM_Sponsor_Posts::may_post(), false );
WPCPM_Sponsor_Posts::set_posting( $S, true, 1 );
$GLOBALS['uid'] = 1;
ck( 'a manager is not a poster here: the flag is about members', WPCPM_Sponsor_Posts::may_post(), false );
$GLOBALS['users'][20]->caps = array();
WPCPM_Sponsor_Posts::heal_caps( 20 );
ck( 'a role change is healed for a live member', $GLOBALS['users'][20]->caps, array( 'edit_posts' => true, 'delete_posts' => true, 'upload_files' => true ) );
WPCPM_Sponsor_Posts::heal_caps( 1 );
ck( 'and does nothing for a non-member', $GLOBALS['users'][1]->caps, array() );
WPCPM_Sponsor_Posts::apply_caps( 20, '' );
ck( 'no record, no caps', $GLOBALS['users'][20]->caps, array() );
WPCPM_Sponsor_Posts::apply_caps( 20, $S );

echo "\n=== The terms ===\n";
reset_calls();
$child = WPCPM_Sponsor_Posts::ensure_terms( $S );
ck( 'the parent Sponsors and the child named after the company are created once', array( count( calls( 'wp_insert_term' ) ), get_term_by( 'slug', 'sponsors' )->name, get_term( $child )->name, get_term( $child )->parent ), array( 2, 'Sponsors', 'TEST Sponsor', get_term_by( 'slug', 'sponsors' )->term_id ) );
ck( 'the child is recorded for the record', WPCPM_Sponsor_Posts::term_of( $S ), $child );
reset_calls();
ck( 'a second call creates nothing and answers the same id', array( WPCPM_Sponsor_Posts::ensure_terms( $S ), count( calls( 'wp_insert_term' ) ) ), array( $child, 0 ) );
$S2 = 'recSPN00000000002';
$GLOBALS['index'][ $S2 ] = array( 'record_id' => $S2, 'name' => 'TEST Sponsor', 'website' => '', 'status' => 'Approved' );
$child2 = WPCPM_Sponsor_Posts::ensure_terms( $S2 );
ck( 'a second company with the same name gets a suffixed slug', array( $child2 !== $child, get_term( $child2 )->slug ), array( true, 'test-sponsor-2' ) );
reset_calls();
WPCPM_Sponsor_Posts::rename_terms(
	array( $S => array( 'name' => 'TEST Sponsor' ), $S2 => array( 'name' => 'TEST Sponsor' ) ),
	array( $S => array( 'name' => 'TEST Sponsor Renamed' ), $S2 => array( 'name' => 'TEST Sponsor' ) )
);
ck( 'a renamed company renames its child term and keeps the slug', array( count( calls( 'wp_update_term' ) ), get_term( $child )->name, get_term( $child )->slug ), array( 1, 'TEST Sponsor Renamed', 'test-sponsor' ) );
WPCPM_Sponsor_Posts::delete_all();
ck( 'delete_all() removes the flags and the term records and keeps the terms', array( array_filter( array_keys( $GLOBALS['opts'] ), static function ( $k ) { return 0 === strpos( $k, 'wpcpm_sponsor_flags_' ) || 0 === strpos( $k, 'wpcpm_sponsor_term_' ); } ), count( $GLOBALS['terms'] ) ), array( array(), 3 ) );

echo "\n=== The fence ===\n";
reset_calls();
WPCPM_Sponsor_Posts::init();
$hooked = array_map( static function ( $c ) { return $c[1]; }, array_merge( calls( 'add_filter' ), calls( 'add_action' ) ) );
sort( $hooked );
ck( 'init() hooks the fence for everyone, and decides per call', $hooked, array( 'add_meta_boxes', 'admin_bar_menu', 'admin_menu', 'admin_post_wpcpm_sponsor_flags', 'admin_post_wpcpm_sponsor_post_publish', 'admin_post_wpcpm_sponsor_post_return', 'ajax_query_attachments_args', 'get_the_author_display_name', 'init', 'oembed_response_data', 'pre_get_posts', 'rest_after_insert_post', 'rest_attachment_query', 'save_post_post', 'set_user_role', 'the_author', 'the_content', 'upload_mimes', 'wp_insert_post_data', 'wp_prepare_attachment_for_js' ) );
ck( 'the hooks that need two or more arguments ask for them', array( in_array( array( 'add_action', 'save_post_post', 20, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'wp_insert_post_data', 10, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'get_the_author_display_name', 10, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'the_content', 6, 1 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'oembed_response_data', 20, 2 ), $GLOBALS['calls'], true ) ), array( true, true, true, true, true ) );
$GLOBALS['uid'] = 1;
$untouched = array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_date' => '2030-01-01 00:00:00', 'post_date_gmt' => '2030-01-01 00:00:00' );
reset_calls();
ck( 'a manager is never fenced: insert data, mimes and the admin list pass through untouched', array( WPCPM_Sponsor_Posts::filter_insert_data( $untouched, array() ), WPCPM_Sponsor_Posts::limit_mimes( array( 'pdf' => 'application/pdf' ) ), WPCPM_Sponsor_Posts::scope_media( array() ) ), array( $untouched, array( 'pdf' => 'application/pdf' ), array() ) );
WPCPM_Sponsor_Posts::remove_access_box();
WPCPM_Sponsor_Posts::trim_menu();
ck( 'and loses no box and no menu', array( calls( 'remove_meta_box' ), calls( 'remove_menu_page' ) ), array( array(), array() ) );
$GLOBALS['uid'] = 0;
ck( 'a logged-out request is not fenced either', WPCPM_Sponsor_Posts::filter_insert_data( $untouched, array() ), $untouched );
$GLOBALS['uid'] = 20;

$data = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_date' => '2030-01-01 00:00:00', 'post_date_gmt' => '2030-01-01 00:00:00' ), array() );
ck( 'a member cannot publish or schedule: pending, now, their own', array( $data['post_status'], $data['post_author'], $data['post_date'], $data['post_date_gmt'] ), array( 'pending', 20, $GLOBALS['now'], $GLOBALS['now'] ) );
$data = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => 20, 'post_date' => '2026-09-01 09:00:00', 'post_date_gmt' => '2026-09-01 09:00:00' ), array() );
ck( 'a draft stays a draft with its date', array( $data['post_status'], $data['post_date'] ), array( 'draft', '2026-09-01 09:00:00' ) );
$data = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_date' => '2030-01-01 00:00:00', 'post_date_gmt' => '0000-00-00 00:00:00' ), array() );
ck( 'a future local date with the zero GMT date is clamped too', array( $data['post_date'], $data['post_date_gmt'] ), array( $GLOBALS['now'], $GLOBALS['now'] ) );
ck( 'auto-draft and trash keep their status', array( WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'auto-draft', 'post_author' => 20 ), array() )['post_status'], WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'trash', 'post_author' => 20 ), array() )['post_status'] ), array( 'auto-draft', 'trash' ) );
$data = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => 1 ), array() );
ck( 'another post type keeps its author as well as its status', array( $data['post_status'], $data['post_author'] ), array( 'publish', 1 ) );
$data = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => 20 ), array() );
ck( 'another post type is not touched', $data['post_status'], 'publish' );

$pid = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_title' => 'Ten tips' ) );
$GLOBALS['obj_terms'][ $pid ] = array( 99 );
$GLOBALS['pmeta'][ $pid ][ WPCPM_Content_Access::META_KEY ] = 'public';
reset_calls();
WPCPM_Sponsor_Posts::enforce( $pid, get_post( $pid ) );
$parent = WPCPM_Sponsor_Posts::parent_term_id();
$mine   = WPCPM_Sponsor_Posts::term_of( $S );
ck( 'the categories are exactly Sponsors and the company, the foreign term dropped', array( $GLOBALS['obj_terms'][ $pid ], calls( 'wp_set_post_terms' )[0][3] ), array( array( $parent, $mine ), false ) );
ck( 'the level is pinned to Students and mentors and the post is stamped', array( get_post_meta( $pid, WPCPM_Content_Access::META_KEY, true ), get_post_meta( $pid, WPCPM_Sponsor_Policy::META_POST_SPONSOR, true ) ), array( 'students_mentors', $S ) );
reset_calls();
WPCPM_Sponsor_Posts::enforce_rest( get_post( $pid ) );
ck( 'the REST save pins the same way', count( calls( 'wp_set_post_terms' ) ), 1 );
$others = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 1, 'post_title' => 'Not theirs' ) );
reset_calls();
WPCPM_Sponsor_Posts::enforce( $others, get_post( $others ) );
ck( 'a member\'s request never pins a post that is not theirs', calls( 'wp_set_post_terms' ), array() );

$q = new class() { public $vars = array(); public function is_main_query() { return true; } public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; } public function set( $k, $v ) { $this->vars[ $k ] = $v; } };
$GLOBALS['is_admin'] = true;
$q->set( 'post_type', 'post' );
WPCPM_Sponsor_Posts::scope_admin_list( $q );
ck( 'the admin list is the member\'s own posts', $q->get( 'author' ), 20 );
$q2 = new class() { public $vars = array(); public function is_main_query() { return true; } public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; } public function set( $k, $v ) { $this->vars[ $k ] = $v; } };
$q2->set( 'post_type', 'attachment' );
WPCPM_Sponsor_Posts::scope_admin_list( $q2 );
$q3 = new class() { public $vars = array(); public function is_main_query() { return true; } public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; } public function set( $k, $v ) { $this->vars[ $k ] = $v; } };
$GLOBALS['is_admin'] = false;
$q3->set( 'post_type', 'post' );
WPCPM_Sponsor_Posts::scope_admin_list( $q3 );
$GLOBALS['is_admin'] = true;
$q4 = new class() { public $vars = array(); public function is_main_query() { return true; } public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; } public function set( $k, $v ) { $this->vars[ $k ] = $v; } };
$q4->set( 'post_type', 'wp_block' );
WPCPM_Sponsor_Posts::scope_admin_list( $q4 );
$GLOBALS['is_admin'] = false;
ck( 'the media list is scoped, the front end and a post type that is not ours are not', array( $q2->get( 'author' ), $q3->get( 'author' ), $q4->get( 'author' ) ), array( 20, '', '' ) );
$q5 = new class() { public $vars = array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any' ); public function is_main_query() { return false; } public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; } public function set( $k, $v ) { $this->vars[ $k ] = $v; } };
$GLOBALS['is_admin'] = true;
WPCPM_Sponsor_Posts::scope_admin_list( $q5 );
$GLOBALS['is_admin'] = false;
ck( 'an admin-ajax query over posts and pages in any status is the member\'s own too', $q5->get( 'author' ), 20 );
$q6 = new class() { public $vars = array(); public function is_main_query() { return false; } public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; } public function set( $k, $v ) { $this->vars[ $k ] = $v; } };
$q7 = new class() { public $vars = array( 'post_type' => 'any' ); public function is_main_query() { return false; } public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; } public function set( $k, $v ) { $this->vars[ $k ] = $v; } };
$GLOBALS['is_admin'] = true;
WPCPM_Sponsor_Posts::scope_admin_list( $q6 );
WPCPM_Sponsor_Posts::scope_admin_list( $q7 );
$GLOBALS['is_admin'] = false;
ck( 'a query naming no type at all, and one naming any, are the member\'s own as well', array( $q6->get( 'author' ), $q7->get( 'author' ) ), array( 20, 20 ) );
$GLOBALS['is_admin'] = false;
ck( 'the media queries are scoped to the member', WPCPM_Sponsor_Posts::scope_media( array( 'post_type' => 'attachment' ) )['author'], 20 );
ck( 'a member gets details of their own files only', array( WPCPM_Sponsor_Posts::scope_attachment_details( array( 'id' => 1 ), new WP_Post( array( 'ID' => 1, 'post_type' => 'attachment', 'post_author' => 20 ) ) ), WPCPM_Sponsor_Posts::scope_attachment_details( array( 'id' => 2 ), new WP_Post( array( 'ID' => 2, 'post_type' => 'attachment', 'post_author' => 1 ) ) ) ), array( array( 'id' => 1 ), false ) );
$GLOBALS['uid'] = 1;
ck( 'a manager is asking, so the same file answers in full', WPCPM_Sponsor_Posts::scope_attachment_details( array( 'id' => 2 ), new WP_Post( array( 'ID' => 2, 'post_type' => 'attachment', 'post_author' => 1 ) ) ), array( 'id' => 2 ) );
$GLOBALS['uid'] = 20;
ck( 'uploads are the four image kinds only', array_keys( WPCPM_Sponsor_Posts::limit_mimes( array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'svg' => 'image/svg+xml', 'mp4' => 'video/mp4' ) ) ), array( 'jpg|jpeg|jpe', 'png', 'gif', 'webp' ) );
reset_calls();
WPCPM_Sponsor_Posts::remove_access_box();
WPCPM_Sponsor_Posts::trim_menu();
ck( 'the access box, Comments and Tools go', array( calls( 'remove_meta_box' )[0], array_map( static function ( $c ) { return $c[1]; }, calls( 'remove_menu_page' ) ) ), array( array( 'remove_meta_box', 'wpcpm-access-level', 'post', 'side' ), array( 'edit-comments.php', 'tools.php' ) ) );

echo "\n=== The Posts card ===\n";
function card( $record, $uid, $open = '' ) { $GLOBALS['uid'] = $uid; ob_start(); WPCPM_Sponsor_Posts::render( $record, array( 'can_manage' => in_array( $uid, $GLOBALS['manage'], true ), 'open' => $open, 'viewer' => wp_get_current_user() ) ); return ob_get_clean(); }
$draft = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => 20, 'post_title' => 'Early draft' ) );
$GLOBALS['pmeta'][ $draft ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $S;
$GLOBALS['pmeta'][ $draft ][ WPCPM_Sponsor_Posts::META_RETURN_NOTE ] = 'Please add a screenshot.';
$GLOBALS['pmeta'][ $draft ][ WPCPM_Sponsor_Posts::META_RETURNED ] = 1788500000;
$html = card( $S, 20 );
ck( 'the member sees the card with its count, Write a post, and each post with its state', array(
	false !== strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-posts" class="wpcpm-group wpcpm-group__disclosure">' ),
	false !== strpos( $html, '<span class="wpcpm-group__count">2</span>' ),
	false !== strpos( $html, 'href="https://example.test/wp-admin/post-new.php"' ),
	false !== strpos( $html, 'wpcpm-posts__item--pending' ),
	false !== strpos( $html, 'wpcpm-posts__item--returned' ),
	false !== strpos( $html, '<p class="wpcpm-posts__note">Please add a screenshot.</p>' ),
	false === strpos( $html, 'wpcpm_sponsor_post_publish' ),
), array( true, true, true, true, true, true, true ) );
ck( 'the card\'s query stands outside the gate by name and suppresses nothing else', array( $GLOBALS['last_get_posts'][ WPCPM_Content_Access::QUERY_UNGATED ], $GLOBALS['last_get_posts']['suppress_filters'] ), array( true, false ) );
$html = card( $S, 1 );
ck( 'a manager sees Publish and Return on the pending post only, and no Write a post', array( substr_count( $html, 'name="action" value="wpcpm_sponsor_post_publish"' ), substr_count( $html, 'name="action" value="wpcpm_sponsor_post_return"' ), strpos( $html, 'post-new.php' ) ), array( 1, 1, false ) );
$html = card( $S, 20 );
ck( 'a member with posting on also has the way into wp-admin\'s Posts screen beside Write a post', false !== strpos( $html, '<a class="wpcpm-button" href="https://example.test/wp-admin/post-new.php">Write a post</a> <a class="wpcpm-posts__admin" href="https://example.test/wp-admin/edit.php">Your posts in wp-admin</a>' ), true );
$html = card( $S, 1 );
ck( 'a manager has the Posts screen filtered to the sponsor\'s category', false !== strpos( $html, '<a class="wpcpm-posts__admin" href="https://example.test/wp-admin/edit.php?cat=' . WPCPM_Sponsor_Posts::term_of( $S ) . '">This sponsor&#039;s posts in wp-admin</a>' ), true );
WPCPM_Sponsor_Posts::set_posting( $S, false, 1 );
$html = card( $S, 20 );
ck( 'with posting off the member reads one sentence instead of Write a post, and no wp-admin link', array( false !== strpos( $html, 'The program has not enabled posting for this sponsor.' ), strpos( $html, 'post-new.php' ), strpos( $html, 'edit.php' ) ), array( true, false, false ) );
WPCPM_Sponsor_Posts::set_posting( $S, true, 1 );
ck( 'an empty card says so in the page\'s own words', false !== strpos( card( $S2, 1 ), '<p class="wpcpm-student__note">No posts yet.</p>' ), true );
$html = card( $S, 20, 'posts' );
ck( 'the card opens when the flash names it', false !== strpos( $html, '<details id="wpcpm-sponsor-posts" class="wpcpm-group wpcpm-group__disclosure" open>' ), true );

echo "\n=== Publish and return ===\n";
function press( $uid, $post ) { $GLOBALS['uid'] = $uid; $_POST = $post; try { if ( isset( $post['wpcpm_action'] ) && 'return' === $post['wpcpm_action'] ) { WPCPM_Sponsor_Posts::handle_return(); } else { WPCPM_Sponsor_Posts::handle_publish(); } } catch ( WPCPM_Test_Redirect $e ) { return $e->getMessage(); } return 'no redirect'; }
$GLOBALS['audit'] = array();
ck( 'a member cannot publish: the one refusal, at the card', press( 20, array( 'wpcpm_post' => $pid ) ), 'refused|posts||' );
ck( 'a manager cannot publish a draft', press( 1, array( 'wpcpm_post' => $draft ) ), 'post-not-pending|posts|' . $S . '|' );
reset_calls();
ck( 'a manager publishes the pending post, and both dates carry the moment it went live', array( press( 1, array( 'wpcpm_post' => $pid ) ), get_post( $pid )->post_status, get_post( $pid )->post_date, get_post( $pid )->post_date_gmt, calls( 'nonce' )[0][1], end( $GLOBALS['audit'] )['kind'] ), array( 'post-published|posts|' . $S . '|', 'publish', $GLOBALS['now'], $GLOBALS['now'], 'wpcpm_sponsor_post_publish_' . $pid, 'post_published' ) );
ck( 'and the update asks for edit_date, without which core keeps the dates it had', calls( 'wp_update_post' )[0][2]['edit_date'], true );
$pending2 = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_title' => 'Second guide' ) );
$GLOBALS['pmeta'][ $pending2 ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $S;
ck( 'returning without a note is refused with its own sentence', press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $pending2, 'wpcpm_note' => '  ' ) ), 'post-note-missing|posts|' . $S . '|' );
$GLOBALS['mail'] = array();
reset_calls();
ck( 'returning with a note drafts the post, keeps the note, mails the author once', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $pending2, 'wpcpm_note' => 'Shorter title, please.' ) ), get_post( $pending2 )->post_status, get_post_meta( $pending2, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ), count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['context'], false !== strpos( $GLOBALS['mail'][0]['mail']['body'], 'Shorter title, please.' ) ), array( 'post-returned|posts|' . $S . '|', 'draft', 'Shorter title, please.', 1, 20, 'sponsor-post-returned', true ) );
$last_audit = end( $GLOBALS['audit'] );
ck( 'the return audit row records the note\'s length, never the note', array( $last_audit['kind'], $last_audit['data']['note_length'], strpos( wp_json_encode( $last_audit ), 'Shorter title' ) ), array( 'post_returned', mb_strlen( 'Shorter title, please.' ), false ) );
ck( 'the Return nonce is keyed to the post with its own action', in_array( array( 'nonce', 'wpcpm_sponsor_post_return_' . $pending2 ), $GLOBALS['calls'], true ), true );
ck( 'publishing clears an earlier return note', array( press( 1, array( 'wpcpm_post' => $draft ) ), get_post_meta( $draft, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ) ), array( 'post-not-pending|posts|' . $S . '|', 'Please add a screenshot.' ) );
$GLOBALS['posts'][ $draft ]->post_status = 'pending';
press( 1, array( 'wpcpm_post' => $draft ) );
ck( 'once pending it publishes and the note is gone', array( get_post( $draft )->post_status, get_post_meta( $draft, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ) ), array( 'publish', '' ) );
$stranger = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 1, 'post_title' => 'A program post' ) );
ck( 'a post with no sponsor stamp is not this handler\'s to publish', press( 1, array( 'wpcpm_post' => $stranger ) ), 'refused|posts||' );
ck( 'every outcome has a sentence', array_keys( WPCPM_Sponsor_Posts::messages() ), array( 'post-published', 'post-returned', 'post-note-missing', 'post-not-pending', 'post-failed' ) );

echo "\n=== The posting switch (wp-admin) ===\n";
function flags( $uid, $post ) { $GLOBALS['uid'] = $uid; $_POST = $post; try { WPCPM_Sponsor_Posts::handle_flags(); } catch ( WPCPM_Test_Redirect $e ) { return $e->getMessage(); } return 'no redirect'; }
$GLOBALS['flash'] = array();
ck( 'a member cannot switch the flag', flags( 20, array( 'wpcpm_sponsor' => $S, 'wpcpm_on' => '0' ) ), 'die:403' );
ck( 'a manager switches it off and is sent back with the flash', array( flags( 1, array( 'wpcpm_sponsor' => $S, 'wpcpm_on' => '0' ) ), WPCPM_Sponsor_Posts::posting_enabled( $S ), end( $GLOBALS['flash'] ) ), array( 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors', false, array( 'sponsors_admin', 'posting-off' ) ) );
flags( 1, array( 'wpcpm_sponsor' => $S, 'wpcpm_on' => '1' ) );
ck( 'and on again', array( WPCPM_Sponsor_Posts::posting_enabled( $S ), end( $GLOBALS['flash'] )[1] ), array( true, 'posting-on' ) );
ck( 'a manager naming a record the index does not hold is refused', flags( 1, array( 'wpcpm_sponsor' => 'recNOPE0000000001', 'wpcpm_on' => '0' ) ), 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors' );
ck( 'with the one sentence', end( $GLOBALS['flash'] )[1], 'refused' );
ck( 'and no flag is left behind for it', get_option( 'wpcpm_sponsor_flags_recNOPE0000000001', 'none' ), 'none' );

echo "\n=== Guides under an offer ===\n";
function guides( $record, $uid ) { $GLOBALS['uid'] = $uid; ob_start(); WPCPM_Sponsor_Posts::render_tools( $record, wp_get_current_user(), 'TEST Sponsor' ); return ob_get_clean(); }
$GLOBALS['users'][30] = new WP_User( 30, array( 'wpcpm_student' ), 'Student Reader' );
ck( 'a sponsor with no published post prints nothing', guides( $S2, 30 ), '' );
$g = guides( $S, 30 );
ck( 'published guides are listed with the lead naming the company and each title linked', array( 0 === strpos( $g, '<div class="wpcpm-tools__posts"><p class="wpcpm-tools__posts-lead">Guides from TEST Sponsor</p><ul class="wpcpm-tools__posts-list">' ), substr_count( $g, '<li><a href="https://example.test/?p=' ), false !== strpos( $g, '>Ten tips</a>' ) ), array( true, 2, true ) );
$GLOBALS['refuse_view'] = true;
ck( 'a reader the level refuses gets nothing', guides( $S, 30 ), '' );
$GLOBALS['refuse_view'] = false;
for ( $i = 0; $i < 6; $i++ ) { $extra = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 20, 'post_title' => 'Guide ' . $i ) ); $GLOBALS['pmeta'][ $extra ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $S; }
ck( 'at most five guides are listed', substr_count( guides( $S, 30 ), '<li>' ), 5 );

echo "\n=== The byline ===\n";
$GLOBALS['post'] = get_post( $pid );
ck( 'a sponsor post carries the company, not the person', WPCPM_Sponsor_Posts::filter_author_name( 'Member One' ), 'TEST Sponsor' );
$GLOBALS['post'] = get_post( $stranger );
ck( 'any other post keeps its author', WPCPM_Sponsor_Posts::filter_author_name( 'Manager' ), 'Manager' );
$GLOBALS['post'] = get_post( $pid );
ck( 'another person named on a sponsor post keeps their name', WPCPM_Sponsor_Posts::filter_author_name( 'Manager', 1 ), 'Manager' );
ck( 'the author named by id gets the company', WPCPM_Sponsor_Posts::filter_author_name( 'Member One', 20 ), 'TEST Sponsor' );
$GLOBALS['is_admin'] = true;
ck( 'in wp-admin the person\'s name stays, so a manager sees who submitted', WPCPM_Sponsor_Posts::filter_author_name( 'Member One', 20 ), 'Member One' );
$GLOBALS['is_admin'] = false;
ck( 'oEmbed carries the company and its site for a sponsor post', WPCPM_Sponsor_Posts::filter_oembed( array( 'author_name' => 'Member One', 'author_url' => 'https://example.test/author/memberone/' ), get_post( $pid ) ), array( 'author_name' => 'TEST Sponsor', 'author_url' => 'https://sponsor.example' ) );
ck( 'and leaves another post alone', WPCPM_Sponsor_Posts::filter_oembed( array( 'author_name' => 'Manager' ), get_post( $stranger ) ), array( 'author_name' => 'Manager' ) );
ck( 'and passes a refusal through', WPCPM_Sponsor_Posts::filter_oembed( false, get_post( $pid ) ), false );
$GLOBALS['post']     = get_post( $pid );
$GLOBALS['singular'] = true;
$banner = WPCPM_Sponsor_Posts::filter_content( '<p>Body</p>' );
ck( 'the banner opens the content with the company and its site', array( 0 === strpos( $banner, '<aside class="wpcpm-sponsor-byline">' ), false !== strpos( $banner, 'href="https://sponsor.example"' ), false !== strpos( $banner, 'A guide from <a' ), false !== strpos( $banner, 'a sponsor of the WordPress Credits program' ), substr( $banner, -11 ) ), array( true, true, true, true, '<p>Body</p>' ) );
$GLOBALS['logo'] = array( 'url' => 'https://example.test/logo.png' );
$GLOBALS['post'] = get_post( $pid );
$GLOBALS['singular'] = true;
ck( 'the banner shows the logo when the sponsor has one, an initial otherwise', array( false !== strpos( WPCPM_Sponsor_Posts::filter_content( '' ), '<span class="wpcpm-sponsor-byline__logo"><img src="https://example.test/logo.png"' ), false !== strpos( $banner, '<span class="wpcpm-sponsor-byline__initials" aria-hidden="true">T</span>' ) ), array( true, true ) );
$GLOBALS['logo'] = array();
$GLOBALS['singular']    = true;
$GLOBALS['refuse_view'] = true;
ck( 'no banner for a reader the level refuses', WPCPM_Sponsor_Posts::filter_content( '<p>Notice</p>' ), '<p>Notice</p>' );
$GLOBALS['refuse_view'] = false;
$GLOBALS['singular']    = false;
ck( 'no banner off the single post', WPCPM_Sponsor_Posts::filter_content( '<p>Body</p>' ), '<p>Body</p>' );

echo "\n=== The accounts attached before 1.95.0 ===\n";
$GLOBALS['users'][22] = new WP_User( 22, array( 'wpcpm_sponsor' ), 'Early Member' );
WPCPM_Sponsor_Members::attach( 22, $S, 'manager', 1 );
$GLOBALS['users'][22]->caps = array();
delete_option( WPCPM_Sponsor_Posts::OPT_APPLIED );
WPCPM_Sponsor_Posts::maybe_apply_caps();
ck( 'the one-time pass gives an account from before the class its three caps and marks itself done', array( $GLOBALS['users'][22]->caps, get_option( WPCPM_Sponsor_Posts::OPT_APPLIED ) ), array( array( 'edit_posts' => true, 'delete_posts' => true, 'upload_files' => true ), '1' ) );
$GLOBALS['users'][22]->caps = array();
WPCPM_Sponsor_Posts::maybe_apply_caps();
ck( 'and never runs twice', $GLOBALS['users'][22]->caps, array() );
WPCPM_Sponsor_Posts::apply_caps( 22, $S );
WPCPM_Sponsor_Posts::uninstall_accounts();
ck( 'uninstall takes the three caps off every live account', array( $GLOBALS['users'][20]->caps, $GLOBALS['users'][22]->caps ), array( array(), array() ) );
WPCPM_Sponsor_Posts::delete_all();
ck( 'and delete_all() forgets the pass ran', get_option( WPCPM_Sponsor_Posts::OPT_APPLIED, 'gone' ), 'gone' );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-posts.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the caps are never put on a role', preg_match( '/get_role\(|add_role\(/', $src ), 0 );
ck( 'publish_posts, edit_others_posts and edit_published_posts appear nowhere in the class', preg_match( '/publish_posts|edit_others_posts|edit_published_posts/', $src ), 0 );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
