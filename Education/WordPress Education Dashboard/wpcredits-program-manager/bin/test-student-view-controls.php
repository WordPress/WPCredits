<?php
/**
 * The controls on one student's detail view: the export link, the status card, the sign-in line and
 * the folded correction form.
 *
 * The card itself is `bin/test-institution-student-view.php`'s. This suite is about the things
 * hung off it, and about the properties that make them safe to hang there:
 *
 * - **Neither is drawn for a reader the fence refuses.** Not as a disabled button, not with a
 *   sentence explaining what they may not do. Refusing the card refuses both; refusing the
 *   export alone leaves the status card standing, and refusing the status change alone leaves
 *   the export link standing, because they are two permissions and not one.
 * - **The export link is keyed to the account on screen.** `student_url()` mints a nonce from
 *   the user ID it is handed, so `get_current_user_id()` in that call would hand a member a
 *   token for exporting themselves. Two different readers of one student get the same link,
 *   byte for byte, and one reader of two students gets two different ones.
 * - **The status card is handed the Students record.** Two Airtable tables are in play and
 *   their record IDs are not interchangeable: the report disclosure on this card is keyed to
 *   the Students Reports row, and `handle_change()` claims the Students row. Hand over the
 *   reports ID and both buttons draw and neither works, because the claim behind them cannot
 *   find that record in the table it reads. The fixture gives the student one of each, so a
 *   file that reached for the wrong one fails here.
 * - **The status card is last, and outside the card.** It is the only control on this page
 *   whose write cannot be taken back, and it draws a card box of its own.
 * - **The sign-in line sits under the name, inside the card, and is handed the student on
 *   screen.** What it says and who may press its button are `bin/test-institution-resend.php`'s.
 * - **"Correct this student's details" is the existing form, folded inside the card.** It is
 *   handed the Students Reports record and the student's account, it is drawn only when the form
 *   itself draws (a reader refused `ACT_EDIT_STUDENT` gets no folded summary with nothing behind
 *   it), it stays closed until a save has left something to say, and it sits between the Student
 *   Report Card and the export link. Who may correct and what is saved are
 *   `bin/test-institution-student-form.php`'s.
 *
 * Nothing real is loaded but the card under test, `WPCPM_Roles`, `WPCPM_Request` and the two
 * small value classes it formats with. The fence, the export and the status card are stand-ins
 * written to the contracts this file calls them by: a suite that loaded the export module
 * would be testing the export module, which `bin/test-institution-export.php` already does.
 *
 * Run from the plugin root:  php bin/test-student-view-controls.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['users']  = array();
$GLOBALS['uid']    = 0;
$GLOBALS['umeta']  = array();
$GLOBALS['roster'] = array();

// Which actions the fence says yes to this render, and every question it was asked. Keyed by
// action so one can be closed without the others, which is the whole point of asking twice.
$GLOBALS['allow']     = array();
$GLOBALS['decisions'] = array();

// What the two stand-ins were handed. Recorded rather than inferred from the markup: the
// arguments are the contract this file is on the hook for, and the markup is theirs.
$GLOBALS['export_calls'] = array();
$GLOBALS['status_calls'] = array();
$GLOBALS['invite_calls'] = array();
$GLOBALS['correct_calls']   = array();
$GLOBALS['correct_message'] = false;

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
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
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ''; }
function remove_query_arg( $key, $query = false ) { return 'https://example.test/institution-dashboard/'; }
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); }
function wp_create_nonce( $a = -1 ) { return 'nonce'; }
function wp_enqueue_script( $handle ) {}
function wp_enqueue_style( $handle ) {}
function wp_localize_script( $handle, $object_name, $l10n ) {}
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }

/** Core's own shape: the arguments appended to whatever URL was passed in. */
function add_query_arg( $args, $url = '' ) {
	$sep = false === strpos( (string) $url, '?' ) ? '?' : '&';

	return (string) $url . $sep . http_build_query( (array) $args );
}

/**
 * The nonce, with the action it was minted for left readable in the URL.
 *
 * Real `wp_nonce_url()` hashes the action away, and a hashed token is a token no assertion can
 * read. Keeping the action visible is what lets this suite say which account the export link
 * was keyed to rather than only that it carries some nonce or other.
 *
 * @param string $url    The URL to sign.
 * @param string $action The nonce action.
 * @param string $name   The query argument the token travels in.
 * @return string
 */
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	$sep = false === strpos( (string) $url, '?' ) ? '?' : '&';

	return (string) $url . $sep . $name . '=nonce-' . $action;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';

/* ---- the pieces around the card, each to its contract --------------------- */

