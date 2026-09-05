<?php
/**
 * Does one run of the sponsors sync produce the index and the team, copy the right logos
 * and only those, and handle sponsors that have left Approved the way the setting says - and
 * does the mentors sync's sponsorship row carry what the Sponsor Dashboard needs?
 *
 * The Airtable client is stood in for by a pager over `$GLOBALS['pages']`, built inline per
 * scenario rather than from a seed fixture: the table is thirty rows in production but this
 * suite only needs five sponsors and two team members to exercise every branch. The other
 * pieces the sync leans on are stood in for at their contracts, the same way the institutions
 * suite does it: `WPCPM_Sponsor_Members` for who acts for a sponsor, `WPCPM_Image_Upload` for
 * the one call that copies a logo, `WPCPM_Sponsors` for the dashboard-account checkbox this
 * sync updates through an array callable (Task 8's class, guarded by `class_exists()`).
 *
 * The failures this pins hardest: an empty or field-less read must never replace a good
 * index (the revoke phase reads it back and would detach every sponsor account on the site);
 * a logo the sponsor uploaded on the site (`source` = `site`) must never be overwritten by
 * Airtable's; an SVG logo must be refused, by name, rather than silently skipped or crashing;
 * and nothing here may ever provision an account - the sync only ever reads and, with the
 * setting on, detaches.
 *
 * Run from the plugin root:  php bin/test-sponsors-sync.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['cron']     = array();
$GLOBALS['users']    = array();
$GLOBALS['calls']    = array();

class WP_Error {
	public $code = '';
	public $message = '';
	public $data = null;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class WP_User {
	public $ID = 0, $roles = array(), $display_name = '', $user_email = '', $user_login = '';
	public function __construct( $id = 0 ) {
		$this->ID = (int) $id;
		if ( isset( $GLOBALS['users'][ $this->ID ] ) ) {
			$this->roles      = $GLOBALS['users'][ $this->ID ]['roles'];
			$this->user_email = isset( $GLOBALS['users'][ $this->ID ]['email'] ) ? $GLOBALS['users'][ $this->ID ]['email'] : '';
			$this->user_login = isset( $GLOBALS['users'][ $this->ID ]['login'] ) ? $GLOBALS['users'][ $this->ID ]['login'] : '';
		}
	}
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function absint( $v ) { return abs( (int) $v ); }
function is_email( $e ) { return false !== filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function apply_filters( $t, $v ) { return $v; }
function add_action( $hook, $cb ) { $GLOBALS['calls']['add_action'][] = $hook; }
function remove_filter() { return true; }
function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function add_option( $k, $v, $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	$GLOBALS['calls']['delete_option'][] = $k;
	unset( $GLOBALS['opts'][ $k ] );
	return true;
}
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_schedule_event( $ts, $rec, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; $GLOBALS['cron_recurrence'][ $hook ] = $rec; return true; }
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 0; }

/**
 * `get_users()` for the revoke phase: the institutions suite's version filters
 * `$GLOBALS['users']` by role, but this suite never populates that array - a sponsor
 * account here is only ever a row of `$GLOBALS['stamps']` (user ID => sponsor record ID),
 * which is also what the `WPCPM_Sponsor_Members` stand-in reads. So this answers the
 * `role => WPCPM_Roles::ROLE_SPONSOR` query with every stamped account, bare IDs when
 * `fields => 'ID'` is asked for (as the revoke phase always asks), `WP_User` objects
 * otherwise.
 */
function get_users( array $args ) {
	$out = array();
	foreach ( array_keys( $GLOBALS['stamps'] ) as $id ) {
		// This program's host returns stdClass rows for `fields => 'ID'` no matter what is
		// asked for (see WPCPM_Roles::id_of()); a bare int here would hide the very bug this
		// fixture exists to catch.
		$out[] = isset( $args['fields'] ) && 'ID' === $args['fields'] ? (object) array( 'ID' => $id ) : new WP_User( $id );
	}
	return $out;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( 'email' === $field && isset( $user['email'] ) && strtolower( $user['email'] ) === strtolower( (string) $value ) ) {
			return new WP_User( $id );
		}
	}
	return false;
}
function username_exists( $login ) {
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( isset( $user['login'] ) && $user['login'] === $login ) {
			return $id;
		}
	}
	return false;
}
function sanitize_user( $login, $strict = false ) { return preg_replace( '/[^a-z0-9 _.\-@]/i', '', (string) $login ); }
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return str_repeat( 'x', (int) $length ); }

/**
 * Creates an account, and can be made to fail or to die the way a real request can.
 *
 * `insert_fails` returns the WP_Error wp_insert_user() gives a duplicate login; `insert_dies`
 * throws, which is a request that never came back, so nothing after it in that tick runs and
 * the run state stays as the last completed slice left it.
 */
