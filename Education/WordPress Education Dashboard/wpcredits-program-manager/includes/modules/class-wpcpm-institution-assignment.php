<?php
/**
 * The Administrator an institution is assigned to, and the email that tells them.
 *
 * An institution's assigned Administrator is the one who follows it up. It is kept on the
 * institution's record on the site (`WPCPM_Institution_Record`, the answer `assigned`, a user ID), so it
 * outlives the application that gave it. An application holds its own copy (`META_ASSIGNED` on
 * `WPCPM_Institution_Application`) from the day it was sent, and approval copies it across.
 *
 * - **Stored is not assigned.** `stored_of()` answers with the stored ID, whoever the account is now;
 *   `assignee_of()` answers with it only while that account still holds `WPCPM_Roles::CAP_MANAGE`, and
 *   with 0 once it does not, so nothing routes to an account that is no longer an Administrator. The
 *   screens use `label()`, which says so.
 * - **One change, one row, one email.** `assign()` writes the audit row (`LOG_CHANGED`) and tells the new
 *   Administrator, unless it is the same Administrator as before (nothing changes), the one who made the
 *   change (they know), or the caller asked for no email (the import, the application's own message).
 * - **Only an Administrator can be assigned**, and a record that could not be kept is an error and not a
 *   quiet false.
 *
 * All static, no state.
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The assigned Administrator of an institution.
 */
class WPCPM_Institution_Assignment {

	/** The record's answer holding the assigned Administrator's user ID. */
	const RECORD_KEY = 'assigned';

	/** The audit kind of a change of Administrator. */
	const LOG_CHANGED = 'assignment_changed';

	/** The email that tells an Administrator an institution is assigned to them. */
	const MAIL_ASSIGNED = 'institution-assigned';

	/** The details card's Assigned Administrator form: the admin-post action, and its nonce's name before the record. */
	const ACTION_SAVE = 'wpcpm_institution_assign';

	/** The posted Administrator: a user ID, or 0 for nobody. */
	const FIELD = 'wpcpm_assigned';

	/** The id of the outcome line, where a save lands: in view, and given the focus by the script. */
	const OUTCOME_ID = 'wpcpm-details-assignment-outcome';

	/**
	 * The stored Administrator, whoever that account is now.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return int User ID, 0 for none.
	 */
	public static function stored_of( $record_id ) {
		$answers = WPCPM_Institution_Record::get( $record_id );

		return self::user_id( isset( $answers[ self::RECORD_KEY ] ) ? $answers[ self::RECORD_KEY ] : 0 );
	}

	/**
	 * Whether the record holds an assignment at all, **Nobody** chosen on the details card included.
	 *
	 * `stored_of()` reads 0 both for a school nobody has assigned yet and for one an Administrator set to
	 * Nobody; this tells the two apart, so a caller that assigns on the site's behalf (the institutions sync)
	 * never undoes somebody's choice of Nobody.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return bool
	 */
	public static function has_choice( $record_id ) {
		return array_key_exists( self::RECORD_KEY, WPCPM_Institution_Record::get( $record_id ) );
	}

	/**
	 * The assigned Administrator, while that account holds the manage capability.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return int User ID, 0 for none and for an account that is no Administrator now.
	 */
	public static function assignee_of( $record_id ) {
		$user_id = self::stored_of( $record_id );

		return self::is_administrator( $user_id ) ? $user_id : 0;
	}

	/**
	 * The assignee as a contact: who, how to write and where to book a call.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return array{user_id: int, name: string, email: string, calendly: string}|null Null for none.
	 */
	public static function contact_of( $record_id ) {
		return self::contact_for( self::assignee_of( $record_id ) );
	}

	/**
	 * An Administrator as a contact.
	 *
	 * @param int $user_id The account.
	 * @return array{user_id: int, name: string, email: string, calendly: string}|null Null for 0, for
	 *                                                                                  an account that is gone and for one that is no Administrator now.
	 */
	public static function contact_for( $user_id ) {
		$user_id = (int) $user_id;
		$user    = self::is_administrator( $user_id ) ? get_userdata( $user_id ) : false;

		if ( ! $user instanceof WP_User ) {
			return null;
		}

		return array(
			'user_id'  => $user_id,
			'name'     => self::name_of( $user ),
			'email'    => (string) $user->user_email,
			'calendly' => WPCPM_Manager_Booking::url_for( $user_id ),
		);
	}

