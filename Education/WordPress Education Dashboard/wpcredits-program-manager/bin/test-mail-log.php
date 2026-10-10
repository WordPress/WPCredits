<?php
/**
 * The mail log's store: one table of who, what, when and status, kept 30 days.
 *
 * The store runs on an in-memory SQLite database (bin/stubs/sqlite-wpdb.php), which rewrites what
 * WordPress's SQLite integration rewrites, so a search that is literal here is literal on the local
 * copies; MySQL reads the same backslash escapes.
 *
 * Run from the plugin root:  php bin/test-mail-log.php
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
$GLOBALS['stale']     = array(); // An option a request read before another request deleted it.
$GLOBALS['dbdelta']   = array();
$GLOBALS['cleared']   = array();
$GLOBALS['hooked']    = array();
$GLOBALS['scheduled'] = array();

function __( $s, $d = null ) { return $s; }
function get_option( $k, $d = false ) {
	if ( array_key_exists( $k, $GLOBALS['stale'] ) ) {
		return $GLOBALS['stale'][ $k ];
	}
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
// Core's: true only when there was an option to delete.
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function wp_clear_scheduled_hook( $h ) { $GLOBALS['cleared'][] = $h; unset( $GLOBALS['scheduled'][ $h ] ); return 1; }
function wp_next_scheduled( $h ) { return isset( $GLOBALS['scheduled'][ $h ] ) ? $GLOBALS['scheduled'][ $h ][0] : false; }
function wp_schedule_event( $t, $r, $h ) { $GLOBALS['scheduled'][ $h ] = array( $t, $r ); return true; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = array( $h, $cb, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function sanitize_email( $e ) { $e = trim( (string) $e ); return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : ''; }
// Core's, as 7.1 has it: valid text passes, and invalid bytes are replaced when asked to strip.
function wp_check_invalid_utf8( $s, $strip = false ) {
	$s = (string) $s;
	if ( '' === $s || mb_check_encoding( $s, 'UTF-8' ) ) {
		return $s;
	}
	return $strip ? mb_scrub( $s, 'UTF-8' ) : '';
}
function dbDelta( $sql ) {
	$GLOBALS['dbdelta'][] = $sql;
	if ( empty( $GLOBALS['dbdelta_fails'] ) ) {
		$GLOBALS['wpdb']->create_mail_log();
	}
	return array();
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

require __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-log.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

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

/** A row as the capture writes one, with some columns put in place. */
function row( array $over = array() ) {
	return array_merge(
		array(
			'sent_at'        => '2026-10-10 09:00:00',
			'to_email'       => 'ada@example.test',
			'user_id'        => 7,
			'to_name'        => 'Ada Lin',
			'recipient_type' => 'student',
			'module'         => 'calls',
			'template'       => 'call-booked',
			'subject'        => 'Call booked with your mentor',
			'status'         => 'sent',
			'error'          => '',
			'is_test'        => false,
		),
		$over
	);
}

/** An empty table, and the store told to look for it again. */
function fresh_table() {
	$GLOBALS['wpdb']->drop_mail_log();
	$GLOBALS['wpdb']->create_mail_log();
	WPCPM_Mail_Log::reset();
}

// The hooks, from the plugin's bootstrap.
WPCPM_Mail_Log::init();
$own = array();
foreach ( $GLOBALS['hooked'] as $h ) {
	if ( is_array( $h[1] ) && 'WPCPM_Mail_Log' === $h[1][0] ) {
		$own[] = array( $h[0], $h[1][1], $h[2] );
	}
}
ck( 'init() starts the capture, which listens to wp_mail and both outcomes', array_values( array_intersect( array( 'wp_mail', 'pre_wp_mail', 'wp_mail_succeeded', 'wp_mail_failed', 'shutdown' ), array_column( $GLOBALS['hooked'], 0 ) ) ), array( 'wp_mail', 'pre_wp_mail', 'wp_mail_succeeded', 'wp_mail_failed', 'shutdown' ) );
ck( 'init() hooks the upgrade early on init, the schedule, the cleanup job and both privacy tools', $own, array( array( 'init', 'maybe_upgrade', 5 ), array( 'init', 'schedule', 10 ), array( 'wpcpm_mail_log_purge', 'purge', 10 ), array( 'wp_privacy_personal_data_exporters', 'register_exporter', 10 ), array( 'wp_privacy_personal_data_erasers', 'register_eraser', 10 ) ) );
WPCPM_Mail_Log::schedule();
WPCPM_Mail_Log::schedule();
ck( 'the cleanup is scheduled once, daily', array( count( $GLOBALS['scheduled'] ), $GLOBALS['scheduled']['wpcpm_mail_log_purge'][1] ?? '' ), array( 1, 'daily' ) );
WPCPM_Mail_Log::deactivate();
ck( 'and deactivation clears it', array( isset( $GLOBALS['scheduled']['wpcpm_mail_log_purge'] ), in_array( 'wpcpm_mail_log_purge', $GLOBALS['cleared'], true ) ), array( false, true ) );
$exporters = WPCPM_Mail_Log::register_exporter( array( 'core' => array() ) );
$erasers   = WPCPM_Mail_Log::register_eraser( array() );
ck( 'the exporter and the eraser join WordPress\'s, each calling the store', array( array_keys( $exporters ), $exporters['wpcpm-mail-log']['callback'], $erasers['wpcpm-mail-log']['callback'], $erasers['wpcpm-mail-log']['eraser_friendly_name'] ), array( array( 'core', 'wpcpm-mail-log' ), array( 'WPCPM_Mail_Log', 'export' ), array( 'WPCPM_Mail_Log', 'erase' ), 'Emails the site sent' ) );

