<?php
/**
 * The institution roster: the four groups, the cohort picker, the strip and the filters.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The view renders what `WPCPM_Roster_Index::groups()` returns and nothing else. The
 *   fixture holds a SPAM row and a Duplicated row that the index's contract drops, and the
 *   assertion below is what fails the day somebody renders `rows()` instead because it was
 *   already to hand.
 * - **`accessibility` appears nowhere.** The fixture student carries a disclosure on her
 *   index row *and* inside her cached `wpcpm_student_program` block, which is the block two
 *   of the columns are read out of. The disclosure was made to the program, not the school.
 * - The picker is built from the institution's own rows, newest first, with "No start date"
 *   last and only when somebody is in it. A cohort holding nobody who signed up is not an
 *   option, because picking it would draw an empty roster.
 * - The strip is two numbers against two numbers, and an empty previous semester says so in
 *   words. Zeros there read as a failure; "no students started in July to December 2024"
 *   reads as what happened.
 * - Every count carries the index's read time, in the strip and again in the footer.
 * - **The hours cell prints a denominator only where the program has one.** The two clocked
 *   tracks read "12 of 150" and "6.2 of 50"; the Developer Track, a track the hours map has
 *   never heard of and a student whose track is behind them all read "12 h", because "12 of 0"
 *   is a target this program does not have. A fractional count keeps its fraction, a count
 *   past the target is printed as it stands, and a student nobody has logged for reads
 *   "Not recorded" while a student who logged nothing reads "0 of 150".
 * - The filters narrow before the render and survive in the URL: the controls come back
 *   filled in, and the link to a student's detail view carries the cohort and the filters,
 *   which is what makes a filtered roster a page a colleague can be sent.
 *
 * Run from the plugin root:  php bin/test-institution-roster-view.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

// The site's today, which is what WPCPM_Cohort::current() reads. The institution in the
// fixture has nobody in this semester, so the picker has to fall back to its newest.
$GLOBALS['today']  = '2026-09-02';
$GLOBALS['uri']    = '/institution-dashboard/';
$GLOBALS['opts']   = array(
	'permalink_structure' => '/%postname%/',
	'date_format'         => 'F j, Y',
	'time_format'         => 'g:i a',
);
$GLOBALS['umeta']  = array();
$GLOBALS['allowed'] = true;
// Actions the fence refuses while everything else is allowed. The real policy answers each
// action off its own row of grounds(), so "reads the roster" and "may export it" are
// separately answerable questions however alike their answers happen to be today. This is
// what lets the export control be tested against the answer it is actually drawn from.
$GLOBALS['refuse'] = array();

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '', $email = '' ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
	}
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( $text );

	return $remove_breaks ? trim( preg_replace( '/[\r\n\t ]+/', ' ', $text ) ) : $text;
}

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
// Core's character filter (fix round 2, the hostile-input checks): a quote or an angle bracket never reaches an attribute.
function esc_url( $s ) { return str_replace( '&', '&#038;', (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
// Core's own, minus the locale: it pads to the number of places it is given, which is what
// makes the difference between "150 of 150" and "150.00 of 150" a thing this suite can see.
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function get_user_by( $field, $value ) { return false; }
// The narrowed list asks whether a row's account exists before it links its name (1.123.98, the outside review).
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function get_queried_object_id() { return 12; }
// The parts (Task 15): the front end, and the dates as the mentor card formats them, read back as the raw day so the
// parts' dates read what the formatter stub above prints.
function is_admin() { return false; }
function date_i18n( $f, $t = false ) { return gmdate( 'Y-m-d', (int) $t ); }
// Core's own output, single quotes and all, so the markup asserted here is the markup a
// browser gets rather than a tidier version of it.
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? " selected='selected'" : ''; }

/** `Y-m-d` is today on the site's clock; anything else is formatted from the timestamp. */
function wp_date( $f, $t = null, $z = null ) {
	if ( 'Y-m-d' === $f && null === $t ) { return $GLOBALS['today']; }
	return gmdate( $f, null === $t ? time() : (int) $t );
}

/** A URL taken apart the way add_query_arg() and remove_query_arg() see one. */
function wpcpm_test_split( $url ) {
	$parts = explode( '?', (string) $url, 2 );
	$args  = array();
	if ( isset( $parts[1] ) ) { parse_str( $parts[1], $args ); }
	return array( $parts[0], $args );
}
function wpcpm_test_join( $path, array $args ) {
	return empty( $args ) ? $path : $path . '?' . http_build_query( $args );
}
function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) {
		$url = null === $value ? $GLOBALS['uri'] : $value;
		$add = $key;
	} else {
		$url = null === $url ? $GLOBALS['uri'] : $url;
		$add = array( $key => $value );
	}
	list( $path, $args ) = wpcpm_test_split( $url );
	foreach ( $add as $k => $v ) { $args[ $k ] = $v; }
	return wpcpm_test_join( $path, $args );
}
function remove_query_arg( $keys, $url = null ) {
	$url = null === $url ? $GLOBALS['uri'] : $url;
	list( $path, $args ) = wpcpm_test_split( $url );
	foreach ( (array) $keys as $k ) { unset( $args[ $k ] ); }
	return wpcpm_test_join( $path, $args );
}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
// The nonce is a readable stand-in rather than a hash, so an assertion can say which action
// the link was keyed to. That is the property that matters here: a link keyed to something
// else is a link `check_admin_referer()` throws out.
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	return add_query_arg( $name, 'nonce-' . $action, $url );
}

/* ---- the other pieces, stubbed to their contracts ------------------------ */

class WPCPM_Mentors_Sync {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $value ) {
		return is_scalar( $value ) && (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
	}
	/** The settings' defaults, which is what the roster index reads for its two lists. */
	public static function tracked_statuses( $settings = null ) {
		return array(
			'active' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ),
			'past'   => array( 'Graduate', 'Dropped out' ),
			'all'    => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Graduate', 'Dropped out' ),
		);
	}
}

class WPCPM_Students_Sync {
	const META_PROGRAM = 'wpcpm_student_program';
	const META_MENTOR  = 'wpcpm_student_mentor';
	const META_COURSE  = 'wpcpm_student_course';
}

/** The fence. Every call is recorded, so the roster's own decide() can be asserted. */
class WPCPM_Institution_Policy {
	const ACT_VIEW_ROSTER = 'view_roster';
	const ACT_EXPORT      = 'export';
	public static function subject_institution( $record_id ) {
		return array( 'type' => 'institution', 'id' => (string) $record_id, 'institution_ids' => array( (string) $record_id ), 'evidence' => 'index' );
	}
	public static function decide( $action, array $subject, $user = null ) {
		$GLOBALS['decisions'][] = array( $action, $subject['id'] );
		$allowed                = $GLOBALS['allowed'] && ! in_array( $action, $GLOBALS['refuse'], true );
		return array(
			'allowed'     => $allowed,
			'ground'      => $allowed ? 'member' : '',
			'institution' => $allowed ? (string) $subject['id'] : '',
			// A decision that names its fields scopes the columns (Task 15: a reader with a narrower set).
			'fields'      => $allowed ? ( $GLOBALS['fields'] ?? null ) : array(),
			'why'         => '',
		);
	}
	public static function scope( array $decision, array $keyed ) {
		if ( empty( $decision['allowed'] ) || ! array_key_exists( 'fields', $decision ) ) { return array(); }
		if ( null === $decision['fields'] ) { return $keyed; }
		$permitted = array();
		foreach ( (array) $decision['fields'] as $key ) { $permitted[ (string) $key ] = true; }
		return array_intersect_key( $keyed, $permitted );
	}
}

class WPCPM_Mentors_Dashboard {
	/** The mentor card's own formatter, stubbed to something a test can read back. */
	public static function format_dates( $start, $end ) {
		if ( '' === $start && '' === $end ) { return ''; }
		if ( '' === $start ) { return 'Until ' . $end; }
		if ( '' === $end ) { return 'From ' . $start; }
		return $start . ' to ' . $end;
	}

	/**
	 * The mentor page's own avatar, stubbed to the real one's markup so the roster's
	 * call to it - and the silence of a row with neither a username nor an email - can
	 * both be asserted here without pulling in the whole mentors dashboard class.
	 */
	public static function render_avatar( $username, $email, $name, $size = 64 ) {
		$url = self::avatar_url( $username, $email, $size );

		if ( '' === $url ) {
			return;
		}

		printf(
			'<img class="wpcpm-avatar" src="%1$s" srcset="%2$s 2x" width="%3$d" height="%3$d" alt="%4$s" title="%5$s" loading="lazy" decoding="async" />',
			esc_url( $url ),
			esc_url( self::avatar_url( $username, $email, $size * 2 ) ),
			(int) $size,
			esc_attr( $name ),
			esc_attr( $username ? 'Photo from their WordPress.org profile' : 'Photo from Gravatar' )
		);
	}

	public static function avatar_url( $username, $email, $size = 64 ) {
		$username = trim( (string) $username );

		if ( '' !== $username ) {
			return 'https://wordpress.org/grav-redirect.php?user=' . rawurlencode( $username ) . '&s=' . (int) $size;
		}

		$email = trim( (string) $email );

		return ( '' !== $email && false !== strpos( $email, '@' ) )
			? 'https://gravatar.example/avatar/' . md5( strtolower( $email ) ) . '?s=' . (int) $size
			: '';
	}
}

/**
 * The roster index, stubbed to contract 2 of the build brief.
 *
 * `groups()` is a faithful miniature of the real one: the cohort filter first, SPAM and
 * Duplicated dropped outright, the two tracked lists deciding current from finished and an
 * empty `reports` list and no `has_mentor` deciding waiting from current, everything else the residue.
 */
class WPCPM_Roster_Index {
	const NEVER_SHOWN = array( 'SPAM', 'Duplicated' );

	public static function read( $record_id ) {
		$store = $GLOBALS['index'][ (string) $record_id ] ?? array( 'read' => 0, 'rows' => array() );
		return array( 'v' => 1, 'read' => (int) $store['read'], 'rows' => $store['rows'] );
	}
	public static function rows( $record_id ) {
		return self::read( $record_id )['rows'];
	}
	public static function groups( $record_id, $cohort = '' ) {
		$groups  = array( 'current' => array(), 'waiting' => array(), 'finished' => array(), 'not_started' => array() );
		$tracked = WPCPM_Mentors_Sync::tracked_statuses();
		$narrow  = WPCPM_Cohort::is_key( $cohort );

		foreach ( self::rows( $record_id ) as $key => $row ) {
			$status = trim( (string) ( $row['status'] ?? '' ) );
			if ( in_array( $status, self::NEVER_SHOWN, true ) ) { continue; }
			if ( $narrow && WPCPM_Cohort::key( $row['start'] ?? '' ) !== $cohort ) { continue; }
			if ( in_array( $status, $tracked['active'], true ) ) {
				$groups[ ( empty( $row['reports'] ) && empty( $row['has_mentor'] ) ) ? 'waiting' : 'current' ][ $key ] = $row;
				continue;
			}
			if ( in_array( $status, $tracked['past'], true ) ) { $groups['finished'][ $key ] = $row; continue; }
			$groups['not_started'][ $key ] = $row;
		}

		return $groups;
	}
	public static function unlinked_for( $record_id ) {
		return $GLOBALS['unlinked'][ (string) $record_id ] ?? array();
	}
}

/** The switcher's argument lives on the dashboard class; the export link reads it from there. */
class WPCPM_Institution_Roster {
	const ARG_VIEW = 'wpcpm_institution_view';
}

// `WPCPM_Institution_Export` is deliberately *not* stubbed here. It is declared further down,
// inside a conditional so PHP cannot early-bind it, which is what lets the first assertions
// run in a process where the export module genuinely does not exist.

// Declares add_filter(), apply_filters() and add_action(): the program map is made of filters.
require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once __DIR__ . '/stubs/view-faults.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';

// The program map is the compiled tracks' (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	++$total;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/* ---- reading the rendered page ------------------------------------------ */

function has( $html, $needle ) { return false !== strpos( $html, $needle ); }

/** The number in a group's phrase, beside its title in the fold's head ("13 students"). */
function group_count( $html, $label ) {
	$pattern = '/<h3 class="wpcpm-ui-fold__title">' . preg_quote( $label, '/' ) . '<\/h3><span class="wpcpm-ui-fold__phrase">(\d+) students?<\/span>/';
	return preg_match( $pattern, $html, $m ) ? (int) $m[1] : null;
}

/** One select's options, in the order they are printed. */
function options( $html, $name ) {
	if ( ! preg_match( '/<select[^>]* name="' . preg_quote( $name, '/' ) . '"[^>]*>(.*?)<\/select>/s', $html, $m ) ) { return null; }
	preg_match_all( '/<option value="([^"]*)"[^>]*>(.*?)<\/option>/s', $m[1], $all, PREG_SET_ORDER );
	$out = array();
	foreach ( $all as $option ) { $out[] = $option[2]; }
	return $out;
}

/** Which of one select's options is marked selected. */
function chosen( $html, $name ) {
	if ( ! preg_match( '/<select[^>]* name="' . preg_quote( $name, '/' ) . '"[^>]*>(.*?)<\/select>/s', $html, $m ) ) { return null; }
	return preg_match( "/<option value=\"([^\"]*)\"(?: selected='selected'| selected)/", $m[1], $s ) ? $s[1] : '';
}

/** The filter bar's row of actions: the Show button and whatever sits beside it. */
function actions( $html ) {
	return preg_match( '/<form class="wpcpm-roster__bar.*?<div class="wpcpm-ui-actions">(.*?)<\/div><\/form>/s', $html, $m ) ? $m[1] : '';
}

/** The export link's address with its entities decoded, or '' when there is no export link. */
function export_href( $html ) {
	if ( ! preg_match( '/<a class="wpcpm-btn wpcpm-btn--quiet" href="([^"]*)">Download this cohort \(CSV\)<\/a>/', $html, $m ) ) { return ''; }
	return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
}

/** The export link's query arguments. */
function export_args( $html ) {
	list( , $args ) = wpcpm_test_split( export_href( $html ) );
	return $args;
}

/** One group's fold, its students and their tables, as plain markup. */
function group_rows( $html, $key ) {
	if ( ! preg_match( '/wpcpm-roster__group--' . preg_quote( $key, '/' ) . '" id="wpcpm-roster-' . preg_quote( $key, '/' ) . '">(.*?)<\/section>/s', $html, $m ) ) { return ''; }
	return $m[1];
}

