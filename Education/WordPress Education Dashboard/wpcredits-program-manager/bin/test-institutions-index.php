<?php
/**
 * Does the pipeline index hold what the base holds, in the order the pipeline runs?
 *
 * Seeded from bin/fixtures/institutions-index-seed.json, the Institutions table as read on
 * 2026-09-02 with every personal column removed, and pinned to the counts that fixture
 * records. The assertions here are the ones a manager screen would silently get wrong:
 * a stage the base has that the grouping does not know, a name whose trailing space was
 * trimmed on one side of a comparison and not the other, a version bump that left an old
 * shape readable, an insert that reset the read time of a 105-row index because one row
 * was added by hand.
 *
 * The other pieces of the module are stood in for at their contracts: `is_record_id()`
 * from the mentors sync, and the two stage lists from the agreement class.
 *
 * Run from the plugin root:  php bin/test-institutions-index.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts'] = array();

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ] = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

/* ---- stand-ins for the other pieces, at their contracts -------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	class WPCPM_Institution_Agreement {
		const STAGE_ORDER     = array( 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Confirmed', 'Student' );
		const TERMINAL_STAGES = array( 'Not Moving Forward', 'SPAM', 'Revisit Later' );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';

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
 * The seed fixture, decoded, or an empty array when it is missing or not JSON.
 *
 * @return array
 */
