<?php
/**
 * The Mentors screen: its two tabs, Accounts and Sync, and on the Accounts tab every Mentor account
 * as a WordPress list table, searched, sorted and paged, and the invitations sent from it.
 *
 * What this pins, and why each part is worth pinning:
 *
 * - Every Mentor account is reachable, however many the site has. Until 1.117.2 the list asked
 *   WordPress for the first 500 accounts by display name, the cap the Students screen carried until
 *   1.117.1, where it hid 13 of 513 accounts with no trace once the site passed 500 students. The
 *   list is paged now: the heading, the pagination and the views count every account, and the last
 *   page holds the 501st.
 * - The table's columns and their values (Mentor, Username, Students, Past, Status), the four after
 *   the name offered under Screen Options, the row actions under the name (Edit, View page to the
 *   Mentor Report Card, the invitation) and the views' counts.
 * - The sorts: Mentor by name, Username by username, and Students and Status by what the mentors
 *   sync writes on each account, read as the cells show it. The sync deletes the count of a mentor
 *   left with no student, and an account made by hand holds neither value, so a query sorting by
 *   the meta would drop them: the table orders every account itself, each value's accounts by name,
 *   then by ID, so every account is listed and none shows twice while another is on no page.
 * - The bulk actions are handled on the screen's load hook, before anything is drawn, as core's own
 *   lists handle theirs: the capability and the list's nonce before anything is read, then the
 *   accounts base's arithmetic called on the Mentor accounts table for its role and stamps, and
 *   the base's sentence for what came of it. The invitations card's own button queues everybody
 *   never invited and comes back to the Accounts tab.
 * - A row's invitation is a nonce link to the Mentors module's own invite handler, which sends it at
 *   once, as the row's form did, and a wrong nonce is refused before anything is read. The link
 *   carries the list's view, search, sort and page, and the handler comes back to them.
 * - The screen's wiring: its load hook, found once the menu has added the page; the tables loaded
 *   there, for the Accounts tab, and not before; the rows-per-page option on it; and the choice's
 *   save, hooked at boot, because WordPress saves screen options before it builds the menu.
 * - The screen is two tabs in the bar every audience screen prints (`WPCPM_Screen_Tabs`): Accounts,
 *   the tab the screen opens on, holding the invitations card and the list, and Sync, holding the
 *   Airtable sync, its last error, the warning that names are still unread, and its last report.
 *   Every press comes back to the tab it was made on, and each tab prints the notices of the
 *   presses made on it. The invitations card is handed the flash channel the screen reads its
 *   outcomes from, which the card's Stop names, so "Sending stopped." prints on this screen.
 *
 * WordPress is stood in for as far as the screen reaches, by the stand-ins every accounts screen's
 * suite shares (bin/stubs/accounts-screen.php), on the harness of bin/test-students-screen.php:
 * `WP_User_Query` answers over the fixture accounts the way WordPress answers the arguments the
 * screen passes, and anything else it is asked is kept apart and fails the last check, `include`
 * among them, which this screen has no reason to ask; `add_query_arg()` is core's, encoding and
 * all. The mentors sync, the mail layer, the flash and the tab bar are the plugin's own. The list
 * table is bin/stubs/class-wp-list-table.php, loaded through this suite's own
 * `wpcpm_load_accounts_tables()`, which requires what the plugin's does (bin/test-accounts-table.php
 * pins the plugin's); its markup is close to core's and is not core's.
 *
 * Each step is run, and what it drew is read, through the helpers the three screen suites share
 * (bin/stubs/screen-helpers.php).
 *
 * Run from the plugin root:  php bin/test-mentors-screen.php
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

/**
 * The settings, as far as the screen and the sync's start read them: whether Airtable is connected,
 * which a check can switch off, and the two statuses the start refuses to run without.
 */
class WPCPM_Settings {
	public static $connected = true;
	public static function is_connected() { return self::$connected; }
	public static function get() {
		return array(
			'student_statuses' => array( 'Currently mentoring' ),
			'mentor_status'    => 'Active',
		);
	}
}

/**
 * The Mentor Report Card's page and a mentor's count of current students, which the list prints:
 * the count read as the real class reads it, from the meta the mentors sync writes.
 */
class WPCPM_Mentors_Dashboard {
	public static $url = 'https://example.test/mentor-report-card/';
	public static function page_url() { return self::$url; }
	public static function get_mentee_count( $user_id ) { return (int) get_user_meta( (int) $user_id, WPCPM_Mentors_Sync::META_COUNT, true ); }
	public static function init() {}
	public static function ensure_page() {}
}

/** What the module boots besides its screen: notes, availability, calls, the calendar, sessions. None is drawn here. */
class WPCPM_Mentor_Notes {
	public static function init() {}
}
class WPCPM_Mentor_Availability {
	public static function init() {}
}
class WPCPM_Mentor_Calls {
	public static function init() {}
	public static function schedule() {}
}
class WPCPM_Call_Calendar {
	public static function init() {}
}
class WPCPM_Group_Sessions {
	public static function init() {}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
// The students sync keeps the three-hour recurrence every sync's schedule is placed on.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php';

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, which there is
 * no WordPress here to lend, then the files the plugin's own `wpcpm_load_accounts_tables()` requires,
 * in its order. The Mentors table's file is required once it exists, so a copy of the plugin without
 * it fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-table.php';

