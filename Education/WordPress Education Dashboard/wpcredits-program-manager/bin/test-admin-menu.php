<?php
/**
 * The WPCredits Program menu, and what the two screens the plugin draws of its own parts say.
 *
 * One top-level entry, WPCredits Program, and under it every screen titled with its own name:
 * Overview, a screen per audience, Tools and a screen per tool, then Settings. The Overview and the
 * Tools screen call the program's parts what the Settings screen and the program manager guide call
 * them, audiences and tools. Never modules: the word named the audiences on the Overview and the
 * tools in the menu, one word for two things a screen apart. Drawn through the real admin class,
 * the real audiences and the real tools, so the words checked are the ones a program manager reads.
 *
 * The same words beyond the screens: the plugin's description, its readme and the blocks that draw
 * the two Report Cards; a menu path written one way, with ">"; and the tools the Settings screen
 * names as keeping their settings on their own screens, which are the registry's.
 *
 * Run from the plugin root:  php bin/test-admin-menu.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']     = array();
$GLOBALS['filters']  = array();
$GLOBALS['menu']     = array();
$GLOBALS['submenu']  = array();
$GLOBALS['accounts'] = array();
$GLOBALS['caps']     = true; // The menu and both screens are a manager's.

class WP_Error {
	public function __construct( $c = '', $m = '' ) {}
	public function get_error_message() { return ''; }
}

/** The one question an audience's card asks of the users: how many accounts hold its role. */
class WP_User_Query {
	private $role = '';

	public function __construct( $args = array() ) {
		$this->role = isset( $args['role'] ) ? (string) $args['role'] : '';
	}

	public function get_total() {
		return isset( $GLOBALS['accounts'][ $this->role ] ) ? (int) $GLOBALS['accounts'][ $this->role ] : 0;
	}
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
// A filter a check hooks answers through its callback; any other value passes through.
function apply_filters( $tag, $value, ...$args ) { return isset( $GLOBALS['filters'][ $tag ] ) ? call_user_func( $GLOBALS['filters'][ $tag ], $value, ...$args ) : $value; }
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
// The Sponsors entry counts what waits for a manager and keeps the count a minute; nothing is kept here.
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $from, $to = 0 ) { return '2 hours'; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, (int) $timestamp ); }
function wp_die( $m = '' ) { throw new Exception( 'wp_die: ' . $m ); }
function get_current_user_id() { return 1; }
require_once __DIR__ . '/stubs/caps.php';

/**
 * WordPress's two menu functions, keeping what each entry is called and where it points, in the order
 * the plugin adds them.
 */
function add_menu_page( $page_title, $menu_title, $capability, $slug, $callback = '', $icon = '', $position = null ) {
	$GLOBALS['menu'][] = array( $page_title, $menu_title, $slug, $capability );

	return 'toplevel_page_' . $slug;
}

function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug, $callback = '' ) {
	$GLOBALS['submenu'][] = array( $page_title, $menu_title, $slug, $capability, $parent );

	return $parent . '_page_' . $slug;
}

/*
 * What the audiences' and the tools' own lines read, stood in for the one answer each gives here:
 * the mentors sync's column names beside the Mentor Status Checker's settings, the checker's last
 * run, the last duplicates scan, the tracks, the notices, and the two queues the Institutions entry
 * counts for its bubble. Each answers "nothing yet", so every line is the one a new site shows.
 */

class WPCPM_Mentors_Sync {
	public static function fields() {
		return array( 'mentor_name' => 'Full Name', 'mentor_profile' => 'WordPress profile', 'mentor_status' => 'Status' );
	}
}

class WPCPM_Mentor_Checker_Runner {
	public static function get_last_run() {
		return array();
	}
}

class WPCPM_Duplicates_Scan {
	public static function report() {
		return null;
	}
}

class WPCPM_Track_Store {
	public static function all_ids() {
		return array();
	}
}

class WPCPM_Notices {
	public static function bodies() {
		return array();
	}
}

