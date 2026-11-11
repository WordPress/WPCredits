<?php
/**
 * The tracks an institution offers.
 *
 * An Administrator lists, per institution, the tracks it offers; the registration form asks a student
 * for a track only when the institution offers two or more the site runs. The list is kept on the
 * site, in the institution's record (`WPCPM_Institution_Record`), by each track's Airtable status, the
 * key that stays when a track's name is edited. It is never written to Airtable.
 *
 * - **What the site runs.** `WPCPM_Program::labels()` (status => name, in the Track Builder's order).
 *   `runnable()` reads the stored list through it, so a track unpublished since is left out.
 * - **A track no longer run is kept.** `save()` keeps it in the stored list, so it comes back when the
 *   track is published again, and drops only a status the site does not run and the list never held.
 * - **A change is audited.** One history row, as an Administrator's correction of any other detail
 *   (`WPCPM_Institution_Details::write()`); an unchanged list writes nothing.
 *
 * This class decides nothing about who may change the list: the caller asks `WPCPM_Institution_Policy`.
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The list of tracks an institution offers, and its audit.
 */
class WPCPM_Institution_Tracks {

	/**
	 * The details card's Tracks offered form: the admin-post action, and its nonce's name before the record.
	 */
	const ACTION_SAVE = 'wpcpm_institution_tracks_save';

	/**
	 * The posted list of statuses the form's checkboxes send.
	 */
	const FIELD = 'wpcpm_tracks_offered';

	/**
	 * The id of the outcome line, where a save lands: the line is in view and keeps the focus the script gives it.
	 */
	const OUTCOME_ID = 'wpcpm-details-tracks-outcome';

	/**
	 * The value of a posted status: one line of printable characters.
	 */
	const POSTED_VALUE = '/\A[^\x00-\x1F\x7F]{1,100}\z/u';

	/**
	 * The key in the institution's record: a list of Airtable statuses.
	 */
	const RECORD_KEY = 'tracks_offered';

	/**
	 * The audit row's kind.
	 */
	const LOG_SAVED = 'tracks_saved';

	/**
	 * The posted value of "Not sure yet".
	 */
	const NOT_SURE = 'not-sure';

	/**
	 * The text written to Airtable for "Not sure yet", untranslated.
	 */
	const NOT_SURE_TEXT = 'Not sure yet';

	/**
	 * The statuses kept for an institution, in the order kept.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return string[] The statuses; an empty list for none, and for an ID that is not a record ID.
	 */
	public static function stored( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$answers = WPCPM_Institution_Record::get( $record_id );
		$held    = isset( $answers[ self::RECORD_KEY ] ) && is_array( $answers[ self::RECORD_KEY ] ) ? $answers[ self::RECORD_KEY ] : array();
		$out     = array();

		foreach ( $held as $status ) {
			if ( is_string( $status ) && '' !== $status && ! in_array( $status, $out, true ) ) {
				$out[] = $status;
			}
		}

		return $out;
	}

	/**
	 * The stored tracks the site runs today, by name.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return array<string, string> Status => name, in `WPCPM_Program::labels()` order.
	 */
	public static function runnable( $record_id ) {
		$stored = self::stored( $record_id );
		$out    = array();

		foreach ( WPCPM_Program::labels() as $status => $name ) {
			if ( in_array( (string) $status, $stored, true ) ) {
				$out[ $status ] = $name;
			}
		}

		return $out;
	}

	/**
	 * The tracks the site runs for each of many institutions, from one read of their records.
	 *
	 * @param string[] $record_ids The institutions' Airtable record IDs.
	 * @return array<string, array<string, string>> Record ID => `runnable()`'s list, for each
	 *                                               institution with at least one; in `labels()` order.
	 */
	public static function runnable_among( array $record_ids ) {
		$labels = WPCPM_Program::labels();
		$out    = array();

		foreach ( WPCPM_Institution_Record::answers_for( $record_ids ) as $record_id => $answers ) {
			$held = isset( $answers[ self::RECORD_KEY ] ) && is_array( $answers[ self::RECORD_KEY ] ) ? $answers[ self::RECORD_KEY ] : array();
			$list = array();

			foreach ( $labels as $status => $name ) {
				if ( in_array( (string) $status, $held, true ) ) {
					$list[ $status ] = $name;
				}
			}

			if ( ! empty( $list ) ) {
				$out[ $record_id ] = $list;
			}
		}

		return $out;
	}

