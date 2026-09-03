<?php
/**
 * The unlinked-row link control on the manager reconciliation card.
 *
 * Three Students rows name no institution. A program manager links one to an institution from
 * this card, and no institution ever does: the import collapses every hit outside a school's
 * own roster into one neutral refusal precisely so that a school cannot walk the base address
 * by address, and a self-service adopt control would hand it that back.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The write this control makes is what fires an automation.** `Add students to Students
 *   Reports and Feedback` creates a Students Reports row and a Feedback row as soon as a
 *   Students row holds a name, an address, an institution link and a mentor at one of four
 *   statuses. A row that already has the other three is one write away from firing it, and
 *   that write is this link. So a row carrying a mentor at one of those statuses is refused,
 *   and so is a row whose address already has a Students Reports row. Those two refusals are
 *   the whole of why this control is a manager's and not a school's.
 * - Both are decided against a **live** read. `wpcpm_roster_unlinked` is as old as the last
 *   sync, and a mentor assigned in the base an hour ago is exactly the case that turns a row
 *   the card offered into a row the handler must refuse.
 * - A refused row is not written: no PATCH, no roster row, no audit entry, nothing.
 * - One rule, asked twice. The card asks it of the index row so it does not offer a control
 *   that would be refused; the handler asks the same function of the live record. Written
 *   once, so the two cannot drift into telling a manager different things about one row.
 * - The handler runs the capability, then `check_admin_referer()`, then
 *   `WPCPM_Institution_Policy::decide()` (spec 5.4), and the order assertions fail when one of
 *   the three is missing rather than passing on `false < 12`.
 * - The nonce is keyed to the Students row, so a token taken from the control of a row the
 *   site is willing to link is not a token for the row beside it.
 * - Airtable failing to answer is not "there is no second row": every read failure ends with
 *   nothing written.
 * - The PATCH carries the institution link and no other cell. Every other cell on that row is
 *   somebody else's writing.
 *
 * Run from the plugin root:  php bin/test-unlinked-link.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array();
$GLOBALS['umeta']    = array();
$GLOBALS['users']    = array();
$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['uid']      = 0;
$GLOBALS['manage']   = array();
$GLOBALS['index']    = array();
$GLOBALS['unlinked'] = array();
$GLOBALS['inserted'] = array();
$GLOBALS['nonces']   = array();

// Every request the client was asked to make, and the answers it was told to give.
$GLOBALS['at'] = array(
	'reads'        => array(),
	'pages'        => array(),
	'writes'       => array(),
	'records'      => array(),
	'page'         => array(),
	'record_error' => false,
	'page_error'   => false,
	'write_error'  => false,
);

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $post_author = 0, $post_date_gmt = '';
}
/** The one query the card makes itself; this suite is about the list below it. */
class WP_User_Query {
	public function __construct( $args = array() ) {}
	public function get_results() { return array(); }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_js( $s ) { return str_replace( array( "'", "\n" ), array( "\\'", '' ), (string) $s ); }
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
function register_post_type() {}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? 1756800000 : $t ); }
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
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post                          = new WP_Post();
	$post->ID                      = ++$next;
	$post->post_title              = $a['post_title'] ?? '';
	$post->post_content            = $a['post_content'] ?? '';
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function get_posts( $a = array() ) { return array(); }
/** Records what the nonce was keyed to, which is half of what the order assertions read. */
function check_admin_referer( $a = -1, $q = '_wpnonce' ) {
	$GLOBALS['nonces'][] = $a;
	if ( ! empty( $GLOBALS['nonce_fails'] ) ) { throw new Exception( 'nonce:' . $a ); }
	return true;
}
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
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
		public static function wporg_username( $raw ) { return trim( (string) $raw, '/ ' ); }
		/**
		 * The column names, spelled as the base spells them.
		 *
		 * `Educational Institutions` is plural with a capital I on the Students table and
		 * singular with a small one on Students Reports, and `Tutor ` ends in a space. All
		 * three are pinned here because a handler that guessed at one of them would write
		 * nothing and report success.
		 */
		public static function fields() {
			return array(
				'student_record_name' => 'Full Name',
				'student_email'       => 'Email',
				'student_status'      => 'Status',
				'student_institution' => 'Educational Institutions',
				'student_start'       => 'Start Date',
				'student_end'         => 'End Date',
				'student_mentor'      => 'Mentor',
				'student_profile'     => 'WP Profile',
				'student_study'       => 'Your field of study',
				'student_tutor'       => 'Tutor ',
				'student_import_key'  => 'Site import key',
				'report_email'        => 'Email',
				'report_instituton'   => 'Educational institution',
			);
		}
	}
}

