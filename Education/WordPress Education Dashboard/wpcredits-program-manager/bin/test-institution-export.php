<?php
/**
 * The two CSV exports: the roster file and the single-student file.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The file opens with a UTF-8 BOM.** Excel reads a BOM-less UTF-8 CSV as the machine's
 *   legacy code page, so the fixture's "Ana Fidelitas" - accented, like most names in this
 *   program - comes out mangled for every school in Latin America and half of Europe. Three
 *   bytes, written once, before anything else.
 * - **A cell that begins `=`, `+`, `-` or `@` is prefixed with an apostrophe.** Those four
 *   begin a formula in every spreadsheet the file will be opened in. The import refuses such a
 *   value at the door; an export cannot, because the value is already in the base and was put
 *   there by somebody nobody can go back and ask, so it is neutralised instead. The fixture
 *   holds one such name, from before the import's rule existed.
 * - **`accessibility` is in no column and no cell of either file.** The fixture student carries
 *   a disclosure on her index row *and* inside her cached `wpcpm_student_program` block, which
 *   is the block two of these columns are read out of. It was disclosed to the program to be
 *   accommodated, not to the school.
 * - **Course grades are in the single-student file and in no other.** They are on one screen
 *   and in one file by design; a roster export carrying them would hand a school a grade list
 *   of a whole cohort for one click.
 * - The rows are `WPCPM_Roster_Index::groups()`'s rows, so SPAM and Duplicated are dropped by
 *   the index's own contract and the cohort narrows before anything is written. The file and
 *   the screen are the same list or they are two lists that will disagree.
 * - Both files have the same shape, so one can be filed under the other.
 * - The fence is asked with `ACT_EXPORT` and its answer is passed through `scope()`, so a
 *   ground that one day narrows fields narrows the files too.
 *
 * Run from the plugin root:  php bin/test-institution-export.php
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

$GLOBALS['today']   = '2026-09-03';
$GLOBALS['umeta']   = array();
$GLOBALS['index']   = array();
$GLOBALS['allowed'] = true;
$GLOBALS['fields']  = null;
$GLOBALS['asked']   = array();

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '', $email = '' ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
	}
	public function exists() { return $this->ID > 0; }
}

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function wp_date( $f, $t = null, $z = null ) {
	if ( 'Y-m-d' === $f && null === $t ) { return $GLOBALS['today']; }
	return gmdate( $f, null === $t ? time() : (int) $t );
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

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

/** The fence. Every call is recorded, so the action the export names can be asserted. */
class WPCPM_Institution_Policy {
	const ACT_EXPORT = 'export';
	public static function subject_institution( $record_id ) {
		return array( 'type' => 'institution', 'id' => (string) $record_id, 'institution_ids' => array( (string) $record_id ), 'evidence' => 'index' );
	}
	public static function subject_student_account( $user_id ) {
		return array( 'type' => 'student', 'id' => (int) $user_id, 'institution_ids' => array(), 'evidence' => 'cache' );
	}
	public static function decide( $action, array $subject, $user = null ) {
		$GLOBALS['asked'][] = array( $action, $subject['id'] );
		return array(
			'allowed'     => (bool) $GLOBALS['allowed'],
			'ground'      => $GLOBALS['allowed'] ? 'member' : '',
			'institution' => $GLOBALS['allowed'] ? (string) $subject['id'] : '',
			'fields'      => $GLOBALS['allowed'] ? $GLOBALS['fields'] : array(),
			'why'         => '',
		);
	}
	/** The real one, byte for byte: the order is the caller's and null means everything. */
	public static function scope( array $decision, array $keyed ) {
		if ( empty( $decision['allowed'] ) || ! array_key_exists( 'fields', $decision ) ) { return array(); }
		if ( null === $decision['fields'] ) { return $keyed; }
		$permitted = array();
		foreach ( (array) $decision['fields'] as $key ) { $permitted[ (string) $key ] = true; }
		return array_intersect_key( $keyed, $permitted );
	}
	public static function refusal() { return new WP_Error( 'wpcpm_inst_unknown', 'That record is not on your roster.' ); }
}

