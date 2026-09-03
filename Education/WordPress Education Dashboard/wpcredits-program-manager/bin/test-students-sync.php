<?php
/**
 * The students sync's Students-table pass, the institution stamp, and the roster it writes.
 *
 * The sync that provisions every student account gained a second job in the Institutions
 * module (design spec section 8.1): read the Students table once into a per-institution
 * index, join each report to its Students row by email, and stamp the account with its
 * institution's record ID. What this suite pins:
 *
 * - the thirteen columns the pass asks for exist in the Students fixture by exact name,
 *   and the fake Airtable returns only what was asked for, so a column the sync forgot to
 *   request is an absent value here rather than a quiet blank in production;
 * - the reconciliation card's numbers against a synthetic base shaped to the spec's
 *   measured ones (31 / 19 / 10 / 9 / 3) and a Krakow-shaped institution (15 / 8 / 2 / 5);
 * - whose word the stamp is on: `students` from a joined row, `reports` only when the
 *   Students table has no row for the address at all, and no stamp at all when the row it
 *   does have names no institution, when one address is filed under two institutions, and
 *   again after the student leaves the synced set;
 * - the accessibility disclosure never reaches a roster row, while `wpcpm_student_program`
 *   keeps every key it had, plus `institution_source`;
 * - `Hours` is asked for, carried onto the roster row and into the cached program block, and
 *   arrives as the base holds it: fractional values undivided, a logged 0 told apart from a
 *   cell nobody has filled in, and a count past the target neither clamped nor doubted.
 *
 * Every address is under example.test and every name is invented.
 *
 * Run from the plugin root:  php bin/test-students-sync.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array();
$GLOBALS['users']    = array();
$GLOBALS['umeta']    = array();
$GLOBALS['cron']     = array();
$GLOBALS['airtable'] = array();
$GLOBALS['fetches']  = array();
$GLOBALS['states']   = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

/**
 * A user hydrated from `$GLOBALS['users']`, whose role changes are written back.
 *
 * `revoke_departed()` builds a fresh `new WP_User( $id )` and reads `->roles` on it, so a
 * stub that kept roles on the object alone would show the sync a stale list.
 */
class WP_User {
	public $ID = 0, $user_login = '', $user_email = '', $display_name = '', $roles = array();
	public function __construct( $id = 0 ) {
		$this->ID = (int) $id;
		$data     = $GLOBALS['users'][ $this->ID ] ?? array();

		$this->user_login   = $data['login'] ?? '';
		$this->user_email   = $data['email'] ?? '';
		$this->display_name = $data['name'] ?? '';
		$this->roles        = $data['roles'] ?? array();
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) { $this->roles = array_values( array_unique( array_merge( $this->roles, array( $r ) ) ) ); $this->save(); }
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); $this->save(); }
	public function set_role( $r ) { $this->roles = array( $r ); $this->save(); }
	private function save() { $GLOBALS['users'][ $this->ID ]['roles'] = $this->roles; }
}
class WP_Post { public $ID = 0, $post_title = '', $post_type = '', $post_status = 'publish'; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $s, $p = null ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_user( $s, $strict = false ) { return preg_replace( $strict ? '/[^a-z0-9 _.\-@]/i' : '/[^a-z0-9 _.\-@]/i', '', (string) $s ); }
function wp_generate_password() { return 'not-a-real-password'; }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {} function do_action() {}
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
/**
 * `update_option()`, keeping every version of the sync's own state.
 *
 * A tick runs every phase to completion, so by the time `run_tick()` returns the state option
 * has been deleted and the rows the Students pass built are gone. They are written back after
 * each phase step, though, so keeping each write is how a suite that cannot pause a tick can
 * still read what one phase handed to the next.
 */
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ] = $v;

	if ( WPCPM_Students_Sync::OPT_STATE === $k ) {
		$GLOBALS['states'][] = $v;
	}

	return true;
}
function add_option( $k, $v, $x = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $h ) { return isset( $GLOBALS['cron'][ $h ] ) ? $GLOBALS['cron'][ $h ] : false; }
function wp_get_scheduled_event( $h ) { return false; }
function wp_schedule_event() { return true; }
function wp_schedule_single_event( $when, $hook ) { $GLOBALS['cron'][ $hook ] = (int) $when; return true; }
function wp_clear_scheduled_hook( $h = '' ) { unset( $GLOBALS['cron'][ $h ] ); return 1; }

function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ (int) $id ][ $key ] = $value; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ (int) $id ][ $key ] ); return true; }

function get_user_by( $field, $value ) {
	if ( 'id' === $field ) {
		return isset( $GLOBALS['users'][ (int) $value ] ) ? new WP_User( (int) $value ) : false;
	}

	foreach ( $GLOBALS['users'] as $id => $data ) {
		if ( 'email' === $field && strtolower( $data['email'] ) === strtolower( (string) $value ) ) {
			return new WP_User( $id );
		}
	}

	return false;
}
function username_exists( $login ) {
	foreach ( $GLOBALS['users'] as $id => $data ) {
		if ( $data['login'] === $login ) {
			return $id;
		}
	}

	return false;
}
function wp_insert_user( array $data ) {
	static $next = 500;

	if ( get_user_by( 'email', $data['user_email'] ) ) {
		return new WP_Error( 'existing_user_email', 'Sorry, that email address is already used!' );
	}

	$id = ++$next;

	$GLOBALS['users'][ $id ] = array(
		'login' => $data['user_login'],
		'email' => $data['user_email'],
		'name'  => $data['display_name'] ?? '',
		'roles' => array( $data['role'] ),
	);

	return $id;
}

