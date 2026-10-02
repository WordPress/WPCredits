<?php
/**
 * The Overview, the page the WPCredits Program menu opens: what waits for a decision, when each
 * sync last ran and runs next, whether each tool can run, and the way to Settings.
 *
 * What each block pins, and why:
 *
 * - **Four cards, in the order a manager reads them**, Waiting for a decision, Syncs, Tools and
 *   Settings, under the heading the menu gives the page, and only for a manager.
 * - **The counts are the Administrator Dashboard's.** The cards class is the real one, its
 *   `collect()` and `counts()` reading stand-ins for the classes that own the data
 *   (bin/stubs/overview-reads.php), so a number here is the attention strip's number there: the
 *   queues with something in them, in the strip's order, each its label and then its count in
 *   parentheses, as core's list tables print their views, linking to where the queue is listed
 *   (`WPCPM_Overview::targets()`): a wp-admin screen for every tile, the sponsor posts among them,
 *   whether or not the Administrator Dashboard's page is there. A tile added to the strip fails
 *   here until it is given a place to link to.
 * - **Decisions have one home.** The card links to the Administrator Dashboard, or says its page
 *   is missing in the words the dashboard class keeps for it, which the Administrators screen
 *   prints too, and nothing on the screen posts anything.
 * - **Each sync's last and next run** in the site's date and time, as the Syncs and health card
 *   reads them, **each tool's readiness** as the Tools screen says it, and the connection in both
 *   of its states.
 * - **Every printed value is escaped**: a translation, a number, a date and an address carrying a
 *   tag each arrive as text.
 *
 * Run from the plugin root:  php bin/test-overview.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://site.example/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']    = array(); // Option => value.
$GLOBALS['posts']   = array(); // Post ID => WP_Post.
$GLOBALS['next']    = array(); // Cron hook => when it next runs.
$GLOBALS['l10n']    = array(); // Text => its translation.
$GLOBALS['hostile'] = false;   // Whether every translation, number, date and address carries a tag.
$GLOBALS['caps']    = true;    // Whether the person looking holds the program's capability.
$GLOBALS['tools']   = array(); // The tools the registry holds, by ID.

/* ---- WordPress, as far as the Overview reaches ------------------------------- */

class WP_Post {
	public $ID = 0, $post_title = '', $post_status = 'publish', $post_date_gmt = '';
}

function __( $text, $domain = null ) {
	$said = isset( $GLOBALS['l10n'][ $text ] ) ? $GLOBALS['l10n'][ $text ] : $text;

	return $GLOBALS['hostile'] ? $said . ' <x>' : $said;
}
function _n( $single, $plural, $number, $domain = null ) {
	return __( 1 === (int) $number ? $single : $plural );
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
}
function esc_html__( $text, $domain = null ) {
	return esc_html( __( $text ) );
}
function esc_attr( $text ) {
	return esc_html( $text );
}
function esc_attr__( $text, $domain = null ) {
	return esc_attr( __( $text ) );
}
/**
 * Core's escaping for a URL on the page: what a URL cannot hold is dropped, `&` and `'` become
 * entities (bin/stubs/accounts-screen.php models it the same way).
 *
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	$url = str_replace( ' ', '%20', ltrim( (string) $url ) );
	$url = preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );
	$url = str_replace( array( '&#038;', '&amp;' ), '&', $url );

	return str_replace( array( '&', "'" ), array( '&#038;', '&#039;' ), $url );
}
function admin_url( $path = '' ) {
	return 'https://site.example/wp-admin/' . $path . ( $GLOBALS['hostile'] ? '&x=<x>' : '' );
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function wp_timezone() {
	return new DateTimeZone( 'UTC' );
}
function wp_date( $format, $timestamp = null, $timezone = null ) {
	$at = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );

	return $at->setTimezone( wp_timezone() )->format( $format );
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['next'][ $hook ] ) ? $GLOBALS['next'][ $hook ] : false;
}
function number_format_i18n( $number, $decimals = 0 ) {
	return (string) $number . ( $GLOBALS['hostile'] ? ' <x>' : '' );
}
function get_post( $id ) {
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}
function get_post_time( $format = 'U', $gmt = false, $post = null ) {
	return $post instanceof WP_Post ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : 0;
}
function get_post_meta( $id, $key = '', $single = false ) {
	return '';
}
function get_post_status( $id ) {
	$post = get_post( $id );

	return $post instanceof WP_Post ? $post->post_status : false;
}
function get_permalink( $id = 0 ) {
	$post = get_post( $id );

	return $post instanceof WP_Post ? 'https://site.example/administrator-dashboard/' . ( $GLOBALS['hostile'] ? '?x=<x>' : '' ) : false;
}
function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) {
	return $value;
}
function wp_die( $message = '', $title = '', $args = array() ) {
	throw new Exception( 'wp_die: ' . $message );
}
require_once __DIR__ . '/stubs/caps.php';

/* ---- the program, as the Overview reads it ----------------------------------- */

