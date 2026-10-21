<?php
/**
 * WPCPM_Sponsors_Page: the Partners page, `/partners/`, that the plugin creates in draft, that the
 * launch switch publishes and returns to draft, that no save publishes while the switch is off,
 * and that a visitor is refused while the switch is off.
 *
 * What is pinned here and why:
 *
 * - maybe_ensure() makes the page on a program manager's admin request alone, and once: never on a
 *   front-end request, and never on an admin request by anyone else, since the public application
 *   form and every signed-in form post to admin-post.php or admin-ajax.php, where is_admin() is
 *   true. A draft at its slug, with its title, recorded in an autoloaded option, since every admin
 *   request reads it. No page meta gates the page, so it is public the moment it is published. A
 *   file uploaded as "partners" and never attached sits at the slug for the whole run: asked for
 *   one type, core's lookup answers such a file when no page matches and core counts it as
 *   published, and it is never taken for the page. With the switch on, a page made on an admin
 *   request is published at once, so a page trashed after the launch comes back the way the switch
 *   says.
 * - The content it is made with, exactly: the "Our partners" heading, one sentence saying the
 *   partners are listed in alphabetical order, and the full Sponsors block, wide. The theme's
 *   page-partners.html template prints the rest of the partner page's text around the page's own
 *   content (1.123.18), so no tier, amount, benefit or button is the plugin's to write, and the
 *   content reads nothing of the application page. No em dash or en dash in any of it.
 * - ensure_page() adopts a page at the slug when the option is gone rather than making a second
 *   one; a trashed page counts as none, and a fresh draft is made beside it.
 * - follow_switch(): the switch turned on publishes the page, making it first when there is none;
 *   turned off, the page goes back to draft, a scheduled one with it, while a private or pending
 *   one stays as a manager set it; a save that leaves the switch where it was changes nothing.
 *   Turned off with the page in the trash, it makes no page: there is nothing to take back.
 *   page_url() is '' unless the page is published.
 * - keep_draft(): while the switch is off, a save of the page to any status but the trash lands as
 *   a draft, a restore from the trash straight to publish included, and another page's save is
 *   never touched; once the switch is on, a publish lands.
 * - The content is written once, at creation: a manager's edit survives an adoption and both
 *   flips of the switch.
 * - guard(): while the switch reads off with the page published all the same, a visitor asking
 *   for the page gets a 404, sent with the headers that keep a cache from storing it, and the
 *   embed view goes with it; a program manager reads the page; once the switch is on, everyone
 *   does; another page is never touched; nothing is sent for a request the guard lets through,
 *   and a request that is not the page reads no post status on the way.
 * - init() hooks each of the four where it belongs, with the arguments each takes, and the wiring
 *   loads the class, starts it with the Sponsors module's other front-end classes and makes the
 *   page on activation after the application page its button points at, then puts it where the
 *   switch says (bin/test-sponsors-screen.php runs that activation).
 * - The house rules: the insert is the one array in the class that names the content, and the
 *   class names no base and carries no em dash or en dash.
 *
 * Posts come from bin/stubs/posts.php, with what it lacks added here: its insert and update keep
 * no `post_content` and run no filter, and its posts are objects, which the checks read and write
 * as arrays. This suite's own WP_Post answers both ways, and its own wp_insert_post() and
 * wp_update_post() keep the content and run `wp_insert_post_data` before they write, as core runs
 * it on every save; the stub's post meta is where a gate would land. A settings save is modeled
 * the way update_option() makes one: the settings written first, then follow_switch() called, as
 * the option's update action calls it, so live() reads the new switch inside it.
 *
 * Run from the plugin root:  php bin/test-sponsors-page.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['opts']      = array();
$GLOBALS['autoload']  = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['settings']  = array();
$GLOBALS['can']       = false;
$GLOBALS['is_admin']  = false;
$GLOBALS['queried']   = 0;
$GLOBALS['headers']   = array();
$GLOBALS['is_404']    = false;
$GLOBALS['hooks']     = array();
$GLOBALS['filters']   = array();
$GLOBALS['apply_url'] = 'https://site.example/sponsor-application/';

/** Only for its type: is_wp_error() asks for it, and nothing in this run is meant to fail. */
class WP_Error {
	public function __construct( $code = '', $message = '' ) {
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * A post the class and the stub read and write as an object, and the checks as an array.
 *
 * `#[\ReturnTypeWillChange]` on offsetGet() alone: its interface return type is `mixed`, which
 * PHP 7.4 cannot declare, and the attribute is a comment there.
 */
class WP_Post implements ArrayAccess {
	public $ID           = 0;
	public $post_type    = 'post';
	public $post_status  = 'draft';
	public $post_title   = '';
	public $post_name    = '';
	public $post_author  = 0;
	public $post_content = '';
	public $post_parent  = 0;

	public function offsetExists( $key ): bool {
		return property_exists( $this, $key );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) {
		return $this->$key;
	}

	public function offsetSet( $key, $value ): void {
		$this->$key = $value;
	}

	public function offsetUnset( $key ): void {
		$this->$key = null;
	}
}

/**
 * Every hook added, in the order added, as `array( $hook, $callback, $priority, $args )`: the
 * Hooks section reads them back. Nothing in this run fires an action.
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = array( $hook, $callback, $priority, $args );
}

/**
 * Recorded with the actions, and kept to be applied: one entry per callback and priority, as core
 * keeps them, so a second init() adds nothing.
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = array( $hook, $callback, $priority, $args );

	$GLOBALS['filters'][ $hook ][ $priority ][ implode( '::', (array) $callback ) ] = array( $callback, $args );
}

/** Each filter in priority order, handed as many arguments as it asked for, as core hands them. */
function apply_filters( $hook, $value, ...$more ) {
	$by_priority = isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ] : array();

	ksort( $by_priority );

	foreach ( $by_priority as $filters ) {
		foreach ( $filters as $filter ) {
			$value = call_user_func_array( $filter[0], array_slice( array_merge( array( $value ), $more ), 0, (int) $filter[1] ) );
		}
	}

	return $value;
}

/**
 * One save, as core's wp_insert_post() makes it: the fields gathered into `$data`, run through
 * `wp_insert_post_data` with the fields the save was given, then written.
 *
 * @param WP_Post $post    The post written to.
 * @param array   $postarr The fields the save was given, the post's ID among them on an update.
 */
function wpcpm_test_save( WP_Post $post, array $postarr ) {
	$data = array();

	foreach ( array( 'post_type', 'post_status', 'post_title', 'post_name', 'post_content', 'post_parent' ) as $field ) {
		$data[ $field ] = array_key_exists( $field, $postarr ) ? $postarr[ $field ] : $post->$field;
	}

	$data = apply_filters( 'wp_insert_post_data', $data, $postarr, $postarr, isset( $postarr['ID'] ) );

	foreach ( $data as $field => $value ) {
		$post->$field = $value;
	}
}

/** The stub's insert, the same IDs from 100, with the content kept and the filter run. */
function wp_insert_post( $postarr, $wp_error = false ) {
	$GLOBALS['posts_next_id'] = isset( $GLOBALS['posts_next_id'] ) ? $GLOBALS['posts_next_id'] : 100;

	$post     = new WP_Post();
	$post->ID = $GLOBALS['posts_next_id']++;

	wpcpm_test_save( $post, $postarr );

	$GLOBALS['posts'][ $post->ID ] = $post;

	return $post->ID;
}

/**
 * Core's update: the post's own fields merged under the ones given, as wp_update_post() merges
 * them before it hands the save to wp_insert_post(), so the filter reads the whole post.
 */
function wp_update_post( $postarr, $wp_error = false ) {
	$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post' ) : 0;
	}

