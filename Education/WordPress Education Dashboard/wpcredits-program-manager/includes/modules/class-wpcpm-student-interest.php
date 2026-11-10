<?php
/**
 * Students module - a student's interest in the program, as the site keeps it (1.123.64).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per interest a student sent through the student interest form: a student whose
 * institution is not in the program yet says they would like to take part, and names a
 * representative of the institution for the program to write to.
 *
 * The record holds a stranger's answers and a second person's name and address, and is reached only
 * through the Students screen's Interests tab, which asks the capability first; no generic screen,
 * no REST route and no query variable reaches it. It is modeled on the student registration's
 * record: the answers are site fields keyed by the form's question keys, written as a slashed copy
 * so a typed backslash survives core's unslashing.
 *
 * **Nothing here reaches Airtable**, and nothing is made from a record: no account, no row, no
 * institution. An Administrator reads an interest and follows it up by hand.
 *
 * **The states.** `new` (stored, for an Administrator to read), `held` (it looks like spam and is
 * kept for a person to judge), `spam`, `contacted` (an Administrator wrote to the representative)
 * and `closed` (nothing more to do). `can_move()` says which state leads to which.
 *
 * **Retention** (`purge()`, once a day): a `spam` interest is deleted `SPAM_DAYS` after it was
 * filed so, and a `closed` one `CLOSED_DAYS` after it was closed. The others are kept until an
 * Administrator moves them.
 *
 * **The privacy tools.** An interest holds two addresses, the student's and the representative's,
 * each kept beside the answers as its hash; WordPress's Export Personal Data and Erase Personal
 * Data tools find an interest by either.
 *
 * Nothing here sends anything or draws anything: the form and the Interests tab read and write
 * through this class.
 */
final class WPCPM_Student_Interest {

	/**
	 * The post type. Seventeen characters: `register_post_type()` refuses a name longer than twenty,
	 * and bin/test-roles.php measures every post type the plugin declares.
	 */
	const POST_TYPE = 'wpcpm_student_int';

	const STATE_NEW       = 'new';
	const STATE_HELD      = 'held';
	const STATE_SPAM      = 'spam';
	const STATE_CONTACTED = 'contacted';
	const STATE_CLOSED    = 'closed';

	/** Post meta: the cleaned answers, keyed by the form's question keys. */
	const META_FIELDS = '_wpcpm_sint_fields';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_sint_state';

	/** Post meta: the short reference an Administrator can quote, such as `SINT-2026-0503`. */
	const META_REFERENCE = '_wpcpm_sint_reference';

	/**
	 * Post meta: the consent evidence, as `WPCPM_Form_Guard::consent_evidence()` records it for the
	 * privacy sentence, with the sentence about the representative beside it (`told`).
	 */
	const META_CONSENT = '_wpcpm_sint_consent';

	/** Post meta: what the form's checks raised, a list of slugs. */
	const META_SIGNALS = '_wpcpm_sint_signals';

	/** Post meta: `wp_hash()` of the student's lowercased address, for duplicates and the privacy tools. */
	const META_EMAIL = '_wpcpm_sint_email';

	/** Post meta: `wp_hash()` of the representative's lowercased address, for the privacy tools. */
	const META_CONTACT = '_wpcpm_sint_contact';

	/** The student's address as the privacy tools look it up: trimmed, in lower case, unsalted. */
	const META_ADDRESS = '_wpcpm_sint_address';

	/** The representative's address, kept the same way. */
	const META_ADDRESS_CONTACT = '_wpcpm_sint_address_rep';

	/** Post meta: the institution's name, trimmed and lowercased, which a duplicate is found by. */
	const META_INSTITUTION = '_wpcpm_sint_institution';

	/** Post meta: when the interest was stored, a Unix time. */
	const META_CREATED = '_wpcpm_sint_created';

	/** Post meta: when the state last changed, a Unix time; absent until it does. */
	const META_CHANGED = '_wpcpm_sint_changed';

	/** Post meta: the account that last changed the state; absent until one does. */
	const META_CHANGED_BY = '_wpcpm_sint_changed_by';

	/** The signal a second interest from one student for one institution carries. */
	const SIGNAL_DUPLICATE = 'duplicate';

	/** How many days back a second interest from the same student for the same institution is a duplicate. */
	const DUPLICATE_DAYS = 30;

	/** How many days a spam interest is kept. */
	const SPAM_DAYS = 30;

