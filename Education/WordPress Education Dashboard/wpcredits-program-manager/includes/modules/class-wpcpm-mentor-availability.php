<?php
/**
 * Mentors module — when a mentor is available for calls.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A mentor's weekly availability, and the bookable slots that fall out of it.
 *
 * The schedule is a *recurring* shape — "Tuesdays and Thursdays, 09:00 to 12:00" —
 * rather than a list of dated openings. A mentor sets it once and it keeps producing
 * slots; a list of dates would have to be topped up every few weeks and would go
 * quietly empty when it was not.
 *
 * Everything here deals in two clocks and it matters which is which. The schedule is
 * stored in the mentor's *own* timezone, because that is the clock they think in when
 * they say "mornings". Slots come back as UTC timestamps, because that is the only
 * form that means the same thing to a mentor in Riga and a student in Kathmandu. The
 * conversion happens once, here, and no caller should ever see a local time it did
 * not ask to format.
 */
class WPCPM_Mentor_Availability {

	/** User meta: the mentor's schedule. */
	const META = 'wpcpm_mentor_availability';

	/**
	 * User meta: which timezone to *show* times in.
	 *
	 * Set on mentors and students alike, which is why it is not prefixed as a mentor
	 * key. WordPress has a site timezone and no per-user one, and a program running
	 * across a dozen countries cannot show every student Warsaw's clock.
	 */
	const META_TIMEZONE = 'wpcpm_timezone';

	const ACTION_SAVE = 'wpcpm_save_availability';

	/**
	 * Windows offered per day in the editor.
	 *
	 * The model takes any number; two is what the form draws, because a split day —
	 * before work and after it — is the common case and a third row is not.
	 */
	const WINDOWS_PER_DAY = 2;

	/** Longest horizon a caller may ask for, whatever the schedule says. */
	const MAX_HORIZON = 120;

	/** Ceiling on generated slots, so a wide schedule cannot build an endless page. */
	const MAX_SLOTS = 600;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * The shape of a schedule that has never been set.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array(
			// Empty rather than a guessed nine-to-five: a schedule nobody chose would
			// still take bookings, and the mentor would find calls in it.
			'weekly'      => array(),
			'timezone'    => wp_timezone_string(),
			'duration'    => 30,
			// Enough notice that a call is never a surprise on the day.
			'lead_hours'  => 24,
			'horizon'     => 28,
			'per_student' => 1,
			'blocked'     => array(),
			'note'        => '',
			// Where the call happens. Empty until a mentor says, and a booking confirmation
			// that cannot say where is the reason this field exists.
			'meeting_url' => '',
		);

