<?php
/**
 * What 1.123.27 changes on purpose in the emails a suite recorded before it, for the suites that
 * hold a recording to its bytes and pin its hash, so the recording itself is never edited.
 *
 * The Institutions' and the Semester reports' emails told a school and an Administrator things the
 * site no longer does or no one can read, and named the site without its "the":
 *
 * - the acknowledgement of an institution application says what the program does next in the words
 *   of today's site (an account is set up on it, where the institution generates the Collaboration
 *   Agreement or uploads its own and uploads the signed copy; nothing is sent to sign), and names
 *   the site after "the";
 * - the invitation names the site after "the", and prints the day its link stops working in the
 *   site's date format;
 * - the notices that sent an institution to "the agreement panel" send it to the Collaboration
 *   Agreement card on its Institution Dashboard (received, returned, revoked);
 * - the Administrators' notice of an upload names an uploader with no name "a member of the
 *   institution", not the institution's Airtable record;
 * - the notice of a template that cannot be used names the institution as the site holds its name,
 *   and says a developer fixes the template;
 * - the notice that an institution has no members left says who, of which institution, as of when
 *   and why, and no longer prints its Airtable record;
 * - the three Semester reports emails' subjects open with the site's name in brackets.
 *
 * A suite runs each recorded email through `wpcpm_mail_since_1_123_27()` where it compares, after
 * the stubs of the releases before, and reads `$GLOBALS['wpcpm_since_1_123_27']` after, to say each
 * change was made in the emails it belongs to and in no other.
 *
 * Loaded with `require_once __DIR__ . '/stubs/mail-since-1-123-27.php';` from a suite's header.
 *
 * @package WPCreditsProgramManager
 */

/** The acknowledgement's "What happens next", as every recording before 1.123.27 holds it. */
const WPCPM_NEXT_BEFORE = 'What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account on the program site and send you the Collaboration Agreement to sign.';

/** And as 1.123.27 sends it. */
const WPCPM_NEXT_SINCE = 'What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account for it on the site, where you generate the program\'s Collaboration Agreement or upload your own, then sign it and upload the signed copy.';

$GLOBALS['wpcpm_since_1_123_27'] = array(
	'next'                 => 0,
	'form_at'              => 0,
	'invite_site'          => 0,
	'invite_expiry'        => 0,
	'received'             => 0,
	'returned'             => 0,
	'revoked'              => 0,
	'landed'               => 0,
	'template_institution' => 0,
	'template_fix'         => 0,
	'member_line'          => 0,
	'member_untouched'     => 0,
	'report_subject'       => 0,
);

/**
 * One recorded email, as 1.123.27 sends it.
 *
 * @param array $mail The email as the recording holds it, after the changes of the releases before:
 *                    its `context` (the id the recording logged it under, or the id 1.122.23 gave
 *                    it), `subject` and `body`.
 * @param array $site What the case's site was: `name` (as WordPress stores it), `date_format`, and
 *                    `institutions`, the name the index holds for each record.
 * @return array The same email, its subject and body changed where 1.123.27 changes them.
 */
