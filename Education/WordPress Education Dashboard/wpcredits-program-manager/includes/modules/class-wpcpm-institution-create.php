<?php
/**
 * Creating the students of one checked batch, a slice at a time.
 *
 * `WPCPM_Institution_Import` reads a school's file and decides; it never writes. This is the
 * half that writes, and it is a separate file for the same reason the reading is: the rules
 * about what makes a retry safe are worth reading without a CSV parser around them, and the
 * import's own suite asserts that `create_records()` appears nowhere in it.
 *
 * Three callers share one method. The confirm handler runs the first slice while somebody
 * watches; the Continue control runs the next; `CRON_TICK` runs the rest when nobody is
 * watching. **The cron caller has no acting user at all**, which is why every check in here
 * reads who confirmed off the batch post rather than off the request.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turn one confirmed batch into Students rows, safely and repeatedly.
 *
 * **Retrying must be free.** A slice can end in four ways: the budget runs out, the lock is
 * held, the base stops answering, or the process is killed between sending a create and
 * storing the ID it returned. Only the last of those can leave a record in Airtable that this
 * site does not know about, and the whole of the design below exists to make that recoverable:
 * the row's state is written *before* the request rather than after, so a row that was in
 * flight is identifiable afterwards, and every created record carries a `Site import key` this
 * site can search for, so the row in flight can be found again rather than created twice.
 */
final class WPCPM_Institution_Create {

	/**
	 * How many seconds of creating one slice attempts.
	 *
	 * A create is one HTTP request to Airtable, so twelve seconds is roughly a dozen students
	 * on a good connection. It is well inside the shortest `max_execution_time` this plugin
	 * has met on a shared host, which is the number that matters: a slice that is killed part
	 * way is recoverable but costs a cron round trip, and one that finishes is not.
	 *
	 * @var int
	 */
	const BUDGET = 12;

	/**
	 * How long a lock is honoured before it is treated as abandoned.
	 *
	 * Longer than a slice can take, so a request that is merely slow is never overtaken by a
	 * second one creating the same rows; short enough that a request killed while holding the
	 * lock does not strand the batch until somebody notices. The same two minutes the two
	 * syncs use, for the same reason.
	 *
	 * @var int
	 */
	const LOCK_TIMEOUT = 120;

	/**
	 * The option name a lock is taken under, one per institution.
	 *
	 * Per institution rather than per batch: only one batch per institution can be in flight,
	 * and a lock named after the batch would let a second batch of the same school run beside
	 * the first if one ever escaped that rule.
	 *
	 * @var string
	 */
	/** The batch store owns this name; taken from there so the two cannot drift. */
	const LOCK_PREFIX = WPCPM_Institution_Import::LOCK_PREFIX;

	/**
	 * Seconds until the next unattended slice.
	 *
	 * @var int
	 */
	const RETRY_IN = 30;

	/** Who pressed Confirm. Read on every later slice, including the ones with no user. */
	const META_ACTOR = '_wpcpm_batch_actor';

	/** The ground that confirm was allowed on: `manager` or `member`. */
	const META_GROUND = '_wpcpm_batch_ground';

	/** Why a batch stopped, for a program manager. */
	const META_REASON = '_wpcpm_batch_reason';

	/** Not attempted yet. */
	const ROW_PENDING = 'pending';

	/** A create was sent for this row and its answer has not been stored. */
	const ROW_CREATING = 'creating';

	/** Created, and the record ID is on the row. */
	const ROW_CREATED = 'created';

	/** Airtable refused this row. The message is on the row, verbatim. */
	const ROW_FAILED = 'failed';

	/** Not created, and not going to be: a verdict said so. */
	const ROW_BLOCKED = 'blocked';

	/** The audit kind for a batch that finished. */
	const LOG_CREATED = 'import_created';

	/** The audit kind for a batch that was parked part way. */
	const LOG_STOPPED = 'import_stopped';

	/** What every `Site import key` starts with. */
	const KEY_PREFIX = 'imp-';

	/**
	 * Hooks.
	 *
	 * Called from `WPCPM_Institutions::init()` beside the other institution modules.
	 */
	public static function init() {
		add_action( WPCPM_Institution_Import::CRON_TICK, array( __CLASS__, 'handle_tick' ) );
	}

	/**
	 * The `Site import key` one row is created under.
	 *
	 * **This is what tells "created a second ago, response lost" from "somebody else enrolled
	 * this student".** Email cannot: the address is the same in both cases. The batch post ID
	 * and the row's position in the stored list are both stable for the life of the batch, so
	 * the same row asks for the same key on every retry, which is the whole point. Nothing may
	 * ever reindex `_wpcpm_batch_rows`: the index is half of this string.
	 *
	 * @param int $batch_id Batch post ID.
	 * @param int $index    The row's key in the stored row list.
	 * @return string
	 */
	public static function key_for( $batch_id, $index ) {
		return self::KEY_PREFIX . (int) $batch_id . '-' . (int) $index;
	}

	/**
	 * Whether a key is one of ours, in the shape `key_for()` makes.
	 *
	 * Checked before the key goes into a `filterByFormula`, which is a string: a value that
	 * could carry an apostrophe would end the quoted literal and change the query into
	 * something else entirely. Every key this module makes is digits and hyphens, so anything
	 * else is a stored value that has been tampered with or corrupted, and the search is
	 * skipped rather than sent.
	 *
	 * @param string $key The stored key.
	 * @return bool
	 */
	public static function is_key( $key ) {
		return (bool) preg_match( '/^' . self::KEY_PREFIX . '\d+-\d+$/', (string) $key );
	}

