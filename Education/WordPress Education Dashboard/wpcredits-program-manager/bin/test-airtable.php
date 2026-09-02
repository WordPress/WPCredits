<?php
/**
 * Does the Airtable client stay under Airtable's rate limit, and stay away once told off?
 *
 * Airtable allows five requests per second per base and answers the sixth with a 429 that
 * locks the whole base for thirty seconds - for every client, not only the one that
 * overstepped. Two live-read paths on this site can be reached by Subscriber-based
 * accounts, so a browser can take the base offline for the syncs, the mentors and the
 * managers at once. This suite pins the three things that stop that: the pacing that keeps
 * one process under five a second, the backoff a 429 records where every other process
 * sees it, and the split between a cron tick that may sleep a few seconds out and a page
 * render that must not. It also pins the case-insensitive formula the institution report
 * relies on, and that the client's older answers did not move.
 *
 * Nothing here touches the network or really sleeps: the transport is a queue of canned
 * responses, and the clock, the sleeper and the "may this process block" check are
 * stand-ins the suite drives by hand. The last one is why the check is injectable at all:
 * this script runs under the CLI, which the real check counts as able to wait.
 *
 * Run from the plugin root:  php bin/test-airtable.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']  = array();
$GLOBALS['now']   = 1700000000.0;
$GLOBALS['mode']  = 'web';
$GLOBALS['queue'] = array();
$GLOBALS['sent']  = array();
$GLOBALS['slept'] = array();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_doing_cron() { return ! empty( $GLOBALS['doing_cron'] ); }

/**
 * The transport: hands out canned responses in order and remembers what was sent.
 *
 * An empty queue is a mistake in the test, not in the code, so it throws rather than
 * inventing a response an assertion might then pass on.
 */
function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['sent'][] = array( 'url' => $url, 'args' => $args );

	if ( empty( $GLOBALS['queue'] ) ) {
		throw new Exception( 'wp_remote_request() called with nothing queued: ' . $url );
	}

	return array_shift( $GLOBALS['queue'] );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['response']['code'] ?? 200 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
/**
 * Case-insensitive, as the real one is: it reads through Requests' header dictionary,
 * and Airtable spells the header `Retry-After` while the code asks for `retry-after`.
 */
