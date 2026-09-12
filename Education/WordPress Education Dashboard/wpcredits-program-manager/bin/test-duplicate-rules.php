<?php
/**
 * The Student Duplicate Finder's rules: which of a student's rows may be deleted, and why not.
 *
 * `WPCPM_Duplicate_Rules` is pure, so this suite loads it and nothing else: no WordPress, no
 * Airtable. Every rule of the design's section 5 is pinned on its own, by a row that differs from
 * a clean candidate in that one respect, so taking a rule out fails exactly the check that names
 * it. The shapes the read of 10 September 2026 found are pinned whole along the way.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-rules.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/includes/tools/class-wpcpm-duplicate-rules.php';

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

/**
 * A record ID that says what it is: `rec` and fourteen characters, padded with zeros.
 *
 * @param string $tag Up to fourteen letters and digits.
 * @return string
 */
function rid( $tag ) {
	return 'rec' . str_pad( strtoupper( $tag ), 14, '0' );
}

/** The work columns the scan would read from the report form, cut down to two. */
$work = array( 'Post Reflection: Building Your Personal Website', 'Beginner WordPress User - final grade' );

/**
 * The context the scan hands over: the site's active tracked statuses, and the work columns.
 *
 * `live` is the default `student_statuses` setting word for word, Paused and Pending graduation
 * included, because that is what `WPCPM_Mentors_Sync::tracked_statuses()` hands the scan.
 */
$ctx = array(
	'live'         => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' ),
	'work_columns' => $work,
);

/**
 * One reduced row, from a record in the shape the API returns.
 *
 * @param string $table   One of the three tables.
 * @param string $tag     The record ID's tag.
 * @param string $created ISO 8601 created time.
 * @param array  $fields  Cells.
 * @return array
 */
function row( $table, $tag, $created, array $fields = array() ) {
	global $ctx;

	return WPCPM_Duplicate_Rules::reduce(
		$table,
		array(
			'id'          => rid( $tag ),
			'createdTime' => $created,
			'fields'      => $fields,
		),
		$ctx
	);
}

/**
 * The same student in all three tables: a first application that did not move forward, and a
 * second one that is under way. The shape the Slack request's example has.
 *
 * @param array $changes Table => tag => cells that differ from the clean shape (null removes one).
 * @return array Table => rows.
 */
