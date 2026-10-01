<?php
/**
 * The Institutions screen's Accounts tab: every institution account as a WordPress list table,
 * searched, sorted and paged; a fourth view, No account, of the Confirmed institutions that have
 * none yet, where the missing accounts are created; and the invitations sent from the list.
 *
 * What this pins, and why each part is worth pinning:
 *
 * - The table's columns and their values (Name, Username, Institution, Member since, Status), the
 *   row actions under the name (Edit, View page, the Institution Dashboard as the account's
 *   institution, then the invitation, then Manage members) and the views' counts: All, Invited and
 *   Never invited count the accounts alone, and No account counts the institutions that have none.
 * - Institution, Member since and Status sort by what the members class writes on each account,
 *   and no query can sort by any of them: the institution is a record ID whose name lives in the
 *   pipeline index, the date sits inside a serialized row, and "locked today" is no meta at all.
 *   The table reads every account the list may hold and orders them itself, by the value, then the
 *   name, then the ID, so no account shows on two pages while another is on none.
 * - The search reaches the institution's name as well as the username, the email and the name.
 * - No account is the provisioning worklist: the Confirmed institutions with no live member, ready
 *   ones first, each saying whether Airtable holds an address for it and why it cannot be given an
 *   account yet, never the address. Create account is that view's bulk action and a ready row's own
 *   action, and that view alone offers it; the invitations are the account views' alone.
 * - The bulk Create account keeps the rules of the button it replaces: nothing is created while any
 *   Confirmed institution has no agreement recorded, and otherwise each ticked institution that is
 *   ready is provisioned, as the manager who pressed, the others named in what the screen says. A
 *   row's Create account is a nonce link to the module's handler for one institution, which reads
 *   the record with its case and comes back to the view as it stood.
 * - The invitations: a row's goes out at once through the institutions sync, the ticked ones are
 *   queued through the accounts base, the card counts the accounts never sent one by any stamp, and
 *   an account that is a mentor too is sent the mentor's invitation and stamped for both.
 * - Manage members: on an account row, the view of the institution the account acts for, or of
 *   the one it left once its membership ended, where Re-add puts it back; on every No account
 *   row, that institution's. The view is the Accounts tab with the institution in its address,
 *   drawn in place of the list, which the load hook then does not build: the institution's name,
 *   the way back to the list as it stood, its members block and the form a signed agreement is
 *   uploaded with, every form there coming back to it. A record the pipeline index does not hold
 *   is said to be none, and a viewer who may not manage the program is drawn the name and the way
 *   back and nothing else.
 * - The screen's wiring: the load hook on the Accounts tab alone, the rows-per-page option under
 *   the Institutions' own name and its save, hooked at boot.
 * - No address is printed on the list, on either kind of view, or anywhere on the tab but the
 *   Manage members view, which prints the members' addresses as the Institution Dashboard's People
 *   card does: a manager adds and removes accounts by address.
 *
 * WordPress is stood in for as far as the screen reaches, by the stand-ins every accounts screen's
 * suite shares (bin/stubs/accounts-screen.php), with `include` modeled, since this list hands
 * WordPress the IDs its search finds by an institution's name. The pipeline index, the institutions
 * sync (its provisioning rule, its provisioning and its row invitation), the mail layer, the flash,
 * the return and the tab bar are the plugin's own; the members, the agreements, the roster's locks
 * and the dashboards' pages are stood in for by their contracts, the members read from the fixture's
 * stamps as the real class reads them. The Manage members view's two blocks are the plugin's own as
 * well, the People class's backstop and the agreement panel's upload form, with the policy both
 * decide through, so the view is read as a manager gets it. The list table is
 * bin/stubs/class-wp-list-table.php, loaded through this suite's own `wpcpm_load_accounts_tables()`;
 * its markup is close to core's and is not core's. Each step is run, and what it drew is read,
 * through the helpers the screen suites share (bin/stubs/screen-helpers.php).
 *
 * Run from the plugin root:  php bin/test-institutions-accounts.php
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

// This list hands WordPress the IDs its search finds by an institution's name.
$GLOBALS['query_include'] = true;

$GLOBALS['settled']      = array(); // Record ID => whether its agreement is recorded.
$GLOBALS['locked']       = array(); // The accounts the roster's ceiling locked today, by ID.
$GLOBALS['locked_reads'] = 0;       // How many times the locked accounts were asked for.
$GLOBALS['member_reads'] = array(); // Every institution whose live members were asked for.
$GLOBALS['inserted']     = array(); // Every account wp_insert_user() was asked to make.
$GLOBALS['attached']     = array(); // Every membership the members class was asked to begin.
$GLOBALS['insert_fails'] = false;   // Whether WordPress refuses to make the next account.
$GLOBALS['pending']      = array(); // Record ID => the ID of its signed copy waiting for review.
$GLOBALS['posts']        = array(); // Post ID => WP_Post: the signed copies waiting for review.
$GLOBALS['pmeta']        = array(); // Post ID => meta key => value.

/* ---- WordPress, beyond the shared stand-ins ------------------------------ */

function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}
function is_email( $email ) {
	return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : false;
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
/** A post, as far as the Manage members view's upload block reads one: a signed copy in review. */
class WP_Post {
	public $ID        = 0;
	public $post_type = '';
	public function __construct( $id, $type ) {
		$this->ID        = (int) $id;
		$this->post_type = (string) $type;
	}
}
function get_post( $id ) {
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}
function get_post_meta( $id, $key = '', $single = false ) {
	return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : ( $single ? '' : array() );
}
/**
 * Core's class-name cleaning, which the upload form's ids are built with: escaped octets dropped,
 * then anything but a letter, a digit, an underscore or a hyphen.
 *
 * @param string $classname The name.
 * @param string $fallback  What to clean instead when nothing is left.
 * @return string
 */
function sanitize_html_class( $classname, $fallback = '' ) {
	$clean = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $classname ) );

	return ( '' === $clean && '' !== (string) $fallback ) ? sanitize_html_class( $fallback ) : $clean;
}

/* ---- the plugin's other pieces, stood in for by their contracts ---------- */

/**
 * The settings, as far as the screen reads them: whether Airtable is connected, and whether the
 * sync creates institution accounts too, which the No account view says.
 */
class WPCPM_Settings {
	public static $connected = true;
	public static $values    = array( 'institution_provision' => false );
	public static function is_connected() { return self::$connected; }
	public static function get() { return self::$values; }
	public static function get_value( $key, $fallback = null ) { return isset( self::$values[ $key ] ) ? self::$values[ $key ] : $fallback; }
}

/** The Institution Dashboard's page, which the list names and a row's View page opens. */
class WPCPM_Institutions_Dashboard {
	public static $url = 'https://example.test/institution-dashboard/';
	public static function page_url() { return self::$url; }
	public static function init() {}
	public static function ensure_page() {}
}

/** The Mentor Dashboard's page, which a mentor's invitation names. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-dashboard/'; }
	public static function get_mentee_count( $user_id ) { return 0; }
}

/**
 * The members class, as far as the list and provisioning reach it: its keys; who acts for an
 * institution and who did, read from the fixture's stamps as the real class reads them, the stamp
 * matched with its case and the flag at 1; and `attach()`, which provisioning calls for the account
 * it made, kept and writing the stamps the real one writes, and, as the real one does, clearing the
 * stamp an account kept of the institution it comes back to. The Manage members view reads the ways
 * a membership comes about, and the policy behind it every institution an account acts for.
 */
class WPCPM_Institution_Members {
	const META_RECORD_ID     = 'wpcpm_institution_record_id';
	const META_ACTIVE        = 'wpcpm_institution_active';
	const META_RECORD_ID_WAS = 'wpcpm_institution_record_id_was';
	const META_MEMBERSHIP    = 'wpcpm_institution_membership';
	const META_INVITED       = 'wpcpm_inst_invited';
	const META_PROFILE       = 'wpcpm_institution_profile';
	const HOW_PROVISIONED    = 'provisioned';
	const HOW_APPROVED       = 'approved';
	const HOW_MANAGER        = 'manager';
	const HOW_INVITED        = 'invited';
	const HOW_LEGACY         = 'legacy';

	public static function members_of( $record_id ) {
		$GLOBALS['member_reads'][] = (string) $record_id;

		return self::stamped( self::META_RECORD_ID, $record_id, true );
	}
	public static function former_members_of( $record_id ) {
		return self::stamped( self::META_RECORD_ID_WAS, $record_id, false );
	}
	public static function institution_of( $user = null ) {
		$id   = $user instanceof WP_User ? (int) $user->ID : (int) $user;
		$meta = isset( $GLOBALS['umeta'][ $id ] ) ? $GLOBALS['umeta'][ $id ] : array();

		return ( isset( $meta[ self::META_RECORD_ID ], $meta[ self::META_ACTIVE ] ) && 1 === (int) $meta[ self::META_ACTIVE ] ) ? (string) $meta[ self::META_RECORD_ID ] : '';
	}
	public static function memberships_of( $user = null ) {
		$record = self::institution_of( $user );

		return '' === $record ? array() : array( $record );
	}
	public static function attach( $user_id, $record_id, $how, $actor_id ) {
		$GLOBALS['attached'][] = array( (int) $user_id, (string) $record_id, (string) $how, (int) $actor_id );

		update_user_meta( $user_id, self::META_RECORD_ID, (string) $record_id );
		update_user_meta( $user_id, self::META_ACTIVE, 1 );
		update_user_meta(
			$user_id,
			self::META_MEMBERSHIP,
			array(
				'since'  => 1790000000,
				'by'     => (int) $actor_id,
				'how'    => (string) $how,
				'invite' => 0,
			)
		);

		if ( isset( $GLOBALS['umeta'][ (int) $user_id ][ self::META_RECORD_ID_WAS ] ) && (string) $record_id === (string) $GLOBALS['umeta'][ (int) $user_id ][ self::META_RECORD_ID_WAS ] ) {
			delete_user_meta( $user_id, self::META_RECORD_ID_WAS );
		}

		return true;
	}
	private static function stamped( $key, $record_id, $live ) {
		$found = array();

		foreach ( $GLOBALS['users'] as $id => $user ) {
			$meta = isset( $GLOBALS['umeta'][ $id ] ) ? $GLOBALS['umeta'][ $id ] : array();

			if ( ! isset( $meta[ $key ] ) || (string) $record_id !== (string) $meta[ $key ] ) {
				continue;
			}

			if ( $live && ( ! isset( $meta[ self::META_ACTIVE ] ) || 1 !== (int) $meta[ self::META_ACTIVE ] ) ) {
				continue;
			}

			$found[] = $user;
		}

		return $found;
	}
}

/**
 * The agreements, as provisioning asks them, whether one is recorded for an institution, and as the
 * Manage members view's upload block asks them: the upload's action, its two kinds, and the summary
 * that says whether a signed copy is already waiting, which one is for an institution a check gives
 * one, with the post type, the institution's key and the two actions the copy's download link and
 * Withdraw form are drawn with. The words for a bulk record on file ask for the last one, of which
 * there is none; the Agreements tab's card draws the bulk form with its action and the longest
 * location note it takes.
 */
class WPCPM_Institution_Agreement {
	const ACTION_UPLOAD      = 'wpcpm_agreement_upload';
	const ACTION_DOWNLOAD    = 'wpcpm_agreement_download';
	const ACTION_WITHDRAW    = 'wpcpm_agreement_withdraw';
	const ACTION_ON_FILE_ALL = 'wpcpm_agreement_on_file_all';
	const MAX_LOCATION       = 200;
	const POST_TYPE          = 'wpcpm_agreement';
	const META_INSTITUTION   = '_wpcpm_agr_institution';
	const KIND_TEMPLATE      = 'template';
	const KIND_OWN           = 'own';
	public static function is_settled( $record_id ) { return ! empty( $GLOBALS['settled'][ (string) $record_id ] ); }
	public static function summary( $record_id ) {
		return isset( $GLOBALS['pending'][ (string) $record_id ] ) ? array( 'pending_id' => (int) $GLOBALS['pending'][ (string) $record_id ] ) : array();
	}
	public static function last_on_file_all() { return null; }
	public static function init() {}
}

/** The roster's ceiling: the accounts it locked for the rest of today, each ask counted. */
class WPCPM_Institution_Roster {
	const ARG_VIEW = 'wpcpm_institution_view';
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

/** A mentor request's outcomes, which the screen's one map of outcomes takes in. */
class WPCPM_Institution_Request {
	public static function messages() { return array(); }
	public static function init() {}
}

/** What the module boots besides its screen. None is drawn here. */
class WPCPM_Institution_Audit {
	public static function init() {}
}
class WPCPM_Ceiling {
	public static function init() {}
}
class WPCPM_Institution_Application {
	public static function init() {}
}
class WPCPM_Agreement_Generate {
	public static function init() {}
}
class WPCPM_Institution_Student_Form {
	public static function init() {}
}
class WPCPM_Institution_Notes {
	public static function init() {}
}
/** A member inviting a colleague, whose outcome the Manage members view prints where it lands: its own suite reads it. */
class WPCPM_Institution_Invite {
	public static function init() {}
	public static function render_message( $record_id ) {}
}
class WPCPM_Institution_Students {
	public static function init() {}
}
class WPCPM_Institution_Export {
	public static function init() {}
}
class WPCPM_Institution_Import {
	public static function init() {}
}
class WPCPM_Institution_Import_Form {
	public static function init() {}
}
class WPCPM_Institution_Create {
	public static function init() {}
}
class WPCPM_Semester_Report {
	public static function init() {}
}
class WPCPM_Semester_Report_Screen {
	public static function init() {}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
// The students sync keeps the three-hour recurrence every sync's schedule is placed on, and the
// mentors sync the record ID's shape the pipeline index reads.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
// The Manage members view's two blocks and the policy both decide through, the plugin's own.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php';

// The invitation's template, hooked as the plugin hooks it, so the stand-in for core's invitation
// records the subject each account was sent.
WPCPM_Mail::init();

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, which there is
 * no WordPress here to lend, then the accounts base and the Institutions table, the second once it
 * exists, so a copy of the plugin without it fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';

