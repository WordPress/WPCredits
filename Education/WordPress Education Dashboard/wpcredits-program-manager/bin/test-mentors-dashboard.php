<?php
/**
 * The Mentor Report Card page: the script it loads for the forms it draws, and the hours on each
 * student's row.
 *
 * A mentor's note carries a Delete that asks a question first, and the question is
 * `assets/js/forms.js` reading `data-wpcpm-confirm`. The page draws a calendar whose script names
 * the forms script as a dependency, so the script reaches every page that draws a Delete, and
 * `render()` still enqueues it itself, above all of its early returns, and does not rely on the
 * calendar for it. This pins that house rule and not the question: if a later edit drops those
 * lines, the page stops loading the script on its own. The page is drawn here for the two visitors
 * who stop early, one logged out and one with no student list, because the enqueue sits above both
 * of those returns.
 *
 * **Each student's row shows the hours they logged**, in the words the Institution Dashboard's
 * roster prints for the same cell: "12 of 150" on a track with a target, "7.5 h" on one without,
 * "0 of 150" for a student who logged none, and the card's own gap for one nobody has logged for.
 * The account's copy of the count is read before the mentor list's, as the roster reads it, while
 * the target follows the status the card's Program row prints. A track whose form asks no Hours
 * question draws no Hours row at all. And only the mentor's own students: a mentor who names
 * another mentor's list in the URL is shown their own, with none of the other list's hours on it.
 *
 * **A student waiting to graduate keeps their course.** The Program row names and links the course
 * the account remembers, with the Pending graduation badge beside it, and the Hours row reads the
 * count against that course's target; with no course known the row says Pending graduation alone.
 * The Program row drawn here is the real shared one, `WPCPM_Program::program_field()`.
 *
 * Run from the plugin root:  php bin/test-mentors-dashboard.php
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

$GLOBALS['opts']       = array();
$GLOBALS['umeta']      = array();
$GLOBALS['users']      = array();
$GLOBALS['uid']        = 0;
$GLOBALS['manage']     = array();
$GLOBALS['scripts']    = array();
$GLOBALS['registered'] = array();

class WP_Error {
	public function __construct( $c = '', $m = '' ) {}
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
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
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $u, $p = null ) { return (string) $u; }
function wp_kses( $s, $allowed ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) { return array_merge( $pairs, array_intersect_key( $atts, $pairs ) ); }
// What is hooked runs: the program map is made of the compiled tracks' filters (below).
function apply_filters( $t, $v, ...$args ) { return wpcpm_stub_apply_filters( $t, $v, ...$args ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function wp_kses_post( $s ) { return (string) $s; }
// Core's own, minus the locale: it pads to the places it is given, so "150 of 150" and
// "150.00 of 150" are two different things this suite can see.
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function date_i18n( $f, $t = false ) { return gmdate( (string) $f, false === $t ? time() : (int) $t ); }
function wp_date( $f, $t = null ) { return gmdate( (string) $f, null === $t ? time() : (int) $t ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function absint( $v ) { return abs( (int) $v ); }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
// Both of the mentor queries (by role, and by the Airtable link) answer with `$GLOBALS['listed']`:
// nobody, unless a check lists somebody.
function get_users( $a = array() ) { return $GLOBALS['listed'] ?? array(); }
// Core's, as `selected()` writes it, for the switcher a manager's page draws.
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? " selected='selected'" : ''; }
// Core's, as far as the fixtures' names reach it: Ágata. The switcher sorts by it.
function remove_accents( $s, $locale = '' ) { return strtr( (string) $s, array( 'Á' => 'A' ) ); }
function get_queried_object_id() { return 0; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function current_user_can( $c ) { return in_array( $GLOBALS['uid'], $GLOBALS['manage'], true ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_post_status( $id ) { return false; }
function get_permalink( $id = 0 ) { return ''; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_login_url( $redirect = '' ) { return 'https://example.test/wp-login.php'; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function wp_create_nonce( $a = -1 ) { return 'nonce'; }
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style() {} function wp_enqueue_style() {}
function wp_localize_script() {}

// The script functions keep what they were asked, so the page can be read for what it loads.
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	$GLOBALS['registered'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $in_footer );
}
function wp_script_is( $handle, $list = 'enqueued' ) {
	return 'registered' === $list ? isset( $GLOBALS['registered'][ $handle ] ) : in_array( $handle, $GLOBALS['scripts'], true );
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	if ( '' !== $src ) {
		wp_register_script( $handle, $src, $deps, $ver, $in_footer );
	}

	$GLOBALS['scripts'][] = $handle;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/plugin/' );
define( 'WPCPM_VERSION', 'test' );

/* ---- the pieces a student's card draws through, stubbed to their contracts ---- */