class WPCPM_Institution_Application {
	public static function pending_count( $limit ) {
		return 0;
	}
}

class WPCPM_Institution_Agreement {
	public static function awaiting_review( $limit ) {
		return array();
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
// The Settings screen the menu's last entry opens, which the admin class holds.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
// The five audiences and their registry, as the plugin's loader requires them.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-modules.php';
// The five tools and their registry, the same way.
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-builder.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';

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

/**
 * One of the admin class's screens, as a manager opening it sees it.
 *
 * @param string $screen `render_overview` or `render_tools`.
 * @return string The screen's markup.
 */
function draw( $screen ) {
	ob_start();
	( new WPCPM_Admin() )->$screen();

	return (string) ob_get_clean();
}

/**
 * The words some markup says, as a person reads them: each tag a space, the entities decoded, a run
 * of spaces one. A tag taken out without a space would join the heading "Modules" to the sentence
 * under it, and the word would hide inside "ModulesParts".
 *
 * @param string $html The markup.
 * @return string
 */
function words_of( $html ) {
	return trim( preg_replace( '/\s+/', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $html ), ENT_QUOTES ) ) );
}

/**
 * Every "module" or "modules" some words hold, whatever their case.
 *
 * @param string $words The words.
 * @return string[]
 */
function modules_in( $words ) {
	preg_match_all( '/\bmodules?\b/i', (string) $words, $found );

	return $found[0];
}

/**
 * The Tools screen's registry forgets the tools it read, so the next screen reads them again, through
 * the filter a check may be hooking.
 */
function forget_tools() {
	$cache = new ReflectionProperty( 'WPCPM_Tools', 'tools' );

	if ( PHP_VERSION_ID < 80100 ) {
		$cache->setAccessible( true );
	}

	$cache->setValue( null, null );
}

echo "=== The menu ===\n";

( new WPCPM_Admin() )->register_menu();

$top = array();

foreach ( $GLOBALS['menu'] as $entry ) {
	$top[] = array( $entry[0], $entry[1], $entry[2] );
}

ck( 'the one top-level entry is the plugin, called WPCredits Program, and it opens the Overview',
    $top,
    array( array( 'WPCredits Program', 'WPCredits Program', 'wpcpm' ) ) );

$under = array();

foreach ( $GLOBALS['submenu'] as $entry ) {
	$under[] = array( $entry[0], $entry[1], $entry[2] );
}

// Each page's title, then the menu's line for it, then its address. A page's title is what the
// browser's tab and the screen's heading say, so it is the screen's own name and nothing else.
ck( 'under it, in order: the Overview, the five audiences, Tools with each tool indented under it, then Settings, each page titled with its own name',
    $under,
    array(
        array( 'Overview', 'Overview', 'wpcpm' ),
        array( 'Students', 'Students', 'wpcpm-students' ),
        array( 'Mentors', 'Mentors', 'wpcpm-mentors' ),
        array( 'Institutions', 'Institutions', 'wpcpm-institutions' ),
        array( 'Sponsors', 'Sponsors', 'wpcpm-sponsors' ),
        array( 'Administrators', 'Administrators', 'wpcpm-administrators' ),
        array( 'Tools', 'Tools', 'wpcpm-tools' ),
        array( 'Header notices', '- Header notices', 'wpcpm-tool-header-notices' ),
        array( 'Need help?', '- Need help?', 'wpcpm-tool-handbook' ),
        array( 'Mentor Status Checker', '- Mentor Status Checker', 'wpcpm-tool-mentor-status-checker' ),
        array( 'Student Duplicate Finder', '- Student Duplicate Finder', 'wpcpm-tool-duplicate-finder' ),
        array( 'Track Builder', '- Track Builder', 'wpcpm-tool-track-builder' ),
        array( 'Settings', 'Settings', 'wpcpm-settings' ),
    ) );

