<?php
/**
 * The Student interests area's emails, as the template registry ships them (1.123.64).
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. The first goes to the address a student gave on the student interest form, once, when
 * the interest is stored as new or held. The second goes to Administrators, for a new interest
 * alone. The representative the student names is never sent anything by the site.
 *
 * The site's signature and footer end each.
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
		'site_name'        => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'student_name'     => array(
			'means'     => __( 'The student\'s name, as they typed it on the form. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Ada Okonjo',
			'body_only' => true,
		),
		'institution_name' => array(
			'means'   => __( 'The institution the student named on the form.', 'wpcredits-program-manager' ),
			'example' => 'Example University',
		),
		'country'          => array(
			'means'   => __( 'The country the student chose on the form.', 'wpcredits-program-manager' ),
			'example' => 'Portugal',
		),
		'reference'        => array(
			'means'   => __( 'The interest\'s reference, such as SINT-2026-0007.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf( 'SINT-%s-0007', wp_date( 'Y' ) );
			},
		),
		'interests_link'   => array(
			'means'   => __( 'The Interests tab of the Students screen.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-students&tab=interests' );
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
	'student-interest-received' => array(
		'trigger'      => __( 'When a student sends the student interest form and the interest is stored as new or held: to the address the student gives. The representative the student names is sent nothing.', 'wpcredits-program-manager' ),
		'subject'      => __( 'We received your interest - WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the student's name, as they typed it. */
					__( 'Hi %s,', 'wpcredits-program-manager' ),
					'{student_name}'
				),
				sprintf(
					/* translators: %s: the name of the institution the student named. */
					__( 'We received your interest in the WordPress Credits Program for %s.', 'wpcredits-program-manager' ),
					'{institution_name}'
				),
				__( 'We will get in touch with the representative you named. Nothing else is needed from you now.', 'wpcredits-program-manager' ),
				__( 'If you have any questions in the meantime, reply to this email.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'student_name', 'institution_name' ), array() ),
	),
	'student-interest-notice'   => array(
		'trigger'      => __( 'When an interest from a student passes every check: to the addresses in "Who hears of a new interest" under Interest from students, or, with none set, to every Administrator. An interest that is held or filed as spam sends none.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the interest's reference. */
			__( '[%1$s] New interest from a student: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{reference}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'A student whose institution is not in the program yet has sent the student interest form, and it passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the institution's name, 2: its country. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					'{institution_name}',
					'{country}'
				),
				__( 'The student and the representative to write to are on the Interests tab:', 'wpcredits-program-manager' ),
				'{interests_link}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'reference', 'institution_name', 'country', 'interests_link' ), array() ),
	),
);
