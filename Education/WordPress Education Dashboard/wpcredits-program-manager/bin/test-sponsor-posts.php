<?php
/**
 * WPCPM_Sponsor_Posts: the posting flag and the three capabilities, the two category terms,
 * the editor fence, the Posts card, publish and return, the byline (Sponsors module, S3), and
 * the three WPCPM_Content_Access surfaces that gate a sponsor post at the Students and mentors
 * level: the real class is loaded here rather than stood in for, because each of those three
 * could be made a no-op with the whole battery green. Publish, Return and the posting switch each
 * check the nonce keyed to what they act on, asserted by name, and a stale switch dies before the
 * flag moves: they read their form through `WPCPM_Request`, where the standard's nonce sniff
 * cannot see a nonce gone. With the tiers live, a Friend or a Supporter, whose level includes no
 * post, has the Posts card only while it has a post of its own the card lists (1.123.22), the
 * level's sentence and the way into wp-admin with it; with none, or one in the trash alone, the card
 * is not drawn, for its members and for an Administrator alike, and from Backer up and while the
 * switch is off the card is drawn as before.
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
$GLOBALS['kept_typing'] = array();
$GLOBALS['index']     = array();
$GLOBALS['grants']    = array();
$GLOBALS['settings']  = array();
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
/** Enough of the REST response for WPCPM_Content_Access::filter_rest(). */
class WP_REST_Response {
	private $data;
	public function __construct( array $data = array() ) { $this->data = $data; }
	public function get_data() { return $this->data; }
	public function set_data( $data ) { $this->data = $data; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_textarea( $s ) { return esc_html( $s ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
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
function get_permalink( $p = null ) { $p = get_post( $p ); return $p ? 'https://example.test/?p=' . $p->ID : ''; }
function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( (string) $to ); }
function get_queried_object() { return isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null; }
function get_preview_post_link( $p ) { $p = get_post( $p ); return $p ? 'https://example.test/?p=' . $p->ID . '&preview=true' : ''; }
function get_edit_post_link( $id, $c = 'display' ) { return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit'; }
function get_the_date( $f, $p ) { return '2026-09-06'; }
function get_post_time( $f = 'U', $gmt = false, $p = null, $translate = false ) { return 1788600000; }
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
// The editor fence's clamp reads a post's status as it stands before this
// save, to tell an edit of something already in flight from a brand new submission.
function get_post_status( $p = null ) { $p = get_post( $p ); return $p instanceof WP_Post ? $p->post_status : false; }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : ''; }
// As core's: what is written is unslashed first, at any depth, so words reach it as slashed copies.
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); $GLOBALS['calls'][] = array( 'update_post_meta', (int) $id, $k, $v ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function wp_insert_post( $a, $e = false ) { $id = $GLOBALS['next_post']++; $a['ID'] = $id; $GLOBALS['posts'][ $id ] = new WP_Post( $a ); return $id; }
// `edit_date` is an instruction to wp_update_post, never a column: core reads it and drops it.
function wp_update_post( $a, $e = false ) { $id = (int) $a['ID']; if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; } foreach ( $a as $k => $v ) { if ( 'edit_date' !== $k ) { $GLOBALS['posts'][ $id ]->$k = $v; } } $GLOBALS['calls'][] = array( 'wp_update_post', $id, $a ); return $id; }
// $GLOBALS['publish_fails']: a wake() check leaves the status untouched, the way a failed $wpdb->update() would, without wp_publish_post() itself answering anything to say so.
function wp_publish_post( $p ) { $id = $p instanceof WP_Post ? $p->ID : (int) $p; if ( empty( $GLOBALS['publish_fails'] ) ) { $GLOBALS['posts'][ $id ]->post_status = 'publish'; } $GLOBALS['calls'][] = array( 'wp_publish_post', $id ); }
function wp_is_post_revision( $p ) { return false; }
function wp_is_post_autosave( $p ) { return false; }
function get_posts( array $args ) {
	$GLOBALS['last_get_posts'] = $args;
	$out = array();
	$statuses = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
	foreach ( $GLOBALS['posts'] as $p ) {
		if ( 'post' !== $p->post_type || ! in_array( $p->post_status, $statuses, true ) ) { continue; }
		if ( isset( $args['meta_key'] ) ) { $have = (string) get_post_meta( $p->ID, $args['meta_key'], true ); if ( isset( $args['meta_compare'] ) && 'EXISTS' === $args['meta_compare'] ) { if ( '' === $have ) { continue; } } elseif ( $have !== (string) ( $args['meta_value'] ?? '' ) ) { continue; } }
		// pending_all()'s own EXISTS / NOT EXISTS pair, relation AND.
		// Anything else is a fatal with the clause printed, so a query this stand-in does not
		// model is never read as a silent pass.
		if ( isset( $args['meta_query'] ) ) {
			$excluded = false;
			foreach ( $args['meta_query'] as $mq_key => $clause ) {
				if ( 'relation' === $mq_key ) {
					if ( 'AND' !== $clause ) { die( 'get_posts: unmodeled meta_query relation ' . var_export( $clause, true ) ); }
					continue;
				}
				if ( ! is_array( $clause ) || ! isset( $clause['key'], $clause['compare'] ) || ! in_array( $clause['compare'], array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
					die( 'get_posts: unmodeled meta_query clause ' . var_export( $clause, true ) );
				}
				$has = '' !== (string) get_post_meta( $p->ID, $clause['key'], true );
				if ( ( 'EXISTS' === $clause['compare'] && ! $has ) || ( 'NOT EXISTS' === $clause['compare'] && $has ) ) { $excluded = true; }
			}
			if ( $excluded ) { continue; }
		}
		$out[] = $p;
	}
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) { $out = array_slice( $out, 0, (int) $args['numberposts'] ); }
	// clear_dormant_stamps() asks for ids alone, the way every other suite's get_posts() already answers it.
	return ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? array_map( static function ( $p ) { return (int) $p->ID; }, $out ) : $out;
}
// Terms: one flat table keyed by id, with slug and parent, the way core answers.
function term_exists( $slug, $tax = '', $parent = null ) { foreach ( $GLOBALS['terms'] as $id => $t ) { if ( $t['slug'] === $slug ) { return array( 'term_id' => $id, 'term_taxonomy_id' => $id ); } } return null; }
function get_term_by( $f, $v, $tax = 'category' ) { foreach ( $GLOBALS['terms'] as $id => $t ) { if ( ( 'slug' === $f && $t['slug'] === $v ) || ( 'id' === $f && $id === (int) $v ) ) { return (object) array_merge( array( 'term_id' => $id ), $t ); } } return false; }
function get_term( $id, $tax = 'category' ) { return get_term_by( 'id', (int) $id, $tax ); }
// The name unslashed, as core's insert and update unslash it (and the description, which no call
// here passes); the call log keeps what was handed over.
function wp_insert_term( $name, $tax, $args = array() ) {
	$slug = isset( $args['slug'] ) ? $args['slug'] : sanitize_title( stripslashes( $name ) );
	if ( term_exists( $slug ) ) { return new WP_Error( 'term_exists', 'A term with the name provided already exists.', term_exists( $slug )['term_id'] ); }
	$id = $GLOBALS['next_term']++;
	$GLOBALS['terms'][ $id ] = array( 'name' => stripslashes( $name ), 'slug' => $slug, 'parent' => isset( $args['parent'] ) ? (int) $args['parent'] : 0 );
	$GLOBALS['calls'][] = array( 'wp_insert_term', $name, $slug );
	return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
}
function wp_update_term( $id, $tax, $args = array() ) { foreach ( $args as $k => $v ) { $GLOBALS['terms'][ (int) $id ][ $k ] = in_array( $k, array( 'name', 'description' ), true ) ? stripslashes( (string) $v ) : $v; } $GLOBALS['calls'][] = array( 'wp_update_term', (int) $id, $args ); return array( 'term_id' => (int) $id ); }
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
// A nonce that fails on demand, one the request carries for another action, dies with WordPress's
// own sentence, not through wp_die() below, whose answer keeps only the status and so would read as
// the capability's refusal; what a handler reached before it dies is what that nonce failed to guard.
function check_admin_referer( $a ) { $GLOBALS['calls'][] = array( 'nonce', $a ); if ( ! empty( $GLOBALS['nonce_fails'] ) ) { throw new WPCPM_Test_Redirect( 'The link you followed has expired.' ); } return true; }
function wp_safe_redirect( $u ) { throw new WPCPM_Test_Redirect( $u ); }
function wp_die( $m, $t = '', $a = array() ) { throw new WPCPM_Test_Redirect( 'die:' . ( is_int( $t ) ? $t : ( isset( $a['response'] ) ? (int) $a['response'] : 0 ) ) ); }

class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator';
	const CAP_VIEW_STUDENT = 'wpcpm_view_student_content'; const CAP_VIEW_MENTOR = 'wpcpm_view_mentor_content'; const CAP_VIEW_INSTITUTION = 'wpcpm_view_institution_content'; const CAP_VIEW_SPONSOR = 'wpcpm_view_sponsor_content'; const CAP_MANAGE = 'wpcpm_manage_program';
	/** The real class's own list, which WPCPM_Content_Access::levels() reads. */
	public static function custom_roles() {
		return array(
			self::ROLE_STUDENT     => array( 'label' => 'Student', 'cap' => self::CAP_VIEW_STUDENT ),
			self::ROLE_MENTOR      => array( 'label' => 'Mentor', 'cap' => self::CAP_VIEW_MENTOR ),
			self::ROLE_INSTITUTION => array( 'label' => 'Institution', 'cap' => self::CAP_VIEW_INSTITUTION ),
			self::ROLE_SPONSOR     => array( 'label' => 'Sponsor', 'cap' => self::CAP_VIEW_SPONSOR ),
		);
	}
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
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
	public static function is_key( $v ) { return 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', (string) $v ); }
	public static function is_test( $sponsor ) { $row = is_array( $sponsor ) ? $sponsor : self::row( (string) $sponsor ); return is_array( $row ) && ! empty( $row['test'] ); }
	public static function test_keys() { return array_keys( array_filter( $GLOBALS['index'], static function ( $row ) { return ! empty( $row['test'] ); } ) ); }
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}
class WPCPM_Field_Value { public static function clean_url( $u ) { return preg_match( '#^https?://#i', (string) $u ) ? (string) $u : ''; } }
class WPCPM_Mail { public static function send( $to, $context, $build ) { $u = $to instanceof WP_User ? $to : get_user_by( 'id', (int) $to ); $GLOBALS['mail'][] = array( 'to' => $u ? $u->ID : 0, 'context' => $context, 'mail' => call_user_func( $build, $u ) ); return true; } }
class WPCPM_Request {
	public static function posted_text( $k ) { return isset( $_POST[ $k ] ) ? sanitize_text_field( $_POST[ $k ] ) : ''; }
	public static function posted_key( $k ) { return isset( $_POST[ $k ] ) ? sanitize_key( $_POST[ $k ] ) : ''; }
	public static function posted_id( $k ) { return isset( $_POST[ $k ] ) ? absint( $_POST[ $k ] ) : 0; }
	public static function posted_raw( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? (string) wp_unslash( $_POST[ $n ] ) : $f; }
}
/**
 * The flash: each outcome a press leaves is journaled in the order it was set, and a refused
 * form's typing waits per user and channel, as user meta keeps it, until `take()` hands it back
 * once and clears it, or `sweep()` clears it unread. bin/test-flash.php pins the real class.
 */
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) {
		if ( 0 === strpos( (string) $channel, WPCPM_Typed_Text::KEPT_CHANNEL ) ) {
			$GLOBALS['kept_typing'][ (int) ( $user_id ? $user_id : $GLOBALS['uid'] ) ][ (string) $channel ] = $value;
			return;
		}
		$GLOBALS['flash'][] = array( $channel, $value );
	}
	public static function take( $channel, $user_id = 0 ) {
		$uid   = (int) ( $user_id ? $user_id : $GLOBALS['uid'] );
		$value = $GLOBALS['kept_typing'][ $uid ][ (string) $channel ] ?? '';
		unset( $GLOBALS['kept_typing'][ $uid ][ (string) $channel ] );
		return $value;
	}
	public static function sweep( $stale, $user_id = 0 ) {
		$uid = (int) ( $user_id ? $user_id : $GLOBALS['uid'] );
		foreach ( $GLOBALS['kept_typing'][ $uid ] ?? array() as $channel => $value ) {
			if ( $stale( (string) $channel, $value ) ) {
				unset( $GLOBALS['kept_typing'][ $uid ][ $channel ] );
			}
		}
	}
}
class WPCPM_Return {
	const FIELD = 'wpcpm_return'; const DASHBOARD = 'dashboard';
	public static function field( $where, $anchor = '' ) { if ( self::DASHBOARD === $where ) { echo '<input type="hidden" name="wpcpm_return" value="dashboard" /><input type="hidden" name="wpcpm_return_to" value="' . $anchor . '" />'; } }
	public static function url( $default ) { return ( isset( $_POST['wpcpm_return'] ) && 'dashboard' === $_POST['wpcpm_return'] ) ? 'https://example.test/administrator-dashboard/#wpcpm-sponsor-posts' : $default; }
}
/**
 * The Sponsors module, as far as this class reaches it: its flash channel, and the address of one
 * sponsor's accounts on its screen, where the posting switch is drawn and comes back to. The module's
 * own builder of that address is pinned by bin/test-sponsors-screen.php.
 */
