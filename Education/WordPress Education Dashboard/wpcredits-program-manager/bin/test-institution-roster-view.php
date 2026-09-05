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
	'date_format'         => 'Y-m-d',
	'time_format'         => 'H:i',
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
function esc_url( $s ) { return str_replace( '&', '&#038;', (string) $s ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
// Core's own, minus the locale: it pads to the number of places it is given, which is what
// makes the difference between "150 of 150" and "150.00 of 150" a thing this suite can see.
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function get_user_by( $field, $value ) { return false; }
function get_queried_object_id() { return 12; }
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
			'fields'      => $allowed ? null : array(),
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
 * empty `reports` list deciding waiting from current, everything else the residue.
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
				$groups[ empty( $row['reports'] ) ? 'waiting' : 'current' ][ $key ] = $row;
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

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';

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

/** The number in the count badge beside a heading. */
function group_count( $html, $label ) {
	$pattern = '/' . preg_quote( $label, '/' ) . ' <span class="wpcpm-group__count">(\d+)<\/span>/';
	return preg_match( $pattern, $html, $m ) ? (int) $m[1] : null;
}

/** One select's options, in the order they are printed. */
function options( $html, $name ) {
	if ( ! preg_match( '/<select name="' . preg_quote( $name, '/' ) . '"[^>]*>(.*?)<\/select>/s', $html, $m ) ) { return null; }
	preg_match_all( '/<option value="([^"]*)"[^>]*>(.*?)<\/option>/s', $m[1], $all, PREG_SET_ORDER );
	$out = array();
	foreach ( $all as $option ) { $out[] = $option[2]; }
	return $out;
}

/** Which of one select's options is marked selected. */
function chosen( $html, $name ) {
	if ( ! preg_match( '/<select name="' . preg_quote( $name, '/' ) . '"[^>]*>(.*?)<\/select>/s', $html, $m ) ) { return null; }
	return preg_match( "/<option value=\"([^\"]*)\" selected='selected'/", $m[1], $s ) ? $s[1] : '';
}

/** The actions paragraph of the filter bar: the Show button and whatever sits beside it. */
function actions( $html ) {
	return preg_match( '/<p class="wpcpm-roster__actions">(.*?)<\/p>/s', $html, $m ) ? $m[1] : '';
}

/** The export link's address with its entities decoded, or '' when there is no export link. */
function export_href( $html ) {
	if ( ! preg_match( '/<a class="wpcpm-roster__export" href="([^"]*)"/', $html, $m ) ) { return ''; }
	return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
}

/** The export link's query arguments. */
function export_args( $html ) {
	list( , $args ) = wpcpm_test_split( export_href( $html ) );
	return $args;
}

/** Every table row of one group, as plain markup. */
function group_rows( $html, $key ) {
	if ( ! preg_match( '/wpcpm-roster__group--' . preg_quote( $key, '/' ) . '">(.*?)<\/section>/s', $html, $m ) ) { return ''; }
	return $m[1];
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
				'username' => 'adaexample', 'email' => 'maciej@a8c.com',
				'tutor' => 'Tutor Example', 'field_of_study' => 'Technology & Engineering',
				'user_id' => 21,
				// The Students table carries this column; the index drops it and the roster
				// must never print it whatever a caller hands over.
				'accessibility' => 'Screen reader user',
			) ),
			// No username and no account email: the row an avatar cannot be drawn for.
			'recSTU00000000002' => row( 'recSTU00000000002', 'Bo Example', 'In Sensei', '2026-02-16', array( 'has_mentor' => true, 'email' => '' ) ),
			'recSTU00000000003' => row( 'recSTU00000000003', 'Cy Example', 'In Sensei 50h', '2026-03-01' ),
			'recSTU00000000004' => row( 'recSTU00000000004', 'Dee Example', 'Graduate', '2026-01-20' ),
			'recSTU00000000005' => row( 'recSTU00000000005', 'Eve Example', 'Not moving forward', '2026-02-16' ),
			'recSTU00000000006' => row( 'recSTU00000000006', 'Spammy Example', 'SPAM', '2026-02-16' ),
			'recSTU00000000007' => row( 'recSTU00000000007', 'Dup Example', 'Duplicated', '2026-02-16' ),
			'recSTU00000000008' => row( 'recSTU00000000008', 'Fi Example', 'Interested', '2026-02-16' ),
			// An account email and no WordPress.org username: enough for the old fallback to
			// leak a Gravatar, which is exactly what this row is here to catch. Interested keeps
			// her out of the signed-up count the same way Fi is, so the cohort's (5) and the
			// strip's two numbers do not move.
			'recSTU00000000013' => row( 'recSTU00000000013', 'Lu Example', 'Interested', '2026-02-16', array( 'email' => 'maciej@a8c.com' ) ),
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

ck( 'Current holds the student with a report record', group_count( $html, 'Current' ), 1 );
ck( 'Waiting for a mentor holds both students without one', group_count( $html, 'Waiting for a mentor' ), 2 );
ck( 'Finished holds the graduate', group_count( $html, 'Finished' ), 1 );
ck( 'Did not start holds the applicant and both leads', group_count( $html, 'Did not start' ), 3 );
ck( 'Ada is in Current', has( group_rows( $html, 'current' ), 'Ada Example' ), true );
ck( 'Bo is not', has( group_rows( $html, 'current' ), 'Bo Example' ), false );
ck( 'Bo is waiting, and the row says a mentor is assigned',
	has( group_rows( $html, 'waiting' ), 'A mentor is assigned. The report record has not been created yet.' ), true );
ck( 'Cy is waiting with no mentor at all', has( group_rows( $html, 'waiting' ), 'No mentor yet.' ), true );
// Every group is a disclosure, so the same chevron is on every row of the page. The two a
// school works from start open; the two it reads rarely start closed. The group, not its
// students: since the roster became the Mentor Report Card's component every student is a
// disclosure of its own, so the assertions name the group's own tag.
ck( 'all four groups are disclosures', substr_count( $html, '<details class="wpcpm-group__disclosure"' ), 4 );
ck( 'Current starts open', has( group_rows( $html, 'current' ), '<details class="wpcpm-group__disclosure" open>' ), true );
ck( 'so does Waiting', has( group_rows( $html, 'waiting' ), '<details class="wpcpm-group__disclosure" open>' ), true );
ck( 'Finished starts closed', has( group_rows( $html, 'finished' ), '<details class="wpcpm-group__disclosure">' ), true );
ck( 'and so does Did not start', has( group_rows( $html, 'not_started' ), '<details class="wpcpm-group__disclosure">' ), true );

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

// Named for the Current group, not "somewhere on the page": Finished and Did not start are
// disclosures whatever their length, so counting them would pass with the ceiling deleted.
ck( 'a group longer than the ceiling starts closed', has( group_rows( $long, 'current' ), '<details class="wpcpm-group__disclosure">' ), true );
ck( 'and not open', has( group_rows( $long, 'current' ), '<details class="wpcpm-group__disclosure" open>' ), false );
ck( 'and says how many are inside it without being opened', has( $long, '>' . ( WPCPM_Institution_Roster_View::OPEN_MAX + 1 ) . '<' ), true );
ck( 'the students are still there, one card each, for search and for the browser to find',
    substr_count( $long, 'wpcpm-mentee__disclosure' ), WPCPM_Institution_Roster_View::OPEN_MAX + 1 );

$GLOBALS['index']['recINSTAAA0000001'] = $seeded;
ck( 'though each of its students is a card that opens', substr_count( group_rows( $html, 'current' ), 'wpcpm-mentee__disclosure' ), 1 );
ck( 'drawn with the mentor card\'s own classes, so the two pages cannot drift apart',
    array(
		has( group_rows( $html, 'current' ), 'wpcpm-mentee__summary' ),
		has( group_rows( $html, 'current' ), 'wpcpm-mentee__table' ),
		has( group_rows( $html, 'current' ), 'wpcpm-mentee__value' ),
	),
    array( true, true, true ) );
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

// The avatar sits before the name, the same shape the mentor page draws it in - one
// student, one face, on both pages (consistency pass, 1.94.7).
ck( 'a student row carries the same 44px avatar as the mentor page, before the name',
	preg_match( '#<summary class="wpcpm-mentee__summary"><img class="wpcpm-avatar" src="[^"]*adaexample[^"]*" srcset="[^"]*" width="44" height="44" alt="[^"]*" title="[^"]*" loading="lazy" decoding="async" /><div class="wpcpm-mentee__identity">#', $html ) === 1, true );
ck( 'a row with neither a username nor an account email prints no avatar',
	preg_match( '#<summary class="wpcpm-mentee__summary"><div class="wpcpm-mentee__identity"><h4 class="wpcpm-mentee__name">Bo Example#', $html ) === 1, true );
// Lu has an account email and no username: the case the old fallback used to draw a Gravatar
// for, keyed on the address the Institution Dashboard otherwise never shows a member.
ck( 'a row with an account email but no username prints no avatar either',
	preg_match( '#<summary class="wpcpm-mentee__summary"><div class="wpcpm-mentee__identity"><h4 class="wpcpm-mentee__name">Lu Example#', $html ) === 1, true );
ck( 'so nothing on the page is a Gravatar URL a member could read as a lookup key',
	has( $html, 'gravatar' ), false );

ck( 'the program is the name people use, with its badge',
	has( $ada, '<span class="wpcpm-badge wpcpm-badge--sensei">WordPress Credits Program 150h</span>' ), true );
ck( 'the dates come from the mentor card formatter', has( $ada, '2026-02-16 to 2026-09-30' ), true );
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
function hours_of( $html, $group = 'current' ) {
	if ( ! preg_match( '/wpcpm-roster__row--reports-hours">.*?<td class="wpcpm-mentee__value"[^>]*>(.*?)<\/td>/s', group_rows( $html, $group ), $m ) ) {
		return null;
	}

	return trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
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
ck( 'and the heading is printed even so', has( group_rows( render(), 'current' ), '>Hours</span>' ), true );

// The index, for the students who have never signed in here - which is most of a school's
// roster. Bo has no account at all, so the cached block cannot be the source, and the track the
// target comes from has to fall back to the Students row's own status.
$GLOBALS['index'][ $A ]['rows']['recSTU00000000002']['hours'] = '12';

ck( 'a student with no account gets their hours off the index', hours_of( render(), 'waiting' ), '12 of 150' );

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

ck( 'the chosen semester is named', has( $html, '<h3 class="wpcpm-roster__strip-title">January to June 2026</h3>' ), true );
ck( 'its two numbers: signed up', has( $html, '5 students signed up.' ), true );
ck( 'its two numbers: graduated', has( $html, '1 has graduated.' ), true );
ck( 'the lead is not counted as somebody who signed up',
	WPCPM_Cohort::participation( WPCPM_Roster_Index::rows( $A ), '2026-H1' )['signed_up'], 5 );
ck( 'though the roster still shows her, under Did not start',
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

ck( 'the strip prints when the counts were read',
	has( $html, '<p class="wpcpm-roster__read wpcpm-roster__read--strip">Read from the program records on 2026-09-02 08:00 (2 hours ago).</p>' ), true );
ck( 'and the footer prints it again under the table',
	has( $html, '<p class="wpcpm-roster__read wpcpm-roster__read--footer">Read from the program records on 2026-09-02 08:00 (2 hours ago).</p>' ), true );
ck( 'a read time the caller hands over is the one printed',
	has( render( array(), $A, array( 'read' => 1787500000 ) ), '2026-08-23 15:46' ), true );

echo "\n=== Not yet in the Students table ===\n";

ck( 'the fifth list is absent when there is nobody in it', has( $html, 'Not yet in the Students table' ), false );

$GLOBALS['unlinked'][ $A ] = array(
	31 => row( '', 'Nia Example', 'In Sensei', '2026-02-16', array( 'user_id' => 31, 'record_id' => '' ) ),
);
$with_fifth = render();

ck( 'it appears when there is somebody in it', group_count( $with_fifth, 'Not yet in the Students table' ), 1 );
ck( 'with the person named', has( $with_fifth, 'Nia Example' ), true );
ck( 'and the explanation of whose job the missing record is',
	has( $with_fifth, 'A program manager needs to complete the record.' ), true );
ck( 'they are not in any of the four groups', group_count( $with_fifth, 'Current' ), 1 );

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

echo "\n=== The filter bar ===\n";

$waiting = render( array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_status' => 'waiting' ) );

ck( 'a group filter narrows to that group', group_count( $waiting, 'Waiting for a mentor' ), 2 );
ck( 'and the other three are not drawn', group_count( $waiting, 'Current' ), null );
ck( 'the fifth list stays out of a request for one of the four',
	has( $waiting, 'Not yet in the Students table' ), false );
ck( 'the filter comes back selected in the form', chosen( $waiting, 'wpcpm_roster_status' ), 'waiting' );
ck( 'and can be cleared without losing the cohort',
	has( $waiting, 'href="/institution-dashboard/?wpcpm_cohort=2026-H1">Clear the filters' ), true );

$search = render( array( 'wpcpm_roster_search' => 'ada' ) );

ck( 'a search narrows the groups before they are drawn', group_count( $search, 'Current' ), 1 );
ck( 'to the matching student', has( group_rows( $search, 'current' ), 'Ada Example' ), true );
ck( 'and empties the ones with no match', group_count( $search, 'Waiting for a mentor' ), 0 );
ck( 'which say why they are empty', has( $search, 'No students in this group match the filters.' ), true );
ck( 'the term survives in the field', has( $search, 'name="wpcpm_roster_search" value="ada"' ), true );

$by_tutor = render( array( 'wpcpm_roster_search' => 'tutor example' ) );

ck( 'the search reads the tutor as well as the name', group_count( $by_tutor, 'Current' ), 1 );

$filtered = render( array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_search' => 'ada' ) );

ck( 'a filtered roster is a URL: the detail link carries the cohort and the search',
	has( $filtered, 'wpcpm_cohort=2026-H1&#038;wpcpm_roster_search=ada&#038;wpcpm_institution_student=21' ), true );
ck( 'no filter is set, so nothing offers to clear one', has( $html, 'Clear the filters' ), false );

$junk = render( array( 'wpcpm_cohort' => 'summer', 'wpcpm_roster_status' => 'everyone' ) );

ck( 'a cohort that is not a key falls back to the default', chosen( $junk, 'wpcpm_cohort' ), '2026-H1' );
ck( 'a group that is not one of the four is no filter at all', chosen( $junk, 'wpcpm_roster_status' ), '' );
ck( 'so every group is drawn', group_count( $junk, 'Did not start' ), 3 );

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
ck( 'and draws the link when the answer is yes', has( $exporting, 'class="wpcpm-roster__export"' ), true );

// Beside Show, in the paragraph the spec puts the roster's actions in, rather than adrift
// above the groups where it would read as a heading.
ck( 'it sits in the actions paragraph beside Show',
	array( has( actions( $exporting ), '>Show</button>' ), has( actions( $exporting ), 'wpcpm-roster__export' ) ),
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

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
