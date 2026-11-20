<?php
/**
 * The Airtable copy (Institutions release 2): once the site keeps the record of institutions, the site
 * writes Airtable a copy of each change to an institution, and tries again every hour what Airtable
 * did not take.
 *
 * What is pinned here and why:
 *
 * - **Exactly the changed columns, from the site's values.** `send()` PATCHes one record with the
 *   columns of the keys it is given, read from the index row and the institution's record as they are
 *   now, the country as a list of one record ID and an empty date as null. Outside site mode, for a
 *   lead's key and with no keys it sends nothing.
 * - **Review Focus 3: a copy refused or timed out.** The site's change stands; the record is marked
 *   pending with its keys, since when, the error and the tries; a second failure merges the keys; the
 *   hourly retry derives the cells when it runs, so it writes the newest values and never the ones that
 *   failed; a written retry clears the mark, a failed one keeps it and counts the try; and the
 *   Institutions screen's notice names each school until its write goes.
 * - **One writer at a time for a school.** A copy of a school another request is writing is marked
 *   pending rather than sent beside it, so two PATCHes never race to be the last one Airtable keeps,
 *   and a retry whose school changed while it wrote keeps the mark.
 * - **A promoted lead's record** is made with one POST of its seven columns, and the error comes back
 *   when the base refuses.
 * - **On the clock, and off it.** The hourly hook, scheduled on `init`, taken off by `unschedule()`.
 *
 * Every Airtable call goes to the stand-in client below, which records each one. The index, the
 * record, the lock and the settings are the real files over this suite's options and posts.
 *
 * Run from the plugin root:  php bin/test-institution-copy.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts']     = array();
$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['settings'] = array();
$GLOBALS['sent']     = array();
$GLOBALS['answers']  = array();
$GLOBALS['created']  = true;
$GLOBALS['next_id']  = 100;
$GLOBALS['now']      = 1795000000;
$GLOBALS['cron']     = array();
$GLOBALS['actions']  = array();

class WP_Error {
	public $code = '';
	public $message = '';
	public $data = null;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_name = '', $post_author = 0, $post_content = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
// The notice's How to read this (1.123.104, G5) is for an account that manages the program, which every reader of the
// Institutions screen is.
function current_user_can( $cap ) { return 'wpcpm_manage_program' === $cap && ! empty( $GLOBALS['can_manage'] ); }
function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['actions'][] = array( $h, $c, $p ); }
function register_post_type( $t, $a = array() ) {}
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? $GLOBALS['now'] : (int) $t ); }
function date_i18n( $f, $t = null ) { return gmdate( $f, null === $t ? $GLOBALS['now'] : (int) $t ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
function number_format_i18n( $n ) { return (string) $n; }

/*
 * The options API as WordPress keeps it, cache and all: each request (`$GLOBALS['req']`) has its own object
 * cache, holding every option it read and the list of the ones it found absent (`notoptions`), over the one
 * table (`$GLOBALS['opts']`). `update_option()` of an option this request holds as absent goes through
 * `add_option()`, which then writes over a row another request added meanwhile, as core's upsert does.
 */
$GLOBALS['req']    = 'A';
$GLOBALS['caches'] = array();
function wp_cache_get( $key, $group = '' ) {
	$cache = $GLOBALS['caches'][ $GLOBALS['req'] ][ $group ] ?? array();
	return array_key_exists( $key, $cache ) ? $cache[ $key ] : false;
}
function wp_cache_set( $key, $value, $group = '' ) { $GLOBALS['caches'][ $GLOBALS['req'] ][ $group ][ $key ] = $value; return true; }
function wp_cache_delete( $key, $group = '' ) { unset( $GLOBALS['caches'][ $GLOBALS['req'] ][ $group ][ $key ] ); return true; }
function forget_absent( $k ) {
	$absent = wp_cache_get( 'notoptions', 'options' );
	if ( is_array( $absent ) && isset( $absent[ $k ] ) ) { unset( $absent[ $k ] ); wp_cache_set( 'notoptions', $absent, 'options' ); }
}
function get_option( $k, $d = false ) {
	$absent = wp_cache_get( 'notoptions', 'options' );
	if ( is_array( $absent ) && isset( $absent[ $k ] ) ) { return $d; }
	$cached = wp_cache_get( $k, 'options' );
	if ( false !== $cached ) { return $cached; }
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { wp_cache_set( $k, $GLOBALS['opts'][ $k ], 'options' ); return $GLOBALS['opts'][ $k ]; }
	$absent       = is_array( $absent ) ? $absent : array();
	$absent[ $k ] = true;
	wp_cache_set( 'notoptions', $absent, 'options' );
	return $d;
}
// A write of an option the database refuses (the outside review, O6): while `$GLOBALS['write_fails']` names it, adding,
// updating or deleting it answers false and changes neither the table nor this request's cache, as core's do.
function write_fails( $k ) { return in_array( $k, (array) ( $GLOBALS['write_fails'] ?? array() ), true ); }
function add_option( $k, $v, $deprecated = '', $a = null ) {
	if ( write_fails( $k ) ) { return false; }
	$absent = wp_cache_get( 'notoptions', 'options' );
	if ( ( ! is_array( $absent ) || ! isset( $absent[ $k ] ) ) && false !== get_option( $k ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	wp_cache_set( $k, $v, 'options' );
	forget_absent( $k );
	return true;
}
function update_option( $k, $v, $a = null ) {
	if ( write_fails( $k ) ) { return false; }
	$old = get_option( $k );
	if ( $v === $old ) { return false; }
	if ( false === $old ) { return add_option( $k, $v, '', $a ); }
	$GLOBALS['opts'][ $k ] = $v;
	wp_cache_set( $k, $v, 'options' );
	return true;
}
function delete_option( $k ) { if ( write_fails( $k ) ) { return false; } unset( $GLOBALS['opts'][ $k ] ); wp_cache_delete( $k, 'options' ); return true; }
function wp_next_scheduled( $hook, $args = array() ) {
	foreach ( $GLOBALS['cron'] as $event ) {
		if ( $hook === $event['hook'] ) { return $event['at']; }
	}
	return false;
}
function wp_schedule_event( $at, $recurrence, $hook, $args = array() ) { $GLOBALS['cron'][] = array( 'hook' => $hook, 'at' => $at, 'recurrence' => $recurrence ); return true; }
function wp_schedule_single_event( $at, $hook, $args = array(), $wp_error = false ) { return true; }
function wp_clear_scheduled_hook( $hook, $args = array() ) {
	$GLOBALS['cron'] = array_values( array_filter( $GLOBALS['cron'], function ( $event ) use ( $hook ) { return $hook !== $event['hook']; } ) );
	return 1;
}


function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );
	$id      = $GLOBALS['next_id']++;
	$post    = new WP_Post();

	$post->ID          = $id;
	$post->post_type   = isset( $postarr['post_type'] ) ? $postarr['post_type'] : 'post';
	$post->post_status = isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'draft';
	$post->post_title  = isset( $postarr['post_title'] ) ? $postarr['post_title'] : '';

	$GLOBALS['posts'][ $id ] = $post;

	return $id;
}
function wp_update_post( $postarr, $wp_error = false ) { return (int) $postarr['ID']; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function get_post_meta( $id, $key = '', $single = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value ); return true; }
function update_meta_cache( $type, $ids ) { return true; }
function get_posts( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['post_status'] ) && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) { continue; }
		if ( isset( $args['meta_key'] ) && (string) get_post_meta( $post->ID, $args['meta_key'], true ) !== (string) $args['meta_value'] ) { continue; }
		$out[] = $post;
	}

	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) { $out = array_slice( $out, 0, (int) $args['numberposts'] ); }

	return 'ids' === ( isset( $args['fields'] ) ? $args['fields'] : '' ) ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
}