class WPCPM_Sponsors {
	const FLASH = 'sponsors_admin';
	public static function accounts_view_url( $record ) { return admin_url( 'admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_sponsor=' . rawurlencode( (string) $record ) ); }
}
class WPCPM_Institutions { const FLASH = 'institutions'; }
// The tier ladder's one setting read here: the launch switch. The real WPCPM_Settings has more;
// this suite asks nothing else of it.
class WPCPM_Settings { public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function leave( $status, $card, $record = '', $detail = '' ) { throw new WPCPM_Test_Redirect( $status . '|' . $card . '|' . $record . '|' . $detail ); }
}
class WPCPM_Refusal_Meter { public static function is_locked( $scope, $user ) { return false; } public static function refuse( $scope, $user ) { $GLOBALS['metered'][] = $scope; return 0; } }

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
// The real gate, not a stand-in: three of its five surfaces could be made no-ops with the whole
// battery green (FSUIT-3), and a stand-in whose can_view() answers a flag is exactly what hid
// them. Everything below that asks "what does a reader the level refuses see" now says so by
// taking the marker capability away.
require_once __DIR__ . '/../includes/class-wpcpm-content-access.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
// The tier ladder (1.112.0): the post cap, Write a post's own sentence, and dormant posts all
// read it now.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
// The shell the Posts card is drawn in.
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
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
// Core unslashes a term's name, and the company's name is the base's, read unslashed: the
// category keeps a backslash only when the name is handed over as a slashed copy.
$S3                      = 'recSPN00000000003';
$typed_company           = 'Gadgets C:\drafts, two \\\\ in a row, "Quoted"';
$typed_renamed           = 'Gadgets C:\drafts\later, two more \\\\, "Renamed"';
$GLOBALS['index'][ $S3 ] = array( 'record_id' => $S3, 'name' => $typed_company, 'website' => '', 'status' => 'Approved' );
$child3                  = WPCPM_Sponsor_Posts::ensure_terms( $S3 );
ck( 'a company\'s category is named exactly as the base names the company', $child3 > 0 ? get_term( $child3 )->name : null, $typed_company );
WPCPM_Sponsor_Posts::rename_terms( array( $S3 => array( 'name' => $typed_company ) ), array( $S3 => array( 'name' => $typed_renamed ) ) );
ck( 'and renamed exactly as the base renames it', $child3 > 0 ? get_term( $child3 )->name : null, $typed_renamed );

echo "\n=== The fence ===\n";
reset_calls();
WPCPM_Sponsor_Posts::init();
$hooked = array_map( static function ( $c ) { return $c[1]; }, array_merge( calls( 'add_filter' ), calls( 'add_action' ) ) );
sort( $hooked );
ck( 'init() hooks the fence for everyone, and decides per call', $hooked, array( 'add_meta_boxes', 'admin_bar_menu', 'admin_menu', 'admin_post_wpcpm_sponsor_flags', 'admin_post_wpcpm_sponsor_post_publish', 'admin_post_wpcpm_sponsor_post_return', 'ajax_query_attachments_args', 'disable_categories_dropdown', 'get_terms_args', 'get_the_author_display_name', 'init', 'jetpack_subscriptions_exclude_these_categories', 'oembed_response_data', 'old_slug_redirect_post_id', 'posts_results', 'pre_get_posts', 'pre_get_posts', 'pre_redirect_guess_404_permalink', 'redirect_canonical', 'rest_after_insert_post', 'rest_attachment_query', 'rest_category_query', 'rest_request_before_callbacks', 'save_post_post', 'set_user_role', 'the_author', 'the_content', 'upload_mimes', 'wp_insert_post_data', 'wp_prepare_attachment_for_js' ) );
ck( 'the hooks that need two or more arguments ask for them', array( in_array( array( 'add_action', 'save_post_post', 20, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'wp_insert_post_data', 10, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'get_the_author_display_name', 10, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'the_content', 6, 1 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'oembed_response_data', 20, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'posts_results', 10, 2 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'rest_request_before_callbacks', 10, 3 ), $GLOBALS['calls'], true ), in_array( array( 'add_filter', 'redirect_canonical', 10, 2 ), $GLOBALS['calls'], true ) ), array( true, true, true, true, true, true, true, true ) );
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
$GLOBALS['pmeta'][ $draft ][ WPCPM_Content_Access::META_KEY ] = WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS;
$GLOBALS['pmeta'][ $draft ][ WPCPM_Sponsor_Posts::META_RETURN_NOTE ] = 'Please add a screenshot.';
$GLOBALS['pmeta'][ $draft ][ WPCPM_Sponsor_Posts::META_RETURNED ] = 1788500000;
$html = card( $S, 20 );
ck( 'the member sees the card with its count, Write a post, and each post with its state', array(
	false !== strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-posts" class="wpcpm-group wpcpm-group__disclosure">' ),
	false !== strpos( $html, '<span class="wpcpm-group__count">2</span>' ),
	false !== strpos( $html, 'href="https://example.test/wp-admin/post-new.php"' ),
	false !== strpos( $html, '<li class="wpcpm-updates__item wpcpm-posts__item--pending"' ),
	false !== strpos( $html, '<li class="wpcpm-updates__item wpcpm-posts__item--returned"' ),
	false !== strpos( $html, '<p class="wpcpm-posts__note">Please add a screenshot.</p>' ),
	false === strpos( $html, 'wpcpm_sponsor_post_publish' ),
), array( true, true, true, true, true, true, true ) );
ck( 'the list is the Updates list: its classes, the title as the link, the state and date on one muted line', array( substr_count( $html, '<ul class="wpcpm-updates wpcpm-updates--posts">' ), false !== strpos( $html, '<a class="wpcpm-updates__link" href="https://example.test/?p=' . $pid . '">Ten tips</a><span class="wpcpm-updates__date">Waiting for review &middot; 2026-09-06</span>' ) || false !== strpos( $html, '<span class="wpcpm-updates__link">Ten tips</span><span class="wpcpm-updates__date">Waiting for review &middot; 2026-09-06</span>' ) ), array( 1, true ) );
ck( 'the card\'s query stands outside the gate by name and suppresses nothing else', array( $GLOBALS['last_get_posts'][ WPCPM_Content_Access::QUERY_UNGATED ], $GLOBALS['last_get_posts']['suppress_filters'] ), array( true, false ) );
$html = card( $S, 1 );
ck( 'a manager sees the same list with no controls on it and no Write a post: publishing happens in wp-admin', array( substr_count( $html, 'wpcpm_sponsor_post_publish' ), substr_count( $html, 'wpcpm_sponsor_post_return' ), strpos( $html, 'post-new.php' ), strpos( $html, 'Guides and stories' ) ), array( 0, 0, false, false ) );
$html = card( $S, 20 );
ck( 'a member with posting on also has the way into wp-admin\'s Posts screen beside Write a post', false !== strpos( $html, '<a class="wpcpm-button" href="https://example.test/wp-admin/post-new.php">Write a post</a> <a class="wpcpm-posts__admin" href="https://example.test/wp-admin/edit.php">Your posts in wp-admin</a>' ), true );
$html = card( $S, 1 );
ck( 'a manager has the Posts screen filtered to the sponsor\'s category', false !== strpos( $html, '<a class="wpcpm-posts__admin" href="https://example.test/wp-admin/edit.php?cat=' . WPCPM_Sponsor_Posts::term_of( $S ) . '">This sponsor&#039;s posts in wp-admin</a>' ), true );
WPCPM_Sponsor_Posts::set_posting( $S, false, 1 );
$html = card( $S, 20 );
ck( 'with posting off the member reads one sentence instead of Write a post, and no wp-admin link', array( false !== strpos( $html, 'The program has not enabled posting for this sponsor.' ), strpos( $html, 'post-new.php' ), strpos( $html, 'edit.php' ) ), array( true, false, false ) );
$html = card( $S, 1 );
ck( 'and a manager reads that posting is off and where it is switched on: Manage accounts on the Sponsors screen\'s Accounts tab', false !== strpos( $html, '<p class="wpcpm-student__note">Posting is off for this sponsor. Switch it on from Manage accounts on the Sponsors screen&#039;s Accounts tab.</p>' ), true );
WPCPM_Sponsor_Posts::set_posting( $S, true, 1 );
ck( 'an empty card says so in the page\'s own words', false !== strpos( card( $S2, 1 ), '<p class="wpcpm-student__note">No posts yet.</p>' ), true );
$html = card( $S, 20, 'posts' );
ck( 'the card opens when the flash names it', false !== strpos( $html, '<details id="wpcpm-sponsor-posts" class="wpcpm-group wpcpm-group__disclosure" open>' ), true );
// The action is printed whatever opened the card, and the stylesheets hide it while the card is open:
// open, the card shows its own Write a post button, and the one after the fold waits for the card to
// be folded.
ck( 'and opened by it, the card shows its own Write a post and still prints the one after its fold, which the stylesheets hide while the card is open', array( substr_count( $html, 'wpcpm-sponsor__row-action' ), substr_count( $html, 'href="https://example.test/wp-admin/post-new.php"' ) ), array( 1, 2 ) );
// Folded otherwise, its row how many posts are published and how many wait for review, read by each
// post's own state, and Write a post after the fold, the same address as the button inside,
// wherever the button inside is drawn: for a member, with posting on and room in the tier (the
// design the owner approved on 27 September 2026), naming for a screen reader what it opens. The
// card's first line says where a published post appears, which the group's lead no longer says.
$write_row = '</div></details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-admin/post-new.php">Write a post<span class="screen-reader-text"> in the WordPress editor</span></a></p></section>';
$member    = card( $S, 20 );
$manager   = card( $S, 1 );
WPCPM_Sponsor_Posts::set_posting( $S, false, 1 );
$off       = card( $S, 20 );
WPCPM_Sponsor_Posts::set_posting( $S, true, 1 );
ck( 'the row: folded, the published and the waiting counted, Write a post after the fold for a member who may write, and none for a manager or with posting off; the first line says where a published post appears', array(
	0 === strpos( $member, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-posts" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Posts <span class="wpcpm-group__count">2</span></h3><span class="wpcpm-sponsor__state">0 published, 1 waiting</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	substr( $member, -strlen( $write_row ) ) === $write_row,
	array( substr_count( $manager, 'wpcpm-sponsor__row-action' ), substr_count( $off, 'wpcpm-sponsor__row-action' ), substr( $manager, -strlen( '</div></details></section>' ) ) ),
	false !== strpos( $manager, '<span class="wpcpm-sponsor__state">0 published, 1 waiting</span>' ),
	false !== strpos( $member, '<div class="wpcpm-group__body"><p class="wpcpm-student__note">A post appears under your offer in Tools from our sponsors, on the Student Report Card and the Mentor Report Card, once an Administrator publishes it.</p>' ),
	false !== strpos( $manager, '<div class="wpcpm-group__body"><p class="wpcpm-student__note">A post appears under your offer in Tools from our sponsors' ),
), array( true, true, array( 0, 0, '</div></details></section>' ), true, true, true ) );

echo "\n=== The tier ladder: the post cap, Write a post, and dormant posts ===\n";
// Fresh sponsors throughout, never $S or $S2: those two carry posts the rest of this file
// depends on, and post_count() would count them against a cap they were never meant to test.
$T1 = 'recTIER0000000001';
$GLOBALS['index'][ $T1 ] = array( 'record_id' => $T1, 'name' => 'TEST Mentor Tier Sponsor', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::MENTOR );
$GLOBALS['users'][60] = new WP_User( 60, array( 'wpcpm_sponsor' ), 'Mentor Tier Member' );
WPCPM_Sponsor_Members::attach( 60, $T1, 'manager', 1 );
$html = card( $T1, 60 );
ck( 'with the switch off Write a post is drawn as before, whatever the tier', false !== strpos( $html, 'href="https://example.test/wp-admin/post-new.php"' ), true );

$GLOBALS['settings']['sponsor_tiers_live'] = true;
$T0 = 'recTIER0000000010';
$GLOBALS['index'][ $T0 ] = array( 'record_id' => $T0, 'name' => 'TEST Supporter Tier Sponsor', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::SUPPORTER );
$GLOBALS['users'][59] = new WP_User( 59, array( 'wpcpm_sponsor' ), 'Supporter Tier Member' );
WPCPM_Sponsor_Members::attach( 59, $T0, 'manager', 1 );
// Below Backer a level includes no post (1.123.22): with none of its own, a Friend's or a Supporter's
// card would only say so over an empty list and a link to a screen with nothing on it, so there is
// no card, for its member and for an Administrator through the switcher alike; and a post in the
// trash is none of its own.
$t1_trash = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'trash', 'post_author' => 60, 'post_title' => 'Thrown away' ) );
$GLOBALS['pmeta'][ $t1_trash ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T1;
ck( 'with the switch on, a Friend or a Supporter with no post of its own has no Posts card: nothing for its member or an Administrator, a post in the trash counting for none', array( card( $T1, 60 ), card( $T1, 1 ), card( $T0, 59 ), card( $T0, 1 ) ), array( '', '', '', '' ) );
// The card's own test, which the Sponsor Dashboard's What you offer line asks too (1.123.22).
$draws_seen = array( WPCPM_Sponsor_Posts::draws( $T1 ), WPCPM_Sponsor_Posts::draws( $T0 ) );
// One of its own, from before the levels or returned or held back since, and the card is there: the
// level's sentence where Write a post would be, naming the level posts start at rather than "the next
// tier", since a Friend's next, Supporter, includes no posts either; and the way into wp-admin, where
// a member reads its posts, edits a draft or a returned one and saves it as a draft, and deletes one.
$t1_returned = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => 60, 'post_title' => 'Returned before the levels' ) );
$GLOBALS['pmeta'][ $t1_returned ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T1;
$GLOBALS['pmeta'][ $t1_returned ][ WPCPM_Sponsor_Posts::META_RETURN_NOTE ]   = 'Please shorten the title.';
$html = card( $T1, 60 );
$draws_seen[] = WPCPM_Sponsor_Posts::draws( $T1 );
ck(
	'a Friend with a post of its own, a returned one, has the card: the level\'s sentence that posts start at Backer, the way into wp-admin, its post, and no Write a post',
	array(
		false !== strpos( $html, 'Your tier, Friend, includes no posts. Posts start at Backer: talk to your program contact.' ),
		strpos( $html, 'the next tier' ),
		false !== strpos( $html, '<a class="wpcpm-posts__admin" href="https://example.test/wp-admin/edit.php">Your posts in wp-admin</a>' ),
		strpos( $html, 'post-new.php' ),
		false !== strpos( $html, '<li class="wpcpm-updates__item wpcpm-posts__item--returned" id="wpcpm-post-' . $t1_returned . '">' ),
		false !== strpos( card( $T1, 1 ), 'This sponsor&#039;s posts in wp-admin' ),
	),
	array( true, false, true, false, true, true )
);
$t0_dormant = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 59, 'post_title' => 'Held back' ) );
$GLOBALS['pmeta'][ $t0_dormant ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T0;
$GLOBALS['pmeta'][ $t0_dormant ][ WPCPM_Sponsor_Posts::META_DORMANT ]       = '2027-01-01';
$t0_dormant_card = card( $T0, 59 );
$GLOBALS['posts'][ $t0_dormant ]->post_status = 'publish';
unset( $GLOBALS['pmeta'][ $t0_dormant ][ WPCPM_Sponsor_Posts::META_DORMANT ] );
$t0_published_card = card( $T0, 59 );
ck( 'and so does a Supporter, with a post the daily run held back or one still published from before the levels, the same sentence true for it too', array( false !== strpos( $t0_dormant_card, 'Your tier, Supporter, includes no posts. Posts start at Backer: talk to your program contact.' ), false !== strpos( $t0_dormant_card, 'wpcpm-posts__item--dormant' ), false !== strpos( $t0_published_card, 'Your tier, Supporter, includes no posts. Posts start at Backer: talk to your program contact.' ), false !== strpos( $t0_published_card, '<span class="wpcpm-sponsor__state">1 published, 0 waiting</span>' ) ), array( true, true, true, true ) );
// The same Friend with the switch off is drawn as before, its post or none: no level applies then.
unset( $GLOBALS['posts'][ $t1_returned ], $GLOBALS['pmeta'][ $t1_returned ], $GLOBALS['posts'][ $t0_dormant ], $GLOBALS['pmeta'][ $t0_dormant ] );
$GLOBALS['settings']['sponsor_tiers_live'] = false;
$off_empty = card( $T1, 60 );
$draws_seen[] = WPCPM_Sponsor_Posts::draws( $T1 );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$GLOBALS['index']['recTIER0000000011'] = array( 'record_id' => 'recTIER0000000011', 'name' => 'TEST Backer With None', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$draws_seen[] = WPCPM_Sponsor_Posts::draws( 'recTIER0000000011' );
unset( $GLOBALS['index']['recTIER0000000011'] );
ck( 'and draws() answers what render() does: no for a Friend or a Supporter with none, yes once one has a post, while the switch is off, and from Backer up with none', $draws_seen, array( false, false, true, true, true ) );
ck( 'while the switch is off, a Friend with no post of its own has the card as before, Write a post and all', array( false !== strpos( $off_empty, '<details id="wpcpm-sponsor-posts"' ), false !== strpos( $off_empty, 'href="https://example.test/wp-admin/post-new.php"' ), strpos( $off_empty, 'includes no posts' ) ), array( true, true, false ) );
unset( $GLOBALS['posts'][ $t1_trash ], $GLOBALS['pmeta'][ $t1_trash ] );

$T2 = 'recTIER0000000002';
$GLOBALS['index'][ $T2 ] = array( 'record_id' => $T2, 'name' => 'TEST Backer One', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$GLOBALS['users'][61] = new WP_User( 61, array( 'wpcpm_sponsor' ), 'Backer Member One' );
WPCPM_Sponsor_Members::attach( 61, $T2, 'manager', 1 );
$t2_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 61, 'post_title' => 'Backer post in flight' ) );
$GLOBALS['pmeta'][ $t2_post ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T2;
$html = card( $T2, 61 );
ck(
	'a Backer with one post in flight reads the tier\'s sentence in place of Write a post',
	array(
		false !== strpos( $html, 'Your tier, Backer, includes one post at a time, in review or published. The next can follow once the program returns this one or takes it down.' ),
		strpos( $html, 'post-new.php' ),
	),
	array( true, false )
);

$T3 = 'recTIER0000000003';
$GLOBALS['index'][ $T3 ] = array( 'record_id' => $T3, 'name' => 'TEST Backer Two', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$GLOBALS['users'][62] = new WP_User( 62, array( 'wpcpm_sponsor' ), 'Backer Member Two' );
WPCPM_Sponsor_Members::attach( 62, $T3, 'manager', 1 );
$html = card( $T3, 62 );
ck( 'a Backer with nothing in flight and nothing published reads Write a post', false !== strpos( $html, 'href="https://example.test/wp-admin/post-new.php"' ), true );

$T4 = 'recTIER0000000004';
$GLOBALS['index'][ $T4 ] = array( 'record_id' => $T4, 'name' => 'TEST Champion', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::CHAMPION );
$GLOBALS['users'][63] = new WP_User( 63, array( 'wpcpm_sponsor' ), 'Champion Member' );
WPCPM_Sponsor_Members::attach( 63, $T4, 'manager', 1 );
// The partner page gives a Champion two self-written posts, the Backer's and a second, and a
// Partner everything in Champion: two at a time since 1.123.18, where a Champion's and a Partner's
// were any number.
$t4_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 63, 'post_title' => 'Champion post 0' ) );
$GLOBALS['pmeta'][ $t4_post ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T4;
$html = card( $T4, 63 );
ck( 'a Champion with one post published reads Write a post, with room for its second', array( false !== strpos( $html, 'href="https://example.test/wp-admin/post-new.php"' ), false !== strpos( $html, '<span class="wpcpm-sponsor__state">1 published, 0 waiting</span>' ), 1 === substr_count( $html, '<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-admin/post-new.php">Write a post<span class="screen-reader-text"> in the WordPress editor</span></a></p></section>' ) ), array( true, true, true ) );
$t4_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 63, 'post_title' => 'Champion post 1' ) );
$GLOBALS['pmeta'][ $t4_post ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T4;
$html = card( $T4, 63 );
ck( 'and with two, its tier\'s two, reads the tier\'s sentence in place of Write a post, its row counting the two published', array( false !== strpos( $html, 'Your tier, Champion, includes 2 posts at a time, in review or published.' ), strpos( $html, 'post-new.php' ), false !== strpos( $html, '<span class="wpcpm-sponsor__state">2 published, 0 waiting</span>' ) ), array( true, false, true ) );
$GLOBALS['index'][ $T4 ]['tier'] = WPCPM_Sponsor_Tiers::PARTNER;
$html = card( $T4, 63 );
ck( 'a Partner holds the same two', array( false !== strpos( $html, 'Your tier, Partner, includes 2 posts at a time, in review or published.' ), strpos( $html, 'post-new.php' ) ), array( true, false ) );
$GLOBALS['index'][ $T4 ]['tier'] = WPCPM_Sponsor_Tiers::CHAMPION;

$T5 = 'recTIER0000000005';
$GLOBALS['index'][ $T5 ] = array( 'record_id' => $T5, 'name' => 'TEST Fence Sponsor', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$GLOBALS['users'][64] = new WP_User( 64, array( 'wpcpm_sponsor' ), 'Fence Member' );
WPCPM_Sponsor_Members::attach( 64, $T5, 'manager', 1 );
$t5_pub = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 64, 'post_title' => 'Fence published post' ) );
$GLOBALS['pmeta'][ $t5_pub ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T5;
$GLOBALS['uid']     = 64;
$fence_new    = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 64, 'post_title' => 'A new submission' ), array( 'ID' => 0 ) );
$fence_resave = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 64 ), array( 'ID' => $t5_pub ) );
ck( 'the fence keeps a submission beyond the cap a draft', array( $fence_new['post_status'], $fence_resave['post_status'] ), array( 'draft', 'pending' ) );

