<?php
/**
 * Mentors module - the mentor application, as the site keeps it (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per application to mentor, from the public form to the decision (design of 16 October
 * 2026, section 3, decisions 1 and 2).
 *
 * The sponsor application's shape with the mentor's questions: a private post with the answers as
 * meta, what the form's checks raised, the consent evidence, the hash of the address that the
 * confirming link is bound to, and a history in which every decision is a dated row with who took
 * it and the note they wrote. The answers are site fields, keyed by the form's question keys
 * (`WPCPM_Mentor_Application_Form::fields()`), never by an Airtable column: the one write to
 * Airtable is M2's, on activation, and maps them there.
 *
 * **The states.** `new`, `held` and `spam` are the form's (`held` and `spam` the checks'); `info`
 * (more information asked for), `second` (a second opinion wanted, internal), `selected` (the course
 * is pending), `declined` and `active` (the account exists, which M2 sets) are a person's; `lapsed`
 * is the clock's, or an Administrator's who withdrew a selected applicant. A row filed as spam that
 * an Administrator puts back in the queue is `new` again, and one never acknowledged is sent the
 * acknowledgement then; an open row never acknowledged is sent it by Send the acknowledgement, and
 * stays where it is (1.122.33). The queue is the four open states; `selected` waits for the course;
 * the rest are closed.
 *
 * **The daily run** (`daily()`) moves a selected row the course never reached to `lapsed`, under the
 * row's lock, then settles the activations (`WPCPM_Mentor_Activation::settle_all()`), then deletes
 * what the retention settings say to, the three the other two application forms keep (design,
 * section 9): spam after its days, declined, lapsed and held rows after the rejected days, an active
 * row only if approved ones are ever set to go, and never while its Mentors row is still to be
 * written. A held row is one of the four open states and goes all the same, as the sponsor
 * application's does: it has no decision, so its days are counted from the last thing that happened
 * to it. A new, info or second row is never deleted, and a selected one lapses first. What a row
 * counted for the feed is kept when it goes (`forget()`), so the months behind the feed do not
 * shrink as rows are deleted.
 *
 * **What an activation keeps here** (1.122.33): the account it made a mentor and how it found it,
 * when, the Mentors row's fate, and why an account could not be made, each a site field with its
 * reader; and the name of the application's one lock (`lock_name()`), which the activation, its
 * retry, settling, the lapse clock, Withdraw and Send the acknowledgement take.
 * `WPCPM_Mentor_Activation` writes them.
 *
 * Nothing here sends anything or draws anything: the form, the queue, the feed and the activation
 * read and write through this class.
 */
final class WPCPM_Mentor_Application {

	/**
	 * The post type. Fourteen characters: `register_post_type()` refuses a name longer than twenty,
	 * and bin/test-roles.php measures every post type the plugin declares.
	 */
	const POST_TYPE = 'wpcpm_ment_app';

	const STATE_NEW      = 'new';
	const STATE_HELD     = 'held';
	const STATE_SPAM     = 'spam';
	const STATE_INFO     = 'info';
	const STATE_SECOND   = 'second';
	const STATE_SELECTED = 'selected';
	const STATE_ACTIVE   = 'active';
	const STATE_DECLINED = 'declined';
	const STATE_LAPSED   = 'lapsed';

	/** Post meta: the cleaned answers, keyed by the form's question keys. Written once, at submission. */
	const META_FIELDS = '_wpcpm_mapp_fields';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_mapp_state';

	/** Post meta: the reference quoted in mail and on the queue, `MAPP-2026-0007`. */
	const META_REFERENCE = '_wpcpm_mapp_reference';

	/** Post meta: the consent evidence, as `WPCPM_Form_Guard::consent_evidence()` records it. */
	const META_CONSENT = '_wpcpm_mapp_consent';

	/** Post meta: what the form's checks raised, a list of slugs. */
	const META_SIGNALS = '_wpcpm_mapp_signals';

	/** Post meta: `wp_hash()` of the lowercased address, for the duplicate check and the confirming link. */
	const META_EMAIL = '_wpcpm_mapp_email';

	/** Post meta: when the applicant followed the confirming link, a Unix time; absent until then. */
	const META_VERIFIED = '_wpcpm_mapp_verified';

	/** Post meta, repeating: one row per event, `event`, `at`, `actor`, `note`. */
	const META_EVENT = '_wpcpm_mapp_event';

	/**
	 * Post meta: when the row was decided, a Unix time, kept while it is filed as spam or declined and
	 * absent otherwise. An index over the history, which stays the record: Recently decided orders and
	 * bounds by it in one query rather than loading every decided row (`decided_posts()`), as the
	 * sponsor application's list does.
	 */
	const META_DECIDED = '_wpcpm_mapp_decided';

	/**
	 * Post meta: the account an activation made a mentor (1.122.33), a user ID; absent until then.
	 */
	const META_USER = '_wpcpm_mapp_user';

	/**
	 * Post meta: what became of the application's Mentors row in Airtable (1.122.33): `state`
	 * (`pending`, `created`, `reactivated`, `adopted`, `found`, `gone`, `already` or `refused`), `how`
	 * (`create` or `reactivate`), `record`, `error`, `left_out` and `at` (`airtable_of()`).
	 */
	const META_AIRTABLE = '_wpcpm_mapp_airtable';

	/**
	 * Post meta: why the last activation could not make the account (1.122.33), `at` and `message`;
	 * absent once an activation makes it.
	 */
	const META_PROBLEM = '_wpcpm_mapp_problem';

