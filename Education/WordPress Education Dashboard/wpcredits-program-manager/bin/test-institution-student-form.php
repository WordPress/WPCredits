<?php
/**
 * The allowlisted student edit form, its two writes and its audit row.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The allowlist is the security boundary.** A POST carrying every column the design spec
 *   deliberately leaves out - `Status` on both tables, `Hours`, `Total hours`, both
 *   institution links, `Mentor`, `Privacy Policy Compliance`, a grade, a `Post Reflection:`
 *   URL, `Notes` and `Email` on both tables - changes nothing and sends no write. The keys
 *   are built the same way the handler builds them, which is exactly what somebody forging
 *   the request would do.
 * - The nonce is checked **before** `claim()`, because `claim()` makes an HTTP request on
 *   this site's Airtable credentials and a cross-site POST must not be able to cause one.
 * - A member of another institution gets the one refusal and **no HTTP happens at all**: the
 *   cheap decision refuses before anything reaches the network.
 * - Each allowed cell lands in the table that owns it: `Name` on Students Reports, the four
 *   others on Students. A form that wrote a Students column to the reports row would fail
 *   silently, because Airtable answers an unknown column with a 422 for the whole record.
 * - An end date before the start date and an end date in 2036 are both refused, and the
 *   refusal names the field rather than losing the rest of the save.
 * - An empty date **clears** rather than being refused: `null` is what Airtable empties a
 *   date column with, and an empty string there is a 422 for every other cell in the save.
 * - **The Students record written is the one `claim()` proved.** A second resolution that
 *   answers with a different row proves nothing and blocks that half; an address on two rows
 *   refuses it by name - with the sentence saying a program manager needs to merge them
 *   first - while the Students Reports half still saves.
 * - **The form and the handler read the same values.** A form posted back untouched writes
 *   nothing however far the live row has drifted, and only the control the reader actually
 *   changed is written. The audit row still names the value the write replaced.
 * - A stored value the program no longer offers is shown **carrying itself**, so re-saving it
 *   is refused rather than clearing the column.
 * - One audit row names the actor, the field, both values and the `member` ground, and an
 *   end date moving on its own is logged as `extend`. A save the log would refuse - a
 *   decision naming no institution - does not happen at all.
 * - The four caches are invalidated, so the report card, the student's own card and the
 *   school's roster all show the change before any sync runs.
 *
 * Run from the plugin root:  php bin/test-institution-student-form.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// Named apart from the local variables below on purpose: at global scope `$GLOBALS['report']`
// and `$report` are the same variable, so a fixture and a record ID sharing a name is one
// silent overwrite and a suite that tests nothing.
$GLOBALS['opts']           = array( 'date_format' => 'Y-m-d' );
$GLOBALS['umeta']          = array();
$GLOBALS['users']          = array();
$GLOBALS['posts']          = array();
$GLOBALS['pmeta']          = array();
$GLOBALS['uid']            = 0;
$GLOBALS['manage']         = array();
$GLOBALS['members']        = array();
$GLOBALS['settled']        = array();
$GLOBALS['http']           = array();
$GLOBALS['writes']         = array();
$GLOBALS['refuse']         = array();
$GLOBALS['live_rows']      = array();
$GLOBALS['report_row']     = array();
$GLOBALS['students_rows']  = array();
$GLOBALS['forgot']         = array();
$GLOBALS['applied']        = array();
$GLOBALS['applied_student'] = array();
$GLOBALS['apply_order']    = array();
$GLOBALS['referer']        = array();
$GLOBALS['fetch_wp_error'] = false;

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return array(); }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s, $p = null ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function register_post_type() {}
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function user_can( $u, $c ) { $id = is_object( $u ) ? $u->ID : (int) $u; return in_array( $id, $GLOBALS['manage'], true ); }
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'email' === $field && 0 === strcasecmp( (string) $user->user_email, (string) $value ) ) { return $user; }
	}
	return false;
}
function get_users( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		$value = $GLOBALS['umeta'][ (int) $id ][ $a['meta_key'] ?? '' ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $a['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post                          = new WP_Post();
	$post->ID                      = ++$next;
	$post->post_title              = $a['post_title'] ?? '';
	$post->post_content            = $a['post_content'] ?? '';
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_date_gmt           = gmdate( 'Y-m-d H:i:s', 1700000000 + $post->ID );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post_time( $f, $gmt = false, $post = null ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			$clause = $a['meta_query'][0];
			$value  = $GLOBALS['pmeta'][ $post->ID ][ $clause['key'] ][0] ?? null;
			if ( null === $value || 0 !== strcasecmp( (string) $value, (string) $clause['value'] ) ) { continue; }
		}
		$out[] = $post;
	}
	return array_reverse( $out );
}
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function selected( $a, $b, $echo = true ) { $out = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $echo ) { echo $out; } return $out; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_get_referer() { return 'https://example.test/institutions/?wpcpm_institution_student=10'; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
		public static function is_record_id( $v ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
		/** Only the keys this form mirrors through; the real map is much longer. */
		public static function fields() {
			return array(
				'report_name'         => 'Name',
				'report_start'        => 'Internship Start Date',
				'report_end'          => 'Internship End Date',
				'student_record_name' => 'Full Name',
				'student_start'       => 'Start Date',
				'student_end'         => 'End Date',
				'student_study'       => 'Your field of study',
			);
		}
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	class WPCPM_Students_Sync {
		const META_RECORD_ID   = 'wpcpm_student_record_id';
		const META_PROGRAM     = 'wpcpm_student_program';
		const META_MENTOR      = 'wpcpm_student_mentor';
		const META_INSTITUTION = 'wpcpm_student_institution';
		public static function user_for_record( $record_id ) {
			$users = get_users( array( 'meta_key' => self::META_RECORD_ID, 'meta_value' => trim( (string) $record_id ) ) );
			return $users ? $users[0] : null;
		}
		/** Contract: carries cells named by their reports-table column into the cached rows. */
		public static function apply_report( $user_id, array $cells ) {
			$GLOBALS['applied'][]     = array( 'user' => (int) $user_id, 'cells' => $cells );
			$GLOBALS['apply_order'][] = 'report';
			return true;
		}
		/**
		 * Contract: the same for the other table, keyed by the Students table's own columns.
		 *
		 * Declared here because the guard in `forget()` is a `method_exists()` one: a stub
		 * without it would pass the suite for a handler that never calls it.
		 */
		public static function apply_student_row( $user_id, array $cells ) {
			$GLOBALS['applied_student'][] = array( 'user' => (int) $user_id, 'cells' => $cells );
			$GLOBALS['apply_order'][]     = 'student';
			return true;
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	class WPCPM_Institution_Members {
		const META_RECORD_ID = '_wpcpm_inst_record';
		public static function memberships_of( $user ) {
			$id = is_object( $user ) ? (int) $user->ID : (int) $user;
			return $GLOBALS['members'][ $id ] ?? array();
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** Contract: the gate the member ground applies, and nothing else is read from here. */
	class WPCPM_Institution_Agreement {
		public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
	}
}
if ( ! class_exists( 'WPCPM_Settings' ) ) {
	class WPCPM_Settings {
		public static function get() { return array( 'reports_table' => 'tblREPORTS', 'students_table' => 'tblSTUDENTS' ); }
	}
}
if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	class WPCPM_Institutions_Dashboard {
		public static function page_url() { return 'https://example.test/institutions/'; }
	}
}
if ( ! class_exists( 'WPCPM_Student_Report_Form' ) ) {
	/** Contract: the cached reports-row reader, and the forget the save has to call. */
	class WPCPM_Student_Report_Form {
		public static function values( $record ) { return $GLOBALS['report_row']; }
		public static function forget( $record ) { $GLOBALS['forgot'][] = trim( (string) $record ); }
	}
}
if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/**
	 * The client, recording every request it is asked to make.
	 *
	 * `flatten()` and `link_ids()` behave as the real ones do, because the handler's
	 * before-and-after comparison and its link resolution are built on them.
	 */
	class WPCPM_Airtable {
		public function __construct( array $settings = array() ) {}
		public function formula_in( $field, array $values, $lower = false ) {
			$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );
			if ( empty( $values ) ) { return ''; }
			return $lower ? sprintf( "LOWER({%s}) = '%s'", $field, strtolower( $values[0] ) ) : sprintf( "{%s} = '%s'", $field, $values[0] );
		}
		public function fetch_page( $table, array $args = array() ) {
			$GLOBALS['http'][] = array( 'fetch', $table, $args );
			if ( $GLOBALS['fetch_wp_error'] ) { return new WP_Error( 'wpcpm_airtable_http', 'The base could not be reached.' ); }
			return array( 'records' => $GLOBALS['students_rows'] );
		}
		public function update_records( $table, array $records ) {
			$GLOBALS['http'][] = array( 'update', $table );
			foreach ( $records as $record ) {
				$GLOBALS['writes'][] = array( 'table' => $table, 'id' => $record['id'], 'fields' => $record['fields'] );
			}
			return in_array( $table, $GLOBALS['refuse'], true ) ? new WP_Error( 'wpcpm_airtable_422', 'Refused.' ) : $records;
		}
		public static function flatten( $value, $glue = ', ' ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) { return (string) $value['name']; }
				$parts = array();
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) { $parts[] = (string) $item; }
					elseif ( is_array( $item ) && isset( $item['name'] ) ) { $parts[] = (string) $item['name']; }
				}
				return implode( $glue, array_filter( $parts, 'strlen' ) );
			}
			return is_scalar( $value ) ? (string) $value : '';
		}
		public static function link_ids( $value ) {
			if ( ! is_array( $value ) ) { return array(); }
			$ids = array();
			foreach ( $value as $item ) {
				if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) { $ids[] = $item; }
				elseif ( is_array( $item ) && ! empty( $item['id'] ) ) { $ids[] = (string) $item['id']; }
			}
			return $ids;
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/**
	 * The fence's front door, stubbed to the four steps the shipped one takes.
	 *
	 * Step 3 is where the HTTP request happens, and it is recorded: "a refusal costs nothing
	 * and a stranger cannot make this site fetch on demand" is an assertion below, and a stub
	 * that read the fixture before deciding would pass it for a handler that did not.
	 */
	class WPCPM_Institution_Roster {
		const ARG_VIEW            = 'wpcpm_institution_view';
		const TYPE_STUDENT        = 'student';
		const TYPE_REPORT         = 'report';
		const FIELD_INSTITUTIONS  = 'Educational Institutions';
		const FIELD_STUDENTS_LINK = 'Students';
		const FIELD_EMAIL         = 'Email';

		public static function claim( $record, $action, $type = self::TYPE_STUDENT, $user = null ) {
			$record = trim( (string) $record );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! in_array( $type, array( self::TYPE_STUDENT, self::TYPE_REPORT ), true ) ) {
				return WPCPM_Institution_Policy::refusal();
			}

			$student = WPCPM_Students_Sync::user_for_record( $record );
			$subject = $student instanceof WP_User
				? WPCPM_Institution_Policy::subject_student_account( $student->ID )
				: WPCPM_Institution_Policy::subject_index_row( '', $record );

			if ( empty( WPCPM_Institution_Policy::decide( $action, $subject, $user )['allowed'] ) ) {
				return WPCPM_Institution_Policy::refusal();
			}

			$GLOBALS['http'][] = array( 'claim', $record );

			$row = $GLOBALS['live_rows'][ $record ] ?? null;

			if ( $row instanceof WP_Error ) { return $row; }
			if ( ! is_array( $row ) ) { return WPCPM_Institution_Policy::refusal(); }

			$decision = WPCPM_Institution_Policy::decide(
				$action,
				WPCPM_Institution_Policy::subject_live( $type, $record, WPCPM_Airtable::link_ids( $row['fields'][ self::FIELD_INSTITUTIONS ] ?? array() ) ),
				$user
			);

			if ( empty( $decision['allowed'] ) ) { return WPCPM_Institution_Policy::refusal(); }

			return array( 'record' => $row, 'decision' => $decision );
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-form.php';

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
function has( $haystack, $needle ) { return false !== strpos( $haystack, $needle ); }
/** Whether one call comes before another, with both of them present: absence answers no. */
function before( $src, $first, $second ) {
	$a = strpos( $src, $first );
	$b = strpos( $src, $second );
	return false !== $a && false !== $b && $a < $b;
}
/** A well-formed Airtable record ID from a short name. */
function rid( $name ) { return 'rec' . str_pad( $name, 14, '0' ); }
function k( $table, $name ) { return WPCPM_Institution_Student_Form::key( $table, $name ); }
function flash() { return $GLOBALS['umeta'][ $GLOBALS['uid'] ]['wpcpm_flash']['institution_student'] ?? null; }
function flash_status() { $f = flash(); return is_array( $f ) ? $f['status'] : ''; }
function flash_detail() { $f = flash(); return is_array( $f ) ? $f['detail'] : ''; }
function writes_to( $table ) {
	$out = array();
	foreach ( $GLOBALS['writes'] as $write ) { if ( $table === $write['table'] ) { $out[] = $write; } }
	return $out;
}

$inst_a  = rid( 'INSTA' );
$inst_b  = rid( 'INSTB' );
$report  = rid( 'REPORT' );
$student = rid( 'STUDENT' );
$dupe    = rid( 'DUPE' );

/**
 * One world: institution A with one student, one member each side, one manager.
 */
function reset_world() {
	global $inst_a, $inst_b, $report, $student;

	$GLOBALS['opts']           = array();
	$GLOBALS['umeta']          = array();
	$GLOBALS['posts']          = array();
	$GLOBALS['pmeta']          = array();
	$GLOBALS['http']           = array();
	$GLOBALS['writes']         = array();
	$GLOBALS['refuse']         = array();
	$GLOBALS['forgot']          = array();
	$GLOBALS['applied']         = array();
	$GLOBALS['applied_student'] = array();
	$GLOBALS['apply_order']     = array();
	$GLOBALS['referer']         = array();
	$GLOBALS['fetch_wp_error'] = false;
	$GLOBALS['manage']         = array( 1 );
	$GLOBALS['settled']        = array( $inst_a, $inst_b );

	$GLOBALS['users'] = array(
		1  => new WP_User( 1, 'A Manager', 'manager@example.test' ),
		2  => new WP_User( 2, 'A Member', 'a@example.test' ),
		3  => new WP_User( 3, 'B Member', 'b@example.test' ),
		10 => new WP_User( 10, 'Ada Example', 'ada@example.test' ),
	);

	$GLOBALS['members'] = array(
		2 => array( $inst_a ),
		3 => array( $inst_b ),
	);

	$GLOBALS['umeta'][10] = array(
		'wpcpm_student_record_id'   => $report,
		'wpcpm_student_institution' => $inst_a,
		'wpcpm_student_program'     => array(
			'name'           => 'Ada E',
			'start'          => '2026-02-01',
			'end'            => '2026-06-30',
			'field_of_study' => 'Technology & Engineering',
			'accessibility'  => 'A disclosure the school never sees',
		),
	);

	// What `claim()` hands back: the live Students row, cut to the disclosed columns.
	$GLOBALS['live_rows'] = array(
		$report => array(
			'id'     => $student,
			'fields' => array(
				'Educational Institutions' => array( $inst_a ),
				'Full Name'                => 'Ada Example',
				'Start Date'               => '2026-02-01',
				'End Date'                 => '2026-06-30',
				'Your field of study'      => 'Technology & Engineering',
				'Email'                    => 'ada@example.test',
			),
		),
	);

	// The reports row, read through the report form's cached reader.
	$GLOBALS['report_row'] = array(
		'Name'     => 'Ada E',
		'Email'    => 'ada@example.test',
		'Students' => array(),
		'Status'   => 'In Sensei',
	);

	// The Students table's answer to the address, one row.
	$GLOBALS['students_rows'] = array( array( 'id' => $student, 'fields' => array( 'Email' => 'ada@example.test' ) ) );

	WPCPM_Roster_Index::write_all(
		array(
			$inst_a => array(
				array(
					'record_id'      => $student,
					'name'           => 'Ada Example',
					'email'          => 'ada@example.test',
					'status'         => 'In Sensei',
					'institution'    => $inst_a,
					'start'          => '2026-02-01',
					'end'            => '2026-06-30',
					'field_of_study' => 'Technology & Engineering',
					'user_id'        => 10,
					'reports'        => array( $report ),
				),
			),
		),
		array(),
		array( $inst_a => array() ),
		array(),
		1756000000
	);
}

/** Post the form as one user and report how the handler ended. */
function save( $viewer, $record, array $values ) {
	$GLOBALS['uid']    = (int) $viewer;
	$GLOBALS['http']   = array();
	$GLOBALS['writes'] = array();
	$_POST             = array(
		'action'  => WPCPM_Institution_Student_Form::ACTION_SAVE,
		'record'  => $record,
		'student' => $values,
	);

	try {
		WPCPM_Institution_Student_Form::handle_save();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}

	return '';
}

/** Draw the form as one reader. */
function draw( $viewer, $record, $institution, $user_id ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Student_Form::render_form( $record, array( 'institution' => $institution, 'user_id' => $user_id ) );
	return (string) ob_get_clean();
}

/**
 * What the drawn form actually put in each control, keyed the way the form keys it.
 *
 * This is the browser's half of the round trip: feeding it straight back to `save()` is a
 * reader pressing the button without touching anything, which is the press that used to
 * overwrite every cell where the caches and the live row disagreed.
 *
 * @param string $html The rendered form.
 * @return array<string, string> Form key to the value the control holds.
 */
function drawn_values( $html ) {
	$out = array();

	if ( preg_match_all( '/name="student\[([^"]+)\]" value="([^"]*)"/', $html, $inputs, PREG_SET_ORDER ) ) {
		foreach ( $inputs as $input ) {
			$out[ $input[1] ] = html_entity_decode( $input[2], ENT_QUOTES );
		}
	}

	// Anchored on `<select` rather than on the name alone: `[^>]*` cannot cross a `>`, but a
	// pattern starting at an input's own name would still run on to the first `</select>`.
	if ( preg_match( '/<select[^>]*name="student\[([^"]+)\]"[^>]*>(.*?)<\/select>/s', $html, $select ) ) {
		$chosen = '';

		if ( preg_match( '/<option value="([^"]*)" selected="selected">/', $select[2], $option ) ) {
			$chosen = html_entity_decode( $option[1], ENT_QUOTES );
		}

		$out[ $select[1] ] = $chosen;
	}

	return $out;
}


/* ---- the allowlist itself ------------------------------------------------ */

echo "=== The allowlist is exactly what the design spec prints ===\n";

ck(
	'five columns across two tables, and no others',
	WPCPM_Institution_Student_Form::fields(),
	array(
		'reports'  => array(
			'Name' => array(
				'type'      => 'text',
				'maxlength' => 200,
			),
		),
		'students' => array(
			'Full Name'           => array(
				'type'      => 'text',
				'maxlength' => 200,
			),
			'Start Date'          => array( 'type' => 'date' ),
			'End Date'            => array(
				'type'     => 'date',
				'after'    => 'Start Date',
				'max_days' => 365,
			),
			'Your field of study' => array(
				'type'    => 'select',
				'choices' => 'field_of_study',
			),
		),
	)
);

ck( 'the key is table-scoped, because both tables carry Email', k( 'reports', 'Email' ) === k( 'students', 'Email' ), false );
ck( 'and it is the hash the spec names', k( 'students', 'End Date' ), 'f' . substr( md5( 'students|End Date' ), 0, 12 ) );
ck( 'the field of study choices are a server-held list', count( WPCPM_Institution_Student_Form::choices( 'field_of_study' ) ), 9 );
ck( 'a list nobody holds is empty, so a select with a typo in it accepts nothing', WPCPM_Institution_Student_Form::choices( 'made_up' ), array() );

$fixture = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/students-table-fields.json' ), true );
ck(
	'and the nine are the base\'s own, as the fixture recorded them',
	WPCPM_Institution_Student_Form::choices( 'field_of_study' ),
	$fixture['choices']['Your field of study']
);

foreach ( array( 'Full Name', 'Start Date', 'End Date', 'Your field of study' ) as $column ) {
	ck( "'$column' is a real Students column", in_array( $column, $fixture['fields'], true ), true );
}


/* ---- the date type ------------------------------------------------------- */

echo "\n=== WPCPM_Field_Value gains 'date' ===\n";

$date = static function ( $raw ) { return WPCPM_Field_Value::clean( $raw, array( 'type' => 'date' ) ); };

ck( 'an empty date clears the column, which Airtable does with null', $date( '' ), array( 'ok' => true, 'value' => null, 'problem' => '' ) );
ck( 'a real date is taken as typed', $date( '2026-06-30' ), array( 'ok' => true, 'value' => '2026-06-30', 'problem' => '' ) );
ck( 'a day that is not on the calendar is refused', $date( '2026-06-31' )['problem'], 'bad_date' );
ck( 'and so is 29 February in a common year', $date( '2025-02-29' )['problem'], 'bad_date' );
ck( 'an unpadded date is refused rather than repaired', $date( '2026-6-3' )['problem'], 'bad_date' );
ck( 'a word is refused', $date( 'next Tuesday' )['problem'], 'bad_date' );
ck( 'a date with time on the end is refused', $date( '2026-06-30T00:00:00Z' )['problem'], 'bad_date' );
ck( 'a refused date writes nothing', $date( '2026-06-31' )['value'], null );

ck( 'and the types that were there are untouched', array(
	WPCPM_Field_Value::clean( '12', array( 'type' => 'number' ) )['value'],
	WPCPM_Field_Value::clean( 'x', array( 'type' => 'select', 'choices' => array( 'x' ) ) )['value'],
	WPCPM_Field_Value::clean( '1', array( 'type' => 'checkbox' ) )['value'],
), array( 12.0, 'x', true ) );


/* ---- one save, both tables ----------------------------------------------- */

echo "\n=== Each allowed cell lands in the table that owns it ===\n";

reset_world();
$end = save(
	2,
	$report,
	array(
		k( 'reports', 'Name' )                 => 'Ada Example-Nowak',
		k( 'students', 'Full Name' )           => 'Ada Example-Nowak',
		k( 'students', 'Start Date' )          => '2026-02-01',
		k( 'students', 'End Date' )            => '2026-07-31',
		k( 'students', 'Your field of study' ) => 'Design & Creative Media',
	)
);

ck( 'the save ends in a redirect back to the card', 0 === strpos( $end, 'redirect:' ), true );
ck( 'and says so', flash_status(), 'student-saved' );
ck( 'the reports row is written its own column and no other', writes_to( 'tblREPORTS' ), array(
	array( 'table' => 'tblREPORTS', 'id' => $report, 'fields' => array( 'Name' => 'Ada Example-Nowak' ) ),
) );
// In the allowlist's own order, which is the order the walk runs in: a cell set is built
// from the map and never from what the form happened to post.
ck( 'the Students row is written the three that changed, on the record that was proven', writes_to( 'tblSTUDENTS' ), array(
	array(
		'table'  => 'tblSTUDENTS',
		'id'     => $student,
		'fields' => array(
			'Full Name'           => 'Ada Example-Nowak',
			'End Date'            => '2026-07-31',
			'Your field of study' => 'Design & Creative Media',
		),
	),
) );
ck( 'the start date that did not change is not written', array_key_exists( 'Start Date', writes_to( 'tblSTUDENTS' )[0]['fields'] ), false );

reset_world();
save( 2, $report, array( k( 'reports', 'Name' ) => 'Ada E' ) );
ck( 'a save that changes nothing writes nothing at all', $GLOBALS['writes'], array() );
ck( 'and says so', flash_status(), 'student-nothing' );


/* ---- the allowlist as a boundary ----------------------------------------- */

echo "\n=== A field outside the allowlist changes nothing ===\n";

reset_world();

// Every column section 7.8 names as deliberately absent, keyed exactly as this form keys
// its own: this is the request somebody who had read the source would send.
$forbidden = array(
	k( 'students', 'Status' )                     => 'Graduate',
	k( 'reports', 'Status' )                      => '150h',
	k( 'students', 'Total hours' )                => '150',
	k( 'reports', 'Hours' )                       => '150',
	k( 'students', 'Educational Institutions' )   => rid( 'INSTB' ),
	k( 'reports', 'Educational institution' )     => rid( 'INSTB' ),
	k( 'students', 'Mentor' )                     => rid( 'MENTOR' ),
	k( 'reports', 'Mentor' )                      => rid( 'MENTOR' ),
	k( 'students', 'Privacy Policy Compliance' )  => '1',
	k( 'reports', 'Grade' )                       => '100',
	k( 'reports', 'Post Reflection: 1' )          => 'https://example.test/post',
	k( 'students', 'Notes' )                      => 'A note the program wrote',
	k( 'students', 'Accessibility needs' )        => 'A disclosure the school never sees',
	k( 'students', 'Email' )                      => 'somebody-else@example.test',
	k( 'reports', 'Email' )                       => 'somebody-else@example.test',
);

save( 2, $report, $forbidden );

ck( 'not one write is made', $GLOBALS['writes'], array() );
ck( 'no request carries any of them', has( wp_json_encode_compat( $GLOBALS['http'] ), 'Graduate' ), false );
ck( 'the save reports that nothing changed rather than that something was refused', flash_status(), 'student-nothing' );
ck( 'and nothing was logged', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );

reset_world();
save( 2, $report, array_merge( $forbidden, array( k( 'students', 'End Date' ) => '2026-07-31' ) ) );
ck( 'a forbidden field alongside an allowed one is still dropped', writes_to( 'tblSTUDENTS' ), array(
	array( 'table' => 'tblSTUDENTS', 'id' => $student, 'fields' => array( 'End Date' => '2026-07-31' ) ),
) );
ck( 'and the allowed one still saves', flash_status(), 'student-saved' );


/* ---- the fence ----------------------------------------------------------- */

echo "\n=== A member of another institution gets the one refusal, and no HTTP ===\n";

reset_world();
save( 3, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );

ck( 'nothing is written', $GLOBALS['writes'], array() );
ck( 'nothing is fetched either: the cheap decision refuses first', $GLOBALS['http'], array() );
ck( 'and the message is the policy\'s one refusal', flash_status(), 'student-refused' );
ck( 'byte for byte', WPCPM_Institution_Student_Form::messages()['student-refused'][1], WPCPM_Institution_Policy::refusal()->get_error_message() );

reset_world();
save( 3, rid( 'NOSUCH' ), array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'an unknown record reads exactly the same way', flash_status(), 'student-refused' );
ck( 'and costs nothing', $GLOBALS['http'], array() );

reset_world();
$GLOBALS['settled'] = array( $inst_b );
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a member whose agreement is not settled is refused by the gate in the policy', flash_status(), 'student-refused' );
ck( 'without a request', $GLOBALS['http'], array() );

reset_world();
save( 1, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a program manager passes', flash_status(), 'student-saved' );


/* ---- the cross-field rules ----------------------------------------------- */

echo "\n=== The rules that need two cells ===\n";

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-01-01' ) );
ck( 'an end date before the stored start date is refused', $GLOBALS['writes'], array() );
ck( 'and the message names the field', array( flash_status(), flash_detail() ), array( 'student-rejected', 'End date.' ) );

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '2036-06-30' ) );
ck( 'an end date in 2036 is refused by max_days', $GLOBALS['writes'], array() );
ck( 'by name', flash_detail(), 'End date.' );