/** The students' inner folds of one group, each from its head to the end of its table. */
function group_cards( $html, $key ) {
	return array_slice( explode( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner', group_rows( $html, $key ) ), 1 );
}

/** One key and value row's value, by the row's column class, as markup. */
function row_value( $card, $slug ) {
	return preg_match( '/wpcpm-roster__row--' . preg_quote( $slug, '/' ) . '"><th[^>]*>.*?<\/th><td class="[^"]*" role="cell">(.*?)<\/td>/s', $card, $m ) ? $m[1] : null;
}

/** Whether an inner fold's head names this student, the break opportunities taken out. */
function card_names( $card, $name ) {
	return false !== strpos( str_replace( '<wbr>', '', substr( $card, 0, (int) strpos( $card, '</summary>' ) ) ), '>' . $name . '<' );
}

/**
 * Render the roster, with the request the reader arrived on.
 *
 * `$_GET` and the URL are set together, because that is how they arrive: the view reads the
 * arguments through `WPCPM_Request` and builds its links from the current URL.
 */
function render( array $get = array(), $record = 'recINSTAAA0000001', array $context = array() ) {
	$_GET             = $get;
	$GLOBALS['uri']   = wpcpm_test_join( '/institution-dashboard/', $get );
	$GLOBALS['decisions'] = array();

	ob_start();
	WPCPM_Institution_Roster_View::render( $record, $context + array( 'can_manage' => false ) );

	return (string) ob_get_clean();
}

/* ---- the fixture -------------------------------------------------------- */

$A = 'recINSTAAA0000001';
$B = 'recINSTBBB0000001';

/**
 * One index row, in the shape the students sync writes.
 *
 * @param array $extra Overrides.
 * @return array
 */
function row( $record_id, $name, $status, $start, array $extra = array() ) {
	return array_merge(
		array(
			'record_id'      => $record_id,
			'name'           => $name,
			'email'          => strtolower( str_replace( ' ', '.', $name ) ) . '@example.com',
			'email_key'      => strtolower( str_replace( ' ', '.', $name ) ) . '@example.com',
			'status'         => $status,
			'institution'    => 'recINSTAAA0000001',
			'start'          => $start,
			'end'            => '',
			'has_mentor'     => false,
			'username'       => '',
			'field_of_study' => '',
			'tutor'          => '',
			// Written by the sync for every row, empty where the reports side had nothing to
			// lend: the roster tells that apart from a logged 0, and both are tested below.
			'hours'          => '',
			'import_key'     => '',
			'reports'        => array(),
			'user_id'        => 0,
		),
		$extra
	);
}

$GLOBALS['index'] = array(
	$A => array(
		'read' => 1788336000,
		'rows' => array(
			// 2026-H1: one of each group, plus the two the index never shows and one
			// `Interested` lead, which is on the roster but is not somebody who signed up.
			'recSTU00000000001' => row( 'recSTU00000000001', 'Ada Example', 'In Sensei', '2026-02-16', array(
				'end' => '2026-09-30', 'has_mentor' => true, 'reports' => array( 'recREP00000000001' ),
				// The WordPress.org username and the account email the roster's avatar is
				// drawn from - the mentor page's own test address, so it is recognizable as
				// a fixture rather than a real person's.
				'username' => 'adaexample', 'email' => 'administrator@example.test',
				'tutor' => 'Tutor Example', 'field_of_study' => 'Technology & Engineering',
				'user_id' => 21,
				// The Students table carries this column; the index drops it and the roster
				// must never print it whatever a caller hands over.
				'accessibility' => 'Screen reader user',
			) ),
			// No username and no account email: the row an avatar cannot be drawn for.
			'recSTU00000000002' => row( 'recSTU00000000002', 'Bo Example', 'In Sensei', '2026-02-16', array( 'has_mentor' => true, 'email' => '' ) ),
			'recSTU00000000003' => row( 'recSTU00000000003', 'Cy Example', 'In Sensei 50h', '2026-03-01' ),
			// Graduated, joined to her report record, mentored, and never given an account: the
			// sync creates none for a past status, so her closed row is what a finished student
			// without an account looks like (1.117.7).
			'recSTU00000000004' => row( 'recSTU00000000004', 'Dee Example', 'Graduate', '2026-01-20', array( 'has_mentor' => true, 'mentor_name' => 'Mo Mentor', 'reports' => array( 'recREP00000000004' ) ) ),
			'recSTU00000000005' => row( 'recSTU00000000005', 'Eve Example', 'Not moving forward', '2026-02-16' ),
			'recSTU00000000006' => row( 'recSTU00000000006', 'Spammy Example', 'SPAM', '2026-02-16' ),
			'recSTU00000000007' => row( 'recSTU00000000007', 'Dup Example', 'Duplicated', '2026-02-16' ),
			'recSTU00000000008' => row( 'recSTU00000000008', 'Fi Example', 'Interested', '2026-02-16' ),
			// An account email and no WordPress.org username: enough for the old fallback to
			// leak a Gravatar, which is exactly what this row is here to catch. Interested keeps
			// her out of the signed-up count the same way Fi is, so the cohort's (5) and the
			// strip's two numbers do not move.
			'recSTU00000000013' => row( 'recSTU00000000013', 'Lu Example', 'Interested', '2026-02-16', array( 'email' => 'administrator@example.test' ) ),
			// 2025-H2, the semester the strip compares 2026-H1 against.
			'recSTU00000000009' => row( 'recSTU00000000009', 'Gus Example', 'Graduate', '2025-09-01' ),
			'recSTU00000000010' => row( 'recSTU00000000010', 'Hal Example', 'In Sensei', '2025-09-01' ),
			// 2025-H1, whose own previous semester holds nobody.
			'recSTU00000000011' => row( 'recSTU00000000011', 'Ida Example', 'Graduate', '2025-03-01' ),
			// No start date at all.
			'recSTU00000000012' => row( 'recSTU00000000012', 'Jo Example', 'In Sensei', '' ),
		),
	),
	$B => array(
		'read' => 0,
		'rows' => array(
			'recSTU00000000020' => row( 'recSTU00000000020', 'Kim Example', 'In Sensei', '2026-02-16' ),
		),
	),
);

// Ada's cached card: the two columns the roster reads out of it, and the disclosure it also
// carries, which is the reason those two are read by name rather than the block printed.
$GLOBALS['umeta'][21] = array(
	'wpcpm_student_program' => array(
		'name'          => 'Ada Example',
		'program'       => 'In Sensei',
		'team'          => 'Documentation Team',
		'website'       => 'https://ada.example.com/',
		'accessibility' => 'Screen reader user',
	),
	'wpcpm_student_mentor'  => array( 'name' => 'Mo Mentor', 'email' => 'mo@example.com' ),
);

$GLOBALS['unlinked'] = array( $A => array() );

echo "=== Before the export module is loaded ===\n";

// `wpcredits-program-manager.php` requires this view before it requires the export module, so
// there is a window in which `WPCPM_Institution_Export` does not exist. A renderer that assumed
// otherwise would take the whole dashboard down with a fatal, where leaving one link out costs
// a school a convenience. These three run first because nothing has declared the stub yet.
$no_module = render();

ck( 'with no export module the control is simply absent', has( $no_module, 'wpcpm-roster__export' ), false );
ck( 'and the roster is drawn anyway rather than fatalling', has( $no_module, 'Ada Example' ), true );
ck( 'and the fence is not asked about an export nothing could perform',
	$GLOBALS['decisions'], array( array( 'view_roster', $A ) ) );

// Declared here, and inside a conditional so PHP cannot early-bind it: an unconditional
// top-level class is available from the first line of the script, which would make the three
// assertions above untestable in a single process.
if ( ! class_exists( 'WPCPM_Institution_Export' ) ) {
	/**
	 * The export module, stubbed to `roster_url()`'s contract and to nothing else.
	 *
	 * The real class is `includes/modules/class-wpcpm-institution-export.php`, which this test
	 * does not load: it reaches Airtable through several classes this file has no stand-ins
	 * for. What is reproduced is exactly what the roster view leans on - a nonced
	 * `admin-post.php` address carrying the cohort and the manager's switcher, naming no
	 * institution at all, because `handle_roster()` resolves that for itself.
	 */
	class WPCPM_Institution_Export {
		const ACTION_ROSTER = 'wpcpm_export_roster';
		const ARG_COHORT    = 'wpcpm_cohort';

		public static function roster_url( $cohort = '' ) {
			$args = array( 'action' => self::ACTION_ROSTER );

			if ( WPCPM_Cohort::is_key( $cohort ) ) { $args[ self::ARG_COHORT ] = (string) $cohort; }

			$view = WPCPM_Request::text( WPCPM_Institution_Roster::ARG_VIEW );

			if ( '' !== $view ) { $args[ WPCPM_Institution_Roster::ARG_VIEW ] = $view; }

			return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION_ROSTER );
		}
	}
}

$html = render();

echo "\n=== The fence, and the shape of the page ===\n";

ck( 'the roster asks the fence before it draws anything', $GLOBALS['decisions'][0], array( 'view_roster', $A ) );
ck( 'a malformed record draws nothing at all', render( array(), 'krakow' ), '' );

$GLOBALS['allowed'] = false;
$refused            = render();
$GLOBALS['allowed'] = true;

ck( 'a refused viewer gets the empty state', has( $refused, 'There are no students to show here yet.' ), true );
ck( 'and no roster table', has( $refused, 'wpcpm-roster__table' ), false );
ck( 'and no student', has( $refused, 'Ada Example' ), false );

echo "\n=== The four groups ===\n";

// Bo has a mentor on his Students row and no report record the sync could join to him, which
// is what a student whose two rows carry different addresses looks like (WPCredits#222). He is
// mentored, so he is Current; the mentor cell is what says his report record is missing.
ck( 'Current holds the student with a report record and the one with only a mentor', group_count( $html, 'Current' ), 2 );
ck( 'Waiting for a mentor holds the student with neither', group_count( $html, 'Waiting for a mentor' ), 1 );
ck( 'Finished holds the graduate', group_count( $html, 'Finished' ), 1 );
ck( 'Not on the program now holds the applicant and both leads', group_count( $html, 'Not on the program now' ), 3 );
ck( 'Ada is in Current', has( group_rows( $html, 'current' ), 'Ada Example' ), true );
ck( 'so is Bo', has( group_rows( $html, 'current' ), 'Bo Example' ), true );
ck( 'Bo is not waiting', has( group_rows( $html, 'waiting' ), 'Bo Example' ), false );
// "Has not reached this page" rather than "has not been created": in the #222 shape the record
// exists and the address join missed it, and telling a school it was never created is untrue.
ck( 'and his row says a mentor is assigned and that the name shows once the progress record reaches the page',
	has( group_rows( $html, 'current' ), 'A mentor is assigned. Their name shows here once the student&#039;s progress record reaches this page.' ), true );
ck( 'in no sentence that names a report record',
	has( group_rows( $html, 'current' ), 'The report record has not reached this page yet.' ), false );
ck( 'the old sentence, which claimed the record was never created, is gone', has( $html, 'has not been created yet' ), false );
ck( 'Cy is waiting with no mentor at all', has( group_rows( $html, 'waiting' ), 'No mentor yet.' ), true );
// A finished student who never had an account (1.117.7): the closed row used to draw its badge
// and its mentor's name from the account alone, so such a row was dates and a portrait and
// nothing else while the card inside named the mentor. A past status is spelled the same in
// both tables, so it is badged off the Students row, and the mentor's name the join lent the
// row is the one the opened card prints.
ck( 'a finished student with no account still wears the tag of their past status',
	has( card_summary( $html, 'finished', 'Dee Example' ), '<span class="wpcpm-status">Graduated</span>' ), true );
ck( 'and the mentor the row already names is in the preview line', has( card_summary( $html, 'finished', 'Dee Example' ), 'Mo Mentor' ), true );
ck( 'but a current student with no account gets no tag off the pipeline status', has( card_summary( $html, 'current', 'Bo Example' ), 'wpcpm-status' ), false );
ck( 'and no mentor name the row does not carry', has( card_summary( $html, 'current', 'Bo Example' ), ' · ' ), false );
// The two notes state the rule the groups are built on, both signals named: a report record
// with no mentor link left on the Students row is still Current, so "with a mentor assigned"
// alone would promise a name the mentor cell then denies.
ck( 'the Current note names both signals, and says students waiting to graduate are in the group (1.123.98: the Overview\'s "on the program now" no longer counts them, so the note does not use those words)', has( $html, 'With a mentor assigned or a progress record already started, students waiting to graduate included.' ), true );
ck( 'and the Waiting note says neither is there', has( $html, 'Signed up, with no mentor assigned and no progress record yet.' ), true );
ck( 'neither note names a report record', has( $html, 'report record' ), false );
// The last group holds whoever is not on the program now: students who have not started yet,
// applicants who never began and students who stopped partway. Its heading and its note say all
// of that, and neither "Did not start" nor "Not continuing" does.
ck( 'the last group is headed Not on the program now', has( $html, '<h3 class="wpcpm-ui-fold__title">Not on the program now</h3>' ), true );
ck( 'its note names the students who have not started, the ones who never began and the ones who stopped',
	has( $html, 'Students who have not started yet, never began the program, or stopped before finishing it.' ), true );
ck( 'and the earlier headings and notes are gone', array(
	has( $html, 'Did not start' ),
	has( $html, '<h3 class="wpcpm-ui-fold__title">Not continuing</h3>' ),
	has( $html, 'Applicants who never began the program' ),
), array( false, false, false ) );
ck( 'the group filter offers the same four headings, in the same order',
	options( $html, 'wpcpm_roster_status' ), array( 'All students', 'Current', 'Waiting for a mentor', 'Finished', 'Not on the program now' ) );
ck( 'while the group keys are the same four', array_keys( WPCPM_Institution_Roster_View::group_labels() ), array( 'current', 'waiting', 'finished', 'not_started' ) );
// Every group is a disclosure, so the same chevron is on every row of the page. The two a
// school works from start open; the two it reads rarely start closed. The group, not its
// students: every student is an inner fold of its own, so the assertions name the group's own tag.
ck( 'all four groups are disclosures', substr_count( $html, '<details class="wpcpm-ui-fold">' ) + substr_count( $html, '<details class="wpcpm-ui-fold" open>' ), 4 );
ck( 'Current starts open', 0 === strpos( group_rows( $html, 'current' ), '<details class="wpcpm-ui-fold" open>' ), true );
ck( 'so does Waiting', 0 === strpos( group_rows( $html, 'waiting' ), '<details class="wpcpm-ui-fold" open>' ), true );
ck( 'Finished starts closed', 0 === strpos( group_rows( $html, 'finished' ), '<details class="wpcpm-ui-fold">' ), true );
ck( 'and so does Not on the program now', 0 === strpos( group_rows( $html, 'not_started' ), '<details class="wpcpm-ui-fold">' ), true );

// ...until it is long. One institution has forty-two students on the program at once, and an
// open list of forty-two buries the groups, the people and the agreement under it. Past
// OPEN_MAX the group starts closed with its count on the row, which is the number a school is
// usually after anyway.
$many = array();

for ( $i = 0; $i <= WPCPM_Institution_Roster_View::OPEN_MAX; $i++ ) {
	$many[ 'recBULKSTUDENT' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ) ] = array(
		'record_id'   => 'recBULKSTUDENT' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ),
		'name'        => 'Student ' . $i,
		'email'       => 'student' . $i . '@example.edu',
		'status'      => 'In Sensei',
		'institution' => 'recINSTAAA0000001',
		'start'       => '2026-02-01',
		'end'         => '2026-06-30',
		'user_id'     => 900 + $i,
		'reports'     => array( 'recREPORT' . str_pad( (string) $i, 6, '0', STR_PAD_LEFT ) ),
	);
}

// Kept and put back: everything below this block reads the fixture's own cohorts.
$seeded = $GLOBALS['index']['recINSTAAA0000001'];

$GLOBALS['index']['recINSTAAA0000001'] = array(
	'read' => 1756000000,
	'rows' => $many,
);

$long = render();

// Named for the Current group, not "somewhere on the page": Finished and Not on the program now are
// disclosures whatever their length, so counting them would pass with the ceiling deleted.
ck( 'a group longer than the ceiling starts closed', 0 === strpos( group_rows( $long, 'current' ), '<details class="wpcpm-ui-fold">' ), true );
ck( 'and not open', has( group_rows( $long, 'current' ), '<details class="wpcpm-ui-fold" open>' ), false );
ck( 'and says how many are inside it without being opened', group_count( $long, 'Current' ), WPCPM_Institution_Roster_View::OPEN_MAX + 1 );
ck( 'the students are still there, one card each, for search and for the browser to find',
    count( group_cards( $long, 'current' ) ), WPCPM_Institution_Roster_View::OPEN_MAX + 1 );

$GLOBALS['index']['recINSTAAA0000001'] = $seeded;
ck( 'though each of its students is a card that opens', count( group_cards( $html, 'current' ) ), 2 );
// The roster used to scroll sideways inside its own box, which is the thing this replaced.
ck( 'and nothing on the page scrolls sideways any more', has( $html, 'wpcpm-roster__scroll' ), false );

echo "\n=== What is never rendered ===\n";

ck( 'the SPAM row is absent', has( $html, 'Spammy Example' ), false );
ck( 'so is the Duplicated one', has( $html, 'Dup Example' ), false );
ck( 'the accessibility disclosure appears nowhere', has( $html, 'Screen reader user' ), false );
ck( 'nor does the column exist', has( $html, 'Accessibility' ), false );
ck( 'the columns are the ones the spec lists', array_values( WPCPM_Institution_Roster_View::columns() ), array(
	'Student', 'Program', 'Dates', 'Days left', 'Mentor', 'WordPress.org', 'Team', 'Website', 'Field of study', 'Tutor', 'Hours',
) );
// The key names the table and the column the value is read from, the way the fence's `scope()`
// reads them, and `Hours` is a Students Reports column. A key naming the Students table would
// scope this column against a table that does not hold it.
ck( 'and hours are keyed to the table the value comes from',
	array_key_exists( 'reports|Hours', WPCPM_Institution_Roster_View::columns() ), true );
ck( 'and none of them names accessibility',
	preg_grep( '/accessib/i', array_keys( WPCPM_Institution_Roster_View::columns() ) ), array() );

echo "\n=== One student's row ===\n";

$ada = group_rows( $html, 'current' );

