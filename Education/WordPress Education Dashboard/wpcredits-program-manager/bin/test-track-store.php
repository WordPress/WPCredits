<?php
/**
 * Where the Track Builder keeps its tracks (1.100.0): the post type, the revisioned definition,
 * the compile step and the uninstall sweep.
 *
 * The stand-ins below model the three WordPress behaviors the store is built around: the post
 * array and every meta value are unslashed on the way in; a revision is saved from inside
 * `wp_update_post()`, with the revisioned meta the post holds at that moment; and
 * `revisions_enabled` is refused for a type that does not support revisions yet. A store that got
 * any of the three wrong passes a pass-through stub and fails here. A check can also make the next
 * `wp_update_post()` fail, as a database error does.
 *
 * Run from the plugin root:  php bin/test-track-store.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '';
}

$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['revisions']  = array();
$GLOBALS['opts']       = array();
$GLOBALS['autoload']   = array();
$GLOBALS['actions']    = array();
$GLOBALS['types']      = array();
$GLOBALS['meta_args']  = array();
$GLOBALS['revisioned'] = array();
$GLOBALS['wrong']      = array();
$GLOBALS['next_id']    = 100;
$GLOBALS['db_fails']   = false;

function __( $s, $d = null ) { return $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['actions'][] = array( $hook, $callback, $priority ); }
function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ], $GLOBALS['autoload'][ $k ] ); return true; }
function get_post_stati() { return array( 'publish' => 'publish', 'draft' => 'draft', 'pending' => 'pending', 'private' => 'private', 'trash' => 'trash', 'auto-draft' => 'auto-draft' ); }
function register_post_type( $type, $args ) { $GLOBALS['types'][ $type ] = $args; }
function post_type_supports( $type, $feature ) { return in_array( $feature, $GLOBALS['types'][ $type ]['supports'] ?? array(), true ); }
function register_post_meta( $type, $key, $args ) {
	// WordPress refuses revisions for a type that does not support them yet, with a notice.
	if ( ! empty( $args['revisions_enabled'] ) && ! post_type_supports( $type, 'revisions' ) ) {
		$GLOBALS['wrong'][]        = $key;
		$args['revisions_enabled'] = false;
	}
	$GLOBALS['meta_args'][ $type ][ $key ] = $args;
	if ( ! empty( $args['revisions_enabled'] ) ) {
		$GLOBALS['revisioned'][ $type ][ $key ] = true;
	}
	return true;
}
function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr           = wp_unslash( $postarr ); // WordPress unslashes the post array.
	$post              = new WP_Post();
	$post->ID          = ++$GLOBALS['next_id'];
	$post->post_type   = $postarr['post_type'] ?? 'post';
	$post->post_status = $postarr['post_status'] ?? 'draft';
	$post->post_title  = $postarr['post_title'] ?? '';
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function wp_update_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );
	$id      = (int) ( $postarr['ID'] ?? 0 );
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post' ) : 0;
	}
	// A check can make the next update fail, as a database error does: nothing is written, and the
	// caller hears of it only when it asked for a WP_Error.
	if ( $GLOBALS['db_fails'] ) {
		$GLOBALS['db_fails'] = false;
		return $wp_error ? new WP_Error( 'db_update_error' ) : 0;
	}
	foreach ( array( 'post_title', 'post_status' ) as $field ) {
		if ( isset( $postarr[ $field ] ) ) {
			$GLOBALS['posts'][ $id ]->$field = $postarr[ $field ];
		}
	}
	// The revision is taken from inside the update, from the meta the post holds right now.
	$type = $GLOBALS['posts'][ $id ]->post_type;
	if ( post_type_supports( $type, 'revisions' ) ) {
		$copy = array();
		foreach ( array_keys( $GLOBALS['revisioned'][ $type ] ?? array() ) as $key ) {
			$copy[ $key ] = $GLOBALS['pmeta'][ $id ][ $key ] ?? null;
		}
		$GLOBALS['revisions'][ $id ][] = $copy;
	}
	return $id;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_posts( $args = array() ) {
	$statuses = (array) ( $args['post_status'] ?? 'publish' );
	$out      = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( ! in_array( $post->post_status, $statuses, true ) ) {
			continue;
		}
		$out[] = 'ids' === ( $args['fields'] ?? '' ) ? $post->ID : $post;
	}
	return $out; // Ascending IDs: the store is keyed by an ID that only grows.
}
function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value ); return true; } // Unslashed, as WordPress does.
function apply_filters( $tag, $value ) { return $value; } // Nothing hooked: the program map as its PHP describes it.
function get_current_user_id() { return 7; }
function add_option( $k, $v = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $autoload;
	return true;
}
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }

/** The hand-written forms the switch compares a definition with: whatever a check sets. */
class WPCPM_Student_Report_Form {
	public static $forms = array();
	public static function builtin_fields( $track ) { return self::$forms[ $track ] ?? array(); }
}
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }

/** The settings: the list the status rule reads, and the one write publishing makes. */
class WPCPM_Settings {
	public static $added = array();
	public static function get() { return array( 'past_statuses' => array( 'Graduate', 'Dropped out' ) ); }
	public static function add_student_status( $status ) { self::$added[] = $status; return true; }
}

/** The sync's column map, its report half: what the reserved-column rule reads. */
class WPCPM_Mentors_Sync {
	public static function fields() {
		return array( 'report_name' => 'Name', 'report_email' => 'Email', 'report_status' => 'Status', 'report_mentor' => 'Mentor', 'report_instituton' => 'Educational institution', 'report_start' => 'Internship Start Date', 'report_end' => 'Internship End Date', 'report_link' => 'Personal link', 'report_link_50h' => '50h personal link', 'report_link_dev' => 'Dev Track ONLY personal link' );
	}
}

function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ], $GLOBALS['revisions'][ (int) $id ] ); return true; }

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-store.php';

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

/** A small definition, told apart by its status and key. */
function track( $status, $key, $label = null ) {
	return array(
		'schema_version' => 1,
		'status'         => $status,
		'key'            => $key,
		'label'          => null === $label ? $status : $label,
		'hue'            => 'green',
		'questions'      => array(
			'Hours'         => array( 'label' => 'Hours contributed', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 10000, 'group' => 'hours', 'airtable_type' => 'number' ),
			$key . ' notes' => array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project', 'why' => 'A developer note.' ),
		),
	);
}