$t5_pending = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 64, 'post_title' => 'Fence pending post' ) );
$GLOBALS['pmeta'][ $t5_pending ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T5;
// A draft in the mix (review finding): post_count() must still answer 2, 1, 1 - a count that
// quietly included drafts would pass unnoticed without one here to catch it.
$t5_draft = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => 64, 'post_title' => 'Fence draft, never counted' ) );
$GLOBALS['pmeta'][ $t5_draft ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T5;
ck(
	'post_count() counts pending and published and leaves the named post out',
	array(
		WPCPM_Sponsor_Posts::post_count( $T5 ),
		WPCPM_Sponsor_Posts::post_count( $T5, $t5_pub ),
		WPCPM_Sponsor_Posts::post_count( $T5, $t5_pending ),
	),
	array( 2, 1, 1 )
);

// Review finding: the clamp used to draft an edit of a post already in flight - a dormant one
// included - whenever the sponsor's other posts alone reached the cap, which is the normal
// state while any post sits dormant. $T5 is well over its cap of one by now (published, pending
// and dormant all at once); re-saving the dormant post or the genuine pending one must still
// leave both pending, and only a truly new post is drafted.
$t5_dormant = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 64, 'post_title' => 'Fence dormant post' ) );
$GLOBALS['pmeta'][ $t5_dormant ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T5;
WPCPM_Sponsor_Posts::set_dormant( $t5_dormant );
$resave_dormant = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 64 ), array( 'ID' => $t5_dormant ) );
$resave_pending = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 64 ), array( 'ID' => $t5_pending ) );
$still_new      = WPCPM_Sponsor_Posts::filter_insert_data( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 64, 'post_title' => 'Another new one' ), array( 'ID' => 0 ) );
ck(
	'the fence leaves a pending post pending when it is re-saved, even with the sponsor over its cap',
	array( $resave_dormant['post_status'], $resave_pending['post_status'], $still_new['post_status'] ),
	array( 'pending', 'pending', 'draft' )
);
// The Posts row counts what is published and what waits for a program manager's review, each by its
// own state; a dormant post is held back by the program, neither published nor waiting for a
// review, so the row counts it as neither. $T5 holds one of each by now.
$t5_row = preg_match( '#<span class="wpcpm-sponsor__state">(.*?)</span>#', card( $T5, 64 ), $t5_state ) ? $t5_state[1] : null;
ck( 'the row counts one published and one waiting, and the dormant post as neither', $t5_row, '1 published, 1 waiting' );