	if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-table.php' ) ) {
		require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-table.php';
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
			'page' => 'wpcpm-institutions',
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
function reach( $name, $class = 'WPCPM_Institutions' ) {
	$method = new ReflectionMethod( $class, $name );

	// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method;
}

/**
 * The institution accounts card, as the screen draws it for a request.
 *
 * @param array $query The query string.
 * @return array What run() kept; the markup is its value.
 */
function draw( array $query ) {
	request( $query );
	$GLOBALS['queries']      = array();
	$GLOBALS['locked_reads'] = 0;
	$GLOBALS['member_reads'] = array();

	return run(
		function () {
			$method = reach( 'render_accounts_list' );
			ob_start();

			try {
				$method->invoke( new WPCPM_Institutions() );
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
			$module = new WPCPM_Institutions();
			$module->load_screen();

			return 'drawn';
		}
	);
}

/**
 * The Institutions screen as WordPress draws it for a request: the screen's load hook, then the
 * page, from the one module.
 *
 * @param array $query The query string.
 * @return array What run() kept; the markup is its value.
 */
function page_for( array $query ) {
	request( $query );
	$GLOBALS['queries']        = array();
	$GLOBALS['current_screen'] = new WPCPM_Stub_Screen();
	$GLOBALS['member_reads']   = array();
	$GLOBALS['locked_reads']   = 0;

	return run(
		function () {
			$module = new WPCPM_Institutions();
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
 * Where a drawn page's Back to the accounts goes: its address and its query arguments, or nothing.
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
			( new WPCPM_Institutions() )->$method();
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
			( new WPCPM_Institutions() )->$handler();
		}
	);
}

/**
 * A fresh program manager for a press: WPCPM_Flash remembers what it took for a person within one
 * run of PHP, so each press that leaves a notice is made by somebody new. The invitation queue and
 * its run go with the last press; the site's own options stay.
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
	$GLOBALS['attached']     = array();
	$GLOBALS['insert_fails'] = false;
	$GLOBALS['member_reads'] = array();
	$GLOBALS['locked_reads'] = 0;

	unset( $GLOBALS['opts'][ WPCPM_Mail::QUEUE_OPTION ], $GLOBALS['opts'][ WPCPM_Mail::RUN_OPTION ] );
}

/**
 * The record IDs a drawn table's rows hold, from the record ID each prints under its name in the
 * Institution column, in order: a row that is not ready draws no checkbox to read one from.
 *
 * @param string $html Markup.
 * @return string[]
 */
function record_ids( $html ) {
	preg_match_all( '/<code class="wpcpm-inst-record">([^<]*)<\/code>/', between( $html, '<tbody', '</tbody>' ), $found );

	return $found[1];
}

/**
 * One row of a drawn table, by its checkbox's HTML id: an account's, or a ready record's.
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
 * One record row of a drawn table, by the record ID it prints in its Institution cell, which every
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
	return preg_match( '/<h2>Institution accounts <span class="wpcpm-count">([^<]*)<\/span><\/h2>/', (string) $html, $found ) ? $found[1] : null;
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
 *                     whole), `count` (a view's count) or `search` (the IDs a search finds).
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
						return $ids && -1 === $number;
				}

				return false;
			}
		)
	);
}

/* ---- fixtures ------------------------------------------------------------- */

// Eight institution records, made up, and a ninth record an account names that the index lost. The
// record IDs run in another order than the names they stand for (Zeta's B, the lost record's M,
// Alpha's Y), so a list sorted by the stamp rather than by the name it stands for is told apart.
define( 'ZETA', 'recINSTBRAVO00001' );
define( 'ALPHA', 'recINSTYANKE00002' );
define( 'ECOLE', 'recINSTECOLE00003' );
define( 'DELTA', 'recINSTDELTA00004' );
define( 'ECHO_REC', 'recINSTECHOX00005' );
define( 'FOX', 'recINSTFOXTR00006' );
define( 'GOLF', 'recINSTGOLFX00007' );
define( 'HOTEL', 'recINSTHOTEL00008' );
define( 'XRAY', 'recINSTMIKEX00009' );

/**
 * One row of the pipeline index, in the shape the institutions sync writes it.
 *
 * @param string $record The record ID.
 * @param string $name   The name, as Airtable holds it.
 * @param string $stage  The stage.
 * @param string $email  The Contact Email, or '' for none.
 * @return array
 */
function index_row( $record, $name, $stage, $email ) {
	return array(
		'record_id'      => $record,
		'name'           => $name,
		'stage'          => $stage,
		'country'        => '',
		'country_name'   => '',
		'city'           => '',
		'website'        => '',
		'contact_person' => '',
		'contact_email'  => $email,
		'created'        => '2025-09-01',
		'consent'        => true,
		'confirmed_on'   => '',
		'agreement'      => array(),
	);
}

/**
 * Write the pipeline index as the sync leaves it: one option, rows keyed by record ID.
 *
 * @param array[] $rows The rows.
 */
function write_index( array $rows ) {
	$keyed = array();

	foreach ( $rows as $row ) {
		$keyed[ $row['record_id'] ] = $row;
	}

	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array(
		'v'    => WPCPM_Institutions_Index::VERSION,
		'read' => 1790000000,
		'rows' => $keyed,
	);
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
 * @param string   $record The institution.
 * @param int|null $since  When the membership began, or null for an account the facts predate.
 * @return array
 */
function member_of( $record, $since = null ) {
	$meta = array(
		WPCPM_Institution_Members::META_RECORD_ID => $record,
		WPCPM_Institution_Members::META_ACTIVE    => 1,
	);

	if ( null !== $since ) {
		$meta[ WPCPM_Institution_Members::META_MEMBERSHIP ] = array(
			'since'  => $since,
			'by'     => 1,
			'how'    => 'approved',
			'invite' => 0,
		);
	}

	return $meta;
}

/**
 * The site: eight institution records, four of them Confirmed; six institution accounts across
 * three institutions; a student and an administrator, neither on the list.
 *
 * Zeta Example Institute is Confirmed with its agreement recorded, and Nora and Ben act for it: Nora
 * invited, Ben never invited and locked out of roster changes today; Ada was its member, her stamp
 * moved to `_was` and her flag 0, and she still holds the Institution role: `detach()` takes it away
 * unless the account is a WordPress administrator, so "Membership ended" shows on the live list only
 * for an account that kept the role or was given it again. Alpha Example College is Confirmed with
 * no agreement recorded, and Carmen, a mentor too, invited under both her roles, and Eli, an account
 * older than the membership facts, act for it. Hugo acts for an institution the index no longer
 * holds. École Example Charlie is Confirmed with its agreement recorded and nobody acting for it,
 * ready for an account, its name stored with a space at the end; Delta Example School is the same
 * but for its Contact Email, which belongs to the student's account. The other four are at earlier
 * or closed stages.
 */
function the_site() {
	$GLOBALS['users']     = array();
	$GLOBALS['umeta']     = array();
	$GLOBALS['opts']      = array(
		'date_format' => 'F j, Y',
		'blogname'    => 'WPCredits',
	);
	$GLOBALS['no_editor'] = false;
	$GLOBALS['settled']   = array(
		ZETA  => true,
		ECOLE => true,
		DELTA => true,
	);
	$GLOBALS['locked']    = array( 22 );

	WPCPM_Settings::$values            = array( 'institution_provision' => false );
	WPCPM_Settings::$connected         = true;
	WPCPM_Institutions_Dashboard::$url = 'https://example.test/institution-dashboard/';

	write_index(
		array(
			index_row( ZETA, 'Zeta Example Institute', 'Confirmed', 'office@zeta.example' ),
			index_row( ALPHA, 'Alpha Example College', 'Confirmed', 'registrar@alpha.example' ),
			index_row( ECOLE, 'École Example Charlie ', 'Confirmed', 'office@ecole.example' ),
			index_row( DELTA, 'Delta Example School', 'Confirmed', 'sstudent@example.test' ),
			index_row( ECHO_REC, 'Echo Example Academy', 'Agreement Sent', 'hello@echo.example' ),
			index_row( FOX, 'Foxtrot Example University', 'Under Review', '' ),
			index_row( GOLF, 'Golf Example Polytechnic', 'Not Moving Forward', 'golf@golf.example' ),
			index_row( HOTEL, 'Hotel Example Institute', 'First Contact Made', '' ),
		)
	);

	seat( 21, 'Nora Quist', 'nquist', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( ZETA, 1767225600 ) + array( 'wpcpm_inst_invited' => 1768000000 ) );
	seat( 22, 'Ben Okafor', 'bokafor', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( ZETA, 1772323200 ) );
	seat( 23, 'Carmen Ibarra', 'cibarra', array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_MENTOR ), member_of( ALPHA, 1769904000 ) + array( 'wpcpm_mentor_invited' => 1770000000, 'wpcpm_inst_invited' => 1770000000 ) );
	seat( 24, 'Hugo Lind', 'hlind', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( XRAY, 1775001600 ) );
	seat(
		25,
		'Ada Moreau',
		'amoreau',
		array( WPCPM_Roles::ROLE_INSTITUTION ),
		array(
			WPCPM_Institution_Members::META_RECORD_ID_WAS => ZETA,
			WPCPM_Institution_Members::META_ACTIVE        => 0,
			WPCPM_Institution_Members::META_MEMBERSHIP    => array(
				'since'  => 1764547200,
				'by'     => 1,
				'how'    => 'provisioned',
				'invite' => 0,
			),
			'wpcpm_inst_invited'                          => 1765000000,
		)
	);
	seat( 26, 'Eli Brandt', 'ebrandt', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( ALPHA ) );
	seat( 30, 'Sam Student', 'sstudent', array( WPCPM_Roles::ROLE_STUDENT ) );
	seat( 31, 'Pat Admin', 'padmin', array( 'administrator' ) );

	manager( 1 );
}

// The request that saves a rows-per-page choice, as WordPress makes it: the module booted on
// `plugins_loaded`, then the save's filter applied in `set_screen_options()`, before the menu, the
// screen's load hook or any table exists. In a process of its own, because the checks below declare
// the tables, and a class once declared cannot be undeclared.
if ( isset( $argv[1] ) && 'child-save' === $argv[1] ) {
	$declared = class_exists( 'WPCPM_Institutions_Table', false );
	$saved    = run(
		function () {
			( new WPCPM_Institutions() )->boot();

			return apply_filters( 'set_screen_option_wpcpm_institutions_per_page', false, 'wpcpm_institutions_per_page', '50' );
		}
	);

	echo json_encode(
		array(
			'before' => $declared,
			'saved'  => $saved['value'],
			'error'  => $saved['error'],
			'after'  => class_exists( 'WPCPM_Institutions_Table', false ),
		)
	);
	exit( 0 );
}

// The invitations card's button as WordPress runs it: admin-post.php, where nothing has loaded the
// list tables the card's reading of the role and the stamp comes from. In a process of its own, for
// the reason the save's run above has one.
if ( isset( $argv[1] ) && 'child-card' === $argv[1] ) {
	the_site();

	$declared = class_exists( 'WPCPM_Institutions_Table', false );
	$pressed  = post_to( 'handle_bulk_invite', 'wpcpm_institutions_bulk_invite', array( 'wpcpm_tab' => 'accounts' ) );

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
// institutions left: the screen prints the outcome above the tab, before anything has built the
// list, and the words for it are the Institutions table's, which nothing has loaded yet. In a process
// of its own, as above.
if ( isset( $argv[1] ) && 'child-notice' === $argv[1] ) {
	the_site();
	manager( 7 );
	WPCPM_Flash::set( 'institutions', 'provision-failed' );
	WPCPM_Flash::set(
		'institutions_admin_detail',
		array(
			'status' => 'provision-failed',
			'names'  => array( 'Delta Example School' ),
			'more'   => 0,
		)
	);
	request( screen() );

	$declared = class_exists( 'WPCPM_Accounts_Table', false );
	$drawn    = run(
		function () {
			ob_start();

			try {
				( new WPCPM_Institutions() )->render_admin_page();
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
	array( class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Institutions_Table', false ) ),
	array( false, false, false ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-save 2>&1', $save_out, $save_status );
$save = (array) json_decode( implode( "\n", $save_out ), true );

ck( 'a rows-per-page choice is saved as WordPress saves it, before any table is loaded: the save, hooked at boot, loads the table itself and keeps 50 under the Institutions\' own name',
	array( $save_status, isset( $save['before'] ) ? $save['before'] : implode( "\n", $save_out ), isset( $save['error'] ) ? $save['error'] : null, isset( $save['saved'] ) ? $save['saved'] : null, isset( $save['after'] ) ? $save['after'] : null ),
	array( 0, false, '', 50, true ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-card 2>&1', $card_out, $card_status );
$card_run = (array) json_decode( implode( "\n", $card_out ), true );

ck( 'the invitations card\'s button, pressed where no list table is loaded, as admin-post.php runs it, loads the table it reads the role and the stamps from, queues the three never invited and comes back to the Accounts tab',
	array( $card_status, isset( $card_run['before'] ) ? $card_run['before'] : implode( "\n", $card_out ), isset( $card_run['error'] ) ? $card_run['error'] : null, isset( $card_run['redirect'] ) ? $card_run['redirect'] : null, isset( $card_run['queue'] ) ? $card_run['queue'] : null ),
	array( 0, false, '', 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts', array( 22, 24, 26 ) ) );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-notice 2>&1', $notice_out, $notice_status );
$notice_run = (array) json_decode( implode( "\n", $notice_out ), true );

ck( 'a Create account on the ticked institutions is worded on a tab drawn without the screen\'s load hook too: its sentence is the Institutions table\'s, loaded for it',
	array( $notice_status, isset( $notice_run['before'] ) ? $notice_run['before'] : implode( "\n", $notice_out ), isset( $notice_run['error'] ) ? $notice_run['error'] : null, isset( $notice_run['notices'] ) ? $notice_run['notices'] : null ),
	array( 0, false, '', '<div class="notice notice-error is-dismissible"><p>These could not be given an account: Delta Example School. The No account view says why for each.</p></div>' ) );

the_site();
$elsewhere = array();

foreach ( array(
	'the menu\'s own address, the queue' => array( 'page' => 'wpcpm-institutions' ),
	'the Sync and storage tab'           => screen( array( 'tab' => 'sync' ) ),
	'the Pipeline tab'                   => screen( array( 'tab' => 'pipeline' ) ),
) as $case => $query ) {
	$elsewhere[ $case ] = got(
		press( $query ),
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Institutions_Table', false ), $GLOBALS['screen_options'], $GLOBALS['queries'], $GLOBALS['current_screen']->reader_content );
		}
	);
}

ck( 'on every tab but Accounts, the screen\'s own address among them, which opens the queue, the load hook leaves the list alone: no list table loaded, no rows-per-page option offered, no account read and no list headings set, and the page goes on',
	$elsewhere,
	array_fill_keys( array( 'the menu\'s own address, the queue', 'the Sync and storage tab', 'the Pipeline tab' ), array( 'drawn', false, false, array(), array(), array() ) ) );

$loaded = press( screen() );

ck( 'on the Accounts tab the load hook loads core\'s list table, the accounts base and the Institutions table, and lets the page go on',
	got(
		$loaded,
		function ( $out ) {
			return array( $out['value'], class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Institutions_Table', false ) );
		}
	),
	array( 'drawn', true, true, true ) );
ck( 'it puts the rows-per-page option on the screen: twenty until somebody chooses, kept under the Institutions\' own name',
	isset( $GLOBALS['screen_options']['per_page'] ) ? $GLOBALS['screen_options']['per_page'] : 'no option added',
	array(
		'default' => 20,
		'option'  => 'wpcpm_institutions_per_page',
	) );
ck( 'and prepares the list there, before the page\'s header, as core\'s own lists do', count( queries( 'page' ) ), 1 );
ck( 'it names the list\'s three headings for screen readers, which core leaves out unless the screen sets them',
	$GLOBALS['current_screen']->reader_content,
	array(
		'heading_views'      => 'Filter institution accounts list',
		'heading_pagination' => 'Institution accounts list navigation',
		'heading_list'       => 'Institution accounts list',
	) );

echo "\n=== The table: a checkbox, then Name, Username, Institution, Member since and Status ===\n";

the_site();
$first = draw( screen() );
$html  = html_of( $first );

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", between( $html, '<thead>', '</thead>' ), $header_ids );

ck( 'the headers are the checkbox, then the five columns in order', $header_ids[1], array( 'cb', 'name', 'login', 'institution', 'since', 'status' ) );
ck( 'each says what it holds, and each sorts',
	array( has( $html, '<span>Name</span>' ), has( $html, '<span>Username</span>' ), has( $html, '<span>Institution</span>' ), has( $html, '<span>Member since</span>' ), has( $html, '<span>Status</span>' ) ),
	array( true, true, true, true, true ) );
ck( 'a row per institution account, by name: Ada, Ben, Carmen, Eli, Hugo, Nora; the student and the administrator are not on it', row_ids( $html ), array( 25, 22, 23, 26, 24, 21 ) );

$cells_of = function ( $row ) {
	return array( cell( $row, 'institution' ), cell( $row, 'since' ), cell( $row, 'status' ) );
};

ck( 'Nora\'s row: her name to her account\'s editor, her username, the institution she acts for by its name in the index, the day her membership began in the site\'s date format, Active',
	array( cell( row_for( $html, 21 ), 'name' ), cell( row_for( $html, 21 ), 'login' ), $cells_of( row_for( $html, 21 ) ) ),
	array( '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=21">Nora Quist</a></strong>', '<code>nquist</code>', array( 'Zeta Example Institute', 'January 1, 2026', 'Active' ) ) );
ck( 'Ben\'s, locked out of roster changes for the rest of today, says so after Active',
	$cells_of( row_for( $html, 22 ) ),
	array( 'Zeta Example Institute', 'March 1, 2026', 'Active, locked today' ) );
ck( 'Carmen\'s, a mentor too, is her institution\'s like any other member\'s',
	$cells_of( row_for( $html, 23 ) ),
	array( 'Alpha Example College', 'February 1, 2026', 'Active' ) );
ck( 'Hugo acts for an institution the index no longer holds: the record ID stands in for its name',
	$cells_of( row_for( $html, 24 ) ),
	array( 'recINSTMIKEX00009', 'April 1, 2026', 'Active' ) );
ck( 'Ada\'s membership ended: her stamp moved aside and her flag is 0, so she acts for no institution, and the day it began is kept',
	$cells_of( row_for( $html, 25 ) ),
	array( '', 'December 1, 2025', 'Membership ended' ) );
ck( 'Eli\'s account is older than the membership facts: no day is written, so none is printed',
	$cells_of( row_for( $html, 26 ) ),
	array( 'Alpha Example College', '', 'Active' ) );

$actions = array();
foreach ( array( 21, 22, 24, 25 ) as $id ) {
	$actions[ $id ] = actions_in( row_for( $html, $id ) );
}

ck( 'under each name, Edit, View page for an account that acts for an institution, then the invitation, Resend invite for Nora and Ada, invited before, Send invite for Ben and Hugo, then Manage members, Ada\'s too, whose membership ended',
	array_map( 'array_keys', $actions ),
	array(
		21 => array( 'edit', 'view', 'reinvite', 'members' ),
		22 => array( 'edit', 'view', 'invite', 'members' ),
		24 => array( 'edit', 'view', 'invite', 'members' ),
		25 => array( 'edit', 'reinvite', 'members' ),
	) );
ck( 'which say so', array_map( 'strip_tags', $actions[21] ), array( 'edit' => 'Edit', 'view' => 'View page', 'reinvite' => 'Resend invite', 'members' => 'Manage members' ) );
ck( 'Manage members opens the Accounts tab\'s view of the institution the account acts for, Nora\'s and Hugo\'s, the record the index lost among them, and Ada\'s of the one she left, where Re-add puts her back',
	array(
		link_of( isset( $actions[21]['members'] ) ? $actions[21]['members'] : '' ),
		link_of( isset( $actions[24]['members'] ) ? $actions[24]['members'] : '' ),
		link_of( isset( $actions[25]['members'] ) ? $actions[25]['members'] : '' ),
	),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_institution' => ZETA ) ),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_institution' => XRAY ) ),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_institution' => ZETA ) ),
	) );
ck( 'View page opens the Institution Dashboard as the account\'s institution, through the switcher\'s own argument, and Edit the account\'s editor',
	array( link_of( isset( $actions[21]['view'] ) ? $actions[21]['view'] : '' ), link_of( isset( $actions[24]['view'] ) ? $actions[24]['view'] : '' ), link_of( isset( $actions[21]['edit'] ) ? $actions[21]['edit'] : '' ) ),
	array(
		array( 'https://example.test/institution-dashboard/', array( 'wpcpm_institution_view' => ZETA ) ),
		array( 'https://example.test/institution-dashboard/', array( 'wpcpm_institution_view' => XRAY ) ),
		array( 'https://example.test/wp-admin/user-edit.php', array( 'user_id' => '21' ) ),
	) );
ck( 'the views: All six accounts, three Invited, three Never invited, and No account counting the two Confirmed institutions with none',
	view_counts( $html ),
	array(
		'all'           => '6',
		'invited'       => '3',
		'never-invited' => '3',
		'no-account'    => '2',
	) );
ck( 'the card counts the accounts, every page of them, and names the Institution Dashboard\'s address',
	array( heading_count( $html ), has( $html, '<p>Institution Dashboard: <a href="https://example.test/institution-dashboard/">https://example.test/institution-dashboard/</a></p>' ) ),
	array( '6', true ) );
ck( 'the accounts locked today are asked for once a draw, not once a row', $GLOBALS['locked_reads'], 1 );

// Two states of an account the site above does not hold: Otto's live stamp is no record ID at all,
// with the flag at 1, so it is printed as what it says, and nothing that opens a record is offered;
// Pia's stamp was moved aside with no flag left, so her membership ended and she acts for none, and
// Manage members opens the institution she left.
seat( 32, 'Otto Vance', 'ovance', array( WPCPM_Roles::ROLE_INSTITUTION ), array( WPCPM_Institution_Members::META_RECORD_ID => 'not-a-record', WPCPM_Institution_Members::META_ACTIVE => 1 ) );
seat( 33, 'Pia Lowe', 'plowe', array( WPCPM_Roles::ROLE_INSTITUTION ), array( WPCPM_Institution_Members::META_RECORD_ID_WAS => ZETA ) );
$odd_states = html_of( draw( screen() ) );
$odd_ones   = array(
	32 => actions_in( row_for( $odd_states, 32 ) ),
	33 => actions_in( row_for( $odd_states, 33 ) ),
);
unset( $GLOBALS['users'][32], $GLOBALS['users'][33], $GLOBALS['umeta'][32], $GLOBALS['umeta'][33] );

ck( 'a live stamp that names no record is printed as it reads, Active, with Edit and the invitation alone: no View page and no Manage members',
	array( $cells_of( row_for( $odd_states, 32 ) ), array_keys( $odd_ones[32] ) ),
	array( array( 'not-a-record', '', 'Active' ), array( 'edit', 'invite' ) ) );
ck( 'a stamp moved aside with no flag is Membership ended, acting for no institution, and Manage members opens the one it left',
	array( $cells_of( row_for( $odd_states, 33 ) ), array_keys( $odd_ones[33] ), link_of( isset( $odd_ones[33]['members'] ) ? $odd_ones[33]['members'] : '' ) ),
	array( array( '', '', 'Membership ended' ), array( 'edit', 'invite', 'members' ), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_institution' => ZETA ) ) ) );

$form = between( $html, '<form method="get"', '</form>' );

ck( 'the list is one form, sent to the screen by GET on its Accounts tab, holding the checkboxes, the bulk actions and their nonce, and the search box',
	array( has( $form, '<input type="hidden" name="page" value="wpcpm-institutions" />' ), has( $form, '<input type="hidden" name="tab" value="accounts" />' ), has( $form, 'name="users[]"' ), has( $form, '<select name="action"' ), has( $form, 'value="' . wp_create_nonce( 'bulk-institutions' ) . '"' ), has( $form, 'name="s"' ) ),
	array( true, true, true, true, true, true ) );

$GLOBALS['list_screen'] = (object) array(
	'id'   => 'wpcredits-program_page_wpcpm-institutions',
	'base' => 'wpcredits-program_page_wpcpm-institutions',
);
$offered                = run(
	function () {
		request( screen() );
		new WPCPM_Institutions_Table();

		return array_keys( apply_filters( 'manage_wpcredits-program_page_wpcpm-institutions_columns', array() ) );
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
	array( 'cb', 'login', 'institution', 'since', 'status' ) );

WPCPM_Institutions_Dashboard::$url = '';
$GLOBALS['no_editor']              = true;
$bare_html                         = html_of( draw( screen() ) );
$bare                              = row_for( $bare_html, 21 );
WPCPM_Institutions_Dashboard::$url = 'https://example.test/institution-dashboard/';
$GLOBALS['no_editor']              = false;

ck( 'with no Institution Dashboard page and no right to the editor, the name is plain, the invitation and Manage members the two actions, and the card says the page is missing',
	array( cell( $bare, 'name' ), array_keys( actions_in( $bare ) ), has( $bare_html, '<p class="wpcpm-warning">The Institution Dashboard page is missing. Re-activate the plugin to recreate it.</p>' ) ),
	array( '<strong>Nora Quist</strong>', array( 'reinvite', 'members' ), true ) );

$kept_index                                                           = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ ZETA ]['name'] = ' Zeta & <b>Co</b> ';
$marked                                                               = html_of( draw( screen() ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]                = $kept_index;

ck( 'an institution\'s name is printed trimmed and as text, whatever the index holds',
	array( cell( row_for( $marked, 21 ), 'institution' ), has( $marked, '<b>Co</b>' ) ),
	array( 'Zeta &amp; &lt;b&gt;Co&lt;/b&gt;', false ) );

$GLOBALS['users'] = array_intersect_key( $GLOBALS['users'], array( 30 => true, 31 => true ) );
$empty_list       = html_of( draw( screen() ) );
the_site();

ck( 'with no institution account at all, the list says it found none', array( row_ids( $empty_list ), has( $empty_list, 'No institution accounts found.' ) ), array( array(), true ) );

echo "\n=== Invited is any stamp: a member who is a mentor too, among them ===\n";

the_site();

ck( 'the Invited view lists Ada, Carmen and Nora, Carmen stamped under both her roles, and the Never invited view Ben, Eli and Hugo',
	array( row_ids( html_of( draw( screen( array( 'wpcpm_view' => 'invited' ) ) ) ) ), row_ids( html_of( draw( screen( array( 'wpcpm_view' => 'never-invited' ) ) ) ) ) ),
	array( array( 25, 23, 21 ), array( 22, 26, 24 ) ) );

unset( $GLOBALS['umeta'][23]['wpcpm_inst_invited'] );
$one_stamp = html_of( draw( screen() ) );

ck( 'with her mentor\'s stamp alone she is Invited all the same, her row offering Resend invite: an account has one password',
	array( view_counts( $one_stamp ), array_keys( actions_in( row_for( $one_stamp, 23 ) ) ) ),
	array( array( 'all' => '6', 'invited' => '3', 'never-invited' => '3', 'no-account' => '2' ), array( 'edit', 'view', 'reinvite', 'members' ) ) );

echo "\n=== The sorts: Name and Username by WordPress, Institution, Member since and Status by the table ===\n";

the_site();
$plain = html_of( draw( screen() ) );

ck( 'Name sorts by display name, Username by username, and Institution, Member since and Status by what each cell shows',
	array( sort_of( $plain, 'name' ), sort_of( $plain, 'login' ), sort_of( $plain, 'institution' ), sort_of( $plain, 'since' ), sort_of( $plain, 'status' ) ),
	array( 'display_name', 'login', 'institution', 'since', 'status' ) );
ck( 'Username sorts Z to A as asked, by WordPress', row_ids( html_of( draw( screen( array( 'orderby' => 'login', 'order' => 'desc' ) ) ) ) ), array( 21, 24, 26, 23, 22, 25 ) );

$by_institution  = draw( screen( array( 'orderby' => 'institution', 'order' => 'asc' ) ) );
$institution_read = $GLOBALS['queries'];

ck( 'Institution A to Z, the institutions\' names as the cells show them, an account acting for none first; each institution\'s accounts by name: Ada; Carmen and Eli at Alpha; Hugo at the record the index lost; Ben and Nora at Zeta',
	got(
		$by_institution,
		function ( $out ) {
			return row_ids( html_of( $out ) );
		}
	),
	array( 25, 23, 26, 24, 22, 21 ) );
ck( 'Institution Z to A, each institution\'s accounts still by name',
	row_ids( html_of( draw( screen( array( 'orderby' => 'institution', 'order' => 'desc' ) ) ) ) ),
	array( 22, 21, 24, 23, 26, 25 ) );
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

$GLOBALS['queries'] = $institution_read;

ck( 'a sort by a value lists every account, counted: one read of every institution account the list may hold, whole, which the table orders itself, and none WordPress is asked to sort by the value or to page',
	array( displaying( html_of( $by_institution ) ), count( queries( 'every' ) ), arg( first_query( 'every' ), 'orderby' ), arg( first_query( 'every' ), 'role' ), count( queries( 'page' ) ) ),
	array( '6 items', 1, 'ID', WPCPM_Roles::ROLE_INSTITUTION, 0 ) );

$GLOBALS['umeta'][1]['wpcpm_institutions_per_page'] = 4;
$second = array(
	'institution' => html_of( draw( screen( array( 'orderby' => 'institution', 'order' => 'asc', 'paged' => '2' ) ) ) ),
	'since'       => html_of( draw( screen( array( 'orderby' => 'since', 'order' => 'desc', 'paged' => '2' ) ) ) ),
	'status'      => html_of( draw( screen( array( 'orderby' => 'status', 'order' => 'asc', 'paged' => '2' ) ) ) ),
);
unset( $GLOBALS['umeta'][1]['wpcpm_institutions_per_page'] );

ck( 'four a page, the second page of each holds the fifth and the sixth in that sort\'s order, and the pagination counts all six',
	array_map(
		function ( $page ) {
			return array( row_ids( $page ), displaying( $page ) );
		},
		$second
	),
	array(
		'institution' => array( array( 22, 21 ), '6 items' ),
		'since'       => array( array( 25, 26 ), '6 items' ),
		'status'      => array( array( 22, 25 ), '6 items' ),
	) );

$sorted_view = html_of( draw( screen( array( 'orderby' => 'institution', 'wpcpm_view' => 'never-invited' ) ) ) );

ck( 'sorted by a value, a view keeps to the view: the three never invited, by institution',
	array( row_ids( $sorted_view ), displaying( $sorted_view ) ),
	array( array( 26, 24, 22 ), '3 items' ) );

preg_match( "/<th scope=\"col\" id='institution' class='([^']*)'/", html_of( $by_institution ), $marked_header );

ck( 'the Institution header says the list is sorted by it, A to Z', isset( $marked_header[1] ) ? $marked_header[1] : '', 'manage-column column-institution sorted asc' );

// The base asks for the name sort in WP_User_Query's array form, so a table that sorts some of its
// columns itself reads what the list is sorted by from the array's first key.
$as_array = run(
	function () {
		request( screen() );
		$found = reach( 'query', 'WPCPM_Institutions_Table' )->invoke(
			new WPCPM_Institutions_Table(),
			array(
				'role'        => WPCPM_Roles::ROLE_INSTITUTION,
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

// A row a page, so the boundary between two pages falls between the two Sam Riveras, whose
// institution, day and status are the same: the table's order has to settle them, or one could
// show on both pages and the other on neither.
the_site();
$GLOBALS['users'] = array_intersect_key( $GLOBALS['users'], array( 30 => true, 31 => true ) );
seat( 51, 'Sam Rivera', 'srivera', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( ZETA, 1767225600 ) );
seat( 52, 'Sam Rivera', 'sriveraortiz', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( ZETA, 1767225600 ) );
seat( 53, 'Tia Rivera', 'trivera', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( ALPHA, 1764547200 ) );
$GLOBALS['locked']                                  = array();
$GLOBALS['umeta'][1]['wpcpm_institutions_per_page'] = 1;

$pages = array();
foreach ( array( 'institution', 'since', 'status' ) as $sort ) {
	foreach ( array( 'asc', 'desc' ) as $way ) {
		foreach ( array( '1', '2', '3' ) as $number ) {
			$pages[ $sort . ' ' . $way ][] = row_ids( html_of( draw( screen( array( 'orderby' => $sort, 'order' => $way, 'paged' => $number ) ) ) ) );
		}
	}
}

unset( $GLOBALS['umeta'][1]['wpcpm_institutions_per_page'] );

ck( 'each Sam Rivera is on a page of their own, by every sort and either way: of one value and one name, the lower ID first',
	$pages,
	array(
		'institution asc'  => array( array( 53 ), array( 51 ), array( 52 ) ),
		'institution desc' => array( array( 51 ), array( 52 ), array( 53 ) ),
		'since asc'        => array( array( 53 ), array( 51 ), array( 52 ) ),
		'since desc'       => array( array( 51 ), array( 52 ), array( 53 ) ),
		'status asc'       => array( array( 51 ), array( 52 ), array( 53 ) ),
		'status desc'      => array( array( 51 ), array( 52 ), array( 53 ) ),
	) );

// PHP's sort is stable since 8.0, and the read comes back in ID order, so on the PHP here the two
// Sam Riveras keep their order whether or not the table's order settles them: the order the table
// sorts by is asked directly, for two accounts of one value and one name, either way round, so the
// ID that settles them on PHP 7.4, whose sort may put two equal items either way round, is pinned
// on any PHP.
$settled_order = run(
	function () {
		$compare = reach( 'compare_accounts', 'WPCPM_Institutions_Table' );
		$results = array();

		foreach ( array( 'Zeta Example Institute', 1767225600 ) as $value ) {
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

echo "\n=== The search: the name, the username, the email and the institution's name ===\n";

the_site();
$found = array();
foreach ( array( 'zeta', 'ALPHA', 'bokafor', 'Quist', 'cibarra@example' ) as $term ) {
	$found[ $term ] = row_ids( html_of( draw( screen( array( 's' => $term ) ) ) ) );
}

ck( 'whatever the case, by the institution\'s name, the username, the name or the email; an account whose membership ended is not found by the institution it left',
	$found,
	array(
		'zeta'            => array( 22, 21 ),
		'ALPHA'           => array( 23, 26 ),
		'bokafor'         => array( 22 ),
		'Quist'           => array( 21 ),
		'cibarra@example' => array( 23 ),
	) );

$plain_index = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ ZETA ]['name'] = 'Zéta Example Institute';
$accented                                                                       = row_ids( html_of( draw( screen( array( 's' => 'zeta' ) ) ) ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]                          = $plain_index;

ck( 'and by the institution\'s name without regard to accents, as the names are sorted: "zeta" finds the accounts acting for Zéta Example Institute',
	$accented,
	array( 22, 21 ) );

$searched = html_of( draw( screen( array( 's' => 'zeta' ) ) ) );

ck( 'WordPress is asked for the accounts its search finds, "contains", over the username, the email and the name; the page is then asked by ID, the accounts at Zeta among them, and with no search of its own',
	array(
		arg( first_query( 'search' ), 'search' ),
		arg( first_query( 'search' ), 'search_columns' ),
		arg( first_query( 'search' ), 'role' ),
		arg( first_query( 'page' ), 'include' ),
		arg( first_query( 'page' ), 'search' ),
		arg( first_query( 'page' ), 'role' ),
	),
	array( '*zeta*', array( 'user_login', 'user_email', 'display_name' ), WPCPM_Roles::ROLE_INSTITUTION, array( 21, 22 ), 'absent', WPCPM_Roles::ROLE_INSTITUTION ) );
ck( 'the views count the whole list, not the search, as WordPress\'s own views do',
	view_counts( $searched ),
	array(
		'all'           => '6',
		'invited'       => '3',
		'never-invited' => '3',
		'no-account'    => '2',
	) );

$nobody = html_of( draw( screen( array( 's' => 'zzz' ) ) ) );

ck( 'a search nobody matches says the list found nobody, asking WordPress for an ID nobody holds rather than for every account',
	array( row_ids( $nobody ), has( $nobody, 'No institution accounts found.' ), arg( first_query( 'page' ), 'include' ) ),
	array( array(), true, array( 0 ) ) );

$sorted_search = html_of( draw( screen( array( 's' => 'alpha', 'orderby' => 'status', 'order' => 'desc' ) ) ) );

ck( 'sorted by a value, a search keeps to what it found', array( row_ids( $sorted_search ), displaying( $sorted_search ) ), array( array( 23, 26 ), '2 items' ) );

echo "\n=== No account: the Confirmed institutions with no account yet, a view of their own ===\n";

the_site();
$all_view = html_of( draw( screen() ) );
$all_read = $GLOBALS['member_reads'];
$records  = draw( screen( array( 'wpcpm_view' => 'no-account' ) ) );
$html     = html_of( $records );
$read     = $GLOBALS['member_reads'];

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", between( $html, '<thead>', '</thead>' ), $record_headers );
preg_match( "/<li class='no-account'>(.*?)<\/li>/", $all_view, $no_account_link );

ck( 'the view\'s link follows the three invitation views, on the Accounts tab, counting the institutions it lists',
	array( link_of( isset( $no_account_link[1] ) ? $no_account_link[1] : '' ), strip_tags( isset( $no_account_link[1] ) ? $no_account_link[1] : '' ) ),
	array( array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) ), 'No account (2)' ) );
ck( 'on it: the checkbox, then Institution, Contact and Account, the institution the row\'s header; a row per Confirmed institution with no live member, the ready one first: École, then Delta',
	array( $record_headers[1], record_ids( $html ), row_ids( $html ) ),
	array( array( 'cb', 'institution', 'contact', 'account' ), array( ECOLE, DELTA ), array() ) );

$ecole = record_row( $html, ECOLE );
$delta = record_row( $html, DELTA );

ck( 'École\'s row: its name as the index holds it, trimmed, over its record ID; Airtable holds an address for it, not printed; Ready',
	array( cell( $ecole, 'institution' ), cell( $ecole, 'contact' ), cell( $ecole, 'account' ) ),
	array( '<strong>École Example Charlie</strong><br /><code class="wpcpm-inst-record">recINSTECOLE00003</code>', 'email on record', 'Ready' ) );
ck( 'Delta\'s: its address belongs to an account already, so the Account column says why in the institutions sync\'s own words, and the row\'s one action is Manage members, where an account is added by hand',
	array( cell( $delta, 'contact' ), cell( $delta, 'account' ), array_keys( actions_in( $delta ) ), substr_count( $delta, 'toggle-row' ) ),
	array( 'email on record', 'The Contact Email already belongs to an account on this site, which is a conflict and not a match. Under Manage members that account is adopted only when it is a mentor&#039;s; for any other, correct the Contact Email in Airtable or the account&#039;s address first.', array( 'members' ), 1 ) );

$create = actions_in( $ecole );

ck( 'a ready row\'s actions are Create account, then Manage members, under its name',
	array( array_keys( $create ), array_map( 'strip_tags', $create ), substr_count( $ecole, 'toggle-row' ) ),
	array( array( 'create', 'members' ), array( 'create' => 'Create account', 'members' => 'Manage members' ), 1 ) );
ck( 'a nonce link to the module\'s handler for one institution: the record with its case, the tab and the view to come back to, under a nonce keyed to the institution',
	link_of( isset( $create['create'] ) ? $create['create'] : '' ),
	array(
		'https://example.test/wp-admin/admin-post.php',
		array(
			'action'            => 'wpcpm_institutions_provision_one',
			'wpcpm_institution' => ECOLE,
			'wpcpm_tab'         => 'accounts',
			'wpcpm_view'        => 'no-account',
			'_wpnonce'          => wp_create_nonce( 'wpcpm_institutions_provision_one_' . ECOLE ),
		),
	) );
ck( 'a ready record\'s checkbox posts its record ID under records[], labeled with its name; a row that is not ready draws none, since Create account could only refuse it; no row posts an account',
	array(
		has( $ecole, '<input type="checkbox" name="records[]" id="wpcpm-record-recINSTECOLE00003" value="recINSTECOLE00003" /><label for="wpcpm-record-recINSTECOLE00003"><span class="screen-reader-text">Select École Example Charlie</span></label>' ),
		has( $delta, 'type="checkbox"' ),
		substr_count( $html, 'name="records[]"' ),
		has( $html, 'name="users[]"' ),
	),
	array( true, false, 1, false ) );

$account_actions = array();
foreach ( array( 21, 22, 23, 24, 25, 26 ) as $id ) {
	$account_actions = array_merge( $account_actions, array_keys( actions_in( row_for( $all_view, $id ) ) ) );
}

ck( 'Create account is the view\'s bulk action and a ready row\'s, and the invitations are not offered there; the account views offer the two invitations, and never Create account',
	array( bulk_of( $html ), has( $html, '>Send invite<' ), has( $html, '>Resend invite<' ), bulk_of( $all_view ), in_array( 'create', $account_actions, true ), has( $all_view, 'Create account' ) ),
	array( array( 'create' ), false, false, array( 'invite', 'reinvite' ), false, false ) );
ck( 'the worklist is read once a draw, on either view: each Confirmed institution\'s members asked once, and no other institution\'s',
	array( $all_read, $read ),
	array( array( ZETA, ALPHA, ECOLE, DELTA ), array( ZETA, ALPHA, ECOLE, DELTA ) ) );
ck( 'the view keeps itself in the list\'s form, so a search, a sort or a page stays on it',
	has( between( $html, '<form method="get"', '</form>' ), '<input type="hidden" name="wpcpm_view" value="no-account" />' ), true );
ck( 'the card\'s heading counts institution accounts, as its words say, on the No account view too: six, while the list there holds two institutions',
	array( heading_count( $all_view ), heading_count( $html ), displaying( $html ) ),
	array( '6', '6', '2 items' ) );
ck( 'the note under the list, on how accounts are made and invited, reads on the account views alone: the No account view offers neither invitation',
	array( has( $all_view, '<p class="description">Accounts are created with a random password.' ), has( $html, 'Accounts are created with a random password.' ) ),
	array( true, false ) );
ck( 'its one sort is the institution\'s name: A to Z, Delta before École, read without regard to accents; Z to A the other way',
	array(
		sort_of( $html, 'institution' ),
		sort_of( $html, 'contact' ),
		record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 'orderby' => 'institution', 'order' => 'asc' ) ) ) ) ),
		record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 'orderby' => 'institution', 'order' => 'desc' ) ) ) ) ),
		record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 'orderby' => 'status' ) ) ) ) ),
	),
	array( 'institution', 'not sortable', array( DELTA, ECOLE ), array( ECOLE, DELTA ), array( ECOLE, DELTA ) ) );

$record_search = html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'DELTA' ) ) ) );

