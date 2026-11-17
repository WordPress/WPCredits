<?php
/**
 * One student's card as their institution reads it, and the report route's third audience.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The fence, from the card's side.** A member sees their own student; a member of another
 *   institution sees the roster instead, and so does anybody who edits the user ID in the URL to
 *   one that belongs to nobody. "No such student" and "not your student" have to be one answer,
 *   or the card is a membership oracle somebody can walk a user ID at a time.
 * - **Every row prints, even when it is empty.** The card for a student whose record is almost
 *   blank has the same rows as the card for a complete one, filled with "Not set". A dropped row
 *   is a question about the page; a blank one is information about the record.
 * - **The accessibility disclosure is not on the card.** The fixture carries one, in the same
 *   cached row as the field of study, and the rendered card must not contain it - asserted on
 *   the output and again on the source, so a later edit that loops over that row fails here.
 * - **The mentor is a name and an address.** The fixture's mentor card also carries a Slack
 *   handle, a WordPress.org profile and a website; none of them may appear. The one address on
 *   the card is theirs: the student's own is not one of section 7.5's columns, and the fixture
 *   carries it in both caches so a row that came back would be caught here.
 * - **The row labeled "Program" speaks one vocabulary.** The two tables both have a `Status`
 *   column and they do not mean the same thing: the Students table's is an application pipeline
 *   (`Not moving forward`, `Fail`, and on this base one row whose status is a school's name),
 *   and `WPCPM_Program::label()` passes anything it does not know straight through. The fixture
 *   puts a pipeline status in every place the card could read one from.
 * - **The page dresses what it draws.** The report disclosure and its fetched body are the
 *   report form's own markup, and the rules for it live in the calendar stylesheet, so the card
 *   enqueues that stylesheet when it draws one and not when it does not. The classes the
 *   identity block prints have rules behind them in the stylesheets this page loads, and the
 *   row the stylesheet lays out is the element the card prints.
 * - **The report route now has three audiences.** A manager and a mentor answered as before; a
 *   member answered by `claim()`, whose refusal is the fence's one message byte for byte and
 *   which spends no live read to refuse a stranger. The route itself answers `false`, never the
 *   `WP_Error`: one route, three audiences, one answer from outside.
 *
 * Run from the plugin root:  php bin/test-institution-student-view.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['users']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['memberships'] = array();
$GLOBALS['settled']     = array();
$GLOBALS['umeta']       = array();
$GLOBALS['roster']      = array();
$GLOBALS['mentees']     = array();
$GLOBALS['enqueued']    = array();
$GLOBALS['styles']      = array();
$GLOBALS['localized']   = array();

// The Students rows an Airtable read would return, keyed by the record that was claimed, and a
// counter for them: a refusal that costs a request is a refusal that can be used to make the
// site pay, which is why the design spec puts the cheap decision first.
$GLOBALS['live']       = array();
$GLOBALS['live_reads'] = 0;

class WP_Error {
	private $code;
	private $message;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_type = '', $post_status = 'private'; }

/** Enough of the REST request for the one permission callback under test. */
class WP_REST_Request implements ArrayAccess {
	private $args = array();
	public function __construct( array $args = array() ) { $this->args = $args; }
	#[\ReturnTypeWillChange]
	public function offsetExists( $o ) { return isset( $this->args[ $o ] ); }
	#[\ReturnTypeWillChange]
	public function offsetGet( $o ) { return isset( $this->args[ $o ] ) ? $this->args[ $o ] : null; }
	#[\ReturnTypeWillChange]
	public function offsetSet( $o, $v ) { $this->args[ $o ] = $v; }
	#[\ReturnTypeWillChange]
	public function offsetUnset( $o ) { unset( $this->args[ $o ] ); }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses_post( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action() {}
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function human_time_diff( $from, $to = 0 ) { return '2 hours'; }
function get_user_by( $f, $v ) { return isset( $GLOBALS['users'][ (int) $v ] ) ? $GLOBALS['users'][ (int) $v ] : false; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ''; }

/** Grants exactly the one capability the policy and the route ask for. */
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;
	return WPCPM_Roles::CAP_MANAGE === $c && in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }

function remove_query_arg( $key, $query = false ) {
	$url = false === $query ? 'https://example.test/institution-dashboard/?wpcpm_institution_student=10&wpcpm_status=current' : (string) $query;
	foreach ( (array) $key as $one ) {
		$url = (string) preg_replace( '/[?&]' . preg_quote( (string) $one, '/' ) . '=[^&]*/', '', $url );
	}
	return str_replace( '/?&', '/?', $url );
}
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); }
function wp_create_nonce( $a = -1 ) { return 'nonce-' . $a; }
function wp_enqueue_script( $handle ) { $GLOBALS['enqueued'][] = (string) $handle; }
function wp_enqueue_style( $handle ) { $GLOBALS['styles'][] = (string) $handle; }
function wp_localize_script( $handle, $object_name, $l10n ) { $GLOBALS['localized'][ (string) $object_name ] = $l10n; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

// Declares add_filter(), apply_filters() and get_option(): the program map is made of filters,
// over the options a compile writes, and this suite's own three read no filter and no option.
require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once __DIR__ . '/stubs/view-faults.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';

/*
 * Stand-ins for the pieces this one builds on, each to its contract. None of the real files is
 * loaded: the card is meant to work from these calls and nothing else, and a suite that loaded
 * the sync would be testing the sync.
 */

/** Stands in for the mentors sync: the record-ID shape check and the stored-name resolver. */
class WPCPM_Mentors_Sync {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $value ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) ); }
	public static function resolve_stored( $value, $type ) { return trim( (string) $value ); }
	/** The status card's two reads (Task 16): the Students table's status column and the tracked statuses. */
	public static function fields() { return array( 'student_status' => 'Status' ); }
	public static function tracked_statuses( $settings = null ) {
		return array(
			'active' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ),
			'past'   => array( 'Graduate', 'Dropped out' ),
			'all'    => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Graduate', 'Dropped out' ),
		);
	}
}

/** Stands in for the students sync: the meta keys and the two cached rows. */
class WPCPM_Students_Sync {
	const META_RECORD_ID   = 'wpcpm_student_record_id';
	const META_PROGRAM     = 'wpcpm_student_program';
	const META_MENTOR      = 'wpcpm_student_mentor';
	const META_UPDATED     = 'wpcpm_student_updated';
	const META_INSTITUTION = 'wpcpm_student_institution';
	const META_COURSE      = 'wpcpm_student_course';
	public static function get_program( $user_id ) {
		$row = get_user_meta( (int) $user_id, self::META_PROGRAM, true );
		return is_array( $row ) ? $row : array();
	}
	public static function get_mentor( $user_id ) {
		$row = get_user_meta( (int) $user_id, self::META_MENTOR, true );
		return is_array( $row ) ? $row : array();
	}
}

/** Stands in for the calls module: the student's Students Reports record. */
class WPCPM_Mentor_Calls {
	public static function student_record( $user_id ) {
		$record = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_RECORD_ID, true );
		$record = is_string( $record ) ? trim( $record ) : '';
		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}
}

/** Stands in for the roster index: one institution's rows and when they were read. */
class WPCPM_Roster_Index {
	public static function read( $record_id ) {
		return array(
			'v'    => 1,
			'read' => isset( $GLOBALS['roster'][ $record_id ] ) ? 1756800000 : 0,
			'rows' => self::rows( $record_id ),
		);
	}
	public static function rows( $record_id ) {
		return isset( $GLOBALS['roster'][ $record_id ] ) ? $GLOBALS['roster'][ $record_id ] : array();
	}
}

/** Stands in for the members module: the institutions an account acts for right now. */
class WPCPM_Institution_Members {
	const LEVEL_REPRESENTATIVE = 'representative';
	const LEVEL_TUTOR          = 'tutor';
	/** The level the account holds: a tutor where the fixture says so (`$GLOBALS['levels']`), a representative otherwise, as the real class reads it. */
	public static function level_of( $user = null ) {
		$id = is_object( $user ) ? (int) $user->ID : (int) $user;
		return isset( $GLOBALS['levels'][ $id ] ) && 'tutor' === $GLOBALS['levels'][ $id ] ? 'tutor' : 'representative';
	}
	public static function memberships_of( $user = null ) {
		$id = is_object( $user ) ? $user->ID : (int) $user;
		return isset( $GLOBALS['memberships'][ $id ] ) ? $GLOBALS['memberships'][ $id ] : array();
	}
}

/** Stands in for the agreement module: whether that institution's gate is open. */
class WPCPM_Institution_Agreement {
	public static function is_settled( $record_id ) { return in_array( $record_id, $GLOBALS['settled'], true ); }
}

/** Stands in for the mentors dashboard: the three display helpers and the script handle. */
class WPCPM_Mentors_Dashboard {
	const SCRIPT = 'wpcpm-mentor-dashboard';
	public static function avatar_url( $username, $email, $size = 64 ) {
		$GLOBALS['avatar_emails'][] = (string) $email;
		return '' !== trim( (string) $username ) ? 'https://wordpress.org/grav-redirect.php?user=' . rawurlencode( $username ) : '';
	}
	public static function format_dates( $start, $end ) {
		$parts = array_filter( array( trim( (string) $start ), trim( (string) $end ) ), 'strlen' );
		return implode( ' to ', $parts );
	}
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) { return ''; }
		return preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $url ) ? $url : 'https://' . ltrim( $url, '/' );
	}
	public static function get_mentees( $user_id ) {
		return isset( $GLOBALS['mentees'][ (int) $user_id ] ) ? $GLOBALS['mentees'][ (int) $user_id ] : array();
	}
}

/**
 * Stands in for the call calendar, which is where the report form's own stylesheet lives.
 *
 * Only the handle: the card enqueues it and never calls anything else on the class, and a
 * stand-in that did more would be testing the calendar.
 */
class WPCPM_Call_Calendar {
	const STYLE = 'wpcpm-call-calendar';
}

