<?php
/**
 * Off camera, for the clip a-student-is-waiting-to-graduate: the card Students waiting to graduate shows only
 * invented students. On the local copy only, with the filming plugin loaded (bin/clips/copy.sh prepare).
 *
 *   bin/wp eval-file <this file>
 *
 * 1. The copy's own roster rows at Pending graduation move to Graduate (every roster, and the unlinked list).
 * 2. Two invented institutions go into the pipeline index (Northfield College, Example University).
 * 3. Three invented students at Pending graduation, each with an account: one stamped nine days ago, one two days
 *    ago, one marked already pending (it reads "Before" and a date); one has no mentor.
 *
 * The status is written straight into the roster index and the stamp into user meta: no sync starts, nothing is read
 * from or written to Airtable, and the accounts are made with every hook of a new account taken off first, so no
 * mail is queued. bin/clips/copy.sh restore puts the database back. Every name is invented; every address is at
 * example.org.
 *
 * @package WPCreditsProgramManager
 */

if ( 'http://localhost:8900' !== (string) get_option( 'siteurl' ) || 'http://localhost:8900' !== (string) get_option( 'home' ) || ! defined( 'WPCPM_FILMING' ) ) {
	echo "refusing: not the prepared local copy\n";
	return;
}

remove_all_actions( 'user_register' );
remove_all_actions( 'profile_update' );
remove_all_actions( 'set_user_role' );
remove_all_actions( 'add_user_role' );
remove_all_actions( 'added_user_meta' );
remove_all_actions( 'updated_user_meta' );

global $wpdb;

// 1. Nobody else pending.
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
echo "moved {$moved} pending roster rows to Graduate\n";

/** An invented record ID in Airtable's shape, starting recFilm, the same for the same input. */
$rec = static function ( $seed ) {
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
	$hash  = md5( 'wpcpm-filming-graduation' . $seed );
	$id    = 'recFilm';
	for ( $i = 0; $i < 10; $i++ ) {
		$id .= $chars[ hexdec( substr( $hash, $i * 2, 2 ) ) % strlen( $chars ) ];
	}
	return $id;
};

// 2. Two invented institutions in the pipeline index.
$schools = array();
foreach ( array( 'Northfield College' => 'northfield', 'Example University' => 'university' ) as $name => $host ) {
	$record                = $rec( 'school-' . $host );
	$row                   = WPCPM_Institutions_Index::empty_row();
	$row['record_id']      = $record;
	$row['name']           = $name;
	$row['website']        = $host . '.example.org';
	$row['contact_person'] = 'Alex Example';
	$row['contact_email']  = 'alex.example@example.org';
	WPCPM_Institutions_Index::insert( $row );
	WPCPM_Institutions_Index::forget_cached();
	echo WPCPM_Institutions_Index::has( $record ) ? "index row {$name}\n" : "NOT made: index row {$name}\n";
	$schools[ $name ] = $record;
}

// A track with an hours target, for the Track column and "152 of 150".
$target = '';
foreach ( array_keys( WPCPM_Program::labels() ) as $t ) {
	if ( WPCPM_Program::has_hours_target( $t ) ) {
		$target = $t;
		break;
	}
}

// 3. Three invented students.
$now     = time();
$day     = DAY_IN_SECONDS;
$pending = static function ( array $s ) use ( $rec, $schools, $now, $target ) {
	$record = $rec( 'student-' . $s['login'] );
	$parts  = explode( ' ', $s['name'], 2 );
	$u      = get_user_by( 'login', $s['login'] );
	$uid    = $u ? (int) $u->ID : (int) wp_insert_user(
		array(
			'user_login'   => $s['login'],
			'user_email'   => $s['login'] . '@example.org',
			'user_pass'    => wp_generate_password( 32 ),
			'display_name' => $s['name'],
			'first_name'   => $parts[0],
			'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
			'role'         => '',
		)
	);
	$school = $schools[ $s['school'] ];
	WPCPM_Roster_Index::insert(
		$school,
		array(
			'record_id'   => $record,
			'name'        => $s['name'],
			'email'       => $s['login'] . '@example.org',
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
		)
	);
	update_user_meta( $uid, 'wpcpm_student_record_id', $record );
	update_user_meta( $uid, 'wpcpm_student_active', '1' );
	update_user_meta( $uid, 'wpcpm_student_institution', $school );
	update_user_meta(
		$uid,
		'wpcpm_student_program',
		array(
			'record_id'      => $record,
			'name'           => $s['name'],
			'email'          => $s['login'] . '@example.org',
			'program'        => 'Pending graduation',
			'is_past'        => false,
			'start'          => '2026-02-02',
			'end'            => $s['end'],
			'institution'    => $s['school'],
			'profile'        => '',
			'username'       => '',
			'slack'          => '',
			'team'           => 'Core',
			'website'        => '',
			'link'           => '',
			'tutor'          => '',
			'field_of_study' => 'Computer Science',
			'accessibility'  => '',
		)
	);
	update_user_meta( $uid, 'wpcpm_student_updated', $now );
	if ( '' !== $target ) {
		update_user_meta( $uid, 'wpcpm_student_course', $target );
	}
	update_user_meta(
		$uid,
		'wpcpm_student_pending_since',
		array(
			'at'     => (int) $s['at'],
			'before' => ! empty( $s['before'] ),
		)
	);
	echo "pending student {$s['name']} ({$s['school']})\n";
};

$pending( array( 'login' => 'morgan.example', 'name' => 'Morgan Example', 'school' => 'Example University', 'end' => '2026-10-02', 'hours' => '150', 'mentor' => 'Rowan Example', 'at' => $now - 20 * $day, 'before' => true ) );
$pending( array( 'login' => 'avery.example', 'name' => 'Avery Example', 'school' => 'Northfield College', 'end' => '2026-10-30', 'hours' => '152', 'mentor' => 'Harper Example', 'at' => $now - 9 * $day, 'before' => false ) );
$pending( array( 'login' => 'quinn.example', 'name' => 'Quinn Example', 'school' => 'Northfield College', 'end' => '2026-11-06', 'hours' => '151', 'mentor' => '', 'at' => $now - 2 * $day, 'before' => false ) );

wp_cache_flush();
echo "track: {$target}\n";