/** The record-ID shape check and the stored-name resolver. */
class WPCPM_Mentors_Sync {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $value ) { return is_scalar( $value ) && (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) ); }
	public static function resolve_stored( $value, $type ) { return trim( (string) $value ); }
}

/** The meta keys and the two cached rows the card reads. */
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

/**
 * The student's Students **Reports** record, which is the one this key holds.
 *
 * The whole reason the status card's argument is worth an assertion: this is the ID the report
 * disclosure is keyed to, and it is not the ID `handle_change()` claims.
 */
class WPCPM_Mentor_Calls {
	public static function student_record( $user_id ) {
		$record = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_RECORD_ID, true );
		$record = is_string( $record ) ? trim( $record ) : '';
		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}
}

/** One institution's roster rows, keyed by Students record ID, and when they were read. */
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

/**
 * The fence, answering per action and recording every question.
 *
 * The shipped grounds answer `view_student` and `export` the same way, so a stand-in that
 * copied them could never show the difference between asking once and asking twice. This one
 * is switchable per action, which is what lets the suite close the export without closing the
 * card and see which control disappears.
 */
class WPCPM_Institution_Policy {
	const ACT_VIEW_STUDENT  = 'view_student';
	const ACT_EDIT_STUDENT  = 'edit_student';
	const ACT_CHANGE_STATUS = 'change_status';
	const ACT_EXPORT        = 'export';

	public static function subject_student_account( $user_id ) {
		$user_id = (int) $user_id;
		$stamp   = (string) get_user_meta( $user_id, WPCPM_Students_Sync::META_INSTITUTION, true );

		return array(
			'type'             => 'student_account',
			'id'               => $user_id,
			'institution_ids'  => '' === $stamp ? array() : array( $stamp ),
			'evidence'         => 'stamp',
		);
	}

	public static function subject_index_row( $institution, $record ) {
		return array(
			'type'            => 'student',
			'id'              => (string) $record,
			'institution_ids' => '' === (string) $institution ? array() : array( (string) $institution ),
			'evidence'        => 'index',
		);
	}

	public static function decide( $action, array $subject, $user = null ) {
		$allowed = ! isset( $GLOBALS['allow'][ $action ] ) || (bool) $GLOBALS['allow'][ $action ];
		$ids     = isset( $subject['institution_ids'] ) ? (array) $subject['institution_ids'] : array();

		$GLOBALS['decisions'][] = array( $action, $subject['id'] );

		return array(
			'allowed'     => $allowed,
			'ground'      => $allowed ? 'member' : '',
			'institution' => $allowed && isset( $ids[0] ) ? (string) $ids[0] : '',
			'fields'      => $allowed ? null : array(),
			'why'         => '',
		);
	}

	public static function scope( array $decision, array $keyed ) {
		if ( empty( $decision['allowed'] ) || ! array_key_exists( 'fields', $decision ) ) { return array(); }
		if ( null === $decision['fields'] ) { return $keyed; }
		$permitted = array();
		foreach ( (array) $decision['fields'] as $key ) { $permitted[ (string) $key ] = true; }
		return array_intersect_key( $keyed, $permitted );
	}
}

/** The cheap cached subject the status card decides from, keyed by Students record ID. */
class WPCPM_Institution_Roster {
	const TYPE_STUDENT = 'student';
	const TYPE_REPORT  = 'report';

	public static function cached_subject( $record, $type ) {
		foreach ( $GLOBALS['roster'] as $institution => $rows ) {
			if ( isset( $rows[ (string) $record ] ) ) {
				return WPCPM_Institution_Policy::subject_index_row( $institution, $record );
			}
		}

		return WPCPM_Institution_Policy::subject_index_row( '', $record );
	}
}

/**
 * The single-student export, to the contract of `student_url()` and no further.
 *
 * The body is the real one's: the two arguments, then a nonce keyed to `ACTION_STUDENT` and
 * the user ID together. That keying is what the assertions below read, so a copy that only
 * echoed the ID back would pass a card that minted the reader's own token.
 */
class WPCPM_Institution_Export {
	const ACTION_STUDENT = 'wpcpm_export_student';
	const ARG_STUDENT    = 'wpcpm_export_student_id';

	public static function student_url( $user_id ) {
		$user_id = (int) $user_id;

		$GLOBALS['export_calls'][] = $user_id;

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'          => self::ACTION_STUDENT,
					self::ARG_STUDENT => $user_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_STUDENT . '_' . $user_id
		);
	}
}

