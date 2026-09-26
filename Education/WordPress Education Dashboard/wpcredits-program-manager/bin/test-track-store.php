<?php
/**
 * Where the Track Builder keeps its tracks (1.100.0): the post type, the revisioned definition,
 * the compile step and the uninstall sweep.
 *
 * The stand-ins below model the three WordPress behaviors the store is built around: the post
 * array and every meta value are unslashed on the way in; a revision is saved from inside
 * `wp_update_post()`, with the revisioned meta the post holds at that moment; and
 * `revisions_enabled` is refused for a type that does not support revisions yet. A store that got
 * any of the three wrong passes a pass-through stub and fails here. A check can also make
 * `wp_update_post()` fail, as a database error does: the next call, or every call it picks. Every
 * option written is noted in order, so a check can count the compiles a call ran: each writes the
 * index once. Every option read and every option added are noted too, and a check can make an add
 * lose, as it does when another request wrote the row after this one read that it was not there.
 *
 * Run from the plugin root:  php bin/test-track-store.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_parent = 0, $post_author = 0, $post_date_gmt = '';
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
$GLOBALS['written']    = array();
$GLOBALS['read']       = array();
$GLOBALS['adds']       = array();
$GLOBALS['add_loses']  = array();

function __( $s, $d = null ) { return $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['actions'][] = array( $hook, $callback, $priority ); }
function add_filter() {}
function get_option( $k, $d = false ) { $GLOBALS['read'][] = $k; return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; $GLOBALS['written'][] = $k; return true; }
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
	// caller hears of it only when it asked for a WP_Error. `db_refuses` fails every update it picks,
	// for as long as a check leaves it set.
	if ( $GLOBALS['db_fails'] || ( isset( $GLOBALS['db_refuses'] ) && call_user_func( $GLOBALS['db_refuses'], $postarr ) ) ) {
		$GLOBALS['db_fails'] = false;
		return $wp_error ? new WP_Error( 'db_update_error' ) : 0;
	}
	foreach ( array( 'post_title', 'post_status' ) as $field ) {
		if ( isset( $postarr[ $field ] ) ) {
			$GLOBALS['posts'][ $id ]->$field = $postarr[ $field ];
		}
	}
	// The revision is taken from inside the update, from the meta the post holds right now. As in
	// WordPress, it is a post of its own, of type `revision`, with the revisioned meta copied onto
	// it, a date of its own and the saving user as its author, so `wp_get_post_revisions()` and
	// `get_post_meta()` on the revision's ID answer the way they do on a live site (T3b).
	$type = $GLOBALS['posts'][ $id ]->post_type;
	if ( post_type_supports( $type, 'revisions' ) ) {
		$copy = array();
		foreach ( array_keys( $GLOBALS['revisioned'][ $type ] ?? array() ) as $key ) {
			$copy[ $key ] = $GLOBALS['pmeta'][ $id ][ $key ] ?? null;
		}
		$GLOBALS['revisions'][ $id ][] = $copy;
		$rev                = new WP_Post();
		$rev->ID            = 100000 + count( $GLOBALS['posts'] );
		$rev->post_type     = 'revision';
		$rev->post_parent   = $id;
		$rev->post_author   = get_current_user_id();
		$rev->post_date_gmt = gmdate( 'Y-m-d H:i:s', 1700000000 + 60 * count( $GLOBALS['revisions'][ $id ] ) );
		$GLOBALS['posts'][ $rev->ID ] = $rev;
		$GLOBALS['pmeta'][ $rev->ID ] = $copy;
	}
	return $id;
}
function wp_get_post_revisions( $post_id, $args = array() ) {
	$found = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'revision' === $post->post_type && (int) $post->post_parent === (int) $post_id ) {
			$found[] = $post;
		}
	}
	// Newest first, as WordPress orders them, and no more than asked for.
	usort( $found, function ( $a, $b ) { return strcmp( $b->post_date_gmt, $a->post_date_gmt ) ?: $b->ID - $a->ID; } );
	$limit = (int) ( $args['posts_per_page'] ?? -1 );
	return $limit > 0 ? array_slice( $found, 0, $limit ) : $found;
}
// What a site keeps: -1 unless a check sets a cap, as WordPress answers where nothing limits
// revisions. The pruning itself is not modeled; the store only reads the number (T3b).
function wp_revisions_to_keep( $post ) { return isset( $GLOBALS['revisions_cap'] ) ? (int) $GLOBALS['revisions_cap'] : -1; }
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
// Nothing hooked: the program map holds no rows of its own, so each of its five maps answers empty
// here, as it does on a site with nothing compiled.
function apply_filters( $tag, $value ) { return $value; }
function get_current_user_id() { return 7; }
function add_option( $k, $v = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) || in_array( $k, $GLOBALS['add_loses'], true ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $autoload;
	$GLOBALS['adds'][]         = array( $k, $autoload );
	return true;
}
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }

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
// For the publish checklist, which the store ticks when it publishes one of the four itself.
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-publish.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-store.php';

// The claim on the upgrade of the seeding and how long it holds, written out here, as the uninstall
// suite writes its names out, so that a check holds the store's own constants to them.
$upgrade_lock    = 'wpcpm_tracks_upgrade_lock';
$upgrade_timeout = 120;

$fails = 0;
$total = 0;

/** How many track posts there are: the posts table holds their revisions too, since T3b models them. */
function track_count() {
	return count( WPCPM_Track_Store::all_ids() );
}

/** How many compiles ran since the note of option writes was last cleared: each writes the index once. */
function compiles() {
	return count( array_keys( $GLOBALS['written'], WPCPM_Tracks::OPT_TRACKS, true ) );
}

/** A track's log as a check compares it: each line's time is asked only to be a timestamp. */
function log_lines( $post_id ) {
	return array_map(
		function ( $entry ) {
			$entry['at'] = is_int( $entry['at'] ?? null ) && $entry['at'] > 0;
			return $entry;
		},
		WPCPM_Track_Store::log_entries( $post_id )
	);
}

/** A site with nothing on it yet, which is where each seeding section starts. */
function fresh_site() {
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['revisions']  = array();
	$GLOBALS['opts']       = array();
	$GLOBALS['autoload']   = array();
	$GLOBALS['written']    = array();
	$GLOBALS['read']       = array();
	$GLOBALS['adds']       = array();
	$GLOBALS['add_loses']  = array();
	WPCPM_Settings::$added = array();
	WPCPM_Tracks::flush();
}

/**
 * The lines the site writes when it publishes one of the four original tracks itself, as the live
 * site's four were logged by the person who published them: the publish, then a tick for each item.
 */
function site_lines( $step, array $items = array( 'automation', 'welcome', 'choices' ) ) {
	$lines = array( array( 'at' => true, 'by' => 0, 'did' => 'publish', 'detail' => array( 'plugin' => $step ) ) );

	foreach ( $items as $item ) {
		$lines[] = array( 'at' => true, 'by' => 0, 'did' => 'tick-' . $item, 'detail' => array( 'plugin' => $step ) );
	}

	return $lines;
}

/** A track's checklist as the publish screen reads it: done, by whom, and when, "now" for a tick made since `$since`. */
function ticks_read( $post_id, $since ) {
	return array_map(
		function ( $item ) use ( $since ) {
			return array( $item['ticked'], $item['by'], $item['at'] >= $since ? 'now' : $item['at'] );
		},
		WPCPM_Track_Publish::checklist( $post_id )
	);
}

/**
 * The four seeds as seed version 1 left a site: each a draft marked `builtin` and never published,
 * which its hand-written form ran until somebody published it and switched it to its definition.
 */
function version_one_seeds() {
	$ids = array();

	foreach ( WPCPM_Track_Store::seeds() as $key => $seed ) {
		$ids[ $key ] = WPCPM_Track_Store::create( $seed );
		update_post_meta( $ids[ $key ], WPCPM_Track_Store::META_SOURCE, 'builtin' );
	}

	return $ids;
}

/** What an upgrade could move on a site: the index, every form, and each track post as it stands. */
function snapshot() {
	$site = array(
		'index' => get_option( WPCPM_Tracks::OPT_TRACKS ),
		'forms' => array(),
		'posts' => array(),
	);

	foreach ( $GLOBALS['opts'] as $name => $value ) {
		if ( 0 === strpos( $name, WPCPM_Tracks::OPT_FIELDS_PREFIX ) ) {
			$site['forms'][ $name ] = $value;
		}
	}

	foreach ( WPCPM_Track_Store::all_ids() as $post_id ) {
		$site['posts'][ $post_id ] = array( get_post( $post_id )->post_status, $GLOBALS['pmeta'][ $post_id ] ?? array() );
	}

	return $site;
}

/** Whether WordPress refuses an update: the Developer Track's status change, as a database error would. */
function refuse_developer_publish( $postarr ) {
	return 'publish' === ( $postarr['post_status'] ?? '' ) && 'Developer Track' === get_post( $postarr['ID'] )->post_title;
}

/**
 * Whether WordPress refuses an update: the title `save()` writes for the 150-hour seed once its
 * post is inserted, as a database error between the two writes would.
 */
