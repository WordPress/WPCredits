<?php
/**
 * Graduate and withdraw, and the three guards on the one write nobody can take back.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **Two states, and the two are the whole list.** `states()` offers `Graduate` and
 *   `Dropped out`. `Paused` and `Pending graduation` are tracked statuses and are still not
 *   offered, because pausing and holding at pending graduation are the program's calls; a POST
 *   naming either changes nothing and, because guard 1 runs before `claim()`, costs no Airtable
 *   request at all. A guard that only refused after the fetch would be a way of making this site
 *   fetch on demand.
 * - The nonce is checked **before** `claim()`, because `claim()` makes an HTTP request on this
 *   site's Airtable credentials and a cross-site POST must not be able to cause one. The token is
 *   keyed to the record **and** the state, so a token for graduating somebody is not a token for
 *   dropping them out.
 * - **The row is read live and has to agree**, in `WPCPM_Mentor_Checker_Runner::promote()`'s
 *   shape: already the target leaves it unchanged, a status that is not tracked and active is
 *   refused naming what the row says, and a roster index that says `In Sensei` does not let a
 *   press through when the base now says something else. The index is a day-old cache; the
 *   decision is about what the base says at the moment of the press.
 * - **A `Paused` row refuses, by name.** The mirror automation is restricted to view
 *   `viwzSJspvACLnhXom` and `Paused` is not in it, so a Paused row marked `Graduate` would mail
 *   the student while the reports row, the account and the mentor's list all still said Paused.
 *   The refusal names the view, before the press on the card and after it in the message, and it
 *   fires even on a site whose settings have lost `Paused` from the tracked list.
 * - **The write reaches the Students table and nothing else**, one cell, on the record `claim()`
 *   proved rather than the one the form named. The Students Reports row carries a `Status` of its
 *   own and is not written: one authority per field, and the automation is what carries it across.
 * - **The confirm names the mail.** It names the student, says Airtable will email them, says the
 *   mail cannot be recalled, and says a program manager is the only way back - because there is
 *   no un-graduate on this screen and somebody who has just pressed it will look for one.
 * - One audit row, naming the actor, both values, the `member` ground and `live` evidence, filed
 *   under the institution the decision named. A change that could not be logged does not happen;
 *   a log that would not insert is said out loud rather than swallowed.
 * - The roster index and the Student Report Card's transient are dropped, so the school's own
 *   list stops calling a graduate a current student before any sync runs.
 *
 * Run from the plugin root:  php bin/test-institution-graduate.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']      = array( 'date_format' => 'Y-m-d' );
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['uid']       = 0;
$GLOBALS['manage']    = array();
$GLOBALS['members']   = array();
$GLOBALS['settled']   = array();
$GLOBALS['http']      = array();
$GLOBALS['writes']    = array();
$GLOBALS['refuse']    = array();
$GLOBALS['live_rows'] = array();
$GLOBALS['forgot']    = array();
$GLOBALS['referer']   = array();
$GLOBALS['no_insert'] = false;
// The tracked active statuses, as `WPCPM_Settings::defaults()` ships them. Held in a global so
// one block can take `Paused` back out and prove the paused refusal does not depend on it.
$GLOBALS['active'] = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' );

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
function wp_json_encode( $v ) { return json_encode( $v ); }
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
/**
 * Inserts, unless the suite has asked for a post that will not insert.
 *
 * The refusal is what proves the handler says so out loud: the change is already in Airtable and
 * the student has already been emailed, so "Saved." over a row nobody can be held to is the one
 * sentence the log exists to prevent.
 */
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	if ( $GLOBALS['no_insert'] ) { return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ); }
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
		/** Only the keys this class reaches for; the real map is much longer. */
		public static function fields() {
			return array(
				'report_status'       => 'Status',
				'student_record_name' => 'Full Name',
				'student_status'      => 'Status',
				'student_institution' => 'Educational Institutions',
			);
		}
		/** Contract: the tracked lists, built from the settings both syncs page from. */
		public static function tracked_statuses( $settings = null ) {
			$past = array( 'Graduate', 'Dropped out' );
			return array(
				'active' => $GLOBALS['active'],
				'past'   => array_values( array_diff( $past, $GLOBALS['active'] ) ),
				'all'    => array_merge( $GLOBALS['active'], array_diff( $past, $GLOBALS['active'] ) ),
			);
		}
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	class WPCPM_Students_Sync {
		const META_RECORD_ID   = 'wpcpm_student_record_id';
		const META_PROGRAM     = 'wpcpm_student_program';
		const META_INSTITUTION = 'wpcpm_student_institution';
		public static function user_for_record( $record_id ) {
			$users = get_users( array( 'meta_key' => self::META_RECORD_ID, 'meta_value' => trim( (string) $record_id ) ) );
			return $users ? $users[0] : null;
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
	/** Contract: the Student Report Card's cache, and the forget this write has to call. */
	class WPCPM_Student_Report_Form {
		public static function forget( $record ) { $GLOBALS['forgot'][] = trim( (string) $record ); }
	}
}
if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/**
	 * The client, recording every request it is asked to make.
	 *
	 * `flatten()` behaves as the real one does, because guard 2 reads the live `Status` through
	 * it and a single select can come back as a string or as a choice object.
	 */
	class WPCPM_Airtable {
		public function __construct( array $settings = array() ) {}
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
	 * Step 3 is where the HTTP request happens, and it is recorded: "guard 1 refuses before
	 * anything reaches the network" is an assertion below, and a stub that read the fixture before
	 * deciding would pass it for a handler that did not.
	 *
	 * `cached_subject()` walks the rosters the counts option names, exactly as the shipped one
	 * does, because the card is drawn from that answer and the handler's cheap step is the same
	 * call: a stub that answered more generously would draw controls the handler refuses.
	 */
	class WPCPM_Institution_Roster {
		const ARG_VIEW            = 'wpcpm_institution_view';
		const TYPE_STUDENT        = 'student';
		const TYPE_REPORT         = 'report';
		const FIELD_INSTITUTIONS  = 'Educational Institutions';
		const FIELD_STUDENTS_LINK = 'Students';
		const FIELD_EMAIL         = 'Email';

		public static function cached_subject( $record, $type ) {
			$record = trim( (string) $record );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
				return WPCPM_Institution_Policy::subject_index_row( '', $record );
			}

			foreach ( array_keys( WPCPM_Roster_Index::counts()['institutions'] ) as $institution ) {
				$rows = WPCPM_Roster_Index::rows( $institution );

				if ( isset( $rows[ $record ] ) ) {
					return WPCPM_Institution_Policy::subject_index_row( $institution, $record );
				}
			}

			return WPCPM_Institution_Policy::subject_index_row( '', $record );
		}

		public static function claim( $record, $action, $type = self::TYPE_STUDENT, $user = null ) {
			$record = trim( (string) $record );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! in_array( $type, array( self::TYPE_STUDENT, self::TYPE_REPORT ), true ) ) {
				return WPCPM_Institution_Policy::refusal();
			}

			if ( empty( WPCPM_Institution_Policy::decide( $action, self::cached_subject( $record, $type ), $user )['allowed'] ) ) {
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

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-students.php';

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
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }
/** Whether one call comes before another, with both of them present: absence answers no. */
function before( $src, $first, $second ) {
	$a = strpos( (string) $src, $first );
	$b = strpos( (string) $src, $second );
	return false !== $a && false !== $b && $a < $b;
}
/** The body of one method, by brace depth: enough to read the order of two calls. */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}
	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	$length = strlen( $source );
	while ( $end < $length && $depth > 0 ) {
		if ( '{' === $source[ $end ] ) { ++$depth; } elseif ( '}' === $source[ $end ] ) { --$depth; }
		++$end;
	}
	return substr( $source, $offset, $end - $offset - 1 );
}
/** A well-formed Airtable record ID from a short name. */
function rid( $name ) { return 'rec' . str_pad( $name, 14, '0' ); }
function flash() { return $GLOBALS['umeta'][ $GLOBALS['uid'] ]['wpcpm_flash']['institution_student_status'] ?? null; }
function flash_outcome() { $f = flash(); return is_array( $f ) ? $f['outcome'] : ''; }
function flash_detail() { $f = flash(); return is_array( $f ) ? $f['detail'] : ''; }
function writes_to( $table ) {
	$out = array();
	foreach ( $GLOBALS['writes'] as $write ) { if ( $table === $write['table'] ) { $out[] = $write; } }
	return $out;
}
/** Which tables this press wrote to, in order, so "the Students table and nothing else" is one check. */
function tables_written() {
	$out = array();
	foreach ( $GLOBALS['writes'] as $write ) { $out[] = $write['table']; }
	return $out;
}