$T6 = 'recTIER0000000006';
$GLOBALS['index'][ $T6 ] = array( 'record_id' => $T6, 'name' => 'TEST Refused Sponsor', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$t6_pub = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_title' => 'Refused sponsor published' ) );
$GLOBALS['pmeta'][ $t6_pub ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T6;
$t6_pending = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 1, 'post_title' => 'Refused sponsor pending' ) );
$GLOBALS['pmeta'][ $t6_pending ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T6;
ck(
	'Publish is refused beyond the cap with post-cap, and the post stays pending',
	array(
		press( 1, array( 'wpcpm_post' => $t6_pending ) ),
		get_post( $t6_pending )->post_status,
	),
	// No detail (review finding): the Sponsor Dashboard's flash already prints post-cap's own
	// sentence from messages(), so a second, nearly identical one is not sent as well.
	array( 'post-cap|posts|' . $T6 . '|', 'pending' )
);

$T7 = 'recTIER0000000007';
$GLOBALS['index'][ $T7 ] = array( 'record_id' => $T7, 'name' => 'TEST Allowed Sponsor', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$t7_pending = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 1, 'post_title' => 'Allowed sponsor pending' ) );
$GLOBALS['pmeta'][ $t7_pending ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T7;
ck(
	'Publish goes through within the cap',
	array(
		press( 1, array( 'wpcpm_post' => $t7_pending ) ),
		get_post( $t7_pending )->post_status,
	),
	array( 'post-published|posts|' . $T7 . '|', 'publish' )
);

$T8 = 'recTIER0000000008';
$GLOBALS['index'][ $T8 ] = array( 'record_id' => $T8, 'name' => 'TEST Dormant Sponsor', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$GLOBALS['users'][68] = new WP_User( 68, array( 'wpcpm_sponsor' ), 'Dormant Member' );
WPCPM_Sponsor_Members::attach( 68, $T8, 'manager', 1 );
// A companion post that stays published, filling the Backer's one slot (review finding): without
// it, $t8_post is the sponsor's only post, so the moment it goes dormant published_of() reads 0,
// which is "room" (below) rather than "no room" - the scenario this card check means to prove.
$t8_keep = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 68, 'post_title' => 'Dormant sponsor, the one that stays published' ) );
$GLOBALS['pmeta'][ $t8_keep ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T8;
$t8_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 68, 'post_title' => 'Dormant sponsor post' ) );
$GLOBALS['pmeta'][ $t8_post ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T8;
$t8_before_date     = get_post( $t8_post )->post_date;
$t8_before_date_gmt = get_post( $t8_post )->post_date_gmt;
ck(
	'set_dormant() takes a published post back to pending with the stamp, and the queue leaves it out',
	array(
		WPCPM_Sponsor_Posts::set_dormant( $t8_post ),
		get_post( $t8_post )->post_status,
		'' !== (string) get_post_meta( $t8_post, WPCPM_Sponsor_Posts::META_DORMANT, true ),
		WPCPM_Sponsor_Posts::state_of( get_post( $t8_post ) ),
		in_array( $t8_post, array_column( WPCPM_Sponsor_Posts::pending_all(), 'id' ), true ),
		WPCPM_Sponsor_Posts::set_dormant( $t8_post ),
	),
	array( true, 'pending', true, 'dormant', false, false )
);

$html = card( $T8, 68 );
ck( 'the card says why a dormant post is held back', false !== strpos( $html, 'Dormant: your tier includes one published post at a time, so the others are held back. They return by themselves when an Administrator sets a tier that includes them.' ), true );

// Nothing about the tiers before the switch is on, and a tier with no cap is a wait for the next
// daily run, never a false "0 published posts".
$GLOBALS['settings']['sponsor_tiers_live'] = false;
ck( 'the dormant note names no tier while the switch is off', WPCPM_Sponsor_Posts::dormant_note( $T8 ), 'Held back by the program: not shown right now. It returns by itself when the program sets it live again.' );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$GLOBALS['index'][ $T8 ]['tier'] = WPCPM_Sponsor_Tiers::CHAMPION;
ck( 'a tier with no cap waits on the next run rather than reading a false zero', WPCPM_Sponsor_Posts::dormant_note( $T8 ), 'Held back until the next daily run, which brings back what your tier has room for.' );
$GLOBALS['index'][ $T8 ]['tier'] = WPCPM_Sponsor_Tiers::BACKER;

// A tier that already has room reads the same as no cap at all - a
// manager raised the tier since the run parked this post, so it is a wait for the run, not a
// decision still to make. $T3 (Backer, "nothing in flight" above) is still empty otherwise, so
// this one post alone gives it room the moment it goes dormant.
$t3_dormant = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 62, 'post_title' => 'Backer Two, dormant with room' ) );
$GLOBALS['pmeta'][ $t3_dormant ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T3;
WPCPM_Sponsor_Posts::set_dormant( $t3_dormant );
ck( 'a dormant post with room under its tier reads that the next daily run brings it back', WPCPM_Sponsor_Posts::dormant_note( $T3 ), 'Held back until the next daily run, which brings back what your tier has room for.' );

// set_dormant()'s own day, asked for by name (review finding): no sponsor needed, just the meta.
$explicit_day_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_title' => 'An explicit dormant date' ) );
WPCPM_Sponsor_Posts::set_dormant( $explicit_day_post, '2027-01-01' );
ck( 'set_dormant() with an explicit day writes exactly that day', get_post_meta( $explicit_day_post, WPCPM_Sponsor_Posts::META_DORMANT, true ), '2027-01-01' );

// An edited post is a new submission. $t8_edit
// goes dormant and is then saved by its own member, the way a real edit reaches enforce() on
// save_post_post - classic or REST, both raise it, so no second fix belongs in enforce_rest() as
// well.
$t8_edit = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 68, 'post_title' => 'Edited after going dormant' ) );
$GLOBALS['pmeta'][ $t8_edit ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T8;
WPCPM_Sponsor_Posts::set_dormant( $t8_edit );
$GLOBALS['uid'] = 68;
WPCPM_Sponsor_Posts::enforce( $t8_edit, get_post( $t8_edit ) );
ck(
	'a member\'s edit of a dormant post takes the stamp off: it stays pending, joins the queue, and wake() leaves it alone',
	array(
		get_post_meta( $t8_edit, WPCPM_Sponsor_Posts::META_DORMANT, true ),
		WPCPM_Sponsor_Posts::state_of( get_post( $t8_edit ) ),
		get_post( $t8_edit )->post_status,
		in_array( $t8_edit, array_column( WPCPM_Sponsor_Posts::pending_all(), 'id' ), true ),
		WPCPM_Sponsor_Posts::wake( $t8_edit ),
	),
	array( '', 'pending', 'pending', true, false )
);

$t8_plain = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 68, 'post_title' => 'A plain submission' ) );
$GLOBALS['pmeta'][ $t8_plain ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T8;
ck(
	'wake() publishes a stamped post, clears the stamp and moves no date, and never a post the sponsor submitted',
	array(
		WPCPM_Sponsor_Posts::wake( $t8_post ),
		get_post( $t8_post )->post_status,
		get_post_meta( $t8_post, WPCPM_Sponsor_Posts::META_DORMANT, true ),
		get_post( $t8_post )->post_date,
		get_post( $t8_post )->post_date_gmt,
		WPCPM_Sponsor_Posts::wake( $t8_plain ),
		get_post( $t8_plain )->post_status,
	),
	array( true, 'publish', '', $t8_before_date, $t8_before_date_gmt, false, 'pending' )
);

// Review finding: wp_publish_post() answers nothing and never checks its own write, so a failed
// one must not be read as a wake that happened - the stamp stays, and the post is not silently
// handed to the queue as a submission nobody made.
$t8_publish_fails = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 68, 'post_title' => 'Dormant, the publish does not take' ) );
$GLOBALS['pmeta'][ $t8_publish_fails ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T8;
WPCPM_Sponsor_Posts::set_dormant( $t8_publish_fails );
$GLOBALS['publish_fails'] = true;
$failed_wake = WPCPM_Sponsor_Posts::wake( $t8_publish_fails );
$GLOBALS['publish_fails'] = false;
ck(
	'wake() keeps the stamp and answers false when the publish did not take',
	array(
		$failed_wake,
		get_post( $t8_publish_fails )->post_status,
		get_post_meta( $t8_publish_fails, WPCPM_Sponsor_Posts::META_DORMANT, true ),
	),
	array( false, 'pending', '2026-09-05' )
);

// Review finding: no check read the call wake() actually makes. $t8_post is already published by
// the check above, so a fresh dormant post proves it here, isolated with reset_calls() from
// set_dormant()'s own wp_update_post() call a moment earlier.
$t8_call_check = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 68, 'post_title' => 'Dormant, checking the call wake() makes' ) );
$GLOBALS['pmeta'][ $t8_call_check ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T8;
WPCPM_Sponsor_Posts::set_dormant( $t8_call_check );
reset_calls();
WPCPM_Sponsor_Posts::wake( $t8_call_check );
ck(
	'wake() publishes through wp_publish_post(), not a content re-save',
	array(
		in_array( array( 'wp_publish_post', $t8_call_check ), $GLOBALS['calls'], true ),
		array_filter( calls( 'wp_update_post' ), static function ( $c ) use ( $t8_call_check ) { return $c[1] === $t8_call_check; } ),
	),
	array( true, array() )
);

// Publish and Return each take the stamp off too, whatever the run left there (the review of
// 1.112.0). $T4 is a Champion with room for two: its two published posts go back to draft first,
// so the cap stays out of this - it is about the stamp alone.
foreach ( WPCPM_Sponsor_Posts::published_of( $T4 ) as $t4_published ) {
	wp_update_post( array( 'ID' => (int) $t4_published->ID, 'post_status' => 'draft' ) );
}
$t4_to_publish = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 63, 'post_title' => 'Champion dormant, about to be published' ) );
$GLOBALS['pmeta'][ $t4_to_publish ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T4;
WPCPM_Sponsor_Posts::set_dormant( $t4_to_publish );
$t4_to_return = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 63, 'post_title' => 'Champion dormant, about to be returned' ) );
$GLOBALS['pmeta'][ $t4_to_return ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T4;
WPCPM_Sponsor_Posts::set_dormant( $t4_to_return );
$publish_result = press( 1, array( 'wpcpm_post' => $t4_to_publish ) );
$return_result  = press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $t4_to_return, 'wpcpm_note' => 'A manager\'s note.' ) );
ck(
	'Publish and Return take the stamp off a dormant post',
	array(
		$publish_result,
		get_post( $t4_to_publish )->post_status,
		get_post_meta( $t4_to_publish, WPCPM_Sponsor_Posts::META_DORMANT, true ),
		$return_result,
		get_post( $t4_to_return )->post_status,
		get_post_meta( $t4_to_return, WPCPM_Sponsor_Posts::META_DORMANT, true ),
	),
	array( 'post-published|posts|' . $T4 . '|', 'publish', '', 'post-returned|posts|' . $T4 . '|', 'draft', '' )
);

// The delete_all() call below wipes every posting flag, every term record and OPT_APPLIED, which
// would otherwise reach every later check in this file (review finding); snapshotting and
// restoring $GLOBALS['opts'] keeps this check's own proof - the stamps it wrote are gone - while
// nothing after this point reads a different world than the one that came before it. delete_all()
// also runs clear_dormant_stamps(), which removes the stamps of the earlier dormant fixtures
// still standing at this point ($t3_dormant, $t5_dormant, $t8_publish_fails): their posts stay
// pending, so a later pending_all() call would now read them as submissions rather than dormant
// posts - not restored with $GLOBALS['opts'], since no check after this one asks about any of them.
$opts_before_delete_all = $GLOBALS['opts'];
$stamp_pending = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 68, 'post_title' => 'Leftover dormant stamp, pending' ) );
update_post_meta( $stamp_pending, WPCPM_Sponsor_Posts::META_DORMANT, '2026-09-01' );
$stamp_published = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 68, 'post_title' => 'Leftover dormant stamp, published' ) );
update_post_meta( $stamp_published, WPCPM_Sponsor_Posts::META_DORMANT, '2026-09-02' );
WPCPM_Sponsor_Posts::delete_all();
ck(
	'delete_all() clears the stamps and leaves the posts',
	array(
		get_post_meta( $stamp_pending, WPCPM_Sponsor_Posts::META_DORMANT, true ),
		get_post_meta( $stamp_published, WPCPM_Sponsor_Posts::META_DORMANT, true ),
		get_post( $stamp_pending ) instanceof WP_Post,
		get_post( $stamp_published ) instanceof WP_Post,
	),
	array( '', '', true, true )
);
$GLOBALS['opts'] = $opts_before_delete_all;

// The window itself (review finding): pending_all()'s old PHP-side skip still let an older
// dormant post spend a slot of $limit before being dropped, so a tight window could come back
// with nothing at all. Isolated in its own copy of $GLOBALS['posts'], the way the review's own
// reproduction proved it: with the dormant post older (first, so first in this stand-in's
// insertion-ordered "oldest first") and the window one post wide, the query itself must skip it
// rather than spend the window's one slot on it.
$T9 = 'recTIER0000000009';
$GLOBALS['index'][ $T9 ] = array( 'record_id' => $T9, 'name' => 'TEST Window Sponsor', 'website' => '', 'status' => 'Approved', 'tier' => WPCPM_Sponsor_Tiers::BACKER );
$older_dormant = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_title' => 'Older dormant post' ) );
$GLOBALS['pmeta'][ $older_dormant ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T9;
WPCPM_Sponsor_Posts::set_dormant( $older_dormant );
$newer_submission = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 1, 'post_title' => 'Newer genuine submission' ) );
$GLOBALS['pmeta'][ $newer_submission ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $T9;
$posts_before_window_check = $GLOBALS['posts'];
$GLOBALS['posts']          = array(
	$older_dormant    => $GLOBALS['posts'][ $older_dormant ],
	$newer_submission => $GLOBALS['posts'][ $newer_submission ],
);
$window            = array_column( WPCPM_Sponsor_Posts::pending_all( 1 ), 'id' );
$GLOBALS['posts']  = $posts_before_window_check;
ck( 'pending_all() leaves dormant posts out by query, so an older dormant post cannot push a submission out of the window', $window, array( $newer_submission ) );

// Nothing after this point in the file expects the switch on: every later check runs the way it
// always has, the switch off, as the rest of the suite was written before tiers existed.
unset( $GLOBALS['settings']['sponsor_tiers_live'] );

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
// Post meta unslashes what it is handed, as core's does, and the note is read from the form
// unslashed: it keeps a backslash only when it is written as a slashed copy.
$typed_note = 'Put the screenshot from C:\drafts\shots, two \\\\ in a row, under "Setup".';
$pending3   = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_title' => 'Third guide' ) );
$GLOBALS['pmeta'][ $pending3 ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $S;
ck( 'a return note is kept exactly as typed', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $pending3, 'wpcpm_note' => $typed_note ) ), get_post_meta( $pending3, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ) ), array( 'post-returned|posts|' . $S . '|', $typed_note ) );
$stranger = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 1, 'post_title' => 'A program post' ) );
ck( 'a post with no sponsor stamp is not this handler\'s to publish', press( 1, array( 'wpcpm_post' => $stranger ) ), 'refused|posts||' );
ck( 'every outcome has a sentence', array_keys( WPCPM_Sponsor_Posts::messages() ), array( 'post-published', 'post-returned', 'post-note-missing', 'post-note-long', 'post-note-loss', 'post-not-pending', 'post-cap', 'post-failed' ) );