ck( 'a search narrows the institutions by name, whatever the case, and not the view\'s count',
	array( record_ids( $record_search ), view_counts( $record_search )['no-account'], displaying( $record_search ) ),
	array( array( DELTA ), '2', '1 item' ) );
ck( 'and without regard to accents, as the names are sorted: "Ecole" finds École Example Charlie',
	record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'Ecole' ) ) ) ) ),
	array( ECOLE ) );

$no_match = html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'zzz' ) ) ) );

ck( 'a search no institution matches says so, rather than that every institution has an account',
	array( record_ids( $no_match ), has( $no_match, 'No institutions found.' ), has( $no_match, 'Every Confirmed institution has an account.' ) ),
	array( array(), true, false ) );

$warning = '<p>Creating an account emails a password-set link to the address Airtable holds for the institution. An invitation cannot be recalled once sent.</p>';
$switch  = '<p class="description">The sync does not create accounts: this tab is the only way one is made.</p>';

ck( 'above the view\'s list, what a Create account sends, which cannot be recalled; under it, whether the sync creates these accounts too; no gate while every Confirmed institution with no member has its agreement recorded',
	array(
		false !== strpos( $html, $warning ) && strpos( $html, $warning ) < strpos( $html, "<ul class='subsubsub'>" ),
		false !== strpos( $html, $switch ) && strpos( $html, $switch ) > strpos( $html, '</table>' ),
		has( $html, 'No account is created in bulk' ),
	),
	array( true, true, false ) );