	/**
	 * What to print for an assigned Administrator.
	 *
	 * @param int $user_id The stored ID.
	 * @return string Their name, "<name> (not an Administrator now)" once the capability is gone, and
	 *                '' for 0.
	 */
	public static function label( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id < 1 ) {
			return '';
		}

		$user = get_userdata( $user_id );
		$name = $user instanceof WP_User ? self::name_of( $user ) : (string) $user_id;

		if ( self::is_administrator( $user_id ) ) {
			return $name;
		}

		return sprintf(
			/* translators: %s: a person's name. */
			__( '%s (not an Administrator now)', 'wpcredits-program-manager' ),
			$name
		);
	}

	/**
	 * Every Administrator an institution can be assigned to.
	 *
	 * @return array<int, string> User ID => display name, A to Z.
	 */
	public static function choices() {
		return WPCPM_Settings_Choices::manager_accounts();
	}

	/**
	 * Assign an institution to an Administrator, or to nobody.
	 *
	 * @param string $record_id The institution's record ID.
	 * @param int    $user_id   The Administrator, 0 for nobody.
	 * @param int    $actor     Who is making the change, 0 for the site.
	 * @param bool   $notify    Whether to tell the new Administrator. The import and the approval, which
	 *                          follows an application whose own message already told them, pass false.
	 * @param bool   $emailed   Set to whether the email was handed off, so a caller says "emailed" only
	 *                          when it was (`handle_save()`); false whenever none was asked for or sent.
	 * @return bool|WP_Error True when it changed, false when it was the same Administrator already, or
	 *                       the refusal: `wpcpm_assign_record` for an ID that is no record,
	 *                       `wpcpm_assign_not_admin` for an account without the manage capability and
	 *                       `wpcpm_assign_not_kept` for a record the site could not keep.
	 */
	public static function assign( $record_id, $user_id, $actor, $notify = true, &$emailed = null ) {
		$record_id = trim( (string) $record_id );
		$user_id   = (int) $user_id;
		$actor     = (int) $actor;
		$emailed   = false;

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return new WP_Error( 'wpcpm_assign_record', __( 'That is not an institution.', 'wpcredits-program-manager' ) );
		}

		if ( $user_id < 0 || ( $user_id > 0 && ! self::is_administrator( $user_id ) ) ) {
			return new WP_Error( 'wpcpm_assign_not_admin', __( 'Only an Administrator can be assigned an institution.', 'wpcredits-program-manager' ) );
		}

		$from = self::stored_of( $record_id );

		// The same Administrator is no change, but Nobody on a school with no assignment yet is:
		// the 0 is the choice, and without it the seed and the sync would assign the school.
		if ( $from === $user_id && self::has_choice( $record_id ) ) {
			return false;
		}

		if ( ! WPCPM_Institution_Record::set( $record_id, array( self::RECORD_KEY => $user_id ) ) ) {
			return new WP_Error( 'wpcpm_assign_not_kept', __( 'The assignment could not be kept. Try again.', 'wpcredits-program-manager' ) );
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_CHANGED,
				'institution' => $record_id,
				'subject'     => $record_id,
				'actor'       => $actor,
				'ground'      => $actor > 0 ? WPCPM_Institution_Audit::GROUND_MANAGER : WPCPM_Institution_Audit::GROUND_SYSTEM,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => $user_id > 0
					? sprintf(
						/* translators: %s: an Administrator's name. */
						__( '%s is now the assigned Administrator.', 'wpcredits-program-manager' ),
						self::label( $user_id )
					)
					: __( 'Nobody is assigned now.', 'wpcredits-program-manager' ),
				'data'        => array(
					'from' => $from,
					'to'   => $user_id,
				),
			)
		);

		if ( $notify && $user_id > 0 && $user_id !== $actor ) {
			$row = WPCPM_Institutions_Index::row( $record_id );
			$row = is_array( $row ) ? $row : array();

			$emailed = self::notify(
				$user_id,
				isset( $row['name'] ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record_id,
				isset( $row['country_name'] ) ? trim( (string) $row['country_name'] ) : '',
				self::page_url( $record_id )
			);
		}

		return true;
	}

	/**
	 * Tell an Administrator an institution is assigned to them.
	 *
	 * @param int    $user_id          The Administrator.
	 * @param string $institution_name The institution's name.
	 * @param string $country_name     Its country's name, or ''.
	 * @param string $link             Where to open it.
	 * @return bool Whether the email was handed off.
	 */
	public static function notify( $user_id, $institution_name, $country_name, $link ) {
		if ( (int) $user_id < 1 ) {
			return false;
		}

		$site    = WPCPM_Mail::site_name();
		$country = trim( (string) $country_name );
		// An institution with no country on record reads "X is now assigned to you", not "X, in , is...".
		$place = '' !== $country
			? sprintf(
				/* translators: %s: a country's name. The commas are part of the phrase: "Example University, in Poland, is now assigned to you." */
				__( ', in %s,', 'wpcredits-program-manager' ),
				$country
			)
			: '';
		$build = static function () use ( $site, $institution_name, $place, $link ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_ASSIGNED,
				array(
					'site_name'        => $site,
					'institution_name' => $institution_name,
					'country_phrase'   => $place,
					'institution_link' => $link,
				)
			);
		};

		return (bool) WPCPM_Mail::send( (int) $user_id, self::MAIL_ASSIGNED, $build );
	}

	/**
	 * Where an Administrator opens an institution: its page on the Institution Dashboard, at the details
	 * card on the Your institution view (`card_url()`), or the front page while the page has no address.
	 * Not escaped: the assignment mail's link.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return string An address.
	 */
	public static function page_url( $record_id ) {
		$url = WPCPM_Institutions_Dashboard::card_url( 'details', trim( (string) $record_id ), WPCPM_Institution_Details_Card::ANCHOR );

		if ( '' !== $url ) {
			return $url;
		}

		$url = add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, trim( (string) $record_id ), home_url( '/' ) );

		return $url . '#' . WPCPM_Institution_Details_Card::ANCHOR;
	}

	/**
	 * The Administrator an application was given to when it was sent.
	 *
	 * @param int $post_id The application.
	 * @return int User ID, 0 for none.
	 */
	public static function for_application( $post_id ) {
		return self::user_id( get_post_meta( (int) $post_id, WPCPM_Institution_Application::META_ASSIGNED, true ) );
	}

	/**
	 * Who an application is for, by name, where a screen shows its contact "for information".
	 *
	 * The Administrator it was given to when it was sent. An application filed before the areas existed,
	 * or while none were set, has nobody and holds the country contact as it was snapshotted
	 * (`META_MANAGER`), so an older row does not show nobody: that contact's name, or their address when the
	 * snapshot holds only a record ID in the name.
	 *
	 * @param int $post_id The application.
	 * @return string The name, or ''.
	 */
	public static function application_label( $post_id ) {
		$stored = self::for_application( $post_id );

		if ( $stored > 0 ) {
			return self::label( $stored );
		}

		$snapshot = get_post_meta( (int) $post_id, WPCPM_Institution_Application::META_MANAGER, true );

		if ( ! is_array( $snapshot ) ) {
			return '';
		}

		$name = isset( $snapshot['manager'] ) ? trim( (string) $snapshot['manager'] ) : '';

		if ( '' !== $name && ! WPCPM_Mentors_Sync::is_record_id( $name ) ) {
			return $name;
		}

		return isset( $snapshot['email'] ) ? trim( (string) $snapshot['email'] ) : '';
	}

	/**
	 * Draw the Assigned Administrator section of the details card.
	 *
	 * Only an Administrator reads it: who follows an institution up is the program's business, not the
	 * school's. It names the stored Administrator (or says nobody is assigned) and holds one closed fold
	 * with the field to change it.
	 *
	 * @param string     $record_id The institution's record ID.
	 * @param bool       $checker   Whether the reader is an Administrator.
	 * @param array|null $message   The outcome of the last post as type and text, or null.
	 */
	public static function render( $record_id, $checker, $message = null ) {
		$record = trim( (string) $record_id );

		if ( ! $checker || ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		// Drawn with the shared parts, as the card that draws this section is (Stage B, Task 19).
		self::render_parts( $record, $message );
	}

	/**
	 * Who the field offers: Nobody first, then every Administrator; one that lost the capability is not offered again.
	 *
	 * @return array<int, string>
	 */
	private static function options() {
		return array( 0 => __( 'Nobody', 'wpcredits-program-manager' ) ) + self::choices();
	}

	/**
	 * The option chosen in the field: the stored Administrator while they are offered, Nobody otherwise.
	 *
	 * @param int   $stored  The stored ID.
	 * @param array $options What `options()` offers.
	 * @return int
	 */
	private static function chosen( $stored, array $options ) {
		return isset( $options[ $stored ] ) ? (int) $stored : 0;
	}

	/**
	 * The words read while nobody is assigned.
	 *
	 * @return string
	 */
	private static function nobody_yet() {
		return __( 'Nobody yet', 'wpcredits-program-manager' );
	}

	/**
	 * The field's placeholder.
	 *
	 * @return string
	 */
	private static function find_words() {
		return __( 'Type to find an Administrator', 'wpcredits-program-manager' );
	}

	/**
	 * What the field's list says when no name matches.
	 *
	 * @return string
	 */
	private static function none_words() {
		return __( 'No Administrator has that name.', 'wpcredits-program-manager' );
	}

	/**
	 * The hidden fields the form posts after its nonce: its action and the institution.
	 *
	 * @param string $record The institution's record ID.
	 * @return array<string, string>
	 */
	private static function form_fields( $record ) {
		return array(
			'action' => self::ACTION_SAVE,
			'record' => (string) $record,
		);
	}

	/**
	 * The Assigned Administrator section on the shared parts (design 6.4), for an Administrator alone: an in-card heading,
	 * the outcome of the last post under it, where its way back lands (its id on a wrapper, since the shell's message
	 * takes none), a key and value row naming the stored Administrator (its words as they are, the sheet breaking a long one, or "Nobody yet" of the empty
	 * kind), and the inner fold Change who follows it up: the one combobox and one primary.
	 *
	 * @param string     $record  The institution's record ID.
	 * @param array|null $message The outcome of the last post as type and text, or null.
	 */
	private static function render_parts( $record, $message ) {
		$heading = 'wpcpm-details-assignment-heading';

		echo WPCPM_UI::heading( __( 'Assigned Administrator', 'wpcredits-program-manager' ), 'h4', $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.

		// The outcome under the heading, where its way back lands; a refusal in the open fold, directly over the form it
		// refused, its way back landing there with it (Gate B round 2, F2).
		$outcome = is_array( $message ) ? sprintf( '<div id="%1$s">%2$s</div>', esc_attr( self::OUTCOME_ID ), WPCPM_Dashboard_Card::message( $message[0], $message[1], 'wpcpm-details__message' ) ) : '';
		$refusal = is_array( $message ) && 'error' === $message[0];

		if ( ! $refusal ) {
			echo $outcome; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Dashboard_Card::message() escapes what it prints.
		}

		$stored = self::stored_of( $record );
		$name   = self::label( $stored );
		$label  = array(
			'key'    => 'label',
			'label'  => '',
			'kind'   => 'text',
			'header' => true,
		);
		$text   = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'text',
		);

		$table = WPCPM_UI::table_open(
			array( $label, $text ),
			array(
				'keyvalue'   => true,
				'labelledby' => $heading,
			)
		)
			. WPCPM_UI::row_open()
			. WPCPM_UI::cell( $label, esc_html__( 'Administrator', 'wpcredits-program-manager' ) )
			. WPCPM_UI::cell( $text, esc_html( '' !== $name ? $name : self::nobody_yet() ), '' !== $name ? '' : 'empty' )
			. WPCPM_UI::row_close() . WPCPM_UI::table_close();

		echo $table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.

		$options = self::options();
		$fold    = WPCPM_UI::inner_open(
			array(
				'title' => __( 'Change who follows it up', 'wpcredits-program-manager' ),
				'open'  => is_array( $message ),
				'id'    => 'wpcpm-details-assignment',
			)
		);

		echo $fold . ( $refusal ? $outcome : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in WPCPM_Dashboard_Card::message().
		printf(
			'<form method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		);
		echo WPCPM_UI::nonce( self::ACTION_SAVE . '_' . $record ) . WPCPM_Institution_Details_Card::hidden_html( self::form_fields( $record ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part and in hidden_html().
		$field  = WPCPM_UI::field(
			array(
				'type'    => 'combo',
				'id'      => 'wpcpm-details-assigned',
				'name'    => self::FIELD,
				'label'   => __( 'Administrator', 'wpcredits-program-manager' ),
				'value'   => self::chosen( $stored, $options ),
				'options' => $options,
				'find'    => self::find_words(),
				'none'    => self::none_words(),
			)
		);
		$button = WPCPM_UI::button(
			array(
				'label' => __( 'Save', 'wpcredits-program-manager' ),
				'side'  => 'front',
			)
		);

		echo $field . WPCPM_UI::actions( array( $button ) ) . '</form>' . WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.
	}

	/**
	 * Save the Administrator an Administrator posted from the details card.
	 *
	 * The order is the tracks form's: the nonce keyed to the posted record, the record's shape, the policy
	 * against the institution the form names, then the manager ground (which the policy's details action
	 * does not require, since members pass it), and only then `assign()`, under the school's lock. A value
	 * that is no whole number is never read as nobody: it is refused as an account that is no
	 * Administrator. Ends the request with the outcome flashed on the card's channel.
	 */
	public static function handle_save() {
		$record = WPCPM_Request::posted_text( 'record' );

		check_admin_referer( self::ACTION_SAVE . '_' . $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EDIT_DETAILS, WPCPM_Institution_Policy::subject_institution( $record ) );

		if ( empty( $decision['allowed'] ) || WPCPM_Institution_Policy::GROUND_MANAGER !== $decision['ground'] ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$posted  = WPCPM_Request::posted_text( self::FIELD );
		$user_id = ctype_digit( $posted ) ? (int) $posted : -1;
		$actor   = get_current_user_id();

		// The record's whole answers are read and written under the school's lock, as the other card saves do.
		$lock  = WPCPM_Institution_Request::school_lock_name( $record );
		$token = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

		if ( '' === $token ) {
			WPCPM_Institution_Details_Card::finish( $record, 'saving-busy', array( 'section' => 'assignment' ), self::OUTCOME_ID );
		}

		$emailed = false;

		try {
			WPCPM_Institution_Record::forget_cached( $record );

			$saved = self::assign( $record, $user_id, $actor, true, $emailed );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		if ( is_wp_error( $saved ) ) {
			WPCPM_Institution_Details_Card::finish(
				$record,
				'said',
				array(
					'text'    => $saved->get_error_message(),
					'section' => 'assignment',
				),
				self::OUTCOME_ID
			);
		}

		// The email goes to the new Administrator unless they made the change themselves, or nobody is assigned;
		// the notice says they were emailed only when the email was handed off.
		$status = $saved ? ( $emailed ? 'assignment-emailed' : 'assignment-saved' ) : 'nothing';

		WPCPM_Institution_Details_Card::finish( $record, $status, array( 'section' => 'assignment' ), self::OUTCOME_ID );
	}

	/**
	 * A stored value as a user ID.
	 *
	 * @param mixed $value Whatever was stored.
	 * @return int 0 for anything that is not a whole number above 0.
	 */
	private static function user_id( $value ) {
		return ( is_scalar( $value ) && ctype_digit( (string) $value ) ) ? (int) $value : 0;
	}

	/**
	 * Whether an account is an Administrator.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function is_administrator( $user_id ) {
		return $user_id > 0 && user_can( $user_id, WPCPM_Roles::CAP_MANAGE );
	}

	/**
	 * An account's name: its display name, its login when it has none.
	 *
	 * @param WP_User $user The account.
	 * @return string
	 */
	private static function name_of( WP_User $user ) {
		$name = trim( (string) $user->display_name );

		return '' !== $name ? $name : (string) $user->user_login;
	}
}