/* ---- the other pieces, at their contracts --------------------------------- */

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}

class WPCPM_Institutions_Sync {
	public static function fields() {
		return array(
			'name'           => 'Name',
			'stage'          => 'Current Stage',
			'country'        => 'Country',
			'city'           => 'City',
			'website'        => 'Website',
			'contact_person' => 'Contact Person',
			'contact_email'  => 'Contact Email',
			'confirmed_on'   => 'Confirmed on',
		);
	}
	public static function read_again_soon() {}
	public static function is_running() { return false; }
}

/** The switch's stand-in for the agreement path: no agreement option, no change owed (T1). */
class WPCPM_Institution_Agreement {
	public static function option( $record_id ) { return null; }
	public static function forget_index_debts() { return true; }
}

class WPCPM_Settings {
	public static function get() { return $GLOBALS['settings']; }
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

/**
 * The stand-in client: records every request it is asked to make, and answers from the queue in
 * `answers` (true for "taken", a WP_Error, or an array returned as it is), "taken" once that is empty.
 */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
	public function update_records( $table, array $records ) {
		$GLOBALS['sent'][] = array( 'PATCH', $table, $records );

		if ( isset( $GLOBALS['during_patch'] ) && is_callable( $GLOBALS['during_patch'] ) ) {
			$during = $GLOBALS['during_patch'];
			unset( $GLOBALS['during_patch'] );
			$during();
		}

		$answer = empty( $GLOBALS['answers'] ) ? true : array_shift( $GLOBALS['answers'] );

		if ( true !== $answer ) { return $answer; }

		$out = array();
		foreach ( $records as $record ) { $out[ $record['id'] ] = true; }

		return $out;
	}
	public function create_records( $table, array $records ) {
		$GLOBALS['sent'][] = array( 'POST', $table, $records );

		return true === $GLOBALS['created'] ? array( 'recNEW00000000001' ) : $GLOBALS['created'];
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-copy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-switch.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
// No guide page unless a check sets one, so the notice's markup is as it was (1.123.104).
require_once __DIR__ . '/stubs/guide-jobs.php';
if ( ! function_exists( 'wp_sprintf_l' ) ) {
	function wp_sprintf_l( $pattern, $args ) { $args = array_values( (array) $args ); if ( count( $args ) < 2 ) { return implode( '', $args ); } $last = array_pop( $args ); return implode( ', ', $args ) . ( count( $args ) > 1 ? ', and ' : ' and ' ) . $last; }
}

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

const SCHOOL  = 'recEXA00000000001';
const SECOND  = 'recEXA00000000002';
const COUNTRY = 'recEXA00000000009';
const TABLE   = 'tblEXA00000000001';
const DAY     = 86400;

/** Two schools in site mode: what the index and each school's record hold. */
function reset_world() {
	$GLOBALS['opts']     = array();
	$GLOBALS['caches']   = array();
	$GLOBALS['req']      = 'A';
	$GLOBALS['posts']    = array();
	$GLOBALS['pmeta']    = array();
	$GLOBALS['sent']     = array();
	$GLOBALS['answers']  = array();
	$GLOBALS['created']  = true;
	$GLOBALS['next_id']  = 100;
	$GLOBALS['now']      = 1795000000;
	$GLOBALS['settings'] = array(
		'institutions_table'      => TABLE,
		'institutions_name_field' => 'Name',
		'institutions_on_site'    => true,
	);

	WPCPM_Institutions_Index::write(
		array(
			SCHOOL => array(
				'record_id'      => SCHOOL,
				'name'           => 'Example University of the North',
				'stage'          => 'Call Scheduled',
				'country'        => COUNTRY,
				'country_name'   => 'Exampleland',
				'city'           => 'Example City',
				'website'        => 'https://north.example.test',
				'contact_person' => 'Example Person',
				'contact_email'  => 'person@example.test',
				'confirmed_on'   => '',
			),
			SECOND => array(
				'record_id' => SECOND,
				'name'      => 'Second Example School',
				'stage'     => 'Lead Contacted',
			),
		),
		1794000000
	);

	WPCPM_Institution_Record::set(
		SCHOOL,
		array(
			'next_step'    => 'Send the agreement',
			'follow_up'    => '2026-12-01',
			'last_contact' => '',
		)
	);
}

/** Change a talks field on the site, as a save on the Talks card does. */
function site_sets( $record, array $answers ) {
	WPCPM_Institution_Record::set( $record, $answers );
	WPCPM_Institution_Record::forget_cached( $record );
}

function patches() {
	return array_values( array_filter( $GLOBALS['sent'], function ( $s ) { return 'PATCH' === $s[0]; } ) );
}

function pending_of( $record ) {
	$all = WPCPM_Institution_Copy::pending();

	return isset( $all[ $record ] ) ? $all[ $record ] : null;
}

/** Move every mark's times back, as the hours between two runs do: its last try and when it was made. */
function age( $seconds ) {
	$stored = get_option( WPCPM_Institution_Copy::OPT_PENDING, array() );

	foreach ( $stored as $record => $entry ) {
		$stored[ $record ]['since'] = (int) $entry['since'] - $seconds;
		$stored[ $record ]['tried'] = (int) $entry['tried'] > 0 ? (int) $entry['tried'] - $seconds : 0;
	}

	update_option( WPCPM_Institution_Copy::OPT_PENDING, $stored, false );
}

/** Store marks as they are, record ID => the fields a mark keeps. */
function store_marks( array $marks ) {
	$stored = array();

	foreach ( $marks as $record => $mark ) {
		$stored[ $record ] = array_merge( array( 'keys' => array( 'city' ), 'since' => time() - DAY, 'error' => 'refused', 'tries' => 1, 'tried' => time() - 3 * HOUR_IN_SECONDS, 'missing' => 0 ), $mark );
	}

	update_option( WPCPM_Institution_Copy::OPT_PENDING, $stored, false );
}

function notice() {
	ob_start();
	WPCPM_Institution_Copy::render_pending_notice();

	return ob_get_clean();
}

$timeout = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 15001 milliseconds' );
$refused = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): INVALID_VALUE_FOR_COLUMN' );

echo "\n-- the names later tasks call ----------------------------------------------\n";

ck(
	'COLUMNS: thirteen keys, each the Institutions column it is written to (the outside review, O9: estimated students and the help desk link)',
	WPCPM_Institution_Copy::COLUMNS,
	array(
		'name'           => 'Name',
		'stage'          => 'Current Stage',
		'country'        => 'Country',
		'city'           => 'City',
		'website'        => 'Website',
		'contact_person' => 'Contact Person',
		'contact_email'  => 'Contact Email',
		'confirmed_on'   => 'Confirmed on',
		'next_step'      => 'Next Step',
		'follow_up'      => 'Follow-Up Date',
		'last_contact'   => 'Last Contact Date',
		'estimated_students' => 'Estimated number of students who may be interested',
		'help_desk'      => 'FreeScout',
	)
);
ck( 'the option, the hook and the lock', array( WPCPM_Institution_Copy::OPT_PENDING, WPCPM_Institution_Copy::CRON_HOURLY, WPCPM_Institution_Copy::LOCK ), array( 'wpcpm_institution_copy_pending', 'wpcpm_institution_copy_hourly', 'wpcpm_institution_copy_lock' ) );
ck( 'the columns the sync knows are the sync\'s spelling', array_intersect_key( WPCPM_Institution_Copy::COLUMNS, WPCPM_Institutions_Sync::fields() ), WPCPM_Institutions_Sync::fields() );

echo "\n-- cells_for(): the site's values now -------------------------------------\n";

reset_world();

ck(
	'every column from the index row and the record, the country as a one-ID link and an empty date as null',
	WPCPM_Institution_Copy::cells_for( SCHOOL, array_keys( WPCPM_Institution_Copy::COLUMNS ) ),
	array(
		'Name'              => 'Example University of the North',
		'Current Stage'     => 'Call Scheduled',
		'Country'           => array( COUNTRY ),
		'City'              => 'Example City',
		'Website'           => 'https://north.example.test',
		'Contact Person'    => 'Example Person',
		'Contact Email'     => 'person@example.test',
		'Confirmed on'      => null,
		'Next Step'         => 'Send the agreement',
		'Follow-Up Date'    => '2026-12-01',
		'Last Contact Date' => null,
		'Estimated number of students who may be interested' => null,
		'FreeScout'         => null,
	)
);
site_sets( SCHOOL, array( 'estimated_students' => '40', 'help_desk' => 'https://helpdesk.example.test/conversation/7' ) );
ck( 'o9_cells: estimated students as a whole number and the help desk link as text, from the record',
	WPCPM_Institution_Copy::cells_for( SCHOOL, array( 'estimated_students', 'help_desk' ) ),
	array( 'Estimated number of students who may be interested' => 40, 'FreeScout' => 'https://helpdesk.example.test/conversation/7' ) );
site_sets( SCHOOL, array( 'estimated_students' => '', 'help_desk' => '   ' ) );
ck( 'o9_cells_null: each empty one is null, which clears the cell',
	WPCPM_Institution_Copy::cells_for( SCHOOL, array( 'estimated_students', 'help_desk' ) ),
	array( 'Estimated number of students who may be interested' => null, 'FreeScout' => null ) );
site_sets( SCHOOL, array( 'estimated_students' => 'about forty' ) );
ck( 'o9_cells_not_a_number: an estimate that is no whole number is null, never text in a number field',
	WPCPM_Institution_Copy::cells_for( SCHOOL, array( 'estimated_students' ) ),
	array( 'Estimated number of students who may be interested' => null ) );
reset_world();
ck( 'only the keys asked for, a key that is not a column left out', WPCPM_Institution_Copy::cells_for( SCHOOL, array( 'next_step', 'stage', 'nonsense' ) ), array( 'Current Stage' => 'Call Scheduled', 'Next Step' => 'Send the agreement' ) );
ck( 'a school with no country and no record: an empty link, empty text, null dates', WPCPM_Institution_Copy::cells_for( SECOND, array( 'country', 'next_step', 'follow_up' ) ), array( 'Country' => array(), 'Next Step' => '', 'Follow-Up Date' => null ) );
ck( 'a school the index does not hold has no cells, so nothing could blank its columns', WPCPM_Institution_Copy::cells_for( 'recEXA00000000077', array( 'name', 'city' ) ), array() );

$GLOBALS['settings']['institutions_name_field'] = 'Institution Name';
ck( 'the name column follows institutions_name_field, as Details::write() does', WPCPM_Institution_Copy::cells_for( SCHOOL, array( 'name' ) ), array( 'Institution Name' => 'Example University of the North' ) );

echo "\n-- send(): exactly the changed columns ------------------------------------\n";

reset_world();

ck( 'send() says true when Airtable took it', WPCPM_Institution_Copy::send( SCHOOL, array( 'stage', 'next_step' ) ), true );
ck(
	'one PATCH of the school, with exactly Current Stage and Next Step, the site\'s values',
	$GLOBALS['sent'],
	array( array( 'PATCH', TABLE, array( array( 'id' => SCHOOL, 'fields' => array( 'Current Stage' => 'Call Scheduled', 'Next Step' => 'Send the agreement' ) ) ) ) )
);
ck( 'and nothing is pending', WPCPM_Institution_Copy::pending(), array() );

foreach (
	array(
		'outside site mode' => array( SCHOOL, array( 'stage' ), false ),
		'for a lead\'s key' => array( 'lead-5', array( 'stage' ), true ),
		'with no keys'      => array( SCHOOL, array(), true ),
		'with no column among the keys' => array( SCHOOL, array( 'contact_role' ), true ),
	) as $why => $case
) {
	reset_world();
	$GLOBALS['settings']['institutions_on_site'] = $case[2];

	$said = WPCPM_Institution_Copy::send( $case[0], $case[1] );

	ck( sprintf( '%s nothing is sent, nothing is marked, and send() says false', $why ), array( $said, $GLOBALS['sent'], WPCPM_Institution_Copy::pending() ), array( false, array(), array() ) );
}

echo "\n-- Review Focus 3: a copy refused or timed out ----------------------------\n";

reset_world();
$GLOBALS['answers'] = array( $timeout );

$started = time();
$said    = WPCPM_Institution_Copy::send( SCHOOL, array( 'stage', 'next_step' ) );
$first   = pending_of( SCHOOL );

ck( 'a timed-out PATCH: send() says false', $said, false );
ck( 'the site\'s change stands: the index and the record are as the site wrote them', array( WPCPM_Institutions_Index::row( SCHOOL )['stage'], WPCPM_Institution_Record::get( SCHOOL )['next_step'] ), array( 'Call Scheduled', 'Send the agreement' ) );
ck(
	'the school is pending with both keys, since now, the error and one try',
	pending_of( SCHOOL ),
	array( 'keys' => array( 'stage', 'next_step' ), 'since' => $first['since'], 'error' => 'cURL error 28: Operation timed out after 15001 milliseconds', 'tries' => 1, 'tried' => $first['tried'], 'missing' => 0 )
);
ck( 'since is the moment it was marked, and tried the moment it failed', array( $first['since'] >= $started && $first['since'] <= time(), $first['tried'] >= $first['since'] && $first['tried'] <= time() ), array( true, true ) );
ck( 'the pending list holds values nowhere, only keys', false === strpos( serialize( get_option( WPCPM_Institution_Copy::OPT_PENDING ) ), 'Send the agreement' ), true );

$GLOBALS['answers'] = array( $refused );
site_sets( SCHOOL, array( 'follow_up' => '2026-12-08' ) );

ck( 'a second failed send, of follow_up, says false', WPCPM_Institution_Copy::send( SCHOOL, array( 'follow_up' ) ), false );
ck(
	'it asked for the pending keys with its own, so a send that goes writes everything owed',
	patches()[1][2][0]['fields'],
	array( 'Current Stage' => 'Call Scheduled', 'Next Step' => 'Send the agreement', 'Follow-Up Date' => '2026-12-08' )
);
ck(
	'the keys merge, the first since stays, the error is the newest, and the try is counted',
	pending_of( SCHOOL ),
	array( 'keys' => array( 'stage', 'next_step', 'follow_up' ), 'since' => $first['since'], 'error' => 'Airtable request failed (HTTP 422): INVALID_VALUE_FOR_COLUMN', 'tries' => 2, 'tried' => pending_of( SCHOOL )['tried'], 'missing' => 0 )
);

reset_world();
$GLOBALS['answers'] = array( array() );

ck( 'an empty answer is a refusal too', array( WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) ), pending_of( SCHOOL )['keys'], pending_of( SCHOOL )['error'] ), array( false, array( 'city' ), 'Airtable did not take the change.' ) );