// The schema, as MySQL will read it.
$schema = WPCPM_Mail_Log::schema();
foreach ( array( 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT', 'sent_at datetime NOT NULL', 'to_email varchar(191)', 'user_id bigint(20) unsigned DEFAULT NULL', 'to_name varchar(191)', 'recipient_type varchar(20)', 'module varchar(20)', 'template varchar(64)', 'subject varchar(255)', 'status varchar(12)', 'error varchar(255)', 'is_test tinyint(1)', 'delivery varchar(20) DEFAULT NULL', 'delivery_at datetime DEFAULT NULL', 'PRIMARY KEY  (id)', 'KEY sent_at (sent_at)', 'KEY to_email (to_email)', 'KEY module_sent (module,sent_at)', 'KEY template (template)', 'KEY user_id (user_id)', 'utf8mb4' ) as $part ) {
	ck( 'the schema holds ' . $part, false !== strpos( $schema, $part ), true );
}

// No table yet: nothing is written and nothing breaks.
ck( 'without the table, exists() says so', WPCPM_Mail_Log::exists(), false );
ck( 'and add() writes nothing and returns 0', WPCPM_Mail_Log::add( row() ), 0 );
ck( 'and find() returns nothing', WPCPM_Mail_Log::find( array() ), array( 'rows' => array(), 'total' => 0 ) );
ck( 'and the counts are 0', array( WPCPM_Mail_Log::count_since( '2026-01-01 00:00:00' ), WPCPM_Mail_Log::failures_since( '2026-01-01 00:00:00' ), WPCPM_Mail_Log::latest() ), array( 0, 0, null ) );

// An upgrade that fails leaves the version unset, so the next load tries again.
$GLOBALS['dbdelta_fails'] = true;
WPCPM_Mail_Log::maybe_upgrade();
ck( 'a failed creation leaves the version unset', get_option( WPCPM_Mail_Log::OPT_VERSION, 0 ), 0 );
$GLOBALS['dbdelta_fails'] = false;

// The old option's hundred entries move in once.
update_option( WPCPM_Mail_Log::OLD_OPTION, array(
	array( 'time' => 1791500000, 'to' => 'a***@example.test', 'context' => 'call-booked', 'sent' => true ),
	array( 'time' => 1791400000, 'to' => 'b***@example.test', 'context' => 'nobody-knows', 'sent' => false ),
) );
WPCPM_Mail_Log::maybe_upgrade();
ck( 'the upgrade creates the table and stamps the version', array( WPCPM_Mail_Log::exists(), (int) get_option( WPCPM_Mail_Log::OPT_VERSION ) ), array( true, 1 ) );
ck( 'and moves the old entries in, then deletes the option', array( WPCPM_Mail_Log::find( array() )['total'], get_option( WPCPM_Mail_Log::OLD_OPTION, 'gone' ) ), array( 2, 'gone' ) );
$moved = WPCPM_Mail_Log::find( array() )['rows'];
ck( 'a moved entry keeps its masked address, context, area and status, with no subject; an unknown context is Other', array( $moved[0]['to_email'], $moved[0]['template'], $moved[0]['module'], $moved[0]['status'], $moved[0]['subject'], $moved[1]['template'], $moved[1]['module'], $moved[1]['status'] ), array( 'a***@example.test', 'call-booked', 'calls', 'sent', '', 'other', 'other', 'failed' ) );
WPCPM_Mail_Log::maybe_upgrade();
ck( 'a second load does nothing more', WPCPM_Mail_Log::find( array() )['total'], 2 );

// Two first requests: both read the hundred, one deletes the option, and only that one writes.
$GLOBALS['stale'][ WPCPM_Mail_Log::OLD_OPTION ] = array( array( 'time' => 1791500000, 'to' => 'a***@example.test', 'context' => 'call-booked', 'sent' => true ) );
ck( 'a request that read the option after another deleted it moves nothing', array( WPCPM_Mail_Log::migrate_old_option(), WPCPM_Mail_Log::find( array() )['total'] ), array( 0, 2 ) );
$GLOBALS['stale'] = array();

// Once found, the table is not asked for again this request.
$asked = count( $GLOBALS['wpdb']->queries );
WPCPM_Mail_Log::exists();
WPCPM_Mail_Log::exists();
ck( 'exists() asks the database once a request once the table is found', count( $GLOBALS['wpdb']->queries ), $asked );
ck( 'and the question escapes the name for LIKE', count( preg_grep( '/^SELECT name FROM sqlite_master/', $GLOBALS['wpdb']->queries ) ) > 0, true );

fresh_table();

// Rows in and out.
$id = WPCPM_Mail_Log::add( row() );
ck( 'add() returns the new id', $id > 0, true );
$got = WPCPM_Mail_Log::find( array() );
ck( 'find() returns it with every column', array( $got['total'], $got['rows'][0]['to_email'], (int) $got['rows'][0]['user_id'], $got['rows'][0]['template'], (int) $got['rows'][0]['is_test'] ), array( 1, 'ada@example.test', 7, 'call-booked', 0 ) );

$long = str_repeat( 'é', 300 );
WPCPM_Mail_Log::add( row( array( 'subject' => $long, 'error' => $long, 'to_name' => $long, 'user_id' => null ) ) );
$r = WPCPM_Mail_Log::find( array( 'search' => 'éé' ) )['rows'][0];
ck( 'a long subject, error and name are cut at their columns without breaking a character', array( mb_strlen( $r['subject'] ), mb_strlen( $r['error'] ), mb_strlen( $r['to_name'] ), mb_check_encoding( $r['subject'], 'UTF-8' ), $r['user_id'] ), array( 255, 255, 191, true, null ) );

WPCPM_Mail_Log::add( row( array( 'subject' => "Broken \xC3\x28 byte", 'to_email' => 'bytes@example.test' ) ) );
$r = WPCPM_Mail_Log::find( array( 'search' => 'bytes@' ) )['rows'];
ck( 'text that is not valid UTF-8 is made valid before the insert, and the row is written', array( count( $r ), isset( $r[0] ) && mb_check_encoding( $r[0]['subject'], 'UTF-8' ), isset( $r[0] ) && 0 === strpos( $r[0]['subject'], 'Broken ' ) ), array( 1, true, true ) );

// Filters.
fresh_table();
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-01 08:00:00', 'to_email' => 'ben@example.test', 'to_name' => 'Ben Ode', 'recipient_type' => 'mentor' ) ) );
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-05 08:00:00', 'module' => 'wordpress', 'template' => 'wp-password-reset', 'subject' => 'Password Reset, sent on request', 'recipient_type' => 'other' ) ) );
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-09 08:00:00', 'status' => 'failed', 'error' => 'Could not instantiate mail function.', 'subject' => 'The draft was not sent' ) ) );
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-09 09:00:00', 'template' => 'test-student', 'module' => 'invitations', 'is_test' => true, 'subject' => '100% ready, o\'brien a_b a\\b' ) ) );