if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	/** The three keys the reconciliation card's own query reads. */
	class WPCPM_Students_Sync {
		const META_INSTITUTION = 'wpcpm_student_institution';
		const META_ACTIVE      = 'wpcpm_student_active';
		const META_PROGRAM     = 'wpcpm_student_program';
	}
}

if ( ! class_exists( 'WPCPM_Settings' ) ) {
	class WPCPM_Settings {
		public static function get() {
			return array(
				'students_table' => 'tbla8GZg5x6NY7aWt',
				'reports_table'  => 'tbljYkkVGbeoaWEtY',
			);
		}
		public static function get_value( $key ) { $s = self::get(); return $s[ $key ] ?? ''; }
		public static function is_connected() { return true; }
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		public static function rows() { return $GLOBALS['index']; }
		public static function row( $r ) { return $GLOBALS['index'][ $r ] ?? null; }
		public static function has( $r ) { return isset( $GLOBALS['index'][ $r ] ); }
		public static function read() { return array( 'v' => 1, 'read' => 1756800000, 'rows' => $GLOBALS['index'] ); }
	}
}

if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	/**
	 * The index, with the one write this control makes recorded rather than stored.
	 *
	 * `insert()` is what puts a linked student on their new roster before tonight's sync, so
	 * a refusal that reached it would be a student filed under a school by a write that never
	 * landed. Recorded so the refusal assertions can say it did not happen.
	 */
	class WPCPM_Roster_Index {
		public static function unlinked() { return $GLOBALS['unlinked']; }
		public static function rows( $id ) { return array(); }
		public static function counts() {
			return array( 'v' => 1, 'read' => 1756796400, 'institutions' => array(), 'reconciliation' => array() );
		}
		public static function insert( $id, array $row ) { $GLOBALS['inserted'][] = array( $id, $row ); }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/**
	 * Membership, which this control never rests on.
	 *
	 * Linking is manager-only, so the policy answers on the manager ground every time and the
	 * member ground is never the one that decides. Answering "no memberships" is therefore the
	 * honest contract for this suite: if a refusal here ever started depending on this, the
	 * fence would have grown a route the card does not draw.
	 */
	class WPCPM_Institution_Members {
		const META_RECORD_ID = 'wpcpm_institution_record_id';
		public static function memberships_of( $user ) { return array(); }
		public static function institution_of( $user ) { return ''; }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** The gate the member ground applies. A manager passes every action without it. */
	class WPCPM_Institution_Agreement {
		public static function is_settled( $id ) { return true; }
	}
}

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/**
	 * The client, recording every request and answering whatever the scenario set.
	 *
	 * The point of most of these assertions is what it was **not** asked to do: a refused row
	 * leaves `writes` empty, and a read that fails leaves it empty too.
	 */
	class WPCPM_Airtable {
		public function __construct( $settings = null ) {}
		public function get_record( $table, $record_id ) {
			$GLOBALS['at']['reads'][] = array( $table, $record_id );
			if ( $GLOBALS['at']['record_error'] ) {
				return new WP_Error( 'wpcpm_airtable_http', 'Airtable did not answer.' );
			}
			return array(
				'id'     => $record_id,
				'fields' => $GLOBALS['at']['records'][ $record_id ] ?? array(),
			);
		}
		public function fetch_page( $table, array $args = array() ) {
			$GLOBALS['at']['pages'][] = array( $table, $args['formula'] ?? '', $args['fields'] ?? array() );
			if ( $GLOBALS['at']['page_error'] ) {
				return new WP_Error( 'wpcpm_airtable_http', 'Airtable did not answer.' );
			}
			return array( 'records' => $GLOBALS['at']['page'], 'offset' => null );
		}
		public function update_records( $table, array $records ) {
			$GLOBALS['at']['writes'][] = array( $table, $records );
			if ( $GLOBALS['at']['write_error'] ) {
				return new WP_Error( 'wpcpm_airtable_422', 'INVALID_VALUE_FOR_COLUMN' );
			}
			$out = array();
			foreach ( $records as $record ) { $out[ $record['id'] ] = true; }
			return $out;
		}
		/** The real one lowercases both sides when the flag is set; so does this. */
		public function formula_in( $field, array $values, $lower = false ) {
			$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );
			if ( empty( $values ) ) { return ''; }
			$needle = $lower ? strtolower( $values[0] ) : $values[0];
			return sprintf( "%s({%s}) = '%s'", $lower ? 'LOWER' : '', $field, $needle );
		}
		public static function flatten( $value, $glue = ', ' ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) { return (string) $value['name']; }
				$parts = array();
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) { $parts[] = (string) $item; } elseif ( is_array( $item ) && isset( $item['name'] ) ) { $parts[] = (string) $item['name']; }
				}
				return implode( $glue, array_filter( $parts, 'strlen' ) );
			}
			return is_scalar( $value ) ? (string) $value : '';
		}
		public static function link_ids( $value ) {
			if ( ! is_array( $value ) ) { return array(); }
			$ids = array();
			foreach ( $value as $item ) {
				if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) { $ids[] = $item; } elseif ( is_array( $item ) && ! empty( $item['id'] ) ) { $ids[] = (string) $item['id']; }
			}
			return $ids;
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';