	/**
	 * Post meta: when the application was made a mentor (1.122.33), a Unix time: the index the
	 * Activated list orders by, and the moment its invitation line counts from.
	 */
	const META_ACTIVATED_AT = '_wpcpm_mapp_activated';

	/**
	 * Post meta: how the activation found the account it made a mentor (1.122.33): `created` for a
	 * new one; how `WPCPM_Mentor_Provision::find_account()` found it (`email`, `login`, `profile`);
	 * or, for the account linked by its record id to the Mentors row that holds the address, `record`
	 * when another account answered for the applicant and `row` when none did. A `login` or a `record`
	 * account is sent no invitation until a person confirms it is the person's (rulings R5 and P9b),
	 * and the Activated list says so by this.
	 */
	const META_LINKED_BY = '_wpcpm_mapp_linked_by';

	/**
	 * Post meta: the Mentor Status Checker's last look at the applicant's WordPress.org profile
	 * (1.122.33), `at`, `state` and `message`; absent until the checker has looked. One look, the
	 * latest, replaced by the next (`note_check()`, `last_check()`).
	 */
	const META_CHECKED = '_wpcpm_mapp_checked';

	/**
	 * The option name an application's lock is kept under, with the application's ID after it
	 * (`lock_name()`, through `WPCPM_Lock`). The activation, its retry, settling, the lapse clock,
	 * and Withdraw and Send the acknowledgement on the Waiting for review tab take it, so one of them
	 * at a time works on one application; the uninstaller sweeps the prefix.
	 */
	const LOCK_PREFIX = 'wpcpm_mapp_lock_';

	/**
	 * How long an application's lock holds at most, in seconds: past the longest an activation takes,
	 * an account, a mail queued and three Airtable requests, so a lock a request that died left runs
	 * out by itself.
	 */
	const LOCK_TTL = 600;

	/** The `admin_post_` action the confirming link lands on (`WPCPM_Mentor_Application_Form::handle_verify()`). */
	const ACTION_VERIFY = 'wpcpm_mentor_apply_verify';

	/**
	 * What the history calls each thing that happened. A decision writes one of these. The feed reads
	 * five of them as four figures, each application once per figure in a month (selected, activated,
	 * declined, and lapsed or withdrawn as lapsed), and counts no other event.
	 */
	const EVENT_SUBMITTED = 'submitted';
	const EVENT_CONFIRMED = 'address confirmed';
	const EVENT_SELECTED  = 'selected';
	const EVENT_INFO      = 'information requested';
	const EVENT_SECOND    = 'second opinion';
	const EVENT_DECLINED  = 'declined';
	const EVENT_SPAM      = 'marked as spam';
	const EVENT_WITHDRAWN = 'withdrawn';
	const EVENT_REOPENED  = 'put back in the queue';
	const EVENT_LAPSED    = 'lapsed';
	const EVENT_ACTIVATED = 'activated';

	/** Put back in the queue and sent the acknowledgement it never had, with the confirming link. */
	const EVENT_REOPENED_ACKNOWLEDGED = 'put back in the queue, acknowledgement sent';

	/**
	 * Sent the acknowledgement it never had, with the confirming link, by Send the acknowledgement on
	 * an open row (1.122.33); the row stays in its state. Not counted by the feed.
	 */
	const EVENT_ACKNOWLEDGED = 'acknowledgement sent';

	/**
	 * The activation's Mentors row (1.122.33): written, refused, and, for a mentor on this site
	 * already, filed as such. None is counted by the feed.
	 */
	const EVENT_AIRTABLE_WRITTEN = 'Airtable row written';
	const EVENT_AIRTABLE_REFUSED = 'Airtable row refused';
	const EVENT_ALREADY_MENTOR   = 'already a mentor';

	/**
	 * The row settled with nothing written from here (1.122.33): the account holds its record,
	 * linked by the mentors sync or by hand, or the account is gone. Its own event, so the history
	 * never claims a write the site did not make; not counted by the feed.
	 */
	const EVENT_AIRTABLE_SETTLED = 'Airtable row settled';

	/** The daily run: the lapse clock, then retention. */
	const CRON_DAILY = 'wpcpm_mentor_applications_daily';

	/**
	 * Option: what each deletion left behind, a reference, a state, the days and a time, and never a
	 * word anybody wrote: a log that outlives the row must not become the copy retention removed.
	 */
	const OPT_LOG = 'wpcpm_mentor_application_log';

	/** How many rows that log keeps. */
	const LOG_MAX = 200;

	/** Option: what deleted rows counted for the feed, by month (`month_counts_of()`). */
	const OPT_KEPT = 'wpcpm_mentor_application_counts';

	/** The fewest days a selected row has before it lapses, whatever the setting says. */
	const LAPSE_FLOOR = 14;

	/**
	 * Hooks: the post type, the daily run, and its place on the clock, put back on every load where
	 * it is missing, since a deploy replaces the files and never fires the activation hook.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
		add_action( self::CRON_DAILY, array( __CLASS__, 'daily' ) );
	}

	/**
	 * Put the daily run on the clock if it is not there: ten hours past the first load, an hour clear
	 * of the sponsor applications' retention run.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
			wp_schedule_event( time() + 10 * HOUR_IN_SECONDS, 'daily', self::CRON_DAILY );
		}
	}

	/**
	 * Take the daily run off the clock.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
	}

	/**
	 * The daily run: lapse first, so a row that lapses tonight starts its retention days tonight; then
	 * settle the activations whose Mentors row was linked meanwhile or whose account is gone, while
	 * the activation's class is loaded, so a settled row neither reads as refused nor is kept for
	 * ever (1.122.33); then retention.
	 */
	public static function daily() {
		self::lapse();

		if ( class_exists( 'WPCPM_Mentor_Activation' ) ) {
			WPCPM_Mentor_Activation::settle_all();
		}

		self::purge();
	}