/** The definition a revision of a post holds, decoded: 0 is the first, -1 the newest. */
function revision( $post_id, $which ) {
	$revisions = $GLOBALS['revisions'][ $post_id ] ?? array();
	$revision  = -1 === $which ? end( $revisions ) : ( $revisions[ $which ] ?? array() );

	return WPCPM_Track_Definition::decode( $revision[ WPCPM_Track_Store::META_DEFINITION ] ?? null );
}

echo "=== Registration ===\n";

WPCPM_Track_Store::init();
ck( 'init() registers the type, then the meta, both on init, and the seeds after them', array_map( function ( $a ) { return array( $a[0], $a[1][1], $a[2] ); }, $GLOBALS['actions'] ), array( array( 'init', 'register_post_type', 10 ), array( 'init', 'register_meta', 10 ), array( 'init', 'maybe_seed', 20 ) ) );

WPCPM_Track_Store::register_meta();
ck( 'registered before its type, the meta would lose its revisions: WordPress refuses them', array( $GLOBALS['wrong'], $GLOBALS['meta_args']['wpcpm_track']['_wpcpm_track_definition']['revisions_enabled'] ), array( array( '_wpcpm_track_definition' ), false ) );
$GLOBALS['wrong']      = array();
$GLOBALS['revisioned'] = array();

WPCPM_Track_Store::register_post_type();
WPCPM_Track_Store::register_meta();
$type = $GLOBALS['types']['wpcpm_track'];
ck( 'the post type is private everywhere', array( $type['public'], $type['publicly_queryable'], $type['show_ui'], $type['show_in_menu'], $type['show_in_rest'], $type['rewrite'], $type['query_var'] ), array( false, false, false, false, false, false, false ) );
ck( 'with a title and revisions', $type['supports'], array( 'title', 'revisions' ) );
ck( 'and a capability type nobody is granted', array( $type['capability_type'], $type['map_meta_cap'] ), array( array( 'wpcpm_track', 'wpcpm_tracks' ), true ) );
ck( 'registered in that order, the definition is revisioned, and nothing else about it is open', $GLOBALS['meta_args']['wpcpm_track']['_wpcpm_track_definition'], array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'revisions_enabled' => true, 'auth_callback' => '__return_false' ) );
ck( 'with no notice', $GLOBALS['wrong'], array() );

echo "\n=== create() and save() ===\n";

$id = WPCPM_Track_Store::create( track( 'Marketing Track', 'marketing' ) );
ck( 'create() hands back the new post', is_int( $id ) && $id > 0, true );
ck( 'a draft of the private type, titled by the track\'s name', array( get_post( $id )->post_type, get_post( $id )->post_status, get_post( $id )->post_title ), array( 'wpcpm_track', 'draft', 'Marketing Track' ) );
ck( 'holding the normalized definition', WPCPM_Track_Store::get( $id ), WPCPM_Track_Definition::normalize( track( 'Marketing Track', 'marketing' ) ) );
ck( 'and the history begins at creation: one revision, holding it', array( count( $GLOBALS['revisions'][ $id ] ), revision( $id, 0 ) ), array( 1, WPCPM_Track_Store::get( $id ) ) );

ck( 'save() hands back the post', WPCPM_Track_Store::save( $id, track( 'Marketing Track', 'marketing', 'Marketing Track, renamed' ) ), $id );
ck( 'the newest revision holds the new definition, not the one before it', array( count( $GLOBALS['revisions'][ $id ] ), revision( $id, -1 )['label'] ), array( 2, 'Marketing Track, renamed' ) );
ck( 'and the title follows the name', get_post( $id )->post_title, 'Marketing Track, renamed' );

$awkward = 'Back\\slash, "quoted", Site' . "\u{2019}" . 's';
WPCPM_Track_Store::save( $id, track( 'Marketing Track', 'marketing', $awkward ) );
ck( 'a name with a backslash, quotes and a typographic apostrophe comes back byte for byte', WPCPM_Track_Store::get( $id )['label'], $awkward );
ck( 'in the revision as well', revision( $id, -1 )['label'], $awkward );
ck( 'and in the title', get_post( $id )->post_title, $awkward );

ck( 'save() refuses a post that is not there', WPCPM_Track_Store::save( 999999, track( 'X Track', 'x-track' ) ) instanceof WP_Error, true );
ck( 'and writes nothing for it', isset( $GLOBALS['pmeta'][999999] ), false );
$plain = wp_insert_post( array( 'post_type' => 'post' ) );
ck( 'and refuses a post of another type', WPCPM_Track_Store::save( $plain, track( 'X Track', 'x-track' ) ) instanceof WP_Error, true );
ck( 'get() answers null for another type of post', WPCPM_Track_Store::get( $plain ), null );
ck( 'and for a post that is not there', WPCPM_Track_Store::get( 424242 ), null );

$infinite                              = track( 'Infinite Track', 'infinite' );
$infinite['questions']['Hours']['max'] = INF;
$before                                = count( $GLOBALS['posts'] );
$refused                               = WPCPM_Track_Store::create( $infinite );
ck( 'create() refuses a definition JSON cannot hold, before any post is made', array( $refused instanceof WP_Error ? $refused->get_error_code() : $refused, count( $GLOBALS['posts'] ) ), array( 'wpcpm_track_unencodable', $before ) );
$kept    = WPCPM_Track_Store::get( $id );
$refused = WPCPM_Track_Store::save( $id, $infinite );
ck( 'and save() refuses it without touching what was stored', array( $refused instanceof WP_Error ? $refused->get_error_code() : $refused, WPCPM_Track_Store::get( $id ) ), array( 'wpcpm_track_unencodable', $kept ) );

echo "\n=== compile() ===\n";

$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['revisions'] = array();
$a = WPCPM_Track_Store::create( track( 'Alpha Track', 'alpha' ) );
$b = WPCPM_Track_Store::create( track( 'Beta Track', 'beta' ) );
$c = WPCPM_Track_Store::create( track( 'Gamma Track', 'gamma' ) );
$d = WPCPM_Track_Store::create( track( 'Delta Track', 'delta' ) );
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array();
WPCPM_Tracks::rows(); // Read, so this request has the empty index in hand.
foreach ( array( $a, $b, $d ) as $published ) {
	WPCPM_Track_Store::publish( $published );
}
update_post_meta( $b, WPCPM_Track_Store::META_SOURCE, 'builtin' );
update_post_meta( $a, WPCPM_Track_Store::META_AUTOMATION, '1' );
update_post_meta( $d, WPCPM_Track_Store::META_PUBLISHED, '{not json' );
$rows = WPCPM_Track_Store::compile();