	/**
	 * Whether the registration form asks this institution's students for a track.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return bool True when two or more of its tracks run.
	 */
	public static function asks( $record_id ) {
		return count( self::runnable( $record_id ) ) >= 2;
	}

	/**
	 * The text to write for a posted track.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param mixed  $posted    The posted value: a status, or `NOT_SURE`.
	 * @return string|null The track's name for a status of the institution's `runnable()`,
	 *                     `NOT_SURE_TEXT` for `NOT_SURE`, and null for anything else.
	 */
	public static function answer_text( $record_id, $posted ) {
		if ( ! is_string( $posted ) || '' === $posted ) {
			return null;
		}

		if ( self::NOT_SURE === $posted ) {
			return self::NOT_SURE_TEXT;
		}

		$runnable = self::runnable( $record_id );

		return isset( $runnable[ $posted ] ) ? (string) $runnable[ $posted ] : null;
	}

	/**
	 * Keep a new list of tracks for an institution, and say so in its history.
	 *
	 * The list kept is the posted statuses the site runs, in `WPCPM_Program::labels()` order, followed by
	 * the stored statuses the site does not run, in the order they were stored: a track unpublished since
	 * is not lost by a save.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $statuses  The posted statuses.
	 * @param int    $actor     The user who made the change.
	 * @return bool|WP_Error True when the list changed, false when nothing changed (or the ID is not a
	 *                       record ID), an error when the record could not be kept.
	 */
	public static function save( $record_id, array $statuses, $actor ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return false;
		}

		$labels = WPCPM_Program::labels();
		$posted = array_map( 'strval', $statuses );
		$new    = array();

		foreach ( $labels as $status => $name ) {
			if ( in_array( (string) $status, $posted, true ) ) {
				$new[] = (string) $status;
			}
		}

		$before = self::stored( $record_id );

		foreach ( $before as $status ) {
			if ( ! isset( $labels[ $status ] ) ) {
				$new[] = $status;
			}
		}

		if ( $new === $before ) {
			return false;
		}

		$kept = WPCPM_Institution_Record::set( $record_id, array( self::RECORD_KEY => $new ) );

		if ( ! $kept ) {
			return new WP_Error( 'wpcpm_tracks_record', __( 'The tracks could not be kept on the site, so nothing was saved. Try again in a while.', 'wpcredits-program-manager' ) );
		}

		$names = array();

