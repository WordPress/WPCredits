<?php
/**
 * Institutions module - the request queue, and its one shipped kind: a mentor is wanted.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An institution says one of its students needs a mentor; a program manager reads that and
 * closes it.
 *
 * **Section 13 of the design spec is the boundary of this file, and it is a short one.** The
 * queue records that a mentor is wanted; it does not negotiate one. So there is no acceptance
 * here, no matching, no shortlist, no mentor-facing surface and no mail to a mentor: a mentor
 * is assigned in Airtable by the person who knows both sides, and the assignment arrives on
 * this site the ordinary way, through the students sync. What this class adds is the one
 * thing the base cannot hold, which is a school being able to say "nobody is mentoring this
 * student" in a place a manager works from, with a date on it.
 *
 * **One row per student per kind, while it is open.** A second press finds the open row and
 * says so rather than making a second one: a queue with two rows for one student is a queue
 * whose count is wrong, and pressing twice is what a person does when nothing visible
 * happened the first time.
 *
 * **The store holds three kinds and this phase raises one.** `add` (a student created by an
 * import who is not in the program records yet, section 7.6) and `format` (a change to the
 * semester report's shape, section 7.9) are the other two the design names. Both are stored
 * exactly like this one - kind, institution, student, state, note, actor - so the day either
 * grows a surface it is a handler and a label, never a migration. `raise()` is the one door
 * in for all three, which is why it takes the kind rather than assuming it; the import will
 * call it with `KIND_ADD` and nothing here changes.
 *
 * **The fence is the module's, not a second copy.** Raising is `ACT_EDIT_STUDENT` on the
 * student's own roster row, resolved through `WPCPM_Institution_Roster::cached_subject()`, so
 * the institution is read from the row and never from the form; a member of B posting A's
 * student gets the same one refusal an unknown record gets. Resolving is the capability, then
 * the nonce, then `decide()` on the institution the request itself names.
 */
class WPCPM_Institution_Request {

	/** One post per request, of any kind. */
	const POST_TYPE = 'wpcpm_inst_request';

	/** Private, like every post type in this module: these rows name students. */
	const POST_STATUS = 'private';

	/** Post meta: which kind of request this is. */
	const META_KIND = '_wpcpm_req_kind';

	/** Post meta: the Airtable institution record the row is for. The queryable key. */
	const META_INSTITUTION = '_wpcpm_req_institution';

	/** Post meta: the Students record ID the row is about; '' for a kind about no student. */
	const META_STUDENT = '_wpcpm_req_student';

	/** Post meta: `open`, `done` or `declined`. */
	const META_STATE = '_wpcpm_req_state';

	/** Post meta: the manager's words on closing it. Never mailed by this class. */
	const META_NOTE = '_wpcpm_req_note';

	/** Post meta: who raised it. Who closed it is the audit row's actor. */
	const META_ACTOR = '_wpcpm_req_actor';

	/** A student an import created is not in the program records yet. Not raised in Phase 4. */
	const KIND_ADD = 'add';

	/** Nobody is mentoring this student. The one kind this phase raises. */
	const KIND_MENTOR = 'mentor';

	/** A change to the semester report's shape. Not raised in Phase 4. */
	const KIND_FORMAT = 'format';

	/** Waiting for a program manager. */
	const STATE_OPEN = 'open';

	/** Closed because it was handled. */
	const STATE_DONE = 'done';

	/** Closed because it will not be. */
	const STATE_DECLINED = 'declined';

	/** A member asks for a mentor for one student. Nonce keyed to that student. */
	const ACTION_REQUEST = 'wpcpm_request_mentor';

	/** A program manager closes one. Nonce keyed to the request. */
	const ACTION_RESOLVE = 'wpcpm_resolve_request';

	/** Audit kind: a request was raised. */
	const LOG_RAISED = 'request_raised';

	/** Audit kind: a request was closed, either way. */
	const LOG_RESOLVED = 'request_resolved';

	/**
	 * Flash channel for the member's side.
	 *
	 * Its own channel, and the value carries the student it is about, for the reason the
	 * People card's does: one outcome, on the card it happened on, and nothing printed on a
	 * card it did not happen on. The manager's side flashes on `WPCPM_Institutions::FLASH`,
	 * which is the channel that screen reads, with the words in `messages()`.
	 */
	const FLASH = 'institution_request';

	/** Longest note kept on a decision, so one paste cannot fill the row. */
	const MAX_NOTE = 2000;

	/** Most rows `open_requests()` will ever build, whatever it is asked for. */
	const QUEUE_MAX = 200;

