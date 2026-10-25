<?php
/**
 * The Administrator guide moves from /program-manager-guide/ to /administrator-guide/ (1.123.41).
 *
 * The guide is a page the release script writes by its ID from docs/build/administrators.html, and the
 * plugin finds it by its path, so the path is the one thing a move has to change: `GUIDE_PAGE_PATH`, which
 * the Settings screen's section intros link, and the page's own slug on a site that has the page already.
 * Both are pinned on the model the application form's page has (bin/test-institution-application.php).
 *
 * - **The move takes the slug and nothing else, and only while the slug is the plugin's.** A page at the old
 *   path, found by the path because the plugin stores no ID for it, gets the new slug through one write to the
 *   posts table that sets `post_name` where the ID and the old slug match. A page another page, post or
 *   attachment shadows is left where it is; so is one whose slug an Administrator changed, and a page that is
 *   only a child of another page (its path is not the old path).
 * - **No save runs.** `wp_update_post()` would fire `save_post` for the guide on whichever request comes first
 *   after the update, and a handler that reads the request (the access level's box takes what any post's form
 *   posts) would write another post's level onto the guide; it would also pass the stored content through
 *   kses on a request without `unfiltered_html`, which rewrites the markup the release script compares with its
 *   build, and bump the modified date. So the update is never called, no action fires, no post meta is written,
 *   and the content, title, status and modified date are left as they were; the page cache is cleared only when
 *   a row changed, which is also what keeps two first requests from both renaming it.
 * - **Once only, the flag first.** The option is written before the write is asked for, whatever the outcome,
 *   so a move that fails halfway is not tried on every request, and a slug put back by hand afterwards is
 *   an Administrator's.
 * - **The old address answers a 404 at exactly that path, with a 301.** The path WordPress matched and the
 *   query variables are both asked, as they are for /apply/, and the query string goes with the visitor. A
 *   page really sitting at the old path (the move skipped) is served, and with no guide published there is
 *   nothing to send anybody to.
 * - **The Settings screen finds the guide at the new path.**
 *
 * Run from the plugin root:  php bin/test-guide-address.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['opts']       = array();
$GLOBALS['autoload']   = array();
$GLOBALS['posts']      = array();
$GLOBALS['hooks']      = array();
$GLOBALS['redirects']  = array();
$GLOBALS['updates']    = array();
$GLOBALS['cleaned']    = array();
$GLOBALS['actions']    = array();
$GLOBALS['meta_writes'] = array();
$GLOBALS['save_handlers'] = array();
$GLOBALS['is_404']     = false;
$GLOBALS['query_vars'] = array();

class WP_Post {
	public $ID = 0, $post_type = 'page', $post_status = 'publish', $post_parent = 0, $post_title = '', $post_name = '', $post_content = '', $post_modified = '';
}

/**
 * The database, as far as the move reaches it: one `update()` of the posts table, applied to the posts above
 * with every column of the WHERE required to match, as MySQL does, and answering the rows it changed. A call
 * is kept with whether the move's flag was already written when it came.
 *
 * `$before` is run first, to let a test make another request write the row between a lookup and this write,
 * and `$returns` makes the write answer what a test says instead (0 for no row, false for an error).
 */
class WPCPM_Test_Wpdb {
	public $posts   = 'wp_posts';
	public $calls   = array();
	public $before  = null;
	public $returns = null;

	public function update( $table, $data, $where ) {
		$this->calls[] = array(
			'table'   => $table,
			'data'    => $data,
			'where'   => $where,
			'stamped' => get_option( 'wpcpm_guide_page_moved', false ),
		);

		if ( null !== $this->before ) {
			call_user_func( $this->before );
		}

		if ( null !== $this->returns ) {
			return $this->returns;
		}

		$post = $GLOBALS['posts'][ (int) ( $where['ID'] ?? 0 ) ] ?? null;

		if ( ! $post || ( isset( $where['post_name'] ) && $where['post_name'] !== $post->post_name ) ) {
			return 0;
		}

		foreach ( $data as $column => $value ) {
			$post->$column = $value;
		}

		return 1;
	}
}

$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

function __( $s, $d = null ) { return $s; }
function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['hooks'][] = array( $h, $c, $p ); }
function apply_filters( $hook, $value ) { return $value; }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;

	return true;
}

