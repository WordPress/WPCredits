<?php
/**
 * Every email the site can send, by name.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The names the Emails tool shows for what the site sends.
 *
 * An email's id is the context the plugin hands `WPCPM_Mail` when it sends it, so the log can name
 * it without the sending code knowing the log exists; WordPress's own emails and the Two Factor
 * plugin's sign-in code are named by the filters they pass through on the way out
 * (`wordpress_filters()`), which the capture hooks. An email nothing names is "Other email".
 *
 * Each email belongs to an area of the program, which the Log's Area column and filter show. The
 * code calls the areas modules (`MODULE_*`, `modules()`, the table's `module` column) and the
 * screens call them areas: no string a person reads says module (the owner's one-vocabulary rule of
 * 28 September 2026, which bin/test-admin-menu.php holds every translated string to).
 *
 * `audience` is who an email is for when the account cannot say: an applicant or an invitee has no
 * account, and a person with two roles is shown as the one this email is for. Empty means the email
 * goes to people of several kinds (a call booked mails the mentor and the student) and the account
 * decides.
 */
final class WPCPM_Mail_Catalog {

	const MODULE_INVITATIONS  = 'invitations';
	const MODULE_CALLS        = 'calls';
	const MODULE_INSTITUTIONS = 'institutions';
	const MODULE_REPORTS      = 'reports';
	const MODULE_SPONSORS     = 'sponsors';
	const MODULE_WORDPRESS    = 'wordpress';
	const MODULE_TWO_FACTOR   = 'two-factor';
	const MODULE_OTHER        = 'other';

	const TYPE_STUDENT     = 'student';
	const TYPE_MENTOR      = 'mentor';
	const TYPE_INSTITUTION = 'institution';
	const TYPE_SPONSOR     = 'sponsor';
	const TYPE_ADMIN       = 'administrator';
	const TYPE_APPLICANT   = 'applicant';
	const TYPE_OTHER       = 'other';

	/** The id of an email nothing names. */
	const OTHER = 'other';

