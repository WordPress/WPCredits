<?php
/**
 * What each mentor has been told about, and the email for a new student (WPCPM_Mentor_Assigned).
 *
 * What this pins, and why:
 *
 * - The first run remembers every student on every list and emails nobody: the students mentors
 *   already have are not news (Review Focus 1). It also takes `known`, the lists the sync read
 *   through `stored_lists()` before it replaced them, which a later run ignores.
 * - The set only grows. A list that empties and comes back, a mentor missing from one run, or a
 *   mentor record moved to another account announces nobody twice (Review Focus 2).
 * - A student is remembered only once their email was accepted, and the set is written after each
 *   email, so a run that dies midway repeats at most one email (Review Focus 3).
 * - With the switch off the set keeps growing, so switching it on later sends no backlog (Review
 *   Focus 4).
 * - A ceiling of emails per run and a brake for a run that would tell far too many mentors at once.
 * - One student's line: name, track, institution, start date and address, on one line (Review
 *   Focus 5); a start date before the site's today reads "started". No student's name in a subject or in what `run()` answers.
 *
 * Every person here is invented, at example.org.
 *
 * Run from the plugin root:  php bin/test-mentor-assigned.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['opts']     = array( 'date_format' => 'j F Y' );
$GLOBALS['writes']   = array();
$GLOBALS['mail']     = array();
$GLOBALS['take']     = true;
$GLOBALS['throw_at'] = 0;
$GLOBALS['card']     = 'https://example.org/mentor-report-card/';
$GLOBALS['setting']  = true;
$GLOBALS['umeta']    = array();
$GLOBALS['refuse_write'] = false;
$GLOBALS['refused']  = array();

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	if ( ! empty( $GLOBALS['refuse_write'] ) ) {
		$GLOBALS['refused'][] = $k;
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	$GLOBALS['writes'][]   = array( $k, $a );
	return true;
}
function __( $s, $d = null ) { return $s; }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['umeta'] as $id => $meta ) {
		foreach ( isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() as $clause ) {
			if ( ! array_key_exists( $clause['key'], $meta ) ) {
				continue 2;
			}
		}
		$out[] = (string) $id;
	}
	return $out;
}
function home_url( $path = '' ) { return 'https://example.org' . $path; }
function date_i18n( $format, $time ) { return gmdate( $format, $time ); }
// The site's clock: `$GLOBALS['now']` when a check sets it, so "today" is a day the check chooses.
function wp_date( $format, $time = null ) { return gmdate( $format, null === $time ? ( isset( $GLOBALS['now'] ) ? (int) $GLOBALS['now'] : time() ) : $time ); }

class WP_User {
	public $ID = 0, $user_email = '';
	public function __construct( $id ) { $this->ID = $id; $this->user_email = 'mentor' . $id . '@example.org'; }
}

/** The switch, as the settings hold it. */
class WPCPM_Settings {
	public static function get_value( $key, $default = null ) {
		return 'mentor_assigned_email' === $key ? $GLOBALS['setting'] : $default;
	}
}