// Only what the class needs of it: an http(s) address, or nothing.
function esc_url_raw( $u, $protocols = null ) {
	return preg_match( '#^https?://[^\s<>"]+$#i', (string) $u ) ? (string) $u : '';
}

function get_permalink( $post ) {
	$post = $post instanceof WP_Post ? $post : ( $GLOBALS['posts'][ (int) $post ] ?? null );

	return $post ? 'https://example.test/' . $post->post_name . '/' : false;
}

/**
 * Core's lookup by path, for a top-level slug: a page or an attachment by default, the types named when
 * it is given a list, in any status, and only a page that has no parent, since a child's path names its
 * parent first.
 */
function get_page_by_path( $slug, $output = 'OBJECT', $post_type = 'page' ) {
	$types = is_array( $post_type ) ? $post_type : array( $post_type, 'attachment' );

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $slug === $post->post_name && in_array( $post->post_type, $types, true ) && 0 === $post->post_parent ) {
			return $post;
		}
	}

	return null;
}

// Recorded, as `clean_post_cache()` would drop the page from the cache.
function clean_post_cache( $id ) { $GLOBALS['cleaned'][] = (int) $id; }

// Recorded, and the `save_post` handlers a test registers run, as core runs the ones a site has.
function do_action( $hook, ...$args ) {
	$GLOBALS['actions'][] = $hook;

	if ( 'save_post' === $hook ) {
		foreach ( $GLOBALS['save_handlers'] as $handler ) {
			call_user_func_array( $handler, $args );
		}
	}
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta_writes'][] = array( (int) $id, $key, $value ); return true; }

/**
 * What the move must not call. It does what core's update does on a request without `unfiltered_html`: the stored
 * post is merged with what is handed over, the content goes through kses again (an image closed with a bare slash
 * gets a space before it, an apostrophe written as a number is written `&apos;`, the two places measured on core),
 * the modified date moves, and `save_post` fires. So a move that reaches for it fails the pins that follow.
 */
function wp_update_post( $a, $error = false ) {
	$post                 = $GLOBALS['posts'][ (int) ( $a['ID'] ?? 0 ) ] ?? null;
	$GLOBALS['updates'][] = $a;

	if ( ! $post ) {
		return 0;
	}

	foreach ( $a as $field => $value ) {
		if ( 'ID' !== $field ) {
			$post->$field = stripslashes( (string) $value );
		}
	}

	$post->post_content  = str_replace( array( '"/>', '&#039;' ), array( '" />', '&apos;' ), $post->post_content );
	$post->post_modified = '2026-10-25 12:00:00';

	do_action( 'save_post', $post->ID, $post, true );

	return $post->ID;
}

// Recorded with its status, then thrown, so a handler's `exit` after it is never reached.
function wp_safe_redirect( $to, $status = 302 ) {
	$GLOBALS['redirects'][] = array( $to, $status );
	throw new Exception( 'redirect: ' . $to );
}
function is_404() { return (bool) $GLOBALS['is_404']; }
function get_query_var( $name, $fallback = '' ) { return array_key_exists( $name, $GLOBALS['query_vars'] ) ? $GLOBALS['query_vars'][ $name ] : $fallback; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';

// The guide as the release script wrote it: an image closed with a bare slash and an apostrophe written as a
// number, the two places kses rewrites in the real guide.
define( 'GUIDE_HTML', "<!-- wp:paragraph -->\n<p>The guide, as the release script wrote it.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"https://example.test/a.png\" alt=\"The Administrators&#039; view\"/></figure>\n<!-- /wp:image -->" );

define( 'MODIFIED', '2026-10-01 10:00:00' );

// The access level's box, as `WPCPM_Content_Access::save_meta_box()` takes it: any post's save that carries the
// box's nonce field writes the posted level, or public, onto the post being saved.
$GLOBALS['save_handlers'][] = static function ( $post_id ) {
	if ( isset( $_POST['wpcpm_access_level'] ) ) {
		update_post_meta( $post_id, '_wpcpm_access_level', isset( $_POST['_wpcpm_access_level'] ) ? $_POST['_wpcpm_access_level'] : 'public' );
	}
};

/** Everything a write could leave a trace in, for a check that nothing was written. */
function writes_made() {
	return array( $GLOBALS['wpdb']->calls, $GLOBALS['cleaned'], $GLOBALS['updates'], $GLOBALS['actions'], $GLOBALS['meta_writes'] );
}

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
 * A post the site holds.
 *
 * @param int    $id     The ID.
 * @param string $slug   The slug.
 * @param string $type   The post type.
 * @param string $status The status.
 * @param int    $parent The parent page's ID.
 * @return WP_Post
 */
function put_post( $id, $slug, $type = 'page', $status = 'publish', $parent = 0 ) {
	$post               = new WP_Post();
	$post->ID           = $id;
	$post->post_name    = $slug;
	$post->post_type    = $type;
	$post->post_status  = $status;
	$post->post_parent  = $parent;
	$post->post_title   = 'Administrator guide';
	$post->post_content = GUIDE_HTML;
	$post->post_modified = MODIFIED;

	$GLOBALS['posts'][ $id ] = $post;

	return $post;
}

/**
 * Forget every option, post and recorded call, and put the site back as live holds it before the move:
 * the guide published as page 560 at the old path.
 *
 * @return WP_Post The guide.
 */
function reset_world() {
	$GLOBALS['opts']       = array();
	$GLOBALS['autoload']   = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['hooks']      = array();
	$GLOBALS['redirects']  = array();
	$GLOBALS['updates']    = array();
	$GLOBALS['cleaned']    = array();
	$GLOBALS['actions']    = array();
	$GLOBALS['meta_writes'] = array();
	$GLOBALS['is_404']     = false;
	$GLOBALS['query_vars'] = array();
	$GLOBALS['wp']         = (object) array( 'request' => '' );
	$GLOBALS['wpdb']       = new WPCPM_Test_Wpdb();

	unset( $_SERVER['QUERY_STRING'], $_POST['wpcpm_access_level'], $_POST['_wpcpm_access_level'] );

	return put_post( 560, WPCPM_Settings_Screen::GUIDE_OLD_PATH );
}

/**
 * Asks `redirect_old_guide_address()` about one request and says where it sent the visitor.
 *
 * @param string $path   The path WordPress matched, as `$wp->request` holds it.
 * @param array  $vars   The query variables the request ended with.
 * @param bool   $is_404 Whether WordPress found nothing there.
 * @param string|null $query The request's query string, if it had one; an empty one is a string too.
 * @return array|null The address and the status, or null when nothing was sent.
 */
function old_guide_address( $path, array $vars, $is_404 = true, $query = null ) {
	$GLOBALS['wp']         = (object) array( 'request' => $path );
	$GLOBALS['query_vars'] = $vars;
	$GLOBALS['is_404']     = $is_404;
	$GLOBALS['redirects']  = array();

	if ( null !== $query ) {
		$_SERVER['QUERY_STRING'] = $query;
	} else {
		unset( $_SERVER['QUERY_STRING'] );
	}

	try {
		WPCPM_Settings_Screen::redirect_old_guide_address();
	} catch ( Exception $e ) {
		// The stand-in's redirect, recorded before it was thrown.
		unset( $e );
	}

	return $GLOBALS['redirects'] ? $GLOBALS['redirects'][0] : null;
}

echo "-- the two paths and the flag ---------------------------------------\n";

ck( 'the guide is at administrator-guide, and its address before is kept by name', array( WPCPM_Settings_Screen::GUIDE_PAGE_PATH, WPCPM_Settings_Screen::GUIDE_OLD_PATH ), array( 'administrator-guide', 'program-manager-guide' ) );
ck( 'the move\'s flag is an option in the plugin\'s own style, at revision 1', array( WPCPM_Settings_Screen::OPT_GUIDE_MOVED, WPCPM_Settings_Screen::GUIDE_MOVE_VERSION ), array( 'wpcpm_guide_page_moved', 1 ) );

echo "\n-- the move ---------------------------------------------------------\n";

// A first request after the update that is an Administrator saving another post: its form posts the access
// level's box, which a `save_post` handler would write onto whatever post is being saved.
$guide = reset_world();
$_POST['wpcpm_access_level']  = 'nonce';
$_POST['_wpcpm_access_level'] = 'public';
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'the page at the old path is renamed by one write to the posts table', count( $GLOBALS['wpdb']->calls ), 1 );
ck( 'which sets post_name and nothing else, where the ID and the old slug match, with the flag written before the work so a move that fails halfway is not tried on every request',
	$GLOBALS['wpdb']->calls[0],
	array( 'table' => 'wp_posts', 'data' => array( 'post_name' => 'administrator-guide' ), 'where' => array( 'ID' => 560, 'post_name' => 'program-manager-guide' ), 'stamped' => 1 ) );
ck( 'the page\'s cache is cleared, by its ID', $GLOBALS['cleaned'], array( 560 ) );
ck( 'wp_update_post() is never called', $GLOBALS['updates'], array() );
ck( 'no action fires, save_post least of all', $GLOBALS['actions'], array() );
ck( 'so with an access level posted nothing writes post meta', $GLOBALS['meta_writes'], array() );
ck( 'the flag is the move\'s revision, and not autoloaded', array( get_option( WPCPM_Settings_Screen::OPT_GUIDE_MOVED ), $GLOBALS['autoload'][ WPCPM_Settings_Screen::OPT_GUIDE_MOVED ] ), array( 1, false ) );
ck( 'the page keeps its ID, title, content (byte for byte), status and modified date, and answers at the new path', array( $guide->ID, $guide->post_title, $guide->post_content, $guide->post_status, $guide->post_modified, $guide->post_name ), array( 560, 'Administrator guide', GUIDE_HTML, 'publish', MODIFIED, 'administrator-guide' ) );
ck( 'and nothing is left at the old path', get_page_by_path( 'program-manager-guide' ), null );

// The pins above can fail: the stand-in handler writes when `save_post` does fire for the page.
do_action( 'save_post', 560, $guide, true );

ck( 'the stand-in for the access level\'s box writes the posted level when save_post fires, so a move that fired it would fail the pin above', $GLOBALS['meta_writes'], array( array( 560, '_wpcpm_access_level', 'public' ) ) );

unset( $_POST['wpcpm_access_level'], $_POST['_wpcpm_access_level'] );

// A rename another request made between this one's lookup and its write changes no row: the WHERE names the old
// slug, so two first requests cannot both rename it, and the second clears nothing.
$guide = reset_world();
$GLOBALS['wpdb']->before = static function () use ( $guide ) {
	$guide->post_name = 'administrator-guide';
};
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a request that lost the race to rename the page writes nothing and clears no cache', array( count( $GLOBALS['wpdb']->calls ), $guide->post_name, $GLOBALS['cleaned'], $GLOBALS['updates'] ), array( 1, 'administrator-guide', array(), array() ) );

$guide = reset_world();
$GLOBALS['wpdb']->returns = 0;
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a write that changed no row clears no cache', $GLOBALS['cleaned'], array() );

$guide = reset_world();
$GLOBALS['wpdb']->returns = false;
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'nor does one the database refused', array( $GLOBALS['cleaned'], get_option( WPCPM_Settings_Screen::OPT_GUIDE_MOVED ) ), array( array(), 1 ) );

// Every path that returns before the write leaves every trace empty.
$early = array();

$guide = reset_world();
$GLOBALS['opts'][ WPCPM_Settings_Screen::OPT_GUIDE_MOVED ] = 1;
WPCPM_Settings_Screen::maybe_move_guide_page();
$early['the move already done'] = writes_made();

reset_world();
unset( $GLOBALS['posts'][560] );
WPCPM_Settings_Screen::maybe_move_guide_page();
$early['no page at the old path'] = writes_made();

$guide = reset_world();
$guide->post_name = 'handbook-for-administrators';
WPCPM_Settings_Screen::maybe_move_guide_page();
$early['a slug changed by hand'] = writes_made();

foreach ( array( 'page', 'post', 'attachment' ) as $type ) {
	reset_world();
	put_post( 77, 'administrator-guide', $type );
	WPCPM_Settings_Screen::maybe_move_guide_page();
	$early[ 'a ' . $type . ' at the new path' ] = writes_made();
}

$guide = reset_world();
put_post( 61, 'handbook', 'page' );
$guide->post_parent = 61;
WPCPM_Settings_Screen::maybe_move_guide_page();
$early['a child page'] = writes_made();

reset_world();
unset( $GLOBALS['posts'][560] );
put_post( 91, 'program-manager-guide', 'attachment', 'inherit' );
WPCPM_Settings_Screen::maybe_move_guide_page();
$early['an attachment at the old path'] = writes_made();

ck( 'nothing is written, cleared or fired on every path that returns before the write',
	$early,
	array_fill_keys( array_keys( $early ), array( array(), array(), array(), array(), array() ) ) );

// Once only: a slug put back by hand afterwards is an Administrator's.
$guide->post_name   = 'program-manager-guide';
$GLOBALS['wpdb']->calls = array();
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a second run does nothing, so a slug an Administrator put back by hand is left alone', array( $GLOBALS['wpdb']->calls, $guide->post_name ), array( array(), 'program-manager-guide' ) );

$guide = reset_world();
$guide->post_status = 'draft';
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a draft guide is renamed as well, and stays a draft', array( $guide->post_name, $guide->post_status ), array( 'administrator-guide', 'draft' ) );

// Another post already at the new path keeps it: core would hand the page `administrator-guide-2`, or,
// for a post, hide it behind the page.
foreach ( array( 'a page' => 'page', 'a post' => 'post', 'an attachment' => 'attachment' ) as $kind => $type ) {
	$guide = reset_world();
	put_post( 77, 'administrator-guide', $type );
	WPCPM_Settings_Screen::maybe_move_guide_page();

	ck( 'with ' . $kind . ' already at the new path nothing is renamed', array( $GLOBALS['wpdb']->calls, $guide->post_name ), array( array(), 'program-manager-guide' ) );
	ck( 'and the move is stamped all the same', get_option( WPCPM_Settings_Screen::OPT_GUIDE_MOVED ), 1 );
}

$guide = reset_world();
$guide->post_name = 'handbook-for-administrators';
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a guide whose slug an Administrator changed is not found at the old path, and nothing is touched', array( $GLOBALS['wpdb']->calls, $guide->post_name, get_option( WPCPM_Settings_Screen::OPT_GUIDE_MOVED ) ), array( array(), 'handbook-for-administrators', 1 ) );

$guide = reset_world();
put_post( 61, 'handbook', 'page' );
$guide->post_parent = 61;
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a page that is only a child of another page is not at the old path, and is left alone', array( $GLOBALS['wpdb']->calls, $guide->post_name ), array( array(), 'program-manager-guide' ) );

reset_world();
unset( $GLOBALS['posts'][560] );
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a site with no guide at the old path has nothing moved, and is stamped', array( $GLOBALS['wpdb']->calls, get_option( WPCPM_Settings_Screen::OPT_GUIDE_MOVED ) ), array( array(), 1 ) );

$guide = reset_world();
put_post( 90, 'program-manager-guide', 'post' );
unset( $GLOBALS['posts'][560] );
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a post at the old path is not the guide, which is a page', array( $GLOBALS['wpdb']->calls, $GLOBALS['posts'][90]->post_name ), array( array(), 'program-manager-guide' ) );

// Core's lookup by path answers an attachment too, and an attachment is not the guide.
reset_world();
unset( $GLOBALS['posts'][560] );
put_post( 91, 'program-manager-guide', 'attachment', 'inherit' );
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'an attachment at the old path is not the guide, and is left alone', array( $GLOBALS['wpdb']->calls, $GLOBALS['posts'][91]->post_name ), array( array(), 'program-manager-guide' ) );