$inst_a  = rid( 'INSTA' );
$inst_b  = rid( 'INSTB' );
$student = rid( 'STUDENT' );
$report  = rid( 'REPORT' );
$other   = rid( 'OTHER' );

/**
 * One world: institution A with one student on `In Sensei`, a member each side, one manager.
 *
 * @param string $status What the live Students row says. The roster index says `In Sensei`
 *                       whatever this is, which is how the suite tells the cache from the base.
 */
function reset_world( $status = 'In Sensei' ) {
	global $inst_a, $inst_b, $student, $report;

	$GLOBALS['opts']      = array();
	$GLOBALS['umeta']     = array();
	$GLOBALS['posts']     = array();
	$GLOBALS['pmeta']     = array();
	$GLOBALS['http']      = array();
	$GLOBALS['writes']    = array();
	$GLOBALS['refuse']    = array();
	$GLOBALS['forgot']    = array();
	$GLOBALS['referer']   = array();
	$GLOBALS['no_insert'] = false;
	$GLOBALS['manage']    = array( 1 );
	$GLOBALS['settled']   = array( $inst_a, $inst_b );
	$GLOBALS['active']    = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' );

	$GLOBALS['users'] = array(
		1  => new WP_User( 1, 'A Manager', 'manager@example.test' ),
		2  => new WP_User( 2, 'A Member', 'a@example.test' ),
		3  => new WP_User( 3, 'B Member', 'b@example.test' ),
		4  => new WP_User( 4, 'A Colleague', 'c@example.test' ),
		10 => new WP_User( 10, 'Anna Nowak', 'anna@example.test' ),
	);

	$GLOBALS['members'] = array(
		2 => array( $inst_a ),
		3 => array( $inst_b ),
		4 => array( $inst_a ),
	);

	$GLOBALS['umeta'][10] = array(
		'wpcpm_student_record_id'   => $report,
		'wpcpm_student_institution' => $inst_a,
	);

	set_live( $status );

	WPCPM_Roster_Index::write_all(
		array(
			$inst_a => array(
				array(
					'record_id'   => $student,
					'name'        => 'Anna Nowak',
					'email'       => 'anna@example.test',
					'status'      => 'In Sensei',
					'institution' => $inst_a,
					'start'       => '2026-02-01',
					'end'         => '2026-06-30',
					'user_id'     => 10,
					'reports'     => array( $report ),
				),
			),
		),
		array(),
		array( $inst_a => array() ),
		array(),
		1756000000
	);
}