echo "\n-- retry(): the newest values, then the mark cleared or kept --------------\n";

reset_world();
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'stage', 'next_step' ) );

// The next save of next_step, made on the site while Airtable is still down: its own send fails too.
site_sets( SCHOOL, array( 'next_step' => 'Book the second call' ) );
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'next_step' ) );

age( 5 * HOUR_IN_SECONDS );
$first              = pending_of( SCHOOL );
$GLOBALS['sent']    = array();
$GLOBALS['answers'] = array( $timeout );

ck( 'a retry Airtable does not take writes none', WPCPM_Institution_Copy::retry(), 0 );
ck( 'it asked with the site\'s newest values, not the ones that failed', patches()[0][2][0]['fields'], array( 'Current Stage' => 'Call Scheduled', 'Next Step' => 'Book the second call' ) );
ck( 'and the school stays pending, its try counted', array( pending_of( SCHOOL )['keys'], pending_of( SCHOOL )['tries'], pending_of( SCHOOL )['since'] ), array( array( 'stage', 'next_step' ), 3, $first['since'] ) );

reset_world();
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'stage', 'next_step' ) );
site_sets( SCHOOL, array( 'next_step' => 'Book the second call' ) );

age( 2 * HOUR_IN_SECONDS );
$GLOBALS['sent']    = array();
$GLOBALS['answers'] = array( $timeout );