/* ---- runner ------------------------------------------------------------- */

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }

/**
 * Whether one call comes before another in a method body, with both of them present.
 *
 * `strpos()` answers a missing needle with false, and `false < 12` is true in PHP, so an order
 * assertion written that way goes on saying ok for a method that has lost the very check it
 * was meant to pin. Absence is the answer no, whichever of the two is missing.
 *
 * @param string $body   The method body.
 * @param string $first  The call that must come first.
 * @param string $second The call that must follow it.
 * @return bool
 */
function before( $body, $first, $second ) {
	$one = strpos( (string) $body, $first );
	$two = strpos( (string) $body, $second );

	return false !== $one && false !== $two && $one < $two;
}

/**
 * The body of one method, by brace depth: enough to read the order of two calls.
 *
 * @param string $source File contents.
 * @param string $name   Method name.
 * @return string|null
 */
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

/**
 * A private method of the module, callable, built once per name.
 *
 * @param string $name Method name.
 * @return ReflectionMethod
 */
function method( $name ) {
	static $built = array();

	if ( ! isset( $built[ $name ] ) ) {
		$built[ $name ] = new ReflectionMethod( 'WPCPM_Institutions', $name );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1 and
		// deprecated in 8.5, where calling it unconditionally prints a notice per call.
		if ( PHP_VERSION_ID < 80100 ) {
			$built[ $name ]->setAccessible( true );
		}
	}

	return $built[ $name ];
}

/** The shared rule, asked the way the card asks it. */
function blocked( array $row ) {
	return method( 'link_block' )->invoke( null, $row );
}

/** The reconciliation card's HTML, drawn for one manager. */
function render_card( $viewer ) {
	$GLOBALS['uid'] = (int) $viewer;

	ob_start();
	method( 'render_reconciliation' )->invokeArgs(
		new WPCPM_Institutions(),
		array( WPCPM_Roster_Index::counts(), WPCPM_Institutions_Index::read(), array( 'contact_not_member' => 0 ) )
	);

	return (string) ob_get_clean();
}

/**
 * Press Link as one user with one posted form, and say how the handler ended.
 *
 * Every recording is cleared first, the queued outcome included, so "nothing was written" and
 * "it was refused for this reason" are statements about this press rather than about whatever
 * the press before it left lying in user meta.
 *
 * @param int   $viewer Acting user ID.
 * @param array $post   The posted form.
 * @return string 'redirect:...' or 'wp_die:...' or ''.
 */
function press_link( $viewer, array $post ) {
	$GLOBALS['uid']          = (int) $viewer;
	$GLOBALS['nonces']       = array();
	$GLOBALS['inserted']     = array();
	$GLOBALS['posts']        = array();
	$GLOBALS['at']['reads']  = array();
	$GLOBALS['at']['pages']  = array();
	$GLOBALS['at']['writes'] = array();
	unset( $GLOBALS['umeta'][ (int) $viewer ]['wpcpm_flash'] );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$_POST = $post;

	try {
		( new WPCPM_Institutions() )->handle_link();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}

	return '';
}

/** The queued outcome for one user, read without consuming it. */
function outcome( $viewer ) {
	return $GLOBALS['umeta'][ (int) $viewer ]['wpcpm_flash']['institutions_link'] ?? null;
}

/** Just the outcome's slug. */
function outcome_status( $viewer ) {
	$flash = outcome( $viewer );

	return is_array( $flash ) ? $flash['status'] : '';
}

/** Every audit row written, as kind and institution. */
function audit_rows() {
	$rows = array();

	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( WPCPM_Institution_Audit::POST_TYPE !== $post->post_type ) { continue; }
		$rows[] = array(
			get_post_meta( $id, WPCPM_Institution_Audit::META_KIND, true ),
			get_post_meta( $id, WPCPM_Institution_Audit::META_INSTITUTION, true ),
			get_post_meta( $id, WPCPM_Institution_Audit::META_GROUND, true ),
			get_post_meta( $id, WPCPM_Institution_Audit::META_EVIDENCE, true ),
			get_post_meta( $id, WPCPM_Institution_Audit::META_SUBJECT, true ),
		);
	}

	return $rows;
}

