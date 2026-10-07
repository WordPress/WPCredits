<?php
/**
 * The Sponsors screen's Accounts tab: every sponsor account as a WordPress list table, searched,
 * sorted and paged; a fourth view, No account, of the Approved sponsors that have none yet, where
 * the missing accounts are created; and the invitations sent from the list, which sponsor accounts
 * had none of before.
 *
 * What this pins, and why each part is worth pinning:
 *
 * - The table's columns and their values (Name, Username, Sponsor, Member since, Status), the row
 *   actions under the name (Edit, View page, the Sponsor Dashboard as the account's sponsor, the
 *   invitation, then Manage accounts, the accounts of the sponsor the account is stamped with) and
 *   the views' counts: All, Invited and Never invited count the accounts alone, and No account counts
 *   the Approved sponsors that have none. The list is the role: an account holding the Sponsor role
 *   whose membership ended is listed, and says so.
 * - Sponsor, Member since and Status sort by what the members class writes on each account, and no
 *   query can sort by any of them: the sponsor is a record ID whose name lives in the sponsors
 *   index, the date sits inside a serialized row, and "locked today" is no meta at all. The table
 *   reads every account the list may hold and orders them itself, by the value, then the name, then
 *   the ID, so no account shows on two pages while another is on none.
 * - The search reaches the sponsor's name as well as the username, the email and the name.
 * - No account is the worklist: the Approved sponsors with no live account, ready ones first, each
 *   with its contact address masked and, while it cannot be given an account, why. Create account
 *   is that view's bulk action and a ready row's own action, and that view alone offers it; the
 *   invitations are the account views' alone.
 * - The bulk Create account creates at most ten a press, the ready ones in the view's order, each
 *   decided by the sponsor policy and made by the module's provisioning, as the manager who
 *   pressed; what it says names each sponsor it could not give an account, with the reason. A
 *   row's Create account is a nonce link to the module's provisioning handler, which reads the
 *   record with its case, gives no second account to a sponsor that has one (a double click, or a
 *   link left open while an account was attached), and comes back to the list as it stood; the
 *   form that posts the record is still served, and refused the same way.
 * - The invitations: a row's goes out at once through the members class, refused for an account
 *   that is gone, one without the Sponsor role and one invited inside the gap; the ticked ones are
 *   queued through the accounts base; the card counts the accounts never sent one by any stamp.
 * - The screen's wiring: the load hook on the Accounts tab alone, the rows-per-page option under
 *   the Sponsors' own name and its save, hooked at boot.
 * - Manage accounts, on an account row and on every No account row, opens one sponsor's accounts, a
 *   view of the Accounts tab, with where the list stands, and the view's way back returns to the
 *   list as it stood, whatever the search holds. The load hook builds no list for that view: no
 *   table is made, so Screen Options offers nothing for a list nobody sees, and the view asks for
 *   that one sponsor's accounts and nothing the list reads.
 * - No address is printed anywhere on the Accounts tab while it shows an account view; the No account
 *   view prints a contact address masked, never whole; one sponsor's accounts print each account's
 *   address, which a manager attaches and removes accounts by.
 *
 * WordPress is stood in for as far as the screen reaches, by the stand-ins every accounts screen's
 * suite shares (bin/stubs/accounts-screen.php), with `include` modeled, since this list hands
 * WordPress the IDs its search finds by a sponsor's name. The sponsors index, the members class,
 * the sponsor policy, the module's provisioning, the mail layer, the flash, the return and the tab
 * bar are the plugin's own; Airtable's write, the audit log, the roster's locks, the sponsors sync
 * and the dashboards' pages are stood in for by their contracts. The list table is
 * bin/stubs/class-wp-list-table.php, loaded through this suite's own `wpcpm_load_accounts_tables()`;
 * its markup is close to core's and is not core's. Each step is run, and what it drew is read,
 * through the helpers the screen suites share (bin/stubs/screen-helpers.php).
 *
 * Run from the plugin root:  php bin/test-sponsors-accounts.php
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

// This list hands WordPress the IDs its search finds by a sponsor's name.
$GLOBALS['query_include'] = true;

$GLOBALS['locked']         = array(); // The accounts the roster's ceiling locked today, by ID.
$GLOBALS['locked_reads']   = 0;       // How many times the locked accounts were asked for.
$GLOBALS['page_reads']     = 0;       // How many times the Sponsor Dashboard's address was asked for.
$GLOBALS['inserted']       = array(); // Every account wp_insert_user() was asked to make.
$GLOBALS['insert_fails']   = false;   // Whether WordPress refuses to make the next account.
$GLOBALS['patched']        = array(); // Every write of the Dashboard account checkbox to Airtable.
$GLOBALS['airtable_fails'] = false;   // Whether Airtable refuses the next write.
$GLOBALS['audit']          = array(); // Every sponsor audit row written.
$GLOBALS['sync_invites']   = array(); // Every account the sponsors sync was asked to invite.

/* ---- WordPress, beyond the shared stand-ins ------------------------------ */

function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}
function is_email( $email ) {
	return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : false;
}
function sanitize_email( $email ) {
	return trim( (string) $email );
}
function sanitize_user( $login, $strict = false ) {
	return preg_replace( '/[^a-z0-9 _.\-@]/i', '', (string) $login );
}
function username_exists( $login ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( (string) $login === (string) $user->user_login ) {
			return (int) $user->ID;
		}
	}

	return false;
}
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	return str_repeat( 'x', (int) $length );
}
/**
 * The account WordPress makes for provisioning: the next ID, the role and the address asked for,
 * every call kept; or the refusal a check asks for, as WordPress refuses a login that is taken.
 *
 * @param array $userdata The account's fields.
 * @return int|WP_Error
 */
function wp_insert_user( $userdata ) {
	$GLOBALS['inserted'][] = $userdata;

	if ( $GLOBALS['insert_fails'] ) {
		return new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
	}

	$id   = 100 + count( $GLOBALS['inserted'] );
	$user = new WP_User( $id, $userdata['display_name'], array( $userdata['role'] ), $userdata['user_login'] );

	$user->user_email = $userdata['user_email'];

	$GLOBALS['users'][ $id ] = $user;
	$GLOBALS['umeta'][ $id ] = array();

	return $id;
}
function wp_login_url() {
	return 'https://example.test/wp-login.php';
}
function wp_specialchars_decode( $text, $quotes = ENT_NOQUOTES ) {
	return htmlspecialchars_decode( (string) $text, $quotes );
}
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

/* ---- the plugin's other pieces, stood in for by their contracts ---------- */

/** The settings, as far as the screen reads them: whether Airtable is connected, and the sponsors table's name. */
class WPCPM_Settings {
	public static $connected = true;
	public static $values    = array( 'sponsors_table' => 'tblSPONSORS' );
	public static function is_connected() { return self::$connected; }
	public static function get() { return self::$values; }
	public static function get_value( $key, $fallback = null ) { return isset( self::$values[ $key ] ) ? self::$values[ $key ] : $fallback; }
}

// The shape a sponsor's record ID has, read from the Airtable class itself, so the stand-in below
// matches what the plugin matches.
preg_match( "/const RECORD_ID_PATTERN = '([^']+)';/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php' ), $wpcpm_test_pattern );
define( 'WPCPM_TEST_RECORD_PATTERN', isset( $wpcpm_test_pattern[1] ) ? $wpcpm_test_pattern[1] : '/^no pattern read$/' );

/**
 * Airtable, as far as provisioning reaches it: the record ID's shape and its test, which the class
 * words this way and every module that reads a record ID asks (`WPCPM_Mentors_Sync::is_record_id()`
 * among them), and the write of the Dashboard account checkbox, each kept, refused when a check asks.
 */
class WPCPM_Airtable {
	const RECORD_ID_PATTERN = WPCPM_TEST_RECORD_PATTERN;
	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
	}
	public function update_records( $table, array $records ) {
		$GLOBALS['patched'][] = array( $table, $records );

		return $GLOBALS['airtable_fails'] ? new WP_Error( 'airtable_down', 'Airtable did not answer.' ) : array( $records[0]['id'] => true );
	}
}

/** The Sponsor Dashboard's page, which the list names and a row's View page opens, each ask counted. */
class WPCPM_Sponsors_Dashboard {
	public static $url = 'https://example.test/sponsor-dashboard/';
	public static function page_url() {
		++$GLOBALS['page_reads'];

		return self::$url;
	}
}

/** The Mentor Dashboard's page, which a mentor's invitation names. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-dashboard/'; }
	public static function get_mentee_count( $user_id ) { return 0; }
}

/** The roster's ceiling: the accounts it locked for the rest of today, each ask counted. */
class WPCPM_Sponsor_Roster {
	const ARG_VIEW = 'wpcpm_sponsor_view';
	public static function locked_today() {
		++$GLOBALS['locked_reads'];

		$locked = array();

		foreach ( $GLOBALS['locked'] as $id ) {
			if ( isset( $GLOBALS['users'][ $id ] ) ) {
				$locked[] = $GLOBALS['users'][ $id ];
			}
		}

		return $locked;
	}
}

/**
 * The sponsors sync, as far as the module's boot reaches it, and a `send_invite()` the real class
 * does not have: the way the accounts screen's own default sends one account its invitation (the
 * module's sync), kept, so a check can tell the Sponsors module's own way from that default.
 */
class WPCPM_Sponsors_Sync {
	public static function register_cron() {}
	public static function send_invite( $user_id ) {
		$GLOBALS['sync_invites'][] = (int) $user_id;

		return true;
	}
}

/** An institution's membership, which the members class refuses a sponsor's account for: read from the fixture's stamps. */
class WPCPM_Institution_Members {
	const META_RECORD_ID = 'wpcpm_institution_record_id';
	const META_ACTIVE    = 'wpcpm_institution_active';
	public static function institution_of( $user = null ) {
		$id   = $user instanceof WP_User ? (int) $user->ID : (int) $user;
		$meta = isset( $GLOBALS['umeta'][ $id ] ) ? $GLOBALS['umeta'][ $id ] : array();

		return ( isset( $meta[ self::META_RECORD_ID ], $meta[ self::META_ACTIVE ] ) && 1 === (int) $meta[ self::META_ACTIVE ] ) ? (string) $meta[ self::META_RECORD_ID ] : '';
	}
}

/** The sponsor's audit log, each row kept. */
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';
	public static function record_sponsor( array $entry ) {
		$GLOBALS['audit'][] = $entry;

		return count( $GLOBALS['audit'] );
	}
}

/** What the module boots besides its screen. None is drawn here. */
class WPCPM_Ceiling {
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
// The students sync names the stamp a student's account carries, which the members class refuses a
// sponsor's account for, and the mentors sync the record ID's shape the index and the members read.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors.php';
// The Administrator Dashboard's page, which the queue tab, the one the screen opens on, links into.
require_once __DIR__ . '/stubs/administrators-dashboard.php';

// The invitation's template, hooked as the plugin hooks it, so the stand-in for core's invitation
// records the subject each account was sent.
WPCPM_Mail::init();

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, which there is
 * no WordPress here to lend, then the accounts base and the Sponsors table, the second once it
 * exists, so a copy of the plugin without it fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';