/**
 * What `claim()` hands back: the live Students row, cut to the disclosed columns.
 *
 * @param string $status      The row's `Status` right now.
 * @param string $institution The link it carries; '' for a row that has lost it.
 * @param string $id          The record ID the claim proves, when it is not the one asked for.
 */
function set_live( $status, $institution = null, $id = null ) {
	global $inst_a, $student;

	$link = null === $institution ? array( $inst_a ) : ( '' === $institution ? array() : array( $institution ) );

	$GLOBALS['live_rows'] = array(
		$student => array(
			'id'     => null === $id ? $student : $id,
			'fields' => array(
				'Educational Institutions' => $link,
				'Full Name'                => 'Anna Nowak',
				'Status'                   => $status,
			),
		),
	);
}

/** Press one button as one user and report how the handler ended. */
function press( $viewer, $record, $state ) {
	$GLOBALS['uid']    = (int) $viewer;
	$GLOBALS['http']   = array();
	$GLOBALS['writes'] = array();
	unset( $GLOBALS['umeta'][ (int) $viewer ]['wpcpm_flash'] );
	$_POST             = array(
		'action' => WPCPM_Institution_Students::ACTION_CHANGE,
		'record' => $record,
		'state'  => $state,
	);

	try {
		WPCPM_Institution_Students::handle_change();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}

	return '';
}

/** Draw the card as one reader. */
function draw( $viewer, $record, array $context = array() ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Students::render_form( $record, $context );
	return (string) ob_get_clean();
}

echo "=== Guard 1: two states, and the two are the whole list ===\n";

reset_world();

$states = WPCPM_Institution_Students::states();

// The map IS the list. A third entry here is a third button and a third branch, so it is worth a
// failing assertion rather than a code review somebody might not get to.
ck( 'exactly two states are offered', count( $states ), 2 );
ck( 'graduated writes the base\'s own Graduate', $states['graduated'], 'Graduate' );
ck( 'withdrawn writes the base\'s own Dropped out', $states['withdrawn'], 'Dropped out' );

// Both are tracked statuses since decision 21, so their absence is a decision about who gets to
// make the call and not a leftover from the first design's role-loss reasoning.
ck( 'Paused is not a state a school may set', in_array( 'Paused', $states, true ), false );
ck( 'Pending graduation is not one either', in_array( 'Pending graduation', $states, true ), false );

ck( 'every state has a label', array_keys( WPCPM_Institution_Students::labels() ), array_keys( $states ) );
ck( 'and an audit kind', array_keys( WPCPM_Institution_Students::kinds() ), array_keys( $states ) );

