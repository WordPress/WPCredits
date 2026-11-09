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
 * apology. The students' names go in a body alone, never in a subject, which the mail log keeps.
 * The site's signature and footer end each.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every placeholder an email of the area may have: what each means and its example, a value or a
// function that works one out when a preview or a test asks for it. An email takes the ones it uses
// (`$wpcpm_take`).
$wpcpm_placeholders = static function () {
	return array(
		'site_name'      => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'days'           => array(
			'means'   => __( 'How many days it has been since the student was last seen on the site.', 'wpcredits-program-manager' ),
			'example' => '15',
		),
		'count'          => array(
			'means'   => __( 'How many students are on the list. The email needs it.', 'wpcredits-program-manager' ),
			'example' => '3',
		),
		'mentor_name'    => array(
			'means'   => __( 'The student\'s mentor. The sentence that names the mentor is left out for a student with none.', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'booking_link'   => array(
			'means'   => __( 'Where the student can book a call: the calls section of their Student Report Card when they have a mentor, else the Administrator\'s booking page, else the Student Report Card. The email needs it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_STUDENT );
			},
		),
		'dashboard_link' => array(
			'means'   => __( 'The recipient\'s own dashboard: the Student Report Card, the Mentor Report Card or the Administrator Dashboard. The email needs it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_STUDENT );
			},
		),
		'students_list'  => array(
			'means'     => __( 'The students, one to a line, each with how long it has been and a link to the student. The students\' names go in the body alone, never in the subject, which the mail log keeps. The email needs it.', 'wpcredits-program-manager' ),
			'example'   => "Ada Okonjo, 15 days: https://example.test/mentor/\nSam Reyes, 21 days: https://example.test/mentor/",
			'body_only' => true,
		),
		'card_link'      => array(
			'means'   => __( 'The Students gone quiet card of the Administrator Dashboard. The email needs it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_ADMIN );
			},
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required.
$wpcpm_take = static function ( array $names, array $required ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders();
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
		'body'         => $wpcpm_body(
			array(
				__( 'Hello,', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: a number of days, 2: the site's name. */
					__( 'It has been %1$s days since we last saw you on %2$s, so we wanted to check in and see how you are doing.', 'wpcredits-program-manager' ),
					'{days}',
					'{site_name}'
				),
				sprintf(
					/* translators: %s: the mentor's name. */
					__( 'Your mentor, %s, is there to help, whatever stage you are at.', 'wpcredits-program-manager' ),
					'{mentor_name}'
				),
				__( 'The Administrator for your country is there to help too. Questions are always welcome.', 'wpcredits-program-manager' ),
				__( 'A short call is the easiest way to get going again. You can book one here:', 'wpcredits-program-manager' ) . "\n{booking_link}",
				__( 'If you have already talked with your mentor or with us, tell us by using the "We\'ve already talked" button on your Student Report Card:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'days', 'mentor_name', 'booking_link', 'dashboard_link' ), array( 'booking_link', 'dashboard_link' ) ),
	),
	'student-quiet-mentor'  => array(
		'trigger'      => __( 'Once a day at most, when the reminders of that day\'s run went out and the switch is On: to a mentor, with the list of their students who were just reminded.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: the site's name. */
			__( '[%s] Some of your students have gone quiet', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Hello,', 'wpcredits-program-manager' ),
				__( 'These students of yours have not been seen on the program site for a while. We have sent each of them a friendly note, and a word from you can mean even more.', 'wpcredits-program-manager' ),
				'{students_list}',
				__( 'If you have already talked with one of them, the Mentor Report Card has a "We\'ve already talked" button on their card:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'students_list', 'dashboard_link' ), array( 'students_list', 'dashboard_link' ) ),
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
					__( 'Students gone quiet in your country: %s. Here they are, most quiet first.', 'wpcredits-program-manager' ),
					'{count}'
				),
				'{students_list}',
				__( 'The Students gone quiet card of the Administrator Dashboard lists them too, and is where you can say that a student has already talked with us:', 'wpcredits-program-manager' ) . "\n{card_link}",
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'count', 'students_list', 'card_link' ), array( 'count', 'students_list', 'card_link' ) ),
	),
);
