<?php
/**
 * Students module - the student registration, as the site keeps it (1.123.54).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per registration a student sent on the site: the answers, what was agreed to,
 * where the registration stands and what became of it (design of 5 November 2026, section 6).
 *
 * The record holds a stranger's answers and is reached only through the Students screen, which asks
 * the capability first; no generic screen, no REST route and no query variable reaches it. It is
 * modeled on the mentor application's record: the answers are site fields keyed by the form's
 * question keys, written as a slashed copy so a typed backslash survives core's unslashing.
 *
 * **The states.** `waiting` (kept on the site, to be written to Airtable: the form could not reach
 * it, or the process has not come to it yet), `written` (the Students row exists), `account` (an
 * account on the site had the address), `already` (the address is held by an earlier registration or
 * a row; an entry under an earlier registration is short and keeps no answers, one a Students row
 * answered keeps them), `failed` (still unwritten after the wait) and
 * `spam` (caught by the guard). Only `waiting` and `written` hold an address: the site keeps at most
 * one of either per address (`held_for()`), a newer waiting one yielding to an older.
 *
 * **The outcome** is one array, replaced as it changes, never a history: the Students row written
 * (`record`), the rows an address matched (`records`), the account found (`user`), the names left
 * out of the row (`left_out`), the last error, the tries and when the last was made, when a create
 * was sent whose answer has not come back (`create_sent`, Ruling 9: set just before the create goes,
 * cleared when Airtable answers), and what became of the email (`mail`).
 *
 * **Retention** (`purge()`): spam after the spam days, every other settled registration after the
 * rejected days, each counted from when it settled; a registration still waiting is never removed.
 * A setting of 0 keeps that kind for ever.
 *
 * Nothing here sends anything or draws anything: the form, the process and the Registrations tab
 * read and write through this class.
 */
final class WPCPM_Student_Registration {

	/**
	 * The post type. Thirteen characters: `register_post_type()` refuses a name longer than twenty,
	 * and bin/test-roles.php measures every post type the plugin declares.
	 */
	const POST_TYPE = 'wpcpm_stu_reg';

	const STATE_WAITING = 'waiting';
	const STATE_WRITTEN = 'written';
	const STATE_ACCOUNT = 'account';
	const STATE_ALREADY = 'already';
	const STATE_FAILED  = 'failed';
	const STATE_SPAM    = 'spam';

	/**
	 * Post meta: the cleaned answers, keyed by the form's question keys. Absent on an `already` entry
	 * filed under an earlier registration.
	 */
	const META_FIELDS = '_wpcpm_sreg_fields';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_sreg_state';

	/** Post meta: `wp_hash()` of the lowercased address, for the one-per-address rule and the lock. */
	const META_EMAIL = '_wpcpm_sreg_email';

	/** Post meta: the consent evidence, as `WPCPM_Form_Guard::consent_evidence()` records it. */
	const META_CONSENT = '_wpcpm_sreg_consent';

	/** Post meta: what the form's checks raised, a list of slugs. */
	const META_SIGNALS = '_wpcpm_sreg_signals';

	/**
	 * Post meta: what became of the registration, one array (`outcome_of()`): `record`, `records`,
	 * `user`, `left_out`, `error`, `tries`, `tried_at`, `create_sent` and `mail`.
	 */
	const META_OUTCOME = '_wpcpm_sreg_outcome';

	/** Post meta: when the 72 hours run from, a Unix time. */
	const META_QUEUED = '_wpcpm_sreg_queued';

	/** Post meta: when the registration left `waiting`, a Unix time; absent while it waits. */
	const META_SETTLED = '_wpcpm_sreg_settled';

	/** Post meta: on an `already` entry, the ID of the registration that holds the address. */
	const META_FIRST = '_wpcpm_sreg_first';

	/**
	 * The option name an address's lock is kept under, with the address's hash after it
	 * (`lock_name()`, through `WPCPM_Lock`); the process's run lock is under the same prefix. The
	 * uninstaller sweeps the prefix.
	 */
	const LOCK_PREFIX = 'wpcpm_sreg_lock_';

	/** How long a lock holds at most, in seconds, so one a request that died left runs out by itself. */
	const LOCK_TTL = 300;

	/** How many hours a registration is tried again before it shows as could not be written. */
	const WAIT_HOURS = 72;