class WPCPM_Two_Factor {
	public static function prompt( $user ) {}
}
class WPCPM_Handbook_Assistant {
	public static function render_resources( $audience ) { return ''; }
}
class WPCPM_Call_Calendar {
	public static function render_mentor( $mentor ) {}
}
class WPCPM_Mentor_Notes {
	const AUDIENCE_MENTOR = 'mentor';
	public static function focused_student() { return ''; }
	public static function anchor( $record ) { return 'student-' . $record; }
	public static function count_notes( $record, $audience = '' ) { return 0; }
	public static function render( $record, $name, $mentor_id ) {}
}

/** The accounts behind the records: record ID => user ID, from `$GLOBALS['accounts']`. */
class WPCPM_Students_Sync {
	const META_PROGRAM = 'wpcpm_student_program';
	const META_COURSE  = 'wpcpm_student_course';
	public static function user_for_record( $record ) {
		$id = $GLOBALS['accounts'][ (string) $record ] ?? 0;
		return $id ? new WP_User( $id ) : null;
	}
}

// Declares add_filter() and add_action(), which keep what is hooked: the program map is the
// compiled tracks', reached through their filters.
require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
// The forms the tracks compile to: the Hours row is drawn only where a student's form asks hours.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';

// The four original tracks as the site runs them: 150 hours for In Sensei, none for the
// Developer Track (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

$GLOBALS['accounts'] = array();

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       exp: ' . var_export( $expected, true ) . '  got: ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * Draw the page for one visitor, with nothing loaded or registered beforehand.
 *
 * @param int $uid     The signed-in user, or 0 for a visitor who is logged out.
 * @param array $known Script handles another module has already registered.
 * @return string The page.
 */
function page_for( $uid, array $known = array() ) {
	$GLOBALS['uid']        = $uid;
	$GLOBALS['scripts']    = array();
	$GLOBALS['registered'] = array_fill_keys( $known, array( 'src' => 'registered-earlier', 'deps' => array(), 'ver' => false, 'footer' => true ) );

	return WPCPM_Mentors_Dashboard::render( array() );
}

$GLOBALS['users'][7] = new WP_User( 7, 'Visitor', array( 'subscriber' ) );

echo "\n=== The page loads the script that asks a note's Delete ===\n";

$out = page_for( 0 );
ck( 'a visitor who is logged out is sent to log in', false !== strpos( $out, 'Please log in to see the students assigned to you.' ) );
ck( 'and the page enqueued wpcpm-forms before it stopped there', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ) );

$out = page_for( 7 );
ck( 'a signed-in visitor with no student list is told so', false !== strpos( $out, 'This page is for program mentors.' ) );
ck( 'and the page enqueued wpcpm-forms before it stopped there', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ) );
ck( 'and only once', count( array_keys( $GLOBALS['scripts'], 'wpcpm-forms', true ) ), 1 );

page_for( 0 );
ck( 'the script is registered from assets/js/forms.js, at the plugin version, in the footer, when no module has registered it yet',
    $GLOBALS['registered']['wpcpm-forms'] ?? null,
    array( 'src' => 'https://example.test/plugin/assets/js/forms.js', 'deps' => array(), 'ver' => 'test', 'footer' => true ) );

page_for( 0, array( 'wpcpm-forms' ) );
ck( 'and left as the calendar registered it when one has, so the handle stays one script',
    array( $GLOBALS['registered']['wpcpm-forms']['src'] ?? null, in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ) ),
    array( 'registered-earlier', true ) );

