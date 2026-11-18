<?php
/**
 * The Institution Dashboard's Overview: what waits for the school and what is news for it.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - "Waits" means the reader has to act: the agreement while the school is the one to act (none
 *   recorded, generated and not uploaded, returned, revoked), and a checked list of students not yet
 *   created or a creation in progress. A signed copy waiting for review waits for an Administrator, so
 *   it is no row for the school.
 * - For an Administrator an unsettled agreement is one row, the banner's two sentences, and the
 *   school's own agreement row is not added beside it (the planner's ruling P1).
 * - **News is not waiting** (the controller's ruling 4): a semester report approved in the last 30
 *   days and a details request decided in the last 30 days, while no newer request is sent, are rows
 *   after the waiting rows, tagged "New", and no menu count includes them. The window reads one moment,
 *   `wpcpm_institution_now`, which this suite fixes.
 * - **Every why is today's sentence, called, not retyped** (the planner's ruling P4): the panel's
 *   `state_sentence()`, the import form's `waiting_sentence()`, the details card's `outcome_sentence()`
 *   and the banner's `banner_sentences()`.
 * - A row whose view is not in the reader's menu is not returned: a tutor never gets a row that opens
 *   Enroll students (Review Focus 4).
 *
 * Run: php bin/test-institution-overview.php
 *
 * @package WPCreditsProgramManager
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_VERSION', 'test' );
define( 'DAY_IN_SECONDS', 86400 );

/** The suite's fixed moment, what `wpcpm_institution_now` answers, and a day of it. */
const NOW = 1792800000;
const DAY = DAY_IN_SECONDS;

$GLOBALS['opts']    = array( 'date_format' => 'F j, Y' );
$GLOBALS['filters'] = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) (int) $n; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? NOW : (int) $t ); }
// The generated agreement's day and template, read by the panel's sentence: no document in this suite.
function get_post( $id ) { return null; }
function get_post_meta( $id, $key = '', $single = false ) { return ''; }

/** The suite's clock: `wpcpm_institution_now` is the fixed `NOW`; every other hook hands its value back. */
function apply_filters( $hook, $value, ...$args ) {
	$GLOBALS['filters'][] = $hook;
	return 'wpcpm_institution_now' === $hook ? NOW : $value;
}

class WP_User {
	public $ID;
	public $user_login;
	/** 1.123.98, fix round 1: what the Overview's sign-in fact asks of a student's account. */
	public $roles           = array();
	public $user_email      = '';
	public $user_registered = '';
	public function __construct( $id = 0, $login = '' ) {
		$this->ID         = (int) $id;
		$this->user_login = (string) $login;
	}
	public function exists() { return $this->ID > 0; }
}

/*
 * The stand-ins `views_for()` asks (Task 3): the policy answering from a table keyed by the viewer's login and the
 * action, the members' stamp, and the import switch read by the real `WPCPM_Institution_Import_Form::enabled()`.
 */
$GLOBALS['allow']   = array();
$GLOBALS['members'] = array();

/** What the stand-ins were asked, by name: the memo's checks count the queries. */
$GLOBALS['q'] = array();
function asked( $name ) { $GLOBALS['q'][ $name ] = ( $GLOBALS['q'][ $name ] ?? 0 ) + 1; }

class WPCPM_Institution_Policy {
	// 1.123.98: the two acts the Overview's rows that wait ask for.
	const ACT_RESEND_INVITE = 'resend_invite';
	const ACT_EDIT_STUDENT  = 'edit_student';
	const ACT_VIEW_ROSTER          = 'view_roster';
	const ACT_ADD_STUDENT          = 'add_student';
	const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
	const ACT_AGREEMENT            = 'agreement';
	const ACT_EDIT_DETAILS         = 'edit_details';
	public static function subject_institution( $record ) { return array( 'kind' => 'institution', 'institution_ids' => array( (string) $record ) ); }
	public static function decide( $act, array $subject, $user = null ) {
		asked( 'decide' );
		$login = $user instanceof WP_User ? $user->user_login : '';
		return array( 'allowed' => ! empty( $GLOBALS['allow'][ $login ][ $act ] ) );
	}
}

class WPCPM_Institution_Members {
	const LEVEL_TUTOR          = 'tutor';
	const LEVEL_REPRESENTATIVE = 'representative';
	public static function institution_of( $user = null ) { return $user instanceof WP_User && isset( $GLOBALS['members'][ $user->ID ] ) ? $GLOBALS['members'][ $user->ID ][0] : ''; }
	public static function level_of( $user = null ) { return $user instanceof WP_User && isset( $GLOBALS['members'][ $user->ID ] ) && 'tutor' === $GLOBALS['members'][ $user->ID ][1] ? self::LEVEL_TUTOR : self::LEVEL_REPRESENTATIVE; }
}

class WPCPM_Settings {
	public static function get_value( $key ) { return 'import_enabled' === $key ? true : null; }
}

/* The agreement: the summary and the settled answer, from the suite's globals. */
$GLOBALS['summary'] = array( 'state' => 'none', 'airtable_status' => '' );
$GLOBALS['settled'] = false;

class WPCPM_Institution_Agreement {
	const SUMMARY_NONE      = 'none';
	const SUMMARY_GENERATED = 'generated';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED  = 'returned';
	const SUMMARY_REVOKED   = 'revoked';
	const SUMMARY_ACCEPTED  = 'accepted';
	const SUMMARY_ON_FILE   = 'on_file';
	const META_TEMPLATE_VERSION = '_wpcpm_agreement_template_version';
	public static function is_settled( $record ) { asked( 'is_settled' ); return (bool) $GLOBALS['settled']; }
	public static function summary( $record ) { asked( 'summary' ); return $GLOBALS['summary'] + array( 'generated_id' => 0, 'pending_id' => 0 ); }
}

/* The import: the batch the form finds, and the batch it reads by that number. */
$GLOBALS['active_batch'] = 0;
$GLOBALS['batches']      = array();

class WPCPM_Institution_Import {
	const STATE_STAGED   = 'staged';
	const STATE_CREATING = 'creating';
	const OK             = 'ok';
	const NEAR_NAME      = 'near_name';
	const BLOCKED        = 'blocked';
	public static function active_for( $institution ) { asked( 'active_for' ); return (int) $GLOBALS['active_batch']; }
	public static function batch( $id ) { asked( 'batch' ); return $GLOBALS['batches'][ (int) $id ] ?? null; }
}

class WPCPM_Institution_Create {
	public static function tally( array $rows ) { return array( 'created' => count( array_filter( $rows, function ( $r ) { return ! empty( $r['created'] ); } ) ) ); }
	public static function remaining( array $rows ) { return count( array_filter( $rows, function ( $r ) { return empty( $r['created'] ); } ) ); }
}

/* The semester reports, cohort to report, newest cohort first as `reports_of()` sorts them. */
$GLOBALS['reports'] = array();

class WPCPM_Semester_Report {
	const STATE_APPROVED = 'approved';
	public static function reports_of( $institution ) { asked( 'reports_of' ); return $GLOBALS['reports']; }
	public static function team_counts( array $rows, $cohort ) { asked( 'team_counts' ); return $GLOBALS['team_counts'] ?? array(); }
	public static function state( $post ) { return $post->state; }
	public static function approved_at( $post ) { return $post->at > 0 ? array( 'at' => $post->at, 'by' => 1 ) : array(); }
	public static function cohort_of( $post ) { return $post->cohort; }
}