/** Track names, as the real reader prints them. */
class WPCPM_Program {
	public static function label( $status ) {
		$labels = array( 'Credits 150h' => 'WordPress Credits Program 150h' );
		$status = trim( (string) $status );
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}

/** A test institution is one whose name starts with the word TEST in capitals, as on the site (`WPCPM_Sponsors_Index::is_test_name()`). */
class WPCPM_Test_Institutions {
	public static function is_test( $name ) { return is_string( $name ) && 1 === preg_match( '/^TEST(?![\p{L}\p{N}])/u', trim( $name ) ); }
}

class WPCPM_Mail_Catalog {
	const TYPE_MENTOR      = 'mentor';
	const TYPE_STUDENT     = 'student';
	const TYPE_INSTITUTION = 'institution';
	const TYPE_SPONSOR     = 'sponsor';
	const TYPE_ADMIN       = 'admin';
}

/** The two keys the first run reads a stored list by. */
class WPCPM_Mentors_Sync {
	const META_RECORD_ID = 'wpcpm_mentor_record_id';
	const META_MENTEES   = 'wpcpm_mentees';
}

function _x( $s, $c, $d = null ) { return $s; }

// The shipped subject and body, read from the template file itself, so the subject checked below is
// the one the template really produces. The fill is the registry's one pass of `strtr`; its sentence
// rules have nothing to leave out here, since every placeholder of this email is given.
$GLOBALS['template'] = ( include dirname( __DIR__ ) . '/includes/mail/templates/invitations.php' )['student-assigned-mentor'];

/** The template layer, stubbed: fills the shipped subject and a body that shows every value. */
class WPCPM_Mail_Templates {
	public static function page_of_type( $type ) { return WPCPM_Mail_Catalog::TYPE_MENTOR === $type ? $GLOBALS['card'] : ''; }
	public static function mail( $id, $values ) {
		$GLOBALS['values'] = $values;
		$fill = array();
		foreach ( $values as $k => $v ) {
			$fill[ '{' . $k . '}' ] = $v;
		}
		return array(
			'subject' => strtr( $GLOBALS['template']['subject'], $fill ),
			'body'    => strtr( $GLOBALS['template']['body'], $fill ),
		);
	}
}

/** The mail layer, stubbed: builds the email as the real one does and records it. */
class WPCPM_Mail {
	public static function site_name() { return 'Example Site'; }
	public static function send( $user_id, $context, $build ) {
		$n = count( $GLOBALS['mail'] ) + 1;
		if ( $GLOBALS['throw_at'] === $n ) {
			throw new RuntimeException( 'mailer down' );
		}
		$mail = call_user_func( $build, new WP_User( $user_id ) );
		$GLOBALS['mail'][] = array( 'to' => $user_id, 'context' => $context, 'subject' => $mail['subject'], 'body' => $mail['body'], 'values' => $GLOBALS['values'] );
		return is_callable( $GLOBALS['take'] ) ? (bool) call_user_func( $GLOBALS['take'], $user_id ) : (bool) $GLOBALS['take'];
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wpcpm-dates.php';

if ( file_exists( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-mentor-assigned.php' ) ) {
	require_once dirname( __DIR__ ) . '/includes/modules/class-wpcpm-mentor-assigned.php';
}

$fails = 0;
function ck( $label, $got, $want ) {
	global $fails;
	if ( $got === $want ) {
		echo "ok   $label\n";
		return;
	}
	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

if ( ! class_exists( 'WPCPM_Mentor_Assigned' ) ) {
	ck( 'the class exists', false, true );
	exit( 1 );
}

/** A mentee row, as the sync stores it. */
function row( $id, $name = 'Ada Example', array $more = array() ) {
	return array_merge(
		array(
			'record_id'   => $id,
			'name'        => $name,
			'email'       => strtolower( strtok( $name, ' ' ) ) . '@example.org',
			'status'      => 'Credits 150h',
			'is_past'     => false,
			'start'       => '2026-12-02',
			'institution' => 'Example University',
		),
		$more
	);
}

/** A mentor's entry in `$lists`. */
function mentor( $user_id, array $rows ) {
	return array( 'user_id' => $user_id, 'rows' => $rows );
}

/** A clean slate: no option, no mail, the switch on. */
function fresh() {
	$GLOBALS['opts']     = array( 'date_format' => 'j F Y' );
	$GLOBALS['writes']   = array();
	$GLOBALS['mail']     = array();
	$GLOBALS['take']     = true;
	$GLOBALS['throw_at'] = 0;
	$GLOBALS['card']     = 'https://example.org/mentor-report-card/';
	$GLOBALS['setting']  = true;
	$GLOBALS['umeta']    = array();
	$GLOBALS['refuse_write'] = false;
	$GLOBALS['refused']  = array();
}

/** The IDs the option holds for one mentor, sorted. */
function told_of( $mentor ) {
	$opt = get_option( WPCPM_Mentor_Assigned::OPT_TOLD );
	$ids = isset( $opt['told'][ $mentor ] ) ? array_values( $opt['told'][ $mentor ] ) : array();
	sort( $ids );
	return $ids;
}

/** A static reader of the class, or null when it has none (so a missing one fails its check). */
function assigned_call( $method ) {
	return method_exists( 'WPCPM_Mentor_Assigned', $method ) ? call_user_func( array( 'WPCPM_Mentor_Assigned', $method ) ) : null;
}

/** Run, after clearing the recorded mail and writes. */
function go( array $lists, array $options = array() ) {
	$GLOBALS['mail']   = array();
	$GLOBALS['writes'] = array();
	return WPCPM_Mentor_Assigned::run( $lists, $options );
}

/** `$n` mentors, each with one known student, as a seeded field. */
function field( $n, $prefix = 'recM' ) {
	$lists = array();
	for ( $i = 1; $i <= $n; $i++ ) {
		$m           = sprintf( '%s%011d', $prefix, $i );
		$lists[ $m ] = mentor( 100 + $i, array( row( sprintf( 'recS%013d', $i ), 'Ben Example' ) ) );
	}
	return $lists;
}

/** Give the first `$k` mentors of a field one new current student each. */
function with_new( array $lists, $k ) {
	$i = 0;
	foreach ( $lists as $m => $entry ) {
		if ( ++$i > $k ) {
			break;
		}
		$lists[ $m ]['rows'][] = row( 'recN' . substr( $m, 4 ), 'Cy Example' );
	}
	return $lists;
}

ck( 'the constants', array( WPCPM_Mentor_Assigned::OPT_TOLD, WPCPM_Mentor_Assigned::MAX_EMAILS, WPCPM_Mentor_Assigned::BRAKE_MIN ), array( 'wpcpm_mentees_told', 25, 20 ) );

echo "\n=== Review Focus 1: the first run remembers and emails nobody ===\n";
fresh();
$lists = array(
	'recMentorA000001' => mentor( 11, array( row( 'recStudA0000001' ), row( 'recStudP0000001', 'Old Example', array( 'is_past' => true ) ) ) ),
	'recMentorB000001' => mentor( 12, array( row( 'recStudB0000001', 'Bea Example' ) ) ),
	'recMentorC000001' => mentor( 13, array( row( 'recStudC0000001', 'Cal Example' ), row( 'recStudC0000002', 'Cat Example' ) ) ),
);
$r = go( $lists );
ck( 'nothing is sent', count( $GLOBALS['mail'] ), 0 );
ck( 'the answer says it seeded', $r, array( 'seeded' => true, 'told' => 0, 'students' => 0, 'waiting' => 0, 'failed' => 0, 'braked' => 0, 'unsaved' => false ) );
ck( 'mentor A\'s IDs are held, the past row too', told_of( 'recMentorA000001' ), array( 'recStudA0000001', 'recStudP0000001' ) );
ck( 'mentor B\'s', told_of( 'recMentorB000001' ), array( 'recStudB0000001' ) );
ck( 'mentor C\'s', told_of( 'recMentorC000001' ), array( 'recStudC0000001', 'recStudC0000002' ) );
$opt = get_option( WPCPM_Mentor_Assigned::OPT_TOLD );
ck( 'the option is version 1 with a seeded time', array( $opt['v'], is_int( $opt['seeded'] ) && $opt['seeded'] > 0 ), array( 1, true ) );
ck( 'and written with autoload off', $GLOBALS['writes'], array( array( 'wpcpm_mentees_told', false ) ) );

$r = go( $lists );
ck( 'a second run with the same lists sends nothing', count( $GLOBALS['mail'] ), 0 );
ck( 'and is not a seed', $r['seeded'], false );
ck( 'and writes nothing', $GLOBALS['writes'], array() );

echo "\n=== A new student ===\n";
$lists['recMentorB000001']['rows'][] = row( 'recStudB0000002', 'Ada Example' );
$r = go( $lists );
ck( 'one email', count( $GLOBALS['mail'] ), 1 );
ck( 'to mentor B\'s account', $GLOBALS['mail'][0]['to'], 12 );
ck( 'told 1, students 1', array( $r['told'], $r['students'], $r['waiting'], $r['failed'], $r['braked'], $r['seeded'] ), array( 1, 1, 0, 0, 0, false ) );
ck( 'the ID is in the set afterwards', told_of( 'recMentorB000001' ), array( 'recStudB0000001', 'recStudB0000002' ) );
ck( 'the email is the catalog\'s student-assigned-mentor', $GLOBALS['mail'][0]['context'], 'student-assigned-mentor' );
ck( 'the subject counts and names nobody', $GLOBALS['mail'][0]['subject'], '[Example Site] 1 student assigned to you' );
ck( 'the subject holds neither the name nor the address', array( false !== strpos( $GLOBALS['mail'][0]['subject'], 'Ada' ), false !== strpos( $GLOBALS['mail'][0]['subject'], 'example.org' ) ), array( false, false ) );
ck( 'the body holds the line', false !== strpos( $GLOBALS['mail'][0]['body'], 'Ada Example, WordPress Credits Program 150h, Example University, starts 2 December 2026: ada@example.org' ), true );
ck( 'one new row reads 1 student', $GLOBALS['mail'][0]['values']['count'], '1 student' );
ck( 'the link is the Mentor Report Card', $GLOBALS['mail'][0]['values']['dashboard_link'], 'https://example.org/mentor-report-card/' );
ck( 'the site name is the site\'s', $GLOBALS['mail'][0]['values']['site_name'], 'Example Site' );
ck( 'the answer holds numbers and yes-or-no values only', array_map( 'gettype', $r ), array( 'seeded' => 'boolean', 'told' => 'integer', 'students' => 'integer', 'waiting' => 'integer', 'failed' => 'integer', 'braked' => 'integer', 'unsaved' => 'boolean' ) );
$r = go( $lists );
ck( 'a third run sends nothing', count( $GLOBALS['mail'] ), 0 );

echo "\n=== Two new students ===\n";
$lists['recMentorC000001']['rows'][] = row( 'recStudC0000003', 'Dee Example' );
$lists['recMentorC000001']['rows'][] = row( 'recStudC0000004', 'Eve Example' );
$r = go( $lists );
ck( 'one email', count( $GLOBALS['mail'] ), 1 );
ck( 'whose list has two lines', count( explode( "\n", $GLOBALS['mail'][0]['values']['students_list'] ) ), 2 );
ck( 'and the body holds both, one to a line', array( 1 === preg_match( '/^Dee Example, [^\n]*: dee@example\.org$/m', $GLOBALS['mail'][0]['body'] ), 1 === preg_match( '/^Eve Example, [^\n]*: eve@example\.org$/m', $GLOBALS['mail'][0]['body'] ) ), array( true, true ) );
ck( 'the real subject reads 2 students', $GLOBALS['mail'][0]['subject'], '[Example Site] 2 students assigned to you' );
ck( 'count reads 2 students', $GLOBALS['mail'][0]['values']['count'], '2 students' );
ck( 'told 1, students 2', array( $r['told'], $r['students'] ), array( 1, 2 ) );

echo "\n=== One student, two mentors ===\n";
$lists['recMentorA000001']['rows'][] = row( 'recStudShared01', 'Fay Example' );
$lists['recMentorB000001']['rows'][] = row( 'recStudShared01', 'Fay Example' );
$r = go( $lists );
ck( 'each mentor is told once', array_column( $GLOBALS['mail'], 'to' ), array( 11, 12 ) );
$r = go( $lists );
ck( 'and not again', count( $GLOBALS['mail'] ), 0 );

echo "\n=== A student moved from mentor A to mentor B ===\n";
$a_before = told_of( 'recMentorA000001' );
$lists['recMentorA000001']['rows'] = array_values( array_filter( $lists['recMentorA000001']['rows'], function ( $r ) { return 'recStudA0000001' !== $r['record_id']; } ) );
$lists['recMentorB000001']['rows'][] = row( 'recStudA0000001' );
$r = go( $lists );
ck( 'B is told, A is not', array_column( $GLOBALS['mail'], 'to' ), array( 12 ) );
ck( 'and A\'s set is unchanged', told_of( 'recMentorA000001' ), $a_before );

echo "\n=== A past student, and a test institution's ===\n";
$lists['recMentorC000001']['rows'][] = row( 'recStudPast0002', 'Gus Example', array( 'is_past' => true ) );
$r = go( $lists );
ck( 'a new past row sends nothing', count( $GLOBALS['mail'] ), 0 );
ck( 'and is remembered', in_array( 'recStudPast0002', told_of( 'recMentorC000001' ), true ), true );
ck( 'in one write', count( $GLOBALS['writes'] ), 1 );
foreach ( $lists['recMentorC000001']['rows'] as $i => $one ) {
	if ( 'recStudPast0002' === $one['record_id'] ) {
		$lists['recMentorC000001']['rows'][ $i ]['is_past'] = false;
	}
}
$r = go( $lists );
ck( 'that row current later: still nothing', count( $GLOBALS['mail'] ), 0 );
$lists['recMentorC000001']['rows'][] = row( 'recStudTest0001', 'Hal Example', array( 'institution' => 'TEST University' ) );
$r = go( $lists );
ck( 'a test institution\'s student sends nothing', count( $GLOBALS['mail'] ), 0 );
ck( 'and is remembered', in_array( 'recStudTest0001', told_of( 'recMentorC000001' ), true ), true );

echo "\n=== A mentor with no account, and a mentor new to the site ===\n";
$lists['recMentorD000001'] = mentor( 0, array( row( 'recStudD0000001', 'Ida Example' ) ) );
$r = go( $lists );
ck( 'no account: nothing sent', count( $GLOBALS['mail'] ), 0 );
ck( 'and nothing remembered', array_key_exists( 'recMentorD000001', get_option( WPCPM_Mentor_Assigned::OPT_TOLD )['told'] ), false );
ck( 'and nothing written', $GLOBALS['writes'], array() );
$lists['recMentorD000001']['user_id'] = 14;
$r = go( $lists );
ck( 'given an account, told then', array_column( $GLOBALS['mail'], 'to' ), array( 14 ) );
$lists['recMentorE000001'] = mentor( 15, array( row( 'recStudE0000001', 'Jo Example' ), row( 'recStudE0000002', 'Kit Example' ) ) );
$r = go( $lists );
ck( 'a mentor new to the site is told about both students', array( array_column( $GLOBALS['mail'], 'to' ), $r['students'] ), array( array( 15 ), 2 ) );

echo "\n=== Review Focus 2: a list that shrinks and comes back ===\n";
$keep = $lists['recMentorE000001']['rows'];
$lists['recMentorE000001']['rows'] = array();
$r = go( $lists );
ck( 'rows empty: nothing sent', count( $GLOBALS['mail'] ), 0 );
ck( 'and the set keeps the mentor', told_of( 'recMentorE000001' ), array( 'recStudE0000001', 'recStudE0000002' ) );
$lists['recMentorE000001']['rows'] = $keep;
$r = go( $lists );
ck( 'rows back: nothing sent', count( $GLOBALS['mail'] ), 0 );
$gone = $lists['recMentorE000001'];
unset( $lists['recMentorE000001'] );
$r = go( $lists );
ck( 'mentor missing from the run: nothing sent', count( $GLOBALS['mail'] ), 0 );
$lists['recMentorE000001'] = $gone;
$r = go( $lists );
ck( 'mentor back: nothing sent', count( $GLOBALS['mail'] ), 0 );
$lists['recMentorE000001']['user_id'] = 99;
$r = go( $lists );
ck( 'the same mentor record on another account: nothing sent', count( $GLOBALS['mail'] ), 0 );

echo "\n=== A refused send ===\n";
$lists['recMentorE000001']['rows'][] = row( 'recStudE0000003', 'Lu Example' );
$GLOBALS['take'] = false;
$r = go( $lists );
ck( 'failed 1', array( $r['failed'], $r['told'], $r['students'] ), array( 1, 0, 0 ) );
ck( 'the ID is not remembered', in_array( 'recStudE0000003', told_of( 'recMentorE000001' ), true ), false );
$GLOBALS['take'] = true;
$r = go( $lists );
ck( 'the next run sends again', array( count( $GLOBALS['mail'] ), $r['told'] ), array( 1, 1 ) );

echo "\n=== Review Focus 3: the mailer throws on the second email ===\n";
fresh();
$lists = field( 3 );
go( $lists );
$lists = with_new( $lists, 3 );
$GLOBALS['throw_at'] = 2;
$thrown = false;
try {
	go( $lists );
} catch ( RuntimeException $e ) {
	$thrown = true;
}
ck( 'the exception leaves run()', $thrown, true );
ck( 'the first mentor\'s IDs are already stored', told_of( 'recM00000000001' ), array( 'recN00000000001', 'recS0000000000001' ) );
ck( 'the second mentor\'s are not', told_of( 'recM00000000002' ), array( 'recS0000000000002' ) );
$GLOBALS['throw_at'] = 0;
$r = go( $lists );
ck( 'the next run tells the two left', array_column( $GLOBALS['mail'], 'to' ), array( 102, 103 ) );

echo "\n=== Review Focus 4: the switch off ===\n";
fresh();
$lists = field( 3 );
go( $lists );
$lists = with_new( $lists, 2 );
$GLOBALS['setting'] = false;
$r = go( $lists );
ck( 'off: nothing sent', count( $GLOBALS['mail'] ), 0 );
ck( 'and the new IDs are remembered', array( told_of( 'recM00000000001' ), told_of( 'recM00000000002' ) ), array( array( 'recN00000000001', 'recS0000000000001' ), array( 'recN00000000002', 'recS0000000000002' ) ) );
ck( 'in one write', count( $GLOBALS['writes'] ), 1 );
ck( 'the answer counts nothing', array( $r['told'], $r['students'], $r['waiting'], $r['failed'], $r['braked'] ), array( 0, 0, 0, 0, 0 ) );
$GLOBALS['setting'] = true;
$r = go( $lists );
ck( 'on in the next run: nothing sent for them', count( $GLOBALS['mail'] ), 0 );

echo "\n=== The ceiling ===\n";
fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 27 );
$r = go( $lists );
ck( '27 mentors to tell: 25 emails', count( $GLOBALS['mail'] ), 25 );
ck( 'waiting 2', array( $r['told'], $r['waiting'], $r['braked'] ), array( 25, 2, 0 ) );
ck( 'told in record ID order', array( $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][24]['to'] ), array( 101, 125 ) );
ck( 'the two waiting are not remembered', array( told_of( 'recM00000000026' ), told_of( 'recM00000000027' ) ), array( array( 'recS0000000000026' ), array( 'recS0000000000027' ) ) );
ck( 'one write per email', count( $GLOBALS['writes'] ), 25 );
$r = go( $lists );
ck( 'the next run sends the 2', array( array_column( $GLOBALS['mail'], 'to' ), $r['waiting'] ), array( array( 126, 127 ), 0 ) );

fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 27 );
$GLOBALS['take'] = function ( $user_id ) { return 101 !== $user_id; };
$r = go( $lists );
ck( 'a refused email does not use up the ceiling: 25 attempts, 24 told, 1 failed', array( count( $GLOBALS['mail'] ), $r['told'], $r['failed'], $r['waiting'] ), array( 25, 24, 1, 2 ) );

fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 27 );
$GLOBALS['take'] = false;
$r = go( $lists );
ck( 'a broken mailer stops after 25 attempts', array( count( $GLOBALS['mail'] ), $r['told'], $r['failed'], $r['waiting'] ), array( 25, 0, 25, 2 ) );
ck( 'and writes once, at the end: only the refused list, nothing told', array( count( $GLOBALS['writes'] ), count( get_option( 'wpcpm_mentees_told' )['failed'] ), told_of( 'recM00000000001' ) ), array( 1, 25, array( 'recS0000000000001' ) ) );

echo "\n=== The brake ===\n";
fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 31 );
$r = go( $lists );
ck( '31 of 60 to tell: nothing sent', count( $GLOBALS['mail'] ), 0 );
ck( 'braked 31', array( $r['braked'], $r['told'], $r['waiting'] ), array( 31, 0, 0 ) );
ck( 'nothing remembered', told_of( 'recM00000000001' ), array( 'recS0000000000001' ) );
ck( 'nothing written', $GLOBALS['writes'], array() );
$r = go( $lists, array( 'by_hand' => true ) );
ck( 'by hand: 25 sent, waiting 6', array( count( $GLOBALS['mail'] ), $r['told'], $r['waiting'], $r['braked'] ), array( 25, 25, 6, 0 ) );