	/**
	 * Hooks: the post type.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the post type: invisible everywhere, since a row holds a person's name, address and
	 * free text, and the only way to one is the Students screen, which asks the capability first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Student registrations', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Student registration', 'wpcredits-program-manager' ),
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
				'capability_type'     => array( 'wpcpm_stu_reg', 'wpcpm_stu_regs' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The six states.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array(
			self::STATE_WAITING,
			self::STATE_WRITTEN,
			self::STATE_ACCOUNT,
			self::STATE_ALREADY,
			self::STATE_FAILED,
			self::STATE_SPAM,
		);
	}

	/**
	 * Store one registration and answer with its ID, or 0 when WordPress refused the row.
	 *
	 * The answers and the consent are written as a slashed copy, as core unslashes what it is handed,
	 * so a backslash a student typed is kept. An `already` entry keeps no answers and no name in its
	 * title, only the hash of the address and the registration that holds it (design, section 6).
	 *
	 * @param array  $fields  The cleaned answers, keyed by question key.
	 * @param string $state   `waiting`, `spam` or `already`; anything else is `waiting`.
	 * @param array  $signals What the checks raised.
	 * @param array  $consent The consent evidence.
	 * @param int    $first   For an `already` entry, the registration that holds the address.
	 * @return int
	 */
	public static function store( array $fields, $state, array $signals, array $consent, $first = 0 ) {
		$state = in_array( $state, array( self::STATE_SPAM, self::STATE_ALREADY ), true ) ? $state : self::STATE_WAITING;
		$name  = isset( $fields['name'] ) ? trim( (string) $fields['name'] ) : '';
		$email = isset( $fields['email'] ) ? trim( (string) $fields['email'] ) : '';

		if ( self::STATE_ALREADY === $state ) {
			$name = '';
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					// Nobody: the student has no account, and a row authored by the visitor's session
					// would read on every screen as though someone made it.
					'post_author' => 0,
					'post_title'  => '' !== $name ? $name : __( 'Registration with no name', 'wpcredits-program-manager' ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;
		$now     = time();

		if ( self::STATE_ALREADY !== $state ) {
			update_post_meta( $post_id, self::META_FIELDS, wp_slash( $fields ) );
		} else {
			update_post_meta( $post_id, self::META_FIRST, (int) $first );
		}

		update_post_meta( $post_id, self::META_STATE, $state );
		update_post_meta( $post_id, self::META_CONSENT, wp_slash( $consent ) );
		update_post_meta( $post_id, self::META_SIGNALS, array_values( array_unique( array_map( 'strval', $signals ) ) ) );
		update_post_meta( $post_id, self::META_EMAIL, self::email_hash( $email ) );
		update_post_meta( $post_id, self::META_QUEUED, $now );

		if ( self::STATE_WAITING !== $state ) {
			update_post_meta( $post_id, self::META_SETTLED, $now );
		}

		return $post_id;
	}

	/**
	 * The hash an address is compared and locked by: trimmed and lowercased, so `Ada@Example.test`
	 * and `ada@example.test` are one student, and '' for no address.
	 *
	 * @param string $email An address.
	 * @return string
	 */
	public static function email_hash( $email ) {
		$email = trim( (string) $email );

		return '' === $email ? '' : wp_hash( mb_strtolower( $email ) );
	}

	/**
	 * The name of an address's lock (`WPCPM_Lock`): the submit, the process and Write it now take it,
	 * so one of them at a time works on one address.
	 *
	 * @param string $email An address.
	 * @return string
	 */
	public static function lock_name( $email ) {
		return self::LOCK_PREFIX . substr( self::email_hash( $email ), 0, 32 );
	}

	/**
	 * A registration by ID, or null when the post is not one.
	 *
	 * @param int $post_id A post ID.
	 * @return WP_Post|null
	 */
	public static function registration( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * A registration's state.
	 *
	 * @param WP_Post $post The registration.
	 * @return string
	 */
	public static function state_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_STATE, true );
	}

	/**
	 * The answers as stored, keyed by question key; empty for an `already` entry filed under an
	 * earlier registration.
	 *
	 * @param WP_Post $post The registration.
	 * @return array
	 */
	public static function fields_of( WP_Post $post ) {
		$fields = get_post_meta( (int) $post->ID, self::META_FIELDS, true );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * The student's name as they typed it, or '' when the answers hold none.
	 *
	 * @param WP_Post $post The registration.
	 * @return string
	 */
	public static function name_of( WP_Post $post ) {
		$fields = self::fields_of( $post );

		return isset( $fields['name'] ) ? trim( (string) $fields['name'] ) : '';
	}

	/**
	 * The hash of the student's address, as stored.
	 *
	 * @param WP_Post $post The registration.
	 * @return string
	 */
	public static function email_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_EMAIL, true );
	}