	wpcpm_test_save( $GLOBALS['posts'][ $id ], array_merge( get_object_vars( $GLOBALS['posts'][ $id ] ), $postarr ) );

	return $id;
}

/**
 * Core's lookup: a string type searches that type and attachments, and answers a post of the type
 * when one matches, else a file at the slug that hangs from no post; an array of types searches
 * those alone. Whatever the status, as core's query reads none.
 */
function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
	$types = is_array( $post_type ) ? $post_type : array( $post_type, 'attachment' );
	$found = null;

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $slug !== $post->post_name || ! in_array( $post->post_type, $types, true ) || 0 !== (int) $post->post_parent ) {
			continue;
		}

		$found = $post;

		if ( $post->post_type === $post_type ) {
			break;
		}
	}

	return $found;
}

/**
 * A post's status as core reports it: false for a post that is not there, and `publish` for a file
 * that inherits its status and hangs from no post, which core assumes is published. Each read is
 * counted, for the check that a request the guard lets through reads none.
 */
function get_post_status( $id ) {
	$GLOBALS['status_reads'] = ( isset( $GLOBALS['status_reads'] ) ? (int) $GLOBALS['status_reads'] : 0 ) + 1;

	if ( ! isset( $GLOBALS['posts'][ (int) $id ] ) ) {
		return false;
	}

	$post = $GLOBALS['posts'][ (int) $id ];

	if ( 'attachment' === $post->post_type && 'inherit' === $post->post_status && 0 === (int) $post->post_parent ) {
		return 'publish';
	}

	return $post->post_status;
}

