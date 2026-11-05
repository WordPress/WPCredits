<?php
/**
 * The Student registrations area's emails, as the template registry ships them (1.123.54).
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. All three go to the address a student gave on the registration form on the site, and
 * each is sent once, when the registration's outcome is known: the registration was written to
 * Airtable, the address has an account on the site, or the address is already registered.
 *
 * The three are the design's words (design of 5 November 2026, section 5), kept as the design
 * fixes them, "a program manager" among them. The site's signature and footer end each.
 *
 * The student's name goes in a body alone, never in a subject, which the mail log keeps.
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
		'site_name'          => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'student_name'       => array(
			'means'     => __( 'The student\'s name, as they typed it on the form. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Ada Okonjo',
			'body_only' => true,
		),
		'institution_name'   => array(
			'means'   => __( 'The institution the student chose on the form.', 'wpcredits-program-manager' ),
			'example' => 'Example University',
		),
		'login_link'         => array(
			'means'   => __( 'The site\'s sign-in page. It is the email\'s point, so the email needs it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return wp_login_url();
			},
		),
		'lost_password_link' => array(
			'means'   => __( 'The page where a person sets a new password. It is the email\'s point, so the email needs it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return wp_lostpassword_url();
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
	'student-registration-received' => array(
		'trigger'      => __( 'When a student registers through the form on the site and the registration is written to Airtable: to the address the registration gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'Registration received - WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the student's name, as they typed it. */
					__( 'Hi %s,', 'wpcredits-program-manager' ),
					'{student_name}'
				),
				sprintf(
					/* translators: %s: the name of the institution the student chose. */
					__( 'Thank you for registering for the WordPress Credits Program. We have received your registration for %s.', 'wpcredits-program-manager' ),
					'{institution_name}'
				),
				__( 'A program manager will be in touch by email with the next steps, including your mentor and your course.', 'wpcredits-program-manager' ),
				__( 'If you have any questions in the meantime, reply to this email.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'student_name', 'institution_name' ), array() ),
	),
	'student-registration-account'  => array(
		'trigger'      => __( 'When somebody registers through the student registration form with the address of an account on this site: to that address.', 'wpcredits-program-manager' ),
		'subject'      => __( 'You already have an account - WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				__( 'Hi,', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: site name. */
					__( 'Someone, probably you, just registered for the WordPress Credits Program with this address. You already have an account on %s, so there is nothing more to fill in: sign in here.', 'wpcredits-program-manager' ),
					'{site_name}'
				),
				'{login_link}',
				sprintf(
					/* translators: %s: the link to set a new password. */
					__( 'If you do not remember your password, set a new one here: %s', 'wpcredits-program-manager' ),
					'{lost_password_link}'
				),
				__( 'If you would like to join another course, reply to this email and a program manager will help.', 'wpcredits-program-manager' ),
				__( 'If this was not you, you can ignore this email.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'login_link', 'lost_password_link' ), array( 'login_link', 'lost_password_link' ) ),
	),
	'student-registration-already'  => array(
		'trigger'      => __( 'When somebody registers through the student registration form with an address already registered: to that address.', 'wpcredits-program-manager' ),
		'subject'      => __( 'You are already registered - WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				__( 'Hi,', 'wpcredits-program-manager' ),
				__( 'Someone, probably you, just registered for the WordPress Credits Program with this address. We already have your registration, so there is nothing more to fill in. A program manager will be in touch by email with the next steps.', 'wpcredits-program-manager' ),
				__( 'If you have any questions, reply to this email. If this was not you, you can ignore this email.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array(), array() ),
	),
);