	/**
	 * Days a request may wait before the queue marks it overdue.
	 *
	 * Fourteen, which is open question 3's answer in the design spec: long enough to cover a
	 * holiday, short enough that a cohort cannot quietly stall. Named on the class rather
	 * than left in prose for the screen to pick a number out of, so the mark on a row and
	 * the sentence above the list cannot come from two different readings; `facts()` answers
	 * with the mark itself and the constant is for the sentence.
	 *
	 * Not `agreement_review_days` and not a setting: that one measures a manager reading
	 * something on their own desk, this one measures a school waiting for somebody to assign
	 * a mentor in Airtable, and a site that shortened the first would not mean the second.
	 */
	const OVERDUE_DAYS = 14;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_REQUEST, array( __CLASS__, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION_RESOLVE, array( __CLASS__, 'handle_resolve' ) );
	}

	/**
	 * Register the request post type.
	 *
	 * Invisible everywhere, like the audit log and the mentor notes: not public, not
	 * queryable, not in REST, not in search, no admin UI. A row names a student and the
	 * school that sent them, and the only routes to one are the student's card and the
	 * manager's queue, both of which check the reader first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Institution requests', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Institution request', 'wpcredits-program-manager' ),
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
				'supports'            => array( 'title', 'author' ),
				// A capability type nothing is granted, so no role can reach these
				// through any generic post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_inst_request', 'wpcpm_inst_requests' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The kinds the store holds.
	 *
	 * All three, from the day the store exists, so that a row written by a later phase is a
	 * row this one could have read. Only `mentor` has a surface in Phase 4; the other two are
	 * refused by nothing here and raised by nobody yet.
	 *
	 * @return string[]
	 */
	public static function kinds() {
		return array( self::KIND_ADD, self::KIND_MENTOR, self::KIND_FORMAT );
	}

	/**
	 * The states a row can be in.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array( self::STATE_OPEN, self::STATE_DONE, self::STATE_DECLINED );
	}

	/**
	 * What a kind is called where a person reads it.
	 *
	 * A server-held map, matched against the kinds this class knows; a value it does not know
	 * is named as unrecorded rather than printed raw, for the reason the People card gives
	 * about the ways a membership came about: a slug is not an answer.
	 *
	 * @param string $kind One of `kinds()`.
	 * @return string
	 */
	public static function kind_label( $kind ) {
		$labels = array(
			self::KIND_ADD    => __( 'A student to add', 'wpcredits-program-manager' ),
			self::KIND_MENTOR => __( 'A mentor is wanted', 'wpcredits-program-manager' ),
			self::KIND_FORMAT => __( 'A change to the report', 'wpcredits-program-manager' ),
		);

		$kind = (string) $kind;

		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : __( 'An unrecorded kind of request', 'wpcredits-program-manager' );
	}

	/**
	 * Which state may follow which.
	 *
	 * The full map, not a default plus exceptions, for the reason the policy's `grounds()`
	 * gives: adding a move is a visible one-line diff here and a failing assertion until the
	 * expected map in the test suite is updated in the same commit. Both closed states are
	 * terminal on purpose. A request that could be reopened would be a row whose age no
	 * longer says how long anybody waited, and the thing a school does when a closed request
	 * turns out to have been closed too early is ask again, which makes a new row with a new
	 * date - which is the truth.
	 *
	 * @return array<string, string[]>
	 */
	public static function transitions() {
		return array(
			self::STATE_OPEN     => array( self::STATE_DONE, self::STATE_DECLINED ),
			self::STATE_DONE     => array(),
			self::STATE_DECLINED => array(),
		);
	}

	/**
	 * Whether one state may follow another.
	 *
	 * @param string $from The state the row is in.
	 * @param string $to   The state asked for.
	 * @return bool
	 */
	public static function can_settle( $from, $to ) {
		$moves = self::transitions();
		$from  = (string) $from;

		return isset( $moves[ $from ] ) && in_array( (string) $to, $moves[ $from ], true );
	}

	/*
	 * The store
	 * --------------------------------------------------------------------
	 */

	/**
	 * Raise one request.
	 *
	 * The one door in, for every kind: the import will call it with `KIND_ADD` and the store
	 * will not have to change. **It writes state and an audit row; it is not a fence.** The
	 * caller has already asked `decide()` and hands the ground it was allowed on down here, so
	 * the log says how, which is section 5.6's whole point.
	 *
	 * @param string $kind        One of `kinds()`.
	 * @param string $institution Institutions record ID.
	 * @param string $student     Students record ID the request is about.
	 * @param int    $actor_id    Who raised it; 0 for a background job.
	 * @param string $ground      The decision's ground; derived from the actor when it is not one.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function raise( $kind, $institution, $student, $actor_id, $ground = '' ) {
		$kind        = sanitize_key( (string) $kind );
		$institution = trim( (string) $institution );
		$student     = trim( (string) $student );
		$actor_id    = absint( $actor_id );

		if ( ! in_array( $kind, self::kinds(), true ) ) {
			return new WP_Error( 'wpcpm_request_kind', __( 'That is not a kind of request this site keeps.', 'wpcredits-program-manager' ) );
		}

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return new WP_Error( 'wpcpm_request_institution', __( 'A request needs the institution it is for.', 'wpcredits-program-manager' ) );
		}

		// Every kind shipped so far is about one student. A kind about the institution itself
		// would pass '' here and is refused until it has a surface of its own, which is the
		// point at which somebody has to decide what its queue row says.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $student ) ) {
			return new WP_Error( 'wpcpm_request_student', __( 'A request needs the student it is about.', 'wpcredits-program-manager' ) );
		}

		// Checked here as well as in the handler, because this is the door the import comes in
		// by: one open row per student per kind is a property of the store, not of one form.
		if ( self::open_for( $kind, $institution, $student ) ) {
			return new WP_Error( 'wpcpm_request_open', __( 'There is already an open request of that kind for this student.', 'wpcredits-program-manager' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => $actor_id,
				'post_title'  => sprintf(
					/* translators: 1: what was asked for, 2: Airtable student record ID, 3: Airtable institution record ID. */
					__( '%1$s: %2$s at %3$s', 'wpcredits-program-manager' ),
					self::kind_label( $kind ),
					$student,
					$institution
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_KIND, $kind );
		update_post_meta( $post_id, self::META_INSTITUTION, $institution );
		update_post_meta( $post_id, self::META_STUDENT, $student );
		update_post_meta( $post_id, self::META_STATE, self::STATE_OPEN );
		update_post_meta( $post_id, self::META_NOTE, '' );
		update_post_meta( $post_id, self::META_ACTOR, $actor_id );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_RAISED,
				'institution' => $institution,
				'subject'     => $student,
				'actor'       => $actor_id,
				'ground'      => self::ground_for( $ground, $actor_id ),
				// Every subject this class decides on is a cached one: a roster index row on
				// the way in, the request's own meta on the way out. Nothing here reads
				// Airtable, so nothing here can claim live evidence.
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => sprintf(
					/* translators: %s: what was asked for, e.g. "A mentor is wanted". */
					__( '%s. Raised by the institution and waiting for a program manager.', 'wpcredits-program-manager' ),
					self::kind_label( $kind )
				),
				'data'        => array(
					'request' => $post_id,
					'kind'    => $kind,
				),
			)
		);

		return $post_id;
	}

	/**
	 * Close one request, either way.
	 *
	 * The one writer of the state machine, and a transition it does not allow is refused
	 * rather than applied: two managers working the same queue press the same button
	 * seconds apart, and the second must be told the row is already closed instead of
	 * overwriting the first one's answer.
	 *
	 * `META_ACTOR` is left alone: it is who raised the row. Who closed it, and with what
	 * words, is the audit entry, which is the record that cannot be edited afterwards.
	 *
	 * @param int    $post_id  The request.
	 * @param string $state    `done` or `declined`.
	 * @param string $note     The manager's words; may be empty.
	 * @param int    $actor_id Who closed it.
	 * @param string $ground   The decision's ground; derived from the actor when it is not one.
	 * @return true|WP_Error
	 */
	public static function settle( $post_id, $state, $note, $actor_id, $ground = '' ) {
		$post = self::post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'wpcpm_request_gone', __( 'That request is not here any more.', 'wpcredits-program-manager' ) );
		}

		$post_id  = (int) $post->ID;
		$state    = sanitize_key( (string) $state );
		$actor_id = absint( $actor_id );
		$from     = (string) get_post_meta( $post_id, self::META_STATE, true );

		if ( ! self::can_settle( $from, $state ) ) {
			return new WP_Error( 'wpcpm_request_state', __( 'That request is not open any more, so it was left as it is.', 'wpcredits-program-manager' ) );
		}

		$note = trim( (string) $note );
		$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, self::MAX_NOTE ) : substr( $note, 0, self::MAX_NOTE );

		$institution = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
		$student     = (string) get_post_meta( $post_id, self::META_STUDENT, true );
		$kind        = (string) get_post_meta( $post_id, self::META_KIND, true );

		update_post_meta( $post_id, self::META_STATE, $state );
		update_post_meta( $post_id, self::META_NOTE, $note );

		$message = self::STATE_DONE === $state
			? __( 'A program manager closed this request as handled.', 'wpcredits-program-manager' )
			: __( 'A program manager declined this request.', 'wpcredits-program-manager' );

		// The manager's own words go under the sentence rather than into `data`, which is for
		// facts and sanitises a paragraph into one line. A row closed with a reason is the row
		// somebody reads a year later.
		if ( '' !== $note ) {
			$message .= "\n" . $note;
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_RESOLVED,
				'institution' => $institution,
				'subject'     => $student,
				'actor'       => $actor_id,
				'ground'      => self::ground_for( $ground, $actor_id ),
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => $message,
				'data'        => array(
					'request' => $post_id,
					'kind'    => $kind,
					'state'   => $state,
				),
			)
		);

		return true;
	}

	/**
	 * The open request of one kind for one student, if there is one.
	 *
	 * **Every part of the question is asked of the database, and the query is not windowed.**
	 * This is the whole of the "one open row per student per kind" invariant: `raise()` and
	 * the handler both ask it before they write, so an answer of 0 is what lets a second row
	 * exist. A window would make that answer depend on how many other rows the institution
	 * has open - two hundred students waiting for mentors, and the two hundred and first
	 * could be asked after twice - and it would do it silently, on the busiest school on the
	 * site. The four clauses bound the query instead: what matches is one row, or the
	 * duplicates that are themselves the bug this is looking for.
	 *
	 * The database matches meta under its own collation, which does not tell `recABC` from
	 * `recabc`; Airtable record IDs do, so both IDs are compared again in PHP. The kind is a
	 * slug this class owns and never a record ID, so the clause is the whole of that filter.
	 *
	 * @param string $kind        One of `kinds()`.
	 * @param string $institution Institutions record ID.
	 * @param string $student     Students record ID.
	 * @return int The post ID, or 0.
	 */
	public static function open_for( $kind, $institution, $student ) {
		$kind        = sanitize_key( (string) $kind );
		$institution = trim( (string) $institution );
		$student     = trim( (string) $student );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) || ! WPCPM_Mentors_Sync::is_record_id( $student ) ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => -1,
				'fields'           => 'ids',
				// Oldest first, ID breaking the tie: where a duplicate does exist, the row
				// everybody is told about is the one that has been waiting longest, and it is
				// the same row on every load rather than the database's own preference.
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_INSTITUTION,
						'value' => $institution,
					),
					array(
						'key'   => self::META_STATE,
						'value' => self::STATE_OPEN,
					),
					array(
						'key'   => self::META_KIND,
						'value' => $kind,
					),
					array(
						'key'   => self::META_STUDENT,
						'value' => $student,
					),
				),
			)
		);

		foreach ( (array) $posts as $post_id ) {
			$post_id = (int) $post_id;

			if ( 0 !== strcmp( (string) get_post_meta( $post_id, self::META_INSTITUTION, true ), $institution ) ) {
				continue;
			}

			if ( 0 !== strcmp( (string) get_post_meta( $post_id, self::META_STUDENT, true ), $student ) ) {
				continue;
			}

			return $post_id;
		}

		return 0;
	}

	/**
	 * Every open request, oldest first, bounded.
	 *
	 * Oldest first because that is the order a queue is worked in, and bounded because the
	 * caller is a card on an admin screen: what a manager does about a queue is the same at
	 * two hundred as at two thousand, and the difference is a cost the screen would carry on
	 * every load. A limit of 0 or less means "as many as the ceiling allows" rather than
	 * "all", so no caller can ask this for an unbounded query by leaving an argument out.
	 *
	 * @param int $limit How many at most.
	 * @return int[] Post IDs.
	 */
	public static function open_requests( $limit = 20 ) {
		$limit = (int) $limit;
		$limit = $limit > 0 ? min( $limit, self::QUEUE_MAX ) : self::QUEUE_MAX;

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => $limit,
				'fields'           => 'ids',
				// ID breaks the tie, as the audit log's listing does: two requests raised in
				// the same second are otherwise ordered by the database's own preference.
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_STATE,
						'value' => self::STATE_OPEN,
					),
				),
			)
		);

		return array_map( 'intval', (array) $posts );
	}

	/**
	 * The requests somebody closed, newest edit first, capped.
	 *
	 * Handled and declined alike: the Administrator Dashboard shows the last few so a manager
	 * can see what a colleague did this week, not to reopen anything (a closed request has
	 * no transition, `transitions()` says so).
	 *
	 * @param int $limit Most rows to read, capped at QUEUE_MAX.
	 * @return int[] Post IDs.
	 */
	public static function closed_requests( $limit = 20 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => (int) $limit > 0 ? min( (int) $limit, self::QUEUE_MAX ) : 20,
				'fields'           => 'ids',
				'orderby'          => array(
					'modified' => 'DESC',
					'ID'       => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'     => self::META_STATE,
						'value'   => array( self::STATE_DONE, self::STATE_DECLINED ),
						'compare' => 'IN',
					),
				),
			)
		);

		return array_map( 'intval', (array) $posts );
	}

	/**
	 * What a queue row needs to know about one request.
	 *
	 * Facts only, in the shape the queue prints them, the way the agreement's
	 * `review_facts()` does: nothing here decides anything and nothing here is a URL. An
	 * unknown post answers with an empty array rather than a half-filled one, so a caller
	 * that forgot to check gets nothing to print.
	 *
	 * The student is a name and a record ID and nothing else. No address: a manager who
	 * needs one has the base, and a queue is a list of work rather than a directory.
	 *
	 * `overdue` is answered here rather than by the screen, so that every list marking a row
	 * marks it on the same threshold: `OVERDUE_DAYS`.
	 *
	 * @param int $post_id The request.
	 * @return array
	 */
	public static function facts( $post_id ) {
		$post = self::post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$post_id     = (int) $post->ID;
		$institution = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
		$student     = (string) get_post_meta( $post_id, self::META_STUDENT, true );
		$kind        = (string) get_post_meta( $post_id, self::META_KIND, true );
		$state       = (string) get_post_meta( $post_id, self::META_STATE, true );
		$at          = (int) get_post_time( 'U', true, $post );
		$actor_id    = (int) get_post_meta( $post_id, self::META_ACTOR, true );
		$actor       = $actor_id ? get_user_by( 'id', $actor_id ) : false;
		$row         = WPCPM_Institutions_Index::row( $institution );
		$row         = is_array( $row ) ? $row : array();
		$name        = trim( isset( $row['name'] ) ? (string) $row['name'] : '' );

		return array(
			'id'               => $post_id,
			'kind'             => $kind,
			'kind_label'       => self::kind_label( $kind ),
			'state'            => $state,
			'institution'      => $institution,
			// A record the index has not read yet is named by its ID rather than by nothing,
			// so a queue row never reads "A mentor is wanted at".
			'institution_name' => '' !== $name ? $name : $institution,
			'country'          => isset( $row['country'] ) ? (string) $row['country'] : '',
			'country_name'     => isset( $row['country_name'] ) ? (string) $row['country_name'] : '',
			'student'          => $student,
			'student_name'     => self::student_name( $institution, $student ),
			'actor'            => $actor_id,
			'actor_name'       => $actor instanceof WP_User ? (string) $actor->display_name : '',
			'note'             => (string) get_post_meta( $post_id, self::META_NOTE, true ),
			'at'               => $at,
			// Only an open row can be overdue. A closed one waited exactly as long as it
			// waited, and a queue that went on marking it would be arguing with its own
			// history in front of the person who answered it.
			'overdue'          => self::STATE_OPEN === $state && ( time() - $at ) > ( self::OVERDUE_DAYS * DAY_IN_SECONDS ),
		);
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * A member asks for a mentor for one of their students.
	 *
	 * The nonce is keyed to the student the request is about, so a token for asking after one
	 * student is not a token for asking after another; the subject is read before it because
	 * the key names it, and nothing has been done with it at that point.
	 *
	 * **The institution is never read from the form.** `cached_subject()` finds the row in the
	 * rosters the last sync wrote and places it by the row's own institution, so a member of B
	 * posting A's student is decided against A, is not a member of A, and gets the one
	 * refusal - byte for byte the one an unknown record gets, because two answers would be a
	 * membership oracle somebody could walk a record ID at a time.
	 *
	 * **The decision's own answer is what refuses, and the two checks under it are not a
	 * second fence.** A refused decision carries no institution at all, so the "nothing to
	 * file it under" check below would refuse the same request in the same words with this
	 * one deleted, and no behaviour a test can watch would change. That is a reason to keep
	 * both and to pin this one by reading the file, which is what the suite does: an
	 * authorisation enforced only by the shape of what a refusal happens to contain is one
	 * refactor away from being enforced by nothing at all.
	 *
	 * No ceiling: what a member can raise is bounded by their own roster and by one open row
	 * per student, and every row they can make is about a student their school sent.
	 */
	public static function handle_request() {
		$student = WPCPM_Request::posted_text( 'student' );

		check_admin_referer( self::ACTION_REQUEST . '_' . $student );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $student ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Roster::owns(
			WPCPM_Institution_Roster::cached_subject( $student, WPCPM_Institution_Roster::TYPE_STUDENT ),
			WPCPM_Institution_Policy::ACT_EDIT_STUDENT
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';

		// A manager passes the fence for an institution-less subject, which is what a student
		// no roster holds is. There is nothing to file the request under, so it is refused
		// outright rather than filed against '': section 5.5's rule, in the one handler where
		// the institution comes from the subject rather than from the switcher.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$row = self::row_for( $institution, $student );

		if ( empty( $row ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		// `has_mentor` is the assignment itself, which is the question being asked here. An
		// empty `reports` list is the neighbouring and different fact that no report record
		// exists yet, and a student can have the one without the other.
		if ( ! empty( $row['has_mentor'] ) ) {
			self::bounce( 'has-mentor', $student );
		}

		if ( self::open_for( self::KIND_MENTOR, $institution, $student ) ) {
			self::bounce( 'already', $student );
		}

		$raised = self::raise(
			self::KIND_MENTOR,
			$institution,
			$student,
			get_current_user_id(),
			isset( $decision['ground'] ) ? (string) $decision['ground'] : ''
		);

		self::bounce( is_wp_error( $raised ) ? 'error' : 'raised', $student );
	}

	/**
	 * A program manager closes one.
	 *
	 * Capability, then nonce, then the policy, in that order (spec 5.4): the capability says
	 * this is the manager's queue, the nonce says the request was meant, and `decide()` says
	 * whether the institution the row names may be acted on at all, so a refusal here is the
	 * policy's one refusal and not a bespoke one.
	 *
	 * The subject is the request's own institution meta, never a posted record: the form says
	 * which row, and the row says whose it is.
	 */
	public static function handle_resolve() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( 'request' );

		check_admin_referer( self::ACTION_RESOLVE . '_' . $post_id );

		$post = self::post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			self::finish( 'request-gone' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_STUDENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$state = WPCPM_Request::posted_key( 'state' );

		// Which button was pressed, matched against the two closing states. `open` is not one
		// of them: this handler closes rows and never reopens one.
		if ( self::STATE_DONE !== $state && self::STATE_DECLINED !== $state ) {
			self::finish( 'request-state' );
		}

		$settled = self::settle(
			(int) $post->ID,
			$state,
			self::posted_note(),
			get_current_user_id(),
			isset( $decision['ground'] ) ? (string) $decision['ground'] : ''
		);

		if ( is_wp_error( $settled ) ) {
			self::finish( 'wpcpm_request_state' === $settled->get_error_code() ? 'request-closed' : 'error' );
		}

		self::finish( self::STATE_DONE === $state ? 'request-done' : 'request-declined' );
	}

	/*
	 * The two surfaces
	 * --------------------------------------------------------------------
	 */

	/**
	 * The mentor request block on one student's card.
	 *
	 * Drawn by the student view, which owns the card; this owns what goes in it, so the
	 * control, its message and the fence that allows it live in one file. A reader the policy
	 * refuses is drawn nothing at all rather than an empty block, because an empty block is
	 * itself an answer about whose student this is.
	 *
	 * Nothing is drawn either when there is nothing to say: a student with a mentor and no
	 * request behind them is the ordinary case, and a control offering what they already have
	 * is noise on every card a school opens.
	 *
	 * @param string $record_id       Institutions record ID whose roster the reader came from.
	 * @param string $students_record The student's Students record ID, from their index row.
	 */
	public static function render_student( $record_id, $students_record ) {
		$record_id       = trim( (string) $record_id );
		$students_record = trim( (string) $students_record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) || ! WPCPM_Mentors_Sync::is_record_id( $students_record ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_STUDENT,
			WPCPM_Institution_Policy::subject_index_row( $record_id, $students_record )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		// Taken whether or not it is printed, so an outcome about another student cannot sit
		// in user meta until it surprises somebody on a card it is not about.
		$message = self::message_for( $students_record );
		$row     = self::row_for( $record_id, $students_record );
		$open    = self::open_for( self::KIND_MENTOR, $record_id, $students_record );

		if ( null === $message && 0 === $open && ! empty( $row['has_mentor'] ) ) {
			return;
		}

		echo '<section class="wpcpm-institution__card wpcpm-request">';

		printf(
			'<h2 class="wpcpm-institution__heading">%s</h2>',
			esc_html__( 'Asking for a mentor', 'wpcredits-program-manager' )
		);

		if ( null !== $message ) {
			printf(
				'<p class="wpcpm-request__message is-%1$s" role="status">%2$s</p>',
				esc_attr( $message[0] ),
				esc_html( $message[1] )
			);
		}

		if ( $open ) {
			self::render_waiting( $open );
		} elseif ( empty( $row['has_mentor'] ) ) {
			self::render_form( $students_record );
		}

		echo '</section>';
	}

	/**
	 * The open request, with its age.
	 *
	 * The age is what the school asked for and the only thing this page can honestly say:
	 * nothing on this site assigns a mentor, so a date and "with a program manager" is the
	 * whole answer. Section 13.
	 *
	 * @param int $post_id The open request.
	 */
	private static function render_waiting( $post_id ) {
		$facts = self::facts( $post_id );

		if ( empty( $facts ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-request__waiting">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: date, 2: human-readable time difference, e.g. "4 days". */
					__( 'A mentor was asked for on %1$s, %2$s ago. A program manager reads these and assigns mentors in the program records; the name appears here once they have.', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d', (int) $facts['at'] ),
					human_time_diff( (int) $facts['at'], time() )
				)
			)
		);

		printf(
			'<p class="wpcpm-student__note">%s</p>',
			esc_html__( 'Asking again does not move it up the list. If it has been a long time, write to your program manager.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The control.
	 *
	 * The institution is not in the form at all, because the handler reads it from the
	 * student's own roster row. The nonce is keyed to that student and to nothing else.
	 *
	 * @param string $students_record The student's Students record ID.
	 */
	private static function render_form( $students_record ) {
		printf(
			'<p class="wpcpm-request__lede">%s</p>',
			esc_html__( 'Nobody is mentoring this student yet. Tell a program manager one is wanted; they assign mentors in the program records, and the name appears on this card once they have.', 'wpcredits-program-manager' )
		);

		echo '<form class="wpcpm-request__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_REQUEST . '_' . $students_record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_REQUEST ) );
		printf( '<input type="hidden" name="student" value="%s" />', esc_attr( $students_record ) );
		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Ask for a mentor', 'wpcredits-program-manager' )
		);
		echo '</form>';
	}

	/**
	 * The two decisions on one request, for the manager's queue.
	 *
	 * Drawn by the class that owns them, so the queue row and the handler behind it cannot
	 * drift apart; the queue calls this the way it calls the agreement panel's review block.
	 * A closed row draws nothing: the queue lists open rows, and a row somebody closed while
	 * this screen was open should stop offering buttons that would now be refused.
	 *
	 * @param int    $post_id The request.
	 * @param string $return  WPCPM_Return::DASHBOARD when drawn on the Administrator Dashboard, else ''.
	 */
	public static function render_decisions( $post_id, $return = '' ) {
		$post = self::post( $post_id );

		if ( ! $post instanceof WP_Post || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_STUDENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		$facts = self::facts( (int) $post->ID );

		if ( empty( $facts ) || self::STATE_OPEN !== $facts['state'] ) {
			return;
		}

		printf(
			'<p class="wpcpm-request__student">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: student name or record ID, 2: who raised it. */
					__( 'The student is %1$s. Raised by %2$s.', 'wpcredits-program-manager' ),
					(string) $facts['student_name'],
					'' !== (string) $facts['actor_name'] ? (string) $facts['actor_name'] : __( 'somebody whose account is gone', 'wpcredits-program-manager' )
				)
			)
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Assigning a mentor happens in Airtable, and this site learns about it at the next students sync. Closing the row here only says the queue has dealt with it.', 'wpcredits-program-manager' )
		);

		printf(
			'<form class="wpcpm-request__decide" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_RESOLVE . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_RESOLVE ) );
		printf( '<input type="hidden" name="request" value="%d" />', (int) $post->ID );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $return, 'requests' );
		}

		printf(
			'<label class="screen-reader-text" for="wpcpm-request-note-%1$d">%2$s</label>',
			(int) $post->ID,
			esc_html__( 'What you did about it', 'wpcredits-program-manager' )
		);
		printf(
			'<textarea id="wpcpm-request-note-%1$d" name="wpcpm_request_note" rows="2" maxlength="%2$d" placeholder="%3$s"></textarea>',
			(int) $post->ID,
			(int) self::MAX_NOTE,
			esc_attr__( 'What you did about it, for the log. Optional, and not sent to the institution.', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="button button-primary" name="state" value="%1$s">%2$s</button> ',
			esc_attr( self::STATE_DONE ),
			esc_html__( 'Mark as handled', 'wpcredits-program-manager' )
		);
		printf(
			'<button type="submit" class="button" name="state" value="%1$s">%2$s</button>',
			esc_attr( self::STATE_DECLINED ),
			esc_html__( 'Decline', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * The manager screen's outcomes, in the words the reader gets.
	 *
	 * Named in the class that flashes them and merged into the screen's own map, the way the
	 * agreement panel's are: one list, beside the handler that sets it.
	 *
	 * @return array<string, array{0: string, 1: string}> Status slug to type and message.
	 */
	public static function messages() {
		return array(
			'request-done'     => array( 'success', __( 'That request is closed as handled. The institution sees it is no longer waiting.', 'wpcredits-program-manager' ) ),
			'request-declined' => array( 'success', __( 'That request is declined and closed. The institution sees it is no longer waiting.', 'wpcredits-program-manager' ) ),
			'request-closed'   => array( 'info', __( 'Nothing was changed: that request had already been closed.', 'wpcredits-program-manager' ) ),
			'request-gone'     => array( 'error', __( 'That request is not here any more.', 'wpcredits-program-manager' ) ),
			'request-state'    => array( 'error', __( 'That is not something a request can be closed as.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * Shared parts
	 * --------------------------------------------------------------------
	 */

	/**
	 * One request post, by ID, when it is one.
	 *
	 * The type is checked here so that no handler acts on somebody's page ID, and so that
	 * `facts()` and `settle()` cannot be walked through the post table.
	 *
	 * @param int $post_id The post.
	 * @return WP_Post|null
	 */
	private static function post( $post_id ) {
		$post = get_post( absint( $post_id ) );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * One student's row on one institution's roster index.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $student     Students record ID.
	 * @return array The row, or an empty array when the index has none.
	 */
	private static function row_for( $institution, $student ) {
		$rows = WPCPM_Roster_Index::rows( $institution );

		return isset( $rows[ $student ] ) && is_array( $rows[ $student ] ) ? $rows[ $student ] : array();
	}

	/**
	 * The student's name for a queue row, or their record ID.
	 *
	 * From the roster index, which is the cache the whole institution side reads; a row the
	 * index does not hold is named by its record ID rather than by nothing, so a manager can
	 * still find it in the base. Never an address: see `facts()`.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $student     Students record ID.
	 * @return string
	 */
	private static function student_name( $institution, $student ) {
		$row  = self::row_for( $institution, $student );
		$name = trim( isset( $row['name'] ) ? (string) $row['name'] : '' );

		return '' !== $name ? $name : (string) $student;
	}

	/**
	 * The ground a row is logged under.
	 *
	 * The decision's own answer when the caller has one, which is what section 5.6 asks for.
	 * A caller with none - a background job, or a path that has not asked yet - is placed by
	 * the actor: 0 is the system, a holder of `CAP_MANAGE` acts as a manager whatever else
	 * they are, and anybody else reaching a write here did so as a member. The same rule as
	 * `WPCPM_Institution_Members`, written out again rather than shared, because that copy is
	 * private to the class that owns memberships.
	 *
	 * @param string $ground   What the caller was allowed on, if anything.
	 * @param int    $actor_id The actor.
	 * @return string One of the audit log's grounds.
	 */
	private static function ground_for( $ground, $actor_id ) {
		$ground   = sanitize_key( (string) $ground );
		$actor_id = absint( $actor_id );

		if ( in_array( $ground, WPCPM_Institution_Audit::grounds(), true ) ) {
			return $ground;
		}

		if ( 0 === $actor_id ) {
			return WPCPM_Institution_Audit::GROUND_SYSTEM;
		}

		if ( user_can( $actor_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return WPCPM_Institution_Audit::GROUND_MANAGER;
		}

		return WPCPM_Institution_Audit::GROUND_MEMBER;
	}

	/**
	 * The note a manager typed, with its line breaks.
	 *
	 * `WPCPM_Request` has no reader for a textarea, and `sanitize_text_field()` would fold a
	 * two-paragraph note into one line. The same helper the agreement's handlers keep, for
	 * the same reason.
	 *
	 * @return string
	 */
	private static function posted_note() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The calling handler verifies the nonce before reaching here.
		if ( ! isset( $_POST['wpcpm_request_note'] ) || ! is_scalar( $_POST['wpcpm_request_note'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		return trim( sanitize_textarea_field( wp_unslash( $_POST['wpcpm_request_note'] ) ) );
	}

	/**
	 * The member's outcome, if there is one for this student's card.
	 *
	 * The flash is taken whether or not it is printed, for the reason the People card gives:
	 * a message nobody will be shown is a message that stays in user meta until it surprises
	 * somebody. Which card prints it is routing and not authorisation; the fence above has
	 * already decided who may see this card at all.
	 *
	 * @param string $students_record The student whose card is being drawn.
	 * @return array{0: string, 1: string}|null Type and message, or null.
	 */
	private static function message_for( $students_record ) {
		$flash   = WPCPM_Flash::take( self::FLASH );
		$status  = is_array( $flash ) && isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';
		$about   = is_array( $flash ) && isset( $flash['student'] ) ? trim( (string) $flash['student'] ) : '';
		$strings = array(
			'raised'     => array( 'success', __( 'A program manager has been asked for a mentor for this student.', 'wpcredits-program-manager' ) ),
			'already'    => array( 'info', __( 'A mentor had already been asked for. The date is below, and asking again would not move it up the list.', 'wpcredits-program-manager' ) ),
			'has-mentor' => array( 'info', __( 'This student already has a mentor, so nothing was asked for.', 'wpcredits-program-manager' ) ),
			'error'      => array( 'error', __( 'That could not be done.', 'wpcredits-program-manager' ) ),
		);

		if ( '' === $about || trim( (string) $students_record ) !== $about || ! isset( $strings[ $status ] ) ) {
			return null;
		}

		return $strings[ $status ];
	}

	/**
	 * Record the member's outcome and return to the card the control was on.
	 *
	 * **This does not return.** Every call to it ends the request, which is why the refusals
	 * above read as one line each and not as an early return with a branch around it.
	 *
	 * The student travels with the outcome so the card that prints it is the one the button
	 * was pressed on. The destination is the page that was posted from, which
	 * `wp_safe_redirect()` keeps on this site, and the dashboard when there is none.
	 *
	 * @param string $status  Outcome slug, one of the keys `message_for()` knows.
	 * @param string $student The Students record the outcome is about.
	 */
	private static function bounce( $status, $student ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'status'  => (string) $status,
				'student' => trim( (string) $student ),
			)
		);

		$back = wp_get_referer();

		wp_safe_redirect( $back ? $back : self::dashboard_url() );
		exit;
	}

	/**
	 * Record the manager's outcome and return to the queue, or to the Administrator Dashboard
	 * when the decision was posted from there.
	 *
	 * **This does not return**, for the reason `bounce()` gives. The channel is the
	 * Institutions screen's own, because that is the screen the queue is on and the one that
	 * reads it; the words are in `messages()`.
	 *
	 * @param string $status Outcome slug, one of the keys `messages()` knows.
	 */
	private static function finish( $status ) {
		WPCPM_Flash::set( WPCPM_Institutions::FLASH, (string) $status );

		$queue = admin_url( 'admin.php?page=wpcpm-institutions' ) . '#wpcpm-queue';

		// The Administrator Dashboard posts the same decision with a return field; the
		// allowlist in `WPCPM_Return` decides, and a missing or foreign value is the queue.
		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $queue ) : $queue );
		exit;
	}

	/**
	 * The institution dashboard, or the front page while it does not exist.
	 *
	 * Reached as an array callable so this file loads and its tests run whether or not the
	 * dashboard shell has landed, the way the People card reaches it.
	 *
	 * @return string
	 */
	private static function dashboard_url() {
		if ( class_exists( 'WPCPM_Institutions_Dashboard' ) && method_exists( 'WPCPM_Institutions_Dashboard', 'page_url' ) ) {
			$url = (string) call_user_func( array( 'WPCPM_Institutions_Dashboard', 'page_url' ) );

			if ( '' !== $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Delete every request. Called on uninstall.
	 *
	 * Post meta goes with the posts, so no `delete_metadata()` line is needed for it. The
	 * audit rows are not touched here: they are the history of what was asked and answered,
	 * and `WPCPM_Institution_Audit::delete_all()` is what removes those.
	 */
	public static function delete_all() {
		$requests = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $requests as $request_id ) {
			wp_delete_post( $request_id, true );
		}
	}
}