reset_world();
save( 2, $report, array( k( 'students', 'Start Date' ) => '2026-03-01', k( 'students', 'End Date' ) => '2026-02-01' ) );
ck( 'the rule runs against what was just typed, not only against what is stored', $GLOBALS['writes'], array(
	array( 'table' => 'tblSTUDENTS', 'id' => $student, 'fields' => array( 'Start Date' => '2026-03-01' ) ),
) );
ck( 'and the rest of the save still lands', flash_status(), 'student-partly' );

reset_world();
save( 2, $report, array( k( 'students', 'Start Date' ) => '2026-01-01', k( 'students', 'End Date' ) => '2026-12-31' ) );
ck( 'a placement of just under a year is fine', flash_status(), 'student-saved' );

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '' ) );
ck( 'an empty end date clears the column rather than being refused', writes_to( 'tblSTUDENTS' ), array(
	array( 'table' => 'tblSTUDENTS', 'id' => $student, 'fields' => array( 'End Date' => null ) ),
) );
ck( 'and it is a save, not a refusal', flash_status(), 'student-saved' );

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-06-31' ) );
ck( 'a date that is not a day is refused', array( $GLOBALS['writes'], flash_status() ), array( array(), 'student-rejected' ) );

reset_world();
save( 2, $report, array( k( 'students', 'Your field of study' ) => 'Underwater Basket Weaving' ) );
ck( 'a choice the column does not offer is refused rather than invented', array( $GLOBALS['writes'], flash_detail() ), array( array(), 'Field of study.' ) );