ck( 'and every entry is a program manager\'s, under the plugin\'s own entry',
    array_values( array_unique( array_merge( array_column( $GLOBALS['menu'], 3 ), array_column( $GLOBALS['submenu'], 3 ), array_column( $GLOBALS['submenu'], 4 ) ) ) ),
    array( 'wpcpm_manage_program', 'wpcpm' ) );

echo "\n=== The Overview ===\n";

$GLOBALS['accounts'] = array( 'wpcpm_student' => 214, 'wpcpm_mentor' => 92, 'administrator' => 3 );

$overview = draw( 'render_overview' );
$titles   = array();

foreach ( $GLOBALS['submenu'] as $entry ) {
	$titles[ $entry[2] ] = $entry[0];
}

preg_match_all( '#<h1>(.*?)</h1>#s', $overview, $h1 );
preg_match_all( '#<p class="wpcpm-lede">(.*?)</p>#s', $overview, $ledes );
preg_match_all( '#<h2><span class="wpcpm-module-card__index">[^<]*</span> <a href="([^"]*)">(.*?)</a></h2>#s', $overview, $audiences );
preg_match_all( '#</div><h2>([^<]*)</h2>#', $overview, $headings );
preg_match_all( '#<div class="wpcpm-module-card"><h2><a href="([^"]*)">(.*?)</a></h2>#s', $overview, $tools );

ck( 'the Overview\'s heading is its name, as the menu titles the page',
    array( array_map( 'words_of', $h1[1] ), $titles['wpcpm'] ),
    array( array( 'Overview' ), 'Overview' ) );

ck( 'it opens on the audiences, one card for each in the menu\'s order, each linking its screen',
    array( isset( $ledes[1][0] ) ? words_of( $ledes[1][0] ) : null, array_map( 'words_of', $audiences[2] ), $audiences[1] ),
    array(
        'The program\'s audiences, each with a user role and a screen of its own.',
        array( 'Students', 'Mentors', 'Institutions', 'Sponsors', 'Administrators' ),
        array(
            'https://example.test/wp-admin/admin.php?page=wpcpm-students',
            'https://example.test/wp-admin/admin.php?page=wpcpm-mentors',
            'https://example.test/wp-admin/admin.php?page=wpcpm-institutions',
            'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors',
            'https://example.test/wp-admin/admin.php?page=wpcpm-administrators',
        ),
    ) );

ck( 'then the tools, under the heading the menu gives them, Tools, one card for each linking its screen',
    array( array_map( 'words_of', $headings[1] ), isset( $ledes[1][1] ) ? words_of( $ledes[1][1] ) : null, array_map( 'words_of', $tools[2] ), $tools[1] ),
    array(
        array( 'Tools' ),
        'Parts of the program that can be switched on, run and configured on their own.',
        array( 'Header notices', 'Need help?', 'Mentor Status Checker', 'Student Duplicate Finder', 'Track Builder' ),
        array(
            'https://example.test/wp-admin/admin.php?page=wpcpm-tool-header-notices',
            'https://example.test/wp-admin/admin.php?page=wpcpm-tool-handbook',
            'https://example.test/wp-admin/admin.php?page=wpcpm-tool-mentor-status-checker',
            'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder',
            'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder',
        ),
    ) );

// Every word on the page: the Overview's own, and what each audience and each tool says of itself.
ck( 'and nothing on the Overview says module, the audiences\' and the tools\' own descriptions and status lines included',
    modules_in( words_of( $overview ) ),
    array() );

echo "\n=== The Tools screen ===\n";

$tools_screen = draw( 'render_tools' );

preg_match_all( '#<h1>(.*?)</h1>#s', $tools_screen, $h1 );
preg_match_all( '#<p class="wpcpm-lede">(.*?)</p>#s', $tools_screen, $ledes );
preg_match_all( '#<div class="wpcpm-module-card"><h2><a href="([^"]*)">(.*?)</a></h2>#s', $tools_screen, $tools );
preg_match_all( '#<a class="button" href="([^"]*)">(.*?)</a>#s', $tools_screen, $opens );