$read_line = '<p class="wpcpm-inst-read">(from the pipeline index read 2026-09-21 14:13; memberships counted now)</p>';

ck( 'and last, which half of what it lists is as old as the last sync, the index, and which was read now, the memberships, as every count on the screen that joins the two says',
	false !== strpos( $html, $read_line ) && strpos( $html, $read_line ) > strpos( $html, $switch ),
	true );
ck( 'the account views say none of it', array( has( $all_view, 'Creating an account' ), has( $all_view, 'The sync does not create accounts' ), has( $all_view, 'memberships counted now' ) ), array( false, false, false ) );

WPCPM_Settings::$values['institution_provision'] = true;
$sync_on                                         = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
WPCPM_Settings::$values['institution_provision'] = false;

ck( 'and when the sync creates them too, it says so', has( $sync_on, '<p class="description">The sync creates these accounts too, by the same rule, on top of anything made here.</p>' ), true );
ck( 'no address is printed on the view, only whether there is one', array( has( $html, '@' ), has( $all_view, '@' ) ), array( false, false ) );

// The gate: École's agreement not recorded, and nobody acting for it.
$GLOBALS['settled'][ ECOLE ] = false;
$gated                       = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$gate                        = '<p class="wpcpm-warning">No account is created in bulk while 1 Confirmed institution on this view has no agreement recorded: École Example Charlie. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions&#038;tab=agreements">Record it on the Agreements tab.</a></p>';

