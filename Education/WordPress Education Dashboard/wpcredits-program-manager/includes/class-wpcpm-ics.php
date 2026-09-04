<?php
/**
 * Calendar invitations for mentor calls.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the `.ics` file that goes out with a booking or a cancellation.
 *
 * Two people agree a half-hour slot across two timezones. Without this they each read a
 * date out of an email and type it into a calendar by hand, which is exactly where the
 * off-by-one-hour mistakes come from - and it has to happen twice, correctly, or the call
 * does not happen.
 *
 * **Attached as a file rather than sent as a `text/calendar` body part.** A true iTIP
 * invitation needs the calendar data to *be* the message body, with `method=REQUEST` in the
 * top-level content type; `wp_mail()` builds a message it controls and takes attachments by
 * path, so reaching that shape means bypassing it. An attached `.ics` is offered as "Add to
 * calendar" by Gmail, Apple Mail and Outlook, which is the part that matters. What is lost
 * is RSVP: neither party can accept from the mail client and have the other one told.
 *
 * The UID is derived from the call's post ID, so the cancellation names the same event the
 * booking created and a calendar can match them. That is the whole reason cancellations
 * work at all - a fresh UID would add a second event rather than remove the first.
 */
class WPCPM_ICS {

	/** A booking. */
	const METHOD_REQUEST = 'REQUEST';

	/** A cancellation of a booking already sent. */
	const METHOD_CANCEL = 'CANCEL';

	/**
	 * Build the calendar object for one call.
	 *
	 * @param array        $facts   Call facts, from `WPCPM_Mentor_Calls::details()`.
	 * @param string       $method  `REQUEST` or `CANCEL`.
	 * @param WP_User|null $mentor  Mentor, the organizer.
	 * @param WP_User|null $student Student, the attendee.
	 * @param string       $summary Event title.
	 * @param string       $body    Event description, as plain text.
	 * @param string       $where    Meeting URL or place, may be empty.
	 * @param int|null     $sequence Revision number, for an event sent more than once. Null keeps
	 *                               the default: 0 for a booking, 1 for a cancellation.
	 * @return string The `.ics` contents, CRLF-delimited.
	 */
	public static function build( array $facts, $method, $mentor, $student, $summary, $body, $where = '', $sequence = null ) {
		$method = self::METHOD_CANCEL === $method ? self::METHOD_CANCEL : self::METHOD_REQUEST;

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//WordPress Credits Program//WPCredits Program Manager//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:' . $method,
			'BEGIN:VEVENT',
			'UID:' . self::uid( $facts['id'] ),
			// A calendar that already holds this event is entitled to ignore anything that does
			// not outrank what it has, so every re-send of the same UID has to count higher than
			// the last. A cancellation outranks the booking it withdraws; an edited session
			// passes its own revision, which is why moving a session actually moves it in the
			// students' calendars rather than arriving as a duplicate they must reconcile.
			'SEQUENCE:' . (int) ( null === $sequence ? ( self::METHOD_CANCEL === $method ? 1 : 0 ) : max( 0, (int) $sequence ) ),
			'DTSTAMP:' . self::stamp( time() ),
			'DTSTART:' . self::stamp( $facts['start'] ),
			'DTEND:' . self::stamp( $facts['end'] ),
			'SUMMARY:' . self::text( $summary ),
			'DESCRIPTION:' . self::text( $body ),
			'STATUS:' . ( self::METHOD_CANCEL === $method ? 'CANCELLED' : 'CONFIRMED' ),
			'TRANSP:OPAQUE',
		);

		if ( '' !== trim( (string) $where ) ) {
			$lines[] = 'LOCATION:' . self::text( $where );
		}

		if ( $mentor instanceof WP_User && ! empty( $mentor->user_email ) ) {
			$lines[] = 'ORGANIZER;CN=' . self::param( $mentor->display_name ) . ':mailto:' . $mentor->user_email;
		}

		// `RSVP=FALSE` and `PARTSTAT=ACCEPTED`: the booking already happened, on the program
		// site. Asking the two people who arranged it to accept an invitation as well would
		// imply the call is not yet real, and nothing here can receive their answer.
		foreach ( array( $mentor, $student ) as $person ) {
			if ( $person instanceof WP_User && ! empty( $person->user_email ) ) {
				$lines[] = 'ATTENDEE;CN=' . self::param( $person->display_name )
					. ';ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:' . $person->user_email;
			}
		}

		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		$folded = array();

		foreach ( $lines as $line ) {
			$folded[] = self::fold( $line );
		}

