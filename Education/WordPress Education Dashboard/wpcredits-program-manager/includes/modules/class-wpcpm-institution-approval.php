<?php
/**
 * Institutions module - approving an application into a record, a row and an account.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one path that turns a public application into an institution the site can act for.
 *
 * Three halves, in this order and never another: the Airtable record, the pipeline row with
 * its agreement option, then the account. Airtable first because the record ID is the
 * account's identity: every stamp, every roster read and every decision the fence makes
 * names a record, so an account created before one exists is the shape the fence cannot
 * tolerate. The index row comes next because `WPCPM_Institution_Members::attach()` refuses a
 * record the index does not hold, and the account cannot wait for the nightly sync.
 *
 * There is no "partially approved" state and no repair path. Every half is stamped on the
 * application the moment it lands, so a request that dies between two of them is finished by
 * pressing Approve again: the stamped halves are skipped, the missing ones are done. That is
 * why the meta is written before the next call rather than at the end of a successful run,
 * and why a failure returns the reason with the stamps intact instead of undoing what worked.
 *
 * "The stamped halves are skipped" is about the record and the account row, and about nothing
 * else. The account half is three things - the account, its membership and its invitation -
 * and only the first of them is ours to stamp; the membership lives on the account's own meta,
 * which `WPCPM_Institution_Members` owns every write to, and the invitation lives in the mail
 * queue. So both are asked again on every press and each says for itself whether it is already
 * done. A press that took the account stamp as proof of all three would leave an institution
 * whose only account belongs to nobody and can see nothing, and would report success.
 *
 * Two rules are worth stating on their own, because both look like over-caution until the
 * day they are not:
 *
 * - **Found by email is a conflict, not a match.** `get_user_by( 'email' )` finding anything
 *   at all refuses the approval. A student's account, a mentor's, an administrator's made by
 *   hand: adopting any of them would attach an institution to a person who never asked for
 *   one and mail them a password link for it. The one exception is the account this very
 *   approval created on an earlier press, which is stamped on the application and is ours.
 * - **The duplicate search adopts, it never merges.** A hit means the program already has
 *   this institution in the base, usually from outreach; the application then joins that
 *   record instead of creating a second one, and nothing the applicant typed is written over
 *   the record the program already keeps.
 *
 * All static, in the shape of `WPCPM_Institution_Agreement`. The queue screen owns the
 * button, the capability and the nonce; this owns what happens after them.
 */
class WPCPM_Institution_Approval {

	/** Option prefix for the per-application lock. The rest is the post ID. */
	const LOCK_PREFIX = 'wpcpm_app_lock_';

	/**
	 * How long a lock is honoured, in seconds.
	 *
	 * Short, unlike the agreement's five minutes: this holds across at most three API calls
	 * on a screen a manager is watching, and a lock nobody released must not keep the one
	 * button that finishes a half-done approval shut for the rest of the afternoon.
	 */
	const LOCK_TIMEOUT = 60;

	/** Where a record this handler creates starts, when the setting names nothing. */
	const NEW_STAGE = 'First Contact Made';

	/** What `Agreement Status` reads on a record this handler creates (design spec 7.4, T1). */
	const AGREEMENT_NOT_STARTED = 'Not started';

	/** Application events this handler writes, in the words the open application prints. */
	const EVENT_RECORD_CREATED  = 'record created';
	const EVENT_RECORD_ADOPTED  = 'record adopted';
	const EVENT_ACCOUNT_CREATED = 'account created';
	const EVENT_APPROVED        = 'approved';

	/** Audit kind, so the log lists this beside the membership rows it causes. */
	const LOG_APPROVED = 'application_approved';

	/**
	 * What `WPCPM_Institution_Members::attach()` refuses an account it has already attached
	 * to this institution with.
	 *
	 * The one refusal a resume reads as success: it says the membership half landed on an
	 * earlier press. Named here because it is another class's contract rather than this
	 * one's, and `bin/test-institution-approval.php` checks that class still answers with it.
	 */
	const MEMBER_ALREADY = 'wpcpm_member_already';

	/**
	 * The states an application may be approved from.
	 *
	 * Everything a manager has not decided yet: a new submission, one the anti-spam checks
	 * held, and one waiting on an answer to a question. `spam`, `rejected` and `approved` are
	 * decisions, and a decided application is reopened before it is approved.
	 *
	 * @return string[]
	 */
	public static function approvable() {
		return array(
			WPCPM_Institution_Application::STATE_NEW,
			WPCPM_Institution_Application::STATE_HELD,
			WPCPM_Institution_Application::STATE_INFO,
		);
	}