/**
 * Users by exact meta match, or by a meta key existing, honouring `fields`.
 */
function get_users( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['umeta'] as $id => $meta ) {
		if ( isset( $args['meta_key'] ) ) {
			if ( ! isset( $meta[ $args['meta_key'] ] ) || $meta[ $args['meta_key'] ] !== $args['meta_value'] ) {
				continue;
			}
		} elseif ( isset( $args['meta_query'][0]['key'] ) ) {
			if ( ! array_key_exists( $args['meta_query'][0]['key'], $meta ) ) {
				continue;
			}
		}

		$out[] = ( 'ID' === ( $args['fields'] ?? 'all' ) ) ? (int) $id : new WP_User( $id );
	}

	return $out;
}

/**
 * An Airtable that answers from `$GLOBALS['airtable'][ table ]`.
 *
 * Two things are faithful on purpose. Only the requested columns come back, as the API
 * does it, so a column the sync forgot to ask for is absent rather than empty. And the
 * reports table is filtered by the status formula, so a reports row in a status the
 * settings do not track is never seen, which is the production truth the reconciliation
 * numbers have to be read against.
 */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public function formula_in( $field, array $values, $lower = false ) {
		return 'IN:' . json_encode( array( 'field' => $field, 'values' => array_values( $values ) ) );
	}
	public function fetch_page( $table, array $args = array() ) {
		$wanted = isset( $args['fields'] ) ? (array) $args['fields'] : array();
		$filter = null;

		$GLOBALS['fetches'][] = array( 'table' => $table, 'fields' => $wanted );

		if ( ! empty( $args['formula'] ) && 0 === strpos( $args['formula'], 'IN:' ) ) {
			$filter = json_decode( substr( $args['formula'], 3 ), true );
		}

		$out = array();

		foreach ( $GLOBALS['airtable'][ $table ] ?? array() as $record ) {
			$cells = $record['fields'];

			if ( $filter && ! in_array( (string) ( $cells[ $filter['field'] ] ?? '' ), $filter['values'], true ) ) {
				continue;
			}

			if ( $wanted ) {
				$cells = array_intersect_key( $cells, array_flip( $wanted ) );
			}

			$out[] = array( 'id' => $record['id'], 'createdTime' => '2026-01-05T10:00:00.000Z', 'fields' => $cells );
		}

		return array( 'records' => $out, 'offset' => null );
	}
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
		return is_scalar( $value ) ? (string) $value : '';
	}
	public static function link_ids( $value ) {
		$ids = array();
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) {
				$ids[] = $item;
			} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$ids[] = (string) $item['id'];
			}
		}
		return $ids;
	}
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';

/*
 * `WPCPM_Cohort` belongs to another piece; this is its section 7.7 contract, no more.
 */
if ( ! class_exists( 'WPCPM_Cohort' ) ) {
	class WPCPM_Cohort {
		const NONE          = 'none';
		const NOT_SIGNED_UP = array( 'SPAM', 'Duplicated', 'Interested' );
		public static function key( $date ) {
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				return $m[1] . '-H' . ( (int) $m[2] <= 6 ? '1' : '2' );
			}
			return self::NONE;
		}
		public static function compare( $a, $b ) {
			if ( $a === $b ) { return 0; }
			if ( self::NONE === $a ) { return 1; }
			if ( self::NONE === $b ) { return -1; }
			return strcmp( $a, $b );
		}
		public static function participation( array $rows, $key ) {
			$out = array( 'signed_up' => 0, 'graduated' => 0, 'pending' => 0, 'active' => 0, 'withdrawn' => 0, 'not_started' => 0, 'other' => 0 );
			foreach ( $rows as $row ) {
				if ( self::key( $row['start'] ?? '' ) !== $key ) { continue; }
				$status = trim( (string) ( $row['status'] ?? '' ) );
				if ( in_array( $status, self::NOT_SIGNED_UP, true ) ) { continue; }
				++$out['signed_up'];
				if ( 'Graduate' === $status ) { ++$out['graduated']; }
				elseif ( 'Pending graduation' === $status ) { ++$out['pending']; }
				elseif ( WPCPM_Program::is_track( $status ) || 'Paused' === $status ) { ++$out['active']; }
				elseif ( in_array( $status, array( 'Dropped out', 'Fail' ), true ) ) { ++$out['withdrawn']; }
				elseif ( 'Not moving forward' === $status ) { ++$out['not_started']; }
				else { ++$out['other']; }
			}
			return $out;
		}
	}
}

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	++$total;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

$defaults       = WPCPM_Settings::defaults();
$students_table = $defaults['students_table'];
$reports_table  = $defaults['reports_table'];
$fields         = WPCPM_Mentors_Sync::fields();

$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = array(
	'api_token'          => 'patTEST',
	'base_id'            => 'appTEST',
	'auto_sync'          => true,
	'send_welcome_email' => false,
	'student_on_inactive' => 'revoke',
);

