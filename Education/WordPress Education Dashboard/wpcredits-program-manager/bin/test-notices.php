<?php
/**
 * Who sees which header notice.
 *
 * The whole point of the feature is that a notice reaches one audience and nobody else,
 * and that somebody in two audiences gets both. Those are the assertions worth having:
 * a mistake here shows the wrong people a notice, or withholds one from the people it
 * was written for, and neither is visible from looking at the settings screen.
 *
 * Run from the plugin root:  php bin/test-notices.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts']  = array();
$GLOBALS['umeta'] = array();
$GLOBALS['users'] = array();
$GLOBALS['uid']   = 0;
$GLOBALS['manage'] = array();

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

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
/**
 * Stands in for `wp_kses_post()`, with the tag list WordPress allows in the post context.
 *
 * The list matters: a narrower stub would have this suite asserting *its own* behaviour
 * rather than WordPress's — the first version kept only `a`, `strong`, `em` and reported
 * that images and lists were being stripped when they are not. What this plugin actually
 * controls is *which* filter it calls, and that is pinned by a static assertion further
 * down rather than by this stub.
 */
function wp_kses_post( $s ) {
	return strip_tags(
		(string) $s,
		'<a><strong><b><em><i><br><p><ul><ol><li><h1><h2><h3><h4><h5><h6>'
		. '<blockquote><code><pre><img><figure><figcaption><table><thead><tbody><tr><th><td>'
	);
}
function wpautop( $s ) { return '<p>' . $s . '</p>'; }
// Block markup only ever reaches this on the way *into* the option, during recovery.
function do_blocks( $s ) { return (string) $s; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;
	return in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style() {} function wp_enqueue_style() {}
function wp_timezone_string() { return 'UTC'; }
function get_role( $r ) { return null; }
function wp_roles() { return null; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_trim_words( $t, $n = 55, $more = null ) { return $t; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function wp_editor() {}
function submit_button() {}
function wp_nonce_field() {}

/**
 * Just enough `$wpdb` for the recovery's one query.
 *
 * `$GLOBALS['notice_posts']` is the audience => post content the block-editor version left
 * behind; an audience missing from it has no post at all, which is the case that mattered.
 */
class WPCPM_Fake_WPDB {
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public function prepare( $sql, ...$args ) { return array( 'sql' => $sql, 'args' => $args ); }
	public function get_var( $query ) {
		$slug = $query['args'][2] ?? '';

		return $GLOBALS['notice_posts'][ $slug ] ?? null;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Fake_WPDB();
$GLOBALS['notice_posts'] = array();

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';
if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Stands in for the members module: a notice for institutions reaches members, not the role. */
	class WPCPM_Institution_Members {
		public static function is_member( $user = null ) {
			$id = $user instanceof WP_User ? $user->ID : (int) $user;
			return in_array( $id, isset( $GLOBALS['institution_members'] ) ? $GLOBALS['institution_members'] : array(), true );
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';

/* ---- fixtures ----------------------------------------------------------- */
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = WPCPM_Settings::defaults();

/**
 * Write the four notices the way the plugin stores them: one option, keyed by audience.
 *
 * Everything below reads through `WPCPM_Notices::bodies()`, so the tool's view of a notice
 * and the audience check's view cannot drift apart in this harness.
 *
 * @param array $bodies Audience slug => markup.
 */
function set_notices( array $bodies ) {
	$GLOBALS['opts'][ WPCPM_Notices::OPTION ] = $bodies;
}

set_notices(
	array(
		'student'     => 'Reports are due Friday.',
		'mentor'      => 'Please confirm your <a href="/hours/">hours</a>.',
		'institution' => 'Agreements renew in March.',
		'admin'       => 'Sync ran overnight.',
	)
);

// 10 plain student. 20 plain mentor. 30 institution. 40 administrator.
$GLOBALS['institution_members'] = array( 30 );
// 50 administrator who also mentors — recognised by an Airtable record, never by role.
// 60 student who also mentors. 70 subscriber in no audience.
$GLOBALS['users'][10] = new WP_User( 10, 'Student', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][20] = new WP_User( 20, 'Mentor', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Institution', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][40] = new WP_User( 40, 'Admin', array( 'administrator' ) );
$GLOBALS['users'][50] = new WP_User( 50, 'Admin who mentors', array( 'administrator' ) );
$GLOBALS['users'][60] = new WP_User( 60, 'Student who mentors', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][70] = new WP_User( 70, 'Subscriber', array( 'subscriber' ) );
$GLOBALS['manage'] = array( 40, 50 );
$GLOBALS['umeta'][50][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMENTOR12345678';

/* ---- runner ------------------------------------------------------------- */
$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . implode( ', ', $expected ) . ( $expected ? '' : '(none)' ) . "\n";
		echo "       actual:   " . implode( ', ', $actual ) . ( $actual ? '' : '(none)' ) . "\n";
	}
}

/**
 * Which notices a user sees.
 *
 * `current()` memoizes per request, which a single-process harness cannot reset — so
 * audience membership is exercised through `applies_to()` directly, against the same stored
 * bodies. That is the part this plugin decides; `current()` only joins it to the option.
 */
function seen_by( $id ) {
	$GLOBALS['uid'] = $id;
	$out = array();

	if ( 0 === $id ) {
		return $out;
	}

	foreach ( WPCPM_Notices::bodies() as $slug => $body ) {
		if ( '' !== $body && WPCPM_Notices::applies_to( $slug ) ) {
			$out[] = $slug;
		}
	}

	return $out;
}

ck( 'a student sees only the student notice',      seen_by( 10 ), array( 'student' ) );
ck( 'a mentor sees only the mentor notice',        seen_by( 20 ), array( 'mentor' ) );
ck( 'an institution sees only its own',            seen_by( 30 ), array( 'institution' ) );
ck( 'an administrator sees only the admin notice', seen_by( 40 ), array( 'admin' ) );
ck( 'an administrator who mentors sees both, mentor first',
    seen_by( 50 ), array( 'mentor', 'admin' ) );
ck( 'a student who mentors sees both, student first',
    seen_by( 60 ), array( 'student', 'mentor' ) );
ck( 'somebody in no audience sees nothing',        seen_by( 70 ), array() );
ck( 'a logged-out visitor sees nothing',           seen_by( 0 ), array() );

// An empty notice is off.
$saved = WPCPM_Notices::bodies();
set_notices( array_merge( $saved, array( 'mentor' => '   ' ) ) );
ck( 'an empty notice is simply off',               seen_by( 20 ), array() );
set_notices( $saved );

// The tool that owns the editing screen.
$tool = new WPCPM_Header_Notices();
ck( 'the tool needs no Airtable connection', array( $tool->is_ready() ), array( true ) );
ck( 'its page slug is stable',               array( $tool->page_slug() ), array( 'wpcpm-tool-header-notices' ) );

set_notices( array( 'student' => 'One', 'mentor' => 'Two', 'institution' => '', 'admin' => '' ) );
ck( 'it counts the live notices', array( $tool->status_line() ), array( '2 notices are showing.' ) );

set_notices( array( 'student' => '', 'mentor' => '', 'institution' => '', 'admin' => '' ) );
ck( 'and says so when there are none', array( $tool->status_line() ), array( 'No notices are showing.' ) );

// A round trip through the real save path. What goes in is what an editor posts; what comes
// back has to be filtered, trimmed, and keyed only by the audiences that exist.
WPCPM_Notices::save(
	array(
		'student' => "  <p>Hello <a href=\"/x/\">there</a></p><script>alert(1)</script>  ",
		'mentor'  => '',
		'ghost'   => 'Not an audience.',
	)
);
$round = WPCPM_Notices::bodies();
ck( 'a saved notice keeps its markup and loses its scripts',
    array( $round['student'] ), array( '<p>Hello <a href="/x/">there</a></p>alert(1)' ) );
ck( 'an audience left blank is stored blank, and an unknown one is dropped',
    array( $round['mentor'], $round['institution'], isset( $round['ghost'] ) ),
    array( '', '', false ) );

set_notices( $saved );

// Storage and the render pipeline. A notice is markup in an option, edited in the classic
// editor — so the assertions that used to pin the post type now pin its absence, because
// leaving `register_post_type()` behind would re-register a type nothing reads.
$pipeline = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-notices.php' );
$tool_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php' );

ck( 'bare lines become paragraphs, then everything is filtered',
    array( (bool) strpos( $pipeline, 'wp_kses_post( wpautop( $body ) )' ) ), array( true ) );
ck( 'no post type is registered any more',
    array( (bool) strpos( $pipeline, 'register_post_type(' ) ), array( false ) );
// `do_blocks()` survives in exactly one place: the one-time move of content off the old
// posts, where block markup is converted on the way into the option. Counted as the call
// with its argument, not as the bare name — the docblock above it says `do_blocks()` too,
// and matching that would make this assertion pass on the prose alone.
ck( 'blocks are converted once, on the way in, not on every render',
    array( substr_count( $pipeline, 'do_blocks( $body )' ), (bool) strpos( $pipeline, 'do_blocks' ) ),
    array( 1, true ) );
/* ---- recovering notices written on older versions ----------------------- */

echo "\n=== Recovering older notices ===\n";

/**
 * Run the recovery against a clean slate.
 *
 * @param array $posts    Audience => content of the block-editor post, if any.
 * @param array $legacy   Audience => content of the pre-post settings key, if any.
 * @param array $current  Audience => content already in the new option.
 * @param int   $revision Recovery revision this site has already had.
 * @return array
 */
function recover( array $posts, array $legacy, array $current = array(), $revision = 0 ) {
	$GLOBALS['notice_posts'] = $posts;

	$settings = WPCPM_Settings::defaults();

	foreach ( $legacy as $slug => $body ) {
		$settings[ 'notice_' . $slug ] = $body;
	}

	$GLOBALS['opts'][ WPCPM_Settings::OPTION ]     = $settings;
	$GLOBALS['opts'][ WPCPM_Notices::OPT_MIGRATED ] = 1;
	$GLOBALS['opts'][ WPCPM_Notices::OPT_PLAIN ]    = $revision;
	$GLOBALS['opts'][ WPCPM_Notices::OPTION ]       = $current;

	WPCPM_Notices::maybe_migrate();

	return WPCPM_Notices::bodies();
}

// The shape the staging site was actually in: two posts written, two left empty, and all
// four still present in the settings option from before any of this existed. Revision 1
// read only the posts, so the two whose posts were empty vanished from the front end.
$out = recover(
	array( 'institution' => 'Institution notice', 'admin' => 'Admin notice' ),
	array(
		'student'     => 'Student notice',
		'mentor'      => 'Mentor notice',
		'institution' => 'Institution notice',
		'admin'       => 'Admin notice',
	)
);
ck( 'a notice surviving only in the old settings key is recovered',
    array( $out['student'], $out['mentor'] ), array( 'Student notice', 'Mentor notice' ) );
ck( 'and one surviving as a post still is',
    array( $out['institution'], $out['admin'] ), array( 'Institution notice', 'Admin notice' ) );

// The post is the newer copy, so it wins where both exist.
$out = recover( array( 'admin' => 'From the post' ), array( 'admin' => 'From the settings' ) );
ck( 'the post wins over the settings key', array( $out['admin'] ), array( 'From the post' ) );

// Anything written in the new editor outranks every older copy, which is what makes the
// recovery safe to run again.
$out = recover(
	array( 'mentor' => 'Old post text' ),
	array( 'mentor' => 'Older settings text' ),
	array( 'mentor' => 'What the manager typed last week' )
);
ck( 'a rewritten notice is never reverted',
    array( $out['mentor'] ), array( 'What the manager typed last week' ) );

// A site already at the current revision is left alone entirely.
$out = recover(
	array( 'admin' => 'Should not appear' ),
	array( 'admin' => 'Nor this' ),
	array(),
	WPCPM_Notices::MIGRATION_VERSION
);
ck( 'a site already recovered is not touched again', array( $out['admin'] ), array( '' ) );

// A revision counter, not a flag: revision 1 ran and missed things, and the whole point is
// that such a site can be revisited.
$out = recover( array(), array( 'student' => 'Rescued on the second pass' ), array(), 1 );
ck( 'a site stuck on the first revision is revisited',
    array( $out['student'] ), array( 'Rescued on the second pass' ) );
ck( 'and the revision is recorded',
    array( (int) get_option( WPCPM_Notices::OPT_PLAIN ) ), array( WPCPM_Notices::MIGRATION_VERSION ) );

// Restore the fixture the assertions below expect.
$GLOBALS['notice_posts'] = array();
set_notices( $saved );

// The editor the tool mounts. `teeny` drops the media button, and `teeny` together with a
// custom toolbar cancels both — which would leave a notice unable to hold an image.
ck( 'the tool mounts the full classic editor with its media button',
    array(
        (bool) strpos( $tool_src, 'wp_editor(' ),
        (bool) strpos( $tool_src, "'media_buttons' => true" ),
        (bool) strpos( $tool_src, "'teeny'         => false" ),
    ),
    array( true, true, true ) );
ck( 'the save checks a nonce and the manage capability',
    array(
        (bool) strpos( $tool_src, 'check_admin_referer( self::NONCE, self::NONCE )' ),
        (bool) strpos( $tool_src, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ),
    ),
    array( true, true ) );
// The outcome travels as a flash, not as `?saved=1`: a query argument stays in the address
// bar, so reloading the screen reports a save that did not happen.
ck( 'the outcome is a flash, not a query argument',
    array(
        (bool) strpos( $tool_src, 'WPCPM_Flash::set( self::FLASH' ),
        (bool) strpos( $tool_src, "add_query_arg( 'saved'" ),
    ),
    array( true, false ) );

// Checked as a hook registration, not as a word: the class still explains in a comment why
// it stopped using `wp_body_open`, and an assertion on the bare string would fail on the
// explanation.
ck( 'notices are placed in the content, not above the header',
    array(
        (bool) strpos( $pipeline, "add_filter( 'the_content'" ),
        (bool) strpos( $pipeline, "add_action( 'wp_body_open'" ),
    ),
    array( true, false ) );
ck( 'the content filter runs once, on the main singular query',
    array( (bool) preg_match( '/\$done \|\| ! is_singular\(\) \|\| ! is_main_query\(\) \|\| ! in_the_loop\(\)/', $pipeline ) ),
    array( true ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