function wp_remote_retrieve_header( $r, $h ) {
	foreach ( ( is_array( $r ) && isset( $r['headers'] ) ) ? $r['headers'] : array() as $name => $value ) {
		if ( strtolower( $name ) === strtolower( $h ) ) {
			return $value;
		}
	}

	return '';
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';

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

/* ---- the stand-ins ------------------------------------------------------- */

// The clock is a number the suite moves by hand. The sleeper records what it was asked
// for and moves the clock by that much, which is what a real sleep does to a real clock,
// so the code's own "read the time again after pausing" logic sees time pass.
$GLOBALS['clock']    = static function () { return $GLOBALS['now']; };
$GLOBALS['sleeper']  = static function ( $seconds ) { $GLOBALS['slept'][] = $seconds; $GLOBALS['now'] += $seconds; };
$GLOBALS['can_wait'] = static function () { return 'cron' === $GLOBALS['mode']; };

/**
 * A fresh scenario: no recorded backoff, no recent requests, nothing queued or sent.
 *
 * @param string $mode 'web' for a page render, 'cron' for a process that may sleep.
 */
function fresh( $mode = 'web' ) {
	WPCPM_Airtable::for_tests( $GLOBALS['clock'], $GLOBALS['sleeper'], $GLOBALS['can_wait'] );
	$GLOBALS['mode']  = $mode;
	$GLOBALS['now']   = 1700000000.0;
	$GLOBALS['opts']  = array();
	$GLOBALS['queue'] = array();
	$GLOBALS['sent']  = array();
	$GLOBALS['slept'] = array();
}

/**
 * A canned HTTP API response in the shape wp_remote_request() returns.
 */
function response( $code, $body = array( 'records' => array() ), $headers = array() ) {
	return array(
		'response' => array( 'code' => $code ),
		'headers'  => $headers,
		'body'     => is_string( $body ) ? $body : json_encode( $body ),
	);
}
function queue( $response ) { $GLOBALS['queue'][] = $response; }
function sent() { return count( $GLOBALS['sent'] ); }
function error_shape( $e ) {
	if ( ! is_wp_error( $e ) ) {
		return array( 'not an error', $e );
	}
	$data = (array) $e->get_error_data();
	return array( $e->get_error_code(), $data['status'] ?? null, $data['retry_after'] ?? null );
}

$airtable = new WPCPM_Airtable( array( 'api_token' => 'patTEST', 'base_id' => 'appTEST' ) );
$ok_page  = response( 200, array( 'records' => array( array( 'id' => 'rec1', 'fields' => array() ) ) ) );

/* ---- formula_in() -------------------------------------------------------- */

echo "=== formula_in() ===\n";

ck( 'one value is a bare equality',
	$airtable->formula_in( 'Status', array( 'Active' ) ),
	"{Status} = 'Active'" );

ck( 'two values are an OR()',
	$airtable->formula_in( 'Status', array( 'Active', 'Paused' ) ),
	"OR({Status} = 'Active',{Status} = 'Paused')" );

ck( 'nothing to filter on is an empty formula',
	$airtable->formula_in( 'Status', array( '', null ) ),
	'' );

ck( 'an explicit false is the two-argument form, byte for byte',
	$airtable->formula_in( 'Status', array( 'Active', 'Paused' ), false ),
	$airtable->formula_in( 'Status', array( 'Active', 'Paused' ) ) );

// The field side is Airtable's LOWER(); the needle is lowercased here, and the quote
// in the address is still escaped after it.
ck( 'lower wraps the field in LOWER() and lowercases the needle, still escaping a quote',
	$airtable->formula_in( 'Email', array( "Ann.O'Neil@Example.ORG" ), true ),
	"LOWER({Email}) = 'ann.o\\'neil@example.org'" );

ck( 'lower with two values wraps each test',
	$airtable->formula_in( 'Email', array( 'A@X.org', 'B@Y.org' ), true ),
	"OR(LOWER({Email}) = 'a@x.org',LOWER({Email}) = 'b@y.org')" );

ck( 'lower still escapes the field name',
	$airtable->formula_in( 'Odd}Name', array( 'x' ), true ),
	"LOWER({Odd\\}Name}) = 'x'" );

// The needle goes through mb_strtolower(), which folds the L with a stroke that
// strtolower() would have left standing. This pins the mechanism only: the docblock
// says why the flag is still not for names.
ck( 'the needle is folded with mb_strtolower(), not strtolower()',
	$airtable->formula_in( 'Name', array( 'Łódź' ), true ),
	"LOWER({Name}) = 'łódź'" );

/* ---- a 429 is honoured --------------------------------------------------- */

echo "\n=== A 429 records a backoff that every later request honours ===\n";

fresh( 'web' );

queue( response( 429, array( 'error' => array( 'type' => 'RATE_LIMIT_REACHED' ) ), array( 'Retry-After' => '7' ) ) );

$r = $airtable->fetch_page( 'tblX' );

ck( 'a forced 429 with Retry-After 7 is the rate-limited error, status 429, retry_after 7',
	error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 7 ) );
ck( 'and says so in words',
	$r->get_error_message(), 'Airtable asked us to wait 7 seconds before sending more requests.' );
ck( 'the option records when the wait ends',
	$GLOBALS['opts']['wpcpm_airtable_backoff'] ?? null, 1700000007 );
ck( 'backoff_remaining() reads it back',
	WPCPM_Airtable::backoff_remaining(), 7 );
ck( 'one request was sent', sent(), 1 );

$r = $airtable->fetch_page( 'tblX' );

ck( 'the next request inside the window, on a page render, is the same error',
	error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 7 ) );
ck( 'without calling wp_remote_request() at all', sent(), 1 );
ck( 'and without sleeping', $GLOBALS['slept'], array() );

$GLOBALS['mode'] = 'cron';

$r = $airtable->fetch_page( 'tblX' );

ck( 'cron with seven seconds left is refused too - a tick spent asleep did no work',
	error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 7 ) );
ck( 'and sends nothing', sent(), 1 );

$GLOBALS['now'] += 4;
$GLOBALS['mode'] = 'web';

$r = $airtable->fetch_page( 'tblX' );

ck( 'a page render with three seconds left is still refused, and told three',
	error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 3 ) );
ck( 'and still sends nothing', sent(), 1 );

$GLOBALS['mode'] = 'cron';
queue( $ok_page );

$r = $airtable->fetch_page( 'tblX' );

ck( 'cron with three seconds left sleeps them out', $GLOBALS['slept'], array( 3 ) );
ck( 'and then calls', sent(), 2 );

echo "\n=== A later refusal recorded by another process outranks this one's memory ===\n";

fresh( 'web' );
queue( response( 429, array( 'error' => array( 'type' => 'RATE_LIMIT_REACHED', 'message' => 'slow down' ) ), array( 'Retry-After' => '7' ) ) );

