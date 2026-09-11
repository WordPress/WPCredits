<?php
/**
 * Where the Track Builder keeps its tracks (1.100.0): the post type, the revisioned definition,
 * the compile step and the uninstall sweep.
 *
 * The stand-ins below model the three WordPress behaviors the store is built around: the post
 * array and every meta value are unslashed on the way in; a revision is saved from inside
 * `wp_update_post()`, with the revisioned meta the post holds at that moment; and
 * `revisions_enabled` is refused for a type that does not support revisions yet. A store that got
 * any of the three wrong passes a pass-through stub and fails here.
 *
 * Run from the plugin root:  php bin/test-track-store.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	public function __construct( $c = '' ) { $this->code = $c; }
	public function get_error_code() { return $this->code; }
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
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ], $GLOBALS['revisions'][ (int) $id ] ); return true; }

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
ck( 'init() registers the type, then the meta, both on init', array_map( function ( $a ) { return array( $a[0], $a[1][1] ); }, $GLOBALS['actions'] ), array( array( 'init', 'register_post_type' ), array( 'init', 'register_meta' ) ) );

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

echo "\n=== compile() ===\n";

$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['revisions'] = array();
$a = WPCPM_Track_Store::create( track( 'Alpha Track', 'alpha' ) );
$b = WPCPM_Track_Store::create( track( 'Beta Track', 'beta' ) );
$c = WPCPM_Track_Store::create( track( 'Gamma Track', 'gamma' ) );
$d = WPCPM_Track_Store::create( track( 'Delta Track', 'delta' ) );
foreach ( array( $a, $b, $d ) as $published ) {
	wp_update_post( array( 'ID' => $published, 'post_status' => 'publish' ) );
}
update_post_meta( $b, WPCPM_Track_Store::META_SOURCE, 'builtin' );
update_post_meta( $a, WPCPM_Track_Store::META_AUTOMATION, '1' );
update_post_meta( $d, WPCPM_Track_Store::META_DEFINITION, '{not json' );

$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array();
WPCPM_Tracks::rows(); // Read, so this request has the empty index in hand.
$rows = WPCPM_Track_Store::compile();

ck( 'only published tracks are compiled, in the order they were made', array_keys( $rows ), array( 'Alpha Track', 'Beta Track' ) );
ck( 'a definition that cannot be read is left out rather than breaking the rest', isset( $rows['Delta Track'] ), false );
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
$main = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
ck( 'the plugin boots the store and the runtime', array( false !== strpos( $main, 'WPCPM_Track_Store::init();' ), false !== strpos( $main, 'WPCPM_Tracks::init();' ) ), array( true, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
