<?php
/**
 * The Quiet students area's emails, as the template registry ships them (1.123.60).
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. All three are sent by the daily job of `WPCPM_Student_Flags`, and only while its switch
 * is On:
 *
 * - the reminder, to a student who has been quiet for the set number of days, once for each quiet
 *   spell;
 * - the digest, to a mentor, one a day, listing the students who were reminded that run;
 * - the weekly list, on Mondays, to each Administrator who has quiet students.
 *
 * The words are friendly and plain: how long it has been, who is there to help, where to book, and
 * how to say the student has already talked with the program. Nothing in them is a penalty or an
 * apology, and nothing is untrue (Release B): a student never seen is told when their track started
 * or their account was made, not "since we last saw you"; the mentor and the Administrator are named
 * only for a student who has them; and the mentor's and the Monday email read right for one student
 * as for several. The students' names go in a body alone, never in a subject, which the mail log
 * keeps. The site's signature and footer end each.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every placeholder an email of the area may have, for one of its three emails (`student`, `mentor` or
// `weekly`): what each means and its example, a value or a function that works one out when a preview
// or a test asks for it. An email takes the ones it uses (`$wpcpm_take`). The examples follow the email:
// each email's links lead where that email's do, so its preview in Tools > Emails reads as a real one.
$wpcpm_placeholders = static function ( $email ) {
	$dashboard = 'mentor' === $email ? WPCPM_Mail_Catalog::TYPE_MENTOR : WPCPM_Mail_Catalog::TYPE_STUDENT;

	return array(
		'site_name'          => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'days'               => array(
			'means'   => __( 'How many days it has been since the student was last seen on the site, or since their track started or their account was made when they have not been seen since.', 'wpcredits-program-manager' ),
			'example' => '15',
		),
		'days_since_seen'    => array(
			'means'   => __( 'How many days since the student was last seen on the site. Empty for a student not seen since their track started or their account was made, and the sentence that holds it is left out.', 'wpcredits-program-manager' ),
			'example' => '15',
		),
		'days_since_track'   => array(
			'means'   => __( 'How many days since the student\'s track started, for a student not seen on the site since. Empty otherwise, and the sentence that holds it is left out.', 'wpcredits-program-manager' ),
			'example' => '',
		),
		'days_since_account' => array(
			'means'   => __( 'How many days since the student\'s account was made, for a student not seen on the site yet. Empty otherwise, and the sentence that holds it is left out.', 'wpcredits-program-manager' ),
			'example' => '',
		),
		'count'              => array(
			'means'   => __( 'How many students are on the list. The email needs it.', 'wpcredits-program-manager' ),
			'example' => 'weekly' === $email ? '3' : '2',
		),
		'mentor_name'        => array(
			'means'   => __( 'The student\'s mentor. The sentence that names the mentor is left out for a student with none.', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'administrator_name' => array(
			'means'   => __( 'The Administrator for the student\'s country. The sentence that names them is left out for a student whose country has none.', 'wpcredits-program-manager' ),
			'example' => 'Cleo Marsh',
		),
		'booking_link'       => array(
			'means'   => __( 'Where the student can book a call: the calls section of their Student Report Card when they have a mentor, else the booking page of the Administrator for their country. Empty for a student with neither, who has nothing to book, and the sentence that holds it is left out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				$card = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_STUDENT );

				return '' !== $card ? $card . '#wpcpm-module-calls' : '';
			},
		),
		'contact_address'    => array(
			'means'   => __( 'The address in Contact address in emails, for a student with nothing to book: no mentor and no booking page for their country. Empty for any other student, and while the setting holds no address, and the sentence that holds it is left out.', 'wpcredits-program-manager' ),
			'example' => '',
		),
		'dashboard_link'     => array(
			'means'   => __( 'The recipient\'s own dashboard: the Student Report Card for the student, the Mentor Report Card for the mentor. The email needs it.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $dashboard ) {
				return WPCPM_Mail_Templates::page_of_type( $dashboard );
			},
		),
		'students_list'      => array(
			'means'     => __( 'The students, one to a line, each with how long it has been and a link to the student: on the Mentor Report Card in the mentor\'s email, on the Student Report Card in the Monday email. The students\' names go in the body alone, never in the subject, which the mail log keeps. The email needs it.', 'wpcredits-program-manager' ),
			'example'   => static function () use ( $email ) {
				if ( 'mentor' === $email ) {
					$card = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_MENTOR );
					$link = static function ( $record ) use ( $card ) {
						return '' !== $card ? ': ' . $card . '?wpcpm_student=' . $record . '#wpcpm-student-' . $record : '';
					};

					return 'Ada Okonjo, 15 days' . $link( 'recEXAMPLE000001' ) . "\n" . 'Sam Reyes, 21 days' . $link( 'recEXAMPLE000002' );
				}

				$card = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_STUDENT );
				$link = static function ( $id ) use ( $card ) {
					return '' !== $card ? ': ' . $card . '?wpcpm_student_view=' . $id : '';
				};

				return 'Sam Reyes, 21 days' . $link( 102 ) . "\n" . 'Ada Okonjo, 15 days' . $link( 101 ) . "\n" . 'Lee Park, 14 days' . $link( 103 );
			},
			'body_only' => true,
		),
		'card_link'          => array(
			'means'   => __( 'The Students gone quiet card of the Administrator Dashboard. The email needs it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				$card = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_ADMIN );

				return '' !== $card && class_exists( 'WPCPM_Administrators_Dashboard' ) ? WPCPM_Administrators_Dashboard::card_url( 'quiet-students' ) : '';
			},
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required.
$wpcpm_take = static function ( $email, array $names, array $required ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders( $email );
	$out = array();

	foreach ( $names as $name ) {
		$out[ $name ] = array(
			'means'     => $all[ $name ]['means'],
			'example'   => $all[ $name ]['example'],
			'required'  => in_array( $name, $required, true ),
			'body_only' => ! empty( $all[ $name ]['body_only'] ),
		);
	}

	return $out;
};

// A body: its paragraphs, a blank line between each two.
$wpcpm_body = static function ( array $paragraphs ) {
	return implode( "\n\n", $paragraphs );
};

return array(
	'student-quiet-student' => array(
		'trigger'      => __( 'Once for each stretch of quiet, when a student has not been seen on the site for the set number of days, has no call booked ahead and the quiet students switch is On: to the student. Not sent for a student of a test institution, nor for a stretch already past the set number of days when the switch moved to On.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: a number of days. */
			__( '[%1$s] Checking in, it has been %2$s days', 'wpcredits-program-manager' ),
			'{site_name}',
			'{days}'
		),
		// The opening is one of three sentences, each with its own count, so a student never seen is
		// never told "since we last saw you": the one whose count is empty is left out.
		'body'         => $wpcpm_body(
			array(
				__( 'Hello,', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: a number of days. */
					__( 'It has been %s days since we last saw you on the program site, so we wanted to check in and see how you are doing.', 'wpcredits-program-manager' ),
					'{days_since_seen}'
				),
				sprintf(
					/* translators: %s: a number of days. */
					__( 'Your track started %s days ago and we have not seen you on the program site since, so we wanted to check in and see how you are doing.', 'wpcredits-program-manager' ),
					'{days_since_track}'
				),
				sprintf(
					/* translators: %s: a number of days. */
					__( 'Your account was made %s days ago and we have not seen you on the program site yet, so we wanted to check in and see how you are doing.', 'wpcredits-program-manager' ),
					'{days_since_account}'
				),
				sprintf(
					/* translators: %s: the mentor's name. */
					__( 'Your mentor, %s, is there to help, whatever stage you are at.', 'wpcredits-program-manager' ),
					'{mentor_name}'
				),
				sprintf(
					/* translators: %s: the name of the Administrator for the student's country. */
					__( '%s, the Administrator for your country, is glad to help.', 'wpcredits-program-manager' ),
					'{administrator_name}'
				) . ' ' . __( 'Questions are always welcome.', 'wpcredits-program-manager' ),
				// "Again" and "your mentor" are left out here: the line is the same for a student never
				// seen and for one with no mentor. A student with nothing to book gets the sentence after it
				// instead, each left out when its placeholder is empty.
				__( 'A short call is the easiest way to get going. You can book one here:', 'wpcredits-program-manager' ) . "\n{booking_link}",
				sprintf(
					/* translators: %s: the program's contact address. */
					__( 'There is no call for you to book on the site yet, so if you would like to talk, write to us at %s.', 'wpcredits-program-manager' ),
					'{contact_address}'
				),
				__( 'If you have already talked with someone from the program, tell us by using the "We\'ve already talked" button on your Student Report Card:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
			)
		),
		'placeholders' => $wpcpm_take( 'student', array( 'site_name', 'days', 'days_since_seen', 'days_since_track', 'days_since_account', 'mentor_name', 'administrator_name', 'booking_link', 'contact_address', 'dashboard_link' ), array( 'dashboard_link' ) ),
	),
	'student-quiet-mentor'  => array(
		'trigger'      => __( 'Once a day at most, when the reminders of that day\'s run went out and the switch is On: to a mentor, with the list of their students who were just reminded.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: a number of students. */
			__( '[%1$s] Your students gone quiet: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{count}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Hello,', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: a number of students. */
					__( 'Students of yours not seen on the program site for a while: %s.', 'wpcredits-program-manager' ),
					'{count}'
				) . ' ' . __( 'We have sent a friendly note to every student listed here, and a word from you can mean even more.', 'wpcredits-program-manager' ),
				'{students_list}',
				__( 'If you have already talked with a student listed here, the Mentor Report Card has a "We\'ve already talked" button on their card:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
			)
		),
		'placeholders' => $wpcpm_take( 'mentor', array( 'site_name', 'count', 'students_list', 'dashboard_link' ), array( 'count', 'students_list', 'dashboard_link' ) ),
	),
	'student-quiet-weekly'  => array(
		'trigger'      => __( 'On the first daily run of each Monday, once a week, when the switch is On: to each Administrator who has at least one quiet student in their country. Students of a test institution are not counted or listed.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: a number of students. */
			__( '[%1$s] Students gone quiet this week: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{count}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Hello,', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: a number of students. */
					__( 'Students gone quiet in your country: %s.', 'wpcredits-program-manager' ),
					'{count}'
				) . ' ' . __( 'The list is most quiet first.', 'wpcredits-program-manager' ),
				'{students_list}',
				__( 'The Students gone quiet card of the Administrator Dashboard has the same list, and is where you can say that a student has already talked with us:', 'wpcredits-program-manager' ) . "\n{card_link}",
			)
		),
		'placeholders' => $wpcpm_take( 'weekly', array( 'site_name', 'count', 'students_list', 'card_link' ), array( 'count', 'students_list', 'card_link' ) ),
	),
);