ck( 'only published tracks are compiled, in the order they were made', array_keys( $rows ), array( 'Alpha Track', 'Beta Track' ) );
ck( 'a published copy that cannot be read is left out rather than breaking the rest', isset( $rows['Delta Track'] ), false );
ck( 'and the track list is told why', get_option( WPCPM_Track_Store::OPT_SKIPPED ), array( $d => array( 'unreadable' ) ) );
ck( 'the list is not autoloaded: only the Track Builder reads it', $GLOBALS['autoload'][ WPCPM_Track_Store::OPT_SKIPPED ], false );
ck( 'the index is autoloaded: labels() reads it for every row of every roster', array( $GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] === $rows, $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ), array( true, true ) );
ck( 'each form is an option of its own, not autoloaded', array( $GLOBALS['autoload']['wpcpm_track_fields_alpha'], $GLOBALS['autoload']['wpcpm_track_fields_beta'] ), array( false, false ) );
ck( 'holding the form the live site draws, without the authoring properties', $GLOBALS['opts']['wpcpm_track_fields_alpha'], WPCPM_Track_Definition::compile_fields( WPCPM_Track_Store::get( $a ) ) );
ck( 'a built-in track keeps its source, so the runtime leaves it to its PHP', array( $rows['Alpha Track']['source'], $rows['Beta Track']['source'] ), array( 'definition', 'builtin' ) );
ck( 'the automation tick is carried into the row', array( $rows['Alpha Track']['automation'], $rows['Beta Track']['automation'] ), array( true, false ) );
ck( 'and compiling refreshes what the runtime read this request', WPCPM_Tracks::rows(), $rows );

wp_update_post( array( 'ID' => $a, 'post_status' => 'draft' ) );
$rows = WPCPM_Track_Store::compile();
ck( 'a track taken back to draft leaves the index', array_keys( $rows ), array( 'Beta Track' ) );
ck( 'and its form option goes with it', array_key_exists( 'wpcpm_track_fields_alpha', $GLOBALS['opts'] ), false );

echo "\n=== What compile() reads: the copy each track was published with ===\n";

$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['revisions'] = array();
$GLOBALS['opts']      = array();
WPCPM_Tracks::flush();
WPCPM_Settings::$added = array();

$alpha = WPCPM_Track_Store::create( track( 'Alpha Track', 'alpha' ) );
ck( 'a new track is a draft', WPCPM_Track_Store::state( $alpha ), 'draft' );
ck( 'publish() hands back the track', WPCPM_Track_Store::publish( $alpha ), $alpha );
ck( 'now published, its copy the definition as it stood', array( WPCPM_Track_Store::state( $alpha ), WPCPM_Track_Store::published( $alpha ) ), array( 'published', WPCPM_Track_Store::get( $alpha ) ) );
ck( 'and compiled', WPCPM_Tracks::rows()['Alpha Track']['label'], 'Alpha Track' );
ck( 'its status joins "Currently mentoring", so the students sync reads its students', WPCPM_Settings::$added, array( 'Alpha Track' ) );

WPCPM_Track_Store::save( $alpha, track( 'Alpha Track', 'alpha', 'Alpha Track, renamed' ) );
ck( 'an edit saved to a published track is an unpublished change', WPCPM_Track_Store::state( $alpha ), 'changed' );
ck( 'which students do not see', WPCPM_Tracks::rows()['Alpha Track']['label'], 'Alpha Track' );

$beta = WPCPM_Track_Store::create( track( 'Beta Track', 'beta' ) );
WPCPM_Track_Store::publish( $beta );
ck( 'nor once another track is published and everything compiles again (the design\'s decision 3.2)', array( WPCPM_Tracks::rows()['Alpha Track']['label'], isset( WPCPM_Tracks::rows()['Beta Track'] ) ), array( 'Alpha Track', true ) );

WPCPM_Track_Store::publish( $alpha );
ck( 'until it is published itself', array( WPCPM_Tracks::rows()['Alpha Track']['label'], WPCPM_Track_Store::state( $alpha ) ), array( 'Alpha Track, renamed', 'published' ) );

echo "\n=== What publish() refuses ===\n";

/** Create a track, publish it, and say what came back: the code, the rules' codes, and its state. */
function publish_new( array $definition, $source = '' ) {
	$id = WPCPM_Track_Store::create( $definition );

	if ( '' !== $source ) {
		update_post_meta( $id, WPCPM_Track_Store::META_SOURCE, $source );
	}

	$result = WPCPM_Track_Store::publish( $id );
	$errors = $result instanceof WP_Error ? array_column( $result->get_error_data()['errors'], 'code' ) : array();

	return array( $result instanceof WP_Error ? $result->get_error_code() : 'published', $errors, WPCPM_Track_Store::state( $id ) );
}

$status_question               = track( 'Gamma Track', 'gamma' );
$status_question['questions']['Status'] = array( 'label' => 'Your status', 'type' => 'text', 'group' => 'project' );
ck( 'a question on a column the syncs own, which would let a student move tracks', publish_new( $status_question ), array( 'wpcpm_track_invalid', array( 'column_reserved' ), 'draft' ) );
ck( 'one of the four built-in statuses, which only the built-in track may hold, and the Designer Track\'s name as well', publish_new( track( 'Designer Track', 'designer-two', 'Designer Two' ) ), array( 'wpcpm_track_invalid', array( 'status_taken', 'status_named' ), 'draft' ) );
ck( 'a key the stylesheets already paint', publish_new( track( 'Design Two Track', 'design' ) ), array( 'wpcpm_track_invalid', array( 'key_reserved' ), 'draft' ) );
ck( 'a built-in track\'s name', publish_new( track( 'Design Two Track', 'design-two', 'Designer Track' ) ), array( 'wpcpm_track_invalid', array( 'label_taken' ), 'draft' ) );
ck( 'another published track\'s status, which is its name as well', publish_new( track( 'Beta Track', 'beta-two', 'Beta Two' ) ), array( 'wpcpm_track_invalid', array( 'status_taken', 'status_named' ), 'draft' ) );
WPCPM_Track_Store::save( $alpha, track( 'Alpha Program', 'alpha' ) );
$moved = WPCPM_Track_Store::publish( $alpha );
ck( 'a new status for a track already published', array( $moved->get_error_code(), array_column( $moved->get_error_data()['errors'], 'code' ) ), array( 'wpcpm_track_invalid', array( 'status_locked' ) ) );
ck( 'and nothing live changes for it', array( WPCPM_Tracks::rows()['Alpha Track']['label'], WPCPM_Track_Store::state( $alpha ) ), array( 'Alpha Track, renamed', 'changed' ) );
WPCPM_Track_Store::save( $alpha, WPCPM_Track_Store::published( $alpha ) );