	/** How many days a closed interest is kept. */
	const CLOSED_DAYS = 365;

	/** The daily job that deletes what retention says to. */
	const CRON_DAILY = 'wpcpm_student_interests_daily';

	/** The group the privacy tools file an interest under, and its exporter's and eraser's key. */
	const PRIVACY_GROUP = 'wpcpm-student-interests';

	/** How many interests one page of the privacy tools handles. */
	const PRIVACY_PAGE = 50;

	/** How many interests one daily run deletes at most; the next run takes up the rest. */
	const PURGE_MAX = 500;

	/** What one interest's lock is named by, before its ID (`WPCPM_Lock`). */
	const LOCK_PREFIX = 'wpcpm_sint_lock_';

	/** How long that lock holds at most, in seconds: a state change or one deletion. */
	const LOCK_SECONDS = 30;

	/**
	 * Hooks: the post type, the daily job and the privacy tools' two entries.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
		add_action( self::CRON_DAILY, array( __CLASS__, 'run_daily' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Register the post type: invisible everywhere, since a row holds two people's names and
	 * addresses and free text, and the only way to one is the Students screen, which asks the
	 * capability first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Student interests', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Student interest', 'wpcredits-program-manager' ),
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
				'supports'            => array( 'title' ),
				// A capability type no role is granted, so no generic screen reaches a row.
				'capability_type'     => array( 'wpcpm_student_int', 'wpcpm_student_ints' ),
				'map_meta_cap'        => true,
				// Not in WordPress's own export of all content: that tool asks for the `export`
				// capability alone, and an interest holds two people's details and the consent.
				'can_export'          => false,
			)
		);
	}

	/**
	 * Put the daily job on the clock, ten minutes out, when it is not there.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'daily', self::CRON_DAILY );
		}
	}

	/**
	 * Take the daily job off the clock.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
	}

	/**
	 * The daily job: delete what retention says to.
	 */
	public static function run_daily() {
		self::purge();
	}

	/**
	 * The five states, in the order the Interests tab lists its views.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array(
			self::STATE_NEW,
			self::STATE_HELD,
			self::STATE_CONTACTED,
			self::STATE_CLOSED,
			self::STATE_SPAM,
		);
	}

	/**
	 * Whether an interest in one state may be moved to another: contacted from new or held, closed
	 * from new, held or contacted, spam from new or held, and back to new from held, spam or
	 * closed. A state never leads to itself.
	 *
	 * @param string $from The state the interest is in.
	 * @param string $to   The state asked for.
	 * @return bool
	 */
	public static function can_move( $from, $to ) {
		$leads = array(
			self::STATE_CONTACTED => array( self::STATE_NEW, self::STATE_HELD ),
			self::STATE_CLOSED    => array( self::STATE_NEW, self::STATE_HELD, self::STATE_CONTACTED ),
			self::STATE_SPAM      => array( self::STATE_NEW, self::STATE_HELD ),
			self::STATE_NEW       => array( self::STATE_HELD, self::STATE_SPAM, self::STATE_CLOSED ),
		);

		return isset( $leads[ (string) $to ] ) && in_array( (string) $from, $leads[ (string) $to ], true );
	}

	/**
	 * Whether an interest in a state may be deleted by hand: a spam or a closed one alone.
	 *
	 * @param string $state The state the interest is in.
	 * @return bool
	 */
	public static function can_delete( $state ) {
		return in_array( (string) $state, array( self::STATE_SPAM, self::STATE_CLOSED ), true );
	}

