<?php
/**
 * The readers the four Claude access answers lean on, as fixed fixtures.
 *
 * `WPCPM_Ability_Answers` builds each answer out of readers the plugin already has: the Administrator
 * Dashboard's cards, the Engagement tab's report, the institutions and roster indexes, the sponsors
 * store, the mentor application feed and the published outcome figures. A suite that stands the
 * readers in with these classes can say exactly what each answer must hold, and, because the fixtures
 * carry people (a contact, a student, a mentor, a member, a sponsor's contact, a sync's error text),
 * can show that none of them reaches an answer.
 *
 * Every shape here is the shape the real reader returns, read from the reader's own docblock and code
 * on this branch; `bin/test-ability-answers.php` checks that each method still exists there. Every
 * name, address and login is invented, every address is on `example.test`, and every record ID is
 * `rec` and fourteen characters with no lowercase letter, so the release scan passes it.
 *
 * Loaded with `require_once __DIR__ . '/stubs/ability-readers.php';` after `ABSPATH` is defined and
 * the real `WPCPM_Cohort` is loaded. `readers_reset()` puts the fixtures back; a suite changes one by
 * writing into `$GLOBALS['readers']`, and reads what the readers were asked from `$GLOBALS['reader_calls']`.
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
	public static function report( $now = 0 ) {
		readers_note( 'engagement', array( $now ) );
		return $GLOBALS['readers']['engagement'];
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
	public static function groups( $record_id, $cohort = '' ) {
		readers_note( 'groups', array( $record_id, $cohort ) );
		$key = WPCPM_Cohort::is_key( $cohort ) ? $cohort : 'all';
		if ( isset( $GLOBALS['readers']['groups'][ $record_id ][ $key ] ) ) {
			return $GLOBALS['readers']['groups'][ $record_id ][ $key ];
		}
		return array( 'current' => array(), 'waiting' => array(), 'finished' => array(), 'not_started' => array() );
	}
	public static function counts() {
		return array( 'v' => 4, 'read' => $GLOBALS['readers']['roster_read'], 'institutions' => array(), 'reconciliation' => array() );
	}
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

class WPCPM_Mentor_Application_Feed {
	public static function build( $now = 0 ) {
		return array( 'months' => new stdClass(), 'today' => $GLOBALS['readers']['feed_today'], 'generated' => '2026-10-24T00:00:00+00:00' );
	}
}

class WPCPM_Outcome_Figures {
	public static function published() { return $GLOBALS['readers']['published']; }
}

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
	}
}