function refuse_150h_title( $postarr ) {
	return 'WordPress Credits Program 150h' === ( $postarr['post_title'] ?? null );
}

/** The four seeds as a request left them that died between writing each copy and publishing its post. */
function half_published( array $ids ) {
	foreach ( $ids as $post_id ) {
		update_post_meta( $post_id, WPCPM_Track_Store::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( WPCPM_Track_Store::get( $post_id ) ) ) );
	}
}

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
ck( 'and not embeddable, which WordPress honors from 6.8 (SURFACES-2)', $type['embeddable'] ?? null, false );
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

// WordPress can refuse the title update `save()` makes once the post is inserted, as a database
// error between the two writes does. Left behind, the post would be a draft nobody asked for,
// holding a status, key and name no other track could then take, since a draft holds all three
// (TRACKS-1). New track, Duplicate and the seeding all create through here.
$before              = count( $GLOBALS['posts'] );
$GLOBALS['db_fails'] = true;
$refused             = WPCPM_Track_Store::create( track( 'Half Made Track', 'half-made' ) );
ck( 'create() that WordPress refuses once the post is inserted hands back the refusal and deletes the post again, with the definition it held',
	array( $refused instanceof WP_Error ? $refused->get_error_code() : $refused, count( $GLOBALS['posts'] ), $GLOBALS['pmeta'][ $GLOBALS['next_id'] ] ?? 'none' ),
	array( 'db_update_error', $before, 'none' ) );

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
// A mark left from before the hand-written forms were removed, which decides nothing now.
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
ck( 'no row says where its track runs from, one whose post still carries the old mark included', array( array_key_exists( 'source', $rows['Alpha Track'] ), array_key_exists( 'source', $rows['Beta Track'] ) ), array( false, false ) );
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

/**
 * Create a track, publish it, and say what came back: the code, the rules' codes, and its state.
 *
 * A track publishing refused is deleted once its state is read. `create()` checks nothing, which
 * is how these checks make a track no screen would, and since TRACKS-1 a draft holds its status,
 * key and name as a published track does: left behind, each refused track would be one more
 * clash for every check after it.
 */
function publish_new( array $definition ) {
	$id     = WPCPM_Track_Store::create( $definition );
	$result = WPCPM_Track_Store::publish( $id );
	$errors = $result instanceof WP_Error ? array_column( $result->get_error_data()['errors'], 'code' ) : array();
	$state  = WPCPM_Track_Store::state( $id );

	if ( $result instanceof WP_Error ) {
		WPCPM_Track_Store::delete( $id );
	}

	return array( $result instanceof WP_Error ? $result->get_error_code() : 'published', $errors, $state );
}

$status_question               = track( 'Gamma Track', 'gamma' );
$status_question['questions']['Status'] = array( 'label' => 'Your status', 'type' => 'text', 'group' => 'project' );
ck( 'a question on a column the syncs own, which would let a student move tracks', publish_new( $status_question ), array( 'wpcpm_track_invalid', array( 'column_reserved' ), 'draft' ) );
ck( 'one of the four original tracks\' statuses under a key of its own, refused by name rather than as a key a published track keeps', publish_new( track( 'Designer Track', 'designer-two', 'Designer Two' ) ), array( 'wpcpm_track_invalid', array( 'status_reserved' ), 'draft' ) );
ck( 'a key the stylesheets already paint', publish_new( track( 'Design Two Track', 'design' ) ), array( 'wpcpm_track_invalid', array( 'key_reserved' ), 'draft' ) );
ck( 'one of the four original tracks\' names', publish_new( track( 'Design Two Track', 'design-two', 'Designer Track' ) ), array( 'wpcpm_track_invalid', array( 'label_taken' ), 'draft' ) );
ck( 'another published track\'s status, which is its name as well', publish_new( track( 'Beta Track', 'beta-two', 'Beta Two' ) ), array( 'wpcpm_track_invalid', array( 'status_taken', 'status_named' ), 'draft' ) );
WPCPM_Track_Store::save( $alpha, track( 'Alpha Program', 'alpha' ) );
$moved = WPCPM_Track_Store::publish( $alpha );
ck( 'a new status for a track already published', array( $moved->get_error_code(), array_column( $moved->get_error_data()['errors'], 'code' ) ), array( 'wpcpm_track_invalid', array( 'status_locked' ) ) );
ck( 'and nothing live changes for it', array( WPCPM_Tracks::rows()['Alpha Track']['label'], WPCPM_Track_Store::state( $alpha ) ), array( 'Alpha Track, renamed', 'changed' ) );
WPCPM_Track_Store::save( $alpha, WPCPM_Track_Store::published( $alpha ) );

$seed          = track( 'Designer Track', 'design' );
$seed['label'] = 'Designer Track';
ck( 'one of the four original tracks\' own definition keeps its status, its key and its name', publish_new( $seed ), array( 'published', array(), 'published' ) );
ck( 'and compiles on its own key', WPCPM_Tracks::rows()['Designer Track']['key'], 'design' );

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

// A draft holds its status as a published track does (TRACKS-1): New track, Duplicate and the
// properties Save could each leave one on a status another track holds, and Publish now refuses a
// track while such a draft stands, so the stray is found before it can take the live form with it.
ck( 'while a draft holds its status, a published track is refused its own status, so Publish finds the stray (TRACKS-1)',
    array_column( WPCPM_Track_Store::check( $alpha, WPCPM_Track_Store::published( $alpha ) ), 'code' ), array( 'status_taken', 'status_named' ) );
WPCPM_Track_Store::delete( $twin );
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
ck( 'and a copy holding an original track\'s status must hold its key as well, or it is refused by name', array( isset( $rows['Developer Track'] ), get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $late ] ), array( false, array( 'status_reserved' ) ) );

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

echo "\n=== The four original tracks, seeded published ===\n";

// A fresh site runs its four tracks at once, as it ran their hand-written forms before those were
// removed (the design's decision 39): each seed is created and published, its checklist ticked as the
// live site's four are, and one compile puts the four live. Nobody pressed anything, so the log and
// the ticks name nobody, though somebody is signed in here (user 7), as somebody is on the request
// that seeds a site.
fresh_site();

$start    = time();
$seeded   = WPCPM_Track_Store::seed();
$compiled = compiles();
$keys     = array( '150h', '50h', 'dev', 'design' );
$shipped  = WPCPM_Track_Store::seeds();
$each     = array();
$logs     = array();
$ticked   = array();
$screens  = array();
$forms    = array();

foreach ( $seeded as $key => $post_id ) {
	$each[ $key ]    = array( WPCPM_Track_Store::state( $post_id ), WPCPM_Track_Store::get( $post_id ) === $shipped[ $key ], WPCPM_Track_Store::published( $post_id ) === $shipped[ $key ], get_post_meta( $post_id, WPCPM_Track_Store::META_SOURCE, true ) );
	$logs[ $key ]    = log_lines( $post_id );
	$ticked[ $key ]  = get_post_meta( $post_id, WPCPM_Track_Store::META_AUTOMATION, true );
	$screens[ $key ] = ticks_read( $post_id, $start );
	$forms[ $key ]   = get_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $key ) === WPCPM_Track_Definition::compile_fields( $shipped[ $key ] );
}

ck( 'seed() creates one track for each seed the plugin ships', array_keys( $seeded ), $keys );
ck( 'each published, its definition and its published copy both the seed as shipped, with no mark', $each, array_fill_keys( $keys, array( 'published', true, true, '' ) ) );
ck( 'each logged as published by the site itself, not by whoever is signed in, then its three checklist items ticked, as the live site\'s four were, each line naming the seeding',
	$logs, array_fill_keys( $keys, site_lines( 'seed' ) ) );
ck( 'and the publish screen reads each item done, ticked in nobody\'s name as the seeding ran',
	$screens, array_fill_keys( $keys, array( 'automation' => array( true, 0, 'now' ), 'welcome' => array( true, 0, 'now' ), 'choices' => array( true, 0, 'now' ) ) ) );
ck( 'each with its reports automation item marked done, as the live site\'s four are, which its row carries',
	array( $ticked, array_column( WPCPM_Tracks::rows(), 'automation' ) ), array( array_fill_keys( $keys, '1' ), array( true, true, true, true ) ) );
ck( 'and one compile puts the four live on their own keys, none left out, and adds no status to "Currently mentoring", whose default list holds the four',
	array( $compiled, array_map( function ( $row ) { return $row['key']; }, WPCPM_Tracks::rows() ), get_option( WPCPM_Track_Store::OPT_SKIPPED ), WPCPM_Settings::$added ),
	array( 1, array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ), array(), array() ) );
ck( 'each form the one its seed compiles to', $forms, array_fill_keys( $keys, true ) );

$rows_then = WPCPM_Tracks::rows();
$again     = WPCPM_Track_Store::seed();
$after     = array();

foreach ( $seeded as $key => $post_id ) {
	$after[ $key ] = array( WPCPM_Track_Store::state( $post_id ), count( WPCPM_Track_Store::log_entries( $post_id ) ) );
}

ck( 'seeding again creates and publishes nothing: each status is already held, and the four keep their four log lines and their rows',
	array( $again, track_count(), $after, WPCPM_Tracks::rows() === $rows_then ),
	array( array_fill_keys( $keys, 0 ), 4, array_fill_keys( $keys, array( 'published', 4 ) ), true ) );

