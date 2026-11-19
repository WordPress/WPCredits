<?php
/**
 * Institutions module - leads, the schools the program has not talked to yet (Institutions off Airtable,
 * release 2).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per lead: a school found, imported from the Outreach Tracker or added by an Administrator,
 * that the program has not talked to yet.
 *
 * **A store of its own until it is promoted.** Every institution is keyed by its Airtable record ID, and a
 * lead is never written to Airtable, so a lead has no record ID: it is keyed `lead-` and its post's ID
 * (`key()`, the shape `WPCPM_Institution_Talks::is_lead_key()` reads). It is never in the institutions'
 * index, so it is never counted, never on a public page, never offered by a form and it has no accounts.
 * `promote()` takes its record ID from Airtable's create, and only then does it join the index, at the stage
 * the talks are at; a create Airtable refuses leaves a lead a lead.
 *
 * **What a lead holds** (`FIELDS`, in one post meta, `META_FIELDS`): its name, city and country, one contact
 * person and one contact address (the two columns an institution has; an Outreach Tracker cell naming several
 * people is kept whole as the contact person), the help desk link, the lead owner as the Outreach Tracker
 * named them, the last contact, the assigned Administrator and the talks: estimated students, the day the
 * first students begin, the next step and the follow-up date. A country is a Countries record ID, or, when
 * the name it came with could not be resolved, that name as text (`country_text`), never both.
 *
 * **Closed, not moved to a closing stage (PROVISIONAL, owner question 3).** A lead that goes nowhere is closed
 * (`close()`) and can be reopened (`reopen()`); it keeps everything, is never written to Airtable, and the
 * readers that list the talks leave it out of the stage groups, the follow-ups due and the weekly email, and
 * Find finds it, marked closed. `promote()` is only into a stage where the talks go on.
 *
 * **Its history** is written under the lead's own key (`WPCPM_Institution_Audit::record_lead()`), which no
 * institution reader lists, and becomes the record's at promotion, with its notes
 * (`WPCPM_Institution_Talk_Notes::rekey()`).
 *
 * Private everywhere: no public URL, no admin list, no REST route, never in WordPress's export file; its contact is
 * in WordPress's personal data export and erase tools (`export()`, `erase()`). A lead names a contact
 * person and an address, so the privacy guard holds the type to `private` and uninstall deletes every lead
 * (`delete_all()`) and sweeps the locks (`LOCK_PREFIX`). Nothing is autoloaded: the class writes no option.
 *
 * All static, in the shape of `WPCPM_Institution_Talk_Notes`.
 */
class WPCPM_Institution_Leads {

	/** One post per lead. */
	const POST_TYPE = 'wpcpm_inst_lead';

	/** Private, like every post type in this module. */
	const POST_STATUS = 'private';

	/** Post meta: the lead's fields, one array (`FIELDS`). */
	const META_FIELDS = '_wpcpm_lead_fields';

	/** Post meta: where an imported lead came from, as the import names it. */
	const META_SOURCE = '_wpcpm_lead_source';

	/**
	 * Post meta: the record ID Airtable made for the lead, kept the moment the create answers, so a promotion
	 * a request did not finish goes on with that record and never asks Airtable for a second one.
	 */
	const META_PROMOTED = '_wpcpm_lead_promoted';

	/** Post meta: the stage that promotion was asked for, so a resumed one is at the same stage as Airtable's record. */
	const META_PROMOTED_STAGE = '_wpcpm_lead_promoted_stage';

	/** The lock a lead is promoted under: the prefix, then the post's ID. */
	const LOCK_PREFIX = 'wpcpm_lead_lock_';

	/** How long a promotion may hold its lead's lock, in seconds: Airtable's create and the writes after it. */
	const LOCK_TTL = 60;

	/** The one lock leads are added under, so two leads of one name are never added at once. Swept with the prefix. */
	const LOCK_ADD = 'wpcpm_lead_lock_add';

	/** How long the adding lock lives, in seconds: an add (or a rename) is one name check and one write. */
	const ADD_LOCK_TTL = 10;

	/** How many times an add asks for its lock, and how long apart (microseconds), before it gives up. */
	const ADD_TRIES = 10;
	const ADD_PAUSE = 100000;

	/** A lead's stage, the talks' own name for it. */
	const STAGE = WPCPM_Institution_Talks::STAGE_LEAD;

	/** What a lead key starts with, the talks' own prefix. */
	const KEY_PREFIX = WPCPM_Institution_Talks::LEAD_PREFIX;

	/** The record's key a promoted lead's source is kept under, so `by_source()` still finds the school. */
	const RECORD_SOURCE = 'lead_source';

	/** The agreement's status a promoted lead's index row starts with, as an approved application's does. */
	const AGREEMENT_START = 'Not started';

	/**
	 * Every field a lead keeps, with the value it has when none is given. `assigned` is a user ID; `closed` is
	 * the day it was closed (`Y-m-d`), '' while it is open; `country_name` is the resolved name of `country`.
	 */
	const FIELDS = array(
		'name'               => '',
		'city'               => '',
		'country'            => '',
		'country_name'       => '',
		'country_text'       => '',
		'contact_person'     => '',
		'contact_email'      => '',
		'help_desk'          => '',
		'lead_owner'         => '',
		'last_contact'       => '',
		'assigned'           => 0,
		'estimated_students' => '',
		'first_students'     => '',
		'next_step'          => '',
		'follow_up'          => '',
		'program_details'    => '',
		'closed'             => '',
	);

	/** The fields a caller may give: every field but the ones the class keeps itself. */
	const GIVEN = array( 'name', 'city', 'country', 'country_text', 'contact_person', 'contact_email', 'help_desk', 'lead_owner', 'last_contact', 'assigned', 'estimated_students', 'first_students', 'next_step', 'follow_up', 'program_details' );

	/** The longest value of each text field, in characters. */
	const MAX_LENGTHS = array(
		'name'            => 200,
		'city'            => 200,
		'country_text'    => 200,
		'contact_person'  => 1000,
		'lead_owner'      => 200,
		'next_step'       => 200,
		'program_details' => WPCPM_Institution_Talks::MAX_PROGRAM_DETAILS,
	);

	/** The fields that are a date (`Y-m-d`) or ''. */
	const DATES = array( 'last_contact', 'first_students', 'follow_up' );

	/** The most students a school can be estimated to send. */
	const MAX_STUDENTS = 100000;

	/** The talks keys a promoted lead's record keeps, beside `help_desk`, `lead_owner` and `assigned`. */
	const RECORD_FIELDS = array( 'estimated_students', 'first_students', 'next_step', 'follow_up', 'last_contact', 'help_desk', 'lead_owner', 'program_details' );

	/** The copy's columns a promoted lead sends after the create, which carries none of them (estimated students and the help desk link since the outside review, O9). */
	const COPY_AFTER = array( 'next_step', 'follow_up', 'last_contact', 'estimated_students', 'help_desk' );