/* ---- fixtures ------------------------------------------------------------ */

$A = 'recDdomg5W6h410JT'; // The TEST institution, which is what the module is built against.
$B = 'rec0IT9J93YkAYvSU';
$C = 'recZZZZZZZZZZZZZZ'; // Well-formed, never indexed.

// Five Students rows with no institution, one per shape the card has to tell apart.
$CLEAN     = 'recCLEAN000000001';
$MENTORED  = 'recMENTOR00000001';
$REPORTED  = 'recREPORT00000001';
$NOEMAIL   = 'recNOEMAIL0000001';
$MALFORMED = 'recBAD';

$GLOBALS['index'] = array(
	$A => array( 'record_id' => $A, 'name' => 'TEST - WordPress Education Dashboard (do not use) ', 'stage' => 'Confirmed' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Example', 'stage' => 'Confirmed' ),
);

/**
 * One row in the roster index's shape.
 *
 * @param string $record_id Students record ID.
 * @param array  $changes   What this row differs by.
 * @return array
 */
function roster_row( $record_id, array $changes = array() ) {
	return array_merge(
		array(
			'record_id'      => $record_id,
			'name'           => 'A Student',
			'email'          => 'a@example.test',
			'email_key'      => 'a@example.test',
			'status'         => 'Not moving forward',
			'institution'    => '',
			'start'          => '2026-02-01',
			'end'            => '',
			'has_mentor'     => false,
			'username'       => '',
			'field_of_study' => '',
			'tutor'          => '',
			'import_key'     => '',
			'reports'        => array(),
			'user_id'        => 0,
		),
		$changes
	);
}

$GLOBALS['unlinked'] = array(
	$CLEAN     => roster_row( $CLEAN, array( 'name' => 'Clean Row', 'email' => 'clean@example.test', 'email_key' => 'clean@example.test' ) ),
	$MENTORED  => roster_row( $MENTORED, array( 'name' => 'Mentored Row', 'email' => 'mentored@example.test', 'email_key' => 'mentored@example.test', 'status' => 'In Sensei', 'has_mentor' => true ) ),
	$REPORTED  => roster_row( $REPORTED, array( 'name' => 'Reported Row', 'email' => 'reported@example.test', 'email_key' => 'reported@example.test', 'reports' => array( 'recRPT00000000001' ) ) ),
	$NOEMAIL   => roster_row( $NOEMAIL, array( 'name' => 'No Address Row', 'email' => '', 'email_key' => '' ) ),
	$MALFORMED => roster_row( $MALFORMED, array( 'name' => 'Malformed Row' ) ),
);

$GLOBALS['users'] = array(
	1 => new WP_User( 1, 'Ada Admin', 'admin@example.test', array( 'administrator' ) ),
	2 => new WP_User( 2, 'Max Manager', 'max@example.test', array( 'administrator' ) ),
	3 => new WP_User( 3, 'Pia Program', 'pia@example.test', array( 'administrator' ) ),
	9 => new WP_User( 9, 'Sam School', 'sam@example.test', array( 'wpcpm_institution' ) ),
);
$GLOBALS['manage'] = array( 1, 2, 3, 4 );

/**
 * A live Students record, in the shape the API returns one.
 *
 * @param array $changes Cells that differ from a clean unlinked row.
 * @return array
 */
function live_row( array $changes = array() ) {
	return array_merge(
		array(
			'Full Name' => 'Clean Row',
			'Email'     => 'Clean@example.test',
			'Status'    => 'Not moving forward',
			'WP Profile' => 'https://profiles.wordpress.org/cleanrow/',
		),
		$changes
	);
}

$GLOBALS['at']['records'] = array(
	$CLEAN    => live_row(),
	$MENTORED => live_row( array( 'Status' => 'In Sensei', 'Mentor' => array( 'recMNT00000000001' ) ) ),
	$REPORTED => live_row( array( 'Email' => 'reported@example.test' ) ),
	$NOEMAIL  => live_row( array( 'Email' => '' ) ),
);

/* ---- the rule the card and the handler share ---------------------------- */

echo "\n=== One rule, asked of the index row and of the live record ===\n";

ck( 'a row with no institution, no mentor and no reports row may be linked', blocked( roster_row( $CLEAN ) ), '' );

// The refusal this whole control exists for. `Add students to Students Reports and Feedback`
// fires the moment the fourth condition is met, and the link is the fourth condition.
foreach ( array( 'In Sensei', 'In Sensei Self-onboarding', 'In Sensei 50h', 'Developer Track' ) as $status ) {
	ck(
		'a mentored row at "' . $status . '" is refused: the link would fire the reports automation',
		blocked( roster_row( $CLEAN, array( 'has_mentor' => true, 'status' => $status ) ) ),
		'automation'
	);
}

// Both halves are needed, and neither on its own is this refusal: a mentor at a status the
// automation does not watch cannot fire it, and a watched status with nobody assigned cannot
// either. Refusing on one half alone would block rows a manager has every reason to link.
ck( 'a mentored row at a status the automation ignores is not refused for it', blocked( roster_row( $CLEAN, array( 'has_mentor' => true, 'status' => 'Graduate' ) ) ), '' );
ck( 'nor is an unmentored row at a watched status', blocked( roster_row( $CLEAN, array( 'status' => 'In Sensei' ) ) ), '' );

// The second refusal. A reports row for this address means the program already holds the
// record the automation would otherwise make, so the link would leave it holding two.
ck( 'a row whose address already has a Students Reports row is refused', blocked( roster_row( $CLEAN, array( 'reports' => array( 'recRPT00000000001' ) ) ) ), 'reports-row' );

// The address is the only join between the two tables, so a row without one cannot be checked
// at all. Refusing is not pedantry: passing would be the site saying "no second row exists"
// on the strength of a question it never asked.
ck( 'a row with no address is refused, because the reports check cannot be run', blocked( roster_row( $CLEAN, array( 'email' => '', 'email_key' => '' ) ) ), 'no-address' );

// This list is a copy of the base as it was at the last sync. A row somebody linked in
// Airtable this morning is not this control's business, and saying so names the sync as the
// fix rather than writing a link over a link.
ck( 'a row that already names an institution is refused', blocked( roster_row( $CLEAN, array( 'institution' => $B ) ) ), 'already-linked' );

// Order matters where two reasons are true at once: the cheaper answer must not hide the one
// that costs a request, and the one that is not this control's business at all comes first.
ck( 'a linked row that also has a mentor is answered as linked', blocked( roster_row( $CLEAN, array( 'institution' => $B, 'has_mentor' => true, 'status' => 'In Sensei' ) ) ), 'already-linked' );
ck( 'a mentored row that also has a reports row is answered as the automation refusal', blocked( roster_row( $CLEAN, array( 'has_mentor' => true, 'status' => 'In Sensei', 'reports' => array( 'recRPT00000000001' ) ) ) ), 'automation' );

/* ---- the card ------------------------------------------------------------ */

echo "\n=== The card ===\n";

$html = render_card( 1 );

ck( 'the list is drawn under an anchor the handler can return to', has( $html, '<h3 id="wpcpm-unlinked">Rows with no institution</h3>' ), true );
ck( 'every unlinked row is named with its status', array(
	has( $html, '<li>Clean Row <span class="wpcpm-inst-muted">Not moving forward</span>' ),
	has( $html, '<li>Mentored Row <span class="wpcpm-inst-muted">In Sensei</span>' ),
	has( $html, '<li>No Address Row <span class="wpcpm-inst-muted">Not moving forward</span>' ),
), array( true, true, true ) );

ck( 'the row that may be linked gets a control', has( $html, 'name="wpcpm_student" value="' . $CLEAN . '"' ), true );
ck( 'posting to the module\'s own action', has( $html, '<input type="hidden" name="action" value="wpcpm_institutions_link" />' ), true );

// Every control is on one page. A nonce keyed to the action rather than the row would let a
// token taken from the row the site is willing to link be posted for the row beside it, which
// is a row it refuses: the refusal would then rest on the markup, which is not a check.
ck( 'under a nonce keyed to that row', has( $html, 'value="nonce-wpcpm_institutions_link_' . $CLEAN . '"' ), true );

ck( 'the institution is chosen from the index, not typed', array(
	has( $html, '<select id="wpcpm-link-' . $CLEAN . '" name="wpcpm_institution" required>' ),
	has( $html, '<option value="' . $A . '">' ),
	has( $html, '<option value="' . $B . '">Universidad Example</option>' ),
), array( true, true, true ) );

// Ten institution names in the base end in a space and two Confirmed records have no name at
// all. A picker that printed the stored string would show a manager two entries that look
// identical, which is how a student ends up filed under the wrong one of a near-pair.
ck( 'and the names print trimmed', has( $html, '<option value="' . $A . '">TEST - WordPress Education Dashboard (do not use)</option>' ), true );

// The three rows the card will not offer a control for, each said in the words the handler
// would use if somebody pressed anyway.
ck( 'the mentored row gets the automation refusal instead of a control', array(
	has( $html, 'name="wpcpm_student" value="' . $MENTORED . '"' ),
	has( $html, 'This row carries a mentor and a status the Airtable automation watches' ),
), array( false, true ) );
ck( 'the row with a reports row gets that refusal', array(
	has( $html, 'name="wpcpm_student" value="' . $REPORTED . '"' ),
	has( $html, 'A Students Reports row already exists for this row&#039;s address' ),
), array( false, true ) );
ck( 'the row with no address gets that one', array(
	has( $html, 'name="wpcpm_student" value="' . $NOEMAIL . '"' ),
	has( $html, 'This row carries no address, and the address is the only join between the two tables' ),
), array( false, true ) );

// A stored option is not a guaranteed shape, and a row this site cannot address in Airtable is
// a row no control here could ever act on. It is listed, and the standing instruction is what
// it gets instead.
ck( 'a row with no usable record ID is listed and offered nothing', array(
	has( $html, '<li>Malformed Row <span class="wpcpm-inst-muted">Not moving forward</span></li>' ),
	has( $html, 'name="wpcpm_student" value="' . $MALFORMED . '"' ),
	has( $html, 'Set Educational Institutions on that row in Airtable.' ),
), array( true, false, true ) );

// Drawing the list costs no request. The card is one of eight on a screen a manager opens all
// day, and a control that paged the base per row would take the rate limit away from the syncs.
ck( 'and drawing the whole card asks Airtable nothing', array( $GLOBALS['at']['reads'], $GLOBALS['at']['pages'] ), array( array(), array() ) );

/* ---- the order every handler in this module runs in ---------------------- */

echo "\n=== Capability, nonce, policy, and only then the network ===\n";

$src     = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );
$handler = method_body( $src, 'handle_link' );
$verify  = method_body( $src, 'verify' );

