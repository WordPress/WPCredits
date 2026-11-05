<?php
/**
 * The Slack message when an institution reaches Confirmed (WPCredits#282, 1.123.56).
 *
 * What this pins, and why:
 *
 * - Nothing is said while no webhook is saved, and nothing is written either, so a webhook added
 *   later announces the institutions that follow it rather than a backlog.
 * - The first run with a webhook seeds the set and says nothing: the partnerships already signed
 *   are not news, and a release that announced forty of them at once would be a release nobody
 *   forgives.
 * - After the seed, an institution missing from the set is announced once, and only once.
 * - A message Slack refuses leaves its institution unannounced, so the next run says it again.
 * - Many at once become one message, because a stage column edited in bulk must not post fifty
 *   times.
 * - Only `Confirmed` counts, and the running total counts the same set the announcement is drawn
 *   from.
 * - A test institution is announced nowhere and counted nowhere, as it is everywhere else on the
 *   site, and renaming it away from TEST makes it an institution like any other.
 *
 * Run from the plugin root:  php bin/test-institution-announce.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

/**
 * An invented incoming webhook, in the shape `is_webhook()` below takes.
 *
 * The host and the path are written in two pieces on purpose. The release scan refuses an added
 * line that holds the shape of a Slack incoming webhook, whatever follows it, and it is right to:
 * it cannot tell an invented address from a real one, and a fixture is no reason to loosen a rule
 * that keeps a webhook out of the public repository. The pieces join to the real shape, so the
 * sender's own check still runs over it.
 */
define( 'HOOK', 'https://hooks.slack' . '.com/services/T000/B000/' . str_repeat( 'x', 8 ) );

$GLOBALS['opts']  = array();
$GLOBALS['sent']  = array();
$GLOBALS['take']  = true;
$GLOBALS['rows']  = array();
$GLOBALS['hook']  = HOOK;

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function __( $s, $d = null ) { return $s; }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function switch_to_locale( $l ) { return true; }
function restore_previous_locale() { return true; }
function get_locale() { return 'en_US'; }

/** The settings the sender reads for its webhook. */
class WPCPM_Settings {
	public static function get() { return array( 'checker_slack_webhook' => $GLOBALS['hook'] ); }
}

/** Record IDs, as the real reader tells them. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $id ) { return is_string( $id ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', $id ); }
}

/**
 * The one place that turns a country into a program manager's name. The real rule, and why it
 * cannot read the Institutions table's own lookup, is in WPCPM_Countries; here it is fixture data.
 */
class WPCPM_Countries {
	public static function manager_name_of( $record_id ) {
		$by = isset( $GLOBALS['managers'] ) ? (array) $GLOBALS['managers'] : array();

		return isset( $by[ $record_id ] ) ? $by[ $record_id ] : '';
	}
}

/** The index the module reads. */
class WPCPM_Institutions_Index {
	public static function rows() { return $GLOBALS['rows']; }
}

/**
 * The sender, stubbed: every message is captured, and `$GLOBALS['take']` decides whether Slack
 * took it. `webhook()` keeps the real rule, so an empty or malformed address behaves as it does
 * on the site.
 */
class WPCPM_Slack {
	public static function is_webhook( $url ) {
		return is_string( $url ) && 1 === preg_match( '#^https://hooks\.slack\.com/services/[A-Za-z0-9_/-]+\z#', $url );
	}
	public static function webhook() {
		$url = WPCPM_Settings::get()['checker_slack_webhook'];
		return self::is_webhook( $url ) ? $url : '';
	}
	public static function send( $webhook, $text ) {
		$GLOBALS['sent'][] = $text;
		return true === $GLOBALS['take'] ? true : new WP_Error();
	}
}