/**
 * The roster index, stubbed to its contract.
 *
 * `groups()` is a faithful miniature of the real one: the cohort filter first, SPAM and
 * Duplicated dropped outright, the two tracked lists deciding current from finished and an
 * empty `reports` list deciding waiting from current, everything else the residue. The export
 * reads nothing else, which is the point of stubbing it this way rather than returning a list.
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
}

class WPCPM_Institutions_Index {
	public static function row( $record_id ) {
		return array( 'record_id' => (string) $record_id, 'name' => 'Universidad Example' );
	}
}

class WPCPM_Institution_Roster {
	const TYPE_REPORT = 'report';
	public static function resolve_institution( $viewer, $can_manage ) { return $GLOBALS['acting'] ?? ''; }
	public static function claim( $record, $action, $type = 'student', $user = null ) {
		return WPCPM_Institution_Policy::refusal();
	}
}

class WPCPM_Mentor_Calls {
	public static function student_record( $user_id ) { return ''; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-export.php';

$fail  = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label    What is being checked, as a sentence.
 * @param mixed  $actual   What the code returned.
 * @param mixed  $expected What it should have returned.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;

	++$total;
	$ok = $actual === $expected;

	if ( ! $ok ) { $fail++; }

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";

	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/* ---- reading a written file back ---------------------------------------- */

/**
 * A written file parsed back into rows, the way a spreadsheet would read it.
 *
 * The BOM is stripped here rather than ignored, so the assertions about it are the ones that
 * test it and no other assertion accidentally passes because of it.
 *
 * @param string $csv The file.
 * @return array[] Rows of cells.
 */
function rows_of( $csv ) {
	$body = ( 0 === strpos( $csv, WPCPM_Institution_Export::BOM ) ) ? substr( $csv, 3 ) : $csv;
	$body = rtrim( $body, "\r\n" );

	if ( '' === $body ) { return array(); }

	$out = array();

	foreach ( explode( "\r\n", $body ) as $line ) { $out[] = str_getcsv( $line, ',', '"', '' ); }

	return $out;
}

/** One column of a parsed file, by its position in the header row. */
function column_of( array $rows, $index ) {
	$out = array();

	foreach ( array_slice( $rows, 1 ) as $row ) { $out[] = $row[ $index ] ?? null; }

	return $out;
}

/** Where a heading sits in the header row, or -1. */
function heading_at( array $rows, $heading ) {
	$found = isset( $rows[0] ) ? array_search( $heading, $rows[0], true ) : false;

	return false === $found ? -1 : (int) $found;
}

function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }

/* ---- the fixture -------------------------------------------------------- */

$A = 'recINSTAAA0000001';

$GLOBALS['acting'] = $A;

/**
 * One index row, in the shape the students sync writes.
 *
 * @param string $record Students record ID.
 * @param string $name   Full name.
 * @param string $status Airtable status.
 * @param string $start  Start date.
 * @param array  $extra  Overrides.
 * @return array
 */
function row( $record, $name, $status, $start, array $extra = array() ) {
	return $extra + array(
		'record_id'      => $record,
		'name'           => $name,
		'email'          => strtolower( str_replace( ' ', '.', $name ) ) . '@example.test',
		'email_key'      => strtolower( str_replace( ' ', '.', $name ) ) . '@example.test',
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
	);
}

$GLOBALS['index'][ $A ] = array(
	'read' => 1756900000,
	'rows' => array(
		'recSTU00000000001' => row(
			'recSTU00000000001',
			'Ana Fidelitas',
			'In Sensei',
			'2026-02-10',
			array(
				'reports'        => array( 'recREP00000000001' ),
				'user_id'        => 11,
				'username'       => 'anafi',
				'field_of_study' => 'Computer Science',
				'tutor'          => 'Dr. Ruiz',
				'end'            => '2026-08-10',
				// The disclosure, on the row itself. The real index's `clean()` drops it; this
				// stub deliberately does not, because what is being asserted is that the export
				// never prints it whatever the row it is handed carries.
				'accessibility'  => 'Uses a screen reader',
			)
		),
		'recSTU00000000002' => row( 'recSTU00000000002', 'Bruno Kowalski', 'In Sensei 50h', '2026-02-11' ),
		// A name from before the import refused one starting with a formula character. It is in
		// the base, it belongs to a real person, and it has to come out of the export as text.
		'recSTU00000000003' => row(
			'recSTU00000000003',
			'=cmd|\' /C calc\'!A0',
			'Graduate',
			'2025-09-01',
			array( 'reports' => array( 'recREP00000000003' ), 'user_id' => 13 )
		),
		'recSTU00000000004' => row( 'recSTU00000000004', 'Dana Nowak', 'Not moving forward', '' ),
		'recSTU00000000005' => row( 'recSTU00000000005', 'Spammer', 'SPAM', '2026-02-10' ),
		'recSTU00000000006' => row( 'recSTU00000000006', 'Twice Over', 'Duplicated', '2026-02-10' ),
		'recSTU00000000007' => row(
			'recSTU00000000007',
			'Ewa Zielinska',
			'In Sensei',
			'2026-02-12',
			array( 'reports' => array( 'recREP00000000007' ), 'user_id' => 14, 'tutor' => '-Smith' )
		),
	),
);