	if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-table.php' ) ) {
		require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-table.php';
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
 * The Accounts tab's query, with whatever else a check wants on it.
 *
 * @param array $more More query arguments.
 * @return array
 */
function screen( array $more = array() ) {
	return array_merge(
		array(
			'page' => 'wpcpm-sponsors',
			'tab'  => 'accounts',
		),
		$more
	);
}

/**
 * A method of a class a check reaches that the class keeps to itself.
 *
 * @param string $name  The method.
 * @param string $class The class.
 * @return ReflectionMethod
 */
function reach( $name, $class = 'WPCPM_Sponsors' ) {
	$method = new ReflectionMethod( $class, $name );

	// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method;
}

/**
 * The sponsor accounts card, as the screen draws it for a request.
 *
 * @param array $query The query string.
 * @return array What run() kept; the markup is its value.
 */
function draw( array $query ) {
	request( $query );
	$GLOBALS['queries']      = array();
	$GLOBALS['reads']        = array();
	$GLOBALS['locked_reads'] = 0;
	$GLOBALS['page_reads']   = 0;

	return run(
		function () {
			$method = reach( 'render_accounts_list' );
			ob_start();

			try {
				$method->invoke( new WPCPM_Sponsors() );
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
			$module = new WPCPM_Sponsors();
			$module->load_screen();

			return 'drawn';
		}
	);
}

/**
 * The Sponsors screen as WordPress draws it for a request: the screen's load hook, then the page,
 * from the one module.
 *
 * @param array $query The query string.
 * @return array What run() kept; the markup is its value.
 */
function page_for( array $query ) {
	request( $query );
	$GLOBALS['queries']        = array();
	$GLOBALS['current_screen'] = new WPCPM_Stub_Screen();
	$GLOBALS['locked_reads']   = 0;

	return run(
		function () {
			$module = new WPCPM_Sponsors();
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
 * The notice the Accounts tab prints for what the last press left, for the person looking, with
 * the list as the press left it.
 *
 * @param array $more The list's view, search, sort or page.
 * @return string
 */
function notice_now( array $more = array() ) {
	return notices_in( html_of( page_for( screen( $more ) ) ) );
}

/**
 * The order some parts of a drawn page come in, by their names, or the first one missing.
 *
 * @param string $html  Markup.
 * @param array  $parts Name => what to find.
 * @return string[]|string The names, in the order the parts are drawn, or `missing: <name>`.
 */
function in_order( $html, array $parts ) {
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
}

/**
 * Where a sponsor's accounts lead back to: the address of their "Back to the accounts" and its query
 * arguments, entities decoded.
 *
 * @param string $html Markup.
 * @return array{0: string, 1: array}
 */
function back_of( $html ) {
	return link_of( preg_match( '#<a href="([^"]*)">Back to the accounts</a>#', (string) $html, $found ) ? $found[1] : '' );
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
			( new WPCPM_Sponsors() )->$method();
		}
	);
}

/**
 * A link drawn on the list followed as a browser follows it, to the handler its action names.
 *
 * @param string $link    The link's markup, or its address.
 * @param string $handler The module's handler.
 * @return array What run() kept.
 */
function follow( $link, $handler ) {
	request( link_of( $link )[1] );

	return run(
		function () use ( $handler ) {
			( new WPCPM_Sponsors() )->$handler();
		}
	);
}

/**
 * A fresh program manager for a press, an account on the site, as the sponsor policy needs: it
 * decides for the person signed in, and refuses one it cannot find. WPCPM_Flash remembers what it
 * took for a person within one run of PHP, so each press that leaves a notice is made by somebody
 * new. The invitation queue and its run go with the last press; the site's own options stay.
 *
 * @param int $uid User ID.
 */
function manager( $uid ) {
	$GLOBALS['uid']          = (int) $uid;
	$GLOBALS['caps']         = true;
	$GLOBALS['nonce_checks'] = array();
	$GLOBALS['reads']        = array();
	$GLOBALS['mails']        = array();
	$GLOBALS['sent']         = array();
	$GLOBALS['scheduled']    = array();
	$GLOBALS['inserted']     = array();
	$GLOBALS['patched']      = array();
	$GLOBALS['audit']        = array();
	$GLOBALS['sync_invites'] = array();
	$GLOBALS['insert_fails'] = false;
	$GLOBALS['locked_reads'] = 0;

	if ( ! isset( $GLOBALS['users'][ (int) $uid ] ) ) {
		seat( (int) $uid, 'Manager ' . (int) $uid, 'manager' . (int) $uid, array( 'administrator' ) );
	}

	unset( $GLOBALS['opts'][ WPCPM_Mail::QUEUE_OPTION ], $GLOBALS['opts'][ WPCPM_Mail::RUN_OPTION ] );
}

/**
 * The record IDs a drawn table's rows hold, from the record ID each prints under its name in the
 * Sponsor column, in order: a row that is not ready draws no checkbox to read one from.
 *
 * @param string $html Markup.
 * @return string[]
 */
function record_ids( $html ) {
	preg_match_all( '/<code class="wpcpm-inst-record">([^<]*)<\/code>/', between( $html, '<tbody', '</tbody>' ), $found );

	return $found[1];
}

/**
 * One row of a drawn table, by its checkbox's HTML id.
 *
 * @param string $html Markup.
 * @param string $id   The checkbox's HTML id.
 * @return string
 */
function row_with( $html, $id ) {
	foreach ( array_slice( preg_split( '/<tr[ >]/', between( $html, '<tbody', '</tbody>' ) ), 1 ) as $row ) {
		if ( has( $row, 'id="' . $id . '"' ) ) {
			return $row;
		}
	}

	return '';
}
function row_for( $html, $id ) {
	return row_with( $html, 'wpcpm-account-' . (int) $id );
}

/**
 * One record row of a drawn table, by the record ID it prints in its Sponsor cell, which every
 * record row prints, ready or not.
 *
 * @param string $html   Markup.
 * @param string $record The record ID.
 * @return string
 */
function record_row( $html, $record ) {
	foreach ( array_slice( preg_split( '/<tr[ >]/', between( $html, '<tbody', '</tbody>' ) ), 1 ) as $row ) {
		if ( has( $row, '<code class="wpcpm-inst-record">' . $record . '</code>' ) ) {
			return $row;
		}
	}

	return '';
}

function heading_count( $html ) {
	return preg_match( '/<h2>Sponsor accounts <span class="wpcpm-count">([^<]*)<\/span><\/h2>/', (string) $html, $found ) ? $found[1] : null;
}

/**
 * The bulk actions a drawn list offers, by value.
 *
 * @param string $html Markup.
 * @return string[]
 */
function bulk_of( $html ) {
	preg_match_all( '/<option value="([a-z-]+)">/', between( (string) $html, '<select name="action"', '</select>' ), $found );

	return $found[1];
}

/**
 * The queries a drawn list made, by what each was for.
 *
 * @param string $kind `page` (the rows drawn), `every` (a read of every account the list may hold,
 *                     whole), `count` (a view's count), `search` (the IDs a search finds) or `live`
 *                     (the live accounts, by the membership's flag, which the No account view reads).
 * @return array[]
 */
function queries( $kind ) {
	return array_values(
		array_filter(
			$GLOBALS['queries'],
			function ( $args ) use ( $kind ) {
				$ids    = isset( $args['fields'] ) && 'ID' === $args['fields'];
				$number = isset( $args['number'] ) ? (int) $args['number'] : 0;
				$role   = isset( $args['role'] ) && WPCPM_Roles::ROLE_SPONSOR === $args['role'];

				switch ( $kind ) {
					case 'page':
						return $role && ! $ids && $number > 0;
					case 'every':
						return $role && ! $ids && -1 === $number;
					case 'count':
						return $ids && 1 === $number;
					case 'search':
						return $ids && -1 === $number && isset( $args['search'] );
					case 'live':
						return isset( $args['meta_key'] ) && WPCPM_Sponsor_Members::META_ACTIVE === $args['meta_key'];
				}

				return false;
			}
		)
	);
}

/**
 * How many accounts a draw looked up by an address, as the No account view does for each sponsor
 * with a valid contact address.
 *
 * @return int
 */
function address_lookups() {
	return count(
		array_filter(
			$GLOBALS['reads'],
			function ( $read ) {
				return 0 === strpos( (string) $read, 'user ' ) && has( $read, '@' );
			}
		)
	);
}

/* ---- fixtures ------------------------------------------------------------- */

// Seven sponsors, made up. The record IDs run in another order than the names they stand for (Wren's
// B before Birch's Y, Ash's A after Nova's N on the No account view), so a list sorted by the stamp
// rather than by the name it stands for is told apart.
define( 'WREN', 'recSPONBRAVO00001' );
define( 'BIRCH', 'recSPONYANKE00002' );
define( 'MOSS', 'recSPONMIKEX00003' );
define( 'NOVA', 'recSPONNOVEM00004' );
define( 'OPAL', 'recSPONOSCAR00005' );
define( 'ASH', 'recSPONALPHA00006' );
define( 'QUAY', 'recSPONQUEBE00007' );

/**
 * One row of the sponsors index, as the sponsors sync writes it.
 *
 * @param string $name    The name, as Airtable holds it.
 * @param string $status  The status.
 * @param string $email   The Contact Email, or '' for none.
 * @param string $contact The contact person, or '' for none.
 * @return array
 */
function index_row( $name, $status, $email, $contact = '' ) {
	return array(
		'name'           => $name,
		'status'         => $status,
		'contact_email'  => $email,
		'contact_person' => $contact,
	);
}

/**
 * Write the sponsors index through its own class: rows keyed by record ID.
 *
 * @param array[] $rows Record ID => row.
 */
function write_index( array $rows ) {
	WPCPM_Sponsors_Index::write( $rows, 1790000000 );
}

/**
 * Put an account on the site, with what its memberships and invitations wrote on it.
 *
 * @param int      $id    User ID.
 * @param string   $name  Display name.
 * @param string   $login Username.
 * @param string[] $roles Roles.
 * @param array    $meta  User meta, key => value.
 */
function seat( $id, $name, $login, array $roles, array $meta = array() ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, $roles, $login );
	$GLOBALS['umeta'][ $id ] = $meta;
}

/**
 * A membership as the members class writes it: the stamp, the flag and, unless the account is
 * older than the facts, when it began.
 *
 * @param string   $record The sponsor.
 * @param int|null $since  When the membership began, or null for an account the facts predate.
 * @return array
 */
function member_of( $record, $since = null ) {
	$meta = array(
		WPCPM_Sponsor_Members::META_RECORD_ID => $record,
		WPCPM_Sponsor_Members::META_ACTIVE    => 1,
	);

	if ( null !== $since ) {
		$meta[ WPCPM_Sponsor_Members::META_MEMBERSHIP ] = array(
			'since' => $since,
			'by'    => 1,
			'how'   => 'provisioned',
		);
	}

	return $meta;
}

/**
 * The site: seven sponsors, five of them Approved; six sponsor accounts across three sponsors; a
 * mentor, a student and an administrator, none of them on the list.
 *
 * Wren Example Labs is Approved, and Nell and Ben act for it: Nell invited, Ben never invited and
 * locked out of changes today; Ada was its member, her stamp moved to `_was` and her flag 0, and she
 * still holds the Sponsor role: `detach()` takes it away unless the account is a WordPress
 * administrator, so "Membership ended" shows on the list only for an account that kept the role or
 * was given it again. Birch Example Cloud is Approved, and Cleo, a mentor too, invited under both her
 * roles, and Hugo act for it. Moss Example Hosting is Paused, and Finn, an account older than the
 * membership facts, acts for it. The three other Approved sponsors have no account: Nova Example
 * Tools, ready, its contact address nobody's; Opal Example Studio, ready too, its name stored with a
 * space at the end and its contact address Uma's, a mentor's account, which a Create account attaches;
 * Ash Example Analytics, with no contact address. Quay Example Media has no status at all.
 */
function the_site() {
	$GLOBALS['users']          = array();
	$GLOBALS['umeta']          = array();
	$GLOBALS['opts']           = array(
		'date_format' => 'F j, Y',
		'blogname'    => 'WPCredits',
	);
	$GLOBALS['no_editor']      = false;
	$GLOBALS['locked']         = array( 22 );
	$GLOBALS['airtable_fails'] = false;

	WPCPM_Settings::$connected     = true;
	WPCPM_Sponsors_Dashboard::$url = 'https://example.test/sponsor-dashboard/';

	write_index(
		array(
			WREN  => index_row( 'Wren Example Labs', 'Approved', 'team@wren.example', 'Nell Ito' ),
			BIRCH => index_row( 'Birch Example Cloud', 'Approved', 'hello@birch.example' ),
			MOSS  => index_row( 'Moss Example Hosting', 'Paused', 'hi@moss.example' ),
			NOVA  => index_row( 'Nova Example Tools', 'Approved', 'hello@nova.example', 'Nia Example' ),
			OPAL  => index_row( 'Opal Example Studio ', 'Approved', 'uross@example.test' ),
			ASH   => index_row( 'Ash Example Analytics', 'Approved', '' ),
			QUAY  => index_row( 'Quay Example Media', '', 'hi@quay.example' ),
		)
	);

	seat( 21, 'Nell Ito', 'nito', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( WREN, 1767225600 ) + array( 'wpcpm_sponsor_invited' => 1768000000 ) );
	seat( 22, 'Ben Okoye', 'bokoye', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( WREN, 1772323200 ) );
	seat( 23, 'Cleo Park', 'cpark', array( WPCPM_Roles::ROLE_SPONSOR, WPCPM_Roles::ROLE_MENTOR ), member_of( BIRCH, 1769904000 ) + array( 'wpcpm_mentor_invited' => 1770000000, 'wpcpm_sponsor_invited' => 1770000000 ) );
	seat( 24, 'Hugo Rao', 'hrao', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( BIRCH, 1775001600 ) );
	seat(
		25,
		'Ada Lund',
		'alund',
		array( WPCPM_Roles::ROLE_SPONSOR ),
		array(
			WPCPM_Sponsor_Members::META_RECORD_ID_WAS => WREN,
			WPCPM_Sponsor_Members::META_ACTIVE        => 0,
			WPCPM_Sponsor_Members::META_MEMBERSHIP    => array(
				'since' => 1764547200,
				'by'    => 1,
				'how'   => 'manager',
			),
			'wpcpm_sponsor_invited'                   => 1765000000,
		)
	);
	seat( 26, 'Finn Hale', 'fhale', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( MOSS ) );
	seat( 30, 'Sam Student', 'sstudent', array( WPCPM_Roles::ROLE_STUDENT ), array( 'wpcpm_student_record_id' => 'recSTUDENT0000001' ) );
	seat( 31, 'Pat Admin', 'padmin', array( 'administrator' ) );
	seat( 32, 'Uma Ross', 'uross', array( WPCPM_Roles::ROLE_MENTOR ) );

	manager( 1 );
}

// The request that saves a rows-per-page choice, as WordPress makes it: the module booted on
// `plugins_loaded`, then the save's filter applied in `set_screen_options()`, before the menu, the
// screen's load hook or any table exists. In a process of its own, because the checks below declare
// the tables, and a class once declared cannot be undeclared.
if ( isset( $argv[1] ) && 'child-save' === $argv[1] ) {
	$declared = class_exists( 'WPCPM_Sponsors_Table', false );
	$saved    = run(
		function () {
			( new WPCPM_Sponsors() )->boot();

			return apply_filters( 'set_screen_option_wpcpm_sponsors_per_page', false, 'wpcpm_sponsors_per_page', '50' );
		}
	);

	echo json_encode(
		array(
			'before' => $declared,
			'saved'  => $saved['value'],
			'error'  => $saved['error'],
			'after'  => class_exists( 'WPCPM_Sponsors_Table', false ),
		)
	);
	exit( 0 );
}

// The invitations card's button as WordPress runs it: admin-post.php, where nothing has loaded the
// list tables the card's reading of the role and the stamp comes from. In a process of its own, for
// the reason the save's run above has one.
if ( isset( $argv[1] ) && 'child-card' === $argv[1] ) {
	the_site();

	$declared = class_exists( 'WPCPM_Sponsors_Table', false );
	$pressed  = post_to( 'handle_bulk_invite', 'wpcpm_sponsors_bulk_invite', array( 'wpcpm_tab' => 'accounts' ) );

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

// The Accounts tab drawn without the screen's load hook, under what a Create account on the ticked
// sponsors left: the screen prints the outcome above the tab, before anything has built the list,
// and the words for it are the Sponsors table's, which nothing has loaded yet. In a process of its
// own, as above.
if ( isset( $argv[1] ) && 'child-notice' === $argv[1] ) {
	the_site();
	manager( 7 );
	WPCPM_Flash::set( 'sponsors_admin', 'provisioned' );
	WPCPM_Flash::set(
		'sponsors_admin_detail',
		array(
			'status'   => 'provisioned',
			'created'  => 2,
			'attached' => 0,
			'airtable' => 0,
			'left'     => 0,
			'limit'    => 10,
		)
	);
	request( screen() );

	$declared = class_exists( 'WPCPM_Accounts_Table', false );
	$drawn    = run(
		function () {
			ob_start();

			try {
				( new WPCPM_Sponsors() )->render_admin_page();
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

echo "=== The screen loads its list table on the Accounts tab's load, and not before ===\n";

ck( 'nothing has declared a list table before the screen loads',
	array( class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Sponsors_Table', false ) ),
	array( false, false, false ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-save 2>&1', $save_out, $save_status );
$save = (array) json_decode( implode( "\n", $save_out ), true );

ck( 'a rows-per-page choice is saved as WordPress saves it, before any table is loaded: the save, hooked at boot, loads the table itself and keeps 50 under the Sponsors\' own name',
	array( $save_status, isset( $save['before'] ) ? $save['before'] : implode( "\n", $save_out ), isset( $save['error'] ) ? $save['error'] : null, isset( $save['saved'] ) ? $save['saved'] : null, isset( $save['after'] ) ? $save['after'] : null ),
	array( 0, false, '', 50, true ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-card 2>&1', $card_out, $card_status );
$card_run = (array) json_decode( implode( "\n", $card_out ), true );

ck( 'the invitations card\'s button, pressed where no list table is loaded, as admin-post.php runs it, loads the table it reads the role and the stamps from, queues the three never invited and comes back to the Accounts tab',
	array( $card_status, isset( $card_run['before'] ) ? $card_run['before'] : implode( "\n", $card_out ), isset( $card_run['error'] ) ? $card_run['error'] : null, isset( $card_run['redirect'] ) ? $card_run['redirect'] : null, isset( $card_run['queue'] ) ? $card_run['queue'] : null ),
	array( 0, false, '', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts', array( 22, 24, 26 ) ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-notice 2>&1', $notice_out, $notice_status );
$notice_run = (array) json_decode( implode( "\n", $notice_out ), true );

ck( 'a Create account on the ticked sponsors is worded on a tab drawn without the screen\'s load hook too: its sentence is the Sponsors table\'s, loaded for it',
	array( $notice_status, isset( $notice_run['before'] ) ? $notice_run['before'] : implode( "\n", $notice_out ), isset( $notice_run['error'] ) ? $notice_run['error'] : null, isset( $notice_run['notices'] ) ? $notice_run['notices'] : null ),
	array( 0, false, '', '<div class="notice notice-success is-dismissible"><p>2 accounts were created, each with its invitation queued.</p></div>' ) );

the_site();
$elsewhere = array();

foreach ( array(
	'the menu\'s own address, the queue' => array( 'page' => 'wpcpm-sponsors' ),
	'the Sponsors tab'                   => screen( array( 'tab' => 'sponsors' ) ),
	'the Offers and codes tab'           => screen( array( 'tab' => 'offers' ) ),
) as $case => $query ) {
	$elsewhere[ $case ] = got(
		press( $query ),
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Sponsors_Table', false ), $GLOBALS['screen_options'], $GLOBALS['queries'], $GLOBALS['current_screen']->reader_content );
		}
	);
}

ck( 'on every tab but Accounts, the screen\'s own address among them, which opens the queue, the load hook leaves the list alone: no list table loaded, no rows-per-page option offered, no account read and no list headings set, and the page goes on',
	$elsewhere,
	array_fill_keys( array( 'the menu\'s own address, the queue', 'the Sponsors tab', 'the Offers and codes tab' ), array( 'drawn', false, false, array(), array(), array() ) ) );

// The hooks the boot adds, read off the stand-in's record of every hook, which keeps what the mail
// layer hooked above: its invitation template, which the row invitations below are sent through.
$hooks_before = $GLOBALS['hooks'];
$booted       = run(
	function () {
		$module = new WPCPM_Sponsors();
		$module->boot();

		$hooked = function ( $hook ) {
			return array_map(
				function ( $added ) {
					return array( is_array( $added[0] ) && is_object( $added[0][0] ) ? get_class( $added[0][0] ) : $added[0][0], $added[0][1], $added[1] );
				},
				isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array()
			);
		};

		return array(
			$hooked( 'admin_post_wpcpm_sponsors_invite' ),
			$hooked( 'admin_post_wpcpm_sponsors_bulk_invite' ),
			$hooked( 'admin_post_wpcpm_sponsor_provision' ),
			$hooked( 'admin_menu' ),
			$hooked( 'set_screen_option_wpcpm_sponsors_per_page' ),
		);
	}
);

$GLOBALS['hooks'] = $hooks_before;

ck( 'the module boots the Accounts tab\'s two invitation handlers, a row\'s and the card\'s, beside the provisioning handler it always had, the screen\'s own load hook once the menu exists, and the rows-per-page save',
	got(
		$booted,
		function ( $out ) {
			return $out['value'];
		}
	),
	array(
		array( array( 'WPCPM_Sponsors', 'handle_invite', 10 ) ),
		array( array( 'WPCPM_Sponsors', 'handle_bulk_invite', 10 ) ),
		array( array( 'WPCPM_Sponsors', 'handle_provision', 10 ) ),
		array( array( 'WPCPM_Sponsors', 'hook_screen', 11 ) ),
		array( array( 'WPCPM_Sponsors', 'save_per_page', 10 ) ),
	) );

$tab_read = run(
	function () {
		$read = array();

		foreach ( array( array(), array( 'tab' => 'accounts' ), array( 'tab' => 'nope' ) ) as $query ) {
			request( array_merge( array( 'page' => 'wpcpm-sponsors' ), $query ) );
			$read[] = WPCPM_Sponsors::tab();
		}

		$read[] = basename( (string) ( new ReflectionMethod( 'WPCPM_Sponsors', 'tab' ) )->getFileName() );
		$read[] = basename( (string) ( new ReflectionMethod( 'WPCPM_Sponsors', 'list_state' ) )->getFileName() );
		$read[] = basename( (string) ( new ReflectionMethod( 'WPCPM_Sponsors', 'tab_labels' ) )->getFileName() );
		$read[] = defined( 'WPCPM_Sponsors::TAB_SYNC' );

		return $read;
	}
);

ck( 'the tab shown is the accounts screen\'s reading, the module keeping none of its own: the queue at the screen\'s own address and for a tab the screen does not have, the tab named otherwise; where the list stands is the screen\'s reading too; the labels are the module\'s own six, and the screen has no Sync tab',
	got(
		$tab_read,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'queue', 'accounts', 'queue', 'trait-wpcpm-accounts-screen.php', 'trait-wpcpm-accounts-screen.php', 'class-wpcpm-sponsors.php', false ) );

$loaded = press( screen() );

ck( 'on the Accounts tab the load hook loads core\'s list table, the accounts base and the Sponsors table, and lets the page go on',
	got(
		$loaded,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Sponsors_Table', false ) );
		}
	),
	array( 'drawn', true, true, true ) );
ck( 'it puts the rows-per-page option on the screen: twenty until somebody chooses, kept under the Sponsors\' own name',
	isset( $GLOBALS['screen_options']['per_page'] ) ? $GLOBALS['screen_options']['per_page'] : 'no option added',
	array(
		'default' => 20,
		'option'  => 'wpcpm_sponsors_per_page',
	) );
ck( 'and prepares the list there, before the page\'s header, as core\'s own lists do', count( queries( 'page' ) ), 1 );
ck( 'it names the list\'s three headings for screen readers, which core leaves out unless the screen sets them',
	$GLOBALS['current_screen']->reader_content,
	array(
		'heading_views'      => 'Filter sponsor accounts list',
		'heading_pagination' => 'Sponsor accounts list navigation',
		'heading_list'       => 'Sponsor accounts list',
	) );

echo "\n=== The table: a checkbox, then Name, Username, Sponsor, Member since and Status ===\n";

the_site();
$first = draw( screen() );
$html  = html_of( $first );

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", between( $html, '<thead>', '</thead>' ), $header_ids );

ck( 'the headers are the checkbox, then the five columns in order', $header_ids[1], array( 'cb', 'name', 'login', 'sponsor', 'since', 'status' ) );
ck( 'each says what it holds, and each sorts',
	array( has( $html, '<span>Name</span>' ), has( $html, '<span>Username</span>' ), has( $html, '<span>Sponsor</span>' ), has( $html, '<span>Member since</span>' ), has( $html, '<span>Status</span>' ) ),
	array( true, true, true, true, true ) );
ck( 'a row per account holding the Sponsor role, by name: Ada, Ben, Cleo, Finn, Hugo, Nell; the mentor, the student and the administrators are not on it', row_ids( $html ), array( 25, 22, 23, 26, 24, 21 ) );

$cells_of = function ( $row ) {
	return array( cell( $row, 'sponsor' ), cell( $row, 'since' ), cell( $row, 'status' ) );
};

ck( 'Nell\'s row: her name to her account\'s editor, her username, the sponsor she acts for by its name in the index, the day her membership began in the site\'s date format, Active',
	array( cell( row_for( $html, 21 ), 'name' ), cell( row_for( $html, 21 ), 'login' ), $cells_of( row_for( $html, 21 ) ) ),
	array( '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=21">Nell Ito</a></strong>', '<code>nito</code>', array( 'Wren Example Labs', 'January 1, 2026', 'Active' ) ) );
ck( 'Ben\'s, locked out of changes for the rest of today, says so after Active',
	$cells_of( row_for( $html, 22 ) ),
	array( 'Wren Example Labs', 'March 1, 2026', 'Active, locked today' ) );
ck( 'Cleo\'s, a mentor too, is her sponsor\'s like any other account\'s',
	$cells_of( row_for( $html, 23 ) ),
	array( 'Birch Example Cloud', 'February 1, 2026', 'Active' ) );
ck( 'Finn acts for a sponsor that is not Approved: its status follows its name in parentheses, as the index holds it; his account is older than the membership facts, so no day is printed',
	$cells_of( row_for( $html, 26 ) ),
	array( 'Moss Example Hosting (Paused)', '', 'Active' ) );
ck( 'Ada\'s membership ended: her stamp moved aside and her flag is 0, so she acts for no sponsor, and the day it began is kept',
	$cells_of( row_for( $html, 25 ) ),
	array( '', 'December 1, 2025', 'Membership ended' ) );

$actions = array();
foreach ( array( 21, 22, 25, 26 ) as $id ) {
	$actions[ $id ] = actions_in( row_for( $html, $id ) );
}

ck( 'under each name, Edit, View page for an account whose membership is live, then the invitation, Resend invite for Nell and Ada, invited before, Send invite for Ben and Finn, then Manage accounts for an account stamped with a sponsor: not Ada, whose stamp was moved aside when her membership ended',
	array_map( 'array_keys', $actions ),
	array(
		21 => array( 'edit', 'view', 'reinvite', 'accounts' ),
		22 => array( 'edit', 'view', 'invite', 'accounts' ),
		25 => array( 'edit', 'reinvite' ),
		26 => array( 'edit', 'view', 'invite', 'accounts' ),
	) );
ck( 'which say so', array_map( 'strip_tags', $actions[21] ), array( 'edit' => 'Edit', 'view' => 'View page', 'reinvite' => 'Resend invite', 'accounts' => 'Manage accounts' ) );
ck( 'Manage accounts opens the Accounts tab\'s view of the sponsor the account is stamped with, Nell\'s Wren and Finn\'s Moss, a sponsor that is not Approved among them',
	array( link_of( isset( $actions[21]['accounts'] ) ? $actions[21]['accounts'] : '' ), link_of( isset( $actions[26]['accounts'] ) ? $actions[26]['accounts'] : '' ) ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => WREN ) ),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => MOSS ) ),
	) );
ck( 'View page opens the Sponsor Dashboard as the account\'s sponsor, through the switcher\'s own argument, a sponsor that is not Approved among them, and Edit the account\'s editor',
	array( link_of( isset( $actions[21]['view'] ) ? $actions[21]['view'] : '' ), link_of( isset( $actions[26]['view'] ) ? $actions[26]['view'] : '' ), link_of( isset( $actions[21]['edit'] ) ? $actions[21]['edit'] : '' ) ),
	array(
		array( 'https://example.test/sponsor-dashboard/', array( 'wpcpm_sponsor_view' => WREN ) ),
		array( 'https://example.test/sponsor-dashboard/', array( 'wpcpm_sponsor_view' => MOSS ) ),
		array( 'https://example.test/wp-admin/user-edit.php', array( 'user_id' => '21' ) ),
	) );
ck( 'the views: All six accounts, three Invited, three Never invited, and No account counting the three Approved sponsors with none',
	view_counts( $html ),
	array(
		'all'           => '6',
		'invited'       => '3',
		'never-invited' => '3',
		'no-account'    => '3',
	) );
ck( 'the card counts the accounts, every page of them, and names the Sponsor Dashboard\'s address',
	array( heading_count( $html ), has( $html, '<p>Sponsor Dashboard: <a href="https://example.test/sponsor-dashboard/">https://example.test/sponsor-dashboard/</a></p>' ) ),
	array( '6', true ) );
ck( 'the accounts locked today are asked for once a draw, not once a row', $GLOBALS['locked_reads'], 1 );
ck( 'and the Sponsor Dashboard\'s address twice a draw, however many rows offer View page: once for the card\'s line, and once by the table for all five rows that offer it',
	array( substr_count( $html, '>View page</a>' ), $GLOBALS['page_reads'] ),
	array( 5, 2 ) );
// The whole tab, each of the three account views as the screen draws it: the list, its notices and
// its cards. A sponsor's accounts are where addresses are printed, on a view of their own. Each
// draw's rows are read beside its addresses, so a draw that printed nothing cannot pass for one
// that printed no address.
$whole_tab = array();
foreach ( array( 'all', 'invited', 'never-invited' ) as $account_view ) {
	$tab_drawn                  = html_of( page_for( screen( 'all' === $account_view ? array() : array( 'wpcpm_view' => $account_view ) ) ) );
	$whole_tab[ $account_view ] = array( has( $tab_drawn, '@' ), row_ids( $tab_drawn ) );
}

ck( 'and no address is printed anywhere on the Accounts tab on any of the three account views, each drawn with its accounts: the search covers the email, no cell prints one, and no card under the list does',
	$whole_tab,
	array(
		'all'           => array( false, array( 25, 22, 23, 26, 24, 21 ) ),
		'invited'       => array( false, array( 25, 23, 21 ) ),
		'never-invited' => array( false, array( 22, 26, 24 ) ),
	) );

// Four states of an account the site above does not hold: Otto's live stamp is no record ID at all,
// with the flag at 1, so it is printed as what it says and no View page is offered; Pia acts for
// Quay Example Media, whose status is blank in the index; Quinn's sponsor is a record the index no
// longer holds, so its record ID stands in for its name; and Rae keeps Wren's stamp with her flag at
// 0, a membership ended by hand, which acts for nobody, so the page she would be shown is not hers.
seat( 33, 'Otto Vance', 'ovance', array( WPCPM_Roles::ROLE_SPONSOR ), array( WPCPM_Sponsor_Members::META_RECORD_ID => 'not-a-record', WPCPM_Sponsor_Members::META_ACTIVE => 1 ) );
seat( 34, 'Pia Lowe', 'plowe', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( QUAY, 1767225600 ) );
seat( 35, 'Quinn Moor', 'qmoor', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( 'recSPONGONEX00099', 1767225600 ) );
seat( 36, 'Rae Kim', 'rkim', array( WPCPM_Roles::ROLE_SPONSOR ), array( WPCPM_Sponsor_Members::META_RECORD_ID => WREN, WPCPM_Sponsor_Members::META_ACTIVE => 0 ) );
$odd_states = html_of( draw( screen() ) );
$odd_ones   = array(
	33 => actions_in( row_for( $odd_states, 33 ) ),
	34 => actions_in( row_for( $odd_states, 34 ) ),
	35 => actions_in( row_for( $odd_states, 35 ) ),
	36 => actions_in( row_for( $odd_states, 36 ) ),
);
foreach ( array( 33, 34, 35, 36 ) as $odd ) {
	unset( $GLOBALS['users'][ $odd ], $GLOBALS['umeta'][ $odd ] );
}

ck( 'a live stamp that names no record is printed as it reads, Active, with Edit and the invitation alone: no View page and no Manage accounts, since it names no sponsor to open',
	array( $cells_of( row_for( $odd_states, 33 ) ), array_keys( $odd_ones[33] ) ),
	array( array( 'not-a-record', '', 'Active' ), array( 'edit', 'invite' ) ) );
ck( 'a sponsor whose status is blank in the index reads "no status" after its name',
	array( $cells_of( row_for( $odd_states, 34 ) ), array_keys( $odd_ones[34] ) ),
	array( array( 'Quay Example Media (no status)', 'January 1, 2026', 'Active' ), array( 'edit', 'view', 'invite', 'accounts' ) ) );
ck( 'a sponsor the index no longer holds is its record ID, with no status to say, and its Manage accounts still opens that record, whose view says the index lacks it',
	array( $cells_of( row_for( $odd_states, 35 ) ), array_keys( $odd_ones[35] ) ),
	array( array( 'recSPONGONEX00099', 'January 1, 2026', 'Active' ), array( 'edit', 'view', 'invite', 'accounts' ) ) );
ck( 'a stamp kept with the flag at 0 is a membership that ended: the sponsor is named, the membership ended, and no View page is offered, since the account acts for nobody; Manage accounts is offered all the same, for the sponsor the stamp names',
	array( $cells_of( row_for( $odd_states, 36 ) ), array_keys( $odd_ones[36] ) ),
	array( array( 'Wren Example Labs', '', 'Membership ended' ), array( 'edit', 'invite', 'accounts' ) ) );

$form = between( $html, '<form method="get"', '</form>' );

ck( 'the list is one form, sent to the screen by GET on its Accounts tab, holding the checkboxes, the bulk actions and their nonce, and the search box',
	array( has( $form, '<input type="hidden" name="page" value="wpcpm-sponsors" />' ), has( $form, '<input type="hidden" name="tab" value="accounts" />' ), has( $form, 'name="users[]"' ), has( $form, '<select name="action"' ), has( $form, 'value="' . wp_create_nonce( 'bulk-sponsors' ) . '"' ), has( $form, 'name="s"' ), has( $form, '<label class="screen-reader-text" for="wpcpm-sponsors-search-input">Search sponsor accounts:</label>' ) ),
	array( true, true, true, true, true, true, true ) );

$GLOBALS['list_screen'] = (object) array(
	'id'   => 'wpcredits-program_page_wpcpm-sponsors',
	'base' => 'wpcredits-program_page_wpcpm-sponsors',
);
$offered                = run(
	function () {
		request( screen() );
		new WPCPM_Sponsors_Table();

		return array_keys( apply_filters( 'manage_wpcredits-program_page_wpcpm-sponsors_columns', array() ) );
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
	array( 'cb', 'login', 'sponsor', 'since', 'status' ) );

WPCPM_Sponsors_Dashboard::$url = '';
$GLOBALS['no_editor']          = true;
$bare_html                     = html_of( draw( screen() ) );
$bare                          = row_for( $bare_html, 21 );
WPCPM_Sponsors_Dashboard::$url = 'https://example.test/sponsor-dashboard/';
$GLOBALS['no_editor']          = false;

ck( 'with no Sponsor Dashboard page and no right to the editor, the name is plain, the invitation and Manage accounts the two actions, and the card says the page is missing',
	array( cell( $bare, 'name' ), array_keys( actions_in( $bare ) ), has( $bare_html, '<p class="wpcpm-warning">The Sponsor Dashboard page is missing. Re-activate the plugin to recreate it.</p>' ) ),
	array( '<strong>Nell Ito</strong>', array( 'reinvite', 'accounts' ), true ) );

$kept_index = $GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ];
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ WREN ]['name'] = ' Wren & <b>Co</b> ';
$marked                                                                    = html_of( draw( screen() ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]                         = $kept_index;

ck( 'a sponsor\'s name is printed trimmed and as text, whatever the index holds',
	array( cell( row_for( $marked, 21 ), 'sponsor' ), has( $marked, '<b>Co</b>' ) ),
	array( 'Wren &amp; &lt;b&gt;Co&lt;/b&gt;', false ) );

$GLOBALS['users'] = array_intersect_key( $GLOBALS['users'], array( 1 => true, 30 => true, 31 => true, 32 => true ) );
$empty_list       = html_of( draw( screen() ) );
the_site();

ck( 'with no sponsor account at all, the list says it found none', array( row_ids( $empty_list ), has( $empty_list, 'No sponsor accounts found.' ) ), array( array(), true ) );

echo "\n=== Invited is any stamp: an account that is a mentor too, among them ===\n";

the_site();

$invited_view = html_of( draw( screen( array( 'wpcpm_view' => 'invited' ) ) ) );
$never_view   = html_of( draw( screen( array( 'wpcpm_view' => 'never-invited' ) ) ) );

ck( 'the Invited view lists Ada, Cleo and Nell, Cleo stamped under both her roles, and the Never invited view Ben, Finn and Hugo',
	array( row_ids( $invited_view ), row_ids( $never_view ) ),
	array( array( 25, 23, 21 ), array( 22, 26, 24 ) ) );

unset( $GLOBALS['umeta'][23]['wpcpm_sponsor_invited'] );
$one_stamp = html_of( draw( screen() ) );

ck( 'with her mentor\'s stamp alone she is Invited all the same, her row offering Resend invite: an account has one password',
	array( view_counts( $one_stamp ), array_keys( actions_in( row_for( $one_stamp, 23 ) ) ) ),
	array( array( 'all' => '6', 'invited' => '3', 'never-invited' => '3', 'no-account' => '3' ), array( 'edit', 'view', 'reinvite', 'accounts' ) ) );

echo "\n=== The sorts: Name and Username by WordPress, Sponsor, Member since and Status by the table ===\n";

the_site();
$plain = html_of( draw( screen() ) );

ck( 'Name sorts by display name, Username by username, and Sponsor, Member since and Status by what each cell shows',
	array( sort_of( $plain, 'name' ), sort_of( $plain, 'login' ), sort_of( $plain, 'sponsor' ), sort_of( $plain, 'since' ), sort_of( $plain, 'status' ) ),
	array( 'display_name', 'login', 'sponsor', 'since', 'status' ) );
ck( 'Username sorts Z to A as asked, by WordPress', row_ids( html_of( draw( screen( array( 'orderby' => 'login', 'order' => 'desc' ) ) ) ) ), array( 21, 24, 26, 23, 22, 25 ) );

$by_sponsor   = draw( screen( array( 'orderby' => 'sponsor', 'order' => 'asc' ) ) );
$sponsor_read = $GLOBALS['queries'];

ck( 'Sponsor A to Z, the sponsors\' names as the cells show them, an account acting for none first; each sponsor\'s accounts by name: Ada; Cleo and Hugo at Birch; Finn at Moss, Paused; Ben and Nell at Wren',
	got(
		$by_sponsor,
		function ( $out ) {
			return row_ids( html_of( $out ) );
		}
	),
	array( 25, 23, 24, 26, 22, 21 ) );
ck( 'Sponsor Z to A, each sponsor\'s accounts still by name',
	row_ids( html_of( draw( screen( array( 'orderby' => 'sponsor', 'order' => 'desc' ) ) ) ) ),
	array( 22, 21, 26, 23, 24, 25 ) );
ck( 'Member since, the earliest first, an account with no day written before every day',
	row_ids( html_of( draw( screen( array( 'orderby' => 'since', 'order' => 'asc' ) ) ) ) ),
	array( 26, 25, 21, 23, 22, 24 ) );
ck( 'Member since, the latest first',
	row_ids( html_of( draw( screen( array( 'orderby' => 'since', 'order' => 'desc' ) ) ) ) ),
	array( 24, 22, 23, 21, 25, 26 ) );
ck( 'Status A to Z as the words run, Active, then Active and locked today, then Membership ended, each status\'s accounts by name',
	row_ids( html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'asc' ) ) ) ) ),
	array( 23, 26, 24, 21, 22, 25 ) );
ck( 'Status Z to A, each status\'s accounts still by name',
	row_ids( html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'desc' ) ) ) ) ),
	array( 25, 22, 23, 26, 24, 21 ) );