function wp_insert_user( $args ) {
	$GLOBALS['calls']['wp_insert_user'][] = $args;
	$n = count( $GLOBALS['calls']['wp_insert_user'] );

	if ( ! empty( $GLOBALS['insert_dies'] ) && (int) $GLOBALS['insert_dies'] === $n ) {
		throw new RuntimeException( 'the request died' );
	}

	if ( ! empty( $GLOBALS['insert_fails'] ) && (int) $GLOBALS['insert_fails'] === $n ) {
		return new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
	}

	$id                      = 100 + $n;
	$GLOBALS['users'][ $id ] = array(
		'roles' => array( $args['role'] ),
		'email' => $args['user_email'],
		'login' => $args['user_login'],
	);

	return $id;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

/* ---- the Airtable client: a pager over $GLOBALS['pages'] ------------------- */

/**
 * Serves `$GLOBALS['pages'][ $table ]`, an array of pages shaped as Airtable itself answers
 * a page (`records`, `offset`), records what it was asked, and fails on the page named in
 * `$GLOBALS['fail_page']` (1-based) with a WP_Error.
 *
 * `fetch_page()` is not the institutions suite's version verbatim: that one expects
 * `$GLOBALS['pages'][ $table ]` to be a flat array of record lists (`array_chunk()`'s
 * shape), one entry per page. This suite's fixtures are written the other way - each page
 * already carries its own `records` and `offset`, so a test can flip one sponsor's `Logo`
 * cell with `$GLOBALS['pages']['tblSPONSORS'][0]['records'][0]['fields']['Logo'] = ...` - so
 * `fetch_page()` reads that shape directly instead.
 */
class WPCPM_Airtable {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';

	public function __construct( $settings = null ) {}

	public function fetch_page( $table, array $args = array() ) {
		$GLOBALS['calls']['fetch_page'][] = array( 'table' => $table, 'args' => $args );

		$pages = isset( $GLOBALS['pages'][ $table ] ) ? $GLOBALS['pages'][ $table ] : array();
		$index = empty( $args['offset'] ) ? 0 : (int) $args['offset'];

		if ( ! empty( $GLOBALS['fail_page'] ) && ( $index + 1 ) === (int) $GLOBALS['fail_page'] ) {
			return new WP_Error( 'wpcpm_airtable_http', 'Airtable said 503 on page ' . ( $index + 1 ) );
		}

		$page = isset( $pages[ $index ] ) ? $pages[ $index ] : array( 'records' => array(), 'offset' => null );

		return array(
			'records' => isset( $page['records'] ) && is_array( $page['records'] ) ? $page['records'] : array(),
			'offset'  => isset( $page['offset'] ) ? $page['offset'] : null,
		);
	}

	public function fetch_all( $table, array $args = array() ) {
		$records = array();
		$offset  = null;
		do {
			$args['offset'] = $offset;
			$page           = $this->fetch_page( $table, $args );
			if ( is_wp_error( $page ) ) {
				return $page;
			}
			$records = array_merge( $records, $page['records'] );
			$offset  = $page['offset'];
		} while ( $offset );
		return $records;
	}

	/* Copied from the real client: the sync leans on all three. */
	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) {
				return (string) $value['name'];
			}
			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$parts[] = (string) $item;
				} elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
					$parts[] = (string) $item['name'];
				}
			}
			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		return '';
	}

	public static function link_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( $value as $item ) {
			if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) {
				$ids[] = $item;
			} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$ids[] = (string) $item['id'];
			}
		}
		return $ids;
	}

	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
	}
}

/**
 * Reads `$GLOBALS['settings']` merged over the four keys the sponsors sync asks of it. Not
 * the real class: that one stores under `get_option( 'wpcpm_settings' )`, and every scenario
 * below sets `$GLOBALS['settings']` directly, the way `bin/test-image-upload.php`'s stand-in
 * does it.
 */
class WPCPM_Settings {
	public static function get() {
		$defaults = array(
			'sponsors_table'      => 'tblSPONSORS',
			'sponsors_name_field' => '',
			'team_members_table'  => 'tblTEAM',
			'sponsor_on_inactive' => 'keep',
		);

		return array_merge( $defaults, isset( $GLOBALS['settings'] ) && is_array( $GLOBALS['settings'] ) ? $GLOBALS['settings'] : array() );
	}

	public static function is_connected() {
		$settings = self::get();

		return ! empty( $settings['api_token'] ) && ! empty( $settings['base_id'] );
	}
}

/* ---- stand-ins for the other pieces, at their contracts -------------------- */

if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	class WPCPM_Students_Sync {
		const CRON_AUTO = 'wpcpm_students_daily';
	}
}

