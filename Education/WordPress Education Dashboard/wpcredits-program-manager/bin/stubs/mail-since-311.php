<?php
/**
 * What #311 changes on purpose in the emails a suite recorded before it, for the suites that hold a
 * recording to its bytes and pin its hash, so the recording itself is never edited.
 *
 * A one-to-one booking's two confirmations, the mentor's and the student's, follow "Times are shown
 * in ..." with the call on the other person's clock: "For Ada Okonjo, that is July 15, 2036 10:45 am
 * to 11:15 am (America / New York)." The sentence is there only while the other person has saved a
 * time zone and it is not the reader's.
 *
 * The other person's time, name and clock are read from the case's other confirmation, which says
 * them on that person's own clock. In the recordings the one account with no saved time zone is a
 * student on the site's clock, UTC, so a copy shown in UTC is the copy of somebody who saved none.
 *
 * A suite runs a case's recorded emails through `wpcpm_mail_since_311()` where it compares, after
 * the stubs of the releases before, and reads `$GLOBALS['wpcpm_since_311']` after, to say the change
 * was made in the emails it belongs to and in no other.
 *
 * @package WPCreditsProgramManager
 */

$GLOBALS['wpcpm_since_311'] = array(
	'added' => 0,
);

/**
 * One case's recorded emails, as #311 sends them.
 *
 * @param array[] $mails The case's emails, each with its `body`, after the stubs before.
 * @return array[] The same emails, the booking confirmations given their second sentence.
 */
function wpcpm_mail_since_311( array $mails ) {
	$copies = array();

	foreach ( $mails as $n => $mail ) {
		$body = isset( $mail['body'] ) ? (string) $mail['body'] : '';

		if ( preg_match( '/^(.+?) booked a call with you on (.+?)\.\n\nTimes are shown in (.+?)\./s', $body, $m ) ) {
			$copies['mentor'] = array( 'n' => $n, 'other' => $m[1], 'when' => $m[2], 'zone' => $m[3] );
		} elseif ( preg_match( '/^Your call with (.+?) is booked for (.+?)\.\n\nTimes are shown in (.+?)\./s', $body, $m ) ) {
			$copies['student'] = array( 'n' => $n, 'other' => $m[1], 'when' => $m[2], 'zone' => $m[3] );
		}
	}

	if ( ! isset( $copies['mentor'], $copies['student'] ) ) {
		return $mails;
	}

	foreach ( array( 'mentor' => 'student', 'student' => 'mentor' ) as $reader => $other ) {
		$theirs = $copies[ $other ];
		$mine   = $copies[ $reader ];

		if ( 'UTC' === $theirs['zone'] || $theirs['zone'] === $mine['zone'] ) {
			continue;
		}

		$line = 'Times are shown in ' . $mine['zone'] . '.';
		$with = $line . ' For ' . $mine['other'] . ', that is ' . $theirs['when'] . ' (' . $theirs['zone'] . ').';
		$body = $mails[ $mine['n'] ]['body'];
		$at   = strpos( $body, $line );

		if ( false === $at ) {
			continue;
		}

		$mails[ $mine['n'] ]['body'] = substr_replace( $body, $with, $at, strlen( $line ) );
		++$GLOBALS['wpcpm_since_311']['added'];
	}

	return $mails;
}