$GLOBALS['queries'] = $sponsor_read;

ck( 'a sort by a value lists every account, counted: one read of every account holding the Sponsor role, whole, which the table orders itself, and none WordPress is asked to sort by the value or to page',
	array( displaying( html_of( $by_sponsor ) ), count( queries( 'every' ) ), arg( first_query( 'every' ), 'orderby' ), arg( first_query( 'every' ), 'role' ), count( queries( 'page' ) ) ),
	array( '6 items', 1, 'ID', WPCPM_Roles::ROLE_SPONSOR, 0 ) );

$GLOBALS['umeta'][1]['wpcpm_sponsors_per_page'] = 4;
$second = array(
	'sponsor' => html_of( draw( screen( array( 'orderby' => 'sponsor', 'order' => 'asc', 'paged' => '2' ) ) ) ),
	'since'   => html_of( draw( screen( array( 'orderby' => 'since', 'order' => 'desc', 'paged' => '2' ) ) ) ),
	'status'  => html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'asc', 'paged' => '2' ) ) ) ),
);
unset( $GLOBALS['umeta'][1]['wpcpm_sponsors_per_page'] );

ck( 'four a page, the second page of each holds the fifth and the sixth in that sort\'s order, and the pagination counts all six',
	array_map(
		function ( $page ) {
			return array( row_ids( $page ), displaying( $page ) );
		},
		$second
	),
	array(
		'sponsor' => array( array( 22, 21 ), '6 items' ),
		'since'   => array( array( 25, 26 ), '6 items' ),
		'status'  => array( array( 22, 25 ), '6 items' ),
	) );