	/**
	 * The nonce action the Confirm button carries.
	 *
	 * **Keyed to the batch and to what the batch says.** The batch ID alone would let a token
	 * minted for one school's list be replayed against another's, and the fingerprint of the
	 * rows on top of it means a token minted for the preview somebody read is not a token for
	 * a list that has changed since. Both halves are derived from the stored post, so the
	 * button and the handler compute the same string without either trusting the request.
	 *
	 * @param int $batch_id Batch post ID.
	 * @return string
	 */
	public static function confirm_action( $batch_id ) {
		$batch_id = (int) $batch_id;
		$batch    = WPCPM_Institution_Import::batch( $batch_id );

		return WPCPM_Institution_Import::ACTION_CONFIRM . '_' . $batch_id . '_' . self::fingerprint( is_array( $batch ) ? $batch : array() );
	}

	/**
	 * The nonce action the Continue button carries.
	 *
	 * The batch ID and nothing else, deliberately. A batch being created changes on every
	 * slice as record IDs are stamped onto its rows, so a fingerprint here would invalidate
	 * the button the moment the first student was created, and the reader watching the page
	 * would be told their token was stale for carrying on with their own import.
	 *
	 * @param int $batch_id Batch post ID.
	 * @return string
	 */
	public static function continue_action( $batch_id ) {
		return WPCPM_Institution_Import::ACTION_CONTINUE . '_' . (int) $batch_id;
	}

	/**
	 * A short, stable digest of what a staged batch says.
	 *
	 * The institution and the batch-wide answers, plus the line, name, address and verdict of
	 * every row: everything a reader was shown and agreed to. Record IDs and row states are
	 * left out on purpose, so this is constant for as long as the batch is staged.
	 *
	 * @param array $batch A batch from `WPCPM_Institution_Import::batch()`.
	 * @return string
	 */
	private static function fingerprint( array $batch ) {
		$values = isset( $batch['values'] ) && is_array( $batch['values'] ) ? $batch['values'] : array();
		$canon  = array(
			'institution' => isset( $batch['institution'] ) ? (string) $batch['institution'] : '',
			'status'      => isset( $values['status'] ) ? (string) $values['status'] : '',
			'start'       => isset( $values['start'] ) ? (string) $values['start'] : '',
			'end'         => isset( $values['end'] ) ? (string) $values['end'] : '',
			'rows'        => array(),
		);

		foreach ( isset( $batch['rows'] ) && is_array( $batch['rows'] ) ? $batch['rows'] : array() as $index => $row ) {
			$canon['rows'][] = array(
				(int) $index,
				isset( $row['line'] ) ? (int) $row['line'] : 0,
				isset( $row['name'] ) ? (string) $row['name'] : '',
				isset( $row['email_key'] ) ? (string) $row['email_key'] : '',
				isset( $row['verdict'] ) ? (string) $row['verdict'] : '',
			);
		}

		return md5( (string) wp_json_encode( $canon ) );
	}

	/**
	 * Record who confirmed a batch, and on what ground.
	 *
	 * Written once, by the confirm handler, while there is still a request with a user on it.
	 * Every slice afterwards re-asks the policy about this account, which is the only way a
	 * continuation running under cron can be held to the same rule as the request that
	 * started it.
	 *
	 * @param int    $batch_id Batch post ID.
	 * @param int    $actor    The account that pressed Confirm.
	 * @param string $ground   The ground `decide()` allowed it on.
	 */
	public static function claim( $batch_id, $actor, $ground ) {
		update_post_meta( (int) $batch_id, self::META_ACTOR, absint( $actor ) );
		update_post_meta( (int) $batch_id, self::META_GROUND, sanitize_key( (string) $ground ) );
	}

	/**
	 * Who confirmed this batch.
	 *
	 * @param int $batch_id Batch post ID.
	 * @return int User ID, or 0 when nothing claimed it.
	 */
	public static function actor_of( $batch_id ) {
		return absint( get_post_meta( (int) $batch_id, self::META_ACTOR, true ) );
	}

	/**
	 * Whether this batch may create anything right now.
	 *
	 * **This is the one check that has to happen on every slice, whoever is running it.** A
	 * three hundred row batch takes a dozen slices over several minutes, most of them under
	 * cron with nobody signed in; an agreement returned, or a member removed, part way through
	 * that has to stop the rest. Asking `wp_get_current_user()` here would answer "nobody"
	 * under cron and would therefore refuse every unattended slice, and asking nothing at all
	 * would let a revoke during a long batch stop precisely nothing. So the account that
	 * pressed Confirm is stored on the batch and re-asked here, every time.
	 *
	 * The agreement is checked separately from the policy rather than through it, because
	 * `ground_manager` is not gated by the agreement: a manager may act for an institution
	 * whose agreement is outstanding, but an import is the institution's own act on its own
	 * roster and stops with the agreement whoever pressed the button.
	 *
	 * @param array $batch A batch from `WPCPM_Institution_Import::batch()`.
	 * @return array{ok:bool,problem:string,ground:string} `problem` is `agreement`, `no-actor`
	 *                                                     or `not-allowed`.
	 */
	public static function may_run( array $batch ) {
		$institution = isset( $batch['institution'] ) ? (string) $batch['institution'] : '';

		if ( ! WPCPM_Institution_Agreement::is_settled( $institution ) ) {
			return array(
				'ok'      => false,
				'problem' => 'agreement',
				'ground'  => '',
			);
		}

		$actor = self::actor_of( isset( $batch['id'] ) ? $batch['id'] : 0 );

		// **An actor of 0 is refused here and never passed on.** `WPCPM_Roles::resolve_user()`
		// treats a zero as "no argument" and falls back to the current user, so handing it
		// down would make an unclaimed batch decide against whoever happened to be signed in,
		// and under cron against nobody at all. Neither is the account that confirmed.
		if ( $actor < 1 ) {
			return array(
				'ok'      => false,
				'problem' => 'no-actor',
				'ground'  => '',
			);
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_ADD_STUDENT,
			WPCPM_Institution_Policy::subject_institution( $institution ),
			$actor
		);

		if ( empty( $decision['allowed'] ) ) {
			return array(
				'ok'      => false,
				'problem' => 'not-allowed',
				'ground'  => '',
			);
		}

		// Re-derived rather than read back off the batch: a manager who has since become an
		// ordinary member of this institution is still allowed, and the log should say which
		// ground actually carried the slice rather than which one carried the first.
		return array(
			'ok'      => true,
			'problem' => '',
			'ground'  => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
		);
	}