ck( 'the handler carries the nonce and the policy, and verify() carries the capability', array(
	has( $handler, '$this->verify(' ),
	has( $verify, 'current_user_can' ),
	has( $verify, 'check_admin_referer' ),
	has( $handler, 'WPCPM_Institution_Policy::decide(' ),
), array( true, true, true, true ) );
ck( 'it verifies before it decides', before( $handler, '$this->verify(', 'WPCPM_Institution_Policy::decide(' ), true );
ck( 'and verify() checks the capability before the nonce', before( $verify, 'current_user_can', 'check_admin_referer' ), true );
ck( 'and decides before anything reaches Airtable', before( $handler, 'WPCPM_Institution_Policy::decide(', 'new WPCPM_Airtable' ), true );
ck( 'it asks about the action the import asks about', has( $handler, 'ACT_ADD_STUDENT' ), true );
ck( 'and refuses with the one refusal', has( $handler, 'WPCPM_Institution_Policy::refusal()' ), true );

// The order assertion above used to be `strpos( $body, 'a' ) < strpos( $body, 'b' )`, which
// passes with the first check deleted, because a missing needle is false and `false < 12` is
// true. Both directions are pinned so nobody writes the passing-when-broken form again.
$only_nonce = 'nonce only: check_admin_referer';
$only_cap   = 'capability only: current_user_can';
ck( 'an order assertion says no when either check is missing, where strpos() said yes', array(
	before( $only_nonce, 'current_user_can', 'check_admin_referer' ),
	before( $only_cap, 'current_user_can', 'check_admin_referer' ),
	strpos( $only_nonce, 'current_user_can' ) < strpos( $only_nonce, 'check_admin_referer' ),
), array( false, false, true ) );