	/**
	 * Approve one application: the record, the row and the account.
	 *
	 * The ten steps of design spec 7.3, in order. Steps 1 and 2 (the capability and the
	 * nonce keyed to the application) belong to the queue's own handler, which is where the
	 * button is; the actor it names is checked again here, because this is the method that
	 * creates an Airtable record and mails somebody a link to set a password.
	 *
	 * Every refusal after the lock is taken gives it back through `refuse()`. The refusals
	 * before it return on their own and must not touch it: a lock this call never took
	 * belongs to another request, and deleting it would be the one bug the lock exists to
	 * prevent.
	 *
	 * @param int $application_id The `wpcpm_inst_app` post.
	 * @param int $manager_id     The program manager pressing Approve.
	 * @return array|WP_Error `array( 'record' => string, 'user_id' => int, 'adopted' => bool )`.
	 */
	public static function approve( $application_id, $manager_id ) {
		$application_id = absint( $application_id );
		$manager_id     = absint( $manager_id );
		$post           = get_post( $application_id );

		if ( ! $post instanceof WP_Post || WPCPM_Institution_Application::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'wpcpm_app_unknown', __( 'There is no application with that ID.', 'wpcredits-program-manager' ) );
		}

		// The actor is checked rather than trusted because it is the actor the audit row and
		// the membership record will both name. The queue's handler has already asked whether
		// the person pressing the button may manage the program; this asks whether the person
		// the row is about to credit may.
		if ( ! user_can( $manager_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return new WP_Error( 'wpcpm_app_actor', __( 'Only a program manager can approve an application.', 'wpcredits-program-manager' ) );
		}

		$state = (string) get_post_meta( $application_id, WPCPM_Institution_Application::META_STATE, true );

		if ( ! in_array( $state, self::approvable(), true ) ) {
			return new WP_Error( 'wpcpm_app_state', __( 'That application has already been decided. Reopen it first.', 'wpcredits-program-manager' ) );
		}

		// Approval must never mail a password link to an address nobody claimed: the whole
		// application is a stranger's typing until the address behind it answers.
		if ( '' === trim( (string) get_post_meta( $application_id, WPCPM_Institution_Application::META_VERIFIED, true ) ) ) {
			return new WP_Error( 'wpcpm_app_unverified', __( 'That address has not been confirmed yet: the applicant has not used the link in their acknowledgement. Nothing here can resend it, so write to them and ask them to use it, or ask them to apply again.', 'wpcredits-program-manager' ) );
		}

		$stored = get_post_meta( $application_id, WPCPM_Institution_Application::META_FIELDS, true );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return new WP_Error( 'wpcpm_app_fields', __( 'That application carries no answers, so there is nothing to create a record from.', 'wpcredits-program-manager' ) );
		}

		if ( ! self::lock( $application_id ) ) {
			return new WP_Error( 'wpcpm_app_busy', __( 'That application is being approved right now. Give it a minute, then look at it again.', 'wpcredits-program-manager' ) );
		}

		$fields = WPCPM_Institutions_Sync::fields();
		$name   = self::cell( $stored, $fields['name'] );
		$email  = sanitize_email( self::cell( $stored, $fields['contact_email'] ) );

		if ( ! is_email( $email ) ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_app_no_email', __( 'That application holds no address WordPress can make an account with.', 'wpcredits-program-manager' ) ) );
		}

		// A nameless application would send `TRIM(LOWER({Name})) = ''` to the base, which
		// matches every nameless record it holds, and the base holds two. Adoption is the one
		// step nothing later undoes, so it is refused rather than guessed at.
		if ( '' === $name ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_app_name', __( 'That application holds no institution name, so it cannot be checked against the records the program already has.', 'wpcredits-program-manager' ) ) );
		}

		$ours  = self::stamped_user( $application_id );
		$found = get_user_by( 'email', $email );

		// Design spec 7.3, step 5. Found by email with no institution stamp is a conflict and
		// not a match: `provision_mentor()`'s conflict test only fires on a different stamp of
		// the same kind, so a student, a mentor or a hand-made administrator would otherwise be
		// adopted here and mailed a password link for an institution nobody asked them to act
		// for. The single exception is the account an earlier press of this button created,
		// which the application names; without it a half-done approval could never be finished.
		if ( $found instanceof WP_User && (int) $found->ID !== $ours ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_app_email', __( 'That address already has an account on this site. That is a conflict, not a match: look at the account before approving.', 'wpcredits-program-manager' ) ) );
		}

		$country_id = trim( (string) get_post_meta( $application_id, WPCPM_Institution_Application::META_COUNTRY, true ) );
		$countries  = WPCPM_Countries::options();

