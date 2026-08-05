<?php
/**
 * Mentors module — the call calendar, on both dashboards.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draws the booking calendar for a student and the diary for a mentor.
 *
 * The month grid and the day picker are ordinary links, and booking is an ordinary
 * form post. Nothing here needs JavaScript: a student on a borrowed laptop with a
 * strict extension still has to be able to book a call, and a calendar is exactly the
 * kind of thing that is usually unusable without it.
 *
 * Everything is rendered on the *viewer's* clock. The mentor's schedule is stored in
 * the mentor's timezone and slots come back from it as UTC timestamps; which calendar
 * day a slot falls on is then a question about the viewer's zone, not the mentor's — a
 * 23:00 slot in Riga is the small hours of the next day in Kathmandu, and putting it on
 * the wrong square is how somebody misses a call.
 */
class WPCPM_Call_Calendar {

	/** Query argument naming the month on show, as `Y-m`. */
	const ARG_MONTH = 'wpcpm_month';

	/** Query argument naming the selected day, as `Y-m-d`. */
	const ARG_DAY = 'wpcpm_day';

	/** Anchor both dashboards' call sections carry. */
	const ANCHOR = 'wpcpm-calls';

	const STYLE  = 'wpcpm-call-calendar';
	const SCRIPT = 'wpcpm-call-calendar';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register the calendar's own stylesheet and script.
	 *
	 * The stylesheet declares the mentor dashboard's as a dependency so it loads after
	 * it and can rely on its `--wpcpm-*` tokens; both dashboards enqueue it, since the
	 * mentor's editor and the student's picker share most of it.
	 *
	 * Guarded per asset rather than behind one early return: registering the style and
	 * skipping the script is a bug that only shows up as "the timezone hint never
	 * appears", which is a long way from where it would have been introduced.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/calendar.css',
				array( WPCPM_Mentors_Dashboard::STYLE ),
				WPCPM_VERSION
			);
		}

		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::SCRIPT,
				WPCPM_PLUGIN_URL . 'assets/js/calendar.js',
				array(),
				WPCPM_VERSION,
				true
			);
		}
	}

	/*
	 * The student's side
	 * --------------------------------------------------------------------
	 */

	/**
	 * Render the student's call section.
	 *
	 * @param WP_User $student    The student whose page this is.
	 * @param bool    $can_manage Whether the viewer manages the program.
	 */
	public static function render_student( WP_User $student, $can_manage ) {
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );

		$viewer_is_student = ( get_current_user_id() === (int) $student->ID );
		$mentor            = WPCPM_Mentor_Calls::mentor_for_student( $student->ID );
		$zone              = WPCPM_Mentor_Availability::viewer_timezone( $student->ID );
		$upcoming          = WPCPM_Mentor_Calls::for_student( $student->ID, true );

		echo '<section class="wpcpm-student__section wpcpm-calls" id="' . esc_attr( self::ANCHOR ) . '">';

		printf(
			'<h3 class="wpcpm-student__heading">%s</h3>',
			esc_html__( 'My mentor call', 'wpcredits-program-manager' )
		);

		// Above the columns: it reports on the last action, which could have come from
		// either of them.
		self::render_message();

		// Two columns: what is booked, and what can be. They answer different questions —
		// "when am I speaking to my mentor" and "when could I" — and the second needs the
		// width a month grid wants, which is why this section spans the card rather than
		// sitting in one of the page's own columns.
		echo '<div class="wpcpm-calls__cols">';

		echo '<div class="wpcpm-calls__col wpcpm-calls__col--booked">';

		if ( ! empty( $upcoming ) ) {
			self::render_call_list( $upcoming, $zone, 'student' );
		} else {
			printf(
				'<p class="wpcpm-calls__empty">%s</p>',
				esc_html__( 'Nothing booked yet.', 'wpcredits-program-manager' )
			);
		}

		echo '</div>';

		echo '<div class="wpcpm-calls__col wpcpm-calls__col--pick">';

		if ( ! $mentor instanceof WP_User ) {
			printf(
				'<p class="wpcpm-calls__empty">%s</p>',
				esc_html__( 'No mentor is linked to your account yet. Once the program data names one, you can book a call here.', 'wpcredits-program-manager' )
			);
			self::close_student( $student, $viewer_is_student );

			return;
		}

		$reason = WPCPM_Mentor_Calls::why_not_bookable( $student->ID, $mentor );

		if ( '' !== $reason ) {
			printf( '<p class="wpcpm-calls__empty">%s</p>', esc_html( $reason ) );
			self::close_student( $student, $viewer_is_student );

			return;
		}

		// A manager looking at somebody else's page can book on their behalf, which is a
		// real need, but the copy should not pretend the call is the manager's own.
		if ( ! $viewer_is_student && $can_manage ) {
			printf(
				'<p class="wpcpm-calls__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: student's name. */
						__( 'Booking on behalf of %s.', 'wpcredits-program-manager' ),
						$student->display_name
					)
				)
			);
		}

		$schedule = WPCPM_Mentor_Availability::get( $mentor->ID );

		printf(
			'<p class="wpcpm-calls__intro">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: mentor's name, 2: call length in minutes. */
					__( 'Pick a time with %1$s. Calls are %2$s minutes.', 'wpcredits-program-manager' ),
					$mentor->display_name,
					number_format_i18n( $schedule['duration'] )
				)
			)
		);

		if ( '' !== $schedule['note'] ) {
			printf(
				'<p class="wpcpm-calls__mentor-note">%s</p>',
				esc_html( $schedule['note'] )
			);
		}

		if ( $viewer_is_student ) {
			self::render_timezone_form( $student->ID, $zone );
		}

		$slots = WPCPM_Mentor_Availability::slots( $mentor->ID );

		if ( empty( $slots ) ) {
			printf(
				'<p class="wpcpm-calls__empty">%s</p>',
				esc_html__( 'Your mentor has availability set but nothing is open at the moment — every slot in the current window is taken. Check back in a few days.', 'wpcredits-program-manager' )
			);
			self::close_student( $student, $viewer_is_student );

			return;
		}

		$by_day = self::group_by_day( $slots, $zone );
		$month  = self::resolve_month( $by_day, $zone );
		$day    = self::resolve_day( $by_day, $month );

		self::render_month( $by_day, $month, $zone );
		self::render_day( $by_day, $day, $zone, $student, $can_manage && ! $viewer_is_student );

		self::close_student( $student, $viewer_is_student );
	}

	/**
	 * Close the picking column, the column pair and the section.
	 *
	 * Four different paths through `render_student()` finish early — no mentor, not
	 * bookable, nothing open, or a full calendar — and every one of them is inside two
	 * open `<div>`s. Closing them by hand at each exit is how one of them eventually
	 * does not, and unbalanced markup on this page pulls the whole card apart.
	 *
	 * Being the single funnel, it is also where the group sessions render — so they appear on the
	 * three early exits too, which is where they matter most: a student whose mentor has published
	 * no private hours can still be invited to a session.
	 *
	 * @param WP_User|null $student           The student, when there is one.
	 * @param bool         $viewer_is_student Whether the viewer may join or leave.
	 */
	private static function close_student( $student = null, $viewer_is_student = false ) {
		echo '</div>';

		// Outside the picking column but inside the pair, so it sits under both and is not
		// squeezed into half the card.
		if ( $student instanceof WP_User ) {
			self::render_student_sessions( $student, $viewer_is_student );
		}

		echo '</div>';
		echo '</section>';
	}

	/**
	 * The student's group sessions, under their own calls.
	 *
	 * Called from every path that closes the booked column, including the early exits: a student
	 * whose mentor has published no private hours can still be invited to a session, and the
	 * version that only rendered this on the happy path hid it from exactly the people most
	 * likely to need it.
	 *
	 * @param WP_User $student           The student.
	 * @param bool    $viewer_is_student Whether the viewer may join or leave.
	 */
	private static function render_student_sessions( WP_User $student, $viewer_is_student ) {
		WPCPM_Group_Sessions::render_student_list( $student, $viewer_is_student );
	}

	/**
	 * Sort slots into calendar days on the viewer's clock.
	 *
	 * @param array[]      $slots Slots from the availability model.
	 * @param DateTimeZone $zone  Viewer's timezone.
	 * @return array<string, array[]> Keyed by `Y-m-d`, in order.
	 */
	private static function group_by_day( array $slots, DateTimeZone $zone ) {
		$days = array();

		foreach ( $slots as $slot ) {
			// Deliberately not `$slot['date']`, which is the mentor's calendar day.
			$key = wp_date( 'Y-m-d', $slot['start'], $zone );

			$days[ $key ][] = $slot;
		}

		ksort( $days );

		return $days;
	}

	/**
	 * Which month to draw.
	 *
	 * @param array        $by_day Slots keyed by day.
	 * @param DateTimeZone $zone Viewer's timezone.
	 * @return string `Y-m`.
	 */
	private static function resolve_month( array $by_day, DateTimeZone $zone ) {
		$months = array();

		foreach ( array_keys( $by_day ) as $date ) {
			$months[ substr( $date, 0, 7 ) ] = true;
		}

		$months = array_keys( $months );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$asked = WPCPM_Request::text( self::ARG_MONTH );

		// Only a month the calendar actually spans. Anything else — a hand-typed
		// argument, a stale bookmark — falls back rather than drawing an empty grid the
		// navigation cannot escape from.
		if ( preg_match( '/^\d{4}-\d{2}$/', $asked ) && in_array( $asked, $months, true ) ) {
			return $asked;
		}

		if ( ! empty( $months ) ) {
			return $months[0];
		}

		return wp_date( 'Y-m', time(), $zone );
	}

	/**
	 * Which day's slots to list.
	 *
	 * @param array  $by_day Slots keyed by day.
	 * @param string $month  Month on show, `Y-m`.
	 * @return string `Y-m-d`, or an empty string.
	 */
	private static function resolve_day( array $by_day, $month ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$asked = WPCPM_Request::text( self::ARG_DAY );

		if ( '' !== WPCPM_Mentor_Availability::date_string( $asked ) && isset( $by_day[ $asked ] ) ) {
			return $asked;
		}

		// Default to the soonest day in the month being shown, so the page always opens
		// on something bookable rather than on an instruction to click.
		foreach ( array_keys( $by_day ) as $date ) {
			if ( 0 === strpos( $date, $month . '-' ) ) {
				return $date;
			}
		}

		return '';
	}

	/**
	 * The current page's URL with calendar arguments replaced.
	 *
	 * @param array $args Arguments to set.
	 * @return string
	 */
	private static function url( array $args ) {
		// `wpcpm_call` used to be stripped here too, because the outcome travelled in the
		// URL and a month link had to shed it. It is a flash now, so there is nothing to
		// strip — and leaving the name here would suggest otherwise.
		$base = remove_query_arg(
			array( self::ARG_MONTH, self::ARG_DAY ),
			self::current_url()
		);

		return add_query_arg( $args, $base ) . '#' . self::ANCHOR;
	}

	/**
	 * The URL of the page being viewed.
	 *
	 * @return string
	 */
	private static function current_url() {
		global $wp;

		$path = isset( $wp->request ) ? $wp->request : '';
		$url  = home_url( '' !== $path ? user_trailingslashit( $path ) : '/' );

		// Keep the view state a manager is holding — which mentor or student they are
		// inspecting — rather than dropping them back to their own page on every click.
		//
		// `wpcpm_student_view`, not `wpcpm_student`: the latter is the *notes* focus on the
		// mentor page and carries an Airtable record ID, so keeping it did nothing here
		// while the argument that actually selects a student was being dropped — a manager
		// paging the calendar on somebody else's page landed back on their own.
		foreach ( array( 'wpcpm_mentor', 'wpcpm_student_view' ) as $keep ) {
			$id = WPCPM_Request::id( $keep );

			if ( $id ) {
				$url = add_query_arg( $keep, $id, $url );
			}
		}

		return $url;
	}

	/**
	 * Render the month grid.
	 *
	 * @param array        $by_day Slots keyed by day.
	 * @param string       $month  Month on show, `Y-m`.
	 * @param DateTimeZone $zone   Viewer's timezone.
	 */
	private static function render_month( array $by_day, $month, DateTimeZone $zone ) {
		global $wp_locale;

		$months = array();

		foreach ( array_keys( $by_day ) as $date ) {
			$months[ substr( $date, 0, 7 ) ] = true;
		}

		$months = array_keys( $months );
		$at     = array_search( $month, $months, true );
		$first  = new DateTimeImmutable( $month . '-01 00:00:00', $zone );
		$today  = wp_date( 'Y-m-d', time(), $zone );

		echo '<div class="wpcpm-calendar">';

		// Header: month name, and prev/next only where there is a month to go to.
		echo '<div class="wpcpm-calendar__head">';

		if ( false !== $at && $at > 0 ) {
			printf(
				'<a class="wpcpm-calendar__nav" href="%1$s" rel="prev">%2$s</a>',
				esc_url( self::url( array( self::ARG_MONTH => $months[ $at - 1 ] ) ) ),
				esc_html__( 'Earlier', 'wpcredits-program-manager' )
			);
		} else {
			echo '<span class="wpcpm-calendar__nav is-disabled" aria-hidden="true"></span>';
		}

		printf(
			'<h4 class="wpcpm-calendar__month">%s</h4>',
			esc_html( wp_date( 'F Y', $first->getTimestamp(), $zone ) )
		);

		if ( false !== $at && $at < ( count( $months ) - 1 ) ) {
			printf(
				'<a class="wpcpm-calendar__nav" href="%1$s" rel="next">%2$s</a>',
				esc_url( self::url( array( self::ARG_MONTH => $months[ $at + 1 ] ) ) ),
				esc_html__( 'Later', 'wpcredits-program-manager' )
			);
		} else {
			echo '<span class="wpcpm-calendar__nav is-disabled" aria-hidden="true"></span>';
		}

		echo '</div>';

		$start_of_week = (int) get_option( 'start_of_week', 1 );

		echo '<table class="wpcpm-calendar__grid">';
		printf(
			'<caption class="screen-reader-text">%s</caption>',
			esc_html(
				sprintf(
					/* translators: %s: month and year. */
					__( 'Days with open call slots in %s', 'wpcredits-program-manager' ),
					wp_date( 'F Y', $first->getTimestamp(), $zone )
				)
			)
		);

		echo '<thead><tr>';
		for ( $column = 0; $column < 7; $column++ ) {
			$weekday = ( $start_of_week + $column ) % 7;
			$name    = $wp_locale instanceof WP_Locale ? $wp_locale->get_weekday( $weekday ) : '';

			printf(
				'<th scope="col"><abbr title="%1$s">%2$s</abbr></th>',
				esc_attr( $name ),
				esc_html(
					$wp_locale instanceof WP_Locale
						? $wp_locale->get_weekday_initial( $name )
						: substr( $name, 0, 1 )
				)
			);
		}
		echo '</tr></thead><tbody>';

		$days_in_month = (int) $first->format( 't' );
		// How many blank cells before the 1st, given where this site's week starts.
		$lead  = ( (int) $first->format( 'w' ) - $start_of_week + 7 ) % 7;
		$cell  = 0;
		$total = $lead + $days_in_month;
		$weeks = (int) ceil( $total / 7 );

		for ( $week = 0; $week < $weeks; $week++ ) {
			echo '<tr>';

			for ( $column = 0; $column < 7; $column++ ) {
				$number = $cell - $lead + 1;
				++$cell;

				if ( $number < 1 || $number > $days_in_month ) {
					echo '<td class="wpcpm-calendar__pad"></td>';
					continue;
				}

				$date  = sprintf( '%1$s-%2$02d', $month, $number );
				$count = isset( $by_day[ $date ] ) ? count( $by_day[ $date ] ) : 0;

				$classes = array( 'wpcpm-calendar__cell' );

				if ( $count > 0 ) {
					$classes[] = 'is-open';
				}

				if ( $date === $today ) {
					$classes[] = 'is-today';
				}

				printf( '<td class="%s">', esc_attr( implode( ' ', $classes ) ) );

				if ( $count > 0 ) {
					printf(
						'<a class="wpcpm-calendar__day" href="%1$s" aria-label="%2$s"><span class="wpcpm-calendar__number">%3$s</span><span class="wpcpm-calendar__slots">%4$s</span></a>',
						esc_url(
							self::url(
								array(
									self::ARG_MONTH => $month,
									self::ARG_DAY   => $date,
								)
							)
						),
						esc_attr(
							sprintf(
								/* translators: 1: full date, 2: number of slots. */
								_n( '%1$s, %2$s slot open', '%1$s, %2$s slots open', $count, 'wpcredits-program-manager' ),
								wp_date( get_option( 'date_format' ), strtotime( $date . ' 12:00:00' ), $zone ),
								number_format_i18n( $count )
							)
						),
						esc_html( number_format_i18n( $number ) ),
						esc_html( number_format_i18n( $count ) )
					);
				} else {
					printf(
						'<span class="wpcpm-calendar__day is-empty"><span class="wpcpm-calendar__number">%s</span></span>',
						esc_html( number_format_i18n( $number ) )
					);
				}

				echo '</td>';
			}

			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render one day's slots, each a booking form.
	 *
	 * @param array        $by_day     Slots keyed by day.
	 * @param string       $day        Day to list, `Y-m-d`.
	 * @param DateTimeZone $zone       Viewer's timezone.
	 * @param WP_User      $student    Student the booking is for.
	 * @param bool         $on_behalf  Whether a manager is booking for somebody else.
	 */
	private static function render_day( array $by_day, $day, DateTimeZone $zone, WP_User $student, $on_behalf ) {
		echo '<div class="wpcpm-slots">';

		if ( '' === $day || empty( $by_day[ $day ] ) ) {
			printf(
				'<p class="wpcpm-calls__empty">%s</p>',
				esc_html__( 'Nothing open in this month. Try a later one.', 'wpcredits-program-manager' )
			);
			echo '</div>';

			return;
		}

		printf(
			'<h5 class="wpcpm-slots__title">%s</h5>',
			esc_html( wp_date( 'l, ' . get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ), $zone ) )
		);

		printf(
			'<p class="wpcpm-slots__zone">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: timezone name. */
					__( 'Times in %s.', 'wpcredits-program-manager' ),
					WPCPM_Mentor_Availability::zone_label( $zone->getName() )
				)
			)
		);

		$time_format = get_option( 'time_format' );

		printf(
			'<form class="wpcpm-slots__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-status="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Booking…', 'wpcredits-program-manager' ),
			esc_attr__( 'Booking your call — one moment.', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Mentor_Calls::ACTION_BOOK );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Mentor_Calls::ACTION_BOOK ) . '" />';

		if ( $on_behalf ) {
			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
		}

		printf(
			'<label class="wpcpm-slots__label" for="wpcpm-call-topic">%s</label>',
			esc_html__( 'What would you like to discuss? (optional)', 'wpcredits-program-manager' )
		);
		printf(
			'<textarea class="wpcpm-slots__topic" id="wpcpm-call-topic" name="topic" rows="2" maxlength="%1$d" placeholder="%2$s"></textarea>',
			(int) WPCPM_Mentor_Calls::MAX_TOPIC,
			esc_attr__( 'Anything you would like your mentor to prepare.', 'wpcredits-program-manager' )
		);

		echo '<ul class="wpcpm-slots__list">';

		foreach ( $by_day[ $day ] as $slot ) {
			echo '<li class="wpcpm-slots__item">';
			printf(
				'<button type="submit" class="wpcpm-slots__button" name="start" value="%1$d"><span class="wpcpm-slots__time">%2$s</span> <span class="wpcpm-slots__until">%3$s</span></button>',
				(int) $slot['start'],
				esc_html( wp_date( $time_format, $slot['start'], $zone ) ),
				esc_html(
					sprintf(
						/* translators: %s: end time of the call. */
						__( 'to %s', 'wpcredits-program-manager' ),
						wp_date( $time_format, $slot['end'], $zone )
					)
				)
			);
			echo '</li>';
		}

		echo '</ul>';

		// Filled in by the script when a slot is pressed. `aria-live` so the change is
		// announced, and empty in the markup so nothing is claimed before it is true.
		echo '<p class="wpcpm-slots__busy" role="status" aria-live="polite" data-wpcpm-busy-status></p>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the timezone chooser.
	 *
	 * @param int          $user_id Viewer's user ID.
	 * @param DateTimeZone $zone    Current timezone.
	 */
	private static function render_timezone_form( $user_id, DateTimeZone $zone ) {
		printf(
			'<form class="wpcpm-calls__zone" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving…', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Mentor_Calls::ACTION_ZONE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Mentor_Calls::ACTION_ZONE ) . '" />';

		printf(
			'<label for="wpcpm-call-zone">%s</label>',
			esc_html__( 'Show times in', 'wpcredits-program-manager' )
		);

		echo '<select id="wpcpm-call-zone" name="timezone" data-wpcpm-zone>';
		foreach ( timezone_identifiers_list() as $name ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $name ),
				selected( $name, $zone->getName(), false ),
				esc_html( WPCPM_Mentor_Availability::zone_label( $name ) )
			);
		}
		echo '</select> ';

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Use this timezone', 'wpcredits-program-manager' )
		);

		// The nudge is only worth making to somebody who has never chosen: replacing a
		// deliberate choice with the browser's guess would be worse than not helping.
		// Rendered hidden and unhidden by the script, which also pre-selects the guess —
		// so with no JavaScript there is no promise here that nothing kept.
		if ( ! WPCPM_Mentor_Availability::has_timezone( $user_id ) ) {
			printf(
				'<span class="wpcpm-calls__zone-hint" data-wpcpm-zone-hint hidden>%s</span>',
				esc_html__( 'This is your device\'s timezone — save it to show every time on your own clock.', 'wpcredits-program-manager' )
			);
		}

		echo '</form>';
	}

	/*
	 * The mentor's side
	 * --------------------------------------------------------------------
	 */

	/**
	 * Render the mentor's diary and their availability editor.
	 *
	 * @param WP_User $mentor The mentor whose page this is.
	 */
	public static function render_mentor( WP_User $mentor ) {
		wp_enqueue_style( self::STYLE );
		// The availability editor's "copy hours" control is scripted, and this is the only
		// page it appears on. The script's other job — offering the browser's timezone —
		// no-ops here, because the element it looks for is on the student page.
		wp_enqueue_script( self::SCRIPT );

		$zone     = WPCPM_Mentor_Availability::viewer_timezone( $mentor->ID );
		$upcoming = WPCPM_Mentor_Calls::for_mentor( $mentor->ID, true );

		// Deliberately *not* `wpcpm-group`. The theme's dashboard script anchors its
		// search toolbar before the first `.wpcpm-group` and reads student rows out of
		// `.wpcpm-group:not(.wpcpm-group--past) .wpcpm-mentees`; wearing that class
		// would put the toolbar above this section and offer to search a diary.
		echo '<section class="wpcpm-calls wpcpm-calls--mentor" id="' . esc_attr( self::ANCHOR ) . '">';

		// Two columns: the diary, and the hours behind it. They are read as one question —
		// "am I getting calls, and are the hours I published the reason?" — so each half
		// is wrapped for the stylesheet to place. With no CSS they stack, which is the
		// right fallback and what happens below 900px anyway.
		// The diary and the sessions share the left-hand side, in one box. **Not two rows of the
		// section's grid**: a tall availability form beside them spans those rows and has its
		// height divided between them, so the sessions ended up as far down the page as the form
		// is tall, with a column of white above them. One cell holding a stack does not care how
		// tall the other column is.
		echo '<div class="wpcpm-calls__side">';

		echo '<div class="wpcpm-calls__col wpcpm-calls__col--diary">';

		printf(
			'<h3 class="wpcpm-calls__heading">%1$s <span class="wpcpm-calls__count">%2$s</span></h3>',
			esc_html__( 'Upcoming calls', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $upcoming ) ) )
		);

		self::render_message();

		if ( empty( $upcoming ) ) {
			// `wpcpm-calls__empty`, never the shared `wpcpm-dashboard__empty`. The theme's
			// dashboard script anchors its search toolbar on the first
			// `.wpcpm-dashboard__empty` when a mentor has no students and there is no
			// `.wpcpm-group` to anchor on — and this section renders before that, so the
			// shared class here would drop the toolbar inside the diary.
			printf(
				'<p class="wpcpm-calls__empty">%s</p>',
				esc_html(
					WPCPM_Mentor_Availability::is_published( $mentor->ID )
						? __( 'Nothing booked yet. Students pick from the hours you set beside this.', 'wpcredits-program-manager' )
						: __( 'Nothing booked yet — set your availability beside this and your students can start picking times.', 'wpcredits-program-manager' )
				)
			);
		} else {
			self::render_call_list( $upcoming, $zone, 'mentor' );
		}

		echo '</div>';

		// Under the diary, in the same side: a group session is neither a booked call nor an hour
		// they published, but it is the same kind of thing — what is in the calendar — and it
		// reads with them rather than under a form somewhere to the right.
		echo '<div class="wpcpm-calls__col wpcpm-calls__col--sessions">';
		WPCPM_Group_Sessions::render_mentor_panel( $mentor );
		echo '</div>';

		echo '</div>';

		// The two controls that change what is in the calendar, one under the other: the hours
		// students book inside, and the session they can all join.
		echo '<div class="wpcpm-calls__col wpcpm-calls__col--availability">';
		WPCPM_Mentor_Availability::render_editor( $mentor );
		WPCPM_Group_Sessions::render_mentor_planner( $mentor );
		echo '</div>';

		echo '</section>';
	}

	/*
	 * Shared
	 * --------------------------------------------------------------------
	 */

	/**
	 * Render a list of calls with a cancel control on each.
	 *
	 * @param WP_Post[]    $calls Calls, soonest first.
	 * @param DateTimeZone $zone  Viewer's timezone.
	 * @param string       $side  `mentor` or `student`, deciding whose name is shown.
	 */
	private static function render_call_list( array $calls, DateTimeZone $zone, $side ) {
		echo '<ul class="wpcpm-calls__list">';

		foreach ( $calls as $call ) {
			$facts = WPCPM_Mentor_Calls::details( $call );

			echo '<li class="wpcpm-call">';

			echo '<div class="wpcpm-call__when">';
			printf(
				'<time class="wpcpm-call__time" datetime="%1$s">%2$s</time>',
				esc_attr( gmdate( 'c', $facts['start'] ) ),
				esc_html( WPCPM_Mentor_Calls::format_range( $facts['start'], $facts['end'], $zone ) )
			);
			printf(
				'<span class="wpcpm-call__relative">%s</span>',
				esc_html( WPCPM_Mentor_Calls::relative( $facts['start'] ) )
			);
			echo '</div>';

			if ( 'mentor' === $side ) {
				$name = '' !== $facts['name'] ? $facts['name'] : __( 'Unnamed student', 'wpcredits-program-manager' );

				printf( '<p class="wpcpm-call__who">%s</p>', esc_html( $name ) );

				// The student booked on their own clock; a mentor reading "09:00" wants to
				// know that was 13:45 where the student is, or the call gets rescheduled
				// over a misunderstanding.
				if ( '' !== $facts['zone'] && $facts['zone'] !== $zone->getName() ) {
					printf(
						'<p class="wpcpm-call__their-zone">%s</p>',
						esc_html(
							sprintf(
								/* translators: 1: time on the student's clock, 2: timezone name. */
								__( 'For them: %1$s (%2$s)', 'wpcredits-program-manager' ),
								wp_date( get_option( 'time_format' ), $facts['start'], WPCPM_Mentor_Availability::timezone( $facts['zone'] ) ),
								WPCPM_Mentor_Availability::zone_label( $facts['zone'] )
							)
						)
					);
				}
			} else {
				$mentor = get_user_by( 'id', $facts['mentor_id'] );

				if ( $mentor instanceof WP_User ) {
					printf(
						'<p class="wpcpm-call__who">%s</p>',
						esc_html(
							sprintf(
								/* translators: %s: mentor's name. */
								__( 'With %s', 'wpcredits-program-manager' ),
								$mentor->display_name
							)
						)
					);
				}
			}

			if ( '' !== $facts['topic'] ) {
				printf(
					'<p class="wpcpm-call__topic">%s</p>',
					esc_html( $facts['topic'] )
				);
			}

			if ( WPCPM_Mentor_Calls::user_can_cancel( $call ) ) {
				printf(
					'<form class="wpcpm-call__cancel" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
					esc_url( admin_url( 'admin-post.php' ) ),
					esc_attr__( 'Canceling…', 'wpcredits-program-manager' )
				);
				wp_nonce_field( WPCPM_Mentor_Calls::ACTION_CANCEL . '_' . $call->ID );
				echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Mentor_Calls::ACTION_CANCEL ) . '" />';
				printf( '<input type="hidden" name="call" value="%d" />', (int) $call->ID );
				printf(
					'<button type="submit" class="wpcpm-call__cancel-button" onclick="return confirm(%1$s)">%2$s</button>',
					esc_attr( wp_json_encode( __( 'Cancel this call? The slot goes back on the calendar.', 'wpcredits-program-manager' ) ) ),
					esc_html__( 'Cancel', 'wpcredits-program-manager' )
				);
				echo '</form>';
			}

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Render the outcome of a booking or cancellation, if there was one.
	 */
	private static function render_message() {
		$message = WPCPM_Mentor_Calls::message( WPCPM_Mentor_Calls::status() );

		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-notes__message is-%1$s" role="status">%2$s</p>',
			esc_attr( $message[0] ),
			esc_html( $message[1] )
		);
	}
}