// The only two things read from the form are which row and which institution. Whether that row
// carries a mentor, what its status is and what its address is are read from the base, because
// they are the facts the refusals rest on and a form cannot be asked about them.
preg_match_all( '/posted_(?:text|key|id)\(\s*\'([^\']+)\'/', $handler, $posted );
ck( 'the handler reads two fields from the form and no more', $posted[1], array( 'wpcpm_student', 'wpcpm_institution' ) );

$nobody = press_link( 9, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );
ck( 'somebody without the capability is refused', has( $nobody, 'wp_die:You do not have permission' ), true );
ck( 'before the nonce is looked at, and before anything else happens', array( $GLOBALS['nonces'], $GLOBALS['at']['reads'], $GLOBALS['at']['writes'] ), array( array(), array(), array() ) );

$GLOBALS['nonce_fails'] = true;
$bad_nonce              = press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );
$GLOBALS['nonce_fails'] = false;
ck( 'a request with the wrong nonce stops at it', has( $bad_nonce, 'nonce:wpcpm_institutions_link_' . $CLEAN ), true );
ck( 'and reaches neither the policy nor the base', array( $GLOBALS['at']['reads'], $GLOBALS['at']['writes'] ), array( array(), array() ) );

// The one way a capability holder is refused by the fence: `decide()` cannot resolve the
// acting user, so it answers `no-user` and no ground is tried. User 4 holds the capability and
// has no account, which is the shape a deleted user leaves mid-request.
$no_user = press_link( 4, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );
ck( 'a request the policy refuses gets the policy\'s own refusal', has( $no_user, 'wp_die:That record is not on your roster.' ), true );
ck( 'and nothing is read or written for it', array( $GLOBALS['at']['reads'], $GLOBALS['at']['writes'] ), array( array(), array() ) );

