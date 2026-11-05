<?php
/**
 * Students module - what happens to a registration after the form's guard (1.123.54).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one step every path runs once a registration is stored: the form's submit, the hourly retry
 * and an Administrator's Write it now (design of 5 November 2026, sections 4 and 5).
 *
 * **The order, under one lock per address** (`WPCPM_Lock`, named by `WPCPM_Student_Registration::lock_name()`):
 *
 * 1. An account on the site has the address (any role, compared trimmed and in lower case): the
 *    registration is `account` and the student is sent the sign-in email. No Airtable request.
 * 2. Another registration holds the address (`held_for()`: written, or waiting and older): this one
 *    is `already`, filed under it with no answers and no name, in its title or its slug (Ruling 14),
 *    and the student is sent the already-registered email. No Airtable request.
 * 3. Airtable is asked for the Students rows that have the address. A row, or more than one, is
 *    `already` too, keeping the answers and the name for the Administrator who reads it, unless this
 *    very registration sent a create whose answer was lost and exactly one row has the address now:
 *    that row is the one it wrote (Ruling 9).
 * 4. Otherwise one Students row is created from the answers, and the student is sent the received
 *    email. The row is never changed or deleted afterwards, and nothing else is written to Airtable.
 *
 * A try that cannot finish (Airtable not connected or unreachable, a refusal) is noted and the
 * registration waits for the hourly run, until 72 hours have passed since it was queued, when it is
 * `failed` (Ruling 8). No email goes while a registration waits: each of the three is sent once, as
 * its outcome becomes known, and never again (Ruling 15).
 *
 * **The create mark.** `create_sent` is set to the time just before the create is sent, with what
 * the row leaves out (`left_out`), and both are cleared when Airtable answers with a status, since an
 * answer says the row was not made. A create whose answer was lost (a timeout) keeps them, and the
 * next try, finding one row, takes it for its own, with what it left out, instead of writing a second.
 *
 * **Held for an Administrator.** A registration the form took past its 200 a day carries
 * `SIGNAL_HELD`: the hourly run passes over it, and only an Administrator's Write it now, which takes
 * the hold off, sends it on.
 */
final class WPCPM_Student_Registration_Process {

	/** The hourly retry of the waiting registrations. */
	const CRON_HOURLY = 'wpcpm_student_registrations_hourly';

	/** The lock one hourly run holds, so two never overlap. Under the prefix uninstall sweeps. */
	const RUN_LOCK = 'wpcpm_sreg_lock_run';

	/** How long the run lock holds at most, in seconds. */
	const RUN_TTL = 900;

	/** The most registrations one run tries (Ruling 12). */
	const BATCH = 40;

	/** The daily ceiling's key for the three emails. */
	const MAIL_KEY = 'student-register-mail';

	const MAIL_RECEIVED = 'student-registration-received';
	const MAIL_ACCOUNT  = 'student-registration-account';
	const MAIL_ALREADY  = 'student-registration-already';

	/** A registration held for a person because the site's daily ceiling was spent (the form's). */
	const SIGNAL_SITE_CEILING = 'site-ceiling';

	/** The day's limit on the three emails was spent, so the student was sent none. */
	const SIGNAL_MAIL_CEILING = 'mail-ceiling';

	/** The mail layer refused the hand-off. */
	const SIGNAL_MAIL_FAILED = 'mail-failed';

	/**
	 * Held for an Administrator: more than the form's 200 a day had arrived (the form's second daily
	 * limit). The hourly run passes over it; Write it now takes the hold off and writes it.
	 */
	const SIGNAL_HELD = 'held';

