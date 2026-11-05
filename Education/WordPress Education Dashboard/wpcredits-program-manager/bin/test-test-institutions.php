<?php
/**
 * Does the one helper for test institutions decide the way the design says?
 *
 * An institution whose name starts with the word TEST in capitals is a test institution
 * (`WPCPM_Sponsors_Index::is_test_name()`), listed with a Test mark and left out of every count.
 * `WPCPM_Test_Institutions` is the one place that decides it, for a record ID or a name, keeps the
 * short list of record IDs found to be tests so a test institution stays one after its row has
 * left the index, and gives each counting site its filter. This suite pins the rule's edges, the
 * record ID's read (the index first, the list only for an ID the index does not hold), the list
 * (what joins it, what leaves it, written only when it changes, never autoloaded), each filter,
 * the mark, and that every stand-in of the rule in `bin/` is the real body.
 *
 * The other pieces of the module are stood in for at their contracts: `is_record_id()` from the
 * mentors sync and `institution_of()` from the memberships.
 *
 * Run from the plugin root:  php bin/test-test-institutions.php
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
$GLOBALS['writes']   = 0;
$GLOBALS['acts_for'] = array();
$GLOBALS['meta']     = array();
$GLOBALS['primed']   = array();
$GLOBALS['posts']    = array();
$GLOBALS['other_posts'] = array();
$GLOBALS['index_reads'] = 0;

function __( $s, $d = null ) { return $s; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) {
	if ( 'wpcpm_institutions_index' === $k ) { ++$GLOBALS['index_reads']; }
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	++$GLOBALS['writes'];
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : '';
}
function update_meta_cache( $type, $ids ) { $GLOBALS['primed'][] = array( $type, $ids ); return array(); }

/**
 * A wpdb as far as the helper reaches it: one prepared read of `ID, post_title` among the IDs
 * given. Every statement is kept as sent, so a check can say how many queries a read made and
 * that none of them read a meta table.
 */
class WPCPM_Test_Titles_Wpdb {
	public $posts      = 'wp_posts';
	public $postmeta   = 'wp_postmeta';
	public $statements = array();

	public function prepare( $query, ...$args ) {
		$args = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;
		$i    = 0;

		return preg_replace_callback(
			'/%[ds]/',
			static function ( $m ) use ( $args, &$i ) {
				$value = $args[ $i++ ];

				return 'd' === $m[0][1] ? (string) (int) $value : "'" . $value . "'";
			},
			$query
		);
	}

	public function get_results( $query, $output = OBJECT ) {
		$this->statements[] = $query;
		preg_match( '/IN \(([0-9,\s]*)\)/', $query, $found );
		$ids  = isset( $found[1] ) ? array_map( 'intval', explode( ',', $found[1] ) ) : array();
		$type = preg_match( "/post_type = '([a-z_]+)'/", $query, $t ) ? $t[1] : '';
		$rows = array();

		// Posts are of the application type; `$GLOBALS['other_posts']` are of another, which a
		// statement with no post type clause would read as well.
		foreach ( $GLOBALS['posts'] + $GLOBALS['other_posts'] as $id => $title ) {
			$of = isset( $GLOBALS['other_posts'][ $id ] ) ? 'page' : 'wpcpm_inst_app';

			if ( in_array( $id, $ids, true ) && ( '' === $type || $type === $of ) ) {
				$rows[] = (object) array( 'ID' => (string) $id, 'post_title' => $title );
			}
		}

		return $rows;
	}
}

if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
$GLOBALS['wpdb'] = new WPCPM_Test_Titles_Wpdb();

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

/* ---- stand-ins for the other pieces, at their contracts -------------------- */

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) {
		return is_scalar( $value ) && (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
	}
}

class WPCPM_Institution_Members {
	public static function institution_of( $user = null ) {
		$id = is_object( $user ) ? $user->ID : (int) $user;

		return isset( $GLOBALS['acts_for'][ $id ] ) ? $GLOBALS['acts_for'][ $id ] : '';
	}
}

class WPCPM_Institution_Application {
	const POST_TYPE = 'wpcpm_inst_app';
}