/* ---- the two refusals, live --------------------------------------------- */

echo "\n=== The two refusals, decided against the base and not against the list ===\n";

$GLOBALS['at']['page'] = array();
$mentored              = press_link( 1, array( 'wpcpm_student' => $MENTORED, 'wpcpm_institution' => $B ) );

ck( 'the mentored row is refused', outcome_status( 1 ), 'automation' );
ck( 'and the row is not written', $GLOBALS['at']['writes'], array() );
ck( 'nor put on anybody\'s roster', $GLOBALS['inserted'], array() );
ck( 'nor logged as something that happened', audit_rows(), array() );
ck( 'the manager lands back on the list', has( $mentored, 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions#wpcpm-unlinked' ), true );

// The reports lookup is a second request. A row the automation already covers is refused
// whatever that lookup would have said, so it is never made.
ck( 'and the reports lookup is not even made for it', $GLOBALS['at']['pages'], array() );

$GLOBALS['at']['page'] = array( array( 'id' => 'recRPT00000000001', 'fields' => array( 'Email' => 'reported@example.test' ) ) );
press_link( 1, array( 'wpcpm_student' => $REPORTED, 'wpcpm_institution' => $B ) );

ck( 'a row whose address already has a reports row is refused', outcome_status( 1 ), 'reports-row' );
ck( 'and nothing is written for it either', array( $GLOBALS['at']['writes'], $GLOBALS['inserted'], audit_rows() ), array( array(), array(), array() ) );
ck( 'the lookup went to the reports table by address, lowercased on both sides', $GLOBALS['at']['pages'], array(
	array( 'tbljYkkVGbeoaWEtY', "LOWER({Email}) = 'reported@example.test'", array( 'Email' ) ),
) );

// **The point of reading live.** This row is clean in the index the card drew from, and a
// mentor was assigned in the base since the last sync. A control that trusted the list would
// write the link and make the duplicate.
$GLOBALS['at']['records'][ $CLEAN ] = live_row( array( 'Status' => 'Developer Track', 'Mentor' => array( array( 'id' => 'recMNT00000000001' ) ) ) );
$GLOBALS['at']['page']              = array();
press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );

ck( 'a row the list calls clean is refused when the base says it has a mentor now', outcome_status( 1 ), 'automation' );
ck( 'and it is the base that was asked', $GLOBALS['at']['reads'], array( array( 'tbla8GZg5x6NY7aWt', $CLEAN ) ) );
ck( 'and nothing was written', $GLOBALS['at']['writes'], array() );

$GLOBALS['at']['records'][ $CLEAN ] = live_row();

// The same freshness the other way: a row the base says is already linked cannot be linked
// again, however this list still describes it.
$GLOBALS['at']['records'][ $CLEAN ]['Educational Institutions'] = array( $A );
press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );

ck( 'a row the base already links is refused', outcome_status( 1 ), 'already-linked' );
ck( 'and is not written over', $GLOBALS['at']['writes'], array() );

unset( $GLOBALS['at']['records'][ $CLEAN ]['Educational Institutions'] );

/* ---- failing closed ------------------------------------------------------ */

echo "\n=== Failing closed ===\n";

$GLOBALS['at']['record_error'] = true;
press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );
$GLOBALS['at']['record_error'] = false;

ck( 'a row the base would not describe is not written', array( outcome_status( 1 ), $GLOBALS['at']['writes'] ), array( 'read-failed', array() ) );

