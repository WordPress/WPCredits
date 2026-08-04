<?php
/**
 * Mentors module — booked mentor calls.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One booked call between a mentor and one of their students.
 *
 * Stored as a private post type for the reasons notes are: the syncs rewrite the
 * `wpcpm_mentees` meta wholesale on every run, so anything kept in there is destroyed
 * the next time anything syncs, and a diary is a set of rows to be ordered and
 * queried rather than one array two browser tabs can clobber.
 *
 * The start time lives in post meta as a UTC timestamp rather than in `post_date`.
 * `post_date` is when the booking was *made* — worth keeping distinct, because "you
 * booked this three weeks ago" and "this call is on Thursday" are different facts, and
 * conflating them would make `get_post_time()` lie about one of them.
 *
 * Canceling trashes the post rather than deleting it. A trashed booking drops out of
 * every `publish` query, so it frees its slot immediately, but it is still there if
 * anyone has to work out what happened to a call somebody swears they booked.
 */
class WPCPM_Mentor_Calls {

	const POST_TYPE = 'wpcpm_mentor_call';

	/** Post meta: UTC timestamp the call starts. */
	const META_START = '_wpcpm_call_start';

	/** Post meta: UTC timestamp the call ends. */
	const META_END = '_wpcpm_call_end';

	/** Post meta: the mentor's WP user ID. */
	const META_MENTOR = '_wpcpm_call_mentor';

	/** Post meta: the student's WP user ID. */
	const META_STUDENT = '_wpcpm_call_student';

	/** Post meta: the student's Airtable record ID — the durable identity. */
	const META_RECORD = '_wpcpm_call_student_record';

	/** Post meta: the student's name when they booked, for listings. */
	const META_NAME = '_wpcpm_call_student_name';

	/** Post meta: the timezone the student was looking at when they booked. */
	const META_ZONE = '_wpcpm_call_zone';

	/** Post meta: who canceled, if anyone. */
	const META_CANCELLED_BY = '_wpcpm_call_cancelled_by';

	/** Marks that the reminder for a call has already gone out. */
	const META_REMINDED = '_wpcpm_call_reminded';

	/**
	 * How many students a call has room for.
	 *
	 * Absent or 1 means a one-to-one call, which is every call that existed before group
	 * sessions — so nothing has to be migrated and an unmarked call keeps behaving exactly
	 * as it did.
	 */
	const META_CAPACITY = '_wpcpm_call_capacity';

	/** Cron hook that sweeps for calls needing a reminder. */
	const CRON_REMINDERS = 'wpcpm_send_call_reminders';

	/** How long before a call the reminder goes out, in hours. */
	const REMINDER_LEAD = 24;

	const ACTION_BOOK   = 'wpcpm_book_call';
	const ACTION_CANCEL = 'wpcpm_cancel_call';
	const ACTION_ZONE   = 'wpcpm_set_timezone';

	/** Longest "what would you like to discuss" accepted. */
	const MAX_TOPIC = 1000;