if ( ! class_exists( 'WPCPM_Countries' ) ) {
	class WPCPM_Countries {
		const OPT_NAME = 'wpcpm_countries';
		public static function refresh( $airtable = null ) {
			$GLOBALS['calls']['countries_refresh'][] = $airtable instanceof WPCPM_Airtable;
			if ( ! empty( $GLOBALS['countries_error'] ) ) {
				return new WP_Error( 'wpcpm_countries_http', 'Countries unreadable' );
			}
			update_option( self::OPT_NAME, array( 'v' => 1, 'read' => time(), 'rows' => $GLOBALS['countries'] ), false );
			return true;
		}
		public static function all() {
			$stored = get_option( self::OPT_NAME );
			return isset( $stored['rows'] ) ? $stored['rows'] : array();
		}
		public static function name_of( $record_id ) {
			$rows = self::all();
			return isset( $rows[ $record_id ] ) ? $rows[ $record_id ]['name'] : '';
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	class WPCPM_Institution_Agreement {
		const STAGE_ORDER      = array( 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Confirmed', 'Student' );
		const TERMINAL_STAGES  = array( 'Not Moving Forward', 'SPAM', 'Revisit Later' );
		const AIRTABLE_SETTLED = array( 'Accepted', 'On file' );
		const OPT_PREFIX    = 'wpcpm_agreement_';
		public static function option_name( $record_id ) { return self::OPT_PREFIX . $record_id; }
		public static function rebuild( $record_id, array $airtable ) {
			$GLOBALS['calls']['rebuild'][ $record_id ] = $airtable;
			$settled = in_array( $airtable['status'], self::AIRTABLE_SETTLED, true ) && ( 'On file' !== $airtable['status'] || '' !== $airtable['document'] );
			$option  = array( 'v' => 1, 'settled' => $settled, 'airtable_status' => $airtable['status'], 'drive_url' => $airtable['document'], 'updated' => time() );
			update_option( self::option_name( $record_id ), $option, false );
			return $option;
		}
		public static function is_settled( $record_id ) {
			$option = get_option( self::option_name( $record_id ) );
			return is_array( $option ) && ! empty( $option['settled'] );
		}
		public static function stored_records() {
			$out = array();
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, self::OPT_PREFIX ) ) {
					$suffix = substr( $name, strlen( self::OPT_PREFIX ) );
					if ( WPCPM_Mentors_Sync::is_record_id( $suffix ) ) {
						$out[] = $suffix;
					}
				}
			}
			return $out;
		}
	}
}