// "Airtable did not answer" is not "there is no reports row". This is the assertion that keeps
// somebody from reading an empty result out of an error and calling the row clean.
$GLOBALS['at']['page_error'] = true;
press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );
$GLOBALS['at']['page_error'] = false;

ck( 'a reports lookup that failed is not an empty reports lookup', array( outcome_status( 1 ), $GLOBALS['at']['writes'] ), array( 'read-failed', array() ) );

// A record ID the site has never read would go into Airtable unchecked, and a mistyped one is
// a link to nothing that no later screen would show as wrong.
press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $C ) );
ck( 'a well-formed institution the site has never read is refused', array( outcome_status( 1 ), $GLOBALS['at']['reads'] ), array( 'unknown-institution', array() ) );

press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => 'the university' ) );
ck( 'and so is a posted value that is not a record ID at all', array( outcome_status( 1 ), $GLOBALS['at']['reads'] ), array( 'bad-record', array() ) );

// A PATCH that came back an error leaves the row where it was, so nothing may be written on
// this side of it either: a roster row for a link the base refused is a student filed under a
// school that has never heard of them.
$GLOBALS['at']['write_error'] = true;
press_link( 1, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );
$GLOBALS['at']['write_error'] = false;

ck( 'a refused PATCH leaves no roster row and no audit row', array( outcome_status( 1 ), $GLOBALS['inserted'], audit_rows() ), array( 'write-failed', array(), array() ) );

/* ---- the row it does link ------------------------------------------------ */

echo "\n=== The row it does link ===\n";

$GLOBALS['at']['page'] = array();
$linked                = press_link( 2, array( 'wpcpm_student' => $CLEAN, 'wpcpm_institution' => $B ) );

ck( 'the link is written', outcome_status( 2 ), 'linked' );
ck( 'and the manager is told which institution', is_array( outcome( 2 ) ) ? outcome( 2 )['detail'] : '', 'Universidad Example' );

// One PATCH, to the Students table, carrying the link and nothing else. Every other cell on
// that row is somebody else's writing, and a PATCH that carried a copy read a moment earlier
// would be this site overwriting the base with it.
ck( 'one PATCH, on the Students table, carrying the institution link and no other cell', $GLOBALS['at']['writes'], array(
	array(
		'tbla8GZg5x6NY7aWt',
		array( array( 'id' => $CLEAN, 'fields' => array( 'Educational Institutions' => array( $B ) ) ) ),
	),
) );

// On the roster now rather than after tonight's sync: a manager who has just linked a student
// and is told to come back tomorrow cannot tell a slow index from a write that did not land.
ck( 'the student is on that institution\'s roster immediately', count( $GLOBALS['inserted'] ), 1 );
ck( 'under the institution they were linked to', $GLOBALS['inserted'][0][0], $B );
ck( 'as the live record describes them, with the link stamped on the row', array(
	$GLOBALS['inserted'][0][1]['record_id'],
	$GLOBALS['inserted'][0][1]['institution'],
	$GLOBALS['inserted'][0][1]['name'],
	$GLOBALS['inserted'][0][1]['email_key'],
	$GLOBALS['inserted'][0][1]['username'],
), array( $CLEAN, $B, 'Clean Row', 'clean@example.test', 'https://profiles.wordpress.org/cleanrow' ) );

// The ground is the fence's answer and not an assumption about who pressed: decision 2 says a
// manager passes every action as `manager`, and the log is where that is read back. The
// evidence is `live`, because that is what the two refusals were decided against.
ck( 'one audit row, on the manager ground, against a live read', audit_rows(), array(
	array( 'student_linked', $B, 'manager', 'live', $CLEAN ),
) );

ck( 'and the manager lands back on the list', has( $linked, '#wpcpm-unlinked' ), true );

// A fresh manager per rendered outcome: `WPCPM_Flash::take()` memoizes per user and channel
// for the life of the request, which is right in production and wrong for a suite that draws
// two outcomes in one process.
$success = render_card( 2 );
ck( 'the card says what happened, where it happened', has( $success, 'The row is linked to Universidad Example.' ), true );

press_link( 3, array( 'wpcpm_student' => $MENTORED, 'wpcpm_institution' => $B ) );
$refused = render_card( 3 );
ck( 'and a refusal says nothing was written before it says why', has( $refused, 'Nothing was written. This row carries a mentor and a status the Airtable automation watches' ), true );

/* ---- prose --------------------------------------------------------------- */

echo "\n=== Prose ===\n";

$dashes = array();

foreach ( array( 'includes/modules/class-wpcpm-institutions.php', 'bin/test-unlinked-link.php' ) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either file', $dashes, array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
