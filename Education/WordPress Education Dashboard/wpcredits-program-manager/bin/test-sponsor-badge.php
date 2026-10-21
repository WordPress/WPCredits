<?php
/**
 * WPCPM_Sponsor_Badge: the badge a sponsor puts on its own site, served by the program's site at
 * `/sponsor-badge/<slug>` and `/sponsor-badge/<slug>-dark`, and the Badge card on the Sponsor
 * Dashboard.
 *
 * What is pinned here and why:
 *
 * - A name resolves to the exact slug first, then, for a name ending in `-dark`, to the sponsor
 *   at the stripped slug as the dark badge, so a company whose own slug ends in `-dark` still
 *   gets its light badge at its own name. An unknown name, an empty one and `-dark` alone are
 *   nothing. A name is read without regard to case, and a slug core stored percent-encoded (a
 *   name in another script) resolves however the request spells it: in a browser's capitals, in
 *   core's lowercase or as the raw bytes.
 * - Who has a badge is decided by the recorded tier: an Approved sponsor whose tier includes it,
 *   Supporter up, and a lapsed term keeps its badge. A Friend and a sponsor that is not
 *   Approved have none. The year is the current one while the term runs or there is none, so a
 *   term that runs into next year names this one, and the year the term ended once it has.
 * - The SVG is the draft the owner was shown, attribute for attribute, in both versions: no
 *   script, no external reference, no external font, the site's own font stack, and well-formed
 *   XML.
 * - `serve()` answers the image with exactly six headers, in order: the type, the `.svg` file name
 *   a save gets, a day of public cache, nosniff, a `default-src 'none'` policy and the length, the
 *   file name cleaned of what a header cannot carry. Every refusal is the same 404
 *   with an empty body, the switch off included, for a program manager too: the badge has no
 *   preview. The ceiling is claimed first, one key per address, 120 an hour: a caller over it is
 *   refused with 429 whatever the switch says, and the store is not queried for it.
 * - One rewrite rule at the top, admitting any name without a slash, with no extension since a
 *   host may answer a missing `.svg` path itself, and still with `.svg` or a trailing slash, the
 *   name handed on without either; and the query var. The rules are flushed once per site through
 *   a version option, which reads the same when the database hands it back as text, and once more
 *   on a site whose rules were written for the first rule, the address with the extension. Core
 *   writes the rules asked for at init on `wp_loaded`, so the version is stored there, after the
 *   write, and not at init: a request that ends between the two leaves the next one to ask again.
 * - `init()` hooks the handler on `parse_request`, which core fires once it has matched the rule
 *   and filled the query vars and before it runs the main query: a badge request never runs the
 *   home page's query, which the rule's one query var would otherwise leave it running and throw
 *   away. The handler reads the name off core's request and answers only a request core matched to
 *   the badge's own rule, so a page asked for with the query var in its query string is that page,
 *   and it leaves every other request alone. What it answers is the status and the body `serve()`
 *   decided, and nothing a theme or a plugin left in an open buffer; an empty buffer, as a page
 *   cache's is when the badge answers, is left open, so the cache sees the answer and stores it.
 *   The sponsor is looked up by its post's name, the one post of that name, and never by reading
 *   every sponsor of every status.
 * - The plugin loads the class and the Sponsors module starts it with its other front-end
 *   classes; bin/test-uninstall.php pins the uninstall's require and the option it deletes.
 * - The card: both badges on their grounds, the embed code linking to the Partners page (to the
 *   site's home while the page is not published), the Copy button carrying its words and its
 *   status. Nothing for a Friend, and nothing while the switch is off, for a program
 *   manager too. A lapsed term's card says the badge names the year it ended. The Copy script
 *   empties its status before it says the outcome, so the same words are announced again.
 *
 * The store and the tiers class are the real ones, over bin/stubs/posts.php; everything they and
 * the badge call is a stand-in. `WPCPM_Ceiling` counts claims per key the way
 * bin/test-sponsors-feed.php's does and records what it was asked, `WPCPM_Form_Guard::actor_key()`
 * answers the prefix and a fixed `$GLOBALS['ip_hash']` rather than hashing an address, and
 * `wp_date()` answers `$GLOBALS['today']` (6 October 2026) for today's date, so every year below
 * is counted from it. `bin/stubs/caps.php` makes the IDs in `$GLOBALS['manage']` program managers.
 * `handle()` prints and exits, so most checks read `serve()`, which answers the same thing as an
 * array. The checks that call `handle()` in this process ask it to return, and a shutdown function
 * fails the suite if it exits instead; what it sends when it does answer is read from this file
 * run as a child (`--handle-child`), under a `WP` stand-in that holds the rule core matched and
 * the query vars it filled. A second child (`--main-child`) runs a badge request in the order
 * core's `WP::main()` runs one, over the hooks `init()` registers, and says whether the main query
 * ran. `do_action()` is stood in by `run_hooks()` over the hooks `add_action()` recorded, and
 * `flush_rewrite_rules()` as core's own flush: asked before `wp_loaded`, it writes on that action.
 * bin/stubs/posts.php records every `get_posts()` call a step makes and matches `name` on the
 * post's name, as core's query does.
 *
 * Run from the plugin root:  php bin/test-sponsor-badge.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$GLOBALS['buckets']  = array(); // WPCPM_Ceiling stand-in: key => claims made.
$GLOBALS['claims']   = array(); // WPCPM_Ceiling stand-in: each claim asked for, key, limit and window.
$GLOBALS['ip_hash']  = 'hash';
$GLOBALS['today']    = '2026-10-06';
$GLOBALS['page_url'] = '';
$GLOBALS['rules']    = array(); // add_rewrite_rule() stand-in: regex, query and position of each rule.
$GLOBALS['flushed']  = 0;       // flush_rewrite_rules() stand-in: how many flushes were asked for.
$GLOBALS['hooks']    = array(); // add_action() and add_filter(), in call order: hook, callback, priority, args.
$GLOBALS['qv']       = array(); // get_query_var() stand-in: the request's query vars.
$GLOBALS['statuses'] = array(); // status_header() stand-in: each status sent.
$GLOBALS['uid']      = 0;
$GLOBALS['manage']   = array();
$GLOBALS['did']      = array(); // did_action() stand-in: how many times each hook has fired.

class WP_Error {
	private $code;

	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/** Core's request: the two fields the badge reads, the rewrite rule the request matched and the query vars it filled. */
