<?php
/**
 * The Students screen: its two tabs, Accounts and Sync, and on the Accounts tab every Student
 * account as a WordPress list table, searched, filtered by institution, sorted and paged, and the
 * invitations sent from it.
 *
 * What this pins, and why each part is worth pinning:
 *
 * - Every Student account is reachable, however many the site has. Before 1.117.1 the list asked
 *   WordPress for the first 500 accounts by display name and filtered and counted from that page
 *   alone, so once the site passed 500 students the accounts sorting after the cap were missing
 *   from the list and from their institution's count with no trace: on the live site on 28 Sep
 *   2026, 13 of 513 accounts, and one school's picker read 13 where it had 14. The list is paged
 *   now: its total counts every account, the last page holds the 501st, and the institution
 *   picker counts from a read of every account, never from a page. Two accounts of one name keep
 *   one order from page to page, the ID after the name, so neither shows twice while the other is
 *   on no page.
 * - The table's columns and their values, the row actions under the name (Edit, View page, the
 *   invitation) and the views' counts.
 * - The institution lives inside the serialized program row the students sync writes, so no query
 *   can match, search or sort by it. The table reads every Student account's row once and hands
 *   WordPress the IDs: the filter is exact (the trim-equality the invitations card narrows by, so a
 *   school whose name holds another's is not the other), the search reaches the institution as
 *   well as the name, username and email, and the Institution sort orders by school, then name,
 *   across pages. The filter stays in the view, sort and page links, its name encoded whole.
 * - The bulk actions are handled on the screen's load hook, before anything is drawn, as core's
 *   own lists handle theirs: the capability and the list's nonce before anything is read, then Send
 *   invite queues the ticked accounts never invited and Resend invite the ticked accounts invited
 *   before, each checked against the Student accounts table's own role and stamps, and the screen
 *   says how many were queued. Resend invite leaves out anybody sent an invitation in the last
 *   fifteen minutes, whom the queue's drain would pass over, and says how many it left out. The
 *   invitations card's own button, narrowed to the list's school, queues that school's never
 *   invited and comes back to the list at that school.
 * - A row's invitation is a nonce link to the Students module's own invite handler, which sends it
 *   at once, as the row's form did, and a wrong nonce is refused before anything is read. The link
 *   carries the list's view, search, school, sort and page, and the handler comes back to them, the
 *   way a press on the ticked accounts comes back.
 * - The screen's wiring: its load hook, found once the menu has added the page; the tables loaded
 *   there, for the Accounts tab, and not before; the rows-per-page option on it; and the choice's
 *   save, hooked at boot, because WordPress saves screen options before it builds the menu.
 * - The screen is two tabs, in the Settings screen's own bar, printed by `WPCPM_Screen_Tabs`, which
 *   the Mentors and Administrators screens print too: Accounts, the tab the screen opens on from
 *   the menu and for a tab it does not have, holding the invitations card and the list; and Sync,
 *   holding the Airtable sync, its last error and its last report, where no account is read and no
 *   list option offered. Every press comes back to the tab it was made on, each of the screen's own
 *   forms naming its tab and the mail layer's Stop and Dismiss returning to the address they were
 *   pressed on, Stop's outcome on the flash channel its form names for the screen, while a screen
 *   still drawn in one piece keeps its own address; and each tab prints the notices of the presses
 *   made on it, in the order the tab's parts are read, so an outcome is read where the press was
 *   made and never waits to surface on the other tab.
 *
 * WordPress is stood in for as far as the screen reaches, by the stand-ins every accounts screen's
 * suite shares (bin/stubs/accounts-screen.php), with `include` modeled for this screen's list and
 * core's `selected()` declared here, since only this screen asks either. `WP_User_Query` answers
 * over the fixture accounts the way WordPress answers the arguments the screen passes (listed on
 * the class), and anything else it is asked is kept apart and fails the last check.
 * `add_query_arg()` is core's, encoding and all, so a value the screen forgets to encode breaks its
 * link here as it would on the site. The list table is bin/stubs/class-wp-list-table.php, loaded
 * through this suite's own `wpcpm_load_accounts_tables()`, which requires what the plugin's does
 * (bin/test-accounts-table.php pins the plugin's); its markup is close to core's and is not core's.
 *
 * Each step is run, and what it drew is read, through the helpers the three screen suites share
 * (bin/stubs/screen-helpers.php).
 *
 * Run from the plugin root:  php bin/test-students-screen.php
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

// The Students screen narrows its list to IDs of its own reading, the accounts at the institution
// chosen and the accounts its search finds (`WPCPM_Students_Table::query()`), so the accounts query
// models `include` for it.
$GLOBALS['query_include'] = true;

/** Core's `selected()`, which the institution picker marks the school in force with: this screen's alone. */
function selected( $selected, $current = true, $display = true ) {
	$out = (string) $selected === (string) $current ? ' selected="selected"' : '';

	if ( $display ) {
		echo $out;
	}

	return $out;
}

/**
 * The settings, as far as the screen and the sync's start read them: whether Airtable is connected,
 * which a check can switch off, and the settings the start hands the statuses check.
 */
class WPCPM_Settings {
	public static $connected = true;
	public static function is_connected() { return self::$connected; }
	public static function get() { return array(); }
}

/** The mentors module's clock for a run's elapsed time, which the sync card prints. */
class WPCPM_Mentors {
	public static function format_duration( $seconds ) { return (int) $seconds . 's'; }
}

/** The statuses check the sync's start makes before it writes anything: every status is set here. */
class WPCPM_Mentors_Sync {
	public static function empty_statuses_error( $settings ) { return null; }
}

/** The student page's address, which the list prints once and links from every row as View page. */
class WPCPM_Students_Dashboard {
	public static $url = 'https://example.test/student-dashboard/';
	public static function page_url() { return self::$url; }
	public static function init() {}
}

/** The Student Report Card's form and the feedback form: booted by the module, never drawn here. */
class WPCPM_Student_Report_Form {
	public static function init() {}
}
class WPCPM_Student_Feedback {
	public static function init() {}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';

// The tab bar's printer, required once it exists, so a copy of the plugin without it fails its
// checks rather than ending this run.
if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
}

/**
 * A module whose screen is drawn in one piece, declaring no tabs, as every audience's screen was
 * before it took them: its presses come back to its own address, whatever tab a form names.
 */
class WPCPM_Stub_Untabbed_Module extends WPCPM_Sync_Module {
	const ACTION_SYNC   = 'wpcpm_untabbed_sync';
	const ACTION_CANCEL = 'wpcpm_untabbed_cancel';
	const ACTION_TICK   = 'wpcpm_untabbed_tick';
	public function id() { return 'untabbed'; }
	public function label() { return 'Untabbed'; }
	public function role() { return 'subscriber'; }
	public function description() { return ''; }
	protected function sync_class() { return 'WPCPM_Students_Sync'; }
	protected function flash_key() { return 'untabbed_admin'; }
	public function render_admin_page() {}
}

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, which there is
 * no WordPress here to lend, then the files the plugin's own `wpcpm_load_accounts_tables()` requires,
 * in its order. The two tables' files are required once they exist, so a copy of the plugin without
 * one fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';

	foreach ( array( 'students', 'mentors' ) as $audience ) {
		if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-' . $audience . '-table.php' ) ) {
			require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-' . $audience . '-table.php';
		}
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
 * The Students screen's query, with whatever else a check wants on it.
 *
 * @param array $more More query arguments.
 * @return array
 */
function screen( array $more = array() ) {
	return array_merge(
		array(
			'page' => 'wpcpm-students',
			'tab'  => 'accounts',
		),
		$more
	);
}

/**
 * A method of the Students module a check reaches that the module keeps to itself.
 *
 * @param string $name The method.
 * @return ReflectionMethod
 */
function reach( $name ) {
	$method = new ReflectionMethod( 'WPCPM_Students', $name );

	// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method;
}

/**
 * The Student accounts card, as the screen draws it for a request.
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
				$method->invoke( new WPCPM_Students() );
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
			$module = new WPCPM_Students();
			$module->load_screen();

			return 'drawn';
		}
	);
}

/**
 * The Students screen as WordPress draws it for a request: the screen's load hook, then the page,
 * from the one module, as the plugin's registry keeps one.
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
			$module = new WPCPM_Students();
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
 * The status notice the screen prints for what the last press left, for the person looking: the
 * Students screen drawn at the tab the press came back to, the Accounts tab unless a check names
 * another, and the notices in it.
 *
 * @param string $tab The tab.
 * @return array What run() kept; the notices' markup is its value.
 */
function notice_now( $tab = 'accounts' ) {
	$out = page_for( screen( array( 'tab' => $tab ) ) );

	if ( '' === $out['error'] ) {
		$out['value'] = notices_in( html_of( $out ) );
	}

	return $out;
}

/**
 * The tab bar a drawn screen prints, read as the Settings screen's suite reads that screen's own:
 * core's newer bar, a navigation region named for a screen reader, holding plain links.
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
 * The label of the tab a drawn screen's bar marks as the one shown, or null for none.
 *
 * @param string $html Markup.
 * @return string|null
 */
function shown_tab( $html ) {
	$bar = bar_of( $html );

	foreach ( null === $bar ? array() : $bar[1] as $tab ) {
		if ( $tab[2] ) {
			return $tab[1];
		}
	}

	return null;
}

/**
 * A tab bar's markup with its words and the two slugs of each address taken out: the region's
 * opening tag, each way a link in it is marked up, and whatever else the region holds, which in
 * core's markup is nothing. Two bars of one shape answer the same, whatever their tabs.
 *
 * @param string $html Markup holding one bar.
 * @return array|null
 */
function bar_shape( $html ) {
	if ( ! preg_match( '#(<nav\b[^>]*>)(.*?)</nav>#s', (string) $html, $bar ) ) {
		return null;
	}

	preg_match_all( '#<a href="[^"]*" class="nav-tab(?: nav-tab-active)?"(?: aria-current="page")?>[^<]*</a>#', $bar[2], $links );

	$kinds = array();

	foreach ( $links[0] as $link ) {
		$kinds[] = preg_replace(
			array( '#page=[a-z0-9_-]+&\#038;tab=[a-z0-9_-]+#', '#>[^<]*</a>#' ),
			array( 'page=P&#038;tab=T', '></a>' ),
			$link
		);
	}

	$kinds = array_values( array_unique( $kinds ) );
	sort( $kinds );

	return array( $bar[1], $kinds, str_replace( $links[0], '', $bar[2] ) );
}

/**
 * The tab a form names in its hidden field, or null when it names none.
 *
 * @param string $form A form's markup.
 * @return string|null
 */
function tab_field_of( $form ) {
	return preg_match( '#<input type="hidden" name="wpcpm_tab" value="([^"]*)" />#', (string) $form, $found ) ? $found[1] : null;
}

/**
 * A drawn form sent as a browser sends it, its hidden fields posted to admin-post.php, to the
 * handler its action names.
 *
 * @param string   $form    The form's markup.
 * @param callable $handler The handler.
 * @return array What run() kept.
 */
function press_form( $form, callable $handler ) {
	request( array(), hidden_fields_of( $form ) );
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin-post.php';

	return run(
		function () use ( $handler ) {
			$handler();
		}
	);
}

/**
 * A press on one of a module's admin-post handlers: its action's nonce, and the fields its form posts.
 *
 * @param string           $method The handler.
 * @param string           $action The admin-post action, which is also the nonce's.
 * @param array            $fields The form's other fields.
 * @param WPCPM_Module|null $module The module, the Students one unless a check gives another.
 * @return array What run() kept.
 */
function post_to( $method, $action, array $fields, $module = null ) {
	request( array( 'action' => $action ), array_merge( array( '_wpnonce' => wp_create_nonce( $action ) ), $fields ) );

	return run(
		function () use ( $method, $module ) {
			$module = null === $module ? new WPCPM_Students() : $module;
			$module->$method();
		}
	);
}

/**
 * A fresh program manager for a press: WPCPM_Flash remembers what it took for a person within one
 * run of PHP, so each press that leaves a notice is made by somebody new.
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
 * One account's row, by its checkbox.
 *
 * @param string $html Markup.
 * @param int    $id   User ID.
 * @return string
 */
function row_for( $html, $id ) {
	foreach ( array_slice( preg_split( '/<tr[ >]/', between( $html, '<tbody', '</tbody>' ) ), 1 ) as $row ) {
		if ( has( $row, 'name="users[]" id="wpcpm-account-' . (int) $id . '"' ) ) {
			return $row;
		}
	}

	return '';
}

/**
 * One view's link.
 *
 * @param string $html Markup.
 * @param string $view The view.
 * @return string
 */
function view_link( $html, $view ) {
	return preg_match( "/<li class='" . preg_quote( $view, '/' ) . "'>(<a [^>]*>)/", (string) $html, $found ) ? $found[1] : '';
}

function heading_count( $html ) {
	return preg_match( '/<h2>Student accounts <span class="wpcpm-count">([^<]*)<\/span><\/h2>/', (string) $html, $found ) ? $found[1] : null;
}

