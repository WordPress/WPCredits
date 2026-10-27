<?php
/**
 * Mentors module - the account half of making a mentor (1.122.33).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create or link the WordPress account for one mentor.
 *
 * This is the account half of the mentors sync, which moved here from the sync in 1.122.33 so that
 * the sync and the activation of a mentor application make a mentor one way. A mentor is an account
 * named after their WordPress.org username, with the Mentor role, the three mentor meta, and the
 * invitation queued under the invitation setting; an account that exists is found and linked, never
 * duplicated, and never given the role by taking another from it. Only a created account has its
 * invitation queued here: for one that is linked the step reports what it was (`linked_accounts`,
 * 1.123.42) and queues nothing, since the sync and the activation each decide about the welcome.
 *
 * What an application needs beside the sync's rule:
 *
 * - A mentor with no record id yet, which is what an application is until its Mentors row exists:
 *   the record id is neither looked up nor written when it is empty.
 * - A lookup by the stored WordPress.org profile (`find_account()`), as the application form
 *   recognizes a mentor, and an account the caller already found, linked as given, so two lookups
 *   cannot make two accounts.
 * - The three keys an activation leaves on an account: while its row is not written
 *   (`USER_ROW_PENDING`); when it last made the account a mentor or last wrote or found its Mentors
 *   row (`USER_ACTIVATED_AT`), a time each of those moves on again, Write the Airtable row again
 *   included; and why it held the invitation (`USER_INVITE_HELD`). Linking the account to a row
 *   clears the first; the invitation sent from the account's row clears the third.
 */
class WPCPM_Mentor_Provision {

	/**
	 * User meta: 1 while an activation's Mentors row is pending or refused. The activation writes it,
	 * the mentors sync's revoke leaves an account that holds it alone, and linking the account to a
	 * row deletes it.
	 */
	const USER_ROW_PENDING = 'wpcpm_mentor_row_pending';

	/**
	 * User meta: when an activation last made the account a mentor, or last wrote or found its Mentors
	 * row (a Unix time). The activation writes it, the row's write just before it deletes
	 * `USER_ROW_PENDING`, and the mentors sync's revoke passes by an account stamped at or after the
	 * run's start.
	 */
	const USER_ACTIVATED_AT = 'wpcpm_mentor_activated_at';

	/**
	 * User meta: why the account's invitation is held, `login` (linked by its WordPress.org username
	 * alone, ruling R5, by an activation or by the mentors sync), `record` (another account also
	 * answered for the applicant, ruling P9b) or `bulk` (a run of the mentors sync made more than
	 * `WPCPM_Mentors_Sync::RELINK_INVITE_MAX` existing accounts active mentors at once, 1.123.42).
	 * Nothing reads which: it is held while the key is there. The invitation carries the welcome's
	 * private links, so it waits for an Administrator: to confirm the account is the person's, for
	 * the first two, and to decide that the welcome should go, for the third. The bulk invite and the
	 * invitation queue pass over an account that holds it (`WPCPM_Mail::never_invited()`,
	 * `queue_invites()`, `drain_queue()`), and the invitation sent from the account's own row deletes
	 * it. An activation that sends the invitation deletes it too.
	 */
	const USER_INVITE_HELD = 'wpcpm_mentor_invite_held';