/**
 * Graduate and withdraw, to the two contract points the caller has to satisfy.
 *
 * Kept: a record that is not a record ID draws nothing, the decision is asked for itself with
 * `ACT_CHANGE_STATUS` on the cached subject for that Students record, a refusal draws nothing
 * at all, and the card it prints is a `wpcpm-institution__card` box of its own. Dropped: the
 * status vocabulary, the three guards, the flash and the nonces, all of which belong to
 * `bin/test-institution-graduate.php` and none of which the caller can influence.
 */
class WPCPM_Institution_Students {
	const ANCHOR = 'wpcpm-student-status';

	public static function render_form( $record, array $context ) {
		$record = trim( (string) $record );

		$GLOBALS['status_calls'][] = array( 'record' => $record, 'context' => $context );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_CHANGE_STATUS,
			WPCPM_Institution_Roster::cached_subject( $record, WPCPM_Institution_Roster::TYPE_STUDENT )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		printf(
			'<section class="wpcpm-institution__card wpcpm-institution__status" id="%1$s">'
				. '<h3>Finishing this placement</h3><p>%2$s</p></section>',
			esc_attr( self::ANCHOR ),
			esc_html( isset( $context['name'] ) ? (string) $context['name'] : '' )
		);
	}
}

/**
 * The sign-in line, to the one contract point the card has to satisfy: it is handed the account on
 * screen. Who may press, which students get a button and what the line says belong to
 * `bin/test-institution-resend.php`.
 */
class WPCPM_Institution_Resend {
	public static function render( WP_User $student ) {
		$GLOBALS['invite_calls'][] = (int) $student->ID;

		echo '<div class="wpcpm-institution__invite" id="wpcpm-student-invite"></div>';
	}
}

/**
 * The correction form, to the contract the card has to satisfy.
 *
 * Kept: it is handed the Students Reports record and a context naming the institution and the student's
 * account; a record that is not a record ID draws nothing; the decision is asked for itself with
 * `ACT_EDIT_STUDENT` on the student's account and a refusal draws nothing at all; and what it draws is a
 * `wpcpm-institution__card` section with a heading of its own, which holds the outcome of the last save
 * when there is one. Dropped: the five controls, the nonce and the handler, which belong to
 * `bin/test-institution-student-form.php`.
 */
class WPCPM_Institution_Student_Form {
	const ANCHOR        = 'wpcpm-student-form';
	const CLASS_MESSAGE = 'wpcpm-institution__edit-message';