class WP {
	public $matched_rule = '';
	public $query_vars   = array();
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr__( $text, $domain = null ) {
	return esc_attr( $text );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * The site's clock, pinned: today's date is `$GLOBALS['today']`, and a moment given is formatted
 * as it is, which is how `WPCPM_Sponsor_Tiers::format_day()` asks for a day.
 *
 * @param string   $format    A `date()` format.
 * @param int|null $timestamp A Unix time, or null for now.
 * @param mixed    $timezone  Unused: the pinned day has no timezone to shift it.
 * @return string
 */
function wp_date( $format, $timestamp = null, $timezone = null ) {
	if ( null === $timestamp ) {
		return 'Y-m-d' === $format ? $GLOBALS['today'] : gmdate( $format, strtotime( $GLOBALS['today'] . ' 12:00:00 UTC' ) );
	}

	return gmdate( $format, (int) $timestamp );
}

// No `date_format` is stored, so `get_option()` answers the default the tiers class passes and a
// day reads "January 31, 2026".
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	// How many times the rules had been written when the badge's version was stored.
	if ( 'wpcpm_badge_rewrite' === $key ) {
		$GLOBALS['stored_at_flushes'] = $GLOBALS['flushed'];
	}
	return true;
}

function home_url( $path = '' ) {
	return 'https://site.example' . $path;
}

/** Core's file-name cleaner, as far as a badge's name reaches it: the characters core strips, gone. */
function sanitize_file_name( $filename ) {
	$filename = str_replace( array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+' ), '', (string) $filename );

	return trim( (string) preg_replace( '/[\r\n\t -]+/', '-', $filename ), '.-_' );
}

function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
	$GLOBALS['rules'][] = array( $regex, $query, $after );
}

/**
 * Core's flush, as WP_Rewrite::flush_rules() makes it: asked for before `wp_loaded`, it hooks
 * itself on that action and writes the rules then, so they hold every rule the request adds.
 *
 * @param bool $hard Unused: the badge asks for a soft flush and the stand-in writes no file.
 */
function flush_rewrite_rules( $hard = true ) {
	if ( empty( $GLOBALS['did']['wp_loaded'] ) ) {
		add_action( 'wp_loaded', 'badge_test_write_rules' );
		return;
	}

	badge_test_write_rules();
}

/** The rules written: counted, with the rules at the top as they stand in memory while it runs. */
function badge_test_write_rules() {
	++$GLOBALS['flushed'];
	$GLOBALS['flushed_top'] = isset( $GLOBALS['wp_rewrite']->extra_rules_top ) ? array_keys( $GLOBALS['wp_rewrite']->extra_rules_top ) : null;
}

function did_action( $hook ) {
	return isset( $GLOBALS['did'][ $hook ] ) ? (int) $GLOBALS['did'][ $hook ] : 0;
}

/**
 * Core's do_action() over the hooks add_action() recorded: the hook counted as fired first, as
 * core counts it, then each callback registered for it in priority order, in the order added
 * within a priority, and once, as core keeps one registration of a callback at a priority.
 *
 * @param string $hook The hook.
 * @param mixed  ...$args What core passes.
 */
function run_hooks( $hook, ...$args ) {
	$GLOBALS['did'][ $hook ] = did_action( $hook ) + 1;

	$queue = array();
	foreach ( $GLOBALS['hooks'] as $order => $registered ) {
		if ( $hook === $registered[0] ) {
			$queue[ ( is_array( $registered[1] ) ? implode( '::', $registered[1] ) : (string) $registered[1] ) . '@' . $registered[2] ] = array( $registered[2], $order, $registered );
		}
	}

	uasort(
		$queue,
		static function ( $a, $b ) {
			return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0];
		}
	);

	foreach ( $queue as $entry ) {
		call_user_func_array( $entry[2][1], array_slice( $args, 0, (int) $entry[2][3] ) );
	}
}

function get_query_var( $query_var, $default_value = '' ) {
	return array_key_exists( $query_var, $GLOBALS['qv'] ) ? $GLOBALS['qv'][ $query_var ] : $default_value;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = array( $hook, $callback, $priority, $args );
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = array( $hook, $callback, $priority, $args );
}

function status_header( $code ) {
	$GLOBALS['statuses'][] = (int) $code;
}

class WPCPM_Settings {
	public static function get_value( $key, $default = null ) {
		return isset( $GLOBALS['settings'][ $key ] ) ? $GLOBALS['settings'][ $key ] : $default;
	}
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

/** The five classes the plugin's deactivation calls before its flush, for the check that runs it. */
class WPCPM_Modules {
	public static function deactivate() {
	}
}
class WPCPM_Tools {
	public static function deactivate() {
	}
}
class WPCPM_Mail_Log {
	public static function deactivate() {
	}
}
class WPCPM_Mail {
	public static function deactivate() {
	}
}
class WPCPM_Outcome_Figures {
	public static function deactivate() {
	}
}

/** Refuses a key's (limit + 1)th claim, as bin/test-sponsors-feed.php's does, and records each claim asked for. */
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$GLOBALS['claims'][] = array( $key, $limit, $window );

		$claimed = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;

		if ( $claimed + $amount > $limit ) {
			return false;
		}

		$GLOBALS['buckets'][ $key ] = $claimed + $amount;

		return true;
	}
}

/** The real class hashes the address and honors the trusted-proxy setting; the badge only passes the key on. */
class WPCPM_Form_Guard {
	public static function actor_key( $prefix ) {
		return (string) $prefix . ':' . $GLOBALS['ip_hash'];
	}
}

/** The page's address while it is published, '' while it is not. */
class WPCPM_Sponsors_Page {
	public static function page_url() {
		return (string) $GLOBALS['page_url'];
	}
}

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
// The shell the card is drawn in.
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-badge.php';

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