/** A report in a state, approved at a moment (0 for none). */
function report( $state, $at ) {
	return (object) array( 'state' => $state, 'at' => (int) $at, 'cohort' => '' );
}

/* The details requests: the latest outcome, and the open request the school sent since. */
$GLOBALS['outcome']      = array();
$GLOBALS['open_details'] = 0;

class WPCPM_Institution_Request {
	const STATE_DONE      = 'done';
	const STATE_DECLINED  = 'declined';
	const STATE_WITHDRAWN = 'withdrawn';
	public static function latest_details_outcome( $institution ) { asked( 'latest_details_outcome' ); return $GLOBALS['outcome']; }
	public static function open_details_for( $institution ) { asked( 'open_details_for' ); return (int) $GLOBALS['open_details']; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details-card.php';
// 1.123.98: what the Overview's new rows ask, where this suite stands in for none of it.
require_once __DIR__ . '/stubs/overview-more.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';

$GLOBALS['failed'] = 0;

function ck( $label, $actual, $expected ) {
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}

	$GLOBALS['failed']++;
	echo "FAIL $label\n     expected: " . var_export( $expected, true ) . "\n     actual:   " . var_export( $actual, true ) . "\n";
}

$has = static function ( $method ) {
	return method_exists( 'WPCPM_Institutions_Dashboard', $method );
};

/** The rows for a reader, worked out afresh (the memo forgotten), or a marker while the method is not there. */
function items( $record, $viewer, $can_manage ) {
	if ( method_exists( 'WPCPM_Institutions_Dashboard', 'forget' ) ) {
		WPCPM_Institutions_Dashboard::forget();
	}
	return method_exists( 'WPCPM_Institutions_Dashboard', 'waiting_items' ) ? WPCPM_Institutions_Dashboard::waiting_items( $record, $viewer, $can_manage ) : array( 'no waiting_items()' );
}

/** The cards' own sentences, or a marker while the method is not there. */
function state_sentence( $state ) { return method_exists( 'WPCPM_Institution_Panel', 'state_sentence' ) ? WPCPM_Institution_Panel::state_sentence( $state ) : 'no state_sentence()'; }
function waiting_sentence( $batch, $record ) { return method_exists( 'WPCPM_Institution_Import_Form', 'waiting_sentence' ) ? WPCPM_Institution_Import_Form::waiting_sentence( $batch, $record ) : 'no waiting_sentence()'; }
function outcome_sentence( array $outcome ) { return method_exists( 'WPCPM_Institution_Details_Card', 'outcome_sentence' ) ? WPCPM_Institution_Details_Card::outcome_sentence( $outcome ) : 'no outcome_sentence()'; }

/** How many rows of each kind, and the counts the menu would show. */
function kinds( array $items ) {
	return array( array_map( static function ( $x ) { return is_array( $x ) ? ( $x['kind'] ?? 'no kind' ) . ':' . $x['tag'] : $x; }, $items ), method_exists( 'WPCPM_Institutions_Dashboard', 'waiting_by_view' ) ? WPCPM_Institutions_Dashboard::waiting_by_view( $items ) : null );
}

/** One field of a row, or null. */
function field( array $items, $i, $key ) {
	return isset( $items[ $i ] ) && is_array( $items[ $i ] ) && array_key_exists( $key, $items[ $i ] ) ? $items[ $i ][ $key ] : null;
}

$rec_locked = 'recEXA00000000001';
$rec        = 'recEXA00000000002';

$locked = new WP_User( 3, 'locked' );
$admin  = new WP_User( 1, 'admin' );
$rep    = new WP_User( 5, 'rep' );
$tutor  = new WP_User( 6, 'tutor' );
$tutor_locked = new WP_User( 7, 'tutor_locked' );

$GLOBALS['members'] = array(
	3 => array( $rec_locked, 'representative' ),
	5 => array( $rec, 'representative' ),
	6 => array( $rec, 'tutor' ),
	7 => array( $rec_locked, 'tutor' ),
);
$GLOBALS['allow'] = array(
	// A locked school's representative is asked the agreement alone; its tutor is asked nothing.
	'locked'       => array( 'agreement' => true ),
	'tutor_locked' => array(),
	'admin'        => array( 'view_roster' => true, 'add_student' => true, 'view_semester_report' => true, 'agreement' => true, 'edit_details' => true ),
	'rep'          => array( 'view_roster' => true, 'add_student' => true, 'view_semester_report' => true, 'agreement' => true, 'edit_details' => true ),
	// The policy's tutor ground: the roster and the reports, never the details card (`ACT_EDIT_DETAILS`).
	'tutor'        => array( 'view_roster' => true, 'view_semester_report' => true ),
);

echo "=== What waits: the agreement, while the school is the one to act ===\n";

$GLOBALS['summary'] = array( 'state' => 'returned', 'airtable_status' => '' );
$items = items( $rec_locked, $locked, false );
ck( 'a returned agreement waits', array( field( $items, 0, 'title' ), field( $items, 0, 'tag' ), field( $items, 0, 'card' ), field( $items, 0, 'action' ) ), array( 'Collaboration Agreement returned', 'Waits', 'agreement', 'Open the agreement' ) );
ck( 'its why is the panel\'s own sentence', field( $items, 0, 'why' ), state_sentence( 'returned' ) );
ck( 'and the row is whole: counted once, red, no query arguments, it waits', array( count( $items ), field( $items, 0, 'count' ), field( $items, 0, 'tone' ), field( $items, 0, 'args' ), field( $items, 0, 'news' ), field( $items, 0, 'kind' ) ), array( 1, 1, 'red', array(), false, 'waits' ) );
ck( 'its keys are the ones the Overview reads, in this order', is_array( $items[0] ) ? array_keys( $items[0] ) : null, array( 'card', 'count', 'title', 'why', 'action', 'kind', 'tag', 'tone', 'args', 'news' ) );

$GLOBALS['summary'] = array( 'state' => 'revoked', 'airtable_status' => '' );
$items = items( $rec_locked, $locked, false );
ck( 'a revoked agreement waits, with the panel\'s sentence', array( field( $items, 0, 'title' ), field( $items, 0, 'why' ) ), array( 'Collaboration Agreement revoked', state_sentence( 'revoked' ) ) );

$GLOBALS['summary'] = array( 'state' => 'none', 'airtable_status' => '' );
$items = items( $rec_locked, $locked, false );
ck( 'no agreement recorded waits: not signed yet, with the panel\'s sentence', array( field( $items, 0, 'title' ), field( $items, 0, 'why' ), field( $items, 0, 'card' ) ), array( 'Collaboration Agreement not signed yet', state_sentence( 'none' ), 'agreement' ) );

$GLOBALS['summary'] = array( 'state' => 'submitted', 'airtable_status' => '' );
ck( 'a copy waiting for review is no row', items( $rec_locked, $locked, false ), array() );
$GLOBALS['summary'] = array( 'state' => 'accepted', 'airtable_status' => 'Awaiting review' );
ck( 'an acceptance the base has not confirmed is no row for the school: there is nothing it can do', items( $rec_locked, $locked, false ), array() );
// Fix round 1, Important 2: the agreement does not ask a tutor, so a tutor of a locked school has no agreement row.
foreach ( array( 'none', 'generated', 'returned', 'revoked' ) as $state ) {
	$GLOBALS['summary'] = array( 'state' => $state, 'airtable_status' => '' );
	ck( 'a tutor of a locked school, ' . $state . ': no agreement row, nothing counted', kinds( items( $rec_locked, $tutor_locked, false ) ), array( array(), array() ) );
}

// A locked school's import does not open: Enroll students is not in its menu.
$GLOBALS['summary']      = array( 'state' => 'returned', 'airtable_status' => '' );
$GLOBALS['active_batch'] = 7;
$GLOBALS['batches']      = array( 7 => array( 'id' => 7, 'institution' => $rec_locked, 'state' => 'staged', 'rows' => array( array( 'verdict' => 'ok' ) ) ) );
$GLOBALS['q'] = array();
ck( 'on a locked page a waiting list is no row: its view is not in the menu', array_column( items( $rec_locked, $locked, false ), 'card' ), array( 'agreement' ) );
ck( 'and the list is not even looked for, nor the reports or the requests', array( isset( $GLOBALS['q']['active_for'] ), isset( $GLOBALS['q']['batch'] ), isset( $GLOBALS['q']['reports_of'] ), isset( $GLOBALS['q']['latest_details_outcome'] ) ), array( false, false, false, false ) );
$GLOBALS['active_batch'] = 0;

echo "\n=== The Administrator's own row (ruling P1) ===\n";

$GLOBALS['summary'] = array( 'state' => 'none', 'airtable_status' => 'Awaiting review' );
$a = items( $rec_locked, $admin, true );
ck( 'the Administrator sees one agreement row', count( array_filter( $a, static function ( $x ) { return is_array( $x ) && 'agreement' === $x['card']; } ) ), 1 );
ck( 'and it is the banner as a row', array( field( $a, 0, 'title' ), field( $a, 0, 'why' ) ), array( 'The agreement is not settled', $has( 'banner_sentences' ) ? implode( ' ', WPCPM_Institutions_Dashboard::banner_sentences( $rec_locked ) ) : 'no banner_sentences()' ) );
ck( 'while the school is the one to act it is said "With the school", to the agreement, and not counted', array( field( $a, 0, 'tag' ), field( $a, 0, 'kind' ), field( $a, 0, 'tone' ), field( $a, 0, 'action' ), field( $a, 0, 'card' ), field( $a, 0, 'news' ), kinds( $a )[1] ), array( 'With the school', 'neutral', '', 'Open the agreement', 'agreement', false, array() ) );
// Fix round 1, Important 2: the row waits for an Administrator only in the states an Administrator acts on.
$admin_states = array();
foreach ( array( 'none', 'generated', 'submitted', 'returned', 'revoked', 'accepted', 'on_file' ) as $state ) {
	$GLOBALS['summary']     = array( 'state' => $state, 'airtable_status' => '' );
	$admin_states[ $state ] = kinds( items( $rec_locked, $admin, true ) );
}
ck( 'an Administrator in each agreement state: one row, waiting and counted only for a copy to review or the two sources disagreeing', $admin_states, array(
	'none'      => array( array( 'neutral:With the school' ), array() ),
	'generated' => array( array( 'neutral:With the school' ), array() ),
	'submitted' => array( array( 'waits:Waits' ), array( 'agreement' => 1 ) ),
	'returned'  => array( array( 'neutral:With the school' ), array() ),
	'revoked'   => array( array( 'neutral:With the school' ), array() ),
	'accepted'  => array( array( 'waits:Waits' ), array( 'agreement' => 1 ) ),
	'on_file'   => array( array( 'waits:Waits' ), array( 'agreement' => 1 ) ),
) );
$GLOBALS['summary'] = array( 'state' => 'none', 'airtable_status' => 'Awaiting review' );
ck( 'the banner\'s sentences: the state in words, then what Airtable records', $has( 'banner_sentences' ) ? WPCPM_Institutions_Dashboard::banner_sentences( $rec_locked ) : null, array(
	// Task 25, fix round 1, B1: the sentence says what a school with no settled agreement shows its people now.
	'This institution\'s agreement is not settled: not started. Its representatives see only the Overview and the Agreement, and its tutors no page.',
	'Airtable records the Agreement Status as Awaiting review.',
) );
$GLOBALS['summary'] = array( 'state' => 'submitted', 'airtable_status' => '' );
ck( 'with no Agreement Status the banner is one sentence, and a copy waiting for review waits for the Administrator', array( $has( 'banner_sentences' ) ? WPCPM_Institutions_Dashboard::banner_sentences( $rec_locked ) : null, field( items( $rec_locked, $admin, true ), 0, 'why' ) ), array(
	// Task 25, fix round 1, B1: the sentence says what a school with no settled agreement shows its people now.
	array( 'This institution\'s agreement is not settled: a signed copy is waiting for review. Its representatives see only the Overview and the Agreement, and its tutors no page.' ),
	'This institution\'s agreement is not settled: a signed copy is waiting for review. Its representatives see only the Overview and the Agreement, and its tutors no page.',
) );

// S2 (1.123.99): on the Agreement view the panel's own sentence names the state, and for no agreement and a revoked one
// also what the school's people see, in more words than the banner (the date, the template, the note, "until it does").
// There the banner says each fact once: it drops the state it repeats, or its first sentence whole where the panel's
// sentence holds both; what Airtable records stays. The Overview's row reads every sentence as before.
$s2 = array();
foreach ( array( 'none', 'generated', 'submitted', 'returned', 'revoked', 'accepted', 'on_file' ) as $state ) {
	foreach ( array( 'Not started', '' ) as $status ) {
		$GLOBALS['summary'] = array( 'state' => $state, 'airtable_status' => $status );
		$s2[ $state . ( '' === $status ? '' : ', Airtable' ) ] = $has( 'banner_sentences' ) ? array( WPCPM_Institutions_Dashboard::banner_sentences( $rec_locked, true ), WPCPM_Institutions_Dashboard::banner_sentences( $rec_locked ) === WPCPM_Institutions_Dashboard::banner_sentences( $rec_locked, false ) ) : null;
	}
}
$s2_short = 'This institution\'s agreement is not settled. Its representatives see only the Overview and the Agreement, and its tutors no page.';
$s2_air   = 'Airtable records the Agreement Status as Not started.';
ck( 'S2: on the Agreement view the banner says each fact once; the Overview\'s sentences do not move', $s2, array(
	'none, Airtable'      => array( array( $s2_air ), true ),
	'none'                => array( array(), true ),
	'generated, Airtable' => array( array( $s2_short, $s2_air ), true ),
	'generated'           => array( array( $s2_short ), true ),
	'submitted, Airtable' => array( array( $s2_short, $s2_air ), true ),
	'submitted'           => array( array( $s2_short ), true ),
	'returned, Airtable'  => array( array( $s2_short, $s2_air ), true ),
	'returned'            => array( array( $s2_short ), true ),
	'revoked, Airtable'   => array( array( $s2_air ), true ),
	'revoked'             => array( array(), true ),
	// F3: the two disagreement states keep the full note, which alone says Airtable has not confirmed the site's copy.
	'accepted, Airtable'  => array( array( 'This institution\'s agreement is not settled: accepted on this site, which Airtable has not confirmed. Its representatives see only the Overview and the Agreement, and its tutors no page.', $s2_air ), true ),
	'accepted'            => array( array( 'This institution\'s agreement is not settled: accepted on this site, which Airtable has not confirmed. Its representatives see only the Overview and the Agreement, and its tutors no page.' ), true ),
	'on_file, Airtable'   => array( array( 'This institution\'s agreement is not settled: on file according to this site, which Airtable has not confirmed. Its representatives see only the Overview and the Agreement, and its tutors no page.', $s2_air ), true ),
	'on_file'             => array( array( 'This institution\'s agreement is not settled: on file according to this site, which Airtable has not confirmed. Its representatives see only the Overview and the Agreement, and its tutors no page.' ), true ),
) );
$GLOBALS['summary'] = array( 'state' => 'none', 'airtable_status' => 'Not started' );
ck( 'S2: the Overview\'s row for a school with no agreement reads as the brief quotes it', field( items( $rec_locked, $admin, true ), 0, 'why' ), 'This institution\'s agreement is not settled: not started. Its representatives see only the Overview and the Agreement, and its tutors no page. Airtable records the Agreement Status as Not started.' );
$GLOBALS['summary'] = array( 'state' => 'submitted', 'airtable_status' => '' );

// The banner's sentences are printed by the Agreement view's panel, inside its anchored block (Task 21,
// bin/test-institution-panel.php); the dashboard's own printer of them went with the old page (Task 23).
ck( 'the dashboard prints no banner of its own: banner_sentences() is what the panel and the Overview read', method_exists( 'WPCPM_Institutions_Dashboard', 'render_banner' ), false );

echo "\n=== News after waits, tagged New, not counted (ruling 4) ===\n";

$GLOBALS['summary']      = array( 'state' => 'accepted', 'airtable_status' => '' );
$GLOBALS['settled']      = true;
$GLOBALS['active_batch'] = 7;
$GLOBALS['batches']      = array( 7 => array( 'id' => 7, 'institution' => $rec, 'state' => 'staged', 'rows' => array( array( 'verdict' => 'ok' ), array( 'verdict' => 'blocked' ) ) ) );
$GLOBALS['reports']      = array( '2026-H1' => report( 'approved', NOW - 3 * DAY ), '2025-H2' => report( 'approved', NOW - 40 * DAY ) );
$GLOBALS['outcome']      = array( 'state' => 'declined', 'closed_at' => NOW - 2 * DAY, 'note' => 'Use the full name.' );
$GLOBALS['open_details'] = 0;
$GLOBALS['filters']      = array();
$i = items( $rec, $rep, false );
ck( 'order and tags', array_map( static function ( $x ) { return is_array( $x ) ? $x['card'] . ':' . $x['tag'] : $x; }, $i ), array( 'import:Waits', 'report:New', 'details:New' ) );
ck( 'the list row: its title, the form\'s own sentence, its action', array( field( $i, 0, 'title' ), field( $i, 0, 'why' ), field( $i, 0, 'action' ), field( $i, 0, 'count' ), field( $i, 0, 'tone' ) ), array( 'A checked list of students waits to be created', waiting_sentence( 7, $rec ), 'Open the list', 1, 'red' ) );
ck( 'and the form\'s sentence for a checked list is how many of its rows can be created', waiting_sentence( 7, $rec ), '1 of 2 rows can be created.' );
ck( 'and for another school\'s batch the form says nothing', waiting_sentence( 7, $rec_locked ), '' );
ck( 'the report row', array( field( $i, 1, 'title' ), field( $i, 1, 'action' ), field( $i, 1, 'args' ) ), array( 'Your semester report for January to June 2026 is ready', 'Read the report', array( 'wpcpm_report' => '2026-H1' ) ) );
ck( 'its why is the day as the report index prints it', field( $i, 1, 'why' ), 'Approved on ' . gmdate( 'F j, Y', NOW - 3 * DAY ) );
ck( 'news is neutral and counted nowhere', array( field( $i, 1, 'tone' ), field( $i, 1, 'news' ), field( $i, 1, 'kind' ), field( $i, 2, 'tone' ), field( $i, 2, 'news' ), field( $i, 2, 'kind' ) ), array( '', true, 'news', '', true, 'news' ) );
ck( 'a report approved 40 days ago is no row', count( $i ), 3 );
ck( 'the request row', array( field( $i, 2, 'title' ), field( $i, 2, 'why' ), field( $i, 2, 'action' ) ), array( 'Your request was decided', 'An Administrator did not make the change you asked for: Use the full name.', 'See your details' ) );
ck( 'its why is the card\'s own sentence', field( $i, 2, 'why' ), outcome_sentence( $GLOBALS['outcome'] ) );
ck( 'only waits are counted', $has( 'waiting_by_view' ) ? WPCPM_Institutions_Dashboard::waiting_by_view( $i ) : null, array( 'enroll' => 1 ) );
ck( 'the 30-day window reads the one moment the hook gives', in_array( 'wpcpm_institution_now', $GLOBALS['filters'], true ), true );
$GLOBALS['open_details'] = 31;
ck( 'a newer request sent: no decided row', count( items( $rec, $rep, false ) ), 2 );
$GLOBALS['open_details'] = 0;

// Review Focus 4: a tutor never gets a row that opens Enroll students.
$GLOBALS['open_details'] = 31;
$GLOBALS['q'] = array();
ck( 'a tutor has no list row', array_column( items( $rec, $tutor, false ), 'card' ), array( 'report' ) );
ck( 'and the list is not even looked for', isset( $GLOBALS['q']['active_for'] ), false );
$GLOBALS['open_details'] = 0;

// Fix round 1, Important 2: news is the school's own. An Administrator looking at the school gets the list, no news,
// and the reports and requests are not read for them.
$GLOBALS['q'] = array();
ck( 'an Administrator gets no news row', kinds( items( $rec, $admin, true ) ), array( array( 'waits:Waits' ), array( 'enroll' => 1 ) ) );
ck( 'and nothing is read for news', array( isset( $GLOBALS['q']['reports_of'] ), isset( $GLOBALS['q']['latest_details_outcome'] ) ), array( false, false ) );

echo "\n=== Worked out once a request (Fix round 1, Important 3) ===\n";

$memo_ok = method_exists( 'WPCPM_Institutions_Dashboard', 'forget' );
if ( $memo_ok ) {
	WPCPM_Institutions_Dashboard::forget();
}
$GLOBALS['q'] = array();
$once         = WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false );
$first        = $GLOBALS['q'];
$views_once   = WPCPM_Institutions_Dashboard::views_for( $rec, $rep, false );
$twice        = WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false );
ck( 'two calls and the menu\'s views: one set of queries, the same rows', array( $GLOBALS['q'], $twice ), array( $first, $once ) );
// Task 25, fix round 1, B2: the details row asks the details card's own question, once.
// 1.123.98: two more of the policy's answers, whether the reader may resend an invitation and edit a student (the rows
// that wait on the Students view), each asked once, and whether the agreement is settled, asked once for the new rows;
// the roster is empty here, so nothing else is read.
ck( 'the one set asks each thing once', $first, array( 'decide' => 6, 'is_settled' => 3, 'active_for' => 1, 'batch' => 1, 'reports_of' => 1, 'latest_details_outcome' => 1, 'open_details_for' => 1 ) );
// Task 25, fix round 1, B2: a tutor is not drawn the details card, so a tutor has no request row.
ck( 'another reader is worked out on their own', array_column( WPCPM_Institutions_Dashboard::waiting_items( $rec, $tutor, false ), 'card' ), array( 'report' ) );
$GLOBALS['active_batch'] = 0;
ck( 'until forget(), a change is not seen: the memo holds', array_column( WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false ), 'card' ), array( 'import', 'report', 'details' ) );
if ( $memo_ok ) {
	WPCPM_Institutions_Dashboard::forget();
}
$GLOBALS['q'] = array();
ck( 'forget() clears it: the rows are worked out again', array( $memo_ok, array_column( WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false ), 'card' ), $GLOBALS['q']['reports_of'] ?? 0 ), array( true, array( 'report', 'details' ), 1 ) );
$GLOBALS['active_batch'] = 7;

