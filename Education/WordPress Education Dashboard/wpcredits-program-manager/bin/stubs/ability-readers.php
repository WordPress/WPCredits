<?php
/**
 * The readers the Claude access answers lean on, as fixed fixtures.
 *
 * `WPCPM_Ability_Answers` builds each answer out of readers the plugin already has: the Administrator
 * Dashboard's cards, the Engagement tab's report, the institutions and roster indexes, the sponsors
 * store, the mentor application feed, the published outcome figures and, for the lookups of people,
 * the roster's Students rows, the students' account meta and the mentor applications' posts and their meta.
 * A suite that stands the readers in with these classes can say exactly what each answer must hold, and,
 * because the fixtures carry people (a contact, a student, a mentor, a member, an applicant, a sponsor's
 * contact, a sync's error text, and on every roster row, mentor card and application every field that holds
 * a person), can show that none of them reaches an answer.
 *
 * Every shape here is the shape the real reader returns, read from the reader's own docblock and code
 * on this branch; `bin/test-ability-answers.php` checks that each method still exists there. Every
 * name, address and login is invented, every address is on `example.test`, and every record ID is
 * `rec` and fourteen characters with no lowercase letter, so the release scan passes it.
 *
 * Loaded with `require_once __DIR__ . '/stubs/ability-readers.php';` after `ABSPATH` is defined and
 * the real `WPCPM_Cohort` and `WPCPM_Roles` are loaded. `readers_reset()` puts the fixtures back; a suite
 * changes one by writing into `$GLOBALS['readers']`, and reads what the readers were asked from
 * `$GLOBALS['reader_calls']`.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Unix times the fixtures were read at, one hour apart, so a wrong source shows. */
const READER_T_STUDENTS     = 1792800000; // 2026-10-24T00:00:00+00:00.
const READER_T_INSTITUTIONS = 1792803600; // 2026-10-24T01:00:00+00:00.
const READER_T_OUTCOMES     = 1792807200; // 2026-10-24T02:00:00+00:00.
const READER_T_ROSTER       = 1792810800; // 2026-10-24T03:00:00+00:00.
const READER_T_SYNC_LAST    = 1792814400; // 2026-10-24T04:00:00+00:00.
const READER_T_SYNC_NEXT    = 1792818000; // 2026-10-24T05:00:00+00:00.
const READER_T_MENTEES      = 1792821600; // 2026-10-24T06:00:00+00:00, when the mentors sync last wrote a mentor's list.

/**
 * Every fixture, as the readers return them. Called again to start a block from a clean slate.
 */