$r = $airtable->fetch_page( 'tblX' );

ck( 'this process was refused for seven seconds', error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 7 ) );

// Another process is refused thirteen seconds later and records a window ending twenty
// seconds from now; this process's own window ends in seven.
$GLOBALS['opts']['wpcpm_airtable_backoff'] = $GLOBALS['now'] + 20;
$GLOBALS['now'] += 8;

$r = $airtable->fetch_page( 'tblX' );

ck( 'once its own window has passed, a page render still honours the later record',
	error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 12 ) );
ck( 'sends nothing', sent(), 1 );
ck( 'and leaves the other process\'s record standing', $GLOBALS['opts']['wpcpm_airtable_backoff'] ?? null, $GLOBALS['now'] + 12 );
ck( 'backoff_remaining() reports the later one', WPCPM_Airtable::backoff_remaining(), 12 );

$GLOBALS['now'] += 9;
$GLOBALS['mode'] = 'cron';
queue( $ok_page );

$r = $airtable->fetch_page( 'tblX' );

ck( 'cron sleeps out the three seconds the later record has left', $GLOBALS['slept'], array( 3 ) );
ck( 'then sends', sent(), 2 );
ck( 'and the record is cleared once nobody is waiting', array_key_exists( 'wpcpm_airtable_backoff', $GLOBALS['opts'] ), false );
ck( 'and gets its page', is_array( $r ) ? count( $r['records'] ) : $r, 1 );
ck( 'the request that went through deleted the option',
	array_key_exists( 'wpcpm_airtable_backoff', $GLOBALS['opts'] ), false );
ck( 'and cleared the in-process record', WPCPM_Airtable::backoff_remaining(), 0 );

echo "\n=== The clock passing the window is enough on its own ===\n";

fresh( 'web' );

queue( response( 429, array( 'error' => 'rate limited' ) ) );

$r = $airtable->fetch_page( 'tblX' );

ck( 'a 429 with no Retry-After header waits the thirty seconds Airtable means',
	error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 30 ) );
ck( 'and records now plus thirty', $GLOBALS['opts']['wpcpm_airtable_backoff'], 1700000030 );

$GLOBALS['now'] += 31;
queue( $ok_page );

$r = $airtable->fetch_page( 'tblX' );

ck( 'once the clock passes the window a page render goes through', sent(), 2 );
ck( 'without sleeping', $GLOBALS['slept'], array() );
ck( 'and the option is deleted', array_key_exists( 'wpcpm_airtable_backoff', $GLOBALS['opts'] ), false );
ck( 'and a request that went through recorded nothing new', WPCPM_Airtable::backoff_remaining(), 0 );

fresh( 'web' );
queue( response( 429, array(), array( 'Retry-After' => 'soon' ) ) );

ck( 'an unparsable Retry-After also falls back to thirty',
	error_shape( $airtable->fetch_page( 'tblX' ) ), array( 'wpcpm_airtable_rate_limited', 429, 30 ) );

fresh( 'web' );
queue( response( 429, array(), array( 'retry-after' => array( '12', '40' ) ) ) );

ck( 'a header sent twice is read from its first copy, whatever its case',
	error_shape( $airtable->fetch_page( 'tblX' ) ), array( 'wpcpm_airtable_rate_limited', 429, 12 ) );

echo "\n=== The option is how other processes learn about it ===\n";

// The static is empty: this process never saw a 429. The option was written by another
// one, and that is enough.
fresh( 'web' );
$GLOBALS['opts']['wpcpm_airtable_backoff'] = 1700000000 + 20;

ck( 'a backoff another process recorded is refused before anything is sent',
	error_shape( $airtable->fetch_page( 'tblX' ) ), array( 'wpcpm_airtable_rate_limited', 429, 20 ) );
ck( 'nothing was sent', sent(), 0 );

$GLOBALS['mode'] = 'cron';
$GLOBALS['now'] += 18;
queue( $ok_page );

$airtable->fetch_page( 'tblX' );

ck( 'cron sleeps out the two seconds another process left', $GLOBALS['slept'], array( 2 ) );
ck( 'and sends', sent(), 1 );
ck( 'and deletes the option for everybody', array_key_exists( 'wpcpm_airtable_backoff', $GLOBALS['opts'] ), false );

fresh( 'web' );
$GLOBALS['opts']['wpcpm_airtable_backoff'] = (int) $GLOBALS['now'] - 5;
queue( $ok_page );

$airtable->fetch_page( 'tblX' );