if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/** The invitation queue: what matters here is who was queued, and that it was queued once. */
	class WPCPM_Mail {
		public static function queue_invites( array $user_ids ) {
			$GLOBALS['calls']['queue_invites'][] = array_values( array_map( 'intval', $user_ids ) );
			return count( $user_ids );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	class WPCPM_Institution_Members {
		const HOW_PROVISIONED = 'provisioned';
		/** Stands in for the real attach(): the stamp, the flag and the role, or a refusal. */
		public static function attach( $user_id, $record_id, $how, $actor_id, $invite_id = 0 ) {
			$GLOBALS['calls']['attach'][] = array( (int) $user_id, $record_id, $how, (int) $actor_id );

			if ( ! empty( $GLOBALS['attach_fails'] ) ) {
				return new WP_Error( 'wpcpm_member_not_indexed', 'That institution is not in the pipeline index yet.' );
			}

			$GLOBALS['members'][ $user_id ] = $record_id;

			if ( ! in_array( WPCPM_Roles::ROLE_INSTITUTION, $GLOBALS['users'][ $user_id ]['roles'], true ) ) {
				$GLOBALS['users'][ $user_id ]['roles'][] = WPCPM_Roles::ROLE_INSTITUTION;
			}

			return true;
		}
		public static function institution_of( $user = null ) {
			$id = $user instanceof WP_User ? $user->ID : (int) $user;
			return isset( $GLOBALS['members'][ $id ] ) ? $GLOBALS['members'][ $id ] : '';
		}
		public static function members_of( $record_id ) {
			$out = array();
			foreach ( $GLOBALS['members'] as $id => $record ) {
				if ( 0 === strcmp( $record, $record_id ) ) {
					$out[] = new WP_User( $id );
				}
			}
			return $out;
		}
		public static function former_members_of( $record_id ) {
			$out = array();
			foreach ( isset( $GLOBALS['former'] ) ? $GLOBALS['former'] : array() as $id => $record ) {
				if ( 0 === strcmp( $record, $record_id ) ) {
					$out[] = new WP_User( $id );
				}
			}
			return $out;
		}
		public static function detach( $user_id, $reason, $actor_id ) {
			$GLOBALS['calls']['detach'][] = array( $user_id, $reason, $actor_id );
			unset( $GLOBALS['members'][ $user_id ] );
			$GLOBALS['users'][ $user_id ]['roles'] = array( 'subscriber' );
			return true;
		}
	}
}

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

// The real mentors sync, loaded here rather than down by the "sponsorship row" checks: both
// WPCPM_Sponsors_Index and WPCPM_Sponsors_Sync call WPCPM_Mentors_Sync::is_record_id()
// throughout, and this suite also asserts the real fields()/sponsorship_row()/OPT_SPONSORSHIP
// later on - one class, loaded once, rather than a stand-in that PHP would then refuse to let
// the real file redeclare. Nothing above declares a WPCPM_Mentors_Sync of its own for exactly
// that reason. Its own is_record_id() delegates to WPCPM_Airtable::is_record_id(), added to
// the stand-in above alongside flatten()/link_ids() so that call resolves.
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';

class WPCPM_Roles {
	const ROLE_SPONSOR = 'wpcpm_sponsor';
	const CAP_MANAGE   = 'wpcpm_manage_program';
	/* Copied from the real class (includes/class-wpcpm-roles.php): an int, a numeric string,
	   a stdClass row (this host's get_users() shape) and a WP_User all resolve; else 0. */
	public static function id_of( $entry ) {
		if ( is_object( $entry ) ) { return isset( $entry->ID ) ? (int) $entry->ID : 0; }
		return is_numeric( $entry ) ? (int) $entry : 0;
	}
}
class WPCPM_Institutions_Sync { const CRON_DAILY = 'wpcpm_institutions_sync_daily'; }
class WPCPM_Image_Upload {
	const TYPES = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp' );
	/** A flat 1 MB ceiling: enough to keep the fixture's ordinary logos and refuse the oversized one. */
	public static function max_bytes( array $rules ) { return 1024 * 1024; }
	public static function sideload( $url, $name, $author, $title, array $rules = array() ) {
		$GLOBALS['sideloads'][] = array( $url, $name, $author, $title );
		if ( isset( $GLOBALS['sideload_fail'][ $url ] ) ) { return new WP_Error( 'wpcpm_image_download', 'Not Found' ); }
		return 500 + count( $GLOBALS['sideloads'] );
	}
}
class WPCPM_Sponsor_Members {
	const REASON_REVOKED = 'revoked';
	public static function sponsor_of( $user ) { $id = is_object( $user ) ? $user->ID : (int) $user; return isset( $GLOBALS['stamps'][ $id ] ) ? $GLOBALS['stamps'][ $id ] : ''; }
	public static function members_of( $record ) { $out = array(); foreach ( $GLOBALS['stamps'] as $id => $r ) { if ( $r === $record ) { $out[] = new WP_User( $id ); } } return $out; }
	public static function detach( $user_id, $reason, $actor ) { $GLOBALS['detached'][] = array( $user_id, $reason ); unset( $GLOBALS['stamps'][ $user_id ] ); return true; }
}
class WPCPM_Sponsors {
	public static function mark_dashboard_account( $record, $flag ) { $GLOBALS['marked'][] = array( $record, $flag ); return true; }
}
/**
 * Enough of `$wpdb` for `WPCPM_Sponsors_Index::delete_all()`'s one raw query: a LIKE-prefix
 * DELETE against the options table it keeps in `$GLOBALS['opts']`.
 */
class WPCPM_Test_DB {
	public $options = 'wp_options';
	public function prepare( $sql, ...$args ) { return vsprintf( str_replace( '%s', "'%s'", $sql ), $args ); }
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function query( $sql ) {
		if ( preg_match( "/LIKE '(.*)%'\$/", $sql, $m ) ) {
			$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, $prefix ) ) { unset( $GLOBALS['opts'][ $name ] ); }
			}
		}
		return true;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_DB();
function get_post( $id ) { return in_array( (int) $id, $GLOBALS['attachments_alive'], true ) ? (object) array( 'ID' => (int) $id ) : null; }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return in_array( (int) $id, $GLOBALS['attachments_alive'], true ) ? 'https://example.test/uploads/' . $id . '.png' : false; }

require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-sync.php';

function run_to_end() { for ( $i = 0; $i < 50 && WPCPM_Sponsors_Sync::is_running(); $i++ ) { WPCPM_Sponsors_Sync::run_tick( WPCPM_Sponsors_Sync::BUDGET_AJAX ); } }
function att( $id, $file, $type, $w = 300, $h = 100, $size = 1000 ) { return array( 'id' => $id, 'url' => 'https://v5.airtableusercontent.com/x/' . $id . '/' . $file, 'filename' => $file, 'type' => $type, 'size' => $size, 'width' => $w, 'height' => $h ); }