// A track holding one of the seeds' keys under a status of its own, which only code can make
// (`create()` checks nothing), holds that seed's place as well: published beside it, the seed would
// meet `key_taken`, and a compile would leave one of the two out.
fresh_site();
WPCPM_Track_Store::create( track( 'Squatter Track', 'dev' ) );
$report = WPCPM_Track_Store::seed();

ck( 'a seed whose key another track holds is passed over as one whose status is held, and the others are published',
	array( $report['dev'], track_count(), array_keys( WPCPM_Tracks::rows() ) ),
	array( 0, 4, array( 'In Sensei', 'In Sensei 50h', 'Designer Track' ) ) );

// WordPress can refuse the status change, as a database error does. A seed left a draft would be
// passed over by the next run as a status already held, so it is deleted again, and the next run
// creates it (the design's decision 33).
fresh_site();
$GLOBALS['db_refuses'] = 'refuse_developer_publish';
$refused               = WPCPM_Track_Store::seed();
unset( $GLOBALS['db_refuses'] );

ck( 'a seed WordPress refuses to publish comes back as the refusal and is deleted again, while the other three publish and compile',
	array( $refused['dev'] instanceof WP_Error ? $refused['dev']->get_error_code() : $refused['dev'], track_count(), array_keys( WPCPM_Tracks::rows() ) ),
	array( 'db_update_error', 3, array( 'In Sensei', 'In Sensei 50h', 'Designer Track' ) ) );

$retried = WPCPM_Track_Store::seed();

ck( 'so the next run creates it and publishes it, and passes the other three over',
	array( $retried['150h'], $retried['50h'], $retried['design'], WPCPM_Track_Store::state( $retried['dev'] ), array_keys( WPCPM_Tracks::rows() ) ),
	array( 0, 0, 0, 'published', array( 'In Sensei', 'In Sensei 50h', 'Designer Track', 'Developer Track' ) ) );

// A seeding cut short inside `create()`: the 150-hour seed's post inserted and its definition
// written, then WordPress refusing the title update, as a database error between two writes does.
// Left behind, that post would be a draft never published holding the seed's status and key, which
// every later run passes over as held: the 150-hour track would never be published, and every
// student on it or on no track would be drawn an empty Student Report Card (decision 34). Nothing
// half made is left, so the next run creates the track (the design's decision 39).
fresh_site();
$GLOBALS['db_refuses'] = 'refuse_150h_title';
$cut_short             = WPCPM_Track_Store::seed();
unset( $GLOBALS['db_refuses'] );

$holding = array();

foreach ( WPCPM_Track_Store::all_ids() as $post_id ) {
	$definition = WPCPM_Track_Store::get( $post_id );

	if ( is_array( $definition ) && 'In Sensei' === ( $definition['status'] ?? '' ) ) {
		$holding[ $post_id ] = WPCPM_Track_Store::state( $post_id );
	}
}

ck( 'a seed whose creation WordPress cuts short comes back as the refusal and leaves no post holding its status, while the other three publish and compile',
	array( $cut_short['150h'] instanceof WP_Error ? $cut_short['150h']->get_error_code() : $cut_short['150h'], $holding, track_count(), array_keys( WPCPM_Tracks::rows() ) ),
	array( 'db_update_error', array(), 3, array( 'In Sensei 50h', 'Developer Track', 'Designer Track' ) ) );

$resumed = WPCPM_Track_Store::seed();

ck( 'so the next run creates the 150-hour track, publishes it and compiles it, and passes the other three over',
	array( $resumed['50h'], $resumed['dev'], $resumed['design'], WPCPM_Track_Store::state( $resumed['150h'] ), track_count(), array_keys( WPCPM_Tracks::rows() ) ),
	array( 0, 0, 0, 'published', 4, array( 'In Sensei 50h', 'Developer Track', 'Designer Track', 'In Sensei' ) ) );

fresh_site();
WPCPM_Track_Store::maybe_seed();

// The stamp is the fresh site's claim, a constant value and autoloaded, which only one request can
// add: a claim holding a time, as the upgrade's does, would let two requests a second apart both seed.
ck( 'a site seeds itself once, the first time it runs the Track Builder, stamped with this version of the seeding, 2, the stamp its only claim',
	array( track_count(), get_option( WPCPM_Track_Store::OPT_SEEDED ), WPCPM_Track_Store::SEED_VERSION, $GLOBALS['adds'] ),
	array( 4, 2, 2, array( array( 'wpcpm_tracks_seeded', true ) ) ) );