	/**
	 * Create or link the account for one mentor.
	 *
	 * Identifies the account in order of reliability: the record ID the sync stored itself, then the
	 * email address, then the username (anything else risks writing a mentor's data onto a
	 * stranger's account); an account the caller found is taken as it is, and no lookup is made.
	 *
	 * @param array      $mentor Mentor data: `record_id` (a string, `''` when the mentor has no row
	 *                           yet), `name`, `profile` and `email`.
	 * @param array      $report The report, by reference. It gains `notices` (strings) and `stats` (the
	 *                           keys of `WPCPM_Mentors_Sync::empty_stats()`) where it lacks them, and
	 *                           both are added to as the sync's state was. An account that is linked is
	 *                           added to `linked_accounts` (1.123.42): its `user_id`, whether it
	 *                           `was_active_mentor` before the link (it held the Mentor role with its
	 *                           active flag at 1) and `how` it was found.
	 * @param array|null $found  What `find_account()` answered for this mentor, or null when the caller
	 *                           looked for nothing: when it holds an account (`user`), that account is
	 *                           linked as given and how it was found (`how`) is kept for the notice a
	 *                           username match earns. The claimed-record check still applies to it. An
	 *                           answer that found nobody is the same as null.
	 * @return int User ID, or 0 when the mentor could not be provisioned.
	 */
	public static function provision( array $mentor, array &$report, $found = null ) {
		if ( ! isset( $report['notices'] ) || ! is_array( $report['notices'] ) ) {
			$report['notices'] = array();
		}

		$report['stats'] = array_merge( WPCPM_Mentors_Sync::empty_stats(), isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array() );

		$login = WPCPM_Mentors_Sync::wporg_username( $mentor['profile'] );
		$email = sanitize_email( $mentor['email'] );

		if ( '' === $login ) {
			$report['notices'][] = sprintf(
				/* translators: 1: mentor name, 2: raw profile value from Airtable. */
				__( 'Skipped %1$s - no WordPress.org username could be read from "%2$s".', 'wpcredits-program-manager' ),
				$mentor['name'] ? $mentor['name'] : $mentor['record_id'],
				$mentor['profile']
			);
			++$report['stats']['skipped'];

			return 0;
		}

		if ( ! is_email( $email ) ) {
			$report['notices'][] = sprintf(
				/* translators: %s: mentor name. */
				__( 'Skipped %s - Airtable has no valid email address, and WordPress cannot create an account without one.', 'wpcredits-program-manager' ),
				$mentor['name'] ? $mentor['name'] : $login
			);
			++$report['stats']['skipped'];

			return 0;
		}

		if ( is_array( $found ) && isset( $found['user'] ) && $found['user'] instanceof WP_User ) {
			$user = $found['user'];
			$how  = isset( $found['how'] ) ? (string) $found['how'] : '';
		} else {
			$user = self::find_by_record_id( $mentor['record_id'] );
			$how  = 'record';

			if ( ! $user ) {
				$account = self::find_account( $email, $login );
				$user    = $account['user'];
				$how     = $account['how'];
			}
		}

		if ( $user instanceof WP_User ) {
			// A login already claimed by a different mentor record is a genuine
			// clash - two people cannot share one account.
			$claimed = (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true );

			if ( '' !== $claimed && $claimed !== $mentor['record_id'] ) {
				$report['notices'][] = sprintf(
					/* translators: 1: mentor name, 2: WordPress username. */
					__( 'Skipped %1$s - the account "%2$s" is already linked to a different Airtable mentor record. Resolve the duplicate in Airtable.', 'wpcredits-program-manager' ),
					$mentor['name'] ? $mentor['name'] : $login,
					$user->user_login
				);
				++$report['stats']['conflicts'];

				return 0;
			}

			self::link_existing_user( $user, $mentor, $how, $report );

			return (int) $user->ID;
		}

		return self::create_mentor_user( $login, $email, $mentor, $report );
	}

	/**
	 * Find the account a person already has: by their address, then their username, then, when a
	 * WordPress.org username is given, by the profile the sync keeps on an account.
	 *
	 * The answer says how the account was found, because a match on the username alone is the weakest
	 * (the mentors sync names a mentor's account after their WordPress.org username, but so may
	 * anyone), and the caller words its notice, or decides about an invitation, by it.
	 *
	 * @param string $email  The address.
	 * @param string $login  The WordPress username the account would be named, a WordPress.org
	 *                       username.
	 * @param string $handle Optional. A WordPress.org username to match against the stored profiles,
	 *                       in any case.
	 * @return array{user: WP_User|null, how: string} `user`: the account, or null. `how`: `email`,
	 *                       `login` or `profile`, or `''` when there is no account.
	 */
	public static function find_account( $email, $login, $handle = '' ) {
		$user = '' !== (string) $email ? get_user_by( 'email', $email ) : false;

		if ( $user instanceof WP_User ) {
			return array(
				'user' => $user,
				'how'  => 'email',
			);
		}

		$user = '' !== (string) $login ? get_user_by( 'login', $login ) : false;

		if ( $user instanceof WP_User ) {
			return array(
				'user' => $user,
				'how'  => 'login',
			);
		}

		$handle = strtolower( trim( (string) $handle ) );

		if ( '' !== $handle ) {
			foreach ( (array) get_users(
				array(
					'meta_key' => WPCPM_Mentors_Sync::META_PROFILE,
					'fields'   => 'all',
				)
			) as $candidate ) {
				if ( $candidate instanceof WP_User && WPCPM_Mentors_Sync::wporg_username( (string) get_user_meta( $candidate->ID, WPCPM_Mentors_Sync::META_PROFILE, true ) ) === $handle ) {
					return array(
						'user' => $candidate,
						'how'  => 'profile',
					);
				}
			}
		}

		return array(
			'user' => null,
			'how'  => '',
		);
	}

	/**
	 * Find the account already linked to an Airtable mentor record.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return WP_User|null Null when no account holds it, and for an empty record ID.
	 */
	public static function find_by_record_id( $record_id ) {
		if ( '' === (string) $record_id ) {
			return null;
		}

		$users = get_users(
			array(
				'meta_key'   => WPCPM_Mentors_Sync::META_RECORD_ID,
				'meta_value' => $record_id,
				'number'     => 1,
			)
		);

		return ! empty( $users[0] ) ? $users[0] : null;
	}