	/**
	 * Register the post type: invisible everywhere, since a row holds a person's name, address and
	 * free text, and the only way to one is the Mentors screen, which asks the capability first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Mentor applications', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Mentor application', 'wpcredits-program-manager' ),
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
				'capability_type'     => array( 'wpcpm_ment_app', 'wpcpm_ment_apps' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The nine states, in the order an application can move through them.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array(
			self::STATE_NEW,
			self::STATE_HELD,
			self::STATE_SPAM,
			self::STATE_INFO,
			self::STATE_SECOND,
			self::STATE_SELECTED,
			self::STATE_ACTIVE,
			self::STATE_DECLINED,
			self::STATE_LAPSED,
		);
	}

	/**
	 * The four states the Waiting for review tab lists: in front of a person, not decided yet.
	 *
	 * @return string[]
	 */
	public static function open_states() {
		return array( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO, self::STATE_SECOND );
	}

	/**
	 * The two states the Recently decided list under the views holds: filed as spam, and declined.
	 *
	 * @return string[]
	 */
	public static function decided_states() {
		return array( self::STATE_SPAM, self::STATE_DECLINED );
	}

	/**
	 * Store one submission and answer with its ID, or 0 when WordPress refused the row.
	 *
	 * The answers are written as a slashed copy, as core unslashes what it is handed, so a backslash
	 * an applicant typed is kept. The title is the applicant's name, for the rare screen that prints
	 * one; everything a person reads takes the name from the answers (`name_of()`).
	 *
	 * @param array  $fields  The cleaned answers, keyed by question key.
	 * @param string $state   `new`, `held` or `spam`.
	 * @param array  $signals What the checks raised.
	 * @param array  $consent The consent evidence.
	 * @return int
	 */
	public static function store( array $fields, $state, array $signals, array $consent ) {
		$name  = isset( $fields['name'] ) ? trim( (string) $fields['name'] ) : '';
		$email = isset( $fields['email'] ) ? trim( (string) $fields['email'] ) : '';

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					// Nobody: the applicant has no account, and a row authored by the Administrator who
					// happened to post the form would read on every screen as though they applied.
					'post_author' => 0,
					'post_title'  => '' !== $name ? $name : __( 'Application with no name', 'wpcredits-program-manager' ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;

		$state = in_array( $state, array( self::STATE_NEW, self::STATE_HELD, self::STATE_SPAM ), true ) ? $state : self::STATE_HELD;

		update_post_meta( $post_id, self::META_FIELDS, wp_slash( $fields ) );
		update_post_meta( $post_id, self::META_STATE, $state );
		update_post_meta( $post_id, self::META_REFERENCE, self::reference( $post_id ) );
		update_post_meta( $post_id, self::META_CONSENT, wp_slash( $consent ) );
		update_post_meta( $post_id, self::META_SIGNALS, array_values( array_unique( array_map( 'strval', $signals ) ) ) );
		update_post_meta( $post_id, self::META_EMAIL, self::email_hash( $email ) );

		self::add_event( $post_id, self::EVENT_SUBMITTED );
		self::index_decision( $post_id, $state );

		return $post_id;
	}

	/**
	 * The hash an address is compared and signed by: lowercased, so `Ada@Example.test` and
	 * `ada@example.test` are one applicant, and '' for no address.
	 *
	 * @param string $email An address.
	 * @return string
	 */
	public static function email_hash( $email ) {
		$email = trim( (string) $email );

		return '' === $email ? '' : wp_hash( mb_strtolower( $email ) );
	}

	/**
	 * Add one row to an application's history.
	 *
	 * Repeating meta rather than one array, so two writes in the same second cannot lose each other's
	 * row; written as a slashed copy, so a note keeps a backslash.
	 *
	 * @param int    $post_id The application.
	 * @param string $event   One of the `EVENT_*` values.
	 * @param int    $actor   Who did it; 0 for the applicant or the clock.
	 * @param string $note    What they wrote, or ''.
	 * @param int    $at      When, a Unix time; 0 for now.
	 */
	public static function add_event( $post_id, $event, $actor = 0, $note = '', $at = 0 ) {
		add_post_meta(
			(int) $post_id,
			self::META_EVENT,
			wp_slash(
				array(
					'event' => sanitize_text_field( (string) $event ),
					'at'    => (int) $at > 0 ? (int) $at : time(),
					'actor' => absint( $actor ),
					'note'  => sanitize_textarea_field( (string) $note ),
				)
			)
		);
	}

	/**
	 * Move an application to a state and write the decision to its history, in one place, so no
	 * state ever changes without its row.
	 *
	 * @param int    $post_id The application.
	 * @param string $state   One of the `STATE_*` values.
	 * @param string $event   One of the `EVENT_*` values.
	 * @param int    $actor   Who decided; 0 for the clock.
	 * @param string $note    What they wrote, or ''.
	 * @param int    $at      When, a Unix time; 0 for now.
	 * @return bool False for a state that is not one.
	 */
	public static function decide( $post_id, $state, $event, $actor, $note = '', $at = 0 ) {
		if ( ! in_array( $state, self::states(), true ) ) {
			return false;
		}

		update_post_meta( (int) $post_id, self::META_STATE, $state );
		self::add_event( (int) $post_id, $event, $actor, $note, $at );
		self::index_decision( (int) $post_id, $state );

		return true;
	}

	/**
	 * Keep the decision time on a row while it is filed as spam or declined, and take it off when it
	 * is not: in the two places a state is written (`store()`, `decide()`), so no row enters either
	 * state without the index that Recently decided finds it by.
	 *
	 * @param int    $post_id The application.
	 * @param string $state   The state it was just given.
	 */
	private static function index_decision( $post_id, $state ) {
		$post = in_array( $state, self::decided_states(), true ) ? get_post( (int) $post_id ) : null;

		if ( $post instanceof WP_Post ) {
			update_post_meta( (int) $post_id, self::META_DECIDED, self::decided_at( $post ) );
		} else {
			delete_post_meta( (int) $post_id, self::META_DECIDED );
		}
	}

	/**
	 * An application's history, oldest first, each row whole: a row stored in another shape is left
	 * out rather than read as half a decision.
	 *
	 * @param int $post_id The application.
	 * @return array[] Each `event`, `at`, `actor`, `note`.
	 */
	public static function events( $post_id ) {
		$out = array();

		foreach ( (array) get_post_meta( (int) $post_id, self::META_EVENT, false ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['event'], $row['at'] ) ) {
				continue;
			}

			$out[] = array(
				'event' => (string) $row['event'],
				'at'    => (int) $row['at'],
				'actor' => isset( $row['actor'] ) ? (int) $row['actor'] : 0,
				'note'  => isset( $row['note'] ) ? (string) $row['note'] : '',
			);
		}

		// By time, and rows of one second in the order they were written, on every PHP the plugin runs
		// on: `usort()` keeps equal rows in place only from PHP 8.0.
		$at    = array_column( $out, 'at' );
		$order = array_keys( $out );

		array_multisort( $at, SORT_ASC, SORT_NUMERIC, $order, SORT_ASC, SORT_NUMERIC, $out );

		return $out;
	}