ck( 'with one failed send and a save that sent nothing, a retry that fails keeps the school, tries 2', array( WPCPM_Institution_Copy::retry(), pending_of( SCHOOL )['tries'], patches()[0][2][0]['fields']['Next Step'] ), array( 0, 2, 'Book the second call' ) );

$GLOBALS['sent'] = array();

ck( 'two tries in, the school is not tried again within four hours', array( WPCPM_Institution_Copy::retry(), $GLOBALS['sent'] ), array( 0, array() ) );

age( 4 * HOUR_IN_SECONDS + 60 );

ck( 'the next retry Airtable takes writes one school', WPCPM_Institution_Copy::retry(), 1 );
ck( 'with the newest values', $GLOBALS['sent'], array( array( 'PATCH', TABLE, array( array( 'id' => SCHOOL, 'fields' => array( 'Current Stage' => 'Call Scheduled', 'Next Step' => 'Book the second call' ) ) ) ) ) );
ck( 'and the mark is cleared', WPCPM_Institution_Copy::pending(), array() );

echo "\n-- M2: a pending school the index no longer holds ------------------------\n";

reset_world();
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) );
age( 3 * HOUR_IN_SECONDS );
WPCPM_Institutions_Index::write( array( SECOND => WPCPM_Institutions_Index::row( SECOND ) ), 1794000000 );
$GLOBALS['sent'] = array();

$retried = WPCPM_Institution_Copy::retry();
$left    = pending_of( SCHOOL );

ck( 'missing_row_keeps_mark: nothing is sent to blank its columns, the mark stays and counts a try, so it backs off', array( $retried, $GLOBALS['sent'], $left['keys'], $left['tries'], $left['missing'], $left['tried'] > time() - 60 ), array( 0, array(), array( 'city' ), 2, 1, true ) );

store_marks( array( SCHOOL => array( 'missing' => 23, 'tries' => 23, 'tried' => time() - 2 * DAY ) ) );
WPCPM_Institutions_Index::write( array(), 1794000000 );

ck( 'missing_row_empty_index_keeps: an empty index (the state the sync\'s guard stops on) never drops a mark, nor counts it missing', array( WPCPM_Institution_Copy::retry(), pending_of( SCHOOL )['missing'], pending_of( SCHOOL )['tries'] ), array( 0, 23, 24 ) );

store_marks( array( SCHOOL => array( 'missing' => 23, 'tries' => 23, 'tried' => time() - 2 * DAY ) ) );
WPCPM_Institutions_Index::write( array( SECOND => array( 'record_id' => SECOND, 'name' => 'Second Example School' ) ), 1794000000 );

ck( 'missing_row_dropped_after_24: the 24th try that finds it missing, the index holding rows, drops the mark', array( WPCPM_Institution_Copy::retry(), WPCPM_Institution_Copy::pending(), $GLOBALS['sent'] ), array( 0, array(), array() ) );

reset_world();
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) );
$GLOBALS['settings']['institutions_on_site'] = false;

ck( 'outside site mode the retry sends nothing and keeps the list', array( WPCPM_Institution_Copy::retry(), count( $GLOBALS['sent'] ), array_keys( WPCPM_Institution_Copy::pending() ) ), array( 0, 1, array( SCHOOL ) ) );

echo "\n-- one writer at a time for a school ----------------------------------------\n";

reset_world();
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'next_step' ) );
age( 3 * HOUR_IN_SECONDS );
$GLOBALS['sent'] = array();

// While the retry's PATCH is out, the school's next step is saved again on the site, and that save's
// own send finds the school's copy being written: it is marked, not sent beside it.
$GLOBALS['during_patch'] = function () {
	site_sets( SCHOOL, array( 'next_step' => 'Sign the agreement' ) );
	$GLOBALS['inner_said'] = WPCPM_Institution_Copy::send( SCHOOL, array( 'next_step' ) );
};

ck( 'the retry\'s PATCH was taken, but the school changed while it was out, so it is not counted written', WPCPM_Institution_Copy::retry(), 0 );
ck( 'the save during it sent nothing of its own and said false', array( $GLOBALS['inner_said'], count( $GLOBALS['sent'] ) ), array( false, 1 ) );
ck( 'and the school stays pending for its newest next step', pending_of( SCHOOL )['keys'], array( 'next_step' ) );

$GLOBALS['sent'] = array();

ck( 'the next retry writes the newest value and clears the mark', array( WPCPM_Institution_Copy::retry(), $GLOBALS['sent'][0][2][0]['fields'], WPCPM_Institution_Copy::pending() ), array( 1, array( 'Next Step' => 'Sign the agreement' ), array() ) );

echo "\n-- the Institutions screen's notice -------------------------------------------\n";

reset_world();

ck( 'nothing pending: no notice at all', notice(), '' );

$GLOBALS['answers'] = array( $refused, $timeout );
WPCPM_Institution_Copy::send( SECOND, array( 'city' ) );
WPCPM_Institution_Copy::send( SCHOOL, array( 'stage' ) );

// Two moments apart, the second school's the later, though it failed first here.
$stored                     = get_option( WPCPM_Institution_Copy::OPT_PENDING );
$stored[ SCHOOL ]['since']  = 1795000000;
$stored[ SECOND ]['since']  = 1795007200;
update_option( WPCPM_Institution_Copy::OPT_PENDING, $stored, false );

$html = notice();