function readers_reset() {
	$GLOBALS['reader_calls'] = array();

	$groups = static function ( $current, $waiting, $finished, $not_started ) {
		$row  = static function ( $n ) {
			return array(
				'record_id'   => sprintf( 'recSTUD%010d', $n ),
				'name'        => 'Student Name Sigma',
				'email'       => 'student-sigma@example.test',
				'username'    => 'student_sigma',
				'mentor_name' => 'Mentor Name Tau',
				'status'      => 'Alpha track',
			);
		};
		$list = static function ( $count, $from ) use ( $row ) {
			$out = array();

			for ( $i = 0; $i < $count; $i++ ) {
				$out[ sprintf( 'recSTUD%010d', $from + $i ) ] = $row( $from + $i );
			}

			return $out;
		};

		return array(
			'current'     => $list( $current, 1 ),
			'waiting'     => $list( $waiting, 100 ),
			'finished'    => $list( $finished, 200 ),
			'not_started' => $list( $not_started, 300 ),
		);
	};

	$GLOBALS['readers'] = array(
		// The Administrator Dashboard's Programs card: track key => name (`tracks()`), and the numbers per key.
		'tracks'       => array(
			'alpha' => 'Alpha Track',
			'beta'  => 'Beta Track',
		),
		'programs'     => array(
			'tracks'   => array(
				'alpha' => array(
					'in_progress' => 12,
					'signed_up'   => 5,
				),
				'beta'  => array(
					'in_progress' => 3,
					'signed_up'   => 0,
				),
			),
			'finished' => 7,
			'rows'     => array(
				array(
					'record'      => 'recINST0000000001',
					'name'        => 'University of Example',
					'in_progress' => 10,
					'by_status'   => array( 'Alpha Track' => 10 ),
					'waiting'     => 1,
					'mentors'     => 4,
					'earliest'    => '2026-12-01',
					'latest'      => '2026-12-20',
					'agreement'   => 'accepted',
					'report'      => 'none',
				),
				array(
					'record'      => 'recINST0000000002',
					'name'        => 'Northern UNIVERSITY of Samples',
					'in_progress' => 5,
					'by_status'   => array( 'Beta Track' => 3, 'Alpha Track' => 2 ),
					'waiting'     => 0,
					'mentors'     => 2,
					'earliest'    => '',
					'latest'      => '',
					'agreement'   => 'none',
					'report'      => 'none',
				),
			),
			'quiet'    => 2,
			'read'     => READER_T_STUDENTS,
			'semester' => '2026-H2',
		),
		// The Sponsors card, with the ladder's five rungs.
		'sponsors'     => array(
			'approved'        => 5,
			'with_accounts'   => 3,
			'live_offers'     => 4,
			'claims_semester' => 9,
			'since'           => '2026-07-01',
			'since_display'   => 'July 1, 2026',
			'tiers'           => array(
				'mentor'    => 1,
				'supporter' => 2,
				'backer'    => 1,
				'champion'  => 0,
				'partner'   => 1,
			),
		),
		// The sponsors store: two of the five approved are test sponsors, and two are not approved.
		'sponsor_rows' => array(
			'spoSPON0000000001' => array( 'status' => 'Approved', 'test' => false, 'name' => 'Sponsor One', 'contact' => 'Sponsor Contact Chi' ),
			'spoSPON0000000002' => array( 'status' => 'Approved', 'test' => true, 'name' => 'TEST Sponsor Two', 'contact' => 'Sponsor Contact Chi' ),
			'spoSPON0000000003' => array( 'status' => 'Approved', 'test' => false, 'name' => 'Sponsor Three', 'contact' => 'Sponsor Contact Chi' ),
			'spoSPON0000000004' => array( 'status' => 'Approved', 'test' => true, 'name' => 'TEST Sponsor Four', 'contact' => 'Sponsor Contact Chi' ),
			'spoSPON0000000005' => array( 'status' => 'Approved', 'test' => false, 'name' => 'Sponsor Five', 'contact' => 'Sponsor Contact Chi' ),
			'spoSPON0000000006' => array( 'status' => 'In review', 'test' => true, 'name' => 'TEST Sponsor Six', 'contact' => 'Sponsor Contact Chi' ),
			'spoSPON0000000007' => array( 'status' => 'Rejected', 'test' => false, 'name' => 'Sponsor Seven', 'contact' => 'Sponsor Contact Chi' ),
		),
		// The Engagement tab's report: rows that name people, and the summary the answer passes on.
		'engagement'   => array(
			'rows'    => array(
				array(
					'id'    => 41,
					'name'  => 'Mentor Name Phi',
					'email' => 'mentor-phi@example.test',
					'login' => 'mentor_phi',
				),
			),
			'summary' => array(
				'active'                => 40,
				'signed_in'             => 31,
				'seen_30'               => 22,
				'seen_7'                => 11,
				'never'                 => 9,
				'acted'                 => 17,
				'idle'                  => 14,
				'with_students'         => 25,
				'with_students_hours'   => 19,
				'with_students_link'    => 15,
				'with_students_notes'   => 13,
				'with_students_seen_30' => 21,
				'calls_held'            => 60,
				'calls_upcoming'        => 8,
				'calls_noted'           => 33,
				'sessions_held'         => 6,
				'sessions_upcoming'     => 2,
				'sessions_noted'        => 4,
				'staff'                 => 3,
			),
		),
		// The institutions index. Contacts and `accepted_by` are on every row, and must never leave.
		'institutions' => array(
			'read' => READER_T_INSTITUTIONS,
			'rows' => array(
				'recINST0000000001' => array(
					'record_id'      => 'recINST0000000001',
					'name'           => 'University of Example',
					'stage'          => 'Confirmed',
					'country'        => 'XE',
					'country_name'   => 'Exampleland',
					'city'           => 'Sample City',
					'website'        => 'https://example.test/university',
					'contact_person' => 'Contact Person Zeta',
					'contact_email'  => 'contact-zeta@example.test',
					'created'        => '2025-09-01',
					'consent'        => true,
					'confirmed_on'   => '2026-03-02',
					'agreement'      => array(
						'status'           => 'Accepted',
						'kind'             => 'Standard',
						'accepted_on'      => '2026-03-02',
						'signed_on'        => '2026-03-01',
						'accepted_by'      => 'Acceptor Name Omega',
						'submitted_on'     => '2026-02-20',
						'template_version' => '2026-01',
						'has_document'     => true,
					),
				),
				'recINST0000000002' => array(
					'record_id'      => 'recINST0000000002',
					'name'           => 'Northern UNIVERSITY of Samples',
					'stage'          => 'Under Review',
					'country'        => 'XE',
					'country_name'   => 'Exampleland',
					'city'           => '',
					'website'        => '',
					'contact_person' => 'Contact Person Zeta',
					'contact_email'  => 'contact-zeta@example.test',
					'created'        => '2026-05-01',
					'consent'        => false,
					'confirmed_on'   => '',
					'agreement'      => array(
						'status'           => '',
						'kind'             => '',
						'accepted_on'      => '',
						'signed_on'        => '',
						'accepted_by'      => '',
						'submitted_on'     => '',
						'template_version' => '',
						'has_document'     => false,
					),
				),
				'recINST0000000003' => array(
					'record_id'      => 'recINST0000000003',
					'name'           => 'Example Institute of Technology',
					'stage'          => 'Confirmed',
					'country'        => 'XF',
					'country_name'   => '',
					'city'           => 'Another City',
					'website'        => '',
					'contact_person' => 'Contact Person Zeta',
					'contact_email'  => 'contact-zeta@example.test',
					'created'        => '2026-06-01',
					'consent'        => false,
					'confirmed_on'   => '',
					'agreement'      => array(
						'status'           => 'Submitted',
						'kind'             => 'Standard',
						'accepted_on'      => '',
						'signed_on'        => '',
						'accepted_by'      => 'Acceptor Name Omega',
						'submitted_on'     => '2026-09-30',
						'template_version' => '',
						'has_document'     => false,
					),
				),
				'recINST0000000004' => array(
					'record_id'      => 'recINST0000000004',
					'name'           => "Long\nName " . str_repeat( 'x', 500 ),
					'stage'          => "Info\u{200B} Sent",
					'country'        => '',
					'country_name'   => '',
					'city'           => "Odd\tCity",
					'website'        => '',
					'contact_person' => 'Contact Person Zeta',
					'contact_email'  => 'contact-zeta@example.test',
					'created'        => '2026-06-02',
					'consent'        => false,
					'confirmed_on'   => '',
					'agreement'      => array(
						'status'           => '',
						'kind'             => '',
						'accepted_on'      => '',
						'signed_on'        => '',
						'accepted_by'      => '',
						'submitted_on'     => '',
						'template_version' => '',
						'has_document'     => false,
					),
				),
				'recINST0000000005' => array(
					'record_id'      => 'recINST0000000005',
					'name'           => 'Plain College',
					'stage'          => '',
					'country'        => '',
					'country_name'   => '',
					'city'           => '',
					'website'        => '',
					'contact_person' => 'Contact Person Zeta',
					'contact_email'  => 'contact-zeta@example.test',
					'created'        => '2026-06-03',
					'consent'        => false,
					'confirmed_on'   => '',
					'agreement'      => array(
						'status'           => '',
						'kind'             => '',
						'accepted_on'      => '',
						'signed_on'        => '',
						'accepted_by'      => '',
						'submitted_on'     => '',
						'template_version' => '',
						'has_document'     => false,
					),
				),
			),
		),
		// Students per institution per cohort: 'all' for no cohort. Anything not listed has no students.
		'groups'       => array(
			'recINST0000000001' => array(
				'all'     => $groups( 7, 2, 4, 1 ),
				'2026-H2' => $groups( 5, 1, 0, 0 ),
			),
			'recINST0000000002' => array(
				'all' => $groups( 0, 3, 0, 0 ),
			),
		),
		'roster_read'  => READER_T_ROSTER,
		// The accounts that act for an institution. The answer counts them and never names one.
		'members'      => array(
			'recINST0000000001' => array(
				(object) array( 'ID' => 71, 'user_login' => 'member_login_one', 'user_email' => 'member-one@example.test', 'display_name' => 'Member Name Upsilon' ),
				(object) array( 'ID' => 72, 'user_login' => 'member_login_two', 'user_email' => 'member-two@example.test', 'display_name' => 'Member Name Upsilon' ),
				(object) array( 'ID' => 73, 'user_login' => 'member_login_three', 'user_email' => 'member-three@example.test', 'display_name' => 'Member Name Upsilon' ),
			),
		),
		// The mentor application feed's counts.
		'feed_today'   => array(
			'open'     => 4,
			'selected' => 2,
		),
		// The outcome figures the homepage may show.
		'published'    => array(
			'read'    => READER_T_OUTCOMES,
			'figures' => array(
				'graduation' => array( 'share' => 78, 'count' => 156, 'n' => 200 ),
				'recommend'  => array( 'share' => 91, 'count' => 91, 'n' => 100 ),
				'keep'       => array( 'share' => 64, 'count' => 64, 'n' => 100 ),
			),
		),
		// The attention strip: what `counts( collect() )` gives, in the order the strip draws it.
		'tiles'        => array(
			'applications'        => array( 'label' => 'Institution applications waiting', 'n' => 3, 'card' => 'applications' ),
			'agreements'          => array( 'label' => 'Collaboration Agreements to review, 1 overdue', 'n' => 2, 'card' => 'agreements' ),
			'mentor_applications' => array( 'label' => 'Mentor applications waiting', 'n' => 4, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' ),
			'sponsor_posts'       => array( 'label' => 'Sponsor posts to review', 'n' => 26, 'shown' => '25+', 'card' => 'sponsor-posts' ),
			'syncs'               => array( 'label' => 'Sync needs a look', 'n' => 1, 'card' => 'health' ),
		),
		'page_url'     => 'https://example.test/administrator-dashboard/',
		// The Syncs and health card's data: a sync's error text rides in `progress` and `note`.
		'health'       => array(
			'syncs'     => array(
				'students'        => array(
					'label'    => 'Students',
					'progress' => array( 'running' => false, 'phase' => '', 'label' => 'Reading Airtable', 'error' => 'Token rejected for mentor-one' ),
					'last'     => READER_T_SYNC_LAST,
					'next'     => READER_T_SYNC_NEXT,
					'screen'   => 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=sync',
				),
				'mentors'         => array(
					'label'    => 'Mentors',
					'progress' => array( 'running' => false, 'phase' => '', 'label' => '', 'error' => '' ),
					'last'     => READER_T_SYNC_LAST,
					'next'     => READER_T_SYNC_NEXT,
					'screen'   => 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=sync',
				),
				'institutions'    => array(
					'label'    => 'Institutions',
					'progress' => array( 'running' => false, 'phase' => '', 'label' => '', 'error' => '' ),
					'last'     => 0,
					'next'     => 0,
					'screen'   => 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=sync',
				),
				'outcome_figures' => array(
					'label'    => 'Outcome figures',
					'progress' => array( 'running' => false, 'phase' => '', 'label' => 'Reading Airtable', 'error' => 'Token rejected for mentor-one' ),
					'last'     => READER_T_OUTCOMES,
					'next'     => READER_T_SYNC_NEXT,
					'screen'   => 'https://example.test/wp-admin/options-general.php',
					'note'     => 'Token rejected for mentor-one',
				),
			),
			'auto_sync' => true,
		),
		// What the Syncs and health card's rule says of each audience's sync, by its label, with Automatic sync
		// on and off: an error needs a look either way, a sync that never ran only while it is on. The rule is
		// pinned on the real class in bin/test-administrators-dashboard.php; these suites pin that an answer
		// asks it of every sync, with the switch `health()` read, and says what it answered.
		'needs_look'   => array(
			'Students'     => array( 'on' => true, 'off' => true ),
			'Mentors'      => array( 'on' => false, 'off' => false ),
			'Institutions' => array( 'on' => true, 'off' => false ),
		),
		// What the outcome figures' weekly read needs a look for: '' for nothing, else held, failed or stopped.
		'figures_look' => '',
		// The roster index's Students rows, per institution, as `rows()` returns them, and the rows that name
		// no institution (`unlinked()`). Every person field a row can hold is on every row, and the account's
		// display name, login and profile address besides, so the people walk can show that none goes out.
		// recINST0000000006 has a roster and is not in the institutions index.
		'roster'       => array(
			'recINST0000000001' => array(
				'recPUPA0000000001' => readers_track( 'alpha', 'recPUPA0000000001', 'recINST0000000001', array( 'status' => 'Pending graduation', 'start' => '2026-03-02', 'user_id' => 501 ) ),
				'recPUPA0000000003' => readers_track( 'alpha', 'recPUPA0000000003', 'recINST0000000001', array( 'status' => 'Duplicated', 'start' => '2024-01-15', 'has_mentor' => true, 'user_id' => 501 ) ),
				'recPUPD0000000001' => readers_track( 'delta', 'recPUPD0000000001', 'recINST0000000001', array( 'status' => 'SPAM', 'start' => '2026-01-10' ) ),
			),
			'recINST0000000002' => array(
				'recPUPA0000000002' => readers_track( 'alpha', 'recPUPA0000000002', 'recINST0000000002', array( 'status' => 'In Sensei', 'start' => '2025-09-01', 'end' => '2026-01-31', 'has_mentor' => true, 'team' => 'Polyglots', 'hours' => '135.5', 'reports' => array( 'recREPT0000000001', 'recREPT0000000002' ), 'user_id' => 501 ) ),
				'recPUPC0000000001' => readers_track( 'gamma', 'recPUPC0000000001', 'recINST0000000002', array( 'status' => 'Pending graduation', 'start' => '2025-03-01', 'team' => "Core\u{200B} Team\n", 'hours' => '6.2', 'user_id' => 503 ) ),
				'recPUPC0000000002' => readers_track( 'gamma', 'recPUPC0000000002', 'recINST0000000002', array( 'status' => "Paused\n", 'start' => '2025-03-01', 'end' => 'soon', 'hours' => 'n/a' ) ),
			),
			'recINST0000000006' => array(
				'recPUPE0000000001' => readers_track( 'epsilon', 'recPUPE0000000001', 'recINST0000000006', array( 'status' => 'Graduate', 'start' => '2024-02-01', 'end' => '2024-06-30', 'has_mentor' => true, 'team' => 'Training', 'hours' => '400', 'reports' => array( 'recREPT0000000003' ), 'user_id' => 502 ) ),
			),
		),
		'unlinked'     => array(
			'recPUPB0000000001' => readers_track( 'beta', 'recPUPB0000000001', '', array( 'status' => 'Interested' ) ),
		),
		// The accounts' meta, by account and key: the mentor card the students sync writes (every field of a
		// mentor on it), the course kept while a student waits to graduate, the Students Reports record the
		// account follows (`META_RECORD_ID`), and the program row (`META_PROGRAM`, every field of the person
		// and the accessibility answer on it), which no answer reads.
		'user_meta'    => array(
			501 => array(
				'wpcpm_student_mentor'    => readers_mentor_card( 'recMENT0000000001' ),
				'wpcpm_student_course'    => 'In Sensei',
				'wpcpm_student_record_id' => 'recREPT0000000002',
				'wpcpm_student_program'   => readers_program_row( 'alpha' ),
			),
			502 => array(
				'wpcpm_student_mentor'    => array(),
				'wpcpm_student_course'    => 'Developer Track',
				'wpcpm_student_record_id' => 'recREPT0000000003',
				'wpcpm_student_program'   => readers_program_row( 'epsilon' ),
			),
			503 => array(
				'wpcpm_student_mentor'  => readers_mentor_card( '' ),
				'wpcpm_student_program' => readers_program_row( 'gamma' ),
			),
		),
		// The statuses the site runs a track for, as `WPCPM_Program::is_track()` reads them.
		'tracks_run'   => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track' ),
		// The accounts a mentor is looked up among (`get_user_by()`, `get_users()`), by account ID: four mentors
		// (91 with three kinds of student, 92 a Mentor with no Mentors record ID, 93 who stopped mentoring and
		// has no students, 94 an Administrator who mentors) and two students' accounts, which are no mentor's.
		'accounts'     => array(
			91  => readers_account( 91, readers_mentors()['gamma'] ),
			92  => readers_account( 92, readers_mentors()['nu'] ),
			93  => readers_account( 93, readers_mentors()['xi'] ),
			94  => readers_account( 94, readers_mentors()['omicron'] ),
			501 => readers_account( 501, readers_pupils()['alpha'], false ),
			502 => readers_account( 502, readers_pupils()['epsilon'], false ),
		),
	);

	// What the mentors sync keeps on a mentor's account: the Mentors record ID, the active flag, the students
	// (the rows of `readers_mentee()`) and when the list was written. 92 holds no record ID, 93 no students.
	$GLOBALS['readers']['user_meta'] += array(
		91 => array(
			'wpcpm_mentor_record_id' => 'recMENT0000000001',
			'wpcpm_mentor_active'    => 1,
			'wpcpm_mentees'          => array(
				readers_mentee( 'epsilon', 'recREPT0000000003', array( 'status' => 'Graduate', 'is_past' => true, 'start' => '2024-02-01', 'end' => '2024-06-30', 'hours' => '400' ) ),
				readers_mentee( 'alpha', 'recREPT0000000001', array( 'status' => 'In Sensei', 'start' => '2026-03-02', 'end' => '2026-08-31', 'hours' => '135.5' ) ),
				readers_mentee( 'beta', 'recREPT0000000002', array( 'status' => "Pending\ngraduation", 'start' => '2026-01-10', 'hours' => '0' ) ),
				readers_mentee( 'gamma', 'recREPT0000000004', array( 'status' => 'Paused', 'start' => 'soon', 'hours' => 'n/a' ) ),
				readers_mentee( 'delta', '', array( 'status' => 'In Sensei', 'start' => '2026-02-02', 'hours' => '12' ) ),
				readers_mentee( 'alpha', 'recREPT0000000001', array( 'status' => 'Duplicated again', 'start' => '2026-04-04', 'hours' => '1' ) ),
			),
			'wpcpm_mentees_updated'  => READER_T_MENTEES,
		),
		92 => array(
			'wpcpm_mentees'         => array( readers_mentee( 'beta', 'recREPT0000000005', array( 'status' => 'In Sensei 50h', 'start' => '2026-05-05', 'hours' => '20' ) ) ),
			'wpcpm_mentees_updated' => READER_T_MENTEES,
		),
		93 => array(
			'wpcpm_mentor_record_id' => 'recMENT0000000002',
			'wpcpm_mentor_active'    => 0,
			'wpcpm_mentees_updated'  => READER_T_MENTEES,
		),
		94 => array(
			'wpcpm_mentor_record_id' => 'recMENT0000000003',
			'wpcpm_mentor_active'    => 1,
			'wpcpm_mentees'          => array( readers_mentee( 'epsilon', 'recREPT0000000006', array( 'status' => 'Developer Track', 'start' => '2026-06-01', 'hours' => '3.5' ) ) ),
			'wpcpm_mentees_updated'  => READER_T_MENTEES,
		),
	);

	// The Engagement tab's rows for the four mentors, after the one that names a mentor the answers never
	// see: 91 is seen and acted (so "With students now"), 92 has students and no weekly hours, 93 stopped
	// mentoring, 94 is an Administrator.
	$GLOBALS['readers']['engagement']['rows'][] = readers_engagement_row( 91, readers_mentors()['gamma'], array( 'students_now' => 2, 'students_past' => 1, 'last_sign_in' => 1792799999, 'last_visit' => 1792886400, 'last_seen' => 1792886400, 'seen_7' => true, 'seen_30' => true, 'schedule_saved' => true, 'weekly_hours' => true, 'acted' => true ) );
	$GLOBALS['readers']['engagement']['rows'][] = readers_engagement_row( 92, readers_mentors()['nu'], array( 'students_now' => 1, 'last_sign_in' => 1792800000, 'last_visit' => 1792800000, 'last_seen' => 1792800000, 'seen_7' => true, 'seen_30' => true, 'schedule_saved' => true, 'acted' => true ) );
	$GLOBALS['readers']['engagement']['rows'][] = readers_engagement_row( 93, readers_mentors()['xi'], array( 'active' => false ) );
	$GLOBALS['readers']['engagement']['rows'][] = readers_engagement_row( 94, readers_mentors()['omicron'], array( 'staff' => true, 'students_now' => 1, 'last_sign_in' => 1792800000, 'last_seen' => 1792800000, 'seen_7' => true, 'seen_30' => true, 'acted' => true ) );

	// The mentor applications: ten, one for each of the ten applicants, in every state the answers look at and
	// in the three they never list. The day a row arrived and the day it entered its state are half a day past a
	// whole number of days before now, so the days it has been in the state are that number whatever the minute.
	// Open (4, the feed's `open`): 701 new, put back in the queue a day and a half ago, a possible duplicate;
	// 702 held; 703 more information asked for; 704 a second opinion wanted, a possible duplicate. Selected (2,
	// the feed's `selected`): 705, 706. Lapsed (1): 707. Never listed: 708 filed as spam, 709 declined, 710 active.
	$GLOBALS['reader_now']                   = time();
	$GLOBALS['readers']['applications']      = array();
	$GLOBALS['readers']['applications_leak'] = false;

	// The countries and the contribution teams the form offers, record ID => name, as the two syncs keep them:
	// the ten countries of the applications above, and the teams they tick, with one of two words. A suite
	// that empties a list is a site whose sync has not run.
	$GLOBALS['readers']['form_countries'] = array(
		'recCTRY0000000001' => 'Brazil',
		'recCTRY0000000002' => 'Canada',
		'recCTRY0000000003' => 'Germany',
		'recCTRY0000000004' => 'India',
		'recCTRY0000000005' => 'Italy',
		'recCTRY0000000006' => 'Japan',
		'recCTRY0000000007' => 'Kenya',
		'recCTRY0000000008' => 'Nigeria',
		'recCTRY0000000009' => 'Poland',
		'recCTRY0000000010' => 'Spain',
	);
	$GLOBALS['readers']['form_teams']     = array(
		'recTEAM0000000001' => 'Accessibility',
		'recTEAM0000000002' => 'Community',
		'recTEAM0000000003' => 'Core',
		'recTEAM0000000004' => 'Design',
		'recTEAM0000000005' => 'Photo Directory',
		'recTEAM0000000006' => 'Polyglots',
		'recTEAM0000000007' => 'Support',
		'recTEAM0000000008' => 'Training',
	);

	foreach ( array(
		701 => array( 'alpha', WPCPM_Mentor_Application::STATE_NEW, 2, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_REOPENED, 1 ) ), 'signals' => array( WPCPM_Mentor_Application_Form::SIGNAL_DUPLICATE ), 'verified' => true, 'fields' => array( 'country' => 'Poland', 'teacher' => 'No', 'languages' => array( 'Polish', 'English' ), 'expertise' => array( 'Training' ), 'hours' => '3' ) ) ),
		702 => array( 'beta', WPCPM_Mentor_Application::STATE_HELD, 9, array( 'signals' => array( WPCPM_Mentor_Application_Form::SIGNAL_HONEYPOT, WPCPM_Mentor_Application_Form::SIGNAL_DWELL ), 'fields' => array( 'country' => 'Kenya', 'teacher' => 'Yes', 'languages' => array( 'Swahili', 'English' ), 'expertise' => array( 'Polyglots', 'Community' ), 'hours' => '2.5' ) ) ),
		703 => array( 'gamma', WPCPM_Mentor_Application::STATE_INFO, 15, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_INFO, 4 ) ), 'signals' => array( WPCPM_Mentor_Application_Form::SIGNAL_MENTOR, WPCPM_Mentor_Application_Form::SIGNAL_MAIL_FAILED ), 'verified' => true, 'fields' => array( 'country' => 'Brazil', 'teacher' => 'No', 'languages' => array( 'Portuguese' ), 'expertise' => array(), 'hours' => '10' ) ) ),
		704 => array( 'delta', WPCPM_Mentor_Application::STATE_SECOND, 20, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_SECOND, 3 ) ), 'signals' => array( WPCPM_Mentor_Application_Form::SIGNAL_DUPLICATE ), 'fields' => array( 'country' => 'Japan', 'teacher' => 'Yes', 'languages' => array( 'Japanese' ), 'expertise' => array( 'Core' ), 'hours' => '4' ) ) ),
		705 => array( 'epsilon', WPCPM_Mentor_Application::STATE_SELECTED, 70, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_SELECTED, 40 ) ), 'verified' => true, 'fields' => array( 'country' => 'India', 'teacher' => 'No', 'languages' => array( 'Hindi', 'English', 'Marathi' ), 'expertise' => array( 'Support' ), 'hours' => '5' ) ) ),
		706 => array( 'zeta', WPCPM_Mentor_Application::STATE_SELECTED, 30, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_SELECTED, 10 ) ), 'signals' => array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING ), 'fields' => array( 'country' => 'Nigeria', 'teacher' => 'No', 'languages' => array( 'English', 'Nigerian Pidgin', 'Hausa' ), 'expertise' => array( 'Design' ), 'hours' => '2' ) ) ),
		707 => array( 'eta', WPCPM_Mentor_Application::STATE_LAPSED, 120, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_SELECTED, 100 ), array( WPCPM_Mentor_Application::EVENT_LAPSED, 5 ) ), 'verified' => true, 'fields' => array( 'country' => 'Spain', 'teacher' => 'Yes', 'languages' => array( 'Spanish', 'Catalan' ), 'expertise' => array( 'Accessibility' ), 'hours' => '1.5' ) ) ),
		708 => array( 'theta', WPCPM_Mentor_Application::STATE_SPAM, 1, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_SPAM, 1 ) ), 'fields' => array( 'country' => 'Canada', 'teacher' => 'No', 'languages' => array( 'English' ), 'expertise' => array(), 'hours' => '3' ) ) ),
		709 => array( 'iota', WPCPM_Mentor_Application::STATE_DECLINED, 3, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_DECLINED, 2 ) ), 'verified' => true, 'fields' => array( 'country' => 'Italy', 'teacher' => 'No', 'languages' => array( 'Italian' ), 'expertise' => array(), 'hours' => '3' ) ) ),
		710 => array( 'kappa', WPCPM_Mentor_Application::STATE_ACTIVE, 4, array( 'events' => array( array( WPCPM_Mentor_Application::EVENT_ACTIVATED, 2 ) ), 'verified' => true, 'fields' => array( 'country' => 'Germany', 'teacher' => 'Yes', 'languages' => array( 'German' ), 'expertise' => array( 'Training' ), 'hours' => '6' ) ) ),
	) as $id => $spec ) {
		$GLOBALS['readers']['applications'][ $id ] = readers_application( $id, $spec[0], $spec[1], $spec[2], $spec[3] );
	}
}

