<?php
/**
 * The Administrators screen: its two tabs, Accounts and Capabilities, and on the Accounts tab every
 * administrator account as a WordPress list table, searched, sorted and paged.
 *
 * What this pins, and why each part is worth pinning:
 *
 * - Every administrator account is reachable, however many the site has. Until 1.117.3 the screen
 *   asked WordPress for the first 200 administrators, the kind of cap that hid 13 of 513 accounts on
 *   the Students screen until 1.117.1. The list is paged now: the heading, the pagination and the
 *   view count every account, and the last page holds the 201st. bin/test-account-queries.php held
 *   this check while the screen was one table.
 * - The table's columns and their values (Name, Username, Can manage program, with "Yes" or what to
 *   do for an administrator without the program's capability), the two after the name offered under
 *   Screen Options, the one row action (Edit), the sorts (Name and Username), the search, and the
 *   sentence of an empty list.
 * - What the list leaves out, because administrators hold WordPress's own role, which the plugin
 *   never invites or syncs: no checkbox column, no bulk actions, one view (All) whatever the address
 *   names, no invitation in a row, no invitations card, no warning while Airtable is not connected
 *   (it is not connected for this whole run), and no `admin_post_` handler at all. An address naming
 *   Send invite, with the list's own nonce, is no action here: nothing is checked, queued or sent.
 * - The screen's wiring, which is the accounts screen every audience's module shares
 *   (`WPCPM_Accounts_Screen`): its load hook, found once the menu has added the page; the tables
 *   loaded there, for the Accounts tab, and not before; the rows-per-page option on it; and the
 *   choice's save, hooked at boot, because WordPress saves screen options before it builds the menu.
 *   The module uses that screen and declares none of its methods again but the three it replaces
 *   (the page, the Accounts tab and the tabs' labels), and every method it keeps finds what it calls
 *   and reads on the module itself.
 * - The screen is two tabs in the bar every audience screen prints (`WPCPM_Screen_Tabs`): Accounts,
 *   the tab the screen opens on, holding the list alone, and Capabilities, holding the program
 *   capabilities the Administrator role is granted. "Open the Administrator Dashboard", the screen's
 *   primary action, stands above the bar on both.
 *
 * WordPress is stood in for as far as the screen reaches, by the stand-ins every accounts screen's
 * suite shares (bin/stubs/accounts-screen.php): `WP_User_Query` answers over the fixture accounts the
 * way WordPress answers the arguments the screen passes, and anything else it is asked is kept apart
 * and fails the last check. The capability is bin/stubs/caps.php's, which grants the program's
 * capability to the accounts `$GLOBALS['manage']` lists. The tab bar, the mail layer and the request
 * are the plugin's own. The sync module is never loaded: the Administrators module reads nothing of
 * it, and every check here draws the screen without it. The list table is
 * bin/stubs/class-wp-list-table.php, loaded through this suite's own `wpcpm_load_accounts_tables()`,
 * which requires what the plugin's does (bin/test-accounts-table.php pins the plugin's); its markup
 * is close to core's and is not core's.
 *
 * Each step is run, and what it drew is read, through the helpers the three screen suites share
 * (bin/stubs/screen-helpers.php).
 *
 * Run from the plugin root:  php bin/test-administrators-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once __DIR__ . '/stubs/accounts-screen.php';
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/screen-helpers.php';

// The accounts holding the program's capability, by ID: each fixture says who.
$GLOBALS['manage'] = array();

/**
 * The settings, as far as a screen could ask them: Airtable is not connected, for the whole run, so a
 * screen that warned about it would say so on every page drawn here.
 */
class WPCPM_Settings {
	public static $connected = false;
	public static function is_connected() { return self::$connected; }
}

/**
 * The Administrator Dashboard's page, which the button above the tabs and the list card name: its
 * address, which a check empties for a page that is missing, and what the class says then. That
 * sentence is the class's to keep, and the Overview prints it too, so this one is a sentence of its
 * own: a screen that wrote the real words out itself would print those, not these.
 */
class WPCPM_Administrators_Dashboard {
	public static $url = 'https://example.test/administrator-dashboard/';
	public static function page_url() { return self::$url; }
	public static function page_missing() { return 'The dashboard class says its page is missing.'; }
	public static function init() {}
	public static function ensure_page() {}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php';

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, which there is
 * no WordPress here to lend, then the files the plugin's own `wpcpm_load_accounts_tables()` requires,
 * in its order. The Administrators table's file is required once it exists, so a copy of the plugin
 * without it fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-table.php';

	if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-table.php' ) ) {
		require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-table.php';
	}
}

/* ---- helpers -------------------------------------------------------------- */

$total = 0;
$fails = 0;
function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}
	++$fails;
	echo "FAIL $label\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}
function has( $haystack, $needle ) {
	return false !== strpos( (string) $haystack, (string) $needle );
}

/**
 * The Administrators screen's query, with whatever else a check wants on it.
 *
 * @param array $more More query arguments.
 * @return array
 */
function screen( array $more = array() ) {
	return array_merge(
		array(
			'page' => 'wpcpm-administrators',
			'tab'  => 'accounts',
		),
		$more
	);
}

/**
 * A method of the Administrators module a check reaches that the module keeps to itself.
 *
 * @param string $name The method.
 * @return ReflectionMethod
 */
function reach( $name ) {
	$method = new ReflectionMethod( 'WPCPM_Administrators', $name );

	// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method;
}

/**
 * The administrator accounts card, as the screen draws it for a request.
 *
 * @param array $query The query string.
 * @return array What run() kept; the markup is its value.
 */
function draw( array $query ) {
	request( $query );
	$GLOBALS['queries'] = array();

	return run(
		function () {
			$method = reach( 'render_accounts_list' );
			ob_start();

			try {
				$method->invoke( new WPCPM_Administrators() );
			} finally {
				$html = (string) ob_get_clean();
			}

			return $html;
		}
	);
}

/**
 * A press on the screen: its load hook, run for the request, as WordPress runs it before the page.
 *
 * @param array $query The query string.
 * @return array What run() kept; `drawn` when the hook let the page go on.
 */
function press( array $query ) {
	request( $query );
	$GLOBALS['queries']        = array();
	$GLOBALS['current_screen'] = new WPCPM_Stub_Screen();

	return run(
		function () {
			$module = new WPCPM_Administrators();
			$module->load_screen();

			return 'drawn';
		}
	);
}

/**
 * The Administrators screen as WordPress draws it for a request: the screen's load hook, then the
 * page, from the one module, as the plugin's registry keeps one.
 *
 * @param array $query The query string.
 * @return array What run() kept; the markup is its value.
 */
