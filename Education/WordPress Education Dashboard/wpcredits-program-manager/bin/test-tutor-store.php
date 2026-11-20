<?php
/**
 * The tutor's transcript store (AI Mentor R3): one table of what a student asked, what the AI Mentor
 * answered and the tool calls of a turn, kept 90 days, with the privacy tools' export and erasure by
 * account.
 *
 * Runs on the SQLite stand-in (bin/stubs/sqlite-wpdb.php); the plugin's MySQL schema is pinned as text.
 *
 * Run from the plugin root:  php bin/test-tutor-store.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts']      = array();
$GLOBALS['dbdelta']   = array();
$GLOBALS['cleared']   = array();
$GLOBALS['hooked']    = array();
$GLOBALS['scheduled'] = array();
$GLOBALS['users']     = array();

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function wp_clear_scheduled_hook( $h ) { $GLOBALS['cleared'][] = $h; unset( $GLOBALS['scheduled'][ $h ] ); return 1; }
function wp_next_scheduled( $h ) { return isset( $GLOBALS['scheduled'][ $h ] ) ? $GLOBALS['scheduled'][ $h ][0] : false; }
function wp_schedule_event( $t, $r, $h ) { $GLOBALS['scheduled'][ $h ] = array( $t, $r ); return true; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = array( $h, $cb, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_check_invalid_utf8( $s, $strip = false ) {
	$s = (string) $s;
	if ( '' === $s || mb_check_encoding( $s, 'UTF-8' ) ) {
		return $s;
	}
	return $strip ? mb_scrub( $s, 'UTF-8' ) : '';
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $id => $email ) {
		if ( 'email' === $field && $email === $value ) {
			return (object) array( 'ID' => $id );
		}
	}
	return false;
}
function dbDelta( $sql ) {
	$GLOBALS['dbdelta'][] = $sql;
	$GLOBALS['wpdb']->create_tutor_turns();
	return array();
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

require __DIR__ . '/../includes/modules/class-wpcpm-tutor-store.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/** Put a row's time somewhere else, as a row written earlier. */
function age( $id, $created ) {
	$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'UPDATE wp_wpcpm_tutor_turns SET created_gmt = %s WHERE id = %d', $created, $id ) );
}

function ago( $seconds ) {
	return gmdate( 'Y-m-d H:i:s', time() - $seconds );
}

function fresh_table() {
	$GLOBALS['wpdb']->drop_tutor_turns();
	$GLOBALS['wpdb']->create_tutor_turns();
	WPCPM_Tutor_Store::reset();
}

echo "=== Hooks and names ===\n";

WPCPM_Tutor_Store::init();
$own = array();
foreach ( $GLOBALS['hooked'] as $h ) {
	if ( is_array( $h[1] ) && 'WPCPM_Tutor_Store' === $h[1][0] ) {
		$own[] = array( $h[0], $h[1][1], $h[2] );
	}
}
ck( 'init() hooks the upgrade early on init, the schedule, the purge and both privacy tools', $own, array( array( 'init', 'maybe_upgrade', 5 ), array( 'init', 'schedule', 10 ), array( 'wpcpm_tutor_purge', 'run_purge', 10 ), array( 'wp_privacy_personal_data_exporters', 'register_exporter', 10 ), array( 'wp_privacy_personal_data_erasers', 'register_eraser', 10 ) ) );
ck( 'the names Ruling 3 gives', array( WPCPM_Tutor_Store::SCHEMA_VERSION, WPCPM_Tutor_Store::OPT_VERSION, WPCPM_Tutor_Store::PURGE_HOOK, WPCPM_Tutor_Store::KEEP_DAYS, WPCPM_Tutor_Store::PRIVACY_ID, WPCPM_Tutor_Store::PRIVACY_PAGE, WPCPM_Tutor_Store::table() ), array( 1, 'wpcpm_tutor_store_version', 'wpcpm_tutor_purge', 90, 'wpcpm-tutor', 100, 'wp_wpcpm_tutor_turns' ) );
WPCPM_Tutor_Store::schedule();
WPCPM_Tutor_Store::schedule();
ck( 'the purge is scheduled once, daily', array( count( $GLOBALS['scheduled'] ), $GLOBALS['scheduled']['wpcpm_tutor_purge'][1] ?? '' ), array( 1, 'daily' ) );
WPCPM_Tutor_Store::deactivate();
ck( 'and deactivation clears it', array( isset( $GLOBALS['scheduled']['wpcpm_tutor_purge'] ), in_array( 'wpcpm_tutor_purge', $GLOBALS['cleared'], true ) ), array( false, true ) );
$exporters = WPCPM_Tutor_Store::register_exporter( array( 'core' => array() ) );
$erasers   = WPCPM_Tutor_Store::register_eraser( array() );
ck( 'the exporter and the eraser join WordPress\'s, each calling the store', array( array_keys( $exporters ), $exporters['wpcpm-tutor']['callback'], $erasers['wpcpm-tutor']['callback'] ), array( array( 'core', 'wpcpm-tutor' ), array( 'WPCPM_Tutor_Store', 'export' ), array( 'WPCPM_Tutor_Store', 'erase' ) ) );