$A = 'recSPONSOR0000001';  // Approved, PNG logo
$B = 'recSPONSOR0000002';  // Approved, SVG logo
$C = 'recSPONSOR0000003';  // Approved, no logo, no name
$D = 'recSPONSOR0000004';  // Paused, PNG logo
$E = 'recSPONSOR0000005';  // Approved, logo already copied
$TM1 = 'recTEAM0000000001';
$TM2 = 'recTEAM0000000002';
$M1 = 'recMENTOR00000001';
$M2 = 'recMENTOR00000002';

$GLOBALS['pages']['tblTEAM'] = array( array( 'records' => array(
	array( 'id' => $TM1, 'fields' => array( 'Name' => 'Maciej (Matt) Pilarski', 'Email' => 'maciej@a8c.com', 'Calendly link' => 'https://calendly.com/matt' ) ),
	array( 'id' => $TM2, 'fields' => array( 'Name' => 'Francesco Di Candia', 'Email' => 'maciej@a8c.com' ) ),
	array( 'id' => 'bad', 'fields' => array( 'Name' => 'Nobody' ) ),
), 'offset' => null ) );
$GLOBALS['pages']['tblSPONSORS'] = array( array( 'records' => array(
	array( 'id' => $A, 'createdTime' => '2026-04-13T08:53:43.000Z', 'fields' => array( 'Company Name' => 'miniOrange ', 'Website' => 'https://plugins.miniorange.com/', 'Contact Person Full Name' => 'Rep One', 'Contact Email' => 'Maciej@a8c.com', 'Status' => 'Approved', 'Sponsorship options' => 'Sponsor mentors + tools/services', 'How would you like to support WP Credits?' => array( 'Sponsor tools or services', 'Sponsor a mentor or multiple mentors' ), 'Type of product' => 'Hosting', 'Offer' => 'One year free', 'Brief instructions' => 'Use the code.', 'More info link' => 'https://plugins.miniorange.com/offer', 'Coupon code/discount link' => 'https://sheet.test/x', "Anything else you'd like to share." => 'Happy to help students.', 'Person of contact' => array( $TM1 ), 'Mentors' => array( $M1, $M2 ), 'Logo' => array( att( 'attA', 'miniorange-300w.png', 'image/png' ) ), 'Privacy Policy Compliance' => true, 'Agreement Status' => 'Accepted', 'Agreement Accepted On' => '2026-09-01', 'Agreement Document' => 'https://drive.test/doc', 'Sponsorship interests' => "2026-09-01: Sponsor tools or services", 'Dashboard account' => true ) ),
	array( 'id' => $B, 'fields' => array( 'Company Name' => 'Wetopi', 'Status' => 'Approved', 'Person of contact' => array( 'recUNKNOWN0000001' ), 'Logo' => array( att( 'attB', 'wetopi-text-logo.svg', 'image/svg+xml' ) ) ) ),
	array( 'id' => $C, 'fields' => array( 'Status' => 'Approved' ) ),
	array( 'id' => $D, 'fields' => array( 'Company Name' => 'Elicus', 'Status' => 'Paused', 'Logo' => array( att( 'attD', 'elicus.png', 'image/png' ) ) ) ),
	array( 'id' => $E, 'fields' => array( 'Company Name' => 'Hostinger', 'Status' => 'Approved', 'Logo' => array( att( 'attE', 'hostinger-300w.png', 'image/png' ) ) ) ),
	array( 'id' => 'garbage', 'fields' => array( 'Company Name' => 'Not a record' ) ),
), 'offset' => null ) );
$GLOBALS['settings'] = array( 'api_token' => 'pat', 'base_id' => 'appX' );
$GLOBALS['attachments_alive'] = array( 777 );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::logo_option( $E ) ] = array( 'colour' => 777, 'white' => 0, 'source' => 'airtable', 'airtable_id' => 'attE' );
$GLOBALS['sideloads'] = array();
$GLOBALS['stamps']    = array( 5 => $A, 6 => $D, 7 => $E );
$GLOBALS['detached']  = array();
$GLOBALS['marked']    = array();