/**
 * The institution picker's options: value, whether it is selected, and what it says.
 *
 * @param string $html Markup.
 * @return array[]
 */
function picker( $html ) {
	preg_match_all( '/<option value="([^"]*)"( selected="selected")?>([^<]*)<\/option>/', between( $html, '<select name="wpcpm_institution"', '</select>' ), $found, PREG_SET_ORDER );

	return array_map(
		function ( $one ) {
			return array( $one[1], '' !== $one[2], $one[3] );
		},
		$found
	);
}

/**
 * The queries a drawn list made, by what each was for.
 *
 * @param string $kind `page` (the rows drawn), `every` (the read of every account), `count` (a view's
 *                     count), `search` (the IDs a search finds) or `ordered` (the IDs an Institution
 *                     sort orders).
 * @return array[]
 */
function queries( $kind ) {
	return array_values(
		array_filter(
			$GLOBALS['queries'],
			function ( $args ) use ( $kind ) {
				$ids    = isset( $args['fields'] ) && 'ID' === $args['fields'];
				$number = isset( $args['number'] ) ? (int) $args['number'] : 0;

				switch ( $kind ) {
					case 'page':
						return ! $ids && $number > 0;
					case 'every':
						return ! $ids && -1 === $number;
					case 'count':
						return $ids && 1 === $number;
					case 'search':
						return $ids && -1 === $number && isset( $args['search'] );
					case 'ordered':
						return $ids && -1 === $number && ! isset( $args['search'] );
				}

				return false;
			}
		)
	);
}

/* ---- fixtures ------------------------------------------------------------- */

/**
 * Put an account on the site with the program row the students sync writes. Every row is complete,
 * so `get_program()`'s heal-from-the-mentor path is never entered.
 *
 * @param int         $id          User ID.
 * @param string      $name        Display name.
 * @param string      $login       Username, or '' for one made from the name.
 * @param string[]    $roles       Roles.
 * @param string      $institution The institution as the program row holds it.
 * @param string      $program     The program.
 * @param string      $mentor      The mentor's name, or '' for none.
 * @param int|null    $invited     The invitation stamp, or null for none.
 */
function person( $id, $name, $login, array $roles, $institution, $program = 'Developer Track', $mentor = 'Mentor Example', $invited = null ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, $roles, $login );
	$GLOBALS['umeta'][ $id ] = array(
		WPCPM_Students_Sync::META_PROGRAM => array(
			'record_id'      => 'rec' . $id,
			'name'           => $name,
			'program'        => $program,
			'is_past'        => false,
			'institution'    => $institution,
			'field_of_study' => 'Technology & Engineering',
			'accessibility'  => 'none',
		),
		WPCPM_Students_Sync::META_MENTOR  => '' === $mentor ? array() : array(
			'name'      => $mentor,
			'record_id' => 'recMentor',
		),
	);

	if ( null !== $invited ) {
		$GLOBALS['umeta'][ $id ]['wpcpm_student_invited'] = $invited;
	}
}

function reset_site() {
	$GLOBALS['users']       = array();
	$GLOBALS['umeta']       = array();
	$GLOBALS['ids_as_rows'] = false;
	$GLOBALS['no_editor']   = false;
	manager( 1 );
}

/**
 * Three students at three schools: Ada invited long ago, Bruno and Cleo never.
 */