/** Stands in for the team links and their icons. */
class WPCPM_Contribution_Teams {
	public static function links( $value ) {
		$value = trim( (string) $value );
		return '' === $value ? '' : '<a href="https://make.wordpress.org/">' . esc_html( $value ) . '</a>';
	}
	public static function label_icon( $value ) { return '<span class="wpcpm-team__icon"></span>'; }
}

/** Stands in for the icon set. */
class WPCPM_Icons {
	public static function svg( $name, $size = 16 ) { return '<svg class="wpcpm-icon wpcpm-icon--' . $name . '"></svg>'; }
	/** The parts' chevron (Task 16), at its size. */
	public static function ui( $name, $size = 16 ) { return '<svg class="wpcpm-icon wpcpm-icon--' . $name . '" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true" focusable="false"></svg>'; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';

/**
 * Stands in for the roster module, to the contract in section 5.3 of the design spec.
 *
 * The four numbered steps, in order, because the order is what the assertions below are about:
 * the shape before anything reaches the network, the cheap cached decision, the live read, and
 * the authoritative decision on the Students row's own institution link.
 */
class WPCPM_Institution_Roster {
	public static function claim( $record, $action, $type = 'student', $user = null ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return WPCPM_Institution_Policy::refusal();
		}

		$pre = WPCPM_Institution_Policy::decide( $action, self::cached_subject( $record, $type ), $user );

		if ( ! $pre['allowed'] ) {
			return WPCPM_Institution_Policy::refusal();
		}

		if ( ! isset( $GLOBALS['live'][ $record ] ) ) {
			return WPCPM_Institution_Policy::refusal();
		}

		++$GLOBALS['live_reads'];
		$row = $GLOBALS['live'][ $record ];

		$decision = WPCPM_Institution_Policy::decide(
			$action,
			WPCPM_Institution_Policy::subject_live( $type, $record, (array) $row['fields']['Educational Institutions'] ),
			$user
		);

		if ( ! $decision['allowed'] ) {
			return WPCPM_Institution_Policy::refusal();
		}

		return array(
			'record'   => $row,
			'decision' => $decision,
		);
	}

	const TYPE_STUDENT = 'student';
	const TYPE_REPORT  = 'report';

	public static function cached_subject( $record, $type ) {
		foreach ( $GLOBALS['umeta'] as $id => $meta ) {
			if ( isset( $meta[ WPCPM_Students_Sync::META_RECORD_ID ] ) && (string) $meta[ WPCPM_Students_Sync::META_RECORD_ID ] === (string) $record ) {
				return WPCPM_Institution_Policy::subject_student_account( $id );
			}
		}

		// A Students record (the status card's, Task 16): the roster index row it is on, as the shipped one reads it.
		foreach ( $GLOBALS['roster'] as $institution => $rows ) {
			if ( isset( $rows[ $record ] ) ) {
				return WPCPM_Institution_Policy::subject_index_row( $institution, $record );
			}
		}

		return WPCPM_Institution_Policy::subject_student_account( 0 );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';

// What the four controls the card hangs off need, drawn by their own classes here (each pinned in its own suite): the
// flash and the kept typing read from this suite's globals, the sign-in record, the last invitation, the export's address.
$GLOBALS['flash']       = array();
$GLOBALS['kept_typing'] = array();
$GLOBALS['kept_flash']  = array();
$GLOBALS['signed_in']   = array();
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function wp_slash( $v ) { return $v; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function is_email( $e ) { return filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? (string) $e : false; }
function wp_date( $f, $t = null ) { return gmdate( (string) $f, null === $t ? time() : (int) $t ); }
function selected( $a, $b, $echo = true ) { $out = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $echo ) { echo $out; } return $out; }
function is_admin() { return false; }
// Each date as this suite's format_dates() stand-in prints it, so the parts' dates, drawn one at a time, read the same.
function date_i18n( $f, $t = false ) { return gmdate( 'Y-m-d', false === $t ? time() : (int) $t ); }
function _doing_it_wrong( $f, $m, $v ) { $GLOBALS['wrong'][] = $f; }
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_script() {}
function wp_referer_field( $display = true ) {
	$field = '<input type="hidden" name="_wp_http_referer" value="/institution-dashboard/?wpcpm_view=students&amp;wpcpm_institution_student=10" />';
	if ( $display ) { echo $field; }
	return $field;
}
function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) {
	$field = '<input type="hidden" id="' . esc_attr( $n ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( wp_create_nonce( $a ) ) . '" />' . ( $r ? wp_referer_field( false ) : '' );
	if ( $e ) { echo $field; }
	return $field;
}
if ( ! class_exists( 'WPCPM_Flash' ) ) {
	/** The flash, read from `$GLOBALS['flash']` by channel and from `$GLOBALS['kept_flash']` for a refused form. */
	class WPCPM_Flash {
		public static function take( $channel, $user_id = 0 ) { return isset( $GLOBALS['flash'][ $channel ] ) ? $GLOBALS['flash'][ $channel ] : ''; }
		public static function kept( $form ) {
			return array( 'values' => array(), 'problems' => isset( $GLOBALS['kept_flash']['problems'] ) ? $GLOBALS['kept_flash']['problems'] : array() );
		}
	}
}
if ( ! class_exists( 'WPCPM_Typed_Text' ) ) {
	/** What a refused post typed, from `$GLOBALS['kept_typing']`, and the box's value as the real one writes it. */
	class WPCPM_Typed_Text {
		public static function kept( $form ) { return $GLOBALS['kept_typing']; }
		public static function kept_attr( $value ) { return str_replace( '&', '&amp;', (string) $value ); }
	}
}
if ( ! class_exists( 'WPCPM_Sign_Ins' ) ) {
	/** The sign-in record began on 15 October 2026; a student has signed in where the fixture says so. */
	class WPCPM_Sign_Ins {
		public static function since() { return 1792022400; }
		public static function has_signed_in( $user_id ) { return ! empty( $GLOBALS['signed_in'][ (int) $user_id ] ); }
	}
}
if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/** The last invitation went on 1 November 2026, and none is held. */
	class WPCPM_Mail {
		public static function last_invited( $user_id ) { return 1793491200; }
		public static function is_held( $user_id ) { return false; }
		/** The three sentences the line's outcomes quote, in the mail layer's words. */
		public static function too_soon_message() { return 'An invitation was sent a few minutes ago.'; }
		public static function invite_failed_message() { return 'The invitation could not be sent.'; }
		public static function invite_refused_message( $who = '' ) { return 'The invitation could not be sent.'; }
	}
}
if ( ! class_exists( 'WPCPM_Institution_Export' ) ) {
	/** The single-student export's address, keyed to the account on screen. */
	class WPCPM_Institution_Export {
		public static function student_url( $user_id ) { return 'https://example.test/wp-admin/admin-post.php?action=wpcpm_export_student&user=' . (int) $user_id . '&_wpnonce=nonce-export'; }
	}
}
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
if ( ! function_exists( 'sanitize_html_class' ) ) {
	/** Core's: a class name with anything but letters, digits, hyphens and underscores taken out. */
	function sanitize_html_class( $c, $f = '' ) { $c = preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $c ); $c = preg_replace( '/[^A-Za-z0-9_-]/', '', $c ); return '' === $c ? (string) $f : $c; }
}
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-resend.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-students.php';

// The program map and the report forms are the compiled tracks' (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

$fail  = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fail, $total;

	++$total;

	if ( $got === $want ) {
		echo 'ok   ' . $label . "\n";
		return;
	}

	++$fail;
	echo 'FAIL ' . $label . "\n";
	echo '       expected: ' . var_export( $want, true ) . "\n";
	echo '       actual:   ' . var_export( $got, true ) . "\n";
}

/**
 * A well-formed Airtable record ID from a short seed.
 *
 * @param string $seed Alphanumeric seed.
 * @return string
 */
function rid( $seed ) {
	return 'rec' . substr( str_pad( preg_replace( '/[^A-Za-z0-9]/', '', $seed ), 14, '0' ), 0, 14 );
}

/**
 * Render one card and hand back its markup.
 *
 * @param string $institution Institutions record ID.
 * @param int    $user_id     The student's account.
 * @param array  $context     What the dashboard would pass.
 * @return string
 */
function card( $institution, $user_id, array $context = array() ) {
	ob_start();
	WPCPM_Institution_Student_View::render( $institution, $user_id, $context );
	return (string) ob_get_clean();
}

/**
 * The value cell of one labeled row.
 *
 * So that an assertion about the Program row is about the Program row: "Not set" appears on
 * several rows of this card, and a search of the whole card would pass on any of them.
 *
 * @param string $html  A rendered card.
 * @param string $label The row's visible label.
 * @return string The cell's markup, or an empty string when the card has no such row.
 */
function row_of( $html, $label ) {
	$pattern = '#<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">' . preg_quote( $label, '#' ) . '</th><td class="[^"]*" role="cell">(.*?)</td>#s';

	return preg_match( $pattern, (string) $html, $m ) ? $m[1] : '';
}

/* ---- the fixture ---------------------------------------------------------- */

$inst_a   = rid( 'INSTA' );
$inst_b   = rid( 'INSTB' );
$report_a = rid( 'REPORTA' );
$report_b = rid( 'REPORTB' );
$row_a    = rid( 'ROWA' );
$stray    = rid( 'STRAY' );

