<?php
/**
 * The ceiling: counted claims in fixed windows, one option per key and window.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The limit-th claim succeeds and the next fails.** Five an hour means five, not four and
 *   not six; a fencepost here is either a form that refuses a legitimate fifth attempt or one
 *   that admits the sixth.
 * - **The first claim is an `add_option()`, and the row is byte-identical for any first claim.**
 *   The real `add_option()` is an INSERT that reports success by rows affected, so a second
 *   first claim writing a different value would overwrite the winner and be told it won. The
 *   dwell token's single use rests on this, so it is asserted from the write the stub records
 *   and from two fresh first claims serialising identically.
 * - **A failed `add_option()` is a refused claim.** Another request created the row in the same
 *   instant; counting on top of a row this request cannot see is how a limit of 1 admits two.
 * - **A new bucket resets, and two keys never share a row.** Once by arithmetic (the previous
 *   bucket's row is untouched and uncounted) and once by the real clock with a one-second
 *   window, because the arithmetic is the thing most likely to be got wrong.
 * - **Nothing is autoloaded.** Asserted through the stub on both the create and the update, and
 *   by reading the source, since a row per key per window that loads on every page is the kind
 *   of option that accumulates until the site is slow.
 * - **`sweep()` removes only what is stale by its own window, and says how many.** A day-long
 *   row and an hour-long row cut at the same moment age at different rates; a row it cannot
 *   read goes too, because nothing else ever will remove it; nothing outside the prefix is
 *   touched.
 * - **`key()` keeps a hash whole and sanitises everything else.** A raw address loses its dots
 *   under `sanitize_key()`, so two addresses could share a key, which is why callers hash
 *   first and why the hash must survive.
 *
 * Run from the plugin root:  php bin/test-ceiling.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']      = array();
$GLOBALS['autoload']  = array();
$GLOBALS['writes']    = array();
$GLOBALS['add_fails'] = false;
$GLOBALS['actions']   = array();

/** The slice of `$wpdb` the ceiling touches: one LIKE over option names. */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	private $args = array();
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { $this->args = $args; return $sql; }
	public function get_col( $sql ) {
		// A LIKE reads `\_` as a literal underscore, so the escaping is undone before matching.
		$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), rtrim( (string) ( $this->args[0] ?? '' ), '%' ) );
		$out    = array();
		foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
			if ( 0 === strpos( $name, $prefix ) ) { $out[] = $name; }
		}
		return $out;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