ck( 'a WordPress warning notice, in wp-admin\'s markup', 0 === strpos( $html, '<div class="notice notice-warning' ), true );
ck( 'its sentence', false !== strpos( $html, 'These institutions&#039; changes are not in Airtable yet. The site tries again by itself: after an hour or two at first, then less often, and at least once a day.' ), true );
ck(
	'each school by name, since when, and the error, oldest first',
	array(
		false !== strpos( $html, 'Example University of the North, waiting since ' . WPCPM_Dates::moment( 1795000000 ) . ': cURL error 28' ),
		false !== strpos( $html, 'Second Example School, waiting since ' . WPCPM_Dates::moment( 1795007200 ) . ': Airtable request failed (HTTP 422)' ),
		strpos( $html, 'Example University of the North' ) < strpos( $html, 'Second Example School' ),
	),
	array( true, true, true )
);
ck( 'a name is printed escaped', ( function () {
	WPCPM_Institutions_Index::patch( SCHOOL, array( 'name' => 'A <b>bold</b> School' ) );
	$html = notice();
	return array( false !== strpos( $html, 'A &lt;b&gt;bold&lt;/b&gt; School' ), false === strpos( $html, '<b>' ) );
} )(), array( true, true ) );

$GLOBALS['sent'] = array();
age( 3 * HOUR_IN_SECONDS );
WPCPM_Institution_Copy::retry();

ck( 'once every write has gone the list is empty and so is the notice', array( WPCPM_Institution_Copy::pending(), notice() ), array( array(), '' ) );

reset_world();
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'stage' ) );
$GLOBALS['settings']['institutions_on_site'] = false;

ck( 'outside site mode the notice is not drawn', notice(), '' );

echo "\n-- I1: a request that cached the list as absent loses no other school's mark ---\n";

reset_world();

// Request A reads the list while there is none: its cache holds the option as absent.
$GLOBALS['req'] = 'A';
WPCPM_Institution_Copy::pending();

// Request B, meanwhile, fails a copy of the second school and adds the list.
$GLOBALS['req']     = 'B';
$GLOBALS['answers'] = array( $refused );
WPCPM_Institution_Copy::send( SECOND, array( 'city' ) );

// Back in A, its own copy of the first school fails too.
$GLOBALS['req']     = 'A';
$GLOBALS['answers'] = array( $timeout );
WPCPM_Institution_Copy::send( SCHOOL, array( 'stage' ) );

ck( 'stale_notoptions_keeps_both: both schools stand in the table, each with its own keys', array( array_keys( $GLOBALS['opts'][ WPCPM_Institution_Copy::OPT_PENDING ] ?? array() ), $GLOBALS['opts'][ WPCPM_Institution_Copy::OPT_PENDING ][ SECOND ]['keys'] ?? null ), array( array( SECOND, SCHOOL ), array( 'city' ) ) );

echo "\n-- I2: marked before the PATCH, never dropped silently, not announced in flight --\n";

reset_world();
$GLOBALS['during_patch'] = function () {
	throw new RuntimeException( 'the request died with its PATCH out' );
};

try {
	WPCPM_Institution_Copy::send( SCHOOL, array( 'stage', 'city' ) );
} catch ( RuntimeException $e ) {
	unset( $e );
}

$dead = pending_of( SCHOOL );

ck( 'write_ahead_survives_death: a request that dies between its mark and the PATCH\'s answer leaves the keys pending, with no try and no error', array( $dead['keys'], $dead['tries'], $dead['error'], $dead['tried'] ), array( array( 'stage', 'city' ), 0, '', 0 ) );
ck( 'notice_skips_in_flight: a mark younger than five minutes with no try is a copy in flight, not announced', notice(), '' );

age( 6 * 60 );

ck( 'notice_counts_old_mark: older than five minutes it is announced', false !== strpos( notice(), 'Example University of the North, waiting since' ), true );

$GLOBALS['sent'] = array();

ck( 'and the next run sends it: never tried, it is first', array( WPCPM_Institution_Copy::retry(), $GLOBALS['sent'][0][2][0]['fields'] ?? null, WPCPM_Institution_Copy::pending() ), array( 1, array( 'Current Stage' => 'Call Scheduled', 'City' => 'Example City' ), array() ) );

reset_world();
$GLOBALS['answers'] = array( $refused );
WPCPM_Institution_Copy::send( SCHOOL, array( 'stage' ) );

ck( 'notice_counts_a_try: a mark with a failed try is announced at once', false !== strpos( notice(), 'Example University of the North, waiting since' ), true );

// Another request holds the list's lock past the wait: the mark cannot be written, and that is said.
reset_world();
$log_file = tempnam( sys_get_temp_dir(), 'wpcpm-copy-log-' );
$log_was  = ini_set( 'error_log', $log_file );

$GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK ] = (string) ( time() + 60 );

// The mark is private: read through reflection, and a class without one fails here rather than stopping the suite.
$owed = 'no mark method';

if ( method_exists( 'WPCPM_Institution_Copy', 'owe' ) ) {
	$owe = new ReflectionMethod( 'WPCPM_Institution_Copy', 'owe' );
	if ( PHP_VERSION_ID < 80100 ) {
		$owe->setAccessible( true );
	}
	$owed = $owe->invoke( null, SCHOOL, array( 'city' ) );
}

ck( 'mark_lock_refused_false: a mark the lock keeps from being written answers false', $owed, false );

$said = WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) );
$log  = (string) file_get_contents( $log_file );

ck( 'mark_lock_refused_logged: the send still makes its PATCH and says true, and the log names the school', array( $said, count( patches() ), false !== strpos( $log, 'the Airtable copy of ' . SCHOOL . ' could not be marked before its copy was sent' ), false === strpos( $log, 'Example City' ) ), array( true, 1, true, true ) );

unset( $GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK ] );
ini_set( 'error_log', false === $log_was ? '' : $log_was );
unlink( $log_file );

echo "\n-- I3: backoff, and the least recently tried first --------------------------\n";

reset_world();

$rows  = array();
$marks = array();

for ( $i = 1; $i <= 51; $i++ ) {
	$id          = sprintf( 'recEXA%011d', 100 + $i );
	$rows[ $id ] = array( 'record_id' => $id, 'name' => 'Refused School ' . $i, 'city' => 'Town ' . $i );
	// Refused for days, the oldest marks of all, each due again.
	$marks[ $id ] = array( 'since' => time() - 10 * DAY + $i, 'tries' => 1, 'tried' => time() - 3 * HOUR_IN_SECONDS + $i );
}

$rows[ SCHOOL ]  = WPCPM_Institutions_Index::row( SCHOOL );
$marks[ SCHOOL ] = array( 'since' => time() - 600, 'tries' => 0, 'tried' => 0, 'error' => '' );

WPCPM_Institutions_Index::write( $rows, 1794000000 );
store_marks( $marks );

$GLOBALS['answers'] = array_fill( 0, 60, $refused );
WPCPM_Institution_Copy::retry();

$asked = array_map( function ( $p ) { return $p[2][0]['id']; }, patches() );

ck( 'retry_new_mark_not_starved: with 51 schools refused for days and one new mark, the new one is sent first, and the batch stays 50', array( $asked[0] ?? null, count( $asked ) ), array( SCHOOL, 50 ) );

reset_world();
store_marks( array( SCHOOL => array( 'tries' => 3, 'tried' => time() - 8 * HOUR_IN_SECONDS + 120 ) ) );