// Institutions, as the lookups map the mentors sync leaves behind: the resolved name is
// what `wpcpm_student_program['institution']` has always held, and still does.
$krakow = 'recINSTKRAKOW0001';
$bee    = 'recINSTBEE0000001';
$cee    = 'recINSTCEE0000001';
$dee    = 'recINSTDEE0000001';
$eee    = 'recINSTEEE0000001';
$fff    = 'recINSTFFF0000001';

$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
	'v'            => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
	'institutions' => array(
		$krakow => 'Krakow University of Economics',
		$bee    => 'Bee Institute of Example',
		$cee    => 'Cee College of Example',
		$dee    => 'Dee University of Example',
		$eee    => 'Eee School of Example',
		$fff    => 'Eff Academy of Example',
	),
	// One team, so a reports row that names it resolves to a name rather than to nothing: the
	// roster prints the name and the record ID would be meaningless on a school's screen.
	'teams'        => array( 'recTEAM0000000001' => 'Polyglots' ),
);

/*
 * The synthetic base. Airtable omits an empty cell rather than sending it, so the
 * builders do the same.
 */
$GLOBALS['airtable'][ $students_table ] = array();
$GLOBALS['airtable'][ $reports_table ]  = array();
$ids = array();

function next_record( $prefix ) {
	static $n = 0;
	++$n;
	return $prefix . str_pad( (string) $n, 17 - strlen( $prefix ), '0', STR_PAD_LEFT );
}

/**
 * A Students row. `$institution` '' means no link; `$start` '' means no date.
 */
function student_row( $name, $email, $status, $institution, $start, array $extra = array() ) {
	global $students_table, $fields;

	$cells = array();

	if ( '' !== $name ) { $cells[ $fields['student_record_name'] ] = $name; }
	if ( '' !== $email ) { $cells[ $fields['student_email'] ] = $email; }
	if ( '' !== $status ) { $cells[ $fields['student_status'] ] = $status; }
	if ( '' !== $institution ) { $cells[ $fields['student_institution'] ] = array( $institution ); }
	if ( '' !== $start ) { $cells[ $fields['student_start'] ] = $start; }

	$id = next_record( 'recS' );

	$GLOBALS['airtable'][ $students_table ][] = array( 'id' => $id, 'fields' => array_merge( $cells, $extra ) );

	return $id;
}

/**
 * A Students Reports row.
 */
function report_row( $name, $email, $status, $institution, array $extra = array() ) {
	global $reports_table, $fields;

	$cells = array(
		$fields['report_name']   => $name,
		$fields['report_email']  => $email,
		$fields['report_status'] => $status,
	);

	if ( '' !== $institution ) { $cells[ $fields['report_instituton'] ] = array( $institution ); }

	$id = next_record( 'recR' );

	$GLOBALS['airtable'][ $reports_table ][] = array( 'id' => $id, 'fields' => array_merge( $cells, $extra ) );

	return $id;
}

// Krakow: 15 rows, all 2026 H1: 8 Graduate, 2 Pending graduation, 5 Not moving forward.
for ( $i = 1; $i <= 8; $i++ ) {
	$email = "krakow-grad-$i@example.test";
	$ids[ "k$i" ]  = student_row( "Krakow Graduate $i", $email, 'Graduate', $krakow, '2026-02-16' );
	// The first one carries a profile on its reports row and none on its Students row, which
	// is what every row at one real university looks like: see the assertion further down.
	$ids[ "rk$i" ] = report_row(
		"Krakow Graduate $i",
		$email,
		'Graduate',
		$krakow,
		1 === $i ? array(
			$fields['report_profile'] => 'https://profiles.wordpress.org/krakow-grad-one/',
			$fields['report_website'] => 'https://krakow-grad-one.example.test',
			$fields['report_team']    => array( 'recTEAM0000000001' ),
			// A number, and a fractional one, which is what the live column holds for some
			// students: Airtable sends it as a number and the sync has to keep every digit.
			$fields['report_hours']   => 135.5,
		) : array()
	);
}
for ( $i = 9; $i <= 10; $i++ ) {
	$email = "krakow-pending-$i@example.test";
	$ids[ "k$i" ]  = student_row( "Krakow Pending $i", $email, 'Pending graduation', $krakow, '2026-03-02', 9 === $i ? array(
		$fields['student_access']     => 'Screen reader user',
		$fields['student_tutor']      => 'Ola Tutor',
		$fields['student_study']      => 'Technology & Engineering',
		$fields['student_profile']    => 'https://profiles.wordpress.org/krakow-pending-nine/',
		$fields['student_mentor']     => array( 'recMENTOR00000001' ),
		$fields['student_end']        => '2026-06-30',
		$fields['student_import_key'] => 'batch-1:9',
	) : array() );
	// Nine has logged zero hours, which is an answer somebody recorded and not a blank cell.
	$ids[ "rk$i" ] = report_row( "Krakow Pending $i", $email, 'Pending graduation', $krakow, 9 === $i ? array( $fields['report_hours'] => 0 ) : array() );
}
for ( $i = 11; $i <= 15; $i++ ) {
	$ids[ "k$i" ] = student_row( "Krakow Applicant $i", "krakow-nmf-$i@example.test", 'Not moving forward', $krakow, '2026-02-16' );
}
// A reports row in a status the settings do not track: the formula never returns it, so
// its student still counts as having no report.
report_row( 'Krakow Applicant 11', 'krakow-nmf-11@example.test', 'Not moving forward', $krakow );

