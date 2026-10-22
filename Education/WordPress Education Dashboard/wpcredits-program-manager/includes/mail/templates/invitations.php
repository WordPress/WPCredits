<?php
/**
 * The Invitations area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one `WPCPM_Mail::welcome_email()` wrote before 1.122.19, under the
 * same translatable string, but one: the institution's "An Administrator then accepts it" said
 * "A program manager then accepts it" until 1.122.20. The bodies are joined by the registry with the
 * line ending WordPress gives its own email, "\r\n".
 *
 * Each invitation wraps WordPress's own lines: `{set_password_link}` is what WordPress wrote, its
 * sentence and the link that sets the password, kept whole so a change to how WordPress words it
 * arrives on its own; and `{username}` is the username when those lines do not show it already
 * (WordPress 7.1 and later do not), else empty, which leaves its sentence out.
 *
 * The mentor's invitation carries the welcome a new mentor is sent after those lines (1.122.33): the
 * paragraphs of the Mentors automation's email in Airtable that this site replaces, with the six
 * public links a mentor needs. The links that are private, the student courses' group invitation
 * links and the mentor resource folder's, are never in the shipped text: one bracketed line stands
 * where they go, and the mentor coordinator types them into the template on the site, in Tools >
 * Emails. The welcome has no placeholder of its own.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The placeholders every invitation has. An example is a value, or a function that works one out from
// the site when a preview or a test asks for it, so building an invitation reads nothing it does not use.
$wpcpm_placeholders = static function ( $audience ) {
	return array(
		'site_name'         => array(
			'means'    => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example'  => static function () {
				return WPCPM_Mail::site_name();
			},
			'required' => false,
		),
		'set_password_link' => array(
			'means'     => __( 'WordPress\'s own lines with the link that sets the password. Every invitation needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return __( 'To set your password, visit the following address:', 'wpcredits-program-manager' ) . "\r\n\r\n" . add_query_arg(
					array(
						'login'  => 'ada.example',
						'key'    => 'EXAMPLE-KEY-NOT-A-REAL-LINK',
						'action' => 'rp',
					),
					wp_login_url()
				);
			},
			'required'  => true,
			// In the body alone: a subject is never a link a person can follow, and it is the one part
			// of an email the mail log keeps, for 30 days, so the live key may never reach it.
			'body_only' => true,
		),
		'username'          => array(
			'means'    => __( 'The person\'s username, when WordPress\'s own lines do not show it. Empty leaves its sentence out.', 'wpcredits-program-manager' ),
			'example'  => 'ada.example',
			'required' => false,
		),
		'login_url'         => array(
			'means'    => __( 'The login page.', 'wpcredits-program-manager' ),
			'example'  => static function () {
				return wp_login_url();
			},
			'required' => false,
		),
		'dashboard_link'    => array(
			'means'    => __( 'The person\'s dashboard, while its page is published. Empty leaves its lines out.', 'wpcredits-program-manager' ),
			'example'  => static function () use ( $audience ) {
				return WPCPM_Mail_Templates::page_of_type( $audience );
			},
			'required' => false,
		),
	);
};

/**
 * One invitation's body: its opening, its next step, WordPress's lines, then the lines every
 * invitation shares, and the line naming the person's dashboard, under its label.
 *
 * @param string $opening What the account is.
 * @param string $next    What to do first.
 * @param string $label   The line above the dashboard's address.
 * @return string
 */