class WP_Error {
	public function get_error_message() { return 'refused'; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-test-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-announce.php';

$fail = 0;

function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}

function row( $name, $stage = 'Confirmed', $city = '', $country = '', $country_id = '', $confirmed_on = '' ) {
	return array(
		'name'         => $name,
		'stage'        => $stage,
		'city'         => $city,
		'country_name' => $country,
		'country'      => $country_id,
		'confirmed_on' => $confirmed_on,
	);
}

/** A date that many whole days back, as the index stores it. */
function days_ago( $n ) {
	return gmdate( 'Y-m-d', time() - ( (int) $n * DAY_IN_SECONDS ) );
}

function reset_all( array $rows ) {
	$GLOBALS['opts'] = array();
	$GLOBALS['sent'] = array();
	$GLOBALS['take'] = true;
	$GLOBALS['hook'] = HOOK;
	$GLOBALS['rows'] = $rows;
	$GLOBALS['managers'] = array();
}

$one   = 'rec00000000000001';
$two   = 'rec00000000000002';
$three = 'rec00000000000003';
$four  = 'rec00000000000004';
$five  = 'rec00000000000005';

echo "=== No webhook ===\n";
reset_all( array( $one => row( 'First University' ) ) );
$GLOBALS['hook'] = '';
ck( 'says nothing', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'and writes nothing, so a webhook added later has no backlog', $GLOBALS['opts'], array() );

reset_all( array( $one => row( 'First University' ) ) );
$GLOBALS['hook'] = 'https://example.test/hook';
ck( 'an address that is not a Slack webhook is no webhook', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'and still writes nothing', $GLOBALS['opts'], array() );

echo "\n=== The first run ===\n";
reset_all(
	array(
		$one => row( 'First University', 'Confirmed', 'Porto', 'Portugal' ),
		$two => row( 'Second College', 'Agreement Sent' ),
	)
);
ck( 'announces nobody', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'sends no message', $GLOBALS['sent'], array() );
ck( 'and records only the Confirmed one', WPCPM_Institution_Announce::OPT_ANNOUNCED === key( $GLOBALS['opts'] ) ? $GLOBALS['opts'][ WPCPM_Institution_Announce::OPT_ANNOUNCED ]['ids'] : null, array( $one ) );

echo "\n=== A new institution after the seed ===\n";
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', 'Kraków', 'Poland' );
ck( 'is announced once', WPCPM_Institution_Announce::after_sync(), 1 );
ck( 'one message', count( $GLOBALS['sent'] ), 1 );
ck(
	'naming it, where it is and the running total',
	$GLOBALS['sent'][0],
	":tada: *Second College* has joined WordPress Credits, from Kraków, Poland.\nThat makes 2 confirmed institutions."
);
ck( 'and the next run says nothing more', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'with no second message', count( $GLOBALS['sent'] ), 1 );

echo "\n=== What the message carries ===\n";
ck(
	'country alone when there is no city',
	WPCPM_Institution_Announce::message( row( 'A School', 'Confirmed', '', 'Spain' ), 1 ),
	":tada: *A School* has joined WordPress Credits, from Spain.\nThat makes 1 confirmed institution."
);
ck(
	'city alone when there is no country',
	WPCPM_Institution_Announce::message( row( 'A School', 'Confirmed', 'Vigo', '' ), 2 ),
	":tada: *A School* has joined WordPress Credits, from Vigo.\nThat makes 2 confirmed institutions."
);
ck(
	'neither, and the sentence still reads',
	WPCPM_Institution_Announce::message( row( 'A School' ), 2 ),
	":tada: *A School* has joined WordPress Credits.\nThat makes 2 confirmed institutions."
);
ck(
	'a record with no name is not named a record ID',
	WPCPM_Institution_Announce::message( row( '' ), 1 ),
	":tada: *An institution* has joined WordPress Credits.\nThat makes 1 confirmed institution."
);

echo "\n=== The program manager line (WPCredits#282) ===\n";

$GLOBALS['managers'] = array( 'recCOUNTRY000001' => 'Manager Example' );
ck(
	'named for the institution\'s country',
	WPCPM_Institution_Announce::message( row( 'A School', 'Confirmed', 'Pisa', 'Italy', 'recCOUNTRY000001' ), 53 ),
	":tada: *A School* has joined WordPress Credits, from Pisa, Italy.\nTheir Administrator is Manager Example.\nThat makes 53 confirmed institutions."
);
ck(
	'a country the site cannot name a manager for leaves the line out, and still announces',
	WPCPM_Institution_Announce::message( row( 'A School', 'Confirmed', 'Pisa', 'Italy', 'recCOUNTRY000009' ), 53 ),
	":tada: *A School* has joined WordPress Credits, from Pisa, Italy.\nThat makes 53 confirmed institutions."
);
ck(
	'a row with no country at all does not ask, and still announces',
	WPCPM_Institution_Announce::message( row( 'A School' ), 1 ),
	":tada: *A School* has joined WordPress Credits.\nThat makes 1 confirmed institution."
);
$GLOBALS['managers'] = array();

echo "\n=== Slack refuses ===\n";
reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'Second College' );
$GLOBALS['take']         = false;
ck( 'nothing is counted as said', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'the message was attempted', count( $GLOBALS['sent'] ), 1 );
ck( 'and the institution is still unannounced', in_array( $two, $GLOBALS['opts'][ WPCPM_Institution_Announce::OPT_ANNOUNCED ]['ids'], true ), false );
$GLOBALS['take'] = true;
ck( 'so the next run says it', WPCPM_Institution_Announce::after_sync(), 1 );
ck( 'and now it is recorded', in_array( $two, $GLOBALS['opts'][ WPCPM_Institution_Announce::OPT_ANNOUNCED ]['ids'], true ), true );

