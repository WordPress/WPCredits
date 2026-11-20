<?php
/**
 * Institutions module - the talks with a school, read (Institutions off Airtable, release 2).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the Administrators know of their talks with a school, as one list of facts: the index's row (name,
 * stage, country, city, website, contact) and the school's record on the site (`WPCPM_Institution_Record`),
 * which keeps the talks fields under `RECORD_KEYS`, the assigned Administrator and the application's answers.
 *
 * This is the read side, for the Talks card on a school's page, the Talks view on the Administrator Dashboard
 * and the weekly email. The fields are written by the card's save and by the import, through
 * `WPCPM_Institution_Record::set()`; the notes are `WPCPM_Institution_Talk_Notes`.
 *
 * **A key is a record ID or a lead key.** A school the program talks with is keyed by its Airtable record ID,
 * as everything else in the module is. A lead, a school the program has not talked to yet, has no record ID
 * until it is promoted, so it is keyed `lead-` and the ID of its post (`is_lead_key()`), and its facts are read
 * from `WPCPM_Institution_Leads`, in the same shape, `lead` true; its address is the lead's page.
 *
 * All static, no state.
 */
class WPCPM_Institution_Talks {

	/**
	 * The record's keys the talks are kept under: the estimated number of students, the day the first
	 * students begin (`Y-m-d`), the next step (one line), the follow-up date (`Y-m-d`), the last contact
	 * (`Y-m-d`), the help desk link, the lead owner and the Program details (what the school asks for: hours, dates,
	 * reports; plain text of lines, for the Administrators alone, never copied to Airtable).
	 */
	const RECORD_KEYS = array( 'estimated_students', 'first_students', 'next_step', 'follow_up', 'last_contact', 'help_desk', 'lead_owner', 'program_details' );

	/** The most characters of a school's Program details: what it asks for, kept for the Administrators alone. */
	const MAX_PROGRAM_DETAILS = 5000;

	/** The record's key of the application's answers kept read-only: a list keyed by answer. */
	const RECORD_APPLICATION = 'application_answers';

	/** The history kind of a change of the talks' stage. */
	const LOG_STAGE = 'talks_stage';

	/** The history kind of a save of the talks' other fields. */
	const LOG_SAVED = 'talks_saved';

	/** The Talks view of the Administrator Dashboard, as its view argument names it. */
	const VIEW_TALKS = 'talks';

	/** The query argument that names a lead on the Talks view: its page. */
	const ARG_LEAD = 'wpcpm_lead';

	/** The stage of a school the program has not talked to yet, before every stage of the agreement's order. */
	const STAGE_LEAD = 'Lead';

	/** What a lead key starts with; the rest is its post's ID. */
	const LEAD_PREFIX = 'lead-';

	/** The Talks card's anchor: on a school's For Administrators view, and on a lead's page. */
	const ANCHOR = 'wpcpm-talks';

	/** The card's admin-post actions, each with its nonce's name before the key: the fields, a note, a lead closed and reopened. */
	const ACTION_SAVE   = 'wpcpm_talks_save';
	const ACTION_NOTE   = 'wpcpm_talks_note';
	const ACTION_CLOSE  = 'wpcpm_talks_close';
	const ACTION_REOPEN = 'wpcpm_talks_reopen';

	/** A closed lead deleted, a form and a press of its own (the owner's wave of release 2, X4). */
	const ACTION_DELETE = 'wpcpm_talks_delete';

	/** The id of Delete this lead's own form, which its button in the card's action row submits. */
	const DELETE_FORM = 'wpcpm-talks-delete';

	/** The flash channel a lead deleted leaves its name on, for the Talks view to say. */
	const DELETED_FLASH = 'institution_talks_deleted';

	/** The nonce field the close and reopen presses carry beside the save's own, in the one form. */
	const LEAD_NONCE = 'wpcpm_talks_lead_nonce';

	/** The flash channel the card's handlers leave their outcome on. */
	const FLASH = 'institution_talks';

	/** How many days ahead a move into Revisit Later proposes the follow-up. */
	const REVISIT_DAYS = 90;

	/** The posted assigned Administrator: a user ID, or 0 for nobody. */
	const FIELD_ASSIGNED = 'wpcpm_talks_assigned';

	/**
	 * The hidden value of the card's fields form: a hash of the stored values of every field the form saves, as they were
	 * when the card was drawn (`drawn_hash()`). A save made on values that changed meanwhile is refused (the owner's wave
	 * of release 2, X1).
	 */
	const FIELD_DRAWN = 'wpcpm_talks_drawn';

	/** The most characters of a next step, one line. */
	const MAX_NEXT_STEP = 200;

	/** The most students a school can be estimated to send. */
	const MAX_STUDENTS = 100000;

	/** How long, in seconds, a second press of the same note by the same Administrator adds nothing. */
	const NOTE_AGAIN = 60;

	/** How long, in seconds, the lock a note is added under is held at most. */
	const NOTE_LOCK_TTL = 10;

	/** The id of the note form's block, where a note's way back lands. */
	const NOTE_ID = 'wpcpm-talks-note';

	/** The stage a move into proposes the follow-up, and the one the agreement path sets. */
	const STAGE_REVISIT   = 'Revisit Later';
	const STAGE_CONFIRMED = 'Confirmed';

	/**
	 * The stages whose follow-up counts as due, the ONE list (PROVISIONAL, owner question 5): a school with no stage (''),
	 * a lead, First Contact Made to Agreement Sent, and Revisit Later. `counts_as_due()` reads it; the owner's other
	 * answer is a change of this list alone.
	 */
	const DUE_STAGES = array( '', 'Lead', 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Revisit Later' );

	/**
	 * One school's talks.
	 *
	 * @param string $key A record ID or a lead key.
	 * @return array|null `key`, `lead` (bool), `name` (trimmed), `stage`, `country` (a Countries record ID),
	 *                    `country_name`, `city`, `website`, `contact_person`, `contact_email`, `assigned`
	 *                    (the stored user ID, 0 for none), the eight `RECORD_KEYS` (each a string, '' for
	 *                    none) and `application_answers` (an array); `closed` (bool, only a lead closes this
	 *                    way) and `country_text` (a lead's country as text when it could not be resolved, '' for
	 *                    an institution), after `lead` and `country_name`. Null for a key the index does not
	 *                    hold, a key of neither shape, and a lead key the site keeps no lead for.
	 */
	public static function facts( $key ) {
		$key = self::clean_key( $key );

		if ( '' === $key ) {
			return null;
		}

		if ( self::is_lead_key( $key ) ) {
			$lead = WPCPM_Institution_Leads::get( $key );

			return null === $lead ? null : self::lead_shape( $lead );
		}

		$rows = WPCPM_Institutions_Index::rows();

		if ( ! isset( $rows[ $key ] ) || ! is_array( $rows[ $key ] ) ) {
			return null;
		}

		return self::shape( $key, $rows[ $key ], WPCPM_Institution_Record::get( $key ) );
	}

	/**
	 * Every school's talks, with the records read in one go (`WPCPM_Institution_Record::answers_for()`).
	 *
	 * @return array<string, array> Key => `facts()`, in the index's order, then every lead, a closed one too
	 *                              (readers that list the talks leave a closed lead out, and Find finds it).
	 */
	public static function all_facts() {
		$out  = array();
		$rows = array();

		foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
			$record_id = self::clean_key( $record_id );

			if ( '' !== $record_id && ! self::is_lead_key( $record_id ) && is_array( $row ) ) {
				$rows[ $record_id ] = $row;
			}
		}

		if ( ! empty( $rows ) ) {
			$answers = WPCPM_Institution_Record::answers_for( array_keys( $rows ) );

			foreach ( $rows as $record_id => $row ) {
				$out[ $record_id ] = self::shape( $record_id, $row, isset( $answers[ $record_id ] ) ? $answers[ $record_id ] : array() );
			}
		}

		foreach ( WPCPM_Institution_Leads::all() as $key => $lead ) {
			$out[ $key ] = self::lead_shape( $lead );
		}

		return $out;
	}

	/**
	 * Every stage the talks can be at: Lead, the agreement's order, then the three that close the talks.
	 *
	 * @return string[]
	 */
	public static function stages() {
		return array_merge( array( self::STAGE_LEAD ), WPCPM_Institution_Agreement::STAGE_ORDER, self::closed() );
	}

	/**
	 * The stages that close the talks: the agreement's own list of them.
	 *
	 * @return string[]
	 */
	public static function closed() {
		return WPCPM_Institution_Agreement::TERMINAL_STAGES;
	}

	/**
	 * Whether a school's follow-up can count as due: its talks go on (PROVISIONAL, owner question 5, from the count on the
	 * live records of 19 November 2026). The one place that says which stages count, read by the Talks view, the menu's
	 * count and the Monday email: a lead that is not closed, First Contact Made to Agreement Sent, Revisit Later, and a
	 * school with no stage (''). Confirmed, Student, Not Moving Forward, SPAM and a stage the site's lists do not hold
	 * never count; their follow-up date stays on the card.
	 *
	 * @param array $facts `facts()`.
	 * @return bool
	 */
	public static function counts_as_due( array $facts ) {
		return empty( $facts['closed'] ) && in_array( isset( $facts['stage'] ) ? (string) $facts['stage'] : '', self::DUE_STAGES, true );
	}

	/**
	 * Every follow-up due on or before a day, of the schools whose talks go on (`counts_as_due()`): oldest first, then by
	 * name. Task 17's email reads it too.
	 *
	 * @param array  $facts `all_facts()`, or any list of `facts()`.
	 * @param string $today The day, `Y-m-d`.
	 * @return array[] The schools' facts, in that order.
	 */
	public static function due( array $facts, $today ) {
		$today = (string) $today;
		$due   = array();

		foreach ( $facts as $one ) {
			if ( is_array( $one ) && isset( $one['follow_up'] ) && self::is_day( (string) $one['follow_up'] ) && (string) $one['follow_up'] <= $today && self::counts_as_due( $one ) ) {
				$due[] = $one;
			}
		}

		usort( $due, array( __CLASS__, 'by_follow_up' ) );

		return $due;
	}

	/**
	 * The order of a list of schools: the follow-up oldest first, a school with none last, then by name without regard to
	 * case.
	 *
	 * @param array $a `facts()`.
	 * @param array $b `facts()`.
	 * @return int
	 */
	public static function by_follow_up( array $a, array $b ) {
		$day_a = self::is_day( (string) $a['follow_up'] ) ? (string) $a['follow_up'] : '9999-99-99';
		$day_b = self::is_day( (string) $b['follow_up'] ) ? (string) $b['follow_up'] : '9999-99-99';

		if ( $day_a !== $day_b ) {
			return strcmp( $day_a, $day_b );
		}

		return strcasecmp( (string) $a['name'], (string) $b['name'] );
	}

	/**
	 * The page of a school's talks: its Talks card on the Institution Dashboard, or a lead's page on the
	 * Administrator Dashboard's Talks view. Not escaped: `esc_url()` in markup, as it is for a mail's link.
	 *
	 * @param string $key A record ID or a lead key.
	 * @return string '' for a key of neither shape and while the page has no address.
	 */
	public static function url( $key ) {
		$key = self::clean_key( $key );

		if ( '' === $key ) {
			return '';
		}

		if ( ! self::is_lead_key( $key ) ) {
			return WPCPM_Institutions_Dashboard::card_url( 'talks', $key );
		}

		$view = (string) WPCPM_Administrators_Dashboard::view_url( self::VIEW_TALKS );

		return '' === $view ? '' : (string) add_query_arg( self::ARG_LEAD, $key, $view );
	}

	/**
	 * Whether a value is a lead key: `lead-` and a post ID, with nothing around it.
	 *
	 * @param mixed $key Candidate.
	 * @return bool
	 */
	public static function is_lead_key( $key ) {
		return is_string( $key ) && 1 === preg_match( '/^' . preg_quote( self::LEAD_PREFIX, '/' ) . '[1-9][0-9]*$/D', $key );
	}

	/**
	 * A key as a caller handed it, trimmed, or '' when it is neither a record ID nor a lead key.
	 *
	 * @param mixed $key Candidate.
	 * @return string
	 */
	private static function clean_key( $key ) {
		if ( ! is_scalar( $key ) ) {
			return '';
		}

		$key = trim( (string) $key );

		return WPCPM_Airtable::is_record_id( $key ) || self::is_lead_key( $key ) ? $key : '';
	}

	/**
	 * One school's facts from its row and its record's answers.
	 *
	 * @param string $key     The key.
	 * @param array  $row     The index's row.
	 * @param array  $answers The record's answers.
	 * @return array
	 */
	private static function shape( $key, array $row, array $answers ) {
		$facts = array(
			'key'    => $key,
			'lead'   => false,
			'closed' => false,
		);

		foreach ( array( 'name', 'stage', 'country', 'country_name', 'country_text', 'city', 'website', 'contact_person', 'contact_email' ) as $field ) {
			$facts[ $field ] = isset( $row[ $field ] ) && is_scalar( $row[ $field ] ) ? trim( (string) $row[ $field ] ) : '';
		}

		// An institution's country is a record ID or nothing: only a lead keeps one as text.
		$facts['country_text'] = '';

		$assigned          = isset( $answers[ WPCPM_Institution_Assignment::RECORD_KEY ] ) ? $answers[ WPCPM_Institution_Assignment::RECORD_KEY ] : 0;
		$facts['assigned'] = is_numeric( $assigned ) && (int) $assigned > 0 ? (int) $assigned : 0;

		foreach ( self::RECORD_KEYS as $field ) {
			$facts[ $field ] = isset( $answers[ $field ] ) && is_scalar( $answers[ $field ] ) ? (string) $answers[ $field ] : '';
		}

		$facts[ self::RECORD_APPLICATION ] = isset( $answers[ self::RECORD_APPLICATION ] ) && is_array( $answers[ self::RECORD_APPLICATION ] ) ? $answers[ self::RECORD_APPLICATION ] : array();

		return $facts;
	}

