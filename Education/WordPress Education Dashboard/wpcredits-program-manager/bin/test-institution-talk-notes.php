<?php
/**
 * The talks notes (Institutions off Airtable, release 2): dated, signed notes an Administrator adds to a
 * school's talks, kept on the site as private posts, never edited, only added.
 *
 * What is pinned here and why:
 *
 * - **The type is invisible everywhere.** Not public, not queryable, no UI, no REST route, no archive, no
 *   rewrite, no query variable, a capability type nobody is granted, and never deleted with a user: a note
 *   names the people a school talks with. Registered on `init`, under WordPress's twenty-character limit.
 * - **`add()` keeps what it is given.** The text, the key (a record ID or a lead key), the author's account
 *   or the name an import carries, the date given (UTC) or now, and the source. It refuses an empty text,
 *   one over 5000 characters, a key that is neither shape, a date that is not one, and a note nobody wrote.
 * - **`for_key()` reads newest first,** each note as `id`, `text`, `date` and `author`.
 * - **`has_source()`** tells an import whether a note it would write is already there.
 * - **`rekey()`** moves every note of a key to another, trashed ones too, and says how many.
 * - **The words survive the round trip,** in every script: Polish, Romanian, Arabic and Japanese, a quote
 *   and a backslash, compared byte for byte after a read back.
 * - **`delete_all()`** removes every note in any status and says how many.
 * - **A note is never updated,** and never inserted as published.
 *
 * Run from the plugin root:  php bin/test-institution-talk-notes.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['post_types'] = array();
$GLOBALS['hooks']      = array();
$GLOBALS['users']      = array();
$GLOBALS['clock']      = '2026-11-19 10:00:00';

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class WP_Post {
	public $ID = 0;
	public $post_type = 'post';
	public $post_status = 'draft';
	public $post_title = '';
	public $post_name = '';
	public $post_author = 0;
	public $post_content = '';
	public $post_date = '';
	public $post_date_gmt = '';
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = array( $hook, $callback );
}

function register_post_type( $type, $args ) {
	$GLOBALS['post_types'][ $type ] = $args;
}

function update_meta_cache( $type, $ids ) {}

function current_time( $type, $gmt = false ) {
	return $GLOBALS['clock'];
}

// The site's zone, two hours ahead, so a check sees the local column is not the UTC one.
function get_date_from_gmt( $date, $format = 'Y-m-d H:i:s' ) {
	return gmdate( $format, strtotime( $date . ' UTC' ) + 2 * 3600 );
}

// Core unslashes the array it is handed; this one keeps the dates and the content, which the shared
// stand-in does not.
/**
 * A write that is killed: each insert, meta write and update ticks one, and once more ticks than
 * `$GLOBALS['kill_after']` allows are asked for, the request dies before that write lands.
 */
class Killed extends Exception {}

function wpcpm_test_tick() {
	$GLOBALS['ticks'] = isset( $GLOBALS['ticks'] ) ? $GLOBALS['ticks'] + 1 : 1;

	if ( isset( $GLOBALS['kill_after'] ) && null !== $GLOBALS['kill_after'] && $GLOBALS['ticks'] > $GLOBALS['kill_after'] ) {
		$GLOBALS['kill_after'] = null;
		throw new Killed( 'killed' );
	}
}

function update_post_meta( $id, $key, $value ) {
	wpcpm_test_tick();
	// A write the database refuses, by the meta key (the outside review, O7).
	if ( ( $GLOBALS['meta_fails_key'] ?? '' ) === $key ) {
		return false;
	}
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );
	return true;
}