	public static function render_form( $record, array $context ) {
		$record  = trim( (string) $record );
		$user_id = isset( $context['user_id'] ) ? (int) $context['user_id'] : 0;

		$GLOBALS['correct_calls'][] = array( 'record' => $record, 'context' => $context );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_STUDENT,
			WPCPM_Institution_Policy::subject_student_account( $user_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		printf(
			'<section class="wpcpm-institution__card wpcpm-institution__edit" id="%s"><h3 class="wpcpm-institution__heading">Correct this student\'s details</h3>',
			esc_attr( self::ANCHOR )
		);

		if ( $GLOBALS['correct_message'] ) {
			echo '<p class="' . self::CLASS_MESSAGE . ' is-error" role="status">Nothing was saved.</p>';
		}

		echo '<form class="wpcpm-institution__edit-form" method="post"><input type="hidden" name="record" value="' . esc_attr( $record ) . '" /></form></section>';
	}
}

/** The three display helpers and the script handle the card uses. */
class WPCPM_Mentors_Dashboard {
	const SCRIPT = 'wpcpm-mentor-dashboard';
	public static function avatar_url( $username, $email, $size = 64 ) {
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
}

/** Only the handle: the card enqueues the report form's stylesheet and calls nothing else. */
class WPCPM_Call_Calendar {
	const STYLE = 'wpcpm-call-calendar';
}

/** The team links and their icon. */
class WPCPM_Contribution_Teams {
	public static function links( $value ) {
		$value = trim( (string) $value );
		return '' === $value ? '' : '<a href="https://make.wordpress.org/">' . esc_html( $value ) . '</a>';
	}
	public static function label_icon( $value ) { return '<span class="wpcpm-team__icon"></span>'; }
}

/** The icon set. */
class WPCPM_Icons {
	public static function svg( $name, $size = 16 ) { return '<svg class="wpcpm-icon wpcpm-icon--' . $name . '"></svg>'; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php';

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
 * Render one card, from a clean slate of recordings.
 *
 * The three recording globals are reset here rather than by each caller, so an assertion about
 * "what this render asked the fence" cannot quietly be reading the render before it.
 *
 * @param string $institution Institutions record ID whose roster the reader came from.
 * @param int    $user_id     The student's account.
 * @param int    $reader      The account doing the reading.
 * @param array  $allow       Action to bool; any action left out is allowed.
 * @return string
 */
function card( $institution, $user_id, $reader, array $allow = array() ) {
	$GLOBALS['uid']          = (int) $reader;
	$GLOBALS['allow']        = $allow;
	$GLOBALS['decisions']    = array();
	$GLOBALS['export_calls'] = array();
	$GLOBALS['status_calls'] = array();
	$GLOBALS['invite_calls'] = array();
	$GLOBALS['correct_calls']   = array();
	$GLOBALS['correct_message'] = false;

	ob_start();
	WPCPM_Institution_Student_View::render( $institution, $user_id, array() );

	return (string) ob_get_clean();
}

/**
 * The address the export link points at, or an empty string when there is no link.
 *
 * The whole href and not a substring of it: "the link carries this user ID somewhere" would
 * pass a link that carried the reader's ID as well.
 *
 * @param string $html A rendered card.
 * @return string
 */
function export_href( $html ) {
	$pattern = '#<p class="wpcpm-institution__student-export"><a href="([^"]*)"#';

	return preg_match( $pattern, (string) $html, $m ) ? $m[1] : '';
}

/**
 * The markers a card printed, in the order the page prints them.
 *
 * Positions rather than a list of yes-or-no assertions, because the order is the rule: the
 * destructive control is last, and a suite that only asked whether each piece was present
 * would pass a page that opened with it.
 *
 * @param string   $html    A rendered card.
 * @param string[] $markers Needles to look for, in any order.
 * @return string[] Those that are present, in the order they appear.
 */
function order( $html, array $markers ) {
	$found = array();

	foreach ( $markers as $marker ) {
		$at = strpos( (string) $html, $marker );

		if ( false !== $at ) {
			$found[ $at ] = $marker;
		}
	}

	ksort( $found );

	return array_values( $found );
}

/* ---- the fixture ---------------------------------------------------------- */

$inst_a = rid( 'INSTA' );
$inst_b = rid( 'INSTB' );

// Two IDs for one student, on purpose. `$report_a` is her Students Reports row, which the
// report disclosure is keyed to; `$row_a` is her Students row, which the status change is
// claimed against. Nothing but the fixture keeps them apart, which is the point.
$report_a = rid( 'REPORTA' );
$row_a    = rid( 'ROWA' );
$report_b = rid( 'REPORTB' );
$row_b    = rid( 'ROWB' );

// 10 Ana at institution A, 11 Bea at institution B, 12 Cleo whose Students row was never
// created. 20 and 21 members, 30 a program manager reading through the switcher.
$GLOBALS['users'] = array(
	10 => new WP_User( 10, 'Ana Lopez', 'ana@example.test', array( WPCPM_Roles::ROLE_STUDENT ) ),
	11 => new WP_User( 11, 'Bea Rossi', 'bea@example.test', array( WPCPM_Roles::ROLE_STUDENT ) ),
	12 => new WP_User( 12, 'Cleo Silva', 'cleo@example.test', array( WPCPM_Roles::ROLE_STUDENT ) ),
	20 => new WP_User( 20, 'Ines Ruiz', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
	21 => new WP_User( 21, 'Bruno Bianchi', 'bruno@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
	13 => new WP_User( 13, 'Student Without Record', 'norecord@example.test', array( WPCPM_Roles::ROLE_STUDENT ) ),
	30 => new WP_User( 30, 'Program Manager', 'pm@example.test', array( 'administrator' ) ),
);

$GLOBALS['umeta'] = array(
	10 => array(
		WPCPM_Students_Sync::META_RECORD_ID   => $report_a,
		WPCPM_Students_Sync::META_INSTITUTION => $inst_a,
		WPCPM_Students_Sync::META_UPDATED     => 1756800000,
		WPCPM_Students_Sync::META_PROGRAM     => array(
			'name'    => 'Ana Lopez',
			'program' => 'In Sensei',
			'start'   => '2026-02-16',
			'end'     => '2026-06-30',
		),
		WPCPM_Students_Sync::META_MENTOR      => array( 'name' => 'Marta Mentor', 'email' => 'marta@example.test' ),
	),
	11 => array(
		WPCPM_Students_Sync::META_RECORD_ID   => $report_b,
		WPCPM_Students_Sync::META_INSTITUTION => $inst_b,
		WPCPM_Students_Sync::META_PROGRAM     => array( 'name' => 'Bea Rossi', 'program' => 'In Sensei' ),
	),
	// 13 has an account and a roster stamp and no progress record at all, so there is no
	// Student Report Card and nothing for the correction form to be keyed to.
	13 => array(
		WPCPM_Students_Sync::META_INSTITUTION => $inst_a,
		WPCPM_Students_Sync::META_PROGRAM     => array( 'name' => 'Student Without Record', 'program' => 'In Sensei' ),
	),
	// The dashboard's fifth list: an account the reports side placed, with no Students row
	// behind it at all. She has a report record and no roster row.
	12 => array(
		WPCPM_Students_Sync::META_RECORD_ID   => rid( 'REPORTC' ),
		WPCPM_Students_Sync::META_INSTITUTION => $inst_a,
		WPCPM_Students_Sync::META_PROGRAM     => array( 'name' => 'Cleo Silva', 'program' => 'In Sensei' ),
	),
);

$GLOBALS['roster'] = array(
	$inst_a => array(
		$row_a => array(
			'record_id'  => $row_a,
			'name'       => 'Ana Lopez',
			'status'     => 'In Sensei',
			'start'      => '2026-02-16',
			'end'        => '2026-06-30',
			'has_mentor' => true,
			'reports'    => array( $report_a ),
			'user_id'    => 10,
		),
	),
	$inst_b => array(
		$row_b => array(
			'record_id' => $row_b,
			'name'      => 'Bea Rossi',
			'status'    => 'In Sensei',
			'start'     => '2026-02-16',
			'reports'   => array( $report_b ),
			'user_id'   => 11,
		),
	),
);

/* ---- 1. neither control is drawn for a reader the fence refuses ----------- */

echo "=== A reader the fence refuses gets neither control ===\n";

$open = card( $inst_a, 10, 20 );

// The baseline the refusals below are read against. Without it, "the export link is absent"
// would pass just as well on a card that never draws one for anybody.
ck( 'with nothing refused, the card carries both controls',
	array( '' !== export_href( $open ), false !== strpos( $open, 'wpcpm-institution__status' ) ),
	array( true, true ) );

$shut = card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_VIEW_STUDENT => false ) );

ck( 'a reader refused the card gets no card at all', $shut, '' );
ck( 'so no export link', export_href( $shut ), '' );
ck( 'and the status card is never even asked for', $GLOBALS['status_calls'], array() );
// Refusing after minting the link would have handed the token out already: it lives in the
// markup, and markup that is discarded has still been built.
ck( 'nor is an export address minted for a card nobody sees', $GLOBALS['export_calls'], array() );

$no_export = card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_EXPORT => false ) );

ck( 'refusing the export alone still draws the card', false !== strpos( $no_export, 'Ana Lopez' ), true );
ck( 'but no link to take it away', export_href( $no_export ), '' );
ck( 'and no address was minted for one', $GLOBALS['export_calls'], array() );
// Two permissions, not one: the export is about carrying data off the site and the status
// change is about writing to the program records, and a ground may one day allow either alone.
ck( 'while the status card is untouched by it', false !== strpos( $no_export, 'wpcpm-institution__status' ), true );

$no_status = card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_CHANGE_STATUS => false ) );

