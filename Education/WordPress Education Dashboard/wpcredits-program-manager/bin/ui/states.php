<?php
/**
 * Gate 7: the states a round switches between on one seeded copy, applied by `snap.mjs --state <events>,<students>`
 * right after the database is put back to ui-ready (so each state starts from the same invented items and the
 * same frozen moment, under either build). On the local copy only, prepared by bin/ui/copy.sh prepare.
 *
 *   events   a  as seeded: events waiting and coming up (gate 6's seed)
 *            b  NO event waiting: the two waiting events and the long-titled one taken away, so two come up, the
 *               program's own with the Administrator on it and the sponsor's approved one
 *            c  no event at all
 *   students p0  nobody pending: every roster row at Pending graduation is moved to Graduate (the copy's own
 *               rows, under both builds alike)
 *            p2  p0, then two pending students in two invented institutions (one stamped nine days before the
 *               frozen moment, one marked already pending, so it reads "Before <date>"), and one in an invented
 *               test institution stamped older than both; one with no mentor, one on a track with no hours
 *               target, one with a 40-character unbroken word in the name; an invented mentor whose Mentor
 *               Report Card lists the first
 *            p60 p2 and fifty-seven more over five institutions with mixed stamps, one roster row with no account
 *   round 7b: the second real student is at an invented school whose name is a 40-character unbroken word, the test
 *   school's name has eight words or more, the test student's mentor a name of two long words, and p60 holds one
 *   more pending row under an invented school the pipeline index does not list (a roster of its own only)
 *
 * Every name is invented (it holds "Example"); every address is at example.org. The status is written straight
 * into the roster index and the stamp into user meta, as the Task 22 report's "For the rig" says: no sync starts,
 * nothing is read from or written to Airtable, and the accounts are made with every hook of a new account taken
 * off first. Prints one line per thing made, and the last line is a JSON object of the values the scenes need.
 *
 * @package WPCreditsProgramManager
 */

if ( 'http://localhost:8900' !== (string) get_option( 'siteurl' ) || 'http://localhost:8900' !== (string) get_option( 'home' ) || ! defined( 'WPCPM_UI_RIG' ) ) {
	echo "refusing: not the prepared local copy\n";
	return;
}

$in       = json_decode( isset( $args[0] ) ? (string) $args[0] : '{}', true );
$in       = is_array( $in ) ? $in : array();
$events   = isset( $in['events'] ) ? (string) $in['events'] : 'a';
$students = isset( $in['students'] ) ? (string) $in['students'] : 'p0';
$schools  = isset( $in['schools'] ) && is_array( $in['schools'] ) ? $in['schools'] : array();
$now      = (int) get_option( 'wpcpm_ui_rig_now' );
$now      = $now > 0 ? $now : time();
$out      = array(
	'events'   => $events,
	'students' => $students,
);

if ( ! in_array( $events, array( 'a', 'b', 'c' ), true ) || ! in_array( $students, array( 'p0', 'p2', 'p60' ), true ) ) {
	echo "refusing: unknown state {$events},{$students}\n";
	return;
}

// No mail leaves the copy (its safety plugin swallows it), and nothing below sends any; no event of the plugin runs.
remove_all_actions( 'user_register' );
remove_all_actions( 'profile_update' );
remove_all_actions( 'set_user_role' );
remove_all_actions( 'add_user_role' );
remove_all_actions( 'added_user_meta' );
remove_all_actions( 'updated_user_meta' );