/* ---- proving the Students row -------------------------------------------- */

echo "\n=== The Students row is proven, not assumed ===\n";

reset_world();
$GLOBALS['report_row']['Students'] = array( $student );
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'the report\'s Students link is honoured first', writes_to( 'tblSTUDENTS' )[0]['id'], $student );
$fetches = 0;
foreach ( $GLOBALS['http'] as $call ) { if ( 'fetch' === $call[0] ) { $fetches++; } }
ck( 'and no address lookup is made when there is a link', $fetches, 0 );

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
$formula = '';
foreach ( $GLOBALS['http'] as $call ) { if ( 'fetch' === $call[0] ) { $formula = $call[2]['formula']; } }
ck( 'with no link the address is used, folded on both sides', $formula, "LOWER({Email}) = 'ada@example.test'" );

reset_world();
$GLOBALS['students_rows'][] = array( 'id' => $dupe, 'fields' => array( 'Email' => 'ada@example.test' ) );
save(
	2,
	$report,
	array(
		k( 'reports', 'Name' )      => 'Ada Example-Nowak',
		k( 'students', 'End Date' ) => '2026-07-31',
	)
);
ck( 'an address on two rows writes nothing to the Students table', writes_to( 'tblSTUDENTS' ), array() );
ck( 'and the Students Reports half still saves', writes_to( 'tblREPORTS' ), array(
	array( 'table' => 'tblREPORTS', 'id' => $report, 'fields' => array( 'Name' => 'Ada Example-Nowak' ) ),
) );
ck( 'the message names the merge', flash_status(), 'student-merge' );
ck(
	'in as many words',
	has( WPCPM_Institution_Student_Form::messages()['student-merge'][1], 'a program manager needs to merge them first' ),
	true
);
ck( 'and the audit row records only what landed', array_column( WPCPM_Institution_Audit::entries_for( $inst_a )[0]['data']['changes'], 'field' ), array( 'reports|Name' ) );