ck( 'retry_backoff_eight_hours: a school refused three times is not tried again within eight hours', array( WPCPM_Institution_Copy::retry(), $GLOBALS['sent'] ), array( 0, array() ) );
ck( 'retry_backoff_notice_lists: and the notice still names it', false !== strpos( notice(), 'Example University of the North, waiting since' ), true );

store_marks( array( SCHOOL => array( 'tries' => 3, 'tried' => time() - 8 * HOUR_IN_SECONDS - 60 ) ) );

ck( 'after eight hours it is tried again', array( WPCPM_Institution_Copy::retry(), count( patches() ) ), array( 1, 1 ) );

store_marks( array( SCHOOL => array( 'tries' => 9, 'tried' => time() - 25 * HOUR_IN_SECONDS ) ) );
$GLOBALS['sent'] = array();

ck( 'the wait is never longer than a day', array( WPCPM_Institution_Copy::retry(), count( patches() ) ), array( 1, 1 ) );

echo "\n-- M1: a held school lock is not a failed write --------------------------------\n";

reset_world();
store_marks( array( SCHOOL => array( 'keys' => array( 'stage' ), 'since' => 1795000000, 'tries' => 2, 'error' => 'refused before', 'tried' => 1795001000 ) ) );
$GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK . '_' . SCHOOL ] = (string) ( time() + 60 );

ck( 'busy_lock_no_try: the keys are marked, the tries, the error, the time and since untouched, nothing sent', array( WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) ), pending_of( SCHOOL ), $GLOBALS['sent'] ), array( false, array( 'keys' => array( 'stage', 'city' ), 'since' => 1795000000, 'error' => 'refused before', 'tries' => 2, 'tried' => 1795001000, 'missing' => 0 ), array() ) );

reset_world();
$GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK . '_' . SCHOOL ] = (string) ( time() + 60 );
WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) );

ck( 'and with no mark before, one with no try and no error', array( pending_of( SCHOOL )['tries'], pending_of( SCHOOL )['error'], pending_of( SCHOOL )['tried'] ), array( 0, '', 0 ) );

echo "\n-- the fix wave: missing, settle with no mark made, retry's places ------------------\n";

// C1: a try that finds the row again starts the count of missing tries over.
reset_world();
store_marks( array( SCHOOL => array( 'missing' => 5, 'tries' => 5, 'tried' => time() - 2 * DAY ) ) );
$GLOBALS['answers'] = array( $refused );
WPCPM_Institution_Copy::retry();

ck( 'missing_reset_when_row_back: a refused try that found the row resets missing to 0, and counts the try', array( pending_of( SCHOOL )['missing'], pending_of( SCHOOL )['tries'] ), array( 0, 6 ) );

store_marks( array( SCHOOL => array( 'missing' => 5, 'tries' => 5, 'tried' => time() - 2 * DAY, 'keys' => array( 'city', 'next_step' ) ) ) );
$GLOBALS['during_patch'] = function () {
	site_sets( SCHOOL, array( 'next_step' => 'Changed while out' ) );
};
WPCPM_Institution_Copy::retry();

ck( 'and a taken try whose key changed meanwhile keeps that key with missing 0', array( pending_of( SCHOOL )['keys'], pending_of( SCHOOL )['missing'] ), array( array( 'next_step' ), 0 ) );

// C2: the list's lock is held when the send would mark its keys; the PATCH goes with the keys asked for,
// and the lock is let go while it is out. The older mark's key was not sent, so its tries stand.
reset_world();
store_marks( array( SCHOOL => array( 'keys' => array( 'stage' ), 'since' => 1795000000, 'tries' => 2, 'error' => 'refused before', 'tried' => 1795001000 ) ) );
$GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK ] = (string) ( time() + 60 );
$GLOBALS['during_patch'] = function () {
	unset( $GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK ] );
};
$log_file = tempnam( sys_get_temp_dir(), 'wpcpm-copy-log-' );
$log_was  = ini_set( 'error_log', $log_file );
$said     = WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) );
ini_set( 'error_log', false === $log_was ? '' : $log_was );
unlink( $log_file );

ck( 'settle_unmarked_keeps_tries: a key left that was not sent keeps the mark\'s since, tries, tried and error', array( $said, patches()[0][2][0]['fields'], pending_of( SCHOOL ) ), array( true, array( 'City' => 'Example City' ), array( 'keys' => array( 'stage' ), 'since' => 1795000000, 'error' => 'refused before', 'tries' => 2, 'tried' => 1795001000, 'missing' => 0 ) ) );

reset_world();
store_marks( array( SCHOOL => array( 'keys' => array( 'city' ), 'since' => 1795000000, 'tries' => 2, 'error' => 'refused before', 'tried' => 1795001000 ) ) );
$GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK ] = (string) ( time() + 60 );
$GLOBALS['during_patch'] = function () {
	unset( $GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK ] );
	WPCPM_Institutions_Index::patch( SCHOOL, array( 'city' => 'Changed Town' ) );
};
$log_file = tempnam( sys_get_temp_dir(), 'wpcpm-copy-log-' );
$log_was  = ini_set( 'error_log', $log_file );
WPCPM_Institution_Copy::send( SCHOOL, array( 'city' ) );
ini_set( 'error_log', false === $log_was ? '' : $log_was );
unlink( $log_file );
$restarted = pending_of( SCHOOL );

ck( 'settle_unmarked_sent_restarts: every key left was among those sent, so the mark starts again with no try', array( $restarted['keys'], $restarted['tries'], $restarted['tried'], $restarted['error'], $restarted['since'] >= time() - 60 ), array( array( 'city' ), 0, 0, '', true ) );

// C3: the retry reads the list past this request's cache.
reset_world();
$GLOBALS['req'] = 'A';
WPCPM_Institution_Copy::pending();
$GLOBALS['req'] = 'B';
store_marks( array( SCHOOL => array( 'tries' => 0, 'tried' => 0, 'error' => '' ) ) );
$GLOBALS['req'] = 'A';

ck( 'retry_reads_fresh: a mark another request wrote after this one cached the list as absent is sent', array( WPCPM_Institution_Copy::retry(), count( patches() ) ), array( 1, 1 ) );

// C3: a school whose lock is held takes none of the 50 places.
reset_world();

$rows  = array();
$marks = array();

for ( $i = 1; $i <= 51; $i++ ) {
	$id           = sprintf( 'recEXA%011d', 300 + $i );
	$rows[ $id ]  = array( 'record_id' => $id, 'name' => 'Waiting School ' . $i, 'city' => 'Town ' . $i );
	$marks[ $id ] = array( 'since' => time() - DAY + $i, 'tries' => 0, 'tried' => 0, 'error' => '' );
}

$held = sprintf( 'recEXA%011d', 301 );
WPCPM_Institutions_Index::write( $rows, 1794000000 );
store_marks( $marks );
$GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK . '_' . $held ] = (string) ( time() + 60 );

$written = WPCPM_Institution_Copy::retry();
$asked   = array_map( function ( $p ) { return $p[2][0]['id']; }, patches() );

ck( 'retry_held_lock_no_place: with 51 due and the first school\'s lock held, 50 others are written and the held one is not asked', array( $written, count( $asked ), in_array( $held, $asked, true ), array_keys( WPCPM_Institution_Copy::pending() ) ), array( 50, 50, false, array( $held ) ) );

unset( $GLOBALS['opts'][ WPCPM_Institution_Copy::LOCK . '_' . $held ] );