function wp_update_post( $postarr, $wp_error = false ) {
	wpcpm_test_tick();
	$postarr = wp_unslash( $postarr );
	$id      = (int) ( isset( $postarr['ID'] ) ? $postarr['ID'] : 0 );

	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post' ) : 0;
	}

	$GLOBALS['updated'][] = $postarr;

	foreach ( array( 'post_title', 'post_status', 'post_date', 'post_date_gmt' ) as $field ) {
		if ( isset( $postarr[ $field ] ) ) {
			$GLOBALS['posts'][ $id ]->$field = $postarr[ $field ];
		}
	}

	return $id;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	wpcpm_test_tick();
	$postarr                  = wp_unslash( $postarr );
	$GLOBALS['posts_next_id'] = isset( $GLOBALS['posts_next_id'] ) ? $GLOBALS['posts_next_id'] : 100;
	$id                       = $GLOBALS['posts_next_id']++;
	$post                     = new WP_Post();

	$GLOBALS['inserted'][] = $postarr;

	foreach ( array( 'post_type', 'post_status', 'post_title', 'post_content', 'post_date', 'post_date_gmt' ) as $field ) {
		if ( isset( $postarr[ $field ] ) ) {
			$post->$field = $postarr[ $field ];
		}
	}

	$post->ID          = $id;
	$post->post_author = isset( $postarr['post_author'] ) ? (int) $postarr['post_author'] : 0;

	$GLOBALS['posts'][ $id ] = $post;

	return $id;
}

require_once __DIR__ . '/stubs/posts.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talk-notes.php';

$total = 0;
$fails = 0;

function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;

	if ( $actual === $expected ) {
		echo "  ok   {$label}\n";
		return;
	}

	++$fails;
	echo "  FAIL {$label}\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

$author = new stdClass();

$author->ID           = 7;
$author->display_name = 'Example Administrator';

$GLOBALS['users'][7] = $author;

$school = 'recEXA00000000001';
$other  = 'recEXA00000000002';
$lead   = 'lead-5';

echo "=== The type is invisible everywhere ===\n";

WPCPM_Institution_Talk_Notes::init();
ck( 'init() registers the type on init', $GLOBALS['hooks'], array( array( 'init', array( 'WPCPM_Institution_Talk_Notes', 'register_post_type' ) ) ) );

WPCPM_Institution_Talk_Notes::register_post_type();
$args = isset( $GLOBALS['post_types']['wpcpm_inst_note'] ) ? $GLOBALS['post_types']['wpcpm_inst_note'] : array();
ck( 'the constants', array( WPCPM_Institution_Talk_Notes::POST_TYPE, WPCPM_Institution_Talk_Notes::META_FOR, WPCPM_Institution_Talk_Notes::META_AUTHOR, WPCPM_Institution_Talk_Notes::META_SOURCE, WPCPM_Institution_Talk_Notes::MAX_LENGTH ), array( 'wpcpm_inst_note', '_wpcpm_inote_for', '_wpcpm_inote_author', '_wpcpm_inote_source', 5000 ) );
ck( 'the type name fits WordPress\'s twenty characters', strlen( WPCPM_Institution_Talk_Notes::POST_TYPE ) <= 20, true );
ck( 'notes_not_exported: the notes stay out of WordPress\'s export file and out of the menus\' screen, as the leads do', array( array_key_exists( 'can_export', $args ) ? $args['can_export'] : null, array_key_exists( 'show_in_nav_menus', $args ) ? $args['show_in_nav_menus'] : null ), array( false, false ) );
ck(
	'not public, not queryable, no UI, no REST, no archive, no rewrite, no query variable, not deleted with a user',
	array(
		$args['public'] ?? null,
		$args['publicly_queryable'] ?? null,
		$args['exclude_from_search'] ?? null,
		$args['show_ui'] ?? null,
		$args['show_in_menu'] ?? null,
		$args['show_in_rest'] ?? null,
		$args['has_archive'] ?? null,
		$args['rewrite'] ?? null,
		$args['query_var'] ?? null,
		$args['delete_with_user'] ?? null,
	),
	array( false, false, true, false, false, false, false, false, false, false )
);
ck( 'a capability type nobody is granted', $args['capability_type'] ?? null, array( 'wpcpm_inst_note', 'wpcpm_inst_notes' ) );

echo "\n=== add() keeps what it is given ===\n";

$first = WPCPM_Institution_Talk_Notes::add( $school, 'Sent the deck to the dean.', 7, array( 'date' => '2026-11-02 09:15:00' ) );
ck( 'a note by an Administrator is a post ID', is_int( $first ) && $first > 0, true );
$post = get_post( $first );
ck( 'a private post of the type, titled by its key', array( $post->post_type, $post->post_status, $post->post_title ), array( 'wpcpm_inst_note', 'private', $school ) );
ck( 'its date is the one given, UTC, and the site\'s own beside it', array( $post->post_date_gmt, $post->post_date ), array( '2026-11-02 09:15:00', '2026-11-02 11:15:00' ) );
ck( 'its key and its author\'s account are kept', array( get_post_meta( $first, '_wpcpm_inote_for', true ), (int) get_post_meta( $first, '_wpcpm_inote_author', true ) ), array( $school, 7 ) );
ck( 'and the post names nobody as its author, so deleting an account never takes a note with it', $post->post_author, 0 );

