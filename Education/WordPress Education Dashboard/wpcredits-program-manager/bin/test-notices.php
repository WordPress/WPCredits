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
 * The list matters: a narrower stub would have this suite asserting *its own* behavior
 * rather than WordPress's - the first version kept only `a`, `strong`, `em` and reported
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
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['option_writes'][] = $k; return true; }
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style() {} function wp_enqueue_style() {}
function wp_timezone_string() { return 'UTC'; }
function get_role( $r ) { return null; }
function wp_roles() { return null; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_trim_words( $t, $n = 55, $more = null ) { return $t; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function wp_editor( $content, $id, $settings = array() ) { echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $settings['textarea_name'] ) . '">' . esc_textarea( $content ) . '</textarea>'; }
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = null ) { echo '<button type="submit" id="' . esc_attr( is_array( $other ) && isset( $other['id'] ) ? $other['id'] : $name ) . '" class="button button-primary">' . esc_html( $text ) . '</button>'; }
function wp_nonce_field( $a = -1, $n = '_wpnonce' ) { echo '<input type="hidden" name="' . esc_attr( $n ) . '" value="nonce-' . esc_attr( $a ) . '" />'; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['nonce_checked'][] = $a; return true; }
class WPCPM_Test_Stop extends Exception {}
function wp_die( $m = '', $c = 0 ) { throw new WPCPM_Test_Stop( 'die ' . $c ); }
function wp_safe_redirect( $u ) { throw new WPCPM_Test_Stop( 'redirect' ); }
function wp_slash( $v ) { return $v; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

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

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';

/* ---- fixtures ----------------------------------------------------------- */
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();

/**
 * Write the four notices the way the plugin stores them: one option, keyed by audience.
 *
 * Everything below reads through `WPCPM_Notices::bodies()`, so the tool's view of a notice
 * and the audience check's view cannot drift apart in this harness.
 *
 * @param array $bodies Audience slug => markup.
 */
function set_notices( array $bodies ) {
	$GLOBALS['opts'][ WPCPM_Notices::OPT_NAME ] = $bodies;
}

set_notices(
	array(
		'student'     => 'Reports are due Friday.',
		'mentor'      => 'Please confirm your <a href="/hours/">hours</a>.',
		'institution' => 'Agreements renew in March.',
		'admin'       => 'The sync ran.',
	)
);

// 10 plain student. 20 plain mentor. 30 institution. 40 administrator.
$GLOBALS['institution_members'] = array( 30 );
// 50 administrator who also mentors - recognized by an Airtable record, never by role.
// 60 student who also mentors. 70 subscriber in no audience.
// 80 holds the Institution role with no live membership, 90 acts for an institution with no
// role yet: the two accounts that tell membership and the role apart.
$GLOBALS['users'][10] = new WP_User( 10, 'Student', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][20] = new WP_User( 20, 'Mentor', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Institution', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][40] = new WP_User( 40, 'Admin', array( 'administrator' ) );
$GLOBALS['users'][50] = new WP_User( 50, 'Admin who mentors', array( 'administrator' ) );
$GLOBALS['users'][60] = new WP_User( 60, 'Student who mentors', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][70] = new WP_User( 70, 'Subscriber', array( 'subscriber' ) );
$GLOBALS['users'][80] = new WP_User( 80, 'Former institution', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][90] = new WP_User( 90, 'Institution staff', array( 'subscriber' ) );
$GLOBALS['institution_members'][] = 90;
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
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * Which notices a user sees.
 *
 * `current()` memoizes per request, which a single-process harness cannot reset - so
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
// The two halves of the delegation, and the reason it delegates at all: `detach()` leaves the
// account holding the Institution role, and `attach()` adds the role to an account that may
// already be a member by the time the role lands. Testing the role here would show a notice to
// somebody whose access ended this morning and hide it from the person who took it over.
ck( 'the Institution role without a live membership sees nothing',
    seen_by( 80 ), array() );
ck( 'and a live membership without the role still sees it',
    seen_by( 90 ), array( 'institution' ) );
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
// editor - so the assertions that used to pin the post type now pin its absence, because
// leaving `register_post_type()` behind would re-register a type nothing reads.
$pipeline = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-notices.php' );
$tool_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php' );

ck( 'the institution audience asks the members class, and never the role',
    array(
        (bool) strpos( $pipeline, 'WPCPM_Institution_Members::is_member( $user )' ),
        (bool) strpos( $pipeline, 'ROLE_INSTITUTION' ),
    ),
    array( true, false ) );
ck( 'bare lines become paragraphs, then everything is filtered',
    array( (bool) strpos( $pipeline, 'wp_kses_post( wpautop( $body ) )' ) ), array( true ) );
ck( 'no post type is registered any more',
    array( (bool) strpos( $pipeline, 'register_post_type(' ) ), array( false ) );
// `do_blocks()` survives in exactly one place: the one-time move of content off the old
// posts, where block markup is converted on the way into the option. Counted as the call
// with its argument, not as the bare name - the docblock above it says `do_blocks()` too,
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

	$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]     = $settings;
	$GLOBALS['opts'][ WPCPM_Notices::OPT_MIGRATED ] = 1;
	$GLOBALS['opts'][ WPCPM_Notices::OPT_PLAIN ]    = $revision;
	$GLOBALS['opts'][ WPCPM_Notices::OPT_NAME ]       = $current;

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
// custom toolbar cancels both - which would leave a notice unable to hold an image.
ck( 'the tool mounts the full classic editor with its media button',
    array(
        (bool) strpos( $tool_src, 'wp_editor(' ),
        (bool) strpos( $tool_src, "'media_buttons' => true" ),
        (bool) strpos( $tool_src, "'teeny'         => false" ),
    ),
    array( true, true, true ) );
ck( 'the save checks a nonce and the manage capability',
    array(
        (bool) strpos( $tool_src, "check_admin_referer( 'wpcpm_notice_' . \$audience, self::NONCE . '_' . \$audience )" ),
        (bool) strpos( $tool_src, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ),
    ),
    array( true, true ) );
// The outcome travels as a flash, not as `?saved=1`: a query argument stays in the address
// bar, so reloading the screen reports a save that did not happen.
ck( 'the outcome is a flash, not a query argument',
    array(
        (bool) preg_match( '/WPCPM_Flash::set\(\s+self::FLASH/', $tool_src ),
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

/* ---- one form and one Save per audience (1.123.58) ---------------------- */

echo "\n=== One form and one Save per audience ===\n";

$GLOBALS['uid'] = 40;
$full           = array( 'student' => 'Student text', 'mentor' => 'Mentor text', 'institution' => 'Institution text', 'sponsor' => 'Sponsor text', 'admin' => 'Admin text' );

/** The tool's screen, as one request draws it. */
function draw_notices() {
	global $tool;
	ob_start();
	$tool->render_admin_page();
	return (string) ob_get_clean();
}

/** The same person on the next request, so the flash can be read again. */
function next_request( $run ) {
	static $next = 900;
	$from = $GLOBALS['uid'];
	++$next;
	$GLOBALS['umeta'][ $next ] = isset( $GLOBALS['umeta'][ $from ] ) ? $GLOBALS['umeta'][ $from ] : array();
	$GLOBALS['users'][ $next ] = clone $GLOBALS['users'][ $from ];
	$GLOBALS['users'][ $next ]->ID = $next;
	$GLOBALS['manage'][] = $next;
	$GLOBALS['uid']      = $next;
	$result              = $run();
	$GLOBALS['uid']      = $from;
	return $result;
}

/** Press one audience's Save with a body and the fingerprint the page drew. */
function press_notice( $audience, $body, $drawn ) {
	global $tool;
	$_POST = array( 'wpcpm_audience' => $audience, 'wpcpm_notice_body' => $body, 'wpcpm_drawn_body' => $drawn );
	$GLOBALS['option_writes']  = array();
	$GLOBALS['nonce_checked']  = array();
	try {
		$tool->handle_save();
	} catch ( WPCPM_Test_Stop $e ) {
		return $e->getMessage();
	}
	return 'no redirect';
}

set_notices( $full );
$page = draw_notices();
$forms = array();
preg_match_all( '#<form\b.*?</form>#s', $page, $forms );
$forms = $forms[0];

ck( 'each audience is a form of its own with its own Save',
    array( count( $forms ), count( array_filter( $forms, static function ( $f ) { return 1 === substr_count( $f, 'Save this notice' ) && 1 === substr_count( $f, 'name="wpcpm_audience"' ); } ) ) ),
    array( 5, 5 ) );
ck( 'and each form carries the audience\'s nonce and the fingerprint of the body as drawn',
    array( false !== strpos( $forms[0], 'value="nonce-wpcpm_notice_student"' ), false !== strpos( $forms[0], 'name="wpcpm_drawn_body" value="' . WPCPM_Settings::fingerprint( 'Student text' ) . '"' ) ),
    array( true, true ) );

$drawn_mentor = WPCPM_Settings::fingerprint( 'Mentor text' );
$drawn_student = WPCPM_Settings::fingerprint( 'Student text' );

ck( 'a Save of the students\' notice leaves the mentors\' notice as stored, whatever the page drew',
    array( press_notice( 'student', 'Student text, edited', $drawn_student ), WPCPM_Notices::body( 'student' ), WPCPM_Notices::body( 'mentor' ) ),
    array( 'redirect', 'Student text, edited', 'Mentor text' ) );
ck( 'its nonce is the audience\'s own',
    $GLOBALS['nonce_checked'], array( 'wpcpm_notice_student' ) );

set_notices( $full );
unset( $GLOBALS['umeta'][40] );
$as_drawn = press_notice( 'mentor', 'Mentor text', $drawn_mentor );
$as_drawn_outcome = next_request( static function () { return WPCPM_Flash::take( WPCPM_Header_Notices::FLASH ); } );
ck( 'a body posted back as drawn writes nothing, and says the notice is saved',
    array( $as_drawn, $GLOBALS['option_writes'], WPCPM_Notices::body( 'mentor' ), $as_drawn_outcome['outcome'] ?? null ),
    array( 'redirect', array(), 'Mentor text', 'notice-saved' ) );

// The students' stored body changes elsewhere after the page was drawn; the page then posts a new body.
set_notices( $full );
unset( $GLOBALS['umeta'][40] );
$stale_drawn = WPCPM_Settings::fingerprint( 'Student text' );
set_notices( array_merge( $full, array( 'student' => 'Changed in another tab' ) ) );
press_notice( 'student', 'My new text', $stale_drawn );
$kept_after = next_request( static function () { return WPCPM_Flash::kept( 'notice:student' ); } );
$outcome    = next_request( static function () { return WPCPM_Flash::take( WPCPM_Header_Notices::FLASH ); } );

ck( 'a body changed elsewhere since the page was drawn is not overwritten: the typing is kept and the sentence says so',
    array( WPCPM_Notices::body( 'student' ), $outcome['outcome'] ?? null, $kept_after['values']['body'] ?? null, $GLOBALS['option_writes'] ),
    array( 'Changed in another tab', 'notice-stale', 'My new text', array() ) );

$page = next_request( static function () use ( $tool ) {
	WPCPM_Flash::keep( 'notice:student', array( 'body' => 'My new text' ) );
	return draw_notices();
} );
ck( 'the kept typing is drawn back in that audience\'s editor only',
    array( false !== strpos( $page, '>My new text</textarea>' ), substr_count( $page, 'My new text' ), false !== strpos( $page, '>Mentor text</textarea>' ) ),
    array( true, 1, true ) );

preg_match_all( '/ id="([^"]+)"/', $page_all = draw_notices(), $ids );
ck( 'the five forms repeat no id',
    array( count( $ids[1] ), count( array_unique( $ids[1] ) ) ), array( count( $ids[1] ), count( $ids[1] ) ) );
$admin_label = WPCPM_Notices::audiences()['admin'];
$admin_flash = next_request( static function () {
	$GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ][ WPCPM_Header_Notices::FLASH ] = array( 'outcome' => 'notice-saved', 'audience' => 'admin' );
	return draw_notices();
} );
ck( 'the saved sentence names the audience as the heading prints it',
    array( false !== strpos( $admin_flash, '<h2><label for="wpcpm_notice_admin">Administrators</label></h2>' ), false !== strpos( $admin_flash, '<p>The notice for Administrators is saved.</p>' ) ),
    array( true, true ) );

ck( 'the sentences are the brief\'s',
    array(
        false !== strpos( $tool_src, "'The notice for %s is saved.'" ),
        false !== strpos( $tool_src, "'The notice for %s was not saved: it was changed elsewhere after this page was opened. Your text is still in the box: check it against the notice as it is now, and save again.'" ),
    ),
    array( true, true ) );

ck( 'without the capability nothing is read or written',
    array( ( static function () { $GLOBALS['uid'] = 70; $r = press_notice( 'student', 'x', 'y' ); $GLOBALS['uid'] = 40; return $r; } )(), $GLOBALS['nonce_checked'] ),
    array( 'die 403', array() ) );

set_notices( $saved );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