// The avatar sits before the name, the same 44px photo the mentor page draws (consistency pass, 1.94.7).
ck( 'a student row carries the same 44px avatar as the mentor page, before the name',
	preg_match( '#<summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__portrait"><img class="wpcpm-avatar" src="[^"]*adaexample[^"]*" srcset="[^"]*" width="44" height="44" alt="" title="[^"]*" loading="lazy" decoding="async" /></span><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title"><a [^>]*>Ada Example#', $html ) === 1, true );
ck( 'a row with neither a username nor an account email prints no avatar, the name\'s first letter in its place',
	preg_match( '#<summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__portrait"><span class="wpcpm-ui-fold__initials" aria-hidden="true">B</span></span><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Bo Example#', $html ) === 1, true );
// Lu has an account email and no username: the case the old fallback used to draw a Gravatar
// for, keyed on the address the Institution Dashboard otherwise never shows a member.
ck( 'a row with an account email but no username prints no avatar either',
	preg_match( '#<summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__portrait"><span class="wpcpm-ui-fold__initials" aria-hidden="true">L</span></span><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Lu Example#', $html ) === 1, true );
ck( 'so nothing on the page is a Gravatar URL a member could read as a lookup key',
	has( $html, 'gravatar' ), false );

ck( 'the program is the name people use, with its tag',
	has( $ada, '<span class="wpcpm-status wpcpm-status--green">WordPress Credits Program 150h</span>' ), true );
ck( 'the dates are the mentor card formatter\'s words, each date whole', has( $ada, '<span class="wpcpm-ui-whole">2026-02-16</span> to <span class="wpcpm-ui-whole">2026-09-30</span>' ), true );
ck( 'the days left are counted from today', has( $ada, '28 days left' ), true );
ck( 'the mentor is named from the cached card', has( $ada, 'Mo Mentor' ), true );
ck( 'and the mentor address is not printed here', has( $ada, 'mo@example.com' ), false );
ck( 'the WordPress.org profile links to the profile', has( $ada, 'https://profiles.wordpress.org/adaexample/' ), true );
ck( 'the team comes from the cached card', has( $ada, 'Documentation Team' ), true );
ck( 'the website is a link with the scheme trimmed off the label', has( $ada, '>ada.example.com</a>' ), true );
ck( 'the tutor is printed', has( $ada, 'Tutor Example' ), true );
ck( 'a row with an account links to the detail view',
	has( $ada, 'wpcpm_institution_student=21' ), true );
ck( 'a row without one does not', has( group_rows( $html, 'waiting' ), 'wpcpm_institution_student' ), false );
ck( 'an empty cell says so rather than going blank', has( group_rows( $html, 'waiting' ), 'Not recorded' ), true );

echo "\n=== A status is printed in a school's words and read in the base's ===\n";

/**
 * One student's Program cell, as the reader sees it: the value of that card's Program row.
 *
 * @param string $html  The rendered page.
 * @param string $group Which group the card is in.
 * @param string $name  The student's name.
 * @return string|null The cell's text, or null when the card has no Program row.
 */
function program_text( $html, $group, $name ) {
	foreach ( group_cards( $html, $group ) as $card ) {
		if ( ! card_names( $card, $name ) ) { continue; }
		if ( null !== row_value( $card, 'students-status' ) ) {
			return trim( html_entity_decode( wp_strip_all_tags( row_value( $card, 'students-status' ) ), ENT_QUOTES, 'UTF-8' ) );
		}
	}

	return null;
}

// One student for each word the Students table's own status list gives a school to read, all but
// the last without an account, so each Program cell is the pipeline status and nothing else. The
// last has an account whose Students Reports status says Graduate: the cell and the closed row's
// badge both print that status, and a school must read the same word as for the others. Swapped
// in for the fixture and put back, as the long list above is: everything after reads the fixture.
$kept = $GLOBALS['index'][ $A ];
$GLOBALS['umeta'][41] = array(
	'wpcpm_student_program' => array( 'name' => 'Kay Example', 'program' => 'Graduate', 'team' => 'Training' ),
);
$GLOBALS['index'][ $A ] = array(
	'read' => 1788336000,
	'rows' => array(
		'recSTW00000000001' => row( 'recSTW00000000001', 'Ann Example', 'Interested', '2026-02-16' ),
		'recSTW00000000002' => row( 'recSTW00000000002', 'Ben Example', 'In Sensei Self-onboarding', '2026-02-16' ),
		'recSTW00000000003' => row( 'recSTW00000000003', 'Cat Example', 'Not moving forward', '2026-02-16' ),
		'recSTW00000000004' => row( 'recSTW00000000004', 'Dan Example', 'Fail', '2026-02-16' ),
		'recSTW00000000005' => row( 'recSTW00000000005', 'Eli Example', 'Graduate', '2026-02-16' ),
		'recSTW00000000006' => row( 'recSTW00000000006', 'Fay Example', 'In Sensei', '2026-02-16', array( 'has_mentor' => true ) ),
		'recSTW00000000007' => row( 'recSTW00000000007', 'Gil Example', 'In Sensei 50h', '2026-02-16' ),
		'recSTW00000000008' => row( 'recSTW00000000008', 'Hui Example', 'Developer Track', '2026-02-16', array( 'has_mentor' => true ) ),
		'recSTW00000000009' => row( 'recSTW00000000009', 'Ivy Example', 'Dropped out', '2026-02-16' ),
		'recSTW00000000010' => row( 'recSTW00000000010', 'Jax Example', 'Paused', '2026-02-16' ),
		'recSTW00000000011' => row( 'recSTW00000000011', 'Kay Example', 'Graduate', '2026-02-16', array( 'user_id' => 41 ) ),
	),
);

$words = render();

// Interested is a lead, and "Signed up" would contradict the strip's count of the students who
// did, so it is printed as it is: the one pipeline word with no plain version.
ck( 'Interested is printed as it is', program_text( $words, 'not_started', 'Ann Example' ), 'Interested' );
ck( 'In Sensei Self-onboarding is Getting started', program_text( $words, 'not_started', 'Ben Example' ), 'Getting started' );
ck( 'Not moving forward is Not continuing, since a student may have started before stopping', program_text( $words, 'not_started', 'Cat Example' ), 'Not continuing' );
ck( 'Fail is Did not complete', program_text( $words, 'not_started', 'Dan Example' ), 'Did not complete' );
ck( 'Graduate is Graduated', program_text( $words, 'finished', 'Eli Example' ), 'Graduated' );

// **One word for a student, whichever table says it.** An account-holding graduate's Program cell
// is drawn from their Students Reports status, and the closed row's badge from the same, and both
// went through the label that passes `Graduate` untouched while the cell of a student without an
// account said Graduated.
ck( 'an account-holding graduate\'s Program cell says Graduated too', program_text( $words, 'finished', 'Kay Example' ), 'Graduated' );
ck( 'and so does their closed row\'s tag, neutral as every state is', has( card_summary( $words, 'finished', 'Kay Example' ), '<span class="wpcpm-status">Graduated</span>' ), true );
ck( 'as does the tag of one without an account', has( card_summary( $words, 'finished', 'Eli Example' ), '<span class="wpcpm-status">Graduated</span>' ), true );
ck( 'a status with no plain word keeps its badge\'s words', has( card_summary( $words, 'finished', 'Ivy Example' ), '>Dropped out</span>' ), true );
ck( 'and nothing on the page says Graduate on its own', has( $words, '>Graduate<' ), false );
ck( 'a status that names a compiled track prints the track\'s name', array(
	program_text( $words, 'current', 'Fay Example' ),
	program_text( $words, 'waiting', 'Gil Example' ),
	program_text( $words, 'current', 'Hui Example' ),
), array( WPCPM_Program::label( 'In Sensei' ), WPCPM_Program::label( 'In Sensei 50h' ), WPCPM_Program::label( 'Developer Track' ) ) );
ck( 'which is the program\'s name and not the status', array(
	program_text( $words, 'current', 'Fay Example' ),
	program_text( $words, 'waiting', 'Gil Example' ),
), array( 'WordPress Credits Program 150h', 'WordPress Credits Program 50h' ) );
ck( 'any other status is printed as it is', array(
	program_text( $words, 'finished', 'Ivy Example' ),
	program_text( $words, 'waiting', 'Jax Example' ),
), array( 'Dropped out', 'Paused' ) );
ck( 'and none of the raw words is left on the page for a school to read', array(
	has( $words, 'Self-onboarding' ), has( $words, 'Not moving forward' ), has( $words, '>Fail<' ),
	has( $words, '>In Sensei<' ), has( $words, '>In Sensei 50h<' ),
), array( false, false, false, false, false ) );

// **Only the printed words changed.** The groups are built from the base's own status, so each of
// these students is filed where their raw status files them: Interested, Not moving forward and
// Fail with the applicants who never began, the way the list always did, whatever the cell says.
ck( 'the group a row is filed in is the raw status\'s: Current', group_count( $words, 'Current' ), 2 );
ck( 'Waiting for a mentor', group_count( $words, 'Waiting for a mentor' ), 2 );
ck( 'Finished, which holds both graduates and the one who dropped out', group_count( $words, 'Finished' ), 3 );
ck( 'and Not on the program now, which holds Interested, Self-onboarding, Not moving forward and Fail', group_count( $words, 'Not on the program now' ), 4 );
ck( 'each of them in it by name', array(
	has( group_rows( $words, 'not_started' ), 'Ann Example' ),
	has( group_rows( $words, 'not_started' ), 'Ben Example' ),
	has( group_rows( $words, 'not_started' ), 'Cat Example' ),
	has( group_rows( $words, 'not_started' ), 'Dan Example' ),
), array( true, true, true, true ) );

// The counts read the base's words too. Ann is a lead and is not counted among the ten who signed
// up, whatever word the cell prints for her.
$cohort_counts = WPCPM_Cohort::participation( WPCPM_Roster_Index::rows( $A ), '2026-H1' );

ck( 'the strip counts who signed up by the raw status, and not Ann', has( $words, '10 students signed up.' ), true );
ck( 'and who graduated, the account-holding graduate with the other', has( $words, '2 have graduated.' ), true );
ck( 'from the same figures the cohort counts hold', array( $cohort_counts['signed_up'], $cohort_counts['graduated'], $cohort_counts['not_started'], $cohort_counts['withdrawn'] ), array( 10, 2, 1, 2 ) );
ck( 'and the picker counts them as it did', options( $words, 'wpcpm_cohort' ), array( 'January to June 2026 (10)' ) );

// The search reads a row's own fields and never its status, so the plain words are no more
// searchable than the base's were: a school typing what it reads finds nobody, and a name still
// finds its student, in the group the raw status files them in.
$by_word = render( array( 'wpcpm_roster_search' => 'Not continuing' ) );
ck( 'searching for a plain word finds nobody, as searching for the raw one did', array(
	group_count( $by_word, 'Current' ), group_count( $by_word, 'Waiting for a mentor' ), group_count( $by_word, 'Finished' ), group_count( $by_word, 'Not on the program now' ),
), array( 0, 0, 0, 0 ) );
$by_raw = render( array( 'wpcpm_roster_search' => 'Not moving forward' ) );
ck( 'and so does the raw word', array(
	group_count( $by_raw, 'Current' ), group_count( $by_raw, 'Waiting for a mentor' ), group_count( $by_raw, 'Finished' ), group_count( $by_raw, 'Not on the program now' ),
), array( 0, 0, 0, 0 ) );
$by_name = render( array( 'wpcpm_roster_search' => 'Cat Example' ) );
ck( 'a name still finds its student, filed by the raw status', array(
	group_count( $by_name, 'Not on the program now' ),
	has( group_rows( $by_name, 'not_started' ), 'Cat Example' ),
	program_text( $by_name, 'not_started', 'Cat Example' ),
), array( 1, true, 'Not continuing' ) );

// The group filter is a request for a group, and the group is the raw status's.
$only = render( array( 'wpcpm_roster_status' => 'not_started' ) );
ck( 'the group filter narrows to the same four', group_count( $only, 'Not on the program now' ), 4 );

$GLOBALS['index'][ $A ] = $kept;
ck( 'the fixture is back', group_count( render(), 'Current' ), 2 );

echo "\n=== Hours ===\n";

/**
 * The Hours cell of the first card in one group, as the reader sees it.
 *
 * Read out of the card by the column's own class rather than by position, so a column added
 * beside it does not silently move these assertions onto somebody else's value.
 *
 * @param string $html  The rendered page.
 * @param string $group Which group's first card to read.
 * @return string|null The cell's text, or null when the card has no hours row at all.
 */
/** One student's closed row: the summary of the card that names them, or ''. */
function card_summary( $html, $group, $name ) {
	foreach ( group_cards( $html, $group ) as $card ) {
		if ( card_names( $card, $name ) ) {
			return substr( $card, 0, (int) strpos( $card, '</summary>' ) );
		}
	}
	return '';
}

function hours_of( $html, $group = 'current', $name = '' ) {
	$rows = group_rows( $html, $group );

	// One student's card, when asked for by name, rather than whichever card comes first.
	if ( '' !== $name ) {
		$rows = '';
		foreach ( group_cards( $html, $group ) as $card ) {
			if ( card_names( $card, $name ) ) { $rows = $card; }
		}
	}

	if ( null === row_value( $rows, 'reports-hours' ) ) {
		return null;
	}

	return trim( html_entity_decode( wp_strip_all_tags( row_value( $rows, 'reports-hours' ) ), ENT_QUOTES, 'UTF-8' ) );
}

/**
 * Ada's cached program block, rewritten from scratch.
 *
 * From scratch rather than merged, because half of what is being tested is what happens when a
 * key is not there at all.
 *
 * @param array $changes Keys to set on top of the block the fixture seeds.
 */
function ada_program( array $changes = array() ) {
	$GLOBALS['umeta'][21]['wpcpm_student_program'] = array_merge(
		array(
			'name'          => 'Ada Example',
			'program'       => 'In Sensei',
			'team'          => 'Documentation Team',
			'website'       => 'https://ada.example.com/',
			'accessibility' => 'Screen reader user',
		),
		$changes
	);
}

// **The header exists and the cell is empty**, which is the state every roster is in the day
// before the sync first reads the column. It has to read as a gap in the records, the way every
// other empty cell on this card does, and not as a zero: "0 of 150" about a student nobody has
// logged for is the page inventing a fact about them.
ck( 'a student with no hours anywhere reads as a gap', hours_of( render() ), 'Not recorded' );
ck( 'and the heading is printed even so', has( group_rows( render(), 'current' ), '>Hours</th>' ), true );

// The index, for the students who have never signed in here - which is most of a school's
// roster. Bo has no account at all, so the cached block cannot be the source, and the track the
// target comes from has to fall back to the Students row's own status.
$GLOBALS['index'][ $A ]['rows']['recSTU00000000002']['hours'] = '12';

ck( 'a student with no account gets their hours off the index', hours_of( render(), 'current', 'Bo Example' ), '12 of 150' );
ck( 'and they are his, not the first card\'s', hours_of( render(), 'current', 'Ada Example' ), 'Not recorded' );

// The 50-hour track is a different denominator, from the same map, and the value is fractional:
// 6.2 is a real count on the live base, and an intval() anywhere would print 6.
$GLOBALS['index'][ $A ]['rows']['recSTU00000000003']['hours'] = '6.2';

ck( 'the 50-hour track prints its own target, and keeps the fraction',
	has( group_rows( render(), 'waiting' ), '6.2 of 50' ), true );

// **The account first, the index second**, the same order the mentor, the team and the website
// are read in: the cached block is written by the same sync and is the fresher of the two when
// a student saves their own report between runs.
$GLOBALS['index'][ $A ]['rows']['recSTU00000000001']['hours'] = '5';
ada_program( array( 'hours' => '12' ) );

ck( 'the account\'s copy is preferred over the index\'s', hours_of( render() ), '12 of 150' );

// **And "0" is a value, not a reason to fall through.** `! $cached( ... )` here would reach past
// a count a student has just cleared and print the index's older, larger one: the school would
// be shown hours the student no longer claims.
ada_program( array( 'hours' => '0' ) );

ck( 'a cleared count wins over the index rather than falling through it', hours_of( render() ), '0 of 150' );
// The other half of the same rule, side by side: nobody has logged for one of them and the
// other has logged nothing, and the two cells say different things.
ck( 'which is not what an unlogged student reads', hours_of( render(), 'waiting' ) === hours_of( render() ), false );

