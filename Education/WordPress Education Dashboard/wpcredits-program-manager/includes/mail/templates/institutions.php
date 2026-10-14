<?php
/**
 * The Institutions area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one the code wrote before 1.122.22, under the same translatable
 * string (`WPCPM_Institution_Application`, `WPCPM_Institutions`, `WPCPM_Institution_Invite`,
 * `WPCPM_Institution_Agreement`, `WPCPM_Agreement_Generate` and `WPCPM_Institution_Members`), but
 * the counted ones: a sentence `_n()` used to word whole takes its counted words as one value now,
 * worked out with `_n()` where the email is sent (`{review_window}`, `{agreements_waiting}`,
 * `{agreements_overdue}`), so the words around them can be edited.
 *
 * An email that said other words in one case than in another is two templates: the application's
 * acknowledgement with a call to book and without one, the notice of an upload with the courtesy
 * scan's notes and without them, and the revocation with a country contact named and without one.
 * The agreement put back in force, which was sent under the acceptance's id, has an id of its own.
 *
 * Since 1.122.23 the invitation is two as well: an invitation with nobody to name as its sender,
 * the inviter's account gone or holding no name, opened " has invited you"; it says who is invited
 * to what in a sentence of its own. Either invitation promises a reply only while it carries an
 * address to reply to (`{replies_reach}`), where both promised one always. And a returned or
 * revoked agreement whose Administrator's account holds no name says "by an Administrator", worded
 * where it is sent. The notice of an upload with the courtesy scan's notes says what the scan
 * noticed in words, each with the PDF's own name for it in brackets.
 *
 * Every body is joined with the line ending the code wrote, "\r\n", and every date is the site's.
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
	// The day an example email is about, on the site's clock, a number of whole days from today.
	$day = static function ( $days = 0 ) {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( ( $days < 0 ? '-' : '+' ) . abs( (int) $days ) . ' days' )->format( 'Y-m-d' );
	};
	// How many working days the program takes to read an agreement, as set.
	$days = static function () {
		return max( 1, (int) ( class_exists( 'WPCPM_Settings' ) ? WPCPM_Settings::get_value( 'agreement_review_days', 3 ) : 3 ) );
	};

	return array(
		'site_name'          => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'institution_name'   => array(
			'means'   => __( 'The institution\'s name; its Airtable record when the site holds no name for it.', 'wpcredits-program-manager' ),
			'example' => 'Example University',
		),
		'reference'          => array(
			'means'   => __( 'The application\'s reference, such as APP-2026-0007.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf( 'APP-%s-0007', wp_date( 'Y' ) );
			},
		),
		'verify_link'        => array(
			'means'     => __( 'The link that confirms the applicant\'s address. It carries a secret, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return add_query_arg(
					array(
						'action' => WPCPM_Institution_Application::ACTION_VERIFY,
						'app'    => 7,
						't'      => 'EXAMPLE-NOT-A-REAL-SIGNATURE',
					),
					admin_url( 'admin-post.php' )
				);
			},
			'body_only' => true,
		),
		'booking_link'       => array(
			'means'   => __( 'The link to book a call with the Administrator who looks after the applicant\'s country, which this email is sent only with.', 'wpcredits-program-manager' ),
			'example' => 'https://calendly.example.test/example-contact',
		),
		'applicant_email'    => array(
			'means'     => __( 'The address the application gave, which this email is sent to, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'admissions@example.test',
			'body_only' => true,
		),
		'city'               => array(
			'means'     => __( 'The city the application gave, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Example City',
			'body_only' => true,
		),
		'country'            => array(
			'means'   => __( 'The country\'s name.', 'wpcredits-program-manager' ),
			'example' => 'Poland',
		),
		'contact_name'       => array(
			'means'     => __( 'The contact person the application gave, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Ada Okonjo',
			'body_only' => true,
		),
		'contact_email'      => array(
			'means'     => __( 'The contact\'s address, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'ada@example.test',
			'body_only' => true,
		),
		'queue_link'         => array(
			'means'   => __( 'The Institutions screen, where it waits for an Administrator.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-institutions' );
			},
		),
		'question'           => array(
			'means'     => __( 'The Administrator\'s question, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Which department would run the internships, and who would sign the Collaboration Agreement?',
			'body_only' => true,
		),
		'inviter_name'       => array(
			'means'   => __( 'Who sent the invitation: a member of the institution or an Administrator.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'replies_reach'      => array(
			'means'   => __( 'Who a reply reaches, "the person who sent it", while the invitation carries the inviter\'s address to reply to. Empty leaves its sentence out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return __( 'the person who sent it', 'wpcredits-program-manager' );
			},
		),
		'accept_link'        => array(
			'means'     => __( 'The link that accepts the invitation. It carries a secret, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return WPCPM_Institution_Invite::accept_url( 'EXAMPLE-NOT-A-REAL-TOKEN' );
			},
			'body_only' => true,
		),
		'expiry_date'        => array(
			'means'   => __( 'The day the invitation\'s link stops working.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day( WPCPM_Institution_Invite::LIFETIME_DAYS );
			},
		),
		'uploaded_on'        => array(
			'means'   => __( 'The day the signed Collaboration Agreement was uploaded.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day( -1 );
			},
		),
		'uploaded_by'        => array(
			'means'   => __( 'Who uploaded it: their name, or, when their account holds none, "a member of your institution" in the institution\'s copy and the institution\'s Airtable record in the Administrators\'.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'review_window'      => array(
			'means'   => __( 'How long the program takes to read a Collaboration Agreement, counted: "3 working days".', 'wpcredits-program-manager' ),
			'example' => static function () use ( $days ) {
				return sprintf(
					/* translators: %s: a number of working days. */
					_n( '%s working day', '%s working days', $days(), 'wpcredits-program-manager' ),
					number_format_i18n( $days() )
				);
			},
		),
		'scan_flags'         => array(
			'means'   => __( 'What the courtesy scan noticed in the file, in words, each with the PDF\'s own name for it in brackets, which this email is sent only with.', 'wpcredits-program-manager' ),
			// In the words the email gives them, in the language of the Administrator looking.
			'example' => static function () {
				return WPCPM_Pdf_Check::in_words( array( '/JavaScript', '/OpenAction' ) );
			},
		),
		'accepted_on'        => array(
			'means'   => __( 'The day the Collaboration Agreement was accepted.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'reinstated_on'      => array(
			'means'   => __( 'The day the Collaboration Agreement was put back in force.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'returned_on'        => array(
			'means'   => __( 'The day the Collaboration Agreement was sent back.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'revoked_on'         => array(
			'means'   => __( 'The day the Collaboration Agreement was revoked.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'administrator_name' => array(
			'means'   => __( 'The Administrator who did it, or "an Administrator" when their account holds no name.', 'wpcredits-program-manager' ),
			'example' => 'Cleo Marsh',
		),
		'note'               => array(
			'means'     => __( 'The Administrator\'s note, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Please sign page 2 as well, and date it.',
			'body_only' => true,
		),
		'country_contact'    => array(
			'means'   => __( 'The address of the program\'s contact for the institution\'s country, which this email is sent only with.', 'wpcredits-program-manager' ),
			'example' => 'contact@example.test',
		),
		'dashboard_link'     => array(
			'means'   => __( 'The Institution Dashboard, while its page is published. Empty leaves its line out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_INSTITUTION );
			},
		),
		'agreements_waiting' => array(
			'means'   => __( 'How many signed Collaboration Agreements are waiting, counted with the verb that goes with the number: "3 signed agreements are".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %s: a number of signed agreements. */
					_n( '%s signed agreement is', '%s signed agreements are', 2, 'wpcredits-program-manager' ),
					number_format_i18n( 2 )
				);
			},
		),
		'agreements_overdue' => array(
			'means'   => __( 'The same count, with the verb the first line takes: "3 signed agreements have been".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %s: a number of signed agreements. */
					_n( '%s signed agreement has been', '%s signed agreements have been', 2, 'wpcredits-program-manager' ),
					number_format_i18n( 2 )
				);
			},
		),
		'review_days'        => array(
			'means'   => __( 'How many days the review window is.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $days ) {
				return number_format_i18n( $days() );
			},
		),
		'overdue_list'       => array(
			'means'   => __( 'Each Collaboration Agreement waiting longer than the window, with the day it was uploaded, one to a paragraph.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				$items = array();

				// Two institutions, their agreements uploaded six and five days ago.
				foreach ( array( array( 'Example University', -6 ), array( 'Example College', -5 ) ) as $waiting ) {
					$items[] = sprintf(
						/* translators: 1: institution name, 2: date the agreement was uploaded. */
						__( '%1$s, uploaded %2$s', 'wpcredits-program-manager' ),
						$waiting[0],
						$day( $waiting[1] )
					);
				}

				return implode( "\r\n\r\n", $items );
			},
		),
		'reason'             => array(
			'means'   => __( 'Why the template could not be used, as the site worded it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return __( 'The agreement template needs attention: it contains a placeholder the generator does not fill in.', 'wpcredits-program-manager' );
			},
		),
		'language'           => array(
			'means'   => __( 'The template\'s language code.', 'wpcredits-program-manager' ),
			'example' => 'en',
		),
		'institution_record' => array(
			'means'   => __( 'The institution\'s Airtable record.', 'wpcredits-program-manager' ),
			'example' => 'recEXAMPLE0000001',
		),
		'name_asked_for'     => array(
			'means'     => __( 'The name the institution asked to print on the Collaboration Agreement, as they typed it. It goes in the body, never in the subject.', 'wpcredits-program-manager' ),
			'example'   => 'Example University',
			'body_only' => true,
		),
		'member_name'        => array(
			'means'   => __( 'The member who is no longer one.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'left_on'            => array(
			'means'   => __( 'The day the membership ended.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return wp_date( get_option( 'date_format' ) );
			},
		),
		'ended_because'      => array(
			'means'   => __( 'Why the membership ended, as the site records it: removed, left or revoked.', 'wpcredits-program-manager' ),
			'example' => 'left',
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required, and
// those named in `$own` with what this email says of them, its explanation or its example, in place
// of the area's.
$wpcpm_take = static function ( array $names, array $required, array $own = array() ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders();
	$out = array();

	foreach ( $names as $name ) {
		$about        = ( isset( $own[ $name ] ) ? $own[ $name ] : array() ) + $all[ $name ];
		$out[ $name ] = array(
			'means'     => $about['means'],
			'example'   => $about['example'],
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

// In the emails about an application, the institution's name is the one the application gave: no
// record exists yet to name it otherwise.
$wpcpm_as_applied = array(
	'institution_name' => array(
		'means' => __( 'The institution\'s name as the application gave it.', 'wpcredits-program-manager' ),
	),
);

// The application's acknowledgement, with the call to book or without it.
$wpcpm_applied = static function ( $call ) use ( $wpcpm_body ) {
	$paragraphs = array(
		sprintf(
			/* translators: %s: the institution's name as it was given on the form. */
			__( 'Thank you for asking about the WordPress Credits Program for %s. Your application is with us.', 'wpcredits-program-manager' ),
			'{institution_name}'
		),
		sprintf(
			/* translators: %s: the application reference, e.g. APP-2026-0007. */
			__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
			'{reference}'
		),
		__( 'What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account on the program site and send you the Collaboration Agreement to sign.', 'wpcredits-program-manager' ),
		__( 'Please confirm this address by following this link. It tells us we are writing to a real address, and it does nothing else:', 'wpcredits-program-manager' ),
		'{verify_link}',
	);

	if ( $call ) {
		$paragraphs[] = __( 'You can also book a call with the Administrator who looks after your country:', 'wpcredits-program-manager' );
		$paragraphs[] = '{booking_link}';
	}

	$paragraphs[] = sprintf(
		/* translators: 1: the address the message went to, 2: site name. */
		__( 'This message went to %1$s because that address was given on the application form at %2$s. If it was not you, you can ignore it: nothing happens until the address is confirmed.', 'wpcredits-program-manager' ),
		'{applicant_email}',
		'{site_name}'
	);

	return $wpcpm_body( $paragraphs );
};

$wpcpm_applied_subject = sprintf(
	/* translators: 1: site name, 2: the application reference. */
	__( '[%1$s] We have your application: %2$s', 'wpcredits-program-manager' ),
	'{site_name}',
	'{reference}'
);

// A colleague's invitation, with its inviter named or with nobody to name (since 1.122.23): the
// opening sentence is the inviter's, and in both the promise of a reply is a value, empty when the
// invitation carries no address to reply to, which leaves its sentence out.
$wpcpm_invite = static function ( $named ) use ( $wpcpm_body ) {
	return $wpcpm_body(
		array(
			$named
				? sprintf(
					/* translators: 1: the member who invited them, 2: the institution's name, 3: the site's name. */
					__( '%1$s has invited you to see %2$s\'s students in the WordPress Credits Program on %3$s.', 'wpcredits-program-manager' ),
					'{inviter_name}',
					'{institution_name}',
					'{site_name}'
				)
				: sprintf(
					/* translators: 1: the institution's name, 2: the site's name. The opening of an invitation that has nobody to name as its sender. */
					__( 'You have been invited to see %1$s\'s students in the WordPress Credits Program on %2$s.', 'wpcredits-program-manager' ),
					'{institution_name}',
					'{site_name}'
				),
			__( 'Open this link, and press Accept on the page it opens:', 'wpcredits-program-manager' ),
			'{accept_link}',
			sprintf(
				/* translators: %s: the date the invitation stops working. */
				__( 'The link works until %s and can only be used once. If you do not have an account here yet, one is made for you and you get a second email with a link to set your password.', 'wpcredits-program-manager' ),
				'{expiry_date}'
			),
			sprintf(
				/* translators: %s: who a reply reaches, "the person who sent it"; the sentence is left out when nobody can be reached. */
				__( 'If you were not expecting this, ignore this message: opening the link only shows you the invitation, nothing happens until you press Accept, and it stops working on its own. Replying to this message reaches %s.', 'wpcredits-program-manager' ),
				'{replies_reach}'
			),
		)
	);
};

$wpcpm_invite_subject = sprintf(
	/* translators: 1: site name, 2: the institution's name. */
	__( '[%1$s] An invitation to %2$s', 'wpcredits-program-manager' ),
	'{site_name}',
	'{institution_name}'
);

// The notice of an upload, with what the courtesy scan noticed or with nothing noticed.
$wpcpm_landed = static function ( $flagged ) use ( $wpcpm_body ) {
	return $wpcpm_body(
		array(
			sprintf(
				/* translators: 1: institution name, 2: date, 3: who uploaded it. */
				__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
				'{institution_name}',
				'{uploaded_on}',
				'{uploaded_by}'
			),
			$flagged
				? sprintf(
					/* translators: %s: what the courtesy scan noticed in the PDF, in words, comma-separated, each with the PDF's own name for it in brackets, such as "an action that runs when the file is opened (/OpenAction)". */
					__( 'The courtesy scan noticed: %s. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
					'{scan_flags}'
				)
				: __( 'The courtesy scan noticed nothing. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
			'{queue_link}',
		)
	);
};

$wpcpm_landed_subject = sprintf(
	/* translators: 1: site name, 2: institution name. */
	__( '[%1$s] Signed agreement from %2$s is waiting for review', 'wpcredits-program-manager' ),
	'{site_name}',
	'{institution_name}'
);

// The revocation, with the country's contact named or with the neutral line.
$wpcpm_revoked = static function ( $contact ) use ( $wpcpm_body ) {
	return $wpcpm_body(
		array(
			sprintf(
				/* translators: 1: date, 2: the Administrator's name. */
				__( 'Your Collaboration Agreement was revoked on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
				'{revoked_on}',
				'{administrator_name}'
			),
			'{note}',
			__( 'Your account on this site is limited to the agreement panel until an agreement is in force again. Nothing about your students has been deleted.', 'wpcredits-program-manager' ),
			$contact
				? sprintf(
					/* translators: 1: country name, 2: the contact's email address. */
					__( 'Your program contact for %1$s is %2$s.', 'wpcredits-program-manager' ),
					'{country}',
					'{country_contact}'
				)
				: __( 'Write to the Administrator who has been in touch with your institution.', 'wpcredits-program-manager' ),
		)
	);
};

$wpcpm_revoked_subject = sprintf(
	/* translators: %s: site name. */
	__( '[%s] Your agreement has been revoked', 'wpcredits-program-manager' ),
	'{site_name}'
);

return array(
	'institution-applied'          => array(
		'trigger'      => __( 'When an institution applies through the form on the site and its country has a call to book: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'reference', 'verify_link', 'booking_link', 'applicant_email' ), array( 'verify_link', 'booking_link' ), $wpcpm_as_applied ),
	),
	'institution-applied-no-call'  => array(
		'trigger'      => __( 'When an institution applies through the form on the site and its country has no call to book: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'reference', 'verify_link', 'applicant_email' ), array( 'verify_link' ), $wpcpm_as_applied ),
	),
	'institution-application'      => array(
		'trigger'      => __( 'When an institution\'s application passes every check: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: institution name. */
			__( '[%1$s] New institution application from %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{institution_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An institution has applied to the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: institution name, 2: city, 3: country. */
					__( '%1$s, %2$s, %3$s', 'wpcredits-program-manager' ),
					'{institution_name}',
					'{city}',
					'{country}'
				),
				sprintf(
					/* translators: 1: contact person's name, 2: their email address. */
					__( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ),
					'{contact_name}',
					'{contact_email}'
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				'{queue_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'city', 'country', 'contact_name', 'contact_email', 'queue_link' ), array(), $wpcpm_as_applied ),
	),
	'institution-information'      => array(
		'trigger'      => __( 'When an Administrator asks an applicant a question about the institution\'s application: to the address the application gives, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: application reference, e.g. APP-2026-0007. */
			__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
			'{site_name}',
			'{reference}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for applying to the WordPress Credits Program. Before an Administrator can take your application further, they have one question:', 'wpcredits-program-manager' ),
				'{question}',
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reference', 'question' ), array( 'question' ) ),
	),
	'institution-declined'         => array(
		'trigger'      => __( 'When an Administrator rejects an institution\'s application: to the address the application gives. The reason the Administrator wrote stays on the application, and is never sent.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] About your application to the WordPress Credits Program', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for applying to the WordPress Credits Program, and for the time you took to write to us.', 'wpcredits-program-manager' ),
				__( 'We have read your institution\'s application, and we cannot accept it into the program at this time. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'You are welcome to apply again later, for example when your programs change. A new application is read on its own merits, and this decision does not count against it.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name' ), array() ),
	),
	'institution-invite'           => array(
		'trigger'      => __( 'When a member of an institution, or an Administrator acting for it, invites a colleague to its account: to the colleague\'s address, with the inviter\'s address to reply to while their account holds one.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_invite_subject,
		'body'         => $wpcpm_invite( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'inviter_name', 'institution_name', 'accept_link', 'expiry_date', 'replies_reach' ), array( 'accept_link' ) ),
	),
	'institution-invite-no-sender' => array(
		'trigger'      => __( 'When a colleague is invited to an institution\'s account and the invitation has nobody to name as its sender, the inviter\'s account being gone or holding no name: to the colleague\'s address, with the inviter\'s address to reply to while their account holds one.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_invite_subject,
		'body'         => $wpcpm_invite( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'accept_link', 'expiry_date', 'replies_reach' ), array( 'accept_link' ) ),
	),
	'agreement-received'           => array(
		'trigger'      => __( 'When an institution uploads its signed Collaboration Agreement: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] We received your signed agreement', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date the file arrived, 2: who uploaded it. */
					__( 'We received your signed Collaboration Agreement on %1$s, uploaded by %2$s.', 'wpcredits-program-manager' ),
					'{uploaded_on}',
					'{uploaded_by}'
				),
				sprintf(
					/* translators: %s: how long the program takes, counted, such as "3 working days". */
					__( 'An Administrator will read it and you will get an email either way, usually within %s.', 'wpcredits-program-manager' ),
					'{review_window}'
				),
				__( 'If it was the wrong file, withdraw it from the agreement panel on the site and upload the right one.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'uploaded_on', 'uploaded_by', 'review_window' ), array() ),
	),
	'agreement-landed'             => array(
		'trigger'      => __( 'When an institution uploads its signed Collaboration Agreement and the courtesy scan notices nothing: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_landed_subject,
		'body'         => $wpcpm_landed( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'uploaded_on', 'uploaded_by', 'queue_link' ), array() ),
	),
	'agreement-landed-flagged'     => array(
		'trigger'      => __( 'When an institution uploads its signed Collaboration Agreement and the courtesy scan notices something: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_landed_subject,
		'body'         => $wpcpm_landed( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'uploaded_on', 'uploaded_by', 'scan_flags', 'queue_link' ), array( 'scan_flags' ) ),
	),
	'agreement-accepted'           => array(
		'trigger'      => __( 'When an Administrator accepts an institution\'s signed Collaboration Agreement: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your agreement is accepted', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Your account on the site is open.', 'wpcredits-program-manager' ),
					'{accepted_on}'
				),
				__( 'You can now see your students and their progress, open one student to read their Student Report Card, and download your own copy of the signed agreement from the card at the foot of the page.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'accepted_on', 'dashboard_link' ), array() ),
	),
	'agreement-reinstated'         => array(
		'trigger'      => __( 'When an Administrator puts an institution\'s revoked Collaboration Agreement back in force: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your agreement is in force again', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: date the agreement was put back in force. */
					__( 'Your Collaboration Agreement was put back in force on %s. Your account on the site is open again.', 'wpcredits-program-manager' ),
					'{reinstated_on}'
				),
				__( 'Nothing was removed while it was out of force: your students, their progress and your copy of the signed agreement are where they were.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reinstated_on', 'dashboard_link' ), array() ),
	),
	'agreement-returned'           => array(
		'trigger'      => __( 'When an Administrator sends an institution\'s signed Collaboration Agreement back with a note: to every member of the institution, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your signed agreement needs a change', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date, 2: the Administrator's name. */
					__( 'Your signed Collaboration Agreement was sent back on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
					'{returned_on}',
					'{administrator_name}'
				),
				'{note}',
				__( 'Upload the corrected copy from the agreement panel on the site. Replying to this message reaches the Administrator who wrote the note.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'returned_on', 'administrator_name', 'note' ), array( 'note' ) ),
	),
	'agreement-revoked'            => array(
		'trigger'      => __( 'When an Administrator revokes an institution\'s Collaboration Agreement and its country has a program contact: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_revoked_subject,
		'body'         => $wpcpm_revoked( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'revoked_on', 'administrator_name', 'note', 'country', 'country_contact' ), array( 'note', 'country_contact' ) ),
	),
	'agreement-revoked-no-contact' => array(
		'trigger'      => __( 'When an Administrator revokes an institution\'s Collaboration Agreement and its country has no program contact on file: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_revoked_subject,
		'body'         => $wpcpm_revoked( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'revoked_on', 'administrator_name', 'note' ), array( 'note' ) ),
	),
	'agreement-reminder'           => array(
		'trigger'      => __( 'Once a day, when signed agreements have waited longer than the review window: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: how many signed agreements, counted with their verb, such as "3 signed agreements are". */
			__( '[%1$s] %2$s waiting for review', 'wpcredits-program-manager' ),
			'{site_name}',
			'{agreements_waiting}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: how many signed agreements, counted with their verb, such as "3 signed agreements have been", 2: number of days in the review window. */
					__( '%1$s waiting longer than the %2$s-day review window.', 'wpcredits-program-manager' ),
					'{agreements_overdue}',
					'{review_days}'
				),
				'{overdue_list}',
				'{queue_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'agreements_waiting', 'agreements_overdue', 'review_days', 'overdue_list', 'queue_link' ), array() ),
	),
	'agreement-template'           => array(
		'trigger'      => __( 'When an institution asks the site to generate the Collaboration Agreement and the template cannot be used: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] The Collaboration Agreement template needs attention', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An institution asked the site to generate the Collaboration Agreement and the template could not be used, so nothing was generated and they were told the template needs attention.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: what the template refused with, 2: language code, 3: institution record ID, 4: the name that was asked for. */
					__( 'The reason given was: %1$s (template language %2$s, institution %3$s, name asked for "%4$s").', 'wpcredits-program-manager' ),
					'{reason}',
					'{language}',
					'{institution_record}',
					'{name_asked_for}'
				),
				__( 'The template lives in the plugin, in includes/templates/. A bracketed token other than the institution name is refused on purpose: it would otherwise print on a document somebody signs. Nothing can be generated in this language until it is fixed.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reason', 'language', 'institution_record', 'name_asked_for' ), array() ),
	),
	'member-last'                  => array(
		'trigger'      => __( 'When the last member of an institution leaves or is removed: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: institution name. */
			__( '[%1$s] %2$s has no members left', 'wpcredits-program-manager' ),
			'{site_name}',
			'{institution_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: person, 2: institution name, 3: record ID, 4: reason, 5: date. */
					__( '%1$s is no longer a member of %2$s (%3$s) as of %5$s (%4$s). No account can act for this institution on the site now.', 'wpcredits-program-manager' ),
					'{member_name}',
					'{institution_name}',
					'{institution_record}',
					'{ended_because}',
					'{left_on}'
				),
				__( 'The Airtable record, the agreement, the students and the history are untouched. A former member can be re-added in one click from the Institutions screen, or a new account created there.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'member_name', 'institution_name', 'institution_record', 'left_on', 'ended_because' ), array() ),
	),
);