echo "=== The columns are pinned ===\n";
$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/sponsors-table-fields.json' ), true );
ck( 'every column fields() reads is a field of the Sponsors table', array_values( array_diff( WPCPM_Sponsors_Sync::fields(), (array) $fixture['fields'] ) ), array() );
ck( 'and every Team Members column too', array_values( array_diff( WPCPM_Sponsors_Sync::team_fields(), (array) $fixture['team_members_fields'] ) ), array() );
ck( 'fields() is a plain array, not a filter', strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-sync.php' ), 'apply_filters' ), false );

echo "\n=== A run ===\n";
ck( 'start() opens on the team phase', WPCPM_Sponsors_Sync::start() === true && 'team' === $GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_STATE ]['phase'], true );
run_to_end();
ck( 'and the run finishes', WPCPM_Sponsors_Sync::is_running(), false );
$report = $GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_REPORT ];
ck( 'the report counts what was read', array( $report['stats']['team_seen'], $report['stats']['records_seen'], $report['stats']['approved'], $report['stats']['skipped'], $report['stats']['nameless'] ), array( 2, 5, 4, 1, 1 ) );
ck( 'the team is keyed by record, addresses lowered, the malformed row dropped', WPCPM_Sponsors_Index::team(), array( $TM1 => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => 'https://calendly.com/matt' ), $TM2 => array( 'name' => 'Francesco Di Candia', 'email' => 'maciej@a8c.com', 'calendly' => '' ) ) );
$rows = WPCPM_Sponsors_Index::rows();
ck( 'five sponsors are indexed, in Airtable\'s order', array_keys( $rows ), array( $A, $B, $C, $D, $E ) );
ck( 'every row has every key', array_keys( $rows[ $C ] ), array_keys( WPCPM_Sponsors_Index::empty_row() ) );
ck( 'the name is kept as the base holds it, trailing space and all', $rows[ $A ]['name'], 'miniOrange ' );
ck( 'the address is lowered, the links are IDs, the support is a list', array( $rows[ $A ]['contact_email'], $rows[ $A ]['manager'], $rows[ $A ]['mentors'], $rows[ $A ]['support'] ), array( 'maciej@a8c.com', $TM1, array( $M1, $M2 ), array( 'Sponsor tools or services', 'Sponsor a mentor or multiple mentors' ) ) );
ck( 'the logo is the first attachment, reduced', $rows[ $A ]['logo'], att( 'attA', 'miniorange-300w.png', 'image/png' ) );
ck( 'the agreement, the interests, the checkbox and the created date', array( $rows[ $A ]['agreement'], $rows[ $A ]['interests'], $rows[ $A ]['dashboard_account'], $rows[ $A ]['created'] ), array( array( 'status' => 'Accepted', 'accepted_on' => '2026-09-01', 'has_document' => true ), '2026-09-01: Sponsor tools or services', true, '2026-04-13' ) );
ck( 'the free-text field is read too, for the profile card', $rows[ $A ]['anything'], 'Happy to help students.' );
ck( 'a sponsor with nothing has empty values, not missing ones', array( $rows[ $C ]['name'], $rows[ $C ]['logo'], $rows[ $C ]['mentors'], $rows[ $C ]['consent'] ), array( '', array(), array(), false ) );
ck( 'approved() is the four', array_keys( WPCPM_Sponsors_Index::approved() ), array( $A, $B, $C, $E ) );
ck( 'status_counts()', WPCPM_Sponsors_Index::status_counts(), array( 'Approved' => 4, 'Paused' => 1 ) );
ck( 'manager_of() resolves at read time', WPCPM_Sponsors_Index::manager_of( $A ), array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => 'https://calendly.com/matt' ) );
ck( 'and answers null for a manager the team does not know, or none', array( WPCPM_Sponsors_Index::manager_of( $B ), WPCPM_Sponsors_Index::manager_of( $C ) ), array( null, null ) );
ck( 'last_read() is stamped', WPCPM_Sponsors_Sync::last_read() > 0, true );