echo "\n=== The schema ===\n";

$schema = WPCPM_Tutor_Store::schema();
foreach ( array( 'CREATE TABLE wp_wpcpm_tutor_turns', 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT', 'user_id bigint(20) unsigned NOT NULL', 'student_record varchar(32) NOT NULL', 'role varchar(16) NOT NULL', 'body longtext NOT NULL', 'sources longtext NOT NULL', 'tool varchar(64) NOT NULL', 'created_gmt datetime NOT NULL', 'PRIMARY KEY  (id)', 'KEY user_created (user_id,created_gmt)', 'utf8mb4' ) as $part ) {
	ck( 'the schema holds ' . $part, false !== strpos( $schema, $part ), true );
}

echo "\n=== No table yet ===\n";

ck( 'exists() says no', WPCPM_Tutor_Store::exists(), false );
ck( 'add() writes nothing', WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'Hello' ), 0 );
ck( 'every reader is empty', array( WPCPM_Tutor_Store::recent( 7, 10 ), WPCPM_Tutor_Store::since( 7 ), WPCPM_Tutor_Store::count_since( 7 ), WPCPM_Tutor_Store::accounts(), WPCPM_Tutor_Store::by_record( 'recA' ), WPCPM_Tutor_Store::purge() ), array( array(), array(), 0, array(), array(), 0 ) );

echo "\n=== The upgrade ===\n";

WPCPM_Tutor_Store::maybe_upgrade();
ck( 'maybe_upgrade() creates the table and stamps the version', array( WPCPM_Tutor_Store::exists(), get_option( 'wpcpm_tutor_store_version' ), count( $GLOBALS['dbdelta'] ) ), array( true, 1, 1 ) );
WPCPM_Tutor_Store::maybe_upgrade();
ck( 'and a second run does nothing', count( $GLOBALS['dbdelta'] ), 1 );

echo "\n=== add() and the readers ===\n";

fresh_table();

ck( 'a role outside the three is refused', WPCPM_Tutor_Store::add( 7, 'recA', 'system', 'x' ), 0 );
ck( 'a user ID of 0 is refused', WPCPM_Tutor_Store::add( 0, 'recA', 'student', 'x' ), 0 );
ck( 'and nothing was written', $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_wpcpm_tutor_turns' ), 0 );

$a1 = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'What is a pull request?' );
$a2 = WPCPM_Tutor_Store::add( 7, 'recA', 'tool', 'found 2 steps', array(), 'find_steps' );
$a3 = WPCPM_Tutor_Store::add( 7, 'recA', 'mentor_ai', 'It is a proposal to merge code.', array( array( 'title' => 'Guide A', 'url' => 'https://example.test/guide-a' ) ) );
$a4 = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'Thanks' );
$b1 = WPCPM_Tutor_Store::add( 8, 'recB', 'student', 'Another student' );
ck( 'add() returns each new row\'s id', array( $a1 > 0, $a2 > $a1, $a3 > $a2, $a4 > $a3, $b1 > $a4 ), array( true, true, true, true, true ) );