reset_world();
$GLOBALS['students_rows'][] = array( 'id' => $dupe, 'fields' => array( 'Email' => 'ada@example.test' ) );
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'with nothing else to save the whole press says so', flash_status(), 'student-merge-only' );
ck( 'and writes nothing', $GLOBALS['writes'], array() );
ck( 'and logs nothing', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );

reset_world();
$GLOBALS['students_rows'] = array();
save( 2, $report, array( k( 'reports', 'Name' ) => 'Ada Example-Nowak', k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a student with no Students row at all is a different sentence', flash_status(), 'student-no-row' );
ck( 'and the reports half still saves', count( writes_to( 'tblREPORTS' ) ), 1 );

reset_world();
$GLOBALS['fetch_wp_error'] = true;
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a base that could not be read is not a half-save', array( $GLOBALS['writes'], flash_status() ), array( array(), 'student-unreadable' ) );


echo "\n=== And the row that is written is the one the claim proved ===\n";

reset_world();

// `claim()` read the live Students row and allowed the write on the link that row carries,
// so `$student` is the only Students record this request is authorised against. The report
// row, read from a cache that can be five minutes old, names another one. The second answer
// is a proof that failed, not a second address to write to.
$GLOBALS['report_row']['Students'] = array( $dupe );

save(
	2,
	$report,
	array(
		k( 'reports', 'Name' )      => 'Ada Example-Nowak',
		k( 'students', 'End Date' ) => '2026-07-31',
	)
);

ck( 'a Students row the claim did not prove is not written to', writes_to( 'tblSTUDENTS' ), array() );
ck( 'and is named in no request at all', has( wp_json_encode_compat( $GLOBALS['http'] ), $dupe ), false );
ck( 'the school is told the sentence it can act on', flash_status(), 'student-merge' );
ck( 'the Students Reports half still saves', count( writes_to( 'tblREPORTS' ) ), 1 );
ck( 'and the audit row names no Students record, because none was written', WPCPM_Institution_Audit::entries_for( $inst_a )[0]['data']['students_record'], '' );

reset_world();
// The same disagreement down the address route, which is the one every claim takes today.
$GLOBALS['students_rows'] = array( array( 'id' => $dupe, 'fields' => array( 'Email' => 'ada@example.test' ) ) );
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'an address answering with a different row is refused the same way', array( $GLOBALS['writes'], flash_status() ), array( array(), 'student-merge-only' ) );
ck( 'and logs nothing', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );

reset_world();
// The proof agreeing is the ordinary case, and it changes nothing about where the write goes.
$GLOBALS['live_rows'][ $report ]['id'] = $student;
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a proof that agrees writes to the record they both name', writes_to( 'tblSTUDENTS' )[0]['id'], $student );

reset_world();
$GLOBALS['refuse'] = array( 'tblSTUDENTS' );
save( 2, $report, array( k( 'reports', 'Name' ) => 'Ada Example-Nowak', k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a refused Students write after a good reports write says part landed', flash_status(), 'student-part-airtable' );
ck( 'and the audit row names only the half that did', array_column( WPCPM_Institution_Audit::entries_for( $inst_a )[0]['data']['changes'], 'field' ), array( 'reports|Name' ) );

reset_world();
$GLOBALS['refuse'] = array( 'tblSTUDENTS' );
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a refused Students write with nothing else in the save says nothing was saved', flash_status(), 'student-airtable' );
ck( 'and logs nothing', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );

reset_world();
$GLOBALS['refuse'] = array( 'tblREPORTS' );
save( 2, $report, array( k( 'reports', 'Name' ) => 'Ada Example-Nowak', k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a refused reports write stops before the second one', writes_to( 'tblSTUDENTS' ), array() );
ck( 'and says nothing was saved', flash_status(), 'student-airtable' );


/* ---- the audit row ------------------------------------------------------- */

echo "\n=== One audit row, naming actor, field, both values and the ground ===\n";

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31', k( 'reports', 'Name' ) => 'Ada Example-Nowak' ) );

$entries = WPCPM_Institution_Audit::entries_for( $inst_a );
ck( 'exactly one row', count( $entries ), 1 );
$entry = $entries[0];
ck( 'filed under the institution the decision named', $entry['institution'], $inst_a );
ck( 'naming the actor', $entry['actor'], 2 );
ck( 'on the member ground', $entry['ground'], WPCPM_Institution_Audit::GROUND_MEMBER );
ck( 'against live evidence, because that is what claim() decided on', $entry['evidence'], WPCPM_Institution_Audit::EVIDENCE_LIVE );
ck( 'about the record that was edited', $entry['subject'], $report );
ck( 'with both values for each field', $entry['data']['changes'], array(
	array( 'field' => 'reports|Name', 'from' => 'Ada E', 'to' => 'Ada Example-Nowak' ),
	array( 'field' => 'students|End Date', 'from' => '2026-06-30', 'to' => '2026-07-31' ),
) );
ck( 'and the Students record it wrote to', $entry['data']['students_record'], $student );
ck( 'the message reads as a sentence', has( $entry['message'], 'End date: 2026-06-30 to 2026-07-31.' ), true );
ck( 'two fields at once is an edit', $entry['kind'], WPCPM_Institution_Student_Form::KIND_EDIT );

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'the end date on its own is an extension', WPCPM_Institution_Audit::entries_for( $inst_a )[0]['kind'], WPCPM_Institution_Student_Form::KIND_EXTEND );

reset_world();
save( 1, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a manager\'s save is logged as a manager\'s', WPCPM_Institution_Audit::entries_for( $inst_a )[0]['ground'], WPCPM_Institution_Audit::GROUND_MANAGER );

reset_world();
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-06-30' ) );
ck( 'a value saved as itself logs nothing', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );


echo "\n=== A save the log would refuse is a save that does not happen ===\n";

reset_world();

// The live Students row has lost its institution link. A member is refused outright by the
// policy, which has no ground for an institution-less subject; a manager passes, on the
// manager ground, with no institution named - and an audit row cannot be filed under one
// that does not exist.
$GLOBALS['live_rows'][ $report ]['fields']['Educational Institutions'] = array();

save( 1, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );

ck( 'a manager\'s write that could not be recorded is not made', $GLOBALS['writes'], array() );
ck( 'nothing is logged either, under this institution or any other', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );
ck( 'and the message says why', flash_status(), 'student-unfiled' );
ck( 'in words about the link, not about permission', has( WPCPM_Institution_Student_Form::messages()['student-unfiled'][1], 'not linked to any institution' ), true );

reset_world();
$GLOBALS['live_rows'][ $report ]['fields']['Educational Institutions'] = array();
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-07-31' ) );
ck( 'a member never reaches it: the fence refuses them first', array( $GLOBALS['writes'], flash_status() ), array( array(), 'student-refused' ) );


/* ---- the three caches ---------------------------------------------------- */

echo "\n=== The four caches that would otherwise show yesterday ===\n";

reset_world();
save(
	2,
	$report,
	array(
		k( 'reports', 'Name' )                 => 'Ada Example-Nowak',
		k( 'students', 'End Date' )            => '2026-07-31',
		k( 'students', 'Your field of study' ) => 'Health & Medicine',
	)
);

ck( 'the report card\'s transient is forgotten', $GLOBALS['forgot'], array( $report ) );
ck( 'the cached program row is handed the mirrored columns', $GLOBALS['applied'], array(
	array(
		'user'  => 10,
		'cells' => array(
			'Name'                  => 'Ada Example-Nowak',
			'Internship End Date'   => '2026-07-31',
		),
	),
) );
// Spec 7.8's fourth invalidation. `Your field of study` has no reports-side column at all,
// so without this call the student's own card keeps last week's answer until a sync runs.
ck( 'and the Students-side columns, which no reports column carries', $GLOBALS['applied_student'], array(
	array(
		'user'  => 10,
		'cells' => array(
			'End Date'            => '2026-07-31',
			'Your field of study' => 'Health & Medicine',
		),
	),
) );
ck( 'the Students half is handed over first, so the reports Name wins a save that corrects both', $GLOBALS['apply_order'], array( 'student', 'report' ) );
$row = WPCPM_Roster_Index::rows( $inst_a )[ $student ];
ck( 'and the roster shows the change without a sync', array( $row['end'], $row['field_of_study'] ), array( '2026-07-31', 'Health & Medicine' ) );
ck( 'the roster\'s read time is left where it was, because the other rows are as old as they were', WPCPM_Roster_Index::read( $inst_a )['read'], 1756000000 );

reset_world();
save( 2, $report, array( k( 'students', 'Full Name' ) => 'Ada Nowak' ) );
ck( 'a corrected full name reaches the cached program row on its own', $GLOBALS['applied_student'], array(
	array( 'user' => 10, 'cells' => array( 'Full Name' => 'Ada Nowak' ) ),
) );
ck( 'and the reports-side map is handed nothing, having no column for it', $GLOBALS['applied'], array() );


/* ---- WPCPM_Roster_Index::update() on its own ----------------------------- */

echo "\n=== The roster index merge ===\n";

reset_world();
ck( 'an unknown institution writes nothing', WPCPM_Roster_Index::update( rid( 'NOPE' ), $student, array( 'end' => '2027-01-01' ) ), false );
ck( 'a record ID that is not one writes nothing', WPCPM_Roster_Index::update( $inst_a, 'not-a-record', array( 'end' => '2027-01-01' ) ), false );
ck( 'an unknown row writes nothing', WPCPM_Roster_Index::update( $inst_a, rid( 'GHOST' ), array( 'end' => '2027-01-01' ) ), false );
ck( 'a row with nothing to change writes nothing', WPCPM_Roster_Index::update( $inst_a, $student, array() ), false );
ck( 'a key the index does not hold writes nothing', WPCPM_Roster_Index::update( $inst_a, $student, array( 'accessibility' => 'no' ) ), false );
ck( 'and the row is untouched by any of them', WPCPM_Roster_Index::rows( $inst_a )[ $student ]['end'], '2026-06-30' );

ck( 'a named key merges', WPCPM_Roster_Index::update( $inst_a, $student, array( 'end' => '2027-01-01' ) ), true );
$row = WPCPM_Roster_Index::rows( $inst_a )[ $student ];
ck( 'and only that key moves', array( $row['end'], $row['name'], $row['status'] ), array( '2027-01-01', 'Ada Example', 'In Sensei' ) );

WPCPM_Roster_Index::update( $inst_a, $student, array( 'institution' => $inst_b, 'record_id' => $dupe, 'name' => 'Renamed' ) );
$row = WPCPM_Roster_Index::rows( $inst_a )[ $student ];
ck( 'the fence\'s anchor cannot be moved through here', array( $row['institution'], $row['record_id'], $row['name'] ), array( $inst_a, $student, 'Renamed' ) );
ck( 'and the row keeps its key', array_keys( WPCPM_Roster_Index::rows( $inst_a ) ), array( $student ) );


/* ---- the form ------------------------------------------------------------ */

echo "\n=== The form ===\n";

reset_world();
$html = draw( 2, $report, $inst_a, 10 );
ck( 'a member is drawn a form', has( $html, 'name="action" value="wpcpm_save_student"' ), true );
ck( 'keyed to the record it edits', has( $html, 'value="nonce-wpcpm_save_student_' . $report . '"' ), true );
ck( 'carrying the record', has( $html, 'name="record" value="' . $report . '"' ), true );

foreach ( array( 'reports|Name', 'students|Full Name', 'students|Start Date', 'students|End Date', 'students|Your field of study' ) as $slot ) {
	list( $table, $name ) = explode( '|', $slot );
	ck( "the control for $slot is drawn", has( $html, 'name="student[' . k( $table, $name ) . ']"' ), true );
}

ck( 'the dates are date controls', 2, substr_count( $html, 'type="date"' ) );

// **The form is drawn from the institution the FENCE names, not the one the card came from.**
// The handler builds its diff baseline the same way, and the two have to be the same question:
// a form filled from one roster and compared against another counts every cell where the two
// have drifted as a change the reader made, and writes it over the live row on a save nobody
// meant to make. Two rosters that disagree about the same student are seeded here to say so.
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $inst_b ) ] = array(
	'v'    => 1,
	'read' => 1756000000,
	'rows' => array(
		$student => array(
			'record_id'      => $student,
			'name'           => 'Ada Example',
			'email'          => 'ada@example.test',
			'status'         => 'In Sensei',
			'institution'    => $inst_b,
			'start'          => '2026-02-01',
			'end'            => '2030-01-01',
			'field_of_study' => 'Technology & Engineering',
			'user_id'        => 10,
			'reports'        => array( $report ),
		),
	),
);

$other = draw( 2, $report, $inst_b, 10 );

ck( 'drawn from the roster the fence names, whatever roster the card says it came from', array(
	has( $other, 'value="2026-06-30"' ),
	has( $other, 'value="2030-01-01"' ),
), array( true, false ) );

unset( $GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $inst_b ) ] );
ck( 'the values come from the caches', has( $html, 'value="2026-06-30"' ), true );
ck( 'the field of study is a list of the program\'s own choices', has( $html, '<option value="Health &amp; Medicine">' ), true );
ck( 'nothing the student told the program in confidence is on the form', has( $html, 'A disclosure the school never sees' ), false );