	/**
	 * Hang the hourly run on its hook, and see that it is on the clock.
	 */
	public static function init() {
		add_action( self::CRON_HOURLY, array( __CLASS__, 'run_hourly' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	/**
	 * Put the hourly run on the clock, five minutes out, when it is not there.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOURLY ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOURLY );
		}
	}

	/**
	 * Take the hourly run off the clock.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOURLY );
	}

	/**
	 * Settle one registration as far as the site can.
	 *
	 * @param int                 $post_id  The registration.
	 * @param WPCPM_Airtable|null $airtable The client, or null for the site's own.
	 * @param int                 $now      The time, Unix; 0 for now.
	 * @return string `written`, `account`, `already`, `waiting`, `failed`, `busy` (another request holds
	 *                the address) or `skipped` (not a registration that waits or failed).
	 */
	public static function process( $post_id, $airtable = null, $now = 0 ) {
		$post_id = (int) $post_id;
		$now     = (int) $now > 0 ? (int) $now : time();
		$post    = WPCPM_Student_Registration::registration( $post_id );

		if ( null === $post || ! self::takes( $post ) ) {
			return 'skipped';
		}

		$fields  = WPCPM_Student_Registration::fields_of( $post );
		$address = isset( $fields['email'] ) ? trim( (string) $fields['email'] ) : '';

		if ( '' === $address ) {
			WPCPM_Student_Registration::set_state( $post_id, WPCPM_Student_Registration::STATE_FAILED, array( 'error' => __( 'The registration holds no address.', 'wpcredits-program-manager' ) ) );

			return WPCPM_Student_Registration::STATE_FAILED;
		}

		$lock  = WPCPM_Student_Registration::lock_name( $address );
		$token = WPCPM_Lock::take( $lock, WPCPM_Student_Registration::LOCK_TTL );

		if ( '' === $token ) {
			return 'busy';
		}

		try {
			return self::settle( $post_id, $airtable, $now );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Try each waiting registration but those held for an Administrator, oldest first, at most `BATCH`
	 * of them, under the run lock; then remove what retention says goes. A held one is left out by the
	 * query, so it is neither tried nor counted toward the batch, however many a flood left.
	 *
	 * @param int                 $now      The time, Unix; 0 for now.
	 * @param WPCPM_Airtable|null $airtable The client, or null for the site's own.
	 * @return array `tried`, `written`, `settled` (written, account or already), `waiting` (still
	 *               waiting, or held by another request), `failed`, `busy` (the run lock is held),
	 *               and `languages_error` (why the copy of the Languages table could not be read, or '').
	 */
	public static function run_hourly( $now = 0, $airtable = null ) {
		$now = (int) $now > 0 ? (int) $now : time();
		$out = array(
			'tried'           => 0,
			'written'         => 0,
			'settled'         => 0,
			'waiting'         => 0,
			'failed'          => 0,
			'busy'            => false,
			'languages_error' => '',
		);

		$token = WPCPM_Lock::take( self::RUN_LOCK, self::RUN_TTL );

		if ( '' === $token ) {
			$out['busy'] = true;

			return $out;
		}

		try {
			if ( null === $airtable && WPCPM_Settings::is_connected() ) {
				$airtable = new WPCPM_Airtable();
			}

			if ( null !== $airtable ) {
				$read = WPCPM_Student_Airtable_Row::refresh_languages( $airtable, $now );

				if ( is_wp_error( $read ) ) {
					$out['languages_error'] = $read->get_error_message();
				}
			}

			foreach ( WPCPM_Student_Registration::in_states( array( WPCPM_Student_Registration::STATE_WAITING ), self::BATCH, 'ASC', self::SIGNAL_HELD ) as $post ) {
				$result = self::process( $post->ID, $airtable, $now );

				if ( 'skipped' === $result ) {
					continue;
				}

				if ( 'busy' !== $result ) {
					++$out['tried'];
				}

				if ( 'written' === $result ) {
					++$out['written'];
				}

				if ( in_array( $result, array( 'written', WPCPM_Student_Registration::STATE_ACCOUNT, WPCPM_Student_Registration::STATE_ALREADY ), true ) ) {
					++$out['settled'];
				} elseif ( WPCPM_Student_Registration::STATE_FAILED === $result ) {
					++$out['failed'];
				} else {
					++$out['waiting'];
				}
			}

			WPCPM_Student_Registration::purge( $now );
		} finally {
			WPCPM_Lock::release( self::RUN_LOCK, $token );
		}

		return $out;
	}

	/**
	 * Whether a registration is one the process takes: waiting, or failed and put back by an
	 * Administrator's Write it now.
	 *
	 * @param WP_Post $post The registration.
	 * @return bool
	 */
	private static function takes( WP_Post $post ) {
		return in_array( WPCPM_Student_Registration::state_of( $post ), array( WPCPM_Student_Registration::STATE_WAITING, WPCPM_Student_Registration::STATE_FAILED ), true );
	}

	/**
	 * The steps, with the address's lock held.
	 *
	 * @param int                 $post_id  The registration.
	 * @param WPCPM_Airtable|null $airtable The client, or null for the site's own.
	 * @param int                 $now      The time, Unix.
	 * @return string One of `process()`'s answers.
	 */
	private static function settle( $post_id, $airtable, $now ) {
		// What was read before the lock may be old: another request could have settled this one.
		WPCPM_Student_Registration::forget_cached( $post_id );

		$post = WPCPM_Student_Registration::registration( $post_id );

		if ( null === $post || ! self::takes( $post ) ) {
			return 'skipped';
		}

		$fields  = WPCPM_Student_Registration::fields_of( $post );
		$address = trim( (string) $fields['email'] );
		$outcome = WPCPM_Student_Registration::outcome_of( $post );
		$failed  = WPCPM_Student_Registration::STATE_FAILED === WPCPM_Student_Registration::state_of( $post );
		$queued  = (int) get_post_meta( $post_id, WPCPM_Student_Registration::META_QUEUED, true );

		// 1. An account with the address, whatever its role. Needs no Airtable.
		$user = get_user_by( 'email', mb_strtolower( $address ) );

		if ( $user ) {
			WPCPM_Student_Registration::set_state( $post_id, WPCPM_Student_Registration::STATE_ACCOUNT, array( 'user' => (int) $user->ID ) );
			self::announce(
				$post_id,
				self::MAIL_ACCOUNT,
				$address,
				array(
					'site_name'          => WPCPM_Mail::site_name(),
					'login_link'         => wp_login_url(),
					'lost_password_link' => wp_lostpassword_url(),
				)
			);

			return WPCPM_Student_Registration::STATE_ACCOUNT;
		}

		// 2. A registration of the site's own that holds the address. Needs no Airtable either.
		$held = WPCPM_Student_Registration::held_for( WPCPM_Student_Registration::email_hash( $address ), $post_id );

		if ( null !== $held ) {
			if ( ! self::file_already( $post_id, $address, array(), true ) ) {
				return self::failed_try( $post_id, __( 'WordPress did not take the name off the registration, so it is not filed as registered already yet.', 'wpcredits-program-manager' ), $now, $failed, $queued );
			}

			update_post_meta( $post_id, WPCPM_Student_Registration::META_FIRST, (int) $held->ID );

			return WPCPM_Student_Registration::STATE_ALREADY;
		}

		// 3. The Students rows that have the address.
		if ( ! WPCPM_Settings::is_connected() ) {
			return self::failed_try( $post_id, __( 'Airtable is not connected.', 'wpcredits-program-manager' ), $now, $failed, $queued );
		}

		$airtable = null === $airtable ? new WPCPM_Airtable() : $airtable;
		$rows     = WPCPM_Student_Airtable_Row::find_by_email( $address, $airtable );

		if ( is_wp_error( $rows ) ) {
			return self::failed_try( $post_id, $rows->get_error_message(), $now, $failed, $queued );
		}

		if ( array() !== $rows ) {
			// A create whose answer was lost made this one row: it is ours, with what it left out, and
			// nothing is written again.
			if ( (int) $outcome['create_sent'] > 0 && 1 === count( $rows ) ) {
				return self::written( $post_id, $rows[0], is_array( $outcome['left_out'] ) ? $outcome['left_out'] : array(), $fields, $address );
			}

			self::file_already( $post_id, $address, $rows, false );

			return WPCPM_Student_Registration::STATE_ALREADY;
		}

		// 4. The row.
		$table = WPCPM_Student_Airtable_Row::students_table( $airtable );

		if ( is_wp_error( $table ) ) {
			return self::failed_try( $post_id, $table->get_error_message(), $now, $failed, $queued );
		}

		$row = WPCPM_Student_Airtable_Row::cells_for(
			array_merge( $fields, array( 'privacy' => true ) ),
			$table,
			array(
				'institutions' => WPCPM_Student_Airtable_Row::institution_links(),
				'languages'    => WPCPM_Student_Airtable_Row::languages(),
			)
		);

		// Marked before it is sent, with what it leaves out: a create whose answer is lost may still
		// have made the row, and the try that finds it says what that row lacks.
		self::merge_outcome(
			$post_id,
			array(
				'create_sent' => $now,
				'left_out'    => $row['left_out'],
			)
		);

		$record = WPCPM_Student_Airtable_Row::create( $row['cells'], $airtable );

		if ( is_wp_error( $record ) ) {
			$data = $record->get_error_data();

			// An answer with a status says the row was not made, so nothing was left out of one.
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				self::merge_outcome(
					$post_id,
					array(
						'create_sent' => 0,
						'left_out'    => array(),
					)
				);
			}

			return self::failed_try( $post_id, $record->get_error_message(), $now, $failed, $queued );
		}

		return self::written( $post_id, $record, $row['left_out'], $fields, $address );
	}

	/**
	 * Settle a registration as written, and send the received email.
	 *
	 * @param int    $post_id  The registration.
	 * @param string $record   The Students row's record ID.
	 * @param array  $left_out What the row left out.
	 * @param array  $fields   The answers.
	 * @param string $address  The address.
	 * @return string `written`.
	 */
	private static function written( $post_id, $record, array $left_out, array $fields, $address ) {
		WPCPM_Student_Registration::set_state(
			$post_id,
			WPCPM_Student_Registration::STATE_WRITTEN,
			array(
				'record'      => (string) $record,
				'left_out'    => $left_out,
				'create_sent' => 0,
			)
		);

		self::announce(
			$post_id,
			self::MAIL_RECEIVED,
			$address,
			array(
				'student_name'     => isset( $fields['name'] ) ? trim( (string) $fields['name'] ) : '',
				'institution_name' => isset( $fields['institution_name'] ) ? trim( (string) $fields['institution_name'] ) : '',
			)
		);

		return WPCPM_Student_Registration::STATE_WRITTEN;
	}

	/**
	 * File a registration as already registered, with the rows Airtable found if it was asked, and
	 * send the already email.
	 *
	 * An entry under an earlier registration of the site's own (step 2, `$forget`) keeps no answers
	 * and no name (Ruling 14): the answers go, and the title and the slug core made from it are
	 * replaced, the slug made again from the new title. That is done first, so a refusal files
	 * nothing and the registration is tried again. An entry a Students row answered (step 3) keeps the
	 * answers, the name and the title, for the Administrator who reads it.
	 *
	 * @param int      $post_id The registration.
	 * @param string   $address The address, as typed.
	 * @param string[] $records The Students rows that have it.
	 * @param bool     $forget  Whether the answers and the name go.
	 * @return bool False when WordPress refused to take the name off, and nothing was filed.
	 */
	private static function file_already( $post_id, $address, array $records, $forget ) {
		if ( $forget ) {
			$updated = wp_update_post(
				wp_slash(
					array(
						'ID'         => $post_id,
						'post_title' => __( 'Registration with no name', 'wpcredits-program-manager' ),
						'post_name'  => '',
					)
				),
				true
			);

			if ( is_wp_error( $updated ) || ! $updated ) {
				return false;
			}

			delete_post_meta( $post_id, WPCPM_Student_Registration::META_FIELDS );
		}

		WPCPM_Student_Registration::set_state( $post_id, WPCPM_Student_Registration::STATE_ALREADY, array( 'records' => array_values( $records ) ) );
		self::announce( $post_id, self::MAIL_ALREADY, $address, array() );

		return true;
	}

	/**
	 * Note a try that did not settle, and say where the registration stands: waiting, or failed once
	 * it was already failed or has waited 72 hours since it was queued.
	 *
	 * @param int    $post_id The registration.
	 * @param string $error   What went wrong.
	 * @param int    $now     The time, Unix.
	 * @param bool   $failed  Whether it was failed before this try.
	 * @param int    $queued  When it was queued.
	 * @return string `waiting` or `failed`.
	 */
	private static function failed_try( $post_id, $error, $now, $failed, $queued ) {
		WPCPM_Student_Registration::note_try( $post_id, $error, $now );

		if ( $failed ) {
			return WPCPM_Student_Registration::STATE_FAILED;
		}

		if ( $queued > 0 && $now - $queued >= WPCPM_Student_Registration::WAIT_HOURS * HOUR_IN_SECONDS ) {
			WPCPM_Student_Registration::set_state( $post_id, WPCPM_Student_Registration::STATE_FAILED );

			return WPCPM_Student_Registration::STATE_FAILED;
		}

		return WPCPM_Student_Registration::STATE_WAITING;
	}

	/**
	 * Send one of the three emails, once, and keep what became of it: `sent`, `ceiling` (the day's
	 * limit was spent, so none went) or `failed` (the mail layer refused it). The second and third
	 * leave a signal on the registration for the Registrations tab.
	 *
	 * @param int    $post_id The registration.
	 * @param string $mail_id One of the three ids.
	 * @param string $address Where it goes, as the student typed it.
	 * @param array  $values  The values of the email's placeholders that are the student's.
	 */
	private static function announce( $post_id, $mail_id, $address, array $values ) {
		$mail   = 'ceiling';
		$signal = self::SIGNAL_MAIL_CEILING;

		if ( WPCPM_Form_Guard::claim_mail( self::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY ) ) {
			$contact = WPCPM_Mail_Templates::program_contact();
			$build   = static function () use ( $mail_id, $values, $contact ) {
				return WPCPM_Mail_Templates::mail( $mail_id, $values, array( 'headers' => '' !== $contact ? array( 'Reply-To: ' . $contact ) : array() ) );
			};

			if ( WPCPM_Mail::send_to( $address, $mail_id, $build ) ) {
				$mail   = 'sent';
				$signal = '';
			} else {
				$mail   = 'failed';
				$signal = self::SIGNAL_MAIL_FAILED;
			}
		}

		self::merge_outcome( $post_id, array( 'mail' => $mail ) );

		if ( '' !== $signal ) {
			$post = WPCPM_Student_Registration::registration( $post_id );

			if ( null !== $post ) {
				update_post_meta( $post_id, WPCPM_Student_Registration::META_SIGNALS, array_values( array_unique( array_merge( WPCPM_Student_Registration::signals_of( $post ), array( $signal ) ) ) ) );
			}
		}
	}

	/**
	 * Change some keys of the outcome and leave the rest, the state as it is.
	 *
	 * @param int   $post_id The registration.
	 * @param array $changes The keys to set.
	 */
	private static function merge_outcome( $post_id, array $changes ) {
		$post = WPCPM_Student_Registration::registration( $post_id );

		if ( null === $post ) {
			return;
		}

		// Slashed, as core unslashes what it is handed, so a backslash in an error text is kept.
		update_post_meta( $post_id, WPCPM_Student_Registration::META_OUTCOME, wp_slash( array_merge( WPCPM_Student_Registration::outcome_of( $post ), $changes ) ) );
	}
}