// 10 Ana, at institution A. 11 Bea, at institution B. 20 and 21 the two members. 30 a program
// manager. 40 Ana's mentor. 60 an account that is not a student at all.
$GLOBALS['users'] = array(
	10 => new WP_User( 10, 'Ana Lopez', 'ana@example.test', array( WPCPM_Roles::ROLE_STUDENT ) ),
	11 => new WP_User( 11, 'Bea Rossi', 'bea@example.test', array( WPCPM_Roles::ROLE_STUDENT ) ),
	20 => new WP_User( 20, 'Ines Ruiz', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
	21 => new WP_User( 21, 'Bruno Bianchi', 'bruno@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
	30 => new WP_User( 30, 'Program Manager', 'pm@example.test', array( 'administrator' ) ),
	40 => new WP_User( 40, 'Marta Mentor', 'marta@example.test', array( WPCPM_Roles::ROLE_MENTOR ) ),
	60 => new WP_User( 60, 'Office Account', 'office@example.test', array( 'editor' ) ),
);

$GLOBALS['manage']      = array( 30 );
$GLOBALS['memberships'] = array(
	20 => array( $inst_a ),
	21 => array( $inst_b ),
);
$GLOBALS['settled'] = array( $inst_a, $inst_b );

$GLOBALS['umeta'] = array(
	10 => array(
		WPCPM_Students_Sync::META_RECORD_ID   => $report_a,
		WPCPM_Students_Sync::META_INSTITUTION => $inst_a,
		WPCPM_Students_Sync::META_UPDATED     => 1756800000,
		WPCPM_Students_Sync::META_PROGRAM     => array(
			'name'           => 'Ana Lopez',
			'email'          => 'ana@example.test',
			'program'        => 'In Sensei',
			'start'          => '2026-02-16',
			'end'            => '2026-06-30',
			'username'       => 'student-one',
			'slack'          => 'ana.lopez',
			'team'           => 'Documentation',
			'website'        => 'https://ana.example.test/',
			'field_of_study' => 'Computer Science',
			// Empty on purpose: the row still prints, and it prints "Not set".
			'tutor'          => '',
			// The disclosure the student made to the program, one array key away from the
			// field of study. It must not reach a school's screen.
			'accessibility'  => 'Needs captions on every call',
		),
		WPCPM_Students_Sync::META_MENTOR      => array(
			'record_id' => rid( 'MENTOR' ),
			'name'      => 'Marta Mentor',
			'email'     => 'marta@example.test',
			// Everything below is on the mentor's own card and on no institution's.
			'username'  => 'mentor-one',
			'profile'   => 'https://profiles.wordpress.org/mentor-one/',
			'slack'     => 'marta.mentor',
			'website'   => 'https://marta.example.test/',
			'github'    => 'mentor-one',
			'location'  => 'Valencia',
		),
	),
	11 => array(
		WPCPM_Students_Sync::META_RECORD_ID   => $report_b,
		WPCPM_Students_Sync::META_INSTITUTION => $inst_b,
		// Nothing else: the card for a student the sync has barely seen is the "every row
		// prints" case.
		WPCPM_Students_Sync::META_PROGRAM     => array(),
	),
	// Not a student, and carrying no institution: a manager may open any account and there is
	// still no card to draw for this one.
	60 => array(),
);

$GLOBALS['roster'] = array(
	$inst_a => array(
		$row_a => array(
			'record_id'      => $row_a,
			'name'           => 'Ana Lopez',
			'email'          => 'ana@example.test',
			'status'         => 'In Sensei',
			'institution'    => $inst_a,
			'start'          => '2026-02-16',
			'end'            => '2026-06-30',
			'has_mentor'     => true,
			'username'       => 'student-one',
			'field_of_study' => 'Computer Science',
			'tutor'          => '',
			'reports'        => array( $report_a ),
			'user_id'        => 10,
		),
	),
	$inst_b => array(),
);

$GLOBALS['mentees'] = array(
	40 => array( array( 'record_id' => $report_a ) ),
);

// What the live Students read would return for each report record.
$GLOBALS['live'] = array(
	$report_a => array(
		'id'     => rid( 'STUDROWA' ),
		'fields' => array( 'Educational Institutions' => array( $inst_a ) ),
	),
	$report_b => array(
		'id'     => rid( 'STUDROWB' ),
		'fields' => array( 'Educational Institutions' => array( $inst_b ) ),
	),
);

/* ---- 1. a member sees their own student ----------------------------------- */

echo "=== A member opens their own student ===\n";

$GLOBALS['uid'] = 20;

ck( 'the argument is read as a user ID', WPCPM_Institution_Student_View::ARG, 'wpcpm_institution_student' );

$_GET[ WPCPM_Institution_Student_View::ARG ] = '10';
ck( 'requested() reads it', WPCPM_Institution_Student_View::requested(), 10 );
$_GET[ WPCPM_Institution_Student_View::ARG ] = 'not-a-number';
ck( 'and answers 0 for anything that is not one', WPCPM_Institution_Student_View::requested(), 0 );
unset( $_GET[ WPCPM_Institution_Student_View::ARG ] );

ck( 'shows() says yes for a student on their roster', WPCPM_Institution_Student_View::shows( 10 ), true );

$html = card( $inst_a, 10, array( 'read' => 1756800000 ) );

ck( 'the card names the student', false !== strpos( $html, 'Ana Lopez' ), true );

// Task 9: on the Institution Dashboard the side menu leads back to the roster (the Students item), so the card draws
// no way back of its own there; drawn on any other surface it keeps the one it always drew.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
WPCPM_UI::enter( 'institution' );
$on_dashboard = card( $inst_a, 10, array( 'read' => 1756800000 ) );
WPCPM_UI::leave();
ck( 'drawn on the Institution Dashboard, the card has no Back to the roster, and is still the card', array( strpos( $on_dashboard, 'Back to the roster' ), strpos( $on_dashboard, 'wpcpm-institution__back' ), false !== strpos( $on_dashboard, 'Ana Lopez' ) ), array( false, false, true ) );
ck( 'nor drawn with no surface named: the side menu\'s Students item is the way back', array( strpos( $html, 'Back to the roster' ), strpos( $html, 'wpcpm-institution__back' ) ), array( false, false ) );
ck( 'and names the program rather than the Airtable status', false !== strpos( $html, 'WordPress Credits Program 150h' ), true );
ck( 'the status is never printed raw', false === strpos( $html, '>In Sensei<' ), true );
ck( 'the dates print as one period, each date whole', false !== strpos( $html, '<span class="wpcpm-ui-whole">2026-02-16</span> to <span class="wpcpm-ui-whole">2026-06-30</span>' ), true );
ck( 'the cohort is derived from the start date', false !== strpos( $html, 'January to June 2026' ), true );
ck( 'the field of study prints', false !== strpos( $html, 'Computer Science' ), true );
ck( 'the WordPress.org profile links to the handle', false !== strpos( $html, 'https://profiles.wordpress.org/student-one/' ), true );
ck( "the student's own address is not a row", row_of( $html, 'Email' ), '' );
ck( 'and is nowhere else on the card either', false === strpos( $html, 'ana@example.test' ), true );

/* ---- 2. the mentor: a name and an address, and nothing else --------------- */

echo "\n=== The mentor is a name and an address ===\n";

ck( "the mentor's name prints", false !== strpos( $html, 'Marta Mentor' ), true );
ck( "the mentor's address prints", false !== strpos( $html, 'mailto:marta@example.test' ), true );

foreach ( array(
	'their WordPress.org profile' => 'mentor-one',
	'their Slack handle'          => 'marta.mentor',
	'their own website'           => 'marta.example.test',
	'where they live'             => 'Valencia',
) as $what => $needle ) {
	ck( sprintf( 'and not %s', $what ), false === strpos( $html, $needle ), true );
}

/* ---- 3. the accessibility disclosure is not on the card ------------------- */

echo "\n=== What was disclosed to the program stays there ===\n";

ck( "the student's own words are absent", false === strpos( $html, 'Needs captions on every call' ), true );
ck( 'and the field is not named anywhere in the markup', false === stripos( $html, 'accessib' ), true );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php' );
ck( 'and the source never names the field, so no loop can reach it', preg_match( '/[\'"]accessibility[\'"]/', $source ), 0 );

// The cells are written out, keyed by the column each is about, so `scope()` can narrow them
// the day a field-scoped ground exists.
$cells = new ReflectionMethod( 'WPCPM_Institution_Student_View', 'cells' );

if ( PHP_VERSION_ID < 80100 ) {
	$cells->setAccessible( true );
}

$keys    = array_keys( $cells->invoke( null, $GLOBALS['users'][10], WPCPM_Students_Sync::get_program( 10 ), $GLOBALS['roster'][ $inst_a ][ $row_a ], WPCPM_Students_Sync::get_mentor( 10 ) ) );
$unkeyed = array_values( array_filter( $keys, static function ( $key ) { return 1 !== preg_match( '/^[a-z]+\|.+$/', $key ); } ) );

ck( 'every cell is keyed "<table>|<field>" for scope()', $unkeyed, array() );
ck( 'and no cell names the disclosure', in_array( 'students|Accessibility needs', $keys, true ), false );
ck( "nor the student's own address", in_array( 'reports|Email', $keys, true ), false );
ck( "while the mentor's is still a cell", in_array( 'mentors|Email', $keys, true ), true );

/* ---- 4. every row prints, even when it is empty --------------------------- */

echo "\n=== Every row prints, even when it is empty ===\n";

$rows_full = substr_count( $html, '<tr role="row">' );

ck( 'the complete card has one row per cell', $rows_full, count( $keys ) );
ck( 'an empty tutor still has a row', false !== strpos( $html, 'Tutor' ), true );
ck( 'and it reads "Not set"', false !== strpos( $html, 'Not set' ), true );

$GLOBALS['uid'] = 21;
$bare           = card( $inst_b, 11, array() );

// A student with no mentor reads as normal (Institutions release 2): the Mentor row says "No mentor yet",
// as the roster's mentor cell does, and the Mentor email row is left out, since there is nobody to write to.
ck( 'a student the sync has barely seen has every row but the mentor\'s address', substr_count( $bare, '<tr role="row">' ), $rows_full - 1 );
ck( 'the mentor row says what its blank means, in the roster\'s words', row_of( $bare, 'Mentor' ), 'No mentor yet' );
ck( 'and the old words are gone', false !== strpos( $bare, 'No mentor assigned yet' ), false );
ck( 'there is no Mentor email row for a student with no mentor', array( row_of( $bare, 'Mentor email' ), false !== strpos( $bare, 'Mentor email' ) ), array( '', false ) );
ck( 'while the student who has one still has both rows', array( '' !== row_of( $html, 'Mentor email' ), 'Marta Mentor' === row_of( $html, 'Mentor' ) ), array( true, true ) );

$bare_cells = new ReflectionMethod( 'WPCPM_Institution_Student_View', 'cells' );

if ( PHP_VERSION_ID < 80100 ) {
	$bare_cells->setAccessible( true );
}

$bare_keys = array_keys( $bare_cells->invoke( null, $GLOBALS['users'][11], array(), array(), array() ) );
ck( 'the cell for the mentor\'s address is not among the keys when there is no mentor', array( in_array( 'mentors|Email', $bare_keys, true ), in_array( 'mentors|Full Name', $bare_keys, true ) ), array( false, true ) );

// A mentor the card knows by name and not by address keeps the row, and it says "Not set": the missing
// address is a fact about the record, and the row is the place the school looks for it.
$name_only = array_keys( $bare_cells->invoke( null, $GLOBALS['users'][11], array(), array(), array( 'name' => 'Marta Mentor' ) ) );
ck( 'a mentor with a name and no address still has the row', in_array( 'mentors|Email', $name_only, true ), true );
ck( 'a missing start date reads as no cohort rather than as nothing', false !== strpos( $bare, 'No start date' ), true );

/* ---- 5. the row labeled "Program" speaks one vocabulary ------------------ */

echo "\n=== The Program row speaks one vocabulary ===\n";

$GLOBALS['uid'] = 20;

// The Students table's `Status` is an application pipeline - `Interested`, `Not moving
// forward`, `Fail`, `SPAM`, and on this base one row whose status is the name of a school -
// and `WPCPM_Program::label()` passes anything it does not recognize straight through. The
// roster index carries that column, so a card that read it would print a raw pipeline value
// in the row a school reads as "Program". Put one in every place the card could reach.
$GLOBALS['roster'][ $inst_a ][ $row_a ]['status'] = 'Not moving forward';

// And a `status` key on the cached program row, which the students sync has never written:
// it was read first of the three, so the day a row grew one it would have taken the row and
// the badge with it.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['status'] = 'Fail';

$mixed = card( $inst_a, 10, array() );

ck( 'the program is read from the Students Reports status', false !== strpos( row_of( $mixed, 'Program' ), 'WordPress Credits Program 150h' ), true );
ck( 'the Students-table status is not printed', false === strpos( $mixed, 'Not moving forward' ), true );
ck( 'and neither is a stray key on the cached row', false === strpos( $mixed, 'Fail' ), true );

// No reports status at all, and the stray key still there: the row prints empty, which is the
// honest answer. A school told nothing is better off than a school told an Airtable value
// from the other table.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = '';

$stray_key = card( $inst_a, 10, array() );

ck( 'a cached row with only that key reads "Not set", the empty kind', array( row_of( $stray_key, 'Program' ), false !== strpos( $stray_key, 'Program</th><td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">Not set</td>' ) ), array( 'Not set', true ) );

// And with the stray key gone the index row is the last thing left to read, which is the
// branch that put a pipeline status on a school's screen in the first place.
unset( $GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['status'] );

$unknown_program = card( $inst_a, 10, array() );

ck( 'with no reports status the Program row reads "Not set"', row_of( $unknown_program, 'Program' ), 'Not set' );
ck( 'the index row is not read for it', false === strpos( $unknown_program, 'Not moving forward' ), true );
ck( 'and the tag is dropped rather than painted from it', false === strpos( $unknown_program, 'wpcpm-status' ), true );

$GLOBALS['roster'][ $inst_a ][ $row_a ]['status']                     = 'In Sensei';
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = 'In Sensei';

// A student waiting to graduate: the school reads the course she took where it reads a current
// student's, named and linked, with the Pending graduation badge beside it, while the badge beside
// her name stays the status the base holds.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = 'Pending graduation';
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_COURSE ]             = 'Developer Track';

$pending_card  = card( $inst_a, 10, array() );
$pending_badge = '<span class="wpcpm-status wpcpm-status--amber">Pending graduation</span>';

ck( 'a pending student\'s Program row names and links the course she took, with the Pending graduation tag beside it',
	row_of( $pending_card, 'Program' ),
	'<a href="' . WPCPM_Program::course_url( 'Developer Track' ) . '" target="_blank" rel="noopener noreferrer">Developer Track</a> ' . $pending_badge );
ck( 'and the tag under her name still says Pending graduation',
	1 === preg_match( '#<h2 class="wpcpm-ui-item__title">[^<]*</h2><p class="wpcpm-ui-item__facts">' . preg_quote( $pending_badge, '#' ) . '#', $pending_card ), true );

// No course known: the row says what it always said, the status alone, and links nowhere.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_COURSE ] = '';

ck( 'a pending student with no known course reads Pending graduation alone, with no link', row_of( card( $inst_a, 10, array() ), 'Program' ), 'Pending graduation' );

unset( $GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_COURSE ] );