ck( 'while a Confirmed institution with no member has no agreement recorded, the view says first that no account is created in bulk, naming it, and sends the reader to the Agreements tab, where it is recorded; with nothing ready to create, it says nothing of what a Create account sends',
	array( has( $gated, $gate ), false !== strpos( $gated, $gate ) && strpos( $gated, $gate ) < strpos( $gated, "<ul class='subsubsub'>" ), has( $gated, $warning ) ),
	array( true, true, false ) );

// The gate and the Agreements tab's card count two sets of the same site: the gate the Confirmed
// institutions on the No account view, which have no member, whose agreement is not recorded; the
// card every Confirmed institution whose agreement is not recorded, members or none, which is what
// its form records. Alpha, Confirmed with two members and nothing recorded, is in the card's set
// and not the gate's, so each sentence says which set it counts.
ob_start();
reach( 'render_agreements_on_file' )->invoke( new WPCPM_Institutions(), WPCPM_Institutions_Index::read() );
$on_file_card = (string) ob_get_clean();

ck( 'on the same site the gate counts 1, École, on the No account view, and the Agreements on file card counts 2, Alpha with its members beside École, its form recording both',
	array(
		has( $gated, 'while 1 Confirmed institution on this view has no agreement recorded: École Example Charlie. <a' ),
		has( $on_file_card, '<p>2 Confirmed institutions have no agreement recorded:</p>' ),
		has( $on_file_card, '<li>Alpha Example College <code class="wpcpm-inst-record">' . ALPHA . '</code></li>' ),
		has( $on_file_card, '<li>École Example Charlie <code class="wpcpm-inst-record">' . ECOLE . '</code></li>' ),
		substr_count( $on_file_card, '<li>' ),
		has( $on_file_card, '>Record all 2 institutions as signed</button>' ),
	),
	array( true, true, true, true, 2, true ) );
ck( 'and lists the two by name, none of them ready, École saying why in the sync\'s words, with no Create account on either and no checkbox to tick',
	array( record_ids( $gated ), cell( record_row( $gated, ECOLE ), 'account' ), has( $gated, '>Create account</a>' ), substr_count( $gated, 'name="records[]"' ) ),
	array( array( DELTA, ECOLE ), 'No agreement is recorded for it. Record the one on file with its Drive link, or accept a signed one, before the account is created.', false, 0 ) );

$kept_index                                                                             = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];
$GLOBALS['settled'][ FOX ]                                                              = true;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['stage']         = 'Confirmed';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['contact_email'] = 'office@fox.example';
$both_said                                                                              = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]                                  = $kept_index;
unset( $GLOBALS['settled'][ FOX ] );

ck( 'with a ready institution beside the one holding the gate, both are said above the list, the gate first, then what a Create account sends',
	array( false !== strpos( $both_said, $gate ), false !== strpos( $both_said, $warning ) && strpos( $both_said, $gate ) < strpos( $both_said, $warning ) && strpos( $both_said, $warning ) < strpos( $both_said, "<ul class='subsubsub'>" ) ),
	array( true, true ) );

$kept_index = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];
foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F' ) as $n => $letter ) {
	$record = 'recINSTKILO' . $letter . sprintf( '%05d', 11 + $n );

	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $record ] = index_row( $record, 'Kilo Example ' . ( $n + 1 ), 'Confirmed', 'office@kilo' . strtolower( $letter ) . '.example' );
}
$many                                                  = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = $kept_index;
$GLOBALS['settled'][ ECOLE ]                           = true;

ck( 'past five, the gate names the first five in the view\'s order and counts the rest, and sends the reader to record them',
	has( $many, 'No account is created in bulk while 7 Confirmed institutions on this view have no agreement recorded: École Example Charlie, Kilo Example 1, Kilo Example 2, Kilo Example 3, Kilo Example 4, and 2 more. <a' )
		&& has( $many, '>Record them on the Agreements tab.</a></p>' ),
	true );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ ECOLE ]['stage'] = 'Student';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ DELTA ]['stage'] = 'Student';
$none_left                                                                        = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]                            = $kept_index;

ck( 'with every Confirmed institution holding an account, the view counts none and says so, and nothing of what a Create account sends',
	array( record_ids( $none_left ), view_counts( $none_left )['no-account'], has( $none_left, 'Every Confirmed institution has an account.' ), has( $none_left, $warning ) ),
	array( array(), '0', true, false ) );

foreach ( array( ZETA, ALPHA, ECOLE, DELTA ) as $record ) {
	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $record ]['stage'] = 'Agreement Sent';
}
$no_confirmed                                          = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = $kept_index;

ck( 'with no institution at Confirmed at all, the view says none has reached it, so there is nothing to create, rather than that each has an account',
	array( record_ids( $no_confirmed ), has( $no_confirmed, 'No institution has reached Confirmed yet, so there is nothing to create.' ), has( $no_confirmed, 'Every Confirmed institution has an account.' ) ),
	array( array(), true, false ) );

echo "\n=== Create account on the ticked institutions, handled before the screen draws ===\n";

// What the list's form sends besides the choice: core's nonce and the address it came from.
$sent_with = array(
	'_wpnonce'         => wp_create_nonce( 'bulk-institutions' ),
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_view=no-account',
	'action2'          => '-1',
);
$on_view   = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$tick      = function ( array $records, array $more = array() ) use ( $sent_with ) {
	return press( screen( array_merge( $sent_with, array( 'wpcpm_view' => 'no-account', 'action' => 'create', 'records' => $records ), $more ) ) );
};

the_site();
manager( 60 );
$both = $tick( array( ECOLE, DELTA ) );

ck( 'Create account on École and Delta provisions École alone, once, as the manager who pressed, from the address Airtable holds for it, and queues its invitation',
	array(
		count( $GLOBALS['inserted'] ),
		isset( $GLOBALS['inserted'][0] ) ? array( $GLOBALS['inserted'][0]['user_email'], $GLOBALS['inserted'][0]['role'] ) : null,
		$GLOBALS['attached'],
		WPCPM_Mail::queue(),
	),
	array( 1, array( 'office@ecole.example', WPCPM_Roles::ROLE_INSTITUTION ), array( array( 101, ECOLE, 'provisioned', 60 ) ), array( 101 ) ) );
ck( 'then back to the No account view, the list\'s nonce the one asked about', array( link_of( $both['redirect'] ), $GLOBALS['nonce_checks'] ), array( $on_view, array( 'bulk-institutions' ) ) );
ck( 'which says how many accounts were created and names Delta, the one that could not be given one',
	notice_now( array( 'wpcpm_view' => 'no-account' ) ),
	'<div class="notice notice-error is-dismissible"><p>1 account was created, with an invitation queued. These could not be given an account: Delta Example School. The No account view says why for each.</p></div>' );

$after = html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) );

ck( 'École now has its account: the view lists Delta alone, and the account views count seven',
	array( record_ids( $after ), view_counts( $after ) ),
	array( array( DELTA ), array( 'all' => '7', 'invited' => '3', 'never-invited' => '4', 'no-account' => '1' ) ) );

the_site();
manager( 61 );
$tick( array( ECOLE ) );