	/**
	 * When an event last happened to an application: the newest row of that event, or, with no
	 * event named, the newest row of any.
	 *
	 * @param int    $post_id The application.
	 * @param string $event   One of the `EVENT_*` values, or '' for any.
	 * @return int A Unix time, or 0 when none happened.
	 */
	public static function last_at( $post_id, $event = '' ) {
		$latest = 0;

		foreach ( self::events( $post_id ) as $row ) {
			if ( ( '' === $event || $event === $row['event'] ) && $row['at'] > $latest ) {
				$latest = $row['at'];
			}
		}

		return $latest;
	}

	/**
	 * The application a handler was posted for, or null when the ID is not one: the type is checked,
	 * not only the number, so a handler never sets a state on a page.
	 *
	 * @param int $post_id A post ID.
	 * @return WP_Post|null
	 */
	public static function application( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * An application's state.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function state_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_STATE, true );
	}

	/**
	 * The answers as stored, keyed by question key.
	 *
	 * @param WP_Post $post The application.
	 * @return array
	 */
	public static function fields_of( WP_Post $post ) {
		$fields = get_post_meta( (int) $post->ID, self::META_FIELDS, true );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * The applicant's name as they typed it, or '' when the answers hold none.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function name_of( WP_Post $post ) {
		$fields = self::fields_of( $post );

		return isset( $fields['name'] ) ? trim( (string) $fields['name'] ) : '';
	}

	/**
	 * The applicant's address, or '' when there is not a usable one.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function email_of( WP_Post $post ) {
		$fields = self::fields_of( $post );
		$email  = isset( $fields['email'] ) ? sanitize_email( (string) $fields['email'] ) : '';

		return is_email( $email ) ? $email : '';
	}

	/**
	 * The applicant's WordPress.org username, as the form normalized it.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function handle_of( WP_Post $post ) {
		$fields = self::fields_of( $post );

		return isset( $fields['profile'] ) ? (string) $fields['profile'] : '';
	}

	/**
	 * The profile's address, built from the handle, or '' with none.
	 *
	 * @param string $handle A WordPress.org username.
	 * @return string
	 */
	public static function profile_url( $handle ) {
		$handle = trim( (string) $handle );

		return '' === $handle ? '' : 'https://profiles.wordpress.org/' . rawurlencode( $handle ) . '/';
	}

	/**
	 * The account an activation made a mentor, or 0 (1.122.33).
	 *
	 * @param WP_Post $post The application.
	 * @return int
	 */
	public static function user_of( WP_Post $post ) {
		return absint( get_post_meta( (int) $post->ID, self::META_USER, true ) );
	}

	/**
	 * What became of the application's Mentors row (1.122.33), every key there, or none when no
	 * activation wrote one or it is stored in another shape.
	 *
	 * @param WP_Post $post The application.
	 * @return array `state`, `how`, `record`, `error`, `left_out` and `at`, or `array()`.
	 */
	public static function airtable_of( WP_Post $post ) {
		$kept = get_post_meta( (int) $post->ID, self::META_AIRTABLE, true );

		if ( ! is_array( $kept ) || ! isset( $kept['state'] ) || '' === (string) $kept['state'] ) {
			return array();
		}

		return array(
			'state'    => (string) $kept['state'],
			'how'      => isset( $kept['how'] ) ? (string) $kept['how'] : '',
			'record'   => isset( $kept['record'] ) ? (string) $kept['record'] : '',
			'error'    => isset( $kept['error'] ) ? (string) $kept['error'] : '',
			'left_out' => isset( $kept['left_out'] ) && is_array( $kept['left_out'] ) ? $kept['left_out'] : array(),
			'at'       => isset( $kept['at'] ) ? (int) $kept['at'] : 0,
		);
	}