echo "\n=== The menu's counts work out no news (Task 28, W5) ===\n";

// Every view's menu counts the rows that wait, and only the Overview prints news: the counts' call (`$news` false) asks for
// no report list and no details outcome, and the Overview's call after it reads the news alone, the rows that wait kept.
WPCPM_Institutions_Dashboard::forget();
$GLOBALS['q'] = array();
$w5_counts    = WPCPM_Institutions_Dashboard::waiting_by_view( WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false, false ) );
$w5_asked     = $GLOBALS['q'];
ck( 'the counts ask for no report list and no details outcome', array( isset( $w5_asked['reports_of'] ), isset( $w5_asked['latest_details_outcome'] ), isset( $w5_asked['open_details_for'] ) ), array( false, false, false ) );
$GLOBALS['q'] = array();
$w5_rows      = WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false );
ck( 'and the Overview, after them, reads the news once and nothing that waits again', array( $GLOBALS['q']['reports_of'] ?? 0, $GLOBALS['q']['latest_details_outcome'] ?? 0, $GLOBALS['q']['active_for'] ?? 0, $GLOBALS['q']['batch'] ?? 0 ), array( 1, 1, 0, 0 ) );
ck( 'the counts are the same with the news and without', WPCPM_Institutions_Dashboard::waiting_by_view( $w5_rows ), $w5_counts );
ck( 'the rows without the news are the rows with it less the news', array_values( array_filter( $w5_rows, static function ( $r ) { return ! $r['news']; } ) ), WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false, false ) );
$w5_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php' );
ck( 'the page\'s counts and the guide\'s menu ask without the news; the Overview alone with it', array(
	substr_count( $w5_src, 'self::waiting_by_view( self::waiting_items( $record, $viewer, $can_manage, false ) )' ),
	substr_count( $w5_src, 'self::waiting_by_view( self::waiting_items( $record, $viewer, $manage, false ) )' ),
	substr_count( $w5_src, 'self::render_waiting( self::waiting_items( $record, $viewer, $can_manage ), $managed );' ),
), array( 1, 1, 1 ) );
WPCPM_Institutions_Dashboard::forget();