// Bee: four addresses each on two rows, both rows filed under Bee, each with one report.
for ( $i = 1; $i <= 4; $i++ ) {
	$email = "bee-twice-$i@example.test";
	$ids[ "b{$i}a" ] = student_row( "Bee Student $i", $email, 'In Sensei', $bee, '2026-08-03' );
	$ids[ "b{$i}b" ] = student_row( "Bee Student $i (again)", strtoupper( $email ), 'In Sensei', $bee, '2026-08-03' );
	// Bee Student 1 has run well past the 150 hours their track is worked to, which four
	// students on the live base have done.
	$ids[ "rb$i" ]   = report_row( "Bee Student $i", $email, 'In Sensei', $bee, 1 === $i ? array( $fields['report_hours'] => 400 ) : array() );
}

// Cee: three duplicated addresses, all Graduate in 2025 H2, none with a report.
for ( $i = 1; $i <= 3; $i++ ) {
	$email = "cee-twice-$i@example.test";
	student_row( "Cee Graduate $i", $email, 'Graduate', $cee, '2025-10-06' );
	student_row( "Cee Graduate $i (again)", $email, 'Graduate', $cee, '2025-10-06' );
}

// One address filed under two institutions, with a report naming Eee.
$ids['e']  = student_row( 'Shared Address', 'shared@example.test', 'In Sensei', $eee, '2026-08-03' );
$ids['f']  = student_row( 'Shared Address', 'shared@example.test', 'In Sensei', $fff, '2026-08-03' );
$ids['re'] = report_row( 'Shared Address', 'shared@example.test', 'In Sensei', $eee );

// Dee: ten status disagreements (six Graduate and four Paused on the Students side, all
// In Sensei on the reports side), one Developer Track row with no start date, the
// students-only rows, and the reports-only rows.
for ( $i = 1; $i <= 6; $i++ ) {
	$email = "dee-disagree-$i@example.test";
	student_row( "Dee Disagreement $i", $email, 'Graduate', $dee, '2026-05-04' );
	report_row( "Dee Disagreement $i", $email, 'In Sensei', $dee );
}
for ( $i = 7; $i <= 10; $i++ ) {
	$email = "dee-disagree-$i@example.test";
	student_row( "Dee Disagreement $i", $email, 'Paused', $dee, '2026-05-04' );
	report_row( "Dee Disagreement $i", $email, 'In Sensei', $dee );
}
$ids['dev']  = student_row( 'Dee Developer', 'dee-dev@example.test', 'Developer Track', $dee, '' );
$ids['rdev'] = report_row( 'Dee Developer', 'dee-dev@example.test', 'Developer Track', $dee );

for ( $i = 1; $i <= 9; $i++ ) {
	student_row( "Dee Applicant $i", "dee-nmf-$i@example.test", 'Not moving forward', $dee, $i <= 3 ? '' : '2026-05-04' );
}
for ( $i = 1; $i <= 6; $i++ ) {
	// One of them has no email at all, which is the third way a row can have no report.
	student_row( "Dee Blank $i", 6 === $i ? '' : "dee-blank-$i@example.test", '', $dee, 1 === $i ? '' : '2026-05-04' );
}
student_row( 'Dee Current 1', 'dee-current-1@example.test', 'In Sensei', $dee, '2026-08-03' );
student_row( 'Dee Current 2', 'dee-current-2@example.test', 'In Sensei', $dee, '2026-08-03' );
student_row( 'Dee Spam', 'dee-spam@example.test', 'SPAM', $dee, '2026-08-03' );

$reports_only = array( 'In Sensei' => 7, 'Graduate' => 6, 'Paused' => 4, 'Developer Track' => 1, 'Pending graduation' => 1 );
foreach ( $reports_only as $status => $count ) {
	for ( $i = 1; $i <= $count; $i++ ) {
		$key = sanitize_key( $status ) . "-$i";
		$id  = report_row( "Reports Only $key", "reports-only-$key@example.test", $status, $dee );

		if ( 'In Sensei' === $status && 1 === $i ) {
			$ids['ronly'] = $id;
		}
	}
}

// No institution on the Students side: one with a report naming Dee, two with nothing.
$ids['u1']  = student_row( 'Unlinked Current', 'unlinked-1@example.test', 'In Sensei', '', '2026-08-03' );
$ids['ru1'] = report_row( 'Unlinked Current', 'unlinked-1@example.test', 'In Sensei', $dee );
student_row( 'Unlinked Applicant', 'unlinked-2@example.test', 'Not moving forward', '', '' );
student_row( 'Unlinked Blank', 'unlinked-3@example.test', '', '', '' );

// A graduate who already has an account: past students are not created, but one that
// exists is refreshed, and must keep its stamp through `revoke_departed()`.
$GLOBALS['users'][100] = array( 'login' => 'krakow-grad-1', 'email' => 'krakow-grad-1@example.test', 'name' => 'Krakow Graduate 1', 'roles' => array( 'subscriber' ) );

echo "=== The columns the pass asks for ===\n";

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/students-table-fields.json' ), true );
$student_keys = array( 'student_record_name', 'student_email', 'student_status', 'student_institution', 'student_start', 'student_end', 'student_mentor', 'student_profile', 'student_tutor', 'student_tutors', 'student_study', 'student_access', 'student_import_key' );

