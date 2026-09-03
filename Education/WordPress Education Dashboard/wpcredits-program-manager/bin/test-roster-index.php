<?php
/**
 * The per-institution roster index: the envelope, the writer, and the sweep.
 *
 * `WPCPM_Roster_Index` is the only thing between the students sync and what an
 * institution reads about its students, so what it must never hold is asserted here as
 * firmly as what it holds: a row handed over with an accessibility disclosure loses it on
 * the way in, an envelope written by another version is discarded rather than read, every
 * write goes down with autoload off, and the uninstall sweep finds every prefixed option
 * through `$wpdb` with the underscores escaped, plus the two named ones by name.
 *
 * The `hours` key is pinned here as a string and never as a number: the live column is
 * fractional for some students, so a row that arrived as 6.2 has to read back as "6.2", and a
 * row that arrived as 0 has to read back as "0" rather than as the "" a student nobody has
 * logged for gets.
 *
 * Run from the plugin root:  php bin/test-roster-index.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['queries']  = array();

function __( $s, $d = null ) { return $s; }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][] = $a; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

/**
 * Just enough `$wpdb` for the sweep's one query, with real LIKE semantics.
 *
 * `esc_like()` escapes `_` and `%`, and `get_col()` honours the escapes: an unescaped
 * `wpcpm_roster_%` would also match `wpcpm-roster-…`, since `_` is a one-character
 * wildcard, and a sweep that deleted by an unescaped prefix would take a neighbour's
 * option with it. The decoy below exists to catch exactly that.
 */
class WPCPM_Fake_WPDB {
	public $options = 'wp_options';
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { return array( 'sql' => $sql, 'args' => $args ); }
	public function get_col( $query ) {
		$GLOBALS['queries'][] = $query;

		$pattern = (string) ( $query['args'][0] ?? '' );
		$regex   = '';

		for ( $i = 0, $n = strlen( $pattern ); $i < $n; $i++ ) {
			$c = $pattern[ $i ];

			if ( '\\' === $c && $i + 1 < $n ) {
				$regex .= preg_quote( $pattern[ ++$i ], '/' );
			} elseif ( '%' === $c ) {
				$regex .= '.*';
			} elseif ( '_' === $c ) {
				$regex .= '.';
			} else {
				$regex .= preg_quote( $c, '/' );
			}
		}

		return array_values( array_filter( array_keys( $GLOBALS['opts'] ), static function ( $name ) use ( $regex ) {
			return (bool) preg_match( '/^' . $regex . '$/', (string) $name );
		} ) );
	}
}
$GLOBALS['wpdb'] = new WPCPM_Fake_WPDB();

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';

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

$inst_a = 'recINSTAAA0000001';
$inst_b = 'recINSTBBB0000001';
$empty  = array( 'v' => WPCPM_Roster_Index::VERSION, 'read' => 0, 'rows' => array() );

/**
 * A row as the sync hands it over, with two keys the index must drop.
 *
 * @param string $record_id Students record ID.
 * @param string $email     Address.
 * @param array  $extra     Overrides.
 * @return array
 */
function row( $record_id, $email, array $extra = array() ) {
	return array_merge(
		array(
			'record_id'      => $record_id,
			'name'           => ' Ada Example ',
			'email'          => $email,
			'email_key'      => strtolower( trim( $email ) ),
			'status'         => 'In Sensei',
			'institution'    => 'recINSTAAA0000001',
			'start'          => '2026-02-16',
			'end'            => '2026-06-30',
			'has_mentor'     => 1,
			'username'       => 'adaexample',
			'field_of_study' => 'Technology & Engineering',
			'tutor'          => 'Tutor Example',
			// A float, as Airtable sends a Number column, and a fractional one: the index
			// stores what it is given as a string and must not round it on the way past.
			'hours'          => 135.5,
			'import_key'     => 'batch-1:1',
			'reports'        => array( 'recREPORT00000001', 'not-a-record', 'recREPORT00000001' ),
			'user_id'        => '42',
			// What the Students table also carries and the index must never hold.
			'accessibility'  => 'Screen reader user',
			'notes'          => 'Free text a manager typed',
		),
		$extra
	);
}

echo "=== The envelope ===\n";

ck( 'an institution never written reads as the empty shape', WPCPM_Roster_Index::read( $inst_a ), $empty );
ck( 'and has no rows', WPCPM_Roster_Index::rows( $inst_a ), array() );
ck( 'a string that is not a record ID reads as the empty shape too', WPCPM_Roster_Index::read( 'krakow' ), $empty );
ck( 'the unlinked list starts empty', WPCPM_Roster_Index::unlinked(), array() );
ck( 'and so do the counts, in their own shape',
	WPCPM_Roster_Index::counts(),
	array( 'v' => WPCPM_Roster_Index::VERSION, 'read' => 0, 'institutions' => array(), 'reconciliation' => array() ) );