$refused = draw( 3, $report, $inst_a, 10 );
ck( 'a member of another institution is drawn nothing at all', $refused, '' );
ck( 'and so is a reader whose student has no report record', draw( 2, '', $inst_a, 10 ), '' );

$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $inst_a ) ]['rows'][ $dupe ] = array(
	'record_id'   => $dupe,
	'name'        => 'Ada Example',
	'email'       => 'ada@example.test',
	'email_key'   => 'ada@example.test',
	'institution' => $inst_a,
	'user_id'     => 0,
);

$twice = draw( 2, $report, $inst_a, 10 );
ck( 'a student on two roster rows has the Students half switched off', 4, substr_count( $twice, 'disabled="disabled"' ) );
ck( 'and is told why', has( $twice, 'A program manager needs to merge them first.' ), true );
ck( 'while the name on the report record is still editable', has( $twice, 'name="student[' . k( 'reports', 'Name' ) . ']" value="Ada E"' ), true );


echo "\n=== The form and the handler compare against the same values ===\n";

reset_world();

// The two caches the form draws from say one thing and the live Students row says another,
// which is the ordinary state of a base people and automations write between two syncs.
$GLOBALS['live_rows'][ $report ]['fields']['Full Name']           = 'Ada Nowak';
$GLOBALS['live_rows'][ $report ]['fields']['End Date']            = '2026-08-15';
$GLOBALS['live_rows'][ $report ]['fields']['Your field of study'] = 'Health & Medicine';
$GLOBALS['report_row']['Name']                                    = 'Ada Nowak';

