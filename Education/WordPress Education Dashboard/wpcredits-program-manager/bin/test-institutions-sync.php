<?php
/**
 * Does one run of the institutions sync produce the index, rebuild every gate, and close
 * the ones that have left the pipeline, and nothing else?
 *
 * The Airtable client is stood in for by a pager over bin/fixtures/institutions-index-seed.json,
 * so the run reads whatever the seed fixture last read from the base. The other pieces of
 * the module are stood in for at their contracts and record what they were asked, which
 * is what most of the assertions read: the countries map refreshed once and first, the
 * columns requested (and the prose columns not), `rebuild()` called once per record with
 * the Drive link the index must not hold, `detach()` called for the members of an
 * institution at `Not Moving Forward` and of one the base no longer has, and not for the
 * member of a Confirmed one.
 *
 * The failure this pins hardest is the half-written index: a page that errors must leave
 * last night's index in place, because a manager screen drawn from half a table would
 * show institutions as gone and the revoke phase would act on it.
 *
 * Run from the plugin root:  php bin/test-institutions-sync.php
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
function get_users( $args ) {
	$ids = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( ! empty( $args['role'] ) && ! in_array( $args['role'], $user['roles'], true ) ) {
			continue;
		}
		$ids[] = $id;
	}
	return $ids;
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

	$id                     = 100 + $n;
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

/* ---- the Airtable client: a pager over the fixture ------------------------- */

/**
 * Returns pages from `$GLOBALS['pages'][ $table ]`, records what it was asked, and fails
 * on the page named in `$GLOBALS['fail_page']` (1-based) with a WP_Error.
 */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}

	public function fetch_page( $table, array $args = array() ) {
		$GLOBALS['calls']['fetch_page'][] = array( 'table' => $table, 'args' => $args );

		$pages  = isset( $GLOBALS['pages'][ $table ] ) ? $GLOBALS['pages'][ $table ] : array();
		$number = ! empty( $args['offset'] ) ? (int) substr( $args['offset'], 4 ) : 1;

		if ( ! empty( $GLOBALS['fail_page'] ) && (int) $GLOBALS['fail_page'] === $number ) {
			return new WP_Error( 'wpcpm_airtable_http', 'Airtable said 503 on page ' . $number );
		}

		$records = isset( $pages[ $number - 1 ] ) ? $pages[ $number - 1 ] : array();

		return array(
			'records' => $records,
			'offset'  => isset( $pages[ $number ] ) ? 'page' . ( $number + 1 ) : null,
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

	/* Copied from the real client: the sync leans on both. */
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
}

/* ---- stand-ins for the other pieces, at their contracts -------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

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

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php';

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
 * The seed fixture, decoded, or an empty array when it is missing or not JSON.
 *
 * @return array
 */
function seed() {
	$path = WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-index-seed.json';
	$data = file_exists( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;

	return is_array( $data ) ? $data : array();
}

/**
 * The fixture as Airtable would return it: records with `id`, `createdTime` and `fields`
 * holding only the cells that are set, the way the API omits empty ones.
 *
 * @param array $seed     The fixture.
 * @param array $override Per record ID, cells to add or replace.
 * @return array Pages of 100 records.
 */
function airtable_pages( array $seed, array $override = array() ) {
	$records = array();

	foreach ( $seed['institutions'] as $inst ) {
		$fields = array();

		if ( '' !== $inst['name'] ) {
			$fields['Name'] = $inst['name'];
		}
		if ( '' !== $inst['stage'] ) {
			$fields['Current Stage'] = $inst['stage'];
		}
		if ( ! empty( $inst['country'] ) ) {
			$fields['Country'] = $inst['country'];
		}
		foreach ( array( 'City' => 'city', 'Website' => 'website', 'Confirmed on' => 'confirmed_on' ) as $column => $key ) {
			if ( '' !== $inst[ $key ] ) {
				$fields[ $column ] = $inst[ $key ];
			}
		}
		if ( ! empty( $inst['consent'] ) ) {
			$fields['Privacy Policy Compliance'] = true;
		}
		foreach ( array( 'Agreement Status' => 'status', 'Agreement Kind' => 'kind', 'Agreement Accepted On' => 'accepted_on', 'Agreement Signed On' => 'signed_on', 'Agreement Accepted By' => 'accepted_by', 'Agreement Submitted On' => 'submitted_on', 'Agreement Template Version' => 'template_version' ) as $column => $key ) {
			if ( '' !== $inst['agreement'][ $key ] ) {
				$fields[ $column ] = $inst['agreement'][ $key ];
			}
		}
		if ( isset( $override[ $inst['id'] ] ) ) {
			$fields = array_merge( $fields, $override[ $inst['id'] ] );
		}

		$records[] = array(
			'id'          => $inst['id'],
			'createdTime' => $inst['createdTime'],
			'fields'      => $fields,
		);
	}

	return array_chunk( $records, 100 );
}

/**
 * Drive a run to the end, the way the browser poll does, with a ceiling so a run that
 * never finishes fails the suite instead of hanging it.
 *
 * @return int Ticks taken.
 */
function run_to_end() {
	$ticks = 0;
	while ( WPCPM_Institutions_Sync::is_running() && $ticks < 50 ) {
		WPCPM_Institutions_Sync::tick( WPCPM_Institutions_Sync::BUDGET_AJAX );
		++$ticks;
	}
	return $ticks;
}

/**
 * A clean site: settings connected, the fixture loaded into the client, no members.
 *
 * @param array $seed     The fixture.
 * @param array $override Cells to add to particular records.
 */
function reset_site( array $seed, array $override = array() ) {
	$GLOBALS['opts']            = array();
	$GLOBALS['autoload']        = array();
	$GLOBALS['cron']            = array();
	$GLOBALS['calls']           = array();
	$GLOBALS['users']           = array();
	$GLOBALS['members']         = array();
	$GLOBALS['former']          = array();
	$GLOBALS['fail_page']       = 0;
	$GLOBALS['countries_error'] = false;
	$GLOBALS['insert_fails']    = 0;
	$GLOBALS['insert_dies']     = 0;
	$GLOBALS['attach_fails']    = false;

	$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array_merge(
		WPCPM_Settings::defaults(),
		array(
			'api_token' => 'patTESTTOKEN',
			'base_id'   => 'appIzQKfwTn5dyPVp',
		)
	);

	$GLOBALS['countries'] = array();
	foreach ( $seed['countries'] as $c ) {
		$GLOBALS['countries'][ $c['id'] ] = array( 'name' => $c['name'], 'manager' => '', 'email' => '', 'calendly' => '' );
	}

	$GLOBALS['pages'] = array( 'tbl4V0FEbzRP7I2w2' => airtable_pages( $seed, $override ) );
}

/**
 * An institution account holding the Institution role and a live membership.
 *
 * @param int    $id     User ID.
 * @param string $record Institution record ID.
 */
function member( $id, $record ) {
	$GLOBALS['users'][ $id ]   = array( 'roles' => array( WPCPM_Roles::ROLE_INSTITUTION ) );
	$GLOBALS['members'][ $id ] = $record;
}

$seed = seed();
$SEEDED = count( $seed['institutions'] );
ck( 'the fixture loaded', array( $SEEDED > 0, $SEEDED === (int) $seed['counts']['institutions'] ), array( true, true ) );

/* ---- one full run --------------------------------------------------------- */

echo "=== One full run ===\n";

reset_site( $seed );

$not_moving = 'rec1uKToSodYueZHm'; // Govt. Mohammadpur Model School and College, Not Moving Forward.
$confirmed  = 'rec1ZgEtczDKjRNP4'; // Università di Pisa, Confirmed.
$gone       = 'recGONE0000000001'; // Not in the base.
$spam       = 'recnd05BHZjmP3HFR'; // SPAM.

member( 40, $not_moving );
member( 41, $confirmed );
member( 42, $gone );
member( 43, $spam );
// An account with the role but no live membership (detached earlier): institution_of() is ''.
$GLOBALS['users'][44] = array( 'roles' => array( WPCPM_Roles::ROLE_INSTITUTION ) );

// Agreement options standing from a previous run, for the three the revoke phase must close
// and the one it must not.
foreach ( array( $not_moving, $confirmed, $gone, $spam ) as $record ) {
	$GLOBALS['opts'][ WPCPM_Institution_Agreement::option_name( $record ) ] = array( 'v' => 1, 'settled' => true );
}

ck( 'start() accepts a connected site', WPCPM_Institutions_Sync::start(), true );
ck( 'a tick is queued as the safety net', isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_TICK ] ), true );
ck( 'the run begins with the countries', WPCPM_Institutions_Sync::progress()['phase'], 'countries' );

