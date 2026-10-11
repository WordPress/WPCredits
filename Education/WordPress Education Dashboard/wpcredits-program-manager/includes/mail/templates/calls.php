<?php
/**
 * The Mentor calls area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one `WPCPM_Mentor_Calls` wrote before 1.122.21, under the same
 * translatable string, but the counted ones: a subject or a first line that said "3 sessions" with
 * `_n()` around the whole sentence now takes the counted words as one value (`{session_count}`,
 * `{group_session_count}`, `{date_count}`), worked out with `_n()` where the email is sent, so the
 * words around them can be edited.
 *
 * One catalog id sent each of these until 1.122.21 to the mentor and to the students alike, about
 * a one-to-one call and a group session alike; each email that says different words is a template
 * of its own now. A mentor who has not set a meeting link is told so where the others are told
 * where to meet, so the mentor's three confirmations each have a second template for that case.
 *
 * Every time is the recipient's, on their own clock, as the code always wrote it: `{when}` and the
 * other times are worked out where the email is sent, and `{time_zone}` names that clock.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An example call for a preview and a test: tomorrow at 3 pm, half an hour long, on the clock of the
// Administrator looking at it, so the times read as a real email to them would. A later session is
// whole days on from it on that clock, as a real series is, so it keeps its clock time across a change
// of summer time and not an hour off.
$wpcpm_example = static function ( $days = 0 ) {
	$zone  = WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() );
	$start = ( new DateTimeImmutable( 'tomorrow 15:00', $zone ) )->modify( '+' . (int) $days . ' days' )->getTimestamp();

	return array(
		'start' => $start,
		'end'   => $start + 30 * MINUTE_IN_SECONDS,
		'zone'  => $zone,
	);
};

// Every placeholder an email of the area may have, for an email to one audience: what each means and
// its example, a value or a function that works one out when a preview or a test asks for it. An
// email takes the ones it uses (`$wpcpm_take`).
$wpcpm_placeholders = static function ( $audience ) use ( $wpcpm_example ) {
	return array(
		'site_name'           => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'student_name'        => array(
			'means'   => __( 'The student\'s name; "your student" when the call holds none.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'mentor_name'         => array(
			'means'   => __( 'The mentor\'s name.', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'with_whom'           => array(
			'means'   => __( 'Who the session is with: the mentor\'s name, or, in the mentor\'s copy, how many students are coming, such as "3 students".', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'when'                => array(
			'means'   => __( 'When the call is, from its start to its end, on the recipient\'s own clock. Every email about a call needs it.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Calls::format_range( $call['start'], $call['end'], $call['zone'] );
			},
		),
		'was_when'            => array(
			'means'   => __( 'When the session was before it moved, on the recipient\'s own clock.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example( 1 );

				return WPCPM_Mentor_Calls::format_range( $call['start'], $call['end'], $call['zone'] );
			},
		),
		'call_date'           => array(
			'means'   => __( 'The day of the call, on the recipient\'s own clock.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return wp_date( get_option( 'date_format' ), $call['start'], $call['zone'] );
			},
		),
		'session_dates'       => array(
			'means'   => __( 'When each session is, one to a line, on the recipient\'s own clock. The email needs them.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$dates = array();

				foreach ( array( 0, 7, 14 ) as $days ) {
					$call    = $wpcpm_example( $days );
					$dates[] = WPCPM_Mentor_Calls::format_range( $call['start'], $call['end'], $call['zone'] );
				}

				return implode( "\n", $dates );
			},
		),
		'session_count'       => array(
			'means'   => __( 'How many sessions the student joined, counted: "3 sessions".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %d: how many sessions. */
					_n( '%d session', '%d sessions', 3, 'wpcredits-program-manager' ),
					3
				);
			},
		),
		'group_session_count' => array(
			'means'   => __( 'How many group sessions the student joined, counted: "3 group sessions".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %d: how many sessions. */
					_n( '%d group session', '%d group sessions', 3, 'wpcredits-program-manager' ),
					3
				);
			},
		),
		'date_count'          => array(
			'means'   => __( 'How many dates the student joined, counted: "3 dates".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %d: how many dates. */
					_n( '%d date', '%d dates', 3, 'wpcredits-program-manager' ),
					3
				);
			},
		),
		'in_how_long'         => array(
			'means'   => __( 'How long until the call starts, such as "in 23 hours".', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Calls::relative( $call['start'] );
			},
		),
		'time_zone'           => array(
			'means'   => __( 'The recipient\'s time zone, which every time in the email is shown in.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Availability::zone_label( $call['zone']->getName() );
			},
		),
		'meeting_place'       => array(
			'means'          => __( 'The meeting link the mentor set beside their availability. Empty, while the mentor has set none, leaves its lines out. It can carry the meeting\'s passcode, so it goes in the body, never in the subject.', 'wpcredits-program-manager' ),
			// Where an email is sent only while the mentor has a link, and so needs it.
			'means_required' => __( 'The meeting link the mentor set beside their availability, which this email is sent only with. It can carry the meeting\'s passcode, so it goes in the body, never in the subject.', 'wpcredits-program-manager' ),
			'example'        => 'https://meet.example.test/ben-lindqvist',
			// In the body alone: a meeting link can carry the meeting's passcode, and the subject is the
			// one part of an email the mail log keeps, for 30 days.
			'body_only'      => true,
		),
		'topic'               => array(
			'means'     => __( 'What the student said they would like to talk about, as they typed it. Empty leaves its lines out. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'My first plugin, and how to get it reviewed',
			'body_only' => true,
		),
		'session_topic'       => array(
			'means'     => __( 'What the session is about, as the mentor typed it. Empty leaves its lines out. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'The release cycle, and how to follow it',
			'body_only' => true,
		),
		'canceled_by'         => array(
			'means'   => __( 'Who canceled: their name, or "an Administrator" when an Administrator did.', 'wpcredits-program-manager' ),
			'example' => WPCPM_Mail_Catalog::TYPE_MENTOR === $audience ? 'Ada Okonjo' : 'Ben Lindqvist',
		),
		'dashboard_link'      => array(
			'means'   => __( 'The recipient\'s dashboard, while its page is published. Empty leaves its lines out.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $audience ) {
				return WPCPM_Mail_Templates::page_of_type( '' === $audience ? WPCPM_Mail_Catalog::TYPE_STUDENT : $audience );
			},
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required: an email
// about a call needs to say when it is, and the mentor's confirmation sent only while the mentor has a
// meeting link needs that link, which its meaning then says.
$wpcpm_take = static function ( $audience, array $names, array $required ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders( $audience );
	$out = array();

	foreach ( $names as $name ) {
		$needed       = in_array( $name, $required, true );
		$out[ $name ] = array(
			'means'     => $needed && isset( $all[ $name ]['means_required'] ) ? $all[ $name ]['means_required'] : $all[ $name ]['means'],
			'example'   => $all[ $name ]['example'],
			'required'  => $needed,
			'body_only' => ! empty( $all[ $name ]['body_only'] ),
		);
	}

	return $out;
};

// The sentences more than one email says, each under the translatable string the code used.
$wpcpm_times = sprintf(
	/* translators: %s: timezone name. */
	__( 'Times are shown in %s.', 'wpcredits-program-manager' ),
	'{time_zone}'
);

/* translators: The line above the mentor's meeting link. Keep the colon at the end; the site leaves this line out with the link under it while the mentor has set none. */
$wpcpm_where = __( 'Where you will meet:', 'wpcredits-program-manager' ) . "\n{meeting_place}";

$wpcpm_no_link = __( 'You have not set a meeting link yet, so this confirmation cannot tell your student where to go. You can add one beside your availability on the program site.', 'wpcredits-program-manager' );

/* translators: The line above what the student typed. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_to_mentor_topic = __( 'What the student would like to discuss:', 'wpcredits-program-manager' ) . "\n{topic}";

/* translators: The line above what the student typed, in the student's own copy. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_to_student_topic = __( 'What you said you would like to discuss:', 'wpcredits-program-manager' ) . "\n{topic}";

/* translators: The line above what the mentor typed about the session. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_session_topic = __( 'What the session is about:', 'wpcredits-program-manager' ) . "\n{session_topic}";

/* translators: The line above what the mentor typed about the series. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_series_topic = __( 'What the sessions are about:', 'wpcredits-program-manager' ) . "\n{session_topic}";

$wpcpm_moved_topic = sprintf(
	/* translators: %s: what the session is about. The site leaves this line out when the mentor wrote nothing. */
	__( 'What it is about: %s', 'wpcredits-program-manager' ),
	'{session_topic}'
);

// A body: its paragraphs, a blank line between each two, and no line break after the last.
$wpcpm_body = static function ( array $paragraphs ) {
	return implode( "\n\n", $paragraphs );
};

$wpcpm_mentor  = WPCPM_Mail_Catalog::TYPE_MENTOR;
$wpcpm_student = WPCPM_Mail_Catalog::TYPE_STUDENT;

// A booking or a join, as the mentor's copy says it: the opening, the time zone, where to meet (or, for
// `$no_link`, that the mentor has set no link), what the student would like to discuss or what the
// session is about, and the mentor's dashboard.
$wpcpm_booked_mentor = static function ( $opening, $topic, $no_link ) use ( $wpcpm_body, $wpcpm_times, $wpcpm_where, $wpcpm_no_link ) {
	return $wpcpm_body( array( $opening, $wpcpm_times, $no_link ? $wpcpm_no_link : $wpcpm_where, $topic, '{dashboard_link}' ) );
};

$wpcpm_booked_call_opening = sprintf(
	/* translators: 1: student name, 2: date and time. */
	__( '%1$s booked a call with you on %2$s.', 'wpcredits-program-manager' ),
	'{student_name}',
	'{when}'
);

$wpcpm_joined_session_opening = sprintf(
	/* translators: 1: student name, 2: date and time. */
	__( '%1$s joined your group session on %2$s.', 'wpcredits-program-manager' ),
	'{student_name}',
	'{when}'
);

$wpcpm_series_mentor_opening = sprintf(
	/* translators: 1: student name, 2: how many group sessions, counted, such as "3 group sessions". */
	__( '%1$s joined %2$s of yours:', 'wpcredits-program-manager' ),
	'{student_name}',
	'{group_session_count}'
) . "\n{session_dates}";

$wpcpm_series_closing = __( 'The attached calendar file adds all of them to your calendar at once.', 'wpcredits-program-manager' );

return array(
	'call-booked-mentor'            => array(
		'trigger'      => __( 'When a student books a one-to-one call with a mentor who has set a meeting link.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] Call booked with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_booked_call_opening, $wpcpm_to_mentor_topic, false ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when', 'meeting_place' ) ),
	),
	'call-booked-mentor-no-link'    => array(
		'trigger'      => __( 'When a student books a one-to-one call with a mentor who has not set a meeting link yet.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] Call booked with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_booked_call_opening, $wpcpm_to_mentor_topic, true ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-booked-student'           => array(
		'trigger'      => __( 'When the student books a one-to-one call with their mentor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] Your call with %2$s is booked', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: date and time. */
					__( 'Your call with %1$s is booked for %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_to_student_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-joined-mentor'         => array(
		'trigger'      => __( 'When a student joins a group session of a mentor who has set a meeting link.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] %2$s joined your group session', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_joined_session_opening, $wpcpm_session_topic, false ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'when', 'meeting_place' ) ),
	),
	'session-joined-mentor-no-link' => array(
		'trigger'      => __( 'When a student joins a group session of a mentor who has not set a meeting link yet.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] %2$s joined your group session', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_joined_session_opening, $wpcpm_session_topic, true ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-joined-student'        => array(
		'trigger'      => __( 'When the student joins a group session of their mentor\'s.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] You are on the group session with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: date and time. */
					__( 'You are on the group session with %1$s on %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'series-joined-mentor'          => array(
		'trigger'      => __( 'When a student joins the sessions of a series at once, with Join all, and the mentor has set a meeting link.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name, 3: how many sessions, counted, such as "3 sessions". */
			__( '[%1$s] %2$s joined your series: %3$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}',
			'{session_count}'
		),
		'body'         => $wpcpm_body( array( $wpcpm_series_mentor_opening, $wpcpm_times, $wpcpm_where, $wpcpm_series_topic, $wpcpm_series_closing, '{dashboard_link}' ) ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'session_count', 'group_session_count', 'session_dates', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'session_dates', 'meeting_place' ) ),
	),
	'series-joined-mentor-no-link'  => array(
		'trigger'      => __( 'When a student joins the sessions of a series at once, with Join all, and the mentor has not set a meeting link yet.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name, 3: how many sessions, counted, such as "3 sessions". */
			__( '[%1$s] %2$s joined your series: %3$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}',
			'{session_count}'
		),
		'body'         => $wpcpm_body( array( $wpcpm_series_mentor_opening, $wpcpm_times, $wpcpm_no_link, $wpcpm_series_topic, $wpcpm_series_closing, '{dashboard_link}' ) ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'session_count', 'group_session_count', 'session_dates', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'session_dates' ) ),
	),
	'series-joined-student'         => array(
		'trigger'      => __( 'When the student joins the sessions of a series at once, with Join all.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name, 3: how many dates, counted, such as "3 dates". */
			__( '[%1$s] Group sessions with %2$s: %3$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}',
			'{date_count}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: how many group sessions, counted, such as "3 group sessions". */
					__( 'You are on %2$s with %1$s:', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{group_session_count}'
				) . "\n{session_dates}",
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_series_topic,
				$wpcpm_series_closing,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'date_count', 'group_session_count', 'session_dates', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'session_dates' ) ),
	),
	'session-moved-student'         => array(
		'trigger'      => __( 'When a group session the student is on moves to another time.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] The group session with %2$s has moved', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the time it used to be at, 2: the time it is at now. Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
					__( 'The group session that was on %1$s has moved. It is now on %2$s.', 'wpcredits-program-manager' ),
					'{was_when}',
					'{when}'
				),
				$wpcpm_times,
				__( 'Your place is kept, and the invitation attached replaces the one already in your calendar. If the new time does not work for you, you can leave the session from your Student Report Card.', 'wpcredits-program-manager' ),
				$wpcpm_moved_topic,
				/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
				__( 'Your Student Report Card:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
			)
		) . "\n",
		// The two moved emails were written with the line ending WordPress gives its own email.
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'was_when', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-moved-mentor'          => array(
		'trigger'      => __( 'When an Administrator moves a group session of the mentor\'s to another time.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your group session has moved', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the time it used to be at, 2: the time it is at now. Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
					__( 'An Administrator moved your group session that was on %1$s. It is now on %2$s.', 'wpcredits-program-manager' ),
					'{was_when}',
					'{when}'
				),
				$wpcpm_times,
				__( 'Everybody on the session has been sent a new invitation, and the one attached replaces the one already in your calendar.', 'wpcredits-program-manager' ),
				$wpcpm_moved_topic,
				'{dashboard_link}',
			)
		) . "\n",
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'was_when', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-left'                  => array(
		'trigger'      => __( 'When the student leaves a group session.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] You left the group session with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: date and time. */
					__( 'You have left the group session with %1$s on %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-released'              => array(
		'trigger'      => __( 'When the students sync takes the student off a former mentor\'s group session, after the student was given a new mentor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the former mentor's name. */
			__( '[%1$s] Your place on the group session with %2$s is released', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the former mentor's name, 2: date and time. */
					__( 'You have a new mentor, so your place on the group session with %1$s on %2$s is released.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-cancelled-mentor'         => array(
		'trigger'      => __( 'When the student or an Administrator cancels a one-to-one call.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: date of the canceled call. */
			__( '[%1$s] Call on %2$s canceled', 'wpcredits-program-manager' ),
			'{site_name}',
			'{call_date}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date and time of the call, 2: who canceled. */
					__( 'The call on %1$s was canceled by %2$s.', 'wpcredits-program-manager' ),
					'{when}',
					'{canceled_by}'
				),
				$wpcpm_times,
				__( 'The slot is free again, and your student can book another time whenever suits them.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'call_date', 'when', 'canceled_by', 'time_zone', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-cancelled-student'        => array(
		'trigger'      => __( 'When the mentor or an Administrator cancels a one-to-one call.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: date of the canceled call. */
			__( '[%1$s] Call on %2$s canceled', 'wpcredits-program-manager' ),
			'{site_name}',
			'{call_date}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date and time of the call, 2: who canceled. */
					__( 'The call on %1$s was canceled by %2$s.', 'wpcredits-program-manager' ),
					'{when}',
					'{canceled_by}'
				),
				$wpcpm_times,
				__( 'The slot is free again, so you can book another time.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'call_date', 'when', 'canceled_by', 'time_zone', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-cancelled'             => array(
		'trigger'      => __( 'When a group session is canceled: to the mentor and to every student on it, but the person who canceled it.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: date of the canceled session. */
			__( '[%1$s] Group session on %2$s canceled', 'wpcredits-program-manager' ),
			'{site_name}',
			'{call_date}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date and time of the session, 2: who canceled. */
					__( 'The group session on %1$s was canceled by %2$s.', 'wpcredits-program-manager' ),
					'{when}',
					'{canceled_by}'
				),
				$wpcpm_times,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( '', array( 'site_name', 'call_date', 'when', 'canceled_by', 'time_zone', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-reminder-mentor'          => array(
		'trigger'      => __( 'A day before a one-to-one call, unless it was booked within that day.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the other person's name. */
			__( '[%1$s] Reminder: your call with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the other person's name, 2: date and time, 3: "in 4 hours". */
					__( 'Your call with %1$s is %3$s, on %2$s.', 'wpcredits-program-manager' ),
					'{student_name}',
					'{when}',
					'{in_how_long}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_to_mentor_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'in_how_long', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-reminder-student'         => array(
		'trigger'      => __( 'A day before a one-to-one call, unless it was booked within that day.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the other person's name. */
			__( '[%1$s] Reminder: your call with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the other person's name, 2: date and time, 3: "in 4 hours". */
					__( 'Your call with %1$s is %3$s, on %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}',
					'{in_how_long}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_to_student_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'in_how_long', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-reminder'              => array(
		'trigger'      => __( 'A day before a group session, unless it was created within that day: to the mentor and to every student on it.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the mentor's name, or how many students are coming. */
			__( '[%1$s] Reminder: your group session with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{with_whom}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the mentor's name, or how many students are coming, 2: date and time, 3: "in 4 hours". */
					__( 'Your group session with %1$s is %3$s, on %2$s.', 'wpcredits-program-manager' ),
					'{with_whom}',
					'{when}',
					'{in_how_long}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( '', array( 'site_name', 'with_whom', 'in_how_long', 'when', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
);