ck( 'and the one compile seed() ends with writes the index, autoloaded, the four in it',
	array( compiles(), array_keys( get_option( WPCPM_Tracks::OPT_TRACKS, array() ) ), $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ?? null ),
	array( 1, array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track' ), true ) );
WPCPM_Track_Store::maybe_seed();
ck( 'and never again', track_count(), 4 );

// Two requests on a fresh site: both read that there is no stamp, and the other adds it first.
fresh_site();
$GLOBALS['add_loses'] = array( 'wpcpm_tracks_seeded' );
WPCPM_Track_Store::maybe_seed();

ck( 'a request that loses the claim to another seeds nothing and compiles nothing, and leaves the seeding to the one that won',
	array( track_count(), $GLOBALS['written'], get_option( WPCPM_Track_Store::OPT_SEEDED, 'missing' ) ),
	array( 0, array(), 'missing' ) );

// The compile runs whatever `seed()` created, so a site whose four statuses are all held by drafts
// still finds the index among the autoloaded options.
fresh_site();

foreach ( WPCPM_Track_Store::seeds() as $seed ) {
	WPCPM_Track_Store::create( $seed );
}

WPCPM_Track_Store::maybe_seed();

ck( 'a site whose seeds are all held by drafts gets the index written, empty and autoloaded, so no request asks for an option that is not there',
	array( get_option( WPCPM_Tracks::OPT_TRACKS, 'missing' ), $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ?? null, track_count(), get_option( WPCPM_Track_Store::OPT_SEEDED ) ),
	array( array(), true, 4, 2 ) );

echo "\n=== Duplicating a track ===\n";

// A new track starts as a copy in T2b (the design's decision 11), and what it was copied from is
// post meta rather than a property: `TRACK_PROPERTIES` does not know it, and `validate()` refuses
// a property it does not know.
$original = WPCPM_Track_Store::create( track( 'Original Track', 'original' ) );
// A mark left from before the hand-written forms were removed.
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
ck( 'and a copy carries no mark the original still carries: nothing copies it',
    array( get_post_meta( $copy, WPCPM_Track_Store::META_SOURCE, true ), get_post_meta( $original, WPCPM_Track_Store::META_SOURCE, true ) ),
    array( '', 'builtin' ) );

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

// A definition handed over (PUBLISH-LEARN-1) is asked about only once the post is known to be a
// track that is not in the trash: handed to any other post, it would have put that post live and
// written the published copy and the log onto it.
$handed = WPCPM_Track_Store::publish( $plain, 0, track( 'Plain Track', 'plain' ) );
ck( 'a definition handed over for a post that is not a track is refused as missing, and the post is left a draft with no copy and no log',
    array( is_wp_error( $handed ) ? $handed->get_error_code() : $handed, get_post( $plain )->post_status, get_post_meta( $plain, WPCPM_Track_Store::META_PUBLISHED, true ), get_post_meta( $plain, WPCPM_Track_Store::META_LOG, true ) ),
    array( 'wpcpm_track_missing', 'draft', '', '' ) );

$handed = WPCPM_Track_Store::publish( $trashed, 0, track( 'Trashed Track', 'trashed' ) );
ck( 'and one handed over for a track in the trash is refused as trashed, the track left in the trash with no copy and no log',
    array( is_wp_error( $handed ) ? $handed->get_error_code() : $handed, get_post( $trashed )->post_status, get_post_meta( $trashed, WPCPM_Track_Store::META_PUBLISHED, true ), get_post_meta( $trashed, WPCPM_Track_Store::META_LOG, true ) ),
    array( 'wpcpm_track_trashed', 'trash', '', '' ) );

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
// Read for the prefixes its sweep loops over rather than for the loop's literal text, which the
// final fix wave changed when it folded the Learn copies' prefixes in (item 7).
// bin/test-uninstall.php runs the file and proves what the sweep takes.
$swept_prefixes = array();

if ( preg_match_all( '/foreach \( array\( ([^)]*) \) as \$wpcpm_prefix \)/', $uninstall, $sweeps ) ) {
	foreach ( $sweeps[1] as $sweep_list ) {
		$swept_prefixes = array_merge( $swept_prefixes, array_map( 'trim', explode( ',', $sweep_list ) ) );
	}
}

ck( 'and sweeps any form option the index lost track of, by the prefix the runtime names its forms with', in_array( 'WPCPM_Tracks::OPT_FIELDS_PREFIX', $swept_prefixes, true ), true );
$main = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
ck( 'the plugin boots the store and the runtime', array( false !== strpos( $main, 'WPCPM_Track_Store::init();' ), false !== strpos( $main, 'WPCPM_Tracks::init();' ) ), array( true, true ) );

echo "\n=== Publishing and the settings ===\n";

// Every published track's status joins "Currently mentoring", the four original tracks' included:
// it is what the track's students carry, and the students sync reads only the statuses listed
// there. The exception the design's decision 13 made was for a seed its hand-written form still
// ran, and those are gone.
WPCPM_Settings::$added = array();

$seed_post = WPCPM_Track_Store::create( WPCPM_Track_Store::seeds()['dev'] );
$seed_done = WPCPM_Track_Store::publish( $seed_post );

ck( 'one of the four original tracks publishes', is_wp_error( $seed_done ) ? $seed_done->get_error_message() : true, true );

ck( 'and asks for its status to be added, as every track does',
    WPCPM_Settings::$added, array( 'Developer Track' ) );

$mine_post = WPCPM_Track_Store::create( track( 'Growth Track', 'growth' ) );
$mine_done = WPCPM_Track_Store::publish( $mine_post );

ck( 'a track of somebody\'s own publishes too', is_wp_error( $mine_done ) ? $mine_done->get_error_message() : true, true );

ck( 'and its status is added too, which is what makes its students sync',
    WPCPM_Settings::$added, array( 'Developer Track', 'Growth Track' ) );

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


echo "\n=== Every other track, for the question editor's sharing index ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['opts']  = array();

$shared_a = WPCPM_Track_Store::create( track( 'Sharing A', 'share-a' ) );
$shared_b = WPCPM_Track_Store::create( track( 'Sharing B', 'share-b' ) );
$shared_c = WPCPM_Track_Store::create( track( 'Sharing C', 'share-c' ) );
WPCPM_Track_Store::publish( $shared_b );
// A mark left from before the hand-written forms were removed, which decides nothing now.
update_post_meta( $shared_c, WPCPM_Track_Store::META_SOURCE, 'builtin' );

// An Airtable name can end in a space (the Global Constraints' own `Company `), so Sharing B
// gets a question keyed with a trailing space, to prove a column name travels verbatim.
$shared_b_definition                          = track( 'Sharing B', 'share-b' );
$shared_b_definition['questions']['Company '] = array( 'label' => 'Where you work', 'type' => 'text', 'group' => 'onboarding' );
WPCPM_Track_Store::save( $shared_b, $shared_b_definition );

$others = WPCPM_Track_Store::others( $shared_a );

ck( 'every track but the one asking, oldest first, with its label and its columns',
    array_map( function ( $o ) { return array( $o['label'], $o['columns'] ); }, $others ),
    array(
        array( 'Sharing B', array( 'Hours', 'share-b notes', 'Company ' ) ),
        array( 'Sharing C', array( 'Hours', 'share-c notes' ) ),
    ) );

ck( 'a published track writes its columns; a draft does not yet, one still carrying the old mark included',
    array( array_column( $others, 'published' ), array_column( WPCPM_Track_Store::others( $shared_b ), 'published' ) ),
    array( array( true, false ), array( false, false ) ) );

ck( 'a column name travels verbatim, so a trailing space is kept',
    array( in_array( 'Company ', $others[0]['columns'], true ), in_array( 'Company', $others[0]['columns'], true ) ),
    array( true, false ) );

echo "\n=== A track that was never published can be deleted ===\n";

ck( 'a draft that has never been published has no publish in its log',
    WPCPM_Track_Store::ever_published( $shared_a ), false );

ck( 'a published track has',
    WPCPM_Track_Store::ever_published( $shared_b ), true );

WPCPM_Track_Store::unpublish( $shared_b );

ck( 'and unpublishing does not take that back: the log is the record',
    array( WPCPM_Track_Store::state( $shared_b ), WPCPM_Track_Store::ever_published( $shared_b ) ),
    array( 'draft', true ) );

$refused = WPCPM_Track_Store::delete( $shared_b );

ck( 'so a track that was ever published is refused, and kept',
    array( $refused->get_error_code(), null !== get_post( $shared_b ) ),
    array( 'wpcpm_track_was_published', true ) );

// A mark keeps nothing on its own: what keeps one of the four original tracks is its status once
// it has been published (the design's decision 38), which the section on the four locked to their
// pairs holds.
$marked = WPCPM_Track_Store::create( track( 'Sharing Marked', 'share-marked' ) );
update_post_meta( $marked, WPCPM_Track_Store::META_SOURCE, 'builtin' );
$gone = WPCPM_Track_Store::delete( $marked );

ck( 'a marked track that was never published is deleted like any draft',
    array( $gone instanceof WP_Error ? $gone->get_error_code() : $gone, get_post( $marked ) ), array( $marked, null ) );

ck( 'a track that does not exist is refused',
    WPCPM_Track_Store::delete( 987654 )->get_error_code(), 'wpcpm_track_missing' );

// TRACKS-1: a draft never has a form option of its own, since `compile()` writes one for a
// published track alone, so the option its key names is another track's. Until check() counted
// drafts, two tracks could hold one key: the published sibling below is planted the way that
// happened, the live track first and the stray draft made on its key by `create()`, which checks
// nothing.
$sibling = WPCPM_Track_Store::create( track( 'Sharing Live', 'share-live' ) );
WPCPM_Track_Store::publish( $sibling );
$stray     = WPCPM_Track_Store::create( track( 'Sharing Stray', 'share-live' ) );
$live_form = get_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . 'share-live' );

ck( 'the published sibling has its compiled form, and Publish refuses it while the stray draft holds its key',
    array( is_array( $live_form ) && array() !== $live_form, array_column( WPCPM_Track_Store::check( $sibling, WPCPM_Track_Store::get( $sibling ) ), 'code' ) ),
    array( true, array( 'key_taken' ) ) );

ck( 'a never-published draft is deleted, its post and nothing else: the form its key names is the published sibling\'s, and stays',
    array( WPCPM_Track_Store::delete( $stray ), get_post( $stray ), get_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . 'share-live' ), WPCPM_Tracks::rows()['Sharing Live']['post'] ?? null ),
    array( $stray, null, $live_form, $sibling ) );

ck( 'and the other tracks are untouched, the sibling free to publish again',
    array( null !== get_post( $shared_b ), null !== get_post( $shared_c ), WPCPM_Track_Store::check( $sibling, WPCPM_Track_Store::get( $sibling ) ) ), array( true, true, array() ) );

// A track in publish status is live whatever its log says: one published by hand, or whose log
// lost its line, would leave its row and its form in the index until the next compile (TRACKS-1).
$by_hand = WPCPM_Track_Store::create( track( 'Published By Hand', 'by-hand' ) );
wp_update_post( array( 'ID' => $by_hand, 'post_status' => 'publish' ) );
$refused = WPCPM_Track_Store::delete( $by_hand );

ck( 'a track in publish status is refused though its log has no publish line, and kept',
    array( $refused instanceof WP_Error ? $refused->get_error_code() : $refused, null !== get_post( $by_hand ), WPCPM_Track_Store::ever_published( $by_hand ) ),
    array( 'wpcpm_track_was_published', true, false ) );

echo "\n=== A draft holds its status, key and name (TRACKS-1, BUILDER-1) ===\n";

// New track and Duplicate ask check() with no track of their own, and the properties Save with
// the track being saved: each is refused what another draft holds, as their docblocks say.
$draft_one = WPCPM_Track_Store::create( track( 'Draft One', 'draft-one' ) );
$draft_two = WPCPM_Track_Store::create( track( 'Draft Two', 'draft-two' ) );

ck( 'New track and Duplicate are refused a status another draft holds, and its name',
    array_column( WPCPM_Track_Store::check( 0, track( 'Draft One', 'draft-three', 'Draft Three' ) ), 'code' ), array( 'status_taken', 'status_named' ) );
ck( 'and a key another draft holds',
    array_column( WPCPM_Track_Store::check( 0, track( 'Draft Three', 'draft-one' ) ), 'code' ), array( 'key_taken' ) );
ck( 'and a name another draft holds',
    array_column( WPCPM_Track_Store::check( 0, track( 'Draft Three', 'draft-three', 'Draft One' ) ), 'code' ), array( 'label_taken' ) );
ck( 'a Save onto another draft\'s key is refused',
    array_column( WPCPM_Track_Store::check( $draft_two, track( 'Draft Two', 'draft-one' ) ), 'code' ), array( 'key_taken' ) );
ck( 'while a draft checked against itself is not',
    WPCPM_Track_Store::check( $draft_two, WPCPM_Track_Store::get( $draft_two ) ), array() );

// A trashed draft holds its key as well. Nothing in the plugin trashes a track, but one trashed by
// other means can be restored, and publishing it would then meet a second track on its key; the
// track list offers Delete on it, and deleting it is what frees the key.
$binned = WPCPM_Track_Store::create( track( 'Binned Draft', 'binned' ) );
wp_update_post( array( 'ID' => $binned, 'post_status' => 'trash' ) );

ck( 'a draft in the trash still holds its key, so New track is refused it',
    array( WPCPM_Track_Store::state( $binned ), array_column( WPCPM_Track_Store::check( 0, track( 'Fresh Track', 'binned' ) ), 'code' ) ),
    array( 'trash', array( 'key_taken' ) ) );