// The column is read through the map the syncs read, so renaming it in the base is one edit.
ck( 'the column written is the Students table\'s Status', WPCPM_Institution_Students::column(), 'Status' );

$ended = press( 2, $student, 'paused' );

ck( 'pressing a hand-made Paused writes nothing', $GLOBALS['writes'], array() );
// Guard 1 runs before `claim()`, so a forged state cannot be used to make this site fetch.
ck( 'and costs no Airtable request at all', $GLOBALS['http'], array() );
ck( 'and says so', flash_outcome(), 'status-unknown' );
ck( 'and it redirected rather than dying', has( $ended, 'redirect:' ), true );

press( 2, $student, 'pending_graduation' );
ck( 'a hand-made Pending graduation is refused the same way', flash_outcome(), 'status-unknown' );
ck( 'and reaches no network either', $GLOBALS['http'], array() );

press( 2, $student, '' );
ck( 'an empty state is refused', flash_outcome(), 'status-unknown' );

echo "\n=== Guard 2: the row is read live, and has to agree ===\n";

reset_world( 'In Sensei' );
press( 2, $student, 'graduated' );

ck( 'a current student graduates', flash_outcome(), 'status-graduated' );
ck( 'one write, and one only', count( $GLOBALS['writes'] ), 1 );
// One authority per field: the Students Reports row carries a Status of its own and automation
// wflUYImI8OEvVuc4R is what carries this value across to it.
ck( 'to the Students table and nothing else', tables_written(), array( 'tblSTUDENTS' ) );
ck( 'nothing reached the reports table', writes_to( 'tblREPORTS' ), array() );
ck( 'one cell, named by the base\'s own column', $GLOBALS['writes'][0]['fields'], array( 'Status' => 'Graduate' ) );
ck( 'on the record the claim proved', $GLOBALS['writes'][0]['id'], $student );

reset_world( 'In Sensei' );
press( 2, $student, 'withdrawn' );

ck( 'a current student can be dropped out', flash_outcome(), 'status-withdrawn' );
ck( 'and that write is Dropped out', $GLOBALS['writes'][0]['fields'], array( 'Status' => 'Dropped out' ) );

// The record written is the claim's, never the form's. The claim is the only step that read the
// row live and decided on the link it carries, so its ID is the only one this request holds an
// authorisation for.
reset_world( 'In Sensei' );
set_live( 'In Sensei', null, $other );
press( 2, $student, 'graduated' );

ck( 'the write names the record the claim returned, not the one posted', $GLOBALS['writes'][0]['id'], $other );

reset_world( 'Graduate' );
press( 2, $student, 'graduated' );

// promote()'s order: the target is tested first, so a row that already says Graduate is left
// alone rather than falling through and being told it is not on the program.
ck( 'a row that already says Graduate is left unchanged', flash_outcome(), WPCPM_Institution_Students::OUT_ALREADY );
ck( 'and nothing is written', $GLOBALS['writes'], array() );
ck( 'the claim still happened, because only a live read can say so', count( $GLOBALS['http'] ), 1 );

reset_world( 'Graduate' );
press( 2, $student, 'withdrawn' );

// There is no un-graduate on a school's screen, and this is the same rule read from the other
// end: a finished status is not a tracked active one, so nothing moves off it here.
ck( 'a graduate cannot then be dropped out from this screen', flash_outcome(), WPCPM_Institution_Students::OUT_UNTRACKED );
ck( 'and the refusal carries what the row says', flash_detail(), 'Graduate' );
ck( 'and nothing is written', $GLOBALS['writes'], array() );

reset_world( 'Not moving forward' );
press( 2, $student, 'graduated' );

ck( 'a row that is not on the program is refused', flash_outcome(), WPCPM_Institution_Students::OUT_UNTRACKED );
ck( 'naming what the row says', flash_detail(), 'Not moving forward' );

reset_world( '' );
press( 2, $student, 'graduated' );

ck( 'a row with no status at all is refused too', flash_outcome(), WPCPM_Institution_Students::OUT_UNTRACKED );
ck( 'and there is nothing to quote', flash_detail(), '' );

// **The re-read is the point.** The roster index still says In Sensei, because reset_world()
// always writes that; the base says otherwise, and the base is what decides.
reset_world( 'Not moving forward' );
ck( 'the index the button was drawn from still says In Sensei',
    WPCPM_Roster_Index::rows( $inst_a )[ $student ]['status'], 'In Sensei' );
press( 2, $student, 'graduated' );
ck( 'and the live row is what refuses, not the cache', flash_outcome(), WPCPM_Institution_Students::OUT_UNTRACKED );