$posted = drawn_values( draw( 2, $report, $inst_a, 10 ) );

ck( 'the form is drawn from the caches, live row or no live row', $posted, array(
	k( 'reports', 'Name' )                 => 'Ada E',
	k( 'students', 'Full Name' )           => 'Ada Example',
	k( 'students', 'Start Date' )          => '2026-02-01',
	k( 'students', 'End Date' )            => '2026-06-30',
	k( 'students', 'Your field of study' ) => 'Technology & Engineering',
) );

save( 2, $report, $posted );

ck( 'a form posted back untouched writes nothing, however far the live row has moved', $GLOBALS['writes'], array() );
ck( 'and says nothing changed rather than claiming a save', flash_status(), 'student-nothing' );
ck( 'and logs nothing', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );
ck( 'and touches none of the cached copies', array( $GLOBALS['applied'], $GLOBALS['applied_student'] ), array( array(), array() ) );

$posted[ k( 'students', 'End Date' ) ] = '2026-09-30';
save( 2, $report, $posted );

ck( 'the one control the reader did change is the only cell written', writes_to( 'tblSTUDENTS' ), array(
	array( 'table' => 'tblSTUDENTS', 'id' => $student, 'fields' => array( 'End Date' => '2026-09-30' ) ),
) );
ck( 'and nothing goes to the reports row, whose cached Name was posted back as drawn', writes_to( 'tblREPORTS' ), array() );
// The two questions are answered from two places on purpose: what the person changed comes
// from the form, what the write replaced comes from the base.
ck( 'the audit row names the value the write replaced, not the one the form showed', WPCPM_Institution_Audit::entries_for( $inst_a )[0]['data']['changes'], array(
	array( 'field' => 'students|End Date', 'from' => '2026-08-15', 'to' => '2026-09-30' ),
) );