ck( 'newest first', array_column( WPCPM_Mail_Log::find( array() )['rows'], 'sent_at' ), array( '2026-10-09 09:00:00', '2026-10-09 08:00:00', '2026-10-05 08:00:00', '2026-10-01 08:00:00' ) );
ck( 'by area', WPCPM_Mail_Log::find( array( 'module' => 'wordpress' ) )['total'], 1 );
ck( 'by recipient type', WPCPM_Mail_Log::find( array( 'recipient_type' => 'mentor' ) )['total'], 1 );
ck( 'by status', WPCPM_Mail_Log::find( array( 'status' => 'failed' ) )['total'], 1 );
ck( 'tests, by the test filter', WPCPM_Mail_Log::find( array( 'status' => WPCPM_Mail_Log::FILTER_TEST ) )['total'], 1 );
ck( 'by template', WPCPM_Mail_Log::find( array( 'template' => 'wp-password-reset' ) )['total'], 1 );
ck( 'by time, from inclusive and to exclusive', WPCPM_Mail_Log::find( array( 'from' => '2026-10-05 08:00:00', 'to' => '2026-10-09 08:00:00' ) )['total'], 1 );
ck( 'search finds the address, the name and the subject', array( WPCPM_Mail_Log::find( array( 'search' => 'ben@' ) )['total'], WPCPM_Mail_Log::find( array( 'search' => 'ode' ) )['total'], WPCPM_Mail_Log::find( array( 'search' => 'reset' ) )['total'] ), array( 1, 1, 1 ) );