echo "\n=== A student's row shows the hours they logged ===\n";

/**
 * One row of a mentor's list, as the mentors sync writes it.
 *
 * @param string $record Students Reports record ID.
 * @param string $name   Student's name.
 * @param string $status Students Reports status.
 * @param string $hours  The Hours cell as the base sends it, or ''.
 * @return array
 */
function mentee( $record, $name, $status, $hours ) {
	return array(
		'record_id' => $record,
		'name'      => $name,
		'status'    => $status,
		'is_past'   => false,
		'hours'     => $hours,
	);
}

/**
 * The Hours row of one student's card, as text, or null when the card has none.
 *
 * @param string $html The page.
 * @param string $name The student's name, as the card's heading prints it.
 * @return string|null
 */
function hours_row( $html, $name ) {
	foreach ( explode( '<article class="wpcpm-mentee"', $html ) as $card ) {
		if ( false === strpos( $card, '<h3 class="wpcpm-mentee__name">' . $name . '</h3>' ) ) {
			continue;
		}

		if ( ! preg_match( '#<span class="wpcpm-mentee__label">(?:<svg.*?</svg>)?Hours</span></th><td class="wpcpm-mentee__value"[^>]*>(.*?)</td>#s', $card, $m ) ) {
			return null;
		}

		return trim( html_entity_decode( strip_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
	}

	return null;
}

// Two mentors, each recognized by the Airtable link the sync leaves on the account, and one
// program manager who mentors nobody.
$GLOBALS['users'][30] = new WP_User( 30, 'Mia Mentor' );
$GLOBALS['users'][31] = new WP_User( 31, 'Max Mentor' );
$GLOBALS['users'][50] = new WP_User( 50, 'Pat Administrator' );
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMEN00000000001';
$GLOBALS['umeta'][31][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMEN00000000002';
$GLOBALS['manage'] = array( 50 );

// Mia's list. Ada has an account, which the students sync writes her program row on; the others
// have none yet, so their row on Mia's list is all this page has.
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_MENTEES ] = array(
	mentee( 'recREP00000000001', 'Ada Example', 'In Sensei', '12' ),
	mentee( 'recREP00000000002', 'Bo Example', 'Developer Track', '7.5' ),
	mentee( 'recREP00000000003', 'Cy Example', 'In Sensei', '0' ),
	mentee( 'recREP00000000004', 'Di Example', 'In Sensei', '' ),
);
$GLOBALS['accounts']['recREP00000000001']                    = 41;
$GLOBALS['umeta'][41][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'In Sensei', 'hours' => '12' );

// Max's list: one student, with an account and a count nobody on Mia's list has.
$GLOBALS['umeta'][31][ WPCPM_Mentors_Sync::META_MENTEES ] = array(
	mentee( 'recREP00000000005', 'Eve Example', 'In Sensei', '99' ),
);
$GLOBALS['accounts']['recREP00000000005']                    = 45;
$GLOBALS['umeta'][45][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'In Sensei', 'hours' => '99' );

$page = page_for( 30 );

ck( 'a track with a target shows the hours against it', hours_row( $page, 'Ada Example' ), '12 of 150' );
ck( 'a track with no target shows the hours alone', hours_row( $page, 'Bo Example' ), '7.5 h' );
ck( 'and never a denominator of nothing', strpos( $page, ' of 0' ), false );
ck( 'zero hours is a count, not a gap', hours_row( $page, 'Cy Example' ), '0 of 150' );
ck( 'a student nobody has logged for reads as the card\'s own gap, never as zero', hours_row( $page, 'Di Example' ), 'Not set' );

// **The account first, the mentee row second**, the order the Institution Dashboard reads the
// same two copies of the same cell in: the account's row is the fresher of the two when a student
// saves their own hours between runs of the two syncs.
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_MENTEES ][0]['hours'] = '5';

ck( 'the account\'s copy is preferred over the mentee row\'s', hours_row( page_for( 30 ), 'Ada Example' ), '12 of 150' );

$GLOBALS['umeta'][41][ WPCPM_Students_Sync::META_PROGRAM ]['hours'] = '0';

ck( 'and a count cleared to 0 there wins rather than falling through to the older one', hours_row( page_for( 30 ), 'Ada Example' ), '0 of 150' );

// **The target follows the status the card prints**, the mentor list's, which is what the Program
// row above the Hours row names. In the hours between the two syncs the account's copy can name
// another track, and a target taken from it would put "of 150" beside a track the card says has
// none, or leave it off one the card says has 150. The count itself still reads the account first.
$GLOBALS['umeta'][41][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'Developer Track', 'hours' => '12' );

ck( 'the target comes from the status the card prints, not from the account\'s track', hours_row( page_for( 30 ), 'Ada Example' ), '12 of 150' );

$GLOBALS['umeta'][41][ WPCPM_Students_Sync::META_PROGRAM ]           = array( 'program' => 'In Sensei', 'hours' => '12' );
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_MENTEES ][0]['status'] = 'Developer Track';