echo "\n=== The window's edges and the news rows' order ===\n";

$GLOBALS['active_batch'] = 0;
$GLOBALS['outcome']      = array();
$GLOBALS['reports']      = array(
	'2026-H1' => report( 'approved', NOW - 20 * DAY ),
	'2025-H2' => report( 'approved', NOW - 1 * DAY ),
	'2025-H1' => report( 'approved', NOW - 30 * DAY ),
	'2024-H2' => report( 'approved', NOW - 30 * DAY - 1 ),
	'2024-H1' => report( 'draft', NOW - 1 * DAY ),
	'2023-H2' => report( 'approved', 0 ),
);
ck( 'newest approval first; thirty days to the second is in, a second more is out; a draft and an approval with no day are no rows', array_map( static function ( $x ) { return is_array( $x ) ? $x['args']['wpcpm_report'] : $x; }, items( $rec, $rep, false ) ), array( '2025-H2', '2026-H1', '2025-H1' ) );
$GLOBALS['reports'] = array();
$GLOBALS['outcome'] = array( 'state' => 'done', 'closed_at' => NOW - 30 * DAY, 'note' => '' );
$done = items( $rec, $rep, false );
ck( 'an approved request is news, with the card\'s "Updated on" sentence', array( field( $done, 0, 'card' ), field( $done, 0, 'why' ) ), array( 'details', 'Updated on ' . WPCPM_Dates::day( NOW - 30 * DAY ) . '.' ) );
$GLOBALS['outcome'] = array( 'state' => 'declined', 'closed_at' => NOW - 31 * DAY, 'note' => '' );
ck( 'a request decided 31 days ago is no row', items( $rec, $rep, false ), array() );
// Fix round 1, Important 2: a stamp later than now is not news.
$GLOBALS['outcome'] = array( 'state' => 'declined', 'closed_at' => NOW + 1, 'note' => '' );
$GLOBALS['reports'] = array( '2026-H1' => report( 'approved', NOW + 1 ) );
ck( 'a stamp in the future is not news: neither the report nor the request', items( $rec, $rep, false ), array() );
$GLOBALS['reports'] = array();
$GLOBALS['outcome'] = array( 'state' => 'withdrawn', 'closed_at' => NOW - 1 * DAY, 'note' => '' );
ck( 'a withdrawal is no row', items( $rec, $rep, false ), array() );
$GLOBALS['outcome'] = array( 'state' => 'declined', 'closed_at' => NOW - 1 * DAY, 'note' => '' );
ck( 'a decline without a note is news with the card\'s sentence', field( items( $rec, $rep, false ), 0, 'why' ), 'An Administrator did not make the change you asked for.' );
ck( 'and nothing waits: no counts', $has( 'waiting_by_view' ) ? WPCPM_Institutions_Dashboard::waiting_by_view( items( $rec, $rep, false ) ) : null, array() );

