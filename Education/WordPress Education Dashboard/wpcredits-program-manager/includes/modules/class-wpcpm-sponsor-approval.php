<?php
/**
 * Sponsors module - approving an application into a record, an account and the rest.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one path that turns a sponsor application into a sponsor the site can act for.
 *
 * Design spec of 4 September 2026, section 9.3, in this order and never another: the Airtable
 * record, then the index row, then the account through the shipped provisioning path, then
 * the child category, the logo record, the seeded primary offer in draft, the welcome through
 * the invitation queue, and the log. Airtable first because the record ID is the account's
 * identity: every stamp and every decision the fence makes names a record. The index row next
 * because `WPCPM_Sponsor_Members::attach()` refuses a record the index does not hold and the
 * account cannot wait for the sponsors sync.
 *
 * **Nothing after a failed write.** When the create fails, or answers with no record ID, the
 * lock is released, the state is untouched, no stamp and no event are written, nobody is
 * mailed, and the reason comes back; the next press starts clean. Once the record lands it is
 * stamped on the application before the next call, and every later half answers for itself on
 * a second press: the index insert is skipped when the row exists, the provisioning path says
 * `already`, `ensure_terms()` returns the term it has, `seed()` refuses a sponsor with an offer,
 * `queue_invites()` drops an account already invited. There is no "partially approved" state
 * and no repair path; pressing Approve again is the repair.
 *
 * **Never adopts a record.** The institution approval joins an application to a record the
 * program already has; this one always creates (plan ruling 17), because the spec says
 * duplicates are flagged and never merged. A manager who agrees with the `in-base` mark
 * rejects the application and presses Create account on the Sponsors card instead.
 *
 * **Never adopts an account.** `get_user_by( 'email' )` finding an account that is not the
 * one this very application already stamped refuses the approval before Airtable is ever
 * asked, the way `WPCPM_Institution_Approval` refuses the same thing: a student's account, a
 * mentor's, an administrator's made by hand, or another sponsor's rep would otherwise be
 * handed the Sponsor Dashboard and mailed a password link for it, because
 * `WPCPM_Sponsors::provision_account()` itself adopts any account it finds at that address.
 * The one exception is the account an earlier press of this button created or attached,
 * which the application already names.
 *
 * All static, in the shape of `WPCPM_Institution_Approval`. The queue owns the button, the
 * capability and the nonce; this owns what happens after them.
 */
final class WPCPM_Sponsor_Approval {

	/** Option prefix for the per-application lock. The rest is the post ID. */
	const LOCK_PREFIX = 'wpcpm_sapp_lock_';

	/**
	 * How long a lock is honored, in seconds.
	 *
	 * Short: this holds across one API call and a handful of local writes on a screen a
	 * manager is watching, and a lock nobody released must not keep the one button that
	 * finishes a half-done approval shut for the rest of the afternoon.
	 */
	const LOCK_TIMEOUT = 60;

	/**
	 * Application events this class writes, in the words the open application prints.
	 *
	 * The first two belong here: nothing but an approval writes them. `approved` is the
	 * application's own word, and it is the one the application's history reads by, so it is
	 * taken from there rather than spelled a second time. Two spellings of one event is a
	 * decision that stops being logged the day somebody edits only one of them.
	 */
	const EVENT_RECORD_CREATED  = 'record created';
	const EVENT_ACCOUNT_CREATED = 'account created';
	const EVENT_APPROVED        = WPCPM_Sponsor_Application::EVENT_APPROVED;

	/** Audit kind, so the log lists this beside the membership row it causes. */
	const LOG_APPROVED = 'application_approved';

	/**
	 * The states an application may be approved from: everything a manager has not decided.
	 *
	 * @return string[]
	 */
	public static function approvable() {
		return WPCPM_Sponsor_Application::open_states();
	}