$wpcpm_body = static function ( $opening, $next, $label ) {
	return implode(
		"\n",
		array(
			$opening,
			'',
			$next,
			'',
			'{set_password_link}',
			'',
			/* translators: A sentence of its own. End it with a full stop: the site reads a full stop followed by a space as the end of a sentence, and leaves the sentence after it, the username's, out on its own when there is no username to give. */
			__( 'The link above sets your password and stops working after a day.', 'wpcredits-program-manager' ) . ' ' . sprintf(
				/* translators: %s: the WordPress username. A sentence of its own, ending in a full stop, after the one on the link: the site leaves it out when the username is not needed, and keeps the sentence before it. */
				__( 'Your username is %s.', 'wpcredits-program-manager' ),
				'{username}'
			),
			'',
			__( 'After that, sign in at the login page. If the password link has expired, open the login page, choose "Lost your password?" and enter your username or email address to get a fresh one.', 'wpcredits-program-manager' ),
			'{login_url}',
			'',
			__( 'Each new invitation or password email replaces the link in the ones before it, so if you have more than one, use the newest.', 'wpcredits-program-manager' ),
			'',
			$label,
			'{dashboard_link}',
		)
	) . "\n";
};

// The welcome a new mentor is sent, after the mentor's invitation's own lines: each paragraph, each
// line of a list and the bracketed line is a string of its own, and a line holding a link takes it as
// "%s". The list's hyphens are layout, outside the strings. No placeholder, and no link but the seven
// public ones.
$wpcpm_welcome = implode(
	"\n",
	array(
		__( 'Thank you, and congratulations on completing the WordPress Credits Mentor\'s Course! As soon as the first students are assigned to you, you will receive the introduction email connecting you with them.', 'wpcredits-program-manager' ),
		'',
		__( 'Here are a few details you will need for now.', 'wpcredits-program-manager' ),
		'',
		__( 'Your Mentor Report Card on this site has your students on it, a triage of who needs a call, your call notes, and a calendar students book directly from. Once you have set your password, check that your account details look right and set your availability: students cannot book a call with you until you have.', 'wpcredits-program-manager' ),
		'',
		__( 'Start here:', 'wpcredits-program-manager' ),
		'- ' . sprintf(
			/* translators: %s: the address of the post that welcomes mentors to their Mentor Report Card. Keep it as it is. */
			__( 'Mentors: Welcome to your Mentor Report Card, %s', 'wpcredits-program-manager' ),
			'https://wordpresseducation.org/2026/08/03/welcome-to-your-mentor-report-card/'
		),
		'- ' . sprintf(
			/* translators: %s: the address of the post that welcomes students to their Student Report Card. Keep it as it is. */
			__( 'Students: Welcome to your Student Report Card, %s', 'wpcredits-program-manager' ),
			'https://wordpresseducation.org/2026/08/03/welcome-to-your-student-report-card/'
		),
		'',
		__( 'Access to the student courses, so you always know what is being asked of your mentees (join each course through its invitation link to see it):', 'wpcredits-program-manager' ),
		'- ' . sprintf(
			/* translators: %s: the address of the course on learn.wordpress.org. Keep it as it is. */
			__( 'WordPress Credits Foundations (the base course, for 150 hours or more): %s', 'wpcredits-program-manager' ),
			'https://learn.wordpress.org/course/wordpress-credits-foundations/'
		),
		'- ' . sprintf(
			/* translators: %s: the address of the course on learn.wordpress.org. Keep it as it is. */
			__( 'WordPress Credits Dev Track: %s', 'wpcredits-program-manager' ),
			'https://learn.wordpress.org/course/wordpress-credits-developer-track/'
		),
		'- ' . sprintf(
			/* translators: %s: the address of the course on learn.wordpress.org. Keep it as it is. */
			__( 'WordPress Credits Design Track: %s', 'wpcredits-program-manager' ),
			'https://learn.wordpress.org/course/wordpress-credits-designer-track/'
		),
		'- ' . sprintf(
			/* translators: %s: the address of the course on learn.wordpress.org. Keep it as it is. */
			__( 'WordPress Credits Translation Track: %s', 'wpcredits-program-manager' ),
			'https://learn.wordpress.org/course/wordpress-credits-translation-track/'
		),
		'- ' . sprintf(
			/* translators: %s: the address of the course on learn.wordpress.org. Keep it as it is. */
			__( 'WordPress Credits: Intro to Contribution (short contributions that are easy to start with, for 50 hours): %s', 'wpcredits-program-manager' ),
			'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/'
		),
		/* translators: A note to the people who run the program, not to the mentor: it stands where the private invitation links go, and is replaced on the site (Tools > Emails) before mentors are sent this email. Keep the square brackets. */
		__( '[Each course\'s invitation link and the mentor resource folder\'s link are added here in Tools > Emails before this email is sent to mentors.]', 'wpcredits-program-manager' ),
		'',
		__( 'Private Slack channel: we will invite you to the program\'s private Slack channel for mentors, where you can report updates or ask any questions that come up during your mentorship.', 'wpcredits-program-manager' ),
		'',
		__( 'Discount codes for students\' tools: you will find them in the canvas inside that channel. Please share them only with your students, and only when they ask for each one. Thank you!', 'wpcredits-program-manager' ),
		'',
		__( 'We have shared the mentor resource folder with you. Feel free to create your own subfolder to organize your notes on your students and any related resources.', 'wpcredits-program-manager' ),
		'',
		__( 'If you have any questions, please reply to this email or write in the private Slack channel.', 'wpcredits-program-manager' ),
		'',
		__( 'Thanks so much for your help, and a big hug.', 'wpcredits-program-manager' ),
	)
) . "\n";