$guide = reset_world();
$GLOBALS['opts'][ WPCPM_Settings_Screen::OPT_GUIDE_MOVED ] = 1;
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'a site that has done the move is not asked again, even with a page at the old path', array( $GLOBALS['wpdb']->calls, $guide->post_name ), array( array(), 'program-manager-guide' ) );

echo "\n-- the old address --------------------------------------------------\n";

reset_world();
WPCPM_Settings_Screen::maybe_move_guide_page();

$new = 'https://example.test/administrator-guide/';

ck( 'a 404 at /program-manager-guide/ that WordPress read as a page sends a 301 to the guide', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ) ), array( $new, 301 ) );
// Under /%postname%/ links a missing page falls through to the post rule, which sets `name`.
ck( 'and so does one it read as a post', old_guide_address( 'program-manager-guide', array( 'name' => 'program-manager-guide' ) ), array( $new, 301 ) );
ck( 'the query string goes with the visitor', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ), true, 'utm_source=slides' ), array( $new . '?utm_source=slides', 301 ) );
ck( 'every part of it', old_guide_address( 'program-manager-guide', array( 'name' => 'program-manager-guide' ), true, 'a=1&b=two' ), array( $new . '?a=1&b=two', 301 ) );
// A query string of "0" is a query string: `empty( '0' )` is true, and it must not be dropped.
ck( 'a query string of 0 goes with the visitor too', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ), true, '0' ), array( $new . '?0', 301 ) );
ck( 'while an empty one, set but empty, adds no question mark', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ), true, '' ), array( $new, 301 ) );