// A student who has finished, and the other statuses the base writes that are not a track: the
// card says what the roster's row says ("Graduated", not the base's own "Graduate"), in the badge
// beside the name and in the Program row, while the badge keeps the color of the raw status.
$plain_words = array(
	'Graduate'                  => 'Graduated',
	'Fail'                      => 'Did not complete',
	'Not moving forward'        => 'Not continuing',
	'In Sensei Self-onboarding' => 'Getting started',
	'Dropped out'               => 'Dropped out',
);

foreach ( $plain_words as $raw => $printed ) {
	$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = $raw;

	$words_card = card( $inst_a, 10, array() );
	$badge_html = '<span class="wpcpm-status' . ( '' === WPCPM_Program::tone( $raw ) ? '' : ' wpcpm-status--' . WPCPM_Program::tone( $raw ) ) . '">' . $printed . '</span>';

	ck( "a student whose status is {$raw} has a tag under the name that says {$printed}",
		1 === preg_match( '#<h2 class="wpcpm-ui-item__title">[^<]*</h2><p class="wpcpm-ui-item__facts">' . preg_quote( $badge_html, '#' ) . '#', $words_card ), true );
	ck( "and a Program row that says {$printed}", row_of( $words_card, 'Program' ), $printed );
}

// And the base's own word is not left anywhere on a graduate's card.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = 'Graduate';

ck( "a graduate's card does not print the base's own word for the status", preg_match( '/\bGraduate\b/', card( $inst_a, 10, array() ) ), 0 );

// A current student on a compiled track reads as before, in the badge and in the row.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = 'Developer Track';

$track_card = card( $inst_a, 10, array() );

ck( "a compiled track's name is still what the Program row says, linked to its course",
	row_of( $track_card, 'Program' ),
	'<a href="' . WPCPM_Program::course_url( 'Developer Track' ) . '" target="_blank" rel="noopener noreferrer">Developer Track</a>' );
ck( 'and what the tag under the name says',
	1 === preg_match( '#<h2 class="wpcpm-ui-item__title">[^<]*</h2><p class="wpcpm-ui-item__facts"><span class="wpcpm-status wpcpm-status--green">Developer Track</span>#', $track_card ), true );

$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = 'In Sensei';

/* ---- 6. the identity block, from both sides ------------------------------- */

echo "\n=== The identity block and the stylesheet agree ===\n";

/**
 * One stylesheet's rules, without its comments.
 *
 * The comments name classes on purpose - "`.wpcpm-mentee__identity` from the mentor
 * stylesheet" is the reason that group is not defined here twice - and a search for a
 * selector must not be answered by the sentence saying it lives somewhere else.
 *
 * @param string $file Stylesheet path, from the plugin root.
 * @return string
 */
function css_rules( $file ) {
	return (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ) );
}

/**
 * Whether a stylesheet has a rule for one class.
 *
 * Matched to the end of the name, so `.wpcpm-institution__student-identity` does not answer
 * for `.wpcpm-institution__student-card` - which is the bug this block is about.
 *
 * @param string $css   Stylesheet rules.
 * @param string $class Class name, without the dot.
 * @return bool
 */
function has_rule( $css, $class ) {
	return 1 === preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![a-zA-Z0-9_-])/', $css );
}

$institution_css = css_rules( 'assets/css/institution.css' );
$dashboard_css   = css_rules( 'assets/css/dashboard.css' );

// The row the stylesheet lays out has to be the element the card prints. It was not: the
// avatar sat in an outer wrapper the stylesheet had no rule for, so it stacked above the
// name instead of standing beside it.
ck( 'the avatar opens the item header the parts lay out', 0 === strpos( $html, '<section class="wpcpm-ui-block"><div class="wpcpm-ui-item__head"><span class="wpcpm-ui-item__media"><img class="wpcpm-avatar"' ), true );
// The Gravatar fallback is a URL carrying a hash of the student's address: an identifier of the
// student handed to a third party's page and a third party's server. The card asks for the
// WordPress.org portrait only.
ck( 'the card never hands the student\'s address to the avatar service', array_unique( (array) ( $GLOBALS['avatar_emails'] ?? array() ) ), array( '' ) );
ck( 'and no wrapper the stylesheet has never heard of', false === strpos( $html, 'wpcpm-institution__student-card' ), true );



/* ---- 7. a stranger's user ID falls back to the roster --------------------- */

echo "\n=== A user ID that is not theirs falls back to the roster ===\n";

$GLOBALS['uid'] = 20;

ck( "a member of A cannot open B's student", WPCPM_Institution_Student_View::shows( 11 ), false );
ck( 'and asking anyway draws nothing at all', card( $inst_a, 11, array() ), '' );
ck( 'a user ID that belongs to nobody draws nothing', card( $inst_a, 999999, array() ), '' );
ck( 'and so does 0', card( $inst_a, 0, array() ), '' );

$GLOBALS['uid'] = 21;
ck( "a member of B cannot open A's student either", WPCPM_Institution_Student_View::shows( 10 ), false );
ck( 'and gets no card', card( $inst_b, 10, array() ), '' );

$GLOBALS['uid'] = 30;
ck( 'a manager opens any student', WPCPM_Institution_Student_View::shows( 10 ), true );
ck( 'but an account that is not a student is still not a card', WPCPM_Institution_Student_View::shows( 60 ), false );
ck( 'and draws nothing', card( $inst_a, 60, array() ), '' );

// The gate, from this side: a member of an institution whose agreement is not settled is
// refused by ground_member() itself, and the card is one more thing they cannot reach.
$GLOBALS['uid']     = 20;
$GLOBALS['settled'] = array( $inst_b );