$sorted_view = html_of( draw( screen( array( 'orderby' => 'sponsor', 'wpcpm_view' => 'never-invited' ) ) ) );

ck( 'sorted by a value, a view keeps to the view: the three never invited, by sponsor',
	array( row_ids( $sorted_view ), displaying( $sorted_view ) ),
	array( array( 24, 26, 22 ), '3 items' ) );

preg_match( "/<th scope=\"col\" id='sponsor' class='([^']*)'/", html_of( $by_sponsor ), $marked_header );

ck( 'the Sponsor header says the list is sorted by it, A to Z', isset( $marked_header[1] ) ? $marked_header[1] : '', 'manage-column column-sponsor sorted asc' );

// The base asks for the name sort in WP_User_Query's array form, so a table that sorts some of its
// columns itself reads what the list is sorted by from the array's first key.
$as_array = run(
	function () {
		request( screen() );
		$found = reach( 'query', 'WPCPM_Sponsors_Table' )->invoke(
			new WPCPM_Sponsors_Table(),
			array(
				'role'        => WPCPM_Roles::ROLE_SPONSOR,
				'number'      => 20,
				'offset'      => 0,
				'orderby'     => array(
					'status' => 'ASC',
					'ID'     => 'ASC',
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

ck( 'asked for the Status sort in the array form, the table still sorts by the status, reading the array\'s first key',
	got(
		$as_array,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 23, 26, 24, 21, 22, 25 ) );

echo "\n=== Two accounts of one value and one name, where one page ends and the next begins ===\n";

// A row a page, so the boundary between two pages falls between the two Sam Riveras, whose sponsor,
// day and status are the same: the table's order has to settle them, or one could show on both
// pages and the other on neither.
the_site();
$GLOBALS['users'] = array_intersect_key( $GLOBALS['users'], array( 1 => true, 30 => true, 31 => true, 32 => true ) );
seat( 51, 'Sam Rivera', 'srivera', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( WREN, 1767225600 ) );
seat( 52, 'Sam Rivera', 'sriveraortiz', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( WREN, 1767225600 ) );
seat( 53, 'Tia Rivera', 'trivera', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( BIRCH, 1764547200 ) );
$GLOBALS['locked']                              = array();
$GLOBALS['umeta'][1]['wpcpm_sponsors_per_page'] = 1;

$pages = array();
foreach ( array( 'sponsor', 'since', 'status' ) as $sort ) {
	foreach ( array( 'asc', 'desc' ) as $way ) {
		foreach ( array( '1', '2', '3' ) as $number ) {
			$pages[ $sort . ' ' . $way ][] = row_ids( html_of( draw( screen( array( 'orderby' => $sort, 'order' => $way, 'paged' => $number ) ) ) ) );
		}
	}
}

unset( $GLOBALS['umeta'][1]['wpcpm_sponsors_per_page'] );

ck( 'each Sam Rivera is on a page of their own, by every sort and either way: of one value and one name, the lower ID first',
	$pages,
	array(
		'sponsor asc'  => array( array( 53 ), array( 51 ), array( 52 ) ),
		'sponsor desc' => array( array( 51 ), array( 52 ), array( 53 ) ),
		'since asc'    => array( array( 53 ), array( 51 ), array( 52 ) ),
		'since desc'   => array( array( 51 ), array( 52 ), array( 53 ) ),
		'status asc'   => array( array( 51 ), array( 52 ), array( 53 ) ),
		'status desc'  => array( array( 51 ), array( 52 ), array( 53 ) ),
	) );

// PHP's sort is stable since 8.0, and the read comes back in ID order, so on the PHP here the two
// Sam Riveras keep their order whether or not the table's order settles them: the order the table
// sorts by is asked directly, for two accounts of one value and one name, either way round, so the
// ID that settles them on PHP 7.4, whose sort may put two equal items either way round, is pinned
// on any PHP.
$settled_order = run(
	function () {
		$compare = reach( 'compare_accounts', 'WPCPM_Sponsors_Table' );
		$results = array();

		foreach ( array( 'Wren Example Labs', 1767225600 ) as $value ) {
			$lower  = array(
				'user'  => $GLOBALS['users'][51],
				'value' => $value,
			);
			$higher = array(
				'user'  => $GLOBALS['users'][52],
				'value' => $value,
			);

			$results[] = array(
				$compare->invoke( null, $lower, $higher, false ) < 0,
				$compare->invoke( null, $lower, $higher, true ) < 0,
				$compare->invoke( null, $higher, $lower, false ) > 0,
				$compare->invoke( null, $higher, $lower, true ) > 0,
			);
		}

		return $results;
	}
);

ck( 'and the order itself says so: of two accounts of one value and one name, a name or a number, the lower ID comes first, whichever way the values run and whichever of the two is asked about first',
	got(
		$settled_order,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( array( true, true, true, true ), array( true, true, true, true ) ) );

echo "\n=== The search: the name, the username, the email and the sponsor's name ===\n";

the_site();
$found = array();
foreach ( array( 'wren', 'BIRCH', 'bokoye', 'Lund', 'cpark@example' ) as $term ) {
	$found[ $term ] = row_ids( html_of( draw( screen( array( 's' => $term ) ) ) ) );
}

ck( 'whatever the case, by the sponsor\'s name, the username, the name or the email; an account whose membership ended is not found by the sponsor it left',
	$found,
	array(
		'wren'          => array( 22, 21 ),
		'BIRCH'         => array( 23, 24 ),
		'bokoye'        => array( 22 ),
		'Lund'          => array( 25 ),
		'cpark@example' => array( 23 ),
	) );

$plain_index = $GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ];
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ WREN ]['name'] = 'Wrén Example Labs';
$accented                                                                  = row_ids( html_of( draw( screen( array( 's' => 'wren' ) ) ) ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]                         = $plain_index;

ck( 'and by the sponsor\'s name without regard to accents, as the names are sorted: "wren" finds the accounts acting for Wrén Example Labs',
	$accented,
	array( 22, 21 ) );
$paused = html_of( draw( screen( array( 's' => 'paused' ) ) ) );

ck( 'the status is not the name: "paused" finds nobody, and the list says it found none', array( row_ids( $paused ), has( $paused, 'No sponsor accounts found.' ) ), array( array(), true ) );

$searched = html_of( draw( screen( array( 's' => 'wren' ) ) ) );

ck( 'WordPress is asked for the accounts its search finds, "contains", over the username, the email and the name; the page is then asked by ID, the accounts at Wren among them, and with no search of its own',
	array(
		arg( first_query( 'search' ), 'search' ),
		arg( first_query( 'search' ), 'search_columns' ),
		arg( first_query( 'search' ), 'role' ),
		arg( first_query( 'page' ), 'include' ),
		arg( first_query( 'page' ), 'search' ),
		arg( first_query( 'page' ), 'role' ),
	),
	array( '*wren*', array( 'user_login', 'user_email', 'display_name' ), WPCPM_Roles::ROLE_SPONSOR, array( 21, 22 ), 'absent', WPCPM_Roles::ROLE_SPONSOR ) );
ck( 'the views count the whole list, not the search, as WordPress\'s own views do',
	view_counts( $searched ),
	array(
		'all'           => '6',
		'invited'       => '3',
		'never-invited' => '3',
		'no-account'    => '3',
	) );

$nobody = html_of( draw( screen( array( 's' => 'zzz' ) ) ) );

ck( 'a search nobody matches says the list found nobody, asking WordPress for an ID nobody holds rather than for every account',
	array( row_ids( $nobody ), has( $nobody, 'No sponsor accounts found.' ), arg( first_query( 'page' ), 'include' ) ),
	array( array(), true, array( 0 ) ) );

$sorted_search = html_of( draw( screen( array( 's' => 'birch', 'orderby' => 'status', 'order' => 'desc' ) ) ) );

ck( 'sorted by a value, a search keeps to what it found', array( row_ids( $sorted_search ), displaying( $sorted_search ) ), array( array( 23, 24 ), '2 items' ) );

echo "\n=== No account: the Approved sponsors with no account yet, a view of their own ===\n";

the_site();
$all_view    = html_of( draw( screen() ) );
$all_live    = count( queries( 'live' ) );
$all_lookups = address_lookups();
$records     = draw( screen( array( 'wpcpm_view' => 'no-account' ) ) );
$html        = html_of( $records );
$live        = count( queries( 'live' ) );
$lookups     = address_lookups();

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", between( $html, '<thead>', '</thead>' ), $record_headers );
preg_match( "/<li class='no-account'>(.*?)<\/li>/", $all_view, $no_account_link );

ck( 'the view\'s link follows the three invitation views, on the Accounts tab, counting the sponsors it lists',
	array( link_of( isset( $no_account_link[1] ) ? $no_account_link[1] : '' ), strip_tags( isset( $no_account_link[1] ) ? $no_account_link[1] : '' ) ),
	array( array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) ), 'No account (3)' ) );
ck( 'on it: the checkbox, then Sponsor, Contact and Account, the sponsor the row\'s header; a row per Approved sponsor with no live account, the ready ones first and then by name: Nova, Opal, then Ash',
	array( $record_headers[1], record_ids( $html ), row_ids( $html ) ),
	array( array( 'cb', 'sponsor', 'contact', 'account' ), array( NOVA, OPAL, ASH ), array() ) );

$nova = record_row( $html, NOVA );
$opal = record_row( $html, OPAL );
$ash  = record_row( $html, ASH );

ck( 'Nova\'s row: its name as the index holds it over its record ID, its contact address masked, Ready',
	array( cell( $nova, 'sponsor' ), cell( $nova, 'contact' ), cell( $nova, 'account' ) ),
	array( '<strong>Nova Example Tools</strong><br /><code class="wpcpm-inst-record">recSPONNOVEM00004</code>', 'h***@nova.example', 'Ready' ) );
ck( 'Opal\'s: its name trimmed; its contact address belongs to an account already, which a Create account attaches rather than duplicates, and the row says so',
	array( cell( $opal, 'sponsor' ), cell( $opal, 'contact' ), cell( $opal, 'account' ) ),
	array( '<strong>Opal Example Studio</strong><br /><code class="wpcpm-inst-record">recSPONOSCAR00005</code>', 'u***@example.test', 'Ready: the account that already uses this address will be attached' ) );
ck( 'Ash\'s: no contact address, so the Account column says where to add one, and the row\'s actions are View page and Manage accounts, where an account at another address is attached by hand',
	array( cell( $ash, 'contact' ), cell( $ash, 'account' ), array_keys( actions_in( $ash ) ), substr_count( $ash, 'toggle-row' ) ),
	array( 'no email', 'No Contact Email in Airtable. Add one there and sync.', array( 'view', 'accounts' ), 1 ) );

$create = actions_in( $nova );

ck( 'a ready row\'s actions are Create account, View page, then Manage accounts, under its name',
	array( array_keys( $create ), array_map( 'strip_tags', $create ), substr_count( $nova, 'toggle-row' ) ),
	array( array( 'create', 'view', 'accounts' ), array( 'create' => 'Create account', 'view' => 'View page', 'accounts' => 'Manage accounts' ), 1 ) );
ck( 'Manage accounts opens the Accounts tab\'s view of the sponsor, with the view the list stood on, the address an account row\'s Manage accounts builds: on the ready row and on the one that is not',
	array( link_of( isset( $create['accounts'] ) ? $create['accounts'] : '' ), link_of( isset( actions_in( $ash )['accounts'] ) ? actions_in( $ash )['accounts'] : '' ) ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => NOVA, 'wpcpm_view' => 'no-account' ) ),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => ASH, 'wpcpm_view' => 'no-account' ) ),
	) );