reset_world();
$GLOBALS['live_rows'][ $report ]['fields']['End Date'] = '2026-09-30';
save( 2, $report, array( k( 'students', 'End Date' ) => '2026-09-30' ) );
ck( 'a value corrected to exactly what the base already holds writes nothing', $GLOBALS['writes'], array() );
ck( 'and logs no row saying a value became itself', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );

reset_world();

// The far end of the same rule. Neither cache holds anything for this student yet - a roster
// row the sync has not written and an account whose program row is empty - so every control
// is drawn blank while the live Students row is full. Reading those blanks as answers would
// clear the record on a press that changed nothing.
$GLOBALS['umeta'][10]['wpcpm_student_program'] = array();
WPCPM_Roster_Index::write_all( array( $inst_a => array() ), array(), array( $inst_a => array() ), array(), array(), 1756000000 );

$posted = drawn_values( draw( 2, $report, $inst_a, 10 ) );

ck( 'every control is drawn blank', array_values( array_unique( $posted ) ), array( '' ) );

save( 2, $report, $posted );

ck( 'and pressing Save on them clears nothing', $GLOBALS['writes'], array() );
ck( 'and says nothing changed', flash_status(), 'student-nothing' );


echo "\n=== A stored value the program no longer offers ===\n";

reset_world();
WPCPM_Roster_Index::update( $inst_a, $student, array( 'field_of_study' => 'Underwater Basket Weaving' ) );
$GLOBALS['live_rows'][ $report ]['fields']['Your field of study'] = 'Underwater Basket Weaving';

