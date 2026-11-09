<?php
/**
 * The Start here and Team Matcher pages (student automation, Release B).
 *
 * What this pins, and why:
 *
 * - Both pages are made published and ungated, at their slugs, by `ensure_pages()`; a page already at
 *   the slug is adopted and left as it is; a second call makes nothing new.
 * - The Team Matcher page is made only while the Find Your Team block is registered.
 * - The Start here page is plain blocks (paragraph, heading, list), links the Student guide, the
 *   Student Report Card and the Team Matcher page, names no dash and starts no sentence with "Please".
 * - The Team Matcher page holds the block.
 * - The checklist's switch turned on makes the pages; saved on again, or off, it makes nothing.
 * - The welcome email's link line is pinned in bin/test-mail.php, where `welcome_email()` runs.
 *
 * Run from the plugin root:  php bin/test-start-pages.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['opts']   = array();
$GLOBALS['posts']  = array();
$GLOBALS['meta']   = array();
$GLOBALS['blocks'] = array();
$GLOBALS['next']   = 100;

class WP_Post {
	public $ID = 0, $post_type = 'page', $post_status = 'publish', $post_name = '', $post_content = '', $post_title = '';
}
class WP_Error {}
class WP_Block_Type_Registry {
	public static function get_instance() { return new self(); }
	public function is_registered( $name ) { return in_array( $name, $GLOBALS['blocks'], true ); }
}
class WPCPM_Settings {
	const OPT_NAME = 'wpcpm_settings';
}
class WPCPM_Content_Access {
	const META_KEY = '_wpcpm_access';
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
class WPCPM_Student_Registration_Form {
	const CONTACT = 'education@example.test';
}
class WPCPM_Handbook_Assistant {
	public static function guides() { return array( 'student' => array( 'url' => 'https://example.test/student-guide/' ) ); }
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['opts'][ $key ] = $value; return true; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function get_post_status( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ]->post_status : false; }
function get_permalink( $id ) { return 'https://example.test/' . $GLOBALS['posts'][ $id ]->post_name . '/'; }
function get_post_meta( $id, $key, $single = false ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_name === $path && 'page' === $post->post_type ) {
			return $post;
		}
	}
	return null;
}
function wp_insert_post( $args, $wp_error = false ) {
	$post = new WP_Post();
	foreach ( $args as $key => $value ) {
		$post->$key = $value;
	}
	$post->ID = ++$GLOBALS['next'];
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
function esc_url( $s ) { return (string) $s; }

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-start-pages.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}

function reset_site( $blocks ) {
	$GLOBALS['opts']   = array();
	$GLOBALS['posts']  = array();
	$GLOBALS['meta']   = array();
	$GLOBALS['blocks'] = $blocks;
}

echo "=== ensure_pages ===\n";
reset_site( array( WPCPM_Start_Pages::MATCHER_BLOCK ) );
$ids = WPCPM_Start_Pages::ensure_pages();
$s   = $GLOBALS['posts'][ $ids['start'] ] ?? null;
$m   = $GLOBALS['posts'][ $ids['matcher'] ] ?? null;
ck( 'ensure_makes_both_public: two published pages at their slugs',
	array( $s ? $s->post_status : null, $s ? $s->post_name : null, $m ? $m->post_status : null, $m ? $m->post_name : null ),
	array( 'publish', 'start-here', 'publish', 'find-your-team' ) );
ck( 'ensure_makes_both_public: no access level on either', array( isset( $GLOBALS['meta'][ $ids['start'] ] ), isset( $GLOBALS['meta'][ $ids['matcher'] ] ) ), array( false, false ) );
ck( 'ensure_makes_both_public: the options hold the IDs', array( get_option( 'wpcpm_start_here_page_id' ), get_option( 'wpcpm_team_matcher_page_id' ) ), array( $ids['start'], $ids['matcher'] ) );
ck( 'the addresses answer while published', array( WPCPM_Start_Pages::start_url(), WPCPM_Start_Pages::matcher_url() ), array( 'https://example.test/start-here/', 'https://example.test/find-your-team/' ) );

$again = WPCPM_Start_Pages::ensure_pages();
ck( 'ensure_idempotent: the same IDs and no new page', array( $again, count( $GLOBALS['posts'] ) ), array( $ids, 2 ) );

$GLOBALS['posts'][ $ids['start'] ]->post_status = 'draft';
ck( 'an unpublished page has no address', WPCPM_Start_Pages::start_url(), '' );

echo "\n=== adoption and the block ===\n";
reset_site( array( WPCPM_Start_Pages::MATCHER_BLOCK ) );
$mine = new WP_Post();
$mine->ID = 7; $mine->post_name = 'start-here'; $mine->post_content = 'The program lead wrote this.';
$GLOBALS['posts'][7] = $mine;
$ids = WPCPM_Start_Pages::ensure_pages();
ck( 'ensure_adopts_existing_slug: the page at the slug is the page, its text untouched', array( $ids['start'], $GLOBALS['posts'][7]->post_content, get_option( 'wpcpm_start_here_page_id' ) ), array( 7, 'The program lead wrote this.', 7 ) );

reset_site( array() );
$ids = WPCPM_Start_Pages::ensure_pages();
ck( 'matcher_skipped_without_block: no Team Matcher page, the Start here page still made', array( $ids['matcher'], $ids['start'] > 0, WPCPM_Start_Pages::matcher_url() ), array( 0, true, '' ) );
ck( 'and the Start here page then links no Team Matcher page', false === strpos( $GLOBALS['posts'][ $ids['start'] ]->post_content, 'find-your-team' ), true );
reset_site( array( WPCPM_Start_Pages::MATCHER_BLOCK ) );
$ids = WPCPM_Start_Pages::ensure_pages();
ck( 'once the block is registered, the next call makes the Team Matcher page', array( WPCPM_Start_Pages::ensure_pages()['matcher'] > 0 ), array( true ) );

echo "\n=== the content ===\n";
$start = $GLOBALS['posts'][ $ids['start'] ]->post_content;
preg_match_all( '/<!-- wp:([a-z\-\/]+)/', $start, $found );
$kinds = array_values( array_unique( $found[1] ) );
sort( $kinds );
ck( 'start_content_blocks: only paragraph, heading and list blocks', $kinds, array( 'heading', 'list', 'list-item', 'paragraph' ) );
ck( 'start_content_blocks: links the Student guide, the Student Report Card and the Team Matcher page',
	array( false !== strpos( $start, 'href="https://example.test/student-guide/"' ), false !== strpos( $start, 'href="https://example.test/student-report-card/"' ), false !== strpos( $start, 'href="https://example.test/find-your-team/"' ) ),
	array( true, true, true ) );
ck( 'start_content_blocks: no em or en dash, no "Please", no bare "Report Card"', array( 1 === preg_match( "/\xE2\x80[\x93\x94]/", $start ), false !== stripos( $start, 'please' ), 1 === preg_match( '/(?<!Student )(?<!Mentor )Report Card/', $start ) ), array( false, false, false ) );
ck( 'start_content_blocks: names Administrators, the first week and the mentor', array( false !== strpos( $start, 'Administrator' ), false !== strpos( $start, 'first week' ), false !== strpos( $start, 'mentor' ) ), array( true, true, true ) );
ck( 'start_content_blocks: says the Student guide opens once signed in as a student', false !== strpos( $start, 'explains the program step by step. It opens once you are signed in as a student.' ), true );
ck( 'start_content_blocks: reads true signed out, it points at the welcome email for the sign-in', false !== strpos( $start, 'link in your welcome email' ), true );
$matcher = $GLOBALS['posts'][ $ids['matcher'] ]->post_content;
ck( 'matcher_content_has_block', array( false !== strpos( $matcher, '<!-- wp:contributor-team-matcher/quiz /-->' ), 0 === strpos( $matcher, '<!-- wp:paragraph -->' ) ), array( true, true ) );
ck( 'the Team Matcher page names no dash', 1 === preg_match( "/\xE2\x80[\x93\x94]/", $matcher ), false );

echo "\n=== the switch ===\n";
reset_site( array( WPCPM_Start_Pages::MATCHER_BLOCK ) );
WPCPM_Start_Pages::follow_switch( array(), array( 'student_checklist_enabled' => false ) );
ck( 'switch off to off makes nothing', count( $GLOBALS['posts'] ), 0 );
WPCPM_Start_Pages::follow_switch( array( 'student_checklist_enabled' => false ), array( 'student_checklist_enabled' => true ) );
ck( 'switch_on_makes_pages: both pages exist', array( count( $GLOBALS['posts'] ), WPCPM_Start_Pages::start_url() !== '', WPCPM_Start_Pages::matcher_url() !== '' ), array( 2, true, true ) );
reset_site( array() );
WPCPM_Start_Pages::follow_switch( array( 'student_checklist_enabled' => true ), array( 'student_checklist_enabled' => true ) );
ck( 'saved on again makes nothing', count( $GLOBALS['posts'] ), 0 );
WPCPM_Start_Pages::follow_switch( array( 'student_checklist_enabled' => true ), array( 'student_checklist_enabled' => false ) );
ck( 'switched off makes nothing', count( $GLOBALS['posts'] ), 0 );
WPCPM_Start_Pages::init();
ck( 'init hangs the switch on the settings option', isset( $GLOBALS['hooks']['update_option_wpcpm_settings'] ), true );

echo $fail ? "\nFAILURES: $fail\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