// A real page published at the old path (the move skipped, because something held the new one) is not a 404.
ck( 'a page that is there is not a 404, and is left alone', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ), false ), null );
ck( 'any other path is left alone', old_guide_address( 'about', array( 'pagename' => 'about' ) ), null );
ck( 'so is a slug that starts with the old one', old_guide_address( 'program-manager-guide-2', array( 'name' => 'program-manager-guide-2' ) ), null );
ck( 'and the new path itself, which is no address to send anybody away from', old_guide_address( 'administrator-guide', array( 'pagename' => 'administrator-guide' ) ), null );

// By `template_redirect` WP_Query has cut a missing page's path to its last part and copied it into `name`,
// and a post type's address sets `name` too: the query variables alone would send /about/program-manager-guide/
// and /<type>/program-manager-guide/ to the guide.
ck( 'a deeper path whose last part is the old slug is left alone', old_guide_address( 'about/program-manager-guide', array( 'pagename' => 'program-manager-guide', 'name' => 'program-manager-guide' ) ), null );
ck( 'and so is a post type\'s address that ends in it', old_guide_address( 'sponsor-posts/program-manager-guide', array( 'name' => 'program-manager-guide', 'post_type' => 'wpcpm_sponsor_post' ) ), null );
ck( 'and so is the old path where WordPress read something else there', old_guide_address( 'program-manager-guide', array( 'category_name' => 'program-manager-guide' ) ), null );
ck( 'and a variable that is not a string is not read as the slug', old_guide_address( 'program-manager-guide', array( 'pagename' => array( 'program-manager-guide' ) ) ), null );