fresh();
$lists = field( 30 );
go( $lists );
$lists = with_new( $lists, 19 );
$r = go( $lists );
ck( '19 of 30 to tell: sent, under the brake\'s minimum', array( count( $GLOBALS['mail'] ), $r['braked'] ), array( 19, 0 ) );

fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 31 );
$lists['recM00000000001']['rows'][] = row( 'recPastNew00001', 'Max Example', array( 'is_past' => true ) );
$r = go( $lists );
ck( 'under the brake a new past row is still remembered', array( $r['braked'], in_array( 'recPastNew00001', told_of( 'recM00000000001' ), true ), in_array( 'recN00000000001', told_of( 'recM00000000001' ), true ) ), array( 31, true, false ) );

echo "\n=== alive ===\n";
fresh();
$lists = field( 10 );
go( $lists );
$lists = with_new( $lists, 3 );
$asked = 0;
$r = go( $lists, array( 'alive' => function () use ( &$asked ) { ++$asked; return false; } ) );
ck( 'alive false before the second email: one sent, the rest waiting', array( count( $GLOBALS['mail'] ), $r['told'], $r['waiting'] ), array( 1, 1, 2 ) );
ck( 'and alive was not asked before the first', $asked, 1 );


echo "\n=== A refused write stops the sending ===\n";
fresh();
$lists = field( 3 );
go( $lists );
$lists = with_new( $lists, 3 );
$before = get_option( WPCPM_Mentor_Assigned::OPT_TOLD );
$GLOBALS['refuse_write'] = true;
$r = go( $lists );
ck( 'one email, and the two others wait', array( count( $GLOBALS['mail'] ), $r['told'], $r['students'], $r['waiting'], $r['failed'], $r['braked'] ), array( 1, 1, 1, 2, 0, 0 ) );
ck( 'one write was tried, and nothing more', $GLOBALS['refused'], array( 'wpcpm_mentees_told' ) );
ck( 'and the stored set is as it was', get_option( WPCPM_Mentor_Assigned::OPT_TOLD ), $before );