/**
 * The invented applicants of the mentor application fixtures, by key, each with every field that holds a
 * person, as `readers_pupils()` gives its people. An applicant's name, address and profile are the answers
 * the form keeps for them, and every field of the person besides is typed into the free-text answer, so a
 * leak of any of them shows.
 *
 * @return array[] Key => the fields.
 */
function readers_applicants() {
	$person = static function ( $key ) {
		$title    = ucfirst( $key );
		$username = "applicant-$key-example";

		return array(
			'name'           => "Applicant Name $title",
			'display_name'   => "Applicant Display $title",
			'login'          => "applicant_login_$key",
			'nicename'       => "applicant-nicename-$key",
			'email'          => "Applicant-$title@example.test",
			'email_key'      => strtolower( "Applicant-$title@example.test" ),
			'username'       => $username,
			'profile'        => 'https://profiles.wordpress.org/' . $username . '/',
			'slack'          => "applicant-$key-slack",
			'github'         => "applicant-$key-github",
			'avatar'         => "https://example.test/avatar-applicant-$key.png",
			'jobline'        => "Jobline Applicant $title",
			'location'       => "Location Applicant $title",
			'website'        => "https://applicant-$key.example.test/",
			'website_label'  => "Website Label Applicant $title",
			'tutor'          => "Tutor Name Applicant $title",
			'mentor_name'    => "Mentor Name Applicant $title",
			'field_of_study' => "Field of Study Applicant $title",
			'import_key'     => "import-key-applicant-$key",
			'accessibility'  => "Accessibility Answer Applicant $title",
		);
	};

	$people = array();

	foreach ( array( 'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta', 'eta', 'theta', 'iota', 'kappa' ) as $key ) {
		$people[ $key ] = $person( $key );
	}

	return $people;
}