// Page addresses are matched without regard to case, so /Program-Manager-Guide/ served the guide before the move.
ck( '/Program-Manager-Guide/ typed with capitals is sent to the guide too', old_guide_address( 'Program-Manager-Guide', array( 'pagename' => 'Program-Manager-Guide' ) ), array( $new, 301 ) );
ck( 'and /PROGRAM-MANAGER-GUIDE/, read as a post, with its query string', old_guide_address( 'PROGRAM-MANAGER-GUIDE', array( 'name' => 'PROGRAM-MANAGER-GUIDE' ), true, 'x=1' ), array( $new . '?x=1', 301 ) );
ck( 'a deeper path ending in the old slug in capitals is left alone', old_guide_address( 'about/Program-Manager-Guide', array( 'pagename' => 'Program-Manager-Guide', 'name' => 'Program-Manager-Guide' ) ), null );

$GLOBALS['posts'][560]->post_status = 'draft';
ck( 'with the guide not published there is nowhere to send anybody', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ) ), null );
$GLOBALS['posts'][560]->post_status = 'private';
ck( 'nor with it private', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ) ), null );

unset( $GLOBALS['posts'][560] );
ck( 'nor on a site with no guide at all', old_guide_address( 'program-manager-guide', array( 'pagename' => 'program-manager-guide' ) ), null );