function get_permalink( $id ) {
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? 'https://site.example/' . $GLOBALS['posts'][ (int) $id ]->post_name . '/' : false;
}

/** Whether this request is for the page: the queried page is whichever ID a check sets. */
function is_page( $id ) {
	return $GLOBALS['queried'] === (int) $id;
}

function status_header( $code ) {
	$GLOBALS['headers'][] = 'status:' . $code;
}

function nocache_headers() {
	$GLOBALS['headers'][] = 'nocache';
}

/** The main query, as far as guard() reaches it: the 404 it sets, and whether it is an embed. */
class WPCPM_Test_Query {
	public $is_embed = false;

	public function set_404() {
		$GLOBALS['is_404'] = true;
	}
}

$GLOBALS['wp_query'] = new WPCPM_Test_Query();

/** Answers $GLOBALS['can'] for the one capability the class reads, false for any other. */
function current_user_can( $cap ) {
	return WPCPM_Roles::CAP_MANAGE === $cap ? (bool) $GLOBALS['can'] : false;
}

function is_admin() {
	return (bool) $GLOBALS['is_admin'];
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

/** Keeps the autoload flag beside the value, for the check that the page's option is autoloaded. */
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ]     = $value;
	$GLOBALS['autoload'][ $key ] = $autoload;

	return true;
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function esc_url( $url ) {
	return (string) $url;
}

function home_url( $path = '' ) {
	return 'https://site.example' . $path;
}

/** The settings store, read through the one key the tiers class reads for the switch. */
class WPCPM_Settings {
	const OPT_NAME = 'wpcpm_settings';

	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** Only the one constant the class reads. */
class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

/** The application page's address: '' while it is not published, as the real class answers. */
class WPCPM_Sponsor_Application {
	public static function page_url() {
		return $GLOBALS['apply_url'];
	}
}

require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-page.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * The guard, run on a fresh request for the page's embed view: whether it set a 404, the headers
 * it sent and whether the request is still an embed.
 *
 * @return array
 */
function guarded() {
	$GLOBALS['is_404']             = false;
	$GLOBALS['headers']            = array();
	$GLOBALS['wp_query']->is_embed = true;

	WPCPM_Sponsors_Page::guard();

	return array( $GLOBALS['is_404'], $GLOBALS['headers'], $GLOBALS['wp_query']->is_embed );
}

$P = 'WPCPM_Sponsors_Page';

// Started the way the Sponsors module starts it, so the filter that keeps the page a draft runs
// on every save below; the Hooks section reads the registrations back.
$P::init();

// A file uploaded as "partners" and never attached: asked for one type, core's lookup answers it
// when no page matches, and core counts it as published. It sits at the slug for the whole run.
$file = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'partners', 'post_name' => 'partners' ) );
// The page this plugin made at the old slug before 1.123.18, on a copy of the site that has one:
// not the Partners page, and never adopted as it.
$old_slug = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Sponsors', 'post_name' => 'sponsors' ) );