echo "\n=== The posting switch (wp-admin) ===\n";
function flags( $uid, $post ) { $GLOBALS['uid'] = $uid; $_POST = $post; try { WPCPM_Sponsor_Posts::handle_flags(); } catch ( WPCPM_Test_Redirect $e ) { return $e->getMessage(); } return 'no redirect'; }
$GLOBALS['flash'] = array();
ck( 'a member cannot switch the flag', flags( 20, array( 'wpcpm_sponsor' => $S, 'wpcpm_on' => '0' ) ), 'die:403' );
// The switch reads its form through WPCPM_Request, where the standard's nonce sniff cannot see it,
// so only a check here notices a nonce gone: a page elsewhere could then turn a sponsor's posting
// off in a signed-in manager's name.
$posting_before         = WPCPM_Sponsor_Posts::posting_enabled( $S );
$flags_before           = get_option( WPCPM_Sponsor_Posts::OPT_FLAGS_PREFIX . $S, 'none' );
$GLOBALS['nonce_fails'] = true;
$stale_flags            = flags( 1, array( 'wpcpm_sponsor' => $S, 'wpcpm_on' => '0' ) );
$GLOBALS['nonce_fails'] = false;
ck( 'a switch whose nonce is not its own dies with WordPress\'s sentence before the flag is written or a flash is set: the sponsor\'s stored flags and its posting flag are as they were, and nothing is flashed', array( $stale_flags, get_option( WPCPM_Sponsor_Posts::OPT_FLAGS_PREFIX . $S, 'none' ), WPCPM_Sponsor_Posts::posting_enabled( $S ), $GLOBALS['flash'] ), array( 'The link you followed has expired.', $flags_before, $posting_before, array() ) );
// And before the sponsor policy is asked, not only before the write: the order is read in the
// handler itself, since a policy asked first would end the press the same way.
$flags_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-posts.php' );
$flags_body = substr( $flags_src, (int) strpos( $flags_src, 'public static function handle_flags()' ) );
$flags_body = substr( $flags_body, 0, (int) strpos( $flags_body, "\n\t}\n" ) );
ck( 'the switch checks the nonce before it asks the sponsor policy', array( strpos( $flags_body, 'check_admin_referer(' ) > 0, strpos( $flags_body, 'check_admin_referer(' ) < strpos( $flags_body, 'WPCPM_Sponsor_Policy::decide(' ) ), array( true, true ) );
ck( 'a manager switches it off and is sent back to the sponsor\'s accounts on the Sponsors screen\'s Accounts tab, where the switch is, with the flash', array( flags( 1, array( 'wpcpm_sponsor' => $S, 'wpcpm_on' => '0' ) ), WPCPM_Sponsor_Posts::posting_enabled( $S ), end( $GLOBALS['flash'] ) ), array( 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_sponsor=' . $S, false, array( 'sponsors_admin', 'posting-off' ) ) );
reset_calls();
ck( 'and on again, back to the same accounts', array( flags( 1, array( 'wpcpm_sponsor' => $S, 'wpcpm_on' => '1' ) ), WPCPM_Sponsor_Posts::posting_enabled( $S ), end( $GLOBALS['flash'] )[1] ), array( 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_sponsor=' . $S, true, 'posting-on' ) );
ck( 'the switch asks one nonce, its own action keyed to the sponsor its form names', calls( 'nonce' ), array( array( 'nonce', 'wpcpm_sponsor_flags_' . $S ) ) );
ck( 'a manager naming a record the index does not hold is refused, back on the Accounts tab itself: that record names no sponsor\'s accounts to come back to', flags( 1, array( 'wpcpm_sponsor' => 'recNOPE0000000001', 'wpcpm_on' => '0' ) ), 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts' );
ck( 'with the one sentence', end( $GLOBALS['flash'] )[1], 'refused' );
ck( 'and no flag is left behind for it', get_option( 'wpcpm_sponsor_flags_recNOPE0000000001', 'none' ), 'none' );

echo "\n=== Guides under an offer ===\n";
function guides( $record, $uid ) { $GLOBALS['uid'] = $uid; ob_start(); WPCPM_Sponsor_Posts::render_tools( $record, wp_get_current_user(), 'TEST Sponsor' ); return ob_get_clean(); }
$GLOBALS['users'][30] = new WP_User( 30, array( 'wpcpm_student' ), 'Student Reader' );
/** Whether the reader holds a marker capability the Students and mentors level accepts. */
function may_read( $on ) { $GLOBALS['grants'][30] = $on ? array( WPCPM_Roles::CAP_VIEW_STUDENT ) : array(); }
may_read( true );
ck( 'a sponsor with no published post prints nothing', guides( $S2, 30 ), '' );
$g = guides( $S, 30 );
ck( 'published guides are listed with the lead naming the company and each title linked', array( 0 === strpos( $g, '<div class="wpcpm-tools__posts"><p class="wpcpm-tools__posts-lead">Guides from TEST Sponsor</p><ul class="wpcpm-tools__posts-list">' ), substr_count( $g, '<li><a href="https://example.test/?p=' ), false !== strpos( $g, '>Ten tips</a>' ) ), array( true, 2, true ) );
may_read( false );
ck( 'a reader the level refuses gets nothing', guides( $S, 30 ), '' );
may_read( true );
for ( $i = 0; $i < 6; $i++ ) { $extra = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 20, 'post_title' => 'Guide ' . $i ) ); $GLOBALS['pmeta'][ $extra ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $S; $GLOBALS['pmeta'][ $extra ][ WPCPM_Content_Access::META_KEY ] = WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS; }
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
may_read( false );
ck( 'no banner for a reader the level refuses', WPCPM_Sponsor_Posts::filter_content( '<p>Notice</p>' ), '<p>Notice</p>' );
may_read( true );
$GLOBALS['singular']    = false;
ck( 'no banner off the single post', WPCPM_Sponsor_Posts::filter_content( '<p>Body</p>' ), '<p>Body</p>' );

echo "\n=== The gate itself, on a sponsor post (FSUIT-3) ===\n";
// Three of the five gate surfaces had no assertion at all: guard_singular(), filter_content()
// and filter_rest() could each be made a no-op with the whole battery green, because the access
// suite asserted only that their hooks were registered, by name. A sponsor post sits at the
// Students and mentors level, so this is the surface a logged-out visitor or a reader without
// the level meets at the permalink, in the body and over REST.
/** Where WPCPM_Content_Access::guard_singular() leaves this request. */
function guarded() { try { WPCPM_Content_Access::guard_singular(); } catch ( WPCPM_Test_Redirect $e ) { return $e->getMessage(); } return 'no redirect'; }
/** A REST response carrying one post's body, as the prepare filters hand it over. */
function rest_body( $rendered ) { return new WP_REST_Response( array( 'content' => array( 'rendered' => $rendered, 'raw' => 'The whole guide.' ) ) ); }
$GLOBALS['post']     = get_post( $pid );
$GLOBALS['singular'] = true;
$GLOBALS['uid']      = 0;
ck( 'a signed-out visitor at the permalink meets the login form with a way back', guarded(), 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( 'https://example.test/?p=' . $pid ) );
$GLOBALS['uid'] = 30;
may_read( false );
ck( 'a signed-in reader without the level is refused with an explanation, not a login form', guarded(), 'die:403' );
$gated = WPCPM_Content_Access::filter_content( '<p>The whole guide.</p>' );
ck( 'the content filter answers with the level\'s notice and not one word of the body', array( false !== strpos( $gated, 'This content is limited to: Students and mentors.' ), strpos( $gated, 'The whole guide.' ) ), array( true, false ) );
$refused = WPCPM_Content_Access::filter_rest( rest_body( '<p>The whole guide.</p>' ), get_post( $pid ), null )->get_data();
ck( 'and REST carries the same notice, with the raw body dropped', array( false !== strpos( $refused['content']['rendered'], 'This content is limited to: Students and mentors.' ), isset( $refused['content']['raw'] ) ), array( true, false ) );
// The fourth surface of the same gate, and the one nothing asserted either: a listing or a
// search result prints excerpts, so an excerpt that came through would hand the opening of a
// gated guide to the reader the three surfaces above just refused.
ck( 'and the excerpt a listing prints is emptied rather than shortened', WPCPM_Content_Access::filter_excerpt( 'The first paragraph of the guide.' ), '' );
may_read( true );
$allowed = WPCPM_Content_Access::filter_rest( rest_body( '<p>The whole guide.</p>' ), get_post( $pid ), null )->get_data();
ck( 'a reader the level admits passes all three untouched', array( guarded(), WPCPM_Content_Access::filter_content( '<p>The whole guide.</p>' ), $allowed['content']['rendered'] ), array( 'no redirect', '<p>The whole guide.</p>', '<p>The whole guide.</p>' ) );
$GLOBALS['singular'] = false;

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

echo "\n=== The Administrator Dashboard's queue ===\n";
$GLOBALS['uid'] = 1;
$queued = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_title' => 'A queued guide' ) );
$GLOBALS['pmeta'][ $queued ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $S;
$unstamped = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 1, 'post_title' => 'A program post, pending' ) );
$rows = WPCPM_Sponsor_Posts::pending_all();
ck( 'pending_all() lists the pending sponsor posts as facts, the company named, and nothing unstamped or in another state', array( in_array( $queued, array_column( $rows, 'id' ), true ), in_array( $unstamped, array_column( $rows, 'id' ), true ), in_array( $pid, array_column( $rows, 'id' ), true ), $rows[ array_search( $queued, array_column( $rows, 'id' ), true ) ]['company'], $rows[ array_search( $queued, array_column( $rows, 'id' ), true ) ]['author'] ), array( true, false, false, 'TEST Sponsor', 'Member One' ) );
ob_start(); WPCPM_Sponsor_Posts::render_decision( $queued, WPCPM_Return::DASHBOARD ); $decision = ob_get_clean();
ck( 'the decision block is the request cards\' shape: Preview, Publish, a folded Return with a note that says what it does, both forms carrying the way back to the dashboard', array( 0 === strpos( $decision, '<div class="wpcpm-request__decide wpcpm-sponsor-post__decide"><a class="button" href="https://example.test/?p=' . $queued . '&preview=true">Preview</a>' ), substr_count( $decision, 'name="wpcpm_return" value="dashboard"' ), substr_count( $decision, 'name="wpcpm_return_to" value="sponsor-posts"' ), false !== strpos( $decision, '<details class="wpcpm-sponsor-post__return"><summary class="button">Return with a note</summary><p class="wpcpm-administrator__note">The post goes back to the sponsor&#039;s account as a draft, and your note is sent to its author by email.</p>' ), false !== strpos( $decision, 'name="action" value="wpcpm_sponsor_post_publish"' ), false !== strpos( $decision, 'name="action" value="wpcpm_sponsor_post_return"' ) ), array( true, 2, 2, true, true, true ) );
// The institution side's words, which the sponsor side shares: the button under the fold reads
// Return it with this note; Publish, Preview and the fold's summary keep theirs.
ck( 'the return button reads Return it with this note, as the institution side\'s does, and Publish keeps its word', array(
	false !== strpos( $decision, '<button type="submit" class="button">Return it with this note</button>' ),
	false !== strpos( $decision, '<button type="submit" class="button button-primary">Publish</button>' ),
	strpos( $decision, 'Send back with this note' ),
), array( true, true, false ) );
$GLOBALS['uid'] = 20;
ob_start(); WPCPM_Sponsor_Posts::render_decision( $queued, WPCPM_Return::DASHBOARD ); $none = ob_get_clean();
ck( 'a member gets no decision block', $none, '' );
$GLOBALS['flash'] = array();
$landed = press( 1, array( 'wpcpm_post' => $queued, 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'sponsor-posts' ) );
ck( 'a decision taken on the Administrator Dashboard goes back there, its sentence on that page\'s channel', array( $landed, end( $GLOBALS['flash'] ), get_post( $queued )->post_status ), array( 'https://example.test/administrator-dashboard/#wpcpm-sponsor-posts', array( 'institutions', 'post-published' ), 'publish' ) );

echo "\n=== Their own category, and nothing else, in wp-admin ===\n";
$GLOBALS['uid'] = 20;
// delete_all() above forgot the term record; the member's category exists again once ensured.
WPCPM_Sponsor_Posts::ensure_terms( $S );
$own = array( WPCPM_Sponsor_Posts::parent_term_id(), WPCPM_Sponsor_Posts::term_of( $S ) );
ck( 'the editor\'s category list is the member\'s two', WPCPM_Sponsor_Posts::scope_categories( array( 'per_page' => 100, 'exclude' => array( 5 ) ), null ), array( 'per_page' => 100, 'exclude' => array(), 'include' => $own ) );
$GLOBALS['is_admin'] = true;
ck( 'so is every category list wp-admin builds, and the filter over their posts is gone', array( WPCPM_Sponsor_Posts::scope_terms_args( array( 'hide_empty' => false ), array( 'category' ) ), WPCPM_Sponsor_Posts::scope_terms_args( array( 'x' => 1 ), array( 'post_tag' ) ), WPCPM_Sponsor_Posts::hide_category_filter( false, 'post' ) ), array( array( 'hide_empty' => false, 'include' => $own ), array( 'x' => 1 ), true ) );
$GLOBALS['is_admin'] = false;
$GLOBALS['uid'] = 1;
ck( 'a manager keeps every category and the filter', array( WPCPM_Sponsor_Posts::scope_categories( array( 'per_page' => 100 ), null ), WPCPM_Sponsor_Posts::hide_category_filter( false, 'post' ) ), array( array( 'per_page' => 100 ), false ) );
$GLOBALS['uid'] = 20;

echo "\n=== A minted key is accepted everywhere a record id was ===\n";
$SPO = 'spoAAAA0000000001';
$GLOBALS['index'][ $SPO ] = array( 'record_id' => $SPO, 'name' => 'TEST Minted Sponsor', 'website' => '', 'status' => 'Approved' );
$minted = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_title' => 'Minted key post' ) );
WPCPM_Sponsor_Posts::pin( $minted, $SPO );
ck( 'pin() stamps a spo key', get_post_meta( $minted, WPCPM_Sponsor_Policy::META_POST_SPONSOR, true ), $SPO );

echo "\n=== The return note: counted, kept and mailed as typed ===\n";
// A manager's words, and the form the cleaner hands them back in: "<3 our " is a "<" that opens no
// tag, written `&lt;`; the tags go; the lone ">" stays; "< 12 welcome, adults >" is kept as typed;
// and after a "<" with no ">" to close it the quote marks and ampersands are entities too. A post
// title as WordPress stores a member's: each "&" written `&amp;` by kses, and a "<" typed as `&lt;`.
$r_note    = "We <3 our <b>team</b> > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
$r_note_cl = "We &lt;3 our team > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.";
$r_title   = 'Q&amp;A: "blocks" &amp; themes, we &lt;3 them';
$r_title_as = 'Q&A: "blocks" & themes, we <3 them';
/** A typing of exactly $limit characters as a person counts them (a line break is one), made of $unit and padded with "x". */
function posts_filled( $unit, $limit ) {
	$one  = mb_strlen( str_replace( "\r\n", "\n", $unit ) );
	$text = str_repeat( $unit, intdiv( $limit, $one ) );
	return $text . str_repeat( 'x', $limit - mb_strlen( str_replace( "\r\n", "\n", $text ) ) );
}
/** A pending post of the sponsor's, by member 20, with the title given. */
function posts_pending( $title ) {
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_title' => $title ) );
	$GLOBALS['pmeta'][ $id ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $GLOBALS['S'];
	return $id;
}
$GLOBALS['S'] = $S;
ck( 'the stand-in is core\'s cleaner: it hands the note back in this form', sanitize_textarea_field( $r_note ), $r_note_cl );

$GLOBALS['mail'] = array(); $GLOBALS['audit'] = array();
$typed_post = posts_pending( $r_title );
ck( 'a note with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and the post waits as it was: no note, no mail', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $typed_post, 'wpcpm_note' => $r_note ) ), get_post( $typed_post )->post_status, get_post_meta( $typed_post, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ), $GLOBALS['mail'] ), array( 'post-note-loss|posts|' . $S . '|', 'pending', '', array() ) );
// The same words without the tag: a "<3" whose "<" opens no tag, a "<" that a ">" closes after a
// space, and a "<" with no ">" after it, the quote marks and the ampersands after it entities too.
$r_sent    = "We <3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
$r_sent_cl = "We &lt;3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.";
ck( 'a note with line breaks, a "<3", a "<" and the ampersands after one returns the post, and is kept as the cleaner left it', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $typed_post, 'wpcpm_note' => $r_sent ) ), get_post( $typed_post )->post_status, get_post_meta( $typed_post, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ) ), array( 'post-returned|posts|' . $S . '|', 'draft', $r_sent_cl ) );
$r_mail = isset( $GLOBALS['mail'][0]['mail'] ) ? $GLOBALS['mail'][0]['mail'] : array();
ck( 'the mail\'s subject carries the title as it reads, "Q&A" and "<3", and is marked plain text so the mail layer keeps them', array( $r_mail['subject'] ?? null, $r_mail['plain_subject'] ?? null ), array( 'Your post "' . $r_title_as . '" needs a change before it is published', true ) );
ck( 'and its body carries the title and the note as typed, line by line, with no entity in either', $r_mail['body'] ?? null, "Hello Member One,\n\nAn Administrator read your post \"" . $r_title_as . "\" and sent it back as a draft with this note:\n\n" . $r_sent . "\n\nEdit it here and submit it for review again: https://example.test/wp-admin/edit.php" );