ck( 'View page opens the Sponsor Dashboard as the sponsor, through the switcher\'s own argument, the address an account row\'s View page builds: on the ready row and on the one that is not',
	array( link_of( isset( $create['view'] ) ? $create['view'] : '' ), link_of( isset( actions_in( $ash )['view'] ) ? actions_in( $ash )['view'] : '' ) ),
	array(
		array( 'https://example.test/sponsor-dashboard/', array( 'wpcpm_sponsor_view' => NOVA ) ),
		array( 'https://example.test/sponsor-dashboard/', array( 'wpcpm_sponsor_view' => ASH ) ),
	) );

WPCPM_Sponsors_Dashboard::$url = '';
$no_page                       = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
WPCPM_Sponsors_Dashboard::$url = 'https://example.test/sponsor-dashboard/';

ck( 'while the Sponsor Dashboard\'s page is missing, View page is left out, as on an account row: Create account and Manage accounts on the ready row, Manage accounts alone on the other, beside the toggle',
	array( array_keys( actions_in( record_row( $no_page, NOVA ) ) ), array_keys( actions_in( record_row( $no_page, ASH ) ) ), substr_count( record_row( $no_page, ASH ), 'toggle-row' ) ),
	array( array( 'create', 'accounts' ), array( 'accounts' ), 1 ) );
ck( 'Create account is a nonce link to the module\'s provisioning handler: the record with its case, the tab and the view to come back to, under the nonce that handler has always checked, keyed to the sponsor',
	link_of( isset( $create['create'] ) ? $create['create'] : '' ),
	array(
		'https://example.test/wp-admin/admin-post.php',
		array(
			'action'        => 'wpcpm_sponsor_provision',
			'wpcpm_sponsor' => NOVA,
			'wpcpm_tab'     => 'accounts',
			'wpcpm_view'    => 'no-account',
			'_wpnonce'      => wp_create_nonce( 'wpcpm_sponsor_provision_' . NOVA ),
		),
	) );
ck( 'a ready record\'s checkbox posts its record ID under records[], labeled with its name; the row with no contact address draws none, since Create account could only refuse it; no row posts an account',
	array(
		has( $nova, '<input type="checkbox" name="records[]" id="wpcpm-record-recSPONNOVEM00004" value="recSPONNOVEM00004" /><label for="wpcpm-record-recSPONNOVEM00004"><span class="screen-reader-text">Select Nova Example Tools</span></label>' ),
		has( $ash, 'type="checkbox"' ),
		substr_count( $html, 'name="records[]"' ),
		has( $html, 'name="users[]"' ),
	),
	array( true, false, 2, false ) );

$account_actions = array();
foreach ( array( 21, 22, 23, 24, 25, 26 ) as $id ) {
	$account_actions = array_merge( $account_actions, array_keys( actions_in( row_for( $all_view, $id ) ) ) );
}

ck( 'Create account is the view\'s bulk action and a ready row\'s, and the invitations are not offered there; the account views offer the two invitations, and never Create account',
	array( bulk_of( $html ), has( $html, '>Send invite<' ), has( $html, '>Resend invite<' ), bulk_of( $all_view ), in_array( 'create', $account_actions, true ), has( $all_view, 'Create account' ) ),
	array( array( 'create' ), false, false, array( 'invite', 'reinvite' ), false, false ) );
ck( 'the worklist is read once a draw, on either view: the live accounts asked for once, and an account looked up by address once for each sponsor with a contact address, Nova\'s and Opal\'s',
	array( $all_live, $all_lookups, $live, $lookups ),
	array( 1, 2, 1, 2 ) );
ck( 'the view keeps itself in the list\'s form, so a search, a sort or a page stays on it',
	has( between( $html, '<form method="get"', '</form>' ), '<input type="hidden" name="wpcpm_view" value="no-account" />' ), true );
ck( 'the card\'s heading counts sponsor accounts, as its words say, on the No account view too: six, while the list there holds three sponsors',
	array( heading_count( $all_view ), heading_count( $html ), displaying( $html ) ),
	array( '6', '6', '3 items' ) );
ck( 'the note under the list, on how accounts are made and invited, reads on the account views alone: the No account view offers neither invitation',
	array( has( $all_view, '<p class="description">Accounts are created with a random password.' ), has( $html, 'Accounts are created with a random password.' ) ),
	array( true, false ) );
ck( 'its one sort is the sponsor\'s name: A to Z, Ash before Nova and Opal, whether ready or not; Z to A the other way; any other order asked for keeps the view\'s own',
	array(
		sort_of( $html, 'sponsor' ),
		sort_of( $html, 'contact' ),
		record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 'orderby' => 'sponsor', 'order' => 'asc' ) ) ) ) ),
		record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 'orderby' => 'sponsor', 'order' => 'desc' ) ) ) ) ),
		record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 'orderby' => 'status' ) ) ) ) ),
	),
	array( 'sponsor', 'not sortable', array( ASH, NOVA, OPAL ), array( OPAL, NOVA, ASH ), array( NOVA, OPAL, ASH ) ) );

$record_search = html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'OPAL' ) ) ) );

ck( 'a search narrows the sponsors by name, whatever the case, and not the view\'s count',
	array( record_ids( $record_search ), view_counts( $record_search )['no-account'], displaying( $record_search ) ),
	array( array( OPAL ), '3', '1 item' ) );

$kept_index = $GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ];
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ NOVA ]['name'] = 'Nóva Example Tools';
$accented_record                                                           = record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'nova' ) ) ) ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]                         = $kept_index;

ck( 'and without regard to accents, as the names are sorted: "nova" finds Nóva Example Tools', $accented_record, array( NOVA ) );

$no_match = html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'zzz' ) ) ) );

ck( 'a search no sponsor matches says so, rather than that every sponsor has an account',
	array( record_ids( $no_match ), has( $no_match, 'No sponsors found.' ), has( $no_match, 'Every Approved sponsor has an account.' ) ),
	array( array(), true, false ) );

$warning = '<p>Creating an account emails a password-set link to the sponsor&#039;s contact address. An invitation cannot be recalled once sent.</p>';
$routes  = '<p class="description">An approved application creates its sponsor&#039;s account. For any other Approved sponsor, this view is the way one is made.</p>';

ck( 'above the view\'s list, what a Create account sends, which cannot be recalled; under it, the other way a sponsor\'s account is made, and that this view is the way for the rest',
	array(
		false !== strpos( $html, $warning ) && strpos( $html, $warning ) < strpos( $html, "<ul class='subsubsub'>" ),
		false !== strpos( $html, $routes ) && strpos( $html, $routes ) > strpos( $html, '</table>' ),
	),
	array( true, true ) );
ck( 'the account views say none of it', array( has( $all_view, 'Creating an account' ), has( $all_view, 'An approved application creates' ) ), array( false, false ) );
ck( 'no whole address is printed on the view: each contact address is masked, the one way to tell which address an account would be made for; the account views print none at all',
	array( has( $html, 'hello@nova.example' ), has( $html, 'uross@example.test' ), has( $html, 'h***@nova.example' ), has( $all_view, '@' ) ),
	array( false, false, true, false ) );

// Nothing ready: Nova's address gone, and Opal's the administrator's, which provisioning refuses.
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ NOVA ]['contact_email'] = '';
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ OPAL ]['contact_email'] = 'padmin@example.test';
$none_ready                                                                          = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]                                   = $kept_index;

ck( 'an address that belongs to an administrator is not ready either, and says why; with nothing ready the view draws no checkbox, no Create account and nothing of what a Create account sends, and still says where else an account comes from',
	array( cell( record_row( $none_ready, OPAL ), 'account' ), cell( record_row( $none_ready, OPAL ), 'contact' ), substr_count( $none_ready, 'name="records[]"' ), has( $none_ready, '>Create account</a>' ), has( $none_ready, $warning ), has( $none_ready, $routes ) ),
	array( 'That address belongs to an administrator, who already reaches every sponsor.', 'p***@example.test', 0, false, false, true ) );

foreach ( array( NOVA, OPAL, ASH ) as $record ) {
	$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ $record ]['status'] = 'Paused';
}
$none_left                                         = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ] = $kept_index;

ck( 'with every Approved sponsor holding an account, the view counts none and says so, and nothing of what a Create account sends',
	array( record_ids( $none_left ), view_counts( $none_left )['no-account'], has( $none_left, 'Every Approved sponsor has an account.' ), has( $none_left, $warning ) ),
	array( array(), '0', true, false ) );

foreach ( array( WREN, BIRCH, NOVA, OPAL, ASH ) as $record ) {
	$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ $record ]['status'] = 'Paused';
}
$no_approved                                       = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ] = $kept_index;

ck( 'with no sponsor Approved at all, the view says none is, so there is nothing to create, rather than that each has an account',
	array( record_ids( $no_approved ), has( $no_approved, 'No sponsor is Approved yet, so there is nothing to create.' ), has( $no_approved, 'Every Approved sponsor has an account.' ) ),
	array( array(), true, false ) );

echo "\n=== Create account on the ticked sponsors, handled before the screen draws ===\n";

// What the list's form sends besides the choice: core's nonce, the address it came from, and the
// Apply button that sent it, which core names from WordPress 6.7 on, as the version here does.
$sent_with = array(
	'_wpnonce'         => wp_create_nonce( 'bulk-sponsors' ),
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_view=no-account',
	'action2'          => '-1',
	'bulk_action'      => 'Apply',
);
$on_view   = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$tick      = function ( array $records, array $more = array() ) use ( $sent_with ) {
	return press( screen( array_merge( $sent_with, array( 'wpcpm_view' => 'no-account', 'action' => 'create', 'records' => $records ), $more ) ) );
};
$provisioned_in = function () {
	return array_values(
		array_map(
			function ( $entry ) {
				return $entry['sponsor'];
			},
			array_filter(
				$GLOBALS['audit'],
				function ( $entry ) {
					return 'provisioned' === $entry['kind'];
				}
			)
		)
	);
};
// Who the log says made each account: the actor of each provisioned row, by its sponsor.
$provisioned_by = function () {
	$by = array();

	foreach ( $GLOBALS['audit'] as $entry ) {
		if ( 'provisioned' === $entry['kind'] ) {
			$by[ $entry['sponsor'] ] = $entry['actor'];
		}
	}

	return $by;
};

the_site();
manager( 60 );
$both = $tick( array( OPAL, NOVA ) );

ck( 'Create account on Nova and Opal provisions each once, in the view\'s order, the log naming the manager who pressed as the actor of each: an account made from Nova\'s address, and the account at Opal\'s attached, both told to Airtable and both queued an invitation',
	array(
		$provisioned_in(),
		$provisioned_by(),
		count( $GLOBALS['inserted'] ),
		isset( $GLOBALS['inserted'][0] ) ? array( $GLOBALS['inserted'][0]['user_email'], $GLOBALS['inserted'][0]['role'], $GLOBALS['inserted'][0]['display_name'] ) : null,
		WPCPM_Sponsor_Members::sponsor_of( 32 ),
		array_column( array_column( array_column( $GLOBALS['patched'], 1 ), 0 ), 'id' ),
		WPCPM_Mail::queue(),
	),
	array( array( NOVA, OPAL ), array( NOVA => 60, OPAL => 60 ), 1, array( 'hello@nova.example', WPCPM_Roles::ROLE_SPONSOR, 'Nia Example' ), OPAL, array( NOVA, OPAL ), array( 101, 32 ) ) );
ck( 'then back to the No account view, the list\'s nonce the one asked about', array( link_of( $both['redirect'] ), $GLOBALS['nonce_checks'] ), array( $on_view, array( 'bulk-sponsors' ) ) );
ck( 'which says how many accounts were created and how many attached',
	notice_now( array( 'wpcpm_view' => 'no-account' ) ),
	'<div class="notice notice-success is-dismissible"><p>1 account was created and its invitation queued. 1 existing account was attached.</p></div>' );

$after = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );

ck( 'Nova and Opal now have their accounts: the view lists Ash alone, and the account views count eight, Uma among them',
	array( record_ids( $after ), view_counts( $after ) ),
	array( array( ASH ), array( 'all' => '8', 'invited' => '3', 'never-invited' => '5', 'no-account' => '1' ) ) );

// One press creates at most ten: twelve more sponsors ready beside Nova and Opal, all twelve ticked,
// in another order than the view's, so the ten made are the view's first ten.
$rooks = array();
for ( $n = 1; $n <= 12; $n++ ) {
	$rooks[] = sprintf( 'recSPONROOK%06d', $n );
}
$with_rooks = function () use ( $rooks ) {
	foreach ( $rooks as $n => $record ) {
		$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ $record ] = array_merge( WPCPM_Sponsors_Index::empty_row(), index_row( sprintf( 'Rook Example %02d', $n + 1 ), 'Approved', sprintf( 'office@rook%02d.example', $n + 1 ) ), array( 'record_id' => $record ) );
	}
};

the_site();
$with_rooks();
manager( 61 );
$tick( array_reverse( $rooks ) );
$left = record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) ) );

ck( 'with twelve ready sponsors ticked, one press creates ten, the view\'s first ten, and the eleventh and twelfth stay listed beside the three that were never ticked',
	array( count( $GLOBALS['inserted'] ), $provisioned_in(), $left ),
	array( 10, array_slice( $rooks, 0, 10 ), array( NOVA, OPAL, $rooks[10], $rooks[11], ASH ) ) );
ck( 'and the view says how many were created, and that one press creates at most ten, so the rest are ticked and pressed again',
	notice_now( array( 'wpcpm_view' => 'no-account' ) ),
	'<div class="notice notice-success is-dismissible"><p>10 accounts were created, each with its invitation queued. One press creates at most 10; tick the rest and press again.</p></div>' );

// A sponsor whose contact address is a student's account: ready to the view, which knows only the
// address, and refused by the members class when the press tries to attach it.
define( 'TEAL', 'recSPONTANGO00008' );
$with_teal = function () {
	$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ TEAL ] = array_merge( WPCPM_Sponsors_Index::empty_row(), index_row( 'Teal Example Apps', 'Approved', 'sstudent@example.test' ), array( 'record_id' => TEAL ) );
};

the_site();
$with_teal();
$teal_before = cell( record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) ), TEAL ), 'account' );
manager( 62 );
$tick( array( NOVA, TEAL ) );