$ticks = run_to_end();

ck( 'the run finished', WPCPM_Institutions_Sync::is_running(), false );
ck( 'in a handful of ticks', array( $ticks > 0 && $ticks < 50 ), array( true ) );

// Countries: once, first, with the run's own client.
ck( 'the countries map was refreshed once', count( $GLOBALS['calls']['countries_refresh'] ), 1 );
ck( 'with the run\'s Airtable client', $GLOBALS['calls']['countries_refresh'][0], true );

// The read: the Institutions table, an explicit column list, no prose.
$reads = array_filter( $GLOBALS['calls']['fetch_page'], static function ( $call ) { return 'tbl4V0FEbzRP7I2w2' === $call['table']; } );
$reads = array_values( $reads );
ck( 'the Institutions table was paged twice', count( $reads ), 2 );
ck( 'each page asked for the seventeen columns and only those',
	$reads[0]['args']['fields'],
	array( 'Name', 'Current Stage', 'Country', 'City', 'Website', 'Contact Person', 'Contact Email', 'Confirmed on', 'Privacy Policy Compliance', 'Agreement Status', 'Agreement Kind', 'Agreement Accepted On', 'Agreement Signed On', 'Agreement Accepted By', 'Agreement Document', 'Agreement Submitted On', 'Agreement Template Version' ) );
ck( 'the second page carried the offset', $reads[1]['args']['offset'], 'page2' );

$prose = array( 'Why are you interested in offering WordPress Credits to your students?', 'Comments', 'Notes', 'Department', 'Anything else you’d like us to know?', 'Feedback', 'Students', 'Mentors', 'Tutors' );
ck( 'no prose or link column was requested', array_values( array_intersect( $reads[0]['args']['fields'], $prose ) ), array() );

// The index.
$rows = WPCPM_Institutions_Index::rows();
ck( 'the index holds every record', count( $rows ), $SEEDED );
ck( 'read at the run\'s start time', array( WPCPM_Institutions_Index::read()['read'] > 0 ), array( true ) );

$counts = WPCPM_Institutions_Index::stage_counts();
$want   = $seed['counts']['by_stage'];
ksort( $counts, SORT_STRING );
ksort( $want, SORT_STRING );
ck( 'with the fixture\'s stage counts', $counts, $want );

$pisa = $rows[ $confirmed ];
ck( 'a row is built from the cells', array( $pisa['name'], $pisa['stage'], $pisa['city'], $pisa['confirmed_on'], $pisa['created'], $pisa['consent'] ), array( 'Università di Pisa', 'Confirmed', 'Pisa', '2025-06-26', '2025-07-17', false ) );
ck( 'the country resolves to a name through the map', array( $pisa['country'], $pisa['country_name'] ), array( 'recQcCJMA9jvWJnTB', 'Italy' ) );
ck( 'the contact columns are empty because the fixture has none', array( $pisa['contact_person'], $pisa['contact_email'] ), array( '', '' ) );