$GLOBALS['umeta'][11] = array(
	WPCPM_Students_Sync::META_PROGRAM => array(
		'name'          => 'Ana Fidelitas',
		'email'         => 'ana.fidelitas@example.test',
		'program'       => 'In Sensei',
		'team'          => 'Documentation, Polyglots',
		'website'       => 'https://ana.example.test/',
		// One array key away from the two that are read. A loop over this block would print it.
		'accessibility' => 'Uses a screen reader',
	),
	WPCPM_Students_Sync::META_MENTOR  => array( 'name' => 'Marta Mentor', 'email' => 'marta@example.test' ),
);

$GLOBALS['umeta'][14] = array(
	WPCPM_Students_Sync::META_PROGRAM => array( 'program' => 'In Sensei', 'team' => '', 'website' => '' ),
);

// A student the Students table has no row for at all: the dashboard's fifth list. Their card is
// drawn from the program block alone, and so is their export.
$GLOBALS['umeta'][21] = array(
	WPCPM_Students_Sync::META_PROGRAM => array(
		'name'    => 'Iker Reports-Only',
		'program' => 'In Sensei 50h',
		'team'    => 'Training',
		'website' => 'https://iker.example.test/',
		'start'   => '2026-03-01',
	),
);

/** The decision the fence would return for a member of this institution. */
function allowed_for( $record ) {
	return WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EXPORT, WPCPM_Institution_Policy::subject_institution( $record ) );
}

echo "\n=== The BOM, and one file per call ===\n";

$csv = WPCPM_Institution_Export::csv( array( array( 'Student' ), array( 'Ana' ) ) );

// Three bytes, and they are the first three. A file that carries them anywhere else carries
// them nowhere useful: Excel looks at the start of the file and nowhere else.
ck( 'the file opens with the UTF-8 byte order mark', substr( $csv, 0, 3 ), "\xEF\xBB\xBF" );
ck( 'the mark is EF BB BF and not another encoding\'s', bin2hex( substr( $csv, 0, 3 ) ), 'efbbbf' );

// One BOM. A second one inside the file is two characters a spreadsheet prints in a cell.
ck( 'the mark appears exactly once', substr_count( $csv, WPCPM_Institution_Export::BOM ), 1 );
ck( 'the heading follows it immediately', substr( $csv, 3, 7 ), 'Student' );

// RFC 4180 says CRLF, and Notepad and a handful of Windows tools show a bare-newline file as
// one very long line. A school that opens the file in the wrong thing first should still see rows.
ck( 'rows end CRLF', substr_count( $csv, "\r\n" ), 2 );
ck( 'and the file ends with one', substr( $csv, -2 ), "\r\n" );

echo "\n=== Formula characters are neutralised, never refused ===\n";

// Each of the four begins a formula in Excel, LibreOffice and Google Sheets alike. The apostrophe
// is the one prefix all three read as "the rest of this is text", and all three strip it again on
// display, so the school sees the value that is in the base.
ck( 'a leading equals sign is prefixed', WPCPM_Institution_Export::cell( '=1+1' ), "'=1+1" );
ck( 'a leading plus is prefixed', WPCPM_Institution_Export::cell( '+1' ), "'+1" );
ck( 'a leading hyphen is prefixed', WPCPM_Institution_Export::cell( '-Smith' ), "'-Smith" );
ck( 'a leading at sign is prefixed', WPCPM_Institution_Export::cell( '@SUM(A1)' ), "'@SUM(A1)" );

// The value survives the prefix. Neutralising is not sanitising: nothing is dropped, so the
// school can still read what the base holds and a program manager can still find the row.
ck( 'the original text is kept whole after the prefix', substr( WPCPM_Institution_Export::cell( '=cmd|x' ), 1 ), '=cmd|x' );

