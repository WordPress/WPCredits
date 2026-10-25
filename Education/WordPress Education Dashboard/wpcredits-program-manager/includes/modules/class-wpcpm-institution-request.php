<?php
/**
 * Institutions module - the request queue: a mentor is wanted, a student the import created is not in
 * the program records yet, or a school asks to correct the details the program checks.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The queue of what an institution has asked of the program, which a program manager reads and
 * closes.
 *
 * **Section 13 of the design spec is the boundary of this file, and it is a short one.** The
 * queue records that something is wanted; it does not negotiate it. So there is no acceptance
 * here, no matching, no shortlist, no mentor-facing surface and no mail to a mentor: a mentor
 * is assigned in Airtable by the person who knows both sides, and the assignment arrives on
 * this site the ordinary way, through the students sync.
 *
 * **A school no longer raises "a mentor is wanted" (Institutions release 2).** The program may
 * move away from mentors for every student, so the block that offered one on a student's card, its
 * form and its handler are gone, and no new surface asks for one anywhere. The kind stays in the store and
 * on the Administrator's side: a request a school made before the release is still drawn on the
 * Administrator Dashboard's "Requests from institutions" card and is closed there the way it
 * always was, with Mark as handled or Decline.
 *
 * **One row per student per kind, while it is open.** A second raise finds the open row and is
 * refused rather than making a second one: a queue with two rows for one student is a queue
 * whose count is wrong, and the import comes in by the same door.
 *
 * **The store holds three student kinds and one is raised.** `add` (a student created by an
 * import who is not in the program records yet, section 7.6) is raised by the import, for each
 * student it creates (`WPCPM_Institution_Create`). `mentor` is no longer raised by anything (see
 * above), and `format` (a change to the semester report's shape, section 7.9) is the third the
 * design names, and nothing raises it yet. All three are stored alike - kind, institution,
 * student, state, note, actor - so the day `format` grows a surface it is a handler and a label,
 * never a migration. `raise()` is the one door in for all three, which is why it takes the kind
 * rather than assuming it.
 *
 * **The fence is the module's, not a second copy.** Resolving is the capability, then the
 * nonce, then `decide()` on the institution the request itself names, read from the request's
 * own meta and never from the form.
 *
 * **The fourth kind is a school's details (Institutions release 2).** `details` asks to correct the
 * name, website, city, country or contact's email, which an Administrator checks before the program
 * records change (`WPCPM_Institution_Details`). It is about no student, there is at most one open per
 * institution, and it holds what it asks for, detail by detail, in `META_CHANGES`. It is the one kind
 * with a decision that writes something, so it has handlers of its own beside `handle_resolve()`, which
 * refuses it: Approve writes the asked values to the base through the details class, Decline needs a
 * note the school reads, and the school may Withdraw what it asked (`STATE_WITHDRAWN`). Each of the
 * three is made under the request's own lock, with its state read again under it, so two Administrators
 * pressing Approve and Decline together write once, email once and close it once. These are the only
 * handlers of this file that send mail, through the templates the registry holds, and the base is
 * written by the details class and by nothing here.
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

	/** Post meta: `open`, `done`, `declined` or `withdrawn`. */
	const META_STATE = '_wpcpm_req_state';

	/** Post meta: the manager's words on closing it. Never mailed by this class. */
	const META_NOTE = '_wpcpm_req_note';

	/** Post meta: who raised it. Who closed it is the audit row's actor. */
	const META_ACTOR = '_wpcpm_req_actor';

	/**
	 * Post meta: when it was closed, unix time. Absent on an open row and on one closed
	 * before 1.99.0.
	 *
	 * A row's own date is when it was raised, and nothing here ever moves `post_modified` off
	 * it: `settle()` writes meta, and `update_post_meta()` does not touch the post row. So
	 * without this stamp the closing had no date at all, and the Administrator Dashboard's
	 * "Recently closed" card was ordered and dated by the openings (deep check FADMN-3).
	 */
	const META_CLOSED_AT = '_wpcpm_req_closed_at';

	/**
	 * Post meta: the ground the request was closed on, one of `WPCPM_Institution_Audit::grounds()` - the
	 * same one `settle()` resolves and writes to the closing row. Absent on an open row and on one closed
	 * before this was kept, which read as the institution's.
	 *
	 * It is kept here as well as on the audit row so that a decision on a request somebody else has closed
	 * can say who closed it without a search of the institution's history: an Administrator may withdraw a
	 * school's request as well as the school, and "the school withdrew it" would then be untrue.
	 */
	const META_CLOSED_GROUND = '_wpcpm_req_closed_ground';

	/**
	 * Post meta: what a details request asks for, detail by detail (`WPCPM_Institution_Details::changes()`):
	 * the key of a detail to `array( 'from' => what the program had when it was asked, 'to' => what is
	 * asked )`. Only a request of kind `details` has any. Approve writes the `to` of each and nothing of
	 * the `from`, which is what the school was told the program had.
	 */
	const META_CHANGES = '_wpcpm_req_changes';

	/**
	 * A student an import created is not in the program records yet. Raised by the import, for
	 * each student it creates.
	 */
	const KIND_ADD = 'add';

	/** Nobody is mentoring this student. No longer raised by anything; one already on record is closed by an Administrator. */
	const KIND_MENTOR = 'mentor';

	/** A change to the semester report's shape. Nothing raises it yet. */
	const KIND_FORMAT = 'format';

	/**
	 * A school asks to correct its name, website, city, country or contact's email. Raised from the
	 * Institution Dashboard's details card, decided by an Administrator.
	 */
	const KIND_DETAILS = 'details';

	/** Waiting for a program manager. */
	const STATE_OPEN = 'open';

	/** Closed because it was handled. */
	const STATE_DONE = 'done';

	/** Closed because it will not be. */
	const STATE_DECLINED = 'declined';

	/** Closed by the school that raised it, before an Administrator decided. A details request's alone. */
	const STATE_WITHDRAWN = 'withdrawn';

	/** A program manager closes one. Nonce keyed to the request. */
	const ACTION_RESOLVE = 'wpcpm_resolve_request';

	/** An Administrator approves or declines a details request. Nonce keyed to the request. */
	const ACTION_DETAILS_DECIDE = 'wpcpm_decide_details';

	/** A school withdraws its details request. Nonce keyed to the request. */
	const ACTION_DETAILS_WITHDRAW = 'wpcpm_withdraw_details';

	/** The emails about a school's details request, by their ids in the template registry. */
	const MAIL_REQUEST   = 'institution-details-request';
	const MAIL_APPROVED  = 'institution-details-approved';
	const MAIL_DECLINED  = 'institution-details-declined';
	const MAIL_WITHDRAWN = 'institution-details-withdrawn';

	/** Audit kind: a request was raised. */
	const LOG_RAISED = 'request_raised';

	/** Audit kind: a request was closed, either way. */
	const LOG_RESOLVED = 'request_resolved';

	/**
	 * Longest note kept on a decision, so one paste cannot fill the row, in characters as typed
	 * (`WPCPM_Typed_Text::box_length()`).
	 */
	const MAX_NOTE = 2000;

	/**
	 * Shortest note a details request is declined with, in characters as typed: the school reads it, and
	 * "no" tells it nothing. The agreement's return and revoke notes have the same floor.
	 */
	const MIN_NOTE = 20;

	/**
	 * What a refused decision keeps its note under, before the request it closes
	 * (`WPCPM_Typed_Text::box_form()`), so a note typed for one request comes back in that
	 * request's box and in no other.
	 */
	const TYPED_NOTE = 'request-close';

	/**
	 * Flash channel for the school's side of a details request: what a withdrawal came to
	 * (`school_messages()`). Its own channel, so no card that draws a student's request can take it.
	 */
	const FLASH_DETAILS = 'institution_details';

	/**
	 * The posted field a school's Withdraw it names the card it was pressed on with, and the one value
	 * of it the handler follows: the details card's anchor on the Institution Dashboard. A posted value
	 * is a claim and never a place to go, so anything else is the page the press was made on, as before.
	 */
	const RETURN_FIELD = 'wpcpm_details_return';

	/** The details card's anchor on the Institution Dashboard (`WPCPM_Institution_Details_Card::ANCHOR`). */
	const DETAILS_ANCHOR = 'wpcpm-details';

	/**
	 * The option name a request's lock is kept under, with the request's ID after it (`lock_name()`),
	 * and the raising of a school's request, and the saving of its details at once, under `details_` and
	 * the school's record ID (`school_lock_name()`), through `WPCPM_Lock`. A details request is decided,
	 * or withdrawn, by one request at a time; the uninstaller sweeps the prefix.
	 */
	const LOCK_PREFIX = 'wpcpm_req_lock_';

	/**
	 * How long a request's lock holds at most, in seconds: past the longest a decision takes, a write to
	 * the base and two emails, so a lock a request that died left runs out by itself.
	 */
	const LOCK_TTL = 300;

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
		add_action( 'admin_post_' . self::ACTION_RESOLVE, array( __CLASS__, 'handle_resolve' ) );
		add_action( 'admin_post_' . self::ACTION_DETAILS_DECIDE, array( __CLASS__, 'handle_details_decide' ) );
		add_action( 'admin_post_' . self::ACTION_DETAILS_WITHDRAW, array( __CLASS__, 'handle_details_withdraw' ) );
	}

	/**
	 * Register the request post type.
	 *
	 * Invisible everywhere, like the audit log and the mentor notes: not public, not
	 * queryable, not in REST, not in search, no admin UI. A row names a student and the
	 * school that sent them, and the only routes to one are the school's details card and the
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
	 * The first three from the day the store exists, so that a row of a kind raised later is a row
	 * this one could have read. `mentor` is raised by nothing now (a school's card no longer offers
	 * one, and a request made before that is still there to close) and `add` by the import
	 * for each student it creates; `format` is refused by nothing here and raised by nothing yet;
	 * `details` (Institutions release 2) is a school's request to correct its name, website, city,
	 * country or contact's email, through `raise_details()` and not `raise()`, since it is about no
	 * student.
	 *
	 * @return string[]
	 */
	public static function kinds() {
		return array( self::KIND_ADD, self::KIND_MENTOR, self::KIND_FORMAT, self::KIND_DETAILS );
	}

	/**
	 * The states a row can be in.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array( self::STATE_OPEN, self::STATE_DONE, self::STATE_DECLINED, self::STATE_WITHDRAWN );
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
			self::KIND_ADD     => __( 'A student to add', 'wpcredits-program-manager' ),
			self::KIND_MENTOR  => __( 'A mentor is wanted', 'wpcredits-program-manager' ),
			self::KIND_FORMAT  => __( 'A change to the report', 'wpcredits-program-manager' ),
			self::KIND_DETAILS => __( 'A change to its details', 'wpcredits-program-manager' ),
		);

		$kind = (string) $kind;

		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : __( 'An unrecorded kind of request', 'wpcredits-program-manager' );
	}

	/**
	 * Which state may follow which.
	 *
	 * The full map, not a default plus exceptions, for the reason the policy's `grounds()`
	 * gives: adding a move is a visible one-line diff here and a failing assertion until the
	 * expected map in the test suite is updated in the same commit. Every closed state is
	 * terminal on purpose. A request that could be reopened would be a row whose age no
	 * longer says how long anybody waited, and the thing a school does when a closed request
	 * turns out to have been closed too early is ask again, which makes a new row with a new
	 * date - which is the truth. A request a school withdrew is closed the same way: an
	 * Administrator cannot approve what its author took back, and the school asks again.
	 *
	 * @return array<string, string[]>
	 */
	public static function transitions() {
		return array(
			self::STATE_OPEN      => array( self::STATE_DONE, self::STATE_DECLINED, self::STATE_WITHDRAWN ),
			self::STATE_DONE      => array(),
			self::STATE_DECLINED  => array(),
			self::STATE_WITHDRAWN => array(),
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
	 * **Every kind about a student, and `details` is not one.** A school's request to correct its details
	 * has a door of its own, `raise_details()`, with the school's lock, the one-open-per-school check and
	 * the changes it holds; through this one it would be a row with a student on it, no changes to approve
	 * and no lock, so it is refused here whatever it is given.
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

		if ( self::KIND_DETAILS === $kind ) {
			return new WP_Error( 'wpcpm_request_kind', __( 'A request to correct a school\'s details is raised from its details card, and not here.', 'wpcredits-program-manager' ) );
		}

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return new WP_Error( 'wpcpm_request_institution', __( 'A request needs the institution it is for.', 'wpcredits-program-manager' ) );
		}

		// Every kind this door takes is about one student. A kind about the institution itself
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

		$post_id = self::create(
			$kind,
			$institution,
			$student,
			$actor_id,
			sprintf(
				/* translators: 1: what was asked for, 2: Airtable student record ID, 3: Airtable institution record ID. */
				__( '%1$s: %2$s at %3$s', 'wpcredits-program-manager' ),
				self::kind_label( $kind ),
				$student,
				$institution
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::record_raised( $post_id, $kind, $institution, $student, $actor_id, $ground, array() );

		return $post_id;
	}

	/**
	 * Raise a school's request to correct its details.
	 *
	 * Beside `raise()` and not through it, since it is about no student, which `raise()` refuses every
	 * other kind without. The caller has decided who may ask (`ACT_EDIT_DETAILS`) and what changed
	 * (`WPCPM_Institution_Details::changes()` of the checked group, a form that changes nothing sends
	 * nothing); this keeps what was asked, detail by detail, with the value the program had when it was
	 * asked, and writes nothing to the base or the index: that is the Administrator's Approve.
	 *
	 * **One is open per school.** A second is `wpcpm_request_open`, in the sentence a school reads, and
	 * it is held under a lock of the school's own so that two accounts, or two tabs, pressing together
	 * cannot both pass the check: the store holds the invariant, not the one form. A lock another
	 * request holds is `wpcpm_request_busy`, in `saving_message()`'s words: that request is raising the
	 * school's one or saving its details at once (`WPCPM_Institution_Details_Card`), and neither has made
	 * a request that waits, so only a request truly open is told one is waiting.
	 *
	 * It audits, and emails "Who is emailed about institutions" with the changes worded as asked and a
	 * link to the Administrator Dashboard's requests card.
	 *
	 * @param string $institution Institutions record ID.
	 * @param array  $changes     Detail key to `array( 'from' => ..., 'to' => ... )`, of the checked group.
	 * @param int    $actor_id    The account that asked.
	 * @param string $ground      The decision's ground; derived from the actor when it is not one.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function raise_details( $institution, array $changes, $actor_id, $ground = '' ) {
		$institution = trim( (string) $institution );
		$actor_id    = absint( $actor_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return new WP_Error( 'wpcpm_request_institution', __( 'A request needs the institution it is for.', 'wpcredits-program-manager' ) );
		}

		$changes = self::clean_changes( $changes );

		if ( array() === $changes ) {
			return new WP_Error( 'wpcpm_request_changes', __( 'A request to correct details needs the details it asks to change.', 'wpcredits-program-manager' ) );
		}

		$waiting = new WP_Error( 'wpcpm_request_open', self::open_message() );
		$lock    = self::school_lock_name( $institution );
		$token   = WPCPM_Lock::take( $lock, self::LOCK_TTL );

		if ( '' === $token ) {
			return new WP_Error( 'wpcpm_request_busy', self::saving_message() );
		}

		try {
			if ( self::open_details_for( $institution ) ) {
				$post_id = $waiting;
			} else {
				$post_id = self::create(
					self::KIND_DETAILS,
					$institution,
					'',
					$actor_id,
					sprintf(
						/* translators: 1: what was asked for, 2: Airtable institution record ID. */
						__( '%1$s: %2$s', 'wpcredits-program-manager' ),
						self::kind_label( self::KIND_DETAILS ),
						$institution
					),
					array( self::META_CHANGES => wp_slash( $changes ) )
				);
			}
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::record_raised( $post_id, self::KIND_DETAILS, $institution, $institution, $actor_id, $ground, array( 'fields' => array_keys( $changes ) ) );

		self::mail_managers(
			self::MAIL_REQUEST,
			static function () use ( $institution, $changes, $actor_id ) {
				return array(
					'institution_name' => self::institution_name( $institution ),
					'actor_name'       => self::person_name( $actor_id ),
					'changes'          => WPCPM_Institution_Details::lines( $changes, true ),
					'request_link'     => self::requests_link(),
				);
			}
		);

		return $post_id;
	}

	/**
	 * What a school is told when it asks while its one request waits.
	 *
	 * The store's refusal (`raise_details()`, under the school's lock) and the details card's early answer to
	 * a post it can see will be refused (`WPCPM_Institution_Details_Card`) say it in these words.
	 *
	 * @return string
	 */
	public static function open_message() {
		return __( 'One change is already waiting for an Administrator.', 'wpcredits-program-manager' );
	}

	/**
	 * What a school is told when its details are being saved, or its request raised, by another request
	 * at that moment: the school's lock (`school_lock_name()`) is held, and nothing was done.
	 *
	 * `raise_details()` refuses with it, and the details card says it of a save at once that finds the lock
	 * held (`WPCPM_Institution_Details_Card`). Deciding and withdrawing a request keep their own sentence,
	 * under the request's lock.
	 *
	 * @return string
	 */
	public static function saving_message() {
		return __( 'Someone else is saving your institution\'s details at this moment, so nothing was done. Try again in a moment.', 'wpcredits-program-manager' );
	}

	/**
	 * Make one request's post and keep its facts on it, open.
	 *
	 * The one writer of a new row, for every kind: `raise()` and `raise_details()` differ in what they
	 * check and in what they say, not in what a row is. `$extra` is meta written before the row is
	 * open, so a row is never open without it.
	 *
	 * @param string $kind        One of `kinds()`.
	 * @param string $institution Institutions record ID.
	 * @param string $student     Students record ID, or '' for a kind about no student.
	 * @param int    $actor_id    Who raised it.
	 * @param string $title       The post's title, which names the row in a database tool.
	 * @param array  $extra       Meta key to value, written first and already slashed.
	 * @return int|WP_Error The post ID, or why not.
	 */
	private static function create( $kind, $institution, $student, $actor_id, $title, array $extra = array() ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => $actor_id,
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		foreach ( $extra as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		update_post_meta( $post_id, self::META_KIND, $kind );
		update_post_meta( $post_id, self::META_INSTITUTION, $institution );
		update_post_meta( $post_id, self::META_STUDENT, $student );
		update_post_meta( $post_id, self::META_STATE, self::STATE_OPEN );
		update_post_meta( $post_id, self::META_NOTE, '' );
		update_post_meta( $post_id, self::META_ACTOR, $actor_id );

		return $post_id;
	}

	/**
	 * Write the history row of a request raised.
	 *
	 * @param int    $post_id     The request.
	 * @param string $kind        Its kind.
	 * @param string $institution Institutions record ID.
	 * @param string $subject     What the row is about: the student, or the institution for a kind about none.
	 * @param int    $actor_id    Who raised it.
	 * @param string $ground      The decision's ground; derived from the actor when it is not one.
	 * @param array  $data        Facts beside the request and its kind: names, never values.
	 */
	private static function record_raised( $post_id, $kind, $institution, $subject, $actor_id, $ground, array $data ) {
		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_RAISED,
				'institution' => $institution,
				'subject'     => $subject,
				'actor'       => $actor_id,
				'ground'      => self::ground_for( $ground, $actor_id ),
				// Every subject this class decides on is a cached one: a roster index row on
				// the way in, the request's own meta on the way out. Nothing here reads
				// Airtable, so nothing here can claim live evidence.
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => sprintf(
					/* translators: %s: what was asked for, e.g. "A mentor is wanted". */
					__( '%s. Raised by the institution and waiting for an Administrator.', 'wpcredits-program-manager' ),
					self::kind_label( $kind )
				),
				'data'        => array_merge(
					array(
						'request' => $post_id,
						'kind'    => $kind,
					),
					$data
				),
			)
		);
	}

	/**
	 * The changes a details request holds, or none when they are not the checked details' pairs.
	 *
	 * All or nothing: every key is a detail an Administrator checks first (the others save at once and
	 * are never asked), and every one holds what the program had and what is asked. What it returns
	 * holds those two and nothing else, in the order given.
	 *
	 * @param array $changes What a caller gives, or what a row stores.
	 * @return array Detail key to `array( 'from' => ..., 'to' => ... )`; empty when nothing is valid.
	 */
	private static function clean_changes( array $changes ) {
		$fields = WPCPM_Institution_Details::fields();
		$out    = array();

		foreach ( $changes as $key => $change ) {
			if ( ! is_string( $key ) || ! isset( $fields[ $key ] ) || WPCPM_Institution_Details::GROUP_CHECKED !== $fields[ $key ]['group'] ) {
				return array();
			}

			if ( ! is_array( $change ) || ! array_key_exists( 'from', $change ) || ! array_key_exists( 'to', $change ) ) {
				return array();
			}

			$out[ $key ] = array(
				'from' => $change['from'],
				'to'   => $change['to'],
			);
		}

		return $out;
	}

	/**
	 * What one details request asks for, as its row holds it.
	 *
	 * @param int $post_id The request.
	 * @return array Detail key to `array( 'from' => ..., 'to' => ... )`; empty for a row that holds none,
	 *               or holds something that is not the pairs `raise_details()` stores.
	 */
	private static function changes_of( $post_id ) {
		$stored = get_post_meta( (int) $post_id, self::META_CHANGES, true );

		return is_array( $stored ) ? self::clean_changes( $stored ) : array();
	}

	/**
	 * The name of the lock a request is decided and withdrawn under, which `WPCPM_Lock` keeps as a row
	 * of the options table.
	 *
	 * @param int $post_id The request.
	 * @return string
	 */
	public static function lock_name( $post_id ) {
		return self::LOCK_PREFIX . absint( $post_id );
	}

	/**
	 * The name of the lock a school's request is raised under, and its details are saved at once under
	 * (`WPCPM_Institution_Details_Card`): one lock per school, so a raise and a save, or two saves of
	 * the same change from two tabs, are made one at a time.
	 *
	 * @param string $institution Institutions record ID.
	 * @return string
	 */
	public static function school_lock_name( $institution ) {
		return self::LOCK_PREFIX . 'details_' . trim( (string) $institution );
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
	 * @param int         $post_id  The request.
	 * @param string      $state    `done`, `declined` or `withdrawn`.
	 * @param string      $note     The manager's words, as the cleaner left them; may be empty.
	 * @param int         $actor_id Who closed it.
	 * @param string      $ground   The decision's ground; derived from the actor when it is not one.
	 * @param string|null $typed    What the manager typed for the note, unslashed and not cleaned,
	 *                              which the limit is counted on, as its box counts it
	 *                              (`WPCPM_Typed_Text::box_length()`). Null counts the note itself.
	 * @return true|WP_Error
	 */
	public static function settle( $post_id, $state, $note, $actor_id, $ground = '', $typed = null ) {
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

		// Counted on what was typed, as the box and the handler count it, so a note that fits its box
		// is kept whole, though the cleaner stores it longer. The handler refuses one over the limit;
		// this cut is for a caller that did not, which hands its note as the typing when it has none.
		if ( WPCPM_Typed_Text::box_length( null === $typed ? $note : $typed ) > self::MAX_NOTE ) {
			$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, self::MAX_NOTE ) : substr( $note, 0, self::MAX_NOTE );
		}

		$institution = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
		$student     = (string) get_post_meta( $post_id, self::META_STUDENT, true );
		$kind        = (string) get_post_meta( $post_id, self::META_KIND, true );
		$ground      = self::ground_for( $ground, $actor_id );

		update_post_meta( $post_id, self::META_STATE, $state );
		// Slashed, because post meta unslashes what it is handed and the note is not.
		update_post_meta( $post_id, self::META_NOTE, wp_slash( $note ) );
		update_post_meta( $post_id, self::META_CLOSED_AT, time() );
		update_post_meta( $post_id, self::META_CLOSED_GROUND, $ground );

		$message = self::closing_message( $kind, $state, $ground );

		// The manager's own words go under the sentence rather than into `data`, which is for
		// facts and sanitizes a paragraph into one line. A row closed with a reason is the row
		// somebody reads a year later.
		if ( '' !== $note ) {
			$message .= "\n" . $note;
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_RESOLVED,
				'institution' => $institution,
				// A kind about no student is about the institution itself.
				'subject'     => '' !== $student ? $student : $institution,
				'actor'       => $actor_id,
				'ground'      => $ground,
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
	 * What the history says of a request's closing, in a sentence it can show as written.
	 *
	 * Plain words, with no record ID, slug or prefix in them, since the school's history prints a
	 * stored message as it is (`WPCPM_Institution_History`). A details request's approval says the
	 * records were updated, which a mentor request's handling does not; a withdrawal is worded as the
	 * school's own act, unless it was made on the manager ground, when an Administrator withdrew it.
	 * The ground is the one the row is written under, so the sentence and the row's `ground` cannot
	 * disagree, and `WPCPM_Institution_History` reads a withdrawal the same way from that ground.
	 *
	 * @param string $kind   The request's kind.
	 * @param string $state  The state it was closed in.
	 * @param string $ground The ground the row is written under, one of `WPCPM_Institution_Audit::grounds()`.
	 * @return string
	 */
	private static function closing_message( $kind, $state, $ground ) {
		if ( self::STATE_WITHDRAWN === $state ) {
			return WPCPM_Institution_Audit::GROUND_MANAGER === $ground
				? __( 'An Administrator withdrew this request.', 'wpcredits-program-manager' )
				: __( 'The institution withdrew this request.', 'wpcredits-program-manager' );
		}

		if ( self::STATE_DECLINED === $state ) {
			return __( 'An Administrator declined this request.', 'wpcredits-program-manager' );
		}

		if ( self::KIND_DETAILS === $kind ) {
			return __( 'An Administrator approved this request, and the details on record were updated.', 'wpcredits-program-manager' );
		}

		return __( 'An Administrator closed this request as handled.', 'wpcredits-program-manager' );
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
		$student = trim( (string) $student );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $student ) ) {
			return 0;
		}

		return self::open_matching( $kind, $institution, $student );
	}

	/**
	 * The open details request of one institution, if there is one.
	 *
	 * The same question `open_for()` asks, without the student a details request has none of, and with
	 * the same answer to the same objections: asked of the database whole and not through a window, and
	 * the ID compared again in PHP, since the database does not tell `recABC` from `recabc` and
	 * Airtable does. At most one is open (`raise_details()` holds that), so what comes back is that one,
	 * or the oldest of the duplicates that would themselves be the bug.
	 *
	 * @param string $institution Institutions record ID.
	 * @return int The post ID, or 0.
	 */
	public static function open_details_for( $institution ) {
		return self::open_matching( self::KIND_DETAILS, $institution, null );
	}

	/**
	 * The open request of one kind for one institution, and for one student when a student is given.
	 *
	 * @param string      $kind        One of `kinds()`.
	 * @param string      $institution Institutions record ID.
	 * @param string|null $student     Students record ID, or null for a kind about no student.
	 * @return int The post ID, or 0.
	 */
	private static function open_matching( $kind, $institution, $student ) {
		$kind        = sanitize_key( (string) $kind );
		$institution = trim( (string) $institution );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return 0;
		}

		$clauses = array(
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
		);

		if ( null !== $student ) {
			$clauses[] = array(
				'key'   => self::META_STUDENT,
				'value' => $student,
			);
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
				// This read decides under the school's lock, so it must see a row another request
				// made a moment ago, not this request's cached answer from before the lock.
				'cache_results'    => false,
				'meta_query'       => $clauses,
			)
		);

		foreach ( (array) $posts as $post_id ) {
			$post_id = (int) $post_id;

			if ( 0 !== strcmp( (string) get_post_meta( $post_id, self::META_INSTITUTION, true ), $institution ) ) {
				continue;
			}

			if ( null !== $student && 0 !== strcmp( (string) get_post_meta( $post_id, self::META_STUDENT, true ), $student ) ) {
				continue;
			}

			return $post_id;
		}

		return 0;
	}

	/**
	 * What an institution was last told about a details request: the one closed last.
	 *
	 * Approved, declined or withdrawn alike: the school's card shows the outcome of its latest closed
	 * request until it sends the next, and a withdrawal is an outcome that says nothing, so it
	 * replaces an approval or a decline that was shown before it. Ordered by the moment it was closed
	 * and not by the order the requests were raised, which is not the same when a school has asked
	 * twice. Every details request closed since this kind exists carries that moment (`settle()` stamps
	 * it), so one query is the whole question; it is bounded, and the ID compared again in PHP.
	 *
	 * @param string $institution Institutions record ID.
	 * @return array `state`, `closed_at` (a Unix time) and `note` (the Administrator's words on a
	 *               decline, else ''); empty for an institution that has none.
	 */
	public static function latest_details_outcome( $institution ) {
		$institution = trim( (string) $institution );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => 20,
				'fields'           => 'ids',
				'orderby'          => array(
					'closed' => 'DESC',
					'ID'     => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_INSTITUTION,
						'value' => $institution,
					),
					array(
						'key'   => self::META_KIND,
						'value' => self::KIND_DETAILS,
					),
					array(
						'key'     => self::META_STATE,
						'value'   => array( self::STATE_DONE, self::STATE_DECLINED, self::STATE_WITHDRAWN ),
						'compare' => 'IN',
					),
					'closed'   => array(
						'key'     => self::META_CLOSED_AT,
						'compare' => 'EXISTS',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( (array) $posts as $post_id ) {
			$post_id = (int) $post_id;

			if ( 0 !== strcmp( (string) get_post_meta( $post_id, self::META_INSTITUTION, true ), $institution ) ) {
				continue;
			}

			return array(
				'state'     => (string) get_post_meta( $post_id, self::META_STATE, true ),
				'closed_at' => (int) get_post_meta( $post_id, self::META_CLOSED_AT, true ),
				'note'      => (string) get_post_meta( $post_id, self::META_NOTE, true ),
			);
		}

		return array();
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
	 * The requests somebody closed, the one closed last on top, capped.
	 *
	 * Handled, declined and withdrawn alike: the Administrator Dashboard shows the last few so a
	 * manager can see what a colleague did this week, or what a school took back, not to reopen
	 * anything (a closed request has no transition, `transitions()` says so).
	 *
	 * Ordered by `META_CLOSED_AT`, because "this week" is about the closing. It used to ask
	 * for `modified` DESC, which reads as the same thing and is not: nothing in this class
	 * ever moves `post_modified` off `post_date`, so the list came back in the order the rows
	 * were raised and a request raised in January and handled today sat at the bottom of it,
	 * or off the cap entirely (deep check FADMN-3).
	 *
	 * Two queries rather than one, because one cannot order both halves. A row closed before
	 * 1.99.0 carries no stamp, and asking for the stamped and the unstamped rows together
	 * means an `OR` group - which is what the first attempt at this shipped. WordPress then
	 * joins the stamp's alias unconstrained, so for a row without one every meta row that
	 * post has reaches the group and `CAST( alias.meta_value AS SIGNED )` reads whichever of
	 * them MySQL hands back: a manager's note that opens with a long number would put a
	 * request closed last year on top of a card headed "closed last".
	 *
	 * Asked apart, each half is ordered by a term it really has. The stamped rows come first,
	 * by the stamp, with the clause ANDed at the top level so the alias is pinned to it. The
	 * legacy rows follow, by their post date, which is the only date they have. The second
	 * query runs only when the first has not filled the cap, so on a site whose closings all
	 * carry a stamp it costs nothing - and that is every closing since 1.99.0, so the legacy
	 * set only ever shrinks.
	 *
	 * @param int $limit Most rows to read, capped at QUEUE_MAX.
	 * @return int[] Post IDs.
	 */
	public static function closed_requests( $limit = 20 ) {
		$limit = (int) $limit > 0 ? min( (int) $limit, self::QUEUE_MAX ) : 20;
		$state = array(
			'key'     => self::META_STATE,
			'value'   => array( self::STATE_DONE, self::STATE_DECLINED, self::STATE_WITHDRAWN ),
			'compare' => 'IN',
		);

		$stamped = array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'        => self::POST_TYPE,
					'post_status'      => self::POST_STATUS,
					'numberposts'      => $limit,
					'fields'           => 'ids',
					'orderby'          => array(
						'closed' => 'DESC',
						'ID'     => 'DESC',
					),
					'suppress_filters' => false,
					'meta_query'       => array(
						'relation' => 'AND',
						$state,
						'closed'   => array(
							'key'     => self::META_CLOSED_AT,
							'compare' => 'EXISTS',
							'type'    => 'NUMERIC',
						),
					),
				)
			)
		);

		if ( count( $stamped ) >= $limit ) {
			return $stamped;
		}

		$legacy = array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'        => self::POST_TYPE,
					'post_status'      => self::POST_STATUS,
					'numberposts'      => $limit - count( $stamped ),
					'fields'           => 'ids',
					'orderby'          => array(
						'date' => 'DESC',
						'ID'   => 'DESC',
					),
					'suppress_filters' => false,
					'meta_query'       => array(
						'relation' => 'AND',
						$state,
						array(
							'key'     => self::META_CLOSED_AT,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			)
		);

		return array_merge( $stamped, $legacy );
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
			// When it was closed, or 0: an open row, and a row closed before 1.99.0, have no
			// such moment, and a list that prints one date has to be able to say which it is.
			'closed_at'        => (int) get_post_meta( $post_id, self::META_CLOSED_AT, true ),
			// Only an open row can be overdue. A closed one waited exactly as long as it
			// waited, and a queue that went on marking it would be arguing with its own
			// history in front of the person who answered it.
			'overdue'          => self::STATE_OPEN === $state && ( time() - $at ) > ( self::OVERDUE_DAYS * DAY_IN_SECONDS ),
			// The table an Administrator decides a details request on, read now (`change_rows()`):
			// empty for every other kind and for a details request that is closed.
			'changes'          => self::STATE_OPEN === $state && self::KIND_DETAILS === $kind ? self::change_rows( $post_id, $institution ) : array(),
		);
	}

	/**
	 * The rows of the table an Administrator decides a details request on: for each detail asked, what
	 * it is called, what the program has on record now, and what the school asks for.
	 *
	 * **"On record now" is read when this is called, from the index, and not from what the school was
	 * shown when it asked.** The program team can change a detail in Airtable while a request waits,
	 * and the sync brings it in; an Administrator who approved against the old value would overwrite
	 * the newer one unseen. So the column is the current value and the request's own `from` is not
	 * read at all here. Approve still writes the asked value: it is what the Administrator is shown
	 * beside it, and they have seen both.
	 *
	 * @param int    $post_id     The details request.
	 * @param string $institution Institutions record ID.
	 * @return array[] In the order of the details' table: `key`, `label`, `now` and `asked`, the two
	 *                 values as a person reads them (`WPCPM_Institution_Details::show()`).
	 */
	private static function change_rows( $post_id, $institution ) {
		$asked = self::changes_of( $post_id );
		$now   = WPCPM_Institution_Details::current( $institution );
		$rows  = array();

		foreach ( WPCPM_Institution_Details::fields() as $key => $spec ) {
			if ( ! isset( $asked[ $key ] ) ) {
				continue;
			}

			$rows[] = array(
				'key'   => $key,
				'label' => $spec['label'],
				'now'   => WPCPM_Institution_Details::show( $key, isset( $now[ $key ] ) ? $now[ $key ] : '' ),
				'asked' => WPCPM_Institution_Details::show( $key, $asked[ $key ]['to'] ),
			);
		}

		return $rows;
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

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

		// A school's details request is decided by its own two buttons, which write the details it asks
		// for. Closing it here as handled would end it without a word to the program's records, and
		// tell the school nothing.
		if ( self::KIND_DETAILS === (string) get_post_meta( (int) $post->ID, self::META_KIND, true ) ) {
			self::finish( 'request-state' );
		}

		$form = WPCPM_Typed_Text::box_form( self::TYPED_NOTE, $post->ID );

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		$state = WPCPM_Request::posted_key( 'state' );

		// Which button was pressed, matched against the two closing states. `open` is not one
		// of them: this handler closes rows and never reopens one.
		if ( self::STATE_DONE !== $state && self::STATE_DECLINED !== $state ) {
			self::finish( 'request-state' );
		}

		$typed = WPCPM_Request::posted_raw( 'wpcpm_request_note' );
		$note  = self::posted_note();

		// The note goes on the row and on its audit entry, the record somebody reads a year later:
		// one the cleaner would take words from is refused before either is written, in the words of
		// the button that was pressed, and the box gets back what was typed.
		if ( WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::finish( self::STATE_DONE === $state ? 'request-done-loss' : 'request-declined-loss' );
		}

		// Counted on what was typed, as the box counts it (`WPCPM_Typed_Text::box_length()`), and
		// refused rather than cut: past the loss check, the typing and the note hold the same words.
		if ( WPCPM_Typed_Text::box_length( $typed ) > self::MAX_NOTE ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::finish( self::STATE_DONE === $state ? 'request-done-long' : 'request-declined-long' );
		}

		$settled = self::settle(
			(int) $post->ID,
			$state,
			$note,
			get_current_user_id(),
			isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
			$typed
		);

		if ( is_wp_error( $settled ) ) {
			self::finish( 'wpcpm_request_state' === $settled->get_error_code() ? 'request-closed' : 'error' );
		}

		self::finish( self::STATE_DONE === $state ? 'request-done' : 'request-declined' );
	}

	/**
	 * An Administrator approves or declines a school's details request.
	 *
	 * Capability, then nonce, then the policy (spec 5.4), as `handle_resolve()` does, and then the
	 * request's own lock, with its state read again under it: that is the whole of the protection
	 * against two Administrators pressing Approve and Decline on one request together. The first holds
	 * the lock and closes it; the second finds it closed, or withdrawn, and does nothing, so there is
	 * one write to the base, one email and one closing row (`locked()`).
	 *
	 * **Approve** writes every asked value to the base in one update, then the index, through
	 * `WPCPM_Institution_Details::write()`, and closes the request as done; a base that refuses leaves
	 * the request open and the site as it was, with a sentence that says so. It tells the account that
	 * asked. **Decline** needs a note of `MIN_NOTE` to `MAX_NOTE` characters, which the school reads,
	 * writes nothing to the base, and tells the account that asked, with the note. A note that is
	 * refused is given back to its box. A request already closed is told so before its note is looked at:
	 * the sentence for its state is the answer to any note posted for it.
	 *
	 * The subject is the request's own institution meta, never a posted record, and the choice is the
	 * button that was pressed.
	 */
	public static function handle_details_decide() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( 'request' );

		check_admin_referer( self::ACTION_DETAILS_DECIDE . '_' . $post_id );

		$post = self::post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			self::finish( 'request-gone' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_DETAILS,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		// A request of another kind is closed by its own handler, which does not write details.
		if ( self::KIND_DETAILS !== (string) get_post_meta( (int) $post->ID, self::META_KIND, true ) ) {
			self::finish( 'request-state' );
		}

		$id     = (int) $post->ID;
		$form   = WPCPM_Typed_Text::box_form( self::TYPED_NOTE, $id );
		$choice = WPCPM_Request::posted_key( 'decision' );
		$actor  = get_current_user_id();
		$ground = isset( $decision['ground'] ) ? (string) $decision['ground'] : '';

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		if ( 'approve' === $choice ) {
			$status = self::locked(
				$id,
				static function () use ( $id, $actor, $ground ) {
					return self::approve_details( $id, $actor, $ground );
				}
			);

			self::finish( $status );
		}

		if ( 'decline' !== $choice ) {
			self::finish( 'request-state' );
		}

		// The state before the note: a request already closed, by the school or by another Administrator,
		// is told so in the sentence for its state, and not that its note is too short. This is the early
		// answer; the state is read again under the lock, which is the one that decides.
		$closed = self::decided_status( $id );

		if ( '' !== $closed ) {
			self::finish( $closed );
		}

		$typed = WPCPM_Request::posted_raw( 'wpcpm_request_note' );
		$note  = self::posted_note();

		// Both refusals ahead of the lock, because they need nothing but the posted string, and the
		// box gets back what was typed. A note the cleaner would take words from would reach the school
		// missing them without a word said.
		if ( WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::finish( 'details-declined-loss' );
		}

		// Counted on what was typed, as the box counts it (`WPCPM_Typed_Text::box_length()`), which
		// trims the ends, so a note of spaces is no note.
		$length = WPCPM_Typed_Text::box_length( $typed );

		if ( $length < self::MIN_NOTE || $length > self::MAX_NOTE ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::finish( 'details-note' );
		}

		$status = self::locked(
			$id,
			static function () use ( $id, $note, $typed, $actor, $ground ) {
				return self::decline_details( $id, $note, $typed, $actor, $ground );
			}
		);

		// Another request holds the lock: nothing was done, and the note is the Administrator's to send
		// again.
		if ( 'details-busy' === $status ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
		}

		self::finish( $status );
	}

	/**
	 * A school withdraws its details request.
	 *
	 * The nonce, then the policy, and no capability: this is the school's. `ACT_EDIT_DETAILS` on the
	 * request's own institution is every account that acts for the school, and an Administrator
	 * viewing it. A request that is not a details request, one that is not there and one of another
	 * school are refused alike, with the policy's one refusal. It closes the request as withdrawn under
	 * its lock, with its state read again under it (an Administrator may be deciding it at that
	 * moment), writes the history and emails "Who is emailed about institutions". The school's Withdraw it
	 * comes back to the details card (`bounce_details()`).
	 */
	public static function handle_details_withdraw() {
		$post_id = WPCPM_Request::posted_id( 'request' );

		check_admin_referer( self::ACTION_DETAILS_WITHDRAW . '_' . $post_id );

		$post = self::post( $post_id );

		if ( ! $post instanceof WP_Post || self::KIND_DETAILS !== (string) get_post_meta( (int) $post->ID, self::META_KIND, true ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_DETAILS,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$id     = (int) $post->ID;
		$actor  = get_current_user_id();
		$ground = isset( $decision['ground'] ) ? (string) $decision['ground'] : '';
		$status = self::locked(
			$id,
			static function () use ( $id, $actor, $ground ) {
				return self::withdraw_details( $id, $actor, $ground );
			}
		);

		self::bounce_details( 'details-busy' === $status ? 'busy' : $status );
	}

	/**
	 * Do one decision on a request under the request's lock.
	 *
	 * Taken once for the work and let go in a `finally`, so an exception lets go too, and the work's
	 * answer returned for the caller to leave with after the lock is let go: the redirect that ends
	 * a request runs no `finally`, so it never happens inside the `try`. Once the lock is held this
	 * request's copy of the row's meta is dropped, so the work reads the state as it stands: WordPress
	 * keeps every meta row it has read for the rest of the request, a decision that read the kind before
	 * the lock has read them all, and a state checked under the lock would otherwise be the one from
	 * before it, with the other Administrator's closing not seen.
	 *
	 * @param int      $post_id The request.
	 * @param callable $work    Answers the outcome: a key of `messages()`, or of `school_messages()`
	 *                          for a withdrawal.
	 * @return string The work's outcome, or `details-busy` while another request holds the lock.
	 */
	private static function locked( $post_id, callable $work ) {
		$name  = self::lock_name( $post_id );
		$token = WPCPM_Lock::take( $name, self::LOCK_TTL );

		if ( '' === $token ) {
			return 'details-busy';
		}

		try {
			wp_cache_delete( (int) $post_id, 'post_meta' );

			$status = (string) call_user_func( $work );
		} finally {
			WPCPM_Lock::release( $name, $token );
		}

		return $status;
	}

	/**
	 * Approve a details request, under its lock: write what was asked, close it, tell the school.
	 *
	 * The base is written first and the request closed after, so a refusal of the base leaves both as
	 * they were. Should the closing fail once the base has taken the values (the request vanished from
	 * under the lock, which nothing here does), the request is still open and a second Approve writes
	 * the same values again and closes it: the write is the asked value and not a change from one.
	 *
	 * @param int    $post_id  The request.
	 * @param int    $actor_id The Administrator.
	 * @param string $ground   The decision's ground.
	 * @return string The outcome, a key of `messages()`.
	 */
	private static function approve_details( $post_id, $actor_id, $ground ) {
		$refused = self::decided_status( $post_id );

		if ( '' !== $refused ) {
			return $refused;
		}

		$institution = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
		$changes     = self::changes_of( $post_id );

		if ( array() === $changes ) {
			return 'details-refused';
		}

		// What the program has on record at the moment of the write, for the email: the asked values
		// are what is written, and the school is told what they replaced, not what it was told when
		// it asked.
		$before  = WPCPM_Institution_Details::current( $institution );
		$written = WPCPM_Institution_Details::write( $institution, $changes, $actor_id, $ground );

		if ( is_wp_error( $written ) ) {
			return self::refusal_status( $written );
		}

		$settled = self::settle( $post_id, self::STATE_DONE, '', $actor_id, $ground );

		if ( is_wp_error( $settled ) ) {
			return 'wpcpm_request_state' === $settled->get_error_code() ? 'request-closed' : 'request-gone';
		}

		$now = array();

		foreach ( $changes as $key => $change ) {
			$now[ $key ] = array(
				'from' => isset( $before[ $key ] ) ? $before[ $key ] : $change['from'],
				'to'   => $change['to'],
			);
		}

		self::mail_account(
			(int) get_post_meta( $post_id, self::META_ACTOR, true ),
			self::MAIL_APPROVED,
			static function () use ( $now ) {
				return array( 'changes' => WPCPM_Institution_Details::lines( $now, false ) );
			}
		);

		return 'details-approved';
	}

	/**
	 * Decline a details request, under its lock: close it with the note, tell the school.
	 *
	 * @param int    $post_id  The request.
	 * @param string $note     The note, as the cleaner left it.
	 * @param string $typed    What was typed for it, which its limit is counted on.
	 * @param int    $actor_id The Administrator.
	 * @param string $ground   The decision's ground.
	 * @return string The outcome, a key of `messages()`.
	 */
	private static function decline_details( $post_id, $note, $typed, $actor_id, $ground ) {
		$refused = self::decided_status( $post_id );

		if ( '' !== $refused ) {
			return $refused;
		}

		$settled = self::settle( $post_id, self::STATE_DECLINED, $note, $actor_id, $ground, $typed );

		if ( is_wp_error( $settled ) ) {
			return 'wpcpm_request_state' === $settled->get_error_code() ? 'request-closed' : 'request-gone';
		}

		// The note as the row keeps it, which is the note as the cleaner left it, cut at the limit.
		$kept = (string) get_post_meta( $post_id, self::META_NOTE, true );

		self::mail_account(
			(int) get_post_meta( $post_id, self::META_ACTOR, true ),
			self::MAIL_DECLINED,
			static function () use ( $kept ) {
				return array(
					'note'           => $kept,
					'dashboard_link' => WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_INSTITUTION ),
				);
			}
		);

		return 'details-declined';
	}

	/**
	 * Withdraw a details request, under its lock: close it, tell the people the setting names.
	 *
	 * A request already withdrawn, by another account of the school or in a second tab, is told so in
	 * its own sentence: nobody decided it, so the sentence for a decided request would be untrue.
	 *
	 * @param int    $post_id  The request.
	 * @param int    $actor_id The member (or the Administrator) who withdrew it.
	 * @param string $ground   The decision's ground.
	 * @return string The outcome, a key of `school_messages()`.
	 */
	private static function withdraw_details( $post_id, $actor_id, $ground ) {
		$state = (string) get_post_meta( $post_id, self::META_STATE, true );

		if ( self::STATE_WITHDRAWN === $state ) {
			return 'withdrawn-already';
		}

		if ( self::STATE_OPEN !== $state ) {
			return 'closed';
		}

		if ( is_wp_error( self::settle( $post_id, self::STATE_WITHDRAWN, '', $actor_id, $ground ) ) ) {
			return 'closed';
		}

		$institution = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		self::mail_managers(
			self::MAIL_WITHDRAWN,
			static function () use ( $institution, $actor_id ) {
				return array(
					'institution_name' => self::institution_name( $institution ),
					'actor_name'       => self::person_name( $actor_id ),
				);
			}
		);

		return 'withdrawn';
	}

	/**
	 * Why a decision on a request read under its lock may not go on, or '' when it may.
	 *
	 * Only an open request is decided. A withdrawn one is told apart from one an Administrator closed,
	 * because it is a withdrawal that is in the way and the sentence says so, and by whom: the school
	 * ordinarily, an Administrator when the ground the request was closed on was the manager's
	 * (`META_CLOSED_GROUND`; a request withdrawn before it was kept reads as the school's).
	 *
	 * @param int $post_id The request.
	 * @return string A key of `messages()`, or ''.
	 */
	private static function decided_status( $post_id ) {
		$state = (string) get_post_meta( $post_id, self::META_STATE, true );

		if ( self::STATE_OPEN === $state ) {
			return '';
		}

		if ( self::STATE_WITHDRAWN !== $state ) {
			return 'request-closed';
		}

		return WPCPM_Institution_Audit::GROUND_MANAGER === (string) get_post_meta( $post_id, self::META_CLOSED_GROUND, true ) ? 'details-withdrawn-admin' : 'details-withdrawn';
	}

	/**
	 * What an Administrator is told when the details class would not write a request.
	 *
	 * Airtable's own refusal, in whatever shape it comes, and an answer of nothing written are one
	 * sentence: nothing was changed here either, and the request stays open. The details class's
	 * refusals of the institution and of a stored value are told apart because what to do about them
	 * is not the same: a school the program records do not hold waits for the sync, and a value that no
	 * longer meets its detail's rules is declined with a note.
	 *
	 * @param WP_Error $error What `WPCPM_Institution_Details::write()` answered.
	 * @return string A key of `messages()`.
	 */
	private static function refusal_status( WP_Error $error ) {
		switch ( $error->get_error_code() ) {
			case 'wpcpm_details_index':
				return 'details-index';

			case 'wpcpm_details_record':
			case 'wpcpm_details_ground':
			case 'wpcpm_details_change':
			case 'wpcpm_details_value':
				return 'details-refused';
		}

		return 'details-airtable';
	}

	/*
	 * The Administrator's surface
	 * --------------------------------------------------------------------
	 */

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

		// A school's details request is decided by Approve and Decline, which the Administrator
		// Dashboard's card draws beside the table of what is asked (`ACTION_DETAILS_DECIDE`). The two
		// buttons below close a request as handled, which would end it without writing a detail.
		if ( self::KIND_DETAILS === $facts['kind'] ) {
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

		// A decision refused for its note comes back with the note as it was typed, in this
		// request's box only.
		$kept = WPCPM_Typed_Text::kept_box( WPCPM_Typed_Text::box_form( self::TYPED_NOTE, $post->ID ), 'note' );

		printf(
			'<label class="screen-reader-text" for="wpcpm-request-note-%1$d">%2$s</label>',
			(int) $post->ID,
			esc_html( self::note_label() )
		);
		printf(
			'<textarea id="wpcpm-request-note-%1$d" name="wpcpm_request_note" rows="2" maxlength="%2$d" placeholder="%3$s">%4$s</textarea>',
			(int) $post->ID,
			(int) self::MAX_NOTE,
			esc_attr__( 'What you did about it, for the log. Optional, and not sent to the institution.', 'wpcredits-program-manager' ),
			// The parser drops one line feed right after the opening tag, so a kept note that begins
			// with a line break keeps it.
			null === $kept ? '' : "\n" . esc_textarea( $kept )
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
			'request-done'            => array( 'success', __( 'That request is closed as handled.', 'wpcredits-program-manager' ) ),
			'request-declined'        => array( 'success', __( 'That request is declined and closed.', 'wpcredits-program-manager' ) ),
			'request-closed'          => array( 'info', __( 'Nothing was changed: that request had already been closed.', 'wpcredits-program-manager' ) ),
			'request-gone'            => array( 'error', __( 'That request is not here any more.', 'wpcredits-program-manager' ) ),
			'request-state'           => array( 'error', __( 'That is not something a request can be closed as.', 'wpcredits-program-manager' ) ),
			// The two buttons each refuse in their own words, a whole sentence apiece.
			'request-done-loss'       => array( 'error', WPCPM_Typed_Text::loss_message( self::note_label(), 'handle' ) ),
			'request-declined-loss'   => array( 'error', WPCPM_Typed_Text::loss_message( self::note_label(), 'decline' ) ),
			'request-done-long'       => array(
				'error',
				sprintf(
					/* translators: 1: the name of the box, such as What you did about it, 2: the longest note allowed, in characters. */
					__( 'Nothing was marked as handled. What you typed in "%1$s" is longer than %2$s characters: shorten it and mark it as handled again.', 'wpcredits-program-manager' ),
					self::note_label(),
					number_format_i18n( self::MAX_NOTE )
				),
			),
			'request-declined-long'   => array(
				'error',
				sprintf(
					/* translators: 1: the name of the box, such as What you did about it, 2: the longest note allowed, in characters. */
					__( 'Nothing was declined. What you typed in "%1$s" is longer than %2$s characters: shorten it and decline it again.', 'wpcredits-program-manager' ),
					self::note_label(),
					number_format_i18n( self::MAX_NOTE )
				),
			),
			// A school's details request: the Approve, Decline and Withdraw outcomes an Administrator reads.
			'details-approved'        => array( 'success', __( 'The records are updated and the school is told.', 'wpcredits-program-manager' ) ),
			'details-declined'        => array( 'success', __( 'The request is declined and the school is told.', 'wpcredits-program-manager' ) ),
			'details-airtable'        => array( 'error', __( 'Airtable could not be updated, so nothing was changed here either. The request stays open.', 'wpcredits-program-manager' ) ),
			'details-index'           => array( 'error', __( 'This institution is not in the program records yet, so nothing was changed. The request stays open.', 'wpcredits-program-manager' ) ),
			'details-refused'         => array( 'error', __( 'A value in this request no longer meets the rules for its detail, so nothing was changed. The request stays open: decline it with a note.', 'wpcredits-program-manager' ) ),
			// The plan's sentence names the limits in digits, so the whole of it is one string for a translator.
			'details-note'            => array( 'error', __( 'A note of 20 to 2000 characters is needed to decline: the school reads it.', 'wpcredits-program-manager' ) ),
			'details-declined-loss'   => array( 'error', WPCPM_Typed_Text::loss_message( self::details_note_label(), 'decline' ) ),
			'details-withdrawn'       => array( 'info', __( 'Nothing was changed: the school withdrew this request.', 'wpcredits-program-manager' ) ),
			'details-withdrawn-admin' => array( 'info', __( 'Nothing was changed: an Administrator withdrew this request.', 'wpcredits-program-manager' ) ),
			'details-busy'            => array( 'error', __( 'Someone else is deciding this request at this moment, so nothing was done. Try again in a moment.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * What the school's side of a details request says of a withdrawal, in the words the school reads.
	 *
	 * Flashed on `FLASH_DETAILS` by `handle_details_withdraw()` as `array( 'status' => <a key of this map> )`,
	 * and printed by the card that drew the button. Its own map and not `messages()`: those are the
	 * Administrator's, on a screen the school never sees, and a sentence one audience reads is not
	 * worded for the other.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to type and message.
	 */
	public static function school_messages() {
		return array(
			'withdrawn'         => array( 'success', __( 'The request is withdrawn.', 'wpcredits-program-manager' ) ),
			'withdrawn-already' => array( 'info', __( 'Nothing was changed: that request had already been withdrawn.', 'wpcredits-program-manager' ) ),
			'closed'            => array( 'info', __( 'Nothing was changed: an Administrator had already decided that request.', 'wpcredits-program-manager' ) ),
			'busy'              => array( 'error', __( 'Someone else is deciding this request at this moment, so nothing was done. Try again in a moment.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The closing note's box by its name, as its form labels it, so the form and the sentences that
	 * refuse a note name it alike.
	 *
	 * @return string
	 */
	private static function note_label() {
		/* translators: The name of the box an Administrator types what they did about a request from an institution in. */
		return __( 'What you did about it', 'wpcredits-program-manager' );
	}

	/**
	 * The details decline's note box by its name, as the Administrator Dashboard's card labels it, so the
	 * card and the sentence that refuses a note name it alike.
	 *
	 * @return string
	 */
	private static function details_note_label() {
		/* translators: The name of the box an Administrator types the note a school reads when its request to correct its details is declined in. */
		return __( 'A note for the school', 'wpcredits-program-manager' );
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
	 * Record the manager's outcome and return to the queue, or to the Administrator Dashboard
	 * when the decision was posted from there.
	 *
	 * **This does not return**: every call ends the request, which is why the refusals above
	 * read as one line each and not as an early return with a branch around it. The channel is the
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
	 * Record the school's outcome of a details request and return to where the press was made.
	 *
	 * Reached from one place, the end of `handle_details_withdraw()`, and **it does not return**: it
	 * redirects and exits, so nothing after the call runs. The outcome is on the details' own
	 * channel (`FLASH_DETAILS`), so no other card takes it; the words are in
	 * `school_messages()`. The destination is the page that was posted from, which `wp_safe_redirect()`
	 * keeps on this site, and the Institution Dashboard when there is none. A press that names the
	 * details card (`RETURN_FIELD`) lands on it, as the dashboard's other forms land on their cards, so a
	 * withdrawal is read where it was made and not at the top of the page; the card's anchor is the one
	 * place followed, and any fragment the page already carried gives way to it.
	 *
	 * @param string $status Outcome slug, one of the keys `school_messages()` knows.
	 */
	private static function bounce_details( $status ) {
		WPCPM_Flash::set( self::FLASH_DETAILS, array( 'status' => (string) $status ) );

		$back = wp_get_referer();
		$url  = $back ? (string) $back : self::dashboard_url();

		if ( self::DETAILS_ANCHOR === WPCPM_Request::posted_key( self::RETURN_FIELD ) ) {
			$url = (string) preg_replace( '/#.*$/', '', $url ) . '#' . self::DETAILS_ANCHOR;
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Email the people "Who is emailed about institutions" names, one of the details emails.
	 *
	 * Built in each recipient's language, where the values are worked out. The site's name is the
	 * mail layer's, added here, with the values a caller gives. Public since the school's details card
	 * sends the "saved" email the same way (`WPCPM_Institution_Details_Card`), with this class's own
	 * names for the institution and the person (`institution_name()`, `person_name()`).
	 *
	 * @param string   $id     The email's id in the template registry.
	 * @param callable $values Answers the email's placeholders, by name, except the site's name.
	 * @return int How many were handed off.
	 */
	public static function mail_managers( $id, callable $values ) {
		$build = static function () use ( $id, $values ) {
			return WPCPM_Mail_Templates::mail( $id, array_merge( array( 'site_name' => WPCPM_Mail::site_name() ), (array) call_user_func( $values ) ) );
		};

		return (int) WPCPM_Institutions::notify_managers( $id, $build );
	}

	/**
	 * Email the account that asked, one of the details emails.
	 *
	 * An account that is gone, or has no address, is told nothing: the school sees the outcome on its
	 * Institution Dashboard all the same.
	 *
	 * @param int      $user_id The account.
	 * @param string   $id      The email's id in the template registry.
	 * @param callable $values  Answers the email's placeholders, by name, except the site's name.
	 * @return bool Whether it was handed off.
	 */
	private static function mail_account( $user_id, $id, callable $values ) {
		$user = absint( $user_id ) > 0 ? get_user_by( 'id', absint( $user_id ) ) : false;

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		$build = static function () use ( $id, $values ) {
			return WPCPM_Mail_Templates::mail( $id, array_merge( array( 'site_name' => WPCPM_Mail::site_name() ), (array) call_user_func( $values ) ) );
		};

		return (bool) WPCPM_Mail::send( $user, $id, $build );
	}

	/**
	 * An institution's name for an email: the index's, or its record ID while the index has not read it.
	 *
	 * @param string $institution Institutions record ID.
	 * @return string
	 */
	public static function institution_name( $institution ) {
		$row  = WPCPM_Institutions_Index::row( $institution );
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : (string) $institution;
	}

	/**
	 * Who did something, for an email that names them: their name, or "A member of the institution"
	 * while their account is gone or holds no name.
	 *
	 * @param int $user_id The account.
	 * @return string
	 */
	public static function person_name( $user_id ) {
		$user = absint( $user_id ) > 0 ? get_user_by( 'id', absint( $user_id ) ) : false;
		$name = $user instanceof WP_User ? trim( (string) $user->display_name ) : '';

		/* translators: Who asked to correct an institution's details, or took the request back, when their account is gone or holds no name: "<this> asks to correct <institution>'s details:". */
		return '' !== $name ? $name : __( 'A member of the institution', 'wpcredits-program-manager' );
	}

	/**
	 * Where an Administrator decides a details request: the Administrator Dashboard's requests card
	 * while its page is published, else the Institutions screen's queue, which lists it.
	 *
	 * @return string
	 */
	private static function requests_link() {
		$page = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_ADMIN );

		return '' !== $page ? $page . '#wpcpm-requests' : admin_url( 'admin.php?page=wpcpm-institutions' );
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