// A single select can come back as a choice object rather than a string.
reset_world( 'In Sensei' );
$GLOBALS['live_rows'][ $student ]['fields']['Status'] = array( 'name' => 'Graduate' );
press( 2, $student, 'graduated' );
ck( 'a status read as a choice object is still read', flash_outcome(), WPCPM_Institution_Students::OUT_ALREADY );

echo "\n=== Guard 2: a Paused row refuses, by name ===\n";

reset_world( 'Paused' );
press( 2, $student, 'graduated' );

ck( 'a paused student cannot be graduated', flash_outcome(), WPCPM_Institution_Students::OUT_PAUSED );
ck( 'and nothing is written', $GLOBALS['writes'], array() );

$paused = WPCPM_Institution_Students::messages()[ WPCPM_Institution_Students::OUT_PAUSED ][1];

// The view is the whole of the reason, and the person who can fix it needs its name to ask.
ck( 'the refusal names the view the mirror automation is restricted to', has( $paused, 'viwzSJspvACLnhXom' ), true );
ck( 'and the constant is that view', WPCPM_Institution_Students::MIRROR_VIEW, 'viwzSJspvACLnhXom' );
ck( 'and it says the student would be emailed anyway', has( $paused, 'email' ), true );
ck( 'and it says what to ask for', has( $paused, 'off pause' ), true );

reset_world( 'Paused' );
press( 2, $student, 'withdrawn' );
ck( 'a paused student cannot be dropped out either', flash_outcome(), WPCPM_Institution_Students::OUT_PAUSED );

// The paused test runs before the tracked-status test on purpose. A site whose saved
// student_statuses predates WPCPM_Settings::maybe_upgrade() has no Paused in it, and a paused row
// there must still be told it is paused rather than told to go and ask about something else.
$GLOBALS['active'] = array( 'In Sensei', 'In Sensei 50h', 'Developer Track' );
ck( 'Paused refuses by name even when the settings have lost it',
    WPCPM_Institution_Students::check( 'graduated', 'Paused' )['outcome'], WPCPM_Institution_Students::OUT_PAUSED );

// And the tracked list is genuinely read, rather than a second copy of the shipped default.
$GLOBALS['active'] = array( 'Research Track' );
ck( 'a status the settings call active can be graduated',
    WPCPM_Institution_Students::check( 'graduated', 'Research Track' )['write'], true );
ck( 'and one they no longer call active cannot',
    WPCPM_Institution_Students::check( 'graduated', 'In Sensei' )['write'], false );

$GLOBALS['active'] = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' );

// check() answers a state it does not know rather than throwing, because it is public and the
// renderer walks states() through it.
ck( 'check() refuses a state that is not on the list',
    WPCPM_Institution_Students::check( 'expelled', 'In Sensei' )['write'], false );

echo "\n=== Guard 3: the confirm names the mail ===\n";

$confirm = WPCPM_Institution_Students::confirm( 'graduated', 'Anna Nowak' );

ck( 'the confirm names the student', has( $confirm, 'Anna Nowak' ), true );
ck( 'and says an email is going out', has( $confirm, 'email' ), true );
ck( 'and names it as the certificate notice', has( $confirm, 'certificate notice' ), true );
ck( 'and says it cannot be recalled', has( $confirm, 'cannot be recalled' ), true );
ck( 'and says the change is logged under your name', has( $confirm, 'logged under your name' ), true );
// There is no un-graduate control, so the dialog says where the way back is rather than leaving
// somebody hunting the card for one.
ck( 'and says a program manager is the way back', has( $confirm, 'program manager' ), true );

$dropped = WPCPM_Institution_Students::confirm( 'withdrawn', 'Anna Nowak' );

ck( 'the dropped-out confirm names the student too', has( $dropped, 'Anna Nowak' ), true );
ck( 'and names its own mail', has( $dropped, 'email' ), true );
ck( 'and says it cannot be recalled', has( $dropped, 'cannot be recalled' ), true );

// No pronoun is guessed: the program's records hold none, and a dialog that picked one would be
// wrong about somebody on every roster.
ck( 'no pronoun is invented', has( $confirm, ' her ' ) || has( $confirm, ' him ' ), false );

ck( 'a state that is not offered has no dialog', WPCPM_Institution_Students::confirm( 'paused', 'Anna Nowak' ), '' );
ck( 'and a nameless student is still named something',
    has( WPCPM_Institution_Students::confirm( 'graduated', '' ), 'this student' ), true );

echo "\n=== The card ===\n";

reset_world( 'In Sensei' );
$html = draw( 2, $student, array( 'name' => 'Anna Nowak' ) );