$trailing = 0;
$nameless = 0;
foreach ( $rows as $row ) {
	if ( '' === $row['name'] ) {
		++$nameless;
	} elseif ( rtrim( $row['name'] ) !== $row['name'] ) {
		++$trailing;
	}
}
ck( 'names keep their trailing spaces', $trailing, $seed['counts']['trailing_space_names'] );
ck( 'the nameless records are kept', $nameless, $seed['counts']['nameless'] );

$drive = 0;
foreach ( $rows as $row ) {
	if ( isset( $row['drive_url'] ) || isset( $row['document'] ) || isset( $row['agreement']['document'] ) ) {
		++$drive;
	}
}
ck( 'no row carries a Drive link', $drive, 0 );

// The gate: one rebuild per record, with the Drive link the index does not hold.
ck( 'rebuild() was called once per record', count( $GLOBALS['calls']['rebuild'] ), $SEEDED );
$rebuilt = array_keys( $GLOBALS['calls']['rebuild'] );
$all     = array_keys( $rows );
sort( $rebuilt );
sort( $all );
ck( 'for exactly the records in the index', $rebuilt, $all );
ck( 'each with the eight agreement values',
	array_keys( $GLOBALS['calls']['rebuild'][ $confirmed ] ),
	array( 'status', 'kind', 'accepted_on', 'signed_on', 'accepted_by', 'document', 'submitted_on', 'template_version' ) );

// Revoke: the member of a terminal-stage institution, of a SPAM one, and of one the base
// no longer has; not the member of a Confirmed one; not the account with no membership.
$detached = array();
foreach ( $GLOBALS['calls']['detach'] as $call ) {
	$detached[] = $call[0];
}
sort( $detached );
ck( 'detach() ran for the Not Moving Forward, SPAM and vanished institutions\' members', $detached, array( 40, 42, 43 ) );
ck( 'with reason revoked and the system as actor', $GLOBALS['calls']['detach'][0][1] . ':' . $GLOBALS['calls']['detach'][0][2], 'revoked:0' );
ck( 'the Confirmed institution\'s member was left alone', isset( $GLOBALS['members'][41] ), true );

ck( 'the departed institutions\' agreement options were deleted',
	array(
		array_key_exists( WPCPM_Institution_Agreement::option_name( $not_moving ), $GLOBALS['opts'] ),
		array_key_exists( WPCPM_Institution_Agreement::option_name( $gone ), $GLOBALS['opts'] ),
		array_key_exists( WPCPM_Institution_Agreement::option_name( $spam ), $GLOBALS['opts'] ),
	),
	array( false, false, false ) );
ck( 'and the Confirmed one\'s was rebuilt, not deleted', array_key_exists( WPCPM_Institution_Agreement::option_name( $confirmed ), $GLOBALS['opts'] ), true );

// The report.
$report = get_option( WPCPM_Institutions_Sync::OPT_REPORT );
// `locked` counts institutions whose gate was closed, not members: every institution that
// has left the active stages loses its agreement option whether or not anybody holds an
// account for it. Counted from the fixture and the settings rather than written down, so a
// refreshed seed moves the expected number with the data.
$active_stages = WPCPM_Settings::defaults()['institution_active_stages'];
$outside       = 0;
foreach ( $seed['institutions'] as $row ) {
	if ( ! in_array( $row['stage'], $active_stages, true ) ) {
		++$outside;
	}
}
ck( 'the report counts the run', array( $report['stats']['records_seen'], $report['stats']['rebuilt'], $report['stats']['countries'], $report['stats']['revoked'], $report['stats']['nameless'], $report['stats']['provisioned'] ), array( $SEEDED, $SEEDED, 196, 3, (int) $seed['counts']['nameless'], 0 ) );
// Provisioning is off by default, and off means the phase does not even look: the first run
// of a sync that mails people is a decision for a human, as it is for the welcome email.
ck( 'and with provisioning off nothing was created or even considered', array( isset( $GLOBALS['calls']['wp_insert_user'] ), $report['stats']['provision_skipped'], $report['stats']['conflicts'] ), array( false, 0, 0 ) );
// `locked` counts institutions whose gate was closed: every seeded row outside the active
// stages, plus the one this scenario removes from the base altogether.
ck( 'and locks every institution that has left the active stages', $report['stats']['locked'], $outside + 1 );
ck( 'a nameless record would be named in the notices', count( $report['notices'] ), (int) $seed['counts']['nameless'] );
ck( 'the last-run time is stamped', array( WPCPM_Institutions_Sync::last_read() > 0 ), array( true ) );
ck( 'the state and lock are gone', array( get_option( WPCPM_Institutions_Sync::OPT_STATE ), get_option( WPCPM_Institutions_Sync::OPT_LOCK ) ), array( false, false ) );
ck( 'the tick event is cleared', isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_TICK ] ), false );
ck( 'every option was written with autoload off',
	array( $GLOBALS['autoload'][ WPCPM_Institutions_Sync::OPT_REPORT ], $GLOBALS['autoload'][ WPCPM_Institutions_Sync::OPT_LAST ], $GLOBALS['autoload'][ WPCPM_Institutions_Index::OPT_NAME ] ),
	array( false, false, false ) );

/* ---- a nameless record ---------------------------------------------------- */

echo "\n=== A record with no name ===\n";

// Proven with a record the suite empties rather than one the base happens to hold: the two that
// were nameless on 2 September were deleted by a program manager the same day, and this
// behaviour has to keep working the next time somebody adds a blank row.
reset_site( $seed, array( $confirmed => array( 'Name' => '' ) ) );

WPCPM_Institutions_Sync::start();
run_to_end();

$rows = WPCPM_Institutions_Index::rows();
ck( 'a nameless record is kept, under its own ID', array( isset( $rows[ $confirmed ] ), $rows[ $confirmed ]['name'] ), array( true, '' ) );
ck( 'and the index is not one row short', count( $rows ), $SEEDED );
$notices = get_option( WPCPM_Institutions_Sync::OPT_REPORT )['notices'];
ck( 'the run names it so a manager can find it in the grid', array( 1, false !== strpos( implode( "\n", $notices ), $confirmed ) ), array( count( $notices ), true ) );
ck( 'and counts it', get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['nameless'], 1 );

