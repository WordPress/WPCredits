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
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
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
	public static function subject_institution( $record_id ) {
		return array( 'type' => 'institution', 'id' => (string) $record_id, 'institution_ids' => array( (string) $record_id ), 'evidence' => 'index' );
	}
	public static function decide( $action, array $subject, $user = null ) {
		$GLOBALS['decisions'][] = array( $action, $subject['id'] );
		return array(
			'allowed'     => (bool) $GLOBALS['allowed'],
			'ground'      => $GLOBALS['allowed'] ? 'member' : '',
			'institution' => $GLOBALS['allowed'] ? (string) $subject['id'] : '',
			'fields'      => $GLOBALS['allowed'] ? null : array(),
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

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';

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
				'username' => 'adaexample', 'tutor' => 'Tutor Example', 'field_of_study' => 'Technology & Engineering',
				'user_id' => 21,
				// The Students table carries this column; the index drops it and the roster
				// must never print it whatever a caller hands over.
				'accessibility' => 'Screen reader user',
			) ),
			'recSTU00000000002' => row( 'recSTU00000000002', 'Bo Example', 'In Sensei', '2026-02-16', array( 'has_mentor' => true ) ),
			'recSTU00000000003' => row( 'recSTU00000000003', 'Cy Example', 'In Sensei 50h', '2026-03-01' ),
			'recSTU00000000004' => row( 'recSTU00000000004', 'Dee Example', 'Graduate', '2026-01-20' ),
			'recSTU00000000005' => row( 'recSTU00000000005', 'Eve Example', 'Not moving forward', '2026-02-16' ),
			'recSTU00000000006' => row( 'recSTU00000000006', 'Spammy Example', 'SPAM', '2026-02-16' ),
			'recSTU00000000007' => row( 'recSTU00000000007', 'Dup Example', 'Duplicated', '2026-02-16' ),
			'recSTU00000000008' => row( 'recSTU00000000008', 'Fi Example', 'Interested', '2026-02-16' ),
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

$html = render();

echo "=== The fence, and the shape of the page ===\n";

ck( 'the roster asks the fence before it draws anything', $GLOBALS['decisions'], array( array( 'view_roster', $A ) ) );
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
ck( 'Did not start holds the applicant and the lead', group_count( $html, 'Did not start' ), 2 );
ck( 'Ada is in Current', has( group_rows( $html, 'current' ), 'Ada Example' ), true );
ck( 'Bo is not', has( group_rows( $html, 'current' ), 'Bo Example' ), false );
ck( 'Bo is waiting, and the row says a mentor is assigned',
	has( group_rows( $html, 'waiting' ), 'A mentor is assigned. The report record has not been created yet.' ), true );
ck( 'Cy is waiting with no mentor at all', has( group_rows( $html, 'waiting' ), 'No mentor yet.' ), true );
ck( 'the collapsed groups are disclosures', substr_count( $html, '<details class="wpcpm-group__disclosure">' ), 2 );
// The group, not its students. Since the roster became the Mentor Report Card's component
// every student is a disclosure of its own, so "no <details> in here" would now be false for
// a reason that has nothing to do with whether the group is collapsed.
ck( 'and Current is not one of them', has( group_rows( $html, 'current' ), 'wpcpm-group__disclosure' ), false );

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
ck( 'a group longer than the ceiling starts closed', has( group_rows( $long, 'current' ), 'wpcpm-group__disclosure' ), true );
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
	'Student', 'Program', 'Dates', 'Days left', 'Mentor', 'WordPress.org', 'Team', 'Website', 'Field of study', 'Tutor',
) );
ck( 'and none of them names accessibility',
	preg_grep( '/accessib/i', array_keys( WPCPM_Institution_Roster_View::columns() ) ), array() );

echo "\n=== One student's row ===\n";

$ada = group_rows( $html, 'current' );

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
ck( 'so every group is drawn', group_count( $junk, 'Did not start' ), 2 );

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

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