$seed          = track( 'Designer Track', 'design' );
$seed['label'] = 'Designer Track';
ck( 'a built-in track\'s own definition keeps its status, its key and its name', publish_new( $seed, 'builtin' ), array( 'published', array(), 'published' ) );
ck( 'and is compiled as built-in, so its PHP keeps running it', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );

// The editor asks the same question Publish will ask, because `validation_context()` leaves every
// track's own status out for everybody (T1's decision 4 hole), so an editor built on it would call
// a clash fine and Publish would refuse it on the next screen.
$fine = WPCPM_Track_Store::create( track( 'Delta Track', 'delta' ) );
ck( 'check() answers nothing for a definition that would publish', WPCPM_Track_Store::check( $fine, WPCPM_Track_Store::get( $fine ) ), array() );

$twin   = WPCPM_Track_Store::create( track( 'Alpha Track', 'twin' ) );
$looked = array_column( WPCPM_Track_Store::check( $twin, WPCPM_Track_Store::get( $twin ) ), 'code' );
$tried  = WPCPM_Track_Store::publish( $twin );
ck( 'it sees another published track\'s status', in_array( 'status_taken', $looked, true ), true );
ck( 'and names exactly the rules publish() names, so the editor and Publish cannot disagree',
    $looked === array_column( $tried->get_error_data()['errors'], 'code' ), true );

ck( 'a published track checking the copy it was published with is not refused its own status',
    WPCPM_Track_Store::check( $alpha, WPCPM_Track_Store::published( $alpha ) ), array() );

echo "\n=== What compile() leaves out ===\n";

/** Write a published copy by hand, as nothing but a bug or a hand edit would. */
function tamper( $post_id, callable $change ) {
	$copy = WPCPM_Track_Store::published( $post_id );
	$change( $copy );
	update_post_meta( $post_id, WPCPM_Track_Store::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( $copy ) ) );
}

tamper( $beta, function ( &$copy ) { $copy['questions']['Status'] = array( 'label' => 'Your status', 'type' => 'text', 'group' => 'project' ); } );
$rows = WPCPM_Track_Store::compile();
ck( 'a copy the rules refuse is not compiled, though it was published', isset( $rows['Beta Track'] ), false );
ck( 'and the track list is told why', get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $beta ], array( 'column_reserved' ) );
ck( 'while every other track compiles regardless', array_keys( $rows ), array( 'Alpha Track', 'Designer Track' ) );

$late = WPCPM_Track_Store::create( track( 'Late Track', 'late' ) );
WPCPM_Track_Store::publish( $late );
tamper( $late, function ( &$copy ) { $copy['status'] = 'Alpha Track'; } );
$rows = WPCPM_Track_Store::compile();
ck( 'of two copies claiming one status, the first made keeps it', array( $rows['Alpha Track']['post'], get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $late ] ), array( $alpha, array( 'status_taken' ) ) );
tamper( $late, function ( &$copy ) { $copy['status'] = 'Developer Track'; } );
$rows = WPCPM_Track_Store::compile();
ck( 'and a copy holding a built-in status must hold its key as well', array( isset( $rows['Developer Track'] ), get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $late ] ), array( false, array( 'key_locked' ) ) );

echo "\n=== unpublish(), and the log ===\n";

ck( 'unpublish() hands back the track', WPCPM_Track_Store::unpublish( $alpha ), $alpha );
ck( 'which is a draft again, off the live site', array( WPCPM_Track_Store::state( $alpha ), isset( WPCPM_Tracks::rows()['Alpha Track'] ) ), array( 'draft', false ) );
ck( 'its published copy kept, as the record of what was live', WPCPM_Track_Store::published( $alpha )['label'], 'Alpha Track, renamed' );
ck( 'a track that is not published cannot be unpublished', WPCPM_Track_Store::unpublish( $alpha )->get_error_code(), 'wpcpm_track_not_published' );
ck( 'the log says what happened, in order, and who did it', array( array_column( WPCPM_Track_Store::log_entries( $alpha ), 'did' ), array_values( array_unique( array_column( WPCPM_Track_Store::log_entries( $alpha ), 'by' ) ) ) ), array( array( 'publish', 'publish', 'unpublish' ), array( 7 ) ) );
WPCPM_Track_Store::publish( $beta, 42 );
$entries = WPCPM_Track_Store::log_entries( $beta );
ck( 'naming the person the screen says published it', end( $entries )['by'], 42 );
ck( 'and a timestamp', is_int( end( $entries )['at'] ) && end( $entries )['at'] > 0, true );

echo "\n=== When WordPress refuses the status change ===\n";

// wp_update_post() can fail, and the store asks for its WP_Error: a publish or an unpublish
// WordPress refuses must leave the track as it was (the final review of T2a, its M2). Every
// compile() writes the skipped list, so a mark left there shows whether one ran.
$first = WPCPM_Track_Store::create( track( 'Epsilon Track', 'epsilon' ) );
$added = WPCPM_Settings::$added;
update_option( WPCPM_Track_Store::OPT_SKIPPED, 'no compile since', false );
$GLOBALS['db_fails'] = true;
$result              = WPCPM_Track_Store::publish( $first );
ck( 'a first publish WordPress refuses hands back its error', $result instanceof WP_Error ? $result->get_error_code() : $result, 'db_update_error' );
ck( 'and leaves the track a draft with no published copy', array( WPCPM_Track_Store::state( $first ), isset( $GLOBALS['pmeta'][ $first ][ WPCPM_Track_Store::META_PUBLISHED ] ) ), array( 'draft', false ) );
ck( 'its status not added to "Currently mentoring", nothing compiled and nothing logged', array( WPCPM_Settings::$added, get_option( WPCPM_Track_Store::OPT_SKIPPED ), WPCPM_Track_Store::log_entries( $first ) ), array( $added, 'no compile since', array() ) );