$imported = WPCPM_Institution_Talk_Notes::add(
	$school,
	'Imported from Airtable: interested in the spring cohort.',
	0,
	array(
		'author_name' => 'A former Administrator',
		'date'        => '2026-10-01 08:00:00',
		'source'      => 'airtable-notes:' . $school,
	)
);
ck( 'an imported note signed with a name and no account is kept', is_int( $imported ) && $imported > 0, true );
ck( 'with its source', get_post_meta( $imported, '_wpcpm_inote_source', true ), 'airtable-notes:' . $school );

$now = WPCPM_Institution_Talk_Notes::add( $school, 'Call booked for Monday.', 7 );
ck( 'a note given no date is dated now, UTC', get_post( $now )->post_date_gmt, '2026-11-19 10:00:00' );

$same_time = WPCPM_Institution_Talk_Notes::add( $school, 'And the follow-up sent.', 7 );

$elsewhere = WPCPM_Institution_Talk_Notes::add( $other, 'Another school\'s note.', 7, array( 'date' => '2026-11-10 12:00:00' ) );
$for_lead  = WPCPM_Institution_Talk_Notes::add( $lead, 'Met at a WordCamp.', 7, array( 'date' => '2026-11-11 12:00:00' ) );
ck( 'a lead\'s key is a key', is_int( $for_lead ) && $for_lead > 0, true );

echo "\n=== add() refuses ===\n";

$before = count( $GLOBALS['posts'] );
$refused = array(
	'an empty text'                  => WPCPM_Institution_Talk_Notes::add( $school, '', 7 ),
	'a text of only space'           => WPCPM_Institution_Talk_Notes::add( $school, "  \n\t ", 7 ),
	'a text over 5000 characters'    => WPCPM_Institution_Talk_Notes::add( $school, str_repeat( 'a', 5001 ), 7 ),
	'5001 characters of two bytes'   => WPCPM_Institution_Talk_Notes::add( $school, str_repeat( 'Ł', 5001 ), 7 ),
	'a text that is not UTF-8'       => WPCPM_Institution_Talk_Notes::add( $school, "Bad \xC3\x28 bytes", 7 ),
	'a text that is not a string'    => WPCPM_Institution_Talk_Notes::add( $school, array( 'x' ), 7 ),
	'a key that is not a record ID'  => WPCPM_Institution_Talk_Notes::add( 'recSHORT', 'Text', 7 ),
	'a lead key of another shape'    => WPCPM_Institution_Talk_Notes::add( 'lead-0', 'Text', 7 ),
	'a lead key with letters'        => WPCPM_Institution_Talk_Notes::add( 'lead-5a', 'Text', 7 ),
	'a date that is no date'         => WPCPM_Institution_Talk_Notes::add( $school, 'Text', 7, array( 'date' => '2026-02-30 10:00:00' ) ),
	'a date in another shape'        => WPCPM_Institution_Talk_Notes::add( $school, 'Text', 7, array( 'date' => '2026-11-02' ) ),
	'a note nobody wrote'            => WPCPM_Institution_Talk_Notes::add( $school, 'Text', 0 ),
	'a note by an account not there' => WPCPM_Institution_Talk_Notes::add( $school, 'Text', 99 ),
);

foreach ( $refused as $why => $result ) {
	ck( 'refused: ' . $why, is_wp_error( $result ), true );
}

ck( 'and nothing refused was written', count( $GLOBALS['posts'] ), $before );
ck( 'exactly 5000 characters of two bytes are kept', is_int( WPCPM_Institution_Talk_Notes::add( $other, str_repeat( 'Ł', 5000 ), 7, array( 'date' => '2026-01-01 00:00:00' ) ) ), true );
ck( 'the refusal of a long text says the limit', $refused['a text over 5000 characters']->get_error_message(), 'A note can be at most 5000 characters long.' );