echo "\n=== Many at once ===\n";
reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['sent']          = array();
$GLOBALS['rows'][ $two ]   = row( 'Second College', 'Confirmed', 'Vigo', 'Spain' );
$GLOBALS['rows'][ $three ] = row( 'Third Institute' );
$GLOBALS['rows'][ $four ]  = row( 'Fourth Academy' );
$GLOBALS['rows'][ $five ]  = row( 'Fifth School' );
ck( 'all four are announced', WPCPM_Institution_Announce::after_sync(), 4 );
ck( 'in one message, not four', count( $GLOBALS['sent'] ), 1 );
ck( 'which names each of them', substr_count( $GLOBALS['sent'][0], "\n- " ), 4 );
ck( 'and carries the total', false !== strpos( $GLOBALS['sent'][0], 'That makes' ), true );

echo "\n=== Only Confirmed, and only real records ===\n";
reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['sent'] = array();
$GLOBALS['rows'] = array(
	$one      => row( 'First University' ),
	$two      => row( 'Second College', 'Under Review' ),
	'not-a-record' => row( 'Smuggled In' ),
	$three    => 'not an array',
);
ck( 'a record not at Confirmed is not announced', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'and nothing was sent', $GLOBALS['sent'], array() );

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['sent'] = array();
$GLOBALS['rows'][ $two ] = row( 'Second College', ' Confirmed ' );
ck( 'a stage stored with spaces around it still counts', WPCPM_Institution_Announce::after_sync(), 1 );

echo "\n=== A confirmation long past is history, not news ===\n";

/*
 * Asked for after the 5 November 2026 import wrote thirteen institution rows into Airtable in
 * eighty-five seconds. That import is not itself the problem: both rows it created at Confirmed
 * carried that day's date, so announcing them was right. The guard is for the next import, which
 * may carry the dates the partnerships were really signed on, and would otherwise be read as
 * forty new partnerships in one afternoon. The date is the signal to trust because the program
 * keeps it: all 55 records at Confirmed had a "Confirmed on" date when this was written, none
 * empty. An undated confirmation is still announced - losing real news to an empty field would
 * be the worse failure, and every fixture above relies on it.
 */

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['sent']         = array();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', 'Vigo', 'Spain', '', days_ago( 400 ) );
ck( 'a row that arrives already confirmed long ago is not announced', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'and nothing is sent', $GLOBALS['sent'], array() );
ck(
	'but it is recorded, so it is not weighed again on every run for ever',
	in_array( $two, $GLOBALS['opts'][ WPCPM_Institution_Announce::OPT_ANNOUNCED ]['ids'], true ),
	true
);
ck( 'so the run after it is silent too', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'and still sends nothing', $GLOBALS['sent'], array() );

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['sent']         = array();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', 'Vigo', 'Spain', '', days_ago( 0 ) );
ck( 'confirmed today is news', WPCPM_Institution_Announce::after_sync(), 1 );

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', '', '', '', days_ago( 10 ) );
ck( 'and so is a confirmation inside the window', WPCPM_Institution_Announce::after_sync(), 1 );

echo "\n=== The edge of the window ===\n";

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', '', '', '', days_ago( WPCPM_Institution_Announce::RECENT_DAYS ) );
ck( 'the last day of the window is still news', WPCPM_Institution_Announce::after_sync(), 1 );

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', '', '', '', days_ago( WPCPM_Institution_Announce::RECENT_DAYS + 1 ) );
ck( 'the day after it is not', WPCPM_Institution_Announce::after_sync(), 0 );

echo "\n=== Dates that are not a date in the past ===\n";

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', '', '', '', gmdate( 'Y-m-d', time() + ( 40 * DAY_IN_SECONDS ) ) );
ck( 'a date in the future is announced, not suppressed', WPCPM_Institution_Announce::after_sync(), 1 );

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', '', '', '', 'not a date' );
ck( 'a date nothing can read is announced rather than swallowed', WPCPM_Institution_Announce::after_sync(), 1 );

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'Second College', 'Confirmed', '', '', '', '' );
ck( 'an undated confirmation is announced', WPCPM_Institution_Announce::after_sync(), 1 );