	/**
	 * Attach mentor data to an account that already exists.
	 *
	 * What the account was before the link is added to the report's `linked_accounts` (1.123.42), for a
	 * caller that decides about the welcome: this step queues no invitation for an account it links,
	 * since the mentors sync and the activation of an application each decide that by their own rules.
	 *
	 * @param WP_User $user   Existing user.
	 * @param array   $mentor Mentor data.
	 * @param string  $how    How the user was matched: record, email, login or profile.
	 * @param array   $report The report, by reference.
	 */
	private static function link_existing_user( WP_User $user, array $mentor, $how, array &$report ) {
		$was_linked = '' !== (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true );
		$is_admin   = in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true );
		$is_mentor  = in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true );

		// Read before the flag and the role are written below: a mentor is active on the site when the
		// account holds the role and its flag is at 1, which is what a former mentor, or an account that
		// was a student or an institution member, is not.
		$report['linked_accounts'][] = array(
			'user_id'           => (int) $user->ID,
			'was_active_mentor' => $is_mentor && 1 === (int) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_ACTIVE, true ),
			'how'               => (string) $how,
		);

		if ( '' !== $mentor['record_id'] ) {
			update_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, $mentor['record_id'] );
			// The account has its row now, so an activation is no longer waiting to write one.
			delete_user_meta( $user->ID, self::USER_ROW_PENDING );
		}

		// Slashed, because user meta unslashes what it is handed and the base's cell is not.
		update_user_meta( $user->ID, WPCPM_Mentors_Sync::META_PROFILE, wp_slash( $mentor['profile'] ) );
		update_user_meta( $user->ID, WPCPM_Mentors_Sync::META_ACTIVE, 1 );

		if ( ! $is_mentor && ! $is_admin ) {
			// add_role() rather than set_role(): the account may legitimately be an
			// author or editor as well, and a sync should not demote anyone.
			$user->add_role( WPCPM_Roles::ROLE_MENTOR );
			$report['notices'][] = sprintf(
				/* translators: 1: WordPress username, 2: mentor name. */
				__( 'Added the Mentor role to the existing account "%1$s" (%2$s).', 'wpcredits-program-manager' ),
				$user->user_login,
				$mentor['name']
			);
		}

		// A slashed copy: core slashes the stored user, merges what it is handed and unslashes the
		// result, so it expects slashed input.
		if ( $mentor['profile'] && $mentor['profile'] !== $user->user_url ) {
			wp_update_user(
				wp_slash(
					array(
						'ID'       => $user->ID,
						'user_url' => esc_url_raw( $mentor['profile'] ),
					)
				)
			);
		}

		if ( $was_linked ) {
			++$report['stats']['updated'];
		} else {
			++$report['stats']['linked'];
			if ( 'login' === $how ) {
				$report['notices'][] = sprintf(
					/* translators: 1: WordPress username, 2: mentor name. */
					__( 'Linked mentor record to the pre-existing account "%1$s" (%2$s) by username. Confirm it is the same person.', 'wpcredits-program-manager' ),
					$user->user_login,
					$mentor['name']
				);
			}
		}
	}

	/**
	 * Create a fresh Mentor account.
	 *
	 * @param string $login  WordPress.org username.
	 * @param string $email  Email address.
	 * @param array  $mentor Mentor data.
	 * @param array  $report The report, by reference.
	 * @return int User ID, or 0 on failure.
	 */
	private static function create_mentor_user( $login, $email, array $mentor, array &$report ) {
		$user_id = WPCPM_Roles::insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $mentor['name'] ? $mentor['name'] : $login,
				'nickname'     => $mentor['name'] ? $mentor['name'] : $login,
				'user_url'     => esc_url_raw( $mentor['profile'] ),
				'role'         => WPCPM_Roles::ROLE_MENTOR,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$report['notices'][] = sprintf(
				/* translators: 1: WordPress username, 2: error message. */
				__( 'Could not create the account "%1$s": %2$s', 'wpcredits-program-manager' ),
				$login,
				$user_id->get_error_message()
			);
			++$report['stats']['skipped'];

			return 0;
		}

		if ( '' !== $mentor['record_id'] ) {
			update_user_meta( $user_id, WPCPM_Mentors_Sync::META_RECORD_ID, $mentor['record_id'] );
		}

		// Slashed, as `link_existing_user()` writes it.
		update_user_meta( $user_id, WPCPM_Mentors_Sync::META_PROFILE, wp_slash( $mentor['profile'] ) );
		update_user_meta( $user_id, WPCPM_Mentors_Sync::META_ACTIVE, 1 );

		++$report['stats']['created'];

		// wp_insert_user() sends nothing on its own. Accounts are created with a
		// random password, so an invite is the only way in - but sending ~90 of
		// them is a decision for a human, hence the opt-in setting.
		if ( WPCPM_Settings::get_value( 'send_welcome_email' ) ) {
			// Queued, not sent. A first sync creates around ninety accounts inside one
			// request, and ninety sends from there means a timeout or a host's hourly mail
			// limit somewhere in the middle with no way to know how far it got.
			WPCPM_Mail::queue_invite( $user_id );
			++$report['stats']['invited'];
		}

		return (int) $user_id;
	}
}