return array(
	'invite-student'             => array(
		'trigger'      => __( 'When a student is sent the invitation to their account: from the Students screen, or as the sync creates the account with invitation emails on.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Welcome to the WordPress Credits Program', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'You have been enrolled on the WordPress Credits Program, and this is your account on the program site. Your program details, your mentor and your report form all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below, then check your details and book your first call with your mentor.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your Student Report Card:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_STUDENT ),
	),
	'invite-mentor'              => array(
		'trigger'      => __( 'When a mentor is sent the invitation to their account: when an application to mentor is activated, from the Mentors screen, or as the sync creates the account with invitation emails on.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your mentor account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		// The invitation as it stands, a blank line, then the welcome, which ends with a line break as the
		// invitation's own lines do.
		'body'         => $wpcpm_body(
			__( 'You have been set up as a mentor on the WordPress Credits Program, and this is your account on the program site. Your students, their programs and the calls they book with you all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below, then publish the hours you are free so your students can book calls with you.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your students:', 'wpcredits-program-manager' )
		) . "\n" . $wpcpm_welcome,
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_MENTOR ),
	),
	'invite-institution'         => array(
		'trigger'      => __( 'When an institution\'s member is sent the invitation to its account before the institution\'s Collaboration Agreement is on file.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your institution account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'Your institution has been set up on the WordPress Credits Program, and this is its account on the program site. Your Collaboration Agreement with the program, and once it is in place your students and their progress, all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below. The first and only step after that is the Collaboration Agreement: generate the program\'s or upload your own, sign it, and upload the signed copy. An Administrator then accepts it and the rest of the site opens.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Where the agreement is uploaded:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_INSTITUTION ),
	),
	'invite-institution-settled' => array(
		'trigger'      => __( 'When an institution\'s member is sent the invitation to its account once the institution\'s Collaboration Agreement is on file.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your institution account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'Your institution has been set up on the WordPress Credits Program, and this is its account on the program site. Your Collaboration Agreement with the program, your students and their progress all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below. Your Collaboration Agreement is on file, so your account is open.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your Institution Dashboard:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_INSTITUTION ),
	),
	'invite-sponsor'             => array(
		'trigger'      => __( 'When a sponsor\'s member is sent the invitation to its account, from the Sponsors screen.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your sponsor account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'Your company has been set up as a sponsor of the WordPress Credits Program, and this is your account on the program site. Your offer, the mentors you sponsor and your contact at the program all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below, then check your company profile.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your Sponsor Dashboard:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_SPONSOR ),
	),
);