age( $a1, ago( 4000 ) );
age( $a2, ago( 3000 ) );
age( $a3, ago( 2000 ) );
age( $a4, ago( 1000 ) );
age( $b1, ago( 500 ) );

$recent = WPCPM_Tutor_Store::recent( 7, 10 );
ck( 'recent() gives student and mentor_ai rows, oldest first', array_column( $recent, 'role' ), array( 'student', 'mentor_ai', 'student' ) );
ck( 'a row has id, user_id, student_record, role, body, sources, tool and created_gmt', array_keys( $recent[1] ), array( 'id', 'user_id', 'student_record', 'role', 'body', 'sources', 'tool', 'created_gmt' ) );
ck( 'a row\'s values', array( $recent[1]['id'], $recent[1]['user_id'], $recent[1]['student_record'], $recent[1]['body'], $recent[1]['sources'], $recent[1]['tool'] ), array( $a3, 7, 'recA', 'It is a proposal to merge code.', array( array( 'title' => 'Guide A', 'url' => 'https://example.test/guide-a' ) ), '' ) );
ck( 'created_gmt is a UTC Y-m-d H:i:s', 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $recent[0]['created_gmt'] ), true );
ck( 'recent() with a limit keeps the newest rows, still oldest first', array_column( WPCPM_Tutor_Store::recent( 7, 2 ), 'body' ), array( 'It is a proposal to merge code.', 'Thanks' ) );
ck( 'recent() with a limit of 0 is empty', WPCPM_Tutor_Store::recent( 7, 0 ), array() );
ck( 'recent() never shows another account\'s rows', array_column( WPCPM_Tutor_Store::recent( 8, 10 ), 'body' ), array( 'Another student' ) );
ck( 'a row without sources has an empty array', WPCPM_Tutor_Store::recent( 7, 10 )[0]['sources'], array() );

$by = WPCPM_Tutor_Store::by_record( 'recA' );
ck( 'by_record() gives all three roles, oldest first', array_column( $by, 'role' ), array( 'student', 'tool', 'mentor_ai', 'student' ) );
ck( 'and a tool row carries its tool\'s name', $by[1]['tool'], 'find_steps' );
ck( 'by_record() of another record is that record\'s alone', array_column( WPCPM_Tutor_Store::by_record( 'recB' ), 'user_id' ), array( 8 ) );

ck( 'since() gives student and mentor_ai rows of the window, oldest first', array_column( WPCPM_Tutor_Store::since( 7, 1 ), 'role' ), array( 'student', 'mentor_ai', 'student' ) );
$old = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'A month ago' );
age( $old, ago( 40 * DAY_IN_SECONDS ) );
ck( 'since() with the default 90 days includes it, first', array_column( WPCPM_Tutor_Store::since( 7 ), 'body' )[0], 'A month ago' );
ck( 'and since( 7, 30 ) leaves it out', array_column( WPCPM_Tutor_Store::since( 7, 30 ), 'body' ), array( 'What is a pull request?', 'It is a proposal to merge code.', 'Thanks' ) );
ck( 'count_since() counts student rows only', array( WPCPM_Tutor_Store::count_since( 7, 30 ), WPCPM_Tutor_Store::count_since( 7 ), WPCPM_Tutor_Store::count_since( 8 ), WPCPM_Tutor_Store::count_since( 99 ) ), array( 2, 3, 1, 0 ) );

$more = WPCPM_Tutor_Store::add( 9, 'recC', 'student', 'Question' );
age( $more, ago( 10 ) );
$accounts = WPCPM_Tutor_Store::accounts();
ck( 'accounts() gives one entry per account, newest last-time first', array_column( $accounts, 'user_id' ), array( 9, 8, 7 ) );
ck( 'an entry has user_id, student_record, turns and last', array_keys( $accounts[2] ), array( 'user_id', 'student_record', 'turns', 'last' ) );
ck( 'its turns count student rows only, and its last is the newest row\'s time', array( $accounts[2]['student_record'], $accounts[2]['turns'], $accounts[2]['last'] ), array( 'recA', 3, WPCPM_Tutor_Store::recent( 7, 1 )[0]['created_gmt'] ) );
ck( 'accounts( 30 ) leaves out what is older and counts only the window', WPCPM_Tutor_Store::accounts( 30 )[2]['turns'], 2 );