class WPCPM_Institution_Agreement {
	const STAGE_ORDER     = array( 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Confirmed', 'Student' );
	const TERMINAL_STAGES = array( 'Not Moving Forward', 'SPAM', 'Revisit Later' );
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-test-institutions.php';

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
 * The index, written from record ID to name, every row Confirmed. Returns the rows as the index
 * holds them, to hand to `remember()` as a before or an after.
 *
 * @param array $names Record ID to name.
 * @return array
 */
function index_world( array $names ) {
	$rows = array();

	foreach ( $names as $id => $name ) {
		$rows[ $id ] = array(
			'record_id' => $id,
			'name'      => $name,
			'stage'     => 'Confirmed',
		);
	}

	WPCPM_Institutions_Index::write( $rows, 1000 );

	return WPCPM_Institutions_Index::rows();
}

/**
 * A clean site: no option, no write counted.
 */
function reset_world() {
	$GLOBALS['opts']     = array();
	$GLOBALS['autoload'] = array();
	$GLOBALS['writes']   = 0;
	$GLOBALS['acts_for'] = array();
	$GLOBALS['meta']     = array();
	$GLOBALS['primed']   = array();
	$GLOBALS['posts']    = array();
	$GLOBALS['other_posts'] = array();
	$GLOBALS['index_reads'] = 0;
	$GLOBALS['wpdb']->statements = array();
}

$TEST = 'recTESTINSTITUT01';
$A    = 'recINSTITUTION001';
$B    = 'recINSTITUTION003';

/* ---- the rule ------------------------------------------------------------- */

echo "=== The rule ===\n";

$names = array( 'TEST - Example Institution', ' TEST - Leading Space College', 'TEST', 'TEST: Example', 'TEST_Example', 'Test College', 'Contest Labs', 'TESTAMENT Academy', 'TEST2 Labs', 'test college', '' );
ck( 'a name: the word TEST in capitals, trimmed, then nothing or no letter or digit', array_map( array( 'WPCPM_Test_Institutions', 'is_test' ), $names ), array( true, true, true, true, true, false, false, false, false, false, false ) );
ck( 'which is the sponsors\' rule, name for name', array_map( array( 'WPCPM_Test_Institutions', 'is_test' ), $names ), array_map( array( 'WPCPM_Sponsors_Index', 'is_test_name' ), $names ) );
ck( 'nothing that is not a string is a test', array( WPCPM_Test_Institutions::is_test( null ), WPCPM_Test_Institutions::is_test( array( 'TEST' ) ), WPCPM_Test_Institutions::is_test( 12 ) ), array( false, false, false ) );

/* ---- a record ID ---------------------------------------------------------- */

echo "\n=== A record ID ===\n";

reset_world();
index_world( array( $TEST => 'TEST - Example Institution', $A => 'Example University', $B => 'Test College' ) );

ck( 'the index\'s name decides: the test, a real one and "Test College"', array( WPCPM_Test_Institutions::is_test( $TEST ), WPCPM_Test_Institutions::is_test( $A ), WPCPM_Test_Institutions::is_test( $B ) ), array( true, false, false ) );
ck( 'a record ID is trimmed as row() trims it', WPCPM_Test_Institutions::is_test( " recTESTINSTITUT01\n" ), true );
ck( 'a record ID held nowhere is not a test', WPCPM_Test_Institutions::is_test( 'recINSTITUTION099' ), false );
ck( 'test_ids() is the index\'s test rows', WPCPM_Test_Institutions::test_ids(), array( $TEST ) );
ck( 'and reading it wrote no option', array_key_exists( WPCPM_Test_Institutions::OPTION, $GLOBALS['opts'] ), false );

/* ---- remembered ----------------------------------------------------------- */

echo "\n=== Remembered ===\n";

reset_world();
$before = index_world( array( $TEST => 'TEST - Example Institution', $A => 'Example University', $B => 'Test College' ) );
$after  = $before;
$GLOBALS['writes'] = 0;

ck( 'remember() answers the test institutions of the world about to be written', WPCPM_Test_Institutions::remember( $before, $after ), array( $TEST ) );
ck( 'and stores them, not autoloaded', array( get_option( WPCPM_Test_Institutions::OPTION ), $GLOBALS['autoload'][ WPCPM_Test_Institutions::OPTION ] ), array( array( $TEST ), false ) );
ck( 'remembered() reads it back', WPCPM_Test_Institutions::remembered(), array( $TEST ) );

// The index written without the row, as the sync drops a row removed from Airtable.
$gone = array( $A => $before[ $A ], $B => $before[ $B ] );
WPCPM_Test_Institutions::remember( $before, $gone );
index_world( array( $A => 'Example University', $B => 'Test College' ) );
$GLOBALS['writes'] = 0;

ck( 'the row gone from the index', WPCPM_Institutions_Index::row( $TEST ), null );
ck( 'it is still a test institution', WPCPM_Test_Institutions::is_test( $TEST ), true );
ck( 'and test_ids() still lists it', WPCPM_Test_Institutions::test_ids(), array( $TEST ) );
ck( 'a real one the index does not hold is still not a test', WPCPM_Test_Institutions::is_test( 'recINSTITUTION099' ), false );

$list_now = WPCPM_Test_Institutions::remembered();
WPCPM_Test_Institutions::remember( $before, $after );
WPCPM_Test_Institutions::remember( $before, $after );
ck( 'the same remember() again writes nothing', array( $GLOBALS['writes'], WPCPM_Test_Institutions::remembered() ), array( 0, $list_now ) );

reset_world();
$real_before = index_world( array( $A => 'Example University', $B => 'Test College' ) );
$GLOBALS['writes'] = 0;
ck( 'a world with no test institution remembers none', WPCPM_Test_Institutions::remember( $real_before, $real_before ), array() );
ck( 'and writes no option at all', array( $GLOBALS['writes'], array_key_exists( WPCPM_Test_Institutions::OPTION, $GLOBALS['opts'] ) ), array( 0, false ) );

/* ---- renamed -------------------------------------------------------------- */

echo "\n=== Renamed ===\n";

reset_world();
$GLOBALS['opts'][ WPCPM_Test_Institutions::OPTION ] = array( $TEST );
$held = array( $TEST => array( 'name' => 'TEST - Example Institution' ) );
ck( 'a row renamed away from TEST leaves the list at the write that renames it', WPCPM_Test_Institutions::remember( $held, array( $TEST => array( 'name' => 'Example Institute' ) ) ), array() );
ck( 'the option follows', WPCPM_Test_Institutions::remembered(), array() );

reset_world();
$held = array( $A => array( 'name' => 'Example University' ) );
ck( 'a row renamed to TEST joins it', WPCPM_Test_Institutions::remember( $held, array( $A => array( 'name' => 'TEST - Example University' ) ) ), array( $A ) );

reset_world();
index_world( array( $A => 'Example University' ) );
$GLOBALS['opts'][ WPCPM_Test_Institutions::OPTION ] = array( $A );
ck( 'a name in the index wins over a planted list entry', WPCPM_Test_Institutions::is_test( $A ), false );
ck( 'and test_ids() lacks it', WPCPM_Test_Institutions::test_ids(), array() );

reset_world();
$GLOBALS['opts'][ WPCPM_Test_Institutions::OPTION ] = array( $TEST );
ck( 'an ID in neither array keeps its place', WPCPM_Test_Institutions::remember( array(), array( $A => array( 'name' => 'Example University' ) ) ), array( $TEST ) );

/* ---- a malformed list ----------------------------------------------------- */

echo "\n=== A malformed list ===\n";

reset_world();
$GLOBALS['opts'][ WPCPM_Test_Institutions::OPTION ] = 'recTESTINSTITUT01';
ck( 'a string is no list', WPCPM_Test_Institutions::remembered(), array() );
$GLOBALS['opts'][ WPCPM_Test_Institutions::OPTION ] = array( 'not a record', 7, 'recTESTINSTITUT01', 'recTESTINSTITUT01', ' recINSTITUTION001 ' );
ck( 'only record IDs, trimmed, once each, sorted', WPCPM_Test_Institutions::remembered(), array( $A, $TEST ) );
$GLOBALS['opts'][ WPCPM_Test_Institutions::OPTION ] = array( 'not a record', 7, 'recTESTINSTITUT01', 'recTESTINSTITUT01' );
ck( 'the case the brief names', WPCPM_Test_Institutions::remembered(), array( $TEST ) );

/* ---- the filters ---------------------------------------------------------- */

echo "\n=== The filters ===\n";

reset_world();
index_world( array( $TEST => 'TEST - Example Institution', $A => 'Example University', $B => 'Test College' ) );

ck( 'real_keys() drops the test keys and keeps the others in order', WPCPM_Test_Institutions::real_keys( array( $A => 1, $TEST => 2, $B => 3 ) ), array( $A => 1, $B => 3 ) );

$rows = array( array( 'record' => $TEST ), array( 'record' => $A ), array( 'record' => '' ) );
ck( 'count_real_rows() over arrays, by a record ID', WPCPM_Test_Institutions::count_real_rows( $rows, 'record' ), 2 );
ck( 'a row without the field is real', WPCPM_Test_Institutions::count_real_rows( array( array( 'other' => $TEST ), array( 'record' => $TEST ) ), 'record' ), 1 );
$posts = array(
	(object) array( 'ID' => 21, 'post_title' => 'TEST - Example Application' ),
	(object) array( 'ID' => 22, 'post_title' => 'Example University' ),
	(object) array( 'ID' => 23, 'post_title' => 'Test College' ),
);
ck( 'count_real_rows() over objects, by a name', WPCPM_Test_Institutions::count_real_rows( $posts, 'post_title' ), 2 );

$GLOBALS['meta'] = array(
	11 => array( '_wpcpm_agr_institution' => $TEST ),
	12 => array( '_wpcpm_agr_institution' => $A ),
);
ck( 'real_post_ids() keeps the posts whose meta is not a test institution\'s', WPCPM_Test_Institutions::real_post_ids( array( 11, 12 ), '_wpcpm_agr_institution' ), array( 12 ) );
ck( 'having primed their meta first', $GLOBALS['primed'], array( array( 'post', array( 11, 12 ) ) ) );
ck( 'and none for no posts', WPCPM_Test_Institutions::real_post_ids( array(), '_wpcpm_agr_institution' ), array() );

$GLOBALS['posts'] = array( 21 => 'TEST - Example Application', 22 => 'Example University', 23 => 'Test College' );
$GLOBALS['wpdb']->statements = array();
ck( 'real_application_ids() keeps the applications whose title is not a test name, in order', WPCPM_Test_Institutions::real_application_ids( array( 23, 21, 22 ) ), array( 23, 22 ) );
ck( 'and the titles of the three are one query that reads no meta', array( count( $GLOBALS['wpdb']->statements ), false !== strpos( $GLOBALS['wpdb']->statements[0], 'postmeta' ) ), array( 1, false ) );
$GLOBALS['wpdb']->statements = array();
ck( 'none given reads nothing', array( WPCPM_Test_Institutions::real_application_ids( array() ), count( $GLOBALS['wpdb']->statements ) ), array( array(), 0 ) );
$GLOBALS['other_posts'] = array( 24 => 'TEST - A page that is no application' );
ck( 'the statement reads applications only: a post of another type titled TEST is not judged by its title', array( WPCPM_Test_Institutions::real_application_ids( array( 22, 24 ) ), false !== strpos( end( $GLOBALS['wpdb']->statements ), "post_type = 'wpcpm_inst_app'" ) ), array( array( 22, 24 ), true ) );
$GLOBALS['other_posts'] = array();
ck( 'an ID with no post to read is not a test name, so it is real', WPCPM_Test_Institutions::real_application_ids( array( 22, 99 ) ), array( 22, 99 ) );

$GLOBALS['acts_for'] = array( 31 => $TEST, 32 => $A, 33 => '' );
$accounts = array( (object) array( 'ID' => 31 ), (object) array( 'ID' => 32 ) );
ck( 'count_real_accounts() leaves out the accounts acting for a test institution', WPCPM_Test_Institutions::count_real_accounts( $accounts ), 1 );
ck( 'an account acting for none is real', WPCPM_Test_Institutions::count_real_accounts( array( (object) array( 'ID' => 33 ) ) ), 1 );

/* ---- one read of the index, however many items ---------------------------- */

echo "\n=== One read of the index for a whole filter ===\n";

reset_world();
index_world( array( $TEST => 'TEST - Example Institution', $A => 'Example University', $B => 'Test College' ) );
$GLOBALS['meta']     = array( 11 => array( 'k' => $TEST ), 12 => array( 'k' => $A ), 13 => array( 'k' => $B ) );
$GLOBALS['acts_for'] = array( 31 => $TEST, 32 => $A, 33 => $B );

$reads = static function ( $call ) {
	$GLOBALS['index_reads'] = 0;
	$call();

	return $GLOBALS['index_reads'];
};

ck( 'real_keys() of three keys reads the index once', $reads( static function () use ( $TEST, $A, $B ) { WPCPM_Test_Institutions::real_keys( array( $TEST => 1, $A => 2, $B => 3 ) ); } ), 1 );
ck( 'count_real_rows() of three rows reads it once', $reads( static function () use ( $TEST, $A, $B ) { WPCPM_Test_Institutions::count_real_rows( array( array( 'r' => $TEST ), array( 'r' => $A ), array( 'r' => $B ) ), 'r' ); } ), 1 );
ck( 'real_post_ids() of three posts reads it once', $reads( static function () { WPCPM_Test_Institutions::real_post_ids( array( 11, 12, 13 ), 'k' ); } ), 1 );
ck( 'count_real_accounts() of three accounts reads it once', $reads( static function () { WPCPM_Test_Institutions::count_real_accounts( array( (object) array( 'ID' => 31 ), (object) array( 'ID' => 32 ), (object) array( 'ID' => 33 ) ) ); } ), 1 );
ck( 'the answers are the same as one item at a time', array(
	array_keys( WPCPM_Test_Institutions::real_keys( array( $TEST => 1, $A => 2, $B => 3 ) ) ),
	WPCPM_Test_Institutions::real_post_ids( array( 11, 12, 13 ), 'k' ),
	WPCPM_Test_Institutions::count_real_accounts( array( (object) array( 'ID' => 31 ), (object) array( 'ID' => 32 ), (object) array( 'ID' => 33 ) ) ),
), array( array( $A, $B ), array( 12, 13 ), 2 ) );
ck( 'nothing to judge reads nothing', $reads( static function () { WPCPM_Test_Institutions::real_keys( array() ); WPCPM_Test_Institutions::real_post_ids( array(), 'k' ); } ), 0 );

// A site with no test institution: the IDs come back unchanged and no meta is read for them.
reset_world();
index_world( array( $A => 'Example University', $B => 'Test College' ) );
$GLOBALS['meta']   = array( 11 => array( 'k' => $A ), 12 => array( 'k' => $B ) );
$GLOBALS['primed'] = array();
ck( 'real_post_ids() with no test institution returns the IDs as they were, in their order, and primes no meta',
	array( WPCPM_Test_Institutions::real_post_ids( array( 12, 11 ), 'k' ), $GLOBALS['primed'] ),
	array( array( 12, 11 ), array() ) );
index_world( array( $TEST => 'TEST - Example Institution', $A => 'Example University', $B => 'Test College' ) );

/* ---- the mark ------------------------------------------------------------- */

echo "\n=== The mark ===\n";

ck( 'mark() of a test institution is the pipeline\'s flag with its leading space', WPCPM_Test_Institutions::mark( $TEST ), ' <span class="wpcpm-inst-mark wpcpm-inst-mark--test" title="A test institution, whose name starts with TEST: listed here and left out of every count.">Test</span>' );
ck( 'on the Administrator Dashboard it is the dashboards\' badge', WPCPM_Test_Institutions::mark( $TEST, WPCPM_Test_Institutions::MARK_DASHBOARD ), ' <span class="wpcpm-badge wpcpm-badge--test" title="A test institution, whose name starts with TEST: listed here and left out of every count.">Test</span>' );
ck( 'a real institution has none', WPCPM_Test_Institutions::mark( $A ), '' );
ck( 'the classes the constants name', array( WPCPM_Test_Institutions::MARK_ADMIN, WPCPM_Test_Institutions::MARK_DASHBOARD, WPCPM_Test_Institutions::OPTION ), array( 'wpcpm-inst-mark wpcpm-inst-mark--test', 'wpcpm-badge wpcpm-badge--test', 'wpcpm_test_institutions' ) );
ck( 'label() of a test institution adds (Test)', WPCPM_Test_Institutions::label( 'TEST - Example Institution', $TEST ), 'TEST - Example Institution (Test)' );
ck( 'label() of a real one is the name as given', WPCPM_Test_Institutions::label( 'Example University', $A ), 'Example University' );
ck( 'labeled() is the "(Test)" form of any name, with no judging, and label() of a test institution is the same words', array( WPCPM_Test_Institutions::labeled( 'Example University' ), WPCPM_Test_Institutions::labeled( 'Ada Example (ada)' ), WPCPM_Test_Institutions::label( 'TEST - Example Institution', $TEST ) === WPCPM_Test_Institutions::labeled( 'TEST - Example Institution' ) ), array( 'Example University (Test)', 'Ada Example (ada) (Test)', true ) );
ck( 'the admin stylesheet holds the flag the mark reuses', false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' ), '.wpcpm-inst-mark {' ), true );
ck( 'the dashboards\' stylesheet holds the badge', false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/dashboard.css' ), '.wpcpm-badge {' ), true );

/* ---- the stand-ins -------------------------------------------------------- */

echo "\n=== The stand-ins ===\n";

/**
 * The body of the first `function is_test_name(` in some source: from its opening brace to the
 * brace that closes it, whitespace removed. Empty when the source defines none.
 *
 * @param string $source PHP source.
 * @return string
 */
function rule_body( $source ) {
	if ( 1 !== preg_match( '/function\s+is_test_name\s*\(/', $source, $hit, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}

	$at = $hit[0][1];

	$open = strpos( $source, '{', $at );
	$len  = strlen( $source );
	$deep = 0;

	for ( $i = $open; $i < $len; $i++ ) {
		if ( '{' === $source[ $i ] ) {
			++$deep;
		} elseif ( '}' === $source[ $i ] && 0 === --$deep ) {
			return (string) preg_replace( '/\s+/', '', substr( $source, $open, $i - $open + 1 ) );
		}
	}

	return '';
}

$method = new ReflectionMethod( 'WPCPM_Sponsors_Index', 'is_test_name' );
$real   = array_slice( file( $method->getFileName() ), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 );
$real   = rule_body( implode( '', $real ) );

ck( 'the real rule\'s body was read, and is more than a stub', array( '' !== $real, strlen( $real ) > 100 ), array( true, true ) );
ck( 'the reader finds the real body in the whole class file too', rule_body( (string) file_get_contents( $method->getFileName() ) ), $real );
ck( 'and tells a body that differs by one character', rule_body( str_replace( "'TEST'", "'TEXT'", (string) file_get_contents( $method->getFileName() ) ) ) === $real, false );

// Every file under bin/ that defines the rule must be one listed here, and every one listed must
// define it: a copy nobody listed, a listed copy that is gone, and a copy that drifted from the real
// body each fail. A task that copies the rule into a stand-in adds the file to this list
// (Task 3 landed bin/test-administrators-dashboard.php, Task 4 bin/test-institutions-sponsors-text.php, Task 6
// bin/stubs/ability-readers.php: the answers' count of the Programs card's rows asks the helper, which asks the rule).
$landed = array( 'bin/stubs/ability-readers.php', 'bin/test-administrators-dashboard.php', 'bin/test-institutions-sponsors-text.php' );
$found    = array();

foreach ( array_merge( glob( WPCPM_PLUGIN_DIR . 'bin/*.php' ), glob( WPCPM_PLUGIN_DIR . 'bin/stubs/*.php' ) ) as $path ) {
	$relative = substr( $path, strlen( WPCPM_PLUGIN_DIR ) );
	$source   = (string) file_get_contents( $path );

	if ( 1 === preg_match( '/function\s+is_test_name\s*\(/', $source ) && 'bin/test-test-institutions.php' !== $relative ) {
		$found[ $relative ] = rule_body( $source );
	}
}

sort( $landed );
ksort( $found );

ck( 'the files that define the rule are exactly the ones landed', array_keys( $found ), $landed );

foreach ( $found as $relative => $body ) {
	ck( $relative . ' holds the real body', $body, $real );
}

// The dashboards' Test badge has a rule of its own in the dashboard sheet: without one it is drawn as bare text in a
// badge's padding, and that padding reads as a stray space before the comma that follows a name.
$sheet = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/dashboard.css' );
ck( 'the dashboard sheet draws the Test badge', 1 === preg_match( '/\.wpcpm-badge--test\s*\{[^}]*background\s*:/', $sheet ), true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