		/**
		 * Filter the calendar invitation for a call.
		 *
		 * @param string $ics    The `.ics` contents.
		 * @param array  $facts  Call facts.
		 * @param string $method `REQUEST` or `CANCEL`.
		 */
		return (string) apply_filters(
			'wpcpm_call_ics',
			implode( "\r\n", $folded ) . "\r\n",
			$facts,
			$method
		);
	}

	/**
	 * A stable identifier for a call's calendar event.
	 *
	 * @param int $call_id Call post ID.
	 * @return string
	 */
	public static function uid( $call_id ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return sprintf( 'wpcpm-call-%1$d@%2$s', (int) $call_id, $host ? $host : 'localhost' );
	}

	/**
	 * A UTC timestamp in the form iCalendar wants.
	 *
	 * Always UTC, never a floating local time with a `TZID`. A floating time needs the
	 * recipient's calendar to resolve the zone from a `VTIMEZONE` block, and getting that
	 * wrong moves the call by an hour without telling anybody.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function stamp( $timestamp ) {
		return gmdate( 'Ymd\THis\Z', (int) $timestamp );
	}

	/**
	 * Escape a value for an iCalendar TEXT field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function text( $value ) {
		$value = wp_specialchars_decode( (string) $value, ENT_QUOTES );

		// Backslash first: escaping it after the others would escape their escapes.
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( array( ';', ',' ), array( '\;', '\,' ), $value );
		$value = str_replace( array( "\r\n", "\r", "\n" ), '\n', $value );

		return $value;
	}

	/**
	 * Clean a value used as a property parameter, such as a `CN`.
	 *
	 * Parameters have no escape sequence for a double quote, so the only safe thing to do
	 * with the characters that would break out of one is to drop them.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function param( $value ) {
		$value = wp_specialchars_decode( (string) $value, ENT_QUOTES );
		$value = str_replace( array( '"', ';', ':', ',', "\r", "\n" ), ' ', $value );

		return '"' . trim( preg_replace( '/\s+/', ' ', $value ) ) . '"';
	}

	/**
	 * Fold a content line to 75 octets, as the format requires.
	 *
	 * Counted in bytes rather than characters, and split on character boundaries: a fold in
	 * the middle of a multi-byte character produces a file some calendars refuse outright.
	 * A note or a name with an accent in it is enough to hit this.
	 *
	 * @param string $line One content line.
	 * @return string
	 */
	private static function fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$out       = '';
		$current   = '';
		$limit     = 75;
		$positions = self::characters( $line );

		foreach ( $positions as $character ) {
			if ( strlen( $current . $character ) > $limit ) {
				$out    .= ( '' === $out ? '' : "\r\n " ) . $current;
				$current = $character;
				// Continuation lines carry a leading space that counts toward the 75.
				$limit = 74;

				continue;
			}

			$current .= $character;
		}

		return $out . ( '' === $out ? '' : "\r\n " ) . $current;
	}

	/**
	 * Split a string into whole characters, multi-byte safe without mbstring.
	 *
	 * @param string $value Value to split.
	 * @return array
	 */
	private static function characters( $value ) {
		$split = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );

		// `preg_split` with `/u` returns false on a string that is not valid UTF-8. Falling
		// back to bytes keeps a malformed name from producing no calendar file at all.
		return false === $split ? str_split( $value ) : $split;
	}

	/**
	 * Write a calendar object to a temporary file for `wp_mail()` to attach.
	 *
	 * @param string $ics  Calendar contents.
	 * @param string $name Filename offered to the recipient.
	 * @return string Absolute path, or an empty string if it could not be written.
	 */
	public static function tempfile( $ics, $name = 'mentor-call.ics' ) {
		$dir = get_temp_dir();

		if ( ! $dir || ! is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checking the system temp directory, which WP_Filesystem does not describe; the alternative is writing blind and attaching nothing.
			return '';
		}

		// A directory per send, so the file keeps the name the recipient should see. Two
		// concurrent bookings would otherwise race for one path, and the loser would attach
		// the winner's calendar - a real possibility with two students booking at once.
		$unique = $dir . 'wpcpm-' . wp_generate_password( 12, false, false ) . '/';

		if ( ! wp_mkdir_p( $unique ) ) {
			return '';
		}

		$path = $unique . sanitize_file_name( $name );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- `wp_mail()` attaches by absolute path, so the file has to exist on disk. WP_Filesystem addresses the site's own directories, not a scratch file deleted moments later in the same request.
		$written = file_put_contents( $path, $ics );

		return false === $written ? '' : $path;
	}

	/**
	 * Delete a file written by `tempfile()`, and the directory holding it.
	 *
	 * @param string $path Absolute path.
	 */
	public static function cleanup( $path ) {
		$path = (string) $path;
		$dir  = dirname( $path );

		if ( '' === $path || ! file_exists( $path ) ) {
			return;
		}

		wp_delete_file( $path );

		// Only ever a directory this class made, and only when it is empty.
		if ( 0 === strpos( $dir, rtrim( get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR . 'wpcpm-' ) ) {
			// Both sniffs named on the line they apply to. Split across two annotations they
			// would be one edit away from sitting above the wrong statement and silently
			// ceasing to apply, which has already happened elsewhere in this plugin.
			@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- Removing the scratch directory this class made, when empty; a directory left behind is harmless, a warning printed into a redirect is not.
		}
	}
}