	/**
	 * Create as much of one batch as fits in the budget.
	 *
	 * The five steps of section 7.6, in order: the guard, the lock, the ladder again, the
	 * rows, and then either a scheduled continuation or the finish.
	 *
	 * @param int      $batch_id Batch post ID.
	 * @param int|null $budget   Seconds of creating to attempt; null for `BUDGET`.
	 * @return array{ok:bool,problem:string,state:string,created:int,blocked:int,failed:int,remaining:int}
	 */
	public static function create_slice( $batch_id, $budget = null ) {
		$batch_id = (int) $batch_id;
		$batch    = WPCPM_Institution_Import::batch( $batch_id );

		if ( ! is_array( $batch ) ) {
			return self::outcome( 'no-batch', '', array() );
		}

		$institution = (string) $batch['institution'];

		// A batch whose institution is unreadable creates nothing at all. Every record this
		// module writes carries `Educational Institutions`, and a batch that cannot say which
		// institution it is for could only produce rows belonging to nobody, which is the one
		// shape of orphan the fence around this module cannot see afterwards.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return self::outcome( 'no-institution', '', $batch['rows'] );
		}

		// Done, or already parked. A second Confirm of a finished batch lands here and creates
		// nothing, which is exactly what a person pressing the browser's back button does.
		if ( ! in_array( $batch['state'], array( WPCPM_Institution_Import::STATE_STAGED, WPCPM_Institution_Import::STATE_CREATING ), true ) ) {
			return self::outcome( 'wrong-state', $batch['state'], $batch['rows'] );
		}

		// **The lock is taken before the guard, not after.** Both orders stop a revoked batch
		// within one slice, because the request holding the lock ran the same guard when it
		// started. Parking from out here while another request is mid-slice would write a
		// state that request then overwrites when it finishes, so the log would carry a stop
		// that did not happen. One writer at a time is worth the extra slice.
		if ( ! self::acquire_lock( $institution ) ) {
			return self::outcome( 'locked', $batch['state'], $batch['rows'] );
		}

		$allowed = self::may_run( $batch );

		if ( ! $allowed['ok'] ) {
			self::park( $batch, $allowed['problem'] );
			self::release_lock( $institution );

			return self::outcome( $allowed['problem'], WPCPM_Institution_Import::STATE_BLOCKED, $batch['rows'] );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$rows     = $batch['rows'];

		if ( WPCPM_Institution_Import::STATE_STAGED === $batch['state'] ) {
			$rows = self::begin_rows( $rows );
			self::save_rows( $batch_id, $rows );
			update_post_meta( $batch_id, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );
		}

		$rechecked = self::recheck( $rows, $institution, $airtable, $settings );

		if ( null !== $rechecked['error'] ) {
			// Nothing is created on a slice that could not re-read the base. The verdicts in
			// hand are as old as the preview, and creating against them would be creating on
			// the strength of a check this slice could not make.
			self::save_rows( $batch_id, $rechecked['rows'] );
			self::release_lock( $institution );
			self::schedule( $batch_id );

			return self::outcome( 'unreadable', WPCPM_Institution_Import::STATE_CREATING, $rechecked['rows'] );
		}

		$rows = $rechecked['rows'];
		self::save_rows( $batch_id, $rows );

		$ran  = self::run_rows( $batch, $rows, $airtable, $settings, $allowed['ground'], $budget );
		$rows = $ran['rows'];

		self::release_lock( $institution );

		// A slice the base stopped is a slice to try again, not a batch to finish. The rows it
		// did create are created and on the roster; the rest are still pending or still
		// `creating`, and the next tick picks them up.
		if ( ! empty( $ran['halt'] ) ) {
			self::schedule( $batch_id );

			return self::outcome( 'unreadable', WPCPM_Institution_Import::STATE_CREATING, $rows );
		}

		if ( self::remaining( $rows ) > 0 ) {
			self::schedule( $batch_id );

			return self::outcome( 'progress', WPCPM_Institution_Import::STATE_CREATING, $rows );
		}

		self::finish( $batch, $rows, $allowed['ground'] );

		return self::outcome( 'created', WPCPM_Institution_Import::STATE_DONE, $rows );
	}

	/** Airtable answered and refused this particular record. The next row may be fine. */
	const BLAME_ROW = 'row';

	/** Refused before anything was sent, so the row can go back to pending. */
	const BLAME_UNSENT = 'unsent';

	/** The base, or the wire. It may have arrived, so the row stays recoverable. */
	const BLAME_BASE = 'base';