$shown = WPCPM_Track_Store::create( track( 'Zeta Track', 'zeta' ) );
WPCPM_Track_Store::publish( $shown );
WPCPM_Track_Store::save( $shown, track( 'Zeta Track', 'zeta', 'Zeta Track, renamed' ) );
$copy  = WPCPM_Track_Store::published( $shown );
$added = WPCPM_Settings::$added;
$log   = WPCPM_Track_Store::log_entries( $shown );
update_option( WPCPM_Track_Store::OPT_SKIPPED, 'no compile since', false );
$GLOBALS['db_fails'] = true;
$result              = WPCPM_Track_Store::publish( $shown );
ck( 'publishing an edit WordPress refuses hands back its error', $result instanceof WP_Error ? $result->get_error_code() : $result, 'db_update_error' );
ck( 'with nothing added, compiled or logged', array( WPCPM_Settings::$added, get_option( WPCPM_Track_Store::OPT_SKIPPED ), WPCPM_Track_Store::log_entries( $shown ) ), array( $added, 'no compile since', $log ) );
ck( 'and puts back the copy it was published with, so no later compile runs the edit', array( WPCPM_Track_Store::published( $shown ), WPCPM_Track_Store::state( $shown ), WPCPM_Track_Store::compile()['Zeta Track']['label'] ), array( $copy, 'changed', 'Zeta Track' ) );

$log = WPCPM_Track_Store::log_entries( $shown );
update_option( WPCPM_Track_Store::OPT_SKIPPED, 'no compile since', false );
$GLOBALS['db_fails'] = true;
$result              = WPCPM_Track_Store::unpublish( $shown );
ck( 'an unpublish WordPress refuses hands back its error', $result instanceof WP_Error ? $result->get_error_code() : $result, 'db_update_error' );
ck( 'and leaves the track published and on the live site, with nothing compiled or logged', array( get_post( $shown )->post_status, isset( WPCPM_Tracks::rows()['Zeta Track'] ), get_option( WPCPM_Track_Store::OPT_SKIPPED ), WPCPM_Track_Store::log_entries( $shown ) ), array( 'publish', true, 'no compile since', $log ) );

echo "\n=== The four built-in tracks, seeded ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['opts']  = array();
WPCPM_Tracks::flush();

$seeded = WPCPM_Track_Store::seed();
ck( 'seed() creates one track for each seed the plugin ships', array_keys( $seeded ), array( '150h', '50h', 'dev', 'design' ) );
$states = array();
foreach ( $seeded as $key => $post_id ) {
	$states[ $key ] = array( WPCPM_Track_Store::state( $post_id ), get_post_meta( $post_id, WPCPM_Track_Store::META_SOURCE, true ), WPCPM_Track_Store::get( $post_id )['status'] );
}
ck( 'each a draft marked built-in, holding its seed', $states, array( '150h' => array( 'draft', 'builtin', 'In Sensei' ), '50h' => array( 'draft', 'builtin', 'In Sensei 50h' ), 'dev' => array( 'draft', 'builtin', 'Developer Track' ), 'design' => array( 'draft', 'builtin', 'Designer Track' ) ) );
ck( 'the seed as shipped, byte for byte', WPCPM_Track_Store::get( $seeded['design'] ), WPCPM_Track_Store::seeds()['design'] );
ck( 'seeding again creates nothing: each status is already held', array( WPCPM_Track_Store::seed(), count( $GLOBALS['posts'] ) ), array( array( '150h' => 0, '50h' => 0, 'dev' => 0, 'design' => 0 ), 4 ) );

// The design's section 6: a built-in track its PHP still runs is read-only, so it can be
// duplicated but not edited. `locked()` reads the status a built-in draft names now, so an edit
// could point a seed at another track (the final review of T2a, its I2).
$kept          = array( WPCPM_Track_Store::get( $seeded['design'] ), get_post( $seeded['design'] )->post_title, count( $GLOBALS['revisions'][ $seeded['design'] ] ) );
$edit          = WPCPM_Track_Store::get( $seeded['design'] );
$edit['label'] = 'Designer Track, edited';
$refused       = WPCPM_Track_Store::save( $seeded['design'], $edit );
ck( 'a seeded draft cannot be saved: its PHP still runs it, and it can be duplicated instead', $refused instanceof WP_Error ? $refused->get_error_code() : $refused, 'wpcpm_track_builtin' );
ck( 'and the refusal writes nothing: its definition, its title and its history are as seeded', array( WPCPM_Track_Store::get( $seeded['design'] ), get_post( $seeded['design'] )->post_title, count( $GLOBALS['revisions'][ $seeded['design'] ] ) ), $kept );

/** Save a seed as another track: what save() answered, and the status the seed holds afterwards. */
function repoint( $post_id, $status, $key ) {
	$definition           = WPCPM_Track_Store::get( $post_id );
	$definition['status'] = $status;
	$definition['key']    = $key;
	$definition['label']  = $status;
	$result               = WPCPM_Track_Store::save( $post_id, $definition );

	return array( $result instanceof WP_Error ? $result->get_error_code() : $result, WPCPM_Track_Store::get( $post_id )['status'] );
}

ck( 'so the 150-hour seed cannot be saved as the Developer Track, which would lock the real one out for good', repoint( $seeded['150h'], 'Developer Track', 'dev' ), array( 'wpcpm_track_builtin', 'In Sensei' ) );
ck( 'nor the 50-hour seed as a Writing Track no page would run, though publishing it would add its status to "Currently mentoring"', repoint( $seeded['50h'], 'Writing Track', 'writing' ), array( 'wpcpm_track_builtin', 'In Sensei 50h' ) );

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['opts']  = array();
WPCPM_Tracks::flush();
WPCPM_Track_Store::maybe_seed();
ck( 'a site seeds itself once, the first time it runs this version', array( count( $GLOBALS['posts'] ), get_option( WPCPM_Track_Store::OPT_SEEDED ) ), array( 4, 1 ) );
ck( 'and writes the index, empty and autoloaded, so no request asks for an option that is not there', array( get_option( WPCPM_Tracks::OPT_TRACKS, 'missing' ), $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ), array( array(), true ) );
WPCPM_Track_Store::maybe_seed();
ck( 'and never again', count( $GLOBALS['posts'] ), 4 );