ck( 'a sponsor the view lists as ready and the members class refuses is named with the reason, beside the one created: Teal\'s address is a student\'s account',
	array( $teal_before, count( $GLOBALS['inserted'] ), $provisioned_in(), WPCPM_Sponsor_Members::sponsor_of( 30 ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 'Ready: the account that already uses this address will be attached', 1, array( NOVA ), '', '<div class="notice notice-error is-dismissible"><p>1 account was created and its invitation queued. Not created: Teal Example Apps: That account belongs to a student. A student cannot represent a sponsor while in the program.</p></div>' ) );

the_site();
manager( 63 );
$GLOBALS['airtable_fails'] = true;
$tick( array( NOVA ) );
$GLOBALS['airtable_fails'] = false;

ck( 'an account made while Airtable would not take the checkbox is counted made, and the view says Airtable could not be told about it',
	array( count( $GLOBALS['inserted'] ), WPCPM_Sponsor_Members::sponsor_of( 101 ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 1, NOVA, '<div class="notice notice-success is-dismissible"><p>1 account was created and its invitation queued. Airtable could not be told about 1 of them: its Dashboard account checkbox is out of step until the next attempt.</p></div>' ) );

the_site();
manager( 75 );
$GLOBALS['airtable_fails'] = true;
$tick( array( OPAL ) );
$GLOBALS['airtable_fails'] = false;

ck( 'and an account attached while Airtable would not take the checkbox is counted attached, not made, and the view says Airtable could not be told about it',
	array( count( $GLOBALS['inserted'] ), WPCPM_Sponsor_Members::sponsor_of( 32 ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 0, OPAL, '<div class="notice notice-success is-dismissible"><p>1 existing account was attached. Airtable could not be told about 1 of them: its Dashboard account checkbox is out of step until the next attempt.</p></div>' ) );

the_site();
manager( 64 );
$GLOBALS['insert_fails'] = true;
$tick( array( NOVA ) );
$GLOBALS['insert_fails'] = false;

ck( 'an account WordPress would not make is named with what WordPress said, and nothing is attached',
	array( count( $GLOBALS['inserted'] ), $provisioned_in(), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 1, array(), '<div class="notice notice-error is-dismissible"><p>Not created: Nova Example Tools: Sorry, that username already exists.</p></div>' ) );

// Past five refused, the outcome names the first five in the view's order and counts the rest: six
// sponsors whose contact address is the student's, ticked together.
the_site();
manager( 65 );
$kilo = array();
foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F' ) as $n => $letter ) {
	$record = 'recSPONKILO' . $letter . sprintf( '%05d', 11 + $n );

	$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ $record ] = array_merge( WPCPM_Sponsors_Index::empty_row(), index_row( 'Kilo Example ' . ( $n + 1 ), 'Approved', 'sstudent@example.test' ), array( 'record_id' => $record ) );
	$kilo[]                                                              = $record;
}
$tick( $kilo );
$student_why = 'That account belongs to a student. A student cannot represent a sponsor while in the program';

ck( 'six that cannot be given an account: nothing is created, and the outcome names the first five in the view\'s order, each with its reason, and counts the sixth',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-error is-dismissible"><p>Not created: Kilo Example 1: ' . $student_why . ', Kilo Example 2: ' . $student_why . ', Kilo Example 3: ' . $student_why . ', Kilo Example 4: ' . $student_why . ', Kilo Example 5: ' . $student_why . ', and 1 more.</p></div>' ) );

// The sponsor policy decides each one, and a refusal is one sponsor not created, not the end of the
// press: asked straight, with no capability behind the person asking, both are refused and named.
the_site();
manager( 66 );
$GLOBALS['caps'] = false;
$refused_all     = run(
	function () {
		return WPCPM_Sponsors::provision_ticked( array( NOVA, OPAL ) );
	}
);
$GLOBALS['caps'] = true;
$policy_no       = 'That is not something your account can do here';

ck( 'the sponsor policy decides each ticked sponsor, and its refusal names that sponsor without ending the press: both are named, neither is made',
	array( $refused_all['died'], $refused_all['error'], $refused_all['value'], $GLOBALS['inserted'], $provisioned_in() ),
	array(
		'',
		'',
		array(
			'provision-failed',
			array(
				'created'     => 0,
				'attached'    => 0,
				'airtable'    => 0,
				'left'        => 0,
				'limit'       => 10,
				'failed'      => array( 'Nova Example Tools: ' . $policy_no, 'Opal Example Studio: ' . $policy_no ),
				'failed_more' => 0,
			),
		),
		array(),
		array(),
	) );

the_site();
manager( 67 );
$tick( array() );

ck( 'with nothing ticked there is nothing to create, and the view says so',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>' ) );

the_site();
manager( 68 );
$tick( array( ASH ) );

ck( 'nor with only a sponsor ticked that is not ready, Ash, which has no contact address: it is left alone, and nothing is created',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>' ) );

// Ticked together, as a hand-made request or a stale page could send them: Wren, which has its
// accounts already, Moss, which is not Approved, and Quay, which has no status at all. None of them
// is on the No account view, so the press makes nothing for any of them.
the_site();
manager( 79 );
$tick( array( WREN, MOSS, QUAY ) );

ck( 'a sponsor with accounts already, one that is not Approved and one with no status, ticked together, create nothing: no account is made or attached, nobody is queued, Airtable is not told, and the view says there was nothing to create',
	array( $GLOBALS['inserted'], $provisioned_in(), WPCPM_Mail::queue(), $GLOBALS['patched'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), array(), array(), array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>' ) );

// Nova ticked and pressed, then ticked again from the page as it stood before the first press and
// pressed again: the second press finds Nova no longer on the No account view.
the_site();
manager( 120 );
$tick( array( NOVA ) );
$first_made = array( count( $GLOBALS['inserted'] ), WPCPM_Sponsor_Members::sponsor_of( 101 ), WPCPM_Mail::queue() );
manager( 121 );
$tick( array( NOVA ) );

ck( 'the same sponsor ticked twice in a row is given one account: the second press makes and attaches nothing, queues nobody, does not tell Airtable again, and the view says there was nothing to create',
	array( $first_made, $GLOBALS['inserted'], $provisioned_in(), WPCPM_Mail::queue(), $GLOBALS['patched'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array( 1, NOVA, array( 101 ) ), array(), array(), array(), array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>' ) );

the_site();
manager( 69 );
$forged = $tick( array( NOVA ), array( '_wpnonce' => 'forged' ) );

ck( 'a press with the wrong nonce dies with WordPress\'s own sentence, before any account is read and before anything is created',
	array( $forged['died'], $GLOBALS['reads'], $GLOBALS['inserted'] ),
	array( 'The link you followed has expired.', array(), array() ) );

the_site();
manager( 70 );
$GLOBALS['caps'] = false;
$no_right        = $tick( array( NOVA ) );
$GLOBALS['caps'] = true;

ck( 'somebody without the program\'s capability is refused before the nonce is asked about, and nothing is created',
	array( $no_right['died'], $GLOBALS['nonce_checks'], $GLOBALS['inserted'] ),
	array( 'You do not have permission to manage the program.', array(), array() ) );

the_site();
manager( 71 );
$from_accounts = press( screen( array_merge( $sent_with, array( 'action' => 'create', 'users' => array( '22' ) ) ) ) );

ck( 'Create account sent from an account view, ticked accounts and all, reads only the ticked sponsors, of which there are none: nothing is created, and the press comes back to that view',
	array( $GLOBALS['inserted'], link_of( $from_accounts['redirect'] ), notice_now() ),
	array(
		array(),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) ),
		'<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>',
	) );

the_site();
manager( 72 );
$tick( array( strtolower( NOVA ) ) );

ck( 'a record ID is read with its case: one in another case names no sponsor the view lists ready, and nothing is created',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>' ) );

the_site();
manager( 73 );
$tick( array( NOVA . 'x', 'Nova', NOVA . "\n" ) );

ck( 'and a value that is not a whole record ID creates nothing, ticked as an ID with a letter more, as a name or with a line break after it; the one pattern the table reads a sponsor\'s identifier by is the Airtable record ID\'s',
	array(
		$GLOBALS['inserted'],
		notice_now( array( 'wpcpm_view' => 'no-account' ) ),
		got(
			run(
				function () {
					return reach( 'record_pattern', 'WPCPM_Sponsors_Table' )->invoke( null );
				}
			),
			function ( $out ) {
				return $out['value'];
			}
		),
	),
	array( array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>', WPCPM_TEST_RECORD_PATTERN ) );

// The search box sends the list's form too, with whatever the bulk select holds: without Apply, the
// action chosen is not carried out on the rows ticked.
the_site();
manager( 74 );
$searched_with = press( screen( array( '_wpnonce' => wp_create_nonce( 'bulk-sponsors' ), '_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_view=no-account', 'action' => 'create', 'action2' => '-1', 'records' => array( NOVA ), 'wpcpm_view' => 'no-account', 's' => 'nova' ) ) );

ck( 'a search pressed with Create account chosen and a sponsor ticked creates nothing: the press comes back to the view, searched, as core\'s own lists do',
	array( $GLOBALS['inserted'], link_of( $searched_with['redirect'] ), $GLOBALS['nonce_checks'] ),
	array( array(), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'nova' ) ), array() ) );

echo "\n=== A row's Create account: a nonce link to the module's provisioning handler ===\n";

the_site();
manager( 80 );
$row_link = actions_in( record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ) ), NOVA ) );
$row_link = isset( $row_link['create'] ) ? $row_link['create'] : '';
$followed = follow( $row_link, 'handle_provision' );

ck( 'followed, it provisions Nova, with its record ID\'s case, under the nonce keyed to it, the log naming the manager who followed it as the actor',
	array( count( $GLOBALS['inserted'] ), $provisioned_in(), $provisioned_by(), WPCPM_Sponsor_Members::sponsor_of( 101 ), $GLOBALS['nonce_checks'] ),
	array( 1, array( NOVA ), array( NOVA => 80 ), NOVA, array( 'wpcpm_sponsor_provision_' . NOVA ) ) );
ck( 'and comes back to the view as the link says it stood, searched, which says the account was created',
	array( link_of( $followed['redirect'] ), notice_now( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'example' ) ),
		'<div class="notice notice-success is-dismissible"><p>The account was created and its invitation queued.</p></div>',
	) );

manager( 81 );
$again = follow( $row_link, 'handle_provision' );

ck( 'followed again once Nova has its account, as a double click does, it makes and attaches nothing and queues no invitation, and says the sponsor already has an account, not that an account was attached',
	array( $GLOBALS['inserted'], $provisioned_in(), WPCPM_Mail::queue(), link_of( $again['redirect'] )[1], notice_now( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ),
	array( array(), array(), array(), array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'example' ), '<div class="notice notice-info is-dismissible"><p>That sponsor already has an account.</p></div>' ) );

// A link left open while the sponsor was given an account at another address, from Manage
// accounts in another tab or by another manager: Ivo acts for Nova now.
the_site();
manager( 77 );
seat( 41, 'Ivo Grant', 'igrant', array( WPCPM_Roles::ROLE_SPONSOR ), member_of( NOVA ) );
$stale_link = follow( $row_link, 'handle_provision' );

ck( 'a link drawn before Nova had an account, followed once Ivo acts for it, makes nothing, tells Airtable nothing and queues no invitation, and says the sponsor already has an account, back on the list as it stood',
	array( $GLOBALS['inserted'], $provisioned_in(), $GLOBALS['patched'], WPCPM_Mail::queue(), WPCPM_Sponsor_Members::sponsor_of( 41 ), link_of( $stale_link['redirect'] )[1], notice_now( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ),
	array( array(), array(), array(), array(), NOVA, array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'example' ), '<div class="notice notice-info is-dismissible"><p>That sponsor already has an account.</p></div>' ) );

the_site();
manager( 82 );
$forged = follow( str_replace( wp_create_nonce( 'wpcpm_sponsor_provision_' . NOVA ), 'forged', $row_link ), 'handle_provision' );

ck( 'with the wrong nonce the link dies before anything is created', array( $forged['died'], $GLOBALS['inserted'] ), array( 'The link you followed has expired.', array() ) );

the_site();
manager( 76 );
$pointed = follow( str_replace( 'wpcpm_sponsor=' . NOVA, 'wpcpm_sponsor=' . OPAL, $row_link ), 'handle_provision' );

ck( 'a link signed for Nova and pointed at Opal is asked about under Opal\'s key and dies at the nonce: nothing is made, and the account at Opal\'s address acts for nobody',
	array( $pointed['died'], $GLOBALS['nonce_checks'], $GLOBALS['inserted'], WPCPM_Sponsor_Members::sponsor_of( 32 ) ),
	array( 'The link you followed has expired.', array( 'wpcpm_sponsor_provision_' . OPAL ), array(), '' ) );

manager( 83 );
$GLOBALS['caps'] = false;
$no_right        = follow( $row_link, 'handle_provision' );
$GLOBALS['caps'] = true;

ck( 'and without the program\'s capability it dies before the nonce is asked about', array( $no_right['died'], $GLOBALS['nonce_checks'], $GLOBALS['inserted'] ), array( 'You do not have permission to manage the program.', array(), array() ) );

/**
 * A row's Create account for any sponsor, as a stale page or a hand-made address could carry it.
 *
 * @param string $record The sponsor's record ID.
 * @return string
 */
function create_link( $record ) {
	return add_query_arg(
		array(
			'action'        => 'wpcpm_sponsor_provision',
			'wpcpm_sponsor' => $record,
			'wpcpm_tab'     => 'accounts',
			'wpcpm_view'    => 'no-account',
			'_wpnonce'      => wp_create_nonce( 'wpcpm_sponsor_provision_' . $record ),
		),
		admin_url( 'admin-post.php' )
	);
}

manager( 84 );
$stale = follow( create_link( ASH ), 'handle_provision' );

ck( 'one provisioning refuses, as a stale page could offer, comes back to the view, which says why: Ash has no contact address',
	array( $GLOBALS['inserted'], link_of( $stale['redirect'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), $on_view, '<div class="notice notice-error is-dismissible"><p>That sponsor has no Contact Email in Airtable. Add one there and sync.</p></div>' ) );

the_site();
$with_teal();
manager( 85 );
$teal_link = actions_in( record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) ), TEAL ) );
follow( isset( $teal_link['create'] ) ? $teal_link['create'] : '', 'handle_provision' );

ck( 'one the members class refuses says why, which the screen dropped before: Teal\'s address is a student\'s account',
	array( $GLOBALS['inserted'], WPCPM_Sponsor_Members::sponsor_of( 30 ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '', '<div class="notice notice-error is-dismissible"><p>That account cannot act for this sponsor: That account belongs to a student. A student cannot represent a sponsor while in the program.</p></div>' ) );

manager( 86 );
$GLOBALS['insert_fails'] = true;
follow( $row_link, 'handle_provision' );
$GLOBALS['insert_fails'] = false;

ck( 'and an account WordPress would not make says what WordPress said',
	array( count( $GLOBALS['inserted'] ), $provisioned_in(), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 1, array(), '<div class="notice notice-error is-dismissible"><p>The account could not be created: Sorry, that username already exists!</p></div>' ) );

manager( 87 );
follow( create_link( strtolower( NOVA ) ), 'handle_provision' );

ck( 'the record is read from the link with its case: one in another case names no sponsor the index holds, and nothing is created',
	array( $GLOBALS['inserted'], $GLOBALS['nonce_checks'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), array( 'wpcpm_sponsor_provision_' . strtolower( NOVA ) ), '<div class="notice notice-error is-dismissible"><p>Only an Approved sponsor can be given an account.</p></div>' ) );

the_site();
manager( 88 );
request( array( 'action' => 'wpcpm_sponsor_provision' ), array( 'wpcpm_sponsor' => NOVA, '_wpnonce' => wp_create_nonce( 'wpcpm_sponsor_provision_' . NOVA ) ) );
$posted = run(
	function () {
		( new WPCPM_Sponsors() )->handle_provision();
	}
);

ck( 'a form that posts the sponsor is still served, under the same nonce: Nova is provisioned, and the press comes back to the Accounts tab',
	array( count( $GLOBALS['inserted'] ), $provisioned_in(), $GLOBALS['nonce_checks'], link_of( $posted['redirect'] ) ),
	array( 1, array( NOVA ), array( 'wpcpm_sponsor_provision_' . NOVA ), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) ) ) );

manager( 78 );
request( array( 'action' => 'wpcpm_sponsor_provision' ), array( 'wpcpm_sponsor' => NOVA, '_wpnonce' => wp_create_nonce( 'wpcpm_sponsor_provision_' . NOVA ) ) );
$posted_again = run(
	function () {
		( new WPCPM_Sponsors() )->handle_provision();
	}
);

ck( 'and posted again once Nova has its account, the form meets the same refusal: nothing is made or attached, and the Accounts tab says the sponsor already has an account',
	array( $GLOBALS['inserted'], $provisioned_in(), link_of( $posted_again['redirect'] ), notice_now() ),
	array( array(), array(), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) ), '<div class="notice notice-info is-dismissible"><p>That sponsor already has an account.</p></div>' ) );