	/**
	 * Create every pending row that fits in the budget.
	 *
	 * The batch ID is taken from the batch rather than passed beside it, so the key this
	 * searches for and the key `fields_for()` writes cannot come from two different numbers:
	 * a row searched for under one key and created under another is a duplicate on every retry.
	 *
	 * @param array          $batch    The batch, for its ID, its values and its institution.
	 * @param array          $rows     The rows, with their states.
	 * @param WPCPM_Airtable $airtable A configured client.
	 * @param array          $settings Plugin settings, for the Students table.
	 * @param string         $ground   The ground this slice is allowed on, for the request log.
	 * @param int|null       $budget   Seconds; null for `BUDGET`.
	 * @return array The rows, as they now stand.
	 */
	private static function run_rows( array $batch, array $rows, $airtable, array $settings, $ground, $budget ) {
		$batch_id    = (int) $batch['id'];
		$institution = (string) $batch['institution'];
		$table       = isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '';
		$actor       = self::actor_of( $batch_id );
		$deadline    = microtime( true ) + ( null === $budget ? self::BUDGET : max( 1, (int) $budget ) );

		foreach ( $rows as $index => $row ) {
			if ( microtime( true ) >= $deadline ) {
				break;
			}

			$state = self::state_of( $row );

			if ( ! in_array( $state, array( self::ROW_PENDING, self::ROW_CREATING ), true ) ) {
				continue;
			}

			if ( '' !== self::record_of( $row ) ) {
				continue;
			}

			$key = self::key_for( $batch_id, $index );

			if ( self::ROW_CREATING === $state ) {
				// **A row left in `creating` had a request sent for it and no answer stored.**
				// Creating it again is how one lost response becomes two students, two welcome
				// emails and a duplicate a program manager has to unpick by hand, so this asks
				// the base what happened before it asks it to do anything.
				$found = self::find_created( $airtable, $settings, $key, isset( $row['email_key'] ) ? (string) $row['email_key'] : '' );

				if ( is_wp_error( $found ) ) {
					// The base is not answering. Leave the row exactly as it is: `creating`
					// with no ID is the state that makes the next slice search again.
					break;
				}

				if ( '' !== $found['record'] ) {
					$rows[ $index ] = self::stamp( $row, $found['record'], $key );
					self::save_rows( $batch_id, $rows );
					self::announce( $institution, $batch, $rows[ $index ], $found['record'], $actor, $ground );
					continue;
				}

				if ( $found['other'] ) {
					// The address is on a record that does not carry this batch's key, so it
					// is not the one this row sent: somebody enrolled this student in between.
					// Stamping that ID would fence the wrong person onto this school's roster.
					$rows[ $index ]['state']   = self::ROW_BLOCKED;
					$rows[ $index ]['verdict'] = WPCPM_Institution_Import::BLOCKED;
					$rows[ $index ]['changed'] = true;
					self::save_rows( $batch_id, $rows );
					continue;
				}
			}

			$fields = self::fields_for( $batch, $row, $index );

			if ( empty( $fields ) ) {
				$rows[ $index ]['state'] = self::ROW_FAILED;
				$rows[ $index ]['error'] = __( 'This row could not be turned into a record.', 'wpcredits-program-manager' );
				self::save_rows( $batch_id, $rows );
				continue;
			}

			// **Written before the request and never after.** A process killed between the
			// send and the answer leaves this row saying `creating` with no ID, which is the
			// only state from which the search above can recover it. Saving afterwards would
			// leave it saying `pending`, and the next slice would create it a second time.
			$rows[ $index ]['state'] = self::ROW_CREATING;
			self::save_rows( $batch_id, $rows );

			// **One row per call.** `create_records()` chunks and returns a re-indexed list of
			// the IDs Airtable accepted, so a batch of ten that drops the third returns nine
			// and every ID after it belongs to the row before: the wrong ID stamped is the
			// wrong person fenced onto this school's roster, and nothing downstream could tell.
			$created = $airtable->create_records( $table, array( array( 'fields' => $fields ) ) );

			if ( is_wp_error( $created ) ) {
				$blame = self::blame( $created );

				if ( self::BLAME_ROW !== $blame ) {
					// **Not this row's fault, so not this row's failure.** A missing token, an
					// open rate-limit window and a 500 refuse every call that follows just as
					// surely as this one: marking the row failed and carrying on would walk
					// the rest of the batch into the same wall and finish it, three hundred
					// students terminally failed by one expired credential. The slice stops
					// and the batch is rescheduled instead.
					//
					// Where nothing was sent the row goes back to pending. Where it may have
					// been sent and the answer was lost, it stays `creating`, which is the one
					// state `find_created()` can recover from: the record may exist in Airtable
					// under the `Site import key` written for it, and calling that row failed
					// would strand the student, created and on nobody's roster.
					$rows[ $index ]['state'] = self::BLAME_UNSENT === $blame ? self::ROW_PENDING : self::ROW_CREATING;
					$rows[ $index ]['error'] = (string) $created->get_error_message();
					self::save_rows( $batch_id, $rows );

					return array(
						'rows' => $rows,
						'halt' => true,
					);
				}

				// Verbatim, because the message is Airtable's own account of what it refused
				// and a paraphrase would cost the program manager the one clue there is.
				$rows[ $index ]['state'] = self::ROW_FAILED;
				$rows[ $index ]['error'] = (string) $created->get_error_message();
				self::save_rows( $batch_id, $rows );
				continue;
			}

			$record = isset( $created[0] ) ? (string) $created[0] : '';

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
				$rows[ $index ]['state'] = self::ROW_FAILED;
				$rows[ $index ]['error'] = __( 'The program records accepted nothing for this row.', 'wpcredits-program-manager' );
				self::save_rows( $batch_id, $rows );
				continue;
			}

			$rows[ $index ] = self::stamp( $row, $record, $key );
			self::save_rows( $batch_id, $rows );
			self::announce( $institution, $batch, $rows[ $index ], $record, $actor, $ground );
		}

