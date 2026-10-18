<?php
/**
 * Mentors module - making a selected mentor applicant a mentor on the site (1.122.33).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one step that makes a selected applicant a mentor (design of 16 October 2026, section 3,
 * decisions 6 and 7, and section 7), used by the Mentor Status Checker's daily check when the
 * mentors' course shows on the profile, and by The course is completed on the Waiting for review tab.
 *
 * **In order, so a mentor is never kept waiting on Airtable** (section 7): under the application's
 * lock, the account is made or linked through the mentors sync's own step
 * (`WPCPM_Mentor_Provision`), so there is one way to make a person a mentor (decision 6); the
 * application is filed `active`; the invitation, which carries the welcome, is queued; the Mentor
 * Status Checker's Slack message is told; and only then is the Mentors row written
 * (`WPCPM_Mentor_Airtable_Row`, decision 7). A refusal from Airtable stays on the application, the
 * mentor made and invited all the same, and Write the Airtable row again (`retry_airtable()`) writes
 * it later; the row is searched for by the address before anything is created, so no path makes a
 * second row. While the row is not written, the account carries
 * `WPCPM_Mentor_Provision::USER_ROW_PENDING`, and the mentors sync leaves it alone.
 *
 * **The account, in the sync's order of reliability.** The account the applicant's address, then
 * WordPress.org username, then stored profile finds (`find_account()`) is the one made a mentor,
 * unless the Mentors row that holds the address is linked to another account by its record id: that
 * account is the person's, and the one holding the address gains nothing. Two cases leave a doubt
 * about whose address the welcome, which carries the private links, would reach, so the invitation
 * is held until an Administrator confirms the account and presses Send invite or Resend invite on
 * its row: an account found by its username alone, which may be somebody else's (ruling R5), and a
 * record holder made a mentor while another account answered for the applicant (ruling P9b). An
 * Administrator's roles are never touched, nor is an Administrator sent an invitation.
 *
 * **The defaults it takes**, each the owner's: nothing activates while Make mentors from
 * applications is off, its default, until the launch (owner item 1); a Mentors row that already
 * holds the address, whatever its Status, is set back to Active and linked (owner item 3); and a
 * current mentor selected again, an Administrator linked and active as one among them, is filed as
 * already a mentor, with nothing sent and nothing written (owner item 4, a departure from the
 * design's section 6).
 *
 * **Settling.** An application whose row the mentors sync or a person linked meanwhile, or whose
 * account is gone, is settled (`settle()`): it no longer reads as refused, and retention applies to
 * it again. The daily run settles every one, and so do the lists the screens draw.
 *
 * Nothing here reads or writes Airtable on a public request: the daily check, WP-CLI and an
 * Administrator's press are the callers.
 */
final class WPCPM_Mentor_Activation {

	/**
	 * Whether Make mentors from applications is on: nothing is made a mentor from an application
	 * while it is off (owner item 1).
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) WPCPM_Settings::get_value( 'mentor_activation_enabled', false );
	}

	/*
	 * The activation
	 * --------------------------------------------------------------------
	 */

	/**
	 * Make a selected applicant a mentor.
	 *
	 * @param int    $post_id The application.
	 * @param int    $actor   Who pressed The course is completed; 0 for the daily check or WP-CLI.
	 * @param string $note    What they wrote, or ''.
	 * @return array `outcome` (`activated`, `already-mentor`, `already`, `off`, `busy`, `not-selected`,
	 *               `no-account` or `unknown`), `user_id`, `airtable` (`created`, `reactivated`,
	 *               `adopted`, `refused` or ''), `invited` (whether the invitation was queued),
	 *               `held` (why it was held for a person to confirm the account: `login` or `record`,
	 *               else ''), `linked` (whether an account that was there already was linked,
	 *               rather than one made) and `message` (a sentence for an Administrator for every
	 *               outcome but `activated`, and for that one what was held or not written, else '').
	 */
	public static function activate( $post_id, $actor = 0, $note = '' ) {
		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			return self::answer( 'unknown' );
		}

		if ( ! self::enabled() ) {
			return self::answer( 'off' );
		}

		$lock  = WPCPM_Mentor_Application::lock_name( (int) $post->ID );
		$token = WPCPM_Lock::take( $lock, WPCPM_Mentor_Application::LOCK_TTL );

		if ( '' === $token ) {
			return self::answer( 'busy' );
		}

