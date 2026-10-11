<?php
/**
 * The Sponsors area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one the code wrote before 1.122.22, under the same translatable
 * string (`WPCPM_Sponsor_Application`, `WPCPM_Sponsor_Agreement`, `WPCPM_Sponsor_Interests`,
 * `WPCPM_Sponsor_Mentors`, `WPCPM_Sponsor_Claims` and `WPCPM_Sponsor_Posts`), but these four: the
 * program's time to answer, a sentence `_n()` used to word whole, takes its counted words as one
 * value now (`{review_window}`); the returned agreement's "An Administrator read your Collaboration
 * Agreement", said when the Administrator's account holds no name, is the value `{returned_by}`
 * takes then; and the low codes' two, the subject and the body, whose counts the code wrote as
 * numbers (`%d`), hold them as placeholders now (`%s`), the same words.
 *
 * An email that said other words in one case than in another is two templates: the application's
 * acknowledgement with the logo kept and without it. The request to sponsor a mentor, which was sent
 * under the interests card's id, has an id of its own.
 *
 * The emails whose subject carries what a person wrote, an offer's title or a post's, keep that
 * subject as plain text, as their code always did (`plain_subject`, in the sender).
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

	return array(
		'site_name'          => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'company_name'       => array(
			'means'   => __( 'The company\'s name; its Airtable record when the site holds no name for it.', 'wpcredits-program-manager' ),
			'example' => 'Example Hosting',
		),
		'reference'          => array(
			'means'   => __( 'The application\'s reference, such as SAPP-2026-0007.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf( 'SAPP-%s-0007', wp_date( 'Y' ) );
			},
		),
		'applicant_email'    => array(
			'means'     => __( 'The address the application gave, which this email is sent to, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'partners@example.test',
			'body_only' => true,
		),
		'website'            => array(
			'means'     => __( 'The website the application gave, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'https://hosting.example.test',
			'body_only' => true,
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
			'means'   => __( 'The Sponsors screen, where it waits for an Administrator.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-sponsors&tab=queue' );
			},
		),
		'question'           => array(
			'means'     => __( 'The Administrator\'s question, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Which of your plans would students get, and for how long?',
			'body_only' => true,
		),
		'uploaded_on'        => array(
			'means'   => __( 'The day the signed Collaboration Agreement was uploaded.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day( -1 );
			},
		),
		'uploaded_by'        => array(
			'means'   => __( 'Who uploaded it: their name, or "somebody at the company" when their account holds none.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'review_window'      => array(
			'means'   => __( 'How long the program takes to answer, counted: "3 working days".', 'wpcredits-program-manager' ),
			'example' => static function () {
				$days = max( 1, (int) ( class_exists( 'WPCPM_Settings' ) ? WPCPM_Settings::get_value( 'agreement_review_days', 3 ) : 3 ) );

				return sprintf(
					/* translators: %s: a number of working days. */
					_n( '%s working day', '%s working days', $days, 'wpcredits-program-manager' ),
					number_format_i18n( $days )
				);
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
		'returned_by'        => array(
			'means'   => __( 'The Administrator who sent it back, or "An Administrator" when their account holds no name.', 'wpcredits-program-manager' ),
			'example' => 'Cleo Marsh',
		),
		'note'               => array(
			'means'     => __( 'The Administrator\'s note, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Please sign page 2 as well, and date it.',
			'body_only' => true,
		),
		'dashboard_link'     => array(
			'means'   => __( 'The Sponsor Dashboard, while its page is published. Empty leaves its line out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_SPONSOR );
			},
		),
		'interest'           => array(
			'means'     => __( 'What the sponsor said, as the dated line the Sponsors table keeps: the day, who, the ways ticked, the events and the note as typed. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return WPCPM_Sponsor_Interests::line( array( 'Sponsor a mentor or multiple mentors' ), array(), 'We could host a study day in the spring.', 'Ada Okonjo', wp_date( 'Y-m-d' ) );
			},
			'body_only' => true,
		),
		'mentor_name'        => array(
			'means'   => __( 'The mentor the sponsor would like to sponsor.', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'mentor_profile'     => array(
			'means'   => __( 'The mentor\'s WordPress.org profile.', 'wpcredits-program-manager' ),
			'example' => 'https://profiles.wordpress.org/mentor-two/',
		),
		'offer_title'        => array(
			'means'   => __( 'The offer\'s title, as the sponsor wrote it.', 'wpcredits-program-manager' ),
			'example' => 'Hosting for students',
		),
		'codes_left'         => array(
			'means'   => __( 'How many codes the offer has left.', 'wpcredits-program-manager' ),
			'example' => '3',
		),
		'low_mark'           => array(
			'means'   => __( 'The number of codes the offer warns below.', 'wpcredits-program-manager' ),
			'example' => '5',
		),
		'offers_link'        => array(
			'means'   => __( 'The Offers card on the Sponsor Dashboard, for the sponsor the codes are of.', 'wpcredits-program-manager' ),
			'example' => static function () {
				// As the sender builds it: the sponsor named, so an Administrator lands on its card.
				return add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, 'recEXAMPLE0000001', WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_SPONSOR ) ) . '#wpcpm-sponsor-offers';
			},
		),
		'claimant_name'      => array(
			'means'   => __( 'The person who claimed the code.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'code_ending'        => array(
			'means'     => __( 'The code\'s last four characters. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'X7Q2',
			'body_only' => true,
		),
		'offers_screen_link' => array(
			'means'   => __( 'The Offers tab of the Sponsors screen.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-sponsors&tab=offers' );
			},
		),
		'author_name'        => array(
			'means'   => __( 'The post\'s author, whose account may hold no name. The email needs it.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'post_title'         => array(
			'means'   => __( 'The post\'s title, as its author wrote it, which may be empty. The email needs it.', 'wpcredits-program-manager' ),
			'example' => 'Our first study day',
		),
		'edit_link'          => array(
			'means'   => __( 'The list of posts in wp-admin, where the author edits it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'edit.php' );
			},
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

// In the emails about an application, the company's name is the one the application gave: no
// record exists yet to name it otherwise.
$wpcpm_as_applied = array(
	'company_name' => array(
		'means' => __( 'The company\'s name as the application gave it.', 'wpcredits-program-manager' ),
	),
);

// The application's acknowledgement, with the logo kept or with the line saying it was not.
$wpcpm_applied = static function ( $logo_kept ) use ( $wpcpm_body ) {
	$paragraphs = array(
		sprintf(
			/* translators: %s: the company's name as it was given on the form. */
			__( 'Thank you for offering to sponsor the WordPress Credits Program on behalf of %s. Your application is with us.', 'wpcredits-program-manager' ),
			'{company_name}'
		),
		sprintf(
			/* translators: %s: the application reference, e.g. SAPP-2026-0007. */
			__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
			'{reference}'
		),
	);

	if ( ! $logo_kept ) {
		$paragraphs[] = __( 'One thing about the logo: the site was taking more applications than usual when yours arrived, so the logo files you attached were not kept. Your answers were, and nothing else about the application was affected. If the program says yes, you can send the logo after your account is set up.', 'wpcredits-program-manager' );
	}

	$paragraphs[] = __( 'What happens next: an Administrator reads every application by hand and checks the company and its products against the program\'s licensing and trademark rules, so a reply takes a few working days. If the program says yes, we will set up an account on the program site and send you a link to choose a password.', 'wpcredits-program-manager' );
	$paragraphs[] = sprintf(
		/* translators: 1: the address the message went to, 2: site name. */
		__( 'This message went to %1$s because that address was given on the sponsor application form at %2$s. If it was not you, you can ignore it: nothing happens until an Administrator decides.', 'wpcredits-program-manager' ),
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

return array(
	'sponsor-applied'              => array(
		'trigger'      => __( 'When a company applies to sponsor the program through the form on the site: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'company_name', 'reference', 'applicant_email' ), array(), $wpcpm_as_applied ),
	),
	'sponsor-applied-no-logo'      => array(
		'trigger'      => __( 'When a company applies to sponsor the program through the form on the site while it is taking more applications than usual, so the logo files are not kept: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'company_name', 'reference', 'applicant_email' ), array(), $wpcpm_as_applied ),
	),
	'sponsor-application'          => array(
		'trigger'      => __( 'When a sponsor application passes every check: to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: company name. */
			__( '[%1$s] New sponsor application from %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'A company has applied to sponsor the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: company name, 2: website. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					'{company_name}',
					'{website}'
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
		'placeholders' => $wpcpm_take( array( 'site_name', 'company_name', 'website', 'contact_name', 'contact_email', 'queue_link' ), array(), $wpcpm_as_applied ),
	),
	'sponsor-information'          => array(
		'trigger'      => __( 'When an Administrator asks a company a question about its sponsor application: to the address the application gives, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: application reference, e.g. SAPP-2026-0007. */
			__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
			'{site_name}',
			'{reference}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for offering to sponsor the WordPress Credits Program. Before an Administrator can take your application further, they have one question:', 'wpcredits-program-manager' ),
				'{question}',
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reference', 'question' ), array( 'question' ) ),
	),
	'sponsor-declined'             => array(
		'trigger'      => __( 'When an Administrator rejects a sponsor application: to the address the application gives. The reason the Administrator wrote stays on the application, and is never sent.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] About your application to sponsor the WordPress Credits Program', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for your interest in sponsoring the WordPress Credits Program, and for the time you took to write to us.', 'wpcredits-program-manager' ),
				__( 'We have read your company\'s application to sponsor the program, and we cannot accept it at this time. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'You are welcome to apply again later, for example when your products or the program change. A new application is read on its own merits, and this decision does not count against it.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name' ), array() ),
	),
	'sponsor-agreement-received'   => array(
		'trigger'      => __( 'When a sponsor uploads its signed Collaboration Agreement: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: company name. */
			__( '[%1$s] %2$s uploaded a signed Collaboration Agreement', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: company name, 2: date, 3: who uploaded it. */
					__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
					'{company_name}',
					'{uploaded_on}',
					'{uploaded_by}'
				),
				sprintf(
					/* translators: %s: how long the program takes, counted, such as "3 working days". */
					__( 'The program aims to answer within %s.', 'wpcredits-program-manager' ),
					'{review_window}'
				),
				__( 'Accept or return it on the Administrator Dashboard. Read it first on the Sponsors screen:', 'wpcredits-program-manager' ),
				'{queue_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take(
			array( 'site_name', 'company_name', 'uploaded_on', 'uploaded_by', 'review_window', 'queue_link' ),
			array(),
			array(
				// The agreements waiting on the Sponsors screen, where the link lands.
				'queue_link' => array(
					'example' => static function () {
						return admin_url( 'admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements' );
					},
				),
			)
		),
	),
	'sponsor-agreement-accepted'   => array(
		'trigger'      => __( 'When an Administrator accepts a sponsor\'s signed Collaboration Agreement: to every account of the sponsor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement is accepted', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Nothing about your dashboard changes: it never depended on an agreement.', 'wpcredits-program-manager' ),
					'{accepted_on}'
				),
				__( 'You can download the copy we hold from the agreement card on your dashboard.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'accepted_on', 'dashboard_link' ), array() ),
	),
	'sponsor-agreement-returned'   => array(
		'trigger'      => __( 'When an Administrator sends a sponsor\'s signed Collaboration Agreement back with a note: to every account of the sponsor, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement came back with a note', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the Administrator's name, or "An Administrator" when their account holds none. */
					__( '%s read your Collaboration Agreement and sent it back, with this note:', 'wpcredits-program-manager' ),
					'{returned_by}'
				),
				'{note}',
				__( 'Upload the corrected document from the agreement card on your dashboard, or reply to this message.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'returned_by', 'note', 'dashboard_link' ), array( 'note' ) ),
	),
	'sponsor-agreement-revoked'    => array(
		'trigger'      => __( 'When an Administrator takes a sponsor\'s Collaboration Agreement out of force: to every account of the sponsor, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement is no longer in force', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Your Collaboration Agreement is no longer in force, with this note from the program:', 'wpcredits-program-manager' ),
				'{note}',
				__( 'Your dashboard, your offers and your codes are unchanged: they never depended on an agreement. Reply to this message if you would like to talk about a new one.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'note' ), array( 'note' ) ),
	),
	'sponsor-agreement-reinstated' => array(
		'trigger'      => __( 'When an Administrator puts a sponsor\'s Collaboration Agreement back in force: to every account of the sponsor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement is back in force', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => sprintf(
			/* translators: %s: today's date. */
			__( 'Your Collaboration Agreement is in force again as of %s. Nothing else changed.', 'wpcredits-program-manager' ),
			'{reinstated_on}'
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reinstated_on' ), array() ),
	),
	'sponsor-interest'             => array(
		'trigger'      => __( 'When a sponsor says on the Sponsor Dashboard what else it would like to support: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: company name. */
			__( '[WordPress Credits] %s would like to support the program', 'wpcredits-program-manager' ),
			'{company_name}'
		),
		'body'         => sprintf(
			/* translators: 1: company name, 2: the dated line. */
			__( "%1\$s said on the Sponsor Dashboard:\n\n%2\$s\n\nThe full history is in the Sponsorship interests column of the Sponsors table.", 'wpcredits-program-manager' ),
			'{company_name}',
			'{interest}'
		),
		'placeholders' => $wpcpm_take( array( 'company_name', 'interest' ), array( 'interest' ) ),
	),
	'sponsor-mentor-interest'      => array(
		'trigger'      => __( 'When a sponsor says on the Sponsor Dashboard that it would like to sponsor a mentor who is looking for one: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: company name, 2: mentor name. */
			__( '[WordPress Credits] %1$s would like to sponsor %2$s', 'wpcredits-program-manager' ),
			'{company_name}',
			'{mentor_name}'
		),
		'body'         => sprintf(
			/* translators: 1: company name, 2: mentor name, 3: the mentor's WordPress.org profile. */
			__( '%1$s said on the Sponsor Dashboard that it would like to sponsor the mentor %2$s (%3$s). Please get in touch with both.', 'wpcredits-program-manager' ),
			'{company_name}',
			'{mentor_name}',
			'{mentor_profile}'
		),
		'placeholders' => $wpcpm_take( array( 'company_name', 'mentor_name', 'mentor_profile' ), array() ),
	),
	'offer-low-stock'              => array(
		'trigger'      => __( 'When a claim leaves an offer with fewer codes than it warns below, once until codes are added: to every account of the sponsor, and to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: offer title, 3: codes left. */
			__( '[%1$s] %2$s: %3$s codes left', 'wpcredits-program-manager' ),
			'{site_name}',
			'{offer_title}',
			'{codes_left}'
		),
		'body'         => sprintf(
			/* translators: 1: offer title, 2: codes left, 3: the threshold, 4: the Offers card's address. */
			__( "The offer \"%1\$s\" has %2\$s codes left; it warns below %3\$s.\n\nAdd more codes, or pause the offer, from the Offers card: %4\$s\n\nThis is sent once. Adding codes arms it again.", 'wpcredits-program-manager' ),
			'{offer_title}',
			'{codes_left}',
			'{low_mark}',
			'{offers_link}'
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'offer_title', 'codes_left', 'low_mark', 'offers_link' ), array() ),
	),
	'claim-problem'                => array(
		'trigger'      => __( 'When somebody who claimed a code reports that it did not work: to the sponsor\'s assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator, with the claimant\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: offer title. */
			__( '[%1$s] A code from %2$s did not work', 'wpcredits-program-manager' ),
			'{site_name}',
			'{offer_title}'
		),
		'body'         => sprintf(
			/* translators: 1: the claimant's name, 2: offer title, 3: the code's last four characters, 4: the Sponsors screen's address. */
			__( "%1\$s reports that their code for \"%2\$s\" (ending %3\$s) did not work.\n\nThe sponsor's offers and the people who claimed from them are on the Sponsors screen: %4\$s\n\nReply to this person from your own mailbox. The site never sends a code by mail.", 'wpcredits-program-manager' ),
			'{claimant_name}',
			'{offer_title}',
			'{code_ending}',
			'{offers_screen_link}'
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'offer_title', 'claimant_name', 'code_ending', 'offers_screen_link' ), array() ),
	),
	'sponsor-post-returned'        => array(
		'trigger'      => __( 'When a sponsor\'s post is sent back to its author as a draft with a note: to the post\'s author.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: the post title. */
			__( 'Your post "%s" needs a change before it is published', 'wpcredits-program-manager' ),
			'{post_title}'
		),
		'body'         => sprintf(
			/* translators: 1: the author's name, 2: the post title, 3: the Administrator's note, 4: the wp-admin posts list URL. */
			__( "Hello %1\$s,\n\nAn Administrator read your post \"%2\$s\" and sent it back as a draft with this note:\n\n%3\$s\n\nEdit it here and submit it for review again: %4\$s", 'wpcredits-program-manager' ),
			'{author_name}',
			'{post_title}',
			'{note}',
			'{edit_link}'
		),
		'placeholders' => $wpcpm_take( array( 'post_title', 'author_name', 'note', 'edit_link' ), array( 'post_title', 'author_name', 'note' ) ),
	),
);