		return array(
			'rows' => $rows,
			'halt' => false,
		);
	}

	/**
	 * Whose fault a refusal is: this row's, or the base's.
	 *
	 * **The difference decides whether the batch survives.** `create_records()` answers with a
	 * `WP_Error` for two quite different things, and the loop used to treat them alike: a value
	 * Airtable would not accept in this record, and a condition that will refuse every call
	 * after it as well. An expired token or an open rate-limit window would have marked three
	 * hundred students terminally failed in one pass and finished the batch, with nothing left
	 * pending for a later slice to pick up.
	 *
	 * Three answers rather than two, because "was it sent" matters as much as "whose fault":
	 * a row whose request never left is safe to put back to pending, and a row whose answer was
	 * lost has to stay `creating` so the import-key search can find what it may have created.
	 *
	 * @param WP_Error $error What `create_records()` answered with.
	 * @return string One of the three BLAME constants.
	 */
	private static function blame( $error ) {
		$code = $error instanceof WP_Error ? (string) $error->get_error_code() : '';

		// Refused before a socket was opened: no token, no base, no table, or a backoff window
		// this client is honouring on Airtable's own instruction.
		if ( in_array( $code, array( 'wpcpm_no_token', 'wpcpm_no_base', 'wpcpm_no_table', 'wpcpm_airtable_rate_limited' ), true ) ) {
			return self::BLAME_UNSENT;
		}

		if ( 'wpcpm_airtable_error' === $code ) {
			$data   = $error instanceof WP_Error ? $error->get_error_data() : array();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

			// Airtable answered and named this record: an unknown field, a value outside a
			// single select, a malformed date. The next row may be perfectly good, so the loop
			// carries on. 401, 403 and 429 are about the credential rather than the record, and
			// a 5xx is the base being unwell; all of those refuse the next row too.
			if ( $status >= 400 && $status < 500 && ! in_array( $status, array( 401, 403, 429 ), true ) ) {
				return self::BLAME_ROW;
			}

			return self::BLAME_BASE;
		}

		// A transport failure, or an answer this client could not parse. Either may have
		// reached Airtable, so the row is not written off.
		return self::BLAME_BASE;
	}

	/**
	 * Put a record ID on a row and call it created.
	 *
	 * @param array  $row    The row.
	 * @param string $record Students record ID.
	 * @param string $key    The `Site import key` it was created under.
	 * @return array
	 */
	private static function stamp( array $row, $record, $key ) {
		$row['state']      = self::ROW_CREATED;
		$row['record_id']  = (string) $record;
		$row['import_key'] = (string) $key;

		return $row;
	}

	/**
	 * Put a created student on the roster, and ask a program manager for a mentor.
	 *
	 * Both are deliberately after the ID is stored. Neither can be repeated harmlessly from
	 * outside - a second index row is only an overwrite, but a second request is a second line
	 * on somebody's queue - so they run once, on the pass that learned the ID.
	 *
	 * @param string $institution Institutions record ID.
	 * @param array  $batch       The batch, for its values.
	 * @param array  $row         The created row.
	 * @param string $record      Students record ID.
	 * @param int    $actor       Who confirmed the batch.
	 * @param string $ground      The ground this slice is allowed on.
	 */
	private static function announce( $institution, array $batch, array $row, $record, $actor, $ground ) {
		// So the school sees the student now rather than after tomorrow's sync. The read time
		// is left where the sync put it: one row is fresh and the rest are as old as they were.
		WPCPM_Roster_Index::insert( $institution, self::index_row( $batch, $row, $record ) );

		// A `WP_Error` here is not a reason to undo anything: the student exists, and the one
		// thing that can go wrong is a request already open for them, which is the state this
		// would have been asking for anyway.
		WPCPM_Institution_Request::raise( WPCPM_Institution_Request::KIND_ADD, $institution, $record, $actor, $ground );
	}

	/**
	 * One created row in the roster index's shape.
	 *
	 * `mentor_name`, `team`, `website`, `reports` and `user_id` are left empty on purpose:
	 * every one of them comes from the Students Reports row and the account, and neither
	 * exists yet. The sync fills them in when a mentor is assigned, which is the moment the
	 * automation creates the report record.
	 *
	 * @param array  $batch  The batch, for the program and the dates.
	 * @param array  $row    The created row.
	 * @param string $record Students record ID.
	 * @return array
	 */
	private static function index_row( array $batch, array $row, $record ) {
		$values = isset( $batch['values'] ) && is_array( $batch['values'] ) ? $batch['values'] : array();
		$get    = function ( $key ) use ( $row ) {
			return isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
		};

		return array(
			'record_id'      => (string) $record,
			'name'           => $get( 'name' ),
			'email'          => $get( 'email_key' ),
			'email_key'      => $get( 'email_key' ),
			'status'         => isset( $values['status'] ) ? (string) $values['status'] : '',
			'institution'    => (string) $batch['institution'],
			'start'          => isset( $values['start'] ) ? (string) $values['start'] : '',
			'end'            => isset( $values['end'] ) ? (string) $values['end'] : '',
			'has_mentor'     => false,
			'username'       => $get( 'handle' ),
			'field_of_study' => $get( 'field_of_study' ),
			'tutor'          => $get( 'tutor' ),
			'import_key'     => $get( 'import_key' ),
		);
	}

	/**
	 * The cells one student is created with.
	 *
	 * **`Educational Institutions` comes from the batch and can never come from a request.**
	 * The batch stored it at check time from `resolve_institution()`, and every handler since
	 * has read it off the post; a cell built from anything a form posted would let one school
	 * file a student under another, and an empty one would produce a row belonging to nobody
	 * that no roster and no fence in this module can see. So a malformed institution returns
	 * no map at all and the caller refuses the row rather than sending it.
	 *
	 * The optional cells are absent rather than empty when there is nothing to say. Airtable
	 * is sent no `typecast`, so an empty string in a single-select is a 422 for the whole
	 * record, and a school that left a column out would lose the student rather than the cell.
	 *
	 * Not written, and each for its own reason: `Mentor` is an assignment a program manager
	 * makes, not something a school may ask for; `Privacy Policy Compliance` and `English and
	 * proactivity acceptance` are the student's own acts and the batch's confirmation tick is
	 * the school's statement, not theirs; `Notes` is the program's working note; `Total hours`,
	 * `Students Reports` and `Feedback` are written by the automation that fires when a mentor
	 * is assigned, and pre-creating any of them is how a second report row appears.
	 *
	 * @param array $batch The batch, for its institution and its batch-wide answers.
	 * @param array $row   A cleaned row.
	 * @param int   $index The row's key, for the import key.
	 * @return array Cells for `create_records()`, or an empty array when the row cannot be sent.
	 */
	public static function fields_for( array $batch, array $row, $index ) {
		$institution = isset( $batch['institution'] ) ? (string) $batch['institution'] : '';
		$values      = isset( $batch['values'] ) && is_array( $batch['values'] ) ? $batch['values'] : array();
		$status      = isset( $values['status'] ) ? (string) $values['status'] : '';
		$start       = isset( $values['start'] ) ? (string) $values['start'] : '';
		$name        = isset( $row['name'] ) ? (string) $row['name'] : '';
		$email       = isset( $row['email_key'] ) ? (string) $row['email_key'] : '';

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return array();
		}

		// The program is the server's own map and the date is the form's checked one, but both
		// are read back out of storage here, and a batch stored before either was validated
		// would otherwise be created with a blank Status that Airtable refuses per record.
		if ( '' === $name || '' === $email || '' === $start || ! isset( WPCPM_Program::labels()[ $status ] ) ) {
			return array();
		}

		$fields = WPCPM_Mentors_Sync::fields();

		$cells = array(
			$fields['student_record_name'] => $name,
			// The lowercased key rather than the address as typed: it is the same mailbox, and
			// it is the value every join in this plugin compares on.
			$fields['student_email']       => $email,
			$fields['student_status']      => $status,
			$fields['student_institution'] => array( $institution ),
			$fields['student_start']       => $start,
			$fields['student_import_key']  => self::key_for( isset( $batch['id'] ) ? $batch['id'] : 0, $index ),
		);

		$optional = array(
			$fields['student_end']     => isset( $values['end'] ) ? (string) $values['end'] : '',
			$fields['student_profile'] => isset( $row['profile'] ) ? (string) $row['profile'] : '',
			// Not in `WPCPM_Mentors_Sync::fields()`, which is the sync's list; both are spelled
			// here as the base spells them. `Tutor ` ends in a space, which is the column's
			// real name and not a typo: dropping it writes nothing and reads nothing back.
			'Your field of study'      => isset( $row['field_of_study'] ) ? (string) $row['field_of_study'] : '',
			'Tutor '                   => isset( $row['tutor'] ) ? (string) $row['tutor'] : '',
		);

		foreach ( $optional as $column => $value ) {
			if ( '' !== $value ) {
				$cells[ $column ] = $value;
			}
		}

		return $cells;
	}

	/**
	 * Give every row the state it starts the creating in.
	 *
	 * A verdict the preview could create becomes `pending`; everything else is `blocked` and
	 * is counted as such in the summary, so a school reading "24 created, 3 blocked" can add
	 * the numbers up to the list it sent.
	 *
	 * @param array $rows Rows as the check left them.
	 * @return array
	 */
	private static function begin_rows( array $rows ) {
		foreach ( $rows as $index => $row ) {
			$verdict = isset( $row['verdict'] ) ? (string) $row['verdict'] : WPCPM_Institution_Import::OK;

			$rows[ $index ]['state']     = self::creatable( $verdict ) ? self::ROW_PENDING : self::ROW_BLOCKED;
			$rows[ $index ]['record_id'] = isset( $row['record_id'] ) ? (string) $row['record_id'] : '';
		}

		return $rows;
	}

	/**
	 * Ask the base about the pending rows again, and block the ones whose answer has changed.
	 *
	 * **Rows already in `creating` are deliberately left out.** Such a row may have been
	 * created by a request whose answer was lost, in which case the base now holds a record
	 * with this row's own address linked to this very institution, and the ladder would
	 * faithfully report it as "already on your roster" and block a row that is about to be
	 * recovered by its import key. The search in the loop is what answers for those.
	 *
	 * @param array          $rows        The rows, with their states.
	 * @param string         $institution Institutions record ID.
	 * @param WPCPM_Airtable $airtable    A configured client.
	 * @param array          $settings    Plugin settings.
	 * @return array{rows:array,error:WP_Error|null}
	 */
	private static function recheck( array $rows, $institution, $airtable, array $settings ) {
		$ask = array();

		foreach ( $rows as $index => $row ) {
			if ( self::ROW_PENDING !== self::state_of( $row ) || '' !== self::record_of( $row ) ) {
				continue;
			}

			// Asked as an unjudged row so the ladder actually looks at it: `check_against_base()`
			// only judges rows whose verdict is `ok`, and a `near-name` row left as it was
			// would sail past the one check that could still stop it.
			$row['verdict'] = WPCPM_Institution_Import::OK;
			$ask[ $index ]  = $row;
		}

		if ( empty( $ask ) ) {
			return array(
				'rows'  => $rows,
				'error' => null,
			);
		}

		$judged = WPCPM_Institution_Import::check_against_base( $ask, $institution, $airtable, $settings );

		if ( is_wp_error( $judged ) ) {
			return array(
				'rows'  => $rows,
				'error' => $judged,
			);
		}

		foreach ( $judged as $index => $row ) {
			$verdict = isset( $row['verdict'] ) ? (string) $row['verdict'] : WPCPM_Institution_Import::OK;

			$rows[ $index ]['verdict'] = $verdict;
			$rows[ $index ]['detail']  = isset( $row['detail'] ) ? $row['detail'] : array();

			if ( self::creatable( $verdict ) ) {
				continue;
			}

			// Named rather than silently dropped. Between the preview somebody read and this
			// slice, this student was enrolled somewhere, and the school is owed the sentence
			// saying so rather than a total that does not add up.
			$rows[ $index ]['manager_reason'] = isset( $row['manager_reason'] ) ? (string) $row['manager_reason'] : '';
			$rows[ $index ]['state']          = self::ROW_BLOCKED;
			$rows[ $index ]['changed']        = true;
		}

		return array(
			'rows'  => $rows,
			'error' => null,
		);
	}

	/**
	 * Look for a record this row may already have created.
	 *
	 * The import key first, which is the only question that has a true answer: it is written
	 * by this module, on this row, in the same create. The address second, which cannot tell
	 * this row's own record from somebody else's student of the same name at another
	 * university - so a hit on the address alone is reported as somebody else's and never
	 * adopted.
	 *
	 * @param WPCPM_Airtable $airtable A configured client.
	 * @param array          $settings Plugin settings.
	 * @param string         $key      The `Site import key` this row would carry.
	 * @param string         $email    The row's lowercased address.
	 * @return array{record:string,other:bool}|WP_Error
	 */
	private static function find_created( $airtable, array $settings, $key, $email ) {
		$fields = WPCPM_Mentors_Sync::fields();
		$table  = isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '';
		$column = $fields['student_import_key'];

		if ( self::is_key( $key ) ) {
			$records = $airtable->fetch_all(
				$table,
				array(
					'formula' => sprintf( "{%s} = '%s'", $column, $key ),
					'fields'  => array( $column ),
				)
			);

			if ( is_wp_error( $records ) ) {
				return $records;
			}

			foreach ( $records as $record ) {
				if ( ! empty( $record['id'] ) ) {
					return array(
						'record' => (string) $record['id'],
						'other'  => false,
					);
				}
			}
		}

		$email = strtolower( trim( (string) $email ) );

		if ( '' === $email ) {
			return array(
				'record' => '',
				'other'  => false,
			);
		}

		$records = $airtable->fetch_all(
			$table,
			array(
				// The same lowercasing the ladder uses: the base holds addresses as they were
				// typed, and `Anna@` and `anna@` are one mailbox and one person.
				'formula' => $airtable->formula_in( $fields['student_email'], array( $email ), true ),
				'fields'  => array( $fields['student_email'] ),
			)
		);

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		return array(
			'record' => '',
			'other'  => ! empty( $records ),
		);
	}

	/**
	 * Park a batch, and say why on the record.
	 *
	 * @param array  $batch   The batch.
	 * @param string $problem `agreement`, `no-actor` or `not-allowed`.
	 */
	private static function park( array $batch, $problem ) {
		$batch_id = (int) $batch['id'];

		update_post_meta( $batch_id, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_BLOCKED );
		// Stops the retention clock from guessing: a post's modified time is not touched by
		// `update_post_meta()`, so without this stamp every batch would look as old as its
		// creation and a long import would be thrown away on the wrong day.
		WPCPM_Institution_Import::settle( $batch_id );
		update_post_meta( $batch_id, self::META_REASON, sanitize_key( (string) $problem ) );

		$tally = self::tally( $batch['rows'] );

		// Written whoever stopped it, because the slice that stops a batch is usually the one
		// nobody is watching: a cron continuation leaves no flash anywhere, and this row is
		// then the only account of why a school's import ended half done.
		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_STOPPED,
				'institution' => (string) $batch['institution'],
				'subject'     => 'batch-' . $batch_id,
				'actor'       => self::actor_of( $batch_id ),
				// The system's own ground: whatever the batch was confirmed on has just
				// stopped being true, which is the whole reason this row exists.
				'ground'      => WPCPM_Institution_Audit::GROUND_SYSTEM,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => sprintf(
					/* translators: 1: why the import stopped, 2: how many students were created before it did. */
					__( 'The import stopped: %1$s. %2$s students had been created.', 'wpcredits-program-manager' ),
					sanitize_key( (string) $problem ),
					number_format_i18n( $tally['created'] )
				),
				'data'        => array(
					'batch'   => $batch_id,
					'reason'  => sanitize_key( (string) $problem ),
					'created' => $tally['created'],
				),
			)
		);
	}

	/**
	 * Mark a batch done, and write the one row that says what it did.
	 *
	 * @param array  $batch  The batch.
	 * @param array  $rows   The rows, as they finished.
	 * @param string $ground The ground the last slice was allowed on.
	 */
	private static function finish( array $batch, array $rows, $ground ) {
		$batch_id = (int) $batch['id'];
		$tally    = self::tally( $rows );

		update_post_meta( $batch_id, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_DONE );
		// Stops the retention clock from guessing: a post's modified time is not touched by
		// `update_post_meta()`, so without this stamp every batch would look as old as its
		// creation and a long import would be thrown away on the wrong day.
		WPCPM_Institution_Import::settle( $batch_id );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_CREATED,
				'institution' => (string) $batch['institution'],
				'subject'     => 'batch-' . $batch_id,
				'actor'       => self::actor_of( $batch_id ),
				'ground'      => in_array( $ground, WPCPM_Institution_Audit::grounds(), true ) ? $ground : WPCPM_Institution_Audit::GROUND_SYSTEM,
				// Live: every one of these rows was written to the base by this module, and
				// the count is of answers Airtable gave rather than of anything cached.
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'     => sprintf(
					/* translators: 1: students created, 2: rows blocked, 3: rows the base refused. */
					__( 'Import finished: %1$s created, %2$s blocked, %3$s failed.', 'wpcredits-program-manager' ),
					number_format_i18n( $tally['created'] ),
					number_format_i18n( $tally['blocked'] ),
					number_format_i18n( $tally['failed'] )
				),
				'data'        => array(
					'batch'   => $batch_id,
					'created' => $tally['created'],
					'blocked' => $tally['blocked'],
					'failed'  => $tally['failed'],
				),
			)
		);
	}

	/**
	 * Carry on when nobody is watching.
	 *
	 * @param int $batch_id Batch post ID.
	 */
	public static function handle_tick( $batch_id ) {
		self::create_slice( (int) $batch_id );
	}

	/**
	 * Ask for one more slice in half a minute.
	 *
	 * Guarded by `wp_next_scheduled()` because the arguments are part of an event's identity
	 * and WordPress refuses a second identical event inside ten minutes: without the guard a
	 * reader pressing Continue would get `false` back from a call whose failure means nothing
	 * and would have no way to tell it from a cron that never got armed.
	 *
	 * @param int $batch_id Batch post ID.
	 */
	private static function schedule( $batch_id ) {
		$args = array( (int) $batch_id );

		if ( ! wp_next_scheduled( WPCPM_Institution_Import::CRON_TICK, $args ) ) {
			wp_schedule_single_event( time() + self::RETRY_IN, WPCPM_Institution_Import::CRON_TICK, $args );
		}
	}

	/**
	 * Claim the right to create for one institution.
	 *
	 * `add_option()` is the test and set: it writes only when the row does not exist and says
	 * which of the two it did, in one statement the database serialises. Two requests reaching
	 * this line together therefore cannot both be told yes, which `get_option()` followed by
	 * `update_option()` would happily do.
	 *
	 * @param string $institution Institutions record ID.
	 * @return bool
	 */
	private static function acquire_lock( $institution ) {
		$name = self::LOCK_PREFIX . trim( (string) $institution );

		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( $name );

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		// Older than a slice can possibly take, so the request that took it is gone. Left
		// alone, a lock a killed request was holding would strand the batch until somebody
		// noticed a school's import had stopped.
		update_option( $name, time(), false );

		return true;
	}

	/**
	 * Let go of one institution's lock.
	 *
	 * @param string $institution Institutions record ID.
	 */
	private static function release_lock( $institution ) {
		delete_option( self::LOCK_PREFIX . trim( (string) $institution ) );
	}

	/**
	 * Store the rows.
	 *
	 * Called after every single row rather than once at the end of the slice, which is what
	 * makes a slice that is killed part way lose one row's worth of certainty instead of
	 * twelve seconds of it.
	 *
	 * @param int   $batch_id Batch post ID.
	 * @param array $rows     The rows.
	 */
	private static function save_rows( $batch_id, array $rows ) {
		update_post_meta( (int) $batch_id, WPCPM_Institution_Import::META_ROWS, $rows );
	}

	/**
	 * What state one row is in.
	 *
	 * Derived when it is absent, so a batch staged by the release before this one is read
	 * without a migration: its rows carry a verdict and no state, and the verdict is what the
	 * state was going to be made of anyway.
	 *
	 * @param array $row A row.
	 * @return string
	 */
	public static function state_of( array $row ) {
		$state = isset( $row['state'] ) ? (string) $row['state'] : '';

		if ( in_array( $state, array( self::ROW_PENDING, self::ROW_CREATING, self::ROW_CREATED, self::ROW_FAILED, self::ROW_BLOCKED ), true ) ) {
			return $state;
		}

		$verdict = isset( $row['verdict'] ) ? (string) $row['verdict'] : WPCPM_Institution_Import::OK;

		return self::creatable( $verdict ) ? self::ROW_PENDING : self::ROW_BLOCKED;
	}

	/**
	 * The record ID on a row, if it has one.
	 *
	 * @param array $row A row.
	 * @return string
	 */
	public static function record_of( array $row ) {
		$record = isset( $row['record_id'] ) ? trim( (string) $row['record_id'] ) : '';

		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}

	/**
	 * Whether a verdict is one this module creates.
	 *
	 * Two of the six: `ok`, and `near-name`, which is a warning printed beside a row that is
	 * still created because two people at one university do share a name.
	 *
	 * @param string $verdict A verdict from the ladder.
	 * @return bool
	 */
	public static function creatable( $verdict ) {
		return in_array(
			(string) $verdict,
			array( WPCPM_Institution_Import::OK, WPCPM_Institution_Import::NEAR_NAME ),
			true
		);
	}

	/**
	 * How many rows are still waiting to be created.
	 *
	 * A row in `creating` with no ID counts: it has an answer somewhere that this site has
	 * not stored, and the next slice's job is to go and find it.
	 *
	 * @param array $rows The rows.
	 * @return int
	 */
	public static function remaining( array $rows ) {
		$left = 0;

		foreach ( $rows as $row ) {
			$state = self::state_of( $row );

			if ( in_array( $state, array( self::ROW_PENDING, self::ROW_CREATING ), true ) && '' === self::record_of( $row ) ) {
				++$left;
			}
		}

		return $left;
	}

	/**
	 * How the rows of a batch stand.
	 *
	 * @param array $rows The rows.
	 * @return array{created:int,blocked:int,failed:int,pending:int}
	 */
	public static function tally( array $rows ) {
		$tally = array(
			'created' => 0,
			'blocked' => 0,
			'failed'  => 0,
			'pending' => 0,
		);

		foreach ( $rows as $row ) {
			switch ( self::state_of( $row ) ) {
				case self::ROW_CREATED:
					++$tally['created'];
					break;

				case self::ROW_FAILED:
					++$tally['failed'];
					break;

				case self::ROW_BLOCKED:
					++$tally['blocked'];
					break;

				default:
					++$tally['pending'];
					break;
			}
		}

		return $tally;
	}

	/**
	 * What one slice did, in the shape the handlers report.
	 *
	 * @param string $problem '' when the slice ran, otherwise why it did not.
	 * @param string $state   The batch's state afterwards.
	 * @param array  $rows    The rows, for the counts.
	 * @return array{ok:bool,problem:string,state:string,created:int,blocked:int,failed:int,remaining:int}
	 */
	private static function outcome( $problem, $state, array $rows ) {
		$tally = self::tally( $rows );

		return array(
			'ok'        => in_array( $problem, array( 'created', 'progress' ), true ),
			'problem'   => (string) $problem,
			'state'     => (string) $state,
			'created'   => $tally['created'],
			'blocked'   => $tally['blocked'],
			'failed'    => $tally['failed'],
			'remaining' => self::remaining( $rows ),
		);
	}
}