	/**
	 * Why the last activation could not make the account (1.122.33), or none.
	 *
	 * @param WP_Post $post The application.
	 * @return array `at` and `message`, or `array()`.
	 */
	public static function problem_of( WP_Post $post ) {
		$kept = get_post_meta( (int) $post->ID, self::META_PROBLEM, true );

		if ( ! is_array( $kept ) || ! isset( $kept['message'] ) || '' === (string) $kept['message'] ) {
			return array();
		}

		return array(
			'at'      => isset( $kept['at'] ) ? (int) $kept['at'] : 0,
			'message' => (string) $kept['message'],
		);
	}

	/**
	 * How the activation found the account it made a mentor (`META_LINKED_BY`), or '' (1.122.33).
	 *
	 * @param WP_Post $post The application.
	 * @return string `created`, `email`, `login`, `profile`, `record`, `row` or ''.
	 */
	public static function linked_by_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_LINKED_BY, true );
	}

	/**
	 * Keep what the Mentor Status Checker found when it last looked up the applicant's profile
	 * (1.122.33), in place of the look before it: the moment, the state it answered (`completed`,
	 * `not_completed`, `no_username`, `not_found` or `error`) and its message. It writes nothing else,
	 * and changes no state of the application: whether the course shows is the checker's to read, and
	 * the activation is the step that makes a mentor of it.
	 *
	 * @param int    $post_id The application.
	 * @param string $state   The state the profile check answered.
	 * @param string $message Its message, or ''.
	 */
	public static function note_check( $post_id, $state, $message ) {
		update_post_meta(
			(int) $post_id,
			self::META_CHECKED,
			wp_slash(
				array(
					'at'      => time(),
					'state'   => sanitize_text_field( (string) $state ),
					'message' => sanitize_text_field( (string) $message ),
				)
			)
		);
	}

	/**
	 * The checker's last look at the applicant's profile (1.122.33), every key there, or none when it
	 * has not looked or the value is stored in another shape.
	 *
	 * @param WP_Post $post The application.
	 * @return array `at`, `state` and `message`, or `array()` for never.
	 */
	public static function last_check( WP_Post $post ) {
		$kept = get_post_meta( (int) $post->ID, self::META_CHECKED, true );

		if ( ! is_array( $kept ) || ! isset( $kept['state'] ) || '' === (string) $kept['state'] ) {
			return array();
		}

		return array(
			'at'      => isset( $kept['at'] ) ? (int) $kept['at'] : 0,
			'state'   => (string) $kept['state'],
			'message' => isset( $kept['message'] ) ? (string) $kept['message'] : '',
		);
	}

	/**
	 * The name of an application's lock, which `WPCPM_Lock` keeps as a row of the options table
	 * (1.122.33).
	 *
	 * @param int $post_id The application.
	 * @return string
	 */
	public static function lock_name( $post_id ) {
		return self::LOCK_PREFIX . (int) $post_id;
	}

	/**
	 * Drop this request's copy of an application's meta, so the next read comes from the table.
	 *
	 * WordPress keeps every meta row it has read for the rest of the request, and a list read before
	 * a lock (the daily check's, the lapse clock's) has read them all; a state checked under the lock
	 * would be the one from before it, and an activation another request finished in between would
	 * not be seen. Called right after the application's lock is taken, before anything under it is
	 * read, as `WPCPM_Mentor_Calls::forget_cached()` is for a call (1.122.33).
	 *
	 * @param int $post_id The application.
	 */
	public static function forget_cached( $post_id ) {
		wp_cache_delete( (int) $post_id, 'post_meta' );
	}

	/**
	 * What the form's checks raised against an application.
	 *
	 * @param WP_Post $post The application.
	 * @return string[]
	 */
	public static function signals_of( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_SIGNALS, true );

		return is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'strval', $stored ), 'strlen' ) ) ) : array();
	}

	/**
	 * Add one signal to what the checks raised against an application, once. For a thing found after
	 * the row was stored, such as an acknowledgement the mail layer could not hand off.
	 *
	 * @param int    $post_id The application.
	 * @param string $signal  A short slug.
	 * @return bool Whether it was added: false for an empty slug, a post that is no application, and a
	 *              signal the row carries already.
	 */
	public static function add_signal( $post_id, $signal ) {
		$post   = self::application( $post_id );
		$signal = trim( (string) $signal );

		if ( null === $post || '' === $signal ) {
			return false;
		}

		$signals = self::signals_of( $post );

		if ( in_array( $signal, $signals, true ) ) {
			return false;
		}

		$signals[] = $signal;

		update_post_meta( (int) $post_id, self::META_SIGNALS, $signals );

		return true;
	}

	/**
	 * One application's reference: derived from the post ID and the year it arrived, so it needs no
	 * counter and no lock. Not a secret and not a key to anything.
	 *
	 * @param int $post_id The application.
	 * @return string
	 */
	public static function reference( $post_id ) {
		$post = get_post( (int) $post_id );
		$year = $post instanceof WP_Post && '' !== (string) $post->post_date ? substr( (string) $post->post_date, 0, 4 ) : wp_date( 'Y' );

		return sprintf( 'MAPP-%1$s-%2$04d', $year, (int) $post_id );
	}

	/**
	 * One application's reference, the stored one first.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function reference_of( WP_Post $post ) {
		$stored = (string) get_post_meta( (int) $post->ID, self::META_REFERENCE, true );

		return '' !== $stored ? $stored : self::reference( (int) $post->ID );
	}

	/**
	 * The application that carries a reference, for the command that prints the row it would write.
	 * The stored reference is the one asked for, as `reference_of()` reads it first.
	 *
	 * @param string $reference A reference such as `MAPP-2026-0503`.
	 * @return WP_Post|null Null for none, and for a reference that is empty.
	 */
	public static function by_reference( $reference ) {
		$reference = trim( (string) $reference );

		if ( '' === $reference ) {
			return null;
		}

		$found = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'   => self::META_REFERENCE,
						'value' => $reference,
					),
				),
			)
		);

		return ! empty( $found[0] ) && $found[0] instanceof WP_Post ? $found[0] : null;
	}

	/**
	 * Whether the applicant has confirmed the address by following the acknowledgement's link. A
	 * decision that sends an email asks this first (design, section 3, decision 4).
	 *
	 * @param int $post_id The application.
	 * @return bool
	 */
	public static function is_confirmed( $post_id ) {
		return '' !== trim( (string) get_post_meta( (int) $post_id, self::META_VERIFIED, true ) );
	}

	/**
	 * Stamp the address as confirmed, once, with a row in the history.
	 *
	 * @param int $post_id The application.
	 * @return bool False when it was confirmed already.
	 */
	public static function confirm( $post_id ) {
		if ( self::is_confirmed( $post_id ) ) {
			return false;
		}

		update_post_meta( (int) $post_id, self::META_VERIFIED, time() );
		self::add_event( (int) $post_id, self::EVENT_CONFIRMED );

		return true;
	}

	/**
	 * The link in the acknowledgement that confirms the address: signed with the site's salts and
	 * bound to the stored hash of the address, as the institution application's is, so it cannot be
	 * made without the salts and stops working if the address on the row ever changes.
	 *
	 * @param int $post_id The application.
	 * @return string
	 */
	public static function verify_url( $post_id ) {
		return add_query_arg(
			array(
				'action' => self::ACTION_VERIFY,
				'app'    => (int) $post_id,
				't'      => self::verify_signature( $post_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The signature half of a confirming link.
	 *
	 * @param int $post_id The application.
	 * @return string
	 */
	public static function verify_signature( $post_id ) {
		$hash = (string) get_post_meta( (int) $post_id, self::META_EMAIL, true );

		return substr( wp_hash( 'wpcpm-mentor-application-verify|' . (int) $post_id . '|' . $hash ), 0, 32 );
	}

	/**
	 * The applications in the given states, oldest first and by ID within one second, so every read
	 * of a list comes back in one order.
	 *
	 * @param array $states States to include; anything that is not a state is dropped, and none means no rows.
	 * @param int   $limit  Most rows to read, or 0 for every one.
	 * @return WP_Post[]
	 */
	public static function applications( array $states, $limit = 0 ) {
		$found = array();

		foreach ( self::query( $states, 'all', $limit ) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * How many applications are in the given states, read as IDs.
	 *
	 * @param array $states States to count.
	 * @return int
	 */
	public static function count_in( array $states ) {
		return count( self::query( $states, 'ids', 0 ) );
	}

	/**
	 * How many applications wait in the queue: the tab's count, the dashboard tile's and the
	 * screen's choice of tab.
	 *
	 * @return int
	 */
	public static function waiting_count() {
		return self::count_in( self::open_states() );
	}

	/**
	 * When a closed row was decided: the newest event that closed it, never the newest event of any
	 * kind, which an address confirmed afterwards would move.
	 *
	 * Filed as spam is the marking or, for a row the form's checks filed, which is never marked, the
	 * day it arrived; declined is the decline; lapsed is the clock's lapse or an Administrator's
	 * withdrawal. The history is read once. A row in any other state has no decision: 0.
	 *
	 * @param WP_Post $post The application.
	 * @return int A Unix time, or 0.
	 */
	public static function decided_at( WP_Post $post ) {
		$closing = array(
			self::STATE_SPAM     => array( self::EVENT_SPAM ),
			self::STATE_DECLINED => array( self::EVENT_DECLINED ),
			self::STATE_LAPSED   => array( self::EVENT_LAPSED, self::EVENT_WITHDRAWN ),
		);
		$state   = self::state_of( $post );

		if ( ! isset( $closing[ $state ] ) ) {
			return 0;
		}

		$closed  = 0;
		$arrived = 0;

		foreach ( self::events( (int) $post->ID ) as $row ) {
			if ( in_array( $row['event'], $closing[ $state ], true ) ) {
				$closed = max( $closed, $row['at'] );
			} elseif ( self::EVENT_SUBMITTED === $row['event'] ) {
				$arrived = max( $arrived, $row['at'] );
			}
		}

		if ( $closed > 0 ) {
			return $closed;
		}

		return $arrived > 0 ? $arrived : (int) get_post_time( 'U', true, $post );
	}

	/**
	 * The applications filed as spam or declined, newest decision first, bounded.
	 *
	 * Ordered in the query by the decision time the row carries (`META_DECIDED`), the ID breaking a tie
	 * of a second in the same direction, so the rows the bound cuts are the same on every load: a sort
	 * in PHP can only arrange the rows the query returned. A row without the index is not found, as a
	 * meta key is an inner join; `store()` and `decide()` write it for every row they file in either
	 * state.
	 *
	 * @param int $limit Most rows to read; one at least.
	 * @return WP_Post[]
	 */
	public static function decided_posts( $limit ) {
		$found = array();

		foreach ( (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => max( 1, (int) $limit ),
				'meta_key'    => self::META_DECIDED,
				'orderby'     => array(
					'meta_value_num' => 'DESC',
					'ID'             => 'DESC',
				),
				'meta_query'  => array(
					array(
						'key'     => self::META_STATE,
						'value'   => self::decided_states(),
						'compare' => 'IN',
					),
				),
			)
		) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * The query behind `applications()` and `count_in()`.
	 *
	 * @param array  $states States to include.
	 * @param string $fields `all` for posts, `ids` for IDs.
	 * @param int    $limit  Most rows, or 0 for every one.
	 * @return array
	 */
	private static function query( array $states, $fields, $limit ) {
		$wanted = array_values( array_intersect( self::states(), array_map( 'strval', $states ) ) );

		if ( empty( $wanted ) ) {
			return array();
		}

		return (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => $limit > 0 ? (int) $limit : -1,
				'orderby'     => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
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

	/*
	 * The lapse clock and retention
	 * --------------------------------------------------------------------
	 */

	/**
	 * Move every selected row whose last selection is older than the days set to `lapsed`, with a
	 * dated note by the site (design, section 6). Two weeks at least, whatever the setting holds.
	 *
	 * A row that is due is lapsed under its lock (1.122.33), and read again once the lock is held: a
	 * row whose lock another request holds is being made a mentor, so it is left tonight, and one an
	 * activation finished since the list was read is active and stays so.
	 *
	 * @param int $now A Unix time, or 0 for now.
	 * @return int How many lapsed.
	 */
	public static function lapse( $now = 0 ) {
		$now    = $now > 0 ? (int) $now : time();
		$days   = max( self::LAPSE_FLOOR, (int) WPCPM_Settings::get_value( 'mentor_lapse_days', 90 ) );
		$lapsed = 0;

		foreach ( self::applications( array( self::STATE_SELECTED ) ) as $post ) {
			if ( 0 === self::selected_due( $post, $now, $days ) ) {
				continue;
			}

			$lock  = self::lock_name( (int) $post->ID );
			$token = WPCPM_Lock::take( $lock, self::LOCK_TTL );

			if ( '' === $token ) {
				continue;
			}

			try {
				self::forget_cached( (int) $post->ID );

				$selected = self::STATE_SELECTED === self::state_of( $post ) ? self::selected_due( $post, $now, $days ) : 0;

				if ( $selected > 0 ) {
					$note = sprintf(
						/* translators: 1: the day the applicant was selected, 2: how many days the course had. */
						__( 'Selected on %1$s, and the mentors\' course had not shown %2$s days later.', 'wpcredits-program-manager' ),
						wp_date( 'Y-m-d', $selected ),
						number_format_i18n( $days )
					);

					if ( self::decide( (int) $post->ID, self::STATE_LAPSED, self::EVENT_LAPSED, 0, $note, $now ) ) {
						++$lapsed;
					}
				}
			} finally {
				WPCPM_Lock::release( $lock, $token );
			}
		}

		return $lapsed;
	}

	/**
	 * When a selected row was last selected, if the clock has run out on it.
	 *
	 * @param WP_Post $post The application.
	 * @param int     $now  A Unix time.
	 * @param int     $days The days a selected row has.
	 * @return int The time of its last selection when it is due to lapse, or 0 when it is not.
	 */
	private static function selected_due( WP_Post $post, $now, $days ) {
		$selected = self::last_at( (int) $post->ID, self::EVENT_SELECTED );
		$selected = $selected > 0 ? $selected : (int) get_post_time( 'U', true, $post );

		return $selected > $now - $days * DAY_IN_SECONDS ? 0 : max( 1, $selected );
	}

	/**
	 * Delete the rows the retention settings say have been kept long enough: the three settings the
	 * institution and sponsor applications keep, each in days, 0 meaning never. A spam row goes after
	 * the spam days, a declined, lapsed or held row after the rejected days, and an active row after
	 * the approved days; a new, info or second row is never deleted, and a selected one lapses first
	 * (`lapse()`). The clock of a spam, declined or lapsed row runs from its decision (`decided_at()`),
	 * so a decision restarts it and an address confirmed afterwards does not; a held or an active row
	 * has no decision, and its clock runs from the last thing that happened to it. A held row is open,
	 * waiting for a person, and is deleted all the same, as the sponsor application's is. An active row
	 * whose Mentors row is still to be written (`pending` or `refused`) is kept whatever the approved
	 * days say, since Write the Airtable row again needs its answers (1.122.33); the daily run settles
	 * one the sync linked, or whose account is gone, before this reads it.
	 *
	 * @param int $now A Unix time, or 0 for now.
	 * @return int How many were deleted.
	 */
	public static function purge( $now = 0 ) {
		$now       = $now > 0 ? (int) $now : time();
		$retention = array(
			self::STATE_SPAM     => 'application_spam_days',
			self::STATE_DECLINED => 'application_rejected_days',
			self::STATE_LAPSED   => 'application_rejected_days',
			self::STATE_HELD     => 'application_rejected_days',
			self::STATE_ACTIVE   => 'application_approved_days',
		);
		$purged    = 0;

		foreach ( $retention as $state => $setting ) {
			$days = (int) WPCPM_Settings::get_value( $setting, 0 );

			if ( $days < 1 ) {
				continue;
			}

			foreach ( self::applications( array( $state ) ) as $post ) {
				if ( self::STATE_ACTIVE === $state && self::row_unwritten( $post ) ) {
					continue;
				}

				// A spam, declined or lapsed row counts from its decision, which an address confirmed
				// afterwards does not move. `decided_at()` is 0 for a row with none, and that row counts
				// from the last thing that happened to it.
				$last = self::decided_at( $post );
				$last = $last > 0 ? $last : self::last_at( (int) $post->ID );
				$last = $last > 0 ? $last : (int) get_post_time( 'U', true, $post );

				if ( $last <= $now - $days * DAY_IN_SECONDS && self::forget( $post, $days, 0 ) ) {
					++$purged;
				}
			}
		}

		return $purged;
	}

	/**
	 * Whether an application's Mentors row is still to be written: its state `pending` or `refused`
	 * (1.122.33).
	 *
	 * @param WP_Post $post The application.
	 * @return bool
	 */
	public static function row_unwritten( WP_Post $post ) {
		$row = self::airtable_of( $post );

		return isset( $row['state'] ) && in_array( $row['state'], array( 'pending', 'refused' ), true );
	}

	/**
	 * Delete one row for good, keeping what it counted for the feed and a line in the log.
	 *
	 * @param WP_Post $post  The row.
	 * @param int     $days  The retention setting that removed it, or 0 for a person's hand.
	 * @param int     $actor Who did it; 0 for the daily run.
	 * @return bool Whether the row is gone.
	 */
	public static function forget( WP_Post $post, $days, $actor ) {
		$counts    = self::month_counts_of( $post );
		$state     = self::state_of( $post );
		$reference = self::reference_of( $post );
		$post_id   = (int) $post->ID;

		if ( ! wp_delete_post( $post_id, true ) ) {
			return false;
		}

		$kept = self::kept_counts();

		foreach ( $counts as $month => $row ) {
			foreach ( $row as $key => $count ) {
				$kept[ $month ][ $key ] = ( isset( $kept[ $month ][ $key ] ) ? (int) $kept[ $month ][ $key ] : 0 ) + (int) $count;
			}
		}

		ksort( $kept );
		update_option( self::OPT_KEPT, $kept, false );

		$log   = self::deletion_log();
		$log[] = array(
			'at'        => time(),
			'id'        => $post_id,
			'reference' => sanitize_text_field( $reference ),
			'state'     => sanitize_key( $state ),
			'days'      => (int) $days,
			'actor'     => (int) $actor,
		);

		update_option( self::OPT_LOG, array_slice( $log, -self::LOG_MAX ), false );

		return true;
	}

	/**
	 * What one row counts for the feed, by month on the site's clock. The feed answers how many
	 * applications reached a step in a month, never how many events there were (design, section 3,
	 * decision 10), so an application counts at most once per figure in a month, each figure 0 or 1:
	 * received where it arrived, unless it is spam; selected, activated or declined in each month it
	 * was; and lapsed in each month it lapsed or was withdrawn, a withdrawal and a lapse in one month
	 * being one lapse. An application selected twice in a month counts once there, and one selected
	 * again in a later month, after a lapse or an appeal, counts in each month it was selected, so
	 * the months are not to be added up as if each application counted once in all. A spam row counts
	 * for nothing, as the sponsors feed counts none.
	 *
	 * Only those decisions are counted: `EVENT_SELECTED`, `EVENT_ACTIVATED`, `EVENT_DECLINED`,
	 * `EVENT_LAPSED` and `EVENT_WITHDRAWN`. An event that is not a decision never is: not
	 * `EVENT_ALREADY_MENTOR` (an applicant who was a mentor already is filed as one, and was not made
	 * one, so is no activation), not `EVENT_AIRTABLE_WRITTEN`, `EVENT_AIRTABLE_REFUSED` or
	 * `EVENT_AIRTABLE_SETTLED` (what became of the Mentors row, which settling closes without a
	 * write from here), not the acknowledgements, and not the confirming of an address, a question, a
	 * second opinion or a reopening.
	 *
	 * @param WP_Post $post The row.
	 * @return array<string, array{received: int, selected: int, activated: int, declined: int, lapsed: int}> Each figure 0 or 1.
	 */
	public static function month_counts_of( WP_Post $post ) {
		if ( self::STATE_SPAM === self::state_of( $post ) ) {
			return array();
		}

		$counted = array(
			self::EVENT_SELECTED  => 'selected',
			self::EVENT_ACTIVATED => 'activated',
			self::EVENT_DECLINED  => 'declined',
			self::EVENT_LAPSED    => 'lapsed',
			self::EVENT_WITHDRAWN => 'lapsed',
		);
		$out     = array();
		$add     = static function ( $month, $key ) use ( &$out ) {
			if ( ! isset( $out[ $month ] ) ) {
				$out[ $month ] = array_fill_keys( array( 'received', 'selected', 'activated', 'declined', 'lapsed' ), 0 );
			}

			// Set, never added to: the figure is whether the application reached the step that month.
			$out[ $month ][ $key ] = 1;
		};

		$add( substr( (string) $post->post_date, 0, 7 ), 'received' );

		foreach ( self::events( (int) $post->ID ) as $row ) {
			if ( isset( $counted[ $row['event'] ] ) ) {
				$add( wp_date( 'Y-m', $row['at'] ), $counted[ $row['event'] ] );
			}
		}

		ksort( $out );

		return $out;
	}

	/**
	 * What deleted rows counted for the feed, by month (`OPT_KEPT`): each deletion (`forget()`) adds
	 * the row's `month_counts_of()` to them, and the feed adds them, as they were kept, to what the
	 * rows the site still holds count, so retention never rewrites a month the feed has shown.
	 *
	 * @return array
	 */
	public static function kept_counts() {
		$kept = get_option( self::OPT_KEPT, array() );

		return is_array( $kept ) ? $kept : array();
	}

	/**
	 * The deletions retention and people have made, oldest first.
	 *
	 * @return array[]
	 */
	public static function deletion_log() {
		$log = get_option( self::OPT_LOG, array() );

		return is_array( $log ) ? array_values( $log ) : array();
	}

	/**
	 * Delete every row, the log, the kept counts and the daily run. Called on uninstall.
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
			wp_delete_post( (int) $post_id, true );
		}

		delete_option( self::OPT_LOG );
		delete_option( self::OPT_KEPT );
		self::unschedule();
	}
}