ck( 'an unsettled agreement closes the card too', WPCPM_Institution_Student_View::shows( 10 ), false );
ck( 'and draws nothing', card( $inst_a, 10, array() ), '' );

$GLOBALS['settled'] = array( $inst_a, $inst_b );

// Institutions release 1, through the real policy: a tutor of settled A sees the student, and the card draws
// as it does for a representative, which is a read; the unsettled gate closes it for them as for anybody.
$GLOBALS['users'][25]       = new WP_User( 25, 'Tess Tutor', 'tess@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['memberships'][25] = array( $inst_a );
$GLOBALS['levels']          = array( 25 => 'tutor' );
$GLOBALS['uid']             = 25;
// The same facts: the header and the table (the controls hung off the card are each their own class's, per reader).
$facts_of = static function ( $html ) {
	return preg_match( '#^.*?</table>#s', (string) $html, $m ) ? (string) preg_replace( '#<div class="wpcpm-ui-block" id="wpcpm-student-invite">.*?</div>(?=<table)#s', '', $m[0] ) : '';
};
ck( 'a tutor of the student\'s institution opens the student and is drawn the card\'s facts, as a representative of it is', array( WPCPM_Institution_Student_View::shows( 10 ), '' !== card( $inst_a, 10, array() ), $facts_of( card( $inst_a, 10, array() ) ) === ( function () use ( $inst_a, $facts_of ) { $GLOBALS['uid'] = 20; return $facts_of( card( $inst_a, 10, array() ) ); } )() ), array( true, true, true ) );
ck( 'a tutor of A may read the Student Report Card of A\'s student, and not B\'s', array( may_read( 25, $report_a ), may_read( 25, $report_b ) ), array( true, false ) );
$GLOBALS['uid']     = 25;
$GLOBALS['settled'] = array( $inst_b );
ck( 'while the agreement is not settled they are drawn nothing', array( WPCPM_Institution_Student_View::shows( 10 ), card( $inst_a, 10, array() ) ), array( false, '' ) );
$GLOBALS['settled'] = array( $inst_a, $inst_b );
$GLOBALS['levels']  = array();

/* ---- 8. the Student Report Card, fetched when it is opened ---------------- */

echo "\n=== The Student Report Card arrives when it is opened ===\n";

$GLOBALS['uid']       = 20;
$GLOBALS['enqueued']  = array();
$GLOBALS['styles']    = array();
$GLOBALS['localized'] = array();

$html = card( $inst_a, 10, array( 'read' => 1756800000 ) );

ck( 'the disclosure carries the report record', false !== strpos( $html, 'data-wpcpm-report="' . $report_a . '"' ), true );
ck( 'the body is empty until it is fetched', false !== strpos( $html, 'data-wpcpm-report-body' ), true );
ck( 'it is named in full', false !== strpos( $html, 'Student Report Card' ), true );
ck( 'the script that fetches it is enqueued', in_array( WPCPM_Mentors_Dashboard::SCRIPT, $GLOBALS['enqueued'], true ), true );
ck( 'and told where the route is', isset( $GLOBALS['localized']['wpcpmDashboard']['reportEndpoint'] ) ? $GLOBALS['localized']['wpcpmDashboard']['reportEndpoint'] : '', 'https://example.test/wp-json/wpcpm/v1/report/' );

// The markup is the report form's own, and so are the rules for it: the disclosure, the
// toggle, the body and every field the body arrives with are dressed by the calendar
// stylesheet. Without this the school's copy of the Student Report Card was the only
// unstyled thing on the page.
ck( 'the stylesheet that dresses the report is enqueued', in_array( WPCPM_Call_Calendar::STYLE, $GLOBALS['styles'], true ), true );

$calendar_css = css_rules( 'assets/css/calendar.css' );

foreach ( array( 'wpcpm-report__disclosure', 'wpcpm-report__toggle', 'wpcpm-report__body', 'wpcpm-report__group' ) as $class ) {
	ck( sprintf( 'and it is the stylesheet holding .%s', $class ), has_rule( $calendar_css, $class ), true );
}

// The one place the page stylesheet may name that markup is the spacing above its own block,
// and it names it compounded with its own class: the calendar stylesheet gives the disclosure
// a margin too, and one class each would leave the winner to whichever of the two files the
// page happened to print last. Everything else about the report stays in one file.
preg_match_all( '/\.wpcpm-report__[a-zA-Z0-9_-]+/', $institution_css, $named );


// The note class is not this page's own either. It comes with that same markup, and the
// stylesheet defining it is the student dashboard's whole dress, which this page has no other
// use for: one muted line in institution.css rather than a second stylesheet for a paragraph.
ck( 'the note the card prints is dressed by the page stylesheet', has_rule( $institution_css, 'wpcpm-student__note' ), true );

// A student with no report record: the disclosure would fetch a record that does not exist.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_RECORD_ID ] = '';
$GLOBALS['styles']                                           = array();

$without = card( $inst_a, 10, array() );

ck( 'a student with no report record gets no disclosure', false === strpos( $without, 'data-wpcpm-report=' ), true );
ck( 'and is told why, in the words a school uses for it', false !== strpos( $without, 'This student has no progress record yet, so there is no Student Report Card to show.' ), true );
ck( 'with no record kind, table or data store named', array( false !== strpos( $without, 'report record' ), false !== strpos( $without, 'program data' ), false !== strpos( $without, 'Airtable' ) ), array( false, false, false ) );
ck( 'and a card with no report to draw loads no stylesheet for one', in_array( WPCPM_Call_Calendar::STYLE, $GLOBALS['styles'], true ), false );

$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_RECORD_ID ] = $report_a;

/* ---- 9. the read times ---------------------------------------------------- */

echo "\n=== The card says how old what it shows is ===\n";

ck( 'the roster read time is printed', false !== strpos( $html, 'The roster this student is on was read 2 hours ago.' ), true );
ck( "the account's own sync time is printed", false !== strpos( $html, 'Their own details were synced 2 hours ago.' ), true );
ck( 'and the live read is named as live', false !== strpos( $html, 'read when you open it' ), true );

// No read time in the context: the index answers for itself rather than the footer going quiet.
$html_no_context = card( $inst_a, 10, array() );
ck( 'the index answers when the caller passes no read time', false !== strpos( $html_no_context, 'The roster this student is on was read' ), true );

/* ---- 10. the report route's three audiences -------------------------------- */

echo "\n=== The report route, and its third audience ===\n";

/**
 * Ask the route's permission callback as one user.
 *
 * @param int    $user_id Who is asking.
 * @param string $record  Students Reports record ID.
 * @return bool|WP_Error
 */
function may_read( $user_id, $record ) {
	$GLOBALS['uid'] = (int) $user_id;

	return WPCPM_Student_Report_Form::rest_permission( new WP_REST_Request( array( 'record' => $record ) ) );
}

$GLOBALS['live_reads'] = 0;

ck( 'a mentor may read their own student', may_read( 40, $report_a ), true );
ck( 'and spends no live read doing it', $GLOBALS['live_reads'], 0 );
ck( 'a mentor may not read somebody else\'s', may_read( 40, $report_b ), false );
ck( 'a program manager may read any', may_read( 30, $report_b ), true );
ck( 'nobody at all may read', may_read( 0, $report_a ), false );

$GLOBALS['live_reads'] = 0;

ck( 'a member may read a student on their own roster', may_read( 20, $report_a ), true );
ck( 'and that one did cost a live read', $GLOBALS['live_reads'], 1 );

$GLOBALS['live_reads'] = 0;

ck( "a member of B may not read A's student", may_read( 21, $report_a ), false );
ck( 'and the refusal spent nothing', $GLOBALS['live_reads'], 0 );
ck( 'an unknown record is refused the same way', may_read( 20, $stray ), false );
ck( 'and so is a record ID that is not one', may_read( 20, 'not-a-record' ), false );

// The one refusal, byte for byte. "Not yours", "no such record", "not a member" and "agreement
// outstanding" are one message: a form that answered differently would be a membership oracle.
$GLOBALS['uid'] = 21;
$refused        = WPCPM_Institution_Roster::claim( $report_a, WPCPM_Institution_Policy::ACT_VIEW_REPORT, 'report' );

ck( 'what claim() returned is the fence\'s refusal', is_wp_error( $refused ), true );
ck( 'with the one code', $refused->get_error_code(), WPCPM_Institution_Policy::REFUSAL_CODE );
ck( 'and the one message, byte for byte', $refused->get_error_message(), WPCPM_Institution_Policy::refusal()->get_error_message() );

$GLOBALS['uid'] = 20;
$unknown        = WPCPM_Institution_Roster::claim( $stray, WPCPM_Institution_Policy::ACT_VIEW_REPORT, 'report' );

ck( 'an unknown record gives the same message', $unknown->get_error_message(), $refused->get_error_message() );

// The route answers false rather than handing the WP_Error to the REST server: one route, three
// audiences, and a member must not be able to tell from outside which question the site asked.
ck( 'the route itself answers false, never the WP_Error', may_read( 21, $report_a ), false );

$GLOBALS['settled'] = array( $inst_b );
ck( 'an unsettled agreement closes the route as well', may_read( 20, $report_a ), false );
$GLOBALS['settled'] = array( $inst_a, $inst_b );

/* ---- 11. the route still reads the mentee list first ----------------------- */

echo "\n=== The order the route asks in ===\n";