// The length and the UTF-8 test share one guard: without mbstring both take the PCRE way, so a
// site without the extension measures the note it just found well formed, and never stops on mb_strlen().
$multibyte = new ReflectionProperty( 'WPCPM_Institution_Talk_Notes', 'multibyte' );
$multibyte->setAccessible( true );
$multibyte->setValue( null, false );
$no_mb_before = count( $GLOBALS['posts'] );
ck( 'without mbstring the same guard measures: 5000 characters of two bytes kept, 5001 refused as too long, bad bytes refused as empty', array(
	is_int( $no_mb_kept = WPCPM_Institution_Talk_Notes::add( 'recEXA00000000009', str_repeat( 'Ł', 5000 ), 7, array( 'date' => '2026-01-01 00:00:01' ) ) ),
	( static function ( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : 'kept'; } )( WPCPM_Institution_Talk_Notes::add( 'recEXA00000000009', str_repeat( 'Ł', 5001 ), 7 ) ),
	( static function ( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : 'kept'; } )( WPCPM_Institution_Talk_Notes::add( 'recEXA00000000009', "Bad \xC3\x28 bytes", 7 ) ),
	count( $GLOBALS['posts'] ) - $no_mb_before,
), array( true, 'wpcpm_note_long', 'wpcpm_note_empty', 1 ) );
$multibyte->setValue( null, null );
unset( $GLOBALS['posts'][ $no_mb_kept ] );

echo "\n=== for_key() reads newest first ===\n";

$notes = WPCPM_Institution_Talk_Notes::for_key( $school );
ck( 'every note of the key, newest first, and of two at the same time the later written first', note_ids( $notes ), array( $same_time, $now, $first, $imported ) );
ck( 'each note as id, text, date and author', $notes[2], array( 'id' => $first, 'text' => 'Sent the deck to the dean.', 'date' => '2026-11-02 09:15:00', 'author' => 'Example Administrator' ) );
ck( 'an imported note is signed with the name it came with', $notes[3]['author'], 'A former Administrator' );
ck( 'a lead\'s notes are its own', note_ids( WPCPM_Institution_Talk_Notes::for_key( $lead ) ), array( $for_lead ) );
ck( 'a key that is neither shape has none', WPCPM_Institution_Talk_Notes::for_key( 'anything' ), array() );

$GLOBALS['posts'][ $imported ]->post_status = 'trash';
ck( 'a note another process trashed is not read', note_ids( WPCPM_Institution_Talk_Notes::for_key( $school ) ), array( $same_time, $now, $first ) );

echo "\n=== has_source() ===\n";

ck( 'a source written is found, trashed or not', WPCPM_Institution_Talk_Notes::has_source( 'airtable-notes:' . $school ), true );
ck( 'one never written is not', WPCPM_Institution_Talk_Notes::has_source( 'airtable-comment:comEXA0000000001' ), false );
ck( 'and an empty source is never found', WPCPM_Institution_Talk_Notes::has_source( '' ), false );

echo "\n=== The words survive the round trip ===\n";

$names = array( 'Wyższa Szkoła Górnołąćka', 'Universitatea Câmpiei Rîușițeni', 'جامعة الواحة الخضراء المتخيلة', '架空山学園大学', 'The "quoted" C:\\path\\ and it\'s' );

foreach ( $names as $i => $name ) {
	$id   = WPCPM_Institution_Talk_Notes::add( 'recEXA0000000001' . ( $i + 3 ), 'Talked with ' . $name, 0, array( 'author_name' => $name ) );
	$back = WPCPM_Institution_Talk_Notes::for_key( 'recEXA0000000001' . ( $i + 3 ) );
	ck( 'byte for byte: ' . bin2hex( substr( $name, 0, 6 ) ), array( bin2hex( $back[0]['text'] ), bin2hex( $back[0]['author'] ) ), array( bin2hex( 'Talked with ' . $name ), bin2hex( $name ) ) );
}

echo "\n=== rekey() ===\n";