function page_for( array $query ) {
	request( $query );
	$GLOBALS['queries']        = array();
	$GLOBALS['current_screen'] = new WPCPM_Stub_Screen();

	return run(
		function () {
			$module = new WPCPM_Administrators();
			$module->load_screen();

			ob_start();

			try {
				$module->render_admin_page();
			} finally {
				$html = (string) ob_get_clean();
			}

			return $html;
		}
	);
}

/**
 * The tab bar a drawn screen prints: core's newer bar, a navigation region named for a screen
 * reader, holding plain links.
 *
 * @param string $html Markup.
 * @return array|null The name the region gives itself, then each tab: its address, entities
 *                    decoded; its label as printed; whether its class marks it as the tab shown;
 *                    and whether it says so to a screen reader. Null when there is no bar.
 */
function bar_of( $html ) {
	if ( ! preg_match( '#<nav class="nav-tab-wrapper wp-clearfix" aria-label="([^"]*)">(.*?)</nav>#s', (string) $html, $bar ) ) {
		return null;
	}

	preg_match_all( '#<a href="([^"]*)" class="nav-tab( nav-tab-active)?"( aria-current="page")?>([^<]*)</a>#', $bar[2], $links, PREG_SET_ORDER );

	$tabs = array();

	foreach ( $links as $link ) {
		$tabs[] = array( html_entity_decode( $link[1], ENT_QUOTES, 'UTF-8' ), $link[4], '' !== $link[2], '' !== $link[3] );
	}

	return array( html_entity_decode( $bar[1], ENT_QUOTES, 'UTF-8' ), $tabs );
}

/**
 * Where the button to the Administrator Dashboard stands on a drawn screen: how many times the
 * screen names it, and whether it stands between the lede and the bar.
 *
 * @param string $html   Markup.
 * @param string $button The button's markup.
 * @return array{0: int, 1: bool}
 */
function button_place( $html, $button ) {
	$lede = strpos( (string) $html, '<p class="wpcpm-lede">' );
	$at   = strpos( (string) $html, $button );
	$bar  = strpos( (string) $html, '<nav class="nav-tab-wrapper' );

	return array( substr_count( (string) $html, 'Open the Administrator Dashboard' ), false !== $lede && false !== $at && false !== $bar && $lede < $at && $at < $bar );
}

/**
 * Whether anything was added to a hook.
 *
 * @param string $hook Hook name.
 * @return bool
 */
function has_action_named( $hook ) {
	return ! empty( $GLOBALS['hooks'][ $hook ] );
}

/**
 * A fresh program manager for a press: WPCPM_Flash remembers what it took for a person within one
 * run of PHP, so each press is made by somebody new.
 *
 * @param int $uid User ID.
 */
function manager( $uid ) {
	$GLOBALS['uid']          = (int) $uid;
	$GLOBALS['caps']         = true;
	$GLOBALS['nonce_checks'] = array();
	$GLOBALS['reads']        = array();
	$GLOBALS['mails']        = array();
	$GLOBALS['opts']         = array();
	$GLOBALS['scheduled']    = array();
}

/**
 * A drawn table's rows, in order.
 *
 * @param string $html Markup.
 * @return string[]
 */
function rows_of( $html ) {
	return array_slice( preg_split( '/<tr[ >]/', between( $html, '<tbody', '</tbody>' ) ), 1 );
}

/**
 * The account IDs a drawn table's rows hold, from the editor link on each name, in order. The list
 * has no checkboxes to read them from.
 *
 * @param string $html Markup.
 * @return int[]
 */
function named_ids( $html ) {
	$ids = array();

	foreach ( rows_of( $html ) as $row ) {
		if ( preg_match( '/user-edit\.php\?user_id=(\d+)/', (string) cell( $row, 'name' ), $found ) ) {
			$ids[] = (int) $found[1];
		}
	}

	return $ids;
}

/**
 * One account's row, by the editor link on its name.
 *
 * @param string $html Markup.
 * @param int    $id   User ID.
 * @return string
 */
function row_for( $html, $id ) {
	foreach ( rows_of( $html ) as $row ) {
		if ( has( cell( $row, 'name' ), 'user-edit.php?user_id=' . (int) $id . '"' ) ) {
			return $row;
		}
	}

	return '';
}

function heading_count( $html ) {
	return preg_match( '/<h2>Administrator accounts <span class="wpcpm-count">([^<]*)<\/span><\/h2>/', (string) $html, $found ) ? $found[1] : null;
}

/**
 * The queries a drawn list made, by what each was for.
 *
 * @param string $kind `page` (the rows drawn) or `count` (the view's count).
 * @return array[]
 */
function queries( $kind ) {
	return array_values(
		array_filter(
			$GLOBALS['queries'],
			function ( $args ) use ( $kind ) {
				$ids    = isset( $args['fields'] ) && 'ID' === $args['fields'];
				$number = isset( $args['number'] ) ? (int) $args['number'] : 0;

				return 'page' === $kind ? ! $ids && $number > 0 : $ids && 1 === $number;
			}
		)
	);
}

/* ---- fixtures ------------------------------------------------------------- */

/**
 * Put an account on the site, holding the program's capability or not.
 *
 * @param int      $id      User ID.
 * @param string   $name    Display name.
 * @param string   $login   Username, or '' for one made from the name.
 * @param string[] $roles   Roles.
 * @param bool     $manages Whether the account holds the program's capability.
 */
function account( $id, $name, $login, array $roles, $manages = true ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, $roles, $login );
	$GLOBALS['umeta'][ $id ] = array();

	if ( $manages ) {
		$GLOBALS['manage'][] = (int) $id;
	}
}

function reset_site() {
	$GLOBALS['users']     = array();
	$GLOBALS['umeta']     = array();
	$GLOBALS['manage']    = array();
	$GLOBALS['no_editor'] = false;
	manager( 1 );
}

/**
 * Three administrators: Ada and Bruno, who hold the program's capability, and Cleo, whose account
 * does not, as an administrator's does on a site the role's capabilities were taken from. And a
 * mentor and a student, neither of them on the list.
 */
function three_admins() {
	reset_site();
	account( 11, 'Ada Kowalski', 'akowalski', array( WPCPM_Roles::ROLE_ADMIN ) );
	account( 12, 'Bruno Diaz', 'bdiaz', array( WPCPM_Roles::ROLE_ADMIN ) );
	account( 13, 'Cleo Ahn', 'cahn', array( WPCPM_Roles::ROLE_ADMIN ), false );
	account( 20, 'Mona Mentor', 'mmentor', array( WPCPM_Roles::ROLE_MENTOR ), false );
	account( 21, 'Sam Student', 'sstudent', array( WPCPM_Roles::ROLE_STUDENT ), false );
}