echo "\n=== The first run reads the stored lists too ===\n";
fresh();
$GLOBALS['umeta'][21] = array( 'wpcpm_mentor_record_id' => ' recMentorZ000001 ', 'wpcpm_mentees' => array( row( 'recStudZ0000001', 'Zed Example' ), row( '', 'Nob Example' ), 'junk' ) );
$GLOBALS['umeta'][22] = array( 'wpcpm_mentees' => array( row( 'recStudY0000001', 'Yan Example' ) ) );
$GLOBALS['umeta'][23] = array( 'wpcpm_mentor_record_id' => 'recMentorX000001', 'wpcpm_mentees' => 'not a list' );
$lists = field( 2 );
$r = go( $lists );
ck( 'seeded, nothing sent', array( $r['seeded'], count( $GLOBALS['mail'] ) ), array( true, 0 ) );
ck( 'a mentor this read missed is seeded from their account\'s stored list', told_of( 'recMentorZ000001' ), array( 'recStudZ0000001' ) );
ck( 'an account with no mentor record ID is skipped', array_key_exists( '', get_option( WPCPM_Mentor_Assigned::OPT_TOLD )['told'] ) || in_array( 'recStudY0000001', array_merge( array(), ...array_values( get_option( WPCPM_Mentor_Assigned::OPT_TOLD )['told'] ) ), true ), false );
ck( 'and this run\'s rows are seeded beside it', told_of( 'recM00000000001' ), array( 'recS0000000000001' ) );
$lists['recMentorZ000001'] = mentor( 21, array( row( 'recStudZ0000001', 'Zed Example' ) ) );
$r = go( $lists );
ck( 'the next full run tells that mentor nothing', count( $GLOBALS['mail'] ), 0 );

fresh();
$r = go( array( 'recMentorQ000001' => mentor( 0, array( row( 'recStudQ0000001' ) ) ), 'recMentorR000001' => mentor( 31, array() ) ) );
ck( 'a first run with nothing to seed answers no seed and sends nothing', array( $r['seeded'], count( $GLOBALS['mail'] ) ), array( false, 0 ) );
ck( 'and writes nothing: the option stays absent', array( $GLOBALS['writes'], array_key_exists( 'wpcpm_mentees_told', $GLOBALS['opts'] ) ), array( array(), false ) );
$r = go( array() );
ck( 'nor does an empty read', array( $r['seeded'], $GLOBALS['writes'] ), array( false, array() ) );
$r = go( with_new( field( 2 ), 2 ) );
ck( 'the next run with data seeds silently', array( $r['seeded'], count( $GLOBALS['mail'] ), told_of( 'recM00000000001' ) ), array( true, 0, array( 'recN00000000001', 'recS0000000000001' ) ) );

echo "\n=== The stored lists, read before the sync replaces them ===\n";
fresh();
$GLOBALS['umeta'][21] = array( 'wpcpm_mentor_record_id' => ' recMentorZ000001 ', 'wpcpm_mentees' => array( row( ' recStudZ0000001 ', 'Zed Example' ), row( '', 'Nob Example' ), 'junk' ) );
$GLOBALS['umeta'][22] = array( 'wpcpm_mentees' => array( row( 'recStudY0000001', 'Yan Example' ) ) );
$GLOBALS['umeta'][23] = array( 'wpcpm_mentor_record_id' => 'recMentorX000001', 'wpcpm_mentees' => 'not a list' );
$GLOBALS['umeta'][24] = array( 'wpcpm_mentor_record_id' => 'recMentorW000001', 'wpcpm_mentees' => array( row( 'recStudW0000001' ), row( 'recStudW0000002' ) ) );
ck( 'stored_lists(): record ID => report IDs only, trimmed, an account with no record ID or no ID skipped',
	assigned_call( 'stored_lists' ),
	array( 'recMentorZ000001' => array( 'recStudZ0000001' ), 'recMentorW000001' => array( 'recStudW0000001', 'recStudW0000002' ) ) );
ck( 'has_baseline() is false with no set', assigned_call( 'has_baseline' ), false );
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 9, 'seeded' => 1, 'told' => array() );
ck( 'and with a set of another version', assigned_call( 'has_baseline' ), false );
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 1, 'seeded' => 1, 'told' => 'junk' );
ck( 'and with a set whose told is not a list', assigned_call( 'has_baseline' ), false );
unset( $GLOBALS['opts']['wpcpm_mentees_told'] );