function wpcpm_mail_since_1_123_27( array $mail, array $site = array() ) {
	$site    += array(
		'name'         => '',
		'date_format'  => 'F j, Y',
		'institutions' => array(),
	);
	$context  = isset( $mail['context'] ) ? (string) $mail['context'] : '';
	$subject  = isset( $mail['subject'] ) ? (string) $mail['subject'] : '';
	$body     = isset( $mail['body'] ) ? (string) $mail['body'] : '';
	$counted  = function ( $key, $count ) {
		$GLOBALS['wpcpm_since_1_123_27'][ $key ] += $count;
	};
	$replace  = function ( $key, $before, $after, $text ) use ( $counted ) {
		$counted( $key, substr_count( $text, $before ) );

		return str_replace( $before, $after, $text );
	};

	switch ( $context ) {
		case 'institution-applied':
		case 'institution-applied-no-call':
			$body = $replace( 'next', WPCPM_NEXT_BEFORE, WPCPM_NEXT_SINCE, $body );
			$body = $replace( 'form_at', 'because that address was given on the application form at ', 'because that address was given on the application form at the ', $body );
			break;

		case 'institution-invite':
		case 'institution-invite-no-sender':
			$body = $replace( 'invite_site', ' in the WordPress Credits Program on ', ' in the WordPress Credits Program on the ', $body );
			$body = preg_replace_callback(
				'/The link works until (\d{4}-\d{2}-\d{2}) and can only be used once\./',
				function ( $m ) use ( $site, $counted ) {
					$counted( 'invite_expiry', 1 );

					// A stored day has no time zone: it is the day it names.
					return 'The link works until ' . gmdate( $site['date_format'], strtotime( $m[1] . ' UTC' ) ) . ' and can only be used once.';
				},
				$body
			);
			break;

		case 'agreement-received':
			$body = $replace( 'received', 'withdraw it from the agreement panel on the site and upload the right one.', 'withdraw it from the Collaboration Agreement card on your Institution Dashboard and upload the right one.', $body );
			break;

		case 'agreement-returned':
			$body = $replace( 'returned', 'Upload the corrected copy from the agreement panel on the site.', 'Upload the corrected copy from the Collaboration Agreement card on your Institution Dashboard.', $body );
			break;

		case 'agreement-revoked':
		case 'agreement-revoked-no-contact':
			$body = $replace( 'revoked', 'Your account on this site is limited to the agreement panel until an agreement is in force again. Nothing about your students has been deleted.', 'Until an agreement is in force again, your Institution Dashboard shows only the Collaboration Agreement card. Nothing about your students has been deleted.', $body );
			break;

		case 'agreement-landed':
		case 'agreement-landed-flagged':
			// An uploader with no name was given as the institution's record.
			$body = preg_replace_callback(
				'/( uploaded a signed Collaboration Agreement on [^\r\n(]+) \(rec[A-Za-z0-9]+\)\./',
				function ( $m ) use ( $counted ) {
					$counted( 'landed', 1 );

					return $m[1] . ' (a member of the institution).';
				},
				$body
			);
			break;

		case 'agreement-template':
			$body = preg_replace_callback(
				'/\(template language ([^,\r\n]+), institution (rec[A-Za-z0-9]+), name asked for/',
				function ( $m ) use ( $site, $counted ) {
					$counted( 'template_institution', 1 );

					return '(template language ' . $m[1] . ', institution ' . ( isset( $site['institutions'][ $m[2] ] ) ? $site['institutions'][ $m[2] ] : $m[2] ) . ', name asked for';
				},
				$body
			);
			$body = $replace( 'template_fix', 'The template lives in the plugin, in includes/templates/. A bracketed token other than the institution name is refused on purpose: it would otherwise print on a document somebody signs. Nothing can be generated in this language until it is fixed.', 'The template is part of the plugin, so a developer fixes it. A bracketed word other than the institution\'s name is refused on purpose: it would otherwise be printed on a document somebody signs. Nothing can be generated in this language until it is fixed.', $body );
			break;

		case 'member-last':
			$body = preg_replace_callback(
				'/([^\r\n]+) is no longer a member of ([^\r\n]+) \(rec[A-Za-z0-9]+\) as of ([^\r\n]+) \((removed|left|revoked)\)\. No account can act for this institution on the site now\./',
				function ( $m ) use ( $counted ) {
					$counted( 'member_line', 1 );

					return $m[1] . ' is no longer a member of ' . $m[2] . ' as of ' . $m[3] . ' (' . $m[4] . '). No account can act for this institution on the site now.';
				},
				$body
			);
			$body = $replace( 'member_untouched', 'The Airtable record, the agreement, the students and the history are untouched.', 'The institution\'s record, its agreement, its students and its history are untouched.', $body );
			break;

		case 'report-drafted':
		case 'report-approved':
		case 'report-consent':
			$counted( 'report_subject', 1 );
			$subject = '[' . html_entity_decode( $site['name'], ENT_QUOTES ) . '] ' . $subject;
			break;
	}

	return array(
		'subject' => $subject,
		'body'    => $body,
	) + $mail;
}