/**
 * The three, and Dana, whose username comes first while her name comes last, so the two sorts
 * differ.
 */
function four_admins() {
	three_admins();
	account( 14, 'Dana Brook', 'abrook', array( WPCPM_Roles::ROLE_ADMIN ) );
}

// The request that saves a rows-per-page choice, as WordPress makes it: the module booted on
// `plugins_loaded`, then the save's filter applied in `set_screen_options()`, before the menu, the
// screen's load hook or any table exists. In a process of its own, because the checks below declare
// the tables, and a class once declared cannot be undeclared.
if ( isset( $argv[1] ) && 'child-save' === $argv[1] ) {
	$declared = class_exists( 'WPCPM_Administrators_Table', false );
	$saved    = run(
		function () {
			( new WPCPM_Administrators() )->boot();

			return apply_filters( 'set_screen_option_wpcpm_administrators_per_page', false, 'wpcpm_administrators_per_page', '50' );
		}
	);

	echo json_encode(
		array(
			'before' => $declared,
			'saved'  => $saved['value'],
			'error'  => $saved['error'],
			'after'  => class_exists( 'WPCPM_Administrators_Table', false ),
		)
	);
	exit( 0 );
}

/* ---- the checks ----------------------------------------------------------- */

echo "=== The screen loads its list table on its own load hook, and not before ===\n";

