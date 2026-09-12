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
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '" />'; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['can_manage'] ); }
function check_admin_referer( $action ) { if ( ( $GLOBALS['nonce'] ?? '' ) !== $action ) { throw new DieSignal( 'the nonce was refused' ); } return true; }
function wp_safe_redirect( $url ) { throw new RedirectSignal( (string) $url ); }
function wp_die( $message = '', $title = '', $args = array() ) { throw new DieSignal( is_string( $message ) ? $message : '' ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; return true; }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ (int) $id ] ) ? (object) array( 'display_name' => $GLOBALS['users'][ (int) $id ] ) : false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function wp_enqueue_style( $handle, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = array( 'style', $handle, $deps ); }

class RedirectSignal extends Exception {}
class DieSignal extends Exception {}

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
	public static function posted_text( $key ) { return isset( $_POST[ $key ] ) ? trim( (string) $_POST[ $key ] ) : ''; }
}

class WPCPM_Track_Palette {
	const HUES = array( 'pink', 'blue', 'green' );

	public static function is_hue( $hue ) { return in_array( $hue, self::HUES, true ); }
}

$GLOBALS['can_manage'] = true;
$GLOBALS['nonce']      = '';
$GLOBALS['hooks']      = array();
$GLOBALS['opts']     = array();
$GLOBALS['users']    = array( 7 => 'A Manager' );
$GLOBALS['enqueued'] = array();

/** The store, as the screen uses it: definitions, states, logs, equivalence and the seeds. */
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

	public static $refreshed  = array();
	public static $duplicated = array();
	public static $saved      = array();

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

require_once __DIR__ . '/../includes/tools/class-wpcpm-tool.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-builder-screen.php';
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

/** Run a handler and say how it ended: a redirect, or the message it died with. */
function outcome( callable $handler ) {
	try {
		$handler();
	} catch ( RedirectSignal $e ) {
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
ck( 'its stylesheet builds on the plugin\'s admin sheet, on its own screen', $GLOBALS['enqueued'], array( array( 'style', 'wpcpm-track-builder', array( 'wpcpm-admin' ) ) ) );
$GLOBALS['enqueued'] = array();
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-settings' );
ck( 'and on no other', $GLOBALS['enqueued'], array() );

echo "\n=== The properties form ===\n";

// The questions are T3's; this edits what a track is, not what it asks. A built-in track its PHP
// still runs is read-only here, because its equivalence with that PHP is what the switch rests on
// (spec section 6), and the store refuses the save in any case.
ck( 'the form offers the track properties, and nothing about its questions',
    array_keys( WPCPM_Track_Builder::form( 13 ) ),
    array( 'id', 'label', 'status', 'key', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'read_only' ) );
ck( 'filled from the definition', array( WPCPM_Track_Builder::form( 13 )['label'], WPCPM_Track_Builder::form( 13 )['status'], WPCPM_Track_Builder::form( 13 )['read_only'] ), array( 'Marketing Track', 'Marketing Track', false ) );
ck( 'and a built-in track its PHP runs is read-only', WPCPM_Track_Builder::form( 11 )['read_only'], true );

ob_start();
WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
$form = ob_get_clean();
ck( 'the form posts to the save action with a field per property',
    array( substr_count( $form, 'name="action" value="wpcpm_track_save"' ), substr_count( $form, 'name="wpcpm_label"' ), substr_count( $form, 'name="wpcpm_status"' ), substr_count( $form, 'name="wpcpm_key"' ), substr_count( $form, 'name="wpcpm_hours_target"' ) ),
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

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