// A term carrying an ampersand, a hash and a space, kept whole through a row's Create account and
// where it lands: each place writes the term as one query argument, encoded, and reads it back whole.
the_site();
manager( 89 );
$odd_term  = 'a&b #1';
$odd_state = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => $odd_term ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ NOVA ]['name'] = 'Nova a&b #1 Example';
$odd_list    = html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => $odd_term ) ) ) );
$odd_actions = actions_in( record_row( $odd_list, NOVA ) );
$odd_create  = link_of( isset( $odd_actions['create'] ) ? $odd_actions['create'] : '' );
$odd_landed  = follow( isset( $odd_actions['create'] ) ? $odd_actions['create'] : '', 'handle_provision' );

ck( 'a term holding an ampersand, a hash and a space finds the sponsor that holds it, and its row\'s Create account and where that lands each keep the term whole',
	array( record_ids( $odd_list ), isset( $odd_create[1]['s'] ) ? $odd_create[1]['s'] : null, link_of( $odd_landed['redirect'] ), count( $GLOBALS['inserted'] ) ),
	array( array( NOVA ), $odd_term, $odd_state, 1 ) );

echo "\n=== The invitations: a row's, the ticked accounts', and the card's ===\n";

the_site();
manager( 90 );
$accounts = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) );
$rows     = html_of( draw( screen() ) );
$ben_link = actions_in( row_for( $rows, 22 ) );
$ben_link = isset( $ben_link['invite'] ) ? $ben_link['invite'] : '';

ck( 'Ben\'s Send invite goes to the Sponsors module\'s invite handler with his account, the tab to come back to and the nonce that handler checks, keyed to his account',
	link_of( $ben_link ),
	array(
		'https://example.test/wp-admin/admin-post.php',
		array(
			'action'    => 'wpcpm_sponsors_invite',
			'user'      => '22',
			'wpcpm_tab' => 'accounts',
			'_wpnonce'  => wp_create_nonce( 'wpcpm_sponsors_invite_22' ),
		),
	) );

// Not `$sent`: at the top of a file a variable is a global, and the stand-in keeps what was sent there.
$ben_sent = follow( $ben_link, 'handle_invite' );

ck( 'followed, the members class sends Ben his invitation now, the sponsor\'s, core\'s message to him alone, and stamps his account as a sponsor\'s; the module\'s sync is never asked; then the list says the email went',
	array( $GLOBALS['mails'], array_column( $GLOBALS['sent'], 'subject' ), array_column( $GLOBALS['sent'], 'notify' ), isset( $GLOBALS['umeta'][22]['wpcpm_sponsor_invited'] ) && is_int( $GLOBALS['umeta'][22]['wpcpm_sponsor_invited'] ), $GLOBALS['sync_invites'], link_of( $ben_sent['redirect'] ), notice_now() ),
	array( array( 22 ), array( '[WPCredits] Your sponsor account is ready' ), array( 'user' ), true, array(), $accounts, '<div class="notice notice-success is-dismissible"><p>Invitation email sent. It replaces the link in any earlier invitation, so ask them to use this newest email.</p></div>' ) );
ck( 'the press came back by a redirect, so the next draw counts afresh: Ben is among the invited now, offered Resend invite',
	array( view_counts( html_of( draw( screen() ) ) ), array_keys( actions_in( row_for( html_of( draw( screen() ) ), 22 ) ) ) ),
	array( array( 'all' => '6', 'invited' => '4', 'never-invited' => '2', 'no-account' => '3' ), array( 'edit', 'view', 'reinvite', 'accounts' ) ) );

the_site();
manager( 91 );
unset( $GLOBALS['umeta'][23]['wpcpm_mentor_invited'], $GLOBALS['umeta'][23]['wpcpm_sponsor_invited'] );
$cleo = actions_in( row_for( html_of( draw( screen() ) ), 23 ) );
follow( isset( $cleo['invite'] ) ? $cleo['invite'] : '', 'handle_invite' );

ck( 'Cleo, never invited and a mentor too, is offered Send invite; followed, she is sent the mentor\'s invitation, the one her account was made for, and stamped under both her roles',
	array( array_keys( $cleo ), $GLOBALS['mails'], array_column( $GLOBALS['sent'], 'subject' ), isset( $GLOBALS['umeta'][23]['wpcpm_mentor_invited'], $GLOBALS['umeta'][23]['wpcpm_sponsor_invited'] ) ),
	array( array( 'edit', 'view', 'invite', 'accounts' ), array( 23 ), array( '[WPCredits] Your mentor account is ready' ), true ) );

/**
 * A row's invitation for any account, under the nonce keyed to it, as a link taken from a row and
 * pointed elsewhere would carry it.
 *
 * @param int $user_id The account.
 * @return string
 */
function invite_link( $user_id ) {
	return add_query_arg(
		array(
			'action'    => 'wpcpm_sponsors_invite',
			'user'      => (int) $user_id,
			'wpcpm_tab' => 'accounts',
			'_wpnonce'  => wp_create_nonce( 'wpcpm_sponsors_invite_' . (int) $user_id ),
		),
		admin_url( 'admin-post.php' )
	);
}

manager( 92 );
follow( invite_link( 999 ), 'handle_invite' );
$gone = array( notice_now(), $GLOBALS['mails'] );

manager( 93 );
follow( invite_link( 32 ), 'handle_invite' );
$no_role = array( notice_now(), $GLOBALS['mails'], isset( $GLOBALS['umeta'][32]['wpcpm_mentor_invited'] ) );

ck( 'a row\'s send that the members class refuses comes back to the Accounts tab, which says the invitation could not be sent, sending nothing: an account that is gone, and Uma\'s, which does not hold the Sponsor role',
	array( $gone, $no_role ),
	array(
		array( '<div class="notice notice-error is-dismissible"><p>The invitation could not be sent.</p></div>', array() ),
		array( '<div class="notice notice-error is-dismissible"><p>The invitation could not be sent.</p></div>', array(), false ),
	) );

the_site();
manager( 94 );
$GLOBALS['umeta'][21]['wpcpm_sponsor_invited'] = time() - 60;
$nell = actions_in( row_for( html_of( draw( screen() ) ), 21 ) );
follow( isset( $nell['reinvite'] ) ? $nell['reinvite'] : '', 'handle_invite' );

ck( 'and one invited a minute ago is not sent another, which would cancel the link in the first: the tab says so, and nothing is sent',
	array( notice_now(), $GLOBALS['mails'] ),
	array( '<div class="notice notice-warning is-dismissible"><p>Nothing was sent: this person was sent an invitation less than 15 minutes ago, and another one now would cancel the link in it. Ask them to use their newest email, or try again later.</p></div>', array() ) );

// The nonce is keyed to the account, so a link taken from one row is no use on another: the
// handler's own action alone is refused, and so is Ben's link pointed at Hugo's account; a form
// posting the account is not read at all.
the_site();
manager( 95 );
$unkeyed       = follow( str_replace( wp_create_nonce( 'wpcpm_sponsors_invite_22' ), wp_create_nonce( 'wpcpm_sponsors_invite' ), $ben_link ), 'handle_invite' );
$unkeyed_asked = $GLOBALS['nonce_checks'];
$borrowed      = follow( str_replace( 'user=22', 'user=24', $ben_link ), 'handle_invite' );
$borrow_asked  = array_slice( $GLOBALS['nonce_checks'], count( $unkeyed_asked ) );
$refused_mails = $GLOBALS['mails'];
manager( 96 );
request( array( 'action' => 'wpcpm_sponsors_invite' ), array( 'user_id' => '24', '_wpnonce' => wp_create_nonce( 'wpcpm_sponsors_invite_24' ) ) );
$posted_invite = run(
	function () {
		( new WPCPM_Sponsors() )->handle_invite();
	}
);

ck( 'the link verifies only under the nonce keyed to its account: the handler\'s unkeyed nonce is refused, and so is Ben\'s nonce on Hugo\'s account, each before anything is sent',
	array( $unkeyed['died'], $unkeyed_asked, $borrowed['died'], $borrow_asked, $refused_mails ),
	array( 'The link you followed has expired.', array( 'wpcpm_sponsors_invite_22' ), 'The link you followed has expired.', array( 'wpcpm_sponsors_invite_24' ), array() ) );
ck( 'and an account posted by a form is not read: no form in the plugin posts one, so the request names no account, dies at the nonce keyed to none, and sends nothing',
	array( $posted_invite['died'], $GLOBALS['nonce_checks'], $GLOBALS['mails'] ),
	array( 'The link you followed has expired.', array( 'wpcpm_sponsors_invite_0' ), array() ) );

// Followed by somebody without the program's capability, Ben's own link dies at the capability:
// the nonce keyed to his account is never asked about, no account is read for it, nothing is sent.
the_site();
manager( 106 );
$GLOBALS['caps'] = false;
$invite_no_right = follow( $ben_link, 'handle_invite' );
$GLOBALS['caps'] = true;

ck( 'a row\'s invitation followed by somebody without the program\'s capability dies before the nonce keyed to the account is asked about, reads no account and sends nothing',
	array( $invite_no_right['died'], $GLOBALS['nonce_checks'], $GLOBALS['reads'], $GLOBALS['mails'], $GLOBALS['sent'] ),
	array( 'You do not have permission to manage the program.', array(), array(), array(), array() ) );

the_site();
manager( 97 );
$invite_with = array(
	'_wpnonce'         => wp_create_nonce( 'bulk-sponsors' ),
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts',
	'action2'          => '-1',
	'bulk_action'      => 'Apply',
);
$queued      = press( screen( array_merge( $invite_with, array( 'action' => 'invite', 'users' => array( '22', '24', '26', '21', '30' ) ) ) ) );

ck( 'Send invite on the ticked accounts queues the three never invited through the accounts base, leaving out Nell, invited before, and the student, who holds no Sponsor role, and comes back to the list, which says how many',
	array( WPCPM_Mail::queue(), link_of( $queued['redirect'] ), notice_now() ),
	array( array( 22, 24, 26 ), $accounts, '<div class="notice notice-success is-dismissible"><p>3 invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

manager( 98 );
press( screen( array_merge( $invite_with, array( 'action' => 'reinvite', 'users' => array( '21', '23' ) ) ) ) );

ck( 'Resend invite queues Nell and Cleo, invited before, one at a time, and says each new link replaces the old',
	array( WPCPM_Mail::queue(), notice_now() ),
	array( array( 21, 23 ), '<div class="notice notice-success is-dismissible"><p>2 invitations queued. They go out with the next batch, and each replaces the link in any earlier invitation.</p></div>' ) );

manager( 99 );
$searched_invite = press( screen( array( '_wpnonce' => wp_create_nonce( 'bulk-sponsors' ), '_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts', 'action' => 'invite', 'action2' => '-1', 'users' => array( '22' ), 's' => 'ben' ) ) );

ck( 'a search pressed with Send invite chosen and Ben ticked queues nothing: the press comes back to the list, searched',
	array( WPCPM_Mail::queue(), link_of( $searched_invite['redirect'] ), $GLOBALS['nonce_checks'] ),
	array( array(), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 's' => 'ben' ) ), array() ) );

the_site();
manager( 100 );
$tab_page = html_of( page_for( screen() ) );
$card     = forms_by_action( $tab_page );
// The card's action by its value, which the module's constant holds (pinned with the screen's names below).
$card = isset( $card['wpcpm_sponsors_bulk_invite'] ) ? $card['wpcpm_sponsors_bulk_invite'] : '';

ck( 'the invitations card counts the three accounts never sent an invitation under any stamp, in words that fit accounts, and its button names the Accounts tab',
	array( has( $card, '>Invite 3 sponsor accounts that have never been invited</button>' ), hidden_fields_of( $card )['wpcpm_tab'] ?? null, has( $tab_page, '<div class="wpcpm-card wpcpm-invites"><h2>Invitations</h2>' ) ),
	array( true, 'accounts', true ) );
// The card's question is shared by every audience's screen, so its words fit any audience's noun.
ck( 'it asks before it sends, of the three, in the plural',
	has( $tab_page, 'data-wpcpm-confirm="Send an invitation to 3 of the sponsor accounts? They cannot be recalled once sent."' ),
	true );

// The press is the manager's next request. WPCPM_Flash remembers within one run of PHP what it took
// for a person, and drawing the tab above took this one's, so the press is made by somebody new.
manager( 102 );
$pressed = post_to( 'handle_bulk_invite', 'wpcpm_sponsors_bulk_invite', array( 'wpcpm_tab' => 'accounts' ) );