ck( 'fields() names thirteen Students columns',
	count( array_intersect_key( $fields, array_flip( $student_keys ) ) ), 13 );

foreach ( $student_keys as $key ) {
	ck( sprintf( "'%s' is a column of the Students table, byte for byte", $fields[ $key ] ),
		in_array( $fields[ $key ], $fixture['fields'], true ), true );
}

ck( 'the phase label says what it reads now',
	WPCPM_Students_Sync::phases()['tutors']['label'], 'Reading the Students table' );

echo "\n=== One run ===\n";

/**
 * Start a run and drive it to the end in one tick, returning when it started.
 */
function run_sync() {
	$GLOBALS['fetches'] = array();
	$GLOBALS['states']  = array();

	$started = WPCPM_Students_Sync::start();

	if ( true !== $started ) {
		return 0;
	}

	$when = $GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ]['started'];

	WPCPM_Students_Sync::run_tick( 60 );

	return $when;
}

$started = run_sync();

ck( 'the run started', $started > 0, true );
ck( 'and finished: the working state is gone', isset( $GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ] ), false );
ck( 'with no error recorded', get_option( WPCPM_Students_Sync::OPT_ERROR, '' ), '' );

$report = get_option( WPCPM_Students_Sync::OPT_REPORT );
$stats  = $report['stats'];

$students_fetch = array_values( array_filter( $GLOBALS['fetches'], static function ( $f ) use ( $students_table ) { return $f['table'] === $students_table; } ) );
$wanted         = array_map( static function ( $key ) use ( $fields ) { return $fields[ $key ]; }, $student_keys );

ck( 'the Students table was read once', count( $students_fetch ), 1 );
sort( $wanted );
sort( $students_fetch[0]['fields'] );
ck( 'asking for exactly the thirteen columns', $students_fetch[0]['fields'], $wanted );
ck( 'every Students row was read', $stats['rows_read'], count( $GLOBALS['airtable'][ $students_table ] ) );

echo "\n=== The reconciliation card ===\n";

$counts = WPCPM_Roster_Index::counts();
$recon  = $counts['reconciliation'];

ksort( $recon['students_without_reports'] );
ksort( $recon['reports_without_students'] );
ksort( $recon['duplicate_emails'] );
ksort( $recon['no_start_date'] );

ck( 'Students rows with no report: 31, split by status',
	$recon['students_without_reports'],
	array( '' => 7, 'Graduate' => 6, 'In Sensei' => 2, 'Not moving forward' => 15, 'SPAM' => 1 ) );
ck( 'which sum to 31', array_sum( $recon['students_without_reports'] ), 31 );
ck( 'reports rows with no Students row: 19, split by status',
	$recon['reports_without_students'],
	array( 'Developer Track' => 1, 'Graduate' => 6, 'In Sensei' => 7, 'Paused' => 4, 'Pending graduation' => 1 ) );
ck( 'which sum to 19', array_sum( $recon['reports_without_students'] ), 19 );
ck( 'status disagreements on joined rows: 10', $recon['status_disagreements'], 10 );
ck( 'duplicate emails per institution: 9 across four institutions',
	$recon['duplicate_emails'],
	array( $bee => 4, $cee => 3, $eee => 1, $fff => 1 ) );
ck( 'which sum to 9', array_sum( $recon['duplicate_emails'] ), 9 );
ck( 'rows with no institution: 3', $recon['no_institution'], 3 );
ck( 'rows with no start date, split by status',
	$recon['no_start_date'],
	array( '' => 2, 'Developer Track' => 1, 'Not moving forward' => 4 ) );
ck( 'the counts carry the read time of the run', $counts['read'], $started );

echo "\n=== Krakow, the reference report ===\n";

ck( 'Krakow has one cohort, 2026 H1', array_keys( $counts['institutions'][ $krakow ] ), array( '2026-H1' ) );
ck( 'and participation() reads 15 / 8 / 2 / 5',
	$counts['institutions'][ $krakow ]['2026-H1'],
	array( 'signed_up' => 15, 'graduated' => 8, 'pending' => 2, 'active' => 0, 'withdrawn' => 0, 'not_started' => 5, 'other' => 0 ) );

$roster = WPCPM_Roster_Index::read( $krakow );

ck( 'Krakow\'s roster holds its 15 rows', count( $roster['rows'] ), 15 );
ck( 'stamped with the run\'s start time', $roster['read'], $started );
ck( 'Dee has cohorts for May, August and the rows with no date, in that order',
	array_keys( $counts['institutions'][ $dee ] ), array( '2026-H1', '2026-H2', 'none' ) );
ck( 'the three unlinked rows are on the manager\'s list', count( WPCPM_Roster_Index::unlinked() ), 3 );

echo "\n=== What a roster row holds ===\n";

$k9 = $roster['rows'][ $ids['k9'] ];

ck( 'the row is in the index shape', array_keys( $k9 ), WPCPM_Roster_Index::KEYS );

// **And it was in that shape before `clean()` ever saw it.** The Students pass builds each row
// once; the join, the mentor fill and `phase_provision()` then write into it by key, and
// `WPCPM_Roster_Index::clean()` runs only at the end. A key the builder leaves out is therefore
// a key nothing in between can lend into without testing for its existence first, which is how
// a column ends up silently absent for every student on a roster.
$mid = null;