// Fix round 1, Important 2: a creation in progress carries on by itself, so it is "In progress", not counted.
$GLOBALS['outcome']      = array();
$GLOBALS['active_batch'] = 8;
$GLOBALS['batches']      = array( 8 => array( 'id' => 8, 'institution' => $rec, 'state' => 'creating', 'rows' => array( array( 'created' => 1 ), array(), array() ) ) );
$run = items( $rec, $rep, false );
ck( 'a creation in progress is said "In progress", with the form\'s sentence, and counted nowhere', array( field( $run, 0, 'card' ), field( $run, 0, 'why' ), field( $run, 0, 'tone' ) ), array( 'import', '1 created so far. 2 still to create.', '' ) );
ck( 'its kind and tag, and the counts', kinds( $run ), array( array( 'neutral:In progress' ), array() ) );
// Task 28, W4: under "In progress" the title says the list is being created; the checked list's title is the waiting one.
ck( 'a creation in progress is titled so', field( $run, 0, 'title' ), 'A list of students is being created' );

echo "\n=== 1.123.98: the students' rows, from the roster's rows and one read of the accounts ===\n";

// The suite's day is 2026-10-24. Seven students of every cohort: two without a sign-in on record on the program now or
// waiting for a mentor, a placement that ended and is still open, two waiting to graduate (the longer since NOW less 20
// days), two finishing within 30 days (one today), and a graduate without a sign-in, who is no row's.
function o98_row( $id, $status, $start, $end, $user, $mentor = true, array $more = array() ) {
	return array_merge( array( 'record_id' => $id, 'name' => 'Student ' . $id, 'email_key' => strtolower( $id ) . '@example.org', 'status' => $status, 'start' => $start, 'end' => $end, 'has_mentor' => $mentor, 'reports' => array(), 'user_id' => $user, 'institution' => 'recEXA00000000002', 'team' => '', 'hours' => '', 'mentor_name' => '' ), $more );
}
$o98_rows = array(
	'recSTU00000000001' => o98_row( 'recSTU00000000001', 'Developer Track', '2026-07-01', '2026-11-03', 101 ),
	'recSTU00000000002' => o98_row( 'recSTU00000000002', 'Developer Track', '2026-07-01', '2026-10-23', 102 ),
	'recSTU00000000003' => o98_row( 'recSTU00000000003', 'In Sensei', '2026-07-01', '2026-12-23', 103, false ),
	'recSTU00000000004' => o98_row( 'recSTU00000000004', 'Pending graduation', '2026-01-10', '2026-06-30', 104 ),
	'recSTU00000000005' => o98_row( 'recSTU00000000005', 'Pending graduation', '2026-01-10', '2026-06-30', 105 ),
	'recSTU00000000006' => o98_row( 'recSTU00000000006', 'Graduate', '2026-01-10', '2026-06-30', 106 ),
	'recSTU00000000007' => o98_row( 'recSTU00000000007', 'Designer Track', '2026-07-01', '2026-10-24', 0 ),
);
// Fix round 1, B2: the site records sign-ins since NOW less 40 days. The students' accounts were made after it, but for
// three rows with no sign-in: 108's account made before the record began, 109's account gone, 110's not a student's.
$o98_rows['recSTU00000000008'] = o98_row( 'recSTU00000000008', 'Developer Track', '2026-07-01', '2026-12-31', 108 );
$o98_rows['recSTU00000000009'] = o98_row( 'recSTU00000000009', 'Developer Track', '2026-07-01', '2026-12-31', 109 );
$o98_rows['recSTU00000000010'] = o98_row( 'recSTU00000000010', 'Developer Track', '2026-07-01', '2026-12-31', 110 );
$GLOBALS['opts']['wpcpm_sign_ins_since'] = NOW - 40 * DAY;
foreach ( array( 101, 102, 103, 104, 105, 106, 108, 110 ) as $o98_id ) {
	$o98_user                  = new WP_User( $o98_id, 'student' . $o98_id );
	$o98_user->roles           = 110 === $o98_id ? array( 'subscriber' ) : array( 'wpcpm_student' );
	$o98_user->user_email      = 'student' . $o98_id . '@example.org';
	$o98_user->user_registered = gmdate( 'Y-m-d H:i:s', 108 === $o98_id ? NOW - 41 * DAY : NOW - 39 * DAY );
	$GLOBALS['users'][ $o98_id ] = $o98_user;
}
$GLOBALS['opts'][ 'wpcpm_roster_' . $rec ] = wpcpm_test_roster( $o98_rows );
$GLOBALS['umeta'] = array(
	102 => array( 'wpcpm_last_sign_in' => NOW - 5 * DAY ),
	104 => array( 'wpcpm_last_sign_in' => NOW - 9 * DAY, 'wpcpm_student_pending_since' => array( 'at' => NOW - 20 * DAY, 'before' => false ) ),
	105 => array( 'wpcpm_last_sign_in' => NOW - 9 * DAY, 'wpcpm_student_pending_since' => array( 'at' => NOW - 5 * DAY, 'before' => false ) ),
);
$GLOBALS['allow']['rep'] += array( 'resend_invite' => true, 'edit_student' => true );
$GLOBALS['allow']['admin'] += array( 'resend_invite' => true, 'edit_student' => true );
$GLOBALS['settled']      = true;
$GLOBALS['active_batch'] = 0;
$GLOBALS['reports']      = array();
$GLOBALS['outcome']      = array();