// Fractions again, on the value the account carries, and the exact target with no decimal
// point: a fixed number of places would print either "135" or "150.0", and both are wrong.
ada_program( array( 'hours' => '135.5' ) );
ck( 'a fractional count keeps its fraction', hours_of( render() ), '135.5 of 150' );

ada_program( array( 'hours' => '150' ) );
ck( 'and a whole one is printed whole', hours_of( render() ), '150 of 150' );

// The places come off the digits the base sent rather than off a fixed width, so each of these
// is written the way it was recorded. `number_format_i18n()` pads, which is why a fixed one or
// two places would print "150.00 of 150" for the student above.
ada_program( array( 'hours' => '6.25' ) );
ck( 'two places are kept', hours_of( render() ), '6.25 of 150' );

ada_program( array( 'hours' => '6.257' ) );
ck( 'and three are rounded to the two this column is written to', hours_of( render() ), '6.26 of 150' );

ada_program( array( 'hours' => '6.20' ) );
ck( 'a trailing zero is not a place', hours_of( render() ), '6.2 of 150' );

// **Past the target, printed as it stands.** Students on the live base have logged 400 against
// a 150-hour track. Clamping to the target would hide an overrun from the only people who are
// counting, and a percentage drawn from this must not assume it stays under 100 either.
ada_program( array( 'hours' => '400' ) );
ck( 'a count past the target is neither clamped nor doubted', hours_of( render() ), '400 of 150' );

// **The Developer Track has no target and prints no denominator** (design decision 23). Its 0
// in the map is the answer rather than a gap, so the cell has to say the hours on their own.
ada_program( array( 'program' => 'Developer Track', 'hours' => '12' ) );

ck( 'the Developer Track prints the hours alone', hours_of( render() ), '12 h' );
ck( 'and never a denominator of nothing', has( group_rows( render(), 'current' ), 'of 0' ), false );

// A track the hours map has never heard of answers the same way, and that is a supported state
// rather than an omission: `hours_targets()`'s docblock says so in as many words, because a
// track the program adds and does not count hours for must not need a row there first.
ada_program( array( 'program' => 'Research Track', 'hours' => '12' ) );
ck( 'a track absent from the hours map prints the hours alone too', hours_of( render() ), '12 h' );

// A student whose track is behind them keeps their hours and loses the denominator, because
// `hours_target()` answers 0 for a finished status: "150 of 150" would be this page deciding
// which track a graduate was on, which it has no way of knowing.
ada_program( array( 'program' => 'Graduate', 'hours' => '150' ) );
ck( 'a finished student\'s hours print without a target', hours_of( render() ), '150 h' );

// A Number column that stopped being a number: printed as it stands. `(float) 'n/a'` is 0.0, so
// a cast here would tell a school its student had done nothing on the strength of somebody
// changing a field type in the base.
ada_program( array( 'hours' => 'n/a' ) );
ck( 'a value that is not a number is shown as it is', hours_of( render() ), 'n/a' );
ck( 'rather than counted as none of the target', has( group_rows( render(), 'current' ), '0 of 150' ), false );

// **And escaped, which nothing pinned until a reviewer removed `esc_html()` twice and watched
// 283 checks stay green.** The value is a Number column in Airtable, so today it cannot hold
// markup; what it can hold is whatever a field type change or a formula makes of it, and this
// cell is the one place on the roster that prints a base value without a shape of its own.
ada_program( array( 'hours' => '<b>12</b>' ) );
ck( 'a value carrying markup is escaped, not rendered', has( render(), '<b>12</b>' ), false );
ck( 'and reaches the page as text', has( render(), '&lt;b&gt;12&lt;/b&gt;' ), true );

// The same for the branch that formats rather than passing through, which was the second of
// the two unescaped returns: a value that survives `is_numeric()` cannot carry markup, so the
// proof there is that the two branches escape through the same call rather than one of them
// having been left out.
$view = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php' );
$start = strpos( $view, 'private static function hours_cell' );
$body  = substr( $view, $start, strpos( $view, "\n\t}", $start ) - $start );

// One `return '';` for the empty case needs no escaping; every other return does.
ck( 'every return in the hours cell that carries a value escapes it', substr_count( $body, 'return ' ) - 1, substr_count( $body, 'esc_html' ) );

// Back to the fixture: everything below reads Ada's own row.
ada_program();
unset( $GLOBALS['index'][ $A ]['rows']['recSTU00000000001']['hours'], $GLOBALS['index'][ $A ]['rows']['recSTU00000000002']['hours'], $GLOBALS['index'][ $A ]['rows']['recSTU00000000003']['hours'] );

echo "\n=== The cohort picker ===\n";

ck( 'the fixture cohorts, newest first, with No start date last', options( $html, 'wpcpm_cohort' ), array(
	'January to June 2026 (5)',
	'July to December 2025 (2)',
	'January to June 2025 (1)',
	'No start date (1)',
) );
ck( 'today is 2026-H2, which this institution has nobody in', WPCPM_Cohort::current(), '2026-H2' );
ck( 'so the picker opens on the newest cohort it does have', chosen( $html, 'wpcpm_cohort' ), '2026-H1' );

// The chosen cohort is an option even when nobody signed up in it, and where it goes in the
// list is the assertion: it is sorted in with the rest rather than pushed on the end, so
// design spec 7.7's order survives a cohort the counts never saw. Asking for this semester
// is how a reader reaches one, and it is also what the revoke-and-return case looks like.
$empty_now = render( array( 'wpcpm_cohort' => '2026-H2' ) );

ck( 'a cohort the institution has nobody in is still an option', chosen( $empty_now, 'wpcpm_cohort' ), '2026-H2' );
ck( 'and it is sorted in, newest first, not appended after No start date',
	options( $empty_now, 'wpcpm_cohort' ), array(
		'July to December 2026 (0)',
		'January to June 2026 (5)',
		'July to December 2025 (2)',
		'January to June 2025 (1)',
		'No start date (1)',
	) );

$empty_old = render( array( 'wpcpm_cohort' => '2024-H1' ) );

ck( 'an empty cohort older than every other one sorts last of the semesters, ahead of No start date',
	options( $empty_old, 'wpcpm_cohort' ), array(
		'January to June 2026 (5)',
		'July to December 2025 (2)',
		'January to June 2025 (1)',
		'January to June 2024 (0)',
		'No start date (1)',
	) );

$GLOBALS['index'][ $B ]['rows']['recSTU00000000021'] = row( 'recSTU00000000021', 'Lee Example', 'SPAM', '' );
$b_html = render( array(), $B );

ck( 'a cohort holding nobody who signed up is not an option',
	options( $b_html, 'wpcpm_cohort' ), array( 'January to June 2026 (1)' ) );
ck( 'so No start date is absent when the only row in it is one nobody counts',
	has( $b_html, 'No start date' ), false );
ck( 'and that institution has never been read, which its footer says',
	has( $b_html, 'These students have not been read from the program records yet.' ), true );

echo "\n=== The comparison strip ===\n";

ck( 'the chosen semester is named', has( $html, '<section class="wpcpm-roster__summary wpcpm-ui-block"><h3 class="wpcpm-ui-h">January to June 2026</h3>' ), true );
ck( 'its two numbers: signed up', has( $html, '5 students signed up.' ), true );
ck( 'its two numbers: graduated', has( $html, '1 has graduated.' ), true );
ck( 'the lead is not counted as somebody who signed up',
	WPCPM_Cohort::participation( WPCPM_Roster_Index::rows( $A ), '2026-H1' )['signed_up'], 5 );
ck( 'though the roster still shows her, under Not on the program now',
	has( group_rows( $html, 'not_started' ), 'Fi Example' ), true );
ck( 'the previous semester is compared, with its own two numbers',
	has( $html, 'Compared with July to December 2025: 2 signed up, 1 graduated.' ), true );

$older = render( array( 'wpcpm_cohort' => '2025-H1' ) );

ck( 'a cohort asked for in the URL is the one drawn', chosen( $older, 'wpcpm_cohort' ), '2025-H1' );
ck( 'an empty previous semester says so instead of printing zeros',
	has( $older, 'No students started in July to December 2024.' ), true );
ck( 'and never as a pair of zeros', has( $older, '0 signed up, 0 graduated' ), false );

$none = render( array( 'wpcpm_cohort' => 'none' ) );

ck( 'the No start date bucket is a cohort like any other', chosen( $none, 'wpcpm_cohort' ), 'none' );
ck( 'it holds the student with no date', has( group_rows( $none, 'waiting' ), 'Jo Example' ), true );
ck( 'whose dates cell names the gap', has( group_rows( $none, 'waiting' ), 'No start date</span>' ), true );
ck( 'and it has no semester before it to compare with',
	has( $none, 'there is no earlier semester to compare them with.' ), true );

echo "\n=== The read time ===\n";

ck( 'the footer prints when the counts were read, in the site\'s date and time formats',
	has( $html, '<p class="wpcpm-roster__read wpcpm-roster__read--footer">Read from the program records on September 2, 2026 8:00 am (2 hours ago).</p>' ), true );
ck( 'and the strip does not print it again: the roster holds the line once',
	array( has( $html, 'wpcpm-roster__read--strip' ), substr_count( $html, 'Read from the program records on' ) ), array( false, 1 ) );
ck( 'nor does the strip of a roster that was never read',
	array( has( $b_html, 'wpcpm-roster__read--strip' ), substr_count( $b_html, 'have not been read from the program records yet' ) ), array( false, 1 ) );
ck( 'not as year-month-day and a clock', has( $html, '2026-09-02 08:00' ), false );
ck( 'a read time the caller hands over is the one printed',
	has( render( array(), $A, array( 'read' => 1787500000 ) ), 'August 23, 2026 3:46 pm' ), true );

// Inside a module the dashboard titles, the page prints the line once at its foot, so the
// roster prints none: neither the dated sentence nor the one for a roster never read.
$titled   = render( array(), $A, array( 'module_titled' => true ) );
$titled_b = render( array(), $B, array( 'module_titled' => true ) );
ck( 'in a titled module the roster prints no read line at all',
	array( has( $titled, 'Read from the program records' ), has( $titled, 'wpcpm-roster__read' ) ), array( false, false ) );
ck( 'and none for a roster never read either',
	array( has( $titled_b, 'have not been read from the program records yet' ), has( $titled_b, 'wpcpm-roster__read' ) ), array( false, false ) );
ck( 'while the strip\'s counts are all still there',
	array( has( $titled, '5 students signed up.' ), has( $titled, '1 has graduated.' ) ), array( true, true ) );
ck( 'and the standalone roster\'s footer sits after its last group',
	strrpos( $html, 'wpcpm-roster__read--footer' ) > strrpos( $html, 'wpcpm-roster__group' ), true );

echo "\n=== Enrollment not recorded yet ===\n";

ck( 'the fifth list is absent when there is nobody in it', has( $html, 'Enrollment not recorded yet' ), false );

$GLOBALS['unlinked'][ $A ] = array(
	31 => row( '', 'Nia Example', 'In Sensei', '2026-02-16', array( 'user_id' => 31, 'record_id' => '' ) ),
);
$with_fifth = render();

ck( 'it appears when there is somebody in it', group_count( $with_fifth, 'Enrollment not recorded yet' ), 1 );
ck( 'with the person named', has( $with_fifth, 'Nia Example' ), true );
ck( 'and the explanation of whose job the missing record is',
	has( $with_fifth, 'These students are reporting their progress, but the program records hold no enrollment for them yet, so they are not counted in the groups above. An Administrator needs to complete the record.' ), true );
ck( 'in no word of the base\'s: no table, no row, no report record', array(
	has( $with_fifth, 'Students table' ),
	has( $with_fifth, 'enrollment row' ),
	has( $with_fifth, 'report record' ),
	has( $with_fifth, 'Airtable' ),
), array( false, false, false, false ) );
ck( 'they are not in any of the four groups', group_count( $with_fifth, 'Current' ), 2 );

// A student's address is a manager's to see, not the school's: the export has no email column
// and the student card prints the mentor's address as the only one. This list printed one per
// account-bearing student to the institution, and the search box matched on it.
ck( 'a member is not shown the address', has( $with_fifth, '@example' ), false );
$as_manager = render( array(), 'recINSTAAA0000001', array( 'can_manage' => true ) );
ck( 'a manager is', has( $as_manager, 'nia.example@example' ), true );
$by_address = render( array( 'wpcpm_roster_search' => 'nia.example@' ) );
ck( 'a member searching by address finds nobody', has( $by_address, 'Nia Example' ), false );
$by_address = render( array( 'wpcpm_roster_search' => 'nia.example@' ), 'recINSTAAA0000001', array( 'can_manage' => true ) );
ck( 'while a manager searching by address does', has( $by_address, 'Nia Example' ), true );
ck( 'and a member searching by name still finds her', has( render( array( 'wpcpm_roster_search' => 'Nia' ) ), 'Nia Example' ), true );

// The search box's hint says what the search reads, for the reader it reads for (1.123.26). It
// promised "email" to an institution's member, whose search never reads an address, so a member
// typing one found nobody and was told nothing. Each hint is written from the same list the search
// reads, so the two cannot part again.
ck( 'a member\'s search box lists what a member\'s search reads, with no email in it', has( render( array() ), '<p class="wpcpm-ui-field__hint" id="wpcpm-roster-search-hint">Name, WordPress.org username, tutor, field of study</p>' ), true );
ck( 'and a manager\'s lists the address too, which theirs reads', has( $as_manager, '<p class="wpcpm-ui-field__hint" id="wpcpm-roster-search-hint">Name, email, WordPress.org username, tutor, field of study, Students record ID</p>' ), true );
// An Administrator puts a name to the Students record ID Claude gave (1.123.48). A student with no
// account has no row on the Student accounts list, so the roster of the institution is where the ID
// is searched: exactly, as the base spells it, and for a manager only.
$by_record = render( array( 'wpcpm_roster_search' => 'recSTU00000000003' ), $A, array( 'can_manage' => true ) );
ck( 'a manager searching a Students record ID finds that row only', array(
	has( $by_record, 'Cy Example' ),
	has( $by_record, 'Ada Example' ),
	group_count( $by_record, 'Current' ),
	group_count( $by_record, 'Waiting for a mentor' ),
), array( true, false, 0, 1 ) );
ck( 'the ID is read exactly: a part of it finds nobody, and nor does another letter case', array(
	group_count( render( array( 'wpcpm_roster_search' => 'recSTU0000000000' ), $A, array( 'can_manage' => true ) ), 'Waiting for a mentor' ),
	group_count( render( array( 'wpcpm_roster_search' => 'recstu00000000003' ), $A, array( 'can_manage' => true ) ), 'Waiting for a mentor' ),
), array( 0, 0 ) );
ck( 'and the space around it is not part of it', has( render( array( 'wpcpm_roster_search' => '  recSTU00000000003 ' ), $A, array( 'can_manage' => true ) ), 'Cy Example' ), true );
ck( 'the roster of the cohort the row is in finds it, and another cohort\'s does not', array(
	has( render( array( 'wpcpm_cohort' => '2025-H2', 'wpcpm_roster_search' => 'recSTU00000000003' ), $A, array( 'can_manage' => true ) ), 'Cy Example' ),
	has( render( array( 'wpcpm_cohort' => '2025-H2', 'wpcpm_roster_search' => 'recSTU00000000010' ), $A, array( 'can_manage' => true ) ), 'Hal Example' ),
), array( false, true ) );
ck( 'a record ID the roster holds as Duplicated or SPAM finds nobody, as the abilities list no such track', array(
	has( render( array( 'wpcpm_roster_search' => 'recSTU00000000006' ), $A, array( 'can_manage' => true ) ), 'Spammy Example' ),
	has( render( array( 'wpcpm_roster_search' => 'recSTU00000000007' ), $A, array( 'can_manage' => true ) ), 'Dup Example' ),
), array( false, false ) );
$member_by_record = render( array( 'wpcpm_roster_search' => 'recSTU00000000003' ) );
ck( 'an institution\'s member searching the same ID finds nobody: it is not one of the fields theirs reads', array(
	has( $member_by_record, 'Cy Example' ),
	group_count( $member_by_record, 'Waiting for a mentor' ),
), array( false, 0 ) );
ck( 'the manager\'s box names it in its hint, and the member\'s does not', array(
	has( $as_manager, '<p class="wpcpm-ui-field__hint" id="wpcpm-roster-search-hint">Name, email, WordPress.org username, tutor, field of study, Students record ID</p>' ),
	has( render( array() ), 'Students record ID' ),
), array( true, false ) );
ck( 'a name search is as it was for both readers', array(
	has( render( array( 'wpcpm_roster_search' => 'Cy Example' ), $A, array( 'can_manage' => true ) ), 'Cy Example' ),
	has( render( array( 'wpcpm_roster_search' => 'Cy Example' ) ), 'Cy Example' ),
	group_count( render( array( 'wpcpm_roster_search' => 'Example' ), $A, array( 'can_manage' => true ) ), 'Waiting for a mentor' ),
), array( true, true, 1 ) );
ck( 'the WordPress.org username and the field of study the hints name are read by the search', array(
	group_count( render( array( 'wpcpm_roster_search' => 'adaexample' ) ), 'Current' ),
	group_count( render( array( 'wpcpm_roster_search' => 'Engineering' ) ), 'Current' ),
), array( 1, 1 ) );