	/** Seconds before a held booking lock is treated as abandoned. */
	const LOCK_TIMEOUT = 30;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_BOOK, array( __CLASS__, 'handle_book' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_ZONE, array( __CLASS__, 'handle_timezone' ) );
		add_action( self::CRON_REMINDERS, array( __CLASS__, 'send_reminders' ) );
	}

	/**
	 * Start the reminder sweep. Called on activation.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_REMINDERS ) ) {
			// Hourly, not daily: the sweep only has to run often enough that a call is caught
			// while it is still inside the reminder window, and a daily run would miss any
			// call whose window opened and closed between two of them.
			wp_schedule_event( time() + MINUTE_IN_SECONDS * 5, 'hourly', self::CRON_REMINDERS );
		}
	}

	/**
	 * Stop the reminder sweep. Called on deactivation.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_REMINDERS );
	}

	/**
	 * Register the call post type.
	 *
	 * Invisible everywhere, like notes: these are private appointments between named
	 * people, and the only routes to them are the two dashboards, each of which checks
	 * the reader against the assignment first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Mentor calls', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Mentor call', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'editor', 'author' ),
				'capability_type'     => array( 'wpcpm_mentor_call', 'wpcpm_mentor_calls' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/*
	 * Who is whose
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Airtable student record a WP account belongs to.
	 *
	 * @param int $user_id User ID.
	 * @return string Record ID, or an empty string.
	 */
	public static function student_record( $user_id ) {
		$record = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_RECORD_ID, true );
		$record = is_string( $record ) ? trim( $record ) : '';

		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}

	/**
	 * The mentor a student books with.
	 *
	 * Two routes, because the two sides of the link are written by two different syncs
	 * and either can be the stale one. The student's own mentor card is authoritative
	 * when it resolves — it is what the student is shown as "your mentor" — and the
	 * mentor's mentee list is the fallback, which covers a student whose card has not
	 * been rebuilt since their mentor's account was created.
	 *
	 * @param int $student_id Student user ID.
	 * @return WP_User|null
	 */
	public static function mentor_for_student( $student_id ) {
		$student_id = (int) $student_id;

		// Resolved more than once per request — the calendar asks, and so does every
		// check that takes a mentor argument it might not have been given.
		static $resolved = array();

		if ( isset( $resolved[ $student_id ] ) ) {
			return $resolved[ $student_id ] ? get_user_by( 'id', $resolved[ $student_id ] ) : null;
		}

		$card   = WPCPM_Students_Sync::get_mentor( $student_id );
		$record = isset( $card['record_id'] ) ? trim( (string) $card['record_id'] ) : '';

		if ( '' !== $record ) {
			// `'ID'` as a string, not `array( 'ID' )` and not `'all'`. Both of those make
			// `WP_User_Query` hand core's `cache_users()` whole rows, and on this site something
			// in the stack leaves them as raw `stdClass` — so `update_meta_cache()` tries to
			// `intval()` an object and raises a warning on every student card render. Asking for
			// a flat list of IDs and hydrating one of them here avoids the path entirely.
			$found = get_users(
				array(
					'meta_key'   => WPCPM_Mentors_Sync::META_RECORD_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_key -- Indexed lookup of one account.
					'meta_value' => $record, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_value -- As above.
					'number'     => 1,
					'fields'     => 'ID',
				)
			);

			$found = ! empty( $found[0] ) ? array( get_user_by( 'id', WPCPM_Roles::id_of( $found[0] ) ) ) : array();

			if ( ! empty( $found[0] ) && $found[0] instanceof WP_User ) {
				$resolved[ $student_id ] = (int) $found[0]->ID;

				return $found[0];
			}
		}

		$mentor = self::scan_for_mentor( $student_id );

		$resolved[ $student_id ] = $mentor instanceof WP_User ? (int) $mentor->ID : 0;

		return $mentor;
	}

	/**
	 * Find a student's mentor by reading every mentor's mentee list.
	 *
	 * The fallback, and an expensive one: mentee lists are serialized user meta, so
	 * there is no way to query into them and the only route is to read all of them. That
	 * is fine as a rescue for a stale student card and unacceptable on every page load,
	 * which is what it would be otherwise — a student whose mentor genuinely cannot be
	 * resolved would pay for the full scan every time they opened their dashboard. So
	 * the answer is cached, *including* the answer "nobody", which is the case that
	 * would otherwise never stop scanning.
	 *
	 * @param int $student_id Student user ID.
	 * @return WP_User|null
	 */
	private static function scan_for_mentor( $student_id ) {
		$record = self::student_record( $student_id );

		if ( '' === $record ) {
			return null;
		}

		$key    = 'wpcpm_call_mentor_' . $record;
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			// Zero is a real answer here, cached so the scan does not repeat.
			$user = (int) $cached ? get_user_by( 'id', (int) $cached ) : null;

			return $user instanceof WP_User ? $user : null;
		}

		$found = null;

		foreach ( WPCPM_Mentors_Dashboard::all_mentors() as $mentor ) {
			foreach ( WPCPM_Mentors_Dashboard::get_mentees( $mentor->ID ) as $mentee ) {
				if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === $record ) {
					$found = $mentor;
					break 2;
				}
			}
		}

		set_transient( $key, $found instanceof WP_User ? (int) $found->ID : 0, HOUR_IN_SECONDS );

		return $found;
	}

	/**
	 * The mentor's stored row for a student, if they have one.
	 *
	 * @param int    $mentor_id Mentor user ID.
	 * @param string $record    Student's Airtable record ID.
	 * @return array|null
	 */
	public static function mentee_row( $mentor_id, $record ) {
		foreach ( WPCPM_Mentors_Dashboard::get_mentees( (int) $mentor_id ) as $mentee ) {
			if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === (string) $record ) {
				return $mentee;
			}
		}

		return null;
	}

	/**
	 * Why a student cannot book, or an empty string if they can.
	 *
	 * Returns the reason rather than a boolean because every one of these is worth
	 * telling the student — "no slots" and "you already have a call" and "mentoring has
	 * finished" all look like a broken calendar otherwise.
	 *
	 * @param int          $student_id Student user ID.
	 * @param WP_User|null $mentor     Their mentor, if already resolved.
	 * @return string Empty string when booking is allowed.
	 */
	public static function why_not_bookable( $student_id, $mentor = null ) {
		$mentor = $mentor instanceof WP_User ? $mentor : self::mentor_for_student( $student_id );

		if ( ! $mentor instanceof WP_User ) {
			return __( 'You do not have a mentor account linked yet, so there is nobody to book a call with.', 'wpcredits-program-manager' );
		}

		$record = self::student_record( $student_id );
		$row    = '' !== $record ? self::mentee_row( $mentor->ID, $record ) : null;

		if ( null !== $row && ! empty( $row['is_past'] ) ) {
			return __( 'Your time on the program has finished, so calls can no longer be booked.', 'wpcredits-program-manager' );
		}

		if ( ! WPCPM_Mentor_Availability::is_published( $mentor->ID ) ) {
			return __( 'Your mentor has not set their availability yet. This calendar fills in as soon as they do.', 'wpcredits-program-manager' );
		}

		$schedule = WPCPM_Mentor_Availability::get( $mentor->ID );
		$upcoming = self::for_student( $student_id, true );

		if ( count( $upcoming ) >= $schedule['per_student'] ) {
			return _n(
				'You already have a call booked. Cancel it if you need a different time.',
				'You have booked as many calls as your mentor takes at once. Cancel one if you need a different time.',
				(int) $schedule['per_student'],
				'wpcredits-program-manager'
			);
		}

		return '';
	}

	/**
	 * Whether a user may cancel a booking.
	 *
	 * The student who booked it, the mentor it is with, and program managers. A mentor
	 * cannot cancel another mentor's call.
	 *
	 * @param WP_Post          $call Call post.
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function user_can_cancel( WP_Post $call, $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) ) {
			return true;
		}

		if ( (int) get_post_meta( $call->ID, self::META_MENTOR, true ) === (int) $user->ID ) {
			return true;
		}

		return (int) get_post_meta( $call->ID, self::META_STUDENT, true ) === (int) $user->ID;
	}

	/*
	 * Queries
	 * --------------------------------------------------------------------
	 */

	/**
	 * Start timestamps already booked with a mentor in a window.
	 *
	 * Keyed by timestamp so the caller can test membership rather than search — slot
	 * generation asks this several hundred times per calendar.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @param int $from      UTC timestamp, inclusive.
	 * @param int $to        UTC timestamp, inclusive.
	 * @return array<int, true>
	 */
	public static function taken_starts( $mentor_id, $from, $to ) {
		$calls = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 500,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- Bounded to one mentor and one window.
					'relation' => 'AND',
					array(
						'key'   => self::META_MENTOR,
						'value' => (int) $mentor_id,
						'type'  => 'NUMERIC',
					),
					array(
						'key'     => self::META_START,
						'value'   => array( (int) $from, (int) $to ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$taken = array();

		foreach ( $calls as $call_id ) {
			$taken[ (int) get_post_meta( $call_id, self::META_START, true ) ] = true;
		}

		return $taken;
	}

	/**
	 * A mentor's calls, soonest first.
	 *
	 * @param int  $mentor_id Mentor user ID.
	 * @param bool $upcoming  Only calls that have not started.
	 * @return WP_Post[]
	 */
	public static function for_mentor( $mentor_id, $upcoming = true ) {
		return self::query(
			array(
				array(
					'key'   => self::META_MENTOR,
					'value' => (int) $mentor_id,
					'type'  => 'NUMERIC',
				),
			),
			$upcoming
		);
	}

	/**
	 * A student's calls, soonest first.
	 *
	 * @param int  $student_id Student user ID.
	 * @param bool $upcoming   Only calls that have not started.
	 * @return WP_Post[]
	 */
	public static function for_student( $student_id, $upcoming = true ) {
		return self::query(
			array(
				array(
					'key'   => self::META_STUDENT,
					'value' => (int) $student_id,
					'type'  => 'NUMERIC',
				),
			),
			$upcoming
		);
	}

	/**
	 * Calls about one Airtable student record, whatever account booked them.
	 *
	 * @param string $record   Airtable record ID.
	 * @param bool   $upcoming Only calls that have not started.
	 * @return WP_Post[]
	 */
	public static function for_record( $record, $upcoming = true ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		return self::query(
			array(
				array(
					'key'   => self::META_RECORD,
					'value' => (string) $record,
				),
			),
			$upcoming
		);
	}

	/**
	 * Run a call query ordered by start time.
	 *
	 * @param array $clauses  Meta query clauses.
	 * @param bool  $upcoming Only calls that have not started.
	 * @return WP_Post[]
	 */
	private static function query( array $clauses, $upcoming ) {
		if ( $upcoming ) {
			$clauses[] = array(
				'key'     => self::META_START,
				// The call is still "upcoming" while it is happening; a mentor looking at
				// the page during a call should see it, not an empty diary.
				'value'   => time() - HOUR_IN_SECONDS,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		$clauses['relation'] = 'AND';

		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 200,
				'orderby'          => 'meta_value_num',
				'meta_key'         => self::META_START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_key -- Needed to order by start time.
				'order'            => $upcoming ? 'ASC' : 'DESC',
				'suppress_filters' => false,
				'meta_query'       => $clauses, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- Bounded to one person.
			)
		);
	}

	/**
	 * The facts about a call, resolved for display.
	 *
	 * @param WP_Post $call Call post.
	 * @return array
	 */
	public static function details( WP_Post $call ) {
		$attendees = self::attendees( $call->ID );

		return array(
			'id'         => (int) $call->ID,
			'start'      => (int) get_post_meta( $call->ID, self::META_START, true ),
			'end'        => (int) get_post_meta( $call->ID, self::META_END, true ),
			'mentor_id'  => (int) get_post_meta( $call->ID, self::META_MENTOR, true ),
			// The first attendee, which for a one-to-one call is the only one. Kept so every
			// caller written before group sessions reads what it always read.
			'student_id' => (int) get_post_meta( $call->ID, self::META_STUDENT, true ),
			'record'     => (string) get_post_meta( $call->ID, self::META_RECORD, true ),
			'name'       => (string) get_post_meta( $call->ID, self::META_NAME, true ),
			'zone'       => (string) get_post_meta( $call->ID, self::META_ZONE, true ),
			'topic'      => (string) $call->post_content,
			'booked'     => get_post_time( 'U', true, $call ),
			'capacity'   => self::capacity( $call->ID ),
			'attendees'  => $attendees,
			'is_group'   => self::capacity( $call->ID ) > 1,
			'places'     => max( 0, self::capacity( $call->ID ) - count( $attendees ) ),
		);
	}

	/*
	 * Attendees
	 * --------------------------------------------------------------------
	 *
	 * **A call's attendees are repeated `META_STUDENT` rows, not one serialized list.** That is
	 * the whole reason group sessions needed no new queries: `for_student()` and `for_record()`
	 * match a meta *value*, so a student finds a session they joined with the same query that
	 * finds their own one-to-one calls, and `taken_starts()` — which is what stops a private
	 * call being booked over a group session — needs no change either.
	 *
	 * `get_post_meta( …, true )` returns the first row, so a one-to-one call still reads as it
	 * always did.
	 */

	/**
	 * How many students this call has room for.
	 *
	 * @param int $call_id Call post ID.
	 * @return int One or more.
	 */
	public static function capacity( $call_id ) {
		$capacity = (int) get_post_meta( (int) $call_id, self::META_CAPACITY, true );

		return $capacity > 1 ? $capacity : 1;
	}

	/**
	 * The users attending, in the order they joined.
	 *
	 * @param int $call_id Call post ID.
	 * @return int[] User IDs.
	 */
	public static function attendees( $call_id ) {
		$ids = get_post_meta( (int) $call_id, self::META_STUDENT, false );
		$out = array();

		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$id = (int) $id;

			if ( $id > 0 && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}

	/**
	 * The Airtable records of the users attending.
	 *
	 * Read separately rather than paired with the user IDs: a note written after a group session
	 * is keyed to records, and this is the list it needs.
	 *
	 * @param int $call_id Call post ID.
	 * @return string[] Record IDs.
	 */
	public static function attendee_records( $call_id ) {
		$records = get_post_meta( (int) $call_id, self::META_RECORD, false );
		$out     = array();

		foreach ( is_array( $records ) ? $records : array() as $record ) {
			$record = trim( (string) $record );

			if ( '' !== $record && ! in_array( $record, $out, true ) ) {
				$out[] = $record;
			}
		}

		return $out;
	}

	/**
	 * Whether a call still has room.
	 *
	 * @param int $call_id Call post ID.
	 * @return bool
	 */
	public static function has_room( $call_id ) {
		return count( self::attendees( $call_id ) ) < self::capacity( $call_id );
	}

	/**
	 * Add somebody to a call.
	 *
	 * `add_post_meta()`, not `update_post_meta()`: the second would replace the row and the
	 * session would hold one attendee however many joined.
	 *
	 * @param int    $call_id    Call post ID.
	 * @param int    $student_id Student user ID.
	 * @param string $record     Their Airtable record.
	 * @return bool Whether they were added.
	 */
	public static function add_attendee( $call_id, $student_id, $record ) {
		$call_id    = (int) $call_id;
		$student_id = (int) $student_id;

		if ( $student_id <= 0 || in_array( $student_id, self::attendees( $call_id ), true ) ) {
			return false;
		}

		add_post_meta( $call_id, self::META_STUDENT, $student_id );

		if ( '' !== trim( (string) $record ) ) {
			add_post_meta( $call_id, self::META_RECORD, trim( (string) $record ) );
		}

		return true;
	}

	/**
	 * Take somebody off a call.
	 *
	 * @param int    $call_id    Call post ID.
	 * @param int    $student_id Student user ID.
	 * @param string $record     Their Airtable record.
	 */
	public static function remove_attendee( $call_id, $student_id, $record ) {
		delete_post_meta( (int) $call_id, self::META_STUDENT, (int) $student_id );

		if ( '' !== trim( (string) $record ) ) {
			delete_post_meta( (int) $call_id, self::META_RECORD, trim( (string) $record ) );
		}
	}

	/*
	 * Booking
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take the booking lock for a mentor.
	 *
	 * `add_option()` is the test-and-set: it returns false when the row already exists,
	 * and it is one INSERT, so two requests racing for the same slot cannot both win.
	 * The same trick the syncs use for their run locks.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @return bool Whether the lock was taken.
	 */
	private static function lock( $mentor_id ) {
		$key  = 'wpcpm_call_lock_' . (int) $mentor_id;
		$held = get_option( $key );

		// A request that died between taking the lock and releasing it would otherwise
		// close the mentor's calendar permanently.
		if ( false !== $held && ( time() - (int) $held ) > self::LOCK_TIMEOUT ) {
			delete_option( $key );
		}

		return (bool) add_option( $key, time(), '', false );
	}

	/**
	 * Release the booking lock.
	 *
	 * @param int $mentor_id Mentor user ID.
	 */
	private static function unlock( $mentor_id ) {
		delete_option( 'wpcpm_call_lock_' . (int) $mentor_id );
	}

	/**
	 * Book a slot.
	 */
	public static function handle_book() {
		check_admin_referer( self::ACTION_BOOK );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in to book a call.', 'wpcredits-program-manager' ), 403 );
		}

		$student_id = get_current_user_id();

		// A program manager booking on a student's behalf is a real need — a student
		// who cannot get into their account still has to be seen.
		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) && isset( $_POST['student'] ) ) {
			$requested = absint( wp_unslash( $_POST['student'] ) );

			if ( $requested ) {
				$student_id = $requested;
			}
		}

		$start  = isset( $_POST['start'] ) ? absint( wp_unslash( $_POST['start'] ) ) : 0;
		$mentor = self::mentor_for_student( $student_id );

		if ( ! $mentor instanceof WP_User ) {
			self::bounce( 'no-mentor' );
		}

		$reason = self::why_not_bookable( $student_id, $mentor );

		if ( '' !== $reason ) {
			self::bounce( 'blocked' );
		}

		if ( ! self::lock( $mentor->ID ) ) {
			// Somebody else is mid-booking with this mentor. Sending them back to try
			// again is honest; queueing behind a lock in a page request is not.
			self::bounce( 'busy' );
		}

		$slot = WPCPM_Mentor_Availability::find_slot( $mentor->ID, $start );

		if ( null === $slot ) {
			self::unlock( $mentor->ID );
			self::bounce( 'taken' );
		}

		// The per-student limit again, now that nothing else can be booking with this
		// mentor. Checked above too, but that read happened before the lock: two requests
		// from the same student could both see zero upcoming calls, and each would then
		// take the lock in turn and book a different slot, leaving them holding two
		// against a limit of one. The slot re-check above does not catch it, because the
		// two are not competing for the same slot.
		if ( '' !== self::why_not_bookable( $student_id, $mentor ) ) {
			self::unlock( $mentor->ID );
			self::bounce( 'blocked' );
		}

		$topic = isset( $_POST['topic'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic'] ) ) : '';
		$topic = trim( mb_substr( $topic, 0, self::MAX_TOPIC ) );

		$student = get_user_by( 'id', $student_id );
		$record  = self::student_record( $student_id );
		$row     = '' !== $record ? self::mentee_row( $mentor->ID, $record ) : null;
		$name    = ( null !== $row && ! empty( $row['name'] ) )
			? (string) $row['name']
			: ( $student instanceof WP_User ? $student->display_name : '' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_content' => $topic,
				'post_title'   => sprintf(
					/* translators: 1: student name, 2: call date and time. */
					__( 'Call with %1$s — %2$s', 'wpcredits-program-manager' ),
					'' !== $name ? $name : $record,
					wp_date( 'Y-m-d H:i', $slot['start'] )
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::unlock( $mentor->ID );
			self::bounce( 'error' );
		}

		update_post_meta( $post_id, self::META_START, (int) $slot['start'] );
		update_post_meta( $post_id, self::META_END, (int) $slot['end'] );
		update_post_meta( $post_id, self::META_MENTOR, (int) $mentor->ID );
		update_post_meta( $post_id, self::META_STUDENT, (int) $student_id );
		update_post_meta( $post_id, self::META_RECORD, $record );
		update_post_meta( $post_id, self::META_NAME, $name );
		update_post_meta( $post_id, self::META_ZONE, WPCPM_Mentor_Availability::viewer_timezone( $student_id )->getName() );

		// Belt and braces over the lock. The lock reads through the object cache, which
		// on a persistent-cache site is not guaranteed to show another request's write
		// the instant it lands; this reads the table for real. If two bookings did land
		// on one slot, the later one loses and its owner is told to pick again.
		$clash = self::taken_starts( $mentor->ID, (int) $slot['start'], (int) $slot['start'] );

		if ( count( $clash ) > 1 ) {
			$winner = self::first_booking_at( $mentor->ID, (int) $slot['start'] );

			if ( $winner && (int) $winner !== (int) $post_id ) {
				wp_delete_post( $post_id, true );
				self::unlock( $mentor->ID );
				self::bounce( 'taken' );
			}
		}

		self::unlock( $mentor->ID );

		self::notify_booked( $post_id, $mentor, $student );

		self::bounce( 'booked' );
	}

	/**
	 * The oldest booking on an exact slot, which is the one that keeps it.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @param int $start     UTC timestamp.
	 * @return int Post ID, or 0.
	 */
	private static function first_booking_at( $mentor_id, $start ) {
		$calls = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 5,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- One slot.
					'relation' => 'AND',
					array(
						'key'   => self::META_MENTOR,
						'value' => (int) $mentor_id,
						'type'  => 'NUMERIC',
					),
					array(
						'key'   => self::META_START,
						'value' => (int) $start,
						'type'  => 'NUMERIC',
					),
				),
			)
		);

		return ! empty( $calls[0] ) ? (int) $calls[0] : 0;
	}

	/**
	 * Cancel a booking.
	 */
	public static function handle_cancel() {
		$call_id = isset( $_POST['call'] ) ? absint( wp_unslash( $_POST['call'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.

		check_admin_referer( self::ACTION_CANCEL . '_' . $call_id );

		$call = $call_id ? get_post( $call_id ) : null;

		if ( ! $call instanceof WP_Post || self::POST_TYPE !== $call->post_type ) {
			wp_die( esc_html__( 'That call does not exist.', 'wpcredits-program-manager' ), 404 );
		}

		if ( ! self::user_can_cancel( $call ) ) {
			wp_die( esc_html__( 'You cannot cancel that call.', 'wpcredits-program-manager' ), 403 );
		}

		update_post_meta( $call->ID, self::META_CANCELLED_BY, get_current_user_id() );

		// Trashed, not deleted: it leaves every `publish` query at once, so the slot is
		// free again immediately, but the record of it survives.
		wp_trash_post( $call->ID );

		self::notify_cancelled( $call );

		self::bounce( 'cancelled' );
	}

	/**
	 * Store the viewer's display timezone.
	 */
	public static function handle_timezone() {
		check_admin_referer( self::ACTION_ZONE );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in first.', 'wpcredits-program-manager' ), 403 );
		}

		$zone = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : '';

		WPCPM_Mentor_Availability::set_timezone( get_current_user_id(), $zone );

		self::bounce( 'zone' );
	}

	/**
	 * Back to whichever dashboard the viewer belongs on.
	 *
	 * Rebuilt here rather than taken from the request, so a form cannot be used to
	 * bounce anyone somewhere else.
	 *
	 * @param string $status Outcome flag.
	 */
	private static function bounce( $status ) {
		$student_page = WPCPM_Students_Dashboard::page_url();
		$mentor_page  = WPCPM_Mentors_Dashboard::page_url();

		// Where they came from is the only reliable answer to where they want to be. One
		// person can be a student, a mentor and a manager at once, so any rule based on
		// who they are gets somebody wrong — a manager booking on a student's behalf was
		// being returned to the mentor dashboard.
		//
		// The referer is only ever *matched against* the two pages already known here,
		// never redirected to, so a forged one can choose between two of our own pages
		// and nothing else.
		$referer = wp_get_referer();
		$page    = '';

		if ( $referer ) {
			$path = wp_parse_url( $referer, PHP_URL_PATH );

			foreach ( array( $student_page, $mentor_page ) as $candidate ) {
				if ( '' !== $candidate && $path && wp_parse_url( $candidate, PHP_URL_PATH ) === $path ) {
					$page = $candidate;
					break;
				}
			}
		}

		if ( '' === $page ) {
			$page = ( WPCPM_Students_Dashboard::is_student() && ! current_user_can( WPCPM_Roles::CAP_MANAGE ) )
				? $student_page
				: $mentor_page;
		}

		if ( '' === $page ) {
			$page = '' !== $student_page ? $student_page : home_url( '/' );
		}

		// The outcome goes in a flash, not the URL. In the URL it survived every reload —
		// "That call is canceled and the slot is free again" stayed on the page for good.
		WPCPM_Flash::set( 'call', $status );

		$args = array();

		// Keep a manager on whichever person they were inspecting, rather than bouncing
		// them to their own page after every action. Taken from the referer so all three
		// forms get it without each carrying hidden fields.
		//
		// Safe to echo back even though a referer is forgeable: both arguments are
		// re-validated against `WPCPM_Roles::CAP_MANAGE` by the page that reads them, so
		// this cannot grant a view the viewer does not already have — and it is only read
		// for somebody who has that capability in the first place.
		if ( $referer && current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$query = (string) wp_parse_url( $referer, PHP_URL_QUERY );
			$parts = array();
			wp_parse_str( $query, $parts );

			foreach ( array( 'wpcpm_mentor', 'wpcpm_student_view' ) as $keep ) {
				if ( ! empty( $parts[ $keep ] ) ) {
					$args[ $keep ] = absint( $parts[ $keep ] );
				}
			}
		}

		// `WPCPM_Call_Calendar::ANCHOR`, not `self::` — the anchor belongs to the section the
		// calendar renders, and this class has no constant of that name. `self::` here was a
		// fatal on every booking, cancellation and timezone change from 1.13.1 until 1.17.1.
		wp_safe_redirect( add_query_arg( $args, $page ) . '#' . WPCPM_Call_Calendar::ANCHOR );
		exit;
	}

	/**
	 * Take the booking lock, for another module.
	 *
	 * Group sessions compete for the same lock as one-to-one bookings, deliberately: two students
	 * racing for the last place in a session is the same race as two racing for one slot.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @return bool Whether the lock was taken.
	 */
	public static function lock_for( $mentor_id ) {
		return self::lock( $mentor_id );
	}

	/**
	 * Release the booking lock, for another module.
	 *
	 * @param int $mentor_id Mentor user ID.
	 */
	public static function unlock_for( $mentor_id ) {
		self::unlock( $mentor_id );
	}

	/**
	 * Redirect with an outcome message, for another module.
	 *
	 * @param string $status Outcome flag.
	 */
	public static function bounce_to( $status ) {
		self::bounce( $status );
	}

	/**
	 * Tell both sides somebody joined a group session.
	 *
	 * The same mail a one-to-one booking sends, and that is right rather than lazy: joining a
	 * session *is* booking a call, the student needs the same calendar invite, and the mentor needs
	 * to know somebody is coming. Each student's invite names only them and the mentor, which also
	 * keeps one student's address off another's calendar entry.
	 *
	 * @param int          $call_id Session post ID.
	 * @param WP_User      $mentor  The mentor.
	 * @param WP_User|null $student Who joined.
	 */
	public static function notify_joined( $call_id, WP_User $mentor, $student ) {
		self::notify_booked( $call_id, $mentor, $student );
	}

	/**
	 * Tell one student their place on a session is released.
	 *
	 * Only them: the session goes ahead for everybody else, so telling the rest that somebody left
	 * would be noise — and telling them *who* left would be worse.
	 *
	 * @param int $call_id    Session post ID.
	 * @param int $student_id Who left.
	 */
	public static function notify_left( $call_id, $student_id ) {
		$call = get_post( (int) $call_id );

		if ( ! $call instanceof WP_Post || ! self::mail_enabled( $call ) ) {
			return;
		}

		$student = get_user_by( 'id', (int) $student_id );

		if ( ! $student instanceof WP_User ) {
			return;
		}

		$facts  = self::details( $call );
		$mentor = get_user_by( 'id', $facts['mentor_id'] );

		WPCPM_Mail::send(
			$student,
			'call-cancelled',
			function ( $recipient ) use ( $facts, $mentor, $student ) {
				// `METHOD:CANCEL` with the same UID as the invitation, so the entry disappears
				// from their calendar rather than sitting there for a session they left.
				$invite = self::calendar( $facts, WPCPM_ICS::METHOD_CANCEL, $mentor, $student, $recipient );

				return array(
					'subject'     => sprintf(
						/* translators: 1: site name, 2: mentor name. */
						__( '[%1$s] You left the group session with %2$s', 'wpcredits-program-manager' ),
						WPCPM_Mail::site_name(),
						$mentor instanceof WP_User ? $mentor->display_name : ''
					),
					'body'        => self::mail_body(
						$facts,
						$recipient,
						$mentor instanceof WP_User ? $mentor->display_name : '',
						false,
						'cancelled'
					),
					'attachments' => $invite,
					'cleanup'     => $invite,
				);
			}
		);
	}

	/**
	 * The message for an outcome flag, or an empty array.
	 *
	 * @param string $status Outcome flag.
	 * @return array{0:string,1:string}|array
	 */
	public static function message( $status ) {
		$messages = array(
			'booked'              => array( 'success', __( 'Your call is booked. It is in the list above, and your mentor can see it too.', 'wpcredits-program-manager' ) ),
			'cancelled'           => array( 'success', __( 'That call is canceled and the slot is free again.', 'wpcredits-program-manager' ) ),
			'zone'                => array( 'success', __( 'Times are now shown in your timezone.', 'wpcredits-program-manager' ) ),
			'taken'               => array( 'error', __( 'Somebody booked that slot first. Please pick another one.', 'wpcredits-program-manager' ) ),
			'busy'                => array( 'error', __( 'Another booking was going through at the same moment. Please try again.', 'wpcredits-program-manager' ) ),
			'blocked'             => array( 'error', __( 'That call could not be booked. The calendar below says why.', 'wpcredits-program-manager' ) ),
			'no-mentor'           => array( 'error', __( 'No mentor is linked to your account yet, so there is nobody to book with.', 'wpcredits-program-manager' ) ),
			'error'               => array( 'error', __( 'That call could not be saved.', 'wpcredits-program-manager' ) ),

			// Group sessions.
			'session-created'     => array( 'success', __( 'Your group session is created. Your students can see it and join.', 'wpcredits-program-manager' ) ),
			'session-joined'      => array( 'success', __( 'You are on the session. It is in your list above, and there is an invitation in your email.', 'wpcredits-program-manager' ) ),
			'session-left'        => array( 'success', __( 'You have left that session, and your place is free for somebody else.', 'wpcredits-program-manager' ) ),
			'session-full'        => array( 'error', __( 'That session filled up while you were reading it.', 'wpcredits-program-manager' ) ),
			'session-gone'        => array( 'error', __( 'That session is no longer open.', 'wpcredits-program-manager' ) ),
			'session-not-yours'   => array( 'error', __( 'That session belongs to a different mentor.', 'wpcredits-program-manager' ) ),
			'session-already'     => array( 'error', __( 'You are already on that session.', 'wpcredits-program-manager' ) ),
			'session-when'        => array( 'error', __( 'A session needs a date and a start time.', 'wpcredits-program-manager' ) ),
			'session-length'      => array( 'error', __( 'That session length is not a number of minutes this can use.', 'wpcredits-program-manager' ) ),
			'session-capacity'    => array( 'error', __( 'A group session holds between 2 and 50 students.', 'wpcredits-program-manager' ) ),
			'session-past'        => array( 'error', __( 'That start time has already passed.', 'wpcredits-program-manager' ) ),
			'session-clash'       => array( 'error', __( 'Something else of yours already starts at that moment.', 'wpcredits-program-manager' ) ),
			'session-noted'       => array( 'success', __( 'Your note is saved, and it is on every card of everybody who was there.', 'wpcredits-program-manager' ) ),
			'session-note-failed' => array( 'error', __( 'That note could not be saved.', 'wpcredits-program-manager' ) ),
		);

		return isset( $messages[ $status ] ) ? $messages[ $status ] : array();
	}

	/**
	 * The outcome flag on the current request, if any.
	 *
	 * @return string
	 */
	public static function status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag.
		return sanitize_key( (string) WPCPM_Flash::take( 'call' ) );
	}

	/*
	 * Mail
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether mail about this call should go out at all.
	 *
	 * @param WP_Post $call Call post.
	 * @return bool
	 */
	private static function mail_enabled( WP_Post $call ) {
		/**
		 * Filter whether call mail is sent.
		 *
		 * Covers bookings, cancellations and reminders alike.
		 *
		 * @param bool    $send Whether to send.
		 * @param WP_Post $call Call post.
		 */
		return (bool) apply_filters( 'wpcpm_send_call_mail', true, $call );
	}

	/**
	 * Tell both people a call was booked.
	 *
	 * Plain mail, deliberately: a call nobody is told about is a call nobody attends, and
	 * neither party is necessarily going to revisit the dashboard.
	 *
	 * @param int          $call_id Call post ID.
	 * @param WP_User      $mentor  Mentor.
	 * @param WP_User|null $student Student.
	 */
	private static function notify_booked( $call_id, WP_User $mentor, $student ) {
		$call = get_post( $call_id );

		if ( ! $call instanceof WP_Post || ! self::mail_enabled( $call ) ) {
			return;
		}

		$facts = self::details( $call );
		$name  = '' !== $facts['name'] ? $facts['name'] : __( 'your student', 'wpcredits-program-manager' );

		WPCPM_Mail::send(
			$mentor,
			'call-booked',
			function ( $recipient ) use ( $facts, $name, $mentor, $student ) {
				$invite = self::calendar( $facts, WPCPM_ICS::METHOD_REQUEST, $mentor, $student, $recipient );

				return array(
					'subject'     => sprintf(
						/* translators: 1: site name, 2: student name. */
						__( '[%1$s] Call booked with %2$s', 'wpcredits-program-manager' ),
						WPCPM_Mail::site_name(),
						$name
					),
					'body'        => self::mail_body( $facts, $recipient, $name, true, 'booked' ),
					'headers'     => WPCPM_Mail::reply_to( $student ),
					'attachments' => $invite,
					'cleanup'     => $invite,
				);
			}
		);

		if ( ! $student instanceof WP_User ) {
			return;
		}

		WPCPM_Mail::send(
			$student,
			'call-booked',
			function ( $recipient ) use ( $facts, $mentor, $student ) {
				$invite = self::calendar( $facts, WPCPM_ICS::METHOD_REQUEST, $mentor, $student, $recipient );

				return array(
					'subject'     => sprintf(
						/* translators: 1: site name, 2: mentor name. */
						__( '[%1$s] Your call with %2$s is booked', 'wpcredits-program-manager' ),
						WPCPM_Mail::site_name(),
						$mentor->display_name
					),
					'body'        => self::mail_body( $facts, $recipient, $mentor->display_name, false, 'booked' ),
					'headers'     => WPCPM_Mail::reply_to( $mentor ),
					'attachments' => $invite,
					'cleanup'     => $invite,
				);
			}
		);
	}

	/**
	 * Everybody a message about this call should go to: the mentor, then every attendee.
	 *
	 * One list, so a cancellation and a reminder can never disagree about who is on a session —
	 * exactly the kind of split that leaves one student turning up to a call the others were told
	 * was off.
	 *
	 * @param array $facts From `details()`.
	 * @return WP_User[]
	 */
	private static function recipients( array $facts ) {
		$people = array();
		$mentor = get_user_by( 'id', $facts['mentor_id'] );

		if ( $mentor instanceof WP_User ) {
			$people[] = $mentor;
		}

		foreach ( $facts['attendees'] as $student_id ) {
			$student = get_user_by( 'id', $student_id );

			if ( $student instanceof WP_User ) {
				$people[] = $student;
			}
		}

		return $people;
	}

	/**
	 * Tell everybody else a call was canceled.
	 *
	 * @param WP_Post $call Call post.
	 */
	private static function notify_cancelled( WP_Post $call ) {
		if ( ! self::mail_enabled( $call ) ) {
			return;
		}

		$facts   = self::details( $call );
		$actor   = get_current_user_id();
		$mentor  = get_user_by( 'id', $facts['mentor_id'] );
		$student = get_user_by( 'id', $facts['student_id'] );

		// Who to name as having canceled. A program manager acting on somebody's behalf is
		// named by their role rather than personally: to a student, an administrator they
		// have never heard of reads as a stranger calling their call off.
		$by = __( 'a program manager', 'wpcredits-program-manager' );

		if ( $actor && ( $actor === (int) $facts['mentor_id'] || $actor === (int) $facts['student_id'] ) ) {
			$by = wp_get_current_user()->display_name;
		}

		foreach ( self::recipients( $facts ) as $person ) {
			// The person who pressed cancel knows; everybody else needs telling.
			if ( (int) $person->ID === $actor ) {
				continue;
			}

			$to_mentor = (int) $person->ID === (int) $facts['mentor_id'];
			$other     = $to_mentor ? $student : $mentor;

			WPCPM_Mail::send(
				$person,
				'call-cancelled',
				function ( $recipient ) use ( $facts, $by, $to_mentor, $mentor, $student, $other ) {
					$zone   = WPCPM_Mentor_Availability::viewer_timezone( $recipient->ID );
					$invite = self::calendar( $facts, WPCPM_ICS::METHOD_CANCEL, $mentor, $student, $recipient );
					$page   = $to_mentor ? WPCPM_Mentors_Dashboard::page_url() : WPCPM_Students_Dashboard::page_url();

					$lines = array(
						sprintf(
							/* translators: 1: date and time of the call, 2: who canceled. */
							__( 'The call on %1$s was canceled by %2$s.', 'wpcredits-program-manager' ),
							self::format_range( $facts['start'], $facts['end'], $zone ),
							$by
						),
						'',
						sprintf(
							/* translators: %s: timezone name. */
							__( 'Times are shown in %s.', 'wpcredits-program-manager' ),
							WPCPM_Mentor_Availability::zone_label( $zone->getName() )
						),
						'',
						// Mentors do not book their own calls, so telling them to book
						// another one is somebody else's instruction.
						$to_mentor
							? __( 'The slot is free again, and your student can book another time whenever suits them.', 'wpcredits-program-manager' )
							: __( 'The slot is free again, so you can book another time.', 'wpcredits-program-manager' ),
					);

					if ( '' !== $page ) {
						$lines[] = '';
						$lines[] = $page;
					}

					return array(
						'subject'     => sprintf(
							/* translators: 1: site name, 2: date of the canceled call. */
							__( '[%1$s] Call on %2$s canceled', 'wpcredits-program-manager' ),
							WPCPM_Mail::site_name(),
							wp_date( get_option( 'date_format' ), (int) $facts['start'], $zone )
						),
						'body'        => implode( "\n", $lines ),
						'headers'     => WPCPM_Mail::reply_to( $other ),
						'attachments' => $invite,
						'cleanup'     => $invite,
					);
				}
			);
		}
	}

	/**
	 * Remind both people about calls that are nearly here.
	 *
	 * Runs hourly. A call booked four weeks out is a call somebody forgets, and the booking
	 * confirmation was read a month ago.
	 */
	public static function send_reminders() {
		/**
		 * Filter how many hours before a call the reminder goes out.
		 *
		 * Zero or less turns reminders off.
		 *
		 * @param int $hours Lead time in hours.
		 */
		$lead = (int) apply_filters( 'wpcpm_call_reminder_lead', self::REMINDER_LEAD );

		if ( $lead <= 0 ) {
			return;
		}

		$now   = time();
		$until = $now + ( $lead * HOUR_IN_SECONDS );

		$calls = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'orderby'          => 'meta_value_num',
				'meta_key'         => self::META_START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_key -- Needed to order by start time.
				'order'            => 'ASC',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- Bounded to one time window.
					'relation' => 'AND',
					array(
						'key'     => self::META_START,
						'value'   => array( $now, $until ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::META_REMINDED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $calls as $call ) {
			// Stamped before sending, not after. A send that dies halfway leaves the call
			// unmarked otherwise, and the next hourly run mails whoever already got one.
			update_post_meta( $call->ID, self::META_REMINDED, time() );

			self::notify_reminder( $call, $lead );
		}
	}

	/**
	 * Send one call's reminder to both parties.
	 *
	 * @param WP_Post $call Call post.
	 * @param int     $lead Reminder lead time in hours.
	 */
	private static function notify_reminder( WP_Post $call, $lead ) {
		if ( ! self::mail_enabled( $call ) ) {
			return;
		}

		$facts = self::details( $call );

		// Booked inside the reminder window: the confirmation *is* the reminder, and a second
		// message minutes after the first says nothing new.
		if ( (int) $facts['booked'] > (int) $facts['start'] - ( $lead * HOUR_IN_SECONDS ) ) {
			return;
		}

		$mentor  = get_user_by( 'id', $facts['mentor_id'] );
		$student = get_user_by( 'id', $facts['student_id'] );
		$name    = '' !== $facts['name'] ? $facts['name'] : __( 'your student', 'wpcredits-program-manager' );

		// A group session has no single other person to name, so the mentor is told how many are
		// coming instead. Naming one of several attendees would be worse than naming none.
		if ( $facts['is_group'] ) {
			$name = sprintf(
				/* translators: %s: number of students attending. */
				_n( '%s student', '%s students', count( $facts['attendees'] ), 'wpcredits-program-manager' ),
				number_format_i18n( count( $facts['attendees'] ) )
			);
		}

		foreach ( self::recipients( $facts ) as $person ) {
			$to_mentor  = (int) $person->ID === (int) $facts['mentor_id'];
			$other      = $to_mentor ? $student : $mentor;
			$other_name = $to_mentor ? $name : ( $mentor instanceof WP_User ? $mentor->display_name : '' );

			WPCPM_Mail::send(
				$person,
				'call-reminder',
				function ( $recipient ) use ( $facts, $other_name, $to_mentor, $other ) {
					return array(
						'subject' => sprintf(
							/* translators: 1: site name, 2: the other person's name. */
							__( '[%1$s] Reminder: your call with %2$s', 'wpcredits-program-manager' ),
							WPCPM_Mail::site_name(),
							$other_name
						),
						'body'    => self::mail_body( $facts, $recipient, $other_name, $to_mentor, 'reminder' ),
						'headers' => WPCPM_Mail::reply_to( $other ),
					);
				}
			);
		}
	}

	/**
	 * The calendar invitation for a call, as a one-element attachment list.
	 *
	 * Returned as a list so a caller can pass it straight to both `attachments` and
	 * `cleanup`, and so a temp directory that could not be written simply means no
	 * attachment rather than a failed send.
	 *
	 * @param array        $facts     Call facts.
	 * @param string       $method    `REQUEST` or `CANCEL`.
	 * @param WP_User|null $mentor    Mentor.
	 * @param WP_User|null $student   Student.
	 * @param WP_User      $recipient Who the mail is for.
	 * @return string[]
	 */
	private static function calendar( array $facts, $method, $mentor, $student, WP_User $recipient ) {
		static $built = array();

		// Called twice per message by design — once for `attachments`, once for `cleanup` —
		// and writing the file twice would leak the first one.
		$memo = $facts['id'] . '|' . $method . '|' . $recipient->ID;

		if ( isset( $built[ $memo ] ) ) {
			return $built[ $memo ];
		}

		$mentor_name  = $mentor instanceof WP_User ? $mentor->display_name : '';
		$student_name = '' !== $facts['name']
			? $facts['name']
			: ( $student instanceof WP_User ? $student->display_name : '' );

		$summary = sprintf(
			/* translators: 1: mentor name, 2: student name. */
			__( 'Mentor call: %1$s and %2$s', 'wpcredits-program-manager' ),
			$mentor_name,
			$student_name
		);

		$where = $mentor instanceof WP_User
			? WPCPM_Mentor_Availability::meeting_place( $mentor->ID )
			: '';

		$description = self::mail_body( $facts, $recipient, $mentor_name, false, 'calendar' );

		$ics  = WPCPM_ICS::build( $facts, $method, $mentor, $student, $summary, $description, $where );
		$path = WPCPM_ICS::tempfile( $ics );

		$built[ $memo ] = '' === $path ? array() : array( $path );

		return $built[ $memo ];
	}

	/**
	 * The body of a booking, reminder or calendar description.
	 *
	 * Built here rather than at each caller so the facts are stated in the same order and
	 * the same clock every time — and, because this runs inside the recipient's locale, in
	 * the same language as everything else they are sent.
	 *
	 * @param array   $facts     Call facts.
	 * @param WP_User $recipient Who is reading it.
	 * @param string  $other     The other person's name.
	 * @param bool    $to_mentor Whether the reader is the mentor.
	 * @param string  $kind      `booked`, `reminder` or `calendar`.
	 * @return string
	 */
	private static function mail_body( array $facts, WP_User $recipient, $other, $to_mentor, $kind = 'booked' ) {
		$zone  = WPCPM_Mentor_Availability::viewer_timezone( $recipient->ID );
		$range = self::format_range( $facts['start'], $facts['end'], $zone );

		$lines = array();

		if ( 'reminder' === $kind ) {
			$lines[] = sprintf(
				/* translators: 1: the other person's name, 2: date and time, 3: "in 4 hours". */
				__( 'Your call with %1$s is %3$s, on %2$s.', 'wpcredits-program-manager' ),
				$other,
				$range,
				self::relative( $facts['start'] )
			);
		} elseif ( 'calendar' === $kind ) {
			// The calendar already shows when it is; repeating the time in the description
			// is noise beside the event's own start and end.
			$lines[] = sprintf(
				/* translators: %s: mentor name. */
				__( 'A mentor call on the WordPress Credits Program with %s.', 'wpcredits-program-manager' ),
				$other
			);
		} elseif ( $to_mentor ) {
			$lines[] = sprintf(
				/* translators: 1: student name, 2: date and time. */
				__( '%1$s booked a call with you on %2$s.', 'wpcredits-program-manager' ),
				$other,
				$range
			);
		} else {
			$lines[] = sprintf(
				/* translators: 1: mentor name, 2: date and time. */
				__( 'Your call with %1$s is booked for %2$s.', 'wpcredits-program-manager' ),
				$other,
				$range
			);
		}

		if ( 'calendar' !== $kind ) {
			$lines[] = '';
			$lines[] = sprintf(
				/* translators: %s: timezone name. */
				__( 'Times are shown in %s.', 'wpcredits-program-manager' ),
				WPCPM_Mentor_Availability::zone_label( $zone->getName() )
			);
		}

		$where = WPCPM_Mentor_Availability::meeting_place( (int) $facts['mentor_id'] );

		if ( '' !== $where ) {
			$lines[] = '';
			$lines[] = __( 'Where you will meet:', 'wpcredits-program-manager' );
			$lines[] = $where;
		} elseif ( $to_mentor && 'booked' === $kind ) {
			// Only the mentor can fix this, and only they should be told about it.
			$lines[] = '';
			$lines[] = __( 'You have not set a meeting link yet, so this confirmation cannot tell your student where to go. You can add one beside your availability on the program site.', 'wpcredits-program-manager' );
		}

		if ( '' !== $facts['topic'] ) {
			$lines[] = '';
			// The student is reading about something they wrote themselves, so they are
			// addressed as themselves rather than described in the third person.
			$lines[] = $to_mentor
				? __( 'What the student would like to discuss:', 'wpcredits-program-manager' )
				: __( 'What you said you would like to discuss:', 'wpcredits-program-manager' );
			$lines[] = $facts['topic'];
		}

		if ( 'calendar' !== $kind ) {
			$page = $to_mentor ? WPCPM_Mentors_Dashboard::page_url() : WPCPM_Students_Dashboard::page_url();

			if ( '' !== $page ) {
				$lines[] = '';
				$lines[] = $page;
			}
		}

		return implode( "\n", $lines );
	}

	/*
	 * Formatting
	 * --------------------------------------------------------------------
	 */

	/**
	 * A call's date and time range, on a given clock.
	 *
	 * @param int          $start UTC timestamp.
	 * @param int          $end   UTC timestamp.
	 * @param DateTimeZone $zone  Timezone to render in.
	 * @return string
	 */
	public static function format_range( $start, $end, DateTimeZone $zone ) {
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$from = wp_date( $date_format . ' ' . $time_format, (int) $start, $zone );

		// The end usually needs no date — it is minutes after the start. But a call late in
		// the evening on one clock is a call after midnight on another, and "11:45 pm –
		// 12:15 am" then reads as ending fourteen hours before it starts. Compared in the
		// *viewer's* zone, because that is the only clock this string is read on.
		$crosses = wp_date( 'Y-m-d', (int) $start, $zone ) !== wp_date( 'Y-m-d', (int) $end, $zone );

		$to = $crosses
			? wp_date( $date_format . ' ' . $time_format, (int) $end, $zone )
			: wp_date( $time_format, (int) $end, $zone );

		return sprintf(
			/* translators: 1: start date and time, 2: end time, with its date only when the call crosses midnight. */
			__( '%1$s – %2$s', 'wpcredits-program-manager' ),
			$from,
			$to
		);
	}

	/**
	 * How long until a call, or how long since it started.
	 *
	 * @param int $start UTC timestamp.
	 * @return string
	 */
	public static function relative( $start ) {
		$start = (int) $start;
		$now   = time();

		if ( $start >= $now ) {
			/* translators: %s: human-readable time difference, e.g. "2 hours". */
			return sprintf( __( 'in %s', 'wpcredits-program-manager' ), human_time_diff( $now, $start ) );
		}

		/* translators: %s: human-readable time difference. */
		return sprintf( __( '%s ago', 'wpcredits-program-manager' ), human_time_diff( $start, $now ) );
	}

	/**
	 * Drop the booking locks and the resolved-mentor cache.
	 *
	 * Neither is durable state, which is exactly why they need sweeping: a lock is
	 * normally released in the same request that took it, so the only ones on disk are
	 * from requests that died holding one, and the mentor cache is keyed per student
	 * record and can run to a row each. Both are named by prefix rather than enumerable,
	 * so this is the `flush_cache()` pattern the profile readers already use.
	 */
	public static function flush_cache() {
		global $wpdb;

		$locks    = $wpdb->esc_like( 'wpcpm_call_lock_' ) . '%';
		$resolved = $wpdb->esc_like( '_transient_wpcpm_call_mentor_' ) . '%';
		$timeout  = $wpdb->esc_like( '_transient_timeout_wpcpm_call_mentor_' ) . '%';

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$locks,
				$resolved,
				$timeout
			)
		);
	}

	/**
	 * Delete every call. Called on uninstall.
	 */
	public static function delete_all() {
		$calls = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $calls as $call_id ) {
			wp_delete_post( $call_id, true );
		}
	}
}