// handle() exits once it has answered. A check that expects it to return sets this first, so a
// handle() that answers a request it should have left alone fails the suite, rather than ending
// it early with the status of a pass.
register_shutdown_function(
	static function () {
		if ( ! empty( $GLOBALS['handle_must_return'] ) ) {
			echo "FAIL handle() answered a request that names no badge, and exited\n";
			exit( 1 );
		}
	}
);

// Three Approved sponsors: a Supporter with a term, a Champion without one whose own slug ends in
// -dark, a Friend. A Paused Champion. An Approved Backer whose term ended in January. A
// Supporter whose name is in another script: the hiragana a and i, written as escapes so this
// file stays in ASCII.
$kana_name = "\u{3042}\u{3044}";
WPCPM_Sponsors_Index::write(
	array(
		'spoBBBB0000000001' => array( 'name' => 'Acme Tools', 'status' => 'Approved', 'tier' => 'supporter', 'tier_until' => '2027-03-31' ),
		'spoBBBB0000000002' => array( 'name' => 'Night Dark', 'status' => 'Approved', 'tier' => 'champion' ),
		'spoBBBB0000000003' => array( 'name' => 'Echo Inc', 'status' => 'Approved', 'tier' => 'mentor' ),
		'spoBBBB0000000004' => array( 'name' => 'Paused Co', 'status' => 'Paused', 'tier' => 'champion' ),
		'spoBBBB0000000005' => array( 'name' => 'Lapsed Co', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-01-31' ),
		'spoBBBB0000000006' => array( 'name' => $kana_name, 'status' => 'Approved', 'tier' => 'supporter' ),
	),
	time()
);

// Core's sanitize_title() stores a name in another script as its UTF-8 bytes, percent-encoded in
// lowercase; bin/stubs/posts.php's stand-in drops them. So the post is given the name core would
// have given it, and patch() copies it into the row, as the store copies a post's name.
$GLOBALS['posts'][ WPCPM_Sponsors_Index::post_id_of( 'spoBBBB0000000006' ) ]->post_name = '%e3%81%82%e3%81%84';
WPCPM_Sponsors_Index::patch( 'spoBBBB0000000006', array() );

$acme   = WPCPM_Sponsors_Index::row( 'spoBBBB0000000001' );
$night  = WPCPM_Sponsors_Index::row( 'spoBBBB0000000002' );
$echo   = WPCPM_Sponsors_Index::row( 'spoBBBB0000000003' );
$paused = WPCPM_Sponsors_Index::row( 'spoBBBB0000000004' );
$lapsed = WPCPM_Sponsors_Index::row( 'spoBBBB0000000005' );
$kana   = WPCPM_Sponsors_Index::row( 'spoBBBB0000000006' );
$B      = 'WPCPM_Sponsor_Badge';

// A badge request that is answered ends in `exit`, which no harness can catch, so what handle()
// sends is read from this same file run as a child, as bin/test-institution-agreement.php reads a
// download. Two buffers are open when it answers, as a site can leave them: a page cache's, started
// before the plugin runs and still empty, and inside it one a theme left bytes in. The child prints
// what handle() sends, then, each on a line of its own, the statuses it sent and the buffers still
// open as the request ends with the bytes they hold: what a page cache stores and sends. With
// `--refused` it asks for a name no sponsor holds.
if ( in_array( '--handle-child', (array) ( $argv ?? array() ), true ) ) {
	register_shutdown_function(
		static function () {
			// Read before this function prints into the buffer it reads.
			$buffered = ob_get_level() . ' ' . ( ob_get_level() > 0 ? (int) ob_get_length() : 0 );

			echo "\nstatus " . implode( ',', $GLOBALS['statuses'] ) . "\nbuffered " . $buffered;
		}
	);

	$GLOBALS['wp']               = new WP();
	$GLOBALS['wp']->matched_rule = WPCPM_Sponsor_Badge::RULE;
	$GLOBALS['wp']->query_vars   = array( 'wpcpm_badge' => in_array( '--refused', (array) $argv, true ) ? 'nobody' : 'acme-tools' );

	ob_start();
	ob_start();
	echo 'What a theme left in a buffer.';

	WPCPM_Sponsor_Badge::handle();
	exit( 1 );
}

// A badge request in the order core's WP::main() makes it, over the hooks the badge's own init()
// registers: the request parsed, the rule matched and the query vars filled, then parse_request
// with core's request; only then the main query, from those query vars, which is what
// get_query_var() reads from then on; then the template's hooks. The child prints whether the
// main query ran before the request ended, and the statuses sent.
if ( in_array( '--main-child', (array) ( $argv ?? array() ), true ) ) {
	register_shutdown_function(
		static function () {
			echo "\nmain query " . ( empty( $GLOBALS['main_query'] ) ? 'not run' : 'run' ) . "\nstatus " . implode( ',', $GLOBALS['statuses'] );
		}
	);

	$GLOBALS['hooks'] = array();
	WPCPM_Sponsor_Badge::init();

	$GLOBALS['wp']               = new WP();
	$GLOBALS['wp']->matched_rule = WPCPM_Sponsor_Badge::RULE;
	$GLOBALS['wp']->query_vars   = array( 'wpcpm_badge' => 'acme-tools' );

	run_hooks( 'parse_request', $GLOBALS['wp'] );

	$GLOBALS['main_query'] = true;
	$GLOBALS['qv']         = $GLOBALS['wp']->query_vars;

	run_hooks( 'wp', $GLOBALS['wp'] );
	run_hooks( 'template_redirect' );
	exit( 1 );
}

echo "=== Resolving a name ===\n";
ck( 'an exact slug is the light badge', array( $B::resolve( 'acme-tools' )['row']['name'], $B::resolve( 'acme-tools' )['dark'] ), array( 'Acme Tools', false ) );
ck( 'a name ending in -dark with a sponsor at the stripped slug is the dark badge', array( $B::resolve( 'acme-tools-dark' )['row']['name'], $B::resolve( 'acme-tools-dark' )['dark'] ), array( 'Acme Tools', true ) );
ck( 'a slug that itself ends in -dark is that sponsor\'s light badge', array( $B::resolve( 'night-dark' )['row']['name'], $B::resolve( 'night-dark' )['dark'], $B::resolve( 'night-dark-dark' )['dark'] ), array( 'Night Dark', false, true ) );
ck( 'an unknown name is nothing', array( $B::resolve( 'nobody' ), $B::resolve( '' ), $B::resolve( '-dark' ) ), array( null, null, null ) );
// The rule hands the name on as the request spelled it: a browser percent-encodes another script in
// capitals, core stored it in lowercase, and a client that sends the raw bytes sends neither.
ck( 'a slug stored percent-encoded resolves from the capitals a browser sends, from the lowercase core stored and from the raw name, light and dark', array(
	$B::resolve( '%E3%81%82%E3%81%84' )['row']['name'],
	$B::resolve( '%e3%81%82%e3%81%84' )['row']['name'],
	$B::resolve( $kana_name )['row']['name'],
	$B::resolve( '%E3%81%82%E3%81%84-dark' )['dark'],
), array( $kana_name, $kana_name, $kana_name, true ) );
ck( 'a name is read without regard to case: Acme-Tools is the same light badge', array( $B::resolve( 'Acme-Tools' )['row']['name'], $B::resolve( 'Acme-Tools' )['dark'], $B::resolve( 'ACME-TOOLS-DARK' )['dark'] ), array( 'Acme Tools', false, true ) );

echo "\n=== Eligibility and the year ===\n";
// The year is today's while a term runs or there is none, and the year a term ended once it has: a
// term that runs to next March names this year, not the year it ends in.
ck( 'a Supporter whose term runs to March next year: eligible, and the badge names the current year', array( $B::eligible( $acme ), $B::year( $acme, '2026-10-06' ) ), array( true, '2026' ) );
ck( 'a Champion without a term: eligible, the current year', array( $B::eligible( $night ), $B::year( $night, '2026-10-06' ) ), array( true, '2026' ) );
ck( 'a lapsed Backer keeps its badge and names the year its term ended', array( $B::eligible( $lapsed ), $B::year( $lapsed, '2026-10-06' ) ), array( true, '2026' ) );
ck( 'a term that ended in an earlier year names that year, not the current one', $B::year( array_merge( $lapsed, array( 'tier_until' => '2025-06-30' ) ), '2026-10-06' ), '2025' );
ck( 'a Friend has none, and neither has a Paused Champion', array( $B::eligible( $echo ), $B::eligible( $paused ) ), array( false, false ) );

echo "\n=== The SVG ===\n";
$svg = $B::svg( 'supporter', '2027', false );
ck( 'the light badge: the frame, the tile, the mark, the eyebrow, the tier and the year', array(
	0 === strpos( $svg, '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="80" viewBox="0 0 300 80" role="img" aria-labelledby="wpcpm-badge-title"><title id="wpcpm-badge-title">WordPress Credits Supporter 2027</title>' ),
	false !== strpos( $svg, '<rect x=".5" y=".5" width="299" height="79" rx="8" fill="#ffffff" stroke="#e4e4e4"/>' ),
	false !== strpos( $svg, '<rect x="16" y="16" width="48" height="48" rx="10" fill="#3858e9"/>' ),
	false !== strpos( $svg, '<g transform="translate(24 24) scale(0.26118)" fill="#ffffff"><path d="M8.708 61.26c0 20.802' ),
	false !== strpos( $svg, '<text x="80" y="33" font-size="11" font-weight="600" letter-spacing="1" fill="#656a71">WORDPRESS CREDITS</text>' ),
	false !== strpos( $svg, '<text x="80" y="58" font-size="20" font-weight="600" fill="#1e1e1e">Supporter <tspan font-weight="400" fill="#656a71">2027</tspan></text></g></svg>' ),
), array( true, true, true, true, true, true ) );
$dark = $B::svg( 'champion', '2026', true );
ck( 'the dark badge: ink ground, its rule, white tier, gray eyebrow and year', array(
	false !== strpos( $dark, 'fill="#1e1e1e" stroke="#40464d"/>' ),
	false !== strpos( $dark, 'fill="#979aa1">WORDPRESS CREDITS</text>' ),
	false !== strpos( $dark, 'fill="#ffffff">Champion <tspan font-weight="400" fill="#979aa1">2026</tspan>' ),
), array( true, true, true ) );
// No `font-face` of either kind, the CSS at-rule or SVG 1.1's element: absent here means the
// at-rule is absent too, and the needle carries no at sign for bin/test-fixtures.php to read as a
// WordPress.org handle.
ck( 'no script, no external reference, no external font, and the site\'s own font stack', array( stripos( $svg, '<script' ), stripos( $svg, 'http' ) === strpos( $svg, 'http://www.w3.org/2000/svg' ) && 1 === substr_count( $svg, 'http' ), stripos( $svg, 'font-face' ), false !== strpos( $svg, "font-family=\"-apple-system, system-ui, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif\"" ) ), array( false, true, false, true ) );
ck( 'the SVG is well-formed XML', false !== simplexml_load_string( $svg ), true );

echo "\n=== Serving ===\n";
$GLOBALS['buckets'] = array();
$served = $B::serve( 'acme-tools', '2026-10-06' );
ck( 'a light badge is served as SVG, named as a .svg file for whoever saves it, cached a day, nosniff, no script allowed, no body but the image', array( $served['status'], $served['headers'], 0 === strpos( $served['body'], '<svg ' ) ), array( 200, array( 'Content-Type: image/svg+xml; charset=utf-8', 'Content-Disposition: inline; filename="wordpress-credits-acme-tools.svg"', 'Cache-Control: public, max-age=86400', 'X-Content-Type-Options: nosniff', 'Content-Security-Policy: default-src \'none\'', 'Content-Length: ' . strlen( $served['body'] ) ), true ) );
$served_dark = $B::serve( 'acme-tools-dark', '2026-10-06' );
ck( 'the dark badge is served the same way, its file named for the dark version', array( $served_dark['status'], false !== strpos( $served_dark['body'], 'fill="#1e1e1e" stroke="#40464d"' ), $served_dark['headers'][1] ), array( 200, true, 'Content-Disposition: inline; filename="wordpress-credits-acme-tools-dark.svg"' ) );
ck( 'a Friend, a paused sponsor, an unknown name: the same 404 with the same empty body', array( $B::serve( 'echo-inc' )['status'], $B::serve( 'paused-co' )['status'], $B::serve( 'nobody' )['status'], $B::serve( 'echo-inc' )['body'], $B::serve( 'nobody' )['headers'] ), array( 404, 404, 404, '', array( 'Cache-Control: no-cache, must-revalidate, max-age=0' ) ) );
$served_kana = $B::serve( '%E3%81%82%E3%81%84', '2026-10-06' );
ck( 'a percent-encoded slug\'s badge is served, at an address built from the slug as stored, which is already a URL, and under a file name cleaned of what a header cannot carry', array( $served_kana['status'], $B::url( $kana['slug'] ), $served_kana['headers'][1] ), array( 200, 'https://site.example/sponsor-badge/%e3%81%82%e3%81%84', 'Content-Disposition: inline; filename="wordpress-credits-e38182e38184.svg"' ) );
$GLOBALS['settings'] = array();
ck( 'the switch off: 404 for everyone, even an eligible sponsor', $B::serve( 'acme-tools' )['status'], 404 );
$GLOBALS['uid'] = 1; $GLOBALS['manage'] = array( 1 );
ck( 'and for a program manager too: the badge has no preview', array( current_user_can( WPCPM_Roles::CAP_MANAGE ), $B::serve( 'acme-tools' )['status'] ), array( true, 404 ) );
$GLOBALS['uid'] = 0; $GLOBALS['manage'] = array();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$GLOBALS['buckets']  = array( 'sponsor-badge:hash' => 120 );
ck( 'over the ceiling: 429 with a Retry-After, before the store is read', array( $B::serve( 'acme-tools' )['status'], $B::serve( 'acme-tools' )['headers'] ), array( 429, array( 'Retry-After: 3600', 'Cache-Control: no-cache, must-revalidate, max-age=0' ) ) );
// The claim comes first: over the ceiling with the switch off is still a 429, and with the
// store's request cache dropped, no query for the rows is made on the way to it.
$GLOBALS['settings'] = array();
$GLOBALS['claims']   = array();
WPCPM_Sponsors_Index::forget();
unset( $GLOBALS['last_get_posts'] );
ck( 'the ceiling is claimed first, 120 an hour per address: over it, the switch off still answers 429, and the store is not queried', array( $B::serve( 'acme-tools' )['status'], $GLOBALS['claims'], isset( $GLOBALS['last_get_posts'] ) ), array( 429, array( array( 'sponsor-badge:hash', 120, HOUR_IN_SECONDS ) ), false ) );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$GLOBALS['buckets'] = array();

// A badge is fetched by the visitors of other sites, each fetch a request of its own: the sponsor
// is read as the one post whose name is the slug, never as every sponsor of every status. A
// dark badge asks for the whole name first, then the name without -dark; a name in another script
// is asked for as core stores it.
$looked_up = array();
foreach ( array( 'acme-tools', 'acme-tools-dark', '%E3%81%82%E3%81%84' ) as $asked ) {
	WPCPM_Sponsors_Index::forget();
	$GLOBALS['get_posts_calls'] = array();
	$status                     = $B::serve( $asked, '2026-10-06' )['status'];
	$looked_up[]                = array(
		$status,
		array_map(
			static function ( $call ) {
				return array( $call['post_type'], $call['post_status'], isset( $call['name'] ) ? $call['name'] : null, isset( $call['numberposts'] ) ? $call['numberposts'] : null );
			},
			$GLOBALS['get_posts_calls']
		),
	);
}
ck( 'a badge looks its sponsor up by the slug, the one post of that name, and never reads every sponsor', $looked_up, array(
	array( 200, array( array( 'wpcpm_sponsor_co', 'private', 'acme-tools', 1 ) ) ),
	array( 200, array( array( 'wpcpm_sponsor_co', 'private', 'acme-tools-dark', 1 ), array( 'wpcpm_sponsor_co', 'private', 'acme-tools', 1 ) ) ),
	array( 200, array( array( 'wpcpm_sponsor_co', 'private', '%e3%81%82%e3%81%84', 1 ) ) ),
) );
// bin/stubs/posts.php folds a name query as core's does, so a spelling core folds into a stored
// name finds that post here as it would on a site, and the lookup's own check of the post's name
// is what keeps it from being a badge.
ck( 'a name core\'s query folds into a stored one finds that post, as on a site, and is still no badge: acme.tools and Acme-Tools! name nobody', array(
	count( get_posts( array( 'post_type' => 'wpcpm_sponsor_co', 'post_status' => 'private', 'name' => 'Acme.Tools' ) ) ),
	$B::resolve( 'acme.tools' ),
	$B::resolve( 'Acme-Tools!' ),
), array( 1, null, null ) );
$GLOBALS['buckets'] = array();

echo "\n=== The rewrite ===\n";
$GLOBALS['rules'] = array(); $GLOBALS['flushed'] = 0; $GLOBALS['opts'] = array();
$B::add_rules();
ck( 'one rule at the top: sponsor-badge/<name>, with or without .svg, to the query var', $GLOBALS['rules'], array( array( '^sponsor-badge/([^/]+?)(?:\.svg)?/?$', 'index.php?wpcpm_badge=$matches[1]', 'top' ) ) );
// Matched as core's WP::parse_request() matches a rule, against the path as the request sent it.
$rule = isset( $GLOBALS['rules'][0][0] ) ? $GLOBALS['rules'][0][0] : '';
ck( 'the rule admits any name without a slash: percent-encoded, in capitals; not a deeper path', array(
	preg_match( "#^$rule#", 'sponsor-badge/%E3%81%82%E3%81%84' ),
	preg_match( "#^$rule#", 'sponsor-badge/Acme-Tools-dark' ),
	preg_match( "#^$rule#", 'sponsor-badge/acme/tools' ),
), array( 1, 1, 0 ) );
// The address has no extension: a host that serves static files itself answers a missing `.svg`
// path with its own 404 before WordPress sees the request. Where a host passes the `.svg` form on,
// it still reaches the badge, and so does a trailing slash: the name the rule hands on is the same.
$names = array();
foreach ( array( 'sponsor-badge/acme-tools', 'sponsor-badge/acme-tools.svg', 'sponsor-badge/acme-tools/', 'sponsor-badge/acme-tools-dark.svg' ) as $path ) {
	$names[] = 1 === preg_match( '#^' . $B::RULE . '#', $path, $matched ) ? $matched[1] : null;
}
ck( 'the name reaches the query var without the extension or the slash: acme-tools, acme-tools.svg and acme-tools/ all name acme-tools', $names, array( 'acme-tools', 'acme-tools', 'acme-tools', 'acme-tools-dark' ) );
ck( 'the query var is registered', in_array( 'wpcpm_badge', $B::query_vars( array( 'p' ) ), true ), true );
// Each request below starts with no hook registered and none fired, and runs init's step, then
// wp_loaded, as core does.
$GLOBALS['hooks'] = array(); $GLOBALS['did'] = array();
$B::maybe_flush();
run_hooks( 'wp_loaded' );
$stored = $GLOBALS['opts']['wpcpm_badge_rewrite'];
// The next request reads the version back from the database, where an option is text: the
// string '1' must read as the version the int was, or every request would flush the rules.
$GLOBALS['opts']['wpcpm_badge_rewrite'] = (string) $stored;
$GLOBALS['hooks'] = array(); $GLOBALS['did'] = array();
$B::maybe_flush();
run_hooks( 'wp_loaded' );
ck( 'the rules are flushed once, and the version is stored; read back as text on the next request, it is the same version', array( $GLOBALS['flushed'], $stored ), array( 1, $B::REWRITE_VERSION ) );
// A site whose rules were written for the first rule, the one that asked for the extension, writes
// them once more, so the address without one reaches the badge there too.
$GLOBALS['opts']['wpcpm_badge_rewrite'] = '1';
$GLOBALS['flushed']                     = 0;
for ( $request = 1; $request <= 2; $request++ ) {
	$GLOBALS['hooks'] = array(); $GLOBALS['did'] = array();
	$B::maybe_flush();
	run_hooks( 'wp_loaded' );
}
ck( 'a site whose rules were flushed for the first rule, the address with the extension, flushes them again, once', array( $GLOBALS['flushed'], (int) $GLOBALS['opts']['wpcpm_badge_rewrite'] ), array( 1, $B::REWRITE_VERSION ) );
// Core writes rules asked for at init on wp_loaded: a version stored at init would claim rules a
// request that ended between the two never wrote, and no later request would ask for them again.
$GLOBALS['opts']    = array();
$GLOBALS['flushed'] = 0;
$GLOBALS['hooks']   = array(); $GLOBALS['did'] = array();
$B::maybe_flush();
$at_init = array( $GLOBALS['flushed'], array_key_exists( 'wpcpm_badge_rewrite', $GLOBALS['opts'] ) );
$GLOBALS['hooks'] = array(); $GLOBALS['did'] = array();
$B::maybe_flush();
run_hooks( 'wp_loaded' );
ck( 'the version is not stored at init, where the flush is only asked for, but on wp_loaded once core has written the rules, so a request that ends before wp_loaded leaves the next to ask again', array( $at_init, $GLOBALS['flushed'], (int) $GLOBALS['opts']['wpcpm_badge_rewrite'], $GLOBALS['stored_at_flushes'] ), array( array( 0, false ), 1, $B::REWRITE_VERSION, 1 ) );
// Asked for once wp_loaded has fired, the flush writes at once, and so the version is stored at once.
$GLOBALS['opts']    = array();
$GLOBALS['flushed'] = 0;
$GLOBALS['hooks']   = array(); $GLOBALS['did'] = array( 'wp_loaded' => 1 );
$B::maybe_flush();
ck( 'asked for after wp_loaded, the rules are written and the version stored at once, with nothing left hooked', array( $GLOBALS['flushed'], (int) $GLOBALS['opts']['wpcpm_badge_rewrite'], $GLOBALS['stored_at_flushes'], $GLOBALS['hooks'] ), array( 1, $B::REWRITE_VERSION, 1, array() ) );
$GLOBALS['did'] = array();
ck( 'url() is the site\'s address for both versions, with no extension', array( $B::url( 'acme-tools' ), $B::url( 'acme-tools', true ) ), array( 'https://site.example/sponsor-badge/acme-tools', 'https://site.example/sponsor-badge/acme-tools-dark' ) );
$GLOBALS['hooks'] = array();
$B::init();
ck( 'init() hooks the rules and the flush on init, the query var, and the handler on parse_request, before core runs the main query', array_map( function ( $h ) { return array( $h[0], $h[1][1], $h[2] ); }, $GLOBALS['hooks'] ), array( array( 'init', 'add_rules', 10 ), array( 'init', 'maybe_flush', 11 ), array( 'query_vars', 'query_vars', 10 ), array( 'parse_request', 'handle', 10 ) ) );
// Whose request it is, by the rule core matched, which it records by the rule's own string.
$GLOBALS['wp']               = new WP();
$GLOBALS['wp']->matched_rule = $B::RULE;
$own                         = $B::is_badge_request();
$GLOBALS['wp']->matched_rule = '(.?.+?)(?:/([0-9]+))?/?$';
$a_page                      = $B::is_badge_request();
$GLOBALS['wp']->matched_rule = '';
$no_rule                     = $B::is_badge_request();
$GLOBALS['wp']               = (object) array( 'matched_rule' => $B::RULE );
$not_core                    = $B::is_badge_request();
unset( $GLOBALS['wp'] );
$unparsed_request = $B::is_badge_request();
ck( 'is_badge_request() is true for the badge\'s own rule alone: not a page\'s rule, no rule, something that is not core\'s request, or no request', array( $own, $a_page, $no_rule, $not_core, $unparsed_request ), array( true, false, false, false, false ) );
// Every front-end request passes through the handler, so it must return, printing and sending
// nothing, for every request but the badge's own: a page asked for with the query var in its query
// string, which is that page and never an image a cache could store in the page's place; the
// badge's own rule with the var sent again as an array, since a query string's value outranks the
// rule's; a request whose query var is empty; and one no request was parsed for.
$GLOBALS['handle_must_return'] = true;
$GLOBALS['statuses']           = array();
$GLOBALS['claims']             = array();
$GLOBALS['wp']                 = new WP();
$GLOBALS['wp']->matched_rule   = '(.?.+?)(?:/([0-9]+))?/?$';
$GLOBALS['wp']->query_vars     = array( 'wpcpm_badge' => 'acme-tools' );
ob_start(); $B::handle(); $on_a_page = ob_get_clean();
$GLOBALS['wp']->matched_rule = $B::RULE;
$GLOBALS['wp']->query_vars   = array( 'wpcpm_badge' => array( 'acme-tools' ) );
ob_start(); $B::handle(); $not_string = ob_get_clean();
$GLOBALS['wp']->query_vars = array();
ob_start(); $B::handle(); $without = ob_get_clean();
unset( $GLOBALS['wp'] );
$GLOBALS['qv'] = array( 'wpcpm_badge' => 'acme-tools' );
ob_start(); $B::handle(); $unparsed = ob_get_clean();
$GLOBALS['handle_must_return'] = false;
$GLOBALS['qv']                 = array();
ck( 'handle() leaves every other request alone: nothing printed, no status sent, no claim made', array( $on_a_page, $not_string, $without, $unparsed, $GLOBALS['statuses'], $GLOBALS['claims'] ), array( '', '', '', '', array(), array() ) );

echo "\n=== Answering a badge request ===\n";
// The headers cannot be read back under CLI, which discards them; serve()'s checks above pin them.
// The child's error output is folded into what it prints, so a warning fails the check, not the
// suite's silence.
$child        = array();
$child_status = null;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --handle-child 2>&1', $child, $child_status );
// The year the child's badge names is year()'s, on the suite's day; the year's own checks are above.
$child_svg = $B::svg( 'supporter', $B::year( $acme, $GLOBALS['today'] ), false );
ck( 'a badge request is answered with the SVG alone, under the status serve() decided, and ends there: the bytes a theme left in a buffer are dropped, and the empty buffer a page cache started first is left open, holding the SVG whole', array( $child_status, $child ), array( 0, array( $child_svg, 'status 200', 'buffered 1 ' . strlen( $child_svg ) ) ) );
$refused        = array();
$refused_status = null;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --handle-child --refused 2>&1', $refused, $refused_status );
ck( 'and a name with no badge is answered with the 404 and nothing at all, the stray bytes included, the page cache\'s buffer still open and empty', array( $refused_status, $refused ), array( 0, array( '', 'status 404', 'buffered 1 0' ) ) );
// The rule's one query var is none the main query reads, so a request that reached the main query
// would run the home page's: its posts, their count, their meta and their terms, all thrown away.
$main        = array();
$main_status = null;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --main-child 2>&1', $main, $main_status );
ck( 'a badge request run in core\'s order is answered on parse_request, before the main query: the home page\'s query is never run', array( $main_status, array_slice( $main, -2 ) ), array( 0, array( 'main query not run', 'status 200' ) ) );

echo "\n=== The card ===\n";
$GLOBALS['page_url'] = 'https://site.example/partners/';
ob_start(); $B::render( 'spoBBBB0000000001', array() ); $card = ob_get_clean();
ck( 'the card: the two badges on their grounds, the embed code, the Copy button and its status', array(
	0 === strpos( $card, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-badge" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Badge</h3><span class="wpcpm-sponsor__state">Supporter badge for 2026</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	false !== strpos( $card, '<figure class="wpcpm-sponsor-badge__figure wpcpm-sponsor-badge__figure--light"><img src="https://site.example/sponsor-badge/acme-tools" alt="WordPress Credits Supporter 2026 badge, for a light background" width="300" height="80" /><figcaption>On a light background</figcaption></figure>' ),
	false !== strpos( $card, '<figure class="wpcpm-sponsor-badge__figure wpcpm-sponsor-badge__figure--dark"><img src="https://site.example/sponsor-badge/acme-tools-dark" alt="WordPress Credits Supporter 2026 badge, for a dark background" width="300" height="80" /><figcaption>On a dark background</figcaption></figure>' ),
	false !== strpos( $card, '<textarea id="wpcpm-sponsor-badge-embed" class="wpcpm-sponsor-badge__embed" rows="3" readonly="readonly">&lt;a href=&quot;https://site.example/partners/&quot;&gt;&lt;img src=&quot;https://site.example/sponsor-badge/acme-tools&quot; alt=&quot;WordPress Credits Supporter 2026&quot; width=&quot;300&quot; height=&quot;80&quot;&gt;&lt;/a&gt;</textarea>' ),
	false !== strpos( $card, '<button type="button" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-copy="#wpcpm-sponsor-badge-embed" data-wpcpm-copied="Copied." data-wpcpm-select-hint="Selected. Copy it with your keyboard.">Copy the embed code</button> <span class="wpcpm-copy__status" role="status" aria-live="polite"></span>' ),
), array( true, true, true, true, true ) );
ob_start(); $B::render( 'spoBBBB0000000003', array() ); $none = ob_get_clean();
ck( 'a Friend gets no card', $none, '' );
$GLOBALS['settings'] = array();
ob_start(); $B::render( 'spoBBBB0000000001', array() ); $off = ob_get_clean();
ck( 'the switch off: no card', $off, '' );
$GLOBALS['uid'] = 1; $GLOBALS['manage'] = array( 1 );
ob_start(); $B::render( 'spoBBBB0000000001', array( 'can_manage' => true ) ); $off_manager = ob_get_clean();
ck( 'and none for a program manager either', array( current_user_can( WPCPM_Roles::CAP_MANAGE ), $off_manager ), array( true, '' ) );
$GLOBALS['uid'] = 0; $GLOBALS['manage'] = array();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$GLOBALS['page_url'] = '';
ob_start(); $B::render( 'spoBBBB0000000001', array() ); $nopage = ob_get_clean();
ck( 'without a published page the embed links home', false !== strpos( $nopage, '&lt;a href=&quot;https://site.example/&quot;&gt;' ), true );
ob_start(); $B::render( 'spoBBBB0000000005', array() ); $lapsed_card = ob_get_clean();
ck( 'a lapsed term: the card says the badge names that year', false !== strpos( $lapsed_card, 'Your Backer term ended on January 31, 2026; the badge names that year until your program contact renews it.' ), true );
// The day the card speaks of can be given, as every function that reads today allows: Acme's term,
// open on the suite's day, has ended by May 2028, and the badge names the year it ended, not that
// day's year.
ob_start(); $B::render( 'spoBBBB0000000001', array(), '2028-05-01' ); $later = ob_get_clean();
ck( 'a day given is the day the card speaks of: no note while the term runs, the note once it has ended, and then the year the term ended', array( strpos( $card, 'term ended' ), false !== strpos( $later, 'Your Supporter term ended on March 31, 2027; the badge names that year' ), false !== strpos( $later, 'alt="WordPress Credits Supporter 2027 badge, for a light background"' ) ), array( false, true, true ) );
// The row (the design the owner approved on 27 September 2026): folded, titled Badge, the tier the
// badge names and its year read by year(), so a lapsed term's row names the year it ended, as the
// badge does; no action, since the Copy button is inside. A flash naming the card opens it.
ob_start(); $B::render( 'spoBBBB0000000001', array( 'open' => 'badge' ) ); $badge_flashed = ob_get_clean();
ck( 'the row names the tier and the year the badge names, the lapsed term\'s year included, offers no action, and the card opens when a flash names it', array(
	false !== strpos( $lapsed_card, '<h3 class="wpcpm-group__title">Badge</h3><span class="wpcpm-sponsor__state">Backer badge for 2026</span>' ),
	false !== strpos( $later, '<span class="wpcpm-sponsor__state">Supporter badge for 2027</span>' ),
	array( strpos( $card, 'wpcpm-sponsor__row-action' ), substr( $card, -strlen( '</p></div></details></section>' ) ) ),
	false !== strpos( $badge_flashed, '<details id="wpcpm-sponsor-badge" class="wpcpm-group wpcpm-group__disclosure" open>' ),
	strpos( $card, 'Your badge' ),
), array( true, true, array( false, '</p></div></details></section>' ), true, false ) );

echo "\n=== Wiring ===\n";
// Read from the source, as bin/test-sponsors-page.php reads its own: boot() starts the whole
// Sponsors module, which is more than this suite stands in for. The uninstall's require and the
// option it deletes are bin/test-uninstall.php's, which runs the file whole.
$module_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$boot       = substr( $module_src, (int) strpos( $module_src, 'public function boot()' ) );
$boot       = substr( $boot, 0, (int) strpos( $boot, "\n\t}\n" ) );
$loader     = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
ck( 'the plugin loads the class and the module starts it with its front-end classes', array(
	false !== strpos( $loader, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-badge.php';" ),
	1 === preg_match( "/foreach \( array\([^)]*'WPCPM_Sponsor_Badge'[^)]*\) as \\\$front \)/", $boot ),
), array( true, true ) );
// The plugin's deactivation, its own function read from the loader and run over the stand-ins
// above: this request's init added the badge's rule, and the flush must write the rules without
// it, or the stored rules keep it once the plugin is gone, its query var no longer public, and
// `/sponsor-badge/<name>` answers the home page. Another rule stays, and with no rewrite object at
// all the flush still runs.
$deactivate = substr( $loader, (int) strpos( $loader, 'function wpcpm_deactivate()' ) );
$deactivate = substr( $deactivate, 0, (int) strpos( $deactivate, "\n}\n" ) + 2 );
eval( $deactivate );
$GLOBALS['wp_rewrite'] = (object) array(
	'extra_rules_top' => array(
		'^elsewhere/?$' => 'index.php?elsewhere=1',
		$B::RULE        => 'index.php?wpcpm_badge=$matches[1]',
	),
);
$GLOBALS['flushed']    = 0;
// Deactivation is pressed on the Plugins screen, a request past wp_loaded, where core writes a flush at once.
$GLOBALS['did']        = array( 'wp_loaded' => 1 );
wpcpm_deactivate();
$with_rules = array( $GLOBALS['flushed'], $GLOBALS['flushed_top'] );
unset( $GLOBALS['wp_rewrite'] );
wpcpm_deactivate();
ck( 'deactivation takes the badge\'s rule out of the rules in memory before it flushes them, another rule staying, and flushes all the same with no rewrite object', array( $with_rules, $GLOBALS['flushed'] ), array( array( 1, array( '^elsewhere/?$' ) ), 2 ) );

echo "\n=== House rules ===\n";
$src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-badge.php' );
ck( 'the badge class names no base, holds no dash, and the mark is one path', array( strpos( $src, 'WPCPM_Airtable' ), preg_match( '/\x{2013}|\x{2014}/u', $src ), substr_count( $src, 'const MARK_PATH' ) ), array( false, 0, 1 ) );
$js = (string) file_get_contents( __DIR__ . '/../assets/js/copy.js' );
ck( 'copy.js tries the clipboard in a secure context and falls back to a selection', array( false !== strpos( $js, 'navigator.clipboard' ), false !== strpos( $js, 'isSecureContext' ), false !== strpos( $js, 'data-wpcpm-copy' ), false !== strpos( $js, '.select()' ) ), array( true, true, true, true ) );
// A live region speaks when its text changes, and "Copied." written over "Copied." is no change: the
// status is emptied first and filled a moment later, so a second press is announced too.
$say = substr( $js, (int) strpos( $js, 'function say(' ) );
$say = substr( $say, 0, (int) strpos( $say, "\n\t}\n" ) );
ck( 'copy.js empties the status before it says the outcome, so the same words are announced again', 1 === preg_match( "/status\\.textContent = '';\\s*window\\.setTimeout\\( function \\(\\) \\{\\s*status\\.textContent = text;/", $say ), true );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