ck( 'until delete() removes the draft, which frees the key',
    array( WPCPM_Track_Store::delete( $binned ), WPCPM_Track_Store::check( 0, track( 'Fresh Track', 'binned' ) ) ),
    array( $binned, array() ) );


echo "\n=== The hours group (TRACKS-3) ===\n";

// The Student Report Card draws Total hours as the hours box alone, which holds Hours and nothing
// else, so check() refuses what that box cannot draw; compile() does not ask, so a published track
// that breaks the rule keeps running rather than dropping out of the index.
$hours_track = WPCPM_Track_Store::create( track( 'Hours Track', 'hours-track' ) );
$lab         = track( 'Hours Track', 'hours-track' );
$lab['questions']['Hours in research lab'] = array( 'label' => 'Hours in the research lab', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 1000, 'group' => 'hours' );
$elsewhere   = track( 'Hours Track', 'hours-track' );
$elsewhere['questions']['Hours']['group'] = 'onboarding';

ck( 'check() refuses a question other than Hours in Total hours, which no student would see',
    array_column( WPCPM_Track_Store::check( $hours_track, $lab ), 'code' ), array( 'hours_only' ) );
ck( 'and Hours in another group, which the page would draw twice',
    array_column( WPCPM_Track_Store::check( $hours_track, $elsewhere ), 'code' ), array( 'hours_group' ) );

WPCPM_Track_Store::save( $hours_track, $lab );
$refused = WPCPM_Track_Store::publish( $hours_track );
ck( 'so Publish refuses it too, and nothing goes live',
    array( $refused instanceof WP_Error ? array_column( $refused->get_error_data()['errors'], 'code' ) : $refused, WPCPM_Track_Store::state( $hours_track ) ),
    array( array( 'hours_only' ), 'draft' ) );

WPCPM_Track_Store::save( $hours_track, track( 'Hours Track', 'hours-track' ) );
WPCPM_Track_Store::publish( $hours_track );
tamper( $hours_track, function ( &$copy ) use ( $lab ) { $copy['questions'] = $lab['questions']; } );
$rows = WPCPM_Track_Store::compile();
ck( 'while a published copy that breaks it still compiles: the rule is not the compile\'s, which would leave a live track out',
    array( isset( $rows['Hours Track'] ), array_key_exists( 'Hours in research lab', get_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . 'hours-track' ) ), isset( get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $hours_track ] ) ),
    array( true, true, false ) );

echo "\n=== publish() puts live the definition it is handed (PUBLISH-LEARN-1) ===\n";

// The publish run hands over the definition its preflight judged and created columns for. A draft
// saved in another tab while the columns were being made is not what goes live: it stays a saved
// change, for the next publish to judge against the base.
$judged_id = WPCPM_Track_Store::create( track( 'Theta Track', 'theta' ) );
$judged    = WPCPM_Track_Store::get( $judged_id );
$later     = track( 'Theta Track', 'theta' );
$later['questions']['Portfolio screenshot'] = array( 'label' => 'Portfolio screenshot', 'type' => 'image', 'group' => 'project' );
WPCPM_Track_Store::save( $judged_id, $later );
$done = WPCPM_Track_Store::publish( $judged_id, 0, $judged );

ck( 'the handed definition is the copy that goes live and compiles, not the draft saved since',
    array( $done, WPCPM_Track_Store::published( $judged_id ), array_keys( (array) get_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . 'theta' ) ) ),
    array( $judged_id, $judged, array_keys( $judged['questions'] ) ) );
ck( 'and the later save is a change not yet published', WPCPM_Track_Store::state( $judged_id ), 'changed' );

// The rules are asked of what goes live: a handed definition the rules refuse is refused, however
// good the saved draft is, and the copy students have stays as it was.
$reserved                        = $judged;
$reserved['questions']['Status'] = array( 'label' => 'Your status', 'type' => 'text', 'group' => 'project' );
WPCPM_Track_Store::save( $judged_id, $judged );
$refused = WPCPM_Track_Store::publish( $judged_id, 0, $reserved );

ck( 'the rules are asked of the handed definition, not of the draft',
    array( $refused instanceof WP_Error ? array_column( $refused->get_error_data()['errors'], 'code' ) : $refused, WPCPM_Track_Store::published( $judged_id ) ),
    array( array( 'column_reserved' ), $judged ) );

echo "\n=== A track's revisions, for History ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['opts']  = array();

$hist = WPCPM_Track_Store::create( track( 'History Track', 'history' ) );
$edit = track( 'History Track', 'history' );
$edit['questions']['Second'] = array( 'label' => 'Second', 'type' => 'text', 'group' => 'project' );
WPCPM_Track_Store::save( $hist, $edit );
$edit['label'] = 'History Track, renamed';
WPCPM_Track_Store::save( $hist, $edit );
$edit['questions']['Third'] = array( 'label' => 'Third', 'type' => 'text', 'group' => 'wrapup' );
WPCPM_Track_Store::save( $hist, $edit );

$all = WPCPM_Track_Store::revisions( $hist );

ck( 'every save is a revision, newest first, the creation the oldest',
    array_map( function ( $r ) { return array_keys( $r['definition']['questions'] ); }, $all ),
    array(
        array( 'Hours', 'history notes', 'Second', 'Third' ),
        array( 'Hours', 'history notes', 'Second' ),
        array( 'Hours', 'history notes', 'Second' ),
        array( 'Hours', 'history notes' ),
    ) );

ck( 'each carries the definition as it was saved, decoded',
    array( $all[1]['definition']['label'], $all[2]['definition']['label'] ),
    array( 'History Track, renamed', 'History Track' ) );

ck( 'with who saved it and when, the times running backwards',
    array( array_unique( array_column( $all, 'by' ) ), $all[0]['at'] > $all[1]['at'] && $all[1]['at'] > $all[2]['at'] && $all[2]['at'] > $all[3]['at'] ),
    array( array( 7 ), true ) );

ck( 'a limit reads one more than it says, so the oldest shown still has its predecessor',
    array( count( WPCPM_Track_Store::revisions( $hist, 2 ) ), count( WPCPM_Track_Store::revisions( $hist, 3 ) ), count( WPCPM_Track_Store::revisions( $hist, 20 ) ) ),
    array( 3, 4, 4 ) );

ck( 'a limit below one reads as one',
    array( count( WPCPM_Track_Store::revisions( $hist, 0 ) ), count( WPCPM_Track_Store::revisions( $hist, -5 ) ) ), array( 2, 2 ) );

// A revision whose own copy of the definition is gone reads as one that kept none, not as a
// broken one: History says so of it (the T3b Task 2 review).
$raw = $GLOBALS['pmeta'][ $all[2]['id'] ][ WPCPM_Track_Store::META_DEFINITION ];
unset( $GLOBALS['pmeta'][ $all[2]['id'] ][ WPCPM_Track_Store::META_DEFINITION ] );

ck( 'a revision whose meta is absent carries a null definition, its neighbors theirs',
    array_map( function ( $r ) { return null === $r['definition'] ? null : $r['definition']['label']; }, WPCPM_Track_Store::revisions( $hist ) ),
    array( 'History Track, renamed', 'History Track, renamed', null, 'History Track' ) );

$GLOBALS['pmeta'][ $all[2]['id'] ][ WPCPM_Track_Store::META_DEFINITION ] = $raw;

ck( 'a post that is not a track has no revisions to give',
    array( WPCPM_Track_Store::revisions( 987654 ), WPCPM_Track_Store::revisions( $all[0]['id'] ) ),
    array( array(), array() ) );

// The cap History asks for, so it calls the oldest revision it shows the creation only where
// nothing was pruned away beneath it (the final review of T3b).
ck( 'a track answers -1 where nothing caps the revisions this site keeps',
    WPCPM_Track_Store::revisions_cap( $hist ), -1 );

$GLOBALS['revisions_cap'] = 5;

ck( 'and the cap itself where the site sets one',
    WPCPM_Track_Store::revisions_cap( $hist ), 5 );

ck( 'a post that is not a track answers -1 whatever the cap, having no definition to keep copies of',
    array( WPCPM_Track_Store::revisions_cap( 987654 ), WPCPM_Track_Store::revisions_cap( $all[0]['id'] ) ),
    array( -1, -1 ) );

unset( $GLOBALS['revisions_cap'] );

echo "\n=== The four original tracks, locked to their pairs whatever the program map holds ===\n";

/** What a store call answered: the error's code and the sentence a person reads, or the value. */
function answered( $result ) {
	return $result instanceof WP_Error ? array( $result->get_error_code(), $result->get_error_message() ) : $result;
}

// Published, as a fresh site seeds them and the live site holds them. The program map has no rows of
// its own, so it gives the four statuses no key, and a lock read from it would leave all four out of
// every compile, a Settings save included, as holding reserved keys. The lock is
// `WPCPM_Tracks::RESERVED_PAIRS` instead (the design's decision 36).
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['opts']  = array();
WPCPM_Tracks::flush();

WPCPM_Track_Store::seed();

$rows = WPCPM_Track_Store::compile();

