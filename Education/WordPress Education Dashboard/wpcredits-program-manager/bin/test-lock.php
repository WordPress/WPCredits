<?php
/**
 * WPCPM_Lock: a lock one request holds over a name, as one row of the options table.
 *
 * What each block pins, and why:
 *
 * - **Taking.** One INSERT IGNORE: the first request writes the row and holds the lock; a second,
 *   while the row is there, writes nothing and is refused. The row is never autoloaded and holds
 *   the time the lock runs to, digits only, which is the holder's token.
 * - **Letting go.** One DELETE matching this request's own value, so a request whose lock ran out
 *   and was taken over never deletes its successor's row.
 * - **Taking over.** One UPDATE matching only a row whose time has passed, writing a later time.
 *   Two requests that both find a lock that ran out, in the moment before either writes, cannot both
 *   hold it: one matches, the other finds the time the first wrote. A row that holds no time reads
 *   as 0 and is taken over, and in a strict SQL session the lock's own digits-only value is read
 *   whole, so nothing is cut short and the take-over still runs.
 * - **Never through the options cache.** The lock asks nothing of get_option() and the rest, so no
 *   cached copy of the row, and no "not there" note in core's notoptions, can answer for it.
 *
 * Run from the plugin root:  php bin/test-lock.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['opts']        = array();
$GLOBALS['option_api']  = array();
$GLOBALS['cache_calls'] = array();

// The options API and the object cache, each call kept: the lock must ask neither.
function get_option( $k, $d = false ) { $GLOBALS['option_api'][] = array( 'get', $k ); return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $a = null ) { $GLOBALS['option_api'][] = array( 'add', $k ); return false; }
function update_option( $k, $v, $a = null ) { $GLOBALS['option_api'][] = array( 'update', $k ); return false; }
function delete_option( $k ) { $GLOBALS['option_api'][] = array( 'delete', $k ); return false; }
function wp_cache_get( $k, $g = '' ) { $GLOBALS['cache_calls'][] = array( 'get', $k, $g ); return false; }
function wp_cache_set( $k, $v, $g = '' ) { $GLOBALS['cache_calls'][] = array( 'set', $k, $g ); return true; }
function wp_cache_delete( $k, $g = '' ) { $GLOBALS['cache_calls'][] = array( 'delete', $k, $g ); return true; }

require_once __DIR__ . '/stubs/options-lock-wpdb.php';

$fail  = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fail, $total;
	++$total;
	if ( $got === $want ) {
		echo 'ok   ' . $label . "\n";
		return;
	}
	++$fail;
	echo 'FAIL ' . $label . "\n";
	echo '       expected: ' . var_export( $want, true ) . "\n";
	echo '       actual:   ' . var_export( $got, true ) . "\n";
}

$file = WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';

if ( ! file_exists( $file ) ) {
	ck( 'includes/class-wpcpm-lock.php exists', false, true );
	printf( "\n%d FAILURE(S) (%d checks)\n", $fail, $total );
	exit( 1 );
}

require_once $file;

/** A fresh options table and a fresh database stand-in. */
function fresh() {
	$GLOBALS['opts']        = array();
	$GLOBALS['option_api']  = array();
	$GLOBALS['cache_calls'] = array();
	$GLOBALS['wpdb']        = new WPCPM_Test_Options_Wpdb();
}

/** The time a row's value says its lock runs to. */
function until_of( $name ) { return WPCPM_Test_Options_Wpdb::leading_number( $GLOBALS['opts'][ $name ] ?? '' ); }

$N = 'wpcpm_invite_lock_7';

echo "=== Taking the lock ===\n";

fresh();
$before = time();
$token  = WPCPM_Lock::take( $N, 600 );

ck( 'the first request takes it, and is handed a token', is_string( $token ) && '' !== $token, true );
ck( 'the row holds the time the lock runs to, now and the lifetime, and the token is that value', array( until_of( $N ) >= $before + 600 && until_of( $N ) <= time() + 600, $GLOBALS['opts'][ $N ] ?? null ), array( true, $token ) );
ck( 'the value is digits only, so the take-over\'s CAST never has to cut it short, in a strict session either', 1 === preg_match( '/^\d{10,}$/', (string) $token ), true );
ck( 'the row is never autoloaded', $GLOBALS['wpdb']->autoload[ $N ] ?? null, 'off' );
ck( 'it was taken with one statement, an INSERT IGNORE on the options table',
	$GLOBALS['wpdb']->statements,
	array( "INSERT IGNORE INTO wp_options (option_name, option_value, autoload) VALUES ('" . $N . "', '" . $token . "', 'off')" ) );

$second = WPCPM_Lock::take( $N, 600 );
ck( 'a second request while the lock is held is refused, and the row is left as the first wrote it', array( $second, $GLOBALS['opts'][ $N ] ), array( '', $token ) );
ck( 'its INSERT IGNORE wrote nothing and its take-over matched nothing, the lock\'s time not having passed',
	array_map( function ( $s ) { return strtok( $s, ' ' ); }, array_slice( $GLOBALS['wpdb']->statements, 1 ) ),
	array( 'INSERT', 'UPDATE' ) );
ck( 'a held lock says when it runs out', WPCPM_Lock::until( $N ), until_of( $N ) );
ck( 'a free one says 0', WPCPM_Lock::until( 'wpcpm_invite_lock_8' ), 0 );
ck( 'a lifetime below one second is one', array( '' !== WPCPM_Lock::take( 'wpcpm_invite_lock_9', 0 ), until_of( 'wpcpm_invite_lock_9' ) <= time() + 1 ), array( true, true ) );