ck( 'refusing the status change draws no status card', false !== strpos( $no_status, 'wpcpm-institution__status' ), false );
ck( 'and no explanation of what this reader may not do', false !== strpos( $no_status, 'Finishing this placement' ), false );
ck( 'while the export link is untouched by it', '' !== export_href( $no_status ), true );

// `shows()` is what the roster asks before it steps aside. It answers on the card's own action
// and on no other, so a ground that closed the export would otherwise send a reader who may
// read the card back to the list.
//
// **Asked under an export refusal, or it proves nothing.** This ran with only the status
// action refused, so both `view_student` and `export` answered yes and the assertion held
// whichever one `shows()` asked. A reviewer swapped it to ask ACT_EXPORT - the regression this
// label describes - and the suite stayed green. Refusing the export is what makes the wrong
// question give the wrong answer.
$GLOBALS['allow'] = array( WPCPM_Institution_Policy::ACT_EXPORT => false );

ck( 'a reader who may read the card but not export it is still shown the card',
	WPCPM_Institution_Student_View::shows( 10, 20 ), true );

$GLOBALS['allow'] = array();

/* ---- 2. the export link is keyed to the account on screen ----------------- */

echo "\n=== The export link is keyed to the student, not to the reader ===\n";

$ana     = card( $inst_a, 10, 20 );
$ana_url = 'https://example.test/wp-admin/admin-post.php'
	. '?action=wpcpm_export_student&wpcpm_export_student_id=10'
	. '&_wpnonce=nonce-wpcpm_export_student_10';