echo "\n=== Duplicating a track ===\n";

// A new track starts as a copy in T2b (the design's decision 11), and what it was copied from is
// post meta rather than a property: `TRACK_PROPERTIES` does not know it, and `validate()` refuses
// a property it does not know.
$original = WPCPM_Track_Store::create( track( 'Original Track', 'original' ) );
update_post_meta( $original, WPCPM_Track_Store::META_SOURCE, 'builtin' );
$copy_definition           = WPCPM_Track_Store::get( $original );
$copy_definition['status'] = 'Copied Track';
$copy_definition['key']    = 'copied';
$copy_definition['label']  = 'Copied Track';
$copy                      = WPCPM_Track_Store::duplicate( $original, $copy_definition );

ck( 'the copy is a draft holding the definition it was given',
    array( WPCPM_Track_Store::state( $copy ), WPCPM_Track_Store::get( $copy )['status'], WPCPM_Track_Store::get( $copy )['questions'] === WPCPM_Track_Store::get( $original )['questions'] ),
    array( 'draft', 'Copied Track', true ) );
ck( 'it records what it was copied from', (int) get_post_meta( $copy, WPCPM_Track_Store::META_DUPLICATED_FROM, true ), $original );
ck( 'and a copy of a built-in track is its own track, not another built-in one',
    array( WPCPM_Track_Store::source( $copy ), WPCPM_Track_Store::source( $original ) ),
    array( 'definition', 'builtin' ) );

// `duplicate()` records whatever `$from_id` it is handed and never walks further up the chain, so
// a copy of a copy names the copy it actually came from, not the ancestor at the top of it.
$second_definition           = WPCPM_Track_Store::get( $copy );
$second_definition['status'] = 'Copied Track Twice';
$second_definition['key']    = 'copied-twice';
$second_definition['label']  = 'Copied Track Twice';
$copy_of_copy                = WPCPM_Track_Store::duplicate( $copy, $second_definition );

ck( 'duplicating a duplicate records the copy it actually came from, not the original ancestor',
    (int) get_post_meta( $copy_of_copy, WPCPM_Track_Store::META_DUPLICATED_FROM, true ), $copy );

echo "\n=== Trash, and a post that is not a track ===\n";

// `publish()` read the definition and never the post, so a track somebody trashed published again
// from the trash (the final review of T2a, M3), and `state()` called it a draft.
$trashed = WPCPM_Track_Store::create( track( 'Trashed Track', 'trashed' ) );
wp_update_post( array( 'ID' => $trashed, 'post_status' => 'trash' ) );
$plain = wp_insert_post( array( 'post_type' => 'post' ) );

$refused = WPCPM_Track_Store::publish( $trashed );
ck( 'publish() refuses a track in the trash', is_wp_error( $refused ) ? $refused->get_error_code() : $refused, 'wpcpm_track_trashed' );
ck( 'state() says trash, and says nothing at all for a post that is not a track',
    array( WPCPM_Track_Store::state( $trashed ), WPCPM_Track_Store::state( $plain ) ),
    array( 'trash', '' ) );

echo "\n=== Refreshing a built-in draft from the seed ===\n";

// A release that edits a hand-written form leaves every site's built-in draft behind it, and since
// 1.101.1 the store refuses to save a built-in track, so nothing else can bring one back into line
// (the design's decision 12). Only a draft nobody has published is touched.
$by_key = array();

foreach ( array_keys( $GLOBALS['posts'] ) as $id ) {
	$held = WPCPM_Track_Store::get( $id );

	if ( is_array( $held ) && isset( $held['key'] ) ) {
		$by_key[ (string) $held['key'] ] = $id;
	}
}

$design         = $by_key['design'];
$stale          = WPCPM_Track_Store::get( $design );
$stale['label'] = 'Designer Track, left behind';
update_post_meta( $design, WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $stale ) ) );
wp_update_post( array( 'ID' => $design, 'post_title' => 'Staled Designer Title' ) );

ck( 'a built-in draft is refreshed from the seed the plugin ships',
    array( WPCPM_Track_Store::refresh_builtin( $design ), WPCPM_Track_Store::get( $design ) === WPCPM_Track_Store::seeds()['design'] ),
    array( $design, true ) );
ck( 'and the post title follows the seed', get_post( $design )->post_title, WPCPM_Track_Store::seeds()['design']['label'] );

$mine = WPCPM_Track_Store::create( track( 'Marketing Track', 'marketing' ) );
$refused = WPCPM_Track_Store::refresh_builtin( $mine );
ck( 'a track that was never built in is not refreshed', is_wp_error( $refused ) ? $refused->get_error_code() : $refused, 'wpcpm_track_not_builtin' );

$published = $by_key['150h'];
$stale_published = WPCPM_Track_Store::get( $published );
$stale_published['label'] = '150-hour track, left behind';
update_post_meta( $published, WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $stale_published ) ) );
update_post_meta( $published, WPCPM_Track_Store::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( WPCPM_Track_Store::get( $published ) ) ) );
$refused = WPCPM_Track_Store::refresh_builtin( $published );
ck( 'nor is one that has been published: students may be reading it', is_wp_error( $refused ) ? $refused->get_error_code() : $refused, 'wpcpm_track_published' );

// The version the site recorded is how it knows a release moved the seeds under it.
$stale          = WPCPM_Track_Store::get( $by_key['dev'] );
$stale['label'] = 'Developer Track, left behind';
update_post_meta( $by_key['dev'], WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $stale ) ) );
update_option( WPCPM_Track_Store::OPT_SEEDED, WPCPM_Track_Store::SEED_VERSION - 1, true );
WPCPM_Track_Store::maybe_seed();

ck( 'a newer seed version refreshes the drafts and records itself',
    array( WPCPM_Track_Store::get( $by_key['dev'] ) === WPCPM_Track_Store::seeds()['dev'], get_option( WPCPM_Track_Store::OPT_SEEDED ) ),
    array( true, WPCPM_Track_Store::SEED_VERSION ) );
ck( 'and the published one it passed over keeps what it was published with',
    WPCPM_Track_Store::get( $published ) === WPCPM_Track_Store::published( $published ), true );

$count = count( $GLOBALS['posts'] );
WPCPM_Track_Store::maybe_seed();
ck( 'running again on the same version creates nothing and refreshes nothing', count( $GLOBALS['posts'] ), $count );