/**
 * One mentor application as the site keeps it, for the stand-in of `WPCPM_Mentor_Application`: the answers
 * the form keeps (the name, address and profile of the applicant, the free-text answer with every other
 * field of the person typed into it, the two boxes), the consent evidence, the notes in the history, and
 * what the form's checks raised.
 *
 * @param int    $id    The post ID.
 * @param string $who   A key of `readers_applicants()`.
 * @param string $state The state.
 * @param int    $days  Whole days before now the application arrived (half a day more).
 * @param array  $facts `events` (each `array( event, whole days before now )`), `signals`, `verified`, and
 *                      `fields` that replace the defaults.
 * @return array
 */
function readers_application( $id, $who, $state, $days, array $facts = array() ) {
	$person = readers_applicants()[ $who ];
	$ago    = static function ( $whole ) {
		return $GLOBALS['reader_now'] - $whole * 86400 - 43200;
	};
	$events = array(
		array(
			'event' => WPCPM_Mentor_Application::EVENT_SUBMITTED,
			'at'    => $ago( $days ),
			'actor' => 0,
			'note'  => '',
		),
	);

	foreach ( isset( $facts['events'] ) ? $facts['events'] : array() as $event ) {
		$events[] = array(
			'event' => $event[0],
			'at'    => $ago( $event[1] ),
			'actor' => 7,
			'note'  => 'NOTE-SENTINEL ' . $person['name'] . ' ' . $person['email'],
		);
	}

	return array(
		'ID'        => $id,
		'arrived'   => $ago( $days ),
		'state'     => $state,
		'reference' => sprintf( 'MAPP-2026-%04d', $id ),
		'verified'  => ! empty( $facts['verified'] ),
		'signals'   => isset( $facts['signals'] ) ? $facts['signals'] : array(),
		'events'    => $events,
		'fields'    => array_merge(
			array(
				'name'      => $person['name'],
				'email'     => $person['email'],
				'profile'   => $person['username'],
				'country'   => '',
				'teacher'   => '',
				'languages' => array(),
				'expertise' => array(),
				'hours'     => '',
				'gpl'       => true,
				'anything'  => 'FREE-TEXT-SENTINEL ' . implode( ' / ', $person ),
				'consent'   => true,
			),
			isset( $facts['fields'] ) ? $facts['fields'] : array()
		),
		'consent'   => array(
			'box' => 'CONSENT-SENTINEL I confirm I have read the privacy policy, ' . $person['name'],
			'at'  => $ago( $days ),
			'ip'  => 'CONSENT-IP-SENTINEL',
		),
	);
}