ck( "the href names the student's account and carries its own nonce", export_href( $ana ), $ana_url );
// The failure this prevents by name: `student_url( get_current_user_id() )` would mint the
// member a token for exporting themselves, and it would look like a working link.
ck( 'and the account handed to student_url() is the student, never the reader', $GLOBALS['export_calls'], array( 10 ) );

$ana_pm = card( $inst_a, 10, 30 );

ck( 'a program manager reading the same student gets the same link, byte for byte', export_href( $ana_pm ), $ana_url );

$bea = card( $inst_b, 11, 21 );

ck( 'a second student gets a link of their own', export_href( $bea ), 'https://example.test/wp-admin/admin-post.php'
	. '?action=wpcpm_export_student&wpcpm_export_student_id=11'
	. '&_wpnonce=nonce-wpcpm_export_student_11' );
ck( 'which is keyed to that student', $GLOBALS['export_calls'], array( 11 ) );

// The link is drawn on an answer to `ACT_EXPORT`, which is the action `handle_student()` asks
// when the link is followed. Asking `ACT_VIEW_STUDENT` twice instead would draw a link that
// 403s the day a ground splits reading a card from carrying it off.
card( $inst_a, 10, 20 );

ck( 'the fence was asked about the export, on the student\'s own account',
	in_array( array( WPCPM_Institution_Policy::ACT_EXPORT, 10 ), $GLOBALS['decisions'], true ), true );
ck( 'and never about the reader\'s account',
	in_array( array( WPCPM_Institution_Policy::ACT_EXPORT, 20 ), $GLOBALS['decisions'], true ), false );

/* ---- 3. the status card gets this student's Students record --------------- */

echo "\n=== The status card is handed the Students record on screen ===\n";

card( $inst_a, 10, 20 );

ck( 'the card is drawn once per student', count( $GLOBALS['status_calls'] ), 1 );
// The assertion the whole fixture exists for. `handle_change()` claims this ID as `TYPE_STUDENT`
// against the Students table; the Students Reports ID beside it in the fixture is what
// `WPCPM_Mentor_Calls::student_record()` answers and what the report disclosure is keyed to.
// Hand over the wrong one and both buttons draw, both nonces verify, and the claim behind them
// finds no such row.
ck( "it is handed the student's Students record", $GLOBALS['status_calls'][0]['record'], $row_a );
ck( 'and never their Students Reports record', $GLOBALS['status_calls'][0]['record'] === $report_a, false );
ck( 'which the card really does hold as well, or this would prove nothing',
	WPCPM_Mentor_Calls::student_record( 10 ), $report_a );
// The confirm dialog names the student; taking the card's own answer means the dialog and the
// heading above it cannot disagree about who is being graduated.
ck( 'the context carries the name the card printed', $GLOBALS['status_calls'][0]['context'], array( 'name' => 'Ana Lopez' ) );
ck( 'and that name is the one in the heading', false !== strpos( card( $inst_a, 10, 20 ), '>Ana Lopez<' ), true );

$bea = card( $inst_b, 11, 21 );

ck( "a second student's card is handed their own record", $GLOBALS['status_calls'][0]['record'], $row_b );
ck( 'and their own name', $GLOBALS['status_calls'][0]['context'], array( 'name' => 'Bea Rossi' ) );

// The fence for the status change is asked about the Students row, not about the account: that
// is the subject `claim()` decides on, and it is how the recorded question proves which of the
// two record IDs was passed along.
card( $inst_a, 10, 20 );

ck( 'the status change is decided on the Students row',
	in_array( array( WPCPM_Institution_Policy::ACT_CHANGE_STATUS, $row_a ), $GLOBALS['decisions'], true ), true );

// A student the reports side placed has no Students row at all. Whether that is a card worth
// drawing is `render_form()`'s call and not this file's, so it is still called and handed the
// empty record, and it says so itself.
$cleo = card( $inst_a, 12, 20 );

ck( 'a student with no Students row still reaches the status card', count( $GLOBALS['status_calls'] ), 1 );
ck( 'handed an empty record rather than a guess', $GLOBALS['status_calls'][0]['record'], '' );
ck( 'and nothing is drawn for it', false !== strpos( $cleo, 'wpcpm-institution__status' ), false );
ck( 'though the rest of their card is there', false !== strpos( $cleo, 'Cleo Silva' ), true );

/* ---- 4. the destructive control is last, and outside the card ------------- */

echo "\n=== Where the two controls sit ===\n";

$ana = card( $inst_a, 10, 20 );