ck( 'the Tools screen\'s heading is its name, as the menu titles the page, and it says what a tool is in the Overview\'s words',
    array( array_map( 'words_of', $h1[1] ), $titles['wpcpm-tools'], array_map( 'words_of', $ledes[1] ) ),
    array( array( 'Tools' ), 'Tools', array( 'Parts of the program that can be switched on, run and configured on their own.' ) ) );

ck( 'a card for each tool, each opening the tool\'s screen',
    array( array_map( 'words_of', $tools[2] ), $opens[1], array_values( array_unique( array_map( 'words_of', $opens[2] ) ) ) ),
    array(
        array( 'Header notices', 'Need help?', 'Mentor Status Checker', 'Student Duplicate Finder', 'Track Builder' ),
        $tools[1],
        array( 'Open tool' ),
    ) );

ck( 'and nothing on the Tools screen says module', modules_in( words_of( $tools_screen ) ), array() );

/**
 * The lines each tool's card says under its description, on the Tools screen or the Overview: its
 * status line, with its class, whether its words are the warning a tool that cannot run gives, and
 * the words.
 *
 * @param string $html The Tools screen, or the Overview.
 * @return array<string, array[]> The tool's name => its lines, each its class, whether it warns, and
 *                                its words.
 */
function card_lines( $html ) {
	preg_match_all( '#<div class="wpcpm-module-card"><h2><a href="[^"]*">(.*?)</a></h2>(.*?)</div>#s', $html, $cards, PREG_SET_ORDER );

	$lines = array();

	foreach ( $cards as $card ) {
		preg_match_all( '#<p class="(wpcpm-tool-status|wpcpm-warning)">(.*?)</p>#s', $card[2], $found, PREG_SET_ORDER );

		$lines[ words_of( $card[1] ) ] = array();

		foreach ( $found as $line ) {
			$lines[ words_of( $card[1] ) ][] = array( $line[1], 1 === preg_match( '#^<span class="wpcpm-warning">[^<]*</span>$#', $line[2] ), words_of( $line[2] ) );
		}
	}

	return $lines;
}

// A tool that cannot run says why on its card, as a warning, in its own words: the Mentor Status
// Checker and the Student Duplicate Finder need Airtable, and Need help? needs its switch and a
// provider and no Airtable at all. On a new site nothing is connected and no provider is set. The
// line is the card's status line either way, with its rule above it, and its words the warning, the
// same on the Tools screen and on the Overview, which show the same cards.
$airtable_missing = 'Airtable is not connected yet, so this tool cannot run.';
$new_site_lines   = array(
	'Header notices'           => array( array( 'wpcpm-tool-status', false, 'No notices are showing.' ) ),
	'Need help?'               => array( array( 'wpcpm-tool-status', true, 'No AI provider is configured, so questions cannot be answered.' ) ),
	'Mentor Status Checker'    => array( array( 'wpcpm-tool-status', true, $airtable_missing ) ),
	'Student Duplicate Finder' => array( array( 'wpcpm-tool-status', true, $airtable_missing ) ),
	'Track Builder'            => array( array( 'wpcpm-tool-status', false, '0 tracks, 0 published.' ) ),
);