fresh();
$r = go( field( 2 ), array( 'known' => array( ' recMentorK000001 ' => array( ' recStudK0000001 ', '', 7 ), 'recM00000000001' => array( 'recStudK0000009' ), '' => array( 'recStudK0000008' ), 'recMentorJ000001' => 'junk' ) ) );
ck( 'run() with known: seeded, nothing sent', array( $r['seeded'], count( $GLOBALS['mail'] ) ), array( true, 0 ) );
ck( 'a mentor known before the sync replaced their list is seeded', told_of( 'recMentorK000001' ), array( '7', 'recStudK0000001' ) );
ck( 'known is unioned with this run\'s rows', told_of( 'recM00000000001' ), array( 'recS0000000000001', 'recStudK0000009' ) );
ck( 'an entry with no record ID, or not a list, adds nothing', array( array_key_exists( 'recMentorJ000001', get_option( WPCPM_Mentor_Assigned::OPT_TOLD )['told'] ), in_array( 'recStudK0000008', array_merge( array(), ...array_values( get_option( WPCPM_Mentor_Assigned::OPT_TOLD )['told'] ) ), true ) ), array( false, false ) );
ck( 'has_baseline() is true once the set is written', assigned_call( 'has_baseline' ), true );
$lists = field( 2 );
$lists['recMentorK000001'] = mentor( 41, array( row( 'recStudK0000001', 'Kit Example' ) ) );
$r = go( $lists );
ck( 'the next run tells that mentor nothing', count( $GLOBALS['mail'] ), 0 );
$r = go( field( 2 ), array( 'known' => array( 'recMentorL000001' => array( 'recStudL0000001' ) ) ) );
ck( 'known is ignored once a baseline exists', array( count( $GLOBALS['mail'] ), told_of( 'recMentorL000001' ), $GLOBALS['writes'] ), array( 0, array(), array() ) );

fresh();
$r = go( array(), array( 'known' => array( 'recMentorK000001' => array( 'recStudK0000001' ) ) ) );
ck( 'a first run with only known seeds it', array( $r['seeded'], told_of( 'recMentorK000001' ) ), array( true, array( 'recStudK0000001' ) ) );

echo "\n=== The quiet way past the brake: the switch off ===\n";
fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 31 );
$GLOBALS['setting'] = false;
$r = go( $lists );
ck( 'off: the brake is not applied, braked 0, nothing sent', array( $r['braked'], count( $GLOBALS['mail'] ), $r['told'], $r['waiting'] ), array( 0, 0, 0, 0 ) );
ck( 'every new ID of every mentor is remembered', array( told_of( 'recM00000000001' ), told_of( 'recM00000000031' ) ), array( array( 'recN00000000001', 'recS0000000000001' ), array( 'recN00000000031', 'recS0000000000031' ) ) );
ck( 'in one write', count( $GLOBALS['writes'] ), 1 );
$GLOBALS['setting'] = true;
$r = go( $lists );
ck( 'on again: the next run tells nobody about them', array( count( $GLOBALS['mail'] ), $r['braked'] ), array( 0, 0 ) );

echo "\n=== A damaged option ===\n";
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 1, 'seeded' => 5, 'told' => 'damaged' );
$r = go( with_new( field( 2 ), 2 ) );
ck( 'told not an array: seeded again, nothing sent', array( $r['seeded'], count( $GLOBALS['mail'] ), told_of( 'recM00000000002' ) ), array( true, 0, array( 'recN00000000002', 'recS0000000000002' ) ) );

fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 1, 'seeded' => 5, 'told' => array( 'recM00000000001' => 'damaged', ' recM00000000002 ' => array( 'recS0000000000002' ) ) );
$r = go( with_new( field( 2 ), 2 ) );
ck( 'one mentor\'s entry not a list: their list is taken in silently, the other mentor is told', array( array_column( $GLOBALS['mail'], 'to' ), $r['told'] ), array( array( 102 ), 1 ) );
ck( 'every row of that mentor is remembered', told_of( 'recM00000000001' ), array( 'recN00000000001', 'recS0000000000001' ) );
ck( 'a stored mentor ID is read trimmed', told_of( 'recM00000000002' ), array( 'recN00000000002', 'recS0000000000002' ) );

fresh();
$lists = field( 2 );
go( $lists );
$r = go( array( ' recM00000000001 ' => mentor( 101, array( row( 'recS0000000000001', 'Ben Example' ), row( 'recNew000000001', 'Ole Example' ) ) ) ) );
ck( 'a mentor ID from the lists is read trimmed', array( count( $GLOBALS['mail'] ), told_of( 'recM00000000001' ), array_key_exists( ' recM00000000001 ', get_option( 'wpcpm_mentees_told' )['told'] ) ), array( 1, array( 'recNew000000001', 'recS0000000000001' ), false ) );

echo "\n=== Mentors whose email was refused go last at the next sync ===\n";

/** The mentor record IDs the option holds as refused, sorted. */
function failed_of() {
	$opt = get_option( WPCPM_Mentor_Assigned::OPT_TOLD );
	$ids = isset( $opt['failed'] ) && is_array( $opt['failed'] ) ? array_values( $opt['failed'] ) : array();
	sort( $ids );
	return $ids;
}

/** The mentor record IDs of a field, from `$from` to `$to`. */
function mentor_ids( $from, $to ) {
	$ids = array();
	for ( $i = $from; $i <= $to; $i++ ) {
		$ids[] = sprintf( 'recM%011d', $i );
	}
	return $ids;
}

// (a) 26 to tell, and the mailer refuses the first 25 by record ID.
fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 26 );
$GLOBALS['take'] = function ( $user_id ) { return $user_id > 125; };
$r = go( $lists );
ck( '(a) run 1: the first 25 by record ID are attempted and refused, mentor 26 waits', array( array_column( $GLOBALS['mail'], 'to' ), $r['told'], $r['failed'], $r['waiting'] ), array( range( 101, 125 ), 0, 25, 1 ) );
ck( '(a) run 1: failed holds those 25', failed_of(), mentor_ids( 1, 25 ) );
ck( '(a) run 1: mentor 26 is not remembered', told_of( 'recM00000000026' ), array( 'recS0000000000026' ) );
$r = go( $lists );
ck( '(a) run 2: mentor 26 first, and emailed, then 24 of the failed', array( array_column( $GLOBALS['mail'], 'to' ), $r['told'], $r['failed'], $r['waiting'] ), array( array_merge( array( 126 ), range( 101, 124 ) ), 1, 24, 1 ) );
ck( '(a) run 2: mentor 26 is remembered', told_of( 'recM00000000026' ), array( 'recN00000000026', 'recS0000000000026' ) );
ck( '(a) run 2: failed holds the 24 refused and the one not attempted', failed_of(), mentor_ids( 1, 25 ) );
// (e) in the same run.
$to = array_column( $GLOBALS['mail'], 'to' );
ck( '(e) run 2: nobody attempted twice, and no more than the ceiling', array( count( $to ) === count( array_unique( $to ) ), count( $to ) <= WPCPM_Mentor_Assigned::MAX_EMAILS ), array( true, true ) );

// (b) A mentor in failed whose email is then accepted leaves failed.
fresh();
$lists = field( 5 );
go( $lists );
$lists = with_new( $lists, 2 );
$GLOBALS['take'] = function ( $user_id ) { return 101 !== $user_id; };
go( $lists );
ck( '(b) the refused mentor is in failed', failed_of(), array( 'recM00000000001' ) );
$GLOBALS['take'] = true;
$r = go( $lists );
ck( '(b) emailed at the next run, and gone from failed', array( array_column( $GLOBALS['mail'], 'to' ), $r['told'], failed_of() ), array( array( 101 ), 1, array() ) );