ck( 'nothing has declared a list table before the screen loads',
	array( class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Administrators_Table', false ) ),
	array( false, false, false ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-save 2>&1', $save_out, $save_status );
$save = (array) json_decode( implode( "\n", $save_out ), true );

ck( 'a rows-per-page choice is saved as WordPress saves it, before any table is loaded: the save loads the table itself, and keeps 50',
	array( $save_status, isset( $save['before'] ) ? $save['before'] : implode( "\n", $save_out ), isset( $save['error'] ) ? $save['error'] : null, isset( $save['saved'] ) ? $save['saved'] : null, isset( $save['after'] ) ? $save['after'] : null ),
	array( 0, false, '', 50, true ) );

three_admins();
$capabilities_load = press( screen( array( 'tab' => 'capabilities' ) ) );

ck( 'on the Capabilities tab, which has no list, the load hook leaves the list alone: no list table is loaded, no rows-per-page option offered, no account read and no list headings set, and the page goes on',
	got(
		$capabilities_load,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Administrators_Table', false ), $GLOBALS['screen_options'], $GLOBALS['queries'], $GLOBALS['current_screen']->reader_content );
		}
	),
	array( 'drawn', false, false, false, array(), array(), array() ) );

three_admins();
$loaded = press( screen() );

ck( 'the screen\'s load hook loads core\'s list table, the accounts base and the Administrators table, and lets the page go on',
	got(
		$loaded,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Administrators_Table', false ) );
		}
	),
	array( 'drawn', true, true, true ) );
ck( 'it puts the rows-per-page option on the screen: twenty until somebody chooses, kept under the Administrators\' own name',
	isset( $GLOBALS['screen_options']['per_page'] ) ? $GLOBALS['screen_options']['per_page'] : 'no option added',
	array(
		'default' => 20,
		'option'  => 'wpcpm_administrators_per_page',
	) );
ck( 'and prepares the list there, before the page\'s header, as core\'s own lists do', count( queries( 'page' ) ), 1 );
ck( 'it names the list\'s three headings for screen readers, which core leaves out unless the screen sets them',
	$GLOBALS['current_screen']->reader_content,
	array(
		'heading_views'      => 'Filter administrator accounts list',
		'heading_pagination' => 'Administrator accounts list navigation',
		'heading_list'       => 'Administrator accounts list',
	) );

// The menu opens the screen at its own address, which names no tab: that is the Accounts tab.
$GLOBALS['screen_options'] = array();
$from_menu                 = press( array( 'page' => 'wpcpm-administrators' ) );

ck( 'from the menu\'s own address, which names no tab, the load hook does the Accounts tab\'s work: the rows-per-page option, the three headings and the page of rows, read before the page\'s header',
	got(
		$from_menu,
		function ( $out ) {
			return array( $out['value'], isset( $GLOBALS['screen_options']['per_page']['option'] ) ? $GLOBALS['screen_options']['per_page']['option'] : 'no option added', array_keys( $GLOBALS['current_screen']->reader_content ), count( queries( 'page' ) ) );
		}
	),
	array( 'drawn', 'wpcpm_administrators_per_page', array( 'heading_views', 'heading_pagination', 'heading_list' ), 1 ) );

echo "\n=== Every administrator account is reachable, a page at a time: 201 of them ===\n";

// Named so that display-name order is the numbering (Admin 001 to Admin 201), and a mentor.
reset_site();
for ( $i = 1; $i <= 201; $i++ ) {
	account( 100 + $i, sprintf( 'Admin %03d', $i ), '', array( WPCPM_Roles::ROLE_ADMIN ) );
}
account( 700, 'Mentor Example', '', array( WPCPM_Roles::ROLE_MENTOR ), false );

$first = draw( screen() );
$html  = html_of( $first );

ck( 'the first page lists twenty accounts, the rows a page until somebody chooses another number',
	got(
		$first,
		function ( $out ) {
			return count( named_ids( html_of( $out ) ) );
		}
	),
	20 );
ck( 'the heading, the pagination and the view count every administrator account, all 201 of them, never a first page',
	array( heading_count( $html ), displaying( $html ), view_counts( $html ) ),
	array( '201', '201 items', array( 'all' => '201' ) ) );
ck( 'the list asked WordPress for one page of administrators, by name with the ID after it, and for its total',
	array( arg( first_query( 'page' ), 'number' ), arg( first_query( 'page' ), 'offset' ), arg( first_query( 'page' ), 'count_total' ), arg( first_query( 'page' ), 'role' ), arg( first_query( 'page' ), 'orderby' ) ),
	array( 20, 0, true, WPCPM_Roles::ROLE_ADMIN, array( 'display_name' => 'ASC', 'ID' => 'ASC' ) ) );

$last = draw( screen( array( 'paged' => '11' ) ) );

ck( 'the last page, the 11th, holds the 201st account by name', named_ids( html_of( $last ) ), array( 301 ) );

$GLOBALS['umeta'][1]['wpcpm_administrators_per_page'] = 999;
$every = named_ids( html_of( draw( screen() ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_administrators_per_page'] );

ck( 'on one page of 999, every administrator account is listed, and the mentor is not',
	array( count( $every ), in_array( 301, $every, true ), in_array( 700, $every, true ) ),
	array( 201, true, false ) );

echo "\n=== The table: Name, Username and Can manage program, and no checkbox ===\n";

three_admins();
$html = html_of( draw( screen() ) );

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", between( $html, '<thead>', '</thead>' ), $header_ids );

ck( 'the headers are the three columns in order, and no checkbox: nothing in the list is ticked, since nothing is pressed on ticked accounts',
	array( $header_ids[1], has( $html, 'type="checkbox"' ), has( $html, 'check-column' ) ),
	array( array( 'name', 'login', 'manage' ), false, false ) );
ck( 'each says what it holds, Name and Username as sort links and Can manage program as plain text',
	array( has( $html, '<span>Name</span>' ), has( $html, '<span>Username</span>' ), has( $html, '>Can manage program</th>' ) ),
	array( true, true, true ) );
ck( 'a row per administrator, by name: Ada, Bruno, Cleo; the mentor and the student are not on it', named_ids( $html ), array( 11, 12, 13 ) );

$ada  = row_for( $html, 11 );
$cleo = row_for( $html, 13 );

ck( 'Ada\'s row: her name to her account\'s editor, in bold, her username, and Yes, she can manage the program',
	array( cell( $ada, 'name' ), cell( $ada, 'login' ), cell( $ada, 'manage' ) ),
	array( '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=11">Ada Kowalski</a></strong>', '<code>akowalski</code>', 'Yes' ) );
ck( 'Cleo\'s, an administrator without the program\'s capability: No, and what to do about it, as the screen has always said it',
	array( cell( $cleo, 'name' ), cell( $cleo, 'login' ), cell( $cleo, 'manage' ) ),
	array( '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=13">Cleo Ahn</a></strong>', '<code>cahn</code>', 'No - re-activate the plugin' ) );

$ada_actions = actions_in( $ada );

ck( 'under each name, Edit alone, which opens the account\'s editor: no invitation, no page of their own',
	array( array_keys( $ada_actions ), array_keys( actions_in( $cleo ) ), array_map( 'strip_tags', $ada_actions ), link_of( isset( $ada_actions['edit'] ) ? $ada_actions['edit'] : '' ) ),
	array( array( 'edit' ), array( 'edit' ), array( 'edit' => 'Edit' ), array( 'https://example.test/wp-admin/user-edit.php', array( 'user_id' => '11' ) ) ) );

$views = between( $html, "<ul class='subsubsub'>", '</ul>' );

ck( 'one view, All, counting the three, marked as the view shown and linking the Accounts tab',
	array( view_counts( $html ), has( $views, 'class="current" aria-current="page"' ), link_of( $views ) ),
	array( array( 'all' => '3' ), true, array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-administrators', 'tab' => 'accounts' ) ) ) );

// The All view is drawn in the markup every audience's views are drawn in, the base's one view link,
// so a change to how a view is linked, marked or counted reaches this list with the others': the view
// the table draws is the base's All for the same count, and the table holds no view markup of its own.
$as_base = run(
	function () {
		$probe = new class() extends WPCPM_Administrators_Table {
			public function drawn_views() {
				return $this->get_views();
			}
			public function base_views( array $counts ) {
				return $this->invite_views( $counts );
			}
		};

		return array( $probe->drawn_views()['all'], $probe->base_views( array( 'all' => 3, 'invited' => 0, 'never-invited' => 3 ) )['all'] );
	}
);
$view_code = function ( $file ) {
	return implode(
		'',
		array_map(
			function ( $token ) {
				return is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
			},
			token_get_all( (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ) )
		)
	);
};

ck( 'and it is the base\'s All view for the same count, drawn by the base\'s one view link: the table keeps no view markup of its own',
	array(
		got(
			$as_base,
			function ( $out ) {
				return $out['value'][0] === $out['value'][1];
			}
		),
		substr_count( $view_code( 'includes/modules/class-wpcpm-administrators-table.php' ), 'aria-current' ),
		substr_count( $view_code( 'includes/class-wpcpm-accounts-table.php' ), 'aria-current' ),
	),
	array( true, 0, 1 ) );

$form        = between( $html, '<form method="get"', '</form>' );
$bulk_offers = run(
	function () {
		return ( new WPCPM_Administrators_Table() )->get_bulk_actions();
	}
);

ck( 'no bulk actions: the table offers none, so the list\'s form holds no bulk select and no Apply button',
	array(
		got(
			$bulk_offers,
			function ( $out ) {
				return $out['value'];
			}
		),
		has( $form, '<select name="action' ),
		has( $form, 'value="Apply"' ),
	),
	array( array(), false, false ) );
ck( 'the list is one form, sent to the screen by GET with its page and its tab, with the search box, and no form in a row',
	array( has( $form, '<input type="hidden" name="page" value="wpcpm-administrators" />' ), has( $form, '<input type="hidden" name="tab" value="accounts" />' ), has( $form, 'name="s"' ), has( $form, 'value="Search administrators"' ), substr_count( $html, '<form' ) ),
	array( true, true, true, true, 1 ) );
ck( 'above it the Administrator Dashboard\'s address, and below it that program managers use the Administrator role and are never invited by the plugin',
	array(
		has( $html, '<p>Administrator Dashboard: <a href="https://example.test/administrator-dashboard/">https://example.test/administrator-dashboard/</a></p>' ),
		has( $html, '<p class="description">Program managers use the built-in WordPress Administrator role and are never invited by the plugin: their accounts are added and managed on the Users screen.</p>' ),
	),
	array( true, true ) );

// Core's Screen Options offers a box to hide each column the screen's columns filter answers, and
// the table answers it with every column but the primary one, which holds the row's actions.
$GLOBALS['list_screen'] = (object) array(
	'id'   => 'wpcredits-program_page_wpcpm-administrators',
	'base' => 'wpcredits-program_page_wpcpm-administrators',
);
$offered                = run(
	function () {
		new WPCPM_Administrators_Table();

		return array_keys( apply_filters( 'manage_wpcredits-program_page_wpcpm-administrators_columns', array() ) );
	}
);
$GLOBALS['list_screen'] = null;

ck( 'Screen Options offers Username and Can manage program to hide, and never the name, which holds the row\'s actions',
	got(
		$offered,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'login', 'manage' ) );

$named_view = html_of( draw( screen( array( 'wpcpm_view' => 'never-invited' ) ) ) );

ck( 'an address naming an invitation view still lists every administrator under All, and the form carries no view: the list has one',
	array( named_ids( $named_view ), view_counts( $named_view ), has( between( $named_view, '<form method="get"', '</form>' ), 'name="wpcpm_view"' ), arg( first_query( 'page' ), 'meta_query' ) ),
	array( array( 11, 12, 13 ), array( 'all' => '3' ), false, array() ) );

WPCPM_Administrators_Dashboard::$url = '';
$GLOBALS['no_editor']                = true;
$bare_html                           = html_of( draw( screen() ) );
$bare_rows                           = rows_of( $bare_html );
$bare                                = isset( $bare_rows[0] ) ? $bare_rows[0] : '';
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
$GLOBALS['no_editor']                = false;

// A row with nothing to press keeps core's "Show more details" toggle, alone after the name: on a
// narrow screen core folds every cell after the primary one, and the toggle is what opens them, so
// without it Username and Can manage program could not be read at all.
ck( 'with no Administrator Dashboard page and no right to the editor, the name is plain, a row has no action but keeps its one toggle, and the card says the page is missing, in the words the dashboard class keeps for it',
	array(
		cell( $bare, 'name' ),
		actions_in( $bare ),
		has( $bare, '<div class="row-actions">' ),
		array_map(
			function ( $row ) {
				return substr_count( $row, 'class="toggle-row"' );
			},
			$bare_rows
		),
		has( $bare_html, '<p class="wpcpm-warning">' . esc_html( WPCPM_Administrators_Dashboard::page_missing() ) . '</p>' ),
	),
	array( '<strong>Ada Kowalski</strong><button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>', array(), false, array( 1, 1, 1 ), true ) );

reset_site();
$nobody_here = html_of( draw( screen() ) );

ck( 'with no administrator account, the list says no administrators were found', array( named_ids( $nobody_here ), has( $nobody_here, 'No administrators found.' ) ), array( array(), true ) );

echo "\n=== The search: the name, the username and the email ===\n";

three_admins();
$found = array();
foreach ( array( 'KOWAL', 'bdiaz', 'cahn@example', 'ahn' ) as $term ) {
	$found[ $term ] = named_ids( html_of( draw( screen( array( 's' => $term ) ) ) ) );
}

ck( 'whatever the case, by the name, the username or the email',
	$found,
	array(
		'KOWAL'        => array( 11 ),
		'bdiaz'        => array( 12 ),
		'cahn@example' => array( 13 ),
		'ahn'          => array( 13 ),
	) );

$searched = html_of( draw( screen( array( 's' => 'bdiaz' ) ) ) );

ck( 'WordPress is asked for the administrator accounts the search finds, "contains", over the username, the email and the name',
	array( arg( first_query( 'page' ), 'search' ), arg( first_query( 'page' ), 'search_columns' ), arg( first_query( 'page' ), 'role' ) ),
	array( '*bdiaz*', array( 'user_login', 'user_email', 'display_name' ), WPCPM_Roles::ROLE_ADMIN ) );
ck( 'the view counts the whole list, not the search, as WordPress\'s own views do', view_counts( $searched ), array( 'all' => '3' ) );

$nobody = html_of( draw( screen( array( 's' => 'zzz' ) ) ) );

ck( 'a search nobody matches says it found no administrator accounts, rather than that there are none', array( has( $nobody, 'No administrator accounts found.' ), has( $nobody, 'No administrators found.' ) ), array( true, false ) );

echo "\n=== The sorts: Name and Username, and not Can manage program ===\n";

four_admins();
$plain = html_of( draw( screen() ) );

ck( 'Name sorts by display name and Username by username; Can manage program does not sort',
	array( sort_of( $plain, 'name' ), sort_of( $plain, 'login' ), sort_of( $plain, 'manage' ) ),
	array( 'display_name', 'login', 'not sortable' ) );
ck( 'a list nobody sorted is by name, A to Z: Ada, Bruno, Cleo, Dana', named_ids( $plain ), array( 11, 12, 13, 14 ) );
ck( 'Name Z to A as asked', named_ids( html_of( draw( screen( array( 'orderby' => 'display_name', 'order' => 'desc' ) ) ) ) ), array( 14, 13, 12, 11 ) );
ck( 'Username A to Z, Dana first, and Z to A, Dana last',
	array( named_ids( html_of( draw( screen( array( 'orderby' => 'login', 'order' => 'asc' ) ) ) ) ), named_ids( html_of( draw( screen( array( 'orderby' => 'login', 'order' => 'desc' ) ) ) ) ) ),
	array( array( 14, 11, 12, 13 ), array( 13, 12, 11, 14 ) ) );
ck( 'a sort the list does not offer is the name sort', named_ids( html_of( draw( screen( array( 'orderby' => 'manage' ) ) ) ) ), array( 11, 12, 13, 14 ) );

// A row a page, so the boundary between two pages falls between the two Sam Riveras: the ID after
// the name settles them, or one could show on both pages and the other on neither.
reset_site();
account( 51, 'Sam Rivera', 'srivera', array( WPCPM_Roles::ROLE_ADMIN ) );
account( 52, 'Sam Rivera', 'sriveraortiz', array( WPCPM_Roles::ROLE_ADMIN ) );
account( 53, 'Tia Rivera', 'trivera', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['umeta'][1]['wpcpm_administrators_per_page'] = 1;

$pages = array();
foreach ( array( 'asc', 'desc' ) as $way ) {
	foreach ( array( '1', '2', '3' ) as $number ) {
		$pages[ $way ][] = named_ids( html_of( draw( screen( array( 'order' => $way, 'paged' => $number ) ) ) ) );
	}
}

unset( $GLOBALS['umeta'][1]['wpcpm_administrators_per_page'] );

ck( 'two administrators of one name, a row a page: each Sam Rivera is on a page of their own, either way',
	$pages,
	array(
		'asc'  => array( array( 51 ), array( 52 ), array( 53 ) ),
		'desc' => array( array( 53 ), array( 52 ), array( 51 ) ),
	) );

echo "\n=== No invitations: nothing hooked, nothing pressed ===\n";

reset_site();
$GLOBALS['hooks'] = array();
$module           = new WPCPM_Administrators();
run(
	function () use ( $module ) {
		$module->boot();
	}
);

ck( 'at boot, the module adds its screen\'s two hooks and no admin_post_ handler at all: nobody is invited from this screen',
	array( array_keys( $GLOBALS['hooks'] ), array_values( preg_grep( '/^admin_post_/', array_keys( $GLOBALS['hooks'] ) ) ), has_action_named( 'admin_post_wpcpm_administrators_invite' ) ),
	array( array( 'admin_menu', 'set_screen_option_wpcpm_administrators_per_page' ), array(), false ) );

three_admins();
manager( 2 );

// What the list's form would send with a bulk action if it offered one: core's nonce, which the
// form carries above the table all the same, and the address it came from.
$crafted = press(
	screen(
		array(
			'action'           => 'invite',
			'users'            => array( '11', '13' ),
			'_wpnonce'         => wp_create_nonce( 'bulk-administrators' ),
			'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-administrators&tab=accounts',
		)
	)
);
$ticked  = run(
	function () {
		request( screen( array( 'action' => 'invite' ) ) );

		return ( new WPCPM_Administrators_Table() )->current_action();
	}
);

ck( 'an address naming Send invite, with the list\'s own nonce, is no action in this list: no nonce is asked about, nobody is queued or sent anything, and it comes back to the list',
	array(
		got(
			$ticked,
			function ( $out ) {
				return $out['value'];
			}
		),
		$GLOBALS['nonce_checks'],
		WPCPM_Mail::queue(),
		$GLOBALS['mails'],
		link_of( $crafted['redirect'] ),
	),
	array( false, array(), array(), array(), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-administrators', 'tab' => 'accounts' ) ) ) );

manager( 3 );
$searched_press = press(
	screen(
		array(
			'_wpnonce'         => wp_create_nonce( 'bulk-administrators' ),
			'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-administrators&tab=accounts',
			'action'           => '-1',
			's'                => 'ada',
			'orderby'          => 'login',
			'order'            => 'desc',
			'paged'            => '2',
		)
	)
);

ck( 'a search sent from the list\'s form comes back to the same list, its search, sort and page, without the form\'s nonce, as core\'s own lists do',
	link_of( $searched_press['redirect'] ),
	array(
		'https://example.test/wp-admin/admin.php',
		array(
			'page'    => 'wpcpm-administrators',
			'tab'     => 'accounts',
			's'       => 'ada',
			'orderby' => 'login',
			'order'   => 'desc',
			'paged'   => '2',
		),
	) );

$names = run(
	function () {
		return array( WPCPM_Administrators::PER_PAGE_OPTION, WPCPM_Administrators_Table::per_page_option(), WPCPM_Administrators::TAB_ACCOUNTS, WPCPM_Accounts_Table::TAB, WPCPM_Administrators_Table::bulk_nonce_action(), WPCPM_Administrators_Table::role(), WPCPM_Administrators::FLASH_DETAIL, WPCPM_Administrators::ACTION_INVITE );
	}
);

ck( 'the module\'s names for the option and the tab are the table\'s, the list is the administrators\', and the names the shared screen reads are the Administrators\' own, though no invitation is ever pressed here',
	got(
		$names,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'wpcpm_administrators_per_page', 'wpcpm_administrators_per_page', 'accounts', 'accounts', 'bulk-administrators', 'administrator', 'administrators_admin_detail', 'wpcpm_administrators_invite' ) );

$own = run(
	function () {
		$module = new WPCPM_Administrators();

		return array( reach( 'flash_key' )->invoke( $module ), reach( 'sync_class' )->invoke( $module ), reach( 'table_class' )->invoke( null ) );
	}
);

ck( 'it names its own flash channel and its own table, and no sync: the shared screen asks for one only in the invitation handler this screen never hooks',
	got(
		$own,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'administrators_admin', '', 'WPCPM_Administrators_Table' ) );

// Nothing invites an administrator, so the Administrator accounts table reads no stamp for them
// (`invite_meta()`), and the shared screen's reading of who was never invited, the mail layer's, finds
// every administrator: Bruno holds a student's stamp, which is no invitation of an administrator.
three_admins();
$GLOBALS['umeta'][12]['wpcpm_student_invited'] = 1790000000;
$never_asked                                   = run(
	function () {
		wpcpm_load_accounts_tables();

		return array( WPCPM_Administrators_Table::invite_meta(), reach( 'never_invited' )->invoke( null ) );
	}
);

ck( 'and no stamp: the table reads none for administrators, and every administrator is one never invited, Bruno with a student\'s stamp among them',
	got(
		$never_asked,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( '', array( 11, 12, 13 ) ) );

// The module's code and the shared screen's, with their comments left out, so a docblock naming a
// method is not read as a declaration of one.
$code_of     = function ( $file ) {
	return implode(
		'',
		array_map(
			function ( $token ) {
				if ( ! is_array( $token ) ) {
					return $token;
				}

				return in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1];
			},
			token_get_all( (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ) )
		)
	);
};
$module_code = $code_of( 'includes/modules/class-wpcpm-administrators.php' );
$screen_code = $code_of( 'includes/modules/trait-wpcpm-accounts-screen.php' );

preg_match_all( '/^\t(?:(?:public|protected|private|static)\s+)*function\s+(\w+)\s*\(/m', $screen_code, $screen_methods );
preg_match_all( '/function\s+(\w+)\s*\(/', $module_code, $module_methods );

ck( 'the module uses the screen every audience\'s module shares, and declares none of its methods again but the three it replaces: the tabs\' labels, the page and the Accounts tab',
	array( 1 === preg_match( '/^\tuse WPCPM_Accounts_Screen;$/m', $module_code ), array_values( array_intersect( $screen_methods[1], $module_methods[1] ) ) ),
	array( true, array( 'tab_labels', 'render_admin_page', 'render_tab_accounts' ) ) );

// The references check reads a trait's `self::` and `static::` in each class that uses it, and not
// what the trait calls on `$this`; this reads both, in every method of the shared screen the module
// keeps, and asks the module for each: a Sync tab, an invitations card or a sync module's member
// named there would be missing here, and would stop the screen the day that method runs.
$kept = run(
	function () {
		$trait   = realpath( WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php' );
		$lines   = file( $trait );
		$kept    = array();
		$missing = array();

		foreach ( ( new ReflectionClass( 'WPCPM_Administrators' ) )->getMethods() as $method ) {
			if ( realpath( (string) $method->getFileName() ) !== $trait || $method->isAbstract() ) {
				continue;
			}

			$kept[] = $method->getName();
			$code   = '';

			foreach ( token_get_all( "<?php\n" . implode( '', array_slice( $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) ) ) as $token ) {
				$code .= ! is_array( $token ) ? $token : ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] );
			}

			preg_match_all( '/\$this->(\w+)\s*\(/', $code, $calls );

			foreach ( $calls[1] as $name ) {
				if ( ! method_exists( 'WPCPM_Administrators', $name ) ) {
					$missing[] = $method->getName() . '(): $this->' . $name . '()';
				}
			}

			preg_match_all( '/\b(?:static|self)::(\w+)(\s*\()?/', $code, $refs, PREG_SET_ORDER );

			foreach ( $refs as $ref ) {
				$call  = isset( $ref[2] ) && '' !== $ref[2];
				$found = $call ? method_exists( 'WPCPM_Administrators', $ref[1] ) : ( 'class' === $ref[1] || defined( 'WPCPM_Administrators::' . $ref[1] ) );

				if ( ! $found ) {
					$missing[] = $method->getName() . '(): static::' . $ref[1] . ( $call ? '()' : '' );
				}
			}
		}

		sort( $kept );

		return array( $kept, $missing );
	}
);

ck( 'every method of the shared screen the module keeps finds what it calls and reads on the Administrators module: no Sync tab, no invitations card, no sync module',
	got(
		$kept,
		function ( $out ) {
			return $out['value'];
		}
	),
	array(
		array( 'accounts_messages', 'accounts_url', 'boot_screen', 'handle_invite', 'handle_list_form', 'hook_screen', 'invite_selected', 'leave', 'list_url', 'load_screen', 'never_invited', 'notice_sentence', 'render_accounts_list', 'render_not_connected', 'save_per_page', 'tab', 'tab_field', 'table' ),
		array(),
	) );

echo "\n=== The screen's wiring: the load hook and the rows-per-page save ===\n";

$on_menu = array();
foreach ( isset( $GLOBALS['hooks']['admin_menu'] ) ? $GLOBALS['hooks']['admin_menu'] : array() as $added ) {
	$on_menu[] = array( is_array( $added[0] ) ? ( is_object( $added[0][0] ) ? get_class( $added[0][0] ) : $added[0][0] ) . '::' . $added[0][1] : 'another callback', $added[1] );
}

ck( 'at boot, the module hooks its screen to the menu, after the menu is built', $on_menu, array( array( 'WPCPM_Administrators::hook_screen', 11 ) ) );

// What add_menu_page() leaves for the pages under the menu: the name their hooks are filed under.
$GLOBALS['admin_page_hooks'] = array( WPCPM_Admin::MENU_SLUG => 'wpcredits-program' );
foreach ( isset( $GLOBALS['hooks']['admin_menu'] ) ? $GLOBALS['hooks']['admin_menu'] : array() as $added ) {
	call_user_func( $added[0] );
}
$on_load = isset( $GLOBALS['hooks']['load-wpcredits-program_page_wpcpm-administrators'] ) ? $GLOBALS['hooks']['load-wpcredits-program_page_wpcpm-administrators'] : array();

ck( 'once the menu has added the page, the page\'s own load hook runs the screen\'s load',
	array( count( $on_load ), isset( $on_load[0][0][1] ) ? $on_load[0][0][1] : '', isset( $on_load[0][0][0] ) && $on_load[0][0][0] === $module ),
	array( 1, 'load_screen', true ) );

$saved = array();
foreach ( array( '50', '0', '1000' ) as $value ) {
	$saved[ $value ] = apply_filters( 'set_screen_option_wpcpm_administrators_per_page', false, 'wpcpm_administrators_per_page', $value );
}
$save_hook = isset( $GLOBALS['hooks']['set_screen_option_wpcpm_administrators_per_page'][0] ) ? $GLOBALS['hooks']['set_screen_option_wpcpm_administrators_per_page'][0] : array( null, 0, 0 );

ck( 'at boot too, the save\'s filter, with the three arguments core passes: 1 to 999 kept, anything else not stored',
	array( $saved, $save_hook[2] ),
	array(
		array(
			'50'   => 50,
			'0'    => false,
			'1000' => false,
		),
		3,
	) );

echo "\n=== The screen in two tabs, Accounts and Capabilities, in the bar every audience screen prints ===\n";

three_admins();
manager( 200 );

$home     = 'https://example.test/wp-admin/admin.php?page=wpcpm-administrators';
$two_tabs = function ( $shown ) use ( $home ) {
	return array(
		'Secondary menu',
		array(
			array( $home . '&tab=accounts', 'Accounts', 'accounts' === $shown, 'accounts' === $shown ),
			array( $home . '&tab=capabilities', 'Capabilities', 'capabilities' === $shown, 'capabilities' === $shown ),
		),
	);
};
$drawn    = array();
$bars     = array();

foreach ( array(
	'from the menu'                    => array( 'page' => 'wpcpm-administrators' ),
	'asked for Accounts'               => screen(),
	'asked for Capabilities'           => screen( array( 'tab' => 'capabilities' ) ),
	'asked for a tab it does not have' => screen( array( 'tab' => 'sync' ) ),
) as $case => $query ) {
	$drawn[ $case ] = page_for( $query );
	$bars[ $case ]  = got(
		$drawn[ $case ],
		function ( $out ) {
			return bar_of( html_of( $out ) );
		}
	);
}

ck( 'the bar holds two tabs, Accounts then Capabilities, each at the screen\'s address with its tab; Accounts is the tab shown from the menu, when asked for and for a tab the screen does not have, and Capabilities when asked for, marked for the eye and for a screen reader',
	$bars,
	array(
		'from the menu'                    => $two_tabs( 'accounts' ),
		'asked for Accounts'               => $two_tabs( 'accounts' ),
		'asked for Capabilities'           => $two_tabs( 'capabilities' ),
		'asked for a tab it does not have' => $two_tabs( 'accounts' ),
	) );
ck( 'a tab the screen does not have, Sync among them, draws the Accounts tab itself, its list among it',
	has( html_of( $drawn['asked for a tab it does not have'] ), '<h2>Administrator accounts <span class="wpcpm-count">3</span></h2>' ),
	true );

$printed_bar = run(
	function () {
		ob_start();

		try {
			WPCPM_Screen_Tabs::render(
				'wpcpm-administrators',
				array(
					'accounts'     => 'Accounts',
					'capabilities' => 'Capabilities',
				),
				'accounts'
			);
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}
);
$menu_page   = html_of( $drawn['from the menu'] );

ck( 'the bar is the one every audience screen prints, `WPCPM_Screen_Tabs`\'s, tag for tag: the screen\'s one bar, and no heading holds it',
	array( '' !== html_of( $printed_bar ) && has( $menu_page, html_of( $printed_bar ) ), substr_count( $menu_page, 'nav-tab-wrapper' ), preg_match( '#<h[1-6][^>]*nav-tab-wrapper#', $menu_page ) ),
	array( true, 1, 0 ) );

$GLOBALS['l10n'] = array(
	'Accounts'       => 'Cuentas',
	'Capabilities'   => 'Capacidades',
	'Secondary menu' => 'Menú secundario',
);
$spanish         = page_for( screen( array( 'tab' => 'capabilities' ) ) );
$GLOBALS['l10n'] = array();

ck( 'the labels are translated where the bar prints them, and so is the name the bar gives itself',
	got(
		$spanish,
		function ( $out ) {
			$bar = bar_of( html_of( $out ) );

			return null === $bar ? null : array( $bar[0], array_column( $bar[1], 1 ) );
		}
	),
	array( 'Menú secundario', array( 'Cuentas', 'Capacidades' ) ) );

$names = run(
	function () {
		return array( WPCPM_Administrators::TABS, WPCPM_Administrators::TAB_ACCOUNTS, WPCPM_Administrators::TAB_CAPABILITIES );
	}
);

ck( 'the tabs are the module\'s own list, Accounts first',
	got(
		$names,
		function ( $out ) {
			return $out['value'];
		}
	),
	array(
		array(
			'accounts'     => 'Accounts',
			'capabilities' => 'Capabilities',
		),
		'accounts',
		'capabilities',
	) );

$read = array();
foreach ( array(
	'none'         => null,
	'accounts'     => 'accounts',
	'capabilities' => 'capabilities',
	'sync'         => 'sync',
	'unknown'      => 'nope',
	'empty'        => '',
) as $case => $value ) {
	request( null === $value ? array( 'page' => 'wpcpm-administrators' ) : array( 'page' => 'wpcpm-administrators', 'tab' => $value ) );
	$read[ $case ] = got(
		run(
			function () {
				return WPCPM_Administrators::tab();
			}
		),
		function ( $out ) {
			return $out['value'];
		}
	);
}

ck( 'the tab shown is the one the address names, and Accounts when it names none or one the screen does not have',
	$read,
	array(
		'none'         => 'accounts',
		'accounts'     => 'accounts',
		'capabilities' => 'capabilities',
		'sync'         => 'accounts',
		'unknown'      => 'accounts',
		'empty'        => 'accounts',
	) );

echo "\n=== Each tab holds its own card ===\n";

three_admins();
manager( 201 );

$caps_list = implode(
	'',
	array_map(
		function ( $cap ) {
			return '<li><code>' . $cap . '</code></li>';
		},
		WPCPM_Roles::administrator_caps()
	)
);
$caps_card = '<div class="wpcpm-card"><h2>Program capabilities</h2><p>Granted to the Administrator role on activation:</p><ul class="wpcpm-caps">' . $caps_list . '</ul></div>';

$accounts_page      = html_of( page_for( screen() ) );
$capabilities_page  = page_for( screen( array( 'tab' => 'capabilities' ) ) );
$capabilities_reads = $GLOBALS['queries'];
$capabilities_html  = html_of( $capabilities_page );

$parts_of = function ( $html ) use ( $caps_card ) {
	return array(
		'list'         => has( $html, '<h2>Administrator accounts <span class="wpcpm-count">' ),
		'capabilities' => has( $html, $caps_card ),
		'invitations'  => has( $html, 'wpcpm-invites' ),
		'notice'       => has( $html, 'class="notice' ),
		'settings'     => has( $html, 'Open settings' ),
	);
};

ck( 'the Accounts tab holds the administrator accounts list alone: not the capabilities, no invitations card, and no notice, though Airtable is not connected, since administrators do not arrive by a sync',
	$parts_of( $accounts_page ),
	array(
		'list'         => true,
		'capabilities' => false,
		'invitations'  => false,
		'notice'       => false,
		'settings'     => false,
	) );
ck( 'the Capabilities tab holds Program capabilities and its list, every capability the Administrator role is granted, each as code, and not the accounts list',
	array( $parts_of( $capabilities_html ), count( WPCPM_Roles::administrator_caps() ), has( $capabilities_html, '<li><code>' . WPCPM_Roles::CAP_MANAGE . '</code></li>' ) ),
	array(
		array(
			'list'         => false,
			'capabilities' => true,
			'invitations'  => false,
			'notice'       => false,
			'settings'     => false,
		),
		5,
		true,
	) );
ck( 'and reads no account to draw it', array( $capabilities_page['error'], $capabilities_reads ), array( '', array() ) );

// Nothing on this screen leaves an outcome on its channel. Were one waiting there all the same, the
// Accounts tab would not print it: the tab prints the list alone.
manager( 202 );
WPCPM_Flash::set( 'administrators_admin', 'invites-queued' );
$flashed = html_of( page_for( screen() ) );

ck( 'with an outcome waiting on the screen\'s own channel, the Accounts tab still prints no notice above its list',
	array( has( $flashed, 'class="notice' ), has( $flashed, '<h2>Administrator accounts <span class="wpcpm-count">3</span></h2>' ) ),
	array( false, true ) );

echo "\n=== The Administrator Dashboard's button, above the bar, on both tabs ===\n";

$button = '<p><a class="button button-primary" href="https://example.test/administrator-dashboard/">Open the Administrator Dashboard</a> Every queue on one page, with its decisions.</p>';

ck( 'on both tabs, the screen\'s primary action, "Open the Administrator Dashboard", opens the Administrator Dashboard\'s page, once, above the bar and under the lede',
	array( button_place( $accounts_page, $button ), button_place( $capabilities_html, $button ) ),
	array( array( 1, true ), array( 1, true ) ) );

WPCPM_Administrators_Dashboard::$url = '';
$missing_accounts                    = html_of( page_for( screen() ) );
$missing_capabilities                = html_of( page_for( screen( array( 'tab' => 'capabilities' ) ) ) );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';

ck( 'while the page is missing, neither tab offers the button, the bar stands under the lede, and the Accounts tab\'s card says the page is missing, in the dashboard class\'s words',
	array(
		has( $missing_accounts, 'Open the Administrator Dashboard' ),
		has( $missing_capabilities, 'Open the Administrator Dashboard' ),
		has( $missing_accounts, '</p><nav class="nav-tab-wrapper wp-clearfix"' ),
		has( $missing_capabilities, '</p><nav class="nav-tab-wrapper wp-clearfix"' ),
		has( $missing_accounts, esc_html( WPCPM_Administrators_Dashboard::page_missing() ) ),
	),
	array( false, false, true, true, true ) );

echo "\n";
ck( 'and nothing asked a stand-in for anything it does not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