$tool_only = WPCPM_Tutor_Store::add( 12, 'recD', 'tool', 'x', array(), 'find_steps' );
ck( 'an account with only a tool row has no turns', array_values( array_filter( WPCPM_Tutor_Store::accounts(), function ( $e ) { return 12 === $e['user_id']; } ) )[0]['turns'], 0 );

echo "\n=== The text is kept as written ===\n";

fresh_table();
$long = str_repeat( 'x', 70000 ) . "\xC3\xA9";
$id   = WPCPM_Tutor_Store::add( 7, str_repeat( 'r', 40 ), 'student', $long, array(), str_repeat( 't', 80 ) );
$row  = WPCPM_Tutor_Store::by_record( str_repeat( 'r', 32 ) )[0];
ck( 'a long body is kept whole; the record is cut to 32 and the tool to 64', array( $row['body'] === $long, strlen( $row['student_record'] ), strlen( $row['tool'] ) ), array( true, 32, 64 ) );
$id2 = WPCPM_Tutor_Store::add( 7, 'recA', 'student', "bad \xC3\x28 byte" );
ck( 'invalid UTF-8 is replaced, not refused', $id2 > 0, true );

echo "\n=== The purge, to the second ===\n";

fresh_table();
$now = 1800000000;
$p1  = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'older by a second' );
$p2  = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'exactly 90 days' );
$p3  = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'newer' );
age( $p1, gmdate( 'Y-m-d H:i:s', $now - 90 * DAY_IN_SECONDS - 1 ) );
age( $p2, gmdate( 'Y-m-d H:i:s', $now - 90 * DAY_IN_SECONDS ) );
age( $p3, gmdate( 'Y-m-d H:i:s', $now - 89 * DAY_IN_SECONDS ) );
ck( 'purge() removes the row one second past 90 days and says how many', WPCPM_Tutor_Store::purge( $now ), 1 );
ck( 'the row exactly 90 days old stays, and the newer one', array_column( WPCPM_Tutor_Store::by_record( 'recA' ), 'body' ), array( 'exactly 90 days', 'newer' ) );
ck( 'a second run removes nothing', WPCPM_Tutor_Store::purge( $now ), 0 );
$fresh = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'today' );
age( $fresh, ago( 91 * DAY_IN_SECONDS ) );
ck( 'purge( 0 ) counts back from now', array( WPCPM_Tutor_Store::purge( 0 ), WPCPM_Tutor_Store::purge( 0 ) ), array( 1, 0 ) );
$stale = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'stale' );
age( $stale, ago( 91 * DAY_IN_SECONDS ) );
ck( 'run_purge(), the daily hook, returns nothing and removes the old row', array( WPCPM_Tutor_Store::run_purge(), (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM wp_wpcpm_tutor_turns WHERE body = 'stale'" ) ), array( null, 0 ) );

echo "\n=== The privacy tools ===\n";

fresh_table();
$GLOBALS['users'] = array( 7 => 'student-a@example.test', 8 => 'student-b@example.test' );
$e1 = WPCPM_Tutor_Store::add( 7, 'recA', 'student', 'Is 3 < 4?' );
$e2 = WPCPM_Tutor_Store::add( 7, 'recA', 'tool', 'result text', array(), 'find_steps' );
$e3 = WPCPM_Tutor_Store::add( 7, 'recA', 'mentor_ai', 'Yes.' );
WPCPM_Tutor_Store::add( 8, 'recB', 'student', 'Another account' );

$out = WPCPM_Tutor_Store::export( 'student-a@example.test', 1 );
ck( 'the export is one item per row, done on a short page', array( count( $out['data'] ), $out['done'] ), array( 3, true ) );
ck( 'each item is in the group wpcpm-tutor, labelled AI Mentor conversation', array( $out['data'][0]['group_id'], $out['data'][0]['group_label'], $out['data'][0]['item_id'] ), array( 'wpcpm-tutor', 'AI Mentor conversation', 'wpcpm-tutor-' . $e1 ) );
ck( 'a student row: when, who is You, the text escaped', array( array_column( $out['data'][0]['data'], 'name' ), $out['data'][0]['data'][1]['value'], $out['data'][0]['data'][2]['value'] ), array( array( 'When', 'Who', 'Text' ), 'You', 'Is 3 &lt; 4?' ) );
ck( 'a tool row adds the tool\'s name; who is Tool call, as the Administrators\' view says', array( array_column( $out['data'][1]['data'], 'name' ), $out['data'][1]['data'][1]['value'], $out['data'][1]['data'][3]['value'] ), array( array( 'When', 'Who', 'Text', 'Tool name' ), 'Tool call', 'find_steps' ) );
ck( 'a mentor_ai row is who AI Mentor, with no tool line', array( $out['data'][2]['data'][1]['value'], count( $out['data'][2]['data'] ) ), array( 'AI Mentor', 3 ) );
ck( 'an unknown address exports nothing, done', WPCPM_Tutor_Store::export( 'nobody@example.test' ), array( 'data' => array(), 'done' => true ) );

// F1: the tutor keeps a student's message as typed, markup and all; the export escapes it.
$typed = "<b>x</b> <?php echo 1; ?>\n<!-- wp:paragraph -->\na < b && c > d";
WPCPM_Tutor_Store::add( 7, 'recA', 'student', $typed );
$kept = WPCPM_Tutor_Store::by_record( 'recA' );
$out  = WPCPM_Tutor_Store::export( 'student-a@example.test', 1 );
ck( 'a message of markup, PHP and a comparison is stored as typed, and exported escaped',
	array( end( $kept )['body'], $out['data'][3]['data'][2]['value'] ),
	array( $typed, "&lt;b&gt;x&lt;/b&gt; &lt;?php echo 1; ?&gt;\n&lt;!-- wp:paragraph --&gt;\na &lt; b &amp;&amp; c &gt; d" ) );

for ( $i = 0; $i < 105; $i++ ) {
	WPCPM_Tutor_Store::add( 8, 'recB', 'student', 'q' . $i );
}
$page1 = WPCPM_Tutor_Store::export( 'student-b@example.test', 1 );
$page2 = WPCPM_Tutor_Store::export( 'student-b@example.test', 2 );
ck( 'the export pages at 100 rows', array( count( $page1['data'] ), $page1['done'], count( $page2['data'] ), $page2['done'] ), array( 100, false, 6, true ) );

$gone = WPCPM_Tutor_Store::erase( 'student-a@example.test', 1 );
ck( 'the eraser deletes every row of the account and says so', array( $gone['items_removed'], $gone['items_retained'], $gone['messages'], $gone['done'] ), array( true, false, array(), true ) );
ck( 'the account has no rows left and the other account keeps all of its own', array( count( WPCPM_Tutor_Store::by_record( 'recA' ) ), count( WPCPM_Tutor_Store::by_record( 'recB' ) ) ), array( 0, 106 ) );
$again = WPCPM_Tutor_Store::erase( 'student-a@example.test', 1 );
ck( 'erasing again, or an unknown address, removes nothing and is done', array( $again['items_removed'], $again['done'], WPCPM_Tutor_Store::erase( 'nobody@example.test' )['items_removed'], WPCPM_Tutor_Store::erase( '' )['done'] ), array( false, true, false, true ) );

echo "\n=== Uninstall ===\n";

update_option( WPCPM_Tutor_Store::OPT_VERSION, 1 );
WPCPM_Tutor_Store::schedule();
WPCPM_Tutor_Store::uninstall();
ck( 'uninstall() drops the table, the option and the hook', array( in_array( 'DROP TABLE IF EXISTS wp_wpcpm_tutor_turns', $GLOBALS['wpdb']->queries, true ), get_option( 'wpcpm_tutor_store_version', 'gone' ), isset( $GLOBALS['scheduled']['wpcpm_tutor_purge'] ), WPCPM_Tutor_Store::exists() ), array( true, 'gone', false, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
