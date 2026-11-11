<?php
/**
 * The tracks an institution offers (registration track question, task 1): the list kept on the
 * institution's record, what of it the site runs, whether the form asks, the text written for an
 * answer, and the one audited way to change the list.
 *
 * What is pinned here and why:
 *
 * - **Kept by status, shown by name, in the Track Builder's order.** `runnable()` is the stored
 *   statuses the site runs today, in `WPCPM_Program::labels()` order, not the order they were saved in.
 * - **A track the site stopped running is kept.** `runnable()` leaves it out; `save()` keeps it in the
 *   stored list so it comes back when the track is published again, and drops only what the site never
 *   ran.
 * - **Asked only where it matters.** `asks()` is true for two or more runnable tracks.
 * - **An answer is one of the institution's runnable tracks, or "Not sure yet".** Anything else is null.
 * - **A change is audited once, and an unchanged save writes nothing.** A record the site cannot keep
 *   is an error and no row of history.
 *
 * Run from the plugin root:  php bin/test-institution-tracks.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['posts']        = array();
$GLOBALS['pmeta']        = array();
$GLOBALS['next_id']      = 100;
$GLOBALS['record_fails'] = '';
$GLOBALS['labels']       = array();

class WP_Error {
	public $code = '';
	public $message = '';
	public $data = null;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_name = '', $post_author = 0, $post_content = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function register_post_type( $t, $a = array() ) {}
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? 1788322400 : (int) $t ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function apply_filters( $hook, $value ) { return 'wpcpm_program_labels' === $hook ? $GLOBALS['labels'] : $value; }

require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/specialchars.php';

function wp_cache_delete( $key, $group = '' ) { return true; }

function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );

	if ( 'post' === $GLOBALS['record_fails'] && 'wpcpm_inst_record' === ( isset( $postarr['post_type'] ) ? $postarr['post_type'] : '' ) ) {
		return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' );
	}

	$id   = $GLOBALS['next_id']++;
	$post = new WP_Post();

	$post->ID           = $id;
	$post->post_type    = isset( $postarr['post_type'] ) ? $postarr['post_type'] : 'post';
	$post->post_status  = isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'draft';
	$post->post_title   = isset( $postarr['post_title'] ) ? $postarr['post_title'] : '';
	$post->post_content = isset( $postarr['post_content'] ) ? $postarr['post_content'] : '';
	$post->post_author  = isset( $postarr['post_author'] ) ? (int) $postarr['post_author'] : 0;

	$GLOBALS['posts'][ $id ] = $post;

	return $id;
}
function wp_update_post( $postarr, $wp_error = false ) { return (int) $postarr['ID']; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function get_post_meta( $id, $key = '', $single = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) {
	if ( '_wpcpm_inst_answers' === $key && 'meta' === $GLOBALS['record_fails'] ) {
		return false;
	}

	$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );

	return true;
}
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

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}

class WPCPM_Airtable {
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-history.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tracks.php';

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

const REC   = 'recINSTITUTION001';
const OTHER = 'recINSTITUTION002';

const FOUR = array(
	'In Sensei'       => 'WordPress Credits Program 150h',
	'In Sensei 50h'   => 'WordPress Credits Program 50h',
	'Designer Track'  => 'Designer Track',
	'Developer Track' => 'Developer Track',
);

function reset_world() {
	$GLOBALS['posts']        = array();
	$GLOBALS['pmeta']        = array();
	$GLOBALS['next_id']      = 100;
	$GLOBALS['record_fails'] = '';
	$GLOBALS['labels']       = FOUR;
}

function audit_rows( $record = REC ) {
	$rows = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'wpcpm_audit_entry' === $post->post_type && $record === get_post_meta( $post->ID, '_wpcpm_log_institution', true ) ) {
			$rows[] = $post;
		}
	}

	return $rows;
}

echo "\n-- the constants -------------------------------------------------------\n";

ck( 'the key in the institution record', WPCPM_Institution_Tracks::RECORD_KEY, 'tracks_offered' );
ck( 'the audit kind', WPCPM_Institution_Tracks::LOG_SAVED, 'tracks_saved' );
ck( 'the posted value of Not sure yet', WPCPM_Institution_Tracks::NOT_SURE, 'not-sure' );
ck( 'the text written for it', WPCPM_Institution_Tracks::NOT_SURE_TEXT, 'Not sure yet' );

echo "\n-- reading --------------------------------------------------------------\n";

reset_world();

ck( 'no record: nothing stored', WPCPM_Institution_Tracks::stored( REC ), array() );
ck( 'a bad ID: nothing stored', WPCPM_Institution_Tracks::stored( 'nope' ), array() );
ck( 'a bad ID: nothing runnable', WPCPM_Institution_Tracks::runnable( 'nope' ), array() );
ck( 'a bad ID: no question', WPCPM_Institution_Tracks::asks( 'nope' ), false );
ck( 'a bad ID cannot be saved', WPCPM_Institution_Tracks::save( 'nope', array( 'In Sensei' ), 7 ), false );

echo "\n-- saving ---------------------------------------------------------------\n";

ck( 'a changed list is saved', WPCPM_Institution_Tracks::save( REC, array( 'Developer Track', 'In Sensei 50h' ), 7 ), true );
ck( 'the list is kept, in labels order', WPCPM_Institution_Tracks::stored( REC ), array( 'In Sensei 50h', 'Developer Track' ) );
ck(
	'runnable is the stored statuses by name, in labels order',
	WPCPM_Institution_Tracks::runnable( REC ),
	array( 'In Sensei 50h' => 'WordPress Credits Program 50h', 'Developer Track' => 'Developer Track' )
);
ck( 'two runnable tracks: the form asks', WPCPM_Institution_Tracks::asks( REC ), true );
ck( 'one audit row', count( audit_rows() ), 1 );

$row = audit_rows()[0];
ck(
	'the row: kind, institution, subject, actor, ground, evidence',
	array(
		get_post_meta( $row->ID, '_wpcpm_log_kind', true ),
		get_post_meta( $row->ID, '_wpcpm_log_institution', true ),
		get_post_meta( $row->ID, '_wpcpm_log_subject', true ),
		(int) get_post_meta( $row->ID, '_wpcpm_log_actor', true ),
		get_post_meta( $row->ID, '_wpcpm_log_ground', true ),
		get_post_meta( $row->ID, '_wpcpm_log_evidence', true ),
	),
	array( 'tracks_saved', REC, REC, 7, 'manager', 'index' )
);
ck( 'the row holds the new list', get_post_meta( $row->ID, '_wpcpm_log_data', true ), array( 'statuses' => array( 'In Sensei 50h', 'Developer Track' ) ) );
ck( 'the message names the tracks by name', $row->post_content, 'Tracks offered were set: WordPress Credits Program 50h, Developer Track.' );

ck( 'the same list again changes nothing', WPCPM_Institution_Tracks::save( REC, array( 'Developer Track', 'In Sensei 50h' ), 7 ), false );
ck( 'and writes no second row', count( audit_rows() ), 1 );

reset_world();
ck( 'a status the site does not run is dropped', WPCPM_Institution_Tracks::save( REC, array( 'In Sensei', 'Made Up' ), 7 ), true );
ck( 'only the run track is stored', WPCPM_Institution_Tracks::stored( REC ), array( 'In Sensei' ) );

reset_world();
WPCPM_Institution_Tracks::save( REC, array( 'Designer Track', 'Developer Track' ), 7 );
ck( 'the message in labels order', audit_rows()[0]->post_content, 'Tracks offered were set: Designer Track, Developer Track.' );
ck( 'an empty list clears the list', WPCPM_Institution_Tracks::save( REC, array(), 7 ), true );
ck( 'nothing stored after it', WPCPM_Institution_Tracks::stored( REC ), array() );
ck( 'the clear is audited in words', audit_rows()[1]->post_content, 'Tracks offered were cleared.' );
ck( 'an empty list again changes nothing', WPCPM_Institution_Tracks::save( REC, array(), 7 ), false );
ck( 'and writes no row', count( audit_rows() ), 2 );
ck( 'an institution with no record saving nothing makes no record post', count( array_filter( $GLOBALS['posts'], function ( $p ) { return 'wpcpm_inst_record' === $p->post_type; } ) ), 1 );

echo "\n-- a track the site stops running --------------------------------------\n";

reset_world();
WPCPM_Institution_Tracks::save( REC, array( 'Designer Track', 'Developer Track' ), 7 );
unset( $GLOBALS['labels']['Designer Track'] );

ck( 'unpublished: left out of runnable', WPCPM_Institution_Tracks::runnable( REC ), array( 'Developer Track' => 'Developer Track' ) );
ck( 'unpublished: still stored', WPCPM_Institution_Tracks::stored( REC ), array( 'Designer Track', 'Developer Track' ) );
ck( 'unpublished: fewer than two, no question', WPCPM_Institution_Tracks::asks( REC ), false );

reset_world();
WPCPM_Institution_Tracks::save( REC, array( 'Designer Track' ), 7 );
unset( $GLOBALS['labels']['Designer Track'] );
ck( 'a save keeps the unpublished track', WPCPM_Institution_Tracks::save( REC, array( 'In Sensei' ), 7 ), true );
ck( 'both are stored, the run one first', WPCPM_Institution_Tracks::stored( REC ), array( 'In Sensei', 'Designer Track' ) );
$GLOBALS['labels'] = FOUR;
ck(
	'published again, it is runnable again',
	WPCPM_Institution_Tracks::runnable( REC ),
	array( 'In Sensei' => 'WordPress Credits Program 150h', 'Designer Track' => 'Designer Track' )
);

echo "\n-- asks -----------------------------------------------------------------\n";

reset_world();
ck( 'no track: no question', WPCPM_Institution_Tracks::asks( REC ), false );
WPCPM_Institution_Tracks::save( REC, array( 'In Sensei' ), 7 );
ck( 'one track: no question', WPCPM_Institution_Tracks::asks( REC ), false );

echo "\n-- the answer's text ---------------------------------------------------\n";

reset_world();
WPCPM_Institution_Tracks::save( REC, array( 'Developer Track', 'In Sensei 50h' ), 7 );
WPCPM_Institution_Tracks::save( OTHER, array( 'Designer Track' ), 7 );

ck( 'a track of the institution: its name', WPCPM_Institution_Tracks::answer_text( REC, 'Developer Track' ), 'Developer Track' );
ck( 'a track with another name: that name', WPCPM_Institution_Tracks::answer_text( REC, 'In Sensei 50h' ), 'WordPress Credits Program 50h' );
ck( 'not sure: the untranslated text', WPCPM_Institution_Tracks::answer_text( REC, 'not-sure' ), 'Not sure yet' );
ck( 'a track of another institution: null', WPCPM_Institution_Tracks::answer_text( REC, 'Designer Track' ), null );
ck( 'empty: null', WPCPM_Institution_Tracks::answer_text( REC, '' ), null );
ck( 'made up: null', WPCPM_Institution_Tracks::answer_text( REC, 'Made Up' ), null );
ck( 'not a string: null', WPCPM_Institution_Tracks::answer_text( REC, array( 'Developer Track' ) ), null );

echo "\n-- the record refuses ---------------------------------------------------\n";

reset_world();
$GLOBALS['record_fails'] = 'meta';
$result                  = WPCPM_Institution_Tracks::save( REC, array( 'In Sensei' ), 7 );
ck( 'an error', $result instanceof WP_Error, true );
ck( 'its code', $result instanceof WP_Error ? $result->get_error_code() : '', 'wpcpm_tracks_record' );
ck( 'no audit row', count( audit_rows() ), 0 );

echo "\n-- the history's words --------------------------------------------------\n";

ck(
	'the sentence for tracks_saved',
	WPCPM_Institution_History::sentence( array( 'kind' => 'tracks_saved', 'message' => '' ) ),
	'The tracks the institution offers were changed.'
);

ck(
	'a real tracks_saved row reads in the history as its stored message',
	array(
		WPCPM_Institution_History::sentence( array( 'kind' => 'tracks_saved', 'message' => 'Tracks offered were set: Designer Track, Developer Track.' ) ),
		WPCPM_Institution_History::sentence( array( 'kind' => 'tracks_saved', 'message' => 'Tracks offered were cleared.' ) ),
	),
	array( 'Tracks offered were set: Designer Track, Developer Track.', 'Tracks offered were cleared.' )
);

echo "\n" . ( $fail ? "FAILED: $fail\n" : "all ok\n" );
exit( $fail ? 1 : 0 );