/**
 * The invented mentors of the fixtures, by key, each with every field that holds a person, as
 * `readers_pupils()` gives its people. `gamma` is the mentor on the students' cards.
 *
 * @return array[] Key => the fields.
 */
function readers_mentors() {
	$person = static function ( $key ) {
		$title    = ucfirst( $key );
		$username = "mentor-$key-example";

		return array(
			'name'           => "Mentor Name $title",
			'display_name'   => "Mentor Display $title",
			'login'          => "mentor_login_$key",
			'nicename'       => "mentor-nicename-$key",
			'email'          => "Mentor-$title@example.test",
			'email_key'      => strtolower( "Mentor-$title@example.test" ),
			'username'       => $username,
			'profile'        => 'https://profiles.wordpress.org/' . $username . '/',
			'slack'          => "mentor-$key-slack",
			'github'         => "mentor-$key-github",
			'avatar'         => "https://example.test/avatar-mentor-$key.png",
			'jobline'        => "Jobline Mentor $title",
			'location'       => "Location Mentor $title",
			'website'        => "https://mentor-$key.example.test/",
			'website_label'  => "Website Label Mentor $title",
			'tutor'          => "Tutor Name Mentor $title",
			'mentor_name'    => "Mentor Name Peer $title",
			'field_of_study' => "Field of Study Mentor $title",
			'import_key'     => "import-key-mentor-$key",
			'accessibility'  => "Accessibility Answer Mentor $title",
		);
	};

	return array(
		'gamma'   => readers_pupils()['mentor'],
		'nu'      => $person( 'nu' ),
		'xi'      => $person( 'xi' ),
		'omicron' => $person( 'omicron' ),
	);
}

/**
 * An account, as core's get_user_by() gives one, for a person of `readers_pupils()` or `readers_mentors()`.
 *
 * A mentor's account is named by their WordPress.org username, as the mentors sync names the accounts it makes,
 * so a profile address finds it; a student's by the person's login.
 *
 * @param int   $id          The account's ID.
 * @param array $person      The person's fields.
 * @param bool  $by_username Whether the account's login is the person's WordPress.org username (a mentor's)
 *                           and not the person's login (a student's).
 * @return WP_User
 */
function readers_account( $id, array $person, $by_username = true ) {
	return new WP_User(
		$id,
		array(
			'user_login'    => $by_username ? $person['username'] : $person['login'],
			'user_email'    => $person['email'],
			'user_nicename' => $person['nicename'],
			'display_name'  => $person['display_name'],
		)
	);
}

/**
 * A student's row on a mentor's list, as the mentors sync writes one (`phase_reports()`): the Students
 * Reports record's ID, status and dates, and every field of the student, the keys the real row holds among them.
 *
 * @param string $who       A key of `readers_pupils()`.
 * @param string $record_id The Students Reports record ID ('' for a row without one).
 * @param array  $facts     The row's own keys: status, is_past, start, end, hours.
 * @return array
 */
function readers_mentee( $who, $record_id, array $facts = array() ) {
	return array_merge(
		readers_pupils()[ $who ],
		array(
			'record_id'   => $record_id,
			'status'      => 'In Sensei',
			'is_past'     => false,
			'start'       => '',
			'end'         => '',
			'institution' => 'University of Example',
			'team'        => '',
			'hours'       => '',
			'link'        => 'https://example.test/report/' . $record_id,
			'mentor_ids'  => array( 'recMENT0000000001' ),
		),
		$facts
	);
}

/**
 * A mentor's row of the Engagement tab's report, as `WPCPM_Mentor_Engagement::compute()` gives one: every key
 * the real row holds, the mentor's name, login (the WordPress.org username the account is named by) and
 * address among them.
 *
 * @param int   $id     The account's ID.
 * @param array $person The mentor's fields.
 * @param array $facts  The row's own keys that differ from a mentor who has done nothing.
 * @return array
 */
function readers_engagement_row( $id, array $person, array $facts = array() ) {
	return array_merge(
		array(
			'id'                  => $id,
			'name'                => $person['display_name'],
			'login'               => $person['username'],
			'email'               => $person['email'],
			'active'              => true,
			'staff'               => false,
			'students_now'        => 0,
			'students_past'       => 0,
			'last_sign_in'        => 0,
			'last_visit'          => 0,
			'last_seen'           => 0,
			'seen_7'              => false,
			'seen_30'             => false,
			'schedule_saved'      => false,
			'weekly_hours'        => false,
			'meeting_link'        => false,
			'notes'               => 0,
			'notes_30'            => 0,
			'last_note'           => 0,
			'calls_booked'        => 0,
			'calls_held'          => 0,
			'calls_upcoming'      => 0,
			'calls_noted'         => 0,
			'cancelled_by_mentor' => 0,
			'sessions_created'    => 0,
			'sessions_held'       => 0,
			'sessions_upcoming'   => 0,
			'sessions_noted'      => 0,
			'acted'               => false,
		),
		$facts
	);
}

/**
 * The invented people of the roster fixtures, by key, each with every field that holds a person: the
 * name, the account's display name, login and nicename, the address and its key, the WordPress.org
 * username and profile address, the Slack and GitHub handles, the avatar, jobline, location, website and
 * its label, the tutor's and the mentor's names, the field of study, the import key and the accessibility
 * answer. `mentor` is the mentor on the accounts' cards. `gamma` has no address, so each of its rows is a
 * person of its own.
 *
 * @return array[] Key => the fields.
 */
function readers_pupils() {
	$person = static function ( $key, $email, $tutor, $mentor ) {
		$title    = ucfirst( $key );
		$username = "pupil-$key-example";

		return array(
			'name'           => "Pupil Name $title",
			'display_name'   => "Pupil Display $title",
			'login'          => "pupil_login_$key",
			'nicename'       => "pupil-nicename-$key",
			'email'          => $email,
			'email_key'      => strtolower( $email ),
			'username'       => $username,
			'profile'        => 'https://profiles.wordpress.org/' . $username . '/',
			'slack'          => "pupil-$key-slack",
			'github'         => "pupil-$key-github",
			'avatar'         => "https://example.test/avatar-pupil-$key.png",
			'jobline'        => "Jobline Pupil $title",
			'location'       => "Location Pupil $title",
			'website'        => "https://pupil-$key.example.test/",
			'website_label'  => "Website Label Pupil $title",
			'tutor'          => $tutor,
			'mentor_name'    => $mentor,
			'field_of_study' => "Field of Study Pupil $title",
			'import_key'     => "import-key-pupil-$key",
			'accessibility'  => "Accessibility Answer Pupil $title",
		);
	};

	return array(
		'alpha'   => $person( 'alpha', 'Pupil-Alpha@Example.test', 'Tutor Name Beta', 'Mentor Name Gamma' ),
		'beta'    => $person( 'beta', 'pupil-beta@example.test', 'Tutor Name Delta', 'Mentor Name Eta' ),
		'gamma'   => $person( 'gamma', '', 'Tutor Name Zeta', 'Mentor Name Theta' ),
		'delta'   => $person( 'delta', 'pupil-delta@example.test', 'Tutor Name Iota', 'Mentor Name Kappa' ),
		'epsilon' => $person( 'epsilon', 'PUPIL-EPSILON@example.test', 'Tutor Name Lambda', 'Mentor Name Gamma' ),
		'mentor'  => array(
			'name'           => 'Mentor Name Gamma',
			'display_name'   => 'Mentor Display Gamma',
			'login'          => 'mentor_login_gamma',
			'nicename'       => 'mentor-nicename-gamma',
			'email'          => 'Mentor-Gamma@example.test',
			'email_key'      => 'mentor-gamma@example.test',
			'username'       => 'mentor-gamma-example',
			'profile'        => 'https://profiles.wordpress.org/mentor-gamma-example/',
			'slack'          => 'mentor-gamma-slack',
			'github'         => 'mentor-gamma-github',
			'avatar'         => 'https://example.test/avatar-mentor-gamma.png',
			'jobline'        => 'Jobline Mentor Gamma',
			'location'       => 'Location Mentor Gamma',
			'website'        => 'https://mentor-gamma.example.test/',
			'website_label'  => 'Website Label Mentor Gamma',
			'tutor'          => 'Tutor Name Mu',
			'mentor_name'    => 'Mentor Name Nu',
			'field_of_study' => 'Field of Study Mentor Gamma',
			'import_key'     => 'import-key-mentor-gamma',
			'accessibility'  => 'Accessibility Answer Mentor Gamma',
		),
	);
}

/**
 * One Students row of the roster index, for a fixture person: every key the index holds, the person's
 * fields on it (and every other field of the person besides, which the index never holds), and the track's.
 *
 * @param string $who         A key of `readers_pupils()`.
 * @param string $record_id   The Students record ID.
 * @param string $institution The Institutions record ID, or '' for a row that names none.
 * @param array  $track       The track's own keys: status, start, end, has_mentor, team, hours, reports, user_id.
 * @return array
 */