	/**
	 * Approve one application.
	 *
	 * The refusals before the lock return on their own and must not touch it: a lock this call
	 * never took belongs to another request. Every refusal after it goes through `refuse()`.
	 *
	 * @param int $application_id The `wpcpm_sponsor_app` post.
	 * @param int $manager_id     The program manager pressing Approve.
	 * @return array|WP_Error `array( 'record', 'user_id', 'offer_id', 'term_id' )`.
	 */
	public static function approve( $application_id, $manager_id ) {
		$application_id = absint( $application_id );
		$manager_id     = absint( $manager_id );
		$post           = get_post( $application_id );

		if ( ! $post instanceof WP_Post || WPCPM_Sponsor_Application::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'wpcpm_sapp_unknown', __( 'There is no sponsor application with that ID.', 'wpcredits-program-manager' ) );
		}

		// The actor is checked rather than trusted because it is the actor the audit row and
		// the membership record will both name.
		if ( ! user_can( $manager_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return new WP_Error( 'wpcpm_sapp_actor', __( 'Only a program manager can approve an application.', 'wpcredits-program-manager' ) );
		}

		$state = (string) get_post_meta( $application_id, WPCPM_Sponsor_Application::META_STATE, true );

		if ( ! in_array( $state, self::approvable(), true ) ) {
			return new WP_Error( 'wpcpm_sapp_state', __( 'That application has already been decided. Reopen it first.', 'wpcredits-program-manager' ) );
		}

		$stored = get_post_meta( $application_id, WPCPM_Sponsor_Application::META_FIELDS, true );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return new WP_Error( 'wpcpm_sapp_fields', __( 'That application carries no answers, so there is nothing to create a record from.', 'wpcredits-program-manager' ) );
		}

		if ( ! self::lock( $application_id ) ) {
			return new WP_Error( 'wpcpm_sapp_busy', __( 'That application is being approved right now. Give it a minute, then look at it again.', 'wpcredits-program-manager' ) );
		}

		$email = sanitize_email( self::cell( $stored, WPCPM_Sponsor_Application::COL_EMAIL ) );

		if ( ! is_email( $email ) ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_sapp_no_email', __( 'That application holds no address WordPress can make an account with.', 'wpcredits-program-manager' ) ) );
		}

		$name = self::cell( $stored, WPCPM_Sponsor_Application::COL_NAME );

		if ( '' === $name ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_sapp_name', __( 'That application holds no company name, so there is nothing to create a record from.', 'wpcredits-program-manager' ) ) );
		}

		// Found by email with no sponsor stamp is a conflict and not a match: adopting a
		// student's, a mentor's or a hand-made administrator's account here would hand a
		// stranger the Sponsor Dashboard and mail them a password link for it, since the
		// provisioning path below adopts any non-administrator account it finds at the address
		// without asking who it belongs to. The one exception is the account an earlier press of
		// this button already created or attached, which the application names. Checked before
		// Airtable is asked, so a refused application creates no record at all.
		$ours  = self::stamped_user( $application_id );
		$found = get_user_by( 'email', $email );

		if ( $found instanceof WP_User && (int) $found->ID !== $ours ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_sapp_conflict', __( 'That address already has an account on this site. That is a conflict, not a match: look at the account before approving.', 'wpcredits-program-manager' ) ) );
		}

		$logos  = WPCPM_Sponsor_Application::logos_of( $application_id );
		$record = self::stamped_record( $application_id );

		// 1. The record. The first write, and the one nothing later undoes: stamped the moment
		// it lands, before the row and long before the account, so a request that dies on the
		// next line has still recorded it and the next press creates nothing.
		if ( '' === $record ) {
			$created = self::create( $stored, $logos );

			if ( is_wp_error( $created ) ) {
				return self::refuse( $application_id, $created );
			}

			$record = $created;

			update_post_meta( $application_id, WPCPM_Sponsor_Application::META_RECORD, $record );
			self::event( $application_id, self::EVENT_RECORD_CREATED, $manager_id, $record );
		}

		// 2. The index row, so the account can be attached tonight rather than tomorrow.
		if ( ! WPCPM_Sponsors_Index::has( $record ) ) {
			WPCPM_Sponsors_Index::insert( self::row( $record, $stored ) );
		}