	/**
	 * One lead's facts, in the shape of an institution's: the stage is Lead, the country's name is the
	 * resolved one, else the text the country came as, and nothing is an application's.
	 *
	 * @param array $lead `WPCPM_Institution_Leads::get()`.
	 * @return array
	 */
	private static function lead_shape( array $lead ) {
		$facts = array(
			'key'    => (string) $lead['key'],
			'lead'   => true,
			'closed' => '' !== (string) $lead['closed'],
			'name'   => trim( (string) $lead['name'] ),
			'stage'  => self::STAGE_LEAD,
		);

		$facts['country']        = (string) $lead['country'];
		$facts['country_name']   = '' !== (string) $lead['country_name'] ? (string) $lead['country_name'] : (string) $lead['country_text'];
		$facts['country_text']   = (string) $lead['country_text'];
		$facts['city']           = (string) $lead['city'];
		$facts['website']        = '';
		$facts['contact_person'] = (string) $lead['contact_person'];
		$facts['contact_email']  = (string) $lead['contact_email'];
		$facts['assigned']       = (int) $lead['assigned'];

		foreach ( self::RECORD_KEYS as $field ) {
			$facts[ $field ] = isset( $lead[ $field ] ) && is_scalar( $lead[ $field ] ) ? (string) $lead[ $field ] : '';
		}

		$facts[ self::RECORD_APPLICATION ] = array();

		return $facts;
	}

	/*
	 * The Talks card's save (Institutions off Airtable, release 2, Task 15)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The words the card says of a press, by the status its handlers flash: tone and sentence. A sentence that depends
	 * on what was asked (a refusal of a method, the confirmation of a move out of the talks) is flashed as text.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'saved'           => array( 'success', __( 'The talks are saved.', 'wpcredits-program-manager' ) ),
			// A save made on values someone changed after the card was drawn (X1): the card shows them as stored now.
			'changed'         => array( 'error', self::changed_sentence() ),
			'saved-mailed'    => array( 'success', __( 'The talks are saved, and the assigned Administrator has been emailed.', 'wpcredits-program-manager' ) ),
			'nothing'         => array( 'info', __( 'Nothing was changed.', 'wpcredits-program-manager' ) ),
			'refused'         => array( 'error', __( 'Nothing was saved. Correct what is marked below and save again.', 'wpcredits-program-manager' ) ),
			'busy'            => array( 'error', self::busy_sentence() ),
			'promoted'        => array( 'success', __( 'The lead is an institution now. Its talks go on here.', 'wpcredits-program-manager' ) ),
			'note-added'      => array( 'success', __( 'The note is added.', 'wpcredits-program-manager' ) ),
			'note-again'      => array( 'info', __( 'That note is already added.', 'wpcredits-program-manager' ) ),
			'note-busy'       => array( 'error', self::busy_sentence() ),
			'note-refused'    => array( 'error', __( 'The note was not added. Correct what is marked below and add it again.', 'wpcredits-program-manager' ) ),
			'closed'          => array( 'success', __( 'The lead is closed.', 'wpcredits-program-manager' ) ),
			'reopened'        => array( 'success', __( 'The lead is reopened.', 'wpcredits-program-manager' ) ),
			// A close or a reopen pressed over changes not saved yet: the typing stays in the form (fix round 1, U2).
			'closed-typed'    => array( 'success', __( 'The lead is closed. What you typed is not saved yet: press Save to keep it.', 'wpcredits-program-manager' ) ),
			'reopened-typed'  => array( 'success', __( 'The lead is reopened. What you typed is not saved yet: press Save to keep it.', 'wpcredits-program-manager' ) ),
			// Add a lead on the Talks view lands on the new lead's page (Task 16); a first note refused stands over Add a note.
			'lead-added'      => array( 'success', __( 'The lead is added.', 'wpcredits-program-manager' ) ),
			'lead-added-note' => array( 'warning', __( 'The lead is added, but its note is not. Correct the note below and add it again.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Land on a lead just added, from the Talks view's Add a lead (Task 16): its page, with "The lead is added." over the
	 * card's fields; or, when its first note was refused, the one warning over Add a note, the note kept in its box and the
	 * refusal under it. **This does not return.**
	 *
	 * @param string $key     The new lead's key.
	 * @param string $problem Why the first note was refused, '' when it was added or none was typed.
	 * @param string $note    The note as typed, kept when it was refused.
	 */
	public static function land_added( $key, $problem = '', $note = '' ) {
		if ( '' === (string) $problem ) {
			self::finish( $key, 'lead-added' );
		}

		// Room for every character typed, so a note refused for its length comes back whole.
		self::keep_note( self::note_form( $key ), (string) $note );

		self::finish(
			$key,
			'lead-added-note',
			array(
				'section'  => 'note',
				'problems' => array( 'note' => (string) $problem ),
			),
			self::NOTE_ID
		);
	}

	/**
	 * What a save made on values that changed after the card was drawn answers (X1): nothing was saved.
	 *
	 * @return string
	 */
	private static function changed_sentence() {
		return __( 'Nothing was saved: these talks changed after you opened the card. The card shows them as they are now. Make your change again and save.', 'wpcredits-program-manager' );
	}

	/**
	 * A hash of the stored values of every field the card's fields form saves for a school (`editable()`, a lead's country
	 * as text among them), as `facts()` reads them: never what the agreement path derives (`settled`). Not a secret: it
	 * only says whether the values are still the ones the card was drawn with (X1).
	 *
	 * @param array $facts `facts()`.
	 * @return string 64 hexadecimal characters.
	 */
	private static function drawn_hash( array $facts ) {
		$parts = array();

		foreach ( array_merge( self::editable( $facts ), array( 'country_text' ) ) as $field ) {
			$value   = isset( $facts[ $field ] ) && is_scalar( $facts[ $field ] ) ? (string) $facts[ $field ] : '';
			$parts[] = $field . '=' . ( 'assigned' === $field ? (string) (int) $value : $value );
		}

		return hash( 'sha256', (string) $facts['key'] . "\n" . implode( "\n", array_map( 'rawurlencode', $parts ) ) );
	}

	/**
	 * Whether a save's posted hash is the one of the values stored now (X1). A missing hash is no match.
	 *
	 * @param array $facts  `facts()`, read under the save's lock.
	 * @param array $posted What was posted, `drawn` among it.
	 * @return bool
	 */
	private static function drawn_matches( array $facts, array $posted ) {
		$drawn = isset( $posted['drawn'] ) && is_scalar( $posted['drawn'] ) ? trim( (string) $posted['drawn'] ) : '';

		return '' !== $drawn && hash_equals( self::drawn_hash( $facts ), $drawn );
	}

	/**
	 * The refusal of a save made on values that changed after the card was drawn: nothing written.
	 *
	 * @return WP_Error
	 */
	private static function changed() {
		return new WP_Error( 'wpcpm_talks_changed', self::changed_sentence() );
	}

	/**
	 * What a save answers when another request holds the school's lock or its agreement lock: nothing was read or written.
	 *
	 * @return string
	 */
	private static function busy_sentence() {
		return __( 'Someone else is changing these talks just now, so nothing was saved. Try again in a moment.', 'wpcredits-program-manager' );
	}

	/**
	 * The fields the card's form edits for a school, in the order it draws them: a lead's contact and help desk link
	 * too, since a lead has no details card to change them on. Last contact follows Follow up on, the two days of a
	 * follow-up read together; a school's help desk link follows Country, where a lead's card has it, so a lead moved
	 * into the talks keeps its link in the same place (1.123.104, round 2: the owner made both changeable).
	 *
	 * @param array $facts `facts()`.
	 * @return string[]
	 */
	private static function editable( array $facts ) {
		$fields = array( 'stage', 'next_step', 'follow_up', 'last_contact', 'assigned', 'country' );

		if ( ! empty( $facts['lead'] ) ) {
			// A lead's own fields, its city first, right after the country (Task 16 fix round 1, V7).
			$fields = array_merge( $fields, array( 'city', 'contact_person', 'contact_email', 'help_desk' ) );
		} else {
			$fields[] = 'help_desk';
		}

		// Program details last, the one long text, before the action row.
		return array_merge( $fields, array( 'estimated_students', 'first_students', 'program_details' ) );
	}

	/**
	 * A field's name, as its label and a sentence say it. The fields are drawn in `editable()`'s order: the ones changed
	 * at every call first (fix round 1, U4).
	 *
	 * @param string $field A field of `editable()`.
	 * @return string
	 */
	private static function label( $field ) {
		$labels = array(
			'stage'              => __( 'Stage', 'wpcredits-program-manager' ),
			'assigned'           => __( 'Assigned Administrator', 'wpcredits-program-manager' ),
			'city'               => __( 'City', 'wpcredits-program-manager' ),
			'contact_person'     => __( 'Contact person', 'wpcredits-program-manager' ),
			'contact_email'      => __( 'Contact\'s email', 'wpcredits-program-manager' ),
			'help_desk'          => __( 'Help desk link', 'wpcredits-program-manager' ),
			'country'            => __( 'Country', 'wpcredits-program-manager' ),
			'estimated_students' => __( 'Estimated students', 'wpcredits-program-manager' ),
			'first_students'     => __( 'First students begin', 'wpcredits-program-manager' ),
			'next_step'          => __( 'Next step', 'wpcredits-program-manager' ),
			'follow_up'          => __( 'Follow up on', 'wpcredits-program-manager' ),
			'last_contact'       => __( 'Last contact', 'wpcredits-program-manager' ),
			'program_details'    => __( 'Program details', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $field ] ) ? $labels[ $field ] : (string) $field;
	}

	/**
	 * The stages the card's select offers a school, value to words; the stages follow the agreement (fix round 1, L2).
	 *
	 * A lead: Lead, and the stages it may be promoted into (`WPCPM_Institution_Leads::promotion_stages()`); a closed
	 * lead, Lead alone, since it is reopened before it moves on. An institution whose Collaboration Agreement is settled
	 * (its index row's agreement block is Accepted or On file, `settled()`): Confirmed, Student and the three that close
	 * the talks. One whose agreement is not settled: First Contact Made to Agreement Sent and the three, never Confirmed
	 * or Student, which the agreement path leads to. Never Lead for a school with a record. The stage the school is at is
	 * always offered, first when the list does not hold it, so a save that does not touch it keeps it; an empty one is
	 * "No stage yet".
	 *
	 * @param array $facts `facts()`, with `settled` (`with_agreement()`).
	 * @return array<string, string>
	 */
	private static function stage_options( array $facts ) {
		$current = (string) $facts['stage'];

		if ( ! empty( $facts['lead'] ) ) {
			$stages = ! empty( $facts['closed'] ) ? array( self::STAGE_LEAD ) : array_merge( array( self::STAGE_LEAD ), WPCPM_Institution_Leads::promotion_stages() );
		} elseif ( ! empty( $facts['settled'] ) ) {
			$stages = array_merge( self::after_confirmed(), self::closed() );
		} else {
			$stages = array_merge( WPCPM_Institution_Leads::promotion_stages(), self::closed() );
		}

		$out = array();

		if ( '' === $current ) {
			$out[''] = __( 'No stage yet', 'wpcredits-program-manager' );
		} elseif ( ! in_array( $current, $stages, true ) ) {
			$out[ $current ] = $current;
		}

		foreach ( $stages as $stage ) {
			$out[ $stage ] = $stage;
		}

		return $out;
	}

	/**
	 * Confirmed and every stage of the agreement's order after it (Student).
	 *
	 * @return string[]
	 */
	private static function after_confirmed() {
		$order = WPCPM_Institution_Agreement::STAGE_ORDER;
		$at    = array_search( self::STAGE_CONFIRMED, $order, true );

		return false === $at ? array( self::STAGE_CONFIRMED ) : array_slice( $order, (int) $at );
	}

	/**
	 * A school's facts with `settled`: whether its index row's agreement block, as the table holds it now, is Accepted or
	 * On file, or a change the row still owes says so (fix round 2, R1). Writes nothing. A lead is never settled.
	 *
	 * @param array $facts `facts()`.
	 * @return array
	 */
	private static function with_agreement( array $facts ) {
		$facts['settled'] = false;

		if ( empty( $facts['lead'] ) ) {
			$row   = WPCPM_Institutions_Index::row( (string) $facts['key'] );
			$block = is_array( $row ) && isset( $row['agreement'] ) && is_array( $row['agreement'] ) ? $row['agreement'] : array();

			$settled = array( 'Accepted', WPCPM_Institution_Agreement::AIRTABLE_ON_FILE );

			// An acceptance the index row still owes counts, as the row will hold it once the change is paid (R1).
			$facts['settled'] = in_array( isset( $block['status'] ) ? (string) $block['status'] : '', $settled, true )
				|| in_array( WPCPM_Institution_Agreement::owed_status( (string) $facts['key'] ), $settled, true );
		}

		return $facts;
	}

