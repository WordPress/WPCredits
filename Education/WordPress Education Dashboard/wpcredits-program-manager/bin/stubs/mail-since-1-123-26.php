<?php
/**
 * What 1.123.26 changes on purpose in the emails a suite recorded before it, for the suites that
 * hold a recording to its bytes and pin its hash, so the recording itself is never edited.
 *
 * One change, from the review of the Institutions module of 22 October 2026: the acknowledgement
 * an applicant is sent, the institution application's (`institution-applied`,
 * `institution-applied-no-call`) and the mentor application's (`mentor-application-received`),
 * says the link in it is needed. It used to say the link "does nothing else", while an
 * Administrator cannot approve an institution or select a mentor, ask them anything or decline
 * them by email until the address is confirmed, and that link is the only thing that confirms it.
 *
 * A suite runs each recorded body through `wpcpm_mail_since_1_123_26()` where it compares, after
 * `wpcpm_mail_since_1_123_24()`, or a whole recording through `wpcpm_mails_since_1_123_26()`, and
 * reads `$GLOBALS['wpcpm_since_1_123_26']` after, to say the change was made in the emails it
 * belongs to and in no other.
 *
 * Loaded with `require_once __DIR__ . '/stubs/mail-since-1-123-26.php';` from a suite's header.
 *
 * @package WPCreditsProgramManager
 */

/** The acknowledgement's sentence about the link, as every recording before 1.123.26 holds it. */
const WPCPM_CONFIRM_LINK_BEFORE = 'Please confirm this address by following this link. It tells us we are writing to a real address, and it does nothing else:';

/** And as 1.123.26 sends it. */
const WPCPM_CONFIRM_LINK_SINCE = 'Please follow this link: we cannot take your application further until you do.';

$GLOBALS['wpcpm_since_1_123_26'] = array(
	'confirm_link' => 0,
);

/**
 * A body recorded before 1.123.26, as 1.123.26 sends it.
 *
 * @param string $body The body as the recording holds it.
 * @return string
 */
function wpcpm_mail_since_1_123_26( $body ) {
	$said = str_replace( WPCPM_CONFIRM_LINK_BEFORE, WPCPM_CONFIRM_LINK_SINCE, (string) $body, $count );

	$GLOBALS['wpcpm_since_1_123_26']['confirm_link'] += $count;

	return $said;
}

/**
 * Every email of a recording, keyed by case, as 1.123.26 sends it.
 *
 * @param array $cases Case name => the emails the recording holds for it.
 * @return array
 */
function wpcpm_mails_since_1_123_26( array $cases ) {
	foreach ( $cases as $name => $mails ) {
		foreach ( (array) $mails as $n => $mail ) {
			if ( is_array( $mail ) && isset( $mail['body'] ) ) {
				$cases[ $name ][ $n ]['body'] = wpcpm_mail_since_1_123_26( $mail['body'] );
			}
		}
	}

	return $cases;
}