		// 3. The account, through the shipped provisioning path (spec 5.3, plan ruling 18).
		$account = WPCPM_Sponsors::provision_account( $record, $manager_id );

		if ( ! in_array( $account['status'], array( 'provisioned', 'provision-attached' ), true ) ) {
			$detail = '' !== (string) $account['detail'] ? (string) $account['detail'] : self::status_sentence( (string) $account['status'] );

			return self::refuse( $application_id, new WP_Error( 'wpcpm_sapp_account', $detail ) );
		}

		$user_id = (int) $account['user_id'];

		// Stamped whenever an account stands, attached or freshly made, and not only when it was
		// created: the conflict check above reads this stamp on the next press, and an account
		// only marked on creation would leave an attached one looking unclaimed and refused as
		// somebody else's the next time this application is pressed.
		if ( $user_id > 0 ) {
			update_post_meta( $application_id, WPCPM_Sponsor_Application::META_USER, $user_id );
		}

		if ( ! empty( $account['created'] ) ) {
			self::event( $application_id, self::EVENT_ACCOUNT_CREATED, $manager_id, (string) $user_id );
		}

		// 4. The child category (spec 7.2). Refused rather than skipped: the record and the
		// account already stand, and treating a missing category as optional would approve a
		// sponsor whose posts have nowhere to file. Pressing Approve again is the repair, and it
		// makes neither the record nor the account a second time.
		$term_id = class_exists( 'WPCPM_Sponsor_Posts' ) ? (int) WPCPM_Sponsor_Posts::ensure_terms( $record ) : 0;

		if ( $term_id < 1 ) {
			return self::refuse( $application_id, new WP_Error( 'wpcpm_sapp_category', __( 'The Sponsors category could not be made.', 'wpcredits-program-manager' ) ) );
		}

		// 5. The logo record (spec 8.1): the attachments the form stored move to the account.
		self::record_logos( $record, $logos, $user_id );

		// 6. The seeded primary offer, in draft (spec 6.1). `false` for a sponsor with one.
		$offer_id = 0;

		if ( class_exists( 'WPCPM_Sponsor_Offers' ) ) {
			$seeded   = WPCPM_Sponsor_Offers::seed( $record );
			$offer_id = is_int( $seeded ) ? $seeded : 0;
		}

		// 7. The welcome, through the invitation queue, which drops an account already invited.
		WPCPM_Mail::queue_invites( array( $user_id ) );

