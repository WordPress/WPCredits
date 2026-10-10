<?php
/**
 * The Invitations area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one `WPCPM_Mail::welcome_email()` wrote before 1.122.19, under the
 * same translatable string, and the bodies are joined by the registry with the line ending WordPress
 * gives its own email, "\r\n".
 *
 * Each invitation wraps WordPress's own lines: `{set_password_link}` is what WordPress wrote, its
 * sentence and the link that sets the password, kept whole so a change to how WordPress words it
 * arrives on its own; and `{username}` is the username when those lines do not show it already
 * (WordPress 7.1 and later do not), else empty, which leaves its sentence out.
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
		'trigger'      => __( 'When a mentor is sent the invitation to their account: from the Mentors screen, or as the sync creates the account with invitation emails on.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your mentor account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'You have been set up as a mentor on the WordPress Credits Program, and this is your account on the program site. Your students, their programs and the calls they book with you all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below, then publish the hours you are free so your students can book calls with you.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your students:', 'wpcredits-program-manager' )
		),
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
			__( 'Set your password using the link below. The first and only step after that is the Collaboration Agreement: generate the program\'s or upload your own, sign it, and upload the signed copy. A program manager then accepts it and the rest of the site opens.', 'wpcredits-program-manager' ),
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
			__( 'Set your password using the link below, then check your company profile and tell us how you would like to support the program.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your Sponsor Dashboard:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_SPONSOR ),
	),
);