echo "\n=== Logos ===\n";
ck( 'one logo was copied: the Approved PNG that the site did not hold', array_map( function ( $s ) { return $s[1]; }, $GLOBALS['sideloads'] ), array( 'miniorange-300w.png' ) );
ck( 'by the sync, with no author and the company\'s title', array( $GLOBALS['sideloads'][0][2], $GLOBALS['sideloads'][0][3] ), array( 0, 'miniOrange logo (colour)' ) );
ck( 'and recorded as Airtable\'s', WPCPM_Sponsors_Index::logo_record( $A ), array( 'colour' => 501, 'white' => 0, 'source' => 'airtable', 'airtable_id' => 'attA' ) );
ck( 'the SVG was refused, with a notice that names the format', $report['stats']['logos_refused'] === 1 && false !== strpos( implode( "\n", $report['notices'] ), 'Wetopi' ) && false !== strpos( implode( "\n", $report['notices'] ), 'image/svg+xml' ), true );
ck( 'the already-copied logo was kept, the Paused sponsor\'s never fetched', array( $report['stats']['logos_kept'], WPCPM_Sponsors_Index::logo_record( $D )['colour'] ), array( 1, 0 ) );
$GLOBALS['attachments_alive'][] = 501;
ck( 'display_logo() is the attachment', WPCPM_Sponsors_Index::display_logo( $A ), array( 'id' => 501, 'url' => 'https://example.test/uploads/501.png' ) );
ck( 'never the Airtable URL, and nothing for a sponsor the site holds no logo for', array( WPCPM_Sponsors_Index::display_logo( $B ), WPCPM_Sponsors_Index::display_logo( $D ) ), array( null, null ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Index::logo_option( $A ) ] = array( 'colour' => 900, 'white' => 0, 'source' => 'site', 'airtable_id' => '' );
$GLOBALS['pages']['tblSPONSORS'][0]['records'][0]['fields']['Logo'] = array( att( 'attA2', 'new-logo.png', 'image/png' ) );
$GLOBALS['sideloads'] = array();
WPCPM_Sponsors_Sync::start();
run_to_end();
ck( 'a logo the sponsor uploaded on the site is never overwritten by Airtable\'s', array( $GLOBALS['sideloads'], WPCPM_Sponsors_Index::logo_record( $A )['source'] ), array( array(), 'site' ) );

echo "\n=== Revoke ===\n";
ck( 'with keep, nobody is detached and the count says so', array( $GLOBALS['detached'], $GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_REPORT ]['stats']['inactive_kept'] ), array( array(), 1 ) );
$GLOBALS['settings']['sponsor_on_inactive'] = 'revoke';
WPCPM_Sponsors_Sync::start();
run_to_end();
ck( 'with revoke, the Paused sponsor\'s account is detached, the Approved ones untouched', $GLOBALS['detached'], array( array( 6, 'revoked' ) ) );
ck( 'and the base is told the sponsor has no account now', $GLOBALS['marked'], array( array( $D, false ) ) );

echo "\n=== The empty answer and the machine ===\n";
$GLOBALS['pages']['tblSPONSORS'] = array( array( 'records' => array(), 'offset' => null ) );
WPCPM_Sponsors_Sync::start();
run_to_end();
ck( 'an empty read never replaces a good index', array( count( WPCPM_Sponsors_Index::rows() ), strpos( (string) $GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_ERROR ], 'no record came back' ) !== false ), array( 5, true ) );
WPCPM_Sponsors_Sync::cancel();
ck( 'cancel() clears the state', WPCPM_Sponsors_Sync::is_running(), false );
$GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_STATE ] = array( 'phase' => 'logos', 'offset' => null, 'started' => time() - 10, 'touched' => time(), 'steps' => array( 'team' => 1, 'records' => 1, 'logos' => 3 ), 'rows' => array(), 'logos' => array( $C ), 'stats' => WPCPM_Sponsors_Sync::empty_stats(), 'notices' => array() );
$p = WPCPM_Sponsors_Sync::progress();
ck( 'progress() reads the phase, the step and the percent', array( $p['running'], $p['phase'], $p['step'], $p['step_total'], $p['percent'] > 40 && $p['percent'] < 60, $p['stalled'] ), array( true, 'logos', 3, 4, true, false ) );
$GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_STATE ]['touched'] = time() - WPCPM_Sponsors_Sync::LOCK_TIMEOUT - 5;
ck( 'a run whose ticks stopped reads as stalled', WPCPM_Sponsors_Sync::progress()['stalled'], true );
$GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_LOCK ] = time() - WPCPM_Sponsors_Sync::LOCK_TIMEOUT - 5;
WPCPM_Sponsors_Sync::run_tick( 1 );
ck( 'a stale lock is taken over and the placed state runs to the end', WPCPM_Sponsors_Sync::is_running(), false );
ck( 'the daily event sits four hours after the institutions run', WPCPM_Sponsors_Sync::SCHEDULE_OFFSET_HOURS, 4 );

echo "\n=== The mentors sync's sponsorship row ===\n";
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';  // already loaded above; require_once is a no-op here
$mf  = WPCPM_Mentors_Sync::fields();
$row = WPCPM_Mentors_Sync::sponsorship_row( array( $mf['mentor_name'] => 'Emilia Pustelnik', $mf['mentor_profile'] => 'https://profiles.wordpress.org/emilia/', $mf['mentor_sponsored'] => 'Yes', $mf['mentor_wants_sponsor'] => 'No', $mf['mentor_sponsor_company'] => array( $A ), $mf['mentor_expertise'] => array( 'Core', 'Polyglots' ) ), $mf, 42, 'Active' );
ck( 'a sponsored mentor\'s row', $row, array( 'name' => 'Emilia Pustelnik', 'profile' => 'https://profiles.wordpress.org/emilia/', 'status' => 'Active', 'user_id' => 42, 'sponsored' => true, 'wants' => false, 'company' => array( $A ), 'expertise' => array( 'Core', 'Polyglots' ) ) );
ck( 'and an unsponsored one who wants a sponsor, with nothing else filled', WPCPM_Mentors_Sync::sponsorship_row( array( $mf['mentor_name'] => 'Nilo', $mf['mentor_wants_sponsor'] => 'Yes' ), $mf, 0, 'Active' ), array( 'name' => 'Nilo', 'profile' => '', 'status' => 'Active', 'user_id' => 0, 'sponsored' => false, 'wants' => true, 'company' => array(), 'expertise' => array() ) );
ck( 'the option name', WPCPM_Mentors_Sync::OPT_SPONSORSHIP, 'wpcpm_mentors_sponsorship' );