echo "\n-- M3: an empty Name is never sent ----------------------------------------------\n";

reset_world();
WPCPM_Institutions_Index::patch( SCHOOL, array( 'name' => '  ' ) );

ck( 'empty_name_left_out: the cells leave an empty name out', WPCPM_Institution_Copy::cells_for( SCHOOL, array( 'name', 'city' ) ), array( 'City' => 'Example City' ) );

WPCPM_Institution_Copy::send( SCHOOL, array( 'name', 'city' ) );

ck( 'and the PATCH holds no Name, and nothing is left owed for it', array( patches()[0][2][0]['fields'], WPCPM_Institution_Copy::pending() ), array( array( 'City' => 'Example City' ), array() ) );

echo "\n-- M6: the notice names ten schools, then counts the rest -----------------------\n";

reset_world();

$rows  = array();
$marks = array();

for ( $i = 1; $i <= 12; $i++ ) {
	$id           = sprintf( 'recEXA%011d', 200 + $i );
	$rows[ $id ]  = array( 'record_id' => $id, 'name' => sprintf( 'Listed School %02d', $i ) );
	$marks[ $id ] = array( 'since' => time() - DAY + $i );
}

WPCPM_Institutions_Index::write( $rows, 1794000000 );
store_marks( $marks );

$html = notice();

ck( 'notice_ten_then_count: the ten oldest by name, then "and 2 more"', array( substr_count( $html, '<li>' ), false !== strpos( $html, 'Listed School 10' ), false === strpos( $html, 'Listed School 11' ), false !== strpos( $html, '<li>and 2 more</li>' ), strpos( $html, 'Listed School 01' ) < strpos( $html, 'Listed School 02' ) ), array( 11, true, true, true, true ) );
ck( 'no link in it while the site has no published guide', false === strpos( $html, '<a ' ), true );

// G5 (1.123.104): with a published guide, the notice ends its sentence with a plain link, How to read this, to the job
// Read the notice about Airtable's copy, for an Administrator; the list under it is as it was.
$GLOBALS['wpcpm_guide_page'] = new WP_Post();
$GLOBALS['wpcpm_guide_page']->post_status = 'publish';
$GLOBALS['can_manage']       = true;
WPCPM_Guide_Jobs::forget();
$linked = notice();
ck( 'g5_notice_link: the sentence ends with How to read this, to Read the notice about Airtable\'s copy, and the list is as it was',
	array(
		false !== strpos( $linked, 'and at least once a day. <a class="wpcpm-job-link" href="https://example.test/administrator-guide/#read-the-notice-about-airtable-s-copy">How to read this</a></p><ul class="ul-disc">' ),
		substr_count( $linked, '<a ' ),
		str_replace( ' <a class="wpcpm-job-link" href="https://example.test/administrator-guide/#read-the-notice-about-airtable-s-copy">How to read this</a>', '', $linked ) === $html,
	),
	array( true, 1, true ) );
$GLOBALS['can_manage'] = false;
ck( 'g5_notice_no_manager: a reader who cannot manage the program gets the notice without it', notice() === $html, true );
$GLOBALS['settings']['institutions_on_site'] = false;
$GLOBALS['can_manage']                       = true;
ck( 'g5_notice_off: outside site mode there is no notice, so no link', notice(), '' );
$GLOBALS['settings']['institutions_on_site'] = true;
$GLOBALS['can_manage']                       = false;
unset( $GLOBALS['wpcpm_guide_page'] );
WPCPM_Guide_Jobs::forget();

echo "\n-- create(): a promoted lead's record ------------------------------------------\n";

reset_world();

$lead = array(
	'name'           => 'Example Lead College',
	'stage'          => 'Call Scheduled',
	'country'        => COUNTRY,
	'city'           => 'Lead Town',
	'website'        => 'https://lead.example.test',
	'contact_person' => 'Lead Person',
	'contact_email'  => 'lead@example.test',
);

ck( 'create() answers the new record ID', WPCPM_Institution_Copy::create( $lead ), 'recNEW00000000001' );
ck(
	'one POST of Name, Current Stage, Country, City, Website, Contact Person and Contact Email',
	$GLOBALS['sent'],
	array(
		array(
			'POST',
			TABLE,
			array(
				array(
					'fields' => array(
						'Name'           => 'Example Lead College',
						'Current Stage'  => 'Call Scheduled',
						'Country'        => array( COUNTRY ),
						'City'           => 'Lead Town',
						'Website'        => 'https://lead.example.test',
						'Contact Person' => 'Lead Person',
						'Contact Email'  => 'lead@example.test',
					),
				),
			),
		),
	)
);

reset_world();
$GLOBALS['created'] = $refused;

ck( 'a refusal comes back as the error', WPCPM_Institution_Copy::create( $lead ), $refused );

$GLOBALS['created'] = array();
$empty_answer       = WPCPM_Institution_Copy::create( $lead );

ck( 'an answer naming no record is an error of the plugin\'s own', is_wp_error( $empty_answer ) ? $empty_answer->get_error_code() : $empty_answer, 'wpcpm_copy_create' );

reset_world();

foreach (
	array(
		'no name'                => array( 'name' => '  ' ),
		'the Lead stage'         => array( 'stage' => 'Lead' ),
		'a country that is no record ID' => array( 'country' => 'Exampleland' ),
	) as $why => $change
) {
	$result = WPCPM_Institution_Copy::create( array_merge( $lead, $change ) );

	ck( sprintf( 'a row with %s is refused before anything is sent', $why ), array( is_wp_error( $result ), $GLOBALS['sent'] ), array( true, array() ) );
}

$GLOBALS['created'] = true;
WPCPM_Institution_Copy::create( array( 'name' => 'Only A Name', 'stage' => 'Lead Contacted' ) );

ck( 'empty columns are left out of the POST', $GLOBALS['sent'][0][2][0]['fields'], array( 'Name' => 'Only A Name', 'Current Stage' => 'Lead Contacted' ) );

echo "\n-- on the clock -------------------------------------------------------------\n";

$GLOBALS['cron']    = array();
$GLOBALS['actions'] = array();
WPCPM_Institution_Copy::init();

ck( 'init() hangs the retry on its hook and the schedule on init', array( $GLOBALS['actions'][0][0], $GLOBALS['actions'][0][1], $GLOBALS['actions'][1][0], $GLOBALS['actions'][1][1] ), array( 'wpcpm_institution_copy_hourly', array( 'WPCPM_Institution_Copy', 'run_hourly' ), 'init', array( 'WPCPM_Institution_Copy', 'schedule' ) ) );

// The whole-branch wave, W3: on the clock in site mode only, as the Monday email is.
$GLOBALS['settings']['institutions_on_site'] = false;
WPCPM_Institution_Copy::schedule();
$copy_off_none = $GLOBALS['cron'];
$GLOBALS['cron'] = array( array( 'hook' => 'wpcpm_institution_copy_hourly', 'at' => time() + 60, 'recurrence' => 'hourly' ), array( 'hook' => 'wpcpm_other_event', 'at' => time() + 60, 'recurrence' => 'daily' ) );
WPCPM_Institution_Copy::schedule();

ck( 'copy_hourly_off: with the setting off, schedule() puts no event on the clock after init and takes one already there off, leaving others',
	array( $copy_off_none, array_column( $GLOBALS['cron'], 'hook' ) ),
	array( array(), array( 'wpcpm_other_event' ) ) );