ck( 'the option name is the prefix and the record', WPCPM_Roster_Index::option_name( $inst_a ), 'wpcpm_roster_' . $inst_a );

echo "\n=== Another version's envelope is discarded ===\n";

// Any version but this one, so a bump does not turn this assertion red and invite somebody to
// edit the number rather than think about what the bump means. A stored roster written under a
// different shape is thrown away and the next sync writes it again, which is the whole point:
// the keys a row carries changed, and half-shaped rows would render as missing data.
$GLOBALS['opts'][ 'wpcpm_roster_' . $inst_a ] = array( 'v' => WPCPM_Roster_Index::VERSION + 1, 'read' => 5, 'rows' => array( 'recSTUDENT0000001' => array( 'record_id' => 'recSTUDENT0000001' ) ) );

ck( 'a roster written by another version reads as empty', WPCPM_Roster_Index::read( $inst_a ), $empty );

// Version 3 is the shape this index had before `hours` joined `KEYS`, so its rows carry no
// such key. Read rather than discarded, every student on the roster would show an empty hours
// cell until the next sync finished - and an empty hours cell reads as "has done nothing",
// which is a different and worse sentence than "not read yet".
$GLOBALS['opts'][ 'wpcpm_roster_' . $inst_a ] = array( 'v' => 3, 'read' => 5, 'rows' => array( 'recSTUDENT0000001' => array( 'record_id' => 'recSTUDENT0000001', 'name' => 'Ada Example' ) ) );

ck( 'a roster written before hours joined the row is discarded too', WPCPM_Roster_Index::read( $inst_a ), $empty );

$GLOBALS['opts'][ 'wpcpm_roster_' . $inst_a ] = 'garbage';

ck( 'and so does a value that is not an array', WPCPM_Roster_Index::read( $inst_a ), $empty );

$GLOBALS['opts'][ WPCPM_Roster_Index::OPTION_COUNTS ] = array( 'v' => 0, 'read' => 5, 'institutions' => array( $inst_a => array() ) );

ck( 'counts from another version are discarded', WPCPM_Roster_Index::counts()['institutions'], array() );

$GLOBALS['opts'][ WPCPM_Roster_Index::OPTION_UNLINKED ] = array( 'v' => WPCPM_Roster_Index::VERSION, 'read' => 5, 'rows' => array( 'recSTUDENT0000009' => array( 'record_id' => 'recSTUDENT0000009' ) ) );

ck( 'this version\'s unlinked rows read back',
	array_keys( WPCPM_Roster_Index::unlinked() ), array( 'recSTUDENT0000009' ) );

echo "\n=== write_all() writes every option ===\n";

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();

// Counts name every institution that has rows, which is how `finish()` builds them, and
// how the next run learns which rosters it has to empty.
$counts = array(
	$inst_a => array( '2026-H1' => array( 'signed_up' => 2, 'graduated' => 0, 'pending' => 0, 'active' => 2, 'withdrawn' => 0, 'not_started' => 0, 'other' => 0 ) ),
	$inst_b => array( '2026-H1' => array( 'signed_up' => 1, 'graduated' => 0, 'pending' => 0, 'active' => 1, 'withdrawn' => 0, 'not_started' => 0, 'other' => 0 ) ),
);
$recon  = array( 'students_without_reports' => array( 'Graduate' => 6 ), 'no_institution' => 1 );

WPCPM_Roster_Index::write_all(
	array(
		$inst_a  => array(
			// Keyed by anything on the way in; keyed by record ID on the way out.
			0 => row( 'recSTUDENT0000001', 'Ada@Example.test' ),
			1 => row( 'recSTUDENT0000002', 'bo@example.test', array( 'email_key' => '', 'has_mentor' => 0, 'user_id' => 0, 'reports' => array() ) ),
			2 => row( 'bogus', 'nobody@example.test' ),
		),
		$inst_b  => array( row( 'recSTUDENT0000003', 'cy@example.test', array( 'institution' => $inst_b ) ) ),
		'krakow' => array( row( 'recSTUDENT0000004', 'di@example.test' ) ),
	),
	array( row( 'recSTUDENT0000005', 'ed@example.test', array( 'institution' => '' ) ) ),
	$counts,
	$recon,
	1700000000
);

$a = get_option( 'wpcpm_roster_' . $inst_a );

ck( 'institution A got its option', is_array( $a ), true );
ck( 'stamped with the version and the read time', array( $a['v'], $a['read'] ), array( WPCPM_Roster_Index::VERSION, 1700000000 ) );
ck( 'rows keyed by Students record ID, the bogus one dropped',
	array_keys( $a['rows'] ), array( 'recSTUDENT0000001', 'recSTUDENT0000002' ) );