function seed() {
	$path = WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-index-seed.json';
	$data = file_exists( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;

	return is_array( $data ) ? $data : array();
}

/**
 * Map one fixture institution to a contract row.
 *
 * The fixture carries no contact columns (they are personal), so those stay ''. The
 * country name is looked up from the fixture's own countries list, the way the sync
 * looks it up from WPCPM_Countries.
 *
 * @param array $inst      Fixture institution.
 * @param array $countries Country ID => name.
 * @return array
 */
function seed_row( array $inst, array $countries ) {
	$country = ( ! empty( $inst['country'] ) && is_array( $inst['country'] ) ) ? (string) $inst['country'][0] : '';

	return array(
		'record_id'      => $inst['id'],
		'name'           => $inst['name'],
		'stage'          => $inst['stage'],
		'country'        => $country,
		'country_name'   => ( '' !== $country && isset( $countries[ $country ] ) ) ? $countries[ $country ] : '',
		'city'           => $inst['city'],
		'website'        => $inst['website'],
		'contact_person' => '',
		'contact_email'  => '',
		'created'        => substr( (string) $inst['createdTime'], 0, 10 ),
		'consent'        => ! empty( $inst['consent'] ),
		'confirmed_on'   => $inst['confirmed_on'],
		'agreement'      => array(
			'status'           => $inst['agreement']['status'],
			'kind'             => $inst['agreement']['kind'],
			'accepted_on'      => $inst['agreement']['accepted_on'],
			'signed_on'        => $inst['agreement']['signed_on'],
			'accepted_by'      => $inst['agreement']['accepted_by'],
			'submitted_on'     => $inst['agreement']['submitted_on'],
			'template_version' => $inst['agreement']['template_version'],
			'has_document'     => ! empty( $inst['agreement']['has_document'] ),
		),
	);
}

$seed      = seed();
$countries = array();

foreach ( isset( $seed['countries'] ) ? $seed['countries'] : array() as $c ) {
	$countries[ $c['id'] ] = $c['name'];
}

$rows = array();

foreach ( isset( $seed['institutions'] ) ? $seed['institutions'] : array() as $inst ) {
	$rows[ $inst['id'] ] = seed_row( $inst, $countries );
}

/* ---- the empty index ---------------------------------------------------- */

echo "=== Nothing stored ===\n";

ck( 'the fixture loaded', array( count( $rows ) > 0 ), array( true ) );

ck( 'read() is the empty shape', WPCPM_Institutions_Index::read(), array( 'v' => 1, 'read' => 0, 'rows' => array() ) );
ck( 'rows() is empty', WPCPM_Institutions_Index::rows(), array() );
ck( 'row() is null', WPCPM_Institutions_Index::row( 'rec1ZgEtczDKjRNP4' ), null );
ck( 'has() is false', WPCPM_Institutions_Index::has( 'rec1ZgEtczDKjRNP4' ), false );
ck( 'stage_counts() is empty', WPCPM_Institutions_Index::stage_counts(), array() );
ck( 'by_stage() is empty', WPCPM_Institutions_Index::by_stage(), array() );
ck( 'OPTION and VERSION are the contract', array( WPCPM_Institutions_Index::OPT_NAME, WPCPM_Institutions_Index::VERSION ), array( 'wpcpm_institutions_index', 1 ) );

/* ---- seeded from the fixture ------------------------------------------- */

echo "\n=== Seeded from the fixture ===\n";

$read_at = 1756800000;
WPCPM_Institutions_Index::write( $rows, $read_at );

$stored = get_option( WPCPM_Institutions_Index::OPT_NAME );
ck( 'written with autoload off', $GLOBALS['autoload'][ WPCPM_Institutions_Index::OPT_NAME ], false );
ck( 'the envelope carries the version and the read time', array( $stored['v'], $stored['read'] ), array( 1, $read_at ) );
ck( 'read() hands the envelope back', WPCPM_Institutions_Index::read()['read'], $read_at );

ck( 'every fixture row is in the index', count( WPCPM_Institutions_Index::rows() ), $seed['counts']['institutions'] );
$SEEDED = count( $seed['institutions'] );
ck( 'and that is what the fixture holds', count( WPCPM_Institutions_Index::rows() ), $SEEDED );

$counts = WPCPM_Institutions_Index::stage_counts();
$want   = $seed['counts']['by_stage'];
ksort( $counts, SORT_STRING );
ksort( $want, SORT_STRING );
ck( 'the stage counts are the fixture\'s', $counts, $want );

$groups = array();
foreach ( WPCPM_Institutions_Index::by_stage() as $stage => $group ) {
	$groups[ $stage ] = count( $group );
}
$want = $seed['counts']['by_stage'];
ksort( $groups, SORT_STRING );
ksort( $want, SORT_STRING );
ck( 'by_stage() groups the same rows', $groups, $want );

ck( 'no record has an empty stage in this fixture', isset( $counts[''] ), false );

// Pipeline order: STAGE_ORDER, then the terminal stages. Every one of the eleven is present
// in the fixture, so the key order is the whole list.
ck( 'by_stage() runs the pipeline in order, terminal stages last',
	array_keys( WPCPM_Institutions_Index::by_stage() ),
	array( 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Confirmed', 'Student', 'Not Moving Forward', 'SPAM', 'Revisit Later' ) );

ck( 'stage_counts() follows the same order', array_keys( WPCPM_Institutions_Index::stage_counts() ), array_keys( WPCPM_Institutions_Index::by_stage() ) );

/* ---- single rows --------------------------------------------------------- */

echo "\n=== One row at a time ===\n";

$pisa = WPCPM_Institutions_Index::row( 'rec1ZgEtczDKjRNP4' );
ck( 'row() finds Pisa', array( $pisa['name'], $pisa['stage'], $pisa['city'], $pisa['confirmed_on'] ), array( 'Università di Pisa', 'Confirmed', 'Pisa', '2025-06-26' ) );
ck( 'and resolves its country through the map', array( $pisa['country'], $pisa['country_name'] ), array( 'recQcCJMA9jvWJnTB', 'Italy' ) );
ck( 'created is the day of createdTime', $pisa['created'], '2025-07-17' );
ck( 'has() agrees', WPCPM_Institutions_Index::has( 'rec1ZgEtczDKjRNP4' ), true );

$test = WPCPM_Institutions_Index::row( 'recDdomg5W6h410JT' );
ck( 'the TEST record is in the index with no country', array( $test['stage'], $test['country'], $test['country_name'] ), array( 'Under Review', '', '' ) );

// Ten names end in a space in the base. The index keeps them: a comparison that trims one
// side and not the other is the fence bug this module keeps meeting, so the base and the
// index must agree byte for byte and renderers trim.
$trailing = 0;
foreach ( WPCPM_Institutions_Index::rows() as $row ) {
	if ( '' !== $row['name'] && rtrim( $row['name'] ) !== $row['name'] ) {
		++$trailing;
	}
}
ck( 'trailing spaces on names survive', $trailing, $seed['counts']['trailing_space_names'] );

// The index stores a name as the base holds it and repairs nothing: trimming here would make
// the site and the grid disagree about what an institution is called, and the screen is where
// the tidying belongs. Ten records ended in a space and two had no name at all on 2 September;
// a program manager cleaned all twelve the same day, so both shapes are made here rather than
// waited for.
$before_awkward = WPCPM_Institutions_Index::read();

$awkward = array(
	'recSPACE0000000AA' => 'Sorbonne university ',
	'recBLANK0000000BB' => '',
);
foreach ( $awkward as $id => $name ) {
	WPCPM_Institutions_Index::insert( array( 'record_id' => $id, 'name' => $name, 'stage' => 'Confirmed' ) );
}

ck( 'a trailing space on a name survives the round trip', WPCPM_Institutions_Index::row( 'recSPACE0000000AA' )['name'], 'Sorbonne university ' );
ck( 'and is not quietly trimmed on the way in', WPCPM_Institutions_Index::row( 'recSPACE0000000AA' )['name'] !== 'Sorbonne university', true );
ck( 'a record with no name is kept, under its own ID', array( WPCPM_Institutions_Index::has( 'recBLANK0000000BB' ), WPCPM_Institutions_Index::row( 'recBLANK0000000BB' )['name'] ), array( true, '' ) );

$trailing_now = 0;
$nameless_now = 0;
foreach ( WPCPM_Institutions_Index::rows() as $row ) {
	if ( '' === $row['name'] ) {
		++$nameless_now;
	} elseif ( rtrim( $row['name'] ) !== $row['name'] ) {
		++$trailing_now;
	}
}
ck( 'the seed itself is clean, so these two are the only awkward rows', array( $trailing_now, $nameless_now ), array( (int) $seed['counts']['trailing_space_names'] + 1, (int) $seed['counts']['nameless'] + 1 ) );

// Put the index back, so the counts the rest of this suite pins are the fixture's.
WPCPM_Institutions_Index::write( $before_awkward['rows'], $before_awkward['read'] );

ck( 'an unknown record is null', WPCPM_Institutions_Index::row( 'recNOPE0000000000' ), null );
ck( 'a malformed ID is null, not a lookup of the empty key', WPCPM_Institutions_Index::row( '' ), null );
ck( 'and not a lookup of a near miss', WPCPM_Institutions_Index::row( 'rec1ZgEtczDKjRNP' ), null );
ck( 'has() on a malformed ID is false', WPCPM_Institutions_Index::has( ' ' ), false );

/* ---- the row shape ------------------------------------------------------- */

echo "\n=== The row shape ===\n";

$contract  = array( 'record_id', 'name', 'stage', 'country', 'country_name', 'city', 'website', 'contact_person', 'contact_email', 'created', 'consent', 'confirmed_on', 'agreement' );
$agreement = array( 'status', 'kind', 'accepted_on', 'signed_on', 'accepted_by', 'submitted_on', 'template_version', 'has_document' );
$off_shape = 0;

foreach ( WPCPM_Institutions_Index::rows() as $id => $row ) {
	$keys = array_keys( $row );
	$sub  = array_keys( $row['agreement'] );
	sort( $keys );
	sort( $sub );
	$want_keys = $contract;
	$want_sub  = $agreement;
	sort( $want_keys );
	sort( $want_sub );

	if ( $keys !== $want_keys || $sub !== $want_sub || 0 !== strcmp( (string) $id, (string) $row['record_id'] ) || ! is_bool( $row['consent'] ) || ! is_bool( $row['agreement']['has_document'] ) ) {
		++$off_shape;
	}
}
ck( 'every row carries exactly the contract keys, keyed by its own ID', $off_shape, 0 );

// The consent report reads these three numbers from the index; the fixture pins them.
$pre = 0;
$pre_confirmed = 0;
$pre_consent = 0;
foreach ( WPCPM_Institutions_Index::rows() as $row ) {
	if ( $row['created'] < '2026-07-20' ) {
		++$pre;
		if ( 'Confirmed' === $row['stage'] ) {
			++$pre_confirmed;
		}
		if ( $row['consent'] ) {
			++$pre_consent;
		}
	}
}
ck( 'the consent report can count records created before the question existed',
	array( $pre, $pre_confirmed, $pre_consent ),
	array( $seed['counts']['created_before_consent_question'], $seed['counts']['created_before_consent_question_confirmed'], $seed['counts']['created_before_consent_question_with_consent'] ) );

// A caller that hands over more than the contract does not get it stored.
WPCPM_Institutions_Index::write(
	array(
		'recSMUGGLE0000001' => array(
			'record_id'  => 'recSMUGGLE0000001',
			'name'       => 'Smuggler',
			'stage'      => 'Confirmed',
			'department' => 'Computer Science',
			'why'        => 'A paragraph of prose',
			'drive_url'  => 'https://drive.google.com/x',
			'agreement'  => array( 'status' => 'On file', 'document' => 'https://drive.google.com/x', 'has_document' => 'yes' ),
		),
		'not a record id'   => array( 'name' => 'Dropped' ),
		'recBADID'          => array( 'record_id' => 'recBADID', 'name' => 'Dropped too' ),
	),
	5
);
$row = WPCPM_Institutions_Index::row( 'recSMUGGLE0000001' );
ck( 'keys outside the contract are dropped on write', array( isset( $row['department'] ), isset( $row['why'] ), isset( $row['drive_url'] ), isset( $row['agreement']['document'] ) ), array( false, false, false, false ) );
ck( 'the agreement block is completed and typed', $row['agreement'], array( 'status' => 'On file', 'kind' => '', 'accepted_on' => '', 'signed_on' => '', 'accepted_by' => '', 'submitted_on' => '', 'template_version' => '', 'has_document' => true ) );
ck( 'missing keys are filled with their empty value', array( $row['country'], $row['contact_email'], $row['consent'] ), array( '', '', false ) );
ck( 'rows without a well-formed ID are not written', count( WPCPM_Institutions_Index::rows() ), 1 );

/* ---- insert -------------------------------------------------------------- */

echo "\n=== insert() ===\n";

WPCPM_Institutions_Index::write( $rows, $read_at );

WPCPM_Institutions_Index::insert(
	array(
		'record_id' => 'recNEWAPPROVED001',
		'name'      => 'Universidad Example',
		'stage'     => 'First Contact Made',
		'country'   => 'recQcCJMA9jvWJnTB',
	)
);

ck( 'the new row is there', WPCPM_Institutions_Index::has( 'recNEWAPPROVED001' ), true );
ck( 'the index grew by one', count( WPCPM_Institutions_Index::rows() ), $SEEDED + 1 );
ck( 'and the read time is the table\'s, not the row\'s', WPCPM_Institutions_Index::read()['read'], $read_at );
ck( 'the inserted row has the full shape', array_keys( WPCPM_Institutions_Index::row( 'recNEWAPPROVED001' ) ), $contract );

WPCPM_Institutions_Index::insert( array_merge( $rows['rec1ZgEtczDKjRNP4'], array( 'stage' => 'Student' ) ) );
ck( 'inserting an existing ID replaces the row', WPCPM_Institutions_Index::row( 'rec1ZgEtczDKjRNP4' )['stage'], 'Student' );
ck( 'without growing the index', count( WPCPM_Institutions_Index::rows() ), $SEEDED + 1 );

WPCPM_Institutions_Index::insert( array( 'record_id' => 'nonsense', 'name' => 'Nope' ) );
ck( 'a malformed ID is not inserted', count( WPCPM_Institutions_Index::rows() ), $SEEDED + 1 );

/* ---- stages the lists do not name ---------------------------------------- */

echo "\n=== Stages outside the lists ===\n";

WPCPM_Institutions_Index::insert( array( 'record_id' => 'recNOSTAGE0000001', 'name' => 'No stage yet', 'stage' => '' ) );
WPCPM_Institutions_Index::insert( array( 'record_id' => 'recNEWSTAGE000001', 'name' => 'Base grew a stage', 'stage' => 'Brand New Stage' ) );
WPCPM_Institutions_Index::insert( array( 'record_id' => 'recNEWSTAGE000002', 'name' => 'Base grew another', 'stage' => 'Another New Stage' ) );

$order = array_keys( WPCPM_Institutions_Index::by_stage() );
ck( 'the empty stage is last', end( $order ), '' );
ck( 'unknown stages sit after the terminal ones, sorted, before the empty group',
	array_slice( $order, -4 ),
	array( 'Revisit Later', 'Another New Stage', 'Brand New Stage', '' ) );
ck( 'stage_counts() names the empty stage as \'\'', WPCPM_Institutions_Index::stage_counts()[''], 1 );

/* ---- versioning ---------------------------------------------------------- */

echo "\n=== Versioning ===\n";

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['v'] = 99;
ck( 'a version mismatch reads as empty', WPCPM_Institutions_Index::read(), array( 'v' => 1, 'read' => 0, 'rows' => array() ) );
ck( 'so rows() is empty', WPCPM_Institutions_Index::rows(), array() );
ck( 'and has() is false', WPCPM_Institutions_Index::has( 'rec1ZgEtczDKjRNP4' ), false );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = 'a string somebody stored';
ck( 'a malformed option reads as empty', WPCPM_Institutions_Index::rows(), array() );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array( 'v' => 1, 'read' => 7, 'rows' => 'not rows' );
ck( 'a malformed rows member reads as empty', WPCPM_Institutions_Index::read(), array( 'v' => 1, 'read' => 7, 'rows' => array() ) );

// insert() on a stale version starts a fresh index rather than grafting a new row onto
// an old shape.
WPCPM_Institutions_Index::write( $rows, $read_at );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['v'] = 0;
WPCPM_Institutions_Index::insert( $rows['rec1ZgEtczDKjRNP4'] );
ck( 'insert() over a stale version writes a fresh index', array( WPCPM_Institutions_Index::read()['v'], count( WPCPM_Institutions_Index::rows() ) ), array( 1, 1 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