ck( 'a compile keeps all four, each on its own key, though nothing but the lock names their keys',
	array( array_map( function ( $row ) { return $row['key']; }, $rows ), get_option( WPCPM_Track_Store::OPT_SKIPPED ) ),
	array( array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ), array() ) );

// The seeds as version 1 of the seeding left a site, drafts never published, which its upgrade then
// publishes: each is locked to its pair by its status alone, and the mark decides nothing.
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['opts']  = array();
WPCPM_Tracks::flush();

$drafts  = version_one_seeds();
$checked = array();

foreach ( $drafts as $key => $post_id ) {
	$checked[ $key ] = array_column( WPCPM_Track_Store::check( $post_id, WPCPM_Track_Store::get( $post_id ) ), 'code' );
}

ck( 'the four seeds as version 1 left them, drafts, each pass every rule Publish asks',
	$checked, array( '150h' => array(), '50h' => array(), 'dev' => array(), 'design' => array() ) );

// Whatever the seeded posts hold, no other track takes one of their statuses under another key, or
// one of their keys under another status (the design's decision 37): a status is refused by name,
// first, and never with "A published track keeps its key", which is untrue of a track never
// published. A second track claiming a whole pair is refused by the original's own post, which
// holds it.
$reach = array();

foreach ( array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) as $original => $original_key ) {
	$reach[ $original ] = array(
		array_column( WPCPM_Track_Store::check( 0, track( $original, 'mine', 'Mine' ) ), 'code' ),
		array_column( WPCPM_Track_Store::check( 0, track( 'Mine Track', $original_key, 'Mine' ) ), 'code' ),
	);
}

ck( 'a new track taking one of their statuses under a key of its own is refused by name, and one taking a key of theirs as reserved',
	$reach,
	array(
		'In Sensei'       => array( array( 'status_reserved', 'status_taken' ), array( 'key_reserved' ) ),
		'In Sensei 50h'   => array( array( 'status_reserved', 'status_taken' ), array( 'key_reserved' ) ),
		'Developer Track' => array( array( 'status_reserved', 'status_taken', 'status_named' ), array( 'key_reserved' ) ),
		'Designer Track'  => array( array( 'status_reserved', 'status_taken', 'status_named' ), array( 'key_reserved' ) ),
	) );

$said = WPCPM_Track_Store::check( 0, track( 'In Sensei', 'mine', 'Mine' ) );
ck( 'and the refusal a person reads first says whose status it is, and the key that track keeps', $said[0]['message'], 'This status belongs to one of the program\'s four original tracks, which keeps the key 150h; a new track needs a status of its own.' );

$published = array();

foreach ( $drafts as $key => $post_id ) {
	$done              = WPCPM_Track_Store::publish( $post_id );
	$published[ $key ] = $done instanceof WP_Error ? $done->get_error_code() : ( WPCPM_Tracks::rows()[ WPCPM_Track_Store::get( $post_id )['status'] ]['key'] ?? null );
}

ck( 'each seeded draft publishes, compiled on its own key', $published, array( '150h' => '150h', '50h' => '50h', 'dev' => 'dev', 'design' => 'design' ) );

// The sentence names the key the original track keeps (the design's decision 37), because it is
// also what a manager reads who types another key on the original track's own page: its lock is
// its own published pair, and "A published track keeps its key" is not said beside it.
$retyped        = WPCPM_Track_Store::get( $drafts['design'] );
$retyped['key'] = 'designer';
$retyped_said   = WPCPM_Track_Store::check( $drafts['design'], $retyped );

ck( 'on an original track\'s own page, another key is refused by name, the sentence naming the key the track keeps',
	array( array_column( $retyped_said, 'code' ), $retyped_said[0]['message'] ),
	array( array( 'status_reserved' ), 'This status belongs to one of the program\'s four original tracks, which keeps the key design; a new track needs a status of its own.' ) );

// A never-published draft on each of their pairs, made by code as no screen can make one
// (`create()` checks nothing): the strays the two checks below are asked about.
$strays = array();

foreach ( WPCPM_Track_Store::seeds() as $key => $seed ) {
	$strays[ $key ] = WPCPM_Track_Store::create( $seed );
}

// Never taken off the live site (the design's decision 38): the 150-hour track's form is the one
// every student on no track reads (decision 34), and the other three are the program's base
// statuses. Refused first, before whether the track is published at all, so a stray is refused too.
$own_live = WPCPM_Track_Store::create( track( 'Mine Live Track', 'mine-live' ) );
WPCPM_Track_Store::publish( $own_live );
$always = array( 'wpcpm_track_reserved', 'The program\'s original tracks always run: edit the track and publish the change instead.' );
$held   = array();

foreach ( $drafts as $key => $post_id ) {
	$held[ $key ] = array(
		answered( WPCPM_Track_Store::unpublish( $post_id ) ),
		get_post( $post_id )->post_status,
		isset( WPCPM_Tracks::rows()[ WPCPM_Track_Store::get( $post_id )['status'] ] ),
		answered( WPCPM_Track_Store::unpublish( $strays[ $key ] ) ),
	);
}

ck( 'unpublish() refuses each of the four, published or a draft, and leaves it live, while a track of somebody\'s own comes off',
	array( $held, answered( WPCPM_Track_Store::unpublish( $own_live ) ), WPCPM_Track_Store::state( $own_live ) ),
	array( array_fill_keys( array_keys( $drafts ), array( $always, 'publish', true, $always ) ), $own_live, 'draft' ) );

// Kept once published (the design's decision 38), and once taken off by hand as well, each with a
// sentence of its own in place of decision 25's, which offers an unpublish these tracks do not
// have. A stray on their pair was never published, created no column and holds no student, so it
// is deleted like any draft: left in place it holds up the original track's Save and Publish,
// since a draft holds its status, key and name (TRACKS-1), and nothing else can remove it.
$kept_for = array( 'wpcpm_track_reserved', 'The program\'s original tracks are kept: this one is the record of a form the program runs.' );
$deleted  = array();
$expected = array();

foreach ( $drafts as $key => $post_id ) {
	$before = array_column( WPCPM_Track_Store::check( $post_id, WPCPM_Track_Store::get( $post_id ) ), 'code' );
	$live   = answered( WPCPM_Track_Store::delete( $post_id ) );
	$stray  = answered( WPCPM_Track_Store::delete( $strays[ $key ] ) );
	$after  = array_column( WPCPM_Track_Store::check( $post_id, WPCPM_Track_Store::get( $post_id ) ), 'code' );

	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );

	$deleted[ $key ]  = array( $live, $before, $stray, get_post( $strays[ $key ] ), $after, answered( WPCPM_Track_Store::delete( $post_id ) ), null !== get_post( $post_id ) );
	$expected[ $key ] = array( $kept_for, in_array( $key, array( 'dev', 'design' ), true ) ? array( 'status_taken', 'status_named', 'key_taken', 'label_taken' ) : array( 'status_taken', 'key_taken', 'label_taken' ), $strays[ $key ], null, array(), $kept_for, true );
}

ck( 'delete() keeps each of the four once published and once set back to draft by hand, and deletes a never-published stray on its pair, which held up the original track until it went',
	$deleted, $expected );

// A mark keeps nothing on its own: a seed on one of their statuses that was never published is a
// draft like any other, as is one of somebody's own.
$marked_seed = WPCPM_Track_Store::create( WPCPM_Track_Store::seeds()['dev'] );
update_post_meta( $marked_seed, WPCPM_Track_Store::META_SOURCE, 'builtin' );
$own_draft = WPCPM_Track_Store::create( track( 'Mine Track', 'mine' ) );

ck( 'a marked seed never published is deleted, like a draft of somebody\'s own',
	array( answered( WPCPM_Track_Store::delete( $marked_seed ) ), get_post( $marked_seed ), answered( WPCPM_Track_Store::delete( $own_draft ) ), get_post( $own_draft ) ),
	array( $marked_seed, null, $own_draft, null ) );

echo "\n=== The switch, the refresh and the comparison with the hand-written forms are gone ===\n";

$gone = array();

foreach ( array( 'switch_to_definition', 'switch_to_builtin', 'refresh_builtins', 'refresh_builtin', 'equivalence', 'php_differences', 'definition_differences', 'not_equivalent', 'source' ) as $method ) {
	$gone[ $method ] = method_exists( 'WPCPM_Track_Store', $method );
}

ck( 'the switch both ways, the refresh, every comparison with the hand-written forms and the answer to which side of the switch a track is on are all removed', $gone, array_fill_keys( array_keys( $gone ), false ) );
ck( 'and so is the list of the built-in keys, while the mark\'s name and the migration\'s record stay',
	array( defined( 'WPCPM_Track_Store::BUILTIN_KEYS' ), defined( 'WPCPM_Track_Store::META_SOURCE' ), defined( 'WPCPM_Track_Store::META_SWITCHED' ), method_exists( 'WPCPM_Track_Store', 'switched' ) ),
	array( false, true, true, true ) );

// A site that never switched holds posts marked `builtin` until its upgrade clears them, and the
// mark decides nothing: such a track saves, publishes, adds its status and compiles like any other.
fresh_site();