// Every class the cards read, answering from $GLOBALS['overview_reads'].
require_once __DIR__ . '/stubs/overview-reads.php';

/** The Institutions module's open states, as the module lists them, which the applications' total is counted over. */
class WPCPM_Institutions {
	public static function open_states() {
		return array( WPCPM_Institution_Application::STATE_NEW, WPCPM_Institution_Application::STATE_HELD, WPCPM_Institution_Application::STATE_INFO );
	}
}

/** The tool registry: whichever tools a check puts in it. */
class WPCPM_Tools {
	public static function all() {
		return $GLOBALS['tools'];
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';

// Required when it is there, so a check against a screen that is missing fails as a check rather
// than ending the run.
if ( is_file( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-overview.php' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-overview.php';
}

/**
 * A tool as the registry hands it over, saying whatever a check needs it to: whether it can run,
 * and its status line.
 */
class Overview_Probe_Tool extends WPCPM_Tool {
	private $slug, $name, $ready, $line;

	public function __construct( $slug, $name, $ready, $line ) {
		$this->slug  = $slug;
		$this->name  = $name;
		$this->ready = $ready;
		$this->line  = $line;
	}
	public function id() { return $this->slug; }
	public function label() { return $this->name; }
	public function description() { return 'A tool a check describes.'; }
	public function is_ready() { return $this->ready; }
	public function status_line() { return $this->line; }
	public function render_admin_page() {}
}

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	$total++;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * What a step returned, or what it threw when the code it reaches is not there.
 *
 * @param callable $step The step.
 * @return mixed
 */
function attempt( callable $step ) {
	try {
		return $step();
	} catch ( Throwable $thrown ) {
		return 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
	}
}

/**
 * What a step printed, and what it threw, if it threw.
 *
 * @param callable $step The step.
 * @return array{html: string, threw: string}
 */
function capture( callable $step ) {
	ob_start();
	$threw = '';

	try {
		$step();
	} catch ( Throwable $thrown ) {
		$threw = get_class( $thrown ) . ': ' . $thrown->getMessage();
	}

	return array(
		'html'  => (string) ob_get_clean(),
		'threw' => $threw,
	);
}

/**
 * The Overview, as a manager opening it sees it, while the program holds what a check says.
 *
 * @param array $world What the program holds (bin/stubs/overview-reads.php).
 * @return string The screen's markup, or what drawing it threw.
 */
function draw( array $world = array() ) {
	$GLOBALS['overview_reads'] = $world;
	$drawn                     = capture(
		static function () {
			WPCPM_Overview::render();
		}
	);

	return '' === $drawn['threw'] ? $drawn['html'] : 'threw ' . $drawn['threw'];
}

/**
 * The words some markup says, as a person reads them: each tag a space, the entities decoded, a run
 * of spaces one.
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
 * The cards the screen draws, in order: each one's heading => what the card holds under it.
 *
 * @param string $html The screen.
 * @return array<string, string>
 */
function cards_of( $html ) {
	preg_match_all( '#<div class="wpcpm-card"><h2>(.*?)</h2>(.*?)</div>#s', (string) $html, $found, PREG_SET_ORDER );

	$cards = array();

	foreach ( $found as $card ) {
		$cards[ words_of( $card[1] ) ] = $card[2];
	}

	return $cards;
}

/**
 * One card's markup, by its heading, or '' when the screen has no such card.
 *
 * @param string $html    The screen.
 * @param string $heading The card's heading.
 * @return string
 */
function card( $html, $heading ) {
	$cards = cards_of( $html );

	return isset( $cards[ $heading ] ) ? $cards[ $heading ] : '';
}

/**
 * The waiting list's links, in order: where each goes, its label, and the count after it, read from
 * inside its parentheses.
 *
 * @param string $card The Waiting for a decision card.
 * @return array[]
 */
function waiting_of( $card ) {
	preg_match_all( '#<li><a href="([^"]*)"><span class="wpcpm-waiting__label">(.*?)</span> <span class="count">\((.*?)\)</span></a></li>#s', (string) $card, $found, PREG_SET_ORDER );

	$links = array();

	foreach ( $found as $link ) {
		$links[] = array( $link[1], words_of( $link[2] ), words_of( $link[3] ) );
	}

	return $links;
}

/**
 * Every chip on the waiting list, in order, whether it links anywhere or not: where it goes, or ''
 * for a chip without a link, its label, and the count read from inside its parentheses.
 *
 * @param string $card The Waiting for a decision card.
 * @return array[]
 */
function chips_of( $card ) {
	preg_match_all( '#<li>(?:<a href="([^"]*)">)?<span class="wpcpm-waiting__label">(.*?)</span> <span class="count">\((.*?)\)</span>(?:</a>)?</li>#s', (string) $card, $found, PREG_SET_ORDER );

	$chips = array();

	foreach ( $found as $chip ) {
		$chips[] = array( $chip[1], words_of( $chip[2] ), words_of( $chip[3] ) );
	}

	return $chips;
}

/**
 * The rows of a card's table, each row's header cell linked: its words, where it links, and the
 * words of each other cell; with the warning a cell's words are flagged, when a check asks.
 *
 * @param string $card The card.
 * @return array[] Each: the name, the address, then one entry per cell, the cell's words, or for
 *                 a cell that is nothing but a warning, `warning: ` and its words.
 */
function rows_of( $card ) {
	preg_match_all( '#<tr><th scope="row"><a href="([^"]*)">(.*?)</a></th>((?:<td>.*?</td>)+)</tr>#s', (string) $card, $found, PREG_SET_ORDER );

	$rows = array();

	foreach ( $found as $row ) {
		preg_match_all( '#<td>(.*?)</td>#s', $row[3], $cells );

		$said = array( words_of( $row[2] ), $row[1] );

		foreach ( $cells[1] as $cell ) {
			$said[] = ( 1 === preg_match( '#^<span class="wpcpm-warning">[^<]*</span>$#', $cell ) ? 'warning: ' : '' ) . words_of( $cell );
		}

		$rows[] = $said;
	}

	return $rows;
}

/**
 * A table's column headings.
 *
 * @param string $card The card.
 * @return string[]
 */
function columns_of( $card ) {
	preg_match_all( '#<th scope="col">(.*?)</th>#s', (string) $card, $found );

	return array_map( 'words_of', $found[1] );
}

/**
 * The strip's own tiles that have something in them, as the Administrator Dashboard draws them:
 * each one's number and label.
 *
 * @param array $counts What `counts()` returned.
 * @return array[]
 */
function strip_tiles( array $counts ) {
	$drawn = capture(
		static function () use ( $counts ) {
			WPCPM_Administrators_Cards::render_strip( $counts );
		}
	);

	preg_match_all( '#<li class="wpcpm-attention__tile"><a class="wpcpm-attention__link" href="[^"]*"><span class="wpcpm-attention__n">(.*?)</span><span class="wpcpm-attention__l">(.*?)</span></a></li>#s', $drawn['html'], $found, PREG_SET_ORDER );

	$tiles = array();

	foreach ( $found as $tile ) {
		$tiles[] = array( words_of( $tile[1] ), words_of( $tile[2] ) );
	}

	return $tiles;
}

/**
 * The counts the Administrator Dashboard's strip reads, while the program holds what a check says.
 *
 * @param array $world What the program holds.
 * @return array
 */
function counts_for( array $world ) {
	$GLOBALS['overview_reads'] = $world;

	return WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
}

/**
 * The address a tile's count links to, as the screen prints it.
 *
 * @param string $key The tile's key.
 * @return string
 */
function target_of( $key ) {
	$targets = attempt(
		static function () {
			return WPCPM_Overview::targets();
		}
	);

	return is_array( $targets ) && isset( $targets[ $key ] ) ? esc_url( $targets[ $key ] ) : '(no target)';
}

function has( $haystack, $needle ) {
	return false !== strpos( (string) $haystack, (string) $needle );
}

/* ---- the fixtures ------------------------------------------------------------- */

$GLOBALS['opts']['date_format'] = 'j F Y';
$GLOBALS['opts']['time_format'] = 'H:i';

// The Administrator Dashboard's page, published: the page the decisions are made on, and the one
// place the sponsor posts waiting are listed, so the map is read with it there. A check that needs
// the page missing trashes it and puts it back.
$GLOBALS['opts'][ WPCPM_Administrators_Dashboard::OPT_PAGE ] = 42;
$page_post                                                  = new WP_Post();
$page_post->ID                                              = 42;
$GLOBALS['posts'][42]                                       = $page_post;

// Two agreements waiting for review, one of them well past the three days a review is given.
foreach ( array( 601 => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ), 602 => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) as $post_id => $sent ) {
	$post                = new WP_Post();
	$post->ID            = $post_id;
	$post->post_status   = 'private';
	$post->post_date_gmt = $sent;

	$GLOBALS['posts'][ $post_id ] = $post;
}

// The Student Duplicate Finder's last scan, twelve duplicated students in it.
$scan = array(
	'v'      => 1,
	'read'   => 1757000000,
	'counts' => array(
		'addresses' => 12,
		'ready'     => 3,
		'decide'    => 9,
	),
);

// Three queues with something in them, one on each kind of screen: the institutions', the
// sponsors' and a tool's. The strip's order is not the alphabet's, which would put the duplicated
// students second.
$three = array(
	'applications'  => 2,
	'sponsor_posts' => array( array( 'id' => 905 ) ),
	'duplicates'    => $scan,
);

// Every queue with something in it.
$busy = array(
	'applications'         => 3,
	'agreements'           => array( 601, 602 ),
	'drafts'               => array( array( 'post_id' => 701 ) ),
	'due'                  => array( array( 'institution' => 'recINSTB000000002', 'cohort' => '2026-H1' ) ),
	'requests'             => array(
		801 => array(
			'id'      => 801,
			'overdue' => true,
		),
		802 => array(
			'id'      => 802,
			'overdue' => false,
		),
	),
	'locked'               => array( 21 ),
	'sponsor_posts'        => array( array( 'id' => 905 ) ),
	'sponsor_agreements'   => array(
		913 => array(
			'post_id' => 913,
			'state'   => 'submitted',
		),
	),
	'sponsor_applications' => array( array( 'id' => 950 ) ),
	'offers'               => array(
		941 => array(
			'id'      => 941,
			'title'   => 'Pro license',
			'sponsor' => 'recSPN00000000001',
			'kind'    => 'codes',
			'state'   => 'live',
			'low'     => 10,
			'expires' => '',
		),
	),
	'codes'                => array(
		941 => array(
			'available' => 3,
			'claimed'   => 7,
			'void'      => 0,
			'total'     => 10,
		),
	),
	'duplicates'           => $scan,
);

// A tool with nothing to report, one that reports, and one that cannot run.
$probes = array(
	'quiet'    => new Overview_Probe_Tool( 'quiet', 'Quiet tool', true, '' ),
	'counting' => new Overview_Probe_Tool( 'counting', 'Counting tool', true, '3 things counted.' ),
	'blocked'  => new Overview_Probe_Tool( 'blocked', 'Blocked tool', false, 'Airtable is not connected yet, so this tool cannot run.' ),
);

$GLOBALS['tools'] = $probes;

/* ---- the screen ----------------------------------------------------------------- */

echo "=== The screen ===\n";

$html = draw( $three );

preg_match_all( '#<h1>(.*?)</h1>#s', $html, $h1 );
preg_match_all( '#<p class="wpcpm-lede">(.*?)</p>#s', $html, $ledes );

ck( 'the heading is the page\'s name, as the menu titles it, and one sentence says what the screen holds',
	array( array_map( 'words_of', $h1[1] ), array_map( 'words_of', $ledes[1] ) ),
	array( array( 'Overview' ), array( 'What waits for a decision, when each sync last ran and runs next, whether each tool can run, and the way to Settings.' ) ) );

ck( 'then four cards, in the order a manager reads them: what waits, the syncs, the tools and the way to Settings',
	array_keys( cards_of( $html ) ),
	array( 'Waiting for a decision', 'Syncs', 'Tools', 'Settings' ) );

// The menu's callback: the capability first, and the screen after it.
$GLOBALS['caps'] = false;
$refused         = capture(
	static function () {
		( new WPCPM_Admin() )->render_overview();
	}
);

$GLOBALS['caps']           = true;
$GLOBALS['overview_reads'] = $three;
$through_menu              = capture(
	static function () {
		( new WPCPM_Admin() )->render_overview();
	}
);

ck( 'the menu\'s entry draws this screen for a manager, and for anybody else refuses before drawing anything',
	array( $through_menu['html'], $through_menu['threw'], $refused['html'], $refused['threw'] ),
	array( $html, '', '', 'Exception: wp_die: You do not have permission to manage the program.' ) );

echo "\n=== Waiting for a decision ===\n";

$counts  = counts_for( $three );
$waiting = card( draw( $three ), 'Waiting for a decision' );

ck( 'three queues with something in them are three links, in the strip\'s order, each the strip\'s label and then its number in parentheses, to the screen that holds the queue',
	waiting_of( $waiting ),
	array(
		array( target_of( 'applications' ), $counts['applications']['label'], '2' ),
		array( target_of( 'sponsor_posts' ), $counts['sponsor_posts']['label'], '1' ),
		array( target_of( 'duplicates' ), $counts['duplicates']['label'], '12' ),
	) );

$zero_labels = array();

foreach ( $counts as $tile ) {
	if ( 0 === (int) $tile['n'] && has( $waiting, '>' . esc_html( $tile['label'] ) . '<' ) ) {
		$zero_labels[] = $tile['label'];
	}
}

ck( 'and a queue with nothing in it has no link: ten of the thirteen are zero, and none of them is on the list of three',
	array( count( array_filter( $counts, static function ( $tile ) { return 0 === (int) $tile['n']; } ) ), count( waiting_of( $waiting ) ), $zero_labels ),
	array( 10, 3, array() ) );

$busy_counts = counts_for( $busy );
$busy_html   = draw( $busy );
$busy_links  = waiting_of( card( $busy_html, 'Waiting for a decision' ) );

ck( 'with every queue waiting, the list is the Administrator Dashboard\'s strip, tile for tile: the same numbers, the same labels, the same order',
	array( count( $busy_links ), array_map( static function ( $link ) { return array( $link[2], $link[1] ); }, $busy_links ) ),
	array( 13, strip_tiles( $busy_counts ) ) );

$busy_targets = array();

foreach ( array_keys( $busy_counts ) as $key ) {
	$busy_targets[] = target_of( $key );
}

ck( 'and each tile links to its own screen',
	array_column( $busy_links, 0 ),
	$busy_targets );

// The sponsor posts waiting are listed on the Sponsors screen's Waiting for review tab, in a card
// of their own beside the applications and the signed agreements, so their count opens that card
// there, as the other two sponsor queues open theirs.
ck( 'and the sponsor posts open the Sponsors screen at their card on Waiting for review',
	array_values(
		array_filter(
			$busy_links,
			static function ( $link ) use ( $busy_counts ) {
				return $link[1] === $busy_counts['sponsor_posts']['label'];
			}
		)
	),
	array( array( esc_url( 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-posts' ), 'Sponsor posts to review', '1' ) ) );

// The map is the class's, read as the class holds it: a tile the strip gains without a screen here
// fails this check until it is given one.
$targets = attempt(
	static function () {
		return WPCPM_Overview::targets();
	}
);
$keys    = array_keys( counts_for( array() ) );

ck( 'every tile the strip counts has a place to link to, and every place on the map is a tile\'s',
	is_array( $targets ) ? array( array_values( array_diff( $keys, array_keys( $targets ) ) ), array_values( array_diff( array_keys( $targets ), $keys ) ) ) : $targets,
	array( array(), array() ) );

ck( 'today the institutions\' queues open the Institutions screen at the tab for each, the decisions on Waiting for review, the semester reports on Semester reports and the locked accounts on Accounts; the sponsors\' the Sponsors screen at the tab for each, the applications, the posts and the signed agreements on Waiting for review, each at its own card there, and the offers running low on Offers and codes; and the duplicated students the Student Duplicate Finder',
	$targets,
	array(
		'applications'         => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=queue',
		'agreements'           => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=queue',
		'overdue_agreements'   => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=queue',
		'drafts'               => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=reports',
		'due'                  => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=reports',
		'requests'             => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=queue',
		'overdue_requests'     => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=queue',
		'locked'               => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts',
		'sponsor_posts'        => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-posts',
		'sponsor_agreements'   => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements',
		'sponsor_applications' => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-applications',
		'offers_low'           => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=offers',
		'duplicates'           => 'https://site.example/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder',
	) );

// A tile the map does not place yet is still counted, since what waits is the card's point, but it
// links nowhere rather than to an address nobody wrote.
$unplaced = capture(
	static function () {
		WPCPM_Overview::render_waiting(
			array(
				'events' => array(
					'label' => 'Events to review',
					'n'     => 4,
					'card'  => 'events',
				),
			)
		);
	}
);

ck( 'a tile without a screen on the map is still on the list, its label and count, unlinked',
	array( $unplaced['threw'], 1 === preg_match( '#<li><span class="wpcpm-waiting__label">Events to review</span> <span class="count">\(4\)</span></li>#', $unplaced['html'] ), has( $unplaced['html'], 'href=""' ) ),
	array( '', true, false ) );

$nothing = card( draw(), 'Waiting for a decision' );

ck( 'with nothing waiting, the card says so in the words the dashboards keep for a manager with nothing waiting, and lists nothing',
	array( 1 === preg_match( '#^<p>Nothing is waiting for a manager right now\.</p>#', $nothing ), has( $nothing, '<ul' ) ),
	array( true, false ) );

ck( 'and the sentence is the dashboards\' own, read from them rather than copied',
	array(
		attempt(
			static function () {
				return WPCPM_Dashboards::empty_sentence( 'administrators' );
			}
		),
		0 === strpos( WPCPM_Dashboards::nothing_to_show( 'administrators', true ), 'Nothing is waiting for a manager right now. <a ' ),
		is_file( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-overview.php' ) && ! has( file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-overview.php' ), 'Nothing is waiting' ),
		attempt(
			static function () {
				return WPCPM_Dashboards::empty_sentence( 'nonsense' );
			}
		),
	),
	array( 'Nothing is waiting for a manager right now.', true, true, '' ) );

// The page the decisions are made on, and the same card while that page is missing.
$with_page                         = card( draw( $three ), 'Waiting for a decision' );
$GLOBALS['posts'][42]->post_status = 'trash';
$page_gone                         = card( draw( $three ), 'Waiting for a decision' );
$busy_page_gone                    = card( draw( $busy ), 'Waiting for a decision' );
$targets_page_gone                 = attempt(
	static function () {
		return WPCPM_Overview::targets();
	}
);
$GLOBALS['posts'][42]->post_status = 'publish';

ck( 'under the list, one link opens the Administrator Dashboard, where the decisions are made',
	array( preg_match_all( '#<a class="button button-primary" href="([^"]*)">Decide on the Administrator Dashboard</a>#', $with_page, $decide ), $decide[1], has( $with_page, 'wpcpm-warning' ) ),
	array( 1, array( 'https://site.example/administrator-dashboard/' ), false ) );

// The sentence has one home, the class that owns the page, which the Administrators screen reads as
// well: the card prints what the class says, whatever it says, and the Overview never writes the
// words itself.
$missing_said = attempt(
	static function () {
		return WPCPM_Administrators_Dashboard::page_missing();
	}
);

ck( 'and while the page is missing, the card says so in the words the dashboard class keeps for it, with no link, and the Overview never writes them',
	array(
		has( $page_gone, 'Decide on the Administrator Dashboard' ),
		is_string( $missing_said ) && '' !== $missing_said && 0 !== strpos( $missing_said, 'threw ' ),
		has( $page_gone, '<p class="wpcpm-warning">' . esc_html( (string) $missing_said ) . '</p>' ),
		is_file( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-overview.php' ) && ! has( file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-overview.php' ), 'page is missing' ),
	),
	array( false, true, true, true ) );

// No count opens the dashboard's page any more, so none of them depends on it: without the page,
// every count, the sponsor posts' among them, still opens the wp-admin screen that lists its queue.
$chips_page_gone = array();

foreach ( array_keys( $busy_counts ) as $key ) {
	$chips_page_gone[] = array( target_of( $key ), $busy_counts[ $key ]['label'], (string) $busy_counts[ $key ]['n'] );
}

ck( 'and while the page is missing, every count still opens its screen, the sponsor posts\' their card on the Sponsors screen, at the same address the map gives with the page there',
	array( chips_of( $busy_page_gone ), $targets_page_gone, has( card( $busy_page_gone, 'Waiting for a decision' ), 'administrator-dashboard/#' ) ),
	array( $chips_page_gone, $targets, false ) );

echo "\n=== Syncs ===\n";

// Students never ran and have a next run booked; mentors ran and have none; institutions are
// running now, though their next run is booked; sponsors ran and run again.
$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LAST ]        = 1756990000;
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_LAST ]   = 1756980000;
$GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_LAST ]       = 1756880000;
$GLOBALS['next'][ WPCPM_Students_Sync::CRON_AUTO ]      = 1757100000;
$GLOBALS['next'][ WPCPM_Institutions_Sync::CRON_DAILY ] = 1757200000;
$GLOBALS['next'][ WPCPM_Sponsors_Sync::CRON_DAILY ]     = 1756993600;

$syncs_world = array(
	'progress' => array(
		'institutions' => array(
			'running' => true,
			'phase'   => 'read',
			'label'   => 'Reading institutions',
			'error'   => '',
		),
	),
);

$syncs = card( draw( $syncs_world ), 'Syncs' );

ck( 'a table with a row for each audience\'s sync, in the dashboard\'s order, headed Sync, Last run and Next run',
	array( columns_of( $syncs ), array_column( rows_of( $syncs ), 0 ) ),
	array( array( 'Sync', 'Last run', 'Next run' ), array( 'Students', 'Mentors', 'Institutions', 'Sponsors' ) ) );

ck( 'each audience\'s name opens the screen its sync is run from, at the tab that runs it; each run is the site\'s date and time, or Never, or Not scheduled; and a sync running now says so in place of its next run',
	rows_of( $syncs ),
	array(
		array( 'Students', esc_url( 'https://site.example/wp-admin/admin.php?page=wpcpm-students&tab=sync' ), 'Never', gmdate( 'j F Y H:i', 1757100000 ) ),
		array( 'Mentors', esc_url( 'https://site.example/wp-admin/admin.php?page=wpcpm-mentors&tab=sync' ), gmdate( 'j F Y H:i', 1756990000 ), 'Not scheduled' ),
		array( 'Institutions', esc_url( 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=sync' ), gmdate( 'j F Y H:i', 1756980000 ), 'Running now' ),
		array( 'Sponsors', esc_url( 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors' ), gmdate( 'j F Y H:i', 1756880000 ), gmdate( 'j F Y H:i', 1756993600 ) ),
	) );

echo "\n=== Tools ===\n";

$tools_card = card( $html, 'Tools' );

ck( 'a row for each tool, its name opening its screen, then its status line: Ready when it has nothing to report, and the warning a tool that cannot run gives',
	array( columns_of( $tools_card ), rows_of( $tools_card ) ),
	array(
		array( 'Name', 'Status' ),
		array(
			array( 'Quiet tool', 'https://site.example/wp-admin/admin.php?page=wpcpm-tool-quiet', 'Ready' ),
			array( 'Counting tool', 'https://site.example/wp-admin/admin.php?page=wpcpm-tool-counting', '3 things counted.' ),
			array( 'Blocked tool', 'https://site.example/wp-admin/admin.php?page=wpcpm-tool-blocked', 'warning: Airtable is not connected yet, so this tool cannot run.' ),
		),
	) );

$GLOBALS['tools'] = array();
$no_tools         = card( draw( $three ), 'Tools' );
$GLOBALS['tools'] = $probes;

ck( 'with no tool registered, the card says so, as the Tools screen does',
	array( words_of( $no_tools ), has( $no_tools, '<table' ) ),
	array( 'No tools are registered.', false ) );

echo "\n=== Settings ===\n";

$not_connected = draw( $three );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array(
	'api_token' => 'patTEST00000000001',
	'base_id'   => 'appTEST00000000001',
);
$connected                                   = draw( $three );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array();

/**
 * What the Settings card says, and where its one link goes.
 *
 * @param string $html The screen.
 * @return array
 */
function settings_said( $html ) {
	$settings = card( $html, 'Settings' );

	preg_match_all( '#<a class="button" href="([^"]*)">(.*?)</a>#s', $settings, $links, PREG_SET_ORDER );

	return array(
		preg_match( '#^<p>(.*?)</p>#s', $settings, $first ) ? words_of( $first[1] ) : '',
		array_map(
			static function ( $link ) {
				return array( $link[1], words_of( $link[2] ) );
			},
			$links
		),
	);
}

ck( 'the Settings card says whether Airtable is connected, then opens the Settings screen, in both states',
	array( settings_said( $not_connected ), settings_said( $connected ) ),
	array(
		array( 'Airtable is not connected yet.', array( array( 'https://site.example/wp-admin/admin.php?page=wpcpm-settings', 'Open Settings' ) ) ),
		array( 'Airtable is connected.', array( array( 'https://site.example/wp-admin/admin.php?page=wpcpm-settings', 'Open Settings' ) ) ),
	) );

ck( 'and while Airtable is not connected, the warning above the cards says so, as it did, with the way to the token',
	array(
		1 === preg_match( '#<div class="notice notice-warning"><p>Airtable is not connected yet\. <a href="https://site\.example/wp-admin/admin\.php\?page=wpcpm-settings">Add a Personal Access Token</a></p></div>#', $not_connected ),
		has( $connected, 'notice-warning' ),
		strpos( $not_connected, 'notice-warning' ) < strpos( $not_connected, '<div class="wpcpm-card">' ),
	),
	array( true, false, true ) );

echo "\n=== What the screen says, and what it does not do ===\n";

$everything = draw( array_merge( $busy, $syncs_world ) );

ck( 'nothing on the screen says module, with every queue waiting, every sync and every tool on it',
	array( count( waiting_of( card( $everything, 'Waiting for a decision' ) ) ), count( rows_of( card( $everything, 'Syncs' ) ) ), modules_in( words_of( $everything ) ) ),
	array( 13, 4, array() ) );

// Decisions have one home, the Administrator Dashboard: this screen links to it and to the queues,
// and posts nothing itself.
ck( 'and it posts nothing: its four cards hold no form, no button that sends, no nonce, no address a form posts to',
	array( count( cards_of( $everything ) ), has( $everything, '<form' ), has( $everything, 'type="submit"' ), has( $everything, '_wpnonce' ), has( $everything, 'admin-post.php' ) ),
	array( 4, false, false, false, false ) );

// Every translation, every number, every date and every address carrying a tag of its own, and
// every tool saying one in its name and its line: each has to arrive as text.
$GLOBALS['hostile']             = true;
$GLOBALS['opts']['time_format'] = 'H:i \<\x\>';
$GLOBALS['tools']               = array(
	'quiet'   => new Overview_Probe_Tool( 'quiet', 'Quiet <x>', true, '' ),
	'blocked' => new Overview_Probe_Tool( 'blocked', 'Blocked <x>', false, 'Cannot run <x>' ),
);

$tagged = draw( array_merge( $busy, $syncs_world ) );

$GLOBALS['hostile']             = false;
$GLOBALS['opts']['time_format'] = 'H:i';
$GLOBALS['tools']               = $probes;

ck( 'every printed value is escaped: not one tag arrives as a tag, and each arrives as text, the strip\'s labels, the numbers, the audiences, the dates, the tools and their lines, the page\'s own words and the addresses among them',
	array(
		substr_count( $tagged, '<x>' ),
		has( $tagged, 'Applications waiting &lt;x&gt;' ),
		has( $tagged, '<span class="count">(3 &lt;x&gt;)</span>' ),
		has( $tagged, '>Students &lt;x&gt;</a>' ),
		has( $tagged, gmdate( 'j F Y H:i', 1756990000 ) . ' &lt;x&gt;' ),
		has( $tagged, '>Quiet &lt;x&gt;</a>' ),
		has( $tagged, '<span class="wpcpm-warning">Cannot run &lt;x&gt;</span>' ),
		has( $tagged, '<h2>Syncs &lt;x&gt;</h2>' ),
		has( $tagged, 'page=wpcpm-institutions&#038;tab=queue&#038;x=x' ),
		has( $tagged, 'administrator-dashboard/?x=x' ),
	),
	array( 0, true, true, true, true, true, true, true, true, true ) );

echo "\n=== The stylesheet ===\n";

$css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' );

ck( 'the waiting list is a row of links without list markers, which wraps onto more lines as the screen narrows',
	array(
		1 === preg_match( '/\.wpcpm-waiting \{[^}]*display: flex;/s', $css ),
		1 === preg_match( '/\.wpcpm-waiting \{[^}]*flex-wrap: wrap;/s', $css ),
		1 === preg_match( '/\.wpcpm-waiting \{[^}]*list-style: none;/s', $css ),
	),
	array( true, true, true ) );

// A chip is one line: the count keeps its figures in columns at the label's own size, and no rule
// is left for the number the chip used to set apart above its label.
ck( 'and each chip reads as one line: the count in tabular figures at the label\'s size, and no rule left for a number set apart',
	array(
		1 === preg_match( '/\.wpcpm-waiting \.count \{[^}]*font-variant-numeric: tabular-nums;/s', $css ),
		1 === preg_match( '/\.wpcpm-waiting \.count \{[^}]*font-size:/s', $css ),
		has( $css, 'wpcpm-waiting__n' ),
	),
	array( true, false, false ) );

$dashes = array();

foreach ( array( 'includes/class-wpcpm-overview.php', 'bin/test-overview.php', 'bin/stubs/overview-reads.php' ) as $relative ) {
	if ( ! is_file( WPCPM_PLUGIN_DIR . $relative ) || preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $relative ) ) ) {
		$dashes[] = $relative;
	}
}

ck( 'no dash but the plain hyphen in the screen, this suite or its stand-ins', $dashes, array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