	if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-table.php' ) ) {
		require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-table.php';
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
 * The Mentors screen's query, with whatever else a check wants on it.
 *
 * @param array $more More query arguments.
 * @return array
 */
function screen( array $more = array() ) {
	return array_merge(
		array(
			'page' => 'wpcpm-mentors',
			'tab'  => 'accounts',
		),
		$more
	);
}

/**
 * A method of the Mentors module a check reaches that the module keeps to itself.
 *
 * @param string $name The method.
 * @return ReflectionMethod
 */
function reach( $name ) {
	$method = new ReflectionMethod( 'WPCPM_Mentors', $name );

	// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method;
}

/**
 * The Mentor accounts card, as the screen draws it for a request.
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
				$method->invoke( new WPCPM_Mentors() );
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
			$module = new WPCPM_Mentors();
			$module->load_screen();

			return 'drawn';
		}
	);
}

/**
 * The Mentors screen as WordPress draws it for a request: the screen's load hook, then the page,
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
			$module = new WPCPM_Mentors();
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
 * Mentors screen drawn at the tab the press came back to, the Accounts tab unless a check names
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
 * A press on one of the module's admin-post handlers: its action's nonce, and the fields its form posts.
 *
 * @param string $method The handler.
 * @param string $action The admin-post action, which is also the nonce's.
 * @param array  $fields The form's other fields.
 * @return array What run() kept.
 */
function post_to( $method, $action, array $fields ) {
	request( array( 'action' => $action ), array_merge( array( '_wpnonce' => wp_create_nonce( $action ) ), $fields ) );

	return run(
		function () use ( $method ) {
			( new WPCPM_Mentors() )->$method();
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

function heading_count( $html ) {
	return preg_match( '/<h2>Mentor accounts <span class="wpcpm-count">([^<]*)<\/span><\/h2>/', (string) $html, $found ) ? $found[1] : null;
}

/**
 * The queries a drawn list made, by what each was for.
 *
 * @param string $kind `page` (the rows drawn), `every` (a read of every account the list may hold,
 *                     whole, which a sort by a column's value orders itself) or `count` (a view's count).
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
				}

				return false;
			}
		)
	);
}

/* ---- fixtures ------------------------------------------------------------- */

/**
 * Put an account on the site with what the mentors sync writes on it: how many current and past
 * students the mentor has and whether Airtable holds them as Active, and the invitation stamp. A
 * null leaves the key off, as the sync leaves it off an account it has not written: it deletes the
 * count of a mentor left with no student, and an account made by hand holds none of them.
 *
 * @param int      $id       User ID.
 * @param string   $name     Display name.
 * @param string   $login    Username, or '' for one made from the name.
 * @param string[] $roles    Roles.
 * @param int|null $students The count of current students, or null for none written.
 * @param int|null $past     The count of past students, or null for none written.
 * @param int|null $active   The Active flag, or null for none written.
 * @param int|null $invited  The invitation stamp, or null for none.
 */
function mentor( $id, $name, $login, array $roles, $students = null, $past = null, $active = null, $invited = null ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, $roles, $login );
	$GLOBALS['umeta'][ $id ] = array();

	foreach ( array(
		WPCPM_Mentors_Sync::META_COUNT      => $students,
		WPCPM_Mentors_Sync::META_PAST_COUNT => $past,
		WPCPM_Mentors_Sync::META_ACTIVE     => $active,
		'wpcpm_mentor_invited'              => $invited,
	) as $key => $value ) {
		if ( null !== $value ) {
			$GLOBALS['umeta'][ $id ][ $key ] = $value;
		}
	}
}

function reset_site() {
	$GLOBALS['users']     = array();
	$GLOBALS['umeta']     = array();
	$GLOBALS['no_editor'] = false;
	manager( 1 );
}

/**
 * Three mentors: Ada, invited long ago, four students and two past, Active; Bruno, never invited,
 * one student, Active; Cleo, never invited, an account made by hand that the sync has written
 * nothing on. And a student and an administrator, neither of them on the list.
 */
function three_mentors() {
	reset_site();
	mentor( 11, 'Ada Kowalski', 'akowalski', array( WPCPM_Roles::ROLE_MENTOR ), 4, 2, 1, 1700000000 );
	mentor( 12, 'Bruno Diaz', 'bdiaz', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );
	mentor( 13, 'Cleo Ahn', 'cahn', array( WPCPM_Roles::ROLE_MENTOR ) );
	mentor( 20, 'Sam Student', 'sstudent', array( WPCPM_Roles::ROLE_STUDENT ) );
	mentor( 21, 'Ola Admin', 'oadmin', array( 'administrator' ) );
}

/**
 * The three, and two more for the sorts, each named so that the name order differs from the ID
 * order among the mentors of one value: Beth, one student as Bruno has, whom the sync no longer
 * finds Active (the flag written 0); Aria, Active with past students only, her count written 0.
 */
function five_mentors() {
	three_mentors();
	mentor( 14, 'Beth Park', 'bpark', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 0 );
	mentor( 15, 'Aria Stone', 'astone', array( WPCPM_Roles::ROLE_MENTOR ), 0, 3, 1, 1700000000 );
}

// The request that saves a rows-per-page choice, as WordPress makes it: the module booted on
// `plugins_loaded`, then the save's filter applied in `set_screen_options()`, before the menu, the
// screen's load hook or any table exists. In a process of its own, because the checks below declare
// the tables, and a class once declared cannot be undeclared.
if ( isset( $argv[1] ) && 'child-save' === $argv[1] ) {
	$declared = class_exists( 'WPCPM_Mentors_Table', false );
	$saved    = run(
		function () {
			( new WPCPM_Mentors() )->boot();

			return apply_filters( 'set_screen_option_wpcpm_mentors_per_page', false, 'wpcpm_mentors_per_page', '50' );
		}
	);

	echo json_encode(
		array(
			'before' => $declared,
			'saved'  => $saved['value'],
			'error'  => $saved['error'],
			'after'  => class_exists( 'WPCPM_Mentors_Table', false ),
		)
	);
	exit( 0 );
}

// The invitations card's button as WordPress runs it: admin-post.php, where nothing has loaded the
// list tables the card's reading of the role and the stamp comes from. In a process of its own, for
// the reason the save's run above has one.
if ( isset( $argv[1] ) && 'child-card' === $argv[1] ) {
	three_mentors();

	$declared = class_exists( 'WPCPM_Mentors_Table', false );
	$pressed  = post_to( 'handle_bulk_invite', WPCPM_Mentors::ACTION_BULK, array( 'wpcpm_tab' => 'accounts' ) );

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

// The Accounts tab drawn without the screen's load hook, the way the module draws its list when
// nothing built the table first, under what a press on the ticked accounts left: the sentence for
// it is the accounts base's, which nothing has loaded yet. In a process of its own, as above.
if ( isset( $argv[1] ) && 'child-notice' === $argv[1] ) {
	three_mentors();
	manager( 7 );
	WPCPM_Flash::set( 'mentors_admin', 'invites-queued' );
	WPCPM_Flash::set(
		'mentors_admin_detail',
		array(
			'status' => 'invites-queued',
			'resend' => false,
			'queued' => 2,
		)
	);
	request( screen() );

	$declared = class_exists( 'WPCPM_Accounts_Table', false );
	$drawn    = run(
		function () {
			ob_start();

			try {
				( new WPCPM_Mentors() )->render_admin_page();
			} finally {
				$html = (string) ob_get_clean();
			}

			return $html;
		}
	);

	echo json_encode(
		array(
			'before'  => $declared,
			'error'   => $drawn['error'],
			'notices' => notices_in( html_of( $drawn ) ),
		)
	);
	exit( 0 );
}

/* ---- the checks ----------------------------------------------------------- */

echo "=== The screen loads its list table on its own load hook, and not before ===\n";

ck( 'nothing has declared a list table before the screen loads',
	array( class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Mentors_Table', false ) ),
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
	array( 0, false, '', 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=accounts', array( 12, 13 ) ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-notice 2>&1', $notice_out, $notice_status );
$notice_run = (array) json_decode( implode( "\n", $notice_out ), true );

ck( 'a press on the ticked accounts is worded on a tab drawn without the screen\'s load hook too: its sentence is the accounts base\'s, loaded for it',
	array( $notice_status, isset( $notice_run['before'] ) ? $notice_run['before'] : implode( "\n", $notice_out ), isset( $notice_run['error'] ) ? $notice_run['error'] : null, isset( $notice_run['notices'] ) ? $notice_run['notices'] : null ),
	array( 0, false, '', '<div class="notice notice-success is-dismissible"><p>2 invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

three_mentors();
$sync_load = press( screen( array( 'tab' => 'sync' ) ) );

ck( 'on the Sync tab, which has no list, the load hook leaves the list alone: no list table is loaded, no rows-per-page option offered, no account read and no list headings set, and the page goes on',
	got(
		$sync_load,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Mentors_Table', false ), $GLOBALS['screen_options'], $GLOBALS['queries'], $GLOBALS['current_screen']->reader_content );
		}
	),
	array( 'drawn', false, false, false, array(), array(), array() ) );

three_mentors();
$loaded = press( screen() );

ck( 'the screen\'s load hook loads core\'s list table, the accounts base and the Mentors table, and lets the page go on',
	got(
		$loaded,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Mentors_Table', false ) );
		}
	),
	array( 'drawn', true, true, true ) );
ck( 'it puts the rows-per-page option on the screen: twenty until somebody chooses, kept under the Mentors\' own name',
	isset( $GLOBALS['screen_options']['per_page'] ) ? $GLOBALS['screen_options']['per_page'] : 'no option added',
	array(
		'default' => 20,
		'option'  => 'wpcpm_mentors_per_page',
	) );
ck( 'and prepares the list there, before the page\'s header, as core\'s own lists do', count( queries( 'page' ) ), 1 );
ck( 'it names the list\'s three headings for screen readers, which core leaves out unless the screen sets them',
	$GLOBALS['current_screen']->reader_content,
	array(
		'heading_views'      => 'Filter mentor accounts list',
		'heading_pagination' => 'Mentor accounts list navigation',
		'heading_list'       => 'Mentor accounts list',
	) );

request( screen() );
$GLOBALS['current_screen'] = null;
$screenless                = run(
	function () {
		( new WPCPM_Mentors() )->load_screen();

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
$GLOBALS['l10n'] = array( 'Mentor accounts list' => 'Lista de <b>mentores</b>' );
press( screen() );
$GLOBALS['l10n'] = array();

ck( 'the headings are escaped before core prints them as they are',
	isset( $GLOBALS['current_screen']->reader_content['heading_list'] ) ? $GLOBALS['current_screen']->reader_content['heading_list'] : 'not set',
	'Lista de &lt;b&gt;mentores&lt;/b&gt;' );

// The menu opens the screen at its own address, which names no tab: that is the Accounts tab, and
// every sort and page link core builds from it names none either.
$GLOBALS['screen_options'] = array();
$from_menu                 = press( array( 'page' => 'wpcpm-mentors' ) );

ck( 'from the menu\'s own address, which names no tab, the load hook does the Accounts tab\'s work: the rows-per-page option, the three headings and the page of rows, read before the page\'s header',
	got(
		$from_menu,
		function ( $out ) {
			return array( $out['value'], isset( $GLOBALS['screen_options']['per_page']['option'] ) ? $GLOBALS['screen_options']['per_page']['option'] : 'no option added', array_keys( $GLOBALS['current_screen']->reader_content ), count( queries( 'page' ) ) );
		}
	),
	array( 'drawn', 'wpcpm_mentors_per_page', array( 'heading_views', 'heading_pagination', 'heading_list' ), 1 ) );

echo "\n=== Every Mentor account is reachable, a page at a time: 501 of them ===\n";

// Named so that display-name order is the numbering (Mentor 001 to Mentor 501), a student, and an
// administrator who is nobody's mentor.
reset_site();
for ( $i = 1; $i <= 501; $i++ ) {
	mentor( 100 + $i, sprintf( 'Mentor %03d', $i ), '', array( WPCPM_Roles::ROLE_MENTOR ), $i % 4, 0, 1, 1 );
}
mentor( 700, 'Student Example', '', array( WPCPM_Roles::ROLE_STUDENT ) );
mentor( 701, 'Manager Example', '', array( 'administrator' ) );

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
ck( 'the heading, the pagination and the views count every Mentor account, all 501 of them, never a first page',
	array( heading_count( $html ), displaying( $html ), view_counts( $html ) ),
	array(
		'501',
		'501 items',
		array(
			'all'           => '501',
			'invited'       => '501',
			'never-invited' => '0',
		),
	) );
ck( 'the list asked WordPress for one page, and for its total',
	array( arg( first_query( 'page' ), 'number' ), arg( first_query( 'page' ), 'offset' ), arg( first_query( 'page' ), 'count_total' ), arg( first_query( 'page' ), 'role' ) ),
	array( 20, 0, true, WPCPM_Roles::ROLE_MENTOR ) );

$last = draw( screen( array( 'paged' => '26' ) ) );

ck( 'the last page, the 26th, holds the 501st account by name', row_ids( html_of( $last ) ), array( 601 ) );

$GLOBALS['umeta'][1]['wpcpm_mentors_per_page'] = 999;
$every = row_ids( html_of( draw( screen() ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_mentors_per_page'] );

ck( 'on one page of 999, every Mentor account is listed, and the student and the administrator are not',
	array( count( $every ), in_array( 601, $every, true ), in_array( 700, $every, true ), in_array( 701, $every, true ) ),
	array( 501, true, false, false ) );

echo "\n=== The table: a checkbox, then Mentor, Username, Students, Past and Status ===\n";

three_mentors();
$html = html_of( draw( screen() ) );

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", between( $html, '<thead>', '</thead>' ), $header_ids );

ck( 'the headers are the checkbox, then the five columns in order', $header_ids[1], array( 'cb', 'name', 'login', 'students', 'past', 'status' ) );
ck( 'each says what it holds, the four that sort as sort links and Past as plain text',
	array( has( $html, '<span>Mentor</span>' ), has( $html, '<span>Username</span>' ), has( $html, '<span>Students</span>' ), has( $html, '>Past</th>' ), has( $html, '<span>Status</span>' ) ),
	array( true, true, true, true, true ) );
ck( 'a row per mentor, by name: Ada, Bruno, Cleo; the student and the administrator are not on it', row_ids( $html ), array( 11, 12, 13 ) );

$ada  = row_for( $html, 11 );
$cleo = row_for( $html, 13 );

ck( 'Ada\'s row: her name to her account\'s editor, her username, four students, two past, Active',
	array( cell( $ada, 'name' ), cell( $ada, 'login' ), cell( $ada, 'students' ), cell( $ada, 'past' ), cell( $ada, 'status' ) ),
	array( '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=11">Ada Kowalski</a></strong>', '<code>akowalski</code>', '4', '2', 'Active' ) );
ck( 'Cleo\'s, an account the sync has written nothing on: no students, none past, Not in Airtable, as the list has always shown it',
	array( cell( $cleo, 'students' ), cell( $cleo, 'past' ), cell( $cleo, 'status' ) ),
	array( '0', '0', 'Not in Airtable' ) );

$ada_actions   = actions_in( $ada );
$bruno_actions = actions_in( row_for( $html, 12 ) );

ck( 'under each name, Edit, View page, then the invitation: Resend invite for Ada, invited before, Send invite for Bruno',
	array( array_keys( $ada_actions ), array_keys( $bruno_actions ) ),
	array( array( 'edit', 'view', 'reinvite' ), array( 'edit', 'view', 'invite' ) ) );
ck( 'which say so', array_map( 'strip_tags', $ada_actions ), array( 'edit' => 'Edit', 'view' => 'View page', 'reinvite' => 'Resend invite' ) );
ck( 'Edit opens the account\'s editor, and View page the Mentor Report Card as that mentor',
	array( link_of( isset( $ada_actions['edit'] ) ? $ada_actions['edit'] : '' ), link_of( isset( $ada_actions['view'] ) ? $ada_actions['view'] : '' ) ),
	array( array( 'https://example.test/wp-admin/user-edit.php', array( 'user_id' => '11' ) ), array( 'https://example.test/mentor-report-card/', array( 'wpcpm_mentor' => '11' ) ) ) );
ck( 'the views count the three: All, one Invited, two Never invited',
	view_counts( $html ),
	array(
		'all'           => '3',
		'invited'       => '1',
		'never-invited' => '2',
	) );

$form = between( $html, '<form method="get"', '</form>' );

ck( 'the list is one form, sent to the screen by GET with its page and its tab, holding the checkboxes, the bulk actions and their nonce',
	array( has( $form, '<input type="hidden" name="page" value="wpcpm-mentors" />' ), has( $form, '<input type="hidden" name="tab" value="accounts" />' ), has( $form, 'name="users[]"' ), has( $form, '<select name="action"' ), has( $form, 'value="' . wp_create_nonce( 'bulk-mentors' ) . '"' ) ),
	array( true, true, true, true, true ) );
ck( 'with the search box, and no form left in a row', array( has( $form, 'name="s"' ), substr_count( $html, '<form' ) ), array( true, 1 ) );
ck( 'above it the mentor page\'s address and below it how the accounts are made, as the list has always shown them',
	array( has( $html, '<p>Mentor Dashboard: <a href="https://example.test/mentor-report-card/">https://example.test/mentor-report-card/</a></p>' ), has( $html, '<p class="description">Accounts are created with a random password and no email.' ) ),
	array( true, true ) );

// Core's Screen Options offers a box to hide each column the screen's columns filter answers, and
// the table answers it with every column but the primary one, which holds the row's actions.
$GLOBALS['list_screen'] = (object) array(
	'id'   => 'wpcredits-program_page_wpcpm-mentors',
	'base' => 'wpcredits-program_page_wpcpm-mentors',
);
$offered                = run(
	function () {
		new WPCPM_Mentors_Table();

		return array_keys( apply_filters( 'manage_wpcredits-program_page_wpcpm-mentors_columns', array() ) );
	}
);
$GLOBALS['list_screen'] = null;

ck( 'Screen Options offers the four columns after the name to hide, and never the name, which holds the row\'s actions',
	got(
		$offered,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'cb', 'login', 'students', 'past', 'status' ) );

$never = html_of( draw( screen( array( 'wpcpm_view' => 'never-invited' ) ) ) );

ck( 'on the Never invited view, Bruno and Cleo, and the form carries the view, so a search stays on it',
	array( row_ids( $never ), has( between( $never, '<form method="get"', '</form>' ), '<input type="hidden" name="wpcpm_view" value="never-invited" />' ) ),
	array( array( 12, 13 ), true ) );

WPCPM_Mentors_Dashboard::$url = '';
$GLOBALS['no_editor']         = true;
$bare_html                    = html_of( draw( screen() ) );
$bare                         = row_for( $bare_html, 11 );
WPCPM_Mentors_Dashboard::$url = 'https://example.test/mentor-report-card/';
$GLOBALS['no_editor']         = false;

ck( 'with no Mentor Report Card page and no right to the editor, the name is plain, the invitation the one action, and the card says the page is missing',
	array( cell( $bare, 'name' ), array_keys( actions_in( $bare ) ), has( $bare_html, 'The mentor page is missing. Re-activate the plugin to recreate it.' ) ),
	array( '<strong>Ada Kowalski</strong>', array( 'reinvite' ), true ) );

reset_site();

ck( 'with no Mentor account yet, the list says how accounts arrive, and on which tab the sync is', has( html_of( draw( screen() ) ), 'No mentor accounts yet. Run a sync on the Sync tab to create them.' ), true );

three_mentors();
unset( $GLOBALS['umeta'][11]['wpcpm_mentor_invited'] );
$nobody_invited = html_of( draw( screen( array( 'wpcpm_view' => 'invited' ) ) ) );

ck( 'a view nobody is in says the list found nobody, rather than that no accounts exist', array( row_ids( $nobody_invited ), has( $nobody_invited, 'No mentor accounts found.' ) ), array( array(), true ) );

echo "\n=== Invited is any stamp: a mentor who studies, sent a student's invitation ===\n";

// An account has one password, so an invitation sent it as a student is one sent it as a mentor
// too: the list's views, its row and the invitations card read every stamp, as the queue does.
// Dev mentors and studies and was stamped as a student alone; Bruno and Cleo were never sent one.
three_mentors();
mentor( 16, 'Dev Both', 'dboth', array( WPCPM_Roles::ROLE_MENTOR, WPCPM_Roles::ROLE_STUDENT ), 2, 0, 1 );
$GLOBALS['umeta'][16]['wpcpm_student_invited'] = 1700000000;
manager( 230 );
$both_page = html_of( page_for( screen() ) );
$both_card = forms_by_action( $both_page );

ck( 'Dev is Invited and counted so, his row offers Resend invite, and the invitations card counts the two never sent one',
	array(
		view_counts( $both_page ),
		array_keys( actions_in( row_for( $both_page, 16 ) ) ),
		has( isset( $both_card[ WPCPM_Mentors::ACTION_BULK ] ) ? $both_card[ WPCPM_Mentors::ACTION_BULK ] : '', '>Invite 2 mentors who have never been invited</button>' ),
	),
	array( array( 'all' => '4', 'invited' => '2', 'never-invited' => '2' ), array( 'edit', 'view', 'reinvite' ), true ) );
// The card's question is shared by every audience's screen, so its words fit any audience's noun.
ck( 'the card asks before it sends, of the two, in the plural',
	has( $both_page, 'onsubmit="return confirm(\'Send an invitation to 2 of the mentors? They cannot be recalled once sent.\');"' ),
	true );
ck( 'the Invited view lists him with Ada, and the Never invited view Bruno and Cleo',
	array( row_ids( html_of( draw( screen( array( 'wpcpm_view' => 'invited' ) ) ) ) ), row_ids( html_of( draw( screen( array( 'wpcpm_view' => 'never-invited' ) ) ) ) ) ),
	array( array( 11, 16 ), array( 12, 13 ) ) );

echo "\n=== The search: the name, the username and the email ===\n";

three_mentors();
$found = array();
foreach ( array( 'KOWAL', 'bdiaz', 'cahn@example', 'ahn' ) as $term ) {
	$found[ $term ] = row_ids( html_of( draw( screen( array( 's' => $term ) ) ) ) );
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

ck( 'WordPress is asked for the Mentor accounts the search finds, "contains", over the username, the email and the name',
	array( arg( first_query( 'page' ), 'search' ), arg( first_query( 'page' ), 'search_columns' ), arg( first_query( 'page' ), 'role' ) ),
	array( '*bdiaz*', array( 'user_login', 'user_email', 'display_name' ), WPCPM_Roles::ROLE_MENTOR ) );
ck( 'the views count the whole list, not the search, as WordPress\'s own views do',
	view_counts( $searched ),
	array(
		'all'           => '3',
		'invited'       => '1',
		'never-invited' => '2',
	) );

$nobody = html_of( draw( screen( array( 's' => 'zzz' ) ) ) );

ck( 'a search nobody matches says so, rather than that no accounts exist', array( row_ids( $nobody ), has( $nobody, 'No mentor accounts found.' ) ), array( array(), true ) );

echo "\n=== The sorts: Mentor, Username, Students and Status, and not Past ===\n";

five_mentors();
$plain = html_of( draw( screen() ) );

ck( 'Mentor sorts by name, Username by username, Students by the count of current students and Status by the flag the sync writes; Past does not sort',
	array(
		sort_of( $plain, 'name' ),
		sort_of( $plain, 'login' ),
		sort_of( $plain, 'students' ),
		sort_of( $plain, 'past' ),
		sort_of( $plain, 'status' ),
	),
	array( 'display_name', 'login', 'students', 'not sortable', 'status' ) );
ck( 'a list nobody sorted is by name, A to Z: Ada, Aria, Beth, Bruno, Cleo', row_ids( $plain ), array( 11, 15, 14, 12, 13 ) );
ck( 'Username sorts Z to A as asked', row_ids( html_of( draw( screen( array( 'orderby' => 'login', 'order' => 'desc' ) ) ) ) ), array( 13, 14, 12, 15, 11 ) );

$by_students   = draw( screen( array( 'orderby' => 'students', 'order' => 'asc' ) ) );
$students_read = $GLOBALS['queries'];

ck( 'Students A to Z, the fewest first, each count\'s mentors by name: Aria\'s written 0 and Cleo\'s, which the sync never wrote, read as the 0 her cell shows; Beth and Bruno\'s one; Ada\'s four',
	got(
		$by_students,
		function ( $out ) {
			return row_ids( html_of( $out ) );
		}
	),
	array( 15, 13, 14, 12, 11 ) );
ck( 'Students Z to A, the most first, each count\'s mentors still by name',
	row_ids( html_of( draw( screen( array( 'orderby' => 'students', 'order' => 'desc' ) ) ) ) ),
	array( 11, 14, 12, 15, 13 ) );
ck( 'Status A to Z, Active first, each by name: Ada, Aria, Bruno, then Not in Airtable, Beth, whom the sync no longer finds, and Cleo, who holds no flag at all, as her cell reads',
	row_ids( html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'asc' ) ) ) ) ),
	array( 11, 15, 12, 14, 13 ) );
ck( 'Status Z to A, Not in Airtable first, each still by name',
	row_ids( html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'desc' ) ) ) ) ),
	array( 14, 13, 11, 15, 12 ) );

$GLOBALS['queries'] = $students_read;

ck( 'a sort by a value lists every account, counted: one read of every Mentor account the list may hold, whole, which the table orders itself, and none WordPress is asked to sort by the value or to page',
	array( displaying( html_of( $by_students ) ), count( queries( 'every' ) ), arg( first_query( 'every' ), 'orderby' ), arg( first_query( 'every' ), 'role' ), count( queries( 'page' ) ) ),
	array( '5 items', 1, 'ID', WPCPM_Roles::ROLE_MENTOR, 0 ) );

$GLOBALS['umeta'][1]['wpcpm_mentors_per_page'] = 2;
$second = html_of( draw( screen( array( 'orderby' => 'students', 'order' => 'asc', 'paged' => '2' ) ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_mentors_per_page'] );

ck( 'two a page, the second page of Students A to Z is the third and the fourth, Beth then Bruno, and the pagination counts all five',
	array( row_ids( $second ), displaying( $second ) ),
	array( array( 14, 12 ), '5 items' ) );

$sorted_view   = html_of( draw( screen( array( 'orderby' => 'students', 'wpcpm_view' => 'never-invited' ) ) ) );
$sorted_search = html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'desc', 's' => 'o' ) ) ) );

ck( 'sorted by a value, a view keeps to the view and a search to what the search found',
	array( row_ids( $sorted_view ), displaying( $sorted_view ), row_ids( $sorted_search ), displaying( $sorted_search ) ),
	array( array( 13, 14, 12 ), '3 items', array( 13, 11, 15, 12 ), '4 items' ) );

preg_match( "/<th scope=\"col\" id='students' class='([^']*)'/", html_of( $by_students ), $marked );

ck( 'the Students header says the list is sorted by it, A to Z', isset( $marked[1] ) ? $marked[1] : '', 'manage-column column-students sorted asc' );

// The base asks for the name sort in WP_User_Query's array form, so a table that sorts some of its
// columns itself reads what the list is sorted by from the array's first key.
$sorted_as_array = run(
	function () {
		$query = new ReflectionMethod( 'WPCPM_Mentors_Table', 'query' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$query->setAccessible( true );
		}

		request( screen() );
		$found = $query->invoke(
			new WPCPM_Mentors_Table(),
			array(
				'role'        => WPCPM_Roles::ROLE_MENTOR,
				'number'      => 20,
				'offset'      => 0,
				'orderby'     => array(
					'students' => 'ASC',
					'ID'       => 'ASC',
				),
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

ck( 'asked for the Students sort in the array form, the table still sorts by the count, reading the array\'s first key',
	got(
		$sorted_as_array,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 15, 13, 14, 12, 11 ) );

echo "\n=== Two mentors of one value and one name, where one page ends and the next begins ===\n";

// A row a page, so the boundary between two pages falls between the two Sam Riveras, whose count and
// flag are the same: the table's order has to settle them, or one could show on both pages and the
// other on neither.
reset_site();
mentor( 51, 'Sam Rivera', 'srivera', array( WPCPM_Roles::ROLE_MENTOR ), 2, 0, 1 );
mentor( 52, 'Sam Rivera', 'sriveraortiz', array( WPCPM_Roles::ROLE_MENTOR ), 2, 0, 1 );
mentor( 53, 'Tia Rivera', 'trivera', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );
$GLOBALS['umeta'][1]['wpcpm_mentors_per_page'] = 1;

$pages = array();
foreach ( array( 'students', 'status' ) as $sort ) {
	foreach ( array( 'asc', 'desc' ) as $way ) {
		foreach ( array( '1', '2', '3' ) as $number ) {
			$pages[ $sort . ' ' . $way ][] = row_ids( html_of( draw( screen( array( 'orderby' => $sort, 'order' => $way, 'paged' => $number ) ) ) ) );
		}
	}
}

unset( $GLOBALS['umeta'][1]['wpcpm_mentors_per_page'] );

ck( 'each Sam Rivera is on a page of their own, by either sort and either way: of one value and one name, the lower ID first',
	$pages,
	array(
		'students asc'  => array( array( 53 ), array( 51 ), array( 52 ) ),
		'students desc' => array( array( 51 ), array( 52 ), array( 53 ) ),
		'status asc'    => array( array( 51 ), array( 52 ), array( 53 ) ),
		'status desc'   => array( array( 51 ), array( 52 ), array( 53 ) ),
	) );

// PHP's sort is stable since 8.0, and the read comes back in ID order, so on the PHP here the two
// Sam Riveras keep their order whether or not the table's order settles them: the order the table
// sorts by is asked directly, for two accounts of one value and one name, either way round, so the
// ID that settles them on PHP 7.4, whose sort may put two equal items either way round, is pinned
// on any PHP.
$settled = run(
	function () {
		$compare = new ReflectionMethod( 'WPCPM_Mentors_Table', 'compare_accounts' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$compare->setAccessible( true );
		}

		$lower  = array(
			'user'  => $GLOBALS['users'][51],
			'value' => 2,
		);
		$higher = array(
			'user'  => $GLOBALS['users'][52],
			'value' => 2,
		);

		return array(
			$compare->invoke( null, $lower, $higher, false ) < 0,
			$compare->invoke( null, $lower, $higher, true ) < 0,
			$compare->invoke( null, $higher, $lower, false ) > 0,
			$compare->invoke( null, $higher, $lower, true ) > 0,
		);
	}
);

ck( 'and the order itself says so: of two accounts of one value and one name, the lower ID comes first, whichever way the values run and whichever of the two is asked about first',
	got(
		$settled,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( true, true, true, true ) );

echo "\n=== Names within one value, read as a reader reads them ===\n";

// The mentors are international, and a name compared byte by byte puts every name that opens on an
// accented letter after Z, where a reader of the list looks for it among its plain letter.
reset_site();
mentor( 61, 'Zofia Nowak', 'znowak', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );
mentor( 62, 'Łukasz Wójcik', 'lwojcik', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );
mentor( 63, 'Álvaro Núñez', 'anunez', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );
mentor( 64, 'alba Ruiz', 'aruiz', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );

ck( 'within one value, names run A to Z without regard to case or accents, by Students and by Status: alba Ruiz, written lowercase, then Álvaro, then Łukasz among the L\'s, then Zofia',
	array(
		row_ids( html_of( draw( screen( array( 'orderby' => 'students', 'order' => 'asc' ) ) ) ) ),
		row_ids( html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'desc' ) ) ) ) ),
	),
	array( array( 64, 63, 62, 61 ), array( 64, 63, 62, 61 ) ) );

echo "\n=== Send invite and Resend invite on the ticked accounts, handled before the screen draws ===\n";

three_mentors();

// What the list's form sends besides the choice: core's nonce and the address it came from; and
// what a press of its Apply button adds, the button's name, which core gives it from WordPress 6.7
// on, as the version here does. A search is sent without it.
$sent_with = array(
	'_wpnonce'         => wp_create_nonce( 'bulk-mentors' ),
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-mentors&tab=accounts',
	'action2'          => '-1',
);
$applied   = array_merge( $sent_with, array( 'bulk_action' => 'Apply' ) );
$accounts  = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts' ) );

manager( 2 );
$sent       = press( screen( array_merge( $applied, array( 'action' => 'invite', 'users' => array( '12', '13' ) ) ) ) );
$sent_reads = $GLOBALS['reads'];

ck( 'Send invite on Bruno and Cleo, never invited, queues the two',
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
ck( 'the list\'s nonce was the one asked about', $GLOBALS['nonce_checks'], array( 'bulk-mentors' ) );
ck( 'the ticked accounts are read in one go, with their meta, before any of them is looked at',
	array( isset( $sent_reads[0] ) ? $sent_reads[0] : 'nothing read', count( preg_grep( '/^cache /', $sent_reads ) ) ),
	array( 'cache 12,13', 1 ) );
ck( 'and the screen says how many were queued, in the words every audience\'s list uses',
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
ck( 'one account at a time, not through the card\'s run, which stays its own button\'s: Ada waits in the queue, and nothing is sent yet', array( WPCPM_Mail::queue(), WPCPM_Mail::run(), $GLOBALS['mails'] ), array( array( 11 ), array(), array() ) );
ck( 'the screen says so, and that the new link replaces the old one',
	html_of( notice_now() ),
	'<div class="notice notice-success is-dismissible"><p>1 invitation queued. It goes out with the next batch and replaces the link in any earlier invitation.</p></div>' );

manager( 4 );
$nothing = press( screen( array_merge( $applied, array( 'action' => 'invite' ) ) ) );

ck( 'with nothing ticked, the press queues nobody, returns to the list and says why',
	array( link_of( $nothing['redirect'] ) === $accounts, WPCPM_Mail::queue(), html_of( notice_now() ) ),
	array( true, array(), '<div class="notice notice-info is-dismissible"><p>Nothing to send: no accounts were selected.</p></div>' ) );

manager( 5 );
press( screen( array_merge( $applied, array( 'action' => 'invite', 'users' => array( '20', '12', '999', '12' ) ) ) ) );

ck( 'every ticked ID is checked against the Mentor role: the student and an ID nobody holds are skipped, a repeat counts once',
	array( WPCPM_Mail::queue(), html_of( notice_now() ) ),
	array( array( 12 ), '<div class="notice notice-success is-dismissible"><p>1 invitation queued. It goes out in the background - the progress is shown below.</p></div>' ) );

manager( 6 );
$kept = press(
	screen(
		array_merge(
			$applied,
			array(
				'action'     => 'invite',
				'users'      => array( '13' ),
				'wpcpm_view' => 'never-invited',
				's'          => 'cleo',
				'orderby'    => 'students',
				'order'      => 'desc',
				'paged'      => '2',
			)
		)
	)
);

ck( 'the press returns to the list as it was, its view, search, sort and page, and none of the form\'s own fields',
	link_of( $kept['redirect'] ),
	array(
		'https://example.test/wp-admin/admin.php',
		array(
			'page'       => 'wpcpm-mentors',
			'tab'        => 'accounts',
			'wpcpm_view' => 'never-invited',
			's'          => 'cleo',
			'orderby'    => 'students',
			'order'      => 'desc',
			'paged'      => '2',
		),
	) );

manager( 8 );
$forged = press( screen( array_merge( $applied, array( '_wpnonce' => 'forged', 'action' => 'invite', 'users' => array( '12', '13' ) ) ) ) );

ck( 'a press with the wrong nonce dies with WordPress\'s own sentence, before any account is read or queued',
	array( $forged['died'], $GLOBALS['reads'], WPCPM_Mail::queue() ),
	array( 'The link you followed has expired.', array(), array() ) );

manager( 9 );
$GLOBALS['caps'] = false;
$refused         = press( screen( array_merge( $applied, array( 'action' => 'invite', 'users' => array( '12' ) ) ) ) );
$GLOBALS['caps'] = true;

ck( 'somebody without the program\'s capability is refused before the nonce is asked about or anything is read',
	array( $refused['died'], $GLOBALS['nonce_checks'], $GLOBALS['reads'], WPCPM_Mail::queue() ),
	array( 'You do not have permission to manage the program.', array(), array(), array() ) );

manager( 10 );
$other = press( screen( array( 'action' => 'delete', 'users' => array( '12' ), 'bulk_action' => 'Apply' ) ) );

ck( 'a bulk action the list does not have does nothing: the screen draws, no nonce is asked about, nobody is queued',
	array( $other['value'], $GLOBALS['nonce_checks'], WPCPM_Mail::queue() ),
	array( 'drawn', array(), array() ) );

manager( 31 );
$searched = press( screen( array_merge( $sent_with, array( 'action' => '-1', 's' => 'ada' ) ) ) );

ck( 'a search sent from the list\'s form comes back to the same list without the form\'s nonce, as core\'s own lists do',
	link_of( $searched['redirect'] ),
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts', 's' => 'ada' ) ) );
ck( 'and nothing is asked about or queued on the way', array( '' !== $searched['redirect'], $GLOBALS['nonce_checks'], WPCPM_Mail::queue() ), array( true, array(), array() ) );

// A search pressed with Send invite chosen and Bruno ticked is a search: the form is sent without
// Apply, so nothing is asked about or queued, and the press comes back to the list, searched.
manager( 32 );
$searched_invite = press( screen( array_merge( $sent_with, array( 'action' => 'invite', 'users' => array( '12' ), 's' => 'bruno' ) ) ) );

ck( 'a search pressed with Send invite chosen and Bruno ticked queues nobody and asks about no nonce: the press comes back to the list, searched',
	array( WPCPM_Mail::queue(), $GLOBALS['nonce_checks'], link_of( $searched_invite['redirect'] ) ),
	array( array(), array(), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts', 's' => 'bruno' ) ) ) );

echo "\n=== A row's invitation: a nonce link to the Mentors module's own handler ===\n";

three_mentors();
manager( 40 );
$rows       = html_of( draw( screen() ) );
$bruno_link = link_of( actions_in( row_for( $rows, 12 ) )['invite'] ?? '' );

ck( 'Bruno\'s Send invite goes to the module\'s invite handler with his account, the tab to come back to and the nonce that handler checks, keyed to his account',
	array( $bruno_link[0], arg( $bruno_link[1], 'action' ), arg( $bruno_link[1], 'user' ), arg( $bruno_link[1], 'wpcpm_tab' ), arg( $bruno_link[1], '_wpnonce' ) ),
	array( 'https://example.test/wp-admin/admin-post.php', WPCPM_Mentors::ACTION_INVITE, '12', 'accounts', wp_create_nonce( WPCPM_Mentors::ACTION_INVITE . '_12' ) ) );

request( $bruno_link[1] );
$followed = run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);

ck( 'followed, it sends Bruno his invitation now, as the row\'s form did, and stamps his account',
	array( $GLOBALS['mails'], isset( $GLOBALS['umeta'][12]['wpcpm_mentor_invited'] ) && is_int( $GLOBALS['umeta'][12]['wpcpm_mentor_invited'] ) ),
	array( array( 12 ), true ) );
ck( 'then returns to the Accounts tab, saying the email went',
	array( link_of( $followed['redirect'] ), has( html_of( notice_now() ), 'Invitation email sent.' ) ),
	array( $accounts, true ) );

manager( 41 );
$ada_link = link_of( actions_in( row_for( $rows, 11 ) )['reinvite'] ?? '' );
request( $ada_link[1] );
run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);

ck( 'Ada\'s Resend invite sends her a fresh one', $GLOBALS['mails'], array( 11 ) );

manager( 42 );
request( array( 'action' => WPCPM_Mentors::ACTION_INVITE ), array( 'user_id' => '13', '_wpnonce' => wp_create_nonce( WPCPM_Mentors::ACTION_INVITE . '_13' ) ) );
$posted = run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);

// The account is read from the row's link alone: no form in the plugin posts one, so a posted
// account names nobody, the nonce asked is keyed to no account, and the request dies there.
ck( 'an account posted by a form is not read: the request names no account, dies at the nonce keyed to none, and sends nothing', array( $posted['died'], $GLOBALS['nonce_checks'], $GLOBALS['mails'] ), array( 'The link you followed has expired.', array( WPCPM_Mentors::ACTION_INVITE . '_0' ), array() ) );

manager( 43 );
request( array_merge( $bruno_link[1], array( '_wpnonce' => 'forged' ) ) );
$forged = run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);

ck( 'with the wrong nonce the link dies before any account is read, and sends nothing',
	array( $forged['died'], $GLOBALS['reads'], $GLOBALS['mails'] ),
	array( 'The link you followed has expired.', array(), array() ) );

// The nonce is keyed to the account, so a link taken from one row is no use on another: the
// handler's own action alone, which every row's link was signed with before, is refused, and so is
// Bruno's link pointed at Cleo's account.
manager( 35 );
request( array_merge( $bruno_link[1], array( '_wpnonce' => wp_create_nonce( WPCPM_Mentors::ACTION_INVITE ) ) ) );
$unkeyed = run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);
$unkeyed_asked = $GLOBALS['nonce_checks'];
manager( 36 );
request( array_merge( $bruno_link[1], array( 'user' => '13' ) ) );
$borrowed = run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);

ck( 'the link verifies only under the nonce keyed to its account: the handler\'s unkeyed nonce is refused, and so is Bruno\'s nonce on Cleo\'s account, each before anything is sent',
	array( $unkeyed['died'], $unkeyed_asked, $borrowed['died'], $GLOBALS['nonce_checks'], $GLOBALS['mails'] ),
	array( 'The link you followed has expired.', array( WPCPM_Mentors::ACTION_INVITE . '_12' ), 'The link you followed has expired.', array( WPCPM_Mentors::ACTION_INVITE . '_13' ), array() ) );

// A link to an account nobody holds, as a row left open while the account went: the send fails,
// and the Accounts tab, which prints no sync error, says what failed without pointing below.
manager( 44 );
request( array_merge( $bruno_link[1], array( 'user' => '999', '_wpnonce' => wp_create_nonce( WPCPM_Mentors::ACTION_INVITE . '_999' ) ) ) );
$failed = run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);

ck( 'a row\'s send that fails comes back to the Accounts tab, which says the invitation could not be sent, and sends nothing',
	array( link_of( $failed['redirect'] ), html_of( notice_now() ), $GLOBALS['mails'] ),
	array( $accounts, '<div class="notice notice-error is-dismissible"><p>The invitation could not be sent.</p></div>', array() ) );

echo "\n=== A row's invitation comes back to the list's page ===\n";

// Three never invited whose names hold "Norte", one invited among them, and one never invited
// elsewhere; a page a row, so the view, the search and the sort by the most students leave three
// pages, and the second holds Hal.
reset_site();
mentor( 41, 'Gia Norte', 'gnorte', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );
mentor( 42, 'Hal Norte', 'hnorte', array( WPCPM_Roles::ROLE_MENTOR ), 2, 0, 1 );
mentor( 43, 'Ivo Norte', 'inorte', array( WPCPM_Roles::ROLE_MENTOR ), 3, 0, 1 );
mentor( 44, 'Jo Norte', 'jnorte', array( WPCPM_Roles::ROLE_MENTOR ), 4, 0, 1, 1700000000 );
mentor( 45, 'Kai Sur', 'ksur', array( WPCPM_Roles::ROLE_MENTOR ), 5, 0, 1 );
manager( 260 );
$GLOBALS['umeta'][260]['wpcpm_mentors_per_page'] = 1;

$place   = array(
	'wpcpm_view' => 'never-invited',
	's'          => 'norte',
	'orderby'    => 'students',
	'order'      => 'desc',
	'paged'      => '2',
);
$on_page = html_of( draw( screen( $place ) ) );
$hal     = link_of( actions_in( row_for( $on_page, 42 ) )['invite'] ?? '' );
$carried = array();

foreach ( $place as $key => $value ) {
	$carried[ $key ] = arg( $hal[1], $key );
}

ck( 'the second page of the Never invited view, searched and sorted by the most students, is Hal\'s row', row_ids( $on_page ), array( 42 ) );
ck( 'his Send invite carries where the list stands, beside the handler\'s own action, account, tab and nonce',
	array( $carried, $hal[0], arg( $hal[1], 'action' ), arg( $hal[1], 'user' ), arg( $hal[1], 'wpcpm_tab' ), arg( $hal[1], '_wpnonce' ) ),
	array( $place, 'https://example.test/wp-admin/admin-post.php', WPCPM_Mentors::ACTION_INVITE, '42', 'accounts', wp_create_nonce( WPCPM_Mentors::ACTION_INVITE . '_42' ) ) );

request( $hal[1] );
$back    = run(
	function () {
		( new WPCPM_Mentors() )->handle_invite();
	}
);
$landing = link_of( $back['redirect'] );
$landed  = html_of( page_for( $landing[1] ) );

ck( 'followed, it sends Hal his invitation and comes back to that page of that list, as a press on the ticked accounts does',
	array( $GLOBALS['mails'], $landing ),
	array( array( 42 ), array( 'https://example.test/wp-admin/admin.php', array_merge( array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts' ), $place ) ) ) );
ck( 'where the Accounts tab says the email went, above the same list, two now that Hal has left the view, on its second page, Gia\'s',
	array( shown_tab( $landed ), notices_in( $landed ), displaying( $landed ), row_ids( $landed ) ),
	array( 'Accounts', '<div class="notice notice-success is-dismissible"><p>Invitation email sent. It replaces the link in any earlier invitation, so ask them to use this newest email.</p></div>', '2 items', array( 41 ) ) );

unset( $GLOBALS['umeta'][260]['wpcpm_mentors_per_page'] );

echo "\n=== The screen's wiring: the load hook, the rows-per-page save, the names ===\n";

reset_site();
$GLOBALS['hooks'] = array();
$module           = new WPCPM_Mentors();
run(
	function () use ( $module ) {
		$module->boot();
	}
);

$on_menu = array();
foreach ( isset( $GLOBALS['hooks']['admin_menu'] ) ? $GLOBALS['hooks']['admin_menu'] : array() as $added ) {
	$on_menu[] = array( is_array( $added[0] ) ? ( is_object( $added[0][0] ) ? get_class( $added[0][0] ) : $added[0][0] ) . '::' . $added[0][1] : 'another callback', $added[1] );
}

ck( 'at boot, the module hooks its screen to the menu, after the menu is built', $on_menu, array( array( 'WPCPM_Mentors::hook_screen', 11 ) ) );

// What add_menu_page() leaves for the pages under the menu: the name their hooks are filed under.
$GLOBALS['admin_page_hooks'] = array( WPCPM_Admin::MENU_SLUG => 'wpcredits-program' );
foreach ( isset( $GLOBALS['hooks']['admin_menu'] ) ? $GLOBALS['hooks']['admin_menu'] : array() as $added ) {
	call_user_func( $added[0] );
}
$on_load = isset( $GLOBALS['hooks']['load-wpcredits-program_page_wpcpm-mentors'] ) ? $GLOBALS['hooks']['load-wpcredits-program_page_wpcpm-mentors'] : array();

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
	array_keys( $GLOBALS['hooks'] ), array( 'load-programa-wpcredits_page_wpcpm-mentors' ) );

$GLOBALS['hooks'] = array();
run(
	function () use ( $module ) {
		$module->boot();
	}
);

$saved = array();
foreach ( array( '50', '0', '1000' ) as $value ) {
	$saved[ $value ] = apply_filters( 'set_screen_option_wpcpm_mentors_per_page', false, 'wpcpm_mentors_per_page', $value );
}
$save_hook = isset( $GLOBALS['hooks']['set_screen_option_wpcpm_mentors_per_page'][0] ) ? $GLOBALS['hooks']['set_screen_option_wpcpm_mentors_per_page'][0] : array( null, 0, 0 );

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
		return array( WPCPM_Mentors::PER_PAGE_OPTION, WPCPM_Mentors_Table::per_page_option(), WPCPM_Mentors::TAB_ACCOUNTS, WPCPM_Accounts_Table::TAB, WPCPM_Mentors_Table::bulk_nonce_action(), WPCPM_Mentors::FLASH_DETAIL );
	}
);

ck( 'the module\'s names for the option and the tab are the table\'s, the bulk nonce is the Mentors\', and the count travels on a channel of its own',
	got(
		$names,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'wpcpm_mentors_per_page', 'wpcpm_mentors_per_page', 'accounts', 'accounts', 'bulk-mentors', 'mentors_admin_detail' ) );

// The role and the stamp the invitations read are the table's own: the module names its table
// (`table_class()`), and the plumbing every accounts screen shares reads both from it, so neither is
// kept in a literal of the module's own. The stamp's name is left written once in the module, in the
// uninstall's list of keys, where no table is loaded. Read from the module's code and the shared
// plumbing's with their comments left out, so a docblock naming either is not read as a use of it.
reset_site();
mentor( 12, 'Bruno Diaz', 'bdiaz', array( WPCPM_Roles::ROLE_MENTOR ), 1, 0, 1 );
$stamps      = run(
	function () {
		WPCPM_Mentors_Sync::send_invite( 12 );

		return array( WPCPM_Mentors_Table::role(), WPCPM_Mentors_Table::invite_meta(), array_keys( $GLOBALS['umeta'][12] ) );
	}
);
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
$module_code = $code_of( 'includes/modules/class-wpcpm-mentors.php' );
$screen_code = $code_of( 'includes/modules/trait-wpcpm-accounts-screen.php' );
$named       = run(
	function () {
		$method = new ReflectionMethod( 'WPCPM_Mentors', 'table_class' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null );
	}
);
$named_table = got(
	$named,
	function ( $out ) {
		return $out['value'];
	}
);

ck( 'the Mentor accounts table says which role and which stamp it reads, the stamp a row\'s send writes, and the module\'s invitations read them there, holding neither as a literal of their own',
	array(
		got(
			$stamps,
			function ( $out ) {
				return array( $out['value'][0], $out['value'][1], in_array( $out['value'][1], $out['value'][2], true ) );
			}
		),
		substr_count( $module_code . $screen_code, "'wpcpm_mentor_invited'" ),
		substr_count( $module_code . $screen_code, 'WPCPM_Roles::ROLE_MENTOR' ),
		$named_table,
		substr_count( $screen_code, 'WPCPM_Mail::never_invited( $table_class::role(), $table_class::invite_meta() )' ) > 0,
	),
	array( array( WPCPM_Roles::ROLE_MENTOR, 'wpcpm_mentor_invited', true ), 1, 1, 'WPCPM_Mentors_Table', true ) );
ck( 'the list queues through the accounts base, called on the Mentor accounts table, and the one queueing the module does itself is the invitations card\'s button',
	array(
		$named_table,
		substr_count( $module_code . $screen_code, '$table_class::queue_ticked(' ),
		substr_count( $module_code . $screen_code, 'WPCPM_Mail::queue_invites(' ),
		substr_count( $module_code . $screen_code, 'WPCPM_Mail::queue_invite(' ),
		substr_count( $module_code . $screen_code, 'WPCPM_Mail::may_invite(' ),
	),
	array( 'WPCPM_Mentors_Table', 1, 1, 0, 0 ) );

$selected = run(
	function () {
		return ( new ReflectionMethod( 'WPCPM_Mentors', 'invite_selected' ) )->isPrivate();
	}
);

ck( 'the call is the module\'s own, reached from the list\'s form once its nonce is checked, and nothing outside can hand it accounts',
	got(
		$selected,
		function ( $out ) {
			return $out['value'];
		}
	),
	true );

echo "\n=== The notices the card's own button and a stray count leave ===\n";

three_mentors();
manager( 50 );
WPCPM_Flash::set( 'mentors_admin', 'invites-queued' );

ck( 'the invitations card\'s button keeps its sentence',
	html_of( notice_now() ),
	'<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' );

manager( 51 );
WPCPM_Flash::set( 'mentors_admin', 'invites-queued' );
WPCPM_Flash::set(
	'mentors_admin_detail',
	array(
		'status' => 'invites-none',
		'resend' => false,
		'why'    => 'none-selected',
	)
);

ck( 'a count carried for another outcome is not printed under this one',
	html_of( notice_now() ),
	'<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' );

three_mentors();
manager( 52 );
WPCPM_Flash::set(
	'mentors_admin_detail',
	array(
		'status' => 'invites-queued',
		'resend' => false,
		'queued' => 7,
	)
);
request( array( 'action' => WPCPM_Mentors::ACTION_BULK ), array( '_wpnonce' => wp_create_nonce( WPCPM_Mentors::ACTION_BULK ) ) );
run(
	function () {
		( new WPCPM_Mentors() )->handle_bulk_invite();
	}
);

ck( 'a count a press on the ticked accounts left unprinted is not shown under the card\'s own button, which queues its two',
	array( WPCPM_Mail::queue(), html_of( notice_now() ) ),
	array( array( 12, 13 ), '<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

echo "\n=== The screen in two tabs, Accounts and Sync, in the bar every audience screen prints ===\n";

three_mentors();
manager( 200 );

$home     = 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors';
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
	'from the menu'                    => array( 'page' => 'wpcpm-mentors' ),
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
	has( html_of( $drawn['asked for a tab it does not have'] ), '<h2>Mentor accounts <span class="wpcpm-count">' ),
	true );

$printed_bar = run(
	function () {
		ob_start();

		try {
			WPCPM_Screen_Tabs::render(
				'wpcpm-mentors',
				array(
					'accounts' => 'Accounts',
					'sync'     => 'Sync',
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

ck( 'the bar is the one every audience screen prints, `WPCPM_Screen_Tabs`\'s, tag for tag: the screen\'s one bar, right under its title and lede, and no heading holds it',
	array( '' !== html_of( $printed_bar ) && has( $menu_page, html_of( $printed_bar ) ), substr_count( $menu_page, 'nav-tab-wrapper' ), preg_match( '#<h[1-6][^>]*nav-tab-wrapper#', $menu_page ), has( $menu_page, '</p><nav class="nav-tab-wrapper wp-clearfix"' ) ),
	array( true, 1, 0, true ) );

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
		return array( WPCPM_Mentors::TABS, WPCPM_Mentors::TAB_ACCOUNTS, WPCPM_Mentors::TAB_SYNC, WPCPM_Mentors::TAB_FIELD, WPCPM_Accounts_Table::TAB_FIELD );
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
	request( null === $value ? array( 'page' => 'wpcpm-mentors' ) : array( 'page' => 'wpcpm-mentors', 'tab' => $value ) );
	$read[ $case ] = got(
		run(
			function () {
				return WPCPM_Mentors::tab();
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

// Ada's students carry an institution the sync has not turned into a name yet, which the Sync tab
// warns about, since a sync is what fills it in.
three_mentors();
manager( 201 );
$GLOBALS['umeta'][11][ WPCPM_Mentors_Sync::META_MENTEES ] = array( array( 'name' => 'Sam Student', 'institution' => 'recINST0000000001' ) );
WPCPM_Settings::$connected                                = false;
update_option( WPCPM_Mentors_Sync::OPT_ERROR, 'Airtable answered 401.' );
update_option( WPCPM_Mentors_Sync::OPT_REPORT, array( 'stats' => array( 'mentors_seen' => 3 ), 'notices' => array() ) );
$accounts_page             = html_of( page_for( screen() ) );
$sync_page                 = html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) );
WPCPM_Settings::$connected = true;

$parts_of = function ( $html ) {
	return array(
		'warning'     => has( $html, 'Airtable is not connected yet, so no mentors can be synced.' ),
		'invitations' => has( $html, '<div class="wpcpm-card wpcpm-invites">' ),
		'list'        => has( $html, '<h2>Mentor accounts <span class="wpcpm-count">' ),
		'error'       => has( $html, '<strong>Last sync error:</strong> Airtable answered 401.' ),
		'names'       => has( $html, '<strong>Institution and team names have not been read yet.</strong>' ),
		'sync'        => has( $html, '<h2>Airtable sync</h2>' ),
		'report'      => has( $html, '<h2>Last sync report</h2>' ),
	);
};

ck( 'the Accounts tab holds the invitations card and the Mentor accounts list, under the warning that Airtable is not connected, and not the sync card, its last error, the names still unread or its last report',
	$parts_of( $accounts_page ),
	array(
		'warning'     => true,
		'invitations' => true,
		'list'        => true,
		'error'       => false,
		'names'       => false,
		'sync'        => false,
		'report'      => false,
	) );
ck( 'the Sync tab the reverse: the warning, the last sync error, the names still unread, which ask for a sync, the Airtable sync card and the last sync report, and neither the invitations card nor the list',
	$parts_of( $sync_page ),
	array(
		'warning'     => true,
		'invitations' => false,
		'list'        => false,
		'error'       => true,
		'names'       => true,
		'sync'        => true,
		'report'      => true,
	) );

// Each tab's parts in the order a manager reads them: what the last press did, then why no more
// accounts arrive while Airtable is not connected, then the tab's own cards. A part the tab does not
// hold is named, so a tab missing one cannot pass as being in order.
$in_order = function ( $html, array $parts ) {
	$at = array();

	foreach ( $parts as $name => $needle ) {
		$found = strpos( (string) $html, $needle );

		if ( false === $found ) {
			return 'missing: ' . $name;
		}

		$at[ $name ] = $found;
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
	update_option( WPCPM_Mentors_Sync::OPT_ERROR, 'Airtable answered 401.' );
	update_option( WPCPM_Mentors_Sync::OPT_REPORT, array( 'stats' => array( 'mentors_seen' => 3 ), 'notices' => array() ) );
	WPCPM_Flash::set( 'mentors_admin', $press[1] );
	$ordered[ $tab ]           = html_of( page_for( screen( array( 'tab' => $tab ) ) ) );
	WPCPM_Settings::$connected = true;
}

ck( 'the Accounts tab reads top to bottom: the press\'s notice, the warning, the invitations card, the list',
	$in_order(
		$ordered['accounts'],
		array(
			'notice'      => '<div class="notice notice-success is-dismissible">',
			'warning'     => 'Airtable is not connected yet, so no mentors can be synced.',
			'invitations' => '<div class="wpcpm-card wpcpm-invites">',
			'list'        => '<h2>Mentor accounts <span class="wpcpm-count">',
		)
	),
	array( 'notice', 'warning', 'invitations', 'list' ) );
ck( 'and the Sync tab: the press\'s notice, the warning, the last sync error, the names still unread, the Airtable sync card, the last sync report',
	$in_order(
		$ordered['sync'],
		array(
			'notice'  => '<div class="notice notice-info is-dismissible">',
			'warning' => 'Airtable is not connected yet, so no mentors can be synced.',
			'error'   => '<strong>Last sync error:</strong>',
			'names'   => '<strong>Institution and team names have not been read yet.</strong>',
			'sync'    => '<h2>Airtable sync</h2>',
			'report'  => '<h2>Last sync report</h2>',
		)
	),
	array( 'notice', 'warning', 'error', 'names', 'sync', 'report' ) );

manager( 202 );

$connected_accounts = html_of( page_for( screen() ) );
$connected_sync     = html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) );

ck( 'with Airtable connected, neither tab warns',
	array( shown_tab( $connected_accounts ), has( $connected_accounts, 'Airtable is not connected yet' ), shown_tab( $connected_sync ), has( $connected_sync, 'Airtable is not connected yet' ) ),
	array( 'Accounts', false, 'Sync', false ) );

echo "\n=== Every form names the tab it is on ===\n";

three_mentors();
manager( 203 );
$accounts_forms = forms_by_action( html_of( page_for( screen() ) ) );
$sync_forms     = forms_by_action( html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) ) );
update_option(
	WPCPM_Mentors_Sync::OPT_STATE,
	array(
		'phase'   => 'mentors',
		'started' => time(),
		'touched' => time(),
	)
);
$running_forms = forms_by_action( html_of( page_for( screen( array( 'tab' => 'sync' ) ) ) ) );
delete_option( WPCPM_Mentors_Sync::OPT_STATE );

ck( 'the Sync tab\'s one form, Sync mentors now, names the Sync tab, and so does Cancel sync, its one form while a run is on',
	array( array_map( 'tab_field_of', $sync_forms ), array_map( 'tab_field_of', $running_forms ) ),
	array( array( WPCPM_Mentors::ACTION_SYNC => 'sync' ), array( WPCPM_Mentors::ACTION_CANCEL => 'sync' ) ) );
ck( 'the invitations card\'s button names the Accounts tab; the list is a form to the screen itself, whose fields are the Accounts tab\'s address',
	array(
		array_map( 'tab_field_of', $accounts_forms ),
		has( isset( $accounts_forms['list'] ) ? $accounts_forms['list'] : '', '<input type="hidden" name="tab" value="accounts" />' ),
	),
	array(
		array(
			WPCPM_Mentors::ACTION_BULK => 'accounts',
			'list'                     => null,
		),
		true,
	) );

echo "\n=== A press comes back to the tab it was made on ===\n";

$to_sync     = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors', 'tab' => 'sync' ) );
$to_accounts = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts' ) );
$to_screen   = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors' ) );

three_mentors();
manager( 210 );
$started = post_to( 'handle_sync', WPCPM_Mentors::ACTION_SYNC, array( 'wpcpm_tab' => 'sync' ) );
$landing = link_of( $started['redirect'] );
$landed  = html_of( page_for( $landing[1] ) );

ck( 'Sync mentors now, pressed on the Sync tab, starts the run and comes back to the Sync tab',
	array( $landing, WPCPM_Mentors_Sync::is_running() ),
	array( $to_sync, true ) );
ck( 'the tab drawn there, which says the run started, above the progress it points to',
	array( shown_tab( $landed ), notices_in( $landed ), has( $landed, 'data-wpcpm-progress' ) ),
	array( 'Sync', '<div class="notice notice-success is-dismissible"><p>Sync started. Progress is shown below and updates as it runs.</p></div>', true ) );

manager( 211 );
update_option(
	WPCPM_Mentors_Sync::OPT_STATE,
	array(
		'phase'   => 'mentors',
		'started' => time(),
		'touched' => time(),
	)
);
$cancelled = post_to( 'handle_cancel', WPCPM_Mentors::ACTION_CANCEL, array( 'wpcpm_tab' => 'sync' ) );
$landing   = link_of( $cancelled['redirect'] );

ck( 'Cancel sync the same: the run stops, the press comes back to the Sync tab, and the tab says so',
	array( $landing, WPCPM_Mentors_Sync::is_running(), notices_in( html_of( page_for( $landing[1] ) ) ) ),
	array( $to_sync, false, '<div class="notice notice-info is-dismissible"><p>Sync canceled.</p></div>' ) );

manager( 212 );
WPCPM_Settings::$connected = false;
$refused                   = post_to( 'handle_sync', WPCPM_Mentors::ACTION_SYNC, array( 'wpcpm_tab' => 'sync' ) );
$landing                   = link_of( $refused['redirect'] );
$landed                    = html_of( page_for( $landing[1] ) );
WPCPM_Settings::$connected = true;

$said  = strpos( $landed, 'That action could not be completed. See the error below.' );
$below = strpos( $landed, '<strong>Last sync error:</strong> Add an Airtable Personal Access Token and Base ID before syncing.' );

ck( 'a start that fails comes back to the Sync tab too, whose notice points to the error, printed below it',
	array( $landing, notices_in( $landed ), false !== $said && false !== $below && $said < $below ),
	array( $to_sync, '<div class="notice notice-error is-dismissible"><p>That action could not be completed. See the error below.</p></div>', true ) );

manager( 213 );
$card    = post_to( 'handle_bulk_invite', WPCPM_Mentors::ACTION_BULK, array( 'wpcpm_tab' => 'accounts' ) );
$landing = link_of( $card['redirect'] );

ck( 'the invitations card\'s button, pressed on the Accounts tab, queues the two never invited and comes back to the Accounts tab, which says so',
	array( $landing, WPCPM_Mail::queue(), notices_in( html_of( page_for( $landing[1] ) ) ) ),
	array( $to_accounts, array( 12, 13 ), '<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

$elsewhere = array();
foreach ( array(
	'a tab the screen does not have' => 'nope',
	'a tab and more'                 => 'sync&tab=accounts',
	'an empty tab'                   => '',
	'no tab'                         => null,
) as $case => $tab ) {
	manager( 214 + count( $elsewhere ) );
	$out                = post_to( 'handle_cancel', WPCPM_Mentors::ACTION_CANCEL, null === $tab ? array() : array( 'wpcpm_tab' => $tab ) );
	$elsewhere[ $case ] = link_of( $out['redirect'] );
}

ck( 'a form that names no tab, or one the screen does not have, comes back to the screen\'s own address, which shows Accounts',
	$elsewhere,
	array_fill_keys( array( 'a tab the screen does not have', 'a tab and more', 'an empty tab', 'no tab' ), $to_screen ) );

three_mentors();
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

// The press is the manager's next request. WPCPM_Flash remembers within one run of PHP what it took
// for a person, and drawing the tab above took this one's, so the press is made by somebody new,
// the queue and the run it stops left as the page found them.
$GLOBALS['uid'] = 224;
$stopped        = press_form( $stop_form, array( 'WPCPM_Mail', 'handle_stop' ) );
$stopped_page   = html_of( page_for( link_of( $stopped['redirect'] )[1] ) );

ck( 'the invitations card is handed the flash channel the Mentors screen reads its outcomes from, which its Stop names; pressed, Stop cancels the rest, and the Accounts tab it comes back to says sending stopped',
	array( arg( hidden_fields_of( $stop_form ), 'wpcpm_flash' ), WPCPM_Mail::queue(), WPCPM_Mail::run(), shown_tab( $stopped_page ), notices_in( $stopped_page ) ),
	array( 'mentors_admin', array(), array(), 'Accounts', '<div class="notice notice-info is-dismissible"><p>Sending stopped. Invitations already sent cannot be recalled.</p></div>' ) );

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

ck( 'the invitations card\'s Stop and Dismiss, the mail layer\'s own forms, name no tab and need none: each comes back to the address it was pressed on, the Accounts tab',
	array( link_of( $stopped['redirect'] ), link_of( $dismissed['redirect'] ) ),
	array(
		array( '/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts' ) ),
		array( '/wp-admin/admin.php', array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts' ) ),
	) );

echo "\n=== Each tab prints the notices of the presses made on it ===\n";

three_mentors();
$printed = array();
$person  = 230; // Not $uid, which at the top level is the global the stand-ins read as the person looking.

foreach ( array( 'started', 'cancelled', 'error', 'invited', 'invite-too-soon', 'invites-queued', 'invites-none', 'invites-stopped' ) as $status ) {
	foreach ( array( 'accounts', 'sync' ) as $tab ) {
		manager( $person++ );
		WPCPM_Flash::set( 'mentors_admin', $status );
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
WPCPM_Flash::set( 'mentors_admin', 'started' );
$wrong = notices_in( html_of( page_for( screen() ) ) );
$left  = get_user_meta( 250, WPCPM_Flash::META, true );

ck( 'an outcome that comes back to the other tab, as a form drawn before the screen had tabs sends it, is taken there unprinted, so no later page shows it as news',
	array( $wrong, is_array( $left ) && array_key_exists( 'mentors_admin', $left ) ),
	array( '', false ) );

echo "\n";
ck( 'and nothing asked a stand-in for anything it does not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