$moved = WPCPM_Institution_Talk_Notes::rekey( $lead, $other );
ck( 'every note of a lead moves to the record it became, and none is said left (the outside review, O4: rekey() answers how many it could not move)', $moved, 0 );
ck( 'the record has them with its own', count( WPCPM_Institution_Talk_Notes::for_key( $other ) ), 3 );
ck( 'the lead has none', WPCPM_Institution_Talk_Notes::for_key( $lead ), array() );
ck( 'a trashed note moves too, none left', WPCPM_Institution_Talk_Notes::rekey( $school, 'recEXA00000000009' ), 0 );
ck( 'so the old key holds nothing, trashed or not', array( get_post_meta( $imported, '_wpcpm_inote_for', true ), WPCPM_Institution_Talk_Notes::for_key( $school ) ), array( 'recEXA00000000009', array() ) );
ck( 'nothing moves to a key of neither shape', WPCPM_Institution_Talk_Notes::rekey( $other, 'nowhere' ), 0 );
ck( 'nor from a key to itself', WPCPM_Institution_Talk_Notes::rekey( $other, $other ), 0 );
ck( 'and the record kept them', count( WPCPM_Institution_Talk_Notes::for_key( $other ) ), 3 );

echo "\n=== A note is never updated, never published ===\n";

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talk-notes.php' );
ck( 'the class updates a post only once, to commit it, and inserts none as publish', array( substr_count( $src, 'wp_update_post(' ), 1 === preg_match( "/'post_status'\s*=>\s*'publish'/", $src ) ), array( 1, false ) );

echo "\n=== A note is committed by its status (Task 18 fix round 1) ===\n";

// An imported note's writes: the insert, the key, the text, the author, the name, the source, and the one update
// that moves it to private. A kill after any of the first six leaves an unfinished draft.
$GLOBALS['ticks']      = 0;
$GLOBALS['kill_after'] = null;
$GLOBALS['updated']    = array();
$whole                 = WPCPM_Institution_Talk_Notes::add( 'recKIL00000000000', 'A whole note.', 0, array( 'author_name' => 'Imported', 'source' => 'kill:whole', 'date' => '2026-11-01 08:00:00' ) );
ck( 'seven writes, the last the move to private, with the date kept', array( $GLOBALS['ticks'], get_post( $whole )->post_status, end( $GLOBALS['updated'] )['post_status'], get_post( $whole )->post_date_gmt ), array( 7, 'private', 'private', '2026-11-01 08:00:00' ) );
ck( 'inserted as an unfinished draft first', end( $GLOBALS['inserted'] )['post_status'], 'draft' );

$killed = array();
for ( $at = 1; $at <= 6; $at++ ) {
	$key                   = sprintf( 'recKIL00000000%03d', $at );
	$GLOBALS['ticks']      = 0;
	$GLOBALS['kill_after'] = $at;
	try {
		WPCPM_Institution_Talk_Notes::add( $key, 'Killed note.', 0, array( 'author_name' => 'Imported', 'source' => 'kill:' . $key, 'date' => '2026-11-01 08:00:00' ) );
		$died = false;
	} catch ( Killed $e ) {
		$died = true;
	}
	$GLOBALS['kill_after'] = null;
	$seen                  = array( $died, WPCPM_Institution_Talk_Notes::for_key( $key ), WPCPM_Institution_Talk_Notes::has_source( 'kill:' . $key ) );
	WPCPM_Institution_Talk_Notes::rekey( $key, 'recKIL00000000999' );
	$again    = WPCPM_Institution_Talk_Notes::add( $key, 'Killed note.', 0, array( 'author_name' => 'Imported', 'source' => 'kill:' . $key, 'date' => '2026-11-01 08:00:00' ) );
	$killed[] = array_merge( $seen, array( count( WPCPM_Institution_Talk_Notes::for_key( $key ) ), WPCPM_Institution_Talk_Notes::has_source( 'kill:' . $key ), get_post( $again )->post_status ) );
}
ck(
	'killed after the insert and after each meta: nothing is listed, the source is not found, a rekey moves nothing, and the rerun writes one whole note',
	$killed,
	array_fill( 0, 6, array( true, array(), false, 1, true, 'private' ) )
);
ck( 'the unfinished ones stay drafts nobody reads', count( array_filter( $GLOBALS['posts'], static function ( $p ) { return 'wpcpm_inst_note' === $p->post_type && 'draft' === $p->post_status; } ) ), 6 );
ck( 'and none was moved to another key', WPCPM_Institution_Talk_Notes::for_key( 'recKIL00000000999' ), array() );
ck( 'text_of_source() gives a finished note\'s text, and nothing for an unfinished one or none', array( WPCPM_Institution_Talk_Notes::text_of_source( 'kill:whole' ), WPCPM_Institution_Talk_Notes::text_of_source( 'kill:nowhere' ), WPCPM_Institution_Talk_Notes::text_of_source( '' ) ), array( 'A whole note.', null, null ) );
ck( 'and writes no option', 1 === preg_match( '/\b(update|add)_option\s*\(/', $src ), false );