$r_at = posts_filled( "Ages 8 < 12. Q&A: \"blocks\" & themes, it's free.\r\n", 2000 );
$GLOBALS['mail'] = array(); $GLOBALS['audit'] = array();
$at_post = posts_pending( 'Fourth guide' );
ck( 'a note of 2,000 characters as typed, with 41 line breaks, is longer than that as the cleaner stores it', array( substr_count( $r_at, "\r\n" ), mb_strlen( sanitize_textarea_field( $r_at ) ) > 2000 ), array( 41, true ) );
ck( 'and returns the post whole: kept, mailed every character as typed, and its length logged as typed', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $at_post, 'wpcpm_note' => $r_at ) ), get_post_meta( $at_post, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ) === sanitize_textarea_field( $r_at ), isset( $GLOBALS['mail'][0]['mail']['body'] ) && false !== strpos( $GLOBALS['mail'][0]['mail']['body'], "this note:\n\n" . $r_at . "\n\nEdit it" ), end( $GLOBALS['audit'] )['data']['note_length'] ?? null ), array( 'post-returned|posts|' . $S . '|', true, true, 2000 ) );
$GLOBALS['mail'] = array(); $GLOBALS['audit'] = array();
$over_post = posts_pending( 'Fifth guide' );
ck( 'one character more is refused with its own sentence, and the post waits as it was: no note, no mail, no audit row', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $over_post, 'wpcpm_note' => $r_at . 'x' ) ), get_post( $over_post )->post_status, get_post_meta( $over_post, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ), $GLOBALS['mail'], $GLOBALS['audit'] ), array( 'post-note-long|posts|' . $S . '|', 'pending', '', array(), array() ) );
ck( 'the sentence says what the limit is and what to do', WPCPM_Sponsor_Posts::messages()['post-note-long'] ?? null, array( 'error', 'The note is longer than 2000 characters. Shorten it and return the post again.' ) );

// The limit is counted on what was typed, as the box counts it: past the loss check, a reference
// typed out after a "<", which the cleaner writes in core's longer form (`&#9;` as `&#009;`), is
// as many characters as it was typed with, so a note the box took is not refused for what the
// cleaner made of it, and the row that logs the act logs the length the limit was checked on.
$p_at_most = str_repeat( 'Ages 8 < 12 &#9; ok. ', 95 ) . 'Done.';
ck( 'the note below is 2,000 characters as typed, and the count of what the cleaner keeps reads it as more', array( WPCPM_Typed_Text::box_length( $p_at_most ), WPCPM_Typed_Text::typed_length( sanitize_textarea_field( $p_at_most ) ) > WPCPM_Sponsor_Posts::MAX_NOTE, WPCPM_Typed_Text::cleaner_loses( $p_at_most, 'lines' ) ), array( WPCPM_Sponsor_Posts::MAX_NOTE, true, false ) );
$GLOBALS['mail'] = array(); $GLOBALS['audit'] = array();
$ref_post = posts_pending( 'Sixth guide' );
ck( 'a note of exactly 2,000 characters as typed, with "&#9;" after a "<", returns the post, mails the author once and logs the length as typed', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $ref_post, 'wpcpm_note' => $p_at_most ) ), get_post( $ref_post )->post_status, get_post_meta( $ref_post, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ) === sanitize_textarea_field( $p_at_most ), count( $GLOBALS['mail'] ), end( $GLOBALS['audit'] )['data']['note_length'] ?? null ), array( 'post-returned|posts|' . $S . '|', 'draft', true, 1, 2000 ) );
$GLOBALS['mail'] = array(); $GLOBALS['audit'] = array();
$ref_over = posts_pending( 'Seventh guide' );
ck( 'and one character more is refused as too long, and the post waits as it was: no note, no mail, no audit row', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $ref_over, 'wpcpm_note' => $p_at_most . 'x' ) ), get_post( $ref_over )->post_status, get_post_meta( $ref_over, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ), $GLOBALS['mail'], $GLOBALS['audit'] ), array( 'post-note-long|posts|' . $S . '|', 'pending', '', array(), array() ) );
$GLOBALS['uid'] = 1;
ob_start(); WPCPM_Sponsor_Posts::render_decision( $over_post, WPCPM_Return::DASHBOARD ); $r_box = ob_get_clean();
ck( 'the note\'s box holds no more than that', 1 === preg_match( '/<textarea id="wpcpm-post-note-' . $over_post . '" name="wpcpm_note" rows="2" maxlength="2000" required placeholder="/', $r_box ), true );

echo "\n=== The return note: a typing the cleaner would take words from is refused, and kept ===\n";
/**
 * One post's Return with a note as a browser shows it to the current user: what its box holds and
 * whether its fold is open, or null when no form is drawn. The parser reads a CR LF as one LF and
 * drops the one line feed right after a text area's opening tag.
 */
function posts_note_box( $post_id ) {
	ob_start();
	WPCPM_Sponsor_Posts::render_decision( $post_id, WPCPM_Return::DASHBOARD );
	$html = (string) ob_get_clean();
	if ( ! preg_match( '/<textarea id="wpcpm-post-note-' . (int) $post_id . '" name="wpcpm_note"[^>]*>(.*?)<\/textarea>/s', $html, $m ) ) {
		return null;
	}
	$shown = (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", $m[1] ) );
	return array( 'shown' => html_entity_decode( $shown, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'open' => false !== strpos( $html, '<details class="wpcpm-sponsor-post__return" open>' ) );
}
$GLOBALS['users'][2]    = new WP_User( 2, array( 'administrator' ), 'Manager Two' );
$GLOBALS['manage']      = array( 1, 2 );
$GLOBALS['kept_typing'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['mail'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['metered'] = array();
$k_note  = "Kids <12 free, adults >18 pay.\r\nA second line, \"quoted\" & C:\\drafts";
$k_post  = posts_pending( 'Kept guide' );
$k_other = posts_pending( 'Another kept guide' );
$k_back  = array( 'wpcpm_action' => 'return', 'wpcpm_post' => $k_post, 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'sponsor-posts' );
reset_calls();
$landed = press( 1, $k_back + array( 'wpcpm_note' => $k_note ) );
ck( 'a note the cleaner would cut to "Kids 18 pay." is refused, back on the Administrator Dashboard, with the sentence for a return, naming the box by its label', array( $landed, end( $GLOBALS['flash'] ), WPCPM_Sponsor_Posts::messages()['post-note-loss'] ?? null ), array( 'https://example.test/administrator-dashboard/#wpcpm-sponsor-posts', array( 'institutions', 'post-note-loss' ), array( 'error', 'Nothing was returned, because WordPress would remove part of what you typed in "A note for the author". To keep every word, put a space after each "<" (or write "less than") and after each "%", then return it again.' ) ) );
ck( 'and nothing is done: the post waits as it was, with no note kept on it, no mail, no audit row, no write to the post at all, and nothing spent of the refusal meter', array( get_post( $k_post )->post_status, get_post_meta( $k_post, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ), get_post_meta( $k_post, WPCPM_Sponsor_Posts::META_RETURNED, true ), $GLOBALS['mail'], $GLOBALS['audit'], calls( 'wp_update_post' ), calls( 'update_post_meta' ), $GLOBALS['metered'] ), array( 'pending', '', '', array(), array(), array(), array(), array() ) );
ck( 'the post\'s Return with a note draws open, its box holding the note as it was typed, a line break as the box counts it', posts_note_box( $k_post ), array( 'shown' => str_replace( "\r\n", "\n", $k_note ), 'open' => true ) );
ck( 'and only once: drawn again, the box is empty and the fold closed', posts_note_box( $k_post ), array( 'shown' => '', 'open' => false ) );
press( 1, $k_back + array( 'wpcpm_note' => "\r\n" . $k_note ) );
ck( 'a kept note that starts with a line break comes back with it: the box writes a line feed of its own for the one the parser drops', posts_note_box( $k_post )['shown'] ?? null, "\n" . str_replace( "\r\n", "\n", $k_note ) );

press( 1, $k_back + array( 'wpcpm_note' => $k_note ) );
$GLOBALS['uid'] = 2;
$k_seen_two     = posts_note_box( $k_post );
$GLOBALS['uid'] = 1;
$k_seen_other   = posts_note_box( $k_other );
ck( 'another manager never sees it on the same post, nor does another post\'s box, while the one who typed it does', array( $k_seen_two, $k_seen_other, posts_note_box( $k_post )['shown'] ), array( array( 'shown' => '', 'open' => false ), array( 'shown' => '', 'open' => false ), str_replace( "\r\n", "\n", $k_note ) ) );

ck( 'posted without the way back, the refusal lands on the Sponsor Dashboard\'s card, as every other outcome there does', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $k_post, 'wpcpm_note' => 'Use code SAVE20<b></b>' ) ), get_post( $k_post )->post_status ), array( 'post-note-loss|posts|' . $S . '|', 'pending' ) );
posts_note_box( $k_post );

$k_long = $r_at . 'xyz';
ck( 'a note over its limit is refused as before, and its box now comes back with it, kept up to the box\'s limit of 2,000', array( press( 1, $k_back + array( 'wpcpm_note' => $k_long ) ), end( $GLOBALS['flash'] ), posts_note_box( $k_post ) ), array( 'https://example.test/administrator-dashboard/#wpcpm-sponsor-posts', array( 'institutions', 'post-note-long' ), array( 'shown' => str_replace( "\r\n", "\n", $r_at ), 'open' => true ) ) );
ck( 'a note left empty keeps nothing: there is nothing to give back', array( press( 1, $k_back + array( 'wpcpm_note' => '   ' ) ), end( $GLOBALS['flash'] ), $GLOBALS['kept_typing'][1] ?? array() ), array( 'https://example.test/administrator-dashboard/#wpcpm-sponsor-posts', array( 'institutions', 'post-note-missing' ), array() ) );

// Refused and kept, then posted with the fix: the post goes back as a draft. When it is submitted
// for review again, its box is empty, not filled with the typing the fix replaced.
press( 1, $k_back + array( 'wpcpm_note' => $k_note ) );
$k_fixed = press( 1, $k_back + array( 'wpcpm_note' => 'Kids under 12 free, adults over 18 pay.' ) );
$GLOBALS['posts'][ $k_post ]->post_status = 'pending';
ck( 'a typing a refusal kept goes when the form is posted again, so the post\'s next review draws its box empty', array( $k_fixed, get_post_meta( $k_post, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ), posts_note_box( $k_post ) ), array( 'https://example.test/administrator-dashboard/#wpcpm-sponsor-posts', 'Kids under 12 free, adults over 18 pay.', array( 'shown' => '', 'open' => false ) ) );

$k_keeps = "We <3 WordPress.\r\nAges 8 < 12 welcome, adults > 18 pay, 100% of them, Q&A: \"blocks\" & themes, &amp; and &copy; typed out, and \xF0\x9F\x98\x80";
$GLOBALS['mail'] = array();
$k_clean = posts_pending( 'Clean guide' );
ck( 'a note the cleaner keeps whole returns the post as before: kept as the cleaner left it, mailed as typed, and nothing kept for the form', array( press( 1, array( 'wpcpm_action' => 'return', 'wpcpm_post' => $k_clean, 'wpcpm_note' => $k_keeps ) ), get_post( $k_clean )->post_status, get_post_meta( $k_clean, WPCPM_Sponsor_Posts::META_RETURN_NOTE, true ), isset( $GLOBALS['mail'][0]['mail']['body'] ) && false !== strpos( $GLOBALS['mail'][0]['mail']['body'], WPCPM_Typed_Text::mail_text( sanitize_textarea_field( $k_keeps ) ) ), $GLOBALS['kept_typing'][1] ?? array() ), array( 'post-returned|posts|' . $S . '|', 'draft', sanitize_textarea_field( $k_keeps ), true, array() ) );
$GLOBALS['manage'] = array( 1 );

echo "\n=== Jetpack's subscriber emails (1.123.18) ===\n";
// Jetpack Subscriptions emails a post to the site's subscribers whenever it moves to publish, each
// time it does, so a sponsor post an Administrator approves, and a dormant one the daily run wakes,
// would be announced, again and again. Two guards, either enough: the Sponsors category and each
// sponsor's own under it are on the list Jetpack's filter leaves out, and every sponsor post
// carries Jetpack's own "do not email" mark, set on a member's save, on Publish and on a wake,
// before the post is published. The filter is Jetpack's to apply: without Jetpack, nothing calls it.
if ( ! function_exists( 'get_term_children' ) ) {
	/** Core's reader of a term's children, from the stand-in terms. */
	function get_term_children( $id, $tax ) { $kids = array(); foreach ( $GLOBALS['terms'] as $tid => $t ) { if ( (int) $t['parent'] === (int) $id ) { $kids[] = $tid; $kids = array_merge( $kids, get_term_children( $tid, $tax ) ); } } return $kids; }
}
/** Where in the calls since the last reset_calls() a call stands, or -1. */
function jp_at( $call ) { $at = array_search( $call, $GLOBALS['calls'], true ); return false === $at ? -1 : (int) $at; }
$jp_parent = (int) get_term_by( 'slug', WPCPM_Sponsor_Posts::PARENT_SLUG, 'category' )->term_id;
$jp_kids   = get_term_children( $jp_parent, 'category' );
sort( $jp_kids );
$jp_left   = WPCPM_Sponsor_Posts::exclude_from_subscriptions( array( 'news' ) );
ck( 'the Sponsors category and every sponsor\'s own under it are left out of Jetpack\'s subscriber emails, beside what the site already left out', array( $jp_left, count( $jp_kids ) > 0 ), array( array_merge( array( 'news', $jp_parent ), $jp_kids ), true ) );
ck( 'a value that is not a list is read as an empty one', WPCPM_Sponsor_Posts::exclude_from_subscriptions( '' ), array_merge( array( $jp_parent ), $jp_kids ) );
$jp_terms         = $GLOBALS['terms'];
$GLOBALS['terms'] = array();
reset_calls();
ck( 'with no Sponsors category yet the list is left as it was, and the filter creates none', array( WPCPM_Sponsor_Posts::exclude_from_subscriptions( array( 'news' ) ), calls( 'wp_insert_term' ) ), array( array( 'news' ), array() ) );
$GLOBALS['terms'] = $jp_terms;

$GLOBALS['uid'] = 20;
$jp_saved = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 20, 'post_title' => 'Jetpack guide' ) );
WPCPM_Sponsor_Posts::pin( $jp_saved, $S );
ck( 'a member\'s save marks the post for Jetpack never to email it', get_post_meta( $jp_saved, WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, true ), 1 );
ck( 'the mark is Jetpack\'s own key', WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, '_jetpack_dont_email_post_to_subs' );