ck( 'so a card naming the Developer Track shows no target, whatever the account says', hours_row( page_for( 30 ), 'Ada Example' ), '12 h' );

$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_MENTEES ][0]['status'] = 'In Sensei';
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_MENTEES ][0]['hours']  = '12';

// **A track whose form asks no Hours question draws no Hours row.** Nobody can log hours for its
// students, so the card's "Not set" there would be a gap nobody could ever fill. The test is the
// one the student's own hours box asks: whether the form of the track the card prints has an Hours
// question. A track may have no hours at all, so the card draws less, and nothing asks for one.
$dev_form = $GLOBALS['opts'][ WPCPM_Tracks::OPT_FIELDS_PREFIX . 'dev' ];
unset( $GLOBALS['opts'][ WPCPM_Tracks::OPT_FIELDS_PREFIX . 'dev' ]['Hours'] );
WPCPM_Tracks::flush();

$no_hours = page_for( 30 );

ck( 'a track with no Hours question draws its student\'s card with no Hours row',
	array( false !== strpos( $no_hours, '<h3 class="wpcpm-mentee__name">Bo Example</h3>' ), hours_row( $no_hours, 'Bo Example' ) ),
	array( true, null ) );
ck( 'and a track that asks it still draws the row', hours_row( $no_hours, 'Ada Example' ), '12 of 150' );

// Asked of the printed status's track again, not the account's.
$GLOBALS['umeta'][41][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'Developer Track', 'hours' => '12' );

ck( 'and the question is asked of the track the card prints, not of the account\'s', hours_row( page_for( 30 ), 'Ada Example' ), '12 of 150' );

$GLOBALS['umeta'][41][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'In Sensei', 'hours' => '12' );
$GLOBALS['opts'][ WPCPM_Tracks::OPT_FIELDS_PREFIX . 'dev' ] = $dev_form;
WPCPM_Tracks::flush();

// The words are the Institution Dashboard's, from the one place both pages take them, so the two
// cannot come to print the same count two ways.
$sources = array(
	(string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php' ),
	(string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php' ),
);

ck( 'the row and the Institution Dashboard\'s cell both take their words from the program map',
	array( substr_count( $sources[0], 'WPCPM_Program::hours_text( $' ), substr_count( $sources[1], 'WPCPM_Program::hours_text( $' ) ),
	array( 1, 1 ) );

// Escaped like every other value on the card: a Number column cannot hold markup today, but a
// field type changed in the base can.
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_MENTEES ][1]['hours'] = '<b>7</b>';
$escaped = page_for( 30 );

ck( 'a value carrying markup is escaped, not rendered', array( strpos( $escaped, '<b>7</b>' ), false !== strpos( $escaped, '&lt;b&gt;7&lt;/b&gt;' ) ), array( false, true ) );

$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_MENTEES ][1]['hours'] = '7.5';

echo "\n=== Only the mentor's own students ===\n";