ck( 'a member sees both controls', substr_count( $html, '<form' ), 2 );
ck( 'the graduate button is there', has( $html, 'Mark as graduated' ), true );
ck( 'and the dropped out button', has( $html, 'Mark as dropped out' ), true );
ck( 'each carries its confirm', substr_count( $html, 'onclick="return confirm(' ), 2 );
ck( 'and the confirm on the card names the student', has( $html, 'Anna Nowak' ), true );
// The nonce is keyed to the record and the state together, so a token for graduating somebody is
// not a token for dropping them out.
ck( 'the graduate nonce names the record and the state', has( $html, 'nonce-wpcpm_change_student_status_' . $student . '_graduated' ), true );
ck( 'and the withdraw nonce names its own', has( $html, 'nonce-wpcpm_change_student_status_' . $student . '_withdrawn' ), true );
ck( 'the card says nothing here undoes it', has( $html, 'Nothing on this screen puts it back' ), true );

// Open question 4 in as many words: an institution cannot graduate a paused student, and the
// roster says so on the row rather than failing silently after a press.
reset_world( 'In Sensei' );
WPCPM_Roster_Index::update( $inst_a, $student, array( 'status' => 'Paused' ) );
$html = draw( 2, $student, array( 'name' => 'Anna Nowak' ) );

ck( 'a paused row draws no control at all', has( $html, '<form' ), false );
ck( 'and says so before anybody presses anything', has( $html, 'paused' ), true );
ck( 'naming the view', has( $html, 'viwzSJspvACLnhXom' ), true );

reset_world( 'In Sensei' );
WPCPM_Roster_Index::update( $inst_a, $student, array( 'status' => 'Graduate' ) );
$html = draw( 2, $student, array( 'name' => 'Anna Nowak' ) );

ck( 'a finished row draws no control', has( $html, '<form' ), false );
ck( 'and says the placement is already recorded as finished', has( $html, 'already recorded as finished' ), true );
ck( 'and quotes what the records say', has( $html, 'They say: Graduate.' ), true );
ck( 'and says who changes it', has( $html, 'A program manager changes it' ), true );

reset_world( 'In Sensei' );
WPCPM_Roster_Index::update( $inst_a, $student, array( 'status' => 'Not moving forward' ) );
$html = draw( 2, $student, array( 'name' => 'Anna Nowak' ) );

ck( 'a row that is not on the program draws no control', has( $html, '<form' ), false );
ck( 'and quotes what the records say', has( $html, 'They say: Not moving forward.' ), true );

reset_world( 'In Sensei' );

// The first call pattern: a refused reader is shown the card they came from, never a disabled
// control telling them what they may not do.
ck( 'a member of another institution sees nothing at all', draw( 3, $student, array() ), '' );
ck( 'and neither does a stranger', draw( 10, $student, array() ), '' );
ck( 'a record the site has never seen draws nothing', draw( 2, rid( 'GHOST' ), array() ), '' );
ck( 'and neither does a pasted string', draw( 2, 'not-a-record', array() ), '' );

// A manager acting through the switcher gets the same controls: the manager ground is first on
// every row of the policy's map, and this is a manager's screen too.
ck( 'a program manager sees the controls', substr_count( draw( 1, $student, array() ), '<form' ), 2 );

// The name falls back to the roster row rather than to a record ID, because a dialog that asked
// "Mark recSTUDENT0000000 as graduated?" is a dialog nobody can answer.
ck( 'with no name in the context the roster row supplies one',
    has( draw( 2, $student, array() ), 'Mark Anna Nowak as graduated?' ), true );

echo "\n=== The fence ===\n";

reset_world( 'In Sensei' );
press( 3, $student, 'graduated' );

ck( 'a member of another institution gets the one refusal', flash_outcome(), 'status-refused' );
ck( 'and nothing is written', $GLOBALS['writes'], array() );
// The cheap decision refuses first, so a stranger cannot make this site fetch from Airtable.
ck( 'and no HTTP happened at all', $GLOBALS['http'], array() );

$refusal = WPCPM_Institution_Students::messages()['status-refused'][1];
ck( 'the refusal is the module\'s one refusal, byte for byte',
    $refusal, WPCPM_Institution_Policy::refusal()->get_error_message() );

reset_world( 'In Sensei' );
$GLOBALS['live_rows'][ $student ] = new WP_Error( 'wpcpm_airtable_http', 'The base could not be reached.' );
press( 2, $student, 'graduated' );

// "That record is not on your roster" because Airtable timed out sends somebody looking for a
// permissions fault that does not exist.
ck( 'a base that could not be read says so, and does not accuse the reader', flash_outcome(), 'status-unreadable' );
ck( 'and writes nothing', $GLOBALS['writes'], array() );