$row = $a['rows']['recSTUDENT0000001'];

ck( 'a row holds exactly the index keys, in order', array_keys( $row ), WPCPM_Roster_Index::KEYS );
ck( 'the accessibility disclosure did not make it in', isset( $row['accessibility'] ), false );
ck( 'nor the free text', isset( $row['notes'] ), false );
ck( 'and the disclosure is nowhere in the option at all',
	false !== strpos( wp_json_encode( $a ), 'Screen reader' ), false );
ck( 'the name is trimmed', $row['name'], 'Ada Example' );
ck( 'has_mentor is a boolean', $row['has_mentor'], true );
ck( 'user_id is an integer', $row['user_id'], 42 );
ck( 'reports keeps only record IDs, once each', $row['reports'], array( 'recREPORT00000001' ) );

// **Hours are a string, and every digit of it survives.** The column is fractional on the live
// base (6.2, 135.5); an `intval()` or an `(int)` cast anywhere on this path would store 135
// and rewrite half an hour of somebody's term out of the record.
ck( 'a fractional hours value is stored whole, as a string', $row['hours'], '135.5' );
// It sits with the other three Students Reports columns rather than on the end of the list,
// because that is what it is: a value the reports row lends, not a Students column.
ck( 'and its key is beside the other lent columns',
	array_slice( WPCPM_Roster_Index::KEYS, (int) array_search( 'mentor_name', WPCPM_Roster_Index::KEYS, true ), 4 ),
	array( 'mentor_name', 'team', 'website', 'hours' ) );
ck( 'the email key is lowercased', $row['email_key'], 'ada@example.test' );
ck( 'a row sent without an email key gets one from its address',
	$a['rows']['recSTUDENT0000002']['email_key'], 'bo@example.test' );
ck( 'and its falsy values keep their types',
	array( $a['rows']['recSTUDENT0000002']['has_mentor'], $a['rows']['recSTUDENT0000002']['user_id'], $a['rows']['recSTUDENT0000002']['reports'] ),
	array( false, 0, array() ) );

ck( 'institution B got its option', array_keys( WPCPM_Roster_Index::rows( $inst_b ) ), array( 'recSTUDENT0000003' ) );
ck( 'a key that is not a record ID is not an option', isset( $GLOBALS['opts']['wpcpm_roster_krakow'] ), false );
ck( 'the unlinked rows are written', array_keys( WPCPM_Roster_Index::unlinked() ), array( 'recSTUDENT0000005' ) );
ck( 'with the same read time', get_option( WPCPM_Roster_Index::OPTION_UNLINKED )['read'], 1700000000 );
ck( 'the counts are written whole',
	WPCPM_Roster_Index::counts(),
	array( 'v' => WPCPM_Roster_Index::VERSION, 'read' => 1700000000, 'institutions' => $counts, 'reconciliation' => $recon ) );
ck( 'four options, every one with autoload off',
	array( count( $GLOBALS['autoload'] ), array_values( array_unique( $GLOBALS['autoload'] ) ) ),
	array( 4, array( false ) ) );

// **A logged zero and an unfilled cell are different facts and must not clean to one value.**
// The first is a student who has done nothing yet, the second a student nobody has logged for,
// and the roster prints "0 of 150" for one and "Not recorded" for the other.
WPCPM_Roster_Index::insert( $inst_a, row( 'recSTUDENT0000030', 'zero@example.test', array( 'hours' => 0 ) ) );
WPCPM_Roster_Index::insert( $inst_a, row( 'recSTUDENT0000031', 'none@example.test', array( 'hours' => null ) ) );

ck( 'a logged zero cleans to "0"', WPCPM_Roster_Index::rows( $inst_a )['recSTUDENT0000030']['hours'], '0' );
ck( 'and a row with no hours at all cleans to ""', WPCPM_Roster_Index::rows( $inst_a )['recSTUDENT0000031']['hours'], '' );
// Padding is trimmed like every other scalar here, so a cell somebody typed a space into joins
// the numbers rather than becoming a value nothing can parse.
WPCPM_Roster_Index::insert( $inst_a, row( 'recSTUDENT0000032', 'pad@example.test', array( 'hours' => ' 6.2 ' ) ) );

ck( 'and padding around a value is trimmed off', WPCPM_Roster_Index::rows( $inst_a )['recSTUDENT0000032']['hours'], '6.2' );

echo "\n=== An institution that lost every row is emptied, not left stale ===\n";