echo "\n=== O7: a note is shown only when all of it was written ===\n";

$o7_notes = static function () {
	return count( array_filter( $GLOBALS['posts'], static function ( $p ) { return 'wpcpm_inst_note' === $p->post_type; } ) );
};
$o7_refusal = array( 'wpcpm_note_write', 'The note could not be kept. Try again in a while.' );
$o7_said    = static function ( $answer ) {
	return $answer instanceof WP_Error ? array( $answer->get_error_code(), $answer->get_error_message() ) : $answer;
};
foreach ( array( '_wpcpm_inote_for', '_wpcpm_inote_text', '_wpcpm_inote_author' ) as $o7_key ) {
	$o7_before                 = $o7_notes();
	$GLOBALS['meta_fails_key'] = $o7_key;
	$o7_typed                  = WPCPM_Institution_Talk_Notes::add( 'recO7N00000000001', 'A typed note.', 7 );
	$GLOBALS['meta_fails_key'] = '';
	ck( "o7_typed_{$o7_key}: a typed note whose {$o7_key} write is refused is not kept: the refusal, no post left, nothing listed", array( $o7_said( $o7_typed ), $o7_notes() - $o7_before, WPCPM_Institution_Talk_Notes::for_key( 'recO7N00000000001' ) ), array( $o7_refusal, 0, array() ) );
}
foreach ( array( '_wpcpm_inote_text', '_wpcpm_inote_author_name', '_wpcpm_inote_source' ) as $o7_key ) {
	$o7_before                 = $o7_notes();
	$GLOBALS['meta_fails_key'] = $o7_key;
	$o7_imported               = WPCPM_Institution_Talk_Notes::add( 'recO7N00000000002', 'An imported note.', 0, array( 'author_name' => 'Imported', 'source' => 'o7:' . $o7_key, 'date' => '2026-11-01 08:00:00' ) );
	$GLOBALS['meta_fails_key'] = '';
	ck( "o7_imported_{$o7_key}: an imported note whose {$o7_key} write is refused is not kept, and a later import does not count it as brought over", array( $o7_said( $o7_imported ), $o7_notes() - $o7_before, WPCPM_Institution_Talk_Notes::has_source( 'o7:' . $o7_key ) ), array( $o7_refusal, 0, false ) );
}
$GLOBALS['ticks'] = 0;
$o7_order_id      = WPCPM_Institution_Talk_Notes::add( 'recO7N00000000003', 'In order.', 0, array( 'author_name' => 'Imported', 'source' => 'o7:order', 'date' => '2026-11-01 08:00:00' ) );
$o7_meta_keys     = array_keys( $GLOBALS['pmeta'][ $o7_order_id ] );
ck( 'o7_source_after_text: the source marker is written after the text', array_search( '_wpcpm_inote_source', $o7_meta_keys, true ) > array_search( '_wpcpm_inote_text', $o7_meta_keys, true ), true );

echo "\n=== delete_all() ===\n";

$keep = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Not a note' ) );
$all  = count( array_filter( $GLOBALS['posts'], static function ( $p ) { return 'wpcpm_inst_note' === $p->post_type; } ) );
ck( 'every note goes, in any status, and the count is said', WPCPM_Institution_Talk_Notes::delete_all(), $all );
ck( 'none is left', count( array_filter( $GLOBALS['posts'], static function ( $p ) { return 'wpcpm_inst_note' === $p->post_type; } ) ), 0 );
ck( 'a post of another type stays', null !== get_post( $keep ), true );

/**
 * The IDs of notes as `for_key()` gives them.
 *
 * @param array $notes Notes.
 * @return int[]
 */
function note_ids( array $notes ) {
	return array_map(
		static function ( $note ) {
			return $note['id'];
		},
		$notes
	);
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