	/**
	 * The areas, in the order the Log's Area filter lists them.
	 *
	 * @return array<string,string> Slug to label.
	 */
	public static function modules() {
		return array(
			self::MODULE_INVITATIONS  => __( 'Invitations', 'wpcredits-program-manager' ),
			self::MODULE_CALLS        => __( 'Mentor calls', 'wpcredits-program-manager' ),
			self::MODULE_INSTITUTIONS => __( 'Institutions', 'wpcredits-program-manager' ),
			self::MODULE_REPORTS      => __( 'Semester reports', 'wpcredits-program-manager' ),
			self::MODULE_SPONSORS     => __( 'Sponsors', 'wpcredits-program-manager' ),
			self::MODULE_WORDPRESS    => __( 'WordPress', 'wpcredits-program-manager' ),
			self::MODULE_TWO_FACTOR   => __( 'Two Factor', 'wpcredits-program-manager' ),
			self::MODULE_OTHER        => __( 'Other', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The recipient types, in the order the Log's filter lists them.
	 *
	 * @return array<string,string> Slug to label.
	 */
	public static function types() {
		return array(
			self::TYPE_STUDENT     => __( 'Student', 'wpcredits-program-manager' ),
			self::TYPE_MENTOR      => __( 'Mentor', 'wpcredits-program-manager' ),
			self::TYPE_INSTITUTION => __( 'Institution member', 'wpcredits-program-manager' ),
			self::TYPE_SPONSOR     => __( 'Sponsor member', 'wpcredits-program-manager' ),
			self::TYPE_ADMIN       => __( 'Administrator', 'wpcredits-program-manager' ),
			self::TYPE_APPLICANT   => __( 'Applicant (no account)', 'wpcredits-program-manager' ),
			self::TYPE_OTHER       => __( 'Other', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Every email, by id, worked out once a request: the labels are shown in wp-admin only, and the
	 * capture stores ids.
	 *
	 * @return array<string,array{label:string,module:string,audience:string,test:bool}>
	 */
	public static function emails() {
		static $out = null;

		if ( null !== $out ) {
			return $out;
		}

		$i = self::MODULE_INVITATIONS;
		$c = self::MODULE_CALLS;
		$n = self::MODULE_INSTITUTIONS;
		$r = self::MODULE_REPORTS;
		$s = self::MODULE_SPONSORS;
		$w = self::MODULE_WORDPRESS;

		$list = array(
			// Invitations.
			'invite-student'                     => array( __( 'Invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_STUDENT ),
			'invite-mentor'                      => array( __( 'Invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_MENTOR ),
			'invite-institution'                 => array( __( 'Invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_INSTITUTION ),
			'invite-sponsor'                     => array( __( 'Invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_SPONSOR ),
			'test-student'                       => array( __( 'Sample invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-mentor'                        => array( __( 'Sample invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-institution'                   => array( __( 'Sample invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-sponsor'                       => array( __( 'Sample invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			// Mentor calls: to the mentor and the students, so the account decides.
			'call-booked'                        => array( __( 'Call booked', 'wpcredits-program-manager' ), $c, '' ),
			'series-joined'                      => array( __( 'Joined a session series', 'wpcredits-program-manager' ), $c, '' ),
			'session-moved'                      => array( __( 'Group session moved', 'wpcredits-program-manager' ), $c, '' ),
			'call-cancelled'                     => array( __( 'Call canceled', 'wpcredits-program-manager' ), $c, '' ),
			'call-reminder'                      => array( __( 'Call reminder', 'wpcredits-program-manager' ), $c, '' ),
			// Institutions.
			'institution-applied'                => array( __( 'Application received, to the applicant', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-application'            => array( __( 'New institution application, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'institution-information'            => array( __( 'Question about an application', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-declined'               => array( __( 'Application declined', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-invite'                 => array( __( 'Invitation to join an institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-received'                 => array( __( 'Agreement received, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-landed'                   => array( __( 'Agreement uploaded, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-accepted'                 => array( __( 'Agreement accepted', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-returned'                 => array( __( 'Agreement returned with a note', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-revoked'                  => array( __( 'Agreement taken out of force', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-reminder'                 => array( __( 'Agreements waiting for review', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-template'                 => array( __( 'Agreement could not be generated', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'member-last'                        => array( __( 'Institution has no members left, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			// Semester reports.
			'report-drafted'                     => array( __( 'Semester report drafted', 'wpcredits-program-manager' ), $r, self::TYPE_ADMIN ),
			'report-approved'                    => array( __( 'Semester report approved', 'wpcredits-program-manager' ), $r, self::TYPE_INSTITUTION ),
			'report-consent'                     => array( __( 'Consent for the semester report', 'wpcredits-program-manager' ), $r, self::TYPE_STUDENT ),
			// Sponsors.
			'sponsor-applied'                    => array( __( 'Sponsor application received, to the applicant', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-application'                => array( __( 'New sponsor application, to Administrators', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-information'                => array( __( 'Question about a sponsor application', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-declined'                   => array( __( 'Sponsor application declined', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-agreement-received'         => array( __( 'Collaboration Agreement received', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-agreement-accepted'         => array( __( 'Collaboration Agreement accepted', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-returned'         => array( __( 'Collaboration Agreement returned with a note', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-revoked'          => array( __( 'Collaboration Agreement taken out of force', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-reinstated'       => array( __( 'Collaboration Agreement back in force', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-interest'                   => array( __( 'Sponsor interest', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'offer-low-stock'                    => array( __( 'Codes running low', 'wpcredits-program-manager' ), $s, '' ),
			'claim-problem'                      => array( __( 'Problem with a claimed code', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-post-returned'              => array( __( 'Sponsor post returned with a note', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			// WordPress's own.
			'wp-password-reset'                  => array( __( 'Password reset', 'wpcredits-program-manager' ), $w, '' ),
			'wp-password-changed'                => array( __( 'Password changed', 'wpcredits-program-manager' ), $w, '' ),
			'wp-password-changed-admin'          => array( __( 'Password changed, to the site\'s address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-email-changed'                   => array( __( 'Email address changed', 'wpcredits-program-manager' ), $w, '' ),
			'wp-email-change-confirm'            => array( __( 'Confirm a new email address', 'wpcredits-program-manager' ), $w, '' ),
			'wp-new-user'                        => array( __( 'New account', 'wpcredits-program-manager' ), $w, '' ),
			'wp-new-user-admin'                  => array( __( 'New account, to the site\'s address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-admin-email-change'              => array( __( 'Confirm the site\'s new address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-admin-email-changed'             => array( __( 'The site\'s address changed', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-personal-data-request'           => array( __( 'Personal data request', 'wpcredits-program-manager' ), $w, '' ),
			'wp-personal-data-request-confirmed' => array( __( 'Personal data request confirmed, to the site\'s address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-personal-data-export'            => array( __( 'Personal data export ready', 'wpcredits-program-manager' ), $w, '' ),
			'wp-personal-data-erased'            => array( __( 'Personal data erased', 'wpcredits-program-manager' ), $w, '' ),
			'wp-core-update'                     => array( __( 'WordPress updated', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-plugin-theme-update'             => array( __( 'Plugins or themes updated', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-updates-debug'                   => array( __( 'Background update details', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-recovery-mode'                   => array( __( 'Recovery mode', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-comment-notification'            => array( __( 'New comment', 'wpcredits-program-manager' ), $w, '' ),
			'wp-comment-moderation'              => array( __( 'Comment waiting for moderation', 'wpcredits-program-manager' ), $w, '' ),
			// The Two Factor plugin's.
			'two-factor-code'                    => array( __( 'Sign-in code', 'wpcredits-program-manager' ), self::MODULE_TWO_FACTOR, '' ),
		);

		$out = array();

		foreach ( $list as $id => $row ) {
			$out[ $id ] = array(
				'label'    => $row[0],
				'module'   => $row[1],
				'audience' => $row[2],
				'test'     => ! empty( $row[3] ),
			);
		}

		return $out;
	}

	/**
	 * One email's entry.
	 *
	 * @param string $id The email's id.
	 * @return array|null The entry, or null for an id the catalog does not hold.
	 */
	public static function get( $id ) {
		$emails = self::emails();

		return isset( $emails[ (string) $id ] ) ? $emails[ (string) $id ] : null;
	}

	/**
	 * An email's name as the Log shows it.
	 *
	 * @param string $id The email's id.
	 * @return string The label, or "Other email".
	 */
	public static function label( $id ) {
		$entry = self::get( $id );

		return null === $entry ? __( 'Other email', 'wpcredits-program-manager' ) : $entry['label'];
	}

	/**
	 * An email's area.
	 *
	 * @param string $id The email's id.
	 * @return string One of the `MODULE_*` slugs; `MODULE_OTHER` for an id the catalog does not hold.
	 */
	public static function module_of( $id ) {
		$entry = self::get( $id );

		return null === $entry ? self::MODULE_OTHER : $entry['module'];
	}

	/**
	 * The filters WordPress and the Two Factor plugin pass an email through on its way out, and the
	 * email each one names. WordPress's were read in WordPress 7.1.2 on 10 October 2026, each a
	 * current filter and none deprecated. `two_factor_token_email_subject` was checked against the Two
	 * Factor plugin 0.17.0 and exists since 0.5.2; the local copies run 0.16.0, which has it too.
	 *
	 * `wp_new_user_notification_email` is WordPress's new-account email, which the plugin rewrites
	 * into its invitations; an invitation is named by the plugin's own context, which wins over this
	 * name (`WPCPM_Mail_Capture::open()`).
	 *
	 * The two comment emails' subject filters run inside WordPress 7.1.2's loop over the recipients,
	 * once before each send, so every recipient's email is named. A WordPress that ran a filter once
	 * before such a loop would have its first recipient's email named and the rest shown as Other.
	 * The Two Factor plugin's two emails about a compromised password pass no filter of their own,
	 * so they show as Other.
	 *
	 * @return array<string,string> Filter name to email id.
	 */
	public static function wordpress_filters() {
		return array(
			'retrieve_password_notification_email'   => 'wp-password-reset',
			'password_change_email'                  => 'wp-password-changed',
			'wp_password_change_notification_email'  => 'wp-password-changed-admin',
			'email_change_email'                     => 'wp-email-changed',
			'new_user_email_content'                 => 'wp-email-change-confirm',
			'wp_new_user_notification_email'         => 'wp-new-user',
			'wp_new_user_notification_email_admin'   => 'wp-new-user-admin',
			'new_admin_email_content'                => 'wp-admin-email-change',
			'site_admin_email_change_email'          => 'wp-admin-email-changed',
			'user_request_action_email_content'      => 'wp-personal-data-request',
			'user_request_confirmed_email_content'   => 'wp-personal-data-request-confirmed',
			'wp_privacy_personal_data_email_content' => 'wp-personal-data-export',
			'user_erasure_fulfillment_email_content' => 'wp-personal-data-erased',
			'auto_core_update_email'                 => 'wp-core-update',
			'auto_plugin_theme_update_email'         => 'wp-plugin-theme-update',
			'automatic_updates_debug_email'          => 'wp-updates-debug',
			'recovery_mode_email'                    => 'wp-recovery-mode',
			'comment_notification_subject'           => 'wp-comment-notification',
			'comment_moderation_subject'             => 'wp-comment-moderation',
			'two_factor_token_email_subject'         => 'two-factor-code',
		);
	}
}