ck( 'on a new site, a tool that cannot run says why on its card, its status line holding the warning in its own words, and a tool that can run its status line, alike on the Tools screen and on the Overview',
    array( card_lines( $tools_screen ), card_lines( $overview ) ),
    array( $new_site_lines, $new_site_lines ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'handbook_enabled' => false );
$switched_off                                = card_lines( draw( 'render_tools' ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array(
	'api_token'         => 'patPROBE0000000001',
	'base_id'           => 'appPROBE0000000001',
	'handbook_provider' => 'gemini',
	'handbook_key'      => 'AQ.probe-key-value',
);
$all_ready = card_lines( draw( 'render_tools' ) );

$GLOBALS['opts'] = array();

ck( 'Need help? switched off says so as its warning, and with Airtable connected and a provider set every card says its status line and none a warning',
    array( $switched_off['Need help?'], $all_ready ),
    array(
        array( array( 'wpcpm-tool-status', true, 'Switched off.' ) ),
        array(
            'Header notices'           => array( array( 'wpcpm-tool-status', false, 'No notices are showing.' ) ),
            'Need help?'               => array( array( 'wpcpm-tool-status', false, 'Answering through Google AI Studio (Gemini).' ) ),
            'Mentor Status Checker'    => array( array( 'wpcpm-tool-status', false, 'Never run.' ) ),
            'Student Duplicate Finder' => array( array( 'wpcpm-tool-status', false, 'No scan has run yet.' ) ),
            'Track Builder'            => array( array( 'wpcpm-tool-status', false, '0 tracks, 0 published.' ) ),
        ),
    ) );

// A site whose filter takes every tool away still gets a screen that says so, in the screen's word.
$GLOBALS['filters']['wpcpm_tools'] = function () {
	return array();
};
forget_tools();

$no_tools = draw( 'render_tools' );

unset( $GLOBALS['filters']['wpcpm_tools'] );
forget_tools();

ck( 'with no tool registered, the Tools screen says there are none, in its own word',
    array( words_of( preg_match( '#<div class="wpcpm-card"><p>(.*?)</p></div>#s', $no_tools, $none ) ? $none[1] : '' ), modules_in( words_of( $no_tools ) ) ),
    array( 'No tools are registered.', array() ) );

echo "\n=== The plugin's screens say audiences and tools ===\n";

/**
 * Every string a file hands a translation function, as a translator is given it: the text, and for
 * a singular and plural pair both.
 *
 * @param string $source The file.
 * @return string[]
 */
function translated_in( $source ) {
	preg_match_all(
		"/(?<![\\w>:$])(_n_noop|_nx_noop|_nx|_n|__|_e|_x|_ex|esc_html__|esc_html_e|esc_html_x|esc_attr__|esc_attr_e|esc_attr_x)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'(?:\\s*,\\s*'((?:[^'\\\\]|\\\\.)*)')?/",
		$source,
		$found,
		PREG_SET_ORDER
	);

	$strings = array();

	foreach ( $found as $call ) {
		$strings[] = stripslashes( $call[2] );

		// The second argument is the plural of `_n()` and its kin; of every other it is the domain.
		if ( 0 === strpos( $call[1], '_n' ) && isset( $call[3] ) ) {
			$strings[] = stripslashes( $call[3] );
		}
	}

	return $strings;
}

// Every file under includes/, not the admin class alone: the tools keep their settings on their own
// screens, so a tool's name, a card's heading or a pill is printed by the tool's own class, and a
// screen drawn by any other class is held to the same words.
$plugin_says = array();
$files_read  = 0;

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}

	++$files_read;
	$plugin_says = array_merge( $plugin_says, translated_in( (string) file_get_contents( $file->getPathname() ) ) );
}

$old_words = array();

foreach ( $plugin_says as $string ) {
	if ( array() !== modules_in( $string ) || false !== strpos( $string, 'Tool:' ) || 'Tool' === $string ) {
		$old_words[] = $string;
	}
}

// The strings are read from the files, so a sentence on a screen no check draws is held too.
ck( 'every file under includes/ is read, and together they hand the translators a meaningful number of strings',
    array( $files_read > 100, count( $plugin_says ) > 2000 ), array( true, true ) );
ck( 'and none of them says module, calls a tool "Tool:" or is the word "Tool" on its own, a tool\'s pill',
    $old_words, array() );

// The panel an audience's screen shows before the audience is built is printed by the audience
// class every audience extends, on that audience's screen.
$panel_says = translated_in( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php' ) );

ck( 'and the panel an audience\'s screen shows before it is built says audience, not module',
    array( count( $panel_says ) > 3, array_values( array_filter( $panel_says, function ( $string ) { return array() !== modules_in( $string ); } ) ) ),
    array( true, array() ) );

// Its line for a role that is missing says what to do the way the Settings screen's line for a page
// that is missing says it: after a colon, not a dash.
ck( 'and its line for a missing role says what to do in the words the Settings screen uses for a missing page',
    array( in_array( '(missing: re-activate the plugin to register it)', $panel_says, true ), in_array( '(missing - re-activate the plugin)', $panel_says, true ) ),
    array( true, false ) );

echo "\n=== The program manager guide uses the same words ===\n";

$sections = glob( WPCPM_PLUGIN_DIR . 'docs/sections/*.md' );
$texts    = array();
$menu_way = array();

foreach ( $sections as $path ) {
	$texts[ basename( $path ) ] = (string) file_get_contents( $path );
}

// The readme up to its changelog: an entry says what a release did in the words of its day.
$readme              = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'readme.txt' );
$changelog           = strpos( $readme, '== Changelog ==' );
$texts['readme.txt'] = false === $changelog ? $readme : substr( $readme, 0, $changelog );

foreach ( $texts as $file => $text ) {
	// "Modules" with its capital is the old name of the Tools menu item and screen; a Learn course's
	// modules and the Student Report Card's movable modules are other things, and lowercase.
	if ( preg_match_all( '/\bModules\b|Tool:/', $text, $found ) ) {
		$menu_way[ $file ] = array_values( array_unique( $found[0] ) );
	}
}

ck( 'no guide section, and nothing the readme says before its changelog, calls the Tools menu item Modules or a tool "Tool:"',
    array( count( $sections ) > 10, false !== $changelog, $menu_way ), array( true, true, array() ) );

$guide_30 = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/30-admin-wpadmin.md' );

ck( 'the guide\'s part on the plugin in wp-admin says audience and tool wherever it once said module',
    modules_in( $guide_30 ), array() );

// The people who run the program are the Administrators, the audience's name, in every guide and in
// what the readme says before its changelog, and managing the program is their job: "Program
// Administrator" was an older name for them.
$retired_name = array();

foreach ( $texts as $file => $text ) {
	if ( preg_match( '/\bProgram Administrators?\b/i', $text ) ) {
		$retired_name[] = $file;
	}
}

ck( 'and no guide section, nor the readme before its changelog, calls the program managers Program Administrators',
    $retired_name, array() );

$table = preg_match( '/^### The words the screens use\n(.*?)(?=^#{2,3} |\z)/ms', $guide_30, $part ) ? $part[1] : '';

preg_match_all( '/^\|(.*)\|$/m', $table, $lines );

$rows = array();

foreach ( array_slice( $lines[1], 2 ) as $line ) {
	$cells  = array_map( 'trim', explode( '|', $line ) );
	$rows[] = array( str_replace( '**', '', $cells[0] ), implode( ' | ', $cells ) );
}

$named = array_column( $rows, 1, 0 );
$lacks = array();

// What each row has to name, in bold as the guide names a thing on a screen: the tools as the
// registry holds them, so a tool added or renamed without its row fails here.
$tool_names = array();

foreach ( WPCPM_Tools::all() as $tool ) {
	$tool_names[] = $tool->label();
}

$must_name = array(
	'WPCredits Program' => array( 'WPCredits Program', 'Overview', 'Settings' ),
	'Audiences'         => array( 'Students', 'Mentors', 'Institutions', 'Sponsors', 'Administrators' ),
	'Tools'             => $tool_names,
	'Landing page'      => array( 'Mentor', 'Student', 'Institution', 'Sponsor landing page' ),
	'Remove and Leave'  => array( 'Remove', 'Leave' ),
);

foreach ( $must_name as $row => $names ) {
	foreach ( $names as $name ) {
		if ( ! isset( $named[ $row ] ) || false === strpos( $named[ $row ], '**' . $name . '**' ) ) {
			$lacks[ $row ][] = $name;
		}
	}
}

ck( 'and it has a table of the words the screens use: the plugin\'s name, the five audiences, every tool by its name, the landing pages and the leaving rules\' two answers, each row naming them as the screens do',
    array( array_column( $rows, 0 ), $lacks ),
    array( array_keys( $must_name ), array() ) );

// One name for each audience, the one its screen and the Overview's card give it: the people who
// manage the program are the Administrators, as their screen is titled, and no second name for them.
$audience_cells = isset( $named['Audiences'] ) ? explode( ' | ', $named['Audiences'] ) : array();

preg_match_all( '/\*\*([^*]+)\*\*/', isset( $audience_cells[1] ) ? $audience_cells[1] : '', $audience_names );

ck( 'and its row of audiences names each by the one name its screen gives it, the people who manage the program as Administrators alone',
    $audience_names[1], array( 'Students', 'Mentors', 'Institutions', 'Sponsors', 'Administrators' ) );

echo "\n=== The plugin's description, its readme and its blocks use the same words ===\n";

// The description wp-admin's Plugins screen prints under the plugin's name, read from the main file's
// header, which the translation template carries too.
$main_file   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );
$description = preg_match( '/^ \* Description:\s*(.+)$/m', $main_file, $found ) ? trim( $found[1] ) : '';
$audiences   = 'Students, Mentors, Institutions, Sponsors and Administrators';