// A live row that has lost its institution link is a manager-ground pass with nowhere to file
// the audit row, and this change mails a student: it does not happen at all.
reset_world( 'In Sensei' );
set_live( 'In Sensei', '' );
press( 1, $student, 'graduated' );

ck( 'a row with no institution refuses even for a manager', flash_outcome(), 'status-unfiled' );
ck( 'and writes nothing', $GLOBALS['writes'], array() );
ck( 'and logs nothing', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );

echo "\n=== The order, read off the source ===\n";

$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-students.php' );
$body   = method_body( $source, 'handle_change' );

ck( 'handle_change() was found', is_string( $body ), true );
// `claim()` makes an HTTP request on this site's Airtable credentials.
ck( 'the nonce is checked before claim()', before( $body, 'check_admin_referer', 'WPCPM_Institution_Roster::claim' ), true );

// **Which nonce, not only that there is one.** The header and `render_button()` both promise
// that a token for graduating somebody is not a token for dropping them out, nor a token for
// anybody else: the key carries the record and the state. Reading only `check_admin_referer`'s
// presence left that unpinned, and a reviewer proved it by shortening the key to the bare
// action and watching every check still pass.
$GLOBALS['referer'] = array();
press( 2, $student, 'graduated' );
ck( 'the nonce names the student and the state', $GLOBALS['referer'], array( WPCPM_Institution_Students::ACTION_CHANGE . '_' . $student . '_graduated' ) );

$GLOBALS['referer'] = array();
press( 2, $student, 'withdrawn' );
ck( 'and a different state is a different token', $GLOBALS['referer'], array( WPCPM_Institution_Students::ACTION_CHANGE . '_' . $student . '_withdrawn' ) );
ck( 'guard 1 runs before claim() too', before( $body, 'isset( $states[ $state ] )', 'WPCPM_Institution_Roster::claim' ), true );
ck( 'the claim comes before the write', before( $body, 'WPCPM_Institution_Roster::claim', 'update_records' ), true );
ck( 'and guard 2 comes before the write', before( $body, 'self::check(', 'update_records' ), true );
// The row records what landed rather than what was attempted, which is why it is written after
// the base has answered and not before the request.
ck( 'the audit row records what landed', before( $body, 'update_records', 'self::log(' ), true );
// The last word in the method is the outcome, so nothing can report a saved change on a path
// that never tried to write the row saying who saved it.
ck( 'and nothing reports an outcome before the log has been tried', strrpos( $body, 'self::bounce(' ) > strpos( $body, 'self::log(' ), true );

echo "\n=== The audit row ===\n";

reset_world( 'In Sensei' );
press( 2, $student, 'graduated' );

$entries = WPCPM_Institution_Audit::entries_for( $inst_a );

ck( 'one row, and one only', count( $entries ), 1 );
ck( 'filed under the institution the decision named', $entries[0]['institution'], $inst_a );
ck( 'about the Students record that was written', $entries[0]['subject'], $student );
ck( 'naming the person who pressed it', $entries[0]['actor'], 2 );
ck( 'on the member ground', $entries[0]['ground'], WPCPM_Institution_Audit::GROUND_MEMBER );
// The decision that allowed this was made against a row read live, and the log says so: "who was
// allowed to, and on what basis" is the question this log exists to answer.
ck( 'against live evidence', $entries[0]['evidence'], WPCPM_Institution_Audit::EVIDENCE_LIVE );
ck( 'under its own kind', $entries[0]['kind'], WPCPM_Institution_Students::KIND_GRADUATED );
ck( 'saying both values', $entries[0]['message'], 'Status: In Sensei to Graduate.' );
ck( 'and carrying them as facts', $entries[0]['data']['from'] . ' -> ' . $entries[0]['data']['to'], 'In Sensei -> Graduate' );
ck( 'and the column it wrote', $entries[0]['data']['field'], 'Status' );

reset_world( 'In Sensei' );
press( 2, $student, 'withdrawn' );
ck( 'dropping out is its own kind', WPCPM_Institution_Audit::entries_for( $inst_a )[0]['kind'], WPCPM_Institution_Students::KIND_WITHDRAWN );

reset_world( 'In Sensei' );
press( 1, $student, 'graduated' );
// The manager ground is first on every row of the policy's map, so a manager who is also a member
// is logged as a manager: that is what the audit log needs to read.
ck( 'a manager\'s press is logged as a manager\'s', WPCPM_Institution_Audit::entries_for( $inst_a )[0]['ground'], WPCPM_Institution_Audit::GROUND_MANAGER );