ck( 'École alone is created, and the view says how many accounts were',
	array( count( $GLOBALS['inserted'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 1, '<div class="notice notice-success is-dismissible"><p>1 account was created, with an invitation queued.</p></div>' ) );

the_site();
manager( 76 );
$GLOBALS['settled'][ FOX ]                                                              = true;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['stage']         = 'Confirmed';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['contact_email'] = 'office@fox.example';
$tick( array( ECOLE, FOX, DELTA ) );

ck( 'two ready institutions and one that is not, ticked together: the two are created, and the view says how many and names the one that could not be given an account',
	array( count( $GLOBALS['inserted'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 2, '<div class="notice notice-error is-dismissible"><p>2 accounts were created, each with an invitation queued. These could not be given an account: Delta Example School. The No account view says why for each.</p></div>' ) );

the_site();
manager( 77 );
$GLOBALS['insert_fails'] = true;
$tick( array( ECOLE ) );
$GLOBALS['insert_fails'] = false;

ck( 'one ready institution ticked whose account WordPress would not make is a failure, named with what WordPress said, and not a refusal',
	array( count( $GLOBALS['inserted'] ), $GLOBALS['attached'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 1, array(), '<div class="notice notice-error is-dismissible"><p>This could not be created: École Example Charlie (Sorry, that username already exists!).</p></div>' ) );

the_site();
manager( 98 );
$GLOBALS['settled'][ FOX ]                                                              = true;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['stage']         = 'Confirmed';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['contact_email'] = 'office@fox.example';
$GLOBALS['insert_fails']                                                                = true;
$tick( array( ECOLE, FOX ) );
$GLOBALS['insert_fails'] = false;

ck( 'two whose accounts WordPress would not make are named together, each with what WordPress said',
	array( count( $GLOBALS['inserted'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 2, '<div class="notice notice-error is-dismissible"><p>These could not be created: École Example Charlie (Sorry, that username already exists!), Foxtrot Example University (Sorry, that username already exists!).</p></div>' ) );

// A second press: a double click on Apply, or a page left open while somebody else pressed. The
// first press made the account, so the institution is off the view's list, and the second says it
// already had one rather than that it could not be given one.
the_site();
manager( 93 );
$tick( array( ECOLE ) );
manager( 94 );
$tick( array( ECOLE ) );

ck( 'a second press on an institution that got its account in between creates nothing and says it already had one, which is no error',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-info is-dismissible"><p>École Example Charlie already had an account.</p></div>' ) );

// École's member is seated rather than made by a press: the stand-in numbers a new account by the
// press's own count, so a second press's account would take the first one's number.
the_site();
seat( 41, 'Eve Example', 'eexample', array( WPCPM_Roles::ROLE_INSTITUTION ), member_of( ECOLE ) );
$GLOBALS['settled'][ FOX ]                                                              = true;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['stage']         = 'Confirmed';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['contact_email'] = 'office@fox.example';
manager( 95 );
$tick( array( ECOLE, FOX, DELTA ) );

ck( 'one that had its account, one created and one refused, ticked together: the count first, then the one that had it, then the refusal, each told apart',
	array( count( $GLOBALS['inserted'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 1, '<div class="notice notice-error is-dismissible"><p>1 account was created, with an invitation queued. École Example Charlie already had an account. These could not be given an account: Delta Example School. The No account view says why for each.</p></div>' ) );

manager( 96 );
$tick( array( ECOLE, FOX ) );

ck( 'and two that had theirs are named together',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-info is-dismissible"><p>These already had an account: École Example Charlie, Foxtrot Example University.</p></div>' ) );

// One press creates at most 25: twenty-five more institutions ready beside École, all ticked on a
// page of fifty, so the twenty-sixth in the order ticked waits for the next press.
the_site();
manager( 78 );
$GLOBALS['umeta'][78]['wpcpm_institutions_per_page'] = 50;
for ( $n = 1; $n <= 25; $n++ ) {
	$record = sprintf( 'recINSTLIMIT%05d', $n );

	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $record ] = index_row( $record, sprintf( 'Lima Example %02d', $n ), 'Confirmed', sprintf( 'office@lima%02d.example', $n ) );
	$GLOBALS['settled'][ $record ]                                            = true;
}
$ready = array_values( array_diff( record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) ) ), array( DELTA ) ) );
$tick( $ready );
$left = record_ids( html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) ) );

ck( 'with twenty-six ready institutions ticked, one press creates twenty-five, the first ticked, and the twenty-sixth stays listed with Delta, which was never ready',
	array( count( $ready ), count( $GLOBALS['inserted'] ), count( $GLOBALS['attached'] ), $left ),
	array( 26, 25, 25, array( 'recINSTLIMIT00025', DELTA ) ) );
ck( 'and the view says how many were created, that one press creates at most 25, and how many of the ticked stay listed',
	notice_now( array( 'wpcpm_view' => 'no-account' ) ),
	'<div class="notice notice-success is-dismissible"><p>25 accounts were created, each with an invitation queued. One press creates at most 25; 1 ticked institution stays listed here.</p></div>' );

// The ceiling and a refusal in one press: the same twenty-six ticked with Delta, which was never
// ready. The refusal takes no place under the ceiling, and the outcome says all three in order.
the_site();
manager( 120 );
$GLOBALS['umeta'][120]['wpcpm_institutions_per_page'] = 50;
for ( $n = 1; $n <= 25; $n++ ) {
	$record = sprintf( 'recINSTLIMIT%05d', $n );

	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $record ] = index_row( $record, sprintf( 'Lima Example %02d', $n ), 'Confirmed', sprintf( 'office@lima%02d.example', $n ) );
	$GLOBALS['settled'][ $record ]                                            = true;
}
$tick( array_merge( $ready, array( DELTA ) ) );

ck( 'twenty-six ready and Delta ticked together: twenty-five created, one held for the next press, and Delta named as refused, the count, the ceiling and the refusal in that order',
	array( count( $GLOBALS['inserted'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 25, '<div class="notice notice-error is-dismissible"><p>25 accounts were created, each with an invitation queued. One press creates at most 25; 1 ticked institution stays listed here. These could not be given an account: Delta Example School. The No account view says why for each.</p></div>' ) );

// Past five refused, the outcome names the first five ticked and counts the rest: six Confirmed
// institutions with no Contact Email, which no account can be made for, ticked together. None holds
// the gate, which waits on an agreement and not on an address.
the_site();
manager( 121 );
$kilo = array();
foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F' ) as $n => $letter ) {
	$record = 'recINSTKILO' . $letter . sprintf( '%05d', 11 + $n );

	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $record ] = index_row( $record, 'Kilo Example ' . ( $n + 1 ), 'Confirmed', '' );
	$GLOBALS['settled'][ $record ]                                            = true;
	$kilo[]                                                                   = $record;
}
$tick( $kilo );

ck( 'six ticked that cannot be given an account: nothing is created, and the outcome names the first five in the order ticked and counts the sixth',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-error is-dismissible"><p>These could not be given an account: Kilo Example 1, Kilo Example 2, Kilo Example 3, Kilo Example 4, Kilo Example 5, and 1 more. The No account view says why for each.</p></div>' ) );

the_site();
manager( 62 );
$tick( array( DELTA ) );

ck( 'Delta alone is refused: nothing is created, and the view says that institution cannot be given an account yet',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-error is-dismissible"><p>That institution cannot be given an account yet. The No account view says why.</p></div>' ) );

manager( 63 );
$tick( array() );

ck( 'with nothing ticked there is nothing to create, and the view says so',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>' ) );

manager( 64 );
$GLOBALS['settled'][ ECOLE ] = false;
$GLOBALS['settled'][ FOX ]   = true;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['stage']         = 'Confirmed';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ FOX ]['contact_email'] = 'office@fox.example';
$blocked = $tick( array( FOX, DELTA ) );

ck( 'while École has no agreement recorded, nothing is created in bulk, not even Foxtrot, ready and ticked: back to the view, which says why',
	array( $GLOBALS['inserted'], link_of( $blocked['redirect'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), $on_view, '<div class="notice notice-error is-dismissible"><p>Nothing was created: a Confirmed institution has no agreement recorded. The No account view names each.</p></div>' ) );

the_site();
manager( 65 );
$forged = $tick( array( ECOLE ), array( '_wpnonce' => 'forged' ) );

ck( 'a press with the wrong nonce dies with WordPress\'s own sentence, before any account or any institution is read and before anything is created',
	array( $forged['died'], $GLOBALS['reads'], $GLOBALS['member_reads'], $GLOBALS['inserted'] ),
	array( 'The link you followed has expired.', array(), array(), array() ) );

manager( 66 );
$GLOBALS['caps'] = false;
$refused         = $tick( array( ECOLE ) );
$GLOBALS['caps'] = true;

ck( 'somebody without the program\'s capability is refused before the nonce is asked about, and nothing is created',
	array( $refused['died'], $GLOBALS['nonce_checks'], $GLOBALS['inserted'] ),
	array( 'You do not have permission to manage the program.', array(), array() ) );

manager( 67 );
$from_accounts = press( screen( array_merge( $sent_with, array( 'action' => 'create', 'users' => array( '22' ) ) ) ) );

ck( 'Create account sent from an account view, ticked accounts and all, reads only the ticked institutions, of which there are none: nothing is created, and the press comes back to that view',
	array( $GLOBALS['inserted'], link_of( $from_accounts['redirect'] ), notice_now() ),
	array(
		array(),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts' ) ),
		'<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>',
	) );

manager( 68 );
$tick( array( strtolower( ECOLE ) ) );

ck( 'a record ID is read with its case: one in another case names no institution the view lists, and is refused rather than created',
	array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), '<div class="notice notice-error is-dismissible"><p>That institution cannot be given an account yet. The No account view says why.</p></div>' ) );

manager( 69 );
$tick( array( ECOLE . 'x', 'Ecole', ECOLE . "\n" ) );

ck( 'and a value that is not a whole record ID is no institution ticked at all', array( $GLOBALS['inserted'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ), array( array(), '<div class="notice notice-info is-dismissible"><p>There was nothing to create.</p></div>' ) );

echo "\n=== A row's Create account: a nonce link to the module's handler for one institution ===\n";

the_site();
manager( 70 );
$row_link = actions_in( record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ) ), ECOLE ) );
$row_link = isset( $row_link['create'] ) ? $row_link['create'] : '';
$followed = follow( $row_link, 'handle_provision_one' );

ck( 'followed, it provisions École, with its record ID\'s case, as the manager who followed it, under the nonce keyed to it',
	array( count( $GLOBALS['inserted'] ), $GLOBALS['attached'], $GLOBALS['nonce_checks'] ),
	array( 1, array( array( 101, ECOLE, 'provisioned', 70 ) ), array( 'wpcpm_institutions_provision_one_' . ECOLE ) ) );
ck( 'and comes back to the view as the link says it stood, searched, which says the one account was created',
	array( link_of( $followed['redirect'] ), notice_now( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'example' ) ),
		'<div class="notice notice-success is-dismissible"><p>1 account was created, with an invitation queued.</p></div>',
	) );

manager( 99 );
$again = follow( $row_link, 'handle_provision_one' );

ck( 'followed again once École has its account, as a double click does, it creates nothing and says the institution already has one, which is no error',
	array( $GLOBALS['inserted'], link_of( $again['redirect'] ), notice_now( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ),
	array(
		array(),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'example' ) ),
		'<div class="notice notice-info is-dismissible"><p>That institution already has an account.</p></div>',
	) );

the_site();
manager( 71 );
$forged = follow( str_replace( wp_create_nonce( 'wpcpm_institutions_provision_one_' . ECOLE ), 'forged', $row_link ), 'handle_provision_one' );

ck( 'with the wrong nonce the link dies before anything is created', array( $forged['died'], $GLOBALS['inserted'] ), array( 'The link you followed has expired.', array() ) );

manager( 72 );
$GLOBALS['caps'] = false;
$refused         = follow( $row_link, 'handle_provision_one' );
$GLOBALS['caps'] = true;

ck( 'and without the program\'s capability it dies before the nonce is asked about', array( $refused['died'], $GLOBALS['nonce_checks'], $GLOBALS['inserted'] ), array( 'You do not have permission to manage the program.', array(), array() ) );

manager( 73 );
$stale = follow(
	add_query_arg(
		array(
			'action'            => 'wpcpm_institutions_provision_one',
			'wpcpm_institution' => DELTA,
			'wpcpm_tab'         => 'accounts',
			'wpcpm_view'        => 'no-account',
			'_wpnonce'          => wp_create_nonce( 'wpcpm_institutions_provision_one_' . DELTA ),
		),
		admin_url( 'admin-post.php' )
	),
	'handle_provision_one'
);

ck( 'one the institutions sync refuses, as a stale page could offer, is a refusal and not a failure: nothing is created, and the view says why',
	array( $GLOBALS['inserted'], link_of( $stale['redirect'] ), notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( array(), $on_view, '<div class="notice notice-error is-dismissible"><p>That institution cannot be given an account yet. The No account view says why.</p></div>' ) );

manager( 74 );
$GLOBALS['insert_fails'] = true;
follow( $row_link, 'handle_provision_one' );
$GLOBALS['insert_fails'] = false;

ck( 'and an account WordPress would not make is a failure and not a refusal, named with what WordPress said',
	array( count( $GLOBALS['inserted'] ), $GLOBALS['attached'], notice_now( array( 'wpcpm_view' => 'no-account' ) ) ),
	array( 1, array(), '<div class="notice notice-error is-dismissible"><p>This could not be created: École Example Charlie (Sorry, that username already exists!).</p></div>' ) );

echo "\n=== Manage members: one institution's members, a view of the Accounts tab ===\n";

// Each manager who looks is an account on the site, as the policy the view's blocks ask needs: it
// decides for the person signed in, and refuses a visitor it cannot find.
the_site();
manager( 110 );
seat( 110, 'Mo Manager', 'mmanager', array( 'administrator' ) );
$GLOBALS['screen_options'] = array();
$GLOBALS['member_reads']   = array();
$view_load                 = press( screen( array( 'wpcpm_institution' => ZETA ) ) );

ck( 'with an institution in the Accounts tab\'s address, the load hook builds no list for the view drawn in its place: no rows-per-page option and no list headings, no account read and no institution asked about, and the page goes on',
	got(
		$view_load,
		function ( $out ) {
			return array( $out['value'], $GLOBALS['screen_options'], $GLOBALS['queries'], $GLOBALS['member_reads'], $GLOBALS['current_screen']->reader_content );
		}
	),
	array( 'drawn', array(), array(), array(), array() ) );

$view       = html_of( page_for( screen( array( 'wpcpm_institution' => ZETA ) ) ) );
$view_body  = (string) strstr( $view, '</nav>' );
$view_reads = $GLOBALS['member_reads'];

ck( 'the view draws, in place of the accounts locked today, the invitations card and the list, the institution\'s name, the way back to the accounts, its members block, on the anchor every press made there lands on, and the form a signed agreement is uploaded with, in that order, asking for that one institution\'s members alone',
	array(
		in_order(
			$view_body,
			array(
				'name'    => '<h2>Zeta Example Institute</h2>',
				'back'    => '>Back to the accounts</a>',
				'members' => '<div class="wpcpm-people wpcpm-people--admin" id="wpcpm-people">',
				'upload'  => 'Upload a signed agreement on the institution&#039;s behalf',
			)
		),
		has( $view, 'locked out of roster changes' ),
		has( $view, 'wpcpm-invites' ),
		has( $view, '<form method="get"' ),
		has( $view, 'Institution accounts <span' ),
		$view_reads,
	),
	array( array( 'name', 'back', 'members', 'upload' ), false, false, false, false, array( ZETA ) ) );
ck( 'the way back is the list, on the Accounts tab', back_of( $view ), array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts' ) ) );
ck( 'every form on it comes back to it: Nora\'s and Ben\'s Remove, Re-add for Ada, who was a member, and Add an account each post the Institutions screen\'s flag under the nonce its handler checks, and the upload form names the institution',
	array(
		'remove' => form_fields_of( $view, 'wpcpm_remove_member' ),
		'readd'  => form_fields_of( $view, 'wpcpm_readd_institution_member' ),
		'add'    => form_fields_of( $view, 'wpcpm_add_institution_account' ),
		'upload' => form_fields_of( $view, 'wpcpm_agreement_upload' ),
	),
	array(
		'remove' => array(
			array( '_wpnonce' => wp_create_nonce( 'wpcpm_remove_member_21' ), 'action' => 'wpcpm_remove_member', 'member' => '21', 'wpcpm_from' => 'admin' ),
			array( '_wpnonce' => wp_create_nonce( 'wpcpm_remove_member_22' ), 'action' => 'wpcpm_remove_member', 'member' => '22', 'wpcpm_from' => 'admin' ),
		),
		'readd'  => array(
			array( '_wpnonce' => wp_create_nonce( 'wpcpm_readd_institution_member_25' ), 'action' => 'wpcpm_readd_institution_member', 'member' => '25', 'record' => ZETA, 'wpcpm_from' => 'admin' ),
		),
		'add'    => array(
			array( '_wpnonce' => wp_create_nonce( 'wpcpm_add_institution_account_' . ZETA ), 'action' => 'wpcpm_add_institution_account', 'record' => ZETA, 'wpcpm_from' => 'admin' ),
		),
		'upload' => array(
			array( '_wpnonce' => wp_create_nonce( 'wpcpm_agreement_upload_' . ZETA ), 'action' => 'wpcpm_agreement_upload', 'wpcpm_agreement_record' => ZETA, 'wpcpm_agreement_signed' => '0' ),
		),
	) );
ck( 'the members\' addresses are printed on the view, as the Institution Dashboard\'s People card prints them, since a manager adds and removes accounts by address: Nora\'s and Ben\'s, Ada\'s among the former members, and the Contact Email no member holds',
	array(
		has( $view_body, '<span class="wpcpm-people__email">nquist@example.test</span>' ),
		has( $view_body, '<span class="wpcpm-people__email">bokafor@example.test</span>' ),
		has( (string) strstr( $view_body, '>Former members<' ), '<span class="wpcpm-people__email">amoreau@example.test</span>' ),
		has( $view_body, 'Airtable names office@zeta.example as the contact' ),
	),
	array( true, true, true, true ) );
ck( 'with no signed copy waiting, every button on the view is one of core\'s: Nora\'s and Ben\'s Remove as Re-add and Add account are, and Upload the signed agreement as the primary one, the Institution Dashboard\'s own button classes nowhere on it',
	array(
		substr_count( $view_body, '<button type="submit" class="button button-secondary wpcpm-people__remove" onclick=' ),
		has( $view_body, '<button type="submit" class="button button-primary">Upload the signed agreement</button>' ),
		has( $view_body, 'class="wpcpm-people__remove"' ),
		has( $view_body, 'class="wpcpm-button"' ),
	),
	array( 2, true, false, false ) );

// With a signed copy already waiting for review, the upload block draws that copy and the control
// that withdraws it in the upload form's place: the view's one other button, core's too.
$GLOBALS['pending'][ ZETA ] = 950;
$GLOBALS['posts'][950]      = new WP_Post( 950, WPCPM_Institution_Agreement::POST_TYPE );
$GLOBALS['pmeta'][950]      = array( WPCPM_Institution_Agreement::META_INSTITUTION => ZETA );
$pending_view               = (string) strstr( html_of( page_for( screen( array( 'wpcpm_institution' => ZETA ) ) ) ), '</nav>' );
$GLOBALS['pending']         = array();
$GLOBALS['posts']           = array();
$GLOBALS['pmeta']           = array();

ck( 'and with a signed copy waiting, Withdraw it on the institution\'s behalf is core\'s secondary button where the upload form was, beside the two Remove buttons, the Institution Dashboard\'s own button classes nowhere on it',
	array(
		substr_count( $pending_view, '<button type="submit" class="button button-secondary" onclick=' ),
		has( $pending_view, '>Withdraw it on the institution&#039;s behalf</button>' ),
		has( $pending_view, 'value="' . wp_create_nonce( 'wpcpm_agreement_withdraw_950' ) . '"' ),
		has( $pending_view, 'Upload the signed agreement</button>' ),
		substr_count( $pending_view, '<button type="submit" class="button button-secondary wpcpm-people__remove" onclick=' ),
		has( $pending_view, 'class="wpcpm-button"' ),
	),
	array( 1, true, true, false, 2, false ) );

// The view prints the Institution Dashboard's People markup in wp-admin, which does not load the
// dashboard's stylesheet: the admin stylesheet dresses it, scoped to the view's own block, read
// the way the Overview's suite reads its rules.
$admin_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' );

ck( 'the admin stylesheet dresses the view: an outcome as a notice with its state\'s color, a member as a row, the facts under the name, the row\'s form under the facts, and the upload form\'s fieldset without a frame',
	array(
		1 === preg_match( '/\.wpcpm-people--admin \.wpcpm-people__message \{[^}]*border-left-width: 4px;/s', $admin_css ),
		1 === preg_match( '/\.wpcpm-people--admin \.wpcpm-people__message\.is-success \{[^}]*border-left-color: #00a32a;/s', $admin_css ),
		1 === preg_match( '/\.wpcpm-people--admin \.wpcpm-people__message\.is-error \{[^}]*border-left-color: #d63638;/s', $admin_css ),
		1 === preg_match( '/\.wpcpm-people--admin \.wpcpm-people__message\.is-info \{[^}]*border-left-color: #72aee6;/s', $admin_css ),
		1 === preg_match( '/\.wpcpm-people--admin \.wpcpm-people__member \{[^}]*border-bottom: 1px solid #f0f0f1;/s', $admin_css ),
		1 === preg_match( '/\.wpcpm-people--admin \.wpcpm-people__facts \{[^}]*display: block;/s', $admin_css ),
		1 === preg_match( '/\.wpcpm-people--admin \.wpcpm-people__list \.wpcpm-people__form \{[^}]*margin-top: 4px;/s', $admin_css ),
		1 === preg_match( '/\.wpcpm-wrap \.wpcpm-agreement-panel__choices \{[^}]*border: 0;/s', $admin_css ),
	),
	array_fill( 0, 8, true ) );

// The press is the next request, by somebody new, for the reason `manager()` gives.
manager( 111 );
seat( 111, 'Kai Manager', 'kmanager', array( 'administrator' ) );
$readd = form_fields_of( $view, 'wpcpm_readd_institution_member' );
request( array(), isset( $readd[0] ) ? $readd[0] : array() );
$readded = run(
	function () {
		WPCPM_Institution_People::handle_readd();
	}
);
$after_readd = html_of( page_for( screen( array( 'wpcpm_institution' => ZETA ) ) ) );

ck( 'Ada\'s Re-add, posted as a browser posts the form, puts her back as the manager who pressed and lands on the view\'s members block, which says so and lists her among the members, with no former member left',
	array( $readded['redirect'], $GLOBALS['attached'], has( $after_readd, 'That former member has access again.' ), has( $after_readd, '>Former members<' ), count( form_fields_of( $after_readd, 'wpcpm_remove_member' ) ) ),
	array( 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . ZETA . '#wpcpm-people', array( array( 25, ZETA, 'manager', 111 ) ), true, false, 3 ) );

// Add account's nonce is keyed to the institution, as every other press on the view is keyed to its
// subject: the nonce another institution's Add account form carries, posted with this one's record,
// is refused after the capability and before anything is read or made.
manager( 117 );
seat( 117, 'Lia Manager', 'lmanager', array( 'administrator' ) );
$alpha_add = form_fields_of( html_of( page_for( screen( array( 'wpcpm_institution' => ALPHA ) ) ) ), 'wpcpm_add_institution_account' );
$zeta_add  = form_fields_of( $view, 'wpcpm_add_institution_account' );
$GLOBALS['nonce_checks'] = array();
request(
	array(),
	array_merge(
		isset( $zeta_add[0] ) ? $zeta_add[0] : array(),
		array(
			'_wpnonce' => isset( $alpha_add[0]['_wpnonce'] ) ? $alpha_add[0]['_wpnonce'] : '',
			'name'     => 'Mia Example',
			'email'    => 'mia@example.test',
		)
	)
);
$borrowed = run(
	function () {
		WPCPM_Institution_People::handle_add_account();
	}
);

ck( 'Add account posted under the nonce another institution\'s form carries dies with WordPress\'s own sentence, the nonce asked about keyed to the posted institution, and nothing is created',
	array( isset( $alpha_add[0]['record'] ) ? $alpha_add[0]['record'] : '', $borrowed['died'], $GLOBALS['nonce_checks'], $GLOBALS['inserted'] ),
	array( ALPHA, 'The link you followed has expired.', array( 'wpcpm_add_institution_account_' . ZETA ), array() ) );

the_site();
manager( 112 );
$GLOBALS['caps'] = false;
$GLOBALS['uid']  = 21;
$member_view     = html_of( page_for( screen( array( 'wpcpm_institution' => ZETA ) ) ) );
$GLOBALS['caps'] = true;

ck( 'a member of the institution reaching the view is drawn its name and the way back and nothing else: the members block and the upload form are a manager\'s, and each asks the capability itself',
	array(
		has( $member_view, '<h2>Zeta Example Institute</h2>' ),
		has( $member_view, '>Back to the accounts</a>' ),
		has( $member_view, 'wpcpm-people' ),
		has( $member_view, '<form' ),
		has( $member_view, 'Upload a signed agreement' ),
		has( $member_view, '@' ),
	),
	array( true, true, false, false, false, false ) );

manager( 113 );
seat( 113, 'Ana Manager', 'amanager', array( 'administrator' ) );
$unknown = array();
foreach ( array( 'recINSTNOPEX00099', strtolower( ZETA ), 'not-a-record' ) as $asked ) {
	$asked_page        = html_of( page_for( screen( array( 'wpcpm_institution' => $asked ) ) ) );
	$unknown[ $asked ] = array( has( $asked_page, '<p>No institution record has that ID. If it should be here, run the institutions sync on the <a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions&#038;tab=sync">Sync and storage tab</a>.</p>' ), back_of( $asked_page ), has( $asked_page, 'wpcpm-people' ), has( $asked_page, '<form' ), has( $asked_page, $asked ), $GLOBALS['queries'], $GLOBALS['member_reads'] );
}

ck( 'a record the pipeline index does not hold, one in another case among them, and a value that is no record ID at all are each said to be none, with the remedy, the sync, on its tab, and the way back to the list, and nothing else is drawn or read for them; the value is never printed',
	$unknown,
	array_fill_keys(
		array( 'recINSTNOPEX00099', strtolower( ZETA ), 'not-a-record' ),
		array( true, array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts' ) ), false, false, false, array(), array() )
	) );

// An Add account pressed on a view whose record a sync has since dropped lands on that record's
// view, which draws no block: the outcome is printed all the same, on the block's anchor, and is
// taken, not left in user meta for the next block to print.
manager( 116 );
seat( 116, 'Uma Manager', 'umanager', array( 'administrator' ) );
WPCPM_Flash::set(
	'institution_people',
	array(
		'status' => 'unknown-record',
		'detail' => '',
		'record' => 'recINSTNOPEX00099',
	)
);
$flash_waiting = isset( $GLOBALS['umeta'][116][ WPCPM_Flash::META ] );
$dropped       = html_of( page_for( screen( array( 'wpcpm_institution' => 'recINSTNOPEX00099' ) ) ) );

ck( 'a press that landed on the view of a record the index no longer holds is told there, before the sentence, on the anchor the landing names, and the outcome is taken',
	array(
		in_order(
			$dropped,
			array(
				'outcome'  => '<div class="wpcpm-people wpcpm-people--admin" id="wpcpm-people"><p class="wpcpm-people__message is-error" role="status">That institution is not in the pipeline index. Run the institutions sync, then try again.</p></div>',
				'sentence' => '<p>No institution record has that ID.',
			)
		),
		$flash_waiting,
		isset( $GLOBALS['umeta'][116][ WPCPM_Flash::META ] ),
	),
	array( array( 'outcome', 'sentence' ), true, false ) );

the_site();
seat( 28, 'Lia Unstamped', 'lunstamped', array( WPCPM_Roles::ROLE_INSTITUTION ) );

ck( 'an account that acts for no institution and never did is offered no Manage members: there is no institution to open',
	array_keys( actions_in( row_for( html_of( draw( screen() ) ), 28 ) ) ),
	array( 'edit', 'invite' ) );

the_site();
manager( 114 );
seat( 114, 'Eva Manager', 'emanager', array( 'administrator' ) );
$from_view    = actions_in( record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => 'example' ) ) ) ), ECOLE ) );
$members_link = link_of( isset( $from_view['members'] ) ? $from_view['members'] : '' );
$ecole_view   = html_of( page_for( $members_link[1] ) );