$html = draw( 2, $report, $inst_a, 10 );

ck( 'it is shown selected and carrying itself, not an empty value', has( $html, '<option value="Underwater Basket Weaving" selected="selected">' ), true );
ck( 'and named for what it is', has( $html, 'not one of the program' ), true );

$posted = drawn_values( $html );
ck( 'so the control posts the stored value back', $posted[ k( 'students', 'Your field of study' ) ], 'Underwater Basket Weaving' );

save( 2, $report, $posted );

ck( 're-saving the card refuses it rather than clearing the column', $GLOBALS['writes'], array() );
ck( 'by name', array( flash_status(), flash_detail() ), array( 'student-rejected', 'Field of study.' ) );
ck( 'and the roster still says what it said', WPCPM_Roster_Index::rows( $inst_a )[ $student ]['field_of_study'], 'Underwater Basket Weaving' );


/* ---- the order, and the file --------------------------------------------- */

echo "\n=== The order is the security ===\n";

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-form.php' );
$handler = substr( $src, (int) strpos( $src, 'public static function handle_save' ) );

ck( 'the nonce is checked before claim() makes a request', before( $handler, 'check_admin_referer', 'WPCPM_Institution_Roster::claim' ), true );
ck( 'the record is read to key the token and nothing happens to it first', before( $handler, "WPCPM_Request::posted_text( 'record' )", 'check_admin_referer' ), true );
ck( 'and the reason is written down', has( $src, 'cross-site POST must not be able to cause one' ), true );
ck( 'claim() comes before anything is read out of the request', before( $handler, 'WPCPM_Institution_Roster::claim', 'self::posted()' ), true );
ck( 'the scope narrows the accepted cells before they are written', before( $handler, 'WPCPM_Institution_Policy::scope', '$airtable->update_records' ), true );
ck( 'the action asked for is the edit action', has( $handler, 'WPCPM_Institution_Policy::ACT_EDIT_STUDENT' ), true );
ck( 'and the claim is on the report record', has( $handler, 'WPCPM_Institution_Roster::TYPE_REPORT' ), true );

ck( 'the walk is over the allowlist and never over what was posted', has( $src, 'foreach ( self::fields() as $table => $columns )' ), true );
ck( 'no institution ID is compared with === in the class', preg_match( '/===\s*\$institution|\$institution\s*===/', $src ), 0 );
ck( 'the withheld column is named nowhere in the class', has( $src, 'Accessibility needs' ), false );

// The Students record is taken from the claim, once, and the second resolution is only ever
// compared with it. An assignment from `students_row_for()` is the bug this pins shut.
ck( 'the record written is read off the claim', has( $handler, '$claim[\'record\'][\'id\']' ), true );
ck( 'and no second resolution is ever assigned to it', preg_match( '/\$students_id\s*=\s*\(string\)\s*\$/', $handler ), 0 );
ck( 'the form and the handler read their values from one method', 2, substr_count( $src, 'self::current(' ) );

/*
 * Every outcome the handler can redirect with has words on the other side.
 *
 * The slugs travel as strings and two of them are built at the call site out of `$blocked`,
 * so a new one costs nothing to add and prints nothing at all when `messages()` has no entry
 * for it - a save that redirects in silence is the one failure a person cannot report.
 */
$slugs = array();

preg_match_all( "/'(student-[a-z-]+)'/", $src, $found );

foreach ( $found[1] as $slug ) {
	$slugs[ $slug ] = true;
}

foreach ( array( 'merge', 'no-row' ) as $blocked ) {
	$slugs[ 'student-' . $blocked ]            = true;
	$slugs[ 'student-' . $blocked . '-only' ] = true;
}

ck( 'every outcome slug the class names has words', array_values( array_diff( array_keys( $slugs ), array_keys( WPCPM_Institution_Student_Form::messages() ) ) ), array() );
ck( 'and there are no words for an outcome nothing can reach', array_values( array_diff( array_keys( WPCPM_Institution_Student_Form::messages() ), array_keys( $slugs ) ) ), array() );

$GLOBALS['hooks'] = array();
WPCPM_Institution_Student_Form::init();
ck( 'the one handler is registered', $GLOBALS['hooks'], array( 'admin_post_wpcpm_save_student' ) );

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-institution-student-form.php',
	'includes/class-wpcpm-field-value.php',
	'includes/class-wpcpm-roster-index.php',
	'bin/test-institution-student-form.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in any of the four files', $dashes, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );

/**
 * `wp_json_encode()` under another name, so the stub list above stays a list of WordPress.
 */
function wp_json_encode_compat( $value ) {
	return (string) json_encode( $value );
}