ck( 'the seeds are the four original tracks\', one file each, in the program\'s order', array_keys( WPCPM_Track_Store::seeds() ), array( '150h', '50h', 'dev', 'design' ) );

$from_before = version_one_seeds();

ck( 'a track still marked from before is saved like any other', answered( WPCPM_Track_Store::save( $from_before['dev'], WPCPM_Track_Store::get( $from_before['dev'] ) ) ), $from_before['dev'] );
ck( 'publishes, and its status joins "Currently mentoring", as the status of every published track does: it is what the track\'s students carry',
	array( answered( WPCPM_Track_Store::publish( $from_before['dev'] ) ), WPCPM_Settings::$added ),
	array( $from_before['dev'], array( 'Developer Track' ) ) );
ck( 'and compiles into a row that says nothing of where it runs from',
	array( array_key_exists( 'source', WPCPM_Tracks::rows()['Developer Track'] ), WPCPM_Tracks::rows()['Developer Track']['key'] ),
	array( false, 'dev' ) );

echo "\n=== A site seeded by version 1 upgrades once ===\n";

// Version 1 of the seeding made each seed a draft marked `builtin`, which its hand-written form ran
// until somebody published it and switched it to its definition. The forms are gone, so a site still
// stamped 1 publishes each seed it holds as a draft, from the definition the draft holds, as seed()
// publishes a seed, clears the mark from every track post and runs seed(), once, under a claim of its
// own, on the first request that reaches `maybe_seed()`.
fresh_site();

$old = version_one_seeds();

// No marked draft could be saved, so each holds the seed it was made from; this one is made to hold
// something else, to show that the upgrade publishes what the draft holds and reads no seed file.
$held_150h          = WPCPM_Track_Store::get( $old['150h'] );
$held_150h['label'] = 'WordPress Credits Program 150h, as held';
update_post_meta( $old['150h'], WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $held_150h ) ) );

// Somebody ticked the 50-hour draft's welcome item before the upgrade, which keeps its name and time.
update_post_meta( $old['50h'], WPCPM_Track_Publish::META_CHECKLIST, array( 'welcome' => array( 'by' => 42, 'at' => 1788000000 ) ) );

// Somebody published the Developer Track and never switched it, so its hand-written form still ran
// it, and somebody put the Designer Track's draft in the trash by hand, which the plugin never does.
WPCPM_Track_Store::publish( $old['dev'], 42 );
$dev_copy = WPCPM_Track_Store::published( $old['dev'] );
$dev_log  = WPCPM_Track_Store::log_entries( $old['dev'] );
wp_update_post( array( 'ID' => $old['design'], 'post_status' => 'trash' ) );

// And a draft of somebody's own, never marked, which the upgrade has no reason to publish.
$own_draft = WPCPM_Track_Store::create( track( 'Growth Track', 'growth' ) );

update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
$GLOBALS['written'] = array();
$GLOBALS['adds']    = array();
$start              = time();
WPCPM_Track_Store::maybe_seed();

$upgraded = array();

foreach ( array( '150h', '50h' ) as $key ) {
	$upgraded[ $key ] = array(
		WPCPM_Track_Store::state( $old[ $key ] ),
		WPCPM_Track_Store::published( $old[ $key ] ) === WPCPM_Track_Store::get( $old[ $key ] ),
		get_post_meta( $old[ $key ], WPCPM_Track_Store::META_AUTOMATION, true ),
	);
}

ck( 'each seed still a draft is published from the definition it holds, as seed() publishes a seed',
	array( $upgraded, WPCPM_Track_Store::published( $old['150h'] )['label'] ?? null ),
	array( array_fill_keys( array( '150h', '50h' ), array( 'published', true, '1' ) ), 'WordPress Credits Program 150h, as held' ) );
ck( 'logged as published by the site itself, then each item not yet ticked ticked in nobody\'s name, every line naming the upgrade, and a person\'s tick keeps its name and time',
	array( log_lines( $old['150h'] ), ticks_read( $old['150h'], $start ), log_lines( $old['50h'] ), ticks_read( $old['50h'], $start ) ),
	array(
		site_lines( 'upgrade' ),
		array( 'automation' => array( true, 0, 'now' ), 'welcome' => array( true, 0, 'now' ), 'choices' => array( true, 0, 'now' ) ),
		site_lines( 'upgrade', array( 'automation', 'choices' ) ),
		array( 'automation' => array( true, 0, 'now' ), 'welcome' => array( true, 42, 1788000000 ), 'choices' => array( true, 0, 'now' ) ),
	) );
ck( 'a seed somebody put in the trash stays there, with no copy, no flag, no tick and no log line, as publish() refuses to publish out of the trash',
	array( WPCPM_Track_Store::state( $old['design'] ), get_post_meta( $old['design'], WPCPM_Track_Store::META_PUBLISHED, true ), get_post_meta( $old['design'], WPCPM_Track_Store::META_AUTOMATION, true ), get_post_meta( $old['design'], WPCPM_Track_Publish::META_CHECKLIST, true ), WPCPM_Track_Store::log_entries( $old['design'] ) ),
	array( 'trash', '', '', '', array() ) );
ck( 'the one somebody published keeps the copy and the log it had, a draft never marked stays a draft, and the stamp moves to 2',
	array( WPCPM_Track_Store::published( $old['dev'] ) === $dev_copy, WPCPM_Track_Store::log_entries( $old['dev'] ) === $dev_log, WPCPM_Track_Store::state( $own_draft ), WPCPM_Track_Store::log_entries( $own_draft ), get_option( WPCPM_Track_Store::OPT_SEEDED ) ),
	array( true, true, 'draft', array(), 2 ) );

$marks = array();

foreach ( WPCPM_Track_Store::all_ids() as $post_id ) {
	$marks[ $post_id ] = get_post_meta( $post_id, WPCPM_Track_Store::META_SOURCE, true );
}