echo "\n-- the hooks --------------------------------------------------------\n";

// Before core's `redirect_canonical()` and `wp_old_slug_redirect()`, both at 10, so core's guess at a post whose
// slug starts with the old one cannot send the visitor somewhere else; and the move after `init`'s first
// stretch, at 20, where the application form's page has it. The Settings screen is built on every request
// (`WPCPM_Admin`), not only in wp-admin, which is why the redirect can live there.
reset_world();
new WPCPM_Settings_Screen();
$hooked = array();

foreach ( $GLOBALS['hooks'] as $hook ) {
	if ( is_array( $hook[1] ) && in_array( $hook[1][1], array( 'redirect_old_guide_address', 'maybe_move_guide_page' ), true ) ) {
		$hooked[ $hook[1][1] ] = array( $hook[0], $hook[2] );
	}
}

ck( 'the redirect is on template_redirect at 9, and the move on init at 20', $hooked, array(
	'maybe_move_guide_page'      => array( 'init', 20 ),
	'redirect_old_guide_address' => array( 'template_redirect', 9 ),
) );

// And the hooks are added where the screen is built, and the screen is built on every request: an
// `is_admin()` gate anywhere on the way would keep the redirect from the front end, where visitors are.
$screen_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php' );
$admin_source  = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php' );
$main_source   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );

