<?php
/**
 * Group sessions: one call, several students.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A mentor announces a session; their students join it.
 *
 * **An office hour, not a slot.** A one-to-one call is something a student *finds* - they open the
 * calendar, see the hours their mentor published, and take one. A group session is something a
 * mentor *announces*: they pick the time, say what it is about, and say how many people it holds.
 * So this does not read the weekly availability at all, and a mentor can run a session at a time
 * they would never offer for private calls.
 *
 * **It does block that time from private booking**, though, and gets that for free: a session is a
 * `wpcpm_mentor_call` post like any other, so `WPCPM_Mentor_Calls::taken_starts()` - which the slot
 * generator subtracts - already counts it. Reusing the post type rather than inventing a second one
 * is what keeps the diary, the reminder sweep, the cancellation mail and the ICS builder working
 * with no changes at all.
 *
 * **What is different is only the capacity and the attendee list.** `META_CAPACITY` above 1 marks a
 * session; attendees are repeated `META_STUDENT` rows. A call with neither reads exactly as it did
 * before any of this existed, which is why nothing needed migrating.
 *
 * Joining counts against the mentor's per-student limit, because an upcoming session is an upcoming
 * call - a student holding three of them has three calls to prepare for.
 */
class WPCPM_Group_Sessions {

	const ACTION_CREATE = 'wpcpm_create_session';
	const ACTION_JOIN   = 'wpcpm_join_session';
	const ACTION_LEAVE  = 'wpcpm_leave_session';
	const ACTION_NOTE   = 'wpcpm_session_note';
	const ACTION_EDIT   = 'wpcpm_edit_session';

	/**
	 * How many times a session has been changed since it was announced.
	 *
	 * The number rides on the calendar invitation as its `SEQUENCE`. A calendar that already
	 * holds the event ignores anything that does not outrank what it has, so without this an
	 * edited session would reach a student's calendar as a duplicate of the original rather
	 * than as a move, and they would keep the old time.
	 */
	const META_REVISION = '_wpcpm_session_revision';

	/**
	 * Most students one session may hold.
	 *
	 * A ceiling rather than a judgement about group size: the number goes into a form a mentor
	 * fills in by hand, and a typo of 200 should not become a session nobody can moderate.
	 */
	const MAX_CAPACITY = 50;

	/** Fewest, because a session for one is a one-to-one call and there is already one of those. */
	const MIN_CAPACITY = 2;

	/** Longest a session may run, in minutes. */
	const MAX_MINUTES = 480;