echo "=== Creation ===\n";
$GLOBALS['is_admin'] = false;
$GLOBALS['can']      = true;
$P::maybe_ensure();
ck( 'maybe_ensure() does nothing on a front-end request, a program manager\'s included', $P::page_id(), 0 );
// is_admin() is true for admin-post.php and admin-ajax.php too, where the public application form
// and every signed-in sponsor, student and mentor form post: the page is made on a program
// manager's admin request alone, or whoever posted a form next would make it, as its author.
$GLOBALS['is_admin'] = true;
$GLOBALS['can']      = false;
$P::maybe_ensure();
ck( 'nor on an admin request by someone who is not a program manager, a form posted to admin-post.php among them', array( $P::page_id(), count( array_filter( $GLOBALS['posts'], function ( $p ) { return 'page' === $p['post_type']; } ) ) ), array( 0, 1 ) );
$GLOBALS['can'] = true;
$P::maybe_ensure();
$id = $P::page_id();
ck( 'and creates the Partners page on a program manager\'s admin request, as a draft, at its slug, autoloaded', array( $id > 0, $GLOBALS['posts'][ $id ]['post_status'], $GLOBALS['posts'][ $id ]['post_name'], $GLOBALS['posts'][ $id ]['post_title'], $GLOBALS['autoload']['wpcpm_sponsors_page_id'], $P::SLUG, $P::title() ), array( true, 'draft', 'partners', 'Partners', true, 'partners', 'Partners' ) );
ck( 'a file at the slug that hangs from no post is not the page: ensure_page() makes one beside it', array( $id !== $file, $GLOBALS['posts'][ $id ]['post_type'] ), array( true, 'page' ) );
ck( 'nor is a page at the old slug, sponsors, which is left as it was', array( $id !== $old_slug, $GLOBALS['posts'][ $old_slug ]['post_status'], $GLOBALS['posts'][ $old_slug ]['post_content'] ), array( true, 'draft', '' ) );
unset( $GLOBALS['posts'][ $old_slug ] );
$P::maybe_ensure();
ck( 'a second run creates nothing', count( array_filter( $GLOBALS['posts'], function ( $p ) { return 'page' === $p['post_type']; } ) ), 1 );
ck( 'no page meta gates it: the page is public once published', isset( $GLOBALS['pmeta'][ $id ] ) ? $GLOBALS['pmeta'][ $id ] : array(), array() );
$content = $GLOBALS['posts'][ $id ]['post_content'];
// The theme prints the partner page's text around the page's own content (its page-partners.html
// template), so the content is the heading, one sentence and the full list, and nothing else.
// The page's title is its h1 and the list holds an h3 for its upcoming events: the h2 opens the
// page, so those sit under a heading of the level above theirs.
ck( 'the content is the "Our partners" heading, one sentence that the partners are listed in alphabetical order, and the full block, wide', $content,
	'<!-- wp:heading --><h2 class="wp-block-heading">Our partners</h2><!-- /wp:heading -->' . "\n\n"
	. '<!-- wp:paragraph --><p>Our partners are listed in alphabetical order.</p><!-- /wp:paragraph -->' . "\n\n"
	. '<!-- wp:wpcpm/sponsors {"variant":"full","align":"wide"} /-->' . "\n"
);
ck( 'no tier, amount, benefit or button of the partner page is the content\'s to write', array(
	preg_match( '/Friend|Supporter|Backer|Champion|\$|a year|Five for the Future|wp:buttons|wp:list|sponsor-application/', $content ),
	preg_match( '/\x{2013}|\x{2014}/u', $content ),
), array( 0, 0 ) );
// The content reads nothing of the application page: the theme's template links the form.
$GLOBALS['apply_url'] = 'https://site.example/apply-now/';
$followed             = $P::content();
$GLOBALS['apply_url'] = '';
$fallback             = $P::content();
$GLOBALS['apply_url'] = 'https://site.example/sponsor-application/';
ck( 'the content is the same whatever the application page\'s address', array( $followed === $content, $fallback === $content ), array( true, true ) );