// Excel trims a cell before deciding whether it is a formula, so a first-character test alone
// would let a space in front of the equals sign through - the same attack with padding.
ck( 'a space before the equals sign does not get it past', WPCPM_Institution_Export::cell( ' =1+1' ), "' =1+1" );
ck( 'nor does a tab', WPCPM_Institution_Export::cell( "\t=1+1" ), "'\t=1+1" );
ck( 'nor a carriage return', WPCPM_Institution_Export::cell( "\r=1+1" ), "'\r=1+1" );

// An address is the common value holding an at sign, and it holds it in the middle. Prefixing
// every address would put an apostrophe in front of every mentor's email on every export.
ck( 'an at sign anywhere but the front is left alone', WPCPM_Institution_Export::cell( 'ana@example.test' ), 'ana@example.test' );
ck( 'so is a hyphen inside a date', WPCPM_Institution_Export::cell( '2026-02-10' ), '2026-02-10' );
ck( 'and an ordinary name is untouched', WPCPM_Institution_Export::cell( 'Ana Fidelitas' ), 'Ana Fidelitas' );

ck( 'an empty cell stays empty rather than becoming an apostrophe', WPCPM_Institution_Export::cell( '' ), '' );
ck( 'whitespace alone is not a formula', WPCPM_Institution_Export::cell( '   ' ), '   ' );
ck( 'an array is a cell with nothing in it, not a warning', WPCPM_Institution_Export::cell( array( 'x' ) ), '' );
ck( 'a number is written as itself', WPCPM_Institution_Export::cell( 87.5 ), '87.5' );

// Headings go through the same function, because a translation is somebody else's string: the
// day a locale renders a heading starting with a hyphen, the file must still be a table.
$headed = rows_of( WPCPM_Institution_Export::csv( array( array( '-Total', 'Student' ), array( 'x', 'y' ) ) ) );
ck( 'a heading is neutralised like any other cell', $headed[0][0], "'-Total" );

echo "\n=== Quoting is fputcsv's, not a hand-rolled join ===\n";

$quoted = WPCPM_Institution_Export::csv( array( array( 'Docs, Polyglots', 'say "hi"', 'C:\\path\\', "line\nbreak" ) ) );
$read   = str_getcsv( substr( $quoted, 3, -2 ), ',', '"', '' );

// A comma inside a cell is ordinary here: a student on two contribution teams has one.
ck( 'a comma inside a cell survives the round trip', $read[0], 'Docs, Polyglots' );
ck( 'so does a quotation mark', $read[1], 'say "hi"' );

// PHP's default escape is a backslash, which is not part of CSV at all and makes a Windows path
// unreadable to every other parser. RFC 4180 has one escape, the doubled quotation mark.
ck( 'a backslash is not doubled or escaped', $read[2], 'C:\\path\\' );
ck( 'a newline inside a quoted cell survives', $read[3], "line\nbreak" );

echo "\n=== The roster export is the roster ===\n";

$decision = allowed_for( $A );
$matrix   = WPCPM_Institution_Export::roster_matrix( $A, '', $decision );
$file     = rows_of( WPCPM_Institution_Export::csv( $matrix ) );

ck( 'the fence is asked with the export action', $GLOBALS['asked'][ count( $GLOBALS['asked'] ) - 1 ][0], 'export' );

ck(
	'the header is every column, in order',
	$file[0],
	array( 'Roster group', 'Student', 'Program', 'Start date', 'End date', 'Cohort', 'Mentor', 'WordPress.org', 'Team', 'Website', 'Field of study', 'Tutor' )
);

// Five people, not seven. SPAM is somebody's abuse of the public form and Duplicated is a row
// naming a student who is already on the list; asking a school to explain either is asking it
// to explain a stranger.
ck( 'five students, the SPAM and Duplicated rows dropped', count( $file ) - 1, 5 );
ck( 'the SPAM row is not in the file', has( implode( '|', array_map( 'implode', array_fill( 0, count( $file ), '|' ), $file ) ), 'Spammer' ), false );
ck( 'nor the Duplicated one', has( implode( '|', array_map( 'implode', array_fill( 0, count( $file ), '|' ), $file ) ), 'Twice Over' ), false );