// ------------------------------------------------------------------ events
if ( 'a' !== $events ) {
	$keep = array( 'Office hours: your first patch', 'Accessibility checks in practice' );
	$gone = 0;
	foreach ( get_posts( array( 'post_type' => 'wpcpm_sponsor_event', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
		if ( 'b' === $events && in_array( $p->post_title, $keep, true ) ) {
			continue;
		}
		wp_delete_post( $p->ID, true );
		++$gone;
	}
	echo "events {$events}: {$gone} taken away\n";
}

// ------------------------------------------------------------------ students
// p0: the copy's own pending rows move to Graduate, in every roster and in the unlinked list.
global $wpdb;
$moved = 0;
foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'wpcpm_roster_' ) . '%' ) ) as $opt ) { // phpcs:ignore WordPress.DB
	$v = get_option( $opt );
	if ( ! is_array( $v ) || empty( $v['rows'] ) || ! is_array( $v['rows'] ) ) {
		continue;
	}
	$changed = false;
	foreach ( $v['rows'] as $k => $row ) {
		if ( is_array( $row ) && isset( $row['status'] ) && 'Pending graduation' === trim( (string) $row['status'] ) ) {
			$v['rows'][ $k ]['status'] = 'Graduate';
			$changed                   = true;
			++$moved;
		}
	}
	if ( $changed ) {
		update_option( $opt, $v, false );
	}
}
echo "students p0: {$moved} pending roster rows moved to Graduate\n";

/** An invented record ID in Airtable's shape, the same for the same input. */
$rec = static function ( $seed ) {
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
	$hash  = md5( 'ui-rig-gate-7' . $seed );
	$id    = 'recGat';
	for ( $i = 0; $i < 11; $i++ ) {
		$id .= $chars[ hexdec( substr( $hash, $i * 2, 2 ) ) % strlen( $chars ) ];
	}
	return $id;
};

/** An invented account, made with no hook of a new account; answers its ID. */
$account = static function ( $login, $name, $role = '' ) {
	$u = get_user_by( 'login', $login );
	if ( $u ) {
		return (int) $u->ID;
	}
	$parts = explode( ' ', $name, 2 );
	$id    = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@example.org',
			'user_pass'    => wp_generate_password( 32 ),
			'display_name' => $name,
			'first_name'   => $parts[0],
			'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
			'role'         => $role,
		)
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
};

// The tracks the copy defines: one with an hours target, one with none where there is one.
$tracks = array_keys( WPCPM_Program::labels() );
$target = '';
$none   = '';
foreach ( $tracks as $t ) {
	if ( WPCPM_Program::has_hours_target( $t ) && '' === $target ) {
		$target = $t;
	}
	if ( ! WPCPM_Program::has_hours_target( $t ) && '' === $none ) {
		$none = $t;
	}
}
$out['tracks'] = array(
	'all'       => $tracks,
	'target'    => $target,
	'no_target' => $none,
);