echo "=== Adoption ===\n";
// A site restored with the page but not the option adopts it.
$GLOBALS['opts'] = array();
ck( 'the option gone: ensure_page() adopts the page at the slug instead of a second one', array( $P::ensure_page(), (int) $GLOBALS['opts']['wpcpm_sponsors_page_id'] ), array( $id, $id ) );
$GLOBALS['posts'][ $id ]['post_status'] = 'trash';
ck( 'a trashed page counts as none', $P::page_id(), 0 );
$new = $P::ensure_page();
ck( 'and ensure_page() makes a fresh draft beside it', array( $new !== $id, $GLOBALS['posts'][ $new ]['post_status'] ), array( true, 'draft' ) );
unset( $GLOBALS['posts'][ $id ] );
$id = $new;

echo "=== The switch ===\n";
// Each save of the settings below is written first and followed second, the order update_option()
// keeps: the option's update action, which follow_switch() is, fires after the value is stored.
ck( 'page_url() is empty while the page is a draft', $P::page_url(), '' );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$P::follow_switch( array( 'sponsor_tiers_live' => false ), array( 'sponsor_tiers_live' => true ) );
ck( 'the switch turned on publishes the page, at the Partners page\'s address', array( $GLOBALS['posts'][ $id ]['post_status'], $P::page_url() ), array( 'publish', 'https://site.example/partners/' ) );
$P::follow_switch( array( 'sponsor_tiers_live' => true ), array( 'sponsor_tiers_live' => true ) );
ck( 'a save that leaves the switch on changes nothing', $GLOBALS['posts'][ $id ]['post_status'], 'publish' );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => false );
$P::follow_switch( array( 'sponsor_tiers_live' => true ), array( 'sponsor_tiers_live' => false ) );
ck( 'the switch turned off returns it to draft', array( $GLOBALS['posts'][ $id ]['post_status'], $P::page_url() ), array( 'draft', '' ) );
$GLOBALS['opts'] = array();
unset( $GLOBALS['posts'][ $id ] );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$P::follow_switch( array(), array( 'sponsor_tiers_live' => true ) );
$id = $P::page_id();
ck( 'the switch turned on with no page, and no old value at all, creates and publishes one', array( $id > 0, $GLOBALS['posts'][ $id ]['post_status'] ), array( true, 'publish' ) );
// A page a manager scheduled, made private or left pending while the switch was on: turning the
// switch off takes back the schedule, a publish waiting to happen, and leaves the other two.
$after_off = array();
foreach ( array( 'future', 'private', 'pending' ) as $status ) {
	$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
	wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
	$GLOBALS['settings'] = array( 'sponsor_tiers_live' => false );
	$P::follow_switch( array( 'sponsor_tiers_live' => true ), array( 'sponsor_tiers_live' => false ) );
	$after_off[ $status ] = $GLOBALS['posts'][ $id ]['post_status'];
}
ck( 'the switch turned off returns a scheduled page to draft, and leaves a private or pending one as a manager set it', $after_off, array( 'future' => 'draft', 'private' => 'private', 'pending' => 'pending' ) );