// The groups in the dashboard's order, so the file reads top to bottom the way the page does.
ck(
	'rows come out in the four groups, in the screen\'s order',
	column_of( $file, 0 ),
	array( 'Current', 'Current', 'Waiting for a mentor', 'Finished', 'Did not start' )
);

// The apostrophe is in the file, so a plain CSV parser reads it back. That is the point of it:
// a spreadsheet strips it on display and refuses to evaluate what follows, so the school sees
// the name the base holds and Excel does not run it.
ck(
	'and the students with them, the formula name neutralised',
	column_of( $file, 1 ),
	array( 'Ana Fidelitas', 'Ewa Zielinska', 'Bruno Kowalski', '\'=cmd|\' /C calc\'!A0', 'Dana Nowak' )
);

ck( 'the neutralised name is quoted as one cell, not split at its spaces', has( WPCPM_Institution_Export::csv( $matrix ), '"\'=cmd|\' /C calc\'!A0"' ), true );
ck( 'a tutor beginning with a hyphen is neutralised too', has( WPCPM_Institution_Export::csv( $matrix ), "'-Smith" ), true );

ck( 'the program is the label, not the raw status', $file[1][2], 'WordPress Credits Program 150h' );
ck( 'the dates are the base\'s ISO strings, which every locale reads as dates', array( $file[1][3], $file[1][4] ), array( '2026-02-10', '2026-08-10' ) );
ck( 'the cohort is derived from the start date', $file[1][5], 'January to June 2026' );
ck( 'a row with no start date says so rather than guessing', $file[5][5], 'No start date' );
ck( 'the mentor is a name from the cached card', $file[1][6], 'Marta Mentor' );
ck( 'the WordPress.org cell is the handle, not the profile URL', $file[1][7], 'anafi' );
ck( 'the team comes from the cached program block', $file[1][8], 'Documentation, Polyglots' );
ck( 'and so does the website', $file[1][9], 'https://ana.example.test/' );
ck( 'a student with no account has no mentor and no team rather than a notice', array( $file[3][6], $file[3][8] ), array( '', '' ) );

echo "\n=== What is not in the roster file ===\n";

$raw = WPCPM_Institution_Export::csv( $matrix );

// The one column this module exists to keep out. It is on the Students table and inside the
// cached program block, so both sources these cells are built from carry it; it was disclosed
// to the program to be accommodated, not to the school.
ck( 'no column is named accessibility', heading_at( $file, 'Accessibility needs' ), -1 );
ck( 'the word appears nowhere in the file', false !== stripos( $raw, 'accessib' ), false );
ck( 'and neither does the disclosure itself', has( $raw, 'Uses a screen reader' ), false );

// Grades are on one screen and in one file. A roster export carrying them would hand a school a
// grade list of a whole cohort for one click, which is the difference between reading a record
// and ranking students on the program's data.
ck( 'no grade column is in the roster file', heading_at( $file, 'Open source basics and WordPress' ), -1 );
ck( 'not one of the eleven', count( array_intersect( $file[0], array_values( WPCPM_Institution_Export::grade_columns( '150h' ) ) ) ), 0 );

// The student card writes its rows out one at a time and has no address among them either: a
// school reaching its own students is not what the program's roster is for.
ck( 'there is no email column', heading_at( $file, 'Email' ), -1 );
ck( 'and no address is in the file', has( $raw, '@example.test' ), false );

// A days-left number is wrong the day after the file is written; an end date is true for as
// long as the file is.
ck( 'there is no days-left column', heading_at( $file, 'Days left' ), -1 );

echo "\n=== The cohort narrows before anything is written ===\n";

$spring = rows_of( WPCPM_Institution_Export::csv( WPCPM_Institution_Export::roster_matrix( $A, '2026-H1', $decision ) ) );

ck( 'January to June 2026 holds three of the five', count( $spring ) - 1, 3 );
ck( 'the graduate who started in 2025 is not among them', has( implode( '|', $spring[1] ) . implode( '|', $spring[2] ) . implode( '|', $spring[3] ), 'calc' ), false );

$none = rows_of( WPCPM_Institution_Export::csv( WPCPM_Institution_Export::roster_matrix( $A, WPCPM_Cohort::NONE, $decision ) ) );

ck( 'the no-start-date bucket is a cohort like any other', column_of( $none, 1 ), array( 'Dana Nowak' ) );

$empty = rows_of( WPCPM_Institution_Export::csv( WPCPM_Institution_Export::roster_matrix( $A, '2030-H1', $decision ) ) );

