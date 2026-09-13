<?php
/**
 * The Track Builder screen (phase T2b): the rows it shows, and the markup it draws them in.
 *
 * The list is the first thing a Program Administrator sees of the Track Builder, and every answer
 * on it comes from somewhere else: the store says what state a track is in and what the last
 * compile left out, the students sync says how many people are on it, and the seeds say whether a
 * built-in draft has fallen behind its PHP. So the collaborators are stood in for here and the
 * screen is held to what it does with their answers.
 *
 * Run from the plugin root:  php bin/test-track-builder.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
// Faithful to core in the one way that matters here: a value is inserted as it is handed and
// never encoded (core says the caller encodes), so a handler that forgets to encode a column
// name fails the check with & and + below rather than passing against a stand-in that encoded
// for it (the Task 5 review). Both forms core accepts.
function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) { $args = $key; $url = (string) $value; } else { $args = array( $key => $value ); $url = (string) $url; }
	foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
	return $url;
}
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '" />'; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['can_manage'] ); }
function check_admin_referer( $action ) { if ( ( $GLOBALS['nonce'] ?? '' ) !== $action ) { throw new DieSignal( 'the nonce was refused' ); } return true; }
function wp_safe_redirect( $url ) { throw new RedirectSignal( (string) $url ); }
function wp_send_json_success( $data ) { throw new JsonSignal( json_encode( array( 'success' => true, 'data' => $data ) ) ); }
function wp_die( $message = '', $title = '', $args = array() ) { throw new DieSignal( is_string( $message ) ? $message : '' ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; return true; }
function get_current_user_id() { return 5; }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ (int) $id ] ) ? (object) array( 'display_name' => $GLOBALS['users'][ (int) $id ] ) : false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function wp_enqueue_style( $handle, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = array( 'style', $handle, $deps ); }
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) { $GLOBALS['enqueued'][] = array( 'script', $handle, $deps, $footer ); }
// Faithful to core's esc_js(): markup and double quotes are encoded before the quotes are
// escaped, so a track name with a tag in it cannot break out of the attribute it sits in.
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function esc_js( $s ) { $s = htmlspecialchars( (string) $s, ENT_COMPAT ); $s = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", $s ); return str_replace( "\n", '\\n', addslashes( str_replace( "\r", '', $s ) ) ); }

class RedirectSignal extends Exception {}
class DieSignal extends Exception {}
class JsonSignal extends Exception {}

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WPCPM_Request {
	public static function posted_id( $key ) { return (int) ( $_POST[ $key ] ?? 0 ); }
	public static function id( $key ) { return (int) ( $_GET[ $key ] ?? 0 ); }
	public static function text( $key ) { return isset( $_GET[ $key ] ) ? trim( (string) $_GET[ $key ] ) : ''; }
	public static function posted_text( $key ) { return isset( $_POST[ $key ] ) ? trim( (string) $_POST[ $key ] ) : ''; }
	public static function posted_key( $key ) { return isset( $_POST[ $key ] ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $_POST[ $key ] ) ) : ''; }
	// Faithful to the real class: posted_verbatim() trims, and only posted_exact() keeps a trailing
	// space, which is what a column name needs. A handler reading a column through the wrong one
	// fails the `Company ` checks below (bin/test-request.php holds the real readers to this).
	public static function posted_verbatim( $key ) { return isset( $_POST[ $key ] ) ? trim( (string) $_POST[ $key ] ) : ''; }
	public static function posted_exact( $key ) { return isset( $_POST[ $key ] ) ? (string) $_POST[ $key ] : ''; }
	public static function exact( $key ) { return isset( $_GET[ $key ] ) ? (string) $_GET[ $key ] : ''; }
	public static function posted_verbatim_lines( $key ) { return isset( $_POST[ $key ] ) ? implode( "\n", array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $_POST[ $key ] ) ), 'strlen' ) ) : ''; }
}

$GLOBALS['can_manage'] = true;
$GLOBALS['nonce']      = '';
$GLOBALS['hooks']      = array();
$GLOBALS['opts']     = array();
$GLOBALS['users']    = array( 7 => 'A Manager' );
$GLOBALS['enqueued'] = array();

/** The store, as the screen uses it: definitions, states, logs, equivalence and the seeds. */
/** Publishing, stood in: what the preflight says, what the checklist holds, and what was pressed. */
class WPCPM_Track_Publish {
	public static $flight    = array();
	public static $checklist = array();
	public static $ran       = array();
	public static $ticked    = array();
	public static $down      = array();
	public static $verified  = array();
	public static $answer    = null;

	public static function preflight( $post_id ) { return self::$flight; }
	public static function checklist( $post_id ) { return self::$checklist; }

	public static function run( $post_id, $user_id = 0 ) {
		self::$ran[] = array( (int) $post_id, (int) $user_id );
		return null === self::$answer ? array( 'created' => array( 'Brand new' ), 'published' => true ) : self::$answer;
	}

	public static function take_down( $post_id, $user_id = 0 ) {
		self::$down[] = array( (int) $post_id, (int) $user_id );
		return null === self::$answer ? (int) $post_id : self::$answer;
	}

	public static function verify( $post_id ) {
		self::$verified[] = (int) $post_id;
		return null === self::$answer ? array( 'columns' => array( 'missing' => array(), 'wrong' => array() ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ) ) : self::$answer;
	}

	public static function tick( $post_id, $item, $user_id = 0 ) {
		self::$ticked[] = array( 'tick', (int) $post_id, (string) $item );
		return null === self::$answer ? true : self::$answer;
	}

	public static function untick( $post_id, $item, $user_id = 0 ) {
		self::$ticked[] = array( 'untick', (int) $post_id, (string) $item );
		return null === self::$answer ? true : self::$answer;
	}
}

/** The settings, stood in for the two questions the screen asks them. */
class WPCPM_Settings {
	public static $schema = true;
	public static $values = array( 'reports_table' => 'tblReports' );
	public static function has_schema_token() { return self::$schema; }
	public static function get() { return self::$values; }
}

/** The client, stood in for the one call the editor makes: the cached reading, or no base. */
class WPCPM_Airtable {
	public static $cached = null;
	public static $asked  = 0;

	public function cached_schema() {
		++self::$asked;

		return null === self::$cached ? new WP_Error( 'wpcpm_airtable_error', 'The base could not be read.' ) : self::$cached;
	}
}

class WPCPM_Track_Store {
	const META_SOURCE = '_wpcpm_track_source';
	const OPT_SKIPPED = 'wpcpm_tracks_skipped';

	public static $tracks = array();

	public static function all_ids() {
		return array_keys( self::$tracks );
	}

	public static function get( $post_id ) {
		return self::$tracks[ $post_id ]['definition'] ?? null;
	}

	public static function state( $post_id ) {
		return self::$tracks[ $post_id ]['state'] ?? '';
	}

	public static function source( $post_id ) {
		return self::$tracks[ $post_id ]['source'] ?? '';
	}

	public static function log_entries( $post_id ) {
		return self::$tracks[ $post_id ]['log'] ?? array();
	}

	// As the real one: newest first, and one more than asked for when there is one (decision 28).
	public static function revisions( $post_id, $limit = 20 ) {
		return array_slice( self::$tracks[ $post_id ]['revisions'] ?? array(), 0, max( 1, (int) $limit ) + 1 );
	}

	// What `wp_revisions_to_keep()` answers for the track: -1 unless a check sets a cap (T3b).
	public static function revisions_cap( $post_id ) {
		return self::$tracks[ $post_id ]['cap'] ?? -1;
	}

	public static function equivalence( $post_id ) {
		return self::$tracks[ $post_id ]['equivalence'] ?? array( 'not_builtin' );
	}

	public static function switched( $post_id ) {
		return ! empty( self::$tracks[ $post_id ]['switched'] );
	}

	public static $switches = array();

	public static function switch_to_definition( $post_id, $user_id = 0 ) {
		self::$switches[] = array( 'definition', (int) $post_id );

		return empty( self::$tracks[ $post_id ]['equivalence'] ) ? (int) $post_id : new WP_Error( 'wpcpm_track_not_equivalent', 'The definition is not identical to the track as its PHP runs it.' );
	}

	public static function switch_to_builtin( $post_id, $user_id = 0 ) {
		self::$switches[] = array( 'builtin', (int) $post_id );

		return self::switched( $post_id ) ? (int) $post_id : new WP_Error( 'wpcpm_track_not_switched', 'That track does not run from its definition.' );
	}

	public static function published( $post_id ) {
		return self::$tracks[ $post_id ]['published'] ?? null;
	}

	public static function others( $post_id ) {
		$others = array();

		foreach ( self::$tracks as $id => $track ) {
			if ( (int) $id === (int) $post_id ) {
				continue;
			}

			$others[] = array(
				'label'     => $track['definition']['label'] ?? '',
				'published' => in_array( $track['state'] ?? '', array( 'published', 'changed' ), true ) || 'builtin' === ( $track['source'] ?? '' ),
				'columns'   => array_map( 'strval', array_keys( $track['definition']['questions'] ?? array() ) ),
			);
		}

		return $others;
	}