	/**
	 * The consent evidence.
	 *
	 * @param WP_Post $post The registration.
	 * @return array
	 */
	public static function consent_of( WP_Post $post ) {
		$consent = get_post_meta( (int) $post->ID, self::META_CONSENT, true );

		return is_array( $consent ) ? $consent : array();
	}

	/**
	 * What the form's checks raised.
	 *
	 * @param WP_Post $post The registration.
	 * @return string[]
	 */
	public static function signals_of( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_SIGNALS, true );

		return is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'strval', $stored ), 'strlen' ) ) ) : array();
	}

	/**
	 * What became of the registration, every key present.
	 *
	 * @param WP_Post $post The registration.
	 * @return array
	 */
	public static function outcome_of( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_OUTCOME, true );

		return array_merge(
			array(
				'record'      => '',
				'records'     => array(),
				'user'        => 0,
				'left_out'    => array(),
				'error'       => '',
				'tries'       => 0,
				'tried_at'    => 0,
				'create_sent' => 0,
				'mail'        => '',
			),
			is_array( $stored ) ? $stored : array()
		);
	}

	/**
	 * The registration that holds an address against the one named: the oldest other than it with
	 * that hash that is `written`, or `waiting` with a lower ID (a newer waiting one yields to an
	 * older). Null when none does.
	 *
	 * @param string $hash       `email_hash()` of the address.
	 * @param int    $exclude_id The registration asking.
	 * @return WP_Post|null
	 */
	public static function held_for( $hash, $exclude_id ) {
		$hash = (string) $hash;

		if ( '' === $hash ) {
			return null;
		}

		foreach ( (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => -1,
				'orderby'     => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'meta_query'  => array(
					array(
						'key'     => self::META_EMAIL,
						'value'   => $hash,
						'compare' => '=',
					),
				),
			)
		) as $post ) {
			if ( ! $post instanceof WP_Post || (int) $post->ID === (int) $exclude_id ) {
				continue;
			}

			$state = self::state_of( $post );

			if ( self::STATE_WRITTEN === $state || ( self::STATE_WAITING === $state && (int) $post->ID < (int) $exclude_id ) ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Move a registration to a state, merging what is given into its outcome. Any state but
	 * `waiting` settles it.
	 *
	 * @param int    $post_id The registration.
	 * @param string $state   One of `states()`.
	 * @param array  $outcome Keys of the outcome to set.
	 * @return bool False for a state that is not one, or a post that is no registration.
	 */
	public static function set_state( $post_id, $state, array $outcome = array() ) {
		$post = self::registration( $post_id );

		if ( null === $post || ! in_array( $state, self::states(), true ) ) {
			return false;
		}

		update_post_meta( (int) $post_id, self::META_STATE, $state );
		update_post_meta( (int) $post_id, self::META_OUTCOME, array_merge( self::outcome_of( $post ), $outcome ) );

		if ( self::STATE_WAITING !== $state ) {
			update_post_meta( (int) $post_id, self::META_SETTLED, time() );
		}

		return true;
	}

	/**
	 * Note one try at writing a registration: the count, when, and why it did not settle.
	 *
	 * @param int    $post_id The registration.
	 * @param string $error   What went wrong.
	 * @param int    $now     The time of the try.
	 */
	public static function note_try( $post_id, $error, $now ) {
		$post = self::registration( $post_id );

		if ( null === $post ) {
			return;
		}

		$outcome = self::outcome_of( $post );

		update_post_meta(
			(int) $post_id,
			self::META_OUTCOME,
			array_merge(
				$outcome,
				array(
					'tries'    => (int) $outcome['tries'] + 1,
					'tried_at' => (int) $now,
					'error'    => (string) $error,
				)
			)
		);
	}

	/**
	 * Take one signal off a registration and leave the others as they were.
	 *
	 * @param int    $post_id The registration.
	 * @param string $signal  The signal's slug.
	 */
	public static function remove_signal( $post_id, $signal ) {
		$post = self::registration( $post_id );

		if ( null === $post ) {
			return;
		}

		update_post_meta( (int) $post_id, self::META_SIGNALS, array_values( array_diff( self::signals_of( $post ), array( (string) $signal ) ) ) );
	}

	/**
	 * Put a spam registration back in the queue: waiting, queued now, not settled, its outcome cleared.
	 *
	 * @param int $post_id The registration.
	 * @return bool False when it is not a spam registration.
	 */
	public static function requeue( $post_id ) {
		$post = self::registration( $post_id );

		if ( null === $post || self::STATE_SPAM !== self::state_of( $post ) ) {
			return false;
		}

		update_post_meta( (int) $post_id, self::META_STATE, self::STATE_WAITING );
		update_post_meta( (int) $post_id, self::META_QUEUED, time() );
		delete_post_meta( (int) $post_id, self::META_SETTLED );
		delete_post_meta( (int) $post_id, self::META_OUTCOME );

		return true;
	}

	/**
	 * The registrations in the given states, newest first unless asked otherwise, by ID within one
	 * second.
	 *
	 * @param array  $states  States to include; anything that is not a state is dropped, and none means no rows.
	 * @param int    $limit   Most rows to read, or 0 for every one.
	 * @param string $order   `DESC` or `ASC`.
	 * @param string $without A signal the rows listed do not carry, or '' for no such rule.
	 * @return WP_Post[]
	 */
	public static function in_states( array $states, $limit = 0, $order = 'DESC', $without = '' ) {
		$found = array();

		foreach ( self::query( $states, 'all', $limit, $order, $without ) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * How many registrations are in the given states, read as IDs.
	 *
	 * @param array $states States to count.
	 * @return int
	 */
	public static function count_in( array $states ) {
		return count( self::query( $states, 'ids', 0, 'DESC', '' ) );
	}

	/**
	 * The query behind `in_states()` and `count_in()`.
	 *
	 * A signal to leave out is read by the database: the signals are one serialized list, so the slug
	 * in its quotes is one element of it, and every registration carries the list, empty or not, since
	 * `store()` writes it. The rows a flood left are never loaded only to be passed over.
	 *
	 * @param array  $states  States to include.
	 * @param string $fields  `all` for posts, `ids` for IDs.
	 * @param int    $limit   Most rows, or 0 for every one.
	 * @param string $order   `DESC` or `ASC`.
	 * @param string $without A signal the rows do not carry, or ''.
	 * @return array
	 */
	private static function query( array $states, $fields, $limit, $order, $without ) {
		$wanted = array_values( array_intersect( self::states(), array_map( 'strval', $states ) ) );

		if ( empty( $wanted ) ) {
			return array();
		}

		$order   = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';
		$clauses = array(
			array(
				'key'     => self::META_STATE,
				'value'   => $wanted,
				'compare' => 'IN',
			),
		);

		if ( '' !== (string) $without ) {
			$clauses[] = array(
				'key'     => self::META_SIGNALS,
				'value'   => '"' . (string) $without . '"',
				'compare' => 'NOT LIKE',
			);
		}

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
				'meta_query'  => $clauses,
			)
		);
	}

	/**
	 * Delete what the retention settings say to: spam after the spam days, written, account,
	 * already and failed registrations after the rejected days, each counted from when it settled
	 * (the time it was queued for a row with no settled time). A waiting registration is never
	 * removed, and a setting of 0 keeps that kind for ever.
	 *
	 * @param int $now The time to count from, or 0 for now.
	 * @return int How many registrations were deleted.
	 */
	public static function purge( $now = 0 ) {
		$now       = $now > 0 ? (int) $now : time();
		$retention = array(
			self::STATE_SPAM    => 'application_spam_days',
			self::STATE_WRITTEN => 'application_rejected_days',
			self::STATE_ACCOUNT => 'application_rejected_days',
			self::STATE_ALREADY => 'application_rejected_days',
			self::STATE_FAILED  => 'application_rejected_days',
		);
		$purged    = 0;

		foreach ( $retention as $state => $setting ) {
			$days = (int) WPCPM_Settings::get_value( $setting, 0 );

			if ( $days < 1 ) {
				continue;
			}

			foreach ( self::in_states( array( $state ), 0, 'ASC' ) as $post ) {
				$last = (int) get_post_meta( (int) $post->ID, self::META_SETTLED, true );
				$last = $last > 0 ? $last : (int) get_post_meta( (int) $post->ID, self::META_QUEUED, true );
				$last = $last > 0 ? $last : (int) get_post_time( 'U', true, $post );

				if ( $last <= $now - $days * DAY_IN_SECONDS && wp_delete_post( (int) $post->ID, true ) ) {
					++$purged;
				}
			}
		}

		return $purged;
	}

	/**
	 * Drop this request's copy of a registration's meta, so the next read comes from the table.
	 * Called right after the address's lock is taken, before anything under it is read: a state
	 * checked under the lock must not be the one from before it.
	 *
	 * @param int $post_id The registration.
	 */
	public static function forget_cached( $post_id ) {
		wp_cache_delete( (int) $post_id, 'post_meta' );
	}

	/**
	 * Remove every registration, whatever its status (uninstall).
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
	}
}