/* ---- provisioning --------------------------------------------------------- */

echo "\n=== Provisioning: one institution, one account ===\n";

/**
 * The cells that make an institution ready for an account: a contact, an address, and an
 * agreement Airtable calls On file with a Drive link behind it.
 *
 * @param string $email  Contact address.
 * @param string $person Contact person.
 * @return array
 */
function ready_cells( $email, $person = 'A Rector' ) {
	return array(
		'Contact Email'      => $email,
		'Contact Person'     => $person,
		'Agreement Status'   => 'On file',
		'Agreement Kind'     => 'Legacy',
		'Agreement Document' => 'https://drive.google.com/drive/folders/pisa',
	);
}

$CONFIRMED_ROWS = (int) $seed['counts']['by_stage']['Confirmed'];

reset_site( $seed, array( $confirmed => ready_cells( 'Rector@Example.EDU' ) ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;

WPCPM_Institutions_Sync::start();
run_to_end();

$rows   = WPCPM_Institutions_Index::rows();
$report = get_option( WPCPM_Institutions_Sync::OPT_REPORT );

ck( 'the contact email is lowercased', $rows[ $confirmed ]['contact_email'], 'rector@example.edu' );
ck( 'the agreement columns reach the index, the link only as a flag', $rows[ $confirmed ]['agreement'], array( 'status' => 'On file', 'kind' => 'Legacy', 'accepted_on' => '', 'signed_on' => '', 'accepted_by' => '', 'submitted_on' => '', 'template_version' => '', 'has_document' => true ) );
ck( 'and the link itself went to rebuild()', $GLOBALS['calls']['rebuild'][ $confirmed ]['document'], 'https://drive.google.com/drive/folders/pisa' );

ck( 'exactly one account was created', count( $GLOBALS['calls']['wp_insert_user'] ), 1 );
$made = $GLOBALS['calls']['wp_insert_user'][0];
ck( 'with the Institution role and the contact address', array( $made['role'], $made['user_email'] ), array( WPCPM_Roles::ROLE_INSTITUTION, 'rector@example.edu' ) );
ck( 'named for the contact person, not the school', array( $made['display_name'], $made['nickname'] ), array( 'A Rector', 'A Rector' ) );
ck( 'on a free login from the address, with a password nobody knows', array( $made['user_login'], strlen( $made['user_pass'] ) ), array( 'rector', 24 ) );
ck( 'the stamp names this institution, provisioned, by the sync', $GLOBALS['calls']['attach'], array( array( 101, $confirmed, 'provisioned', 0 ) ) );
ck( 'and it holds the role and the membership', array( in_array( WPCPM_Roles::ROLE_INSTITUTION, $GLOBALS['users'][101]['roles'], true ), WPCPM_Institution_Members::institution_of( new WP_User( 101 ) ) ), array( true, $confirmed ) );
ck( 'one invitation was queued, for that account alone', $GLOBALS['calls']['queue_invites'], array( array( 101 ) ) );
ck( 'the run counts it, with the other Confirmed rows skipped for want of an address',
	array( $report['stats']['provisioned'], $report['stats']['provision_skipped'], $report['stats']['conflicts'], $report['stats']['provision_failed'] ),
	array( 1, $CONFIRMED_ROWS - 1, 0, 0 ) );
ck( 'and nothing was named in the notices, because nothing needs a person', $report['notices'], array() );

// The rule that matters most, from the other side: run it again and the institution now has a
// member, so it is skipped rather than given a second account.
WPCPM_Institutions_Sync::start();
run_to_end();
ck( 'a second run creates nothing for an institution that now has a member',
	array( count( $GLOBALS['calls']['wp_insert_user'] ), get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['provisioned'] ),
	array( 1, 0 ) );

echo "\n=== An address that already has an account is a conflict ===\n";

reset_site( $seed, array( $confirmed => ready_cells( 'rector@example.edu' ) ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;
// Somebody who registered here for something else, in the case the base does not use.
$GLOBALS['users'][9] = array( 'roles' => array( 'subscriber' ), 'email' => 'Rector@Example.EDU', 'login' => 'rector' );

WPCPM_Institutions_Sync::start();
run_to_end();

$report = get_option( WPCPM_Institutions_Sync::OPT_REPORT );
ck( 'no account was created and none was adopted', array( isset( $GLOBALS['calls']['wp_insert_user'] ), isset( $GLOBALS['calls']['attach'] ), isset( $GLOBALS['members'][9] ) ), array( false, false, false ) );
ck( 'the conflict is counted rather than skipped in silence', array( $report['stats']['conflicts'], $report['stats']['provisioned'] ), array( 1, 0 ) );
ck( 'and named, with the institution and what a conflict is', array(
	false !== strpos( implode( "\n", $report['notices'] ), 'Università di Pisa' ),
	false !== strpos( implode( "\n", $report['notices'] ), 'conflict, not a match' ),
), array( true, true ) );

echo "\n=== The history rule, and the gate ===\n";

reset_site( $seed, array( $confirmed => ready_cells( 'rector@example.edu' ) ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;
$GLOBALS['former'][77] = $confirmed;
WPCPM_Institutions_Sync::start();
run_to_end();
ck( 'an institution with a former member is skipped, not provisioned again',
	array( isset( $GLOBALS['calls']['wp_insert_user'] ), get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['provisioned'], get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['provision_skipped'] ),
	array( false, 0, $CONFIRMED_ROWS ) );

reset_site( $seed, array( $confirmed => array( 'Contact Email' => 'rector@example.edu', 'Contact Person' => 'A Rector' ) ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;
WPCPM_Institutions_Sync::start();
run_to_end();
ck( 'nor is one whose agreement is not recorded', array( isset( $GLOBALS['calls']['wp_insert_user'] ), get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['provisioned'] ), array( false, 0 ) );

// The block reasons the manager screen reads, from the same one copy of the rule.
ck( 'provision_block() names why, in the order it decides', array(
	WPCPM_Institutions_Sync::provision_block( 'recNOTINDEXED0001' ),
	WPCPM_Institutions_Sync::provision_block( 'not a record id' ),
	WPCPM_Institutions_Sync::provision_block( $not_moving ),
	WPCPM_Institutions_Sync::provision_block( $confirmed ),
), array( 'not_indexed', 'not_indexed', 'not_confirmed', 'no_agreement' ) );
ck( 'and the no-agreement wording names the Drive link, which is what a manager has to find', array(
	false !== strpos( WPCPM_Institutions_Sync::provision_message( WPCPM_Institutions_Sync::BLOCK_NO_AGREEMENT ), 'Drive link' ),
	'' === WPCPM_Institutions_Sync::provision_message( 'something else' ),
), array( true, true ) );
ck( 'provision() refuses the same way, with the reason in the error data', array(
	is_wp_error( WPCPM_Institutions_Sync::provision( $confirmed ) ),
	WPCPM_Institutions_Sync::provision( $confirmed )->get_error_code(),
	WPCPM_Institutions_Sync::provision( $confirmed )->get_error_data()['reason'],
	isset( $GLOBALS['calls']['wp_insert_user'] ),
), array( true, WPCPM_Institutions_Sync::PROVISION_ERROR, 'no_agreement', false ) );

echo "\n=== An agreement revoked from under an account that already exists ===\n";

// Design spec 7.4's T8: the option deleted, the stage left at Confirmed, and the account left
// standing, because a revocation locks a member out rather than removing them. The row that
// produces is the one this block pins. Read in the wrong order it is an institution "waiting
// for its first agreement", which is three wrong things at once: the worklist tells a manager
// to record an agreement for a partner that already has an account, the bulk button is shut
// for every other institution while that row is there, and the row is missing from the count
// of the ones that already have an account.
$second = '';
foreach ( $seed['institutions'] as $institution ) {
	if ( 'Confirmed' === $institution['stage'] && $institution['id'] !== $confirmed ) {
		$second = $institution['id'];
		break;
	}
}

reset_site(
	$seed,
	array(
		$confirmed => ready_cells( 'rector@example.edu' ),
		// A second Confirmed institution with an address and no agreement: the one the gate
		// exists for, so the assertions below can tell the two rows apart.
		$second    => array( 'Contact Email' => 'dean@example.edu', 'Contact Person' => 'A Dean' ),
	)
);
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;
WPCPM_Institutions_Sync::start();
run_to_end();

ck( 'the settled institution got its one account', array( count( $GLOBALS['calls']['wp_insert_user'] ), WPCPM_Institution_Members::institution_of( new WP_User( 101 ) ) ), array( 1, $confirmed ) );

// The revoke, on the site's side: the gate's option deleted and nothing else touched.
delete_option( WPCPM_Institution_Agreement::option_name( $confirmed ) );

ck( 'a revoked institution is one that already has an account, whatever its agreement says now',
	WPCPM_Institutions_Sync::provision_block( $confirmed ), WPCPM_Institutions_Sync::BLOCK_HAS_MEMBER );
ck( 'and the sentence beside it says so rather than asking for the agreement again',
	WPCPM_Institutions_Sync::provision_message( WPCPM_Institutions_Sync::provision_block( $confirmed ) ),
	WPCPM_Institutions_Sync::provision_message( WPCPM_Institutions_Sync::BLOCK_HAS_MEMBER ) );

// The manager card's two lists, built the way it builds them: the count of the institutions
// that already have an account, and the list whose emptiness decides whether the bulk button
// is offered to anybody at all.
$reasons = array();
foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
	if ( 'Confirmed' === trim( (string) $row['stage'] ) ) {
		$reasons[ $record_id ] = WPCPM_Institutions_Sync::provision_block( $record_id );
	}
}

ck( 'so the revoked one is counted among the institutions that already have an account',
	array_keys( $reasons, WPCPM_Institutions_Sync::BLOCK_HAS_MEMBER, true ), array( $confirmed ) );
ck( 'and the only institution holding the bulk button shut is the one that really has no agreement',
	array_keys( $reasons, WPCPM_Institutions_Sync::BLOCK_NO_AGREEMENT, true ), array( $second ) );

// One detach later the account is gone from the membership and is history instead, which the
// sync must not undo either: the same question, one reason further down the list.
WPCPM_Institution_Members::detach( 101, 'revoked', 0 );
$GLOBALS['former'][101] = $confirmed; // The stand-in detach() drops the stamp; the real one moves it to `_was`.

// A former member does not excuse a missing agreement. Only a LIVE account is "already has an
// account"; once that account is gone, an institution with no recorded agreement belongs back on
// the list of what a manager still has to do, or a school whose contact once left would slip
// out of the one list that names it. The former member is reported only once the agreement is.
ck( 'and once that member is removed it is an institution with no recorded agreement first, its former member notwithstanding',
	WPCPM_Institutions_Sync::provision_block( $confirmed ), WPCPM_Institutions_Sync::BLOCK_NO_AGREEMENT );

// Airtable's own half of T8, so the next run rebuilds the gate as Revoked rather than as the
// `On file` the fixture holds: neither institution may be given an account by it.
$GLOBALS['pages']['tbl4V0FEbzRP7I2w2'] = airtable_pages(
	$seed,
	array(
		$confirmed => array_merge( ready_cells( 'rector@example.edu' ), array( 'Agreement Status' => 'Revoked' ) ),
		$second    => array( 'Contact Email' => 'dean@example.edu', 'Contact Person' => 'A Dean' ),
	)
);

WPCPM_Institutions_Sync::start();
run_to_end();

ck( 'and the nightly run creates no second account for either of them',
	array( count( $GLOBALS['calls']['wp_insert_user'] ), get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['provisioned'] ),
	array( 1, 0 ) );

echo "\n=== A run that dies part way through provisioning ===\n";

// Six institutions ready at once, so the phase has to take more than one batch.
$confirmed_ids = array();
foreach ( $seed['institutions'] as $institution ) {
	if ( 'Confirmed' === $institution['stage'] ) {
		$confirmed_ids[] = $institution['id'];
	}
}
$six      = array_slice( $confirmed_ids, 0, 6 );
$override = array();
foreach ( $six as $n => $id ) {
	$override[ $id ] = ready_cells( 'contact' . $n . '@example.edu', 'Contact ' . $n );
}

reset_site( $seed, $override );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;
$GLOBALS['insert_dies'] = WPCPM_Institutions_Sync::PROVISION_BATCH + 1;

WPCPM_Institutions_Sync::start();
$died = '';
try {
	run_to_end();
} catch ( RuntimeException $e ) {
	$died = $e->getMessage();
}

$state = get_option( WPCPM_Institutions_Sync::OPT_STATE );
ck( 'the request never came back', $died, 'the request died' );
ck( 'the accounts made before it stand', count( $GLOBALS['calls']['attach'] ), WPCPM_Institutions_Sync::PROVISION_BATCH );
ck( 'the run is still in the provision phase, with the rest of the candidates pending',
	array( $state['phase'], count( $state['provision'] ), $state['stats']['provisioned'] ),
	array( 'provision', $CONFIRMED_ROWS - WPCPM_Institutions_Sync::PROVISION_BATCH, WPCPM_Institutions_Sync::PROVISION_BATCH ) );
ck( 'and the lock it died holding is still held', array( get_option( WPCPM_Institutions_Sync::OPT_LOCK ) > 0 ), array( true ) );

// The next tick, once the lock has gone stale, carries on from the batch it had reached.
$GLOBALS['insert_dies'] = 0;
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_LOCK ] = time() - WPCPM_Institutions_Sync::LOCK_TIMEOUT - 1;
run_to_end();

$report = get_option( WPCPM_Institutions_Sync::OPT_REPORT );
ck( 'the run finished', WPCPM_Institutions_Sync::is_running(), false );
ck( 'the six ready institutions have six accounts between them, not eleven',
	array( $report['stats']['provisioned'], count( $GLOBALS['calls']['attach'] ), count( array_unique( array_map( static function ( $call ) { return $call[1]; }, $GLOBALS['calls']['attach'] ) ) ) ),
	array( 6, 6, 6 ) );
ck( 'each was invited once', count( $GLOBALS['calls']['queue_invites'] ), 6 );
ck( 'and the ones with no address were skipped', $report['stats']['provision_skipped'], $CONFIRMED_ROWS - 6 );

echo "\n=== An account that cannot be created, and one that cannot be stamped ===\n";

reset_site( $seed, array( $confirmed => ready_cells( 'rector@example.edu' ) ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;
$GLOBALS['insert_fails'] = 1;
WPCPM_Institutions_Sync::start();
run_to_end();
$report = get_option( WPCPM_Institutions_Sync::OPT_REPORT );
ck( 'a refused insert is counted and named, and nothing is stamped',
	array( $report['stats']['provision_failed'], $report['stats']['provisioned'], isset( $GLOBALS['calls']['attach'] ), false !== strpos( implode( "\n", $report['notices'] ), 'already exists' ) ),
	array( 1, 0, false, true ) );

reset_site( $seed, array( $confirmed => ready_cells( 'rector@example.edu' ) ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_provision'] = true;
$GLOBALS['attach_fails'] = true;
WPCPM_Institutions_Sync::start();
run_to_end();
$report = get_option( WPCPM_Institutions_Sync::OPT_REPORT );
// The account is left standing rather than deleted, and it is not invited: an invitation to an
// account that acts for nobody is worse than none. The next run finds the address taken and
// says so, which is a person's problem to look at rather than a loop.
ck( 'a refused stamp leaves the account, sends nothing, and is named',
	array( $report['stats']['provision_failed'], isset( $GLOBALS['calls']['queue_invites'] ), isset( $GLOBALS['users'][101] ), false !== strpos( implode( "\n", $report['notices'] ), 'pipeline index' ) ),
	array( 1, false, true, true ) );

$GLOBALS['attach_fails'] = false;
WPCPM_Institutions_Sync::start();
run_to_end();
ck( 'and the next run names it as a conflict rather than trying again', get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['conflicts'], 1 );

/* ---- keep, not revoke ----------------------------------------------------- */

echo "\n=== institution_on_inactive = keep ===\n";

reset_site( $seed );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['institution_on_inactive'] = 'keep';
member( 40, $not_moving );
$GLOBALS['opts'][ WPCPM_Institution_Agreement::option_name( $not_moving ) ] = array( 'v' => 1, 'settled' => true );

WPCPM_Institutions_Sync::start();
run_to_end();

ck( 'the membership is kept', array( isset( $GLOBALS['calls']['detach'] ), isset( $GLOBALS['members'][40] ) ), array( false, true ) );
ck( 'but the gate still closes', array_key_exists( WPCPM_Institution_Agreement::option_name( $not_moving ), $GLOBALS['opts'] ), false );
ck( 'and the report says so', array( get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['locked'], get_option( WPCPM_Institutions_Sync::OPT_REPORT )['stats']['revoked'] ), array( $outside, 0 ) );

/* ---- an error mid-records ------------------------------------------------- */

echo "\n=== A page fails ===\n";

reset_site( $seed );
$GLOBALS['fail_page'] = 2;
member( 40, $not_moving );

// Last night's index: one row, an old read time.
WPCPM_Institutions_Index::write( array( 'recLASTNIGHT00001' => array( 'record_id' => 'recLASTNIGHT00001', 'name' => 'Last night', 'stage' => 'Confirmed' ) ), 1000 );

WPCPM_Institutions_Sync::start();
run_to_end();

ck( 'the run stopped', array( WPCPM_Institutions_Sync::is_running(), WPCPM_Institutions_Sync::progress()['phase'] ), array( true, 'records' ) );
ck( 'with the error recorded', WPCPM_Institutions_Sync::progress()['error'], 'Airtable said 503 on page 2' );
ck( 'the previous index is untouched', array( count( WPCPM_Institutions_Index::rows() ), WPCPM_Institutions_Index::read()['read'] ), array( 1, 1000 ) );
ck( 'no gate was rebuilt', isset( $GLOBALS['calls']['rebuild'] ), false );
ck( 'and nobody was detached', isset( $GLOBALS['calls']['detach'] ), false );
ck( 'the countries were still refreshed, because they come first', count( $GLOBALS['calls']['countries_refresh'] ), 1 );

// Every poll retried the failed page and nothing else: the first page was not read again.
$retries = array_filter( $GLOBALS['calls']['fetch_page'], static function ( $call ) { return 'page2' === $call['args']['offset']; } );
ck( 'every tick retried the failed page', array( count( $retries ) > 1, count( $retries ) + 1 === count( $GLOBALS['calls']['fetch_page'] ) ), array( true, true ) );

// Once the error clears, a tick resumes from the failed page rather than from the top.
$GLOBALS['fail_page']           = 0;
$GLOBALS['calls']['fetch_page'] = array();
run_to_end();
ck( 'the next tick resumed from page two, not page one',
	array_map( static function ( $call ) { return $call['args']['offset']; }, $GLOBALS['calls']['fetch_page'] ),
	array( 'page2' ) );
ck( 'and the run finished', array( WPCPM_Institutions_Sync::is_running(), count( WPCPM_Institutions_Index::rows() ) ), array( false, $SEEDED ) );

reset_site( $seed );
$GLOBALS['countries_error'] = true;
WPCPM_Institutions_Sync::start();
run_to_end();
ck( 'a failed countries read stops the run before the table is touched', array( WPCPM_Institutions_Sync::is_running(), isset( $GLOBALS['calls']['fetch_page'] ), WPCPM_Institutions_Sync::progress()['error'] ), array( true, false, 'Countries unreadable' ) );

// A read that comes back with records carrying no fields is Airtable's answer to an
// unknown field name, not an empty table.
reset_site( $seed );
$blank = array();
foreach ( $GLOBALS['pages']['tbl4V0FEbzRP7I2w2'] as $page ) {
	$stripped = array();
	foreach ( $page as $record ) {
		$stripped[] = array( 'id' => $record['id'], 'createdTime' => $record['createdTime'], 'fields' => array() );
	}
	$blank[] = $stripped;
}
$GLOBALS['pages']['tbl4V0FEbzRP7I2w2'] = $blank;
WPCPM_Institutions_Index::write( array( 'recLASTNIGHT00001' => array( 'record_id' => 'recLASTNIGHT00001', 'name' => 'Last night', 'stage' => 'Confirmed' ) ), 1000 );
WPCPM_Institutions_Sync::start();
run_to_end();
ck( 'records with no fields at all do not replace the index', array( count( WPCPM_Institutions_Index::rows() ), WPCPM_Institutions_Sync::is_running() ), array( 1, true ) );
ck( 'and the error names the settings to check', array( false !== strpos( WPCPM_Institutions_Sync::progress()['error'], 'name field' ) ), array( true ) );

/* ---- cancel, lock, start ------------------------------------------------- */

echo "\n=== cancel(), the lock, start() ===\n";

reset_site( $seed );
WPCPM_Institutions_Sync::start();
WPCPM_Institutions_Sync::cancel();
ck( 'cancel() clears the state, the lock and the tick', array( get_option( WPCPM_Institutions_Sync::OPT_STATE ), get_option( WPCPM_Institutions_Sync::OPT_LOCK ), isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_TICK ] ) ), array( false, false, false ) );
ck( 'and the run is no longer running', WPCPM_Institutions_Sync::is_running(), false );

reset_site( $seed );
WPCPM_Institutions_Sync::start();
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_LOCK ] = time();
WPCPM_Institutions_Sync::tick();
ck( 'a fresh lock held elsewhere makes a tick do nothing', array( WPCPM_Institutions_Sync::progress()['phase'], isset( $GLOBALS['calls']['countries_refresh'] ) ), array( 'countries', false ) );

$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_LOCK ] = time() - WPCPM_Institutions_Sync::LOCK_TIMEOUT - 1;
WPCPM_Institutions_Sync::tick( 1 );
ck( 'a stale lock is taken over', array( isset( $GLOBALS['calls']['countries_refresh'] ) ), array( true ) );

reset_site( $seed );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] = '';
$result = WPCPM_Institutions_Sync::start();
ck( 'start() refuses an unconnected site', array( is_wp_error( $result ), WPCPM_Institutions_Sync::is_running() ), array( true, false ) );
ck( 'and records why', array( '' !== get_option( WPCPM_Institutions_Sync::OPT_ERROR ) ), array( true ) );

/* ---- the progress payload ------------------------------------------------- */

echo "\n=== The progress payload ===\n";

// With stubbed I/O a one-second budget finishes the whole run, so the mid-run payload is
// read by placing the state where a tick would leave it: countries done, records begun.
reset_site( $seed );
WPCPM_Institutions_Sync::start();
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['phase'] = 'records';
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['steps'] = array( 'countries' => 1 );
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['stats']['records_seen'] = 100;

$progress = WPCPM_Institutions_Sync::progress();
$keys     = array_keys( $progress );
sort( $keys );
ck( 'the payload has every key the students sync has', $keys, array( 'detail', 'elapsed', 'error', 'idle', 'label', 'percent', 'phase', 'running', 'stalled', 'stats', 'step', 'step_label', 'step_total' ) );
ck( 'the keys admin.js reads are typed as it expects',
	array( is_bool( $progress['running'] ), is_string( $progress['label'] ), is_string( $progress['step_label'] ), is_int( $progress['percent'] ), is_string( $progress['detail'] ), is_int( $progress['elapsed'] ), is_bool( $progress['stalled'] ) ),
	array( true, true, true, true, true, true, true ) );
ck( 'running, on step two of four', array( $progress['running'], $progress['step'], $progress['step_total'], $progress['step_label'], $progress['phase'] ), array( true, 2, 4, 'Step 2 of 4', 'records' ) );
ck( 'the label names the phase and the detail counts it', array( $progress['label'], $progress['detail'] ), array( 'Reading the Institutions table…', '100 institutions read · 0 agreement records rebuilt' ) );
ck( 'the percent is the countries\' weight once they are done', $progress['percent'], 15 );

$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['steps'] = array( 'countries' => 1, 'records' => 3 );
ck( 'and climbs through the records phase by its slices', WPCPM_Institutions_Sync::progress()['percent'], 34 );
ck( 'the phase weights are the design\'s', array_map( static function ( $p ) { return $p['weight']; }, WPCPM_Institutions_Sync::phases() ), array( 'countries' => 15, 'records' => 45, 'provision' => 25, 'revoke' => 15 ) );

// The provision phase says what it is doing rather than what it would do, and counts both
// halves a manager cares about: the accounts made, and the addresses that stopped one.
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['phase']                 = 'provision';
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['stats']['provisioned']  = 3;
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['stats']['conflicts']    = 2;
ck( 'the provision phase names the work and counts it', array( WPCPM_Institutions_Sync::progress()['label'], WPCPM_Institutions_Sync::progress()['detail'] ), array( 'Creating the institution accounts…', '3 accounts created · 2 addresses already taken' ) );
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['phase'] = 'records';

ck( 'a run whose ticks keep coming is not stalled', WPCPM_Institutions_Sync::progress()['stalled'], false );
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['touched'] = time() - WPCPM_Institutions_Sync::LOCK_TIMEOUT - 5;
ck( 'a run whose ticks stopped reads as stalled', WPCPM_Institutions_Sync::progress()['stalled'], true );

run_to_end();
ck( 'a finished run reports 100 and not running', array( WPCPM_Institutions_Sync::progress()['percent'], WPCPM_Institutions_Sync::progress()['running'] ), array( 100, false ) );

/* ---- the schedule --------------------------------------------------------- */

echo "\n=== The schedule ===\n";

reset_site( $seed );
$GLOBALS['cron'][ WPCPM_Students_Sync::CRON_AUTO ] = 2000000000;
WPCPM_Institutions_Sync::schedule();
// Four hours, not six: the students sync recurs every three, so six would land this run on
// one of its slots and WP-Cron would fire both hooks in the same request.
ck( 'the daily run sits four hours after the students sync', $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ], 2000000000 + 4 * HOUR_IN_SECONDS );
ck( 'which is not a slot the students sync itself uses', 0 !== ( ( 4 * HOUR_IN_SECONDS ) % ( 3 * HOUR_IN_SECONDS ) ), true );
ck( 'on the daily recurrence', $GLOBALS['cron_recurrence'][ WPCPM_Institutions_Sync::CRON_DAILY ], 'daily' );

$GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ] = 1234;
WPCPM_Institutions_Sync::schedule();
ck( 'an existing event is left alone', $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ], 1234 );

reset_site( $seed );
$before = time();
WPCPM_Institutions_Sync::schedule();
ck( 'with no students sync scheduled, four hours from now', array( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ] >= $before + 4 * HOUR_IN_SECONDS ), array( true ) );

WPCPM_Institutions_Sync::start();
WPCPM_Institutions_Sync::unschedule();
ck( 'unschedule() drops both events', array( isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ] ), isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_TICK ] ) ), array( false, false ) );

