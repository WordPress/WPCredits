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
 * - **The row labelled "Program" speaks one vocabulary.** The two tables both have a `Status`
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
function apply_filters( $t, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function human_time_diff( $from, $to = 0 ) { return '2 hours'; }
function get_option( $k, $d = false ) { return $d; }
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
function wp_create_nonce( $a = -1 ) { return 'nonce'; }
function wp_enqueue_script( $handle ) { $GLOBALS['enqueued'][] = (string) $handle; }
function wp_enqueue_style( $handle ) { $GLOBALS['styles'][] = (string) $handle; }
function wp_localize_script( $handle, $object_name, $l10n ) { $GLOBALS['localized'][ (string) $object_name ] = $l10n; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

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
}

/** Stands in for the students sync: the meta keys and the two cached rows. */
class WPCPM_Students_Sync {
	const META_RECORD_ID   = 'wpcpm_student_record_id';
	const META_PROGRAM     = 'wpcpm_student_program';
	const META_MENTOR      = 'wpcpm_student_mentor';
	const META_UPDATED     = 'wpcpm_student_updated';
	const META_INSTITUTION = 'wpcpm_student_institution';
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
		return implode( ' - ', $parts );
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

	public static function cached_subject( $record, $type ) {
		foreach ( $GLOBALS['umeta'] as $id => $meta ) {
			if ( isset( $meta[ WPCPM_Students_Sync::META_RECORD_ID ] ) && (string) $meta[ WPCPM_Students_Sync::META_RECORD_ID ] === (string) $record ) {
				return WPCPM_Institution_Policy::subject_student_account( $id );
			}
		}

		return WPCPM_Institution_Policy::subject_student_account( 0 );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';

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
 * The value cell of one labelled row.
 *
 * So that an assertion about the Program row is about the Program row: "Not set" appears on
 * several rows of this card, and a search of the whole card would pass on any of them.
 *
 * @param string $html  A rendered card.
 * @param string $label The row's visible label.
 * @return string The cell's markup, or an empty string when the card has no such row.
 */
function row_of( $html, $label ) {
	$pattern = '#>' . preg_quote( $label, '#' ) . '</span>.*?<td class="wpcpm-mentee__value"[^>]*>(.*?)</td>#s';

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
			'username'       => 'analopez',
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
			'username'  => 'martamentor',
			'profile'   => 'https://profiles.wordpress.org/martamentor/',
			'slack'     => 'marta.mentor',
			'website'   => 'https://marta.example.test/',
			'github'    => 'martamentor',
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
			'username'       => 'analopez',
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
ck( 'and names the program rather than the Airtable status', false !== strpos( $html, 'WordPress Credits Program 150h' ), true );
ck( 'the status is never printed raw', false === strpos( $html, '>In Sensei<' ), true );
ck( 'the dates print as one period', false !== strpos( $html, '2026-02-16 - 2026-06-30' ), true );
ck( 'the cohort is derived from the start date', false !== strpos( $html, 'January to June 2026' ), true );
ck( 'the field of study prints', false !== strpos( $html, 'Computer Science' ), true );
ck( 'the WordPress.org profile links to the handle', false !== strpos( $html, 'https://profiles.wordpress.org/analopez/' ), true );
ck( "the student's own address is not a row", row_of( $html, 'Email' ), '' );
ck( 'and is nowhere else on the card either', false === strpos( $html, 'ana@example.test' ), true );
ck( 'the way back to the roster keeps the filters', false !== strpos( $html, 'wpcpm_status=current' ), true );
ck( 'and does not carry the student argument', false === strpos( $html, 'wpcpm_institution_student=' ), true );

/* ---- 2. the mentor: a name and an address, and nothing else --------------- */

echo "\n=== The mentor is a name and an address ===\n";

ck( "the mentor's name prints", false !== strpos( $html, 'Marta Mentor' ), true );
ck( "the mentor's address prints", false !== strpos( $html, 'mailto:marta@example.test' ), true );

foreach ( array(
	'their WordPress.org profile' => 'martamentor',
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

$rows_full = substr_count( $html, '<tr class="wpcpm-mentee__row' );

ck( 'the complete card has one row per cell', $rows_full, count( $keys ) );
ck( 'an empty tutor still has a row', false !== strpos( $html, 'Tutor' ), true );
ck( 'and it reads "Not set"', false !== strpos( $html, 'Not set' ), true );

$GLOBALS['uid'] = 21;
$bare           = card( $inst_b, 11, array() );

ck( 'a student the sync has barely seen has the same rows', substr_count( $bare, '<tr class="wpcpm-mentee__row' ), $rows_full );
ck( 'and the mentor row says what its blank means', false !== strpos( $bare, 'No mentor assigned yet' ), true );
ck( 'a missing start date reads as no cohort rather than as nothing', false !== strpos( $bare, 'No start date' ), true );

/* ---- 5. the row labelled "Program" speaks one vocabulary ------------------ */

echo "\n=== The Program row speaks one vocabulary ===\n";

$GLOBALS['uid'] = 20;

// The Students table's `Status` is an application pipeline - `Interested`, `Not moving
// forward`, `Fail`, `SPAM`, and on this base one row whose status is the name of a school -
// and `WPCPM_Program::label()` passes anything it does not recognise straight through. The
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

ck( 'a cached row with only that key reads "Not set"', row_of( $stray_key, 'Program' ), '<span class="wpcpm-mentee__missing">Not set</span>' );

// And with the stray key gone the index row is the last thing left to read, which is the
// branch that put a pipeline status on a school's screen in the first place.
unset( $GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_PROGRAM ]['status'] );

$unknown_program = card( $inst_a, 10, array() );

ck( 'with no reports status the Program row reads "Not set"', row_of( $unknown_program, 'Program' ), '<span class="wpcpm-mentee__missing">Not set</span>' );
ck( 'the index row is not read for it', false === strpos( $unknown_program, 'Not moving forward' ), true );
ck( 'and the badge is dropped rather than painted from it', false === strpos( $unknown_program, 'wpcpm-badge' ), true );

$GLOBALS['roster'][ $inst_a ][ $row_a ]['status']                     = 'In Sensei';
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
ck( 'the avatar opens the row the stylesheet paints', false !== strpos( $html, '<div class="wpcpm-institution__student-identity"><img class="wpcpm-avatar"' ), true );
// The Gravatar fallback is a URL carrying a hash of the student's address: an identifier of the
// student handed to a third party's page and a third party's server. The card asks for the
// WordPress.org portrait only.
ck( 'the card never hands the student\'s address to the avatar service', array_unique( (array) ( $GLOBALS['avatar_emails'] ?? array() ) ), array( '' ) );
ck( 'and no wrapper the stylesheet has never heard of', false === strpos( $html, 'wpcpm-institution__student-card' ), true );

foreach ( array( 'wpcpm-institution__student-identity', 'wpcpm-institution__student-name' ) as $class ) {
	ck( sprintf( 'the page stylesheet has a rule for .%s', $class ), has_rule( $institution_css, $class ), true );
}

// The name and its badge are the mentor page's own group, so the two headers read the same
// and neither stylesheet defines that group twice.
ck( "the name and the badge reuse the mentor page's group", false !== strpos( $html, '<div class="wpcpm-mentee__identity">' ), true );
ck( 'which the mentor stylesheet defines', has_rule( $dashboard_css, 'wpcpm-mentee__identity' ), true );
ck( 'and the page stylesheet does not define it again', has_rule( $institution_css, 'wpcpm-mentee__identity' ), false );

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

ck( 'the page stylesheet names the report markup once', array_values( array_unique( $named[0] ) ), array( '.wpcpm-report__disclosure' ) );
ck( 'and always compounded with its own class', false !== strpos( $institution_css, '.wpcpm-report__disclosure.wpcpm-institution__report' ), true );

// The note class is not this page's own either. It comes with that same markup, and the
// stylesheet defining it is the student dashboard's whole dress, which this page has no other
// use for: one muted line in institution.css rather than a second stylesheet for a paragraph.
ck( 'the note the card prints is dressed by the page stylesheet', has_rule( $institution_css, 'wpcpm-student__note' ), true );

// A student with no report record: the disclosure would fetch a record that does not exist.
$GLOBALS['umeta'][10][ WPCPM_Students_Sync::META_RECORD_ID ] = '';
$GLOBALS['styles']                                           = array();

$without = card( $inst_a, 10, array() );

ck( 'a student with no report record gets no disclosure', false === strpos( $without, 'data-wpcpm-report=' ), true );
ck( 'and is told why', false !== strpos( $without, 'no report record' ), true );
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

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