function reapplied( array $changes = array() ) {
	$base = array(
		'students' => array(
			'stuold' => array( '2026-01-27T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'Not moving forward', 'Educational Institutions' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
			'stunew' => array( '2026-09-09T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'In Sensei', 'Educational Institutions' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
		),
		'reports'  => array(
			'repold' => array( '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'Not moving forward', 'Educational institution' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
			'repnew' => array( '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'In Sensei', 'Educational institution' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
		),
		'feedback' => array(
			'fbold'  => array( '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Institution' => array( rid( 'inst' ) ) ) ),
			'fbnew'  => array( '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Course' => 'In Sensei', 'Institution' => array( rid( 'inst' ) ) ) ),
		),
	);

	$group = array();
	foreach ( $base as $table => $rows ) {
		$group[ $table ] = array();
		foreach ( $rows as $tag => $shape ) {
			$fields = isset( $changes[ $table ][ $tag ] ) ? array_merge( $shape[1], $changes[ $table ][ $tag ] ) : $shape[1];
			foreach ( $fields as $name => $value ) {
				if ( null === $value ) {
					unset( $fields[ $name ] );
				}
			}
			$group[ $table ][] = row( $table, $tag, $shape[0], $fields );
		}
	}

	return $group;
}

/**
 * The classified row with this tag.
 *
 * @param array  $classified `classify()`'s answer.
 * @param string $tag        The record ID's tag.
 * @return array|null
 */
function pick( array $classified, $tag ) {
	foreach ( $classified['rows'] as $rows ) {
		foreach ( $rows as $one ) {
			if ( rid( $tag ) === $one['id'] ) {
				return $one;
			}
		}
	}
	return null;
}

/**
 * A row's proposal and reason codes, the two things most checks read.
 *
 * @param array  $classified `classify()`'s answer.
 * @param string $tag        The record ID's tag.
 * @return array
 */
function says( array $classified, $tag ) {
	$one = pick( $classified, $tag );

	return null === $one ? array() : array( $one['proposal'], $one['codes'] );
}

/* ---- the key and the reduced row ----------------------------------------- */

echo "\n=== The key and the reduced row ===\n";

ck( 'the key compares mailboxes: case and padding do not matter', WPCPM_Duplicate_Rules::key( '  Student@Example.TEST ' ), WPCPM_Duplicate_Rules::key( 'student@example.test' ) );
ck( 'and it is sixteen hex characters, not the address', 1 === preg_match( '/^[0-9a-f]{16}$/', WPCPM_Duplicate_Rules::key( 'student@example.test' ) ), true );
ck( 'no address, no key', WPCPM_Duplicate_Rules::key( '   ' ), '' );

$reduced = row(
	'students',
	'reduced',
	'2026-01-27T10:00:00.000Z',
	array(
		'Email'                    => ' Student@example.test ',
		'Full Name'                => 'A Student',
		'Status'                   => 'In Sensei',
		'Educational Institutions' => array( rid( 'inst' ), 'not a record', array( 'id' => rid( 'inst2' ) ) ),
		'Mentor'                   => array( rid( 'mentor' ) ),
		'Start Date'               => '2026-02-01',
		'Total hours'              => 12.5,
		'Notes'                    => 'Asked to pause.',
	)
);
ck( 'a Students record reduces to what the rules read', $reduced, array(
	'id'          => rid( 'reduced' ),
	'created'     => '2026-01-27T10:00:00.000Z',
	'email'       => 'Student@example.test',
	'name'        => 'A Student',
	'status'      => 'In Sensei',
	'institution' => array( rid( 'inst' ), rid( 'inst2' ) ),
	'mentor'      => true,
	'start'       => '2026-02-01',
	'end'         => '',
	'hours'       => '12.5',
	'notes'       => true,
	'work'        => 0,
) );

$report_cells = array( 'Email' => 'a@example.test', 'Beginner WordPress User - final grade' => 88, 'Hours' => 4, 'Slack Name' => 'someone', 'Personal link' => 'https://example.test/form' );
ck( 'work on a report is a report-form column or Hours above zero, nothing else', WPCPM_Duplicate_Rules::work_filled( 'reports', $report_cells, $work ), array( 'Beginner WordPress User - final grade', 'Hours' ) );
ck( 'and the reduced row keeps the count, not the names, so a scan of three tables stays small', row( 'reports', 'r', '2026-01-01T00:00:00.000Z', $report_cells )['work'], 2 );
ck( 'Hours of zero is not work', WPCPM_Duplicate_Rules::work_filled( 'reports', array( 'Email' => 'a@example.test', 'Hours' => 0 ), $work ), array() );
ck( 'an answer is any Feedback column that is not identity, legacy ones included', WPCPM_Duplicate_Rules::work_filled( 'feedback', array( 'Email' => 'a@example.test', 'Name' => 'A', 'Course' => 'In Sensei', 'Institution' => array( rid( 'inst' ) ), 'F1 - Mentor' => array( rid( 'mentor' ) ), 'F1- Overall experience so far' => 4, 'Review' => 'Good' ), $work ), array( 'F1- Overall experience so far', 'Review' ) );
ck( 'the Students table carries neither', WPCPM_Duplicate_Rules::work_filled( 'students', array( 'Email' => 'a@example.test', 'Notes' => 'Something' ), $work ), array() );
ck( 'an empty string, an empty list and false are not filled; zero is', array( WPCPM_Duplicate_Rules::is_filled( '' ), WPCPM_Duplicate_Rules::is_filled( array() ), WPCPM_Duplicate_Rules::is_filled( false ), WPCPM_Duplicate_Rules::is_filled( 0 ) ), array( false, false, false, true ) );

/* ---- the clean case ------------------------------------------------------ */

echo "\n=== The Slack example: a first application that did not move forward, and a second ===\n";

$clean = WPCPM_Duplicate_Rules::classify( reapplied(), array(), $ctx );
ck( 'the student is Ready', $clean['verdict'], 'ready' );
ck( 'the three older rows are delete candidates', array( says( $clean, 'stuold' ), says( $clean, 'repold' ), says( $clean, 'fbold' ) ), array( array( 'delete', array( 'older' ) ), array( 'delete', array( 'older' ) ), array( 'delete', array( 'older' ) ) ) );
ck( 'and the three newest are kept', array( says( $clean, 'stunew' ), says( $clean, 'repnew' ), says( $clean, 'fbnew' ) ), array( array( 'keep', array( 'newest' ) ), array( 'keep', array( 'newest' ) ), array( 'keep', array( 'newest' ) ) ) );
ck( 'a candidate may be selected, a kept row may not', array( pick( $clean, 'stuold' )['selectable'], pick( $clean, 'stunew' )['selectable'] ), array( true, false ) );
ck( 'the card is headed with the newest Students row\'s name', $clean['name'], 'A Student' );
ck( 'the counts, and no flag', array( $clean['counts'], $clean['flags'] ), array( array( 'students' => 2, 'reports' => 2, 'feedback' => 2 ), array() ) );

$shuffled             = reapplied();
$shuffled['students'] = array_reverse( $shuffled['students'] );
ck( 'rows handed over newest first are sorted oldest first', says( WPCPM_Duplicate_Rules::classify( $shuffled, array(), $ctx ), 'stuold' ), array( 'delete', array( 'older' ) ) );

/* ---- each hold, alone ---------------------------------------------------- */

echo "\n=== Each reason an older row stays, alone ===\n";

/**
 * The Slack example with one difference, classified.
 *
 * @param array $changes As for `reapplied()`.
 * @param array $refs    Record ID => site references.
 * @return array
 */
function held( array $changes, array $refs = array() ) {
	global $ctx;

	return WPCPM_Duplicate_Rules::classify( reapplied( $changes ), $refs, $ctx );
}

$graduate = held( array( 'students' => array( 'stuold' => array( 'Status' => 'Graduate' ) ) ) );
ck( 'a graduation holds an older Students row', says( $graduate, 'stuold' ), array( 'review', array( 'graduation' ) ) );
ck( 'and the student then needs a decision', $graduate['verdict'], 'decide' );
ck( 'held, it can still be ticked on its own', pick( $graduate, 'stuold' )['selectable'], true );

ck( 'a live status holds an older report', says( held( array( 'reports' => array( 'repold' => array( 'Status' => 'Developer Track' ) ) ) ), 'repold' ), array( 'review', array( 'live' ) ) );
ck( 'Paused counts as live though it is no track', says( held( array( 'students' => array( 'stuold' => array( 'Status' => 'Paused' ) ) ) ), 'stuold' ), array( 'review', array( 'live' ) ) );
ck( 'a status the site does not track is not live', says( held( array( 'students' => array( 'stuold' => array( 'Status' => 'Interested' ) ) ) ), 'stuold' ), array( 'delete', array( 'older' ) ) );
ck( 'work holds an older report', says( held( array( 'reports' => array( 'repold' => array( 'Post Reflection: Building Your Personal Website' => 'https://example.test/post' ) ) ) ), 'repold' ), array( 'review', array( 'work' ) ) );
ck( 'answers hold an older Feedback row', says( held( array( 'feedback' => array( 'fbold' => array( 'F4 - What stopped you?' => 'Exams' ) ) ) ), 'fbold' ), array( 'review', array( 'answers' ) ) );
ck( 'Total hours above zero hold an older Students row', says( held( array( 'students' => array( 'stuold' => array( 'Total hours' => 30 ) ) ) ), 'stuold' ), array( 'review', array( 'hours' ) ) );
ck( 'Notes hold an older Students row', says( held( array( 'students' => array( 'stuold' => array( 'Notes' => 'Moved to the 50h course.' ) ) ) ), 'stuold' ), array( 'review', array( 'notes' ) ) );
ck( 'an institution link the newest row lacks holds the older row', says( held( array( 'feedback' => array( 'fbnew' => array( 'Institution' => null ) ) ) ), 'fbold' ), array( 'review', array( 'lacks-institution' ) ) );
ck( 'so does a mentor the newest row lacks', says( held( array( 'reports' => array( 'repnew' => array( 'Mentor' => null ) ) ) ), 'repold' ), array( 'review', array( 'lacks-mentor' ) ) );
ck( 'and a Course the newest Feedback row lacks', says( held( array( 'feedback' => array( 'fbold' => array( 'Course' => 'In Sensei' ), 'fbnew' => array( 'Course' => null ) ) ) ), 'fbold' ), array( 'review', array( 'lacks-status' ) ) );
ck( 'created in the same second as the newest holds the older row', says( WPCPM_Duplicate_Rules::classify( array( 'feedback' => array( row( 'feedback', 'fbone', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ), row( 'feedback', 'fbtwo', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ) ) ), array(), $ctx ), 'fbone' ), array( 'review', array( 'same-second' ) ) );

$site = held( array(), array( rid( 'repold' ) => array( array( 'kind' => 'wpcpm_mentor_note', 'key' => '_wpcpm_student_record', 'object' => 501 ) ) ) );
ck( 'a row the site points at is held and locked', array( says( $site, 'repold' ), pick( $site, 'repold' )['locked'], pick( $site, 'repold' )['selectable'] ), array( array( 'review', array( 'site' ) ), true, false ) );

/* ---- the status rules leave Feedback out ---------------------------------- */

echo "\n=== The status rules leave Feedback out ===\n";

ck( 'an older Feedback row whose Course is a live track is still a candidate', says( held( array( 'feedback' => array( 'fbold' => array( 'Course' => 'In Sensei' ) ) ) ), 'fbold' ), array( 'delete', array( 'older' ) ) );

/* ---- doubts about the newest ---------------------------------------------- */

echo "\n=== When the newest row is not clearly the one to keep ===\n";

$inverted = held( array( 'students' => array( 'stuold' => array( 'Status' => 'In Sensei' ), 'stunew' => array( 'Status' => 'Not moving forward' ) ) ) );
ck( 'a newest row that did not move forward while an older one is live goes to review', says( $inverted, 'stunew' ), array( 'review', array( 'inverted' ) ) );
ck( 'and the older live row says why it stays', says( $inverted, 'stuold' ), array( 'review', array( 'live', 'newest-abandoned' ) ) );
ck( 'that newest row can be ticked on its own', pick( $inverted, 'stunew' )['selectable'], true );

$older_used = held( array(), array( rid( 'repold' ) => array( array( 'kind' => 'user', 'key' => 'wpcpm_student_record_id', 'object' => 9 ) ) ) );
ck( 'when the site uses the older report, the newest goes to review instead of being kept', says( $older_used, 'repnew' ), array( 'review', array( 'site-older' ) ) );
ck( 'and the older one is locked', pick( $older_used, 'repold' )['locked'], true );

$tie = WPCPM_Duplicate_Rules::classify( array( 'students' => array( row( 'students', 'tieone', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ), row( 'students', 'tietwo', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ) ) ), array(), $ctx );
ck( 'two rows created in the same second: the later ID is not "newest", it goes to review', says( $tie, 'tietwo' ), array( 'review', array( 'tie' ) ) );

/* ---- verdicts and flags --------------------------------------------------- */

echo "\n=== Verdicts and flags ===\n";

ck( 'one held row anywhere makes the whole student a decision', held( array( 'feedback' => array( 'fbold' => array( 'Review' => 'It was fine' ) ) ) )['verdict'], 'decide' );

$refire = reapplied();
unset( $refire['students'][0] );
ck( 'more report rows than Students rows is flagged', WPCPM_Duplicate_Rules::classify( $refire, array(), $ctx )['flags'], array( 'refire' ) );

$pending = reapplied();
array_pop( $pending['reports'] );
array_pop( $pending['feedback'] );
ck( 'a second Students row with no second report is flagged', WPCPM_Duplicate_Rules::classify( $pending, array(), $ctx )['flags'], array( 'pending' ) );

ck( 'two spellings of one address are flagged', WPCPM_Duplicate_Rules::classify( reapplied( array( 'feedback' => array( 'fbnew' => array( 'Email' => 'Student@Example.test' ) ) ) ), array(), $ctx )['flags'], array( 'spelling' ) );

/* ---- the selection -------------------------------------------------------- */

echo "\n=== A posted selection, against the stored report ===\n";

$ready_key  = WPCPM_Duplicate_Rules::key( 'student@example.test' );
$decide_key = WPCPM_Duplicate_Rules::key( 'other@example.test' );
$report     = array(
	'groups' => array(
		$ready_key  => $clean,
		$decide_key => $older_used,
	),
);

/**
 * A selection's rows as table, record ID and how each came in.
 *
 * @param array $rows `expand()`'s rows.
 * @return array
 */
function picked( array $rows ) {
	return array_map(
		static function ( $one ) {
			return array( $one['table'], $one['id'], $one['via'] );
		},
		$rows
	);
}

$chosen = WPCPM_Duplicate_Rules::expand( $report, array( $ready_key ), array() );
ck( 'a Ready student stands for its three candidates, through the student checkbox', picked( $chosen['rows'] ), array( array( 'students', rid( 'stuold' ), 'student' ), array( 'reports', rid( 'repold' ), 'student' ), array( 'feedback', rid( 'fbold' ), 'student' ) ) );
ck( 'and nothing is dropped', $chosen['dropped'], array() );

$chosen = WPCPM_Duplicate_Rules::expand( $report, array( $decide_key, 'ffffffffffffffff' ), array() );
ck( 'a student who needs a decision cannot be taken whole, and an unknown key is dropped', array( $chosen['rows'], array_column( $chosen['dropped'], 'code' ) ), array( array(), array( 'not-ready', 'unknown' ) ) );

$chosen = WPCPM_Duplicate_Rules::expand( $report, array(), array( 'reports:' . rid( 'repnew' ), 'reports:' . rid( 'repold' ), 'students:' . rid( 'stunew' ), 'reports:' . rid( 'repnew' ) ) );
ck( 'a selectable row is taken once; a locked or a kept one is dropped', array( picked( $chosen['rows'] ), array_column( $chosen['dropped'], 'code' ) ), array( array( array( 'reports', rid( 'repnew' ), 'row' ) ), array( 'not-selectable', 'not-selectable' ) ) );

// Thirty-four Ready students with three candidates each: 102 rows, two over the limit.
$many = array( 'groups' => array() );
for ( $i = 0; $i < 34; $i++ ) {
	$one = $clean;
	foreach ( $one['rows'] as $table => $rows ) {
		foreach ( array_keys( $rows ) as $n ) {
			// Two digits for the group: `rid()` pads with zeros, so "g1" and "g10" would meet.
			$one['rows'][ $table ][ $n ]['id'] = rid( sprintf( '%s%dg%02d', substr( $table, 0, 1 ), $n, $i ) );
		}
	}
	$many['groups'][ WPCPM_Duplicate_Rules::key( "s{$i}@example.test" ) ] = $one;
}
$chosen = WPCPM_Duplicate_Rules::expand( $many, array_keys( $many['groups'] ), array() );
ck( 'at most 100 rows go in one confirmation; the rest are dropped as over the limit', array( count( $chosen['rows'] ), count( array_keys( array_column( $chosen['dropped'], 'code' ), 'limit', true ) ) ), array( 100, 2 ) );

/* ---- the re-check just before the delete ---------------------------------- */

echo "\n=== The same questions, asked of the base just before the delete ===\n";

$selection = WPCPM_Duplicate_Rules::expand( $report, array( $ready_key ), array() )['rows'];
$now       = array( $ready_key => reapplied() );

$again = WPCPM_Duplicate_Rules::recheck( $selection, $now, array(), $ctx );
ck( 'nothing changed: all three go', array( count( $again['go'] ), $again['refused'] ), array( 3, array() ) );

$gone = $now;
unset( $gone[ $ready_key ]['feedback'][0] );
$again = WPCPM_Duplicate_Rules::recheck( $selection, $gone, array(), $ctx );
ck( 'a row no longer under its address is refused as gone', array_column( $again['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'gone' ) );

$again = WPCPM_Duplicate_Rules::recheck( $selection, $now, array( rid( 'stuold' ) => array( array( 'kind' => 'user', 'key' => 'wpcpm_student_record_id', 'object' => 9 ) ) ), $ctx );
ck( 'a row the site points at now is refused', array_column( $again['refused'], 'code', 'id' ), array( rid( 'stuold' ) => 'site' ) );

$again = WPCPM_Duplicate_Rules::recheck( $selection, array( $ready_key => reapplied( array( 'feedback' => array( 'fbold' => array( 'F1 - How easy was it to get started?' => 5 ) ) ) ) ), array(), $ctx );
ck( 'a row ticked through the student checkbox that has gained answers is refused', array_column( $again['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'changed' ) );

$held_pick = WPCPM_Duplicate_Rules::expand( array( 'groups' => array( $ready_key => $graduate ) ), array(), array( 'students:' . rid( 'stuold' ) ) )['rows'];
$again     = WPCPM_Duplicate_Rules::recheck( $held_pick, array( $ready_key => reapplied( array( 'students' => array( 'stuold' => array( 'Status' => 'Graduate' ) ) ) ) ), array(), $ctx );
ck( 'a held row ticked on its own goes when nothing new holds it', array( count( $again['go'] ), $again['refused'] ), array( 1, array() ) );

$again = WPCPM_Duplicate_Rules::recheck( $held_pick, array( $ready_key => reapplied( array( 'students' => array( 'stuold' => array( 'Status' => 'Graduate', 'Notes' => 'Graduated in June.' ) ) ) ) ), array(), $ctx );
ck( 'but not when it has gained a reason to stay since the scan', array_column( $again['refused'], 'code', 'id' ), array( rid( 'stuold' ) => 'changed' ) );

$last = $now;
unset( $last[ $ready_key ]['feedback'][1] );
$again = WPCPM_Duplicate_Rules::recheck( $selection, $last, array(), $ctx );
ck( 'a row that is now the only one its address has in the table is refused as the last row', array_column( $again['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'last-row' ) );

$both  = WPCPM_Duplicate_Rules::expand( array( 'groups' => array( $ready_key => $inverted ) ), array(), array( 'students:' . rid( 'stuold' ), 'students:' . rid( 'stunew' ) ) )['rows'];
$again = WPCPM_Duplicate_Rules::recheck( $both, array( $ready_key => reapplied( array( 'students' => array( 'stuold' => array( 'Status' => 'In Sensei' ), 'stunew' => array( 'Status' => 'Not moving forward' ) ) ) ) ), array(), $ctx );
ck( 'and ticking every row a table has refuses them all as the last row', array_column( $again['refused'], 'code', 'id' ), array( rid( 'stuold' ) => 'last-row', rid( 'stunew' ) => 'last-row' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