ck( 'the way back first, then the report, the export link, the read time, and the status card last',
	order( $ana, array(
		'wpcpm-institution__status',
		'wpcpm-institution__student-export',
		'wpcpm-institution__back',
		'wpcpm-institution__read',
		'wpcpm-report__disclosure',
	) ),
	array(
		'wpcpm-institution__back',
		'wpcpm-report__disclosure',
		'wpcpm-institution__student-export',
		'wpcpm-institution__read',
		'wpcpm-institution__status',
	) );

// Everything printed before the status card's own opening tag. Cut at that tag rather than at
// the class name inside it, so the count below is of closed sections and not of half of one.
$opens  = substr( $ana, 0, (int) strpos( $ana, 'wpcpm-institution__status' ) );
$before = substr( $ana, 0, (int) strrpos( $opens, '<section' ) );

// `render_form()` prints a `wpcpm-institution__card` box of its own, so nesting it would put
// two card borders around one block. Counting the tags says it is a sibling without pinning
// how many sections the card above it happens to print.
ck( 'the status card is a sibling of the student card, not a box inside it',
	substr_count( $before, '<section' ) === substr_count( $before, '</section>' ), true );
ck( 'and it is the last thing on the page',
	trim( substr( $ana, (int) strrpos( $ana, '</section>' ) + strlen( '</section>' ) ) ), '' );

// A quiet link, not a button and not a form: it takes nothing away and changes nothing, and a
// second submit control beside the two that mail a student is a control somebody clicks first.
ck( 'the export is an anchor', substr_count( $ana, '<p class="wpcpm-institution__student-export"><a href=' ), 1 );
ck( 'and not a form', false !== strpos( $ana, 'wpcpm-institution__student-export"><form' ), false );
ck( 'its label says what the file holds, grades included',
	false !== strpos( $ana, 'details and course grades as a CSV file' ), true );

/* ---- 5. the sign-in line sits under the name ------------------------------ */

echo "\n=== The sign-in line sits under the name ===\n";

$ana = card( $inst_a, 10, 20 );

ck( 'the line is asked for once, about the student on screen and never the reader', $GLOBALS['invite_calls'], array( 10 ) );
ck( 'it comes right after the identity block, before the table',
	order( $ana, array( '<table', 'wpcpm-institution__invite', 'wpcpm-institution__student-identity', 'wpcpm-report__disclosure' ) ),
	array( 'wpcpm-institution__student-identity', 'wpcpm-institution__invite', '<table', 'wpcpm-report__disclosure' ) );
ck( 'as the next thing the card prints once the identity block closes', false !== strpos( $ana, '</div></div><div class="wpcpm-institution__invite"' ), true );

$opens  = substr( $ana, 0, (int) strpos( $ana, 'wpcpm-institution__invite' ) );
ck( 'inside the student card, not beside it', substr_count( $opens, '<section' ) - substr_count( $opens, '</section>' ), 1 );

card( $inst_b, 11, 30 );
ck( 'an Administrator reading another student asks about that student', $GLOBALS['invite_calls'], array( 11 ) );

card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_VIEW_STUDENT => false ) );
ck( 'a reader refused the card never reaches the line', $GLOBALS['invite_calls'], array() );

/* ---- 6. the correction form is folded inside the card -------------------- */

echo "\n=== The correction form is folded inside the card ===\n";

$ana      = card( $inst_a, 10, 20 );
$folded   = '<details class="wpcpm-institution__correct"><summary class="wpcpm-institution__correct-toggle">Correct this student&#039;s details</summary><section class="wpcpm-institution__card wpcpm-institution__edit" id="wpcpm-student-form">';
$one_form = substr_count( $ana, '<details class="wpcpm-institution__correct"' );

ck( 'the form is drawn once, in a closed disclosure whose summary names it', array( $one_form, false !== strpos( $ana, $folded ) ), array( 1, true ) );
ck( 'and the disclosure holds the form and closes right after it', 1 === preg_match( '#<details class="wpcpm-institution__correct">.*</form></section></details>#s', $ana ), true );
ck( 'a closed disclosure carries no open attribute', false !== strpos( $ana, '<details class="wpcpm-institution__correct" open' ), false );

// The Students Reports record, and not the Students record the status card is keyed to: the form
// is claimed against the report, and the nonce it mints is keyed to it.
ck( 'the form is handed the Students Reports record and the student on screen',
	$GLOBALS['correct_calls'], array( array( 'record' => $report_a, 'context' => array( 'institution' => $inst_a, 'user_id' => 10 ) ) ) );
ck( 'and never the Students record', $GLOBALS['correct_calls'][0]['record'] === $row_a, false );
ck( 'nor the reader', $GLOBALS['correct_calls'][0]['context']['user_id'] === 20, false );