echo "\n=== Letting it go ===\n";

ck( 'a token that is not the row\'s lets nothing go', array( WPCPM_Lock::release( $N, $token . 'x' ), $GLOBALS['opts'][ $N ] ?? null ), array( false, $token ) );
ck( 'nor does no token', array( WPCPM_Lock::release( $N, '' ), isset( $GLOBALS['opts'][ $N ] ) ), array( false, true ) );
ck( 'the request\'s own token deletes the row', array( WPCPM_Lock::release( $N, $token ), isset( $GLOBALS['opts'][ $N ] ) ), array( true, false ) );
ck( 'with one DELETE matching that value', end( $GLOBALS['wpdb']->statements ), "DELETE FROM wp_options WHERE option_name = '" . $N . "' AND option_value = '" . $token . "'" );
ck( 'and the next request takes it at once', '' !== WPCPM_Lock::take( $N, 600 ), true );

echo "\n=== Taking over a lock that ran out ===\n";

// A request that took the lock and died before letting it go (a fatal error, a killed process).
fresh();
$dead                 = (string) ( time() - 1 );
$GLOBALS['opts'][ $N ] = $dead;
$taken                = WPCPM_Lock::take( $N, 600 );

ck( 'a lock whose time has passed is taken over', array( '' !== $taken, $GLOBALS['opts'][ $N ] ?? null ), array( true, $taken ) );
ck( 'and the take-over writes a later value than the row it replaced, now and the lifetime, so the two holders\' tokens differ', (int) $taken > (int) $dead, true );
ck( 'with one UPDATE that matches only a row whose time has passed',
	end( $GLOBALS['wpdb']->statements ),
	"UPDATE wp_options SET option_value = '" . $taken . "' WHERE option_name = '" . $N . "' AND CAST( option_value AS UNSIGNED ) <= " . WPCPM_Test_Options_Wpdb::leading_number( $taken ) - 600 );
ck( 'a holder whose lock ran out and was taken over lets go of nothing: the new holder\'s row stays', array( WPCPM_Lock::release( $N, $dead ), $GLOBALS['opts'][ $N ] ?? null ), array( false, $taken ) );

// A session left in one of MySQL's strict modes, by a db.php drop-in or the incompatible_sql_modes
// filter: a CAST that has to cut a value short fails the statement there. The lock's own value is
// digits only, so a lock it wrote that ran out is taken over all the same.
fresh();
$GLOBALS['wpdb']->strict = true;
$GLOBALS['opts'][ $N ]  = (string) ( time() - 1 );
ck( 'in a strict session, a lock this class wrote that ran out is taken over, the CAST cutting nothing short',
	array( '' !== WPCPM_Lock::take( $N, 600 ), $GLOBALS['wpdb']->errors ), array( true, array() ) );
fresh();
$GLOBALS['wpdb']->strict = true;
$first                   = WPCPM_Lock::take( $N, 600 );
// A lifetime later: the row as take() wrote it, its time now past.
$GLOBALS['opts'][ $N ] = (string) preg_replace( '/^\d+/', (string) ( time() - 1 ), (string) $first );
ck( 'and so is one take() wrote, once its lifetime has passed, the CAST reading the whole value',
	array( '' !== WPCPM_Lock::take( $N, 600 ), $GLOBALS['wpdb']->errors ), array( true, array() ) );

// Two requests find the lock that ran out, in the moment before either writes: the second runs
// whole inside the first's take-over, as a request on another web node can.
fresh();
$GLOBALS['opts'][ $N ]            = (string) ( time() - 30 );
$inner                           = null;
$GLOBALS['wpdb']->before_takeover = function () use ( $N, &$inner ) {
	$inner = WPCPM_Lock::take( $N, 600 );
};
$outer = WPCPM_Lock::take( $N, 600 );

ck( 'of two requests taking over one lock that ran out at the same moment, one holds it and the other is refused',
	array( '' !== (string) $inner, '' !== (string) $outer ),
	array( true, false ) );
ck( 'and the row holds the winner\'s token', $GLOBALS['opts'][ $N ] ?? null, $inner );

fresh();
$GLOBALS['opts'][ $N ] = 'held';
ck( 'a row that holds no time at all reads as 0, and is taken over', '' !== WPCPM_Lock::take( $N, 600 ), true );

fresh();
$GLOBALS['opts'][ $N ] = (string) ( time() + 3600 );
ck( 'a lock that runs to a later time than this request\'s lifetime is still held to that time, as its taker wrote it', array( WPCPM_Lock::take( $N, 600 ), WPCPM_Lock::until( $N ) ), array( '', WPCPM_Test_Options_Wpdb::leading_number( $GLOBALS['opts'][ $N ] ) ) );

echo "\n=== Never through the options cache ===\n";

fresh();
$t = WPCPM_Lock::take( $N, 600 );
WPCPM_Lock::take( $N, 600 );
WPCPM_Lock::until( $N );
WPCPM_Lock::release( $N, $t );
$GLOBALS['opts'][ $N ] = (string) ( time() - 1 );
WPCPM_Lock::take( $N, 600 );

ck( 'taking, being refused, reading, letting go and taking over ask nothing of the options API', $GLOBALS['option_api'], array() );
ck( 'and nothing of the object cache, so no copy and no "not there" note can answer for the row', $GLOBALS['cache_calls'], array() );
ck( 'every statement is one the database answers alone', $GLOBALS['wpdb']->unmodeled, array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
