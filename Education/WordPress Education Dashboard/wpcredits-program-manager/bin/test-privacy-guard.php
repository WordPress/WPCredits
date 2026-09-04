<?php
/**
 * WPCPM_Privacy_Guard: the three doors WordPress leaves open on a site full of real names.
 *
 * `?author=N` answers 200 for every existing account with the display name as its title, which
 * the syncs set to the person's real name; `redirect_canonical()` then 301s to `/author/<login>/`
 * for anyone who authored a published row of any type, and the plugin stored calls, notes and
 * audit rows as published with the person as author. The users REST route and the users sitemap
 * are the same list through two more doors. The guard closes all three for anybody without the
 * manage capability, and flips the rows written before it to `private` once.
 *
 * Run from the plugin root:  php bin/test-privacy-guard.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['hooks']    = array();
$GLOBALS['manage']   = false;
$GLOBALS['uid']      = 0;
$GLOBALS['author']   = false;
$GLOBALS['status']   = array();
$GLOBALS['nocache']  = 0;
$GLOBALS['opts']     = array();
$GLOBALS['ended']    = 0;
$GLOBALS['db']       = array();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_Query {
	public $is_404 = false;
	public function set_404() { $this->is_404 = true; }
}
class WP_REST_Request {
	private $route;
	public function __construct( $route ) { $this->route = $route; }
	public function get_route() { return $this->route; }
}
class WPCPM_Roles { const CAP_MANAGE = 'wpcpm_manage_program'; }
class WPCPM_Mentor_Calls { const POST_TYPE = 'wpcpm_mentor_call'; }
class WPCPM_Mentor_Notes { const POST_TYPE = 'wpcpm_mentor_note'; }
class WPCPM_Institution_Audit { const POST_TYPE = 'wpcpm_audit_entry'; }
class FakeDB {
	public $posts = 'wp_posts';
	public $updates = array();
	public function prepare( $q, ...$a ) { return vsprintf( str_replace( '%s', "'%s'", $q ), $a ); }
	public function get_col( $q ) { $ids = array(); foreach ( $GLOBALS['db'] as $id => $row ) { if ( false !== strpos( $q, "'" . $row['type'] . "'" ) && 'publish' === $row['status'] ) { $ids[] = $id; } } return $ids; }
	public function update( $table, $data, $where ) { $n = 0; foreach ( $GLOBALS['db'] as $id => $row ) { if ( $row['type'] === $where['post_type'] && $row['status'] === $where['post_status'] ) { $GLOBALS['db'][ $id ]['status'] = $data['post_status']; ++$n; } } $this->updates[] = $where['post_type']; return $n; }
}
$wpdb = new FakeDB();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $priority ); }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $priority ); }
function is_author() { return $GLOBALS['author']; }
require_once __DIR__ . '/stubs/caps.php';
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return $GLOBALS['uid']; }
function status_header( $code ) { $GLOBALS['status'][] = (int) $code; }
function nocache_headers() { ++$GLOBALS['nocache']; }
function apply_filters( $tag, $value ) { return $value; }
function get_404_template() { return '/nonexistent/404.php'; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function clean_post_cache( $id ) {}
function rest_authorization_required_code() { return $GLOBALS['uid'] > 0 ? 403 : 401; }
function __( $s, $d = null ) { return $s; }

require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-privacy-guard.php';

$total = 0;
$fails = 0;
function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}
	++$fails;
	echo "FAIL $label\n     got:  " . var_export( $actual, true ) . "\n     want: " . var_export( $expected, true ) . "\n";
}

WPCPM_Privacy_Guard::for_tests( function () { ++$GLOBALS['ended']; } );

echo "=== The hooks, and the one priority that matters ===\n";

WPCPM_Privacy_Guard::init();
$author_guard = null;
foreach ( $GLOBALS['hooks'] as $hook ) { if ( 'template_redirect' === $hook[0] ) { $author_guard = $hook[1]; } }
ck( 'the author guard runs on template_redirect', is_int( $author_guard ), true );
ck( 'before redirect_canonical() at priority 10, so the 301 to /author/<login>/ never fires', $author_guard < 10, true );
ck( 'the users sitemap provider filter is taken', in_array( array( 'wp_sitemaps_add_provider', 10 ), $GLOBALS['hooks'], true ), true );
ck( 'and the REST dispatch filter', in_array( array( 'rest_pre_dispatch', 10 ), $GLOBALS['hooks'], true ), true );

echo "\n=== Author archives ===\n";

$wp_query = new WP_Query();
$GLOBALS['author'] = true;
$GLOBALS['manage'] = false;
WPCPM_Privacy_Guard::deny_author_archives();
ck( 'a visitor asking for an author archive gets a 404', $GLOBALS['status'], array( 404 ) );
ck( 'with no-cache headers, so no cache keeps a 200 for them', $GLOBALS['nocache'], 1 );
ck( 'the main query is told it is a 404', $wp_query->is_404, true );
ck( 'and the request ends there', $GLOBALS['ended'], 1 );

$GLOBALS['status'] = array();
$GLOBALS['manage'] = true;
WPCPM_Privacy_Guard::deny_author_archives();
ck( 'a program manager still reaches the archive', $GLOBALS['status'], array() );

$GLOBALS['manage'] = false;
$GLOBALS['author'] = false;
WPCPM_Privacy_Guard::deny_author_archives();
ck( 'and a page that is not an archive is untouched', $GLOBALS['status'], array() );

echo "\n=== The users sitemap and the users REST route ===\n";

ck( 'the users provider is dropped', WPCPM_Privacy_Guard::drop_users_sitemap( 'provider', 'users' ), false );
ck( 'and every other provider kept', WPCPM_Privacy_Guard::drop_users_sitemap( 'provider', 'posts' ), 'provider' );

$refused = WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/users' ) );
ck( 'a visitor listing users is refused', $refused instanceof WP_Error ? $refused->get_error_data()['status'] : 'allowed', 401 );
$refused = WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/users/7' ) );
ck( 'and reading one user', $refused instanceof WP_Error, true );
$GLOBALS['uid'] = 7;
ck( 'a signed-in person may read their own record', WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/users/7' ) ), null );
ck( 'and /me', WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/users/me' ) ), null );
$refused = WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/users/8' ) );
ck( 'but not somebody else\'s', $refused instanceof WP_Error ? $refused->get_error_data()['status'] : 'allowed', 403 );
ck( 'nor the list', WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/users' ) ) instanceof WP_Error, true );
$GLOBALS['manage'] = true;
ck( 'a manager may list users', WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/users' ) ), null );
$GLOBALS['manage'] = false;
ck( 'other routes pass through untouched', WPCPM_Privacy_Guard::refuse_user_routes( null, null, new WP_REST_Request( '/wp/v2/posts' ) ), null );
ck( 'and a result already decided is left alone', WPCPM_Privacy_Guard::refuse_user_routes( 'decided', null, new WP_REST_Request( '/wp/v2/users' ) ), 'decided' );

echo "\n=== The rows written before the guard are flipped once ===\n";

$GLOBALS['db'] = array(
	1 => array( 'type' => 'wpcpm_mentor_call', 'status' => 'publish' ),
	2 => array( 'type' => 'wpcpm_mentor_note', 'status' => 'publish' ),
	3 => array( 'type' => 'wpcpm_audit_entry', 'status' => 'publish' ),
	4 => array( 'type' => 'post', 'status' => 'publish' ),
	5 => array( 'type' => 'wpcpm_mentor_call', 'status' => 'trash' ),
);
WPCPM_Privacy_Guard::maybe_upgrade();
ck( 'the three private types are made private', array( $GLOBALS['db'][1]['status'], $GLOBALS['db'][2]['status'], $GLOBALS['db'][3]['status'] ), array( 'private', 'private', 'private' ) );
ck( 'a real post is not', $GLOBALS['db'][4]['status'], 'publish' );
ck( 'nor a trashed row', $GLOBALS['db'][5]['status'], 'trash' );
ck( 'the schema version is recorded', (int) get_option( WPCPM_Privacy_Guard::OPT_VERSION ), WPCPM_Privacy_Guard::SCHEMA_VERSION );
$before = count( $wpdb->updates );
WPCPM_Privacy_Guard::maybe_upgrade();
ck( 'and a second load does nothing', count( $wpdb->updates ), $before );

echo "\n=== Nothing the plugin keeps about people is published ===\n";

$publish = array();
foreach ( array( 'class-wpcpm-mentor-calls.php', 'class-wpcpm-mentor-notes.php', 'class-wpcpm-institution-notes.php', 'class-wpcpm-institution-audit.php', 'class-wpcpm-group-sessions.php' ) as $file ) {
	$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/' . $file );
	if ( preg_match( "/'post_status'\s*=>\s*'publish'/", $src ) ) {
		$publish[] = $file;
	}
}
ck( 'no call, note, session or audit row is inserted as publish', $publish, array() );
ck( 'the guard names the types it flips', WPCPM_Privacy_Guard::private_post_types(), array( 'wpcpm_mentor_call', 'wpcpm_mentor_note', 'wpcpm_audit_entry' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