	/** Every post status a lead can have, an unfinished draft among them, for `delete_all()`. */
	const POST_STATUSES = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );

	/**
	 * The status a lead is written in until every meta of it is (Task 18 fix round 1): a lead is committed by its
	 * status, the one update that moves it to `POST_STATUS` coming last, so a request killed half way leaves a draft
	 * that no reader lists, `by_source()` and `name_taken()` do not find, and the next add writes a whole one.
	 */
	const UNFINISHED_STATUS = 'draft';

	/** The statuses `by_source()` finds a lead in: every one but an unfinished draft and an auto-draft. */
	const FOUND_STATUSES = array( 'publish', 'private', 'pending', 'future', 'trash' );

	/** The statuses a lead is read in: every one but the trash, an unfinished draft and an auto-draft. */
	const READ_STATUSES = array( 'publish', 'private', 'pending', 'future' );

	/** The Talks view's Add a lead (Task 16): its admin-post action. */
	const ACTION_ADD = 'wpcpm_talks_add_lead';

	/** The flash channel a refused Add a lead says why on. */
	const ADD_FLASH = 'institution_talks_add';

	/** Add a lead's form, as `WPCPM_Typed_Text::keep()` names it. */
	const ADD_FORM = 'talks-add';

	/** Add a lead's anchor on the Talks view: its card's section. */
	const ADD_ANCHOR = 'wpcpm-add-lead';

	/** Who added a lead (a user ID), so a second press of Add the lead by the same account is known (Task 16 fix round 1, G4). */
	const META_ADDED_BY = '_wpcpm_lead_added_by';

	/** How long, in seconds, a second press of Add the lead with the same name lands on the lead the first one made. */
	const ADD_AGAIN = 60;

	/** The record key a promoted lead's record keeps its old lead key under, so the lead's old link finds the school (G1). */
	const RECORD_LEAD_KEY = 'lead_key';

	/** The group WordPress's personal data tools file a lead's contact under, and its exporter's and eraser's key (X3). */
	const PRIVACY_GROUP = 'wpcpm-institution-leads';

	/** How many leads one page of the personal data tools handles. */
	const PRIVACY_PAGE = 50;

	/** The fields kept on one line, every run of whitespace one space (`one_line()`). */
	const ONE_LINE = array( 'name', 'city', 'contact_person', 'next_step' );

	/** The fields Add a lead posts, in the order it draws them, the note aside. */
	const ADD_FIELDS = array( 'name', 'city', 'country', 'contact_person', 'contact_email', 'help_desk', 'next_step', 'follow_up' );

	/**
	 * Hooks. Called from `WPCPM_Institutions::boot()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_ADD, array( __CLASS__, 'handle_add' ) );

		// WordPress's Export and Erase Personal Data tools, in both modes: no lead exists while the setting is off (X3).
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );

		// A lead or a note brought back from the Trash comes back as it was, not as a draft no reader sees (round 2, L4).
		add_filter( 'wp_untrash_post_status', array( __CLASS__, 'untrash_status' ), 10, 3 );

		// A lead brought back from the Trash brings its notes, which its delete moved there with it (the outside review, O10).
		add_action( 'untrashed_post', array( __CLASS__, 'untrashed' ) );
	}

	/**
	 * When a lead is untrashed (`untrashed_post`), its notes in the Trash are untrashed too, each back to the status it
	 * had (`WPCPM_Institution_Talk_Notes::untrash_for()`), so a lead deleted and brought back (`wp post untrash`) is in
	 * the talks with its notes (the outside review of release 2, O10). Any other post is left alone.
	 *
	 * @param int $post_id The post untrashed.
	 */
	public static function untrashed( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		WPCPM_Institution_Talk_Notes::untrash_for( self::key( (int) $post_id ) );
	}

	/**
	 * The status a lead or a note untrashed comes back in: the one it had before it was trashed, so a lead deleted and
	 * untrashed (`wp post untrash`; the plugin offers no screen for it) is back in the talks as it was, a closed one
	 * closed, with its notes. Since WordPress 5.6 an untrashed post is a draft otherwise. Other post types are left to
	 * WordPress.
	 *
	 * @param string $new_status      The status WordPress would give.
	 * @param int    $post_id         The post.
	 * @param string $previous_status The status it had before it was trashed.
	 * @return string
	 */
	public static function untrash_status( $new_status, $post_id, $previous_status ) {
		$post = get_post( (int) $post_id );
		$ours = $post instanceof WP_Post && in_array( $post->post_type, array( self::POST_TYPE, WPCPM_Institution_Talk_Notes::POST_TYPE ), true );

		return $ours && in_array( (string) $previous_status, self::READ_STATUSES, true ) ? (string) $previous_status : $new_status;
	}

	/*
	 * The personal data tools (the owner's wave of release 2, X3)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The leads' entry in WordPress's Export Personal Data tool.
	 *
	 * @param array $exporters WordPress's exporters.
	 * @return array The same, with the leads'.
	 */
	public static function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters[ self::PRIVACY_GROUP ] = array(
			'exporter_friendly_name' => __( 'Institution leads', 'wpcredits-program-manager' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * The leads' entry in WordPress's Erase Personal Data tool.
	 *
	 * @param array $erasers WordPress's erasers.
	 * @return array The same, with the leads'.
	 */
	public static function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers[ self::PRIVACY_GROUP ] = array(
			'eraser_friendly_name' => __( 'Institution leads', 'wpcredits-program-manager' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Every lead whose contact email is this address, case ignored, whatever its status: closed, in the Trash, a draft
	 * a request did not finish.
	 *
	 * @param string $email The address.
	 * @return int[] The leads' posts, oldest first.
	 */
	public static function for_address( $email ) {
		$asked = is_scalar( $email ) ? strtolower( trim( (string) $email ) ) : '';
		$found = array();

		if ( '' === $asked ) {
			return $found;
		}

		foreach ( self::ids( self::POST_STATUSES ) as $post_id ) {
			$fields = self::fields_of( $post_id );

			if ( strtolower( trim( $fields['contact_email'] ) ) === $asked ) {
				$found[] = $post_id;
			}
		}

		return $found;
	}

	/**
	 * WordPress's Export Personal Data tool: one item for each lead whose contact is this address, its institution, its
	 * contact person and the address. Nothing of the notes, the Program details or the talks.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1.
	 * @return array{data:array,done:bool}
	 */
	public static function export( $email, $page = 1 ) {
		$page = max( 1, (int) $page );
		$all  = self::for_address( $email );
		$data = array();

		foreach ( array_slice( $all, ( $page - 1 ) * self::PRIVACY_PAGE, self::PRIVACY_PAGE ) as $post_id ) {
			$fields = self::fields_of( $post_id );
			$data[] = array(
				'group_id'    => self::PRIVACY_GROUP,
				'group_label' => __( 'Institution leads', 'wpcredits-program-manager' ),
				'item_id'     => self::PRIVACY_GROUP . '-' . (int) $post_id,
				'data'        => array(
					array(
						'name'  => __( 'Institution', 'wpcredits-program-manager' ),
						'value' => esc_html( trim( $fields['name'] ) ),
					),
					array(
						'name'  => __( 'Contact person', 'wpcredits-program-manager' ),
						'value' => esc_html( trim( $fields['contact_person'] ) ),
					),
					array(
						'name'  => __( 'Contact\'s email', 'wpcredits-program-manager' ),
						'value' => esc_html( trim( $fields['contact_email'] ) ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => $page * self::PRIVACY_PAGE >= count( $all ),
		);
	}

	/**
	 * WordPress's Erase Personal Data tool: for each lead whose contact is this address, the contact person and the
	 * address are blanked; the lead, its talks and its notes stay. A lead with notes says so, since a note may name the
	 * person. No history row is written: one would only say again whom it was about. One call blanks `PRIVACY_PAGE` of
	 * them at most and says whether more are left, so WordPress calls again; a blanked lead is not found again.
	 *
	 * @param string $email The address.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public static function erase( $email ) {
		$removed  = 0;
		$retained = 0;
		$messages = array();
		$more     = false;

		foreach ( self::for_address( $email ) as $post_id ) {
			if ( $removed >= self::PRIVACY_PAGE ) {
				$more = true;
				break;
			}

			// WordPress's privacy screen builds the messages as HTML, and a name keeps what was typed (round 2, L1).
			$name = esc_html( trim( self::fields_of( $post_id )['name'] ) );

			// Under the lead's lock, as a save, a close and a delete write its fields (round 2, L3): a lead another
			// request holds, or whose write is refused, is left whole, counted as retained and said, and the erasure goes
			// on, so it never asks again for ever.
			if ( ! self::blank_contact( $post_id ) ) {
				++$retained;
				$messages[] = sprintf(
					/* translators: %s: the lead's name. */
					__( 'The lead %s was being saved and was left as it is. Run the erasure again.', 'wpcredits-program-manager' ),
					$name
				);

				continue;
			}

			++$removed;

			if ( WPCPM_Institution_Talk_Notes::count_for( self::key( $post_id ) ) > 0 ) {
				++$retained;
				$messages[] = sprintf(
					/* translators: %s: the lead's name. */
					__( 'The lead %s keeps its notes, which may name this person. A note cannot be changed: to remove them, close and delete the lead.', 'wpcredits-program-manager' ),
					$name
				);
			}
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained > 0,
			'messages'       => $messages,
			'done'           => ! $more,
		);
	}

	/**
	 * Blank a lead's contact person and contact email, under the lead's lock, the fields read again under it.
	 *
	 * @param int $post_id The lead's post.
	 * @return bool False when the lock is held or the write is refused: nothing was blanked.
	 */
	private static function blank_contact( $post_id ) {
		$lock  = self::LOCK_PREFIX . (int) $post_id;
		$token = WPCPM_Lock::take( $lock, self::LOCK_TTL );

		if ( '' === $token ) {
			return false;
		}

		try {
			self::forget_cached( $post_id );

			$fields                   = self::fields_of( $post_id );
			$fields['contact_person'] = '';
			$fields['contact_email']  = '';

			return false !== update_post_meta( $post_id, self::META_FIELDS, wp_slash( $fields ) );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Add a lead from the Talks view's Add a lead (Task 16): the nonce, then an Administrator in site mode, else the
	 * policy's one refusal (403) and nothing written. A refusal of `add()` stands over the form, linked to its box, the
	 * typing kept; a lead made lands on its page (`WPCPM_Institution_Talks::land_added()`), with its first note when one
	 * was typed. A note refused leaves the lead made, and says so over Add a note. **This does not return.**
	 */
	public static function handle_add() {
		check_admin_referer( self::ACTION_ADD );

		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_TALKS, WPCPM_Institution_Policy::subject_institution( '' ) );

		if ( empty( $decision['allowed'] ) || ! WPCPM_Institutions_Index::on_site() ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$posted = array();

		// Read as the Talks card reads the same fields: verbatim, and cleaned by `add()`, so markup typed is kept whole and
		// escaped where it is drawn, never stripped (the whole-branch wave of release 2, W7).
		foreach ( self::ADD_FIELDS as $field ) {
			$posted[ $field ] = (string) WPCPM_Request::posted_verbatim( $field );
		}

		$note  = (string) WPCPM_Request::posted_verbatim( 'note' );
		$actor = get_current_user_id();

		WPCPM_Typed_Text::forget( self::ADD_FORM );

		$fields = array_filter(
			$posted,
			static function ( $value ) {
				return '' !== trim( $value );
			}
		);
		$key    = self::add( array_merge( array( 'name' => $posted['name'] ), $fields ), $actor );

		// A second press of the same form (no script to stop it): the lead the first press made, by this account, a moment
		// ago, is the one landed on, and nothing more is made.
		if ( is_wp_error( $key ) && 'wpcpm_lead_taken' === $key->get_error_code() ) {
			$again = self::added_just_now( self::name_taken( $posted['name'] ), $actor );

			if ( '' !== $again ) {
				WPCPM_Institution_Talks::land_added( $again );
			}
		}

		if ( is_wp_error( $key ) ) {
			$room = array();

			// Room for every character typed, so a value refused for its length comes back whole.
			foreach ( $posted as $field => $value ) {
				$room[ $field ] = WPCPM_Typed_Text::typed_room( $value, isset( self::MAX_LENGTHS[ $field ] ) ? self::MAX_LENGTHS[ $field ] : 500 );
			}

			// The raw typing, so its room is counted as typed: a typed entity comes back whole (1.123.102).
			$room['note'] = WPCPM_Typed_Text::typed_room( $note, WPCPM_Institution_Talk_Notes::MAX_LENGTH );

			WPCPM_Typed_Text::keep( self::ADD_FORM, array_merge( $posted, array( 'note' => $note ) ), $room );
			WPCPM_Flash::set(
				self::ADD_FLASH,
				array(
					'text'  => $key->get_error_message(),
					'field' => self::refused_field( $key, $posted ),
				)
			);

			$view = (string) WPCPM_Administrators_Dashboard::view_url( WPCPM_Institution_Talks::VIEW_TALKS );

			wp_safe_redirect( '' !== $view ? $view . '#' . self::ADD_ANCHOR : home_url( '/' ) );
			exit;
		}

		$problem = '';

		if ( '' !== trim( $note ) ) {
			$added   = WPCPM_Institution_Talk_Notes::add( $key, $note, $actor );
			$problem = is_wp_error( $added ) ? $added->get_error_message() : '';
		}

		WPCPM_Institution_Talks::land_added( $key, $problem, $note );
	}

	/**
	 * A lead this account added less than `ADD_AGAIN` seconds ago, by its key.
	 *
	 * @param string $key   A lead key, or anything else.
	 * @param int    $actor The account.
	 * @return string The key, or '' when it is no such lead.
	 */
	private static function added_just_now( $key, $actor ) {
		$post_id = self::is_key( $key ) ? self::post_of( $key ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post instanceof WP_Post || (int) $actor < 1 || (int) get_post_meta( $post_id, self::META_ADDED_BY, true ) !== (int) $actor ) {
			return '';
		}

		$when = strtotime( (string) $post->post_date_gmt . ' UTC' );

		return false !== $when && time() - $when <= self::ADD_AGAIN ? (string) $key : '';
	}

	/**
	 * The institution a promoted lead became, by the lead key its record keeps (`RECORD_LEAD_KEY`).
	 *
	 * @param string $key A lead key.
	 * @return string The record ID, or '' when no institution came from it.
	 */
	public static function promoted_to( $key ) {
		if ( ! self::is_key( $key ) ) {
			return '';
		}

		$ids = array_keys( WPCPM_Institutions_Index::rows() );

		foreach ( WPCPM_Institution_Record::answers_for( $ids ) as $record_id => $answers ) {
			if ( is_array( $answers ) && isset( $answers[ self::RECORD_LEAD_KEY ] ) && (string) $answers[ self::RECORD_LEAD_KEY ] === (string) $key ) {
				return (string) $record_id;
			}
		}

		return '';
	}

	/**
	 * The field of Add a lead a refusal of `add()` is about: the first posted field the same check refuses, the name for
	 * a name missing or taken, '' for a refusal of no field (busy, not kept).
	 *
	 * @param WP_Error $error  The refusal.
	 * @param array    $posted The fields as posted.
	 * @return string A key of `ADD_FIELDS`, or ''.
	 */
	private static function refused_field( WP_Error $error, array $posted ) {
		$code = $error->get_error_code();

		if ( in_array( $code, array( 'wpcpm_lead_name', 'wpcpm_lead_taken' ), true ) ) {
			return 'name';
		}

		foreach ( $posted as $field => $value ) {
			$checked = self::clean_one( $field, $value );

			if ( is_wp_error( $checked ) && $checked->get_error_code() === $code ) {
				return $field;
			}
		}

		return '';
	}

	/**
	 * Register the post type: invisible everywhere and never exported. The only way to read or write a lead
	 * is this class.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Leads', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Lead', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'supports'            => array( 'title' ),
				// A capability type nothing is granted, so no role can reach these through any generic
				// post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_inst_lead', 'wpcpm_inst_leads' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * A lead's key.
	 *
	 * @param int $post_id The lead's post.
	 * @return string '' for an ID that is none.
	 */
	public static function key( $post_id ) {
		$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;

		return $post_id > 0 ? self::KEY_PREFIX . $post_id : '';
	}

	/**
	 * Whether a value is a lead key.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	public static function is_key( $value ) {
		return WPCPM_Institution_Talks::is_lead_key( $value );
	}

	/**
	 * One lead.
	 *
	 * @param string $key A lead key.
	 * @return array|null `key`, every one of `FIELDS` (`assigned` an int, the rest strings), `source` and
	 *                    `promoted` (the record ID a promotion that did not finish was given, else ''). Null
	 *                    for a key of the wrong shape and a lead the site does not keep.
	 */
	public static function get( $key ) {
		$post_id = self::post_of( $key );

		return $post_id > 0 ? self::shape( $post_id ) : null;
	}

	/**
	 * Every lead, oldest first.
	 *
	 * @return array<string, array> Key => `get()`.
	 */
	public static function all() {
		$ids = self::ids( self::READ_STATUSES );

		if ( empty( $ids ) ) {
			return array();
		}

		update_meta_cache( 'post', $ids );

		$out = array();

		foreach ( $ids as $id ) {
			$out[ self::key( $id ) ] = self::shape( $id );
		}

		return $out;
	}

	/**
	 * The school an imported row became: the lead that came from it, or the record it was promoted into.
	 *
	 * @param string $source A source as `add()` was given it.
	 * @return string A lead key or a record ID; '' for none and for an empty source.
	 */
	public static function by_source( $source ) {
		$source = is_scalar( $source ) ? trim( (string) $source ) : '';

		if ( '' === $source ) {
			return '';
		}

		$found = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::FOUND_STATUSES,
				'meta_key'         => self::META_SOURCE,
				'meta_value'       => $source,
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);

		if ( ! empty( $found ) ) {
			return self::key( (int) reset( $found ) );
		}

		$records = array_keys( WPCPM_Institutions_Index::rows() );

		if ( empty( $records ) ) {
			return '';
		}

		foreach ( WPCPM_Institution_Record::answers_for( array_map( 'strval', $records ) ) as $record_id => $answers ) {
			if ( isset( $answers[ self::RECORD_SOURCE ] ) && is_scalar( $answers[ self::RECORD_SOURCE ] ) && (string) $answers[ self::RECORD_SOURCE ] === $source ) {
				return (string) $record_id;
			}
		}

		return '';
	}

	/**
	 * The school in the talks with this name: a lead's or an institution's, the space around it trimmed and
	 * the case ignored.
	 *
	 * @param string $name   A name.
	 * @param string $except A lead key not to count, the lead being renamed.
	 * @return string The key (a lead key or a record ID), or ''.
	 */
	public static function name_taken( $name, $except = '' ) {
		$wanted = self::fold( $name );

		if ( '' === $wanted ) {
			return '';
		}

		foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
			if ( is_array( $row ) && isset( $row['name'] ) && is_scalar( $row['name'] ) && self::fold( $row['name'] ) === $wanted ) {
				return (string) $record_id;
			}
		}

		foreach ( self::all() as $key => $lead ) {
			if ( $key !== $except && self::fold( $lead['name'] ) === $wanted ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * The leads an application to the program names (the owner's wave of release 2, X2): every lead the talks hold, a
	 * closed one too, whose name is the application's as `fold()` compares two, or whose contact email is the
	 * application's address, case ignored. A lead in the Trash, or one already moved into the talks (its post gone), is
	 * not one of them.
	 *
	 * @param string $name  The institution's name, as applied under.
	 * @param string $email The application's address.
	 * @return array<string, array> Key => `get()`, oldest first.
	 */
	public static function matching( $name, $email ) {
		$wanted  = self::fold( $name );
		$address = is_scalar( $email ) ? strtolower( trim( (string) $email ) ) : '';
		$out     = array();

		if ( '' === $wanted && '' === $address ) {
			return $out;
		}

		foreach ( self::all() as $key => $lead ) {
			$by_name    = '' !== $wanted && self::fold( $lead['name'] ) === $wanted;
			$by_address = '' !== $address && strtolower( trim( (string) $lead['contact_email'] ) ) === $address;

			if ( $by_name || $by_address ) {
				$out[ $key ] = $lead;
			}
		}

		return $out;
	}

	/**
	 * Add a lead.
	 *
	 * @param array $fields Any of `GIVEN`, and `source` (where an imported lead came from). `assigned` given
	 *                      is kept; otherwise the lead is assigned by its country's area
	 *                      (`WPCPM_Institution_Areas::administrator_for()`), to nobody when none holds it.
	 * @param int   $actor  Who adds it, 0 for the site.
	 * @param bool  $notify Whether to tell the assignee. The import passes false.
	 * @return string|WP_Error The lead's key, or why it was refused.
	 */
	public static function add( array $fields, $actor, $notify = true ) {
		$actor = (int) $actor;
		$clean = self::clean( array_merge( array( 'name' => '' ), $fields ), self::FIELDS );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		if ( ! array_key_exists( 'assigned', $fields ) ) {
			$clean['assigned'] = '' !== $clean['country'] ? (int) WPCPM_Institution_Areas::administrator_for( $clean['country'] ) : 0;
		}

		$token = self::take_add_lock();

		if ( '' === $token ) {
			return self::add_busy();
		}

		try {
			$key = self::insert_lead( $clean, $fields, $actor );
		} finally {
			WPCPM_Lock::release( self::LOCK_ADD, $token );
		}

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		if ( $notify && $clean['assigned'] > 0 && $clean['assigned'] !== $actor ) {
			self::tell( $clean['assigned'], $key, $clean );
		}

		return $key;
	}

	/**
	 * The name checked and the lead's post written, the adding lock held.
	 *
	 * @param array $clean  The fields, checked.
	 * @param array $fields The fields as given, for the source.
	 * @param int   $actor  Who adds it, 0 for the site.
	 * @return string|WP_Error The key, or the refusal.
	 */
	private static function insert_lead( array $clean, array $fields, $actor = 0 ) {
		// The index as the table holds it, so an institution added a moment ago is seen.
		WPCPM_Institutions_Index::forget_cached();

		if ( '' !== self::name_taken( $clean['name'] ) ) {
			return self::taken();
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => self::UNFINISHED_STATUS,
					'post_title'  => self::STAGE,
					'post_author' => 0,
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new WP_Error( 'wpcpm_lead_write', __( 'The lead could not be kept. Try again in a while.', 'wpcredits-program-manager' ) );
		}

		$post_id = (int) $post_id;
		$source  = isset( $fields['source'] ) && is_scalar( $fields['source'] ) ? trim( (string) $fields['source'] ) : '';

		// Slashed because core unslashes post meta on its way in, and a backslash in a name is a word. Every write is
		// checked before the commit (the outside review, O7): a refused one removes the unfinished draft and keeps
		// nothing, so no lead is shown without its name.
		$metas = array( self::META_FIELDS => wp_slash( $clean ) );

		if ( '' !== $source ) {
			$metas[ self::META_SOURCE ] = wp_slash( $source );
		}

		if ( (int) $actor > 0 ) {
			$metas[ self::META_ADDED_BY ] = (int) $actor;
		}

		foreach ( $metas as $meta_key => $value ) {
			if ( false === update_post_meta( $post_id, $meta_key, $value ) ) {
				wp_delete_post( $post_id, true );

				return new WP_Error( 'wpcpm_lead_write', __( 'The lead could not be kept. Try again in a while.', 'wpcredits-program-manager' ) );
			}
		}

		// The commit: every meta is written, so the lead may now be read.
		$committed = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => self::POST_STATUS,
			),
			true
		);

		if ( is_wp_error( $committed ) || ! $committed ) {
			return new WP_Error( 'wpcpm_lead_write', __( 'The lead could not be kept. Try again in a while.', 'wpcredits-program-manager' ) );
		}

		return self::key( $post_id );
	}

	/**
	 * The refusal of an add or a rename while another holds the adding lock.
	 *
	 * @return WP_Error
	 */
	private static function add_busy() {
		return new WP_Error( 'wpcpm_lead_busy', __( 'Another lead is being saved just now. Try again in a moment.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Take the adding lock, asking again a few times while another add holds it.
	 *
	 * @return string The token, or '' when it could not be taken.
	 */
	private static function take_add_lock() {
		for ( $tries = 1; ; ++$tries ) {
			$token = WPCPM_Lock::take( self::LOCK_ADD, self::ADD_LOCK_TTL );

			if ( '' !== $token || $tries >= self::ADD_TRIES ) {
				return $token;
			}

			usleep( self::ADD_PAUSE );
		}
	}

	/**
	 * Change a lead's fields. A change of the assigned Administrator writes an `assignment_changed` row and
	 * tells the new one (unless they made it); a change of any other field writes one `talks_saved` row
	 * naming the fields. Both under the lead's own key.
	 *
	 * @param string $key     A lead key.
	 * @param array  $changes Any of `GIVEN`; a country ID given clears the text, and a text given clears the ID.
	 * @param int    $actor   Who changes it, 0 for the site.
	 * @return bool|WP_Error True when it changed, false when nothing did, or why it was refused (nothing
	 *                       is then written).
	 */
	public static function update( $key, array $changes, $actor ) {
		$actor   = (int) $actor;
		$post_id = self::post_of( $key );

		if ( $post_id < 1 ) {
			return self::gone();
		}

		$key  = self::key( $post_id );
		$held = self::fields_of( $post_id );
		$next = self::clean( $changes, $held );

		if ( is_wp_error( $next ) ) {
			return $next;
		}

		$changed = array();

		foreach ( self::GIVEN as $field ) {
			if ( 'assigned' !== $field && 'country_text' !== $field && $next[ $field ] !== $held[ $field ] ) {
				$changed[] = $field;
			}
		}

		if ( $next['country_text'] !== $held['country_text'] && ! in_array( 'country', $changed, true ) ) {
			$changed[] = 'country';
		}

		$reassigned = $next['assigned'] !== $held['assigned'];

		if ( empty( $changed ) && ! $reassigned ) {
			return false;
		}

		if ( self::fold( $next['name'] ) !== self::fold( $held['name'] ) ) {
			// A rename is checked and written under the adding lock, so it cannot race an add of the same name.
			$token = self::take_add_lock();

			if ( '' === $token ) {
				return self::add_busy();
			}

			try {
				WPCPM_Institutions_Index::forget_cached();

				if ( '' !== self::name_taken( $next['name'], $key ) ) {
					return self::taken();
				}

				update_post_meta( $post_id, self::META_FIELDS, wp_slash( $next ) );
			} finally {
				WPCPM_Lock::release( self::LOCK_ADD, $token );
			}
		} else {
			update_post_meta( $post_id, self::META_FIELDS, wp_slash( $next ) );
		}

		if ( ! empty( $changed ) ) {
			self::log_saved( $key, $actor, self::changed_sentence( $changed ), $changed );
		}

		if ( $reassigned ) {
			WPCPM_Institution_Audit::record_lead(
				array(
					'kind'     => WPCPM_Institution_Assignment::LOG_CHANGED,
					'lead'     => $key,
					'subject'  => $key,
					'actor'    => $actor,
					'ground'   => self::ground( $actor ),
					'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'  => $next['assigned'] > 0
						? sprintf(
							/* translators: %s: an Administrator's name. */
							__( '%s is now the assigned Administrator.', 'wpcredits-program-manager' ),
							WPCPM_Institution_Assignment::label( $next['assigned'] )
						)
						: __( 'Nobody is assigned now.', 'wpcredits-program-manager' ),
					'data'     => array(
						'from' => $held['assigned'],
						'to'   => $next['assigned'],
					),
				)
			);

			if ( $next['assigned'] > 0 && $next['assigned'] !== $actor ) {
				self::tell( $next['assigned'], $key, $next );
			}
		}

		return true;
	}

	/**
	 * Close a lead that goes nowhere. It keeps everything and can be reopened (PROVISIONAL, owner question 3).
	 *
	 * @param string $key   A lead key.
	 * @param int    $actor Who closes it.
	 * @return bool|WP_Error True when it closed, false when it was closed already, or the refusal.
	 */
	public static function close( $key, $actor ) {
		return self::set_closed( $key, $actor, true );
	}

	/**
	 * Reopen a closed lead.
	 *
	 * @param string $key   A lead key.
	 * @param int    $actor Who reopens it.
	 * @return bool|WP_Error True when it reopened, false when it was open, or the refusal.
	 */
	public static function reopen( $key, $actor ) {
		return self::set_closed( $key, $actor, false );
	}

	/**
	 * Promote a lead into the talks: Airtable makes its record, and the record ID is its key from then on.
	 *
	 * Only in site mode (`WPCPM_Institutions_Index::on_site()`), refused before any request otherwise: before
	 * the switch the sync rewrites the index from Airtable and the site's talks would not hold.
	 *
	 * In this order, under the lead's lock: the name checked again against the institutions and the other
	 * leads; Airtable's create (`WPCPM_Institution_Copy::create()`), its record ID and the stage kept on the
	 * lead at once; the index row at the stage (`insert()`, under the index's lock); the record (the talks
	 * keys, the help desk link, the lead owner, the assignment and the source, each only where the record
	 * holds none); the notes and the history rows given to the record; one `talks_stage` row (from Lead); the
	 * copy asked for the talks columns the create does not carry (it sends only in site mode); the lead
	 * deleted. Nobody is emailed: the assignee was told when the lead was assigned.
	 *
	 * **A promotion that stops goes on.** Once the record ID is kept on the lead, the next call goes on with
	 * that record at the stage first asked for, whatever stage it is handed, and never creates a second; an
	 * index that already holds the row (a call that stopped after the insert) is left as it is, the name is not
	 * checked again, and the stage row from Lead is written only when the record has none yet. An index whose lock cannot be taken is an error, and nothing is deleted.
	 *
	 * **What the site cannot prevent.** A request that dies between Airtable's answer and keeping the record
	 * ID, or a create that times out after Airtable made the record, leaves a record the site never heard of;
	 * the next try then creates a second one. The site cannot look for it, since in site mode it reads nothing
	 * of the Institutions table, so a failed or timed-out create is logged with the lead's key (and never its
	 * name or address) for an Administrator to look in Airtable.
	 *
	 * @param string $key   A lead key.
	 * @param string $stage A stage before Confirmed (First Contact Made to Agreement Sent): not Confirmed or
	 *                      Student, which the agreement leads to, and none of the three that close the
	 *                      talks (a lead that goes nowhere is closed instead).
	 * @param int    $actor Who promotes it.
	 * @return string|WP_Error The record ID, or why the lead is still a lead.
	 */
	public static function promote( $key, $stage, $actor ) {
		if ( ! WPCPM_Institutions_Index::on_site() ) {
			return new WP_Error( 'wpcpm_lead_not_on_site', __( 'A lead is moved into the talks only once the site keeps the record of institutions.', 'wpcredits-program-manager' ) );
		}

		$actor   = (int) $actor;
		$stage   = is_scalar( $stage ) ? trim( (string) $stage ) : '';
		$post_id = self::post_of( $key );

		if ( $post_id < 1 ) {
			return self::gone();
		}

		$lock  = self::LOCK_PREFIX . $post_id;
		$token = WPCPM_Lock::take( $lock, self::LOCK_TTL );

		if ( '' === $token ) {
			return self::lead_busy();
		}

		try {
			self::forget_cached( $post_id );

			return self::promote_held( $post_id, $stage, $actor );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Delete a closed lead (the owner's wave of release 2, X4): the lead and each of its notes go to WordPress's Trash
	 * (`wp_trash_post()`, which keeps each post's status to restore), under the lead's lock; nothing is deleted for good
	 * here, WordPress empties its Trash in its own time, and nothing is sent to Airtable. In the Trash a lead is in no
	 * list, count or search and its name is free again; `by_source()` still finds it, so an import does not bring it back.
	 *
	 * **Two presses.** The first, without the confirmation, deletes nothing and answers the question and the confirmation
	 * (`delete_token()`) its second press posts; the confirmation is of the lead as it is now, its fields, its notes and
	 * its history, so any other action on the lead uses it up and the next press asks again.
	 *
	 * @param string $key     A lead key.
	 * @param int    $actor   Who deletes it.
	 * @param string $confirm The confirmation the question gave, '' for none.
	 * @return array|WP_Error `deleted` (bool), and `name` once deleted, or `question` and `confirm` when it asks; the
	 *                        refusal, nothing written: a lead that is not closed (`wpcpm_lead_open`), a move into the
	 *                        talks that did not finish (`wpcpm_lead_unfinished`), the lead's lock held (`wpcpm_lead_busy`),
	 *                        a lead the site does not keep (`wpcpm_lead_gone`).
	 */
	public static function trash( $key, $actor, $confirm = '' ) {
		$post_id = self::post_of( $key );

		if ( $post_id < 1 ) {
			return self::gone();
		}

		$lock  = self::LOCK_PREFIX . $post_id;
		$token = WPCPM_Lock::take( $lock, self::LOCK_TTL );

		if ( '' === $token ) {
			return self::lead_busy();
		}

		try {
			self::forget_cached( $post_id );

			return self::trash_held( $post_id, (int) $actor, is_scalar( $confirm ) ? trim( (string) $confirm ) : '' );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * The delete, the lead's lock held.
	 *
	 * @param int    $post_id The lead's post.
	 * @param int    $actor   Who deletes it.
	 * @param string $confirm The confirmation posted.
	 * @return array|WP_Error As `trash()` answers.
	 */
	private static function trash_held( $post_id, $actor, $confirm ) {
		$key = self::key( $post_id );

		// Read again under the lock: another request may have reopened it, or begun its move into the talks.
		if ( self::post_of( $key ) < 1 ) {
			return self::gone();
		}

		if ( self::unfinished( $key ) ) {
			return new WP_Error( 'wpcpm_lead_unfinished', self::unfinished_sentence() );
		}

		$fields = self::fields_of( $post_id );

		if ( '' === $fields['closed'] ) {
			return new WP_Error( 'wpcpm_lead_open', __( 'Close this lead before you delete it.', 'wpcredits-program-manager' ) );
		}

		// With `EMPTY_TRASH_DAYS` at 0, `wp_trash_post()` deletes for good, which the plugin never does (round 2, L5).
		if ( defined( 'EMPTY_TRASH_DAYS' ) && ! EMPTY_TRASH_DAYS ) {
			return new WP_Error( 'wpcpm_lead_no_trash', __( 'This site keeps no Trash, so a lead cannot be deleted here.', 'wpcredits-program-manager' ) );
		}

		$wanted = self::delete_token( $post_id );

		if ( '' === $confirm || ! hash_equals( $wanted, $confirm ) ) {
			return array(
				'deleted'  => false,
				'question' => self::delete_question( trim( $fields['name'] ), count( WPCPM_Institution_Talk_Notes::for_key( $key ) ) ),
				'confirm'  => $wanted,
			);
		}

		if ( ! wp_trash_post( $post_id ) ) {
			return new WP_Error( 'wpcpm_lead_delete', __( 'The lead could not be deleted. Try again in a moment.', 'wpcredits-program-manager' ) );
		}

		$notes = WPCPM_Institution_Talk_Notes::trash_for( $key );

		self::log( $key, sprintf( 'was moved to the Trash with %1$d notes by account %2$d', $notes, $actor ) );

		return array(
			'deleted' => true,
			'name'    => trim( $fields['name'] ),
		);
	}

	/**
	 * The confirmation of a lead's delete: a hash of the lead as it is now, its fields, its notes and its history rows,
	 * so the question confirms that lead alone, and only until something else is done to it. Not a secret: the press is
	 * an Administrator's, behind its nonce.
	 *
	 * @param int $post_id The lead's post.
	 * @return string 64 hexadecimal characters.
	 */
	private static function delete_token( $post_id ) {
		$key   = self::key( $post_id );
		$notes = array();

		foreach ( WPCPM_Institution_Talk_Notes::for_key( $key ) as $note ) {
			$notes[] = (int) $note['id'];
		}

		sort( $notes );

		return hash( 'sha256', 'delete|' . $key . '|' . implode( ',', array_map( 'rawurlencode', array_map( 'strval', self::fields_of( $post_id ) ) ) ) . '|' . implode( ',', $notes ) . '|' . count( WPCPM_Institution_Audit::entries_for_lead( $key, 0 ) ) );
	}

	/**
	 * The question the first press of Delete this lead asks.
	 *
	 * @param string $name  The lead's name.
	 * @param int    $notes How many notes it has.
	 * @return string
	 */
	private static function delete_question( $name, $notes ) {
		if ( $notes > 0 ) {
			return sprintf(
				/* translators: 1: the lead's name, 2: how many notes it has. */
				_n( 'Deleting removes %1$s, its contact and its %2$d note from the talks. Press Delete this lead again to confirm.', 'Deleting removes %1$s, its contact and its %2$d notes from the talks. Press Delete this lead again to confirm.', $notes, 'wpcredits-program-manager' ),
				$name,
				$notes
			);
		}

		return sprintf(
			/* translators: %s: the lead's name. */
			__( 'Deleting removes %s and its contact from the talks. Press Delete this lead again to confirm.', 'wpcredits-program-manager' ),
			$name
		);
	}

	/**
	 * The refusal of a change while another request holds the lead's lock: the promotion's own words.
	 *
	 * @return WP_Error
	 */
	private static function lead_busy() {
		return new WP_Error( 'wpcpm_lead_busy', __( 'Somebody else is changing this lead. Try again in a while.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Say in the site's error log what was done to a lead, by its key alone: the log is a server's, and no name or address
	 * of the lead goes in it.
	 *
	 * @param string $key  The lead key.
	 * @param string $what What happened, after "The lead <key> ".
	 */
	private static function log( $key, $what ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- A lead moved to the Trash leaves no history row anyone reads; the key and the account are what an Administrator looks for.
		error_log( sprintf( 'WPCredits Program Manager: the lead %1$s %2$s.', (string) $key, (string) $what ) );
	}

	/**
	 * Delete every lead, in any status, with its meta. Uninstall only.
	 *
	 * @return int How many leads were deleted.
	 */
	public static function delete_all() {
		$deleted = 0;

		foreach ( self::ids( self::POST_STATUSES ) as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * The promotion, its lead's lock held.
	 *
	 * @param int    $post_id The lead's post.
	 * @param string $stage   The stage asked for, trimmed.
	 * @param int    $actor   Who promotes it.
	 * @return string|WP_Error
	 */
	private static function promote_held( $post_id, $stage, $actor ) {
		// Read again under the lock: another request may have promoted it, closed it or changed it.
		$lead = get_post( $post_id ) instanceof WP_Post ? self::shape( $post_id ) : null;

		if ( null === $lead ) {
			return self::gone();
		}

		$key       = $lead['key'];
		$record_id = $lead['promoted'];

		// A promotion that goes on is at the stage Airtable's record was made at.
		if ( '' !== $record_id && '' !== $lead['promoted_stage'] ) {
			$stage = $lead['promoted_stage'];
		} else {
			$refused = self::stage_refused( $stage );

			if ( is_wp_error( $refused ) ) {
				return $refused;
			}
		}

		if ( '' !== $lead['closed'] ) {
			return new WP_Error( 'wpcpm_lead_closed', __( 'This lead is closed. Reopen it before you move it on.', 'wpcredits-program-manager' ) );
		}

		if ( '' === $lead['country'] && '' !== $lead['country_text'] ) {
			return new WP_Error(
				'wpcpm_lead_country_text',
				sprintf(
					/* translators: %s: a country's name as it was given. */
					__( 'The country of this lead, %s, is not one of the program\'s yet. Choose it, then move the lead on.', 'wpcredits-program-manager' ),
					$lead['country_text']
				)
			);
		}

		// The name again, from the table: another school may have taken it since the lead was added. The
		// record this lead is becoming is no other school, and a resume whose record the index holds already
		// is past the check: it only finishes what was begun.
		WPCPM_Institutions_Index::forget_cached();

		if ( '' === $record_id || ! WPCPM_Institutions_Index::has( $record_id ) ) {
			$taken = self::name_taken( $lead['name'], $key );

			if ( '' !== $taken && $taken !== $record_id ) {
				return self::taken();
			}
		}

		if ( '' === $record_id ) {
			$created = WPCPM_Institution_Copy::create(
				array(
					'name'           => $lead['name'],
					'stage'          => $stage,
					'country'        => $lead['country'],
					'city'           => $lead['city'],
					'website'        => '',
					'contact_person' => $lead['contact_person'],
					'contact_email'  => $lead['contact_email'],
				)
			);

			if ( is_wp_error( $created ) || ! WPCPM_Airtable::is_record_id( $created ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Airtable may have made a record the site never heard of; the key is what an Administrator looks for, never a name or an address.
				error_log( 'WPCredits Program Manager: Airtable did not answer the create of ' . $key . ' with a record; look in Airtable for a record made anyway before it is promoted again.' );

				return new WP_Error( 'wpcpm_lead_create', __( 'Airtable could not create the institution\'s record, so it is still a lead. Try again in a while.', 'wpcredits-program-manager' ) );
			}

			$record_id = trim( (string) $created );

			// Kept before anything else, so a request that dies from here on is gone on with, not repeated.
			update_post_meta( $post_id, self::META_PROMOTED_STAGE, $stage );
			update_post_meta( $post_id, self::META_PROMOTED, $record_id );
		}

		WPCPM_Institutions_Index::forget_cached();

		$had_row = WPCPM_Institutions_Index::has( $record_id );

		if ( ! $had_row ) {
			$inserted = WPCPM_Institutions_Index::insert(
				array_merge(
					WPCPM_Institutions_Index::empty_row(),
					array(
						'record_id'      => $record_id,
						'name'           => $lead['name'],
						'stage'          => $stage,
						'country'        => $lead['country'],
						'country_name'   => $lead['country_name'],
						'city'           => $lead['city'],
						'contact_person' => $lead['contact_person'],
						'contact_email'  => $lead['contact_email'],
						'created'        => wp_date( 'Y-m-d' ),
						'agreement'      => array( 'status' => self::AGREEMENT_START ),
					)
				)
			);

			if ( ! $inserted ) {
				return self::index_refused();
			}
		}

		$answers = array( WPCPM_Institution_Assignment::RECORD_KEY => $lead['assigned'] );

		foreach ( self::RECORD_FIELDS as $field ) {
			$answers[ $field ] = $lead[ $field ];
		}

		if ( '' !== $lead['source'] ) {
			$answers[ self::RECORD_SOURCE ] = $lead['source'];
		}

		// The lead's key, so the link the assignee was emailed still finds the school (Task 16 fix round 1, G1).
		$answers[ self::RECORD_LEAD_KEY ] = $key;

		// Only what the record does not hold: a promotion that goes on changes nothing made since. Each of the three
		// answers whether it finished (the outside review, O4), and each is safe to repeat: fill_empty() fills only what
		// is empty, rekey() moves the notes still under the lead's key, and a row moves once.
		WPCPM_Institution_Record::fill_empty( $record_id, $answers );

		$finished = self::record_holds( $record_id, $answers );
		$finished = 0 === WPCPM_Institution_Talk_Notes::rekey( $key, $record_id ) && $finished;
		$finished = 0 === WPCPM_Institution_Audit::move_lead_rows( $key, $record_id ) && $finished;

		// Written when the record has no row from Lead yet, so a promotion that died between the insert and
		// this row gets it on the resume, once.
		if ( ! self::has_lead_stage_row( $record_id ) ) {
			WPCPM_Institution_Audit::record(
				array(
					'kind'        => WPCPM_Institution_Talks::LOG_STAGE,
					'institution' => $record_id,
					'subject'     => $record_id,
					'actor'       => $actor,
					'ground'      => self::ground( $actor ),
					'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'     => sprintf(
						/* translators: 1: the stage the talks were at, 2: the stage they are at now. */
						__( 'The stage of the talks moved from %1$s to %2$s.', 'wpcredits-program-manager' ),
						self::STAGE,
						$stage
					),
					'data'        => array(
						'from' => self::STAGE,
						'to'   => $stage,
					),
				)
			);
		}

		$after = array();

		foreach ( self::COPY_AFTER as $field ) {
			if ( '' !== $lead[ $field ] ) {
				$after[] = $field;
			}
		}

		// What the lead carried is the institution's only once all three finished; until then the lead is the only copy,
		// and stays a lead with its record ID kept (`unfinished()`), which the next save goes on with. Nothing is sent
		// then, so the copy goes once, with the save that finishes (the outside review, round 2, R2).
		if ( ! $finished ) {
			return new WP_Error( 'wpcpm_lead_move_unfinished', self::unfinished_sentence() );
		}

		if ( ! empty( $after ) ) {
			WPCPM_Institution_Copy::send( $record_id, $after );
		}

		// For good, not to the Trash: the institution holds everything the lead did, and a lead brought back from the
		// Trash would stand in the talks beside its own institution.
		wp_delete_post( $post_id, true );

		return $record_id;
	}

	/**
	 * Whether the record holds every answer a promotion gave it (the outside review of release 2, O4), read from the
	 * table: `fill_empty()` answers false both for a write refused and for answers the record held already, so its
	 * answer alone cannot tell a promotion whether it finished. An answer counts as held when the record keeps a value
	 * under its key; a blank answer is no answer, as `fill_empty()` reads it.
	 *
	 * @param string $record_id The record ID.
	 * @param array  $answers   What the promotion gave `fill_empty()`.
	 * @return bool
	 */
	private static function record_holds( $record_id, array $answers ) {
		WPCPM_Institution_Record::forget_cached( $record_id );

		$kept = WPCPM_Institution_Record::get( $record_id );

		foreach ( $answers as $field => $value ) {
			if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
				continue;
			}

			if ( ! array_key_exists( $field, $kept ) || null === $kept[ $field ] || ( is_string( $kept[ $field ] ) && '' === trim( $kept[ $field ] ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The refusal of a promotion whose index insert was refused: the lead stays a lead, and the next save goes on with
	 * it. A promotion whose insert landed and whose transfers did not finish answers `wpcpm_lead_move_unfinished`
	 * (`unfinished_sentence()`) instead (the outside review, round 2, R2).
	 *
	 * @return WP_Error
	 */
	private static function index_refused() {
		return new WP_Error( 'wpcpm_lead_index', __( 'The school could not be added to the site\'s list of institutions, so it is still a lead. Try again in a while.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Whether the record's history holds its `talks_stage` row from Lead (the promotion's).
	 *
	 * @param string $record_id The record ID.
	 * @return bool
	 */
	private static function has_lead_stage_row( $record_id ) {
		foreach ( WPCPM_Institution_Audit::entries_for( $record_id, 0 ) as $entry ) {
			if ( WPCPM_Institution_Talks::LOG_STAGE === $entry['kind'] && isset( $entry['data']['from'] ) && self::STAGE === $entry['data']['from'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Why a stage is no stage to promote a lead into, or null when it is one.
	 *
	 * @param string $stage A stage, trimmed.
	 * @return WP_Error|null
	 */
	private static function stage_refused( $stage ) {
		if ( in_array( $stage, self::after_confirmed(), true ) ) {
			return new WP_Error( 'wpcpm_lead_confirmed', __( 'Confirmed is set when the Collaboration Agreement is accepted or recorded as on file.', 'wpcredits-program-manager' ) );
		}

		if ( in_array( $stage, WPCPM_Institution_Talks::closed(), true ) ) {
			return new WP_Error(
				'wpcpm_lead_stage_closed',
				sprintf(
					/* translators: %s: a stage that closes the talks. */
					__( 'A lead that goes nowhere is closed, not moved to %s.', 'wpcredits-program-manager' ),
					$stage
				)
			);
		}

		if ( ! in_array( $stage, self::promotion_stages(), true ) ) {
			return new WP_Error( 'wpcpm_lead_stage', __( 'Choose the stage the talks are at.', 'wpcredits-program-manager' ) );
		}

		return null;
	}

	/**
	 * The stages a lead may be promoted into: the agreement's order before Confirmed, First Contact Made to
	 * Agreement Sent. A school past them (Confirmed, Student) is one the agreement path leads to, and one put
	 * there with no agreement could never be confirmed.
	 *
	 * @return string[]
	 */
	public static function promotion_stages() {
		$order = WPCPM_Institution_Agreement::STAGE_ORDER;
		$at    = array_search( 'Confirmed', $order, true );

		return false === $at ? array() : array_slice( $order, 0, (int) $at );
	}

	/**
	 * Confirmed and every stage of the agreement's order after it.
	 *
	 * @return string[]
	 */
	private static function after_confirmed() {
		$order = WPCPM_Institution_Agreement::STAGE_ORDER;
		$at    = array_search( 'Confirmed', $order, true );

		return false === $at ? array( 'Confirmed' ) : array_slice( $order, (int) $at );
	}

	/**
	 * Close or reopen a lead, with its row.
	 *
	 * @param string $key    A lead key.
	 * @param int    $actor  Who does it.
	 * @param bool   $closed Whether to close it.
	 * @return bool|WP_Error
	 */
	private static function set_closed( $key, $actor, $closed ) {
		$post_id = self::post_of( $key );

		if ( $post_id < 1 ) {
			return self::gone();
		}

		// Under the lead's lock, as a save and a delete hold it, so neither reads between this read and this write
		// (the owner's wave, round 2, L3).
		$lock  = self::LOCK_PREFIX . $post_id;
		$token = WPCPM_Lock::take( $lock, self::LOCK_TTL );

		if ( '' === $token ) {
			return self::lead_busy();
		}

		try {
			self::forget_cached( $post_id );

			return self::set_closed_held( $post_id, $actor, $closed );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Close or reopen a lead, its lock held.
	 *
	 * @param int  $post_id The lead's post.
	 * @param int  $actor   Who does it.
	 * @param bool $closed  Whether to close it.
	 * @return bool|WP_Error
	 */
	private static function set_closed_held( $post_id, $actor, $closed ) {
		$key = self::key( $post_id );

		// Read again under the lock, from the table: another request may have deleted it or moved it into the talks.
		if ( self::post_of( $key ) < 1 ) {
			return self::gone();
		}

		// A lead whose move into the talks did not finish has a record in Airtable the site does not hold yet: closed, it
		// would stay there with nothing on the site to say so (the whole-branch wave of release 2, W5).
		if ( $closed && self::unfinished( $key ) ) {
			return new WP_Error( 'wpcpm_lead_unfinished', self::unfinished_sentence() );
		}

		$fields = self::fields_of( $post_id );

		if ( ( '' !== $fields['closed'] ) === $closed ) {
			return false;
		}

		$fields['closed'] = $closed ? (string) wp_date( 'Y-m-d' ) : '';

		update_post_meta( $post_id, self::META_FIELDS, wp_slash( $fields ) );

		self::log_saved(
			self::key( $post_id ),
			(int) $actor,
			$closed ? __( 'The lead was closed.', 'wpcredits-program-manager' ) : __( 'The lead was reopened.', 'wpcredits-program-manager' ),
			array( 'closed' )
		);

		return true;
	}

	/**
	 * Whether a lead's move into the talks began and did not finish: Airtable's record was made and kept on the lead
	 * (`META_PROMOTED`), and the lead is still a lead, so the index never took the row (the whole-branch wave of release
	 * 2, W5). Choosing a stage on its Talks card and saving goes on with that record (`promote()`).
	 *
	 * @param string $key A lead key.
	 * @return bool
	 */
	public static function unfinished( $key ) {
		$post_id = self::post_of( $key );

		return $post_id > 0 && WPCPM_Airtable::is_record_id( trim( (string) get_post_meta( $post_id, self::META_PROMOTED, true ) ) );
	}

	/**
	 * The name of a lead's own lock, the one a promotion, a save of its Talks card and a delete hold (the owner's wave of
	 * release 2, X1 and X4).
	 *
	 * @param string $key A lead key.
	 * @return string '' for a lead the site does not keep.
	 */
	public static function lock_name( $key ) {
		$post_id = self::post_of( $key );

		return $post_id > 0 ? self::LOCK_PREFIX . $post_id : '';
	}

	/**
	 * Take a lead's own lock (`lock_name()`, one try, `LOCK_TTL`) for a caller outside this class that reads the lead and
	 * then writes it, the Talks card's save of a lead, and drop this request's copy of the lead once it is held
	 * (`forget_cached()`), so what the caller reads under the lock is the table's (the outside review of release 2, O3).
	 * The caller lets it go with `WPCPM_Lock::release( lock_name( $key ), $token )` on every path.
	 *
	 * @param string $key A lead key.
	 * @return string The lock's token, or '' when the lead is not kept or another request holds the lock.
	 */
	public static function take_lock( $key ) {
		$lock = self::lock_name( $key );

		if ( '' === $lock ) {
			return '';
		}

		$token = WPCPM_Lock::take( $lock, self::LOCK_TTL );

		if ( '' !== $token ) {
			self::forget_cached( (int) substr( $lock, strlen( self::LOCK_PREFIX ) ) );
		}

		return $token;
	}

	/**
	 * Add a note to a lead under the lead's own lock (the outside review of release 2, O8), the lock a promotion and a
	 * delete hold, so a note is never added while the lead's notes move to the record it becomes or go to the Trash.
	 * One try: held, the lead's busy sentence and nothing written. Under it the lead is read from the table
	 * (`take_lock()`); a lead deleted or moved into the talks meanwhile takes no note: a lead that is an institution now
	 * (`promoted_to()`) says so (`wpcpm_lead_promoted`, round 2, R3), any other answers the leads' sentence for a lead
	 * that is gone.
	 *
	 * @param string $key       A lead key.
	 * @param string $text      The note.
	 * @param int    $author_id Who wrote it.
	 * @return int|WP_Error The note's post, or the refusal (`wpcpm_lead_busy`, `wpcpm_lead_promoted`, `wpcpm_lead_gone`,
	 *                      or the note's own).
	 */
	public static function add_note( $key, $text, $author_id ) {
		$lock = self::lock_name( $key );

		// No post, so no lock to name: a lead moved into the talks has its post deleted (round 3, P1).
		if ( '' === $lock ) {
			return self::not_here( $key );
		}

		$token = self::take_lock( $key );

		if ( '' === $token ) {
			return self::lead_busy();
		}

		try {
			if ( null === self::get( $key ) ) {
				return self::not_here( $key );
			}

			return WPCPM_Institution_Talk_Notes::add( $key, $text, $author_id );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Why a note cannot be added to a lead the site no longer keeps: one that is an institution now (`promoted_to()`),
	 * whose Talks card takes notes for it (round 2, R3), or one that is gone.
	 *
	 * @param string $key A lead key.
	 * @return WP_Error `wpcpm_lead_promoted` or `wpcpm_lead_gone`.
	 */
	private static function not_here( $key ) {
		return '' !== self::promoted_to( $key )
			? new WP_Error( 'wpcpm_lead_promoted', __( 'This lead is an institution now. Add the note on its Talks card.', 'wpcredits-program-manager' ) )
			: self::gone();
	}

	/**
	 * Drop this request's cached copy of one lead's post and its meta (the outside review of release 2, O3). WordPress
	 * keeps every post and meta row it read for the rest of a request, so a critical section that read the lead before
	 * its lock (a handler's first read) would read that copy again under it, and a change another request saved in
	 * between would be missed. Every critical section of this class that reads a lead and then writes it (a close, a
	 * reopen, a delete, a promotion, the eraser's blanking) and the Talks card's save of a lead (`take_lock()`) call this
	 * first, once the lead's lock is held.
	 *
	 * @param int $post_id The lead's post.
	 */
	private static function forget_cached( $post_id ) {
		wp_cache_delete( (int) $post_id, 'posts' );
		wp_cache_delete( (int) $post_id, 'post_meta' );
	}

	/**
	 * What the Talks card says of such a lead, and why Close refuses it.
	 *
	 * @return string
	 */
	public static function unfinished_sentence() {
		return __( 'This lead\'s move into the talks as an institution did not finish. Choose the stage again and save to finish it.', 'wpcredits-program-manager' );
	}

	/**
	 * A single-line field as both of a lead's paths keep it, Add a lead and the Talks card (round 2 of the whole-branch
	 * wave, point 2): every run of whitespace, tabs and line breaks included, one space, then trimmed. Markup is kept
	 * whole: it is escaped where it is drawn.
	 *
	 * @param string $value What was typed.
	 * @return string
	 */
	public static function one_line( $value ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $value ) );
	}

	/**
	 * One `talks_saved` row under a lead's key.
	 *
	 * @param string   $key     The lead key.
	 * @param int      $actor   Who saved.
	 * @param string   $message The sentence.
	 * @param string[] $changed The fields that changed.
	 */
	private static function log_saved( $key, $actor, $message, array $changed ) {
		WPCPM_Institution_Audit::record_lead(
			array(
				'kind'     => WPCPM_Institution_Talks::LOG_SAVED,
				'lead'     => $key,
				'subject'  => $key,
				'actor'    => $actor,
				'ground'   => self::ground( $actor ),
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $message,
				'data'     => array( 'changed' => array_values( $changed ) ),
			)
		);
	}

	/**
	 * "The talks were updated: Next step and Follow up on.": the fields that changed, in the order of `GIVEN`,
	 * each named as its label is.
	 *
	 * @param string[] $changed Field keys.
	 * @return string
	 */
	private static function changed_sentence( array $changed ) {
		return sprintf(
			/* translators: %s: the names of the fields that changed, as a list, as "Next step and Follow up on". */
			__( 'The talks were updated: %s.', 'wpcredits-program-manager' ),
			wp_sprintf_l( '%l', array_map( array( __CLASS__, 'label' ), $changed ) )
		);
	}

	/**
	 * The sentence for a value over its field's longest, each whole, with the length to fill in.
	 *
	 * @param string $field A key of `MAX_LENGTHS`.
	 * @return string
	 */
	private static function too_long( $field ) {
		switch ( $field ) {
			case 'name':
				/* translators: %d: the most characters the name may hold. */
				return __( 'The school\'s name can be at most %d characters long.', 'wpcredits-program-manager' );
			case 'city':
				/* translators: %d: the most characters the city may hold. */
				return __( 'The city can be at most %d characters long.', 'wpcredits-program-manager' );
			case 'country_text':
				/* translators: %d: the most characters the country may hold. */
				return __( 'The country can be at most %d characters long.', 'wpcredits-program-manager' );
			case 'contact_person':
				/* translators: %d: the most characters the contact person may hold. */
				return __( 'The contact person can be at most %d characters long.', 'wpcredits-program-manager' );
			case 'lead_owner':
				/* translators: %d: the most characters the lead owner may hold. */
				return __( 'The lead owner can be at most %d characters long.', 'wpcredits-program-manager' );
			case 'program_details':
				/* translators: %d: the most characters the Program details may hold. */
				return __( 'Program details can be at most %d characters long.', 'wpcredits-program-manager' );
			default:
				/* translators: %d: the most characters the next step may hold. */
				return __( 'The next step can be at most %d characters long.', 'wpcredits-program-manager' );
		}
	}

	/**
	 * A field's name, as a sentence says it.
	 *
	 * @param string $field A field key.
	 * @return string
	 */
	private static function label( $field ) {
		$labels = array(
			'name'               => __( 'Name', 'wpcredits-program-manager' ),
			'city'               => __( 'City', 'wpcredits-program-manager' ),
			'country'            => __( 'Country', 'wpcredits-program-manager' ),
			'country_text'       => __( 'Country', 'wpcredits-program-manager' ),
			'contact_person'     => __( 'Contact person', 'wpcredits-program-manager' ),
			'contact_email'      => __( 'Contact email', 'wpcredits-program-manager' ),
			'help_desk'          => __( 'Help desk link', 'wpcredits-program-manager' ),
			'lead_owner'         => __( 'Lead owner', 'wpcredits-program-manager' ),
			'last_contact'       => __( 'Last contact', 'wpcredits-program-manager' ),
			'assigned'           => __( 'Assigned Administrator', 'wpcredits-program-manager' ),
			'estimated_students' => __( 'Estimated students', 'wpcredits-program-manager' ),
			'first_students'     => __( 'First students begin', 'wpcredits-program-manager' ),
			'next_step'          => __( 'Next step', 'wpcredits-program-manager' ),
			'follow_up'          => __( 'Follow up on', 'wpcredits-program-manager' ),
			'program_details'    => __( 'Program details', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
	}

	/**
	 * The fields given, checked and merged over the ones held.
	 *
	 * @param array $given The fields given.
	 * @param array $held  The fields as they are (`FIELDS` for a new lead).
	 * @return array|WP_Error Every field of `FIELDS`, or the first refusal.
	 */
	private static function clean( array $given, array $held ) {
		$next = array_merge( self::FIELDS, $held );

		foreach ( self::GIVEN as $field ) {
			if ( ! array_key_exists( $field, $given ) ) {
				continue;
			}

			$value = self::clean_one( $field, $given[ $field ] );

			if ( is_wp_error( $value ) ) {
				return $value;
			}

			$next[ $field ] = $value;
		}

		if ( '' === $next['name'] ) {
			return new WP_Error( 'wpcpm_lead_name', __( 'Give the school\'s name.', 'wpcredits-program-manager' ) );
		}

		// A country is an ID or the text it came as, never both: the ID given wins, and a text given alone
		// clears the ID.
		if ( array_key_exists( 'country', $given ) && '' !== $next['country'] ) {
			$next['country_text'] = '';
		} elseif ( array_key_exists( 'country_text', $given ) && '' !== $next['country_text'] ) {
			$next['country'] = '';
		}

		$next['country_name'] = '' !== $next['country'] ? (string) WPCPM_Countries::name_of( $next['country'] ) : '';

		return $next;
	}

	/**
	 * One field given, checked.
	 *
	 * @param string $field A key of `GIVEN`.
	 * @param mixed  $value What was given.
	 * @return string|int|WP_Error
	 */
	private static function clean_one( $field, $value ) {
		if ( 'assigned' === $field ) {
			$user_id = is_numeric( $value ) ? (int) $value : -1;

			if ( $user_id < 0 || ( $user_id > 0 && ! user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) ) ) {
				return new WP_Error( 'wpcpm_lead_assigned', __( 'Only an Administrator can be assigned a school.', 'wpcredits-program-manager' ) );
			}

			return $user_id;
		}

		if ( null === $value ) {
			$value = '';
		}

		if ( ! is_scalar( $value ) || ! self::is_utf8( (string) $value ) ) {
			/* translators: %s: the name of a field. */
			return new WP_Error( 'wpcpm_lead_text', sprintf( __( '%s holds characters the site cannot read.', 'wpcredits-program-manager' ), self::label( $field ) ) );
		}

		$value = trim( (string) $value );

		// One line, as the Talks card draws each of these, by the one rule both paths share (`one_line()`).
		if ( in_array( $field, self::ONE_LINE, true ) ) {
			$value = self::one_line( $value );
		}

		if ( isset( self::MAX_LENGTHS[ $field ] ) && self::length( $value ) > self::MAX_LENGTHS[ $field ] ) {
			return new WP_Error( 'wpcpm_lead_long', sprintf( self::too_long( $field ), self::MAX_LENGTHS[ $field ] ) );
		}

		if ( '' === $value ) {
			return '';
		}

		switch ( $field ) {
			case 'country':
				if ( ! WPCPM_Airtable::is_record_id( $value ) ) {
					return new WP_Error( 'wpcpm_lead_country', __( 'That country is not one of the program\'s.', 'wpcredits-program-manager' ) );
				}
				return $value;

			case 'contact_email':
				if ( ! is_email( $value ) ) {
					return new WP_Error( 'wpcpm_lead_email', __( 'That contact email is not an email address.', 'wpcredits-program-manager' ) );
				}
				return strtolower( $value );

			case 'help_desk':
				if ( 0 !== strpos( $value, 'https://' ) || false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return new WP_Error( 'wpcpm_lead_help_desk', __( 'The help desk link must be an address that starts with https://.', 'wpcredits-program-manager' ) );
				}
				return $value;

			case 'estimated_students':
				if ( 1 !== preg_match( '/^[0-9]{1,6}$/D', $value ) || (int) $value > self::MAX_STUDENTS ) {
					/* translators: %d: the most students a school can be estimated to send. */
					return new WP_Error( 'wpcpm_lead_number', sprintf( __( 'Estimated students must be a whole number from 0 to %d.', 'wpcredits-program-manager' ), self::MAX_STUDENTS ) );
				}
				return (string) (int) $value;
		}

		if ( in_array( $field, self::DATES, true ) && ! self::is_date( $value ) ) {
			/* translators: %s: the name of a date field, as "Follow up on". */
			return new WP_Error( 'wpcpm_lead_date', sprintf( __( '%s must be a date.', 'wpcredits-program-manager' ), self::label( $field ) ) );
		}

		return $value;
	}

	/**
	 * A lead's fields as kept, every key of `FIELDS` there and typed.
	 *
	 * @param int $post_id The lead's post.
	 * @return array
	 */
	private static function fields_of( $post_id ) {
		$stored = get_post_meta( $post_id, self::META_FIELDS, true );
		$stored = is_array( $stored ) ? $stored : array();
		$fields = array();

		foreach ( self::FIELDS as $field => $default ) {
			if ( 'assigned' === $field ) {
				$fields[ $field ] = isset( $stored[ $field ] ) && is_numeric( $stored[ $field ] ) && (int) $stored[ $field ] > 0 ? (int) $stored[ $field ] : 0;
				continue;
			}

			$fields[ $field ] = isset( $stored[ $field ] ) && is_scalar( $stored[ $field ] ) ? (string) $stored[ $field ] : $default;
		}

		return $fields;
	}

	/**
	 * One lead as `get()` returns it.
	 *
	 * @param int $post_id The lead's post.
	 * @return array
	 */
	private static function shape( $post_id ) {
		return array_merge(
			array( 'key' => self::key( $post_id ) ),
			self::fields_of( $post_id ),
			array(
				'source'         => (string) get_post_meta( $post_id, self::META_SOURCE, true ),
				'promoted'       => (string) get_post_meta( $post_id, self::META_PROMOTED, true ),
				'promoted_stage' => (string) get_post_meta( $post_id, self::META_PROMOTED_STAGE, true ),
			)
		);
	}

	/**
	 * The post of a lead key, when the site keeps that lead.
	 *
	 * @param mixed $key Candidate.
	 * @return int 0 for none.
	 */
	private static function post_of( $key ) {
		$key = is_scalar( $key ) ? trim( (string) $key ) : '';

		if ( ! self::is_key( $key ) ) {
			return 0;
		}

		$post_id = (int) substr( $key, strlen( self::KEY_PREFIX ) );
		$post    = get_post( $post_id );

		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type && in_array( $post->post_status, self::READ_STATUSES, true ) ? $post_id : 0;
	}

	/**
	 * The leads' posts in the statuses given, oldest first.
	 *
	 * @param string[] $statuses Post statuses.
	 * @return int[]
	 */
	private static function ids( array $statuses ) {
		$ids = array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'        => self::POST_TYPE,
					'post_status'      => $statuses,
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			)
		);

		sort( $ids );

		return $ids;
	}

	/**
	 * Tell an Administrator a lead is assigned to them, with the lead's page.
	 *
	 * @param int    $user_id The Administrator.
	 * @param string $key     The lead key.
	 * @param array  $fields  The lead's fields.
	 */
	private static function tell( $user_id, $key, array $fields ) {
		WPCPM_Institution_Assignment::notify(
			$user_id,
			$fields['name'],
			'' !== $fields['country_name'] ? $fields['country_name'] : $fields['country_text'],
			WPCPM_Institution_Talks::url( $key )
		);
	}

	/**
	 * The ground a change by this actor stands on.
	 *
	 * @param int $actor A user ID, 0 for the site.
	 * @return string
	 */
	private static function ground( $actor ) {
		return $actor > 0 ? WPCPM_Institution_Audit::GROUND_MANAGER : WPCPM_Institution_Audit::GROUND_SYSTEM;
	}

	/**
	 * The refusal of a lead the site does not keep.
	 *
	 * @return WP_Error
	 */
	private static function gone() {
		return new WP_Error( 'wpcpm_lead_gone', __( 'That lead is not in the talks any more.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The refusal of a name the talks already hold.
	 *
	 * @return WP_Error
	 */
	private static function taken() {
		return new WP_Error( 'wpcpm_lead_taken', __( 'A school of that name is already in the talks.', 'wpcredits-program-manager' ) );
	}

	/**
	 * A name as two are compared, as the import compares them: trimmed, runs of spaces as one, composed letters
	 * (NFC, where the intl extension offers it), lowercase.
	 *
	 * @param mixed $name A name.
	 * @return string
	 */
	private static function fold( $name ) {
		// The index's own fold, so the approval's search and the leads' agree on a name (the owner's wave, round 2, L2).
		return WPCPM_Institutions_Index::fold( $name );
	}

	/**
	 * A string's length in characters.
	 *
	 * @param string $text Well-formed UTF-8.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : (int) preg_match_all( '/./us', $text );
	}

	/**
	 * Whether a value is a day as the talks keep one: `Y-m-d`, and a real one.
	 *
	 * @param string $date Candidate.
	 * @return bool
	 */
	private static function is_date( $date ) {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) {
			return false;
		}

		$parsed = DateTime::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );

		return $parsed instanceof DateTime && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Whether a string is well-formed UTF-8.
	 *
	 * @param string $text Candidate.
	 * @return bool
	 */
	private static function is_utf8( $text ) {
		return function_exists( 'mb_check_encoding' ) ? mb_check_encoding( $text, 'UTF-8' ) : 1 === preg_match( '//u', $text );
	}
}