reset_world( 'In Sensei' );
$GLOBALS['no_insert'] = true;
press( 2, $student, 'graduated' );

// The change is in the base and the student has already been emailed. "Saved." over a change
// nobody can be held to is the sentence the log exists to prevent.
ck( 'a log that would not insert is said out loud', flash_outcome(), 'status-unlogged' );
ck( 'and the write still happened, because it did', count( $GLOBALS['writes'] ), 1 );

echo "\n=== Airtable refusing the write ===\n";

reset_world( 'In Sensei' );
$GLOBALS['refuse'] = array( 'tblSTUDENTS' );
press( 2, $student, 'graduated' );

ck( 'a refused write says so', flash_outcome(), 'status-airtable' );
// A row saying the site wrote a value it did not write is a false one.
ck( 'and nothing is logged', WPCPM_Institution_Audit::entries_for( $inst_a ), array() );
ck( 'and the roster is not moved on', WPCPM_Roster_Index::rows( $inst_a )[ $student ]['status'], 'In Sensei' );

echo "\n=== The caches ===\n";

reset_world( 'In Sensei' );
press( 2, $student, 'graduated' );

// The roster index is the Students table as the last sync read it, and it is what every
// institution-side surface counts and prints: without this the row stays under "current
// students" until tomorrow's sync.
ck( 'the school\'s own roster shows the change now', WPCPM_Roster_Index::rows( $inst_a )[ $student ]['status'], 'Graduate' );
ck( 'the Student Report Card cache is dropped for the report row', $GLOBALS['forgot'], array( $report ) );
ck( 'and the read time is left where it was', WPCPM_Roster_Index::read( $inst_a )['read'], 1756000000 );

reset_world( 'In Sensei' );
$GLOBALS['live_rows'][ $student ]['fields']['Status'] = 'Paused';
press( 2, $student, 'graduated' );

ck( 'a refused press invalidates nothing', $GLOBALS['forgot'], array() );
ck( 'and leaves the roster where it was', WPCPM_Roster_Index::rows( $inst_a )[ $student ]['status'], 'In Sensei' );

echo "\n=== What the outcomes say ===\n";

$messages = WPCPM_Institution_Students::messages();
$notes    = WPCPM_Institution_Students::notes();

foreach ( array( WPCPM_Institution_Students::OUT_ALREADY, WPCPM_Institution_Students::OUT_PAUSED, WPCPM_Institution_Students::OUT_UNTRACKED ) as $outcome ) {
	// Two vocabularies, one key set: an outcome with a message and no note is a card that draws
	// no button and gives no reason, which is the silent failure open question 4 rules out.
	ck( "the outcome $outcome has a message", isset( $messages[ $outcome ] ), true );
	ck( "and a sentence for the card", isset( $notes[ $outcome ] ), true );
}

foreach ( array_keys( $states ) as $state ) {
	ck( "a press that lands on $state has its own sentence", isset( $messages[ 'status-' . $state ] ), true );
}

ck( 'the graduate message says the certificate notice is on its way',
    has( $messages['status-graduated'][1], 'certificate notice' ), true );
ck( 'and that it cannot be recalled from here', has( $messages['status-graduated'][1], 'cannot be recalled from here' ), true );
ck( 'and who changes it back', has( $messages['status-graduated'][1], 'A program manager changes it' ), true );
ck( 'the dropped out message says a notice went out', has( $messages['status-withdrawn'][1], 'their own notice' ), true );

// The outcome slugs go through sanitize_key() on the way back out of the flash, so one that did
// not survive it would print no sentence at all - a redirect in silence is the one failure a
// person at the screen cannot report.
$survives = true;
foreach ( array_keys( $messages ) as $outcome ) {
	if ( sanitize_key( $outcome ) !== $outcome ) { $survives = false; }
}
ck( 'every outcome slug survives sanitize_key()', $survives, true );

reset_world( 'In Sensei' );
press( 4, $student, 'graduated' );
$html = draw( 4, $student, array( 'name' => 'Anna Nowak' ) );

ck( 'the outcome prints on the card it happened to', has( $html, 'recorded as graduated' ), true );

$GLOBALS['uid'] = 4;
ck( 'and the flash is cleared by printing it', flash(), null );

echo "\n=== House rules ===\n";

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-institution-students.php',
	'bin/test-institution-graduate.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either file', $dashes, array() );

$GLOBALS['hooks'] = array();
WPCPM_Institution_Students::init();
ck( 'the one handler is registered', $GLOBALS['hooks'], array( 'admin_post_wpcpm_change_student_status' ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