reset_site( $seed );
WPCPM_Institutions_Sync::register_cron();
ck( 'register_cron() listens on both hooks and schedules', array( $GLOBALS['calls']['add_action'], isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ] ) ), array( array( 'wpcpm_institutions_sync_daily', 'wpcpm_institutions_sync_tick' ), true ) );

// The daily entry point: off when auto_sync is off, and never on top of a live run.
reset_site( $seed );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['auto_sync'] = false;
WPCPM_Institutions_Sync::cron_daily();
ck( 'cron_daily() does nothing with auto_sync off', WPCPM_Institutions_Sync::is_running(), false );

reset_site( $seed );
WPCPM_Institutions_Sync::start();
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['phase']   = 'records';
$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['started'] = 1500000000;
WPCPM_Institutions_Sync::cron_daily();
ck( 'cron_daily() leaves a live run alone', array( get_option( WPCPM_Institutions_Sync::OPT_STATE )['phase'], get_option( WPCPM_Institutions_Sync::OPT_STATE )['started'] ), array( 'records', 1500000000 ) );

$GLOBALS['opts'][ WPCPM_Institutions_Sync::OPT_STATE ]['touched'] = time() - WPCPM_Institutions_Sync::LOCK_TIMEOUT - 5;
WPCPM_Institutions_Sync::cron_daily();
ck( 'but restarts a stalled one from the top', array( get_option( WPCPM_Institutions_Sync::OPT_STATE )['phase'], get_option( WPCPM_Institutions_Sync::OPT_STATE )['started'] > 1500000000 ), array( 'countries', true ) );

reset_site( $seed );
WPCPM_Institutions_Sync::activate();
ck( 'activate() schedules and refreshes the countries', array( isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ] ), count( $GLOBALS['calls']['countries_refresh'] ) ), array( true, 1 ) );
WPCPM_Institutions_Sync::deactivate();
ck( 'deactivate() unschedules', isset( $GLOBALS['cron'][ WPCPM_Institutions_Sync::CRON_DAILY ] ), false );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