$o98_keys = array();
foreach ( array( 'no-sign-in', 'ended-open', 'pending-graduation', 'finishing' ) as $o98_key ) {
	WPCPM_Institutions_Dashboard::forget();
	$o98_keys[ $o98_key ] = array_keys( method_exists( 'WPCPM_Institutions_Dashboard', 'only_rows' ) ? WPCPM_Institutions_Dashboard::only_rows( $rec, $o98_key ) : array() );
}
ck( 'the four lists hold the students each names, from every cohort', $o98_keys, array(
	'no-sign-in'         => array( 'recSTU00000000001', 'recSTU00000000003' ),
	'ended-open'         => array( 'recSTU00000000002' ),
	'pending-graduation' => array( 'recSTU00000000004', 'recSTU00000000005' ),
	'finishing'          => array( 'recSTU00000000001', 'recSTU00000000007' ),
) );
// Fix round 1, B5: words for one: one placement ended has the singular line; several the plural; one student waiting has
// "The wait", several "The longest wait".
$o98_plural = $o98_rows;
$o98_plural['recSTU00000000011'] = o98_row( 'recSTU00000000011', 'Developer Track', '2026-07-01', '2026-10-20', 0 );
unset( $o98_plural['recSTU00000000005'] );
$GLOBALS['opts'][ 'wpcpm_roster_' . $rec ] = wpcpm_test_roster( $o98_plural );
$o98_words = items( $rec, $rep, false );
$GLOBALS['opts'][ 'wpcpm_roster_' . $rec ] = wpcpm_test_roster( $o98_rows );
WPCPM_Institutions_Dashboard::forget();

$GLOBALS['cache_users_calls'] = array();
$GLOBALS['q']                 = array();
$o98_rep = items( $rec, $rep, false );
$o98_row = static function ( array $items, $only ) {
	foreach ( $items as $item ) {
		if ( ( $item['args']['wpcpm_only'] ?? '' ) === $only ) {
			return array( $item['card'], $item['kind'], $item['tag'], $item['title'], $item['why'], $item['action'] );
		}
	}
	return null;
};
ck( 'B5: several placements ended say so for several, one student waiting to graduate says "The wait"', array( $o98_row( $o98_words, 'ended-open' )[3] ?? null, $o98_row( $o98_words, 'ended-open' )[4] ?? null, $o98_row( $o98_words, 'pending-graduation' )[4] ?? null ), array( '2 placements ended and are still open', 'The end dates have passed and the students are still on the program. Correct a date if its placement goes on.', 'The program is checking their work. The wait began on ' . gmdate( 'F j, Y', NOW - 20 * DAY ) . '.' ) );
// Fix round 1, B1 (the owner's decision): the four are facts of the school, with no tag and counted nowhere, in the order
// a placement runs.
ck( 'a representative: the four facts with no tag, counted nowhere, in the order a placement runs', array(
	array_map( static function ( $i ) { return $i['args']['wpcpm_only'] ?? ''; }, array_values( array_filter( $o98_rep, static function ( $i ) { return isset( $i['args']['wpcpm_only'] ); } ) ) ),
	$o98_row( $o98_rep, 'no-sign-in' ),
	$o98_row( $o98_rep, 'finishing' ),
	$o98_row( $o98_rep, 'ended-open' ),
	$o98_row( $o98_rep, 'pending-graduation' ),
	WPCPM_Institutions_Dashboard::waiting_by_view( $o98_rep ),
), array(
	array( 'no-sign-in', 'finishing', 'ended-open', 'pending-graduation' ),
	array( 'roster', 'neutral', '', '2 students have not signed in yet', 'Open a student to send them an invitation.', 'See them' ),
	array( 'roster', 'neutral', '', '2 students finish within 30 days', '', 'See them' ),
	array( 'roster', 'neutral', '', '1 placement ended and is still open', 'The end date has passed and the student is still on the program. Correct the date if the placement goes on.', 'See them' ),
	array( 'roster', 'neutral', '', '2 students are waiting to graduate', 'The program is checking their work. The longest wait began on ' . gmdate( 'F j, Y', NOW - 20 * DAY ) . '.', 'See them' ),
	array(),
) );
ck( 'and the accounts\' meta is read once on the Overview, for the school\'s account holders', $GLOBALS['cache_users_calls'], array( array( 101, 102, 103, 104, 105, 106, 108, 109, 110 ) ) );