foreach ( $GLOBALS['states'] as $state ) {
	if ( 'provision' === ( $state['phase'] ?? '' ) && ! empty( $state['rows'] ) ) {
		$mid = $state;
		break;
	}
}

$built    = array_keys( $mid['rows'][ $ids['k9'] ] );
$declared = WPCPM_Roster_Index::KEYS;

sort( $built );
sort( $declared );

ck( 'the Students pass built it with every key the index declares', $built, $declared );
ck( 'with the columns read from the table',
	array( $k9['name'], $k9['email_key'], $k9['status'], $k9['institution'], $k9['start'], $k9['end'], $k9['has_mentor'], $k9['username'], $k9['field_of_study'], $k9['tutor'], $k9['import_key'] ),
	array( 'Krakow Pending 9', 'krakow-pending-9@example.test', 'Pending graduation', $krakow, '2026-03-02', '2026-06-30', true, 'krakow-pending-nine', 'Technology & Engineering', 'Ola Tutor', 'batch-1:9' ) );
ck( 'its report and its account were filled in', array( $k9['reports'], $k9['user_id'] > 0 ), array( array( $ids['rk9'] ), true ) );

// **The profile lives on the reports row, not on the Students row.** Measured on the live
// base: at one university the Students table's `WP Profile` column is empty for all fifteen
// of their students while the reports row carries one for eleven of the same fifteen, so a
// roster reading only the Students column showed no WordPress.org profile at all and read as
// though nobody in the program had one. It is the first step of onboarding.
$k1 = $roster['rows'][ $ids['k1'] ];

ck( 'a student with no profile on their Students row takes the one on their report', $k1['username'], 'krakow-grad-one' );
// Only when the Students side gave nothing: a school that does populate its own column keeps
// what it wrote there.
ck( 'and a Students row that has its own keeps it', $k9['username'], 'krakow-pending-nine' );
// A student with neither is still empty, rather than borrowing somebody else's.
ck( 'a student with neither has none', $roster['rows'][ $ids['k2'] ]['username'], '' );

// The same for every other column the reports row can lend. Each of these was found by
// somebody looking at a real roster and asking why a cell was blank, one at a time, so they
// are asserted together: the roster reads all of them off the index for a student who has
// never signed in, which is most students on a school's roster.
ck( 'the website comes off the report record too', $k1['website'], 'https://krakow-grad-one.example.test' );
ck( 'and so does the contribution team', $k1['team'], 'Polyglots' );
// A row the reports side never reached lends nothing, rather than borrowing a neighbour's.
ck( 'a student with no report record has none of them', array( $roster['rows'][ $ids['k2'] ]['website'], $roster['rows'][ $ids['k2'] ]['team'] ), array( '', '' ) );

echo "\n=== Hours reach the roster ===\n";

// Airtable returns only the fields a request names, so a column left out of the pass's list
// arrives as an absent cell rather than as an error: the roster would read "Not recorded" for
// every student in the program and nothing anywhere would say why.
$reports_fetch  = array_values( array_filter( $GLOBALS['fetches'], static function ( $f ) use ( $reports_table ) { return $f['table'] === $reports_table; } ) );
$reports_fields = json_decode( file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );

ck( 'the reports pass asks Airtable for the hours column',
	in_array( $fields['report_hours'], $reports_fetch[0]['fields'], true ), true );
// The name is the base's, byte for byte. A column asked for under a name the base does not
// have is not an error either: Airtable answers with the rows and without that field.
ck( sprintf( "'%s' is a column of the Students Reports table, byte for byte", $fields['report_hours'] ),
	in_array( $fields['report_hours'], $reports_fields['fields'], true ), true );

// **Fractional, and kept that way.** 135.5 is a real value on the live base. An `intval()` or
// a `round()` anywhere on this path prints 135 and tells a school half an hour of somebody's
// term did not happen, so the sync carries the number as the string the base sent.
ck( 'a fractional count reaches the roster row undivided and unrounded', $k1['hours'], '135.5' );

// **Zero is an answer, and `empty()` cannot tell it from silence.** The lend guard compares
// against '' for exactly this row: a student who has logged nothing is not a student nobody
// has logged for, and lending on truthiness would file the first under the second.
ck( 'a logged zero is lent onto the roster row', $k9['hours'], '0' );

// The other half of the same rule. A report row with no hours cell at all leaves the roster
// row empty, so the two students render differently: "0 of 150" against "Not recorded".
ck( 'a report row with no hours cell lends nothing', $roster['rows'][ $ids['k2'] ]['hours'], '' );
ck( 'and a student with no report record has none either', $roster['rows'][ $ids['k11'] ]['hours'], '' );

// **Past the target, and printed as it stands.** Four students on the live base have run over
// 150 hours; nothing on this path may clamp to the target or treat it as a ceiling.
$roster_bee = WPCPM_Roster_Index::rows( $bee );

ck( 'a count past the track target is carried whole', $roster_bee[ $ids['b1a'] ]['hours'], '400' );
// Both Students rows join the one report, the case-only duplicate included, so the lend has to
// reach both of them rather than the first one it meets.
ck( 'and reaches the case-only duplicate of that row too', $roster_bee[ $ids['b1b'] ]['hours'], '400' );

