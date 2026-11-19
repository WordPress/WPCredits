<?php
/**
 * Institutions module - the school's details card: what the program has on record, and the two ways a
 * school corrects it.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The card at the head of the "Representatives and agreement" module (Institutions release 2, mockup B:
 * read first, edit on request) and the two handlers behind its forms.
 *
 * **Two lists, then two forms.** The details read as rows under two small headings, in the order and
 * with the labels of `WPCPM_Institution_Details::fields()`, an empty one reading "Not given yet". Under
 * each list a closed disclosure opens the form for that group alone: "Correct these" for the details that
 * save at once, "Ask to correct these" for the five an Administrator checks first (an Administrator's own
 * second form, saved at once, reads "Correct these" as well). A form asks the
 * application's own questions, with its labels, help and limits, and its country is the one type-to-find
 * field the application uses.
 *
 * **A change waiting for an Administrator is a line under its row**, "Waiting for an Administrator: ...,
 * asked on ...", ending in "Withdraw it", and the group offers no form while one waits: a school has one
 * request at a time, and an Administrator, who is not offered the form either, is told where it is decided.
 * The outcome of the latest closed request is read by every account of the school until
 * the next request is sent. Everything about the request itself is `WPCPM_Institution_Request`'s; this
 * card draws what it reports and posts to its handlers.
 *
 * **Who may use it is the policy's answer** (`ACT_EDIT_DETAILS`): the members of a school whose agreement
 * is settled, and an Administrator. The members of a school whose agreement is not settled never reach the
 * module, and should the card be asked for theirs the policy refuses it: they correct nothing here, and
 * set the school's name on the agreement document in the panel. The policy is asked again by each handler,
 * against the institution the form names and never trusting it.
 *
 * **What a post does.**
 *
 * - The first group saves at once, for a member and for an Administrator: `WPCPM_Institution_Details::write()`
 *   (Airtable first, then the site), one email to the people "Who is emailed about institutions" names,
 *   and a line on the card.
 * - The second group, from a member, raises a request and writes nothing. From an Administrator it is
 *   written at once like the first: they are the checker, so nobody would approve their own change. Not
 *   while the school's request waits: it is decided where requests are decided, and a post of the form
 *   is refused with the sentence that says so.
 * - A post that changes nothing does nothing, says so, and sends no email.
 * - A save at once is made under the school's lock, the one its request is raised under, with what is on
 *   record read again under it: two tabs, or two accounts, posting the same correction write it once, and
 *   the second says "Nothing was changed.". A save, or a request, that finds the lock held writes nothing,
 *   keeps the typing and says "Someone else is saving your institution's details at this moment"
 *   (`WPCPM_Institution_Request::saving_message()`).
 * - A refusal gives back what was typed (`WPCPM_Typed_Text`, as the sponsors' profile card does), with the
 *   problem under the field that has one. An Airtable refusal writes nothing on the site, keeps every typed
 *   value and says so.
 * - A value posted back as it was drawn is not a change, even one the cleaner would have altered: a name
 *   Airtable holds with a percent octet in it is the program's to correct, not a reason to refuse the
 *   school's other answers.
 *
 * The outcome of a post is flashed on the channel the request's own withdrawal uses
 * (`WPCPM_Institution_Request::FLASH_DETAILS`), so the card is the one reader of that channel. The
 * words are a school's: none names the base the program keeps its records in.
 */
class WPCPM_Institution_Details_Card {

	/** The first group's form: saves at once. */
	const ACTION_SAVE = 'wpcpm_save_details';

	/** The second group's form: a request for a member, a save for an Administrator. */
	const ACTION_ASK = 'wpcpm_ask_details';

	/** The card's anchor, so a post lands back on the card it was made on: the request class follows the same one for a withdrawal. */
	const ANCHOR = WPCPM_Institution_Request::DETAILS_ANCHOR;

	/**
	 * The group whose typing a refusal of this request kept (`keep_typing()`), '' for none: its way back lands on that
	 * group's fold, which is drawn open with the refusal in it (Gate B round 2, F2 and F3).
	 *
	 * @var string
	 */
	private static $kept_group = '';

	/** The email the people the setting names are sent when details are saved. */
	const MAIL_SAVED = 'institution-details-saved';

	/** What every posted field is named before its detail's key. */
	const FIELD_PREFIX = 'wpcpm_details_';

	/** A value of a posted list: one line of printable characters. */
	const LIST_VALUE = '/\A[^\x00-\x1F\x7F]{1,100}\z/u';