$GLOBALS['cache_users_calls'] = array();
$GLOBALS['q']                 = array();
WPCPM_Institutions_Dashboard::forget();
$o98_menu = WPCPM_Institutions_Dashboard::waiting_by_view( WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false, false ) );
ck( 'the menu\'s count holds none of them and reads no account; it asks for no fact and no team', array( $o98_menu, count( $GLOBALS['cache_users_calls'] ), isset( $GLOBALS['q']['team_counts'] ) ), array( array(), 0, false ) );

$o98_admin = items( $rec, $admin, true );
ck( 'an Administrator looking at the school: the four facts as the school has them, no tag, counted nowhere', array(
	array_slice( (array) $o98_row( $o98_admin, 'no-sign-in' ), 1, 2 ),
	array_slice( (array) $o98_row( $o98_admin, 'finishing' ), 1, 2 ),
	array_slice( (array) $o98_row( $o98_admin, 'ended-open' ), 1, 2 ),
	array_slice( (array) $o98_row( $o98_admin, 'pending-graduation' ), 1, 2 ),
	WPCPM_Institutions_Dashboard::waiting_by_view( $o98_admin ),
), array( array( 'neutral', '' ), array( 'neutral', '' ), array( 'neutral', '' ), array( 'neutral', '' ), array() ) );

$o98_tutor = items( $rec, $tutor, false );
ck( 'a tutor: the two facts, and neither row that waits', array( null === $o98_row( $o98_tutor, 'no-sign-in' ), null === $o98_row( $o98_tutor, 'ended-open' ), null !== $o98_row( $o98_tutor, 'pending-graduation' ), null !== $o98_row( $o98_tutor, 'finishing' ) ), array( true, true, true, true ) );

$GLOBALS['opts'][ 'wpcpm_roster_' . $rec_locked ] = wpcpm_test_roster( $o98_rows );
$GLOBALS['settled'] = false;
ck( 'a school with no settled agreement: none of them, for its representative or an Administrator', array(
	array_filter( array_map( static function ( $i ) { return $i['args']['wpcpm_only'] ?? null; }, items( $rec_locked, $locked, false ) ) ),
	array_filter( array_map( static function ( $i ) { return $i['args']['wpcpm_only'] ?? null; }, items( $rec_locked, $admin, true ) ) ),
), array( array(), array() ) );
$GLOBALS['settled'] = true;

// The longest wait is said only when it is true: a stamp that says the student was waiting before stamping began has no
// day to say "began on".
$GLOBALS['umeta'][104]['wpcpm_student_pending_since']['before'] = true;
ck( 'a longest wait whose stamp began before stamping is not said', $o98_row( items( $rec, $rep, false ), 'pending-graduation' )[4] ?? null, 'The program is checking their work.' );
$GLOBALS['umeta'][104]['wpcpm_student_pending_since']['before'] = false;

ck( 'the offered lists: a tutor is offered the facts and not the rows that wait; an unknown key nobody', array(
	WPCPM_Institutions_Dashboard::only_offered( 'no-sign-in', $rec, $tutor, false ),
	WPCPM_Institutions_Dashboard::only_offered( 'ended-open', $rec, $tutor, false ),
	WPCPM_Institutions_Dashboard::only_offered( 'pending-graduation', $rec, $tutor, false ),
	WPCPM_Institutions_Dashboard::only_offered( 'finishing', $rec, $tutor, false ),
	WPCPM_Institutions_Dashboard::only_offered( 'no-sign-in', $rec, $rep, false ),
	WPCPM_Institutions_Dashboard::only_offered( 'no-sign-in', $rec, $admin, true ),
	WPCPM_Institutions_Dashboard::only_offered( 'everyone', $rec, $rep, false ),
	WPCPM_Institutions_Dashboard::only_offered( 'finishing', $rec_locked, $locked, false ),
), array( false, false, true, true, true, true, false, false ) );

// The sign-in fence: WHETHER a sign-in is on record, and nothing more. The rows say no date of a sign-in, and the class
// reads the sign-in record through `has_signed_in()` alone.
$o98_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php' );
ck( 'the sign-in fence: has_signed_in() alone, no latest trace, no since, no sign-in date in any row', array(
	substr_count( $o98_src, 'WPCPM_Sign_Ins::has_signed_in( $' ),
	substr_count( $o98_src, 'WPCPM_Sign_Ins::latest_trace(' ) + substr_count( $o98_src, 'META_SIGN_IN' ),
	false !== strpos( serialize( $o98_rep ), gmdate( 'F j, Y', NOW - 5 * DAY ) ) || false !== strpos( serialize( $o98_rep ), gmdate( 'F j, Y', NOW - 9 * DAY ) ),
	// Fix round 1, B2: `user_registered` is read once, for the comparison with the record's start, and printed nowhere.
	substr_count( $o98_src, 'user_registered' ) - substr_count( $o98_src, '`user_registered`' ),
	false !== strpos( serialize( $o98_rep ), gmdate( 'Y-m-d H:i:s', NOW - 39 * DAY ) ) || false !== strpos( serialize( $o98_rep ), gmdate( 'F j, Y', NOW - 39 * DAY ) ),
), array( 1, 0, false, 1, false ) );
ck( 'B2: counted only an account that exists, is a student\'s with an address, was made since the record began and has no sign-in', array_keys( WPCPM_Institutions_Dashboard::only_rows( $rec, 'no-sign-in' ) ), array( 'recSTU00000000001', 'recSTU00000000003' ) );
$GLOBALS['opts']['wpcpm_sign_ins_since'] = 0;
WPCPM_Institutions_Dashboard::forget();
ck( 'and a site that records no sign-in counts nobody', WPCPM_Institutions_Dashboard::only_rows( $rec, 'no-sign-in' ), array() );
$GLOBALS['opts']['wpcpm_sign_ins_since'] = NOW - 40 * DAY;
WPCPM_Institutions_Dashboard::forget();