	/**
	 * What a post holds for the card's form, read from the request: each field the form posts, unslashed and with the
	 * control characters dropped, by its key (`FIELD_ASSIGNED` read as `assigned`), and the two the form posts for its
	 * own rules, `follow_up_drawn` and `leave`. A field the post does not hold is left out, and left as it is.
	 *
	 * @param array $facts `facts()`.
	 * @return array<string, string>
	 */
	private static function posted( array $facts ) {
		$out = array();

		foreach ( array_merge( self::editable( $facts ), array( 'follow_up_drawn', 'leave' ) ) as $field ) {
			// Program details as typed, so an invalid character is refused at its box and not dropped unsaid; a value that
			// is no scalar (`program_details[]`) is not posted, and the stored text stays.
			$value = 'program_details' === $field
				? ( isset( $_POST['program_details'] ) && is_scalar( $_POST['program_details'] ) ? WPCPM_Request::posted_raw( 'program_details' ) : null ) // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The handler checked the nonce before it reads the post; only its type is read here.
				: WPCPM_Request::posted_verbatim( 'assigned' === $field ? self::FIELD_ASSIGNED : $field, null );

			if ( null !== $value ) {
				$out[ $field ] = (string) $value;
			}
		}

		// The hash of the stored values the card was drawn with (X1).
		$drawn = WPCPM_Request::posted_verbatim( self::FIELD_DRAWN, null );

		if ( null !== $drawn ) {
			$out['drawn'] = (string) $drawn;
		}

		return $out;
	}

	/**
	 * The posted fields, checked, as the values to keep and the sentence of each field refused.
	 *
	 * @param array $facts  `facts()`, as the school is now.
	 * @param array $posted Field to what was posted; a field not posted is left as it is.
	 * @return array{values: array<string, mixed>, problems: array<string, string>}
	 */
	private static function clean( array $facts, array $posted ) {
		$values   = array();
		$problems = array();

		foreach ( self::editable( $facts ) as $field ) {
			if ( ! array_key_exists( $field, $posted ) ) {
				continue;
			}

			$clean = self::clean_one( $field, (string) $posted[ $field ], $facts );

			if ( is_wp_error( $clean ) ) {
				$problems[ $field ] = $clean->get_error_message();

				continue;
			}

			if ( null !== $clean ) {
				$values[ $field ] = $clean;
			}
		}

		return array(
			'values'   => $values,
			'problems' => $problems,
		);
	}

	/**
	 * One field, checked.
	 *
	 * @param string $field A field of `editable()`.
	 * @param string $value What was posted.
	 * @param array  $facts `facts()`.
	 * @return mixed The value to keep, null to leave the field as it is, or the refusal.
	 */
	private static function clean_one( $field, $value, array $facts ) {
		$value = trim( $value );
		$lead  = ! empty( $facts['lead'] );

		switch ( $field ) {
			case 'stage':
				// The stage the school is at, saved unchanged, is always fine.
				if ( (string) $facts['stage'] === $value ) {
					return null;
				}

				if ( ! $lead && self::STAGE_LEAD === $value ) {
					return new WP_Error( 'wpcpm_talks_lead', __( 'Lead is only for a school the program has not talked to yet.', 'wpcredits-program-manager' ) );
				}

				// A lead's stage is the promotion's to refuse, in its own words.
				if ( $lead ) {
					return in_array( $value, self::stages(), true ) ? $value : new WP_Error( 'wpcpm_talks_stage', __( 'Choose the stage the talks are at.', 'wpcredits-program-manager' ) );
				}

				if ( array_key_exists( $value, self::stage_options( $facts ) ) ) {
					return $value;
				}

				if ( in_array( $value, self::after_confirmed(), true ) && empty( $facts['settled'] ) ) {
					return new WP_Error( 'wpcpm_talks_confirmed', __( 'Confirmed is set when the Collaboration Agreement is accepted or recorded as on file.', 'wpcredits-program-manager' ) );
				}

				if ( in_array( $value, WPCPM_Institution_Leads::promotion_stages(), true ) && ! empty( $facts['settled'] ) ) {
					return new WP_Error( 'wpcpm_talks_settled', __( 'This institution\'s Collaboration Agreement is settled, so its talks are at Confirmed or Student.', 'wpcredits-program-manager' ) );
				}

				return new WP_Error( 'wpcpm_talks_stage', __( 'Choose the stage the talks are at.', 'wpcredits-program-manager' ) );

			case 'assigned':
				$user_id = ctype_digit( $value ) ? (int) $value : -1;

				// Posted back as drawn: left as it is, an account that is no Administrator now included.
				if ( $user_id === (int) $facts['assigned'] ) {
					return null;
				}

				if ( $user_id < 0 || ( $user_id > 0 && ! user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) ) ) {
					return new WP_Error( 'wpcpm_talks_assigned', __( 'Only an Administrator can be assigned a school.', 'wpcredits-program-manager' ) );
				}

				return $user_id;

			case 'country':
				if ( '' === $value ) {
					// A lead whose country is kept as text draws no country chosen: its text is left as it is.
					if ( $lead || '' === (string) $facts['country'] ) {
						return null;
					}

					return new WP_Error( 'wpcpm_talks_country_empty', __( 'Choose the school\'s country.', 'wpcredits-program-manager' ) );
				}

				if ( ! WPCPM_Airtable::is_record_id( $value ) || ! array_key_exists( $value, WPCPM_Countries::options() ) ) {
					return new WP_Error( 'wpcpm_talks_country', __( 'That country is not one of the program\'s.', 'wpcredits-program-manager' ) );
				}

				return $value;

			case 'estimated_students':
				if ( '' !== $value && ( 1 !== preg_match( '/^[0-9]{1,6}$/D', $value ) || (int) $value > self::MAX_STUDENTS ) ) {
					/* translators: %d: the most students a school can be estimated to send. */
					return new WP_Error( 'wpcpm_talks_number', sprintf( __( 'Estimated students must be a whole number from 0 to %d.', 'wpcredits-program-manager' ), self::MAX_STUDENTS ) );
				}

				return '' === $value ? '' : (string) (int) $value;

			case 'last_contact':
				// A day the import left, posted back as drawn, is left as it is, so it never stops a save of another field.
				if ( trim( (string) $facts['last_contact'] ) === $value ) {
					return null;
				}

				if ( '' !== $value && ! self::is_day( $value ) ) {
					/* translators: %s: the name of a date field, as "Follow up on". */
					return new WP_Error( 'wpcpm_talks_date', sprintf( __( '%s must be a date.', 'wpcredits-program-manager' ), self::label( $field ) ) );
				}

				// After today only when it is after today everywhere on Earth: today at UTC+14 (1.123.104, round 4), so an
				// Administrator east of the site's time zone can give their own today.
				if ( '' !== $value && $value > gmdate( 'Y-m-d', time() + 14 * HOUR_IN_SECONDS ) ) {
					return new WP_Error( 'wpcpm_talks_last_contact', __( 'Last contact cannot be after today.', 'wpcredits-program-manager' ) );
				}

				return $value;

			case 'first_students':
			case 'follow_up':
				if ( '' !== $value && ! self::is_day( $value ) ) {
					/* translators: %s: the name of a date field, as "Follow up on". */
					return new WP_Error( 'wpcpm_talks_date', sprintf( __( '%s must be a date.', 'wpcredits-program-manager' ), self::label( $field ) ) );
				}

				return $value;

			case 'program_details':
				if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
					/* translators: %s: the name of a field. */
					return new WP_Error( 'wpcpm_talks_text', sprintf( __( '%s holds characters the site cannot read.', 'wpcredits-program-manager' ), self::label( $field ) ) );
				}

				// Lines kept, each break one "\n", the control characters (`\p{Cc}`) but the breaks and tabs dropped; format
				// characters stay, so a zero-width joiner or non-joiner and a soft hyphen are the text's.
				$value = trim( (string) preg_replace( '/[^\P{Cc}\n\t]+/u', '', str_replace( array( "\r\n", "\r" ), "\n", $value ) ) );

				if ( mb_strlen( $value, 'UTF-8' ) > self::MAX_PROGRAM_DETAILS ) {
					/* translators: %d: the most characters the Program details may hold. */
					return new WP_Error( 'wpcpm_talks_long', sprintf( __( 'Program details can be at most %d characters long.', 'wpcredits-program-manager' ), self::MAX_PROGRAM_DETAILS ) );
				}

				return $value;

			case 'next_step':
				$value = WPCPM_Institution_Leads::one_line( $value );

				if ( mb_strlen( $value, 'UTF-8' ) > self::MAX_NEXT_STEP ) {
					/* translators: %d: the most characters the next step may hold. */
					return new WP_Error( 'wpcpm_talks_long', sprintf( __( 'The next step can be at most %d characters long.', 'wpcredits-program-manager' ), self::MAX_NEXT_STEP ) );
				}

				return $value;

			case 'contact_email':
				if ( '' !== $value && ! is_email( $value ) ) {
					return new WP_Error( 'wpcpm_talks_email', __( 'That contact email is not an email address.', 'wpcredits-program-manager' ) );
				}

				return strtolower( $value );

			case 'help_desk':
				// A link the import left, posted back as drawn, is left as it is, so it never stops a save of another field.
				if ( trim( (string) $facts['help_desk'] ) === $value ) {
					return null;
				}

				// One rule for a lead's and a school's link, the leads' own (1.123.104, round 4).
				$problem = WPCPM_Institution_Leads::help_desk_problem( $value );