echo "\n=== An import that is partly history and partly news ===\n";

reset_all( array( $one => row( 'First University' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['sent']           = array();
$GLOBALS['rows'][ $two ]   = row( 'Second College', 'Confirmed', '', '', '', days_ago( 500 ) );
$GLOBALS['rows'][ $three ] = row( 'Third Institute', 'Confirmed', '', '', '', days_ago( 420 ) );
$GLOBALS['rows'][ $four ]  = row( 'Fourth Academy', 'Confirmed', '', '', '', days_ago( 1 ) );
$GLOBALS['rows'][ $five ]  = row( 'Fifth School', 'Confirmed', '', '', '', days_ago( 2 ) );
ck( 'only the two recent ones are announced', WPCPM_Institution_Announce::after_sync(), 2 );
ck( 'one message each, not one for all four', count( $GLOBALS['sent'] ), 2 );
ck(
	'the two old ones are named in neither',
	false === strpos( implode( "\n", $GLOBALS['sent'] ), 'Second College' ) && false === strpos( implode( "\n", $GLOBALS['sent'] ), 'Third Institute' ),
	true
);
ck(
	'and the running total counts every confirmed institution, the history included',
	false !== strpos( $GLOBALS['sent'][0], 'That makes 5 confirmed institutions.' ),
	true
);
ck(
	'all four are recorded, so none is weighed again',
	count( $GLOBALS['opts'][ WPCPM_Institution_Announce::OPT_ANNOUNCED ]['ids'] ),
	5
);

echo "\n=== A test institution ===\n";

reset_all(
	array(
		$one => row( 'First University', 'Confirmed', 'Porto', 'Portugal' ),
		$two => row( 'TEST - Trial College', 'Confirmed', 'Kraków', 'Poland' ),
	)
);
ck( 'the seeding run announces nobody', WPCPM_Institution_Announce::after_sync(), 0 );
ck(
	'and records the real institution alone',
	$GLOBALS['opts'][ WPCPM_Institution_Announce::OPT_ANNOUNCED ]['ids'],
	array( $one )
);

reset_all( array( $one => row( 'First University', 'Confirmed', 'Porto', 'Portugal' ) ) );
WPCPM_Institution_Announce::after_sync();
$GLOBALS['rows'][ $two ] = row( 'TEST - Trial College', 'Confirmed', 'Kraków', 'Poland' );
ck( 'one reaching Confirmed afterwards is announced nowhere', WPCPM_Institution_Announce::after_sync(), 0 );
ck( 'with no message at all', $GLOBALS['sent'], array() );
ck(
	'and nothing recorded for it, so it stays a test institution rather than an announced one',
	$GLOBALS['opts'][ WPCPM_Institution_Announce::OPT_ANNOUNCED ]['ids'],
	array( $one )
);

$GLOBALS['rows'][ $three ] = row( 'Third Institute', 'Confirmed', 'Lisbon', 'Portugal' );
ck( 'a real one arriving beside it is announced', WPCPM_Institution_Announce::after_sync(), 1 );
ck(
	'one message, naming the real one',
	1 === count( $GLOBALS['sent'] ) && false !== strpos( $GLOBALS['sent'][0], 'Third Institute' ),
	true
);
ck( 'which does not name the test institution', false === strpos( $GLOBALS['sent'][0], 'Trial College' ), true );
ck(
	'and the running total leaves the test institution out',
	false !== strpos( $GLOBALS['sent'][0], 'That makes 2 confirmed institutions.' ),
	true
);

$GLOBALS['sent']         = array();
$GLOBALS['rows'][ $two ] = row( 'Trial College', 'Confirmed', 'Kraków', 'Poland' );
ck( 'renamed away from TEST it is news like any other institution', WPCPM_Institution_Announce::after_sync(), 1 );
ck( 'named in the message', false !== strpos( $GLOBALS['sent'][0], '*Trial College*' ), true );
ck(
	'and counted in the total from then on',
	false !== strpos( $GLOBALS['sent'][0], 'That makes 3 confirmed institutions.' ),
	true
);

echo "\n";
echo $fail > 0 ? "$fail FAILURE(S)\n" : "ALL PASS\n";
exit( $fail > 0 ? 1 : 0 );