$jp_post = posts_pending( 'Approved without a mark' );
unset( $GLOBALS['pmeta'][ $jp_post ][ WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL ] );
reset_calls();
$jp_r = press( 1, array( 'wpcpm_post' => $jp_post ) );
ck( 'Publish marks a post that has no mark, before it publishes it, so Jetpack reads the mark the moment the post goes live', array( $jp_r, get_post( $jp_post )->post_status, get_post_meta( $jp_post, WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, true ), jp_at( array( 'update_post_meta', $jp_post, WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, 1 ) ) > -1 && jp_at( array( 'update_post_meta', $jp_post, WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, 1 ) ) < (int) array_search( 'wp_update_post', array_column( $GLOBALS['calls'], 0 ), true ) ), array( 'post-published|posts|' . $S . '|', 'publish', 1, true ) );

$jp_dormant = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 20, 'post_title' => 'Dormant, from before the mark' ) );
$GLOBALS['pmeta'][ $jp_dormant ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $S;
WPCPM_Sponsor_Posts::set_dormant( $jp_dormant );
unset( $GLOBALS['pmeta'][ $jp_dormant ][ WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL ] );
reset_calls();
ck( 'a wake marks a post published before the mark existed, before it publishes it again', array( WPCPM_Sponsor_Posts::wake( $jp_dormant ), get_post_meta( $jp_dormant, WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, true ), jp_at( array( 'update_post_meta', $jp_dormant, WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, 1 ) ) > -1 && jp_at( array( 'update_post_meta', $jp_dormant, WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL, 1 ) ) < jp_at( array( 'wp_publish_post', $jp_dormant ) ) ), array( true, 1, true ) );
$jp_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-posts.php' );
ck( 'nothing asks for Jetpack: no class or function of its is called, only its filter hooked and its key written', preg_match( '/\bJetpack(?:_[A-Za-z]+)?::|\bjetpack_[a-z_]+\(/', $jp_src ), 0 );

echo "\n=== A test sponsor's posts in the site's listings (1.123.18) ===\n";
// A test sponsor is never listed publicly, and a sponsor post is a post of the site, which the
// blog, its archives, its feeds and its search list to the students and mentors its level admits.
// On the front end, for anybody but an Administrator, a listing leaves out every post stamped with
// a test sponsor's key; a query the plugin's own cards make stands outside by name, as it does the
// access gate, so the test sponsor's own members keep their Posts card, and a single post's own
// page is the gate's to answer.
if ( ! class_exists( 'WP_Query' ) ) {
	/** Enough of core's query for the listings' filter: its vars, whether it is a single post's, and whether it is the request's own (1.123.20). */
	class WP_Query {
		public $vars = array();
		public $singular = false;
		public $main = false;
		public function __construct( array $vars = array(), $singular = false, $main = false ) { $this->vars = $vars; $this->singular = $singular; $this->main = $main; }
		public function get( $k ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : ''; }
		public function set( $k, $v ) { $this->vars[ $k ] = $v; }
		public function is_singular() { return $this->singular; }
		public function is_main_query() { return $this->main; }
	}
}
$tp_test = 'spoTESTPOSTS00001';
$GLOBALS['index'][ $tp_test ] = array( 'record_id' => $tp_test, 'name' => 'TEST - Posts', 'status' => 'Approved', 'test' => true );
$tp_clause = array(
	'relation' => 'OR',
	array(
		'key'     => WPCPM_Sponsor_Policy::META_POST_SPONSOR,
		'compare' => 'NOT EXISTS',
	),
	array(
		'key'     => WPCPM_Sponsor_Policy::META_POST_SPONSOR,
		'value'   => array( $tp_test ),
		'compare' => 'NOT IN',
	),
);
/** The meta query a listing is left with once the filter has read it, as a viewer and on a screen. */
function tp_listed( WP_Query $q, $uid, $admin = false ) { $GLOBALS['uid'] = $uid; $GLOBALS['is_admin'] = $admin; WPCPM_Sponsor_Posts::hide_test_posts( $q ); $GLOBALS['is_admin'] = false; return $q->get( 'meta_query' ); }
ck( 'the blog index, an archive, a feed or a search, read by anybody but an Administrator, signed in or not, leaves the test sponsor\'s posts out, beside what the query already asked', array(
	tp_listed( new WP_Query(), 20 ),
	tp_listed( new WP_Query( array( 'post_type' => 'post', 'meta_query' => array( array( 'key' => 'x', 'value' => 'y' ) ) ) ), 0 ),
	tp_listed( new WP_Query( array( 'post_type' => array( 'post', 'page' ) ) ), 0 ),
), array( array( $tp_clause ), array( array( 'key' => 'x', 'value' => 'y' ), $tp_clause ), array( $tp_clause ) ) );
ck( 'an Administrator\'s listing, wp-admin, a single post\'s page, a query that names other post types and the plugin\'s own ungated queries are left as they were', array(
	tp_listed( new WP_Query(), 1 ),
	tp_listed( new WP_Query(), 20, true ),
	tp_listed( new WP_Query( array(), true ), 20 ),
	tp_listed( new WP_Query( array( 'post_type' => 'page' ) ), 20 ),
	tp_listed( new WP_Query( array( 'post_type' => 'post', WPCPM_Content_Access::QUERY_UNGATED => true ) ), 20 ),
), array( '', '', '', '', '' ) );
unset( $GLOBALS['index'][ $tp_test ] );
ck( 'with no test sponsor on the site, nothing is added to any listing', tp_listed( new WP_Query(), 20 ), '' );
$GLOBALS['uid'] = 20;

echo "\n=== A test sponsor's post by its own link (1.123.20) ===\n";
// The listings leave a test sponsor's posts out, and a post's own link is the other way in: its
// page, its REST route and its oEmbed answer as not found for every reader but an Administrator, a
// member of that same sponsor and the post's author. One predicate decides all three, read afresh on
// every request, so a sponsor that stops being a test sponsor is reachable at once; wp-admin, and
// the editor's own reads of a post it edits, are left as they were.
/** Enough of core's REST request for the single-post route: its method, its route, its URL's id and its context. */
class WP_REST_Request {
	private $method, $route, $params;
	public function __construct( $method, $route, array $params = array() ) { $this->method = $method; $this->route = $route; $this->params = $params; }
	public function get_method() { return $this->method; }
	public function get_route() { return $this->route; }
	public function get_url_params() { return preg_match( '#/(\d+)/?$#', $this->route, $m ) ? array( 'id' => $m[1] ) : array(); }
	public function get_param( $k ) { if ( 'id' === $k ) { $u = $this->get_url_params(); return isset( $u['id'] ) ? $u['id'] : null; } return isset( $this->params[ $k ] ) ? $this->params[ $k ] : null; }
}
/** Core's route for one post: the posts controller's base for a post, nothing for another type here. */
function rest_get_route_for_post( $post ) { $post = get_post( $post ); return $post instanceof WP_Post && 'post' === $post->post_type ? '/wp/v2/posts/' . $post->ID : ''; }
/** The controllers a route's handler can name: core's posts controller, whose get_item() answers one post, and another. */
class WP_REST_Controller {}
class WP_REST_Posts_Controller extends WP_REST_Controller { public function get_item( $request ) {} public function get_items( $request ) {} }
class WP_REST_Terms_Controller extends WP_REST_Controller { public function get_item( $request ) {} }
/** The canonical redirect's view of the request (1.123.20): whether it 404s, its query vars, the post a URL names, and core's guess. */
function is_404() { return ! empty( $GLOBALS['is_404'] ); }
function get_query_var( $k, $d = '' ) { return isset( $GLOBALS['qv'][ $k ] ) ? $GLOBALS['qv'][ $k ] : $d; }
function url_to_postid( $url ) { if ( preg_match( '#[?&](?:p|page_id)=(\d+)#', (string) $url, $m ) ) { return (int) $m[1]; } return isset( $GLOBALS['url_ids'][ $url ] ) ? (int) $GLOBALS['url_ids'][ $url ] : 0; }
/** Core's guess, with its own filter applied as core applies it, this plugin's callback the one hooked. */
function redirect_guess_404_permalink() { $pre = WPCPM_Sponsor_Posts::hide_test_post_guess( null ); if ( null !== $pre ) { return $pre; } $GLOBALS['guessed'] = ( $GLOBALS['guessed'] ?? 0 ) + 1; return isset( $GLOBALS['guess'] ) ? $GLOBALS['guess'] : false; }
$tl_test  = 'spoTESTLINK000001';
$tl_other = 'spoREALLINK000001';
$GLOBALS['index'][ $tl_test ]  = array( 'record_id' => $tl_test, 'name' => 'TEST - Linked', 'website' => '', 'status' => 'Approved', 'test' => true );
$GLOBALS['index'][ $tl_other ] = array( 'record_id' => $tl_other, 'name' => 'Linked Real', 'website' => '', 'status' => 'Approved' );
$GLOBALS['users'][40] = new WP_User( 40, array( 'wpcpm_sponsor' ), 'Test Member' );
$GLOBALS['users'][41] = new WP_User( 41, array( 'wpcpm_sponsor' ), 'Other Member' );
$GLOBALS['users'][42] = new WP_User( 42, array( 'subscriber' ), 'Former Member' );
$GLOBALS['umeta'][40] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $tl_test, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['umeta'][41] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $tl_other, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$tl_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 42, 'post_title' => 'A test guide' ) );
$GLOBALS['pmeta'][ $tl_post ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $tl_test;
$GLOBALS['pmeta'][ $tl_post ][ WPCPM_Content_Access::META_KEY ]          = 'public';
$tl_real = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 41, 'post_title' => 'A real guide' ) );
$GLOBALS['pmeta'][ $tl_real ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $tl_other;
/** The predicate's answer for one reader. */
function tl_hidden( $post_id, $uid ) { $GLOBALS['uid'] = $uid; return WPCPM_Sponsor_Posts::is_hidden_test_post( get_post( $post_id ) ); }
/** What the main query of a request for one post is left with, for one reader. */
function tl_page( $post_id, $uid, $singular = true, $main = true, $admin = false ) { $GLOBALS['uid'] = $uid; $GLOBALS['is_admin'] = $admin; $found = WPCPM_Sponsor_Posts::hide_test_post_page( array( get_post( $post_id ) ), new WP_Query( array(), $singular, $main ) ); $GLOBALS['is_admin'] = false; return array_map( static function ( $p ) { return $p->ID; }, $found ); }
/** What the REST server is left to answer for one post's route, for one reader: null goes on to the controller. The route as spelled, and the handler core matched it to, the posts controller's get_item() unless named. */
function tl_rest( $post_id, $uid, $method = 'GET', $params = array(), $response = null, $route = '', $callback = null ) { $GLOBALS['uid'] = $uid; $route = '' !== $route ? $route : '/wp/v2/posts/' . $post_id; $callback = null !== $callback ? $callback : array( new WP_REST_Posts_Controller(), 'get_item' ); $answer = WPCPM_Sponsor_Posts::hide_test_post_rest( $response, array( 'callback' => $callback ), new WP_REST_Request( $method, $route, $params ) ); return $answer instanceof WP_Error ? array( $answer->get_error_code(), $answer->get_error_data() ) : $answer; }
ck( 'the one predicate: hidden from a visitor, a student, a member of another sponsor and a manager of nothing, and reachable by an Administrator, a member of that same sponsor and the post\'s author', array( tl_hidden( $tl_post, 0 ), tl_hidden( $tl_post, 30 ), tl_hidden( $tl_post, 41 ), tl_hidden( $tl_post, 21 ), tl_hidden( $tl_post, 1 ), tl_hidden( $tl_post, 40 ), tl_hidden( $tl_post, 42 ) ), array( true, true, true, true, false, false, false ) );
ck( 'and never for a real sponsor\'s post or a post no sponsor wrote', array( tl_hidden( $tl_real, 0 ), tl_hidden( $stranger, 0 ) ), array( false, false ) );
ck( 'its own page answers as not found: the main query of the request finds no post, so WordPress serves its 404, with the theme\'s 404 template and a 404 status', array( tl_page( $tl_post, 0 ), tl_page( $tl_post, 30 ), tl_page( $tl_post, 41 ) ), array( array(), array(), array() ) );
ck( 'for an Administrator, a member of the sponsor and the author it is found as before', array( tl_page( $tl_post, 1 ), tl_page( $tl_post, 40 ), tl_page( $tl_post, 42 ) ), array( array( $tl_post ), array( $tl_post ), array( $tl_post ) ) );
ck( 'and nothing else is touched: a real sponsor\'s page, a listing, a query that is not the request\'s own, and wp-admin', array( tl_page( $tl_real, 0 ), tl_page( $tl_post, 0, false ), tl_page( $tl_post, 0, true, false ), tl_page( $tl_post, 0, true, true, true ) ), array( array( $tl_real ), array( $tl_post ), array( $tl_post ), array( $tl_post ) ) );
ck( 'its REST route answers as core answers for a post that is not there: rest_post_invalid_id, status 404', array( tl_rest( $tl_post, 0 ), tl_rest( $tl_post, 30, 'HEAD' ), tl_rest( $tl_post, 41, 'GET', array( 'context' => 'embed' ) ) ), array( array( 'rest_post_invalid_id', array( 'status' => 404 ) ), array( 'rest_post_invalid_id', array( 'status' => 404 ) ), array( 'rest_post_invalid_id', array( 'status' => 404 ) ) ) );
$GLOBALS['users'][43] = new WP_User( 43, array( 'editor' ), 'Site Editor' );
$GLOBALS['grants'][43] = array( 'edit_post' );
ck( 'and goes on to the controller for an Administrator, a member of the sponsor and the author, for a real post, for the editor\'s own read by somebody who may edit the post, for a write, and for an answer already decided', array( tl_rest( $tl_post, 1 ), tl_rest( $tl_post, 40 ), tl_rest( $tl_post, 42 ), tl_rest( $tl_real, 0 ), tl_rest( $tl_post, 43, 'GET', array( 'context' => 'edit' ) ), tl_rest( $tl_post, 0, 'POST' ), tl_rest( $tl_post, 0, 'GET', array(), 'decided' ) ), array( null, null, null, null, null, null, 'decided' ) );
ck( 'the route is decided by the handler core matched, not by how it was spelled: a leading zero, capitals, or the same through ?rest_route= answer as not found too', array( tl_rest( '0' . $tl_post, 0 ), tl_rest( $tl_post, 0, 'GET', array(), null, '/wp/v2/POSTS/' . $tl_post ), tl_rest( $tl_post, 0, 'GET', array(), null, '/WP/V2/posts/00' . $tl_post . '/' ) ), array( array( 'rest_post_invalid_id', array( 'status' => 404 ) ), array( 'rest_post_invalid_id', array( 'status' => 404 ) ), array( 'rest_post_invalid_id', array( 'status' => 404 ) ) ) );
ck( 'and another controller, or the posts controller\'s list, goes on as it was', array( tl_rest( $tl_post, 0, 'GET', array(), null, '/wp/v2/categories/' . $tl_post, array( new WP_REST_Terms_Controller(), 'get_item' ) ), tl_rest( $tl_post, 0, 'GET', array(), null, '/wp/v2/posts', array( new WP_REST_Posts_Controller(), 'get_items' ) ) ), array( null, null ) );
ck( 'the edit context is no way to learn the post is there: for somebody who may not edit it, it answers as not found too, never as a forbidden context', array( tl_rest( $tl_post, 0, 'GET', array( 'context' => 'edit' ) ), tl_rest( $tl_post, 30, 'GET', array( 'context' => 'edit' ) ) ), array( array( 'rest_post_invalid_id', array( 'status' => 404 ) ), array( 'rest_post_invalid_id', array( 'status' => 404 ) ) ) );
// A 404 for the post is no redirect to it either: core's canonical redirect sends ?p= and ?page_id=
// to the permalink on a 404, guesses a permalink from part of a slug, and follows an old slug.
$tl_link = 'https://example.test/a-test-guide/';
$GLOBALS['url_ids'] = array( $tl_link => $tl_post, 'https://example.test/a-real-guide/' => $tl_real );
/** Where the canonical redirect sends one reader of a 404, for the query vars and the URL given. */
function tl_redirect( $uid, array $qv, $url, $is_404 = true ) { $GLOBALS['uid'] = $uid; $GLOBALS['qv'] = $qv; $GLOBALS['is_404'] = $is_404; $to = WPCPM_Sponsor_Posts::hide_test_post_redirect( $url, 'https://example.test/?p=' . ( $qv['p'] ?? 0 ) ); $GLOBALS['qv'] = array(); $GLOBALS['is_404'] = false; return $to; }
ck( 'a 404 for ?p= or ?page_id= of the post, or for a URL that names it, redirects nowhere, so no slug is learned from it', array( tl_redirect( 0, array( 'p' => $tl_post ), $tl_link ), tl_redirect( 30, array( 'page_id' => $tl_post ), $tl_link ), tl_redirect( 0, array(), $tl_link ), tl_redirect( 41, array( 'p' => (string) $tl_post ), $tl_link ) ), array( false, false, false, false ) );
ck( 'and redirects as before for an Administrator, a member of the sponsor, the author, a real post, and a request that found its post', array( tl_redirect( 1, array( 'p' => $tl_post ), $tl_link ), tl_redirect( 40, array( 'p' => $tl_post ), $tl_link ), tl_redirect( 42, array(), $tl_link ), tl_redirect( 0, array( 'p' => $tl_real ), 'https://example.test/a-real-guide/' ), tl_redirect( 0, array(), $tl_link, false ) ), array( $tl_link, $tl_link, $tl_link, 'https://example.test/a-real-guide/', $tl_link ) );
$GLOBALS['uid'] = 0;
$GLOBALS['guess'] = $tl_link;
$tl_guessed = array( redirect_guess_404_permalink(), WPCPM_Sponsor_Posts::hide_test_post_guess( 'decided' ) );
$GLOBALS['uid'] = 1;
$tl_guessed[] = redirect_guess_404_permalink();
$GLOBALS['uid'] = 0;
$GLOBALS['guess'] = 'https://example.test/a-real-guide/';
$tl_guessed[] = redirect_guess_404_permalink();
$GLOBALS['guess'] = false;
$tl_guessed[] = redirect_guess_404_permalink();
ck( 'core\'s guess from part of a slug is never the post, for a reader it is hidden from, and is the guess for anyone else and for any other post; a guess already made goes through', $tl_guessed, array( false, 'decided', $tl_link, 'https://example.test/a-real-guide/', false ) );
$tl_old = array( WPCPM_Sponsor_Posts::hide_test_post_old_slug( $tl_post ), WPCPM_Sponsor_Posts::hide_test_post_old_slug( $tl_real ), WPCPM_Sponsor_Posts::hide_test_post_old_slug( 0 ) );
$GLOBALS['uid'] = 40;
$tl_old[] = WPCPM_Sponsor_Posts::hide_test_post_old_slug( $tl_post );
ck( 'and an old slug of the post leads nowhere, but for the readers who may reach it', $tl_old, array( 0, $tl_real, 0, $tl_post ) );
unset( $GLOBALS['url_ids'], $GLOBALS['guess'] );
$GLOBALS['uid'] = 0;
$tl_embed = array( 'author_name' => 'Former Member', 'title' => 'A test guide' );
ck( 'its oEmbed answers as not found too, for the same readers, and an Administrator still gets the company', array( WPCPM_Sponsor_Posts::filter_oembed( $tl_embed, get_post( $tl_post ) ), tl_hidden( $tl_post, 1 ) ? 'hidden' : WPCPM_Sponsor_Posts::filter_oembed( $tl_embed, get_post( $tl_post ) )['author_name'] ), array( false, 'TEST - Linked' ) );
$GLOBALS['index'][ $tl_test ]['test'] = false;
ck( 'a sponsor that stops being a test sponsor is reachable at once, on every surface: nothing remembers the answer', array( tl_hidden( $tl_post, 0 ), tl_page( $tl_post, 0 ), tl_rest( $tl_post, 0 ), is_array( WPCPM_Sponsor_Posts::filter_oembed( $tl_embed, get_post( $tl_post ) ) ) ), array( false, array( $tl_post ), null, true ) );
$GLOBALS['index'][ $tl_test ]['test'] = true;
$tl_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-posts.php' );
$tl_body = static function ( $name ) use ( $tl_src ) { $at = strpos( $tl_src, 'public static function ' . $name . '(' ); return false === $at ? '' : substr( $tl_src, $at, strpos( $tl_src, "\n\t}\n", $at ) - $at ); };
ck( 'the three surfaces and the three redirects ask the one predicate, and nothing caches its answer', array( false !== strpos( $tl_body( 'hide_test_post_page' ), 'self::is_hidden_test_post(' ), false !== strpos( $tl_body( 'hide_test_post_rest' ), 'self::is_hidden_test_post(' ), false !== strpos( $tl_body( 'filter_oembed' ), 'self::is_hidden_test_post(' ), false !== strpos( $tl_body( 'hide_test_post_redirect' ), 'self::is_hidden_test_post(' ), false !== strpos( $tl_body( 'hide_test_post_guess' ), 'self::is_hidden_test_post(' ), false !== strpos( $tl_body( 'hide_test_post_old_slug' ), 'self::is_hidden_test_post(' ), preg_match( '/static \$|set_transient|wp_cache_set/', $tl_body( 'is_hidden_test_post' ) ) ), array( true, true, true, true, true, true, 0 ) );
unset( $GLOBALS['index'][ $tl_test ], $GLOBALS['index'][ $tl_other ], $GLOBALS['posts'][ $tl_post ], $GLOBALS['posts'][ $tl_real ] );
$GLOBALS['uid'] = 20;

echo "\n=== What waits on a sponsor: the posts returned with a note (Institutions release 3) ===\n";
// The Sponsor Dashboard's strip counts the posts its Posts card shows as returned with a note, through
// returned_count(), which asks state_of() of the posts the card lists: a draft that holds a return note.
// A draft with none, a post waiting for review or held back by the daily run, and a published one are
// not, whatever note an earlier return left on them; a post in the trash is none the card lists; and
// one sponsor's returned posts are none of another's.
$RW  = 'recWAIT0000000001';
$RW2 = 'recWAIT0000000002';
$RW3 = 'recWAIT0000000003';
foreach ( array( $RW, $RW2, $RW3 ) as $rw_key ) {
	$GLOBALS['index'][ $rw_key ] = array( 'record_id' => $rw_key, 'name' => 'TEST Waiting Sponsor', 'website' => '', 'status' => 'Approved' );
}
/** One post of a sponsor's, in a status, with the return note an earlier return left on it, if any. */
function rw_post( $record, $status, $title, $note = '', $dormant = false ) {
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => $status, 'post_author' => 20, 'post_title' => $title ) );
	$GLOBALS['pmeta'][ $id ][ WPCPM_Sponsor_Policy::META_POST_SPONSOR ] = $record;
	if ( '' !== $note ) {
		$GLOBALS['pmeta'][ $id ][ WPCPM_Sponsor_Posts::META_RETURN_NOTE ] = $note;
	}
	if ( $dormant ) {
		$GLOBALS['pmeta'][ $id ][ WPCPM_Sponsor_Posts::META_DORMANT ] = '2026-09-01';
	}
	return $id;
}
ck( 'a sponsor with no post has none returned', array( WPCPM_Sponsor_Posts::returned_count( $RW ), WPCPM_Sponsor_Posts::returned_count( 'not a key' ), WPCPM_Sponsor_Posts::returned_count( '' ) ), array( 0, 0, 0 ) );
$rw_returned_a = rw_post( $RW, 'draft', 'Returned once', 'Please add a screenshot.' );
ck( 'a draft with a return note is one', array( WPCPM_Sponsor_Posts::state_of( get_post( $rw_returned_a ) ), WPCPM_Sponsor_Posts::returned_count( $RW ) ), array( 'returned', 1 ) );
$rw_returned_b = rw_post( $RW, 'draft', 'Returned twice', 'Shorter title, please.' );
rw_post( $RW, 'draft', 'A draft with no note' );
rw_post( $RW, 'pending', 'Waiting for review' );
rw_post( $RW, 'pending', 'Waiting again, after a return', 'An earlier note.' );
rw_post( $RW, 'pending', 'Held back by the daily run', '', true );
rw_post( $RW, 'publish', 'Published after a return', 'An older note.' );
rw_post( $RW, 'trash', 'Thrown away', 'A note on a post in the trash.' );
rw_post( $RW2, 'draft', 'Another company\'s returned post', 'Not this sponsor\'s.' );
ck( 'and only those: a draft without a note, a post waiting for review, one held back and a published one are not, nor is one in the trash', array( WPCPM_Sponsor_Posts::returned_count( $RW ), WPCPM_Sponsor_Posts::returned_count( $RW2 ), WPCPM_Sponsor_Posts::returned_count( $RW3 ) ), array( 2, 1, 0 ) );
ck( 'the Posts card shows as returned the posts the count names, and no others', array( substr_count( card( $RW, 1 ), 'wpcpm-posts__item--returned' ), substr_count( card( $RW2, 1 ), 'wpcpm-posts__item--returned' ), substr_count( card( $RW3, 1 ), 'wpcpm-posts__item--returned' ) ), array( WPCPM_Sponsor_Posts::returned_count( $RW ), WPCPM_Sponsor_Posts::returned_count( $RW2 ), WPCPM_Sponsor_Posts::returned_count( $RW3 ) ) );
$GLOBALS['pmeta'][ $rw_returned_a ][ WPCPM_Sponsor_Posts::META_RETURN_NOTE ] = '';
ck( 'a return note taken off (a publish clears it) leaves the post a draft, no longer counted', array( WPCPM_Sponsor_Posts::state_of( get_post( $rw_returned_a ) ), WPCPM_Sponsor_Posts::returned_count( $RW ) ), array( 'draft', 1 ) );
$GLOBALS['uid'] = 20;

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-posts.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the caps are never put on a role', preg_match( '/get_role\(|add_role\(/', $src ), 0 );
ck( 'publish_posts, edit_others_posts and edit_published_posts appear nowhere in the class', preg_match( '/publish_posts|edit_others_posts|edit_published_posts/', $src ), 0 );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
