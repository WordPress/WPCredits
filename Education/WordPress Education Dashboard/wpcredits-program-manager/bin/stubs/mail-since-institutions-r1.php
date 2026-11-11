<?php
/**
 * What Institutions release 1 changes on purpose in the emails a suite recorded before it, for the
 * suites that hold a recording to its bytes and pin its hash, so the recording itself is never edited.
 *
 * The invitation to an institution says what the invited person may do. Every invitation recorded before
 * the Tutor level was a representative's, so each now carries one more paragraph after its opening
 * sentence: "You are invited as a representative: ..."
 *
 * A suite runs each recorded email through `wpcpm_mail_since_institutions_r1()` where it compares, after
 * the stubs of the releases before, and reads `$GLOBALS['wpcpm_since_institutions_r1']` after, to say the
 * change was made in the emails it belongs to and in no other.
 *
 * Loaded with `require_once __DIR__ . '/stubs/mail-since-institutions-r1.php';` from a suite's header.
 *
 * @package WPCreditsProgramManager
 */

$GLOBALS['wpcpm_since_institutions_r1'] = array(
	'invite_level' => 0,
);

/**
 * One recorded email, as Institutions release 1 sends it.
 *
 * @param array $mail The email as the recording holds it, after the changes of the releases before: its
 *                    `context` (the id the recording logged it under, or the id 1.122.23 gave it), `subject`
 *                    and `body`.
 * @return array The same email, its body changed where this release changes it.
 */
function wpcpm_mail_since_institutions_r1( array $mail ) {
	$context = isset( $mail['context'] ) ? (string) $mail['context'] : '';

	if ( 'institution-invite' !== $context && 'institution-invite-no-sender' !== $context ) {
		return $mail;
	}

	$body = (string) ( isset( $mail['body'] ) ? $mail['body'] : '' );
	$edge = strpos( $body, "\r\n\r\n" );

	if ( false === $edge ) {
		return $mail;
	}

	++$GLOBALS['wpcpm_since_institutions_r1']['invite_level'];

	$sentence = 'You are invited as a representative: you can see and add the institution\'s students, invite colleagues and correct its details.';

	return array( 'body' => substr( $body, 0, $edge + 4 ) . $sentence . "\r\n\r\n" . substr( $body, $edge + 4 ) ) + $mail;
}