// The page of rows is not prepared a second time: "sent" holds "%s" once it is a LIKE pattern.
$sent = WPCPM_Mail_Log::find( array( 'search' => 'sent' ) );
ck( 'a search of "sent" returns as many rows as it counts, and raises no error', array( $sent['total'], count( $sent['rows'] ), $GLOBALS['wpdb']->last_error ), array( 2, 2, '' ) );

// The search reads SQL's own characters literally: a % or _ or backslash or quote in it matches only itself.
ck( 'search "100%" finds only the subject that holds it', WPCPM_Mail_Log::find( array( 'search' => '100%' ) )['total'], 1 );
ck( 'search "o\'brien" finds it and raises no error', array( WPCPM_Mail_Log::find( array( 'search' => "o'brien" ) )['total'], $GLOBALS['wpdb']->last_error ), array( 1, '' ) );
ck( 'search "a_b" does not read "_" as any character', WPCPM_Mail_Log::find( array( 'search' => 'a_b' ) )['total'], 1 );
ck( 'search "a%b" finds nothing', WPCPM_Mail_Log::find( array( 'search' => 'a%b' ) )['total'], 0 );
ck( 'search "a\\b" finds only the subject that holds the backslash', WPCPM_Mail_Log::find( array( 'search' => 'a\\b' ) )['total'], 1 );
ck( 'and the search sends no ESCAPE of its own: the database adds the one it reads', count( preg_grep( "/ESCAPE '!'/", $GLOBALS['wpdb']->queries ) ), 0 );

ck( 'paging: 2 per page, page 2', array_column( WPCPM_Mail_Log::find( array(), 2, 2 )['rows'], 'sent_at' ), array( '2026-10-05 08:00:00', '2026-10-01 08:00:00' ) );
ck( 'latest()', WPCPM_Mail_Log::latest()['sent_at'], '2026-10-09 09:00:00' );
ck( 'count_since()', array( WPCPM_Mail_Log::count_since( '2026-10-05 08:00:00' ), WPCPM_Mail_Log::count_since( '2026-10-10 00:00:00' ) ), array( 3, 0 ) );
ck( 'failures_since()', array( WPCPM_Mail_Log::failures_since( '2026-10-09 00:00:00' ), WPCPM_Mail_Log::failures_since( '2026-10-09 08:00:01' ) ), array( 1, 0 ) );

// The cleanup keeps 30 days.
$now = strtotime( '2026-10-31 09:00:00 UTC' ); // The cutoff is 1 October 09:00 UTC.
ck( 'purge() deletes what is older than 30 days and says how many', WPCPM_Mail_Log::purge( $now ), 1 );
ck( 'the rest stay', WPCPM_Mail_Log::find( array() )['total'], 3 );
ck( 'and the run is stamped', (int) get_option( WPCPM_Mail_Log::OPT_PURGED ), $now );
ck( 'purge() uses no DELETE ... LIMIT', count( preg_grep( '/^DELETE.*LIMIT/i', $GLOBALS['wpdb']->queries ) ), 0 );

// Export and erase, by address.
$export = WPCPM_Mail_Log::export( 'ada@example.test' );
ck( 'export lists the address\'s entries with no other person\'s', array( count( $export['data'] ), $export['done'], $export['data'][0]['group_id'] ), array( 3, true, 'wpcpm-mail-log' ) );
$names = array_column( $export['data'][0]['data'], 'name' );
ck( 'each exported entry names when, the email, the subject and the status', $names, array( 'When', 'Email', 'Subject', 'Status' ) );
$erase = WPCPM_Mail_Log::erase( 'ada@example.test' );
ck( 'erase removes them and says so', array( $erase['items_removed'], $erase['done'], WPCPM_Mail_Log::find( array() )['total'] ), array( true, true, 0 ) );
ck( 'and an address with nothing logged removes nothing', WPCPM_Mail_Log::erase( 'nobody@example.test' )['items_removed'], false );

// The statuses in words.
ck( 'status_label()', array_map( array( 'WPCPM_Mail_Log', 'status_label' ), array( 'sent', 'failed', 'unconfirmed', '' ) ), array( 'Handed to the mail server', 'Failed', 'Not confirmed', 'Not recorded' ) );

// Uninstall.
update_option( WPCPM_Mail_Log::OPT_VERSION, 1 );
update_option( WPCPM_Mail_Log::OPT_PURGED, 1 );
WPCPM_Mail_Log::uninstall();
ck( 'uninstall drops the table, deletes both options and clears the job', array( WPCPM_Mail_Log::exists(), get_option( WPCPM_Mail_Log::OPT_VERSION, 'gone' ), get_option( WPCPM_Mail_Log::OPT_PURGED, 'gone' ), in_array( WPCPM_Mail_Log::PURGE_HOOK, $GLOBALS['cleared'], true ) ), array( false, 'gone', 'gone', true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