		foreach ( $new as $status ) {
			$names[] = isset( $labels[ $status ] ) ? (string) $labels[ $status ] : $status;
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_SAVED,
				'institution' => $record_id,
				'subject'     => $record_id,
				'actor'       => (int) $actor,
				'ground'      => WPCPM_Institution_Policy::GROUND_MANAGER,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => empty( $names )
					? __( 'Tracks offered were cleared.', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %s: the names of the tracks the institution offers, comma-separated. */
						__( 'Tracks offered were set: %s.', 'wpcredits-program-manager' ),
						implode( ', ', $names )
					),
				'data'        => array( 'statuses' => $new ),
			)
		);

		return true;
	}

	/**
	 * Draw the Tracks offered section of the details card.
	 *
	 * Everybody who reads the card reads the names of the tracks the institution offers that the site
	 * runs. Administrators (the card's manager ground) are also drawn the form: one checkbox for each
	 * track the site runs. A member is told who sets them and where to write.
	 *
	 * @param string     $record_id The institution's Airtable record ID.
	 * @param bool       $checker   Whether the reader is an Administrator, who sets the list.
	 * @param array|null $message   The outcome of the last post as type and text, or null.
	 */
	public static function render( $record_id, $checker, $message = null ) {
		$record = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		printf( '<h4 class="wpcpm-details__subtitle">%s</h4>', esc_html__( 'Tracks', 'wpcredits-program-manager' ) );

		// The outcome line takes the focus after a post (`assets/js/forms.js`), as the dashboard card's does.
		if ( is_array( $message ) ) {
			printf(
				'<p class="wpcpm-details__message is-%1$s" role="status" tabindex="-1" id="%3$s" data-wpcpm-card-message>%2$s</p>',
				esc_attr( $message[0] ),
				esc_html( $message[1] ),
				esc_attr( self::OUTCOME_ID )
			);
		}

		$offered = self::runnable( $record );

		// One row, drawn as the card's two groups draw theirs (`WPCPM_Institution_Details_Card::render_rows()`).
		printf(
			'<dl class="wpcpm-details__list"><div class="wpcpm-details__row"><dt>%1$s</dt><dd>%2$s</dd></div></dl>',
			esc_html__( 'Tracks offered', 'wpcredits-program-manager' ),
			esc_html( array() === $offered ? __( 'None listed yet.', 'wpcredits-program-manager' ) : implode( ', ', $offered ) )
		);

		if ( ! $checker ) {
			$contact = trim( (string) WPCPM_Settings::get_value( 'mail_contact', '' ) );

			printf(
				'<p class="wpcpm-details__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the program's contact email address. */
						__( 'The program sets the tracks your institution offers. To change them, write to %s.', 'wpcredits-program-manager' ),
						'' !== $contact ? $contact : WPCPM_Student_Registration_Form::CONTACT
					)
				)
			);

			return;
		}

		// A closed disclosure, as the groups' forms are, open after an outcome the form was posted for.
		printf(
			'<details class="wpcpm-details__more" id="wpcpm-details-tracks"%1$s><summary class="wpcpm-details__toggle">%2$s</summary>',
			is_array( $message ) ? ' open' : '',
			esc_html__( 'Change the tracks', 'wpcredits-program-manager' )
		);
		printf(
			'<form class="wpcpm-details__form" method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="record" value="%s" />', esc_attr( $record ) );
		echo '<span class="wpcpm-field__choices" role="group" aria-label="' . esc_attr__( 'Tracks offered', 'wpcredits-program-manager' ) . '">';

		foreach ( WPCPM_Program::labels() as $status => $name ) {
			printf(
				'<label class="wpcpm-field__check"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> <span>%4$s</span></label>',
				esc_attr( self::FIELD ),
				esc_attr( (string) $status ),
				checked( isset( $offered[ $status ] ), true, false ),
				esc_html( (string) $name )
			);
		}

		echo '</span>';
		printf( '<span class="wpcpm-field__hint">%s</span>', esc_html__( 'Students are asked which track they want when two or more are ticked.', 'wpcredits-program-manager' ) );
		printf( '<p class="wpcpm-details__actions"><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Save tracks', 'wpcredits-program-manager' ) );
		echo '</form></details>';
	}

	/**
	 * Save the list an Administrator posted from the details card.
	 *
	 * The order is the card's own handler: the nonce keyed to the posted record, the record's shape, the
	 * policy against the institution the form names, and then the manager ground, which the policy's
	 * details action does not require (members pass it), and only then `save()`, under the school's lock. Ends the request with
	 * the outcome flashed on the card's channel.
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

		// The record's whole answers are read and written, as the details card's save at once does under the
		// school's lock: two writers at once would each overwrite the other's keys.
		$lock  = WPCPM_Institution_Request::school_lock_name( $record );
		$token = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

		if ( '' === $token ) {
			WPCPM_Institution_Details_Card::finish( $record, 'saving-busy', array( 'section' => 'tracks' ), self::OUTCOME_ID );
		}

		try {
			WPCPM_Institution_Record::forget_cached( $record );

			$saved = self::save( $record, WPCPM_Request::posted_list( self::FIELD, self::POSTED_VALUE ), get_current_user_id() );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		if ( is_wp_error( $saved ) ) {
			WPCPM_Institution_Details_Card::finish(
				$record,
				'said',
				array(
					'text'    => $saved->get_error_message(),
					'section' => 'tracks',
				),
				self::OUTCOME_ID
			);
		}

		WPCPM_Institution_Details_Card::finish( $record, $saved ? 'tracks-saved' : 'nothing', array( 'section' => 'tracks' ), self::OUTCOME_ID );
	}
}