ck( 'a No account row\'s Manage members opens that institution\'s view with where the list stood, and the view\'s way back returns to the list as it stood: the No account view, searched',
	array( $members_link, has( $ecole_view, '<h2>École Example Charlie</h2>' ), back_of( $ecole_view ) ),
	array(
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_institution' => ECOLE, 'wpcpm_view' => 'no-account', 's' => 'example' ) ),
		true,
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => 'example' ) ),
	) );

// A term carrying an ampersand, a hash and a space, kept whole through a row's Manage members and the
// view's way back, and through the row's Create account and where it lands: each place writes the
// term as one query argument, encoded, and reads it back whole.
the_site();
manager( 123 );
seat( 123, 'Rae Manager', 'rmanager', array( 'administrator' ) );
$odd_record = 'recINSTKILOS00021';
$odd_term   = 'a&b #1';
$odd_state  = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => $odd_term ) );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $odd_record ] = index_row( $odd_record, 'Kilo a&b #1 Example', 'Confirmed', 'office@kilo-ab.example' );
$GLOBALS['settled'][ $odd_record ]                                            = true;

$odd_list    = html_of( draw( screen( array( 'wpcpm_view' => 'no-account', 's' => $odd_term ) ) ) );
$odd_actions = actions_in( record_row( $odd_list, $odd_record ) );
$odd_members = link_of( isset( $odd_actions['members'] ) ? $odd_actions['members'] : '' );
$odd_create  = link_of( isset( $odd_actions['create'] ) ? $odd_actions['create'] : '' );
$odd_back    = back_of( html_of( page_for( $odd_members[1] ) ) );
$odd_landed  = follow( isset( $odd_actions['create'] ) ? $odd_actions['create'] : '', 'handle_provision_one' );