// Graduate 2, not 1: Graduate 1 is the one with a pre-existing account.
$k2 = $roster['rows'][ $ids['k2'] ];

ck( 'a graduate with a report but no account still lists the report',
	array( $k2['reports'], $k2['user_id'] ), array( array( $ids['rk2'] ), 0 ) );
ck( 'and the graduate whose account exists is pointed at it',
	$roster['rows'][ $ids['k1'] ]['user_id'], 100 );
ck( 'a row without a report has neither',
	array( $roster['rows'][ $ids['k11'] ]['reports'], $roster['rows'][ $ids['k11'] ]['user_id'] ), array( array(), 0 ) );

$leak = false;
foreach ( $GLOBALS['opts'] as $name => $value ) {
	if ( 0 === strpos( (string) $name, WPCPM_Roster_Index::OPTION_PREFIX ) && false !== strpos( wp_json_encode( $value ), 'accessibility' ) ) {
		$leak = $name;
	}
	if ( 0 === strpos( (string) $name, WPCPM_Roster_Index::OPTION_PREFIX ) && false !== strpos( wp_json_encode( $value ), 'Screen reader' ) ) {
		$leak = $name;
	}
}
ck( 'no roster option holds an accessibility key or the disclosure itself', $leak, false );

echo "\n=== The stamp, and whose word it is on ===\n";

/**
 * The account behind an address, with its stamp and its program row.
 */
function account( $email ) {
	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		return array( 'id' => 0, 'stamp' => '(no account)', 'program' => array() );
	}

	$meta = $GLOBALS['umeta'][ $user->ID ] ?? array();

	return array(
		'id'      => $user->ID,
		'stamp'   => array_key_exists( WPCPM_Students_Sync::META_INSTITUTION, $meta ) ? $meta[ WPCPM_Students_Sync::META_INSTITUTION ] : '(absent)',
		'program' => $meta[ WPCPM_Students_Sync::META_PROGRAM ] ?? array(),
	);
}

$a = account( 'krakow-pending-9@example.test' );

ck( 'a joined account is stamped with the Students row\'s institution', $a['stamp'], $krakow );
ck( 'on the Students table\'s word', $a['program']['institution_source'], 'students' );
ck( 'and the roster row points back at the account', $k9['user_id'], $a['id'] );

$a = account( 'bee-twice-1@example.test' );

ck( 'two Students rows that agree stamp their institution', $a['stamp'], $bee );
ck( 'still on the Students table\'s word', $a['program']['institution_source'], 'students' );
$roster_b = WPCPM_Roster_Index::rows( $bee );

ck( 'both rows list the report and the account, the case-only duplicate included',
	array(
		$roster_b[ $ids['b1a'] ]['reports'],
		$roster_b[ $ids['b1a'] ]['user_id'],
		$roster_b[ $ids['b1b'] ]['reports'],
		$roster_b[ $ids['b1b'] ]['user_id'],
	),
	array( array( $ids['rb1'] ), $a['id'], array( $ids['rb1'] ), $a['id'] ) );

$a = account( 'reports-only-' . sanitize_key( 'In Sensei' ) . '-1@example.test' );

ck( 'a reports-only account is stamped from the reports-side link', $a['stamp'], $dee );
ck( 'marked as the reports table\'s word', $a['program']['institution_source'], 'reports' );

$a = account( 'unlinked-1@example.test' );

// The authority's blank wins over the reports-side link. The stamp is the fence key, so
// stamping this account from the reports side would hand Dee's roster an account the
// Students table - the authority, design decision 2 - files under nobody, which is the
// failure design decision 1 exists to prevent. The reports-side link is the fallback only
// where the authority is silent, and a row that names no institution is not silence.
ck( 'a Students row that names no institution leaves the account unstamped', $a['stamp'], '(absent)' );
ck( 'still on the Students table\'s word, which is blank', $a['program']['institution_source'], 'students' );
ck( 'and its row is on the unlinked list with the account filled in',
	WPCPM_Roster_Index::unlinked()[ $ids['u1'] ]['user_id'], $a['id'] );

// Counted once and once only: the row is an institution-less row on the reconciliation
// card and on the unlinked list above, and is not also a reports-side stamp.
$on_a_roster = array();

foreach ( array_keys( $counts['institutions'] ) as $institution ) {
	if ( isset( WPCPM_Roster_Index::rows( $institution )[ $ids['u1'] ] ) ) {
		$on_a_roster[] = $institution;
	}
}

// The count is in the label so a run that grouped no rosters at all cannot pass this quietly.
ck( sprintf( 'and on none of the %d institution rosters', count( $counts['institutions'] ) ), $on_a_roster, array() );
ck( 'the reports-side stamps count only the addresses the Students table has no row for',
	$stats['stamped_from_reports'], 13 );

$a = account( 'shared@example.test' );

ck( 'one address under two institutions gets an account', $a['id'] > 0, true );
ck( 'but no stamp at all, not an empty one', $a['stamp'], '(absent)' );
ck( 'and no source', $a['program']['institution_source'], '' );
ck( 'the conflict is counted', $stats['conflicts'], 1 );
ck( 'and named, with the reason',
	count( array_filter( $report['notices'], static function ( $n ) { return false !== strpos( $n, 'duplicate email in the Students table' ) && false !== strpos( $n, 'Shared Address' ); } ) ),
	1 );