ck( 'the description the Plugins screen prints names the five audiences as the screens do, and says module nowhere',
    array( '' !== $description, false !== strpos( $description, $audiences ), modules_in( $description ) ),
    array( true, true, array() ) );

// The readme's short description, the line after its header that wordpress.org prints under the
// plugin's name, and everything the readme says before its changelog.
$short = preg_match( '/\A=== [^\n]* ===\n(?:[^\n]+\n)+\n([^\n]+)\n/', $readme, $found ) ? $found[1] : '';

ck( 'the readme\'s short description names the five audiences as the screens do, and nothing the readme says before its changelog says module',
    array( false !== strpos( $short, $audiences ), modules_in( $texts['readme.txt'] ) ),
    array( true, array() ) );

// The titles the two Report Cards' pages had before, which each dashboard renames on sight
// (`OLD_TITLES`), read from the two classes, so a title retired later is held here too.
$retired = array();

foreach ( array( 'class-wpcpm-students-dashboard.php', 'class-wpcpm-mentors-dashboard.php' ) as $dashboard ) {
	if ( preg_match( '/const OLD_TITLES = array\(([^)]*)\);/', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/' . $dashboard ), $list ) ) {
		preg_match_all( "/'([^']+)'/", $list[1], $titles );
		$retired = array_merge( $retired, $titles[1] );
	}
}

// The blocks that draw the two Report Cards: the title the inserter lists, the placeholder the editor
// shows in the block's place, and the version the editor script is fetched under, which is the asset
// file's (WordPress prefers it to block.json's), so an editor that has the old script cached is sent
// the new words.
$blocks = array();
$said   = $texts;

foreach ( array( 'student-dashboard', 'mentor-dashboard' ) as $block_dir ) {
	$block  = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/' . $block_dir . '/block.json' ), true );
	$script = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/' . $block_dir . '/editor.js' );
	$asset  = include WPCPM_PLUGIN_DIR . 'blocks/' . $block_dir . '/editor.asset.php';

	preg_match( "/components\\.Placeholder,\\s*\\{ label: __\\( '([^']*)'/", $script, $placeholder );

	$blocks[ $block_dir ]           = array( $block['title'], isset( $placeholder[1] ) ? $placeholder[1] : null, $asset['version'] === $block['version'] );
	$said[ 'blocks/' . $block_dir ] = $block['title'] . "\n" . $block['description'] . "\n" . $script;
}

ck( 'the two blocks that draw the Report Cards are called by their pages\' names, in the inserter and in the editor\'s placeholder, and each editor script is fetched under its block\'s version',
    $blocks,
    array(
        'student-dashboard' => array( 'Student Report Card', 'Student Report Card', true ),
        'mentor-dashboard'  => array( 'Mentor Report Card', 'Mentor Report Card', true ),
    ) );

$still = array();

foreach ( $said as $where => $text ) {
	foreach ( $retired as $title ) {
		if ( preg_match( '/(?<![\w-])' . preg_quote( $title, '/' ) . '(?![\w-])/', $text ) ) {
			$still[ $where ][] = $title;
		}
	}
}

ck( 'and no guide section, nothing the readme says before its changelog, and neither block calls either page by a title it no longer has',
    array( count( $retired ) >= 4, $still ), array( true, array() ) );

echo "\n=== A menu path is written one way ===\n";

// Each step of a menu path follows "WPCredits Program" after ">", a plain character any encoding
// carries, in the plugin's strings, the guides and the readme before its changelog alike. The arrow
// stays where it is not a menu path: the mentor page's source line, a table and its column, and two
// of the readme's own lines. A released changelog entry keeps the words of its day.
$separators = array();
$paths      = 0;

foreach ( array_merge( array_values( $texts ), $plugin_says ) as $text ) {
	if ( preg_match_all( '/WPCredits Program\s*(→|->|»|&gt;|>)\s*\S/u', $text, $found ) ) {
		$paths     += count( $found[1] );
		$separators = array_merge( $separators, $found[1] );
	}
}

$arrows = array();

foreach ( $texts as $file => $text ) {
	if ( 'readme.txt' !== $file && false !== strpos( $text, '→' ) ) {
		$arrows[] = $file;
	}
}

ck( 'every menu path the plugin\'s strings, the guides and the readme write steps from WPCredits Program with ">"',
    array( $paths > 10, array_values( array_unique( $separators ) ) ), array( true, array( '>' ) ) );

// And a path through WordPress's own Settings menu, which the privacy lines send a manager along, is
// written the same way: "Settings > Privacy", not with a comma.
$core_separators = array();
$core_paths      = 0;

foreach ( array_merge( array_values( $texts ), $plugin_says ) as $text ) {
	if ( preg_match_all( '/\bSettings\s*(,|→|->|»|&gt;|>)\s*(?:General|Writing|Reading|Discussion|Media|Permalinks|Privacy)\b/u', $text, $found ) ) {
		$core_paths     += count( $found[1] );
		$core_separators = array_merge( $core_separators, $found[1] );
	}
}

ck( 'and every path through WordPress\'s own Settings menu the plugin\'s strings, the guides and the readme write, Settings > Privacy among them, steps with ">" too',
    array( $core_paths >= 5, array_values( array_unique( $core_separators ) ) ), array( true, array( '>' ) ) );
ck( 'no guide section holds the arrow, and the one string that does is the mentor page\'s source line, a table and its column rather than a menu path',
    array( $arrows, array_values( array_filter( $plugin_says, function ( $string ) { return false !== strpos( $string, '→' ); } ) ) ),
    array( array(), array( 'Airtable · %1$s → %2$s' ) ) );

echo "\n=== The Settings screen names the tools that keep their own settings ===\n";

// The Connection tab's first line names the tools that keep settings on their own screens, each a
// link to its Settings section there: the tools the registry holds that keep settings, every one.
$keeping_own = array();

foreach ( WPCPM_Tools::all() as $tool ) {
	if ( array() !== $tool->settings_keys() ) {
		$keeping_own[] = $tool->id();
	}
}

$named_there = defined( 'WPCPM_Settings_Screen::TOOLS_WITH_SETTINGS' ) ? WPCPM_Settings_Screen::TOOLS_WITH_SETTINGS : array();

sort( $keeping_own );
sort( $named_there );

ck( 'the tools the Connection tab names as keeping their settings on their own screens are the registry\'s tools that keep settings, all three of them',
    array( $named_there, count( $keeping_own ) ), array( $keeping_own, 3 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

exit( $fail ? 1 : 0 );
