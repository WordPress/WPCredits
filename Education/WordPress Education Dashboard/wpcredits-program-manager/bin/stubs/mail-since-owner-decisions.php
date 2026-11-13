<?php
/**
 * What the owner's decisions of 13 November 2026 change on purpose in the emails a suite recorded
 * before them, for the suites that hold a recording to its bytes and pin its hash, so the recording
 * itself is never edited.
 *
 * The two "Call canceled" emails of a one-to-one call close with a sentence that is true for every
 * cancellation, a student moved to another mentor among them:
 *
 * - the mentor's says the slot is free again and open for booking;
 * - the student's says they can book another time on their Student Report Card.
 *
 * A suite runs each recorded email through `wpcpm_mail_since_owner_decisions()` where it compares,
 * after the stubs of the releases before, and reads `$GLOBALS['wpcpm_since_owner_decisions']`
 * after, to say each change was made in the emails it belongs to and in no other.
 *
 * Loaded with `require_once __DIR__ . '/stubs/mail-since-owner-decisions.php';` from a suite's header.
 *
 * @package WPCreditsProgramManager
 */

$GLOBALS['wpcpm_since_owner_decisions'] = array(
	'mentor_closing'  => 0,
	'student_closing' => 0,
);

/**
 * One recorded email, as the owner's decisions of 13 November 2026 send it.
 *
 * @param array $mail The email as the recording holds it, after the changes of the releases before:
 *                    its `context`, `subject` and `body`.
 * @return array The same email, its body changed where the decisions change it.
 */
function wpcpm_mail_since_owner_decisions( array $mail ) {
	$context = isset( $mail['context'] ) ? (string) $mail['context'] : '';
	$body    = isset( $mail['body'] ) ? (string) $mail['body'] : '';
	$replace = function ( $key, $before, $after, $text ) {
		$GLOBALS['wpcpm_since_owner_decisions'][ $key ] += substr_count( $text, $before );

		return str_replace( $before, $after, $text );
	};

	// Recorded before 1.122.23 gave the two their own ids, both are logged as `call-cancelled`; each
	// sentence is in its own email only.
	if ( in_array( $context, array( 'call-cancelled', 'call-cancelled-mentor', 'call-cancelled-student' ), true ) ) {
		$body = $replace( 'mentor_closing', 'The slot is free again, and your student can book another time whenever suits them.', 'The slot is free again and open for booking.', $body );
		$body = $replace( 'student_closing', 'The slot is free again, so you can book another time.', 'You can book another time on your Student Report Card.', $body );
	}

	return array( 'body' => $body ) + $mail;
}