ck( 'the mark leaves every track post, published, a draft or in the trash', array( count( $marks ), array_filter( $marks ) ), array( 5, array() ) );
ck( 'then seed(), with the four statuses held, creates nothing and compiles once: three live on their own keys, and the Designer Track, in the trash, out',
	array( compiles(), array_map( function ( $row ) { return $row['key']; }, WPCPM_Tracks::rows() ), get_option( WPCPM_Track_Store::OPT_SKIPPED ), track_count() ),
	array( 1, array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev' ), array(), 5 ) );
ck( 'all of it under a claim of its own, taken with add_option() and not autoloaded, let go once the stamp, the last thing written, is in',
	array( defined( 'WPCPM_Track_Store::OPT_UPGRADE_LOCK' ) ? constant( 'WPCPM_Track_Store::OPT_UPGRADE_LOCK' ) : null, defined( 'WPCPM_Track_Store::UPGRADE_LOCK_TIMEOUT' ) ? constant( 'WPCPM_Track_Store::UPGRADE_LOCK_TIMEOUT' ) : null, $GLOBALS['adds'], end( $GLOBALS['written'] ), get_option( $upgrade_lock, 'gone' ) ),
	array( $upgrade_lock, $upgrade_timeout, array( array( $upgrade_lock, false ) ), WPCPM_Track_Store::OPT_SEEDED, 'gone' ) );

// Once: a request that finds this version's stamp does nothing, whatever the site holds, and never
// touches the claim, which is not autoloaded, so reading or adding it would cost a query on every
// request.
$late = WPCPM_Track_Store::create( track( 'Late Track', 'late' ) );
update_post_meta( $late, WPCPM_Track_Store::META_SOURCE, 'builtin' );
$GLOBALS['written'] = array();
$GLOBALS['read']    = array();
$GLOBALS['adds']    = array();
WPCPM_Track_Store::maybe_seed();

ck( 'and it runs once: at this version\'s stamp, 2, nothing is published, cleared or compiled, a mark planted since included, and the claim is neither read nor taken',
	array( WPCPM_Track_Store::SEED_VERSION, WPCPM_Track_Store::state( $late ), get_post_meta( $late, WPCPM_Track_Store::META_SOURCE, true ), $GLOBALS['written'], in_array( $upgrade_lock, $GLOBALS['read'], true ), $GLOBALS['adds'] ),
	array( 2, 'draft', 'builtin', array(), false, array() ) );

// The claim. Another request took it a moment ago, and is still upgrading: this one does nothing, and
// the site runs on the rows it holds meanwhile.
fresh_site();
$claimed = version_one_seeds();
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
$GLOBALS['opts'][ $upgrade_lock ] = time();
$claim_then                       = $GLOBALS['opts'][ $upgrade_lock ];
$GLOBALS['written']               = array();
WPCPM_Track_Store::maybe_seed();

ck( 'a claim another request took a moment ago holds: nothing is published, cleared or compiled, and the stamp and the claim stay as they were',
	array( WPCPM_Track_Store::state( $claimed['150h'] ), get_post_meta( $claimed['150h'], WPCPM_Track_Store::META_SOURCE, true ), $GLOBALS['written'], get_option( WPCPM_Track_Store::OPT_SEEDED ), get_option( $upgrade_lock ) ),
	array( 'draft', 'builtin', array(), 1, $claim_then ) );

// The request that held it died: a claim older than the timeout is taken over, so a fatal inside the
// upgrade costs one request each timeout rather than every request.
$GLOBALS['opts'][ $upgrade_lock ] = time() - $upgrade_timeout - 1;
WPCPM_Track_Store::maybe_seed();

ck( 'a claim older than the timeout is a dead request\'s, and is taken over: the upgrade runs, the stamp moves to 2 and the claim goes',
	array( WPCPM_Track_Store::state( $claimed['150h'] ), get_post_meta( $claimed['150h'], WPCPM_Track_Store::META_SOURCE, true ), get_option( WPCPM_Track_Store::OPT_SEEDED ), get_option( $upgrade_lock, 'gone' ) ),
	array( 'published', '', 2, 'gone' ) );

// A request that died inside the upgrade, after writing a seed's copy and before publishing its post:
// the draft is asked by its state, not by its copy, so the next request finishes it.
fresh_site();
$halfway = version_one_seeds();
half_published( array( $halfway['50h'] ) );
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
WPCPM_Track_Store::maybe_seed();

ck( 'a seed a dead request left with its copy written and its post still a draft is published by the next, with its flag, its ticks and its lines',
	array( WPCPM_Track_Store::state( $halfway['50h'] ), get_post_meta( $halfway['50h'], WPCPM_Track_Store::META_AUTOMATION, true ), log_lines( $halfway['50h'] ), isset( WPCPM_Tracks::rows()['In Sensei 50h'] ) ),
	array( 'published', '1', site_lines( 'upgrade' ), true ) );

// And one WordPress refuses to publish keeps the copy it held, as `publish()` puts back the copy a
// track was published with, rather than losing it.
fresh_site();
$refusing = version_one_seeds();
half_published( array( $refusing['dev'] ) );
$held_copy = get_post_meta( $refusing['dev'], WPCPM_Track_Store::META_PUBLISHED, true );
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
$GLOBALS['db_refuses'] = 'refuse_developer_publish';
WPCPM_Track_Store::maybe_seed();
unset( $GLOBALS['db_refuses'] );

ck( 'a half-published seed WordPress refuses to publish is left a draft holding the copy it held, with no flag, no tick and no line',
	array( WPCPM_Track_Store::state( $refusing['dev'] ), get_post_meta( $refusing['dev'], WPCPM_Track_Store::META_PUBLISHED, true ) === $held_copy, get_post_meta( $refusing['dev'], WPCPM_Track_Store::META_AUTOMATION, true ), get_post_meta( $refusing['dev'], WPCPM_Track_Publish::META_CHECKLIST, true ), WPCPM_Track_Store::log_entries( $refusing['dev'] ) ),
	array( 'draft', true, '', '', array() ) );

// A site stamped 1 that lost one of its four seed posts, which a seeding that died partway or a post
// deleted outside the plugin leaves: the hand-written form ran that track until now, so the upgrade's
// seed() creates it and publishes it.
fresh_site();
$lost = version_one_seeds();
wp_delete_post( $lost['50h'], true );
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
WPCPM_Track_Store::maybe_seed();

$found = 0;

foreach ( WPCPM_Track_Store::all_ids() as $post_id ) {
	if ( 'In Sensei 50h' === WPCPM_Track_Store::get( $post_id )['status'] ) {
		$found = (int) $post_id;
	}
}

ck( 'a seed the site lost is seeded again, published as seed() publishes one, and the four run',
	array( WPCPM_Track_Store::state( $found ), log_lines( $found ), array_keys( WPCPM_Tracks::rows() ), compiles(), get_option( WPCPM_Track_Store::OPT_SEEDED ) ),
	array( 'published', site_lines( 'seed' ), array( 'In Sensei', 'Developer Track', 'Designer Track', 'In Sensei 50h' ), 1, 2 ) );

// A marked draft whose definition reads but breaks a rule: the upgrade asks no rule, as seed() asks
// none, and the compile leaves the copy out and records why, for the track list to show.
fresh_site();
$clashing           = version_one_seeds();
$clash_50h          = WPCPM_Track_Store::get( $clashing['50h'] );
$clash_50h['label'] = 'WordPress Credits Program 150h';
update_post_meta( $clashing['50h'], WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $clash_50h ) ) );
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
WPCPM_Track_Store::maybe_seed();

ck( 'a seed whose definition breaks a rule is published all the same, and the compile leaves it out and says why',
	array( WPCPM_Track_Store::state( $clashing['50h'] ), get_option( WPCPM_Track_Store::OPT_SKIPPED ), array_keys( WPCPM_Tracks::rows() ) ),
	array( 'published', array( $clashing['50h'] => array( 'label_taken' ) ), array( 'In Sensei', 'Developer Track', 'Designer Track' ) ) );

// A marked draft whose definition cannot be read has nothing to publish, and one WordPress refuses to
// publish is left a draft with no copy, as `publish()` leaves one. Neither stops the rest, and seed()
// then seeds the 50-hour track again beside the unreadable post, which holds no status.
fresh_site();
$broken = version_one_seeds();
update_post_meta( $broken['50h'], WPCPM_Track_Store::META_DEFINITION, '{not json' );
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
$GLOBALS['db_refuses'] = 'refuse_developer_publish';
WPCPM_Track_Store::maybe_seed();
unset( $GLOBALS['db_refuses'] );

$left = array();

foreach ( $broken as $key => $post_id ) {
	$left[ $key ] = array( WPCPM_Track_Store::state( $post_id ), '' !== get_post_meta( $post_id, WPCPM_Track_Store::META_PUBLISHED, true ), get_post_meta( $post_id, WPCPM_Track_Store::META_SOURCE, true ), '' !== get_post_meta( $post_id, WPCPM_Track_Publish::META_CHECKLIST, true ), count( WPCPM_Track_Store::log_entries( $post_id ) ) );
}

ck( 'a seed whose definition cannot be read, and one WordPress refuses to publish, stay drafts with no copy, no tick and no log line, their marks gone with the rest, the others publish, and the 50-hour track is seeded again',
	array( $left, array_keys( WPCPM_Tracks::rows() ), track_count(), get_option( WPCPM_Track_Store::OPT_SEEDED ) ),
	array( array( '150h' => array( 'published', true, '', true, 4 ), '50h' => array( 'draft', false, '', false, 0 ), 'dev' => array( 'draft', false, '', false, 0 ), 'design' => array( 'published', true, '', true, 4 ) ), array( 'In Sensei', 'Designer Track', 'In Sensei 50h' ), 5, 2 ) );

echo "\n=== The live site's four upgrade to the rows they hold ===\n";

// As the live site holds them since 22 September 2026: seeded by version 1, published and their three
// checklist items ticked by one person in one go, switched to their definitions (the fingerprint kept
// and the mark taken off, which is what the switch wrote), beside a track of somebody's own.
fresh_site();
$live = version_one_seeds();

foreach ( $live as $post_id ) {
	WPCPM_Track_Store::publish( $post_id, 42 );
	update_post_meta( $post_id, WPCPM_Track_Store::META_AUTOMATION, '1' );
	update_post_meta( $post_id, WPCPM_Track_Publish::META_CHECKLIST, array_fill_keys( WPCPM_Track_Publish::CHECKLIST, array( 'by' => 42, 'at' => 1790113170 ) ) );

	foreach ( WPCPM_Track_Publish::CHECKLIST as $item ) {
		WPCPM_Track_Store::log( $post_id, 'tick-' . $item, 42 );
	}

	update_post_meta( $post_id, WPCPM_Track_Store::META_SWITCHED, md5( (string) get_post_meta( $post_id, WPCPM_Track_Store::META_DEFINITION, true ) ) );
	delete_post_meta( $post_id, WPCPM_Track_Store::META_SOURCE );
	WPCPM_Track_Store::log( $post_id, 'switch_definition', 42 );
}

$growth = WPCPM_Track_Store::create( track( 'Growth Track', 'growth' ) );
WPCPM_Track_Store::publish( $growth, 42 );
WPCPM_Track_Store::compile();

$before = snapshot();
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
$GLOBALS['written'] = array();
WPCPM_Track_Store::maybe_seed();

ck( 'four published, ticked and switched with no mark, their rows current, recompile once to the very rows they hold, nothing else on the site moves, and the claim goes',
	array( compiles(), snapshot() === $before, get_option( WPCPM_Track_Store::OPT_SEEDED ), get_option( $upgrade_lock, 'gone' ) ),
	array( 1, true, 2, 'gone' ) );

// The rows 1.110.4 wrote said where each track ran from, which a row no longer says.
$as_written = $before['index'];

foreach ( $as_written as $status => $row ) {
	$as_written[ $status ]['source'] = 'definition';
}

update_option( WPCPM_Tracks::OPT_TRACKS, $as_written, true );
update_option( WPCPM_Track_Store::OPT_SEEDED, 1, true );
WPCPM_Tracks::flush();
WPCPM_Track_Store::maybe_seed();

ck( 'and rows as 1.110.4 wrote them, each naming where its track ran from, recompile to the same rows without it',
	array( get_option( WPCPM_Tracks::OPT_TRACKS ) === $before['index'], array_keys( WPCPM_Tracks::rows() ) ),
	array( true, array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Growth Track' ) ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