	public static function ever_published( $post_id ) {
		foreach ( self::$tracks[ $post_id ]['log'] ?? array() as $entry ) {
			if ( 'publish' === ( $entry['did'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	public static $deleted = array();

	public static function delete( $post_id ) {
		if ( ! isset( self::$tracks[ $post_id ] ) ) {
			return new WP_Error( 'wpcpm_track_missing', 'That track does not exist.' );
		}

		if ( self::ever_published( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_was_published', 'This track has been published, so it is kept.' );
		}

		self::$deleted[] = (int) $post_id;
		unset( self::$tracks[ $post_id ] );

		return (int) $post_id;
	}

	public static $refreshed  = array();
	public static $duplicated = array();
	public static $saved      = array();

	public static $created = array();

	public static function create( array $definition ) {
		self::$created[]      = $definition;
		$new                  = 98;
		self::$tracks[ $new ] = array( 'definition' => $definition, 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );

		return $new;
	}

	public static function duplicate( $from_id, array $definition ) {
		self::$duplicated[] = array( (int) $from_id, $definition );
		$new                = 99;
		self::$tracks[ $new ] = array( 'definition' => $definition, 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );

		return $new;
	}
	public static $errors    = array();

	public static function check( $post_id, array $definition ) {
		return self::$errors;
	}

	public static function save( $post_id, array $definition ) {
		if ( 'builtin' === self::source( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_builtin', 'A built-in track runs from its hand-written form until it switches to its definition.' );
		}

		self::$saved[ (int) $post_id ] = $definition;
		self::$tracks[ $post_id ]['definition'] = $definition;

		return (int) $post_id;
	}

	public static function refresh_builtin( $post_id ) {
		self::$refreshed[] = (int) $post_id;

		return isset( self::$tracks[ $post_id ] ) ? (int) $post_id : new WP_Error( 'wpcpm_track_missing', 'That track does not exist.' );
	}

	public static function seeds() {
		return array( 'design' => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track', 'questions' => array( 'A' => 1 ) ) );
	}
}

/** The runtime stores, for what the last compile left out. */
class WPCPM_Tracks {
	const OPT_TRACKS = 'wpcpm_tracks';
}

/** The calendar module, which owns the sheet that dresses the Student Report Card. */
class WPCPM_Call_Calendar {
	const STYLE = 'wpcpm-call-calendar';

	public static $registered = 0;

	public static function register_assets() { ++self::$registered; }
}

/** The report form, stood in: what the preview handed it, and a marker where it drew. */
class WPCPM_Student_Report_Form {
	public static $previewed = array();

	public static function render_preview( array $fields ) {
		self::$previewed[] = array_keys( $fields );
		echo '<div class="wpcpm-report__body wpcpm-report__body--preview">' . count( $fields ) . ' fields</div>';
	}
}

/** The students sync, for how many people are on a track now. */
class WPCPM_Students_Sync {
	public static $counts = array();

	public static function count_on_status( $status ) {
		return (int) ( self::$counts[ $status ] ?? 0 );
	}
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

class WPCPM_Flash {
	public static $set = array();

	public static function set( $key, $value ) {
		self::$set[ $key ] = $value;
	}

	public static function take( $key ) {
		return $GLOBALS['flash'] ?? array();
	}
}

// The real rules, not a stand-in: a stand-in for WPCPM_Track_Questions would let a handler pass
// against a rule the real class does not hold (T2c's stub-drift findings).
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-diff.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-questions.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-tool.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-editor.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-editor-screen.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-builder-screen.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-history-screen.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-builder.php';

$fail  = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label    What is being checked.
 * @param mixed  $actual   What the code answered.
 * @param mixed  $expected What it should answer.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	++$total;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
		echo "FAIL $label\n  got:  " . str_replace( "\n", ' ', var_export( $actual, true ) ) . "\n  want: " . str_replace( "\n", ' ', var_export( $expected, true ) ) . "\n";
		return;
	}
	echo "ok $label\n";
}

echo "=== The tool itself ===\n";

$tool = new WPCPM_Track_Builder();
ck( 'it is a tool, so the Modules menu lists it', $tool instanceof WPCPM_Tool, true );
ck( 'with its own id and page', array( $tool->id(), $tool->page_slug() ), array( 'track-builder', 'wpcpm-tool-track-builder' ) );
ck( 'and it does not need Airtable to draw its list', $tool->is_ready(), true );

echo "\n=== The rows the list shows ===\n";

WPCPM_Track_Store::$tracks = array(
	11 => array(
		'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => 'WordPress Credits Program 150h', 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits/' ),
		'state'       => 'published',
		'source'      => 'builtin',
		'log'         => array( array( 'at' => 1788000000, 'by' => 7, 'did' => 'publish' ) ),
		'equivalence' => array(),
		'published'   => array( 'key' => '150h' ),
	),
	12 => array(
		'definition'  => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track, left behind', 'course_url' => '', 'questions' => array( 'B' => 2 ) ),
		'state'       => 'draft',
		'source'      => 'builtin',
		'log'         => array(),
		'equivalence' => array( 'not_published' ),
		'published'   => null,
	),
	13 => array(
		'definition'  => array( 'key' => 'marketing', 'status' => 'Marketing Track', 'label' => 'Marketing Track', 'course_url' => '', 'questions' => array( 'A' => 1 ) ),
		'state'       => 'published',
		'source'      => 'definition',
		'log'         => array( array( 'at' => 1788100000, 'by' => 7, 'did' => 'publish' ) ),
		'equivalence' => array( 'not_builtin' ),
		'published'   => array( 'key' => 'marketing' ),
	),
);
WPCPM_Students_Sync::$counts = array( 'In Sensei' => 411, 'Marketing Track' => 0 );
$GLOBALS['opts']['wpcpm_tracks_skipped'] = array( 13 => array( 'column_reserved' ) );

$rows = WPCPM_Track_Builder::rows();

ck( 'one row per track, in post order', array_column( $rows, 'id' ), array( 11, 12, 13 ) );
ck( 'each carrying what the list prints',
    array( $rows[0]['label'], $rows[0]['status'], $rows[0]['key'], $rows[0]['source'], $rows[0]['state'], $rows[0]['students'] ),
    array( 'WordPress Credits Program 150h', 'In Sensei', '150h', 'builtin', 'published', 411 ) );
ck( 'who published it last, and when', array( $rows[0]['published_by'], $rows[0]['published_at'] ), array( 7, 1788000000 ) );
ck( 'a track the last compile left out says why', array( $rows[2]['skipped'], $rows[0]['skipped'] ), array( array( 'column_reserved' ), array() ) );
ck( 'a built-in draft that has fallen behind its PHP can be refreshed', array( $rows[1]['stale'], $rows[0]['stale'], $rows[2]['stale'] ), array( true, false, false ) );
ck( 'and the equivalence line travels with the built-in rows', array( $rows[0]['equivalence'], $rows[2]['equivalence'] ), array( array(), array( 'not_builtin' ) ) );

// False against false alone cannot tell this from `rows()` hardcoding `'switched' => false`: it
// has to be seen answering true too, for a track the store actually marks switched (the Task 8
// review).
WPCPM_Track_Store::$tracks[11]['switched'] = true;

$switched_rows = WPCPM_Track_Builder::rows();

unset( WPCPM_Track_Store::$tracks[11]['switched'] );

ck( 'a track that has switched to its definition says so, so the way back can be offered, and one that has not says so too',
    array( $switched_rows[0]['switched'], $rows[0]['switched'], $rows[2]['switched'] ),
    array( true, false, false ) );

echo "\n=== The markup ===\n";

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => $rows, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$html = ob_get_clean();

ck( 'a row per track, and every name on the page', array( substr_count( $html, '<tr class="wpcpm-tracks__row' ), false !== strpos( $html, 'WordPress Credits Program 150h' ), false !== strpos( $html, 'Marketing Track' ) ), array( 3, true, true ) );
ck( 'the students on each track are shown', false !== strpos( $html, '411' ), true );
ck( 'a skipped track is shown as needing action, not left out quietly', false !== strpos( $html, 'wpcpm-tracks__skipped' ), true );
ck( 'the refresh is offered on the stale built-in draft only', substr_count( $html, 'name="action" value="wpcpm_track_refresh"' ), 1 );
ck( 'a built-in track its PHP runs says so rather than offering an edit that would be refused', false !== strpos( $html, 'wpcpm-tracks__readonly' ), true );
ck( 'Edit is offered on every row that has a URL, built-in included, since the form itself refuses to edit one', substr_count( $html, '>Edit</a>' ), 3 );
ck( 'and Duplicate on every row too', substr_count( $html, '>Duplicate</a>' ), 3 );

// From Task 6 onward the screen prints values a Program Administrator typed into a track's own
// name, so a label is exactly where stored markup would surface if `esc_html()` were ever
// dropped. A row built by hand, not through `WPCPM_Track_Builder::rows()` or the three tracks
// above, so this proves what the screen does with a label and nothing about the earlier checks.
$escaped_row = array(
	'id'           => 21,
	'label'        => 'Marketing <b>Track</b>',
	'status'       => 'Marketing Track',
	'key'          => 'marketing',
	'course'       => '',
	'source'       => 'definition',
	'state'        => 'draft',
	'students'     => 0,
	'published_by' => 0,
	'published_at' => 0,
	'skipped'      => array(),
	'equivalence'  => array(),
	'stale'        => false,
);

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => array( $escaped_row ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$escaped_html = ob_get_clean();

ck( 'a label with markup in it reaches the page encoded, not raw',
    array( false !== strpos( $escaped_html, 'Marketing &lt;b&gt;Track&lt;/b&gt;' ), false !== strpos( $escaped_html, '<b>Track</b>' ) ),
    array( true, false ) );

echo "\n=== Refreshing a built-in draft from the screen ===\n";

/**
 * Run a handler and say how it ended: a redirect, or the message it died with.
 *
 * A redirect's target is also kept, in `$GLOBALS['last_redirect']`, for a check that cares where
 * it landed rather than only that it happened (Task 9 review, L5).
 */
function outcome( callable $handler ) {
	try {
		$handler();
	} catch ( RedirectSignal $e ) {
		$GLOBALS['last_redirect'] = $e->getMessage();
		return 'redirect';
	} catch ( DieSignal $e ) {
		return 'die: ' . $e->getMessage();
	}

	return 'no outcome';
}

$tool                        = new WPCPM_Track_Builder();
$GLOBALS['can_manage']       = false;
$GLOBALS['nonce']            = 'another-action';
WPCPM_Track_Store::$refreshed = array();

ck( 'without the capability it dies before the nonce is read, and refreshes nothing',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Track_Store::$refreshed ),
    array( 'die: You do not have permission to manage the program.', array() ) );

$GLOBALS['can_manage'] = true;
$GLOBALS['nonce']      = 'another-action';
ck( 'with the capability but the wrong nonce it dies too',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Track_Store::$refreshed ),
    array( 'die: the nonce was refused', array() ) );

$GLOBALS['nonce'] = WPCPM_Track_Builder::ACTION_REFRESH;
$_POST['track']   = 12;
ck( 'with both, it refreshes that draft and says so',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Track_Store::$refreshed, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
    array( 'redirect', array( 12 ), 'success' ) );

$_POST['track'] = 999;
ck( 'and when the store refuses, the screen says what the store said',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ] ),
    array( 'redirect', array( 'status' => 'error', 'message' => 'That track does not exist.' ) ) );

$tool->boot();
ck( 'and the handler is on admin-post', in_array( 'admin_post_' . WPCPM_Track_Builder::ACTION_REFRESH, $GLOBALS['hooks'], true ), true );
ck( 'and the assets are hooked to admin_enqueue_scripts', in_array( 'admin_enqueue_scripts', $GLOBALS['hooks'], true ), true );

$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-track-builder' );
ck( 'its stylesheet builds on the plugin\'s admin sheet, on its own screen, and the question list\'s script rides in the footer (T3a)', $GLOBALS['enqueued'], array( array( 'style', 'wpcpm-track-builder', array( 'wpcpm-admin' ) ), array( 'script', 'wpcpm-track-editor', array(), true ) ) );
$GLOBALS['enqueued'] = array();
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-settings' );
ck( 'and on no other', $GLOBALS['enqueued'], array() );

echo "\n=== The properties form ===\n";

// The properties edit what a track is; since T3a the form also carries what it asks, and every
// other track's columns for the sharing index, so the list under the properties is drawn from one
// read. A built-in track its PHP still runs is read-only here, because its equivalence with that
// PHP is what the switch rests on (spec section 6), and the store refuses the save in any case.
ck( 'the form offers the track properties, then its questions and every other track\'s columns',
    array_keys( WPCPM_Track_Builder::form( 13 ) ),
    array( 'id', 'label', 'status', 'key', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'read_only', 'questions', 'others', 'schema', 'locked' ) );
ck( 'filled from the definition', array( WPCPM_Track_Builder::form( 13 )['label'], WPCPM_Track_Builder::form( 13 )['status'], WPCPM_Track_Builder::form( 13 )['read_only'] ), array( 'Marketing Track', 'Marketing Track', false ) );
ck( 'and a built-in track its PHP runs is read-only', WPCPM_Track_Builder::form( 11 )['read_only'], true );

ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$form = ob_get_clean();
ck( 'the form posts to the save action with a field per property',
    // By id, since T3a: each add form under the list has a `wpcpm_label` of its own, for the question's words.
    array( substr_count( $form, 'name="action" value="wpcpm_track_save"' ), substr_count( $form, 'id="wpcpm_label" name="wpcpm_label"' ), substr_count( $form, 'name="wpcpm_status"' ), substr_count( $form, 'name="wpcpm_key"' ), substr_count( $form, 'name="wpcpm_hours_target"' ) ),
    array( 1, 1, 1, 1, 1 ) );

// A refusal flashes what was typed, and the form has to prefer it over the stored value - the
// whole point of carrying `values` at all (Task 6 review). The typed label carries a quote and a
// tag so this also proves the win is not shown raw.
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array( 'values' => array( 'label' => 'Renamed "Marketing" <b>Track</b>' ) ) ) );
$flashed = ob_get_clean();
ck( 'a flash value wins over the stored one, and reaches the page encoded',
    array( false !== strpos( $flashed, 'id="wpcpm_label" name="wpcpm_label" value="Renamed &quot;Marketing&quot; &lt;b&gt;Track&lt;/b&gt;"' ), false !== strpos( $flashed, '<b>Track</b>' ) ),
    array( true, false ) );

ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 11 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$readonly = ob_get_clean();
ck( 'a built-in track shows why it cannot be edited instead of a form that would be refused',
    array( substr_count( $readonly, 'name="action" value="wpcpm_track_save"' ), false !== strpos( $readonly, 'wpcpm-tracks__readonly' ) ),
    array( 0, true ) );

// Now that Edit reaches every row, including a built-in one, this is what a person following it
// actually sees: the paragraph, and not one editable field, since the properties table is never
// drawn at all for a read-only form.
ck( 'and none of the editable fields render for it',
    array( substr_count( $readonly, 'name="wpcpm_label"' ), substr_count( $readonly, 'name="wpcpm_hours_target"' ), false !== strpos( $readonly, 'form-table' ) ),
    array( 0, 0, false ) );

// Track 13's definition carries no `hours_target` and an empty `course_url`. Posting the form's
// own output back unchanged must not invent the one or keep the other as a stored empty string: a
// blank field means the property is absent, not a value of zero or '' (the Task 6 review for
// hours_target, and the final review for course_url - an untouched Save was flipping a published
// track to "Unpublished changes" for a value nobody typed).
$form_before = WPCPM_Track_Builder::form( 13 );

$GLOBALS['nonce']          = WPCPM_Track_Builder::ACTION_SAVE;
WPCPM_Track_Store::$saved  = array();
WPCPM_Track_Store::$errors = array();
$_POST                     = array(
	'track'                 => 13,
	'wpcpm_label'           => $form_before['label'],
	'wpcpm_status'          => $form_before['status'],
	'wpcpm_key'             => $form_before['key'],
	'wpcpm_course_url'      => $form_before['course_url'],
	'wpcpm_learn_course_id' => $form_before['learn_course_id'],
	'wpcpm_hours_target'    => $form_before['hours_target'],
	'wpcpm_hue'             => $form_before['hue'],
);

ck( 'an untouched save does not turn no target at all into a target of zero, or no course into an empty one',
    array( outcome( array( $tool, 'handle_save' ) ), array_key_exists( 'hours_target', WPCPM_Track_Store::$saved[13] ), array_key_exists( 'course_url', WPCPM_Track_Store::$saved[13] ) ),
    array( 'redirect', false, false ) );

$GLOBALS['nonce']           = WPCPM_Track_Builder::ACTION_SAVE;
WPCPM_Track_Store::$saved   = array();
WPCPM_Track_Store::$errors  = array();
$_POST                      = array(
	'track'             => 13,
	'wpcpm_label'       => 'Marketing Track, renamed',
	'wpcpm_status'      => 'Marketing Track',
	'wpcpm_key'         => 'marketing',
	'wpcpm_course_url'  => 'https://learn.wordpress.org/course/marketing/',
	'wpcpm_hours_target' => '120',
	'wpcpm_hue'         => 'blue',
);

ck( 'a save writes the properties and leaves the questions alone',
    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Track_Store::$saved[13]['label'], WPCPM_Track_Store::$saved[13]['hours_target'], WPCPM_Track_Store::$saved[13]['key'], WPCPM_Track_Store::$saved[13]['questions'] ),
    array( 'redirect', 'Marketing Track, renamed', 120, 'marketing', array( 'A' => 1 ) ) );

WPCPM_Track_Store::$saved  = array();
WPCPM_Track_Store::$errors = array( array( 'code' => 'status_taken', 'message' => 'Another track already has this status.' ) );
ck( 'a definition the rules refuse is not stored, and the screen says what publishing would have said',
    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Track_Store::$saved, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
    array( 'redirect', array(), 'Another track already has this status.' ) );
ck( 'and what the person typed comes back with the refusal', WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['values']['label'], 'Marketing Track, renamed' );

WPCPM_Track_Store::$errors = array();
$_POST['track']            = 11;
ck( 'the store has the last word on a built-in track, whatever the screen offered',
    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], WPCPM_Track_Store::$saved ),
    array( 'redirect', 'error', array() ) );

$_POST = array();

echo "\n=== Duplicating a track ===\n";

// The three things a copy cannot share are what the form asks for; everything else, the questions
// above all, travels with it.
ck( 'the duplicate form names what the copy needs of its own, and what it is copying',
    WPCPM_Track_Builder::duplicate_form( 11 ),
    array( 'id' => 11, 'from' => 'WordPress Credits Program 150h', 'label' => 'WordPress Credits Program 150h copy', 'status' => '', 'key' => '' ) );