// A header and nothing under it. An institution with nobody in a semester gets a file saying
// what would have been in it, which is a different thing from being refused.
ck( 'a cohort with nobody in it is still a file with headings', count( $empty ), 1 );
ck( 'and the headings are the whole list', count( $empty[0] ), 12 );

echo "\n=== The fence, and the field scope it may one day carry ===\n";

$GLOBALS['allowed'] = false;
$refused            = WPCPM_Institution_Export::roster_matrix( $A, '', allowed_for( $A ) );

// Not an empty file: an empty matrix. The handler tells "no columns permitted" from "no
// students" and refuses the first, because a refusal that arrives as a valid empty CSV is a
// file a school might keep and believe.
ck( 'a refused decision writes no matrix at all', $refused, array() );
$GLOBALS['allowed'] = true;

$GLOBALS['fields'] = array( 'site|cohort', 'students|Full Name' );
$narrow            = rows_of( WPCPM_Institution_Export::csv( WPCPM_Institution_Export::roster_matrix( $A, '2026-H1', allowed_for( $A ) ) ) );

// `scope()` keeps the caller's order, not the fields list's, so a ground that names its columns
// in any order cannot reshuffle the file's columns under the reader.
ck( 'a scoped decision keeps only its columns, in the export\'s order', $narrow[0], array( 'Student', 'Cohort' ) );
ck( 'and the cells follow the headings', $narrow[1], array( 'Ana Fidelitas', 'January to June 2026' ) );
$GLOBALS['fields'] = null;

echo "\n=== The single-student export ===\n";

$student = new WP_User( 11, 'Ana Fidelitas', 'ana.fidelitas@example.test' );
$grades  = array(
	'Open source basics and WordPress - final grade' => 92.5,
	'How decisions are made in the WordPress project - final grade' => 0,
	'Beginner WordPress Developer'                  => 78,
	// A column the form does not ask on this track, and one it never asks at all. Neither may
	// reach the file: the columns are the form's list, not whatever the record happens to hold.
	'Hours'                                         => 150,
	'Accessibility needs'                           => 'Uses a screen reader',
);

$one  = WPCPM_Institution_Export::student_matrix( 11, allowed_for( $A ), $grades, $student );
$card = rows_of( WPCPM_Institution_Export::csv( $one ) );

ck( 'the file is a header and one student', count( $card ), 2 );

// The same shape as the roster export, so a school can file one under the other. A two-column
// "field, value" layout - the obvious way to write one record out - could not be.
ck( 'the first twelve columns are the roster export\'s, in its order', array_slice( $card[0], 0, 12 ), $file[0] );
ck( 'then this track\'s eleven grades', count( $card[0] ), 23 );

ck( 'the student is the one asked for', $card[1][1], 'Ana Fidelitas' );
ck( 'and the row carries the roster group the card sits under', $card[1][0], 'Current' );

ck( 'a grade is written out', $card[1][ heading_at( $card, 'Open source basics and WordPress' ) ], '92.5' );

// A final grade of 0 is a grade. An empty cell would turn a mark somebody earned into a course
// they never took, which is the difference between a record and an accusation.
ck( 'a grade of zero prints as zero, not as blank', $card[1][ heading_at( $card, 'How decisions are made in the WordPress project' ) ], '0' );
ck( 'a course mark is a column of its own', $card[1][ heading_at( $card, 'Beginner WordPress Developer' ) ], '78' );

// Absent from the record is not zero either: nobody has entered it.
ck( 'a grade nobody has entered is empty rather than zero', $card[1][ heading_at( $card, 'Community meeting etiquette' ) ], '' );

$raw_card = WPCPM_Institution_Export::csv( $one );

ck( 'accessibility is in no column of this file either', false !== stripos( $raw_card, 'accessib' ), false );
ck( 'and the disclosure is in no cell, although the record handed one over', has( $raw_card, 'Uses a screen reader' ), false );
ck( 'hours are not a column here, whatever the record holds', heading_at( $card, 'Hours contributed' ), -1 );
ck( 'nor is the student\'s own address', has( $raw_card, '@example.test' ), false );

echo "\n=== One student, one track's worth of grades ===\n";

ck( 'the 150-hour form asks eleven', count( WPCPM_Institution_Export::grade_columns( '150h' ) ), 11 );