// (c) A mentor in failed who is no longer pending leaves failed.
fresh();
$lists = field( 5 );
go( $lists );
$lists = with_new( $lists, 2 );
$GLOBALS['take'] = false;
go( $lists );
ck( '(c) both refused mentors are in failed', failed_of(), array( 'recM00000000001', 'recM00000000002' ) );
$lists['recM00000000001']['rows'] = array( row( 'recS0000000000001', 'Ben Example' ) );
$r = go( $lists );
ck( '(c) the mentor with nobody new leaves failed; the one refused again stays', array( array_column( $GLOBALS['mail'], 'to' ), failed_of() ), array( array( 102 ), array( 'recM00000000002' ) ) );

// (d) A set stored without failed behaves as before.
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array(
	'v'      => 1,
	'seeded' => 5,
	'told'   => array(
		'recM00000000001' => array( 'recS0000000000001' ),
		'recM00000000002' => array( 'recS0000000000002' ),
		'recM00000000003' => array( 'recS0000000000003' ),
	),
);
$r = go( with_new( field( 3 ), 3 ) );
ck( '(d) no failed key: told in record ID order, as before', array( $r['seeded'], array_column( $GLOBALS['mail'], 'to' ), $r['told'], $r['failed'], failed_of() ), array( false, array( 101, 102, 103 ), 3, 0, array() ) );

// (e) A stored failed list with repeats, padding and junk attempts nobody twice.
fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 28 );
$stored           = get_option( WPCPM_Mentor_Assigned::OPT_TOLD );
$stored['failed'] = array_merge( mentor_ids( 1, 28 ), array( ' recM00000000001 ', 'recM00000000002', null, array(), 'recNobody000001' ) );
$GLOBALS['opts']['wpcpm_mentees_told'] = $stored;
$GLOBALS['take'] = false;
$r  = go( $lists );
$to = array_column( $GLOBALS['mail'], 'to' );
ck( '(e) a failed list with repeats and junk: 25 attempts, each mentor once', array( count( $to ), count( array_unique( $to ) ), $to ), array( 25, 25, range( 101, 125 ) ) );
ck( '(e) and failed is read clean: the 28 still pending, once each', failed_of(), mentor_ids( 1, 28 ) );

echo "\n=== Damage is taken in silently ===\n";

// (f) A set whose told is empty is not a set.
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 1, 'seeded' => 5, 'told' => array() );
ck( '(f) an empty told is no baseline', assigned_call( 'has_baseline' ), false );
$r = go( field( 5 ) );
ck( '(f) an empty told: seeded again, nothing sent', array( $r['seeded'], count( $GLOBALS['mail'] ) ), array( true, 0 ) );
ck( '(f) and the set holds the current students', array( told_of( 'recM00000000001' ), told_of( 'recM00000000005' ) ), array( array( 'recS0000000000001' ), array( 'recS0000000000005' ) ) );

// (g) An entry that is a list of nothing usable is damaged.
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array(
	'v'      => 1,
	'seeded' => 5,
	'told'   => array(
		'recM00000000001' => array( null, array() ),
		'recM00000000002' => array( 'recS0000000000002' ),
		'recM00000000003' => array( 'recS0000000000003' ),
	),
);
$lists = field( 3 );
$lists['recM00000000001']['rows'] = array( row( 'recS0000000000001', 'Ben Example' ), row( 'recG00000000001', 'Gus Example' ), row( 'recG00000000002', 'Gia Example' ) );
$lists['recM00000000002']['rows'][] = row( 'recN00000000002', 'Cy Example' );
$r = go( $lists );
ck( '(g) an entry of only junk: no mail to that mentor, another mentor\'s new student still told', array( array_column( $GLOBALS['mail'], 'to' ), $r['told'] ), array( array( 102 ), 1 ) );
ck( '(g) the three IDs are remembered', told_of( 'recM00000000001' ), array( 'recG00000000001', 'recG00000000002', 'recS0000000000001' ) );

// (h) An entry that is a genuinely empty list: a known mentor with no students.
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array(
	'v'      => 1,
	'seeded' => 5,
	'told'   => array(
		'recM00000000001' => array(),
		'recM00000000002' => array( 'recS0000000000002' ),
	),
);
$r = go( field( 2 ) );
ck( '(h) an empty entry and a first student: emailed', array( array_column( $GLOBALS['mail'], 'to' ), $r['students'] ), array( array( 101 ), 1 ) );

// (i) An entry with some usable IDs and some junk keeps its usable IDs.
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array(
	'v'      => 1,
	'seeded' => 5,
	'told'   => array(
		'recM00000000001' => array( 'recA', null ),
		'recM00000000002' => array( 'recS0000000000002' ),
	),
);
$lists = field( 2 );
$lists['recM00000000001']['rows'] = array( row( 'recA', 'Ann Example' ), row( 'recB', 'Bob Example' ) );
$r = go( $lists );
$body = isset( $GLOBALS['mail'][0]['body'] ) ? $GLOBALS['mail'][0]['body'] : '';
ck( '(i) some junk beside a usable ID: only the new student is announced', array( array_column( $GLOBALS['mail'], 'to' ), $r['students'], false !== strpos( $body, 'Bob Example' ), false !== strpos( $body, 'Ann Example' ) ), array( array( 101 ), 1, true, false ) );

echo "\n=== A set that could not be saved is said, and tried again ===\n";

// (j) The first run, with the write refused.
fresh();
$GLOBALS['refuse_write'] = true;
$r = go( field( 3 ) );
ck( '(j) a refused seed: not seeded, unsaved, no mail, no set stored', array( $r['seeded'], $r['unsaved'] ?? null, count( $GLOBALS['mail'] ), array_key_exists( 'wpcpm_mentees_told', $GLOBALS['opts'] ) ), array( false, true, 0, false ) );
$GLOBALS['refuse_write'] = false;
$r = go( field( 3 ) );
ck( '(j) the next run with writes working seeds', array( $r['seeded'], $r['unsaved'] ?? null, count( $GLOBALS['mail'] ), told_of( 'recM00000000003' ) ), array( true, false, 0, array( 'recS0000000000003' ) ) );

// (k) The switch off, one new student, the write refused.
fresh();
$lists = field( 3 );
go( $lists );
$lists = with_new( $lists, 1 );
$GLOBALS['setting']      = false;
$GLOBALS['refuse_write'] = true;
$r = go( $lists );
ck( '(k) switch off and the write refused: unsaved, no mail', array( $r['unsaved'] ?? null, count( $GLOBALS['mail'] ) ), array( true, 0 ) );
$GLOBALS['refuse_write'] = false;
$r = go( $lists );
ck( '(k) the next run, still off, remembers the student', array( $r['unsaved'] ?? null, told_of( 'recM00000000001' ) ), array( false, array( 'recN00000000001', 'recS0000000000001' ) ) );
$GLOBALS['setting'] = true;
go( $lists );
ck( '(k) and a run with the switch on sends nothing', count( $GLOBALS['mail'] ), 0 );

// The brake's write refused.
fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 31 );
$lists['recM00000000001']['rows'][] = row( 'recPastNew00001', 'Max Example', array( 'is_past' => true ) );
$GLOBALS['refuse_write'] = true;
$r = go( $lists );
ck( 'under the brake a refused write is unsaved too, and nothing is sent', array( $r['braked'], $r['unsaved'] ?? null, count( $GLOBALS['mail'] ) ), array( 31, true, 0 ) );

