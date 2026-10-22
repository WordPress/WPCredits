<?php
/**
 * What 1.123.24 changes on purpose in the emails a suite recorded before it, for the suites that
 * hold a recording to its bytes and pin its hash, so the recording itself is never edited.
 *
 * Two changes, from the sponsor feedback of 22 October 2026:
 *
 * - the rejection a sponsor applicant is sent (`sponsor-declined`) asks them to apply again without
 *   guessing why they might, its third paragraph dropping "for example when your products or the
 *   program change"; the institution's rejection keeps its own words;
 * - the shipped signature and footer prints the site's address, `{site_link}`, on the line under its
 *   first, which the owner approved that day: every recorded footer gains that line, in the body's
 *   own line ending, but one whose body names the home page standing whole, which
 *   `WPCPM_Mail_Templates::add_footer()` leaves it out of, with no empty line in its place.
 *
 * A suite runs each recorded body through `wpcpm_mail_since_1_123_24()` where it compares, or a
 * whole recording through `wpcpm_mails_since_1_123_24()`, and reads
 * `$GLOBALS['wpcpm_since_1_123_24']` after, to say each change was made where it should be and
 * nowhere else. The home page is the suite's own `home_url( '/' )`.
 *
 * Loaded with `require_once __DIR__ . '/stubs/mail-since-1-123-24.php';` from a suite's header.
 *
 * @package WPCreditsProgramManager
 */

/** The sponsor rejection's third paragraph as every recording before 1.123.24 holds it. */
const WPCPM_SPONSOR_DECLINE_BEFORE = 'You are welcome to apply again later, for example when your products or the program change. A new application is read on its own merits, and this decision does not count against it.';

/** And as 1.123.24 sends it. */
const WPCPM_SPONSOR_DECLINE_SINCE = 'You are welcome to apply again later. A new application is read on its own merits, and this decision does not count against it.';

/** The signature's first line, as the shipped footer has always begun. */
const WPCPM_FOOTER_FIRST_LINE = 'WordPress Credits, WordPress Education';

$GLOBALS['wpcpm_since_1_123_24'] = array(
	'declined'  => 0,
	'site_line' => 0,
	'site_kept' => 0,
);

/**
 * A body recorded before 1.123.24, as 1.123.24 sends it.
 *
 * @param string $body The body as the recording holds it.
 * @return string
 */
function wpcpm_mail_since_1_123_24( $body ) {
	$body = (string) $body;
	$said = str_replace( WPCPM_SPONSOR_DECLINE_BEFORE, WPCPM_SPONSOR_DECLINE_SINCE, $body, $count );

	$GLOBALS['wpcpm_since_1_123_24']['declined'] += $count;

	return wpcpm_site_line_since_1_123_24( $said );
}

/**
 * Every email of a recording, keyed by case, as 1.123.24 sends it.
 *
 * @param array $cases Case name => the emails the recording holds for it.
 * @return array
 */
function wpcpm_mails_since_1_123_24( array $cases ) {
	foreach ( $cases as $name => $mails ) {
		foreach ( (array) $mails as $n => $mail ) {
			if ( is_array( $mail ) && isset( $mail['body'] ) ) {
				$cases[ $name ][ $n ]['body'] = wpcpm_mail_since_1_123_24( $mail['body'] );
			}
		}
	}

	return $cases;
}

/**
 * A body recorded before 1.123.24 with the site's address on the line under the signature's first,
 * where its footer is the shipped one, and as recorded where its body names the home page standing
 * whole (`site_kept` counts those) or where it has no such footer.
 *
 * The footer starts at the last "-- " line after a blank line, as the mail layer writes it, in the
 * line ending the body is written in; the home page counts as named where its address stands whole,
 * with its last slash or without it, as `WPCPM_Mail_Templates::names_address()` reads it, so a body
 * naming a page of the site keeps the line.
 *
 * @param string $body The body.
 * @return string
 */
function wpcpm_site_line_since_1_123_24( $body ) {
	$body = (string) $body;
	$eol  = false !== strpos( $body, "\r\n" ) ? "\r\n" : "\n";
	$sign = $eol . $eol . '-- ' . $eol . WPCPM_FOOTER_FIRST_LINE . $eol;
	$at   = strrpos( $body, $sign );

	if ( false === $at ) {
		return $body;
	}

	$home = (string) home_url( '/' );
	$bare = rtrim( $home, '/' );

	if ( 1 === preg_match( '~' . preg_quote( $bare, '~' ) . '/?(?=$|[\s<>"\')\]]|[.,;:!?](?:$|\s))~', substr( $body, 0, $at ) ) ) {
		++$GLOBALS['wpcpm_since_1_123_24']['site_kept'];

		return $body;
	}

	++$GLOBALS['wpcpm_since_1_123_24']['site_line'];

	$cut = $at + strlen( $sign );

	return substr( $body, 0, $cut ) . $home . $eol . substr( $body, $cut );
}