ck( 'pressed, it queues the three and comes back to the Accounts tab, which says so',
	array( WPCPM_Mail::queue(), link_of( $pressed['redirect'] ), notice_now() ),
	array( array( 22, 24, 26 ), $accounts, '<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

// The button pressed with a forged nonce: by somebody without the program's capability it dies
// before the nonce is asked about, and by a manager with WordPress's own sentence, its own nonce the
// only one asked about. The three never invited are still there to queue, and neither queues them.
the_site();
manager( 107 );
$GLOBALS['caps'] = false;
$card_no_right   = post_to( 'handle_bulk_invite', 'wpcpm_sponsors_bulk_invite', array( 'wpcpm_tab' => 'accounts', '_wpnonce' => 'forged' ) );
$GLOBALS['caps'] = true;

ck( 'the button pressed by somebody without the program\'s capability, with a forged nonce, dies before the nonce is asked about and queues nobody',
	array( $card_no_right['died'], $GLOBALS['nonce_checks'], WPCPM_Mail::queue() ),
	array( 'You do not have permission to manage the program.', array(), array() ) );

manager( 108 );
$card_forged = post_to( 'handle_bulk_invite', 'wpcpm_sponsors_bulk_invite', array( 'wpcpm_tab' => 'accounts', '_wpnonce' => 'forged' ) );

ck( 'and the button pressed by a manager with a forged nonce dies with WordPress\'s own sentence, its own nonce the only one asked about, and queues nobody',
	array( $card_forged['died'], $GLOBALS['nonce_checks'], WPCPM_Mail::queue() ),
	array( 'The link you followed has expired.', array( 'wpcpm_sponsors_bulk_invite' ), array() ) );

the_site();
manager( 103 );
$GLOBALS['umeta'][24]['wpcpm_sponsor_invited'] = 1780000000;
$GLOBALS['umeta'][26]['wpcpm_sponsor_invited'] = 1780000000;
$one_page                                      = html_of( page_for( screen() ) );
$one_card                                      = forms_by_action( $one_page );

ck( 'with one left, the button says so in the singular',
	has( isset( $one_card['wpcpm_sponsors_bulk_invite'] ) ? $one_card['wpcpm_sponsors_bulk_invite'] : '', '>Invite 1 sponsor account that has never been invited</button>' ),
	true );
ck( 'and the card asks about the one, in the singular',
	has( $one_page, 'data-wpcpm-confirm="Send an invitation to 1 of the sponsor accounts? It cannot be recalled once sent."' ),
	true );

echo "\n=== Manage accounts: one sponsor's accounts, a view of the Accounts tab ===\n";

// A list table built on a screen hands that screen its columns, for Screen Options to offer: a screen
// is planted for the load hook to build on, so a table built there leaves its columns' filter on it.
// The list's own load proves the measure: it makes its one table.
the_site();
manager( 130 );
$GLOBALS['list_screen']    = (object) array(
	'id'   => 'wpcpm-test-planted',
	'base' => 'wpcpm-test-planted',
);
$GLOBALS['screen_options'] = array();
$tables_made               = function () {
	return isset( $GLOBALS['hooks']['manage_wpcpm-test-planted_columns'] ) ? count( $GLOBALS['hooks']['manage_wpcpm-test-planted_columns'] ) : 0;
};
$view_load                 = press( screen( array( 'wpcpm_sponsor' => WREN ) ) );
$view_made                 = $tables_made();
$view_left                 = array( $GLOBALS['screen_options'], $GLOBALS['queries'], $GLOBALS['current_screen']->reader_content );
$list_load                 = press( screen() );
$list_made                 = $tables_made() - $view_made;
$GLOBALS['list_screen']    = null;
$loaded_as                 = function ( $out ) {
	return $out['value'];
};

ck( 'with a sponsor in the Accounts tab\'s address, the load hook builds no list for the view drawn in its place: no table is made, so none hands the screen its columns, no rows-per-page option and no list headings are set, no account is read, and the page goes on; the list\'s own load makes its one table',
	array( got( $view_load, $loaded_as ), $view_made, $view_left, got( $list_load, $loaded_as ), $list_made ),
	array( 'drawn', 0, array( array(), array(), array() ), 'drawn', 1 ) );

the_site();
manager( 131 );
$view       = html_of( page_for( screen( array( 'wpcpm_sponsor' => WREN ) ) ) );
$view_body  = (string) strstr( $view, '</nav>' );
$view_locks = $GLOBALS['locked_reads'];
$view_asks  = array_map(
	function ( $args ) {
		return array( arg( $args, 'meta_key' ), arg( $args, 'meta_value' ) );
	},
	$GLOBALS['queries']
);

ck( 'the view draws, in place of the accounts locked today, the invitations card and the list, Wren\'s name, the way back to the accounts, its accounts by name and address, each with Remove, then the Attach account form, in that order, and asks for that one sponsor\'s accounts and nothing else, the day\'s locks among them',
	array(
		in_order(
			$view_body,
			array(
				'name'   => '<h2>Wren Example Labs</h2>',
				'back'   => '>Back to the accounts</a>',
				'ben'    => '<li>Ben Okoye (bokoye@example.test) <form',
				'nell'   => '<li>Nell Ito (nito@example.test) <form',
				'attach' => '>Attach account</button>',
			)
		),
		has( $view, 'locked out of changes' ),
		has( $view, 'wpcpm-invites' ),
		has( $view, '<form method="get"' ),
		has( $view, 'Sponsor accounts <span' ),
		$view_asks,
		$view_locks,
	),
	array( array( 'name', 'back', 'ben', 'nell', 'attach' ), false, false, false, false, array( array( WPCPM_Sponsor_Members::META_RECORD_ID, WREN ) ), 0 ) );
ck( 'the way back is the list, on the Accounts tab', back_of( $view ), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) ) );
ck( 'every form on it posts the Accounts tab and the sponsor under the members handler\'s nonce: Ben\'s and Nell\'s Remove, then Attach account',
	form_fields_of( $view, 'wpcpm_sponsor_members' ),
	array(
		array( '_wpnonce' => wp_create_nonce( 'wpcpm_sponsor_members' ), 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => WREN, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => '22' ),
		array( '_wpnonce' => wp_create_nonce( 'wpcpm_sponsor_members' ), 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => WREN, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => '21' ),
		array( '_wpnonce' => wp_create_nonce( 'wpcpm_sponsor_members' ), 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'attach', 'wpcpm_sponsor' => WREN, 'wpcpm_tab' => 'accounts' ),
	) );

// The press is the next request, by somebody new, for the reason `manager()` gives. Uma, a mentor,
// may act for a sponsor too, and is attached by her address, as a manager types it in the form.
manager( 132 );
$attach_form = array_values(
	array_filter(
		form_fields_of( $view, 'wpcpm_sponsor_members' ),
		function ( $fields ) {
			return isset( $fields['wpcpm_op'] ) && 'attach' === $fields['wpcpm_op'];
		}
	)
);
$attached    = post_to( 'handle_members', 'wpcpm_sponsor_members', array_merge( isset( $attach_form[0] ) ? $attach_form[0] : array(), array( 'wpcpm_email' => 'uross@example.test' ) ) );
$landed      = '' !== $attached['redirect'] ? html_of( page_for( link_of( $attached['redirect'] )[1] ) ) : '';

ck( 'Attach account, posted as a browser posts the form with Uma\'s address, makes her act for Wren and comes back to Wren\'s accounts, which print the outcome above the bar and list her beside Ben and Nell',
	array( link_of( $attached['redirect'] ), WPCPM_Sponsor_Members::is_member( 32, WREN ), notices_in( $landed ), in_order( $landed, array( 'notice' => 'is-dismissible', 'bar' => '<nav class="nav-tab-wrapper', 'uma' => '<li>Uma Ross (uross@example.test) <form' ) ), substr_count( $landed, '<li>' ) ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => WREN ) ),
		true,
		'<div class="notice notice-success is-dismissible"><p>The account now acts for the sponsor.</p></div>',
		array( 'notice', 'bar', 'uma' ),
		3,
	) );

// Attach account and Remove pressed with a forged nonce, each with the fields its form on Wren's
// accounts posts (pinned above): by somebody without the program's capability each dies before the
// nonce is asked about, and by a manager with WordPress's own sentence, the members handler's nonce
// the only one asked about. Uma is attached to nobody and Ben still acts for Wren, each with the
// roles they had; nobody is queued, and Airtable is not told.
$attach_forged_fields = array( 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'attach', 'wpcpm_sponsor' => WREN, 'wpcpm_tab' => 'accounts', 'wpcpm_email' => 'uross@example.test', '_wpnonce' => 'forged' );
$remove_forged_fields = array( 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => WREN, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => '22', '_wpnonce' => 'forged' );
$members_left         = function () {
	return array( WPCPM_Sponsor_Members::sponsor_of( 32 ), $GLOBALS['users'][32]->roles, WPCPM_Sponsor_Members::is_member( 22, WREN ), $GLOBALS['users'][22]->roles, WPCPM_Mail::queue(), $GLOBALS['patched'] );
};
$members_as_they_were = array( '', array( WPCPM_Roles::ROLE_MENTOR ), true, array( WPCPM_Roles::ROLE_SPONSOR ), array(), array() );

the_site();
manager( 137 );
$GLOBALS['caps'] = false;
$attach_no_right = post_to( 'handle_members', 'wpcpm_sponsor_members', $attach_forged_fields );
$GLOBALS['caps'] = true;

ck( 'Attach account pressed by somebody without the program\'s capability, with a forged nonce, dies before the nonce is asked about: nobody is attached, no role changes, nobody is queued and Airtable is not told',
	array( $attach_no_right['died'], $GLOBALS['nonce_checks'], $members_left() ),
	array( 'You do not have permission to manage the program.', array(), $members_as_they_were ) );

manager( 138 );
$attach_forged = post_to( 'handle_members', 'wpcpm_sponsor_members', $attach_forged_fields );

ck( 'Attach account pressed by a manager with a forged nonce dies with WordPress\'s own sentence, the members handler\'s nonce the only one asked about: nobody is attached, no role changes, nobody is queued and Airtable is not told',
	array( $attach_forged['died'], $GLOBALS['nonce_checks'], $members_left() ),
	array( 'The link you followed has expired.', array( 'wpcpm_sponsor_members' ), $members_as_they_were ) );

the_site();
manager( 139 );
$GLOBALS['caps'] = false;
$remove_no_right = post_to( 'handle_members', 'wpcpm_sponsor_members', $remove_forged_fields );
$GLOBALS['caps'] = true;

ck( 'Remove pressed on Ben by somebody without the program\'s capability, with a forged nonce, dies before the nonce is asked about: nobody is detached and no role changes',
	array( $remove_no_right['died'], $GLOBALS['nonce_checks'], $members_left() ),
	array( 'You do not have permission to manage the program.', array(), $members_as_they_were ) );

manager( 140 );
$remove_forged = post_to( 'handle_members', 'wpcpm_sponsor_members', $remove_forged_fields );

ck( 'Remove pressed on Ben by a manager with a forged nonce dies with WordPress\'s own sentence, the members handler\'s nonce the only one asked about: nobody is detached and no role changes',
	array( $remove_forged['died'], $GLOBALS['nonce_checks'], $members_left() ),
	array( 'The link you followed has expired.', array( 'wpcpm_sponsor_members' ), $members_as_they_were ) );

the_site();
manager( 133 );
$moss_view = html_of( page_for( screen( array( 'wpcpm_sponsor' => MOSS ) ) ) );

ck( 'Moss, which is not Approved, is drawn Finn with Remove, then the sentence that says why nothing else is offered, and no Attach account',
	array( has( $moss_view, '<li>Finn Hale (fhale@example.test) <form' ), has( $moss_view, '<p class="description">This sponsor is not Approved, so no account can be attached to it and its posting cannot be switched.</p>' ), array_column( form_fields_of( $moss_view, 'wpcpm_sponsor_members' ), 'wpcpm_op' ) ),
	array( true, true, array( 'detach' ) ) );

manager( 134 );
$gone = html_of( page_for( screen( array( 'wpcpm_sponsor' => 'recSPONGONEX00099' ) ) ) );

ck( 'a record the index does not hold is said to be none, with the way back to the list, and nothing is read or printed for it: no account, no list, not the record',
	array( has( $gone, '<p>No sponsor record has that ID. If it should be here, run the sponsors sync on the <a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&#038;tab=sponsors">Sponsors</a> tab.</p>' ), back_of( $gone ), $GLOBALS['queries'], has( $gone, 'recSPONGONEX00099' ), has( $gone, '<form method="post"' ) ),
	array( true, array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) ), array(), false, false ) );

the_site();
manager( 135 );
$from_list     = actions_in( record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ) ), NOVA ) );
$accounts_link = link_of( isset( $from_list['accounts'] ) ? $from_list['accounts'] : '' );
$nova_view     = html_of( page_for( $accounts_link[1] ) );

ck( 'a No account row\'s Manage accounts opens that sponsor\'s accounts with where the list stood, and the view\'s way back returns to the list as it stood, the No account view searched; with no account yet, the view says so and offers Attach account alone',
	array( $accounts_link, has( $nova_view, '<h2>Nova Example Tools</h2>' ), back_of( $nova_view ), has( $nova_view, '<p class="description">No account yet.</p>' ), array_column( form_fields_of( $nova_view, 'wpcpm_sponsor_members' ), 'wpcpm_op' ) ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => NOVA, 'wpcpm_view' => 'no-account', 's' => 'example' ) ),
		true,
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'example' ) ),
		true,
		array( 'attach' ),
	) );

$sorted_rows = html_of( draw( screen( array( 'wpcpm_view' => 'invited', 'orderby' => 'sponsor', 'order' => 'desc' ) ) ) );
$nell_row    = actions_in( row_for( $sorted_rows, 21 ) );

ck( 'an account row\'s Manage accounts carries where the list stands as well: its view and its sort',
	link_of( isset( $nell_row['accounts'] ) ? $nell_row['accounts'] : '' ),
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => WREN, 'wpcpm_view' => 'invited', 'orderby' => 'sponsor', 'order' => 'desc' ) ) );

// A term carrying an ampersand, a hash and a space, kept whole through a row's Manage accounts and the
// view's way back: each place writes the term as one query argument, encoded, and reads it back whole.
the_site();
manager( 136 );
$odd_term = 'a&b #1';
$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'][ NOVA ]['name'] = 'Nova a&b #1 Example';
$odd_row  = actions_in( record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => $odd_term ) ) ) ), NOVA ) );
$odd_link = link_of( isset( $odd_row['accounts'] ) ? $odd_row['accounts'] : '' );
$odd_back = back_of( html_of( page_for( $odd_link[1] ) ) );

ck( 'a term holding an ampersand, a hash and a space is kept whole through a row\'s Manage accounts and the view\'s way back',
	array( $odd_link, $odd_back ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => NOVA, 'wpcpm_view' => 'no-account', 's' => $odd_term ) ),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => $odd_term ) ),
	) );

echo "\n=== The screen's names, and the Accounts tab top to bottom ===\n";

$names = run(
	function () {
		return array(
			WPCPM_Sponsors::PER_PAGE_OPTION,
			WPCPM_Sponsors_Table::per_page_option(),
			WPCPM_Sponsors::TAB_ACCOUNTS,
			WPCPM_Accounts_Table::TAB,
			WPCPM_Sponsors_Table::bulk_nonce_action(),
			WPCPM_Sponsors::FLASH_DETAIL,
			WPCPM_Sponsors::ACTION_INVITE,
			reach( 'invite_action', 'WPCPM_Sponsors_Table' )->invoke( null ),
			WPCPM_Sponsors::ACTION_BULK,
			WPCPM_Sponsors_Table::role(),
			WPCPM_Sponsors_Table::invite_meta(),
			WPCPM_Sponsor_Members::META_INVITED,
			WPCPM_Sponsors::PROVISION_LIMIT,
			WPCPM_Sponsors_Table::VIEW_NO_ACCOUNT,
			WPCPM_Sponsors_Table::ACTION_CREATE,
		);
	}
);

ck( 'the module\'s names for the option and the tab are the table\'s; the bulk nonce, the count\'s channel and the two invitation actions are the Sponsors\', a row\'s invitation the one the table links; the role and the stamp are the sponsor account\'s, the stamp the members class keeps; one press creates at most ten',
	got(
		$names,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'wpcpm_sponsors_per_page', 'wpcpm_sponsors_per_page', 'accounts', 'accounts', 'bulk-sponsors', 'sponsors_admin_detail', 'wpcpm_sponsors_invite', 'wpcpm_sponsors_invite', 'wpcpm_sponsors_bulk_invite', WPCPM_Roles::ROLE_SPONSOR, 'wpcpm_sponsor_invited', 'wpcpm_sponsor_invited', 10, 'no-account', 'create' ) );

the_site();
manager( 104 );
WPCPM_Settings::$connected = false;
WPCPM_Flash::set( 'sponsors_admin', 'invited' );
$top_to_bottom             = html_of( page_for( screen() ) );
WPCPM_Settings::$connected = true;

ck( 'the Accounts tab reads top to bottom: the press\'s notice and the warning that Airtable is not connected, each once, above the tab bar, then the accounts locked today, the invitations card and the list, and nothing under it',
	array(
		in_order(
			$top_to_bottom,
			array(
				'notice'      => '<div class="notice notice-success is-dismissible">',
				'warning'     => 'Airtable is not connected yet, so no sponsors can be synced.',
				'bar'         => '<nav class="nav-tab-wrapper',
				'locked'      => '1 sponsor account is locked out of changes for the rest of today.',
				'invitations' => '<div class="wpcpm-card wpcpm-invites">',
				'list'        => '<h2>Sponsor accounts <span class="wpcpm-count">6</span></h2>',
			)
		),
		substr_count( $top_to_bottom, 'Airtable is not connected yet' ),
		substr_count( $top_to_bottom, 'is-dismissible' ),
		substr_count( $top_to_bottom, '<div class="wpcpm-card' ),
		substr_count( (string) strstr( $top_to_bottom, '<h2>Sponsor accounts <span' ), '<h2>' ),
	),
	array( array( 'notice', 'warning', 'bar', 'locked', 'invitations', 'list' ), 1, 1, 2, 1 ) );
ck( 'and no address is printed anywhere on it: the locked accounts are named by name and username, as the list read them', array( has( $top_to_bottom, '@' ), has( $top_to_bottom, 'Ben Okoye (bokoye)' ) ), array( false, true ) );

the_site();
manager( 105 );
page_for( screen() );
$plain_draw = $GLOBALS['locked_reads'];
page_for( screen( array( 'orderby' => 'status' ) ) );
$status_draw = $GLOBALS['locked_reads'];

ck( 'one draw of the Accounts tab asks for the accounts locked today once, for the notice that names them and for the Status cells together, sorted by status or not',
	array( $plain_draw, $status_draw ),
	array( 1, 1 ) );

echo "\n";
ck( 'and nothing asked a stand-in for anything it does not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