	/** The most characters a country's record ID is kept with for a refused form. */
	const COUNTRY_ROOM = 40;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_ASK, array( __CLASS__, 'handle_ask' ) );
		add_action( 'admin_post_' . WPCPM_Institution_Tracks::ACTION_SAVE, array( 'WPCPM_Institution_Tracks', 'handle_save' ) );
		add_action( 'admin_post_' . WPCPM_Institution_Assignment::ACTION_SAVE, array( 'WPCPM_Institution_Assignment', 'handle_save' ) );
	}

	/**
	 * The words this card says of a post, by the status the handler flashes.
	 *
	 * The withdrawal's own four are `WPCPM_Institution_Request::school_messages()`, merged in when the
	 * flash is read. A sentence that depends on what was typed (a refused box, an error of the details
	 * class) is flashed as text and is not in this map.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to type and message.
	 */
	public static function messages() {
		return array(
			'saved'           => array( 'success', __( 'Your institution\'s details are saved.', 'wpcredits-program-manager' ) ),
			'asked'           => array( 'success', __( 'Sent: an Administrator checks the change, and you will see it here once it is decided.', 'wpcredits-program-manager' ) ),
			'nothing'         => array( 'info', __( 'Nothing was changed.', 'wpcredits-program-manager' ) ),
			// The page never names the base to a school: the records the program keeps are what could not be updated.
			'airtable'        => array( 'error', __( 'The program records could not be updated, so nothing was saved. Try again in a while.', 'wpcredits-program-manager' ) ),
			'refused-now'     => array( 'error', __( 'Nothing was saved. Correct what is marked below and save again.', 'wpcredits-program-manager' ) ),
			'refused-checked' => array( 'error', __( 'Nothing was sent. Correct what is marked below and send it again.', 'wpcredits-program-manager' ) ),
			'tracks-saved'    => array( 'success', __( 'The tracks offered are saved.', 'wpcredits-program-manager' ) ),
			'assignment-emailed' => array( 'success', __( 'The assigned Administrator is saved, and they have been emailed.', 'wpcredits-program-manager' ) ),
			'assignment-saved'   => array( 'success', __( 'The assigned Administrator is saved.', 'wpcredits-program-manager' ) ),
			// A save at once that found the school's lock held: the store's words for a raise that found it so.
			'saving-busy'     => array( 'error', WPCPM_Institution_Request::saving_message() ),
		);
	}

	/*
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * Draw the card.
	 *
	 * Nothing at all for a reader the policy refuses: an empty card would itself be an answer about
	 * whose school this is. The flash and the typing a refused form kept are taken here whether or not
	 * they are printed, so none waits in the account's meta until it surprises somebody.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param array  $context   The dashboard's context, the signature every card shares; this card asks
	 *                          the policy itself and reads nothing from it.
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 * phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	 */
	public static function render( $record_id, array $context ) {
		$record = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EDIT_DETAILS, WPCPM_Institution_Policy::subject_institution( $record ) );

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		$current = WPCPM_Institution_Details::current( $record );
		$open    = WPCPM_Institution_Request::open_details_for( $record );
		$facts   = $open ? WPCPM_Institution_Request::facts( $open ) : array();
		$flash   = self::take_flash( $record );
		$kept    = array(
			WPCPM_Institution_Details::GROUP_NOW     => WPCPM_Typed_Text::kept( self::typed_form( $record, WPCPM_Institution_Details::GROUP_NOW ) ),
			WPCPM_Institution_Details::GROUP_CHECKED => WPCPM_Typed_Text::kept( self::typed_form( $record, WPCPM_Institution_Details::GROUP_CHECKED ) ),
		);

		// The people who check the second group correct it themselves, and the form says so.
		$checker = WPCPM_Institution_Policy::GROUND_MANAGER === $decision['ground'];

		// Drawn with the shared parts (Stage B, Task 19), from the reader and the data read above.
		self::render_parts( $record, $current, $open, $facts, $flash, $kept, $checker );
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

	/**
	 * The two groups and what each is headed.
	 *
	 * @return array<string, string> Group to heading.
	 */
	private static function groups() {
		return array(
			WPCPM_Institution_Details::GROUP_NOW     => __( 'Contact and agreement', 'wpcredits-program-manager' ),
			WPCPM_Institution_Details::GROUP_CHECKED => __( 'Name and address', 'wpcredits-program-manager' ),
		);
	}

	/*
	 * The rules the card asks (Stage B, Task 19: one helper per rule)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The outcome the card prints at its head: an outcome of the Tracks offered or the Assigned Administrator form is
	 * read under that section's heading instead.
	 *
	 * @param array $flash What `take_flash()` returned.
	 * @return array{0: string, 1: string}|null Tone and sentence, or null.
	 */
	private static function card_message( array $flash ) {
		return null !== $flash['message'] && ! in_array( $flash['section'], array( 'tracks', 'assignment' ), true ) ? $flash['message'] : null;
	}

	/**
	 * The group whose form a refusal is about, when that form is drawn: an error the flash names a group for (a refused
	 * post, or one whose typing was kept), while the group offers its form. '' for every other outcome, which the card
	 * says at its top (Gate B round 2, F2).
	 *
	 * @param array|null $message What `card_message()` returned.
	 * @param array      $flash   What `take_flash()` returned.
	 * @param int        $open    The request waiting, or 0.
	 * @return string `GROUP_NOW`, `GROUP_CHECKED` or ''.
	 */
	private static function refused_form( $message, array $flash, $open ) {
		if ( null === $message || 'error' !== $message[0] || ! array_key_exists( $flash['group'], self::groups() ) ) {
			return '';
		}

		return self::offers_form( $flash['group'], $open ) ? $flash['group'] : '';
	}

	/**
	 * A group's fold, the id its refusal's way back lands on.
	 *
	 * @param string $group `GROUP_NOW` or `GROUP_CHECKED`.
	 * @return string
	 */
	private static function fold_id( $group ) {
		return 'wpcpm-details-' . $group;
	}

	/**
	 * The outcome a section of the card reads under its own heading: the Tracks offered or the Assigned Administrator
	 * form's, by the section the handler flashed it for.
	 *
	 * @param array  $flash   What `take_flash()` returned.
	 * @param string $section `tracks` or `assignment`.
	 * @return array{0: string, 1: string}|null Tone and sentence, or null.
	 */
	private static function section_message( array $flash, $section ) {
		return $section === $flash['section'] ? $flash['message'] : null;
	}

	/**
	 * Whether the line telling an Administrator where the school's waiting request is decided is drawn: under the second
	 * group while it offers no form, to an Administrator alone (the school's accounts read Withdraw it instead).
	 *
	 * @param string $group   `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param int    $open    The request waiting, or 0.
	 * @param bool   $checker Whether the reader is an Administrator.
	 * @return bool
	 */
	private static function shows_decide( $group, $open, $checker ) {
		return $checker && ! self::offers_form( $group, $open );
	}

	/**
	 * Whether a group's outcome line is drawn: the latest answered request's, under the second group, while none waits.
	 *
	 * @param string $group `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param int    $open  The request waiting, or 0.
	 * @return bool
	 */
	private static function shows_outcome( $group, $open ) {
		return WPCPM_Institution_Details::GROUP_CHECKED === $group && ! $open;
	}

	/**
	 * Whether a group offers its form. One request at a time: while one waits, the second group offers nothing to type
	 * into, to the Administrator no more than to the school. An Administrator's own correction would leave the school's
	 * request open over a value it was not asked against, so they are told where it is decided (`decide_html()`).
	 *
	 * @param string $group `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param int    $open  The request waiting, or 0.
	 * @return bool
	 */
	private static function offers_form( $group, $open ) {
		return ! ( WPCPM_Institution_Details::GROUP_CHECKED === $group && $open );
	}

	/**
	 * Whether a group's form saves at once: the first group's for everybody, the second group's for an Administrator,
	 * who checks it. Only the school's own second form is a request.
	 *
	 * @param string $group   `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param bool   $checker Whether the reader is an Administrator.
	 * @return bool
	 */
	private static function saves( $group, $checker ) {
		return WPCPM_Institution_Details::GROUP_NOW === $group || $checker;
	}

	/**
	 * A form's words: the fold's title and second piece, the busy words and the button.
	 *
	 * @param bool $saves Whether the form saves at once (`saves()`).
	 * @return array{title: string, second: string, busy: string, button: string}
	 */
	private static function fold_words( $saves ) {
		return array(
			'title'  => $saves ? __( 'Correct these', 'wpcredits-program-manager' ) : __( 'Ask to correct these', 'wpcredits-program-manager' ),
			'second' => $saves ? __( '(saved at once)', 'wpcredits-program-manager' ) : __( '(an Administrator checks first)', 'wpcredits-program-manager' ),
			'busy'   => $saves ? __( 'Saving', 'wpcredits-program-manager' ) : __( 'Sending', 'wpcredits-program-manager' ),
			'button' => $saves ? __( 'Save the details', 'wpcredits-program-manager' ) : __( 'Send to an Administrator', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The note over an Administrator's second form, which saves what a school's would only ask; '' for every other form.
	 *
	 * @param string $group   `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param bool   $checker Whether the reader is an Administrator.
	 * @return string
	 */
	private static function checker_note( $group, $checker ) {
		return WPCPM_Institution_Details::GROUP_NOW !== $group && $checker ? __( 'You are an Administrator, so these are saved at once and nobody approves them.', 'wpcredits-program-manager' ) : '';
	}

	/**
	 * The hidden fields a group's form posts after its nonce: its action and the institution.
	 *
	 * @param string $group  `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param string $record Institutions record ID.
	 * @return array<string, string> Name to value, in the order posted.
	 */
	private static function form_fields( $group, $record ) {
		return array(
			'action' => WPCPM_Institution_Details::GROUP_NOW === $group ? self::ACTION_SAVE : self::ACTION_ASK,
			'record' => (string) $record,
		);
	}

	/**
	 * The hidden fields Withdraw it posts after its nonce: the request's handler, the request, and the card it was
	 * pressed on, so the withdrawal comes back to it and not to the top of the page.
	 *
	 * @param int $request The request.
	 * @return array<string, string> Name to value, in the order posted.
	 */
	private static function withdraw_fields( $request ) {
		return array(
			'action'                                => WPCPM_Institution_Request::ACTION_DETAILS_WITHDRAW,
			'request'                               => (string) (int) $request,
			WPCPM_Institution_Request::RETURN_FIELD => self::ANCHOR,
		);
	}

	/**
	 * Hidden fields as today's forms print them, one input to a name.
	 *
	 * @param array<string, string> $fields Name to value.
	 * @return string Escaped markup.
	 */
	public static function hidden_html( array $fields ) {
		$out = '';

		foreach ( $fields as $name => $value ) {
			$out .= sprintf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}

		return $out;
	}

	/**
	 * The words of the line under a row whose change waits, with the value asked for and the day as its two placeholders.
	 *
	 * @return string
	 */
	private static function waiting_format() {
		/* translators: 1: the value an Administrator is being asked to put on record, 2: the day it was asked. */
		return __( 'Waiting for an Administrator: %1$s, asked on %2$s.', 'wpcredits-program-manager' );
	}

	/**
	 * The values asked for by the waiting request, by the detail's key.
	 *
	 * @param array $facts The open request's facts (`WPCPM_Institution_Request::facts()`), or none.
	 * @return array<string, string>
	 */
	private static function asked( array $facts ) {
		$asked = array();

		foreach ( isset( $facts['changes'] ) ? (array) $facts['changes'] : array() as $row ) {
			$asked[ $row['key'] ] = $row['asked'];
		}

		return $asked;
	}

	/**
	 * What an Administrator reads under the second group while the school's request waits, as markup: the place linked
	 * to the requests card of the Administrator Dashboard while that page is published.
	 *
	 * @return string Escaped markup.
	 */
	private static function decide_html() {
		$page = (string) WPCPM_Administrators_Dashboard::page_url();

		return wp_kses(
			'' !== $page
				? self::decide_sentence( '<a href="' . esc_url( $page . '#wpcpm-requests' ) . '">', '</a>' )
				: esc_html( self::decide_sentence() ),
			array( 'a' => array( 'href' => array() ) )
		);
	}

	/**
	 * The outcome line's sentence for a school: the latest answered request's, '' for none.
	 *
	 * @param string $record Institutions record ID.
	 * @return string Not escaped.
	 */
	private static function outcome_for( $record ) {
		return self::outcome_sentence( WPCPM_Institution_Request::latest_details_outcome( $record ) );
	}

	/**
	 * Whether a detail holds nothing: "Not given yet" is read for it.
	 *
	 * @param mixed $value What the program has on record.
	 * @return bool
	 */
	private static function is_empty( $value ) {
		return is_array( $value ) ? array() === $value : '' === trim( (string) $value );
	}

	/**
	 * Everything a question of a form is drawn with, worked out once: its id and name, its words, and
	 * the box's value, limit and keyboard by today's typed-text rules, or the ticked languages, or the country chosen.
	 *
	 * @param string            $key      The detail's key.
	 * @param string            $type     Its type: `text`, `url`, `email`, `country` or `choices`.
	 * @param array             $question The application's question for it.
	 * @param string|string[]   $value    What the program has on record.
	 * @param string|array|null $typed    What a refused post typed for it, or null.
	 * @return array
	 */
	private static function field_spec( $key, $type, array $question, $value, $typed ) {
		$spec = array(
			'id'       => 'wpcpm-details-' . $key,
			'name'     => self::FIELD_PREFIX . $key,
			'label'    => (string) $question['label'],
			'required' => ! empty( $question['required'] ),
			'help'     => ! empty( $question['help'] ) ? (string) $question['help'] : '',
			'columns'  => ! empty( $question['columns'] ),
		);

		if ( 'choices' === $type ) {
			$spec['picked']  = is_array( $typed ) ? $typed : (array) $value;
			$spec['choices'] = isset( $question['choices'] ) ? (array) $question['choices'] : array();

			return $spec;
		}

		if ( 'country' === $type ) {
			$spec['current'] = is_string( $typed ) ? $typed : (string) $value;
			$spec['options'] = array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + WPCPM_Countries::options();
			/* translators: Shown in the empty country field, which takes typing. */
			$spec['find'] = __( 'Type to find your country', 'wpcredits-program-manager' );
			$spec['none'] = __( 'No country has that name.', 'wpcredits-program-manager' );
			/* translators: %s: how many countries the list shows, as a number. */
			$spec['count'] = __( 'Countries in the list: %s', 'wpcredits-program-manager' );

			return $spec;
		}

		$limit = isset( $question['maxlength'] ) ? (int) $question['maxlength'] : 200;

		if ( is_string( $typed ) ) {
			$shown = WPCPM_Typed_Text::kept_attr( $typed );
		} elseif ( 'email' === $type ) {
			$shown = (string) $value;
		} elseif ( 'url' === $type ) {
			$shown = WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::kept_attr( (string) $value ) );
		} else {
			$shown = WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::attr_text( (string) $value ) );
		}

		$spec['box']   = 'email' === $type ? 'email' : 'text';
		$spec['shown'] = $shown;
		// A text the program holds longer than its box is drawn with room for all of it.
		$spec['maxlength'] = (int) ( 'text' === $type ? WPCPM_Typed_Text::drawn_limit( (string) $value, $limit ) : $limit );
		$spec['inputmode'] = 'url' === $type ? 'url' : ( 'email' === $type ? 'email' : '' );
		$spec['auto']      = isset( $question['auto'] ) ? (string) $question['auto'] : '';

		return $spec;
	}

	/*
	 * The card on the shared parts (Stage B, Task 19; design 6.4)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The card on the parts: the shell's card fold, open, its anchor on the section; its name and the phrase "What the
	 * program has on record." in its head (the view is "Your institution" and holds other cards, so the card keeps a
	 * head of its own: design 6, 6.4); the outcome first; then each group's in-card heading, its key and value table and
	 * its inner fold; the outcome line under the second table, or an Administrator's note of where a waiting request is
	 * decided; then Tracks offered and, for an Administrator while the site does not keep the record, who follows the
	 * school up (in site mode that is the Talks card's).
	 *
	 * @param string $record  Institutions record ID.
	 * @param array  $current What the program has on record.
	 * @param int    $open    The request waiting, or 0.
	 * @param array  $facts   The open request's facts, or none.
	 * @param array  $flash   What `take_flash()` returned.
	 * @param array  $kept    What a refused post kept, by group.
	 * @param bool   $checker Whether the reader is an Administrator.
	 */
	private static function render_parts( $record, array $current, $open, array $facts, array $flash, array $kept, $checker ) {
		WPCPM_Dashboard_Card::open(
			array(
				'key'           => 'details',
				// The name alone: the view's lede already says what the program has on record (Task 19, fix round 1).
				'title'         => __( 'Your institution\'s details', 'wpcredits-program-manager' ),
				'open'          => true,
				'prefix'        => 'wpcpm-',
				'id_on'         => 'section',
				'section_class' => '',
			)
		);

		$message = self::card_message( $flash );
		$at_form = self::refused_form( $message, $flash, $open );

		// A refusal of a group's form is said in that form's fold (`render_form_parts()`); any other outcome here.
		if ( null !== $message && '' === $at_form ) {
			echo WPCPM_Dashboard_Card::message( $message[0], $message[1], 'wpcpm-details__message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Dashboard_Card::message() escapes what it prints.
		}

		$asked = self::asked( $facts );

		foreach ( self::groups() as $group => $heading ) {
			$heading_id = 'wpcpm-details-' . $group . '-heading';

			echo WPCPM_UI::heading( $heading, 'h4', $heading_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.

			self::render_table_parts( $group, $current, $asked, $facts, $checker, $heading_id );

			if ( self::shows_outcome( $group, $open ) ) {
				$sentence = self::outcome_for( $record );

				if ( '' !== $sentence ) {
					echo WPCPM_UI::intro( $sentence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.
				}
			}

			if ( self::shows_decide( $group, $open, $checker ) ) {
				echo WPCPM_UI::intro( '', '', '', self::decide_html() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- decide_html() passes through wp_kses().
			}

			if ( ! self::offers_form( $group, $open ) ) {
				continue;
			}

			self::render_form_parts( $record, $group, $current, $kept[ $group ], $group === $flash['group'] ? $flash['problems'] : array(), $checker, $group === $at_form ? WPCPM_Dashboard_Card::message( $message[0], $message[1], 'wpcpm-details__message' ) : '' );
		}

		WPCPM_Institution_Tracks::render( $record, $checker, self::section_message( $flash, 'tracks' ) );

		// Once the site keeps the record (Institutions off Airtable, release 2), who follows the school up is changed on its
		// Talks card, with the rest of the talks.
		if ( ! WPCPM_Institutions_Index::on_site() ) {
			WPCPM_Institution_Assignment::render( $record, $checker, self::section_message( $flash, 'assignment' ) );
		}

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * One group's key and value table (design 2.7, 6): a row to each detail of the group, in the details table's order
	 * and with its labels, each label a row header; "Not given yet" in words, of the empty kind; the page address, the
	 * website and the contact's email token cells, which may break anywhere; every other value as it is: the key and value table's value cell breaks
	 * a word anywhere, so a 40-character name never widens the card. A row whose change waits holds the waiting line under
	 * its value.
	 *
	 * @param string $group      `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param array  $current    What the program has on record.
	 * @param array  $asked      What the waiting request asks, by key (`asked()`).
	 * @param array  $facts      The open request's facts, or none.
	 * @param bool   $checker    Whether the reader is an Administrator, who is not offered the school's Withdraw it.
	 * @param string $heading_id The group's heading, which names the table.
	 */
	private static function render_table_parts( $group, array $current, array $asked, array $facts, $checker, $heading_id ) {
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
		$out   = WPCPM_UI::table_open(
			array( $label, $text ),
			array(
				'keyvalue'   => true,
				'labelledby' => $heading_id,
			)
		);

		foreach ( WPCPM_Institution_Details::fields() as $key => $spec ) {
			if ( $group !== $spec['group'] ) {
				continue;
			}

			$empty = self::is_empty( $current[ $key ] );
			$shown = self::shown( $key, $current[ $key ] );
			$cells = ! $empty && in_array( $spec['type'], array( 'url', 'email' ), true );
			$html  = esc_html( $shown );

			if ( isset( $asked[ $key ] ) ) {
				$html .= self::waiting_parts( $asked[ $key ], (int) $facts['at'], (int) $facts['id'], ! $checker, $spec['label'] );
			}

			$out .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $label, esc_html( $spec['label'] ) )
				. WPCPM_UI::cell( $cells ? $token : $text, $html, $empty ? 'empty' : '' )
				. WPCPM_UI::row_close();
		}

		echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value escaped above.
	}

	/**
	 * The waiting line under a row's value on the parts: today's sentence in words, the value asked for
	 * as it is and the day whole, as the cell's second line, with no colored edge; then, for the school's own
	 * accounts, Withdraw it (quiet red), posting what today's form posts. Today's form asks no question, and neither
	 * does this one; the row is named for a screen reader, as every row has its own.
	 *
	 * @param string $asked    The value asked for, as a person reads it.
	 * @param int    $at       When it was asked, as a Unix time.
	 * @param int    $request  The request.
	 * @param bool   $withdraw Whether to offer Withdraw it.
	 * @param string $label    The row's label.
	 * @return string Escaped markup.
	 */
	private static function waiting_parts( $asked, $at, $request, $withdraw, $label ) {
		$line = sprintf( esc_html( self::waiting_format() ), esc_html( (string) $asked ), WPCPM_UI::whole( WPCPM_Dates::day( $at ) ) );
		$out  = WPCPM_UI::cell_sub( '', $line );

		if ( ! $withdraw ) {
			return $out;
		}

		return $out
			. sprintf(
				'<form method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Withdrawing', 'wpcredits-program-manager' )
			)
			. WPCPM_UI::nonce( WPCPM_Institution_Request::ACTION_DETAILS_WITHDRAW . '_' . $request )
			. self::hidden_html( self::withdraw_fields( $request ) )
			. WPCPM_UI::button(
				array(
					'label'   => __( 'Withdraw it', 'wpcredits-program-manager' ),
					'kind'    => 'quiet-danger',
					'context' => (string) $label,
					'side'    => 'front',
				)
			)
			. '</form>';
	}

	/**
	 * One group's inner fold on the parts, with its form: "Correct these" with "(saved at once)", or the school's
	 * "Ask to correct these" with "(an Administrator checks first)"; closed, and open for a form a refusal gave its
	 * typing back to. The form posts what today's form posts (its nonce without an id), asks the application's own
	 * questions as fields, the languages a choice group and Country the one combobox, and ends with its one primary.
	 *
	 * @param string $record   Institutions record ID.
	 * @param string $group    `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param array  $current  What the program has on record.
	 * @param array  $typed    What a refused post kept, by key, or none.
	 * @param array  $problems What was wrong with a refused post's fields, by key.
	 * @param bool   $checker  Whether the reader is an Administrator.
	 * @param string $refusal  The refusal of this form, as the shell's message, or ''.
	 */
	private static function render_form_parts( $record, $group, array $current, array $typed, array $problems, $checker, $refusal = '' ) {
		$words  = self::fold_words( self::saves( $group, $checker ) );
		$fields = self::form_fields( $group, $record );
		$note   = self::checker_note( $group, $checker );

		$fold = WPCPM_UI::inner_open(
			array(
				'title'  => $words['title'],
				'second' => $words['second'],
				'open'   => array() !== $typed || '' !== $refusal,
				'id'     => self::fold_id( $group ),
			)
		);

		// A refusal of this form is the open fold's first block, directly over the form (Gate B round 2, F2).
		echo $fold . $refusal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in WPCPM_Dashboard_Card::message().
		printf(
			'<form method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $words['busy'] )
		);
		echo WPCPM_UI::nonce( $fields['action'] . '_' . $record ) . self::hidden_html( $fields ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part and in hidden_html().

		if ( '' !== $note ) {
			echo WPCPM_UI::intro( $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.
		}

		$questions = WPCPM_Institution_Application::fields();

		foreach ( WPCPM_Institution_Details::fields() as $key => $spec ) {
			if ( $group !== $spec['group'] ) {
				continue;
			}

			$field = self::field_parts(
				$spec['type'],
				self::field_spec( $key, $spec['type'], $questions[ WPCPM_Institution_Details::question( $key ) ], $current[ $key ], isset( $typed[ $key ] ) ? $typed[ $key ] : null ),
				isset( $problems[ $key ] ) ? (string) $problems[ $key ] : ''
			);

			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.
		}

		$button = WPCPM_UI::button(
			array(
				'label' => $words['button'],
				'side'  => 'front',
			)
		);

		echo WPCPM_UI::actions( array( $button ) ) . '</form>' . WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.
	}

	/**
	 * One question of a form on the parts: a field, the languages a choice group in columns (its help under its legend
	 * and its refusal under its options), Country the one combobox; the problem under its box and tied to it.
	 *
	 * @param string $type    The detail's type.
	 * @param array  $spec    What `field_spec()` worked out.
	 * @param string $problem What was wrong with it, or ''.
	 * @return string Escaped markup.
	 */
	private static function field_parts( $type, array $spec, $problem ) {
		$need = $spec['required'] ? 'required' : '';

		if ( 'choices' === $type ) {
			$options = array();

			foreach ( $spec['choices'] as $choice ) {
				$options[ (string) $choice ] = trim( (string) $choice );
			}

			return WPCPM_UI::choices(
				array(
					'id'            => $spec['id'],
					'legend'        => $spec['label'],
					'name'          => $spec['name'] . '[]',
					'type'          => 'checkbox',
					'options'       => $options,
					'checked'       => $spec['picked'],
					'need'          => $need,
					'hint'          => $spec['help'],
					'group_problem' => $problem,
					'columns'       => $spec['columns'],
				)
			);
		}

		if ( 'country' === $type ) {
			return WPCPM_UI::field(
				array(
					'type'          => 'combo',
					'id'            => $spec['id'],
					'name'          => $spec['name'],
					'label'         => $spec['label'],
					'value'         => $spec['current'],
					'options'       => $spec['options'],
					'need'          => $need,
					// Today's select carries no `required`: the handler says what is missing.
					'required_attr' => false,
					'hint'          => $spec['help'],
					'problem'       => $problem,
					'find'          => $spec['find'],
					'none'          => $spec['none'],
					'count'         => $spec['count'],
				)
			);
		}

		return WPCPM_UI::field(
			array(
				'type'    => $spec['box'],
				'id'      => $spec['id'],
				'name'    => $spec['name'],
				'label'   => $spec['label'],
				'value'   => $spec['shown'],
				'need'    => $need,
				'hint'    => $spec['help'],
				'problem' => $problem,
				'attrs'   => array(
					'maxlength'    => $spec['maxlength'],
					'autocomplete' => $spec['auto'],
					'inputmode'    => $spec['inputmode'],
				),
			)
		);
	}

	/*
	 * The card today (every surface not on the parts)
	 * --------------------------------------------------------------------
	 */

	/**
	 * One value as the list reads it: the details class's words for it, and "Not given yet" for none.
	 *
	 * @param string $key   A key of the details table.
	 * @param mixed  $value What the program has on record.
	 * @return string
	 */
	private static function shown( $key, $value ) {
		return self::is_empty( $value ) ? __( 'Not given yet', 'wpcredits-program-manager' ) : WPCPM_Institution_Details::show( $key, $value );
	}

	/**
	 * What an Administrator is told while the school's request waits: in the note under the second group,
	 * with the place linked, and as the refusal of their post of it, as plain text.
	 *
	 * @param string $open  The opening link tag, or '' for none.
	 * @param string $close The closing link tag, or '' for none.
	 * @return string
	 */
	private static function decide_sentence( $open = '', $close = '' ) {
		return sprintf(
			/* translators: 1: the opening link tag to the Administrator Dashboard's requests card (empty while that page is not published), 2: the closing link tag. */
			__( 'A request from the school is waiting. Decide it in %1$sRequests from institutions on the Administrator Dashboard%2$s.', 'wpcredits-program-manager' ),
			$open,
			$close
		);
	}

	/**
	 * The sentence the card prints for a request's outcome, which the Institution Dashboard's Overview
	 * reads as its news row's why: the day an approval was made, or the decline with the Administrator's note when
	 * there is one. Not escaped: a caller escapes it where it prints it.
	 *
	 * @param array $outcome What `WPCPM_Institution_Request::latest_details_outcome()` returned.
	 * @return string '' for a withdrawal, no outcome, or an approval with no day.
	 */
	public static function outcome_sentence( array $outcome ): string {
		$state = isset( $outcome['state'] ) ? (string) $outcome['state'] : '';

		if ( WPCPM_Institution_Request::STATE_DONE === $state ) {
			$day = WPCPM_Dates::day( isset( $outcome['closed_at'] ) ? (int) $outcome['closed_at'] : 0 );

			if ( '' === $day ) {
				return '';
			}

			return sprintf(
				/* translators: %s: the day an Administrator made the change a school asked for. */
				__( 'Updated on %s.', 'wpcredits-program-manager' ),
				$day
			);
		}

		if ( WPCPM_Institution_Request::STATE_DECLINED !== $state ) {
			return '';
		}

		$note = trim( isset( $outcome['note'] ) ? (string) $outcome['note'] : '' );

		return '' === $note
			? __( 'An Administrator did not make the change you asked for.', 'wpcredits-program-manager' )
			: sprintf(
				/* translators: %s: the note an Administrator left when they declined a school's request to correct its details. */
				__( 'An Administrator did not make the change you asked for: %s', 'wpcredits-program-manager' ),
				$note
			);
	}

	/**
	 * The outcome of the last thing a handler did, or of the withdrawal, for this card.
	 *
	 * Taken whether or not it is printed, so a sentence nobody will be shown does not wait in the account's
	 * meta. A flash that names an institution is that institution's card alone: an Administrator who moved
	 * to another school meanwhile is not told that school's details were saved. The withdrawal's names none,
	 * and is read by the card of whoever pressed it.
	 *
	 * @param string $record Institutions record ID of the card being drawn.
	 * @return array{message: array{0: string, 1: string}|null, section: string, group: string, problems: array<string, string>}
	 */
	private static function take_flash( $record ) {
		$out   = array(
			'message'  => null,
			'section'  => '',
			'group'    => '',
			'problems' => array(),
		);
		$flash = WPCPM_Flash::take( WPCPM_Institution_Request::FLASH_DETAILS );

		if ( ! is_array( $flash ) ) {
			return $out;
		}

		$about = isset( $flash['record'] ) ? trim( (string) $flash['record'] ) : '';

		if ( '' !== $about && $about !== $record ) {
			return $out;
		}

		$text     = isset( $flash['text'] ) && is_scalar( $flash['text'] ) ? trim( (string) $flash['text'] ) : '';
		$status   = isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';
		$messages = array_merge( self::messages(), WPCPM_Institution_Request::school_messages() );

		if ( '' !== $text ) {
			$out['message'] = array( 'error', $text );
		} elseif ( isset( $messages[ $status ] ) ) {
			$out['message'] = $messages[ $status ];
		}

		if ( isset( $flash['section'] ) && in_array( $flash['section'], array( 'tracks', 'assignment' ), true ) ) {
			$out['section'] = $flash['section'];
		}

		if ( isset( $flash['group'] ) && is_scalar( $flash['group'] ) ) {
			$out['group'] = (string) $flash['group'];
		}

		if ( isset( $flash['problems'] ) && is_array( $flash['problems'] ) ) {
			foreach ( $flash['problems'] as $key => $problem ) {
				if ( is_string( $key ) && is_string( $problem ) ) {
					$out['problems'][ $key ] = $problem;
				}
			}
		}

		return $out;
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Save the first group's details: the contact and the agreement.
	 */
	public static function handle_save() {
		self::handle( WPCPM_Institution_Details::GROUP_NOW );
	}

	/**
	 * Ask to correct the second group's details: a request for a member, a save for an Administrator.
	 */
	public static function handle_ask() {
		self::handle( WPCPM_Institution_Details::GROUP_CHECKED );
	}

	/**
	 * One group's post.
	 *
	 * **The order is the design.** The nonce, keyed to the institution the form names; the shape of that
	 * institution; the policy, against the institution the form names, since the form's `record` is a claim
	 * and a member of another school posting it gets the policy's one refusal; and only then anything that
	 * changes a thing. Then, in order, what a refusal costs least: a text the cleaner would take words from,
	 * a field the application's rules refuse, a post that changes nothing; and last the write, or the request.
	 *
	 * **A value posted back as it was drawn is left as it is.** The program may hold a value that
	 * the cleaner would alter (Airtable holds what it holds), and the school is told about, and held to the
	 * rules for, only what it has typed.
	 *
	 * **A refusal gives back what was typed**, by key, for the form's redraw, and flashes what was wrong; an
	 * Airtable refusal, the details class's own refusals, and a save or a request that found the school's
	 * lock held keep it too. A second request, refused by the store because one truly waits, does not: the
	 * group draws no form while one waits.
	 *
	 * **A save at once is worked out again under the school's lock** (`save_now()`), so a change another tab
	 * made while this post waited is not made twice.
	 *
	 * @param string $group `GROUP_NOW` or `GROUP_CHECKED`.
	 */
	private static function handle( $group ) {
		$action = WPCPM_Institution_Details::GROUP_NOW === $group ? self::ACTION_SAVE : self::ACTION_ASK;
		$record = WPCPM_Request::posted_text( 'record' );

		check_admin_referer( $action . '_' . $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EDIT_DETAILS, WPCPM_Institution_Policy::subject_institution( $record ) );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$ground = (string) $decision['ground'];
		$actor  = get_current_user_id();
		// The first group is saved at once, and so is the second for the people who check it.
		$direct = WPCPM_Institution_Details::GROUP_NOW === $group || WPCPM_Institution_Policy::GROUND_MANAGER === $ground;

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( self::typed_form( $record, $group ) );

		// One request waits for the second group: a member is told so, and an Administrator, who does not
		// correct over the school's request, is told it is decided where requests are decided. Checked before
		// anything is read, so the refusal costs nothing and keeps nothing: the page draws no form for what
		// the rules would have marked. `raise_details()` stays the guard under the school's lock.
		if ( WPCPM_Institution_Details::GROUP_CHECKED === $group && 0 !== WPCPM_Institution_Request::open_details_for( $record ) ) {
			self::finish( $record, 'said', array( 'text' => $direct ? self::decide_sentence() : WPCPM_Institution_Request::open_message() ) );
		}

		// A request is made for an institution the program records hold, or it could not be approved.
		if ( ! $direct && ! WPCPM_Institutions_Index::has( $record ) ) {
			self::finish( $record, 'said', array( 'text' => __( 'This institution is not in the program records yet, so nothing was sent.', 'wpcredits-program-manager' ) ) );
		}

		$details   = WPCPM_Institution_Details::fields();
		$questions = WPCPM_Institution_Application::fields();
		$current   = WPCPM_Institution_Details::current( $record );
		$typed     = array();
		$untouched = array();

		foreach ( $details as $key => $spec ) {
			if ( $group !== $spec['group'] ) {
				continue;
			}

			if ( 'choices' === $spec['type'] ) {
				$typed[ $key ] = array_values( array_intersect( WPCPM_Request::posted_list( self::FIELD_PREFIX . $key, self::LIST_VALUE ), (array) $questions[ WPCPM_Institution_Details::question( $key ) ]['choices'] ) );

				continue;
			}

			$typed[ $key ] = WPCPM_Request::posted_raw( self::FIELD_PREFIX . $key );

			if ( in_array( $spec['type'], array( 'text', 'url', 'email' ), true ) && '' !== trim( (string) $current[ $key ] ) && self::unchanged( $typed[ $key ], $current[ $key ] ) ) {
				$untouched[ $key ] = true;
			}
		}

		// A text the cleaner would take words from is refused before anything is cleaned, and the form gets
		// back what was typed: saved, it would lose the words without a word said.
		foreach ( $typed as $key => $value ) {
			if ( 'text' === $details[ $key ]['type'] && ! isset( $untouched[ $key ] ) && WPCPM_Typed_Text::cleaner_loses( $value, 'line' ) ) {
				self::keep_typing( $record, $group, $typed, $current );
				self::finish( $record, 'said', array( 'text' => WPCPM_Typed_Text::loss_message( $questions[ WPCPM_Institution_Details::question( $key ) ]['label'], $direct ? 'save' : 'send' ) ) );
			}
		}

		$cleaned  = WPCPM_Institution_Details::clean( $group, $typed );
		$values   = $cleaned['values'];
		$problems = $cleaned['problems'];

		foreach ( array_keys( $untouched ) as $key ) {
			$values[ $key ] = $current[ $key ];

			unset( $problems[ $key ] );
		}

		if ( array() !== $problems ) {
			self::keep_typing( $record, $group, $typed, $current );
			self::finish(
				$record,
				'refused-' . $group,
				array(
					'group'    => $group,
					'problems' => $problems,
				)
			);
		}

		$changes = WPCPM_Institution_Details::changes( $record, $values );

		// A form that changes nothing sends nothing: no write, no request, no email.
		if ( array() === $changes ) {
			self::finish( $record, 'nothing' );
		}

		if ( $direct ) {
			$saved = self::save_now( $record, $values, $actor, $ground );

			// Another request holds the school's lock: nothing was done, and the typing is the person's to
			// send again.
			if ( 'busy' === $saved['status'] ) {
				self::keep_typing( $record, $group, $typed, $current );
				self::finish( $record, 'saving-busy' );
			}

			if ( is_wp_error( $saved['written'] ) ) {
				self::keep_typing( $record, $group, $typed, $current );
				self::refuse_write( $record, $saved['written'] );
			}

			// Another tab, or another account, made the same change while this post waited for the lock.
			if ( array() === $saved['changes'] ) {
				self::finish( $record, 'nothing' );
			}

			$changes = $saved['changes'];

			WPCPM_Institution_Request::mail_managers(
				self::MAIL_SAVED,
				static function () use ( $record, $changes, $actor ) {
					return array(
						'institution_name' => WPCPM_Institution_Request::institution_name( $record ),
						'actor_name'       => WPCPM_Institution_Request::person_name( $actor ),
						'changes'          => WPCPM_Institution_Details::lines( $changes, false ),
					);
				}
			);

			self::finish( $record, 'saved' );
		}

		$raised = WPCPM_Institution_Request::raise_details( $record, $changes, $actor, $ground );

		if ( is_wp_error( $raised ) ) {
			if ( 'wpcpm_request_open' !== $raised->get_error_code() ) {
				self::keep_typing( $record, $group, $typed, $current );
			}

			self::finish( $record, 'said', array( 'text' => $raised->get_error_message() ) );
		}

		self::finish( $record, 'asked' );
	}

	/**
	 * Save a group at once, under the school's lock: what is on record read again under it, the change
	 * worked out again from that, and written.
	 *
	 * Two tabs, or two accounts, posting the same correction both find a change before either has written
	 * it. The school's lock (`WPCPM_Institution_Request::school_lock_name()`, the one a request is raised
	 * under, through the shared `WPCPM_Lock`) lets one write at a time, and the second, reading again under
	 * it what the program has on record, finds nothing left to change: no second write to Airtable, no
	 * second history row, no second email. The lock is let go in a `finally`, on every path, and the caller
	 * says what came of it once it is let go, since the redirect that ends a request runs no `finally`.
	 *
	 * @param string $record Institutions record ID.
	 * @param array  $values The group's cleaned values, by key.
	 * @param int    $actor  Who saves.
	 * @param string $ground The decision's ground.
	 * @return array `status` (`busy` while another request holds the lock, and nothing was read or written;
	 *               else `done`), `changes` (worked out under the lock; none when nothing was left) and
	 *               `written` (what `WPCPM_Institution_Details::write()` answered, true when it was not asked).
	 */
	private static function save_now( $record, array $values, $actor, $ground ) {
		$lock  = WPCPM_Institution_Request::school_lock_name( $record );
		$token = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

		if ( '' === $token ) {
			return array(
				'status'  => 'busy',
				'changes' => array(),
				'written' => true,
			);
		}

		try {
			WPCPM_Institution_Details::forget_cached( $record );

			$changes = WPCPM_Institution_Details::changes( $record, $values );
			$written = array() === $changes ? true : WPCPM_Institution_Details::write( $record, $changes, $actor, $ground );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		return array(
			'status'  => 'done',
			'changes' => $changes,
			'written' => $written,
		);
	}

	/**
	 * Say why the details class would not write, in the words that fit.
	 *
	 * Its own refusals (an institution the program records do not hold, a record the site could not keep,
	 * a value that no longer meets its rules) carry sentences that say what happened; anything else is the
	 * base turning the change down, and is the one Airtable sentence.
	 *
	 * **This does not return**, for the reason `finish()` gives.
	 *
	 * @param string   $record Institutions record ID.
	 * @param WP_Error $error  What `WPCPM_Institution_Details::write()` answered.
	 */
	private static function refuse_write( $record, WP_Error $error ) {
		if ( in_array( $error->get_error_code(), array( 'wpcpm_details_index', 'wpcpm_details_record', 'wpcpm_details_value' ), true ) ) {
			self::finish( $record, 'said', array( 'text' => $error->get_error_message() ) );
		}

		self::finish( $record, 'airtable' );
	}

	/**
	 * Whether a posted box is the value the program has, as it was drawn.
	 *
	 * Read as the box reads it: the entities a stored text holds are read back, and each line break is a
	 * space, since the box is drawn that way (`field_parts()`). The test of whether a box posted back
	 * unedited changed anything.
	 *
	 * @param string $typed   What was posted.
	 * @param string $current What the program has.
	 * @return bool
	 */
	private static function unchanged( $typed, $current ) {
		$typed   = trim( (string) $typed );
		$current = trim( (string) $current );

		return $typed === $current
			|| WPCPM_Typed_Text::same_text( $typed, $current )
			|| WPCPM_Typed_Text::same_text( $typed, WPCPM_Typed_Text::one_line( $current ) );
	}

	/**
	 * The form, as `WPCPM_Typed_Text::keep()` names it: one group of one institution's card.
	 *
	 * @param string $record Institutions record ID.
	 * @param string $group  `GROUP_NOW` or `GROUP_CHECKED`.
	 * @return string
	 */
	private static function typed_form( $record, $group ) {
		return 'details:' . $record . ':' . $group;
	}

	/**
	 * Keep what a refused post typed, for the one time the form is drawn again.
	 *
	 * Every field of the group, as typed and not cleaned, each text up to the room its box was drawn with.
	 *
	 * @param string $record  Institutions record ID.
	 * @param string $group   `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param array  $typed   Key to what was posted: a string, or the chosen values of a list.
	 * @param array  $current What the program has on record.
	 */
	private static function keep_typing( $record, $group, array $typed, array $current ) {
		$questions = WPCPM_Institution_Application::fields();
		$details   = WPCPM_Institution_Details::fields();
		$room      = array();

		foreach ( $typed as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}

			$question = $questions[ WPCPM_Institution_Details::question( $key ) ];
			$limit    = 'country' === $details[ $key ]['type'] ? self::COUNTRY_ROOM : ( isset( $question['maxlength'] ) ? (int) $question['maxlength'] : 200 );

			$room[ $key ] = WPCPM_Typed_Text::kept_room( is_scalar( $current[ $key ] ) ? (string) $current[ $key ] : '', $limit );
		}

		WPCPM_Typed_Text::keep( self::typed_form( $record, $group ), $typed, $room );

		self::$kept_group = (string) $group;
	}

	/**
	 * Flash the outcome and go back to the card.
	 *
	 * **This does not return.** Every call to it ends the request, which is why a refusal above reads as one
	 * line and not as an early return with a branch around it. The destination is rebuilt here and not taken
	 * from the request: the Institution Dashboard's Your institution view, and for an Administrator the school
	 * they were viewing, through the switcher's argument, since the page would otherwise open on the first school
	 * with a member.
	 *
	 * @param string $record Institutions record ID the outcome is about.
	 * @param string $status A key of `messages()`, or `said` with the sentence in `$extra['text']`.
	 * @param array  $extra  `group` and `problems` of a refused post, or `text`; `section` set to `tracks` or
	 *                       `assignment` for the section's form, whose outcome is read under its own heading.
	 * @param string $anchor The id to land on, the card's own by default: a section's form lands on its outcome line, and
	 *                       a refusal that kept a group's typing on that group's fold, where the refusal is drawn
	 *                       (Gate B round 2, F3), the group named in the flash for the card to place it.
	 */
	public static function finish( $record, $status, array $extra = array(), $anchor = '' ) {
		$kept             = self::$kept_group;
		self::$kept_group = '';

		if ( '' !== $kept && ! isset( $extra['section'] ) ) {
			$extra  = array_merge( array( 'group' => $kept ), $extra );
			$anchor = '' !== $anchor ? $anchor : self::fold_id( $kept );
		}

		WPCPM_Flash::set(
			WPCPM_Institution_Request::FLASH_DETAILS,
			array_merge(
				array(
					'status' => $status,
					'record' => $record,
				),
				$extra
			)
		);

		$manage = current_user_can( WPCPM_Roles::CAP_MANAGE );

		// Your institution, the view that draws the card (`card_url()`), at the card or the section's outcome line.
		$url = WPCPM_Institutions_Dashboard::card_url( 'details', $manage ? (string) $record : '', '' !== $anchor ? (string) $anchor : self::ANCHOR );

		if ( '' === $url ) {
			$url = $manage ? add_query_arg( array( WPCPM_Institution_Roster::ARG_VIEW => $record ), home_url( '/' ) ) : home_url( '/' );
			$url = $url . '#' . ( '' !== $anchor ? $anchor : self::ANCHOR );
		}

		wp_safe_redirect( $url );
		exit;
	}
}