		// Re-read rather than trusted: the country was resolved when the form was posted, and
		// a record deleted or renamed in the Countries table since would make the link cell a
		// 422 for the whole create, after the applicant has been told they are approved.
		if ( '' === $country_id || ! isset( $countries[ $country_id ] ) ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_app_country', __( 'The country this application named no longer resolves. Refresh the countries, then try again.', 'wpcredits-program-manager' ) ) );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$record   = self::stamped_record( $application_id );
		$adopted  = self::has_event( $application_id, self::EVENT_RECORD_ADOPTED );
		$cells    = array();

		if ( '' === $record ) {
			$hit = self::search( $airtable, $settings, $fields, $name, $email );

			if ( is_wp_error( $hit ) ) {
				return self::refuse( $application_id, $hit );
			}

			if ( '' !== $hit['id'] ) {
				$record  = $hit['id'];
				$cells   = $hit['cells'];
				$adopted = true;

				update_post_meta( $application_id, WPCPM_Institution_Application::META_RECORD, $record );
				self::event( $application_id, self::EVENT_RECORD_ADOPTED, $manager_id, $record );
			} else {
				$created = self::create( $airtable, $settings, $fields, $stored, $country_id );

				if ( is_wp_error( $created ) ) {
					return self::refuse( $application_id, $created );
				}

				$record = $created;

				// Stamped the moment it lands, before the row and long before the account: a
				// request that dies on the next line has still recorded the one thing that
				// cannot be undone, and the next press of Approve adopts nothing and creates
				// nothing because this stamp is here.
				update_post_meta( $application_id, WPCPM_Institution_Application::META_RECORD, $record );
				self::event( $application_id, self::EVENT_RECORD_CREATED, $manager_id, $record );
			}
		}

		$indexed = self::index( $airtable, $settings, $fields, $record, $stored, $country_id, $adopted, $cells );

		if ( is_wp_error( $indexed ) ) {
			return self::refuse( $application_id, $indexed );
		}

		$user_id = self::account( $application_id, $record, $stored, $fields, $email, $manager_id );

		if ( is_wp_error( $user_id ) ) {
			return self::refuse( $application_id, $user_id );
		}

		update_post_meta( $application_id, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_APPROVED );
		self::event( $application_id, self::EVENT_APPROVED, $manager_id, $record );

		// Design spec 5.6: every act that changes what somebody may do writes one row carrying
		// the ground it was allowed on. There is no `decide()` above it to take a ground from,
		// because the subject of this decision is an institution that did not exist when it was
		// made; the ground is the manager capability, which is the only ground approval has.
		// The evidence is `live`: what the duplicate search read from the base is what decided
		// whether this institution was created or joined.
		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_APPROVED,
				'institution' => $record,
				'subject'     => (string) $application_id,
				'actor'       => $manager_id,
				'ground'      => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'     => $adopted
					? __( 'An application was approved and joined to a record the program already had.', 'wpcredits-program-manager' )
					: __( 'An application was approved, and the institution record and its first account were created.', 'wpcredits-program-manager' ),
				'data'        => array(
					'application' => $application_id,
					'user'        => $user_id,
					'adopted'     => $adopted,
				),
			)
		);

		self::unlock( $application_id );

		return array(
			'record'  => $record,
			'user_id' => $user_id,
			'adopted' => $adopted,
		);
	}

	/**
	 * The Airtable record this application already has, or ''.
	 *
	 * @param int $application_id The application post.
	 * @return string
	 */
	public static function stamped_record( $application_id ) {
		$record = trim( (string) get_post_meta( absint( $application_id ), WPCPM_Institution_Application::META_RECORD, true ) );

		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}

	/**
	 * The account this application already created, or 0.
	 *
	 * A stamp naming an account that has since been deleted reads as 0, so the approval
	 * makes the missing one rather than reporting a half nobody can see.
	 *
	 * @param int $application_id The application post.
	 * @return int
	 */
	public static function stamped_user( $application_id ) {
		$user_id = absint( get_post_meta( absint( $application_id ), WPCPM_Institution_Application::META_USER, true ) );

		return ( $user_id && get_userdata( $user_id ) ) ? $user_id : 0;
	}

	/**
	 * Whether an approval was begun and left unfinished.
	 *
	 * What the queue's half-done banner asks, so the sentence "press Approve again to finish"
	 * is printed on exactly the applications where pressing it does something.
	 *
	 * The record landed and nothing has been decided since, which is the whole test. Not "the
	 * account is missing": a membership that failed to attach leaves an account that exists
	 * and can do nothing, and that is precisely the half nobody would otherwise be told about.
	 * The state is what makes the sentence true, because an application already decided is one
	 * the button refuses.
	 *
	 * @param int $application_id The application post.
	 * @return bool
	 */
	public static function is_half_done( $application_id ) {
		$application_id = absint( $application_id );

		if ( '' === self::stamped_record( $application_id ) ) {
			return false;
		}

		return in_array( (string) get_post_meta( $application_id, WPCPM_Institution_Application::META_STATE, true ), self::approvable(), true );
	}

	/**
	 * Look for the institution the program may already have.
	 *
	 * The trimmed, lower-cased comparison of design spec 7.2, made in the base rather than
	 * against the index: a record created by outreach an hour ago is exactly the one this
	 * must find, and the index is as old as the last sync. Outreach records often carry no
	 * `Contact Email` at all, which is why the name is half the test.
	 *
	 * The fields asked for are the sync's list and nothing else, so the answer carries what
	 * an index row is made of and none of the prose columns beside it.
	 *
	 * @param WPCPM_Airtable $airtable Client to read through.
	 * @param array          $settings Plugin settings.
	 * @param array          $fields   The sync's column map.
	 * @param string         $name     The institution's name, as typed.
	 * @param string         $email    The contact address, already validated.
	 * @return array|WP_Error `array( 'id' => string, 'cells' => array )`; `id` is '' for no hit.
	 */
	private static function search( WPCPM_Airtable $airtable, array $settings, array $fields, $name, $email ) {
		$formula = sprintf(
			'OR( TRIM(LOWER({%1$s})) = %2$s, LOWER({%3$s}) = %4$s )',
			$fields['name'],
			self::quote( self::lower( $name ) ),
			$fields['contact_email'],
			self::quote( self::lower( $email ) )
		);

		$page = $airtable->fetch_page(
			$settings['institutions_table'],
			array(
				'formula' => $formula,
				'fields'  => array_values( $fields ),
			)
		);

		// A search that could not be made is not "no duplicate". Creating a record on a failed
		// read is how a base ends up with two of an institution, and the second one is the one
		// nobody notices until a manager wonders which roster is real.
		if ( is_wp_error( $page ) ) {
			return new WP_Error( 'wpcpm_app_search', $page->get_error_message() );
		}

		foreach ( $page['records'] as $found ) {
			$record_id = isset( $found['id'] ) ? trim( (string) $found['id'] ) : '';

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			return array(
				'id'    => $record_id,
				'cells' => ( isset( $found['fields'] ) && is_array( $found['fields'] ) ) ? $found['fields'] : array(),
			);
		}

		return array(
			'id'    => '',
			'cells' => array(),
		);
	}

	/**
	 * Create the institution record.
	 *
	 * `array_merge()` and not `+`: `+` keeps the left operand, and the stored answers would
	 * then decide `Country`, `Current Stage` and the consent checkbox. The applicant's country
	 * is a string where the column is a link, and the checkbox column is sent without
	 * `typecast`, so a string there is a 422 for the whole record. The four values below are
	 * the server's, and merging in this order is what makes them so.
	 *
	 * @param WPCPM_Airtable $airtable   Client to write through.
	 * @param array          $settings   Plugin settings.
	 * @param array          $fields     The sync's column map.
	 * @param array          $stored     The application's answers, keyed by column name.
	 * @param string         $country_id Countries record ID, already resolved.
	 * @return string|WP_Error The new record ID.
	 */
	private static function create( WPCPM_Airtable $airtable, array $settings, array $fields, array $stored, $country_id ) {
		$stage = trim( (string) WPCPM_Settings::get_value( 'institution_new_stage', self::NEW_STAGE ) );

		$cells = array_merge(
			$stored,
			array(
				$fields['country']    => array( $country_id ),
				$fields['stage']      => '' !== $stage ? $stage : self::NEW_STAGE,
				// A PHP boolean, not "1" and not "true": the column is a checkbox and
				// `create_records()` sends no `typecast`.
				$fields['consent']    => true,
				$fields['agr_status'] => self::AGREEMENT_NOT_STARTED,
			)
		);

		$created = $airtable->create_records( $settings['institutions_table'], array( array( 'fields' => $cells ) ) );

		// An empty answer is a refusal too: `create_records()` drops a record it cannot send
		// and reports the ones it made, so "nothing was created" must not read as success on
		// the one path where success opens an account.
		if ( is_wp_error( $created ) ) {
			return new WP_Error( 'wpcpm_app_airtable', $created->get_error_message() );
		}

		$record = ( ! empty( $created ) && is_array( $created ) ) ? trim( (string) $created[0] ) : '';

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return new WP_Error( 'wpcpm_app_airtable', __( 'Airtable did not answer with a record ID, so nothing was created here either.', 'wpcredits-program-manager' ) );
		}

		return $record;
	}

	/**
	 * Put the institution in the pipeline index, with an agreement option to match.
	 *
	 * `attach()` refuses a record the index does not hold, and the account half is next, so
	 * this cannot wait for the nightly sync. The agreement option is written the way
	 * `apply_report()` bridges the same gap: the gate reads that option and nothing else, and
	 * an institution with no row at all is locked, which is right but says nothing.
	 *
	 * A created record's row is the application's own answers, which is exactly what was just
	 * written to the base. An adopted record's row is never made of them: that institution
	 * has a life in the base already, and its stage, its contact and its agreement are the
	 * program's facts and not an applicant's. Where the index already holds it, the sync's
	 * copy stands untouched.
	 *
	 * @param WPCPM_Airtable $airtable   Client, for the one live read an adopted record may need.
	 * @param array          $settings   Plugin settings.
	 * @param array          $fields     The sync's column map.
	 * @param string         $record     Institutions record ID.
	 * @param array          $stored     The application's answers, keyed by column name.
	 * @param string         $country_id Countries record ID.
	 * @param bool           $adopted    Whether the record was already the program's.
	 * @param array          $cells      The adopted record's cells when the search read them.
	 * @return true|WP_Error
	 */
	private static function index( WPCPM_Airtable $airtable, array $settings, array $fields, $record, array $stored, $country_id, $adopted, array $cells ) {
		$has_row    = WPCPM_Institutions_Index::has( $record );
		$has_option = null !== WPCPM_Institution_Agreement::option( $record );

		if ( ! $adopted ) {
			if ( ! $has_row ) {
				WPCPM_Institutions_Index::insert( self::row_from_application( $record, $fields, $stored, $country_id ) );
			}

			// Only when there is nothing readable there. `rebuild()` answers with an empty
			// array when a sync holds the record's lock, so a first press can leave no option
			// at all and the second one has to write it; an option that does exist is at least
			// as fresh as the empty block this would put over it.
			if ( ! $has_option ) {
				WPCPM_Institution_Agreement::rebuild( $record, self::empty_block() );
			}

			return true;
		}

		if ( $has_row && $has_option ) {
			return true;
		}

		if ( empty( $cells ) ) {
			// The search that adopted this record ran on an earlier press and its answer is
			// gone. Read the record rather than describe it from the application: writing an
			// applicant's stage over a Confirmed institution's row, or `Not started` over an
			// agreement already on file, would lock out the members it has.
			$live = $airtable->get_record( $settings['institutions_table'], $record );

			if ( is_wp_error( $live ) ) {
				return new WP_Error( 'wpcpm_app_airtable', $live->get_error_message() );
			}

			$cells = ( isset( $live['fields'] ) && is_array( $live['fields'] ) ) ? $live['fields'] : array();
		}

		if ( ! $has_row ) {
			WPCPM_Institutions_Index::insert( self::row_from_cells( $record, $fields, $cells ) );
		}

		WPCPM_Institution_Agreement::rebuild( $record, self::block_from_cells( $fields, $cells ) );

		return true;
	}

	/**
	 * Create the institution's first account, make it a member, and invite it.
	 *
	 * Three things, and every press does whichever of them is still missing. Only the account
	 * is skipped on a stamp, because it is the only one this handler could stamp twice: a
	 * second `wp_insert_user()` for the same address is a second account with nobody to merge
	 * it. The membership and the invitation are asked again every time and answer for
	 * themselves - `attach()` with `MEMBER_ALREADY` for an account that is already this
	 * institution's, `queue_invites()` by dropping anybody already queued or already stamped
	 * invited - so a press that finds them done does them no second time, and a press that
	 * finds them missing finishes the half. Returning early on the account stamp is what this
	 * must never do again: an attachment that failed once would then never be made, and the
	 * next press would report success over an account belonging to nobody.
	 *
	 * The stamp goes on between the account and the membership, not after both, because the
	 * conflict test at the top of `approve()` refuses any address that already has an account
	 * and would otherwise refuse the very account this handler had just made.
	 *
	 * An account that could not be attached is left standing rather than deleted: deleting a
	 * person's account to tidy up a failed write is worse than the mess it tidies, the next
	 * press attaches it, and a manager can add it by hand from the People card meanwhile.
	 *
	 * @param int    $application_id The application post.
	 * @param string $record         Institutions record ID.
	 * @param array  $stored         The application's answers, keyed by column name.
	 * @param array  $fields         The sync's column map.
	 * @param string $email          The contact address, already validated.
	 * @param int    $manager_id     The program manager pressing Approve.
	 * @return int|WP_Error The account's ID.
	 */
	private static function account( $application_id, $record, array $stored, array $fields, $email, $manager_id ) {
		$user_id = self::stamped_user( $application_id );

		if ( ! $user_id ) {
			$name = self::cell( $stored, $fields['contact_person'] );

			if ( '' === $name ) {
				$name = self::cell( $stored, $fields['name'] );
			}

			$login   = self::unique_login( $email );
			$created = WPCPM_Roles::insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24, true, true ),
					'display_name' => '' !== $name ? $name : $login,
					'nickname'     => '' !== $name ? $name : $login,
					'role'         => WPCPM_Roles::ROLE_INSTITUTION,
				)
			);

			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$user_id = (int) $created;

			update_post_meta( $application_id, WPCPM_Institution_Application::META_USER, $user_id );
			self::event( $application_id, self::EVENT_ACCOUNT_CREATED, $manager_id, (string) $user_id );
		}

		$attached = self::attach( $user_id, $record, $manager_id );

		if ( is_wp_error( $attached ) ) {
			return $attached;
		}

		// Queued rather than sent, like every other invitation: the queue is what the mail log
		// and the stop control are built on. `welcome_email()`'s institution branch reads
		// `is_settled()` at send time, so the wording is the new institution's one sentence
		// about the agreement rather than a dashboard link to a page it cannot open yet.
		// Asked on every press because the queue is what remembers: it drops an account that
		// is waiting in it or has already been mailed, so a resume never sends a second link.
		WPCPM_Mail::queue_invites( array( $user_id ) );

		return $user_id;
	}

	/**
	 * Make the account this institution's member, on this press or on a later one.
	 *
	 * The membership is not stamped on the application, because it is not the application's
	 * to stamp: it lives on the account's own meta and `WPCPM_Institution_Members` owns every
	 * write to it. So it is asked for every time and the answer is read rather than the stamp.
	 * `MEMBER_ALREADY` is the answer a half already done gives and is a success here; every
	 * other refusal is real and stops the approval, `wpcpm_member_elsewhere` above all, since
	 * an account somebody has moved to another institution since is a thing a person has to
	 * look at rather than a step to repeat.
	 *
	 * The comparison of the two record IDs happens inside `attach()`, which is the point: an
	 * institution ID is compared in the class that owns it and nowhere else.
	 *
	 * @param int    $user_id    The account.
	 * @param string $record     Institutions record ID.
	 * @param int    $manager_id The program manager pressing Approve.
	 * @return true|WP_Error
	 */
	private static function attach( $user_id, $record, $manager_id ) {
		$attached = WPCPM_Institution_Members::attach( $user_id, $record, WPCPM_Institution_Members::HOW_APPROVED, $manager_id );

		if ( ! is_wp_error( $attached ) ) {
			return true;
		}

		return self::MEMBER_ALREADY === $attached->get_error_code() ? true : $attached;
	}

	/**
	 * The index row for a record this handler created.
	 *
	 * Built from `empty_row()` so a shape change is a failing test rather than a row missing
	 * a key, and from the same answers that were written to the base a moment ago, so the
	 * site and the base agree until the first sync reads the record properly.
	 *
	 * @param string $record     Institutions record ID.
	 * @param array  $fields     The sync's column map.
	 * @param array  $stored     The application's answers, keyed by column name.
	 * @param string $country_id Countries record ID.
	 * @return array
	 */
	private static function row_from_application( $record, array $fields, array $stored, $country_id ) {
		$stage = trim( (string) WPCPM_Settings::get_value( 'institution_new_stage', self::NEW_STAGE ) );

		return array_merge(
			WPCPM_Institutions_Index::empty_row(),
			array(
				'record_id'      => $record,
				'name'           => self::cell( $stored, $fields['name'] ),
				'stage'          => '' !== $stage ? $stage : self::NEW_STAGE,
				'country'        => $country_id,
				'country_name'   => WPCPM_Countries::name_of( $country_id ),
				'city'           => self::cell( $stored, $fields['city'] ),
				'website'        => self::cell( $stored, $fields['website'] ),
				'contact_person' => self::cell( $stored, $fields['contact_person'] ),
				'contact_email'  => self::lower( self::cell( $stored, $fields['contact_email'] ) ),
				'created'        => wp_date( 'Y-m-d' ),
				// The application could not have been stored without it: consent is a
				// precondition of the form, not one of its answers.
				'consent'        => true,
				// The index's own agreement shape, not the rebuild block: the two differ by
				// one key, `has_document` against `document`, and `insert()` keeps the one it
				// knows. Everything but the status takes the empty row's default.
				'agreement'      => array( 'status' => self::AGREEMENT_NOT_STARTED ),
			)
		);
	}

	/**
	 * The index row for a record the program already had, from the record itself.
	 *
	 * The sync's mapping, applied to one record instead of a page of them. It is written out
	 * again rather than shared because the sync's copy lives inside its paging loop and
	 * counts statistics as it goes; what matters is that both read through the same column
	 * map and produce the same shape, which `bin/test-institution-approval.php` asserts.
	 *
	 * @param string $record Institutions record ID.
	 * @param array  $fields The sync's column map.
	 * @param array  $cells  The record's cells, as Airtable answered.
	 * @return array
	 */
	private static function row_from_cells( $record, array $fields, array $cells ) {
		$country_ids = WPCPM_Airtable::link_ids( isset( $cells[ $fields['country'] ] ) ? $cells[ $fields['country'] ] : array() );
		$country     = ( ! empty( $country_ids ) && WPCPM_Mentors_Sync::is_record_id( $country_ids[0] ) ) ? $country_ids[0] : '';
		$block       = self::block_from_cells( $fields, $cells );

		return array_merge(
			WPCPM_Institutions_Index::empty_row(),
			array(
				'record_id'      => $record,
				// As stored, trailing space and all: ten records in the base end in one and
				// the index is the one place that must agree with it byte for byte.
				'name'           => self::flat( $cells, $fields['name'] ),
				'stage'          => trim( self::flat( $cells, $fields['stage'] ) ),
				'country'        => $country,
				'country_name'   => '' !== $country ? WPCPM_Countries::name_of( $country ) : '',
				'city'           => trim( self::flat( $cells, $fields['city'] ) ),
				'website'        => trim( self::flat( $cells, $fields['website'] ) ),
				'contact_person' => trim( self::flat( $cells, $fields['contact_person'] ) ),
				'contact_email'  => self::lower( trim( self::flat( $cells, $fields['contact_email'] ) ) ),
				'created'        => wp_date( 'Y-m-d' ),
				'consent'        => ! empty( $cells[ $fields['consent'] ] ),
				'confirmed_on'   => self::date_part( self::flat( $cells, $fields['confirmed_on'] ) ),
				// The Drive link itself never enters the index: it lives on the per-institution
				// agreement option, and the row says only whether there is one.
				'agreement'      => array_merge( $block, array( 'has_document' => '' !== $block['document'] ) ),
			)
		);
	}

	/**
	 * The agreement block of a record that has none: what a new institution starts with.
	 *
	 * @return array
	 */
	private static function empty_block() {
		return array(
			'status'           => self::AGREEMENT_NOT_STARTED,
			'kind'             => '',
			'accepted_on'      => '',
			'signed_on'        => '',
			'accepted_by'      => '',
			'document'         => '',
			'submitted_on'     => '',
			'template_version' => '',
		);
	}

	/**
	 * The agreement block as the base holds it, for a record the program already had.
	 *
	 * @param array $fields The sync's column map.
	 * @param array $cells  The record's cells, as Airtable answered.
	 * @return array
	 */
	private static function block_from_cells( array $fields, array $cells ) {
		return array(
			'status'           => trim( self::flat( $cells, $fields['agr_status'] ) ),
			'kind'             => trim( self::flat( $cells, $fields['agr_kind'] ) ),
			'accepted_on'      => self::date_part( self::flat( $cells, $fields['agr_accepted_on'] ) ),
			'signed_on'        => self::date_part( self::flat( $cells, $fields['agr_signed_on'] ) ),
			'accepted_by'      => trim( self::flat( $cells, $fields['agr_accepted_by'] ) ),
			'document'         => trim( self::flat( $cells, $fields['agr_document'] ) ),
			'submitted_on'     => self::date_part( self::flat( $cells, $fields['agr_submitted_on'] ) ),
			'template_version' => trim( self::flat( $cells, $fields['agr_template'] ) ),
		);
	}

	/**
	 * A free username, from the local part of the contact address.
	 *
	 * The shape both syncs use, for the reason they use it: the address is the only
	 * identifier an application gives us, and a login is not an identity here. Plenty of
	 * institutions write from `info@`, so the numeric suffix is the common case rather than
	 * an edge one; what matters is that it is free.
	 *
	 * @param string $email Contact address.
	 * @return string
	 */
	private static function unique_login( $email ) {
		$base = strtolower( (string) strstr( (string) $email, '@', true ) );
		$base = preg_replace( '/[^a-z0-9._\-]/', '', $base );
		$base = sanitize_user( $base, true );

		if ( '' === $base ) {
			$base = 'institution';
		}

		$login = $base;
		$n     = 1;

		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}

		return $login;
	}

	/**
	 * Add one row to the application's event list.
	 *
	 * The list is the answer to "what did the site already do about this one", which is what
	 * a half-done approval and every support question about a lost record both ask.
	 *
	 * @param int    $application_id The application post.
	 * @param string $event          One of the `EVENT_` constants.
	 * @param int    $actor          Who did it.
	 * @param string $note           A record ID or an account ID; never free text.
	 */
	private static function event( $application_id, $event, $actor, $note = '' ) {
		add_post_meta(
			absint( $application_id ),
			WPCPM_Institution_Application::META_EVENT,
			array(
				'event' => (string) $event,
				'at'    => time(),
				'actor' => absint( $actor ),
				'note'  => sanitize_text_field( (string) $note ),
			)
		);
	}

	/**
	 * Whether an event of this kind has already been written.
	 *
	 * How a second press learns what the first one did: the record stamp says an institution
	 * was settled on, and this says whether it was created or joined, which is the difference
	 * between the two sentences the screen prints.
	 *
	 * @param int    $application_id The application post.
	 * @param string $event          One of the `EVENT_` constants.
	 * @return bool
	 */
	private static function has_event( $application_id, $event ) {
		foreach ( (array) get_post_meta( absint( $application_id ), WPCPM_Institution_Application::META_EVENT, false ) as $row ) {
			if ( is_array( $row ) && isset( $row['event'] ) && (string) $event === (string) $row['event'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * One stored answer as a trimmed string.
	 *
	 * @param array  $stored The application's answers, keyed by column name.
	 * @param string $column Exact Airtable column name.
	 * @return string
	 */
	private static function cell( array $stored, $column ) {
		if ( ! isset( $stored[ $column ] ) || ! is_scalar( $stored[ $column ] ) ) {
			return '';
		}

		return trim( (string) $stored[ $column ] );
	}

	/**
	 * One Airtable cell as a string, whatever shape the API sent it in.
	 *
	 * @param array  $cells  The record's cells.
	 * @param string $column Exact Airtable column name.
	 * @return string
	 */
	private static function flat( array $cells, $column ) {
		return WPCPM_Airtable::flatten( isset( $cells[ $column ] ) ? $cells[ $column ] : '' );
	}

	/**
	 * The `Y-m-d` part of an Airtable date, or ''.
	 *
	 * @param string $value Raw cell value, already flattened.
	 * @return string
	 */
	private static function date_part( $value ) {
		$value = trim( (string) $value );

		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', $value, $m ) ? $m[1] : '';
	}

	/**
	 * Lower-cased, the way the duplicate search's formula lower-cases in the base.
	 *
	 * The mbstring extension is on every host this plugin has met but is not something
	 * WordPress requires, and the fallback loses nothing on an address; on a name it can
	 * differ from Airtable's own casing rules, which is why the name half of the search is a
	 * comparison the base makes rather than one this side makes.
	 *
	 * @param string $value Any string.
	 * @return string
	 */
	private static function lower( $value ) {
		$value = trim( (string) $value );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * Quote and escape a string literal for an Airtable formula.
	 *
	 * The same escaping `WPCPM_Airtable::formula_in()` does, which is private to that class;
	 * this formula is a shape it cannot express, and an institution named `L'Ecole` must be
	 * searched for rather than turned into a syntax error.
	 *
	 * @param string $value Literal value.
	 * @return string
	 */
	private static function quote( $value ) {
		return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $value ) . "'";
	}

	/**
	 * Give the lock back and answer with the reason.
	 *
	 * Every refusal from the lock onwards goes through here, so "did that exit release it"
	 * is one question about one method instead of a dozen about a dozen returns.
	 *
	 * @param int      $application_id The application post.
	 * @param WP_Error $error          Why the approval stopped.
	 * @return WP_Error
	 */
	private static function refuse( $application_id, WP_Error $error ) {
		self::unlock( $application_id );

		return $error;
	}

	/**
	 * Take the approval lock for one application.
	 *
	 * `add_option()` is the test-and-set: one INSERT that reports failure when the row is
	 * already there, so two managers pressing Approve in the same second cannot both find no
	 * record and both create one. A lock older than `LOCK_TIMEOUT` belonged to a request that
	 * died between taking and releasing it and is cleared, since the whole design of this
	 * handler is that the next press finishes what that request began.
	 *
	 * @param int $application_id The application post.
	 * @return bool Whether the lock was taken.
	 */
	private static function lock( $application_id ) {
		$key  = self::lock_name( $application_id );
		$held = get_option( $key );

		if ( false !== $held && ( time() - (int) $held ) > self::LOCK_TIMEOUT ) {
			delete_option( $key );
		}

		return (bool) add_option( $key, time(), '', false );
	}

	/**
	 * Release the approval lock.
	 *
	 * @param int $application_id The application post.
	 */
	private static function unlock( $application_id ) {
		delete_option( self::lock_name( $application_id ) );
	}

	/**
	 * The option one application's lock is held as.
	 *
	 * @param int $application_id The application post.
	 * @return string
	 */
	public static function lock_name( $application_id ) {
		return self::LOCK_PREFIX . absint( $application_id );
	}

	/**
	 * Remove every approval lock. Called on uninstall.
	 *
	 * Each one lives for a moment and is released on the way out, so this finds nothing on a
	 * site nothing has crashed on. It exists because the one it does find would be a row
	 * nothing else will ever delete: the applications it names are gone by then.
	 *
	 * @return int How many rows were removed.
	 */
	public static function delete_all() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rows are addressable only by exact name; this runs from uninstall.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LOCK_PREFIX ) . '%' ) );

		foreach ( (array) $names as $name ) {
			delete_option( (string) $name );
		}

		return count( (array) $names );
	}
}