ck( 'a term holding an ampersand, a hash and a space finds the institution that holds it, and its row\'s Manage members, the view\'s way back, its Create account and where that lands each keep the term whole',
	array(
		record_ids( $odd_list ),
		isset( $odd_members[1]['s'] ) ? $odd_members[1]['s'] : null,
		$odd_back,
		isset( $odd_create[1]['s'] ) ? $odd_create[1]['s'] : null,
		link_of( $odd_landed['redirect'] ),
		count( $GLOBALS['inserted'] ),
	),
	array( array( $odd_record ), $odd_term, $odd_state, $odd_term, $odd_state, 1 ) );

// École has had a member: Ivo, whose membership ended and whose account kept no role.
the_site();
manager( 115 );
seat( 115, 'Ida Manager', 'imanager', array( 'administrator' ) );
seat(
	27,
	'Ivo Pell',
	'ipell',
	array(),
	array(
		WPCPM_Institution_Members::META_RECORD_ID_WAS => ECOLE,
		WPCPM_Institution_Members::META_ACTIVE        => 0,
	)
);
$former_row  = record_row( html_of( draw( screen( array( 'wpcpm_view' => 'no-account' ) ) ) ), ECOLE );
$former_view = html_of( page_for( screen( array( 'wpcpm_institution' => ECOLE ) ) ) );

ck( 'an institution that has had a member is not ready for an account: its row sends the reader to Manage members, its one action, where that former member is listed with a Re-add',
	array( cell( $former_row, 'account' ), array_keys( actions_in( $former_row ) ), form_fields_of( $former_view, 'wpcpm_readd_institution_member' ) ),
	array(
		'It has had a member before. Re-add the member, or add another account, under Manage members.',
		array( 'members' ),
		array( array( '_wpnonce' => wp_create_nonce( 'wpcpm_readd_institution_member_27' ), 'action' => 'wpcpm_readd_institution_member', 'member' => '27', 'record' => ECOLE, 'wpcpm_from' => 'admin' ) ),
	) );

echo "\n=== The invitations: a row's, the ticked accounts', and the card's ===\n";

the_site();
manager( 80 );
$accounts = array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-institutions', 'tab' => 'accounts' ) );
$rows     = html_of( draw( screen() ) );
$ben_link = actions_in( row_for( $rows, 22 ) );
$ben_link = isset( $ben_link['invite'] ) ? $ben_link['invite'] : '';

ck( 'Ben\'s Send invite goes to the Institutions module\'s invite handler with his account, the tab to come back to and the nonce that handler checks',
	link_of( $ben_link ),
	array(
		'https://example.test/wp-admin/admin-post.php',
		array(
			'action'    => 'wpcpm_institutions_invite',
			'user'      => '22',
			'wpcpm_tab' => 'accounts',
			'_wpnonce'  => wp_create_nonce( 'wpcpm_institutions_invite' ),
		),
	) );

// Not `$sent`: at the top of a file a variable is a global, and the stand-in keeps what was sent there.
$ben_sent = follow( $ben_link, 'handle_invite' );

ck( 'followed, the institutions sync sends Ben his invitation now, the institution\'s, core\'s message to him alone, and stamps his account as an institution\'s, then the list says the email went',
	array( $GLOBALS['mails'], array_column( $GLOBALS['sent'], 'subject' ), array_column( $GLOBALS['sent'], 'notify' ), isset( $GLOBALS['umeta'][22]['wpcpm_inst_invited'] ) && is_int( $GLOBALS['umeta'][22]['wpcpm_inst_invited'] ), link_of( $ben_sent['redirect'] ), notice_now() ),
	array( array( 22 ), array( '[WPCredits] Your institution account is ready' ), array( 'user' ), true, $accounts, '<div class="notice notice-success is-dismissible"><p>Invitation email sent. It replaces the link in any earlier invitation, so ask them to use this newest email.</p></div>' ) );

// The shared stand-in writes core's message when core does: for `user` and `both`, for '' only with
// the old second argument set, never for `admin`. The administrator's copy, which core sends for
// anything but `user`, is not modeled, so it is noted, and every suite's last check reads the note:
// an invitation sent with any `$notify` but `user` fails there, whatever its other checks read.
$mails_kept     = $GLOBALS['mails'];
$sent_kept      = $GLOBALS['sent'];
$unmodeled_kept = $GLOBALS['unmodeled'];
$GLOBALS['mails'] = array();
$GLOBALS['sent']  = array();
foreach ( array( array( null, 'admin' ), array( null, '' ), array( 'a password', '' ), array( null, 'both' ), array( null, 'nobody' ) ) as $asked ) {
	wp_new_user_notification( 22, $asked[0], $asked[1] );
}

ck( 'the stand-in writes core\'s message to the user as core does, for both and for an empty notify given the old second argument alone, and notes the administrator\'s copy and a notify core refuses',
	array( $GLOBALS['mails'], array_column( $GLOBALS['sent'], 'notify' ), array_slice( $GLOBALS['unmodeled'], count( $unmodeled_kept ) ) ),
	array(
		array( 22, 22 ),
		array( '', 'both' ),
		array_merge( array_fill( 0, 4, 'wp_new_user_notification() the administrator\'s copy' ), array( 'wp_new_user_notification() notify nobody' ) ),
	) );

$GLOBALS['mails']     = $mails_kept;
$GLOBALS['sent']      = $sent_kept;
$GLOBALS['unmodeled'] = $unmodeled_kept;

the_site();
manager( 81 );
unset( $GLOBALS['umeta'][23]['wpcpm_mentor_invited'], $GLOBALS['umeta'][23]['wpcpm_inst_invited'] );
$carmen = actions_in( row_for( html_of( draw( screen() ) ), 23 ) );
follow( isset( $carmen['invite'] ) ? $carmen['invite'] : '', 'handle_invite' );

ck( 'Carmen, never invited and a mentor too, is offered Send invite; followed, she is sent the mentor\'s invitation, the one her account was made for, and stamped under both her roles',
	array( array_keys( $carmen ), $GLOBALS['mails'], array_column( $GLOBALS['sent'], 'subject' ), isset( $GLOBALS['umeta'][23]['wpcpm_mentor_invited'], $GLOBALS['umeta'][23]['wpcpm_inst_invited'] ) ),
	array( array( 'edit', 'view', 'invite', 'members' ), array( 23 ), array( '[WPCredits] Your mentor account is ready' ), true ) );

manager( 82 );
follow( str_replace( 'user=22', 'user=999', $ben_link ), 'handle_invite' );

ck( 'a row\'s send that fails comes back to the Accounts tab, which says the invitation could not be sent, sending nothing',
	array( notice_now(), $GLOBALS['mails'] ),
	array( '<div class="notice notice-error is-dismissible"><p>The invitation could not be sent.</p></div>', array() ) );

the_site();
manager( 83 );
$invite_with = array(
	'_wpnonce'         => wp_create_nonce( 'bulk-institutions' ),
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts',
	'action2'          => '-1',
);
$queued      = press( screen( array_merge( $invite_with, array( 'action' => 'invite', 'users' => array( '22', '24', '26', '21', '30' ) ) ) ) );

ck( 'Send invite on the ticked accounts queues the three never invited through the accounts base, leaving out Nora, invited before, and the student, who is no institution account, and comes back to the list, which says how many',
	array( WPCPM_Mail::queue(), link_of( $queued['redirect'] ), notice_now() ),
	array( array( 22, 24, 26 ), $accounts, '<div class="notice notice-success is-dismissible"><p>3 invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

manager( 84 );
press( screen( array_merge( $invite_with, array( 'action' => 'reinvite', 'users' => array( '21', '23' ) ) ) ) );

ck( 'Resend invite queues Nora and Carmen, invited before, one at a time, and says each new link replaces the old',
	array( WPCPM_Mail::queue(), notice_now() ),
	array( array( 21, 23 ), '<div class="notice notice-success is-dismissible"><p>2 invitations queued. They go out with the next batch, and each replaces the link in any earlier invitation.</p></div>' ) );

the_site();
manager( 85 );
$tab_page = html_of( page_for( screen() ) );
$card     = forms_by_action( $tab_page );
// The card's action by its value, which the module's constant holds (pinned with the screen's names below).
$card     = isset( $card['wpcpm_institutions_bulk_invite'] ) ? $card['wpcpm_institutions_bulk_invite'] : '';

ck( 'the invitations card counts the three accounts never sent an invitation under any stamp, in words that fit accounts, and its button names the Accounts tab',
	array( has( $card, '>Invite 3 institution accounts that have never been invited</button>' ), hidden_fields_of( $card )['wpcpm_tab'] ?? null, has( $tab_page, '<div class="wpcpm-card wpcpm-invites"><h2>Invitations</h2>' ) ),
	array( true, 'accounts', true ) );

// The press is the manager's next request. WPCPM_Flash remembers within one run of PHP what it took
// for a person, and drawing the tab above took this one's, so the press is made by somebody new.
$GLOBALS['uid'] = 87;
$pressed        = post_to( 'handle_bulk_invite', 'wpcpm_institutions_bulk_invite', array( 'wpcpm_tab' => 'accounts' ) );

ck( 'pressed, it queues the three and comes back to the Accounts tab, which says so',
	array( WPCPM_Mail::queue(), link_of( $pressed['redirect'] ), notice_now() ),
	array( array( 22, 24, 26 ), $accounts, '<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );

the_site();
manager( 86 );
$GLOBALS['umeta'][24]['wpcpm_inst_invited'] = 1780000000;
$GLOBALS['umeta'][26]['wpcpm_inst_invited'] = 1780000000;
$one_card                                   = forms_by_action( html_of( page_for( screen() ) ) );

ck( 'with one left, the button says so in the singular',
	has( isset( $one_card['wpcpm_institutions_bulk_invite'] ) ? $one_card['wpcpm_institutions_bulk_invite'] : '', '>Invite 1 institution account that has never been invited</button>' ),
	true );

echo "\n=== The screen's names, and the Accounts tab top to bottom ===\n";

$names = run(
	function () {
		return array( WPCPM_Institutions::PER_PAGE_OPTION, WPCPM_Institutions_Table::per_page_option(), WPCPM_Institutions::TAB_ACCOUNTS, WPCPM_Accounts_Table::TAB, WPCPM_Institutions::TAB_SYNC, WPCPM_Institutions_Table::bulk_nonce_action(), WPCPM_Institutions::FLASH_DETAIL, WPCPM_Institutions::ACTION_INVITE, WPCPM_Institutions::ACTION_BULK, WPCPM_Institutions_Table::role(), WPCPM_Institutions_Table::invite_meta() );
	}
);

ck( 'the module\'s names for the option and the tab are the table\'s; the bulk nonce, the count\'s channel and the two invitation actions are the Institutions\'; the role and the stamp are the institution account\'s',
	got(
		$names,
		function ( $out ) {
			return $out['value'];
		}
	),
	array( 'wpcpm_institutions_per_page', 'wpcpm_institutions_per_page', 'accounts', 'accounts', 'sync', 'bulk-institutions', 'institutions_admin_detail', 'wpcpm_institutions_invite', 'wpcpm_institutions_bulk_invite', WPCPM_Roles::ROLE_INSTITUTION, 'wpcpm_inst_invited' ) );

the_site();
manager( 90 );
WPCPM_Settings::$connected = false;
WPCPM_Flash::set( 'institutions', 'invited' );
$top_to_bottom             = html_of( page_for( screen() ) );
WPCPM_Settings::$connected = true;

ck( 'the Accounts tab reads top to bottom: the press\'s notice and the warning that Airtable is not connected, each once, above the tab bar, then the accounts locked today, the invitations card and the list',
	array(
		in_order(
			$top_to_bottom,
			array(
				'notice'      => '<div class="notice notice-success is-dismissible">',
				'warning'     => 'Airtable is not connected yet, so no institutions can be synced.',
				'bar'         => '<nav class="nav-tab-wrapper',
				'locked'      => '1 institution account is locked out of roster changes for the rest of today.',
				'invitations' => '<div class="wpcpm-card wpcpm-invites">',
				'list'        => '<h2>Institution accounts <span class="wpcpm-count">6</span></h2>',
			)
		),
		substr_count( $top_to_bottom, 'Airtable is not connected yet' ),
		substr_count( $top_to_bottom, 'is-dismissible' ),
	),
	array( array( 'notice', 'warning', 'bar', 'locked', 'invitations', 'list' ), 1, 1 ) );
ck( 'and no address is printed anywhere on it: the locked accounts are named by name and username', array( has( $top_to_bottom, '@' ), has( $top_to_bottom, 'Ben Okafor (bokafor)' ) ), array( false, true ) );

the_site();
manager( 92 );
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