if ( 'p0' !== $students ) {
	$design = isset( $schools['Example School of Design'] ) ? (string) $schools['Example School of Design'] : '';
	$inst   = isset( $schools['Example Institute of Technology'] ) ? (string) $schools['Example Institute of Technology'] : '';
	$comm   = isset( $schools['Example Community College'] ) ? (string) $schools['Example Community College'] : '';
	$univ   = isset( $schools['Example University'] ) ? (string) $schools['Example University'] : '';
	if ( '' === $design || '' === $inst ) {
		echo "refusing: the invented schools' records were not handed over\n";
		return;
	}
	$names_of = array(
		$design => 'Example School of Design',
		$inst   => 'Example Institute of Technology',
		$comm   => 'Example Community College',
		$univ   => 'Example University',
	);

	// Two invented institutions of the index: a test one (its name starts TEST) and, for p60, a fifth real one.
	$index_row = static function ( $record, $name, $from ) {
		$row               = WPCPM_Institutions_Index::row( $from );
		$row               = is_array( $row ) ? $row : WPCPM_Institutions_Index::empty_row();
		$row['record_id']  = $record;
		$row['name']       = $name;
		$row['website']    = 'academy.example.org';
		$row['contact_person'] = 'Sage Example';
		$row['contact_email']  = 'sage.example@example.org';
		WPCPM_Institutions_Index::insert( $row );
		WPCPM_Institutions_Index::forget_cached();
		return WPCPM_Institutions_Index::has( $record );
	};
	$test = $rec( 'test-academy' );
	$tname = 'TEST Example Academy of Design, Media and the Applied Arts';
	echo $index_row( $test, $tname, $design ) ? "made index row {$tname}\n" : "not made index row {$tname}\n";
	$names_of[ $test ] = $tname;
	// Round 7b: an invented school whose name is one unbroken word of 40 characters.
	$longschool = $rec( 'long-word-school' );
	$lname      = 'Exampleinstituteforappliedwebengineering';
	echo $index_row( $longschool, $lname, $design ) ? "made index row {$lname}\n" : "not made index row {$lname}\n";
	$names_of[ $longschool ] = $lname;

	// The mentor whose Mentor Report Card lists the first pending student.
	$mentor_id = $account( 'harper.example', 'Harper Example', 'wpcpm_mentor' );
	$mentor    = array(
		'record_id'     => $rec( 'mentor-harper' ),
		'name'          => 'Harper Example',
		'email'         => 'harper.example@example.org',
		'username'      => '',
		'profile'       => '',
		'slack'         => '',
		'jobline'       => '',
		'location'      => '',
		'website'       => '',
		'website_label' => '',
		'github'        => '',
		'teams'         => array(),
		'avatar'        => '',
	);
	$mentees   = array();

	/*
	 * One pending student: the roster row, and with an account its program row, mentor, course and stamp.
	 * $s: key, name, school (record), end, hours, course, mentor ('' for none), at (0 for no stamp), before,
	 * account (bool).
	 */
	$pending = static function ( array $s ) use ( $rec, $account, $now, $names_of, $mentor, &$mentees ) {
		$record = $rec( 'student-' . $s['key'] );
		$login  = sanitize_user( strtolower( str_replace( ' ', '.', $s['key'] ) ), true );
		$uid    = ! empty( $s['account'] ) ? $account( $login, $s['name'] ) : 0;
		$school = (string) $s['school'];
		$row    = array(
			'record_id'   => $record,
			'name'        => $s['name'],
			'email'       => $login . '@example.org',
			'status'      => 'Pending graduation',
			'institution' => $school,
			'start'       => '2026-02-02',
			'end'         => $s['end'],
			'has_mentor'  => '' !== $s['mentor'],
			'username'    => '',
			'mentor_name' => $s['mentor'],
			'team'        => 'Core',
			'hours'       => $s['hours'],
			'reports'     => array(),
			'user_id'     => $uid,
		);
		WPCPM_Roster_Index::insert( $school, $row );
		if ( $uid > 0 ) {
			$program = array(
				'record_id'      => $record,
				'name'           => $s['name'],
				'email'          => $login . '@example.org',
				'program'        => 'Pending graduation',
				'is_past'        => false,
				'start'          => '2026-02-02',
				'end'            => $s['end'],
				'institution'    => isset( $names_of[ $school ] ) ? $names_of[ $school ] : '',
				'profile'        => '',
				'username'       => '',
				'slack'          => '',
				'team'           => 'Core',
				'website'        => '',
				'link'           => '',
				'tutor'          => '',
				'field_of_study' => 'Computer Science',
				'accessibility'  => '',
			);
			update_user_meta( $uid, 'wpcpm_student_record_id', $record );
			update_user_meta( $uid, 'wpcpm_student_active', '1' );
			update_user_meta( $uid, 'wpcpm_student_program', $program );
			update_user_meta( $uid, 'wpcpm_student_institution', $school );
			update_user_meta( $uid, 'wpcpm_student_updated', $now );
			if ( '' !== $s['course'] ) {
				update_user_meta( $uid, 'wpcpm_student_course', $s['course'] );
			}
			if ( 'Harper Example' === $s['mentor'] ) {
				update_user_meta( $uid, 'wpcpm_student_mentor', $mentor );
				$mentees[] = array_merge( $program, array( 'status' => 'Pending graduation', 'hours' => $s['hours'] ) );
				unset( $mentees[ count( $mentees ) - 1 ]['program'] );
			}
			if ( $s['at'] > 0 ) {
				update_user_meta(
					$uid,
					'wpcpm_student_pending_since',
					array(
						'at'     => (int) $s['at'],
						'before' => ! empty( $s['before'] ),
					)
				);
			}
		}
		return $uid > 0 ? $uid : $record;
	};

	$day   = DAY_IN_SECONDS;
	$first = $pending(
		array(
			'key'     => 'riley example',
			'name'    => 'Riley Example',
			'school'  => $design,
			'end'     => '2026-10-30',
			'hours'   => '152',
			'course'  => $target,
			'mentor'  => 'Harper Example',
			'at'      => $now - 9 * $day,
			'before'  => false,
			'account' => true,
		)
	);
	$second = $pending(
		array(
			'key'     => 'jules example',
			'name'    => 'Examplegraduatewaitingonthefinalquizpass Example',
			'school'  => $longschool,
			'end'     => '2026-11-06',
			'hours'   => '87.5',
			'course'  => '' !== $none ? $none : $target,
			'mentor'  => '',
			'at'      => $now - 2 * $day,
			'before'  => true,
			'account' => true,
		)
	);
	$third = $pending(
		array(
			'key'     => 'sage example',
			'name'    => 'Sage Example',
			'school'  => $test,
			'end'     => '2026-09-25',
			'hours'   => '150',
			'course'  => $target,
			'mentor'  => 'Christopherexample Wojciechowskaexample',
			'at'      => $now - 20 * $day,
			'before'  => false,
			'account' => true,
		)
	);
	echo "made pending students: 2 real and 1 at the test institution\n";
	$out['pending_student'] = (int) $first;
	$out['pending_mentor']  = (int) $mentor_id;

	if ( 'p60' === $students ) {
		$fifth = $rec( 'academy-of-arts' );
		echo $index_row( $fifth, 'Example Academy of Arts', $design ) ? "made index row Example Academy of Arts\n" : "not made index row Example Academy of Arts\n";
		$names_of[ $fifth ] = 'Example Academy of Arts';
		$five    = array_values( array_filter( array( $design, $inst, $comm, $univ, $fifth ) ) );
		$mentors = array( 'Harper Example', 'Rowan Mentor Example', 'Indigo Example', '', 'Marlowe Example' );
		$courses = array_values( array_filter( array( $target, $none ) ) );
		for ( $n = 1; $n <= 57; $n++ ) {
			$stamp  = 0 === $n % 11 ? 0 : $now - ( ( $n * 7 ) % 31 + 1 ) * $day;
			$pending(
				array(
					'key'     => sprintf( 'learner %02d example', $n ),
					'name'    => sprintf( 'Learner %02d Example', $n ),
					'school'  => $five[ $n % count( $five ) ],
					'end'     => gmdate( 'Y-m-d', strtotime( '2026-09-01' ) + ( ( $n * 5 ) % 70 ) * DAY_IN_SECONDS ),
					'hours'   => 0 === $n % 9 ? '' : (string) ( 100 + ( $n * 13 ) % 90 ),
					'course'  => $courses ? $courses[ $n % count( $courses ) ] : '',
					'mentor'  => $mentors[ $n % count( $mentors ) ],
					'at'      => $stamp,
					'before'  => 0 === $n % 4,
					// One roster row with no account at all: no link, no day.
					'account' => 30 !== $n,
				)
			);
		}
		echo "made pending students: 57 more over five institutions, one with no account\n";
		// Round 7b: one more under an invented school the pipeline index does not list (its roster only).
		$pending(
			array(
				'key'     => 'unlisted example',
				'name'    => 'Unlisted Example',
				'school'  => $rec( 'school-not-in-the-index' ),
				'end'     => '2026-10-15',
				'hours'   => '140',
				'course'  => $target,
				'mentor'  => 'Indigo Example',
				'at'      => $now - 5 * $day,
				'before'  => false,
				'account' => true,
			)
		);
		echo "made pending student: one under a school the index does not list\n";
	}

	if ( $mentor_id > 0 ) {
		update_user_meta( $mentor_id, 'wpcpm_mentor_record_id', $mentor['record_id'] );
		update_user_meta( $mentor_id, 'wpcpm_mentor_active', '1' );
		update_user_meta( $mentor_id, 'wpcpm_mentees', $mentees );
		update_user_meta( $mentor_id, 'wpcpm_mentee_count', count( $mentees ) );
		update_user_meta( $mentor_id, 'wpcpm_mentee_past_count', 0 );
		update_user_meta( $mentor_id, 'wpcpm_mentees_updated', $now );
		echo 'made mentor Harper Example with ' . count( $mentees ) . " students\n";
	}
}

wp_cache_flush();
echo wp_json_encode( $out ) . "\n";