// Fewer courses, fewer columns. The file's shape follows the form the student actually filled
// in, so a 50-hour student's export has no blank columns for courses nobody asked them to take.
ck( 'the 50-hour form asks three', count( WPCPM_Institution_Export::grade_columns( '50h' ) ), 3 );
ck( 'the developer track asks the long form\'s eleven', count( WPCPM_Institution_Export::grade_columns( 'dev' ) ), 11 );
ck( 'every grade column is keyed on the Students Reports table', array_unique( array_map( function ( $key ) { return substr( $key, 0, 8 ); }, array_keys( WPCPM_Institution_Export::grade_columns( '150h' ) ) ) ), array( 'reports|' ) );

// The form's own list, read through `fields()`, so the base's spelling of these columns stays
// settled in one file. The trailing " - final grade" is part of the column name and not of the heading.
ck( 'the column names are the base\'s', array_key_exists( 'reports|Open source basics and WordPress - final grade', WPCPM_Institution_Export::grade_columns( '150h' ) ), true );
ck( 'and the headings are the course names a person reads', WPCPM_Institution_Export::grade_columns( '150h' )['reports|Open source basics and WordPress - final grade'], 'Open source basics and WordPress' );

$unmentored = rows_of( WPCPM_Institution_Export::csv( WPCPM_Institution_Export::student_matrix( 14, allowed_for( $A ), array(), new WP_User( 14, 'Ewa Zielinska' ) ) ) );

ck( 'a 150-hour student with no report record still gets every grade column', count( $unmentored[0] ), 23 );

// A student waiting for a mentor has no Students Reports row at all, because the automation
// that creates one fires on the assignment. Empty grade columns are the truth about them; a
// refusal would make "waiting for a mentor" an unexportable state, which for a school's first
// term is most of its roster.
ck( 'and every one of them is empty', array_unique( array_slice( $unmentored[1], 12 ) ), array( '' ) );

echo "\n=== A student the Students table has no row for ===\n";

$iker = rows_of(
	WPCPM_Institution_Export::csv(
		WPCPM_Institution_Export::student_matrix( 21, allowed_for( $A ), array(), new WP_User( 21, 'Iker Reports-Only' ) )
	)
);

// The dashboard's fifth list: an account the sync could only place from the reports side. Their
// card is drawn from the program block alone and so is their file, or a school would be told a
// student it sent does not exist.
ck( 'their name comes from the account', $iker[1][1], 'Iker Reports-Only' );
ck( 'their program comes from the cached block', $iker[1][2], 'WordPress Credits Program 50h' );
ck( 'their team and website come with it', array( $iker[1][8], $iker[1][9] ), array( 'Training', 'https://iker.example.test/' ) );
ck( 'and the roster group is empty, because they are on no roster row', $iker[1][0], '' );
ck( 'the 50-hour form gives them fifteen columns', count( $iker[0] ), 15 );

echo "\n=== The two lists that have to agree ===\n";

// The link between the screen's columns and the file's is this assertion rather than a shared
// array: the headings and the values legitimately differ, and one shared array would force a
// screen's phrasing on a file. What must not differ is which Airtable column each key names.
ck(
	'every column on the roster screen has a column in the export',
	array_values( array_diff( array_keys( WPCPM_Institution_Roster_View::columns() ), array_keys( WPCPM_Institution_Export::columns() ) ) ),
	array()
);

// A rename on either side has to fail something. Without this the link that carries the cohort
// would quietly stop matching and every export would be of the whole roster.
ck( 'the cohort argument is the roster view\'s', WPCPM_Institution_Export::ARG_COHORT, WPCPM_Institution_Roster_View::ARG_COHORT );

// The four groups are the index's and the headings are the screen's, so a fifth group added to
// either shows up in the file under its own name instead of being dropped.
ck( 'the group column uses the screen\'s headings', array_values( WPCPM_Institution_Roster_View::group_labels() ), array( 'Current', 'Waiting for a mentor', 'Finished', 'Did not start' ) );

// The four characters are one rule with two halves: the import refuses them, the export
// neutralises them. Listed as data so the two halves can be read side by side.
ck( 'the formula characters are the four the import refuses', WPCPM_Institution_Export::FORMULA_LEADERS, array( '=', '+', '-', '@' ) );

echo "\n=== House rules ===\n";

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-institution-export.php',
	'bin/test-institution-export.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either file', $dashes, array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