	/**
	 * Shortest a session may run, in minutes, and the grid the length field steps on.
	 *
	 * The two are the same number on purpose. A number field's `step` counts from its `min`, not
	 * from zero, so `min="1" step="5"` made the valid lengths 1, 6, 11 … 56, 61 - and rejected 60,
	 * which was the field's own default value. Reported by Celi Garoe in prerelease testing
	 * (WordPress/WPCredits#166): "It does not allow for 60 minutes (but yes more or less than
	 * that)." Keeping the floor on the grid is what makes every multiple of it valid.
	 */
	const MIN_MINUTES = 5;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_CREATE, array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_post_' . self::ACTION_JOIN, array( __CLASS__, 'handle_join' ) );
		add_action( 'admin_post_' . self::ACTION_LEAVE, array( __CLASS__, 'handle_leave' ) );
		add_action( 'admin_post_' . self::ACTION_NOTE, array( __CLASS__, 'handle_note' ) );
		add_action( 'admin_post_' . self::ACTION_EDIT, array( __CLASS__, 'handle_edit' ) );
	}

	/*
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * A mentor's upcoming group sessions, soonest first.
	 *
	 * @param int $mentor_id Mentor user ID.
	 * @return WP_Post[]
	 */
	public static function for_mentor( $mentor_id ) {
		$out = array();

		foreach ( WPCPM_Mentor_Calls::for_mentor( $mentor_id, true ) as $call ) {
			if ( WPCPM_Mentor_Calls::capacity( $call->ID ) > 1 ) {
				$out[] = $call;
			}
		}

		return $out;
	}

	/**
	 * The sessions one student may see: their own mentor's, upcoming.
	 *
	 * Only their mentor's. A session is not public - it is an office hour for the students that
	 * mentor is responsible for, and listing somebody else's would tell a student who else is on
	 * the program.
	 *
	 * @param int $student_id Student user ID.
	 * @return WP_Post[]
	 */
	public static function for_student( $student_id ) {
		$mentor = WPCPM_Mentor_Calls::mentor_for_student( $student_id );

		if ( ! $mentor instanceof WP_User ) {
			return array();
		}

		return self::for_mentor( $mentor->ID );
	}

	/**
	 * Whether a student is on a session.
	 *
	 * @param int $call_id    Session post ID.
	 * @param int $student_id Student user ID.
	 * @return bool
	 */
	public static function has_joined( $call_id, $student_id ) {
		return in_array( (int) $student_id, WPCPM_Mentor_Calls::attendees( $call_id ), true );
	}

	/*
	 * Creating
	 * --------------------------------------------------------------------
	 */

	/**
	 * Create a session.
	 */
	public static function handle_create() {
		check_admin_referer( self::ACTION_CREATE );

		$mentor_id = isset( $_POST['mentor'] ) ? absint( wp_unslash( $_POST['mentor'] ) ) : 0;

		// The same gate the availability form uses: the mentor themselves, or a program manager
		// acting for them. Anything else is somebody creating a session on another mentor's diary.
		if ( ! WPCPM_Mentor_Availability::user_can_edit( $mentor_id ) ) {
			wp_die( esc_html__( 'You cannot create a session for that mentor.', 'wpcredits-program-manager' ), 403 );
		}

		// Sanitized *and* validated: `sanitize_text_field()` for the shape, then `date_string()` and
		// `time_string()`, which return an empty string for anything that is not a real date or a
		// real time. The sanitizer is not redundant - it is what makes the validator's input a
		// plain string rather than whatever was posted.
		$date     = isset( $_POST['date'] ) ? WPCPM_Mentor_Availability::date_string( sanitize_text_field( wp_unslash( $_POST['date'] ) ) ) : '';
		$time     = isset( $_POST['time'] ) ? WPCPM_Mentor_Availability::time_string( sanitize_text_field( wp_unslash( $_POST['time'] ) ) ) : '';
		$minutes  = isset( $_POST['minutes'] ) ? absint( wp_unslash( $_POST['minutes'] ) ) : 0;
		$capacity = isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 0;
		$topic    = isset( $_POST['topic'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic'] ) ) : '';
		$topic    = trim( mb_substr( $topic, 0, WPCPM_Mentor_Calls::MAX_TOPIC ) );

		if ( '' === $date || '' === $time ) {
			self::bounce( 'session-when' );
		}

		if ( $minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES ) {
			self::bounce( 'session-length' );
		}

		if ( $capacity < self::MIN_CAPACITY || $capacity > self::MAX_CAPACITY ) {
			self::bounce( 'session-capacity' );
		}

		// Entered in the mentor's own clock - the one they mean when they say "Tuesday at two" -
		// and stored as UTC, exactly as the weekly hours are.
		$zone  = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( $mentor_id )['timezone'] );
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $zone );

		if ( false === $start ) {
			self::bounce( 'session-when' );
		}

		$start_ts = $start->getTimestamp();

		if ( $start_ts <= time() ) {
			self::bounce( 'session-past' );
		}

		// Nothing else of this mentor's may start at the same moment, in either direction: a
		// session over a booked call would double-book the mentor, and two sessions at once is a
		// mistake rather than a plan.
		if ( ! empty( WPCPM_Mentor_Calls::taken_starts( $mentor_id, $start_ts, $start_ts ) ) ) {
			self::bounce( 'session-clash' );
		}

		// `private`, like every call: see `WPCPM_Mentor_Calls::register_post_type()`. A
		// `publish` row here handed out the mentor's login through `?author=N`.
		$post_id = wp_insert_post(
			array(
				'post_type'    => WPCPM_Mentor_Calls::POST_TYPE,
				'post_status'  => 'private',
				'post_author'  => get_current_user_id(),
				'post_content' => $topic,
				'post_title'   => sprintf(
					/* translators: %s: session date and time. */
					__( 'Group session - %s', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d H:i', $start_ts )
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::bounce( 'error' );
		}

		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_START, $start_ts );
		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_END, $start_ts + ( $minutes * MINUTE_IN_SECONDS ) );
		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_MENTOR, (int) $mentor_id );
		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_CAPACITY, $capacity );
		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_ZONE, $zone->getName() );

		self::bounce( 'session-created' );
	}

	/**
	 * Change a session that has already been announced.
	 *
	 * A mentor could cancel a session but not move it, so a room double-booked or a clash with a
	 * class meant cancelling on everybody and asking them to join again. This is the same form
	 * as creating one, with the three rules an edit needs and a create does not.
	 *
	 * **Places may not fall below the people already on it.** Reducing capacity cannot be allowed
	 * to silently decide which student loses their place; the mentor removes somebody or keeps
	 * the places.
	 *
	 * **The clash test ignores this session.** Every session clashes with itself, so the check
	 * that stops two things starting at once has to be told which one is being moved.
	 *
	 * **Everybody on it is told, and their calendar is moved.** A session whose time changed
	 * without an invitation is worse than one that was cancelled: the student has an entry that
	 * is now wrong and no reason to look again.
	 */
	public static function handle_edit() {
		$call_id = isset( $_POST['session'] ) ? absint( wp_unslash( $_POST['session'] ) ) : 0;

		check_admin_referer( self::ACTION_EDIT . '_' . $call_id );

		$call = self::session( $call_id );

		if ( null === $call ) {
			self::bounce( 'session-gone' );
		}

		$mentor_id = (int) get_post_meta( $call->ID, WPCPM_Mentor_Calls::META_MENTOR, true );

		// The same gate as creating one: the mentor themselves, or a program manager acting for
		// them. Read from the session rather than from the form, so a posted mentor ID decides
		// nothing.
		if ( ! WPCPM_Mentor_Availability::user_can_edit( $mentor_id ) ) {
			wp_die( esc_html__( 'You cannot change that session.', 'wpcredits-program-manager' ), 403 );
		}

		$date     = isset( $_POST['date'] ) ? WPCPM_Mentor_Availability::date_string( sanitize_text_field( wp_unslash( $_POST['date'] ) ) ) : '';
		$time     = isset( $_POST['time'] ) ? WPCPM_Mentor_Availability::time_string( sanitize_text_field( wp_unslash( $_POST['time'] ) ) ) : '';
		$minutes  = isset( $_POST['minutes'] ) ? absint( wp_unslash( $_POST['minutes'] ) ) : 0;
		$capacity = isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 0;
		$topic    = isset( $_POST['topic'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic'] ) ) : '';
		$topic    = trim( mb_substr( $topic, 0, WPCPM_Mentor_Calls::MAX_TOPIC ) );

		if ( '' === $date || '' === $time ) {
			self::bounce( 'session-when' );
		}

		if ( $minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES ) {
			self::bounce( 'session-length' );
		}

		if ( $capacity < self::MIN_CAPACITY || $capacity > self::MAX_CAPACITY ) {
			self::bounce( 'session-capacity' );
		}

		$facts = WPCPM_Mentor_Calls::details( $call );
		$taken = count( $facts['attendees'] );

		if ( $capacity < $taken ) {
			self::bounce( 'session-shrink' );
		}

		$zone  = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( $mentor_id )['timezone'] );
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $zone );

		if ( false === $start ) {
			self::bounce( 'session-when' );
		}

		$start_ts = $start->getTimestamp();

		if ( $start_ts <= time() ) {
			self::bounce( 'session-past' );
		}

		// Anything else of this mentor's starting at that moment. `taken_starts()` cannot answer
		// this one, because it reports which instants are taken and not by what, and every
		// session clashes with itself.
		if ( self::clashes_with_another( $mentor_id, $start_ts, $call->ID ) ) {
			self::bounce( 'session-clash' );
		}

		$was_start = (int) $facts['start'];
		$was_end   = (int) $facts['end'];
		$end_ts    = $start_ts + ( $minutes * MINUTE_IN_SECONDS );

		wp_update_post(
			array(
				'ID'           => $call->ID,
				'post_content' => $topic,
				'post_title'   => sprintf(
					/* translators: %s: session date and time. */
					__( 'Group session - %s', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d H:i', $start_ts )
				),
			)
		);

		update_post_meta( $call->ID, WPCPM_Mentor_Calls::META_START, $start_ts );
		update_post_meta( $call->ID, WPCPM_Mentor_Calls::META_END, $end_ts );
		update_post_meta( $call->ID, WPCPM_Mentor_Calls::META_CAPACITY, $capacity );
		update_post_meta( $call->ID, WPCPM_Mentor_Calls::META_ZONE, $zone->getName() );

		// Only when the time actually moved. Correcting a typo in the topic should not put an
		// email in front of everybody on the session, and a calendar that gets an update for an
		// event that did not change teaches people to ignore the next one.
		if ( $start_ts !== $was_start || $end_ts !== $was_end ) {
			$revision = (int) get_post_meta( $call->ID, self::META_REVISION, true ) + 1;
			update_post_meta( $call->ID, self::META_REVISION, $revision );

			WPCPM_Mentor_Calls::notify_session_moved( $call->ID, $was_start, $revision );
		}

		self::bounce( 'session-updated' );
	}

	/**
	 * Whether anything else of this mentor's starts at that moment.
	 *
	 * `taken_starts()` answers with the timestamps that are taken, which cannot say *which*
	 * booking holds one. For an edit that is the whole question, so the sessions and calls at
	 * that instant are read back and the one being moved is discounted.
	 *
	 * @param int $mentor_id The mentor.
	 * @param int $start_ts  The proposed start.
	 * @param int $except    The session being moved.
	 * @return bool
	 */
	private static function clashes_with_another( $mentor_id, $start_ts, $except ) {
		$others = get_posts(
			array(
				'post_type'        => WPCPM_Mentor_Calls::POST_TYPE,
				'post_status'      => 'private',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'exclude'          => array( (int) $except ),
				'suppress_filters' => false,
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'   => WPCPM_Mentor_Calls::META_MENTOR,
						'value' => (int) $mentor_id,
					),
					array(
						'key'   => WPCPM_Mentor_Calls::META_START,
						'value' => (int) $start_ts,
					),
				),
			)
		);

		return ! empty( $others );
	}

	/*
	 * Joining and leaving
	 * --------------------------------------------------------------------
	 */

	/**
	 * Join a session.
	 */
	public static function handle_join() {
		check_admin_referer( self::ACTION_JOIN );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in to join a session.', 'wpcredits-program-manager' ), 403 );
		}

		$student_id = self::acting_student();
		$call       = self::session( isset( $_POST['session'] ) ? absint( wp_unslash( $_POST['session'] ) ) : 0 );

		if ( null === $call ) {
			self::bounce( 'session-gone' );
		}

		$mentor = get_user_by( 'id', (int) get_post_meta( $call->ID, WPCPM_Mentor_Calls::META_MENTOR, true ) );

		// Their own mentor's session and nobody else's, checked on the server rather than trusted
		// from the form - the session ID is a number anybody could change.
		$theirs = WPCPM_Mentor_Calls::mentor_for_student( $student_id );

		if ( ! $mentor instanceof WP_User || ! $theirs instanceof WP_User || (int) $theirs->ID !== (int) $mentor->ID ) {
			self::bounce( 'session-not-yours' );
		}

		if ( self::has_joined( $call->ID, $student_id ) ) {
			self::bounce( 'session-already' );
		}

		// An upcoming session is an upcoming call, so it counts against the mentor's per-student
		// limit - read before the lock, and again inside it.
		if ( '' !== WPCPM_Mentor_Calls::why_not_bookable( $student_id, $mentor ) ) {
			self::bounce( 'blocked' );
		}

		// The same lock the one-to-one booking takes, for the same reason: two students racing for
		// the last place would both read "one place left" and both take it.
		if ( ! WPCPM_Mentor_Calls::lock_for( $mentor->ID ) ) {
			self::bounce( 'busy' );
		}

		if ( ! WPCPM_Mentor_Calls::has_room( $call->ID ) ) {
			WPCPM_Mentor_Calls::unlock_for( $mentor->ID );
			self::bounce( 'session-full' );
		}

		if ( '' !== WPCPM_Mentor_Calls::why_not_bookable( $student_id, $mentor ) ) {
			WPCPM_Mentor_Calls::unlock_for( $mentor->ID );
			self::bounce( 'blocked' );
		}

		WPCPM_Mentor_Calls::add_attendee( $call->ID, $student_id, WPCPM_Mentor_Calls::student_record( $student_id ) );

		WPCPM_Mentor_Calls::unlock_for( $mentor->ID );

		WPCPM_Mentor_Calls::notify_joined( $call->ID, $mentor, get_user_by( 'id', $student_id ) );

		self::bounce( 'session-joined' );
	}

	/**
	 * Leave a session.
	 *
	 * Leaving is not cancelling. The session goes on for everybody else, so only the person
	 * leaving is told - and the place they free goes straight back.
	 */
	public static function handle_leave() {
		check_admin_referer( self::ACTION_LEAVE );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in to leave a session.', 'wpcredits-program-manager' ), 403 );
		}

		$student_id = self::acting_student();
		$call       = self::session( isset( $_POST['session'] ) ? absint( wp_unslash( $_POST['session'] ) ) : 0 );

		if ( null === $call || ! self::has_joined( $call->ID, $student_id ) ) {
			self::bounce( 'session-gone' );
		}

		WPCPM_Mentor_Calls::remove_attendee( $call->ID, $student_id, WPCPM_Mentor_Calls::student_record( $student_id ) );
		WPCPM_Mentor_Calls::notify_left( $call->ID, $student_id );

		self::bounce( 'session-left' );
	}


	/**
	 * Write one note against everybody who attended a session.
	 */
	public static function handle_note() {
		$call_id = isset( $_POST['session'] ) ? absint( wp_unslash( $_POST['session'] ) ) : 0;

		check_admin_referer( self::ACTION_NOTE . '_' . $call_id );

		$call = get_post( $call_id );

		if ( ! $call instanceof WP_Post || WPCPM_Mentor_Calls::POST_TYPE !== $call->post_type ) {
			wp_die( esc_html__( 'That session does not exist.', 'wpcredits-program-manager' ), 404 );
		}

		$mentor_id = (int) get_post_meta( $call->ID, WPCPM_Mentor_Calls::META_MENTOR, true );

		// The mentor whose session it is, or a program manager. `add_for_records()` checks access to
		// every attendee as well, so this is the outer of two gates rather than the only one.
		if ( ! WPCPM_Mentor_Availability::user_can_edit( $mentor_id ) ) {
			wp_die( esc_html__( 'You cannot add notes for that session.', 'wpcredits-program-manager' ), 403 );
		}

		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		$saved = WPCPM_Mentor_Notes::add_for_records(
			$call->ID,
			trim( mb_substr( $note, 0, WPCPM_Mentor_Notes::MAX_LENGTH ) ),
			WPCPM_Mentor_Calls::attendee_records( $call->ID )
		);

		self::bounce( is_wp_error( $saved ) ? 'session-note-failed' : 'session-noted' );
	}

	/*
	 * Rendering
	 * --------------------------------------------------------------------
	 */

	/**
	 * The mentor's own panel: what is coming, and the form to announce another.
	 *
	 * @param WP_User $mentor The mentor.
	 */
	public static function render_mentor_panel( WP_User $mentor ) {
		$sessions = self::for_mentor( $mentor->ID );
		$zone     = WPCPM_Mentor_Availability::viewer_timezone( $mentor->ID );

		echo '<div class="wpcpm-sessions">';

		printf(
			'<h3 class="wpcpm-calls__heading">%1$s <span class="wpcpm-calls__count">%2$s</span></h3>',
			esc_html__( 'Group sessions', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $sessions ) ) )
		);

		if ( empty( $sessions ) ) {
			printf(
				'<p class="wpcpm-calls__empty">%s</p>',
				esc_html__( 'No sessions planned.', 'wpcredits-program-manager' )
			);
		} else {
			echo '<ul class="wpcpm-sessions__list">';

			foreach ( $sessions as $session ) {
				self::render_session_row( $session, $zone, true );
			}

			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * The panel that plans one, for the column beside the diary.
	 *
	 * Split from the list on purpose: what is in the calendar reads down the left with the booked
	 * calls, and the two controls that change the calendar - the hours, and this - sit together on
	 * the right. The explanation travels with the control rather than with the list, because it
	 * describes what pressing it does.
	 *
	 * @param WP_User $mentor The mentor whose sessions these are.
	 */
	public static function render_mentor_planner( WP_User $mentor ) {
		$settings = WPCPM_Mentor_Availability::get( $mentor->ID );

		echo '<div class="wpcpm-sessions wpcpm-sessions--planner">';

		self::render_create_form( $mentor, $settings );

		printf(
			'<p class="wpcpm-calls__intro">%s</p>',
			esc_html__( 'An open session your students can join, at a time you choose. It also blocks that time from one-to-one booking, so nobody books you privately over it.', 'wpcredits-program-manager' )
		);

		echo '</div>';
	}

	/**
	 * The form a mentor announces a session with.
	 *
	 * @param WP_User $mentor   The mentor.
	 * @param array   $settings Their availability settings, for the timezone the times mean.
	 */
	private static function render_create_form( WP_User $mentor, array $settings ) {
		$zone = WPCPM_Mentor_Availability::timezone( $settings['timezone'] );

		echo '<details class="wpcpm-sessions__new">';
		printf(
			'<summary class="wpcpm-sessions__new-toggle">%s</summary>',
			esc_html__( 'Plan a group session', 'wpcredits-program-manager' )
		);

		printf(
			'<form class="wpcpm-sessions__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Creating…', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION_CREATE );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CREATE ) );
		printf( '<input type="hidden" name="mentor" value="%d" />', (int) $mentor->ID );

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-session-date">%1$s</label>'
				. '<input type="date" id="wpcpm-session-date" name="date" required /></p>',
			esc_html__( 'Date', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-session-time">%1$s</label>'
				. '<input type="time" id="wpcpm-session-time" name="time" required />'
				. '<span class="wpcpm-field__hint">%2$s</span></p>',
			esc_html__( 'Start time', 'wpcredits-program-manager' ),
			esc_html(
				sprintf(
					/* translators: %s: timezone name, e.g. Europe/Riga. */
					__( 'In your own timezone, %s. Your students see it in theirs.', 'wpcredits-program-manager' ),
					WPCPM_Mentor_Availability::zone_label( $zone->getName() )
				)
			)
		);

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-session-minutes">%1$s</label>'
				. '<input type="number" id="wpcpm-session-minutes" name="minutes" value="60" min="%2$d" max="%3$d" step="%2$d" required /></p>',
			esc_html__( 'Length in minutes', 'wpcredits-program-manager' ),
			(int) self::MIN_MINUTES,
			(int) self::MAX_MINUTES
		);

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-session-capacity">%1$s</label>'
				. '<input type="number" id="wpcpm-session-capacity" name="capacity" value="6" min="%2$d" max="%3$d" required />'
				. '<span class="wpcpm-field__hint">%4$s</span></p>',
			esc_html__( 'Places', 'wpcredits-program-manager' ),
			(int) self::MIN_CAPACITY,
			(int) self::MAX_CAPACITY,
			esc_html__( 'How many students may join.', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-session-topic">%1$s</label>'
				. '<textarea id="wpcpm-session-topic" name="topic" rows="3" maxlength="%2$d"></textarea>'
				. '<span class="wpcpm-field__hint">%3$s</span></p>',
			esc_html__( 'What it is about', 'wpcredits-program-manager' ),
			(int) WPCPM_Mentor_Calls::MAX_TOPIC,
			esc_html__( 'Shown to your students beside the session, so they know whether it is for them.', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-sessions__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
			esc_html__( 'Create the session', 'wpcredits-program-manager' )
		);

		echo '</form>';
		echo '</details>';
	}

	/**
	 * The list a student sees: their mentor's sessions, with a way on and off each.
	 *
	 * @param WP_User $student           The student.
	 * @param bool    $viewer_is_student Whether the person looking is the student themselves.
	 */
	public static function render_student_list( WP_User $student, $viewer_is_student ) {
		$sessions = self::for_student( $student->ID );

		if ( empty( $sessions ) ) {
			return;
		}

		$zone = WPCPM_Mentor_Availability::viewer_timezone( $student->ID );

		echo '<div class="wpcpm-sessions wpcpm-sessions--student">';

		printf(
			'<h4 class="wpcpm-calls__subheading">%s</h4>',
			esc_html__( 'Group sessions with your mentor', 'wpcredits-program-manager' )
		);

		echo '<ul class="wpcpm-sessions__list">';

		foreach ( $sessions as $session ) {
			self::render_session_row( $session, $zone, false, $student, $viewer_is_student );
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * One session in a list.
	 *
	 * @param WP_Post      $session           The session.
	 * @param DateTimeZone $zone              The clock to show it in.
	 * @param bool         $for_mentor        Whether this is the mentor's own list.
	 * @param WP_User|null $student           The student, on their list.
	 * @param bool         $viewer_is_student Whether the viewer may join or leave.
	 */
	private static function render_session_row( WP_Post $session, DateTimeZone $zone, $for_mentor, $student = null, $viewer_is_student = false ) {
		$facts  = WPCPM_Mentor_Calls::details( $session );
		$joined = ( $student instanceof WP_User ) && self::has_joined( $session->ID, $student->ID );

		echo '<li class="wpcpm-sessions__item">';

		printf(
			'<p class="wpcpm-call__when"><strong>%1$s</strong> <span class="wpcpm-call__relative">%2$s</span></p>',
			esc_html( WPCPM_Mentor_Calls::format_range( $facts['start'], $facts['end'], $zone ) ),
			esc_html(
				sprintf(
					/* translators: %s: human time difference, e.g. "3 days". */
					__( 'in %s', 'wpcredits-program-manager' ),
					human_time_diff( $facts['start'] )
				)
			)
		);

		// The count, and for a student whether there is room. "Full" is the one fact that decides
		// whether reading any further is worth their time.
		printf(
			'<p class="wpcpm-sessions__places">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: places taken, 2: places in total. */
					__( '%1$s of %2$s places taken', 'wpcredits-program-manager' ),
					number_format_i18n( count( $facts['attendees'] ) ),
					number_format_i18n( $facts['capacity'] )
				)
			)
		);

		if ( '' !== trim( (string) $facts['topic'] ) ) {
			printf( '<p class="wpcpm-call__topic">%s</p>', esc_html( $facts['topic'] ) );
		}

		if ( $for_mentor ) {
			self::render_attendees( $facts );
			self::render_note_form( $session, $facts );
			self::render_mentor_actions( $session, $facts );
		} elseif ( $viewer_is_student && $student instanceof WP_User ) {
			self::render_student_actions( $session, $facts, $student, $joined );
		} elseif ( $joined ) {
			printf( '<p class="wpcpm-sessions__state">%s</p>', esc_html__( 'They are on this session.', 'wpcredits-program-manager' ) );
		}

		echo '</li>';
	}

	/**
	 * Who is coming, for the mentor.
	 *
	 * Named, because the mentor needs to know who to expect - and the mentor is the one person
	 * who is already entitled to every one of these names.
	 *
	 * @param array $facts From `details()`.
	 */
	private static function render_attendees( array $facts ) {
		if ( empty( $facts['attendees'] ) ) {
			printf( '<p class="wpcpm-sessions__state">%s</p>', esc_html__( 'Nobody has joined yet.', 'wpcredits-program-manager' ) );

			return;
		}

		$names = array();

		foreach ( $facts['attendees'] as $student_id ) {
			$user = get_user_by( 'id', $student_id );

			if ( $user instanceof WP_User ) {
				$names[] = $user->display_name;
			}
		}

		printf(
			'<p class="wpcpm-sessions__attendees">%s</p>',
			esc_html( implode( ', ', $names ) )
		);
	}

	/**
	 * One note for everybody who came.
	 *
	 * Offered whether or not the session has happened, because a mentor writing up three sessions on
	 * a Friday should not be blocked by the clock - and hidden when nobody has joined, since there
	 * would be nobody for the note to land on.
	 *
	 * @param WP_Post $session The session.
	 * @param array   $facts   From `details()`.
	 */
	private static function render_note_form( WP_Post $session, array $facts ) {
		if ( empty( $facts['attendees'] ) ) {
			return;
		}

		echo '<details class="wpcpm-sessions__note">';
		printf(
			'<summary class="wpcpm-sessions__note-toggle">%s</summary>',
			esc_html__( 'Add a note for everybody on this session', 'wpcredits-program-manager' )
		);

		printf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving…', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_NOTE . '_' . $session->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_NOTE ) );
		printf( '<input type="hidden" name="session" value="%d" />', (int) $session->ID );

		printf(
			'<label class="screen-reader-text" for="wpcpm-session-note-%1$d">%2$s</label>'
				. '<textarea id="wpcpm-session-note-%1$d" name="note" rows="3" maxlength="%3$d" placeholder="%4$s"></textarea>',
			(int) $session->ID,
			esc_html__( 'Note', 'wpcredits-program-manager' ),
			(int) WPCPM_Mentor_Notes::MAX_LENGTH,
			esc_attr__( 'What did you cover with the group?', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-sessions__note-hint">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of students. */
					_n(
						'Saved once and shown on %s student\'s card.',
						'Saved once and shown on all %s students\' cards.',
						count( $facts['attendees'] ),
						'wpcredits-program-manager'
					),
					number_format_i18n( count( $facts['attendees'] ) )
				)
			)
		);

		printf(
			'<p><button type="submit" class="wpcpm-button">%s</button></p>',
			esc_html__( 'Save the note', 'wpcredits-program-manager' )
		);

		echo '</form>';
		echo '</details>';
	}

	/**
	 * The form that changes a session that has already been announced.
	 *
	 * Folded away behind a summary, because the common case is reading the list rather than
	 * changing it, and an open form per session would bury the sessions themselves.
	 *
	 * Pre-filled from the session in the mentor's own timezone, which is the clock they entered
	 * it in. The places field cannot be dragged below the number already on it, so the rule the
	 * handler enforces is visible before it is hit rather than only afterwards as an error.
	 *
	 * @param WP_Post $session The session.
	 * @param array   $facts   From `details()`.
	 */
	private static function render_edit_form( WP_Post $session, array $facts ) {
		$mentor_id = (int) $facts['mentor_id'];
		$zone      = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( $mentor_id )['timezone'] );
		$start     = ( new DateTimeImmutable( '@' . (int) $facts['start'] ) )->setTimezone( $zone );
		$minutes   = max( 1, (int) round( ( (int) $facts['end'] - (int) $facts['start'] ) / MINUTE_IN_SECONDS ) );
		$taken     = count( $facts['attendees'] );
		$floor     = max( (int) self::MIN_CAPACITY, $taken );
		$id        = 'wpcpm-session-' . (int) $session->ID;

		echo '<details class="wpcpm-sessions__edit">';
		printf(
			'<summary class="wpcpm-sessions__edit-toggle">%s</summary>',
			esc_html__( 'Change this session', 'wpcredits-program-manager' )
		);

		printf(
			'<form class="wpcpm-sessions__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving…', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION_EDIT . '_' . $session->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_EDIT ) );
		printf( '<input type="hidden" name="session" value="%d" />', (int) $session->ID );

		printf(
			'<p class="wpcpm-field"><label for="%1$s-date">%2$s</label>'
				. '<input type="date" id="%1$s-date" name="date" value="%3$s" required /></p>',
			esc_attr( $id ),
			esc_html__( 'Date', 'wpcredits-program-manager' ),
			esc_attr( $start->format( 'Y-m-d' ) )
		);

		printf(
			'<p class="wpcpm-field"><label for="%1$s-time">%2$s</label>'
				. '<input type="time" id="%1$s-time" name="time" value="%3$s" required />'
				. '<span class="wpcpm-field__hint">%4$s</span></p>',
			esc_attr( $id ),
			esc_html__( 'Start time', 'wpcredits-program-manager' ),
			esc_attr( $start->format( 'H:i' ) ),
			esc_html(
				sprintf(
					/* translators: %s: timezone name, e.g. Europe/Riga. */
					__( 'In your own timezone, %s. Your students see it in theirs.', 'wpcredits-program-manager' ),
					WPCPM_Mentor_Availability::zone_label( $zone->getName() )
				)
			)
		);

		printf(
			'<p class="wpcpm-field"><label for="%1$s-minutes">%2$s</label>'
				. '<input type="number" id="%1$s-minutes" name="minutes" value="%3$d" min="%4$d" max="%5$d" step="%4$d" required /></p>',
			esc_attr( $id ),
			esc_html__( 'Length in minutes', 'wpcredits-program-manager' ),
			(int) $minutes,
			(int) self::MIN_MINUTES,
			(int) self::MAX_MINUTES
		);

		printf(
			'<p class="wpcpm-field"><label for="%1$s-capacity">%2$s</label>'
				. '<input type="number" id="%1$s-capacity" name="capacity" value="%3$d" min="%4$d" max="%5$d" required />'
				. '<span class="wpcpm-field__hint">%6$s</span></p>',
			esc_attr( $id ),
			esc_html__( 'Places', 'wpcredits-program-manager' ),
			(int) $facts['capacity'],
			(int) $floor,
			(int) self::MAX_CAPACITY,
			esc_html(
				$taken > 0
					? sprintf(
						/* translators: %s: how many students are already on the session. */
						_n( '%s student is already on it, so the places cannot go below that.', '%s students are already on it, so the places cannot go below that.', $taken, 'wpcredits-program-manager' ),
						number_format_i18n( $taken )
					)
					: __( 'How many students may join.', 'wpcredits-program-manager' )
			)
		);

		printf(
			'<p class="wpcpm-field"><label for="%1$s-topic">%2$s</label>'
				. '<textarea id="%1$s-topic" name="topic" rows="3" maxlength="%3$d">%4$s</textarea></p>',
			esc_attr( $id ),
			esc_html__( 'What it is about', 'wpcredits-program-manager' ),
			(int) WPCPM_Mentor_Calls::MAX_TOPIC,
			esc_textarea( (string) $facts['topic'] )
		);

		if ( $taken > 0 ) {
			printf(
				'<p class="wpcpm-field__hint">%s</p>',
				esc_html__( 'If you change the time, everybody on the session is emailed a new invitation that replaces the one in their calendar.', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<p class="wpcpm-sessions__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
			esc_html__( 'Save the changes', 'wpcredits-program-manager' )
		);

		echo '</form>';
		echo '</details>';
	}

	/**
	 * The mentor's controls for a session: change it, or cancel it.
	 *
	 * Cancelling is the existing call cancellation, which trashes the post and tells every
	 * attendee - there is nothing group-specific to add. Changing it is this module's own, and
	 * comes first because cancelling on everybody used to be the only way to move a session.
	 *
	 * @param WP_Post $session The session.
	 * @param array   $facts   From `details()`.
	 */
	private static function render_mentor_actions( WP_Post $session, array $facts ) {
		self::render_edit_form( $session, $facts );

		printf(
			'<form class="wpcpm-call__cancel" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Canceling…', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Mentor_Calls::ACTION_CANCEL . '_' . $session->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Mentor_Calls::ACTION_CANCEL ) );
		printf( '<input type="hidden" name="call" value="%d" />', (int) $session->ID );
		printf(
			'<button type="submit" class="wpcpm-link-button" data-wpcpm-confirm="%1$s">%2$s</button>',
			esc_attr__( 'Cancel this session for everybody on it?', 'wpcredits-program-manager' ),
			esc_html__( 'Cancel the session', 'wpcredits-program-manager' )
		);
		echo '</form>';
	}

	/**
	 * The student's controls: join, or leave.
	 *
	 * @param WP_Post $session The session.
	 * @param array   $facts   From `details()`.
	 * @param WP_User $student The student.
	 * @param bool    $joined  Whether they are already on it.
	 */
	private static function render_student_actions( WP_Post $session, array $facts, WP_User $student, $joined ) {
		if ( $joined ) {
			printf(
				'<form class="wpcpm-call__cancel" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Leaving…', 'wpcredits-program-manager' )
			);
			wp_nonce_field( self::ACTION_LEAVE );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_LEAVE ) );
			printf( '<input type="hidden" name="session" value="%d" />', (int) $session->ID );
			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
			printf(
				'<button type="submit" class="wpcpm-link-button" data-wpcpm-confirm="%1$s">%2$s</button>',
				esc_attr__( 'Leave this session? Your place goes back.', 'wpcredits-program-manager' ),
				esc_html__( 'Leave the session', 'wpcredits-program-manager' )
			);
			echo '</form>';

			return;
		}

		if ( $facts['places'] < 1 ) {
			printf( '<p class="wpcpm-sessions__state">%s</p>', esc_html__( 'This session is full.', 'wpcredits-program-manager' ) );

			return;
		}

		printf(
			'<form class="wpcpm-sessions__join" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Joining…', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_JOIN );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_JOIN ) );
		printf( '<input type="hidden" name="session" value="%d" />', (int) $session->ID );
		printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
		printf(
			'<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>',
			esc_html__( 'Join this session', 'wpcredits-program-manager' )
		);
		echo '</form>';
	}

	/*
	 * Helpers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whose place is being taken.
	 *
	 * A program manager may act for a student who cannot get into their account - the same
	 * allowance one-to-one booking makes, and for the same reason.
	 *
	 * @return int User ID.
	 */
	private static function acting_student() {
		$student_id = get_current_user_id();

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) && isset( $_POST['student'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.
			$requested = absint( wp_unslash( $_POST['student'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.

			if ( $requested ) {
				$student_id = $requested;
			}
		}

		return $student_id;
	}

	/**
	 * One session, if it is a session and it has not started.
	 *
	 * @param int $call_id Post ID.
	 * @return WP_Post|null
	 */
	private static function session( $call_id ) {
		$call = get_post( (int) $call_id );

		if ( ! $call instanceof WP_Post || WPCPM_Mentor_Calls::POST_TYPE !== $call->post_type || 'private' !== $call->post_status ) {
			return null;
		}

		if ( WPCPM_Mentor_Calls::capacity( $call->ID ) < 2 ) {
			return null;
		}

		// Joining something that has already started is not a thing anybody means to do.
		if ( (int) get_post_meta( $call->ID, WPCPM_Mentor_Calls::META_START, true ) <= time() ) {
			return null;
		}

		return $call;
	}

	/**
	 * Back where they came from, with a message.
	 *
	 * Which mentor a manager was inspecting rides on the referer, which the redirect preserves -
	 * so this needs no mentor argument, and an earlier draft's was doing nothing.
	 *
	 * @param string $status Message key.
	 */
	private static function bounce( $status ) {
		WPCPM_Mentor_Calls::bounce_to( $status );
	}
}