function three_students() {
	reset_site();
	person( 11, 'Ada Lovelace', 'alovelace', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte', 'Developer Track', 'Mentor Uno', 1700000000 );
	person( 12, 'Bruno Diaz', 'bdiaz', array( WPCPM_Roles::ROLE_STUDENT ), 'Academia Sur', 'Contributor Track', 'Mentor Dos' );
	person( 13, 'Cleo Ahn', 'cahn', array( WPCPM_Roles::ROLE_STUDENT ), 'Instituto Este & Oeste', 'Developer Track', '' );
}

/**
 * The three, and more at four schools and none: Dana at a school whose name holds Ada's, Eve at
 * Bruno's with spaces around its name, Fred at no school, and a mentor and a former student at
 * Ada's school, neither of them on the list.
 */
function six_students() {
	three_students();
	person( 14, 'Dana Park', 'dpark', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte Dos' );
	person( 15, 'Eve Stone', 'estone', array( WPCPM_Roles::ROLE_STUDENT ), '  Academia Sur ', 'Developer Track', 'Mentor Dos', 1700000000 );
	person( 16, 'Fred Nobody', 'fnobody', array( WPCPM_Roles::ROLE_STUDENT ), '' );
	person( 20, 'Mia Mentor', 'mmentor', array( WPCPM_Roles::ROLE_MENTOR ), 'Escuela Norte' );
	person( 21, 'Old Student', 'ostudent', array( 'subscriber' ), 'Escuela Norte' );
}

// The request that saves a rows-per-page choice, as WordPress makes it: the module booted on
// `plugins_loaded`, then the save's filter applied in `set_screen_options()`, before the menu, the
// screen's load hook or any table exists. In a process of its own, because the checks below declare
// the tables, and a class once declared cannot be undeclared.
if ( isset( $argv[1] ) && 'child-save' === $argv[1] ) {
	$module = new WPCPM_Students();
	$module->boot();

	$declared = class_exists( 'WPCPM_Students_Table', false );
	$saved    = run(
		function () {
			return apply_filters( 'set_screen_option_wpcpm_students_per_page', false, 'wpcpm_students_per_page', '50' );
		}
	);

	echo json_encode(
		array(
			'before' => $declared,
			'saved'  => $saved['value'],
			'error'  => $saved['error'],
			'after'  => class_exists( 'WPCPM_Students_Table', false ),
		)
	);
	exit( 0 );
}

// The invitations card's button as WordPress runs it: admin-post.php, where nothing has loaded the
// list tables the card's reading of the role and the stamp comes from. In a process of its own, for
// the reason the save's run above has one.
if ( isset( $argv[1] ) && 'child-card' === $argv[1] ) {
	three_students();

	$declared = class_exists( 'WPCPM_Students_Table', false );
	$pressed  = post_to(
		'handle_bulk_invite',
		WPCPM_Students::ACTION_BULK,
		array(
			'wpcpm_tab'         => 'accounts',
			'wpcpm_institution' => '',
		)
	);

	echo json_encode(
		array(
			'before'   => $declared,
			'error'    => $pressed['error'],
			'redirect' => $pressed['redirect'],
			'queue'    => WPCPM_Mail::queue(),
		)
	);
	exit( 0 );
}

/* ---- the checks ----------------------------------------------------------- */

echo "=== The screen loads its list table on its own load hook, and not before ===\n";

ck( 'nothing has declared a list table before the screen loads',
	array( class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Students_Table', false ) ),
	array( false, false, false ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-save 2>&1', $save_out, $save_status );
$save = (array) json_decode( implode( "\n", $save_out ), true );

ck( 'a rows-per-page choice is saved as WordPress saves it, before any table is loaded: the save loads the table itself, and keeps 50',
	array( $save_status, isset( $save['before'] ) ? $save['before'] : implode( "\n", $save_out ), isset( $save['error'] ) ? $save['error'] : null, isset( $save['saved'] ) ? $save['saved'] : null, isset( $save['after'] ) ? $save['after'] : null ),
	array( 0, false, '', 50, true ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-card 2>&1', $card_out, $card_status );
$card_run = (array) json_decode( implode( "\n", $card_out ), true );

ck( 'the invitations card\'s button, pressed where no list table is loaded, as admin-post.php runs it, loads the table it reads the role and the stamp from, queues the two never invited and comes back to the Accounts tab',
	array( $card_status, isset( $card_run['before'] ) ? $card_run['before'] : implode( "\n", $card_out ), isset( $card_run['error'] ) ? $card_run['error'] : null, isset( $card_run['redirect'] ) ? $card_run['redirect'] : null, isset( $card_run['queue'] ) ? $card_run['queue'] : null ),
	array( 0, false, '', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=accounts', array( 12, 13 ) ) );

three_students();
$sync_load = press( screen( array( 'tab' => 'sync' ) ) );

ck( 'on the Sync tab, which has no list, the load hook leaves the list alone: no list table is loaded, no rows-per-page option offered, no account read and no list headings set, and the page goes on',
	got(
		$sync_load,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Students_Table', false ), $GLOBALS['screen_options'], $GLOBALS['queries'], $GLOBALS['current_screen']->reader_content );
		}
	),
	array( 'drawn', false, false, false, array(), array(), array() ) );

three_students();
$loaded = press( screen() );

ck( 'the screen\'s load hook loads core\'s list table, the accounts base and the Students table, and lets the page go on',
	got(
		$loaded,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Students_Table', false ) );
		}
	),
	array( 'drawn', true, true, true ) );
ck( 'it puts the rows-per-page option on the screen: twenty until somebody chooses, kept under the Students\' own name',
	isset( $GLOBALS['screen_options']['per_page'] ) ? $GLOBALS['screen_options']['per_page'] : 'no option added',
	array(
		'default' => 20,
		'option'  => 'wpcpm_students_per_page',
	) );
ck( 'and prepares the list there, before the page\'s header, as core\'s own lists do', count( queries( 'page' ) ), 1 );
ck( 'it names the list\'s three headings for screen readers, which core leaves out unless the screen sets them',
	$GLOBALS['current_screen']->reader_content,
	array(
		'heading_views'      => 'Filter student accounts list',
		'heading_pagination' => 'Student accounts list navigation',
		'heading_list'       => 'Student accounts list',
	) );

request( screen() );
$GLOBALS['current_screen'] = null;
$screenless                = run(
	function () {
		( new WPCPM_Students() )->load_screen();

		return 'drawn';
	}
);

ck( 'and without a screen to name them on, it goes on without them', got(
	$screenless,
	function ( $out ) {
		return $out['value'];
	}
), 'drawn' );

// Core prints the three headings as they are given, so a translation holding markup would reach the
// page as markup.
$GLOBALS['l10n'] = array( 'Student accounts list' => 'Lista de <b>cuentas</b>' );
press( screen() );
$GLOBALS['l10n'] = array();

ck( 'the headings are escaped before core prints them as they are',
	isset( $GLOBALS['current_screen']->reader_content['heading_list'] ) ? $GLOBALS['current_screen']->reader_content['heading_list'] : 'not set',
	'Lista de &lt;b&gt;cuentas&lt;/b&gt;' );

// The menu opens the screen at its own address, which names no tab: that is the Accounts tab, and
// every sort and page link core builds from it names none either.
$GLOBALS['screen_options'] = array();
$from_menu                 = press( array( 'page' => 'wpcpm-students' ) );

ck( 'from the menu\'s own address, which names no tab, the load hook does the Accounts tab\'s work: the rows-per-page option, the three headings and the page of rows, read before the page\'s header',
	got(
		$from_menu,
		function ( $out ) {
			return array( $out['value'], isset( $GLOBALS['screen_options']['per_page']['option'] ) ? $GLOBALS['screen_options']['per_page']['option'] : 'no option added', array_keys( $GLOBALS['current_screen']->reader_content ), count( queries( 'page' ) ) );
		}
	),
	array( 'drawn', 'wpcpm_students_per_page', array( 'heading_views', 'heading_pagination', 'heading_list' ), 1 ) );

echo "\n=== Every Student account is reachable, a page at a time: 501 at one school, one elsewhere ===\n";

// Named so that display-name order is the numbering (Student 001 to Student 501), one more at
// another school, a mentor, and a former student whose role the sync took away.
reset_site();
$school = 'Universidad de Ejemplo';
for ( $i = 1; $i <= 501; $i++ ) {
	person( 100 + $i, sprintf( 'Student %03d', $i ), '', array( WPCPM_Roles::ROLE_STUDENT ), $school, 'Developer Track', 'Mentor Example', 1 );
}
person( 700, 'Zed Elsewhere', '', array( WPCPM_Roles::ROLE_STUDENT ), 'Another School', 'Developer Track', 'Mentor Example', 1 );
person( 701, 'Mentor Example', '', array( WPCPM_Roles::ROLE_MENTOR ), $school, 'Developer Track', 'Mentor Example', 1 );
person( 702, 'Former Student', '', array( 'subscriber' ), $school, 'Developer Track', 'Mentor Example', 1 );

$first = draw( screen() );
$html  = html_of( $first );

ck( 'the first page lists twenty accounts, the rows a page until somebody chooses another number',
	got(
		$first,
		function ( $out ) {
			return count( row_ids( html_of( $out ) ) );
		}
	),
	20 );
ck( 'the heading, the pagination and the views count every Student account: 501 at the school and one elsewhere',
	array( heading_count( $html ), displaying( $html ), view_counts( $html ) ),
	array(
		'502',
		'502 items',
		array(
			'all'           => '502',
			'invited'       => '502',
			'never-invited' => '0',
		),
	) );
ck( 'the list asked WordPress for one page, and for its total',
	array( arg( first_query( 'page' ), 'number' ), arg( first_query( 'page' ), 'offset' ), arg( first_query( 'page' ), 'count_total' ) ),
	array( 20, 0, true ) );
ck( 'the picker counts the whole school, from a read of every Student account, never from a page',
	array( has( $html, esc_html( $school ) . ' (501)' ), arg( first_query( 'every' ), 'number' ), arg( first_query( 'every' ), 'role' ) ),
	array( true, -1, WPCPM_Roles::ROLE_STUDENT ) );

$last = draw( screen( array( 'paged' => '26' ) ) );

ck( 'the last page, the 26th, holds the 501st account by name and the one elsewhere', row_ids( html_of( $last ) ), array( 601, 700 ) );

$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = 999;
$every = row_ids( html_of( draw( screen() ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

ck( 'on one page of 999, every Student account is listed, and the mentor and the former student are not',
	array( count( $every ), in_array( 601, $every, true ), in_array( 701, $every, true ), in_array( 702, $every, true ) ),
	array( 502, true, false, false ) );

echo "\n=== The same list narrowed to the school ===\n";

$html = html_of( draw( screen( array( 'wpcpm_institution' => $school ) ) ) );

ck( 'narrowed to the school, the heading and the pagination count its 501 students', array( heading_count( $html ), displaying( $html ) ), array( '501', '501 items' ) );
ck( 'its last page holds the 501st by name', row_ids( html_of( draw( screen( array( 'wpcpm_institution' => $school, 'paged' => '26' ) ) ) ) ), array( 601 ) );

$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = 999;
$every = row_ids( html_of( draw( screen( array( 'wpcpm_institution' => $school ) ) ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

ck( 'and one page of it lists every one of the school\'s 501 students, and not the student from the other school',
	array( count( $every ), in_array( 601, $every, true ), in_array( 700, $every, true ) ),
	array( 501, true, false ) );

echo "\n=== The table: a checkbox, then Student, Username, Program, Institution and Mentor ===\n";

three_students();
$html = html_of( draw( screen() ) );

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", between( $html, '<thead>', '</thead>' ), $header_ids );

ck( 'the headers are the checkbox, then the five columns in order', $header_ids[1], array( 'cb', 'name', 'login', 'program', 'institution', 'mentor' ) );
ck( 'each says what it holds',
	array( has( $html, '<span>Student</span>' ), has( $html, '<span>Username</span>' ), has( $html, '>Program</th>' ), has( $html, '<span>Institution</span>' ), has( $html, '>Mentor</th>' ) ),
	array( true, true, true, true, true ) );
ck( 'a row per student, by name: Ada, Bruno, Cleo', row_ids( $html ), array( 11, 12, 13 ) );

$ada  = row_for( $html, 11 );
$cleo = row_for( $html, 13 );

ck( 'Ada\'s row: her name to her account\'s editor, her username, program, school and mentor',
	array( cell( $ada, 'name' ), cell( $ada, 'login' ), cell( $ada, 'program' ), cell( $ada, 'institution' ), cell( $ada, 'mentor' ) ),
	array( '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=11">Ada Lovelace</a></strong>', '<code>alovelace</code>', 'Developer Track', 'Escuela Norte', 'Mentor Uno' ) );
ck( 'Cleo\'s: a school with an ampersand, escaped, and no mentor yet', array( cell( $cleo, 'institution' ), cell( $cleo, 'mentor' ) ), array( 'Instituto Este &amp; Oeste', '-' ) );

$ada_actions   = actions_in( $ada );
$bruno_actions = actions_in( row_for( $html, 12 ) );

ck( 'under each name, Edit, View page, then the invitation: Resend invite for Ada, invited before, Send invite for Bruno',
	array( array_keys( $ada_actions ), array_keys( $bruno_actions ) ),
	array( array( 'edit', 'view', 'reinvite' ), array( 'edit', 'view', 'invite' ) ) );
ck( 'which say so', array_map( 'strip_tags', $ada_actions ), array( 'edit' => 'Edit', 'view' => 'View page', 'reinvite' => 'Resend invite' ) );
ck( 'Edit opens the account\'s editor, and View page the student page as that student',
	array( link_of( isset( $ada_actions['edit'] ) ? $ada_actions['edit'] : '' ), link_of( isset( $ada_actions['view'] ) ? $ada_actions['view'] : '' ) ),
	array( array( 'https://example.test/wp-admin/user-edit.php', array( 'user_id' => '11' ) ), array( 'https://example.test/student-dashboard/', array( 'wpcpm_student_view' => '11' ) ) ) );
ck( 'the views count the three: All, one Invited, two Never invited',
	view_counts( $html ),
	array(
		'all'           => '3',
		'invited'       => '1',
		'never-invited' => '2',
	) );

$form = between( $html, '<form method="get"', '</form>' );

ck( 'the list is one form, sent to the screen by GET with its page and its tab, holding the checkboxes, the bulk actions and their nonce',
	array( has( $form, '<input type="hidden" name="page" value="wpcpm-students" />' ), has( $form, '<input type="hidden" name="tab" value="accounts" />' ), has( $form, 'name="users[]"' ), has( $form, '<select name="action"' ), has( $form, 'value="' . wp_create_nonce( 'bulk-students' ) . '"' ) ),
	array( true, true, true, true, true ) );
ck( 'with the search box, and no form left in a row', array( has( $form, 'name="s"' ), substr_count( $html, '<form' ) ), array( true, 1 ) );

// The institution picker sends the form by GET and the page loads again from its top, with the
// list a screen's height or more below the notices and the invitations card. The form is sent to
// the list card's own anchor, so a filter, a search or a view lands with the list in view.
preg_match( '/<form method="get" action="#([A-Za-z0-9_-]+)"/', $html, $anchor );

ck( 'the list\'s form is sent to an anchor, and the list card carries that anchor, so the filter brings the list back into view',
	array( isset( $anchor[1] ) ? $anchor[1] : '', isset( $anchor[1] ) && has( $html, '<div class="wpcpm-card" id="' . $anchor[1] . '"' ), substr_count( $html, 'id="wpcpm-accounts-list"' ) ),
	array( 'wpcpm-accounts-list', true, 1 ) );
ck( 'and the admin stylesheet leaves the fixed toolbar clear above the anchored card',
	1 === preg_match( '/#wpcpm-accounts-list\s*\{[^}]*scroll-margin-top\s*:\s*\d+px/', (string) file_get_contents( __DIR__ . '/../assets/css/admin.css' ) ), true );
ck( 'above it the Student Report Card\'s address, under the page\'s own name',
	has( $html, '<p>Student Report Card: <a href="https://example.test/student-dashboard/">https://example.test/student-dashboard/</a></p>' ), true );

$never = html_of( draw( screen( array( 'wpcpm_view' => 'never-invited' ) ) ) );

ck( 'on the Never invited view, Bruno and Cleo, and the form carries the view, so a search or a filter stays on it',
	array( row_ids( $never ), has( between( $never, '<form method="get"', '</form>' ), '<input type="hidden" name="wpcpm_view" value="never-invited" />' ) ),
	array( array( 12, 13 ), true ) );

WPCPM_Students_Dashboard::$url = '';
$GLOBALS['no_editor']          = true;
$bare                          = row_for( html_of( draw( screen() ) ), 11 );
WPCPM_Students_Dashboard::$url = 'https://example.test/student-dashboard/';
$GLOBALS['no_editor']          = false;

ck( 'with no student page and no right to the editor, the name is plain and the invitation the one action',
	array( cell( $bare, 'name' ), array_keys( actions_in( $bare ) ) ),
	array( '<strong>Ada Lovelace</strong>', array( 'reinvite' ) ) );

reset_site();

ck( 'with no Student account yet, the list says how accounts arrive, and on which tab the sync is', has( html_of( draw( screen() ) ), 'No student accounts yet. Run a sync on the Sync tab to create them.' ), true );

echo "\n=== Invited is any stamp: a student who mentors, sent a mentor's invitation ===\n";

// An account has one password, so an invitation sent it as a mentor is one sent it as a student
// too: the list's views, its row and the invitations card read every stamp, as the queue does.
// Gil studies and mentors and was stamped as a mentor alone; Bruno and Cleo were never sent one.
three_students();
person( 17, 'Gil Both', 'gboth', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ), 'Escuela Norte' );
$GLOBALS['umeta'][17]['wpcpm_mentor_invited'] = 1700000000;
manager( 230 );
$both_page = html_of( page_for( screen() ) );
$both_card = forms_by_action( $both_page );

ck( 'Gil is Invited and counted so, his row offers Resend invite, and the invitations card counts the two never sent one',
	array(
		view_counts( $both_page ),
		array_keys( actions_in( row_for( $both_page, 17 ) ) ),
		has( isset( $both_card[ WPCPM_Students::ACTION_BULK ] ) ? $both_card[ WPCPM_Students::ACTION_BULK ] : '', '>Invite 2 students who have never been invited</button>' ),
	),
	array( array( 'all' => '4', 'invited' => '2', 'never-invited' => '2' ), array( 'edit', 'view', 'reinvite' ), true ) );
// The card's question is shared by every audience's screen, so its words fit any audience's noun.
ck( 'the card asks before it sends, of the two, in the plural',
	has( $both_page, 'data-wpcpm-confirm="Send an invitation to 2 of the students? They cannot be recalled once sent."' ),
	true );
ck( 'the Invited view lists him with Ada, and the Never invited view Bruno and Cleo',
	array( row_ids( html_of( draw( screen( array( 'wpcpm_view' => 'invited' ) ) ) ) ), row_ids( html_of( draw( screen( array( 'wpcpm_view' => 'never-invited' ) ) ) ) ) ),
	array( array( 11, 17 ), array( 12, 13 ) ) );

echo "\n=== The institution filter: exact, as the invitations card narrows ===\n";

six_students();
$north = draw( screen( array( 'wpcpm_institution' => 'Escuela Norte' ) ) );

ck( 'narrowed to Escuela Norte, the list is Ada alone: not Dana, whose school\'s name holds hers, nor the mentor or the former student there',
	got(
		$north,
		function ( $out ) {
			return row_ids( html_of( $out ) );
		}
	),
	array( 11 ) );
ck( 'the page\'s query asked WordPress for that school\'s accounts by ID', arg( first_query( 'page' ), 'include' ), array( 11 ) );

$south = html_of( draw( screen( array( 'wpcpm_institution' => 'Academia Sur' ) ) ) );

ck( 'by the trim-equality the card narrows by: Eve, whose row holds the name with spaces around it, is at Academia Sur with Bruno', row_ids( $south ), array( 12, 15 ) );
ck( 'the views count the school\'s accounts only',
	view_counts( $south ),
	array(
		'all'           => '2',
		'invited'       => '1',
		'never-invited' => '1',
	) );
ck( 'each view\'s count asked for the school\'s IDs too',
	array_map(
		function ( $args ) {
			return arg( $args, 'include' );
		},
		queries( 'count' )
	),
	array( array( 12, 15 ), array( 12, 15 ) ) );

$nowhere = html_of( draw( screen( array( 'wpcpm_institution' => 'Nowhere' ) ) ) );

ck( 'a school nobody is at lists nobody, and WordPress is asked for no account rather than for all of them',
	array( row_ids( $nowhere ), arg( first_query( 'page' ), 'include' ), displaying( $nowhere ) ),
	array( array(), array( 0 ), '0 items' ) );
ck( 'the picker offers every school with its count, read from every Student account, the one in force selected',
	picker( $south ),
	array(
		array( '', false, 'All institutions' ),
		array( 'Academia Sur', true, 'Academia Sur (2)' ),
		array( 'Escuela Norte', false, 'Escuela Norte (1)' ),
		array( 'Escuela Norte Dos', false, 'Escuela Norte Dos (1)' ),
		array( 'Instituto Este &amp; Oeste', false, 'Instituto Este &amp; Oeste (1)' ),
	) );

$tablenav = between( $south, '<div class="tablenav top">', '<br class="clear" />' );

ck( 'the picker sits in the table\'s own navigation, inside the list\'s form, beside a Filter button that is no bulk action',
	array( has( $tablenav, '<select name="wpcpm_institution" id="wpcpm-institution">' ), has( $tablenav, 'name="filter_action"' ), has( between( $south, '<form method="get"', '</form>' ), '<select name="wpcpm_institution"' ) ),
	array( true, true, true ) );

ck( 'the picker is drawn once, above the table, so the form sends one school and no id twice',
	array( substr_count( $south, 'name="wpcpm_institution"' ), substr_count( $south, 'id="wpcpm-institution"' ) ),
	array( 1, 1 ) );

preg_match( '/<a href="([^"]*)">Show all students<\/a>/', $tablenav, $back );

$back_href = isset( $back[1] ) ? html_entity_decode( $back[1], ENT_QUOTES, 'UTF-8' ) : '';

ck( 'with a link back to every student: the Accounts tab, no school', link_of( preg_replace( '/#.*$/', '', $back_href ) ), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) ) );
// The undo of the filter is the other way to reload the list, and it lands on the list as the
// filter's own form does, not at the top of the screen.
ck( 'and that link lands on the list card as the filter does, by the same anchor', array( (string) parse_url( $back_href, PHP_URL_FRAGMENT ), has( $south, 'id="' . (string) parse_url( $back_href, PHP_URL_FRAGMENT ) . '"' ) ), array( 'wpcpm-accounts-list', true ) );

// One name for the anchor, held where the table is and read by the form and the link, so the card's
// id and the two ways to reach it cannot drift apart.
$anchor_trait = (string) file_get_contents( __DIR__ . '/../includes/modules/trait-wpcpm-accounts-screen.php' );
$anchor_table = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-students-table.php' );

ck( 'the anchor is one constant of the accounts table, read by the form and by the undo link, and written out in neither',
	array(
		defined( 'WPCPM_Accounts_Table::LIST_ANCHOR' ) ? WPCPM_Accounts_Table::LIST_ANCHOR : 'undefined',
		false !== strpos( $anchor_trait, 'WPCPM_Accounts_Table::LIST_ANCHOR' ),
		false !== strpos( $anchor_table, 'self::LIST_ANCHOR' ),
		false === strpos( $anchor_trait, "'wpcpm-accounts-list'" ),
		false === strpos( $anchor_table, 'wpcpm-accounts-list' ),
	),
	array( 'wpcpm-accounts-list', true, true, true, true ) );

$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = 1;
$paged = html_of( draw( screen( array( 'wpcpm_institution' => 'Academia Sur' ) ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

preg_match( "/<a class='next-page button' href='([^']*)'/", $paged, $next );
preg_match( "/<th scope=\"col\" id='login'[^>]*><a href=\"([^\"]*)\"/", $paged, $sort );

ck( 'a page of one takes the school\'s two students to two pages, and the next page keeps the school',
	array( row_ids( $paged ), isset( $next[1] ) ? link_of( $next[1] )[1] : array() ),
	array(
		array( 12 ),
		array(
			'page'              => 'wpcpm-students',
			'tab'               => 'accounts',
			'wpcpm_institution' => 'Academia Sur',
			'paged'             => '2',
		),
	) );
ck( 'so does a column\'s sort link', isset( $sort[1] ) ? arg( link_of( $sort[1] )[1], 'wpcpm_institution' ) : 'no sort link', 'Academia Sur' );
ck( 'and each view\'s link',
	array( arg( link_of( view_link( $paged, 'all' ) )[1], 'wpcpm_institution' ), arg( link_of( view_link( $paged, 'never-invited' ) )[1], 'wpcpm_institution' ) ),
	array( 'Academia Sur', 'Academia Sur' ) );

$amp = html_of( draw( screen( array( 'wpcpm_institution' => 'Instituto Este & Oeste' ) ) ) );

ck( 'a school with an ampersand is found, and its whole name survives in the view links, encoded',
	array( row_ids( $amp ), arg( link_of( view_link( $amp, 'invited' ) )[1], 'wpcpm_institution' ) ),
	array( array( 13 ), 'Instituto Este & Oeste' ) );

echo "\n=== The search: the name, the username, the email and the institution ===\n";

six_students();
$norte = draw( screen( array( 's' => 'norte' ) ) );

ck( '"norte" finds Ada and Dana by their schools, though no name, username or email of theirs holds it',
	got(
		$norte,
		function ( $out ) {
			return row_ids( html_of( $out ) );
		}
	),
	array( 11, 14 ) );
ck( 'WordPress was asked for every Student account the search finds over the username, the email and the name, as IDs',
	array( arg( first_query( 'search' ), 'search' ), arg( first_query( 'search' ), 'search_columns' ), arg( first_query( 'search' ), 'number' ), arg( first_query( 'search' ), 'role' ) ),
	array( '*norte*', array( 'user_login', 'user_email', 'display_name' ), -1, WPCPM_Roles::ROLE_STUDENT ) );
ck( 'and the page\'s query carries no search of its own, only the IDs the two searches found',
	array( arg( first_query( 'page' ), 'search' ), arg( first_query( 'page' ), 'include' ) ),
	array( 'absent', array( 11, 14 ) ) );

$found = array();
foreach ( array( 'NORTE', 'bdiaz', 'cahn@example', 'Stone', 'sur' ) as $term ) {
	$found[ $term ] = row_ids( html_of( draw( screen( array( 's' => $term ) ) ) ) );
}

ck( 'whatever the case, and by username, email, name or school',
	$found,
	array(
		'NORTE'        => array( 11, 14 ),
		'bdiaz'        => array( 12 ),
		'cahn@example' => array( 13 ),
		'Stone'        => array( 15 ),
		'sur'          => array( 12, 15 ),
	) );
ck( 'a search inside a school keeps to the school', row_ids( html_of( draw( screen( array( 's' => 'eve', 'wpcpm_institution' => 'Academia Sur' ) ) ) ) ), array( 15 ) );

$GLOBALS['ids_as_rows'] = true;
$by_rows                = row_ids( html_of( draw( screen( array( 's' => 'bdiaz' ) ) ) ) );
$GLOBALS['ids_as_rows'] = false;

ck( 'the same where WordPress answers a request for IDs with rows, as the program\'s own site does', $by_rows, array( 12 ) );
ck( 'the views count the whole list, not the search, as WordPress\'s own views do',
	view_counts( html_of( $norte ) ),
	array(
		'all'           => '6',
		'invited'       => '2',
		'never-invited' => '4',
	) );

$nobody = html_of( draw( screen( array( 's' => 'zzz' ) ) ) );

ck( 'a search nobody matches says so, rather than that no accounts exist', array( row_ids( $nobody ), has( $nobody, 'No student accounts found.' ) ), array( array(), true ) );

echo "\n=== The Institution sort: by school, then name, across the pages ===\n";

six_students();
$asc = draw( screen( array( 'orderby' => 'institution', 'order' => 'asc' ) ) );

ck( 'A to Z: Academia Sur\'s Bruno and Eve, Escuela Norte\'s Ada, Escuela Norte Dos\'s Dana, then Cleo\'s Instituto, then Fred at no school',
	got(
		$asc,
		function ( $out ) {
			return row_ids( html_of( $out ) );
		}
	),
	array( 12, 15, 11, 14, 13, 16 ) );
ck( 'Z to A: Fred first, the schools the other way, each school\'s students still by name',
	row_ids( html_of( draw( screen( array( 'orderby' => 'institution', 'order' => 'desc' ) ) ) ) ),
	array( 16, 13, 14, 11, 12, 15 ) );

$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = 2;
$second = html_of( draw( screen( array( 'orderby' => 'institution', 'order' => 'asc', 'paged' => '2' ) ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

ck( 'two a page, the second page is the third and the fourth in that order: Ada, then Dana', row_ids( $second ), array( 11, 14 ) );
ck( 'fetched as that page\'s two IDs in that order, and counted from every account the sort ordered',
	array( arg( first_query( 'page' ), 'include' ), arg( first_query( 'page' ), 'orderby' ), displaying( $second ), arg( first_query( 'ordered' ), 'number' ) ),
	array( array( 11, 14 ), 'include', '6 items', -1 ) );
$sorted_school = html_of( draw( screen( array( 'orderby' => 'institution', 'wpcpm_institution' => 'Academia Sur' ) ) ) );
$sorted_search = html_of( draw( screen( array( 'orderby' => 'institution', 'order' => 'desc', 's' => 'norte' ) ) ) );

ck( 'sorted by institution, a list narrowed to a school keeps to the school, and a searched list to what the search found',
	array( row_ids( $sorted_school ), displaying( $sorted_school ), row_ids( $sorted_search ), displaying( $sorted_search ) ),
	array( array( 12, 15 ), '2 items', array( 14, 11 ), '2 items' ) );
ck( 'on the Never invited view, the view\'s accounts alone, in the same order',
	row_ids( html_of( draw( screen( array( 'orderby' => 'institution', 'wpcpm_view' => 'never-invited' ) ) ) ) ),
	array( 12, 14, 13, 16 ) );

preg_match( "/<th scope=\"col\" id='institution' class='([^']*)'/", html_of( $asc ), $marked );

ck( 'the Institution header says the list is sorted by it, A to Z', isset( $marked[1] ) ? $marked[1] : '', 'manage-column column-institution sorted asc' );

// The program's schools and students are international: a name compared byte by byte puts every one
// that opens on an accented letter after Z, where a reader of the list looks for it among its plain
// letter. The schools are read so too, and the students within one school.
reset_site();
person( 71, 'Zofia Nowak', 'znowak', array( WPCPM_Roles::ROLE_STUDENT ), 'Zespół Szkół' );
person( 72, 'Łukasz Wójcik', 'lwojcik', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte' );
person( 73, 'Álvaro Núñez', 'anunez', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte' );
person( 74, 'Zoe Park', 'zpark', array( WPCPM_Roles::ROLE_STUDENT ), 'École Sud' );
person( 76, 'Beto Díaz', 'bdiaz', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte' );
person( 77, 'Mateo Cruz', 'mcruz', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte' );

ck( 'schools and the names within one school run A to Z without regard to accents: École Sud among the E\'s, Escuela Norte\'s Álvaro, Beto, Łukasz among the L\'s, Mateo, then Zespół Szkół',
	row_ids( html_of( draw( screen( array( 'orderby' => 'institution', 'order' => 'asc' ) ) ) ) ),
	array( 74, 73, 76, 72, 77, 71 ) );

six_students();

$plain   = html_of( draw( screen() ) );
$sort_of = function ( $column ) use ( $plain ) {
	return preg_match( "/<th scope=\"col\" id='" . $column . "'[^>]*><a href=\"([^\"]*)\"/", $plain, $found ) ? arg( link_of( $found[1] )[1], 'orderby' ) : 'not sortable';
};

ck( 'Student sorts by name, Username by username and Institution by school; Program and Mentor do not sort',
	array_map( $sort_of, array( 'name', 'login', 'institution', 'program', 'mentor' ) ),
	array( 'display_name', 'login', 'institution', 'not sortable', 'not sortable' ) );
ck( 'Username sorts Z to A as asked', row_ids( html_of( draw( screen( array( 'orderby' => 'login', 'order' => 'desc' ) ) ) ) ), array( 16, 15, 14, 13, 12, 11 ) );

// The base asks for the name sort in WP_User_Query's array form, so a table that sorts some of its
// columns itself reads what the list is sorted by from the array's first key.
$sorted_as_array = run(
	function () {
		$query = new ReflectionMethod( 'WPCPM_Students_Table', 'query' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$query->setAccessible( true );
		}

		request( screen() );
		$found = $query->invoke(
			new WPCPM_Students_Table(),
			array(
				'role'        => WPCPM_Roles::ROLE_STUDENT,
				'number'      => 20,
				'offset'      => 0,
				'orderby'     => array( 'institution' => 'ASC', 'ID' => 'ASC' ),
				'order'       => 'ASC',
				'count_total' => true,
			)
		);

		return array_map(
			function ( $user ) {
				return $user->ID;
			},
			$found['items']
		);
	}
);

ck( 'asked for the Institution sort in the array form, the table still sorts by school, reading the array\'s first key',
	got(
		$sorted_as_array,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 12, 15, 11, 14, 13, 16 ) );

echo "\n=== Two students of one name, where one page ends and the next begins ===\n";

// A row a page, so the boundary between two pages falls between the two Sam Riveras. The database
// may return rows that tie on the whole order in any order, and in another for another page (the
// query stand-in above takes it at its word): ordered by the name alone, one of them could show on
// both pages and the other on neither.
reset_site();
person( 51, 'Sam Rivera', 'srivera', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte' );
person( 52, 'Sam Rivera', 'sriveraortiz', array( WPCPM_Roles::ROLE_STUDENT ), 'Academia Sur' );
person( 53, 'Tia Rivera', 'trivera', array( WPCPM_Roles::ROLE_STUDENT ), 'Escuela Norte' );
$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = 1;

$pages = array();
foreach ( array( 'asc' => array( '1', '2' ), 'desc' => array( '2', '3' ) ) as $way => $numbers ) {
	foreach ( $numbers as $number ) {
		$pages[ $way ][] = row_ids( html_of( draw( screen( array( 'order' => $way, 'paged' => $number ) ) ) ) );
	}
}

unset( $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

ck( 'each Sam Rivera is on a page of their own, A to Z and Z to A, the one with the lower ID first A to Z',
	$pages,
	array(
		'asc'  => array( array( 51 ), array( 52 ) ),
		'desc' => array( array( 52 ), array( 51 ) ),
	) );
ck( 'because the page\'s query asks for the ID after the name', arg( first_query( 'page' ), 'orderby' ), array( 'display_name' => 'DESC', 'ID' => 'DESC' ) );

echo "\n=== Send invite and Resend invite on the ticked accounts, handled before the screen draws ===\n";

three_students();
person( 20, 'Mia Mentor', 'mmentor', array( WPCPM_Roles::ROLE_MENTOR ), 'Escuela Norte' );

// What the list's form sends besides the choice: core's nonce and the address it came from; and
// what a press of its Apply button adds, the button's name, which core gives it from WordPress 6.7
// on, as the version here does. A search or a filter is sent without it.
$sent_with = array(
	'_wpnonce'         => wp_create_nonce( 'bulk-students' ),
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-students&tab=accounts',
	'action2'          => '-1',
);
$applied   = array_merge( $sent_with, array( 'bulk_action' => 'Apply' ) );
$accounts  = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );

manager( 2 );
$sent       = press( screen( array_merge( $applied, array( 'action' => 'invite', 'users' => array( '11', '12', '13' ) ) ) ) );
$sent_reads = $GLOBALS['reads'];

ck( 'Send invite on Ada, Bruno and Cleo queues the two never invited, and skips Ada, invited before',
	got(
		$sent,
		function () {
			return WPCPM_Mail::queue();
		}
	),
	array( 12, 13 ) );
ck( 'through the queue the invitations card uses, which records the run the card reports, and sends nothing yet',
	array( WPCPM_Mail::run() ? WPCPM_Mail::run()['total'] : 0, $GLOBALS['mails'] ),
	array( 2, array() ) );
ck( 'then back to the Accounts tab', link_of( $sent['redirect'] ), $accounts );
ck( 'the list\'s nonce was the one asked about', $GLOBALS['nonce_checks'], array( 'bulk-students' ) );
ck( 'the ticked accounts are read in one go, with their meta, before any of them is looked at',
	array( isset( $sent_reads[0] ) ? $sent_reads[0] : 'nothing read', count( preg_grep( '/^cache /', $sent_reads ) ), array_search( 'user 11', $sent_reads, true ) > 0 ),
	array( 'cache 11,12,13', 1, true ) );
ck( 'and the screen says how many were queued',
	html_of( notice_now() ),
	'<div class="notice notice-success is-dismissible"><p>2 invitations queued. They go out in the background - the progress is shown below.</p></div>' );

manager( 3 );
$again = press( screen( array_merge( $applied, array( 'action' => 'reinvite', 'users' => array( '11', '12' ) ) ) ) );

ck( 'Resend invite on Ada and Bruno queues Ada, invited before, and skips Bruno, never invited',
	got(
		$again,
		function () {
			return WPCPM_Mail::queue();
		}
	),
	array( 11 ) );
ck( 'the card\'s run is its own button\'s alone, and nothing is sent yet', array( WPCPM_Mail::run(), $GLOBALS['mails'] ), array( array(), array() ) );
ck( 'the screen says so, and that the new link replaces the old one',
	html_of( notice_now() ),
	'<div class="notice notice-success is-dismissible"><p>1 invitation queued. It goes out with the next batch and replaces the link in any earlier invitation.</p></div>' );

// Cleo was sent an invitation five minutes ago. The queue's drain passes over anybody sent one in
// the last fifteen minutes, whose link another would cancel, so a re-invitation queued for her now
// may never go, and a notice counting her as queued would say it had.
$GLOBALS['umeta'][13]['wpcpm_student_invited'] = time() - 5 * MINUTE_IN_SECONDS;
$too_soon                                      = array();

foreach ( array(
	'Ada and Cleo'               => array( array( '11', '13' ), array() ),
	'Cleo alone'                 => array( array( '13' ), array() ),
	'Cleo, and Ada in the queue' => array( array( '11', '13' ), array( 11 ) ),
) as $case => $choice ) {
	manager( 70 + count( $too_soon ) );

	if ( $choice[1] ) {
		update_option( WPCPM_Mail::QUEUE_OPTION, $choice[1] );
	}

	press( screen( array_merge( $applied, array( 'action' => 'reinvite', 'users' => $choice[0] ) ) ) );
	$too_soon[ $case ] = array( WPCPM_Mail::queue(), html_of( notice_now() ) );
}

unset( $GLOBALS['umeta'][13]['wpcpm_student_invited'] );

ck( 'Resend invite leaves out anybody sent an invitation in the last fifteen minutes, queues the rest, and says how many it left out and why',
	$too_soon,
	array(
		'Ada and Cleo'               => array( array( 11 ), '<div class="notice notice-success is-dismissible"><p>1 invitation queued. It goes out with the next batch and replaces the link in any earlier invitation. 1 selected account was left out: it was sent an invitation less than 15 minutes ago, and another one now would cancel the link in it.</p></div>' ),
		'Cleo alone'                 => array( array(), '<div class="notice notice-info is-dismissible"><p>Nothing to send: the selected accounts were each sent an invitation less than 15 minutes ago, and another one now would cancel the link in it. Ask them to use their newest email, or try again later.</p></div>' ),
		'Cleo, and Ada in the queue' => array( array( 11 ), '<div class="notice notice-info is-dismissible"><p>Nothing to send: the selected accounts are already waiting in the queue. 1 selected account was left out: it was sent an invitation less than 15 minutes ago, and another one now would cancel the link in it.</p></div>' ),
	) );

$nothing = array();
foreach ( array(
	'invite on Ada'           => array( 'invite', array( '11' ), array() ),
	'resend to Bruno'         => array( 'reinvite', array( '12' ), array() ),
	'nothing ticked'          => array( 'invite', null, array() ),
	'resend to Ada, in queue' => array( 'reinvite', array( '11' ), array( 11 ) ),
	'invite on Bruno, queued' => array( 'invite', array( '12' ), array( 12 ) ),
) as $case => $choice ) {
	manager( 50 + count( $nothing ) );

	if ( $choice[2] ) {
		update_option( WPCPM_Mail::QUEUE_OPTION, $choice[2] );
	}

	$fields = array_merge( $applied, array( 'action' => $choice[0] ) );

	if ( null !== $choice[1] ) {
		$fields['users'] = $choice[1];
	}

	$out              = press( screen( $fields ) );
	$nothing[ $case ] = array( link_of( $out['redirect'] ) === $accounts, WPCPM_Mail::queue(), html_of( notice_now() ) );
}

ck( 'with nothing to send, the press queues nobody, returns to the list and says why',
	$nothing,
	array(
		'invite on Ada'           => array( true, array(), '<div class="notice notice-info is-dismissible"><p>Nothing to send: none of the selected accounts needs a first invitation.</p></div>' ),
		'resend to Bruno'         => array( true, array(), '<div class="notice notice-info is-dismissible"><p>Nothing to send: none of the selected accounts has been invited yet. Use Send invite for a first invitation.</p></div>' ),
		'nothing ticked'          => array( true, array(), '<div class="notice notice-info is-dismissible"><p>Nothing to send: no accounts were selected.</p></div>' ),
		'resend to Ada, in queue' => array( true, array( 11 ), '<div class="notice notice-info is-dismissible"><p>Nothing to send: the selected accounts are already waiting in the queue.</p></div>' ),
		'invite on Bruno, queued' => array( true, array( 12 ), '<div class="notice notice-info is-dismissible"><p>Nothing to send: the selected accounts are already waiting in the queue.</p></div>' ),
	) );

manager( 60 );
press( screen( array_merge( $applied, array( 'action' => 'invite', 'users' => array( '20', '12', '999', '12' ) ) ) ) );

ck( 'every ticked ID is checked against the Student role: the mentor and an ID nobody holds are skipped, a repeat counts once',
	array( WPCPM_Mail::queue(), html_of( notice_now() ) ),
	array( array( 12 ), '<div class="notice notice-success is-dismissible"><p>1 invitation queued. It goes out in the background - the progress is shown below.</p></div>' ) );

manager( 21 );
$kept = press(
	screen(
		array_merge(
			$applied,
			array(
				'action'            => 'invite',
				'users'             => array( '13' ),
				'wpcpm_view'        => 'never-invited',
				's'                 => 'cleo',
				'wpcpm_institution' => 'Instituto Este & Oeste',
				'orderby'           => 'institution',
				'order'             => 'desc',
				'paged'             => '2',
			)
		)
	)
);

ck( 'the press returns to the list as it was, its view, search, school, sort and page, and none of the form\'s own fields',
	link_of( $kept['redirect'] ),
	array(
		'https://example.test/wp-admin/admin.php',
		array(
			'page'              => 'wpcpm-students',
			'tab'               => 'accounts',
			'wpcpm_view'        => 'never-invited',
			's'                 => 'cleo',
			'wpcpm_institution' => 'Instituto Este & Oeste',
			'orderby'           => 'institution',
			'order'             => 'desc',
			'paged'             => '2',
		),
	) );

manager( 22 );
$forged = press( screen( array_merge( $applied, array( '_wpnonce' => 'forged', 'action' => 'invite', 'users' => array( '12', '13' ) ) ) ) );

ck( 'a press with the wrong nonce dies with WordPress\'s own sentence, before any account is read or queued',
	array( $forged['died'], $GLOBALS['reads'], WPCPM_Mail::queue() ),
	array( 'The link you followed has expired.', array(), array() ) );

manager( 23 );
$GLOBALS['caps'] = false;
$refused         = press( screen( array_merge( $applied, array( 'action' => 'invite', 'users' => array( '12' ) ) ) ) );
$GLOBALS['caps'] = true;

ck( 'somebody without the program\'s capability is refused before the nonce is asked about or anything is read',
	array( $refused['died'], $GLOBALS['nonce_checks'], $GLOBALS['reads'], WPCPM_Mail::queue() ),
	array( 'You do not have permission to manage the program.', array(), array(), array() ) );

manager( 24 );
$other = press( screen( array( 'action' => 'delete', 'users' => array( '12' ), 'bulk_action' => 'Apply' ) ) );

ck( 'a bulk action the list does not have does nothing: the screen draws, no nonce is asked about, nobody is queued',
	array( $other['value'], $GLOBALS['nonce_checks'], WPCPM_Mail::queue() ),
	array( 'drawn', array(), array() ) );

manager( 25 );
$searched = press( screen( array_merge( $sent_with, array( 'action' => '-1', 's' => 'ada' ) ) ) );

ck( 'a search sent from the list\'s form comes back to the same list without the form\'s nonce, as core\'s own lists do',
	link_of( $searched['redirect'] ),
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 's' => 'ada' ) ) );
ck( 'and nothing is asked about or queued on the way', array( $GLOBALS['nonce_checks'], WPCPM_Mail::queue() ), array( array(), array() ) );

$filtered = press( screen( array_merge( $sent_with, array( 'action' => 'invite', 'users' => array( '12' ), 'wpcpm_institution' => 'Academia Sur', 'filter_action' => 'Filter' ) ) ) );

ck( 'Filter pressed while a bulk action is chosen filters, and queues nobody',
	array( link_of( $filtered['redirect'] )[1], WPCPM_Mail::queue() ),
	array( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_institution' => 'Academia Sur' ), array() ) );

// A search pressed with Send invite chosen and Bruno ticked is a search: the form is sent without
// Apply, so nothing is asked about or queued, and the press comes back to the list, searched.
manager( 26 );
$searched_invite = press( screen( array_merge( $sent_with, array( 'action' => 'invite', 'users' => array( '12' ), 's' => 'bruno' ) ) ) );

ck( 'a search pressed with Send invite chosen and Bruno ticked queues nobody and asks about no nonce: the press comes back to the list, searched',
	array( WPCPM_Mail::queue(), $GLOBALS['nonce_checks'], link_of( $searched_invite['redirect'] ) ),
	array( array(), array(), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 's' => 'bruno' ) ) ) );

echo "\n=== A row's invitation: a nonce link to the Students module's own handler ===\n";

three_students();
manager( 30 );
$rows       = html_of( draw( screen() ) );
$bruno_link = link_of( actions_in( row_for( $rows, 12 ) )['invite'] ?? '' );

ck( 'Bruno\'s Send invite goes to the module\'s invite handler with his account, the tab to come back to and the nonce that handler checks, keyed to his account',
	array( $bruno_link[0], arg( $bruno_link[1], 'action' ), arg( $bruno_link[1], 'user' ), arg( $bruno_link[1], 'wpcpm_tab' ), arg( $bruno_link[1], '_wpnonce' ) ),
	array( 'https://example.test/wp-admin/admin-post.php', WPCPM_Students::ACTION_INVITE, '12', 'accounts', wp_create_nonce( WPCPM_Students::ACTION_INVITE . '_12' ) ) );

request( $bruno_link[1] );
$followed = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);

ck( 'followed, it sends Bruno his invitation now, as the row\'s form did, and stamps his account',
	array( $GLOBALS['mails'], isset( $GLOBALS['umeta'][12]['wpcpm_student_invited'] ) && is_int( $GLOBALS['umeta'][12]['wpcpm_student_invited'] ) ),
	array( array( 12 ), true ) );
ck( 'then returns to the Accounts tab, saying the email went',
	array( link_of( $followed['redirect'] ), has( html_of( notice_now() ), 'Invitation email sent.' ) ),
	array( $accounts, true ) );

manager( 31 );
$ada_link = link_of( actions_in( row_for( $rows, 11 ) )['reinvite'] ?? '' );
request( $ada_link[1] );
run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);

ck( 'Ada\'s Resend invite sends her a fresh one', $GLOBALS['mails'], array( 11 ) );

manager( 32 );
request( array( 'action' => WPCPM_Students::ACTION_INVITE ), array( 'user_id' => '13', '_wpnonce' => wp_create_nonce( WPCPM_Students::ACTION_INVITE . '_13' ) ) );
$posted = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);

// The account is read from the row's link alone: no form in the plugin posts one, so a posted
// account names nobody, the nonce asked is keyed to no account, and the request dies there.
ck( 'an account posted by a form is not read: the request names no account, dies at the nonce keyed to none, and sends nothing', array( $posted['died'], $GLOBALS['nonce_checks'], $GLOBALS['mails'] ), array( 'The link you followed has expired.', array( WPCPM_Students::ACTION_INVITE . '_0' ), array() ) );

manager( 33 );
request( array_merge( $bruno_link[1], array( '_wpnonce' => 'forged' ) ) );
$forged = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);

ck( 'with the wrong nonce the link dies before any account is read, and sends nothing',
	array( $forged['died'], $GLOBALS['reads'], $GLOBALS['mails'] ),
	array( 'The link you followed has expired.', array(), array() ) );

manager( 38 );
$GLOBALS['caps'] = false;
request( $bruno_link[1] );
$no_right        = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);
$GLOBALS['caps'] = true;

ck( 'followed by somebody without the program\'s capability, the link dies before the nonce keyed to the account is asked about, reads no account and sends nothing',
	array( $no_right['died'], $GLOBALS['nonce_checks'], $GLOBALS['reads'], $GLOBALS['mails'] ),
	array( 'You do not have permission to manage the program.', array(), array(), array() ) );

// The nonce is keyed to the account, so a link taken from one row is no use on another: the
// handler's own action alone, which every row's link was signed with before, is refused, and so is
// Bruno's link pointed at Cleo's account.
manager( 35 );
request( array_merge( $bruno_link[1], array( '_wpnonce' => wp_create_nonce( WPCPM_Students::ACTION_INVITE ) ) ) );
$unkeyed = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);
$unkeyed_asked = $GLOBALS['nonce_checks'];
manager( 36 );
request( array_merge( $bruno_link[1], array( 'user' => '13' ) ) );
$borrowed = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);

ck( 'the link verifies only under the nonce keyed to its account: the handler\'s unkeyed nonce is refused, and so is Bruno\'s nonce on Cleo\'s account, each before anything is sent',
	array( $unkeyed['died'], $unkeyed_asked, $borrowed['died'], $GLOBALS['nonce_checks'], $GLOBALS['mails'] ),
	array( 'The link you followed has expired.', array( WPCPM_Students::ACTION_INVITE . '_12' ), 'The link you followed has expired.', array( WPCPM_Students::ACTION_INVITE . '_13' ), array() ) );

// One invitation is sent by the module's `invite_one()`, its sync's own send unless the module
// answers it itself, as a module whose sync class sends none does. Bruno is never invited again
// here, so a sync asked all the same would send him one.
three_students();
manager( 37 );
$own_invite = new class() extends WPCPM_Students {
	/**
	 * The accounts this module was asked to invite.
	 *
	 * @var int[]
	 */
	public static $asked = array();

	protected function invite_one( $user_id ) {
		self::$asked[] = $user_id;

		return true;
	}
};
request( $bruno_link[1] );
$answered = run(
	function () use ( $own_invite ) {
		$own_invite->handle_invite();
	}
);

ck( 'a module that answers invite_one() itself is asked in its sync\'s place, once both checks have passed: the account the link names is handed to it, the sync sends nothing, and the list says what it answered',
	array( $own_invite::$asked, $GLOBALS['nonce_checks'], $GLOBALS['mails'], link_of( $answered['redirect'] ), has( html_of( notice_now() ), 'Invitation email sent.' ) ),
	array( array( 12 ), array( WPCPM_Students::ACTION_INVITE . '_12' ), array(), $accounts, true ) );

// A link to an account nobody holds, as a row left open while the account went: the send fails,
// and the Accounts tab, which prints no sync error, says what failed without pointing below.
manager( 34 );
request( array_merge( $bruno_link[1], array( 'user' => '999', '_wpnonce' => wp_create_nonce( WPCPM_Students::ACTION_INVITE . '_999' ) ) ) );
$failed = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);

ck( 'a row\'s send that fails comes back to the Accounts tab, which says the invitation could not be sent, and sends nothing',
	array( link_of( $failed['redirect'] ), html_of( notice_now() ), $GLOBALS['mails'] ),
	array( $accounts, '<div class="notice notice-error is-dismissible"><p>The invitation could not be sent.</p></div>', array() ) );

echo "\n=== A row's invitation comes back to the list's page ===\n";

// Three never invited at a school whose name holds an ampersand, one invited there, and one never
// invited elsewhere; a page a row, so the view, the search, the school and the sort leave three
// pages, and the second holds Hal.
reset_site();
person( 41, 'Gia Norte', 'gnorte', array( WPCPM_Roles::ROLE_STUDENT ), 'Instituto Este & Oeste' );
person( 42, 'Hal Norte', 'hnorte', array( WPCPM_Roles::ROLE_STUDENT ), 'Instituto Este & Oeste' );
person( 43, 'Ivo Norte', 'inorte', array( WPCPM_Roles::ROLE_STUDENT ), 'Instituto Este & Oeste' );
person( 44, 'Jo Norte', 'jnorte', array( WPCPM_Roles::ROLE_STUDENT ), 'Instituto Este & Oeste', 'Developer Track', 'Mentor Example', 1700000000 );
person( 45, 'Kai Norte', 'knorte', array( WPCPM_Roles::ROLE_STUDENT ), 'Academia Sur' );
manager( 260 );
$GLOBALS['umeta'][260]['wpcpm_students_per_page'] = 1;

$place   = array(
	'wpcpm_view'        => 'never-invited',
	's'                 => 'norte',
	'wpcpm_institution' => 'Instituto Este & Oeste',
	'orderby'           => 'display_name',
	'order'             => 'desc',
	'paged'             => '2',
);
$on_page = html_of( draw( screen( $place ) ) );
$hal     = link_of( actions_in( row_for( $on_page, 42 ) )['invite'] ?? '' );
$carried = array();

foreach ( $place as $key => $value ) {
	$carried[ $key ] = arg( $hal[1], $key );
}

ck( 'the second page of the Never invited view, searched, at one school and sorted Z to A, is Hal\'s row', row_ids( $on_page ), array( 42 ) );
ck( 'his Send invite carries where the list stands, the school\'s name whole, beside the handler\'s own action, account, tab and nonce',
	array( $carried, $hal[0], arg( $hal[1], 'action' ), arg( $hal[1], 'user' ), arg( $hal[1], 'wpcpm_tab' ), arg( $hal[1], '_wpnonce' ) ),
	array( $place, 'https://example.test/wp-admin/admin-post.php', WPCPM_Students::ACTION_INVITE, '42', 'accounts', wp_create_nonce( WPCPM_Students::ACTION_INVITE . '_42' ) ) );

request( $hal[1] );
$back    = run(
	function () {
		( new WPCPM_Students() )->handle_invite();
	}
);
$landing = link_of( $back['redirect'] );
$landed  = html_of( page_for( $landing[1] ) );

ck( 'followed, it sends Hal his invitation and comes back to that page of that list, as a press on the ticked accounts does',
	array( $GLOBALS['mails'], $landing ),
	array( array( 42 ), array( 'https://example.test/wp-admin/admin.php', array_merge( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ), $place ) ) ) );
ck( 'where the Accounts tab says the email went, above the same list, two now that Hal has left the view, on its second page, Gia\'s',
	array( shown_tab( $landed ), notices_in( $landed ), displaying( $landed ), row_ids( $landed ) ),
	array( 'Accounts', '<div class="notice notice-success is-dismissible"><p>Invitation email sent. It replaces the link in any earlier invitation, so ask them to use this newest email.</p></div>', '2 items', array( 41 ) ) );

unset( $GLOBALS['umeta'][260]['wpcpm_students_per_page'] );

echo "\n=== The screen's wiring: the load hook, the rows-per-page save, the names ===\n";

reset_site();
$GLOBALS['hooks'] = array();
$module           = new WPCPM_Students();
run(
	function () use ( $module ) {
		$module->boot();
	}
);

$on_menu = array();
foreach ( isset( $GLOBALS['hooks']['admin_menu'] ) ? $GLOBALS['hooks']['admin_menu'] : array() as $added ) {
	$on_menu[] = array( is_array( $added[0] ) ? ( is_object( $added[0][0] ) ? get_class( $added[0][0] ) : $added[0][0] ) . '::' . $added[0][1] : 'another callback', $added[1] );
}

ck( 'at boot, the module hooks its screen to the menu, after the menu is built', $on_menu, array( array( 'WPCPM_Students::hook_screen', 11 ) ) );
ck( 'which WPCPM_Admin builds at the default priority, 10',
	has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php' ), "add_action( 'admin_menu', array( \$this, 'register_menu' ) );" ),
	true );

// What add_menu_page() leaves for the pages under the menu: the name their hooks are filed under.
$GLOBALS['admin_page_hooks'] = array( WPCPM_Admin::MENU_SLUG => 'wpcredits-program' );
foreach ( isset( $GLOBALS['hooks']['admin_menu'] ) ? $GLOBALS['hooks']['admin_menu'] : array() as $added ) {
	call_user_func( $added[0] );
}
$on_load = isset( $GLOBALS['hooks']['load-wpcredits-program_page_wpcpm-students'] ) ? $GLOBALS['hooks']['load-wpcredits-program_page_wpcpm-students'] : array();

ck( 'once the menu has added the page, the page\'s own load hook runs the screen\'s load',
	array( count( $on_load ), isset( $on_load[0][0][1] ) ? $on_load[0][0][1] : '', isset( $on_load[0][0][0] ) && $on_load[0][0][0] === $module ),
	array( 1, 'load_screen', true ) );

// Core files the menu under its translated title, so on a site in Spanish the page's hooks carry
// the Spanish name, and a hook named in English would never fire there.
$menu_callbacks              = isset( $GLOBALS['hooks']['admin_menu'] ) ? $GLOBALS['hooks']['admin_menu'] : array();
$GLOBALS['hooks']            = array();
$GLOBALS['admin_page_hooks'] = array( WPCPM_Admin::MENU_SLUG => 'programa-wpcredits' );
foreach ( $menu_callbacks as $added ) {
	call_user_func( $added[0] );
}

ck( 'on a site in another language, the load hook follows the name the menu was registered under',
	array_keys( $GLOBALS['hooks'] ), array( 'load-programa-wpcredits_page_wpcpm-students' ) );

$GLOBALS['hooks'] = array();
run(
	function () use ( $module ) {
		$module->boot();
	}
);

$saved = array();
foreach ( array( '50', '0', '1000' ) as $value ) {
	$saved[ $value ] = apply_filters( 'set_screen_option_wpcpm_students_per_page', false, 'wpcpm_students_per_page', $value );
}
$save_hook = isset( $GLOBALS['hooks']['set_screen_option_wpcpm_students_per_page'][0] ) ? $GLOBALS['hooks']['set_screen_option_wpcpm_students_per_page'][0] : array( null, 0, 0 );

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

$names = run(
	function () {
		return array( WPCPM_Students::PER_PAGE_OPTION, WPCPM_Students_Table::per_page_option(), WPCPM_Students::TAB_ACCOUNTS, WPCPM_Accounts_Table::TAB, WPCPM_Students_Table::bulk_nonce_action(), WPCPM_Students::FLASH_DETAIL );
	}
);

ck( 'the module\'s names for the option and the tab are the table\'s, the bulk nonce is the Students\', and the count travels on a channel of its own',
	got(
		$names,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'wpcpm_students_per_page', 'wpcpm_students_per_page', 'accounts', 'accounts', 'bulk-students', 'students_admin_detail' ) );

// The role and the stamp the invitations read are the table's own: the module names its table
// (`table_class()`), and the plumbing every accounts screen shares reads both from it, so no audience
// keeps another's in a literal. The stamp's name is left written once in the module, in the
// uninstall's list of keys, where no table is loaded.
reset_site();
person( 12, 'Bruno Diaz', 'bdiaz', array( WPCPM_Roles::ROLE_STUDENT ), 'Academia Sur' );
$stamps = run(
	function () {
		WPCPM_Students_Sync::send_invite( 12 );

		return array( WPCPM_Students_Table::role(), WPCPM_Students_Table::invite_meta(), array_keys( $GLOBALS['umeta'][12] ) );
	}
);
$module_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php' );
$screen_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php' );
$named      = run(
	function () {
		$method = new ReflectionMethod( 'WPCPM_Students', 'table_class' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null );
	}
);

ck( 'the Student accounts table says which role and which stamp it reads, the stamp a row\'s send writes, and the module\'s invitations read them there, holding neither as a literal of their own',
	array(
		got(
			$stamps,
			function ( $out ) {
				return array( $out['value'][0], $out['value'][1], in_array( $out['value'][1], $out['value'][2], true ) );
			}
		),
		substr_count( $module_src . $screen_src, "'wpcpm_student_invited'" ),
		substr_count( $module_src . $screen_src, 'WPCPM_Roles::ROLE_STUDENT' ),
		got(
			$named,
			function ( $out ) {
				return $out['value'];
			}
		),
		substr_count( $screen_src, 'WPCPM_Mail::never_invited( $table_class::role(), $table_class::invite_meta() )' ) > 0,
	),
	array( array( WPCPM_Roles::ROLE_STUDENT, 'wpcpm_student_invited', true ), 1, 1, 'WPCPM_Students_Table', true ) );

$selected = new ReflectionMethod( 'WPCPM_Students', 'invite_selected' );

ck( 'the list\'s press is the module\'s own, reached from the list\'s form once its nonce is checked; what it queues is the base\'s', $selected->isPrivate(), true );

reset_site();

echo "\n=== The notices the card's own button and a stray count leave ===\n";

manager( 40 );
WPCPM_Flash::set( 'students_admin', 'invites-queued' );

ck( 'the invitations card\'s button keeps its sentence',
	html_of( notice_now() ),
	'<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' );

manager( 41 );
WPCPM_Flash::set( 'students_admin', 'invites-queued' );
WPCPM_Flash::set(
	'students_admin_detail',
	array(
		'status' => 'invites-none',
		'resend' => false,
		'why'    => 'none-selected',
	)
);

ck( 'a count carried for another outcome is not printed under this one',
	html_of( notice_now() ),
	'<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' );

three_students();
manager( 42 );
WPCPM_Flash::set(
	'students_admin_detail',
	array(
		'status' => 'invites-queued',
		'resend' => false,
		'queued' => 7,
	)
);
request( array( 'action' => WPCPM_Students::ACTION_BULK ), array( '_wpnonce' => wp_create_nonce( WPCPM_Students::ACTION_BULK ) ) );
$card = run(
	function () {
		( new WPCPM_Students() )->handle_bulk_invite();
	}
);

ck( 'a count a press on the ticked accounts left unprinted is not shown under the card\'s own button, which queues its two',
	array( WPCPM_Mail::queue(), html_of( notice_now() ) ),
	array( array( 12, 13 ), '<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

echo "\n=== The screen in two tabs, Accounts and Sync, in the Settings screen's bar ===\n";

three_students();
manager( 200 );

$home     = 'https://example.test/wp-admin/admin.php?page=wpcpm-students';
$two_tabs = function ( $shown ) use ( $home ) {
	return array(
		'Secondary menu',
		array(
			array( $home . '&tab=accounts', 'Accounts', 'accounts' === $shown, 'accounts' === $shown ),
			array( $home . '&tab=sync', 'Sync', 'sync' === $shown, 'sync' === $shown ),
		),
	);
};
$drawn    = array();
$bars     = array();

foreach ( array(
	'from the menu'                    => array( 'page' => 'wpcpm-students' ),
	'asked for Accounts'               => screen(),
	'asked for Sync'                   => screen( array( 'tab' => 'sync' ) ),
	'asked for a tab it does not have' => screen( array( 'tab' => 'nope' ) ),
) as $case => $query ) {
	$drawn[ $case ] = page_for( $query );
	$bars[ $case ]  = got(
		$drawn[ $case ],
		function ( $out ) {
			return bar_of( html_of( $out ) );
		}
	);
}

ck( 'the bar holds two tabs, Accounts then Sync, each at the screen\'s address with its tab; Accounts is the tab shown from the menu, when asked for and for a tab the screen does not have, and Sync when asked for, marked for the eye and for a screen reader',
	$bars,
	array(
		'from the menu'                    => $two_tabs( 'accounts' ),
		'asked for Accounts'               => $two_tabs( 'accounts' ),
		'asked for Sync'                   => $two_tabs( 'sync' ),
		'asked for a tab it does not have' => $two_tabs( 'accounts' ),
	) );
ck( 'a tab the screen does not have draws the Accounts tab itself, its list among it',
	has( html_of( $drawn['asked for a tab it does not have'] ), '<h2>Student accounts <span class="wpcpm-count">' ),
	true );

$settings_bar = run(
	function () {
		$settings = ( new ReflectionClass( 'WPCPM_Settings_Screen' ) )->newInstanceWithoutConstructor();
		$method   = new ReflectionMethod( 'WPCPM_Settings_Screen', 'render_tab_bar' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();

		try {
			$method->invoke( $settings, 'people' );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}
);
$bar_kinds    = array(
	'<a href="https://example.test/wp-admin/admin.php?page=P&#038;tab=T" class="nav-tab nav-tab-active" aria-current="page"></a>',
	'<a href="https://example.test/wp-admin/admin.php?page=P&#038;tab=T" class="nav-tab"></a>',
);
sort( $bar_kinds );
$bar_markup = array( '<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">', $bar_kinds, '' );
$menu_page  = html_of( $drawn['from the menu'] );

ck( 'the bar is the Settings screen\'s, tag for tag: the same named region holding the links alone, the tab shown and the others marked up as the Settings screen marks its own, at the same shape of address; the screen\'s one bar, right under its title and lede, and no heading holds it',
	array( bar_shape( html_of( $settings_bar ) ), bar_shape( $menu_page ), substr_count( $menu_page, 'nav-tab-wrapper' ), preg_match( '#<h[1-6][^>]*nav-tab-wrapper#', $menu_page ), has( $menu_page, '</p><nav class="nav-tab-wrapper wp-clearfix"' ) ),
	array( $bar_markup, $bar_markup, 1, 0, true ) );

$GLOBALS['l10n'] = array(
	'Accounts'       => 'Cuentas',
	'Sync'           => 'Sincronización',
	'Secondary menu' => 'Menú secundario',
);
$spanish         = page_for( screen( array( 'tab' => 'sync' ) ) );
$GLOBALS['l10n'] = array();

ck( 'the labels are translated where the bar prints them, and so is the name the bar gives itself',
	got(
		$spanish,
		function ( $out ) {
			$bar = bar_of( html_of( $out ) );

			return null === $bar ? null : array( $bar[0], array_column( $bar[1], 1 ) );
		}
	),
	array( 'Menú secundario', array( 'Cuentas', 'Sincronización' ) ) );

$names = run(
	function () {
		return array( WPCPM_Students::TABS, WPCPM_Students::TAB_ACCOUNTS, WPCPM_Students::TAB_SYNC, WPCPM_Students::TAB_FIELD, WPCPM_Accounts_Table::TAB_FIELD );
	}
);

ck( 'the tabs are the module\'s own list, Accounts first, and a form names its tab in the field the list\'s row links carry theirs in',
	got(
		$names,
		function ( $out ) {
			return $out['value'];
		}
	),
	array(
		array(
			'accounts' => 'Accounts',
			'sync'     => 'Sync',
		),
		'accounts',
		'sync',
		'wpcpm_tab',
		'wpcpm_tab',
	) );

$read = array();
foreach ( array(
	'none'     => null,
	'accounts' => 'accounts',
	'sync'     => 'sync',
	'unknown'  => 'nope',
	'empty'    => '',
) as $case => $value ) {
	request( null === $value ? array( 'page' => 'wpcpm-students' ) : array( 'page' => 'wpcpm-students', 'tab' => $value ) );
	$read[ $case ] = got(
		run(
			function () {
				return WPCPM_Students::tab();
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
		'none'     => 'accounts',
		'accounts' => 'accounts',
		'sync'     => 'sync',
		'unknown'  => 'accounts',
		'empty'    => 'accounts',
	) );

echo "\n=== Each tab holds its own cards ===\n";

three_students();
manager( 201 );
WPCPM_Settings::$connected = false;
update_option( WPCPM_Students_Sync::OPT_ERROR, 'Airtable answered 401.' );
update_option( WPCPM_Students_Sync::OPT_REPORT, array( 'stats' => array( 'students_seen' => 3 ), 'notices' => array() ) );
$accounts_page             = html_of( page_for( screen() ) );
$sync_page                 = html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) );
$sync_reads                = $GLOBALS['queries'];
WPCPM_Settings::$connected = true;

$parts_of = function ( $html ) {
	return array(
		'warning'     => has( $html, 'Airtable is not connected yet, so no students can be synced.' ),
		'invitations' => has( $html, '<div class="wpcpm-card wpcpm-invites">' ),
		'list'        => has( $html, '<h2>Student accounts <span class="wpcpm-count">' ),
		'error'       => has( $html, '<strong>Last sync error:</strong> Airtable answered 401.' ),
		'sync'        => has( $html, '<h2>Airtable sync</h2>' ),
		'report'      => has( $html, '<h2>Last sync report</h2>' ),
	);
};

ck( 'the Accounts tab holds the invitations card and the Student accounts list, under the warning that Airtable is not connected, and not the sync card, its last error or its last report',
	$parts_of( $accounts_page ),
	array(
		'warning'     => true,
		'invitations' => true,
		'list'        => true,
		'error'       => false,
		'sync'        => false,
		'report'      => false,
	) );
ck( 'the Sync tab the reverse: the warning, the last sync error, the Airtable sync card and the last sync report, and neither the invitations card nor the list',
	$parts_of( $sync_page ),
	array(
		'warning'     => true,
		'invitations' => false,
		'list'        => false,
		'error'       => true,
		'sync'        => true,
		'report'      => true,
	) );
ck( 'and on the Sync tab no account is read, on its load hook or as it draws', $sync_reads, array() );

// Each tab's parts in the order a manager reads them: what the last press did, then why no more
// accounts arrive while Airtable is not connected, then the tab's own cards.
$in_order = function ( $html, array $parts ) {
	$at = array();

	foreach ( $parts as $name => $needle ) {
		$found       = strpos( (string) $html, $needle );
		$at[ $name ] = false === $found ? -1 : $found;
	}

	asort( $at );

	return array_keys( $at );
};
$ordered  = array();

foreach ( array(
	'accounts' => array( 205, 'invited' ),
	'sync'     => array( 206, 'cancelled' ),
) as $tab => $press ) {
	manager( $press[0] );
	WPCPM_Settings::$connected = false;
	update_option( WPCPM_Students_Sync::OPT_ERROR, 'Airtable answered 401.' );
	update_option( WPCPM_Students_Sync::OPT_REPORT, array( 'stats' => array( 'students_seen' => 3 ), 'notices' => array() ) );
	WPCPM_Flash::set( 'students_admin', $press[1] );
	$ordered[ $tab ]           = html_of( page_for( screen( array( 'tab' => $tab ) ) ) );
	WPCPM_Settings::$connected = true;
}

ck( 'the Accounts tab reads top to bottom: the press\'s notice, the warning, the invitations card, the list',
	$in_order(
		$ordered['accounts'],
		array(
			'notice'      => '<div class="notice notice-success is-dismissible">',
			'warning'     => 'Airtable is not connected yet, so no students can be synced.',
			'invitations' => '<div class="wpcpm-card wpcpm-invites">',
			'list'        => '<h2>Student accounts <span class="wpcpm-count">',
		)
	),
	array( 'notice', 'warning', 'invitations', 'list' ) );
ck( 'and the Sync tab: the press\'s notice, the warning, the last sync error, the Airtable sync card, the last sync report',
	$in_order(
		$ordered['sync'],
		array(
			'notice'  => '<div class="notice notice-info is-dismissible">',
			'warning' => 'Airtable is not connected yet, so no students can be synced.',
			'error'   => '<strong>Last sync error:</strong>',
			'sync'    => '<h2>Airtable sync</h2>',
			'report'  => '<h2>Last sync report</h2>',
		)
	),
	array( 'notice', 'warning', 'error', 'sync', 'report' ) );

manager( 202 );

ck( 'with Airtable connected, neither tab warns',
	array( has( html_of( page_for( screen() ) ), 'Airtable is not connected yet' ), has( html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) ), 'Airtable is not connected yet' ) ),
	array( false, false ) );

echo "\n=== Every form names the tab it is on ===\n";

three_students();
manager( 203 );
$accounts_forms = forms_by_action( html_of( page_for( screen() ) ) );
$sync_forms     = forms_by_action( html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) ) );
update_option(
	WPCPM_Students_Sync::OPT_STATE,
	array(
		'phase'   => 'reports',
		'started' => time(),
		'touched' => time(),
	)
);
$running_forms = forms_by_action( html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) ) );
delete_option( WPCPM_Students_Sync::OPT_STATE );

ck( 'the Sync tab\'s one form, Sync students now, names the Sync tab, and so does Cancel sync, its one form while a run is on',
	array( array_map( 'tab_field_of', $sync_forms ), array_map( 'tab_field_of', $running_forms ) ),
	array( array( WPCPM_Students::ACTION_SYNC => 'sync' ), array( WPCPM_Students::ACTION_CANCEL => 'sync' ) ) );
ck( 'the invitations card\'s button names the Accounts tab, beside the institution the card is narrowed to; the list is a form to the screen itself, whose fields are the Accounts tab\'s address',
	array(
		array_map( 'tab_field_of', $accounts_forms ),
		has( isset( $accounts_forms[ WPCPM_Students::ACTION_BULK ] ) ? $accounts_forms[ WPCPM_Students::ACTION_BULK ] : '', '<input type="hidden" name="wpcpm_institution" value="" />' ),
		has( isset( $accounts_forms['list'] ) ? $accounts_forms['list'] : '', '<input type="hidden" name="tab" value="accounts" />' ),
	),
	array(
		array(
			WPCPM_Students::ACTION_BULK => 'accounts',
			'list'                      => null,
		),
		true,
		true,
	) );

echo "\n=== A press comes back to the tab it was made on ===\n";

$to_sync     = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'sync' ) );
$to_accounts = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$to_screen   = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students' ) );

three_students();
manager( 210 );
$started = post_to( 'handle_sync', WPCPM_Students::ACTION_SYNC, array( 'wpcpm_tab' => 'sync' ) );
$landing = link_of( $started['redirect'] );
$landed  = html_of( page_for( $landing[1] ) );

ck( 'Sync students now, pressed on the Sync tab, starts the run and comes back to the Sync tab',
	array( $landing, WPCPM_Students_Sync::is_running() ),
	array( $to_sync, true ) );
ck( 'the tab drawn there, which says the run started, above the progress it points to',
	array( shown_tab( $landed ), notices_in( $landed ), has( $landed, 'data-wpcpm-progress' ) ),
	array( 'Sync', '<div class="notice notice-success is-dismissible"><p>Sync started. Progress is shown below and updates as it runs.</p></div>', true ) );

manager( 211 );
update_option(
	WPCPM_Students_Sync::OPT_STATE,
	array(
		'phase'   => 'reports',
		'started' => time(),
		'touched' => time(),
	)
);
$cancelled = post_to( 'handle_cancel', WPCPM_Students::ACTION_CANCEL, array( 'wpcpm_tab' => 'sync' ) );
$landing   = link_of( $cancelled['redirect'] );

ck( 'Cancel sync the same: the run stops, the press comes back to the Sync tab, and the tab says so',
	array( $landing, WPCPM_Students_Sync::is_running(), notices_in( html_of( page_for( $landing[1] ) ) ) ),
	array( $to_sync, false, '<div class="notice notice-info is-dismissible"><p>Sync canceled.</p></div>' ) );

manager( 212 );
WPCPM_Settings::$connected = false;
$refused                   = post_to( 'handle_sync', WPCPM_Students::ACTION_SYNC, array( 'wpcpm_tab' => 'sync' ) );
$landing                   = link_of( $refused['redirect'] );
$landed                    = html_of( page_for( $landing[1] ) );
WPCPM_Settings::$connected = true;

$said  = strpos( $landed, 'That action could not be completed. See the error below.' );
$below = strpos( $landed, '<strong>Last sync error:</strong> Add an Airtable Personal Access Token and Base ID before syncing.' );

ck( 'a start that fails comes back to the Sync tab too, whose notice points to the error, printed below it',
	array( $landing, notices_in( $landed ), false !== $said && false !== $below && $said < $below ),
	array( $to_sync, '<div class="notice notice-error is-dismissible"><p>That action could not be completed. See the error below.</p></div>', true ) );

manager( 213 );
$card    = post_to(
	'handle_bulk_invite',
	WPCPM_Students::ACTION_BULK,
	array(
		'wpcpm_tab'         => 'accounts',
		'wpcpm_institution' => '',
	)
);
$landing = link_of( $card['redirect'] );

ck( 'the invitations card\'s button, pressed on the Accounts tab, queues the two never invited and comes back to the Accounts tab, which says so',
	array( $landing, WPCPM_Mail::queue(), notices_in( html_of( page_for( $landing[1] ) ) ) ),
	array( $to_accounts, array( 12, 13 ), '<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

// The list narrowed to Cleo's school, whose name holds an ampersand, where Gus was never invited
// either: the card counts and posts the school, its press queues those two alone, and comes back to
// the same list, the school's name whole.
six_students();
person( 17, 'Gus Este', 'geste', array( WPCPM_Roles::ROLE_STUDENT ), 'Instituto Este & Oeste' );
manager( 222 );
$narrowed = forms_by_action( html_of( page_for( screen( array( 'wpcpm_institution' => 'Instituto Este & Oeste' ) ) ) ) );
$button   = isset( $narrowed[ WPCPM_Students::ACTION_BULK ] ) ? $narrowed[ WPCPM_Students::ACTION_BULK ] : '';

ck( 'on a list narrowed to a school, the invitations card offers that school\'s never invited alone, and posts the school with its press',
	array( has( $button, '>Invite 2 students at Instituto Este &amp; Oeste who have never been invited</button>' ), arg( hidden_fields_of( $button ), 'wpcpm_institution' ) ),
	array( true, 'Instituto Este & Oeste' ) );

// The press is the manager's next request. WPCPM_Flash remembers within one run of PHP what it took
// for a person, and drawing the tab above took this one's, so the press is made by somebody new.
manager( 223 );
$card    = press_form( $button, array( new WPCPM_Students(), 'handle_bulk_invite' ) );
$landing = link_of( $card['redirect'] );
$landed  = html_of( page_for( $landing[1] ) );

ck( 'pressed, it queues those two, not every student never invited, and comes back to the list narrowed to the same school, which says so',
	array( WPCPM_Mail::queue(), $landing, notices_in( $landed ), displaying( $landed ) ),
	array(
		array( 13, 17 ),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_institution' => 'Instituto Este & Oeste' ) ),
		'<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>',
		'2 items',
	) );

$elsewhere = array();
foreach ( array(
	'a tab the screen does not have' => 'nope',
	'a tab and more'                 => 'sync&tab=accounts',
	'an empty tab'                   => '',
	'no tab'                         => null,
) as $case => $tab ) {
	manager( 214 + count( $elsewhere ) );
	$out                = post_to( 'handle_cancel', WPCPM_Students::ACTION_CANCEL, null === $tab ? array() : array( 'wpcpm_tab' => $tab ) );
	$elsewhere[ $case ] = link_of( $out['redirect'] );
}

ck( 'a form that names no tab, or one the screen does not have, comes back to the screen\'s own address, which shows Accounts',
	$elsewhere,
	array_fill_keys( array( 'a tab the screen does not have', 'a tab and more', 'an empty tab', 'no tab' ), $to_screen ) );

manager( 218 );
$untabbed = post_to( 'handle_cancel', 'wpcpm_untabbed_cancel', array( 'wpcpm_tab' => 'sync' ), new WPCPM_Stub_Untabbed_Module() );

ck( 'a screen drawn in one piece, which declares no tabs, comes back to its own address whatever tab a form names',
	link_of( $untabbed['redirect'] ),
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-untabbed' ) ) );

three_students();
manager( 219 );
update_option( WPCPM_Mail::QUEUE_OPTION, array( 12, 13 ) );
update_option(
	WPCPM_Mail::RUN_OPTION,
	array(
		'total'    => 2,
		'started'  => time(),
		'finished' => 0,
	)
);
$sending   = forms_by_action( html_of( page_for( screen() ) ) );
$stop_form = isset( $sending[ WPCPM_Mail::ACTION_STOP ] ) ? $sending[ WPCPM_Mail::ACTION_STOP ] : '';

// The press is the manager's next request, made by somebody new for the reason above, the queue and
// the run it stops left as the page found them.
$GLOBALS['uid'] = 224;
$stopped        = press_form( $stop_form, array( 'WPCPM_Mail', 'handle_stop' ) );
$stopped_page   = html_of( page_for( link_of( $stopped['redirect'] )[1] ) );

ck( 'Stop names the flash channel the Students screen reads its outcomes from, and pressed, it cancels the rest, and the Accounts tab it comes back to says sending stopped',
	array( arg( hidden_fields_of( $stop_form ), 'wpcpm_flash' ), WPCPM_Mail::queue(), WPCPM_Mail::run(), shown_tab( $stopped_page ), notices_in( $stopped_page ) ),
	array( 'students_admin', array(), array(), 'Accounts', '<div class="notice notice-info is-dismissible"><p>Sending stopped. Invitations already sent cannot be recalled.</p></div>' ) );

// The card serves the Mentors screen too, whose outcomes are on a channel of their own: the Stop the
// card draws for a screen names that screen's channel, and the press leaves its outcome there alone.
manager( 221 );
update_option( WPCPM_Mail::QUEUE_OPTION, array( 12 ) );
update_option(
	WPCPM_Mail::RUN_OPTION,
	array(
		'total'    => 1,
		'started'  => time(),
		'finished' => 0,
	)
);
request( array( 'page' => 'wpcpm-mentors' ) );
ob_start();
WPCPM_Mail::render_invite_card(
	array(
		'action'  => 'wpcpm_mentors_bulk_invite',
		'pending' => array(),
		'noun'    => 'mentors',
		'flash'   => 'mentors_admin',
	)
);
$mentors_card = forms_by_action( (string) ob_get_clean() );
$mentors_stop = press_form( isset( $mentors_card[ WPCPM_Mail::ACTION_STOP ] ) ? $mentors_card[ WPCPM_Mail::ACTION_STOP ] : '', array( 'WPCPM_Mail', 'handle_stop' ) );

ck( 'on another screen, the Stop names that screen\'s channel, and the outcome waits there, not on the Students screen\'s',
	array( link_of( $mentors_stop['redirect'] ), WPCPM_Flash::take( 'mentors_admin' ), WPCPM_Flash::take( 'students_admin' ) ),
	array( array( '/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors' ) ), 'invites-stopped', '' ) );

manager( 220 );
update_option(
	WPCPM_Mail::RUN_OPTION,
	array(
		'total'    => 2,
		'started'  => time(),
		'finished' => time(),
	)
);
$finished  = forms_by_action( html_of( page_for( screen() ) ) );
$dismissed = press_form( isset( $finished[ WPCPM_Mail::ACTION_DISMISS ] ) ? $finished[ WPCPM_Mail::ACTION_DISMISS ] : '', array( 'WPCPM_Mail', 'handle_dismiss' ) );

ck( 'the invitations card\'s Stop and Dismiss, the mail layer\'s own forms, name no tab and need none: each comes back to the address it was pressed on, the Accounts tab, with no outcome of its own in the address',
	array( link_of( $stopped['redirect'] ), link_of( $dismissed['redirect'] ) ),
	array(
		array( '/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) ),
		array( '/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) ),
	) );

echo "\n=== Each tab prints the notices of the presses made on it ===\n";

three_students();
$printed = array();
$person  = 230; // Not $uid, which at the top level is the global the stand-ins read as the person looking.

foreach ( array( 'started', 'cancelled', 'error', 'invited', 'invite-too-soon', 'invites-queued', 'invites-none', 'invites-stopped' ) as $status ) {
	foreach ( array( 'accounts', 'sync' ) as $tab ) {
		manager( $person++ );
		WPCPM_Flash::set( 'students_admin', $status );
		$printed[ $status ][ $tab ] = '' !== notices_in( html_of( page_for( screen( array( 'tab' => $tab ) ) ) ) );
	}
}

ck( 'the sync\'s outcomes print on the Sync tab and the invitations\' on the Accounts tab, and the shared error on either: a failed start comes back to Sync, a row\'s failed send to Accounts',
	$printed,
	array(
		'started'         => array( 'accounts' => false, 'sync' => true ),
		'cancelled'       => array( 'accounts' => false, 'sync' => true ),
		'error'           => array( 'accounts' => true, 'sync' => true ),
		'invited'         => array( 'accounts' => true, 'sync' => false ),
		'invite-too-soon' => array( 'accounts' => true, 'sync' => false ),
		'invites-queued'  => array( 'accounts' => true, 'sync' => false ),
		'invites-none'    => array( 'accounts' => true, 'sync' => false ),
		'invites-stopped' => array( 'accounts' => true, 'sync' => false ),
	) );

manager( 250 );
WPCPM_Flash::set( 'students_admin', 'started' );
$wrong = notices_in( html_of( page_for( screen() ) ) );
$left  = get_user_meta( 250, WPCPM_Flash::META, true );

ck( 'an outcome that comes back to the other tab, as a form drawn before the screen had tabs sends it, is taken there unprinted, so no later page shows it as news',
	array( $wrong, is_array( $left ) && array_key_exists( 'students_admin', $left ) ),
	array( '', false ) );

echo "\n=== The bar's printer, which the Mentors and Administrators screens print too ===\n";

$printer = function ( $page, array $tabs, $current ) {
	return run(
		function () use ( $page, $tabs, $current ) {
			ob_start();

			try {
				WPCPM_Screen_Tabs::render( $page, $tabs, $current );
			} finally {
				$html = (string) ob_get_clean();
			}

			return $html;
		}
	);
};
$bar_in  = function ( $out ) {
	return bar_of( html_of( $out ) );
};
$mentors = 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors';

ck( 'it prints the tabs it is given, in their order, at the screen it is given, the one shown marked, each label escaped',
	got( $printer( 'wpcpm-mentors', array( 'accounts' => 'Accounts', 'sync' => 'Sync & <em>more</em>' ), 'sync' ), $bar_in ),
	array(
		'Secondary menu',
		array(
			array( $mentors . '&tab=accounts', 'Accounts', false, false ),
			array( $mentors . '&tab=sync', 'Sync &amp; &lt;em&gt;more&lt;/em&gt;', true, true ),
		),
	) );
ck( 'and marks none when the tab shown is not among them',
	got( $printer( 'wpcpm-mentors', array( 'accounts' => 'Accounts' ), 'nope' ), $bar_in ),
	array( 'Secondary menu', array( array( $mentors . '&tab=accounts', 'Accounts', false, false ) ) ) );

echo "\n";
ck( 'and nothing asked a stand-in for anything it does not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
