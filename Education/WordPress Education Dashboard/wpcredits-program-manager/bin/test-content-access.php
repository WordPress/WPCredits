<?php
/**
 * WPCPM_Content_Access: the query filter gates what it says it gates.
 *
 * The class docblock promises that "the query filter hides restricted posts from listings". It
 * did not, on the one query that matters most: the main query on the blog index, the Updates
 * category, every feed and front-end search has no `post_type` set when `pre_get_posts` fires
 * (WordPress fills it in afterwards), and `(array) ''` is a non-empty array that intersects
 * nothing, so the filter returned before adding its meta query. A logged-out visitor could list
 * every gated title and confirm phrases inside gated bodies with `?s=`. Stubs only what the
 * method touches; the cases are the ones the review reproduced.
 *
 * Run from the plugin root:  php bin/test-content-access.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function is_admin() { return false; }
function get_current_user_id() { return 0; }
function user_can( $u, $c ) { return false; }
function apply_filters( $tag, $value ) { return $value; }
function __( $s, $d = null ) { return $s; }
function get_post( $p = null ) { return null; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; }

class WP_Query {
	public $vars      = array( 'post_type' => '' );
	public $set_calls = array();
	public function __construct( $vars = array() ) { $this->vars = array_merge( $this->vars, $vars ); }
	public function is_singular() { return false; }
	public function get( $k, $d = '' ) { return isset( $this->vars[ $k ] ) ? $this->vars[ $k ] : $d; }
	public function set( $k, $v ) { $this->set_calls[ $k ] = $v; }
}
class WP_Role {}
class WP_User {}
class WPCPM_Roles {
	const ROLE_ADMIN = 'administrator';
	const CAP_MANAGE = 'wpcpm_manage_program';
	public static function custom_roles() { return array( 'wpcpm_student' => array( 'label' => 'Student', 'cap' => 'wpcpm_view_student_content' ) ); }
	public static function resolve_user( $u = null ) { return null; }
}

require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-content-access.php';

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
function gated( WP_Query $q ) {
	WPCPM_Content_Access::filter_queries( $q );
	return isset( $q->set_calls['meta_query'] );
}

echo "=== The main query is gated, whatever WordPress has filled in by then ===\n";

ck( 'the home, archive, feed and search query, whose post_type is still empty, is gated', gated( new WP_Query() ), true );
ck( 'an explicit post query is gated', gated( new WP_Query( array( 'post_type' => 'post' ) ) ), true );
ck( 'a query for every type is gated', gated( new WP_Query( array( 'post_type' => 'any' ) ) ), true );
ck( 'an array of types that includes ours is gated', gated( new WP_Query( array( 'post_type' => array( 'nav_menu_item', 'page' ) ) ) ), true );
ck( 'a query for other types only is left alone', gated( new WP_Query( array( 'post_type' => 'attachment' ) ) ), false );
ck( 'and so is an array of other types', gated( new WP_Query( array( 'post_type' => array( 'attachment', 'revision' ) ) ) ), false );
ck( 'an empty string inside an array counts for nothing', gated( new WP_Query( array( 'post_type' => array( '', 'attachment' ) ) ) ), false );

echo "\n=== Feeds have their own hooks, and both are taken ===\n";

$GLOBALS['hooks'] = array();
WPCPM_Content_Access::init();
ck( 'the feed body runs through the content filter', in_array( 'the_content_feed', $GLOBALS['hooks'], true ), true );
ck( 'and the feed excerpt, which never passes the_excerpt, through the excerpt filter', in_array( 'the_excerpt_rss', $GLOBALS['hooks'], true ), true );

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-content-access.php' );
ck( 'the docblock says listings, feeds and search, which is now true', false !== strpos( $src, 'listings, feeds and search' ), true );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