		// 8. The decision and the log.
		update_post_meta( $application_id, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::STATE_APPROVED );
		self::event( $application_id, self::EVENT_APPROVED, $manager_id, $record );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_APPROVED,
				'sponsor'  => $record,
				'subject'  => (string) $application_id,
				'actor'    => $manager_id,
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'  => __( 'A sponsor application was approved: the record, the account, the category, the logo record and the first offer were made together.', 'wpcredits-program-manager' ),
				'data'     => array(
					'application' => $application_id,
					'user'        => $user_id,
					'offer'       => $offer_id,
					'term'        => $term_id,
				),
			)
		);

		self::unlock( $application_id );

		return array(
			'record'   => $record,
			'user_id'  => $user_id,
			'offer_id' => $offer_id,
			'term_id'  => $term_id,
		);
	}

	/**
	 * The cells one approval writes, in the spec's order (plan ruling 16).
	 *
	 * Every name from the sync's map, never a literal; the two checkboxes as PHP booleans,
	 * because the client sends nothing that would coerce a string into a tick; `Logo` only when
	 * there is an attachment, color first.
	 *
	 * @param array $stored The application's six answers, keyed by column name.
	 * @param array $logos  The attachment IDs, keyed `colour` and `white`.
	 * @return array
	 */
	public static function payload( array $stored, array $logos ) {
		$fields = WPCPM_Sponsors_Sync::fields();

		$cells = array(
			$fields['name']           => self::cell( $stored, WPCPM_Sponsor_Application::COL_NAME ),
			$fields['website']        => self::cell( $stored, WPCPM_Sponsor_Application::COL_WEBSITE ),
			$fields['contact_person'] => self::cell( $stored, WPCPM_Sponsor_Application::COL_PERSON ),
			$fields['contact_email']  => self::cell( $stored, WPCPM_Sponsor_Application::COL_EMAIL ),
			$fields['option']         => self::cell( $stored, WPCPM_Sponsor_Application::COL_OPTION ),
			$fields['anything']       => self::cell( $stored, WPCPM_Sponsor_Application::COL_ANYTHING ),
			$fields['consent']        => true,
			$fields['status']         => WPCPM_Sponsors_Index::STATUS_APPROVED,
		);

		$logo = self::logo_cells( $logos );

		if ( ! empty( $logo ) ) {
			$cells[ $fields['logo'] ] = $logo;
		}

		$cells[ $fields['dashboard_account'] ] = true;

		return $cells;
	}

	/**
	 * The Airtable record this application already has, or ''.
	 *
	 * @param int $application_id The application post.
	 * @return string
	 */
	public static function stamped_record( $application_id ) {
		$record = trim( (string) get_post_meta( absint( $application_id ), WPCPM_Sponsor_Application::META_RECORD, true ) );

		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}

	/**
	 * The account this application already created, or 0.
	 *
	 * @param int $application_id The application post.
	 * @return int
	 */
	public static function stamped_user( $application_id ) {
		$user_id = absint( get_post_meta( absint( $application_id ), WPCPM_Sponsor_Application::META_USER, true ) );

		return ( $user_id && get_userdata( $user_id ) ) ? $user_id : 0;
	}

	/**
	 * Whether an approval was begun and left unfinished: the record landed and nothing has
	 * been decided since.
	 *
	 * @param int $application_id The application post.
	 * @return bool
	 */
	public static function is_half_done( $application_id ) {
		$application_id = absint( $application_id );

		if ( '' === self::stamped_record( $application_id ) ) {
			return false;
		}

		return in_array( (string) get_post_meta( $application_id, WPCPM_Sponsor_Application::META_STATE, true ), self::approvable(), true );
	}

	/**
	 * Create the sponsor record.
	 *
	 * @param array $stored The application's answers, keyed by column name.
	 * @param array $logos  The attachment IDs, keyed `colour` and `white`.
	 * @return string|WP_Error The new record ID.
	 */
	private static function create( array $stored, array $logos ) {
		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$created  = $airtable->create_records(
			(string) ( isset( $settings['sponsors_table'] ) ? $settings['sponsors_table'] : '' ),
			array( array( 'fields' => self::payload( $stored, $logos ) ) )
		);

		if ( is_wp_error( $created ) ) {
			return new WP_Error( 'wpcpm_sapp_airtable', $created->get_error_message() );
		}

		// An empty answer is a refusal too: "nothing was created" must not read as success on
		// the one path where success opens an account.
		$record = ( ! empty( $created ) && is_array( $created ) ) ? trim( (string) $created[0] ) : '';

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return new WP_Error( 'wpcpm_sapp_airtable', __( 'Airtable did not answer with a record ID, so nothing was created here either.', 'wpcredits-program-manager' ) );
		}

		return $record;
	}

	/**
	 * The index row for the record this class just created: the application's own answers,
	 * which is exactly what was written to the base a moment ago.
	 *
	 * @param string $record Sponsors record ID.
	 * @param array  $stored The application's answers, keyed by column name.
	 * @return array
	 */
	private static function row( $record, array $stored ) {
		return array_merge(
			WPCPM_Sponsors_Index::empty_row(),
			array(
				'record_id'         => $record,
				'name'              => self::cell( $stored, WPCPM_Sponsor_Application::COL_NAME ),
				'website'           => self::cell( $stored, WPCPM_Sponsor_Application::COL_WEBSITE ),
				'status'            => WPCPM_Sponsors_Index::STATUS_APPROVED,
				'option'            => self::cell( $stored, WPCPM_Sponsor_Application::COL_OPTION ),
				'anything'          => self::cell( $stored, WPCPM_Sponsor_Application::COL_ANYTHING ),
				'contact_person'    => self::cell( $stored, WPCPM_Sponsor_Application::COL_PERSON ),
				// Lowercased, the way the sync stores the column.
				'contact_email'     => strtolower( self::cell( $stored, WPCPM_Sponsor_Application::COL_EMAIL ) ),
				// The application could not have been stored without it: consent is a
				// precondition of the form, not one of its answers.
				'consent'           => true,
				'created'           => wp_date( 'Y-m-d' ),
				// True in the payload, so true here: no second PATCH to say so.
				'dashboard_account' => true,
			)
		);
	}

	/**
	 * The attachments' public URLs as Airtable's attachment field takes them, color first.
	 *
	 * @param array $logos The attachment IDs, keyed `colour` and `white`.
	 * @return array[] Each `url` and `filename`; empty when there is no attachment.
	 */
	private static function logo_cells( array $logos ) {
		$cells = array();

		foreach ( array( 'colour', 'white' ) as $half ) {
			$id = isset( $logos[ $half ] ) ? (int) $logos[ $half ] : 0;

			if ( $id < 1 ) {
				continue;
			}

			$url = wp_get_attachment_url( $id );

			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$cells[] = array(
				'url'      => $url,
				'filename' => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
			);
		}

		return $cells;
	}

	/**
	 * Record the two attachments as the sponsor's logo, owned by the site, and by the account.
	 *
	 * `source => site` is what the sponsors sync reads before it copies Airtable's attachment,
	 * and the base now holds the same picture anyway. The attachments were stored with author 0
	 * by the form; they belong to the account from here on.
	 *
	 * **And they are published here** (S5 review). The application form stores a stranger's
	 * files as `private` attachments, out of the unauthenticated media listing, until somebody
	 * has looked at them; approval is that moment, so the status goes back to `inherit` in the
	 * same write that moves the author. A press that died before this one leaves them private,
	 * and pressing Approve again publishes them, as it finishes everything else.
	 *
	 * @param string $record  Sponsors record ID.
	 * @param array  $logos   The attachment IDs, keyed `colour` and `white`.
	 * @param int    $user_id The account.
	 */
	private static function record_logos( $record, array $logos, $user_id ) {
		$colour = isset( $logos['colour'] ) ? (int) $logos['colour'] : 0;
		$white  = isset( $logos['white'] ) ? (int) $logos['white'] : 0;

		if ( $colour < 1 && $white < 1 ) {
			return;
		}

		foreach ( array( $colour, $white ) as $attachment_id ) {
			if ( $attachment_id > 0 ) {
				wp_update_post(
					array(
						'ID'          => $attachment_id,
						'post_author' => (int) $user_id,
						'post_status' => 'inherit',
					)
				);
			}
		}

		WPCPM_Sponsors_Index::write_logo_record(
			$record,
			array(
				'colour'      => $colour,
				'white'       => $white,
				'source'      => 'site',
				'airtable_id' => '',
			)
		);
	}

	/**
	 * The sentence behind a provisioning status, for a refusal that carried no detail.
	 *
	 * @param string $status A key of `WPCPM_Sponsors::messages()`.
	 * @return string
	 */
	private static function status_sentence( $status ) {
		$messages = WPCPM_Sponsors::messages();

		return isset( $messages[ $status ][1] ) ? (string) $messages[ $status ][1] : __( 'The account could not be made.', 'wpcredits-program-manager' );
	}

	/**
	 * Add one row to the application's event list.
	 *
	 * @param int    $application_id The application post.
	 * @param string $event          One of the `EVENT_` constants.
	 * @param int    $actor          Who did it.
	 * @param string $note           A record ID or an account ID; never free text.
	 */
	private static function event( $application_id, $event, $actor, $note = '' ) {
		// Through the application's own writer, so an approval stamps the decision time the
		// Recently decided list sorts by (clean-up 1.98.1): a row written here directly would
		// vanish from that list the day after the backfill.
		WPCPM_Sponsor_Application::add_event( absint( $application_id ), (string) $event, absint( $actor ), (string) $note );
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
	 * Give the lock back and answer with the reason.
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
	 * died between taking and releasing it and is cleared.
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