function readers_track( $who, $record_id, $institution, array $track ) {
	$person = readers_pupils()[ $who ];

	return array_merge(
		$person,
		array(
			'record_id'   => $record_id,
			'status'      => '',
			'institution' => $institution,
			'start'       => '',
			'end'         => '',
			'has_mentor'  => false,
			'team'        => '',
			'hours'       => '',
			'reports'     => array(),
			'user_id'     => 0,
			'touched'     => 0,
		),
		$track
	);
}

/**
 * The mentor card the students sync keeps on a student's account (`WPCPM_Students_Sync::META_MENTOR`),
 * with every field of the fixture mentor on it.
 *
 * @param string $record_id The Mentors record ID the card holds; '' for a card without one.
 * @return array
 */
function readers_mentor_card( $record_id ) {
	$mentor = readers_pupils()['mentor'];

	return array(
		'record_id'     => $record_id,
		'name'          => $mentor['name'],
		'email'         => $mentor['email'],
		'username'      => $mentor['username'],
		'profile'       => $mentor['profile'],
		'slack'         => $mentor['slack'],
		'jobline'       => $mentor['jobline'],
		'location'      => $mentor['location'],
		'website'       => $mentor['website'],
		'website_label' => $mentor['website_label'],
		'github'        => $mentor['github'],
		'teams'         => array( 'Polyglots' ),
		'avatar'        => $mentor['avatar'],
	);
}

/**
 * The program row the students sync keeps on a student's account (`WPCPM_Students_Sync::META_PROGRAM`):
 * every field of the person, the accessibility answer among them. No answer reads it.
 *
 * @param string $who A key of `readers_pupils()`.
 * @return array
 */
function readers_program_row( $who ) {
	return array_merge(
		readers_pupils()[ $who ],
		array(
			'record_id' => 'recREPT0000000099',
			'program'   => 'In Sensei',
		)
	);
}

/**
 * Every invented person-shaped string the fixtures hold: names, addresses, logins and the words of a
 * sync's error. None of them may appear in any answer.
 *
 * @return string[]
 */
function readers_people() {
	return array(
		'Contact Person Zeta',
		'contact-zeta@example.test',
		'Acceptor Name Omega',
		'Student Name Sigma',
		'student-sigma@example.test',
		'student_sigma',
		'Mentor Name Tau',
		'Member Name Upsilon',
		'member_login_one',
		'member-one@example.test',
		'member_login_three',
		'Mentor Name Phi',
		'mentor-phi@example.test',
		'mentor_phi',
		'Sponsor Contact Chi',
		'Token rejected for mentor-one',
		'Applicant Name Alpha',
		'applicant-alpha@example.test',
		'applicant-alpha-example',
		'Applicant Name Theta',
		'applicant-theta@example.test',
		'applicant-theta-example',
		'FREE-TEXT-SENTINEL',
		'NOTE-SENTINEL',
		'CONSENT-SENTINEL',
		'CONSENT-IP-SENTINEL',
	);
}

/** Note one reader call. */
function readers_note( $reader, array $args = array() ) {
	$GLOBALS['reader_calls'][] = array_merge( array( $reader ), $args );
}

/* ---- The readers ----------------------------------------------------------------- */

class WPCPM_Administrators_Cards {
	public static function tracks() { return $GLOBALS['readers']['tracks']; }
	public static function programs() { readers_note( 'programs' ); return $GLOBALS['readers']['programs']; }
	public static function sponsors() { readers_note( 'sponsors' ); return $GLOBALS['readers']['sponsors']; }
	public static function collect() { readers_note( 'collect' ); return array( 'collected' => true ); }
	public static function counts( array $data ) {
		readers_note( 'counts', array( $data ) );
		return $GLOBALS['readers']['tiles'];
	}
	public static function health() {
		readers_note( 'health' );
		if ( ! empty( $GLOBALS['readers']['throw'] ) ) { throw new RuntimeException( $GLOBALS['readers']['throw'] ); }
		return $GLOBALS['readers']['health'];
	}
	public static function sync_needs_look( array $sync, bool $auto ): bool {
		readers_note( 'needs_look', array( isset( $sync['label'] ) ? $sync['label'] : '', $auto ) );
		$by_label = isset( $sync['label'], $GLOBALS['readers']['needs_look'][ $sync['label'] ] ) ? $GLOBALS['readers']['needs_look'][ $sync['label'] ] : array();
		return ! empty( $by_label[ $auto ? 'on' : 'off' ] );
	}
	public static function figures_need_look(): bool {
		readers_note( 'figures_look' );
		return '' !== $GLOBALS['readers']['figures_look'];
	}
}

class WPCPM_Administrators_Dashboard {
	public static function page_url() { return $GLOBALS['readers']['page_url']; }
}

class WPCPM_Mentor_Engagement {
	const VIEW_ALL      = 'all';
	const VIEW_ACTIVE   = 'active';
	const VIEW_STUDENTS = 'students';
	const VIEW_NEVER    = 'never';
	const VIEW_QUIET    = 'quiet';
	const VIEW_IDLE     = 'idle';
	const VIEW_NO_HOURS = 'no-hours';
	const VIEW_STAFF    = 'staff';

	public static function report( $now = 0 ) {
		readers_note( 'engagement', array( $now ) );
		return $GLOBALS['readers']['engagement'];
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function views() {
		return array(
			self::VIEW_ALL      => __( 'All', 'wpcredits-program-manager' ),
			self::VIEW_ACTIVE   => __( 'Active', 'wpcredits-program-manager' ),
			self::VIEW_STUDENTS => __( 'With students now', 'wpcredits-program-manager' ),
			self::VIEW_NEVER    => __( 'Never signed in', 'wpcredits-program-manager' ),
			self::VIEW_QUIET    => __( 'Not seen in 30 days', 'wpcredits-program-manager' ),
			self::VIEW_IDLE     => __( 'No program action', 'wpcredits-program-manager' ),
			self::VIEW_NO_HOURS => __( 'Students, no weekly hours', 'wpcredits-program-manager' ),
			self::VIEW_STAFF    => __( 'Administrators', 'wpcredits-program-manager' ),
		);
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function in_view( array $row, $view ) {
		$active = $row['active'] && ! $row['staff'];

		switch ( (string) $view ) {
			case self::VIEW_ACTIVE:
				return $active;
			case self::VIEW_STUDENTS:
				return $active && $row['students_now'] > 0;
			case self::VIEW_NEVER:
				return $active && 0 === $row['last_seen'];
			case self::VIEW_QUIET:
				return $active && $row['last_seen'] > 0 && ! $row['seen_30'];
			case self::VIEW_IDLE:
				return $active && ! $row['acted'];
			case self::VIEW_NO_HOURS:
				return $active && $row['students_now'] > 0 && ! $row['weekly_hours'];
			case self::VIEW_STAFF:
				return $row['active'] && $row['staff'];
			default:
				return true;
		}
	}
}

class WPCPM_Institutions_Index {
	public static function read() { return array( 'v' => 1, 'read' => $GLOBALS['readers']['institutions']['read'], 'rows' => $GLOBALS['readers']['institutions']['rows'] ); }
	public static function rows() { return $GLOBALS['readers']['institutions']['rows']; }
	public static function row( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) { return null; }
		$rows = self::rows();
		return isset( $rows[ trim( (string) $record_id ) ] ) ? $rows[ trim( (string) $record_id ) ] : null;
	}
	public static function by_stage() {
		$groups = array();
		foreach ( self::rows() as $row ) {
			$groups[ (string) $row['stage'] ][ $row['record_id'] ] = $row;
		}
		return $groups;
	}
}

class WPCPM_Roster_Index {
	const NEVER_SHOWN = array( 'SPAM', 'Duplicated' );
	public static function read( $record_id ) {
		readers_note( 'roster', array( $record_id ) );
		$id = trim( (string) $record_id );
		$rows = WPCPM_Mentors_Sync::is_record_id( $id ) && isset( $GLOBALS['readers']['roster'][ $id ] ) ? $GLOBALS['readers']['roster'][ $id ] : array();
		return array( 'v' => 4, 'read' => $GLOBALS['readers']['roster_read'], 'rows' => $rows );
	}
	public static function rows( $record_id ) { return self::read( $record_id )['rows']; }
	public static function unlinked() {
		readers_note( 'unlinked' );
		return $GLOBALS['readers']['unlinked'];
	}
	public static function groups( $record_id, $cohort = '' ) {
		readers_note( 'groups', array( $record_id, $cohort ) );
		$key = WPCPM_Cohort::is_key( $cohort ) ? $cohort : 'all';
		if ( isset( $GLOBALS['readers']['groups'][ $record_id ][ $key ] ) ) {
			return $GLOBALS['readers']['groups'][ $record_id ][ $key ];
		}
		return array( 'current' => array(), 'waiting' => array(), 'finished' => array(), 'not_started' => array() );
	}
	public static function counts() {
		// The institutions the last sync wrote a roster for, the only list of rosters the site has.
		return array( 'v' => 4, 'read' => $GLOBALS['readers']['roster_read'], 'institutions' => array_fill_keys( array_keys( $GLOBALS['readers']['roster'] ), array() ), 'reconciliation' => array() );
	}
}

/** An account, as core's get_user_by() gives one: the fields a lookup of a mentor reads, and no more. */
class WP_User {
	public $ID            = 0;
	public $user_login    = '';
	public $user_email    = '';
	public $user_nicename = '';
	public $display_name  = '';