// (l) The sending path.
fresh();
$lists = field( 3 );
go( $lists );
$GLOBALS['refuse_write'] = true;
$r = go( with_new( $lists, 3 ) );
ck( '(l) a refused write after an accepted email: unsaved, and the sending stops', array( $r['unsaved'] ?? null, count( $GLOBALS['mail'] ), $r['waiting'] ), array( true, 1, 2 ) );
fresh();
$lists = field( 3 );
go( $lists );
$GLOBALS['take']         = false;
$GLOBALS['refuse_write'] = true;
$r = go( with_new( $lists, 1 ) );
ck( '(l) only the refused list\'s write refused at the end: not unsaved', array( $r['unsaved'] ?? null, $r['failed'], $GLOBALS['refused'] ), array( false, 1, array( 'wpcpm_mentees_told' ) ) );
fresh();
$lists = field( 3 );
go( $lists );
$r = go( with_new( $lists, 2 ) );
ck( '(l) writes that work: not unsaved', array( $r['unsaved'] ?? null, $r['told'] ), array( false, 2 ) );

echo "\n=== The ceiling holds across a sync that runs its last step again ===\n";

/** 60 mentors seeded, each with one new student. */
function sixty_new() {
	$lists = field( 60 );
	go( $lists );
	return with_new( $lists, 60 );
}

/** Run, and say whether the mailer threw out of it. */
function go_catching( array $lists, array $options ) {
	try {
		go( $lists, $options );
	} catch ( RuntimeException $e ) {
		return true;
	}
	return false;
}

// (n) 24 accepted, then the mailer throws; the same sync's last step runs again.
fresh();
$lists = sixty_new();
$GLOBALS['throw_at'] = 25;
$thrown = go_catching( $lists, array( 'by_hand' => true, 'run' => 1000 ) );
ck( '(n) 24 accepted, then the mailer throws', array( $thrown, count( $GLOBALS['mail'] ) ), array( true, 24 ) );
$GLOBALS['throw_at'] = 0;
$r = go( $lists, array( 'by_hand' => true, 'run' => 1000 ) );
ck( '(n) the same run again sends 1 more: 25 in all', array( count( $GLOBALS['mail'] ), $r['told'] ), array( 1, 1 ) );
$stored = get_option( WPCPM_Mentor_Assigned::OPT_TOLD );
ck( '(n) the set keeps the run and what it sent', isset( $stored['run'] ) ? $stored['run'] : null, array( 'id' => 1000, 'sent' => 25 ) );
$r = go( $lists, array( 'by_hand' => true, 'run' => 1000 ) );
ck( '(n) and a third time sends nothing', array( count( $GLOBALS['mail'] ), $r['told'] ), array( 0, 0 ) );

// (o) Another run starts a fresh budget.
$r = go( $lists, array( 'by_hand' => true, 'run' => 2000 ) );
ck( '(o) another run starts a fresh budget of 25', array( count( $GLOBALS['mail'] ), $r['told'] ), array( 25, 25 ) );
ck( '(o) under its own id', get_option( WPCPM_Mentor_Assigned::OPT_TOLD )['run'] ?? null, array( 'id' => 2000, 'sent' => 25 ) );

// (p) Without the option, as today.
fresh();
$lists = sixty_new();
$a = go( $lists, array( 'by_hand' => true ) );
$n = count( $GLOBALS['mail'] );
$b = go( $lists, array( 'by_hand' => true ) );
ck( '(p) no run given: two calls send 25 and 25', array( $n, count( $GLOBALS['mail'] ), $a['told'], $b['told'] ), array( 25, 25, 25, 25 ) );

echo "\n=== The last write of a run, and the count a run answers ===\n";

// (t) Rows remembered silently, and the run's one write refused: it is said.
fresh();
$lists = field( 3 );
go( $lists );
$lists['recM00000000001']['rows'][] = row( 'recPastNew00002', 'Max Example', array( 'is_past' => true ) );
$GLOBALS['refuse_write'] = true;
$r = go( $lists );
ck( '(t) a past row to remember and the write refused: unsaved, no mail', array( $r['unsaved'] ?? null, count( $GLOBALS['mail'] ) ), array( true, 0 ) );
$GLOBALS['refuse_write'] = false;
$r = go( $lists );
ck( '(t) the next run remembers it and says nothing', array( $r['unsaved'] ?? null, in_array( 'recPastNew00002', (array) told_of( 'recM00000000001' ), true ) ), array( false, true ) );

// (u) The answer counts every email accepted under the run.
fresh();
$lists = sixty_new();
$GLOBALS['throw_at'] = 25;
go_catching( $lists, array( 'by_hand' => true, 'run' => 1000 ) );
$GLOBALS['throw_at'] = 0;
$r = go( $lists, array( 'by_hand' => true, 'run' => 1000 ) );
ck( '(u) the step run again answers this call\'s 1 and the run\'s 25', array( $r['told'], $r['run_sent'] ?? null ), array( 1, 25 ) );
$r = go( $lists, array( 'by_hand' => true ) );
ck( '(u) no run given: the answer holds no count of a run', array_key_exists( 'run_sent', $r ), false );

// (v) Refused mentors take turns: more of them than one run tries, and then one's email works again.
fresh();
$lists = field( 60 );
go( $lists );
$lists = with_new( $lists, 26 );
$GLOBALS['take'] = false;
go( $lists ); // Run 1: mentors 1 to 25 tried and refused, mentor 26 waits.
go( $lists ); // Run 2: mentor 26 first and refused, then 24 of the others; mentor 25 is not tried.
$GLOBALS['take'] = function ( $user_id ) { return 126 === $user_id; };
$r = go( $lists );
ck( '(v) run 3: the one not tried last time goes first, then the one refused longest ago', array_slice( array_column( $GLOBALS['mail'], 'to' ), 0, 2 ), array( 125, 126 ) );
ck( '(v) run 3: the mentor whose email works again is emailed', array( $r['told'], in_array( 'recN00000000026', (array) told_of( 'recM00000000026' ), true ) ), array( 1, true ) );
$to = array_column( $GLOBALS['mail'], 'to' );
ck( '(v) run 3: nobody tried twice, and no more than the ceiling', array( count( $to ) === count( array_unique( $to ) ), count( $to ) ), array( true, 25 ) );

// (w) The switch turned off before an interrupted run's step runs again: its count is still answered.
fresh();
$lists = sixty_new();
$GLOBALS['throw_at'] = 3;
go_catching( $lists, array( 'by_hand' => true, 'run' => 1000 ) );
$GLOBALS['throw_at'] = 0;
$GLOBALS['setting']  = false;
$r = go( $lists, array( 'by_hand' => true, 'run' => 1000 ) );
ck( '(w) switched off before the step runs again: nothing sent, and the run still answers its 2', array( count( $GLOBALS['mail'] ), $r['run_sent'] ?? null ), array( 0, 2 ) );

echo "\n=== A set with no usable entry is not a set ===\n";

// (q) Only a blank mentor key.
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 1, 'seeded' => 5, 'told' => array( ' ' => array( 'recOld' ) ) );
ck( '(q) a blank mentor key alone is no baseline', assigned_call( 'has_baseline' ), false );
$r = go( field( 5 ) );
ck( '(q) seeded again, no mail', array( $r['seeded'], count( $GLOBALS['mail'] ), told_of( 'recM00000000005' ) ), array( true, 0, array( 'recS0000000000005' ) ) );

// (r) Only damaged entries.
foreach ( array( false, 'junk', array( null ), array() ) as $junk ) {
	fresh();
	$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 1, 'seeded' => 5, 'told' => array( 'recA' => $junk ) );
	$base = assigned_call( 'has_baseline' );
	$r    = go( field( 3 ) );
	ck( '(r) an entry of ' . str_replace( "\n", '', var_export( $junk, true ) ) . ' alone: no baseline, seeded, no mail', array( $base, $r['seeded'], count( $GLOBALS['mail'] ) ), array( false, true, 0 ) );
}

// (s) One damaged entry among good ones is still a set.
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 1, 'seeded' => 5, 'told' => array( 'recM00000000001' => 'junk', 'recM00000000002' => array( 'recS0000000000002' ) ) );
ck( '(s) one damaged entry among good ones: still a baseline', assigned_call( 'has_baseline' ), true );
$r = go( with_new( field( 2 ), 2 ) );
ck( '(s) not seeded; the damaged mentor is taken in, the other told', array( $r['seeded'], array_column( $GLOBALS['mail'], 'to' ) ), array( false, array( 102 ) ) );