// A mentor who edits the URL to name another mentor's list is not a manager, so the argument is
// never read: the page is drawn from their own list, and no other student's hours reach it.
$_GET['wpcpm_mentor'] = '31';
$crafted              = page_for( 30 );

ck( 'a mentor who asks for another mentor\'s list is shown their own', array( false !== strpos( $crafted, 'Ada Example' ), strpos( $crafted, 'Eve Example' ) ), array( true, false ) );
ck( 'and none of the other mentor\'s student\'s hours', strpos( $crafted, '99 of 150' ), false );

// The same argument from a program manager is the inspection the switcher exists for.
$inspected = page_for( 50 );

ck( 'a program manager inspecting that mentor sees that mentor\'s student and hours', hours_row( $inspected, 'Eve Example' ), '99 of 150' );
ck( 'and nobody else\'s', strpos( $inspected, 'Ada Example' ), false );

unset( $_GET['wpcpm_mentor'] );

echo "\n=== A student waiting to graduate keeps their course ===\n";

/**
 * One row of one student's card, as markup, or null when the card has no such row.
 *
 * @param string $html  The page.
 * @param string $name  The student's name, as the card's heading prints it.
 * @param string $label The row's label.
 * @return string|null
 */
function card_row( $html, $name, $label ) {
	foreach ( explode( '<article class="wpcpm-mentee"', $html ) as $card ) {
		if ( false === strpos( $card, '<h3 class="wpcpm-mentee__name">' . $name . '</h3>' ) ) {
			continue;
		}

		if ( ! preg_match( '#<span class="wpcpm-mentee__label">(?:<svg.*?</svg>)?' . preg_quote( $label, '#' ) . '</span></th><td class="wpcpm-mentee__value"[^>]*>(.*?)</td>#s', $card, $m ) ) {
			return null;
		}

		return trim( $m[1] );
	}

	return null;
}

/**
 * The badge one student's card wears beside their name.
 *
 * @param string $html The page.
 * @param string $name The student's name.
 * @return string|null
 */
function summary_badge( $html, $name ) {
	return preg_match( '#<h3 class="wpcpm-mentee__name">' . preg_quote( $name, '#' ) . '</h3>(<span class="wpcpm-badge[^"]*">[^<]*</span>)#', $html, $m ) ? $m[1] : null;
}