$route = new ReflectionMethod( 'WPCPM_Student_Report_Form', 'rest_permission' );
$body  = implode(
	"\n",
	array_slice(
		file( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php' ),
		$route->getStartLine() - 1,
		$route->getEndLine() - $route->getStartLine() + 1
	)
);

ck( 'the capability is asked before anything else', strpos( $body, 'current_user_can' ) < strpos( $body, 'get_mentees' ), true );
ck( 'and the mentee list before the claim, which may cost a request', strpos( $body, 'get_mentees' ) < strpos( $body, '::claim(' ), true );
ck( 'the claim is guarded, so the route works without the module', false !== strpos( $body, "class_exists( 'WPCPM_Institution_Roster' )" ), true );

/* ---- 12. one argument, two files ------------------------------------------ */

echo "\n=== The roster's links and this card's argument are one name ===\n";

// Source level, and skipped when that piece is not in the checkout: the roster builds the links
// and this card reads them, so a rename on either side is a list of names that lead nowhere.
$roster_view = WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';

if ( file_exists( $roster_view ) ) {
	$declared = preg_match( '/ARG_STUDENT\s*=\s*\'([^\']+)\'/', (string) file_get_contents( $roster_view ), $m ) ? $m[1] : '';

	ck( "the roster's student links carry this argument", $declared, WPCPM_Institution_Student_View::ARG );
} else {
	echo "     the roster view is not in this checkout, so its links were not read\n";
}

/* ---- 13. the card on the parts (Institution Dashboard, Task 16) ----------- */

echo "\n=== A student's card on the parts (Institution Dashboard, Task 16) ===\n";


// The long fixture: a 40-character unbroken word in the student's name, a 48-character mentor address, a long website, a
// long tutor's name, on institution A, with every row filled.
$long      = 70;
$report_l  = rid( 'REPORTLONG' );
$row_l     = rid( 'ROWLONG' );
$GLOBALS['users'][ $long ] = new WP_User( $long, 'Ama Exampleunbrokenstudentnamewithfortychars', 'ama@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][22]     = new WP_User( 22, 'Tom Tutor', 'tom@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['memberships'][22] = array( $inst_a );
$GLOBALS['umeta'][ $long ] = array(
	WPCPM_Students_Sync::META_RECORD_ID   => $report_l,
	WPCPM_Students_Sync::META_INSTITUTION => $inst_a,
	WPCPM_Students_Sync::META_UPDATED     => 1756800000,
	WPCPM_Students_Sync::META_PROGRAM     => array(
		'name'           => 'Ama Exampleunbrokenstudentnamewithfortychars',
		'program'        => 'In Sensei',
		'start'          => '2026-02-16',
		'end'            => '2026-09-30',
		'username'       => 'unbrokenusernamewithfortycharsofexample',
		'team'           => 'Documentation',
		'website'        => 'https://averyveryveryverylongsubdomainnameofthe.example.org/a/long/path/to/the/page',
		'field_of_study' => 'Technology & Engineering',
		'tutor'          => 'Dr Exampleunbrokentutornamewithfortycharss',
		'accessibility'  => 'Needs captions on every call',
	),
	WPCPM_Students_Sync::META_MENTOR      => array(
		'name'  => 'Mia Exampleunbrokenmentornamewithfortycharss',
		'email' => 'averyveryveryverylongmailboxname1234@example.org',
	),
);
$GLOBALS['roster'][ $inst_a ][ $row_l ] = array(
	'record_id'      => $row_l,
	'name'           => 'Ama Exampleunbrokenstudentnamewithfortychars',
	'email'          => 'ama@example.test',
	'email_key'      => 'ama@example.test',
	'status'         => 'In Sensei',
	'institution'    => $inst_a,
	'start'          => '2026-02-16',
	'end'            => '2026-09-30',
	'username'       => 'unbrokenusernamewithfortycharsofexample',
	'field_of_study' => 'Technology & Engineering',
	'tutor'          => 'Dr Exampleunbrokentutornamewithfortycharss',
	'reports'        => array( $report_l ),
	'user_id'        => $long,
);
$GLOBALS['roster'][ $inst_a ][ $row_a ]['email_key'] = 'ana@example.test';
$GLOBALS['opts']['date_format']                       = 'F j, Y';

/** Whether a needle is in a haystack. */
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }

/** One card as one reader, with a fresh flash, kept typing and enqueue log. */
function card_as( $viewer, $institution, $user_id, array $context = array( 'read' => 1756800000 ) ) {
	$GLOBALS['uid']       = (int) $viewer;
	$GLOBALS['enqueued']  = array();
	$GLOBALS['localized'] = array();
	return card( $institution, $user_id, $context );
}

/** The refused correction: its sentence, the boxes it refused, and the typing it kept. */
function refuse_correction( $report ) {
	$GLOBALS['flash']['institution_student'] = array( 'status' => 'student-partly', 'detail' => 'Start date, End date.', 'record' => $report );
	$GLOBALS['kept_flash']                   = array( 'problems' => array(
		'wpcpm-student-' . WPCPM_Institution_Student_Form::key( 'students', 'Start Date' ) => 'An end date is at most a year after the start date.',
		'wpcpm-student-' . WPCPM_Institution_Student_Form::key( 'students', 'End Date' )   => 'An end date is at most a year after the start date.',
	) );
	$GLOBALS['kept_typing'] = array( 'students|End Date' => '2036-06-30', 'reports|Name' => 'Ama & <b>Q&amp;A</b>' );
}

/** Forget what the last case set. */
function forget_case() {
	$GLOBALS['flash']       = array();
	$GLOBALS['kept_flash']  = array();
	$GLOBALS['kept_typing'] = array();
	$GLOBALS['levels']      = array();
	unset( $GLOBALS['wpcpm_stub_filters']['wpcpm_institution_graduation_offered'] );
}

/**
 * The cards the suite pins on both branches: a representative's, an Administrator's and a tutor's, the long student's
 * with every row, a student with no mentor, the correction refused, and the status card with graduating on.
 *
 * @return string[] By case.
 */
function student_cases() {
	global $inst_a, $inst_b, $long, $report_l;
	$cases = array();
	forget_case();
	$cases['a representative']                = card_as( 20, $inst_a, 10 );
	$cases['an Administrator']                = card_as( 30, $inst_a, 10 );
	$GLOBALS['levels']                        = array( 22 => 'tutor' );
	$cases['a tutor']                         = card_as( 22, $inst_a, 10 );
	forget_case();
	$cases['the long student']                = card_as( 20, $inst_a, $long );
	$cases['no mentor']                       = card_as( 21, $inst_b, 11 );
	refuse_correction( $report_l );
	$cases['the correction refused']          = card_as( 20, $inst_a, $long );
	forget_case();
	add_filter( 'wpcpm_institution_graduation_offered', '__return_true' );
	$cases['graduating on']                   = card_as( 20, $inst_a, $long );
	forget_case();
	return $cases;
}
function __return_true() { return true; }

$sv_digest = static function ( array $cases ) {
	return array_map(
		static function ( $html ) {
			return md5( $html );
		},
		$cases
	);
};

// What the old card offered and said, read from the classes at db850661 (the last commit that drew it) before it was
// deleted (Task 23): every reader's facts (a digest of `card_facts()` by case), and each case's controls as
// `card_actions()` reads them (the forms, the export, the report, the photo). The parts are held to them.
$old_facts_digest = '862fc0f22c87a66b796c624ad9a950e6';
$old_tutor_facts  = '835b7db0e61f93e33ebb928862afefaa';
$sv_forms_a       = array( 'wpcpm_change_student_status:withdrawn', 'wpcpm_institution_resend_invite', 'wpcpm_save_student' );
$sv_export        = 'https://example.test/wp-admin/admin-post.php?action=wpcpm_export_student&user=%d&_wpnonce=nonce-export';
$sv_ana           = 'https://wordpress.org/grav-redirect.php?user=student-one';
$sv_ama           = 'https://wordpress.org/grav-redirect.php?user=unbrokenusernamewithfortycharsofexample';
$old_actions      = array(
	'a representative'       => array( $sv_forms_a, sprintf( $sv_export, 10 ), 'recREPORTA0000000', $sv_ana ),
	'an Administrator'       => array( $sv_forms_a, sprintf( $sv_export, 10 ), 'recREPORTA0000000', $sv_ana ),
	'a tutor'                => array( array(), '', 'recREPORTA0000000', $sv_ana ),
	'the long student'       => array( $sv_forms_a, sprintf( $sv_export, 70 ), 'recREPORTLONG0000', $sv_ama ),
	'no mentor'              => array( array( 'wpcpm_institution_resend_invite', 'wpcpm_save_student' ), sprintf( $sv_export, 11 ), 'recREPORTB0000000', '' ),
	'the correction refused' => array( $sv_forms_a, sprintf( $sv_export, 70 ), 'recREPORTLONG0000', $sv_ama ),
	'graduating on'          => array( array_merge( array( 'wpcpm_change_student_status:graduated' ), $sv_forms_a ), sprintf( $sv_export, 70 ), 'recREPORTLONG0000', $sv_ama ),
);
// What the old card enqueued and handed its script.
$enqueue_old = array(
	array( 'wpcpm-mentor-dashboard' ),
	array(
		'wpcpmDashboard' => array(
			'reportEndpoint' => 'https://example.test/wp-json/wpcpm/v1/report/',
			'nonce'          => 'nonce-wp_rest',
			'strings'        => array( 'failed' => 'That report could not be loaded just now. Close this and open it again to retry.' ),
		),
	),
);
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
WPCPM_UI::enter( 'institution' );
$new_student = student_cases();
$GLOBALS['avatar_emails'] = array();
$enqueue_check            = array( card_as( 20, $inst_a, 10 ), $GLOBALS['enqueued'], $GLOBALS['styles'], $GLOBALS['localized'] );
$avatar_emails_new        = $GLOBALS['avatar_emails'];
WPCPM_UI::leave();
WPCPM_UI::set_live( null );

/** Everything between two markers, the first included. */
function between( $html, $from, $to = null ) {
	$start = strpos( (string) $html, $from );
	if ( false === $start ) {
		return '';
	}
	$end = null === $to ? false : strpos( (string) $html, $to, $start + strlen( $from ) );
	return false === $end ? substr( (string) $html, $start ) : substr( (string) $html, $start, $end - $start );
}

/** The table's rows as a reader reads them: each label to its value's words. */
function card_facts( $html ) {
	$facts = array();
	preg_match_all( '#<tr\b[^>]*>\s*<th\b[^>]*>(.*?)</th>\s*<td\b[^>]*>(.*?)</td>#s', (string) $html, $rows, PREG_SET_ORDER );
	foreach ( $rows as $row ) {
		$words = static function ( $cell ) {
			return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $cell ), ENT_QUOTES ) ) );
		};
		$facts[ $words( $row[1] ) ] = $words( $row[2] );
	}
	return $facts;
}