echo "\n=== The filter bar ===\n";

$waiting = render( array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_status' => 'waiting' ) );

ck( 'a group filter narrows to that group', group_count( $waiting, 'Waiting for a mentor' ), 1 );
ck( 'and the other three are not drawn', group_count( $waiting, 'Current' ), null );
ck( 'the fifth list stays out of a request for one of the four',
	has( $waiting, 'Enrollment not recorded yet' ), false );
ck( 'the filter comes back selected in the form', chosen( $waiting, 'wpcpm_roster_status' ), 'waiting' );
ck( 'and can be cleared without losing the cohort',
	has( $waiting, 'href="/institution-dashboard/?wpcpm_cohort=2026-H1">Clear the filters' ), true );

$search = render( array( 'wpcpm_roster_search' => 'ada' ) );

ck( 'a search narrows the groups before they are drawn', group_count( $search, 'Current' ), 1 );
ck( 'to the matching student', has( group_rows( $search, 'current' ), 'Ada Example' ), true );
ck( 'and empties the ones with no match', group_count( $search, 'Waiting for a mentor' ), 0 );
ck( 'which say why they are empty', has( $search, 'No students in this group match the filters.' ), true );
ck( 'the term survives in the field', 1 === preg_match( '/name="wpcpm_roster_search"[^>]* value="ada"/', $search ), true );

$by_tutor = render( array( 'wpcpm_roster_search' => 'tutor example' ) );

ck( 'the search reads the tutor as well as the name', group_count( $by_tutor, 'Current' ), 1 );

$filtered = render( array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_search' => 'ada' ) );

ck( 'a filtered roster is a URL: the detail link carries the cohort and the search',
	has( $filtered, 'wpcpm_cohort=2026-H1&#038;wpcpm_roster_search=ada&#038;wpcpm_institution_student=21' ), true );
ck( 'no filter is set, so nothing offers to clear one', has( $html, 'Clear the filters' ), false );

$junk = render( array( 'wpcpm_cohort' => 'summer', 'wpcpm_roster_status' => 'everyone' ) );

ck( 'a cohort that is not a key falls back to the default', chosen( $junk, 'wpcpm_cohort' ), '2026-H1' );
ck( 'a group that is not one of the four is no filter at all', chosen( $junk, 'wpcpm_roster_status' ), '' );
ck( 'so every group is drawn', group_count( $junk, 'Not on the program now' ), 3 );

$posted = render( array(), $A, array( 'cohort' => '2025-H1', 'filters' => array( 'status' => 'finished', 'search' => '' ) ) );

ck( 'the dashboard may hand over the cohort it resolved', chosen( $posted, 'wpcpm_cohort' ), '2025-H1' );
ck( 'and the filters with it', chosen( $posted, 'wpcpm_roster_status' ), 'finished' );

echo "\n=== The manager switcher, and the form ===\n";

$managed = render( array( 'wpcpm_institution_view' => $A, 'wpcpm_roster_status' => 'current' ), $A, array( 'can_manage' => true ) );

ck( 'the switcher argument is carried through the filter form',
	has( $managed, '<input type="hidden" name="wpcpm_institution_view" value="' . $A . '" />' ), true );
ck( 'a value that is not a record ID is not carried anywhere',
	has( render( array( 'wpcpm_institution_view' => 'krakow' ), $A, array( 'can_manage' => true ) ), 'name="wpcpm_institution_view"' ), false );
ck( 'and a member never carries one, whatever their URL said',
	has( render( array( 'wpcpm_institution_view' => $B ) ), 'name="wpcpm_institution_view"' ), false );
ck( 'with pretty permalinks the page needs no page_id', has( $html, 'name="page_id"' ), false );

$GLOBALS['opts']['permalink_structure'] = '';
$plain                                  = render();
$GLOBALS['opts']['permalink_structure'] = '/%postname%/';

ck( 'and without them it carries one, as the mentor switcher does',
	has( $plain, '<input type="hidden" name="page_id" value="12" />' ), true );

echo "\n=== The roster export ===\n";

$exporting = render();

// Two decisions and not one. `view_roster` and `export` are separate rows of the policy's
// grounds(); today they carry the same grounds, so this only ever agrees with the first, and
// that is exactly why it has to be asked rather than assumed. handle_roster() decides on its
// own ACT_EXPORT call, so the day either row is narrowed a renderer reading the other would
// leave a link whose only destination is that handler's refusal page.
ck( 'the control asks the fence for its own action, on this institution',
	$GLOBALS['decisions'], array( array( 'view_roster', $A ), array( 'export', $A ) ) );
ck( 'and draws the link when the answer is yes', '' !== export_href( $exporting ), true );

// Beside Show, in the paragraph the spec puts the roster's actions in, rather than adrift
// above the groups where it would read as a heading.
ck( 'it sits in the actions paragraph beside Show',
	array( has( actions( $exporting ), '>Show</button>' ), has( actions( $exporting ), '>Download this cohort (CSV)</a>' ) ),
	array( true, true ) );

// The export is a GET. A second submit button inside this form would post the filter bar's own
// fields back to this page and never reach admin-post.php at all.
ck( 'and is a link, so the filter form still has exactly one button', substr_count( actions( $exporting ), '<button' ), 1 );

// The divergence itself: a reader the fence allows the roster and refuses the export. No
// shipped ground produces it today, which is why it is worth holding a fixture at: the whole
// value of drawing this control from ACT_EXPORT is what happens on the release that does.
$GLOBALS['refuse'] = array( 'export' );
$unsettled         = render();
$GLOBALS['refuse'] = array();

ck( 'a reader the fence refuses an export is offered no control', has( $unsettled, 'wpcpm-roster__export' ), false );
ck( 'though their roster is drawn as before', has( $unsettled, 'Ada Example' ), true );

// Institutions release 1: a tutor holds view_roster and neither export nor add_student (the policy suite's rows),
// which is exactly this fixture: the roster is drawn, with no file of it and no way to add a student.
$GLOBALS['refuse'] = array( 'export', 'add_student' );
$tutor_view        = render();
$GLOBALS['refuse'] = array();
ck( 'a tutor of the school is drawn the roster and no export control', array( has( $tutor_view, 'Ada Example' ), has( $tutor_view, 'wpcpm-roster__export' ) ), array( true, false ) );

$GLOBALS['allowed'] = false;
$refused_all        = render();
$GLOBALS['allowed'] = true;

ck( 'and a viewer refused the roster is offered no file of it either', has( $refused_all, 'wpcpm-roster__export' ), false );

// The whole address, because every part of it matters: the admin-post action, the cohort, and
// a nonce keyed to that action rather than to some other one check_admin_referer() would throw
// out.
ck( 'the link is a nonced admin-post address keyed to the roster export',
	export_href( $exporting ),
	'https://example.test/wp-admin/admin-post.php?action=wpcpm_export_roster&wpcpm_cohort=2026-H1&_wpnonce=nonce-wpcpm_export_roster' );

// Design spec 5.5: a member's own stamp or a manager's switcher, resolved by the handler. An
// institution that arrived inside a link is an institution nobody answered for.
ck( 'and it names no institution, which handle_roster() resolves for itself',
	false !== strpos( export_href( $exporting ), $A ), false );

$older_export = render( array( 'wpcpm_cohort' => '2025-H1' ) );
$none_export  = render( array( 'wpcpm_cohort' => 'none' ) );

ck( 'the cohort on screen travels, so the file is the semester being read',
	export_args( $older_export )['wpcpm_cohort'] ?? null, '2025-H1' );
ck( 'and No start date is a cohort the file can be of, like any other',
	export_args( $none_export )['wpcpm_cohort'] ?? null, 'none' );

$searched = render( array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_search' => 'ada' ) );

// roster_matrix() narrows by cohort and by nothing else, so a search that leaves one student on
// the page leaves all five in the file. That is why the label says "this cohort": a link
// promising "these students" would hand a school the other four without saying so.
ck( 'the search does not travel, because it does not narrow the file',
	export_args( $searched ),
	array( 'action' => 'wpcpm_export_roster', 'wpcpm_cohort' => '2026-H1', '_wpnonce' => 'nonce-wpcpm_export_roster' ) );
ck( 'and the label says which of the two the file is',
	has( $searched, '>Download this cohort (CSV)</a>' ), true );

$switched = render( array( 'wpcpm_institution_view' => $B ), $B, array( 'can_manage' => true ) );

// resolve_institution() reads this argument on the manager branch only. Without it a manager
// reading school B through the switcher would press Download and get school A's students, under
// a filename naming A.
ck( "a manager's switcher travels, or the file is of the other school",
	export_args( $switched )['wpcpm_institution_view'] ?? null, $B );

$member_stray = render( array( 'wpcpm_institution_view' => $B ) );

// **The subject the fence is asked about is the caller's, never the request's.** Design spec
// 5.5 forbids reading it out of a query string, and until this assertion existed nothing
// proved it: a reviewer swapped `subject_institution( $record_id )` for
// `subject_institution( WPCPM_Request::text( self::ARG_VIEW, $record_id ) )` - the forbidden
// shape exactly - and all 115 checks stayed green, because the only render that pinned the
// subject carried no switcher at all. This one carries a switcher naming another school and
// still expects both questions to be asked about this reader's own.
ck( 'a stray switcher does not become the subject of either question',
	$GLOBALS['decisions'], array( array( 'view_roster', $A ), array( 'export', $A ) ) );

// The filter form refuses to echo a member's stray switcher back into its own links, and the
// export link does carry one, because roster_url() reads the argument out of the request for
// itself. It is inert: resolve_institution() only consults it on the manager branch, so a
// member still gets their own institution's file. Pinned so the difference between the two
// links is a decision on the record rather than something nobody noticed.
ck( "a member's stray switcher rides along in the export link, where the handler ignores it",
	export_args( $member_stray )['wpcpm_institution_view'] ?? null, $B );
ck( 'while their filter form still refuses to carry one',
	has( $member_stray, 'name="wpcpm_institution_view"' ), false );

echo "\n=== Days left ===\n";

$GLOBALS['index'][ $A ]['rows']['recSTU00000000001']['end'] = $GLOBALS['today'];
ck( 'an end date of today', has( group_rows( render(), 'current' ), 'Ends today' ), true );

$GLOBALS['index'][ $A ]['rows']['recSTU00000000001']['end'] = '2026-09-03';
ck( 'one day out', has( group_rows( render(), 'current' ), '1 day left' ), true );

$GLOBALS['index'][ $A ]['rows']['recSTU00000000001']['end'] = '2026-08-31';
ck( 'a date that has passed', has( group_rows( render(), 'current' ), 'Ended 2 days ago' ), true );

$GLOBALS['index'][ $A ]['rows']['recSTU00000000001']['end'] = '2026-02-31';
ck( 'a date that never existed is not a number of days',
	has( group_rows( render(), 'current' ), 'days' ), false );

$GLOBALS['index'][ $A ]['rows']['recSTU00000000001']['end'] = '2026-09-30';

echo "\n=== A student waiting to graduate keeps their course ===\n";

/**
 * The Program cell of one student's card, as markup.
 *
 * @param string $html  The rendered page.
 * @param string $group Which group the card is in.
 * @param string $name  The student's name.
 * @return string|null
 */
function program_of( $html, $group, $name ) {
	foreach ( group_cards( $html, $group ) as $card ) {
		if ( card_names( $card, $name ) && null !== row_value( $card, 'students-status' ) ) {
			return trim( row_value( $card, 'students-status' ) );
		}
	}

	return null;
}

// Ada's account says Pending graduation and remembers the 50-hour track she took. The school reads
// her course where it reads a current student's, with the status beside it, and her hours against
// that course's target.
ada_program( array( 'program' => 'Pending graduation', 'hours' => '12' ) );
$GLOBALS['umeta'][21]['wpcpm_student_course'] = 'In Sensei 50h';

$pending  = '<span class="wpcpm-status wpcpm-status--amber">Pending graduation</span>';
$fifty    = '<span class="wpcpm-status wpcpm-status--green">' . WPCPM_Program::label( 'In Sensei 50h' ) . '</span>';
$kept     = render();

// Gate B, B9: the head carries the status as its tag, so the cell says it in words beside the course's tag.
ck( 'a pending student\'s Program cell names their course as a current student\'s does, with Pending graduation beside it in words (the head carries its tag)',
	program_of( $kept, 'current', 'Ada Example' ), $fifty . ' Pending graduation' );
ck( 'and the hours are read against that course\'s target', hours_of( $kept, 'current', 'Ada Example' ), '12 of 50' );
ck( 'while the card still wears the Pending graduation tag at the end of its head', has( card_summary( $kept, 'current', 'Ada Example' ), '<span class="wpcpm-ui-fold__end">' . $pending . '</span>' ), true );

// No course known: the cell keeps what it always said, the status alone, and no target.
$GLOBALS['umeta'][21]['wpcpm_student_course'] = '';
$unknown = render();

ck( 'a pending student with no known course shows Pending graduation alone, in words',
	array( program_of( $unknown, 'current', 'Ada Example' ), hours_of( $unknown, 'current', 'Ada Example' ) ),
	array( 'Pending graduation', '12 h' ) );

// Paused keeps no course, whatever the account remembers.
ada_program( array( 'program' => 'Paused', 'hours' => '12' ) );
$GLOBALS['umeta'][21]['wpcpm_student_course'] = 'In Sensei 50h';

ck( 'a paused student\'s cell says Paused, in words, with no course', program_of( render(), 'current', 'Ada Example' ), 'Paused' );

unset( $GLOBALS['umeta'][21]['wpcpm_student_course'] );
ada_program();

echo "\n=== The cohort the Students view opens on, asked by the Overview (Task 6) ===\n";

// The Overview's figures count the cohort the Students view opens on, so the two read one answer: this semester
// while the school has students in it, otherwise the newest cohort it has.
$opening = static function ( $record ) {
	return method_exists( 'WPCPM_Institution_Roster_View', 'opening_cohort' ) ? WPCPM_Institution_Roster_View::opening_cohort( $record ) : 'no opening_cohort()';
};
ck( 'with nobody this semester, the newest cohort the school has', $opening( $A ), '2026-H1' );
$GLOBALS['today'] = '2025-10-01';
ck( 'this semester while it has students', $opening( $A ), '2025-H2' );
ck( 'and a school whose only cohort is a later one opens on that one', $opening( $B ), '2026-H1' );
ck( 'with no rows at all, this semester', $opening( 'recINSTZZZ0000001' ), '2025-H2' );
$GLOBALS['today'] = '2026-09-02';
// The roster opens on the cohort `opening_cohort()` names: with no cohort asked for, what it draws is that cohort's page.
$asked_h2 = render( array( 'wpcpm_cohort' => '2025-H2' ) );
$GLOBALS['today'] = '2025-10-01';
ck( 'on the semester it opens on by itself, the roster draws that cohort, as if it had been asked for', render(), $asked_h2 );
$GLOBALS['today'] = '2026-09-02';

echo "\n=== The Students view on the parts (Institution Dashboard, Task 15) ===\n";

/** The roster drawn while the Institution Dashboard's surface is on the parts. */
function parts( array $get = array(), $record = 'recINSTAAA0000001', array $context = array() ) {
	WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
	WPCPM_UI::enter( 'institution' );
	try {
		return render( $get, $record, $context );
	} finally {
		WPCPM_UI::leave();
		WPCPM_UI::set_live( null );
	}
}

/** The filter form's fields as name => value: hidden inputs, each select's chosen option, the search box. */
function form_pairs( $html ) {
	if ( ! preg_match( '/<form[^>]*method="get"[^>]*>(.*?)<\/form>/s', $html, $f ) ) { return null; }
	$pairs = array();
	preg_match_all( '/<input type="hidden" name="([^"]*)" value="([^"]*)"/', $f[1], $hidden, PREG_SET_ORDER );
	foreach ( $hidden as $h ) { $pairs[ $h[1] ] = $h[2]; }
	preg_match_all( '/<select[^>]*name="([^"]*)"[^>]*>(.*?)<\/select>/s', $f[1], $selects, PREG_SET_ORDER );
	foreach ( $selects as $s ) {
		$pairs[ $s[1] ] = preg_match( '/<option value="([^"]*)"(?: selected=\'selected\'| selected)/', $s[2], $o ) ? $o[1] : '';
	}
	if ( preg_match( '/<input type="search"[^>]*name="([^"]*)"[^>]*value="([^"]*)"/', $f[1], $q ) ) { $pairs[ $q[1] ] = $q[2]; }
	ksort( $pairs );
	return $pairs;
}

/** One parts group's section, by its key. */
function parts_group( $html, $key ) {
	return preg_match( '/<section class="wpcpm-ui-fold-card wpcpm-roster__group wpcpm-roster__group--' . preg_quote( $key, '/' ) . '" id="wpcpm-roster-' . preg_quote( $key, '/' ) . '">(.*?)<\/section>/s', $html, $m ) ? $m[1] : '';
}

/** Whether a parts group's disclosure is printed open: true, false, or null when the group is not drawn. */
function parts_open( $html, $key ) {
	$group = parts_group( $html, $key );
	if ( '' === $group ) { return null; }
	return 0 === strpos( $group, '<details class="wpcpm-ui-fold" open>' ) ? true : ( 0 === strpos( $group, '<details class="wpcpm-ui-fold">' ) ? false : null );
}

/** A parts group's phrase, the count in words. */
function parts_phrase( $html, $key ) {
	return preg_match( '/<span class="wpcpm-ui-fold__phrase">([^<]*)<\/span>/', parts_group( $html, $key ), $m ) ? $m[1] : null;
}

/** The students' names a roster draws, in order, the break opportunities taken out. */
function names_drawn( $html ) {
	$new = preg_match_all( '/<span class="wpcpm-ui-fold__title">(?:<a [^>]*>)?(.*?)(?:<\/a>)?<\/span>/', $html, $n ) ? $n[1] : array();
	return array_map( static function ( $name ) { return str_replace( '<wbr>', '', $name ); }, $new );
}

/** The column headings a roster draws in its students' tables, once each. */
function columns_drawn( $html ) {
	$new = preg_match_all( '/<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">(.*?)<\/th>/', $html, $n ) ? $n[1] : array();
	return array_values( array_unique( $new ) );
}

/** The names of the enrollment-not-recorded list's rows on the parts, the break opportunities taken out. */
function names_of_unlinked( $html ) {
	return preg_match_all( '/<span class="wpcpm-ui-list__name">(.*?)<\/span>/', $html, $m ) ? array_map( static function ( $name ) { return str_replace( '<wbr>', '', $name ); }, $m[1] ) : array();
}

/** A parts group's whole section, by its key, for the layout harness. */
function parts_section( $html, $key ) {
	return preg_match( '/<section class="wpcpm-ui-fold-card wpcpm-roster__group wpcpm-roster__group--' . preg_quote( $key, '/' ) . '".*?<\/section>/s', $html, $m ) ? $m[0] : '';
}

/** One student's inner fold on the parts, by the name its head shows. */
function parts_student( $html, $name ) {
	foreach ( explode( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner', $html ) as $i => $card ) {
		if ( $i > 0 && false !== strpos( str_replace( '<wbr>', '', substr( $card, 0, (int) strpos( $card, '</summary>' ) ) ), $name ) ) {
			return substr( $card, 0, (int) strpos( $card, '</details>' ) );
		}
	}
	return '';
}

$on = parts();

// What the old branch's filter form posted and offered, read from the class at db850661 (the last commit that drew it)
// before it was deleted (Task 23): the parts are held to it.
$old_pairs   = array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_search' => '', 'wpcpm_roster_status' => '' );
$old_manager = array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_institution_view' => 'recINSTAAA0000001', 'wpcpm_roster_search' => 'ada', 'wpcpm_roster_status' => 'current' );
$old_plain   = array( 'page_id' => '12', 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_search' => '', 'wpcpm_roster_status' => '' );
$old_cohorts = array( 'January to June 2026 (5)', 'July to December 2025 (2)', 'January to June 2025 (1)', 'No start date (1)' );
$old_groups  = array( 'All students', 'Current', 'Waiting for a mentor', 'Finished', 'Not on the program now' );
$old_export  = 'https://example.test/wp-admin/admin-post.php?action=wpcpm_export_roster&wpcpm_cohort=2026-H1&_wpnonce=nonce-wpcpm_export_roster';

// Rulings 3 and 2: no inset of its own, one name per page, and the anchor the view's card is linked by.
ck( 'on the parts the roster wraps itself in no inset class, and carries the anchor its view is linked by', array(
	has( $on, '<section class="wpcpm-roster">' ),
	0 === strpos( $on, '<section class="wpcpm-roster__view" id="wpcpm-roster">' ),
), array( false, true ) );
$on_titled = parts( array(), $A, array( 'module_titled' => true ) );
ck( 'in a titled view nothing under the view\'s head repeats "Students" as a title', array(
	preg_match( '/<h[1-6][^>]*>Students<\/h[1-6]>/', $on_titled ),
	has( $on_titled, 'Read from the program records' ),
), array( 0, false ) );

// The filter bar: today's GET form, today's names, now the view it belongs to.
ck( 'the filter form posts today\'s names and values, and now the view', form_pairs( $on ), array_merge( $old_pairs, array( 'wpcpm_view' => 'students' ) ) );
ck( 'and they are the cohort, the group and the search', form_pairs( $on ), array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_search' => '', 'wpcpm_roster_status' => '', 'wpcpm_view' => 'students' ) );
$on_manager  = parts( array( 'wpcpm_institution_view' => $A, 'wpcpm_roster_status' => 'current', 'wpcpm_roster_search' => 'ada' ), $A, array( 'can_manage' => true ) );
$GLOBALS['opts']['permalink_structure'] = '';
$on_plain = parts();
$GLOBALS['opts']['permalink_structure'] = '/%postname%/';
ck( 'an Administrator\'s form keeps the school viewed, and a page without pretty addresses its page_id', array(
	form_pairs( $on_manager ),
	form_pairs( $on_plain ),
), array(
	array_merge( $old_manager, array( 'wpcpm_view' => 'students' ) ),
	array_merge( $old_plain, array( 'wpcpm_view' => 'students' ) ),
) );
ck( 'with the hidden inputs in today\'s order, the view last', preg_match_all( '/<input type="hidden" name="([^"]*)"/', $on_manager, $hm ) ? $hm[1] : array(), array( 'wpcpm_institution_view', 'wpcpm_view' ) );
ck( 'the three fields are the parts\' fields, each label above its box, in one row of fields', array(
	has( $on, '<div class="wpcpm-ui-fields"><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-roster-cohort">Cohort</label><select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" id="wpcpm-roster-cohort" name="wpcpm_cohort">' ),
	has( $on, '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-roster-status">Group</label><select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" id="wpcpm-roster-status" name="wpcpm_roster_status">' ),
	has( $on, '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-roster-search">Search</label><input type="search" class="wpcpm-ui-field__control" id="wpcpm-roster-search" name="wpcpm_roster_search" aria-describedby="wpcpm-roster-search-hint" value=""><p class="wpcpm-ui-field__hint" id="wpcpm-roster-search-hint">Name, WordPress.org username, tutor, field of study</p></div></div>' ),
	has( $on, 'placeholder=' ),
), array( true, true, true, false ) );
ck( 'the options are today\'s, in today\'s order', array(
	preg_match_all( '/<option value="[^"]*"(?: selected)?>([^<]*)<\/option>/', (string) strstr( (string) strstr( $on, 'id="wpcpm-roster-cohort"' ), '</select>', true ), $co ) ? $co[1] : array(),
	preg_match_all( '/<option value="[^"]*"(?: selected)?>([^<]*)<\/option>/', (string) strstr( (string) strstr( $on, 'id="wpcpm-roster-status"' ), '</select>', true ), $go ) ? $go[1] : array(),
), array( $old_cohorts, $old_groups ) );
ck( 'Show is the secondary button; with no filter set nothing offers to clear one', array(
	has( $on, '<button type="submit" class="wpcpm-btn wpcpm-btn--secondary">Show</button>' ),
	has( $on, 'Clear the filters' ),
), array( true, false ) );
$on_waiting = parts( array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_status' => 'waiting' ) );
ck( 'with a filter set, Clear the filters is a quiet link to today\'s address', has( $on_waiting, '<a class="wpcpm-btn wpcpm-btn--quiet" href="/institution-dashboard/?wpcpm_cohort=2026-H1">Clear the filters</a>' ), true );
ck( 'the export is a quiet link to today\'s address, asked of its own fence', array(
	has( $on, '<a class="wpcpm-btn wpcpm-btn--quiet" href="' . esc_url( $old_export ) . '">Download this cohort (CSV)</a>' ),
	$GLOBALS['decisions'],
), array( true, array( array( 'view_roster', $A ), array( 'export', $A ) ) ) );
$GLOBALS['refuse'] = array( 'export' );
$on_no_export      = parts();
$GLOBALS['refuse'] = array();
ck( 'and absent when the fence refuses the export', has( $on_no_export, 'Download this cohort (CSV)' ), false );
ck( 'the Administrator\'s search box names what an Administrator\'s search reads, as today', has( $on_manager, '<p class="wpcpm-ui-field__hint" id="wpcpm-roster-search-hint">Name, email, WordPress.org username, tutor, field of study, Students record ID</p>' ), true );

// The cohort summary: the cohort's name and today's two sentences, no colored edge.
ck( 'the cohort summary is a plain block: the cohort\'s name, then today\'s two sentences', has( $on, '<section class="wpcpm-roster__summary wpcpm-ui-block"><h3 class="wpcpm-ui-h">January to June 2026</h3><p class="wpcpm-ui-intro">5 students signed up. 1 has graduated.</p><p class="wpcpm-ui-intro">Compared with July to December 2025: 2 signed up, 1 graduated.</p></section>' ), true );
ck( 'and the old strip is not drawn', has( $on, 'wpcpm-roster__strip' ), false );

// Ruling 1: today's open rule, through the parts' fold; the count in words.
$C = 'recINSTCCC0000001';
$GLOBALS['index'][ $C ] = array( 'read' => 1788336000, 'rows' => array() );
foreach ( array( 'Ca', 'Cb', 'Cc' ) as $i => $n ) {
	$GLOBALS['index'][ $C ]['rows'][ 'recSTC0000000000' . $i ] = row( 'recSTC0000000000' . $i, $n . ' Example', 'In Sensei', '2026-02-16', array( 'has_mentor' => true ) );
}
foreach ( array( 'Wa', 'Wb' ) as $i => $n ) {
	$GLOBALS['index'][ $C ]['rows'][ 'recSTW0000000010' . $i ] = row( 'recSTW0000000010' . $i, $n . ' Example', 'In Sensei', '2026-02-16' );
}
$GLOBALS['index'][ $C ]['rows']['recSTF00000000001'] = row( 'recSTF00000000001', 'Fa Example', 'Graduate', '2026-02-16' );
$ruling = parts( array(), $C );
ck( 'ruling 1: with 3 current, 2 waiting, 1 finished and none not started, Current and Waiting open, the other two closed', array(
	parts_open( $ruling, 'current' ), parts_open( $ruling, 'waiting' ), parts_open( $ruling, 'finished' ), parts_open( $ruling, 'not_started' ),
), array( true, true, false, false ) );
ck( 'each group\'s count is a phrase in words, never a bare number in a badge', array(
	parts_phrase( $ruling, 'current' ), parts_phrase( $ruling, 'waiting' ), parts_phrase( $ruling, 'finished' ), parts_phrase( $ruling, 'not_started' ),
	has( $ruling, 'wpcpm-group__count' ), has( $ruling, 'wpcpm-ui-count' ),
), array( '3 students', '2 students', '1 student', '0 students', false, false ) );
ck( 'a group\'s head is the card fold\'s, its title the group\'s name', has( parts_group( $ruling, 'current' ), '<summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Current</h3><span class="wpcpm-ui-fold__phrase">3 students</span>' ), true );
ck( 'its body opens with the group\'s sentence; an empty group says so in today\'s words', array(
	has( parts_group( $ruling, 'current' ), '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">With a mentor assigned or a progress record already started, students waiting to graduate included.</p>' ),
	has( parts_group( $ruling, 'not_started' ), '<p class="wpcpm-ui-list__empty">No students in this group.</p>' ),
	has( parts_group( parts( array( 'wpcpm_roster_search' => 'nobody at all' ), $C ), 'current' ), '<p class="wpcpm-ui-list__empty">No students in this group match the filters.</p>' ),
), array( true, true, true ) );

// The long fixture: thirteen current students, a 40-character word in a name, a mentor's name, a username, a tutor
// and a website; and an enrollment not recorded yet with a long name and a long address.
$D        = 'recINSTDDD0000001';
$long_stu = 'Exampleunbrokenstudentnamewithfortychars';
$long_men = 'Exampleunbrokenmentornamewithfortycharss';
$long_tut = 'Exampleunbrokentutornamewithfortycharsss';
$long_usr = 'exampleunbrokenusernamewithfortycharacte';
$long_web = 'https://averyveryveryverylongsubdomainnameofthe.example.org/a/long/path/to/the/page';
$GLOBALS['index'][ $D ] = array( 'read' => 1788336000, 'rows' => array() );
for ( $i = 1; $i <= 13; $i++ ) {
	$key = 'recSTD' . str_pad( (string) $i, 11, '0', STR_PAD_LEFT );
	$GLOBALS['index'][ $D ]['rows'][ $key ] = row( $key, 1 === $i ? 'Ama ' . $long_stu : 'Student' . $i . ' Example', 'In Sensei', '2026-02-16', array(
		'end'            => '2026-09-30',
		'has_mentor'     => true,
		'reports'        => array( 'recREPD' . str_pad( (string) $i, 10, '0', STR_PAD_LEFT ) ),
		'username'       => 2 === $i ? '' : ( 1 === $i ? $long_usr : 'student' . $i ),
		'tutor'          => 1 === $i ? 'Tia ' . $long_tut : 'Tutor Example',
		'field_of_study' => 'Technology & Engineering',
		'mentor_name'    => 1 === $i ? 'Mia ' . $long_men : 'Mentor Example',
		'website'        => 1 === $i ? $long_web : '',
		'user_id'        => 1 === $i ? 701 : 0,
	) );
}
$GLOBALS['umeta'][701] = array(
	'wpcpm_student_program' => array( 'name' => 'Ama ' . $long_stu, 'program' => 'In Sensei', 'team' => 'Documentation Team', 'website' => $long_web, 'hours' => '12' ),
	'wpcpm_student_mentor'  => array( 'name' => 'Mia ' . $long_men, 'email' => 'mia@example.org' ),
);
$GLOBALS['unlinked'][ $D ] = array(
	801 => row( '', 'Una ' . $long_stu, 'In Sensei', '2026-02-16', array( 'user_id' => 801, 'record_id' => '', 'email' => 'averyveryveryverylongmailboxname1234@example.org' ) ),
	802 => row( '', 'Ula Example', 'Graduate', '2026-02-16', array( 'user_id' => 802, 'record_id' => '', 'email' => 'ula@example.org' ) ),
);
$long_on  = parts( array(), $D );
ck( 'ruling 1: thirteen current students start their group closed, the count in words', array( parts_open( $long_on, 'current' ), parts_phrase( $long_on, 'current' ) ), array( false, '13 students' ) );
// Task 28, W6: a group's students are a list again, as on the live page: one list, an item per student, each item the
// student's fold and nothing else, so a screen reader hears "list, 13 items" and moves from student to student.
$w6_group = parts_group( $long_on, 'current' );
ck( 'a group\'s students are one list whose items are their folds, after the group\'s sentence and nothing after it', array(
	substr_count( $w6_group, '<ul class="wpcpm-ui-folds" role="list">' ),
	substr_count( $w6_group, '<li class="wpcpm-ui-folds__item"><details class="wpcpm-ui-fold wpcpm-ui-fold--inner' ),
	substr_count( $w6_group, '</details></li>' ),
	1 === preg_match( '#<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">[^<]+</p><ul class="wpcpm-ui-folds" role="list"><li class="wpcpm-ui-folds__item">.*</details></li></ul></div></details>$#s', $w6_group ),
), array( 1, 13, 13, true ) );
ck( 'every student is an inner fold with the photo or the initials as its media, closed in a group of thirteen', array(
	substr_count( $long_on, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__portrait">' ),
	has( parts_student( $long_on, 'Student2 Example' ), '<span class="wpcpm-ui-fold__portrait"><span class="wpcpm-ui-fold__initials" aria-hidden="true">S</span></span>' ),
	1 === preg_match( '#<span class="wpcpm-ui-fold__portrait"><img class="wpcpm-avatar" src="[^"]*student3[^"]*" srcset="[^"]*" width="44" height="44" alt="[^"]*" title="[^"]*" loading="lazy" decoding="async" /></span>#', parts_student( $long_on, 'Student3 Example' ) ),
), array( 13, true, true ) );
$ama = parts_student( $long_on, 'Ama ' . $long_stu );
ck( 'the name links to the student\'s card as plain words (the sheet breaks them, no `<wbr>`, Task 22b), the dates whole and the mentor its second piece, the program a green tag', array(
	has( $ama, '<span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title"><a href="/institution-dashboard/?wpcpm_institution_student=701">Ama ' . $long_stu . '</a></span><span class="wpcpm-ui-fold__second"><span class="wpcpm-ui-whole">2026-02-16</span> to <span class="wpcpm-ui-whole">2026-09-30</span> · Mia ' . $long_men . '</span></span>' ),
	has( $ama, '<span class="wpcpm-ui-fold__end"><span class="wpcpm-status wpcpm-status--green">WordPress Credits Program 150h</span></span><span class="wpcpm-ui-fold__chev" aria-hidden="true">' ),
	substr_count( $ama, '<wbr>' ),
), array( true, true, 0 ) );
ck( 'its body is a key and value table of the permitted columns, the name not repeated', array(
	has( $ama, '<div class="wpcpm-ui-fold__body"><table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table"><caption class="screen-reader-text">Program details for Ama ' . $long_stu . '</caption><tbody role="rowgroup">' ),
	columns_drawn( $ama ),
), array( true, array( 'Program', 'Dates', 'Days left', 'Mentor', 'WordPress.org', 'Team', 'Website', 'Field of study', 'Tutor', 'Hours' ) ) );
ck( 'the WordPress.org and Website cells are token cells, the dates whole, the names plain words the key and value table breaks (Task 22b)', array(
	1 === preg_match( '#<td class="wpcpm-ui-cell--token" role="cell"><a href="https://profiles.wordpress.org/' . $long_usr . '/" target="_blank" rel="noopener noreferrer">' . preg_quote( $long_usr, '#' ) . '</a></td>#', $ama ),
	has( $ama, '<td class="wpcpm-ui-cell--token" role="cell"><a href="' . $long_web . '" target="_blank" rel="noopener noreferrer">averyveryveryverylongsubdomainnameofthe.example.org/a/long/path/to/the/page</a></td>' ),
	has( $ama, '<td class="wpcpm-ui-cell--text" role="cell"><span class="wpcpm-ui-whole">2026-02-16</span> to <span class="wpcpm-ui-whole">2026-09-30</span></td>' ),
	has( $ama, '<td class="wpcpm-ui-cell--text" role="cell">Mia ' . $long_men . '</td>' ),
	has( $ama, '<td class="wpcpm-ui-cell--text" role="cell">Tia ' . $long_tut . '</td>' ),
	has( $ama, '<td class="wpcpm-ui-cell--text" role="cell">12 of 150</td>' ),
), array( true, true, true, true, true, true ) );
ck( 'an empty value says "Not recorded" with the empty kind', array(
	has( parts_student( $long_on, 'Student2 Example' ), '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">Not recorded</td>' ),
	has( $on, 'class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">Not recorded</td>' ),
), array( true, true ) );
ck( 'a student is open when the group holds one, as today', array(
	has( parts_group( $ruling, 'finished' ), '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait" open>' ),
	has( parts_group( $ruling, 'current' ), '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait" open>' ),
), array( true, false ) );
ck( 'a mentor who is not named yet is today\'s sentence with the empty kind; a pipeline status is plain words', array(
	has( parts_student( $on, 'Bo Example' ), 'class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">A mentor is assigned. Their name shows here once the student&#039;s progress record reaches this page.</td>' ),
	has( parts_student( $on, 'Cy Example' ), 'class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">No mentor yet.</td>' ),
	has( parts_student( $on, 'Eve Example' ), '<td class="wpcpm-ui-cell--text" role="cell">Not continuing</td>' ),
	has( $on, 'wpcpm-badge' ),
), array( true, true, true, false ) );
ck( 'a finished student with no account still wears the tag of their past status, neutral', has( parts_student( $on, 'Dee Example' ), '<span class="wpcpm-ui-fold__end"><span class="wpcpm-status">Graduated</span></span>' ), true );
ck( 'the accessibility disclosure appears nowhere on the parts either', array( has( $on, 'Screen reader user' ), has( $on, 'Accessibility' ) ), array( false, false ) );

// The enrollment not recorded yet: a card fold like a group, the count in words, today's sentence, a list of rows.
$unl_member  = parts( array(), $D );
$unl_manager = parts( array(), $D, array( 'can_manage' => true ) );
ck( 'enrollment not recorded yet is a card fold like a group, open, its count in words, today\'s sentence first', array(
	has( $unl_member, '<section class="wpcpm-ui-fold-card wpcpm-roster__group wpcpm-roster__group--unlinked" id="wpcpm-roster-unlinked"><details class="wpcpm-ui-fold" open><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Enrollment not recorded yet</h3><span class="wpcpm-ui-fold__phrase">2 students</span>' ),
	has( parts_group( $unl_member, 'unlinked' ), '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">These students are reporting their progress, but the program records hold no enrollment for them yet, so they are not counted in the groups above. An Administrator needs to complete the record.</p><ul class="wpcpm-ui-list">' ),
), array( true, true ) );
ck( 'each a row: the name and the status tag; an Administrator sees the address as a fact, a member never', array(
	has( $unl_member, '<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Ula Example</span><span class="wpcpm-status">Graduated</span><span class="wpcpm-ui-list__facts"></span></div></li>' ),
	has( $unl_member, '@example.org' ),
	has( $unl_manager, '<span class="wpcpm-ui-list__name">Ula Example</span><span class="wpcpm-status">Graduated</span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">ula@example.org</span></span>' ),
	has( $unl_manager, '<span class="wpcpm-ui-item__fact">averyveryveryverylongmailboxname1234@example.org</span>' ),
	substr_count( $unl_manager, '<wbr>' ),
), array( true, false, true, true, 0 ) );
ck( 'and the same people as today, the search narrowing it as today', array(
	names_of_unlinked( $unl_manager ),
	names_of_unlinked( parts( array( 'wpcpm_roster_search' => 'ula@' ), $D, array( 'can_manage' => true ) ) ),
	names_of_unlinked( parts( array( 'wpcpm_roster_search' => 'ula@' ), $D ) ),
	has( parts( array( 'wpcpm_roster_status' => 'current' ), $D ), 'wpcpm-roster__group--unlinked' ),
), array( array( 'Una ' . $long_stu, 'Ula Example' ), array( 'Ula Example' ), array(), false ) );

// Ruling 2: the same queries, the same policy calls, the same columns and students for each reader as the old branch
// drew: digests of what the old branch drew for each, read from the class at db850661 before it was deleted (Task 23).
$old_readers = array(
	'representative'                    => '8952613a32234aa7945a6ee4c227f276',
	'tutor (no export, a narrower set)' => '719cb6fb9868021f95c1e42cc34467b8',
	'Administrator viewing a school'    => '8952613a32234aa7945a6ee4c227f276',
);
$readers = array(
	'representative'                 => array( array(), array(), null, array() ),
	'tutor (no export, a narrower set)' => array( array(), array(), array( 'students|Full Name', 'students|Status', 'students|Start Date', 'students|Mentor', 'reports|Hours' ), array( 'export', 'add_student' ) ),
	'Administrator viewing a school' => array( array( 'wpcpm_institution_view' => $A ), array( 'can_manage' => true ), null, array() ),
);
foreach ( $readers as $reader => $setup ) {
	$GLOBALS['fields'] = $setup[2];
	$GLOBALS['refuse'] = $setup[3];
	$drawn             = array();
	foreach ( array( array(), array( 'wpcpm_cohort' => '2025-H2' ), array( 'wpcpm_roster_search' => 'example' ), array( 'wpcpm_roster_status' => 'finished' ) ) as $get ) {
		$html    = parts( $get + $setup[0], $A, $setup[1] );
		$drawn[] = array( names_drawn( $html ), columns_drawn( $html ), $GLOBALS['decisions'], has( $html, 'Download this cohort (CSV)' ) );
	}
	ck( 'ruling 2, ' . $reader . ': the same students, the same columns, the same fence questions and the same export as the old branch drew', md5( serialize( $drawn ) ), $old_readers[ $reader ] );
}
$GLOBALS['fields'] = array( 'students|Full Name', 'students|Status', 'students|Start Date', 'students|Mentor', 'reports|Hours' );
$GLOBALS['refuse'] = array( 'export', 'add_student' );
ck( 'the narrower set is narrower: the four columns it names after the name', columns_drawn( parts() ), array( 'Program', 'Dates', 'Mentor', 'Hours' ) );
$tutor_on          = parts( array(), $D );
$GLOBALS['fields'] = null;
$GLOBALS['refuse'] = array();

// The whole view on the real roster (Task 23, fix round 1): each reader's roster, as the page asks for it (titled by
// the view), for each query above and for the long school, and the not-recorded list, under the Students view's own head.
$t23_views = array();
foreach ( $readers as $reader => $setup ) {
	$GLOBALS['fields'] = $setup[2];
	$GLOBALS['refuse'] = $setup[3];
	foreach ( array( 'all' => array(), 'a cohort' => array( 'wpcpm_cohort' => '2025-H2' ), 'a search' => array( 'wpcpm_roster_search' => 'example' ), 'finished' => array( 'wpcpm_roster_status' => 'finished' ), 'nothing found' => array( 'wpcpm_roster_search' => 'zzz-nobody' ) ) as $query => $get ) {
		$t23_views[ $reader . ', ' . $query ] = parts( $get + $setup[0], $A, $setup[1] + array( 'module_titled' => true ) );
		$t23_views[ $reader . ', ' . $query . ', the long school' ] = parts( $get + $setup[0], $D, $setup[1] + array( 'module_titled' => true ) );
	}
}
$GLOBALS['fields'] = null;
$GLOBALS['refuse'] = array();
// Each student on their own line (Task 23, fix round 1, S5): a representative's roster of this school, each
// student's fold read whole (its head and its facts), the days left masked as they move with the clock. Read off the
// fixture by hand: Ada Example is the one with a mentor's name, a tutor and a profile; Bo Example's mentor is assigned
// but not yet named; Dee Example graduated; Eve Example is not continuing; Fi and Lu Example are interested.
$t23_students = array();
foreach ( explode( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner', $t23_views['representative, all'] ) as $t23_i => $t23_card ) {
	if ( $t23_i > 0 ) {
		$t23_card       = substr( $t23_card, (int) strpos( $t23_card, '>' ) + 1, (int) strpos( $t23_card, '</details>' ) - (int) strpos( $t23_card, '>' ) - 1 );
		$t23_text       = trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( str_replace( '<', ' <', (string) preg_replace( '#<span class="screen-reader-text">.*?</span>#s', '', $t23_card ) ) ), ENT_QUOTES ) ) );
		$t23_students[] = (string) preg_replace( '/Days left \d+ days? left/', 'Days left {n} days left', $t23_text );
	}
}
ck( 'each student reads their own program, dates, mentor and facts on their own line', $t23_students, array(
	'Ada Example 2026-02-16 to 2026-09-30 · Mo Mentor WordPress Credits Program 150h Program details for Ada Example Program WordPress Credits Program 150h Dates 2026-02-16 to 2026-09-30 Days left {n} days left Mentor Mo Mentor WordPress.org adaexample Team Documentation Team Website ada.example.com Field of study Technology & Engineering Tutor Tutor Example Hours Not recorded',
	'B Bo Example From 2026-02-16 Program details for Bo Example Program WordPress Credits Program 150h Dates From 2026-02-16 Days left Not recorded Mentor A mentor is assigned. Their name shows here once the student\'s progress record reaches this page. WordPress.org Not recorded Team Not recorded Website Not recorded Field of study Not recorded Tutor Not recorded Hours Not recorded',
	'C Cy Example From 2026-03-01 Program details for Cy Example Program WordPress Credits Program 50h Dates From 2026-03-01 Days left Not recorded Mentor No mentor yet. WordPress.org Not recorded Team Not recorded Website Not recorded Field of study Not recorded Tutor Not recorded Hours Not recorded',
	'D Dee Example From 2026-01-20 · Mo Mentor Graduated Program details for Dee Example Program Graduated Dates From 2026-01-20 Days left Not recorded Mentor Mo Mentor WordPress.org Not recorded Team Not recorded Website Not recorded Field of study Not recorded Tutor Not recorded Hours Not recorded',
	'E Eve Example From 2026-02-16 Program details for Eve Example Program Not continuing Dates From 2026-02-16 Days left Not recorded Mentor No mentor yet. WordPress.org Not recorded Team Not recorded Website Not recorded Field of study Not recorded Tutor Not recorded Hours Not recorded',
	'F Fi Example From 2026-02-16 Program details for Fi Example Program Interested Dates From 2026-02-16 Days left Not recorded Mentor No mentor yet. WordPress.org Not recorded Team Not recorded Website Not recorded Field of study Not recorded Tutor Not recorded Hours Not recorded',
	'L Lu Example From 2026-02-16 Program details for Lu Example Program Interested Dates From 2026-02-16 Days left Not recorded Mentor No mentor yet. WordPress.org Not recorded Team Not recorded Website Not recorded Field of study Not recorded Tutor Not recorded Hours Not recorded',
) );
ck( 'the whole Students view on the real roster, every reader and query: one H1, the headings in order, no id twice, the lede once, no title again', array( wpcpm_view_faults_of( 'Students', $t23_views ), count( array_filter( array_map( 'trim', $t23_views ) ) ) ), array( array(), 30 ) );

// Fix round 2. The rules that decide what a reader may see are written once: the filter form's hidden fields (only an
// Administrator's form carries the school viewed) and the not-recorded list's names (a member never sees an address).
// Read from the source, so a second copy cannot come back unnoticed.
$src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php' );
$body_of = static function ( $name ) use ( $src ) {
	$at = strpos( $src, 'private static function ' . $name . '(' );
	return false === $at ? '' : substr( $src, $at, strpos( $src, "\n\t}", $at ) - $at );
};
ck( 'fix round 2: the filter form prints its hidden fields through one helper, and the list names a person through one', array(
	substr_count( $body_of( 'render_filters_parts' ), 'self::hidden_fields(' ),
	substr_count( $src, 'self::ARG_VIEW ),' ),
	substr_count( $body_of( 'render_unlinked_parts' ), 'self::unlinked_label(' ),
	substr_count( $src, "'A student whose name is not recorded yet'" ),
), array( 1, 1, 1, 1 ) );
ck( 'the not-recorded names are plain words the sheet breaks, like every other long name (no `<wbr>`, Task 22b)', has( $unl_member, '<span class="wpcpm-ui-list__name">Una ' . $long_stu . '</span>' ), true );
ck( 'the portrait is decoration beside the name: an empty alt', 1 === preg_match( '#<span class="wpcpm-ui-fold__portrait"><img class="wpcpm-avatar" src="[^"]*student3[^"]*" [^>]*alt="" #', parts_student( $long_on, 'Student3 Example' ) ), true );

// Hostile input, with no surface named and on the Institution Dashboard: a name and a mentor's name holding a script and a double quote, a website and a
// username holding a quote and an angle bracket. Nothing unescaped reaches the page, and no attribute is broken.
$E = 'recINSTEEE0000001';
$GLOBALS['index'][ $E ] = array( 'read' => 1788336000, 'rows' => array(
	'recSTE00000000001' => row( 'recSTE00000000001', 'Ann <script>alert(1)</script> "Q" Example', 'In Sensei', '2026-02-16', array( 'has_mentor' => true, 'reports' => array( 'recREPE0000000001' ), 'username' => 'us"er<x>', 'user_id' => 951 ) ),
	'recSTE00000000002' => row( 'recSTE00000000002', "E\u{0301}va Example", 'In Sensei', '2026-02-16', array( 'has_mentor' => true, 'mentor_name' => '<script>alert(1)</script> "M" Mentor' ) ),
) );
$GLOBALS['umeta'][951] = array(
	'wpcpm_student_program' => array( 'name' => 'Ann', 'program' => 'In Sensei', 'website' => 'https://ex.example.org/"><b>x</b>', 'team' => '<i>Team</i>' ),
	'wpcpm_student_mentor'  => array( 'name' => '<script>alert(1)</script> "M" Mentor' ),
);
$GLOBALS['unlinked'][ $E ] = array( 961 => row( '', 'Uli <script>alert(1)</script> "U"', 'In Sensei', '2026-02-16', array( 'user_id' => 961, 'record_id' => '', 'email' => 'u"<b>@example.org' ) ) );
foreach ( array( 'no surface named' => render( array(), $E, array( 'can_manage' => true ) ), 'the Institution Dashboard' => parts( array(), $E, array( 'can_manage' => true ) ) ) as $branch => $hostile ) {
	$tags = preg_match_all( '/<([a-z0-9]+)[\s>\/]/i', $hostile, $tm ) ? array_values( array_unique( array_map( static function ( $t ) { return strtolower( $t ); }, $tm[1] ) ) ) : array();
	sort( $tags );
	ck( 'hostile input, ' . $branch . ': no script, no bold or italic from the data, no raw quote in a word, no broken attribute', array(
		has( $hostile, '<script' ),
		has( $hostile, '<b>' ) || has( $hostile, '<i>' ) || has( $hostile, '<x>' ),
		has( $hostile, '"Q"' ) || has( $hostile, '"M"' ) || has( $hostile, '"U"' ),
		has( $hostile, '&lt;script&gt;alert(1)&lt;/script&gt;' ),
		// Every attribute is name="value", with no quote left over inside a tag.
		preg_match( '/<[a-z][^<>]*"[^<>"=]*"[^<>=]*>/i', (string) preg_replace( '/\s[a-z-]+="[^"]*"/i', '', $hostile ) ),
		array_values( array_diff( $tags, array( 'a', 'br', 'button', 'caption', 'details', 'div', 'form', 'h2', 'h3', 'h4', 'img', 'input', 'label', 'li', 'option', 'p', 'path', 'section', 'select', 'span', 'summary', 'svg', 'table', 'tbody', 'td', 'th', 'tr', 'ul', 'wbr' ) ) ),
	), array( false, false, false, true, 0, array() ) );
}
$hostile_on = parts( array(), $E );
ck( 'initials are the first character a reader sees: a letter and its combining mark together', has( $hostile_on, '<span class="wpcpm-ui-fold__initials" aria-hidden="true">' . "E\u{0301}" . '</span>' ), true );

// A group is drawn by the parts alone and stands as the Administrator Dashboard's cards do, 10px apart (parts.css): no
// rule of the page's sheet names a group (Task 28, W11: the old markup's gap matched nothing), nor the old switcher, which
// this page's root no longer prints, and the read line is dressed by one rule.
$inst_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );
ck( 'institution.css: no rule for a group or for the old switcher, and one for the read line', array(
	// The group's own class: a modifier such as the unlinked group's (X12's people rows) is not the old gap's rule.
	preg_match_all( '/wpcpm-roster__group(?![\w-])/', $inst_css ),
	preg_match_all( '/wpcpm-dashboard__switcher\b/', $inst_css ),
	preg_match_all( '/^\.wpcpm-institution__read\s*\{/m', $inst_css ),
), array( 0, 0, 1 ) );

// Institution Dashboard, Task 16, fix round 3: the roster and the student's card say the same thing about the same student.
// One map from a program status to a tag tone (`WPCPM_Program::tone()`), used by both: a track green, Pending graduation
// amber, Paused and every other status neutral. The student's card asks the same map (bin/test-institution-student-view.php).
$tone_table = array(
	'In Sensei'          => 'green',
	'In Sensei 50h'      => 'green',
	'Developer Track'    => 'green',
	'Paused'             => '',
	'Pending graduation' => 'amber',
	'Graduate'           => '',
	'Dropped out'        => '',
);
$roster_tones = array();
$saved_ama    = $GLOBALS['umeta'][701]['wpcpm_student_program'];
foreach ( array_keys( $tone_table ) as $st ) {
	$GLOBALS['umeta'][701]['wpcpm_student_program']['program'] = $st;
	$ama_tone = parts_student( parts( array( 'wpcpm_roster_status' => '' ), $D ), 'Ama ' . $long_stu );
	preg_match( '#<span class="wpcpm-ui-fold__end"><span class="wpcpm-status( wpcpm-status--([a-z]+))?">#', $ama_tone, $tm );
	$roster_tones[ $st ] = '' === $ama_tone ? 'not drawn' : ( $tm[2] ?? '' );
}
$GLOBALS['umeta'][701]['wpcpm_student_program'] = $saved_ama;
ck( 'the roster\'s tag tone, status by status, is the one map\'s, and the map is the student\'s card\'s table', array( $roster_tones, array_map( static function ( $st ) { return method_exists( 'WPCPM_Program', 'tone' ) ? WPCPM_Program::tone( $st ) : 'no map'; }, array_combine( array_keys( $tone_table ), array_keys( $tone_table ) ) ) ), array( $tone_table, $tone_table ) );
// 1.123.98: the narrowed list's rows ask the map too, a third call.
ck( 'and the roster writes no tone of its own: its students, its not-recorded rows and its narrowed list ask the map', array( substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php' ), "'green'" ), substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php' ), 'WPCPM_Program::tone(' ) ), array( 0, 3 ) );

// The layout harness's cases (bin/ui/layout): the whole view with thirteen students and the long words, the
// Administrator's filter bar, the enrollment not recorded yet, and a tutor's narrower view.
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Students view, parts: 13 students, 40-character words', parts( array(), $D, array( 'module_titled' => true ) ) );
	$GLOBALS['opts']['permalink_structure'] = '';
	wpcpm_layout_keep( 'Students view, parts: an Administrator\'s filter bar, a filter set', parts( array( 'wpcpm_institution_view' => $D, 'wpcpm_roster_search' => 'Example', 'wpcpm_roster_status' => 'current' ), $D, array( 'can_manage' => true, 'module_titled' => true ) ) );
	$GLOBALS['opts']['permalink_structure'] = '/%postname%/';
	wpcpm_layout_keep( 'Students view, parts: enrollment not recorded yet, an Administrator', parts_section( $unl_manager, 'unlinked' ) );
	wpcpm_layout_keep( 'Students view, parts: a tutor\'s narrower view', $tutor_on );
}

// Gate B, B9: a student's program is a status tag once, in the row's head; the open row's Program row says it in words,
// as a student's card does, and keeps a course status that differs from it as its own tag.
$b9_html = parts( array(), $D, array( 'module_titled' => true ) );
$b9_bad  = array();
if ( preg_match_all( '#<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait".*?</details>#s', $b9_html, $b9_rows ) ) {
	foreach ( $b9_rows[0] as $b9_row ) {
		$b9_head = substr( $b9_row, 0, (int) strpos( $b9_row, '</summary>' ) );
		$b9_cell = preg_match( '#<tr[^>]*wpcpm-roster__row--students-status.*?</tr>#s', $b9_row, $b9_m ) ? $b9_m[0] : '';
		preg_match_all( '#<span class="wpcpm-status[^"]*">([^<]*)</span>#', $b9_head, $b9_h );
		preg_match_all( '#<span class="wpcpm-status[^"]*">([^<]*)</span>#', $b9_cell, $b9_c );
		if ( array_intersect( $b9_h[1], $b9_c[1] ) ) {
			$b9_bad[] = implode( ', ', $b9_h[1] );
		}
	}
}
ck( 'no student\'s row prints its head\'s program tag again in its Program row', array( isset( $b9_rows[0] ) && count( $b9_rows[0] ) > 1, $b9_bad ), array( true, array() ) );

// Gate B, B6: the read line in words for one student, on a student's card; the roster's own words otherwise.
ob_start();
WPCPM_Institution_Roster_View::read_line( 0, 'wpcpm-dashboard__updated', true );
$b6_one = ob_get_clean();
ob_start();
WPCPM_Institution_Roster_View::read_line( 0, 'wpcpm-dashboard__updated' );
$b6_many = ob_get_clean();
ck( 'the read line for one student says "This student", and for the roster "These students"', array( $b6_one, $b6_many ), array( '<p class="wpcpm-dashboard__updated">This student has not been read from the program records yet.</p>', '<p class="wpcpm-dashboard__updated">These students have not been read from the program records yet.</p>' ) );

echo "\n=== 1.123.98: a narrowed list in the roster's place ===\n";

// The dashboard's answers, standing in (its own suite, bin/test-institution-overview.php, holds what each list holds and
// who is offered it): declared here, at run time, so every check above ran with no dashboard class at all.
if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	class WPCPM_Institutions_Dashboard {
		const VIEW_STUDENTS = 'students';
		const ONLY_ARG        = 'wpcpm_only';
		const ONLY_NO_SIGN_IN = 'no-sign-in';
		const ONLY_KEYS     = array( 'no-sign-in', 'ended-open', 'pending-graduation', 'finishing' );
		public static function only_offered( string $key, string $record, $viewer, bool $can_manage ): bool { return ! empty( $GLOBALS['only_offer'][ $key ] ); }
		public static function only_rows( string $record, string $key ): array { return $GLOBALS['only_rows'][ $key ] ?? array(); }
		public static function only_title( string $key, int $count ): string { return 'no-sign-in' === $key ? sprintf( 1 === $count ? '%s student has not signed in yet' : '%s students have not signed in yet', $count ) : 'Some students'; }
		public static function view_url( string $view = 'overview', string $record = '', array $args = array() ): string { return '/institution-dashboard/?wpcpm_view=' . $view . ( '' !== $record ? '&wpcpm_institution_view=' . $record : '' ); }
	}
}
if ( ! class_exists( 'WPCPM_Sign_Ins' ) ) {
	/** The day the sign-in record began (fix round 1, B2), from the suite's global. */
	class WPCPM_Sign_Ins { public static function since() { return (int) ( $GLOBALS['sign_ins_since'] ?? 0 ); } }
}
$GLOBALS['sign_ins_since'] = 1760486400;
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() { return new WP_User( 5 ); }
}
$GLOBALS['only_offer'] = array( 'no-sign-in' => true, 'pending-graduation' => true );
$GLOBALS['only_rows']  = array(
	'no-sign-in' => array(
		'recSTUAAA00000901' => row( 'recSTUAAA00000901', 'Ada Example', 'Developer Track', '2026-02-16', array( 'end' => '2026-09-30', 'user_id' => 701 ) ),
		'recSTUAAA00000902' => row( 'recSTUAAA00000902', 'Bo Example', 'In Sensei', '2026-07-01', array( 'end' => '2026-12-23' ) ),
		'recSTUAAA00000903' => row( 'recSTUAAA00000903', 'Cy Example', 'In Sensei', '', array( 'end' => '2026-12-23' ) ),
	),
);
$GLOBALS['users'][701] = new WP_User( 701 );
$only = parts( array( 'wpcpm_view' => 'students', 'wpcpm_only' => 'no-sign-in' ), $A, array( 'module_titled' => true ) );
ck( 'A8: the sentence in the row\'s words with its count, then Show all students, a quiet link to the Students view', array(
	has( $only, '<section class="wpcpm-roster__view wpcpm-roster__view--only" id="wpcpm-roster"><p class="wpcpm-ui-intro">3 students have not signed in yet.</p>' ),
	// Fix round 3: a link in a line of its own at the text's edge, not a quiet button, whose padding set the words 8px in.
	has( $only, '<p class="wpcpm-ui-intro"><a href="/institution-dashboard/?wpcpm_view=students">Show all students</a></p><ul' ) && ! has( $only, 'wpcpm-btn--quiet' ),
), array( true, true ) );
$GLOBALS['only_rows']['pending-graduation'] = array(
	'recSTUAAA00000904' => row( 'recSTUAAA00000904', 'Di Example', 'Pending graduation', '2026-02-16', array( 'end' => '2026-06-30' ) ),
	// The outside review, 1.123.98: one row names an account that exists, one an account that is gone.
	'recSTUAAA00000905' => row( 'recSTUAAA00000905', 'Eli Example', 'Pending graduation', '2026-02-16', array( 'end' => '2026-06-30', 'user_id' => 702 ) ),
	'recSTUAAA00000906' => row( 'recSTUAAA00000906', 'Fay Example', 'Pending graduation', '2026-02-16', array( 'end' => '2026-06-30', 'user_id' => 799 ) ),
);
$GLOBALS['users'][702] = new WP_User( 702 );
$only_pending = parts( array( 'wpcpm_only' => 'pending-graduation' ), $A, array( 'module_titled' => true ) );
ck( 'a row whose account is gone has no card to open: its name is drawn without a link, and a row whose account exists keeps its link', array( false !== strpos( $only_pending, 'wpcpm_institution_student=702' ), false !== strpos( $only_pending, 'wpcpm_institution_student=799' ), false !== strpos( $only_pending, 'Fay Example' ) ), array( true, false, true ) );
unset( $GLOBALS['only_rows']['pending-graduation'] );
// Fix round 1, B2: on the no-sign-in list alone, one muted line under the sentence and before Show all students: the day the
// record began, kept whole, the record's and no student's.
ck( 'B2: the no-sign-in list says which accounts are not counted, with the day the record began, under the sentence; another list does not', array(
	has( $only, '<p class="wpcpm-ui-intro">3 students have not signed in yet.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Accounts made before <span class="wpcpm-ui-whole">' . gmdate( 'F j, Y', 1760486400 ) . '</span> are not counted: the site kept no sign-in record before then.</p><p class="wpcpm-ui-intro"><a href=' ),
	has( $only_pending, 'Accounts made before' ),
	has( $only_pending, '<p class="wpcpm-ui-intro">Some students.</p><p class="wpcpm-ui-intro"><a href=' ),
), array( true, false, true ) );
ck( 'one list on the parts\' rows, a student per row, and no filter bar, no group, no cohort picker', array(
	substr_count( $only, '<ul class="wpcpm-ui-list wpcpm-ui-list--under">' ),
	substr_count( $only, '<li class="wpcpm-ui-list__row">' ),
	has( $only, 'wpcpm-roster__group' ) || has( $only, '<form' ) || has( $only, 'wpcpm_cohort' ),
), array( 1, 3, false ) );
preg_match_all( '#<li class="wpcpm-ui-list__row">.*?</li>#s', $only, $only_rows );
ck( 'a row: the name to the student\'s card where there is an account, the program as the roster\'s tag, the cohort and the dates', array(
	has( $only_rows[0][0], '<span class="wpcpm-ui-list__name"><a href="/institution-dashboard/?wpcpm_view=students&#038;wpcpm_only=no-sign-in&#038;wpcpm_institution_student=701">Ada Example</a></span>' ) || has( $only_rows[0][0], 'wpcpm_institution_student=701">Ada Example</a></span>' ),
	1 === preg_match( '#<span class="wpcpm-status[^"]*">#', $only_rows[0][0] ),
	has( $only_rows[0][0], '<span class="wpcpm-ui-item__fact">January to June 2026</span>' ),
	has( $only_rows[0][1], '<span class="wpcpm-ui-list__name">Bo Example</span>' ),
	has( $only_rows[0][2], '<span class="wpcpm-ui-list__name">Cy Example</span>' ) && has( $only_rows[0][2], 'No start date' ) && 1 === substr_count( $only_rows[0][2], 'No start date' ),
), array( true, true, true, true, true ) );
ck( 'an unknown key, a key not offered (a tutor\'s ended-open) and an empty list draw the ordinary roster', array(
	has( parts( array( 'wpcpm_only' => 'everyone' ), $A, array( 'module_titled' => true ) ), 'wpcpm-roster__group' ),
	has( parts( array( 'wpcpm_only' => 'ended-open' ), $A, array( 'module_titled' => true ) ), 'wpcpm-roster__group' ),
	has( parts( array( 'wpcpm_only' => 'pending-graduation' ), $A, array( 'module_titled' => true ) ), 'wpcpm-roster__group' ),
), array( true, true, true ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Students view, parts, 1.123.98: the narrowed list of students with no sign-in on record', $only );
}

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