	/**
	 * Store one interest and answer with its ID, or 0 when WordPress refused the row.
	 *
	 * The answers and the consent are written as a slashed copy, as core unslashes what it is handed,
	 * so a backslash a student typed is kept. The two addresses are kept beside the answers as their
	 * hashes, and the institution's name in lower case, for the duplicate check and the privacy tools.
	 *
	 * @param array  $fields  The cleaned answers, keyed by question key.
	 * @param string $state   `new`, `held` or `spam`; anything else is `new`.
	 * @param array  $signals What the checks raised.
	 * @param array  $consent The consent evidence.
	 * @return int
	 */
	public static function store( array $fields, $state, array $signals, array $consent ) {
		$state = in_array( $state, array( self::STATE_HELD, self::STATE_SPAM ), true ) ? $state : self::STATE_NEW;
		$name  = isset( $fields['name'] ) && is_scalar( $fields['name'] ) ? trim( (string) $fields['name'] ) : '';

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					// Nobody: the student has no account, and a row authored by the visitor's session
					// would read on every screen as though someone made it.
					'post_author' => 0,
					'post_title'  => '' !== $name ? $name : __( 'Interest with no name', 'wpcredits-program-manager' ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;

		// The answers, the state and the consent are the interest: a row without one of them is not
		// one a person can follow up, or that says what was agreed to. A write WordPress refuses is
		// read as the whole interest refused, and the row made a moment ago is taken back, so the
		// form says it could not be stored and keeps the answers, where a thank-you would lose them.
		$kept = array(
			update_post_meta( $post_id, self::META_FIELDS, wp_slash( $fields ) ),
			update_post_meta( $post_id, self::META_STATE, $state ),
			update_post_meta( $post_id, self::META_CONSENT, wp_slash( $consent ) ),
			// The two addresses the privacy tools find the interest by: an interest they cannot
			// find is one a person can never be handed, or have erased.
			update_post_meta( $post_id, self::META_ADDRESS, wp_slash( self::address_key( self::answer( $fields, 'email' ) ) ) ),
			update_post_meta( $post_id, self::META_ADDRESS_CONTACT, wp_slash( self::address_key( self::answer( $fields, 'contact_email' ) ) ) ),
		);

		if ( in_array( false, $kept, true ) ) {
			wp_delete_post( $post_id, true );

			return 0;
		}

		update_post_meta( $post_id, self::META_REFERENCE, self::reference( $post_id ) );
		update_post_meta( $post_id, self::META_SIGNALS, array_values( array_unique( array_map( 'strval', $signals ) ) ) );
		update_post_meta( $post_id, self::META_EMAIL, self::email_hash( self::answer( $fields, 'email' ) ) );
		update_post_meta( $post_id, self::META_CONTACT, self::email_hash( self::answer( $fields, 'contact_email' ) ) );
		update_post_meta( $post_id, self::META_INSTITUTION, wp_slash( self::institution_key( self::answer( $fields, 'institution' ) ) ) );
		update_post_meta( $post_id, self::META_CREATED, time() );

		return $post_id;
	}

	/**
	 * One answer of a set as text, or '' for none and for one that is no text.
	 *
	 * @param array  $fields The answers.
	 * @param string $key    The question's key.
	 * @return string
	 */
	public static function answer( array $fields, $key ) {
		return isset( $fields[ $key ] ) && is_scalar( $fields[ $key ] ) ? trim( (string) $fields[ $key ] ) : '';
	}

	/**
	 * The hash an address is compared by: trimmed and lowercased, so `Ines@Example.test` and
	 * `ines@example.test` are one person, and '' for no address.
	 *
	 * @param string $email An address.
	 * @return string
	 */
	public static function email_hash( $email ) {
		$email = trim( (string) $email );

		return '' === $email ? '' : wp_hash( mb_strtolower( $email ) );
	}

	/**
	 * An institution's name as two interests are compared by it: its white space folded, trimmed and
	 * in lower case.
	 *
	 * @param string $name The name as typed.
	 * @return string
	 */
	public static function institution_key( $name ) {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', (string) $name ) ) );
	}

	/**
	 * The reference an interest goes by: the year it arrived and its post ID, which is unique and
	 * stable already. It is no secret and no key to anything.
	 *
	 * @param int $post_id The interest.
	 * @return string
	 */
	public static function reference( $post_id ) {
		$post = get_post( (int) $post_id );
		$year = $post instanceof WP_Post && '' !== (string) $post->post_date ? substr( (string) $post->post_date, 0, 4 ) : wp_date( 'Y' );

		return sprintf( 'SINT-%1$s-%2$04d', $year, (int) $post_id );
	}

	/**
	 * One interest's reference, the stored one first.
	 *
	 * @param WP_Post $post The interest.
	 * @return string
	 */
	public static function reference_of( WP_Post $post ) {
		$stored = (string) get_post_meta( (int) $post->ID, self::META_REFERENCE, true );

		return '' !== $stored ? $stored : self::reference( (int) $post->ID );
	}

	/**
	 * An interest by ID, or null when the post is not one.
	 *
	 * @param int $post_id A post ID.
	 * @return WP_Post|null
	 */
	public static function interest( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * An interest's state.
	 *
	 * @param WP_Post $post The interest.
	 * @return string
	 */
	public static function state_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_STATE, true );
	}