echo "\n=== The switch between a built-in track's PHP and its definition ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['opts']  = array();
WPCPM_Tracks::flush();

$designer = array(
	'schema_version'  => 1,
	'status'          => 'Designer Track',
	'key'             => 'design',
	'label'           => 'Designer Track',
	'course_url'      => WPCPM_Program::course_url( 'Designer Track' ),
	'learn_course_id' => WPCPM_Program::course_id( 'Designer Track' ),
	'hours_target'    => 150,
	'hue'             => 'pink',
	'questions'       => track( 'Designer Track', 'design' )['questions'],
);
$form = WPCPM_Track_Definition::compile_fields( WPCPM_Track_Definition::normalize( $designer ) );
$d    = WPCPM_Track_Store::create( $designer );
update_post_meta( $d, WPCPM_Track_Store::META_SOURCE, 'builtin' );
WPCPM_Track_Store::publish( $d );

// Sequence a of the final review's I1: an edit saved while the PHP runs a published track
// passes the switch unseen, since the switch compares the published copy, and once the edit
// is published the fingerprint taken at the switch still opens the way back, dropping it.
$early          = WPCPM_Track_Store::get( $d );
$early['label'] = 'Designer Track, edited while its PHP runs it';
$refused        = WPCPM_Track_Store::save( $d, $early );
ck( 'published and still run by its PHP, it takes no edit either, so none can ride the switch onto the live site', array( $refused instanceof WP_Error ? $refused->get_error_code() : $refused, WPCPM_Track_Store::state( $d ) ), array( 'wpcpm_track_builtin', 'published' ) );

WPCPM_Student_Report_Form::$forms['design'] = array( 'Something else' => array( 'label' => 'x', 'type' => 'text', 'group' => 'project' ) );
$refused = WPCPM_Track_Store::switch_to_definition( $d );
ck( 'a built-in track cannot switch while its definition differs from its PHP', array( $refused->get_error_code(), $refused->get_error_data()['differences'] ), array( 'wpcpm_track_not_equivalent', array( 'form' ) ) );
ck( 'and keeps running from its PHP', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );

WPCPM_Student_Report_Form::$forms['design'] = $form;
ck( 'identical, the two are equivalent', WPCPM_Track_Store::equivalence( $d ), array() );
ck( 'and the switch hands back the track', WPCPM_Track_Store::switch_to_definition( $d ), $d );
ck( 'which now runs from its definition', array( WPCPM_Tracks::rows()['Designer Track']['source'], get_post_meta( $d, WPCPM_Track_Store::META_SOURCE, true ) ), array( 'definition', '' ) );
ck( 'switching back is open while nothing has been edited', WPCPM_Track_Store::switch_to_builtin( $d ), $d );
ck( 'and puts its PHP back in charge', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );

WPCPM_Track_Store::switch_to_definition( $d );
$edited          = WPCPM_Track_Store::get( $d );
$edited['label'] = 'Designer Track, edited';
WPCPM_Track_Store::save( $d, $edited );
ck( 'once the definition is edited, the way back is closed: it would drop the edit', WPCPM_Track_Store::switch_to_builtin( $d )->get_error_code(), 'wpcpm_track_edited' );
ck( 'the log records each switch', array_column( WPCPM_Track_Store::log_entries( $d ), 'did' ), array( 'publish', 'switch_definition', 'switch_builtin', 'switch_definition' ) );

// The fingerprint alone cannot see an edit that was published and then saved back to the old
// text without being published: going back would put the PHP in front of students and drop the
// edit they see (the final review of T2a, its I1, sequence b). The published copy must be the
// PHP's as well, which equivalence() answers.
WPCPM_Track_Store::publish( $d );
ck( 'and it stays closed once the edit is published', WPCPM_Track_Store::switch_to_builtin( $d )->get_error_code(), 'wpcpm_track_edited' );
$reverted          = $edited;
$reverted['label'] = $designer['label'];
WPCPM_Track_Store::save( $d, $reverted );
$refused = WPCPM_Track_Store::switch_to_builtin( $d );
ck( 'saving the old text back does not reopen it while students see the edit, and the refusal says what differs', $refused instanceof WP_Error ? array( $refused->get_error_code(), $refused->get_error_data()['differences'] ) : $refused, array( 'wpcpm_track_not_equivalent', array( 'label' ) ) );
WPCPM_Track_Store::publish( $d );
ck( 'once the old text is published, students see the PHP\'s copy again, and the way back opens', WPCPM_Track_Store::switch_to_builtin( $d ), $d );
WPCPM_Track_Store::switch_to_definition( $d );
WPCPM_Track_Store::unpublish( $d );
ck( 'an unpublished track may go back as well: its PHP already runs it, so going back changes nothing a student sees', WPCPM_Track_Store::switch_to_builtin( $d ), $d );

$other = WPCPM_Track_Store::create( track( 'Marketing Track', 'marketing' ) );
WPCPM_Track_Store::publish( $other );
ck( 'a track that was never built in cannot switch', WPCPM_Track_Store::switch_to_definition( $other )->get_error_code(), 'wpcpm_track_not_builtin' );
ck( 'nor switch back', WPCPM_Track_Store::switch_to_builtin( $other )->get_error_code(), 'wpcpm_track_not_switched' );
ck( 'and is equivalent to no PHP', WPCPM_Track_Store::equivalence( $other ), array( 'not_builtin' ) );

$drift = WPCPM_Track_Store::create( array( 'hours_target' => 120, 'label' => 'Designer Track, renamed' ) + $designer );
update_post_meta( $drift, WPCPM_Track_Store::META_SOURCE, 'builtin' );
update_post_meta( $drift, WPCPM_Track_Store::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( WPCPM_Track_Store::get( $drift ) ) ) );
wp_update_post( array( 'ID' => $drift, 'post_status' => 'publish' ) );
ck( 'equivalence names each way a definition differs from its PHP', WPCPM_Track_Store::equivalence( $drift ), array( 'label', 'hours' ) );

echo "\n=== php_differences(), for the preflight ===\n";

// The preflight calls php_differences() to check a definition before publishing, not after.
// It uses the same comparison logic as equivalence() but on the definition being handed to it.
// Use the designer track that's already set up with the correct form.
$not_builtin  = WPCPM_Track_Store::create( track( 'Not Built-In', 'not-builtin' ) );
update_post_meta( $not_builtin, WPCPM_Track_Store::META_SOURCE, 'definition' );
$builtin_id = WPCPM_Track_Store::create( $designer );
update_post_meta( $builtin_id, WPCPM_Track_Store::META_SOURCE, 'builtin' );