/** What a card offers: each form's action and state, the export's address, the report it fetches, the portrait's address. */
function card_actions( $html ) {
	preg_match_all( '#<form\b[^>]*>(.*?)</form>#s', (string) $html, $forms );
	$offers = array();
	foreach ( $forms[1] as $form ) {
		preg_match( '/name="action" value="([^"]*)"/', $form, $action );
		preg_match( '/name="state" value="([^"]*)"/', $form, $state );
		$offers[] = ( $action[1] ?? '' ) . ( isset( $state[1] ) ? ':' . $state[1] : '' );
	}
	sort( $offers );
	preg_match( '#href="([^"]*wpcpm_export_student[^"]*)"#', (string) $html, $export );
	preg_match( '#data-wpcpm-report="([^"]*)"#', (string) $html, $report );
	preg_match( '#<img class="wpcpm-avatar" src="([^"]*)"#', (string) $html, $portrait );
	return array( $offers, $export[1] ?? '', $report[1] ?? '', $portrait[1] ?? '' );
}

$nc = $new_student['the long student'];
$sk = 'Exampleunbrokenstudentnamewithfortychars';
ck( 'on the parts: a block with no inset of its own, the item header first: the photo, the name as plain words as the one heading, the program\'s tag, the dates and the cohort as facts', array(
	0 === strpos( $nc, '<section class="wpcpm-ui-block"><div class="wpcpm-ui-item__head"><span class="wpcpm-ui-item__media"><img class="wpcpm-avatar" src="https://wordpress.org/grav-redirect.php?user=unbrokenusernamewithfortycharsofexample" width="44" height="44" alt="" loading="lazy" decoding="async" /></span><div class="wpcpm-ui-item__lead"><h2 class="wpcpm-ui-item__title">Ama ' . $sk . '</h2>' ),
	has( $nc, '<p class="wpcpm-ui-item__facts"><span class="wpcpm-status wpcpm-status--green">WordPress Credits Program 150h</span><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact"><span class="wpcpm-ui-whole">2026-02-16</span> to <span class="wpcpm-ui-whole">2026-09-30</span></span><span class="wpcpm-ui-item__fact">January to June 2026</span></span></p></div></div>' ),
), array( true, true ) );
ck( 'one name per page: no heading but the item header holds it, no first-level heading, and no old class', array(
	preg_match_all( '#<h[1-6][^>]*>[^<]*Ama #', $nc ),
	has( $nc, '<h1' ),
	preg_match( '#class="[^"]*wpcpm-(?:institution|mentee|badge|student__|report__)#', $nc ),
	has( $nc, 'wpcpm-button' ),
), array( 1, false, 0, false ) );
ck( 'and no Back to the roster: the menu\'s Students item leads back', array( has( $nc, 'Back to the roster' ), has( $nc, 'wpcpm-institution__back' ) ), array( false, false ) );
ck( 'with no username, the name\'s first letter in the photo\'s box, as the roster draws it', has( $new_student['no mentor'], '<span class="wpcpm-ui-item__media"><span class="wpcpm-ui-item__initials" aria-hidden="true">B</span></span>' ), true );
ck( 'the photo is the WordPress.org one, keyed on the username, never on the address, for exactly the readers and students it shows for today', array(
	array_map( static function ( $html ) { return card_actions( $html )[3]; }, $new_student ),
	array_values( array_unique( $avatar_emails_new ) ),
), array( array_map( static function ( $actions ) { return $actions[3]; }, $old_actions ), array( '' ) ) );

ck( 'the sign-in line follows the header, under its anchor, Resend invitation secondary', 1 === preg_match( '#</div></div><div class="wpcpm-ui-block" id="wpcpm-student-invite"><p class="wpcpm-ui-intro">No sign-in on record since <span class="wpcpm-ui-whole">October 15, 2026</span>\. Invitation last sent <span class="wpcpm-ui-whole">November 1, 2026</span>\.</p><form method="post" [^>]*>.*?<button type="submit" class="wpcpm-btn wpcpm-btn--secondary">Resend invitation</button></div></form></div><table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table"><caption class="screen-reader-text">Program details for Ama Exampleunbrokenstudentnamewithfortychars</caption><tbody role="rowgroup">#s', $nc ), true );
ck( 'the key and value table: today\'s ten rows in today\'s order', array_keys( card_facts( $nc ) ), array( 'Program', 'Internship duration', 'Cohort', 'Field of study', 'Tutor', 'WordPress.org', 'Contribution teams', 'Personal website', 'Mentor', 'Mentor email' ) );
ck( 'the address, the profile and the website are token cells; the names are plain words the key and value table breaks (no `<wbr>`, Task 22b); the dates whole', array(
	has( $nc, '<td class="wpcpm-ui-cell--token" role="cell"><a href="mailto:averyveryveryverylongmailboxname1234@example.org">averyveryveryverylongmailboxname1234@example.org</a></td>' ),
	has( $nc, '<td class="wpcpm-ui-cell--token" role="cell"><a href="https://profiles.wordpress.org/unbrokenusernamewithfortycharsofexample/" target="_blank" rel="noopener noreferrer">@unbrokenusernamewithfortycharsofexample</a></td>' ),
	has( $nc, '<td class="wpcpm-ui-cell--token" role="cell"><a href="https://averyveryveryverylongsubdomainnameofthe.example.org/a/long/path/to/the/page" target="_blank" rel="noopener noreferrer">averyveryveryverylongsubdomainnameofthe.example.org/a/long/path/to/the/page</a></td>' ),
	has( $nc, '<td class="wpcpm-ui-cell--text" role="cell">Mia Exampleunbrokenmentornamewithfortycharss</td>' ),
	has( $nc, '<td class="wpcpm-ui-cell--text" role="cell">Dr Exampleunbrokentutornamewithfortycharss</td>' ),
	has( $nc, '<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Internship duration</th><td class="wpcpm-ui-cell--text" role="cell"><span class="wpcpm-ui-whole">2026-02-16</span> to <span class="wpcpm-ui-whole">2026-09-30</span></td>' ),
	substr_count( implode( '', $new_student ), '<wbr>' ),
), array( true, true, true, true, true, true, 0 ) );
ck( '"Not set" and "No mentor yet" are kept in words, muted (the empty kind); a student with no mentor has no Mentor email row', array(
	has( $new_student['a representative'], '<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Tutor</th><td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">Not set</td>' ),
	has( $new_student['no mentor'], '<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Mentor</th><td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">No mentor yet</td>' ),
	isset( card_facts( $new_student['no mentor'] )['Mentor email'] ),
), array( true, true, false ) );
ck( 'every reader reads the facts the old card said', md5( serialize( array_map( 'card_facts', $new_student ) ) ), $old_facts_digest );
ck( 'and is offered the controls the old card offered: the same forms, the same export, the same report, the same photo', array_map( 'card_actions', $new_student ), $old_actions );
ck( 'what was disclosed to the program, and the student\'s own address, are on no card', array(
	array_sum( array_map( static function ( $html ) { return (int) has( $html, 'Needs captions' ) + (int) has( $html, 'ama@example.test' ) + (int) has( $html, 'ana@example.test' ); }, $new_student ) ),
), array( 0 ) );