		try {
			return self::activate_held( $post, absint( $actor ), trim( (string) $note ) );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * The activation, with the application's lock held.
	 *
	 * @param WP_Post $post  The application.
	 * @param int     $actor Who pressed, or 0.
	 * @param string  $note  What they wrote, or ''.
	 * @return array As `activate()` answers.
	 */
	private static function activate_held( WP_Post $post, $actor, $note ) {
		$post_id = (int) $post->ID;

		WPCPM_Mentor_Application::forget_cached( $post_id );

		$state = WPCPM_Mentor_Application::state_of( $post );

		if ( WPCPM_Mentor_Application::STATE_ACTIVE === $state ) {
			return self::answer( 'already', array( 'user_id' => WPCPM_Mentor_Application::user_of( $post ) ) );
		}

		if ( WPCPM_Mentor_Application::STATE_SELECTED !== $state ) {
			return self::answer( 'not-selected' );
		}

		$email   = WPCPM_Mentor_Application::email_of( $post );
		$handle  = WPCPM_Mentor_Application::handle_of( $post );
		$profile = WPCPM_Mentor_Application::profile_url( $handle );
		$name    = WPCPM_Typed_Text::mail_text( WPCPM_Mentor_Application::name_of( $post ) );

		// The login the shared step would name a new account, so the answer is the one it would read.
		$found    = WPCPM_Mentor_Provision::find_account( $email, WPCPM_Mentors_Sync::wporg_username( $profile ), $handle );
		$user     = $found['user'] instanceof WP_User ? $found['user'] : null;
		$record   = null !== $user ? trim( (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true ) ) : '';
		$airtable = WPCPM_Settings::is_connected() ? new WPCPM_Airtable() : null;
		$notes    = array( $note );
		$other    = null;
		$other_by = '';

		// The found account's own record id is the one it holds, so it is that record's holder. Without
		// one, the Mentors row that holds the address may be linked to another account by its record id,
		// which then is the person's: record id, then address, then username, as the sync trusts them.
		if ( '' === $record && null !== $airtable ) {
			$holder = self::holder_by_address( $email, $airtable );

			if ( null !== $holder ) {
				// The account find_account() named, by the address, the username or the stored profile,
				// is not the one made a mentor; the history and the answer say how it answered.
				$other    = $user;
				$other_by = null !== $other ? (string) $found['how'] : '';

				// `record` when another account answered for the applicant, whose invitation is held
				// (P9b); `row` when none did.
				$user   = $holder['user'];
				$record = $holder['record'];
				$found  = array(
					'user' => $user,
					'how'  => null !== $other ? 'record' : 'row',
				);
			}
		}

		$how = null !== $user ? (string) $found['how'] : 'created';

		// A mentor already: linked to its row, active, and a Mentor, or an Administrator, whom the shared
		// step never gives the Mentor role, so who is known by the record and the flag alone.
		if ( null !== $user && '' !== $record && array() !== array_intersect( array( WPCPM_Roles::ROLE_MENTOR, WPCPM_Roles::ROLE_ADMIN ), (array) $user->roles ) && 1 === (int) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_ACTIVE, true ) ) {
			if ( null !== $other ) {
				$notes = array_merge( $notes, self::other_account_notes( $other, $other_by, $user, true ) );
			}

			return self::file_already_mentor( $post_id, $user, $record, $how, $actor, $notes );
		}

		if ( null !== $other ) {
			$notes = array_merge( $notes, self::other_account_notes( $other, $other_by, $user, false ) );
		}

		$report  = array();
		$user_id = WPCPM_Mentor_Provision::provision(
			array(
				'record_id' => $record,
				'name'      => $name,
				'profile'   => $profile,
				'email'     => $email,
			),
			$report,
			$found
		);

		if ( $user_id <= 0 ) {
			$why = isset( $report['notices'][0] ) && '' !== (string) $report['notices'][0] ? (string) $report['notices'][0] : __( 'The account could not be made.', 'wpcredits-program-manager' );

			update_post_meta(
				$post_id,
				WPCPM_Mentor_Application::META_PROBLEM,
				wp_slash(
					array(
						'at'      => time(),
						'message' => $why,
					)
				)
			);

			return self::answer(
				'no-account',
				array(
					'message' => sprintf(
						/* translators: %s: why WordPress could not make the account, in the words the account step gave. */
						__( 'The account could not be made, so the application stays selected: %s', 'wpcredits-program-manager' ),
						$why
					),
				)
			);
		}

		// On the account first, so a request that dies before the next write cannot leave `settle()`
		// reading an older record on the account as this row's: it waits for its row from now on.
		update_user_meta( $user_id, WPCPM_Mentor_Provision::USER_ACTIVATED_AT, time() );
		update_user_meta( $user_id, WPCPM_Mentor_Provision::USER_ROW_PENDING, 1 );

		update_post_meta( $post_id, WPCPM_Mentor_Application::META_USER, $user_id );
		update_post_meta( $post_id, WPCPM_Mentor_Application::META_ACTIVATED_AT, time() );
		update_post_meta( $post_id, WPCPM_Mentor_Application::META_LINKED_BY, $how );
		delete_post_meta( $post_id, WPCPM_Mentor_Application::META_PROBLEM );
		self::keep_row(
			$post_id,
			array(
				'state'  => 'pending',
				'how'    => '' !== $record ? 'reactivate' : 'create',
				'record' => $record,
			)
		);

		$account  = get_userdata( $user_id );
		$is_admin = $account instanceof WP_User && in_array( WPCPM_Roles::ROLE_ADMIN, (array) $account->roles, true );
		$held     = ! $is_admin && in_array( $how, array( 'login', 'record' ), true ) ? $how : '';
		$notes    = array_merge( $notes, isset( $report['notices'] ) ? (array) $report['notices'] : array() );

		// A held invitation is marked on the account before the application is filed, so the bulk
		// invite and the queue pass the account over until an Administrator sends it from its row; an
		// account this activation invites holds nothing back any more.
		if ( '' !== $held ) {
			update_user_meta( $user_id, WPCPM_Mentor_Provision::USER_INVITE_HELD, $held );
		} elseif ( ! $is_admin ) {
			delete_user_meta( $user_id, WPCPM_Mentor_Provision::USER_INVITE_HELD );
		}

		if ( $is_admin ) {
			$notes[] = __( 'The account is an Administrator\'s, which signs in already: no invitation was sent.', 'wpcredits-program-manager' );
		} elseif ( 'login' === $held ) {
			$notes[] = __( 'No invitation was sent, since a match by username alone may be somebody else: once that is confirmed, press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		} elseif ( 'record' === $held ) {
			$notes[] = __( 'No invitation was sent: once it is confirmed which of the two accounts is the person\'s, press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		}

		WPCPM_Mentor_Application::decide( $post_id, WPCPM_Mentor_Application::STATE_ACTIVE, WPCPM_Mentor_Application::EVENT_ACTIVATED, $actor, self::joined( $notes ) );

		// Whatever the sync's invitation setting says: this invitation carries the welcome. Never to an
		// Administrator, who is not one of the plugin's people; and held where it is not sure whose
		// address it would reach: an account matched by its username alone (R5), and a record holder
		// made a mentor while another account answered for the applicant (P9b).
		$invited = ! $is_admin && '' === $held;

		if ( $invited ) {
			WPCPM_Mail::queue_invite( $user_id );
		}

		if ( class_exists( 'WPCPM_Mentor_Checker_Slack' ) ) {
			WPCPM_Mentor_Checker_Slack::record(
				array(
					'id'      => 'app-' . $post_id,
					'name'    => $name,
					'profile' => $profile,
					'source'  => 'site',
				)
			);
		}

		$row = self::write_row( $post, $record, $user_id, 0, $airtable );

		return self::answer(
			'activated',
			array(
				'user_id'  => $user_id,
				'airtable' => $row['ok'] ? $row['how'] : 'refused',
				'invited'  => $invited,
				'held'     => $held,
				'linked'   => 'created' !== $how,
				'message'  => self::activated_sentence( $held, $other_by, $row['ok'] ? '' : (string) $row['error'] ),
			)
		);
	}

	/**
	 * What an activation that made a mentor says to an Administrator: why the invitation was held,
	 * then why the Airtable row was not written; '' when neither.
	 *
	 * @param string $held     Why the invitation was held: `login`, `record` or ''.
	 * @param string $other_by For `record`, how the other account answered for the applicant: `email`,
	 *                         `login` or `profile`.
	 * @param string $error    Why the Airtable row was not written, or ''.
	 * @return string
	 */
	private static function activated_sentence( $held, $other_by, $error ) {
		$sentences = array();

		if ( 'login' === $held ) {
			$sentences[] = __( 'Made a mentor, but the invitation is held: the account was matched by its username alone. Confirm it is the same person, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		} elseif ( 'record' === $held && 'login' === $other_by ) {
			$sentences[] = __( 'Made a mentor, but the invitation is held: another account is named after the applicant\'s WordPress.org username. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		} elseif ( 'record' === $held && 'profile' === $other_by ) {
			$sentences[] = __( 'Made a mentor, but the invitation is held: another account holds the applicant\'s WordPress.org profile. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		} elseif ( 'record' === $held ) {
			$sentences[] = __( 'Made a mentor, but the invitation is held: another account holds the applicant\'s address. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		}

		if ( '' !== $error && array() === $sentences ) {
			/* translators: %s: Airtable's words, or the site's, for why the Mentors row was not written. */
			$sentences[] = sprintf( __( 'Made a mentor, but the Airtable row was not written: %s', 'wpcredits-program-manager' ), $error );
		} elseif ( '' !== $error ) {
			/* translators: %s: Airtable's words, or the site's, for why the Mentors row was not written. */
			$sentences[] = sprintf( __( 'The Airtable row was not written: %s', 'wpcredits-program-manager' ), $error );
		}

		return implode( ' ', $sentences );
	}

	/**
	 * What the history says when the account linked to the Mentors row that holds the address is not
	 * the account `find_account()` named for the applicant: how that other account answered (by the
	 * address, the username or the stored profile), then what became of the record holder.
	 *
	 * @param WP_User $other    The account `find_account()` named.
	 * @param string  $other_by How it named it: `email`, `login` or `profile`.
	 * @param WP_User $holder   The account linked to the row by its record id.
	 * @param bool    $already  Whether the holder is a mentor on this site already, and so was made
	 *                          nothing.
	 * @return string[] Two sentences.
	 */
	private static function other_account_notes( WP_User $other, $other_by, WP_User $holder, $already ) {
		if ( 'login' === $other_by ) {
			/* translators: %s: the username of another account on the site. */
			$first = sprintf( __( 'Another account, "%s", is named after the applicant\'s WordPress.org username.', 'wpcredits-program-manager' ), $other->user_login );
		} elseif ( 'profile' === $other_by ) {
			/* translators: %s: the username of another account on the site. */
			$first = sprintf( __( 'Another account, "%s", holds the applicant\'s WordPress.org profile.', 'wpcredits-program-manager' ), $other->user_login );
		} else {
			/* translators: %s: the username of another account on the site. */
			$first = sprintf( __( 'Another account, "%s", holds the applicant\'s address.', 'wpcredits-program-manager' ), $other->user_login );
		}

		if ( $already ) {
			/* translators: %s: the username of the account linked to the Mentors row that holds the applicant's address. */
			$second = sprintf( __( 'The account linked to the Mentors row with the applicant\'s address, "%s", is a mentor on this site already.', 'wpcredits-program-manager' ), $holder->user_login );
		} else {
			/* translators: %s: the username of the account linked to the Mentors row that holds the applicant's address. */
			$second = sprintf( __( 'The account linked to the Mentors row with the applicant\'s address, "%s", was made a mentor.', 'wpcredits-program-manager' ), $holder->user_login );
		}

		return array( $first, $second );
	}

	/**
	 * File a current mentor's application as already a mentor: nothing is sent, written or told to
	 * Slack (owner item 4).
	 *
	 * @param int      $post_id The application.
	 * @param WP_User  $user    The mentor's account.
	 * @param string   $record  Its record id.
	 * @param string   $how     How it was found.
	 * @param int      $actor   Who pressed, or 0.
	 * @param string[] $notes   The press's note first, then what the lookup noticed.
	 * @return array As `activate()` answers.
	 */
	private static function file_already_mentor( $post_id, WP_User $user, $record, $how, $actor, array $notes ) {
		if ( ! isset( $notes[0] ) || '' === trim( (string) $notes[0] ) ) {
			$notes[0] = __( 'A mentor on this site already: nothing was sent, and nothing was written to Airtable.', 'wpcredits-program-manager' );
		}

		update_post_meta( $post_id, WPCPM_Mentor_Application::META_USER, (int) $user->ID );
		update_post_meta( $post_id, WPCPM_Mentor_Application::META_ACTIVATED_AT, time() );
		update_post_meta( $post_id, WPCPM_Mentor_Application::META_LINKED_BY, $how );
		delete_post_meta( $post_id, WPCPM_Mentor_Application::META_PROBLEM );
		self::keep_row(
			$post_id,
			array(
				'state'  => 'already',
				'record' => $record,
			)
		);

		WPCPM_Mentor_Application::decide( $post_id, WPCPM_Mentor_Application::STATE_ACTIVE, WPCPM_Mentor_Application::EVENT_ALREADY_MENTOR, $actor, self::joined( $notes ) );

		return self::answer( 'already-mentor', array( 'user_id' => (int) $user->ID ) );
	}

	/**
	 * The account linked, by its record id, to the one Mentors row that holds an address.
	 *
	 * A search that fails, or that finds no row or two, finds nobody: the write searches again and
	 * says what it found.
	 *
	 * @param string         $email    The applicant's address.
	 * @param WPCPM_Airtable $airtable The client.
	 * @return array|null `user` (WP_User) and `record`, or null.
	 */
	private static function holder_by_address( $email, WPCPM_Airtable $airtable ) {
		if ( '' === (string) $email ) {
			return null;
		}

		$rows = WPCPM_Mentor_Airtable_Row::find_by_email( $email, $airtable );

		if ( is_wp_error( $rows ) || 1 !== count( $rows ) ) {
			return null;
		}

		$holder = WPCPM_Mentor_Provision::find_by_record_id( $rows[0] );

		return $holder instanceof WP_User ? array(
			'user'   => $holder,
			'record' => (string) $rows[0],
		) : null;
	}

	/*
	 * The Mentors row
	 * --------------------------------------------------------------------
	 */

	/**
	 * Write the Airtable row again: for an active application whose row is still to be written.
	 *
	 * Under the application's lock. An account that holds a record id now, where the activation was to
	 * create one, was linked meanwhile, by the mentors sync or by hand, and nothing is written; an
	 * account that is gone has nothing written for it. Otherwise the row is written as the activation
	 * writes it, whose write searches the table by the address before it creates anything, so a row a
	 * request that died had made is found, not made twice.
	 *
	 * @param int $post_id The application.
	 * @param int $actor   Who pressed.
	 * @return array `outcome` (`written`, `refused`, `not-needed`, `gone` for an account that no
	 *               longer exists, `busy`, `not-active` or `unknown`) and `message`, a sentence for an
	 *               Administrator.
	 */
	public static function retry_airtable( $post_id, $actor ) {
		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			return self::retry_answer( 'unknown' );
		}

		$lock  = WPCPM_Mentor_Application::lock_name( (int) $post->ID );
		$token = WPCPM_Lock::take( $lock, WPCPM_Mentor_Application::LOCK_TTL );

		if ( '' === $token ) {
			return self::retry_answer( 'busy' );
		}

		try {
			return self::retry_held( $post, absint( $actor ) );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Write the Airtable row again, with the application's lock held.
	 *
	 * @param WP_Post $post  The application.
	 * @param int     $actor Who pressed.
	 * @return array As `retry_airtable()` answers.
	 */
	private static function retry_held( WP_Post $post, $actor ) {
		$post_id = (int) $post->ID;

		WPCPM_Mentor_Application::forget_cached( $post_id );

		if ( WPCPM_Mentor_Application::STATE_ACTIVE !== WPCPM_Mentor_Application::state_of( $post ) ) {
			return self::retry_answer( 'not-active' );
		}

		if ( ! WPCPM_Mentor_Application::row_unwritten( $post ) ) {
			return self::retry_answer( 'not-needed' );
		}

		$kept    = WPCPM_Mentor_Application::airtable_of( $post );
		$user_id = WPCPM_Mentor_Application::user_of( $post );

		wp_cache_delete( $user_id, 'user_meta' );

		if ( ! get_userdata( $user_id ) instanceof WP_User ) {
			self::settle_as( $post_id, 'gone', '', $actor );

			return self::retry_answer( 'gone', self::gone_note() );
		}

		$record = trim( (string) get_user_meta( $user_id, WPCPM_Mentors_Sync::META_RECORD_ID, true ) );

		if ( 'create' === $kept['how'] && '' !== $record ) {
			// Stamped before the wait ends, as a write is: a sync run that read the table before this
			// record was linked must not revoke the account in the moment it stops waiting.
			update_user_meta( $user_id, WPCPM_Mentor_Provision::USER_ACTIVATED_AT, time() );
			delete_user_meta( $user_id, WPCPM_Mentor_Provision::USER_ROW_PENDING );
			self::settle_as( $post_id, 'found', $record, $actor, __( 'The account already holds its Airtable record; nothing was written.', 'wpcredits-program-manager' ) );

			return self::retry_answer( 'not-needed', __( 'Nothing was written: the mentor\'s account holds its Airtable record already.', 'wpcredits-program-manager' ) );
		}

		$row = self::write_row( $post, $record, $user_id, $actor, WPCPM_Settings::is_connected() ? new WPCPM_Airtable() : null );

		if ( $row['ok'] ) {
			return self::retry_answer( 'written' );
		}

		return self::retry_answer(
			'refused',
			sprintf(
				/* translators: %s: Airtable's words, or the site's, for why the Mentors row was not written. */
				__( 'The Airtable row was not written: %s', 'wpcredits-program-manager' ),
				$row['error']
			)
		);
	}

	/**
	 * Write the application's Mentors row and keep what became of it: on the account, the record id
	 * (a new or adopted one replacing a stale one), a fresh `USER_ACTIVATED_AT` and then the end of its
	 * wait; on the application, the row's state and a line in the history.
	 *
	 * @param WP_Post             $post     The application.
	 * @param string              $record   The account's own Mentors row, or ''.
	 * @param int                 $user_id  The mentor's account.
	 * @param int                 $actor    Who pressed, or 0.
	 * @param WPCPM_Airtable|null $airtable The client, or null when Airtable is not connected, which
	 *                                      asks nothing of it.
	 * @return array `ok`, `how`, `record` and `error`, as the row's `write()` answers them.
	 */
	private static function write_row( WP_Post $post, $record, $user_id, $actor, $airtable ) {
		$post_id = (int) $post->ID;
		$kept    = WPCPM_Mentor_Application::airtable_of( $post );
		$how     = isset( $kept['how'] ) ? $kept['how'] : '';

		if ( $airtable instanceof WPCPM_Airtable ) {
			$row = WPCPM_Mentor_Airtable_Row::write( $post, $record, $user_id, $airtable );
		} else {
			$row = array(
				'ok'       => false,
				'how'      => '',
				'record'   => '',
				'error'    => __( 'Airtable is not connected.', 'wpcredits-program-manager' ),
				'left_out' => array(),
			);
		}

		$left_out = isset( $row['left_out'] ) && is_array( $row['left_out'] ) ? $row['left_out'] : array();

		if ( ! empty( $row['ok'] ) ) {
			update_user_meta( (int) $user_id, WPCPM_Mentors_Sync::META_RECORD_ID, (string) $row['record'] );
			// Stamped now, before the wait ends: a mentors sync run that started before this write read
			// the table without this row, or with its Status not yet set back, and its revoke passes by
			// an account stamped at or after its start. Stamped at the activation alone, a row written
			// again later, or a run that started between the activation and its own write, would leave
			// the account neither waiting nor stamped inside the run, and the run would revoke it.
			update_user_meta( (int) $user_id, WPCPM_Mentor_Provision::USER_ACTIVATED_AT, time() );
			delete_user_meta( (int) $user_id, WPCPM_Mentor_Provision::USER_ROW_PENDING );

			self::keep_row(
				$post_id,
				array(
					'state'    => (string) $row['how'],
					'how'      => $how,
					'record'   => (string) $row['record'],
					'left_out' => $left_out,
				)
			);
			WPCPM_Mentor_Application::add_event( $post_id, WPCPM_Mentor_Application::EVENT_AIRTABLE_WRITTEN, $actor, WPCPM_Mentor_Airtable_Row::left_out_sentence( $left_out ) );

			return $row;
		}

		self::keep_row(
			$post_id,
			array(
				'state'    => 'refused',
				'how'      => $how,
				'record'   => isset( $kept['record'] ) ? $kept['record'] : '',
				'error'    => (string) $row['error'],
				'left_out' => $left_out,
			)
		);
		WPCPM_Mentor_Application::add_event( $post_id, WPCPM_Mentor_Application::EVENT_AIRTABLE_REFUSED, $actor, (string) $row['error'] );

		return $row;
	}

	/*
	 * Settling
	 * --------------------------------------------------------------------
	 */

	/**
	 * Settle an application whose Mentors row is still to be written, once nothing more is to be
	 * written for it: its account holds a record id and waits for no row, linked by the mentors sync
	 * or by hand (`found`), or its account no longer exists (`gone`). Each is its own event, so the
	 * history never says the site wrote a row it did not.
	 *
	 * The conditions are read without the lock first, so a list that reads every activated row takes
	 * no lock for a row with nothing to settle; a row that has is settled under the application's lock
	 * and read again under it, and one whose lock another request holds is left, as `lapse()` leaves
	 * one.
	 *
	 * @param WP_Post $post The application.
	 * @return bool Whether it settled.
	 */
	public static function settle( WP_Post $post ) {
		if ( '' === self::settles_as( $post ) ) {
			return false;
		}

		$post_id = (int) $post->ID;
		$lock    = WPCPM_Mentor_Application::lock_name( $post_id );
		$token   = WPCPM_Lock::take( $lock, WPCPM_Mentor_Application::LOCK_TTL );

		if ( '' === $token ) {
			return false;
		}

		try {
			WPCPM_Mentor_Application::forget_cached( $post_id );
			wp_cache_delete( WPCPM_Mentor_Application::user_of( $post ), 'user_meta' );

			$as = self::settles_as( $post );

			if ( 'gone' === $as ) {
				self::settle_as( $post_id, 'gone', '', 0 );

				return true;
			}

			if ( 'found' === $as ) {
				self::settle_as( $post_id, 'found', trim( (string) get_user_meta( WPCPM_Mentor_Application::user_of( $post ), WPCPM_Mentors_Sync::META_RECORD_ID, true ) ), 0 );

				return true;
			}

			return false;
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Settle every active application that has something to settle.
	 *
	 * @return int How many settled.
	 */
	public static function settle_all() {
		$settled = 0;

		foreach ( WPCPM_Mentor_Application::applications( array( WPCPM_Mentor_Application::STATE_ACTIVE ) ) as $post ) {
			if ( self::settle( $post ) ) {
				++$settled;
			}
		}

		return $settled;
	}

	/**
	 * How an application would settle, read as it stands.
	 *
	 * @param WP_Post $post The application.
	 * @return string `found`, `gone`, or '' when it has nothing to settle.
	 */
	private static function settles_as( WP_Post $post ) {
		if ( WPCPM_Mentor_Application::STATE_ACTIVE !== WPCPM_Mentor_Application::state_of( $post ) || ! WPCPM_Mentor_Application::row_unwritten( $post ) ) {
			return '';
		}

		$user_id = WPCPM_Mentor_Application::user_of( $post );

		if ( ! get_userdata( $user_id ) instanceof WP_User ) {
			return 'gone';
		}

		if ( '' !== (string) get_user_meta( $user_id, WPCPM_Mentor_Provision::USER_ROW_PENDING, true ) ) {
			return '';
		}

		return '' !== trim( (string) get_user_meta( $user_id, WPCPM_Mentors_Sync::META_RECORD_ID, true ) ) ? 'found' : '';
	}

	/**
	 * Keep a settled row, with its own event.
	 *
	 * @param int    $post_id The application.
	 * @param string $state   `found` or `gone`.
	 * @param string $record  The record the account holds, for `found`.
	 * @param int    $actor   Who pressed, or 0 for the site.
	 * @param string $note    The event's note, or '' for the state's own.
	 */
	private static function settle_as( $post_id, $state, $record, $actor, $note = '' ) {
		$post = get_post( (int) $post_id );
		$kept = $post instanceof WP_Post ? WPCPM_Mentor_Application::airtable_of( $post ) : array();

		self::keep_row(
			(int) $post_id,
			array(
				'state'  => $state,
				'how'    => isset( $kept['how'] ) ? $kept['how'] : '',
				'record' => (string) $record,
			)
		);

		if ( '' === $note ) {
			$note = 'gone' === $state ? self::gone_note() : __( 'Linked by the mentors sync or by hand; nothing was written from here.', 'wpcredits-program-manager' );
		}

		WPCPM_Mentor_Application::add_event( (int) $post_id, WPCPM_Mentor_Application::EVENT_AIRTABLE_SETTLED, $actor, $note );
	}

	/**
	 * What the history says of an application whose account is gone.
	 *
	 * @return string
	 */
	private static function gone_note() {
		return __( 'The account made for this mentor no longer exists; nothing more is written for it.', 'wpcredits-program-manager' );
	}

	/*
	 * The lists
	 * --------------------------------------------------------------------
	 */

	/**
	 * The active applications whose Mentors row is still to be written and whose account still waits
	 * for it (the mentors sync clears the wait once it links the account to an Active row), oldest
	 * first, the ID ascending on a tie. It may settle rows as it reads them, as a screen draws them.
	 *
	 * @return WP_Post[]
	 */
	public static function unwritten() {
		$waiting = array();

		foreach ( WPCPM_Mentor_Application::applications( array( WPCPM_Mentor_Application::STATE_ACTIVE ) ) as $post ) {
			self::settle( $post );

			if ( self::is_unwritten( $post ) ) {
				$waiting[] = $post;
			}
		}

		return self::by_activation( $waiting, SORT_ASC );
	}

	/**
	 * How many active applications wait for their Mentors row. It may settle rows as it counts them.
	 *
	 * @return int
	 */
	public static function unwritten_count() {
		return count( self::unwritten() );
	}

	/**
	 * The Activated list: every application still waiting for its Mentors row first, oldest first,
	 * then the other active applications, the newest made a mentor first, the ID descending on a
	 * tie; at most `$limit`. It may settle rows as it reads them, as a screen draws them.
	 *
	 * @param int $limit Most rows to answer; one at least.
	 * @return WP_Post[]
	 */
	public static function activated_posts( $limit ) {
		$waiting = array();
		$rest    = array();

		foreach ( WPCPM_Mentor_Application::applications( array( WPCPM_Mentor_Application::STATE_ACTIVE ) ) as $post ) {
			self::settle( $post );

			if ( self::is_unwritten( $post ) ) {
				$waiting[] = $post;
			} else {
				$rest[] = $post;
			}
		}

		return array_slice( array_merge( self::by_activation( $waiting, SORT_ASC ), self::by_activation( $rest, SORT_DESC ) ), 0, max( 1, (int) $limit ) );
	}

	/**
	 * Whether an active application waits for its Mentors row: its row still to be written, and its
	 * account waiting for it.
	 *
	 * @param WP_Post $post The application.
	 * @return bool
	 */
	private static function is_unwritten( WP_Post $post ) {
		if ( ! WPCPM_Mentor_Application::row_unwritten( $post ) ) {
			return false;
		}

		$user_id = WPCPM_Mentor_Application::user_of( $post );

		return get_userdata( $user_id ) instanceof WP_User && '' !== (string) get_user_meta( $user_id, WPCPM_Mentor_Provision::USER_ROW_PENDING, true );
	}

	/**
	 * Applications in the order they were made mentors, the ID breaking a tie in the same direction:
	 * with `array_multisort()`, since `usort()` keeps equal rows in place only from PHP 8.0.
	 *
	 * @param WP_Post[] $posts     The applications.
	 * @param int       $direction `SORT_ASC` or `SORT_DESC`.
	 * @return WP_Post[]
	 */
	private static function by_activation( array $posts, $direction ) {
		if ( array() === $posts ) {
			return array();
		}

		$at  = array();
		$ids = array();

		foreach ( $posts as $post ) {
			$at[]  = (int) get_post_meta( (int) $post->ID, WPCPM_Mentor_Application::META_ACTIVATED_AT, true );
			$ids[] = (int) $post->ID;
		}

		array_multisort( $at, $direction, SORT_NUMERIC, $ids, $direction, SORT_NUMERIC, $posts );

		return $posts;
	}

	/*
	 * The answers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Keep what became of an application's Mentors row, every key there, stamped now.
	 *
	 * @param int   $post_id The application.
	 * @param array $row     `state` and what else is known of it.
	 */
	private static function keep_row( $post_id, array $row ) {
		update_post_meta(
			(int) $post_id,
			WPCPM_Mentor_Application::META_AIRTABLE,
			// Slashed, as post meta unslashes what it is handed and Airtable's words may hold a backslash.
			wp_slash(
				array_merge(
					array(
						'state'    => '',
						'how'      => '',
						'record'   => '',
						'error'    => '',
						'left_out' => array(),
					),
					$row,
					array( 'at' => time() )
				)
			)
		);
	}

	/**
	 * The notes of an event, joined by a space, the empty ones left out.
	 *
	 * @param string[] $notes The notes.
	 * @return string
	 */
	private static function joined( array $notes ) {
		return implode( ' ', array_filter( array_map( 'trim', array_map( 'strval', $notes ) ), 'strlen' ) );
	}

	/**
	 * What `activate()` answers, its sentence filled in for every outcome but `activated`.
	 *
	 * @param string $outcome The outcome.
	 * @param array  $more    What else it answers.
	 * @return array
	 */
	private static function answer( $outcome, array $more = array() ) {
		$answer = array_merge(
			array(
				'outcome'  => $outcome,
				'user_id'  => 0,
				'airtable' => '',
				'invited'  => false,
				'held'     => '',
				'linked'   => false,
				'message'  => '',
			),
			$more
		);

		if ( '' === $answer['message'] ) {
			$answer['message'] = self::sentence( $outcome );
		}

		return $answer;
	}

	/**
	 * The sentence of an outcome of `activate()`, for an Administrator: '' for `activated`.
	 *
	 * @param string $outcome The outcome.
	 * @return string
	 */
	private static function sentence( $outcome ) {
		switch ( $outcome ) {
			case 'unknown':
				return __( 'There is no such mentor application.', 'wpcredits-program-manager' );

			case 'off':
				return __( 'Nothing was done: Make mentors from applications is off, under Mentor applications on the Students and mentors tab of Settings.', 'wpcredits-program-manager' );

			case 'busy':
				return __( 'Another request is making this applicant a mentor at this moment, so nothing was done.', 'wpcredits-program-manager' );

			case 'not-selected':
				return __( 'This application is not selected, so it was not made a mentor.', 'wpcredits-program-manager' );

			case 'already':
				return __( 'This application was made a mentor already, so nothing was changed.', 'wpcredits-program-manager' );

			case 'already-mentor':
				return __( 'The applicant is a mentor on this site already: the application is filed as already a mentor, and nothing was sent or written to Airtable.', 'wpcredits-program-manager' );

			case 'no-account':
				return __( 'The account could not be made, so the application stays selected.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * What `retry_airtable()` answers.
	 *
	 * @param string $outcome The outcome.
	 * @param string $message Its sentence, or '' for the outcome's own.
	 * @return array
	 */
	private static function retry_answer( $outcome, $message = '' ) {
		if ( '' === $message ) {
			switch ( $outcome ) {
				case 'unknown':
					$message = __( 'There is no such mentor application.', 'wpcredits-program-manager' );
					break;

				case 'busy':
					$message = __( 'Another request is working on this application at this moment, so nothing was done.', 'wpcredits-program-manager' );
					break;

				case 'not-active':
					$message = __( 'Only an application that was made a mentor has an Airtable row to write, and this one was not.', 'wpcredits-program-manager' );
					break;

				case 'not-needed':
					$message = __( 'Nothing was written: the mentor\'s Airtable row needs nothing more.', 'wpcredits-program-manager' );
					break;

				case 'gone':
					$message = self::gone_note();
					break;

				case 'written':
					$message = __( 'The Airtable row is written, and the mentor\'s account holds its record.', 'wpcredits-program-manager' );
					break;
			}
		}

		return array(
			'outcome' => $outcome,
			'message' => $message,
		);
	}
}