ck( 'the fence was asked about editing, on the student\'s own account',
	in_array( array( WPCPM_Institution_Policy::ACT_EDIT_STUDENT, 10 ), $GLOBALS['decisions'], true ), true );

// Between the Student Report Card and the export link: both of those are read, and this is the
// one block that changes something, so it comes after the reading and before the way out.
ck( 'it sits after the Student Report Card and before the export link, inside the card',
	order( $ana, array(
		'wpcpm-institution__student-export',
		'wpcpm-institution__correct',
		'wpcpm-report__disclosure',
		'wpcpm-institution__status',
	) ),
	array( 'wpcpm-report__disclosure', 'wpcpm-institution__correct', 'wpcpm-institution__student-export', 'wpcpm-institution__status' ) );

$opens_before_form = substr( $ana, 0, (int) strpos( $ana, 'wpcpm-institution__correct' ) );
ck( 'in the student card, not beside it', substr_count( $opens_before_form, '<section' ) - substr_count( $opens_before_form, '</section>' ), 1 );

$by_manager = card( $inst_a, 10, 30 );
ck( 'an Administrator reading the same student gets the same folded form', false !== strpos( $by_manager, $folded ), true );

// A refusal to edit is a form the card is not given: not a closed summary with nothing behind it.
$no_edit = card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_EDIT_STUDENT => false ) );

ck( 'a reader allowed the card and refused the edit gets no disclosure', false !== strpos( $no_edit, 'wpcpm-institution__correct' ), false );
ck( 'and no summary line naming a form they cannot use', false !== strpos( $no_edit, 'Correct this student&#039;s details</summary>' ), false );
ck( 'while the rest of the card is there', array( false !== strpos( $no_edit, 'Ana Lopez' ), '' !== export_href( $no_edit ), false !== strpos( $no_edit, 'wpcpm-institution__status' ) ), array( true, true, true ) );

$shut = card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_VIEW_STUDENT => false ) );
ck( 'a reader refused the card is never even offered the form', array( $shut, $GLOBALS['correct_calls'] ), array( '', array() ) );

// Closing the other two controls leaves this one alone: three permissions, three controls.
$no_export_no_status = card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_EXPORT => false, WPCPM_Institution_Policy::ACT_CHANGE_STATUS => false ) );
ck( 'closing the export and the status change does not close the correction form', false !== strpos( $no_export_no_status, $folded ), true );

$no_record = card( $inst_a, 13, 20 );
ck( 'a student with no progress record is given no disclosure', array( false !== strpos( $no_record, 'wpcpm-institution__correct' ), false !== strpos( $no_record, 'no Student Report Card to show' ) ), array( false, true ) );

// A save that was refused or that changed something leaves its sentence inside the form, and a
// sentence inside a closed disclosure is a sentence nobody reads. So the disclosure opens for it.
$GLOBALS['correct_message'] = true;
ob_start();
WPCPM_Institution_Student_View::render( $inst_a, 10, array() );
$said = (string) ob_get_clean();

ck( 'the disclosure opens when the last save left a message', false !== strpos( $said, '<details class="wpcpm-institution__correct" open><summary' ), true );
ck( 'and the message is inside it', 1 === preg_match( '#<details class="wpcpm-institution__correct" open>.*Nothing was saved\..*</details>#s', $said ), true );
$GLOBALS['correct_message'] = false;

// Every class the disclosure prints has a rule in the stylesheet this page loads. The form folded inside it
// is a part of the card and not a second one: on the WPCredits theme every `wpcpm-institution__card` takes
// a 32px inset, so a card inside a card would be inset twice unless the plugin takes it off.
$css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );

foreach ( array( 'wpcpm-institution__correct', 'wpcpm-institution__correct-toggle' ) as $class ) {
	ck( sprintf( '.%s is styled on this page', $class ), false !== strpos( $css, '.' . $class . ' {' ), true );
}

ck( 'the form inside the disclosure is not a second card: no second inset, no repeated heading',
	array(
		false !== strpos( $css, '.wpc-institution-page .wpcpm-institution__correct > .wpcpm-institution__card {' . "\n\tpadding-inline: 0;" ),
		false !== strpos( $css, '.wpcpm-institution__correct .wpcpm-institution__edit > .wpcpm-institution__heading {' . "\n\tdisplay: none;" ),
	),
	array( true, true ) );

$dashes = array();

foreach ( array( 'includes/modules/class-wpcpm-institution-student-view.php', 'bin/test-student-view-controls.php' ) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in the card or this suite', $dashes, array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