echo "=== No save publishes it while the switch is off ===\n";
$landed = array();
foreach ( array( 'publish', 'future', 'private', 'pending' ) as $status ) {
	wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
	$landed[ $status ] = $GLOBALS['posts'][ $id ]['post_status'];
}
ck( 'the switch off: a save of the page to publish, to future or to any status but the trash lands as a draft', $landed, array( 'publish' => 'draft', 'future' => 'draft', 'private' => 'draft', 'pending' => 'draft' ) );
wp_update_post( array( 'ID' => $id, 'post_status' => 'trash' ) );
ck( 'a trash lands in the trash', $GLOBALS['posts'][ $id ]['post_status'], 'trash' );
// A site that restores a post's old status restores the page straight to publish; the filter knows
// the page by the recorded ID, which the trash leaves in place, so it comes back a draft.
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
ck( 'and restored from the trash straight to publish, it comes back a draft', $GLOBALS['posts'][ $id ]['post_status'], 'draft' );
$other = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about' ) );
wp_update_post( array( 'ID' => $other, 'post_status' => 'publish' ) );
ck( 'while another page publishes as ever', $GLOBALS['posts'][ $other ]['post_status'], 'publish' );
unset( $GLOBALS['posts'][ $other ] );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
ck( 'the switch on: a publish lands', $GLOBALS['posts'][ $id ]['post_status'], 'publish' );
// Trashed after the launch, the page comes back the way the switch says.
wp_update_post( array( 'ID' => $id, 'post_status' => 'trash' ) );
// Unless the switch is turned off first: with the page in the trash there is nothing to take back,
// so turning the switch off makes no page, not even a draft.
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => false );
$P::follow_switch( array( 'sponsor_tiers_live' => true ), array( 'sponsor_tiers_live' => false ) );
ck( 'the switch turned off with the page in the trash makes no page', array( $P::page_id(), count( array_filter( $GLOBALS['posts'], function ( $p ) { return 'page' === $p['post_type']; } ) ) ), array( 0, 1 ) );
// Turned back on beneath the class, as a restored database would: the admin request below is what
// makes the page.
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$GLOBALS['is_admin'] = true;
$P::maybe_ensure();
$back = $P::page_id();
ck( 'the switch on and no page: the next admin request makes one, published', array( $back > 0, $back !== $id, $back > 0 ? $GLOBALS['posts'][ $back ]['post_status'] : '' ), array( true, true, 'publish' ) );
unset( $GLOBALS['posts'][ $id ] );
$id = $back;

echo "=== The content is the manager's ===\n";
// Written once, at creation: an edit survives the class adopting the page and both flips.
$edit = "<!-- wp:paragraph --><p>A manager's edit.</p><!-- /wp:paragraph -->";
wp_update_post( array( 'ID' => $id, 'post_content' => $edit ) );
$GLOBALS['opts'] = array();
$adopted         = $P::ensure_page();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => false );
$P::follow_switch( array( 'sponsor_tiers_live' => true ), array( 'sponsor_tiers_live' => false ) );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$P::follow_switch( array( 'sponsor_tiers_live' => false ), array( 'sponsor_tiers_live' => true ) );
ck( 'a manager\'s edit survives an adoption and both flips of the switch', array( $adopted, $GLOBALS['posts'][ $id ]['post_status'], $GLOBALS['posts'][ $id ]['post_content'] ), array( $id, 'publish', $edit ) );

echo "=== The guard ===\n";
// The page is published and the switch reads off, the settings changed without the option's
// update action, the way a database restored from a backup leaves them: the case the guard is
// the belt for.
$GLOBALS['settings'] = array();
$GLOBALS['can']      = false;
$GLOBALS['queried']  = $id;
ck( 'the switch off: a visitor gets a 404, not cached, and no embed view', guarded(), array( true, array( 'status:404', 'nocache' ), false ) );
$GLOBALS['can'] = true;
ck( 'a program manager reads the page, and nothing is sent', guarded(), array( false, array(), true ) );
$GLOBALS['can'] = false; $GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
ck( 'the switch on: everyone reads it, and nothing is sent', guarded(), array( false, array(), true ) );
$GLOBALS['settings'] = array(); $GLOBALS['queried'] = 999;
ck( 'another page is never touched', guarded(), array( false, array(), true ) );
// Every front-end request passes through the guard, so one that is not the page costs it nothing
// beyond the autoloaded option: whether this is the page is asked before any post is read.
$GLOBALS['status_reads'] = 0;
guarded();
$GLOBALS['queried'] = 0;
guarded();
ck( 'a request that is not the page, another page or none, reads no post status', $GLOBALS['status_reads'], 0 );