WPCPM_Roster_Index::write_all(
	array( $inst_a => array( row( 'recSTUDENT0000001', 'ada@example.test' ) ) ),
	array(),
	array( $inst_a => array() ),
	array(),
	1700000100
);

ck( 'B, known from last run\'s counts, is rewritten empty',
	WPCPM_Roster_Index::read( $inst_b ), array( 'v' => WPCPM_Roster_Index::VERSION, 'read' => 1700000100, 'rows' => array() ) );
ck( 'A keeps its row', array_keys( WPCPM_Roster_Index::rows( $inst_a ) ), array( 'recSTUDENT0000001' ) );
ck( 'the unlinked list is now empty', WPCPM_Roster_Index::unlinked(), array() );

echo "\n=== insert() ===\n";

$before = get_option( 'wpcpm_roster_' . $inst_a )['read'];

WPCPM_Roster_Index::insert( $inst_a, row( 'recSTUDENT0000006', 'fi@example.test', array( 'user_id' => 7 ) ) );

ck( 'a new row is added', array_keys( WPCPM_Roster_Index::rows( $inst_a ) ), array( 'recSTUDENT0000001', 'recSTUDENT0000006' ) );
ck( 'the read time is kept, because the rest of the roster is still that old',
	get_option( 'wpcpm_roster_' . $inst_a )['read'], $before );
ck( 'the inserted row is cleaned like any other',
	array( array_keys( WPCPM_Roster_Index::rows( $inst_a )['recSTUDENT0000006'] ) === WPCPM_Roster_Index::KEYS, WPCPM_Roster_Index::rows( $inst_a )['recSTUDENT0000006']['user_id'] ),
	array( true, 7 ) );

WPCPM_Roster_Index::insert( $inst_a, row( 'recSTUDENT0000006', 'fi@example.test', array( 'status' => 'Graduate' ) ) );

ck( 'the same record again replaces rather than duplicates',
	array( count( WPCPM_Roster_Index::rows( $inst_a ) ), WPCPM_Roster_Index::rows( $inst_a )['recSTUDENT0000006']['status'] ),
	array( 2, 'Graduate' ) );

$snapshot = $GLOBALS['opts'];

WPCPM_Roster_Index::insert( 'krakow', row( 'recSTUDENT0000007', 'gu@example.test' ) );
WPCPM_Roster_Index::insert( $inst_a, row( 'bogus', 'hu@example.test' ) );

ck( 'a bad institution or a bad record ID writes nothing', $GLOBALS['opts'], $snapshot );

WPCPM_Roster_Index::insert( $inst_b, row( 'recSTUDENT0000008', 'ia@example.test', array( 'institution' => $inst_b ) ) );

ck( 'inserting into an emptied roster works and keeps its read time',
	array( array_keys( WPCPM_Roster_Index::rows( $inst_b ) ), WPCPM_Roster_Index::read( $inst_b )['read'] ),
	array( array( 'recSTUDENT0000008' ), 1700000100 ) );

$GLOBALS['opts'] = array();

WPCPM_Roster_Index::insert( 'recINSTCCC0000001', row( 'recSTUDENT0000009', 'jo@example.test' ) );

ck( 'inserting where nothing was written yet creates the envelope with no read time',
	WPCPM_Roster_Index::read( 'recINSTCCC0000001' )['read'], 0 );

echo "\n=== delete_all() sweeps by prefix and by name ===\n";

$GLOBALS['opts'] = array(
	'wpcpm_roster_' . $inst_a               => array( 'v' => 1 ),
	'wpcpm_roster_' . $inst_b               => array( 'v' => 1 ),
	WPCPM_Roster_Index::OPTION_UNLINKED     => array( 'v' => 1 ),
	WPCPM_Roster_Index::OPTION_COUNTS       => array( 'v' => 1 ),
	// A neighbour an unescaped LIKE would match, since `_` matches any one character.
	'wpcpm-roster-' . $inst_a               => 'decoy',
	'wpcpm_settings'                        => array( 'api_token' => 'keep' ),
	'wpcpm_students_state'                  => array( 'phase' => 'reports' ),
);
$GLOBALS['queries'] = array();

WPCPM_Roster_Index::delete_all();

ck( 'every prefixed option is gone, and both named ones',
	array_keys( $GLOBALS['opts'] ),
	array( 'wpcpm-roster-' . $inst_a, 'wpcpm_settings', 'wpcpm_students_state' ) );
ck( 'the names came through one prepared LIKE query', count( $GLOBALS['queries'] ), 1 );
ck( 'with the prefix escaped', $GLOBALS['queries'][0]['args'][0], 'wpcpm\\_roster\\_%' );
ck( 'against the options table', false !== strpos( $GLOBALS['queries'][0]['sql'], 'wp_options' ), true );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