ck( 'a built-in track whose definition matches its PHP answers empty',
    WPCPM_Track_Store::php_differences( $builtin_id, $designer ), array() );

$diff_label = $designer;
$diff_label['label'] = 'Different Title';
ck( 'a built-in track whose label differs answers with that difference',
    WPCPM_Track_Store::php_differences( $builtin_id, $diff_label ), array( 'label' ) );

$diff_course = $designer;
$diff_course['course_url'] = 'https://learn.wordpress.org/course/different/';
ck( 'a built-in track whose course URL differs answers with that difference',
    WPCPM_Track_Store::php_differences( $builtin_id, $diff_course ), array( 'course' ) );

$diff_hours = $designer;
$diff_hours['hours_target'] = 999;
ck( 'a built-in track whose hours target differs answers with that difference',
    WPCPM_Track_Store::php_differences( $builtin_id, $diff_hours ), array( 'hours' ) );

$diff_form = $designer;
$diff_form['questions']['New Question'] = array( 'label' => 'New Q', 'type' => 'text', 'group' => 'project' );
ck( 'a built-in track whose form differs answers with that difference',
    WPCPM_Track_Store::php_differences( $builtin_id, $diff_form ), array( 'form' ) );

ck( 'a non-built-in track answers empty, meaning no comparison to PHP',
    WPCPM_Track_Store::php_differences( $not_builtin, $designer ), array() );

echo "\n=== delete_all(), for uninstall ===\n";

$trashed = WPCPM_Track_Store::create( track( 'Trashed Track', 'trashed' ) );
wp_update_post( array( 'ID' => $trashed, 'post_status' => 'trash' ) );
$orphan = WPCPM_Track_Store::create( track( 'Orphan Track', 'orphan' ) );
update_option( 'wpcpm_track_fields_orphan', array( 'x' => array() ), false ); // Written by a compile that never finished.
$other = wp_insert_post( array( 'post_type' => 'post' ) );

WPCPM_Track_Store::delete_all();

ck( 'every track goes, a draft and a trashed one included', get_posts( array( 'post_type' => 'wpcpm_track', 'post_status' => array_keys( get_post_stati() ), 'fields' => 'ids' ) ), array() );
ck( 'with its revisions', array_values( array_intersect( array_keys( $GLOBALS['revisions'] ), array( $a, $b, $c, $d, $trashed, $orphan ) ) ), array() );
ck( 'the index and every form option go, one that only a post still knew included', array_values( preg_grep( '/^wpcpm_track/', array_keys( $GLOBALS['opts'] ) ) ), array() );
ck( 'and nothing that is not a track is touched', null !== get_post( $other ), true );
ck( 'the runtime forgets what it read', WPCPM_Tracks::rows(), array() );

echo "\n=== Wired into the plugin ===\n";

$uninstall = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
ck( 'uninstall.php loads the store and the two classes it reads', array( false !== strpos( $uninstall, "includes/tracks/class-wpcpm-track-store.php'" ), false !== strpos( $uninstall, "includes/tracks/class-wpcpm-tracks.php'" ), false !== strpos( $uninstall, "includes/tracks/class-wpcpm-track-definition.php'" ) ), array( true, true, true ) );
ck( 'and calls delete_all()', false !== strpos( $uninstall, 'WPCPM_Track_Store::delete_all();' ), true );
ck( 'and sweeps any form option the index lost track of', false !== strpos( $uninstall, "array( 'wpcpm_institution_modules_', WPCPM_Tracks::OPT_FIELDS_PREFIX ) as \$wpcpm_prefix" ), true );
$main = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
ck( 'the plugin boots the store and the runtime', array( false !== strpos( $main, 'WPCPM_Track_Store::init();' ), false !== strpos( $main, 'WPCPM_Tracks::init();' ) ), array( true, true ) );

echo "\n=== Publishing and the settings (the design's decision 13) ===\n";

// Those four statuses were the program's before the Track Builder existed and are edited in
// Settings, so a seed published again must not ask for one to be put back.
WPCPM_Settings::$added = array();

$builtin_post = WPCPM_Track_Store::create( WPCPM_Track_Store::seeds()['dev'] );
update_post_meta( $builtin_post, WPCPM_Track_Store::META_SOURCE, 'builtin' );
$builtin_done = WPCPM_Track_Store::publish( $builtin_post );

ck( 'a built-in track publishes', is_wp_error( $builtin_done ) ? $builtin_done->get_error_message() : true, true );

ck( 'and asks for no status to be added, so one a manager took out stays out',
    WPCPM_Settings::$added, array() );

$mine_post = WPCPM_Track_Store::create( track( 'Growth Track', 'growth' ) );
$mine_done = WPCPM_Track_Store::publish( $mine_post );

ck( 'a track of somebody\'s own publishes too', is_wp_error( $mine_done ) ? $mine_done->get_error_message() : true, true );

ck( 'and its status is the one added, which is what makes its students sync',
    WPCPM_Settings::$added, array( 'Growth Track' ) );

echo "\n=== Log entries with detail ===\n";

$track = WPCPM_Track_Store::create( track( 'Test Track', 'test' ) );

WPCPM_Track_Store::log( $track, 'columns', 5, array( 'columns' => array( 'One', 'Two' ) ) );
$entries_with = WPCPM_Track_Store::log_entries( $track );

ck( 'a log entry with detail stores the detail field',
    isset( $entries_with[0]['detail'] ) && isset( $entries_with[0]['detail']['columns'] ),
    true );

ck( 'the detail contains the columns',
    $entries_with[0]['detail']['columns'], array( 'One', 'Two' ) );

$track2 = WPCPM_Track_Store::create( track( 'Other Track', 'other' ) );

WPCPM_Track_Store::log( $track2, 'publish', 5 );
$entries_without = WPCPM_Track_Store::log_entries( $track2 );

ck( 'a log entry without detail does not have the detail field',
    isset( $entries_without[0]['detail'] ), false );

ck( 'but it still has the required fields',
    array( isset( $entries_without[0]['at'] ), isset( $entries_without[0]['by'] ), isset( $entries_without[0]['did'] ) ),
    array( true, true, true ) );


printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