$GLOBALS['cron']                             = array();
$GLOBALS['settings']['institutions_on_site'] = true;
WPCPM_Institution_Copy::schedule();
WPCPM_Institution_Copy::schedule();

ck( 'schedule() puts one hourly event on the clock in site mode, however often it runs', array_map( function ( $e ) { return array( $e['hook'], $e['recurrence'] ); }, $GLOBALS['cron'] ), array( array( 'wpcpm_institution_copy_hourly', 'hourly' ) ) );

WPCPM_Institution_Copy::unschedule();

ck( 'unschedule() takes it off', $GLOBALS['cron'], array() );

$GLOBALS['settings']['institutions_on_site'] = false;

ck( 'copy_hourly_uninstall: uninstall.php still clears the hourly hook', false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'uninstall.php' ), 'wp_clear_scheduled_hook( WPCPM_Institution_Copy::CRON_HOURLY );' ), true );

// The whole-branch wave, W4: the names the settings screen's warning gives, oldest mark first, a school the index
// does not name by its record ID.
reset_world();
$GLOBALS['opts'][ WPCPM_Institution_Copy::OPT_PENDING ] = array(
	'recEXA00000000009' => array( 'keys' => array( 'stage' ), 'since' => 200, 'error' => '', 'tries' => 0, 'tried' => 0, 'missing' => 0 ),
	SCHOOL              => array( 'keys' => array( 'name' ), 'since' => 100, 'error' => '', 'tries' => 1, 'tried' => 150, 'missing' => 0 ),
	'recEXA00000000008' => array( 'keys' => array(), 'since' => 50, 'error' => '', 'tries' => 0, 'tried' => 0, 'missing' => 0 ),
);

ck( 'copy_pending_names: the schools with a copy pending, oldest first, by name or else record ID; a mark with no keys is none',
	WPCPM_Institution_Copy::pending_names(),
	array( 'Example University of the North', 'recEXA00000000009' ) );

echo "\n-- the source ------------------------------------------------------------------\n";

$src  = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-copy.php' );
$mine = file_get_contents( __FILE__ );

echo "\n-- T1: going back leaves no copy to be sent later ----------------------------\n";

/** The switch's store, as the screen's save makes it: the value stored as asked when allowed. */
function switch_to( $to, $dry = false ) {
	return WPCPM_Institutions_Switch::change(
		$to,
		static function ( $allowed ) use ( $to ) {
			if ( $allowed ) {
				$GLOBALS['settings']['institutions_on_site'] = (bool) $to;
			}

			return ! empty( $GLOBALS['settings']['institutions_on_site'] );
		},
		$dry
	);
}

reset_world();
$GLOBALS['answers'] = array( $refused );
site_sets( SCHOOL, array( 'next_step' => 'The site\'s old step' ) );
WPCPM_Institution_Copy::send( SCHOOL, array( 'next_step' ) );
$t1_owed = null !== pending_of( SCHOOL );
$t1_off  = switch_to( false );
$t1_left = WPCPM_Institution_Copy::pending();
// Airtable's Next step changes meanwhile, and the sync reads the list again: a fresh read, so the guard lets it on.
WPCPM_Institutions_Index::write( WPCPM_Institutions_Index::rows(), time() );
$t1_on    = switch_to( true );
// The hours to the next run pass, so a mark still there would be due.
age( 25 * HOUR_IN_SECONDS );
$t1_sent  = count( $GLOBALS['sent'] );
$t1_retry = WPCPM_Institution_Copy::retry();
ck( 'T1: the trial\'s sequence: a copy owed, the record given back (the warning names the school, the marks emptied), turned on again through the guard, and the hourly retry sends nothing', array(
	$t1_owed,
	$t1_off['warning'],
	$t1_left,
	$t1_on['refusal'],
	WPCPM_Institution_Copy::pending(),
	$t1_retry,
	count( $GLOBALS['sent'] ) - $t1_sent,
), array(
	true,
	'The site reads Airtable again. Airtable never received the last changes of Example University of the North, and the site will not send them later: write them in Airtable by hand if they matter.',
	array(),
	null,
	array(),
	0,
	0,
) );

reset_world();
$GLOBALS['settings']['institutions_on_site'] = false;
store_marks( array( SECOND => array() ) );
WPCPM_Institutions_Index::write( WPCPM_Institutions_Index::rows(), time() );
$t1_stray = switch_to( true );
ck( 'T1: a mark left behind by another path is emptied when the setting is turned on', array( $t1_stray['refusal'], $GLOBALS['settings']['institutions_on_site'], WPCPM_Institution_Copy::pending() ), array( null, true, array() ) );

reset_world();
store_marks( array( SCHOOL => array(), SECOND => array() ) );
$t1_dry = switch_to( false, true );
ck( 'T1: a dry run empties nothing and stores nothing', array( $t1_dry['stored'], $GLOBALS['settings']['institutions_on_site'], array_keys( WPCPM_Institution_Copy::pending() ) ), array( null, true, array( SCHOOL, SECOND ) ) );
ck( 'T1: going back with two schools owed says the count and the names', switch_to( false )['warning'], 'The site reads Airtable again. Airtable never received the last changes of 2 institutions (Example University of the North and Second Example School), and the site will not send them later: write them in Airtable by hand if they matter.' );
ck( 'T1: forget_pending_marks() empties the list under its lock and says it took it', array( store_marks( array( SCHOOL => array() ) ), WPCPM_Institution_Copy::forget_pending_marks(), WPCPM_Institution_Copy::pending() ), array( null, true, array() ) );

echo "\n=== O6: a mark that was not stored is not reported as stored ===\n";

reset_world();
$GLOBALS['settings']['institutions_on_site'] = true;
$GLOBALS['write_fails'] = array( WPCPM_Institution_Copy::OPT_PENDING );
$o6_marked              = WPCPM_Institution_Copy::mark( SCHOOL, array( 'city' ) );
$GLOBALS['write_fails'] = array();
ck( 'o6_mark_not_stored: with the pending list\'s write refused, mark() answers false and nothing is pending', array( $o6_marked, WPCPM_Institution_Copy::pending() ), array( false, array() ) );
ck( 'o6_mark_stored: with the write let through, mark() answers true and the key is pending', array( WPCPM_Institution_Copy::mark( SCHOOL, array( 'city' ) ), pending_of( SCHOOL )['keys'] ?? null ), array( true, array( 'city' ) ) );
ck( 'o6_unchanged_mark: a mark of a key already owed changes nothing, which update_option() answers false for, and is still stored', WPCPM_Institution_Copy::mark( SCHOOL, array( 'city' ) ), true );
$GLOBALS['write_fails'] = array( WPCPM_Institution_Copy::OPT_PENDING );
ck( 'o6_unchanged_under_refusal: a mark already stored needs no write, so a refused write does not make it false', WPCPM_Institution_Copy::mark( SCHOOL, array( 'city' ) ), true );
$GLOBALS['write_fails'] = array();

ck( 'no em dash or en dash in the class or this suite', array( preg_match( '/[\x{2013}\x{2014}]/u', $src ), preg_match( '/[\x{2013}\x{2014}]/u', $mine ) ), array( 0, 0 ) );
ck( 'the pending option is written with autoload off', false !== strpos( $src, 'update_option( self::OPT_PENDING, $pending, false )' ), true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