echo "\n=== Boundaries ===\n";
fresh();
$lists = field( 40 );
go( $lists );
$r = go( with_new( $lists, 20 ) );
ck( '20 to tell of 40 with students: sent', array( count( $GLOBALS['mail'] ), $r['braked'] ), array( 20, 0 ) );
fresh();
$lists = field( 39 );
go( $lists );
$r = go( with_new( $lists, 20 ) );
ck( '20 of 39: held', array( count( $GLOBALS['mail'] ), $r['braked'] ), array( 0, 20 ) );
fresh();
$lists = field( 60 );
go( $lists );
$r = go( with_new( $lists, 25 ) );
ck( 'exactly 25 to tell: 25 sent, none waiting', array( count( $GLOBALS['mail'] ), $r['told'], $r['waiting'] ), array( 25, 25, 0 ) );

foreach ( array( array( '1', 1 ), array( 1, 1 ), array( '', 0 ), array( null, 0 ) ) as $case ) {
	fresh();
	$lists = field( 2 );
	go( $lists );
	$GLOBALS['setting'] = $case[0];
	go( with_new( $lists, 1 ) );
	ck( 'the setting read as ' . var_export( $case[0], true ), count( $GLOBALS['mail'] ), $case[1] );
}

fresh();
$lists = field( 3 );
go( $lists );
$asked = 0;
$r = go( with_new( $lists, 1 ), array( 'alive' => function () use ( &$asked ) { ++$asked; return true; } ) );
ck( 'a single mentor to tell: alive is never asked', array( count( $GLOBALS['mail'] ), $asked ), array( 1, 0 ) );

fresh();
$lists = field( 5 );
go( $lists );
$r = go( with_new( $lists, 3 ), array( 'alive' => 'not a function' ) );
ck( 'alive given but not callable reads false: one sent, the rest wait', array( count( $GLOBALS['mail'] ), $r['told'], $r['waiting'] ), array( 1, 1, 2 ) );

echo "\n=== The link with no Mentor Report Card ===\n";
fresh();
$lists = field( 2 );
go( $lists );
$lists = with_new( $lists, 1 );
$GLOBALS['card'] = '';
go( $lists );
ck( 'the home address', $GLOBALS['mail'][0]['values']['dashboard_link'], 'https://example.org/' );

echo "\n=== Another version of the option ===\n";
fresh();
$GLOBALS['opts']['wpcpm_mentees_told'] = array( 'v' => 2, 'seeded' => 1, 'told' => array() );
$r = go( with_new( field( 2 ), 2 ) );
ck( 'is treated as none: seeded, nothing sent', array( $r['seeded'], count( $GLOBALS['mail'] ) ), array( true, 0 ) );
ck( 'and replaced by version 1', get_option( 'wpcpm_mentees_told' )['v'], 1 );

echo "\n=== Rows with no record ID ===\n";
fresh();
$lists = field( 2 );
go( $lists );
$lists['recM00000000001']['rows'][] = row( '', 'No Example' );
$lists['recM00000000001']['rows'][] = array( 'name' => 'Nil Example', 'is_past' => false );
$r = go( $lists );
ck( 'are ignored', array( count( $GLOBALS['mail'] ), $GLOBALS['writes'] ), array( 0, array() ) );

echo "\n=== Review Focus 5: one student's line ===\n";
$GLOBALS['now'] = gmmktime( 12, 0, 0, 11, 13, 2026 );
ck( 'a full row', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example' ) ), 'Ada Example, WordPress Credits Program 150h, Example University, starts 2 December 2026: ada@example.org' );
ck( 'no institution and no start date', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'institution' => '', 'start' => '' ) ) ), 'Ada Example, WordPress Credits Program 150h: ada@example.org' );
ck( 'no institution', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'institution' => '  ' ) ) ), 'Ada Example, WordPress Credits Program 150h, starts 2 December 2026: ada@example.org' );
ck( 'a name with a line break is one line', WPCPM_Mentor_Assigned::line( row( 'recX', "Ada\r\nExample", array( 'email' => 'ada@example.org' ) ) ), 'Ada Example, WordPress Credits Program 150h, Example University, starts 2 December 2026: ada@example.org' );
ck( 'angle brackets kept as typed', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada <Example>', array( 'email' => 'ada@example.org' ) ) ), 'Ada <Example>, WordPress Credits Program 150h, Example University, starts 2 December 2026: ada@example.org' );
ck( 'an empty name reads A student', WPCPM_Mentor_Assigned::line( row( 'recX', '  ', array( 'email' => 'ada@example.org' ) ) ), 'A student, WordPress Credits Program 150h, Example University, starts 2 December 2026: ada@example.org' );
ck( 'an institution still an Airtable record ID is left out', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'institution' => 'recSEED0000000009' ) ) ), 'Ada Example, WordPress Credits Program 150h, starts 2 December 2026: ada@example.org' );
ck( 'an unreadable start date is left out', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'start' => 'soon' ) ) ), 'Ada Example, WordPress Credits Program 150h, Example University: ada@example.org' );

ck( 'a start date before today reads started', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'start' => '2026-09-02' ) ) ), 'Ada Example, WordPress Credits Program 150h, Example University, started 2 September 2026: ada@example.org' );
ck( 'the day before today reads started', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'start' => '2026-11-12' ) ) ), 'Ada Example, WordPress Credits Program 150h, Example University, started 12 November 2026: ada@example.org' );
ck( 'today reads starts', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'start' => '2026-11-13' ) ) ), 'Ada Example, WordPress Credits Program 150h, Example University, starts 13 November 2026: ada@example.org' );
ck( 'a future date reads starts', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'start' => '2026-11-14' ) ) ), 'Ada Example, WordPress Credits Program 150h, Example University, starts 14 November 2026: ada@example.org' );
$GLOBALS['now'] = gmmktime( 23, 30, 0, 11, 12, 2026 );
ck( 'today is the site\'s day: on the evening before, the same date still reads starts', WPCPM_Mentor_Assigned::line( row( 'recX', 'Ada Example', array( 'start' => '2026-11-12' ) ) ), 'Ada Example, WordPress Credits Program 150h, Example University, starts 12 November 2026: ada@example.org' );
unset( $GLOBALS['now'] );
$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-mentor-assigned.php' );
ck( 'both words are translatable phrases with a translators\' comment', array( (bool) preg_match( "#/\\* translators: [^*]+\\*/\\s*\\\$\\w+\\[\\] = sprintf\\( __\\( 'started %s', 'wpcredits-program-manager' \\)#", $src ), (bool) preg_match( "#/\\* translators: [^*]+\\*/\\s*\\\$\\w+\\[\\] = sprintf\\( __\\( 'starts %s', 'wpcredits-program-manager' \\)#", $src ) ), array( true, true ) );
ck( 'a test institution needs TEST in capitals, as on the site', array( WPCPM_Test_Institutions::is_test( 'TEST College' ), WPCPM_Test_Institutions::is_test( 'Test College' ), WPCPM_Test_Institutions::is_test( 'TESTING College' ) ), array( true, false, false ) );

echo "\n=== The source: no names in a log line ===\n";
$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-mentor-assigned.php' );
ck( 'the class logs nothing', (bool) preg_match( '/\b(error_log|WPCPM_Log|trigger_error)\b/', $src ), false );
ck( 'and adds no hook', (bool) preg_match( '/\badd_(action|filter)\s*\(/', $src ), false );

echo "\n" . ( $fails ? "$fails FAILED\n" : "All checks passed.\n" );
exit( $fails ? 1 : 0 );
