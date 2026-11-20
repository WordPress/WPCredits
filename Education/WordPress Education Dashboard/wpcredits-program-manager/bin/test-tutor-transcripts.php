<?php
/**
 * The AI Mentor transcripts view of the Administrator Dashboard (AI Mentor R3, Task 6).
 *
 * What is pinned here and why:
 *
 * - **The view is a view of the frame**, asked for by `?wpcpm_view=ai-mentor`, which the Student Report Card's manager
 *   line links to with `wpcpm_tutor_record`.
 * - **The menu item shows while there is something to read**: a listed login or a kept turn.
 * - **The list** has a row an account with turns in 90 days, newest first, and a line for none.
 * - **A transcript** is one account's rows in order, all three roles, a tool call with its name and its arguments
 *   and result as escaped JSON, a reply's sources as links to http and https addresses only.
 * - **Opening a transcript is itself recorded**: one audit row each time, holding the record ID and the reader and
 *   never a word of the conversation, and the last five readers are shown. A malformed record ID, a record with no
 *   rows and a viewer who is no Administrator draw no transcript and write nothing.
 * - **The audit row is on its own key**: no institution, sponsor, lead or call reader lists it.
 *
 * The audit log, the transcript store (on the SQLite stand-in) and the shared parts are the real classes.
 *
 * Run from the plugin root:  php bin/test-tutor-transcripts.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']  = array( 'date_format' => 'j F Y', 'time_format' => 'H:i' );
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['next']  = 100;
$GLOBALS['uid']   = 11;
$GLOBALS['can']   = true;
$GLOBALS['logins'] = array();
$GLOBALS['styles'] = array();
$GLOBALS['names']  = array(
	11 => 'Administrator A',
	12 => 'Administrator B',
	13 => 'Administrator C',
	14 => 'Administrator D',
	15 => 'Administrator E',
	16 => 'Administrator F',
	17 => 'Administrator G',
);

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_Post {
	public $ID; public $post_type; public $post_status; public $post_author; public $post_content; public $post_title; public $post_date_gmt;
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
}
class WPCPM_Administrators_Dashboard {
	const STYLE = 'wpcpm-administrator';
	public static function page_name() { return '<p class="wpcpm-head__eyebrow">Administrator Dashboard</p>'; }
	public static function page_url() { return 'https://example.test/administrators/'; }
	public static function view_url( $view = 'overview' ) { return 'overview' === $view ? self::page_url() : self::page_url() . '?wpcpm_view=' . $view; }
}
class WPCPM_Tutor {
	public static function accounts() { return $GLOBALS['logins']; }
}

function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s, $protocols = null ) {
	$s = (string) $s;
	$ok = null === $protocols ? array( 'http', 'https' ) : $protocols;
	return 1 === preg_match( '#^(' . implode( '|', $ok ) . ')://#i', $s ) ? htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ) : '';
}
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function number_format_i18n( $n ) { return (string) $n; }
function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function current_user_can( $cap ) { return WPCPM_Roles::CAP_MANAGE === $cap && $GLOBALS['can']; }
function get_current_user_id() { return $GLOBALS['uid']; }
function get_userdata( $id ) { return isset( $GLOBALS['names'][ $id ] ) ? (object) array( 'ID' => $id, 'display_name' => $GLOBALS['names'][ $id ] ) : false; }
function wp_style_is( $h, $s = 'enqueued' ) { return isset( $GLOBALS['styles'][ $h ] ); }
function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $h ] = array( $src, $deps ); }
function add_action( $h, $cb, $p = 10, $n = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return true; }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_event( $t, $r, $h ) { return true; }
function wp_clear_scheduled_hook( $h ) { return 1; }
function register_post_type() {}
function dbDelta( $sql ) { $GLOBALS['wpdb']->create_tutor_turns(); return array(); }

function wpcpm_test_when( $post ) { return ! empty( $post->post_date_gmt ) ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : 1757000000 + 60 * (int) $post->ID; }
function get_post_time( $f, $gmt, $post, $translate = false ) { return wpcpm_test_when( $post ); }
function wp_insert_post( array $a, $wp_error = false ) {
	if ( ! empty( $GLOBALS['fail_insert'] ) ) { return new WP_Error( 'db', 'down' ); }
	$id = $GLOBALS['next']++;
	$a = stripslashes_deep( $a );
	$a['ID'] = $id;
	$GLOBALS['posts'][ $id ] = new WP_Post( $a );
	return $id;
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ $id ][ $k ] = stripslashes_deep( $v ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['pmeta'][ $id ][ $k ] ) ? $GLOBALS['pmeta'][ $id ][ $k ] : ''; }
function get_posts( array $args ) {
	$out = array();
	$ids = array_keys( $GLOBALS['posts'] );
	usort( $ids, function ( $a, $b ) {
		return array( wpcpm_test_when( $GLOBALS['posts'][ $b ] ), $b ) <=> array( wpcpm_test_when( $GLOBALS['posts'][ $a ] ), $a );
	} );
	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = isset( $GLOBALS['pmeta'][ $id ][ $clause['key'] ] );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { $ok = $ok && $have; continue; }
			$ok = $ok && $have && 0 === strcasecmp( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], (string) $clause['value'] );
		}
		if ( $ok ) { $out[] = $post; }
		if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 && count( $out ) >= $args['numberposts'] ) { break; }
	}
	return $out;
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-tutor-store.php';
require WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-tutor-transcripts-view.php';

$fails = 0;
$total = 0;
function ck( $label, $got, $want ) {
	global $fails, $total;
	++$total;
	if ( $got === $want ) { printf( "ok   %s\n", $label ); return; }
	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
function has( $h, $n ) { return false !== strpos( (string) $h, (string) $n ); }
function age( $id, $created ) { $GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'UPDATE wp_wpcpm_tutor_turns SET created_gmt = %s WHERE id = %d', $created, $id ) ); }
function ago( $seconds ) { return gmdate( 'Y-m-d H:i:s', time() - $seconds ); }
function fresh_table() {
	$GLOBALS['wpdb']->drop_tutor_turns();
	$GLOBALS['wpdb']->create_tutor_turns();
	WPCPM_Tutor_Store::reset();
}
function draw( $record = null ) {
	if ( null === $record ) { unset( $_GET['wpcpm_tutor_record'] ); } else { $_GET['wpcpm_tutor_record'] = $record; }
	ob_start();
	WPCPM_Tutor_Transcripts_View::render_view( WPCPM_Administrators_Dashboard::page_name() );
	return (string) ob_get_clean();
}
function reads() { return WPCPM_Institution_Audit::tutor_reads( 'recSTUDENT0000001', 0 ); }
function all_audit_rows() { return count( $GLOBALS['posts'] ); }

$A = 'recSTUDENT0000001';
$B = 'recSTUDENT0000002';

echo "=== Names and the request ===\n";

ck( 'the names', array( WPCPM_Tutor_Transcripts_View::ARG, WPCPM_Tutor_Transcripts_View::VIEW, WPCPM_Tutor_Transcripts_View::RECORD_ARG, WPCPM_Tutor_Transcripts_View::STYLE ), array( 'wpcpm_view', 'ai-mentor', 'wpcpm_tutor_record', 'wpcpm-tutor-transcripts' ) );
unset( $_GET['wpcpm_view'] );
ck( 'no view asked: not requested', WPCPM_Tutor_Transcripts_View::is_requested(), false );
$_GET['wpcpm_view'] = 'cohort-timeline';
ck( 'another view asked: not requested', WPCPM_Tutor_Transcripts_View::is_requested(), false );
$_GET['wpcpm_view'] = 'ai-mentor';
ck( 'wpcpm_view=ai-mentor is requested', WPCPM_Tutor_Transcripts_View::is_requested(), true );
ck( 'its address is the page\'s with the argument', WPCPM_Tutor_Transcripts_View::url(), 'https://example.test/administrators/?wpcpm_view=ai-mentor' );
ck( 'a transcript\'s address adds the record', WPCPM_Tutor_Transcripts_View::url( $A ), 'https://example.test/administrators/?wpcpm_view=ai-mentor&wpcpm_tutor_record=' . $A );
WPCPM_Tutor_Transcripts_View::register_assets();
WPCPM_Tutor_Transcripts_View::register_assets();
ck( 'the stylesheet is registered once, over the dashboard\'s', $GLOBALS['styles'], array( 'wpcpm-tutor-transcripts' => array( WPCPM_PLUGIN_URL . 'assets/css/tutor-transcripts.css', array( 'wpcpm-administrator' ) ) ) );

echo "\n=== When the menu item shows ===\n";

fresh_table();
$GLOBALS['logins'] = array();
ck( 'no listed login and no turn: not shown', WPCPM_Tutor_Transcripts_View::shown(), false );
$GLOBALS['logins'] = array( 'student-a' );
ck( 'a listed login: shown', WPCPM_Tutor_Transcripts_View::shown(), true );
$GLOBALS['logins'] = array();
WPCPM_Tutor_Store::add( 7, $A, 'student', 'Hello' );
ck( 'a kept turn, with no listed login: shown', WPCPM_Tutor_Transcripts_View::shown(), true );
$GLOBALS['wpdb']->drop_tutor_turns();
WPCPM_Tutor_Store::reset();
ck( 'no table and no login: not shown', WPCPM_Tutor_Transcripts_View::shown(), false );

echo "\n=== The list ===\n";

fresh_table();
$list = draw();
ck( 'with nothing kept: the empty state', has( $list, 'No AI Mentor conversations in the last 90 days.' ), true );
ck( 'and no table', has( $list, '<table' ), false );

$a1 = WPCPM_Tutor_Store::add( 7, $A, 'student', 'What is a pull request?' );
$a2 = WPCPM_Tutor_Store::add( 7, $A, 'tool', wp_json_encode( array( 'args' => array( 'topic' => 'pull request' ), 'result' => array( 'steps' => 2, 'note' => '<b>two</b>' ) ) ), array(), 'find_steps' );
$a3 = WPCPM_Tutor_Store::add( 7, $A, 'mentor_ai', "It is a proposal to merge code.\nSecond line <script>alert(1)</script>", array(
	array( 'title' => 'Guide A', 'link' => 'https://example.test/guide-a' ),
	array( 'title' => 'Guide B', 'link' => 'http://example.test/guide-b' ),
	array( 'title' => 'Bad one', 'link' => 'javascript:alert(1)' ),
	array( 'title' => 'Data one', 'link' => 'data:text/html,x' ),
) );
$a4 = WPCPM_Tutor_Store::add( 7, $A, 'student', 'Thanks' );
$b1 = WPCPM_Tutor_Store::add( 8, $B, 'student', 'Another student asks' );
$b2 = WPCPM_Tutor_Store::add( 8, $B, 'mentor_ai', 'Another answer' );
$c1 = WPCPM_Tutor_Store::add( 9, 'recOLDSTUDENT001', 'student', 'Long ago' );
age( $a1, ago( 4000 ) );
age( $a2, ago( 3900 ) );
age( $a3, ago( 3800 ) );
age( $a4, ago( 3000 ) );
age( $b1, ago( 600 ) );
age( $b2, ago( 500 ) );
age( $c1, ago( 100 * DAY_IN_SECONDS ) );

$list = draw();
ck( 'the columns', array( has( $list, '>Record<' ), has( $list, '>Messages<' ), has( $list, '>Last message<' ) ), array( true, true, true ) );
ck( 'newest first: record B before record A, and the one 100 days old is not listed', array( strpos( $list, $B ) < strpos( $list, $A ), has( $list, 'recOLDSTUDENT001' ) ), array( true, false ) );
ck( 'each record links to its transcript', array( has( $list, '<a href="https://example.test/administrators/?wpcpm_view=ai-mentor&amp;wpcpm_tutor_record=' . $A . '"' ), has( $list, 'wpcpm_tutor_record=' . $B . '"' ) ), array( true, true ) );
ck( 'the messages are the student\'s turns', array( 1 === preg_match( '/role="cell" data-label="Messages">2</', $list ), 1 === preg_match( '/role="cell" data-label="Messages">1</', $list ) ), array( true, true ) );
ck( 'the last message is a time in the site\'s format', has( $list, wp_date( 'j F Y H:i', time() - 500 ) ), true );
ck( 'drawing the list writes no audit row', all_audit_rows(), 0 );
ck( 'its heading and lede name the view', array( has( $list, '<h2 class="wpcpm-head__title">AI Mentor transcripts</h2>' ), has( $list, 'Administrator Dashboard' ) ), array( true, true ) );

echo "\n=== A transcript ===\n";

$out = draw( $A );
ck( 'the heading names the record', has( $out, '<h2 class="wpcpm-head__title">Transcript of ' . $A . '</h2>' ), true );
ck( 'a link back to the list', has( $out, '<a href="https://example.test/administrators/?wpcpm_view=ai-mentor">All AI Mentor transcripts</a>' ), true );
ck( 'the three roles are labelled Student, AI Mentor and Tool call', array( has( $out, '>Student<' ), has( $out, '>AI Mentor<' ), has( $out, '>Tool call<' ) ), array( true, true, true ) );
ck( 'every row of the record, in order, and none of another record', array( strpos( $out, 'What is a pull request?' ) < strpos( $out, 'find_steps' ), strpos( $out, 'find_steps' ) < strpos( $out, 'It is a proposal' ), strpos( $out, 'It is a proposal' ) < strpos( $out, 'Thanks' ), has( $out, 'Another student asks' ) ), array( true, true, true, false ) );
ck( 'a reply\'s text is escaped and keeps its line breaks, in a pre that core\'s texturizing leaves alone', array( has( $out, '&lt;script&gt;alert(1)&lt;/script&gt;' ), has( $out, '<script>' ), has( $out, "merge code.\nSecond line" ), has( $out, "<br" ), has( $out, '<pre class="wpcpm-tutor__text">' ) ), array( true, false, true, false, true ) );
ck( 'a tool row shows its name and its args and result as indented JSON in a pre, escaped', array( has( $out, 'find_steps' ), has( $out, '<pre>' ), has( $out, "&quot;args&quot;: {\n    &quot;topic&quot;: &quot;pull request&quot;\n}" ) || has( $out, "&quot;args&quot;: {\n        &quot;topic&quot;" ), has( $out, '&lt;b&gt;two&lt;/b&gt;' ), has( $out, '<b>two</b>' ) ), array( true, true, true, true, false ) );
ck( 'a reply\'s sources are links, only http and https ones, and a source with any other address is left out whole', array( has( $out, '<a href="https://example.test/guide-a"' ), has( $out, '<a href="http://example.test/guide-b"' ), has( $out, 'javascript:' ), has( $out, 'data:text' ), has( $out, 'Bad one' ) ), array( true, true, false, false, false ) );
ck( 'each row has its time', has( $out, wp_date( 'j F Y H:i', time() - 3000 ) ), true );
ck( 'a reply\'s sources are a numbered list, as the card draws them', array( has( $out, '<ol><li><a href="https://example.test/guide-a"' ), has( $out, '<ul>' ) ), array( true, false ) );

echo "\n=== The audit row ===\n";

ck( 'opening the transcript wrote one row, about the record, by the reader', array_map( static function ( $e ) { return array( $e['kind'], $e['subject'], $e['actor'], $e['ground'], $e['evidence'], $e['data'], $e['message'] ); }, reads() ), array( array( 'tutor_transcript_read', $A, 11, 'manager', 'live', array(), '' ) ) );
ck( 'it is under its own key', get_post_meta( reads()[0]['id'], '_wpcpm_log_tutor', true ), $A );
ck( 'Read by lists the reader and the time', array( has( $out, '>Read by<' ), has( $out, 'Administrator A' ) ), array( true, true ) );
draw( $A );
ck( 'each open writes another row', count( reads() ), 2 );

$GLOBALS['uid'] = 12; draw( $A );
$GLOBALS['uid'] = 13; draw( $A );
$GLOBALS['uid'] = 14; draw( $A );
$GLOBALS['uid'] = 15; draw( $A );
$GLOBALS['uid'] = 16; $out = draw( $A );
ck( 'Read by shows the last five, newest first', array( has( $out, 'Administrator F' ), strpos( $out, 'Administrator F' ) < strpos( $out, 'Administrator E' ), has( $out, 'Administrator B' ), has( $out, 'Administrator A' ) ), array( true, true, true, false ) );
ck( 'and 7 rows were written', count( reads() ), 7 );
$every = '';
foreach ( $GLOBALS['posts'] as $id => $post ) {
	$every .= $post->post_title . $post->post_content . wp_json_encode( $GLOBALS['pmeta'][ $id ] );
}
ck( 'no row holds a word of the conversation', array( has( $every, 'pull request' ), has( $every, 'proposal' ), has( $every, 'Thanks' ), has( $every, 'find_steps' ) ), array( false, false, false, false ) );
ck( 'the record ID is the one thing kept of it', has( $every, $A ), true );

$before = all_audit_rows();
$GLOBALS['uid'] = 11;
$bad = draw( 'not-a-record' );
ck( 'a malformed record ID shows the list and writes nothing', array( has( $bad, '<h2 class="wpcpm-head__title">AI Mentor transcripts</h2>' ), has( $bad, 'Transcript of' ), all_audit_rows() ), array( true, false, $before ) );
$none = draw( 'recNOROWS0000001' );
ck( 'a valid record ID with no rows shows the list and writes nothing', array( has( $none, 'Transcript of' ), has( $none, '>Record<' ), all_audit_rows() ), array( false, true, $before ) );
$GLOBALS['fail_insert'] = true;
$failed = draw( $A );
ck( 'a read that cannot be recorded draws no transcript: the notice and the list, no text of the conversation, no row', array( has( $failed, 'This transcript could not be opened just now. Try again in a moment.' ), has( $failed, 'Transcript of' ), has( $failed, 'What is a pull request?' ), has( $failed, 'Thanks' ), has( $failed, '>Record<' ), all_audit_rows() ), array( true, false, false, false, true, $before ) );
unset( $GLOBALS['fail_insert'] );
$good = draw( $A );
ck( 'and once the write works the transcript is drawn again, without the notice', array( has( $good, 'Transcript of ' . $A ), has( $good, 'could not be opened' ), all_audit_rows() ), array( true, false, $before + 1 ) );
$before = all_audit_rows();
$GLOBALS['can'] = false;
$denied = draw( $A );
ck( 'a viewer who is no Administrator is shown nothing and nothing is written', array( $denied, all_audit_rows() ), array( '', $before ) );
$GLOBALS['can'] = true;
unset( $_GET['wpcpm_tutor_record'] );

echo "\n=== A message as typed ===\n";

// F1: the tutor keeps a student's message as typed, markup and all; the view escapes it.
$typed = "<b>x</b> <?php echo 1; ?>\n<!-- wp:paragraph -->\na < b && c > d";
WPCPM_Tutor_Store::add( 10, 'recSTUDENT0000003', 'student', $typed );
$as_typed = draw( 'recSTUDENT0000003' );
ck( 'a message of markup, PHP and a comparison is shown escaped, its line breaks kept, never as markup, in a pre',
	array( has( $as_typed, '&lt;b&gt;x&lt;/b&gt; &lt;?php echo 1; ?&gt;' . "\n" . '&lt;!-- wp:paragraph --&gt;' . "\n" . 'a &lt; b &amp;&amp; c &gt; d' ), has( $as_typed, '<b>x</b>' ), has( $as_typed, '<?php' ), has( $as_typed, '<!-- wp:paragraph -->' ) ),
	array( true, false, false, false ) );
unset( $_GET['wpcpm_tutor_record'] );

echo "\n=== Sources numbered as the markers are ===\n";

WPCPM_Tutor_Store::add( 10, 'recSTUDENT0000004', 'mentor_ai', 'These [1] [2] [3].', array(
	array( 'title' => 'Guide A', 'link' => 'https://example.test/guide-a' ),
	array( 'title' => 'Bad one', 'link' => 'javascript:alert(1)' ),
	array( 'title' => 'Guide C', 'link' => 'https://example.test/guide-c' ),
) );
$numbered = draw( 'recSTUDENT0000004' );
ck( 'a source left out keeps the next one at its own number, and the list\'s numbers show',
	array( has( $numbered, '<ol><li><a href="https://example.test/guide-a" target="_blank" rel="noopener noreferrer">Guide A</a></li><li value="3"><a href="https://example.test/guide-c" target="_blank" rel="noopener noreferrer">Guide C</a></li></ol>' ), has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/tutor-transcripts.css' ), 'td ol {' ) ),
	array( true, true ) );
unset( $_GET['wpcpm_tutor_record'] );

echo "\n=== A fallback's extracts ===\n";

WPCPM_Tutor_Store::add( 10, 'recSTUDENT0000005', 'mentor_ai', 'The parts of the guides that match.', array(
	array( 'title' => 'Guide A', 'link' => 'https://example.test/guide-a', 'extract' => 'Send <it> -- then wait...' ),
	array( 'title' => 'Guide C', 'link' => 'https://example.test/guide-c', 'extract' => '' ),
) );
$extracts = draw( 'recSTUDENT0000005' );
ck( 'a source\'s extract is drawn under its link, escaped, in a pre of its own class; a source without one has none',
	array(
		has( $extracts, '<li><a href="https://example.test/guide-a" target="_blank" rel="noopener noreferrer">Guide A</a><pre class="wpcpm-tutor__extract">Send &lt;it&gt; -- then wait...</pre></li>' ),
		has( $extracts, '<li><a href="https://example.test/guide-c" target="_blank" rel="noopener noreferrer">Guide C</a></li>' ),
		has( $extracts, 'Send <it>' ),
		has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/tutor-transcripts.css' ), 'pre.wpcpm-tutor__extract {' ),
	),
	array( true, true, false, true ) );
unset( $_GET['wpcpm_tutor_record'] );

echo "\n=== House rules ===\n";

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-tutor-transcripts-view.php' );
ck( 'no em or en dash in the view', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the view never reads the account\'s login or address', array( has( $src, 'user_login' ), has( $src, 'user_email' ) ), array( false, false ) );

printf( "\n%s (%d checks)\n", $fails ? "$fails FAILED" : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
