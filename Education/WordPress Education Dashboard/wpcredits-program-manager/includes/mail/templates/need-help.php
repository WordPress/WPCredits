<?php
/**
 * The Need help? area's email, as the template registry ships it (AI Mentor R2, 1.123.103).
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds the email's name, area and audience from the
 * catalog. It is sent by `WPCPM_Student_Questions::send()`, and only while Send to mentors is On:
 *
 * - a student's question, to their mentor, with the student's address as Reply-To so the mentor answers by
 *   replying. The words are the ones the product owner approved, and the subject carries no site name.
 *
 * What the student typed, the question and the line added to it, and the sections Need help? showed with
 * it go in the body alone, never in the subject, which the mail log keeps. The site's signature and footer
 * end it.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'student-question-mentor' => array(
		'trigger'      => __( 'When a student whose question Need help? could not answer sends it, and Send to mentors is On: to the student\'s mentor, with the student\'s address as Reply-To. The question is also kept for the Mentor Report Card.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: the student's first name. */
			__( '%s sent you a question from Need help?', 'wpcredits-program-manager' ),
			'{student_first_name}'
		),
		'body'         => implode(
			"\n\n",
			array(
				sprintf(
					/* translators: %s: the mentor's first name. */
					__( 'Hi %s,', 'wpcredits-program-manager' ),
					'{mentor_first_name}'
				),
				sprintf(
					/* translators: %s: the student's first name. */
					__( '%s asked Need help? a question it could not fully answer:', 'wpcredits-program-manager' ),
					'{student_first_name}'
				),
				'{question}',
				'{student_note}',
				__( 'Need help? showed them:', 'wpcredits-program-manager' ) . "\n{links}",
				sprintf(
					/* translators: %s: the student's first name. */
					__( 'Reply to this email to answer. Your reply goes to %s.', 'wpcredits-program-manager' ),
					'{student_first_name}'
				),
				sprintf(
					/* translators: %s: the address of the student's card on the Mentor Report Card. */
					__( 'The question is also on your Mentor Report Card: %s', 'wpcredits-program-manager' ),
					'{student_card_url}'
				),
			)
		),
		'placeholders' => array(
			'student_first_name' => array(
				'means'     => __( 'The student\'s first name, or the name on their account when they have no first name.', 'wpcredits-program-manager' ),
				'example'   => 'Student A',
				'required'  => false,
				'body_only' => false,
			),
			'mentor_first_name'  => array(
				'means'     => __( 'The mentor\'s first name, or the name on their account when they have no first name.', 'wpcredits-program-manager' ),
				'example'   => 'Mentor A',
				'required'  => false,
				'body_only' => false,
			),
			'question'           => array(
				'means'     => __( 'The question as the student typed it. The email needs it.', 'wpcredits-program-manager' ),
				'example'   => 'How do I start the final project?',
				'required'  => true,
				'body_only' => true,
			),
			'student_note'       => array(
				'means'     => __( 'The line the student added to the question. Empty when they added nothing, and its paragraph is left out.', 'wpcredits-program-manager' ),
				'example'   => 'I read the guide twice and I am still not sure where to begin.',
				'required'  => false,
				'body_only' => true,
			),
			'links'              => array(
				'means'     => __( 'The sections Need help? showed with its answer, one address to a line. Empty when it showed none, and the line that introduces them is left out with it.', 'wpcredits-program-manager' ),
				'example'   => static function () {
					return home_url( '/' );
				},
				'required'  => false,
				'body_only' => true,
			),
			'student_card_url'   => array(
				'means'     => __( 'The student on the Mentor Report Card. The email needs it.', 'wpcredits-program-manager' ),
				'example'   => static function () {
					$card = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_MENTOR );

					return '' !== $card ? $card . '?wpcpm_student=recEXAMPLE000001#wpcpm-student-recEXAMPLE000001' : '';
				},
				'required'  => true,
				'body_only' => false,
			),
		),
	),
);