		/**
		 * Filter the default call schedule.
		 *
		 * @param array $defaults Default schedule.
		 */
		return (array) apply_filters( 'wpcpm_availability_defaults', $defaults );
	}

	/**
	 * A mentor's schedule, with every key present.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @return array
	 */
	public static function get( $mentor_id ) {
		$stored = get_user_meta( (int) $mentor_id, self::META, true );
		$stored = is_array( $stored ) ? $stored : array();

		return self::normalize( array_merge( self::defaults(), $stored ) );
	}

	/**
	 * Whether a mentor has published any availability at all.
	 *
	 * Distinct from "has no slots left": a mentor whose next fortnight is fully booked
	 * still has a schedule, and the student needs to be told the difference.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @return bool
	 */
	public static function is_published( $mentor_id ) {
		$schedule = self::get( $mentor_id );

		foreach ( $schedule['weekly'] as $windows ) {
			if ( ! empty( $windows ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Force a stored schedule into a shape the rest of the class can trust.
	 *
	 * Everything downstream — slot generation especially — assumes windows are sane,
	 * ordered pairs of `H:i` on days 1 to 7. Validating once on the way out of the
	 * database means a hand-edited or part-migrated value degrades to "no
	 * availability" rather than to a loop that never terminates.
	 *
	 * @param array $schedule Raw schedule.
	 * @return array
	 */
	private static function normalize( array $schedule ) {
		$schedule['timezone'] = self::timezone_name( isset( $schedule['timezone'] ) ? $schedule['timezone'] : '' );

		// A duration that does not divide the hour makes for slots nobody can read;
		// these are the only lengths the form offers, and the only ones accepted.
		$schedule['duration'] = in_array( (int) $schedule['duration'], self::durations(), true )
			? (int) $schedule['duration']
			: 30;

		$schedule['lead_hours']  = max( 0, min( 336, (int) $schedule['lead_hours'] ) );
		$schedule['horizon']     = max( 1, min( self::MAX_HORIZON, (int) $schedule['horizon'] ) );
		$schedule['per_student'] = max( 1, min( 10, (int) $schedule['per_student'] ) );
		$schedule['note']        = sanitize_textarea_field( self::scalar( $schedule['note'] ) );
		$schedule['meeting_url'] = self::meeting_url( self::scalar( isset( $schedule['meeting_url'] ) ? $schedule['meeting_url'] : '' ) );

		$weekly = array();

		foreach ( (array) $schedule['weekly'] as $day => $windows ) {
			$day = (int) $day;

			if ( $day < 1 || $day > 7 ) {
				continue;
			}

			$clean = array();

			foreach ( (array) $windows as $window ) {
				$start = self::time_string( isset( $window['start'] ) ? $window['start'] : '' );
				$end   = self::time_string( isset( $window['end'] ) ? $window['end'] : '' );

				// A window that ends before it starts, or is shorter than one slot, can
				// only produce nothing — dropped here so it is not carried around.
				if ( '' === $start || '' === $end || $end <= $start ) {
					continue;
				}

				$clean[] = array(
					'start' => $start,
					'end'   => $end,
				);
			}

			if ( ! empty( $clean ) ) {
				usort( $clean, array( __CLASS__, 'compare_windows' ) );
				$weekly[ $day ] = $clean;
			}
		}

		ksort( $weekly );

		$schedule['weekly'] = $weekly;

		$blocked = array();

		foreach ( (array) $schedule['blocked'] as $date ) {
			$date = self::date_string( $date );

			if ( '' !== $date ) {
				$blocked[ $date ] = true;
			}
		}

		$schedule['blocked'] = array_keys( $blocked );
		sort( $schedule['blocked'] );

		return $schedule;
	}

	/**
	 * A meeting link, or nothing.
	 *
	 * Restricted to http and https by `esc_url_raw`'s protocol list. This value reaches a
	 * calendar file's `LOCATION` and a link in an email, and a `javascript:` or `data:` URL
	 * in either is somebody else's exploit rather than a video room.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function meeting_url( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$url = esc_url_raw( $value, array( 'http', 'https' ) );

		return $url ? $url : '';
	}

	/**
	 * Where a mentor's calls happen, if they have said.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @return string
	 */
	public static function meeting_place( $mentor_id ) {
		$schedule = self::get( $mentor_id );

		return isset( $schedule['meeting_url'] ) ? (string) $schedule['meeting_url'] : '';
	}

	/**
	 * Order two windows by start time.
	 *
	 * @param array $a First window.
	 * @param array $b Second window.
	 * @return int
	 */
	public static function compare_windows( $a, $b ) {
		return strcmp( $a['start'], $b['start'] );
	}

	/**
	 * Slot lengths on offer, in minutes.
	 *
	 * @return int[]
	 */
	public static function durations() {
		return array( 15, 20, 30, 45, 60, 90 );
	}

	/**
	 * A submitted value as a string, or an empty string if it was not one.
	 *
	 * Anything posted can be an array, and casting one to string is a PHP warning and
	 * the word "Array" where a value should be. Nothing here wants a non-scalar, so the
	 * shape is refused rather than coerced.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function scalar( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Validate an `H:i` time.
	 *
	 * @param mixed $value Raw value.
	 * @return string Time, or an empty string.
	 */
	public static function time_string( $value ) {
		$value = self::scalar( $value );
		$value = trim( $value );

		if ( ! preg_match( '/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Validate a `Y-m-d` date.
	 *
	 * `checkdate()` as well as the pattern, so 2026-02-31 is rejected rather than
	 * silently rolled into March by the date library later on.
	 *
	 * @param mixed $value Raw value.
	 * @return string Date, or an empty string.
	 */
	public static function date_string( $value ) {
		$value = trim( self::scalar( $value ) );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return '';
		}

		if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * A timezone name that PHP will accept.
	 *
	 * @param string $name Candidate name.
	 * @return string
	 */
	public static function timezone_name( $name ) {
		$name = trim( self::scalar( $name ) );

		if ( '' !== $name && in_array( $name, timezone_identifiers_list(), true ) ) {
			return $name;
		}

		return wp_timezone_string();
	}

	/**
	 * A timezone object, falling back to the site's.
	 *
	 * @param string $name Timezone name.
	 * @return DateTimeZone
	 */
	public static function timezone( $name ) {
		try {
			return new DateTimeZone( self::timezone_name( $name ) );
		} catch ( Exception $e ) {
			// `wp_timezone_string()` can be a raw offset such as "+05:45" on sites that
			// never set a city, and older PHP refuses those as timezone names.
			return wp_timezone();
		}
	}

	/**
	 * Which timezone to show a given user their times in.
	 *
	 * @param int $user_id User ID.
	 * @return DateTimeZone
	 */
	public static function viewer_timezone( $user_id ) {
		$stored = get_user_meta( (int) $user_id, self::META_TIMEZONE, true );

		return self::timezone( is_string( $stored ) ? $stored : '' );
	}

	/**
	 * Whether a user has chosen a timezone, as opposed to inheriting the site's.
	 *
	 * The calendar uses this to decide whether to offer the browser's zone: a student
	 * who has picked one should not have it quietly replaced on the next visit.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_timezone( $user_id ) {
		$stored = get_user_meta( (int) $user_id, self::META_TIMEZONE, true );

		return is_string( $stored ) && '' !== trim( $stored );
	}

	/**
	 * Store a user's display timezone.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Timezone name.
	 * @return bool Whether it was accepted.
	 */
	public static function set_timezone( $user_id, $name ) {
		$name = trim( self::scalar( $name ) );

		if ( '' === $name || ! in_array( $name, timezone_identifiers_list(), true ) ) {
			return false;
		}

		update_user_meta( (int) $user_id, self::META_TIMEZONE, $name );

		return true;
	}

	/**
	 * Every bookable slot for a mentor, as UTC timestamps.
	 *
	 * "Bookable" means inside a weekly window, not on a blocked date, far enough ahead
	 * to satisfy the mentor's notice period, and not already taken. Slots that fail
	 * any of those never reach the caller, so no screen has to decide what to gray out
	 * and no two screens can disagree about it.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @param int $days      Optional horizon override, in days.
	 * @return array[] Each entry has `start`, `end` (UTC timestamps) and `date`
	 *                 (`Y-m-d` in the mentor's timezone).
	 */
	public static function slots( $mentor_id, $days = 0 ) {
		$schedule = self::get( $mentor_id );

		if ( empty( $schedule['weekly'] ) ) {
			return array();
		}

		$zone     = self::timezone( $schedule['timezone'] );
		$now      = time();
		$earliest = $now + ( $schedule['lead_hours'] * HOUR_IN_SECONDS );
		$horizon  = $days > 0 ? min( (int) $days, self::MAX_HORIZON ) : $schedule['horizon'];
		$length   = new DateInterval( 'PT' . $schedule['duration'] . 'M' );
		$seconds  = $schedule['duration'] * MINUTE_IN_SECONDS;

		// Taken slots are fetched once for the whole window rather than queried per
		// candidate: a 28-day schedule can hold several hundred slots, and a query
		// each would be several hundred queries to draw one calendar.
		$taken = WPCPM_Mentor_Calls::taken_starts(
			$mentor_id,
			$now,
			$now + ( ( $horizon + 1 ) * DAY_IN_SECONDS )
		);

		$midnight = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $zone )->setTime( 0, 0 );
		$slots    = array();

		for ( $offset = 0; $offset <= $horizon; $offset++ ) {
			// Stepped in whole days from a midnight rather than by adding 86400
			// seconds, so the day after a clock change is still that day.
			$day  = $midnight->modify( '+' . $offset . ' days' );
			$date = $day->format( 'Y-m-d' );

			if ( in_array( $date, $schedule['blocked'], true ) ) {
				continue;
			}

			$weekday = (int) $day->format( 'N' );

			if ( empty( $schedule['weekly'][ $weekday ] ) ) {
				continue;
			}

			// Wall-clock times already offered on this day. On the night the clocks go
			// back, an hour of local time happens twice, and stepping by absolute time
			// through it produces two distinct instants that both render "03:00" — two
			// buttons a student cannot tell apart. A schedule is written in wall-clock
			// terms, so "Sunday 03:00" is one appointment; the second pass is dropped.
			$offered = array();

			foreach ( $schedule['weekly'][ $weekday ] as $window ) {
				$open  = self::at( $day, $window['start'] );
				$close = self::at( $day, $window['end'] );

				if ( null === $open || null === $close ) {
					continue;
				}

				$cursor = $open;

				while ( $cursor->getTimestamp() + $seconds <= $close->getTimestamp() ) {
					$start  = $cursor->getTimestamp();
					$label  = $cursor->format( 'H:i' );
					$cursor = $cursor->add( $length );

					if ( isset( $offered[ $label ] ) ) {
						continue;
					}

					$offered[ $label ] = true;

					if ( $start < $earliest || isset( $taken[ $start ] ) ) {
						continue;
					}

					$slots[ $start ] = array(
						'start' => $start,
						'end'   => $start + $seconds,
						'date'  => $date,
					);

					if ( count( $slots ) >= self::MAX_SLOTS ) {
						break 3;
					}
				}
			}
		}

		ksort( $slots );

		return array_values( $slots );
	}

	/**
	 * Whether one exact slot is still open.
	 *
	 * Asked again inside the booking lock, because the calendar a student is looking
	 * at was generated before they clicked.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @param int $start     UTC timestamp.
	 * @return array|null The slot, or null if it is not on offer.
	 */
	public static function find_slot( $mentor_id, $start ) {
		$start = (int) $start;

		foreach ( self::slots( $mentor_id ) as $slot ) {
			if ( $slot['start'] === $start ) {
				return $slot;
			}
		}

		return null;
	}

	/**
	 * A local time on a given day, or null if the clock skipped it.
	 *
	 * Spring-forward deletes an hour from the local day, and PHP resolves a time
	 * inside it by rolling forward — which would put a 02:30 slot at 03:30 and out of
	 * its own window. Comparing the formatted result catches that.
	 *
	 * @param DateTimeImmutable $day  Midnight on the day, in the target zone.
	 * @param string            $time `H:i`.
	 * @return DateTimeImmutable|null
	 */
	private static function at( DateTimeImmutable $day, $time ) {
		$parts  = explode( ':', $time );
		$hour   = isset( $parts[0] ) ? (int) $parts[0] : 0;
		$minute = isset( $parts[1] ) ? (int) $parts[1] : 0;

		$moment = $day->setTime( $hour, $minute );

		return ( $moment->format( 'H:i' ) === $time ) ? $moment : null;
	}

	/**
	 * Weekday names in ISO order, Monday first.
	 *
	 * Not `start_of_week`: this is the storage order, and it has to stay put whatever
	 * a site's display preference is.
	 *
	 * @return array<int, string>
	 */
	public static function weekdays() {
		global $wp_locale;

		$names = array();

		for ( $iso = 1; $iso <= 7; $iso++ ) {
			// `get_weekday()` is indexed Sunday-first, which ISO 7 is.
			$names[ $iso ] = $wp_locale instanceof WP_Locale
				? $wp_locale->get_weekday( 7 === $iso ? 0 : $iso )
				: gmdate( 'l', strtotime( 'Sunday +' . ( 7 === $iso ? 0 : $iso ) . ' days' ) );
		}

		return $names;
	}

	/**
	 * Whether a user may edit a mentor's schedule.
	 *
	 * @param int              $mentor_id Mentor user ID.
	 * @param int|WP_User|null $user      Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function user_can_edit( $mentor_id, $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) ) {
			return true;
		}

		return ( (int) $user->ID === (int) $mentor_id ) && WPCPM_Mentors_Dashboard::is_mentor( $user );
	}

	/**
	 * Save a schedule.
	 */
	public static function handle_save() {
		$mentor = isset( $_POST['mentor'] ) ? absint( wp_unslash( $_POST['mentor'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.

		check_admin_referer( self::ACTION_SAVE . '_' . $mentor );

		if ( ! self::user_can_edit( $mentor ) ) {
			wp_die( esc_html__( 'You cannot change that mentor\'s availability.', 'wpcredits-program-manager' ), 403 );
		}

		$schedule = self::get( $mentor );

		$posted = isset( $_POST['availability'] ) && is_array( $_POST['availability'] )
			? wp_unslash( $_POST['availability'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is validated below.
			: array();

		$weekly = array();

		foreach ( (array) ( isset( $posted['weekly'] ) ? $posted['weekly'] : array() ) as $day => $windows ) {
			$day = (int) $day;

			if ( $day < 1 || $day > 7 ) {
				continue;
			}

			foreach ( (array) $windows as $window ) {
				$start = self::time_string( isset( $window['start'] ) ? $window['start'] : '' );
				$end   = self::time_string( isset( $window['end'] ) ? $window['end'] : '' );

				// One side filled in on its own is a half-finished row, not an error
				// worth refusing the whole form over.
				if ( '' === $start || '' === $end ) {
					continue;
				}

				$weekly[ $day ][] = array(
					'start' => $start,
					'end'   => $end,
				);
			}
		}

		$schedule['weekly'] = $weekly;

		// Every field below is read through `scalar()`. The whole `availability` key is
		// attacker-shaped: any of these can arrive as an array however the form is drawn,
		// and `(string) array()` is an "Array to string conversion" warning plus the
		// literal value "Array" written to the schedule.
		if ( isset( $posted['timezone'] ) ) {
			$schedule['timezone'] = self::timezone_name( self::scalar( $posted['timezone'] ) );
		}

		foreach ( array( 'duration', 'lead_hours', 'horizon', 'per_student' ) as $key ) {
			if ( isset( $posted[ $key ] ) ) {
				$schedule[ $key ] = (int) self::scalar( $posted[ $key ] );
			}
		}

		if ( isset( $posted['note'] ) ) {
			$schedule['note'] = sanitize_textarea_field( self::scalar( $posted['note'] ) );
		}

		if ( isset( $posted['meeting_url'] ) ) {
			$schedule['meeting_url'] = self::meeting_url( self::scalar( $posted['meeting_url'] ) );
		}

		// Blocked days arrive as free text — one date per line is the only thing a
		// mentor can type without a date picker for each.
		if ( isset( $posted['blocked'] ) ) {
			$dates = preg_split( '/[\s,;]+/', self::scalar( $posted['blocked'] ) );
			$clean = array();

			foreach ( (array) $dates as $date ) {
				$date = self::date_string( $date );

				if ( '' !== $date ) {
					$clean[] = $date;
				}
			}

			$schedule['blocked'] = $clean;
		}

		update_user_meta( $mentor, self::META, self::normalize( $schedule ) );

		// The mentor's own display timezone follows the schedule's, so the calls they
		// are shown are in the clock they just said they work in.
		self::set_timezone( $mentor, $schedule['timezone'] );

		self::redirect_back( $mentor, 'saved' );
	}

	/**
	 * Return to the mentor page.
	 *
	 * @param int    $mentor Mentor user ID.
	 * @param string $status Outcome flag.
	 */
	private static function redirect_back( $mentor, $status ) {
		$page = WPCPM_Mentors_Dashboard::page_url();

		if ( '' === $page ) {
			$page = home_url( '/' );
		}

		WPCPM_Flash::set( 'availability', $status );

		$args = array();

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$args['wpcpm_mentor'] = (int) $mentor;
		}

		wp_safe_redirect( add_query_arg( $args, $page ) . '#wpcpm-availability' );
		exit;
	}

	/**
	 * A one-line summary of the schedule, for the mentor's own page.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @return string
	 */
	public static function summary( $mentor_id ) {
		$schedule = self::get( $mentor_id );

		if ( empty( $schedule['weekly'] ) ) {
			return esc_html__( 'No availability set, so students cannot book a call yet.', 'wpcredits-program-manager' );
		}

		$names = self::weekdays();
		$days  = array();

		foreach ( $schedule['weekly'] as $day => $windows ) {
			$times = array();

			foreach ( $windows as $window ) {
				$times[] = $window['start'] . '–' . $window['end'];
			}

			$days[] = sprintf(
				/* translators: 1: weekday name, 2: list of time ranges. */
				__( '%1$s %2$s', 'wpcredits-program-manager' ),
				$names[ $day ],
				implode( ', ', $times )
			);
		}

		return sprintf(
			/* translators: 1: slot length in minutes, 2: weekday and time list, 3: timezone name. */
			esc_html__( '%1$s-minute calls: %2$s (%3$s)', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( $schedule['duration'] ) ),
			esc_html( implode( '; ', $days ) ),
			esc_html( self::zone_label( $schedule['timezone'] ) )
		);
	}

	/**
	 * A timezone name as a person reads it.
	 *
	 * @param string $name Timezone name.
	 * @return string
	 */
	public static function zone_label( $name ) {
		return str_replace( array( '_', '/' ), array( ' ', ' / ' ), self::timezone_name( $name ) );
	}

	/**
	 * Render the schedule editor.
	 *
	 * @param WP_User $mentor Mentor whose schedule this is.
	 */
	public static function render_editor( WP_User $mentor ) {
		if ( ! self::user_can_edit( $mentor->ID ) ) {
			return;
		}

		$schedule = self::get( $mentor->ID );
		$names    = self::weekdays();
		$is_self  = ( get_current_user_id() === (int) $mentor->ID );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag.
		$status = sanitize_key( (string) WPCPM_Flash::take( 'availability' ) );

		$published = self::is_published( $mentor->ID );

		// The unset state is a class, not a colour decided here — the stylesheets say what
		// it looks like, and one of them is a theme's.
		printf(
			'<section class="wpcpm-availability%s" id="wpcpm-availability">',
			$published ? '' : ' wpcpm-availability--unset'
		);

		// The confirmation sits *outside* the disclosure, so it is readable with the panel
		// shut. It used to be in the body, which is the only reason the panel had to spring
		// open after every save — and springing open after every save is the thing this is
		// closed by default to avoid.
		if ( 'saved' === $status ) {
			printf(
				'<p class="wpcpm-notes__message is-success" role="status">%s</p>',
				esc_html__( 'Availability saved. Students can book the slots it opens.', 'wpcredits-program-manager' )
			);
		}

		// Closed. Always — at rest, with nothing published, and after a save. A panel that
		// unfolds on arrival is a panel that has to be folded away again, and the summary
		// line beside the title already says what the schedule is.
		echo '<details class="wpcpm-availability__disclosure">';

		printf(
			'<summary class="wpcpm-availability__summary"><span class="wpcpm-availability__title">%1$s</span> <span class="wpcpm-availability__state">%2$s</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Your availability for calls', 'wpcredits-program-manager' ),
			wp_kses_post( self::summary( $mentor->ID ) )
		);

		echo '<div class="wpcpm-availability__body">';

		printf(
			'<p class="wpcpm-availability__intro">%s</p>',
			esc_html(
				$is_self
					? __( 'Set the hours you are free each week. Students assigned to you pick a slot inside them, and you keep every booking on this page.', 'wpcredits-program-manager' )
					: __( 'Set the hours this mentor is free each week. Their students pick a slot inside them.', 'wpcredits-program-manager' )
			)
		);

		printf(
			'<form class="wpcpm-availability__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving…', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE . '_' . $mentor->ID );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';
		printf( '<input type="hidden" name="mentor" value="%d" />', (int) $mentor->ID );

		self::render_week( $schedule, $names );
		self::render_options( $schedule, $mentor->ID );

		printf(
			'<p class="wpcpm-availability__actions"><button type="submit" class="wpcpm-button">%s</button></p>',
			esc_html__( 'Save availability', 'wpcredits-program-manager' )
		);

		echo '</form>';
		echo '</div>';
		echo '</details>';
		echo '</section>';
	}

	/**
	 * Render the seven day rows.
	 *
	 * @param array $schedule Schedule.
	 * @param array $names    Weekday names.
	 */
	private static function render_week( array $schedule, array $names ) {
		echo '<fieldset class="wpcpm-availability__week">';
		printf(
			'<legend class="wpcpm-availability__legend">%s</legend>',
			esc_html__( 'Weekly hours', 'wpcredits-program-manager' )
		);

		echo '<table class="wpcpm-availability__table"><tbody>';

		for ( $day = 1; $day <= 7; $day++ ) {
			$windows = isset( $schedule['weekly'][ $day ] ) ? $schedule['weekly'][ $day ] : array();

			echo '<tr class="wpcpm-availability__day">';
			printf( '<th scope="row">%s</th>', esc_html( $names[ $day ] ) );
			echo '<td class="wpcpm-availability__windows">';

			for ( $index = 0; $index < self::WINDOWS_PER_DAY; $index++ ) {
				$start = isset( $windows[ $index ]['start'] ) ? $windows[ $index ]['start'] : '';
				$end   = isset( $windows[ $index ]['end'] ) ? $windows[ $index ]['end'] : '';
				$base  = sprintf( 'availability[weekly][%1$d][%2$d]', $day, $index );
				$id    = sprintf( 'wpcpm-av-%1$d-%2$d', $day, $index );

				echo '<span class="wpcpm-availability__window">';
				printf(
					'<label class="screen-reader-text" for="%1$s-start">%2$s</label>',
					esc_attr( $id ),
					/* translators: %s: weekday name. */
					esc_html( sprintf( __( 'Start time on %s', 'wpcredits-program-manager' ), $names[ $day ] ) )
				);
				printf(
					'<input type="time" id="%1$s-start" name="%2$s[start]" value="%3$s" step="900" />',
					esc_attr( $id ),
					esc_attr( $base ),
					esc_attr( $start )
				);
				echo '<span class="wpcpm-availability__to" aria-hidden="true">–</span>';
				printf(
					'<label class="screen-reader-text" for="%1$s-end">%2$s</label>',
					esc_attr( $id ),
					/* translators: %s: weekday name. */
					esc_html( sprintf( __( 'End time on %s', 'wpcredits-program-manager' ), $names[ $day ] ) )
				);
				printf(
					'<input type="time" id="%1$s-end" name="%2$s[end]" value="%3$s" step="900" />',
					esc_attr( $id ),
					esc_attr( $base ),
					esc_attr( $end )
				);
				echo '</span>';
			}

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		self::render_copy( $names );

		printf(
			'<p class="wpcpm-availability__hint">%s</p>',
			esc_html__( 'Leave a day blank to take no calls that day. Two ranges per day let you split a morning from an afternoon.', 'wpcredits-program-manager' )
		);

		echo '</fieldset>';
	}

	/**
	 * "Copy Monday to weekdays" — filling in the week without retyping it.
	 *
	 * Scripted by nature, and rendered `hidden` because of it: all it does is write values
	 * into inputs this form already posts, so there is no handler behind it and nothing
	 * for it to degrade to. A mentor without JavaScript is not shown a control that would
	 * do nothing; they type the days out, which is what they did before this existed.
	 *
	 * Nothing is saved by copying. The values land in the form and the mentor still presses
	 * Save — so a copy that went to the wrong days is undone by not saving, which is a much
	 * better escape hatch than an undo button.
	 *
	 * @param array $names Weekday names.
	 */
	private static function render_copy( array $names ) {
		$targets = array(
			'all'      => __( 'every day', 'wpcredits-program-manager' ),
			'weekdays' => __( 'weekdays', 'wpcredits-program-manager' ),
			'weekend'  => __( 'the weekend', 'wpcredits-program-manager' ),
		);

		printf(
			'<div class="wpcpm-availability__copy" data-wpcpm-copy data-wpcpm-windows="%1$d" data-wpcpm-copied="%2$s" data-wpcpm-blank="%3$s" hidden>',
			(int) self::WINDOWS_PER_DAY,
			/* translators: 1: weekday name, 2: which days it was copied to. */
			esc_attr__( 'Copied %1$s to %2$s. Press Save availability to keep it.', 'wpcredits-program-manager' ),
			/* translators: %s: weekday name. */
			esc_attr__( '%s has no hours set, so there was nothing to copy.', 'wpcredits-program-manager' )
		);

		printf(
			'<label class="wpcpm-availability__copy-label" for="wpcpm-av-copy-from">%s</label>',
			esc_html__( 'Copy hours from', 'wpcredits-program-manager' )
		);

		echo '<select id="wpcpm-av-copy-from" data-wpcpm-copy-from>';
		for ( $day = 1; $day <= 7; $day++ ) {
			printf( '<option value="%1$d">%2$s</option>', (int) $day, esc_html( $names[ $day ] ) );
		}
		echo '</select>';

		printf(
			'<label class="wpcpm-availability__copy-label" for="wpcpm-av-copy-to">%s</label>',
			esc_html__( 'to', 'wpcredits-program-manager' )
		);

		echo '<select id="wpcpm-av-copy-to" data-wpcpm-copy-to>';
		foreach ( $targets as $key => $label ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $key ), esc_html( $label ) );
		}
		echo '</select>';

		// `type="button"`: inside a form, a button with no type submits it, which would
		// save the schedule the moment somebody meant to copy a row.
		printf(
			'<button type="button" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-copy-go>%s</button>',
			esc_html__( 'Copy', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-availability__copy-status" role="status" data-wpcpm-copy-status></p>'
		);

		echo '</div>';
	}

	/**
	 * Render the slot length, notice and horizon fields.
	 *
	 * @param array $schedule  Schedule.
	 * @param int   $mentor_id Mentor user ID.
	 */
	private static function render_options( array $schedule, $mentor_id ) {
		echo '<fieldset class="wpcpm-availability__options">';
		printf(
			'<legend class="wpcpm-availability__legend">%s</legend>',
			esc_html__( 'How calls are offered', 'wpcredits-program-manager' )
		);

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-timezone">%s</label>',
			esc_html__( 'Your timezone', 'wpcredits-program-manager' )
		);
		echo '<select id="wpcpm-av-timezone" name="availability[timezone]">';
		foreach ( timezone_identifiers_list() as $zone ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $zone ),
				selected( $zone, $schedule['timezone'], false ),
				esc_html( self::zone_label( $zone ) )
			);
		}
		echo '</select>';
		printf(
			'<span class="wpcpm-availability__note">%s</span>',
			esc_html__( 'The hours above are in this timezone. Students see the same slots on their own clock.', 'wpcredits-program-manager' )
		);
		echo '</p>';

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-duration">%s</label>',
			esc_html__( 'Call length', 'wpcredits-program-manager' )
		);
		echo '<select id="wpcpm-av-duration" name="availability[duration]">';
		foreach ( self::durations() as $minutes ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $minutes,
				selected( $minutes, $schedule['duration'], false ),
				esc_html(
					sprintf(
						/* translators: %s: number of minutes. */
						_n( '%s minute', '%s minutes', $minutes, 'wpcredits-program-manager' ),
						number_format_i18n( $minutes )
					)
				)
			);
		}
		echo '</select>';
		echo '</p>';

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-lead">%s</label>',
			esc_html__( 'Shortest notice', 'wpcredits-program-manager' )
		);
		printf(
			'<input type="number" id="wpcpm-av-lead" name="availability[lead_hours]" value="%d" min="0" max="336" step="1" />',
			(int) $schedule['lead_hours']
		);
		printf(
			'<span class="wpcpm-availability__note">%s</span>',
			esc_html__( 'Hours. Slots closer than this are not offered.', 'wpcredits-program-manager' )
		);
		echo '</p>';

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-horizon">%s</label>',
			esc_html__( 'How far ahead', 'wpcredits-program-manager' )
		);
		printf(
			'<input type="number" id="wpcpm-av-horizon" name="availability[horizon]" value="%1$d" min="1" max="%2$d" step="1" />',
			(int) $schedule['horizon'],
			(int) self::MAX_HORIZON
		);
		printf(
			'<span class="wpcpm-availability__note">%s</span>',
			esc_html__( 'Days. How far into the future students can book.', 'wpcredits-program-manager' )
		);
		echo '</p>';

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-per-student">%s</label>',
			esc_html__( 'Bookings per student', 'wpcredits-program-manager' )
		);
		printf(
			'<input type="number" id="wpcpm-av-per-student" name="availability[per_student]" value="%d" min="1" max="10" step="1" />',
			(int) $schedule['per_student']
		);
		printf(
			'<span class="wpcpm-availability__note">%s</span>',
			esc_html__( 'How many upcoming calls one student may hold at once.', 'wpcredits-program-manager' )
		);
		echo '</p>';

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-blocked">%s</label>',
			esc_html__( 'Days off', 'wpcredits-program-manager' )
		);
		printf(
			'<textarea id="wpcpm-av-blocked" name="availability[blocked]" rows="2" placeholder="2026-12-24">%s</textarea>',
			esc_textarea( implode( "\n", $schedule['blocked'] ) )
		);
		printf(
			'<span class="wpcpm-availability__note">%s</span>',
			esc_html__( 'One date per line, as 2026-12-24. Nothing is offered on these days.', 'wpcredits-program-manager' )
		);
		echo '</p>';

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-meeting">%s</label>',
			esc_html__( 'Where we meet', 'wpcredits-program-manager' )
		);
		printf(
			// The placeholder is not translated: it is an example URL, and there is nothing in
			// it to translate. gettext warns about embedded URLs for exactly this reason.
			'<input type="url" id="wpcpm-av-meeting" name="availability[meeting_url]" value="%1$s" placeholder="%2$s" inputmode="url" />',
			esc_attr( $schedule['meeting_url'] ),
			'https://meet.example.com/your-room'
		);
		printf(
			'<span class="wpcpm-availability__note">%s</span>',
			esc_html__( 'Your video room. It goes in the booking confirmation and the calendar invitation, so neither of you has to arrange it afterwards.', 'wpcredits-program-manager' )
		);
		echo '</p>';

		echo '<p class="wpcpm-availability__field">';
		printf(
			'<label for="wpcpm-av-note">%s</label>',
			esc_html__( 'Note for students', 'wpcredits-program-manager' )
		);
		printf(
			'<textarea id="wpcpm-av-note" name="availability[note]" rows="2" maxlength="500" placeholder="%1$s">%2$s</textarea>',
			esc_attr__( 'Bring a link to whatever you are working on.', 'wpcredits-program-manager' ),
			esc_textarea( $schedule['note'] )
		);
		printf(
			'<span class="wpcpm-availability__note">%s</span>',
			esc_html__( 'Shown on the booking calendar, above the slots.', 'wpcredits-program-manager' )
		);
		echo '</p>';

		echo '</fieldset>';

		// Free-standing rather than a form field: it is the same schedule seen from the
		// student's side, and the quickest way for a mentor to check it looks right.
		$open = count( self::slots( $mentor_id ) );

		printf(
			'<p class="wpcpm-availability__count">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of slots. */
					_n(
						'%s slot is open for booking right now.',
						'%s slots are open for booking right now.',
						$open,
						'wpcredits-program-manager'
					),
					number_format_i18n( $open )
				)
			)
		);
	}
}
