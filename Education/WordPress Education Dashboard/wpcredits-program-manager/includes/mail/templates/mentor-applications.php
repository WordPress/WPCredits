<?php
/**
 * The Mentor applications area's emails, as the template registry ships them (1.122.31).
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Four go to the person who applied to mentor through the form on the site, and one to the
 * Administrators.
 *
 * The four are the program's Airtable automations' words as they stood on 16 October 2026 (Mentors
 * 1, 2, 5 and 4; design of 16 October 2026, sections 2.1 and 8), kept word for word but for what
 * the site does its own way: the site's signature and footer stand where each automation signed
 * off; the acknowledgement carries the lines the institution acknowledgement has for the link that
 * confirms the address; the course's name and its invitation link are the settings'
 * (`{course_name}`, `{course_link}`); the program's address is the Contact address in emails
 * setting's (`{program_contact}`), on a line of its own in the selection, so that email reads whole
 * while none is set; and a dash in an automation's text is a comma or a full stop here. The mentor coordinator reads the four before the form is switched on. The Administrators'
 * notice is the sponsor application's, naming the applicant and their profile.
 *
 * No course code, invitation code or folder link is written here: the course invitation link is a
 * setting an Administrator fills in (`mentor_course_link`), and the examples carry that setting,
 * or a fake one while it is empty.
 *
 * The applicant's name goes in a body alone, never in a subject, which the mail log keeps.
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
		'site_name'       => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'applicant_name'  => array(
			'means'     => __( 'The applicant\'s name, as they typed it on the form. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Ada Okonjo',
			'body_only' => true,
		),
		'reference'       => array(
			'means'   => __( 'The application\'s reference, such as MAPP-2026-0007.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf( 'MAPP-%s-0007', wp_date( 'Y' ) );
			},
		),
		'verify_link'     => array(
			'means'     => __( 'The link that confirms the applicant\'s address. It carries a secret, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return add_query_arg(
					array(
						'action' => WPCPM_Mentor_Application::ACTION_VERIFY,
						'app'    => 7,
						't'      => 'EXAMPLE-NOT-A-REAL-SIGNATURE',
					),
					admin_url( 'admin-post.php' )
				);
			},
			'body_only' => true,
		),
		'applicant_email' => array(
			'means'     => __( 'The address the application gave, which this email is sent to, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'ada@example.test',
			'body_only' => true,
		),
		'course_name'     => array(
			'means'   => __( 'The mentors\' course, as the Mentor Status Checker\'s settings name it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return (string) ( class_exists( 'WPCPM_Settings' ) ? WPCPM_Settings::get_value( 'checker_course_title', 'WordPress Credits Mentor\'s Course' ) : 'WordPress Credits Mentor\'s Course' );
			},
		),
		'course_link'     => array(
			'means'     => __( 'The mentors\' course invitation link, from the setting on the Students and mentors tab of Settings. It is the email\'s point, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				$set = class_exists( 'WPCPM_Settings' ) ? trim( (string) WPCPM_Settings::get_value( 'mentor_course_link', '' ) ) : '';

				// The link as set, so a test of the email shows what an applicant would follow; a
				// fake one until an Administrator sets it.
				return '' !== $set ? $set : 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE';
			},
			'body_only' => true,
		),
		'program_contact' => array(
			'means'   => __( 'The address in Contact address in emails, which a selected applicant writes to when the course is done. Empty leaves its sentence out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				$set = WPCPM_Mail_Templates::program_contact();

				// The address as set, or an example one while none is.
				return '' !== $set ? $set : 'programs@example.test';
			},
		),
		'profile_link'    => array(
			'means'     => __( 'The applicant\'s WordPress.org profile. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'https://profiles.wordpress.org/mentor-two/',
			'body_only' => true,
		),
		'queue_link'      => array(
			'means'   => __( 'The Waiting for review tab of the Mentors screen, where the application waits for an Administrator.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' );
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
	'mentor-application-received' => array(
		'trigger'      => __( 'When somebody applies to mentor through the form on the site: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'Mentor application received - WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				__( 'Hi!', 'wpcredits-program-manager' ),
				__( 'Thank you for submitting your application to become a mentor in the WordPress Credits Program. We truly appreciate your willingness to contribute your time and expertise to support our students.', 'wpcredits-program-manager' ),
				__( 'Please note that it may take several weeks before we are able to review all applications. Once the selection process is complete, we will contact you to let you know whether you have been chosen as a mentor.', 'wpcredits-program-manager' ),
				__( 'We are grateful for your patience and interest in being part of this program.', 'wpcredits-program-manager' ),
				__( 'Please confirm this address by following this link. It tells us we are writing to a real address, and it does nothing else:', 'wpcredits-program-manager' ),
				'{verify_link}',
				sprintf(
					/* translators: 1: the address the message went to, 2: site name. */
					__( 'This message went to %1$s because that address was given on the mentor application form at %2$s. If it was not you, you can ignore it: nothing happens until the address is confirmed.', 'wpcredits-program-manager' ),
					'{applicant_email}',
					'{site_name}'
				),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'verify_link', 'applicant_email' ), array( 'verify_link' ) ),
	),
	'mentor-selected'             => array(
		'trigger'      => __( 'When an Administrator presses Select on a mentor application: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'WordPress Credits Program - Mentor welcome and mandatory course', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the applicant's name, as they typed it. */
					__( 'Hi %s,', 'wpcredits-program-manager' ),
					'{applicant_name}'
				),
				__( 'Congratulations! You have been selected as a mentor for the WordPress Credits Program.', 'wpcredits-program-manager' ),
				__( 'Please remember that we cannot ensure sponsorship for mentors. Please move forward with the next steps only if you are sure you can commit at least two hours each week to the program\'s students.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the name of the mentors' course, 2: its invitation link. */
					__( 'As the next step, please access the %1$s through this invitation link: %2$s', 'wpcredits-program-manager' ),
					'{course_name}',
					'{course_link}'
				),
				__( 'Inside, you will find all the guidance, materials, and examples you need to support your students effectively throughout the WordPress Credits program.', 'wpcredits-program-manager' ),
				// The automation's one sentence, "reach out to {contact} or reply to this email", is two
				// lines, so the paragraph reads whole while no contact address is set: the registry
				// leaves the second out then.
				implode(
					"\n",
					array(
						__( 'When you finish the course, please reply to this email to let us know.', 'wpcredits-program-manager' ),
						sprintf(
							/* translators: %s: the program's contact address. */
							__( 'You can also write to %s.', 'wpcredits-program-manager' ),
							'{program_contact}'
						),
					)
				),
				__( 'Once we receive your message, we will review your course report and confirm when you are officially ready to start mentoring in the WordPress Credits program.', 'wpcredits-program-manager' ),
				__( 'If you experience any issues accessing the course, just reply to this email and we will assist you right away.', 'wpcredits-program-manager' ),
				__( 'Thank you for joining the WordPress Credits mentor community. Your support makes a real difference!', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'applicant_name', 'course_name', 'course_link', 'program_contact' ), array( 'course_link' ) ),
	),
	'mentor-more-information'     => array(
		'trigger'      => __( 'When an Administrator presses Ask for more information on a mentor application: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'WordPress mentorship application - request for further details', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				__( 'Hello,', 'wpcredits-program-manager' ),
				__( 'Thank you for your interest in contributing to the WordPress project as a mentor, and for taking the time to submit your application.', 'wpcredits-program-manager' ),
				__( 'As part of the review process carried out by the WordPress Credits team, we aim to better understand each applicant\'s involvement with the WordPress project and its community. After reviewing your profile and the information you shared with us, we would appreciate a bit more detail to help us clearly identify your contributions and connection to WordPress.', 'wpcredits-program-manager' ),
				__( 'Could you please provide additional information about your participation in the WordPress ecosystem? For example, this could include (but is not limited to):', 'wpcredits-program-manager' ),
				implode(
					"\n",
					array(
						__( '- Contributions to WordPress, like core, themes, plugins, documentation, translations, or any of the WordPress contribution teams', 'wpcredits-program-manager' ),
						__( '- Involvement in WordPress community activities (meetups, WordCamps, organizing, speaking, volunteering, etc.)', 'wpcredits-program-manager' ),
						__( '- Mentorship, teaching, or support roles related to WordPress', 'wpcredits-program-manager' ),
						__( '- Any other relevant experience that demonstrates your engagement with the WordPress project', 'wpcredits-program-manager' ),
					)
				),
				__( 'This information will help us better understand your background and evaluate your application more accurately.', 'wpcredits-program-manager' ),
				__( 'Thank you very much for your time and your interest in supporting the WordPress community. We look forward to hearing from you.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array(), array() ),
	),
	'mentor-declined'             => array(
		'trigger'      => __( 'When an Administrator presses Decline on a mentor application: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'Thank you for your interest in the WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the applicant's name, as they typed it. */
					__( 'Dear %s,', 'wpcredits-program-manager' ),
					'{applicant_name}'
				),
				__( 'Thank you sincerely for expressing your interest in supporting and educating students through the WordPress Credits program. We truly appreciate your enthusiasm and your commitment to fostering contributions within the WordPress community.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the WordPress Credits Slack channel's address, 2: the GitHub repository's address. */
					__( 'While we would have been delighted to welcome you to the team, after reviewing your application, we found that the information available in your WordPress.org profile was not sufficient to confirm your eligibility to serve as a mentor at this time. We genuinely value the time and effort you invested, and we encourage you to stay involved with the WordPress Credits initiative by contributing in other ways, such as joining discussions in the WordPress Credits Slack channel (%1$s) or helping out with specific tasks in our GitHub repository (%2$s).', 'wpcredits-program-manager' ),
					'https://wordpress.slack.com/archives/C0959D2M3T8',
					'https://github.com/WordPress/WPCredits-Tracker'
				),
				__( 'If you would like to appeal this decision or share additional details about your background, experience, and reasons why you\'d be a great fit as a mentor, please reply to this email and provide more context about your involvement with WordPress and any mentoring or teaching experience you may have.', 'wpcredits-program-manager' ),
				__( 'We truly appreciate your willingness to contribute to this initiative and would be happy to review your application again once more information is available.', 'wpcredits-program-manager' ),
				__( 'Thank you once again for your time, interest, and support.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'applicant_name' ), array() ),
	),
	'mentor-application-notice'   => array(
		'trigger'      => __( 'When a mentor application passes every check: to the addresses in Who is mailed under Mentor applications, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the application reference. */
			__( '[%1$s] New mentor application: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{reference}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Somebody has applied to mentor through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the applicant's name, 2: their WordPress.org profile. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					'{applicant_name}',
					'{profile_link}'
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				'{queue_link}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'reference', 'applicant_name', 'profile_link', 'queue_link' ), array() ),
	),
);