ck( 'neither disputed row is given the account or the report',
	array(
		WPCPM_Roster_Index::rows( $eee )[ $ids['e'] ]['user_id'], WPCPM_Roster_Index::rows( $eee )[ $ids['e'] ]['reports'],
		WPCPM_Roster_Index::rows( $fff )[ $ids['f'] ]['user_id'], WPCPM_Roster_Index::rows( $fff )[ $ids['f'] ]['reports'],
	),
	array( 0, array(), 0, array() ) );

$a = account( 'krakow-grad-1@example.test' );

ck( 'a graduate with an existing account is stamped', $a['stamp'], $krakow );
ck( 'and kept inactive, as before', $GLOBALS['umeta'][ $a['id'] ][ WPCPM_Students_Sync::META_ACTIVE ], 0 );
ck( 'a graduate with no account gets none', account( 'krakow-grad-2@example.test' )['id'], 0 );

echo "\n=== wpcpm_student_program keeps its shape ===\n";

$program = account( 'krakow-pending-9@example.test' )['program'];

ck( 'every key the row had, plus institution_source, and nothing else',
	array_keys( $program ),
	array( 'record_id', 'name', 'email', 'program', 'is_past', 'start', 'end', 'institution', 'profile', 'username', 'slack', 'team', 'website', 'hours', 'link', 'tutor', 'field_of_study', 'accessibility', 'institution_source' ) );
// **`hours` has to be one of them.** This block is replaced whole on every run, and
// `apply_report()` writes the student's own saved hours into it between runs; a sync that
// rebuilt the block without the key would delete that value every night, and the roster reads
// this copy before it reads the index.
ck( 'the hours the report row carried are in the cached block', $program['hours'], '0' );
ck( 'the institution is still the resolved name the cards print', $program['institution'], 'Krakow University of Economics' );
ck( 'the accessibility needs still reach the program row', $program['accessibility'], 'Screen reader user' );
ck( 'and so do the tutor and the field of study',
	array( $program['tutor'], $program['field_of_study'] ), array( 'Ola Tutor', 'Technology & Engineering' ) );
ck( 'the reports-side record ID never becomes a program key', isset( $program['institution_id'] ), false );

echo "\n=== Leaving the synced set ===\n";

// Krakow Pending 9 is removed from both tables; the next run must clear the stamp.
$k9_id = account( 'krakow-pending-9@example.test' )['id'];

foreach ( array( $students_table, $reports_table ) as $table ) {
	$GLOBALS['airtable'][ $table ] = array_values( array_filter( $GLOBALS['airtable'][ $table ], static function ( $r ) use ( $fields ) {
		return 'krakow-pending-9@example.test' !== ( $r['fields']['Email'] ?? '' );
	} ) );
}

$second = run_sync();

ck( 'the second run finished', $second > 0 && ! isset( $GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ] ), true );
ck( 'the departed student\'s stamp is deleted', array_key_exists( WPCPM_Students_Sync::META_INSTITUTION, $GLOBALS['umeta'][ $k9_id ] ), false );
ck( 'and the account is inactive', $GLOBALS['umeta'][ $k9_id ][ WPCPM_Students_Sync::META_ACTIVE ], 0 );
ck( 'with the Student role gone', in_array( WPCPM_Roles::ROLE_STUDENT, ( new WP_User( $k9_id ) )->roles, true ), false );
ck( 'the classmate who stayed keeps the stamp', account( 'krakow-pending-10@example.test' )['stamp'], $krakow );
ck( 'the graduate\'s stamp survives too: finished is not departed', account( 'krakow-grad-1@example.test' )['stamp'], $krakow );
ck( 'Krakow\'s roster is down to 14 rows', count( WPCPM_Roster_Index::rows( $krakow ) ), 14 );
ck( 'and reads as of the second run', WPCPM_Roster_Index::read( $krakow )['read'], $second );

echo "\n=== A run started under the previous version ===\n";

// Its state has no `rows`; finishing it must not replace the index with an empty one.
$snapshot = array_filter( $GLOBALS['opts'], static function ( $name ) { return 0 === strpos( (string) $name, WPCPM_Roster_Index::OPTION_PREFIX ); }, ARRAY_FILTER_USE_KEY );

$GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ] = array(
	'phase'    => 'provision',
	'offset'   => null,
	'cursor'   => 0,
	'started'  => time() - 300,
	'touched'  => time(),
	'steps'    => array(),
	'students' => array(),
	'mentors'  => array(),
	'pending'  => array(),
	'tutors'   => array(),
	'study'    => array(),
	'access'   => array(),
	'stats'    => array( 'students_seen' => 0, 'mentors_seen' => 0, 'profiles_read' => 0, 'created' => 0, 'linked' => 0, 'updated' => 0, 'invited' => 0, 'assigned' => 0, 'revoked' => 0, 'skipped' => 0, 'conflicts' => 0, 'no_mentor' => 0 ),
	'notices'  => array(),
);

WPCPM_Students_Sync::run_tick( 60 );

$after = array_filter( $GLOBALS['opts'], static function ( $name ) { return 0 === strpos( (string) $name, WPCPM_Roster_Index::OPTION_PREFIX ); }, ARRAY_FILTER_USE_KEY );

ck( 'the old run finished', isset( $GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ] ), false );
ck( 'and left every roster option exactly as it was', $after, $snapshot );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