// Fix round 2, F1: a student waiting to graduate is counted under that fact alone, so one with a new account and no
// sign-in on record is not on the list of students who have not signed in yet (a finished student needs no invitation).
$o98_signed_105 = $GLOBALS['umeta'][105]['wpcpm_last_sign_in'];
unset( $GLOBALS['umeta'][105]['wpcpm_last_sign_in'] );
WPCPM_Institutions_Dashboard::forget();
ck( 'F1: a student waiting to graduate who has not signed in is not counted as not signed in yet, and stays under waiting to graduate', array( array_keys( WPCPM_Institutions_Dashboard::only_rows( $rec, 'no-sign-in' ) ), isset( WPCPM_Institutions_Dashboard::only_rows( $rec, 'pending-graduation' )['recSTU00000000005'] ) ), array( array( 'recSTU00000000001', 'recSTU00000000003' ), true ) );
$GLOBALS['umeta'][105]['wpcpm_last_sign_in'] = $o98_signed_105;
WPCPM_Institutions_Dashboard::forget();

$GLOBALS['opts'][ 'wpcpm_roster_' . $rec ]        = wpcpm_test_roster( array() );
$GLOBALS['opts'][ 'wpcpm_roster_' . $rec_locked ] = wpcpm_test_roster( array() );
WPCPM_Institutions_Dashboard::forget();

echo "\n=== Every row opens a view in the reader's menu, on a card drawn for them (Task 25, fix round 1, B2) ===\n";

// The question each card asks before it draws anything: the panel, the import form, the report screen and the details
// card. A row whose button lands on a card the reader is not drawn is a row the reader cannot act on.
$card_act = array(
	'agreement' => 'agreement',
	'import'    => 'add_student',
	'report'    => 'view_semester_report',
	'details'   => 'edit_details',
);
$sweep = array();
// 1.123.98: the students' rows open the Students view; each asks its own question (the two that wait the reader's acts,
// the two facts the roster's), and every school holds the seven students of the rows above.
$only_act = array( 'no-sign-in' => 'resend_invite', 'ended-open' => 'edit_student', 'pending-graduation' => 'view_roster', 'finishing' => 'view_roster' );
$GLOBALS['opts'][ 'wpcpm_roster_' . $rec ]        = wpcpm_test_roster( $o98_rows );
$GLOBALS['opts'][ 'wpcpm_roster_' . $rec_locked ] = wpcpm_test_roster( $o98_rows );
$sweep_seen = array();
$readers = array(
	'a representative of a settled school' => array( $rec, $rep, false, true ),
	'a tutor of a settled school'          => array( $rec, $tutor, false, true ),
	'a locked school\'s representative'    => array( $rec_locked, $locked, false, false ),
	// Task 25, fix round 2 (N6): a tutor of a school with no settled agreement is shown no page, so no row at all.
	'a locked school\'s tutor'            => array( $rec_locked, $tutor_locked, false, false ),
	'an Administrator, a settled school'   => array( $rec, $admin, true, true ),
	'an Administrator, a locked school'    => array( $rec_locked, $admin, true, false ),
);
// Every row there is at once: a list waiting, a report approved, a request decided, and, for a locked school, each state.
foreach ( array( 'none', 'generated', 'submitted', 'returned', 'revoked', 'accepted' ) as $sweep_state ) {
	foreach ( $readers as $sweep_name => $sweep_reader ) {
		list( $sweep_rec, $sweep_user, $sweep_manage, $sweep_settled ) = $sweep_reader;
		$GLOBALS['settled']      = $sweep_settled;
		$GLOBALS['summary']      = array( 'state' => $sweep_settled ? 'accepted' : $sweep_state, 'airtable_status' => '' );
		$GLOBALS['active_batch'] = 7;
		$GLOBALS['batches']      = array( 7 => array( 'id' => 7, 'institution' => $sweep_rec, 'state' => 'staged', 'rows' => array( array( 'verdict' => 'ok' ) ) ) );
		$GLOBALS['reports']      = array( '2026-H1' => report( 'approved', NOW - 3 * DAY ) );
		$GLOBALS['outcome']      = array( 'state' => 'done', 'closed_at' => NOW - 2 * DAY, 'note' => '' );
		$GLOBALS['open_details'] = 0;
		$sweep_items = items( $sweep_rec, $sweep_user, $sweep_manage );
		$sweep_views = WPCPM_Institutions_Dashboard::views_for( $sweep_rec, $sweep_user, $sweep_manage );
		foreach ( $sweep_items as $sweep_item ) {
			$sweep_view = WPCPM_Institutions_Dashboard::view_of( (string) $sweep_item['card'] );
			$sweep_act  = 'roster' === $sweep_item['card'] ? ( $only_act[ $sweep_item['args']['wpcpm_only'] ?? '' ] ?? '' ) : ( $card_act[ $sweep_item['card'] ] ?? '' );
			if ( 'roster' === $sweep_item['card'] ) {
				$sweep_seen[ $sweep_name ][ $sweep_item['args']['wpcpm_only'] ?? '' ] = true;
			}
			if ( ! in_array( $sweep_view, $sweep_views, true ) || '' === $sweep_act || empty( $GLOBALS['allow'][ $sweep_user->user_login ][ $sweep_act ] ) ) {
				$sweep[] = $sweep_name . ', ' . $sweep_state . ': ' . $sweep_item['title'];
			}
		}
	}
}
ck( 'no reader is given a row whose view is not in their menu or whose card is not drawn for them', array_values( array_unique( $sweep ) ), array() );
ck( 'and the students\' rows reach the readers they are for, and no school with no settled agreement', array_map( 'array_keys', $sweep_seen ), array(
	'a representative of a settled school' => array( 'no-sign-in', 'finishing', 'ended-open', 'pending-graduation' ),
	'a tutor of a settled school'          => array( 'finishing', 'pending-graduation' ),
	'an Administrator, a settled school'   => array( 'no-sign-in', 'finishing', 'ended-open', 'pending-graduation' ),
) );
// And the menu counts none of them for any reader (fix round 1, B1).
$o98_counts = array();
foreach ( $readers as $sweep_name => $sweep_reader ) {
	list( $sweep_rec, $sweep_user, $sweep_manage ) = $sweep_reader;
	WPCPM_Institutions_Dashboard::forget();
	$o98_counts[ $sweep_name ] = WPCPM_Institutions_Dashboard::waiting_by_view( WPCPM_Institutions_Dashboard::waiting_items( $sweep_rec, $sweep_user, $sweep_manage, false ) )['students'] ?? 0;
}
ck( 'and the menu counts none of them on Students, for any reader', array_sum( $o98_counts ), 0 );
$sweep_tutor = 0;
foreach ( array( 'none', 'generated', 'submitted', 'returned', 'revoked', 'accepted' ) as $sweep_state ) {
	$GLOBALS['settled'] = false;
	$GLOBALS['summary'] = array( 'state' => $sweep_state, 'airtable_status' => '' );
	$sweep_tutor       += count( items( $rec_locked, $tutor_locked, false ) );
}
ck( 'a tutor of a school with no settled agreement is given no row in any state', $sweep_tutor, 0 );

echo $GLOBALS['failed'] ? "\n{$GLOBALS['failed']} FAILURE(S)\n" : "\nall passed\n";
exit( $GLOBALS['failed'] ? 1 : 0 );