preg_match( '/public function __construct\(\) \{(.*?)\n\t\}/s', $screen_source, $screen_constructor );
preg_match( '/public function __construct\(\) \{(.*?)\n\t\}/s', $admin_source, $admin_constructor );
preg_match( '/function wpcpm_bootstrap\(\) \{(.*?)\n\}/s', $main_source, $bootstrap );

$screen_constructor = $screen_constructor[1] ?? '';
$admin_constructor  = $admin_constructor[1] ?? '';
$bootstrap          = $bootstrap[1] ?? '';

ck( 'the two hooks are added in the Settings screen\'s constructor, with no condition on the way',
	array(
		false !== strpos( $screen_constructor, "\n\t\tadd_action( 'init', array( __CLASS__, 'maybe_move_guide_page' ), 20 );" ),
		false !== strpos( $screen_constructor, "\n\t\tadd_action( 'template_redirect', array( __CLASS__, 'redirect_old_guide_address' ), 9 );" ),
		false === strpos( $screen_constructor, 'is_admin' ) && false === strpos( $screen_constructor, 'if (' ),
	),
	array( true, true, true ) );
ck( 'the screen is built by WPCPM_Admin\'s constructor, and the plugin builds WPCPM_Admin on every request, outside any is_admin() gate',
	array(
		false !== strpos( $admin_constructor, "\n\t\t\$this->settings = new WPCPM_Settings_Screen();" ),
		false === strpos( $admin_constructor, 'is_admin' ),
		false !== strpos( $bootstrap, "\n\tnew WPCPM_Admin();" ),
		false === strpos( $bootstrap, 'is_admin' ),
		1 === preg_match( "/add_action\( 'plugins_loaded', 'wpcpm_bootstrap' \);/", $main_source ),
	),
	array( true, true, true, true, true ) );

echo "\n-- the Settings screen finds the guide at the new path ----------------\n";

$settings_guide = new ReflectionMethod( 'WPCPM_Settings_Screen', 'settings_guide' );

if ( PHP_VERSION_ID < 80100 ) {
	$settings_guide->setAccessible( true );
}

reset_world();
put_post( 561, 'administrator-guide' );

ck( 'a published page at the new path is the guide the intros link', $settings_guide->invoke( null ), array( 'url' => 'https://example.test/administrator-guide/', 'label' => 'Administrator guide' ) );

reset_world();

ck( 'a page still at the old path is not found there, so the intros say their sentence alone until the move has run', $settings_guide->invoke( null ), null );

reset_world();
WPCPM_Settings_Screen::maybe_move_guide_page();

ck( 'and once the move has run, the same page is the guide', $settings_guide->invoke( null ), array( 'url' => 'https://example.test/administrator-guide/', 'label' => 'Administrator guide' ) );

echo "\n-- what is written about the address ----------------------------------\n";

$settings_source  = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php' );
$dashboard_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php' );
$settings_guide_md = (string) preg_replace( '/\s+/', ' ', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/31-admin-settings.md' ) );
$built_guide_md    = (string) preg_replace( '/\s+/', ' ', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/administrators.md' ) );
$built_guide_html  = (string) preg_replace( '/\s+/', ' ', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/build/administrators.html' ) );
$sentence          = 'on a site that publishes the guide at /administrator-guide/';

ck( 'the Settings screen\'s docblocks name the new path, and the dashboard\'s comment the new page',
	array( false === strpos( $settings_source, '`program-manager-guide`, or' ), false !== strpos( $settings_source, '`administrator-guide`, or' ), false !== strpos( $dashboard_source, '/administrator-guide/ page' ), false === strpos( $dashboard_source, '/program-manager-guide/ page' ) ),
	array( true, true, true, true ) );
ck( 'the Settings part of the guide names the new address and says the old one redirects, in the source and in the built guide, Markdown and block markup',
	array(
		false !== strpos( $settings_guide_md, $sentence ),
		false !== strpos( $settings_guide_md, '/program-manager-guide/, its address before' ),
		false !== strpos( $built_guide_md, $sentence ),
		false !== strpos( $built_guide_html, $sentence ),
	),
	array( true, true, true, true ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