echo "=== Hooks ===\n";
$GLOBALS['hooks'] = array();
$P::init();
ck( 'init() hooks the ensure on init at 6, the switch on the settings option\'s update with both values, the draft keeper on every save with both arrays, the guard on template_redirect', array_map( function ( $h ) use ( $P ) { return array( $h[0], is_array( $h[1] ) && $P === $h[1][0] ? $h[1][1] : $h[1], $h[2], $h[3] ); }, $GLOBALS['hooks'] ), array(
	array( 'init', 'maybe_ensure', 6, 1 ),
	array( 'update_option_wpcpm_settings', 'follow_switch', 10, 2 ),
	array( 'wp_insert_post_data', 'keep_draft', 10, 2 ),
	array( 'template_redirect', 'guard', 10, 1 ),
) );

echo "=== Wiring ===\n";
// Read from the source, the way the store's and the booking field's suites read theirs: boot() and
// activate() start the whole Sponsors module, which is more than this suite stands in for.
$module_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$boot       = substr( $module_src, (int) strpos( $module_src, 'public function boot()' ) );
$boot       = substr( $boot, 0, (int) strpos( $boot, "\n\t}\n" ) );
$activate   = substr( $module_src, (int) strpos( $module_src, 'public function activate()' ) );
$activate   = substr( $activate, 0, (int) strpos( $activate, "\n\t}\n" ) );
$loader     = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$made_apply = strpos( $activate, 'WPCPM_Sponsor_Application::ensure_page();' );
$made_page  = strpos( $activate, 'WPCPM_Sponsors_Page::ensure_page();' );
$followed   = strpos( $activate, 'WPCPM_Sponsors_Page::set_published( WPCPM_Sponsor_Tiers::live() );' );
ck( 'the plugin loads the class, the module starts it with its front-end classes, and activation makes the page after the application page, then puts it where the switch says', array(
	false !== strpos( $loader, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-page.php';" ),
	1 === preg_match( "/foreach \( array\([^)]*'WPCPM_Sponsors_Page'[^)]*\) as \\\$front \)/", $boot ),
	is_int( $made_apply ) && is_int( $made_page ) && $made_apply < $made_page,
	is_int( $followed ) && $made_page < $followed,
), array( true, true, true, true ) );

echo "=== House rules ===\n";
// The public page is the Partners page since 1.123.18: nothing the plugin ships, no guide and no
// part of the readme above its changelog calls it the sponsors page or the sponsor listing, or
// links /sponsors/. The program's own words stay: the Sponsor Dashboard, the Sponsors screen and
// the Sponsors block.
$old_page_words = array();
$plugin_root    = dirname( __DIR__ );
foreach ( array( 'includes', 'blocks', 'assets/js', 'docs/sections' ) as $shipped ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_root . '/' . $shipped, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( preg_match( '/\.(php|js|json|md)$/', $file->getFilename() ) && preg_match_all( '#sponsors\'? page|sponsor listing|/sponsors/#i', (string) file_get_contents( $file->getPathname() ), $found ) ) {
			$old_page_words[ substr( $file->getPathname(), strlen( $plugin_root ) + 1 ) ] = count( $found[0] );
		}
	}
}
$readme_body = strstr( (string) file_get_contents( $plugin_root . '/readme.txt' ), '== Changelog ==', true );
if ( preg_match_all( '#sponsors\'? page|sponsor listing|/sponsors/#i', (string) $readme_body, $found ) ) {
	$old_page_words['readme.txt'] = count( $found[0] );
}
ck( 'nothing shipped, no guide and no part of the readme above its changelog calls the public page the sponsors page or the sponsor listing, or links /sponsors/', $old_page_words, array() );
$src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-page.php' );
ck( 'the insert is the one array in the class that names the content', substr_count( $src, "'post_content' =>" ), 1 );
ck( 'and names no base', array( strpos( $src, 'WPCPM_Airtable' ), preg_match( '/\x{2013}|\x{2014}/u', $src ) ), array( false, 0 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