// A third mentor, whose students are all waiting to graduate. Each account remembers the track the
// students sync last saw on it, or nothing, and the list says Pending graduation for all three.
$GLOBALS['users'][32] = new WP_User( 32, 'Nia Mentor' );
$GLOBALS['umeta'][32][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMEN00000000003';
$GLOBALS['umeta'][32][ WPCPM_Mentors_Sync::META_MENTEES ]   = array(
	mentee( 'recREP00000000006', 'Gus Example', 'Pending graduation', '12' ),
	mentee( 'recREP00000000007', 'Hana Example', 'Pending graduation', '20' ),
	mentee( 'recREP00000000008', 'Ian Example', 'Pending graduation', '5' ),
);
$GLOBALS['accounts']['recREP00000000006'] = 46;
$GLOBALS['accounts']['recREP00000000007'] = 47;
$GLOBALS['accounts']['recREP00000000008'] = 48;
$GLOBALS['umeta'][46] = array( WPCPM_Students_Sync::META_PROGRAM => array( 'program' => 'Pending graduation', 'hours' => '12' ), WPCPM_Students_Sync::META_COURSE => 'Developer Track' );
$GLOBALS['umeta'][47] = array( WPCPM_Students_Sync::META_PROGRAM => array( 'program' => 'Pending graduation', 'hours' => '20' ), WPCPM_Students_Sync::META_COURSE => 'In Sensei 50h' );
$GLOBALS['umeta'][48] = array( WPCPM_Students_Sync::META_PROGRAM => array( 'program' => 'Pending graduation', 'hours' => '5' ), WPCPM_Students_Sync::META_COURSE => '' );

$pending = page_for( 32 );
$badge   = '<span class="wpcpm-badge wpcpm-badge--pending">Pending graduation</span>';
$link    = static function ( $status ) {
	return '<a href="' . WPCPM_Program::course_url( $status ) . '" target="_blank" rel="noopener noreferrer">' . WPCPM_Program::label( $status ) . '</a>';
};

ck( 'the Developer Track has a course to link to, so the checks below can see it', '' !== WPCPM_Program::course_url( 'Developer Track' ), true );
ck( 'a pending Developer Track student\'s Program row names and links the course, as a current student\'s does, with the Pending graduation badge beside it',
	card_row( $pending, 'Gus Example', 'Program' ), $link( 'Developer Track' ) . ' ' . $badge );
ck( 'and the card still wears the Pending graduation badge beside the name', summary_badge( $pending, 'Gus Example' ), $badge );
ck( 'and the hours are read against the Developer Track, which has no target', hours_row( $pending, 'Gus Example' ), '12 h' );
ck( 'a pending 50-hour student keeps their course and its target',
	array( card_row( $pending, 'Hana Example', 'Program' ), hours_row( $pending, 'Hana Example' ) ),
	array( $link( 'In Sensei 50h' ) . ' ' . $badge, '20 of 50' ) );
ck( 'a pending student with no known course shows Pending graduation alone, with no link',
	array( card_row( $pending, 'Ian Example', 'Program' ), hours_row( $pending, 'Ian Example' ), summary_badge( $pending, 'Ian Example' ) ),
	array( 'Pending graduation', '5 h', $badge ) );

// Paused is on no track and keeps no course, whatever the account remembers.
$GLOBALS['umeta'][32][ WPCPM_Mentors_Sync::META_MENTEES ][0]['status'] = 'Paused';

ck( 'a paused student\'s Program row still says Paused, with no course', card_row( page_for( 32 ), 'Gus Example', 'Program' ), 'Paused' );

$GLOBALS['umeta'][32][ WPCPM_Mentors_Sync::META_MENTEES ][0]['status'] = 'Pending graduation';

echo "\n=== A manager's switcher ===\n";

// Five mentors, as the queries hand them over, and as `all_mentors()` orders them byte by byte:
// Mentor 10 before Mentor 2, and Ágata after every unaccented name.
$GLOBALS['users'][33] = new WP_User( 33, 'Ágata Mentor' );
$GLOBALS['users'][34] = new WP_User( 34, 'Mentor 10' );
$GLOBALS['users'][35] = new WP_User( 35, 'Mentor 2' );
$GLOBALS['listed']    = array( $GLOBALS['users'][30], $GLOBALS['users'][31], $GLOBALS['users'][33], $GLOBALS['users'][34], $GLOBALS['users'][35] );
$_GET['wpcpm_mentor'] = '31';
$switched             = page_for( 50 );

unset( $_GET['wpcpm_mentor'] );
$GLOBALS['listed'] = array();

preg_match( '#<select name="wpcpm_mentor" id="wpcpm-mentor-switcher" autocomplete="off">(.*?)</select>#s', $switched, $list );
preg_match_all( '#<option value="(\d+)"( selected=\'selected\')?>([^<]*)</option>#', $list[1] ?? '', $entries, PREG_SET_ORDER );

ck( 'a manager\'s Mentor Report Card draws the switcher, every mentor A to Z', array_column( $entries, 3 ), array( 'Ágata Mentor', 'Max Mentor', 'Mentor 2', 'Mentor 10', 'Mia Mentor' ) );
ck( 'with the mentor being viewed selected, and only that one', array_column( array_filter( $entries, function ( $entry ) { return '' !== $entry[2]; } ), 1 ), array( '31' ) );
ck(
	'with the one field that finds and picks a mentor, the note, and the page loads the script that makes the field work',
	array(
		false !== strpos( $switched, 'role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="wpcpm-mentor-switcher-list" autocomplete="off" spellcheck="false" placeholder="Find a mentor" />' ),
		false !== strpos( $switched, '<span class="wpcpm-dashboard__switcher-note">Only Administrators see this control.</span>' ),
		in_array( 'wpcpm-switcher', $GLOBALS['scripts'], true ),
	),
	array( true, true, true )
);

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