	public function __construct( $id = 0, array $fields = array() ) {
		$this->ID = (int) $id;

		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}

/** Core's get_user_by(), over the fixtures' accounts: by ID, by login or by address, in any case. */
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['readers']['accounts'] as $user ) {
		if ( 'id' === $field && (int) $value === $user->ID ) { return $user; }
		if ( 'login' === $field && '' !== $user->user_login && strtolower( (string) $value ) === strtolower( $user->user_login ) ) { return $user; }
		if ( 'email' === $field && '' !== $user->user_email && strtolower( (string) $value ) === strtolower( $user->user_email ) ) { return $user; }
	}

	return false;
}

/** Core's is_email(), as far as the fixtures' addresses reach. */
function is_email( $email ) {
	return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

/**
 * Core's get_users(), as far as a lookup by one meta value reaches: the accounts holding the key with that
 * value, as the rows this site's stack gives for `'fields' => 'ID'` (`stdClass`, never a bare number) and as
 * accounts for anything else. The query is noted, so a suite can say that it was asked once, and what for.
 */
function get_users( $args = array() ) {
	readers_note( 'get_users', array( isset( $args['meta_key'] ) ? $args['meta_key'] : '' ) );
	$found = array();

	foreach ( $GLOBALS['readers']['accounts'] as $user ) {
		$meta = isset( $GLOBALS['readers']['user_meta'][ $user->ID ] ) ? $GLOBALS['readers']['user_meta'][ $user->ID ] : array();

		// The database compares meta under its own collation, which does not tell recABC from recabc, as the
		// real queries are checked again in PHP for: the stand-in compares the same way, so that a lookup that
		// forgets the second look finds an account it should not.
		if ( isset( $args['meta_key'] ) && ( ! isset( $meta[ $args['meta_key'] ] ) || 0 !== strcasecmp( (string) $meta[ $args['meta_key'] ], (string) $args['meta_value'] ) ) ) {
			continue;
		}

		$found[] = isset( $args['fields'] ) && 'ID' === $args['fields'] ? (object) array( 'ID' => $user->ID ) : $user;
	}

	return isset( $args['number'] ) && (int) $args['number'] > 0 ? array_slice( $found, 0, (int) $args['number'] ) : $found;
}

class WPCPM_Mentors_Dashboard {
	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function find_mentor( $needle ) {
		$needle = is_scalar( $needle ) ? (string) $needle : '';

		$user = get_user_by( 'login', $needle );

		if ( ! $user && is_email( $needle ) ) {
			$user = get_user_by( 'email', $needle );
		}

		if ( ! $user ) {
			$login = WPCPM_Mentors_Sync::typed_username( $needle );
			$user  = $login ? get_user_by( 'login', $login ) : false;
		}

		return $user instanceof WP_User ? $user : null;
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function get_mentees( $user_id ) {
		$mentees = get_user_meta( (int) $user_id, WPCPM_Mentors_Sync::META_MENTEES, true );

		return is_array( $mentees ) ? $mentees : array();
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function logged_count( array $mentee, $student = false ) {
		if ( false === $student ) {
			$record  = isset( $mentee['record_id'] ) && is_scalar( $mentee['record_id'] ) ? trim( (string) $mentee['record_id'] ) : '';
			$student = '' !== $record ? WPCPM_Students_Sync::user_for_record( $record ) : null;
		}

		$program = $student instanceof WP_User ? get_user_meta( $student->ID, WPCPM_Students_Sync::META_PROGRAM, true ) : array();
		$read    = static function ( $block, $key ) {
			return ( is_array( $block ) && isset( $block[ $key ] ) && is_scalar( $block[ $key ] ) ) ? trim( (string) $block[ $key ] ) : '';
		};

		return '' !== $read( $program, 'hours' ) ? $read( $program, 'hours' ) : $read( $mentee, 'hours' );
	}
}

class WPCPM_Students_Sync {
	const META_RECORD_ID = 'wpcpm_student_record_id';
	const META_PROGRAM   = 'wpcpm_student_program';
	const META_MENTOR    = 'wpcpm_student_mentor';
	const META_COURSE    = 'wpcpm_student_course';

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function user_for_record( $record_id ) {
		$record_id = is_string( $record_id ) ? trim( $record_id ) : '';

		return '' === $record_id ? null : self::find_by_record_id( $record_id );
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	private static function find_by_record_id( $record_id ) {
		$users = get_users(
			array(
				'meta_key'   => self::META_RECORD_ID,
				'meta_value' => $record_id,
				'number'     => 1,
			)
		);

		return ! empty( $users[0] ) ? $users[0] : null;
	}
}

class WPCPM_Program {
	const STATUS_PENDING = 'Pending graduation';

	// Whether the site runs a track for the status: the fixtures' list stands in for the published tracks.
	public static function is_track( $status ) {
		return in_array( trim( (string) $status ), $GLOBALS['readers']['tracks_run'], true );
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function course_status( $status, $user_id = 0 ) {
		$status = trim( (string) $status );

		if ( self::is_track( $status ) ) {
			return $status;
		}

		if ( self::STATUS_PENDING !== $status || (int) $user_id < 1 ) {
			return '';
		}

		$kept = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_COURSE, true );
		$kept = is_scalar( $kept ) ? trim( (string) $kept ) : '';

		return self::is_track( $kept ) ? $kept : '';
	}
}

/**
 * Core's get_user_meta(), over the fixtures' account meta: '' for a key an account does not hold, as
 * core gives it for a single value.
 */
function get_user_meta( $user_id, $key = '', $single = false ) {
	readers_note( 'user_meta', array( (int) $user_id, $key ) );
	$value = isset( $GLOBALS['readers']['user_meta'][ (int) $user_id ][ $key ] ) ? $GLOBALS['readers']['user_meta'][ (int) $user_id ][ $key ] : '';
	return $single ? $value : ( '' === $value ? array() : array( $value ) );
}

class WPCPM_Institution_Members {
	public static function members_of( $record_id ) {
		readers_note( 'members_of', array( $record_id ) );
		return isset( $GLOBALS['readers']['members'][ $record_id ] ) ? $GLOBALS['readers']['members'][ $record_id ] : array();
	}
}

class WPCPM_Sponsors_Index {
	const STATUS_APPROVED = 'Approved';
	public static function rows() { return $GLOBALS['readers']['sponsor_rows']; }
	public static function is_test( $sponsor ) { return is_array( $sponsor ) && ! empty( $sponsor['test'] ); }
}

/**
 * A time the mentor application fixtures were made at, for a post: core's get_post_time(), over the fixtures.
 * Defined only when the suite has no such function of its own.
 */
if ( ! function_exists( 'get_post_time' ) ) {
	function get_post_time( $format = 'U', $gmt = false, $post = null, $translate = false ) {
		return is_object( $post ) && isset( $GLOBALS['readers']['applications'][ $post->ID ] ) ? $GLOBALS['readers']['applications'][ $post->ID ]['arrived'] : false;
	}
}

/** The site's countries, record ID => name: the fixtures' list, which a suite may empty (`form_countries`). */
class WPCPM_Countries {
	public static function options() {
		return $GLOBALS['readers']['form_countries'];
	}
}

/** The site's contribution teams, record ID => name: the fixtures' list, which a suite may empty (`form_teams`). */
class WPCPM_Contribution_Teams {
	public static function options() {
		return $GLOBALS['readers']['form_teams'];
	}
}

/**
 * The form's marks of an application, as the queue reads them. bin/test-ability-answers.php checks each
 * constant against the real class.
 */
class WPCPM_Mentor_Application_Form {
	const SIGNAL_DUPLICATE    = 'duplicate';
	const SIGNAL_MENTOR       = 'mentor';
	const SIGNAL_HONEYPOT     = 'honeypot';
	const SIGNAL_DWELL        = 'dwell';
	const SIGNAL_MAIL_CEILING = 'mail-ceiling';
	const SIGNAL_MAIL_FAILED  = 'mail-failed';

	/** The languages a mentor can mentor in: the real class's fixed list, which bin/test-ability-answers.php checks name for name. */
	public static function languages() {
		return array(
			'Bengali',
			'Catalan',
			'Croatian',
			'Egyptian Arabic',
			'English',
			'Finnish',
			'French',
			'German',
			'Gujarati',
			'Hausa',
			'Hindi',
			'Indonesian',
			'Iranian Persian',
			'Italian',
			'Japanese',
			'Javanese',
			'Korean',
			'Levantine Arabic',
			'Mandarin Chinese',
			'Marathi',
			'Modern Standard Arabic',
			'Nepali',
			'Nigerian Pidgin',
			'Polish',
			'Portuguese',
			'Russian',
			'Spanish',
			'Swahili',
			'Tagalog (Filipino)',
			'Tamil',
			'Telugu',
			'Thai',
			'Turkish',
			'Urdu',
			'Vietnamese',
			'Western Punjabi',
			'Wu Chinese (Shanghainese)',
			'Yue Chinese (Cantonese)',
		);
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function countries() {
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', WPCPM_Countries::options() ) ), 'strlen' ) ) );
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function teams() {
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', WPCPM_Contribution_Teams::options() ) ), 'strlen' ) ) );
	}
}

/**
 * The mentor application, as far as the answers read it: the states, the post meta keys and the accessors
 * for the state, the answers, the reference, whether the address is confirmed, what the checks raised and
 * the history's times. Each accessor notes the post meta key it stands for, the way core's get_user_meta()
 * stand-in does for an account's, so a suite can say which meta an answer reads; bin/test-ability-answers.php
 * checks the constants and the keys against the real class. The posts are the rows of
 * `$GLOBALS['readers']['applications']`, handed out as objects that hold the ID alone.
 */
class WPCPM_Mentor_Application {
	const POST_TYPE = 'wpcpm_ment_app';

	const STATE_NEW      = 'new';
	const STATE_HELD     = 'held';
	const STATE_SPAM     = 'spam';
	const STATE_INFO     = 'info';
	const STATE_SECOND   = 'second';
	const STATE_SELECTED = 'selected';
	const STATE_ACTIVE   = 'active';
	const STATE_DECLINED = 'declined';
	const STATE_LAPSED   = 'lapsed';

	const META_FIELDS    = '_wpcpm_mapp_fields';
	const META_STATE     = '_wpcpm_mapp_state';
	const META_REFERENCE = '_wpcpm_mapp_reference';
	const META_CONSENT   = '_wpcpm_mapp_consent';
	const META_SIGNALS   = '_wpcpm_mapp_signals';
	const META_VERIFIED  = '_wpcpm_mapp_verified';
	const META_EVENT     = '_wpcpm_mapp_event';

	const EVENT_SUBMITTED             = 'submitted';
	const EVENT_SELECTED              = 'selected';
	const EVENT_INFO                  = 'information requested';
	const EVENT_SECOND                = 'second opinion';
	const EVENT_DECLINED              = 'declined';
	const EVENT_SPAM                  = 'marked as spam';
	const EVENT_WITHDRAWN             = 'withdrawn';
	const EVENT_REOPENED              = 'put back in the queue';
	const EVENT_LAPSED                = 'lapsed';
	const EVENT_ACTIVATED             = 'activated';
	const EVENT_REOPENED_ACKNOWLEDGED = 'put back in the queue, acknowledgement sent';

	public static function states() {
		return array( self::STATE_NEW, self::STATE_HELD, self::STATE_SPAM, self::STATE_INFO, self::STATE_SECOND, self::STATE_SELECTED, self::STATE_ACTIVE, self::STATE_DECLINED, self::STATE_LAPSED );
	}

	public static function open_states() {
		return array( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO, self::STATE_SECOND );
	}

	/** Oldest first and by ID within one second, as the real query orders them. While `applications_leak` is set, the states asked for are ignored, as a state changed after the query would be. */
	public static function applications( array $states, $limit = 0 ) {
		readers_note( 'post_query', array( self::META_STATE ) );
		$wanted = array_values( array_intersect( self::states(), array_map( 'strval', $states ) ) );
		$rows   = array();

		foreach ( $GLOBALS['readers']['applications'] as $row ) {
			if ( ! empty( $GLOBALS['readers']['applications_leak'] ) || in_array( $row['state'], $wanted, true ) ) {
				$rows[] = $row;
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return array( $a['arrived'], $a['ID'] ) <=> array( $b['arrived'], $b['ID'] );
			}
		);

		$rows = $limit > 0 ? array_slice( $rows, 0, (int) $limit ) : $rows;

		return array_map(
			static function ( $row ) {
				return (object) array( 'ID' => $row['ID'] );
			},
			$rows
		);
	}

	public static function count_in( array $states ) {
		readers_note( 'post_query', array( self::META_STATE ) );
		$wanted = array_values( array_intersect( self::states(), array_map( 'strval', $states ) ) );

		return count(
			array_filter(
				$GLOBALS['readers']['applications'],
				static function ( $row ) use ( $wanted ) {
					return in_array( $row['state'], $wanted, true );
				}
			)
		);
	}

	public static function state_of( $post ) {
		readers_note( 'post_meta', array( $post->ID, self::META_STATE ) );
		return (string) $GLOBALS['readers']['applications'][ $post->ID ]['state'];
	}

	public static function fields_of( $post ) {
		readers_note( 'post_meta', array( $post->ID, self::META_FIELDS ) );
		return $GLOBALS['readers']['applications'][ $post->ID ]['fields'];
	}

	public static function reference_of( $post ) {
		readers_note( 'post_meta', array( $post->ID, self::META_REFERENCE ) );
		return (string) $GLOBALS['readers']['applications'][ $post->ID ]['reference'];
	}

	public static function is_confirmed( $post_id ) {
		readers_note( 'post_meta', array( (int) $post_id, self::META_VERIFIED ) );
		return ! empty( $GLOBALS['readers']['applications'][ (int) $post_id ]['verified'] );
	}

	public static function signals_of( $post ) {
		readers_note( 'post_meta', array( $post->ID, self::META_SIGNALS ) );
		$stored = $GLOBALS['readers']['applications'][ $post->ID ]['signals'];

		return is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'strval', $stored ), 'strlen' ) ) ) : array();
	}

	public static function last_at( $post_id, $event = '' ) {
		readers_note( 'post_meta', array( (int) $post_id, self::META_EVENT ) );
		$latest = 0;

		foreach ( $GLOBALS['readers']['applications'][ (int) $post_id ]['events'] as $row ) {
			if ( ( '' === $event || $event === $row['event'] ) && $row['at'] > $latest ) {
				$latest = $row['at'];
			}
		}

		return $latest;
	}

	/** When a lapsed row was decided: the lapse or the withdrawal, else the day it arrived. */
	public static function decided_at( $post ) {
		readers_note( 'post_meta', array( $post->ID, self::META_EVENT ) );
		$closed = max( self::last_at( $post->ID, self::EVENT_LAPSED ), self::last_at( $post->ID, self::EVENT_WITHDRAWN ) );

		return $closed > 0 ? $closed : (int) $GLOBALS['readers']['applications'][ $post->ID ]['arrived'];
	}
}

class WPCPM_Mentor_Application_Feed {
	/** The whole feed, which the real class builds from every application's history; `today()` is its `today`. */
	public static function build( $now = 0 ) {
		readers_note( 'feed_build' );
		return array( 'months' => new stdClass(), 'today' => self::today(), 'generated' => '2026-10-24T00:00:00+00:00' );
	}

	/** The two numbers of today, which the real class counts from the posts' states alone. */
	public static function today() {
		readers_note( 'feed_today' );
		return $GLOBALS['readers']['feed_today'];
	}
}

class WPCPM_Outcome_Figures {
	public static function published() { return $GLOBALS['readers']['published']; }
}

class WPCPM_Mentors_Sync {
	const META_RECORD_ID = 'wpcpm_mentor_record_id';
	const META_ACTIVE    = 'wpcpm_mentor_active';
	const META_MENTEES   = 'wpcpm_mentees';
	const META_UPDATED   = 'wpcpm_mentees_updated';

	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function wporg_username( $raw ) {
		$value = trim( (string) $raw );

		if ( '' === $value ) {
			return '';
		}

		$value = trim( $value, " \t\n\r\0\x0B<>" );
		$value = ltrim( $value, '@' );

		// Drop the scheme, then any query string or fragment.
		$value = preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $value );
		$value = preg_split( '/[?#]/', $value )[0];

		if ( false !== strpos( $value, '/' ) ) {
			$parts = array_values( array_filter( explode( '/', $value ), 'strlen' ) );

			// The first segment is the host when it looks like a domain.
			if ( ! empty( $parts ) && false !== strpos( $parts[0], '.' ) ) {
				array_shift( $parts );
			}

			// Trailing BuddyPress components are not part of the username.
			$parts = array_values(
				array_diff( $parts, array( 'profile', 'profiles', 'activity', 'badges', 'notifications', 'messages' ) )
			);

			$value = empty( $parts ) ? '' : end( $parts );
		}

		// WordPress.org usernames allow letters, numbers, dashes, dots, underscores.
		$value = preg_replace( '/[^A-Za-z0-9._\-]/', '', $value );

		return strtolower( $value );
	}

	// The real class's own, line for line: bin/test-ability-answers.php checks the two bodies are the same.
	public static function typed_username( $raw ) {
		$typed = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		$login = self::wporg_username( $typed );

		if ( '' === $login ) {
			return '';
		}

		if ( strtolower( ltrim( $typed, '@' ) ) === $login ) {
			return $login;
		}

		return 1 === preg_match( '#(?:^|/)@?' . preg_quote( $login, '#' ) . '(?:[/?\#]|$)#i', $typed ) ? $login : '';
	}
}