				return null === $problem ? $value : $problem;
		}

		// The city and the contact person: one line, as their boxes draw them, by the rule a lead's Add a lead keeps too.
		return WPCPM_Institution_Leads::one_line( $value );
	}

	/**
	 * Whether a value is a day as the talks keep one: `Y-m-d`, and a real one.
	 *
	 * @param string $date Candidate.
	 * @return bool
	 */
	private static function is_day( $date ) {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/D', (string) $date ) ) {
			return false;
		}

		$parsed = DateTime::createFromFormat( '!Y-m-d', (string) $date, new DateTimeZone( 'UTC' ) );

		return $parsed instanceof DateTime && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * The fields whose cleaned value differs from the one the school holds.
	 *
	 * @param array $facts  `facts()`.
	 * @param array $values `clean()`'s values.
	 * @return array<string, mixed> Field to its new value, in the order of `editable()`.
	 */
	private static function changes( array $facts, array $values ) {
		$out = array();

		foreach ( $values as $field => $value ) {
			$now = isset( $facts[ $field ] ) ? $facts[ $field ] : '';

			if ( 'assigned' === $field ? (int) $now !== (int) $value : trim( (string) $now ) !== (string) $value ) {
				$out[ $field ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Save the card's fields for one school.
	 *
	 * Only once the site keeps the record (`WPCPM_Institutions_Index::on_site()`). For an institution, under the school's
	 * lock (`WPCPM_Institution_Request::school_lock_name()`, the details card's) and then its agreement lock
	 * (`WPCPM_Institution_Agreement::take_lock()`, the one acceptance and recording on file decide the stage under), in
	 * this order: what the school holds read again under them, its agreement block among it; the fields checked, the stage
	 * against the stages its agreement leads to (`stage_options()`); a move out of the active stages asked first (`leave`,
	 * the stage asked about); a move into Revisit Later given a follow-up 90 days ahead when the date was left as drawn;
	 * the index row (stage and country, one locked `patch()`); the record (the talks keys: when it cannot be kept after
	 * the index change landed, its keys are left out of what is saved and named in `unsaved`); the assignment
	 * (`WPCPM_Institution_Assignment::assign()`, its row and its email); one `talks_stage` row and one `talks_saved` row;
	 * the gate closed for a move out (`WPCPM_Institutions_Sync::close_gate()`). Both locks are let go, then one copy of the
	 * changed copy columns (`WPCPM_Institution_Copy::send()`, which reads the values at send time and never fails the
	 * save), then a move back into the active stages opens the gate (`open_gate()`, which takes the agreement lock itself).
	 *
	 * For a lead, its fields through `WPCPM_Institution_Leads::update()` under the lead's own lock, then a stage through
	 * `promote()`, each refusal in the method's own sentence.
	 *
	 * Either way, under the lock and before anything is written, the posted `drawn` (the hash the card was drawn with)
	 * must be the hash of the values stored now; otherwise nothing is saved (`wpcpm_talks_changed`, the owner's wave, X1).
	 *
	 * @param string $key    A record ID or a lead key.
	 * @param array  $posted Field to what was posted (`stage`, `assigned`, `country`, `estimated_students`,
	 *                       `first_students`, `next_step`, `follow_up`, a lead's `contact_person`, `contact_email` and
	 *                       `help_desk`), with `follow_up_drawn` (the date the form drew) and `leave` (the stage a move out
	 *                       of the active stages was confirmed for).
	 * @param int    $actor  Who saves.
	 * @return array|WP_Error `changed` (the fields changed, as `editable()` names them), `said` (sentences to say after the
	 *                        outcome), `mailed` (whether a new assignee was emailed) and `key` (the school's key after the
	 *                        save: a promoted lead's record ID), `unsaved` (the fields the record could not keep) and `copy`
	 *                        (the copy columns sent); or the refusal, nothing written: `wpcpm_talks_fields` with `problems`
	 *                        (field to sentence) as its data, `wpcpm_talks_leave` (the question, its sentence, `stage` its
	 *                        data), `wpcpm_talks_busy`, `wpcpm_talks_index`, `wpcpm_talks_record`, a lead method's own (with
	 *                        `also` in its data when the lead's other fields were saved), and `wpcpm_talks_not_on_site` and
	 *                        `wpcpm_talks_gone`.
	 */
	public static function save( $key, array $posted, $actor ) {
		$key   = self::clean_key( $key );
		$actor = (int) $actor;

		if ( ! WPCPM_Institutions_Index::on_site() ) {
			return new WP_Error( 'wpcpm_talks_not_on_site', __( 'The talks are changed here only once the site keeps the record of institutions.', 'wpcredits-program-manager' ) );
		}

		if ( '' === $key ) {
			return self::gone();
		}

		if ( self::is_lead_key( $key ) ) {
			return self::save_lead( $key, $posted, $actor );
		}

		$school = WPCPM_Institution_Request::school_lock_name( $key );
		$token  = WPCPM_Lock::take( $school, WPCPM_Institution_Request::LOCK_TTL );

		if ( '' === $token ) {
			return new WP_Error( 'wpcpm_talks_busy', self::busy_sentence() );
		}

		$from = '';
		$to   = '';

		try {
			if ( ! WPCPM_Institution_Agreement::take_lock( $key ) ) {
				return new WP_Error( 'wpcpm_talks_busy', self::busy_sentence() );
			}

			try {
				$saved = self::save_held( $key, $posted, $actor, $from, $to );
			} finally {
				WPCPM_Institution_Agreement::release_lock( $key );
			}
		} finally {
			WPCPM_Lock::release( $school, $token );
		}

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// The copy reads the site's values when it sends, so it is sent once both locks are let go (fix round 1, L4).
		if ( array() !== $saved['copy'] ) {
			WPCPM_Institution_Copy::send( $key, $saved['copy'] );
		}

		if ( $from === $to ) {
			return $saved;
		}

		// Back into the active stages: the gate the move out closed is rebuilt from the site's own record, now that the
		// agreement lock the rebuild takes is free again.
		$active = self::active_stages();

		if ( ! WPCPM_Institutions_Sync::is_in_pipeline( $from, $active ) && WPCPM_Institutions_Sync::is_in_pipeline( $to, $active ) ) {
			if ( WPCPM_Institutions_Sync::open_gate( $key ) ) {
				if ( WPCPM_Institution_Agreement::is_settled( $key ) ) {
					$saved['said'][] = __( 'Its Institution Dashboard is open again.', 'wpcredits-program-manager' );
				}
			} else {
				$saved['said'][] = __( 'Its Institution Dashboard is still locked. It opens again with the next sync.', 'wpcredits-program-manager' );
			}

			// The accounts the leaving removed stay former members, and only those are spoken of (the trial's round of
			// release 2, T2 and T4): never one taken off on purpose, nor one that is another school's member now.
			$revoked = self::revoked_by_leaving( $key );

			if ( $revoked > 0 ) {
				$saved['said'][] = 1 === $revoked
					? __( 'The account removed when it left does not come back by itself: add it again with Re-add, under Former members on Manage members.', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %d: how many accounts the move out of the active stages removed. */
						_n( 'The %d account removed when it left does not come back by itself: add it again with Re-add, under Former members on Manage members.', 'The %d accounts removed when it left do not come back by themselves: add them again with Re-add, under Former members on Manage members.', $revoked, 'wpcredits-program-manager' ),
						$revoked
					);
			}
		}

		return $saved;
	}

	/**
	 * How many of a school's former members lost the membership because the school left the active stages (their
	 * newest `member_removed` row for this school says `revoked`, `WPCPM_Institution_Members::REASON_REVOKED`) and are a
	 * member of no institution now (the trial's round of release 2, T4). The reason is kept only in the history row the
	 * detach writes, so it is read from there, and only on a move back into the active stages.
	 *
	 * @param string $key The record ID.
	 * @return int
	 */
	private static function revoked_by_leaving( $key ) {
		$former = WPCPM_Institution_Members::former_members_of( $key );

		if ( array() === $former ) {
			return 0;
		}

		// The newest row of each account, newest first.
		$reason = array();

		foreach ( WPCPM_Institution_Audit::entries_for( $key, 0 ) as $entry ) {
			if ( WPCPM_Institution_Audit::KIND_MEMBER_REMOVED !== ( isset( $entry['kind'] ) ? $entry['kind'] : '' ) || ! isset( $entry['data']['user'], $entry['data']['reason'] ) ) {
				continue;
			}

			$user = (int) $entry['data']['user'];

			if ( ! isset( $reason[ $user ] ) ) {
				$reason[ $user ] = (string) $entry['data']['reason'];
			}
		}

		$count = 0;

		foreach ( $former as $user ) {
			$id = (int) $user->ID;

			if ( WPCPM_Institution_Members::REASON_REVOKED === ( isset( $reason[ $id ] ) ? $reason[ $id ] : '' ) && array() === WPCPM_Institution_Members::memberships_of( $user ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * An institution's save, with its two locks held: `save()`'s steps from the read to the copy.
	 *
	 * @param string $key    The record ID.
	 * @param array  $posted What was posted.
	 * @param int    $actor  Who saves.
	 * @param string $from   Set to the stage the school was at.
	 * @param string $to     Set to the stage it is at after the save.
	 * @return array|WP_Error As `save()` answers.
	 */
	private static function save_held( $key, array $posted, $actor, &$from, &$to ) {
		// The values as stored now, under both locks and before anything is written, against the ones the card was drawn
		// with: a save made on values someone changed meanwhile would silently put back the old ones (X1).
		WPCPM_Institutions_Index::forget_cached();
		WPCPM_Institution_Record::forget_cached( $key );

		$now = self::facts( $key );

		if ( null === $now ) {
			return self::gone();
		}

		if ( ! self::drawn_matches( $now, $posted ) ) {
			return self::changed();
		}

		// What the agreement path owes the row is paid first, as open_gate() pays it, so the stages read follow the
		// agreement; a change still owed after that is another request's to finish, and nothing is written (R1).
		if ( WPCPM_Institution_Agreement::owes_index( $key ) ) {
			WPCPM_Institution_Agreement::keep_index( $key, array() );

			if ( WPCPM_Institution_Agreement::owes_index( $key ) ) {
				return new WP_Error( 'wpcpm_talks_busy', self::busy_sentence() );
			}
		}

		WPCPM_Institutions_Index::forget_cached();
		WPCPM_Institution_Record::forget_cached( $key );

		$facts = self::facts( $key );

		if ( null === $facts ) {
			return self::gone();
		}

		// The agreement block as the index holds it under the agreement lock: the stages offered follow it (L2).
		$facts   = self::with_agreement( $facts );
		$from    = (string) $facts['stage'];
		$to      = $from;
		$cleaned = self::clean( $facts, $posted );

		if ( array() !== $cleaned['problems'] ) {
			return new WP_Error( 'wpcpm_talks_fields', __( 'Nothing was saved. Correct what is marked below and save again.', 'wpcredits-program-manager' ), array( 'problems' => $cleaned['problems'] ) );
		}

		$changes = self::changes( $facts, $cleaned['values'] );
		$said    = array();

		if ( isset( $changes['stage'] ) ) {
			$to     = (string) $changes['stage'];
			$active = self::active_stages();
			$leaves = WPCPM_Institutions_Sync::is_in_pipeline( $from, $active ) && ! WPCPM_Institutions_Sync::is_in_pipeline( $to, $active );

			// The question confirms the one stage it asked about: a save of another stage asks again (L6).
			if ( $leaves && ( isset( $posted['leave'] ) ? trim( (string) $posted['leave'] ) : '' ) !== $to ) {
				$to = $from;

				return new WP_Error( 'wpcpm_talks_leave', self::leave_question( $key, (string) $facts['name'], $changes['stage'] ), array( 'stage' => (string) $changes['stage'] ) );
			}

			// Revisit Later proposes its follow-up when the date was left as the form drew it; a date typed wins.
			if ( self::STAGE_REVISIT === $to && isset( $posted['follow_up'], $posted['follow_up_drawn'] ) && trim( (string) $posted['follow_up'] ) === trim( (string) $posted['follow_up_drawn'] ) ) {
				$ahead = (string) wp_date( 'Y-m-d', time() + self::REVISIT_DAYS * DAY_IN_SECONDS );

				// Said only when the save changed the date (L7).
				if ( $ahead !== (string) $facts['follow_up'] ) {
					$changes['follow_up'] = $ahead;
					$said['follow_up']    = sprintf(
						/* translators: 1: a day, 2: how many days ahead it is, as a number. */
						__( 'Follow up on is set to %1$s, %2$s days ahead. Change it if you like.', 'wpcredits-program-manager' ),
						WPCPM_Dates::day( $ahead ),
						number_format_i18n( self::REVISIT_DAYS )
					);
				}
			}
		}

		if ( array() === $changes ) {
			return self::saved( array(), $key );
		}

		$row     = array();
		$unsaved = array();

		if ( isset( $changes['stage'] ) ) {
			$row['stage'] = $changes['stage'];
		}

		if ( isset( $changes['country'] ) ) {
			$row['country']      = $changes['country'];
			$row['country_name'] = (string) WPCPM_Countries::name_of( $changes['country'] );
		}

		if ( array() !== $row && ! WPCPM_Institutions_Index::patch( $key, $row ) ) {
			$to = $from;

			return new WP_Error( 'wpcpm_talks_index', self::not_saved_sentence() );
		}

		$answers = array_intersect_key( $changes, array_flip( self::RECORD_KEYS ) );

		if ( array() !== $answers && ! WPCPM_Institution_Record::set( $key, $answers ) ) {
			if ( array() === $row ) {
				return new WP_Error( 'wpcpm_talks_record', self::not_saved_sentence() );
			}

			// The index change landed and stands; the record's keys did not, so nothing names them as saved (L1).
			$unsaved = array_keys( $answers );
			$changes = array_diff_key( $changes, $answers );

			// A follow-on sentence about a field that was not saved is not said (R2).
			$said = array_diff_key( $said, $answers );
		}

		$mailed = false;

		if ( isset( $changes['assigned'] ) ) {
			$assigned = WPCPM_Institution_Assignment::assign( $key, (int) $changes['assigned'], $actor, true, $mailed );

			if ( is_wp_error( $assigned ) ) {
				unset( $changes['assigned'] );

				if ( array() === $changes ) {
					return $assigned;
				}

				$said[] = $assigned->get_error_message();
			}
		}

		if ( isset( $changes['stage'] ) ) {
			self::log_stage( $key, $from, $to, $actor );
		}

		$saved = array_values( array_diff( array_keys( $changes ), array( 'stage', 'assigned' ) ) );

		if ( array() !== $saved ) {
			WPCPM_Institution_Audit::record(
				array(
					'kind'        => self::LOG_SAVED,
					'institution' => $key,
					'subject'     => $key,
					'actor'       => $actor,
					'ground'      => WPCPM_Institution_Audit::GROUND_MANAGER,
					'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'     => self::saved_sentence( $saved ),
					'data'        => array( 'changed' => $saved ),
				)
			);
		}

		if ( isset( $changes['stage'] ) ) {
			$active = self::active_stages();

			if ( WPCPM_Institutions_Sync::is_in_pipeline( $from, $active ) && ! WPCPM_Institutions_Sync::is_in_pipeline( $to, $active ) ) {
				$said = array_merge( $said, self::closed_words( WPCPM_Institutions_Sync::close_gate( $key, $actor ) ) );
			}
		}

		$out            = self::saved( array_keys( $changes ), $key );
		$out['said']    = array_values( $said );
		$out['mailed']  = (bool) $mailed;
		$out['unsaved'] = $unsaved;
		$out['copy']    = array_values( array_intersect( array_keys( $changes ), array_keys( WPCPM_Institution_Copy::COLUMNS ) ) );

		// The copy's mark is written with the change, inside both locks (it sends no request): a request that dies before
		// its send() after the locks still leaves the keys owed, for the hourly retry (the outside review, O5).
		if ( array() !== $out['copy'] ) {
			WPCPM_Institution_Copy::mark( $key, $out['copy'] );
		}

		return $out;
	}

	/**
	 * A lead's save: its fields through `update()`, then a stage other than Lead through `promote()`.
	 *
	 * @param string $key    The lead key.
	 * @param array  $posted What was posted.
	 * @param int    $actor  Who saves.
	 * @return array|WP_Error As `save()` answers.
	 */
	private static function save_lead( $key, array $posted, $actor ) {
		$lock = WPCPM_Institution_Leads::lock_name( $key );

		if ( '' === $lock ) {
			return self::gone();
		}

		// The lead's own lock, the one a promotion and a delete hold: the values are read and checked under it (X1), from
		// the table, this request's copy of the lead dropped once it is held (the outside review, O3).
		$token = WPCPM_Institution_Leads::take_lock( $key );

		if ( '' === $token ) {
			return new WP_Error( 'wpcpm_talks_busy', self::busy_sentence() );
		}

		try {
			$facts = self::facts( $key );

			if ( null === $facts ) {
				return self::gone();
			}

			if ( ! self::drawn_matches( $facts, $posted ) ) {
				return self::changed();
			}

			$facts   = self::with_agreement( $facts );
			$cleaned = self::clean( $facts, $posted );

			if ( array() !== $cleaned['problems'] ) {
				return new WP_Error( 'wpcpm_talks_fields', __( 'Nothing was saved. Correct what is marked below and save again.', 'wpcredits-program-manager' ), array( 'problems' => $cleaned['problems'] ) );
			}

			$changes = self::changes( $facts, $cleaned['values'] );
			$stage   = isset( $changes['stage'] ) ? (string) $changes['stage'] : '';

			unset( $changes['stage'] );

			if ( array() !== $changes ) {
				$updated = WPCPM_Institution_Leads::update( $key, $changes, $actor );

				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		// The promotion takes the lead's lock itself, so it runs once this save has let it go.

		$out = self::saved( array_keys( $changes ), $key );

		if ( '' === $stage ) {
			return $out;
		}

		$record = WPCPM_Institution_Leads::promote( $key, $stage, $actor );

		// A refused promotion after the other fields were saved says so after its own sentence (L3).
		if ( is_wp_error( $record ) ) {
			return array() === $changes ? $record : new WP_Error( $record->get_error_code(), $record->get_error_message(), array( 'also' => array( __( 'The other changes are saved.', 'wpcredits-program-manager' ) ) ) );
		}

		$out['changed'][] = 'stage';
		$out['key']       = (string) $record;

		return $out;
	}

	/**
	 * A save's answer: the fields changed, nothing to say, nobody emailed, and the school's key.
	 *
	 * @param string[] $changed The fields changed.
	 * @param string   $key     The school's key.
	 * @return array
	 */
	private static function saved( array $changed, $key ) {
		return array(
			'changed' => array_values( $changed ),
			'said'    => array(),
			'mailed'  => false,
			'key'     => (string) $key,
			'unsaved' => array(),
			'copy'    => array(),
		);
	}

	/**
	 * "The talks were updated: Next step and Follow up on.": the fields saved, in the card's order, as their labels read,
	 * the sentence a lead's save stores too (`WPCPM_Institution_Leads::update()`).
	 *
	 * @param string[] $fields Fields of `editable()`.
	 * @return string
	 */
	private static function saved_sentence( array $fields ) {
		return sprintf(
			/* translators: %s: the names of the fields that changed, as a list, as "Next step and Follow up on". */
			__( 'The talks were updated: %s.', 'wpcredits-program-manager' ),
			wp_sprintf_l( '%l', array_map( array( __CLASS__, 'label' ), $fields ) )
		);
	}

	/**
	 * The `talks_stage` row of a move the card made.
	 *
	 * @param string $key   The record ID.
	 * @param string $from  The stage it was at.
	 * @param string $to    The stage it is at now.
	 * @param int    $actor Who moved it.
	 */
	private static function log_stage( $key, $from, $to, $actor ) {
		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_STAGE,
				'institution' => $key,
				'subject'     => $key,
				'actor'       => (int) $actor,
				'ground'      => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => '' === $from
					? sprintf(
						/* translators: %s: the stage the talks moved to. */
						__( 'The stage of the talks was set to %s.', 'wpcredits-program-manager' ),
						$to
					)
					: sprintf(
						/* translators: 1: the stage the talks were at, 2: the stage they moved to. */
						__( 'The stage of the talks moved from %1$s to %2$s.', 'wpcredits-program-manager' ),
						$from,
						$to
					),
				'data'        => array(
					'from' => $from,
					'to'   => $to,
				),
			)
		);
	}

	/**
	 * The stages the program treats as active, from the settings.
	 *
	 * @return string[]
	 */
	private static function active_stages() {
		$settings = WPCPM_Settings::get();

		return isset( $settings['institution_active_stages'] ) ? (array) $settings['institution_active_stages'] : array();
	}

	/**
	 * What the card asks before a move out of the active stages: what the move does to the school's accounts, which is
	 * what the sync's revoke does to them, and that saving again confirms it.
	 *
	 * @param string $key   The record ID.
	 * @param string $name  The school's name.
	 * @param string $stage The stage asked for.
	 * @return string
	 */
	private static function leave_question( $key, $name, $stage ) {
		$settings = WPCPM_Settings::get();
		$accounts = 'revoke' === ( isset( $settings['institution_on_inactive'] ) ? $settings['institution_on_inactive'] : 'revoke' ) ? count( WPCPM_Institution_Members::members_of( $key ) ) : 0;
		$name     = '' !== trim( $name ) ? trim( $name ) : $key;

		if ( $accounts > 0 ) {
			return sprintf(
				/* translators: 1: the school's name, 2: a stage, 3: how many accounts act for the school, as a number. */
				_n( 'Moving %1$s to %2$s removes the access of its %3$s account. Save again to confirm.', 'Moving %1$s to %2$s removes the access of its %3$s accounts. Save again to confirm.', $accounts, 'wpcredits-program-manager' ),
				$name,
				$stage,
				number_format_i18n( $accounts )
			);
		}

		return sprintf(
			/* translators: 1: the school's name, 2: a stage. */
			__( 'Moving %1$s to %2$s locks its Institution Dashboard. Save again to confirm.', 'wpcredits-program-manager' ),
			$name,
			$stage
		);
	}

	/**
	 * What the save says once the gate is closed: the accounts whose access went, or that the page is locked.
	 *
	 * @param array $closed `WPCPM_Institutions_Sync::close_gate()`'s answer.
	 * @return string[]
	 */
	private static function closed_words( array $closed ) {
		$out = array();

		if ( (int) $closed['revoked'] > 0 ) {
			$out[] = sprintf(
				/* translators: %s: how many accounts lost their access, as a number. */
				_n( 'The access of its %s account was removed.', 'The access of its %s accounts was removed.', (int) $closed['revoked'], 'wpcredits-program-manager' ),
				number_format_i18n( (int) $closed['revoked'] )
			);
		} else {
			$out[] = __( 'Its Institution Dashboard is locked now.', 'wpcredits-program-manager' );
		}

		foreach ( isset( $closed['failed'] ) ? (array) $closed['failed'] : array() as $message ) {
			$out[] = (string) $message;
		}

		return $out;
	}

	/**
	 * The sentence of a change the site could not keep: the details card's own.
	 *
	 * @return string
	 */
	private static function not_saved_sentence() {
		return __( 'The change could not be saved on the site, so nothing was changed. Try again in a while.', 'wpcredits-program-manager' );
	}

	/**
	 * The refusal of a key the site keeps no school for.
	 *
	 * @return WP_Error
	 */
	private static function gone() {
		return new WP_Error( 'wpcpm_talks_gone', __( 'That school is not in the talks any more.', 'wpcredits-program-manager' ) );
	}

	/*
	 * The card's handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Hooks: the card's four admin-post actions. Called from `WPCPM_Institutions::boot()`.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_NOTE, array( __CLASS__, 'handle_note' ) );
		add_action( 'admin_post_' . self::ACTION_CLOSE, array( __CLASS__, 'handle_close' ) );
		add_action( 'admin_post_' . self::ACTION_REOPEN, array( __CLASS__, 'handle_reopen' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Save the card's fields.
	 *
	 * **The order is the details card's:** the nonce keyed to the posted key, the key's shape (a record ID or a lead
	 * key), the policy (`ACT_TALKS`, the Administrators alone) and the setting, and only then `save()`, which takes the
	 * school's locks. A refusal keeps what was typed for the redraw and says why over the form; the question before a
	 * move out of the active stages keeps it too, and the redraw posts `leave` with it.
	 */
	public static function handle_save() {
		$key = WPCPM_Request::posted_text( 'key' );

		check_admin_referer( self::ACTION_SAVE . '_' . $key );

		self::refuse_unless_allowed( $key, WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_TALKS, WPCPM_Institution_Policy::subject_institution( $key ) ) );

		$facts = self::facts( $key );

		if ( null === $facts ) {
			self::finish( $key, 'said', array( 'text' => self::gone()->get_error_message() ) );
		}

		WPCPM_Typed_Text::forget( self::typed_form( $key ) );

		$posted = self::posted( $facts );
		$saved  = self::save( $key, $posted, get_current_user_id() );

		if ( is_wp_error( $saved ) ) {
			$code = $saved->get_error_code();
			$data = $saved->get_error_data();

			// Values changed meanwhile: the card is drawn as stored now, what was typed is not kept (X1).
			if ( 'wpcpm_talks_changed' === $code ) {
				self::finish( $key, 'changed' );
			}

			self::keep_typing( $key, $posted, $facts );

			if ( 'wpcpm_talks_fields' === $code ) {
				self::finish( $key, 'refused', array( 'problems' => is_array( $data ) && isset( $data['problems'] ) ? (array) $data['problems'] : array() ) );
			}

			if ( 'wpcpm_talks_leave' === $code ) {
				self::finish(
					$key,
					'leave',
					array(
						'text'  => $saved->get_error_message(),
						'leave' => is_array( $data ) && isset( $data['stage'] ) ? (string) $data['stage'] : '',
					)
				);
			}

			self::finish(
				$key,
				'wpcpm_talks_busy' === $code ? 'busy' : 'said',
				array(
					'text' => $saved->get_error_message(),
					'said' => is_array( $data ) && isset( $data['also'] ) ? (array) $data['also'] : array(),
				)
			);
		}

		// Part of the save was kept and part was not: one warning names what was saved and what was not, the typing kept
		// in the form (L1, R2).
		if ( array() !== $saved['unsaved'] ) {
			self::keep_typing( $key, $posted, $facts );

			self::finish(
				$key,
				'partial',
				array(
					'said'    => $saved['said'],
					'kept'    => array_map( array( __CLASS__, 'label' ), $saved['changed'] ),
					'unsaved' => array_map( array( __CLASS__, 'label' ), $saved['unsaved'] ),
				)
			);
		}

		if ( array() === $saved['changed'] ) {
			self::finish( $key, 'nothing' );
		}

		// A lead promoted is a school with a record now: its page is the new one, at the card.
		if ( $saved['key'] !== $key ) {
			self::finish( $saved['key'], 'promoted' );
		}

		self::finish( $key, $saved['mailed'] ? 'saved-mailed' : 'saved', array( 'said' => $saved['said'] ) );
	}

	/**
	 * Add a note to a school's talks: dated now and signed by the Administrator who adds it, with no history row,
	 * since the note is its own record. A second press of the same words by the same Administrator within
	 * `NOTE_AGAIN` seconds adds nothing.
	 */
	public static function handle_note() {
		$key = WPCPM_Request::posted_text( 'key' );

		check_admin_referer( self::ACTION_NOTE . '_' . $key );

		self::refuse_unless_allowed( $key, WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_TALKS, WPCPM_Institution_Policy::subject_institution( $key ) ) );

		$form  = self::note_form( $key );
		$actor = get_current_user_id();
		$text  = (string) WPCPM_Request::posted_verbatim( 'note' );
		$extra = array( 'section' => 'note' );

		WPCPM_Typed_Text::forget( $form );

		// A lead the site no longer keeps is answered by the leads' own add, which says whether it became an institution
		// (round 3, P1); a school the talks do not hold is refused here.
		if ( ! self::is_lead_key( $key ) && null === self::facts( $key ) ) {
			self::finish( $key, 'said', array_merge( $extra, array( 'text' => self::gone()->get_error_message() ) ), self::NOTE_ID );
		}

		// The same-words guard and the add under one short lock, so two presses at once add one note (L8).
		$lock  = WPCPM_Institution_Request::LOCK_PREFIX . 'talks_note_' . $key;
		$token = WPCPM_Lock::take( $lock, self::NOTE_LOCK_TTL );

		// The card is drawn again after this refusal, so the note typed comes back in its box (1.123.102); not for a lead
		// gone or moved into the talks meanwhile, whose page has no card to draw it or forget it.
		if ( '' === $token ) {
			if ( ! self::is_lead_key( $key ) || null !== WPCPM_Institution_Leads::get( $key ) ) {
				self::keep_note( $form, $text );
			}

			self::finish( $key, 'note-busy', $extra, self::NOTE_ID );
		}

		try {
			$again = self::added_already( $key, $text, $actor );

			// A lead's note under the lead's own lock, the lead read again under it (the outside review, O8).
			if ( $again ) {
				$added = 0;
			} elseif ( self::is_lead_key( $key ) ) {
				$added = WPCPM_Institution_Leads::add_note( $key, $text, $actor );
			} else {
				$added = WPCPM_Institution_Talk_Notes::add( $key, $text, $actor );
			}
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		if ( $again ) {
			self::finish( $key, 'note-again', $extra, self::NOTE_ID );
		}

		if ( is_wp_error( $added ) ) {
			// A lead that is gone, or was moved into the talks, has no card to draw the box again, so nothing would ever
			// draw or forget a note kept for it (1.123.102).
			if ( ! in_array( $added->get_error_code(), array( 'wpcpm_lead_gone', 'wpcpm_lead_promoted' ), true ) ) {
				self::keep_note( $form, $text );
			}

			self::finish( $key, 'note-refused', array_merge( $extra, array( 'problems' => array( 'note' => $added->get_error_message() ) ) ), self::NOTE_ID );
		}

		self::finish( $key, 'note-added', $extra, self::NOTE_ID );
	}

	/**
	 * Close a lead that goes nowhere (PROVISIONAL, owner question 3): it keeps everything and can be reopened.
	 */
	public static function handle_close() {
		self::handle_lead( self::ACTION_CLOSE );
	}

	/**
	 * Reopen a closed lead.
	 */
	public static function handle_reopen() {
		self::handle_lead( self::ACTION_REOPEN );
	}

	/**
	 * Delete a closed lead (the owner's wave of release 2, X4): its own nonce keyed to the lead, a lead key, the policy and
	 * the setting, then `WPCPM_Institution_Leads::trash()`. The first press deletes nothing: the card is drawn again with
	 * the question, in the warning tone, and Delete this lead's form carries the confirmation; the second moves the lead
	 * and its notes to WordPress's Trash and lands on the Talks view, which says so. A refusal is said over the card's
	 * fields. **This does not return.**
	 */
	public static function handle_delete() {
		$key = WPCPM_Request::posted_text( 'key' );

		check_admin_referer( self::ACTION_DELETE . '_' . $key );

		self::refuse_unless_allowed( $key, WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_TALKS, WPCPM_Institution_Policy::subject_institution( $key ) ) );

		if ( ! self::is_lead_key( $key ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$done = WPCPM_Institution_Leads::trash( $key, get_current_user_id(), (string) WPCPM_Request::posted_verbatim( 'confirm' ) );

		if ( is_wp_error( $done ) ) {
			self::finish( $key, 'said', array( 'text' => $done->get_error_message() ) );
		}

		if ( empty( $done['deleted'] ) ) {
			self::finish(
				$key,
				'delete-ask',
				array(
					'text'   => (string) $done['question'],
					'delete' => (string) $done['confirm'],
				)
			);
		}

		if ( class_exists( 'WPCPM_Institution_Talks_View' ) ) {
			WPCPM_Institution_Talks_View::forget();
		}

		WPCPM_Flash::set( self::DELETED_FLASH, array( 'name' => (string) $done['name'] ) );

		$view = (string) WPCPM_Administrators_Dashboard::view_url( self::VIEW_TALKS );

		wp_safe_redirect( '' !== $view ? $view : home_url( '/' ) );
		exit;
	}

	/**
	 * Whether this request brings back a refused note's outcome for this key (`note-refused`), taken whether or not it is
	 * printed: for a lead's page that has no card to show it on (round 3, P2). A flash about another key is taken and
	 * answered false, as the card itself drops one.
	 *
	 * @param string $key A lead key.
	 * @return bool
	 */
	public static function take_refused_note( $key ) {
		$flash = self::take_flash( (string) $key );

		return is_array( $flash ) && 'note-refused' === $flash['status'];
	}

	/**
	 * What the Talks view says once a lead is deleted, taken whether or not it is printed: "The lead <name> was deleted."
	 *
	 * @return string '' when no lead was deleted.
	 */
	public static function take_deleted() {
		$flash = WPCPM_Flash::take( self::DELETED_FLASH );

		if ( ! is_array( $flash ) || ! isset( $flash['name'] ) || ! is_string( $flash['name'] ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: the lead's name. */
			__( 'The lead %s was deleted.', 'wpcredits-program-manager' ),
			$flash['name']
		);
	}

	/**
	 * A lead's close or reopen: the press's own nonce, a lead key, the policy and the setting, then the leads' method.
	 *
	 * @param string $action `ACTION_CLOSE` or `ACTION_REOPEN`.
	 */
	private static function handle_lead( $action ) {
		$key = WPCPM_Request::posted_text( 'key' );

		check_admin_referer( $action . '_' . $key, self::LEAD_NONCE );

		self::refuse_unless_allowed( $key, WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_TALKS, WPCPM_Institution_Policy::subject_institution( $key ) ) );

		if ( ! self::is_lead_key( $key ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		// The press comes from the fields form: what it typed and did not save is kept for the form, and said (U2).
		$facts  = self::facts( $key );
		$typed  = false;
		$posted = array();

		if ( null !== $facts ) {
			$posted  = self::posted( $facts );
			$cleaned = self::clean( self::with_agreement( $facts ), $posted );
			$differs = self::changes( $facts, $cleaned['values'] );

			// A closed lead's Stage box offers Lead alone, so its stage is no typed work (R6).
			if ( ! empty( $facts['closed'] ) ) {
				unset( $differs['stage'], $cleaned['problems']['stage'] );
			}

			$typed = array() !== $cleaned['problems'] || array() !== $differs;
		}

		WPCPM_Typed_Text::forget( self::typed_form( $key ) );

		$done = self::ACTION_CLOSE === $action
			? WPCPM_Institution_Leads::close( $key, get_current_user_id() )
			: WPCPM_Institution_Leads::reopen( $key, get_current_user_id() );

		if ( $typed ) {
			self::keep_typing( $key, $posted, $facts );
		}

		if ( is_wp_error( $done ) ) {
			self::finish( $key, 'said', array( 'text' => $done->get_error_message() ) );
		}

		$status = ! $done ? 'nothing' : ( self::ACTION_CLOSE === $action ? 'closed' : 'reopened' );

		self::finish( $key, $typed && $done ? $status . '-typed' : $status );
	}

	/**
	 * Refuse a post with the policy's one refusal, and nothing written, unless its key is a record ID or a lead key, the
	 * policy allowed the poster the talks (`ACT_TALKS`, decided by the handler before anything is read) and the site keeps
	 * the record of institutions. **This does not return** on a refusal.
	 *
	 * @param string $key      The posted key.
	 * @param array  $decision `WPCPM_Institution_Policy::decide()`'s answer for it.
	 */
	private static function refuse_unless_allowed( $key, array $decision ) {
		if ( '' !== self::clean_key( $key ) && WPCPM_Institutions_Index::on_site() && ! empty( $decision['allowed'] ) ) {
			return;
		}

		wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
	}

	/**
	 * Whether the newest note of a school is these words, added by this Administrator less than `NOTE_AGAIN` seconds ago.
	 *
	 * @param string $key   The school's key.
	 * @param string $text  The words posted.
	 * @param int    $actor Who posts them.
	 * @return bool
	 */
	private static function added_already( $key, $text, $actor ) {
		$notes = WPCPM_Institution_Talk_Notes::for_key( $key );
		$text  = trim( (string) $text );

		if ( array() === $notes || '' === $text || $actor < 1 ) {
			return false;
		}

		$newest = $notes[0];
		$when   = strtotime( $newest['date'] . ' UTC' );

		return trim( (string) $newest['text'] ) === $text
			&& (int) get_post_meta( (int) $newest['id'], WPCPM_Institution_Talk_Notes::META_AUTHOR, true ) === (int) $actor
			&& false !== $when
			&& time() - $when <= self::NOTE_AGAIN;
	}

	/**
	 * The card's fields form, as `WPCPM_Typed_Text::keep()` names it.
	 *
	 * @param string $key The school's key.
	 * @return string
	 */
	private static function typed_form( $key ) {
		return 'talks:' . $key;
	}

	/**
	 * The card's note form, as `WPCPM_Typed_Text::keep()` names it.
	 *
	 * @param string $key The school's key.
	 * @return string
	 */
	private static function note_form( $key ) {
		return 'talks-note:' . $key;
	}

	/**
	 * Keep a refused note for the one time its box is drawn again, with room for every character typed, so a note
	 * refused for its length comes back whole (1.123.102). The note is the raw typing, so its room is counted as typed
	 * (`WPCPM_Typed_Text::typed_room()`): `kept_room()` alone would cut a typed `&amp;` short.
	 *
	 * @param string $form The note form's name (`note_form()`).
	 * @param string $note The note as typed.
	 */
	private static function keep_note( $form, $note ) {
		$note = (string) $note;

		WPCPM_Typed_Text::keep( (string) $form, array( 'note' => $note ), array( 'note' => WPCPM_Typed_Text::typed_room( $note, WPCPM_Institution_Talk_Notes::MAX_LENGTH ) ) );
	}

	/**
	 * Keep what a refused post typed, for the one time the form is drawn again, each text up to the room its box has.
	 *
	 * @param string $key    The school's key.
	 * @param array  $posted What `posted()` read.
	 * @param array  $facts  `facts()`.
	 */
	private static function keep_typing( $key, array $posted, array $facts ) {
		$room = array();

		// The hash is the drawing's own, never typing (X1).
		unset( $posted['drawn'] );

		foreach ( array_keys( $posted ) as $field ) {
			$limits         = array(
				'next_step'       => self::MAX_NEXT_STEP,
				// A refused text comes back whole, over its limit too, so nothing typed is lost.
				'program_details' => 2 * self::MAX_PROGRAM_DETAILS,
				'city'            => WPCPM_Institution_Leads::MAX_LENGTHS['city'],
				'contact_person'  => 1000,
				'contact_email'   => 200,
				'help_desk'       => 500,
			);
			$limit          = isset( $limits[ $field ] ) ? $limits[ $field ] : 100;
			$room[ $field ] = WPCPM_Typed_Text::kept_room( isset( $facts[ $field ] ) && is_scalar( $facts[ $field ] ) ? (string) $facts[ $field ] : '', $limit );
		}

		WPCPM_Typed_Text::keep( self::typed_form( $key ), $posted, $room );
	}

	/**
	 * Flash the outcome and go back to the card. **This does not return.** The way back is rebuilt here: a school's
	 * Talks card through `card_url()` (its For Administrators view, the school named for an Administrator), and a lead's
	 * page at the card.
	 *
	 * @param string $key    The school's key the outcome is about.
	 * @param string $status A key of `messages()`, `leave`, or `said` with the sentence in `$extra['text']`.
	 * @param array  $extra  `text`, `problems` (field to sentence), `said` (sentences after the outcome), `leave`, `section`.
	 * @param string $anchor The id to land on, the card's own by default.
	 */
	private static function finish( $key, $status, array $extra = array(), $anchor = '' ) {
		$key = (string) $key;

		// What was saved, noted, closed, reopened or added is the site's now: a drawing later in this request reads it
		// again rather than the view's memo of the talks.
		if ( class_exists( 'WPCPM_Institution_Talks_View' ) ) {
			WPCPM_Institution_Talks_View::forget();
		}

		WPCPM_Flash::set(
			self::FLASH,
			array_merge(
				array(
					'status' => (string) $status,
					'key'    => $key,
				),
				$extra
			)
		);

		$anchor = '' !== $anchor ? $anchor : self::ANCHOR;

		if ( self::is_lead_key( $key ) ) {
			$url = self::url( $key );
			$url = '' !== $url ? $url . '#' . $anchor : '';
		} else {
			$url = WPCPM_Institutions_Dashboard::card_url( 'talks', WPCPM_Airtable::is_record_id( $key ) ? $key : '', $anchor );
		}

		wp_safe_redirect( '' !== $url ? $url : home_url( '/' ) );
		exit;
	}

	/**
	 * The outcome a handler left for this school's card, taken whether or not it is printed, so none waits in the
	 * account's meta.
	 *
	 * @param string $key The school's key.
	 * @return array{status: string, text: string, section: string, problems: array, said: string[], unsaved: string[], kept: string[], leave: string, delete: string}|null
	 */
	private static function take_flash( $key ) {
		$flash = WPCPM_Flash::take( self::FLASH );

		if ( ! is_array( $flash ) ) {
			return null;
		}

		// A flash about another school is that school's card's alone: an Administrator who moved on meanwhile is not
		// told here what was saved there.
		if ( ! isset( $flash['key'] ) || (string) $flash['key'] !== (string) $key ) {
			return null;
		}

		$said     = array();
		$problems = array();
		$unsaved  = array();
		$kept     = array();

		foreach ( isset( $flash['unsaved'] ) && is_array( $flash['unsaved'] ) ? $flash['unsaved'] : array() as $words ) {
			if ( is_string( $words ) && '' !== $words ) {
				$unsaved[] = $words;
			}
		}

		foreach ( isset( $flash['kept'] ) && is_array( $flash['kept'] ) ? $flash['kept'] : array() as $words ) {
			if ( is_string( $words ) && '' !== $words ) {
				$kept[] = $words;
			}
		}

		foreach ( isset( $flash['said'] ) && is_array( $flash['said'] ) ? $flash['said'] : array() as $sentence ) {
			if ( is_string( $sentence ) && '' !== trim( $sentence ) ) {
				$said[] = $sentence;
			}
		}

		foreach ( isset( $flash['problems'] ) && is_array( $flash['problems'] ) ? $flash['problems'] : array() as $field => $sentence ) {
			if ( is_string( $field ) && is_string( $sentence ) ) {
				$problems[ $field ] = $sentence;
			}
		}

		return array(
			'status'   => isset( $flash['status'] ) ? (string) $flash['status'] : '',
			'text'     => isset( $flash['text'] ) && is_scalar( $flash['text'] ) ? trim( (string) $flash['text'] ) : '',
			'section'  => isset( $flash['section'] ) && 'note' === $flash['section'] ? 'note' : 'fields',
			'problems' => $problems,
			'said'     => $said,
			'unsaved'  => $unsaved,
			'kept'     => $kept,
			'leave'    => isset( $flash['leave'] ) && is_string( $flash['leave'] ) ? $flash['leave'] : '',
			'delete'   => isset( $flash['delete'] ) && is_string( $flash['delete'] ) ? $flash['delete'] : '',
		);
	}

	/*
	 * The card (design 5; the dashboards' frame, on the shared parts)
	 * --------------------------------------------------------------------
	 */

	/**
	 * Draw the Talks card for one school: the first card of the For Administrators view of the Institution Dashboard
	 * (PROVISIONAL, owner question 1), and the card of a lead's page.
	 *
	 * Nothing at all outside site mode, and for a reader the policy refuses (`ACT_TALKS`, the Administrators alone): a
	 * member never sees it. The flash and the typing a refused form kept are taken here whether or not they are printed.
	 *
	 * @param string $key     A record ID or a lead key.
	 * @param array  $context The dashboard's context, the signature every card shares; the card asks the policy itself.
	 *                        `named` => false draws it without the school's name (a lead's page, Task 16 fix round 1).
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 * phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	 */
	public static function render( $key, array $context = array() ) {
		$key = self::clean_key( $key );

		if ( '' === $key || ! WPCPM_Institutions_Index::on_site() ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_TALKS, WPCPM_Institution_Policy::subject_institution( $key ) );

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		$facts = self::facts( $key );

		if ( null === $facts ) {
			return;
		}

		$flash = self::take_flash( $key );
		$kept  = WPCPM_Typed_Text::kept( self::typed_form( $key ) );
		$note  = WPCPM_Typed_Text::kept( self::note_form( $key ) );

		// A lead's page names the lead in its head, so the card does not say the name again (Task 16 fix round 1, V7).
		self::render_parts( $facts, $flash, $kept, $note, ! isset( $context['named'] ) || false !== $context['named'] );
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

	/**
	 * The card on the parts: the shell's card fold, open, its anchor on the section; the school's name with a status tag
	 * when one is due ("Closed" for a closed lead, with the line that says how it moves on; "Past due" for a follow-up
	 * before today); the fields form, its outcome its first block, directly over its fields, the stage's last change the
	 * Stage field's hint, and its one Save (a lead's action row also holds Close this lead or Reopen this lead, quiet);
	 * what is on record and not edited here; the notes, newest first; and Add a note, a form of its own with its outcome
	 * directly over it.
	 *
	 * @param array      $facts `facts()`.
	 * @param array|null $flash `take_flash()`.
	 * @param array      $kept  What a refused fields post kept.
	 * @param array      $note  What a refused note kept.
	 * @param bool       $named Whether the card says the school's name (false on a lead's page, whose head says it).
	 */
	private static function render_parts( array $facts, $flash, array $kept, array $note, $named = true ) {
		$facts = self::with_agreement( $facts );
		$title = __( 'Talks', 'wpcredits-program-manager' );

		// How to do this, as the dashboards' other cards carry it (1.123.104, G5): a lead's page to closing or reopening
		// the lead, a school's card to changing its stage, next step and follow-up date.
		$help = ! empty( $facts['lead'] )
			? WPCPM_Guide_Jobs::link( 'lead-close', $title )
			: WPCPM_Guide_Jobs::link( 'talks-change', $title );

		WPCPM_Dashboard_Card::open(
			array(
				'key'           => 'talks',
				'title'         => $title,
				'help'          => $help,
				'open'          => true,
				'prefix'        => 'wpcpm-',
				'id_on'         => 'section',
				'section_class' => '',
			)
		);

		echo self::header_html( $facts, $named ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.

		$fields = null !== $flash && 'fields' === $flash['section'] ? $flash : null;
		$notes  = null !== $flash && 'note' === $flash['section'] ? $flash : null;

		// The standing warning of a lead whose move did not finish is drawn only while the card has no outcome to show, so
		// the outcome is the card's first message, the one the script focuses (round 2 of the whole-branch wave, point 1).
		echo self::form_html( $facts, $fields, $kept, null === $flash ) . self::record_html( $facts ) . self::notes_html( $facts, $notes, $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in WPCPM_Dashboard_Card::message().

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The card's first block: the school's name (a long word in it breaks) and, when one is due, its status tag: "Closed"
	 * for a closed lead, with one muted line beside it, "Reopen this lead to move it on."; else "Past due" for a follow-up
	 * before today. The stage is said once, in its field (fix round 1, U3, U8).
	 *
	 * @param array $facts `facts()`.
	 * @param bool  $named Whether to say the name; without it the tag and the muted line alone.
	 * @return string Escaped markup.
	 */
	private static function header_html( array $facts, $named = true ) {
		$tag        = null;
		$facts_line = array();

		if ( ! empty( $facts['closed'] ) ) {
			$tag          = array(
				'words' => __( 'Closed', 'wpcredits-program-manager' ),
				'tone'  => '',
			);
			$facts_line[] = __( 'Reopen this lead to move it on.', 'wpcredits-program-manager' );
		} elseif ( self::past_due( $facts ) ) {
			$tag = array(
				'words' => __( 'Past due', 'wpcredits-program-manager' ),
				'tone'  => 'red',
			);
		}

		// Without the name: the tag and the muted line alone, as one line of facts, or nothing.
		if ( ! $named ) {
			if ( null !== $tag ) {
				array_unshift( $facts_line, array( 'html' => WPCPM_UI::tag( $tag['words'], $tag['tone'] ) ) );
			}

			return WPCPM_UI::facts_line( $facts_line );
		}

		return WPCPM_UI::item_header(
			array(
				'name_html' => WPCPM_UI::breakable( '' !== (string) $facts['name'] ? (string) $facts['name'] : (string) $facts['key'] ),
				'tag'       => $tag,
				'facts'     => $facts_line,
			)
		);
	}

	/**
	 * Whether the school's follow-up day is before today: never for a closed lead, which is left out of what is due.
	 *
	 * @param array $facts `facts()`.
	 * @return bool
	 */
	private static function past_due( array $facts ) {
		$day = (string) $facts['follow_up'];

		return empty( $facts['closed'] ) && self::is_day( $day ) && $day < (string) wp_date( 'Y-m-d' );
	}

	/**
	 * "Since <day>, by <name>.": when the stage was last changed and by whom, from the school's newest `talks_stage` row;
	 * "Since <day>." for a change the site made; '' when no row says it.
	 *
	 * @param array $facts `facts()`.
	 * @return string
	 */
	private static function since_line( array $facts ) {
		$entries = ! empty( $facts['lead'] )
			? WPCPM_Institution_Audit::entries_for_lead( (string) $facts['key'], 200 )
			: WPCPM_Institution_Audit::entries_for( (string) $facts['key'], 200 );

		foreach ( $entries as $entry ) {
			if ( self::LOG_STAGE !== ( isset( $entry['kind'] ) ? $entry['kind'] : '' ) ) {
				continue;
			}

			$day   = WPCPM_Dates::day( (int) $entry['time'] );
			$actor = isset( $entry['actor'] ) ? (int) $entry['actor'] : 0;
			$name  = $actor > 0 ? WPCPM_Institution_Assignment::label( $actor ) : '';

			if ( '' === $day ) {
				return '';
			}

			return '' !== $name
				/* translators: 1: the day the stage of the talks was last changed, 2: who changed it. */
				? sprintf( __( 'Since %1$s, by %2$s.', 'wpcredits-program-manager' ), $day, $name )
				/* translators: %s: the day the stage of the talks was last changed. */
				: sprintf( __( 'Since %s.', 'wpcredits-program-manager' ), $day );
		}

		return '';
	}

	/**
	 * A form's outcome as the one block directly over it: the flash's sentence in its tone, then each sentence the save
	 * said after it as running text in the same block (fix round 2, R4). A refusal's sentence links to the first box it
	 * marked (fix round 1, U6). A save kept in part is one warning naming what was saved and what was not (R2).
	 *
	 * @param array|null $flash The form's outcome, or null.
	 * @param string     $lead  The id of the first box the refusal marked, or ''.
	 * @return string Escaped markup.
	 */
	private static function outcome_html( $flash, $lead = '' ) {
		if ( null === $flash ) {
			return '';
		}

		$messages = self::messages();
		$follow   = implode( ' ', $flash['said'] );

		// The question before a move out of the active stages is the only thing said then: a block of its own.
		if ( in_array( $flash['status'], array( 'leave', 'delete-ask' ), true ) && '' !== $flash['text'] ) {
			return WPCPM_Dashboard_Card::message( 'warning', $flash['text'], 'wpcpm-talks__message' );
		}

		if ( 'partial' === $flash['status'] && array() !== $flash['unsaved'] ) {
			$tone = 'warning';
			$text = sprintf(
				/* translators: 1: the names of the fields that were saved, 2: the names of those that were not, each as "Next step and Follow up on". */
				__( 'Not everything was saved. Saved: %1$s. Not saved: %2$s. Save again.', 'wpcredits-program-manager' ),
				wp_sprintf_l( '%l', $flash['kept'] ),
				wp_sprintf_l( '%l', $flash['unsaved'] )
			);
		} elseif ( 'said' === $flash['status'] && '' !== $flash['text'] ) {
			$tone = 'error';
			$text = $flash['text'];
		} elseif ( isset( $messages[ $flash['status'] ] ) ) {
			list( $tone, $text ) = $messages[ $flash['status'] ];
		} else {
			return '' === $follow ? '' : WPCPM_Dashboard_Card::message( 'info', $follow, 'wpcpm-talks__message' );
		}

		// One block per outcome: its sentence, then what the save says after it, as running text in its tone (R4). A
		// refusal alone links to the first box it marked.
		if ( '' !== $follow ) {
			return WPCPM_Dashboard_Card::message( $tone, $text . ' ' . $follow, 'wpcpm-talks__message' );
		}

		return WPCPM_Dashboard_Card::message( $tone, $text, 'wpcpm-talks__message', 'error' === $tone ? $lead : '' );
	}

	/**
	 * The fields form, in `editable()`'s order: the stage (its last change its hint), the next step, the follow-up day,
	 * the assigned Administrator (the one field to type and pick), the country (the one combobox), a lead's contact and
	 * help desk link, the estimated students and the day the first students begin; each refusal under its box; one Save,
	 * and for a lead Close this lead or Reopen this lead, quiet, each its own press with its own nonce; a closed lead's row
	 * ends with Delete this lead, quiet red, which submits its own small form after this one (X4). The form carries the
	 * hash of the stored values it was drawn with (`FIELD_DRAWN`, X1).
	 *
	 * @param array      $facts    `facts()`, with `settled`.
	 * @param array|null $flash    The form's outcome, or null.
	 * @param array      $kept     What a refused post kept.
	 * @param bool       $standing Whether a standing warning may be drawn: false while the card shows an outcome.
	 * @return string Escaped markup.
	 */
	private static function form_html( array $facts, $flash, array $kept, $standing = true ) {
		$key      = (string) $facts['key'];
		$lead     = ! empty( $facts['lead'] );
		$problems = null !== $flash ? $flash['problems'] : array();
		$value    = static function ( $field ) use ( $facts, $kept ) {
			if ( array_key_exists( $field, $kept ) && is_scalar( $kept[ $field ] ) ) {
				return (string) $kept[ $field ];
			}

			return isset( $facts[ $field ] ) && is_scalar( $facts[ $field ] ) ? (string) $facts[ $field ] : '';
		};
		$hidden   = array(
			'action'          => self::ACTION_SAVE,
			'key'             => $key,
			'follow_up_drawn' => array_key_exists( 'follow_up_drawn', $kept ) ? (string) $kept['follow_up_drawn'] : (string) $facts['follow_up'],
			// The stored values as drawn now, whatever a refusal kept of the typing (X1).
			self::FIELD_DRAWN => self::drawn_hash( $facts ),
		);

		// The question before a move out of the active stages is answered by saving again: the redraw posts the stage it
		// asked about, and a save of any other stage asks again (L6).
		if ( null !== $flash && '' !== $flash['leave'] ) {
			$hidden['leave'] = $flash['leave'];
		}

		$options = self::stage_options( $facts );
		$stage   = $value( 'stage' );

		// A kept stage the list does not offer would fall to its first option and move the school silently (U1).
		if ( ! array_key_exists( $stage, $options ) ) {
			$stage = (string) $facts['stage'];
		}

		$first = '';

		foreach ( self::editable( $facts ) as $field ) {
			if ( isset( $problems[ $field ] ) ) {
				$first = self::field_id( $field );
				break;
			}
		}

		$parts = array(
			'stage'              => WPCPM_UI::field(
				array(
					'type'          => 'select',
					'id'            => self::field_id( 'stage' ),
					'name'          => 'stage',
					'label'         => self::label( 'stage' ),
					'value'         => $stage,
					'options'       => $options,
					'need'          => 'required',
					'required_attr' => false,
					'hint'          => self::since_line( $facts ),
					'problem'       => isset( $problems['stage'] ) ? $problems['stage'] : '',
				)
			),
			'assigned'           => self::assigned_field( $facts, $kept, isset( $problems['assigned'] ) ? $problems['assigned'] : '' ),
			'country'            => WPCPM_UI::field(
				array(
					'type'          => 'combo',
					'id'            => self::field_id( 'country' ),
					'name'          => 'country',
					'label'         => self::label( 'country' ),
					'value'         => $value( 'country' ),
					'options'       => array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + WPCPM_Countries::options(),
					'need'          => 'required',
					'required_attr' => false,
					// A lead whose country could not be resolved says what it came as, until one is chosen.
					'hint'          => $lead && '' === (string) $facts['country'] && '' !== (string) $facts['country_text']
						? sprintf(
							/* translators: %s: a country's name as it was given. */
							__( 'The country was given as %s, which is not in the program\'s list. Choose one from the list.', 'wpcredits-program-manager' ),
							(string) $facts['country_text']
						)
						: '',
					'problem'       => isset( $problems['country'] ) ? $problems['country'] : '',
					'find'          => __( 'Type to find a country', 'wpcredits-program-manager' ),
					'none'          => __( 'No country has that name.', 'wpcredits-program-manager' ),
					/* translators: %s: how many countries the list shows, as a number. */
					'count'         => __( 'Countries in the list: %s', 'wpcredits-program-manager' ),
				)
			),
			'estimated_students' => WPCPM_UI::field(
				array(
					'type'    => 'number',
					'id'      => self::field_id( 'estimated_students' ),
					'name'    => 'estimated_students',
					'label'   => self::label( 'estimated_students' ),
					'value'   => $value( 'estimated_students' ),
					'problem' => isset( $problems['estimated_students'] ) ? $problems['estimated_students'] : '',
					'attrs'   => array(
						'min'  => 0,
						'max'  => self::MAX_STUDENTS,
						'step' => 1,
					),
				)
			),
		);

		foreach ( array( 'first_students', 'next_step', 'follow_up', 'last_contact' ) as $field ) {
			$parts[ $field ] = WPCPM_UI::field(
				array(
					'type'    => 'next_step' === $field ? 'text' : 'date',
					'id'      => self::field_id( $field ),
					'name'    => $field,
					'label'   => self::label( $field ),
					'value'   => $value( $field ),
					'problem' => isset( $problems[ $field ] ) ? $problems[ $field ] : '',
					'attrs'   => 'next_step' === $field ? array( 'maxlength' => self::MAX_NEXT_STEP ) : array(),
				)
			);
		}

		$parts['program_details'] = WPCPM_UI::field(
			array(
				'type'    => 'textarea',
				'id'      => self::field_id( 'program_details' ),
				'name'    => 'program_details',
				'label'   => self::label( 'program_details' ),
				'value'   => $value( 'program_details' ),
				'rows'    => 4,
				'hint'    => __( 'What this school asks for: hours, dates, reports. Only Administrators see it.', 'wpcredits-program-manager' ),
				'problem' => isset( $problems['program_details'] ) ? $problems['program_details'] : '',
				'attrs'   => array( 'maxlength' => self::MAX_PROGRAM_DETAILS ),
			)
		);

		// The stored link, when it is one, opens from a text link under its box, the field's hint line, in a tab of its own
		// as the dashboards' other outside links open (1.123.104, rounds 4 and 5): a text box cannot be followed.
		$desk = trim( (string) $facts['help_desk'] );
		$open = '' !== $desk && null === WPCPM_Institution_Leads::help_desk_problem( $desk )
			? array(
				'href'    => $desk,
				'label'   => __( 'Open the help desk link', 'wpcredits-program-manager' ),
				'outside' => true,
			)
			: array();

		// A lead's own fields; the help desk link a school's too (round 2).
		$own = $lead
			? array(
				'city'           => 'text',
				'contact_person' => 'text',
				'contact_email'  => 'email',
				'help_desk'      => 'url',
			)
			: array( 'help_desk' => 'url' );

		foreach ( $own as $field => $type ) {
			$parts[ $field ] = WPCPM_UI::field(
				array(
					'type'    => 'url' === $type ? 'text' : $type,
					'id'      => self::field_id( $field ),
					'name'    => $field,
					'label'   => self::label( $field ),
					'value'   => $value( $field ),
					'problem' => isset( $problems[ $field ] ) ? $problems[ $field ] : '',
					'after'   => 'help_desk' === $field ? $open : array(),
					'attrs'   => array(
						'maxlength' => 'city' === $field ? WPCPM_Institution_Leads::MAX_LENGTHS['city'] : ( 'contact_person' === $field ? 1000 : ( 'contact_email' === $field ? 200 : 500 ) ),
						'inputmode' => 'url' === $type ? 'url' : '',
					),
				)
			);
		}

		// A lead whose move into the talks did not finish says so, until a save of a stage finishes it (W5): in the place
		// and wrapper of the outcome, the form's first block, so it stands on the outcome's rhythm (the final round, F1).
		$unfinished = $standing && $lead && WPCPM_Institution_Leads::unfinished( $key )
			? WPCPM_Dashboard_Card::message( 'warning', WPCPM_Institution_Leads::unfinished_sentence(), 'wpcpm-talks__message' )
			: '';

		$out = sprintf(
			'<form method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		)
			. WPCPM_UI::nonce( self::ACTION_SAVE . '_' . $key )
			. WPCPM_Institution_Details_Card::hidden_html( $hidden )
			// The outcome is the form's first block, directly over its fields, and on the rhythm after the name.
			. self::outcome_html( $flash, $first )
			. $unfinished;

		foreach ( self::editable( $facts ) as $field ) {
			$out .= isset( $parts[ $field ] ) ? $parts[ $field ] : '';
		}

		$buttons = array(
			WPCPM_UI::button(
				array(
					'label' => __( 'Save', 'wpcredits-program-manager' ),
					'side'  => 'front',
				)
			),
		);

		// A lead's close and reopen are presses of their own, through this form, their action and nonce their own.
		if ( $lead ) {
			$action = empty( $facts['closed'] ) ? self::ACTION_CLOSE : self::ACTION_REOPEN;

			$out      .= WPCPM_UI::nonce( $action . '_' . $key, self::LEAD_NONCE, false );
			$buttons[] = WPCPM_UI::button(
				array(
					'label' => empty( $facts['closed'] ) ? __( 'Close this lead', 'wpcredits-program-manager' ) : __( 'Reopen this lead', 'wpcredits-program-manager' ),
					'kind'  => 'quiet',
					'name'  => 'action',
					'value' => $action,
					'side'  => 'front',
				)
			);
		}

		// A closed lead can be deleted (X4): the last press of the row, in the quiet red the People card removes a person
		// with, submitting its own small form after this one, which carries the confirmation once the question is asked.
		$delete = '';

		if ( $lead && ! empty( $facts['closed'] ) ) {
			$buttons[] = WPCPM_UI::button(
				array(
					'label' => __( 'Delete this lead', 'wpcredits-program-manager' ),
					'kind'  => 'quiet-danger',
					'form'  => self::DELETE_FORM,
					'side'  => 'front',
				)
			);

			$fields = array(
				'action' => self::ACTION_DELETE,
				'key'    => $key,
			);

			if ( null !== $flash && 'delete-ask' === $flash['status'] && '' !== $flash['delete'] ) {
				$fields['confirm'] = $flash['delete'];
			}

			$delete = sprintf(
				'<form id="%1$s" method="post" action="%2$s" novalidate hidden data-wpcpm-once data-wpcpm-busy="%3$s">',
				esc_attr( self::DELETE_FORM ),
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Deleting', 'wpcredits-program-manager' )
			)
				. WPCPM_UI::nonce( self::ACTION_DELETE . '_' . $key, '_wpnonce', false )
				. WPCPM_Institution_Details_Card::hidden_html( $fields )
				. '</form>';
		}

		return $out . WPCPM_UI::actions( $buttons ) . '</form>' . $delete;
	}

	/**
	 * A field's box id.
	 *
	 * @param string $field A field of `editable()`.
	 * @return string
	 */
	private static function field_id( $field ) {
		return 'wpcpm-talks-' . str_replace( '_', '-', (string) $field );
	}

	/**
	 * The assigned Administrator, the one field to type and pick: Nobody, then every Administrator. One stored who is no
	 * Administrator now is offered as their label says, so a save that does not touch the field keeps them.
	 *
	 * @param array  $facts   `facts()`.
	 * @param array  $kept    What a refused post kept.
	 * @param string $problem The field's refusal, or ''.
	 * @return string Escaped markup.
	 */
	private static function assigned_field( array $facts, array $kept, $problem ) {
		$stored  = (int) $facts['assigned'];
		$options = array( 0 => __( 'Nobody', 'wpcredits-program-manager' ) ) + WPCPM_Institution_Assignment::choices();

		if ( $stored > 0 && ! isset( $options[ $stored ] ) ) {
			$options[ $stored ] = WPCPM_Institution_Assignment::label( $stored );
		}

		return WPCPM_UI::field(
			array(
				'type'    => 'combo',
				'id'      => 'wpcpm-talks-assigned',
				'name'    => self::FIELD_ASSIGNED,
				'label'   => self::label( 'assigned' ),
				'value'   => array_key_exists( 'assigned', $kept ) && is_scalar( $kept['assigned'] ) ? (string) $kept['assigned'] : (string) $stored,
				'options' => $options,
				'problem' => $problem,
				'find'    => __( 'Type to find an Administrator', 'wpcredits-program-manager' ),
				'none'    => __( 'No Administrator has that name.', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * What the card shows and does not edit, under its heading: an institution's contact person and their email, which
	 * the details card changes (and the sentence that says where); the lead owner when the import gave one. "Not
	 * recorded" in words, of the empty kind, for none. Nothing at all for a lead with no lead owner. Last contact and the
	 * help desk link are the form's since round 2 of 1.123.104.
	 *
	 * @param array $facts `facts()`.
	 * @return string Escaped markup.
	 */
	private static function record_html( array $facts ) {
		$lead  = ! empty( $facts['lead'] );
		$rows  = array();
		$label = array(
			'key'    => 'label',
			'label'  => '',
			'kind'   => 'text',
			'header' => true,
		);
		$text  = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'text',
		);
		$token = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'token',
		);

		if ( ! $lead ) {
			$rows[] = array( __( 'Contact person', 'wpcredits-program-manager' ), (string) $facts['contact_person'], 'text' );
			$rows[] = array( __( 'Contact\'s email', 'wpcredits-program-manager' ), (string) $facts['contact_email'], 'token' );
		}

		if ( '' !== trim( (string) $facts['lead_owner'] ) ) {
			$rows[] = array( __( 'Lead owner', 'wpcredits-program-manager' ), (string) $facts['lead_owner'], 'text' );
		}

		if ( array() === $rows ) {
			return '';
		}

		$heading = 'wpcpm-talks-record-heading';
		$out     = WPCPM_UI::heading( __( 'On record', 'wpcredits-program-manager' ), 'h4', $heading )
			. WPCPM_UI::table_open(
				array( $label, $text ),
				array(
					'keyvalue'   => true,
					'labelledby' => $heading,
				)
			);

		foreach ( $rows as $row ) {
			list( $words, $shown, $kind ) = $row;

			if ( '' === trim( $shown ) ) {
				$cell = WPCPM_UI::cell( $text, esc_html__( 'Not recorded', 'wpcredits-program-manager' ), 'empty' );
			} else {
				$cell = WPCPM_UI::cell( 'text' === $kind ? $text : $token, esc_html( $shown ) );
			}

			$out .= WPCPM_UI::row_open() . WPCPM_UI::cell( $label, esc_html( $words ) ) . $cell . WPCPM_UI::row_close();
		}

		$out .= WPCPM_UI::table_close();

		if ( ! $lead ) {
			$details = WPCPM_Institutions_Dashboard::card_url( 'details', (string) $facts['key'] );
			$out    .= WPCPM_UI::intro(
				'',
				'',
				'',
				sprintf(
					/* translators: 1: the opening link tag to the card that holds the school's details, 2: the closing link tag. */
					esc_html__( 'Change the contact person and their email in %1$sYour institution\'s details%2$s, on the Your institution view.', 'wpcredits-program-manager' ),
					'' !== $details ? '<a href="' . esc_url( $details ) . '">' : '',
					'' !== $details ? '</a>' : ''
				)
			);
		}

		return $out;
	}

	/**
	 * The notes, newest first, never edited, only added: a list of rows, each its author as the name, its moment whole as
	 * the fact and the note as written under them; "No notes yet." when there is none. Then Add a note, a form of its own, its outcome directly over
	 * it, its way back landing on it.
	 *
	 * @param array      $facts `facts()`.
	 * @param array|null $flash The note form's outcome, or null.
	 * @param array      $kept  What a refused note kept.
	 * @return string Escaped markup.
	 */
	private static function notes_html( array $facts, $flash, array $kept ) {
		$key   = (string) $facts['key'];
		$notes = WPCPM_Institution_Talk_Notes::for_key( $key );
		$out   = WPCPM_UI::heading( __( 'Notes', 'wpcredits-program-manager' ), 'h4' );

		if ( array() === $notes ) {
			$out .= WPCPM_UI::list_empty( __( 'No notes yet.', 'wpcredits-program-manager' ) );
		} else {
			$out .= WPCPM_UI::list_open( array( 'facts' => 'under' ) );

			foreach ( $notes as $note ) {
				$time   = strtotime( $note['date'] . ' UTC' );
				$moment = false !== $time ? WPCPM_Dates::moment( $time ) : '';
				$facts  = '' !== $moment ? array( array( 'html' => '<time datetime="' . esc_attr( gmdate( 'c', $time ) ) . '">' . WPCPM_UI::whole( $moment ) . '</time>' ) ) : array();
				$author = trim( (string) $note['author'] );

				// Who wrote it is the row's name, the moment its fact, and the words follow in the note's regular weight (U7).
				$out .= WPCPM_UI::row(
					array(
						'name'  => '' !== $author ? $author : $moment,
						'facts' => '' !== $author ? $facts : array(),
						'more'  => WPCPM_UI::note( (string) $note['text'] ),
					)
				);
			}

			$out .= WPCPM_UI::list_close();
		}

		$problem = null !== $flash && isset( $flash['problems']['note'] ) ? $flash['problems']['note'] : '';

		return $out
			. '<div id="' . esc_attr( self::NOTE_ID ) . '">'
			. self::outcome_html( $flash, '' !== $problem ? 'wpcpm-talks-note-text' : '' )
			. sprintf(
				'<form method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Adding', 'wpcredits-program-manager' )
			)
			. WPCPM_UI::nonce( self::ACTION_NOTE . '_' . $key )
			. WPCPM_Institution_Details_Card::hidden_html(
				array(
					'action' => self::ACTION_NOTE,
					'key'    => $key,
				)
			)
			. WPCPM_UI::field(
				array(
					'type'    => 'textarea',
					'id'      => 'wpcpm-talks-note-text',
					'name'    => 'note',
					'label'   => __( 'Add a note', 'wpcredits-program-manager' ),
					'value'   => isset( $kept['note'] ) && is_scalar( $kept['note'] ) ? (string) $kept['note'] : '',
					'rows'    => 4,
					'problem' => $problem,
					'attrs'   => array( 'maxlength' => WPCPM_Institution_Talk_Notes::MAX_LENGTH ),
				)
			)
			. WPCPM_UI::actions(
				array(
					WPCPM_UI::button(
						array(
							'label' => __( 'Add the note', 'wpcredits-program-manager' ),
							'side'  => 'front',
						)
					),
				)
			)
			. '</form></div>';
	}
}