	/**
	 * The answers as stored, keyed by question key.
	 *
	 * @param WP_Post $post The interest.
	 * @return array
	 */
	public static function fields_of( WP_Post $post ) {
		$fields = get_post_meta( (int) $post->ID, self::META_FIELDS, true );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * The consent evidence.
	 *
	 * @param WP_Post $post The interest.
	 * @return array
	 */
	public static function consent_of( WP_Post $post ) {
		$consent = get_post_meta( (int) $post->ID, self::META_CONSENT, true );

		return is_array( $consent ) ? $consent : array();
	}

	/**
	 * What the form's checks raised.
	 *
	 * @param WP_Post $post The interest.
	 * @return string[]
	 */
	public static function signals_of( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_SIGNALS, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$filled = static function ( $value ) {
			return '' !== (string) $value;
		};

		return array_values( array_unique( array_filter( array_map( 'strval', $stored ), $filled ) ) );
	}

	/**
	 * Add one signal to an interest already stored: the few that are only known once the row
	 * exists, such as an acknowledgement the day's limit on emails kept back.
	 *
	 * @param int    $post_id The interest.
	 * @param string $signal  The signal's slug.
	 */
	public static function add_signal( $post_id, $signal ) {
		$post = self::interest( $post_id );

		if ( null === $post || '' === (string) $signal ) {
			return;
		}

		update_post_meta( (int) $post_id, self::META_SIGNALS, array_values( array_unique( array_merge( self::signals_of( $post ), array( (string) $signal ) ) ) ) );
	}

	/**
	 * When an interest was stored, a Unix time: the stored one first, the post's own otherwise.
	 *
	 * @param WP_Post $post The interest.
	 * @return int
	 */
	public static function created_of( WP_Post $post ) {
		$created = (int) get_post_meta( (int) $post->ID, self::META_CREATED, true );

		return $created > 0 ? $created : (int) get_post_time( 'U', true, $post );
	}

	/**
	 * Who last changed an interest's state and when, each 0 until somebody does.
	 *
	 * @param WP_Post $post The interest.
	 * @return array{by: int, at: int}
	 */
	public static function changed_of( WP_Post $post ) {
		return array(
			'by' => (int) get_post_meta( (int) $post->ID, self::META_CHANGED_BY, true ),
			'at' => (int) get_post_meta( (int) $post->ID, self::META_CHANGED, true ),
		);
	}

	/**
	 * Whether the same student already sent an interest for the same institution within the last
	 * `DUPLICATE_DAYS` days, whatever became of it. The new one is stored all the same, marked
	 * `duplicate`: nothing is merged and nothing refused, since a stranger sending first with a
	 * student's address must not be able to suppress the student's own.
	 *
	 * @param string $email       The student's address.
	 * @param string $institution The institution's name, as typed.
	 * @param int    $now         The time to count from, or 0 for now.
	 * @return bool
	 */
	public static function has_recent( $email, $institution, $now = 0 ) {
		$hash = self::email_hash( $email );
		$key  = self::institution_key( $institution );
		$now  = $now > 0 ? (int) $now : time();

		if ( '' === $hash || '' === $key ) {
			return false;
		}

		foreach ( self::by_hash( self::META_EMAIL, $hash ) as $post ) {
			if ( (string) get_post_meta( (int) $post->ID, self::META_INSTITUTION, true ) !== $key ) {
				continue;
			}

			if ( self::created_of( $post ) > $now - self::DUPLICATE_DAYS * DAY_IN_SECONDS ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Move an interest to a state, noting who did it and when.
	 *
	 * @param int    $post_id The interest.
	 * @param string $state   One of `states()`.
	 * @param int    $user_id The account that asked, or 0 for none.
	 * @return bool False for a post that is no interest, and for a state the interest's own does
	 *              not lead to (`can_move()`).
	 */
	public static function set_state( $post_id, $state, $user_id = 0 ) {
		$post_id = (int) $post_id;

		// Under the interest's own lock, which the daily deletion takes too (`purge()`): a press that
		// puts a spam interest back to New and the run that would delete it never both go ahead on
		// what the other is changing.
		$token = self::lock( $post_id );

		if ( '' === $token ) {
			return false;
		}

		// What this request read before it held the lock is not what the interest holds now.
		wp_cache_delete( $post_id, 'post_meta' );

		$post = self::interest( $post_id );

		if ( null === $post || ! self::can_move( self::state_of( $post ), $state ) ) {
			self::unlock( $post_id, $token );

			return false;
		}

		// The time of the change is written first, and the state only once that time is kept:
		// retention counts from it, so a state with no time of its own would be counted from the day
		// the interest was stored, and a spam interest could be deleted the same night.
		$before = (int) get_post_meta( $post_id, self::META_CHANGED, true );
		$at     = time();

		update_post_meta( $post_id, self::META_CHANGED, $at );

		if ( (int) get_post_meta( $post_id, self::META_CHANGED, true ) !== $at ) {
			self::unlock( $post_id, $token );

			return false;
		}

		update_post_meta( $post_id, self::META_STATE, (string) $state );

		// The state as it reads now is the answer, not that the write was asked for: a write
		// WordPress refuses leaves the interest where it was, and the press says so.
		$moved = self::state_of( $post ) === (string) $state;

		if ( $moved ) {
			update_post_meta( $post_id, self::META_CHANGED_BY, (int) $user_id );
		} elseif ( $before > 0 ) {
			update_post_meta( $post_id, self::META_CHANGED, $before );
		} else {
			delete_post_meta( $post_id, self::META_CHANGED );
		}

		self::unlock( $post_id, $token );

		return $moved;
	}

	/**
	 * Take one interest's lock, held a few seconds at most: a state change and the daily deletion
	 * take it, so neither acts on what the other is changing.
	 *
	 * @param int $post_id The interest.
	 * @return string The token `unlock()` takes, or '' when another request holds the lock.
	 */
	private static function lock( $post_id ) {
		return (string) WPCPM_Lock::take( self::LOCK_PREFIX . (int) $post_id, self::LOCK_SECONDS );
	}

	/**
	 * Let one interest's lock go.
	 *
	 * @param int    $post_id The interest.
	 * @param string $token   What `lock()` answered.
	 */
	private static function unlock( $post_id, $token ) {
		WPCPM_Lock::release( self::LOCK_PREFIX . (int) $post_id, (string) $token );
	}

	/**
	 * Delete one interest for good: a spam or a closed one alone (`can_delete()`).
	 *
	 * @param int $post_id The interest.
	 * @return bool False for a post that is no interest, and for one in another state.
	 */
	public static function delete( $post_id ) {
		$post_id = (int) $post_id;
		$token   = self::lock( $post_id );

		if ( '' === $token ) {
			return false;
		}

		$post    = self::interest( $post_id );
		$deleted = null !== $post && self::can_delete( self::state_of( $post ) ) && (bool) wp_delete_post( $post_id, true );

		self::unlock( $post_id, $token );

		return $deleted;
	}

	/**
	 * The interests in the given states, newest first unless asked otherwise, by ID within one
	 * second.
	 *
	 * @param array  $states States to include; anything that is not a state is dropped, and none means no rows.
	 * @param int    $limit  Most rows to read, or 0 for every one.
	 * @param string $order  `DESC` or `ASC`.
	 * @return WP_Post[]
	 */
	public static function in_states( array $states, $limit = 0, $order = 'DESC' ) {
		$found = array();

		foreach ( self::query( $states, 'all', $limit, $order ) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * One page of the interests in the given states, newest first, with what a list needs to offer
	 * the other pages: every interest of a view is reached, however many it holds.
	 *
	 * @param array $states   States to include.
	 * @param int   $page     The page asked for, from 1; one past the last is the last.
	 * @param int   $per_page Interests a page.
	 * @return array{rows: WP_Post[], total: int, pages: int, page: int}
	 */
	public static function page_of( array $states, $page, $per_page ) {
		$per_page = max( 1, (int) $per_page );
		$ids      = array_values( array_map( 'intval', self::query( $states, 'ids', 0, 'DESC' ) ) );
		$total    = count( $ids );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( $pages, max( 1, (int) $page ) );
		$rows     = array();

		foreach ( array_slice( $ids, ( $page - 1 ) * $per_page, $per_page ) as $post_id ) {
			$post = self::interest( $post_id );

			if ( null !== $post ) {
				$rows[] = $post;
			}
		}

		return array(
			'rows'  => $rows,
			'total' => $total,
			'pages' => $pages,
			'page'  => $page,
		);
	}

	/**
	 * How many interests are in the given states, read as IDs.
	 *
	 * @param array $states States to count.
	 * @return int
	 */
	public static function count_in( array $states ) {
		return count( self::query( $states, 'ids', 0, 'DESC' ) );
	}

	/**
	 * How many interests are new: the number the Interests tab's heading carries.
	 *
	 * @return int
	 */
	public static function count_new() {
		return self::count_in( array( self::STATE_NEW ) );
	}

	/**
	 * The query behind `in_states()` and `count_in()`.
	 *
	 * @param array  $states States to include.
	 * @param string $fields `all` for posts, `ids` for IDs.
	 * @param int    $limit  Most rows, or 0 for every one.
	 * @param string $order  `DESC` or `ASC`.
	 * @return array
	 */
	private static function query( array $states, $fields, $limit, $order ) {
		$wanted = array_values( array_intersect( self::states(), array_map( 'strval', $states ) ) );

		if ( empty( $wanted ) ) {
			return array();
		}

		$order = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';

		return (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => $limit > 0 ? (int) $limit : -1,
				'orderby'     => array(
					'date' => $order,
					'ID'   => $order,
				),
				'fields'      => 'ids' === $fields ? 'ids' : '',
				'meta_query'  => array(
					array(
						'key'     => self::META_STATE,
						'value'   => $wanted,
						'compare' => 'IN',
					),
				),
			)
		);
	}

	/**
	 * The interests one of the two hashes names, oldest first.
	 *
	 * @param string $meta_key `META_EMAIL` or `META_CONTACT` (or one of the two address keys).
	 * @param string $hash     `email_hash()` of an address (or `address_key()` of one).
	 * @return WP_Post[]
	 */
	private static function by_hash( $meta_key, $hash ) {
		$found = array();

		foreach ( self::ids_by( $meta_key, $hash ) as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * The IDs of the interests that hold a value in one field, oldest first: the IDs alone, so a
	 * long list costs little to read.
	 *
	 * @param string $meta_key The field.
	 * @param string $value    What it holds.
	 * @return int[]
	 */
	private static function ids_by( $meta_key, $value ) {
		if ( '' === (string) $value ) {
			return array();
		}

		return array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					'numberposts' => -1,
					'orderby'     => array(
						'date' => 'ASC',
						'ID'   => 'ASC',
					),
					'fields'      => 'ids',
					'meta_query'  => array(
						array(
							'key'     => (string) $meta_key,
							'value'   => (string) $value,
							'compare' => '=',
						),
					),
				)
			)
		);
	}

	/**
	 * Delete what retention says to: a spam interest `SPAM_DAYS` after it was filed so, a closed one
	 * `CLOSED_DAYS` after it was closed, each counted from the last change of its state (from when it
	 * was stored, for one whose state never changed). Every other interest is kept.
	 *
	 * @param int $now The time to count from, or 0 for now.
	 * @return int How many interests were deleted.
	 */
	public static function purge( $now = 0 ) {
		$now       = $now > 0 ? (int) $now : time();
		$retention = array(
			self::STATE_SPAM   => self::SPAM_DAYS,
			self::STATE_CLOSED => self::CLOSED_DAYS,
		);
		$purged    = 0;

		foreach ( $retention as $state => $days ) {
			// The IDs alone, never the interests themselves: a list that a flood of spam made long
			// is still read, and each interest is loaded only when its turn comes.
			foreach ( self::query( array( $state ), 'ids', 0, 'ASC' ) as $post_id ) {
				if ( $purged >= self::PURGE_MAX ) {
					break 2;
				}

				$post_id = (int) $post_id;
				$token   = self::lock( $post_id );

				// An interest somebody is moving right now is left for the next run.
				if ( '' === $token ) {
					continue;
				}

				// Read again under the lock, from the database and not from what the list above left
				// in this request's memory, and only then deleted: an interest put back to New since
				// that list was read is no longer one this run may delete.
				wp_cache_delete( $post_id, 'post_meta' );

				$fresh = self::interest( $post_id );

				if ( null !== $fresh && self::state_of( $fresh ) === $state ) {
					$last = (int) get_post_meta( $post_id, self::META_CHANGED, true );
					$last = $last > 0 ? $last : self::created_of( $fresh );

					if ( $last <= $now - $days * DAY_IN_SECONDS && wp_delete_post( $post_id, true ) ) {
						++$purged;
					}
				}

				self::unlock( $post_id, $token );
			}
		}

		return $purged;
	}

	/**
	 * Remove every interest, whatever its status, and the lock a request that died holding one may
	 * have left on it (uninstall).
	 */
	public static function delete_all() {
		foreach ( (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		) as $post_id ) {
			delete_option( self::LOCK_PREFIX . (int) $post_id );
			wp_delete_post( (int) $post_id, true );
		}
	}

	/*
	 * The privacy tools
	 * --------------------------------------------------------------------
	 */

	/**
	 * The interests' entry in WordPress's Export Personal Data tool.
	 *
	 * @param array $exporters WordPress's exporters.
	 * @return array The same, with the interests'.
	 */
	public static function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters[ self::PRIVACY_GROUP ] = array(
			'exporter_friendly_name' => __( 'Interest from students', 'wpcredits-program-manager' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * The interests' entry in WordPress's Erase Personal Data tool.
	 *
	 * @param array $erasers WordPress's erasers.
	 * @return array The same, with the interests'.
	 */
	public static function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers[ self::PRIVACY_GROUP ] = array(
			'eraser_friendly_name' => __( 'Interest from students', 'wpcredits-program-manager' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Every interest an address is on, as the student's or as the representative's, each once,
	 * oldest first.
	 *
	 * @param string $email The address.
	 * @return int[] Their IDs: an interest is loaded only when its turn comes.
	 */
	public static function for_address( $email ) {
		$address = self::address_key( $email );
		$found   = array();

		if ( '' === $address ) {
			return $found;
		}

		// By the two addresses each interest keeps for this, never by the hashes beside them: a hash
		// is salted with the site's authentication keys (`wp_hash()`), and a site that changes its
		// keys would no longer find an interest it still holds, so an export would hand over nothing
		// and an erasure would leave the person's data where it is and say nothing is left. And by
		// a lookup of those two fields, not a read of every interest: the tool asks once a page, and
		// a read of them all would grow with the list until the request could not finish.
		foreach ( array( self::META_ADDRESS, self::META_ADDRESS_CONTACT ) as $meta_key ) {
			foreach ( self::ids_by( $meta_key, $address ) as $post_id ) {
				$found[ (int) $post_id ] = (int) $post_id;
			}
		}

		ksort( $found );

		return array_values( $found );
	}

	/**
	 * An address as two are compared: trimmed and in lower case, or '' for none.
	 *
	 * @param string $email An address.
	 * @return string
	 */
	private static function address_key( $email ) {
		return mb_strtolower( trim( (string) $email ) );
	}

	/**
	 * WordPress's Export Personal Data tool: the interests an address is on, as the student who sent
	 * one or as the representative one names.
	 *
	 * A student is handed everything they sent. A representative is handed what is about them, their
	 * name and address and the institution, and who named them is not said: the rest is the
	 * student's.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1.
	 * @return array{data:array,done:bool}
	 */
	public static function export( $email, $page = 1 ) {
		$page   = max( 1, (int) $page );
		$asked  = self::address_key( $email );
		$all    = self::for_address( $email );
		$data   = array();
		$names  = array(
			'name'           => __( 'Your name', 'wpcredits-program-manager' ),
			'email'          => __( 'Your email', 'wpcredits-program-manager' ),
			'institution'    => __( 'Institution name', 'wpcredits-program-manager' ),
			'country_name'   => __( 'Country', 'wpcredits-program-manager' ),
			'city'           => __( 'City', 'wpcredits-program-manager' ),
			'website'        => __( 'Institution website', 'wpcredits-program-manager' ),
			'department'     => __( 'Department', 'wpcredits-program-manager' ),
			'representative' => __( 'Institution representative', 'wpcredits-program-manager' ),
			'contact_email'  => __( 'Contact email', 'wpcredits-program-manager' ),
			'internships'    => __( 'How internships or practices work', 'wpcredits-program-manager' ),
			'anything'       => __( 'Anything else', 'wpcredits-program-manager' ),
		);
		$theirs = array( 'institution', 'country_name', 'city', 'website', 'representative', 'contact_email' );

		foreach ( array_slice( $all, ( $page - 1 ) * self::PRIVACY_PAGE, self::PRIVACY_PAGE ) as $post_id ) {
			$post = self::interest( $post_id );

			if ( null === $post ) {
				continue;
			}

			$fields  = self::fields_of( $post );
			$student = '' !== $asked && self::address_key( self::answer( $fields, 'email' ) ) === $asked;
			$rows    = array(
				array(
					'name'  => __( 'Received', 'wpcredits-program-manager' ),
					'value' => gmdate( 'Y-m-d H:i:s', self::created_of( $post ) ) . ' UTC',
				),
			);

			foreach ( $names as $key => $label ) {
				if ( ! $student && ! in_array( $key, $theirs, true ) ) {
					continue;
				}

				$value = isset( $fields[ $key ] ) ? $fields[ $key ] : '';
				$value = is_array( $value ) ? implode( ', ', array_map( 'trim', array_map( 'strval', $value ) ) ) : trim( (string) $value );

				if ( '' !== $value ) {
					$rows[] = array(
						'name'  => $label,
						'value' => esc_html( $value ),
					);
				}
			}

			// What the site keeps as evidence of the two boxes the student ticked is the student's
			// too: the sentences agreed to, the policy, the time, and the address and browser the
			// form was sent from, as they are stored. A representative ticked nothing.
			if ( $student ) {
				foreach ( self::consent_rows( self::consent_of( $post ) ) as $row ) {
					$rows[] = $row;
				}
			}

			$data[] = array(
				'group_id'    => self::PRIVACY_GROUP,
				'group_label' => __( 'Interest from students', 'wpcredits-program-manager' ),
				'item_id'     => self::PRIVACY_GROUP . '-' . (int) $post->ID,
				'data'        => $rows,
			);
		}

		return array(
			'data' => $data,
			'done' => $page * self::PRIVACY_PAGE >= count( $all ),
		);
	}

	/**
	 * WordPress's Erase Personal Data tool: delete every interest an address is on, the student's or
	 * the representative's, whatever its state. The whole interest goes: with either person taken
	 * out of it, nothing is left to follow up. One call deletes `PRIVACY_PAGE` of them at most and
	 * says whether more are left, so WordPress calls again. One WordPress will not delete does not
	 * count toward that, so every interest is tried before the tool is told it is done.
	 *
	 * @param string $email The address.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public static function erase( $email ) {
		$removed  = 0;
		$retained = 0;

		$more = false;

		foreach ( self::for_address( $email ) as $post_id ) {
			if ( $removed >= self::PRIVACY_PAGE ) {
				$more = true;
				break;
			}

			if ( wp_delete_post( (int) $post_id, true ) ) {
				++$removed;
			} else {
				++$retained;
			}
		}

		// An interest WordPress would not delete is still there, and the tool says so: an erasure
		// that reported nothing retained would tell the person their data is gone when it is not.
		$messages = array();

		if ( $retained > 0 ) {
			$messages[] = sprintf(
				/* translators: %s: how many interests could not be deleted. */
				_n( '%s interest from a student could not be deleted and is still stored. Delete it from the Interests tab of the Students screen.', '%s interests from students could not be deleted and are still stored. Delete them from the Interests tab of the Students screen.', $retained, 'wpcredits-program-manager' ),
				number_format_i18n( $retained )
			);
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained > 0,
			'messages'       => $messages,
			'done'           => ! $more,
		);
	}

	/**
	 * The consent evidence of an interest as rows of an export, each value escaped: what was agreed
	 * to, where the policy is, when, and the address and browser the form was sent from, each left
	 * out when it is not stored.
	 *
	 * @param array $consent The evidence, as `consent_of()` answers it.
	 * @return array[] Each `name` and `value`.
	 */
	private static function consent_rows( array $consent ) {
		$labels = array(
			'told'     => __( 'Agreed to, about the representative', 'wpcredits-program-manager' ),
			'sentence' => __( 'Agreed to, about privacy', 'wpcredits-program-manager' ),
			'url'      => __( 'Privacy policy', 'wpcredits-program-manager' ),
			'at'       => __( 'Agreed at', 'wpcredits-program-manager' ),
			'ip'       => __( 'Sent from the address', 'wpcredits-program-manager' ),
			'agent'    => __( 'Sent from the browser', 'wpcredits-program-manager' ),
		);
		$rows   = array();

		foreach ( $labels as $key => $label ) {
			$value = isset( $consent[ $key ] ) && is_scalar( $consent[ $key ] ) ? trim( (string) $consent[ $key ] ) : '';

			if ( '' === $value ) {
				continue;
			}

			if ( 'at' === $key && ctype_digit( $value ) ) {
				$value = gmdate( 'Y-m-d H:i:s', (int) $value ) . ' UTC';
			}

			$rows[] = array(
				'name'  => $label,
				'value' => esc_html( wp_strip_all_tags( $value ) ),
			);
		}

		return $rows;
	}
}