ck( 'the Student Report Card is an inner fold carrying today\'s fetch hook, its body the one the script replaces, with today\'s two sentences', has( $nc, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" data-wpcpm-report="' . $report_l . '"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Student Report Card</span></span>' ) && has( $nc, '</summary><div class="wpcpm-ui-fold__body"><div data-wpcpm-report-body><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Loading…</p><noscript><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">This one needs JavaScript' ), true );
ck( 'fetched as today: the same script, its settings and the report\'s sheet', array( $enqueue_check[1], $enqueue_check[3], in_array( 'wpcpm-call-calendar', $enqueue_check[2], true ) ), array( $enqueue_old[0], $enqueue_old[1], true ) );
ck( 'a student with a progress record is never told there is none (the sentence for one with none is pinned above)', array(
	has( $new_student['no mentor'], '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">This student has no progress record yet, so there is no Student Report Card to show.</p>' ),
	has( $new_student['no mentor'], 'no progress record yet' ),
), array( false, false ) );
ck( 'Correct this student\'s details is an inner fold under the form\'s anchor, closed, the form inside it', 1 === preg_match( '#<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-student-form"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Correct this student&\#039;s details</span></span>.*?</summary><div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">These five details are yours to correct\.#s', $nc ), true );
$refused_new = $new_student['the correction refused'];
ck( 'and open when its save left a sentence: the refusal tied to its box, the typing kept', array(
	has( $refused_new, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-student-form">' ),
	has( $refused_new, 'aria-invalid="true" aria-describedby="wpcpm-student-' . WPCPM_Institution_Student_Form::key( 'students', 'End Date' ) . '-problem"' ),
	has( $refused_new, 'value="2036-06-30"' ),
	has( $refused_new, 'value="Ama &amp;amp; &lt;b&gt;Q&amp;amp;amp;A&lt;/b&gt;"' ) || has( $refused_new, 'value="Ama &amp; &lt;b&gt;Q&amp;amp;A&lt;/b&gt;"' ),
), array( true, true, true, true ) );
ck( 'the download is a quiet link, a line of its own that wraps, after the folds, then the read line, muted', 1 === preg_match( '#</details><p class="wpcpm-ui-intro"><a href="https://example\.test/wp-admin/admin-post\.php\?action=wpcpm_export_student&user=70&_wpnonce=nonce-export">Download this student&\#039;s details and course grades as a CSV file</a></p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">The roster this student is on was read 2 hours ago\. Their own details were synced 2 hours ago\. The Student Report Card is read when you open it\.</p></section>#', $nc ), true );
ck( 'then Finishing this placement, its own card after the student\'s block, with one Decide row', array(
	has( $nc, '</section><section class="wpcpm-ui-fold-card" id="wpcpm-student-status"><details class="wpcpm-ui-fold" open>' ),
	substr_count( $new_student['graduating on'], 'wpcpm-ui-decide' ),
	has( $new_student['graduating on'], '>Mark as graduated</button>' ),
	has( $nc, '>Mark as graduated</button>' ),
	has( $nc, '<button type="submit" class="wpcpm-btn wpcpm-btn--danger" data-wpcpm-confirm="Mark Ama Exampleunbrokenstudentnamewithfortychars as dropped out?' ),
), array( true, 1, true, false, true ) );
ck( 'the three anchors a mail, a redirect or a link lands on are each on the page once', array( substr_count( $nc, 'id="wpcpm-student-form"' ), substr_count( $nc, 'id="wpcpm-student-status"' ), substr_count( $nc, 'id="wpcpm-student-invite"' ) ), array( 1, 1, 1 ) );
ck( 'every form on the card carries the referer it posts, as the old card\'s four did', array( substr_count( $new_student['graduating on'], '<form' ), substr_count( $new_student['graduating on'], 'name="_wp_http_referer"' ) ), array( 4, 4 ) );
ck( 'no two ids on the parts\' card are the same', count( array_unique( preg_match_all( '/\sid="([^"]+)"/', $new_student['graduating on'], $ids ) ? $ids[1] : array() ) ), count( $ids[1] ) );
ck( 'the tutor\'s card is the tutor\'s: the old card\'s facts and no control the tutor is not given', array( md5( serialize( card_facts( $new_student['a tutor'] ) ) ), card_actions( $new_student['a tutor'] ) ), array( $old_tutor_facts, $old_actions['a tutor'] ) );
$view_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php' );
ck( 'the rules the card needs are written once: the export\'s fence, the photo\'s source, whether the correction is drawn and open', array(
	substr_count( $view_src, 'WPCPM_Institution_Policy::ACT_EXPORT' ),
	substr_count( $view_src, 'WPCPM_Mentors_Dashboard::avatar_url(' ),
	substr_count( $view_src, "WPCPM_Institution_Student_Form::render_form(\n" ),
	substr_count( $view_src, 'strpos( $form, WPCPM_Institution_Student_Form::CLASS_MESSAGE )' ),
), array( 1, 1, 1, 1 ) );

// Fix round 2, Minor 4: the program's tag takes the tone that matches today's badge, for every status: a track (today's
// blue, purple, teal or rose badge) green, as the roster's tags are; Pending graduation (today's amber badge) amber; Paused
// (today's gray badge) and a status with no badge (Graduate, Dropped out) neutral. In the header and, for a student waiting
// to graduate, beside the course in the Program row.
$tone_of   = array();
$saved_row = $GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ];
$saved_crs = $GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_COURSE ] ?? null;
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
WPCPM_UI::enter( 'institution' );
foreach ( array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Graduate', 'Dropped out' ) as $st ) {
	$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['program'] = $st;
	$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_COURSE ]             = 'Pending graduation' === $st ? 'Developer Track' : '';
	$tone_html = card_as( 20, $inst_a, 10 );
	preg_match( '#<p class="wpcpm-ui-item__facts"><span class="wpcpm-status( wpcpm-status--[a-z]+)?">#', $tone_html, $tm );
	$tone_of[ $st ] = isset( $tm[1] ) ? substr( $tm[1], strlen( ' wpcpm-status--' ) ) : 'neutral';
	if ( 'Pending graduation' === $st ) {
		$tone_of['Pending graduation, in the Program row'] = 1 === preg_match( '#</a> <span class="wpcpm-status wpcpm-status--amber">Pending graduation</span></td>#', $tone_html ) ? 'amber' : 'other';
	}
}
WPCPM_UI::leave();
WPCPM_UI::set_live( null );
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ] = $saved_row;
if ( null === $saved_crs ) {
	unset( $GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_COURSE ] );
} else {
	$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_COURSE ] = $saved_crs;
}
ck( 'and the tone is the one map the roster asks too (WPCPM_Program::tone()), with no tone written in the card\'s class', array(
	array_map( static function ( $st ) { return method_exists( 'WPCPM_Program', 'tone' ) ? ( '' === WPCPM_Program::tone( $st ) ? 'neutral' : WPCPM_Program::tone( $st ) ) : 'no map'; }, array( 'In Sensei' => 'In Sensei', 'In Sensei 50h' => 'In Sensei 50h', 'Developer Track' => 'Developer Track', 'Paused' => 'Paused', 'Pending graduation' => 'Pending graduation', 'Graduate' => 'Graduate', 'Dropped out' => 'Dropped out' ) ),
	substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php' ), "'green'" ),
	substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php' ), 'WPCPM_Program::tone(' ),
), array( array_diff_key( $tone_of, array( 'Pending graduation, in the Program row' => true ) ), 0, 2 ) );
ck( 'the program\'s tag takes the tone of today\'s badge, status by status', $tone_of, array(
	'In Sensei'                             => 'green',
	'In Sensei 50h'                         => 'green',
	'Developer Track'                       => 'green',
	'Paused'                                => 'neutral',
	'Pending graduation'                    => 'amber',
	'Pending graduation, in the Program row' => 'amber',
	'Graduate'                              => 'neutral',
	'Dropped out'                           => 'neutral',
) );
// Fix round 2, Minor 1: the dates' words are format_dates()'s own, each date wrapped whole.
$dates_seen = array();
$dates_row  = $GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ];
$dates_idx  = $GLOBALS['roster'][ $inst_a ][ $row_a ];
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
WPCPM_UI::enter( 'institution' );
foreach ( array( array( '2026-02-16', '2026-09-30' ), array( '2026-02-16', '' ), array( '', '2026-09-30' ) ) as $pair ) {
	$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['start'] = $pair[0];
	$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['end']   = $pair[1];
	$GLOBALS['roster'][ $inst_a ][ $row_a ]['start']                    = $pair[0];
	$GLOBALS['roster'][ $inst_a ][ $row_a ]['end']                      = $pair[1];
	$dates_html = card_as( 20, $inst_a, 10 );
	preg_match( '#>Internship duration</th><td[^>]*>(.*?)</td>#', $dates_html, $dm );
	$dates_seen[] = array( html_entity_decode( strip_tags( $dm[1] ?? '' ) ), substr_count( $dm[1] ?? '', 'wpcpm-ui-whole' ) );
}
WPCPM_UI::leave();
WPCPM_UI::set_live( null );
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ] = $dates_row;
$GLOBALS['roster'][ $inst_a ][ $row_a ]                    = $dates_idx;
ck( 'the parts\' dates read as format_dates() words them, each date whole, for both dates, the start alone and the end alone', $dates_seen, array(
	array( WPCPM_Mentors_Dashboard::format_dates( '2026-02-16', '2026-09-30' ), 2 ),
	array( WPCPM_Mentors_Dashboard::format_dates( '2026-02-16', '' ), 1 ),
	array( WPCPM_Mentors_Dashboard::format_dates( '', '2026-09-30' ), 1 ),
) );
$dates_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php' );
ck( 'and the words are never written a second time in the card\'s class', array( substr_count( $dates_src, "'%1\$s to %2\$s'" ), substr_count( $dates_src, "'From %s'" ), substr_count( $dates_src, 'WPCPM_Mentors_Dashboard::format_dates( $' ) ), array( 0, 0, 2 ) );

// Fix round 2, Important 1: the card's three outcomes are the dashboards' shared message in their own classes: a saved
// correction and a sent invitation polite, a refused status press alerting, each the focus target forms.js moves to.
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
WPCPM_UI::enter( 'institution' );
forget_case();
$GLOBALS['flash']['institution_student']        = array( 'status' => 'student-saved', 'detail' => '', 'record' => $report_l );
$GLOBALS['flash']['institution_resend']         = array( 'outcome' => 'invite-resent', 'user' => $long, 'detail' => 'a***@example.test' );
$GLOBALS['flash']['institution_student_status'] = array( 'outcome' => 'status-paused', 'detail' => 'Paused', 'record' => $row_l );
$said_card = card_as( 20, $inst_a, $long );
forget_case();
WPCPM_UI::leave();
WPCPM_UI::set_live( null );
ck( 'on the parts the card\'s outcomes are the shared message: the save and the invitation polite, the refused press alerting, all three focus targets', array(
	1 === preg_match( '#<p class="wpcpm-institution__edit-message wpcpm-institution__edit-message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Saved\.#', $said_card ),
	1 === preg_match( '#<p class="wpcpm-institution__invite-message wpcpm-institution__invite-message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>#', $said_card ),
	1 === preg_match( '#<p class="wpcpm-institution__status-message wpcpm-institution__status-message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>Nothing was changed\.#', $said_card ),
	has( $said_card, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-student-form">' ),
), array( true, true, true, true ) );

// The whole view on the real card (Task 23, fix round 1): every reader's and state's card, and the card holding
// three messages, under the Students view's own head (`bin/stubs/view-faults.php`).
ck( 'the whole Students view on a student\'s real card, every reader and state: one H1, the headings in order, no id twice, the lede once, no title again', wpcpm_view_faults_of( 'Students', $new_student + array( 'three messages at once' => $said_card ) ), array() );

if ( function_exists( 'wpcpm_layout_keep' ) ) {
	$status_of = static function ( $html ) {
		return between( $html, '<section class="wpcpm-ui-fold-card" id="wpcpm-student-status">' );
	};
	wpcpm_layout_keep( 'Student card, parts: a representative, every fold, a 40-character name, a 48-character mentor email, a long website', $nc );
	wpcpm_layout_keep( 'Student card, parts: the correction refused and its typing kept', $new_student['the correction refused'] );
	wpcpm_layout_keep( 'Finishing this placement, parts: both buttons', $status_of( $new_student['graduating on'] ) );
	wpcpm_layout_keep( 'Finishing this placement, parts: Mark as graduated switched off', $status_of( $nc ) );
	wpcpm_layout_keep( 'Student card, parts: a tutor', $new_student['a tutor'] );
	wpcpm_layout_keep( 'Student card, parts: a saved correction, a sent invitation and a refused press, as the shared message', $said_card );
}

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