ck( 'a stale option from another process is cleared on the way through', sent(), 1 );
ck( 'and deleted', array_key_exists( 'wpcpm_airtable_backoff', $GLOBALS['opts'] ), false );

// With the real check in place this script is a CLI process, which counts as able to
// wait: the same clause that lets a WP-CLI sync sleep a few seconds out.
WPCPM_Airtable::for_tests( $GLOBALS['clock'], $GLOBALS['sleeper'], null );
$GLOBALS['opts']  = array( 'wpcpm_airtable_backoff' => (int) $GLOBALS['now'] + 2 );
$GLOBALS['queue'] = array( $ok_page );
$GLOBALS['sent']  = array();
$GLOBALS['slept'] = array();

$airtable->fetch_page( 'tblX' );

ck( 'the real check counts a CLI process as able to sleep two seconds out', $GLOBALS['slept'], array( 2 ) );
ck( 'and it sends', sent(), 1 );

/* ---- pacing -------------------------------------------------------------- */

echo "\n=== Pacing keeps one process under five a second ===\n";

fresh( 'web' );

for ( $i = 0; $i < 6; $i++ ) {
	queue( $ok_page );
	$airtable->fetch_page( 'tblX' );
}

ck( 'six back-to-back requests all went out', sent(), 6 );
ck( 'and triggered exactly one pacing sleep', count( $GLOBALS['slept'] ), 1 );
ck( 'of the rest of the second',
	abs( $GLOBALS['slept'][0] - 1 ) < 0.0001, true );

queue( $ok_page );
$airtable->fetch_page( 'tblX' );

ck( 'the seventh, in the fresh second the sleep opened, does not sleep', count( $GLOBALS['slept'] ), 1 );

$GLOBALS['now'] += 1;

for ( $i = 0; $i < 5; $i++ ) {
	queue( $ok_page );
	$airtable->fetch_page( 'tblX' );
}

ck( 'five more a second later do not sleep either', count( $GLOBALS['slept'] ), 1 );
ck( 'twelve requests in all', sent(), 12 );

/* ---- the error travels ---------------------------------------------------- */

echo "\n=== fetch_all() hands the error back unchanged ===\n";

fresh( 'web' );
queue( response( 429, array(), array( 'Retry-After' => '9' ) ) );

$r = $airtable->fetch_all( 'tblX' );

ck( 'fetch_all() returns the rate-limited error as it is',
	error_shape( $r ), array( 'wpcpm_airtable_rate_limited', 429, 9 ) );
ck( 'and stops after the one page', sent(), 1 );

/* ---- nothing older moved ------------------------------------------------- */

echo "\n=== The older answers did not move ===\n";

fresh( 'web' );
queue( response( 403, array( 'error' => array( 'type' => 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ) ) );

$r = $airtable->update_records( 'tblX', array( array( 'id' => 'rec1', 'fields' => array( 'Status' => 'Active' ) ) ) );

ck( 'a 403 on a write is the ordinary error with the write-scope hint, word for word',
	array( $r->get_error_code(), $r->get_error_message(), $r->get_error_data() ),
	array(
		'wpcpm_airtable_error',
		'Airtable request failed (HTTP 403): INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND This was a write, so the token most likely lacks the "data.records:write" scope. Add it at airtable.com/create/tokens, or use report-only mode.',
		array( 'status' => 403 ),
	) );
ck( 'and it was sent as a PATCH', $GLOBALS['sent'][0]['args']['method'], 'PATCH' );
ck( 'a 403 sets no backoff', WPCPM_Airtable::backoff_remaining(), 0 );

queue( response( 403, '' ) );

ck( 'a 403 on a read carries the read-scope hint and "no further detail"',
	$airtable->fetch_page( 'tblX' )->get_error_message(),
	'Airtable request failed (HTTP 403): no further detail Check that the token has the "data.records:read" scope and access to this base.' );

queue( response( 401, array( 'error' => 'unauthorized' ) ) );

ck( 'a 401 on the schema names the schema scope',
	$airtable->fetch_schema()->get_error_message(),
	'Airtable request failed (HTTP 401): unauthorized This was a schema read, so the token most likely lacks the "schema.bases:read" scope.' );

queue( response( 200, 'not json' ) );

ck( 'a 200 that is not JSON is still the bad-response error',
	$airtable->fetch_page( 'tblX' )->get_error_code(), 'wpcpm_airtable_bad_response' );

ck( 'none of which recorded a backoff',
	array( WPCPM_Airtable::backoff_remaining(), array_key_exists( 'wpcpm_airtable_backoff', $GLOBALS['opts'] ) ),
	array( 0, false ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