echo "\n=== A logo larger than the site allows ===\n";
$GLOBALS['settings']['sponsor_on_inactive'] = 'keep'; // back to the "Revoke" section's default; a fresh, self-contained scenario below.
$F = 'recSPONSOR0000006';
$GLOBALS['pages']['tblSPONSORS'] = array( array( 'records' => array(
	array( 'id' => $F, 'fields' => array( 'Company Name' => 'Bigfile Co', 'Status' => 'Approved', 'Logo' => array( att( 'attF', 'huge.png', 'image/png', 300, 100, 5 * 1024 * 1024 ) ) ) ),
), 'offset' => null ) );
$GLOBALS['sideloads'] = array();
WPCPM_Sponsors_Sync::start();
run_to_end();
$report = $GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_REPORT ];
ck( 'a logo larger than the site allows is never downloaded, and the notice names the sponsor', array( $GLOBALS['sideloads'], $report['stats']['logos_refused'], false !== strpos( implode( "\n", $report['notices'] ), 'Bigfile Co' ) ), array( array(), 1, true ) );

echo "\n=== A vanished Team Members table must not blank every contact ===\n";
ck( 'the team is not empty before this scenario', empty( WPCPM_Sponsors_Index::team() ), false );
$GLOBALS['pages']['tblTEAM'] = array( array( 'records' => array(), 'offset' => null ) );
WPCPM_Sponsors_Sync::start();
run_to_end();
ck(
	'a filtered or renamed Team Members table refuses rather than blanking every contact',
	array( empty( WPCPM_Sponsors_Index::team() ), false !== strpos( (string) $GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_ERROR ], 'blank every one of them' ) ),
	array( false, true )
);
WPCPM_Sponsors_Sync::cancel();

echo "\n=== A first run against a table missing a column ===\n";
// Both tables reset: the team page back to a valid read (this scenario is about the sponsors
// table, not the one above), and no stored index at all - the unknown-column signature (a
// read that comes back with records but none of them named) must refuse even before any
// index has ever been written, not only when one already exists.
$GLOBALS['pages']['tblTEAM'] = array( array( 'records' => array(
	array( 'id' => $TM1, 'fields' => array( 'Name' => 'Maciej (Matt) Pilarski', 'Email' => 'maciej@a8c.com' ) ),
), 'offset' => null ) );
unset( $GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ] );
$GLOBALS['pages']['tblSPONSORS'] = array( array( 'records' => array(
	array( 'id' => $A, 'fields' => array() ),
	array( 'id' => $B, 'fields' => array() ),
), 'offset' => null ) );
WPCPM_Sponsors_Sync::start();
run_to_end();
ck(
	'a first run against a table missing a column writes nothing and says which',
	array( WPCPM_Sponsors_Index::rows(), false !== strpos( (string) $GLOBALS['opts'][ WPCPM_Sponsors_Sync::OPT_ERROR ], 'does not exist in the base yet' ) ),
	array( array(), true )
);
WPCPM_Sponsors_Sync::cancel();

echo "\n=== delete_all() clears every logo option, not only the indexed rows' ===\n";
$GLOBALS['opts'][ WPCPM_Sponsors_Index::logo_option( 'recORPHANED000001' ) ] = array( 'colour' => 999, 'white' => 0, 'source' => 'airtable', 'airtable_id' => 'attX' );
WPCPM_Sponsors_Index::delete_all();
ck(
	'a logo option whose sponsor has since left the index is removed too, by prefix',
	array( array_keys( WPCPM_Sponsors_Index::rows() ), get_option( WPCPM_Sponsors_Index::logo_option( 'recORPHANED000001' ) ) ),
	array( array(), false )
);

echo "\n=== House rules ===\n";
ck( 'no em or en dash in the two classes', preg_match( '/\x{2013}|\x{2014}/u', file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php' ) . file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-sync.php' ) ), 0 );
ck( 'the sync never creates an account', strpos( file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-sync.php' ), 'insert_user' ), false );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', 47 );
exit( $fail ? 1 : 0 );