function __( $s, $d = null ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_hash( $data, $scheme = 'auth' ) { return hash_hmac( 'md5', (string) $data, 'test-salt-' . $scheme ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['actions'][] = array( $hook, $callback ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $a = 'yes' ) {
	// What the real one does: the row exists, the INSERT reports no new row, false. The flag
	// stands in for another request's INSERT landing between this request's read and its write.
	if ( $GLOBALS['add_fails'] || array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	$GLOBALS['writes'][]       = array( 'add', $k, $a );
	return true;
}
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	$GLOBALS['writes'][]       = array( 'update', $k, $a );
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ], $GLOBALS['autoload'][ $k ] ); return true; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';

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

/** A fresh store. */
function reset_store() {
	$GLOBALS['opts']      = array();
	$GLOBALS['autoload']  = array();
	$GLOBALS['writes']    = array();
	$GLOBALS['add_fails'] = false;
}

/** The bucket number the class will use right now for a window, moved by `$offset` windows. */
function bucket_now( $window, $offset = 0 ) {
	return (int) floor( time() / $window ) + $offset;
}

/** The option name the class will use for a key right now, moved by `$offset` windows. */
function name_for( $key, $window, $offset = 0 ) {
	return WPCPM_Ceiling::PREFIX . md5( $key . '|' . bucket_now( $window, $offset ) );
}

/** Seed a row exactly as the class writes it, `$offset` windows from now. Returns its name. */
function seed( $key, $window, $offset, $count ) {
	$name = name_for( $key, $window, $offset );

	$GLOBALS['opts'][ $name ] = array( 'v' => 1, 'window' => $window, 'bucket' => bucket_now( $window, $offset ), 'count' => $count );

	return $name;
}

/** The names under the prefix, sorted. */
function ceiling_names() {
	$names = array_values( array_filter( array_keys( $GLOBALS['opts'] ), function ( $name ) {
		return 0 === strpos( $name, WPCPM_Ceiling::PREFIX );
	} ) );
	sort( $names );
	return $names;
}

/* ---- the limit ------------------------------------------------------------ */

echo "=== The limit-th claim succeeds and the next fails ===\n";

$ip     = wp_hash( '203.0.113.7' );
$key    = 'form:' . $ip;
$bucket = bucket_now( HOUR_IN_SECONDS );
$name   = name_for( $key, HOUR_IN_SECONDS );

$claims = array();
for ( $i = 1; $i <= 7; $i++ ) {
	$claims[] = WPCPM_Ceiling::claim( $key, 5, HOUR_IN_SECONDS );
}

ck( 'five an hour means five: claims one to five succeed, six and seven fail', $claims, array( true, true, true, true, true, false, false ) );
ck( 'the window holds five, not seven', WPCPM_Ceiling::count( $key, HOUR_IN_SECONDS ), 5 );

$dwell = 'dwell:' . wp_hash( 'token-body' );
ck( 'a limit of one is a single use: the dwell token',
    array( WPCPM_Ceiling::claim( $dwell, 1, 12 * HOUR_IN_SECONDS ), WPCPM_Ceiling::claim( $dwell, 1, 12 * HOUR_IN_SECONDS ) ),
    array( true, false ) );

ck( 'a limit of zero admits nobody and writes nothing',
    array( WPCPM_Ceiling::claim( 'closed', 0, HOUR_IN_SECONDS ), array_key_exists( name_for( 'closed', HOUR_IN_SECONDS ), $GLOBALS['opts'] ) ),
    array( false, false ) );
ck( 'and count() reads zero for a key never claimed', WPCPM_Ceiling::count( 'never', HOUR_IN_SECONDS ), 0 );

/* ---- the row --------------------------------------------------------------- */

echo "\n=== The row: one option per key and window, never autoloaded ===\n";

ck( 'the form key and the dwell token are two rows and nothing else was written', count( ceiling_names() ), 2 );
ck( 'the name is the prefix and md5( key|bucket )', in_array( $name, ceiling_names(), true ), true );
ck( 'so nothing about the key is readable from the options table',
    array_values( array_filter( ceiling_names(), function ( $n ) use ( $ip ) {
        return ! preg_match( '/^wpcpm_ceiling_[0-9a-f]{32}$/', $n ) || false !== strpos( $n, $ip ) || false !== strpos( $n, 'form' );
    } ) ),
    array() );
ck( 'the value carries its window and bucket, so sweep() can date it without the name',
    $GLOBALS['opts'][ $name ], array( 'v' => 1, 'window' => HOUR_IN_SECONDS, 'bucket' => $bucket, 'count' => 5 ) );

$writes = array_values( array_filter( $GLOBALS['writes'], function ( $w ) use ( $name ) { return $w[1] === $name; } ) );
ck( 'the first write is add_option() and every later one update_option()',
    array_column( $writes, 0 ), array( 'add', 'update', 'update', 'update', 'update' ) );
ck( 'and each of them passes autoload false', array_values( array_unique( array_column( $writes, 2 ) ) ), array( false ) );
ck( 'the stub agrees: neither row is autoloaded',
    array( $GLOBALS['autoload'][ $name ], $GLOBALS['autoload'][ name_for( $dwell, 12 * HOUR_IN_SECONDS ) ] ),
    array( false, false ) );

/* ---- the test-and-set ------------------------------------------------------ */

echo "\n=== The test-and-set ===\n";

// Two first claims from two requests write byte-identical rows. The real add_option() is an
// INSERT ... ON DUPLICATE KEY UPDATE that reports success by rows affected, so a differing
// value (a timestamp, say) would overwrite the winner's row and be told it won.
reset_store();
WPCPM_Ceiling::claim( 'first', 3, HOUR_IN_SECONDS );
$one = serialize( $GLOBALS['opts'][ name_for( 'first', HOUR_IN_SECONDS ) ] );
reset_store();
WPCPM_Ceiling::claim( 'first', 3, HOUR_IN_SECONDS );
$two = serialize( $GLOBALS['opts'][ name_for( 'first', HOUR_IN_SECONDS ) ] );
ck( 'two first claims write byte-identical rows', $one === $two, true );
ck( 'and the row holds no clock reading of its own', array_keys( $GLOBALS['opts'][ name_for( 'first', HOUR_IN_SECONDS ) ] ), array( 'v', 'window', 'bucket', 'count' ) );

// A failed add_option() is another request's row landing in the same instant. Its claim
// counts; this one is refused rather than counted on top of a row this request cannot see.
reset_store();
$GLOBALS['add_fails'] = true;
ck( 'a first claim that loses the race is refused and writes nothing',
    array( WPCPM_Ceiling::claim( 'race', 1, HOUR_IN_SECONDS ), $GLOBALS['writes'], $GLOBALS['opts'] ),
    array( false, array(), array() ) );
$GLOBALS['add_fails'] = false;

/* ---- buckets --------------------------------------------------------------- */

echo "\n=== A new bucket resets ===\n";

reset_store();

// The previous hour's row, full, seeded as the class writes it, one bucket back.
$old = seed( $key, HOUR_IN_SECONDS, -1, 5 );
ck( 'the previous window counts for nothing now', WPCPM_Ceiling::count( $key, HOUR_IN_SECONDS ), 0 );
ck( 'so a claim succeeds', WPCPM_Ceiling::claim( $key, 5, HOUR_IN_SECONDS ), true );
ck( 'in a row of its own, with the old one untouched',
    array( count( ceiling_names() ), $GLOBALS['opts'][ $old ]['count'], $GLOBALS['opts'][ name_for( $key, HOUR_IN_SECONDS ) ]['count'] ),
    array( 2, 5, 1 ) );

// And by the real clock: a one-second window, filled, then the next second. The three claims
// are redone if the second turned over between them, so the fill is read inside one bucket.
reset_store();
do {
	reset_store();
	$start  = bucket_now( 1 );
	$before = array( WPCPM_Ceiling::claim( 'tick', 2, 1 ), WPCPM_Ceiling::claim( 'tick', 2, 1 ), WPCPM_Ceiling::claim( 'tick', 2, 1 ) );
} while ( bucket_now( 1 ) !== $start );

while ( bucket_now( 1 ) === $start ) {
	usleep( 20000 );
}

ck( 'a one-second window fills, and the next second admits again',
    array( $before, WPCPM_Ceiling::claim( 'tick', 2, 1 ), count( ceiling_names() ) ),
    array( array( true, true, false ), true, 2 ) );

/* ---- keys ------------------------------------------------------------------ */

echo "\n=== Two keys never share a row ===\n";

reset_store();

$a = 'agreement-upload:recAAAAAAAAAAAAAA';
$b = 'agreement-upload:recBBBBBBBBBBBBBB';
WPCPM_Ceiling::claim( $a, 2, DAY_IN_SECONDS );
WPCPM_Ceiling::claim( $a, 2, DAY_IN_SECONDS );

ck( 'one institution at its ceiling does not close the other',
    array( WPCPM_Ceiling::claim( $a, 2, DAY_IN_SECONDS ), WPCPM_Ceiling::claim( $b, 2, DAY_IN_SECONDS ) ),
    array( false, true ) );
ck( 'and the counts are each their own',
    array( WPCPM_Ceiling::count( $a, DAY_IN_SECONDS ), WPCPM_Ceiling::count( $b, DAY_IN_SECONDS ), count( ceiling_names() ) ),
    array( 2, 1, 2 ) );

// The same key under two windows is two rows too: the form's hourly and daily ceilings share
// nothing but the address hash.
ck( 'the same key in an hour window and a day window are two rows',
    array( WPCPM_Ceiling::claim( $key, 1, HOUR_IN_SECONDS ), WPCPM_Ceiling::claim( $key, 1, DAY_IN_SECONDS ), count( ceiling_names() ) ),
    array( true, true, 4 ) );

/* ---- sweep ----------------------------------------------------------------- */

echo "\n=== sweep() removes only what is stale by its own window ===\n";

reset_store();

$kept = array(
	seed( 'h', HOUR_IN_SECONDS, 0, 1 ),
	seed( 'h', HOUR_IN_SECONDS, -1, 1 ),
	seed( 'h', HOUR_IN_SECONDS, -2, 1 ),
	seed( 'd', DAY_IN_SECONDS, -2, 1 ),
);
$stale = array(
	seed( 'h', HOUR_IN_SECONDS, -3, 1 ),
	seed( 'h', HOUR_IN_SECONDS, -240, 1 ),
	seed( 'd', DAY_IN_SECONDS, -3, 1 ),
);
$GLOBALS['opts'][ WPCPM_Ceiling::PREFIX . md5( 'junk' ) ]      = 'not a row';
$GLOBALS['opts']['wpcpm_settings']                             = array( 'base_id' => 'appPROBE0000000001' );
$GLOBALS['opts']['wpcpm_agreement_lock_recAAAAAAAAAAAAAA']     = time();
sort( $kept );

ck( 'sweep() reports how many it removed: three stale rows and one it could not read', WPCPM_Ceiling::sweep(), 4 );
ck( 'the current bucket and the two before it stay, each judged by its own window', ceiling_names(), $kept );
ck( 'nothing outside the prefix is touched',
    array( isset( $GLOBALS['opts']['wpcpm_settings'] ), isset( $GLOBALS['opts']['wpcpm_agreement_lock_recAAAAAAAAAAAAAA'] ) ),
    array( true, true ) );
ck( 'a second sweep finds nothing', WPCPM_Ceiling::sweep(), 0 );
ck( 'and an empty store sweeps to zero rather than to an error', ( reset_store() ?? WPCPM_Ceiling::sweep() ), 0 );

/* ---- uninstall ------------------------------------------------------------- */

echo "\n=== delete_all() ===\n";

reset_store();
seed( 'x', HOUR_IN_SECONDS, 0, 1 );
seed( 'y', DAY_IN_SECONDS, 0, 3 );
seed( 'z', HOUR_IN_SECONDS, -5, 1 );
$GLOBALS['opts']['wpcpm_settings'] = array();

ck( 'delete_all() removes every row, current or not, and reports the count',
    array( WPCPM_Ceiling::delete_all(), array_keys( $GLOBALS['opts'] ) ),
    array( 3, array( 'wpcpm_settings' ) ) );

/* ---- key() ----------------------------------------------------------------- */

echo "\n=== key() ===\n";

$hash = wp_hash( '203.0.113.7' );

ck( 'a wp_hash()ed part is kept whole', WPCPM_Ceiling::key( 'dwell', $hash ), 'dwell:' . $hash );
ck( 'so is an upper-case digest, which sanitize_key() would have lowercased',
    WPCPM_Ceiling::key( strtoupper( $hash ) ), strtoupper( $hash ) );
ck( 'everything else is sanitised and joined with a colon',
    WPCPM_Ceiling::key( 'Agreement Upload', 'rec ABC/def', 7 ), 'agreementupload:recabcdef:7' );

// Why callers hash first: a raw address is not a hash, loses its dots, and two addresses can
// end up as one key.
ck( 'a raw address collapses, which is why callers never pass one',
    array( WPCPM_Ceiling::key( 'form', '203.0.113.7' ), WPCPM_Ceiling::key( 'form', '20.30.11.37' ) ),
    array( 'form:20301137', 'form:20301137' ) );

/* ---- the hook -------------------------------------------------------------- */

echo "\n=== The sweep is hooked ===\n";

WPCPM_Ceiling::init();
ck( 'init() hooks sweep() on CRON_SWEEP',
    $GLOBALS['actions'], array( array( WPCPM_Ceiling::CRON_SWEEP, array( 'WPCPM_Ceiling', 'sweep' ) ) ) );
ck( 'and the hook is prefixed', 0 === strpos( WPCPM_Ceiling::CRON_SWEEP, 'wpcpm_' ), true );

/* ---- read off the source --------------------------------------------------- */

echo "\n=== Read off the source ===\n";

$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php' );

ck( 'every update_option() call passes autoload false',
    array( preg_match_all( '/update_option\( \$/', $source ), preg_match_all( '/update_option\( \$[^;]*?,\s*false\s*\);/s', $source ) ),
    array( 1, 1 ) );
ck( 'every add_option() call passes autoload false',
    array( preg_match_all( '/add_option\( \$/', $source ), preg_match_all( "/add_option\( \\\$[^;]*?,\s*'',\s*false\s*\)/s", $source ) ),
    array( 1, 1 ) );
ck( 'no option is read for autoloading: the class never calls add_option() or update_option() any other way',
    preg_match( '/(add|update)_option\([^;]*(true|\'yes\')\s*\)/', $source ), 0 );
ck( 'the row is dated by its value, never by parsing the name',
    array( false !== strpos( $source, "'window' => (int) \$window" ), false !== strpos( $source, "'bucket' => (int) \$bucket" ), false !== strpos( $source, 'substr( $name' ) ),
    array( true, true, false ) );
ck( 'no dash but the hyphen', preg_match( '/[\x{2013}\x{2014}]/u', $source ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