ob_start();
WPCPM_Track_Builder_Screen::render_duplicate( array( 'form' => WPCPM_Track_Builder::duplicate_form( 11 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$duplicate = ob_get_clean();
ck( 'and the form posts the three of them',
    array( substr_count( $duplicate, 'name="action" value="wpcpm_track_duplicate"' ), substr_count( $duplicate, 'name="wpcpm_label"' ), substr_count( $duplicate, 'name="wpcpm_status"' ), substr_count( $duplicate, 'name="wpcpm_key"' ) ),
    array( 1, 1, 1, 1 ) );

$GLOBALS['nonce']             = WPCPM_Track_Builder::ACTION_DUPLICATE;
WPCPM_Track_Store::$duplicated = array();
WPCPM_Track_Store::$errors     = array( array( 'code' => 'status_taken', 'message' => 'Another track already has this status.' ) );
$_POST                         = array( 'track' => 11, 'wpcpm_label' => 'A Copy', 'wpcpm_status' => 'In Sensei', 'wpcpm_key' => 'copy' );

ck( 'a copy claiming a status another track holds is refused before anything is created',
    array( outcome( array( $tool, 'handle_duplicate' ) ), WPCPM_Track_Store::$duplicated, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
    array( 'redirect', array(), 'Another track already has this status.' ) );

WPCPM_Track_Store::$errors = array();
$_POST['wpcpm_status']     = 'Copied Track';
ck( 'and a copy with three of its own is created from the original, questions and all',
    array( outcome( array( $tool, 'handle_duplicate' ) ), WPCPM_Track_Store::$duplicated[0][0], WPCPM_Track_Store::$duplicated[0][1]['status'], WPCPM_Track_Store::$duplicated[0][1]['label'], WPCPM_Track_Store::$duplicated[0][1]['key'] ),
    array( 'redirect', 11, 'Copied Track', 'A Copy', 'copy' ) );

WPCPM_Track_Store::$duplicated = array();
$_POST                         = array( 'track' => 999 );

// A post that is not a track - somebody else's, or none at all - is the one guard between a
// request and a copy of a post it named but never held, so this is pinned on its own (the Task 7
// review).
ck( 'a track id naming no track is refused before anything is created',
    array( outcome( array( $tool, 'handle_duplicate' ) ), WPCPM_Track_Store::$duplicated, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ] ),
    array( 'redirect', array(), array( 'status' => 'error', 'message' => 'That track does not exist.' ) ) );

$_POST = array();

echo "\n=== The switch, both ways ===\n";

// A built-in track runs from its hand-written form until somebody flips it, and only while the two
// are identical, which is what makes the flip invisible to students (spec decision 3.5). The way
// back needs the same: T2a's final review found it could drop a published edit.
WPCPM_Track_Store::$tracks[11]['equivalence'] = array();
WPCPM_Track_Store::$tracks[12]['equivalence'] = array( 'form' );
WPCPM_Track_Store::$tracks[13]['switched']    = true;
WPCPM_Track_Store::$tracks[13]['equivalence'] = array();

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$switches = ob_get_clean();

// "form" alone would pass whatever the actual answer said: every sentence `render_equivalence()`
// prints mentions "its hand-written form". This is the exact sentence only track 12's difference
// (`array( 'form' )`) can produce (the Task 8 review).
ck( 'the track that matches its PHP is offered the switch, and the one that does not is told what differs',
    array( substr_count( $switches, 'name="action" value="wpcpm_track_switch_definition"' ), false !== strpos( $switches, 'Differs from its hand-written form: form.' ), substr_count( $switches, 'wpcpm-tracks__equivalence' ) ),
    array( 1, true, 3 ) );
ck( 'and a track already running from its definition is offered the way back',
    substr_count( $switches, 'name="action" value="wpcpm_track_switch_builtin"' ), 1 );

// `render_equivalence()` returns early for a track that is neither built-in nor switched, and
// nothing above exercises a row like that: without the guard, a plain custom track would be told
// it "differs from its hand-written form" it never had (the Task 8 review).
$plain_row = array(
	'id'           => 31,
	'label'        => 'A Custom Track',
	'status'       => 'Custom Status',
	'key'          => 'custom',
	'course'       => '',
	'source'       => 'definition',
	'state'        => 'draft',
	'students'     => 0,
	'published_by' => 0,
	'published_at' => 0,
	'skipped'      => array(),
	'equivalence'  => array( 'not_builtin' ),
	'switched'     => false,
	'stale'        => false,
);

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => array( $plain_row ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$plain_html = ob_get_clean();

ck( 'a plain track, neither built-in nor switched, is told nothing about its equivalence',
    substr_count( $plain_html, 'wpcpm-tracks__equivalence' ), 0 );

$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_SWITCH_DEFINITION;
WPCPM_Track_Store::$switches = array();
$_POST                       = array( 'track' => 11 );

ck( 'flipping a track to its definition says so',
    array( outcome( array( $tool, 'handle_switch_definition' ) ), WPCPM_Track_Store::$switches, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
    array( 'redirect', array( array( 'definition', 11 ) ), 'success' ) );

WPCPM_Track_Store::$switches = array();
$_POST['track']              = 12;
ck( 'and a track whose definition differs is refused in the store\'s own words',
    array( outcome( array( $tool, 'handle_switch_definition' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
    array( 'redirect', 'The definition is not identical to the track as its PHP runs it.' ) );

$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_SWITCH_BUILTIN;
WPCPM_Track_Store::$switches = array();
$_POST['track']              = 13;
ck( 'the way back runs through the store too',
    array( outcome( array( $tool, 'handle_switch_builtin' ) ), WPCPM_Track_Store::$switches, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
    array( 'redirect', array( array( 'builtin', 13 ) ), 'success' ) );

$_POST = array();

echo "\n=== The publish screen ===\n";

WPCPM_Track_Publish::$flight = array(
	'refusals'    => array(),
	'warnings'    => array( array( 'code' => 'course_unreachable', 'column' => '', 'message' => 'The Learn course did not answer.' ) ),
	'columns'     => array( 'create' => array( 'Brand new' ), 'ready' => array( 'What you did' ) ),
	'choices'     => array( 'reports' => 'ok', 'students' => 'missing' ),
	'fields'      => array( 'now' => 120, 'after' => 121 ),
	'adds_status' => true,
	'ready'       => true,
);
WPCPM_Track_Publish::$checklist = array(
	'automation' => array( 'label' => 'Add the status to the reports automation', 'detail' => 'Add "Marketing Track" to the condition.', 'ticked' => false, 'by' => 0, 'at' => 0 ),
	'welcome'    => array( 'label' => 'Create the welcome email automation', 'detail' => 'Copy an existing one.', 'ticked' => true, 'by' => 7, 'at' => 1788000000 ),
	'choices'    => array( 'label' => 'Add the two Status choices', 'detail' => 'On both tables.', 'ticked' => false, 'by' => 0, 'at' => 0 ),
);
$GLOBALS['users'] = array( 7 => 'Ada Lovelace' );

ob_start();
WPCPM_Track_Builder_Screen::render_publish(
	array(
		'track'     => 12,
		'label'     => 'Marketing Track',
		'state'     => 'draft',
		'preflight' => WPCPM_Track_Publish::$flight,
		'checklist' => WPCPM_Track_Publish::$checklist,
		'can_make'  => true,
		'url'       => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder',
		'flash'     => array(),
	)
);
$screen = ob_get_clean();

ck( 'it names the track it is about', false !== strpos( $screen, 'Publishing Marketing Track' ), true );

ck( 'a warning is drawn as a warning, not as a refusal',
    array( false !== strpos( $screen, 'notice-warning' ), false !== strpos( $screen, 'notice-error' ) ), array( true, false ) );

ck( 'the column to create is named, so somebody could make it by hand',
    false !== strpos( $screen, '<code>Brand new</code>' ), true );

ck( 'and what the table would come to is said',
    false !== strpos( $screen, 'The table would hold 121 columns afterward.' ), true );

ck( 'every checklist item is drawn, with the one that is done marked',
    array( substr_count( $screen, 'wpcpm-tracks__item' ), substr_count( $screen, 'wpcpm-tracks__item--done' ) ), array( 4, 1 ) );

ck( 'a ticked item says who ticked it', false !== strpos( $screen, 'Ticked by Ada Lovelace' ), true );

ck( 'an unticked one offers the tick and a ticked one offers the undo',
    array( substr_count( $screen, 'I have done this' ), substr_count( $screen, '>Undo</button>' ) ), array( 2, 1 ) );

ck( 'the tick carries the item as well as the track',
    false !== strpos( $screen, 'name="item" value="automation"' ), true );

ck( 'a draft that passes its preflight offers Publish, and neither of the live-track buttons',
    array(
        false !== strpos( $screen, 'Publish this track' ),
        false !== strpos( $screen, 'Check it against Airtable' ),
        false !== strpos( $screen, 'Take it off the live site' ),
    ),
    array( true, false, false ) );

// Finding 4 (final review): the preflight works out the Status choice's state on both tables
// and the screen showed only the near warning. The fixture above has reports => ok, students
// => missing, so both states must read differently, not the same "checklist item 3" line.
ck( 'the Status choice\'s state is shown for the table that has it and the one that does not',
    array(
        false !== strpos( $screen, 'Students Reports already has this choice.' ),
        false !== strpos( $screen, 'Students does not have this choice yet.' ),
    ),
    array( true, true ) );

// Finding 5 (final review): adds_status is computed and tested and nothing showed it. The
// fixture above has adds_status => true, for a track of somebody's own. Matched against the
// escaped form - esc_html() turns the apostrophe into &#039; and the quotes into &quot;, same
// as bin/test-institutions-screen.php already does for a string in this shape.
ck( 'publishing a track of one\'s own says it will add the status to the settings',
    false !== strpos( $screen, 'Publishing adds this track&#039;s status to &quot;Currently mentoring&quot; in Settings.' ), true );

$builtin_choices_flight                = WPCPM_Track_Publish::$flight;
$builtin_choices_flight['choices']     = array( 'reports' => 'near', 'students' => 'ok' );
$builtin_choices_flight['adds_status'] = false;

ob_start();
WPCPM_Track_Builder_Screen::render_publish(
	array(
		'track'     => 12,
		'label'     => 'Marketing Track',
		'state'     => 'draft',
		'preflight' => $builtin_choices_flight,
		'checklist' => WPCPM_Track_Publish::$checklist,
		'can_make'  => true,
		'url'       => '',
		'flash'     => array(),
	)
);
$builtin_screen = ob_get_clean();

ck( 'a choice that is nearly there reads as nearly there, not as either "has it" or "does not"',
    false !== strpos( $builtin_screen, 'Students Reports has a choice close to this one, but not an exact match.' ), true );

ck( 'a built-in track\'s screen says publishing adds nothing to the settings',
    false !== strpos( $builtin_screen, 'This track runs from its hand-written form, so publishing it does not add anything to &quot;Currently mentoring&quot; in Settings.' ), true );

// A label with markup in it reaches the page encoded: the track's name is typed by a person.
ob_start();
WPCPM_Track_Builder_Screen::render_publish(
	array(
		'track'     => 12,
		'label'     => 'Marketing <b>Track</b>',
		'state'     => 'draft',
		'preflight' => WPCPM_Track_Publish::$flight,
		'checklist' => array(),
		'can_make'  => true,
		'url'       => '',
		'flash'     => array(),
	)
);
$escaped_screen = ob_get_clean();

ck( 'and a name with markup in it is encoded on the way out',
    array( false !== strpos( $escaped_screen, 'Marketing &lt;b&gt;Track&lt;/b&gt;' ), false !== strpos( $escaped_screen, '<b>Track</b>' ) ),
    array( true, false ) );

$refused_flight            = WPCPM_Track_Publish::$flight;
$refused_flight['ready']   = false;
$refused_flight['refusals'] = array( array( 'code' => 'column_computed', 'column' => 'Personal link', 'message' => 'Airtable works this column out for itself.' ) );

ob_start();
WPCPM_Track_Builder_Screen::render_publish(
	array(
		'track'     => 12,
		'label'     => 'Marketing Track',
		'state'     => 'draft',
		'preflight' => $refused_flight,
		'checklist' => array(),
		'can_make'  => true,
		'url'       => '',
		'flash'     => array(),
	)
);
$refused_screen = ob_get_clean();

ck( 'a refused preflight says so and offers no Publish button at all',
    array( false !== strpos( $refused_screen, 'notice-error' ), false !== strpos( $refused_screen, '<code>Personal link</code>' ), false !== strpos( $refused_screen, 'Publish this track' ) ),
    array( true, true, false ) );

ob_start();
WPCPM_Track_Builder_Screen::render_publish(
	array(
		'track'     => 12,
		'label'     => 'Marketing Track',
		'state'     => 'published',
		'preflight' => WPCPM_Track_Publish::$flight,
		'checklist' => array(),
		'can_make'  => false,
		'url'       => '',
		'flash'     => array(),
	)
);
$live_screen = ob_get_clean();

ck( 'a live track offers the check and the way off the live site, and not Publish',
    array(
        false !== strpos( $live_screen, 'Check it against Airtable' ),
        false !== strpos( $live_screen, 'Take it off the live site' ),
        false !== strpos( $live_screen, 'Publish this track' ),
    ),
    array( true, true, false ) );

ck( 'and with no schema token the columns are a list to make by hand',
    false !== strpos( $live_screen, 'no schema token is configured' ), true );

// M1/L8 (Task 9 review): a draft whose preflight is ready but has columns pending, on a site
// with no schema token, must not be handed a Publish button - `run()` can only refuse it with
// `wpcpm_track_columns_by_hand` - and the by-hand list it is offered instead has to carry enough
// (name, type, and a select's choices) that nobody has to guess.
$by_hand_flight            = WPCPM_Track_Publish::$flight;
$by_hand_flight['columns'] = array(
	'create' => array( 'Brand new', 'Favorite color' ),
	'ready'  => array( 'What you did' ),
	'detail' => array(
		'Brand new'      => array( 'type' => 'singleLineText' ),
		'Favorite color' => array(
			'type'    => 'singleSelect',
			'options' => array( 'choices' => array( array( 'name' => 'Red' ), array( 'name' => 'Green' ) ) ),
		),
	),
);

ob_start();
WPCPM_Track_Builder_Screen::render_publish(
	array(
		'track'     => 12,
		'label'     => 'Marketing Track',
		'state'     => 'draft',
		'preflight' => $by_hand_flight,
		'checklist' => array(),
		'can_make'  => false,
		'url'       => '',
		'flash'     => array(),
	)
);
$by_hand_screen = ob_get_clean();

ck( 'a draft with columns pending and no schema token is not offered Publish, which could only fail',
    array( false !== strpos( $by_hand_screen, 'Publish this track' ), false !== strpos( $by_hand_screen, 'no schema token is configured' ) ),
    array( false, true ) );

ck( 'the by-hand list gives the type beside the name',
    false !== strpos( $by_hand_screen, '<code>Brand new</code> - singleLineText' ), true );

ck( 'and a select column\'s choices too',
    false !== strpos( $by_hand_screen, '<code>Favorite color</code> - singleSelect (choices: Red, Green)' ), true );

echo "\n=== The publish handlers, and their guards ===\n";

$tool = new WPCPM_Track_Builder();

$GLOBALS['can_manage']        = false;
$GLOBALS['nonce']             = 'another-action';
WPCPM_Track_Publish::$ran      = array();
WPCPM_Track_Publish::$down     = array();
WPCPM_Track_Publish::$ticked   = array();
WPCPM_Track_Publish::$verified = array();
$_POST                         = array( 'track' => 12, 'item' => 'automation' );

// Decision 3.9: the capability is checked before the nonce. The nonce here is the wrong one, so
// a handler that read it first would die saying so instead.
foreach ( array( 'handle_publish', 'handle_unpublish', 'handle_verify', 'handle_tick', 'handle_untick' ) as $handler ) {
	ck( sprintf( '%s dies on the capability before it reads the nonce', $handler ),
	    outcome( array( $tool, $handler ) ), 'die: You do not have permission to manage the program.' );
}

// L4 (Task 9 review): $verified belongs in this list too, or a handle_verify() that read before
// its guard would still pass every check here.
ck( 'and none of them did anything',
    array( WPCPM_Track_Publish::$ran, WPCPM_Track_Publish::$down, WPCPM_Track_Publish::$ticked, WPCPM_Track_Publish::$verified ),
    array( array(), array(), array(), array() ) );

$GLOBALS['can_manage'] = true;
$GLOBALS['nonce']      = WPCPM_Track_Builder::ACTION_PUBLISH;
WPCPM_Track_Publish::$answer = null;

ck( 'with both guards passed, Publish runs for the track that was posted',
    array( outcome( array( $tool, 'handle_publish' ) ), WPCPM_Track_Publish::$ran ), array( 'redirect', array( array( 12, 5 ) ) ) );

ck( 'and the flash says how many columns were created',
    false !== strpos( WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'], '1 column was created' ), true );

// L5 (Task 9 review): the redirect target itself, not only that a redirect happened - four
// handlers deliberately carry `wpcpm_publish` so the notice lands back on this same screen.
ck( 'and lands back on the publish screen it was pressed from',
    false !== strpos( $GLOBALS['last_redirect'], 'wpcpm_publish=12' ), true );

// L6 (Task 9 review): handle_publish()'s error path had no check at all.
WPCPM_Track_Publish::$answer = new WP_Error( 'wpcpm_track_columns_by_hand', 'This track needs columns the base does not have, and no schema token is configured.' );

ck( 'a publish the store refuses comes back as the error it is',
    array( outcome( array( $tool, 'handle_publish' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ] ),
    array( 'redirect', array( 'status' => 'error', 'message' => 'This track needs columns the base does not have, and no schema token is configured.' ) ) );

// L6 (Task 9 review): the brief's own "nothing had to be created" case, the 0 === $made branch.
WPCPM_Track_Publish::$answer = array( 'created' => array(), 'published' => true );

ck( 'and a run with nothing pending says so, not a count of columns it did not make',
    array( outcome( array( $tool, 'handle_publish' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
    array( 'redirect', 'The track is live. Nothing had to be created in Airtable: every column was already there.' ) );

$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_UNPUBLISH;
WPCPM_Track_Publish::$answer = new WP_Error( 'wpcpm_track_in_use', '3 students are on this track in Airtable.' );

ck( 'a refused unpublish comes back as the error it is, in the store\'s own words',
    array( outcome( array( $tool, 'handle_unpublish' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ] ),
    array( 'redirect', array( 'status' => 'error', 'message' => '3 students are on this track in Airtable.' ) ) );

$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_TICK;
WPCPM_Track_Publish::$answer = null;
WPCPM_Track_Publish::$ticked = array();

ck( 'a tick names the item it was pressed for',
    array( outcome( array( $tool, 'handle_tick' ) ), WPCPM_Track_Publish::$ticked ), array( 'redirect', array( array( 'tick', 12, 'automation' ) ) ) );

ck( 'and it too lands back on the publish screen',
    false !== strpos( $GLOBALS['last_redirect'], 'wpcpm_publish=12' ), true );

// L6 (Task 9 review): handle_untick()'s effect was exercised only by its capability guard.
$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_UNTICK;
WPCPM_Track_Publish::$answer = null;
WPCPM_Track_Publish::$ticked = array();

ck( 'and untick names the item it was pressed for too',
    array( outcome( array( $tool, 'handle_untick' ) ), WPCPM_Track_Publish::$ticked ), array( 'redirect', array( array( 'untick', 12, 'automation' ) ) ) );

ck( 'landing back on the publish screen as well',
    false !== strpos( $GLOBALS['last_redirect'], 'wpcpm_publish=12' ), true );

$GLOBALS['nonce']              = WPCPM_Track_Builder::ACTION_VERIFY;
WPCPM_Track_Publish::$answer   = array( 'columns' => array( 'missing' => array( 'Brand new' ), 'wrong' => array() ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ) );

WPCPM_Flash::$set = array();
$verify_outcome   = outcome( array( $tool, 'handle_verify' ) );

// A column renamed in the base takes its answers with it, and the form keeps writing into a name
// nothing reads, so the one thing this notice must do is name the column (7.4).
ck( 'a verify that finds a column gone names it, as an error',
    array( $verify_outcome, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], false !== strpos( WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'], 'Brand new' ) ),
    array( 'redirect', 'error', true ) );

ck( 'and lands back on the publish screen',
    false !== strpos( $GLOBALS['last_redirect'], 'wpcpm_publish=12' ), true );

// L2/L6 (Task 9 review): verified()'s choices branch had no check, and the notice it returns now
// names the checklist item ("Add the two Status choices") rather than a number the screen, which
// draws an unordered list, never shows.
WPCPM_Flash::$set            = array();
WPCPM_Track_Publish::$answer = array( 'columns' => array( 'missing' => array(), 'wrong' => array() ), 'choices' => array( 'reports' => 'near', 'students' => 'ok' ) );
outcome( array( $tool, 'handle_verify' ) );

ck( 'and one where the columns are clean but the status choice is not names the checklist item, not a number the screen never shows',
    array( WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
    array( 'error', 'The track\'s status is not a choice on both tables, so students on it are not synced. "Add the two Status choices" is the checklist item to do.' ) );

WPCPM_Flash::$set            = array();
WPCPM_Track_Publish::$answer = array( 'columns' => array( 'missing' => array(), 'wrong' => array() ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ) );
outcome( array( $tool, 'handle_verify' ) );

ck( 'and one that finds everything in place says so',
    WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], 'success' );

WPCPM_Track_Publish::$answer = null;
$_POST                       = array();



echo "\n=== The question editor: its handlers ===\n";

$editor = new WPCPM_Track_Editor( $tool );
$GLOBALS['hooks'] = array();
$editor->boot();

ck( 'the editor hooks its five handlers',
    $GLOBALS['hooks'],
    array( 'admin_post_wpcpm_question_add', 'admin_post_wpcpm_question_save', 'admin_post_wpcpm_question_move', 'admin_post_wpcpm_question_remove', 'admin_post_wpcpm_track_delete' ) );

/**
 * Press one of the editor's handlers and report what came of it.
 *
 * @param string $method The handler.
 * @param array  $post   What the form posted.
 * @return array `redirect`, `die` or `json`, and the detail.
 */
function press_editor( $method, array $post ) {
	global $editor;
	$_POST = $post;
	WPCPM_Flash::$set = array();

	try {
		$editor->$method();
	} catch ( RedirectSignal $e ) {
		return array( 'redirect', $e->getMessage(), WPCPM_Flash::$set['track-builder'] ?? array() );
	} catch ( DieSignal $e ) {
		return array( 'die', $e->getMessage() );
	} catch ( JsonSignal $e ) {
		return array( 'json', json_decode( $e->getMessage(), true ) );
	}

	return array( 'fell through' );
}

/** A track of three questions across two groups, a draft of somebody's own. */
function editable_track() {
	return array(
		'definition' => array(
			'schema_version' => 1,
			'key'            => 'marketing',
			'status'         => 'Marketing Track',
			'label'          => 'Marketing Track',
			'questions'      => array(
				'Hours'      => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'min' => 0, 'max' => 1000, 'step' => 1, 'airtable_type' => 'number' ),
				'Slack name' => array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding', 'airtable_type' => 'singleLineText' ),
				'Your blog'  => array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding', 'airtable_type' => 'url', 'learn_lesson_id' => 4242 ),
			),
		),
		'state'       => 'draft',
		'source'      => 'definition',
		'log'         => array(),
		'equivalence' => array( 'not_builtin' ),
		'published'   => null,
	);
}

WPCPM_Track_Store::$tracks = array(
	13 => editable_track(),
	11 => array(
		'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Hours' => array( 'type' => 'number' ), 'Slack name' => array( 'type' => 'text' ) ) ),
		'state'       => 'published',
		'source'      => 'builtin',
		'log'         => array( array( 'at' => 1788000000, 'by' => 7, 'did' => 'publish' ) ),
		'equivalence' => array(),
		'published'   => array( 'key' => '150h' ),
	),
);
WPCPM_Track_Store::$errors = array();
WPCPM_Track_Store::$saved  = array();

echo "\n--- capability first, then the nonce, on every handler ---\n";

foreach ( array( 'handle_add', 'handle_save', 'handle_move', 'handle_remove', 'handle_delete' ) as $handler ) {
	$GLOBALS['can_manage'] = false;
	$GLOBALS['nonce']      = '';
	$refused = press_editor( $handler, array( 'track' => 13 ) );
	$GLOBALS['can_manage'] = true;
	$nonce_refused = press_editor( $handler, array( 'track' => 13 ) );

	ck( "$handler refuses somebody without the capability before it looks at the nonce, and then a bad nonce",
	    array( $refused[0], $refused[1], $nonce_refused[0], $nonce_refused[1] ),
	    array( 'die', 'You do not have permission to manage the program.', 'die', 'the nonce was refused' ) );
}

echo "\n--- adding ---\n";

$GLOBALS['nonce'] = WPCPM_Track_Editor::ACTION_ADD;
$added = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Company ', 'wpcpm_label' => 'Where you work', 'wpcpm_type' => 'text', 'wpcpm_group' => 'onboarding' ) );

ck( 'a new question lands after the last of its group, with its control, its words, its group and the Airtable type its control implies',
    array( array_keys( WPCPM_Track_Store::$saved[13]['questions'] ), WPCPM_Track_Store::$saved[13]['questions']['Company '] ),
    array(
        array( 'Hours', 'Slack name', 'Your blog', 'Company ' ),
        array( 'type' => 'text', 'label' => 'Where you work', 'group' => 'onboarding', 'airtable_type' => 'singleLineText' ),
    ) );

ck( 'and the person is taken to the new question, its column name verbatim',
    array( $added[0], $added[1], $added[2]['status'] ),
    array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=13&wpcpm_question=Company%20', 'success' ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
$ampersand = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Practical: Duplicate & Explore + more', 'wpcpm_label' => 'Two things', 'wpcpm_type' => 'text', 'wpcpm_group' => 'project' ) );

ck( 'a column holding & and + reaches the redirect encoded, so it comes back as the same column',
    array( $ampersand[2]['status'], substr( $ampersand[1], -strlen( '&wpcpm_question=Practical%3A%20Duplicate%20%26%20Explore%20%2B%20more' ) ) ),
    array( 'success', '&wpcpm_question=Practical%3A%20Duplicate%20%26%20Explore%20%2B%20more' ) );

WPCPM_Track_Store::$saved = array();
$dup = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Hours', 'wpcpm_label' => 'Again', 'wpcpm_type' => 'number', 'wpcpm_group' => 'hours' ) );

// Under `question_values`, not `values`: `values` is what the track's own properties are drawn
// from, and a question's label arriving there renamed the track (the whole-branch review).
ck( 'a column the track already asks is refused, nothing saved, and what was typed comes back',
    array( $dup[2]['status'], $dup[2]['question_values']['column'], array_key_exists( 'values', $dup[2] ), WPCPM_Track_Store::$saved ),
    array( 'error', 'Hours', false, array() ) );

WPCPM_Track_Store::$errors = array( array( 'code' => 'column_reserved', 'message' => 'This column belongs to the syncs.' ) );
$reserved = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Status', 'wpcpm_label' => 'Your status', 'wpcpm_type' => 'text', 'wpcpm_group' => 'project' ) );
WPCPM_Track_Store::$errors = array();

ck( 'the store\'s own rules refuse through the same call the publish screen makes',
    array( $reserved[2]['status'], $reserved[2]['message'], WPCPM_Track_Store::$saved ),
    array( 'error', 'This column belongs to the syncs.', array() ) );

// The whole definition is checked, so the first refusal may name another question entirely. The
// key is `where`, the one `WPCPM_Track_Definition::validate()` writes and the one
// `WPCPM_Track_Publish::preflight()` reads; a refusal carrying `column` names nothing, because
// nothing writes that key (the final review of T3b).
WPCPM_Track_Store::$errors = array( array( 'code' => 'label_empty', 'where' => 'Slack name', 'message' => 'The question needs the words a student reads.' ) );
$named = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Status', 'wpcpm_label' => 'Your status', 'wpcpm_type' => 'text', 'wpcpm_group' => 'project' ) );

WPCPM_Track_Store::$errors = array( array( 'code' => 'label_empty', 'column' => 'Slack name', 'message' => 'The question needs the words a student reads.' ) );
$named_column = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Status', 'wpcpm_label' => 'Your status', 'wpcpm_type' => 'text', 'wpcpm_group' => 'project' ) );
WPCPM_Track_Store::$errors = array();

ck( 'a refusal names its column through `where`, so a rule another question tripped is not read as this one\'s, and `column` names nothing because nothing writes it',
    array( $named[2]['message'], $named_column[2]['message'] ),
    array( 'Slack name: The question needs the words a student reads.', 'The question needs the words a student reads.' ) );

$team = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Main Contribution Team', 'wpcpm_label' => 'Your team', 'wpcpm_type' => 'team', 'wpcpm_group' => 'project' ) );

ck( 'a team question takes the link type, which no other control may',
    array( $team[2]['status'], WPCPM_Track_Store::$saved[13]['questions']['Main Contribution Team']['airtable_type'] ?? 'not saved' ),
    array( 'success', 'multipleRecordLinks' ) );

echo "\n--- saving one question ---\n";

WPCPM_Track_Store::$tracks[13] = editable_track();
WPCPM_Track_Store::$saved      = array();
$GLOBALS['nonce'] = WPCPM_Track_Editor::ACTION_SAVE;

$saved = press_editor( 'handle_save', array(
	'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_column' => 'Your blog',
	'wpcpm_type' => 'url', 'wpcpm_label' => 'Your blog, if you have one', 'wpcpm_group' => 'onboarding',
	'wpcpm_help' => 'The address', 'wpcpm_lead' => 'About you', 'wpcpm_required' => '1', 'wpcpm_hide_from_institution' => '1',
	'wpcpm_row' => 'links', 'wpcpm_stack' => '1', 'wpcpm_why' => 'Kept short',
) );

ck( 'every property the control owns is read, the flags only when ticked, and the lesson id is carried through untouched',
    WPCPM_Track_Store::$saved[13]['questions']['Your blog'],
    array( 'type' => 'url', 'label' => 'Your blog, if you have one', 'group' => 'onboarding', 'help' => 'The address', 'lead' => 'About you', 'why' => 'Kept short', 'row' => 'links', 'stack' => true, 'required' => true, 'hide_from_institution' => true, 'airtable_type' => 'url', 'learn_lesson_id' => 4242 ) );

ck( 'and the question keeps its place',
    array_keys( WPCPM_Track_Store::$saved[13]['questions'] ), array( 'Hours', 'Slack name', 'Your blog' ) );

ck( 'a save returns to the track',
    array( $saved[0], $saved[1], $saved[2]['status'] ),
    array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=13', 'success' ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Hours', 'wpcpm_column' => 'Hours', 'wpcpm_type' => 'number', 'wpcpm_label' => 'Hours', 'wpcpm_group' => 'hours', 'wpcpm_min' => '0', 'wpcpm_max' => '100', 'wpcpm_step' => '0.5' ) );

ck( 'a number reads its bounds as numbers, a step with a point as a float',
    array( WPCPM_Track_Store::$saved[13]['questions']['Hours']['min'], WPCPM_Track_Store::$saved[13]['questions']['Hours']['max'], WPCPM_Track_Store::$saved[13]['questions']['Hours']['step'] ),
    array( 0, 100, 0.5 ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_column' => 'Slack name', 'wpcpm_type' => 'text', 'wpcpm_label' => 'Your Slack name', 'wpcpm_group' => 'onboarding', 'wpcpm_maxlength' => '100' ) );

ck( 'text reads its length limit as a whole number',
    WPCPM_Track_Store::$saved[13]['questions']['Slack name']['maxlength'], 100 );

WPCPM_Track_Store::$tracks[13] = editable_track();
press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_column' => 'Your blog', 'wpcpm_type' => 'select', 'wpcpm_label' => 'Your blog', 'wpcpm_group' => 'onboarding', 'wpcpm_options' => " Yes \r\n\r\nNo\n" ) );

ck( 'a select reads its choices one a line, trimmed, blank lines dropped, and the control change moves the Airtable type with it',
    array( WPCPM_Track_Store::$saved[13]['questions']['Your blog']['options'], WPCPM_Track_Store::$saved[13]['questions']['Your blog']['airtable_type'] ),
    array( array( 'Yes', 'No' ), 'singleSelect' ) );

echo "\n--- renaming a column ---\n";

WPCPM_Track_Store::$tracks[13] = editable_track();
$renamed = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_column' => 'Your website', 'wpcpm_type' => 'url', 'wpcpm_label' => 'Your blog', 'wpcpm_group' => 'onboarding' ) );

ck( 'a question never published may take another column, and keeps its place',
    array( $renamed[2]['status'], array_keys( WPCPM_Track_Store::$saved[13]['questions'] ) ),
    array( 'success', array( 'Hours', 'Slack name', 'Your website' ) ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
WPCPM_Track_Store::$saved      = array();
$onto = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_column' => 'Hours', 'wpcpm_type' => 'url', 'wpcpm_label' => 'Your blog', 'wpcpm_group' => 'onboarding' ) );

ck( 'renaming onto another question is refused, back on the question with what was typed',
    array( $onto[2]['status'], $onto[1], WPCPM_Track_Store::$saved ),
    array( 'error', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=13&wpcpm_question=Your%20blog', array() ) );

echo "\n--- forking a shared column ---\n";

// Slack name is shared with the 150-hour Track: rewording keeps it, a control change forks it.
WPCPM_Track_Store::$tracks[13] = editable_track();
$reworded = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_column' => 'Slack name', 'wpcpm_type' => 'text', 'wpcpm_label' => 'Your name in Slack', 'wpcpm_group' => 'onboarding' ) );

ck( 'rewording a shared question keeps its column',
    array( $reworded[2]['status'], array_keys( WPCPM_Track_Store::$saved[13]['questions'] ) ),
    array( 'success', array( 'Hours', 'Slack name', 'Your blog' ) ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
$forked = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_column' => 'Slack name', 'wpcpm_type' => 'textarea', 'wpcpm_label' => 'Your Slack name', 'wpcpm_group' => 'onboarding' ) );

ck( 'changing the control of a shared question gives it a column of its own, named after the track, in the same place',
    array( array_keys( WPCPM_Track_Store::$saved[13]['questions'] ), WPCPM_Track_Store::$saved[13]['questions']['Slack name - marketing']['airtable_type'] ),
    array( array( 'Hours', 'Slack name - marketing', 'Your blog' ), 'multilineText' ) );

ck( 'and the message says so, naming the new column',
    array( $forked[2]['status'], false !== strpos( $forked[2]['message'], 'Slack name - marketing' ) ),
    array( 'success', true ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name - marketing'] = array( 'type' => 'text', 'label' => 'Taken', 'group' => 'onboarding' );
WPCPM_Track_Store::$saved = array();
$collision = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_column' => 'Slack name', 'wpcpm_type' => 'textarea', 'wpcpm_label' => 'Your Slack name', 'wpcpm_group' => 'onboarding' ) );

ck( 'a fork whose name the track already uses is refused rather than overwriting it',
    array( $collision[2]['status'], WPCPM_Track_Store::$saved ), array( 'error', array() ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
$alone = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_column' => 'Your blog', 'wpcpm_type' => 'textarea', 'wpcpm_label' => 'Your blog', 'wpcpm_group' => 'onboarding' ) );

ck( 'a question no other track shares just changes its control',
    array( $alone[2]['status'], array_keys( WPCPM_Track_Store::$saved[13]['questions'] ) ),
    array( 'success', array( 'Hours', 'Slack name', 'Your blog' ) ) );

echo "\n--- a published question is fixed ---\n";

WPCPM_Track_Store::$tracks[13]              = editable_track();
WPCPM_Track_Store::$tracks[13]['state']     = 'published';
WPCPM_Track_Store::$tracks[13]['published'] = array( 'questions' => array( 'Hours' => array(), 'Slack name' => array() ) );
WPCPM_Track_Store::$saved = array();

$locked_rename = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_column' => 'Slack handle', 'wpcpm_type' => 'text', 'wpcpm_label' => 'Your Slack name', 'wpcpm_group' => 'onboarding' ) );
$locked_fork   = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_column' => 'Slack name', 'wpcpm_type' => 'textarea', 'wpcpm_label' => 'Your Slack name', 'wpcpm_group' => 'onboarding' ) );

ck( 'a published question can neither take another column nor fork, and is told to remove and re-add instead',
    array( $locked_rename[2]['status'], $locked_fork[2]['status'], false !== strpos( $locked_fork[2]['message'], 'remove it and add a new question' ), WPCPM_Track_Store::$saved ),
    array( 'error', 'error', true, array() ) );

$locked_reword = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_column' => 'Slack name', 'wpcpm_type' => 'text', 'wpcpm_label' => 'Your name in Slack', 'wpcpm_group' => 'onboarding' ) );

ck( 'but its wording may still change',
    array( $locked_reword[2]['status'], WPCPM_Track_Store::$saved[13]['questions']['Slack name']['label'] ),
    array( 'success', 'Your name in Slack' ) );

$unpublished_one = press_editor( 'handle_save', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_column' => 'Your site', 'wpcpm_type' => 'url', 'wpcpm_label' => 'Your blog', 'wpcpm_group' => 'onboarding' ) );

ck( 'and a question added since the last publish is still free to move column',
    array( $unpublished_one[2]['status'], array_key_exists( 'Your site', WPCPM_Track_Store::$saved[13]['questions'] ) ),
    array( 'success', true ) );

echo "\n--- moving ---\n";

WPCPM_Track_Store::$tracks[13] = editable_track();
WPCPM_Track_Store::$saved      = array();
$GLOBALS['nonce'] = WPCPM_Track_Editor::ACTION_MOVE;

$moved = press_editor( 'handle_move', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_direction' => 'up' ) );

ck( 'a move swaps within the group, saves, and comes back to the track',
    array( array_keys( WPCPM_Track_Store::$saved[13]['questions'] ), $moved[0], $moved[2]['status'] ),
    array( array( 'Hours', 'Your blog', 'Slack name' ), 'redirect', 'success' ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
WPCPM_Track_Store::$saved      = array();
$edge = press_editor( 'handle_move', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_direction' => 'up', 'wpcpm_async' => '1' ) );

ck( 'at the edge nothing is saved, and the page that asked in the background gets the order the store holds',
    array( WPCPM_Track_Store::$saved, $edge[0], $edge[1]['data']['order'] ),
    array( array(), 'json', array( 'Hours', 'Slack name', 'Your blog' ) ) );

$async = press_editor( 'handle_move', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_direction' => 'up', 'wpcpm_async' => '1' ) );

ck( 'a background move answers with the new order',
    $async[1]['data']['order'], array( 'Hours', 'Your blog', 'Slack name' ) );

// Without the script the flash is all a person reads, so it cannot say a row moved when the map
// came back unchanged (the whole-branch review).
WPCPM_Track_Store::$tracks[13] = editable_track();
WPCPM_Track_Store::$saved      = array();
$edge_page = press_editor( 'handle_move', array( 'track' => 13, 'wpcpm_question' => 'Slack name', 'wpcpm_direction' => 'up' ) );

ck( 'at the edge without the script the message says nothing moved, and still comes back as a success',
    array( $edge_page[0], $edge_page[2]['status'], $edge_page[2]['message'], WPCPM_Track_Store::$saved ),
    array( 'redirect', 'success', 'That question is already at the edge of its group, so nothing moved.', array() ) );

WPCPM_Track_Store::$tracks[13] = editable_track();
$moved_page = press_editor( 'handle_move', array( 'track' => 13, 'wpcpm_question' => 'Your blog', 'wpcpm_direction' => 'up' ) );

ck( 'and a move that did happen still says so',
    $moved_page[2]['message'], 'The question was moved.' );

echo "\n--- removing ---\n";

WPCPM_Track_Store::$tracks[13] = editable_track();
$GLOBALS['nonce'] = WPCPM_Track_Editor::ACTION_REMOVE;
$removed = press_editor( 'handle_remove', array( 'track' => 13, 'wpcpm_question' => 'Slack name' ) );

ck( 'a removed question leaves the track, and the message says the column and its answers stay in Airtable',
    array( array_keys( WPCPM_Track_Store::$saved[13]['questions'] ), false !== strpos( $removed[2]['message'], 'stay in Airtable' ) ),
    array( array( 'Hours', 'Your blog' ), true ) );

WPCPM_Track_Store::$saved = array();
$gone = press_editor( 'handle_remove', array( 'track' => 13, 'wpcpm_question' => 'Nothing here' ) );

ck( 'a question that is not on the track is refused and nothing is saved',
    array( $gone[2]['status'], WPCPM_Track_Store::$saved ), array( 'error', array() ) );

echo "\n--- deleting a track ---\n";

$GLOBALS['nonce'] = WPCPM_Track_Editor::ACTION_DELETE;
WPCPM_Track_Store::$deleted = array();
$kept = press_editor( 'handle_delete', array( 'track' => 11 ) );

ck( 'a track that was ever published is refused by the store and kept',
    array( $kept[2]['status'], WPCPM_Track_Store::$deleted, isset( WPCPM_Track_Store::$tracks[11] ) ),
    array( 'error', array(), true ) );

$deleted = press_editor( 'handle_delete', array( 'track' => 13 ) );

ck( 'a draft never published is deleted, and the list says which one went',
    array( $deleted[2]['status'], WPCPM_Track_Store::$deleted, $deleted[1], false !== strpos( $deleted[2]['message'], 'Marketing Track was deleted' ) ),
    array( 'success', array( 13 ), 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', true ) );


echo "\n=== The question list under a track's properties ===\n";

WPCPM_Track_Store::$tracks = array(
	13 => editable_track(),
	11 => array(
		'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours' ), 'Slack name' => array( 'type' => 'text', 'label' => 'Slack', 'group' => 'onboarding' ) ) ),
		'state'       => 'published',
		'source'      => 'builtin',
		'log'         => array( array( 'at' => 1788000000, 'by' => 7, 'did' => 'publish' ) ),
		'equivalence' => array(),
		'published'   => array( 'key' => '150h' ),
	),
);
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name - marketing'] = array( 'type' => 'textarea', 'label' => 'Your Slack name, at length', 'group' => 'onboarding' );

$form = WPCPM_Track_Builder::form( 13 );

ck( 'form() carries the questions in order and every other track\'s columns',
    array( array_keys( $form['questions'] ), array_column( $form['others'], 'label' ) ),
    array( array( 'Hours', 'Slack name', 'Your blog', 'Slack name - marketing' ), array( '150-hour Track' ) ) );

ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => $form, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$list = ob_get_clean();

ck( 'the four groups are drawn in the order the Student Report Card uses, each with its heading',
    array_map( function ( $m ) { return $m; }, preg_match_all( '/<h3>([^<]+)<\/h3>/', $list, $m ) ? $m[1] : array() ),
    array( 'Total hours', 'Onboarding', 'Project', 'Wrap-up' ) );

ck( 'a group with nothing in it says so, and still offers Add',
    array( substr_count( $list, 'No questions in this group.' ), substr_count( $list, 'name="wpcpm_group" value="wrapup"' ) ),
    array( 2, 1 ) );

preg_match_all( '/<tr class="wpcpm-question" id="wpcpm-question-[a-f0-9]{32}" data-wpcpm-column="([^"]*)" data-wpcpm-group="([^"]*)">/', $list, $rows_found );

ck( 'each row carries its column verbatim and its group, in page order',
    array( $rows_found[1], $rows_found[2] ),
    array( array( 'Hours', 'Slack name', 'Your blog', 'Slack name - marketing' ), array( 'hours', 'onboarding', 'onboarding', 'onboarding' ) ) );

ck( 'the words, the column as code, and the control by its name',
    array(
        false !== strpos( $list, '<strong>Your Slack name</strong><code class="wpcpm-question__column">Slack name</code>' ),
        substr_count( $list, '<td>Text, one line</td>' ),
        substr_count( $list, '<td>Web address</td>' ),
    ),
    array( true, 1, 1 ) );

ck( 'a column another track writes says so, naming it, and a fork says what it came from',
    array(
        substr_count( $list, 'Shared with 150-hour Track. Rewording keeps the column' ),
        false !== strpos( $list, 'A column of this track&#039;s own, forked from Slack name.' ),
        false === strpos( $list, 'Shared with' . ' ' . 'Marketing' ),
    ),
    array( 2, true, true ) );

ck( 'each row offers Edit by column name, two arrows in a background-ready form, and Remove behind a confirmation that says what stays in Airtable',
    array(
        substr_count( $list, 'wpcpm_question=Slack%20name%20-%20marketing">Edit</a>' ),
        substr_count( $list, 'class="wpcpm-question__mover" data-wpcpm-refused="The move was not saved. The question is back where it was."' ),
        substr_count( $list, 'name="wpcpm_direction" value="up"' ),
        substr_count( $list, 'name="wpcpm_direction" value="down"' ),
        substr_count( $list, 'onsubmit="return confirm(\'Remove this question from the track? Its column, and whatever students wrote in it, stay in Airtable.\');"' ),
    ),
    array( 1, 4, 4, 4, 4 ) );

ck( 'every form carries its own nonce and action',
    array(
        substr_count( $list, 'name="_wpnonce" value="wpcpm_question_move"' ),
        substr_count( $list, 'name="_wpnonce" value="wpcpm_question_remove"' ),
        substr_count( $list, 'name="_wpnonce" value="wpcpm_question_add"' ),
        substr_count( $list, 'name="action" value="wpcpm_question_add"' ),
    ),
    array( 4, 4, 4, 4 ) );

ck( 'the add form asks for the column, the words and one of the ten controls, and knows its group',
    array(
        substr_count( $list, 'name="wpcpm_column"' ),
        substr_count( $list, 'id="wpcpm_add_label_' ),
        substr_count( $list, '<select id="wpcpm_add_type_project" name="wpcpm_type">' ),
        substr_count( $list, '<option value="team">Contribution team</option>' ),
    ),
    array( 4, 4, 1, 4 ) );

ck( 'the list sits after the properties form, not inside it',
    strpos( $list, '<div class="wpcpm-questions">' ) > strpos( $list, 'Save the track' ), true );

// A refused Add lands here, on the track's own screen. What it carries belongs to the add form and
// to nothing else: a question's label in the track's Name box was renaming the track on the next
// press (the whole-branch review).
ob_start();
WPCPM_Track_Builder_Screen::render_form( array(
	'form'  => $form,
	'url'   => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder',
	'flash' => array( 'status' => 'error', 'message' => 'This column belongs to the syncs.', 'question_values' => array( 'column' => 'Status', 'label' => 'Your "status"', 'type' => 'select', 'group' => 'project' ) ),
) );
$refused_add = ob_get_clean();

ck( 'a refused Add leaves the track\'s name alone, fills its own group\'s boxes again encoded, and leaves the other groups\' empty',
    array(
        false !== strpos( $refused_add, 'id="wpcpm_label" name="wpcpm_label" value="Marketing Track"' ),
        false !== strpos( $refused_add, 'id="wpcpm_add_column_project" name="wpcpm_column" value="Status"' ),
        false !== strpos( $refused_add, 'id="wpcpm_add_label_project" name="wpcpm_label" value="Your &quot;status&quot;"' ),
        substr_count( $refused_add, '<option value="select" selected="selected">One choice of several</option>' ),
        false !== strpos( $refused_add, 'id="wpcpm_add_column_wrapup" name="wpcpm_column" value=""' ),
        substr_count( $refused_add, 'selected="selected"' ),
    ),
    array( true, true, true, 1, true, 1 ) );

// The lock reaches the row as well: a published question cannot fork, so its notice stops at who
// shares the column (the whole-branch review, against decision 23).
WPCPM_Track_Store::$tracks[13]['published'] = array( 'questions' => array( 'Slack name' => array() ) );
$locked_form = WPCPM_Track_Builder::form( 13 );
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => $locked_form, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$locked_list = ob_get_clean();
WPCPM_Track_Store::$tracks[13]['published'] = null;

ck( 'form() carries the published copy\'s columns, and a published question\'s row says who shares it and stops, while an unpublished one still offers the fork',
    array(
        $locked_form['locked'],
        substr_count( $locked_list, 'Shared with 150-hour Track.' ),
        substr_count( $locked_list, 'Shared with 150-hour Track. Rewording keeps the column' ),
        substr_count( $list, 'Shared with 150-hour Track. Rewording keeps the column' ),
    ),
    array( array( 'Slack name' ), 2, 1, 2 ) );

ck( 'and a track never published locks nothing',
    WPCPM_Track_Builder::form( 13 )['locked'], array() );

$read_only_form = WPCPM_Track_Builder::form( 11 );
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => $read_only_form, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$read_only_list = ob_get_clean();

ck( 'a built-in track still shows its questions, with nothing to press and no Add',
    array(
        substr_count( $read_only_list, 'class="wpcpm-question"' ),
        substr_count( $read_only_list, 'Edit</a>' ),
        substr_count( $read_only_list, 'wpcpm-question__mover' ),
        substr_count( $read_only_list, 'wpcpm-questions__add' ),
        false !== strpos( $read_only_list, 'cannot be edited here' ),
    ),
    array( 2, 0, 0, 0, true ) );

$GLOBALS['enqueued'] = array();
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-track-builder' );

ck( 'the screen enqueues its stylesheet and the editor script, the script in the footer',
    $GLOBALS['enqueued'],
    array( array( 'style', 'wpcpm-track-builder', array( 'wpcpm-admin' ) ), array( 'script', 'wpcpm-track-editor', array(), true ) ) );


echo "\n=== One question on a screen of its own ===\n";

WPCPM_Track_Store::$tracks = array(
	13 => editable_track(),
	11 => array(
		'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours' ), 'Slack name' => array( 'type' => 'text', 'label' => 'Slack', 'group' => 'onboarding' ) ) ),
		'state'       => 'published',
		'source'      => 'builtin',
		'log'         => array( array( 'at' => 1788000000, 'by' => 7, 'did' => 'publish' ) ),
		'equivalence' => array(),
		'published'   => array( 'key' => '150h' ),
	),
);
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name - marketing'] = array( 'type' => 'textarea', 'label' => 'At length', 'group' => 'onboarding', 'mono' => true );
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Company ']               = array( 'type' => 'text', 'label' => 'Where you work', 'group' => 'onboarding' );

$one = WPCPM_Track_Builder::question_form( 13, 'Slack name' );

ck( 'question_form() gathers the question, who else writes its column, and that nothing locks it',
    array( $one['track'], $one['label'], $one['key'], $one['column'], $one['question']['label'], array_column( $one['owners'], 'label' ), $one['forked_from'], $one['locked'], $one['read_only'] ),
    array( 13, 'Marketing Track', 'marketing', 'Slack name', 'Your Slack name', array( '150-hour Track' ), '', false, false ) );

ck( 'a fork names what it came from, and is shared with nobody',
    array( WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' )['forked_from'], WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' )['owners'] ),
    array( 'Slack name', array() ) );

ck( 'a column with a trailing space is found exactly, and the trimmed name is not',
    array( is_array( WPCPM_Track_Builder::question_form( 13, 'Company ' ) ), WPCPM_Track_Builder::question_form( 13, 'Company' ) ),
    array( true, null ) );

ck( 'a question that is not on the track, or a track that does not exist, is null',
    array( WPCPM_Track_Builder::question_form( 13, 'Nothing' ), WPCPM_Track_Builder::question_form( 404, 'Hours' ) ),
    array( null, null ) );

WPCPM_Track_Store::$tracks[13]['published'] = array( 'questions' => array( 'Slack name' => array() ) );

ck( 'a question in the published copy is locked, one added since is not',
    array( WPCPM_Track_Builder::question_form( 13, 'Slack name' )['locked'], WPCPM_Track_Builder::question_form( 13, 'Your blog' )['locked'] ),
    array( true, false ) );

WPCPM_Track_Store::$tracks[13]['published'] = null;

$_GET = array( 'wpcpm_track' => 13, 'wpcpm_question' => 'Slack name' );
ob_start();
$tool->render_admin_page();
$routed = ob_get_clean();
$_GET = array( 'wpcpm_track' => 13, 'wpcpm_question' => 'Nothing' );
ob_start();
$tool->render_admin_page();
$fallen = ob_get_clean();
$_GET = array();

ck( 'the screen routes to the question the URL names, and falls back to the track when it names none it has',
    array(
        false !== strpos( $routed, 'class="wpcpm-question-form"' ), false === strpos( $routed, 'Save the track' ),
        false === strpos( $fallen, 'class="wpcpm-question-form"' ), false !== strpos( $fallen, 'Save the track' ),
    ),
    array( true, true, true, true ) );

/**
 * Draw one question's screen.
 *
 * @param array $form  From `question_form()`.
 * @param array $flash What the last press left.
 * @return string
 */
function question_screen( $form, array $flash = array() ) {
	ob_start();
	WPCPM_Track_Editor_Screen::render_question( array( 'form' => $form, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => $flash ) );

	return ob_get_clean();
}

$screen = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name' ) );

ck( 'the way back names the track, and the form posts the save action for this question',
    array(
        false !== strpos( $screen, 'wpcpm_track=13">Back to Marketing Track</a>' ),
        substr_count( $screen, 'name="_wpnonce" value="wpcpm_question_save"' ),
        substr_count( $screen, 'name="action" value="wpcpm_question_save"' ),
        substr_count( $screen, '<input type="hidden" name="track" value="13" />' ),
        substr_count( $screen, '<input type="hidden" name="wpcpm_question" value="Slack name" />' ),
    ),
    array( true, 1, 1, 1, 1 ) );

ck( 'the column is a box and the control a select with the current one chosen, and the sharing notice sits beside them',
    array(
        false !== strpos( $screen, 'id="wpcpm_column" name="wpcpm_column" value="Slack name"' ),
        false !== strpos( $screen, '<option value="text" selected="selected">Text, one line</option>' ),
        substr_count( $screen, 'selected="selected"' ),
        false !== strpos( $screen, 'This column is shared with 150-hour Track.' ),
    ),
    array( true, true, 2, true ) );

ck( 'every property a question has is a row: words, group with its own chosen, help, lead, subheading, note, row, three flags and the developer note',
    array(
        false !== strpos( $screen, 'id="wpcpm_label" name="wpcpm_label" value="Your Slack name"' ),
        false !== strpos( $screen, '<option value="onboarding" selected="selected">Onboarding</option>' ),
        substr_count( $screen, 'name="wpcpm_help"' ) + substr_count( $screen, 'name="wpcpm_lead"' ) + substr_count( $screen, 'name="wpcpm_subgroup"' ) + substr_count( $screen, 'name="wpcpm_note"' ) + substr_count( $screen, 'name="wpcpm_row"' ) + substr_count( $screen, 'name="wpcpm_why"' ),
        substr_count( $screen, 'type="checkbox" id="wpcpm_stack"' ) + substr_count( $screen, 'type="checkbox" id="wpcpm_required"' ) + substr_count( $screen, 'type="checkbox" id="wpcpm_hide_from_institution"' ),
        substr_count( $screen, 'checked="checked"' ),
    ),
    array( true, true, 6, 3, 0 ) );

ck( 'a single-line text box offers its length limit and nothing another control owns',
    array( substr_count( $screen, 'name="wpcpm_maxlength"' ), substr_count( $screen, 'name="wpcpm_min"' ), substr_count( $screen, 'name="wpcpm_options"' ), substr_count( $screen, 'id="wpcpm_mono"' ), false !== strpos( $screen, 'Save the question' ) ),
    array( 1, 0, 0, 0, true ) );

$number = question_screen( WPCPM_Track_Builder::question_form( 13, 'Hours' ) );

ck( 'a number offers its bounds, filled from the question, and no length limit',
    array(
        false !== strpos( $number, 'id="wpcpm_min" name="wpcpm_min" value="0"' ),
        false !== strpos( $number, 'id="wpcpm_max" name="wpcpm_max" value="1000"' ),
        false !== strpos( $number, 'id="wpcpm_step" name="wpcpm_step" value="1"' ),
        substr_count( $number, 'name="wpcpm_maxlength"' ),
    ),
    array( true, true, true, 0 ) );

$mono = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' ) );

ck( 'a text area offers monospace, ticked when set, and says what it forked from',
    array( false !== strpos( $mono, 'type="checkbox" id="wpcpm_mono" name="wpcpm_mono" value="1" checked="checked"' ), false !== strpos( $mono, 'forked from Slack name.' ) ),
    array( true, true ) );

$typed = question_screen(
	WPCPM_Track_Builder::question_form( 13, 'Your blog' ),
	array( 'status' => 'error', 'message' => 'A select needs its choices.', 'question_values' => array( 'column' => 'Your blog', 'type' => 'select', 'label' => 'Your blog', 'group' => 'wrapup', 'options' => array( 'Yes', 'No & maybe' ) ) )
);

ck( 'after a refusal what was typed wins, box by box: the new control\'s rows are drawn, its choices one a line and encoded, the group as typed, and the refusal is shown',
    array(
        false !== strpos( $typed, '<option value="select" selected="selected">One choice of several</option>' ),
        false !== strpos( $typed, "<textarea id=\"wpcpm_options\" name=\"wpcpm_options\" rows=\"6\" class=\"large-text code\">Yes\nNo &amp; maybe</textarea>" ),
        false !== strpos( $typed, '<option value="wrapup" selected="selected">Wrap-up</option>' ),
        false !== strpos( $typed, '<div class="notice notice-error is-dismissible"><p>A select needs its choices.</p></div>' ),
    ),
    array( true, true, true, true ) );

WPCPM_Track_Store::$tracks[13]['definition']['questions']['Your blog']['help']     = 'The address';
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Your blog']['required'] = true;

$cleared = question_screen(
	WPCPM_Track_Builder::question_form( 13, 'Your blog' ),
	array( 'status' => 'error', 'message' => 'A web address is not shaped like that.', 'question_values' => array( 'column' => 'Your blog', 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' ) )
);

ck( 'a refusal redraws a cleared box empty and an unticked flag unticked, rather than restoring what was stored',
    array(
        false !== strpos( $cleared, 'id="wpcpm_help" name="wpcpm_help" value=""' ),
        substr_count( $cleared, 'id="wpcpm_required" name="wpcpm_required" value="1" checked="checked"' ),
    ),
    array( true, 0 ) );

unset( WPCPM_Track_Store::$tracks[13]['definition']['questions']['Your blog']['help'] );
unset( WPCPM_Track_Store::$tracks[13]['definition']['questions']['Your blog']['required'] );

WPCPM_Track_Store::$tracks[13]['definition']['questions']['Personal email'] = array( 'type' => 'email', 'label' => 'An address of your own', 'group' => 'wrapup' );
$email = question_screen( WPCPM_Track_Builder::question_form( 13, 'Personal email' ) );

ck( 'an email question is always kept off the institution, so that box comes ticked',
    false !== strpos( $email, 'id="wpcpm_hide_from_institution" name="wpcpm_hide_from_institution" value="1" checked="checked"' ), true );

WPCPM_Track_Store::$tracks[13]['published'] = array( 'questions' => array( 'Slack name' => array() ) );
$locked = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name' ) );
WPCPM_Track_Store::$tracks[13]['published'] = null;

ck( 'a published question shows its column and control as text, posts them hidden, and says why they are fixed',
    array(
        substr_count( $locked, 'id="wpcpm_column"' ),
        substr_count( $locked, '<select id="wpcpm_type"' ),
        false !== strpos( $locked, '<input type="hidden" name="wpcpm_column" value="Slack name" />' ),
        false !== strpos( $locked, '<input type="hidden" name="wpcpm_type" value="text" />' ),
        false !== strpos( $locked, '<code>Slack name</code>' ),
        false !== strpos( $locked, 'remove it and add a new question with a column of its own' ),
        false !== strpos( $locked, 'id="wpcpm_label" name="wpcpm_label" value="Your Slack name"' ),
    ),
    array( 0, 0, true, true, true, true, true ) );

// The notices take the lock's side: `handle_save()` refuses the fork this one used to offer
// (decision 23, the whole-branch review), and the sentence at the top names the choices too.
ck( 'a published shared question names the tracks that share its column and promises no fork, and its locked sentence names the choices',
    array(
        false !== strpos( $locked, 'its column, its control and its choices are fixed' ),
        false !== strpos( $locked, 'This column is shared with 150-hour Track.' ),
        false !== strpos( $locked, 'Changing the control or the choices gives this track a column of its own' ),
    ),
    array( true, true, false ) );

ck( 'while an unpublished shared question still offers it',
    array(
        false !== strpos( $screen, 'Changing the control or the choices gives this track a column of its own' ),
        false !== strpos( $screen, 'its column, its control and its choices are fixed' ),
    ),
    array( true, false ) );

// A lock can be taken while this screen is open: publish the track in another tab, then save a
// control change. What comes back is the control the question has (the Task 7 review).
WPCPM_Track_Store::$tracks[13]['published'] = array( 'questions' => array( 'Slack name' => array() ) );
$raced = question_screen(
	WPCPM_Track_Builder::question_form( 13, 'Slack name' ),
	array( 'status' => 'error', 'message' => 'This question has been published, so its control and its choices are fixed.', 'question_values' => array( 'column' => 'Slack name', 'type' => 'textarea', 'label' => 'Your Slack name', 'group' => 'onboarding' ) )
);
WPCPM_Track_Store::$tracks[13]['published'] = null;

ck( 'a locked question posts and shows the control it has, not the one a refused press typed',
    array(
        false !== strpos( $raced, '<input type="hidden" name="wpcpm_type" value="text" />' ),
        false !== strpos( $raced, '<strong>Control</strong> Text, one line</p>' ),
        false !== strpos( $raced, 'Text, many lines' ),
    ),
    array( true, true, false ) );

// The choices of a published select are fixed too, so the box is posted but not editable: an empty
// list would be refused for having no choices, which is not what happened (the whole-branch review).
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Tool used'] = array( 'type' => 'select', 'label' => 'Which tool', 'group' => 'project', 'options' => array( 'MAAMP', 'Studio' ) );
WPCPM_Track_Store::$tracks[13]['published']                           = array( 'questions' => array( 'Tool used' => array() ) );
$locked_select = question_screen( WPCPM_Track_Builder::question_form( 13, 'Tool used' ) );
WPCPM_Track_Store::$tracks[13]['published'] = null;
$open_select = question_screen( WPCPM_Track_Builder::question_form( 13, 'Tool used' ) );
unset( WPCPM_Track_Store::$tracks[13]['definition']['questions']['Tool used'] );

ck( 'a published select still posts its choices, in a box that cannot be edited, and says why; an unpublished one is editable',
    array(
        false !== strpos( $locked_select, "<textarea id=\"wpcpm_options\" name=\"wpcpm_options\" rows=\"6\" class=\"large-text code\" readonly=\"readonly\">MAAMP\nStudio</textarea>" ),
        false !== strpos( $locked_select, 'The choices are fixed: this question has been published.' ),
        false !== strpos( $open_select, "<textarea id=\"wpcpm_options\" name=\"wpcpm_options\" rows=\"6\" class=\"large-text code\">MAAMP\nStudio</textarea>" ),
        false !== strpos( $open_select, 'A column that already exists in Airtable must offer every one of these' ),
    ),
    array( true, true, true, true ) );

$read_only = question_screen( WPCPM_Track_Builder::question_form( 11, 'Hours' ) );

ck( 'a built-in track\'s question has no form at all, and points at Duplicate',
    array( substr_count( $read_only, '<form' ), false !== strpos( $read_only, 'Duplicate the track to start one of your own' ) ),
    array( 0, true ) );


echo "\n=== Delete, on the list, for a track that was never published ===\n";

WPCPM_Track_Store::$tracks = array(
	21 => array(
		// An apostrophe as well as the quotes: esc_js() escapes the one and encodes the other, and
		// a label with only quotes cannot tell it from esc_attr() (the whole-branch review).
		'definition'  => array( 'key' => 'never', 'status' => 'Never Track', 'label' => 'Sam\'s "Never" Track', 'course_url' => '', 'questions' => array() ),
		'state'       => 'draft',
		'source'      => 'definition',
		'log'         => array(),
		'equivalence' => array( 'not_builtin' ),
		'published'   => null,
	),
	22 => array(
		'definition'  => array( 'key' => 'once', 'status' => 'Once Track', 'label' => 'Once Track', 'course_url' => '', 'questions' => array() ),
		'state'       => 'draft',
		'source'      => 'definition',
		'log'         => array( array( 'at' => 1788000000, 'by' => 7, 'did' => 'publish' ), array( 'at' => 1788100000, 'by' => 7, 'did' => 'unpublish' ) ),
		'equivalence' => array( 'not_builtin' ),
		'published'   => null,
	),
	23 => array(
		'definition'  => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track', 'course_url' => '', 'questions' => array() ),
		'state'       => 'draft',
		'source'      => 'builtin',
		'log'         => array(),
		'equivalence' => array(),
		'published'   => null,
	),
);
WPCPM_Students_Sync::$counts = array();
$GLOBALS['opts']['wpcpm_tracks_skipped'] = array();

$delete_rows = WPCPM_Track_Builder::rows();

ck( 'each row says whether the track was ever published, from its log rather than its state',
    array_column( $delete_rows, 'ever_published' ), array( false, true, false ) );

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => $delete_rows, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$delete_list = ob_get_clean();

ck( 'Delete is drawn once: for the draft never published, not for the one unpublished since, not for the built-in draft',
    array(
        substr_count( $delete_list, 'class="wpcpm-tracks__delete"' ),
        substr_count( $delete_list, 'name="action" value="wpcpm_track_delete"' ),
        substr_count( $delete_list, 'name="_wpnonce" value="wpcpm_track_delete"' ),
        substr_count( $delete_list, '<input type="hidden" name="track" value="21" />' ),
    ),
    array( 1, 1, 1, 1 ) );

ck( 'its confirmation names the track, encoded for the script it sits in, and says what deleting means',
    false !== strpos( $delete_list, 'onsubmit="return confirm(\'Delete Sam\\\'s &quot;Never&quot; Track? It was never published, so nothing in Airtable or on the live site refers to it. This cannot be undone.\');"' ),
    true );


echo "\n=== The line above the list: what publishing would create, off the cached reading ===\n";

WPCPM_Track_Store::$tracks = array( 13 => editable_track(), 11 => array(
	'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours' ) ) ),
	'state'       => 'published',
	'source'      => 'builtin',
	'log'         => array( array( 'at' => 1788000000, 'by' => 7, 'did' => 'publish' ) ),
	'equivalence' => array(),
	'published'   => array( 'key' => '150h' ),
) );
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Main Contribution Team'] = array( 'type' => 'team', 'label' => 'Your team', 'group' => 'project' );

/** The base as the cached reading holds it: two of the track's columns exist, one of the right type. */
function cached_base( $age ) {
	return array(
		'age'    => $age,
		'schema' => array(
			'tblReports' => array(
				'name'    => 'Students Reports',
				'columns' => array(
					'Hours'      => array( 'type' => 'number', 'options' => array() ),
					'Slack name' => array( 'type' => 'singleLineText', 'options' => array() ),
				),
			),
		),
	);
}

WPCPM_Airtable::$cached = cached_base( 300 );
WPCPM_Airtable::$asked  = 0;
$line = WPCPM_Track_Builder::schema_line( WPCPM_Track_Store::$tracks[13]['definition'] );

ck( 'the columns the base lacks are the ones publishing would create, judged by the class the preflight uses, and the reading\'s age travels with them',
    $line, array( 'create' => array( 'Your blog' ), 'age' => 300 ) );

ck( 'a column no control can create is not counted: the preflight refuses it rather than creating it',
    in_array( 'Main Contribution Team', $line['create'], true ), false );

ck( 'form() carries the reading for a track of somebody\'s own, and asks the client once',
    array( WPCPM_Track_Builder::form( 13 )['schema'], WPCPM_Airtable::$asked ), array( $line, 2 ) );

WPCPM_Airtable::$asked = 0;

ck( 'and does not ask at all for a built-in track, whose columns all exist',
    array( WPCPM_Track_Builder::form( 11 )['schema'], WPCPM_Airtable::$asked ), array( array(), 0 ) );

ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$aged = ob_get_clean();

ck( 'the line says how many columns, how old the reading is, and that the publish screen reads afresh',
    false !== strpos( $aged, '<p class="wpcpm-questions__schema">Publishing will create 1 column in Airtable. <span class="wpcpm-questions__age">Read from Airtable 5 minutes ago; the publish screen reads it afresh.</span></p>' ),
    true );

ck( 'and the row of that column says so, once, on the right row',
    array( substr_count( $aged, 'Publishing will create this column in Airtable.' ), preg_match( '/data-wpcpm-column="Your blog"[^\n]*?Publishing will create this column/', $aged ) ),
    array( 1, 1 ) );

WPCPM_Airtable::$cached = cached_base( 12 );
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$fresh_line = ob_get_clean();

ck( 'a reading under a minute old was read just now',
    false !== strpos( $fresh_line, 'Read from Airtable just now.' ), true );

unset( WPCPM_Track_Store::$tracks[13]['definition']['questions']['Your blog'], WPCPM_Track_Store::$tracks[13]['definition']['questions']['Main Contribution Team'] );
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$nothing_new = ob_get_clean();

ck( 'a track whose columns all exist needs no new columns, and no row says otherwise',
    array( false !== strpos( $nothing_new, 'This track needs no new Airtable columns.' ), substr_count( $nothing_new, 'Publishing will create this column' ) ),
    array( true, 0 ) );

WPCPM_Airtable::$cached = null;
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$unread = ob_get_clean();

ck( 'when the base cannot be read the line is left off rather than guessed, and the list is still drawn',
    array( substr_count( $unread, 'wpcpm-questions__schema' ), substr_count( $unread, 'class="wpcpm-question"' ) ),
    array( 0, 2 ) );


echo "\n=== Unpublishing returns to the publish screen ===\n";

WPCPM_Track_Store::$tracks   = array( 13 => editable_track() );
WPCPM_Track_Publish::$answer = null;
WPCPM_Track_Publish::$down   = array();
$GLOBALS['can_manage']       = true;
$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_UNPUBLISH;
$_POST                       = array( 'track' => 13 );
WPCPM_Flash::$set            = array();
$went = '';

try {
	$tool->handle_unpublish();
} catch ( RedirectSignal $e ) {
	$went = $e->getMessage();
}

ck( 'a track taken down comes back to its own publish screen, where the press came from, not to the list',
    array( $went, WPCPM_Track_Publish::$down, WPCPM_Flash::$set['track-builder']['status'] ?? '' ),
    array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_publish=13', array( array( 13, 5 ) ), 'success' ) );

WPCPM_Track_Publish::$answer = new WP_Error( 'wpcpm_track_in_use', 'Three students hold this status.' );
$went = '';

try {
	$tool->handle_unpublish();
} catch ( RedirectSignal $e ) {
	$went = $e->getMessage();
}

WPCPM_Track_Publish::$answer = null;

ck( 'and so does a refusal, with the reason',
    array( $went, WPCPM_Flash::$set['track-builder']['message'] ?? '' ),
    array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_publish=13', 'Three students hold this status.' ) );


echo "\n=== New track: a status, a key and a label, and an empty form (1.106.0) ===\n";

$GLOBALS['hooks'] = array();
$tool->boot();

ck( 'the handler is on admin-post', in_array( 'admin_post_' . WPCPM_Track_Builder::ACTION_NEW, $GLOBALS['hooks'], true ), true );

/**
 * Press the builder's New track handler.
 *
 * @param array $post What the form posted.
 * @return array `redirect` or `die`, the detail, and the flash.
 */
function press_new( array $post ) {
	global $tool;
	$_POST = $post;
	WPCPM_Flash::$set = array();

	try {
		$tool->handle_new();
	} catch ( RedirectSignal $e ) {
		return array( 'redirect', $e->getMessage(), WPCPM_Flash::$set['track-builder'] ?? array() );
	} catch ( DieSignal $e ) {
		return array( 'die', $e->getMessage() );
	}

	return array( 'fell through' );
}

WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
WPCPM_Track_Store::$tracks[13]['definition']['hue'] = 'pink';
WPCPM_Track_Store::$errors  = array();
WPCPM_Track_Store::$created = array();

$GLOBALS['can_manage'] = false;
$GLOBALS['nonce']      = '';
$refused = press_new( array( 'wpcpm_label' => 'Blank Track' ) );
$GLOBALS['can_manage'] = true;
$nonce_refused = press_new( array( 'wpcpm_label' => 'Blank Track' ) );

ck( 'the capability is checked before the nonce, and then the nonce',
    array( $refused[0], $refused[1], $nonce_refused[0], $nonce_refused[1] ),
    array( 'die', 'You do not have permission to manage the program.', 'die', 'the nonce was refused' ) );

$GLOBALS['nonce'] = WPCPM_Track_Builder::ACTION_NEW;
$made = press_new( array( 'wpcpm_label' => ' Blank Track ', 'wpcpm_status' => 'Blank Track', 'wpcpm_key' => 'blank' ) );

ck( 'a new track is created from the three things it needs, with an empty form and the first hue no track holds',
    WPCPM_Track_Store::$created,
    array( array( 'schema_version' => 1, 'status' => 'Blank Track', 'key' => 'blank', 'label' => 'Blank Track', 'hue' => 'blue', 'questions' => array() ) ) );

ck( 'and the person lands on the new track\'s page, told there are no questions yet',
    array( $made[0], $made[1], $made[2]['status'], false !== strpos( $made[2]['message'], 'no questions yet' ) ),
    array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=98', 'success', true ) );

WPCPM_Track_Store::$created = array();
WPCPM_Track_Store::$errors  = array( array( 'code' => 'status_taken', 'message' => 'Another track already claims that status.' ) );
$taken = press_new( array( 'wpcpm_label' => 'Blank Track', 'wpcpm_status' => 'Marketing Track', 'wpcpm_key' => 'blank' ) );
WPCPM_Track_Store::$errors = array();

ck( 'a status another track holds is refused through check(), nothing is created, and the form comes back with what was typed',
    array( $taken[2]['status'], $taken[2]['message'], $taken[1], $taken[2]['values']['status'], WPCPM_Track_Store::$created ),
    array( 'error', 'Another track already claims that status.', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_new=1', 'Marketing Track', array() ) );

// Every hue the palette has, across seven tracks: the eighth gets the palette's first.
$holding = array_keys( WPCPM_Track_Palette::HUES );
foreach ( $holding as $i => $hue ) {
	WPCPM_Track_Store::$tracks[ 200 + $i ] = array( 'definition' => array( 'hue' => $hue, 'questions' => array() ), 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );
}
WPCPM_Track_Store::$created = array();
press_new( array( 'wpcpm_label' => 'Eighth', 'wpcpm_status' => 'Eighth', 'wpcpm_key' => 'eighth' ) );

ck( 'with every hue taken, the first one, since a chip must have some color',
    array( count( $holding ), WPCPM_Track_Store::$created[0]['hue'] ), array( 7, 'blue' ) );

foreach ( $holding as $i => $hue ) {
	unset( WPCPM_Track_Store::$tracks[ 200 + $i ] );
}

ob_start();
WPCPM_Track_Builder_Screen::render_new( array( 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array( 'status' => 'error', 'message' => 'Another track already claims that status.', 'values' => array( 'label' => 'Blank "Track"', 'status' => 'Marketing Track', 'key' => 'blank' ) ) ) );
$new_form = ob_get_clean();

ck( 'the form asks for the three things, posts the new action with its nonce, and redraws what was typed, encoded',
    array(
        substr_count( $new_form, 'name="action" value="wpcpm_track_new"' ),
        substr_count( $new_form, 'name="_wpnonce" value="wpcpm_track_new"' ),
        false !== strpos( $new_form, 'id="wpcpm_label" name="wpcpm_label" value="Blank &quot;Track&quot;"' ),
        false !== strpos( $new_form, 'id="wpcpm_status" name="wpcpm_status" value="Marketing Track"' ),
        false !== strpos( $new_form, 'id="wpcpm_key" name="wpcpm_key" value="blank"' ),
        false !== strpos( $new_form, 'Create the track' ),
        false !== strpos( $new_form, 'Another track already claims that status.' ),
        substr_count( $new_form, 'name="track"' ),
    ),
    array( 1, 1, true, true, true, true, true, 0 ) );

$_GET = array( 'wpcpm_new' => 1 );
ob_start();
$tool->render_admin_page();
$new_page = ob_get_clean();
$_GET = array();

ck( 'the screen routes to the form, before any track is looked up',
    array( false !== strpos( $new_page, 'name="action" value="wpcpm_track_new"' ), false === strpos( $new_page, '<table class="widefat striped wpcpm-tracks">' ) ),
    array( true, true ) );

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => array(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$empty_list = ob_get_clean();

ck( 'New track is offered above the list, even with no tracks at all',
    array( substr_count( $empty_list, 'wpcpm_new=1">New track</a>' ), false !== strpos( $empty_list, 'No tracks yet' ) ),
    array( 1, true ) );


echo "\n=== Preview: the draft through the report form's own renderer (1.106.0) ===\n";

WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
WPCPM_Track_Store::$tracks[11] = array(
	'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'airtable_type' => 'number' ) ) ),
	'state'       => 'published',
	'source'      => 'builtin',
	'log'         => array(),
	'equivalence' => array(),
	'published'   => array( 'key' => '150h' ),
);

ck( 'the builder hands the view the draft compiled as the live site compiles it: the authoring properties gone, the rest as stored',
    WPCPM_Track_Builder::preview( 13 ),
    array(
        'track'  => 13,
        'label'  => 'Marketing Track',
        'fields' => array(
            'Hours'      => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'min' => 0, 'max' => 1000, 'step' => 1 ),
            'Slack name' => array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding' ),
            'Your blog'  => array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' ),
        ),
        'state'  => 'draft',
        'source' => 'definition',
    ) );

$GLOBALS['enqueued'] = array();
WPCPM_Call_Calendar::$registered = 0;
$_GET = array( 'wpcpm_preview' => 13 );
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-track-builder' );

ck( 'on the preview route the screen also enqueues the sheet that dresses the Student Report Card, registered first',
    array( $GLOBALS['enqueued'][2] ?? null, count( $GLOBALS['enqueued'] ), WPCPM_Call_Calendar::$registered ),
    array( array( 'style', 'wpcpm-call-calendar', array() ), 3, 1 ) );

WPCPM_Student_Report_Form::$previewed = array();
ob_start();
$tool->render_admin_page();
$preview_page = ob_get_clean();

ck( 'the route draws the heading, the two ways back, the sentence that nothing is kept, the state, and the renderer\'s output inside the tokened wrapper',
    array(
        false !== strpos( $preview_page, '<h2>Previewing Marketing Track</h2>' ),
        substr_count( $preview_page, 'wpcpm_track=13">Back to the track</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder">Back to every track</a>' ),
        false !== strpos( $preview_page, 'Nothing typed here is kept: there is no Save button and no form behind the controls. Nothing reaches students until the track is published.' ),
        substr_count( $preview_page, '<div class="wpcpm-dashboard wpcpm-tracks__preview"><div class="wpcpm-report__body wpcpm-report__body--preview">3 fields</div></div>' ),
        WPCPM_Student_Report_Form::$previewed,
        substr_count( $preview_page, '<form' ),
        substr_count( $preview_page, '_wpnonce' ),
        false !== strpos( $preview_page, '<table class="widefat striped wpcpm-tracks">' ),
    ),
    array( true, 1, true, 1, array( array( 'Hours', 'Slack name', 'Your blog' ) ), 0, 0, false ) );

$_GET = array( 'wpcpm_preview' => 11 );
ob_start();
$tool->render_admin_page();
$builtin_preview = ob_get_clean();

ck( 'a built-in track still running from its PHP previews too, and the sentence says its definition is what that form draws',
    array(
        false !== strpos( $builtin_preview, '<h2>Previewing 150-hour Track</h2>' ),
        false !== strpos( $builtin_preview, 'This track runs from its hand-written form, and this definition is what that form draws.' ),
        substr_count( $builtin_preview, '1 fields</div>' ),
    ),
    array( true, true, 1 ) );

foreach ( array( 'published' => 'so this is the form students on the track have', 'changed' => 'this draft has changes they do not see yet', 'trash' => 'This track is in the trash.' ) as $state => $said ) {
	WPCPM_Track_Store::$tracks[13]['state'] = $state;
	$_GET = array( 'wpcpm_preview' => 13 );
	ob_start();
	$tool->render_admin_page();
	$states[ $state ] = false !== strpos( ob_get_clean(), $said );
}
WPCPM_Track_Store::$tracks[13]['state'] = 'draft';

ck( 'and a published, a changed and a trashed track each say what students have against what is drawn',
    $states, array( 'published' => true, 'changed' => true, 'trash' => true ) );

WPCPM_Track_Store::$tracks[13]['definition']['questions'] = array();
WPCPM_Student_Report_Form::$previewed = array();
ob_start();
$tool->render_admin_page();
$empty_preview = ob_get_clean();
WPCPM_Track_Store::$tracks[13] = editable_track();

ck( 'a track with no questions yet says so instead of drawing an empty form',
    array( false !== strpos( $empty_preview, 'This track has no questions yet, so there is nothing to draw.' ), WPCPM_Student_Report_Form::$previewed, substr_count( $empty_preview, 'wpcpm-tracks__preview"' ) ),
    array( true, array(), 0 ) );

$_GET = array( 'wpcpm_preview' => 999 );
ob_start();
$tool->render_admin_page();
$no_such = ob_get_clean();
$_GET = array();

ck( 'a track that does not exist falls through to the list', false !== strpos( $no_such, '<table class="widefat striped wpcpm-tracks">' ), true );

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$rows_html = ob_get_clean();
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$track_page = ob_get_clean();

ck( 'Preview is offered on every row, the built-in one included, and beside the way back on the track\'s page',
    array( substr_count( $rows_html, '>Preview</a>' ), substr_count( $rows_html, 'wpcpm_preview=11">Preview</a>' ), substr_count( $track_page, 'Back to every track</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_preview=13">Preview</a>' ) ),
    array( 2, 1, 1 ) );


echo "\n=== History: what publishing would change, every save against the one before, the log (1.106.0) ===\n";

ck( 'the question screen names every property the definition allows, and the track form every property the diff compares',
    array(
        array_values( array_diff( WPCPM_Track_Definition::QUESTION_PROPERTIES, array_keys( WPCPM_Track_Editor_Screen::property_labels() ) ) ),
        array_values( array_diff( WPCPM_Track_Diff::TRACK, array_keys( WPCPM_Track_Builder_Screen::track_labels() ) ) ),
        WPCPM_Track_Editor_Screen::property_labels()['help'],
    ),
    array( array(), array(), 'Help under the box' ) );

$question_page = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name' ) );

ck( 'and the question screen still draws its rows in those words',
    array( substr_count( $question_page, '>What the student reads</label>' ), substr_count( $question_page, '>Help under the box</label>' ), substr_count( $question_page, '>Control</label>' ) ),
    array( 1, 1, 1 ) );

$first  = array( 'schema_version' => 1, 'key' => 'marketing', 'status' => 'Marketing Track', 'label' => 'Marketing Track', 'hours_target' => 100, 'hue' => 'pink', 'questions' => array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours' ) ) );
$second = $first;
$second['questions']['Slack name'] = array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding' );
$third  = $second;
$third['hours_target'] = 120;
$third['questions']['Slack name']['help'] = 'Without the @.';
$third['questions']['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' );

WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
WPCPM_Track_Store::$tracks[13]['definition'] = $third;
WPCPM_Track_Store::$tracks[13]['state']      = 'changed';
WPCPM_Track_Store::$tracks[13]['published']  = $second;
WPCPM_Track_Store::$tracks[13]['revisions']  = array(
	array( 'id' => 103, 'at' => 1788200000, 'by' => 7, 'definition' => $third ),
	array( 'id' => 102, 'at' => 1788100000, 'by' => 7, 'definition' => $second ),
	array( 'id' => 101, 'at' => 1788000000, 'by' => 9, 'definition' => $first ),
);
WPCPM_Track_Store::$tracks[13]['log'] = array(
	array( 'at' => 1788050000, 'by' => 7, 'did' => 'publish' ),
	array( 'at' => 1788060000, 'by' => 7, 'did' => 'columns', 'detail' => array( 'columns' => array( 'Slack name' ) ) ),
	array( 'at' => 1788070000, 'by' => 7, 'did' => 'tick-welcome' ),
	array( 'at' => 1788080000, 'by' => 7, 'did' => 'untick-nothing' ),
);
$GLOBALS['users'] = array( 7 => 'A Manager' );

$history = WPCPM_Track_Builder::history( 13 );

ck( 'the builder hands the view the published copy against the draft, each save against the one before, "created" on the first, and the log newest first with the checklist labels for its ticks',
    array(
        $history['track'],
        $history['label'],
        array( $history['pending']['track'], $history['pending']['added'], $history['pending']['changed'], $history['pending']['same'] ),
        array( $history['revisions'][0]['at'], $history['revisions'][0]['by'], $history['revisions'][0]['diff']['track'], $history['revisions'][0]['diff']['added'], $history['revisions'][0]['diff']['changed'], $history['revisions'][0]['created'] ),
        array( $history['revisions'][1]['diff']['added'], $history['revisions'][1]['diff']['changed'], $history['revisions'][1]['created'] ),
        array( $history['revisions'][2]['by'], $history['revisions'][2]['diff'], $history['revisions'][2]['created'] ),
        $history['more'],
        array_column( $history['log'], 'did' ),
        $history['items']['welcome'],
    ),
    array(
        13,
        'Marketing Track',
        array( array( 'hours_target' ), array( 'Your blog' ), array( 'Slack name' => array( 'help' ) ), false ),
        array( 1788200000, 7, array( 'hours_target' ), array( 'Your blog' ), array( 'Slack name' => array( 'help' ) ), null ),
        array( array( 'Slack name' ), array(), null ),
        array( 9, null, 1 ),
        false,
        array( 'untick-nothing', 'tick-welcome', 'columns', 'publish' ),
        'Create the welcome email automation',
    ) );

$_GET = array( 'wpcpm_history' => 13 );
ob_start();
$tool->render_admin_page();
$history_page = ob_get_clean();
$_GET = array();

ck( 'the route draws the three parts in order, the diffs in the screens\' own words, the columns as code, and who did what when',
    array(
        false !== strpos( $history_page, '<h2>History of Marketing Track</h2>' ),
        substr_count( $history_page, 'wpcpm_track=13">Back to the track</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder">Back to every track</a>' ),
        strpos( $history_page, '<h3>What publishing would change</h3>' ) < strpos( $history_page, '<h3>Saves</h3>' ) && strpos( $history_page, '<h3>Saves</h3>' ) < strpos( $history_page, '<h3>Publish log</h3>' ),
        substr_count( $history_page, '<li>Track: Hours target</li>' ),
        substr_count( $history_page, '<li>Added: <code>Your blog</code></li>' ),
        substr_count( $history_page, '<li><code>Slack name</code>: Help under the box</li>' ),
        substr_count( $history_page, '<li>Added: <code>Slack name</code></li>' ),
        substr_count( $history_page, '<p class="wpcpm-history__meta">' . gmdate( 'Y-m-d H:i', 1788000000 ) . ' by somebody since removed</p><p>Created, with 1 question.</p>' ),
        substr_count( $history_page, '<p class="wpcpm-history__meta">' . gmdate( 'Y-m-d H:i', 1788200000 ) . ' by A Manager</p>' ),
        substr_count( $history_page, '<li>Published, ' . gmdate( 'Y-m-d H:i', 1788050000 ) . ' by A Manager</li>' ),
        substr_count( $history_page, '<li>Created in Airtable: Slack name, ' ),
        substr_count( $history_page, '<li>Ticked &quot;Create the welcome email automation&quot;, ' ),
        substr_count( $history_page, '<li>Unticked &quot;nothing&quot;, ' ),
        strpos( $history_page, 'Unticked' ) < strpos( $history_page, '<li>Published, ' ),
        false !== strpos( $history_page, 'Older saves exist' ),
        substr_count( $history_page, '<form' ),
    ),
    array( true, 1, true, 2, 2, 2, 1, 1, 1, 1, 1, 1, 1, true, false, 0 ) );

WPCPM_Track_Store::$tracks[13]['published'] = null;
WPCPM_Track_Store::$tracks[13]['log']       = array();
$_GET = array( 'wpcpm_history' => 13 );
ob_start();
$tool->render_admin_page();
$never = ob_get_clean();
WPCPM_Track_Store::$tracks[13]['published'] = $third;
ob_start();
$tool->render_admin_page();
$same = ob_get_clean();
$_GET = array();

ck( 'a track never published says so in place of the top diff, and so does one whose published copy is the draft; an empty log says when it starts',
    array(
        false !== strpos( $never, 'This track has never been published, so there is no published copy to compare the draft with.' ),
        false !== strpos( $never, 'Nothing yet: the log starts when the track is first published.' ),
        false !== strpos( $same, 'The published copy is the same as the draft: publishing would change nothing.' ),
        substr_count( $same, '<li>Track: Hours target</li>' ),
    ),
    array( true, true, true, 1 ) );

$many = array();
for ( $i = 21; $i >= 1; $i-- ) {
	$copy = $first;
	$copy['hours_target'] = $i;
	$many[] = array( 'id' => 200 + $i, 'at' => 1788000000 + $i, 'by' => 7, 'definition' => $copy );
}
WPCPM_Track_Store::$tracks[13]['revisions'] = $many;
$capped = WPCPM_Track_Builder::history( 13 );
WPCPM_Track_Store::$tracks[13]['revisions'] = array_slice( $many, 0, 20 );
$exact = WPCPM_Track_Builder::history( 13 );
WPCPM_Track_Store::$tracks[13]['revisions'] = array();
$_GET = array( 'wpcpm_history' => 13 );
ob_start();
$tool->render_admin_page();
$no_saves = ob_get_clean();
$_GET = array();

ck( 'twenty saves are shown of more, the oldest shown against the one before it rather than as "created"; exactly twenty are shown whole; none at all says why',
    array(
        count( $capped['revisions'] ), $capped['more'], $capped['revisions'][19]['diff']['track'], $capped['revisions'][19]['created'],
        count( $exact['revisions'] ), $exact['more'], $exact['revisions'][19]['diff'], $exact['revisions'][19]['created'],
        false !== strpos( $no_saves, 'No saves have been kept for this track. WordPress keeps a copy of each save only while revisions are on.' ),
    ),
    array( 20, true, array( 'hours_target' ), null, 20, false, null, 1, true ) );

// WordPress prunes a track's revisions to the site's cap at every save, so a list standing at the
// cap has no way of knowing that its oldest entry is the creation (the final review of T3b).
WPCPM_Track_Store::$tracks[13]['cap']       = 3;
WPCPM_Track_Store::$tracks[13]['revisions'] = array_slice( $many, 0, 3 );
$at_cap = WPCPM_Track_Builder::history( 13 );
$_GET   = array( 'wpcpm_history' => 13 );
ob_start();
$tool->render_admin_page();
$at_cap_page = ob_get_clean();
$_GET = array();

ck( 'a list standing at the site\'s cap is marked pruned, its oldest entry with it, and that entry still counts the questions it held',
    array( $at_cap['pruned'], $at_cap['cap'], count( $at_cap['revisions'] ), $at_cap['revisions'][2]['pruned'], $at_cap['revisions'][2]['created'], $at_cap['revisions'][0]['pruned'] ),
    array( true, 3, 3, true, 1, false ) );

ck( 'and the page calls that save the oldest kept rather than the creation, and says what the site discarded',
    array(
        substr_count( $at_cap_page, '<p>The oldest save kept, with 1 question. The saves before it were not kept.</p>' ),
        substr_count( $at_cap_page, '<p>This site keeps the last 3 saves of a track; earlier ones were discarded.</p>' ),
        false !== strpos( $at_cap_page, 'Created, with' ),
        false !== strpos( $at_cap_page, 'Older saves exist' ),
    ),
    array( 1, 1, false, false ) );

WPCPM_Track_Store::$tracks[13]['revisions'] = array_slice( $many, 0, 2 );
$under_cap = WPCPM_Track_Builder::history( 13 );
$_GET      = array( 'wpcpm_history' => 13 );
ob_start();
$tool->render_admin_page();
$under_cap_page = ob_get_clean();
$_GET = array();

ck( 'fewer saves kept than the cap means nothing was pruned away beneath them, so the oldest is the creation again',
    array( $under_cap['pruned'], $under_cap['revisions'][1]['pruned'], substr_count( $under_cap_page, '<p>Created, with 1 question.</p>' ), false !== strpos( $under_cap_page, 'This site keeps the last' ) ),
    array( false, false, 1, false ) );

unset( WPCPM_Track_Store::$tracks[13]['cap'] );
WPCPM_Track_Store::$tracks[13]['revisions'] = array_slice( $many, 0, 3 );
$no_cap = WPCPM_Track_Builder::history( 13 );
$_GET   = array( 'wpcpm_history' => 13 );
ob_start();
$tool->render_admin_page();
$no_cap_page = ob_get_clean();
$_GET = array();

ck( 'and a site that caps nothing reads as it did before the cap was asked for',
    array( $no_cap['cap'], $no_cap['pruned'], $no_cap['revisions'][2]['pruned'], substr_count( $no_cap_page, '<p>Created, with 1 question.</p>' ), false !== strpos( $no_cap_page, 'This site keeps the last' ) ),
    array( -1, false, false, 1, false ) );

WPCPM_Track_Store::$tracks[13]['revisions'] = array(
	array( 'id' => 103, 'at' => 1788200000, 'by' => 7, 'definition' => $third ),
	array( 'id' => 102, 'at' => 1788100000, 'by' => 7, 'definition' => null ),
);
$gap = WPCPM_Track_Builder::history( 13 );

ck( 'a save that kept no definition is handed over with neither a diff nor a count, and the one after it is compared with nothing',
    array( $gap['revisions'][1]['diff'], $gap['revisions'][1]['created'], $gap['revisions'][0]['diff']['added'] ),
    array( null, null, array( 'Hours', 'Slack name', 'Your blog' ) ) );

WPCPM_Track_Store::$tracks = array( 13 => editable_track() );

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$rows_html = ob_get_clean();
ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$track_page = ob_get_clean();

ck( 'History is offered on every row and beside Preview on the track\'s page',
    array( substr_count( $rows_html, 'wpcpm_history=13">History</a>' ), substr_count( $track_page, 'wpcpm_preview=13">Preview</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_history=13">History</a>' ) ),
    array( 1, 1 ) );


echo "\n=== The words on a built-in row and on its publish screen (decision 29) ===\n";

WPCPM_Track_Store::$tracks = array(
	12 => array(
		'definition'  => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track', 'course_url' => '', 'questions' => array( 'B' => array( 'type' => 'text', 'label' => 'B', 'group' => 'project' ) ) ),
		'state'       => 'draft',
		'source'      => 'builtin',
		'log'         => array(),
		'equivalence' => array( 'not_published' ),
		'published'   => null,
	),
	13 => editable_track(),
);
WPCPM_Students_Sync::$counts             = array( 'Designer Track' => 458, 'Marketing Track' => 0 );
$GLOBALS['opts']['wpcpm_tracks_skipped'] = array();

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$words = ob_get_clean();

ck( 'a built-in track still on its PHP reads live rather than Draft, says what publishing its definition does, and offers Publish definition; a draft of somebody\'s own still reads Draft and offers Publish',
    array(
        substr_count( $words, '<td>Live, from its hand-written form' ),
        substr_count( $words, '<td>Draft' ),
        substr_count( $words, 'Its definition is not published yet. Publishing it changes nothing for students: it records the definition, so that the track can switch to running from it once the two are identical.' ),
        substr_count( $words, '>Publish definition</a>' ),
        substr_count( $words, '>Publish</a>' ),
        substr_count( $words, '<td>458</td>' ),
    ),
    array( 1, 1, 1, 1, 1, 1 ) );

WPCPM_Track_Publish::$flight    = array( 'refusals' => array(), 'warnings' => array(), 'columns' => array( 'create' => array(), 'ready' => array( 'B' ) ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ), 'fields' => array( 'now' => 120, 'after' => 120 ), 'adds_status' => false, 'ready' => true );
WPCPM_Track_Publish::$checklist = array();

$_GET = array( 'wpcpm_publish' => 12 );
ob_start();
$tool->render_admin_page();
$builtin_publish = ob_get_clean();
WPCPM_Track_Store::$tracks[12]['state'] = 'published';
ob_start();
$tool->render_admin_page();
$builtin_live = ob_get_clean();
$_GET = array( 'wpcpm_publish' => 13 );
ob_start();
$tool->render_admin_page();
$own_publish = ob_get_clean();
$_GET = array();

ck( 'its publish screen is headed as the definition\'s, says the track keeps running from its form, and its buttons name the definition; a track of somebody\'s own keeps its words',
    array(
        false !== strpos( $builtin_publish, '<h2>Publishing the definition of Designer Track</h2>' ),
        false !== strpos( $builtin_publish, 'Publishing records its definition and changes nothing for students' ),
        substr_count( $builtin_publish, 'Publish the definition' ),
        substr_count( $builtin_publish, 'Publish this track' ),
        substr_count( $builtin_live, 'Unpublish the definition' ),
        substr_count( $builtin_live, 'Take it off the live site' ),
        substr_count( $builtin_live, 'Check it against Airtable' ),
        false !== strpos( $own_publish, '<h2>Publishing Marketing Track</h2>' ),
        substr_count( $own_publish, 'Publish this track' ),
        false !== strpos( $own_publish, 'keeps doing so' ),
    ),
    array( true, true, 1, 0, 1, 0, 1, true, 1, false ) );


echo "\n=== The editor's fold-ins: a locked question's rows and its notice, and every control through the real validator (decision 29) ===\n";

WPCPM_Track_Store::$tracks = array(
	13 => editable_track(),
	11 => array(
		'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Slack name' => array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding' ) ) ),
		'state'       => 'published',
		'source'      => 'builtin',
		'log'         => array(),
		'equivalence' => array(),
		'published'   => array( 'key' => '150h' ),
	),
);
unset( WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name'] );
WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name - marketing'] = array( 'type' => 'textarea', 'label' => 'Your Slack name', 'group' => 'onboarding', 'mono' => true );
WPCPM_Track_Store::$tracks[13]['state']     = 'published';
WPCPM_Track_Store::$tracks[13]['published'] = WPCPM_Track_Store::$tracks[13]['definition'];

$raced = question_screen(
	WPCPM_Track_Builder::question_form( 13, 'Hours' ),
	array( 'status' => 'error', 'message' => 'This question has been published, so its control and its choices are fixed.', 'question_values' => array( 'column' => 'Hours', 'type' => 'text', 'label' => 'Hours', 'group' => 'hours', 'maxlength' => '5' ) )
);

ck( 'a locked question\'s rows follow its stored control after a lock race: the number\'s bounds, not the typed text box\'s length limit, with the stored control posted back',
    array( substr_count( $raced, 'name="wpcpm_min"' ), substr_count( $raced, 'name="wpcpm_maxlength"' ), substr_count( $raced, 'name="wpcpm_type" value="number"' ), false !== strpos( $raced, 'its control and its choices are fixed' ) ),
    array( 1, 0, 1, true ) );

ck( 'and those rows carry the stored control\'s own bounds, not blanks: the typed press could not have carried a number\'s properties',
    array(
        false !== strpos( $raced, 'id="wpcpm_min" name="wpcpm_min" value="0"' ),
        false !== strpos( $raced, 'id="wpcpm_max" name="wpcpm_max" value="1000"' ),
        false !== strpos( $raced, 'id="wpcpm_step" name="wpcpm_step" value="1"' ),
    ),
    array( true, true, true ) );

$raced_mono = question_screen(
	WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' ),
	array( 'status' => 'error', 'message' => 'This question has been published, so its control and its choices are fixed.', 'question_values' => array( 'column' => 'Slack name - marketing', 'type' => 'number', 'label' => 'Your Slack name', 'group' => 'onboarding', 'min' => '1', 'max' => '2', 'step' => '1' ) )
);

ck( 'the mirror case: a stored text area raced by a typed number draws its stored mono tick and no bounds, with the stored control posted back',
    array(
        false !== strpos( $raced_mono, 'type="checkbox" id="wpcpm_mono" name="wpcpm_mono" value="1" checked="checked"' ),
        substr_count( $raced_mono, 'name="wpcpm_min"' ),
        false !== strpos( $raced_mono, 'name="wpcpm_type" value="textarea"' ),
    ),
    array( true, 0, true ) );

$forked_locked = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' ) );
WPCPM_Track_Store::$tracks[13]['state']     = 'draft';
WPCPM_Track_Store::$tracks[13]['published'] = null;
$forked_free = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' ) );

ck( 'the forked-from notice on a locked question no longer promises a rename, and on a draft still does',
    array(
        false !== strpos( $forked_locked, 'forked from Slack name.</p>' ),
        false !== strpos( $forked_locked, 'can still be changed' ),
        false !== strpos( $forked_free, 'forked from Slack name. Its name can still be changed until the track is published.</p>' ),
    ),
    array( true, false, true ) );

$posts = array(
	'text'     => array( 'wpcpm_maxlength' => '80' ),
	'textarea' => array( 'wpcpm_mono' => '1' ),
	'richtext' => array(),
	'url'      => array(),
	'email'    => array(),
	'number'   => array( 'wpcpm_min' => '0', 'wpcpm_max' => '10', 'wpcpm_step' => '0.5' ),
	'checkbox' => array(),
	'select'   => array( 'wpcpm_options' => "Yes\nNo" ),
	'image'    => array(),
	'team'     => array(),
);
$verdicts = array();

foreach ( $posts as $control => $extra ) {
	$_POST    = array_merge( array( 'wpcpm_type' => $control, 'wpcpm_label' => 'Words', 'wpcpm_group' => 'project', 'wpcpm_help' => 'Help', 'wpcpm_row' => 'pair', 'wpcpm_stack' => '1', 'wpcpm_required' => '1' ), $extra );
	$column   = 'team' === $control ? WPCPM_Track_Definition::TEAM_COLUMN : 'Column ' . $control;
	$question = WPCPM_Track_Editor::posted_question( array() );
	$errors   = WPCPM_Track_Definition::validate( array( 'schema_version' => 1, 'status' => 'Marketing Track', 'key' => 'marketing', 'label' => 'Marketing Track', 'hue' => 'blue', 'questions' => array( $column => $question ) ) );

	$verdicts[ $control ] = array( array_column( $errors, 'code' ), isset( $question['airtable_type'] ) );
}

$_POST = array();

ck( 'what posted_question() builds for each of the ten controls is a question the real validator accepts, each carrying its Airtable type',
    $verdicts, array_fill_keys( array_keys( $posts ), array( array(), true ) ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
